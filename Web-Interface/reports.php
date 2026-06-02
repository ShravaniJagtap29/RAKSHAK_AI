<?php
session_start();
if (!isset($_SESSION['user'])) { header('Location: login.php'); exit; }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>RAKSHAKAI — Reports</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <style>
        .report-controls {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 10px;
            padding: 16px 20px;
            margin-bottom: 20px;
            display: flex;
            align-items: flex-end;
            gap: 16px;
            flex-wrap: wrap;
        }
        .ctrl-group {
            display: flex;
            flex-direction: column;
            gap: 5px;
        }
        .ctrl-group label {
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            color: var(--text-muted);
        }
        .ctrl-group input,
        .ctrl-group select {
            background: var(--surface2);
            border: 1px solid var(--border);
            border-radius: 6px;
            padding: 8px 12px;
            color: var(--text);
            font-size: 13px;
            outline: none;
            min-width: 150px;
        }
        .ctrl-group input:focus,
        .ctrl-group select:focus { border-color: var(--accent); }
        .ctrl-actions {
            display: flex;
            gap: 8px;
            margin-left: auto;
            align-items: flex-end;
        }
        .btn-sm {
            padding: 8px 16px;
            border-radius: 6px;
            border: 1px solid var(--border);
            background: var(--surface2);
            color: var(--text);
            font-size: 13px;
            cursor: pointer;
            transition: all 0.15s;
            white-space: nowrap;
        }
        .btn-sm:hover { border-color: var(--accent); color: var(--accent); }
        .btn-sm.primary {
            background: var(--accent);
            color: #000;
            border-color: var(--accent);
            font-weight: 500;
        }
        .btn-sm.primary:hover { opacity: 0.85; }
        .btn-sm.purple {
            background: rgba(124,58,237,0.15);
            color: #a78bfa;
            border-color: rgba(124,58,237,0.3);
        }
        .btn-sm.purple:hover { background: rgba(124,58,237,0.25); }

        /* Summary cards */
        .summary-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 14px;
            margin-bottom: 20px;
        }
        .summary-card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 10px;
            padding: 18px;
        }
        .summary-card .s-label {
            font-size: 12px;
            color: var(--text-muted);
            margin-bottom: 8px;
        }
        .summary-card .s-value {
            font-size: 32px;
            font-weight: 700;
            line-height: 1;
        }
        .summary-card .s-sub {
            font-size: 12px;
            color: var(--text-muted);
            margin-top: 6px;
        }

        /* Chart grid */
        .chart-grid-2 {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
            margin-bottom: 20px;
        }
        .chart-grid-3 {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr;
            gap: 16px;
            margin-bottom: 20px;
        }
        .chart-card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 10px;
            padding: 18px;
        }
        .chart-card h3 {
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.07em;
            color: var(--text-muted);
            margin-bottom: 16px;
        }
        .chart-card canvas {
            max-height: 240px;
        }

        /* Top tables */
        .table-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
            margin-bottom: 20px;
        }
        .table-card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 10px;
            padding: 18px;
        }
        .table-card h3 {
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.07em;
            color: var(--text-muted);
            margin-bottom: 14px;
        }
        .rank-row {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 7px 0;
            border-bottom: 1px solid var(--border);
            font-size: 13px;
        }
        .rank-row:last-child { border-bottom: none; }
        .rank-num {
            font-size: 11px;
            font-weight: 700;
            color: var(--text-muted);
            min-width: 18px;
        }
        .rank-label { flex: 1; color: var(--text); font-family: monospace; font-size: 12px; }
        .rank-bar-wrap { width: 70px; height: 4px; background: var(--surface2); border-radius: 2px; overflow: hidden; }
        .rank-bar { height: 100%; border-radius: 2px; }
        .rank-count { font-weight: 600; color: var(--accent); min-width: 28px; text-align: right; }

        /* Loading overlay */
        .report-loading {
            display: none;
            position: fixed;
            top: 0; left: 0;
            width: 100%; height: 100%;
            background: rgba(13,17,23,0.7);
            z-index: 999;
            align-items: center;
            justify-content: center;
            flex-direction: column;
            gap: 16px;
        }
        .report-loading.show { display: flex; }
        .report-loading p { color: var(--text); font-size: 14px; }

        @media (max-width: 1000px) {
            .summary-grid  { grid-template-columns: repeat(2,1fr); }
            .chart-grid-2  { grid-template-columns: 1fr; }
            .chart-grid-3  { grid-template-columns: 1fr; }
            .table-grid    { grid-template-columns: 1fr; }
        }
    </style>
    <script src="assets/js/app.js" defer></script>
