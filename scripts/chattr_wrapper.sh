#!/bin/bash

# Ensure it's run as root via sudo
if [ -z "$SUDO_USER" ] || [ "$EUID" -ne 0 ]; then
  echo "Error: This script must be run via sudo by a normal user."
  exit 1
fi

ACTION="$1" # "lock" or "unlock"
TARGET_PATH="$2"

if [ "$ACTION" != "lock" ] && [ "$ACTION" != "unlock" ]; then
  echo "Error: Invalid action. Use 'lock' or 'unlock'."
  exit 1
fi

# Resolve the absolute path
REAL_PATH=$(readlink -f "$TARGET_PATH")

# Safety check: path must be inside the user's home directory
USER_HOME="/home/$SUDO_USER"
if [[ "$REAL_PATH" != "$USER_HOME"/* ]]; then
  echo "Error: Access denied. Path must be inside your home directory ($USER_HOME)."
  exit 1
fi

# Safety check: must contain wp-config.php to confirm it's a WordPress folder
if [ ! -f "$REAL_PATH/wp-config.php" ]; then
  echo "Error: Safety block. wp-config.php not found at $REAL_PATH."
  exit 1
fi

# Find chattr command
CHATTR_CMD=$(which chattr 2>/dev/null || echo "/usr/bin/chattr")

if [ "$ACTION" == "lock" ]; then
  "$CHATTR_CMD" +i "$REAL_PATH/wp-config.php" 2>/dev/null
  "$CHATTR_CMD" -R +i "$REAL_PATH/wp-includes" 2>/dev/null
  "$CHATTR_CMD" -R +i "$REAL_PATH/wp-admin" 2>/dev/null
  "$CHATTR_CMD" -R +i "$REAL_PATH/wp-content/plugins" 2>/dev/null
  "$CHATTR_CMD" -R +i "$REAL_PATH/wp-content/themes" 2>/dev/null
  echo "Successfully locked WordPress files at $REAL_PATH"
else
  "$CHATTR_CMD" -i "$REAL_PATH/wp-config.php" 2>/dev/null
  "$CHATTR_CMD" -R -i "$REAL_PATH/wp-includes" 2>/dev/null
  "$CHATTR_CMD" -R -i "$REAL_PATH/wp-admin" 2>/dev/null
  "$CHATTR_CMD" -R -i "$REAL_PATH/wp-content/plugins" 2>/dev/null
  "$CHATTR_CMD" -R -i "$REAL_PATH/wp-content/themes" 2>/dev/null
  echo "Successfully unlocked WordPress files at $REAL_PATH"
fi
