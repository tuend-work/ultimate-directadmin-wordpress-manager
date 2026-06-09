<?php
/**
 * Scanner Debugging Script
 * Run this on your server: php test_scan.php
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

$username = getenv('USERNAME') ?: getenv('USER') ?: 'nobody';
if ($username === 'root' || $username === 'nobody') {
    // If run as root, try to guess or use the first user
    echo "Running as root. Checking users in /home...\n";
    $users = array_diff(scandir('/home'), ['.', '..']);
    foreach ($users as $u) {
        if (is_dir("/home/{$u}/domains")) {
            $username = $u;
            break;
        }
    }
}

$home = "/home/{$username}";
echo "Detected User: {$username}\n";
echo "Detected Home Path: {$home}\n\n";

if (!is_dir($home)) {
    die("Error: Home directory {$home} does not exist.\n");
}

require_once __DIR__ . '/app.php';

echo "--- START DIRECTORY SCAN ---\n";
$domains_dir = $home . '/domains';
if (!is_dir($domains_dir)) {
    die("Error: Domains directory {$domains_dir} does not exist.\n");
}

$domains = array_diff(scandir($domains_dir), ['.', '..']);
foreach ($domains as $domain) {
    $domain_path = $domains_dir . '/' . $domain;
    echo "Found domain folder: {$domain_path}\n";
    
    // Check public_html
    $public_html = $domain_path . '/public_html';
    if (is_dir($public_html)) {
        echo "  - public_html exists. Scanning...\n";
        $installations = [];
        scan_directory_for_wp_verbose($public_html, $domain, '', $installations);
        echo "  - Scan completed. Found " . count($installations) . " installations.\n";
        print_r($installations);
    } else {
        echo "  - public_html does not exist.\n";
    }
}

function scan_directory_for_wp_verbose($dir, $domain, $sub_path, &$installations, $depth = 0, &$scanned_paths = null) {
    if ($depth > 2) {
        echo "    [Depth > 2] Skipping: {$dir}\n";
        return;
    }
    
    if ($scanned_paths === null) {
        $scanned_paths = [];
    }
    
    $real = realpath($dir);
    echo "    [Depth {$depth}] Checking directory: {$dir} (Realpath: {$real})\n";
    if ($real === false) {
        echo "      -> Realpath failed.\n";
        return;
    }
    if (isset($scanned_paths[$real])) {
        echo "      -> Already scanned: {$real}\n";
        return;
    }
    $scanned_paths[$real] = true;
    
    $wp_config_path = $dir . '/wp-config.php';
    echo "      Checking wp-config.php: {$wp_config_path}\n";
    if (file_exists($wp_config_path)) {
        echo "      -> FOUND wp-config.php!\n";
        $readable = is_readable($wp_config_path) ? 'YES' : 'NO';
        echo "      -> Readable by current user: {$readable}\n";
        
        // Attempt parsing
        $info = parse_wp_config($wp_config_path, $domain, $sub_path);
        if ($info) {
            echo "      -> Parsed successfully.\n";
            $installations[] = $info;
        } else {
            echo "      -> Parsing failed.\n";
        }
        return;
    }
    
    if (!is_dir($dir)) {
        echo "      -> Is not a directory.\n";
        return;
    }
    
    $files = @scandir($dir);
    if ($files === false) {
        echo "      -> Unable to read directory (Permission denied?)\n";
        return;
    }
    
    $files = array_diff($files, ['.', '..']);
    foreach ($files as $file) {
        $path = $dir . '/' . $file;
        if (is_dir($path)) {
            if (in_array($file, ['wp-admin', 'wp-includes', 'wp-content', 'node_modules', '.git', '.wp-cache'])) {
                continue;
            }
            $next_sub_path = $sub_path === '' ? $file : $sub_path . '/' . $file;
            scan_directory_for_wp_verbose($path, $domain, $next_sub_path, $installations, $depth + 1, $scanned_paths);
        }
    }
}
