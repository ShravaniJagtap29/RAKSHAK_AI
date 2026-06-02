<?php
session_start();
if (!isset($_SESSION['user'])) { header('Location: login.php'); exit; }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>IDS — Rules</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .rules-layout {
            display: grid;
            grid-template-columns: 1fr 340px;
            gap: 16px;
            align-items: start;
        }
        .rules-card, .add-card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 10px;
            padding: 20px;
        }
        .rules-card h3, .add-card h3 {
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            color: var(--text-muted);
            margin-bottom: 16px;
            padding-bottom: 10px;
            border-bottom: 1px solid var(--border);
        }
        .rule-row {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 0;
            border-bottom: 1px solid var(--border);
        }
        .rule-row:last-child { border-bottom: none; }
        .rule-info { flex: 1; min-width: 0; }
        .rule-name {
            font-size: 14px;
            font-weight: 500;
            color: var(--text);
            margin-bottom: 3px;
        }
        .rule-pattern {
            font-size: 12px;
            color: var(--text-muted);
            font-family: monospace;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .rule-actions { display: flex; gap: 8px; align-items: center; flex-shrink: 0; }
        .action-badge {
            padding: 2px 8px;
            border-radius: 4px;
            font-size: 11px;
            font-weight: 600;
        }
        .action-badge.ALERT  { background:rgba(255,165,2,0.15);  color:var(--high); }
        .action-badge.BLOCK  { background:rgba(255,71,87,0.15);  color:var(--danger); }
        .action-badge.LOG    { background:rgba(139,148,158,0.15);color:var(--text-muted); }
        .toggle-btn {
            width: 40px;
            height: 22px;
            border-radius: 11px;
            border: none;
            cursor: pointer;
            position: relative;
            transition: background 0.2s;
            flex-shrink: 0;
        }
        .toggle-btn::after {
            content: '';
            position: absolute;
            width: 16px;
            height: 16px;
            border-radius: 50%;
            background: #fff;
            top: 3px;
            transition: left 0.2s;
        }
        .toggle-btn.on  { background: var(--success); }
        .toggle-btn.on::after  { left: 21px; }
        .toggle-btn.off { background: var(--border); }
        .toggle-btn.off::after { left: 3px; }
        .delete-btn {
            background: none;
            border: 1px solid var(--border);
            border-radius: 6px;
            color: var(--danger);
            font-size: 13px;
            padding: 4px 8px;
            cursor: pointer;
            transition: all 0.15s;
        }
        .delete-btn:hover { background: rgba(255,71,87,0.1); border-color: var(--danger); }
        .form-row {
            margin-bottom: 14px;
        }
        .form-row label {
            display: block;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            color: var(--text-muted);
            margin-bottom: 6px;
        }
        .form-row input,
        .form-row select,
        .form-row textarea {
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
        .form-row input:focus,
        .form-row select:focus,
        .form-row textarea:focus { border-color: var(--accent); }
        .form-row textarea { resize: vertical; min-height: 60px; font-family: monospace; }
        .hint {
            font-size: 11px;
            color: var(--text-muted);
            margin-top: 5px;
            line-height: 1.5;
        }
        .builtin-label {
            font-size: 10px;
            background: rgba(0,212,255,0.1);
            color: var(--accent);
            padding: 1px 6px;
            border-radius: 4px;
            font-weight: 600;
        }
        @media (max-width: 900px) {
            .rules-layout { grid-template-columns: 1fr; }
        }
    </style>
    <script src="assets/js/app.js" defer></script>
</head>
<body>
<div class="app-layout">
    <?php include 'nav.php'; ?>
    <main class="main-content">

        <div class="page-header">
            <h2>Detection rules</h2>
            <p>Manage built-in rules and add custom pattern rules</p>
        </div>

        <div class="rules-layout">

            <!-- Rules list -->
            <div class="rules-card">
                <h3>Active rules <span id="rule-count" style="color:var(--accent)"></span></h3>

                <!-- Built-in rules (always shown) -->
                <div id="builtin-rules">
                    <?php
                    $builtins = [
                        ['id'=>'builtin_1','name'=>'Null packet (TCP flags=0)',    'pattern'=>'flags=0,protocol_num=6','action'=>'ALERT'],
                        ['id'=>'builtin_2','name'=>'XMAS scan (FIN+PSH+URG)',      'pattern'=>'flags=41',              'action'=>'ALERT'],
                        ['id'=>'builtin_3','name'=>'Telnet access attempt',        'pattern'=>'dst_port=23',           'action'=>'ALERT'],
                        ['id'=>'builtin_4','name'=>'FTP unencrypted access',       'pattern'=>'dst_port=21',           'action'=>'LOG'],
                        ['id'=>'builtin_5','name'=>'RDP exposure (port 3389)',     'pattern'=>'dst_port=3389',         'action'=>'ALERT'],
                    ];
                    foreach ($builtins as $r): ?>
                    <div class="rule-row">
                        <div class="rule-info">
                            <div class="rule-name">
                                <?= htmlspecialchars($r['name']) ?>
                                <span class="builtin-label">built-in</span>
                            </div>
                            <div class="rule-pattern"><?= htmlspecialchars($r['pattern']) ?></div>
                        </div>
                        <div class="rule-actions">
                            <span class="action-badge <?= $r['action'] ?>"><?= $r['action'] ?></span>
                            <span style="font-size:12px;color:var(--success)">Always on</span>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>

                <!-- DB rules loaded by JS -->
                <div id="db-rules">
                    <div class="loading-overlay" style="padding:20px">
                        <span class="spinner"></span>&nbsp; Loading rules...
                    </div>
                </div>
            </div>

            <!-- Add rule form -->
            <div class="add-card">
                <h3>Add custom rule</h3>

                <div class="form-row">
                    <label>Rule name</label>
                    <input type="text" id="r-name" placeholder="e.g. Block Tor exit nodes">
                </div>

                <div class="form-row">
                    <label>Pattern</label>
                    <textarea id="r-pattern"
                        placeholder="dst_port=9001&#10;src_ip=185.220&#10;dst_port=4444,pkt_rate=>5"></textarea>
                    <div class="hint">
                        Format: <code>field=value</code> — multiple conditions separated by comma.<br>
                        Operators: <code>=</code> substring/regex &nbsp;
                        <code>&gt;N</code> greater than &nbsp; <code>&lt;N</code> less than.<br>
                        Fields: <code>src_ip</code>, <code>dst_ip</code>, <code>dst_port</code>,
                        <code>src_port</code>, <code>protocol_num</code>,
                        <code>flags</code>, <code>pkt_rate</code>, <code>packet_size</code>
                    </div>
                </div>

                <div class="form-row">
                    <label>Action</label>
                    <select id="r-action">
                        <option value="ALERT">ALERT — log and notify</option>
                        <option value="BLOCK">BLOCK — add IP to blacklist</option>
                        <option value="LOG">LOG — silent log only</option>
                    </select>
                </div>

                <button class="btn btn-primary" onclick="addRule()" style="margin-top:4px">
                    Add rule
                </button>

                <div id="add-feedback" style="margin-top:12px;font-size:13px"></div>

                <div style="margin-top:20px;padding-top:16px;border-top:1px solid var(--border)">
                    <div style="font-size:11px;font-weight:600;text-transform:uppercase;
                        letter-spacing:0.06em;color:var(--text-muted);margin-bottom:10px">
                        Pattern examples
                    </div>
                    <div style="font-size:12px;color:var(--text-muted);line-height:2">
                        <code>dst_port=9001</code> — Tor port<br>
                        <code>src_ip=185.220</code> — IP prefix block<br>
                        <code>dst_port=22,pkt_rate=>10</code> — SSH brute force<br>
                        <code>packet_size=>60000</code> — oversized packets<br>
                        <code>protocol_num=17,dst_port=53</code> — DNS traffic
                    </div>
                </div>
            </div>

        </div>

    </main>
</div>

<script>
async function loadRules() {
    try {
        const res   = await fetch('api_proxy.php?endpoint=rules');
        const rules = await res.json();

        if (!Array.isArray(rules)) {
            document.getElementById('db-rules').innerHTML =
                '<p style="color:var(--danger);font-size:13px;padding:12px">Could not load rules.</p>';
            return;
        }

        document.getElementById('rule-count').textContent = `(${5 + rules.length} total)`;

        if (rules.length === 0) {
            document.getElementById('db-rules').innerHTML =
                '<p style="color:var(--text-muted);font-size:13px;padding:12px 0">No custom rules yet.</p>';
            return;
        }

        document.getElementById('db-rules').innerHTML = rules.map(r => `
            <div class="rule-row" id="rule-${r.id}">
                <div class="rule-info">
                    <div class="rule-name">${escHtml(r.name)}</div>
                    <div class="rule-pattern">${escHtml(r.pattern || '—')}</div>
                </div>
                <div class="rule-actions">
                    <span class="action-badge ${r.action}">${r.action}</span>
                    <button class="toggle-btn ${r.enabled ? 'on' : 'off'}"
                        onclick="toggleRule(${r.id}, this)"
                        title="${r.enabled ? 'Click to disable' : 'Click to enable'}">
                    </button>
                    <button class="delete-btn" onclick="deleteRule(${r.id})">✕</button>
                </div>
            </div>
        `).join('');

    } catch (e) {
        document.getElementById('db-rules').innerHTML =
            '<p style="color:var(--danger);font-size:13px;padding:12px">Error loading rules.</p>';
    }
}

async function toggleRule(id, btn) {
    btn.style.opacity = '0.5';
    try {
        const res  = await fetch(`api_proxy.php?endpoint=rules/${id}/toggle`, { method: 'POST' });
        const data = await res.json();
        if (data.success) {
            btn.classList.toggle('on');
            btn.classList.toggle('off');
        }
    } catch (e) {
        alert('Could not toggle rule.');
    }
    btn.style.opacity = '1';
}

async function deleteRule(id) {
    if (!confirm('Delete this rule?')) return;
    try {
        const res  = await fetch(`api_proxy.php?endpoint=rules/${id}`, { method: 'DELETE' });
        const data = await res.json();
        if (data.success || res.ok) {
            const row = document.getElementById(`rule-${id}`);
            if (row) row.remove();
        }
    } catch (e) {
        alert('Could not delete rule.');
    }
}

async function addRule() {
    const name    = document.getElementById('r-name').value.trim();
    const pattern = document.getElementById('r-pattern').value.trim();
    const action  = document.getElementById('r-action').value;
    const feedback = document.getElementById('add-feedback');

    if (!name) {
        feedback.innerHTML = '<span style="color:var(--danger)">Rule name is required.</span>';
        return;
    }

    feedback.innerHTML = '<span style="color:var(--text-muted)">Saving...</span>';

    try {
        const res  = await fetch('api_proxy.php?endpoint=rules', {
            method:  'POST',
            headers: { 'Content-Type': 'application/json' },
            body:    JSON.stringify({ name, pattern, action }),
        });
        const data = await res.json();

        if (data.success) {
            feedback.innerHTML = '<span style="color:var(--success)">Rule added successfully.</span>';
            document.getElementById('r-name').value    = '';
            document.getElementById('r-pattern').value = '';
            loadRules();
            setTimeout(() => feedback.innerHTML = '', 3000);
        } else {
            feedback.innerHTML = `<span style="color:var(--danger)">Error: ${data.error||'unknown'}</span>`;
        }
    } catch (e) {
        feedback.innerHTML = '<span style="color:var(--danger)">Could not reach API.</span>';
    }
}

function escHtml(str) {
    return String(str)
        .replace(/&/g,'&amp;')
        .replace(/</g,'&lt;')
        .replace(/>/g,'&gt;')
        .replace(/"/g,'&quot;');
}

loadRules();
</script>
</body>
</html>