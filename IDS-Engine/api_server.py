import os
import json
import time
import threading
import queue
from datetime import datetime
from functools import wraps

import mysql.connector
import requests
from dotenv import load_dotenv
from flask import Flask, jsonify, request
from flask_socketio import SocketIO
from flask_cors import CORS

from detector import ThreatDetector
from rules    import RuleEngine
from alerter  import Alerter

load_dotenv()

# ── App setup ─────────────────────────────────────────────────────────────────

app      = Flask(__name__)
app.config['SECRET_KEY'] = os.getenv('SECRET_KEY', 'ids-secret')

CORS(app, origins='*')
socketio = SocketIO(app, cors_allowed_origins='*', async_mode='threading')

detector = ThreatDetector()
rules    = RuleEngine()
alerter  = Alerter()

if not detector.trained:
    detector.train_with_synthetic()

# ── Background enrichment queue ───────────────────────────────────────────────
# Instead of calling AbuseIPDB/ip-api.com inline (blocks the request),
# we store the alert in DB immediately with abuse_score=0, then enrich
# it asynchronously in a background worker thread.

enrichment_queue = queue.Queue(maxsize=500)

def enrichment_worker():
    """
    Background thread. Picks alerts off the queue, calls external APIs,
    updates the DB row, then emits the enriched alert via WebSocket.
    """
    while True:
        try:
            job = enrichment_queue.get(timeout=1)
            if job is None:
                break

            alert_id = job['alert_id']
            src_ip   = job['src_ip']
            alert    = job['alert']

            # External calls — these can take 1-3 seconds, that's fine here
            abuse_score = _check_abuseipdb(src_ip)
            geo         = _geolocate_ip(src_ip)

            description = (
                f"ML anomaly score: {alert.get('anomaly_score', 0)} | "
                f"Rate: {alert.get('pkt_rate', 0)} pkt/s | "
                f"AbuseIPDB: {abuse_score}/100 | "
                f"Country: {geo.get('country', '?')}"
            )

            # Update the DB row with enriched data
            try:
                db  = get_db()
                cur = db.cursor()
                cur.execute("""
                    UPDATE alerts
                    SET abuse_score  = %s,
                        latitude     = %s,
                        longitude    = %s,
                        country      = %s,
                        description  = %s
                    WHERE id = %s
                """, (
                    abuse_score,
                    geo.get('lat', 0),
                    geo.get('lng', 0),
                    geo.get('country', ''),
                    description,
                    alert_id,
                ))
                db.close()
            except Exception as e:
                print(f"[Enrichment] DB update error: {e}")

            # Emit enriched alert to dashboard
            full_alert = {
                **alert,
                'id':          alert_id,
                'abuse_score': abuse_score,
                'latitude':    geo.get('lat', 0),
                'longitude':   geo.get('lng', 0),
                'country':     geo.get('country', ''),
                'description': description,
                'timestamp':   alert.get('timestamp', datetime.now().strftime('%Y-%m-%d %H:%M:%S')),
            }
            socketio.emit('new_alert', full_alert)

            # Notifications
            settings = get_settings()
            alerter.handle(full_alert, settings)

            # Auto-block if abuse score very high
            auto_block = int(settings.get('auto_block_score', 90))
            if abuse_score >= auto_block and src_ip:
                try:
                    db  = get_db()
                    cur = db.cursor()
                    cur.execute("""
                        INSERT IGNORE INTO ip_blacklist (ip, reason, abuse_score)
                        VALUES (%s, %s, %s)
                    """, (src_ip, f'Auto-blocked: AbuseIPDB score {abuse_score}', abuse_score))
                    db.close()
                    print(f"[Enrichment] Auto-blocked {src_ip} (score {abuse_score})")
                except Exception as e:
                    print(f"[Enrichment] Auto-block error: {e}")

            enrichment_queue.task_done()

        except queue.Empty:
            continue
        except Exception as e:
            print(f"[Enrichment] Worker error: {e}")

# Start the background worker thread
worker_thread = threading.Thread(target=enrichment_worker, daemon=True)
worker_thread.start()
print("[Enrichment] Background worker started.")

# ── DB helper ─────────────────────────────────────────────────────────────────

def get_db():
    return mysql.connector.connect(
        host      = os.getenv('DB_HOST', 'localhost'),
        user      = os.getenv('DB_USER', 'root'),
        password  = os.getenv('DB_PASS', ''),
        database  = os.getenv('DB_NAME', 'ids'),
        autocommit= True
    )

def get_settings() -> dict:
    try:
        db  = get_db()
        cur = db.cursor(dictionary=True)
        cur.execute("SELECT setting_key, setting_value FROM settings")
        rows = cur.fetchall()
        db.close()
        return {r['setting_key']: r['setting_value'] for r in rows}
    except Exception as e:
        print(f"[DB] get_settings error: {e}")
        return {}

