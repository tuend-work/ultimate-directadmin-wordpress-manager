#!/bin/bash
# Install script for Ultimate DirectAdmin WordPress Manager
# Run: curl -sSL https://raw.githubusercontent.com/tuend-work/ultimate-directadmin-wordpress-manager/main/install.sh | bash

# Ensure the script is run as root
if [ "$EUID" -ne 0 ]; then
  echo -e "\e[31mError: This script must be run as root.\e[0m"
  exit 1
fi

PLUGIN_DIR="/usr/local/directadmin/plugins/ultimate-directadmin-wordpress-manager"
TMP_DIR="/tmp/da_wp_manager_install"

echo -e "\e[34m[1/4] Preparing directories...\e[0m"
rm -rf "$TMP_DIR"
mkdir -p "$TMP_DIR"

echo -e "\e[34m[2/4] Downloading latest code from GitHub...\e[0m"
curl -sSL https://github.com/tuend-work/ultimate-directadmin-wordpress-manager/archive/refs/heads/main.zip -o "$TMP_DIR/plugin.zip"

if [ ! -f "$TMP_DIR/plugin.zip" ]; then
  echo -e "\e[31mError: Failed to download source code from GitHub.\e[0m"
  exit 1
fi

echo -e "\e[34m[3/4] Extracting plugin files...\e[0m"
unzip -q "$TMP_DIR/plugin.zip" -d "$TMP_DIR"
EXTRACTED_DIR=$(find "$TMP_DIR" -maxdepth 1 -type d -name "ultimate-directadmin-wordpress-manager-*" | head -n 1)

if [ -z "$EXTRACTED_DIR" ]; then
  echo -e "\e[31mError: Failed to locate extracted directory.\e[0m"
  rm -rf "$TMP_DIR"
  exit 1
fi

# Ensure target directory exists and is clean
mkdir -p "$PLUGIN_DIR"
rm -rf "$PLUGIN_DIR"/*
cp -rf "$EXTRACTED_DIR"/* "$PLUGIN_DIR/"

echo -e "\e[34m[4/4] Configuring ownership and permissions...\e[0m"
# Change ownership to diradmin:diradmin
chown -R diradmin:diradmin "$PLUGIN_DIR"

# Set standard permissions
find "$PLUGIN_DIR" -type d -exec chmod 755 {} \;
find "$PLUGIN_DIR" -type f -exec chmod 644 {} \;

# Set executable permissions for scripts and panel entry points
chmod 755 "$PLUGIN_DIR"/scripts/*.sh 2>/dev/null
chmod 755 "$PLUGIN_DIR"/admin/index.html 2>/dev/null
chmod 755 "$PLUGIN_DIR"/admin/index.raw 2>/dev/null
chmod 755 "$PLUGIN_DIR"/reseller/index.html 2>/dev/null
chmod 755 "$PLUGIN_DIR"/reseller/index.raw 2>/dev/null
chmod 755 "$PLUGIN_DIR"/user/index.html 2>/dev/null
chmod 755 "$PLUGIN_DIR"/user/index.raw 2>/dev/null

# Cleanup
rm -rf "$TMP_DIR"

echo -e "\e[32m✔ Success: Ultimate DirectAdmin WordPress Manager installed successfully!\e[0m"
echo -e "\e[32mYou can now access it under the Extra Features section in DirectAdmin.\e[0m"
