const API      = 'api_proxy.php?endpoint=';
let charts     = {};
let alertCount = 0;

// ── Utility ───────────────────────────────────────────────────────────────
fetch("http://localhost:5000/api/stats", {
    headers: {
        "X-API-Key": "ids-api-key-change-me"
    }
})

function severityColor(s) {
    const map = {
        CRITICAL: '#ff4757',
        HIGH:     '#ffa502',
        MEDIUM:   '#ffd32a',
        LOW:      '#2ed573',
    };
    return map[s] || '#8b949e';
}

function severityBg(s) {
    const map = {
        CRITICAL: 'rgba(255,71,87,0.15)',
        HIGH:     'rgba(255,165,2,0.15)',
        MEDIUM:   'rgba(255,211,42,0.15)',
        LOW:      'rgba(46,213,115,0.15)',
    };
    return map[s] || 'rgba(139,148,158,0.15)';
}

function timeSince(dateStr) {
    const d    = new Date(dateStr);
    const secs = Math.floor((Date.now() - d) / 1000);
    if (secs < 60)  return `${secs}s ago`;
    if (secs < 3600) return `${Math.floor(secs/60)}m ago`;
    return `${Math.floor(secs/3600)}h ago`;
}

function formatTime(dateStr) {
    const d = new Date(dateStr);
    return d.toLocaleTimeString([], {hour:'2-digit', minute:'2-digit', second:'2-digit'});
}

// ── Stat cards ────────────────────────────────────────────────────────────

async function loadStats() {
    try {
        const res  = await fetch(API + 'stats');
        const data = await res.json();
        if (data.error) return;

        const sv = data.by_severity || {};

        const cards = {
            'stat-total':    { value: data.total_24h  || 0, class: 'total' },
            'stat-critical': { value: sv.CRITICAL      || 0, class: 'critical' },
            'stat-high':     { value: sv.HIGH          || 0, class: 'high' },
            'stat-medium':   { value: sv.MEDIUM        || 0, class: 'medium' },
            'stat-low':      { value: sv.LOW           || 0, class: 'low' },
        };

        for (const [id, info] of Object.entries(cards)) {
            const el = document.getElementById(id);
            if (el) el.textContent = info.value;
        }

        // Unread badge
        const unread = document.getElementById('unread-count');
        if (unread) {
            unread.textContent = data.unread || 0;
            unread.style.display = (data.unread > 0) ? 'inline-block' : 'none';
        }

        drawSeverityChart(sv);
        drawHourlyChart(data.hourly || []);
        drawThreatChart(data.top_threats || []);

    } catch (e) {
        console.error('loadStats error:', e);
    }
}

// ── Alert table ───────────────────────────────────────────────────────────

async function loadAlerts(limit = 20) {
    const tbody = document.getElementById('alert-body');
    if (!tbody) return;

    tbody.innerHTML = `
        <tr><td colspan="7" class="loading-overlay">
            <span class="spinner"></span>&nbsp; Loading alerts...
        </td></tr>`;

    try {
        const res  = await fetch(API + `alerts&limit=${limit}`);
        const data = await res.json();
        if (data.error || !data.alerts) {
            tbody.innerHTML = `<tr><td colspan="7" style="color:var(--text-muted);padding:16px">No alerts yet.</td></tr>`;
            return;
        }
        renderAlertRows(data.alerts, tbody, false);
        alertCount = data.alerts.length;
    } catch (e) {
        tbody.innerHTML = `<tr><td colspan="7" style="color:var(--danger);padding:16px">Could not load alerts.</td></tr>`;
    }
}

function renderAlertRows(alerts, tbody, prepend = false) {
    const rows = alerts.map(a => buildAlertRow(a));
    if (prepend) {
        rows.reverse().forEach(row => {
            const tr = createRowElement(row, a => a);
            tr.classList.add('new-row');
            tbody.prepend(tr);
        });
        // Keep max 50 rows in the live table
        while (tbody.rows.length > 50) tbody.deleteRow(tbody.rows.length - 1);
    } else {
        tbody.innerHTML = '';
        alerts.forEach(a => {
            tbody.appendChild(createRowElement(buildAlertRow(a)));
        });
    }
}

