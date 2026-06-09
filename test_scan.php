<?php
/**
 * Scanner Debugging Script - v2 (find-based)
 * Run: php test_scan.php
 */
error_reporting(E_ALL);
ini_set('display_errors', 1);

$username = 'testplugin';
$home     = '/home/testplugin';

echo "User : {$username}\n";
echo "Home : {$home}\n\n";

require_once __DIR__ . '/app.php';

// --- Step 1: raw find output ---
echo "=== RAW find output ===\n";
$cmd = sprintf(
    'find %s -name "wp-config.php" -not -path "*/wp-content/*" -not -path "*/node_modules/*" 2>/dev/null',
    escapeshellarg($home)
);
echo "CMD: {$cmd}\n\n";
exec($cmd, $raw);
if (empty($raw)) {
    echo "  (no files found)\n";
} else {
    foreach ($raw as $f) echo "  {$f}\n";
}

echo "\n=== FULL SCAN result ===\n";
$results = scan_wordpress_installations($home, $username);
echo "Total found: " . count($results) . "\n";
foreach ($results as $i => $s) {
    echo "\n[" . ($i+1) . "] {$s['blogname']} ({$s['siteurl']})\n";
    echo "    path   : {$s['path']}\n";
    echo "    domain : {$s['domain']}\n";
    echo "    subdir : {$s['subdir']}\n";
    echo "    version: {$s['version']}\n";
    echo "    status : {$s['status']}\n";
}
