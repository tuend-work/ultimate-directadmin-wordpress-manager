#!/bin/bash
# self_update.sh — Called by the SUID wrapper when admin clicks "Update Plugin"
# Runs as root via wrapper (setuid), downloads latest code from GitHub
# and replaces plugin files with correct diradmin ownership.

set -e

PLUGIN_DIR="/usr/local/directadmin/plugins/ultimate-directadmin-wordpress-manager"
TMP_DIR="/tmp/da_wp_manager_update_$$"
GITHUB_ZIP="https://github.com/tuend-work/ultimate-directadmin-wordpress-manager/archive/refs/heads/main.zip"

echo "[update] Preparing temp directory..."
rm -rf "$TMP_DIR"
mkdir -p "$TMP_DIR"

echo "[update] Downloading latest version from GitHub..."
curl -sSL --connect-timeout 30 --max-time 120 \
     -A "DirectAdmin-WordPress-Manager-Updater" \
     "$GITHUB_ZIP" -o "$TMP_DIR/plugin.zip"

if [ ! -f "$TMP_DIR/plugin.zip" ] || [ ! -s "$TMP_DIR/plugin.zip" ]; then
    echo "[update] ERROR: Download failed or empty file."
    rm -rf "$TMP_DIR"
    exit 1
fi

echo "[update] Extracting archive..."
unzip -q "$TMP_DIR/plugin.zip" -d "$TMP_DIR"

EXTRACTED_DIR=$(find "$TMP_DIR" -maxdepth 1 -type d -name "ultimate-directadmin-wordpress-manager-*" | head -n 1)
if [ -z "$EXTRACTED_DIR" ]; then
    echo "[update] ERROR: Extracted directory not found."
    rm -rf "$TMP_DIR"
    exit 1
fi

echo "[update] Copying new files to plugin directory..."
# Preserve php.ini (custom config, do not overwrite)
if [ -f "$PLUGIN_DIR/php.ini" ]; then
    cp -f "$PLUGIN_DIR/php.ini" "$TMP_DIR/php.ini.bak"
fi

cp -rf "$EXTRACTED_DIR"/* "$PLUGIN_DIR/"

# Restore php.ini if it existed
if [ -f "$TMP_DIR/php.ini.bak" ]; then
    cp -f "$TMP_DIR/php.ini.bak" "$PLUGIN_DIR/php.ini"
fi

echo "[update] Setting ownership and permissions..."
chown -R diradmin:diradmin "$PLUGIN_DIR"
find "$PLUGIN_DIR" -type d -exec chmod 755 {} \;
find "$PLUGIN_DIR" -type f -exec chmod 644 {} \;

# Fix line endings
find "$PLUGIN_DIR" -type f \( -name "*.sh" -o -name "*.php" -o -name "*.conf" -o -name "*.html" -o -name "*.raw" \) \
    -exec sed -i 's/\r$//' {} \;

# Restore executable bits
chmod 755 "$PLUGIN_DIR/scripts/"*.sh 2>/dev/null || true
chmod 755 "$PLUGIN_DIR/admin/index.html"   2>/dev/null || true
chmod 755 "$PLUGIN_DIR/admin/index.raw"    2>/dev/null || true
chmod 755 "$PLUGIN_DIR/reseller/index.html" 2>/dev/null || true
chmod 755 "$PLUGIN_DIR/reseller/index.raw"  2>/dev/null || true
chmod 755 "$PLUGIN_DIR/user/index.html"    2>/dev/null || true
chmod 755 "$PLUGIN_DIR/user/index.raw"     2>/dev/null || true

# Maintain SUID permissions for both binary wrappers
for binary in wrapper update_wrapper; do
    if [ -f "$PLUGIN_DIR/scripts/$binary" ]; then
        chown root:diradmin "$PLUGIN_DIR/scripts/$binary"
        chmod 4755 "$PLUGIN_DIR/scripts/$binary"
    fi
done

chmod 755 "$PLUGIN_DIR/scripts/read_log.sh" 2>/dev/null || true
chmod 755 "$PLUGIN_DIR/scripts/self_update.sh" 2>/dev/null || true
chmod 755 "$PLUGIN_DIR/scripts/delegate.sh" 2>/dev/null || true

echo "[update] Cleanup..."
rm -rf "$TMP_DIR"

echo "[update] Done. Plugin updated successfully."
exit 0
