<?php
session_start();
if (!isset($_SESSION['user'])) { header('Location: login.php'); exit; }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>IDS — Alerts</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .filter-bar {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 10px;
            padding: 16px;
            margin-bottom: 20px;
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            align-items: flex-end;
        }
        .filter-group {
            display: flex;
            flex-direction: column;
            gap: 5px;
        }
        .filter-group label {
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            color: var(--text-muted);
        }
        .filter-group select,
        .filter-group input {
            background: var(--surface2);
            border: 1px solid var(--border);
            border-radius: 6px;
            padding: 7px 12px;
            color: var(--text);
            font-size: 13px;
            outline: none;
            min-width: 140px;
        }
        .filter-group select:focus,
        .filter-group input:focus {
            border-color: var(--accent);
        }
        .filter-actions {
            display: flex;
            gap: 8px;
            align-items: flex-end;
            margin-left: auto;
        }
        .btn-sm {
            padding: 7px 14px;
            font-size: 13px;
            border-radius: 6px;
            border: 1px solid var(--border);
            background: var(--surface2);
            color: var(--text);
            cursor: pointer;
            transition: all 0.15s;
            white-space: nowrap;
        }
        .btn-sm:hover { border-color: var(--accent); color: var(--accent); }
        .btn-sm.accent { background: var(--accent); color: #000; border-color: var(--accent); }
        .btn-sm.accent:hover { opacity: 0.85; }
        .alerts-card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 10px;
            padding: 16px;
        }
        .alerts-top {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 16px;
            flex-wrap: wrap;
            gap: 10px;
        }
        .alerts-top h3 {
            font-size: 13px;
            font-weight: 600;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: 0.06em;
        }
        .total-label {
            font-size: 12px;
            color: var(--text-muted);
        }
        .pagination {
            display: flex;
            align-items: center;
            gap: 6px;
            margin-top: 16px;
            justify-content: center;
        }
        .page-btn {
            padding: 5px 12px;
            border-radius: 6px;
            border: 1px solid var(--border);
            background: var(--surface2);
            color: var(--text);
            font-size: 13px;
            cursor: pointer;
            transition: all 0.15s;
        }
        .page-btn:hover   { border-color: var(--accent); }
        .page-btn.active  { background: var(--accent); color: #000; border-color: var(--accent); }
        .page-btn:disabled { opacity: 0.4; cursor: not-allowed; }
        tr.clickable { cursor: pointer; }
        tr.clickable:hover td { background: var(--surface2); }
        .export-row {
            display: flex;
            justify-content: flex-end;
            gap: 8px;
            margin-bottom: 8px;
        }
    </style>
    <script src="assets/js/app.js" defer></script>
</head>
<body>
<div class="app-layout">
    <?php include 'nav.php'; ?>
    <main class="main-content">

        <div class="page-header">
            <h2>Alerts</h2>
            <p>All detected threats — filter, search, export</p>
        </div>

        <!-- Filter bar -->
        <div class="filter-bar">
            <div class="filter-group">
                <label>Severity</label>
                <select id="f-severity">
                    <option value="">All severities</option>
                    <option value="CRITICAL">Critical</option>
                    <option value="HIGH">High</option>
                    <option value="MEDIUM">Medium</option>
                    <option value="LOW">Low</option>
                </select>
            </div>
            <div class="filter-group">
                <label>Threat type</label>
                <select id="f-threat">
                    <option value="">All types</option>
                    <option value="C2_COMMUNICATION">C2 Communication</option>
                    <option value="SYN_FLOOD">SYN Flood</option>
                    <option value="DOS_ATTACK">DoS Attack</option>
                    <option value="PORT_SCAN">Port Scan</option>
                    <option value="BRUTE_FORCE">Brute Force</option>
                    <option value="UDP_FLOOD">UDP Flood</option>
                    <option value="DNS_AMPLIFICATION">DNS Amplification</option>
                    <option value="ICMP_FLOOD">ICMP Flood</option>
                </select>
            </div>
            <div class="filter-group">
                <label>Source IP</label>
                <input type="text" id="f-ip" placeholder="e.g. 192.168">
            </div>
            <div class="filter-group">
                <label>Limit</label>
                <select id="f-limit">
                    <option value="25">25</option>
                    <option value="50" selected>50</option>
                    <option value="100">100</option>
                    <option value="200">200</option>
                </select>
            </div>
            <div class="filter-actions">
                <button class="btn-sm" onclick="resetFilters()">Reset</button>
                <button class="btn-sm accent" onclick="loadAlerts(0)">Apply</button>
            </div>
        </div>

        <!-- Alerts table -->
        <div class="alerts-card">
            <div class="alerts-top">
                <h3>Results <span class="total-label" id="total-label"></span></h3>
                <div style="display:flex;gap:8px">
                    <button class="btn-sm" onclick="exportCSV()">Export CSV</button>
                    <button class="btn-sm" onclick="loadAlerts(currentOffset)">Refresh</button>
                </div>
            </div>

            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Time</th>
                            <th>Source IP</th>
                            <th>Destination</th>
                            <th>Protocol</th>
                            <th>Threat type</th>
                            <th>Severity</th>
                            <th>Confidence</th>
                            <th>Country</th>
                            <th>Abuse</th>
                        </tr>
                    </thead>
                    <tbody id="alert-body">
                        <tr><td colspan="10" class="loading-overlay">
                            <span class="spinner"></span>&nbsp; Loading...
                        </td></tr>
                    </tbody>
                </table>
            </div>

            <div class="pagination" id="pagination"></div>
        </div>

    </main>
</div>

<script>
let currentOffset = 0;
let totalAlerts   = 0;

function getFilters() {
    return {
        severity:    document.getElementById('f-severity').value,
        threat_type: document.getElementById('f-threat').value,
        src_ip:      document.getElementById('f-ip').value,
        limit:       document.getElementById('f-limit').value,
    };
}

function resetFilters() {
    document.getElementById('f-severity').value = '';
    document.getElementById('f-threat').value   = '';
    document.getElementById('f-ip').value        = '';
    document.getElementById('f-limit').value     = '50';
    loadAlerts(0);
}

async function loadAlerts(offset = 0) {
    currentOffset = offset;
    const f      = getFilters();
    const tbody  = document.getElementById('alert-body');
    tbody.innerHTML = `<tr><td colspan="10" class="loading-overlay">
        <span class="spinner"></span>&nbsp; Loading...</td></tr>`;

    const params = new URLSearchParams({
        endpoint:    'alerts',
        limit:       f.limit,
        offset:      offset,
        severity:    f.severity,
        threat_type: f.threat_type,
        src_ip:      f.src_ip,
    });

    try {
        const res  = await fetch('api_proxy.php?' + params);
        const data = await res.json();

        if (data.error) {
            tbody.innerHTML = `<tr><td colspan="10" style="color:var(--danger);padding:16px">
                Error: ${data.error}</td></tr>`;
            return;
        }

        totalAlerts = data.total || 0;
        const alerts = data.alerts || [];

        document.getElementById('total-label').textContent =
            `— ${totalAlerts.toLocaleString()} total`;

        if (alerts.length === 0) {
            tbody.innerHTML = `<tr><td colspan="10"
                style="color:var(--text-muted);padding:20px;text-align:center">
                No alerts match your filters.</td></tr>`;
            renderPagination(offset, parseInt(f.limit));
            return;
        }

        tbody.innerHTML = '';
        alerts.forEach(a => {
            const tr        = document.createElement('tr');
            tr.className    = 'clickable';
            tr.onclick      = () => window.location.href = `alert_detail.php?id=${a.id}`;
            const conf      = a.confidence ? (parseFloat(a.confidence)*100).toFixed(1)+'%' : '—';
            const time      = new Date(a.timestamp).toLocaleString();
            const abuse     = a.abuse_score > 0
                ? `<span style="color:${a.abuse_score>75?'var(--danger)':a.abuse_score>40?'var(--warning)':'var(--text-muted)'}">${a.abuse_score}</span>`
                : '<span style="color:var(--text-muted)">—</span>';

            tr.innerHTML = `
                <td style="color:var(--text-muted);font-size:12px">${a.id}</td>
                <td style="font-family:monospace;font-size:12px;white-space:nowrap">${time}</td>
                <td><code>${a.src_ip || '—'}</code></td>
                <td><code style="font-size:11px">${a.dst_ip||'—'}:${a.dst_port||'?'}</code></td>
                <td>${a.protocol || '—'}</td>
                <td style="font-weight:500">${(a.threat_type||'').replace(/_/g,' ')}</td>
                <td><span class="badge ${a.severity}">${a.severity}</span></td>
                <td style="color:var(--text-muted)">${conf}</td>
                <td style="font-size:12px">${a.country || '—'}</td>
                <td>${abuse}</td>
            `;
            tbody.appendChild(tr);
        });

        renderPagination(offset, parseInt(f.limit));

    } catch (e) {
        tbody.innerHTML = `<tr><td colspan="10" style="color:var(--danger);padding:16px">
            Could not load alerts. Is the Python engine running?</td></tr>`;
    }
}

function renderPagination(offset, limit) {
    const container = document.getElementById('pagination');
    const pages     = Math.ceil(totalAlerts / limit);
    const current   = Math.floor(offset / limit);

    if (pages <= 1) { container.innerHTML = ''; return; }

    let html = `<button class="page-btn" onclick="loadAlerts(${Math.max(0,(current-1)*limit)})"
        ${current===0?'disabled':''}>← Prev</button>`;

    const start = Math.max(0, current - 2);
    const end   = Math.min(pages - 1, current + 2);

    for (let i = start; i <= end; i++) {
        html += `<button class="page-btn ${i===current?'active':''}"
            onclick="loadAlerts(${i*limit})">${i+1}</button>`;
    }

    html += `<button class="page-btn" onclick="loadAlerts(${Math.min((pages-1)*limit,(current+1)*limit)})"
        ${current>=pages-1?'disabled':''}>Next →</button>`;

    container.innerHTML = html;
}

async function exportCSV() {
    const f      = getFilters();
    const params = new URLSearchParams({
        endpoint:    'alerts',
        limit:       999,
        offset:      0,
        severity:    f.severity,
        threat_type: f.threat_type,
        src_ip:      f.src_ip,
    });

    const res    = await fetch('api_proxy.php?' + params);
    const data   = await res.json();
    const alerts = data.alerts || [];

    const headers = ['ID','Timestamp','Source IP','Dest IP','Dest Port',
                     'Protocol','Threat Type','Severity','Confidence',
                     'Abuse Score','Country','Description'];

    const rows = alerts.map(a => [
        a.id, a.timestamp, a.src_ip, a.dst_ip, a.dst_port,
        a.protocol, a.threat_type, a.severity,
        a.confidence ? (parseFloat(a.confidence)*100).toFixed(1)+'%' : '',
        a.abuse_score, a.country,
        (a.description||'').replace(/,/g,' '),
    ]);

    const csv  = [headers, ...rows].map(r => r.join(',')).join('\n');
    const blob = new Blob([csv], { type: 'text/csv' });
    const url  = URL.createObjectURL(blob);
    const a    = document.createElement('a');
    a.href     = url;
    a.download = `ids-alerts-${new Date().toISOString().slice(0,10)}.csv`;
    a.click();
    URL.revokeObjectURL(url);
}

// Load on page ready
loadAlerts(0);
</script>
</body>
</html>