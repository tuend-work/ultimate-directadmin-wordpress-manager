# Ultimate DirectAdmin WordPress Manager

Một plugin quản lý WordPress giao diện tối (glassmorphism dark) cao cấp, siêu nhẹ và nhanh dành cho DirectAdmin, cung cấp các tính năng tương tự như WP Toolkit. Plugin được xây dựng bằng HTML, Vanilla CSS và các file thực thi PHP CGI.

## ✨ Các tính năng nổi bật

- **Quét máy chủ (Hosting Scan)**: Tự động quét và phát hiện các thư mục cài đặt WordPress trong thư mục domains của người dùng (`~/domains/*/public_html/`).
- **Cài đặt WordPress 1-Click**: Tự động tải bộ cài WordPress Core, tạo khóa muối bảo mật (salt keys), tạo file `wp-config.php` và chạy thiết lập khởi tạo database chỉ trong vài giây.
- **Đăng nhập nhanh (Magic Login ⚡)**: Đăng nhập vào trang quản trị WordPress không cần mật khẩu. Plugin tự động tạo 1 tệp `mu-plugin` tạm thời trong thư mục `wp-content/mu-plugins/` và tự xóa sạch dấu vết (`unlink(__FILE__)`) ngay sau khi đăng nhập thành công.
- **Liên kết Database thông qua API DirectAdmin**: Tận dụng trực tiếp session đăng nhập của người dùng trên trình duyệt để gọi API DirectAdmin (`/CMD_API_DATABASES`), giúp tạo và xóa database tự động mà không cần lưu trữ hay hiển thị thông tin đăng nhập mật khẩu hosting.
- **Tự động Cập nhật từ GitHub (Dành cho Admin)**: Người quản trị DirectAdmin có thể nhấn nút cập nhật trực tiếp phiên bản mới nhất từ GitHub công khai, hệ thống sẽ tự động tải, ghi đè và đặt lại phân quyền tệp tin chính xác.

---

## 🚀 Cài đặt & Cập nhật nhanh (Qua SSH - Quyền Root)

Đăng nhập vào máy chủ DirectAdmin của bạn dưới quyền **root** thông qua SSH và chạy lệnh sau:

```bash
curl -sSL https://raw.githubusercontent.com/tuend-work/ultimate-directadmin-wordpress-manager/main/install.sh | bash
```

Script này sẽ tự động thực hiện:
1. Tải bản mã nguồn mới nhất từ nhánh `main` của dự án trên GitHub.
2. Giải nén vào thư mục `/usr/local/directadmin/plugins/ultimate-directadmin-wordpress-manager`.
3. Phân quyền sở hữu thư mục cho user hệ thống `diradmin:diradmin`.
4. Thiết lập quyền truy cập chính xác (`755` cho thư mục/scripts thực thi và `644` cho các tệp tĩnh).

---

## 📦 Cài đặt thủ công (Qua giao diện DirectAdmin)

Nếu bạn muốn cài đặt thông qua giao diện quản trị Admin của DirectAdmin:

1. Đóng gói thư mục plugin thành file nén `.tar.gz`:
   ```bash
   tar -czf ultimate-directadmin-wordpress-manager.tar.gz -C /path/to/plugin .
   ```
2. Đăng nhập vào DirectAdmin với tài khoản **Admin**.
3. Đi tới mục **Extra Features** > **Plugin Manager**.
4. Tải lên tệp `ultimate-directadmin-wordpress-manager.tar.gz` và nhấn **Install** (Cài đặt).

---

## 🛠️ Cấu hình cấu trúc Plugin

Cấu hình plugin được định nghĩa tại tệp [plugin.conf](plugin.conf):
```ini
active=yes
admin=yes
reseller=yes
user=yes
name=Ultimate WordPress Manager
version=1.0.2
author=tuend-work
update_url=https://raw.githubusercontent.com/tuend-work/ultimate-directadmin-wordpress-manager/main/plugin.conf
```

Plugin phân chia quyền hạn truy cập theo các thư mục tương ứng của DirectAdmin:
- **`admin/`**: Giao diện và API dành riêng cho Administrator (chứa bảng điều khiển cập nhật từ GitHub).
- **`reseller/`**: Giao diện dành cho Reseller.
- **`user/`**: Giao diện và API quản lý dành cho người dùng cuối (chứa tính năng quét hosting, cài đặt WP và Magic Login).