function buildAlertRow(a) {
    return {
        time:       formatTime(a.timestamp),
        src_ip:     a.src_ip     || '—',
        dst_ip:     a.dst_ip     || '—',
        dst_port:   a.dst_port   || '—',
        protocol:   a.protocol   || '—',
        threat:     a.threat_type || 'UNKNOWN',
        severity:   a.severity   || 'LOW',
        confidence: a.confidence ? (parseFloat(a.confidence) * 100).toFixed(1) + '%' : '—',
        id:         a.id,
    };
}

function createRowElement(r) {
    const tr       = document.createElement('tr');
    tr.style.cursor = 'pointer';
    tr.onclick      = () => window.location.href = `alert_detail.php?id=${r.id}`;
    tr.innerHTML    = `
        <td style="font-family:monospace;font-size:12px;color:var(--text-muted)">${r.time}</td>
        <td><code>${r.src_ip}</code></td>
        <td><code>${r.dst_ip}:${r.dst_port}</code></td>
        <td>${r.protocol}</td>
        <td style="font-weight:500">${r.threat}</td>
        <td><span class="badge ${r.severity}">${r.severity}</span></td>
        <td style="color:var(--text-muted)">${r.confidence}</td>
    `;
    return tr;
}

// ── Charts ────────────────────────────────────────────────────────────────

const CHART_DEFAULTS = {
    color:         '#e6edf3',
    gridColor:     'rgba(255,255,255,0.05)',
    tickColor:     '#8b949e',
    tooltipBg:     '#21262d',
    tooltipBorder: '#30363d',
};

function chartTooltipPlugin() {
    return {
        backgroundColor: CHART_DEFAULTS.tooltipBg,
        borderColor:     CHART_DEFAULTS.tooltipBorder,
        borderWidth:     1,
        titleColor:      '#e6edf3',
        bodyColor:       '#8b949e',
        padding:         10,
        cornerRadius:    6,
    };
}

function drawSeverityChart(sv) {
    const ctx = document.getElementById('severityChart');
    if (!ctx) return;

    const labels = ['Critical', 'High', 'Medium', 'Low'];
    const values = [sv.CRITICAL||0, sv.HIGH||0, sv.MEDIUM||0, sv.LOW||0];
    const colors = ['#ff4757', '#ffa502', '#ffd32a', '#2ed573'];

    if (charts.severity) charts.severity.destroy();

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
            responsive:       true,
            maintainAspectRatio: false,
            cutout:           '65%',
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: {
                        color:     '#8b949e',
                        font:      { size: 12 },
                        padding:   16,
                        usePointStyle: true,
                    }
                },
                tooltip: chartTooltipPlugin(),
            }
        }
    });
}

function drawHourlyChart(hourly) {
    const ctx = document.getElementById('hourlyChart');
    if (!ctx) return;

    const labels = hourly.map(h => h.hour);
    const values = hourly.map(h => h.count);

    if (charts.hourly) charts.hourly.destroy();

    charts.hourly = new Chart(ctx, {
        type: 'bar',
        data: {
            labels,
            datasets: [{
                label:           'Alerts',
                data:            values,
                backgroundColor: 'rgba(0,212,255,0.25)',
                borderColor:     '#00d4ff',
                borderWidth:     1,
                borderRadius:    4,
            }]
        },
        options: {
            responsive:          true,
            maintainAspectRatio: false,
            plugins: {
                legend:  { display: false },
                tooltip: chartTooltipPlugin(),
            },
            scales: {
                x: {
                    grid:  { color: CHART_DEFAULTS.gridColor },
                    ticks: { color: CHART_DEFAULTS.tickColor, font: { size: 11 } },
                },
                y: {
                    grid:       { color: CHART_DEFAULTS.gridColor },
                    ticks:      { color: CHART_DEFAULTS.tickColor, font: { size: 11 }, stepSize: 1 },
                    beginAtZero: true,
                }
            }
        }
    });
}

function drawThreatChart(threats) {
    const ctx = document.getElementById('threatChart');
    if (!ctx) return;

    const labels = threats.map(t => t.threat_type.replace(/_/g, ' '));
    const values = threats.map(t => t.count);

    if (charts.threat) charts.threat.destroy();

    charts.threat = new Chart(ctx, {
        type: 'bar',
        data: {
            labels,
            datasets: [{
                label:           'Count',
                data:            values,
                backgroundColor: 'rgba(124,58,237,0.3)',
                borderColor:     '#7c3aed',
                borderWidth:     1,
                borderRadius:    4,
            }]
        },
        options: {
            indexAxis:           'y',
            responsive:          true,
            maintainAspectRatio: false,
            plugins: {
                legend:  { display: false },
                tooltip: chartTooltipPlugin(),
            },
            scales: {
                x: {
                    grid:        { color: CHART_DEFAULTS.gridColor },
                    ticks:       { color: CHART_DEFAULTS.tickColor, font: { size: 11 } },
                    beginAtZero: true,
                },
                y: {
                    grid:  { color: CHART_DEFAULTS.gridColor },
                    ticks: { color: CHART_DEFAULTS.tickColor, font: { size: 11 } },
                }
            }
        }
    });
}

