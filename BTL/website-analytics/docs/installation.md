# HƯỚNG DẪN CÀI ĐẶT VÀ KHỞI CHẠY HỆ THỐNG

**Học phần:** Phát triển phần mềm mã nguồn mở  
**Đề tài 17:** Xây dựng công cụ thống kê lượt truy cập và thao tác trên website  
**Đơn vị thực hiện:** Nhóm 07 — Lớp CNTT 18-09  

---

## 1. Yêu Cầu Môi Trường

Hệ thống được thiết kế và kiểm thử tương thích 100% với môi trường chuẩn:
- **Hệ điều hành:** Windows 10 / 11.
- **XAMPP Control Panel:** v3.3.0.
- **Apache Web Server:** Port 80 (hoặc 8080 nếu đổi cổng).
- **Database Server:** MariaDB 10.4.32 / MySQL 8.0+ trên cổng mặc định `3306`.
- **PHP:** Phiên bản `8.2.12` (Extension bắt buộc: `pdo_mysql`, `json`, `mbstring`, `session`).
- **phpMyAdmin:** Có sẵn trong XAMPP tại `http://localhost/phpmyadmin/`.

---

## 2. Các Bước Khởi Chạy (Dành Cho Giảng Viên & Sinh Viên)

### Bước 1: Khởi động dịch vụ trên XAMPP
1. Mở ứng dụng **XAMPP Control Panel**.
2. Nhấn nút **Start** tại mục **Apache** (cổng 80/443).
3. Nhấn nút **Start** tại mục **MySQL** (cổng 3306).
   > *Lưu ý: Không thay đổi cổng 3306 hoặc gỡ MySQL có sẵn.*

---

### Bước 2: Khởi tạo Cơ Sở Dữ Liệu MySQL

Bạn có thể lựa chọn 1 trong 2 cách sau:

#### Cách A: Nhập qua phpMyAdmin (Giao diện đồ họa)
1. Truy cập trình duyệt: `http://localhost/phpmyadmin/`.
2. Tạo mới một cơ sở dữ liệu có tên: `website_analytics` (chọn bảng mã `utf8mb4_unicode_ci`).
3. Bấm vào cơ sở dữ liệu `website_analytics` vừa tạo $\rightarrow$ Chọn tab **Import (Nhập)**.
4. Bấm **Choose File** và chọn file:  
   `database/schema.sql` $\rightarrow$ Nhấn **Import** ở cuối trang.
5. Tiếp tục chọn tab **Import** $\rightarrow$ Chọn file:  
   `database/seed.sql` $\rightarrow$ Nhấn **Import**.
6. Kiểm tra danh sách bảng: Phải xuất hiện đủ 8 bảng:
   - `websites`
   - `users`
   - `website_permissions`
   - `event_definitions`
   - `sessions`
   - `events`
   - `event_ingest_errors`
   - `report_queries`

#### Cách B: Nhập qua dòng lệnh (CLI - Khuyên dùng)
Mở PowerShell tại thư mục dự án và chạy:
```powershell
Get-Content database/schema.sql | & "C:\xampp\mysql\bin\mysql.exe" -u root
Get-Content database/seed.sql | & "C:\xampp\mysql\bin\mysql.exe" -u root
```

---

### Bước 3: Cấu hình Kết Nối CSDL

Kiểm tra file [`config/database.php`](file:///d:/btl%20PMP/PTPMMNM-CNTT-1809-NHOM7/BTL/website-analytics/config/database.php):
```php
define('DB_HOST', '127.0.0.1');
define('DB_PORT', '3306');
define('DB_NAME', 'website_analytics');
define('DB_USER', 'root');
define('DB_PASS', ''); // Mặc định mật khẩu root trong XAMPP để trống
```

---

### Bước 4: Kết Nối Dự Án Vào XAMPP Apache

Nhấp đúp chuột vào file batch tự động:
[`KET_NOI_XAMPP.bat`](file:///d:/btl%20PMP/PTPMMNM-CNTT-1809-NHOM7/BTL/KET_NOI_XAMPP.bat)  
*(Khi Windows hiện bảng User Account Control, bấm **Yes** để script tạo liên kết Junction vào `C:\xampp\htdocs\website-analytics`).*

---

### Bước 5: Truy Cập Trực Tiếp Trên Trình Duyệt

Sau khi kết nối, truy cập các địa chỉ:
- **Trang Dashboard Quản Trị:**  
  `http://localhost/website-analytics/` (hoặc `http://localhost:8000/` nếu chạy dev server)
- **Website Thử Nghiệm A (Alpha Store):**  
  `http://localhost/website-analytics/demo-sites/site-a/index.html`
- **Website Thử Nghiệm B (Beta Services):**  
  `http://localhost/website-analytics/demo-sites/site-b/index.html`
- **Quản lý CSDL phpMyAdmin:**  
  `http://localhost/phpmyadmin/`

---

## 3. Tài Khoản Đăng Nhập Sẵn Có

| Vai trò | Email đăng nhập | Mật khẩu | Phạm vi quyền |
| :--- | :--- | :--- | :--- |
| **Admin** | `admin@analytics.local` | `Admin@123456` | Toàn quyền: Xem tất cả website, cấu hình sự kiện, quản lý người dùng, test center, xóa dữ liệu thử |
| **Member** | `member@analytics.local` | `Member@123456` | Chỉ xem được Website A; bị chặn 403 khi vào Website B; không có quyền quản lý người dùng |

---

## 4. Cách Khởi Chạy Nhanh Bằng PHP CLI (Không Cần Chờ Apache)

Nếu muốn chạy độc lập qua máy chủ tích hợp của PHP:
```powershell
cd "d:\btl PMP\PTPMMNM-CNTT-1809-NHOM7\BTL\website-analytics"
& "C:\xampp\php\php.exe" -S localhost:8000
```
Mở trình duyệt: `http://localhost:8000/`.
