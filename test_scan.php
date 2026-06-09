<?php
/**
 * Scanner Debugging Script
 * Upload to the plugin directory, then run: php test_scan.php
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

$username = 'testplugin';
$home = getenv('HOME') ?: "/home/{$username}";

echo "Detected User : {$username}\n";
echo "Detected Home : {$home}\n\n";

if (!is_dir($home)) {
    die("ERROR: Home directory {$home} does not exist.\n");
}

require_once __DIR__ . '/app.php';

echo "--- START DIRECTORY SCAN ---\n";
$domains_dir = $home . '/domains';
if (!is_dir($domains_dir)) {
    die("ERROR: Domains directory {$domains_dir} does not exist.\n");
}

$domains = array_diff(scandir($domains_dir), ['.', '..']);
foreach ($domains as $domain) {
    $domain_path = $domains_dir . '/' . $domain;
    if (!is_dir($domain_path)) continue;

    echo "\n[DOMAIN] {$domain_path}\n";

    // Search recursively for every wp-config.php found anywhere under this domain
    $found_configs = [];
    find_wp_configs($domain_path, $found_configs);

    if (empty($found_configs)) {
        echo "  -> No wp-config.php found under this domain folder.\n";
    } else {
        foreach ($found_configs as $cfg_path) {
            echo "  [wp-config.php] {$cfg_path}\n";
            $readable = is_readable($cfg_path) ? 'YES' : 'NO';
            echo "    Readable: {$readable}\n";

            $info = parse_wp_config($cfg_path);
            if ($info) {
                echo "    Parsed OK:\n";
                echo "      domain   : {$info['domain']}\n";
                echo "      subdir   : {$info['subdir']}\n";
                echo "      siteurl  : {$info['siteurl']}\n";
                echo "      blogname : {$info['blogname']}\n";
                echo "      version  : {$info['version']}\n";
                echo "      db_name  : {$info['db_name']}\n";
                echo "      status   : {$info['status']}\n";
            } else {
                echo "    Parsing FAILED.\n";
            }
        }
    }
}

echo "\n--- FULL SCAN (via scan_wordpress_installations) ---\n";
$results = scan_wordpress_installations($home, $username);
echo "Total installations found: " . count($results) . "\n";
foreach ($results as $i => $site) {
    echo "\n  [" . ($i+1) . "] {$site['blogname']} ({$site['siteurl']})\n";
    echo "      path    : {$site['path']}\n";
    echo "      domain  : {$site['domain']}\n";
    echo "      version : {$site['version']}\n";
    echo "      status  : {$site['status']}\n";
}

// ---------------------------------------------------------------
// Helper: recursively find all wp-config.php files (no depth limit)
// ---------------------------------------------------------------
function find_wp_configs($dir, &$results, &$visited = null, $depth = 0) {
    if ($depth > 6) return;

    if ($visited === null) $visited = [];
    $real = realpath($dir);
    if ($real === false || isset($visited[$real])) return;
    $visited[$real] = true;

    $wp_config = $dir . '/wp-config.php';
    if (file_exists($wp_config)) {
        $results[] = $wp_config;
        return; // Don't recurse inside a WP install
    }

    $entries = @scandir($dir);
    if (!$entries) return;

    foreach (array_diff($entries, ['.', '..']) as $entry) {
        $path = $dir . '/' . $entry;
        if (is_dir($path)) {
            if (in_array($entry, ['wp-admin', 'wp-includes', 'wp-content', 'node_modules', '.git'])) {
                continue;
            }
            find_wp_configs($path, $results, $visited, $depth + 1);
        }
    }
}
