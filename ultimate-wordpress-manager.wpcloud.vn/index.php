<?php
/**
 * Ultimate WordPress Manager - Premium Store Server
 * Central Web Manager for Premium Plugins & Themes
 * URL: ultimate-wordpress-manager.wpcloud.vn
 */
session_start();

// Configuration
define('ADMIN_PASSWORD', 'admin@wpcloud'); // Thay đổi mật khẩu quản trị tại đây
define('DATA_FILE', __DIR__ . '/premium.json');
define('UPLOAD_DIR', __DIR__ . '/premium_uploads');

// Create upload directory if not exists
if (!is_dir(UPLOAD_DIR)) {
    @mkdir(UPLOAD_DIR, 0755, true);
    @chmod(UPLOAD_DIR, 0755);
}

// Initialize JSON data file if not exists
if (!file_exists(DATA_FILE)) {
    file_put_contents(DATA_FILE, json_encode(['plugins' => [], 'themes' => []], JSON_PRETTY_PRINT));
    @chmod(DATA_FILE, 0644);
}

// Authentication check
$expected_token = md5(ADMIN_PASSWORD . ($_SERVER['HTTP_USER_AGENT'] ?? ''));
$authenticated = false;

if (isset($_SESSION['store_logged_in']) && $_SESSION['store_logged_in'] === true) {
    $authenticated = true;
} elseif (isset($_COOKIE['store_auth']) && $_COOKIE['store_auth'] === $expected_token) {
    $_SESSION['store_logged_in'] = true; // Sync to session
    $authenticated = true;
}

// Login process
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'login') {
    $password = $_POST['password'] ?? '';
    if ($password === ADMIN_PASSWORD) {
        $_SESSION['store_logged_in'] = true;
        // Set cookie valid for 30 days, HttpOnly to protect against XSS
        setcookie('store_auth', $expected_token, time() + 86400 * 30, '/', '', false, true);
        header('Location: index.php');
        exit;
    } else {
        $error = 'Mật khẩu quản trị không chính xác!';
    }
}

// Logout process
if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    unset($_SESSION['store_logged_in']);
    session_destroy();
    setcookie('store_auth', '', time() - 3600, '/');
    header('Location: index.php');
    exit;
}

// Helper: Load premium list
function get_store_list() {
    $content = @file_get_contents(DATA_FILE);
    $data = json_decode($content, true);
    return $data ?: ['plugins' => [], 'themes' => []];
}

