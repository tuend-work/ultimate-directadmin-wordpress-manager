#!/bin/bash
set -e

# Arguments
USERNAME=$1
SITE_PATH=$2

if [ -z "$USERNAME" ] || [ -z "$SITE_PATH" ]; then
    echo "Usage: $0 <username> <site_path>"
    exit 1
fi

# Ensure user exists
if ! id "$USERNAME" >/dev/null 2>&1; then
    echo "Error: User $USERNAME does not exist."
    exit 1
fi

# Ensure site path exists and contains wp-config.php
if [ ! -d "$SITE_PATH" ] || [ ! -f "$SITE_PATH/wp-config.php" ]; then
    echo "Error: Invalid site path or wp-config.php missing."
    exit 1
fi

echo "[fix_permissions] Resetting owner to $USERNAME:$USERNAME for $SITE_PATH..."
chown -R "$USERNAME:$USERNAME" "$SITE_PATH"

echo "[fix_permissions] Setting directory permissions to 755..."
find "$SITE_PATH" -type d -exec chmod 755 {} +

echo "[fix_permissions] Setting file permissions to 644..."
find "$SITE_PATH" -type f -exec chmod 644 {} +

echo "[fix_permissions] Securing wp-config.php to 600..."
if [ -f "$SITE_PATH/wp-config.php" ]; then
    chmod 600 "$SITE_PATH/wp-config.php"
fi

echo "[fix_permissions] Securing .htaccess if exists to 644..."
if [ -f "$SITE_PATH/.htaccess" ]; then
    chmod 644 "$SITE_PATH/.htaccess"
fi

echo "Success: Permissions fixed for $USERNAME at $SITE_PATH"
exit 0
