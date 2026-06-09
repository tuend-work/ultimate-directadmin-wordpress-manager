<?php
/**
 * Ultimate DirectAdmin WordPress Manager
 * Front-end GUI Template
 */

$username = getenv('USERNAME') ?: getenv('USER') ?: 'user';
$isAdmin = is_admin_user();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ultimate WordPress Manager</title>
    <!-- Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
    
    <style>
        :root {
            --bg-color: #0b0c10;
            --surface-color: rgba(20, 24, 38, 0.65);
            --border-color: rgba(255, 255, 255, 0.06);
            --text-primary: #f8fafc;
            --text-secondary: #94a3b8;
            
            --primary: #6366f1;
            --primary-glow: rgba(99, 102, 241, 0.35);
            --primary-hover: #4f46e5;
            
            --accent-purple: #a855f7;
            --accent-purple-glow: rgba(168, 85, 247, 0.35);
            
            --success: #10b981;
            --success-glow: rgba(16, 185, 129, 0.2);
            --danger: #f43f5e;
            --danger-glow: rgba(244, 63, 94, 0.2);
            --warning: #f59e0b;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            user-select: none;
        }

        body {
            font-family: 'Outfit', sans-serif;
            background: radial-gradient(circle at 50% 0%, #1e1b4b 0%, #09090b 100%);
            color: var(--text-primary);
            min-height: 100vh;
            overflow-x: hidden;
            padding: 2.5rem;
            line-height: 1.5;
        }

        /* Glassmorphism panel styling */
        .glass-panel {
            background: var(--surface-color);
            backdrop-filter: blur(16px);
            -webkit-backdrop-filter: blur(16px);
            border: 1px solid var(--border-color);
            border-radius: 16px;
            box-shadow: 0 10px 40px -10px rgba(0, 0, 0, 0.5);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1.5rem 2rem;
            margin-bottom: 2rem;
        }

        .header-logo {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .logo-icon {
            background: linear-gradient(135deg, var(--primary) 0%, var(--accent-purple) 100%);
            width: 48px;
            height: 48px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 0 20px var(--primary-glow);
            font-size: 1.5rem;
        }

        .header-title h1 {
            font-size: 1.6rem;
            font-weight: 700;
            background: linear-gradient(to right, #ffffff, #c084fc);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .header-title p {
            font-size: 0.85rem;
            color: var(--text-secondary);
        }

        .header-actions {
            display: flex;
            gap: 1rem;
            align-items: center;
        }

        /* Stats Section */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            padding: 1.5rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            position: relative;
            overflow: hidden;
        }

        .stat-card:hover {
            transform: translateY(-4px);
            border-color: rgba(255, 255, 255, 0.12);
        }

        .stat-card::after {
            content: '';
            position: absolute;
            top: 0;
            right: 0;
            width: 80px;
            height: 80px;
            background: radial-gradient(circle, var(--primary-glow) 0%, transparent 70%);
            opacity: 0.5;
            pointer-events: none;
        }

        .stat-info h3 {
            font-size: 0.9rem;
            text-transform: uppercase;
            color: var(--text-secondary);
            font-weight: 500;
            letter-spacing: 0.05em;
        }

        .stat-info p {
            font-size: 2.2rem;
            font-weight: 700;
            margin-top: 0.25rem;
        }

        .stat-icon {
            font-size: 2.5rem;
            opacity: 0.85;
            background: linear-gradient(135deg, #f8fafc, #94a3b8);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        /* Main Content Panel */
        .main-panel {
            padding: 2rem;
        }

        .panel-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
            flex-wrap: wrap;
            gap: 1rem;
        }

        .panel-title h2 {
            font-size: 1.3rem;
            font-weight: 600;
        }

        .search-input-wrapper {
            position: relative;
        }

        .search-input {
            background: rgba(15, 18, 30, 0.8);
            border: 1px solid var(--border-color);
            color: var(--text-primary);
            padding: 0.6rem 1rem 0.6rem 2.5rem;
            border-radius: 8px;
            width: 280px;
            font-family: inherit;
            transition: all 0.3s;
        }

        .search-input:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 10px rgba(99, 102, 241, 0.2);
        }

        .search-icon {
            position: absolute;
            left: 0.8rem;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-secondary);
            pointer-events: none;
        }

        /* Modern Table styling */
        .table-responsive {
            overflow-x: auto;
            width: 100%;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            text-align: left;
        }

        th {
            padding: 1rem;
            color: var(--text-secondary);
            font-size: 0.85rem;
            font-weight: 600;
            border-bottom: 1px solid var(--border-color);
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        td {
            padding: 1.25rem 1rem;
            border-bottom: 1px solid rgba(255, 255, 255, 0.03);
            font-size: 0.95rem;
            vertical-align: middle;
        }

        tr:hover td {
            background: rgba(255, 255, 255, 0.015);
        }

        .site-title-cell {
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .site-avatar {
            width: 40px;
            height: 40px;
            border-radius: 8px;
            background: rgba(255, 255, 255, 0.05);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
            font-weight: 700;
            color: var(--primary);
            border: 1px solid rgba(255, 255, 255, 0.05);
        }

        .site-meta h4 {
            font-weight: 600;
            font-size: 1rem;
        }

        .site-meta a {
            color: var(--text-secondary);
            font-size: 0.8rem;
            text-decoration: none;
            transition: color 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 0.25rem;
        }

        .site-meta a:hover {
            color: var(--primary);
            text-decoration: underline;
        }

        .badge {
            display: inline-flex;
            align-items: center;
            padding: 0.25rem 0.6rem;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 600;
            gap: 0.35rem;
        }

        .badge-success {
            background: var(--success-glow);
            color: var(--success);
            border: 1px solid rgba(16, 185, 129, 0.2);
        }

        .badge-danger {
            background: var(--danger-glow);
            color: var(--danger);
            border: 1px solid rgba(244, 63, 94, 0.2);
        }

        .path-text {
            font-family: 'JetBrains Mono', monospace;
            font-size: 0.8rem;
            color: var(--text-secondary);
        }

        /* Buttons & Actions */
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 0.6rem 1.2rem;
            border-radius: 8px;
            font-size: 0.9rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
            border: none;
            gap: 0.5rem;
            font-family: inherit;
        }

        .btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--primary) 0%, var(--accent-purple) 100%);
            color: white;
            box-shadow: 0 4px 15px var(--primary-glow);
        }

        .btn-primary:hover:not(:disabled) {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(99, 102, 241, 0.5);
        }

        .btn-secondary {
            background: rgba(255, 255, 255, 0.05);
            color: var(--text-primary);
            border: 1px solid var(--border-color);
        }

        .btn-secondary:hover:not(:disabled) {
            background: rgba(255, 255, 255, 0.1);
        }

        .btn-danger {
            background: var(--danger-glow);
            color: var(--danger);
            border: 1px solid rgba(244, 63, 94, 0.3);
        }

        .btn-danger:hover:not(:disabled) {
            background: var(--danger);
            color: white;
            box-shadow: 0 4px 15px var(--danger-glow);
        }

        .action-group {
            display: flex;
            gap: 0.5rem;
        }

        .btn-icon-only {
            padding: 0.5rem;
            border-radius: 6px;
            background: rgba(255, 255, 255, 0.03);
            border: 1px solid var(--border-color);
            color: var(--text-secondary);
            cursor: pointer;
            transition: all 0.2s;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .btn-icon-only:hover {
            color: var(--text-primary);
            background: rgba(255, 255, 255, 0.08);
            border-color: rgba(255, 255, 255, 0.15);
        }

        .btn-icon-only.magic-login-btn:hover {
            color: var(--accent-purple);
            box-shadow: 0 0 10px var(--accent-purple-glow);
            border-color: var(--accent-purple);
        }

        .btn-icon-only.delete-btn:hover {
            color: var(--danger);
            box-shadow: 0 0 10px var(--danger-glow);
            border-color: var(--danger);
        }

        /* Modals */
        .modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.75);
            backdrop-filter: blur(8px);
            z-index: 1000;
            display: flex;
            align-items: center;
            justify-content: center;
            opacity: 0;
            pointer-events: none;
            transition: opacity 0.3s;
        }

        .modal-overlay.active {
            opacity: 1;
            pointer-events: auto;
        }

        .modal-box {
            width: 100%;
            max-width: 600px;
            transform: scale(0.9);
            transition: transform 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
            max-height: 90vh;
            overflow-y: auto;
        }

        .modal-overlay.active .modal-box {
            transform: scale(1);
        }

        .modal-header {
            padding: 1.5rem 2rem;
            border-bottom: 1px solid var(--border-color);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .modal-body {
            padding: 2rem;
        }

        .modal-footer {
            padding: 1.5rem 2rem;
            border-top: 1px solid var(--border-color);
            display: flex;
            justify-content: flex-end;
            gap: 1rem;
            background: rgba(0, 0, 0, 0.15);
        }

        /* Form elements */
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1.25rem;
            margin-bottom: 1.25rem;
        }

        .form-group {
            margin-bottom: 1.25rem;
            display: flex;
            flex-direction: column;
            gap: 0.4rem;
        }

        .form-group.full-width {
            grid-column: span 2;
        }

        label {
            font-size: 0.85rem;
            font-weight: 600;
            color: var(--text-secondary);
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .form-control {
            background: rgba(10, 12, 22, 0.8);
            border: 1px solid var(--border-color);
            border-radius: 8px;
            padding: 0.75rem 1rem;
            color: var(--text-primary);
            font-family: inherit;
            width: 100%;
            transition: border-color 0.2s;
        }

        .form-control:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 10px rgba(99, 102, 241, 0.15);
        }

        .input-group {
            display: flex;
            gap: 0.5rem;
        }

        .input-prefix {
            background: rgba(255, 255, 255, 0.03);
            border: 1px solid var(--border-color);
            color: var(--text-secondary);
            padding: 0.75rem 1rem;
            border-radius: 8px;
            font-family: 'JetBrains Mono', monospace;
            font-size: 0.9rem;
            display: flex;
            align-items: center;
        }

        /* Terminal updating screen */
        .terminal-box {
            background: #020204;
            border: 1px solid rgba(255, 255, 255, 0.08);
            border-radius: 8px;
            padding: 1.5rem;
            font-family: 'JetBrains Mono', monospace;
            font-size: 0.85rem;
            color: #38bdf8;
            height: 250px;
            overflow-y: auto;
            margin-top: 1rem;
        }

        .terminal-line {
            margin-bottom: 0.5rem;
            white-space: pre-wrap;
        }

        .terminal-success {
            color: var(--success);
        }

        .terminal-error {
            color: var(--danger);
        }

        /* Empty states */
        .empty-state {
            padding: 4rem 2rem;
            text-align: center;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 1.5rem;
        }

        .empty-icon {
            font-size: 4rem;
            background: linear-gradient(135deg, var(--primary) 0%, var(--accent-purple) 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            filter: drop-shadow(0 0 15px var(--primary-glow));
        }

        /* Toast Notifications */
        .toast-container {
            position: fixed;
            bottom: 2rem;
            right: 2rem;
            display: flex;
            flex-direction: column;
            gap: 0.75rem;
            z-index: 1100;
        }

        .toast {
            background: rgba(18, 22, 38, 0.95);
            border: 1px solid var(--border-color);
            border-left: 4px solid var(--primary);
            border-radius: 8px;
            padding: 1rem 1.5rem;
            color: var(--text-primary);
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.4);
            display: flex;
            align-items: center;
            gap: 0.75rem;
            transform: translateX(120%);
            transition: transform 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
            min-width: 300px;
        }

        .toast.show {
            transform: translateX(0);
        }

        .toast.toast-success {
            border-left-color: var(--success);
        }

        .toast.toast-error {
            border-left-color: var(--danger);
        }
    </style>
