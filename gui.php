<?php
/**
 * Ultimate DirectAdmin WordPress Manager
 * GUI — Dark theme, wide layout, with site screenshots
 */
$username = getenv('USERNAME') ?: getenv('USER') ?: 'user';

// Read plugin version from plugin.conf
$plugin_version = '1.3.24';
$conf_file = __DIR__ . '/plugin.conf';
if (is_readable($conf_file)) {
    foreach (file($conf_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        if (strncmp($line, 'version=', 8) === 0) {
            $plugin_version = trim(substr($line, 8));
            break;
        }
    }
}
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
    <link rel="stylesheet" href="https://s.w.org/wp-includes/css/dashicons.min.css">
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
}
.topbar .logo-icon {
    font-size: 20px;
    color: var(--blue);
    margin-right: 6px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
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
.site-path {
    font-size: 10.5px; color: var(--text3);
    font-family: monospace;
    white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
    margin-top: 3px;
    cursor: default;
}

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
    align-items: center;
}
.card-action-row .sep {
    width: 1px;
    height: 18px;
    background: var(--border);
    margin: 0 4px;
    flex-shrink: 0;
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
.inline-store-tabs {
    display: inline-flex;
    gap: 8px;
    align-items: center;
    margin-left: 12px;
}
.store-tab-btn {
    background: transparent;
    border: 1px solid transparent;
    color: var(--text2);
    cursor: pointer;
    font-size: 12px;
    font-weight: 700;
    padding: 7px 12px;
    border-radius: 4px;
}
.store-tab-btn:hover {
    color: var(--text);
    border-color: var(--border);
}
.store-tab-btn.active {
    color: var(--text);
    background: var(--bg3);
    border-color: var(--border);
}
.wp-admin-icon {
    width: 16px;
    height: 16px;
    font-size: 16px;
    line-height: 1;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    color: inherit;
}
.store-tab-btn .wp-admin-icon,
.tab-btn .wp-admin-icon,
.card-sec-title .wp-admin-icon,`r`n.btn .wp-admin-icon,`r`n.badge .wp-admin-icon,`r`n.lock-status-label .wp-admin-icon,`r`n.shot-label .wp-admin-icon,`r`n.site-path .wp-admin-icon,`r`n.empty-state .wp-admin-icon {
    flex: 0 0 auto;
}

.dashicons-spin { animation: dashicons-spin 1s linear infinite; }
@keyframes dashicons-spin { from { transform: rotate(0deg); } to { transform: rotate(360deg); } }
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

/* ── Premium Tab styles ── */
.premium-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(240px, 1fr));
    gap: 14px;
    margin-top: 14px;
}
.premium-card {
    background: var(--bg);
    border: 1px solid var(--border);
    border-radius: 8px;
    overflow: hidden;
    display: flex;
    flex-direction: column;
    transition: all .2s ease;
}
.premium-card:hover {
    border-color: var(--blue);
    transform: translateY(-2px);
    box-shadow: 0 8px 24px rgba(0,0,0,0.3);
}
.premium-banner {
    height: 100px;
    background: linear-gradient(135deg, var(--bg3), var(--border2));
    display: flex;
    align-items: center;
    justify-content: center;
    position: relative;
    overflow: hidden;
    color: var(--text3);
}
.premium-banner img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}
.premium-banner .banner-icon-fallback {
    font-size: 32px;
}
.premium-banner .badge-source {
    position: absolute;
    top: 8px;
    right: 8px;
    font-size: 9px;
    padding: 2px 6px;
    border-radius: 4px;
}
.premium-card-body {
    padding: 12px;
    flex: 1;
    display: flex;
    flex-direction: column;
    gap: 6px;
}
.premium-card-title {
    font-size: 13px;
    font-weight: 700;
    color: var(--text);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.premium-card-desc {
    font-size: 11px;
    color: var(--text2);
    height: 34px;
    overflow: hidden;
    text-overflow: ellipsis;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    line-height: 1.3;
}
.premium-card-footer {
    padding: 8px 12px 12px 12px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    border-top: 1px solid var(--border2);
}
.admin-premium-box {
    margin-top: 24px;
    background: var(--bg3);
    border: 1px solid var(--border);
    border-radius: 8px;
    padding: 16px;
}
.admin-premium-form {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
    gap: 12px;
    margin-top: 12px;
}
</style>
</head>
<body>

<!-- Top bar -->
<div class="topbar">
    <div class="logo">
        <span class="dashicons dashicons-wordpress logo-icon"></span>
        Ultimate WordPress Manager
        <span style="font-size:10px;font-weight:500;color:var(--text3);background:var(--bg3);border:1px solid var(--border);border-radius:10px;padding:1px 7px;margin-left:2px;letter-spacing:.3px;">v<?php echo htmlspecialchars($plugin_version); ?></span>
    </div>
    <span style="color:var(--text3);font-size:11px;display:flex;align-items:center;gap:12px;">
        <span><?php echo htmlspecialchars($server_ip); ?></span> | <span><?php echo htmlspecialchars($hostname); ?></span>
        <a href="/" class="btn btn-sm btn-secondary" style="font-size:10px;padding:2px 8px;border-radius:4px;height:22px;display:inline-flex;align-items:center;gap:4px;border-color:var(--border);"><span class="dashicons dashicons-arrow-left-alt2 wp-admin-icon"></span> Back to DirectAdmin</a>
    </span>
    <div class="user">
        <span><span class="dashicons dashicons-admin-users wp-admin-icon"></span> <?php echo htmlspecialchars($username); ?></span>
        <?php if ($isAdmin): ?>
        <span class="badge badge-yellow" style="font-size:10px;">Admin</span>
        <?php endif; ?>
    </div>
</div>

<!-- Toolbar -->
<div class="toolbar">
    <button class="btn btn-primary" onclick="openInstallModal()"><span class="dashicons dashicons-plus-alt2 wp-admin-icon"></span> Install WordPress</button>
    <button class="btn btn-secondary" id="btn-scan" onclick="triggerScan()"><span class="dashicons dashicons-update wp-admin-icon"></span> Scan Hosting</button>
    <?php if ($isAdmin): ?>
    <div class="toolbar-sep"></div>
    <button class="btn btn-secondary" onclick="openUpdateModal()"><span class="dashicons dashicons-update-alt wp-admin-icon"></span> Update Plugin</button>
    <?php endif; ?>
    <div class="toolbar-sep"></div>
    <input type="text" id="search-input" class="toolbar-search" placeholder="Search by name, URL, path…" oninput="filterSites()">
    <span class="count-label" id="count-label">Loading…</span>
    <button class="btn btn-secondary btn-sm" onclick="openLogsModal()" style="margin-left: 8px;"><span class="dashicons dashicons-list-view wp-admin-icon"></span> Show Logs</button>
</div>



<!-- Content -->
<div class="content">
    <div id="sites-container">
        <div class="empty-state"><div class="icon"><span class="dashicons dashicons-update wp-admin-icon dashicons-spin"></span></div><strong>Loading installations…</strong></div>
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
                    <button type="button" class="btn btn-secondary" onclick="loadUserZipFiles()"><span class="dashicons dashicons-update wp-admin-icon"></span> Quét lại</button>
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

<!-- ═══ MODAL: Clone Website ═══ -->
<div class="modal-overlay" id="modal-clone">
<div class="modal">
    <div class="modal-head">
        <h3><span class="dashicons dashicons-admin-page wp-admin-icon"></span> Clone WordPress Website</h3>
        <button class="modal-close" onclick="closeModal('modal-clone')">✕</button>
    </div>
    <form onsubmit="executeClone(event)">
    <input type="hidden" id="clone-src-path">
    <div class="modal-body">

        <div class="form-section">
            <div class="form-section-title">Source Website Path</div>
            <div class="form-group">
                <code id="clone-src-display" style="display:block;padding:8px 12px;background:var(--bg);border:1px solid var(--border);border-radius:6px;font-size:11px;word-break:break-all;color:var(--text2);"></code>
            </div>
        </div>

        <div class="form-section">
            <div class="form-section-title">Target Website Location</div>
            <div style="display: grid; grid-template-columns: 1.2fr 3fr 2fr; gap: 12px;">
                <div class="form-group">
                    <label>Protocol</label>
                    <select id="clone-protocol" class="form-control">
                        <option value="https">https://</option>
                        <option value="http">http://</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Domain / Subdomain</label>
                    <select id="clone-domain" class="form-control" required>
                        <option value="" disabled selected>Loading…</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Directory <span style="font-weight:400;color:var(--text3)">(optional)</span></label>
                    <input type="text" id="clone-subdir" class="form-control" placeholder="e.g. clone-site">
                </div>
            </div>
        </div>

        <div class="form-section">
            <div class="form-section-title">Target Database</div>
            <div style="display: grid; grid-template-columns: 1fr 1fr 1.2fr; gap: 12px;">
                <div class="form-group">
                    <label>DB Name</label>
                    <div class="input-group">
                        <span class="input-prefix"><?php echo htmlspecialchars($username); ?>_</span>
                        <input type="text" id="clone-dbname" class="form-control" placeholder="wpclone" required maxlength="16">
                    </div>
                </div>
                <div class="form-group">
                    <label>DB User</label>
                    <div class="input-group">
                        <span class="input-prefix"><?php echo htmlspecialchars($username); ?>_</span>
                        <input type="text" id="clone-dbuser" class="form-control" placeholder="wpcloneuser" required maxlength="16">
                    </div>
                </div>
                <div class="form-group">
                    <label>DB Password</label>
                    <div class="input-group-btn">
                        <input type="text" id="clone-dbpass" class="form-control" required placeholder="Password">
                        <button type="button" class="btn btn-secondary" onclick="genPass('clone-dbpass')">Gen</button>
                    </div>
                </div>
            </div>
        </div>

    </div>
    <div class="modal-footer">
        <button type="button" class="btn btn-secondary" onclick="closeModal('modal-clone')">Cancel</button>
        <button type="submit" class="btn btn-primary" id="btn-clone-submit">Clone Website</button>
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
        <div class="notice notice-error"><span class="dashicons dashicons-warning wp-admin-icon"></span> This action permanently deletes all files and cannot be undone.</div>
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

<!-- ═══ MODAL: Show Logs ═══ -->
<div class="modal-overlay" id="modal-logs">
<div class="modal" style="max-width:700px;">
    <div class="modal-head">
        <h3><span class="dashicons dashicons-list-view wp-admin-icon"></span> Nhật ký hoạt động (System Logs)</h3>
        <button class="modal-close" onclick="closeModal('modal-logs')">✕</button>
    </div>
    <div class="modal-body" style="padding:15px;">
        <textarea id="logs-textarea" readonly style="width:100%; height:400px; background:#0d1117; border:1px solid var(--border); border-radius:6px; color:#c9d1d9; font-family:monospace; font-size:11px; padding:12px; resize:vertical; outline:none; line-height:1.4;"></textarea>
    </div>
    <div class="modal-footer">
        <button class="btn btn-secondary" onclick="refreshLogs()"><span class="dashicons dashicons-update wp-admin-icon"></span> Làm mới</button>
        <button class="btn btn-secondary" onclick="closeModal('modal-logs')">Đóng</button>
    </div>
</div>
</div>

<?php if ($isAdmin): ?>
<!-- ═══ MODAL: Update Plugin ═══ -->
<div class="modal-overlay" id="modal-update">
<div class="modal" style="max-width:520px;">
    <div class="modal-head">
        <h3><span class="dashicons dashicons-upload wp-admin-icon"></span> Update Plugin from GitHub</h3>
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
const isAdmin = <?php echo $isAdmin ? 'true' : 'false'; ?>;
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
function escJsArg(s) {
    return esc(s).replace(/\\/g, '\\\\').replace(/'/g, "\\'").replace(/\r?\n/g, ' ');
}

/* ─── Copy Nginx Config ─── */
function copyNginxConfig(i) {
    const el = document.getElementById('nginx-config-' + i);
    if (el) {
        el.select();
        document.execCommand('copy');
        toast('Đã copy cấu hình Nginx vào Clipboard!', 'success');
    }
}

/* ─── Toggle card ─── */
function toggleCard(i) {
    const body = document.getElementById('cb-'+i);
    const chev = document.getElementById('cv-'+i);
    const isOpening = !body.classList.contains('open');

    // Close all other cards first and stop their log polling
    document.querySelectorAll('.card-body.open').forEach(el => {
        if (el.id !== 'cb-'+i) {
            el.classList.remove('open');
            const idx = parseInt(el.id.replace('cb-', ''));
            if (!isNaN(idx)) {
                stopLogPolling(idx);
            }
        }
    });
    document.querySelectorAll('.chevron.open').forEach(el => {
        if (el.id !== 'cv-'+i) {
            el.classList.remove('open');
        }
    });

    body.classList.toggle('open');
    chev.classList.toggle('open');
    
    // Stop polling if card is closed
    if (!body.classList.contains('open')) {
        stopLogPolling(i);
    } else {
        // Start polling if card is opened and logs tab is active
        const activeTab = body.querySelector('.tab-btn.active');
        if (activeTab && activeTab.getAttribute('onclick') && activeTab.getAttribute('onclick').includes("'logs'")) {
            startLogPolling(i);
        }
    }
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
    
    if (tabName === 'logs') {
        startLogPolling(siteIdx);
    } else {
        stopLogPolling(siteIdx);
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

/* ─── Logs Polling & Control ─── */
let logPollStates = {};

function startLogPolling(i) {
    if (!logPollStates[i]) {
        logPollStates[i] = {
            paused: false,
            timer: null,
            lastData: '',
            intervalMs: getLogPollIntervalMs(i)
        };
    }

    if (logPollStates[i].timer) return;

    fetchLogData(i);
    restartLogPollingTimer(i);
}

function stopLogPolling(i) {
    if (logPollStates[i] && logPollStates[i].timer) {
        clearInterval(logPollStates[i].timer);
        logPollStates[i].timer = null;
    }
}

function getLogPollIntervalMs(i) {
    const select = document.getElementById(`log-refresh-${i}`);
    const seconds = parseInt(select ? select.value : '3', 10);
    return Math.max(seconds || 3, 1) * 1000;
}

function restartLogPollingTimer(i) {
    if (!logPollStates[i]) return;
    if (logPollStates[i].timer) {
        clearInterval(logPollStates[i].timer);
        logPollStates[i].timer = null;
    }
    logPollStates[i].intervalMs = getLogPollIntervalMs(i);
    logPollStates[i].timer = setInterval(() => {
        if (!logPollStates[i].paused) {
            fetchLogData(i);
        }
    }, logPollStates[i].intervalMs);
}

function onLogRefreshIntervalChanged(i) {
    if (!logPollStates[i]) {
        logPollStates[i] = { paused: false, timer: null, lastData: '', intervalMs: getLogPollIntervalMs(i) };
    }
    restartLogPollingTimer(i);
    fetchLogData(i);
}

function toggleLogPause(i) {
    if (!logPollStates[i]) return;
    logPollStates[i].paused = !logPollStates[i].paused;
    const btn = document.getElementById(`btn-log-pause-${i}`);
    if (logPollStates[i].paused) {
        btn.innerHTML = '<span class="dashicons dashicons-controls-play wp-admin-icon"></span> Tiếp tục';
        btn.className = 'btn btn-sm btn-blue';
        toast('⏸ Đã tạm dừng cập nhật log.');
    } else {
        btn.innerHTML = '<span class="dashicons dashicons-controls-pause wp-admin-icon"></span> Tạm dừng';
        btn.className = 'btn btn-sm btn-secondary';
        toast('▶ Đang tải log thời gian thực...');
        fetchLogData(i);
    }
}

function onLogTypeChanged(i) {
    const type = document.getElementById(`log-type-${i}`).value;
    const filtersDiv = document.getElementById(`log-filetype-filters-${i}`);
    
    if (type === 'access') {
        filtersDiv.style.display = 'flex';
    } else {
        filtersDiv.style.display = 'none';
    }
    
    if (logPollStates[i]) {
        logPollStates[i].lastData = '';
    }
    
    const term = document.getElementById(`log-terminal-${i}`);
    const pathInfo = document.getElementById(`log-path-info-${i}`);
    term.value = "[Hệ thống] Đang tải log mới...";
    if (pathInfo) pathInfo.textContent = "Đang tải log từ: ...";
    
    fetchLogData(i);
}

function onLogFilterChanged(i) {
    fetchLogData(i);
}

async function fetchLogData(i) {
    const s = allSites[i];
    if (!s) return;
    
    const type = document.getElementById(`log-type-${i}`).value;
    const search = document.getElementById(`log-search-${i}`).value;
    const lines = document.getElementById(`log-lines-${i}`).value;
    const timeFilter = document.getElementById(`log-time-${i}`).value;
    const term = document.getElementById(`log-terminal-${i}`);
    const pathInfo = document.getElementById(`log-path-info-${i}`);
    
    let fileTypes = [];
    if (type === 'access') {
        const checkboxes = document.querySelectorAll(`input[name="log-filetype-${i}"]:checked`);
        checkboxes.forEach(cb => fileTypes.push(cb.value));
    }
    
    try {
        const fd = new FormData();
        fd.append('path', s.path);
        fd.append('log_type', type);
        fd.append('search', search);
        fd.append('lines', lines);
        fd.append('time_filter', timeFilter);
        fd.append('file_types', JSON.stringify(fileTypes));
        
        const r = await fetch(apiUrl('get_logs_data'), { method: 'POST', body: fd });
        const d = await r.json();
        
        if (d.success) {
            if (logPollStates[i] && logPollStates[i].lastData === d.logs) {
                return;
            }
            
            if (logPollStates[i]) {
                logPollStates[i].lastData = d.logs;
            }
            
            const isScrollAtBottom = term.scrollHeight - term.clientHeight <= term.scrollTop + 30;
            
            if (pathInfo) pathInfo.textContent = `Đang tải log từ: ${d.filepath || d.log_path || "Không xác định"}`;
            term.value = d.logs || "[Hệ thống] Tệp tin log rỗng hoặc không có dòng nào khớp với bộ lọc.";
            
            if (isScrollAtBottom || term.value.includes("[Hệ thống] Đang tải log")) {
                term.scrollTop = term.scrollHeight;
            }
        } else {
            if (pathInfo) pathInfo.textContent = "Đang tải log từ: Không xác định";
            term.value = `[Lỗi hệ thống] ${d.error || 'Không thể lấy dữ liệu log.'}`;
        }
    } catch (err) {
        if (pathInfo) pathInfo.textContent = "Đang tải log từ: Không xác định";
        term.value = `[Lỗi kết nối] Không thể kết nối tới máy chủ.`;
    }
}

async function clearLogFile(i) {
    const s = allSites[i];
    if (!s) return;
    
    const type = document.getElementById(`log-type-${i}`).value;
    if (type === 'access' || type === 'error') {
        toast('❌ Không được phép xóa file log hệ thống (Access/Error log) để tránh làm lỗi Web Server.', 'error');
        return;
    }
    
    if (!confirm('Bạn có chắc chắn muốn xóa rỗng nội dung file log này không? Thao tác này không thể hoàn tác.')) {
        return;
    }
    
    try {
        const fd = new FormData();
        fd.append('path', s.path);
        fd.append('log_type', type);
        
        const r = await fetch(apiUrl('clear_log_file'), { method: 'POST', body: fd });
        const d = await r.json();
        
        if (d.success) {
            toast('✅ Đã xóa rỗng dữ liệu log thành công!', 'success');
            const term = document.getElementById(`log-terminal-${i}`);
            term.value = '';
            if (logPollStates[i]) logPollStates[i].lastData = '';
            fetchLogData(i);
        } else {
            toast('❌ Lỗi: ' + d.error, 'error');
        }
    } catch (err) {
        toast('❌ Lỗi kết nối máy chủ.', 'error');
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
                    ? `<span id="plug-status-${i}-${idx}" style="color:var(--green);font-size:11px;font-weight:bold;">Active</span>` 
                    : `<span id="plug-status-${i}-${idx}" style="color:var(--text3);font-size:11px;">Inactive</span>`;
                const latestVersion = p.latest_version || p.version || 'Unknown';
                const updateBadge = p.update_available
                    ? `<span id="plug-up-badge-${i}-${idx}" style="color:var(--yellow);font-size:11px;font-weight:bold;">Update available</span>`
                    : `<span id="plug-up-badge-${i}-${idx}" style="color:var(--text3);font-size:11px;">Up to date</span>`;
                const updateDisabled = ''; // Always enable Update button to allow force reinstalling/cleaning malware
                const updateTitle = p.update_available
                    ? 'Update this plugin'
                    : 'Force update/reinstall this plugin (already at latest version)';
                
                return `
                <div class="plugin-item">
                    <div class="plugin-info">
                        <div class="plugin-name" title="${esc(p.name)}">${esc(p.name)}</div>
                        <div class="plugin-desc" title="${esc(p.description)}">${esc(p.description)}</div>
                        <div class="plugin-meta">Current: <span id="plug-ver-${i}-${idx}">v${esc(p.version)}</span> | Latest: <span id="plug-latest-${i}-${idx}">v${esc(latestVersion)}</span> | By ${esc(p.author)} | ${statusBadge} | ${updateBadge}</div>
                    </div>
                    <div class="plugin-toggle">
                        <button class="btn btn-sm btn-secondary" data-file="${esc(p.file)}" ${updateDisabled} title="${esc(updateTitle)}" id="btn-plug-up-${i}-${idx}" onclick="updatePlugin(${i}, ${idx}, '${esc(p.file)}')"><span class="dashicons dashicons-update-alt wp-admin-icon"></span> Update</button>
                        <button class="btn btn-sm btn-secondary" id="btn-plug-reinst-${i}-${idx}" onclick="reinstallPlugin(${i}, ${idx}, '${esc(p.file)}')"><span class="dashicons dashicons-update wp-admin-icon"></span> Reinstall</button>
                        <button class="btn btn-sm ${actionBtnClass}" id="btn-plug-${i}-${idx}" onclick="togglePlugin(${i}, ${idx}, '${esc(p.file)}', ${p.active})">
                            ${actionText}
                        </button>
                        <button class="btn btn-sm btn-danger" id="btn-plug-del-${i}-${idx}" onclick="deletePlugin(${i}, ${idx}, '${esc(p.file)}')"><span class="dashicons dashicons-trash wp-admin-icon"></span> Delete</button>
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

async function updateAllPlugins(siteIdx) {
    const s = allSites[siteIdx];
    if (s.locked) {
        toast('Website is under WordPress Lockdown. Please disable Lockdown before updating plugins.', 'error');
        return;
    }

    const btn = document.getElementById(`btn-plug-upall-${siteIdx}`);
    const originalText = btn ? btn.textContent : 'Update All';
    if (btn) {
        btn.disabled = true;
        btn.textContent = 'Updating all...';
    }

    try {
        let updatedCount = 0;
        const buttons = document.querySelectorAll(`[id^='btn-plug-up-${siteIdx}-']`);
        for (const b of buttons) {
            if (b.disabled) continue;
            const file = b.getAttribute('data-file');
            if (!file) continue;
            const parts = b.id.split('-');
            const plugIdx = parseInt(parts[parts.length - 1]);
            
            try {
                const success = await updatePlugin(siteIdx, plugIdx, file, false);
                if (success) updatedCount++;
            } catch (e) {
                console.error('Failed to update plugin:', file, e);
            }
        }
        if (updatedCount > 0) {
            toast('All plugins update processed!', 'success');
        } else {
            toast('No plugins needed updating.', 'info');
        }
    } catch (err) {
        toast('Connection error during bulk update.', 'error');
    } finally {
        if (btn) {
            btn.disabled = false;
            btn.textContent = originalText;
        }
        loadPlugins(siteIdx);
    }
}

async function updateAllThemes(siteIdx) {
    const s = allSites[siteIdx];
    if (s.locked) {
        toast('Website is under WordPress Lockdown. Please disable Lockdown before updating themes.', 'error');
        return;
    }

    const btn = document.getElementById(`btn-theme-upall-${siteIdx}`);
    const originalText = btn ? btn.textContent : 'Update All';
    if (btn) {
        btn.disabled = true;
        btn.textContent = 'Updating all...';
    }

    try {
        let updatedCount = 0;
        const buttons = document.querySelectorAll(`[id^='btn-theme-up-${siteIdx}-']`);
        for (const b of buttons) {
            if (b.disabled) continue;
            const folder = b.getAttribute('data-folder');
            if (!folder) continue;
            const parts = b.id.split('-');
            const themeIdx = parseInt(parts[parts.length - 1]);
            
            try {
                const success = await updateTheme(siteIdx, themeIdx, folder, false);
                if (success) updatedCount++;
            } catch (e) {
                console.error('Failed to update theme:', folder, e);
            }
        }
        if (updatedCount > 0) {
            toast('All themes update processed!', 'success');
        } else {
            toast('No themes needed updating.', 'info');
        }
    } catch (err) {
        toast('Connection error during bulk update.', 'error');
    } finally {
        if (btn) {
            btn.disabled = false;
            btn.textContent = originalText;
        }
        loadThemes(siteIdx);
    }
}

async function updatePlugin(siteIdx, plugIdx, file, reloadAfter = true) {
    const s = allSites[siteIdx];
    if (s.locked) {
        toast('Website is under WordPress Lockdown. Please disable Lockdown before updating plugins.', 'error');
        return false;
    }

    const btn = document.getElementById(`btn-plug-up-${siteIdx}-${plugIdx}`);
    const originalText = btn ? btn.textContent : 'Update';
    if (btn) {
        btn.disabled = true;
        btn.textContent = 'Updating...';
    }

    try {
        const fd = new FormData();
        fd.append('path', s.path);
        fd.append('plugin_file', file);

        const r = await fetch(apiUrl('update_wp_plugin'), { method: 'POST', body: fd });
        const d = await r.json();

        if (d.success) {
            toast(d.message || 'Plugin updated successfully!', 'success');
            if (reloadAfter) {
                if (btn) {
                    btn.disabled = false;
                    btn.textContent = 'Update';
                }
                const newPlug = d.plugin;
                if (newPlug) {
                    const verEl = document.getElementById(`plug-ver-${siteIdx}-${plugIdx}`);
                    if (verEl) verEl.textContent = `v${newPlug.version}`;
                    const latestEl = document.getElementById(`plug-latest-${siteIdx}-${plugIdx}`);
                    if (latestEl) latestEl.textContent = `v${newPlug.latest_version}`;
                    const badgeEl = document.getElementById(`plug-up-badge-${siteIdx}-${plugIdx}`);
                    if (badgeEl) badgeEl.innerHTML = `<span style="color:var(--text3);font-size:11px;">Up to date</span>`;
                }
            } else if (btn) {
                btn.textContent = 'Updated';
            }
            return true;
        } else {
            toast(d.error || 'Plugin update failed.', 'error');
            if (btn) {
                btn.disabled = false;
                btn.textContent = originalText;
            }
            return false;
        }
    } catch (err) {
        toast('Connection error.', 'error');
        if (btn) {
            btn.disabled = false;
            btn.textContent = originalText;
        }
        return false;
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
            const newActive = !isActive;
            toast(`Plugin ${newActive ? 'activated' : 'deactivated'} successfully!`, 'success');
            
            btn.disabled = false;
            btn.className = `btn btn-sm ${newActive ? 'btn-danger' : 'btn-primary'}`;
            btn.textContent = newActive ? 'Deactivate' : 'Activate';
            btn.setAttribute('onclick', `togglePlugin(${siteIdx}, ${plugIdx}, '${file.replace(/'/g, "\\'")}', ${newActive})`);
            
            const statusEl = document.getElementById(`plug-status-${siteIdx}-${plugIdx}`);
            if (statusEl) {
                statusEl.style.color = newActive ? 'var(--green)' : 'var(--text3)';
                statusEl.style.fontWeight = newActive ? 'bold' : 'normal';
                statusEl.textContent = newActive ? 'Active' : 'Inactive';
            }
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
            if (btn) {
                btn.disabled = false;
                btn.textContent = originalText;
            }
            const newPlug = d.plugin;
            if (newPlug) {
                const verEl = document.getElementById(`plug-ver-${siteIdx}-${plugIdx}`);
                if (verEl) verEl.textContent = `v${newPlug.version}`;
                const latestEl = document.getElementById(`plug-latest-${siteIdx}-${plugIdx}`);
                if (latestEl) latestEl.textContent = `v${newPlug.latest_version}`;
                const badgeEl = document.getElementById(`plug-up-badge-${siteIdx}-${plugIdx}`);
                if (badgeEl) badgeEl.innerHTML = `<span style="color:var(--text3);font-size:11px;">Up to date</span>`;
            }
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
            const pluginItem = btn.closest('.plugin-item');
            if (pluginItem) {
                pluginItem.remove();
            }
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
                    ? `<span id="theme-status-${i}-${idx}" style="color:var(--green);font-size:11px;font-weight:bold;">Active</span>` 
                    : `<span id="theme-status-${i}-${idx}" style="color:var(--text3);font-size:11px;">Inactive</span>`;
                const disabledAttr = t.active ? 'disabled' : '';
                const latestVersion = t.latest_version || t.version || 'Unknown';
                const updateBadge = t.update_available
                    ? `<span id="theme-up-badge-${i}-${idx}" style="color:var(--yellow);font-size:11px;font-weight:bold;">Update available</span>`
                    : `<span id="theme-up-badge-${i}-${idx}" style="color:var(--text3);font-size:11px;">Up to date</span>`;
                const updateDisabled = ''; // Always enable Update button to allow force reinstalling/cleaning malware
                const updateTitle = t.update_available
                    ? 'Update this theme'
                    : 'Force update/reinstall this theme (already at latest version)';
                
                return `
                <div class="plugin-item">
                    <div class="plugin-info">
                        <div class="plugin-name" title="${esc(t.name)}">${esc(t.name)}</div>
                        <div class="plugin-desc" title="${esc(t.description)}">${esc(t.description)}</div>
                        <div class="plugin-meta">Current: <span id="theme-ver-${i}-${idx}">v${esc(t.version)}</span> | Latest: <span id="theme-latest-${i}-${idx}">v${esc(latestVersion)}</span> | By ${esc(t.author)} | ${statusBadge} | ${updateBadge}</div>
                    </div>
                    <div class="plugin-toggle">
                        <button class="btn btn-sm btn-secondary" data-folder="${esc(t.folder)}" ${updateDisabled} title="${esc(updateTitle)}" id="btn-theme-up-${i}-${idx}" onclick="updateTheme(${i}, ${idx}, '${esc(t.folder)}')"><span class="dashicons dashicons-update-alt wp-admin-icon"></span> Update</button>
                        <button class="btn btn-sm btn-secondary" id="btn-theme-reinst-${i}-${idx}" onclick="reinstallTheme(${i}, ${idx}, '${esc(t.folder)}')"><span class="dashicons dashicons-update wp-admin-icon"></span> Reinstall</button>
                        <button class="btn btn-sm ${actionBtnClass}" ${disabledAttr} id="btn-theme-${i}-${idx}" onclick="activateTheme(${i}, ${idx}, '${esc(t.folder)}')">
                            ${actionText}
                        </button>
                        <button class="btn btn-sm btn-danger" ${disabledAttr} id="btn-theme-del-${i}-${idx}" onclick="deleteTheme(${i}, ${idx}, '${esc(t.folder)}')"><span class="dashicons dashicons-trash wp-admin-icon"></span> Delete</button>
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

async function updateTheme(siteIdx, themeIdx, folder, reloadAfter = true) {
    const s = allSites[siteIdx];
    if (s.locked) {
        toast('Website is under WordPress Lockdown. Please disable Lockdown before updating themes.', 'error');
        return false;
    }

    const btn = document.getElementById(`btn-theme-up-${siteIdx}-${themeIdx}`);
    const originalText = btn ? btn.textContent : 'Update';
    if (btn) {
        btn.disabled = true;
        btn.textContent = 'Updating...';
    }

    try {
        const fd = new FormData();
        fd.append('path', s.path);
        fd.append('theme_folder', folder);

        const r = await fetch(apiUrl('update_wp_theme'), { method: 'POST', body: fd });
        const d = await r.json();

        if (d.success) {
            toast(d.message || 'Theme updated successfully!', 'success');
            if (reloadAfter) {
                if (btn) {
                    btn.disabled = false;
                    btn.textContent = 'Update';
                }
                const newTheme = d.theme;
                if (newTheme) {
                    const verEl = document.getElementById(`theme-ver-${siteIdx}-${themeIdx}`);
                    if (verEl) verEl.textContent = `v${newTheme.version}`;
                    const latestEl = document.getElementById(`theme-latest-${siteIdx}-${themeIdx}`);
                    if (latestEl) latestEl.textContent = `v${newTheme.latest_version}`;
                    const badgeEl = document.getElementById(`theme-up-badge-${siteIdx}-${themeIdx}`);
                    if (badgeEl) badgeEl.innerHTML = `<span style="color:var(--text3);font-size:11px;">Up to date</span>`;
                }
            } else if (btn) {
                btn.textContent = 'Updated';
            }
            return true;
        } else {
            toast(d.error || 'Theme update failed.', 'error');
            if (btn) {
                btn.disabled = false;
                btn.textContent = originalText;
            }
            return false;
        }
    } catch (err) {
        toast('Connection error.', 'error');
        if (btn) {
            btn.disabled = false;
            btn.textContent = originalText;
        }
        return false;
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
            
            const container = document.getElementById('theme-list-' + siteIdx);
            if (container) {
                const items = container.querySelectorAll('.plugin-item');
                items.forEach((item, idx) => {
                    const statusEl = item.querySelector(`[id^="theme-status-${siteIdx}-"]`);
                    const actBtn = item.querySelector(`[id^="btn-theme-${siteIdx}-"]`);
                    const delBtn = item.querySelector(`[id^="btn-theme-del-${siteIdx}-"]`);
                    
                    if (actBtn) {
                        const isThisTheme = actBtn.id === `btn-theme-${siteIdx}-${themeIdx}`;
                        
                        actBtn.disabled = isThisTheme;
                        actBtn.className = `btn btn-sm ${isThisTheme ? 'btn-secondary' : 'btn-primary'}`;
                        actBtn.textContent = isThisTheme ? 'Active' : 'Activate';
                        
                        if (statusEl) {
                            statusEl.style.color = isThisTheme ? 'var(--green)' : 'var(--text3)';
                            statusEl.style.fontWeight = isThisTheme ? 'bold' : 'normal';
                            statusEl.textContent = isThisTheme ? 'Active' : 'Inactive';
                        }
                        
                        if (delBtn) {
                            delBtn.disabled = isThisTheme;
                        }
                    }
                });
            }
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
            if (btn) {
                btn.disabled = false;
                btn.textContent = originalText;
            }
            const newTheme = d.theme;
            if (newTheme) {
                const verEl = document.getElementById(`theme-ver-${siteIdx}-${themeIdx}`);
                if (verEl) verEl.textContent = `v${newTheme.version}`;
                const latestEl = document.getElementById(`theme-latest-${siteIdx}-${themeIdx}`);
                if (latestEl) latestEl.textContent = `v${newTheme.latest_version}`;
                const badgeEl = document.getElementById(`theme-up-badge-${siteIdx}-${themeIdx}`);
                if (badgeEl) badgeEl.innerHTML = `<span style="color:var(--text3);font-size:11px;">Up to date</span>`;
            }
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
            const themeItem = btn.closest('.plugin-item');
            if (themeItem) {
                themeItem.remove();
            }
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
                { key: 'restrict_files', title: 'Hạn chế quyền wp-config.php', desc: 'Chỉnh sửa phân quyền (Chmod) tệp wp-config.php về quyền đọc tối thiểu (0400/0600) để ngăn các tiến trình hoặc tài khoản khác trên máy chủ đọc trộm thông tin cấu hình.', icon: 'dashicons-admin-network' },
                { key: 'security_keys', title: 'Khóa bảo mật Salt Keys', desc: 'Thay đổi hoặc tạo mới các chuỗi Salt Keys ngẫu nhiên trong tệp wp-config.php để bảo mật mã hóa cookie và phiên đăng nhập của người dùng.', icon: 'dashicons-shield' },
                { key: 'db_prefix', title: 'Thay đổi tiền tố Database', desc: 'Đổi tên toàn bộ các bảng dữ liệu WordPress trong cơ sở dữ liệu MySQL và cập nhật tiền tố cấu hình $table_prefix trong tệp cấu hình wp-config.php.', icon: 'dashicons-database', promptInput: true },
                { key: 'rename_admin_user', title: 'Đổi tên tài khoản "admin"', desc: 'Thay đổi trực tiếp tên đăng nhập (user_login) của tài khoản quản trị từ mặc định "admin" sang tên mới trong bảng dữ liệu mysql wp_users.', icon: 'dashicons-admin-users', promptInput: true },
                { key: 'block_wp_config', title: 'Khóa truy cập wp-config.php', desc: 'Thêm cấu hình chặn truy cập trực tiếp tệp wp-config.php từ trình duyệt bên ngoài vào tệp cấu hình máy chủ .htaccess.', icon: 'dashicons-hidden' },
                { key: 'block_htaccess', title: 'Khóa truy cập .htaccess', desc: 'Thêm cấu hình chặn hoàn toàn việc đọc và ghi đè trực tiếp tệp cấu hình máy chủ .htaccess và tệp mật khẩu .htpasswd từ trình duyệt thông qua luật trong tệp .htaccess.', icon: 'dashicons-lock' },
                { key: 'block_xmlrpc', title: 'Chặn truy cập XML-RPC', desc: 'Thêm cấu hình từ chối và vô hiệu hóa quyền truy cập tệp xmlrpc.php vào tệp cấu hình .htaccess để chống các cuộc tấn công đoán mật khẩu hàng loạt và DDoS qua API XML-RPC.', icon: 'dashicons-dismiss' },
                { key: 'forbid_php_includes', title: 'Chặn chạy PHP trong wp-includes', desc: 'Tạo tệp cấu hình .htaccess mới trong thư mục wp-includes/ để chặn trình duyệt thực thi trực tiếp bất kỳ tệp tin .php nào tại đây.', icon: 'dashicons-media-code' },
                { key: 'forbid_php_uploads', title: 'Chặn chạy PHP trong wp-content/uploads', desc: 'Tạo tệp cấu hình .htaccess mới trong thư mục wp-content/uploads/ để ngăn chặn tin tặc thực thi các shell/backdoor PHP được tải lên.', icon: 'dashicons-format-image' },
                { key: 'disable_php_cache', title: 'Chặn chạy PHP trong wp-content/cache', desc: 'Tạo tệp cấu hình .htaccess mới trong thư mục wp-content/cache/ để chặn thực thi trực tiếp các tệp tin .php lưu trong bộ nhớ đệm.', icon: 'dashicons-archive' },
                { key: 'disallow_file_edit', title: 'Tắt chỉnh sửa file giao diện/plugin', desc: 'Thêm định nghĩa define("DISALLOW_FILE_EDIT", true); vào tệp cấu hình wp-config.php để ẩn hoàn toàn trình chỉnh sửa file giao diện/plugin trong trang admin WordPress.', icon: 'dashicons-edit' },
                { key: 'disable_scripts_concat', title: 'Tắt gộp script admin (CONCATENATE_SCRIPTS)', desc: 'Thêm định nghĩa define("CONCATENATE_SCRIPTS", false); vào tệp cấu hình wp-config.php để tắt tính năng gộp mã script của admin.', icon: 'dashicons-admin-generic' },
                { key: 'turn_off_pingbacks', title: 'Tắt tính năng Pingbacks', desc: 'Chạy truy vấn SQL tắt các tùy chọn default_pingback_flag và ping_status trong bảng wp_options và wp_posts của cơ sở dữ liệu để dừng cơ chế Pingbacks.', icon: 'dashicons-megaphone' },
                { key: 'bot_protection', title: 'Bảo vệ khỏi Bot xấu & Scrapers', desc: 'Thêm luật RewriteCond chặn các User-Agent của bot xấu (như curl, wget, scrapy...) truy cập vào website thông qua tệp cấu hình máy chủ .htaccess.', icon: 'dashicons-shield-alt' },
                { key: 'block_sensitive_files', title: 'Chặn file tài liệu nhạy cảm', desc: 'Thêm luật chặn đọc trực tiếp các tệp thông tin nhạy cảm (readme.html, license.txt, wp-config-sample.php) từ trình duyệt vào tệp cấu hình .htaccess.', icon: 'dashicons-media-document' },
                { key: 'block_author_scans', title: 'Chặn quét tên đăng nhập (Author scan)', desc: 'Thêm cấu hình điều hướng RewriteRule chặn và trả về lỗi 403 đối với mọi yêu cầu quét định danh tác giả (/?author=X) vào tệp cấu hình .htaccess.', icon: 'dashicons-search' },
                { key: 'block_directory_browsing', title: 'Tắt liệt kê thư mục công cộng', desc: 'Thêm dòng lệnh Options -Indexes vào đầu tệp cấu hình máy chủ .htaccess để tắt chế độ tự động hiển thị danh sách tệp tin trong các thư mục.', icon: 'dashicons-category' },
                { key: 'block_sensitive_extensions', title: 'Chặn tải file sao lưu (backup files)', desc: 'Thêm luật chặn tải xuống trực tiếp các định dạng tệp sao lưu nguy hiểm (.sql, .bak, .log, .sh, .ini, .dist) từ trình duyệt vào tệp cấu hình .htaccess.', icon: 'dashicons-portfolio' }
            ];
            
            const mainHtml = `<div style="display:flex; flex-direction:column; gap:8px;">` + measures.map((m, idx) => {
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
                    <div style="font-size: 18px; flex-shrink: 0; margin-right: 8px;"><span class="dashicons ${m.icon} wp-admin-icon"></span></div>
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
            
            let nginxHtml = '';
            if (sec.is_nginx) {
                const nginxConfigRules = `# ─── Nginx Hardening Configuration for WordPress ───

# 1. Block access to wp-config.php
location = /wp-config.php {
    deny all;
}

# 2. Block access to XML-RPC to prevent DDoS / Brute Force
location = /xmlrpc.php {
    deny all;
}

# 3. Block access to hidden files and .htaccess
location ~ /\\. {
    deny all;
}

# 4. Prevent direct PHP execution in wp-includes
location ~* ^/wp-includes/.*\\.php$ {
    deny all;
}

# 5. Prevent direct PHP execution in uploads
location ~* ^/wp-content/uploads/.*\\.php$ {
    deny all;
}

# 6. Block access to sensitive backup/log extensions
location ~* \\.(bak|config|sql|fla|psd|ini|log|sh|inc|swp|dist)$ {
    deny all;
}

# 7. Block access to sensitive documentation files
location ~* /(readme\\.html|license\\.txt|wp-config-sample\\.php) {
    deny all;
}

# 8. Block user/author scan queries
if ($query_string ~* "author=[0-9]") {
    return 403;
}`;

                nginxHtml = `
                <div style="margin-top:20px; border-top: 1px dashed var(--border); padding-top: 20px;">
                    <div class="notice notice-info" style="display:flex; flex-direction:column; gap:6px; margin-bottom:12px; line-height:1.4;">
                        <strong><span class="dashicons dashicons-admin-site-alt3 wp-admin-icon"></span> Phát hiện website đang sử dụng Nginx</strong>
                        <span>Hệ thống nhận diện máy chủ web của bạn đang chạy Nginx. Do Nginx không hỗ trợ tệp <code>.htaccess</code>, các luật bảo mật liên quan đến cấu hình web không thể tự động áp dụng.</span>
                        <span style="font-weight:bold;">Vui lòng copy và dán các dòng cấu hình sau vào tệp cấu hình Nginx (server block) của website:</span>
                    </div>
                    <div style="position:relative;">
                        <textarea readonly style="width:100%; height:250px; background:var(--bg); border:1px solid var(--border); border-radius:6px; color:var(--text2); font-family:monospace; font-size:11px; padding:10px 12px; resize:vertical; outline:none;" id="nginx-config-${i}">${esc(nginxConfigRules)}</textarea>
                        <button class="btn btn-sm btn-secondary" style="position:absolute; top:10px; right:10px;" onclick="copyNginxConfig(${i})"><span class="dashicons dashicons-clipboard wp-admin-icon"></span> Copy</button>
                    </div>
                </div>
                `;
            }
            
            container.innerHTML = mainHtml + nginxHtml;
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

/* ─── Open phpMyAdmin ─── */
function openPhpMyAdmin(i) {
    const s = allSites[i];
    if (!s || !s.db_name) {
        toast('Database name not found for this site.', 'error');
        return;
    }

    let domain = '';
    if (s.path) {
        const parts = s.path.split('/domains/');
        if (parts.length > 1) {
            domain = parts[1].split('/')[0];
        }
    }
    if (!domain) {
        domain = s.domain || '';
    }

    const form = document.createElement('form');
    form.method = 'POST';
    form.action = '/CMD_PMA_LOGIN';
    form.target = '_blank';

    const dbInput = document.createElement('input');
    dbInput.type = 'hidden';
    dbInput.name = 'name';
    dbInput.value = s.db_name;
    form.appendChild(dbInput);

    const domainInput = document.createElement('input');
    domainInput.type = 'hidden';
    domainInput.name = 'domain';
    domainInput.value = domain;
    form.appendChild(domainInput);

    document.body.appendChild(form);
    form.submit();
    document.body.removeChild(form);
}

/* ─── Render ─── */
function renderSites(sites) {
    // Stop all active log pollings before rendering new lists
    if (typeof logPollStates !== 'undefined') {
        Object.keys(logPollStates).forEach(idx => {
            stopLogPolling(parseInt(idx));
        });
        logPollStates = {};
    }

    const cnt = document.getElementById('sites-container');
    document.getElementById('count-label').textContent =
        sites.length + ' installation' + (sites.length!==1?'s':'') + ' total';

    if (!sites.length) {
        cnt.innerHTML = `<div class="empty-state">
            <div class="icon"><span class="dashicons dashicons-portfolio wp-admin-icon" style="font-size:40px; width:40px; height:40px;"></span></div>
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
            ? `<span class="badge badge-yellow" id="hb-lock-${i}"><span class="dashicons dashicons-lock wp-admin-icon"></span> Lockdown</span>`
            : `<span class="badge badge-gray" id="hb-lock-${i}"><span class="dashicons dashicons-unlock wp-admin-icon"></span> Unlocked</span>`;
        const pathShort = s.path.replace('/home/'+DA_USER+'/', '~/');
        const shotSrc   = thumbUrl(s.siteurl);

        return `<div class="site-card">
            <!-- Card header -->
            <div class="card-header" onclick="toggleCard(${i})">
                <!-- Thumbnail -->
                <div class="site-thumb">
                    <div class="thumb-loader" id="tl-${i}"><span class="dashicons dashicons-admin-site-alt3 wp-admin-icon"></span></div>
                    <img src="${esc(shotSrc)}"
                          alt="screenshot"
                          onload="document.getElementById('tl-${i}').classList.add('hidden')"
                          onerror="this.style.opacity='.2'; document.getElementById('tl-${i}').classList.add('hidden')">
                </div>
                <!-- Info -->
                <div class="site-info">
                    <div class="site-name">${esc(s.blogname)}</div>
                    <div class="site-url"><a href="${esc(s.siteurl)}" target="_blank" onclick="event.stopPropagation()">${esc(s.siteurl)}</a></div>
                    <div class="site-path" onclick="event.stopPropagation()" title="${esc(pathShort)}"><span class="dashicons dashicons-category wp-admin-icon"></span> ${esc(pathShort)}</div>
                </div>
                <!-- Quick actions -->

                <!-- Badges -->
                <div class="badges" style="margin-left:auto;">
                    ${statusBadge}
                    <span class="badge badge-blue">WP ${esc(s.version)}</span>
                    ${lockBadge}
                </div>
                <span class="chevron" id="cv-${i}">▶</span>
            </div>

            <!-- Action row (Always visible) -->
            <div class="card-action-row" onclick="event.stopPropagation()">
                <button class="btn btn-blue btn-sm" onclick="doMagicLogin(${i})"><span class="dashicons dashicons-unlock wp-admin-icon"></span> Magic Login</button>
                <div class="sep"></div>
                <button class="btn btn-secondary btn-sm" onclick="openCloneModal(${i})"><span class="dashicons dashicons-admin-page wp-admin-icon"></span> Clone Website</button>
                <button class="btn btn-secondary btn-sm" onclick="openFileManager(${i})"><span class="dashicons dashicons-portfolio wp-admin-icon"></span> File Manager</button>
                <button class="btn btn-secondary btn-sm" onclick="openPhpMyAdmin(${i})"><span class="dashicons dashicons-database wp-admin-icon"></span> phpMyAdmin</button>
            </div>

            <!-- Card body (expanded) -->
            <div class="card-body" id="cb-${i}">
                <!-- Big screenshot strip -->
                <div style="display:none;" class="card-screenshot-bar">
                    <img id="shot-big-${i}" src="${esc(shotSrc)}"
                          alt="Website screenshot"
                          onerror="this.style.opacity='.1'">
                    <div class="shot-overlay"></div>
                    <span class="shot-label"><span class="dashicons dashicons-camera wp-admin-icon"></span> Live screenshot via thum.io</span>
                    <div class="shot-refresh">
                        <button class="btn btn-secondary btn-sm" onclick="refreshShot(${i})"><span class="dashicons dashicons-update wp-admin-icon"></span> Refresh</button>
                    </div>
                </div>

                <!-- Tabs headers -->
                <div class="card-tabs" onclick="event.stopPropagation()">
                    <button class="tab-btn active" onclick="switchTab(${i}, 'details', event)"><span class="dashicons dashicons-info wp-admin-icon"></span> Overview Details</button>
                    <button class="tab-btn" onclick="switchTab(${i}, 'security', event)"><span class="dashicons dashicons-shield wp-admin-icon"></span> Security & Protection</button>
                    <button class="tab-btn" onclick="switchTab(${i}, 'plugins', event)"><span class="dashicons dashicons-admin-plugins wp-admin-icon"></span> Plugins</button>
                    <button class="tab-btn" onclick="switchTab(${i}, 'themes', event)"><span class="dashicons dashicons-admin-appearance wp-admin-icon"></span> Themes</button>
                    <button class="tab-btn" onclick="switchTab(${i}, 'logs', event)"><span class="dashicons dashicons-list-view wp-admin-icon"></span> Task & Logs</button>
                </div>

                <!-- Tab 1: Overview Details -->
                <div class="card-tab-content active" id="tab-content-${i}-details">
                    <div class="card-sec-title"><span><span class="dashicons dashicons-info wp-admin-icon"></span> Installation Details</span></div>
                    <div class="card-details" style="grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));">
                        <div class="detail-item"><label>Domain</label><div class="val">${esc(s.domain)}</div></div>
                        <div class="detail-item"><label>Sub-path</label><div class="val">${esc(s.subdir||'(root)')}</div></div>
                        <div class="detail-item"><label>Database</label><div class="val">${esc(s.db_name)}</div></div>
                        <div class="detail-item"><label>DB User</label><div class="val">${esc(s.db_user||'—')}</div></div>
                        <div class="detail-item"><label>DB Prefix</label><div class="val">${esc(s.db_prefix)}</div></div>
                        <div class="detail-item"><label>WP Version</label><div class="val">${esc(s.version)}</div></div>
                    </div>
                    <div class="lock-section" onclick="event.stopPropagation()">
                        <div class="lock-status-label"><span class="dashicons dashicons-wordpress wp-admin-icon"></span> WordPress Core (Phiên bản WordPress hiện tại: <strong>v${esc(s.version)}</strong>)
                        </div>
                        <button class="btn btn-primary btn-sm" id="btn-core-update-${i}" onclick="updateCore(${i})"><span class="dashicons dashicons-update-alt wp-admin-icon"></span> Update Core</button>
                    </div>
                    <div class="lock-section" style="margin-top: 12px; border-top: 1px dashed var(--border); padding-top: 12px;" onclick="event.stopPropagation()">
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
                    <!-- Danger Zone / Delete Website -->
                    <div class="lock-section" style="margin-top: 24px; border-top: 1px solid rgba(248,81,73,0.3); padding-top: 16px;" onclick="event.stopPropagation()">
                        <div class="lock-status-label" style="color: var(--red); font-weight: 500;"><span class="dashicons dashicons-trash wp-admin-icon"></span> Gỡ bỏ website này hoàn toàn khỏi hệ thống (Không thể hoàn tác)
                        </div>
                        <button class="btn btn-danger btn-sm" onclick="openDeleteModal(${i})"><span class="dashicons dashicons-trash wp-admin-icon"></span> Delete Website</button>
                    </div>
                </div>

                <!-- Tab 1.5: Security & Protection -->
                <div class="card-tab-content" id="tab-content-${i}-security">
                    <div class="card-sec-title">
                        <span><span class="dashicons dashicons-shield wp-admin-icon"></span> Security Hardening Status</span>
                        <button class="btn btn-secondary btn-sm" onclick="loadSecurity(${i})"><span class="dashicons dashicons-update wp-admin-icon"></span> Scan & Refresh</button>
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
                        <span>
                            <button class="store-tab-btn active" id="plugin-store-tab-${i}-installed" onclick="switchStoreTab(${i}, 'plugins', 'installed', event)"><span class="dashicons dashicons-admin-plugins wp-admin-icon"></span> Installed Plugins</button>
                            <span class="inline-store-tabs">
                                <button class="store-tab-btn" id="plugin-store-tab-${i}-popular" onclick="switchStoreTab(${i}, 'plugins', 'popular', event)"><span class="dashicons dashicons-star-filled wp-admin-icon"></span> Popular</button>
                                <button class="store-tab-btn" id="plugin-store-tab-${i}-premium" onclick="switchStoreTab(${i}, 'plugins', 'premium', event)"><span class="dashicons dashicons-products wp-admin-icon"></span> Premium</button>
                            </span>
                        </span>
                        <div style="display:flex; gap:8px;" id="plugin-installed-actions-${i}">
                            <button class="btn btn-primary btn-sm" id="btn-plug-upall-${i}" onclick="updateAllPlugins(${i})"><span class="dashicons dashicons-upload wp-admin-icon"></span> Update All</button>
                            <button class="btn btn-secondary btn-sm" onclick="loadPlugins(${i})"><span class="dashicons dashicons-update wp-admin-icon"></span> Refresh</button>
                        </div>
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
                        <span>
                            <button class="store-tab-btn active" id="theme-store-tab-${i}-installed" onclick="switchStoreTab(${i}, 'themes', 'installed', event)"><span class="dashicons dashicons-admin-appearance wp-admin-icon"></span> Installed Themes</button>
                            <span class="inline-store-tabs">
                                <button class="store-tab-btn" id="theme-store-tab-${i}-popular" onclick="switchStoreTab(${i}, 'themes', 'popular', event)"><span class="dashicons dashicons-star-filled wp-admin-icon"></span> Popular</button>
                                <button class="store-tab-btn" id="theme-store-tab-${i}-premium" onclick="switchStoreTab(${i}, 'themes', 'premium', event)"><span class="dashicons dashicons-products wp-admin-icon"></span> Premium</button>
                            </span>
                        </span>
                        <div style="display:flex; gap:8px;" id="theme-installed-actions-${i}">
                            <button class="btn btn-primary btn-sm" id="btn-theme-upall-${i}" onclick="updateAllThemes(${i})"><span class="dashicons dashicons-upload wp-admin-icon"></span> Update All</button>
                            <button class="btn btn-secondary btn-sm" onclick="loadThemes(${i})"><span class="dashicons dashicons-update wp-admin-icon"></span> Refresh</button>
                        </div>
                    </div>
                    <div class="plugin-list" id="theme-list-${i}">
                        <div style="color:var(--text3);font-size:12px;padding:12px;text-align:center;">
                            Clicking Themes tab or Refresh will load themes...
                        </div>
                    </div>
                </div>
                <!-- Tab 5: Logs -->
                <div class="card-tab-content" id="tab-content-${i}-logs">
                    <div class="card-sec-title">
                        <span><span class="dashicons dashicons-list-view wp-admin-icon"></span> Trình xem nhật ký hoạt động (Logs Viewer)</span>
                    </div>
                    <div style="background: var(--bg3); border: 1px solid var(--border); border-radius: 8px; padding: 12px; margin-top: 10px; display: flex; flex-direction: column; gap: 8px;" onclick="event.stopPropagation()">
                        <div style="display: flex; gap: 8px; align-items: center; flex-wrap: wrap;">
                            <select id="log-type-${i}" class="form-control" style="width: 220px; padding: 4px 8px; font-size: 11px; display: inline-block;" onchange="onLogTypeChanged(${i})">
                                <option value="wp_debug">WordPress Debug (wp-content/debug.log)</option>
                                <option value="access">Access Log (Nginx/Apache)</option>
                                <option value="error">Error Log (Nginx/Apache)</option>
                                <option value="php_error">PHP Error Log</option>
                            </select>
                            
                            <input type="text" id="log-search-${i}" placeholder="Tìm kiếm log..." class="toolbar-search" style="width: 160px; padding: 4px 8px; font-size: 11px;" oninput="onLogFilterChanged(${i})">
                            
                            <select id="log-lines-${i}" class="form-control" style="width: 95px; padding: 4px 8px; font-size: 11px; display: inline-block;" onchange="onLogFilterChanged(${i})">
                                <option value="50">50 dòng</option>
                                <option value="100" selected>100 dòng</option>
                                <option value="200">200 dòng</option>
                                <option value="500">500 dòng</option>
                            </select>
                            
                            <select id="log-time-${i}" class="form-control" style="width: 110px; padding: 4px 8px; font-size: 11px; display: inline-block;" onchange="onLogFilterChanged(${i})">
                                <option value="0" selected>Mọi lúc</option>
                                <option value="60">1 phút qua</option>
                                <option value="300">5 phút qua</option>
                                <option value="900">15 phút qua</option>
                                <option value="1800">30 phút qua</option>
                                <option value="3600">1 giờ qua</option>
                                <option value="14400">4 giờ qua</option>
                                <option value="43200">12 giờ qua</option>
                                <option value="86400">24 giờ qua</option>
                            </select>

                            <select id="log-refresh-${i}" class="form-control" style="width: 95px; padding: 4px 8px; font-size: 11px; display: inline-block;" onchange="onLogRefreshIntervalChanged(${i})">
                                <option value="1">1s</option>
                                <option value="3" selected>3s</option>
                                <option value="5">5s</option>
                                <option value="15">15s</option>
                                <option value="30">30s</option>
                                <option value="60">1p</option>
                            </select>
                            
                            <button class="btn btn-sm btn-secondary" id="btn-log-pause-${i}" onclick="toggleLogPause(${i})" style="padding: 4px 10px; font-size: 11px;"><span class="dashicons dashicons-controls-pause wp-admin-icon"></span> Tạm dừng</button>
                            <button class="btn btn-sm btn-danger" id="btn-log-clear-${i}" onclick="clearLogFile(${i})" style="padding: 4px 10px; font-size: 11px;"><span class="dashicons dashicons-trash wp-admin-icon"></span> Xóa log</button>
                        </div>
                        
                        <div id="log-path-info-${i}" style=" padding-top: 8px; color: var(--text2); font-size: 11px; font-family: ui-monospace, 'SFMono-Regular', Consolas, monospace; word-break: break-all;">
                            Đang tải log từ: ...
                        </div>

                        <div id="log-filetype-filters-${i}" style="display: none; align-items: center; gap: 8px; flex-wrap: wrap; font-size: 11px; color: var(--text2); border-top: 1px dashed var(--border); padding-top: 8px;">
                            <span style="font-weight: bold;">Lọc loại file:</span>
                            <label style="display: inline-flex; align-items: center; gap: 3px; cursor: pointer;"><input type="checkbox" name="log-filetype-${i}" value="php_backend" checked onchange="onLogFilterChanged(${i})"> PHP Backend</label>
                            <label style="display: inline-flex; align-items: center; gap: 3px; cursor: pointer;"><input type="checkbox" name="log-filetype-${i}" value="php" checked onchange="onLogFilterChanged(${i})"> PHP</label>
                            <label style="display: inline-flex; align-items: center; gap: 2px; cursor: pointer;"><input type="checkbox" name="log-filetype-${i}" value="html" checked onchange="onLogFilterChanged(${i})"> HTML</label>
                            <label style="display: inline-flex; align-items: center; gap: 2px; cursor: pointer;"><input type="checkbox" name="log-filetype-${i}" value="css" checked onchange="onLogFilterChanged(${i})"> CSS</label>
                            <label style="display: inline-flex; align-items: center; gap: 2px; cursor: pointer;"><input type="checkbox" name="log-filetype-${i}" value="js" checked onchange="onLogFilterChanged(${i})"> JS</label>
                            <label style="display: inline-flex; align-items: center; gap: 2px; cursor: pointer;"><input type="checkbox" name="log-filetype-${i}" value="image" checked onchange="onLogFilterChanged(${i})"> Image</label>
                            <label style="display: inline-flex; align-items: center; gap: 2px; cursor: pointer;"><input type="checkbox" name="log-filetype-${i}" value="font" checked onchange="onLogFilterChanged(${i})"> Font</label>
                            <label style="display: inline-flex; align-items: center; gap: 2px; cursor: pointer;"><input type="checkbox" name="log-filetype-${i}" value="other" checked onchange="onLogFilterChanged(${i})"> Khác</label>
                        </div>
                    </div>
                    
                    <div style="position: relative;" onclick="event.stopPropagation()">
                        <textarea id="log-terminal-${i}" readonly style="width:100%; height:420px; background:#05070a; border:1px solid var(--border); border-radius:6px; color:#39ff14; font-family:Consolas, 'Fira Code', Monaco, 'Courier New', monospace; font-size:11.5px; padding:12px; margin-top:10px; resize:vertical; outline:none; line-height:1.4; white-space: pre; overflow-wrap: normal; overflow-x: auto;" placeholder="[Hệ thống] Nhật ký đang được tải..."></textarea>
                    </div>
                </div>

            </div>
        </div>`;
    }).join('');
}

/* Premium/Popular install lists inside Plugins and Themes tabs */
let premiumListCache = null;

function storePrefix(itemType) {
    return itemType === 'plugins' ? 'plugin' : 'theme';
}

function storeListId(itemType, siteIdx) {
    return `${storePrefix(itemType)}-list-${siteIdx}`;
}

function setStoreMode(siteIdx, itemType, group) {
    const prefix = storePrefix(itemType);
    document.querySelectorAll(`#cb-${siteIdx} .store-tab-btn[id^="${prefix}-store-tab-"]`).forEach(el => el.classList.remove('active'));
    const active = document.getElementById(`${prefix}-store-tab-${siteIdx}-${group}`);
    if (active) active.classList.add('active');

    const actions = document.getElementById(`${prefix}-installed-actions-${siteIdx}`);
    if (actions) actions.style.display = group === 'installed' ? 'flex' : 'none';
}

function switchStoreTab(siteIdx, itemType, group, event) {
    if (event) event.stopPropagation();
    setStoreMode(siteIdx, itemType, group);

    if (group === 'installed') {
        if (itemType === 'plugins') {
            loadPlugins(siteIdx);
        } else {
            loadThemes(siteIdx);
        }
        return;
    }

    loadPremiumItems(siteIdx, itemType, group);
}

async function fetchPremiumList() {
    if (premiumListCache) return premiumListCache;
    const r = await fetch(apiUrl('get_premium_list'));
    const d = await r.json();
    if (!d.success) {
        throw new Error(d.error || 'Cannot load install list.');
    }
    premiumListCache = d;
    return d;
}

async function loadPremiumItems(siteIdx, itemType, group) {
    const container = document.getElementById(storeListId(itemType, siteIdx));
    if (!container) return;

    const label = itemType === 'plugins'
        ? (group === 'popular' ? 'popular plugins' : 'premium plugins')
        : (group === 'popular' ? 'popular themes' : 'premium themes');
    container.innerHTML = `<div style="color:var(--text3);font-size:12px;padding:12px;text-align:center;">Loading ${label}...</div>`;

    try {
        const data = await fetchPremiumList();
        renderPremiumItems(siteIdx, itemType, group, data);
    } catch (err) {
        container.innerHTML = `<div style="color:var(--red);font-size:12px;padding:12px;text-align:center;">${esc(err.message || 'Cannot load install list.')}</div>`;
    }
}

function renderPremiumItems(siteIdx, itemType, group, data) {
    const container = document.getElementById(storeListId(itemType, siteIdx));
    if (!container) return;

    const allItems = data[itemType] || [];
    const explicitKey = `${group}_${itemType}`;
    const items = Array.isArray(data[explicitKey])
        ? data[explicitKey]
        : allItems.filter(item => group === 'popular' ? item.type === 'wporg' : item.type !== 'wporg');
    const isPlugin = itemType === 'plugins';
    const emptyText = isPlugin
        ? (group === 'popular' ? 'No popular plugins configured.' : 'No premium plugins configured.')
        : (group === 'popular' ? 'No popular themes configured.' : 'No premium themes configured.');

    if (!items.length) {
        container.innerHTML = `<div style="color:var(--text3);font-size:12px;padding:12px;text-align:center;">${emptyText}</div>`;
        return;
    }

    container.innerHTML = items.map((item, idx) => {
        const isWpOrg = item.type === 'wporg';
        const statusBadge = `<span style="color:var(--text3);font-size:11px;">${isWpOrg ? 'WordPress.org' : 'Premium ZIP'}</span>`;
        const sourceMeta = isWpOrg ? `Slug: ${esc(item.slug || '')}` : `File: ${esc(item.file || '')}`;
        const idVal = isWpOrg ? item.slug : item.file;
        const activateTheme = !isPlugin ? `
                            <label style="font-size: 10px; color: var(--text2); display: inline-flex; align-items: center; gap: 3px; cursor: pointer;">
                                <input type="checkbox" id="premium-activate-theme-${siteIdx}-${group}-${idx}" checked style="accent-color: var(--blue);"> Activate
                            </label>` : '';
        const themeIdxArg = isPlugin ? 'null' : `'${group}-${idx}'`;

        return `
                <div class="plugin-item">
                    <div class="plugin-info">
                        <div class="plugin-name" title="${esc(item.name)}">${esc(item.name)}</div>
                        <div class="plugin-desc" title="${esc(item.description)}">${esc(item.description || 'Không có mô tả.')}</div>
                        <div class="plugin-meta">${sourceMeta} | ${statusBadge}</div>
                    </div>
                    <div class="plugin-toggle">
                        ${activateTheme}
                        <button class="btn btn-sm btn-primary" id="btn-premium-inst-${siteIdx}-${isPlugin ? 'plug' : 'theme'}-${group}-${idx}" onclick="installPremiumItem(${siteIdx}, '${itemType}', '${item.type}', '${escJsArg(idVal)}', '${escJsArg(item.name)}', this, ${themeIdxArg})"><span class="dashicons dashicons-download wp-admin-icon"></span> Install</button>
                    </div>
                </div>`;
    }).join('');
}
async function installPremiumItem(siteIdx, itemType, itemSource, idVal, itemName, btnEl, themeIdx = null) {
    const s = allSites[siteIdx];
    if (s.locked) {
        toast('🔒 Website đang bị khóa (WP Lock). Vui lòng tắt WP Lock để cài đặt.', 'error');
        return;
    }
    
    const originalText = btnEl.textContent;
    btnEl.disabled = true;
    btnEl.textContent = '⏳ Cài đặt...';
    
    let activate = false;
    if (itemType === 'themes' && themeIdx !== null) {
        const chk = document.getElementById(`premium-activate-theme-${siteIdx}-${themeIdx}`);
        if (chk && chk.checked) {
            activate = true;
        }
    }
    
    try {
        const fd = new FormData();
        fd.append('path', s.path);
        fd.append('item_type', itemType);
        fd.append('item_source', itemSource);
        if (itemSource === 'wporg') {
            fd.append('slug', idVal);
        } else {
            fd.append('file', idVal);
        }
        if (activate) {
            fd.append('activate', 'true');
        }
        
        const r = await fetch(apiUrl('install_premium_item'), { method: 'POST', body: fd });
        const text = await r.text();
        let d;
        try {
            d = JSON.parse(text);
        } catch (e) {
            console.error("Non-JSON response:", text);
            toast('Lỗi phản hồi từ server: ' + text.substring(0, 150), 'error');
            btnEl.disabled = false;
            btnEl.textContent = originalText;
            return;
        }
        
        if (d.success) {
            toast(`✅ Cài đặt ${itemName} thành công!`, 'success');
            btnEl.innerHTML = '<span class="dashicons dashicons-yes wp-admin-icon"></span> Đã cài đặt';
            btnEl.className = 'btn btn-sm btn-secondary';
            
            setStoreMode(siteIdx, itemType, 'installed');
            if (itemType === 'plugins') {
                loadPlugins(siteIdx);
            } else {
                loadThemes(siteIdx);
            }
        } else {
            toast(d.error || 'Cài đặt thất bại.', 'error');
            btnEl.disabled = false;
            btnEl.textContent = originalText;
        }
    } catch (err) {
        toast('Lỗi kết nối mạng: ' + (err.message || 'Không rõ nguyên nhân'), 'error');
        btnEl.disabled = false;
        btnEl.textContent = originalText;
    }
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
        `<div class="empty-state"><div class="icon"><span class="dashicons dashicons-update wp-admin-icon dashicons-spin" style="font-size: 40px; width: 40px; height: 40px;"></span></div><strong>${scan?'Scanning directories…':'Loading…'}</strong></div>`;
    try {
        const r = await fetch(apiUrl(scan?'scan':'list'));
        const d = await r.json();
        if (d.success) { allSites = d.sites; renderSites(allSites); }
        else toast(d.error||'Failed.', 'error');
    } catch { toast('Cannot connect to backend.', 'error'); }
}

async function triggerScan() {
    const btn = document.getElementById('btn-scan');
    btn.disabled=true; btn.innerHTML='<span class="dashicons dashicons-update wp-admin-icon dashicons-spin"></span> Scanning…';
    await fetchSites(true);
    btn.disabled=false; btn.innerHTML='<span class="dashicons dashicons-update wp-admin-icon"></span> Scan Hosting';
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

/* ─── Clone Website Handlers ─── */
async function loadCloneDomains() {
    const sel = document.getElementById('clone-domain');
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

function openCloneModal(i) {
    const s = allSites[i];
    document.getElementById('clone-src-path').value = s.path;
    document.getElementById('clone-src-display').textContent = s.path;
    openModal('modal-clone');
    loadCloneDomains();
    genPass('clone-dbpass');
    
    // Suggest target database details
    const randomStr = Math.random().toString(36).substring(2, 6);
    document.getElementById('clone-dbname').value = 'cln' + randomStr;
    document.getElementById('clone-dbuser').value = 'u' + randomStr;
}

async function executeClone(e) {
    e.preventDefault();
    const btn = document.getElementById('btn-clone-submit');
    btn.disabled = true; btn.textContent = 'Creating database…';
    try {
        const db = await createDB(
            document.getElementById('clone-dbname').value,
            document.getElementById('clone-dbuser').value,
            document.getElementById('clone-dbpass').value
        );
        toast('1/2 Target Database created.', 'success');
        
        btn.textContent = 'Cloning files & database…';
        
        const fd = new FormData();
        fd.append('action', 'clone');
        fd.append('src_path', document.getElementById('clone-src-path').value);
        fd.append('domain', document.getElementById('clone-domain').value);
        fd.append('subdir', document.getElementById('clone-subdir').value);
        fd.append('db_name', db.db_name);
        fd.append('db_user', db.db_user);
        fd.append('db_pass', document.getElementById('clone-dbpass').value);
        fd.append('protocol', document.getElementById('clone-protocol').value);
        
        const r = await fetch(apiUrl(), {
            method: 'POST',
            body: fd
        });
        const d = await r.json();
        if (d.success) {
            toast(d.message || 'Clone website thành công!', 'success');
            closeModal('modal-clone');
            fetchSites(false);
        } else {
            toast(d.error || 'Clone failed.', 'error');
        }
    } catch (err) {
        toast(err.message || 'Error.', 'error');
    } finally {
        btn.disabled = false;
        btn.textContent = 'Clone Website';
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
        if(d.success){
            log(d.message,'ok');
            log('✅ Done! Tự động tải lại trang sau 3 giây...','ok');
            let sec = 3;
            const countdown = setInterval(() => {
                sec--;
                if (sec > 0) {
                    log('⏳ Đang tải lại sau ' + sec + 's...','ok');
                } else {
                    clearInterval(countdown);
                    location.reload(true);
                }
            }, 1000);
        }
        else log('Error: '+d.error,'err');
    } catch(e){log('Connection error: '+e.message,'err');}
    finally{document.getElementById('btn-update-done').disabled=false;}
}
<?php endif; ?>

/* ─── Show Logs Handlers ─── */
function openLogsModal() {
    openModal('modal-logs');
    refreshLogs();
}

async function refreshLogs() {
    const txt = document.getElementById('logs-textarea');
    txt.value = 'Đang tải nhật ký...';
    try {
        const r = await fetch(apiUrl('get_logs'));
        const d = await r.json();
        if (d.success) {
            txt.value = d.logs || '(Không có dữ liệu nhật ký hoặc file nhật ký trống)';
        } else {
            txt.value = 'Lỗi: ' + (d.error || 'Không thể tải nhật ký.');
        }
    } catch (err) {
        txt.value = 'Lỗi kết nối: ' + err.message;
    }
    // Auto scroll to bottom
    txt.scrollTop = txt.scrollHeight;
}

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
