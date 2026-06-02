import os
import smtplib
import time
from email.mime.text import MIMEText
from email.mime.multipart import MIMEMultipart
from dotenv import load_dotenv

load_dotenv()

class Alerter:
    def __init__(self):
        self.last_sms_time   = {}   # threat_type -> last SMS timestamp
        self.last_email_time = {}
        self.SMS_COOLDOWN    = 300  # 5 minutes between SMS per threat type
        self.EMAIL_COOLDOWN  = 120  # 2 minutes between emails

        self.twilio_enabled = bool(os.getenv('TWILIO_SID') and os.getenv('TWILIO_TOKEN'))
        self.email_enabled  = bool(os.getenv('SMTP_USER') and os.getenv('SMTP_PASS'))

    def handle(self, alert: dict, settings: dict):
        """
        Called whenever a new threat is detected.
        alert   = the full alert dict (threat_type, severity, src_ip, etc.)
        settings = dict from DB settings table (notify_email, sms_threshold, etc.)
        """
        severity      = alert.get('severity', 'LOW')
        sms_threshold = settings.get('sms_threshold', 'CRITICAL')
        notify_email  = settings.get('notify_email', '')

        severity_rank = {'LOW': 1, 'MEDIUM': 2, 'HIGH': 3, 'CRITICAL': 4}
        alert_rank    = severity_rank.get(severity, 1)
        sms_rank      = severity_rank.get(sms_threshold, 4)

        # Send SMS if severity meets threshold
        if alert_rank >= sms_rank and self.twilio_enabled:
            self._send_sms(alert)

        # Send email for HIGH and CRITICAL
        if alert_rank >= 3 and notify_email and self.email_enabled:
            self._send_email(alert, notify_email)

    def _send_sms(self, alert: dict):
        threat_type = alert.get('threat_type', 'UNKNOWN')
        now         = time.time()

        if now - self.last_sms_time.get(threat_type, 0) < self.SMS_COOLDOWN:
            print(f"[Alerter] SMS cooldown active for {threat_type}, skipping.")
            return

        try:
            from twilio.rest import Client
            client  = Client(os.getenv('TWILIO_SID'), os.getenv('TWILIO_TOKEN'))
            message = (
                f"IDS ALERT [{alert.get('severity')}]\n"
                f"Threat: {threat_type}\n"
                f"Source: {alert.get('src_ip', 'unknown')}\n"
                f"Time: {alert.get('timestamp', 'now')}"
            )
            client.messages.create(
                body=message,
                from_=os.getenv('TWILIO_FROM'),
                to=os.getenv('TWILIO_TO')
            )
            self.last_sms_time[threat_type] = now
            print(f"[Alerter] SMS sent for {threat_type}")
        except Exception as e:
            print(f"[Alerter] SMS error: {e}")

    def _send_email(self, alert: dict, to_email: str):
        threat_type = alert.get('threat_type', 'UNKNOWN')
        now         = time.time()

        if now - self.last_email_time.get(threat_type, 0) < self.EMAIL_COOLDOWN:
            return

        try:
            msg            = MIMEMultipart('alternative')
            msg['Subject'] = f"IDS Alert: {alert.get('severity')} — {threat_type}"
            msg['From']    = os.getenv('SMTP_USER')
            msg['To']      = to_email

            html = f"""
            <html><body style="font-family:sans-serif;background:#0d1117;color:#e6edf3;padding:20px">
            <h2 style="color:#ff4757">IDS Security Alert</h2>
            <table style="border-collapse:collapse;width:100%">
              <tr><td style="padding:8px;color:#8b949e">Severity</td>
                  <td style="padding:8px;color:#ff4757;font-weight:bold">{alert.get('severity')}</td></tr>
              <tr><td style="padding:8px;color:#8b949e">Threat type</td>
                  <td style="padding:8px">{threat_type}</td></tr>
              <tr><td style="padding:8px;color:#8b949e">Source IP</td>
                  <td style="padding:8px;font-family:monospace">{alert.get('src_ip','unknown')}</td></tr>
              <tr><td style="padding:8px;color:#8b949e">Destination</td>
                  <td style="padding:8px;font-family:monospace">{alert.get('dst_ip','unknown')}:{alert.get('dst_port','?')}</td></tr>
              <tr><td style="padding:8px;color:#8b949e">Confidence</td>
                  <td style="padding:8px">{round(float(alert.get('confidence',0))*100,1)}%</td></tr>
              <tr><td style="padding:8px;color:#8b949e">Description</td>
                  <td style="padding:8px">{alert.get('description','')}</td></tr>
            </table>
            <p style="color:#8b949e;margin-top:20px;font-size:12px">
              IDS Monitor — automated security alert
            </p>
            </body></html>
            """

            msg.attach(MIMEText(html, 'html'))

            with smtplib.SMTP_SSL('smtp.gmail.com', 465) as server:
                server.login(os.getenv('SMTP_USER'), os.getenv('SMTP_PASS'))
                server.sendmail(os.getenv('SMTP_USER'), to_email, msg.as_string())

            self.last_email_time[threat_type] = now
            print(f"[Alerter] Email sent for {threat_type}")
        except Exception as e:
            print(f"[Alerter] Email error: {e}")