</head>
<body>
<div class="app-layout">
    <?php include 'nav.php'; ?>
    <main class="main-content">

        <!-- Loading overlay -->
        <div class="report-loading" id="loading-overlay">
            <span class="spinner" style="width:32px;height:32px;border-width:3px"></span>
            <p id="loading-msg">Generating report...</p>
        </div>

        <div class="page-header">
            <h2>Reports</h2>
            <p>Security analytics and threat summaries</p>
        </div>

        <!-- Date range controls -->
        <div class="report-controls">
            <div class="ctrl-group">
                <label>Preset range</label>
                <select id="preset" onchange="applyPreset()">
                    <option value="1">Last 24 hours</option>
                    <option value="7" selected>Last 7 days</option>
                    <option value="30">Last 30 days</option>
                    <option value="0">Custom range</option>
                </select>
            </div>
            <div class="ctrl-group" id="custom-dates" style="display:none">
                <label>From</label>
                <input type="date" id="date-from">
            </div>
            <div class="ctrl-group" id="custom-dates2" style="display:none">
                <label>To</label>
                <input type="date" id="date-to">
            </div>
            <div class="ctrl-actions">
                <button class="btn-sm" onclick="loadReport()">Generate</button>
                <button class="btn-sm purple" onclick="exportPDF()">Export PDF</button>
                <button class="btn-sm" onclick="exportCSV()">Export CSV</button>
            </div>
        </div>

        <!-- Summary cards -->
        <div class="summary-grid">
            <div class="summary-card">
                <div class="s-label">Total alerts</div>
                <div class="s-value" id="r-total" style="color:var(--accent)">—</div>
                <div class="s-sub" id="r-total-sub"></div>
            </div>
            <div class="summary-card">
                <div class="s-label">Critical threats</div>
                <div class="s-value" id="r-critical" style="color:var(--critical)">—</div>
                <div class="s-sub" id="r-critical-sub"></div>
            </div>
            <div class="summary-card">
                <div class="s-label">Unique attackers</div>
                <div class="s-value" id="r-ips" style="color:var(--high)">—</div>
                <div class="s-sub">unique source IPs</div>
            </div>
            <div class="summary-card">
                <div class="s-label">Blocked IPs</div>
                <div class="s-value" id="r-blocked" style="color:var(--success)">—</div>
                <div class="s-sub">in blacklist</div>
            </div>
        </div>

        <!-- Charts row 1 -->
        <div class="chart-grid-2">
            <div class="chart-card">
                <h3>Alerts over time</h3>
                <canvas id="timelineChart"></canvas>
            </div>
            <div class="chart-card">
                <h3>Severity distribution</h3>
                <canvas id="severityChart"></canvas>
            </div>
        </div>

        <!-- Charts row 2 -->
        <div class="chart-grid-3">
            <div class="chart-card">
                <h3>Top threat types</h3>
                <canvas id="threatChart"></canvas>
            </div>
            <div class="chart-card">
                <h3>Top targeted ports</h3>
                <canvas id="portChart"></canvas>
            </div>
            <div class="chart-card">
                <h3>Protocol breakdown</h3>
                <canvas id="protoChart"></canvas>
            </div>
        </div>

        <!-- Top tables -->
        <div class="table-grid">
            <div class="table-card">
                <h3>Top attacker IPs</h3>
                <div id="top-ips-list">
                    <div class="loading-overlay"><span class="spinner"></span></div>
                </div>
            </div>
            <div class="table-card">
                <h3>Top threat types</h3>
                <div id="top-threats-list">
                    <div class="loading-overlay"><span class="spinner"></span></div>
                </div>
            </div>
        </div>

    </main>
