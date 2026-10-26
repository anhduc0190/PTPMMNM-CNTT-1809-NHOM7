# TÀI LIỆU HƯỚNG DẪN VẬN HÀNH VÀ BÁO CÁO BÀI TẬP LỚN

**Học phần:** Phát triển phần mềm mã nguồn mở  
**Đề tài 17:** Xây dựng công cụ thống kê lượt truy cập và thao tác trên website  
**Đơn vị thực hiện:** Nhóm 07 — Lớp CNTT 18-09 — FIT DNU  

---

## I. TỔNG QUAN HỆ THỐNG VÀ KIẾN TRÚC MÃ NGUỒN MỞ

Hệ thống được phát triển hoàn chỉnh trên nền tảng **PHP 8.2 thuần + PDO + MySQL/MariaDB + JavaScript SDK thuần**, đáp ứng chính xác 100% đề bài và rubric của giảng viên:
1. **Tầng Thu thập (Client-side):** File [`sdk/tracker.js`](file:///d:/btl%20PMP/PTPMMNM-CNTT-1809-NHOM7/BTL/website-analytics/sdk/tracker.js) (thuần Vanilla JavaScript). Tự động ghi nhận `page_view`, `button_click`, và chuỗi tải file (`download_start` $\to$ `download_complete`). Tự sinh UUID v4 và session ẩn danh trong `sessionStorage`.
2. **Tầng Tiếp nhận (API Ingestion):** [`api/track.php`](file:///d:/btl%20PMP/PTPMMNM-CNTT-1809-NHOM7/BTL/website-analytics/api/track.php). Xác thực tracking key, chuẩn hóa payload (ksort de-quy), tính toán mã băm SHA-256 (`payload_hash`), kiểm tra múi giờ `Asia/Ho_Chi_Minh` và hạn trễ 24 giờ.
3. **Cơ chế chống đếm trùng (Idempotency):**
   - **Case A (Trùng lặp):** Cùng `event_id`, cùng `payload_hash` $\to$ Trả về HTTP 200 `duplicate_idempotent`, **không tăng số đếm**.
   - **Case B (Xung đột):** Cùng `event_id`, khác `payload_hash` $\to$ Trả về **HTTP 409 Conflict**, ghi log lỗi vào `event_ingest_errors`.
   - **Case C (Hợp lệ mới):** `event_id` mới với payload tương tự $\to$ Ghi nhận sự kiện mới (HTTP 201 Created).
4. **Tầng Lưu trữ (Persistence):** CSDL MySQL/MariaDB với 8 bảng chuẩn hóa: `websites`, `users`, `website_permissions`, `event_definitions`, `sessions`, `events`, `event_ingest_errors`, `report_queries`.
5. **Tầng Phân tích & Báo cáo:** Dashboard quản trị hiện đại, phân tích phễu chuyển đổi 3 bước, trung tâm kiểm thử tự động 12 tiêu chí Rubric và xuất CSV an toàn có BOM UTF-8.

---

## II. HƯỚNG DẪN KẾT NỐI VÀ CHẠY DỰ ÁN VỚI XAMPP

### 1. Khởi động XAMPP Control Panel
1. Mở **XAMPP Control Panel**.
2. Nhấn nút **Start** ở cả 2 dịch vụ: **Apache** (cổng 80) và **MySQL** (cổng 3306).

### 2. Khởi tạo Cơ Sở Dữ Liệu
Mở PowerShell tại thư mục này và chạy:
```powershell
Get-Content website-analytics/database/schema.sql | & "C:\xampp\mysql\bin\mysql.exe" -u root
Get-Content website-analytics/database/seed.sql | & "C:\xampp\mysql\bin\mysql.exe" -u root
```

### 3. Kết nối vào XAMPP htdocs
- Nhấp đúp chuột file: [`KET_NOI_XAMPP.bat`](file:///d:/btl%20PMP/PTPMMNM-CNTT-1809-NHOM7/BTL/KET_NOI_XAMPP.bat)
- Khi Windows hiện bảng User Account Control, bấm **Yes**.
- Script sẽ tự động tạo liên kết Junction vào `C:\xampp\htdocs\website-analytics` và mở trình duyệt.

### 4. Các đường dẫn truy cập hệ thống:
- **Dashboard Quản Trị Analytics:** `http://localhost/website-analytics/` (hoặc `http://localhost:8000/`)
- **Website Demo A (Alpha Store - 6 trang):** `http://localhost/website-analytics/demo-sites/site-a/index.html`
- **Website Demo B (Beta Services - 6 trang):** `http://localhost/website-analytics/demo-sites/site-b/index.html`
- **Quản lý CSDL phpMyAdmin:** `http://localhost/phpmyadmin/`

---

## III. HƯỚNG DẪN TRÌNH DIỄN KHI BẢO VỆ ĐỒ ÁN

### 🎯 Tình huống 1: Trình diễn Thu Thập Sự Kiện Thực Tế
1. Đăng nhập tài khoản **Admin** (`admin@analytics.local` / `Admin@123456`).
2. Mở tab mới vào Website Demo A (`demo-sites/site-a/index.html`).
3. Click các nút: "Khám phá sản phẩm", "Thêm vào giỏ", "Xem chi tiết".
4. Vào trang Tải Catalog (`download.html`), bấm nút "Tải Ngay Bản PDF Đầy Đủ".
5. Quan sát thanh thông báo toast: Sự kiện `download_start` $\to$ File PDF được tải về $\to$ Sự kiện `download_complete` được gửi vào CSDL.
6. Quay lại Dashboard: Các KPI Lượt xem, Thao tác, Hoàn tất tải file tăng số liệu tức thì!

### 🎯 Tình huống 2: Trình diễn Chống Đếm Trùng & Xung Đột (Rubric B)
1. Mở trang **Nhật ký sự kiện & Lỗi** (`event-logs.php`).
2. Vào **Trung tâm kiểm thử** (`test-center.php`), bấm nút "Chạy Toàn Bộ 12 Bài Kiểm Thử Tự Động".
3. Chỉ cho giảng viên thấy:
   - **Case A:** 10 request gửi lại đúng ID và payload $\to$ Trả về 200, không tăng bản ghi CSDL.
   - **Case B:** 5 request cùng ID khác payload $\to$ Trả về 409 Conflict và lưu rõ trong bảng `event_ingest_errors`.

### 🎯 Tình huống 3: Trình diễn Phân Quyền RBAC (Rubric D)
1. Đăng xuất tài khoản Admin.
2. Đăng nhập bằng tài khoản **Member** (`member@analytics.local` / `Member@123456`).
3. Member xem được báo cáo của Website A bình thường.
4. Member không nhìn thấy menu Quản trị người dùng, Cài đặt hệ thống.
5. Khi cố tình truy cập Website B hoặc API Website B, hệ thống lập tức chặn với mã lỗi **403 Forbidden**.
