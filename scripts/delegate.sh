#!/bin/bash
# delegate.sh - Handles cross-user SUID delegation requests

USER=$1
DOMAIN=$2
LOG_TYPE=$3
LINES=$4

if [[ "$DOMAIN" == run-as.* ]]; then
    TARGET_USER="$USER"
    TARGET_SCRIPT="/usr/local/directadmin/plugins/ultimate-directadmin-wordpress-manager/user/index.raw"
    
    # Run the php script via runuser (or su fallback), explicitly passing environment variables
    # and forcing /bin/bash shell to bypass accounts that have /bin/false or /sbin/nologin shells.
    CMD="export USERNAME='$TARGET_USER' USER='$TARGET_USER' HOME='/home/$TARGET_USER' QUERY_STRING='$QUERY_STRING' POST='$POST'; /usr/local/bin/php -nc /usr/local/directadmin/plugins/ultimate-directadmin-wordpress-manager/php.ini '$TARGET_SCRIPT'"
    
    if command -v runuser >/dev/null 2>&1; then
        runuser -s /bin/bash "$TARGET_USER" -c "$CMD"
    else
        su -s /bin/bash "$TARGET_USER" -c "$CMD"
    fi
    exit 0
fi

if [[ "$DOMAIN" == run-as-root.* ]]; then
    TARGET_USER="$USER"
    TARGET_SCRIPT="/usr/local/directadmin/plugins/ultimate-directadmin-wordpress-manager/user/index.raw"
    
    # Run the php script directly as root without dropping privileges
    export USERNAME="$TARGET_USER"
    export USER="$TARGET_USER"
    export HOME="/home/$TARGET_USER"
    export QUERY_STRING="$QUERY_STRING"
    export POST="$POST"
    
    /usr/local/bin/php -nc /usr/local/directadmin/plugins/ultimate-directadmin-wordpress-manager/php.ini "$TARGET_SCRIPT"
    exit 0
fi