def load_rules_from_db():
    try:
        db  = get_db()
        cur = db.cursor(dictionary=True)
        cur.execute("SELECT * FROM rules WHERE enabled = 1")
        db_rules = cur.fetchall()
        db.close()
        rules.load_from_db(db_rules)
    except Exception as e:
        print(f"[DB] load_rules error: {e}")

load_rules_from_db()

# ── Auth decorator ────────────────────────────────────────────────────────────

def require_api_key(f):
    @wraps(f)
    def decorated(*args, **kwargs):
        key = request.headers.get('X-API-Key', '')
        if key != os.getenv('API_KEY', 'ids-api-key-change-me'):
            return jsonify({'error': 'Unauthorized'}), 401
        return f(*args, **kwargs)
    return decorated

# ── External APIs (called only from background thread) ────────────────────────

def _check_abuseipdb(ip: str) -> int:
    key = os.getenv('ABUSEIPDB_KEY', '')
    if not key or not ip or ip.startswith(('192.168', '10.', '127.', '172.')):
        return 0
    try:
        r = requests.get(
            'https://api.abuseipdb.com/api/v2/check',
            headers={'Key': key, 'Accept': 'application/json'},
            params={'ipAddress': ip, 'maxAgeInDays': 90},
            timeout=5
        )
        return int(r.json()['data']['abuseConfidenceScore'])
    except Exception:
        return 0

def _geolocate_ip(ip: str) -> dict:
    if not ip or ip.startswith(('192.168', '10.', '127.', '172.')):
        return {'lat': 0, 'lng': 0, 'country': 'Local'}
    try:
        r = requests.get(f'http://ip-api.com/json/{ip}', timeout=5)
        d = r.json()
        if d.get('status') == 'success':
            return {
                'lat':     d.get('lat', 0),
                'lng':     d.get('lon', 0),
                'country': d.get('country', ''),
            }
    except Exception:
        pass
    return {'lat': 0, 'lng': 0, 'country': ''}

def _is_blacklisted(ip: str) -> bool:
    try:
        db  = get_db()
        cur = db.cursor()
        cur.execute("SELECT ip FROM ip_blacklist WHERE ip = %s", (ip,))
        result = cur.fetchone()
        db.close()
        return result is not None
    except Exception:
        return False

# ── Routes ────────────────────────────────────────────────────────────────────

@app.route('/api/health', methods=['GET'])
def health():
    return jsonify({
        'status':  'online',
        'model':   'trained' if detector.trained else 'untrained',
        'rules':   len(rules.rules),
        'queue':   enrichment_queue.qsize(),
        'time':    datetime.now().strftime('%Y-%m-%d %H:%M:%S'),
    })


@app.route('/api/analyze', methods=['POST'])
@require_api_key
def analyze_packet():
    """
    Fast path — runs ML + rules, saves to DB instantly, returns response.
    External API calls happen in background thread (non-blocking).
    """
    try:
        data = request.get_json(force=True)
        if not data:
            return jsonify({'error': 'No JSON body'}), 400

        src_ip = data.get('src_ip', '')

        # Blacklist check — fast DB lookup
        if _is_blacklisted(src_ip):
            return jsonify({
                'is_threat':   True,
                'threat_type': 'BLACKLISTED',
                'severity':    'CRITICAL',
                'confidence':  1.0,
                'anomaly_score': 0,
                'pkt_rate':    0,
            })

        # ML detection — fast, in-process
        result = detector.predict(data)

        # Rule engine — fast, in-process
        rule_match = rules.check(data)
        if rule_match and not result['is_threat']:
            result['is_threat']   = True
            result['threat_type'] = rule_match['threat']
            result['severity']    = 'HIGH'
            result['confidence']  = 0.9

        if result['is_threat']:
            timestamp = datetime.now().strftime('%Y-%m-%d %H:%M:%S')

            # Save to DB immediately (no external calls yet)
            try:
                db  = get_db()
                cur = db.cursor()
                cur.execute("""
                    INSERT INTO alerts
                    (src_ip, dst_ip, src_port, dst_port, protocol,
                     threat_type, severity, confidence, description, timestamp)
                    VALUES (%s,%s,%s,%s,%s,%s,%s,%s,%s,%s)
                """, (
                    src_ip,
                    data.get('dst_ip'),
                    data.get('src_port'),
                    data.get('dst_port'),
                    data.get('protocol', 'TCP'),
                    result['threat_type'],
                    result['severity'],
                    result['confidence'],
                    f"Detected: {result['threat_type']} | Rate: {result.get('pkt_rate',0)} pkt/s",
                    timestamp,
                ))
                alert_id = cur.lastrowid
                db.close()
            except Exception as e:
                print(f"[API] DB insert error: {e}")
                alert_id = None

            # Queue enrichment job (non-blocking — returns instantly)
            if alert_id:
                job = {
                    'alert_id': alert_id,
                    'src_ip':   src_ip,
                    'alert':    {
                        **data,
                        **result,
                        'id':        alert_id,
                        'timestamp': timestamp,
                    }
                }
                try:
                    enrichment_queue.put_nowait(job)
                except queue.Full:
                    # Queue full during flood — skip enrichment for this packet
                    # Still emit basic alert to dashboard immediately
                    socketio.emit('new_alert', {
                        **data,
                        **result,
                        'id':        alert_id,
                        'timestamp': timestamp,
                    })

        # Return immediately — no waiting for external APIs
        return jsonify(result)

    except Exception as e:
        print(f"[API] /analyze error: {e}")
        return jsonify({'error': str(e)}), 500


