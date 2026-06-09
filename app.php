<?php
/**
 * Ultimate DirectAdmin WordPress Manager
 * Core Backend Controller
 */

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

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

$_POST = [];
$POST_STRING = getenv('POST');
if ($POST_STRING != "") {
    parse_str(html_entity_decode($POST_STRING), $post_array);
    foreach ($post_array as $key => $value) {
        $_POST[urldecode($key)] = urldecode($value);
    }
}

/**
 * Determine if current executing user is administrator
 */
function is_admin_user() {
    $current_user = getenv('USERNAME') ?: getenv('USER');
    return (strpos($_SERVER['SCRIPT_FILENAME'] ?? '', '/admin/') !== false) || ($current_user === 'admin');
}

/**
 * Recursively remove directories and files
 */
function rmdir_recursive($dir) {
    if (!is_dir($dir)) return;
    $files = array_diff(scandir($dir), ['.', '..']);
    foreach ($files as $file) {
        $path = $dir . '/' . $file;
        if (is_dir($path) && !is_link($path)) {
            rmdir_recursive($path);
        } else {
            @unlink($path);
        }
    }
    @rmdir($dir);
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
 * Check if WordPress files are locked (immutable)
 */
function is_wordpress_locked($site_path) {
    if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
        return file_exists($site_path . '/.locked_mock');
    }
    $wp_config = $site_path . '/wp-config.php';
    if (!file_exists($wp_config)) return false;
    
    $lsattr_path = trim(@shell_exec('which lsattr 2>/dev/null') ?: '/usr/bin/lsattr');
    $output = @shell_exec("{$lsattr_path} " . escapeshellarg($wp_config) . " 2>/dev/null");
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
    $output = @shell_exec("{$esc_wrapper} lock {$esc_path} 2>&1");
    
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
    $output = @shell_exec("{$esc_wrapper} unlock {$esc_path} 2>&1");
    
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
    
    return true;
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
    
    // Fallbacks
    if ($siteurl === '') {
        $siteurl = 'http://' . $domain . ($sub_path !== '' ? '/' . $sub_path : '');
    }
    if ($blogname === '') {
        $blogname = $domain . ($sub_path !== '' ? '/' . $sub_path : '');
    }
    
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
        'locked' => is_wordpress_locked(dirname($wp_config_path))
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
    $home = getenv('HOME') ?: "/home/{$username}";
    $log_file = $home . '/.ultimate_wp_manager_debug.log';
    $timestamp = date('Y-m-d H:i:s');
    @file_put_contents($log_file, "[{$timestamp}] {$msg}\n", FILE_APPEND);
}

/**
 * Helper to resolve dynamic path overrides from DirectAdmin config files.
 */