// ── WebSocket live feed ───────────────────────────────────────────────────

function initWebSocket() {
    const liveBadge = document.getElementById('live-badge');

    try {
        const socket = io('http://localhost:5000', {
            transports:       ['websocket', 'polling'],
            reconnectionDelay: 2000,
        });

        socket.on('connect', () => {
            console.log('[WS] Connected');
            if (liveBadge) {
                liveBadge.style.background = 'rgba(46,213,115,0.15)';
                liveBadge.style.color      = '#2ed573';
                liveBadge.textContent      = '● LIVE';
            }
        });

        socket.on('disconnect', () => {
            if (liveBadge) {
                liveBadge.style.background = 'rgba(255,71,87,0.15)';
                liveBadge.style.color      = '#ff4757';
                liveBadge.textContent      = '● RECONNECTING';
            }
        });

        socket.on('new_alert', alert => {
            prependAlertRow(alert);
            loadStats();
            playAlertSound(alert.severity);
            showToast(alert);
        });

    } catch (e) {
        console.warn('[WS] Socket.io not available:', e);
        if (liveBadge) {
            liveBadge.style.color = '#ff4757';
            liveBadge.textContent = '● OFFLINE';
        }
    }
}

function prependAlertRow(a) {
    const tbody = document.getElementById('alert-body');
    if (!tbody) return;

    const tr        = createRowElement(buildAlertRow(a));
    tr.classList.add('new-row');
    tbody.prepend(tr);

    while (tbody.rows.length > 50) tbody.deleteRow(tbody.rows.length - 1);
}

// ── Toast notification ────────────────────────────────────────────────────

function showToast(alert) {
    const container = document.getElementById('toast-container');
    if (!container) return;

    const toast       = document.createElement('div');
    toast.style.cssText = `
        background: var(--surface);
        border: 1px solid ${severityColor(alert.severity)};
        border-left: 3px solid ${severityColor(alert.severity)};
        border-radius: 8px;
        padding: 12px 16px;
        margin-bottom: 8px;
        min-width: 260px;
        max-width: 320px;
        animation: slideIn 0.3s ease;
        font-size: 13px;
    `;
    toast.innerHTML = `
        <div style="font-weight:500;color:${severityColor(alert.severity)};margin-bottom:4px">
            ${alert.severity} — ${alert.threat_type}
        </div>
        <div style="color:var(--text-muted);font-family:monospace;font-size:12px">
            ${alert.src_ip || 'unknown'} → port ${alert.dst_port || '?'}
        </div>
    `;

    container.appendChild(toast);
    setTimeout(() => toast.remove(), 5000);
}

// ── Alert sound ───────────────────────────────────────────────────────────

let audioCtx = null;

function playAlertSound(severity) {
    if (severity !== 'CRITICAL' && severity !== 'HIGH') return;
    try {
        if (!audioCtx) audioCtx = new (window.AudioContext || window.webkitAudioContext)();
        const osc  = audioCtx.createOscillator();
        const gain = audioCtx.createGain();
        osc.connect(gain);
        gain.connect(audioCtx.destination);
        osc.frequency.value = severity === 'CRITICAL' ? 880 : 660;
        osc.type            = 'sine';
        gain.gain.setValueAtTime(0.1, audioCtx.currentTime);
        gain.gain.exponentialRampToValueAtTime(0.001, audioCtx.currentTime + 0.4);
        osc.start();
        osc.stop(audioCtx.currentTime + 0.4);
    } catch (e) {}
}

// ── Init ──────────────────────────────────────────────────────────────────

document.addEventListener('DOMContentLoaded', () => {
    loadStats();
    loadAlerts(20);
    initWebSocket();

    // Auto refresh stats every 30 seconds
    setInterval(loadStats, 30000);

    // Auto refresh alerts every 60 seconds
    setInterval(() => loadAlerts(20), 60000);
});