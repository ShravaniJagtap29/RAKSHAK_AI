import numpy as np
from sklearn.ensemble import IsolationForest, RandomForestClassifier
from sklearn.preprocessing import StandardScaler
from collections import defaultdict, deque
import time
import pickle
import os

class ThreatDetector:

    def __init__(self):
        self.anomaly_model  = IsolationForest(
            contamination=0.05,
            n_estimators=100,
            random_state=42
        )
        self.scaler         = StandardScaler()
        self.trained        = False
        self.model_path     = 'ids_model.pkl'

        # Per-IP sliding window for rate tracking
        # ip_windows[ip] = deque of timestamps
        self.ip_windows     = defaultdict(lambda: deque(maxlen=1000))

        # Per-IP port tracking for port scan detection
        # ip_ports[ip] = set of destination ports seen in last 10 seconds
        self.ip_ports       = defaultdict(set)
        self.ip_port_times  = defaultdict(float)

        self.SCAN_WINDOW    = 10    # seconds
        self.SCAN_THRESHOLD = 15    # unique ports in window = port scan

        if os.path.exists(self.model_path):
            self.load_model()

    # ── Feature extraction ───────────────────────────────────────────────

    def extract_features(self, packet_info: dict) -> list:
        """
        Turn a raw packet dict into a fixed-length numeric feature vector.
        Every key has a safe default so missing fields never crash.
        """
        src_ip   = packet_info.get('src_ip', '0.0.0.0')
        dst_port = int(packet_info.get('dst_port', 0))
        src_port = int(packet_info.get('src_port', 0))

        pkt_rate    = self._get_rate(src_ip)
        is_scan     = self._check_port_scan(src_ip, dst_port)

        # Known malicious port flag
        bad_ports   = {4444, 1337, 31337, 6666, 6667, 9001, 9002}
        is_bad_port = 1 if dst_port in bad_ports else 0

        # Common service ports
        service_ports = {80, 443, 22, 21, 25, 53, 3306, 3389, 8080, 8443}
        is_service    = 1 if dst_port in service_ports else 0

        features = [
            float(packet_info.get('packet_size', 0)),
            float(src_port),
            float(dst_port),
            float(packet_info.get('protocol_num', 0)),   # TCP=6 UDP=17 ICMP=1
            float(packet_info.get('flags', 0)),           # TCP flags as int
            float(packet_info.get('ttl', 64)),
            float(pkt_rate),
            float(1 if is_scan else 0),
            float(is_bad_port),
            float(is_service),
        ]
        return features

    # ── Rate tracking ────────────────────────────────────────────────────

    def _get_rate(self, ip: str) -> float:
        """Return packets per second from this IP in the last 2 seconds."""
        now    = time.time()
        window = self.ip_windows[ip]
        window.append(now)
        cutoff = now - 2.0          # wider window — catches HTTP overhead
        count  = sum(1 for t in window if t >= cutoff)
        return float(count / 2.0)  # normalise to per-second rate

    def _check_port_scan(self, ip: str, dst_port: int) -> bool:
        """Return True if this IP has hit more than SCAN_THRESHOLD ports in SCAN_WINDOW seconds."""
        now = time.time()
        last_reset = self.ip_port_times.get(ip, 0)

        if now - last_reset > self.SCAN_WINDOW:
            self.ip_ports[ip]      = set()
            self.ip_port_times[ip] = now

        self.ip_ports[ip].add(dst_port)
        return len(self.ip_ports[ip]) >= self.SCAN_THRESHOLD

    # ── Training ─────────────────────────────────────────────────────────

    def train(self, normal_traffic_samples: list):
        """
        Train the Isolation Forest on normal traffic.
        Pass a list of packet_info dicts captured during a known-clean period.
        """
        if len(normal_traffic_samples) < 20:
            print("[Detector] Not enough samples to train (need ≥ 20). Skipping.")
            return

        X = np.array([self.extract_features(p) for p in normal_traffic_samples])
        X_scaled = self.scaler.fit_transform(X)
        self.anomaly_model.fit(X_scaled)
        self.trained = True
        self.save_model()
        print(f"[Detector] Trained on {len(normal_traffic_samples)} samples.")

    def train_with_synthetic(self):
        """
        Train immediately using synthetic normal traffic so the model
        is usable from day 1 without capturing real traffic first.
        """
        print("[Detector] Generating synthetic training data...")
        samples = []
        import random
        random.seed(42)

        for _ in range(500):
            samples.append({
                'packet_size':   random.randint(40, 1500),
                'src_port':      random.randint(1024, 65535),
                'dst_port':      random.choice([80, 443, 53, 22, 25, 3306, 8080]),
                'protocol_num':  random.choice([6, 17]),
                'flags':         random.choice([0, 2, 16, 18, 24]),
                'ttl':           random.randint(48, 128),
                'src_ip':        f"192.168.1.{random.randint(1, 50)}",
            })

        self.train(samples)

    # ── Prediction ───────────────────────────────────────────────────────

    def predict(self, packet_info: dict) -> dict:
        """
        Main entry point. Returns a result dict with all threat fields.
        """
        src_ip   = packet_info.get('src_ip', '')
        dst_port = int(packet_info.get('dst_port', 0))
        is_scan  = self._check_port_scan(src_ip, dst_port)
        pkt_rate = self._get_rate(src_ip)

        packet_info['is_port_scan'] = is_scan
        packet_info['pkt_rate']     = pkt_rate

        # Rule-based classification first (always runs)
        threat_type = self._classify_threat(packet_info)

        # Anomaly score from ML model
        anomaly_score = 0.0
        is_anomaly    = False

        if self.trained:
            try:
                features  = np.array(self.extract_features(packet_info)).reshape(1, -1)
                scaled    = self.scaler.transform(features)
                score     = float(self.anomaly_model.decision_function(scaled)[0])
                is_anomaly = self.anomaly_model.predict(scaled)[0] == -1
                anomaly_score = round(score, 4)
            except Exception as e:
                print(f"[Detector] Prediction error: {e}")

        severity   = self._get_severity(threat_type, anomaly_score)
        confidence = self._get_confidence(threat_type, anomaly_score)
        is_threat  = (threat_type != 'NORMAL') or is_anomaly

        return {
            'is_threat':     is_threat,
            'threat_type':   threat_type,
            'severity':      severity,
            'confidence':    confidence,
            'anomaly_score': anomaly_score,
            'is_anomaly':    is_anomaly,
            'pkt_rate':      round(pkt_rate, 2),
            'is_port_scan':  is_scan,
        }

    # ── Classification logic ─────────────────────────────────────────────

    def _classify_threat(self, p: dict) -> str:
        dst_port = int(p.get('dst_port', 0))
        src_port = int(p.get('src_port', 0))
        pkt_rate = float(p.get('pkt_rate', 0))
        flags    = int(p.get('flags', 0))
        size     = int(p.get('packet_size', 0))
        protocol = int(p.get('protocol_num', 0))

        # C2 — triggers immediately on port match, no rate needed
        c2_ports = {4444, 1337, 31337, 6666, 6667, 9001, 9002, 1234, 8888}
        if dst_port in c2_ports or src_port in c2_ports:
            return 'C2_COMMUNICATION'

        # Telnet
        if dst_port == 23:
            return 'TELNET_ATTEMPT'

        # SYN flood — lowered from 100 to 40 for simulator testing
        if flags == 0x02 and pkt_rate > 40:
            return 'SYN_FLOOD'

        # DoS — lowered from 500 to 200 for simulator testing
        if pkt_rate > 200:
            return 'DOS_ATTACK'

        # UDP flood
        if protocol == 17 and pkt_rate > 80 and size > 500:
            return 'UDP_FLOOD'

        # DNS amplification
        if protocol == 17 and (dst_port == 53 or src_port == 53) and size > 512:
            return 'DNS_AMPLIFICATION'

        # Brute force — lowered from 10 to 5
        auth_ports = {22, 23, 3389, 5900, 21, 25, 110, 143}
        if dst_port in auth_ports and pkt_rate > 5:
            return 'BRUTE_FORCE'

        # Port scan
        if p.get('is_port_scan'):
            return 'PORT_SCAN'

        # ICMP flood
        if protocol == 1 and pkt_rate > 20:
            return 'ICMP_FLOOD'

        # Malformed
        if size > 65000:
             return 'MALFORMED_PACKET'

        return 'NORMAL'

    def _get_severity(self, threat_type: str, anomaly_score: float) -> str:
        critical = {'C2_COMMUNICATION', 'SYN_FLOOD', 'DOS_ATTACK', 'UDP_FLOOD'}
        high     = {'BRUTE_FORCE', 'PORT_SCAN', 'DNS_AMPLIFICATION', 'ICMP_FLOOD'}
        medium   = {'MALFORMED_PACKET'}

        if threat_type in critical:
            return 'CRITICAL'
        if threat_type in high:
            return 'HIGH'
        if threat_type in medium:
            return 'MEDIUM'
        if anomaly_score < -0.2:
            return 'MEDIUM'
        if anomaly_score < -0.1:
            return 'LOW'
        return 'LOW'

    def _get_confidence(self, threat_type: str, anomaly_score: float) -> float:
        if threat_type != 'NORMAL':
            # Rule-based hits are high confidence
            return round(min(0.95, 0.75 + abs(anomaly_score) * 0.2), 3)
        return round(min(0.99, abs(anomaly_score)), 3)

    # ── Persistence ──────────────────────────────────────────────────────

    def save_model(self):
        with open(self.model_path, 'wb') as f:
            pickle.dump({'model': self.anomaly_model, 'scaler': self.scaler}, f)
        print(f"[Detector] Model saved to {self.model_path}")

    def load_model(self):
        try:
            with open(self.model_path, 'rb') as f:
                data = pickle.load(f)
            self.anomaly_model = data['model']
            self.scaler        = data['scaler']
            self.trained       = True
            print("[Detector] Model loaded from disk.")
        except Exception as e:
            print(f"[Detector] Could not load model: {e}")