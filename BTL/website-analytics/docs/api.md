# TÀI LIỆU KỸ THUẬT REST API

**Hệ thống:** WebAnalytics Platform — Đề tài 17  
**Giao thức:** HTTP/JSON REST API  

---

## 1. API Thu Thập Sự Kiện (`POST /api/track.php`)

Endpoint tiếp nhận sự kiện từ JavaScript Tracking SDK hoặc ứng dụng bên ngoài.

### Header yêu cầu:
```http
POST /api/track.php HTTP/1.1
Host: localhost
Content-Type: application/json; charset=utf-8
X-Tracking-Key: trk_site_a_alpha_shop_2026
```

### Cấu trúc Body JSON:
```json
{
  "tracking_key": "trk_site_a_alpha_shop_2026",
  "event_id": "c1f7a28b-9e45-42cf-991c-7f5b892a0142",
  "session_id": "sess_k9z12m_a831",
  "event_name": "button_click",
  "page_path": "/products.html",
  "occurred_at": "2026-10-26T14:30:15.000Z",
  "properties": {
    "element_id": "btn-add-cart",
    "button_text": "Thêm vào giỏ"
  }
}
```

### Quy tắc phản hồi theo Rubric chống đếm trùng:

#### A. Sự kiện mới hợp lệ (Case C):
**HTTP Status:** `201 Created`
```json
{
  "success": true,
  "status": "created",
  "is_duplicate": false,
  "message": "Sự kiện mới được tiếp nhận và lưu trữ thành công.",
  "event_id": "c1f7a28b-9e45-42cf-991c-7f5b892a0142",
  "event_row_id": 142,
  "received_at": "2026-10-26 21:30:15"
}
```

#### B. Gửi lại mã cũ cùng nội dung (Case A — Idempotent Duplicate):
**HTTP Status:** `200 OK`
```json
{
  "success": true,
  "status": "duplicate_idempotent",
  "is_duplicate": true,
  "message": "Sự kiện gửi lại hợp lệ (trùng event_id và trùng nội dung). Bỏ qua không tăng số đếm.",
  "event_id": "c1f7a28b-9e45-42cf-991c-7f5b892a0142",
  "received_at": "2026-10-26 21:30:15"
}
```

#### C. Cùng event_id nhưng khác nội dung (Case B — Conflict):
**HTTP Status:** `409 Conflict`
```json
{
  "success": false,
  "error": "PAYLOAD_CONFLICT",
  "message": "Xung đột dữ liệu: event_id đã tồn tại nhưng có nội dung payload khác với sự kiện ban đầu.",
  "event_id": "c1f7a28b-9e45-42cf-991c-7f5b892a0142"
}
```

#### D. Sự kiện đến muộn quá hạn cho phép (> 24 giờ):
**HTTP Status:** `422 Unprocessable Entity`
```json
{
  "success": false,
  "error": "LATE_EVENT_EXCEEDED",
  "message": "Sự kiện bị từ chối do đến muộn quá thời hạn cấu hình (24 giờ)."
}
```

#### E. Sự kiện chưa được khai báo:
**HTTP Status:** `400 Bad Request`
```json
{
  "success": false,
  "error": "EVENT_NOT_DEFINED",
  "message": "Sự kiện 'unregistered_event' chưa được cấu hình hoặc đã bị vô hiệu hoá."
}
```

---

## 2. Các Endpoint Báo Cáo Thống Kê (Dành cho Dashboard & Phân Quyền)

Tất cả các endpoint báo cáo yêu cầu session đăng nhập hợp lệ:

| Method | Endpoint | Tham số | Mô tả chức năng |
| :--- | :--- | :--- | :--- |
| `GET` | `/api/websites.php` | Không | Lấy danh sách website thuộc quyền quản lý của user |
| `GET` | `/api/event-definitions.php` | `website_id` | Lấy danh sách sự kiện được khai báo của website |
| `GET` | `/api/analytics-overview.php` | `website_id, from_date, to_date` | Lấy KPI tổng quan: Views, Actions, Sessions, Conversions, Lỗi |
| `GET` | `/api/analytics-traffic.php` | `website_id, from_date, to_date` | Lấy chuỗi dữ liệu theo ngày cho biểu đồ đường |
| `GET` | `/api/analytics-pages.php` | `website_id, from_date, to_date` | Xếp hạng các trang theo lượt xem và số phiên |
| `GET` | `/api/analytics-events.php` | `website_id, from_date, to_date` | Phân bổ số lượng các loại sự kiện trong kỳ |
| `GET` | `/api/analytics-funnels.php` | `website_id, from_date, to_date` | Phân tích phễu 3 bước và danh sách session |
| `GET` | `/api/analytics-export.php` | `website_id, from_date, to_date, event_name, page_path` | Xuất toàn bộ sự kiện nguồn ra file CSV có BOM UTF-8 |
