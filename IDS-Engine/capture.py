"""
Real packet capture engine using Scapy.
Run as Administrator for full packet access.
Usage: python capture.py
       python capture.py --iface "Wi-Fi"
       python capture.py --iface "Ethernet" --filter "tcp"
"""

import sys
import time
import argparse
import requests
import threading
from collections import defaultdict, deque
from datetime import datetime

# Scapy import with Windows fix
try:
    from scapy.all import sniff, IP, TCP, UDP, ICMP, get_if_list, conf
    SCAPY_OK = True
except ImportError:
    print("[Capture] Scapy not installed. Run: pip install scapy")
    SCAPY_OK = False

API     = 'http://localhost:5000/api/analyze'
HEADERS = {'X-API-Key': 'ids-api-key-change-me', 'Content-Type': 'application/json'}

# Per-IP sliding window for rate calculation
ip_windows    = defaultdict(lambda: deque(maxlen=2000))
ip_ports      = defaultdict(set)
ip_port_times = defaultdict(float)

SCAN_WINDOW    = 10
SCAN_THRESHOLD = 15

stats = {
    'captured': 0,
    'sent':     0,
    'errors':   0,
    'threats':  0,
}

# ── Rate tracking ─────────────────────────────────────────────────────────────

def get_rate(ip: str) -> float:
    now    = time.time()
    window = ip_windows[ip]
    window.append(now)
    cutoff = now - 2.0
    count  = sum(1 for t in window if t >= cutoff)
    return round(count / 2.0, 2)

def check_port_scan(ip: str, dst_port: int) -> bool:
    now        = time.time()
    last_reset = ip_port_times.get(ip, 0)
    if now - last_reset > SCAN_WINDOW:
        ip_ports[ip]      = set()
        ip_port_times[ip] = now
    ip_ports[ip].add(dst_port)
    return len(ip_ports[ip]) >= SCAN_THRESHOLD

# ── Packet processor ──────────────────────────────────────────────────────────

def process_packet(pkt):
    try:
        if not pkt.haslayer(IP):
            return

        ip_layer = pkt[IP]
        src_ip   = ip_layer.src
        dst_ip   = ip_layer.dst
        ttl      = ip_layer.ttl
        size     = len(pkt)

        # Skip loopback
        if src_ip.startswith('127.') or dst_ip.startswith('127.'):
            return

        src_port    = 0
        dst_port    = 0
        flags       = 0
        protocol    = 'OTHER'
        protocol_num = ip_layer.proto

        if pkt.haslayer(TCP):
            tcp_layer = pkt[TCP]
            src_port  = tcp_layer.sport
            dst_port  = tcp_layer.dport
            flags     = int(tcp_layer.flags)
            protocol  = 'TCP'

        elif pkt.haslayer(UDP):
            udp_layer = pkt[UDP]
            src_port  = udp_layer.sport
            dst_port  = udp_layer.dport
            protocol  = 'UDP'

        elif pkt.haslayer(ICMP):
            protocol = 'ICMP'

        pkt_rate    = get_rate(src_ip)
        is_scan     = check_port_scan(src_ip, dst_port)

        packet_info = {
            'src_ip':       src_ip,
            'dst_ip':       dst_ip,
            'src_port':     src_port,
            'dst_port':     dst_port,
            'protocol':     protocol,
            'protocol_num': protocol_num,
            'packet_size':  size,
            'flags':        flags,
            'ttl':          ttl,
            'pkt_rate':     pkt_rate,
            'is_port_scan': is_scan,
        }

        stats['captured'] += 1

        # Send to API in a background thread so sniffing never blocks
        t = threading.Thread(target=send_to_api, args=(packet_info,), daemon=True)
        t.start()

        # Print stats every 100 packets
        if stats['captured'] % 100 == 0:
            print(
                f"[Capture] Packets: {stats['captured']} | "
                f"Sent: {stats['sent']} | "
                f"Threats: {stats['threats']} | "
                f"Errors: {stats['errors']}"
            )

    except Exception as e:
        stats['errors'] += 1

def send_to_api(packet_info: dict):
    try:
        r      = requests.post(API, json=packet_info, headers=HEADERS, timeout=3)
        result = r.json()
        stats['sent'] += 1

        if result.get('is_threat'):
            stats['threats'] += 1
            ts = datetime.now().strftime('%H:%M:%S')
            print(
                f"[{ts}] THREAT [{result.get('severity'):8s}] "
                f"{result.get('threat_type'):25s} "
                f"from {packet_info.get('src_ip'):16s} "
                f"→ port {packet_info.get('dst_port')}"
            )

    except requests.exceptions.ConnectionError:
        stats['errors'] += 1
        if stats['errors'] == 1:
            print("[Capture] Cannot reach API — is api_server.py running?")
    except requests.exceptions.Timeout:
        stats['errors'] += 1
    except Exception as e:
        stats['errors'] += 1

# ── Interface listing ─────────────────────────────────────────────────────────

def list_interfaces():
    print("\nAvailable network interfaces:")
    print("-" * 40)
    try:
        ifaces = get_if_list()
        for i, iface in enumerate(ifaces):
            print(f"  [{i}] {iface}")
    except Exception as e:
        print(f"  Error listing interfaces: {e}")
    print("-" * 40)
    print("Use: python capture.py --iface \"<name from above>\"")
    print()

# ── Main ──────────────────────────────────────────────────────────────────────

def main():
    parser = argparse.ArgumentParser(description='IDS Packet Capture Engine')
    parser.add_argument('--iface',  default=None,  help='Network interface name')
    parser.add_argument('--filter', default='ip',  help='BPF filter (default: ip)')
    parser.add_argument('--list',   action='store_true', help='List available interfaces')
    args = parser.parse_args()

    if not SCAPY_OK:
        sys.exit(1)

    if args.list:
        list_interfaces()
        sys.exit(0)

    print("=" * 60)
    print("  IDS Packet Capture Engine")
    print(f"  Interface : {args.iface or 'default (auto-detect)'}")
    print(f"  BPF filter: {args.filter}")
    print(f"  API       : {API}")
    print("=" * 60)
    print("  Run as Administrator for full packet access.")
    print("  Press Ctrl+C to stop.\n")

    # Verify API is reachable before starting
    try:
        r = requests.get('http://localhost:5000/api/health', timeout=3)
        data = r.json()
        print(f"[Capture] API online — model: {data.get('model')}, rules: {data.get('rules')}")
    except Exception:
        print("[Capture] WARNING — Cannot reach API at localhost:5000")
        print("[Capture] Start api_server.py first, then run capture.py")
        sys.exit(1)

    print(f"[Capture] Starting packet sniffing...\n")

    try:
        sniff(
            iface  = args.iface,
            filter = args.filter,
            prn    = process_packet,
            store  = False,       # never store packets in memory
        )
    except PermissionError:
        print("\n[Capture] Permission denied.")
        print("  Fix: Right-click VS Code → Run as Administrator")
        print("  Then retry: python capture.py")
    except OSError as e:
        print(f"\n[Capture] Interface error: {e}")
        print("  Run: python capture.py --list")
        print("  Then: python capture.py --iface \"<interface name>\"")
    except KeyboardInterrupt:
        print(f"\n[Capture] Stopped.")
        print(f"  Total captured : {stats['captured']}")
        print(f"  Sent to API    : {stats['sent']}")
        print(f"  Threats found  : {stats['threats']}")
        print(f"  Errors         : {stats['errors']}")

if __name__ == '__main__':
    main()