# BÀI TẬP LỚN PHÁT TRIỂN PHẦN MỀM MÃ NGUỒN MỞ — FIT DNU
## ĐỀ TÀI 17: XÂY DỰNG CÔNG CỤ THỐNG KÊ LƯỢT TRUY CẬP VÀ THAO TÁC TRÊN WEBSITE

[![License: MIT](https://img.shields.io/badge/License-MIT-blue.svg)](website-analytics/LICENSE)
[![PHP](https://img.shields.io/badge/PHP-8.2%2B-purple.svg)](https://php.net)
[![MariaDB](https://img.shields.io/badge/Database-MariaDB%2010.4%2B-orange.svg)](https://mariadb.org)
[![Platform](https://img.shields.io/badge/Platform-XAMPP%20Windows-green.svg)](https://apachefriends.org)
[![Tests](https://img.shields.io/badge/Tests-12%2F12%20PASS%20(100%25)-brightgreen.svg)](website-analytics/scripts/run-tests.php)

**Đơn vị thực hiện:** Nhóm 07 — Lớp CNTT 18-09  
**Mã nguồn chính thức:** [`BTL/website-analytics/`](file:///d:/btl%20PMP/PTPMMNM-CNTT-1809-NHOM7/BTL/website-analytics/)

---

## 🚀 HƯỚNG DẪN CHẠY DỰ ÁN CHO HỘI ĐỒNG CHẤM

### 1. Khởi động XAMPP Control Panel
- Bấm **Start** tại mục **Apache** (Cổng 80) và **MySQL** (Cổng 3306).

### 2. Khởi tạo Cơ Sở Dữ Liệu
Mở PowerShell tại thư mục này và chạy:
```powershell
Get-Content website-analytics/database/schema.sql | & "C:\xampp\mysql\bin\mysql.exe" -u root
Get-Content website-analytics/database/seed.sql | & "C:\xampp\mysql\bin\mysql.exe" -u root
```

### 3. Chạy Kiểm Thử Tự Động (Đối chiếu 12 Tiêu chí Rubric)
```powershell
& "C:\xampp\php\php.exe" website-analytics/scripts/run-tests.php
```
*Kết quả: 12/12 bài kiểm tra đạt yêu cầu (100% PASS RATE).*

### 4. Mở Ứng Dụng Trên Trình Duyệt
- **Cách A (XAMPP Apache):** Chạy [`KET_NOI_XAMPP.bat`](file:///d:/btl%20PMP/PTPMMNM-CNTT-1809-NHOM7/BTL/KET_NOI_XAMPP.bat) $\to$ Truy cập `http://localhost/website-analytics/`
- **Cách B (PHP Built-in Server):**  
  ```powershell
  cd website-analytics
  & "C:\xampp\php\php.exe" -S localhost:8000
  ```  
  Truy cập: `http://localhost:8000/`

---

## 👥 TÀI KHOẢN ĐĂNG NHẬP SẴN CÓ
- **Admin:** `admin@analytics.local` / `Admin@123456` (Toàn quyền)
- **Member:** `member@analytics.local` / `Member@123456` (Chỉ xem Website A)

---

## 🌐 ĐỊA CHỈ TRUY CẬP WEBSITE DEMO
- **Website A (Cửa hàng công nghệ - 6 trang):** `http://localhost/website-analytics/demo-sites/site-a/index.html`
- **Website B (Cổng dịch vụ CNTT - 6 trang):** `http://localhost/website-analytics/demo-sites/site-b/index.html`
- **Quản lý phpMyAdmin:** `http://localhost/phpmyadmin/`
