# Web Analytics — Công cụ thống kê lượt truy cập và thao tác trên website

[![License: MIT](https://img.shields.io/badge/License-MIT-blue.svg)](LICENSE)
[![PHP](https://img.shields.io/badge/PHP-8.1%2B-purple.svg)](https://php.net)
[![MySQL](https://img.shields.io/badge/MySQL-8.0%2B-orange.svg)](https://mysql.com)

Hệ thống thu thập và phân tích thống kê lượt xem trang, thao tác người dùng
và chuỗi chuyển đổi (funnel) trên website thử nghiệm.

> **Phạm vi:** Chỉ đo website do nhóm quản lý. Người tham gia đã biết việc thu thập.
> Không theo dõi chéo website, không dùng dấu vân tay thiết bị, không ghi nội dung
> trường nhập liệu, không lưu địa chỉ IP đầy đủ.

---

## Tính năng

| # | Chức năng | Trạng thái |
|---|-----------|-----------|
| 1 | Đăng ký website thử, định nghĩa sự kiện, quản lý quyền | ✅ |
| 2 | JavaScript tracker tự động + API thu thập | ✅ |
| 3 | Validate, idempotency, phát hiện xung đột | ✅ |
| 4 | Dashboard: lượt xem, phiên, bộ lọc thời gian | ✅ |
| 5 | Phân tích funnel, xuất CSV/JSON, giữ/xóa dữ liệu | ✅ |

## Yêu cầu môi trường

- **XAMPP** (PHP ≥ 8.1, Apache, MySQL / MariaDB) hoặc PHP độc lập
- Extension: `pdo_mysql`, `pdo_sqlite`
- Git / Bash (Git Bash trên Windows)

---

## Kết nối và Chạy với XAMPP

Hệ thống được thiết kế **tương thích 100% với XAMPP** (sử dụng cổng `3306`, tài khoản `root`, mật khẩu để trống mặc định của XAMPP).

### Bước 1: Khởi động XAMPP
1. Mở **XAMPP Control Panel**.
2. Nhấn nút **Start** ở cả 2 dịch vụ: **Apache** và **MySQL**.

### Bước 2: Khởi tạo Database trên XAMPP
Có 2 cách thực hiện:

- **Cách 1 — Dùng dòng lệnh (Khuyên dùng):**
  ```bash
  # Tự động tạo bảng trên XAMPP MySQL:
  bash lab.sh sql

  # Tạo ~200 sự kiện mẫu để đối chiếu:
  bash lab.sh seed
  ```

- **Cách 2 — Dùng giao diện phpMyAdmin của XAMPP:**
  1. Mở trình duyệt truy cập: `http://localhost/phpmyadmin`
  2. Bấm tab **Nhập (Import)** $\rightarrow$ Chọn tệp `database/schema.sql` $\rightarrow$ Nhấn **Thực hiện (Import)**.
  3. CSDL `web_analytics` cùng 6 bảng dữ liệu sẽ được tạo tự động.

### Bước 3: Khởi chạy ứng dụng Web

- **Cách 1 — Chạy trực tiếp trên Apache XAMPP (Localhost):**
  1. Nhấp đúp chuột chạy file: [`KET_NOI_XAMPP.bat`](KET_NOI_XAMPP.bat) (Khi Windows hỏi Administrator bấm **Yes** - chỉ cần làm 1 lần).
  2. Mở **XAMPP Control Panel** $\rightarrow$ Bấm **Start** Apache và MySQL $\rightarrow$ Bấm nút **Admin** cạnh Apache (hoặc vào trình duyệt gõ: `http://localhost/`).
  3. Hoặc nhấp đúp file [`CHAY_XAMPP.bat`](CHAY_XAMPP.bat) để tự động bật XAMPP và mở trang web!
  👉 Dashboard Quản trị: `http://localhost/` hoặc `http://localhost/web-analytics/`  
  👉 Website Alpha: `http://localhost/web-analytics/demo-sites/site-alpha/`  
  👉 Website Beta: `http://localhost/web-analytics/demo-sites/site-beta/`

- **Cách 2 — Chạy máy chủ tích hợp (CLI / Linux / WSL):**
  ```bash
  bash lab.sh run   # Dashboard: http://127.0.0.1:8200
  bash lab.sh demo  # Demo Alpha (8201) và Beta (8202)
  ```

---

## Kiểm thử tự động (Rubric)

```bash
# Phép thử đơn vị (25 phép thử, chạy độc lập SQLite):
bash lab.sh test

# Phép thử tích hợp với MySQL XAMPP (8 kịch bản):
bash lab.sh integrate
```

## Cấu trúc dự án

```
web-analytics/
├── database/schema.sql        # Schema MySQL
├── public/                    # Web server root
│   ├── index.php              # Dashboard
│   ├── register.php           # Đăng ký website
│   ├── report.php             # Xem & xuất báo cáo
│   ├── funnel.php             # Phân tích chuỗi thao tác
│   ├── collect.php            # API tiếp nhận sự kiện
│   └── assets/js/tracker.js  # JavaScript tracker
├── src/
│   ├── Database.php           # Kết nối MySQL (PDO)
│   ├── WebsiteRegistry.php    # Đăng ký & xác thực
│   ├── EventCollector.php     # Thu thập & validate
│   ├── Aggregator.php         # Tổng hợp báo cáo
│   ├── FunnelAnalyzer.php     # Phân tích chuỗi
│   └── ReportExporter.php     # Xuất CSV/JSON
├── tests/
│   ├── run.php                # 6 phép thử bắt buộc
│   └── integration.php        # Test tích hợp MySQL
├── fixtures/seed.php          # Tạo ~200 sự kiện mẫu
├── tools/                     # Công cụ CLI
└── demo-sites/                # 2 website thử mẫu
```

## Quy tắc kỹ thuật

### Múi giờ
- Tất cả sự kiện lưu UTC trong database
- Báo cáo nhóm theo **Asia/Ho_Chi_Minh (UTC+7)**
- Sự kiện qua nửa đêm UTC+7 được nhóm đúng theo ngày địa phương

### Idempotency
- Mỗi sự kiện gửi kèm `idempotency_key` (SHA-256 từ client)
- Gửi lại **đúng nội dung** → `duplicate` (không tăng số đếm)
- Gửi lại **khác nội dung** → `conflict` (ghi lỗi, từ chối)

### Sự kiện đến muộn
- Chấp nhận tối đa **3600 giây** (1 giờ) trễ
- Vượt quá → bị từ chối và ghi log

### Phiên (Session)
- Token phiên được **hash SHA-256** trước khi lưu (không lưu token gốc)
- Không phải định danh chắc chắn của một người thật
- Một phiên chỉ tính hoàn tất funnel **1 lần**

### Bảo mật & Quyền riêng tư
- IP: chỉ lưu /24 (3 octet IPv4) hoặc /48 (IPv6)
- Không lưu: nội dung trường nhập, mật khẩu, email đầy đủ
- Quyền báo cáo: xác thực bằng API key tại máy chủ
- CORS: chỉ cho phép origin đã đăng ký

## Giấy phép

MIT License — xem [LICENSE](LICENSE)

## Nguồn sử dụng

- [Chart.js](https://chartjs.org) (MIT) — biểu đồ dashboard
- [Inter Font](https://fonts.google.com/specimen/Inter) (OFL) — typography
- [Web Crypto API](https://developer.mozilla.org/en-US/docs/Web/API/SubtleCrypto) — SHA-256 trong tracker.js
