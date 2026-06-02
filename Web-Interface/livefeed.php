<?php
session_start();
if (!isset($_SESSION['user'])) { header('Location: login.php'); exit; }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>IDS — Live Feed</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .feed-layout {
            display: grid;
            grid-template-columns: 380px 1fr;
            gap: 16px;
            height: calc(100vh - 130px);
        }

        /* ── Left: ticker ── */
        .ticker-panel {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 10px;
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }
        .ticker-header {
            padding: 14px 16px;
            border-bottom: 1px solid var(--border);
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-shrink: 0;
        }
        .ticker-header h3 {
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            color: var(--text-muted);
        }
        .ticker-scroll {
            flex: 1;
            overflow-y: auto;
            padding: 8px;
        }
        .ticker-scroll::-webkit-scrollbar { width: 4px; }
        .ticker-scroll::-webkit-scrollbar-track { background: transparent; }
        .ticker-scroll::-webkit-scrollbar-thumb {
            background: var(--border);
            border-radius: 2px;
        }
        .ticker-item {
            border-radius: 8px;
            padding: 10px 12px;
            margin-bottom: 6px;
            border-left: 3px solid;
            font-size: 12px;
            animation: tickerIn 0.3s ease;
            cursor: pointer;
            transition: background 0.15s;
        }
        .ticker-item:hover { filter: brightness(1.1); }
        @keyframes tickerIn {
            from { opacity:0; transform:translateY(-8px); }
            to   { opacity:1; transform:translateY(0); }
        }
        .ticker-item.CRITICAL {
            background: rgba(255,71,87,0.08);
            border-color: var(--critical);
        }
        .ticker-item.HIGH {
            background: rgba(255,165,2,0.08);
            border-color: var(--high);
        }
        .ticker-item.MEDIUM {
            background: rgba(255,211,42,0.08);
            border-color: var(--medium);
        }
        .ticker-item.LOW {
            background: rgba(46,213,115,0.08);
            border-color: var(--low);
        }
        .ticker-top {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 4px;
        }
        .ticker-threat {
            font-weight: 600;
            font-size: 12px;
        }
        .ticker-time {
            font-size: 10px;
            color: var(--text-muted);
        }
        .ticker-ip {
            font-family: monospace;
            color: var(--text-muted);
            font-size: 11px;
        }

        /* ── Right: map + stats ── */
        .right-panel {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        /* Counter row */
        .counter-row {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 10px;
            flex-shrink: 0;
        }
        .counter-box {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 10px;
            padding: 14px 16px;
        }
        .counter-box .label {
            font-size: 11px;
            color: var(--text-muted);
            margin-bottom: 6px;
        }
        .counter-box .value {
            font-size: 26px;
            font-weight: 700;
            line-height: 1;
            transition: color 0.3s;
        }

        /* Map */
        .map-card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 10px;
            flex: 1;
            display: flex;
            flex-direction: column;
            overflow: hidden;
            min-height: 300px;
        }
        .map-header {
            padding: 12px 16px;
            border-bottom: 1px solid var(--border);
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-shrink: 0;
        }
        .map-header h3 {
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            color: var(--text-muted);
        }
        #world-map {
            flex: 1;
            position: relative;
            overflow: hidden;
        }
        #world-map svg {
            width: 100%;
            height: 100%;
        }

        /* Map dots */
        .map-dot {
            position: absolute;
            width: 10px;
            height: 10px;
            border-radius: 50%;
            transform: translate(-50%, -50%);
            cursor: pointer;
        }
        .map-dot::after {
            content: '';
            position: absolute;
            width: 100%;
            height: 100%;
            border-radius: 50%;
            animation: ping 1.5s ease-out infinite;
        }
        @keyframes ping {
            0%   { transform:scale(1);   opacity:0.8; }
            100% { transform:scale(3);   opacity:0; }
        }
        .map-dot.CRITICAL { background:#ff4757; }
        .map-dot.CRITICAL::after { background:#ff4757; }
        .map-dot.HIGH     { background:#ffa502; }
        .map-dot.HIGH::after     { background:#ffa502; }
        .map-dot.MEDIUM   { background:#ffd32a; }
        .map-dot.MEDIUM::after   { background:#ffd32a; }
        .map-dot.LOW      { background:#2ed573; }
        .map-dot.LOW::after      { background:#2ed573; }

        .map-tooltip {
            position: absolute;
            background: var(--surface2);
            border: 1px solid var(--border);
            border-radius: 6px;
            padding: 8px 12px;
            font-size: 12px;
            color: var(--text);
            pointer-events: none;
            z-index: 10;
            white-space: nowrap;
            display: none;
        }

        /* Top attackers table */
        .attackers-card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 10px;
            padding: 14px 16px;
            flex-shrink: 0;
        }
        .attackers-card h3 {
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            color: var(--text-muted);
            margin-bottom: 12px;
        }
        .attacker-row {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 6px 0;
            border-bottom: 1px solid var(--border);
            font-size: 13px;
        }
        .attacker-row:last-child { border-bottom: none; }
        .attacker-ip   { font-family: monospace; color: var(--text); flex: 1; }
        .attacker-count {
            font-weight: 600;
            color: var(--accent);
            min-width: 30px;
            text-align: right;
        }
        .attacker-bar-wrap {
            width: 80px;
            height: 4px;
            background: var(--surface2);
            border-radius: 2px;
            overflow: hidden;
        }
        .attacker-bar {
            height: 100%;
            background: var(--accent);
            border-radius: 2px;
        }

        @media (max-width: 1000px) {
            .feed-layout { grid-template-columns: 1fr; height: auto; }
            .counter-row { grid-template-columns: repeat(2, 1fr); }
        }
    </style>
    <script src="assets/js/app.js" defer></script>
</head>
<body>
<div class="app-layout">
    <?php include 'nav.php'; ?>
    <main class="main-content">

        <div class="page-header" style="margin-bottom:16px">
            <h2>Live feed</h2>
            <p>Real-time threat stream &nbsp;·&nbsp;
                <span id="ws-status" style="color:var(--text-muted)">Connecting...</span>
            </p>
        </div>

        <div class="feed-layout">

            <!-- Left: alert ticker -->
            <div class="ticker-panel">
                <div class="ticker-header">
                    <h3>Alert ticker</h3>
                    <div style="display:flex;gap:8px;align-items:center">
                        <span id="ticker-count"
                            style="font-size:12px;color:var(--accent);font-weight:600">
                            0 alerts
                        </span>
                        <button onclick="clearTicker()"
                            style="background:none;border:1px solid var(--border);
                                   border-radius:4px;color:var(--text-muted);
                                   font-size:11px;padding:2px 8px;cursor:pointer">
                            Clear
                        </button>
                    </div>
                </div>
                <div class="ticker-scroll" id="ticker-scroll">
                    <div style="padding:20px;text-align:center;color:var(--text-muted);font-size:13px">
                        Waiting for alerts...
                    </div>
                </div>
            </div>

            <!-- Right: counters + map + attackers -->
            <div class="right-panel">

                <!-- Counter row -->
                <div class="counter-row">
                    <div class="counter-box">
                        <div class="label">Packets/sec</div>
                        <div class="value" id="c-rate" style="color:var(--accent)">0</div>
                    </div>
                    <div class="counter-box">
                        <div class="label">Threats (session)</div>
                        <div class="value" id="c-threats" style="color:var(--danger)">0</div>
                    </div>
                    <div class="counter-box">
                        <div class="label">Critical</div>
                        <div class="value" id="c-critical" style="color:var(--critical)">0</div>
                    </div>
                    <div class="counter-box">
                        <div class="label">Unique IPs</div>
                        <div class="value" id="c-ips" style="color:var(--text-muted)">0</div>
                    </div>
                </div>

                <!-- World map -->
                <div class="map-card">
                    <div class="map-header">
                        <h3>Threat origin map</h3>
                        <div style="display:flex;gap:14px;font-size:11px">
                            <span><span style="color:var(--critical)">●</span> Critical</span>
                            <span><span style="color:var(--high)">●</span> High</span>
                            <span><span style="color:var(--medium)">●</span> Medium</span>
                            <span><span style="color:var(--low)">●</span> Low</span>
                        </div>
                    </div>
                    <div id="world-map">
                        <!-- SVG world map (simplified outlines) -->
                        <svg viewBox="0 0 1000 500" preserveAspectRatio="xMidYMid meet"
                             xmlns="http://www.w3.org/2000/svg">
                            <rect width="1000" height="500" fill="#0d1117"/>

                            <!-- Ocean background -->
                            <rect width="1000" height="500" fill="#0d1623" rx="0"/>

                            <!-- Simplified continent outlines -->
                            <!-- North America -->
                            <path d="M120 80 L240 70 L270 90 L280 130 L260 160 L240 200
                                     L220 230 L200 260 L180 280 L160 260 L140 230
                                     L120 200 L100 160 L90 120 Z"
                                  fill="#161b22" stroke="#21262d" stroke-width="1"/>
                            <!-- Central America -->
                            <path d="M180 280 L200 290 L210 310 L195 320 L175 310 Z"
                                  fill="#161b22" stroke="#21262d" stroke-width="1"/>
                            <!-- South America -->
                            <path d="M200 320 L260 310 L290 340 L300 390 L280 440
                                     L250 460 L220 450 L200 420 L185 380 L190 340 Z"
                                  fill="#161b22" stroke="#21262d" stroke-width="1"/>
                            <!-- Europe -->
                            <path d="M440 60 L500 55 L520 70 L510 100 L490 120
                                     L465 130 L445 110 L435 85 Z"
                                  fill="#161b22" stroke="#21262d" stroke-width="1"/>
                            <!-- Africa -->
                            <path d="M450 150 L510 140 L540 160 L550 220 L540 290
                                     L510 340 L480 360 L455 340 L440 280 L435 220
                                     L440 170 Z"
                                  fill="#161b22" stroke="#21262d" stroke-width="1"/>
                            <!-- Asia (simplified) -->
                            <path d="M520 55 L700 45 L780 65 L800 100 L790 140
                                     L750 160 L700 150 L650 160 L600 150 L560 130
                                     L530 110 L515 85 Z"
                                  fill="#161b22" stroke="#21262d" stroke-width="1"/>
                            <!-- India -->
                            <path d="M620 160 L660 155 L670 200 L650 240
                                     L625 235 L610 195 Z"
                                  fill="#161b22" stroke="#21262d" stroke-width="1"/>
                            <!-- Southeast Asia -->
                            <path d="M720 160 L780 155 L800 180 L780 200
                                     L740 195 L715 175 Z"
                                  fill="#161b22" stroke="#21262d" stroke-width="1"/>
                            <!-- China/East Asia -->
                            <path d="M700 80 L800 70 L840 95 L830 140
                                     L790 155 L750 145 L710 140 L695 110 Z"
                                  fill="#161b22" stroke="#21262d" stroke-width="1"/>
                            <!-- Japan -->
                            <path d="M830 90 L850 85 L860 110 L845 120 L828 108 Z"
                                  fill="#161b22" stroke="#21262d" stroke-width="1"/>
                            <!-- Australia -->
                            <path d="M740 300 L840 290 L870 320 L865 380
                                     L830 410 L770 415 L730 390 L720 350 Z"
                                  fill="#161b22" stroke="#21262d" stroke-width="1"/>
                            <!-- Greenland -->
                            <path d="M300 30 L370 25 L385 50 L360 70
                                     L310 65 L290 45 Z"
                                  fill="#161b22" stroke="#21262d" stroke-width="1"/>

                            <!-- Grid lines (longitude/latitude) -->
                            <g stroke="#21262d" stroke-width="0.4" opacity="0.5">
                                <line x1="0"    y1="125" x2="1000" y2="125"/>
                                <line x1="0"    y1="250" x2="1000" y2="250"/>
                                <line x1="0"    y1="375" x2="1000" y2="375"/>
                                <line x1="250"  y1="0"   x2="250"  y2="500"/>
                                <line x1="500"  y1="0"   x2="500"  y2="500"/>
                                <line x1="750"  y1="0"   x2="750"  y2="500"/>
                            </g>

                            <!-- Equator -->
                            <line x1="0" y1="250" x2="1000" y2="250"
                                  stroke="#21262d" stroke-width="0.8"
                                  stroke-dasharray="4 4" opacity="0.6"/>
                        </svg>

                        <!-- Dots container (absolutely positioned over SVG) -->
                        <div id="dots-layer"
                             style="position:absolute;top:0;left:0;
                                    width:100%;height:100%;pointer-events:none">
                        </div>

                        <!-- Tooltip -->
                        <div class="map-tooltip" id="map-tooltip"></div>
                    </div>
                </div>

                <!-- Top attackers -->
                <div class="attackers-card">
                    <h3>Top attacker IPs (session)</h3>
                    <div id="attacker-list">
                        <div style="color:var(--text-muted);font-size:13px">
                            No data yet...
                        </div>
                    </div>
                </div>

            </div>
        </div>

    </main>
</div>

<!-- Socket.io -->
<script src="https://cdn.socket.io/4.7.2/socket.io.min.js"></script>

<script>
// ── State ─────────────────────────────────────────────────────────────────────
let tickerCount = 0;
let sessionThreats  = 0;
let sessionCritical = 0;
let seenIPs   = new Set();
let attackers = {};    // ip -> count
let pktTimes  = [];    // timestamps for rate calculation
let mapDots   = {};    // ip -> dot element

// ── WebSocket ─────────────────────────────────────────────────────────────────
const socket = io('http://localhost:5000', {
    transports: ['websocket', 'polling'],
    reconnectionDelay: 2000,
});

const wsStatus = document.getElementById('ws-status');

socket.on('connect', () => {
    wsStatus.style.color = 'var(--success)';
    wsStatus.textContent = '● Connected — receiving live alerts';
});

socket.on('disconnect', () => {
    wsStatus.style.color = 'var(--danger)';
    wsStatus.textContent = '● Disconnected — reconnecting...';
});

socket.on('new_alert', alert => {
    handleAlert(alert);
});

// ── Alert handler ─────────────────────────────────────────────────────────────
function handleAlert(a) {
    const severity = a.severity || 'LOW';
    const threat   = (a.threat_type || 'UNKNOWN').replace(/_/g, ' ');
    const srcIp    = a.src_ip || 'unknown';
    const now      = new Date();

    // Track for rate
    pktTimes.push(Date.now());

    // Session counters
    sessionThreats++;
    if (severity === 'CRITICAL') sessionCritical++;
    seenIPs.add(srcIp);

    // Attackers leaderboard
    attackers[srcIp] = (attackers[srcIp] || 0) + 1;

    // Update counter display
    document.getElementById('c-threats').textContent  = sessionThreats;
    document.getElementById('c-critical').textContent = sessionCritical;
    document.getElementById('c-ips').textContent      = seenIPs.size;

    // Add ticker item
    addTickerItem(a, severity, threat, srcIp, now);

    // Place map dot
    if (a.latitude && a.longitude &&
        parseFloat(a.latitude) !== 0 &&
        parseFloat(a.longitude) !== 0) {
        placeMapDot(a, severity);
    }

    // Update attackers list
    updateAttackerList();
}

// ── Ticker ────────────────────────────────────────────────────────────────────
function addTickerItem(a, severity, threat, srcIp, now) {
    const scroll  = document.getElementById('ticker-scroll');
    const timeStr = now.toLocaleTimeString([], {
        hour: '2-digit', minute: '2-digit', second: '2-digit'
    });

    // Remove "waiting" placeholder on first alert
    if (tickerCount === 0) scroll.innerHTML = '';

    tickerCount++;
    document.getElementById('ticker-count').textContent =
        `${tickerCount} alert${tickerCount !== 1 ? 's' : ''}`;

    const item      = document.createElement('div');
    item.className  = `ticker-item ${severity}`;
    item.onclick    = () => {
        if (a.id) window.location.href = `alert_detail.php?id=${a.id}`;
    };
    item.innerHTML  = `
        <div class="ticker-top">
            <span class="ticker-threat" style="color:${sevColor(severity)}">${threat}</span>
            <span class="ticker-time">${timeStr}</span>
        </div>
        <div class="ticker-ip">
            ${srcIp}
            ${a.dst_port ? '→ :' + a.dst_port : ''}
            ${a.country  ? '&nbsp;·&nbsp;' + a.country : ''}
        </div>
        <div style="margin-top:4px">
            <span class="badge ${severity}">${severity}</span>
            ${a.confidence
                ? `<span style="color:var(--text-muted);font-size:11px;margin-left:6px">
                      ${(parseFloat(a.confidence)*100).toFixed(0)}% confidence
                  </span>`
                : ''}
        </div>
    `;

    scroll.prepend(item);

    // Keep max 100 items
    while (scroll.children.length > 100) {
        scroll.removeChild(scroll.lastChild);
    }
}

function clearTicker() {
    document.getElementById('ticker-scroll').innerHTML =
        '<div style="padding:20px;text-align:center;color:var(--text-muted);font-size:13px">Cleared.</div>';
    tickerCount = 0;
    document.getElementById('ticker-count').textContent = '0 alerts';
}

// ── Map dots ──────────────────────────────────────────────────────────────────
function latLngToPercent(lat, lng) {
    // Convert lat/lng to percentage position on our SVG map
    // Map spans approx: lng -180 to 180, lat 85 to -60
    const x = ((parseFloat(lng) + 180) / 360) * 100;
    const y = ((85 - parseFloat(lat)) / 145) * 100;
    return {
        x: Math.max(0, Math.min(100, x)),
        y: Math.max(0, Math.min(100, y)),
    };
}

function placeMapDot(alert, severity) {
    const layer = document.getElementById('dots-layer');
    const pos   = latLngToPercent(alert.latitude, alert.longitude);
    const ip    = alert.src_ip;

    // If dot for this IP already exists, pulse it instead of creating new one
    if (mapDots[ip]) {
        const existing = mapDots[ip];
        existing.style.animation = 'none';
        existing.offsetHeight;  // reflow
        existing.style.animation = '';
        return;
    }

    const dot       = document.createElement('div');
    dot.className   = `map-dot ${severity}`;
    dot.style.left  = pos.x + '%';
    dot.style.top   = pos.y + '%';
    dot.style.pointerEvents = 'all';

    // Tooltip on hover
    dot.addEventListener('mouseenter', e => {
        const tip     = document.getElementById('map-tooltip');
        tip.innerHTML = `
            <strong style="color:${sevColor(severity)}">${(alert.threat_type||'').replace(/_/g,' ')}</strong><br>
            IP: ${ip}<br>
            ${alert.country ? 'Country: ' + alert.country + '<br>' : ''}
            Port: ${alert.dst_port || '?'}
        `;
        tip.style.display = 'block';
        tip.style.left    = (e.offsetX + 12) + 'px';
        tip.style.top     = (e.offsetY - 10) + 'px';
    });

    dot.addEventListener('mousemove', e => {
        const tip     = document.getElementById('map-tooltip');
        tip.style.left = (e.offsetX + 12) + 'px';
        tip.style.top  = (e.offsetY - 10) + 'px';
    });

    dot.addEventListener('mouseleave', () => {
        document.getElementById('map-tooltip').style.display = 'none';
    });

    dot.addEventListener('click', () => {
        if (alert.id) window.location.href = `alert_detail.php?id=${alert.id}`;
    });

    layer.appendChild(dot);
    mapDots[ip] = dot;

    // Remove dot after 60 seconds to keep map clean
    setTimeout(() => {
        if (layer.contains(dot)) layer.removeChild(dot);
        delete mapDots[ip];
    }, 60000);
}

// ── Attackers leaderboard ─────────────────────────────────────────────────────
function updateAttackerList() {
    const sorted = Object.entries(attackers)
        .sort((a, b) => b[1] - a[1])
        .slice(0, 6);

    if (sorted.length === 0) return;

    const maxCount = sorted[0][1];
    const list     = document.getElementById('attacker-list');

    list.innerHTML = sorted.map(([ip, count]) => `
        <div class="attacker-row">
            <span class="attacker-ip">${ip}</span>
            <div class="attacker-bar-wrap">
                <div class="attacker-bar"
                     style="width:${Math.round((count/maxCount)*100)}%">
                </div>
            </div>
            <span class="attacker-count">${count}</span>
        </div>
    `).join('');
}

// ── Packets/sec counter ───────────────────────────────────────────────────────
setInterval(() => {
    const now    = Date.now();
    const cutoff = now - 5000;
    pktTimes     = pktTimes.filter(t => t > cutoff);
    const rate   = Math.round(pktTimes.length / 5);
    document.getElementById('c-rate').textContent = rate;
}, 1000);

// ── Helpers ───────────────────────────────────────────────────────────────────
function sevColor(s) {
    return {
        CRITICAL: 'var(--critical)',
        HIGH:     'var(--high)',
        MEDIUM:   'var(--medium)',
        LOW:      'var(--low)',
    }[s] || 'var(--text-muted)';
}

// ── Load existing alerts on page open ─────────────────────────────────────────
async function loadRecentAlerts() {
    try {
        const res  = await fetch('api_proxy.php?endpoint=alerts&limit=20');
        const data = await res.json();
        const list = (data.alerts || []).reverse();

        list.forEach(a => handleAlert(a));
    } catch (e) {
        console.warn('Could not preload alerts:', e);
    }
}

loadRecentAlerts();
</script>
</body>
</html>