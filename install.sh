#!/bin/bash
# Install script for Ultimate DirectAdmin WordPress Manager
# Run: curl -sSL https://raw.githubusercontent.com/tuend-work/ultimate-directadmin-wordpress-manager/main/install.sh | bash

# Ensure the script is run as root
if [ "$EUID" -ne 0 ]; then
  echo -e "\e[31mError: This script must be run as root.\e[0m"
  exit 1
fi

PLUGIN_DIRS=(
  "/usr/local/directadmin/plugins/ultimate-directadmin-wordpress-manager"
  "/usr/local/directadmin/plugins/ultimate_da_wordpress_manager"
)
TMP_DIR="/tmp/da_wp_manager_install"

# Auto-install unzip if missing
if ! command -v unzip &> /dev/null; then
  echo -e "\e[33m[1/5] unzip utility is missing. Installing unzip...\e[0m"
  if command -v yum &> /dev/null; then
    yum install -y unzip
  elif command -v apt-get &> /dev/null; then
    apt-get update && apt-get install -y unzip
  fi
fi

echo -e "\e[34m[2/5] Preparing directories...\e[0m"
rm -rf "$TMP_DIR"
mkdir -p "$TMP_DIR"

echo -e "\e[34m[3/5] Downloading latest code from GitHub...\e[0m"
curl -sSL https://github.com/tuend-work/ultimate-directadmin-wordpress-manager/archive/refs/heads/main.zip -o "$TMP_DIR/plugin.zip"

if [ ! -f "$TMP_DIR/plugin.zip" ]; then
  echo -e "\e[31mError: Failed to download source code from GitHub.\e[0m"
  exit 1
fi

echo -e "\e[34m[4/5] Extracting plugin files...\e[0m"
unzip -q "$TMP_DIR/plugin.zip" -d "$TMP_DIR"
EXTRACTED_DIR=$(find "$TMP_DIR" -maxdepth 1 -type d -name "ultimate-directadmin-wordpress-manager-*" | head -n 1)

if [ -z "$EXTRACTED_DIR" ]; then
  echo -e "\e[31mError: Failed to locate extracted directory.\e[0m"
  rm -rf "$TMP_DIR"
  exit 1
fi

for PLUGIN_DIR in "${PLUGIN_DIRS[@]}"; do
  echo -e "\e[34mInstalling to $PLUGIN_DIR...\e[0m"
  
  # Ensure target directory exists and is clean
  mkdir -p "$PLUGIN_DIR"
  rm -rf "$PLUGIN_DIR"/*
  cp -rf "$EXTRACTED_DIR"/* "$PLUGIN_DIR/"

  # Copy custom php.ini from resource manager template if exists
  if [ -f "/usr/local/directadmin/plugins/ultimate_da_resource_manager/php.ini" ]; then
    cp -f "/usr/local/directadmin/plugins/ultimate_da_resource_manager/php.ini" "$PLUGIN_DIR/php.ini"
  else
    # Fallback to default php.ini
    cp -f /usr/local/lib/php.ini "$PLUGIN_DIR/php.ini" 2>/dev/null
  fi

  echo -e "\e[34mConfiguring ownership and permissions for $PLUGIN_DIR...\e[0m"
  # Change ownership to diradmin:diradmin
  chown -R diradmin:diradmin "$PLUGIN_DIR"

  # Set standard permissions
  find "$PLUGIN_DIR" -type d -exec chmod 755 {} \;
  find "$PLUGIN_DIR" -type f -exec chmod 644 {} \;

  # Remove Windows carriage returns (\r) to convert files to Unix format (fixing shebang load errors)
  find "$PLUGIN_DIR" -type f \( -name "*.sh" -o -name "*.html" -o -name "*.raw" -o -name "*.php" -o -name "*.conf" \) -exec sed -i 's/\r$//' {} \;

  # Set executable permissions for scripts and panel entry points
  chmod 755 "$PLUGIN_DIR"/scripts/*.sh 2>/dev/null
  chmod 755 "$PLUGIN_DIR/scripts/self_update.sh" 2>/dev/null
  chmod 755 "$PLUGIN_DIR"/admin/index.html 2>/dev/null
  chmod 755 "$PLUGIN_DIR"/admin/index.raw 2>/dev/null
  chmod 755 "$PLUGIN_DIR"/reseller/index.html 2>/dev/null
  chmod 755 "$PLUGIN_DIR"/reseller/index.raw 2>/dev/null
  chmod 755 "$PLUGIN_DIR"/user/index.html 2>/dev/null
  chmod 755 "$PLUGIN_DIR"/user/index.raw 2>/dev/null

  echo -e "\e[34mCompiling secure SUID wrappers for $PLUGIN_DIR...\e[0m"
  for binary in wrapper update_wrapper; do
    if [ -f "$PLUGIN_DIR/scripts/${binary}.c" ]; then
      gcc -O2 "$PLUGIN_DIR/scripts/${binary}.c" -o "$PLUGIN_DIR/scripts/${binary}"
      if [ -f "$PLUGIN_DIR/scripts/${binary}" ]; then
        chown root:diradmin "$PLUGIN_DIR/scripts/${binary}"
        chmod 4755 "$PLUGIN_DIR/scripts/${binary}"
        echo -e "\e[32m✔ ${binary} compiled and SUID permissions configured successfully.\e[0m"
      else
        echo -e "\e[31mError: Failed to compile ${binary} binary.\e[0m"
      fi
    fi
  done
done

# Cleanup
rm -rf "$TMP_DIR"

echo -e "\e[32m✔ Success: Ultimate DirectAdmin WordPress Manager installed successfully!\e[0m"
echo -e "\e[32mYou can now access it under the Extra Features section in DirectAdmin.\e[0m"
