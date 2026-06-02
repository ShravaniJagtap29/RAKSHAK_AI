<?php
session_start();
if (!isset($_SESSION['user'])) { header('Location: login.php'); exit; }
if ($_SESSION['role'] !== 'admin') {
    header('Location: index.php');
    exit;
}

require_once 'db.php';

$message = '';
$type    = '';

// Load current settings
$settings = $pdo->query("SELECT setting_key, setting_value FROM settings")
                 ->fetchAll(PDO::FETCH_KEY_PAIR);

// Handle form save
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'save_notifications') {
        $fields = [
            'notify_email'     => trim($_POST['notify_email']     ?? ''),
            'notify_phone'     => trim($_POST['notify_phone']     ?? ''),
            'sms_threshold'    => $_POST['sms_threshold']          ?? 'CRITICAL',
            'auto_block_score' => intval($_POST['auto_block_score'] ?? 90),
        ];
        $stmt = $pdo->prepare(
            "INSERT INTO settings (setting_key, setting_value)
             VALUES (?, ?)
             ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)"
        );
        foreach ($fields as $key => $val) {
            $stmt->execute([$key, $val]);
        }
        $message = 'Notification settings saved.';
        $type    = 'success';
        $settings = array_merge($settings, $fields);
    }

    if ($action === 'save_api') {
        $api_key = trim($_POST['api_key'] ?? '');
        if (strlen($api_key) >= 8) {
            $stmt = $pdo->prepare(
                "INSERT INTO settings (setting_key, setting_value)
                 VALUES ('api_key', ?)
                 ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)"
            );
            $stmt->execute([$api_key]);
            $settings['api_key'] = $api_key;
            $message = 'API key updated.';
            $type    = 'success';
        } else {
            $message = 'API key must be at least 8 characters.';
            $type    = 'error';
        }
    }

    if ($action === 'add_user') {
        $username = trim($_POST['new_username'] ?? '');
        $password = $_POST['new_password'] ?? '';
        $role     = $_POST['new_role'] ?? 'analyst';

        if (strlen($username) < 3 || strlen($password) < 6) {
            $message = 'Username must be 3+ chars, password 6+ chars.';
            $type    = 'error';
        } else {
            $check = $pdo->prepare("SELECT id FROM users WHERE username = ?");
            $check->execute([$username]);
            if ($check->fetch()) {
                $message = 'Username already exists.';
                $type    = 'error';
            } else {
                $hash = password_hash($password, PASSWORD_BCRYPT);
                $ins  = $pdo->prepare(
                    "INSERT INTO users (username, password_hash, role) VALUES (?,?,?)"
                );
                $ins->execute([$username, $hash, $role]);
                $message = "User '$username' created successfully.";
                $type    = 'success';
            }
        }
    }

    if ($action === 'delete_user') {
        $uid = intval($_POST['user_id'] ?? 0);
        if ($uid && $uid !== intval($_SESSION['user_id'])) {
            $pdo->prepare("DELETE FROM users WHERE id = ?")->execute([$uid]);
            $message = 'User deleted.';
            $type    = 'success';
        } else {
            $message = 'Cannot delete yourself.';
            $type    = 'error';
        }
    }
}

