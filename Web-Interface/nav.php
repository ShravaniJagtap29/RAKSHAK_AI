<?php
$current = basename($_SERVER['PHP_SELF']);

// API status check — fast timeout so it never slows page load
$api_online = false;
$ctx        = stream_context_create(['http' => ['timeout' => 1, 'ignore_errors' => true]]);
$ping       = @file_get_contents('http://localhost:5000/api/health', false, $ctx);
if ($ping !== false) {
    $health     = json_decode($ping, true);
    $api_online = ($health['status'] ?? '') === 'online';
}

$initials = strtoupper(substr($_SESSION['user'] ?? 'U', 0, 2));
$role     = $_SESSION['role'] ?? 'analyst';
?>
<aside class="sidebar">

    <div class="sidebar-logo">
        <div class="shield-sm">🛡</div>
        <span>RAKSHAKAI</span>
    </div>

    <nav class="sidebar-nav">
        <div class="nav-section">Monitor</div>

        <a href="index.php"
           class="nav-item <?= $current === 'index.php' ? 'active' : '' ?>"
           title="Dashboard (G then D)">
            <span class="icon">📊</span>
            <span>Dashboard</span>
        </a>

        <a href="livefeed.php"
           class="nav-item <?= $current === 'livefeed.php' ? 'active' : '' ?>"
           title="Live Feed (G then L)">
            <span class="icon">📡</span>
            <span>Live feed</span>
        </a>

        <div class="nav-section">Threats</div>

        <a href="alerts.php"
           class="nav-item <?= $current === 'alerts.php' || $current === 'alert_detail.php' ? 'active' : '' ?>"
           title="Alerts (G then A)">
            <span class="icon">🚨</span>
            <span>Alerts</span>
            <?php
            // Unread badge
            try {
                require_once 'db.php';
                $unread = $pdo->query("SELECT COUNT(*) FROM alerts WHERE is_read = 0")
                              ->fetchColumn();
                if ($unread > 0):
            ?>
            <span style="margin-left:auto;background:var(--danger);color:#fff;
                         font-size:10px;font-weight:700;padding:1px 6px;
                         border-radius:10px;min-width:18px;text-align:center">
                <?= min($unread, 99) ?>
            </span>
            <?php endif; } catch (Exception $e) {} ?>
        </a>

        <a href="blacklist.php"
           class="nav-item <?= $current === 'blacklist.php' ? 'active' : '' ?>">
            <span class="icon">🚫</span>
            <span>IP blacklist</span>
        </a>

        <div class="nav-section">Manage</div>

        <a href="rules.php"
           class="nav-item <?= $current === 'rules.php' ? 'active' : '' ?>">
            <span class="icon">📋</span>
            <span>Rules</span>
        </a>

        <a href="reports.php"
           class="nav-item <?= $current === 'reports.php' ? 'active' : '' ?>"
           title="Reports (G then R)">
            <span class="icon">📈</span>
            <span>Reports</span>
        </a>

        <?php if ($role === 'admin'): ?>
        <a href="settings.php"
           class="nav-item <?= $current === 'settings.php' ? 'active' : '' ?>"
           title="Settings (G then S)">
            <span class="icon">⚙️</span>
            <span>Settings</span>
        </a>
        <?php endif; ?>
    </nav>

    <div class="sidebar-footer">
        <!-- User badge -->
        <div class="user-badge">
            <div class="user-avatar"><?= $initials ?></div>
            <div class="user-info">
                <span><?= htmlspecialchars($_SESSION['user'] ?? '') ?></span>
                <small><?= htmlspecialchars($role) ?></small>
            </div>
        </div>

        <!-- Engine status -->
        <div style="padding:6px 10px;display:flex;align-items:center;gap:7px;
                    font-size:12px;color:var(--text-muted)">
            <span class="status-dot api-status-dot <?= $api_online ? 'online' : 'offline' ?>">
            </span>
            <span class="api-status-label" style="color:<?= $api_online ? 'var(--success)' : 'var(--danger)' ?>">
                <?= $api_online ? 'Engine online' : 'Engine offline' ?>
            </span>
        </div>

        <!-- Keyboard hint -->
        <div style="padding:4px 10px 8px;font-size:10px;color:var(--text-muted);
                    opacity:0.6">
            Press G then D/A/L/R/S to navigate
        </div>

        <a href="logout.php" class="nav-item" style="color:var(--danger)">
            <span class="icon">🚪</span>
            <span>Sign out</span>
        </a>
    </div>

</aside>