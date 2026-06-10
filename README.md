# Ultimate DirectAdmin WordPress Manager

Một plugin quản lý WordPress cao cấp dành cho DirectAdmin, mang đến trải nghiệm quản trị mạnh mẽ, trực quan tương tự như Plesk WP Toolkit hoặc cPanel WordPress Manager. Plugin được thiết kế với giao diện tối hiện đại (Glassmorphism Dark Mode), vận hành cực nhanh, tối ưu hóa tài nguyên hệ thống và không yêu cầu daemon chạy ngầm.

---

## 🚀 Các Tính Năng Chi Tiết

### 1. Quét & Phát Hiện Website (Hosting Scan)
- **Quét thông minh**: Tự động duyệt qua tất cả thư mục thuộc quyền quản lý của User (`~/domains/*/public_html/`) để nhận diện các website chạy WordPress.
- **Bộ nhớ đệm thông minh (Caching)**: Lưu kết quả quét vào file ẩn `.ultimate_wp_manager.json` trong thư mục Home của User để tối ưu tốc độ tải trang cho những lần truy cập sau.
- **Tự động xác thực**: Xác minh trạng thái hoạt động thực tế của từng mã nguồn.

### 2. Cài Đặt WordPress 1-Click (WordPress Installer)
- **Cài đặt mới (Fresh Install)**: Tự động tải phiên bản WordPress mới nhất từ WordPress.org, tạo file `wp-config.php`, sinh ngẫu nhiên mã muối Salt Keys bảo mật.
- **Cài đặt từ tệp sao lưu (Install from ZIP)**: Cho phép quét và chọn tệp nén `.zip` lưu trên hosting (chứa mã nguồn và tệp cơ sở dữ liệu dạng `.sql` hoặc `.sql.gz`) để bung nhanh một website có sẵn.
- **Tự động hóa Database**: Gọi trực tiếp đến API DirectAdmin (`/CMD_API_DATABASES`) dưới session hiện tại của người dùng để tạo Database và User DB mới, loại bỏ nguy cơ rò rỉ thông tin mật khẩu quản trị hosting.
- **Đồng bộ liên kết**: Tự động thêm cấu hình động vào `wp-config.php` để tương thích mọi tên miền chạy thực tế:
  ```php
  define('WP_SITEURL', 'https://' . $_SERVER['HTTP_HOST'] . $subdir);
  define('WP_HOME', 'https://' . $_SERVER['HTTP_HOST'] . $subdir);
  ```

### 3. Đăng Nhập Nhanh Không Mật Khẩu (Magic Login ⚡)
- Đăng nhập trực tiếp vào trang quản trị `wp-admin` của bất kỳ website nào chỉ với một cú click chuột.
- **Cơ chế hoạt động**: Tạo tệp tin `mu-plugin` tạm thời trong thư mục `wp-content/mu-plugins/` để tự động xác thực và bỏ qua trang đăng nhập của WordPress, tệp tin này sẽ tự động xóa sạch dấu vết (`@unlink(__FILE__)`) ngay sau khi đăng nhập thành công để đảm bảo bảo mật tuyệt đối.

### 4. Sao Chép Website (Clone Website)
- **Sao chép thư mục**: Copy đệ quy toàn bộ thư mục mã nguồn sang thư mục đích mới.
- **Loại trừ dữ liệu thừa**: Tự động bỏ qua thư mục cache `wp-content/cache` giúp rút ngắn thời gian sao chép và tránh làm nặng dung lượng đĩa cứng.
- **Sao chép dữ liệu & Chuyển đổi liên kết**: Tự động dump database nguồn, tạo database mới ở đích, import dữ liệu và tự động chạy truy vấn Tìm kiếm & Thay thế (Search & Replace) các liên kết (URL) cũ sang URL mới trên các bảng `options`, `posts`, `postmeta`.
- **Cơ chế an toàn (Safety features)**:
  - Tự động phát hiện và chặn các trường hợp đệ quy vô hạn (ví dụ: Clone một website vào chính thư mục con của nó như `laophatgia.net/clone`).
  - Khi clone thất bại, hệ thống chỉ dọn dẹp các tệp tin lỗi bên trong thư mục đích và **luôn giữ nguyên thư mục gốc của domain đích** (không xóa thư mục `public_html` gốc).
  - Tự động cấp quyền ghi tạm thời `chmod 0644` vào tệp `wp-config.php` trước khi cấu hình và khôi phục về quyền an toàn `chmod 0600` sau khi hoàn tất.

### 5. Khóa Website Chống Mã Độc (WordPress Lockdown)
- Cho phép bật/tắt chế độ khóa bảo vệ website.
- Khi được kích hoạt, plugin sẽ thực hiện phân quyền hạn chế (Read-only) đối với các thư mục cốt lõi của WordPress (`wp-admin`, `wp-includes`, `wp-config.php`), ngăn chặn tin tặc tải lên hoặc chỉnh sửa tệp tin trái phép nhằm chèn backdoor/mã độc.

### 6. Quản Lý Plugins & Themes Trực Tiếp
- **Plugins**: Liệt kê chi tiết danh sách, phiên bản hiện tại, phiên bản mới nhất, tác giả và trạng thái. Hỗ trợ kích hoạt, hủy kích hoạt, cập nhật lên phiên bản mới nhất, xóa bỏ hoặc **cài đặt lại (reinstall)** trực tiếp từ kho WordPress.org.
- **Themes**: Quản lý danh sách giao diện, kích hoạt giao diện, cập nhật, xóa bỏ, và cài đặt lại bản gốc từ WordPress.org.

