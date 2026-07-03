# WPC Optimize MU Plugins

Copy toàn bộ file `.php` trong thư mục `mu-plugins` vào:

```txt
wp-content/mu-plugins/
```

Lưu ý: MU plugins của WordPress chỉ tự load các file PHP nằm trực tiếp trong `wp-content/mu-plugins/`, không tự load file nằm sâu trong thư mục con.

## Danh sách file

- `wpc-optimize-editor.php`: tắt Gutenberg, dùng Classic Editor mặc định.
- `wpc-optimize-security.php`: tắt XML-RPC/pingback, ẩn version, chặn username chứa `test`, tắt sửa file theme/plugin trong admin.
- `wpc-optimize-compatibility.php`: giảm cảnh báo `_load_textdomain_just_in_time`.
- `wpc-optimize-admin.php`: dọn dashboard widget và logo WordPress trên admin bar.
- `wpc-optimize-media.php`: ngăn tạo ảnh phụ/crop ảnh, cho phép upload SVG và CSV.
- `wpc-optimize-frontend.php`: dọn wp_head, tắt RSS, emoji, CF7 CSS, query string version, đưa jQuery xuống footer, tắt resource hints.
- `wpc-optimize-flatsome.php`: ẩn notice license/maintenance Flatsome trong admin.

## Ghi chú

- Đã bỏ dòng `add_action('wp_enqueue_scripts', 'shostvn_setup');` vì function `shostvn_setup()` không tồn tại và có thể gây lỗi fatal.
- Đã sửa dòng `remove_action( 'wp_head', 'adjacent_posts_rel_link_wp_head, 10, 0');` thành cú pháp đúng.
- Đã gộp 2 hàm cho phép SVG upload thành một hàm duy nhất.