</head>
<body>

    <!-- Header Panel -->
    <div class="glass-panel header">
        <div class="header-logo">
            <div class="logo-icon">✨</div>
            <div class="header-title">
                <h1>Ultimate WordPress Manager</h1>
                <p>DirectAdmin Edition | Logged in as: <strong><?php echo htmlspecialchars($username); ?></strong></p>
            </div>
        </div>
        <div class="header-actions">
            <?php if ($isAdmin): ?>
                <span class="badge badge-success" style="padding: 0.5rem 1rem;">🛡️ Administrator Mode</span>
                <button class="btn btn-secondary" onclick="openUpdateModal()">Update from GitHub</button>
            <?php endif; ?>
            <button class="btn btn-primary" onclick="openInstallModal()">+ Install WordPress</button>
        </div>
    </div>

    <!-- Stats Panel -->
    <div class="stats-grid">
        <div class="glass-panel stat-card">
            <div class="stat-info">
                <h3>Total Sites</h3>
                <p id="stat-total">0</p>
            </div>
            <div class="stat-icon">🌐</div>
        </div>
        <div class="glass-panel stat-card">
            <div class="stat-info">
                <h3>Healthy Connections</h3>
                <p id="stat-healthy">0</p>
            </div>
            <div class="stat-icon" style="background: linear-gradient(135deg, #10b981, #34d399); -webkit-background-clip: text; -webkit-text-fill-color: transparent;">✓</div>
        </div>
        <div class="glass-panel stat-card">
            <div class="stat-info">
                <h3>Needs Action</h3>
                <p id="stat-issues">0</p>
            </div>
            <div class="stat-icon" style="background: linear-gradient(135deg, #f43f5e, #fb7185); -webkit-background-clip: text; -webkit-text-fill-color: transparent;">!</div>
        </div>
    </div>

    <!-- Main List Panel -->
    <div class="glass-panel main-panel">
        <div class="panel-header">
            <div class="panel-title">
                <h2>Discovered WordPress Websites</h2>
            </div>
            <div style="display: flex; gap: 1rem; align-items: center;">
                <div class="search-input-wrapper">
                    <span class="search-icon">🔍</span>
                    <input type="text" id="site-search" class="search-input" placeholder="Search by name, domain, path..." oninput="filterSites()">
                </div>
                <button class="btn btn-secondary" id="btn-scan" onclick="triggerScan()">🔄 Scan Hosting</button>
            </div>
        </div>

        <div class="table-responsive">
            <table id="sites-table">
                <thead>
                    <tr>
                        <th>Site Name & URL</th>
                        <th>Connection Status</th>
                        <th>WordPress Version</th>
                        <th>Database Details</th>
                        <th>Files Directory</th>
                        <th style="text-align: right;">Actions</th>
                    </tr>
                </thead>
                <tbody id="sites-list">
                    <!-- Dynamic injection -->
                </tbody>
            </table>
            
            <!-- Empty state -->
            <div class="empty-state" id="empty-state" style="display: none;">
                <div class="empty-icon">📂</div>
                <h3>No WordPress installations found</h3>
                <p style="color: var(--text-secondary);">Run a hosting directory scan or install a new WordPress site to get started.</p>
                <button class="btn btn-primary" onclick="triggerScan()">🔄 Run Directory Scan</button>
            </div>
        </div>
    </div>

    <!-- MODAL: Install WordPress -->
    <div class="modal-overlay" id="modal-install">
        <div class="glass-panel modal-box">
            <div class="modal-header">
                <h3>Install WordPress</h3>
                <button class="btn-icon-only" onclick="closeInstallModal()">✕</button>
            </div>
            <form id="form-install" onsubmit="executeInstall(event)">
                <div class="modal-body">
                    <!-- Server Domain & URL Details -->
                    <div class="form-row">
                        <div class="form-group">
                            <label>Protocol</label>
                            <select id="inst-protocol" class="form-control">
                                <option value="https">https://</option>
                                <option value="http">http://</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Domain</label>
                            <select id="inst-domain" class="form-control" required>
                                <option value="" disabled selected>Loading Domains...</option>
                            </select>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Installation Type</label>
                        <div style="display: flex; gap: 1.5rem; margin-top: 0.5rem; margin-bottom: 0.5rem;">
                            <label style="display: flex; align-items: center; gap: 0.5rem; color: var(--text-primary); cursor: pointer; text-transform: none;">
                                <input type="radio" name="inst_type" value="directory" checked onchange="toggleInstallType()" style="width: 16px; height: 16px;">
                                <span>Directory (subfolder)</span>
                            </label>
                            <label style="display: flex; align-items: center; gap: 0.5rem; color: var(--text-primary); cursor: pointer; text-transform: none;">
                                <input type="radio" name="inst_type" value="subdomain" onchange="toggleInstallType()" style="width: 16px; height: 16px;">
                                <span>Subdomain</span>
                            </label>
                        </div>
                    </div>
                    <div class="form-group">
                        <label id="inst-subdir-label">Subdirectory (optional)</label>
                        <input type="text" id="inst-subdir" class="form-control" placeholder="e.g. blog (leave empty for root)">
                    </div>

                    <!-- DB details -->
                    <div class="form-row">
                        <div class="form-group">
                            <label>Database Name Suffix</label>
                            <div class="input-group">
                                <span class="input-prefix"><?php echo htmlspecialchars($username); ?>_</span>
                                <input type="text" id="inst-dbname" class="form-control" placeholder="wp1" required maxlength="16">
                            </div>
                        </div>
                        <div class="form-group">
                            <label>Database User Suffix</label>
                            <div class="input-group">
                                <span class="input-prefix"><?php echo htmlspecialchars($username); ?>_</span>
                                <input type="text" id="inst-dbuser" class="form-control" placeholder="wpuser" required maxlength="16">
                            </div>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Database Password</label>
                        <div class="input-group">
                            <input type="text" id="inst-dbpass" class="form-control" required placeholder="Choose a secure DB password">
                            <button type="button" class="btn btn-secondary" onclick="generatePassword('inst-dbpass')">Gen</button>
                        </div>
                    </div>

                    <hr style="border: 0; border-top: 1px solid var(--border-color); margin: 1.5rem 0;">

                    <!-- Admin User details -->
                    <div class="form-group">
                        <label>Site Title</label>
                        <input type="text" id="inst-title" class="form-control" placeholder="My Awesome WordPress Site" required>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>WordPress Admin Username</label>
                            <input type="text" id="inst-adminuser" class="form-control" placeholder="admin" required>
                        </div>
                        <div class="form-group">
                            <label>Admin Password</label>
                            <div class="input-group">
                                <input type="text" id="inst-adminpass" class="form-control" required placeholder="Dashboard Password">
                                <button type="button" class="btn btn-secondary" onclick="generatePassword('inst-adminpass')">Gen</button>
                            </div>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Admin Email</label>
                        <input type="email" id="inst-adminemail" class="form-control" placeholder="admin@domain.com" required value="admin@<?php echo htmlspecialchars($username); ?>.com">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeInstallModal()">Cancel</button>
                    <button type="submit" class="btn btn-primary" id="btn-submit-install">Install WordPress</button>
                </div>
            </form>
        </div>
    </div>

    <!-- MODAL: Delete WordPress Confirmation -->
    <div class="modal-overlay" id="modal-delete">
        <div class="glass-panel modal-box" style="max-width: 500px;">
            <div class="modal-header">
                <h3>Delete WordPress Site</h3>
                <button class="btn-icon-only" onclick="closeDeleteModal()">✕</button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to permanently delete the WordPress installation at the path below?</p>
                <div class="path-text" id="del-display-path" style="background: rgba(0, 0, 0, 0.2); padding: 0.75rem 1rem; border-radius: 8px; margin: 1rem 0; border: 1px solid var(--border-color); font-weight: 500; color: #fb7185;"></div>
                
                <div style="margin: 1.5rem 0; display: flex; flex-direction: column; gap: 0.75rem;">
                    <label style="display: flex; align-items: center; gap: 0.5rem; text-transform: none; color: var(--text-primary); cursor: pointer;">
                        <input type="checkbox" id="del-db-checkbox" checked style="width: 18px; height: 18px;">
                        <span>Delete database <strong><span id="del-display-dbname"></span></strong></span>
                    </label>
                    <p style="color: var(--text-secondary); font-size: 0.8rem; padding-left: 1.5rem;">If selected, this will also use the DirectAdmin API to remove the MySQL database and associated database user.</p>
                </div>
                
                <p style="color: var(--danger); font-size: 0.85rem; font-weight: 600;">⚠️ WARNING: This action is destructive and cannot be undone!</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeDeleteModal()">Cancel</button>
                <button type="button" class="btn btn-danger" id="btn-submit-delete" onclick="executeDelete()">Confirm Delete</button>
            </div>
        </div>
    </div>

    <!-- MODAL: GitHub Update Console (Admin Only) -->
    <?php if ($isAdmin): ?>
    <div class="modal-overlay" id="modal-update">
        <div class="glass-panel modal-box">
            <div class="modal-header">
                <h3>GitHub Update console</h3>
                <button class="btn-icon-only" id="btn-close-update" onclick="closeUpdateModal()">✕</button>
            </div>
            <div class="modal-body">
                <p>Downloading latest files from the GitHub public repository and updating folder permissions...</p>
                <div class="terminal-box" id="update-terminal">
                    <!-- Live console logs -->
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" id="btn-ok-update" disabled onclick="closeUpdateModal()">Done</button>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Toast Notifications Area -->
    <div class="toast-container" id="toast-container"></div>

    <!-- JS Logic -->
    <script>
        const DA_USERNAME = '<?php echo $username; ?>';
        let allSites = [];
        
        // Calculate the correct relative API URL path to handle both with and without trailing slashes in the browser URL
        const getApiUrl = (action = '') => {
            let base = window.location.pathname.split('?')[0];
            if (base.endsWith('.html') || base.endsWith('.raw')) {
                base = base.substring(0, base.lastIndexOf('/') + 1);
            } else if (!base.endsWith('/')) {
                base = base + '/';
            }
            return base + 'index.raw' + (action ? '?action=' + action : '');
        };
        
        // Show floating message
        function showToast(message, type = 'success') {
            const container = document.getElementById('toast-container');
            const toast = document.createElement('div');
            toast.className = `toast toast-${type}`;
            
            let icon = 'ℹ️';
            if (type === 'success') icon = '✅';
            if (type === 'error') icon = '❌';
            
            toast.innerHTML = `<span>${icon}</span> <div>${message}</div>`;
            container.appendChild(toast);
            
            // Trigger animation
            setTimeout(() => toast.classList.add('show'), 50);
            
            // Auto hide
            setTimeout(() => {
                toast.classList.remove('show');
                setTimeout(() => toast.remove(), 300);
            }, 4000);
        }

        // Generate strong password
        function generatePassword(inputId) {
            const chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%&*';
            let password = '';
            // Strong password starting with letters
            password += 'wpAdmin';
            for (let i = 0; i < 10; i++) {
                password += chars.charAt(Math.floor(Math.random() * chars.length));
            }
            document.getElementById(inputId).value = password;
        }

        // Toggle subdirectory vs subdomain labels and settings
        function toggleInstallType() {
            const instType = document.querySelector('input[name="inst_type"]:checked').value;
            const label = document.getElementById('inst-subdir-label');
            const input = document.getElementById('inst-subdir');
            if (instType === 'subdomain') {
                label.textContent = "Subdomain Prefix (required)";
                input.placeholder = "e.g. blog (for blog.domain.com)";
                input.required = true;
            } else {
                label.textContent = "Subdirectory (optional)";
                input.placeholder = "e.g. blog (leave empty for root)";
                input.required = false;
            }
        }


        // Fetch domains and render
        async function loadDomains() {
            try {
                const response = await fetch(getApiUrl('get_domains'));
                const data = await response.json();
                const select = document.getElementById('inst-domain');
                select.innerHTML = '';
                
                if (data.success && data.domains.length > 0) {
                    data.domains.forEach(domain => {
                        const opt = document.createElement('option');
                        opt.value = domain;
                        opt.textContent = domain;
                        select.appendChild(opt);
                    });
                } else {
                    select.innerHTML = '<option value="" disabled>No domains found on hosting</option>';
                }
            } catch (error) {
                showToast("Failed to fetch domains list", 'error');
            }
        }

        // Fetch WordPress installations list
        async function fetchSites(triggerScanOnMissing = false) {
            const tbody = document.getElementById('sites-list');
            const emptyState = document.getElementById('empty-state');
            tbody.innerHTML = '<tr><td colspan="6" style="text-align: center; color: var(--text-secondary); padding: 3rem 0;">🔍 Fetching WordPress installations...</td></tr>';
            
            try {
                const action = triggerScanOnMissing ? 'scan' : 'list';
                const response = await fetch(getApiUrl(action));
                const data = await response.json();
                
                if (data.success) {
                    allSites = data.sites;
                    renderSites(allSites);
                } else {
                    showToast(data.error || "Unable to read installations cache.", "error");
                    tbody.innerHTML = '';
                    emptyState.style.display = 'block';
                }
            } catch (error) {
                showToast("Connection to backend lost.", "error");
                tbody.innerHTML = '';
                emptyState.style.display = 'block';
            }
        }

        // Trigger scan hosting
        async function triggerScan() {
            const btn = document.getElementById('btn-scan');
            btn.disabled = true;
            btn.textContent = '🔄 Scanning directories...';
            showToast("Directory scan started. Searching public_html folders...");
            
            await fetchSites(true);
            
            btn.disabled = false;
            btn.textContent = '🔄 Scan Hosting';
            showToast("Scan finished. Dashboard updated.");
        }

        // Filter sites on search
        function filterSites() {
            const query = document.getElementById('site-search').value.toLowerCase();
            const filtered = allSites.filter(site => {
                return site.blogname.toLowerCase().includes(query) ||
                       site.siteurl.toLowerCase().includes(query) ||
                       site.path.toLowerCase().includes(query) ||
                       site.db_name.toLowerCase().includes(query);
            });
            renderSites(filtered);
        }

        // Render sites table
        function renderSites(sites) {
            const tbody = document.getElementById('sites-list');
            const emptyState = document.getElementById('empty-state');
            
            tbody.innerHTML = '';
            
            // Update stats
            document.getElementById('stat-total').textContent = sites.length;
            const healthy = sites.filter(s => s.status === 'active').length;
            document.getElementById('stat-healthy').textContent = healthy;
            document.getElementById('stat-issues').textContent = sites.length - healthy;
            
            if (sites.length === 0) {
                emptyState.style.display = 'block';
                document.getElementById('sites-table').style.display = 'none';
                return;
            }
            
            emptyState.style.display = 'none';
            document.getElementById('sites-table').style.display = 'table';
            
            sites.forEach(site => {
                const tr = document.createElement('tr');
                
                // Status badge
                let statusBadge = `<span class="badge badge-success">● Connected</span>`;
                if (site.status === 'db_error') {
                    statusBadge = `<span class="badge badge-danger">● Database Error</span>`;
                }
                
                // Truncate path for visual neatness
                const displayPath = site.path.replace(`/home/${DA_USERNAME}/`, '~/');
                
                tr.innerHTML = `
                    <td>
                        <div class="site-title-cell">
                            <div class="site-avatar">${site.blogname.charAt(0).toUpperCase()}</div>
                            <div class="site-meta">
                                <h4>${site.blogname}</h4>
                                <a href="${site.siteurl}" target="_blank">${site.siteurl} ↗</a>
                            </div>
                        </div>
                    </td>
                    <td>${statusBadge}</td>
                    <td><strong style="color: var(--text-primary); font-size: 0.95rem;">v${site.version}</strong></td>
                    <td>
                        <div style="font-size: 0.85rem; color: var(--text-secondary);">
                            <div>DB: <strong style="color: var(--text-primary); font-family: monospace;">${site.db_name}</strong></div>
                            <div>Prefix: <span style="font-family: monospace;">${site.db_prefix}</span></div>
                        </div>
                    </td>
                    <td class="path-text">${displayPath}</td>
                    <td style="text-align: right;">
                        <div class="action-group" style="justify-content: flex-end;">
                            <button class="btn btn-secondary btn-icon-only magic-login-btn" onclick="executeMagicLogin('${site.path}')" title="Magic Login">⚡ Magic Login</button>
                            <button class="btn btn-secondary btn-icon-only delete-btn" onclick="openDeleteModal('${site.path}', '${site.db_name}')" title="Delete Site">🗑️ Delete</button>
                        </div>
                    </td>
                `;
                tbody.appendChild(tr);
            });
        }

        // Install Modal Actions
        function openInstallModal() {
            document.getElementById('modal-install').classList.add('active');
            loadDomains();
            generatePassword('inst-dbpass');
            generatePassword('inst-adminpass');
            toggleInstallType();
        }

        function closeInstallModal() {
            document.getElementById('modal-install').classList.remove('active');
        }

        // Run DirectAdmin Database Creation
        async function createDatabaseDirectAdmin(dbNameSuffix, dbUserSuffix, dbPassword) {
            const params = new URLSearchParams();
            params.append('action', 'create');
            params.append('name', dbNameSuffix);
            params.append('user', dbUserSuffix);
            params.append('passwd', dbPassword);
            params.append('passwd2', dbPassword);
            
            const response = await fetch('/CMD_API_DATABASES', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded'
                },
                body: params.toString()
            });
            
            const text = await response.text();
            const queryParams = new URLSearchParams(text);
            if (queryParams.get('error') === '1') {
                throw new Error(decodeURIComponent(queryParams.get('details') || 'Failed to create database via DirectAdmin API.'));
            }
            
            return {
                db_name: queryParams.get('db') || (DA_USERNAME + '_' + dbNameSuffix),
                db_user: queryParams.get('user') || (DA_USERNAME + '_' + dbUserSuffix)
            };
        }

        // Run installation
        async function executeInstall(event) {
            event.preventDefault();
            const submitBtn = document.getElementById('btn-submit-install');
            submitBtn.disabled = true;
            submitBtn.textContent = 'Creating DB & Downloading WP...';
            
            const dbNameSuffix = document.getElementById('inst-dbname').value;
            const dbUserSuffix = document.getElementById('inst-dbuser').value;
            const dbPassword = document.getElementById('inst-dbpass').value;
            
            try {
                // 1. Create DB via DirectAdmin session
                showToast("1/3 Creating database via DirectAdmin API...");
                const dbInfo = await createDatabaseDirectAdmin(dbNameSuffix, dbUserSuffix, dbPassword);
                
                // 2. Trigger installation backend
                showToast("2/3 Extracting WordPress core & running setup...");
                submitBtn.textContent = 'Installing Database Tables...';
                
                const installParams = new URLSearchParams();
                installParams.append('action', 'install');
                installParams.append('domain', document.getElementById('inst-domain').value);
                installParams.append('subdir', document.getElementById('inst-subdir').value);
                
                const isSubdomain = document.querySelector('input[name="inst_type"]:checked').value === 'subdomain';
                installParams.append('is_subdomain', isSubdomain ? 'true' : 'false');
                
                installParams.append('db_name', dbInfo.db_name);
                installParams.append('db_user', dbInfo.db_user);
                installParams.append('db_pass', dbPassword);
                installParams.append('site_title', document.getElementById('inst-title').value);
                installParams.append('admin_user', document.getElementById('inst-adminuser').value);
                installParams.append('admin_pass', document.getElementById('inst-adminpass').value);
                installParams.append('admin_email', document.getElementById('inst-adminemail').value);
                installParams.append('protocol', document.getElementById('inst-protocol').value);
                
                const response = await fetch(getApiUrl(), {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded'
                    },
                    body: installParams.toString()
                });
                
                const result = await response.json();
                if (result.success) {
                    showToast("3/3 WordPress installed successfully!", "success");
                    closeInstallModal();
                    fetchSites(false);
                    
                    // Reset form fields
                    document.getElementById('form-install').reset();
                } else {
                    showToast(result.error || "WordPress setup failed.", "error");
                }
            } catch (error) {
                showToast(error.message || "An error occurred during installation.", "error");
            } finally {
                submitBtn.disabled = false;
                submitBtn.textContent = 'Install WordPress';
            }
        }

        // Magic Login Action
        async function executeMagicLogin(sitePath) {
            showToast("Generating magic login token...");
            try {
                const params = new URLSearchParams();
                params.append('action', 'magic_login');
                params.append('path', sitePath);
                
                const response = await fetch(getApiUrl(), {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded'
                    },
                    body: params.toString()
                });
                const result = await response.json();
                
                if (result.success && result.login_url) {
                    showToast("Redirecting securely...", "success");
                    window.open(result.login_url, '_blank');
                } else {
                    showToast(result.error || "Magic login generation failed.", "error");
                }
            } catch (error) {
                showToast("Connection to server failed.", "error");
            }
        }

        // Delete Modal Actions
        let activeDeletePath = '';
        let activeDeleteDb = '';

        function openDeleteModal(sitePath, dbName) {
            activeDeletePath = sitePath;
            activeDeleteDb = dbName;
            document.getElementById('del-display-path').textContent = sitePath;
            document.getElementById('del-display-dbname').textContent = dbName;
            
            // If DB name is empty, disable checkbox
            const checkbox = document.getElementById('del-db-checkbox');
            if (!dbName) {
                checkbox.checked = false;
                checkbox.disabled = true;
            } else {
                checkbox.checked = true;
                checkbox.disabled = false;
            }
            
            document.getElementById('modal-delete').classList.add('active');
        }

        function closeDeleteModal() {
            document.getElementById('modal-delete').classList.remove('active');
            activeDeletePath = '';
            activeDeleteDb = '';
        }

        async function deleteDatabaseDirectAdmin(dbName) {
            const params = new URLSearchParams();
            params.append('action', 'delete');
            params.append('select0', dbName);
            
            const response = await fetch('/CMD_API_DATABASES', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded'
                },
                body: params.toString()
            });
            const text = await response.text();
            if (text.includes('error=1')) {
                const match = text.match(/details=(.*?)(&|$)/);
                const details = match ? decodeURIComponent(match[1]) : "DirectAdmin database error";
                throw new Error(details);
            }
        }

        async function executeDelete() {
            const deleteDb = document.getElementById('del-db-checkbox').checked;
            const submitBtn = document.getElementById('btn-submit-delete');
            submitBtn.disabled = true;
            submitBtn.textContent = 'Processing Deletion...';
            
            try {
                // 1. Delete DB if checked
                if (deleteDb && activeDeleteDb) {
                    showToast(`Deleting database ${activeDeleteDb}...`);
                    await deleteDatabaseDirectAdmin(activeDeleteDb);
                }
                
                // 2. Delete files on backend
                showToast("Removing directory files recursively...");
                const params = new URLSearchParams();
                params.append('action', 'delete');
                params.append('path', activeDeletePath);
                
                const response = await fetch(getApiUrl(), {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded'
                    },
                    body: params.toString()
                });
                const result = await response.json();
                
                if (result.success) {
                    showToast("WordPress installation removed successfully.", "success");
                    closeDeleteModal();
                    fetchSites(false);
                } else {
                    showToast(result.error || "File deletion failed.", "error");
                }
            } catch (error) {
                showToast(error.message || "An error occurred during deletion.", "error");
            } finally {
                submitBtn.disabled = false;
                submitBtn.textContent = 'Confirm Delete';
            }
        }

        // Admin Update Modal
        function openUpdateModal() {
            const terminal = document.getElementById('update-terminal');
            terminal.innerHTML = '<div class="terminal-line">Initialized updater...</div>';
            document.getElementById('modal-update').classList.add('active');
            
            document.getElementById('btn-close-update').disabled = true;
            document.getElementById('btn-ok-update').disabled = true;
            
            // Start self update
            executePluginUpdate();
        }

        function closeUpdateModal() {
            document.getElementById('modal-update').classList.remove('active');
        }

        async function executePluginUpdate() {
            const terminal = document.getElementById('update-terminal');
            
            function printLog(text, type = 'info') {
                const line = document.createElement('div');
                line.className = 'terminal-line';
                if (type === 'success') line.classList.add('terminal-success');
                if (type === 'error') line.classList.add('terminal-error');
                line.textContent = `[${new Date().toLocaleTimeString()}] ${text}`;
                terminal.appendChild(line);
                terminal.scrollTop = terminal.scrollHeight;
            }
            
            printLog("Connecting to GitHub API to pull master branch...");
            try {
                const params = new URLSearchParams();
                params.append('action', 'update_plugin');
                
                const response = await fetch(getApiUrl(), {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded'
                    },
                    body: params.toString()
                });
                
                const result = await response.json();
                if (result.success) {
                    printLog("GitHub Zip download complete.", "info");
                    printLog("Unpacking archive files...", "info");
                    printLog("Overwriting core plugin assets...", "info");
                    printLog("Applying recursive file permissions: chmod 644 on resources, chmod 755 on scripts/wrappers...", "info");
                    printLog(result.message, "success");
                    printLog("Update completed successfully! Please reload the page.", "success");
                    showToast("Update successful!", "success");
                } else {
                    printLog("Error: " + result.error, "error");
                    showToast(result.error || "Update failed.", "error");
                }
            } catch (error) {
                printLog("Connection error occurred: " + error.message, "error");
                showToast("Connection failed.", "error");
            } finally {
                document.getElementById('btn-close-update').disabled = false;
                document.getElementById('btn-ok-update').disabled = false;
            }
        }

        // On Load initialization
        window.addEventListener('DOMContentLoaded', () => {
            fetchSites(false);
        });
    </script>
</body>
</html>
