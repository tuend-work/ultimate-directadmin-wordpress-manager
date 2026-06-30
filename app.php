<?php
/**
 * Ultimate DirectAdmin WordPress Manager
 * Core Backend Controller
 */

// Log errors but never display them to stdout (would corrupt JSON API responses)
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Start output buffering to capture any accidental warnings/notices and protect CGI headers
ob_start();

// Parse GET and POST variables (DirectAdmin executes plugins via CLI)
$_GET = [];
$QUERY_STRING = getenv('QUERY_STRING');
if ($QUERY_STRING != "") {
    parse_str(html_entity_decode($QUERY_STRING), $get_array);
    foreach ($get_array as $key => $value) {
        $_GET[urldecode($key)] = urldecode($value);
    }
}

$POST_STRING = getenv('POST');
if ($POST_STRING != "") {
    $_POST = [];
    parse_str(html_entity_decode($POST_STRING), $post_array);
    foreach ($post_array as $key => $value) {
        $_POST[urldecode($key)] = urldecode($value);
    }
}

/**
 * Generate a cryptographically secure random string for WordPress keys/salts
 */
function wp_generate_random_secret(int $length = 64): string {
    $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*()-_ []{}<>~`+=,.;:/?|';
    $chars_len = strlen($chars);
    $secret = '';
    $bytes = random_bytes($length);
    for ($i = 0; $i < $length; $i++) {
        $secret .= $chars[ord($bytes[$i]) % $chars_len];
    }
    return $secret;
}

/**
 * Determine if current executing user is administrator
 */
function is_admin_user() {
    $is_win = strtoupper(substr(PHP_OS, 0, 3)) === 'WIN';
    if ($is_win) {
        $current_user = getenv('USERNAME') ?: getenv('USER');
        return ($current_user === 'admin') || (strpos($_SERVER['SCRIPT_FILENAME'] ?? '', 'admin') !== false);
    }
    // Check script path (canonical admin path in DirectAdmin)
    if (strpos($_SERVER['SCRIPT_FILENAME'] ?? '', '/admin/') !== false) {
        return true;
    }
    // Fallback: DA Access Level switcher keeps /user/ URL but CGI runs as system user 'admin'
    $sys_user = getenv('USER') ?: getenv('USERNAME') ?: '';
    return $sys_user === 'admin';
}

/**
 * Get all DirectAdmin users on the server
 */
function get_all_directadmin_users() {
    $users = [];
    if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
        return ['local_user', 'userA', 'userB'];
    }
    // Try to list /usr/local/directadmin/data/users/
    $da_users_dir = '/usr/local/directadmin/data/users';
    if (is_dir($da_users_dir)) {
        $dirs = array_diff(scandir($da_users_dir), ['.', '..']);
        foreach ($dirs as $d) {
            if (is_dir($da_users_dir . '/' . $d)) {
                $users[] = $d;
            }
        }
    }
    // Fallback: list directories in /home
    if (empty($users) && is_dir('/home')) {
        $dirs = array_diff(scandir('/home'), ['.', '..']);
        foreach ($dirs as $d) {
            if (is_dir('/home/' . $d . '/domains')) {
                $users[] = $d;
            }
        }
    }
    sort($users);
    return $users;
}


/**
 * Recursively remove directories and files
 */
function rmdir_recursive($dir, $keep_root = false) {
    if (!is_dir($dir)) return;
    $files = array_diff(scandir($dir), ['.', '..']);
    foreach ($files as $file) {
        $path = $dir . '/' . $file;
        if (is_dir($path) && !is_link($path)) {
            rmdir_recursive($path, false);
        } else {
            @unlink($path);
        }
    }
    if (!$keep_root) {
        @rmdir($dir);
    }
}

/**
 * Recursively set files/folders permissions
 */
function set_permissions_recursive($dir) {
    if (!is_dir($dir)) return;
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );
    foreach ($iterator as $item) {
        $path = $item->getPathname();
        if ($item->isDir()) {
            @chmod($path, 0755);
        } else {
            $filename = $item->getFilename();
            $parent_dir = basename(dirname($path));
            if ($parent_dir === 'scripts' || $filename === 'index.html' || substr($filename, -4) === '.raw') {
                @chmod($path, 0755);
            } else {
                @chmod($path, 0644);
            }
        }
    }
    @chmod($dir, 0755);
}

/**
 * Command execution helper to bypass disabled shell_exec
 */
function wp_exec($cmd) {
    $output = [];
    $retval = null;
    @exec($cmd, $output, $retval);
    if ($retval !== 0 && empty($output)) {
        return null;
    }
    return implode("\n", $output);
}

/**
 * Safely add or remove rules in a .htaccess file using markers
 */
function wp_manager_htaccess_modify($htaccess_path, $marker, $rules, $enable) {
    $content = file_exists($htaccess_path) ? file_get_contents($htaccess_path) : '';
    $start_marker = "# BEGIN WP_MANAGER_{$marker}";
    $end_marker = "# END WP_MANAGER_{$marker}";
    
    // Remove existing marker block if any
    $pattern = "/# BEGIN WP_MANAGER_" . preg_quote($marker, '/') . ".*# END WP_MANAGER_" . preg_quote($marker, '/') . "/s";
    $content = preg_replace($pattern, '', $content);
    $content = trim($content);
    
    if ($enable) {
        $block = "\n\n{$start_marker}\n" . trim($rules) . "\n{$end_marker}\n";
        $content .= $block;
    }
    
    $dir = dirname($htaccess_path);
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }
    
    @file_put_contents($htaccess_path, trim($content) . "\n");
    @chmod($htaccess_path, 0644);
}

/**
 * Check if wp-config.php is writable, throwing clear error if not (e.g. Chmod 400 or locked)
 */
function check_wp_config_writable($wp_config_path) {
    if (!file_exists($wp_config_path)) {
        throw new Exception("Safety block: wp-config.php không tồn tại.");
    }
    if (!is_writable($wp_config_path)) {
        throw new Exception("Không thể ghi vào file wp-config.php. File đang bị giới hạn quyền truy cập (ví dụ: Chmod 400) hoặc bị khóa ghi. Vui lòng kiểm tra và phân lại quyền cho file (ví dụ: Chmod 644) trước khi thực hiện.");
    }
}

/**
 * Safely add/change or remove constant definitions in wp-config.php
 */
function wp_manager_config_define_modify($wp_config_path, $constant, $value, $enable) {
    if (!file_exists($wp_config_path)) return;
    check_wp_config_writable($wp_config_path);
    $content = file_get_contents($wp_config_path);
    
    // Remove existing define if present
    $pattern = "/\s*define\s*\(\s*['\"]" . preg_quote($constant, '/') . "['\"]\s*,\s*[^;]*\)\s*;/i";
    $content = preg_replace($pattern, '', $content);
    
    if ($enable) {
        $val_str = is_bool($value) ? ($value ? 'true' : 'false') : "'" . addslashes($value) . "'";
        $define_str = "\ndefine('{$constant}', {$val_str});\n";
        
        $insert_pos = strpos($content, "/* That's all, stop editing!");
        if ($insert_pos === false) {
            $insert_pos = strpos($content, "require_once");
        }
        
        if ($insert_pos !== false) {
            $content = substr_replace($content, $define_str, $insert_pos, 0);
        } else {
            $content .= $define_str;
        }
    }
    
    @file_put_contents($wp_config_path, $content);
}

/**
 * Helper to fetch database connection details from wp-config.php
 */
function wp_manager_get_db_conn($wp_config_path) {
    if (!file_exists($wp_config_path)) return null;
    $content = file_get_contents($wp_config_path);
    preg_match("/define\s*\(\s*['\"]DB_NAME['\"]\s*,\s*['\"](.*?)['\"]\s*\)/", $content, $db_name_match);
    preg_match("/define\s*\(\s*['\"]DB_USER['\"]\s*,\s*['\"](.*?)['\"]\s*\)/", $content, $db_user_match);
    preg_match("/define\s*\(\s*['\"]DB_PASSWORD['\"]\s*,\s*['\"](.*?)['\"]\s*\)/", $content, $db_pass_match);
    preg_match("/define\s*\(\s*['\"]DB_HOST['\"]\s*,\s*['\"](.*?)['\"]\s*\)/", $content, $db_host_match);
    preg_match("/\\\$table_prefix\s*=\s*['\"](.*?)['\"]/", $content, $prefix_match);
    
    $db_name = $db_name_match[1] ?? '';
    $db_user = $db_user_match[1] ?? '';
    $db_pass = $db_pass_match[1] ?? '';
    $db_host = $db_host_match[1] ?? 'localhost';
    $db_prefix = $prefix_match[1] ?? 'wp_';
    
    try {
        $dsn = "mysql:host={$db_host};dbname={$db_name};charset=utf8mb4";
        $pdo = new PDO($dsn, $db_user, $db_pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_TIMEOUT => 2
        ]);
        return ['pdo' => $pdo, 'prefix' => $db_prefix];
    } catch (Exception $e) {
        return null;
    }
}

/**
 * Bootstrap WordPress auth helpers without running update checks.
 */
function wp_manager_bootstrap_auth($site_path) {
    static $bootstrapped_path = null;

    $wp_load = rtrim($site_path, '/') . '/wp-load.php';
    if (!file_exists($wp_load)) {
        throw new Exception("wp-load.php not found. This does not look like a complete WordPress installation.");
    }

    $_SERVER['HTTP_HOST'] = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $_SERVER['SERVER_NAME'] = $_SERVER['SERVER_NAME'] ?? $_SERVER['HTTP_HOST'];
    $_SERVER['REQUEST_URI'] = $_SERVER['REQUEST_URI'] ?? '/wp-admin/';
    $_SERVER['SERVER_PROTOCOL'] = $_SERVER['SERVER_PROTOCOL'] ?? 'HTTP/1.1';
    $_SERVER['REQUEST_METHOD'] = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    $_SERVER['REMOTE_ADDR'] = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';

    chdir($site_path);
    $real_site_path = realpath($site_path) ?: $site_path;
    if ($bootstrapped_path !== null && $bootstrapped_path !== $real_site_path) {
        throw new Exception("Cannot bootstrap multiple WordPress installations in the same request.");
    }
    if ($bootstrapped_path === null) {
        require_once $wp_load;
        $bootstrapped_path = $real_site_path;
    }
    require_once ABSPATH . 'wp-includes/pluggable.php';
}

/**
 * Get security status of 18 measures for a site
 */
function get_wordpress_security_status($site_path) {
    $wp_config_path = $site_path . '/wp-config.php';
    if (!file_exists($wp_config_path)) {
        throw new Exception("wp-config.php not found at site path.");
    }
    
    $htaccess_path = $site_path . '/.htaccess';
    $config_content = file_get_contents($wp_config_path);
    $htaccess_content = file_exists($htaccess_path) ? file_get_contents($htaccess_path) : '';
    
    $status = [];
    
    // 1. restrict_files
    $perms = fileperms($wp_config_path) & 0777;
    $status['restrict_files'] = ($perms <= 0640 || $perms === 0600 || $perms === 0400 || $perms === 0440);
    
    // 2. security_keys
    preg_match("/define\s*\(\s*['\"]AUTH_KEY['\"]\s*,\s*['\"](.*?)['\"]\s*\)/", $config_content, $auth_match);
    $auth_key = $auth_match[1] ?? '';
    $status['security_keys'] = (!empty($auth_key) && $auth_key !== 'put your unique phrase here' && strlen($auth_key) > 20);
    
    // 3. db_prefix
    preg_match("/\\\$table_prefix\s*=\s*['\"](.*?)['\"]/", $config_content, $prefix_match);
    $prefix = $prefix_match[1] ?? 'wp_';
    $status['db_prefix'] = ($prefix !== 'wp_');
    
    // 4. block_xmlrpc
    $status['block_xmlrpc'] = (strpos($htaccess_content, 'WP_MANAGER_BLOCK_XMLRPC') !== false);
    
    // 5. forbid_php_includes
    $inc_htaccess = $site_path . '/wp-includes/.htaccess';
    $status['forbid_php_includes'] = file_exists($inc_htaccess) && (strpos(file_get_contents($inc_htaccess), 'WP_MANAGER_FORBID_PHP') !== false);
    
    // 6. forbid_php_uploads
    $up_htaccess = $site_path . '/wp-content/uploads/.htaccess';
    $status['forbid_php_uploads'] = file_exists($up_htaccess) && (strpos(file_get_contents($up_htaccess), 'WP_MANAGER_FORBID_PHP') !== false);
    
    // 7. disable_scripts_concat
    $status['disable_scripts_concat'] = (preg_match("/define\s*\(\s*['\"]CONCATENATE_SCRIPTS['\"]\s*,\s*false\s*\)/i", $config_content) === 1);
    
    // 8. turn_off_pingbacks
    $status['turn_off_pingbacks'] = false;
    $db = wp_manager_get_db_conn($wp_config_path);
    if ($db) {
        try {
            $stmt = $db['pdo']->prepare("SELECT option_value FROM `{$db['prefix']}options` WHERE option_name = 'default_pingback_accept'");
            $stmt->execute();
            $val = $stmt->fetchColumn();
            $status['turn_off_pingbacks'] = ($val === '0');
        } catch (Exception $e) {}
    }
    
    // 9. disallow_file_edit
    $status['disallow_file_edit'] = (preg_match("/define\s*\(\s*['\"]DISALLOW_FILE_EDIT['\"]\s*,\s*true\s*\)/i", $config_content) === 1);
    
    // 10. bot_protection
    $status['bot_protection'] = (strpos($htaccess_content, 'WP_MANAGER_BOT_PROTECTION') !== false);
    
    // 11. block_sensitive_files
    $status['block_sensitive_files'] = (strpos($htaccess_content, 'WP_MANAGER_BLOCK_SENSITIVE') !== false);
    
    // 12. block_htaccess
    $status['block_htaccess'] = (strpos($htaccess_content, 'WP_MANAGER_BLOCK_HTACCESS') !== false);
    
    // 13. block_author_scans
    $status['block_author_scans'] = (strpos($htaccess_content, 'WP_MANAGER_BLOCK_AUTHOR_SCANS') !== false);
    
    // 14. block_directory_browsing
    $status['block_directory_browsing'] = (strpos($htaccess_content, 'Options -Indexes') !== false || strpos($htaccess_content, 'WP_MANAGER_BLOCK_DIRECTORY_BROWSING') !== false);
    
    // 15. block_wp_config
    $status['block_wp_config'] = (strpos($htaccess_content, 'WP_MANAGER_BLOCK_WP_CONFIG') !== false);
    
    // 16. disable_php_cache
    $cache_htaccess = $site_path . '/wp-content/cache/.htaccess';
    $status['disable_php_cache'] = file_exists($cache_htaccess) && (strpos(file_get_contents($cache_htaccess), 'WP_MANAGER_FORBID_PHP') !== false);
    
    // 17. block_sensitive_extensions
    $status['block_sensitive_extensions'] = (strpos($htaccess_content, 'WP_MANAGER_BLOCK_EXTENSIONS') !== false);
    
    // 18. rename_admin_user
    $status['rename_admin_user'] = true;
    if ($db) {
        try {
            $stmt = $db['pdo']->prepare("SELECT COUNT(*) FROM `{$db['prefix']}users` WHERE user_login = 'admin'");
            $stmt->execute();
            $count = $stmt->fetchColumn();
            $status['rename_admin_user'] = ($count == 0);
        } catch (Exception $e) {}
    }
    
    // 19. wordpress_lockdown
    $status['wordpress_lockdown'] = is_wordpress_locked($site_path);
    
    // Detect Nginx using cURL HEAD request
    $is_nginx = false;
    $siteurl = '';
    if ($db) {
        try {
            $stmt = $db['pdo']->prepare("SELECT option_value FROM `{$db['prefix']}options` WHERE option_name = 'siteurl'");
            $stmt->execute();
            $siteurl = $stmt->fetchColumn();
        } catch (Exception $e) {}
    }
    
    if (empty($siteurl)) {
        $username = getenv('USERNAME') ?: getenv('USER') ?: 'nobody';
        $home = getenv('HOME') ?: "/home/{$username}";
        if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
            $home = 'C:/Users/local_user';
        }
        $domain = '';
        $sub_path = '';
        $domains_prefix = $home . '/domains/';
        $site_path_real = realpath($site_path) ?: $site_path;
        if (strpos($site_path_real, $domains_prefix) === 0) {
            $relative = substr($site_path_real, strlen($domains_prefix));
            $parts = explode('/', str_replace('\\', '/', $relative));
            if (count($parts) > 0) {
                $domain = $parts[0];
                $pub_index = array_search('public_html', $parts);
                if ($pub_index !== false && $pub_index < count($parts) - 1) {
                    $sub_path = implode('/', array_slice($parts, $pub_index + 1));
                }
            }
        }
        $siteurl = 'http://' . ($domain ?: 'localhost') . ($sub_path !== '' ? '/' . $sub_path : '');
    }
    
    if (!empty($siteurl)) {
        $ch = curl_init($siteurl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADER, true);
        curl_setopt($ch, CURLOPT_NOBODY, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 3);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        $headers = curl_exec($ch);
        curl_close($ch);
        if ($headers && preg_match('/Server:\s*nginx/i', $headers)) {
            $is_nginx = true;
        }
    }
    
    $status['is_nginx'] = $is_nginx;
    
    return $status;
}

/**
 * Toggle security measure on/off for a site
 */
function toggle_wordpress_security_measure($site_path, $measure, $enable, $params = []) {
    $wp_config_path = $site_path . '/wp-config.php';
    if (!file_exists($wp_config_path)) {
        throw new Exception("wp-config.php not found at site path.");
    }
    
    // --- WP Lock conflict detection ---
    // Measures that need to write to wp-config.php
    $wp_config_write_measures = ['security_keys', 'db_prefix', 'disable_scripts_concat', 'disallow_file_edit'];
    // Measures that need to write to wp-includes/
    $wp_includes_write_measures = ['forbid_php_includes'];
    
    if (in_array($measure, $wp_config_write_measures)) {
        if (is_wordpress_locked($site_path)) {
            throw new Exception(
                "Không thể ghi vào wp-config.php. File đang bị khóa (immutable/chattr). " .
                "Vui lòng tắt WP Lock trong tab 'Overview Details' trước khi thay đổi tính năng này."
            );
        }
        check_wp_config_writable($wp_config_path);
    }
    
    if (in_array($measure, $wp_includes_write_measures)) {
        $wp_includes = $site_path . '/wp-includes';
        if (is_dir($wp_includes) && !is_writable($wp_includes)) {
            throw new Exception(
                "Không thể ghi vào thư mục wp-includes/. Thư mục đang bị khóa (immutable/chattr). " .
                "Vui lòng tắt WP Lock trong tab 'Overview Details' trước khi thay đổi tính năng này."
            );
        }
    }
    // --- End lock detection ---
    
    $htaccess_path = $site_path . '/.htaccess';

    
    switch ($measure) {
        case 'wordpress_lockdown':
            if ($enable) {
                lock_wordpress_instance($site_path);
            } else {
                unlock_wordpress_instance($site_path);
            }
            break;

        case 'restrict_files':
            if ($enable) {
                @chmod($wp_config_path, 0400);
            } else {
                @chmod($wp_config_path, 0644);
            }
            break;
            
        case 'security_keys':
            if ($enable) {
                $salts = '';
                $ch = curl_init('https://api.wordpress.org/secret-key/1.1/salt/');
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_TIMEOUT, 5);
                $salts = curl_exec($ch);
                curl_close($ch);
                
                if (!$salts || strpos($salts, 'define') === false) {
                    $salts = '';
                    $keys = ['AUTH_KEY', 'SECURE_AUTH_KEY', 'LOGGED_IN_KEY', 'NONCE_KEY', 'AUTH_SALT', 'SECURE_AUTH_SALT', 'LOGGED_IN_SALT', 'NONCE_SALT'];
                    foreach ($keys as $key) {
                        $random_salt = bin2hex(random_bytes(32));
                        $salts .= "define('{$key}', '{$random_salt}');\n";
                    }
                }
                
                $content = file_get_contents($wp_config_path);
                $keys = ['AUTH_KEY', 'SECURE_AUTH_KEY', 'LOGGED_IN_KEY', 'NONCE_KEY', 'AUTH_SALT', 'SECURE_AUTH_SALT', 'LOGGED_IN_SALT', 'NONCE_SALT'];
                foreach ($keys as $key) {
                    $content = preg_replace("/\s*define\s*\(\s*['\"]" . preg_quote($key, '/') . "['\"]\s*,\s*[^;]*\)\s*;/i", '', $content);
                }
                
                $insert_pos = strpos($content, "/* That's all, stop editing!");
                if ($insert_pos === false) {
                    $insert_pos = strpos($content, "require_once");
                }
                
                if ($insert_pos !== false) {
                    $content = substr_replace($content, "\n" . $salts . "\n", $insert_pos, 0);
                } else {
                    $content .= "\n" . $salts . "\n";
                }
                
                file_put_contents($wp_config_path, $content);
            }
            break;
            
        case 'db_prefix':
            if ($enable) {
                $new_prefix = $params['new_prefix'] ?? '';
                if (!preg_match('/^[a-z0-9_]+$/i', $new_prefix)) {
                    throw new Exception("Invalid database prefix format. Use alphanumeric characters and underscores.");
                }
                
                $db = wp_manager_get_db_conn($wp_config_path);
                if (!$db) {
                    throw new Exception("Unable to establish database connection to modify prefix.");
                }
                
                $pdo = $db['pdo'];
                $old_prefix = $db['prefix'];
                if ($old_prefix === $new_prefix) {
                    break;
                }
                
                $stmt = $pdo->prepare("SHOW TABLES LIKE " . $pdo->quote($old_prefix . "%"));
                $stmt->execute();
                $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
                
                if (empty($tables)) {
                    throw new Exception("No database tables found matching old prefix: {$old_prefix}");
                }
                
                foreach ($tables as $table) {
                    $new_table_name = $new_prefix . substr($table, strlen($old_prefix));
                    $pdo->exec("RENAME TABLE `{$table}` TO `{$new_table_name}`");
                }
                
                $pdo->exec("UPDATE `{$new_prefix}options` SET option_name = REPLACE(option_name, '{$old_prefix}', '{$new_prefix}') WHERE option_name LIKE '{$old_prefix}%'");
                $pdo->exec("UPDATE `{$new_prefix}usermeta` SET meta_key = REPLACE(meta_key, '{$old_prefix}', '{$new_prefix}') WHERE meta_key LIKE '{$old_prefix}%'");
                
                $content = file_get_contents($wp_config_path);
                $content = preg_replace("/\\\$table_prefix\s*=\s*['\"].*?['\"]\s*;/", "\$table_prefix = '" . addslashes($new_prefix) . "';", $content);
                file_put_contents($wp_config_path, $content);
            }
            break;
            
        case 'block_xmlrpc':
            $rules = "<Files xmlrpc.php>\n" .
                     "Order Deny,Allow\n" .
                     "Deny from all\n" .
                     "</Files>";
            wp_manager_htaccess_modify($htaccess_path, 'BLOCK_XMLRPC', $rules, $enable);
            break;
            
        case 'forbid_php_includes':
            $inc_htaccess = $site_path . '/wp-includes/.htaccess';
            $rules = "<Files *.php>\n" .
                     "Order Deny,Allow\n" .
                     "Deny from all\n" .
                     "</Files>\n" .
                     "<Files wp-tinymce.php>\n" .
                     "Order Allow,Deny\n" .
                     "Allow from all\n" .
                     "</Files>\n" .
                     "<Files ms-files.php>\n" .
                     "Order Allow,Deny\n" .
                     "Allow from all\n" .
                     "</Files>";
            wp_manager_htaccess_modify($inc_htaccess, 'FORBID_PHP', $rules, $enable);
            if (!$enable && file_exists($inc_htaccess) && filesize($inc_htaccess) === 0) {
                @unlink($inc_htaccess);
            }
            break;
            
        case 'forbid_php_uploads':
            $up_htaccess = $site_path . '/wp-content/uploads/.htaccess';
            $rules = "<Files *.php>\n" .
                     "Order Deny,Allow\n" .
                     "Deny from all\n" .
                     "</Files>";
            wp_manager_htaccess_modify($up_htaccess, 'FORBID_PHP', $rules, $enable);
            if (!$enable && file_exists($up_htaccess) && filesize($up_htaccess) === 0) {
                @unlink($up_htaccess);
            }
            break;
            
        case 'disable_scripts_concat':
            wp_manager_config_define_modify($wp_config_path, 'CONCATENATE_SCRIPTS', false, $enable);
            break;
            
        case 'turn_off_pingbacks':
            $db = wp_manager_get_db_conn($wp_config_path);
            if (!$db) {
                throw new Exception("Unable to connect to database.");
            }
            $val = $enable ? '0' : '1';
            $stmt = $db['pdo']->prepare("UPDATE `{$db['prefix']}options` SET option_value = ? WHERE option_name = 'default_pingback_accept'");
            $stmt->execute([$val]);
            break;
            
        case 'disallow_file_edit':
            wp_manager_config_define_modify($wp_config_path, 'DISALLOW_FILE_EDIT', true, $enable);
            break;
            
        case 'bot_protection':
            $rules = "<IfModule mod_rewrite.c>\n" .
                     "RewriteEngine On\n" .
                     "RewriteCond %{HTTP_USER_AGENT} (custombot|curl|wget|python|libwww|scrapy) [NC]\n" .
                     "RewriteRule .* - [F,L]\n" .
                     "</IfModule>";
            wp_manager_htaccess_modify($htaccess_path, 'BOT_PROTECTION', $rules, $enable);
            break;
            
        case 'block_sensitive_files':
            $rules = "<FilesMatch \"^(readme\\.html|license\\.txt|wp-config-sample\\.php)$\">\n" .
                     "Order Deny,Allow\n" .
                     "Deny from all\n" .
                     "</FilesMatch>";
            wp_manager_htaccess_modify($htaccess_path, 'BLOCK_SENSITIVE', $rules, $enable);
            break;
            
        case 'block_htaccess':
            $rules = "<Files ~ \"^.*\\.([Hh][Tt][AaPp])\">\n" .
                     "Order Deny,Allow\n" .
                     "Deny from all\n" .
                     "</Files>";
            wp_manager_htaccess_modify($htaccess_path, 'BLOCK_HTACCESS', $rules, $enable);
            break;
            
        case 'block_author_scans':
            $rules = "<IfModule mod_rewrite.c>\n" .
                     "RewriteEngine On\n" .
                     "RewriteCond %{QUERY_STRING} author=\\d [NC]\n" .
                     "RewriteRule .* - [F,L]\n" .
                     "</IfModule>";
            wp_manager_htaccess_modify($htaccess_path, 'BLOCK_AUTHOR_SCANS', $rules, $enable);
            break;
            
        case 'block_directory_browsing':
            $rules = "Options -Indexes";
            wp_manager_htaccess_modify($htaccess_path, 'BLOCK_DIRECTORY_BROWSING', $rules, $enable);
            break;
            
        case 'block_wp_config':
            $rules = "<Files wp-config.php>\n" .
                     "Order Deny,Allow\n" .
                     "Deny from all\n" .
                     "</Files>";
            wp_manager_htaccess_modify($htaccess_path, 'BLOCK_WP_CONFIG', $rules, $enable);
            break;
            
        case 'disable_php_cache':
            $cache_htaccess = $site_path . '/wp-content/cache/.htaccess';
            $rules = "<Files *.php>\n" .
                     "Order Deny,Allow\n" .
                     "Deny from all\n" .
                     "</Files>";
            wp_manager_htaccess_modify($cache_htaccess, 'FORBID_PHP', $rules, $enable);
            if (!$enable && file_exists($cache_htaccess) && filesize($cache_htaccess) === 0) {
                @unlink($cache_htaccess);
            }
            break;
            
        case 'block_sensitive_extensions':
            $rules = "<FilesMatch \"\\.(bak|config|sql|fla|psd|ini|log|sh|inc|swp|dist)$\">\n" .
                     "Order Deny,Allow\n" .
                     "Deny from all\n" .
                     "</FilesMatch>";
            wp_manager_htaccess_modify($htaccess_path, 'BLOCK_EXTENSIONS', $rules, $enable);
            break;
            
        case 'rename_admin_user':
            if ($enable) {
                $new_admin_username = $params['new_admin_username'] ?? '';
                if (empty($new_admin_username) || !preg_match('/^[a-z0-9_\-\.]+$/i', $new_admin_username)) {
                    throw new Exception("Invalid username format.");
                }
                
                $db = wp_manager_get_db_conn($wp_config_path);
                if (!$db) {
                    throw new Exception("Unable to connect to database.");
                }
                
                $pdo = $db['pdo'];
                $prefix = $db['prefix'];
                
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM `{$prefix}users` WHERE user_login = ?");
                $stmt->execute([$new_admin_username]);
                if ($stmt->fetchColumn() > 0) {
                    throw new Exception("Username '{$new_admin_username}' already exists in database.");
                }
                
                $stmt = $pdo->prepare("UPDATE `{$prefix}users` SET user_login = ? WHERE user_login = 'admin'");
                $stmt->execute([$new_admin_username]);
            }
            break;
    }
    
    return true;
}

/**

 * Check if WordPress files are locked (immutable)
 */
function is_wordpress_locked($site_path) {
    if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
        return file_exists($site_path . '/.locked_mock');
    }
    $wp_config = $site_path . '/wp-config.php';
    if (!file_exists($wp_config)) return false;
    
    $lsattr_path = trim(wp_exec('which lsattr 2>/dev/null') ?: '/usr/bin/lsattr');
    $output = wp_exec("{$lsattr_path} " . escapeshellarg($wp_config) . " 2>/dev/null");
    if ($output) {
        $parts = preg_split('/\s+/', trim($output));
        if (!empty($parts[0]) && strpos($parts[0], 'i') !== false) {
            return true;
        }
    }
    return false;
}

/**
 * Lock WordPress files (make them immutable)
 */
function lock_wordpress_instance($site_path) {
    if (!file_exists($site_path . '/wp-config.php')) {
        throw new Exception("Safety block: Could not locate wp-config.php.");
    }
    if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
        file_put_contents($site_path . '/.locked_mock', '1');
        return [
            'success' => true,
            'message' => 'WordPress files successfully locked (Mock on Windows).'
        ];
    }
    
    $wrapper = '/usr/local/directadmin/plugins/ultimate-directadmin-wordpress-manager/scripts/wrapper';
    if (!file_exists($wrapper)) {
        $wrapper = dirname(__FILE__) . '/scripts/wrapper';
    }
    
    if (!file_exists($wrapper)) {
        throw new Exception("Lock failed: SUID wrapper binary not found. Please run the install script (install.sh) as root to compile the binary.");
    }
    
    $esc_path = escapeshellarg($site_path);
    $esc_wrapper = escapeshellarg($wrapper);
    $output = wp_exec("{$esc_wrapper} lock {$esc_path} 2>&1");
    
    if (!is_wordpress_locked($site_path)) {
        $err_msg = $output ? trim($output) : "lsattr did not detect the lock. SUID permissions might be missing.";
        throw new Exception("Lock failed: " . $err_msg . "\n\nPlease ensure your administrator compiled and set SUID permissions:\ngcc -O2 " . dirname($wrapper) . "/wrapper.c -o " . $wrapper . "\nchown root:diradmin " . $wrapper . "\nchmod 4755 " . $wrapper);
    }
    
    return [
        'success' => true,
        'message' => 'WordPress files successfully locked (Immutable).'
    ];
}

/**
 * Unlock WordPress files (make them writable)
 */
function unlock_wordpress_instance($site_path) {
    if (!file_exists($site_path . '/wp-config.php')) {
        throw new Exception("Safety block: Could not locate wp-config.php.");
    }
    if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
        @unlink($site_path . '/.locked_mock');
        return [
            'success' => true,
            'message' => 'WordPress files successfully unlocked (Mock on Windows).'
        ];
    }
    
    $wrapper = '/usr/local/directadmin/plugins/ultimate-directadmin-wordpress-manager/scripts/wrapper';
    if (!file_exists($wrapper)) {
        $wrapper = dirname(__FILE__) . '/scripts/wrapper';
    }
    
    if (!file_exists($wrapper)) {
        throw new Exception("Unlock failed: SUID wrapper binary not found. Please run the install script (install.sh) as root to compile the binary.");
    }
    
    $esc_path = escapeshellarg($site_path);
    $esc_wrapper = escapeshellarg($wrapper);
    $output = wp_exec("{$esc_wrapper} unlock {$esc_path} 2>&1");
    
    if (is_wordpress_locked($site_path)) {
        $err_msg = $output ? trim($output) : "lsattr still detects the lock. SUID permissions might be missing.";
        throw new Exception("Unlock failed: " . $err_msg . "\n\nPlease ensure your administrator compiled and set SUID permissions:\ngcc -O2 " . dirname($wrapper) . "/wrapper.c -o " . $wrapper . "\nchown root:diradmin " . $wrapper . "\nchmod 4755 " . $wrapper);
    }
    
    return [
        'success' => true,
        'message' => 'WordPress files successfully unlocked (Writable).'
    ];
}

/**
 * Extract WordPress plugin details from its header comments
 */
function get_plugin_details($file_path) {
    if (!file_exists($file_path)) return null;
    $content = file_get_contents($file_path, false, null, 0, 8192);
    if (!preg_match('/Plugin Name\s*:\s*(.*)/i', $content, $name_match)) {
        return null;
    }
    
    preg_match('/Version\s*:\s*(.*)/i', $content, $version_match);
    preg_match('/Description\s*:\s*(.*)/i', $content, $desc_match);
    preg_match('/Author\s*:\s*(.*)/i', $content, $author_match);
    
    return [
        'name' => trim(strip_tags($name_match[1])),
        'version' => isset($version_match[1]) ? trim(strip_tags($version_match[1])) : 'Unknown',
        'description' => isset($desc_match[1]) ? trim(strip_tags($desc_match[1])) : '',
        'author' => isset($author_match[1]) ? trim(strip_tags($author_match[1])) : ''
    ];
}

/**
 * List all installed plugins
 */
function list_plugins($site_path) {
    $plugins_dir = $site_path . '/wp-content/plugins';
    if (!is_dir($plugins_dir)) {
        return [];
    }
    
    $plugins = [];
    $entries = array_diff(scandir($plugins_dir), ['.', '..']);
    
    foreach ($entries as $entry) {
        $entry_path = $plugins_dir . '/' . $entry;
        if (is_dir($entry_path)) {
            $sub_files = glob($entry_path . '/*.php');
            if ($sub_files) {
                foreach ($sub_files as $sub_file) {
                    $details = get_plugin_details($sub_file);
                    if ($details) {
                        $plugin_file = $entry . '/' . basename($sub_file);
                        $plugins[$plugin_file] = $details;
                        break;
                    }
                }
            }
        } elseif (is_file($entry_path) && pathinfo($entry_path, PATHINFO_EXTENSION) === 'php') {
            $details = get_plugin_details($entry_path);
            if ($details) {
                $plugins[$entry] = $details;
            }
        }
    }
    return $plugins;
}

/**
 * Fetch active plugins from database
 */
function get_active_plugins($site_path) {
    $wp_config_path = $site_path . '/wp-config.php';
    if (!file_exists($wp_config_path)) return [];
    
    $content = file_get_contents($wp_config_path);
    preg_match("/define\s*\(\s*['\"]DB_NAME['\"]\s*,\s*['\"](.*?)['\"]\s*\)/", $content, $db_name_match);
    preg_match("/define\s*\(\s*['\"]DB_USER['\"]\s*,\s*['\"](.*?)['\"]\s*\)/", $content, $db_user_match);
    preg_match("/define\s*\(\s*['\"]DB_PASSWORD['\"]\s*,\s*['\"](.*?)['\"]\s*\)/", $content, $db_pass_match);
    preg_match("/define\s*\(\s*['\"]DB_HOST['\"]\s*,\s*['\"](.*?)['\"]\s*\)/", $content, $db_host_match);
    preg_match("/\\\$table_prefix\s*=\s*['\"](.*?)['\"]/", $content, $prefix_match);
    
    $db_name = $db_name_match[1] ?? '';
    $db_user = $db_user_match[1] ?? '';
    $db_pass = $db_pass_match[1] ?? '';
    $db_host = $db_host_match[1] ?? 'localhost';
    $db_prefix = $prefix_match[1] ?? 'wp_';
    
    try {
        $dsn = "mysql:host={$db_host};dbname={$db_name};charset=utf8mb4";
        $pdo = new PDO($dsn, $db_user, $db_pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_TIMEOUT => 2
        ]);
        
        $stmt = $pdo->prepare("SELECT option_value FROM `{$db_prefix}options` WHERE option_name = 'active_plugins'");
        $stmt->execute();
        $val = $stmt->fetchColumn();
        if ($val) {
            $unserialized = unserialize($val);
            if (is_array($unserialized)) {
                return $unserialized;
            }
        }
    } catch (Exception $e) {
        // fail silently
    }
    return [];
}

/**
 * Toggle plugin status (activate/deactivate) directly in the database
 */
function toggle_plugin_status($site_path, $plugin_file, $status) {
    // 1. Try toggling plugin using WP-CLI first (safer and runs in a separate process)
    try {
        $wp_cli = trim(wp_exec('which wp 2>/dev/null') ?: '/usr/local/bin/wp');
        if (file_exists($wp_cli) || wp_exec("hash wp 2>/dev/null; echo $?")) {
            $esc_path = escapeshellarg($site_path);
            $esc_plugin = escapeshellarg($plugin_file);
            $cmd_action = ($status === 'activate') ? 'activate' : 'deactivate';
            $output = wp_exec("{$wp_cli} plugin {$cmd_action} {$esc_plugin} --path={$esc_path} --allow-root 2>&1");
            if (strpos($output, 'Success:') !== false || strpos($output, 'Already') !== false) {
                wp_manager_log("Plugin toggled successfully using WP-CLI.");
                return true;
            }
            wp_manager_log("WP-CLI plugin toggle failed: " . $output . ". Falling back to direct DB update.");
        }
    } catch (Throwable $t) {
        wp_manager_log("WP-CLI execution error: " . $t->getMessage());
    }

    // 2. FALLBACK: Direct Database Update
    $wp_config_path = $site_path . '/wp-config.php';
    if (!file_exists($wp_config_path)) {
        throw new Exception("wp-config.php not found.");
    }
    
    $content = file_get_contents($wp_config_path);
    preg_match("/define\s*\(\s*['\"]DB_NAME['\"]\s*,\s*['\"](.*?)['\"]\s*\)/", $content, $db_name_match);
    preg_match("/define\s*\(\s*['\"]DB_USER['\"]\s*,\s*['\"](.*?)['\"]\s*\)/", $content, $db_user_match);
    preg_match("/define\s*\(\s*['\"]DB_PASSWORD['\"]\s*,\s*['\"](.*?)['\"]\s*\)/", $content, $db_pass_match);
    preg_match("/define\s*\(\s*['\"]DB_HOST['\"]\s*,\s*['\"](.*?)['\"]\s*\)/", $content, $db_host_match);
    preg_match("/\\\$table_prefix\s*=\s*['\"](.*?)['\"]/", $content, $prefix_match);
    
    $db_name = $db_name_match[1] ?? '';
    $db_user = $db_user_match[1] ?? '';
    $db_pass = $db_pass_match[1] ?? '';
    $db_host = $db_host_match[1] ?? 'localhost';
    $db_prefix = $prefix_match[1] ?? 'wp_';
    
    $dsn = "mysql:host={$db_host};dbname={$db_name};charset=utf8mb4";
    $pdo = new PDO($dsn, $db_user, $db_pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_TIMEOUT => 2
    ]);
    
    $stmt = $pdo->prepare("SELECT option_value FROM `{$db_prefix}options` WHERE option_name = 'active_plugins'");
    $stmt->execute();
    $val = $stmt->fetchColumn();
    
    $active_plugins = [];
    if ($val) {
        $unserialized = @unserialize($val);
        if (is_array($unserialized)) {
            $active_plugins = $unserialized;
        }
    }
    
    if ($status === 'activate') {
        if (!in_array($plugin_file, $active_plugins)) {
            $active_plugins[] = $plugin_file;
            $active_plugins = array_values(array_unique($active_plugins));
        }
    } else {
        $active_plugins = array_values(array_diff($active_plugins, [$plugin_file]));
    }
    
    $serialized = serialize($active_plugins);
    
    $stmt = $pdo->prepare("UPDATE `{$db_prefix}options` SET option_value = ? WHERE option_name = 'active_plugins'");
    $stmt->execute([$serialized]);

    // Attempt cache flush via WP-CLI
    try {
        $wp_cli = trim(wp_exec('which wp 2>/dev/null') ?: '/usr/local/bin/wp');
        if (file_exists($wp_cli) || wp_exec("hash wp 2>/dev/null; echo $?")) {
            $esc_path = escapeshellarg($site_path);
            wp_exec("{$wp_cli} cache flush --path={$esc_path} --allow-root 2>&1");
            wp_manager_log("Flushed cache via WP-CLI.");
        }
    } catch (Throwable $t) {
        // Ignore WP-CLI flush errors
    }
    
    return true;
}

/**
 * Extract WordPress theme details from style.css
 */
function get_theme_details($style_css_path) {
    if (!file_exists($style_css_path)) return null;
    $content = file_get_contents($style_css_path, false, null, 0, 8192);
    if (!preg_match('/Theme Name\s*:\s*(.*)/i', $content, $name_match)) {
        return null;
    }
    
    preg_match('/Version\s*:\s*(.*)/i', $content, $version_match);
    preg_match('/Description\s*:\s*(.*)/i', $content, $desc_match);
    preg_match('/Author\s*:\s*(.*)/i', $content, $author_match);
    
    return [
        'name' => trim(strip_tags($name_match[1])),
        'version' => isset($version_match[1]) ? trim(strip_tags($version_match[1])) : 'Unknown',
        'description' => isset($desc_match[1]) ? trim(strip_tags($desc_match[1])) : '',
        'author' => isset($author_match[1]) ? trim(strip_tags($author_match[1])) : ''
    ];
}

/**
 * List all installed themes
 */
function list_themes($site_path) {
    $themes_dir = $site_path . '/wp-content/themes';
    if (!is_dir($themes_dir)) {
        return [];
    }
    
    $themes = [];
    $entries = array_diff(scandir($themes_dir), ['.', '..']);
    
    foreach ($entries as $entry) {
        $entry_path = $themes_dir . '/' . $entry;
        if (is_dir($entry_path)) {
            $style_css = $entry_path . '/style.css';
            if (file_exists($style_css)) {
                $details = get_theme_details($style_css);
                if ($details) {
                    $details['folder'] = $entry;
                    $themes[] = $details;
                }
            }
        }
    }
    return $themes;
}

/**
 * Get active theme from database
 */
function get_active_theme($site_path) {
    $wp_config_path = $site_path . '/wp-config.php';
    if (!file_exists($wp_config_path)) return '';
    
    $content = file_get_contents($wp_config_path);
    preg_match("/define\s*\(\s*['\"]DB_NAME['\"]\s*,\s*['\"](.*?)['\"]\s*\)/", $content, $db_name_match);
    preg_match("/define\s*\(\s*['\"]DB_USER['\"]\s*,\s*['\"](.*?)['\"]\s*\)/", $content, $db_user_match);
    preg_match("/define\s*\(\s*['\"]DB_PASSWORD['\"]\s*,\s*['\"](.*?)['\"]\s*\)/", $content, $db_pass_match);
    preg_match("/define\s*\(\s*['\"]DB_HOST['\"]\s*,\s*['\"](.*?)['\"]\s*\)/", $content, $db_host_match);
    preg_match("/\\\$table_prefix\s*=\s*['\"](.*?)['\"]/", $content, $prefix_match);
    
    $db_name = $db_name_match[1] ?? '';
    $db_user = $db_user_match[1] ?? '';
    $db_pass = $db_pass_match[1] ?? '';
    $db_host = $db_host_match[1] ?? 'localhost';
    $db_prefix = $prefix_match[1] ?? 'wp_';
    
    try {
        $dsn = "mysql:host={$db_host};dbname={$db_name};charset=utf8mb4";
        $pdo = new PDO($dsn, $db_user, $db_pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_TIMEOUT => 2
        ]);
        
        $stmt = $pdo->prepare("SELECT option_value FROM `{$db_prefix}options` WHERE option_name = 'stylesheet'");
        $stmt->execute();
        return $stmt->fetchColumn() ?: '';
    } catch (Exception $e) {
        // fail silently
    }
    return '';
}

/**
 * Activate a theme in the database
 */
function activate_theme($site_path, $theme_folder) {
    // 1. Try activating theme using WP-CLI first (safer and runs in a separate process)
    try {
        $wp_cli = trim(wp_exec('which wp 2>/dev/null') ?: '/usr/local/bin/wp');
        if (file_exists($wp_cli) || wp_exec("hash wp 2>/dev/null; echo $?")) {
            $esc_path = escapeshellarg($site_path);
            $esc_theme = escapeshellarg($theme_folder);
            $output = wp_exec("{$wp_cli} theme activate {$esc_theme} --path={$esc_path} --allow-root 2>&1");
            if (strpos($output, 'Success:') !== false || strpos($output, 'Already') !== false) {
                wp_manager_log("Theme activated successfully using WP-CLI.");
                return true;
            }
            wp_manager_log("WP-CLI theme activation failed: " . $output . ". Falling back to direct DB update.");
        }
    } catch (Throwable $t) {
        wp_manager_log("WP-CLI execution error: " . $t->getMessage());
    }

    // 2. FALLBACK: Direct Database Update
    $wp_config_path = $site_path . '/wp-config.php';
    if (!file_exists($wp_config_path)) {
        throw new Exception("wp-config.php not found.");
    }
    
    $content = file_get_contents($wp_config_path);
    preg_match("/define\s*\(\s*['\"]DB_NAME['\"]\s*,\s*['\"](.*?)['\"]\s*\)/", $content, $db_name_match);
    preg_match("/define\s*\(\s*['\"]DB_USER['\"]\s*,\s*['\"](.*?)['\"]\s*\)/", $content, $db_user_match);
    preg_match("/define\s*\(\s*['\"]DB_PASSWORD['\"]\s*,\s*['\"](.*?)['\"]\s*\)/", $content, $db_pass_match);
    preg_match("/define\s*\(\s*['\"]DB_HOST['\"]\s*,\s*['\"](.*?)['\"]\s*\)/", $content, $db_host_match);
    preg_match("/\\\$table_prefix\s*=\s*['\"](.*?)['\"]/", $content, $prefix_match);
    
    $db_name = $db_name_match[1] ?? '';
    $db_user = $db_user_match[1] ?? '';
    $db_pass = $db_pass_match[1] ?? '';
    $db_host = $db_host_match[1] ?? 'localhost';
    $db_prefix = $prefix_match[1] ?? 'wp_';
    
    $dsn = "mysql:host={$db_host};dbname={$db_name};charset=utf8mb4";
    $pdo = new PDO($dsn, $db_user, $db_pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_TIMEOUT => 2
    ]);
    
    $template = $theme_folder;
    $theme_style_css = $site_path . '/wp-content/themes/' . $theme_folder . '/style.css';
    if (file_exists($theme_style_css)) {
        $style_content = file_get_contents($theme_style_css);
        if (preg_match('/Template\s*:\s*(.*)/i', $style_content, $tmpl_match)) {
            $template = trim($tmpl_match[1]);
        }
    }
    
    $stmt = $pdo->prepare("UPDATE `{$db_prefix}options` SET option_value = ? WHERE option_name = 'template'");
    $stmt->execute([$template]);
    
    $stmt = $pdo->prepare("UPDATE `{$db_prefix}options` SET option_value = ? WHERE option_name = 'stylesheet'");
    $stmt->execute([$theme_folder]);

    // Attempt cache flush via WP-CLI
    try {
        $wp_cli = trim(wp_exec('which wp 2>/dev/null') ?: '/usr/local/bin/wp');
        if (file_exists($wp_cli) || wp_exec("hash wp 2>/dev/null; echo $?")) {
            $esc_path = escapeshellarg($site_path);
            wp_exec("{$wp_cli} cache flush --path={$esc_path} --allow-root 2>&1");
            wp_manager_log("Flushed cache via WP-CLI.");
        }
    } catch (Throwable $t) {
        // Ignore WP-CLI flush errors
    }
    
    return true;
}

/**
 * Prepare a WordPress installation so internal upgrade APIs can run from DirectAdmin CGI.
 */
function wp_manager_bootstrap_wordpress($site_path) {
    static $bootstrapped_path = null;

    $wp_load = rtrim($site_path, '/') . '/wp-load.php';
    if (!file_exists($wp_load)) {
        throw new Exception("wp-load.php not found. This does not look like a complete WordPress installation.");
    }

    if (!defined('FS_METHOD')) {
        define('FS_METHOD', 'direct');
    }
    if (!defined('WP_ADMIN')) {
        define('WP_ADMIN', true);
    }

    $_SERVER['HTTP_HOST'] = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $_SERVER['SERVER_NAME'] = $_SERVER['SERVER_NAME'] ?? $_SERVER['HTTP_HOST'];
    $_SERVER['REQUEST_URI'] = $_SERVER['REQUEST_URI'] ?? '/wp-admin/';
    $_SERVER['SERVER_PROTOCOL'] = $_SERVER['SERVER_PROTOCOL'] ?? 'HTTP/1.1';
    $_SERVER['REQUEST_METHOD'] = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    $_SERVER['REMOTE_ADDR'] = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';

    chdir($site_path);
    $real_site_path = realpath($site_path) ?: $site_path;
    if ($bootstrapped_path !== null && $bootstrapped_path !== $real_site_path) {
        throw new Exception("Cannot bootstrap multiple WordPress installations in the same request.");
    }
    if ($bootstrapped_path === null) {
        require_once $wp_load;
        $bootstrapped_path = $real_site_path;
    }
    require_once ABSPATH . 'wp-admin/includes/file.php';
    require_once ABSPATH . 'wp-admin/includes/plugin.php';
    require_once ABSPATH . 'wp-admin/includes/theme.php';
    require_once ABSPATH . 'wp-admin/includes/update.php';
    require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';

    if (function_exists('wp_raise_memory_limit')) {
        wp_raise_memory_limit('admin');
    }
    if (function_exists('wp_update_plugins')) {
        wp_update_plugins();
    }
    if (function_exists('wp_update_themes')) {
        wp_update_themes();
    }
}

/**
 * Helper to fetch a site transient directly from options table via PDO (avoiding WP bootstrap)
 */
function get_site_transient_from_db($site_path, $transient_name) {
    $wp_config_path = $site_path . '/wp-config.php';
    if (!file_exists($wp_config_path)) return null;
    
    $content = file_get_contents($wp_config_path);
    preg_match("/define\s*\(\s*['\"]DB_NAME['\"]\s*,\s*['\"](.*?)['\"]\s*\)/", $content, $db_name_match);
    preg_match("/define\s*\(\s*['\"]DB_USER['\"]\s*,\s*['\"](.*?)['\"]\s*\)/", $content, $db_user_match);
    preg_match("/define\s*\(\s*['\"]DB_PASSWORD['\"]\s*,\s*['\"](.*?)['\"]\s*\)/", $content, $db_pass_match);
    preg_match("/define\s*\(\s*['\"]DB_HOST['\"]\s*,\s*['\"](.*?)['\"]\s*\)/", $content, $db_host_match);
    preg_match("/\\\$table_prefix\s*=\s*['\"](.*?)['\"]/", $content, $prefix_match);
    
    $db_name = $db_name_match[1] ?? '';
    $db_user = $db_user_match[1] ?? '';
    $db_pass = $db_pass_match[1] ?? '';
    $db_host = $db_host_match[1] ?? 'localhost';
    $db_prefix = $prefix_match[1] ?? 'wp_';
    
    try {
        $dsn = "mysql:host={$db_host};dbname={$db_name};charset=utf8mb4";
        $pdo = new PDO($dsn, $db_user, $db_pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_TIMEOUT => 2
        ]);
        
        $option_name = '_site_transient_' . $transient_name;
        $stmt = $pdo->prepare("SELECT option_value FROM `{$db_prefix}options` WHERE option_name = ?");
        $stmt->execute([$option_name]);
        $val = $stmt->fetchColumn();
        if ($val) {
            return @unserialize($val);
        }
    } catch (Exception $e) {
        wp_manager_log("PDO unable to fetch transient {$transient_name}: " . $e->getMessage());
    }
    return null;
}

/**
 * Fetch available plugin updates from WordPress update transients directly from the database.
 */
function get_wordpress_plugin_update_info($site_path) {
    $updates = [];
    try {
        $transient = get_site_transient_from_db($site_path, 'update_plugins');
        if ($transient) {
            $response = is_array($transient) ? ($transient['response'] ?? []) : ($transient->response ?? []);
            $no_update = is_array($transient) ? ($transient['no_update'] ?? []) : ($transient->no_update ?? []);
            
            foreach (['response' => $response, 'no_update' => $no_update] as $bucket => $list) {
                $has_update = ($bucket === 'response');
                if (!empty($list) && (is_array($list) || is_object($list))) {
                    foreach ($list as $file => $item) {
                        $new_version = '';
                        $package = '';
                        if (is_object($item)) {
                            $new_version = $item->new_version ?? '';
                            $package = $item->package ?? '';
                        } elseif (is_array($item)) {
                            $new_version = $item['new_version'] ?? '';
                            $package = $item['package'] ?? '';
                        }
                        
                        $updates[$file] = [
                            'latest_version' => $new_version,
                            'update_available' => $has_update,
                            'update_package_available' => $has_update && !empty($package)
                        ];
                    }
                }
            }
        }
    } catch (Throwable $e) {
        wp_manager_log("Unable to parse plugin update info from DB: " . $e->getMessage());
    }
    return $updates;
}

/**
 * Fetch available theme updates from WordPress update transients directly from the database.
 */
function get_wordpress_theme_update_info($site_path) {
    $updates = [];
    try {
        $transient = get_site_transient_from_db($site_path, 'update_themes');
        if ($transient) {
            $response = is_array($transient) ? ($transient['response'] ?? []) : ($transient->response ?? []);
            $no_update = is_array($transient) ? ($transient['no_update'] ?? []) : ($transient->no_update ?? []);
            
            foreach (['response' => $response, 'no_update' => $no_update] as $bucket => $list) {
                $has_update = ($bucket === 'response');
                if (!empty($list) && (is_array($list) || is_object($list))) {
                    foreach ($list as $folder => $item) {
                        $new_version = '';
                        $package = '';
                        if (is_object($item)) {
                            $new_version = $item->new_version ?? '';
                            $package = $item->package ?? '';
                        } elseif (is_array($item)) {
                            $new_version = $item['new_version'] ?? '';
                            $package = $item['package'] ?? '';
                        }
                        
                        $updates[$folder] = [
                            'latest_version' => $new_version,
                            'update_available' => $has_update,
                            'update_package_available' => $has_update && !empty($package)
                        ];
                    }
                }
            }
        }
    } catch (Throwable $e) {
        wp_manager_log("Unable to parse theme update info from DB: " . $e->getMessage());
    }
    return $updates;
}

/**
 * Copy WordPress core files from an extracted wordpress.org package.
 */
function wp_manager_copy_core_files($src_dir, $target_dir) {
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($src_dir, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );

    foreach ($iterator as $item) {
        $sub_path = str_replace('\\', '/', $iterator->getSubPathName());
        if ($sub_path === 'wp-content' || strpos($sub_path, 'wp-content/') === 0) {
            continue;
        }

        $target = rtrim($target_dir, '/') . '/' . $sub_path;
        if ($item->isDir()) {
            if (!is_dir($target)) {
                mkdir($target, 0755, true);
            }
        } else {
            $parent = dirname($target);
            if (!is_dir($parent)) {
                mkdir($parent, 0755, true);
            }
            copy($item->getPathname(), $target);
        }
    }
}

/**
 * Update WordPress core to the latest wordpress.org release.
 */
function update_wordpress_core($site_path, $home) {
    if (!file_exists($site_path . '/wp-config.php')) {
        throw new Exception("Safety block: wp-config.php not found.");
    }
    if (is_wordpress_locked($site_path)) {
        throw new Exception("Website is under WordPress Lockdown. Please disable Lockdown before updating WordPress core.");
    }

    $cache_dir = $home . '/.wp-cache';
    if (!is_dir($cache_dir)) {
        mkdir($cache_dir, 0755, true);
    }

    $zip_path = $cache_dir . '/latest.zip';
    $ch = curl_init('https://wordpress.org/latest.zip');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 120);
    $data = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($http_code !== 200 || !$data) {
        throw new Exception("Unable to download the latest WordPress core package.");
    }
    file_put_contents($zip_path, $data);

    $extract_dir = $cache_dir . '/core_update_' . time();
    $maintenance = $site_path . '/.maintenance';
    $legacy_maintenance = $site_path . '/.maintainance';
    @unlink($maintenance);
    @unlink($legacy_maintenance);
    register_shutdown_function(function() use ($maintenance, $legacy_maintenance) {
        if (file_exists($maintenance)) {
            @unlink($maintenance);
        }
        if (file_exists($legacy_maintenance)) {
            @unlink($legacy_maintenance);
        }
    });

    try {
        $zip = new ZipArchive;
        if ($zip->open($zip_path) !== TRUE) {
            throw new Exception("Failed to open WordPress core ZIP.");
        }
        mkdir($extract_dir, 0755, true);
        $zip->extractTo($extract_dir);
        $zip->close();

        $src_dir = $extract_dir . '/wordpress';
        if (!is_dir($src_dir)) {
            throw new Exception("Downloaded WordPress package has an unexpected structure.");
        }

        file_put_contents($maintenance, "<?php \$upgrading = " . time() . ";");
        @chmod($maintenance, 0644);

        // Clean up WordPress core files/directories.
        // Only delete standard WordPress core directories (wp-admin, wp-includes) and files in the root (except configurations).
        // Preserve other directories (subdomains, subfolder installations, custom folders) to prevent data loss.
        $files = scandir($site_path);
        if ($files !== false) {
            foreach ($files as $file) {
                if ($file === '.' || $file === '..') {
                    continue;
                }
                $full_path = $site_path . '/' . $file;
                if ($file === 'wp-content' || $file === 'wp-config.php') {
                    continue;
                }
                if ($file === '.htaccess' || $file === 'php.ini' || $file === '.user.ini') {
                    continue;
                }
                
                if (is_dir($full_path)) {
                    if ($file === 'wp-admin' || $file === 'wp-includes') {
                        rmdir_recursive($full_path);
                    } else {
                        wp_manager_log("Preserving custom/subfolder directory: " . $file);
                        continue;
                    }
                } else {
                    @unlink($full_path);
                }
            }
        }

        wp_manager_copy_core_files($src_dir, $site_path);

        $buffer_level = ob_get_level();
        ob_start();
        try {
            require_once $site_path . '/wp-load.php';
            require_once ABSPATH . 'wp-admin/includes/upgrade.php';
            if (function_exists('wp_upgrade')) {
                wp_upgrade();
            }
        } catch (Throwable $e) {
            wp_manager_log("Core updated but database upgrade failed: " . $e->getMessage());
        }
        while (ob_get_level() > $buffer_level) {
            ob_end_clean();
        }
    } finally {
        if (file_exists($maintenance)) {
            @unlink($maintenance);
        }
        if (file_exists($legacy_maintenance)) {
            @unlink($legacy_maintenance);
        }
        if (is_dir($extract_dir)) {
            rmdir_recursive($extract_dir);
        }
    }

    $cache_file = $home . '/.ultimate_wp_manager.json';
    if (file_exists($cache_file)) {
        @unlink($cache_file);
    }

    return ['success' => true, 'message' => 'WordPress core updated successfully.'];
}

/**
 * Update one installed WordPress plugin by downloading ZIP from WordPress.org and cleaning.
 */
function update_wordpress_plugin($site_path, $plugin_file) {
    if (is_wordpress_locked($site_path)) {
        throw new Exception("Website is under WordPress Lockdown. Please disable Lockdown before updating plugins.");
    }

    $slug = (strpos($plugin_file, '/') !== false) ? explode('/', $plugin_file)[0] : basename($plugin_file, '.php');
    $url = "https://downloads.wordpress.org/plugin/{$slug}.zip";

    // Download the ZIP file
    $temp_zip = sys_get_temp_dir() . '/update_plugin_' . time() . '.zip';
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 60);
    curl_setopt($ch, CURLOPT_USERAGENT, 'WordPress-Plugin-Updater');
    $data = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $fallback_used = false;

    if ($http_code !== 200 || !$data) {
        $fallback_used = true;
        wp_manager_log("Plugin ZIP not found on WordPress.org for {$slug}. Falling back to native WordPress upgrade...");
        
        $_GET['force-check'] = 1;
        $_GET['force-lookup'] = 1;
        
        $plugin_path = $site_path . '/wp-content/plugins/' . $plugin_file;
        $orig_version = false;
        if (file_exists($plugin_path)) {
            $orig_version = change_plugin_file_version($plugin_path, '0.0.1');
        }

        $buffer_level = ob_get_level();
        ob_start();
        try {
            wp_manager_bootstrap_wordpress($site_path);
            require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
            
            delete_site_transient('update_plugins');
            if (function_exists('wp_clean_plugins_cache')) {
                wp_clean_plugins_cache(true);
            }
            wp_update_plugins();
            
            $updates = get_site_transient('update_plugins');
            $update = $updates->response[$plugin_file] ?? null;
            
            if (!$update || empty($update->package)) {
                if ($orig_version !== false) {
                    change_plugin_file_version($plugin_path, $orig_version);
                }
                throw new Exception("Không tìm thấy bản cập nhật qua WordPress Core. Vui lòng cập nhật license của plugin.");
            }

            $skin = new Automatic_Upgrader_Skin();
            $upgrader = new Plugin_Upgrader($skin);
            $result = $upgrader->upgrade($plugin_file);
            
            if (is_wp_error($result)) {
                if ($orig_version !== false) {
                    change_plugin_file_version($plugin_path, $orig_version);
                }
                throw new Exception($result->get_error_message());
            }
            if (!$result) {
                if ($orig_version !== false) {
                    change_plugin_file_version($plugin_path, $orig_version);
                }
                throw new Exception("Cập nhật plugin thất bại.");
            }
        } catch (Throwable $e) {
            if ($orig_version !== false) {
                change_plugin_file_version($plugin_path, $orig_version);
            }
            throw $e;
        } finally {
            while (ob_get_level() > $buffer_level) {
                ob_end_clean();
            }
        }
    } else {
        file_put_contents($temp_zip, $data);

        // Delete the old directory/file entirely to remove malware
        $plugin_dir = $site_path . '/wp-content/plugins/' . $slug;
        if (is_dir($plugin_dir)) {
            rmdir_recursive($plugin_dir);
        } else {
            $single_file = $site_path . '/wp-content/plugins/' . $plugin_file;
            if (file_exists($single_file)) {
                @unlink($single_file);
            }
        }

        // Extract ZIP
        $zip = new ZipArchive;
        if ($zip->open($temp_zip) === TRUE) {
            $zip->extractTo($site_path . '/wp-content/plugins');
            $zip->close();
            @unlink($temp_zip);
        } else {
            @unlink($temp_zip);
            throw new Exception("Không thể giải nén file ZIP của plugin.");
        }
    }

    return [
        'success' => true, 
        'message' => $fallback_used 
            ? 'Cập nhật plugin thành công qua cơ chế WordPress Core.' 
            : 'Plugin updated and cleaned successfully via WordPress.org ZIP download.'
    ];
}

/**
 * Update one installed WordPress theme by downloading ZIP from WordPress.org and cleaning.
 */
function update_wordpress_theme($site_path, $theme_folder) {
    if (is_wordpress_locked($site_path)) {
        throw new Exception("Website is under WordPress Lockdown. Please disable Lockdown before updating themes.");
    }

    $url = "https://downloads.wordpress.org/theme/{$theme_folder}.zip";

    // Download the ZIP file
    $temp_zip = sys_get_temp_dir() . '/update_theme_' . time() . '.zip';
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 60);
    curl_setopt($ch, CURLOPT_USERAGENT, 'WordPress-Theme-Updater');
    $data = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $fallback_used = false;

    if ($http_code !== 200 || !$data) {
        $fallback_used = true;
        wp_manager_log("Theme ZIP not found on WordPress.org for {$theme_folder}. Falling back to native WordPress upgrade...");
        
        $_GET['force-check'] = 1;
        $_GET['force-lookup'] = 1;
        
        $theme_style_path = $site_path . '/wp-content/themes/' . $theme_folder . '/style.css';
        $orig_version = false;
        if (file_exists($theme_style_path)) {
            $orig_version = change_theme_style_version($theme_style_path, '0.0.1');
        }

        $buffer_level = ob_get_level();
        ob_start();
        try {
            wp_manager_bootstrap_wordpress($site_path);
            require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
            require_once ABSPATH . 'wp-admin/includes/theme.php';
            
            delete_site_transient('update_themes');
            if (function_exists('wp_get_themes')) {
                wp_get_themes(['errors' => null]);
            }
            wp_update_themes();
            
            $updates = get_site_transient('update_themes');
            $update = $updates->response[$theme_folder] ?? null;
            
            if (!$update) {
                if ($orig_version !== false) {
                    change_theme_style_version($theme_style_path, $orig_version);
                }
                throw new Exception("Không tìm thấy bản cập nhật cho theme này trên hệ thống hoặc thư viện.");
            }
            $package = is_array($update) ? ($update['package'] ?? '') : ($update->package ?? '');
            if (empty($package)) {
                if ($orig_version !== false) {
                    change_theme_style_version($theme_style_path, $orig_version);
                }
                throw new Exception("Không tìm thấy bản cập nhật qua WordPress Core. Vui lòng cập nhật license của theme.");
            }

            $skin = new Automatic_Upgrader_Skin();
            $upgrader = new Theme_Upgrader($skin);
            $result = $upgrader->upgrade($theme_folder);
            
            if (is_wp_error($result)) {
                if ($orig_version !== false) {
                    change_theme_style_version($theme_style_path, $orig_version);
                }
                throw new Exception($result->get_error_message());
            }
            if (!$result) {
                if ($orig_version !== false) {
                    change_theme_style_version($theme_style_path, $orig_version);
                }
                throw new Exception("Cập nhật theme thất bại.");
            }
        } catch (Throwable $e) {
            if ($orig_version !== false) {
                change_theme_style_version($theme_style_path, $orig_version);
            }
            throw $e;
        } finally {
            while (ob_get_level() > $buffer_level) {
                ob_end_clean();
            }
        }
    } else {
        file_put_contents($temp_zip, $data);

        // Delete the old directory entirely to remove malware
        $theme_dir = $site_path . '/wp-content/themes/' . $theme_folder;
        if (is_dir($theme_dir)) {
            rmdir_recursive($theme_dir);
        }

        // Extract ZIP
        $zip = new ZipArchive;
        if ($zip->open($temp_zip) === TRUE) {
            $zip->extractTo($site_path . '/wp-content/themes');
            $zip->close();
            @unlink($temp_zip);
        } else {
            @unlink($temp_zip);
            throw new Exception("Không thể giải nén file ZIP của theme.");
        }
    }

    return [
        'success' => true, 
        'message' => $fallback_used 
            ? 'Cập nhật theme thành công qua cơ chế WordPress Core.' 
            : 'Theme updated and cleaned successfully via WordPress.org ZIP download.'
    ];
}

/**
 * Update all WordPress plugins at once using direct downloads and cleaning.
 */
function update_all_wordpress_plugins($site_path) {
    if (is_wordpress_locked($site_path)) {
        throw new Exception("Website is under WordPress Lockdown. Please disable Lockdown before updating plugins.");
    }

    wp_manager_bootstrap_wordpress($site_path);
    require_once ABSPATH . 'wp-admin/includes/plugin.php';
    
    $all_plugins = get_plugins();
    if (empty($all_plugins)) {
        return ['success' => true, 'message' => 'Không tìm thấy plugin nào được cài đặt.'];
    }

    $success_count = 0;
    $fail_count = 0;

    foreach (array_keys($all_plugins) as $plugin_file) {
        if ($plugin_file === 'hello.php') {
            continue;
        }

        try {
            update_wordpress_plugin($site_path, $plugin_file);
            $success_count++;
        } catch (Exception $e) {
            $fail_count++;
            wp_manager_log("Failed to update plugin {$plugin_file}: " . $e->getMessage());
        }
    }

    return [
        'success' => true, 
        'message' => "Đã cập nhật/tải lại thành công {$success_count} plugins." . ($fail_count > 0 ? " Bỏ qua {$fail_count} plugins bản quyền/riêng tư." : "")
    ];
}

/**
 * Update all WordPress themes at once using direct downloads and cleaning.
 */
function update_all_wordpress_themes($site_path) {
    if (is_wordpress_locked($site_path)) {
        throw new Exception("Website is under WordPress Lockdown. Please disable Lockdown before updating themes.");
    }

    wp_manager_bootstrap_wordpress($site_path);
    require_once ABSPATH . 'wp-admin/includes/theme.php';
    
    $all_themes = wp_get_themes();
    if (empty($all_themes)) {
        return ['success' => true, 'message' => 'Không tìm thấy theme nào được cài đặt.'];
    }

    $success_count = 0;
    $fail_count = 0;

    foreach ($all_themes as $theme_slug => $theme_obj) {
        try {
            update_wordpress_theme($site_path, $theme_slug);
            $success_count++;
        } catch (Exception $e) {
            $fail_count++;
            wp_manager_log("Failed to update theme {$theme_slug}: " . $e->getMessage());
        }
    }

    return [
        'success' => true, 
        'message' => "Đã cập nhật/tải lại thành công {$success_count} themes." . ($fail_count > 0 ? " Bỏ qua {$fail_count} themes bản quyền/riêng tư." : "")
    ];
}

/**
 * Reinstall a plugin by fetching from WordPress.org
 */
function reinstall_wordpress_plugin($site_path, $plugin_file) {
    if (is_wordpress_locked($site_path)) {
        throw new Exception("Website is under WordPress Lockdown. Please disable Lockdown before reinstalling plugins.");
    }
    $slug = explode('/', $plugin_file)[0];
    $url = "https://downloads.wordpress.org/plugin/{$slug}.zip";

    // Download the ZIP file
    $temp_zip = sys_get_temp_dir() . '/reinstall_plugin_' . time() . '.zip';
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 60);
    curl_setopt($ch, CURLOPT_USERAGENT, 'WordPress-Plugin-Reinstaller');
    $data = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    $fallback_used = false;

    if ($http_code !== 200 || !$data) {
        $fallback_used = true;
        wp_manager_log("Plugin ZIP not found on WordPress.org for {$slug}. Falling back to native WordPress upgrade...");
        
        $_GET['force-check'] = 1;
        $_GET['force-lookup'] = 1;
        
        $plugin_path = $site_path . '/wp-content/plugins/' . $plugin_file;
        $orig_version = false;
        if (file_exists($plugin_path)) {
            $orig_version = change_plugin_file_version($plugin_path, '0.0.1');
        }

        $buffer_level = ob_get_level();
        ob_start();
        try {
            wp_manager_bootstrap_wordpress($site_path);
            require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
            
            delete_site_transient('update_plugins');
            if (function_exists('wp_clean_plugins_cache')) {
                wp_clean_plugins_cache(true);
            }
            wp_update_plugins();
            
            $updates = get_site_transient('update_plugins');
            $update = $updates->response[$plugin_file] ?? null;
            
            if (!$update || empty($update->package)) {
                if ($orig_version !== false) {
                    change_plugin_file_version($plugin_path, $orig_version);
                }
                throw new Exception("Không tìm thấy bản cập nhật qua WordPress Core. Vui lòng cập nhật license của plugin.");
            }

            $skin = new Automatic_Upgrader_Skin();
            $upgrader = new Plugin_Upgrader($skin);
            $result = $upgrader->upgrade($plugin_file);
            
            if (is_wp_error($result)) {
                if ($orig_version !== false) {
                    change_plugin_file_version($plugin_path, $orig_version);
                }
                throw new Exception($result->get_error_message());
            }
            if (!$result) {
                if ($orig_version !== false) {
                    change_plugin_file_version($plugin_path, $orig_version);
                }
                throw new Exception("Cập nhật plugin thất bại.");
            }
        } catch (Throwable $e) {
            if ($orig_version !== false) {
                change_plugin_file_version($plugin_path, $orig_version);
            }
            throw $e;
        } finally {
            while (ob_get_level() > $buffer_level) {
                ob_end_clean();
            }
        }
    } else {
        file_put_contents($temp_zip, $data);
        
        // Extract ZIP
        $zip = new ZipArchive;
        if ($zip->open($temp_zip) === TRUE) {
            $plugins_dir = $site_path . '/wp-content/plugins';
            $target_dir = $plugins_dir . '/' . $slug;
            
            // Remove old plugin folder
            if (is_dir($target_dir)) {
                rmdir_recursive($target_dir);
            }
            
            // Extract
            $zip->extractTo($plugins_dir);
            $zip->close();
            @unlink($temp_zip);
            
            // Reset permissions
            set_permissions_recursive($target_dir);
        } else {
            @unlink($temp_zip);
            throw new Exception("Không thể giải nén tệp ZIP của plugin.");
        }
    }
    
    return [
        'success' => true, 
        'message' => $fallback_used 
            ? 'Cài đặt lại plugin thành công qua cơ chế WordPress Core.' 
            : 'Plugin reinstalled successfully.'
    ];
}

/**
 * Delete a plugin
 */
function delete_wordpress_plugin($site_path, $plugin_file) {
    if (is_wordpress_locked($site_path)) {
        throw new Exception("Website is under WordPress Lockdown. Please disable Lockdown before deleting plugins.");
    }
    
    // Deactivate first
    toggle_plugin_status($site_path, $plugin_file, 'deactivate');
    
    $slug = explode('/', $plugin_file)[0];
    $plugin_dir = $site_path . '/wp-content/plugins/' . $slug;
    if (is_dir($plugin_dir)) {
        rmdir_recursive($plugin_dir);
    } elseif (file_exists($site_path . '/wp-content/plugins/' . $plugin_file)) {
        @unlink($site_path . '/wp-content/plugins/' . $plugin_file);
    } else {
        throw new Exception("Plugin folder or file not found.");
    }
    return ['success' => true, 'message' => 'Plugin deleted successfully.'];
}

/**
 * Reinstall a theme by fetching from WordPress.org
 */
function reinstall_wordpress_theme($site_path, $theme_folder) {
    if (is_wordpress_locked($site_path)) {
        throw new Exception("Website is under WordPress Lockdown. Please disable Lockdown before reinstalling themes.");
    }
    $url = "https://downloads.wordpress.org/theme/{$theme_folder}.zip";

    // Download the ZIP file
    $temp_zip = sys_get_temp_dir() . '/reinstall_theme_' . time() . '.zip';
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 60);
    curl_setopt($ch, CURLOPT_USERAGENT, 'WordPress-Theme-Reinstaller');
    $data = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    $fallback_used = false;

    if ($http_code !== 200 || !$data) {
        $fallback_used = true;
        wp_manager_log("Theme ZIP not found on WordPress.org for {$theme_folder}. Falling back to native WordPress upgrade...");
        
        $_GET['force-check'] = 1;
        $_GET['force-lookup'] = 1;
        
        $theme_style_path = $site_path . '/wp-content/themes/' . $theme_folder . '/style.css';
        $orig_version = false;
        if (file_exists($theme_style_path)) {
            $orig_version = change_theme_style_version($theme_style_path, '0.0.1');
        }

        $buffer_level = ob_get_level();
        ob_start();
        try {
            wp_manager_bootstrap_wordpress($site_path);
            require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
            require_once ABSPATH . 'wp-admin/includes/theme.php';
            
            delete_site_transient('update_themes');
            if (function_exists('wp_get_themes')) {
                wp_get_themes(['errors' => null]);
            }
            wp_update_themes();
            
            $updates = get_site_transient('update_themes');
            $update = $updates->response[$theme_folder] ?? null;
            
            if (!$update) {
                if ($orig_version !== false) {
                    change_theme_style_version($theme_style_path, $orig_version);
                }
                throw new Exception("Không tìm thấy bản cập nhật cho theme này trên hệ thống hoặc thư viện.");
            }
            $package = is_array($update) ? ($update['package'] ?? '') : ($update->package ?? '');
            if (empty($package)) {
                if ($orig_version !== false) {
                    change_theme_style_version($theme_style_path, $orig_version);
                }
                throw new Exception("Không tìm thấy bản cập nhật qua WordPress Core. Vui lòng cập nhật license của theme.");
            }

            $skin = new Automatic_Upgrader_Skin();
            $upgrader = new Theme_Upgrader($skin);
            $result = $upgrader->upgrade($theme_folder);
            
            if (is_wp_error($result)) {
                if ($orig_version !== false) {
                    change_theme_style_version($theme_style_path, $orig_version);
                }
                throw new Exception($result->get_error_message());
            }
            if (!$result) {
                if ($orig_version !== false) {
                    change_theme_style_version($theme_style_path, $orig_version);
                }
                throw new Exception("Cập nhật theme thất bại.");
            }
        } catch (Throwable $e) {
            if ($orig_version !== false) {
                change_theme_style_version($theme_style_path, $orig_version);
            }
            throw $e;
        } finally {
            while (ob_get_level() > $buffer_level) {
                ob_end_clean();
            }
        }
    } else {
        file_put_contents($temp_zip, $data);
        
        // Extract ZIP
        $zip = new ZipArchive;
        if ($zip->open($temp_zip) === TRUE) {
            $themes_dir = $site_path . '/wp-content/themes';
            $target_dir = $themes_dir . '/' . $theme_folder;
            
            // Remove old theme folder
            if (is_dir($target_dir)) {
                rmdir_recursive($target_dir);
            }
            
            // Extract
            $zip->extractTo($themes_dir);
            $zip->close();
            @unlink($temp_zip);
            
            // Reset permissions
            set_permissions_recursive($target_dir);
        } else {
            @unlink($temp_zip);
            throw new Exception("Không thể giải nén tệp ZIP của theme.");
        }
    }
    
    return [
        'success' => true, 
        'message' => $fallback_used 
            ? 'Cài đặt lại theme thành công qua cơ chế WordPress Core.' 
            : 'Theme reinstalled successfully.'
    ];
}

/**
 * Delete a theme
 */
function delete_wordpress_theme($site_path, $theme_folder) {
    if (is_wordpress_locked($site_path)) {
        throw new Exception("Website is under WordPress Lockdown. Please disable Lockdown before deleting themes.");
    }
    
    $active_theme = get_active_theme($site_path);
    if ($theme_folder === $active_theme) {
        throw new Exception("Cannot delete the currently active theme.");
    }
    
    $theme_dir = $site_path . '/wp-content/themes/' . $theme_folder;
    if (is_dir($theme_dir)) {
        rmdir_recursive($theme_dir);
    } else {
        throw new Exception("Theme directory not found.");
    }
    return ['success' => true, 'message' => 'Theme deleted successfully.'];
}

/**
 * List WordPress users.
 */
function list_wordpress_users($site_path) {
    $wp_config_path = $site_path . '/wp-config.php';
    $db = wp_manager_get_db_conn($wp_config_path);
    if (!$db) {
        throw new Exception("Unable to connect to database.");
    }

    $prefix = $db['prefix'];
    $stmt = $db['pdo']->prepare("
        SELECT ID, user_login, user_email, user_registered, display_name
        FROM `{$prefix}users`
        ORDER BY COALESCE(NULLIF(display_name, ''), user_login) ASC
    ");
    $stmt->execute();
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($users as &$user) {
        $cap_stmt = $db['pdo']->prepare("SELECT meta_value FROM `{$prefix}usermeta` WHERE user_id = ? AND meta_key = ?");
        $cap_stmt->execute([$user['ID'], $prefix . 'capabilities']);
        $caps = @unserialize($cap_stmt->fetchColumn() ?: '');
        $roles = [];
        if (is_array($caps)) {
            foreach ($caps as $role => $enabled) {
                if ($enabled) {
                    $roles[] = $role;
                }
            }
        }
        $user['roles'] = $roles;
    }
    unset($user);

    usort($users, function($a, $b) {
        $name_a = $a['display_name'] ?: $a['user_login'];
        $name_b = $b['display_name'] ?: $b['user_login'];
        return strcasecmp($name_a, $name_b);
    });

    return $users;
}

/**
 * Change a WordPress user's password.
 */
function change_wordpress_user_password($site_path, $user_id, $new_password) {
    $user_id = (int)$user_id;
    if ($user_id <= 0) {
        throw new Exception("Invalid user ID.");
    }
    if (strlen($new_password) < 8) {
        throw new Exception("Password must be at least 8 characters.");
    }
    if (!wordpress_user_exists($site_path, $user_id)) {
        throw new Exception("User not found.");
    }

    wp_manager_bootstrap_auth($site_path);
    if (!function_exists('wp_set_password')) {
        throw new Exception("WordPress password helper is unavailable.");
    }

    wp_set_password($new_password, $user_id);
    return ['success' => true, 'message' => 'User password changed successfully.'];
}

/**
 * Create a new WordPress user.
 */
function create_wordpress_user($site_path, $username, $password, $email, $role) {
    if (empty($username) || empty($password) || empty($email) || empty($role)) {
        throw new Exception("Vui lòng điền đầy đủ thông tin bắt buộc.");
    }
    if (strlen($password) < 8) {
        throw new Exception("Mật khẩu phải dài ít nhất 8 ký tự.");
    }
    
    // Check if website is locked
    if (is_wordpress_locked($site_path)) {
        throw new Exception("Website đang bị khóa (WP Lock). Vui lòng tắt WP Lock trước khi tạo user.");
    }

    $db = wp_manager_get_db_conn($site_path . '/wp-config.php');
    if (!$db) {
        throw new Exception("Không thể kết nối đến Database.");
    }

    $prefix = $db['prefix'];

    // Check if username already exists
    $stmt = $db['pdo']->prepare("SELECT COUNT(*) FROM `{$prefix}users` WHERE user_login = ?");
    $stmt->execute([$username]);
    if ($stmt->fetchColumn() > 0) {
        throw new Exception("Tên đăng nhập đã tồn tại.");
    }

    // Check if email already exists
    $stmt = $db['pdo']->prepare("SELECT COUNT(*) FROM `{$prefix}users` WHERE user_email = ?");
    $stmt->execute([$email]);
    if ($stmt->fetchColumn() > 0) {
        throw new Exception("Email đã tồn tại.");
    }

    // Hash the password using WordPress's Phpass library
    $phpass_file = $site_path . '/wp-includes/class-phpass.php';
    if (!file_exists($phpass_file)) {
        throw new Exception("Không tìm thấy thư viện băm mật khẩu của WordPress.");
    }
    
    require_once $phpass_file;
    if (!class_exists('PasswordHash')) {
        throw new Exception("Lớp băm mật khẩu không khả dụng.");
    }

    $wp_hasher = new PasswordHash(8, true);
    $hashed_password = $wp_hasher->HashPassword($password);

    // Insert user
    $nicename = strtolower(preg_replace('/[^a-zA-Z0-9-]/', '', $username));
    if (empty($nicename)) {
        $nicename = 'user';
    }
    $display_name = $username;
    $registered = (new DateTime("now", new DateTimeZone("Asia/Ho_Chi_Minh")))->format('Y-m-d H:i:s');

    $stmt = $db['pdo']->prepare("
        INSERT INTO `{$prefix}users` (user_login, user_pass, user_nicename, user_email, user_registered, user_status, display_name)
        VALUES (?, ?, ?, ?, ?, 0, ?)
    ");
    $stmt->execute([$username, $hashed_password, $nicename, $email, $registered, $display_name]);
    $user_id = $db['pdo']->lastInsertId();

    if (!$user_id) {
        throw new Exception("Không thể tạo User trong cơ sở dữ liệu.");
    }

    // Insert capabilities (Role)
    $caps = serialize([$role => true]);
    $stmt = $db['pdo']->prepare("INSERT INTO `{$prefix}usermeta` (user_id, meta_key, meta_value) VALUES (?, ?, ?)");
    $stmt->execute([$user_id, $prefix . 'capabilities', $caps]);

    // Insert user level
    $level = 0;
    if ($role === 'administrator') $level = 10;
    elseif ($role === 'editor') $level = 7;
    elseif ($role === 'author') $level = 2;
    elseif ($role === 'contributor') $level = 1;

    $stmt = $db['pdo']->prepare("INSERT INTO `{$prefix}usermeta` (user_id, meta_key, meta_value) VALUES (?, ?, ?)");
    $stmt->execute([$user_id, $prefix . 'user_level', $level]);

    return [
        'success' => true,
        'message' => 'Đã tạo tài khoản thành công!',
        'user_id' => $user_id
    ];
}

/**
 * Ensure a WordPress user exists in the target installation.
 */
function wordpress_user_exists($site_path, $user_id) {
    $user_id = (int)$user_id;
    if ($user_id <= 0) {
        return false;
    }
    $db = wp_manager_get_db_conn($site_path . '/wp-config.php');
    if (!$db) {
        throw new Exception("Unable to connect to database.");
    }
    $stmt = $db['pdo']->prepare("SELECT COUNT(*) FROM `{$db['prefix']}users` WHERE ID = ?");
    $stmt->execute([$user_id]);
    return ((int)$stmt->fetchColumn()) > 0;
}

/**
 * Format bytes for compact UI display.
 */
function wp_manager_format_bytes($bytes) {
    $bytes = max(0, (float)$bytes);
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $power = $bytes > 0 ? min((int)floor(log($bytes, 1024)), count($units) - 1) : 0;
    $value = $bytes / pow(1024, $power);
    return ($power === 0 ? (string)(int)$value : number_format($value, 2)) . ' ' . $units[$power];
}

/**
 * Count files, directories and bytes under a path.
 */
function wp_manager_path_stats($path) {
    $stats = ['bytes' => 0, 'files' => 0, 'dirs' => 0, 'human' => '0 B'];
    if (!is_dir($path)) {
        return $stats;
    }

    try {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $item) {
            $pathname = $item->getPathname();
            if (strpos($pathname, DIRECTORY_SEPARATOR . '.git' . DIRECTORY_SEPARATOR) !== false) {
                continue;
            }
            if ($item->isLink()) {
                continue;
            }
            if ($item->isDir()) {
                $stats['dirs']++;
            } elseif ($item->isFile()) {
                $stats['files']++;
                $stats['bytes'] += (int)$item->getSize();
            }
        }
    } catch (Exception $e) {
        wp_manager_log("Unable to calculate path stats for {$path}: " . $e->getMessage());
    }

    $stats['human'] = wp_manager_format_bytes($stats['bytes']);
    return $stats;
}

/**
 * Build site weight/content statistics for the Installation Details tab.
 */
function wp_manager_get_site_weight_stats($site_path, $pdo = null, $db_prefix = '', $db_name = '') {
    $stats = [
        'storage' => [
            'site_size' => wp_manager_path_stats($site_path),
            'wp_content_size' => wp_manager_path_stats($site_path . '/wp-content'),
            'uploads_size' => wp_manager_path_stats($site_path . '/wp-content/uploads'),
            'plugins_size' => wp_manager_path_stats($site_path . '/wp-content/plugins'),
            'themes_size' => wp_manager_path_stats($site_path . '/wp-content/themes'),
        ],
        'counts' => [
            'plugins' => is_dir($site_path . '/wp-content/plugins') ? count(list_plugins($site_path)) : 0,
            'themes' => is_dir($site_path . '/wp-content/themes') ? count(list_themes($site_path)) : 0,
        ],
        'db' => [
            'tables' => 0,
            'size_bytes' => 0,
            'size_human' => '0 B',
            'options_rows' => 0,
            'autoload_options' => 0,
            'autoload_size_bytes' => 0,
            'autoload_size_human' => '0 B',
            'transients' => 0,
            'postmeta_rows' => 0,
            'commentmeta_rows' => 0,
            'usermeta_rows' => 0,
        ],
        'content' => [
            'posts' => 0,
            'products' => 0,
            'product_variations' => 0,
            'pages' => 0,
            'attachments' => 0,
            'revisions' => 0,
            'nav_menu_items' => 0,
            'custom_post_total' => 0,
            'custom_post_types' => [],
            'post_statuses' => [],
            'comments_total' => 0,
            'comments_approved' => 0,
            'comments_pending' => 0,
            'comments_spam' => 0,
            'comments_trash' => 0,
            'users' => 0,
            'terms' => 0,
        ],
    ];

    if (!$pdo || !$db_prefix || !$db_name) {
        return $stats;
    }

    try {
        $stmt = $pdo->prepare("
            SELECT COUNT(*) AS tables_count, COALESCE(SUM(data_length + index_length), 0) AS db_size
            FROM information_schema.TABLES
            WHERE table_schema = ? AND table_name LIKE ?
        ");
        $stmt->execute([$db_name, $db_prefix . '%']);
        $db_row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
        $stats['db']['tables'] = (int)($db_row['tables_count'] ?? 0);
        $stats['db']['size_bytes'] = (int)($db_row['db_size'] ?? 0);
        $stats['db']['size_human'] = wp_manager_format_bytes($stats['db']['size_bytes']);

        $stmt = $pdo->query("SELECT post_type, COUNT(*) AS total FROM `{$db_prefix}posts` GROUP BY post_type");
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $type = $row['post_type'];
            $total = (int)$row['total'];
            if ($type === 'post') {
                $stats['content']['posts'] = $total;
            } elseif ($type === 'product') {
                $stats['content']['products'] = $total;
            } elseif ($type === 'product_variation') {
                $stats['content']['product_variations'] = $total;
            } elseif ($type === 'page') {
                $stats['content']['pages'] = $total;
            } elseif ($type === 'attachment') {
                $stats['content']['attachments'] = $total;
            } elseif ($type === 'revision') {
                $stats['content']['revisions'] = $total;
            } elseif ($type === 'nav_menu_item') {
                $stats['content']['nav_menu_items'] = $total;
            } else {
                $stats['content']['custom_post_types'][$type] = $total;
                $stats['content']['custom_post_total'] += $total;
            }
        }

        $stmt = $pdo->query("SELECT post_status, COUNT(*) AS total FROM `{$db_prefix}posts` GROUP BY post_status");
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $stats['content']['post_statuses'][$row['post_status']] = (int)$row['total'];
        }

        $stmt = $pdo->query("SELECT comment_approved, COUNT(*) AS total FROM `{$db_prefix}comments` GROUP BY comment_approved");
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $status = (string)$row['comment_approved'];
            $total = (int)$row['total'];
            $stats['content']['comments_total'] += $total;
            if ($status === '1') {
                $stats['content']['comments_approved'] = $total;
            } elseif ($status === '0') {
                $stats['content']['comments_pending'] = $total;
            } elseif ($status === 'spam') {
                $stats['content']['comments_spam'] = $total;
            } elseif ($status === 'trash') {
                $stats['content']['comments_trash'] = $total;
            }
        }

        $simple_counts = [
            'users' => "SELECT COUNT(*) FROM `{$db_prefix}users`",
            'terms' => "SELECT COUNT(*) FROM `{$db_prefix}terms`",
            'options_rows' => "SELECT COUNT(*) FROM `{$db_prefix}options`",
            'autoload_options' => "SELECT COUNT(*) FROM `{$db_prefix}options` WHERE autoload IN ('yes', 'on', 'auto-on', 'auto')",
            'autoload_size_bytes' => "SELECT COALESCE(SUM(LENGTH(option_value)), 0) FROM `{$db_prefix}options` WHERE autoload IN ('yes', 'on', 'auto-on', 'auto')",
            'transients' => "SELECT COUNT(*) FROM `{$db_prefix}options` WHERE option_name LIKE '_transient_%' OR option_name LIKE '_site_transient_%'",
            'postmeta_rows' => "SELECT COUNT(*) FROM `{$db_prefix}postmeta`",
            'commentmeta_rows' => "SELECT COUNT(*) FROM `{$db_prefix}commentmeta`",
            'usermeta_rows' => "SELECT COUNT(*) FROM `{$db_prefix}usermeta`",
        ];

        foreach ($simple_counts as $key => $sql) {
            $value = (int)$pdo->query($sql)->fetchColumn();
            if (isset($stats['content'][$key])) {
                $stats['content'][$key] = $value;
            } else {
                $stats['db'][$key] = $value;
            }
        }
        $stats['db']['autoload_size_human'] = wp_manager_format_bytes($stats['db']['autoload_size_bytes']);
    } catch (Exception $e) {
        wp_manager_log("Unable to collect site weight stats for {$site_path}: " . $e->getMessage());
    }

    ksort($stats['content']['custom_post_types']);
    ksort($stats['content']['post_statuses']);
    return $stats;
}

/**
 * Parse wp-config.php and extract metadata
 */
function parse_wp_config($wp_config_path) {
    if (!file_exists($wp_config_path)) return null;
    
    // Auto-detect domain and sub-path from path
    $domain = '';
    $sub_path = '';
    
    $username = getenv('USERNAME') ?: getenv('USER') ?: 'nobody';
    $home = getenv('HOME') ?: "/home/{$username}";
    if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
        $home = 'C:/Users/local_user';
    }
    
    $domains_prefix = $home . '/domains/';
    $site_path = dirname($wp_config_path);
    if (strpos($site_path, $domains_prefix) === 0) {
        $relative = substr($site_path, strlen($domains_prefix));
        $parts = explode('/', $relative);
        if (count($parts) > 0) {
            $domain = $parts[0];
            
            // Check if it's in a subdomains directory: e.g. domain.com/subdomains/sub/public_html
            $subdomains_index = array_search('subdomains', $parts);
            if ($subdomains_index !== false && isset($parts[$subdomains_index + 1])) {
                $subdomain_name = $parts[$subdomains_index + 1];
                $domain = $subdomain_name . '.' . $domain;
                
                $pub_index = array_search('public_html', $parts);
                if ($pub_index !== false && $pub_index < count($parts) - 1) {
                    $sub_path = implode('/', array_slice($parts, $pub_index + 1));
                }
            } else {
                $pub_index = array_search('public_html', $parts);
                if ($pub_index !== false && $pub_index < count($parts) - 1) {
                    $sub_path = implode('/', array_slice($parts, $pub_index + 1));
                }
            }
            
            // If it's in public_html, check if the first folder inside public_html is a configured subdomain
            if ($sub_path !== '') {
                $sub_parts = explode('/', $sub_path);
                $first_part = $sub_parts[0];
                
                $is_sub = false;
                $sub_list_file = $home . '/domains/' . $domain . '/subdomains.list';
                if (file_exists($sub_list_file)) {
                    $lines = file($sub_list_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
                    if ($lines !== false) {
                        foreach ($lines as $line) {
                            if (trim($line) === $first_part) {
                                $is_sub = true;
                                break;
                            }
                        }
                    }
                }
                if (is_dir($home . '/domains/' . $domain . '/subdomains/' . $first_part)) {
                    $is_sub = true;
                }
                
                if ($is_sub) {
                    $domain = $first_part . '.' . $domain;
                    $sub_path = implode('/', array_slice($sub_parts, 1));
                }
            }
        }
    }
    
    $content = file_get_contents($wp_config_path);
    
    preg_match("/define\s*\(\s*['\"]DB_NAME['\"]\s*,\s*['\"](.*?)['\"]\s*\)/", $content, $db_name_match);
    preg_match("/define\s*\(\s*['\"]DB_USER['\"]\s*,\s*['\"](.*?)['\"]\s*\)/", $content, $db_user_match);
    preg_match("/define\s*\(\s*['\"]DB_PASSWORD['\"]\s*,\s*['\"](.*?)['\"]\s*\)/", $content, $db_pass_match);
    preg_match("/define\s*\(\s*['\"]DB_HOST['\"]\s*,\s*['\"](.*?)['\"]\s*\)/", $content, $db_host_match);
    preg_match("/\\\$table_prefix\s*=\s*['\"](.*?)['\"]/", $content, $prefix_match);
    
    $db_name = $db_name_match[1] ?? '';
    $db_user = $db_user_match[1] ?? '';
    $db_pass = $db_pass_match[1] ?? '';
    $db_host = $db_host_match[1] ?? 'localhost';
    $db_prefix = $prefix_match[1] ?? 'wp_';
    
    // Extract WP Version
    $version_path = dirname($wp_config_path) . '/wp-includes/version.php';
    $wp_version = 'Unknown';
    if (file_exists($version_path)) {
        $version_content = file_get_contents($version_path);
        if (preg_match("/\\\$wp_version\s*=\s*['\"](.*?)['\"]/", $version_content, $ver_match)) {
            $wp_version = $ver_match[1];
        }
    }
    
    // Connect to database to fetch site URL and Title
    $siteurl = '';
    $blogname = '';
    $status = 'active';
    $pdo = null;
    
    try {
        $dsn = "mysql:host={$db_host};dbname={$db_name};charset=utf8mb4";
        $pdo = new PDO($dsn, $db_user, $db_pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_TIMEOUT => 2
        ]);
        
        $stmt = $pdo->prepare("SELECT option_name, option_value FROM `{$db_prefix}options` WHERE option_name IN ('siteurl', 'blogname')");
        $stmt->execute();
        $options = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
        
        $siteurl = $options['siteurl'] ?? '';
        $blogname = $options['blogname'] ?? '';
    } catch (Exception $e) {
        $status = 'db_error';
    }
    
    // Fallback siteurl/blogname if DB unreachable
    if ($siteurl === '') {
        $siteurl = 'http://' . $domain . ($sub_path !== '' ? '/' . $sub_path : '');
    }
    if ($blogname === '') {
        $blogname = $domain . ($sub_path !== '' ? '/' . $sub_path : '');
    }

    // --- AUTHORITATIVE: Prioritize deriving domain and sub_path from the folder path ---
    // If the database has a siteurl, we extract its scheme (http/https) but keep the folder-derived domain and sub_path.
    $protocol = 'http';
    if (!empty($siteurl)) {
        $parsed_db = parse_url($siteurl);
        if (!empty($parsed_db['scheme'])) {
            $protocol = $parsed_db['scheme'];
        }
    }
    $siteurl = $protocol . '://' . $domain . ($sub_path !== '' ? '/' . $sub_path : '');
    // If DB was unreachable, $domain and $sub_path remain as path-derived fallback

    
    // Auto Cleanup expired Magic Logins (older than 1 hour)
    $mu_dir = dirname($wp_config_path) . '/wp-content/mu-plugins';
    if (is_dir($mu_dir)) {
        $files = array_diff(scandir($mu_dir), ['.', '..']);
        foreach ($files as $file) {
            if (strpos($file, 'magic-login-') === 0 && substr($file, -4) === '.php') {
                $file_path = $mu_dir . '/' . $file;
                if (file_exists($file_path) && (time() - filemtime($file_path) > 3600)) {
                    @unlink($file_path);
                }
            }
        }
    }
    
    // Extract WP Cron disable status
    $disable_wp_cron = false;
    if (preg_match("/define\s*\(\s*['\"]DISABLE_WP_CRON['\"]\s*,\s*true\s*\)/i", $content)) {
        $disable_wp_cron = true;
    }
    
    // Check if auto check update is disabled
    $disable_auto_update = false;
    $mu_plugin_file = dirname($wp_config_path) . '/wp-content/mu-plugins/wp_disable_check_update.php';
    if (file_exists($mu_plugin_file)) {
        $disable_auto_update = true;
    }

    // Extract WP Debug status
    $wp_debug_enabled = false;
    if (preg_match("/define\s*\(\s*['\"]WP_DEBUG['\"]\s*,\s*true\s*\)/i", $content)) {
        $wp_debug_enabled = true;
    }

    $weight_stats = wp_manager_get_site_weight_stats(dirname($wp_config_path), $pdo, $db_prefix, $db_name);
    
    return [
        'path' => dirname($wp_config_path),
        'domain' => $domain,
        'subdir' => $sub_path,
        'siteurl' => $siteurl,
        'blogname' => $blogname,
        'version' => $wp_version,
        'db_name' => $db_name,
        'db_user' => $db_user,
        'db_host' => $db_host,
        'db_prefix' => $db_prefix,
        'status' => $status,
        'locked' => is_wordpress_locked(dirname($wp_config_path)),
        'disable_wp_cron' => $disable_wp_cron,
        'disable_auto_update' => $disable_auto_update,
        'wp_debug_enabled' => $wp_debug_enabled,
        'weight_stats' => $weight_stats
    ];
}

/**
 * Scans all WordPress installations under $home using the system `find` command.
 * This approach is symlink-safe, depth-unlimited, and handles all DirectAdmin layouts.
 */
function scan_wordpress_installations($home, $username) {
    $installations = [];
    $seen_realpaths = [];

    // Use find to locate all wp-config.php files under the user's home directory.
    // Exclude known WP internal directories to avoid false positives.
    $cmd = sprintf(
        'find %s -name "wp-config.php" -not -path "*/wp-content/*" -not -path "*/node_modules/*" -not -path "*/.git/*" 2>/dev/null',
        escapeshellarg($home)
    );

    $output = [];
    exec($cmd, $output);

    foreach ($output as $wp_config_path) {
        $wp_config_path = trim($wp_config_path);
        if ($wp_config_path === '') continue;

        // Deduplicate: two symlinked paths that resolve to the same file should only be listed once
        $real = realpath($wp_config_path);
        if ($real !== false) {
            if (isset($seen_realpaths[$real])) continue;
            $seen_realpaths[$real] = true;
        }

        $info = parse_wp_config($wp_config_path);
        if ($info) {
            $installations[] = $info;
        }
    }

    $cache_file = $home . '/.ultimate_wp_manager.json';
    file_put_contents($cache_file, json_encode($installations, JSON_PRETTY_PRINT));
    @chmod($cache_file, 0600);

    return $installations;
}


/**
 * Download WordPress Core and extract it
 */
function download_and_extract_wordpress($target_dir, $home) {
    $cache_dir = $home . '/.wp-cache';
    if (!is_dir($cache_dir)) {
        mkdir($cache_dir, 0755, true);
    }
    
    $zip_path = $cache_dir . '/latest.zip';
    
    // Download zip if missing or older than 24 hours
    if (!file_exists($zip_path) || (time() - filemtime($zip_path) > 86400)) {
        $ch = curl_init('https://wordpress.org/latest.zip');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 120);
        $data = curl_exec($ch);
        curl_close($ch);
        if ($data) {
            file_put_contents($zip_path, $data);
        }
    }
    
    if (!file_exists($zip_path) || filesize($zip_path) === 0) {
        throw new Exception("Unable to download or cache WordPress archive.");
    }
    
    // Extract using ZipArchive
    $zip = new ZipArchive;
    if ($zip->open($zip_path) === TRUE) {
        $temp_extract_dir = $target_dir . '/_temp_wp_extract';
        if (is_dir($temp_extract_dir)) {
            rmdir_recursive($temp_extract_dir);
        }
        mkdir($temp_extract_dir, 0755, true);
        
        $zip->extractTo($temp_extract_dir);
        $zip->close();
        
        $src_dir = $temp_extract_dir . '/wordpress';
        if (is_dir($src_dir)) {
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($src_dir, RecursiveDirectoryIterator::SKIP_DOTS),
                RecursiveIteratorIterator::SELF_FIRST
            );
            foreach ($iterator as $item) {
                $subPath = $iterator->getSubPathName();
                $target = $target_dir . '/' . $subPath;
                if ($item->isDir()) {
                    if (!is_dir($target)) {
                        mkdir($target, 0755, true);
                    }
                } else {
                    $parent_target = dirname($target);
                    if (!is_dir($parent_target)) {
                        mkdir($parent_target, 0755, true);
                    }
                    copy($item->getPathname(), $target);
                }
            }
        }
        
        rmdir_recursive($temp_extract_dir);
    } else {
        throw new Exception("Failed to extract WordPress core ZIP.");
    }
}

/**
 * Debug logging helper
 */
function wp_manager_log($msg) {
    $username = getenv('USERNAME') ?: getenv('USER') ?: 'nobody';
    $home = getenv('HOME') ?: getenv('USERPROFILE') ?: "/home/{$username}";
    $log_file = $home . '/.ultimate_wp_manager_debug.log';
    $timestamp = (new DateTime("now", new DateTimeZone("Asia/Ho_Chi_Minh")))->format('Y-m-d H:i:s');
    @file_put_contents($log_file, "[{$timestamp}] {$msg}\n", FILE_APPEND);
}

/**
 * Helper to resolve dynamic path overrides from DirectAdmin config files.
 */
function get_custom_docroot_from_configs($domain, $subdomain, $home, $wrapper) {
    $username = getenv('USERNAME') ?: getenv('USER') ?: 'nobody';
    if ($username === 'nobody' && strpos($home, '/home/') === 0) {
        $parts = explode('/', $home);
        if (isset($parts[2])) {
            $username = $parts[2];
        }
    }
    wp_manager_log("get_custom_docroot_from_configs: domain={$domain}, subdomain={$subdomain}, user={$username}");
    $domains_dir = $home . '/domains';
    
    // Check subdomains, conf, and custom HTTPD configurations via wrapper
    $types = ['subdomains', 'conf', 'cust_httpd', 'cust_nginx', 'cust_openlitespeed', 'cust_apache', 'subdomains.docroot.override'];
    $config_contents = [];
    foreach ($types as $type) {
        $cmd = escapeshellarg($wrapper) . " get_domain_config " . escapeshellarg($username) . " " . escapeshellarg($domain) . " " . escapeshellarg($type) . " 2>&1";
        $content = wp_exec($cmd);
        wp_manager_log("  wrapper query '{$type}': status=" . ($content !== null ? "success" : "failed") . ", length=" . strlen((string)$content));
        if ($content && strpos($content, 'Error:') === false) {
            $config_contents[$type] = $content;
        } else {
            if ($content) {
                wp_manager_log("    wrapper error: " . trim($content));
            }
        }
    }
    
    $custom_docroot = '';
    
    // 1. Search in subdomains.docroot.override file if resolving a subdomain
    if (empty($custom_docroot) && !empty($subdomain) && !empty($config_contents['subdomains.docroot.override'])) {
        wp_manager_log("  parsing subdomains.docroot.override");
        $lines = explode("\n", $config_contents['subdomains.docroot.override']);
        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line)) continue;
            wp_manager_log("    line: {$line}");
            $parts_line = explode('=', $line, 2);
            if (count($parts_line) === 2 && trim($parts_line[0]) === $subdomain) {
                $val = trim($parts_line[1]);
                $query_params = [];
                parse_str($val, $query_params);
                wp_manager_log("      matched subdomain '{$subdomain}', query_params: " . json_encode($query_params));
                if (!empty($query_params['public_html'])) {
                    $custom_docroot = $query_params['public_html'];
                    wp_manager_log("      found public_html override: {$custom_docroot}");
                    break;
                } elseif (!empty($query_params['private_html'])) {
                    $custom_docroot = $query_params['private_html'];
                    wp_manager_log("      found private_html override: {$custom_docroot}");
                    break;
                }
            }
        }
    }
    
    // 2. Search in custom overrides (.cust_*)
    if (empty($custom_docroot)) {
        foreach (['cust_httpd', 'cust_nginx', 'cust_openlitespeed', 'cust_apache'] as $type) {
            if (!empty($config_contents[$type])) {
                $content = $config_contents[$type];
                $lines = explode("\n", $content);
                $in_subdomain_block = false;
                
                foreach ($lines as $line) {
                    $line = trim($line);
                    if (empty($line)) continue;
                    
                    // If resolving a subdomain, keep track of conditional blocks:
                    // |*if SUB="subdomain"| or |*if SUB='subdomain'|
                    if (!empty($subdomain)) {
                        if (preg_match('/\|\*if\s+SUB=["\']' . preg_quote($subdomain, '/') . '["\']\|/i', $line)) {
                            $in_subdomain_block = true;
                            continue;
                        }
                        if (strpos($line, '|*endif|') !== false) {
                            $in_subdomain_block = false;
                            continue;
                        }
                    }
                    
                    // Check if target DOCROOT / SDOCROOT matches the block or main domain context
                    if (empty($subdomain) || $in_subdomain_block) {
                        if (preg_match('/\|\?(SDOCROOT|DOCROOT)=([^\s\|]+)/i', $line, $matches)) {
                            $val = trim($matches[2]);
                            $val = str_replace('`HOME`', $home, $val);
                            $val = str_replace('`DOMAIN`', $domain, $val);
                            $val = trim($val, '`\'"');
                            if (!empty($val)) {
                                $custom_docroot = $val;
                                if (!empty($subdomain)) {
                                    break 2; // Found for subdomain
                                }
                            }
                        }
                    }
                }
                
                if (empty($subdomain) && !empty($custom_docroot)) {
                    break;
                }
            }
        }
    }
    
    // 3. Search in .subdomains file if resolving a subdomain
    if (empty($custom_docroot) && !empty($subdomain) && !empty($config_contents['subdomains'])) {
        $lines = explode("\n", $config_contents['subdomains']);
        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line)) continue;
            $parts_line = explode('=', $line, 2);
            if (count($parts_line) === 2 && trim($parts_line[0]) === $subdomain) {
                $custom_docroot = trim($parts_line[1]);
                break;
            }
        }
    }
    
    // 3. Search in .conf file
    if (empty($custom_docroot) && !empty($config_contents['conf'])) {
        $lines = explode("\n", $config_contents['conf']);
        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line)) continue;
            $parts_line = explode('=', $line, 2);
            if (count($parts_line) === 2) {
                $key = trim($parts_line[0]);
                $val = trim($parts_line[1]);
                if (!empty($subdomain)) {
                    if ($key === 'subdomain_docroot_' . $subdomain || 
                        $key === 'subdomain_public_html_' . $subdomain || 
                        $key === 'subdomain_private_html_' . $subdomain) {
                        $custom_docroot = $val;
                        break;
                    }
                } else {
                    if ($key === 'docroot' || $key === 'public_html' || $key === 'private_html') {
                        $custom_docroot = $val;
                        break;
                    }
                }
            }
        }
    }
    
    // Resolve absolute path (handles relative paths like /domains/sub... or public_html/...)
    if (!empty($custom_docroot)) {
        wp_manager_log("  raw resolved custom_docroot: {$custom_docroot}");
        $custom_docroot = rtrim(trim($custom_docroot), '/');
        $resolved = '';
        if (strpos($custom_docroot, $home) === 0) {
            $resolved = $custom_docroot;
        } elseif (strpos($custom_docroot, '/home/') === 0) {
            $resolved = $custom_docroot;
        } elseif (strpos($custom_docroot, '/domains/') === 0) {
            $resolved = $home . $custom_docroot;
        } elseif (strpos($custom_docroot, 'domains/') === 0) {
            $resolved = $home . '/' . $custom_docroot;
        } elseif (strpos($custom_docroot, '/public_html/') === 0) {
            $resolved = $home . $custom_docroot;
        } elseif (strpos($custom_docroot, 'public_html/') === 0) {
            $resolved = $home . '/' . $custom_docroot;
        } elseif (strpos($custom_docroot, '/') === 0) {
            if (is_dir($custom_docroot)) {
                $resolved = $custom_docroot;
            } else {
                $resolved = $home . $custom_docroot;
            }
        } else {
            $resolved = $home . '/' . $custom_docroot;
        }
        wp_manager_log("  absolute resolved custom_docroot: {$resolved}");
        return $resolved;
    }
    
    wp_manager_log("  no custom_docroot found in configs");
    return '';
}

