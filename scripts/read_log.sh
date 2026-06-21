#!/bin/bash
# read_log.sh - Chạy dưới quyền root để đọc trực tiếp file log hệ thống và in ra stdout

USER=$1
DOMAIN=$2
LOG_TYPE=$3  # access hoặc error
LINES=$4

if [ -z "$USER" ] || [ -z "$DOMAIN" ] || [ -z "$LOG_TYPE" ] || [ -z "$LINES" ]; then
    echo "Error: Missing arguments."
    exit 1
fi

LOG_FILE=""

# 1. Quét thư mục log riêng của domain do DirectAdmin cấu hình (Apache/OLS/Nginx)
if [ -d "/var/log/httpd/domains/$DOMAIN" ]; then
    if [ "$LOG_TYPE" = "access" ]; then
        for f in "/var/log/httpd/domains/$DOMAIN/access.log" "/var/log/httpd/domains/$DOMAIN/$DOMAIN.log"; do
            if [ -f "$f" ]; then LOG_FILE="$f"; break; fi
        done
    else
        for f in "/var/log/httpd/domains/$DOMAIN/error.log" "/var/log/httpd/domains/$DOMAIN/$DOMAIN.error.log"; do
            if [ -f "$f" ]; then LOG_FILE="$f"; break; fi
        done
    fi
elif [ -d "/var/log/nginx/domains/$DOMAIN" ]; then
    if [ "$LOG_TYPE" = "access" ]; then
        for f in "/var/log/nginx/domains/$DOMAIN/access.log" "/var/log/nginx/domains/$DOMAIN/$DOMAIN.log"; do
            if [ -f "$f" ]; then LOG_FILE="$f"; break; fi
        done
    else
        for f in "/var/log/nginx/domains/$DOMAIN/error.log" "/var/log/nginx/domains/$DOMAIN/$DOMAIN.error.log"; do
            if [ -f "$f" ]; then LOG_FILE="$f"; break; fi
        done
    fi
fi

# 2. Quét file log trực tiếp trong domains/
if [ -z "$LOG_FILE" ]; then
    if [ "$LOG_TYPE" = "access" ]; then
        for f in "/var/log/nginx/domains/$DOMAIN.log" "/var/log/httpd/domains/$DOMAIN.log" "/var/log/nginx/domains/$DOMAIN.access.log"; do
            if [ -f "$f" ]; then LOG_FILE="$f"; break; fi
        done
    else
        for f in "/var/log/nginx/domains/$DOMAIN.error.log" "/var/log/httpd/domains/$DOMAIN.error.log"; do
            if [ -f "$f" ]; then LOG_FILE="$f"; break; fi
        done
    fi
fi

# 3. Quét rộng hơn bằng find
if [ -z "$LOG_FILE" ]; then
    if [ "$LOG_TYPE" = "access" ]; then
        LOG_FILE=$(find /var/log/nginx /var/log/httpd -name "$DOMAIN.log" -o -name "$DOMAIN.access.log" -o -name "access.log" -path "*/$DOMAIN/*" 2>/dev/null | head -n 1)
    else
        LOG_FILE=$(find /var/log/nginx /var/log/httpd -name "$DOMAIN.error.log" -o -name "error.log" -path "*/$DOMAIN/*" 2>/dev/null | head -n 1)
    fi
fi

if [ -n "$LOG_FILE" ] && [ -f "$LOG_FILE" ]; then
    tail -n "$LINES" "$LOG_FILE"
else
    echo "[Hệ thống] Tệp tin log cho tên miền $DOMAIN không tồn tại hoặc chưa có dữ liệu ghi nhận."
    exit 1
fi