@app.route('/api/stats', methods=['GET'])
@require_api_key
def get_stats():
    try:
        db  = get_db()
        cur = db.cursor(dictionary=True)

        cur.execute("""
            SELECT severity, COUNT(*) as count
            FROM alerts
            WHERE timestamp >= NOW() - INTERVAL 24 HOUR
            GROUP BY severity
        """)
        by_severity = {row['severity']: row['count'] for row in cur.fetchall()}

        cur.execute("SELECT COUNT(*) as total FROM alerts WHERE timestamp >= NOW() - INTERVAL 24 HOUR")
        total = cur.fetchone()['total']

        cur.execute("SELECT COUNT(*) as total FROM alerts WHERE is_read = 0")
        unread = cur.fetchone()['total']

        cur.execute("SELECT COUNT(*) as total FROM ip_blacklist")
        blacklisted = cur.fetchone()['total']

        cur.execute("""
            SELECT threat_type, COUNT(*) as count
            FROM alerts
            WHERE timestamp >= NOW() - INTERVAL 24 HOUR
            GROUP BY threat_type
            ORDER BY count DESC
            LIMIT 5
        """)
        top_threats = cur.fetchall()

        cur.execute("""
            SELECT src_ip, COUNT(*) as count
            FROM alerts
            WHERE timestamp >= NOW() - INTERVAL 24 HOUR
            GROUP BY src_ip
            ORDER BY count DESC
            LIMIT 5
        """)
        top_ips = cur.fetchall()

        cur.execute("""
            SELECT DATE_FORMAT(timestamp, '%H:00') as hour, COUNT(*) as count
            FROM alerts
            WHERE timestamp >= NOW() - INTERVAL 24 HOUR
            GROUP BY hour
            ORDER BY hour
        """)
        hourly = cur.fetchall()

        db.close()
        return jsonify({
            'total_24h':   total,
            'unread':      unread,
            'blacklisted': blacklisted,
            'by_severity': by_severity,
            'top_threats': top_threats,
            'top_ips':     top_ips,
            'hourly':      hourly,
        })
    except Exception as e:
        return jsonify({'error': str(e)}), 500


@app.route('/api/alerts', methods=['GET'])
@require_api_key
def get_alerts():
    try:
        limit    = int(request.args.get('limit', 50))
        offset   = int(request.args.get('offset', 0))
        severity = request.args.get('severity', '')
        threat   = request.args.get('threat_type', '')
        src_ip   = request.args.get('src_ip', '')

        query  = "SELECT * FROM alerts WHERE 1=1"
        params = []

        if severity:
            query += " AND severity = %s"
            params.append(severity)
        if threat:
            query += " AND threat_type = %s"
            params.append(threat)
        if src_ip:
            query += " AND src_ip LIKE %s"
            params.append(f"%{src_ip}%")

        query += " ORDER BY timestamp DESC LIMIT %s OFFSET %s"
        params.extend([limit, offset])

        db  = get_db()
        cur = db.cursor(dictionary=True)
        cur.execute(query, params)
        alerts = cur.fetchall()

        cur.execute("SELECT COUNT(*) as total FROM alerts")
        total  = cur.fetchone()['total']
        db.close()

        for a in alerts:
            if a.get('timestamp'):
                a['timestamp'] = str(a['timestamp'])

        return jsonify({'alerts': alerts, 'total': total})
    except Exception as e:
        return jsonify({'error': str(e)}), 500


@app.route('/api/alerts/<int:alert_id>', methods=['GET'])
@require_api_key
def get_alert(alert_id):
    try:
        db  = get_db()
        cur = db.cursor(dictionary=True)
        cur.execute("SELECT * FROM alerts WHERE id = %s", (alert_id,))
        alert = cur.fetchone()
        cur.execute("UPDATE alerts SET is_read = 1 WHERE id = %s", (alert_id,))
        db.close()
        if not alert:
            return jsonify({'error': 'Alert not found'}), 404
        if alert.get('timestamp'):
            alert['timestamp'] = str(alert['timestamp'])
        return jsonify(alert)
    except Exception as e:
        return jsonify({'error': str(e)}), 500