### 7. Tối Ưu Hóa & Cấu Hình Nhanh
- **WP Debug (Phát Triển)**: Bật/tắt nhanh các chế độ gỡ lỗi `WP_DEBUG`, `WP_DEBUG_LOG`, và `WP_DEBUG_DISPLAY` trực tiếp trong tệp cấu hình `wp-config.php`.
- **WP Cron**: Bật/tắt tác vụ chạy ngầm tự động (Cron job) của WordPress.
- **Tự động cập nhật**: Thiết lập tắt/bật tính năng tự động cập nhật WordPress Core.

### 8. Thắt Chặt Bảo Mật Với 18 Biện Pháp (Security Hardening Status)
Plugin cung cấp 18 cấu hình thắt chặt bảo mật chuẩn quốc tế, tự động chỉnh sửa file `.htaccess` hoặc `wp-config.php`:
1. Hạn chế quyền hạn tệp cấu hình `wp-config.php` (Chmod về quyền đọc tối thiểu 0400/0600).
2. Tạo mới/thay đổi Salt Keys ngẫu nhiên bảo mật phiên đăng nhập.
3. Thay đổi tiền tố dữ liệu MySQL (`$table_prefix`).
4. Thay đổi tên tài khoản quản trị mặc định `"admin"` để chống brute-force.
5. Chặn truy cập trực tiếp file `wp-config.php` từ trình duyệt.
6. Chặn sửa đổi và xem trực tiếp tệp cấu hình `.htaccess`.
7. Vô hiệu hóa và chặn truy cập API XML-RPC chống tấn công DDoS.
8. Chặn thực thi tệp tin PHP trong thư mục `wp-includes`.
9. Chặn thực thi tệp tin PHP trong thư mục tải lên `wp-content/uploads`.
10. Chặn chạy tệp tin PHP trong thư mục bộ đệm `wp-content/cache`.
11. Tắt trình chỉnh sửa giao diện và plugin ngay trong trang quản trị WordPress (`DISALLOW_FILE_EDIT`).
12. Tắt cơ chế tự động gộp file script admin (`CONCATENATE_SCRIPTS`).
13. Tắt tính năng gửi tin phản hồi Pingbacks trong database.
14. Bảo vệ website khỏi các bot quét dữ liệu xấu (Bad bots & Scrapers).
15. Chặn truy cập các tệp thông tin nhạy cảm (`readme.html`, `license.txt`, `wp-config-sample.php`).
16. Ngăn chặn quét tên đăng nhập của tác giả (`/?author=X`).
17. Tắt chế độ tự động hiển thị danh sách file khi vào thư mục trống (`Options -Indexes`).
18. Ngăn tải xuống các định dạng tệp sao lưu nguy hiểm (`.sql`, `.bak`, `.log`, `.sh`, `.ini`, `.dist`).

*Đối với website chạy máy chủ Nginx (không dùng `.htaccess`), plugin sẽ tự động phát hiện và cung cấp danh sách đầy đủ cấu hình Nginx tối ưu để quản trị viên sao chép thủ công.*

### 9. Ghi Nhật Ký Hoạt Động (System Logs Viewer)
- **Nhật ký thời gian thực**: Toàn bộ thao tác (Cài đặt, Clone, Xóa, Bật/tắt bảo mật, Cài đặt lại plugin/theme...) và kết quả (Thành công/Lỗi chi tiết) được lưu lại vào tệp tin ẩn `.ultimate_wp_manager_debug.log` tại thư mục Home của người dùng.
- **Xem log nhanh (Show Logs)**: Nút xem log trực tiếp trên thanh công cụ mở ra cửa sổ modal hiển thị nội dung log dạng console, tự động cuộn xuống dòng mới nhất khi có cập nhật hoặc khi nhấn làm mới (Refresh).
- **Xử lý ngoại lệ an toàn**: Hệ thống bắt lỗi toàn cục bằng `Throwable` của PHP, ghi nhận chính xác mọi lỗi Fatal Error (như thiếu thư viện) hoặc Exception để quản trị viên dễ dàng khắc phục.

---

## 🛠️ Cấu Trúc Thư Mục Plugin

```text
ultimate-directadmin-wordpress-manager/
├── admin/                     # Thư mục xử lý giao diện/API dành cho Admin DirectAdmin
├── reseller/                  # Thư mục xử lý giao diện dành cho Reseller
├── user/                      # Thư mục xử lý giao diện dành cho User (chạy chính)
├── hooks/                     # Các file html hook tiêm giao diện vào DirectAdmin
├── images/                    # Chứa icon và cấu hình hiển thị menu DirectAdmin
├── app.php                    # Bộ điều hướng logic core backend (CGI Entry point)
├── gui.php                    # Mã nguồn giao diện chính (HTML, CSS, JS)
├── plugin.conf                # Tệp cấu hình thông tin plugin của DirectAdmin
└── install.sh                 # Kịch bản cài đặt tự động qua dòng lệnh
```

---

## 🚀 Cài Đặt Qua SSH (Dành cho Administrator)

Đăng nhập vào máy chủ DirectAdmin của bạn bằng tài khoản **root** qua SSH và chạy lệnh cài đặt nhanh sau:

```bash
curl -sSL https://raw.githubusercontent.com/tuend-work/ultimate-directadmin-wordpress-manager/main/install.sh | bash
```

Script cài đặt sẽ tự động tải mã nguồn, đưa vào đúng cấu trúc thư mục plugin của DirectAdmin `/usr/local/directadmin/plugins/`, phân quyền sở hữu cho user `diradmin:diradmin` và đặt các quyền thực thi tệp tin (`755` cho scripts/CGI và `644` cho tệp tĩnh) một cách chuẩn xác nhất.
