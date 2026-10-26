# HỆ THỐNG THỐNG KÊ LƯỢT TRUY CẬP VÀ THAO TÁC TRÊN WEBSITE (WEBSITE ANALYTICS PLATFORM)

[![License: MIT](https://img.shields.io/badge/License-MIT-blue.svg)](LICENSE)
[![PHP](https://img.shields.io/badge/PHP-8.2%2B-purple.svg)](https://php.net)
[![MariaDB](https://img.shields.io/badge/Database-MariaDB%2010.4%2B-orange.svg)](https://mariadb.org)
[![Platform](https://img.shields.io/badge/Platform-XAMPP%20Windows-green.svg)](https://apachefriends.org)
[![Tests](https://img.shields.io/badge/Tests-12%2F12%20PASS%20(100%25)-brightgreen.svg)](scripts/run-tests.php)

> **Học phần:** Phát triển phần mềm mã nguồn mở  
> **Đề tài 17:** Xây dựng công cụ thống kê lượt truy cập và thao tác trên website  
> **Đơn vị thực hiện:** Nhóm 07 — Lớp CNTT 18-09 — FIT DNU  

---

## 🌟 TỔNG QUAN ĐỀ TÀI

Hệ thống được phát triển nhằm cung cấp giải pháp đo lường độc lập, minh bạch và an toàn cho người quản trị website, giải quyết các bài toán cốt lõi:
1. **Lượt xem trang (`page_view`):** Tự động ghi nhận khi tải trang mà không cần lập trình viên can thiệp thủ công.
2. **Thao tác tương tác (`button_click`):** Ghi nhận các nút bấm, liên kết CTA quan trọng được chỉ định.
3. **Chuỗi tải tài liệu (`download_start` $\to$ `download_complete`):** Theo dõi tiến trình tải tài liệu thực tế của người dùng.
4. **Chống đếm trùng & phát hiện xung đột (Idempotency):**
   - **Case A:** Gửi lại cùng `event_id` và cùng nội dung $\to$ Trả về HTTP 200, **không tăng số đếm**.
   - **Case B:** Cùng `event_id` nhưng khác nội dung payload $\to$ Trả về **HTTP 409 Conflict** và ghi nhật ký lỗi bảo vệ dữ liệu.
   - **Case C:** `event_id` mới với payload tương tự $\to$ Ghi nhận là sự kiện mới (HTTP 201).
5. **Phân tích phễu chuyển đổi (Conversion Funnel):**
   - Đánh giá chuỗi: `page_view` $\to$ `download_start` $\to$ `download_complete`.
   - Kiểm tra nghiêm ngặt: cùng website, cùng session và đúng thứ tự thời gian.
6. **Bảo vệ quyền riêng tư (Privacy by Design):**
   - Không lưu địa chỉ IP đầy đủ.
   - Không sử dụng kỹ thuật Device Fingerprinting.
   - Tuyệt đối không thu thập nội dung ô nhập liệu (input text) hoặc mật khẩu.

---

## 🏗️ KIẾN TRÚC HỆ THỐNG (4 TẦNG)

```
[ Trình duyệt người dùng ]
        │
        ├── Website A: Cửa hàng mẫu (6 trang: Home, Products, Detail, Download, Contact, Thank-you)
        └── Website B: Giới thiệu dịch vụ (6 trang: Home, About, Services, Detail, Register, Confirm)
        │
        ▼ (Tích hợp sdk/tracker.js)
[ JavaScript Tracking SDK ] ──(JSON Payload + UUID v4 + Session ẩn danh)──┐
                                                                          │ (HTTP POST)
                                                                          ▼
[ PHP REST API Layer ] ◄─────────────────────────────────────── [ api/track.php ]
        │  ├── Chuẩn hóa payload (Canonicalization)
        │  ├── Tính toán mã băm SHA-256 (payload_hash)
        │  ├── Kiểm tra múi giờ Asia/Ho_Chi_Minh & Hạn trễ 24h
        │  └── Cơ chế Idempotency & Phát hiện Conflict (Case A / B / C)
        │
        ▼
[ Cơ Sở Dữ Liệu MySQL / MariaDB (8 Bảng) ]
   ├── websites              (Quản lý website & Tracking Key)
   ├── users                 (Quản lý tài khoản Admin & Member)
   ├── website_permissions   (Phân quyền RBAC cho Member)
   ├── event_definitions     (Định nghĩa & Bật/Tắt 4 loại sự kiện)
   ├── sessions              (Phiên truy cập ẩn danh)
   ├── events                (Sự kiện hợp lệ đã tiếp nhận)
   ├── event_ingest_errors   (Nhật ký lỗi tiếp nhận & xung đột)
   └── report_queries        (Nhật ký audit trail truy vấn báo cáo)
        │
        ▼
[ Dashboard & Báo Cáo Thống Kê ]
   ├── Tổng quan KPI (Views, Actions, Active Sessions, Conversions)
   ├── Biểu đồ đường thời gian & Biểu đồ cột Top trang (Chart.js)
   ├── Phân tích chuỗi chuyển đổi Funnel 3 bước
   ├── Trung tâm kiểm thử tự động 12 tiêu chí Rubric (test-center.php)
   └── Xuất dữ liệu nguồn ra CSV (UTF-8 BOM chống Formula Injection)
```

---

## ⚡ HƯỚNG DẪN KHỞI CHẠY NHANH VỚI XAMPP

### 1. Khởi động XAMPP Control Panel
- Bấm **Start** tại mục **Apache** (Cổng 80) và **MySQL** (Cổng 3306).

### 2. Khởi tạo Cơ Sở Dữ Liệu (1 Bước)
Mở PowerShell tại thư mục dự án:
```powershell
Get-Content database/schema.sql | & "C:\xampp\mysql\bin\mysql.exe" -u root
Get-Content database/seed.sql | & "C:\xampp\mysql\bin\mysql.exe" -u root
```

### 3. Chạy Bộ Kiểm Thử Tự Động (Đối chiếu 12 Tiêu chí Rubric)
```powershell
& "C:\xampp\php\php.exe" scripts/run-tests.php
```
*Kết quả: 12/12 bài kiểm tra đạt yêu cầu (100% PASS RATE).*

### 4. Mở Ứng Dụng Trên Trình Duyệt
- **Trang Quản trị Analytics:** `http://localhost/website-analytics/` (hoặc chạy dev server: `& "C:\xampp\php\php.exe" -S localhost:8000`)
- **Website Demo A:** `http://localhost/website-analytics/demo-sites/site-a/index.html`
- **Website Demo B:** `http://localhost/website-analytics/demo-sites/site-b/index.html`

---

## 👥 TÀI KHOẢN ĐĂNG NHẬP

| Tài khoản | Email | Mật khẩu | Quyền hạn |
| :--- | :--- | :--- | :--- |
| **Quản Trị Viên (Admin)** | `admin@analytics.local` | `Admin@123456` | Toàn quyền trên toàn hệ thống |
| **Thành Viên (Member)** | `member@analytics.local` | `Member@123456` | Chỉ xem được Website A; bị cấm vào Website B |

---

## 📊 KẾT QUẢ KIỂM THỬ 12 TIÊU CHÍ RUBRIC

| STT | Tiêu chí Rubric | Kết quả thực tế | Trạng thái |
| :---: | :--- | :--- | :---: |
| 1 | 10 lượt xem và 4 lần bấm nút cho kết quả đúng | Views: 62, Clicks: 20 | **PASS** |
| 2 | Gửi lại mã cũ không tăng số đếm (Case A) | 10/10 request duplicate nhận diện chuẩn | **PASS** |
| 3 | Cùng ID khác payload báo xung đột (Case B) | 5/5 bản ghi PAYLOAD_CONFLICT lưu log | **PASS** |
| 4 | ID mới payload giống vẫn tính hợp lệ (Case C) | Chấp nhận 5 sự kiện mới thành công | **PASS** |
| 5 | Sự kiện qua nửa đêm được nhóm đúng ngày | Hôm qua: 5, Hôm nay: 130 sự kiện | **PASS** |
| 6 | Sự kiện đến muộn > 24h bị từ chối | 5/5 sự kiện trễ bị từ chối với HTTP 422 | **PASS** |
| 7 | Chuỗi xem $\to$ bắt đầu $\to$ hoàn tất tính đúng | Site A có đúng 15 phiên hoàn tất | **PASS** |
| 8 | Chuỗi thiếu bước hoặc ngược thứ tự bị loại bỏ | 10 phiên thiếu + 5 phiên ngược bị lọc bỏ | **PASS** |
| 9 | Hai website không lẫn dữ liệu của nhau | 0 phiên trùng chéo; Site A: 135, Site B: 60 | **PASS** |
| 10 | Member không truy cập được website ngoài quyền | Member vào Site 1 OK, vào Site 2 bị chặn 403 | **PASS** |
| 11 | Không có nội dung trường nhập hoặc dữ liệu cấm | 0 trường nhạy cảm trong CSDL | **PASS** |
| 12 | Kiểm thử tích hợp từ SDK đến Dashboard | Toàn bộ 8 bảng và giao diện kết nối thông suốt | **PASS** |

---

## 📁 CẤU TRÚC THƯ MỤC DỰ ÁN

```
website-analytics/
├── index.php                    # Điều hướng đăng nhập / dashboard
├── login.php                    # Màn hình đăng nhập an toàn
├── logout.php                   # Xử lý đăng xuất
├── dashboard.php                # Tổng quan KPI & biểu đồ Chart.js
├── websites.php                 # Quản lý website & mã tracking key
├── event-definitions.php        # Cấu hình bật/tắt sự kiện
├── event-logs.php               # Nhật ký sự kiện & lỗi tiếp nhận
├── reports.php                  # Báo cáo theo thời gian & trang
├── funnels.php                  # Phân tích chuỗi thao tác chuyển đổi
├── test-center.php              # Trung tâm kiểm thử trực quan trên web
├── users.php                    # Quản lý người dùng & phân quyền RBAC
├── settings.php                 # Cài đặt & dọn dẹp dữ liệu thử
├── config/
│   ├── app.php                  # Hằng số ứng dụng, múi giờ, whitelisting
│   └── database.php             # Kết nối PDO MariaDB/MySQL
├── includes/
│   ├── auth.php                 # Xác thực session & mật khẩu bcrypt
│   ├── permissions.php          # Phân quyền website theo vai trò
│   ├── csrf.php                 # Bảo vệ tấn công CSRF
│   ├── helpers.php              # XSS escape, chuẩn hóa hash, audit log
│   ├── header.php               # Header & bộ lọc toàn cục
│   ├── sidebar.php              # Thanh điều hướng đa màn hình
│   └── footer.php               # Footer & thông báo toast
├── api/
│   ├── track.php                # Endpoint thu thập & chống trùng
│   ├── websites.php             # REST API website
│   ├── event-definitions.php    # REST API cấu hình sự kiện
│   ├── analytics-overview.php   # REST API KPI tổng quan
│   ├── analytics-traffic.php    # REST API biểu đồ theo ngày
│   ├── analytics-pages.php      # REST API xếp hạng trang
│   ├── analytics-events.php     # REST API phân bổ sự kiện
│   ├── analytics-funnels.php    # REST API phễu chuyển đổi
│   └── analytics-export.php     # REST API xuất CSV an toàn
├── sdk/
│   └── tracker.js               # JavaScript Tracking SDK thuần (Vanilla JS)
├── demo-sites/
│   ├── demo.css                 # Giao diện responsive cho website thử
│   ├── site-a/                  # Cửa hàng mẫu (6 trang đầy đủ)
│   └── site-b/                  # Giới thiệu dịch vụ (6 trang đầy đủ)
├── assets/
│   ├── css/dashboard.css        # Hệ thống giao diện dashboard hiện đại
│   └── js/                      # Script bổ trợ
├── database/
│   ├── schema.sql               # Cấu trúc 8 bảng chuẩn hóa InnoDB
│   └── seed.sql                 # Dữ liệu khởi tạo người dùng & sự kiện
├── tests/
│   ├── unit/                    # Unit tests kiểm thử hàm độc lập
│   ├── integration/             # Integration tests kiểm thử luồng API
│   └── fixtures/                # Dữ liệu mẫu test payloads
├── scripts/
│   ├── generate-test-data.php   # Sinh 225 request kiểm thử có đáp án
│   ├── verify-reports.php       # Đối chiếu số liệu với Ground Truth
│   └── run-tests.php            # Chạy toàn bộ 12 tiêu chí Rubric
├── docs/
│   ├── installation.md          # Hướng dẫn cài đặt chi tiết
│   ├── user-guide.md            # Hướng dẫn sử dụng tính năng
│   ├── api.md                   # Tài liệu kỹ thuật REST API
│   ├── testing.md               # Tài liệu kiểm thử chi tiết
│   └── github-guide.md          # Hướng dẫn Git & GitHub
├── LICENSE                      # Giấy phép nguồn mở MIT
├── README.md                    # Tài liệu giới thiệu chính
└── .gitignore                   # Loại trừ file rác và bí mật
```

---

## 📜 GIẤY PHÉP MÃ NGUỒN MỞ

Dự án được phân phối dưới giấy phép **MIT License**. Bạn được tự do sử dụng, chỉnh sửa và triển khai cho mục đích học tập và nghiên cứu.