/**
 * Resolves a selected domain string into parent domain, subdomain prefix (if any), and its document root directory.
 */
function resolve_domain_path($domain_str, $home) {
    wp_manager_log("resolve_domain_path: domain_str={$domain_str}");
    $domains_dir = $home . '/domains';
    
    $wrapper = '/usr/local/directadmin/plugins/ultimate-directadmin-wordpress-manager/scripts/wrapper';
    if (!file_exists($wrapper)) {
        $wrapper = dirname(__FILE__) . '/scripts/wrapper';
    }
    
    // First, check if the domain_str exists directly as a main domain folder or a custom domain folder
    if (is_dir($domains_dir . '/' . $domain_str)) {
        wp_manager_log("  domain_str exists directly as directory: {$domain_str}");
        $custom_docroot = get_custom_docroot_from_configs($domain_str, '', $home, $wrapper);
        if (!empty($custom_docroot)) {
            $doc_root = $custom_docroot;
        } else {
            $doc_root = $domains_dir . '/' . $domain_str;
            if (is_dir($doc_root . '/public_html')) {
                $doc_root .= '/public_html';
            }
        }
        
        $parts = explode('.', $domain_str);
        $is_subdomain = false;
        $parent = $domain_str;
        $subdomain = '';
        
        // Determine if it looks like a subdomain structure
        for ($i = 1; $i < count($parts) - 1; $i++) {
            $p = implode('.', array_slice($parts, $i));
            if (is_dir($domains_dir . '/' . $p)) {
                $is_subdomain = true;
                $parent = $p;
                $subdomain = implode('.', array_slice($parts, 0, $i));
                break;
            }
        }
        
        $res = [
            'is_subdomain' => $is_subdomain,
            'parent_domain' => $parent,
            'subdomain_prefix' => $subdomain,
            'doc_root' => $doc_root
        ];
        wp_manager_log("  resolved to: " . json_encode($res));
        return $res;
    }
    
    // If not, it could be a subdomain. Let's find if a parent domain matches the suffix.
    $parts = explode('.', $domain_str);
    for ($i = 1; $i < count($parts) - 1; $i++) {
        $parent = implode('.', array_slice($parts, $i));
        if (is_dir($domains_dir . '/' . $parent)) {
            $subdomain = implode('.', array_slice($parts, 0, $i));
            wp_manager_log("  matched parent domain folder: {$parent}, subdomain_prefix: {$subdomain}");
            
            // Query DirectAdmin configs via SUID wrapper to check for custom document root
            $custom_docroot = get_custom_docroot_from_configs($parent, $subdomain, $home, $wrapper);
            if (!empty($custom_docroot)) {
                $res = [
                    'is_subdomain' => true,
                    'parent_domain' => $parent,
                    'subdomain_prefix' => $subdomain,
                    'doc_root' => $custom_docroot
                ];
                wp_manager_log("  resolved to: " . json_encode($res));
                return $res;
            }
            
            // Now check standard subdomain directories
            $subdomain_dir = $domains_dir . '/' . $parent . '/subdomains/' . $subdomain . '/public_html';
            if (is_dir($domains_dir . '/' . $parent . '/subdomains/' . $subdomain)) {
                $doc_root = $subdomain_dir;
            } else {
                // Fallback to public_html/subdomain
                // Check if parent domain has a custom document root
                $parent_custom_docroot = get_custom_docroot_from_configs($parent, '', $home, $wrapper);
                $parent_docroot = !empty($parent_custom_docroot) ? $parent_custom_docroot : ($domains_dir . '/' . $parent . '/public_html');
                
                $doc_root = $parent_docroot . '/' . $subdomain;
            }
            
            $res = [
                'is_subdomain' => true,
                'parent_domain' => $parent,
                'subdomain_prefix' => $subdomain,
                'doc_root' => $doc_root
            ];
            wp_manager_log("  resolved to fallback: " . json_encode($res));
            return $res;
        }
    }
    
    // Fallback: treat it as a main domain, even if folder doesn't exist yet
    $res = [
        'is_subdomain' => false,
        'parent_domain' => $domain_str,
        'subdomain_prefix' => '',
        'doc_root' => $domains_dir . '/' . $domain_str . '/public_html'
    ];
    wp_manager_log("  resolved to absolute fallback: " . json_encode($res));
    return $res;
}


