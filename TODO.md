# TODO — WordPress Lockdown vào tab Security & Protection

- [ ] 1) Chỉnh `gui.php`: thêm block “WordPress Lockdown (Khoá thư mục và tập tin)” vào tab `Security & Protection` (dạng chỉ nút bật/tắt, không cần badge trạng thái riêng trong tab này).
- [ ] 2) Tránh trùng ID DOM với các phần đang tồn tại ở tab `Overview Details` bằng cách dùng id riêng cho tab Security.
- [ ] 3) Thêm JS để toggle Lock/Unlock từ tab Security (gọi API actions `lock`/`unlock`).
- [ ] 4) Kiểm tra: vào website card → tab Security & Protection → bấm Lock/Unlock hoạt động đúng và không gây lỗi UI.