// Load users
$users = $pdo->query("SELECT id, username, role, created_at FROM users ORDER BY id")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>RAKSHAKAI — Settings</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .settings-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
        }
        .settings-card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 10px;
            padding: 22px;
        }
        .settings-card.full { grid-column: 1 / -1; }
        .settings-card h3 {
            font-size: 13px;
            font-weight: 600;
            color: var(--text);
            margin-bottom: 4px;
        }
        .settings-card .card-desc {
            font-size: 12px;
            color: var(--text-muted);
            margin-bottom: 20px;
            padding-bottom: 14px;
            border-bottom: 1px solid var(--border);
        }
        .field-row {
            margin-bottom: 14px;
        }
        .field-row label {
            display: block;
            font-size: 12px;
            font-weight: 600;
            color: var(--text-muted);
            margin-bottom: 6px;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
        .field-row input,
        .field-row select {
            width: 100%;
            background: var(--surface2);
            border: 1px solid var(--border);
            border-radius: 7px;
            padding: 9px 13px;
            color: var(--text);
            font-size: 13px;
            outline: none;
            transition: border-color 0.2s;
        }
        .field-row input:focus,
        .field-row select:focus { border-color: var(--accent); }
        .field-hint {
            font-size: 11px;
            color: var(--text-muted);
            margin-top: 4px;
        }
        .save-btn {
            background: var(--accent);
            color: #000;
            border: none;
            border-radius: 7px;
            padding: 9px 20px;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            margin-top: 6px;
            transition: opacity 0.2s;
        }
        .save-btn:hover { opacity: 0.85; }
        .save-btn.red {
            background: var(--danger);
            color: #fff;
        }
        .alert-msg {
            padding: 10px 14px;
            border-radius: 7px;
            font-size: 13px;
            margin-bottom: 16px;
        }
        .alert-msg.success {
            background: rgba(46,213,115,0.12);
            border: 1px solid rgba(46,213,115,0.3);
            color: var(--success);
        }
        .alert-msg.error {
            background: rgba(255,71,87,0.12);
            border: 1px solid rgba(255,71,87,0.3);
            color: var(--danger);
        }
        .env-block {
            background: var(--surface2);
            border: 1px solid var(--border);
            border-radius: 7px;
            padding: 12px 14px;
            font-family: monospace;
            font-size: 12px;
            color: var(--text-muted);
            line-height: 2;
        }
        .env-block .env-key { color: var(--accent); }
        .env-block .env-val { color: var(--success); }
        .user-row {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 10px 0;
            border-bottom: 1px solid var(--border);
            font-size: 13px;
        }
        .user-row:last-child { border-bottom: none; }
        .user-avatar-sm {
            width: 28px;
            height: 28px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--accent), #7c3aed);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 11px;
            font-weight: 600;
            color: #000;
            flex-shrink: 0;
        }
        .role-badge {
            font-size: 11px;
            padding: 2px 8px;
            border-radius: 4px;
            font-weight: 600;
        }
        .role-badge.admin   { background:rgba(124,58,237,0.15); color:#a78bfa; }
        .role-badge.analyst { background:rgba(0,212,255,0.1);   color:var(--accent); }
        .status-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 10px 0;
            border-bottom: 1px solid var(--border);
            font-size: 13px;
        }
        .status-row:last-child { border-bottom: none; }
        .status-row .s-key { color: var(--text-muted); }
        .status-row .s-val { font-family: monospace; font-size: 12px; }
        @media (max-width: 900px) {
            .settings-grid { grid-template-columns: 1fr; }
            .settings-card.full { grid-column: 1; }
        }
    </style>
    <script src="assets/js/app.js" defer></script>