/**
 * Helper to recursively copy directories
 */
function copy_dir_recursive($src, $dst) {
    if (!is_dir($dst)) {
        mkdir($dst, 0755, true);
    }
    
    // Resolve real paths to prevent infinite recursion
    $real_src = realpath($src);
    $real_dst = realpath($dst);
    
    $dir = opendir($src);
    while (false !== ($file = readdir($dir))) {
        if (($file != '.') && ($file != '..') && ($file != '.locked_mock') && ($file != '.git')) {
            // Skip wp-content/cache folder to avoid copying heavy caching data
            if ($file === 'cache' && basename($src) === 'wp-content') {
                continue;
            }
            
            $src_file = $src . '/' . $file;
            $dst_file = $dst . '/' . $file;
            
            // Resolve real path of current item
            $real_item = realpath($src_file);
            if ($real_item !== false && $real_dst !== false) {
                // Skip if this item is the target directory
                if ($real_item === $real_dst) {
                    continue;
                }
                // Skip if target directory is inside this subdirectory
                if (strpos($real_dst . '/', $real_item . '/') === 0) {
                    continue;
                }
            }
            
            if (is_dir($src_file)) {
                copy_dir_recursive($src_file, $dst_file);
            } else {
                copy($src_file, $dst_file);
            }
        }
    }
    closedir($dir);
}

