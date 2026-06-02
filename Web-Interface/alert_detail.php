<?php
session_start();
if (!isset($_SESSION['user'])) { header('Location: login.php'); exit; }

$id = intval($_GET['id'] ?? 0);
if (!$id) { header('Location: alerts.php'); exit; }

require_once 'db.php';

// Load settings for API key
$settings = $pdo->query("SELECT setting_key, setting_value FROM settings")
                 ->fetchAll(PDO::FETCH_KEY_PAIR);
$API_KEY  = $settings['api_key'] ?? 'ids-api-key-change-me';
$OPENAI_KEY = getenv('OPENAI_KEY') ?: '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>IDS — Alert Detail</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .detail-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
            margin-bottom: 20px;
        }
        .detail-card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 10px;
            padding: 20px;
        }
        .detail-card h3 {
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            color: var(--text-muted);
            margin-bottom: 16px;
            padding-bottom: 10px;
            border-bottom: 1px solid var(--border);
        }
        .detail-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 8px 0;
            border-bottom: 1px solid var(--border);
            font-size: 13px;
        }
        .detail-row:last-child { border-bottom: none; }
        .detail-row .key {
            color: var(--text-muted);
            flex-shrink: 0;
            margin-right: 16px;
        }
        .detail-row .val {
            color: var(--text);
            text-align: right;
            font-family: monospace;
            font-size: 12px;
            word-break: break-all;
        }
        .severity-banner {
            display: flex;
            align-items: center;
            gap: 16px;
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 10px;
            padding: 20px 24px;
            margin-bottom: 20px;
        }
        .severity-icon {
            font-size: 36px;
            line-height: 1;
        }
        .severity-info h2 {
            font-size: 20px;
            font-weight: 600;
        }
        .severity-info p {
            font-size: 13px;
            color: var(--text-muted);
            margin-top: 4px;
        }
        .action-bar {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            margin-bottom: 20px;
        }
        .abuse-meter {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .abuse-bar {
            flex: 1;
            height: 6px;
            background: var(--surface2);
            border-radius: 3px;
            overflow: hidden;
        }
        .abuse-fill {
            height: 100%;
            border-radius: 3px;
            transition: width 0.5s ease;
        }
        .ai-box {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
        }
        .ai-box h3 {
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            color: var(--text-muted);
            margin-bottom: 12px;
        }
        .ai-response {
            font-size: 14px;
            line-height: 1.7;
            color: var(--text);
            min-height: 60px;
        }
        .ai-loading {
            display: flex;
            align-items: center;
            gap: 10px;
            color: var(--text-muted);
            font-size: 13px;
        }
        .toast-fixed {
            position: fixed;
            bottom: 24px;
            right: 24px;
            z-index: 999;
            display: flex;
            flex-direction: column;
            gap: 8px;
        }
        .toast {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 8px;
            padding: 12px 16px;
            font-size: 13px;
            animation: slideIn 0.3s ease;
            min-width: 220px;
        }
        .toast.success { border-left: 3px solid var(--success); }
        .toast.error   { border-left: 3px solid var(--danger); }
        @keyframes slideIn {
            from { opacity:0; transform:translateX(20px); }
            to   { opacity:1; transform:translateX(0); }
        }
        @media (max-width: 800px) {
            .detail-grid { grid-template-columns: 1fr; }
        }
    </style>
    <script src="assets/js/app.js" defer></script>
</head>
<body>
<div class="app-layout">
    <?php include 'nav.php'; ?>
    <main class="main-content">

        <div class="page-header" style="display:flex;align-items:center;justify-content:space-between">
            <div>
                <h2>Alert detail</h2>
                <p>Full information for alert #<?= $id ?></p>
            </div>
            <a href="alerts.php" class="btn btn-secondary" style="font-size:13px;padding:8px 16px">
                ← Back to alerts
            </a>
        </div>

        <div id="page-content">
            <div class="loading-overlay" style="padding:40px">
                <span class="spinner"></span>&nbsp; Loading alert...
            </div>
        </div>

        <div class="toast-fixed" id="toasts"></div>

    </main>
</div>

<script>
const ALERT_ID  = <?= $id ?>;
const HAS_OPENAI = <?= $OPENAI_KEY ? 'true' : 'false' ?>;
let   alertData  = null;

function severityIcon(s) {
    return {CRITICAL:'🔴', HIGH:'🟠', MEDIUM:'🟡', LOW:'🟢'}[s] || '⚪';
}

function severityColor(s) {
    return {
        CRITICAL:'var(--critical)',
        HIGH:'var(--high)',
        MEDIUM:'var(--medium)',
        LOW:'var(--low)'
    }[s] || 'var(--text-muted)';
}

function abuseColor(score) {
    if (score >= 75) return 'var(--danger)';
    if (score >= 40) return 'var(--warning)';
    return 'var(--success)';
}

function toast(msg, type='success') {
    const box       = document.createElement('div');
    box.className   = `toast ${type}`;
    box.textContent = msg;
    document.getElementById('toasts').appendChild(box);
    setTimeout(() => box.remove(), 4000);
}

async function loadAlert() {
    try {
        const res  = await fetch(`api_proxy.php?endpoint=alerts/${ALERT_ID}`);
        const data = await res.json();

        if (data.error) {
            document.getElementById('page-content').innerHTML =
                `<div style="color:var(--danger);padding:20px">Error: ${data.error}</div>`;
            return;
        }

        alertData = data;
        render(data);
    } catch (e) {
        document.getElementById('page-content').innerHTML =
            `<div style="color:var(--danger);padding:20px">
                Could not load alert. Is the Python engine running?
            </div>`;
    }
}

function render(a) {
    const conf      = a.confidence ? (parseFloat(a.confidence)*100).toFixed(1)+'%' : '—';
    const time      = new Date(a.timestamp).toLocaleString();
    const abuseScore = parseInt(a.abuse_score) || 0;

    document.getElementById('page-content').innerHTML = `

        <!-- Severity banner -->
        <div class="severity-banner" style="border-color:${severityColor(a.severity)}20">
            <div class="severity-icon">${severityIcon(a.severity)}</div>
            <div class="severity-info">
                <h2 style="color:${severityColor(a.severity)}">
                    ${(a.threat_type||'').replace(/_/g,' ')}
                </h2>
                <p>
                    Detected ${time} &nbsp;·&nbsp;
                    <span class="badge ${a.severity}">${a.severity}</span>
                    &nbsp;·&nbsp; Confidence: ${conf}
                    ${a.country ? '&nbsp;·&nbsp; ' + a.country : ''}
                </p>
            </div>
        </div>

        <!-- Action buttons -->
        <div class="action-bar">
            <button class="btn btn-danger" onclick="blockIP('${a.src_ip}')">
                🚫 Block ${a.src_ip}
            </button>
            <button class="btn btn-secondary" onclick="explainWithAI()"
                style="font-size:13px">
                🤖 ${HAS_OPENAI ? 'Explain with AI' : 'Explain with AI (add OpenAI key to .env)'}
            </button>
            <button class="btn btn-secondary"
                onclick="window.location.href='alerts.php?src_ip=${a.src_ip}'"
                style="font-size:13px">
                🔍 All alerts from this IP
            </button>
            <button class="btn btn-secondary"
                onclick="markRead()"
                style="font-size:13px">
                ✓ Mark read
            </button>
        </div>

        <!-- AI explanation box (hidden until clicked) -->
        <div class="ai-box" id="ai-box" style="display:none">
            <h3>AI explanation</h3>
            <div class="ai-response" id="ai-response">
                <div class="ai-loading">
                    <span class="spinner"></span> Asking AI to explain this threat...
                </div>
            </div>
        </div>

        <!-- Detail grid -->
        <div class="detail-grid">

            <!-- Network info -->
            <div class="detail-card">
                <h3>Network details</h3>
                <div class="detail-row">
                    <span class="key">Source IP</span>
                    <span class="val">${a.src_ip || '—'}</span>
                </div>
                <div class="detail-row">
                    <span class="key">Source port</span>
                    <span class="val">${a.src_port || '—'}</span>
                </div>
                <div class="detail-row">
                    <span class="key">Destination IP</span>
                    <span class="val">${a.dst_ip || '—'}</span>
                </div>
                <div class="detail-row">
                    <span class="key">Destination port</span>
                    <span class="val">${a.dst_port || '—'}</span>
                </div>
                <div class="detail-row">
                    <span class="key">Protocol</span>
                    <span class="val">${a.protocol || '—'}</span>
                </div>
                <div class="detail-row">
                    <span class="key">Country</span>
                    <span class="val">${a.country || '—'}</span>
                </div>
                <div class="detail-row">
                    <span class="key">Coordinates</span>
                    <span class="val">
                        ${a.latitude && a.longitude
                            ? a.latitude + ', ' + a.longitude
                            : '—'}
                    </span>
                </div>
            </div>

            <!-- Threat info -->
            <div class="detail-card">
                <h3>Threat analysis</h3>
                <div class="detail-row">
                    <span class="key">Threat type</span>
                    <span class="val" style="font-family:sans-serif;font-weight:500">
                        ${(a.threat_type||'').replace(/_/g,' ')}
                    </span>
                </div>
                <div class="detail-row">
                    <span class="key">Severity</span>
                    <span class="val">
                        <span class="badge ${a.severity}">${a.severity}</span>
                    </span>
                </div>
                <div class="detail-row">
                    <span class="key">Confidence</span>
                    <span class="val" style="color:${severityColor(a.severity)}">${conf}</span>
                </div>
                <div class="detail-row">
                    <span class="key">Blocked</span>
                    <span class="val" id="blocked-status">
                        ${a.is_blocked
                            ? '<span style="color:var(--danger)">Yes</span>'
                            : '<span style="color:var(--text-muted)">No</span>'}
                    </span>
                </div>
                <div class="detail-row">
                    <span class="key">AbuseIPDB score</span>
                    <span class="val" style="width:100%">
                        <div class="abuse-meter">
                            <span style="color:${abuseColor(abuseScore)};min-width:28px">
                                ${abuseScore}
                            </span>
                            <div class="abuse-bar">
                                <div class="abuse-fill"
                                    style="width:${abuseScore}%;background:${abuseColor(abuseScore)}">
                                </div>
                            </div>
                            <span style="color:var(--text-muted);font-size:11px">/100</span>
                        </div>
                    </span>
                </div>
                <div class="detail-row">
                    <span class="key">Alert ID</span>
                    <span class="val">#${a.id}</span>
                </div>
                <div class="detail-row">
                    <span class="key">Timestamp</span>
                    <span class="val">${time}</span>
                </div>
            </div>

        </div>

        <!-- Description card -->
        <div class="detail-card" style="margin-bottom:20px">
            <h3>Description</h3>
            <p style="font-size:13px;color:var(--text-muted);line-height:1.7;font-family:monospace">
                ${a.description || 'No description available.'}
            </p>
        </div>

    `;
}

async function blockIP(ip) {
    if (!ip) return;
    if (!confirm(`Block IP ${ip}? This will add it to the blacklist.`)) return;

    try {
        const res  = await fetch('api_proxy.php?endpoint=blacklist', {
            method:  'POST',
            headers: { 'Content-Type': 'application/json' },
            body:    JSON.stringify({
                ip,
                reason:    `Manually blocked from alert #${ALERT_ID}`,
                added_by:  '<?= htmlspecialchars($_SESSION['user']) ?>',
            }),
        });
        const data = await res.json();

        if (data.success) {
            toast(`${ip} added to blacklist`, 'success');
            document.getElementById('blocked-status').innerHTML =
                '<span style="color:var(--danger)">Yes</span>';
        } else {
            toast('Block failed: ' + (data.error||'unknown'), 'error');
        }
    } catch (e) {
        toast('Could not reach API', 'error');
    }
}

async function markRead() {
    toast('Marked as read', 'success');
}

async function explainWithAI() {
    const box = document.getElementById('ai-box');
    const res = document.getElementById('ai-response');
    box.style.display = 'block';
    box.scrollIntoView({ behavior: 'smooth', block: 'nearest' });

    res.innerHTML = `<div class="ai-loading">
        <span class="spinner"></span> Asking AI to explain this threat...
    </div>`;

    try {
        const r = await fetch('ai_explain.php', {
            method:  'POST',
            headers: { 'Content-Type': 'application/json' },
            body:    JSON.stringify(alertData),
        });
        const data = await r.json();

        if (data.explanation) {
            res.innerHTML = data.explanation
                .replace(/\n/g, '<br>')
                .replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>');
        } else if (data.error) {
            res.innerHTML = `<span style="color:var(--danger)">${data.error}</span>`;
        }
    } catch (e) {
        res.innerHTML = `<span style="color:var(--danger)">
            Could not get AI explanation. Check your OpenAI key in .env
        </span>`;
    }
}

loadAlert();
</script>
</body>
</html>