@app.route('/api/rules', methods=['GET'])
@require_api_key
def get_rules():
    try:
        db  = get_db()
        cur = db.cursor(dictionary=True)
        cur.execute("SELECT * FROM rules ORDER BY id")
        result = cur.fetchall()
        db.close()
        return jsonify(result)
    except Exception as e:
        return jsonify({'error': str(e)}), 500


@app.route('/api/rules', methods=['POST'])
@require_api_key
def add_rule():
    try:
        data = request.get_json()
        db   = get_db()
        cur  = db.cursor()
        cur.execute(
            "INSERT INTO rules (name, pattern, action) VALUES (%s, %s, %s)",
            (data['name'], data.get('pattern', ''), data.get('action', 'ALERT'))
        )
        db.close()
        load_rules_from_db()
        return jsonify({'success': True})
    except Exception as e:
        return jsonify({'error': str(e)}), 500


@app.route('/api/rules/<int:rule_id>/toggle', methods=['POST'])
@require_api_key
def toggle_rule(rule_id):
    try:
        db  = get_db()
        cur = db.cursor()
        cur.execute("UPDATE rules SET enabled = NOT enabled WHERE id = %s", (rule_id,))
        db.close()
        load_rules_from_db()
        return jsonify({'success': True})
    except Exception as e:
        return jsonify({'error': str(e)}), 500

@app.route('/api/rules/<int:rule_id>', methods=['DELETE'])
@require_api_key
def delete_rule(rule_id):
    try:
        db  = get_db()
        cur = db.cursor()
        cur.execute("DELETE FROM rules WHERE id = %s", (rule_id,))
        db.close()
        load_rules_from_db()
        return jsonify({'success': True})
    except Exception as e:
        return jsonify({'error': str(e)}), 500


@app.route('/api/blacklist', methods=['GET'])
@require_api_key
def get_blacklist():
    try:
        db  = get_db()
        cur = db.cursor(dictionary=True)
        cur.execute("SELECT * FROM ip_blacklist ORDER BY added_at DESC")
        result = cur.fetchall()
        db.close()
        for r in result:
            if r.get('added_at'):
                r['added_at'] = str(r['added_at'])
        return jsonify(result)
    except Exception as e:
        return jsonify({'error': str(e)}), 500


@app.route('/api/blacklist', methods=['POST'])
@require_api_key
def add_to_blacklist():
    try:
        data = request.get_json()
        ip   = data.get('ip', '').strip()
        if not ip:
            return jsonify({'error': 'IP required'}), 400
        db  = get_db()
        cur = db.cursor()
        cur.execute(
            "INSERT IGNORE INTO ip_blacklist (ip, reason, added_by) VALUES (%s, %s, %s)",
            (ip, data.get('reason', 'Manual block'), data.get('added_by', 'admin'))
        )
        db.close()
        return jsonify({'success': True})
    except Exception as e:
        return jsonify({'error': str(e)}), 500


@app.route('/api/blacklist/<ip>', methods=['DELETE'])
@require_api_key
def remove_from_blacklist(ip):
    try:
        db  = get_db()
        cur = db.cursor()
        cur.execute("DELETE FROM ip_blacklist WHERE ip = %s", (ip,))
        db.close()
        return jsonify({'success': True})
    except Exception as e:
        return jsonify({'error': str(e)}), 500


@app.route('/api/logs', methods=['GET'])
@require_api_key
def get_logs():
    try:
        limit = int(request.args.get('limit', 100))
        db    = get_db()
        cur   = db.cursor(dictionary=True)
        cur.execute("SELECT * FROM logs ORDER BY timestamp DESC LIMIT %s", (limit,))
        result = cur.fetchall()
        db.close()
        for r in result:
            if r.get('timestamp'):
                r['timestamp'] = str(r['timestamp'])
        return jsonify(result)
    except Exception as e:
        return jsonify({'error': str(e)}), 500


# ── WebSocket events ──────────────────────────────────────────────────────────

@socketio.on('connect')
def on_connect():
    print(f"[WS] Client connected: {request.sid}")

@socketio.on('disconnect')
def on_disconnect():
    print(f"[WS] Client disconnected: {request.sid}")


# ── Entry point ───────────────────────────────────────────────────────────────

if __name__ == '__main__':
    print("=" * 50)
    print("  IDS Python Engine starting...")
    print("  API:       http://localhost:5000/api")
    print("  Health:    http://localhost:5000/api/health")
    print("  WebSocket: ws://localhost:5000")
    print("=" * 50)
    socketio.run(app, host='0.0.0.0', port=5000, debug=True)