/**
 * Programmatic WordPress Cloner
 */
function clone_wordpress_instance($params, $home) {
    $src_dir = $params['src_path'] ?? '';
    $domain = $params['domain'] ?? '';
    $subdir = $params['subdir'] ?? '';
    $db_name = $params['db_name'] ?? '';
    $db_user = $params['db_user'] ?? '';
    $db_pass = $params['db_pass'] ?? '';
    $protocol = $params['protocol'] ?? 'http';
    
    if (empty($src_dir) || !file_exists($src_dir . '/wp-config.php')) {
        throw new Exception("Thư mục nguồn không hợp lệ hoặc không chứa wp-config.php.");
    }
    
    // Sanitize path inputs
    $domain_clean = str_replace(['..', '/', '\\'], '', $domain);
    $subdir_clean = str_replace(['..', '\\'], '', $subdir);
    $subdir_clean = trim($subdir_clean, '/');
    
    // Resolve target path
    $domain_info = resolve_domain_path($domain_clean, $home);
    $target_dir = $domain_info['doc_root'];
    if ($subdir_clean !== '') {
        $target_dir .= '/' . $subdir_clean;
    }
    
    // Security check: must reside inside home directory
    $check_path = realpath($target_dir) ?: $target_dir;
    if (strpos($check_path, $home) !== 0) {
        throw new Exception("Error: Thư mục đích phải nằm trong thư mục home của bạn.");
    }
    
    if (file_exists($target_dir . '/wp-config.php')) {
        throw new Exception("Thư mục đích đã chứa một cài đặt WordPress (wp-config.php đã tồn tại).");
    }
    
    // 1. Copy files
    copy_dir_recursive($src_dir, $target_dir);
    set_permissions_recursive($target_dir);
    
    // 2. Export source DB
    $src_config = $src_dir . '/wp-config.php';
    $src_content = file_get_contents($src_config);
    preg_match("/define\s*\(\s*['\"]DB_NAME['\"]\s*,\s*['\"](.*?)['\"]\s*\)/", $src_content, $db_name_match);
    preg_match("/define\s*\(\s*['\"]DB_USER['\"]\s*,\s*['\"](.*?)['\"]\s*\)/", $src_content, $db_user_match);
    preg_match("/define\s*\(\s*['\"]DB_PASSWORD['\"]\s*,\s*['\"](.*?)['\"]\s*\)/", $src_content, $db_pass_match);
    preg_match("/define\s*\(\s*['\"]DB_HOST['\"]\s*,\s*['\"](.*?)['\"]\s*\)/", $src_content, $db_host_match);
    preg_match("/\\\$table_prefix\s*=\s*['\"](.*?)['\"]/", $src_content, $prefix_match);
    
    $src_db_name = $db_name_match[1] ?? '';
    $src_db_user = $db_user_match[1] ?? '';
    $src_db_pass = $db_pass_match[1] ?? '';
    $src_db_host = $db_host_match[1] ?? 'localhost';
    $db_prefix = $prefix_match[1] ?? 'wp_';
    
    $src_db = wp_manager_get_db_conn($src_config);
    $src_siteurl = '';
    if ($src_db) {
        try {
            $stmt = $src_db['pdo']->prepare("SELECT option_value FROM `{$db_prefix}options` WHERE option_name = 'siteurl'");
            $stmt->execute();
            $src_siteurl = rtrim($stmt->fetchColumn(), '/');
        } catch (Exception $e) {}
    }
    
    $dump_file = sys_get_temp_dir() . '/clone_dump_' . time() . '.sql';
    $dump_error_file = sys_get_temp_dir() . '/clone_dump_err_' . time() . '.log';
    
    $cmd_dump = sprintf(
        'mysqldump -h %s -u %s -p%s %s > %s 2>%s',
        escapeshellarg($src_db_host),
        escapeshellarg($src_db_user),
        escapeshellarg($src_db_pass),
        escapeshellarg($src_db_name),
        escapeshellarg($dump_file),
        escapeshellarg($dump_error_file)
    );
    
    $retval = null;
    exec($cmd_dump, $output_dump, $retval);
    
    if ($retval !== 0) {
        $err_msg = file_exists($dump_error_file) ? file_get_contents($dump_error_file) : '';
        @unlink($dump_error_file);
        @unlink($dump_file);
        rmdir_recursive($target_dir, true);
        throw new Exception("Lỗi khi xuất database nguồn: " . $err_msg);
    }
    @unlink($dump_error_file);
    
    // 3. Import target DB
    $import_error_file = sys_get_temp_dir() . '/clone_import_err_' . time() . '.log';
    $cmd_import = sprintf(
        'mysql -h localhost -u %s -p%s %s < %s 2>%s',
        escapeshellarg($db_user),
        escapeshellarg($db_pass),
        escapeshellarg($db_name),
        escapeshellarg($dump_file),
        escapeshellarg($import_error_file)
    );
    
    $retval_import = null;
    exec($cmd_import, $output_import, $retval_import);
    @unlink($dump_file);
    
    if ($retval_import !== 0) {
        $err_msg = file_exists($import_error_file) ? file_get_contents($import_error_file) : '';
        @unlink($import_error_file);
        rmdir_recursive($target_dir, true);
        throw new Exception("Lỗi khi nhập database đích: " . $err_msg);
    }
    @unlink($import_error_file);
    
    // 4. Update wp-config.php in target
    $tgt_config = $target_dir . '/wp-config.php';
    if (file_exists($tgt_config)) {
        @chmod($tgt_config, 0644); // Ensure it is writable
        $content = file_get_contents($tgt_config);
        $content = preg_replace("/define\s*\(\s*['\"]DB_NAME['\"]\s*,\s*['\"].*?['\"]\s*\)/", "define('DB_NAME', '" . addslashes($db_name) . "')", $content);
        $content = preg_replace("/define\s*\(\s*['\"]DB_USER['\"]\s*,\s*['\"].*?['\"]\s*\)/", "define('DB_USER', '" . addslashes($db_user) . "')", $content);
        $content = preg_replace("/define\s*\(\s*['\"]DB_PASSWORD['\"]\s*,\s*['\"].*?['\"]\s*\)/", "define('DB_PASSWORD', '" . addslashes($db_pass) . "')", $content);
        $content = preg_replace("/define\s*\(\s*['\"]DB_HOST['\"]\s*,\s*['\"](.*?)['\"]\s*\)/", "define('DB_HOST', 'localhost')", $content);
        
        // Remove existing WP_SITEURL and WP_HOME if present
        $content = preg_replace("/\s*define\s*\(\s*['\"]WP_SITEURL['\"]\s*,\s*[^;]*\)\s*;/i", "", $content);
        $content = preg_replace("/\s*define\s*\(\s*['\"]WP_HOME['\"]\s*,\s*[^;]*\)\s*;/i", "", $content);
        
        $dynamic_url = "'https://' . \$_SERVER['HTTP_HOST']" . ($subdir_clean !== '' ? " . '/" . addslashes($subdir_clean) . "'" : "");
        $extra_defines = "\ndefine('WP_SITEURL', {$dynamic_url});\ndefine('WP_HOME', {$dynamic_url});\n";
        
        $insert_pos = strpos($content, "/* That's all, stop editing!");
        if ($insert_pos === false) {
            $insert_pos = strpos($content, "require_once");
        }
        if ($insert_pos !== false) {
            $content = substr_replace($content, $extra_defines, $insert_pos, 0);
        } else {
            $content .= $extra_defines;
        }
        
        // Regenerate all WordPress security keys & salts to prevent session/cache
        // collisions between the cloned site and the original site
        $wp_security_keys = [
            'AUTH_KEY', 'SECURE_AUTH_KEY', 'LOGGED_IN_KEY', 'NONCE_KEY',
            'AUTH_SALT', 'SECURE_AUTH_SALT', 'LOGGED_IN_SALT', 'NONCE_SALT',
            'WP_CACHE_KEY_SALT',
        ];
        foreach ($wp_security_keys as $key_name) {
            $new_value = wp_generate_random_secret(64);
            // Replace existing define if present
            $pattern = "/define\s*\(\s*['\"]" . preg_quote($key_name, '/') . "['\"]\s*,\s*'[^']*'\s*\)\s*;/";
            $replacement = "define('" . $key_name . "', '" . addslashes($new_value) . "');";
            if (preg_match($pattern, $content)) {
                $content = preg_replace($pattern, $replacement, $content);
            }
            // Also handle double-quoted values
            $pattern_dq = "/define\s*\(\s*['\"]" . preg_quote($key_name, '/') . "['\"]\s*,\s*\"[^\"]*\"\s*\)\s*;/";
            if (preg_match($pattern_dq, $content)) {
                $content = preg_replace($pattern_dq, $replacement, $content);
            }
        }
        
        file_put_contents($tgt_config, $content);
        @chmod($tgt_config, 0600); // Restore secure permissions

    }
    
    // 5. Database search and replace
    $new_siteurl = ($protocol === 'https' ? 'https://' : 'http://') . $domain_clean . ($subdir_clean !== '' ? '/' . $subdir_clean : '');
    $tgt_siteurl = rtrim($new_siteurl, '/');
    
    try {
        $dsn = "mysql:host=localhost;dbname={$db_name};charset=utf8mb4";
        $pdo = new PDO($dsn, $db_user, $db_pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_TIMEOUT => 5
        ]);
        
        // Update siteurl and home
        $stmt = $pdo->prepare("UPDATE `{$db_prefix}options` SET option_value = ? WHERE option_name IN ('siteurl', 'home')");
        $stmt->execute([$tgt_siteurl]);
        
        if (!empty($src_siteurl) && $src_siteurl !== $tgt_siteurl) {
            // Update posts content, excerpt, and guid
            $stmt = $pdo->prepare("UPDATE `{$db_prefix}posts` SET 
                post_content = REPLACE(post_content, ?, ?),
                post_excerpt = REPLACE(post_excerpt, ?, ?),
                guid = REPLACE(guid, ?, ?)
            ");
            $stmt->execute([$src_siteurl, $tgt_siteurl, $src_siteurl, $tgt_siteurl, $src_siteurl, $tgt_siteurl]);
            
            // Update postmeta
            $stmt = $pdo->prepare("UPDATE `{$db_prefix}postmeta` SET meta_value = REPLACE(meta_value, ?, ?) WHERE meta_value LIKE ?");
            $stmt->execute([$src_siteurl, $tgt_siteurl, '%' . $src_siteurl . '%']);
            
            // Update options
            $stmt = $pdo->prepare("UPDATE `{$db_prefix}options` SET option_value = REPLACE(option_value, ?, ?) WHERE option_name NOT IN ('siteurl', 'home') AND option_value LIKE ?");
            $stmt->execute([$src_siteurl, $tgt_siteurl, '%' . $src_siteurl . '%']);
        }
    } catch (Exception $e) {
        wp_manager_log("Search and replace failed: " . $e->getMessage());
    }
    
    // If running as root/admin, fix file ownership to target user using native PHP calls
    // (exec/shell forks lose SUID elevation; native chown() runs in-process as root)
    if (is_admin_user() || (function_exists('posix_getuid') && posix_getuid() === 0)) {
        $tgt_user = basename($home);
        $pw = function_exists('posix_getpwnam') ? posix_getpwnam($tgt_user) : false;
        if ($pw) {
            $uid = $pw['uid'];
            $gid = $pw['gid'];
            // Recursively chown every file and directory to the target user
            $iter = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($target_dir, RecursiveDirectoryIterator::SKIP_DOTS),
                RecursiveIteratorIterator::SELF_FIRST
            );
            chown($target_dir, $uid);
            chgrp($target_dir, $gid);
            foreach ($iter as $item) {
                chown($item->getPathname(), $uid);
                chgrp($item->getPathname(), $gid);
            }
        }
    }
    
    return [
        'success' => true,
        'siteurl' => $new_siteurl,
        'message' => 'Clone website WordPress thành công.'
    ];
}

