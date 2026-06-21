#!/bin/bash
# read_log.sh - Chạy dưới quyền root để đọc trực tiếp file log hệ thống và in ra stdout
# Hỗ trợ cả domain chính và subdomain theo định dạng DirectAdmin:
#   - Domain chính:  /var/log/nginx/domains/{domain}.log
#   - Subdomain:     /var/log/nginx/domains/{parent}.{sub}.log
#                    (ví dụ: sub.domain.com → domain.com.sub.log)

USER=$1
DOMAIN=$2
LOG_TYPE=$3  # access hoặc error
LINES=$4

if [ -z "$USER" ] || [ -z "$DOMAIN" ] || [ -z "$LOG_TYPE" ] || [ -z "$LINES" ]; then
    echo "Error: Missing arguments."
    exit 1
fi

LOG_FILE=""

# Tính toán tên file log theo định dạng DirectAdmin subdomain:
# sub.domain.com → FIRST_PART=sub, PARENT_DOMAIN=domain.com
FIRST_PART="${DOMAIN%%.*}"
PARENT_DOMAIN="${DOMAIN#*.}"
# Chỉ áp dụng nếu đây thực sự là subdomain (FIRST_PART != PARENT_DOMAIN)
if [ "$FIRST_PART" = "$PARENT_DOMAIN" ]; then
    PARENT_DOMAIN=""
fi

try_find_log() {
    local base_domain="$1"
    local log_dirs="/var/log/nginx/domains /var/log/httpd/domains /var/log/openlitespeed/domains"

    if [ "$LOG_TYPE" = "access" ]; then
        for dir in $log_dirs; do
            # Thư mục riêng của domain (ví dụ: /var/log/nginx/domains/{domain}/)
            for f in "$dir/$base_domain/access.log" "$dir/$base_domain/$base_domain.log"; do
                if [ -f "$f" ]; then echo "$f"; return; fi
            done
            # File log phẳng nằm thẳng trong thư mục domains/
            for f in "$dir/$base_domain.log" "$dir/$base_domain.access.log"; do
                if [ -f "$f" ]; then echo "$f"; return; fi
            done
        done
    else
        for dir in $log_dirs; do
            for f in "$dir/$base_domain/error.log" "$dir/$base_domain/$base_domain.error.log"; do
                if [ -f "$f" ]; then echo "$f"; return; fi
            done
            for f in "$dir/$base_domain.error.log"; do
                if [ -f "$f" ]; then echo "$f"; return; fi
            done
        done
    fi
}

# 1. Thử với tên domain như truyền vào (domain chính hoặc full subdomain)
LOG_FILE=$(try_find_log "$DOMAIN")

# 2. Nếu là subdomain, thử định dạng DirectAdmin đảo ngược: {parent}.{sub}.log
#    ví dụ: sub.domain.com → /var/log/nginx/domains/domain.com.sub.log
if [ -z "$LOG_FILE" ] && [ -n "$PARENT_DOMAIN" ]; then
    DA_SUBDOMAIN_NAME="${PARENT_DOMAIN}.${FIRST_PART}"
    LOG_FILE=$(try_find_log "$DA_SUBDOMAIN_NAME")
fi

# 3. Fallback: quét rộng bằng find (phòng trường hợp log format lạ)
if [ -z "$LOG_FILE" ]; then
    LOG_DIRS="/var/log/nginx/domains /var/log/httpd/domains"
    if [ "$LOG_TYPE" = "access" ]; then
        LOG_FILE=$(find $LOG_DIRS \
            \( -name "${DOMAIN}.log" \
            -o -name "${DOMAIN}.access.log" \
            -o -name "access.log" -path "*/${DOMAIN}/*" \) \
            2>/dev/null | head -n 1)
        # Thêm tìm kiếm theo định dạng subdomain đảo ngược
        if [ -z "$LOG_FILE" ] && [ -n "$PARENT_DOMAIN" ]; then
            LOG_FILE=$(find $LOG_DIRS \
                \( -name "${PARENT_DOMAIN}.${FIRST_PART}.log" \
                -o -name "${PARENT_DOMAIN}.${FIRST_PART}.access.log" \) \
                2>/dev/null | head -n 1)
        fi
    else
        LOG_FILE=$(find $LOG_DIRS \
            \( -name "${DOMAIN}.error.log" \
            -o -name "error.log" -path "*/${DOMAIN}/*" \) \
            2>/dev/null | head -n 1)
        if [ -z "$LOG_FILE" ] && [ -n "$PARENT_DOMAIN" ]; then
            LOG_FILE=$(find $LOG_DIRS \
                -name "${PARENT_DOMAIN}.${FIRST_PART}.error.log" \
                2>/dev/null | head -n 1)
        fi
    fi
fi

if [ -n "$LOG_FILE" ] && [ -f "$LOG_FILE" ]; then
    tail -n "$LINES" "$LOG_FILE"
else
    echo "[Hệ thống] Tệp tin log cho tên miền $DOMAIN không tồn tại hoặc chưa có dữ liệu ghi nhận."
    exit 1
fi
