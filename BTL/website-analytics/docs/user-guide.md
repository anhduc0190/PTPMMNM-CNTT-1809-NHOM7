# TÀI LIỆU HƯỚNG DẪN SỬ DỤNG HỆ THỐNG

**Hệ thống:** WebAnalytics Platform — Đề tài 17  

---

## 1. Giới Thiệu Chức Năng

WebAnalytics Platform cung cấp giao diện quản trị hiện đại, trực quan giúp theo dõi và đánh giá hành vi người dùng trên các website thử nghiệm:
- **KPI Cards:** Theo dõi nhanh tổng lượt xem trang (`page_view`), số lần tương tác bấm nút (`button_click`), số phiên truy cập ẩn danh (`active_sessions`), và số lượt tải file thành công (`download_complete`).
- **Biểu đồ xu hướng:** Biểu đồ đường (Line chart) thể hiện tương quan lượt xem và thao tác theo từng ngày.
- **Top trang phổ biến:** Biểu đồ cột (Bar chart) xếp hạng các trang có lưu lượng truy cập cao nhất.
- **Bộ lọc thời gian & website:** Lọc theo từng website được phân quyền và khoảng ngày tùy chọn.

---

## 2. Các Màn Hình Chức Năng

### 2.1. Đăng Nhập (`login.php`)
- Sử dụng cơ chế băm mật khẩu chuẩn `password_hash()` với thuật toán `BCRYPT`.
- Có sẵn 2 nút điền nhanh tài khoản: **Admin** và **Member** để thuận tiện khi bảo vệ đồ án.
- Tự động chống tấn công CSRF và tạo phiên làm việc bảo mật `HttpOnly`.

### 2.2. Tổng Quan KPI (`dashboard.php`)
- Cung cấp cái nhìn toàn cảnh về hoạt động trên website được chọn.
- Bảng hiển thị 10 sự kiện thu thập mới nhất kèm mã băm SHA-256 (`payload_hash`).

### 2.3. Quản Lý Website (`websites.php`)
- Xem danh sách website: Tên, Domain, Mã Tracking Key, Múi giờ, Hạn sự kiện trễ.
- Nút **Sao chép mã Tracking Key (Clipboard)** với 1 click.
- Thao tác Tạm dừng (Pause) hoặc Kích hoạt lại (Active) website.

### 2.4. Cấu Hình Sự Kiện (`event-definitions.php`)
- Khai báo các sự kiện được phép theo dõi trên từng website.
- Bật/Tắt (Enable/Disable) sự kiện linh hoạt:
  - Khi một sự kiện bị Tắt, API `/api/track.php` sẽ từ chối ghi nhận và lưu log lỗi `EVENT_NOT_DEFINED`.

### 2.5. Nhật Ký Sự Kiện & Lỗi (`event-logs.php`)
- **Tab 1: Sự kiện hợp lệ:** Hiển thị chi tiết từng sự kiện đã lưu vào bảng `events`.
- **Tab 2: Nhật ký lỗi & xung đột:** Theo dõi các yêu cầu bị từ chối:
  - `PAYLOAD_CONFLICT`: Khi client gửi cùng `event_id` nhưng thay đổi nội dung payload.
  - `LATE_EVENT_EXCEEDED`: Khi client gửi sự kiện đã xảy ra quá 24 giờ.
  - `EVENT_NOT_DEFINED`: Khi client gửi tên sự kiện chưa được khai báo.

### 2.6. Báo Cáo Theo Thời Gian (`reports.php`)
- Bảng phân bổ số lượng từng loại sự kiện trong kỳ.
- Báo cáo chi tiết theo từng đường dẫn trang (`page_path`) với tỷ lệ thao tác tương tác.
- Nút **Xuất CSV** tích hợp sẵn mã UTF-8 BOM hiển thị chuẩn tiếng Việt trên Microsoft Excel.

### 2.7. Phân Tích Chuỗi Thao Tác Funnel (`funnels.php`)
- Chuỗi chuyển đổi 3 bước:
  $$\text{page\_view} \longrightarrow \text{download\_start} \longrightarrow \text{download\_complete}$$
- Điều kiện hoàn tất nghiêm ngặt:
  - Các bước phải thuộc **cùng website** và **cùng session**.
  - Đúng thứ tự thời gian: $t(\text{page\_view}) \le t(\text{download\_start}) \le t(\text{download\_complete})$.
  - Không thiếu bước và không tính trùng lặp khi retry.
- Bảng đối chiếu nguồn từng phiên truy cập để kiểm tra tính minh bạch.

### 2.8. Trung Tâm Kiểm Thử (`test-center.php`)
- Nhấn 1 nút để tự động sinh **225 requests kiểm thử** và chạy đối chiếu **12 tiêu chí Rubric**.
- Hiển thị trực tiếp kết quả PASS/FAIL và giải thích chi tiết cho từng tiêu chí.

### 2.9. Quản Lý Người Dùng & Phân Quyền (`users.php`)
- Chỉ hiển thị với vai trò **Admin**.
- Tạo tài khoản Member mới.
- Khóa / Mở khóa tài khoản.
- Ma trận phân quyền chi tiết cho từng Member trên từng Website (`can_view`, `can_manage_tracking`, `can_export`).

### 2.10. Cài Đặt Hệ Thống & Xóa Dữ Liệu (`settings.php`)
- Xem thông số môi trường: Phiên bản PHP, MySQL Version, Múi giờ hệ thống.
- Thống kê số lượng bản ghi của 8 bảng CSDL.
- Tính năng **Xóa sạch dữ liệu thử nghiệm** (Clear events, sessions, errors) và **Khôi phục Seed mặc định**.