/**
 * Core WordPress programmatic Installer
 */
function install_wordpress_instance($params, $home) {
    $mode = $params['mode'] ?? 'fresh';
    $domain = $params['domain'];
    $subdir = $params['subdir'] ?? '';
    $db_name = $params['db_name'];
    $db_user = $params['db_user'];
    $db_pass = $params['db_pass'];
    $protocol = $params['protocol'] ?? 'http';
    
    // Sanitize path inputs
    $domain_clean = str_replace(['..', '/', '\\'], '', $domain);
    $subdir_clean = str_replace(['..', '\\'], '', $subdir);
    $subdir_clean = trim($subdir_clean, '/');
    
    // Resolve the directory root and subdomain mapping
    $domain_info = resolve_domain_path($domain_clean, $home);
    $target_dir = $domain_info['doc_root'];
    if ($subdir_clean !== '') {
        $target_dir .= '/' . $subdir_clean;
    }
    
    $site_host = $domain_clean;
    
    // Security check: must reside inside home directory
    $check_path = realpath($target_dir) ?: $target_dir;
    if (strpos($check_path, $home) !== 0) {
        throw new Exception("Error: Target path must be within your home folder.");
    }
    
    if (!is_dir($target_dir)) {
        mkdir($target_dir, 0755, true);
    }
    
    if (file_exists($target_dir . '/wp-config.php')) {
        throw new Exception("WordPress is already configured in this folder.");
    }
    
    if ($mode === 'zip') {
        // Zip install from path
        $zip_path = $params['zip_path'] ?? '';
        if (empty($zip_path) || !file_exists($zip_path)) {
            throw new Exception("Không tìm thấy tệp ZIP cấu hình tại đường dẫn: " . $zip_path);
        }
        
        // Save copy to .wp-cache for debugging
        $cache_dir = $home . '/.wp-cache';
        if (!is_dir($cache_dir)) {
            mkdir($cache_dir, 0755, true);
        }
        @copy($zip_path, $cache_dir . '/uploaded_backup.zip');
        
        $zip = new ZipArchive;
        if ($zip->open($zip_path) === TRUE) {
            $zip->extractTo($target_dir);
            $zip->close();
        } else {
            throw new Exception("Không thể giải nén tệp ZIP source code.");
        }
        
        // Scan for database file
        $files = scandir($target_dir);
        $db_file = null;
        
        // Priority 1: *.sql.gz
        foreach ($files as $f) {
            if (preg_match('/\.sql\.gz$/i', $f)) {
                $db_file = $f;
                break;
            }
        }
        // Priority 2: *.sql
        if (!$db_file) {
            foreach ($files as $f) {
                if (preg_match('/\.sql$/i', $f)) {
                    $db_file = $f;
                    break;
                }
            }
        }
        // Priority 3: *.gz (excluding *.sql.gz)
        if (!$db_file) {
            foreach ($files as $f) {
                if (preg_match('/\.gz$/i', $f) && !preg_match('/\.sql\.gz$/i', $f)) {
                    $db_file = $f;
                    break;
                }
            }
        }
        
        $db_imported = false;
        $db_prefix = 'wp_';
        
        if ($db_file) {
            $db_file_path = $target_dir . '/' . $db_file;
            
            // Build MySQL command
            if (preg_match('/\.gz$/i', $db_file)) {
                $cmd = sprintf(
                    'gunzip -c %s | mysql -h %s -u %s -p%s %s 2>&1',
                    escapeshellarg($db_file_path),
                    escapeshellarg('localhost'),
                    escapeshellarg($db_user),
                    escapeshellarg($db_pass),
                    escapeshellarg($db_name)
                );
            } else {
                $cmd = sprintf(
                    'mysql -h %s -u %s -p%s %s < %s 2>&1',
                    escapeshellarg('localhost'),
                    escapeshellarg($db_user),
                    escapeshellarg($db_pass),
                    escapeshellarg($db_name),
                    escapeshellarg($db_file_path)
                );
            }
            
            $output = [];
            $retval = null;
            exec($cmd, $output, $retval);
            
            // Delete the database file immediately for security
            @unlink($db_file_path);
            
            // Update wp-config.php with new database details
            $wp_config_path = $target_dir . '/wp-config.php';
            if (file_exists($wp_config_path)) {
                @chmod($wp_config_path, 0644); // Ensure it is writable
                $content = file_get_contents($wp_config_path);
                
                // Extract db table prefix
                if (preg_match("/\\\$table_prefix\s*=\s*['\"](.*?)['\"]/", $content, $prefix_match)) {
                    $db_prefix = $prefix_match[1];
                }
                
                $content = preg_replace("/define\s*\(\s*['\"]DB_NAME['\"]\s*,\s*['\"].*?['\"]\s*\)/", "define('DB_NAME', '" . addslashes($db_name) . "')", $content);
                $content = preg_replace("/define\s*\(\s*['\"]DB_USER['\"]\s*,\s*['\"].*?['\"]\s*\)/", "define('DB_USER', '" . addslashes($db_user) . "')", $content);
                $content = preg_replace("/define\s*\(\s*['\"]DB_PASSWORD['\"]\s*,\s*['\"].*?['\"]\s*\)/", "define('DB_PASSWORD', '" . addslashes($db_pass) . "')", $content);
                $content = preg_replace("/define\s*\(\s*['\"]DB_HOST['\"]\s*,\s*['\"](.*?)['\"]\s*\)/", "define('DB_HOST', 'localhost')", $content);
                
                // Remove existing WP_SITEURL and WP_HOME if present
                $content = preg_replace("/\s*define\s*\(\s*['\"]WP_SITEURL['\"]\s*,\s*[^;]*\)\s*;/i", "", $content);
                $content = preg_replace("/\s*define\s*\(\s*['\"]WP_HOME['\"]\s*,\s*[^;]*\)\s*;/i", "", $content);
                
                $dynamic_url = "'https://' . \$_SERVER['HTTP_HOST']" . ($subdir_clean !== '' ? " . '/" . addslashes($subdir_clean) . "'" : "");
                $extra_defines = "\ndefine('WP_SITEURL', {$dynamic_url});\ndefine('WP_HOME', {$dynamic_url});\n";
                
                $insert_pos = strpos($content, "/* That's all, stop editing!");
                if ($insert_pos === false) {
                    $insert_pos = strpos($content, "require_once");
                }
                if ($insert_pos !== false) {
                    $content = substr_replace($content, $extra_defines, $insert_pos, 0);
                } else {
                    $content .= $extra_defines;
                }
                
                file_put_contents($wp_config_path, $content);
                @chmod($wp_config_path, 0600); // Restore secure permissions
            }
            
            if ($retval !== 0) {
                $err_detail = implode("\n", $output);
                throw new Exception("Lỗi khi nhập database backup: " . $err_detail);
            }
            $db_imported = true;
        }
        
        // Update siteurl and home in database
        if ($db_imported) {
            $new_siteurl = ($protocol === 'https' ? 'https://' : 'http://') . $site_host . ($subdir_clean !== '' ? '/' . $subdir_clean : '');
            try {
                $dsn = "mysql:host=localhost;dbname={$db_name};charset=utf8mb4";
                $pdo = new PDO($dsn, $db_user, $db_pass, [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_TIMEOUT => 5
                ]);
                
                $stmt = $pdo->prepare("UPDATE `{$db_prefix}options` SET option_value = ? WHERE option_name IN ('siteurl', 'home')");
                $stmt->execute([$new_siteurl]);
            } catch (Exception $e) {
                wp_manager_log("Failed to update siteurl/home: " . $e->getMessage());
            }
        }
        
        // Force cache refresh
        $cache_file = $home . '/.ultimate_wp_manager.json';
        if (file_exists($cache_file)) {
            @unlink($cache_file);
        }
        
        return [
            'success' => true,
            'siteurl' => ($protocol === 'https' ? 'https://' : 'http://') . $site_host . ($subdir_clean !== '' ? '/' . $subdir_clean : ''),
            'details' => 'Cài đặt từ ZIP hoàn tất.'
        ];
        
    } else {
        // Fresh install
        $site_title = $params['site_title'];
        $admin_user = $params['admin_user'];
        $admin_pass = $params['admin_pass'];
        $admin_email = $params['admin_email'];
        $request_uri = $subdir_clean !== '' ? '/' . $subdir_clean . '/' : '/';
        
        // Download & Unpack
        download_and_extract_wordpress($target_dir, $home);
        
        // Fetch security keys
        $salts = '';
        $ch = curl_init('https://api.wordpress.org/secret-key/1.1/salt/');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 6);
        $salts = curl_exec($ch);
        curl_close($ch);
        
        if (!$salts || strpos($salts, 'define') === false) {
            $salts = '';
            $keys = ['AUTH_KEY', 'SECURE_AUTH_KEY', 'LOGGED_IN_KEY', 'NONCE_KEY', 'AUTH_SALT', 'SECURE_AUTH_SALT', 'LOGGED_IN_SALT', 'NONCE_SALT'];
            foreach ($keys as $key) {
                $random_salt = bin2hex(random_bytes(32));
                $salts .= "define('{$key}', '{$random_salt}');\n";
            }
        }
        
        // Write wp-config.php
        $wp_config_content = "<?php\n" .
             "define('DB_NAME', '" . addslashes($db_name) . "');\n" .
             "define('DB_USER', '" . addslashes($db_user) . "');\n" .
             "define('DB_PASSWORD', '" . addslashes($db_pass) . "');\n" .
             "define('DB_HOST', 'localhost');\n" .
             "define('DB_CHARSET', 'utf8mb4');\n" .
             "define('DB_COLLATE', '');\n\n" .
             $salts . "\n" .
             "\$table_prefix = 'wp_';\n\n" .
             "define('WP_DEBUG', false);\n\n" .
             "if (!defined('ABSPATH')) {\n" .
             "    define('ABSPATH', __DIR__ . '/');\n" .
             "}\n\n" .
             "require_once ABSPATH . 'wp-settings.php';\n";
             
        file_put_contents($target_dir . '/wp-config.php', $wp_config_content);
        @chmod($target_dir . '/wp-config.php', 0600);
        
        // Mock server context for installation script
        $_SERVER['HTTP_HOST'] = $site_host;
        $_SERVER['SERVER_NAME'] = $site_host;
        $_SERVER['REQUEST_URI'] = $request_uri;
        $_SERVER['SERVER_PROTOCOL'] = 'HTTP/1.1';
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
        if ($protocol === 'https') {
            $_SERVER['HTTPS'] = 'on';
        }
        
        define('WP_INSTALLING', true);
        
        if (!file_exists($target_dir . '/wp-load.php')) {
            throw new Exception("Critical error: core extraction check failed.");
        }
        
        // Run installer programmatically
        require_once $target_dir . '/wp-load.php';
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        
        $install_result = wp_install($site_title, $admin_user, $admin_email, true, '', $admin_pass);
        
        // Force cache refresh
        $cache_file = $home . '/.ultimate_wp_manager.json';
        if (file_exists($cache_file)) {
            @unlink($cache_file);
        }
        
        return [
            'success' => true,
            'siteurl' => ($protocol === 'https' ? 'https://' : 'http://') . $site_host . ($subdir_clean !== '' ? '/' . $subdir_clean : ''),
            'details' => $install_result
        ];
    }
}

/**
 * Generate Magic Login temporary mu-plugin
 */
function generate_magic_login($site_path, $home, $user_id = null) {
    if (strpos(realpath($site_path) ?: $site_path, $home) !== 0) {
        throw new Exception("Invalid directory path constraints.");
    }
    
    $wp_config_path = $site_path . '/wp-config.php';
    if (!file_exists($wp_config_path)) {
        throw new Exception("No WordPress configuration found at site path.");
    }
    
    $mu_dir = $site_path . '/wp-content/mu-plugins';
    if (!is_dir($mu_dir)) {
        mkdir($mu_dir, 0755, true);
    }
    
    $token = bin2hex(random_bytes(16));
    $target_user_id = $user_id !== null ? (int)$user_id : 0;
    
    $mu_code = <<<'PHP'
<?php
/*
Plugin Name: Magic Login Temporary Plugin
Description: Temporary auto-login plugin created by DirectAdmin WP Manager.
Version: 1.0
Author: DirectAdmin WP Manager
*/
add_action('init', function() {
    if (isset($_GET['magic_login']) && $_GET['magic_login'] === '{{TOKEN}}') {
        // Delete the mu-plugin immediately on execution to prevent leftover files if wp_login hook redirects/exits
        @unlink(__FILE__);
        
        require_once ABSPATH . 'wp-includes/pluggable.php';
        $target_user_id = {{USER_ID}};
        $user = $target_user_id > 0 ? get_user_by('id', $target_user_id) : null;
        if (!$user && $target_user_id <= 0) {
            $users = get_users(['role' => 'administrator', 'number' => 1]);
            $user = !empty($users) ? $users[0] : null;
        }
        if ($user) {
            wp_clear_auth_cookie();
            wp_set_current_user($user->ID, $user->user_login);
            wp_set_auth_cookie($user->ID, true);
            do_action('wp_login', $user->user_login, $user);
            
            // Redirect to dashboard
            wp_safe_redirect(admin_url());
            exit;
        }
    }
});
PHP;

    $mu_code = str_replace('{{TOKEN}}', $token, $mu_code);
    $mu_code = str_replace('{{USER_ID}}', (string)$target_user_id, $mu_code);
    $mu_plugin_file = $mu_dir . '/magic-login-' . $token . '.php';
    file_put_contents($mu_plugin_file, $mu_code);
    @chmod($mu_plugin_file, 0644);
    
    // Parse site URL for redirection (extract domain and subpath for fallback)
    $domain = '';
    $sub_path = '';
    $domains_prefix = $home . '/domains/';
    if (strpos($site_path, $domains_prefix) === 0) {
        $relative = substr($site_path, strlen($domains_prefix));
        $parts = explode('/', $relative);
        if (count($parts) > 0) {
            $domain = $parts[0];
            
            // Check if it's in a subdomains directory: e.g. domain.com/subdomains/sub/public_html
            $subdomains_index = array_search('subdomains', $parts);
            if ($subdomains_index !== false && isset($parts[$subdomains_index + 1])) {
                $subdomain_name = $parts[$subdomains_index + 1];
                $domain = $subdomain_name . '.' . $domain;
                
                $pub_index = array_search('public_html', $parts);
                if ($pub_index !== false && $pub_index < count($parts) - 1) {
                    $sub_path = implode('/', array_slice($parts, $pub_index + 1));
                }
            } else {
                $pub_index = array_search('public_html', $parts);
                if ($pub_index !== false && $pub_index < count($parts) - 1) {
                    $sub_path = implode('/', array_slice($parts, $pub_index + 1));
                }
            }
        }
    }
    
    $info = parse_wp_config($wp_config_path, $domain, $sub_path);
    $siteurl = $info['siteurl'] ?? '';
    
    return [
        'success' => true,
        'login_url' => rtrim($siteurl, '/') . '/?magic_login=' . $token
    ];
}

