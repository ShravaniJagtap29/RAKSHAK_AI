"""
RAKSHAKAI — IDS Entry Point
Run this file to start everything at once.

Usage:
    python main.py             ← API + simulator
    python main.py --capture   ← API + real packet capture
    python main.py --api-only  ← API only
"""

import sys
import time
import argparse
import threading
import subprocess
import requests

def wait_for_api(timeout=15):
    """Wait until the Flask API is ready."""
    print("[Main] Waiting for API to start...", end='', flush=True)
    start = time.time()
    while time.time() - start < timeout:
        try:
            r = requests.get('http://localhost:5000/api/health', timeout=1)
            if r.json().get('status') == 'online':
                print(" ready.")
                return True
        except Exception:
            pass
        print(".", end='', flush=True)
        time.sleep(1)
    print(" timed out.")
    return False

def run_api():
    from api_server import socketio, app
    socketio.run(app, host='0.0.0.0', port=5000, debug=False, use_reloader=False)

def run_simulator():
    time.sleep(3)
    print("[Main] Starting simulator...")
    import simulator  # runs the simulator module

def run_capture(iface=None):
    time.sleep(3)
    import capture
    capture.main()

def print_banner():
    print("""
╔══════════════════════════════════════════════╗
║          RAKSHAKAI IDS — Starting            ║
║    Intrusion Detection System v1.0           ║
╠══════════════════════════════════════════════╣
║  Dashboard : http://localhost/ids/           ║
║  API       : http://localhost:5000/api       ║
║  Health    : http://localhost:5000/api/health║
╚══════════════════════════════════════════════╝
""")

if __name__ == '__main__':
    parser = argparse.ArgumentParser(description='RAKSHAKAI IDS')
    parser.add_argument('--capture',  action='store_true', help='Use real packet capture')
    parser.add_argument('--api-only', action='store_true', help='Start API only')
    args = parser.parse_args()

    print_banner()

    # Start API in background thread
    api_thread = threading.Thread(target=run_api, daemon=True)
    api_thread.start()

    if args.api_only:
        print("[Main] API-only mode. Press Ctrl+C to stop.")
        try:
            api_thread.join()
        except KeyboardInterrupt:
            print("\n[Main] Stopped.")
        sys.exit(0)

    # Wait for API to be ready
    if not wait_for_api():
        print("[Main] API failed to start. Check for errors above.")
        sys.exit(1)

    if args.capture:
        print("[Main] Starting packet capture mode (requires Admin)...")
        capture_thread = threading.Thread(target=run_capture, daemon=True)
        capture_thread.start()
    else:
        print("[Main] Starting simulator mode...")
        sim_thread = threading.Thread(target=run_simulator, daemon=True)
        sim_thread.start()

    print("[Main] All systems running. Press Ctrl+C to stop.\n")
    try:
        while True:
            time.sleep(1)
    except KeyboardInterrupt:
        print("\n[Main] RAKSHAKAI stopped.")