</head>
<body>
<div class="app-layout">
    <?php include 'nav.php'; ?>
    <main class="main-content">

        <div class="page-header">
            <h2>Settings</h2>
            <p>System configuration — admin only</p>
        </div>

        <?php if ($message): ?>
        <div class="alert-msg <?= $type ?>">
            <?= htmlspecialchars($message) ?>
        </div>
        <?php endif; ?>

        <div class="settings-grid">

            <!-- Notification settings -->
            <div class="settings-card">
                <h3>Notifications</h3>
                <p class="card-desc">
                    Configure email and SMS alerts for detected threats
                </p>
                <form method="POST">
                    <input type="hidden" name="action" value="save_notifications">

                    <div class="field-row">
                        <label>Notification email</label>
                        <input type="email" name="notify_email"
                            placeholder="you@example.com"
                            value="<?= htmlspecialchars($settings['notify_email'] ?? '') ?>">
                        <div class="field-hint">
                            Receives HIGH and CRITICAL alert emails
                        </div>
                    </div>

                    <div class="field-row">
                        <label>SMS phone number</label>
                        <input type="text" name="notify_phone"
                            placeholder="+91xxxxxxxxxx"
                            value="<?= htmlspecialchars($settings['notify_phone'] ?? '') ?>">
                        <div class="field-hint">
                            International format e.g. +919876543210
                        </div>
                    </div>

                    <div class="field-row">
                        <label>SMS threshold</label>
                        <select name="sms_threshold">
                            <?php
                            $thresh = $settings['sms_threshold'] ?? 'CRITICAL';
                            foreach (['CRITICAL','HIGH','MEDIUM','LOW'] as $opt):
                            ?>
                            <option value="<?= $opt ?>"
                                <?= $thresh === $opt ? 'selected' : '' ?>>
                                <?= $opt ?> and above
                            </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="field-hint">
                            Only send SMS for threats at or above this severity
                        </div>
                    </div>

                    <div class="field-row">
                        <label>Auto-block abuse score threshold</label>
                        <input type="number" name="auto_block_score"
                            min="0" max="100"
                            value="<?= intval($settings['auto_block_score'] ?? 90) ?>">
                        <div class="field-hint">
                            IPs with AbuseIPDB score ≥ this value are auto-blocked (0 = disabled)
                        </div>
                    </div>

                    <button type="submit" class="save-btn">Save notifications</button>
                </form>
            </div>

            <!-- API key -->
            <div class="settings-card">
                <h3>API security</h3>
                <p class="card-desc">
                    Internal API key used between PHP and Python engine
                </p>
                <form method="POST">
                    <input type="hidden" name="action" value="save_api">

                    <div class="field-row">
                        <label>Internal API key</label>
                        <input type="text" name="api_key"
                            value="<?= htmlspecialchars($settings['api_key'] ?? '') ?>"
                            autocomplete="off">
                        <div class="field-hint">
                            Must match <code>API_KEY</code> in your Python .env file.
                            Change both together.
                        </div>
                    </div>

                    <button type="submit" class="save-btn">Update API key</button>
                </form>

                <div style="margin-top:24px">
                    <div style="font-size:12px;font-weight:600;color:var(--text-muted);
                                text-transform:uppercase;letter-spacing:0.05em;
                                margin-bottom:10px">
                        Python .env reference
                    </div>
                    <div class="env-block">
                        <span class="env-key">API_KEY</span>=<span class="env-val">
                            <?= htmlspecialchars($settings['api_key'] ?? 'ids-api-key-change-me') ?>
                        </span><br>
                        <span class="env-key">DB_HOST</span>=<span class="env-val">localhost</span><br>
                        <span class="env-key">ABUSEIPDB_KEY</span>=<span class="env-val">your_key_here</span><br>
                        <span class="env-key">OPENAI_KEY</span>=<span class="env-val">sk-...</span><br>
                        <span class="env-key">TWILIO_SID</span>=<span class="env-val">AC...</span><br>
                        <span class="env-key">SMTP_USER</span>=<span class="env-val">you@gmail.com</span><br>
                        <span class="env-key">SMTP_PASS</span>=<span class="env-val">app-password-here</span>
                    </div>
                </div>
            </div>

            <!-- System status -->
            <div class="settings-card">
                <h3>System status</h3>
                <p class="card-desc">Live status of all components</p>
                <div id="status-list">
                    <div class="loading-overlay">
                        <span class="spinner"></span>&nbsp; Checking...
                    </div>
                </div>
            </div>

            <!-- User management -->
            <div class="settings-card">
                <h3>User management</h3>
                <p class="card-desc">Add and remove system users</p>

                <!-- Existing users -->
                <div style="margin-bottom:20px">
                    <?php foreach ($users as $u): ?>
                    <div class="user-row">
                        <div class="user-avatar-sm">
                            <?= strtoupper(substr($u['username'], 0, 2)) ?>
                        </div>
                        <div style="flex:1">
                            <div style="font-weight:500">
                                <?= htmlspecialchars($u['username']) ?>
                                <?php if ($u['username'] === $_SESSION['user']): ?>
                                <span style="font-size:11px;color:var(--text-muted)">(you)</span>
                                <?php endif; ?>
                            </div>
                            <div style="font-size:11px;color:var(--text-muted)">
                                <?= htmlspecialchars($u['created_at'] ?? '') ?>
                            </div>
                        </div>
                        <span class="role-badge <?= $u['role'] ?>">
                            <?= $u['role'] ?>
                        </span>
                        <?php if ($u['username'] !== $_SESSION['user']): ?>
                        <form method="POST" style="margin:0"
                              onsubmit="return confirm('Delete <?= htmlspecialchars($u['username']) ?>?')">
                            <input type="hidden" name="action"  value="delete_user">
                            <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                            <button type="submit"
                                style="background:none;border:1px solid var(--border);
                                       border-radius:5px;color:var(--danger);
                                       font-size:12px;padding:3px 8px;cursor:pointer">
                                Remove
                            </button>
                        </form>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                </div>

                <!-- Add user -->
                <div style="border-top:1px solid var(--border);padding-top:16px">
                    <div style="font-size:12px;font-weight:600;color:var(--text-muted);
                                text-transform:uppercase;letter-spacing:0.05em;
                                margin-bottom:12px">
                        Add new user
                    </div>
                    <form method="POST">
                        <input type="hidden" name="action" value="add_user">
                        <div class="field-row">
                            <label>Username</label>
                            <input type="text" name="new_username"
                                placeholder="analyst01" required minlength="3">
                        </div>
                        <div class="field-row">
                            <label>Password</label>
                            <input type="password" name="new_password"
                                placeholder="6+ characters" required minlength="6">
                        </div>
                        <div class="field-row">
                            <label>Role</label>
                            <select name="new_role">
                                <option value="analyst">Analyst — read-only</option>
                                <option value="admin">Admin — full access</option>
                            </select>
                        </div>
                        <button type="submit" class="save-btn">Add user</button>
                    </form>
                </div>
            </div>

            <!-- Twilio setup guide -->
            <div class="settings-card">
                <h3>SMS setup (Twilio)</h3>
                <p class="card-desc">
                    Follow these steps to enable SMS alerts
                </p>
                <ol style="font-size:13px;color:var(--text-muted);
                           line-height:2.2;padding-left:18px">
                    <li>Go to <a href="https://www.twilio.com" target="_blank">twilio.com</a>
                        → Create free account</li>
                    <li>Verify your phone number during signup</li>
                    <li>Go to Console → Account Info</li>
                    <li>Copy <strong style="color:var(--text)">Account SID</strong>
                        and <strong style="color:var(--text)">Auth Token</strong></li>
                    <li>Get a free Twilio phone number (From number)</li>
                    <li>Add to <code>C:/projects/ids-engine/.env</code>:</li>
                </ol>
                <div class="env-block" style="margin-top:8px">
                    <span class="env-key">TWILIO_SID</span>=<span class="env-val">ACxxxxxxxxxxxxxxxx</span><br>
                    <span class="env-key">TWILIO_TOKEN</span>=<span class="env-val">your_auth_token</span><br>
                    <span class="env-key">TWILIO_FROM</span>=<span class="env-val">+1xxxxxxxxxx</span><br>
                    <span class="env-key">TWILIO_TO</span>=<span class="env-val">+91xxxxxxxxxx</span>
                </div>
                <div style="margin-top:12px;font-size:12px;color:var(--text-muted)">
                    Then restart <code>api_server.py</code> to pick up the new keys.
                </div>
            </div>

            <!-- Gmail setup guide -->
            <div class="settings-card">
                <h3>Email setup (Gmail)</h3>
                <p class="card-desc">
                    Follow these steps to enable email alerts
                </p>
                <ol style="font-size:13px;color:var(--text-muted);
                           line-height:2.2;padding-left:18px">
                    <li>Go to your Google Account → Security</li>
                    <li>Enable <strong style="color:var(--text)">2-Step Verification</strong>
                        (required)</li>
                    <li>Search for <strong style="color:var(--text)">App passwords</strong>
                        in Google Account</li>
                    <li>Create a new app password → choose "Mail"</li>
                    <li>Copy the 16-character password shown</li>
                    <li>Add to <code>C:/projects/ids-engine/.env</code>:</li>
                </ol>
                <div class="env-block" style="margin-top:8px">
                    <span class="env-key">SMTP_USER</span>=<span class="env-val">you@gmail.com</span><br>
                    <span class="env-key">SMTP_PASS</span>=<span class="env-val">xxxx xxxx xxxx xxxx</span>
                </div>
                <div style="margin-top:12px;font-size:12px;color:var(--text-muted)">
                    Add your email to the Notification email field above and
                    restart <code>api_server.py</code>.
                </div>
            </div>

        </div>

    </main>