/**
 * Safely delete WordPress installation files
 */
function delete_wordpress_instance($site_path, $home) {
    if (strpos(realpath($site_path) ?: $site_path, $home) !== 0) {
        throw new Exception("Invalid directory access.");
    }
    
    $wp_config = $site_path . '/wp-config.php';
    if (!file_exists($wp_config)) {
        throw new Exception("Safety block: Could not locate wp-config.php. Deletion canceled.");
    }
    
    // Delete only the files and folders inside the site path, keeping the root directory itself
    if (is_dir($site_path)) {
        $files = array_diff(scandir($site_path), ['.', '..']);
        foreach ($files as $file) {
            $path = $site_path . '/' . $file;
            if (is_dir($path) && !is_link($path)) {
                rmdir_recursive($path);
            } else {
                @unlink($path);
            }
        }
    }
    
    // Force cache refresh
    $cache_file = $home . '/.ultimate_wp_manager.json';
    if (file_exists($cache_file)) {
        @unlink($cache_file);
    }
    
    return [
        'success' => true,
        'message' => 'WordPress files successfully deleted.'
    ];
}

/**
 * Manage user crontab
 */
function get_user_cronjobs() {
    $output = [];
    $retval = null;
    @exec('crontab -l 2>/dev/null', $output, $retval);
    return $output;
}

function save_user_cronjobs($cronjobs) {
    // Write cronjobs to a temporary file and load them via crontab command
    $tmp_file = tempnam(sys_get_temp_dir(), 'cron');
    file_put_contents($tmp_file, implode("\n", $cronjobs) . "\n");
    
    $output = [];
    $retval = null;
    @exec('crontab ' . escapeshellarg($tmp_file) . ' 2>&1', $output, $retval);
    @unlink($tmp_file);
    
    if ($retval !== 0) {
        throw new Exception("Lỗi khi lưu cronjob: " . implode("\n", $output));
    }
    return true;
}

function toggle_site_cronjob($site_path, $enable) {
    $cron_file = rtrim($site_path, '/') . '/wp-cron.php';
    $cron_line_pattern = $cron_file;
    
    $cronjobs = get_user_cronjobs();
    $new_cronjobs = [];
    $found = false;
    
    foreach ($cronjobs as $line) {
        $line = trim($line);
        if ($line === '') continue;
        if (strpos($line, $cron_line_pattern) !== false) {
            $found = true;
            if ($enable) {
                $new_cronjobs[] = "*/10 * * * * /usr/local/bin/php " . escapeshellarg($cron_file) . " >/dev/null 2>&1";
            }
        } else {
            $new_cronjobs[] = $line;
        }
    }
    
    if ($enable && !$found) {
        $new_cronjobs[] = "*/10 * * * * /usr/local/bin/php " . escapeshellarg($cron_file) . " >/dev/null 2>&1";
    }
    
    save_user_cronjobs($new_cronjobs);
}

/**
 * Toggle WP Cron disable status (config + cronjob)
 */
function toggle_wordpress_cron($site_path, $enable) {
    if (is_wordpress_locked($site_path)) {
        throw new Exception("Website is under WordPress Lockdown. Please disable Lockdown before modifying WP Cron status.");
    }
    
    $wp_config_path = $site_path . '/wp-config.php';
    if (!file_exists($wp_config_path)) {
        throw new Exception("Safety block: wp-config.php not found.");
    }
    check_wp_config_writable($wp_config_path);
    
    $content = file_get_contents($wp_config_path);
    
    // Remove any existing definition of DISABLE_WP_CRON
    $pattern = "/\s*define\s*\(\s*['\"]DISABLE_WP_CRON['\"]\s*,\s*[^;]*\)\s*;/i";
    $content = preg_replace($pattern, '', $content);
    
    if ($enable) {
        $define_str = "\ndefine('DISABLE_WP_CRON', true);\n";
        $insert_pos = strpos($content, "/* That's all, stop editing!");
        if ($insert_pos === false) {
            $insert_pos = strpos($content, "require_once");
        }
        
        if ($insert_pos !== false) {
            $content = substr_replace($content, $define_str, $insert_pos, 0);
        } else {
            $content .= $define_str;
        }
    }
    
    file_put_contents($wp_config_path, $content);
    
    // Manage crontab
    toggle_site_cronjob($site_path, $enable);
    
    return true;
}

/**
 * Toggle WP Auto Check Update status
 */
function toggle_wordpress_auto_update($site_path, $enable) {
    if (is_wordpress_locked($site_path)) {
        throw new Exception("Website is under WordPress Lockdown. Please disable Lockdown before modifying Auto Update status.");
    }
    
    $mu_dir = $site_path . '/wp-content/mu-plugins';
    $mu_plugin_file = $mu_dir . '/wp_disable_check_update.php';
    
    if ($enable) {
        // We want to ENABLE auto check update, which means REMOVING the disable file.
        if (file_exists($mu_plugin_file)) {
            @unlink($mu_plugin_file);
        }
    } else {
        // We want to DISABLE auto check update, which means CREATING/WRITING the disable file.
        if (!is_dir($mu_dir)) {
            mkdir($mu_dir, 0755, true);
        }
        
        $code = <<<'PHP'
<?php
/**
 * Plugin Name: Disable Automatic Update Checks
 * Plugin URI: https://github.com/tuend-work/ultimate-directadmin-wordpress-manager
 * Description: Optimizes server performance by disabling default automatic update checks for WordPress core, plugins, and themes, scheduling them to run monthly instead. Managed by Ultimate WordPress Manager.
 * Version: 1.0.0
 * Author: Ultimate WordPress Manager
 * Author URI: https://github.com/tuend-work/ultimate-directadmin-wordpress-manager
 * License: GPLv2 or later
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Filter transient update checks to return empty updates.
 */
function uwm_disable_update_checks($value) {
    global $wp_version;
    return (object) array(
        'last_checked'    => time(),
        'version_checked' => $wp_version,
        'updates'         => array()
    );
}
add_filter('pre_site_transient_update_core', 'uwm_disable_update_checks');
add_filter('pre_site_transient_update_plugins', 'uwm_disable_update_checks');
add_filter('pre_site_transient_update_themes', 'uwm_disable_update_checks');

// Disable automatic updater background checks and updates
if (!defined('AUTOMATIC_UPDATER_DISABLED')) {
    define('AUTOMATIC_UPDATER_DISABLED', true);
}

/**
 * Schedule manual update checks monthly instead of default frequencies.
 */
add_action('init', function() {
    if (!wp_next_scheduled('wp_custom_check_updates')) {
        wp_schedule_event(time(), 'monthly', 'wp_custom_check_updates');
    }
});

add_action('wp_custom_check_updates', function() {
    wp_update_plugins();
    wp_update_themes();
    wp_version_check();
});

/**
 * Disable automatic check update hooks in WordPress admin panel.
 */
add_action('admin_init', function() {
    // Disable maybe update checks
    remove_action('admin_init', '_maybe_update_core');
    remove_action('admin_init', '_maybe_update_plugins_and_themes');

    // Disable default background update cron actions
    remove_action('wp_version_check', 'wp_version_check');
    remove_action('wp_update_plugins', 'wp_update_plugins');
    remove_action('wp_update_themes', 'wp_update_themes');

    // Remove automatic check actions on core update screen
    remove_action('load-update-core.php', 'wp_update_plugins');
    remove_action('load-update-core.php', 'wp_update_themes');
    remove_action('load-update-core.php', 'wp_version_check');
    remove_action('init', 'wp_schedule_update_checks');
    
    // Remove automatic checks on plugins and themes lists
    remove_action('load-plugins.php', 'wp_update_plugins');
    remove_action('load-themes.php', 'wp_update_themes');
});
PHP;
        file_put_contents($mu_plugin_file, $code);
        @chmod($mu_plugin_file, 0644);
    }
    
}

/**
 * Toggle WP DEBUG status (WP_DEBUG, WP_DEBUG_LOG, WP_DEBUG_DISPLAY)
 */
function toggle_wordpress_debug($site_path, $enable) {
    if (is_wordpress_locked($site_path)) {
        throw new Exception("Website is under WordPress Lockdown. Please disable Lockdown before modifying WP Debug status.");
    }
    
    $wp_config_path = $site_path . '/wp-config.php';
    if (!file_exists($wp_config_path)) {
        throw new Exception("Safety block: wp-config.php not found.");
    }
    
    check_wp_config_writable($wp_config_path);
    
    // Modify debug constants
    wp_manager_config_define_modify($wp_config_path, 'WP_DEBUG', (bool)$enable, true);
    wp_manager_config_define_modify($wp_config_path, 'WP_DEBUG_LOG', (bool)$enable, true);
    wp_manager_config_define_modify($wp_config_path, 'WP_DEBUG_DISPLAY', (bool)$enable, true);
    
    return true;
}

/**
 * Self Update Admin functionality
 */
function update_plugin_from_github() {
    if (!is_admin_user()) {
        throw new Exception("Forbidden: Updates are restricted to Administrators.");
    }

    $plugin_dir = '/usr/local/directadmin/plugins/ultimate-directadmin-wordpress-manager';
    $wrapper    = $plugin_dir . '/scripts/update_wrapper';
    if (!file_exists($wrapper) || !is_executable($wrapper)) {
        $wrapper = $plugin_dir . '/scripts/wrapper';
    }
    $is_win     = strtoupper(substr(PHP_OS, 0, 3)) === 'WIN';

    if ($is_win) {
        $plugin_dir = 'f:/ultimate-directadmin-wordpress-manager';
        $wrapper    = null; // wrapper not available on Windows dev
    }

    if (!is_dir($plugin_dir)) {
        throw new Exception("Plugin directory not found: {$plugin_dir}");
    }

    // ── Strategy 1: dùng SUID wrapper (chạy với quyền root) ──
    if (!$is_win && file_exists($wrapper) && is_executable($wrapper)) {

        // Kiểm tra wrapper có hỗ trợ action 'update' không (tránh lỗi với bản cũ)
        $probe = shell_exec(escapeshellcmd($wrapper) . ' 2>&1');
        if ($probe !== null && strpos($wrapper, 'update_wrapper') === false && strpos($probe, 'update') === false) {
            // Wrapper cũ — chưa được recompile với action 'update'
            throw new Exception(
                "Wrapper binary is outdated and does not support 'update' action.\n\n" .
                "Please run the following commands on the server as root (one-time setup):\n\n" .
                "  cd {$plugin_dir}/scripts\n" .
                "  gcc -O2 wrapper.c -o wrapper\n" .
                "  chown root:diradmin wrapper\n" .
                "  chmod 4755 wrapper\n" .
                "  chmod 755 self_update.sh read_log.sh\n\n" .
                "After that, the Update Plugin button will work automatically."
            );
        }

        $output  = [];
        $retcode = 0;
        exec(escapeshellcmd($wrapper) . ' update 2>&1', $output, $retcode);
        $out_str = implode("\n", $output);

        if ($retcode !== 0) {
            throw new Exception("Wrapper update failed (exit {$retcode}):\n{$out_str}");
        }

        // Clear PHP opcode cache
        if (function_exists('opcache_reset')) {
            opcache_reset();
        }

        return [
            'success' => true,
            'message' => "Plugin updated via system wrapper successfully.\n" . $out_str
        ];
    }

    // ── Strategy 2 (Windows dev / fallback): copy trực tiếp nếu có quyền ghi ──
    if (!is_writable($plugin_dir)) {
        $owner = $proc = '?';
        if (function_exists('posix_getpwuid') && function_exists('posix_geteuid')) {
            $o = posix_getpwuid(fileowner($plugin_dir)); $owner = $o['name'] ?? '?';
            $p = posix_getpwuid(posix_geteuid());        $proc  = $p['name'] ?? '?';
        }
        $wrapper_path = $plugin_dir . '/scripts/wrapper';
        throw new Exception(
            "Permission denied: cannot write to plugin directory.\n" .
            "Dir owner: {$owner} | Process user: {$proc}\n\n" .
            "The SUID wrapper was not found or not executable.\n" .
            "On the server, run as root:\n" .
            "  cd {$plugin_dir}/scripts\n" .
            "  gcc -O2 wrapper.c -o wrapper\n" .
            "  chown root:diradmin wrapper\n" .
            "  chmod 4755 wrapper\n" .
            "  chmod 755 self_update.sh read_log.sh"
        );
    }

    // ── Download ZIP from GitHub ──
    $temp_zip = sys_get_temp_dir() . '/plugin_update_' . time() . '.zip';
    $github_zip_url = 'https://github.com/tuend-work/ultimate-directadmin-wordpress-manager/archive/refs/heads/main.zip';

    $ch = curl_init($github_zip_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 120);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 30);
    curl_setopt($ch, CURLOPT_USERAGENT, 'DirectAdmin-WordPress-Manager-Updater');
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    $data      = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_err  = curl_error($ch);
    curl_close($ch);

    if ($curl_err) {
        throw new Exception("GitHub connection failed: {$curl_err}");
    }
    if ($http_code !== 200 || !$data) {
        throw new Exception("Download failed from GitHub (HTTP {$http_code}).");
    }
    file_put_contents($temp_zip, $data);

    // ── Extract ZIP ──
    $zip = new ZipArchive;
    if ($zip->open($temp_zip) !== TRUE) {
        @unlink($temp_zip);
        throw new Exception("ZIP archive could not be opened/extracted.");
    }

    $extract_temp = sys_get_temp_dir() . '/plugin_extract_' . time();
    mkdir($extract_temp, 0755, true);
    $zip->extractTo($extract_temp);
    $zip->close();

    $src_dir = $extract_temp . '/ultimate-directadmin-wordpress-manager-main';
    if (!is_dir($src_dir)) {
        $dirs = array_diff(scandir($extract_temp), ['.', '..']);
        if (!empty($dirs)) {
            $src_dir = $extract_temp . '/' . reset($dirs);
        }
    }
    if (!is_dir($src_dir)) {
        rmdir_recursive($extract_temp);
        @unlink($temp_zip);
        throw new Exception("Unexpected ZIP structure — source directory not found.");
    }

    // ── Copy files with per-file error checking ──
    $copied = 0;
    $failed = [];
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($src_dir, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );
    foreach ($iterator as $item) {
        $subPath = $iterator->getSubPathName();
        $target  = $plugin_dir . '/' . $subPath;
        if ($item->isDir()) {
            if (!is_dir($target) && !mkdir($target, 0755, true)) {
                $failed[] = "mkdir:{$subPath}";
            }
        } else {
            $parent = dirname($target);
            if (!is_dir($parent)) {
                mkdir($parent, 0755, true);
            }
            if (!is_writable(is_dir($parent) ? $parent : $plugin_dir)) {
                $failed[] = "no-perm:{$subPath}";
                continue;
            }
            if (copy($item->getPathname(), $target)) {
                $copied++;
            } else {
                $err = error_get_last();
                $failed[] = "copy-fail:{$subPath}" . ($err ? " [{$err['message']}]" : '');
            }
        }
    }

    // Cleanup temp
    rmdir_recursive($extract_temp);
    @unlink($temp_zip);

    if (!empty($failed)) {
        $fail_count = count($failed);
        $sample     = implode('; ', array_slice($failed, 0, 5));
        throw new Exception(
            "Update incomplete: {$copied} files copied, {$fail_count} failed.\n" .
            "Failures: {$sample}\n" .
            "Fix permissions: chown -R <da_user> {$plugin_dir} && chmod -R 755 {$plugin_dir}"
        );
    }

    // Reset permissions
    set_permissions_recursive($plugin_dir);

    // Clear PHP opcode cache
    if (function_exists('opcache_reset')) {
        opcache_reset();
    }

    return [
        'success' => true,
        'message' => "Ultimate WordPress Manager updated successfully from GitHub. ({$copied} files replaced)"
    ];
}

/**
 * Handle GUI rendering
 */
function run_gui() {
    // Clear any warnings/notices captured in output buffer before rendering GUI
    if (ob_get_length()) {
        ob_clean();
    }
    
    $plugin_dir = '/usr/local/directadmin/plugins/ultimate-directadmin-wordpress-manager';
    // LOCAL DEVELOPMENT / WIN FALLBACK
    if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
        $plugin_dir = 'f:/ultimate-directadmin-wordpress-manager';
    }
    
    require_once $plugin_dir . '/gui.php';
}

/**
 * Helper to read reverse lines from a file using PHP fallback
 */
function get_file_last_lines_php($filepath, $num_lines) {
    if (!file_exists($filepath) || !is_readable($filepath)) {
        return '';
    }
    $fp = fopen($filepath, 'rb');
    if (!$fp) return '';
    
    $lines = [];
    $buffer_size = 4096;
    fseek($fp, 0, SEEK_END);
    $pos = ftell($fp);
    $chunk = '';
    
    while ($pos > 0 && count($lines) <= $num_lines) {
        $read_size = min($pos, $buffer_size);
        $pos -= $read_size;
        fseek($fp, $pos, SEEK_SET);
        $chunk = fread($fp, $read_size) . $chunk;
        
        $lines = explode("\n", $chunk);
    }
    fclose($fp);
    
    if (count($lines) > $num_lines) {
        $lines = array_slice($lines, -$num_lines);
    }
    return implode("\n", $lines);
}

/**
 * Helper to read N last lines of a file
 */
function get_file_last_lines($filepath, $num_lines) {
    if (!file_exists($filepath) || !is_readable($filepath)) {
        return '';
    }
    $num_lines = max(1, intval($num_lines));
    
    if (strtoupper(substr(PHP_OS, 0, 3)) !== 'WIN') {
        $cmd = 'tail -n ' . $num_lines . ' ' . escapeshellarg($filepath);
        $output = [];
        $retval = 0;
        @exec($cmd, $output, $retval);
        if ($retval === 0) {
            return implode("\n", $output);
        }
    }
    
    return get_file_last_lines_php($filepath, $num_lines);
}

/**
 * Parse time from log line
 */
function parse_log_line_time($line) {
    // [21-Jun-2026 01:16:02 UTC] / [21-Jun-2026 01:16:02]
    if (preg_match('/^\[(\d{1,2}-[a-zA-Z]{3}-\d{4} \d{2}:\d{2}:\d{2}(?:\s+[a-zA-Z0-9\/+-]+)?)\]/', $line, $matches)) {
        $t = strtotime($matches[1]);
        if ($t !== false) return $t;
    }
    
    // Nginx access: [21/Jun/2026:01:13:00 +0000]
    if (preg_match('/\[(\d{1,2}\/[a-zA-Z]{3}\/\d{4}:\d{2}:\d{2}:\d{2}\s+[+-]\d{4})\]/', $line, $matches)) {
        $date_str = preg_replace('/^(\d{1,2}\/[a-zA-Z]{3}\/\d{4}):/', '$1 ', $matches[1]);
        $t = strtotime(str_replace('/', '-', $date_str));
        if ($t !== false) return $t;
    }
    
    // Nginx error: 2026/06/21 01:13:00
    if (preg_match('/^(\d{4}\/\d{2}\/\d{2}\s+\d{2}:\d{2}:\d{2})/', $line, $matches)) {
        $t = strtotime(str_replace('/', '-', $matches[1]));
        if ($t !== false) return $t;
    }
    
    // [yyyy-mm-dd hh:mm:ss]
    if (preg_match('/^\[(\d{4}-\d{2}-\d{2}\s+\d{2}:\d{2}:\d{2})\]/', $line, $matches)) {
        $t = strtotime($matches[1]);
        if ($t !== false) return $t;
    }
    
    return null;
}

/**
 * Check if log line is within N seconds
 */
function log_line_matches_time($line, $seconds) {
    if ($seconds <= 0) return true;
    $line_time = parse_log_line_time($line);
    if ($line_time === null) return true;
    return (time() - $line_time) <= $seconds;
}

/**
 * Check if log line extension is allowed
 */
function log_line_matches_file_types($line, $enabled_types) {
    if (empty($enabled_types)) return true;
    
    if (preg_match('/"([A-Z]+)\s+([^\s?]+)(?:\?[^\s]*)?\s+HTTP/', $line, $matches)) {
        $url_path = $matches[2];
        $ext = strtolower(pathinfo($url_path, PATHINFO_EXTENSION));
    } else {
        return true;
    }
    
    $matched_types = [];
    if ($ext === 'php') {
        $matched_types[] = 'php';
        $matched_types[] = 'php_backend';
    } elseif ($ext === '') {
        $matched_types[] = 'php_backend';
        $matched_types[] = 'other';
    } elseif ($ext === 'html' || $ext === 'htm') {
        $matched_types[] = 'html';
    } elseif ($ext === 'css') {
        $matched_types[] = 'css';
    } elseif ($ext === 'js') {
        $matched_types[] = 'js';
    } elseif (in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'svg', 'webp', 'ico'])) {
        $matched_types[] = 'image';
    } elseif (in_array($ext, ['woff', 'woff2', 'ttf', 'otf', 'eot'])) {
        $matched_types[] = 'font';
    } else {
        $matched_types[] = 'other';
    }
    
    foreach ($matched_types as $t) {
        if (in_array($t, $enabled_types)) {
            return true;
        }
    }
    return false;
}

/**
 * Helper to scan and find PHP error log path
 */
function find_php_error_log_path($site_path, $domain, $username) {
    $home = "/home/{$username}";
    if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
        $home = 'C:/Users/local_user';
    }
    
    $path = rtrim($site_path, '/') . '/error_log';
    if (file_exists($path)) {
        return $path;
    }
    
    if ($domain) {
        $path = $home . '/domains/' . $domain . '/logs/php_error.log';
        if (file_exists($path)) return $path;
        
        $path = $home . '/domains/' . $domain . '/logs/error_log';
        if (file_exists($path)) return $path;
        
        $path = $home . '/domains/' . $domain . '/public_html/error_log';
        if (file_exists($path)) return $path;
    }
    
    $path = $home . '/logs/php_error.log';
    if (file_exists($path)) return $path;
    
    $path = $home . '/logs/error_log';
    if (file_exists($path)) return $path;
    
    return rtrim($site_path, '/') . '/error_log';
}