</div>

<script>
let charts      = {};
let reportData  = null;
let reportDays  = 7;

const TOOLTIP = {
    backgroundColor: '#21262d',
    borderColor:     '#30363d',
    borderWidth:     1,
    titleColor:      '#e6edf3',
    bodyColor:       '#8b949e',
    padding:         10,
    cornerRadius:    6,
};

// ── Date helpers ──────────────────────────────────────────────────────────────

function applyPreset() {
    const val      = document.getElementById('preset').value;
    const custom1  = document.getElementById('custom-dates');
    const custom2  = document.getElementById('custom-dates2');
    if (val === '0') {
        custom1.style.display = '';
        custom2.style.display = '';
        const today    = new Date();
        const weekAgo  = new Date(today - 7 * 86400000);
        document.getElementById('date-to').value   = today.toISOString().slice(0,10);
        document.getElementById('date-from').value = weekAgo.toISOString().slice(0,10);
    } else {
        custom1.style.display = 'none';
        custom2.style.display = 'none';
    }
}

function getDateRange() {
    const preset = document.getElementById('preset').value;
    if (preset !== '0') {
        reportDays = parseInt(preset);
        const to   = new Date();
        const from = new Date(to - reportDays * 86400000);
        return {
            from: from.toISOString().slice(0,10),
            to:   to.toISOString().slice(0,10),
            days: reportDays,
        };
    }
    return {
        from: document.getElementById('date-from').value,
        to:   document.getElementById('date-to').value,
        days: 0,
    };
}

// ── Load report ───────────────────────────────────────────────────────────────

async function loadReport() {
    showLoading('Loading report data...');
    const range = getDateRange();

    try {
        // Fetch alerts for the range
        const limit  = 1000;
        const res    = await fetch(
            `api_proxy.php?endpoint=alerts&limit=${limit}&offset=0`
        );
        const data   = await res.json();
        const all    = data.alerts || [];

        // Filter by date range
        const from   = new Date(range.from + 'T00:00:00');
        const to     = new Date(range.to   + 'T23:59:59');
        const alerts = all.filter(a => {
            const d = new Date(a.timestamp);
            return d >= from && d <= to;
        });

        // Fetch stats and blacklist count
        const [statsRes, blRes] = await Promise.all([
            fetch('api_proxy.php?endpoint=stats'),
            fetch('api_proxy.php?endpoint=blacklist'),
        ]);
        const stats     = await statsRes.json();
        const blacklist = await blRes.json();

        reportData = { alerts, stats, blacklist, range };
        hideLoading();
        renderReport(alerts, stats, blacklist, range);

    } catch (e) {
        hideLoading();
        console.error('Report error:', e);
        alert('Could not load report data. Is the Python engine running?');
    }
}

// ── Render ────────────────────────────────────────────────────────────────────

