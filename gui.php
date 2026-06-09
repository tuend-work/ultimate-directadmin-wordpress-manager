<?php
/**
 * Ultimate DirectAdmin WordPress Manager
 * Front-end GUI Template — WP Toolkit style (clean & simple)
 */

$username = getenv('USERNAME') ?: getenv('USER') ?: 'user';
$isAdmin = is_admin_user();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>WordPress Manager</title>
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Arial, sans-serif;
            font-size: 13px;
            background: #f1f1f1;
            color: #23282d;
            line-height: 1.5;
        }

        /* ── Top bar ── */
        .topbar {
            background: #1d2327;
            color: #a7aaad;
            padding: 0 20px;
            display: flex;
            align-items: center;
            gap: 16px;
            height: 36px;
            font-size: 12px;
        }
        .topbar .logo { color: #fff; font-weight: 600; font-size: 14px; }
        .topbar .user { margin-left: auto; }

        /* ── Page header ── */
        .page-header {
            background: #fff;
            border-bottom: 1px solid #dcdcde;
            padding: 12px 20px;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .page-header h1 {
            font-size: 18px;
            font-weight: 600;
            color: #1d2327;
        }

        /* ── Toolbar ── */
        .toolbar {
            background: #fff;
            border-bottom: 1px solid #dcdcde;
            padding: 8px 20px;
            display: flex;
            align-items: center;
            gap: 6px;
            flex-wrap: wrap;
        }
        .toolbar .sep {
            width: 1px;
            height: 20px;
            background: #dcdcde;
            margin: 0 4px;
        }
        .count-label {
            margin-left: auto;
            color: #646970;
            font-size: 12px;
        }

        /* ── Buttons ── */
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 5px 12px;
            font-size: 12px;
            font-family: inherit;
            border-radius: 3px;
            cursor: pointer;
            text-decoration: none;
            border: 1px solid transparent;
            line-height: 1.6;
            white-space: nowrap;
        }
        .btn:disabled { opacity: .5; cursor: not-allowed; }
        .btn-primary {
            background: #2271b1;
            border-color: #2271b1;
            color: #fff;
        }
        .btn-primary:hover:not(:disabled) { background: #135e96; border-color: #135e96; }
        .btn-secondary {
            background: #f6f7f7;
            border-color: #dcdcde;
            color: #2c3338;
        }
        .btn-secondary:hover:not(:disabled) { background: #f0f0f1; border-color: #8c8f94; }
        .btn-danger { background: #fff; border-color: #cc1818; color: #cc1818; }
        .btn-danger:hover:not(:disabled) { background: #cc1818; color: #fff; }
        .btn-link { background: none; border: none; color: #2271b1; padding: 4px 6px; cursor: pointer; font-size: 12px; }
        .btn-link:hover { color: #135e96; text-decoration: underline; }
        .btn-sm { padding: 3px 8px; font-size: 11px; }

        /* ── Content area ── */
        .content { padding: 16px 20px; }

        /* ── Site cards ── */
        .site-card {
            background: #fff;
            border: 1px solid #dcdcde;
            border-radius: 3px;
            margin-bottom: 8px;
            overflow: hidden;
        }
        .site-card-header {
            display: flex;
            align-items: center;
            padding: 10px 14px;
            gap: 12px;
            border-bottom: 1px solid #f0f0f1;
            cursor: pointer;
            user-select: none;
        }
        .site-card-header:hover { background: #f9f9f9; }

        .wp-icon {
            width: 32px;
            height: 32px;
            background: #0073aa;
            border-radius: 4px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #fff;
            font-size: 16px;
            font-weight: 700;
            flex-shrink: 0;
        }

        .site-info { flex: 1; min-width: 0; }
        .site-name {
            font-weight: 600;
            font-size: 14px;
            color: #1d2327;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .site-url { color: #646970; font-size: 11px; }
        .site-url a { color: #2271b1; text-decoration: none; }
        .site-url a:hover { text-decoration: underline; }

        .site-badges { display: flex; gap: 6px; align-items: center; flex-wrap: wrap; }

        .badge {
            display: inline-flex;
            align-items: center;
            gap: 3px;
            padding: 2px 8px;
            border-radius: 10px;
            font-size: 11px;
            font-weight: 500;
            border: 1px solid transparent;
        }
        .badge-ok { background: #edfaef; color: #1a7431; border-color: #c3e6cb; }
        .badge-warn { background: #fff8e5; color: #996800; border-color: #f5c942; }
        .badge-error { background: #fbeaea; color: #8a1f1f; border-color: #f5c6c6; }
        .badge-version { background: #f0f6fc; color: #044289; border-color: #c8e1ff; }

        .site-actions { display: flex; gap: 4px; align-items: center; }

        /* ── Site body (expanded) ── */
        .site-card-body {
            display: none;
            padding: 14px;
            background: #fdfdfd;
            border-top: 1px solid #f0f0f1;
        }
        .site-card-body.open { display: block; }

        .detail-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 12px;
            margin-bottom: 14px;
        }
        .detail-item label {
            display: block;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
            color: #646970;
            margin-bottom: 2px;
        }
        .detail-item .val {
            font-size: 12px;
            color: #1d2327;
            font-family: monospace;
        }

        .action-row {
            display: flex;
            gap: 6px;
            padding-top: 10px;
            border-top: 1px solid #f0f0f1;
            flex-wrap: wrap;
        }

        /* ── Empty state ── */
        .empty-state {
            text-align: center;
            padding: 40px 20px;
            color: #646970;
        }
        .empty-state .icon { font-size: 36px; margin-bottom: 10px; }
        .empty-state p { margin-top: 6px; }

        /* ── Search ── */
        .search-row {
            margin-bottom: 12px;
            display: flex;
            gap: 8px;
            align-items: center;
        }
        .search-input {
            padding: 5px 10px;
            border: 1px solid #dcdcde;
            border-radius: 3px;
            font-size: 12px;
            width: 260px;
            font-family: inherit;
        }
        .search-input:focus { outline: none; border-color: #2271b1; box-shadow: 0 0 0 1px #2271b1; }

        /* ── Modals ── */
        .modal-overlay {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(0,0,0,.55);
            z-index: 9000;
            align-items: center;
            justify-content: center;
        }
        .modal-overlay.open { display: flex; }
        .modal {
            background: #fff;
            border-radius: 4px;
            box-shadow: 0 5px 40px rgba(0,0,0,.3);
            width: 100%;
            max-width: 540px;
            max-height: 90vh;
            overflow-y: auto;
        }
        .modal-head {
            padding: 14px 18px;
            border-bottom: 1px solid #dcdcde;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .modal-head h3 { font-size: 15px; font-weight: 600; }
        .modal-close { background: none; border: none; font-size: 18px; cursor: pointer; color: #646970; line-height: 1; }
        .modal-close:hover { color: #cc1818; }
        .modal-body { padding: 18px; }
        .modal-footer {
            padding: 12px 18px;
            border-top: 1px solid #dcdcde;
            display: flex;
            justify-content: flex-end;
            gap: 8px;
        }

        /* ── Form ── */
        .form-section { margin-bottom: 16px; }
        .form-section-title {
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
            color: #646970;
            border-bottom: 1px solid #f0f0f1;
            padding-bottom: 4px;
            margin-bottom: 10px;
        }
        .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; }
        .form-group { margin-bottom: 10px; }
        .form-group label {
            display: block;
            font-size: 12px;
            font-weight: 600;
            margin-bottom: 3px;
            color: #2c3338;
        }
        .form-control {
            width: 100%;
            padding: 5px 8px;
            border: 1px solid #dcdcde;
            border-radius: 3px;
            font-size: 12px;
            font-family: inherit;
            color: #2c3338;
        }
        .form-control:focus { outline: none; border-color: #2271b1; box-shadow: 0 0 0 1px #2271b1; }
        .input-group { display: flex; gap: 4px; }
        .input-prefix {
            background: #f6f7f7;
            border: 1px solid #dcdcde;
            border-radius: 3px;
            padding: 5px 8px;
            font-size: 12px;
            white-space: nowrap;
            border-right: none;
            border-radius: 3px 0 0 3px;
        }
        .input-group .form-control { border-radius: 0 3px 3px 0; }

        /* ── Notice / alert ── */
        .notice {
            padding: 8px 12px;
            border-left: 4px solid #dcdcde;
            background: #f9f9f9;
            font-size: 12px;
            margin-bottom: 10px;
        }
        .notice-error { border-color: #cc1818; background: #fbeaea; color: #8a1f1f; }
        .notice-warn  { border-color: #dba617; background: #fff8e5; }

        /* ── Toast ── */
        #toast-area {
            position: fixed;
            bottom: 20px;
            right: 20px;
            z-index: 9999;
            display: flex;
            flex-direction: column;
            gap: 6px;
        }
        .toast {
            background: #1d2327;
            color: #fff;
            padding: 9px 16px;
            border-radius: 3px;
            font-size: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,.3);
            animation: fadeIn .2s ease;
            max-width: 320px;
        }
        .toast.ok  { border-left: 4px solid #00a32a; }
        .toast.err { border-left: 4px solid #cc1818; }
        @keyframes fadeIn { from { opacity:0; transform:translateY(6px); } to { opacity:1; transform:none; } }

        /* ── Admin terminal ── */
        .terminal {
            background: #1d2327;
            color: #a7aaad;
            font-family: monospace;
            font-size: 12px;
            padding: 12px;
            border-radius: 3px;
            height: 200px;
            overflow-y: auto;
            margin-top: 10px;
        }
        .terminal .ok  { color: #00a32a; }
        .terminal .err { color: #cc1818; }

        /* ── Chevron toggle ── */
        .chevron { font-size: 10px; color: #646970; transition: transform .2s; }
        .chevron.open { transform: rotate(90deg); }
    </style>
</head>
<body>

<!-- Top bar -->
<div class="topbar">
    <span class="logo">WordPress Manager</span>
    <span>DirectAdmin Edition</span>
    <span class="user">👤 <?php echo htmlspecialchars($username); ?></span>
    <?php if ($isAdmin): ?>
    <span class="badge badge-ok" style="font-size:11px;">Admin</span>
    <?php endif; ?>
</div>

<!-- Page heading -->
<div class="page-header">
    <span style="font-size:22px;">⊞</span>
    <h1>WordPress Installations</h1>
</div>

<!-- Toolbar -->
<div class="toolbar">
    <button class="btn btn-primary" onclick="openInstallModal()">+ Install WordPress</button>
    <button class="btn btn-secondary" id="btn-scan" onclick="triggerScan()">🔄 Scan Hosting</button>
    <?php if ($isAdmin): ?>
    <div class="sep"></div>
    <button class="btn btn-secondary" onclick="openUpdateModal()">↑ Update Plugin</button>
    <?php endif; ?>
    <span class="count-label" id="count-label">Loading…</span>
</div>

<!-- Main content -->
<div class="content">
    <div class="search-row">
        <input type="text" id="search-input" class="search-input" placeholder="Search by name, URL, path…" oninput="filterSites()">
    </div>

    <div id="sites-container">
        <div class="empty-state">
            <div class="icon">⏳</div>
            <strong>Loading installations…</strong>
        </div>
    </div>
</div>

<!-- ═══════════════ MODAL: Install WordPress ═══════════════ -->
<div class="modal-overlay" id="modal-install">
    <div class="modal">
        <div class="modal-head">
            <h3>Install WordPress</h3>
            <button class="modal-close" onclick="closeModal('modal-install')">✕</button>
        </div>
        <form onsubmit="executeInstall(event)">
        <div class="modal-body">
            <div class="form-section">
                <div class="form-section-title">Website Location</div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Protocol</label>
                        <select id="inst-protocol" class="form-control">
                            <option value="https">https://</option>
                            <option value="http">http://</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Domain / Subdomain</label>
                        <select id="inst-domain" class="form-control" required>
                            <option value="" disabled selected>Loading…</option>
                        </select>
                    </div>
                </div>
                <div class="form-group">
                    <label>Directory <span style="font-weight:400;color:#646970;">(optional — leave empty for domain root)</span></label>
                    <input type="text" id="inst-subdir" class="form-control" placeholder="e.g. blog">
                </div>
            </div>

            <div class="form-section">
                <div class="form-section-title">Database</div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Database Name</label>
                        <div class="input-group">
                            <span class="input-prefix"><?php echo htmlspecialchars($username); ?>_</span>
                            <input type="text" id="inst-dbname" class="form-control" placeholder="wp1" required maxlength="16">
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Database User</label>
                        <div class="input-group">
                            <span class="input-prefix"><?php echo htmlspecialchars($username); ?>_</span>
                            <input type="text" id="inst-dbuser" class="form-control" placeholder="wpuser" required maxlength="16">
                        </div>
                    </div>
                </div>
                <div class="form-group">
                    <label>Database Password</label>
                    <div class="input-group" style="gap:6px;">
                        <input type="text" id="inst-dbpass" class="form-control" required placeholder="Password">
                        <button type="button" class="btn btn-secondary" onclick="genPass('inst-dbpass')">Generate</button>
                    </div>
                </div>
            </div>

            <div class="form-section">
                <div class="form-section-title">WordPress Admin</div>
                <div class="form-group">
                    <label>Site Title</label>
                    <input type="text" id="inst-title" class="form-control" required placeholder="My WordPress Site">
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Admin Username</label>
                        <input type="text" id="inst-adminuser" class="form-control" required placeholder="admin">
                    </div>
                    <div class="form-group">
                        <label>Admin Password</label>
                        <div class="input-group" style="gap:6px;">
                            <input type="text" id="inst-adminpass" class="form-control" required placeholder="Password">
                            <button type="button" class="btn btn-secondary" onclick="genPass('inst-adminpass')">Gen</button>
                        </div>
                    </div>
                </div>
                <div class="form-group">
                    <label>Admin Email</label>
                    <input type="email" id="inst-adminemail" class="form-control" required value="admin@<?php echo htmlspecialchars($username); ?>.com">
                </div>
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" onclick="closeModal('modal-install')">Cancel</button>
            <button type="submit" class="btn btn-primary" id="btn-install-submit">Install WordPress</button>
        </div>
        </form>
    </div>
</div>

<!-- ═══════════════ MODAL: Delete ═══════════════ -->
<div class="modal-overlay" id="modal-delete">
    <div class="modal" style="max-width:440px;">
        <div class="modal-head">
            <h3>Delete WordPress</h3>
            <button class="modal-close" onclick="closeModal('modal-delete')">✕</button>
        </div>
        <div class="modal-body">
            <div class="notice notice-error">⚠️ This action permanently deletes all files and cannot be undone.</div>
            <p style="margin-bottom:10px;">Deleting installation at:</p>
            <code id="del-path" style="background:#f6f7f7;display:block;padding:6px 10px;border-radius:3px;font-size:12px;word-break:break-all;"></code>

            <div style="margin-top:14px;">
                <label style="display:flex;align-items:center;gap:8px;font-size:12px;cursor:pointer;">
                    <input type="checkbox" id="del-db-check" checked style="width:14px;height:14px;">
                    Also delete database: <strong id="del-db-name"></strong>
                </label>
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn btn-secondary" onclick="closeModal('modal-delete')">Cancel</button>
            <button class="btn btn-danger" id="btn-delete-confirm" onclick="executeDelete()">Delete</button>
        </div>
    </div>
</div>

<?php if ($isAdmin): ?>
<!-- ═══════════════ MODAL: Update Plugin ═══════════════ -->
<div class="modal-overlay" id="modal-update">
    <div class="modal" style="max-width:500px;">
        <div class="modal-head">
            <h3>Update Plugin from GitHub</h3>
            <button class="modal-close" id="btn-close-update" onclick="closeModal('modal-update')">✕</button>
        </div>
        <div class="modal-body">
            <p style="font-size:12px;color:#646970;">Downloads the latest version from the public GitHub repository and replaces the current plugin files.</p>
            <div class="terminal" id="update-terminal"></div>
        </div>
        <div class="modal-footer">
            <button class="btn btn-secondary" id="btn-update-done" disabled onclick="closeModal('modal-update')">Close</button>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Toast area -->
<div id="toast-area"></div>

<script>
const DA_USER = '<?php echo $username; ?>';
let allSites = [];
let deletePath = '', deleteDb = '';

/* ─── API URL helper ─── */
const apiUrl = (action = '') => {
    let base = window.location.pathname.split('?')[0];
    if (base.endsWith('.html') || base.endsWith('.raw'))
        base = base.substring(0, base.lastIndexOf('/') + 1);
    else if (!base.endsWith('/'))
        base += '/';
    return base + 'index.raw' + (action ? '?action=' + action : '');
};

/* ─── Toast ─── */
function toast(msg, type = 'info') {
    const el = document.createElement('div');
    el.className = 'toast' + (type === 'success' ? ' ok' : type === 'error' ? ' err' : '');
    el.textContent = msg;
    document.getElementById('toast-area').appendChild(el);
    setTimeout(() => el.remove(), 4000);
}

/* ─── Modal helpers ─── */
function openModal(id)  { document.getElementById(id).classList.add('open'); }
function closeModal(id) { document.getElementById(id).classList.remove('open'); }

/* ─── Password generator ─── */
function genPass(id) {
    const chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%&*';
    let p = 'wp';
    for (let i = 0; i < 12; i++) p += chars[Math.floor(Math.random() * chars.length)];
    document.getElementById(id).value = p;
}

/* ─── Site card expand/collapse ─── */
function toggleCard(id) {
    const body = document.getElementById('body-' + id);
    const chev = document.getElementById('chev-' + id);
    body.classList.toggle('open');
    chev.classList.toggle('open');
}

/* ─── Render sites ─── */
function renderSites(sites) {
    const cont = document.getElementById('sites-container');
    const lbl  = document.getElementById('count-label');
    lbl.textContent = sites.length + ' installation' + (sites.length !== 1 ? 's' : '') + ' total';

    if (!sites.length) {
        cont.innerHTML = `<div class="empty-state">
            <div class="icon">📂</div>
            <strong>No WordPress installations found</strong>
            <p>Click <em>Scan Hosting</em> to search, or <em>Install WordPress</em> to create one.</p>
        </div>`;
        return;
    }

    cont.innerHTML = sites.map((s, i) => {
        const statusBadge = s.status === 'active'
            ? '<span class="badge badge-ok">● Connected</span>'
            : '<span class="badge badge-error">● DB Error</span>';
        const pathDisplay = s.path.replace('/home/' + DA_USER + '/', '~/');

        return `<div class="site-card">
            <div class="site-card-header" onclick="toggleCard(${i})">
                <div class="wp-icon">W</div>
                <div class="site-info">
                    <div class="site-name">${esc(s.blogname)}</div>
                    <div class="site-url"><a href="${esc(s.siteurl)}" target="_blank">${esc(s.siteurl)}</a></div>
                </div>
                <div class="site-badges">
                    ${statusBadge}
                    <span class="badge badge-version">WP ${esc(s.version)}</span>
                </div>
                <span class="chevron" id="chev-${i}">▶</span>
            </div>
            <div class="site-card-body" id="body-${i}">
                <div class="detail-grid">
                    <div class="detail-item"><label>Domain</label><div class="val">${esc(s.domain)}</div></div>
                    <div class="detail-item"><label>Directory</label><div class="val">${esc(s.subdir || '(root)')}</div></div>
                    <div class="detail-item"><label>Database</label><div class="val">${esc(s.db_name)}</div></div>
                    <div class="detail-item"><label>Table Prefix</label><div class="val">${esc(s.db_prefix)}</div></div>
                    <div class="detail-item" style="grid-column:span 2"><label>Files Path</label><div class="val">${esc(pathDisplay)}</div></div>
                </div>
                <div class="action-row">
                    <button class="btn btn-primary btn-sm" onclick="doMagicLogin(${JSON.stringify(s.path)})">⚡ Magic Login</button>
                    <button class="btn btn-danger btn-sm" onclick="openDeleteModal(${JSON.stringify(s.path)}, ${JSON.stringify(s.db_name)})">🗑 Delete</button>
                </div>
            </div>
        </div>`;
    }).join('');
}

function esc(s) {
    return String(s || '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

/* ─── Filter ─── */
function filterSites() {
    const q = document.getElementById('search-input').value.toLowerCase();
    renderSites(allSites.filter(s =>
        (s.blogname + s.siteurl + s.path + s.db_name).toLowerCase().includes(q)
    ));
}

/* ─── Fetch sites list ─── */
async function fetchSites(scan = false) {
    document.getElementById('sites-container').innerHTML =
        `<div class="empty-state"><div class="icon">⏳</div><strong>${scan ? 'Scanning directories…' : 'Loading…'}</strong></div>`;
    try {
        const r = await fetch(apiUrl(scan ? 'scan' : 'list'));
        const d = await r.json();
        if (d.success) { allSites = d.sites; renderSites(allSites); }
        else { toast(d.error || 'Failed to load sites.', 'error'); }
    } catch(e) {
        toast('Cannot connect to backend.', 'error');
        document.getElementById('sites-container').innerHTML =
            `<div class="empty-state"><div class="icon">⚠️</div><strong>Connection failed</strong></div>`;
    }
}

async function triggerScan() {
    const btn = document.getElementById('btn-scan');
    btn.disabled = true; btn.textContent = '⏳ Scanning…';
    await fetchSites(true);
    btn.disabled = false; btn.textContent = '🔄 Scan Hosting';
    toast('Scan complete.', 'success');
}

/* ─── Domain list for install modal ─── */
async function loadDomains() {
    const sel = document.getElementById('inst-domain');
    sel.innerHTML = '<option value="" disabled selected>Loading…</option>';
    try {
        const r = await fetch(apiUrl('get_domains'));
        const d = await r.json();
        sel.innerHTML = '<option value="" disabled selected>Select domain / subdomain…</option>';
        if (d.success && d.domains.length) {
            for (const dom of d.domains) {
                const o = document.createElement('option');
                o.value = dom;
                o.textContent = dom;
                sel.appendChild(o);
                // Fetch subdomains
                const subs = await fetchSubdomains(dom);
                subs.forEach(sub => {
                    const s = document.createElement('option');
                    const full = sub + '.' + dom;
                    s.value = full; s.textContent = '   └─ ' + full;
                    sel.appendChild(s);
                });
            }
        } else {
            sel.innerHTML = '<option value="" disabled>No domains found</option>';
        }
    } catch(e) { toast('Failed to load domains.', 'error'); }
}

async function fetchSubdomains(domain) {
    try {
        const r = await fetch('/CMD_API_SUBDOMAINS?json=yes&domain=' + encodeURIComponent(domain));
        const ct = r.headers.get('content-type') || '';
        if (ct.includes('application/json')) {
            const d = await r.json();
            if (Array.isArray(d)) return d;
            if (d && d.list) return Array.isArray(d.list) ? d.list : Object.values(d.list);
        }
        const text = await r.text();
        const params = new URLSearchParams(text);
        const list = [];
        for (const [k, v] of params.entries()) {
            if (k === 'list[]' || k.startsWith('list[')) list.push(v);
        }
        return list;
    } catch { return []; }
}

/* ─── Install modal ─── */
function openInstallModal() {
    openModal('modal-install');
    loadDomains();
    genPass('inst-dbpass');
    genPass('inst-adminpass');
}

/* ─── DirectAdmin DB API ─── */
async function createDB(name, user, pass) {
    const p = new URLSearchParams({action:'create', name, user, passwd:pass, passwd2:pass});
    const r = await fetch('/CMD_API_DATABASES', {method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body:p.toString()});
    const text = await r.text();
    const q = new URLSearchParams(text);
    if (q.get('error') === '1') throw new Error(decodeURIComponent(q.get('details') || 'DB creation failed'));
    return {
        db_name: q.get('db') || (DA_USER + '_' + name),
        db_user: q.get('user') || (DA_USER + '_' + user)
    };
}

async function executeInstall(e) {
    e.preventDefault();
    const btn = document.getElementById('btn-install-submit');
    btn.disabled = true; btn.textContent = 'Creating database…';
    try {
        const dbSuffix   = document.getElementById('inst-dbname').value;
        const userSuffix = document.getElementById('inst-dbuser').value;
        const dbPass     = document.getElementById('inst-dbpass').value;

        toast('1/3 Creating database via DirectAdmin…');
        const db = await createDB(dbSuffix, userSuffix, dbPass);

        toast('2/3 Downloading & installing WordPress…');
        btn.textContent = 'Installing…';

        const p = new URLSearchParams({
            action:      'install',
            domain:      document.getElementById('inst-domain').value,
            subdir:      document.getElementById('inst-subdir').value,
            db_name:     db.db_name,
            db_user:     db.db_user,
            db_pass:     dbPass,
            site_title:  document.getElementById('inst-title').value,
            admin_user:  document.getElementById('inst-adminuser').value,
            admin_pass:  document.getElementById('inst-adminpass').value,
            admin_email: document.getElementById('inst-adminemail').value,
            protocol:    document.getElementById('inst-protocol').value,
        });

        const r = await fetch(apiUrl(), {method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body:p.toString()});
        const d = await r.json();
        if (d.success) {
            toast('3/3 WordPress installed successfully!', 'success');
            closeModal('modal-install');
            fetchSites(false);
        } else {
            toast(d.error || 'Installation failed.', 'error');
        }
    } catch(err) {
        toast(err.message || 'Error during installation.', 'error');
    } finally {
        btn.disabled = false; btn.textContent = 'Install WordPress';
    }
}

/* ─── Magic Login ─── */
async function doMagicLogin(path) {
    toast('Generating magic login link…');
    try {
        const p = new URLSearchParams({action:'magic_login', path});
        const r = await fetch(apiUrl(), {method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body:p.toString()});
        const d = await r.json();
        if (d.success && d.login_url) { window.open(d.login_url, '_blank'); toast('Redirecting…', 'success'); }
        else toast(d.error || 'Magic login failed.', 'error');
    } catch { toast('Connection error.', 'error'); }
}

/* ─── Delete ─── */
function openDeleteModal(path, db) {
    deletePath = path; deleteDb = db;
    document.getElementById('del-path').textContent = path;
    document.getElementById('del-db-name').textContent = db || '(none)';
    const chk = document.getElementById('del-db-check');
    chk.checked = !!db; chk.disabled = !db;
    openModal('modal-delete');
}

async function executeDelete() {
    const btn = document.getElementById('btn-delete-confirm');
    btn.disabled = true; btn.textContent = 'Deleting…';
    try {
        if (document.getElementById('del-db-check').checked && deleteDb) {
            toast('Removing database…');
            const p = new URLSearchParams({action:'delete', select0: deleteDb});
            await fetch('/CMD_API_DATABASES', {method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body:p.toString()});
        }
        const p = new URLSearchParams({action:'delete', path: deletePath});
        const r = await fetch(apiUrl(), {method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body:p.toString()});
        const d = await r.json();
        if (d.success) {
            toast('Deleted successfully.', 'success');
            closeModal('modal-delete');
            fetchSites(false);
        } else { toast(d.error || 'Deletion failed.', 'error'); }
    } catch(err) { toast(err.message || 'Error.', 'error'); }
    finally { btn.disabled = false; btn.textContent = 'Delete'; }
}

<?php if ($isAdmin): ?>
/* ─── Plugin update ─── */
function openUpdateModal() {
    const term = document.getElementById('update-terminal');
    term.innerHTML = '';
    document.getElementById('btn-update-done').disabled = true;
    openModal('modal-update');
    runPluginUpdate();
}

async function runPluginUpdate() {
    const term = document.getElementById('update-terminal');
    const log = (msg, cls='') => {
        const ln = document.createElement('div');
        if (cls) ln.className = cls;
        ln.textContent = '[' + new Date().toLocaleTimeString() + '] ' + msg;
        term.appendChild(ln);
        term.scrollTop = term.scrollHeight;
    };
    log('Connecting to GitHub…');
    try {
        const p = new URLSearchParams({action:'update_plugin'});
        const r = await fetch(apiUrl(), {method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body:p.toString()});
        const d = await r.json();
        if (d.success) { log(d.message, 'ok'); log('Done. Reload the page.', 'ok'); }
        else { log('Error: ' + d.error, 'err'); }
    } catch(e) { log('Connection error: ' + e.message, 'err'); }
    finally { document.getElementById('btn-update-done').disabled = false; }
}
<?php endif; ?>

/* ─── Init ─── */
window.addEventListener('DOMContentLoaded', () => fetchSites(false));
</script>
</body>
</html>