function resolve_site_domain_for_logs($site_path, $home) {
    $site_path_real = realpath($site_path) ?: $site_path;
    $site_path_real = str_replace('\\', '/', $site_path_real);
    $home = rtrim(str_replace('\\', '/', $home), '/');

    // DirectAdmin log names are based on the configured domain path, not always WP siteurl.
    // Prefer /home/{user}/domains/{domain} so a siteurl like www.domain.com still reads domain.com logs.
    $domains_prefix = $home . '/domains/';
    if (strpos($site_path_real, $domains_prefix) === 0) {
        $relative = substr($site_path_real, strlen($domains_prefix));
        $parts = explode('/', trim($relative, '/'));
        if (!empty($parts[0])) {
            $parent_domain = $parts[0];
            $subdomains_index = array_search('subdomains', $parts, true);
            if ($subdomains_index !== false && !empty($parts[$subdomains_index + 1])) {
                return $parts[$subdomains_index + 1] . '.' . $parent_domain;
            }

            $public_html_index = array_search('public_html', $parts, true);
            if ($public_html_index !== false && !empty($parts[$public_html_index + 1])) {
                $first_subdir = $parts[$public_html_index + 1];
                $sub_list_file = $home . '/domains/' . $parent_domain . '/subdomains.list';
                if (file_exists($sub_list_file)) {
                    $subdomains = file($sub_list_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
                    if (in_array($first_subdir, array_map('trim', $subdomains), true)) {
                        return $first_subdir . '.' . $parent_domain;
                    }
                }
                if (is_dir($home . '/domains/' . $parent_domain . '/subdomains/' . $first_subdir)) {
                    return $first_subdir . '.' . $parent_domain;
                }
            }

            return $parent_domain;
        }
    }

    $wp_config = rtrim($site_path_real, '/') . '/wp-config.php';
    if (file_exists($wp_config)) {
        $info = parse_wp_config($wp_config);
        if (!empty($info['domain'])) {
            return preg_replace('/^www\./i', '', $info['domain']);
        }
    }

    return '';
}
/**
 * Handle API Endpoint actions
 */
function run_api() {
    // Suppress display_errors inside API to prevent any PHP warning from corrupting JSON output
    ini_set('display_errors', 0);

    // Clear any warnings/notices captured in output buffer to keep CGI headers clean
    while (ob_get_level() > 0) {
        ob_end_clean();
    }

    // Standard CGI headers for DirectAdmin raw mode (HTTP/1.1 is standard)
    echo "HTTP/1.1 200 OK\r\n";
    echo "Content-Type: application/json; charset=utf-8\r\n";
    echo "Access-Control-Allow-Origin: *\r\n";
    echo "\r\n";

    // Start a fresh output buffer so that any stray output during action handling
    // is captured and discarded — only the explicit json_encode() will be echoed.
    ob_start();
    
    $username = getenv('USERNAME') ?: getenv('USER') ?: 'nobody';
    $home = getenv('HOME') ?: "/home/{$username}";
    if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
        $username = 'local_user';
        $home = 'C:/Users/local_user';
    }
    
    // Override target user if executing user is administrator
    $target_user_input = $_GET['target_user'] ?? $_POST['target_user'] ?? '';
    if (is_admin_user() && !empty($target_user_input)) {
        $username = preg_replace('/[^a-zA-Z0-9_-]/', '', $target_user_input);
        $home = "/home/{$username}";
        if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
            $home = 'C:/Users/' . $username;
        }
    }
    
    $action = $_GET['action'] ?? $_POST['action'] ?? '';
    
    // Delegation logic for admin impersonation
    $current_exec_user = getenv('USERNAME') ?: getenv('USER') ?: 'nobody';
    $is_win = strtoupper(substr(PHP_OS, 0, 3)) === 'WIN';
    if (!$is_win && is_admin_user() && !empty($target_user_input) && $target_user_input !== $current_exec_user && $action !== 'get_users' && $action !== 'update_plugin' && $action !== 'create_database') {
        $target_user_clean = preg_replace('/[^a-zA-Z0-9_-]/', '', $target_user_input);
        $wrapper = '/usr/local/directadmin/plugins/ultimate-directadmin-wordpress-manager/scripts/wrapper';
        if (!file_exists($wrapper)) {
            $wrapper = dirname(__FILE__) . '/scripts/wrapper';
        }
        
        if (file_exists($wrapper)) {
            $target_home = "/home/{$target_user_clean}";
            $env_prefix = sprintf(
                'USERNAME=%s USER=%s HOME=%s QUERY_STRING=%s POST=%s ',
                escapeshellarg($target_user_clean),
                escapeshellarg($target_user_clean),
                escapeshellarg($target_home),
                escapeshellarg(getenv('QUERY_STRING') ?: ''),
                escapeshellarg(getenv('POST') ?: '')
            );
            
            // Check if wrapper supports the run_as command directly (meaning compilation was successful)
            $supports_run_as = false;
            $test_cmd = escapeshellarg($wrapper) . " run_as 2>&1";
            $test_out = [];
            exec($test_cmd, $test_out);
            $test_str = implode("\n", $test_out);
            if (strpos($test_str, 'Usage:') !== false) {
                $supports_run_as = true;
            }
            
            if ($supports_run_as) {
                // If compiled wrapper supports run_as, run the php script directly with correct user context
                $exec_user = ($action === 'clone') ? 'root' : $target_user_clean;
                $cmd = $env_prefix . escapeshellarg($wrapper) . " run_as " . escapeshellarg($exec_user) . " /usr/local/bin/php -nc /usr/local/directadmin/plugins/ultimate-directadmin-wordpress-manager/php.ini /usr/local/directadmin/plugins/ultimate-directadmin-wordpress-manager/user/index.raw 2>&1";
            } else {
                // Fallback: Execute the user panel raw entry point as the target user using SUID read_log bypass.
                // If action is clone, we run as root (run-as-root) to bypass cross-user file read boundaries.
                $prefix = ($action === 'clone') ? 'run-as-root' : 'run-as';
                $cmd = $env_prefix . escapeshellarg($wrapper) . " read_log " . escapeshellarg($target_user_clean) . " " . escapeshellarg("{$prefix}.{$target_user_clean}") . " access 100 2>&1";
            }
            
            $output = [];
            $retval = null;
            exec($cmd, $output, $retval);
            
            // Clean output buffering to prevent header errors
            while (ob_get_level() > 0) {
                ob_end_clean();
            }
            
            // Find the JSON response starting from the output (skip shebang or warnings if any)
            $output_str = implode("\n", $output);
            $json_start = strpos($output_str, '{');
            if ($json_start !== false) {
                echo substr($output_str, $json_start);
            } else {
                echo json_encode([
                    'success' => false,
                    'error' => 'Lỗi thực thi ủy quyền (Delegation failed): ' . $output_str
                ]);
            }
            exit;
        }
    }
    
    try {
        switch ($action) {
            case 'get_users':
                if (!is_admin_user()) {
                    throw new Exception("Forbidden: Access restricted to Administrators.");
                }
                $users = get_all_directadmin_users();
                echo json_encode(['success' => true, 'users' => $users]);
                break;
                
            case 'create_database':
                $dbname = isset($_POST['dbname']) ? $_POST['dbname'] : '';
                $dbuser = isset($_POST['dbuser']) ? $_POST['dbuser'] : '';
                $dbpass = isset($_POST['dbpass']) ? $_POST['dbpass'] : '';
                $target_user_input = isset($_POST['target_user']) ? $_POST['target_user'] : '';
                
                if (empty($dbname) || empty($dbuser) || empty($dbpass)) {
                    throw new Exception("Missing required database parameters.");
                }
                
                $target_user = $username;
                if (is_admin_user() && !empty($target_user_input)) {
                    $target_user = $target_user_input;
                }
                
                $result = create_directadmin_database($dbname, $dbuser, $dbpass, $target_user);
                $prefix = $target_user;
                
                echo json_encode([
                    'success' => true,
                    'db_name' => isset($result['db']) ? $result['db'] : ($prefix . '_' . $dbname),
                    'db_user' => isset($result['user']) ? $result['user'] : ($prefix . '_' . $dbuser)
                ]);
                break;
                
            case 'get_domains':
                $domains_dir = $home . '/domains';
                $domains = [];
                if (is_dir($domains_dir)) {
                    $dirs = array_diff(scandir($domains_dir), ['.', '..']);
                    foreach ($dirs as $dir) {
                        if (is_dir($domains_dir . '/' . $dir . '/public_html')) {
                            $domains[] = $dir;
                        }
                    }
                }
                echo json_encode(['success' => true, 'domains' => $domains]);
                break;
                
            case 'scan':
                $sites = scan_wordpress_installations($home, $username);
                echo json_encode(['success' => true, 'sites' => $sites]);
                break;
                
            case 'list':
                $cache_file = $home . '/.ultimate_wp_manager.json';
                if (file_exists($cache_file)) {
                    $sites = json_decode(file_get_contents($cache_file), true);
                } else {
                    $sites = scan_wordpress_installations($home, $username);
                }
                echo json_encode(['success' => true, 'sites' => $sites]);
                break;
                
            case 'install':
                wp_manager_log("Install API triggered. POST: " . json_encode($_POST) . " | CONTENT_TYPE: " . ($_SERVER['CONTENT_TYPE'] ?? '') . " | CONTENT_LENGTH: " . ($_SERVER['CONTENT_LENGTH'] ?? ''));
                $mode = $_POST['mode'] ?? 'fresh';
                if ($mode === 'zip') {
                    $required = ['domain', 'db_name', 'db_user', 'db_pass', 'zip_path'];
                    foreach ($required as $field) {
                        if (empty($_POST[$field])) {
                            throw new Exception("Required parameter missing: {$field}");
                        }
                    }
                } else {
                    $required = ['domain', 'db_name', 'db_user', 'db_pass', 'site_title', 'admin_user', 'admin_pass', 'admin_email'];
                    foreach ($required as $field) {
                        if (empty($_POST[$field])) {
                            throw new Exception("Required parameter missing: {$field}");
                        }
                    }
                }
                wp_manager_log("Bắt đầu cài đặt WordPress cho tên miền: " . $_POST['domain'] . " (Database: " . $_POST['db_name'] . ")");
                $res = install_wordpress_instance($_POST, $home);
                if (isset($res['success']) && $res['success']) {
                    wp_manager_log("Cài đặt WordPress thành công cho tên miền: " . $_POST['domain']);
                } else {
                    wp_manager_log("Cài đặt WordPress thất bại cho tên miền: " . $_POST['domain'] . " | Lỗi: " . ($res['error'] ?? 'Không rõ lý do'));
                }
                echo json_encode($res);
                break;
            case 'clone':
                $required = ['src_path', 'domain', 'db_name', 'db_user', 'db_pass'];
                foreach ($required as $field) {
                    if (empty($_POST[$field])) {
                        throw new Exception("Required parameter missing: {$field}");
                    }
                }
                $src_real = realpath($_POST['src_path']) ?: $_POST['src_path'];
                $is_root = (function_exists('posix_getuid') && posix_getuid() === 0) || (getenv('USER') === 'root') || (getenv('USERNAME') === 'root');
                if (is_admin_user() || $is_root) {
                    $allowed_root = strtoupper(substr(PHP_OS, 0, 3)) === 'WIN' ? 'C:/Users' : '/home';
                    if (strpos($src_real, $allowed_root) !== 0) {
                        throw new Exception("Invalid source directory access.");
                    }
                } else {
                    if (strpos($src_real, $home) !== 0) {
                        throw new Exception("Invalid source directory access.");
                    }
                }
                wp_manager_log("Bắt đầu clone WordPress từ " . $_POST['src_path'] . " sang tên miền: " . $_POST['domain']);
                $res = clone_wordpress_instance($_POST, $home);
                if (isset($res['success']) && $res['success']) {
                    wp_manager_log("Clone WordPress thành công từ " . $_POST['src_path'] . " sang tên miền: " . $_POST['domain']);
                } else {
                    wp_manager_log("Clone WordPress thất bại từ " . $_POST['src_path'] . " sang tên miền: " . $_POST['domain']);
                }
                echo json_encode($res);
                break;
                
            case 'list_zips':
                $zips = list_user_zip_files($home);
                echo json_encode(['success' => true, 'zips' => $zips]);
                break;
                
            case 'magic_login':
                if (empty($_POST['path'])) {
                    throw new Exception("Missing site path parameter.");
                }
                wp_manager_log("Tạo Magic Login cho website tại: " . $_POST['path']);
                $res = generate_magic_login($_POST['path'], $home);
                if (isset($res['success']) && $res['success']) {
                    wp_manager_log("Tạo Magic Login thành công cho website tại: " . $_POST['path']);
                } else {
                    wp_manager_log("Tạo Magic Login thất bại cho website tại: " . $_POST['path'] . " | Lỗi: " . ($res['error'] ?? 'Không rõ lý do'));
                }
                echo json_encode($res);
                break;
                
            case 'delete':
                if (empty($_POST['path'])) {
                    throw new Exception("Missing site path parameter.");
                }
                wp_manager_log("Xóa cài đặt WordPress tại đường dẫn: " . $_POST['path']);
                $res = delete_wordpress_instance($_POST['path'], $home);
                if (isset($res['success']) && $res['success']) {
                    wp_manager_log("Xóa WordPress thành công tại: " . $_POST['path']);
                } else {
                    wp_manager_log("Xóa WordPress thất bại tại: " . $_POST['path']);
                }
                echo json_encode($res);
                break;
                
            case 'lock':
                if (empty($_POST['path'])) {
                    throw new Exception("Missing site path parameter.");
                }
                if (strpos(realpath($_POST['path']) ?: $_POST['path'], $home) !== 0) {
                    throw new Exception("Invalid directory access.");
                }
                wp_manager_log("Khóa bảo vệ WordPress (Lockdown) tại: " . $_POST['path']);
                $res = lock_wordpress_instance($_POST['path']);
                if (isset($res['success']) && $res['success']) {
                    wp_manager_log("Khóa bảo vệ thành công tại: " . $_POST['path']);
                } else {
                    wp_manager_log("Khóa bảo vệ thất bại tại: " . $_POST['path']);
                }
                $cache_file = $home . '/.ultimate_wp_manager.json';
                if (file_exists($cache_file)) {
                    @unlink($cache_file);
                }
                echo json_encode($res);
                break;
                
            case 'unlock':
                if (empty($_POST['path'])) {
                    throw new Exception("Missing site path parameter.");
                }
                if (strpos(realpath($_POST['path']) ?: $_POST['path'], $home) !== 0) {
                    throw new Exception("Invalid directory access.");
                }
                wp_manager_log("Mở khóa bảo vệ WordPress tại: " . $_POST['path']);
                $res = unlock_wordpress_instance($_POST['path']);
                if (isset($res['success']) && $res['success']) {
                    wp_manager_log("Mở khóa bảo vệ thành công tại: " . $_POST['path']);
                } else {
                    wp_manager_log("Mở khóa bảo vệ thất bại tại: " . $_POST['path']);
                }
                $cache_file = $home . '/.ultimate_wp_manager.json';
                if (file_exists($cache_file)) {
                    @unlink($cache_file);
                }
                echo json_encode($res);
                break;

            case 'list_plugins':
                if (empty($_POST['path'])) {
                    throw new Exception("Missing site path parameter.");
                }
                if (strpos(realpath($_POST['path']) ?: $_POST['path'], $home) !== 0) {
                    throw new Exception("Invalid directory access.");
                }
                $plugins = list_plugins($_POST['path']);
                $active = get_active_plugins($_POST['path']);
                $updates = get_wordpress_plugin_update_info($_POST['path']);
                
                $response_plugins = [];
                foreach ($plugins as $file => $details) {
                    $update_info = $updates[$file] ?? [];
                    $details['file'] = $file;
                    $details['active'] = in_array($file, $active);
                    $details['latest_version'] = $update_info['latest_version'] ?? $details['version'];
                    $details['update_available'] = $update_info['update_available'] ?? false;
                    $details['update_package_available'] = $update_info['update_package_available'] ?? false;
                    $response_plugins[] = $details;
                }
                usort($response_plugins, function($a, $b) {
                    return strcasecmp($a['name'] ?? '', $b['name'] ?? '');
                });
                echo json_encode(['success' => true, 'plugins' => $response_plugins]);
                break;

            case 'toggle_plugin':
                if (empty($_POST['path']) || empty($_POST['plugin_file']) || empty($_POST['status'])) {
                    throw new Exception("Missing parameters.");
                }
                if (strpos(realpath($_POST['path']) ?: $_POST['path'], $home) !== 0) {
                    throw new Exception("Invalid directory access.");
                }
                wp_manager_log("Thay đổi trạng thái plugin '" . $_POST['plugin_file'] . "' cho website " . $_POST['path'] . " -> " . $_POST['status']);
                toggle_plugin_status($_POST['path'], $_POST['plugin_file'], $_POST['status']);
                wp_manager_log("Thay đổi trạng thái plugin thành công.");
                echo json_encode(['success' => true, 'message' => 'Plugin status updated successfully.']);
                break;

            case 'update_wp_plugin':
                if (empty($_POST['path']) || empty($_POST['plugin_file'])) {
                    throw new Exception("Missing parameters.");
                }
                if (strpos(realpath($_POST['path']) ?: $_POST['path'], $home) !== 0) {
                    throw new Exception("Invalid directory access.");
                }
                wp_manager_log("Cập nhật plugin '" . $_POST['plugin_file'] . "' cho website: " . $_POST['path']);
                $res = update_wordpress_plugin($_POST['path'], $_POST['plugin_file']);
                if (isset($res['success']) && $res['success']) {
                    wp_manager_log("Cập nhật plugin thành công.");
                    $plugin_path = $_POST['path'] . '/wp-content/plugins/' . $_POST['plugin_file'];
                    $details = get_plugin_details($plugin_path);
                    if ($details) {
                        $details['file'] = $_POST['plugin_file'];
                        $active = get_active_plugins($_POST['path']);
                        $details['active'] = in_array($_POST['plugin_file'], $active);
                        $details['update_available'] = false;
                        $details['latest_version'] = $details['version'];
                        $res['plugin'] = $details;
                    }
                } else {
                    wp_manager_log("Cập nhật plugin thất bại | Lỗi: " . ($res['error'] ?? 'Không rõ lý do'));
                }
                echo json_encode($res);
                break;
                
            case 'update_all_wp_plugins':
                if (empty($_POST['path'])) {
                    throw new Exception("Missing site path parameter.");
                }
                if (strpos(realpath($_POST['path']) ?: $_POST['path'], $home) !== 0) {
                    throw new Exception("Invalid directory access.");
                }
                wp_manager_log("Cập nhật tất cả plugins cho website: " . $_POST['path']);
                $res = update_all_wordpress_plugins($_POST['path']);
                if (isset($res['success']) && $res['success']) {
                    wp_manager_log("Cập nhật tất cả plugins thành công.");
                } else {
                    wp_manager_log("Cập nhật tất cả plugins thất bại | Lỗi: " . ($res['error'] ?? 'Không rõ lý do'));
                }
                echo json_encode($res);
                break;
                
            case 'reinstall_wp_plugin':
                if (empty($_POST['path']) || empty($_POST['plugin_file'])) {
                    throw new Exception("Missing parameters.");
                }
                if (strpos(realpath($_POST['path']) ?: $_POST['path'], $home) !== 0) {
                    throw new Exception("Invalid directory access.");
                }
                wp_manager_log("Cài đặt lại plugin '" . $_POST['plugin_file'] . "' cho website: " . $_POST['path']);
                $res = reinstall_wordpress_plugin($_POST['path'], $_POST['plugin_file']);
                if (isset($res['success']) && $res['success']) {
                    wp_manager_log("Cài đặt lại plugin thành công.");
                    $plugin_path = $_POST['path'] . '/wp-content/plugins/' . $_POST['plugin_file'];
                    $details = get_plugin_details($plugin_path);
                    if ($details) {
                        $details['file'] = $_POST['plugin_file'];
                        $active = get_active_plugins($_POST['path']);
                        $details['active'] = in_array($_POST['plugin_file'], $active);
                        $details['update_available'] = false;
                        $details['latest_version'] = $details['version'];
                        $res['plugin'] = $details;
                    }
                } else {
                    wp_manager_log("Cài đặt lại plugin thất bại | Lỗi: " . ($res['error'] ?? 'Không rõ lý do'));
                }
                echo json_encode($res);
                break;
                
            case 'delete_wp_plugin':
                if (empty($_POST['path']) || empty($_POST['plugin_file'])) {
                    throw new Exception("Missing parameters.");
                }
                if (strpos(realpath($_POST['path']) ?: $_POST['path'], $home) !== 0) {
                    throw new Exception("Invalid directory access.");
                }
                wp_manager_log("Xóa plugin '" . $_POST['plugin_file'] . "' khỏi website: " . $_POST['path']);
                $res = delete_wordpress_plugin($_POST['path'], $_POST['plugin_file']);
                if (isset($res['success']) && $res['success']) {
                    wp_manager_log("Xóa plugin thành công.");
                } else {
                    wp_manager_log("Xóa plugin thất bại | Lỗi: " . ($res['error'] ?? 'Không rõ lý do'));
                }
                echo json_encode($res);
                break;
                
            case 'list_themes':
                if (empty($_POST['path'])) {
                    throw new Exception("Missing site path parameter.");
                }
                if (strpos(realpath($_POST['path']) ?: $_POST['path'], $home) !== 0) {
                    throw new Exception("Invalid directory access.");
                }
                $themes = list_themes($_POST['path']);
                $active = get_active_theme($_POST['path']);
                $updates = get_wordpress_theme_update_info($_POST['path']);
                
                $response_themes = [];
                foreach ($themes as $theme) {
                    $update_info = $updates[$theme['folder']] ?? [];
                    $theme['active'] = ($theme['folder'] === $active);
                    $theme['latest_version'] = $update_info['latest_version'] ?? $theme['version'];
                    $theme['update_available'] = $update_info['update_available'] ?? false;
                    $theme['update_package_available'] = $update_info['update_package_available'] ?? false;
                    $response_themes[] = $theme;
                }
                usort($response_themes, function($a, $b) {
                    return strcasecmp($a['name'] ?? '', $b['name'] ?? '');
                });
                echo json_encode(['success' => true, 'themes' => $response_themes]);
                break;

            case 'activate_theme':
                if (empty($_POST['path']) || empty($_POST['theme_folder'])) {
                    throw new Exception("Missing parameters.");
                }
                if (strpos(realpath($_POST['path']) ?: $_POST['path'], $home) !== 0) {
                    throw new Exception("Invalid directory access.");
                }
                wp_manager_log("Kích hoạt theme '" . $_POST['theme_folder'] . "' cho website: " . $_POST['path']);
                activate_theme($_POST['path'], $_POST['theme_folder']);
                wp_manager_log("Kích hoạt theme thành công.");
                echo json_encode(['success' => true, 'message' => 'Theme activated successfully.']);
                break;

            case 'update_wp_theme':
                if (empty($_POST['path']) || empty($_POST['theme_folder'])) {
                    throw new Exception("Missing parameters.");
                }
                if (strpos(realpath($_POST['path']) ?: $_POST['path'], $home) !== 0) {
                    throw new Exception("Invalid directory access.");
                }
                wp_manager_log("Cập nhật theme '" . $_POST['theme_folder'] . "' cho website: " . $_POST['path']);
                $res = update_wordpress_theme($_POST['path'], $_POST['theme_folder']);
                if (isset($res['success']) && $res['success']) {
                    wp_manager_log("Cập nhật theme thành công.");
                    $theme_style_css = $_POST['path'] . '/wp-content/themes/' . $_POST['theme_folder'] . '/style.css';
                    $details = get_theme_details($theme_style_css);
                    if ($details) {
                        $details['folder'] = $_POST['theme_folder'];
                        $active_theme = get_active_theme($_POST['path']);
                        $details['active'] = ($details['folder'] === $active_theme);
                        $details['update_available'] = false;
                        $details['latest_version'] = $details['version'];
                        $res['theme'] = $details;
                    }
                } else {
                    wp_manager_log("Cập nhật theme thất bại | Lỗi: " . ($res['error'] ?? 'Không rõ lý do'));
                }
                echo json_encode($res);
                break;
                
            case 'update_all_wp_themes':
                if (empty($_POST['path'])) {
                    throw new Exception("Missing site path parameter.");
                }
                if (strpos(realpath($_POST['path']) ?: $_POST['path'], $home) !== 0) {
                    throw new Exception("Invalid directory access.");
                }
                wp_manager_log("Cập nhật tất cả themes cho website: " . $_POST['path']);
                $res = update_all_wordpress_themes($_POST['path']);
                if (isset($res['success']) && $res['success']) {
                    wp_manager_log("Cập nhật tất cả themes thành công.");
                } else {
                    wp_manager_log("Cập nhật tất cả themes thất bại | Lỗi: " . ($res['error'] ?? 'Không rõ lý do'));
                }
                echo json_encode($res);
                break;
                
            case 'reinstall_wp_theme':
                if (empty($_POST['path']) || empty($_POST['theme_folder'])) {
                    throw new Exception("Missing parameters.");
                }
                if (strpos(realpath($_POST['path']) ?: $_POST['path'], $home) !== 0) {
                    throw new Exception("Invalid directory access.");
                }
                wp_manager_log("Cài đặt lại theme '" . $_POST['theme_folder'] . "' cho website: " . $_POST['path']);
                $res = reinstall_wordpress_theme($_POST['path'], $_POST['theme_folder']);
                if (isset($res['success']) && $res['success']) {
                    wp_manager_log("Cài đặt lại theme thành công.");
                    $theme_style_css = $_POST['path'] . '/wp-content/themes/' . $_POST['theme_folder'] . '/style.css';
                    $details = get_theme_details($theme_style_css);
                    if ($details) {
                        $details['folder'] = $_POST['theme_folder'];
                        $active_theme = get_active_theme($_POST['path']);
                        $details['active'] = ($details['folder'] === $active_theme);
                        $details['update_available'] = false;
                        $details['latest_version'] = $details['version'];
                        $res['theme'] = $details;
                    }
                } else {
                    wp_manager_log("Cài đặt lại theme thất bại | Lỗi: " . ($res['error'] ?? 'Không rõ lý do'));
                }
                echo json_encode($res);
                break;
                
            case 'delete_wp_theme':
                if (empty($_POST['path']) || empty($_POST['theme_folder'])) {
                    throw new Exception("Missing parameters.");
                }
                if (strpos(realpath($_POST['path']) ?: $_POST['path'], $home) !== 0) {
                    throw new Exception("Invalid directory access.");
                }
                wp_manager_log("Xóa theme '" . $_POST['theme_folder'] . "' khỏi website: " . $_POST['path']);
                $res = delete_wordpress_theme($_POST['path'], $_POST['theme_folder']);
                if (isset($res['success']) && $res['success']) {
                    wp_manager_log("Xóa theme thành công.");
                } else {
                    wp_manager_log("Xóa theme thất bại | Lỗi: " . ($res['error'] ?? 'Không rõ lý do'));
                }
                echo json_encode($res);
                break;

            case 'list_users':
                if (empty($_POST['path'])) {
                    throw new Exception("Missing site path parameter.");
                }
                if (strpos(realpath($_POST['path']) ?: $_POST['path'], $home) !== 0) {
                    throw new Exception("Invalid directory access.");
                }
                $users = list_wordpress_users($_POST['path']);
                echo json_encode(['success' => true, 'users' => $users]);
                break;

            case 'change_user_password':
                if (empty($_POST['path']) || empty($_POST['user_id']) || !isset($_POST['password'])) {
                    throw new Exception("Missing parameters.");
                }
                if (strpos(realpath($_POST['path']) ?: $_POST['path'], $home) !== 0) {
                    throw new Exception("Invalid directory access.");
                }
                wp_manager_log("Thay đổi mật khẩu user ID '" . $_POST['user_id'] . "' cho website: " . $_POST['path']);
                $res = change_wordpress_user_password($_POST['path'], $_POST['user_id'], $_POST['password']);
                wp_manager_log("Thay đổi mật khẩu user thành công.");
                echo json_encode($res);
                break;

            case 'create_user':
                if (empty($_POST['path']) || empty($_POST['username']) || empty($_POST['password']) || empty($_POST['email']) || empty($_POST['role'])) {
                    throw new Exception("Thiếu thông tin bắt buộc để tạo User.");
                }
                if (strpos(realpath($_POST['path']) ?: $_POST['path'], $home) !== 0) {
                    throw new Exception("Yêu cầu quyền truy cập thư mục hợp lệ.");
                }
                wp_manager_log("Tạo user '" . $_POST['username'] . "' với email '" . $_POST['email'] . "' cho website: " . $_POST['path']);
                $res = create_wordpress_user($_POST['path'], $_POST['username'], $_POST['password'], $_POST['email'], $_POST['role']);
                wp_manager_log("Tạo user thành công.");
                echo json_encode($res);
                break;

            case 'login_as_user':
                if (empty($_POST['path']) || empty($_POST['user_id'])) {
                    throw new Exception("Missing parameters.");
                }
                if (strpos(realpath($_POST['path']) ?: $_POST['path'], $home) !== 0) {
                    throw new Exception("Invalid directory access.");
                }
                if (!wordpress_user_exists($_POST['path'], $_POST['user_id'])) {
                    throw new Exception("User not found.");
                }
                wp_manager_log("Tạo Login As User cho user ID '" . $_POST['user_id'] . "' tại website: " . $_POST['path']);
                $res = generate_magic_login($_POST['path'], $home, (int)$_POST['user_id']);
                echo json_encode($res);
                break;
                
            case 'get_security_status':
                if (empty($_POST['path'])) {
                    throw new Exception("Missing site path parameter.");
                }
                if (strpos(realpath($_POST['path']) ?: $_POST['path'], $home) !== 0) {
                    throw new Exception("Invalid directory access.");
                }
                $status = get_wordpress_security_status($_POST['path']);
                echo json_encode(['success' => true, 'security' => $status]);
                break;

            case 'toggle_security':
                if (empty($_POST['path']) || empty($_POST['measure'])) {
                    throw new Exception("Missing parameters.");
                }
                if (strpos(realpath($_POST['path']) ?: $_POST['path'], $home) !== 0) {
                    throw new Exception("Invalid directory access.");
                }
                $enable = isset($_POST['enable']) && ($_POST['enable'] === 'true' || $_POST['enable'] === '1');
                wp_manager_log("Thay đổi cấu hình bảo mật '" . $_POST['measure'] . "' cho website: " . $_POST['path'] . " | Trạng thái: " . ($enable ? 'Bật' : 'Tắt'));
                toggle_wordpress_security_measure($_POST['path'], $_POST['measure'], $enable, $_POST);
                echo json_encode(['success' => true, 'message' => 'Security setting updated successfully.']);
                break;

            case 'update_core':
                if (empty($_POST['path'])) {
                    throw new Exception("Missing site path parameter.");
                }
                if (strpos(realpath($_POST['path']) ?: $_POST['path'], $home) !== 0) {
                    throw new Exception("Invalid directory access.");
                }
                wp_manager_log("Cập nhật WordPress Core cho website: " . $_POST['path']);
                $res = update_wordpress_core($_POST['path'], $home);
                if (isset($res['success']) && $res['success']) {
                    wp_manager_log("Cập nhật WordPress Core thành công.");
                } else {
                    wp_manager_log("Cập nhật WordPress Core thất bại | Lỗi: " . ($res['error'] ?? 'Không rõ lý do'));
                }
                echo json_encode($res);
                break;
                
            case 'update_plugin':
                wp_manager_log("Bắt đầu cập nhật plugin WordPress Manager từ GitHub.");
                $res = update_plugin_from_github();
                if (isset($res['success']) && $res['success']) {
                    wp_manager_log("Cập nhật plugin WordPress Manager thành công.");
                } else {
                    wp_manager_log("Cập nhật plugin WordPress Manager thất bại | Lỗi: " . ($res['error'] ?? 'Không rõ lý do'));
                }
                echo json_encode($res);
                break;
                
            case 'toggle_cron':
                if (empty($_POST['path'])) {
                    throw new Exception("Missing site path parameter.");
                }
                if (strpos(realpath($_POST['path']) ?: $_POST['path'], $home) !== 0) {
                    throw new Exception("Invalid directory access.");
                }
                $enable = isset($_POST['enable']) && ($_POST['enable'] === 'true' || $_POST['enable'] === '1');
                wp_manager_log("Thay đổi trạng thái WP Cron cho website: " . $_POST['path'] . " | Trạng thái: " . ($enable ? 'Tắt Cron mặc định (Bật)' : 'Sử dụng Cron mặc định (Tắt)'));
                toggle_wordpress_cron($_POST['path'], $enable);
                $cache_file = $home . '/.ultimate_wp_manager.json';
                if (file_exists($cache_file)) {
                    @unlink($cache_file);
                }
                echo json_encode(['success' => true, 'message' => 'Cập nhật trạng thái WP Cron thành công.']);
                break;
                
            case 'toggle_auto_update':
                if (empty($_POST['path'])) {
                    throw new Exception("Missing site path parameter.");
                }
                if (strpos(realpath($_POST['path']) ?: $_POST['path'], $home) !== 0) {
                    throw new Exception("Invalid directory access.");
                }
                $enable = isset($_POST['enable']) && ($_POST['enable'] === 'true' || $_POST['enable'] === '1');
                wp_manager_log("Thay đổi trạng thái Tự động cập nhật cho website: " . $_POST['path'] . " | Trạng thái: " . ($enable ? 'Cho phép cập nhật (Tắt chặn)' : 'Không cho phép cập nhật (Bật chặn)'));
                toggle_wordpress_auto_update($_POST['path'], $enable);
                $cache_file = $home . '/.ultimate_wp_manager.json';
                if (file_exists($cache_file)) {
                    @unlink($cache_file);
                }
                echo json_encode(['success' => true, 'message' => 'Cập nhật trạng thái tự động kiểm tra cập nhật thành công.']);
                break;

            case 'toggle_debug':
                if (empty($_POST['path'])) {
                    throw new Exception("Missing site path parameter.");
                }
                if (strpos(realpath($_POST['path']) ?: $_POST['path'], $home) !== 0) {
                    throw new Exception("Invalid directory access.");
                }
                $enable = isset($_POST['enable']) && ($_POST['enable'] === 'true' || $_POST['enable'] === '1');
                wp_manager_log("Thay đổi trạng thái WP Debug cho website: " . $_POST['path'] . " | Trạng thái: " . ($enable ? 'Bật' : 'Tắt'));
                toggle_wordpress_debug($_POST['path'], $enable);
                $cache_file = $home . '/.ultimate_wp_manager.json';
                if (file_exists($cache_file)) {
                    @unlink($cache_file);
                }
                echo json_encode(['success' => true, 'message' => 'Cập nhật trạng thái WP Debug thành công.']);
                break;

            case 'get_premium_list':
                $list = get_premium_list();
                echo json_encode(array_merge(['success' => true], $list));
                break;

            case 'install_premium_item':
                if (empty($_POST['path']) || empty($_POST['item_type']) || empty($_POST['item_source']) || (!isset($_POST['slug']) && !isset($_POST['file']))) {
                    throw new Exception("Thiếu tham số bắt buộc.");
                }
                if (strpos(realpath($_POST['path']) ?: $_POST['path'], $home) !== 0) {
                    throw new Exception("Yêu cầu quyền truy cập thư mục hợp lệ.");
                }
                if (is_wordpress_locked($_POST['path'])) {
                    throw new Exception("Website đang bị khóa (WP Lock). Vui lòng tắt WP Lock trước khi cài đặt.");
                }
                
                $site_path = $_POST['path'];
                $item_type = $_POST['item_type'];
                $item_source = $_POST['item_source'];
                
                $temp_zip = sys_get_temp_dir() . '/premium_install_' . time() . '.zip';
                $cleanup_zip = true;
                
                if ($item_source === 'wporg') {
                    $slug = $_POST['slug'];
                    $base_type = ($item_type === 'plugins') ? 'plugin' : 'theme';
                    $url = "https://downloads.wordpress.org/{$base_type}/{$slug}.zip";
                    
                    $ch = curl_init($url);
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
                    curl_setopt($ch, CURLOPT_TIMEOUT, 120);
                    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
                    $data = curl_exec($ch);
                    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                    $curl_err = curl_error($ch);
                    curl_close($ch);
                    
                    if ($http_code !== 200 || !$data) {
                        throw new Exception("Không thể tải xuống từ WordPress.org. HTTP Code: {$http_code} | cURL Error: {$curl_err}");
                    }
                    file_put_contents($temp_zip, $data);
                } else {
                    $filename = basename($_POST['file']);
                    $download_url = "https://ultimate-wordpress-manager.wpcloud.vn/index.php?action=download&file={$filename}";
                    
                    $ch = curl_init($download_url);
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
                    curl_setopt($ch, CURLOPT_TIMEOUT, 120);
                    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
                    $data = curl_exec($ch);
                    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                    $curl_err = curl_error($ch);
                    curl_close($ch);
                    
                    if ($http_code !== 200 || !$data) {
                        throw new Exception("Không thể tải xuống ZIP từ Store Web. HTTP Code: {$http_code} | cURL Error: {$curl_err}");
                    }
                    file_put_contents($temp_zip, $data);
                    $cleanup_zip = true;
                }
                
                $target_dir = $site_path . '/wp-content/' . $item_type;
                if (!is_dir($target_dir)) {
                    @mkdir($target_dir, 0755, true);
                }
                
                $zip = new ZipArchive;
                if ($zip->open($temp_zip) === TRUE) {
                    $first_entry = $zip->getNameIndex(0);
                    $folder_name = explode('/', $first_entry)[0];
                    
                    $zip->extractTo($target_dir);
                    $zip->close();
                    if ($cleanup_zip) {
                        @unlink($temp_zip);
                    }

                    // Rename GitHub's username-repo-commit folder style to the expected slug folder name
                    if ($item_source !== 'wporg') {
                        $expected_folder_name = '';
                        if ($item_type === 'plugins') {
                            $main_file = find_plugin_main_file($target_dir, $folder_name);
                            if ($main_file) {
                                $expected_folder_name = basename($main_file, '.php');
                            }
                        }
                        
                        if (empty($expected_folder_name)) {
                            // Fallback / Theme logic: clean the folder name from prefixes and suffixes
                            $expected_folder_name = $folder_name;
                            $expected_folder_name = preg_replace('/^\d+_/', '', $expected_folder_name);
                            $expected_folder_name = preg_replace('/-(main|master|heads|dev)$/i', '', $expected_folder_name);
                            $expected_folder_name = preg_replace('/-[a-f0-9]{7,40}$/i', '', $expected_folder_name);
                        }

                        if (!empty($expected_folder_name) && $folder_name !== $expected_folder_name) {
                            $old_path = $target_dir . '/' . $folder_name;
                            $new_path = $target_dir . '/' . $expected_folder_name;
                            if (is_dir($old_path)) {
                                if (is_dir($new_path)) {
                                    rmdir_recursive($new_path);
                                }
                                if (@rename($old_path, $new_path)) {
                                    $folder_name = $expected_folder_name;
                                }
                            }
                        }
                    }
                } else {
                    if ($cleanup_zip) {
                        @unlink($temp_zip);
                    }
                    throw new Exception("Không thể giải nén tệp tin cài đặt.");
                }
                
                $activated = false;
                
                if ($item_type === 'plugins') {
                    $main_file = find_plugin_main_file($target_dir, $folder_name);
                    if ($main_file) {
                        try {
                            $activated = toggle_plugin_status($site_path, $main_file, 'activate');
                        } catch (Throwable $e) {
                            wp_manager_log("Kích hoạt plugin thất bại: " . $e->getMessage());
                        }
                    }
                } else {
                    if (isset($_POST['activate']) && ($_POST['activate'] === 'true' || $_POST['activate'] === '1')) {
                        try {
                            $activated = activate_theme($site_path, $folder_name);
                        } catch (Throwable $e) {
                            wp_manager_log("Kích hoạt theme thất bại: " . $e->getMessage());
                        }
                    }
                }
                
                wp_manager_log("Cài đặt thành công mục Premium: " . ($item_type === 'plugins' ? 'Plugin' : 'Theme') . " {$folder_name}");
                echo json_encode([
                    'success' => true, 
                    'message' => 'Cài đặt mục Premium thành công.' . ($activated ? ' (Đã tự động kích hoạt)' : '')
                ]);
                break;

            case 'get_logs':
                $log_file = $home . '/.ultimate_wp_manager_debug.log';
                $log_content = file_exists($log_file) ? file_get_contents($log_file) : 'Chưa có nhật ký hoạt động nào.';
                echo json_encode(['success' => true, 'logs' => $log_content]);
                break;

            case 'get_logs_data':
                if (empty($_POST['path']) || empty($_POST['log_type'])) {
                    throw new Exception("Missing parameters.");
                }
                if (strpos(realpath($_POST['path']) ?: $_POST['path'], $home) !== 0) {
                    throw new Exception("Invalid directory access.");
                }
                
                $domain = resolve_site_domain_for_logs($_POST['path'], $home);
                if (empty($domain)) {
                    throw new Exception("Không thể xác định tên miền từ thư mục cài đặt.");
                }
                
                $log_type = $_POST['log_type'];
                $lines_to_read = max(intval($_POST['lines'] ?? 100) * 5, 1000);
                
                $log_content = '';
                $filepath = '';
                
                if ($log_type === 'access' || $log_type === 'error') {
                    // Đọc log trực tiếp hệ thống qua wrapper SUID root
                    $plugin_dir = '/usr/local/directadmin/plugins/ultimate-directadmin-wordpress-manager';
                    if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
                        $plugin_dir = 'f:/ultimate-directadmin-wordpress-manager';
                    }
                    $wrapper = $plugin_dir . '/scripts/wrapper';
                    
                    if (strtoupper(substr(PHP_OS, 0, 3)) !== 'WIN' && file_exists($wrapper) && is_executable($wrapper)) {
                        $cmd = escapeshellcmd($wrapper) . ' read_log ' . escapeshellarg($username) . ' ' . escapeshellarg($domain) . ' ' . escapeshellarg($log_type) . ' ' . escapeshellarg($lines_to_read);
                        $output = [];
                        $retval = 0;
                        @exec($cmd . ' 2>&1', $output, $retval);
                        if ($retval !== 0) {
                            wp_manager_log("Lỗi khi chạy wrapper read_log. Command: {$cmd} | Exit code: {$retval} | Output: " . implode("\n", $output));
                        }
                        $log_content = implode("\n", $output);
                    } else {
                        $log_content = "[Hệ thống] Trình xem log trực tiếp không khả dụng trên môi trường Windows phát triển.";
                    }
                    $filepath = "System $log_type log";
                } else {
                    // wp_debug hoặc php_error: đọc bình thường dưới quyền user
                    if ($log_type === 'wp_debug') {
                        $filepath = $_POST['path'] . '/wp-content/debug.log';
                    } elseif ($log_type === 'php_error') {
                        $filepath = find_php_error_log_path($_POST['path'], $domain, $username);
                    }
                    
                    if (empty($filepath) || !file_exists($filepath)) {
                        $log_content = "[Hệ thống] Tệp tin log chưa tồn tại hoặc chưa có dữ liệu ghi nhận.";
                    } else {
                        $log_content = get_file_last_lines($filepath, $lines_to_read);
                    }
                }
                
                if (preg_match('/^__WP_MANAGER_LOG_FILE__=(.+?)(\r?\n|$)/', $log_content, $log_path_match)) {
                    $filepath = trim($log_path_match[1]);
                    $log_content = preg_replace('/^__WP_MANAGER_LOG_FILE__=.+?(\r?\n|$)/', '', $log_content, 1);
                }

                $lines_arr = explode("\n", $log_content);
                $filtered_lines = [];
                $search = $_POST['search'] ?? '';
                $time_filter = intval($_POST['time_filter'] ?? 0);
                
                $file_types = [];
                if (isset($_POST['file_types'])) {
                    if (is_array($_POST['file_types'])) {
                        $file_types = $_POST['file_types'];
                    } else {
                        $file_types = json_decode($_POST['file_types'], true) ?: [];
                    }
                }
                
                foreach ($lines_arr as $line) {
                    $line_trimmed = trim($line);
                    if ($line_trimmed === '') continue;
                    
                    // Giữ nguyên các dòng thông báo hệ thống/lỗi của wrapper
                    if (strpos($line, '[Hệ thống]') !== false || strpos($line, 'Error:') === 0) {
                        $filtered_lines[] = $line;
                        continue;
                    }
                    
                    if ($search !== '' && stripos($line, $search) === false) {
                        continue;
                    }
                    
                    if ($time_filter > 0 && !log_line_matches_time($line, $time_filter)) {
                        continue;
                    }
                    
                    if ($log_type === 'access' && !empty($file_types)) {
                        if (!log_line_matches_file_types($line, $file_types)) {
                            continue;
                        }
                    }
                    
                    $filtered_lines[] = $line;
                }
                
                $target_lines_count = intval($_POST['lines'] ?? 100);
                if (count($filtered_lines) > $target_lines_count) {
                    $filtered_lines = array_slice($filtered_lines, -$target_lines_count);
                }
                
                echo json_encode([
                    'success' => true,
                    'filepath' => $filepath,
                    'logs' => implode("\n", $filtered_lines)
                ]);
                break;
                
            case 'get_wp_defines':
                if (empty($_POST['path'])) {
                    throw new Exception("Missing site path parameter.");
                }
                if (strpos(realpath($_POST['path']) ?: $_POST['path'], $home) !== 0) {
                    throw new Exception("Invalid directory access.");
                }
                $defines = get_wp_defines_from_config($_POST['path'] . '/wp-config.php');
                echo json_encode(['success' => true, 'defines' => $defines]);
                break;

            case 'set_wp_define':
                if (empty($_POST['path']) || !isset($_POST['constant']) || !isset($_POST['value']) || !isset($_POST['enable'])) {
                    throw new Exception("Missing parameters.");
                }
                if (strpos(realpath($_POST['path']) ?: $_POST['path'], $home) !== 0) {
                    throw new Exception("Invalid directory access.");
                }
                $wp_config_path = $_POST['path'] . '/wp-config.php';
                if (is_wordpress_locked($_POST['path'])) {
                    throw new Exception("Website đang bị khóa (WP Lock). Vui lòng tắt WP Lock trước khi thay đổi cấu hình.");
                }
                check_wp_config_writable($wp_config_path);
                $constant = preg_replace('/[^A-Z0-9_]/', '', strtoupper($_POST['constant']));
                $value_raw = $_POST['value'];
                $enable = ($_POST['enable'] === 'true' || $_POST['enable'] === '1');

                // Determine value type
                if (in_array(strtolower($value_raw), ['true', 'false', '1', '0'])) {
                    // Boolean constants
                    $val_bool = in_array(strtolower($value_raw), ['true', '1']);
                    wp_manager_config_define_modify($wp_config_path, $constant, $val_bool, $enable);
                } elseif (is_numeric($value_raw) && strpos($value_raw, '.') === false) {
                    // Integer constants — store as string but without quotes in PHP
                    $content = file_get_contents($wp_config_path);
                    $pattern = "/\s*define\s*\(\s*['\"]" . preg_quote($constant, '/') . "['\"]\s*,\s*[^;]*\)\s*;/i";
                    $content = preg_replace($pattern, '', $content);
                    if ($enable) {
                        $define_str = "\ndefine('{$constant}', " . intval($value_raw) . ");\n";
                        $insert_pos = strpos($content, "/* That's all, stop editing!");
                        if ($insert_pos === false) $insert_pos = strpos($content, "require_once");
                        if ($insert_pos !== false) {
                            $content = substr_replace($content, $define_str, $insert_pos, 0);
                        } else {
                            $content .= $define_str;
                        }
                    }
                    @file_put_contents($wp_config_path, $content);
                } else {
                    // String constants
                    wp_manager_config_define_modify($wp_config_path, $constant, $value_raw, $enable);
                }
                wp_manager_log("Cập nhật define '{$constant}' cho website: " . $_POST['path'] . " | Enable: " . ($enable ? 'true' : 'false') . " | Value: {$value_raw}");
                echo json_encode(['success' => true, 'message' => "Define '{$constant}' đã được cập nhật thành công."]);
                break;

            case 'clear_log_file':
                if (empty($_POST['path']) || empty($_POST['log_type'])) {
                    throw new Exception("Missing parameters.");
                }
                if (strpos(realpath($_POST['path']) ?: $_POST['path'], $home) !== 0) {
                    throw new Exception("Invalid directory access.");
                }
                
                $log_type = $_POST['log_type'];
                if ($log_type === 'access' || $log_type === 'error') {
                    throw new Exception("Không được phép xóa file log hệ thống (Access/Error log) để tránh làm gián đoạn Web Server và ảnh hưởng đến thống kê của DirectAdmin.");
                }
                
                $domain = '';
                $domains_prefix = $home . '/domains/';
                $site_path_real = realpath($_POST['path']) ?: $_POST['path'];
                if (strpos($site_path_real, $domains_prefix) === 0) {
                    $relative = substr($site_path_real, strlen($domains_prefix));
                    $parts = explode('/', str_replace('\\', '/', $relative));
                    if (count($parts) > 0) {
                        $domain = $parts[0];
                    }
                }
                
                $filepath = '';
                if ($log_type === 'wp_debug') {
                    $filepath = $_POST['path'] . '/wp-content/debug.log';
                } elseif ($log_type === 'php_error') {
                    $filepath = find_php_error_log_path($_POST['path'], $domain, $username);
                }
                
                if (empty($filepath) || !file_exists($filepath)) {
                    throw new Exception("File log không tồn tại hoặc chưa được tạo.");
                }
                
                if (!is_writable($filepath)) {
                    throw new Exception("Không có quyền ghi vào file log: " . basename($filepath) . ". Vui lòng kiểm tra lại phân quyền file.");
                }
                
                if (@file_put_contents($filepath, '') === false) {
                    throw new Exception("Lỗi khi xóa rỗng nội dung file log.");
                }
                
                wp_manager_log("Xóa rỗng file log " . basename($filepath) . " cho website: " . $_POST['path']);
                echo json_encode(['success' => true, 'message' => 'Đã xóa rỗng dữ liệu log thành công.']);
                break;
            default:
                throw new Exception("Unknown action parameter: " . $action);
        }
    } catch (Throwable $e) {
        wp_manager_log("Thao tác thất bại. Action: {$action} | Lỗi: " . $e->getMessage());
        // Discard any stray output that leaked into the inner buffer before writing the error JSON
        ob_end_clean();
        ob_start();
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    // Flush inner buffer (JSON body) to the actual output stream
    $json_body = ob_get_clean();
    echo $json_body;
    exit;
}