function renderReport(alerts, stats, blacklist, range) {
    const total    = alerts.length;
    const critical = alerts.filter(a => a.severity === 'CRITICAL').length;
    const ips      = new Set(alerts.map(a => a.src_ip).filter(Boolean)).size;
    const blocked  = Array.isArray(blacklist) ? blacklist.length : 0;
    const prevPct  = total > 0 ? '' : 'No data';

    // Summary cards
    document.getElementById('r-total').textContent    = total.toLocaleString();
    document.getElementById('r-critical').textContent = critical.toLocaleString();
    document.getElementById('r-ips').textContent      = ips.toLocaleString();
    document.getElementById('r-blocked').textContent  = blocked.toLocaleString();
    document.getElementById('r-total-sub').textContent =
        `over ${range.days || 'custom'} day period`;
    document.getElementById('r-critical-sub').textContent =
        total > 0 ? `${((critical/total)*100).toFixed(1)}% of total` : '';

    // Build aggregated data
    const byDate     = {};
    const bySeverity = { CRITICAL:0, HIGH:0, MEDIUM:0, LOW:0 };
    const byThreat   = {};
    const byPort     = {};
    const byProto    = {};

    alerts.forEach(a => {
        // By date
        const d = (a.timestamp || '').slice(0,10);
        if (d) byDate[d] = (byDate[d] || 0) + 1;

        // By severity
        if (a.severity in bySeverity) bySeverity[a.severity]++;

        // By threat type
        const t = a.threat_type || 'UNKNOWN';
        byThreat[t] = (byThreat[t] || 0) + 1;

        // By port
        if (a.dst_port) {
            const p = String(a.dst_port);
            byPort[p] = (byPort[p] || 0) + 1;
        }

        // By protocol
        const pr = a.protocol || 'OTHER';
        byProto[pr] = (byProto[pr] || 0) + 1;
    });

    // Top IPs
    const ipCounts = {};
    alerts.forEach(a => {
        if (a.src_ip) ipCounts[a.src_ip] = (ipCounts[a.src_ip] || 0) + 1;
    });
    const topIPs = Object.entries(ipCounts)
        .sort((a,b) => b[1]-a[1]).slice(0,8);
    const topThreats = Object.entries(byThreat)
        .sort((a,b) => b[1]-a[1]).slice(0,8);

    drawTimelineChart(byDate, range);
    drawSeverityChart(bySeverity);
    drawThreatChart(byThreat);
    drawPortChart(byPort);
    drawProtoChart(byProto);
    renderRankList('top-ips-list',     topIPs,     '#00d4ff');
    renderRankList('top-threats-list', topThreats, '#7c3aed');
}

// ── Charts ────────────────────────────────────────────────────────────────────

function destroyChart(id) {
    if (charts[id]) { charts[id].destroy(); delete charts[id]; }
}

function drawTimelineChart(byDate, range) {
    destroyChart('timeline');
    const ctx = document.getElementById('timelineChart');

    // Fill all dates in range
    const labels = [];
    const values = [];
    const from   = new Date(range.from + 'T00:00:00');
    const to     = new Date(range.to   + 'T00:00:00');
    for (let d = new Date(from); d <= to; d.setDate(d.getDate()+1)) {
        const key = d.toISOString().slice(0,10);
        labels.push(key.slice(5));   // MM-DD
        values.push(byDate[key] || 0);
    }

    charts.timeline = new Chart(ctx, {
        type: 'line',
        data: {
            labels,
            datasets: [{
                label:           'Alerts',
                data:            values,
                borderColor:     '#00d4ff',
                backgroundColor: 'rgba(0,212,255,0.08)',
                borderWidth:     2,
                pointRadius:     3,
                pointBackgroundColor: '#00d4ff',
                tension:         0.3,
                fill:            true,
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { display:false }, tooltip: TOOLTIP },
            scales: {
                x: { grid: { color:'rgba(255,255,255,0.05)' },
                     ticks: { color:'#8b949e', font:{size:11} } },
                y: { grid: { color:'rgba(255,255,255,0.05)' },
                     ticks: { color:'#8b949e', font:{size:11}, stepSize:1 },
                     beginAtZero: true },
            }
        }
    });
}

function drawSeverityChart(bySeverity) {
    destroyChart('severity');
    const ctx    = document.getElementById('severityChart');
    const labels = ['Critical', 'High', 'Medium', 'Low'];
    const values = [bySeverity.CRITICAL, bySeverity.HIGH, bySeverity.MEDIUM, bySeverity.LOW];
    const colors = ['#ff4757', '#ffa502', '#ffd32a', '#2ed573'];

    charts.severity = new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels,
            datasets: [{
                data:            values,
                backgroundColor: colors.map(c => c + '99'),
                borderColor:     colors,
                borderWidth:     2,
                hoverOffset:     8,
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            cutout: '60%',
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: { color:'#8b949e', font:{size:11},
                              padding:12, usePointStyle:true }
                },
                tooltip: TOOLTIP,
            }
        }
    });
}

