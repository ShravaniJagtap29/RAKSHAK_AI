"""
Fixed simulator — sends rapid bursts to trigger rate-based detection.
Usage: python simulator.py
"""
import requests
import random
import time
import threading

API     = 'http://localhost:5000/api/analyze'
HEADERS = {'X-API-Key': 'ids-api-key-change-me', 'Content-Type': 'application/json'}

NORMAL_IPS  = [f"192.168.1.{i}" for i in range(1, 30)]
ATTACK_IPS  = ['45.33.32.156', '185.220.101.5', '91.108.4.1', '194.165.16.10']
SAFE_PORTS  = [80, 443, 53, 8080, 3306]
BAD_PORTS   = [4444, 1337, 31337, 6666]

sent     = 0
detected = 0

def post(payload):
    global sent, detected
    try:
        r      = requests.post(API, json=payload, headers=HEADERS, timeout=3)
        result = r.json()
        sent  += 1
        if result.get('is_threat'):
            detected += 1
            print(f"  [{result.get('severity'):8s}] {result.get('threat_type'):25s} "
                  f"from {payload.get('src_ip'):16s} "
                  f"confidence={result.get('confidence')}")
        return result
    except requests.exceptions.ConnectionError:
        print("  Cannot connect — is api_server.py running?")
        time.sleep(2)
    except Exception as e:
        print(f"  Error: {e}")

# ── Attack scenarios ──────────────────────────────────────────────────────────

def attack_c2():
    """C2 port — triggers immediately, no rate needed."""
    print("\n>> Sending C2 communication packets...")
    for _ in range(5):
        post({
            'src_ip':       random.choice(ATTACK_IPS),
            'dst_ip':       '192.168.1.100',
            'src_port':     random.randint(1024, 65535),
            'dst_port':     random.choice(BAD_PORTS),
            'protocol':     'TCP',
            'protocol_num': 6,
            'packet_size':  random.randint(100, 600),
            'flags':        24,
            'ttl':          128,
        })
        time.sleep(0.05)

def attack_syn_flood():
    """Send 150 SYN packets from same IP within 1 second to trigger rate detection."""
    print("\n>> Sending SYN flood (150 packets in 1 second)...")
    ip = random.choice(ATTACK_IPS)
    for _ in range(150):
        post({
            'src_ip':       ip,
            'dst_ip':       '192.168.1.100',
            'src_port':     random.randint(1024, 65535),
            'dst_port':     80,
            'protocol':     'TCP',
            'protocol_num': 6,
            'packet_size':  60,
            'flags':        2,
            'ttl':          64,
        })
        # No sleep — send as fast as possible within 1 second

def attack_port_scan():
    """Hit 20 different ports from same IP to trigger port scan detection."""
    print("\n>> Sending port scan (20 unique ports)...")
    ip = random.choice(ATTACK_IPS)
    ports = random.sample(range(1, 10000), 20)
    for port in ports:
        post({
            'src_ip':       ip,
            'dst_ip':       '192.168.1.100',
            'src_port':     random.randint(1024, 65535),
            'dst_port':     port,
            'protocol':     'TCP',
            'protocol_num': 6,
            'packet_size':  60,
            'flags':        2,
            'ttl':          64,
        })
        time.sleep(0.02)

def attack_brute_force():
    """Rapid hits on SSH port 22 from same IP."""
    print("\n>> Sending brute force (SSH port 22, 20 packets fast)...")
    ip = random.choice(ATTACK_IPS)
    for _ in range(20):
        post({
            'src_ip':       ip,
            'dst_ip':       '192.168.1.100',
            'src_port':     random.randint(1024, 65535),
            'dst_port':     22,
            'protocol':     'TCP',
            'protocol_num': 6,
            'packet_size':  80,
            'flags':        2,
            'ttl':          64,
        })
        time.sleep(0.04)

def attack_dos():
    """500+ packets per second from same IP."""
    print("\n>> Sending DoS flood (500+ packets)...")
    ip = random.choice(ATTACK_IPS)
    for _ in range(520):
        post({
            'src_ip':       ip,
            'dst_ip':       '192.168.1.100',
            'src_port':     random.randint(1024, 65535),
            'dst_port':     80,
            'protocol':     'TCP',
            'protocol_num': 6,
            'packet_size':  1400,
            'flags':        16,
            'ttl':          64,
        })

def normal_traffic(count=10):
    """Send normal background traffic."""
    for _ in range(count):
        post({
            'src_ip':       random.choice(NORMAL_IPS),
            'dst_ip':       '192.168.1.100',
            'src_port':     random.randint(1024, 65535),
            'dst_port':     random.choice(SAFE_PORTS),
            'protocol':     random.choice(['TCP', 'UDP']),
            'protocol_num': random.choice([6, 17]),
            'packet_size':  random.randint(64, 1400),
            'flags':        random.choice([2, 16, 18, 24]),
            'ttl':          random.randint(60, 128),
        })
        time.sleep(0.05)

# ── Main loop ─────────────────────────────────────────────────────────────────

ATTACK_SEQUENCE = [
    attack_c2,
    attack_port_scan,
    attack_brute_force,
    attack_syn_flood,
    attack_dos,
]

print("=" * 60)
print("  IDS Simulator — Fixed version")
print("  API:", API)
print("=" * 60)
print("\nRunning attack sequence first to verify detection...\n")

# Run each attack type once immediately so you see output right away
for attack_fn in ATTACK_SEQUENCE:
    attack_fn()
    time.sleep(1)

print(f"\n{'='*60}")
print(f"  Initial sequence done. Sent={sent}, Detected={detected}")
print(f"  Now running continuous mixed traffic loop...")
print(f"  Press Ctrl+C to stop.")
print(f"{'='*60}\n")

# Continuous loop
cycle = 0
while True:
    try:
        cycle += 1

        # Normal traffic
        normal_traffic(random.randint(5, 15))

        # Random attack every ~3 cycles
        if cycle % 3 == 0:
            random.choice(ATTACK_SEQUENCE)()

        if cycle % 10 == 0:
            print(f"\n  -- Cycle {cycle} | Total sent: {sent} | Threats detected: {detected} --\n")

        time.sleep(0.5)

    except KeyboardInterrupt:
        print(f"\n\nStopped.")
        print(f"Total packets sent:      {sent}")
        print(f"Threats detected:        {detected}")
        if sent > 0:
            print(f"Detection rate:          {(detected/sent*100):.1f}%")
        break