/**
 * Read all define() constants from wp-config.php and return them as an array
 */
function get_wp_defines_from_config($wp_config_path) {
    if (!file_exists($wp_config_path)) {
        return [];
    }
    $content = file_get_contents($wp_config_path);
    $defines = [];
    // Match both quoted string and boolean/int values
    preg_match_all("/define\s*\(\s*['\"]([A-Z0-9_]+)['\"]\s*,\s*(.*?)\s*\)\s*;/im", $content, $matches, PREG_SET_ORDER);
    foreach ($matches as $m) {
        $constant = $m[1];
        $raw_val  = trim($m[2]);
        // Determine value type
        if (preg_match("/^['\"](.*)['\"]\s*$/s", $raw_val, $sv)) {
            $value = $sv[1];
            $type  = 'string';
        } elseif (strtolower($raw_val) === 'true') {
            $value = 'true';
            $type  = 'bool';
        } elseif (strtolower($raw_val) === 'false') {
            $value = 'false';
            $type  = 'bool';
        } elseif (is_numeric($raw_val)) {
            $value = $raw_val;
            $type  = 'int';
        } else {
            $value = $raw_val;
            $type  = 'raw';
        }
        $defines[$constant] = ['value' => $value, 'type' => $type, 'defined' => true];
    }
    return $defines;
}

/**
 * Scan home folder for any .zip files
 */
function list_user_zip_files($home) {
    $zip_files = [];
    // Standard Linux find command
    if (strtoupper(substr(PHP_OS, 0, 3)) !== 'WIN') {
        $cmd = sprintf(
            'find %s -name "*.zip" -not -path "*/node_modules/*" -not -path "*/.git/*" -maxdepth 4 2>/dev/null',
            escapeshellarg($home)
        );
        $output = [];
        @exec($cmd, $output);
        foreach ($output as $line) {
            $line = trim($line);
            if ($line !== '' && file_exists($line)) {
                $display_path = str_replace($home . '/', '', $line);
                $zip_files[] = [
                    'absolute_path' => $line,
                    'display_path' => $display_path
                ];
            }
        }
    } else {
        // Windows fallback for local development
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($home, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );
        foreach ($iterator as $item) {
            if ($item->isFile() && strtolower($item->getExtension()) === 'zip') {
                $path = str_replace('\\', '/', $item->getPathname());
                $display_path = str_replace($home . '/', '', $path);
                $zip_files[] = [
                    'absolute_path' => $path,
                    'display_path' => $display_path
                ];
            }
        }
    }
    return $zip_files;
}

/**
 * Helper to change plugin file version header
 */
function change_plugin_file_version($file_path, $new_version) {
    if (!file_exists($file_path)) {
        wp_manager_log("change_plugin_file_version: File not found: " . $file_path);
        return false;
    }
    $content = file_get_contents($file_path);
    if (preg_match('/Version\s*:\s*([^\r\n]*)/i', $content, $matches)) {
        $orig_version = trim($matches[1]);
        wp_manager_log("change_plugin_file_version: Found version: " . $orig_version . " in " . $file_path . ". Changing to " . $new_version);
        $new_content = preg_replace('/Version\s*:\s*[^\r\n]*/i', 'Version:              ' . $new_version, $content, 1);
        file_put_contents($file_path, $new_content);
        return $orig_version;
    }
    wp_manager_log("change_plugin_file_version: Version header not found in " . $file_path);
    return false;
}

/**
 * Helper to change theme style.css version header
 */
function change_theme_style_version($style_css_path, $new_version) {
    if (!file_exists($style_css_path)) {
        wp_manager_log("change_theme_style_version: File not found: " . $style_css_path);
        return false;
    }
    $content = file_get_contents($style_css_path);
    if (preg_match('/Version\s*:\s*([^\r\n]*)/i', $content, $matches)) {
        $orig_version = trim($matches[1]);
        wp_manager_log("change_theme_style_version: Found version: " . $orig_version . " in " . $style_css_path . ". Changing to " . $new_version);
        $new_content = preg_replace('/Version\s*:\s*[^\r\n]*/i', 'Version:              ' . $new_version, $content, 1);
        file_put_contents($style_css_path, $new_content);
        return $orig_version;
    }
    wp_manager_log("change_theme_style_version: Version header not found in " . $style_css_path);
    return false;
}

/**
 * Lấy danh sách premium từ Store Web trung tâm
 */
function get_premium_list() {
    $url = 'https://ultimate-wordpress-manager.wpcloud.vn/premium.json';
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    $data = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($http_code !== 200 || !$data) {
        return ['plugins' => [], 'themes' => []];
    }
    $json = json_decode($data, true);
    if (!is_array($json)) {
        return ['plugins' => [], 'themes' => []];
    }
    if (!isset($json['plugins'])) $json['plugins'] = [];
    if (!isset($json['themes'])) $json['themes'] = [];
    return $json;
}

/**
 * Quét file PHP chính chứa tiêu đề Plugin trong một thư mục
 */
function find_plugin_main_file($plugins_dir, $folder_name) {
    $dir = $plugins_dir . '/' . $folder_name;
    if (!is_dir($dir)) {
        if (file_exists($dir . '.php')) {
            return $folder_name . '.php';
        }
        return null;
    }
    
    // Prioritize checking the file that matches the plugin directory name (standard convention)
    $primary_file = $dir . '/' . $folder_name . '.php';
    if (file_exists($primary_file)) {
        $content = file_get_contents($primary_file, false, null, 0, 8192);
        if (preg_match('/Plugin Name\s*:/i', $content)) {
            return $folder_name . '/' . $folder_name . '.php';
        }
    }
    
    $files = glob($dir . '/*.php');
    if ($files) {
        foreach ($files as $file) {
            if (basename($file) === $folder_name . '.php') {
                continue;
            }
            $content = file_get_contents($file, false, null, 0, 8192);
            if (preg_match('/Plugin Name\s*:/i', $content)) {
                return $folder_name . '/' . basename($file);
            }
        }
    }
    
    $subfiles = glob($dir . '/*/*.php');
    if ($subfiles) {
        foreach ($subfiles as $file) {
            $content = file_get_contents($file, false, null, 0, 8192);
            if (preg_match('/Plugin Name\s*:/i', $content)) {
                $sub_folder = basename(dirname($file));
                return $folder_name . '/' . $sub_folder . '/' . basename($file);
            }
        }
    }
    return null;
}

function create_directadmin_database($dbname, $dbuser, $dbpass, $targetUser) {
    // Step 1: Read MySQL admin credentials from DirectAdmin config
    $mysql_conf = '/usr/local/directadmin/conf/mysql.conf';
    if (!file_exists($mysql_conf) || !is_readable($mysql_conf)) {
        throw new Exception("Cannot read DirectAdmin MySQL config: $mysql_conf");
    }
    
    $mysql_creds = [];
    foreach (file($mysql_conf, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        if (strpos($line, '=') !== false) {
            [$k, $v] = explode('=', $line, 2);
            $mysql_creds[trim($k)] = trim($v);
        }
    }
    
    $mysql_host = $mysql_creds['host'] ?? 'localhost';
    $mysql_admin = $mysql_creds['user'] ?? 'da_admin';
    $mysql_admin_pass = $mysql_creds['passwd'] ?? '';
    
    if (empty($mysql_admin_pass)) {
        throw new Exception("Empty MySQL admin password in DirectAdmin config.");
    }
    
    // Step 2: Build full prefixed names (DA always prefixes with username)
    $full_db   = $targetUser . '_' . $dbname;
    $full_user = $targetUser . '_' . $dbuser;
    
    // da_admin is typically granted on localhost via Unix socket, not TCP 127.0.0.1.
    // If host is 127.0.0.1 or localhost, omit -h flag to force Unix socket.
    // If a custom remote host is configured, use -h explicitly.
    $esc_admin_pass = escapeshellarg($mysql_admin_pass);
    $mysql_bin = file_exists('/usr/bin/mariadb') ? '/usr/bin/mariadb' : 'mysql';
    $mysql_user_arg = '-u ' . escapeshellarg($mysql_admin);
    
    // Find socket path — try common locations
    $socket_paths = [
        $mysql_creds['socket'] ?? '',
        '/var/lib/mysql/mysql.sock',
        '/tmp/mysql.sock',
        '/var/run/mysqld/mysqld.sock',
    ];
    $socket_arg = '';
    foreach ($socket_paths as $sp) {
        if (!empty($sp) && file_exists($sp)) {
            $socket_arg = '--socket=' . escapeshellarg($sp);
            break;
        }
    }
    
    if (in_array($mysql_host, ['localhost', '127.0.0.1', '::1'], true)) {
        // Use Unix socket — either explicit socket path or let client find default
        $mysql_cmd = "$mysql_bin $socket_arg $mysql_user_arg -p$esc_admin_pass";
    } else {
        $mysql_cmd = "$mysql_bin -h " . escapeshellarg($mysql_host) . " $mysql_user_arg -p$esc_admin_pass";
    }
    
    // Helper: run SQL via stdin pipe — avoids ALL shell-escaping issues
    $run_sql = function(string $sql) use ($mysql_cmd): void {
        $desc = [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
        $proc = proc_open($mysql_cmd, $desc, $pipes);
        if (!is_resource($proc)) {
            throw new Exception("Failed to open MySQL process.");
        }
        fwrite($pipes[0], $sql);
        fclose($pipes[0]);
        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $code = proc_close($proc);
        if ($code !== 0) {
            throw new Exception(trim($stderr ?: $stdout));
        }
    };
    
    // Step 3: Create database (no backtick needed — name is safe alphanumeric+underscore)
    $run_sql("CREATE DATABASE IF NOT EXISTS {$full_db};");
    
    // Step 4: Create MySQL user and grant privileges
    // Escape password value for SQL (not shell) — replace ' with \'
    $sql_safe_pass = str_replace("'", "\\'", $dbpass);
    $run_sql(
        "CREATE USER IF NOT EXISTS '{$full_user}'@'localhost' IDENTIFIED BY '{$sql_safe_pass}';" .
        " GRANT ALL PRIVILEGES ON {$full_db}.* TO '{$full_user}'@'localhost';" .
        " FLUSH PRIVILEGES;"
    );
    
    // Step 5: Register database with DirectAdmin by updating user's config files
    $da_user_dir  = "/usr/local/directadmin/data/users/{$targetUser}";
    $db_list_file = "{$da_user_dir}/databases.list";
    $db_conf_file = "{$da_user_dir}/databases.conf";
    
    if (is_dir($da_user_dir)) {
        // Append to databases.list (one db per line)
        if (file_exists($db_list_file)) {
            $existing = file($db_list_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            if (!in_array($full_db, $existing)) {
                file_put_contents($db_list_file, $full_db . "\n", FILE_APPEND | LOCK_EX);
            }
        } else {
            file_put_contents($db_list_file, $full_db . "\n", LOCK_EX);
        }
        
        // Append to databases.conf (format: db_name=db_user)
        if (file_exists($db_conf_file)) {
            $existing_conf = file_get_contents($db_conf_file);
            if (strpos($existing_conf, $full_db . '=') === false) {
                file_put_contents($db_conf_file, "{$full_db}={$full_user}\n", FILE_APPEND | LOCK_EX);
            }
        } else {
            file_put_contents($db_conf_file, "{$full_db}={$full_user}\n", LOCK_EX);
        }
    }
    
    return [
        'db'   => $full_db,
        'user' => $full_user,
    ];
}


