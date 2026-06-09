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
 * Parse wp-config.php and extract metadata
 */
function parse_wp_config($wp_config_path, $domain, $sub_path) {
    if (!file_exists($wp_config_path)) return null;
    
    // Auto-detect if $sub_path starts with a configured subdomain prefix and remap it
    if ($sub_path !== '') {
        $parts = explode('/', $sub_path);
        $first_part = $parts[0];
        
        $username = getenv('USERNAME') ?: getenv('USER') ?: 'nobody';
        $home = getenv('HOME') ?: "/home/{$username}";
        if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
            $home = 'C:/Users/local_user';
        }
        
        $is_sub = false;
        // Check in subdomains.list
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
        // Also check if physical /domains/{domain}/subdomains/{first_part} exists
        if (is_dir($home . '/domains/' . $domain . '/subdomains/' . $first_part)) {
            $is_sub = true;
        }
        
        if ($is_sub) {
            $domain = $first_part . '.' . $domain;
            $sub_path = implode('/', array_slice($parts, 1));
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
        'status' => $status
    ];
}

/**
 * Scan directory recursively up to 2 sub-levels for WP config (supports symlinks safely)
 */
function scan_directory_for_wp($dir, $domain, $sub_path, &$installations, $depth = 0, &$scanned_paths = null) {
    if ($depth > 2) return;
    
    if ($scanned_paths === null) {
        $scanned_paths = [];
    }
    
    $real = realpath($dir);
    if ($real === false || isset($scanned_paths[$real])) {
        return;
    }
    $scanned_paths[$real] = true;
    
    $wp_config_path = $dir . '/wp-config.php';
    if (file_exists($wp_config_path)) {
        $info = parse_wp_config($wp_config_path, $domain, $sub_path);
        if ($info) {
            $installations[] = $info;
        }
        return; // Skip checking subdirectories inside this WordPress root
    }
    
    if (!is_dir($dir)) return;
    $files = array_diff(scandir($dir), ['.', '..']);
    foreach ($files as $file) {
        $path = $dir . '/' . $file;
        if (is_dir($path)) {
            if (in_array($file, ['wp-admin', 'wp-includes', 'wp-content', 'node_modules', '.git', '.wp-cache'])) {
                continue;
            }
            $next_sub_path = $sub_path === '' ? $file : $sub_path . '/' . $file;
            scan_directory_for_wp($path, $domain, $next_sub_path, $installations, $depth + 1, $scanned_paths);
        }
    }
}

/**
 * Scans all domains and saves cache
 */
function scan_wordpress_installations($home, $username) {
    $domains_dir = $home . '/domains';
    $installations = [];
    
    if (is_dir($domains_dir)) {
        $domains = array_diff(scandir($domains_dir), ['.', '..']);
        foreach ($domains as $domain) {
            $domain_path = $domains_dir . '/' . $domain;
            if (!is_dir($domain_path)) continue;
            
            // 1. Scan standard public_html folder
            $public_html = $domain_path . '/public_html';
            if (is_dir($public_html)) {
                scan_directory_for_wp($public_html, $domain, '', $installations);
            }
            
            // 2. Scan dedicated subdomains folder if it exists
            $subdomains_dir = $domain_path . '/subdomains';
            if (is_dir($subdomains_dir)) {
                $subdomains = array_diff(scandir($subdomains_dir), ['.', '..']);
                foreach ($subdomains as $subdomain) {
                    $sub_pub_html = $subdomains_dir . '/' . $subdomain . '/public_html';
                    if (is_dir($sub_pub_html)) {
                        scan_directory_for_wp($sub_pub_html, $subdomain . '.' . $domain, '', $installations);
                    }
                }
            }
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
 * Resolves a selected domain string into parent domain, subdomain prefix (if any), and its document root directory.
 */
function resolve_domain_path($domain_str, $home) {
    $domains_dir = $home . '/domains';
    
    // First, check if the domain_str exists directly as a main domain folder
    if (is_dir($domains_dir . '/' . $domain_str)) {
        return [
            'is_subdomain' => false,
            'parent_domain' => $domain_str,
            'subdomain_prefix' => '',
            'doc_root' => $domains_dir . '/' . $domain_str . '/public_html'
        ];
    }
    
    // If not, it could be a subdomain. Let's find if a parent domain matches the suffix.
    $parts = explode('.', $domain_str);
    for ($i = 1; $i < count($parts) - 1; $i++) {
        $parent = implode('.', array_slice($parts, $i));
        if (is_dir($domains_dir . '/' . $parent)) {
            $subdomain = implode('.', array_slice($parts, 0, $i));
            
            // Now check standard subdomain directories
            $subdomain_dir = $domains_dir . '/' . $parent . '/subdomains/' . $subdomain . '/public_html';
            if (is_dir($domains_dir . '/' . $parent . '/subdomains/' . $subdomain)) {
                $doc_root = $subdomain_dir;
            } else {
                // Fallback to public_html/subdomain
                $doc_root = $domains_dir . '/' . $parent . '/public_html/' . $subdomain;
            }
            
            return [
                'is_subdomain' => true,
                'parent_domain' => $parent,
                'subdomain_prefix' => $subdomain,
                'doc_root' => $doc_root
            ];
        }
    }
    
    // Fallback: treat it as a main domain, even if folder doesn't exist yet
    return [
        'is_subdomain' => false,
        'parent_domain' => $domain_str,
        'subdomain_prefix' => '',
        'doc_root' => $domains_dir . '/' . $domain_str . '/public_html'
    ];
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
    
    rmdir_recursive($site_path);
    
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
