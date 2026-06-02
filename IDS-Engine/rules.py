import re
import time
from collections import defaultdict, deque

class RuleEngine:
    """
    Stateful rule engine. Rules are stored in the DB and loaded at startup.
    Also contains stateful in-memory checks (rate windows, port tracking).
    """

    def __init__(self):
        # Loaded from DB on startup
        self.rules = []

        # Built-in hard-coded rules (always active)
        self.builtin_rules = [
            {
                'id':      'builtin_1',
                'name':    'Null packet',
                'check':   lambda p: int(p.get('flags', 1)) == 0 and int(p.get('protocol_num', 0)) == 6,
                'action':  'ALERT',
                'threat':  'NULL_SCAN',
            },
            {
                'id':      'builtin_2',
                'name':    'XMAS scan',
                'check':   lambda p: int(p.get('flags', 0)) == 0x29,  # FIN+PSH+URG
                'action':  'ALERT',
                'threat':  'XMAS_SCAN',
            },
            {
                'id':      'builtin_3',
                'name':    'Telnet access attempt',
                'check':   lambda p: int(p.get('dst_port', 0)) == 23,
                'action':  'ALERT',
                'threat':  'TELNET_ATTEMPT',
            },
            {
                'id':      'builtin_4',
                'name':    'FTP unencrypted',
                'check':   lambda p: int(p.get('dst_port', 0)) == 21,
                'action':  'LOG',
                'threat':  'FTP_ACCESS',
            },
            {
                'id':      'builtin_5',
                'name':    'RDP exposure',
                'check':   lambda p: int(p.get('dst_port', 0)) == 3389,
                'action':  'ALERT',
                'threat':  'RDP_EXPOSURE',
            },
        ]

    def load_from_db(self, db_rules: list):
        """
        Accept a list of rule dicts from the DB.
        Each dict has: id, name, pattern, action, enabled
        """
        self.rules = [r for r in db_rules if r.get('enabled')]
        print(f"[Rules] Loaded {len(self.rules)} rules from DB.")

    def check(self, packet_info: dict) -> dict | None:
        """
        Run all rules against a packet.
        Returns the first matching rule result dict, or None if no match.
        """
        # Check built-in rules first
        for rule in self.builtin_rules:
            try:
                if rule['check'](packet_info):
                    return {
                        'rule_id':    rule['id'],
                        'rule_name':  rule['name'],
                        'action':     rule['action'],
                        'threat':     rule['threat'],
                    }
            except Exception:
                continue

        # Check DB rules (pattern matching)
        for rule in self.rules:
            pattern = rule.get('pattern', '')
            if not pattern:
                continue
            try:
                matched = self._match_pattern(pattern, packet_info)
                if matched:
                    return {
                        'rule_id':   rule['id'],
                        'rule_name': rule['name'],
                        'action':    rule['action'],
                        'threat':    rule.get('name', 'RULE_MATCH').upper().replace(' ', '_'),
                    }
            except Exception:
                continue

        return None

    def _match_pattern(self, pattern: str, packet_info: dict) -> bool:
        """
        Pattern format examples:
          dst_port=4444
          src_ip=192.168.
          threat_type=SYN_FLOOD
          dst_port=22,src_port=>1024
        """
        conditions = [c.strip() for c in pattern.split(',')]
        for condition in conditions:
            if '=' not in condition:
                continue
            key, value = condition.split('=', 1)
            key        = key.strip()
            value      = value.strip()
            pkt_val    = str(packet_info.get(key, ''))

            # Greater-than / less-than operators
            if value.startswith('>'):
                try:
                    if not (float(pkt_val) > float(value[1:])):
                        return False
                except ValueError:
                    return False
            elif value.startswith('<'):
                try:
                    if not (float(pkt_val) < float(value[1:])):
                        return False
                except ValueError:
                    return False
            else:
                # Substring / regex match
                try:
                    if not re.search(value, pkt_val, re.IGNORECASE):
                        return False
                except re.error:
                    if value not in pkt_val:
                        return False

        return True