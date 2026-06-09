<?php
/**
 * Ultimate DirectAdmin WordPress Manager
 * GUI — Dark theme, wide layout, with site screenshots
 */
$username = getenv('USERNAME') ?: getenv('USER') ?: 'user';
$isAdmin  = is_admin_user();

// Fetch server IP and hostname
$hostname = gethostname();
$server_ip = gethostbyname($hostname);
if ($server_ip === '127.0.0.1' || $server_ip === '127.0.1.1' || !filter_var($server_ip, FILTER_VALIDATE_IP)) {
    $server_ip = $_SERVER['SERVER_ADDR'] ?? $_SERVER['LOCAL_ADDR'] ?? '';
    if (empty($server_ip) && strtoupper(substr(PHP_OS, 0, 3)) !== 'WIN') {
        $server_ip = trim(shell_exec("hostname -I | awk '{print $1}'"));
    }
}
if (empty($server_ip)) {
    $server_ip = $_SERVER['HTTP_HOST'] ?? 'Unknown IP';
}
// Strip port from HTTP_HOST if it's there
if (strpos($server_ip, ':') !== false) {
    $parts = explode(':', $server_ip);
    $server_ip = $parts[0];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Ultimate WordPress Manager</title>
<style>
/* ── Reset ── */
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}

/* ── Tokens ── */
:root {
    --bg:       #0d1117;
    --bg2:      #161b22;
    --bg3:      #1c2128;
    --border:   #30363d;
    --border2:  #21262d;
    --text:     #e6edf3;
    --text2:    #8b949e;
    --text3:    #6e7681;
    --blue:     #2f81f7;
    --blue-dim: #1f6feb;
    --green:    #3fb950;
    --yellow:   #d29922;
    --red:      #f85149;
    --purple:   #bc8cff;
    --accent:   #238636;
}

body {
    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Arial, sans-serif;
    font-size: 13px;
    background: var(--bg);
    color: var(--text);
    line-height: 1.5;
    min-height: 100vh;
    width: 100%;
}

div.plugin-legacy-host {
    padding:0px !important;
}
div#iframe-container{
    width: 100%;
}
/* ── Top bar ── */
.topbar {
    background: var(--bg2);
    border-bottom: 1px solid var(--border);
    padding: 0 24px;
    display: flex;
    align-items: center;
    gap: 16px;
    height: 40px;
    position: sticky;
    top: 0;
    z-index: 100;
}
.topbar .logo {
    color: var(--text);
    font-weight: 700;
    font-size: 14px;
    display: flex;
    align-items: center;
    gap: 8px;
}
.topbar .logo-icon {
    width: 24px; height: 24px;
    background: var(--blue);
    border-radius: 4px;
    display: flex; align-items: center; justify-content: center;
    font-size: 13px; font-weight: 900; color: #fff;
}
.topbar .user { margin-left: auto; color: var(--text2); font-size: 12px; display: flex; align-items: center; gap: 8px; }

/* ── Toolbar ── */
.toolbar {
    background: var(--bg2);
    border-bottom: 1px solid var(--border);
    padding: 10px 24px;
    display: flex;
    align-items: center;
    gap: 8px;
    flex-wrap: wrap;
}
.toolbar-sep { width: 1px; height: 20px; background: var(--border); margin: 0 4px; }
.count-label { margin-left: auto; color: var(--text3); font-size: 12px; }

/* ── Buttons ── */
.btn {
    display: inline-flex; align-items: center; gap: 5px;
    padding: 6px 14px;
    font-size: 12px; font-family: inherit;
    border-radius: 6px; cursor: pointer;
    text-decoration: none;
    border: 1px solid transparent;
    line-height: 1.5; white-space: nowrap;
    transition: all .15s;
}
.btn:disabled { opacity: .45; cursor: not-allowed; }