</div>

<script>
async function loadStatus() {
    const container = document.getElementById('status-list');

    // Check Python API
    let apiStatus  = 'offline';
    let apiDetail  = '';
    let modelStatus = '—';
    let queueSize   = '—';

    try {
        const r    = await fetch('api_proxy.php?endpoint=health');
        const data = await r.json();
        if (data.status === 'online') {
            apiStatus   = 'online';
            modelStatus = data.model  || '—';
            queueSize   = data.queue  !== undefined ? data.queue : '—';
        }
    } catch (e) {
        apiStatus = 'offline';
    }

    // Check DB (via PHP — always works if page loaded)
    const dbStatus = 'online';

    // Count alerts
    let alertCount = '—';
    try {
        const r    = await fetch('api_proxy.php?endpoint=stats');
        const data = await r.json();
        alertCount = data.total_24h !== undefined ? data.total_24h : '—';
    } catch (e) {}

    const dot = ok => `<span class="status-dot ${ok ? 'online' : 'offline'}"></span>`;

    container.innerHTML = `
        <div class="status-row">
            <span class="s-key">Python AI engine</span>
            <span class="s-val">
                ${dot(apiStatus==='online')}
                ${apiStatus === 'online'
                    ? '<span style="color:var(--success)">Online</span>'
                    : '<span style="color:var(--danger)">Offline</span>'}
            </span>
        </div>
        <div class="status-row">
            <span class="s-key">MySQL database</span>
            <span class="s-val">
                ${dot(true)}
                <span style="color:var(--success)">Online</span>
            </span>
        </div>
        <div class="status-row">
            <span class="s-key">ML model</span>
            <span class="s-val" style="font-family:sans-serif">${modelStatus}</span>
        </div>
        <div class="status-row">
            <span class="s-key">Enrichment queue</span>
            <span class="s-val">${queueSize} jobs pending</span>
        </div>
        <div class="status-row">
            <span class="s-key">Alerts (last 24h)</span>
            <span class="s-val" style="color:var(--accent);font-family:sans-serif;font-weight:600">
                ${alertCount}
            </span>
        </div>
        <div class="status-row">
            <span class="s-key">Logged in as</span>
            <span class="s-val" style="font-family:sans-serif">
                <?= htmlspecialchars($_SESSION['user']) ?>
                (<?= htmlspecialchars($_SESSION['role']) ?>)
            </span>
        </div>
    `;
}

loadStatus();
setInterval(loadStatus, 15000);
</script>
</body>
</html>