// Helper: Save premium list
function save_store_list($data) {
    return file_put_contents(DATA_FILE, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}

// Action: Add item
if ($authenticated && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add') {
    $item_type = $_POST['item_type'] ?? 'plugins'; // plugins or themes
    $type = $_POST['type'] ?? 'wporg'; // wporg or zip
    $name = trim($_POST['name'] ?? '');
    $desc = trim($_POST['description'] ?? '');
    
    try {
        if (!$name) {
            throw new Exception("Vui lòng nhập tên hiển thị.");
        }
        
        $list = get_store_list();
        $new_item = [
            'name' => $name,
            'description' => $desc,
            'type' => $type
        ];
        
        if ($type === 'wporg') {
            $slug = trim($_POST['slug'] ?? '');
            if (!$slug) {
                throw new Exception("Vui lòng nhập Slug của WordPress.org.");
            }
            $new_item['slug'] = $slug;
        } else {
            // ZIP File Upload
            if (empty($_FILES['zip_file']) || $_FILES['zip_file']['error'] !== UPLOAD_ERR_OK) {
                throw new Exception("Vui lòng chọn tệp ZIP hợp lệ.");
            }
            
            $file = $_FILES['zip_file'];
            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            if ($ext !== 'zip') {
                throw new Exception("Chỉ chấp nhận định dạng tệp .zip.");
            }
            
            $filename = basename($file['name']);
            $filename = preg_replace('/[^a-zA-Z0-9_\-\.]/', '', $filename);
            // Thêm timestamp tránh trùng file
            $filename = time() . '_' . $filename;
            $dest = UPLOAD_DIR . '/' . $filename;
            
            if (move_uploaded_file($file['tmp_name'], $dest)) {
                @chmod($dest, 0644);
                $new_item['file'] = $filename;
            } else {
                throw new Exception("Không thể lưu trữ tệp tin tải lên máy chủ.");
            }
        }
        
        $list[$item_type][] = $new_item;
        save_store_list($list);
        $success_msg = "Đã thêm mục Premium thành công!";
    } catch (Exception $e) {
        $add_error = $e->getMessage();
    }
}

// Action: Delete item
if ($authenticated && isset($_GET['action']) && $_GET['action'] === 'delete') {
    $item_type = $_GET['type'] ?? ''; // plugins or themes
    $index = isset($_GET['index']) ? (int)$_GET['index'] : -1;
    
    $list = get_store_list();
    if (($item_type === 'plugins' || $item_type === 'themes') && isset($list[$item_type][$index])) {
        $item = $list[$item_type][$index];
        // Nếu là zip, xóa file vật lý
        if ($item['type'] === 'zip' && !empty($item['file'])) {
            $file_path = UPLOAD_DIR . '/' . $item['file'];
            if (file_exists($file_path)) {
                @unlink($file_path);
            }
        }
        
        array_splice($list[$item_type], $index, 1);
        save_store_list($list);
        header('Location: index.php?msg=deleted');
        exit;
    }
}

$list_data = get_store_list();
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ultimate WordPress Manager - Premium Store Dashboard</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg-dark: #090d16;
            --bg-card: #111827;
            --bg-input: #1f2937;
            --border: #374151;
            --text-main: #f3f4f6;
            --text-muted: #9ca3af;
            --primary: #3b82f6;
            --primary-hover: #2563eb;
            --success: #10b981;
            --danger: #ef4444;
            --warning: #f59e0b;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: 'Inter', sans-serif;
            background-color: var(--bg-dark);
            color: var(--text-main);
            line-height: 1.5;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }

        .container {
            width: 100%;
            max-width: 1200px;
            margin: 0 auto;
            padding: 24px;
        }

        header {
            background-color: var(--bg-card);
            border-bottom: 1px solid var(--border);
            padding: 16px 0;
            margin-bottom: 30px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.3);
        }

        .header-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        h1 {
            font-size: 20px;
            font-weight: 700;
            background: linear-gradient(90deg, #60a5fa, #34d399);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 8px 16px;
            font-size: 13px;
            font-weight: 500;
            border-radius: 6px;
            border: 1px solid transparent;
            cursor: pointer;
            text-decoration: none;
            transition: all 0.2s ease;
        }

        .btn-primary {
            background-color: var(--primary);
            color: white;
        }

        .btn-primary:hover {
            background-color: var(--primary-hover);
        }

        .btn-danger {
            background-color: transparent;
            border-color: rgba(239, 68, 68, 0.4);
            color: var(--danger);
        }

        .btn-danger:hover {
            background-color: var(--danger);
            color: white;
        }

        .btn-logout {
            background-color: var(--bg-input);
            border-color: var(--border);
            color: var(--text-main);
        }

        .btn-logout:hover {
            background-color: var(--border);
        }

        /* Login Box Styles */
        .login-wrapper {
            display: flex;
            justify-content: center;
            align-items: center;
            flex-grow: 1;
            padding: 20px;
        }

        .login-box {
            width: 100%;
            max-width: 400px;
            background: var(--bg-card);
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 32px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.5);
            text-align: center;
        }

        .login-box h2 {
            font-size: 22px;
            margin-bottom: 24px;
            font-weight: 700;
        }

        .form-group {
            margin-bottom: 18px;
            text-align: left;
        }

        .form-group label {
            display: block;
            font-size: 12px;
            font-weight: 600;
            margin-bottom: 6px;
            color: var(--text-muted);
            text-transform: uppercase;
        }

        .form-control {
            width: 100%;
            padding: 10px 14px;
            font-size: 13px;
            font-family: inherit;
            background-color: var(--bg-input);
            border: 1px solid var(--border);
            border-radius: 6px;
            color: white;
            outline: none;
            transition: border-color 0.2s;
        }

        .form-control:focus {
            border-color: var(--primary);
        }

        .alert {
            padding: 12px 16px;
            border-radius: 6px;
            font-size: 13px;
            margin-bottom: 20px;
            border: 1px solid transparent;
        }

        .alert-danger {
            background-color: rgba(239, 68, 68, 0.1);
            border-color: rgba(239, 68, 68, 0.3);
            color: #fca5a5;
        }

        .alert-success {
            background-color: rgba(16, 185, 129, 0.1);
            border-color: rgba(16, 185, 129, 0.3);
            color: #a7f3d0;
        }

        /* Dashboard Grid Layout */
        .dashboard-grid {
            display: grid;
            grid-template-columns: 350px 1fr;
            gap: 24px;
        }

        @media (max-width: 900px) {
            .dashboard-grid {
                grid-template-columns: 1fr;
            }
        }

        .card {
            background: var(--bg-card);
            border: 1px solid var(--border);
            border-radius: 10px;
            padding: 24px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.2);
            margin-bottom: 24px;
        }

        .card-title {
            font-size: 15px;
            font-weight: 700;
            margin-bottom: 18px;
            padding-bottom: 8px;
            border-bottom: 1px solid var(--border);
            color: var(--text-main);
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .badge {
            font-size: 10px;
            padding: 2px 8px;
            border-radius: 12px;
            font-weight: 600;
        }

        .badge-blue { background-color: rgba(59, 130, 246, 0.2); color: #93c5fd; border: 1px solid rgba(59, 130, 246, 0.3); }
        .badge-green { background-color: rgba(16, 185, 129, 0.2); color: #a7f3d0; border: 1px solid rgba(16, 185, 129, 0.3); }
        .badge-yellow { background-color: rgba(245, 158, 11, 0.2); color: #fde68a; border: 1px solid rgba(245, 158, 11, 0.3); }

        select.form-control {
            appearance: none;
            background-image: url("data:image/svg+xml;charset=utf-8,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%239ca3af' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpolyline points='6 9 12 15 18 9'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 12px center;
            background-size: 16px;
            padding-right: 40px;
        }

        /* Custom Table Styles */
        .table-responsive {
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 12px;
            text-align: left;
        }

        th {
            background-color: rgba(31, 41, 55, 0.5);
            color: var(--text-muted);
            font-weight: 600;
            padding: 12px 16px;
            border-bottom: 1px solid var(--border);
            text-transform: uppercase;
            font-size: 10px;
            letter-spacing: 0.05em;
        }

        td {
            padding: 14px 16px;
            border-bottom: 1px solid rgba(55, 65, 81, 0.5);
            color: var(--text-main);
            vertical-align: middle;
        }

        tr:hover td {
            background-color: rgba(31, 41, 55, 0.2);
        }

        .item-name {
            font-weight: 600;
            color: white;
            font-size: 13px;
        }

        .item-desc {
            color: var(--text-muted);
            font-size: 11px;
            margin-top: 2px;
            max-width: 300px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .section-header {
            margin-top: 24px;
            margin-bottom: 14px;
            font-size: 14px;
            font-weight: 700;
            color: var(--text-main);
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            display: inline-block;
        }
        .dot-blue { background-color: var(--primary); }
        .dot-success { background-color: var(--success); }

        footer {
            text-align: center;
            padding: 30px 0;
            color: var(--text-muted);
            font-size: 12px;
            border-top: 1px solid var(--border);
            margin-top: 40px;
        }

        .footer-links {
            margin-top: 10px;
            display: flex;
            justify-content: center;
            gap: 15px;
        }

        .footer-links a {
            color: var(--primary);
            text-decoration: none;
        }

        .footer-links a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>

    <?php if (!$authenticated): ?>
        <!-- Login Screen -->
        <div class="login-wrapper">
            <div class="login-box">
                <h2>✨ Premium Store Admin</h2>
                <?php if (isset($error)): ?>
                    <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
                <?php endif; ?>
                <form method="POST">
                    <input type="hidden" name="action" value="login">
                    <div class="form-group">
                        <label for="password">Mật khẩu đăng nhập</label>
                        <input type="password" id="password" name="password" class="form-control" placeholder="••••••••" required autofocus>
                    </div>
                    <button type="submit" style="width: 100%; justify-content: center; margin-top: 10px;" class="btn btn-primary">🔓 Đăng nhập Dashboard</button>
                </form>
            </div>
        </div>
    <?php else: ?>
        <!-- Dashboard Screen -->
        <header>
            <div class="container header-content">
                <h1>👑 Ultimate WP Manager - Premium Store</h1>
                <div style="display: flex; gap: 10px; align-items: center;">
                    <a href="premium.json" target="_blank" class="btn btn-logout" style="border-color: rgba(59, 130, 246, 0.4); color: #60a5fa;">📄 Xem premium.json API</a>
                    <a href="?action=logout" class="btn btn-logout">🚪 Đăng xuất</a>
                </div>
            </div>
        </header>

        <div class="container" style="flex-grow: 1; padding-top: 0;">
            <?php if (isset($success_msg)): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($success_msg); ?></div>
            <?php endif; ?>
            <?php if (isset($_GET['msg']) && $_GET['msg'] === 'deleted'): ?>
                <div class="alert alert-success">Đã xóa tài nguyên thành công!</div>
            <?php endif; ?>

            <div class="dashboard-grid">
                <!-- Left Column: Add Item Form -->
                <div>
                    <div class="card">
                        <div class="card-title">➕ Thêm mục Premium mới</div>
                        <form method="POST" enctype="multipart/form-data">
                            <input type="hidden" name="action" value="add">
                            
                            <?php if (isset($add_error)): ?>
                                <div class="alert alert-danger"><?php echo htmlspecialchars($add_error); ?></div>
                            <?php endif; ?>

                            <div class="form-group">
                                <label for="item_type">Loại tài nguyên</label>
                                <select name="item_type" id="item_type" class="form-control">
                                    <option value="plugins">🔌 Plugin</option>
                                    <option value="themes">🎨 Theme</option>
                                </select>
                            </div>

                            <div class="form-group">
                                <label for="type">Nguồn cài đặt</label>
                                <select name="type" id="type" class="form-control" onchange="toggleFormFields()">
                                    <option value="wporg">WordPress.org (Slug)</option>
                                    <option value="zip">Tải lên file ZIP</option>
                                </select>
                            </div>

                            <div class="form-group">
                                <label for="name">Tên hiển thị</label>
                                <input type="text" name="name" id="name" class="form-control" required placeholder="Ví dụ: WooCommerce">
                            </div>

                            <div class="form-group" id="slug_field">
                                <label for="slug">Slug từ WordPress.org</label>
                                <input type="text" name="slug" id="slug" class="form-control" placeholder="Ví dụ: woocommerce">
                            </div>

                            <div class="form-group" id="zip_field" style="display: none;">
                                <label for="zip_file">Chọn tệp ZIP từ máy tính</label>
                                <input type="file" name="zip_file" id="zip_file" class="form-control" accept=".zip">
                            </div>

                            <div class="form-group">
                                <label for="description">Mô tả ngắn</label>
                                <textarea name="description" id="description" class="form-control" rows="3" placeholder="Mô tả công dụng hoặc tính năng..."></textarea>
                            </div>

                            <button type="submit" style="width: 100%; justify-content: center; margin-top: 10px;" class="btn btn-primary">➕ Thêm vào Store</button>
                        </form>
                    </div>
                </div>

                <!-- Right Column: Current List -->
                <div>
                    <!-- Section: Plugins -->
                    <div class="section-header">
                        <span class="dot dot-blue"></span>
                        <span>🔌 Danh sách Plugins (<?php echo count($list_data['plugins']); ?>)</span>
                    </div>
                    <div class="card" style="padding: 0; overflow: hidden;">
                        <div class="table-responsive">
                            <table>
                                <thead>
                                    <tr>
                                        <th style="padding-left: 20px;">Tên hiển thị</th>
                                        <th>Loại</th>
                                        <th>Định danh (Slug/File)</th>
                                        <th style="text-align: center; width: 100px; padding-right: 20px;">Hành động</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($list_data['plugins'])): ?>
                                        <tr>
                                            <td colspan="4" style="text-align: center; color: var(--text-muted); padding: 24px;">Chưa có plugin premium nào được cấu hình.</td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($list_data['plugins'] as $idx => $p): ?>
                                            <tr>
                                                <td style="padding-left: 20px;">
                                                    <div class="item-name"><?php echo htmlspecialchars($p['name']); ?></div>
                                                    <div class="item-desc" title="<?php echo htmlspecialchars($p['description'] ?? ''); ?>"><?php echo htmlspecialchars($p['description'] ?: 'Không có mô tả.'); ?></div>
                                                </td>
                                                <td>
                                                    <?php if ($p['type'] === 'wporg'): ?>
                                                        <span class="badge badge-blue">WordPress.org</span>
                                                    <?php else: ?>
                                                        <span class="badge badge-yellow">Premium ZIP</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td style="font-family: monospace; font-size: 11px; color: var(--text-muted);">
                                                    <?php echo htmlspecialchars($p['type'] === 'wporg' ? ($p['slug'] ?? '') : ($p['file'] ?? '')); ?>
                                                </td>
                                                <td style="text-align: center; padding-right: 20px;">
                                                    <a href="?action=delete&type=plugins&index=<?php echo $idx; ?>" class="btn btn-sm btn-danger" onclick="return confirm('Bạn có chắc chắn muốn xóa mục này khỏi Store?')">🗑 Xóa</a>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Section: Themes -->
                    <div class="section-header" style="margin-top: 30px;">
                        <span class="dot dot-success"></span>
                        <span>🎨 Danh sách Themes (<?php echo count($list_data['themes']); ?>)</span>
                    </div>
                    <div class="card" style="padding: 0; overflow: hidden;">
                        <div class="table-responsive">
                            <table>
                                <thead>
                                    <tr>
                                        <th style="padding-left: 20px;">Tên hiển thị</th>
                                        <th>Loại</th>
                                        <th>Định danh (Slug/File)</th>
                                        <th style="text-align: center; width: 100px; padding-right: 20px;">Hành động</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($list_data['themes'])): ?>
                                        <tr>
                                            <td colspan="4" style="text-align: center; color: var(--text-muted); padding: 24px;">Chưa có theme premium nào được cấu hình.</td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($list_data['themes'] as $idx => $t): ?>
                                            <tr>
                                                <td style="padding-left: 20px;">
                                                    <div class="item-name"><?php echo htmlspecialchars($t['name']); ?></div>
                                                    <div class="item-desc" title="<?php echo htmlspecialchars($t['description'] ?? ''); ?>"><?php echo htmlspecialchars($t['description'] ?: 'Không có mô tả.'); ?></div>
                                                </td>
                                                <td>
                                                    <?php if ($t['type'] === 'wporg'): ?>
                                                        <span class="badge badge-blue">WordPress.org</span>
                                                    <?php else: ?>
                                                        <span class="badge badge-yellow">Premium ZIP</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td style="font-family: monospace; font-size: 11px; color: var(--text-muted);">
                                                    <?php echo htmlspecialchars($t['type'] === 'wporg' ? ($t['slug'] ?? '') : ($t['file'] ?? '')); ?>
                                                </td>
                                                <td style="text-align: center; padding-right: 20px;">
                                                    <a href="?action=delete&type=themes&index=<?php echo $idx; ?>" class="btn btn-sm btn-danger" onclick="return confirm('Bạn có chắc chắn muốn xóa mục này khỏi Store?')">🗑 Xóa</a>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <script>
            function toggleFormFields() {
                const type = document.getElementById('type').value;
                const slugField = document.getElementById('slug_field');
                const zipField = document.getElementById('zip_field');
                const slugInput = document.getElementById('slug');
                const zipInput = document.getElementById('zip_file');
                
                if (type === 'wporg') {
                    slugField.style.display = 'block';
                    zipField.style.display = 'none';
                    slugInput.required = true;
                    zipInput.required = false;
                } else {
                    slugField.style.display = 'none';
                    zipField.style.display = 'block';
                    slugInput.required = false;
                    zipInput.required = true;
                }
            }
            // Khởi chạy khi load trang
            document.addEventListener('DOMContentLoaded', toggleFormFields);
        </script>
    <?php endif; ?>

    <footer>
        <div class="container">
            <p>&copy; 2026 Ultimate WP Manager Store. Thiết kế bởi Antigravity.</p>
            <div class="footer-links">
                <a href="premium.json" target="_blank">Cấu hình JSON</a>
                <span>&bull;</span>
                <a href="https://wpcloud.vn" target="_blank">WPCloud.vn</a>
            </div>
        </div>
    </footer>
</body>
</html>