function drawThreatChart(byThreat) {
    destroyChart('threat');
    const ctx    = document.getElementById('threatChart');
    const sorted = Object.entries(byThreat).sort((a,b)=>b[1]-a[1]).slice(0,6);
    const labels = sorted.map(([k]) => k.replace(/_/g,' '));
    const values = sorted.map(([,v]) => v);

    charts.threat = new Chart(ctx, {
        type: 'bar',
        data: {
            labels,
            datasets: [{
                data:            values,
                backgroundColor: 'rgba(124,58,237,0.3)',
                borderColor:     '#7c3aed',
                borderWidth:     1,
                borderRadius:    4,
            }]
        },
        options: {
            indexAxis: 'y',
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend:{display:false}, tooltip: TOOLTIP },
            scales: {
                x: { grid:{color:'rgba(255,255,255,0.05)'},
                     ticks:{color:'#8b949e',font:{size:10}}, beginAtZero:true },
                y: { grid:{color:'rgba(255,255,255,0.05)'},
                     ticks:{color:'#8b949e',font:{size:10}} },
            }
        }
    });
}

function drawPortChart(byPort) {
    destroyChart('port');
    const ctx    = document.getElementById('portChart');
    const sorted = Object.entries(byPort).sort((a,b)=>b[1]-a[1]).slice(0,6);
    const labels = sorted.map(([k]) => 'Port ' + k);
    const values = sorted.map(([,v]) => v);

    charts.port = new Chart(ctx, {
        type: 'bar',
        data: {
            labels,
            datasets: [{
                data:            values,
                backgroundColor: 'rgba(255,71,87,0.25)',
                borderColor:     '#ff4757',
                borderWidth:     1,
                borderRadius:    4,
            }]
        },
        options: {
            indexAxis: 'y',
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend:{display:false}, tooltip: TOOLTIP },
            scales: {
                x: { grid:{color:'rgba(255,255,255,0.05)'},
                     ticks:{color:'#8b949e',font:{size:10}}, beginAtZero:true },
                y: { grid:{color:'rgba(255,255,255,0.05)'},
                     ticks:{color:'#8b949e',font:{size:10}} },
            }
        }
    });
}

function drawProtoChart(byProto) {
    destroyChart('proto');
    const ctx    = document.getElementById('protoChart');
    const labels = Object.keys(byProto);
    const values = Object.values(byProto);
    const colors = ['#00d4ff','#ffa502','#2ed573','#ff4757','#7c3aed'];

    charts.proto = new Chart(ctx, {
        type: 'pie',
        data: {
            labels,
            datasets: [{
                data:            values,
                backgroundColor: colors.map(c => c + '99'),
                borderColor:     colors,
                borderWidth:     2,
                hoverOffset:     6,
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: { color:'#8b949e', font:{size:11},
                              padding:10, usePointStyle:true }
                },
                tooltip: TOOLTIP,
            }
        }
    });
}

function renderRankList(containerId, items, color) {
    const el = document.getElementById(containerId);
    if (!items.length) {
        el.innerHTML = '<p style="color:var(--text-muted);font-size:13px">No data.</p>';
        return;
    }
    const max = items[0][1];
    el.innerHTML = items.map(([label, count], i) => `
        <div class="rank-row">
            <span class="rank-num">${i+1}</span>
            <span class="rank-label">${label.replace(/_/g,' ')}</span>
            <div class="rank-bar-wrap">
                <div class="rank-bar"
                     style="width:${Math.round((count/max)*100)}%;background:${color}">
                </div>
            </div>
            <span class="rank-count">${count}</span>
        </div>
    `).join('');
}

// ── Export PDF ────────────────────────────────────────────────────────────────

