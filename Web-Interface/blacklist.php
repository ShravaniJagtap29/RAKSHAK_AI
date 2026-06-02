<?php
session_start();
if (!isset($_SESSION['user'])) { header('Location: login.php'); exit; }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>RAKSHAKAI — IP Blacklist</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .bl-layout {
            display: grid;
            grid-template-columns: 1fr 320px;
            gap: 16px;
            align-items: start;
        }
        .bl-card, .add-card, .check-card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 16px;
        }
        .bl-card h3, .add-card h3, .check-card h3 {
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            color: var(--text-muted);
            margin-bottom: 14px;
            padding-bottom: 10px;
            border-bottom: 1px solid var(--border);
        }
        .field-row { margin-bottom: 12px; }
        .field-row label {
            display: block;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            color: var(--text-muted);
            margin-bottom: 5px;
        }
        .field-row input, .field-row textarea {
            width: 100%;
            background: var(--surface2);
            border: 1px solid var(--border);
            border-radius: 6px;
            padding: 8px 12px;
            color: var(--text);
            font-size: 13px;
            outline: none;
            font-family: inherit;
        }
        .field-row input:focus, .field-row textarea:focus {
            border-color: var(--accent);
        }
        .btn-block {
            width: 100%;
            padding: 9px;
            border-radius: 6px;
            border: none;
            font-size: 13px;
            font-weight: 500;
            cursor: pointer;
            margin-top: 4px;
            transition: opacity 0.2s;
        }
        .btn-block.primary { background: var(--danger); color: #fff; }
        .btn-block.secondary {
            background: var(--surface2);
            border: 1px solid var(--border);
            color: var(--text);
        }
        .btn-block:hover { opacity: 0.85; }
        .abuse-result {
            margin-top: 12px;
            padding: 12px;
            background: var(--surface2);
            border-radius: 7px;
            font-size: 13px;
            display: none;
        }
        .abuse-score-big {
            font-size: 32px;
            font-weight: 700;
            margin: 8px 0 4px;
        }
        @media (max-width: 900px) {
            .bl-layout { grid-template-columns: 1fr; }
        }
    </style>
    <script src="assets/js/app.js" defer></script>
</head>
<body>
<div class="app-layout">
    <?php include 'nav.php'; ?>
    <main class="main-content">

        <div class="page-header">
            <h2>IP Blacklist</h2>
            <p>Manage blocked IP addresses and check reputation</p>
        </div>

        <div class="bl-layout">

            <!-- Blacklist table -->
            <div class="bl-card">
                <h3>
                    Blocked IPs
                    <span id="bl-count" style="color:var(--accent)"></span>
                </h3>
                <div class="table-wrap">
                    <table>
                        <thead>
                            <tr>
                                <th>IP Address</th>
                                <th>Reason</th>
                                <th>Abuse score</th>
                                <th>Added by</th>
                                <th>Added at</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody id="bl-body">
                            <tr><td colspan="6" class="loading-overlay">
                                <span class="spinner"></span>&nbsp; Loading...
                            </td></tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Right sidebar -->
            <div>
                <!-- Add IP -->
                <div class="add-card">
                    <h3>Block an IP</h3>
                    <div class="field-row">
                        <label>IP address</label>
                        <input type="text" id="bl-ip"
                            placeholder="e.g. 185.220.101.5">
                    </div>
                    <div class="field-row">
                        <label>Reason</label>
                        <input type="text" id="bl-reason"
                            placeholder="e.g. Known Tor exit node">
                    </div>
                    <button class="btn-block primary" onclick="addToBlacklist()">
                        Block IP
                    </button>
                    <div id="bl-feedback"
                         style="margin-top:10px;font-size:13px"></div>
                </div>

                <!-- Check IP reputation -->
                <div class="check-card">
                    <h3>Check IP reputation</h3>
                    <div class="field-row">
                        <label>IP address</label>
                        <input type="text" id="check-ip"
                            placeholder="Any public IP">
                    </div>
                    <button class="btn-block secondary" onclick="checkReputation()">
                        Check AbuseIPDB
                    </button>
                    <div class="abuse-result" id="abuse-result"></div>
                </div>
            </div>

        </div>

    </main>
</div>

<script>
async function loadBlacklist() {
    const tbody = document.getElementById('bl-body');
    try {
        const res  = await fetch('api_proxy.php?endpoint=blacklist');
        const data = await res.json();

        if (!Array.isArray(data) || data.length === 0) {
            tbody.innerHTML = `<tr><td colspan="6"
                style="color:var(--text-muted);padding:20px;text-align:center">
                No IPs blocked yet.</td></tr>`;
            document.getElementById('bl-count').textContent = '(0)';
            return;
        }

        document.getElementById('bl-count').textContent = `(${data.length})`;

        tbody.innerHTML = data.map(r => {
            const score      = parseInt(r.abuse_score) || 0;
            const scoreColor = score >= 75
                ? 'var(--danger)'
                : score >= 40 ? 'var(--warning)' : 'var(--text-muted)';

            return `
            <tr>
                <td><code>${r.ip}</code></td>
                <td style="font-size:12px;color:var(--text-muted);max-width:180px;
                           white-space:nowrap;overflow:hidden;text-overflow:ellipsis">
                    ${r.reason || '—'}
                </td>
                <td style="color:${scoreColor};font-weight:600">${score || '—'}</td>
                <td style="color:var(--text-muted);font-size:12px">${r.added_by || '—'}</td>
                <td style="font-family:monospace;font-size:11px;color:var(--text-muted)">
                    ${r.added_at ? new Date(r.added_at).toLocaleDateString() : '—'}
                </td>
                <td>
                    <button onclick="removeIP('${r.ip}')"
                        style="background:none;border:1px solid var(--border);
                               border-radius:5px;color:var(--danger);font-size:12px;
                               padding:3px 10px;cursor:pointer;transition:all 0.15s"
                        onmouseover="this.style.background='rgba(255,71,87,0.1)'"
                        onmouseout="this.style.background='none'">
                        Unblock
                    </button>
                </td>
            </tr>`;
        }).join('');
    } catch (e) {
        tbody.innerHTML = `<tr><td colspan="6"
            style="color:var(--danger);padding:16px">
            Could not load blacklist.</td></tr>`;
    }
}

async function addToBlacklist() {
    const ip       = document.getElementById('bl-ip').value.trim();
    const reason   = document.getElementById('bl-reason').value.trim();
    const feedback = document.getElementById('bl-feedback');

    if (!ip) {
        feedback.innerHTML = '<span style="color:var(--danger)">IP address required.</span>';
        return;
    }

    feedback.innerHTML = '<span style="color:var(--text-muted)">Blocking...</span>';

    try {
        const res  = await fetch('api_proxy.php?endpoint=blacklist', {
            method:  'POST',
            headers: { 'Content-Type': 'application/json' },
            body:    JSON.stringify({
                ip,
                reason:   reason || 'Manually blocked',
                added_by: '<?= htmlspecialchars($_SESSION['user']) ?>',
            }),
        });
        const data = await res.json();

        if (data.success) {
            feedback.innerHTML = `<span style="color:var(--success)">${ip} blocked.</span>`;
            document.getElementById('bl-ip').value     = '';
            document.getElementById('bl-reason').value = '';
            loadBlacklist();
            setTimeout(() => feedback.innerHTML = '', 3000);
        } else {
            feedback.innerHTML = `<span style="color:var(--danger)">
                Error: ${data.error || 'unknown'}</span>`;
        }
    } catch (e) {
        feedback.innerHTML = '<span style="color:var(--danger)">Could not reach API.</span>';
    }
}

async function removeIP(ip) {
    if (!confirm(`Unblock ${ip}?`)) return;
    try {
        const res  = await fetch(`api_proxy.php?endpoint=blacklist/${ip}`, {
            method: 'DELETE',
        });
        const data = await res.json();
        if (data.success) loadBlacklist();
    } catch (e) {
        alert('Could not unblock IP.');
    }
}

async function checkReputation() {
    const ip     = document.getElementById('check-ip').value.trim();
    const result = document.getElementById('abuse-result');

    if (!ip) { alert('Enter an IP address.'); return; }

    result.style.display = 'block';
    result.innerHTML     = '<div class="loading-overlay"><span class="spinner"></span>&nbsp; Checking...</div>';

    try {
        // Use our API proxy to call AbuseIPDB via the Python engine
        const res  = await fetch(`api_proxy.php?endpoint=alerts&src_ip=${encodeURIComponent(ip)}&limit=5`);
        const data = await res.json();
        const alerts = data.alerts || [];

        const score     = alerts.length > 0 ? (parseInt(alerts[0].abuse_score) || 0) : 0;
        const scoreColor = score >= 75
            ? '#ff4757'
            : score >= 40 ? '#ffa502' : '#2ed573';
        const label     = score >= 75 ? 'High Risk'
                        : score >= 40 ? 'Suspicious'
                        : score >  0  ? 'Low Risk' : 'No data';

        result.innerHTML = `
            <div style="color:var(--text-muted);font-size:11px;
                        text-transform:uppercase;letter-spacing:0.05em">
                AbuseIPDB score
            </div>
            <div class="abuse-score-big" style="color:${scoreColor}">${score}</div>
            <div style="color:${scoreColor};font-weight:600;margin-bottom:8px">
                ${label}
            </div>
            ${alerts.length > 0
                ? `<div style="font-size:12px;color:var(--text-muted)">
                       Found in ${alerts.length} local alert(s)
                   </div>`
                : '<div style="font-size:12px;color:var(--text-muted)">No local alerts for this IP</div>'
            }
            <button onclick="document.getElementById('bl-ip').value='${ip}';
                             document.getElementById('bl-reason').value='Checked: score ${score}'"
                style="margin-top:10px;width:100%;padding:7px;
                       background:rgba(255,71,87,0.15);border:1px solid rgba(255,71,87,0.3);
                       border-radius:5px;color:var(--danger);font-size:12px;
                       cursor:pointer;font-weight:500">
                Block this IP
            </button>`;
    } catch (e) {
        result.innerHTML = '<span style="color:var(--danger)">Check failed.</span>';
    }
}

loadBlacklist();
</script>
</body>
</html>