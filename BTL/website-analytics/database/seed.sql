-- ====================================================================
-- DU LIEU SEED KHOI TAO BAN DAU
-- Database: website_analytics
-- ====================================================================

SET NAMES utf8mb4;
SET CHARACTER SET utf8mb4;

USE `website_analytics`;
SET NAMES utf8mb4;

-- 1. KHOI TAO WEBSITES MAU
INSERT INTO `websites` (`website_id`, `name`, `domain`, `tracking_key`, `timezone`, `status`, `late_event_window_hours`, `created_at`)
VALUES 
  (1, 'Website A - Cửa hàng mẫu (Alpha Store)', 'localhost', 'trk_site_a_alpha_shop_2026', 'Asia/Ho_Chi_Minh', 'active', 24, NOW()),
  (2, 'Website B - Giới thiệu dịch vụ (Beta Services)', 'localhost', 'trk_site_b_beta_portal_2026', 'Asia/Ho_Chi_Minh', 'active', 24, NOW())
ON DUPLICATE KEY UPDATE 
  `name` = VALUES(`name`), 
  `domain` = VALUES(`domain`), 
  `tracking_key` = VALUES(`tracking_key`),
  `status` = VALUES(`status`);

-- 2. KHOI TAO NGUOI DUNG
-- Mat khau Admin: Admin@123456 (Hash Bcrypt chuan)
-- Mat khau Member: Member@123456 (Hash Bcrypt chuan)
INSERT INTO `users` (`user_id`, `full_name`, `email`, `password_hash`, `role`, `status`, `created_at`)
VALUES 
  (1, 'Quản Trị Viên (Admin)', 'admin@analytics.local', '$2y$10$VQpkl3wzam2z4sowokRe8uTSl1n/g4ClBzBXxRyTRuVu2G/QIVdJq', 'admin', 'active', NOW()),
  (2, 'Thành Viên Báo Cáo (Member)', 'member@analytics.local', '$2y$10$AWkOdJ41owm.hrO0j/.tUub7BfGTJtTSA3TcRvvBg0a6Zd1cVhUgq', 'member', 'active', NOW())
ON DUPLICATE KEY UPDATE 
  `full_name` = VALUES(`full_name`),
  `password_hash` = VALUES(`password_hash`),
  `role` = VALUES(`role`),
  `status` = VALUES(`status`);

-- 3. PHAN QUYEN WEBSITE
-- Admin (user_id=1) mac dinh co quyen tren toan he thong.
-- Member (user_id=2) chi duoc cap quyen xem Website A (website_id=1), KHONG co quyen Website B (website_id=2).
INSERT INTO `website_permissions` (`user_id`, `website_id`, `can_view`, `can_manage_tracking`, `can_export`, `created_at`)
VALUES 
  (2, 1, 1, 0, 1, NOW())
ON DUPLICATE KEY UPDATE 
  `can_view` = VALUES(`can_view`),
  `can_manage_tracking` = VALUES(`can_manage_tracking`),
  `can_export` = VALUES(`can_export`);

-- 4. KHOI TAO 4 LOAI SU KIEN CHO WEBSITE A
INSERT INTO `event_definitions` (`website_id`, `event_name`, `description`, `enabled`, `created_at`)
VALUES 
  (1, 'page_view', 'Ghi nhận lượt xem các trang trên Website A', 1, NOW()),
  (1, 'button_click', 'Bấm các nút thao tác: Thêm vào giỏ, Xem chi tiết, Gửi liên hệ', 1, NOW()),
  (1, 'download_start', 'Bắt đầu tải tài liệu Catalog hoặc hướng dẫn sản phẩm', 1, NOW()),
  (1, 'download_complete', 'Hoàn tất tải file tài liệu thành công', 1, NOW())
ON DUPLICATE KEY UPDATE 
  `description` = VALUES(`description`), 
  `enabled` = VALUES(`enabled`);

-- 5. KHOI TAO 4 LOAI SU KIEN CHO WEBSITE B
INSERT INTO `event_definitions` (`website_id`, `event_name`, `description`, `enabled`, `created_at`)
VALUES 
  (2, 'page_view', 'Ghi nhận lượt xem các trang trên Website B', 1, NOW()),
  (2, 'button_click', 'Bấm các nút thao tác: Đăng ký dịch vụ, Tư vấn ngay, Khám phá', 1, NOW()),
  (2, 'download_start', 'Bắt đầu tải hồ sơ năng lực công ty hoặc báo cáo dịch vụ', 1, NOW()),
  (2, 'download_complete', 'Hoàn tất tải file hồ sơ năng lực thành công', 1, NOW())
ON DUPLICATE KEY UPDATE 
  `description` = VALUES(`description`), 
  `enabled` = VALUES(`enabled`);
