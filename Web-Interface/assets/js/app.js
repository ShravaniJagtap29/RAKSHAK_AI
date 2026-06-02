/**
 * RAKSHAKAI — shared JS utilities
 * Include on every page after style.css
 */

// ── Global error handler ──────────────────────────────────────────────────────
window.addEventListener('unhandledrejection', e => {
    console.warn('Unhandled promise rejection:', e.reason);
});

// ── API fetch wrapper ─────────────────────────────────────────────────────────
async function apiFetch(endpoint, options = {}) {
    const url     = `api_proxy.php?endpoint=${endpoint}`;
    const defaults = { headers: { 'Content-Type': 'application/json' } };
    const config   = { ...defaults, ...options };

    try {
        const res  = await fetch(url, config);
        const data = await res.json();
        if (!res.ok) throw new Error(data.error || `HTTP ${res.status}`);
        return data;
    } catch (e) {
        console.error(`apiFetch(${endpoint}) failed:`, e);
        throw e;
    }
}

// ── Toast system ──────────────────────────────────────────────────────────────
(function initToasts() {
    const container       = document.createElement('div');
    container.id          = 'global-toasts';
    container.style.cssText = `
        position: fixed;
        bottom: 24px;
        right: 24px;
        z-index: 9999;
        display: flex;
        flex-direction: column;
        gap: 8px;
        pointer-events: none;
    `;
    document.body.appendChild(container);
})();

function showToast(message, type = 'info', duration = 4000) {
    const container = document.getElementById('global-toasts');
    if (!container) return;

    const colors = {
        success: 'var(--success)',
        error:   'var(--danger)',
        warning: 'var(--warning)',
        info:    'var(--accent)',
    };

    const toast             = document.createElement('div');
    toast.style.cssText     = `
        background: var(--surface);
        border: 1px solid var(--border);
        border-left: 3px solid ${colors[type] || colors.info};
        border-radius: 8px;
        padding: 12px 16px;
        font-size: 13px;
        color: var(--text);
        min-width: 240px;
        max-width: 340px;
        pointer-events: all;
        animation: slideIn 0.25s ease;
        cursor: pointer;
    `;
    toast.textContent       = message;
    toast.onclick           = () => toast.remove();
    container.appendChild(toast);
    setTimeout(() => {
        toast.style.opacity    = '0';
        toast.style.transition = 'opacity 0.3s';
        setTimeout(() => toast.remove(), 300);
    }, duration);
}

// ── Confirm dialog ────────────────────────────────────────────────────────────
function confirmAction(message, onConfirm) {
    if (window.confirm(message)) onConfirm();
}

// ── Format helpers ────────────────────────────────────────────────────────────
function formatTime(dateStr) {
    if (!dateStr) return '—';
    return new Date(dateStr).toLocaleTimeString([],
        { hour: '2-digit', minute: '2-digit', second: '2-digit' });
}

function formatDate(dateStr) {
    if (!dateStr) return '—';
    return new Date(dateStr).toLocaleDateString([], {
        year: 'numeric', month: 'short', day: 'numeric'
    });
}

function formatDateTime(dateStr) {
    if (!dateStr) return '—';
    return new Date(dateStr).toLocaleString();
}

function severityColor(s) {
    return {
        CRITICAL: 'var(--critical)',
        HIGH:     'var(--high)',
        MEDIUM:   'var(--medium)',
        LOW:      'var(--low)',
    }[s] || 'var(--text-muted)';
}

function severityIcon(s) {
    return { CRITICAL:'🔴', HIGH:'🟠', MEDIUM:'🟡', LOW:'🟢' }[s] || '⚪';
}

// ── Rate limiter (client side) ────────────────────────────────────────────────
const _callTimes = {};
function rateLimited(key, limitPerMinute = 10) {
    const now     = Date.now();
    const cutoff  = now - 60000;
    _callTimes[key] = (_callTimes[key] || []).filter(t => t > cutoff);
    if (_callTimes[key].length >= limitPerMinute) return true;
    _callTimes[key].push(now);
    return false;
}

// ── System status poll ────────────────────────────────────────────────────────
async function pollSystemStatus() {
    try {
        const res  = await fetch('health_check.php', { cache: 'no-store' });
        const data = await res.json();

        const dots = document.querySelectorAll('.api-status-dot');
        const labels = document.querySelectorAll('.api-status-label');

        dots.forEach(dot => {
            dot.className = 'status-dot api-status-dot ' +
                (data.api ? 'online' : 'offline');
        });
        labels.forEach(label => {
            label.textContent = data.api
                ? 'Engine online'
                : 'Engine offline';
            label.style.color = data.api
                ? 'var(--success)'
                : 'var(--danger)';
        });
    } catch (e) {
        // silent fail — nav handles its own status
    }
}

// Poll every 30 seconds
pollSystemStatus();
setInterval(pollSystemStatus, 30000);

// ── Keyboard shortcuts ────────────────────────────────────────────────────────
document.addEventListener('keydown', e => {
    // G then D = go to dashboard
    // G then A = go to alerts
    // G then L = go to live feed
    if (e.key === 'g' && !e.ctrlKey && !e.metaKey) {
        window._gPressed = true;
        setTimeout(() => { window._gPressed = false; }, 1000);
        return;
    }
    if (window._gPressed) {
        const map = {
            'd': 'index.php',
            'a': 'alerts.php',
            'l': 'livefeed.php',
            'r': 'reports.php',
            's': 'settings.php',
        };
        if (map[e.key]) {
            window.location.href = map[e.key];
            window._gPressed = false;
        }
    }
});