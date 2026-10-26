-- Chạy trong database lab riêng; không dùng database production.
-- Múi giờ hệ thống: Asia/Ho_Chi_Minh (UTC+7)
-- Tất cả cột thời gian lưu UTC trong DATETIME; hiển thị chuyển sang UTC+7.

CREATE TABLE IF NOT EXISTS websites (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name        VARCHAR(120) NOT NULL,
    origin      VARCHAR(255) NOT NULL UNIQUE,           -- https://example.com
    api_key     CHAR(64)     NOT NULL UNIQUE,           -- SHA-256 hex, dùng xác thực
    timezone    VARCHAR(50)  NOT NULL DEFAULT 'Asia/Ho_Chi_Minh',
    created_at  DATETIME     NOT NULL DEFAULT (UTC_TIMESTAMP()),
    active      TINYINT(1)   NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS event_definitions (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    website_id  INT UNSIGNED NOT NULL REFERENCES websites(id),
    event_type  VARCHAR(60)  NOT NULL,                  -- pageview | click | form_submit | download
    target      VARCHAR(255) NOT NULL DEFAULT '*',      -- đường dẫn trang / selector nút
    description VARCHAR(255) NOT NULL DEFAULT '',
    active      TINYINT(1)   NOT NULL DEFAULT 1,
    UNIQUE KEY uq_site_type_target (website_id, event_type, target)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Phiên: không phải định danh người thật; chỉ là chuỗi token đã hash
CREATE TABLE IF NOT EXISTS sessions (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    website_id      INT UNSIGNED NOT NULL REFERENCES websites(id),
    session_token   CHAR(64)     NOT NULL,              -- SHA-256 của token gốc, không lưu gốc
    started_at      DATETIME     NOT NULL,
    last_seen_at    DATETIME     NOT NULL,
    UNIQUE KEY uq_site_session (website_id, session_token)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Sự kiện thô - idempotency_key đảm bảo không đếm đôi
CREATE TABLE IF NOT EXISTS events (
    id                  BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    website_id          INT UNSIGNED NOT NULL REFERENCES websites(id),
    session_id          BIGINT UNSIGNED NOT NULL REFERENCES sessions(id),
    event_definition_id INT UNSIGNED REFERENCES event_definitions(id),
    event_type          VARCHAR(60)  NOT NULL,
    page_path           VARCHAR(500) NOT NULL DEFAULT '/',
    target_selector     VARCHAR(255) NOT NULL DEFAULT '',
    idempotency_key     CHAR(64)     NOT NULL,          -- SHA-256 từ client; UNIQUE per website
    client_ts           DATETIME     NOT NULL,          -- thời điểm trình duyệt ghi nhận (UTC)
    server_ts           DATETIME     NOT NULL DEFAULT (UTC_TIMESTAMP()),
    ip_prefix           VARCHAR(20)  NOT NULL DEFAULT '', -- chỉ lưu /24 (3 octet), không đầy đủ
    UNIQUE KEY uq_site_idempotency (website_id, idempotency_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Log lỗi tiếp nhận (giới hạn 1000 dòng; xoay vòng)
CREATE TABLE IF NOT EXISTS event_errors (
    id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    website_id  INT UNSIGNED REFERENCES websites(id),
    error_code  VARCHAR(60)  NOT NULL,
    payload_hash CHAR(64)    NOT NULL DEFAULT '',       -- hash payload lỗi, không lưu payload gốc
    server_ts   DATETIME     NOT NULL DEFAULT (UTC_TIMESTAMP())
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Lịch sử truy vấn báo cáo (audit)
CREATE TABLE IF NOT EXISTS report_queries (
    id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    website_id  INT UNSIGNED NOT NULL REFERENCES websites(id),
    viewer_key  VARCHAR(120) NOT NULL DEFAULT 'system', -- người xem (không lưu thông tin định danh)
    params      JSON         NOT NULL,
    queried_at  DATETIME     NOT NULL DEFAULT (UTC_TIMESTAMP())
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Dữ liệu mẫu: 2 website thử
INSERT INTO websites (name, origin, api_key, timezone) VALUES
('Alpha Shop',  'http://127.0.0.1:8201', SHA2(CONCAT('alpha-secret-', RAND()), 256),  'Asia/Ho_Chi_Minh'),
('Beta Portal', 'http://127.0.0.1:8202', SHA2(CONCAT('beta-secret-',  RAND()), 256),  'Asia/Ho_Chi_Minh')
ON DUPLICATE KEY UPDATE name = VALUES(name);

-- Event definitions cho website 1 (4 loại)
INSERT INTO event_definitions (website_id, event_type, target, description) VALUES
(1, 'pageview',     '*',                   'Lượt xem trang'),
(1, 'click',        '#btn-download',       'Bấm nút tải tài liệu'),
(1, 'form_submit',  '#form-contact',       'Gửi biểu mẫu liên hệ'),
(1, 'download',     '/assets/brochure.pdf','Tải tài liệu')
ON DUPLICATE KEY UPDATE description = VALUES(description);

-- Event definitions cho website 2 (4 loại)
INSERT INTO event_definitions (website_id, event_type, target, description) VALUES
(2, 'pageview',     '*',                   'Lượt xem trang'),
(2, 'click',        '#btn-start',          'Bấm nút bắt đầu'),
(2, 'click',        '#btn-complete',       'Bấm nút hoàn tất'),
(2, 'form_submit',  '#form-survey',        'Gửi biểu mẫu khảo sát')
ON DUPLICATE KEY UPDATE description = VALUES(description);