function exportPDF() {
    if (!reportData) { alert('Generate a report first.'); return; }
    showLoading('Generating PDF...');

    const range   = reportData.range;
    const alerts  = reportData.alerts;
    const total   = alerts.length;
    const critical = alerts.filter(a => a.severity === 'CRITICAL').length;
    const high     = alerts.filter(a => a.severity === 'HIGH').length;
    const medium   = alerts.filter(a => a.severity === 'MEDIUM').length;
    const low      = alerts.filter(a => a.severity === 'LOW').length;
    const ips      = new Set(alerts.map(a => a.src_ip)).size;

    // Threat breakdown
    const byThreat = {};
    alerts.forEach(a => {
        const t = a.threat_type || 'UNKNOWN';
        byThreat[t] = (byThreat[t] || 0) + 1;
    });
    const topThreats = Object.entries(byThreat)
        .sort((a,b) => b[1]-a[1]).slice(0,5);

    // Top IPs
    const byIP = {};
    alerts.forEach(a => {
        if (a.src_ip) byIP[a.src_ip] = (byIP[a.src_ip] || 0) + 1;
    });
    const topIPs = Object.entries(byIP)
        .sort((a,b) => b[1]-a[1]).slice(0,5);

    // Recent critical alerts
    const criticalAlerts = alerts
        .filter(a => a.severity === 'CRITICAL')
        .slice(0,10);

    // Build HTML for PDF
    const html = `
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<style>
  body { font-family: Arial, sans-serif; color: #1a1a2e; background: #fff; margin: 0; padding: 0; }
  .cover { background: linear-gradient(135deg, #0d1117, #161b22);
           color: #fff; padding: 60px 50px; min-height: 200px; }
  .cover h1 { font-size: 32px; margin: 0 0 8px; color: #00d4ff; }
  .cover p  { color: #8b949e; font-size: 14px; margin: 4px 0; }
  .section  { padding: 30px 50px; border-bottom: 1px solid #eee; }
  .section h2 { font-size: 16px; color: #0d1117; border-left: 4px solid #00d4ff;
                padding-left: 12px; margin-bottom: 16px; }
  .stat-row { display: flex; gap: 20px; margin-bottom: 20px; flex-wrap: wrap; }
  .stat-box { flex: 1; min-width: 100px; background: #f6f8fa;
              border-radius: 8px; padding: 14px; text-align: center; }
  .stat-box .v { font-size: 28px; font-weight: 700; color: #0d1117; }
  .stat-box .l { font-size: 11px; color: #8b949e; margin-top: 4px;
                 text-transform: uppercase; letter-spacing: 0.05em; }
  .stat-box.critical .v { color: #ff4757; }
  .stat-box.high .v     { color: #ffa502; }
  .stat-box.medium .v   { color: #e6a817; }
  .stat-box.low .v      { color: #2ed573; }
  table { width: 100%; border-collapse: collapse; font-size: 12px; }
  th { background: #f6f8fa; padding: 8px 10px; text-align: left;
       font-size: 11px; text-transform: uppercase; letter-spacing: 0.05em; color: #8b949e; }
  td { padding: 8px 10px; border-bottom: 1px solid #f0f0f0; }
  .badge { display:inline-block; padding:2px 7px; border-radius:4px;
           font-size:10px; font-weight:700; text-transform:uppercase; }
  .badge.CRITICAL { background:#fff0f0; color:#ff4757; }
  .badge.HIGH     { background:#fff8f0; color:#ffa502; }
  .badge.MEDIUM   { background:#fffdf0; color:#e6a817; }
  .badge.LOW      { background:#f0fff5; color:#2ed573; }
  .footer { padding: 20px 50px; color: #8b949e; font-size: 11px; text-align: center; }
</style>
</head>
<body>

<div class="cover">
  <h1>🛡 RAKSHAKAI</h1>
  <h2 style="color:#fff;font-size:20px;margin:8px 0">Security Report</h2>
  <p>Period: ${range.from} to ${range.to}</p>
  <p>Generated: ${new Date().toLocaleString()}</p>
</div>

<div class="section">
  <h2>Executive Summary</h2>
  <div class="stat-row">
    <div class="stat-box">
      <div class="v">${total}</div><div class="l">Total Alerts</div>
    </div>
    <div class="stat-box critical">
      <div class="v">${critical}</div><div class="l">Critical</div>
    </div>
    <div class="stat-box high">
      <div class="v">${high}</div><div class="l">High</div>
    </div>
    <div class="stat-box medium">
      <div class="v">${medium}</div><div class="l">Medium</div>
    </div>
    <div class="stat-box low">
      <div class="v">${low}</div><div class="l">Low</div>
    </div>
    <div class="stat-box">
      <div class="v">${ips}</div><div class="l">Unique IPs</div>
    </div>
  </div>
</div>

<div class="section">
  <h2>Top Threat Types</h2>
  <table>
    <tr><th>#</th><th>Threat Type</th><th>Count</th><th>% of Total</th></tr>
    ${topThreats.map(([t,c],i) => `
    <tr>
      <td>${i+1}</td>
      <td>${t.replace(/_/g,' ')}</td>
      <td><strong>${c}</strong></td>
      <td>${total > 0 ? ((c/total)*100).toFixed(1)+'%' : '—'}</td>
    </tr>`).join('')}
  </table>
</div>

<div class="section">
  <h2>Top Attacker IPs</h2>
  <table>
    <tr><th>#</th><th>Source IP</th><th>Alerts</th><th>% of Total</th></tr>
    ${topIPs.map(([ip,c],i) => `
    <tr>
      <td>${i+1}</td>
      <td style="font-family:monospace">${ip}</td>
      <td><strong>${c}</strong></td>
      <td>${total > 0 ? ((c/total)*100).toFixed(1)+'%' : '—'}</td>
    </tr>`).join('')}
  </table>
</div>

<div class="section">
  <h2>Recent Critical Alerts</h2>
  <table>
    <tr><th>Time</th><th>Source IP</th><th>Threat Type</th><th>Severity</th><th>Port</th></tr>
    ${criticalAlerts.length === 0
        ? '<tr><td colspan="5" style="color:#8b949e">No critical alerts in this period.</td></tr>'
        : criticalAlerts.map(a => `
    <tr>
      <td style="font-family:monospace;font-size:11px">${a.timestamp||'—'}</td>
      <td style="font-family:monospace">${a.src_ip||'—'}</td>
      <td>${(a.threat_type||'').replace(/_/g,' ')}</td>
      <td><span class="badge ${a.severity}">${a.severity}</span></td>
      <td>${a.dst_port||'—'}</td>
    </tr>`).join('')}
  </table>
</div>

<div class="footer">
  RAKSHAKAI Intrusion Detection System &nbsp;·&nbsp;
  Report generated ${new Date().toLocaleString()} &nbsp;·&nbsp;
  Confidential
</div>
</body>
</html>`;

    // Open in new window and trigger print-to-PDF
    const win = window.open('', '_blank');
    win.document.write(html);
    win.document.close();
    win.onload = () => {
        hideLoading();
        win.focus();
        win.print();
    };
}