.btn-primary   { background: var(--accent); border-color: #2ea043; color: #fff; }
.btn-primary:hover:not(:disabled) { background: #2ea043; }

.btn-secondary { background: var(--bg3); border-color: var(--border); color: var(--text); }
.btn-secondary:hover:not(:disabled) { background: var(--bg2); border-color: var(--text3); }

.btn-blue   { background: var(--blue-dim); border-color: var(--blue); color: #fff; }
.btn-blue:hover:not(:disabled) { background: var(--blue); }

.btn-danger { background: transparent; border-color: var(--red); color: var(--red); }
.btn-danger:hover:not(:disabled) { background: var(--red); color: #fff; }

.btn-sm { padding: 4px 10px; font-size: 11px; border-radius: 4px; }

/* ── Content ── */
.content { padding: 20px 24px; max-width: 100%; width: 100%; }

/* ── Search ── */
.toolbar-search {
    padding: 6px 12px;
    background: var(--bg);
    border: 1px solid var(--border);
    border-radius: 6px;
    color: var(--text);
    font-size: 12px;
    font-family: inherit;
    width: 280px;
}
.toolbar-search:focus { outline: none; border-color: var(--blue); }

/* ── Site cards ── */
.site-card {
    background: var(--bg2);
    border: 1px solid var(--border);
    border-radius: 8px;
    margin-bottom: 10px;
    overflow: hidden;
    transition: border-color .15s;
}
.site-card:hover { border-color: var(--border2); }

/* Card header row */
.card-header {
    display: flex;
    align-items: center;
    padding: 12px 16px;
    gap: 14px;
    cursor: pointer;
    user-select: none;
    transition: background .12s;
}
.card-header:hover { background: var(--bg3); }

/* Screenshot thumbnail */
.site-thumb {
    width: 96px;
    height: 60px;
    border-radius: 5px;
    overflow: hidden;
    background: var(--bg3);
    border: 1px solid var(--border);
    flex-shrink: 0;
    position: relative;
}
.site-thumb img {
    width: 100%; height: 100%;
    object-fit: cover;
    display: block;
    transition: opacity .3s;
}
.site-thumb .thumb-loader {
    position: absolute; inset: 0;
    display: flex; align-items: center; justify-content: center;
    font-size: 18px; color: var(--text3);
}
.site-thumb .thumb-loader.hidden { display: none; }

/* Site info */
.site-info { flex: 1; min-width: 0; }
.site-name {
    font-size: 14px; font-weight: 600; color: var(--text);
    white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
    margin-bottom: 2px;
}
.site-url { font-size: 11px; color: var(--text2); }
.site-url a { color: var(--blue); text-decoration: none; }
.site-url a:hover { text-decoration: underline; }

/* Badges row */
.badges { display: flex; gap: 6px; align-items: center; flex-shrink: 0; flex-wrap: wrap; }
.badge {
    display: inline-flex; align-items: center; gap: 3px;
    padding: 3px 9px;
    border-radius: 12px;
    font-size: 11px; font-weight: 500;
    border: 1px solid transparent;
}
.badge-green  { background: #0d1f12; color: var(--green); border-color: #1a4223; }
.badge-red    { background: #1f0d0d; color: var(--red);   border-color: #421a1a; }
.badge-blue   { background: #0d1b2f; color: var(--blue);  border-color: #1a3352; }
.badge-yellow { background: #1f1a0d; color: var(--yellow);border-color: #42380a; }
.badge-gray   { background: #1c2128; color: var(--text2); border-color: var(--border); }

/* Card actions column */
.card-actions { display: flex; gap: 6px; align-items: center; flex-shrink: 0; }

/* Chevron */
.chevron { color: var(--text3); font-size: 10px; transition: transform .2s; margin-left: 4px; }
.chevron.open { transform: rotate(90deg); }

/* ── Expanded card body ── */
.card-body { display: none; border-top: 1px solid var(--border); }
.card-body.open { display: block; }

/* Screenshot full-width strip */
.card-screenshot-bar {
    width: 100%;
    height: 220px;
    background: var(--bg3);
    overflow: hidden;
    position: relative;
    border-bottom: 1px solid var(--border);
}
.card-screenshot-bar img {
    width: 100%; height: 100%;
    object-fit: cover;
    object-position: top;
}
.card-screenshot-bar .shot-overlay {
    position: absolute; inset: 0;
    background: linear-gradient(to bottom, transparent 60%, var(--bg2));
    pointer-events: none;
}
.card-screenshot-bar .shot-label {
    position: absolute; bottom: 10px; left: 14px;
    font-size: 11px; color: var(--text3);
}
.card-screenshot-bar .shot-refresh {
    position: absolute; bottom: 8px; right: 12px;
}

.card-details {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
    gap: 12px;
    padding: 0;
    margin-top: 12px;
}
.detail-item label {
    display: block;
    font-size: 10px; font-weight: 700;
    text-transform: uppercase;
    letter-spacing: .6px;
    color: var(--text3);
    margin-bottom: 3px;
}
.detail-item .val {
    font-size: 12px; color: var(--text2);
    font-family: ui-monospace, "SFMono-Regular", monospace;
    word-break: break-all;
}

.card-action-row {
    display: flex; gap: 8px; flex-wrap: wrap;
    padding: 12px 16px;
    border-top: 1px solid var(--border2);
    background: var(--bg3);
}

/* ── Empty state ── */
.empty-state {
    text-align: center; padding: 60px 20px; color: var(--text3);
}
.empty-state .icon { font-size: 40px; margin-bottom: 12px; }
.empty-state strong { color: var(--text2); display: block; margin-bottom: 6px; }

/* ── Modals ── */
.modal-overlay {
    display: none; position: fixed; inset: 0;
    background: rgba(0,0,0,.7);
    z-index: 9000;
    align-items: center; justify-content: center;
}
.modal-overlay.open { display: flex; }
.modal {
    background: var(--bg2);
    border: 1px solid var(--border);
    border-radius: 10px;
    box-shadow: 0 16px 64px rgba(0,0,0,.5);
    width: 100%; max-width: 750px;
    max-height: 92vh; overflow-y: auto;
}
.modal-head {
    padding: 16px 20px;
    border-bottom: 1px solid var(--border);
    display: flex; justify-content: space-between; align-items: center;
}
.modal-head h3 { font-size: 15px; font-weight: 700; color: var(--text); }
.modal-close { background: none; border: none; font-size: 18px; cursor: pointer; color: var(--text3); line-height: 1; }
.modal-close:hover { color: var(--red); }
.modal-body { padding: 20px; }
.modal-footer {
    padding: 14px 20px;
    border-top: 1px solid var(--border);
    display: flex; justify-content: flex-end; gap: 8px;
    background: var(--bg3);
}

/* ── Form ── */
.form-section { margin-bottom: 20px; }
.form-section-title {
    font-size: 11px; font-weight: 700;
    text-transform: uppercase; letter-spacing: .6px;
    color: var(--text3);
    border-bottom: 1px solid var(--border2);
    padding-bottom: 6px; margin-bottom: 12px;
}
.form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
.form-group { margin-bottom: 12px; }
.form-group label {
    display: block; font-size: 12px; font-weight: 600;
    color: var(--text2); margin-bottom: 4px;
}
.form-control {
    width: 100%; padding: 6px 10px;
    background: var(--bg);
    border: 1px solid var(--border);
    border-radius: 6px;
    font-size: 12px; font-family: inherit; color: var(--text);
}
.form-control:focus { outline: none; border-color: var(--blue); box-shadow: 0 0 0 3px rgba(47,129,247,.15); }
.form-control option { background: var(--bg2); }

.input-group { display: flex; gap: 0; }
.input-prefix {
    background: var(--bg3); border: 1px solid var(--border);
    border-right: none; border-radius: 6px 0 0 6px;
    padding: 6px 10px; font-size: 11px; color: var(--text3); white-space: nowrap;
}
.input-group .form-control { border-radius: 0 6px 6px 0; }
.input-group-btn { display: flex; gap: 4px; align-items: stretch; }
.input-group-btn .form-control { border-radius: 6px 0 0 6px; }
.input-group-btn .btn { border-radius: 0 6px 6px 0; padding: 6px 10px; }

/* ── Notices ── */
.notice {
    padding: 10px 14px; border-radius: 6px;
    font-size: 12px; margin-bottom: 12px;
}
.notice-error { background: #1f0d0d; border: 1px solid #421a1a; color: #f97171; }
.notice-info  { background: #0d1b2f; border: 1px solid #1a3352; color: #79b8ff; }

/* ── Toast ── */
#toast-area {
    position: fixed; top: 20px; right: 20px;
    z-index: 9999; display: flex; flex-direction: column; gap: 6px;
}
.toast {
    background: var(--bg2); border: 1px solid var(--border);
    color: var(--text); padding: 10px 16px;
    border-radius: 6px; font-size: 12px;
    box-shadow: 0 4px 16px rgba(0,0,0,.4);
    animation: fadeUp .2s ease; max-width: 340px;
    border-left-width: 3px;
}
.toast.ok  { border-left-color: var(--green); }
.toast.err { border-left-color: var(--red); }
@keyframes fadeUp { from{opacity:0;transform:translateY(-8px)}to{opacity:1;transform:none} }

/* ── Terminal ── */
.terminal {
    background: #0d1117;
    border: 1px solid var(--border);
    border-radius: 6px;
    color: var(--text2);
    font-family: ui-monospace,"SFMono-Regular",monospace;
    font-size: 12px;
    padding: 12px;
    height: 200px;
    overflow-y: auto;
    margin-top: 10px;
}
.terminal .ok  { color: var(--green); }
.terminal .err { color: var(--red); }

/* ── Tabs ── */
.card-tabs {
    display: flex;
    border-bottom: 1px solid var(--border);
    background: var(--bg3);
    padding: 0 16px;
    gap: 8px;
}
.tab-btn {
    background: transparent;
    border: none;
    border-bottom: 2px solid transparent;
    color: var(--text2);
    padding: 12px 16px;
    font-size: 12px;
    font-weight: 600;
    cursor: pointer;
    transition: all .15s;
    outline: none;
    display: flex;
    align-items: center;
    gap: 6px;
    margin-bottom: -1px;
}
.tab-btn:hover {
    color: var(--text);
}
.tab-btn.active {
    color: var(--blue);
    border-bottom-color: var(--blue);
}
.card-tab-content {
    display: none;
    padding: 16px;
}
.card-tab-content.active {
    display: block;
}
.tab-grid-details {
    display: grid;
    grid-template-columns: 1.2fr 1fr;
    gap: 24px;
}
.card-sec-title {
    font-size: 13px;
    font-weight: 700;
    color: var(--text);
    border-bottom: 1px solid var(--border);
    padding-bottom: 8px;
    margin-bottom: 0px;
    display: flex;
    align-items: center;
    justify-content: space-between;
}
.plugin-list {
    display: flex;
    flex-direction: column;
    gap: 8px;
    padding-right: 4px;
    margin-top: 12px;
}
.plugin-item {
    background: var(--bg);
    border: 1px solid var(--border);
    border-radius: 6px;
    padding: 8px 12px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
}
.plugin-info {
    min-width: 0;
    flex: 1;
}
.plugin-name {
    font-size: 12px;
    font-weight: 600;
    color: var(--text);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.plugin-desc {
    font-size: 11px;
    color: var(--text2);
    margin-top: 2px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.plugin-meta {
    font-size: 11px;
    color: var(--text3);
    margin-top: 2px;
}
.plugin-toggle {
    flex-shrink: 0;
    display: flex;
    align-items: center;
    gap: 6px;
}
.lock-section {
    margin-top: 16px;
    border-top: 1px dashed var(--border);
    padding-top: 16px;
    display: flex;
    align-items: center;
    justify-content: space-between;
}
.lock-status-label {
    display: flex;
    align-items: center;
    gap: 6px;
    font-weight: 600;
    font-size: 12px;
}
/* Toggle Switch */
.switch {
    position: relative;
    display: inline-block;
    width: 38px;
    height: 20px;
}
.switch input { 
    opacity: 0;
    width: 0;
    height: 0;
}
.slider {
    position: absolute;
    cursor: pointer;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background-color: var(--border);
    transition: .2s;
    border-radius: 20px;
}
.slider:before {
    position: absolute;
    content: "";
    height: 14px;
    width: 14px;
    left: 3px;
    bottom: 3px;
    background-color: white;
    transition: .2s;
    border-radius: 50%;
}
input:checked + .slider {
    background-color: var(--green);
}
input:focus + .slider {
    box-shadow: 0 0 1px var(--green);
}
input:checked + .slider:before {
    transform: translateX(18px);
}
input:disabled + .slider {
    opacity: 0.5;
    cursor: not-allowed;
}
</style>
</head>
<body>

<!-- Top bar -->
<div class="topbar">
    <div class="logo">
        <div class="logo-icon">W</div>
        Ultimate WordPress Manager
    </div>
    <span style="color:var(--text3);font-size:11px;display:flex;align-items:center;gap:12px;">
        <span><?php echo htmlspecialchars($server_ip); ?></span> | <span><?php echo htmlspecialchars($hostname); ?></span>
        <a href="/" class="btn btn-sm btn-secondary" style="font-size:10px;padding:2px 8px;border-radius:4px;height:22px;display:inline-flex;align-items:center;gap:4px;border-color:var(--border);">
            ← Back to DirectAdmin
        </a>
    </span>
    <div class="user">
        <span>👤 <?php echo htmlspecialchars($username); ?></span>
        <?php if ($isAdmin): ?>
        <span class="badge badge-yellow" style="font-size:10px;">Admin</span>
        <?php endif; ?>
    </div>
</div>

<!-- Toolbar -->
<div class="toolbar">
    <button class="btn btn-primary" onclick="openInstallModal()">+ Install WordPress</button>
    <button class="btn btn-secondary" id="btn-scan" onclick="triggerScan()">⟳ Scan Hosting</button>
    <?php if ($isAdmin): ?>
    <div class="toolbar-sep"></div>
    <button class="btn btn-secondary" onclick="openUpdateModal()">↑ Update Plugin</button>
    <?php endif; ?>
    <div class="toolbar-sep"></div>
    <input type="text" id="search-input" class="toolbar-search" placeholder="🔍  Search by name, URL, path…" oninput="filterSites()">
    <span class="count-label" id="count-label">Loading…</span>
</div>

<!-- Content -->
<div class="content">
    <div id="sites-container">
        <div class="empty-state"><div class="icon">⏳</div><strong>Loading installations…</strong></div>
    </div>
</div>

<!-- ═══ MODAL: Install WordPress ═══ -->
<div class="modal-overlay" id="modal-install">
<div class="modal">
    <div class="modal-head">
        <h3>+ Install WordPress</h3>
        <button class="modal-close" onclick="closeModal('modal-install')">✕</button>
    </div>
    <form onsubmit="executeInstall(event)">
    <div class="modal-body">

        <div class="form-section">
            <div class="form-section-title">Website Location</div>
            <div style="display: grid; grid-template-columns: 1.2fr 3fr 2fr; gap: 12px;">
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
                <div class="form-group">
                    <label>Directory <span style="font-weight:400;color:var(--text3)">(optional)</span></label>
                    <input type="text" id="inst-subdir" class="form-control" placeholder="e.g. blog">
                </div>
            </div>
        </div>

        <div class="form-section">
            <div class="form-section-title">Nguồn Cài Đặt (Source)</div>
            <div style="display: flex; gap: 20px; margin-bottom: 12px;">
                <label style="display: flex; align-items: center; gap: 6px; cursor: pointer; color: var(--text2);">
                    <input type="radio" name="inst-source" value="fresh" checked onchange="toggleInstallSource('fresh')" style="width:14px; height:14px; accent-color: var(--blue);">
                    <span>Cài đặt mới (Fresh WP)</span>
                </label>
                <label style="display: flex; align-items: center; gap: 6px; cursor: pointer; color: var(--text2);">
                    <input type="radio" name="inst-source" value="zip" onchange="toggleInstallSource('zip')" style="width:14px; height:14px; accent-color: var(--blue);">
                    <span>Cài đặt từ tệp ZIP (From ZIP)</span>
                </label>
            </div>
            
            <div id="inst-zip-wrapper" style="display: none;" class="form-group">
                <label>Chọn tệp ZIP source code từ hosting <span style="font-weight:400;color:var(--red)">*</span></label>
                <div class="input-group-btn">
                    <select id="inst-zip-path" class="form-control">
                        <option value="" disabled selected>Đang quét tệp .zip...</option>
                    </select>
                    <button type="button" class="btn btn-secondary" onclick="loadUserZipFiles()">⟳ Quét lại</button>
                </div>
                <span style="font-size:11px;color:var(--text3)">Hãy upload tệp ZIP sao lưu của bạn lên hosting (qua FTP/File Manager) rồi chọn từ danh sách trên. Tệp ZIP phải chứa mã nguồn WordPress và file DB (.sql.gz, .sql, .gz).</span>
            </div>
        </div>

        <div class="form-section">
            <div class="form-section-title">Database</div>
            <div style="display: grid; grid-template-columns: 1fr 1fr 1.2fr; gap: 12px;">
                <div class="form-group">
                    <label>DB Name</label>
                    <div class="input-group">
                        <span class="input-prefix"><?php echo htmlspecialchars($username); ?>_</span>
                        <input type="text" id="inst-dbname" class="form-control" placeholder="wp1" required maxlength="16">
                    </div>
                </div>
                <div class="form-group">
                    <label>DB User</label>
                    <div class="input-group">
                        <span class="input-prefix"><?php echo htmlspecialchars($username); ?>_</span>
                        <input type="text" id="inst-dbuser" class="form-control" placeholder="wpuser" required maxlength="16">
                    </div>
                </div>
                <div class="form-group">
                    <label>DB Password</label>
                    <div class="input-group-btn">
                        <input type="text" id="inst-dbpass" class="form-control" required placeholder="Password">
                        <button type="button" class="btn btn-secondary" onclick="genPass('inst-dbpass')">Gen</button>
                    </div>
                </div>
            </div>
        </div>

        <div class="form-section" id="inst-admin-section">
            <div class="form-section-title">WordPress Admin</div>
            <div class="form-group">
                <label>Site Title</label>
                <input type="text" id="inst-title" class="form-control" required placeholder="My WordPress Site">
            </div>
            <div style="display: grid; grid-template-columns: 1fr 1.2fr 1.2fr; gap: 12px; margin-top: 12px;">
                <div class="form-group">
                    <label>Admin Username</label>
                    <input type="text" id="inst-adminuser" class="form-control" required placeholder="admin">
                </div>
                <div class="form-group">
                    <label>Admin Password</label>
                    <div class="input-group-btn">
                        <input type="text" id="inst-adminpass" class="form-control" required placeholder="Password">
                        <button type="button" class="btn btn-secondary" onclick="genPass('inst-adminpass')">Gen</button>
                    </div>
                </div>
                <div class="form-group">
                    <label>Admin Email</label>
                    <input type="email" id="inst-adminemail" class="form-control" required value="admin@<?php echo htmlspecialchars($username); ?>.com">
                </div>
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

<!-- ═══ MODAL: Delete ═══ -->
<div class="modal-overlay" id="modal-delete">
<div class="modal" style="max-width:460px;">
    <div class="modal-head">
        <h3>Delete WordPress</h3>
        <button class="modal-close" onclick="closeModal('modal-delete')">✕</button>
    </div>
    <div class="modal-body">
        <div class="notice notice-error">⚠ This action permanently deletes all files and cannot be undone.</div>
        <p style="margin-bottom:10px;color:var(--text2);">Installation path:</p>
        <code id="del-path" style="display:block;padding:8px 12px;background:var(--bg);border:1px solid var(--border);border-radius:6px;font-size:11px;word-break:break-all;color:var(--text2);"></code>
        <div style="margin-top:16px;">
            <label style="display:flex;align-items:center;gap:8px;font-size:12px;cursor:pointer;color:var(--text2);">
                <input type="checkbox" id="del-db-check" checked style="width:14px;height:14px;accent-color:var(--red)">
                Also delete database: <strong id="del-db-name" style="color:var(--text);font-family:monospace;"></strong>
            </label>
        </div>
    </div>
    <div class="modal-footer">
        <button class="btn btn-secondary" onclick="closeModal('modal-delete')">Cancel</button>
        <button class="btn btn-danger" id="btn-delete-confirm" onclick="executeDelete()">Delete Permanently</button>
    </div>
</div>
</div>

<?php if ($isAdmin): ?>
<!-- ═══ MODAL: Update Plugin ═══ -->
<div class="modal-overlay" id="modal-update">
<div class="modal" style="max-width:520px;">
    <div class="modal-head">
        <h3>↑ Update Plugin from GitHub</h3>
        <button class="modal-close" onclick="closeModal('modal-update')">✕</button>
    </div>
    <div class="modal-body">
        <p style="font-size:12px;color:var(--text2);margin-bottom:6px;">Downloads the latest version from GitHub and replaces current plugin files.</p>
        <div class="terminal" id="update-terminal"></div>
    </div>
    <div class="modal-footer">
        <button class="btn btn-secondary" id="btn-update-done" disabled onclick="closeModal('modal-update')">Close</button>
    </div>
</div>
</div>
<?php endif; ?>

<div id="toast-area"></div>

<script>
const DA_USER = '<?php echo $username; ?>';
let allSites = [];
let deletePath = '', deleteDb = '';

/* ─── Screenshot service (free, no key) ─── */
function thumbUrl(siteurl) {
    return 'https://image.thum.io/get/width/1024/crop/768/noanimate/' + siteurl;
}

/* ─── API helper ─── */
const apiUrl = (action='') => {
    let b = window.location.pathname.split('?')[0];
    if (b.endsWith('.html') || b.endsWith('.raw')) b = b.substring(0, b.lastIndexOf('/')+1);
    else if (!b.endsWith('/')) b += '/';
    return b + 'index.raw' + (action ? '?action='+action : '');
};

/* ─── Toast ─── */
function toast(msg, type='info') {
    const el = document.createElement('div');
    el.className = 'toast' + (type==='success'?' ok':type==='error'?' err':'');
    el.textContent = msg;
    
    let targetDoc = document;
    let targetArea = document.getElementById('toast-area');
    
    try {
        if (window.parent && window.parent.document) {
            let parentToastArea = window.parent.document.getElementById('plugin-toast-area');
            if (!parentToastArea) {
                parentToastArea = window.parent.document.createElement('div');
                parentToastArea.id = 'plugin-toast-area';
                parentToastArea.style.cssText = 'position: fixed; top: 100px; right: 20px; z-index: 99999; display: flex; flex-direction: column; gap: 6px; pointer-events: none;';
                window.parent.document.body.appendChild(parentToastArea);
            }
            targetDoc = window.parent.document;
            targetArea = parentToastArea;
        }
    } catch (e) {
        targetArea = document.getElementById('toast-area');
    }
    
    try {
        if (targetDoc !== document && !targetDoc.getElementById('plugin-toast-styles')) {
            const style = targetDoc.createElement('style');
            style.id = 'plugin-toast-styles';
            style.textContent = `
                #plugin-toast-area .toast {
                    background: #161b22; border: 1px solid #30363d;
                    color: #e6edf3; padding: 10px 16px;
                    border-radius: 6px; font-size: 12px;
                    box-shadow: 0 4px 16px rgba(0,0,0,.4);
                    animation: fadeUpParent .2s ease; max-width: 340px;
                    border-left: 3px solid #6e7681;
                    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Arial, sans-serif;
                    pointer-events: auto;
                    margin-bottom: 6px;
                }
                #plugin-toast-area .toast.ok { border-left-color: #3fb950; }
                #plugin-toast-area .toast.err { border-left-color: #f85149; }
                @keyframes fadeUpParent { from{opacity:0;transform:translateY(-8px)}to{opacity:1;transform:none} }
            `;
            targetDoc.head.appendChild(style);
        }
    } catch (e) {}

    targetArea.appendChild(el);
    setTimeout(() => el.remove(), 4000);
}

/* ─── Modal ─── */
function openModal(id)  { document.getElementById(id).classList.add('open'); }
function closeModal(id) { document.getElementById(id).classList.remove('open'); }

/* ─── Password generator ─── */
function genPass(id) {
    const c = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%&*';
    let p = '';
    for (let i=0;i<14;i++) p += c[Math.floor(Math.random()*c.length)];
    document.getElementById(id).value = p;
}

/* ─── Esc helper ─── */
function esc(s) {
    return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

/* ─── Toggle card ─── */
function toggleCard(i) {
    const body = document.getElementById('cb-'+i);
    const chev = document.getElementById('cv-'+i);
    body.classList.toggle('open');
    chev.classList.toggle('open');
}

/* ─── Switch tab ─── */
function switchTab(siteIdx, tabName, event) {
    if (event) event.stopPropagation();
    
    const contents = document.querySelectorAll(`#cb-${siteIdx} .card-tab-content`);
    contents.forEach(el => el.classList.remove('active'));
    
    const buttons = document.querySelectorAll(`#cb-${siteIdx} .tab-btn`);
    buttons.forEach(el => el.classList.remove('active'));
    
    document.getElementById(`tab-content-${siteIdx}-${tabName}`).classList.add('active');
    if (event && event.currentTarget) {
        event.currentTarget.classList.add('active');
    }
    
    if (tabName === 'plugins') {
        const list = document.getElementById(`plugin-list-${siteIdx}`);
        if (list.innerHTML.includes('Expanding card will load') || list.innerHTML.includes('will load plugins')) {
            loadPlugins(siteIdx);
        }
    } else if (tabName === 'themes') {
        const list = document.getElementById(`theme-list-${siteIdx}`);
        if (list.innerHTML.includes('Expanding card will load') || list.innerHTML.includes('will load themes')) {
            loadThemes(siteIdx);
        }
    } else if (tabName === 'security') {
        const list = document.getElementById(`security-list-${siteIdx}`);
        if (list.innerHTML.includes('Clicking Security tab') || list.innerHTML.includes('will load status')) {
            loadSecurity(siteIdx);
        }
    }
}

/* ─── Plugin Manager ─── */
async function loadPlugins(i) {
    const s = allSites[i];
    const container = document.getElementById('plugin-list-' + i);
    container.innerHTML = '<div style="color:var(--text3);font-size:12px;padding:12px;text-align:center;">⏳ Loading plugins...</div>';
    
    try {
        const fd = new FormData();
        fd.append('path', s.path);
        const r = await fetch(apiUrl('list_plugins'), { method: 'POST', body: fd });
        const d = await r.json();
        
        if (d.success) {
            if (!d.plugins.length) {
                container.innerHTML = '<div style="color:var(--text3);font-size:12px;padding:12px;text-align:center;">No plugins installed.</div>';
                return;
            }
            container.innerHTML = d.plugins.map((p, idx) => {
                const actionText = p.active ? 'Deactivate' : 'Activate';
                const actionBtnClass = p.active ? 'btn-danger' : 'btn-primary';
                const statusBadge = p.active 
                    ? '<span style="color:var(--green);font-size:11px;font-weight:bold;">Active</span>' 
                    : '<span style="color:var(--text3);font-size:11px;">Inactive</span>';
                const latestVersion = p.latest_version || p.version || 'Unknown';
                const updateBadge = p.update_available
                    ? '<span style="color:var(--yellow);font-size:11px;font-weight:bold;">Update available</span>'
                    : '<span style="color:var(--text3);font-size:11px;">Up to date</span>';
                const updateDisabled = p.update_package_available ? '' : 'disabled';
                const updateTitle = p.update_package_available
                    ? 'Update this plugin'
                    : (p.update_available ? 'No update package available for this plugin' : 'Plugin is already up to date');
                
                return `
                <div class="plugin-item">
                    <div class="plugin-info">
                        <div class="plugin-name" title="${esc(p.name)}">${esc(p.name)}</div>
                        <div class="plugin-desc" title="${esc(p.description)}">${esc(p.description)}</div>
                        <div class="plugin-meta">Current: v${esc(p.version)} | Latest: v${esc(latestVersion)} | By ${esc(p.author)} | ${statusBadge} | ${updateBadge}</div>
                    </div>
                    <div class="plugin-toggle">
                        <button class="btn btn-sm btn-secondary" ${updateDisabled} title="${esc(updateTitle)}" id="btn-plug-up-${i}-${idx}" onclick="updatePlugin(${i}, ${idx}, '${esc(p.file)}')">
                            ↑ Update
                        </button>
                        <button class="btn btn-sm btn-secondary" id="btn-plug-reinst-${i}-${idx}" onclick="reinstallPlugin(${i}, ${idx}, '${esc(p.file)}')">
                            ⟳ Reinstall
                        </button>
                        <button class="btn btn-sm ${actionBtnClass}" id="btn-plug-${i}-${idx}" onclick="togglePlugin(${i}, ${idx}, '${esc(p.file)}', ${p.active})">
                            ${actionText}
                        </button>
                        <button class="btn btn-sm btn-danger" id="btn-plug-del-${i}-${idx}" onclick="deletePlugin(${i}, ${idx}, '${esc(p.file)}')">
                            🗑 Delete
                        </button>
                    </div>
                </div>`;
            }).join('');
        } else {
            container.innerHTML = `<div style="color:var(--red);font-size:12px;padding:12px;text-align:center;">Error: ${esc(d.error)}</div>`;
        }
    } catch (err) {
        container.innerHTML = '<div style="color:var(--red);font-size:12px;padding:12px;text-align:center;">Cannot load plugins.</div>';
    }
}

async function updatePlugin(siteIdx, plugIdx, file) {
    const s = allSites[siteIdx];
    if (s.locked) {
        toast('Website is under WordPress Lockdown. Please disable Lockdown before updating plugins.', 'error');
        return;
    }

    const btn = document.getElementById(`btn-plug-up-${siteIdx}-${plugIdx}`);
    const originalText = btn.textContent;
    btn.disabled = true;
    btn.textContent = 'Updating...';

    try {
        const fd = new FormData();
        fd.append('path', s.path);
        fd.append('plugin_file', file);

        const r = await fetch(apiUrl('update_wp_plugin'), { method: 'POST', body: fd });
        const d = await r.json();

        if (d.success) {
            toast(d.message || 'Plugin updated successfully!', 'success');
            loadPlugins(siteIdx);
        } else {
            toast(d.error || 'Plugin update failed.', 'error');
            btn.disabled = false;
            btn.textContent = originalText;
        }
    } catch (err) {
        toast('Connection error.', 'error');
        btn.disabled = false;
        btn.textContent = originalText;
    }
}

async function togglePlugin(siteIdx, plugIdx, file, isActive) {
    const s = allSites[siteIdx];
    const btn = document.getElementById(`btn-plug-${siteIdx}-${plugIdx}`);
    const originalText = btn.textContent;
    btn.disabled = true;
    btn.textContent = '⏳...';
    
    try {
        const nextStatus = isActive ? 'deactivate' : 'activate';
        const fd = new FormData();
        fd.append('path', s.path);
        fd.append('plugin_file', file);
        fd.append('status', nextStatus);
        
        const r = await fetch(apiUrl('toggle_plugin'), { method: 'POST', body: fd });
        const d = await r.json();
        
        if (d.success) {
            toast(`Plugin ${isActive ? 'deactivated' : 'activated'} successfully!`, 'success');
            loadPlugins(siteIdx);
        } else {
            toast(d.error || 'Failed to toggle plugin.', 'error');
            btn.disabled = false;
            btn.textContent = originalText;
        }
    } catch (err) {
        toast('Connection error.', 'error');
        btn.disabled = false;
        btn.textContent = originalText;
    }
}

async function reinstallPlugin(siteIdx, plugIdx, file) {
    const s = allSites[siteIdx];
    if (s.locked) {
        toast('Website is under WordPress Lockdown. Please disable Lockdown before reinstalling plugins.', 'error');
        return;
    }
    if (!confirm('Bạn có chắc chắn muốn cài đặt lại plugin này không? Thao tác này sẽ tải phiên bản gốc từ WordPress.org và ghi đè lên thư mục plugin hiện tại.')) {
        return;
    }
    
    const btn = document.getElementById(`btn-plug-reinst-${siteIdx}-${plugIdx}`);
    const originalText = btn.textContent;
    btn.disabled = true;
    btn.textContent = '⏳...';
    
    try {
        const fd = new FormData();
        fd.append('path', s.path);
        fd.append('plugin_file', file);
        
        const r = await fetch(apiUrl('reinstall_wp_plugin'), { method: 'POST', body: fd });
        const d = await r.json();
        
        if (d.success) {
            toast(d.message || 'Plugin reinstalled successfully!', 'success');
            loadPlugins(siteIdx);
        } else {
            toast(d.error || 'Failed to reinstall plugin.', 'error');
            btn.disabled = false;
            btn.textContent = originalText;
        }
    } catch (err) {
        toast('Connection error.', 'error');
        btn.disabled = false;
        btn.textContent = originalText;
    }
}

async function deletePlugin(siteIdx, plugIdx, file) {
    const s = allSites[siteIdx];
    if (s.locked) {
        toast('Website is under WordPress Lockdown. Please disable Lockdown before deleting plugins.', 'error');
        return;
    }
    if (!confirm('Bạn có chắc chắn muốn xóa plugin này không? Hành động này sẽ xóa toàn bộ thư mục plugin khỏi hệ thống.')) {
        return;
    }
    
    const btn = document.getElementById(`btn-plug-del-${siteIdx}-${plugIdx}`);
    const originalText = btn.textContent;
    btn.disabled = true;
    btn.textContent = '⏳...';
    
    try {
        const fd = new FormData();
        fd.append('path', s.path);
        fd.append('plugin_file', file);
        
        const r = await fetch(apiUrl('delete_wp_plugin'), { method: 'POST', body: fd });
        const d = await r.json();
        
        if (d.success) {
            toast(d.message || 'Plugin deleted successfully!', 'success');
            loadPlugins(siteIdx);
        } else {
            toast(d.error || 'Failed to delete plugin.', 'error');
            btn.disabled = false;
            btn.textContent = originalText;
        }
    } catch (err) {
        toast('Connection error.', 'error');
        btn.disabled = false;
        btn.textContent = originalText;
    }
}


/* ─── Theme Manager ─── */
async function loadThemes(i) {
    const s = allSites[i];
    const container = document.getElementById('theme-list-' + i);
    container.innerHTML = '<div style="color:var(--text3);font-size:12px;padding:12px;text-align:center;">⏳ Loading themes...</div>';
    
    try {
        const fd = new FormData();
        fd.append('path', s.path);
        const r = await fetch(apiUrl('list_themes'), { method: 'POST', body: fd });
        const d = await r.json();
        
        if (d.success) {
            if (!d.themes.length) {
                container.innerHTML = '<div style="color:var(--text3);font-size:12px;padding:12px;text-align:center;">No themes installed.</div>';
                return;
            }
            container.innerHTML = d.themes.map((t, idx) => {
                const actionText = t.active ? 'Active' : 'Activate';
                const actionBtnClass = t.active ? 'btn-secondary' : 'btn-primary';
                const statusBadge = t.active 
                    ? '<span style="color:var(--green);font-size:11px;font-weight:bold;">Active</span>' 
                    : '<span style="color:var(--text3);font-size:11px;">Inactive</span>';
                const disabledAttr = t.active ? 'disabled' : '';
                const latestVersion = t.latest_version || t.version || 'Unknown';
                const updateBadge = t.update_available
                    ? '<span style="color:var(--yellow);font-size:11px;font-weight:bold;">Update available</span>'
                    : '<span style="color:var(--text3);font-size:11px;">Up to date</span>';
                const updateDisabled = t.update_package_available ? '' : 'disabled';
                const updateTitle = t.update_package_available
                    ? 'Update this theme'
                    : (t.update_available ? 'No update package available for this theme' : 'Theme is already up to date');
                
                return `
                <div class="plugin-item">
                    <div class="plugin-info">
                        <div class="plugin-name" title="${esc(t.name)}">${esc(t.name)}</div>
                        <div class="plugin-desc" title="${esc(t.description)}">${esc(t.description)}</div>
                        <div class="plugin-meta">Current: v${esc(t.version)} | Latest: v${esc(latestVersion)} | By ${esc(t.author)} | ${statusBadge} | ${updateBadge}</div>
                    </div>
                    <div class="plugin-toggle">
                        <button class="btn btn-sm btn-secondary" ${updateDisabled} title="${esc(updateTitle)}" id="btn-theme-up-${i}-${idx}" onclick="updateTheme(${i}, ${idx}, '${esc(t.folder)}')">
                            ↑ Update
                        </button>
                        <button class="btn btn-sm btn-secondary" id="btn-theme-reinst-${i}-${idx}" onclick="reinstallTheme(${i}, ${idx}, '${esc(t.folder)}')">
                            ⟳ Reinstall
                        </button>
                        <button class="btn btn-sm ${actionBtnClass}" ${disabledAttr} id="btn-theme-${i}-${idx}" onclick="activateTheme(${i}, ${idx}, '${esc(t.folder)}')">
                            ${actionText}
                        </button>
                        <button class="btn btn-sm btn-danger" ${disabledAttr} id="btn-theme-del-${i}-${idx}" onclick="deleteTheme(${i}, ${idx}, '${esc(t.folder)}')">
                            🗑 Delete
                        </button>
                    </div>
                </div>`;
            }).join('');
        } else {
            container.innerHTML = `<div style="color:var(--red);font-size:12px;padding:12px;text-align:center;">Error: ${esc(d.error)}</div>`;
        }
    } catch (err) {
        container.innerHTML = '<div style="color:var(--red);font-size:12px;padding:12px;text-align:center;">Cannot load themes.</div>';
    }
}

async function updateTheme(siteIdx, themeIdx, folder) {
    const s = allSites[siteIdx];
    if (s.locked) {
        toast('Website is under WordPress Lockdown. Please disable Lockdown before updating themes.', 'error');
        return;
    }

    const btn = document.getElementById(`btn-theme-up-${siteIdx}-${themeIdx}`);
    const originalText = btn.textContent;
    btn.disabled = true;
    btn.textContent = 'Updating...';

    try {
        const fd = new FormData();
        fd.append('path', s.path);
        fd.append('theme_folder', folder);

        const r = await fetch(apiUrl('update_wp_theme'), { method: 'POST', body: fd });
        const d = await r.json();

        if (d.success) {
            toast(d.message || 'Theme updated successfully!', 'success');
            loadThemes(siteIdx);
        } else {
            toast(d.error || 'Theme update failed.', 'error');
            btn.disabled = false;
            btn.textContent = originalText;
        }
    } catch (err) {
        toast('Connection error.', 'error');
        btn.disabled = false;
        btn.textContent = originalText;
    }
}

async function activateTheme(siteIdx, themeIdx, folder) {
    const s = allSites[siteIdx];
    const btn = document.getElementById(`btn-theme-${siteIdx}-${themeIdx}`);
    const originalText = btn.textContent;
    btn.disabled = true;
    btn.textContent = '⏳...';
    
    try {
        const fd = new FormData();
        fd.append('path', s.path);
        fd.append('theme_folder', folder);
        
        const r = await fetch(apiUrl('activate_theme'), { method: 'POST', body: fd });
        const d = await r.json();
        
        if (d.success) {
            toast('Theme activated successfully!', 'success');
            loadThemes(siteIdx);
        } else {
            toast(d.error || 'Failed to activate theme.', 'error');
            btn.disabled = false;
            btn.textContent = originalText;
        }
    } catch (err) {
        toast('Connection error.', 'error');
        btn.disabled = false;
        btn.textContent = originalText;
    }
}

async function reinstallTheme(siteIdx, themeIdx, folder) {
    const s = allSites[siteIdx];
    if (s.locked) {
        toast('Website is under WordPress Lockdown. Please disable Lockdown before reinstalling themes.', 'error');
        return;
    }
    if (!confirm('Bạn có chắc chắn muốn cài đặt lại theme này không? Thao tác này sẽ tải phiên bản gốc từ WordPress.org và ghi đè lên thư mục theme hiện tại.')) {
        return;
    }
    
    const btn = document.getElementById(`btn-theme-reinst-${siteIdx}-${themeIdx}`);
    const originalText = btn.textContent;
    btn.disabled = true;
    btn.textContent = '⏳...';
    
    try {
        const fd = new FormData();
        fd.append('path', s.path);
        fd.append('theme_folder', folder);
        
        const r = await fetch(apiUrl('reinstall_wp_theme'), { method: 'POST', body: fd });
        const d = await r.json();
        
        if (d.success) {
            toast(d.message || 'Theme reinstalled successfully!', 'success');
            loadThemes(siteIdx);
        } else {
            toast(d.error || 'Failed to reinstall theme.', 'error');
            btn.disabled = false;
            btn.textContent = originalText;
        }
    } catch (err) {
        toast('Connection error.', 'error');
        btn.disabled = false;
        btn.textContent = originalText;
    }
}

async function deleteTheme(siteIdx, themeIdx, folder) {
    const s = allSites[siteIdx];
    if (s.locked) {
        toast('Website is under WordPress Lockdown. Please disable Lockdown before deleting themes.', 'error');
        return;
    }
    if (folder === s.active_theme || (document.getElementById(`btn-theme-${siteIdx}-${themeIdx}`) && document.getElementById(`btn-theme-${siteIdx}-${themeIdx}`).disabled)) {
        toast('Cannot delete the active theme.', 'error');
        return;
    }
    if (!confirm('Bạn có chắc chắn muốn xóa theme này không? Hành động này sẽ xóa toàn bộ thư mục theme khỏi hệ thống.')) {
        return;
    }
    
    const btn = document.getElementById(`btn-theme-del-${siteIdx}-${themeIdx}`);
    const originalText = btn.textContent;
    btn.disabled = true;
    btn.textContent = '⏳...';
    
    try {
        const fd = new FormData();
        fd.append('path', s.path);
        fd.append('theme_folder', folder);
        
        const r = await fetch(apiUrl('delete_wp_theme'), { method: 'POST', body: fd });
        const d = await r.json();
        
        if (d.success) {
            toast(d.message || 'Theme deleted successfully!', 'success');
            loadThemes(siteIdx);
        } else {
            toast(d.error || 'Failed to delete theme.', 'error');
            btn.disabled = false;
            btn.textContent = originalText;
        }
    } catch (err) {
        toast('Connection error.', 'error');
        btn.disabled = false;
        btn.textContent = originalText;
    }
}


/* ─── Security Manager ─── */
async function loadSecurity(i) {
    const s = allSites[i];
    const container = document.getElementById('security-list-' + i);
    container.innerHTML = '<div style="color:var(--text3);font-size:12px;padding:12px;text-align:center;">⏳ Đang quét bảo mật website...</div>';
    
    try {
        const fd = new FormData();
        fd.append('path', s.path);
        const r = await fetch(apiUrl('get_security_status'), { method: 'POST', body: fd });
        const d = await r.json();
        
        if (d.success) {
            const sec = d.security;
            
            const measures = [
                { key: 'restrict_files', title: 'Hạn chế quyền wp-config.php', desc: 'Chỉnh sửa phân quyền (Chmod) tệp wp-config.php về quyền đọc tối thiểu (0400/0600) để ngăn các tiến trình hoặc tài khoản khác trên máy chủ đọc trộm thông tin cấu hình.', icon: '🔑' },
                { key: 'security_keys', title: 'Khóa bảo mật Salt Keys', desc: 'Thay đổi hoặc tạo mới các chuỗi Salt Keys ngẫu nhiên trong tệp wp-config.php để bảo mật mã hóa cookie và phiên đăng nhập của người dùng.', icon: '🛡️' },
                { key: 'db_prefix', title: 'Thay đổi tiền tố Database', desc: 'Đổi tên toàn bộ các bảng dữ liệu WordPress trong cơ sở dữ liệu MySQL và cập nhật tiền tố cấu hình $table_prefix trong tệp cấu hình wp-config.php.', icon: '🗄️', promptInput: true },
                { key: 'rename_admin_user', title: 'Đổi tên tài khoản "admin"', desc: 'Thay đổi trực tiếp tên đăng nhập (user_login) của tài khoản quản trị từ mặc định "admin" sang tên mới trong bảng dữ liệu mysql wp_users.', icon: '👤', promptInput: true },
                { key: 'block_wp_config', title: 'Khóa truy cập wp-config.php', desc: 'Thêm cấu hình chặn truy cập trực tiếp tệp wp-config.php từ trình duyệt bên ngoài vào tệp cấu hình máy chủ .htaccess.', icon: '🚫' },
                { key: 'block_htaccess', title: 'Khóa truy cập .htaccess', desc: 'Thêm cấu hình chặn hoàn toàn việc đọc và ghi đè trực tiếp tệp cấu hình máy chủ .htaccess và tệp mật khẩu .htpasswd từ trình duyệt thông qua luật trong tệp .htaccess.', icon: '🔒' },
                { key: 'block_xmlrpc', title: 'Chặn truy cập XML-RPC', desc: 'Thêm cấu hình từ chối và vô hiệu hóa quyền truy cập tệp xmlrpc.php vào tệp cấu hình .htaccess để chống các cuộc tấn công đoán mật khẩu hàng loạt và DDoS qua API XML-RPC.', icon: '⚡' },
                { key: 'forbid_php_includes', title: 'Chặn chạy PHP trong wp-includes', desc: 'Tạo tệp cấu hình .htaccess mới trong thư mục wp-includes/ để chặn trình duyệt thực thi trực tiếp bất kỳ tệp tin .php nào tại đây.', icon: '📁' },
                { key: 'forbid_php_uploads', title: 'Chặn chạy PHP trong wp-content/uploads', desc: 'Tạo tệp cấu hình .htaccess mới trong thư mục wp-content/uploads/ để ngăn chặn tin tặc thực thi các shell/backdoor PHP được tải lên.', icon: '🖼️' },
                { key: 'disable_php_cache', title: 'Chặn chạy PHP trong wp-content/cache', desc: 'Tạo tệp cấu hình .htaccess mới trong thư mục wp-content/cache/ để chặn thực thi trực tiếp các tệp tin .php lưu trong bộ nhớ đệm.', icon: '💾' },
                { key: 'disallow_file_edit', title: 'Tắt chỉnh sửa file giao diện/plugin', desc: 'Thêm định nghĩa define("DISALLOW_FILE_EDIT", true); vào tệp cấu hình wp-config.php để ẩn hoàn toàn trình chỉnh sửa file giao diện/plugin trong trang admin WordPress.', icon: '✍️' },
                { key: 'disable_scripts_concat', title: 'Tắt gộp script admin (CONCATENATE_SCRIPTS)', desc: 'Thêm định nghĩa define("CONCATENATE_SCRIPTS", false); vào tệp cấu hình wp-config.php để tắt tính năng gộp mã script của admin.', icon: '⚙️' },
                { key: 'turn_off_pingbacks', title: 'Tắt tính năng Pingbacks', desc: 'Chạy truy vấn SQL tắt các tùy chọn default_pingback_flag và ping_status trong bảng wp_options và wp_posts của cơ sở dữ liệu để dừng cơ chế Pingbacks.', icon: '📣' },
                { key: 'bot_protection', title: 'Bảo vệ khỏi Bot xấu & Scrapers', desc: 'Thêm luật RewriteCond chặn các User-Agent của bot xấu (như curl, wget, scrapy...) truy cập vào website thông qua tệp cấu hình máy chủ .htaccess.', icon: '🤖' },
                { key: 'block_sensitive_files', title: 'Chặn file tài liệu nhạy cảm', desc: 'Thêm luật chặn đọc trực tiếp các tệp thông tin nhạy cảm (readme.html, license.txt, wp-config-sample.php) từ trình duyệt vào tệp cấu hình .htaccess.', icon: '📄' },
                { key: 'block_author_scans', title: 'Chặn quét tên đăng nhập (Author scan)', desc: 'Thêm cấu hình điều hướng RewriteRule chặn và trả về lỗi 403 đối với mọi yêu cầu quét định danh tác giả (/?author=X) vào tệp cấu hình .htaccess.', icon: '🔍' },
                { key: 'block_directory_browsing', title: 'Tắt liệt kê thư mục công cộng', desc: 'Thêm dòng lệnh Options -Indexes vào đầu tệp cấu hình máy chủ .htaccess để tắt chế độ tự động hiển thị danh sách tệp tin trong các thư mục.', icon: '📇' },
                { key: 'block_sensitive_extensions', title: 'Chặn tải file sao lưu (backup files)', desc: 'Thêm luật chặn tải xuống trực tiếp các định dạng tệp sao lưu nguy hiểm (.sql, .bak, .log, .sh, .ini, .dist) từ trình duyệt vào tệp cấu hình .htaccess.', icon: '📦' }
            ];
            
            container.innerHTML = `<div style="display:flex; flex-direction:column; gap:8px;">` + measures.map((m, idx) => {
                const isSecure = !!sec[m.key];
                const badgeClass = isSecure ? 'badge-green' : 'badge-red';
                const badgeText = isSecure ? 'Đã bảo vệ' : 'Chưa bảo vệ';
                const checkedAttr = isSecure ? 'checked' : '';
                
                let onchangeHandler = '';
                if (m.promptInput) {
                    onchangeHandler = `handleSpecialSecurityMeasure(${i}, '${m.key}', ${isSecure})`;
                } else {
                    onchangeHandler = `toggleSecurityMeasure(${i}, '${m.key}', ${isSecure})`;
                }
                
                return `
                <div class="plugin-item" style="padding: 10px 14px;">
                    <div style="font-size: 18px; flex-shrink: 0; margin-right: 8px;">${m.icon}</div>
                    <div class="plugin-info">
                        <div style="display:flex; align-items:center; gap:8px;">
                            <span class="plugin-name" style="font-size:12px; font-weight:600; color:var(--text);">${esc(m.title)}</span>
                            <span class="badge ${badgeClass}" style="padding: 1px 6px; font-size: 9px; line-height: 1;">${badgeText}</span>
                        </div>
                        <div class="plugin-desc" style="font-size:11px; color:var(--text2); margin-top: 2px; white-space: normal; line-height: 1.3;" title="${esc(m.desc)}">${esc(m.desc)}</div>
                    </div>
                    <div style="flex-shrink:0; margin-left: 12px; display:flex; align-items:center;">
                        <label class="switch">
                            <input type="checkbox" id="sec-switch-${i}-${m.key}" ${checkedAttr} onchange="${onchangeHandler}">
                            <span class="slider"></span>
                        </label>
                    </div>
                </div>
                `;
            }).join('') + `</div>`;
        } else {
            container.innerHTML = `<div style="color:var(--red);font-size:12px;padding:12px;text-align:center;">Lỗi: ${esc(d.error)}</div>`;
        }
    } catch (err) {
        container.innerHTML = '<div style="color:var(--red);font-size:12px;padding:12px;text-align:center;">Không thể tải trạng thái bảo mật.</div>';
    }
}

async function toggleSecurityMeasure(siteIdx, measureKey, currentSecure) {
    const s = allSites[siteIdx];
    const checkbox = document.getElementById(`sec-switch-${siteIdx}-${measureKey}`);
    const nextSecure = !currentSecure;

    // Pre-flight: detect WP Lock conflict before calling API
    const WP_CONFIG_MEASURES = ['security_keys', 'db_prefix', 'disable_scripts_concat', 'disallow_file_edit'];
    const WP_INCLUDES_MEASURES = ['forbid_php_includes'];
    if (s.locked && (WP_CONFIG_MEASURES.includes(measureKey) || WP_INCLUDES_MEASURES.includes(measureKey))) {
        checkbox.checked = currentSecure;
        toast('🔒 Website đang bị khóa (WP Lock). Vui lòng tắt WP Lock trong tab "Overview Details" trước khi thay đổi tính năng này.', 'error');
        return;
    }
    
    checkbox.disabled = true;


    let success = false;
    let errMsg = '';

    try {
        const fd = new FormData();
        fd.append('path', s.path);
        fd.append('measure', measureKey);
        fd.append('enable', nextSecure ? 'true' : 'false');
        
        const r = await fetch(apiUrl('toggle_security'), { method: 'POST', body: fd });
        const d = await r.json();
        
        if (d.success) {
            success = true;
        } else {
            errMsg = d.error || 'Thao tác thất bại.';
        }
    } catch (err) {
        errMsg = 'Lỗi kết nối đến máy chủ.';
    }

    if (success) {
        // ✅ Cập nhật badge trực tiếp trên DOM — không reload lại list
        const badge = checkbox.closest('.plugin-item')?.querySelector('.badge');
        if (badge) {
            badge.className = `badge ${nextSecure ? 'badge-green' : 'badge-red'}`;
            badge.textContent = nextSecure ? 'Đã bảo vệ' : 'Chưa bảo vệ';
        }
        // Cập nhật lại onchange với giá trị currentSecure mới
        checkbox.setAttribute('onchange', `toggleSecurityMeasure(${siteIdx}, '${measureKey}', ${nextSecure})`);
        checkbox.disabled = false;
        toast(`${nextSecure ? '✅ Đã bật' : '⛔ Đã tắt'} tính năng bảo mật thành công!`, 'success');
    } else {
        checkbox.checked = currentSecure; // revert toggle state on error
        checkbox.disabled = false;
        toast('❌ ' + errMsg, 'error');
    }
}

async function handleSpecialSecurityMeasure(siteIdx, measureKey, currentSecure) {
    const s = allSites[siteIdx];
    const checkbox = document.getElementById(`sec-switch-${siteIdx}-${measureKey}`);
    
    checkbox.checked = currentSecure;

    // Pre-flight: db_prefix needs to write to wp-config.php — blocked when locked
    if (s.locked && measureKey === 'db_prefix') {
        toast('🔒 Website đang bị khóa (WP Lock). Vui lòng tắt WP Lock trong tab "Overview Details" trước khi đổi tiền tố Database.', 'error');
        return;
    }
    
    if (measureKey === 'db_prefix') {
        const randomStr = Math.random().toString(36).substring(2, 6);
        const suggestedPrefix = `wp_${randomStr}_`;
        const newPrefix = prompt(
            "Nhập tiền tố Database mới (chỉ dùng chữ cái thường, số và dấu gạch dưới):\nLưu ý: Hệ thống sẽ đổi tên toàn bộ các bảng hiện tại và cập nhật tệp wp-config.php.", 
            suggestedPrefix
        );
        
        if (newPrefix === null) return;
        
        const cleanPrefix = newPrefix.trim();
        if (!cleanPrefix) {
            alert("Tiền tố Database không được để trống!");
            return;
        }
        
        if (!/^[a-z0-9_]+$/i.test(cleanPrefix)) {
            alert("Tiền tố Database không đúng định dạng (chỉ dùng chữ cái, số, gạch dưới)!");
            return;
        }
        
        checkbox.disabled = true;
        let ok = false;
        try {
            const fd = new FormData();
            fd.append('path', s.path);
            fd.append('measure', 'db_prefix');
            fd.append('enable', 'true');
            fd.append('new_prefix', cleanPrefix);
            
            const r = await fetch(apiUrl('toggle_security'), { method: 'POST', body: fd });
            const d = await r.json();
            
            if (d.success) {
                toast("✅ Đã thay đổi tiền tố database thành công!", "success");
                s.db_prefix = cleanPrefix;
                ok = true;
            } else {
                toast("❌ " + (d.error || "Lỗi khi đổi tiền tố database."), "error");
            }
        } catch (err) {
            toast("❌ Lỗi kết nối máy chủ.", "error");
        }
        checkbox.disabled = false;
        setTimeout(() => loadSecurity(siteIdx), ok ? 800 : 300);
        
    } else if (measureKey === 'rename_admin_user') {
        if (currentSecure) {
            alert("Tài khoản 'admin' mặc định đã được đổi tên hoặc không tồn tại. Để bảo mật, không thể khôi phục lại tên tài khoản mặc định 'admin'.");
            return;
        }
        
        const newUsername = prompt(
            "Nhập tên đăng nhập mới thay thế cho tài khoản 'admin' (chỉ dùng chữ thường, số, dấu chấm, gạch ngang, gạch dưới):",
            "wp_manager_admin"
        );
        
        if (newUsername === null) return;
        
        const cleanUsername = newUsername.trim();
        if (!cleanUsername) {
            alert("Tên đăng nhập mới không được để trống!");
            return;
        }
        
        if (!/^[a-z0-9_\-\.]+$/i.test(cleanUsername)) {
            alert("Tên đăng nhập mới không đúng định dạng!");
            return;
        }
        
        if (cleanUsername.toLowerCase() === 'admin') {
            alert("Tên đăng nhập mới không được là 'admin'!");
            return;
        }
        
        checkbox.disabled = true;
        let ok2 = false;
        try {
            const fd = new FormData();
            fd.append('path', s.path);
            fd.append('measure', 'rename_admin_user');
            fd.append('enable', 'true');
            fd.append('new_admin_username', cleanUsername);
            
            const r = await fetch(apiUrl('toggle_security'), { method: 'POST', body: fd });
            const d = await r.json();
            
            if (d.success) {
                toast(`✅ Đã đổi tên tài khoản 'admin' thành '${cleanUsername}' thành công!`, "success");
                ok2 = true;
            } else {
                toast("❌ " + (d.error || "Lỗi khi đổi tên tài khoản admin."), "error");
            }
        } catch (err) {
            toast("❌ Lỗi kết nối máy chủ.", "error");
        }
        checkbox.disabled = false;
        setTimeout(() => loadSecurity(siteIdx), ok2 ? 800 : 300);
    }
}

/* ─── File Lock Protection ─── */
async function toggleLock(i) {
    const s = allSites[i];
    const isLocked = s.locked;
    const btn = document.getElementById('btn-lock-' + i);
    const label = document.getElementById('lock-label-' + i);
    const hBadge = document.getElementById('hb-lock-' + i);
    
    const originalText = btn.textContent;
    btn.disabled = true;
    btn.textContent = '⏳...';
    
    try {
        const action = isLocked ? 'unlock' : 'lock';
        const fd = new FormData();
        fd.append('path', s.path);
        
        const r = await fetch(apiUrl(action), { method: 'POST', body: fd });
        const d = await r.json();
        
        if (d.success) {
            s.locked = !isLocked;
            toast(d.message || 'Updated protection status.', 'success');
            
            if (s.locked) {
                btn.className = 'btn btn-sm btn-secondary';
                btn.textContent = '🔓 Tắt';
                label.innerHTML = '🔒 WordPress Lockdown (Khoá thư mục và tập tin): đang <span style="color:var(--green);font-weight:bold;">Bật</span>';
                if (hBadge) {
                    hBadge.className = 'badge badge-yellow';
                    hBadge.textContent = '🔒 Lockdown';
                }
            } else {
                btn.className = 'btn btn-sm btn-primary';
                btn.textContent = '🔒 Bật';
                label.innerHTML = '🔓 WordPress Lockdown (Khoá thư mục và tập tin): đang <span style="color:var(--text3);font-weight:bold;">Tắt</span>';
                if (hBadge) {
                    hBadge.className = 'badge badge-gray';
                    hBadge.textContent = '🔓 Unlocked';
                }
            }
        } else {
            toast(d.error || 'Failed to update file protection.', 'error');
            btn.textContent = originalText;
        }
    } catch (err) {
        toast('Connection error.', 'error');
        btn.textContent = originalText;
    } finally {
        btn.disabled = false;
    }
}

/* ─── WP Cron Management ─── */
async function toggleCron(i) {
    const s = allSites[i];
    if (s.locked) {
        toast('Website is under WordPress Lockdown. Please disable Lockdown before modifying WP Cron status.', 'error');
        return;
    }
    const isCronDisabled = s.disable_wp_cron;
    const btn = document.getElementById('btn-cron-' + i);
    const label = document.getElementById('cron-label-' + i);
    
    const originalText = btn.textContent;
    btn.disabled = true;
    btn.textContent = '⏳...';
    
    try {
        const nextStatus = !isCronDisabled;
        const fd = new FormData();
        fd.append('path', s.path);
        fd.append('enable', nextStatus ? 'true' : 'false');
        
        const r = await fetch(apiUrl('toggle_cron'), { method: 'POST', body: fd });
        const d = await r.json();
        
        if (d.success) {
            s.disable_wp_cron = nextStatus;
            toast(d.message || 'Updated WP Cron status.', 'success');
            
            if (s.disable_wp_cron) {
                btn.className = 'btn btn-sm btn-secondary';
                btn.textContent = '⚙️ Tắt';
                label.innerHTML = '⚡ Disable WP Cron (Tắt Cron mặc định và dùng System Cron): đang <span style="color:var(--green);font-weight:bold;">Bật</span>';
            } else {
                btn.className = 'btn btn-sm btn-primary';
                btn.textContent = '⚡ Bật';
                label.innerHTML = '⚙️ Disable WP Cron (Tắt Cron mặc định và dùng System Cron): đang <span style="color:var(--text3);font-weight:bold;">Tắt</span>';
            }
        } else {
            toast(d.error || 'Failed to update WP Cron status.', 'error');
            btn.textContent = originalText;
        }
    } catch (err) {
        toast('Connection error.', 'error');
        btn.textContent = originalText;
    } finally {
        btn.disabled = false;
    }
}

/* ─── Auto Check Update Management ─── */
async function toggleAutoUpdate(i) {
    const s = allSites[i];
    if (s.locked) {
        toast('Website is under WordPress Lockdown. Please disable Lockdown before modifying Auto Update status.', 'error');
        return;
    }
    const isAutoUpdateDisabled = s.disable_auto_update;
    const btn = document.getElementById('btn-autoupdate-' + i);
    const label = document.getElementById('autoupdate-label-' + i);
    
    const originalText = btn.textContent;
    btn.disabled = true;
    btn.textContent = '⏳...';
    
    try {
        const nextStatus = isAutoUpdateDisabled; // true to Enable (remove file), false to Disable (create file)
        const fd = new FormData();
        fd.append('path', s.path);
        fd.append('enable', nextStatus ? 'true' : 'false');
        
        const r = await fetch(apiUrl('toggle_auto_update'), { method: 'POST', body: fd });
        const d = await r.json();
        
        if (d.success) {
            s.disable_auto_update = !nextStatus;
            toast(d.message || 'Updated Auto Update status.', 'success');
            
            if (s.disable_auto_update) {
                btn.className = 'btn btn-sm btn-secondary';
                btn.textContent = '⚙️ Tắt';
                label.innerHTML = '⚡ Disable Auto Check Update (Tắt tự động kiểm tra cập nhật): đang <span style="color:var(--green);font-weight:bold;">Bật</span>';
            } else {
                btn.className = 'btn btn-sm btn-primary';
                btn.textContent = '⚡ Bật';
                label.innerHTML = '⚙️ Disable Auto Check Update (Tắt tự động kiểm tra cập nhật): đang <span style="color:var(--text3);font-weight:bold;">Tắt</span>';
            }
        } else {
            toast(d.error || 'Failed to update Auto Update status.', 'error');
            btn.textContent = originalText;
        }
    } catch (err) {
        toast('Connection error.', 'error');
        btn.disabled = false;
        btn.textContent = originalText;
    } finally {
        btn.disabled = false;
    }
}

/* ─── WP Debug Management ─── */
async function toggleDebug(i) {
    const s = allSites[i];
    if (s.locked) {
        toast('Website is under WordPress Lockdown. Please disable Lockdown before modifying WP Debug status.', 'error');
        return;
    }
    const isDebugEnabled = s.wp_debug_enabled;
    const btn = document.getElementById('btn-debug-' + i);
    const label = document.getElementById('debug-label-' + i);
    
    const originalText = btn.textContent;
    btn.disabled = true;
    btn.textContent = '⏳...';
    
    try {
        const nextStatus = !isDebugEnabled;
        const fd = new FormData();
        fd.append('path', s.path);
        fd.append('enable', nextStatus ? 'true' : 'false');
        
        const r = await fetch(apiUrl('toggle_debug'), { method: 'POST', body: fd });
        const d = await r.json();
        
        if (d.success) {
            s.wp_debug_enabled = nextStatus;
            toast(d.message || 'Updated WP Debug status.', 'success');
            
            if (s.wp_debug_enabled) {
                btn.className = 'btn btn-sm btn-secondary';
                btn.textContent = '⚙️ Tắt';
                label.innerHTML = '⚡ WP Debug (Ghi nhật ký lỗi phát triển): đang <span style="color:var(--green);font-weight:bold;">Bật</span>';
            } else {
                btn.className = 'btn btn-sm btn-primary';
                btn.textContent = '⚡ Bật';
                label.innerHTML = '⚙️ WP Debug (Ghi nhật ký lỗi phát triển): đang <span style="color:var(--text3);font-weight:bold;">Tắt</span>';
            }
        } else {
            toast(d.error || 'Failed to update WP Debug status.', 'error');
            btn.textContent = originalText;
        }
    } catch (err) {
        toast('Connection error.', 'error');
        btn.textContent = originalText;
    } finally {
        btn.disabled = false;
    }
}

/* ─── WordPress Core Update ─── */
async function updateCore(i) {
    const s = allSites[i];
    if (s.locked) {
        toast('Website is under WordPress Lockdown. Please disable Lockdown before updating WordPress core.', 'error');
        return;
    }

    if (!confirm('Update WordPress core for this website now? A backup is strongly recommended before running core updates.')) {
        return;
    }

    const btn = document.getElementById('btn-core-update-' + i);
    const originalText = btn.textContent;
    btn.disabled = true;
    btn.textContent = 'Updating core...';

    try {
        const fd = new FormData();
        fd.append('path', s.path);

        const r = await fetch(apiUrl('update_core'), { method: 'POST', body: fd });
        const d = await r.json();

        if (d.success) {
            toast(d.message || 'WordPress core updated successfully!', 'success');
            await fetchSites(true);
        } else {
            toast(d.error || 'WordPress core update failed.', 'error');
            btn.disabled = false;
            btn.textContent = originalText;
        }
    } catch (err) {
        toast('Connection error.', 'error');
        btn.disabled = false;
        btn.textContent = originalText;
    }
}

/* ─── Refresh screenshot ─── */
function refreshShot(i) {
    const s   = allSites[i];
    const img = document.getElementById('shot-big-'+i);
    const th  = document.querySelector('#cb-'+i+' .site-thumb img');
    const url = thumbUrl(s.siteurl) + '&_=' + Date.now();
    img.src = url;
    if (th) th.src = url;
    toast('Refreshing screenshot…');
}

/* ─── Visit site / WP Admin ─── */
function visitSite(i, suffix) {
    const s = allSites[i];
    window.open(s.siteurl + suffix, '_blank');
}

/* ─── Open File Manager ─── */
function openFileManager(i) {
    const s = allSites[i];
    let path = s.path;
    const prefix = '/home/' + DA_USER;
    if (path.startsWith(prefix)) {
        path = path.substring(prefix.length);
    }
    window.open('/CMD_FILE_MANAGER?path=' + encodeURIComponent(path), '_blank');
}

/* ─── Render ─── */
function renderSites(sites) {
    const cnt = document.getElementById('sites-container');
    document.getElementById('count-label').textContent =
        sites.length + ' installation' + (sites.length!==1?'s':'') + ' total';

    if (!sites.length) {
        cnt.innerHTML = `<div class="empty-state">
            <div class="icon">📂</div>
            <strong>No WordPress installations found</strong>
            <p>Click <em>Scan Hosting</em> to search, or <em>Install WordPress</em> to add one.</p>
        </div>`;
        return;
    }

    cnt.innerHTML = sites.map((s,i) => {
        const statusBadge = s.status === 'active'
            ? '<span class="badge badge-green">● Connected</span>'
            : '<span class="badge badge-red">● DB Error</span>';
        const lockBadge = s.locked
            ? `<span class="badge badge-yellow" id="hb-lock-${i}">🔒 Lockdown</span>`
            : `<span class="badge badge-gray" id="hb-lock-${i}">🔓 Unlocked</span>`;
        const pathShort = s.path.replace('/home/'+DA_USER+'/', '~/');
        const shotSrc   = thumbUrl(s.siteurl);

        return `<div class="site-card">
            <!-- Card header -->
            <div class="card-header" onclick="toggleCard(${i})">
                <!-- Thumbnail -->
                <div class="site-thumb">
                    <div class="thumb-loader" id="tl-${i}">🌐</div>
                    <img src="${esc(shotSrc)}"
                          alt="screenshot"
                          onload="document.getElementById('tl-${i}').classList.add('hidden')"
                          onerror="this.style.opacity='.2'; document.getElementById('tl-${i}').classList.add('hidden')">
                </div>
                <!-- Info -->
                <div class="site-info">
                    <div class="site-name">${esc(s.blogname)}</div>
                    <div class="site-url"><a href="${esc(s.siteurl)}" target="_blank" onclick="event.stopPropagation()">${esc(s.siteurl)}</a></div>
                </div>
                <!-- Badges -->
                <div class="badges">
                    ${statusBadge}
                    <span class="badge badge-blue">WP ${esc(s.version)}</span>
                    ${lockBadge}
                </div>
                <!-- Quick actions -->

                <span class="chevron" id="cv-${i}">▶</span>
            </div>

            <!-- Card body (expanded) -->
            <div class="card-body" id="cb-${i}">
                <!-- Big screenshot strip -->
                <div style="display:none;" class="card-screenshot-bar">
                    <img id="shot-big-${i}" src="${esc(shotSrc)}"
                          alt="Website screenshot"
                          onerror="this.style.opacity='.1'">
                    <div class="shot-overlay"></div>
                    <span class="shot-label">📷 Live screenshot via thum.io</span>
                    <div class="shot-refresh">
                        <button class="btn btn-secondary btn-sm" onclick="refreshShot(${i})">⟳ Refresh</button>
                    </div>
                </div>

                <!-- Tabs headers -->
                <div class="card-tabs" onclick="event.stopPropagation()">
                    <button class="tab-btn active" onclick="switchTab(${i}, 'details', event)">ℹ️ Overview Details</button>
                    <button class="tab-btn" onclick="switchTab(${i}, 'security', event)">🛡️ Security & Protection</button>
                    <button class="tab-btn" onclick="switchTab(${i}, 'plugins', event)">🔌 Plugins</button>
                    <button class="tab-btn" onclick="switchTab(${i}, 'themes', event)">🎨 Themes</button>
                </div>

                <!-- Tab 1: Overview Details -->
                <div class="card-tab-content active" id="tab-content-${i}-details">
                    <div class="card-sec-title">ℹ️ Installation Details</div>
                    <div class="card-details" style="grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));">
                        <div class="detail-item"><label>Domain</label><div class="val">${esc(s.domain)}</div></div>
                        <div class="detail-item"><label>Sub-path</label><div class="val">${esc(s.subdir||'(root)')}</div></div>
                        <div class="detail-item"><label>Database</label><div class="val">${esc(s.db_name)}</div></div>
                        <div class="detail-item"><label>DB User</label><div class="val">${esc(s.db_user||'—')}</div></div>
                        <div class="detail-item"><label>DB Prefix</label><div class="val">${esc(s.db_prefix)}</div></div>
                        <div class="detail-item"><label>WP Version</label><div class="val">${esc(s.version)}</div></div>
                        <div class="detail-item" style="grid-column:span 2"><label>Files Path</label><div class="val">${esc(pathShort)}</div></div>
                    </div>
                    <div class="lock-section" onclick="event.stopPropagation()">
                        <div class="lock-status-label" id="lock-label-${i}">
                            ${s.locked 
                                ? '🔒 WordPress Lockdown (Khoá thư mục và tập tin): đang <span style="color:var(--green);font-weight:bold;">Bật</span>' 
                                : '🔓 WordPress Lockdown (Khoá thư mục và tập tin): đang <span style="color:var(--text3);font-weight:bold;">Tắt</span>'}
                        </div>
                        <button class="btn btn-sm ${s.locked ? 'btn-secondary' : 'btn-primary'}" id="btn-lock-${i}" onclick="toggleLock(${i})">
                            ${s.locked ? '🔓 Tắt' : '🔒 Bật'}
                        </button>
                    </div>
                    <div class="lock-section" style="margin-top: 12px; border-top: 1px dashed var(--border); padding-top: 12px;" onclick="event.stopPropagation()">
                        <div class="lock-status-label" id="cron-label-${i}">
                            ${s.disable_wp_cron 
                                ? '⚡ Disable WP Cron (Tắt Cron mặc định và dùng System Cron): đang <span style="color:var(--green);font-weight:bold;">Bật</span>' 
                                : '⚙️ Disable WP Cron (Tắt Cron mặc định và dùng System Cron): đang <span style="color:var(--text3);font-weight:bold;">Tắt</span>'}
                        </div>
                        <button class="btn btn-sm ${s.disable_wp_cron ? 'btn-secondary' : 'btn-primary'}" id="btn-cron-${i}" onclick="toggleCron(${i})">
                            ${s.disable_wp_cron ? '⚙️ Tắt' : '⚡ Bật'}
                        </button>
                    </div>
                    <div class="lock-section" style="margin-top: 12px; border-top: 1px dashed var(--border); padding-top: 12px;" onclick="event.stopPropagation()">
                        <div class="lock-status-label" id="autoupdate-label-${i}">
                            ${s.disable_auto_update 
                                ? '⚡ Disable Auto Check Update (Tắt tự động kiểm tra cập nhật): đang <span style="color:var(--green);font-weight:bold;">Bật</span>' 
                                : '⚙️ Disable Auto Check Update (Tắt tự động kiểm tra cập nhật): đang <span style="color:var(--text3);font-weight:bold;">Tắt</span>'}
                        </div>
                        <button class="btn btn-sm ${s.disable_auto_update ? 'btn-secondary' : 'btn-primary'}" id="btn-autoupdate-${i}" onclick="toggleAutoUpdate(${i})">
                            ${s.disable_auto_update ? '⚙️ Tắt' : '⚡ Bật'}
                        </button>
                    </div>
                    <div class="lock-section" style="margin-top: 12px; border-top: 1px dashed var(--border); padding-top: 12px;" onclick="event.stopPropagation()">
                        <div class="lock-status-label" id="debug-label-${i}">
                            ${s.wp_debug_enabled 
                                ? '⚡ WP Debug (Ghi nhật ký lỗi phát triển): đang <span style="color:var(--green);font-weight:bold;">Bật</span>' 
                                : '⚙️ WP Debug (Ghi nhật ký lỗi phát triển): đang <span style="color:var(--text3);font-weight:bold;">Tắt</span>'}
                        </div>
                        <button class="btn btn-sm ${s.wp_debug_enabled ? 'btn-secondary' : 'btn-primary'}" id="btn-debug-${i}" onclick="toggleDebug(${i})">
                            ${s.wp_debug_enabled ? '⚙️ Tắt' : '⚡ Bật'}
                        </button>
                    </div>
                </div>

                <!-- Tab 1.5: Security & Protection -->
                <div class="card-tab-content" id="tab-content-${i}-security">
                    <div class="card-sec-title">
                        <span>🛡️ Security Hardening Status</span>
                        <button class="btn btn-secondary btn-sm" onclick="loadSecurity(${i})">⟳ Scan & Refresh</button>
                    </div>
                    <div class="plugin-list" id="security-list-${i}" style="margin-top: 12px;">
                        <div style="color:var(--text3);font-size:12px;padding:12px;text-align:center;">
                            Clicking Security tab or Scan & Refresh will load status...
                        </div>
                    </div>
                </div>

                <!-- Tab 2: Plugins -->
                <div class="card-tab-content" id="tab-content-${i}-plugins">
                    <div class="card-sec-title">
                        <span>🔌 Installed Plugins</span>
                        <button class="btn btn-secondary btn-sm" onclick="loadPlugins(${i})">⟳ Refresh</button>
                    </div>
                    <div class="plugin-list" id="plugin-list-${i}">
                        <div style="color:var(--text3);font-size:12px;padding:12px;text-align:center;">
                            Clicking Plugins tab or Refresh will load plugins...
                        </div>
                    </div>
                </div>

                <!-- Tab 3: Themes -->
                <div class="card-tab-content" id="tab-content-${i}-themes">
                    <div class="card-sec-title">
                        <span>🎨 Installed Themes</span>
                        <button class="btn btn-secondary btn-sm" onclick="loadThemes(${i})">⟳ Refresh</button>
                    </div>
                    <div class="plugin-list" id="theme-list-${i}">
                        <div style="color:var(--text3);font-size:12px;padding:12px;text-align:center;">
                            Clicking Themes tab or Refresh will load themes...
                        </div>
                    </div>
                </div>

                <!-- Action row -->
                <div class="card-action-row">
                    <button class="btn btn-blue btn-sm" onclick="doMagicLogin(${i})">⚡ Magic Login</button>
                    <button class="btn btn-primary btn-sm" id="btn-core-update-${i}" onclick="updateCore(${i})">↑ Update Core</button>
                    <button class="btn btn-secondary btn-sm" onclick="visitSite(${i}, '/wp-admin/')">⊞ WP Admin</button>
                    <button class="btn btn-secondary btn-sm" onclick="visitSite(${i}, '')">🌐 Visit Site</button>
                    <button class="btn btn-secondary btn-sm" onclick="openFileManager(${i})">📂 File Manager</button>
                    <button class="btn btn-danger btn-sm" style="margin-left:auto" onclick="openDeleteModal(${i})">🗑 Delete Website</button>
                </div>
            </div>
        </div>`;
    }).join('');
}

/* ─── Filter ─── */
function filterSites() {
    const q = document.getElementById('search-input').value.toLowerCase();
    renderSites(allSites.filter(s =>
        (s.blogname+s.siteurl+s.path+s.db_name).toLowerCase().includes(q)
    ));
}

/* ─── Fetch list ─── */
async function fetchSites(scan=false) {
    document.getElementById('sites-container').innerHTML =
        `<div class="empty-state"><div class="icon">⏳</div><strong>${scan?'Scanning directories…':'Loading…'}</strong></div>`;
    try {
        const r = await fetch(apiUrl(scan?'scan':'list'));
        const d = await r.json();
        if (d.success) { allSites = d.sites; renderSites(allSites); }
        else toast(d.error||'Failed.', 'error');
    } catch { toast('Cannot connect to backend.', 'error'); }
}

async function triggerScan() {
    const btn = document.getElementById('btn-scan');
    btn.disabled=true; btn.textContent='⏳ Scanning…';
    await fetchSites(true);
    btn.disabled=false; btn.textContent='⟳ Scan Hosting';
    toast('Scan complete.', 'success');
}

/* ─── Install: domain list ─── */
async function loadDomains() {
    const sel = document.getElementById('inst-domain');
    sel.innerHTML = '<option value="" disabled selected>Loading…</option>';
    try {
        const r = await fetch(apiUrl('get_domains'));
        const d = await r.json();
        sel.innerHTML = '<option value="" disabled selected>Select domain…</option>';
        if (d.success && d.domains.length) {
            for (const dom of d.domains) {
                const o = document.createElement('option'); o.value=dom; o.textContent=dom;
                sel.appendChild(o);
                const subs = await fetchSubdomains(dom);
                subs.forEach(sub => {
                    const s = document.createElement('option');
                    const full = sub+'.'+dom;
                    s.value=full; s.textContent='  └─ '+full;
                    sel.appendChild(s);
                });
            }
        }
    } catch { toast('Failed to load domains.', 'error'); }
}

async function fetchSubdomains(domain) {
    try {
        const r = await fetch('/CMD_API_SUBDOMAINS?json=yes&domain='+encodeURIComponent(domain));
        const ct = r.headers.get('content-type')||'';
        if (ct.includes('application/json')) {
            const d = await r.json();
            if (Array.isArray(d)) return d;
            if (d&&d.list) return Array.isArray(d.list)?d.list:Object.values(d.list);
        }
        const text = await r.text();
        const params = new URLSearchParams(text);
        const list=[];
        for (const [k,v] of params.entries())
            if (k==='list[]'||k.startsWith('list[')) list.push(v);
        return list;
    } catch { return []; }
}

async function loadUserZipFiles() {
    const select = document.getElementById('inst-zip-path');
    select.innerHTML = '<option value="" disabled selected>Đang quét các tệp ZIP trên hosting...</option>';
    try {
        const r = await fetch(apiUrl('list_zips'));
        const d = await r.json();
        if (d.success && d.zips && d.zips.length) {
            select.innerHTML = '<option value="" disabled selected>Chọn tệp ZIP backup...</option>';
            d.zips.forEach(z => {
                const opt = document.createElement('option');
                opt.value = z.absolute_path;
                opt.textContent = z.display_path;
                select.appendChild(opt);
            });
        } else {
            select.innerHTML = '<option value="" disabled selected>Không tìm thấy tệp .zip nào trong tài khoản hosting của bạn.</option>';
        }
    } catch {
        select.innerHTML = '<option value="" disabled selected>Lỗi khi kết nối để quét tệp ZIP.</option>';
    }
}

function toggleInstallSource(mode) {
    const zipWrapper = document.getElementById('inst-zip-wrapper');
    const zipSelect = document.getElementById('inst-zip-path');
    const adminSec = document.getElementById('inst-admin-section');
    const adminInputs = adminSec.querySelectorAll('input');

    if (mode === 'zip') {
        zipWrapper.style.display = 'block';
        zipSelect.required = true;
        adminSec.style.display = 'none';
        adminInputs.forEach(i => i.required = false);
        loadUserZipFiles();
    } else {
        zipWrapper.style.display = 'none';
        zipSelect.required = false;
        adminSec.style.display = 'block';
        adminInputs.forEach(i => i.required = true);
    }
}

function openInstallModal() {
    openModal('modal-install');
    loadDomains();
    genPass('inst-dbpass');
    genPass('inst-adminpass');
    
    // Reset install source selection to 'fresh'
    const radios = document.getElementsByName('inst-source');
    if (radios.length) radios[0].checked = true;
    toggleInstallSource('fresh');
}

/* ─── Install: create DB + install WP ─── */
async function createDB(name, user, pass) {
    const p = new URLSearchParams({action:'create',name,user,passwd:pass,passwd2:pass});
    const r = await fetch('/CMD_API_DATABASES',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:p.toString()});
    const text = await r.text();
    const q = new URLSearchParams(text);
    if (q.get('error')==='1') throw new Error(decodeURIComponent(q.get('details')||'DB creation failed'));
    return { db_name: q.get('db')||(DA_USER+'_'+name), db_user: q.get('user')||(DA_USER+'_'+user) };
}

async function executeInstall(e) {
    e.preventDefault();
    const btn = document.getElementById('btn-install-submit');
    
    // Determine the source mode
    const sourceRadio = document.querySelector('input[name="inst-source"]:checked');
    const mode = sourceRadio ? sourceRadio.value : 'fresh';
    
    btn.disabled=true; btn.textContent='Creating database…';
    try {
        const db = await createDB(
            document.getElementById('inst-dbname').value,
            document.getElementById('inst-dbuser').value,
            document.getElementById('inst-dbpass').value
        );
        toast('1/3 Database created.', 'success');
        
        btn.textContent = mode === 'zip' ? 'Extracting ZIP & Importing database…' : 'Installing WordPress…';

        const fd = new FormData();
        fd.append('action', 'install');
        fd.append('mode', mode);
        fd.append('domain', document.getElementById('inst-domain').value);
        fd.append('subdir', document.getElementById('inst-subdir').value);
        fd.append('db_name', db.db_name);
        fd.append('db_user', db.db_user);
        fd.append('db_pass', document.getElementById('inst-dbpass').value);
        fd.append('protocol', document.getElementById('inst-protocol').value);

        if (mode === 'zip') {
            const zipPath = document.getElementById('inst-zip-path').value;
            if (!zipPath) {
                throw new Error("Vui lòng chọn một tệp ZIP backup để tiến hành cài đặt!");
            }
            fd.append('zip_path', zipPath);
        } else {
            fd.append('site_title', document.getElementById('inst-title').value);
            fd.append('admin_user', document.getElementById('inst-adminuser').value);
            fd.append('admin_pass', document.getElementById('inst-adminpass').value);
            fd.append('admin_email', document.getElementById('inst-adminemail').value);
        }

        const r = await fetch(apiUrl(), {
            method: 'POST',
            body: fd
        });
        const d = await r.json();
        if (d.success) {
            toast(mode === 'zip' ? 'Cài đặt WordPress từ ZIP thành công!' : 'WordPress installed successfully!', 'success');
            closeModal('modal-install');
            fetchSites(false);
        } else {
            toast(d.error || 'Installation failed.', 'error');
        }
    } catch(err) { 
        toast(err.message || 'Error.', 'error'); 
    } finally { 
        btn.disabled=false; 
        btn.textContent='Install WordPress'; 
    }
}

/* ─── Magic Login ─── */
async function doMagicLogin(i) {
    const s = allSites[i];
    toast('Generating magic login…');
    try {
        const p = new URLSearchParams({action:'magic_login', path: s.path});
        const r = await fetch(apiUrl(),{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:p.toString()});
        const d = await r.json();
        if (d.success&&d.login_url) { window.open(d.login_url,'_blank'); toast('Opening WP Admin…','success'); }
        else toast(d.error||'Magic login failed.','error');
    } catch { toast('Connection error.','error'); }
}

/* ─── Delete ─── */
function openDeleteModal(i) {
    const s = allSites[i];
    deletePath = s.path; deleteDb = s.db_name;
    document.getElementById('del-path').textContent = s.path;
    document.getElementById('del-db-name').textContent = s.db_name || '(none)';
    const chk = document.getElementById('del-db-check');
    chk.checked = !!s.db_name; chk.disabled = !s.db_name;
    openModal('modal-delete');
}

async function executeDelete() {
    const btn=document.getElementById('btn-delete-confirm');
    btn.disabled=true; btn.textContent='Deleting…';
    try {
        if (document.getElementById('del-db-check').checked&&deleteDb) {
            const p=new URLSearchParams({action:'delete',select0:deleteDb});
            await fetch('/CMD_API_DATABASES',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:p.toString()});
        }
        const p=new URLSearchParams({action:'delete',path:deletePath});
        const r=await fetch(apiUrl(),{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:p.toString()});
        const d=await r.json();
        if (d.success) { toast('Deleted.','success'); closeModal('modal-delete'); fetchSites(false); }
        else toast(d.error||'Deletion failed.','error');
    } catch(err) { toast(err.message||'Error.','error'); }
    finally { btn.disabled=false; btn.textContent='Delete Permanently'; }
}

<?php if ($isAdmin): ?>
/* ─── Plugin update ─── */
function openUpdateModal() {
    document.getElementById('update-terminal').innerHTML='';
    document.getElementById('btn-update-done').disabled=true;
    openModal('modal-update');
    runPluginUpdate();
}
async function runPluginUpdate() {
    const term=document.getElementById('update-terminal');
    const log=(msg,cls='')=>{
        const ln=document.createElement('div');
        if(cls)ln.className=cls;
        ln.textContent='['+new Date().toLocaleTimeString()+'] '+msg;
        term.appendChild(ln); term.scrollTop=term.scrollHeight;
    };
    log('Connecting to GitHub…');
    try {
        const p=new URLSearchParams({action:'update_plugin'});
        const r=await fetch(apiUrl(),{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:p.toString()});
        const d=await r.json();
        if(d.success){log(d.message,'ok');log('Done. Please reload.','ok');}
        else log('Error: '+d.error,'err');
    } catch(e){log('Connection error: '+e.message,'err');}
    finally{document.getElementById('btn-update-done').disabled=false;}
}
<?php endif; ?>

/* ─── Init ─── */
window.addEventListener('DOMContentLoaded', () => {
    fetchSites(false);
    // Hide DirectAdmin's injected plugin header (same-origin iframe)
    try {
        const hdr = window.parent.document.getElementById('plugin-host-header');
        if (hdr) hdr.style.display = 'none';
    } catch(e) {}
});
</script>
</body>
</html>