function get_custom_docroot_from_configs($domain, $subdomain, $home, $wrapper) {
    wp_manager_log("get_custom_docroot_from_configs: domain={$domain}, subdomain={$subdomain}");
    $domains_dir = $home . '/domains';
    
    // Check subdomains, conf, and custom HTTPD configurations via wrapper
    $types = ['subdomains', 'conf', 'cust_httpd', 'cust_nginx', 'cust_openlitespeed', 'cust_apache', 'subdomains.docroot.override'];
    $config_contents = [];
    foreach ($types as $type) {
        $cmd = escapeshellarg($wrapper) . " get_domain_config " . escapeshellarg($domain) . " " . escapeshellarg($type) . " 2>&1";
        $content = @shell_exec($cmd);
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
 * Core WordPress programmatic Installer
 */
function install_wordpress_instance($params, $home) {
    $domain = $params['domain'];
    $subdir = $params['subdir'] ?? '';
    $db_name = $params['db_name'];
    $db_user = $params['db_user'];
    $db_pass = $params['db_pass'];
    $site_title = $params['site_title'];
    $admin_user = $params['admin_user'];
    $admin_pass = $params['admin_pass'];
    $admin_email = $params['admin_email'];
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
    $request_uri = $subdir_clean !== '' ? '/' . $subdir_clean . '/' : '/';
    
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

/**
 * Generate Magic Login temporary mu-plugin
 */
function generate_magic_login($site_path, $home) {
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
        // Auto-detect and fetch the first administrative user
        $users = get_users(['role' => 'administrator', 'number' => 1]);
        if (!empty($users)) {
            $user = $users[0];
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
 * Self Update Admin functionality
 */
function update_plugin_from_github() {
    if (!is_admin_user()) {
        throw new Exception("Forbidden: Updates are restricted to Administrators.");
    }
    
    $plugin_dir = '/usr/local/directadmin/plugins/ultimate-directadmin-wordpress-manager';
    // LOCAL DEVELOPMENT / WIN FALLBACK
    if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
        $plugin_dir = 'f:/ultimate-directadmin-wordpress-manager';
    }
    
    $temp_zip = sys_get_temp_dir() . '/plugin_update_' . time() . '.zip';
    $github_zip_url = 'https://github.com/tuend-work/ultimate-directadmin-wordpress-manager/archive/refs/heads/main.zip';
    
    $ch = curl_init($github_zip_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 60);
    curl_setopt($ch, CURLOPT_USERAGENT, 'DirectAdmin-WordPress-Manager-Updater');
    $data = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($http_code !== 200 || !$data) {
        throw new Exception("Update download failed from GitHub (HTTP Code: {$http_code}).");
    }
    
    file_put_contents($temp_zip, $data);
    
    $zip = new ZipArchive;
    if ($zip->open($temp_zip) === TRUE) {
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
            throw new Exception("Structure error in downloaded ZIP archive.");
        }
        
        // Copy files recursively
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($src_dir, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );
        foreach ($iterator as $item) {
            $subPath = $iterator->getSubPathName();
            $target = $plugin_dir . '/' . $subPath;
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
        
        // Reset permissions
        set_permissions_recursive($plugin_dir);
        
        // Cleanup temp extraction
        rmdir_recursive($extract_temp);
        @unlink($temp_zip);
        
        return [
            'success' => true,
            'message' => 'Ultimate WordPress Manager updated from GitHub successfully.'
        ];
    } else {
        @unlink($temp_zip);
        throw new Exception("ZIP archive could not be extracted.");
    }
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
 * Handle API Endpoint actions
 */
function run_api() {
    // Clear any warnings/notices captured in output buffer to keep CGI headers clean
    if (ob_get_length()) {
        ob_clean();
    }
    
    // Standard CGI headers for DirectAdmin raw mode (HTTP/1.1 is standard)
    echo "HTTP/1.1 200 OK\r\n";
    echo "Content-Type: application/json; charset=utf-8\r\n";
    echo "Access-Control-Allow-Origin: *\r\n";
    echo "\r\n";
    
    $username = getenv('USERNAME') ?: getenv('USER') ?: 'nobody';
    $home = getenv('HOME') ?: "/home/{$username}";
    if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
        $username = 'local_user';
        $home = 'C:/Users/local_user';
    }
    
    $action = $_GET['action'] ?? $_POST['action'] ?? '';
    
    try {
        switch ($action) {
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
                $required = ['domain', 'db_name', 'db_user', 'db_pass', 'site_title', 'admin_user', 'admin_pass', 'admin_email'];
                foreach ($required as $field) {
                    if (empty($_POST[$field])) {
                        throw new Exception("Required parameter missing: {$field}");
                    }
                }
                $res = install_wordpress_instance($_POST, $home);
                echo json_encode($res);
                break;
                
            case 'magic_login':
                if (empty($_POST['path'])) {
                    throw new Exception("Missing site path parameter.");
                }
                $res = generate_magic_login($_POST['path'], $home);
                echo json_encode($res);
                break;
                
            case 'delete':
                if (empty($_POST['path'])) {
                    throw new Exception("Missing site path parameter.");
                }
                $res = delete_wordpress_instance($_POST['path'], $home);
                echo json_encode($res);
                break;
                
            case 'lock':
                if (empty($_POST['path'])) {
                    throw new Exception("Missing site path parameter.");
                }
                if (strpos(realpath($_POST['path']) ?: $_POST['path'], $home) !== 0) {
                    throw new Exception("Invalid directory access.");
                }
                $res = lock_wordpress_instance($_POST['path']);
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
                $res = unlock_wordpress_instance($_POST['path']);
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
                
                $response_plugins = [];
                foreach ($plugins as $file => $details) {
                    $details['file'] = $file;
                    $details['active'] = in_array($file, $active);
                    $response_plugins[] = $details;
                }
                echo json_encode(['success' => true, 'plugins' => $response_plugins]);
                break;

            case 'toggle_plugin':
                if (empty($_POST['path']) || empty($_POST['plugin_file']) || empty($_POST['status'])) {
                    throw new Exception("Missing parameters.");
                }
                if (strpos(realpath($_POST['path']) ?: $_POST['path'], $home) !== 0) {
                    throw new Exception("Invalid directory access.");
                }
                toggle_plugin_status($_POST['path'], $_POST['plugin_file'], $_POST['status']);
                echo json_encode(['success' => true, 'message' => 'Plugin status updated successfully.']);
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
                
                $response_themes = [];
                foreach ($themes as $theme) {
                    $theme['active'] = ($theme['folder'] === $active);
                    $response_themes[] = $theme;
                }
                echo json_encode(['success' => true, 'themes' => $response_themes]);
                break;

            case 'activate_theme':
                if (empty($_POST['path']) || empty($_POST['theme_folder'])) {
                    throw new Exception("Missing parameters.");
                }
                if (strpos(realpath($_POST['path']) ?: $_POST['path'], $home) !== 0) {
                    throw new Exception("Invalid directory access.");
                }
                activate_theme($_POST['path'], $_POST['theme_folder']);
                echo json_encode(['success' => true, 'message' => 'Theme activated successfully.']);
                break;
                
            case 'update_plugin':
                $res = update_plugin_from_github();
                echo json_encode($res);
                break;
                
            default:
                throw new Exception("Unknown action parameter: " . $action);
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}