// ── Export CSV ────────────────────────────────────────────────────────────────

function exportCSV() {
    if (!reportData || !reportData.alerts.length) {
        alert('Generate a report first.');
        return;
    }
    const alerts  = reportData.alerts;
    const headers = ['ID','Timestamp','Source IP','Dest IP','Dest Port',
                     'Protocol','Threat Type','Severity','Confidence',
                     'Abuse Score','Country','Description'];
    const rows    = alerts.map(a => [
        a.id, a.timestamp, a.src_ip, a.dst_ip, a.dst_port,
        a.protocol, a.threat_type, a.severity,
        a.confidence ? (parseFloat(a.confidence)*100).toFixed(1)+'%' : '',
        a.abuse_score || 0, a.country || '',
        (a.description||'').replace(/,/g,' '),
    ]);
    const csv  = [headers, ...rows].map(r => r.join(',')).join('\n');
    const blob = new Blob([csv], { type:'text/csv' });
    const url  = URL.createObjectURL(blob);
    const a    = document.createElement('a');
    a.href     = url;
    a.download = `rakshakai-report-${reportData.range.from}-to-${reportData.range.to}.csv`;
    a.click();
    URL.revokeObjectURL(url);
}

// ── Loading helpers ───────────────────────────────────────────────────────────

function showLoading(msg) {
    document.getElementById('loading-msg').textContent = msg;
    document.getElementById('loading-overlay').classList.add('show');
}

function hideLoading() {
    document.getElementById('loading-overlay').classList.remove('show');
}

// Load on page open
loadReport();
</script>
</body>
</html>