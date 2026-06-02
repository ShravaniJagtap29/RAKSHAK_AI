<?php
session_start();
if (!isset($_SESSION['user'])) {
    header('Location: login.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>IDS — Dashboard</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .chart-grid {
            display: grid;
            grid-template-columns: 300px 1fr 280px;
            gap: 16px;
            margin-bottom: 24px;
        }
        .chart-box {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 10px;
            padding: 16px;
        }
        .chart-box h3 {
            font-size: 13px;
            font-weight: 600;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: 0.06em;
            margin-bottom: 16px;
        }
        .chart-box canvas {
            max-height: 220px;
        }
        .alerts-card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 10px;
            padding: 16px;
        }
        .alerts-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 16px;
        }
        .alerts-header h3 {
            font-size: 13px;
            font-weight: 600;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: 0.06em;
        }
        .live-pill {
            padding: 3px 10px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
            background: rgba(46,213,115,0.15);
            color: #2ed573;
            transition: all 0.3s;
        }
        #toast-container {
            position: absolute;
            bottom: 24px;
            right: 24px;
            z-index: 999;
        }
        @keyframes slideIn {
            from { opacity:0; transform: translateX(20px); }
            to   { opacity:1; transform: translateX(0); }
        }
        .unread-badge {
            display: inline-block;
            background: var(--danger);
            color: #fff;
            font-size: 10px;
            font-weight: 700;
            padding: 1px 6px;
            border-radius: 10px;
            margin-left: 6px;
            vertical-align: middle;
        }
        @media (max-width: 1100px) {
            .chart-grid { grid-template-columns: 1fr 1fr; }
            .chart-grid .chart-box:last-child { grid-column: 1 / -1; }
        }
        @media (max-width: 700px) {
            .chart-grid { grid-template-columns: 1fr; }
        }
    </style>
    <script src="assets/js/app.js" defer></script>
</head>
<body>
<div class="app-layout">

    <?php include 'nav.php'; ?>

    <main class="main-content" style="position:relative">

        <div id="toast-container"></div>

        <!-- Page header -->
        <div class="page-header">
            <h2>Dashboard
                <span id="unread-count" class="unread-badge" style="display:none">0</span>
            </h2>
            <p>
                Real-time intrusion monitoring &nbsp;·&nbsp;
                Last 24 hours &nbsp;·&nbsp;
                <span style="font-size:12px;color:var(--text-muted)">
                    Auto-refreshes every 30s
                </span>
            </p>
        </div>

        <!-- Stat cards -->
        <div class="stat-grid">
            <div class="stat-card total">
                <div class="label">Total alerts (24h)</div>
                <div class="value" id="stat-total">—</div>
            </div>
            <div class="stat-card critical">
                <div class="label">Critical</div>
                <div class="value" id="stat-critical">—</div>
            </div>
            <div class="stat-card high">
                <div class="label">High</div>
                <div class="value" id="stat-high">—</div>
            </div>
            <div class="stat-card medium">
                <div class="label">Medium</div>
                <div class="value" id="stat-medium">—</div>
            </div>
            <div class="stat-card low">
                <div class="label">Low</div>
                <div class="value" id="stat-low">—</div>
            </div>
        </div>

        <!-- Charts row -->
        <div class="chart-grid">
            <div class="chart-box">
                <h3>Severity breakdown</h3>
                <canvas id="severityChart"></canvas>
            </div>
            <div class="chart-box">
                <h3>Alerts per hour (last 24h)</h3>
                <canvas id="hourlyChart"></canvas>
            </div>
            <div class="chart-box">
                <h3>Top threat types</h3>
                <canvas id="threatChart"></canvas>
            </div>
        </div>

        <!-- Live alerts table -->
        <div class="alerts-card">
            <div class="alerts-header">
                <h3>Live alerts</h3>
                <div style="display:flex;align-items:center;gap:12px">
                    <span class="live-pill" id="live-badge">● CONNECTING</span>
                    <a href="alerts.php"
                       style="font-size:12px;color:var(--accent)">
                        View all →
                    </a>
                </div>
            </div>

            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Time</th>
                            <th>Source IP</th>
                            <th>Destination</th>
                            <th>Protocol</th>
                            <th>Threat</th>
                            <th>Severity</th>
                            <th>Confidence</th>
                        </tr>
                    </thead>
                    <tbody id="alert-body">
                        <tr>
                            <td colspan="7" class="loading-overlay">
                                <span class="spinner"></span>&nbsp; Loading...
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

    </main>
</div>

<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>

<!-- Socket.io client -->
<script src="https://cdn.socket.io/4.7.2/socket.io.min.js"></script>

<!-- Our dashboard JS -->
<script src="assets/js/dashboard.js"></script>
</body>
</html>