#!/bin/bash
# setup_logs.sh - Chạy dưới quyền root để tạo symlink và phân quyền log cho user

USER=$1
DOMAIN=$2
SITE_PATH=$3

# 1. Validate inputs
if [ -z "$USER" ] || [ -z "$DOMAIN" ] || [ -z "$SITE_PATH" ]; then
    echo "Error: Missing arguments."
    exit 1
fi

# 2. Check if user exists
if ! id "$USER" >/dev/null 2>&1; then
    echo "Error: User $USER does not exist."
    exit 1
fi

USER_HOME=$(eval echo "~$USER")
USER_LOGS_DIR="$USER_HOME/domains/$DOMAIN/logs"

# Tự động tạo thư mục logs của user nếu chưa có
if [ ! -d "$USER_LOGS_DIR" ]; then
    mkdir -p "$USER_LOGS_DIR"
    # Lấy group của user
    USER_GID=$(id -g "$USER")
    chown "$USER:$USER_GID" "$USER_LOGS_DIR"
    chmod 755 "$USER_LOGS_DIR"
fi

# 3. Detect Log Files based on Web Server
SYSTEM_ACCESS_LOG=""
SYSTEM_ERROR_LOG=""

# Quét Nginx
if [ -f "/var/log/nginx/domains/$DOMAIN.log" ]; then
    SYSTEM_ACCESS_LOG="/var/log/nginx/domains/$DOMAIN.log"
    SYSTEM_ERROR_LOG="/var/log/nginx/domains/$DOMAIN.error.log"
# Quét Apache / OpenLiteSpeed
elif [ -f "/var/log/httpd/domains/$DOMAIN.log" ]; then
    SYSTEM_ACCESS_LOG="/var/log/httpd/domains/$DOMAIN.log"
    SYSTEM_ERROR_LOG="/var/log/httpd/domains/$DOMAIN.error.log"
fi

# Nếu không quét thấy ở các đường dẫn mặc định trên, thử tìm file log bất kỳ trong /var/log/nginx/ hoặc /var/log/httpd/ chứa tên domain
if [ -z "$SYSTEM_ACCESS_LOG" ]; then
    SYSTEM_ACCESS_LOG=$(find /var/log/nginx /var/log/httpd -name "$DOMAIN.log" -o -name "$DOMAIN.access.log" 2>/dev/null | head -n 1)
    SYSTEM_ERROR_LOG=$(find /var/log/nginx /var/log/httpd -name "$DOMAIN.error.log" 2>/dev/null | head -n 1)
fi

# Hàm cấp quyền đi xuyên qua các thư mục cha cho user để đọc được file log gốc
grant_parent_dirs_x() {
    local log_file="$1"
    local u="$2"
    local dir
    dir=$(dirname "$log_file")
    
    # Đi ngược từ thư mục cha lên đến /var hoặc / (nhưng dừng lại ở /var, /usr)
    while [ "$dir" != "/" ] && [ "$dir" != "." ] && [ "$dir" != "/var" ] && [ "$dir" != "/usr" ]; do
        if [ -d "$dir" ]; then
            if command -v setfacl >/dev/null 2>&1; then
                setfacl -m "u:$u:x" "$dir" 2>/dev/null || true
            else
                chmod o+x "$dir" 2>/dev/null || true
            fi
        fi
        dir=$(dirname "$dir")
    done
}

# 4. Tạo symlink và setfacl/chmod
if [ -n "$SYSTEM_ACCESS_LOG" ] && [ -f "$SYSTEM_ACCESS_LOG" ]; then
    # Cấp quyền đi xuyên qua các thư mục cha
    grant_parent_dirs_x "$SYSTEM_ACCESS_LOG" "$USER"

    # Tạo symlink access log
    USER_ACCESS_LINK="$USER_LOGS_DIR/$DOMAIN.log"
    if [ ! -L "$USER_ACCESS_LINK" ] && [ ! -f "$USER_ACCESS_LINK" ]; then
        ln -s "$SYSTEM_ACCESS_LOG" "$USER_ACCESS_LINK"
        chown -h "$USER" "$USER_ACCESS_LINK"
    fi
    
    # Phân quyền đọc cho user
    if command -v setfacl >/dev/null 2>&1; then
        setfacl -m "u:$USER:r" "$SYSTEM_ACCESS_LOG"
    else
        chmod o+r "$SYSTEM_ACCESS_LOG"
    fi
fi

if [ -n "$SYSTEM_ERROR_LOG" ] && [ -f "$SYSTEM_ERROR_LOG" ]; then
    # Cấp quyền đi xuyên qua các thư mục cha
    grant_parent_dirs_x "$SYSTEM_ERROR_LOG" "$USER"

    # Tạo symlink error log
    USER_ERROR_LINK="$USER_LOGS_DIR/$DOMAIN.error.log"
    if [ ! -L "$USER_ERROR_LINK" ] && [ ! -f "$USER_ERROR_LINK" ]; then
        ln -s "$SYSTEM_ERROR_LOG" "$USER_ERROR_LINK"
        chown -h "$USER" "$USER_ERROR_LINK"
    fi
    
    # Phân quyền đọc cho user
    if command -v setfacl >/dev/null 2>&1; then
        setfacl -m "u:$USER:r" "$SYSTEM_ERROR_LOG"
    else
        chmod o+r "$SYSTEM_ERROR_LOG"
    fi
fi

echo "Success: Symlinks and permissions configured for domain $DOMAIN."
exit 0
