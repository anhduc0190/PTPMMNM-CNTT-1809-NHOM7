-- ====================================================================
-- HE THONG THONG KE LUOT TRUY CAP VA THAO TAC TREN WEBSITE
-- Database Schema: website_analytics
-- Tuong thich: MySQL 8.0+ / MariaDB 10.4+ (XAMPP Windows)
-- Engine: InnoDB | Charset: utf8mb4 | Collation: utf8mb4_unicode_ci
-- ====================================================================

CREATE DATABASE IF NOT EXISTS `website_analytics`
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE `website_analytics`;

-- 1. BANG WEBSITES: Quan ly danh sach cac website duoc phep theo doi
CREATE TABLE IF NOT EXISTS `websites` (
  `website_id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(150) NOT NULL COMMENT 'Ten website theo doi',
  `domain` VARCHAR(255) NOT NULL COMMENT 'Ten mien hoac dia chi goc (Origin)',
  `tracking_key` VARCHAR(64) NOT NULL UNIQUE COMMENT 'Khoa theo doi cong khai gui kem payload',
  `timezone` VARCHAR(50) NOT NULL DEFAULT 'Asia/Ho_Chi_Minh' COMMENT 'Mui gio bao cao',
  `status` ENUM('active', 'paused', 'archived') NOT NULL DEFAULT 'active' COMMENT 'Trang thai hoat dong',
  `late_event_window_hours` INT NOT NULL DEFAULT 24 COMMENT 'Khoang chap nhan su kien den muon (gio)',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_websites_status` (`status`),
  INDEX `idx_websites_tracking_key` (`tracking_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. BANG USERS: Quan ly nguoi dung dang nhap dashboard
CREATE TABLE IF NOT EXISTS `users` (
  `user_id` INT AUTO_INCREMENT PRIMARY KEY,
  `full_name` VARCHAR(100) NOT NULL,
  `email` VARCHAR(150) NOT NULL UNIQUE,
  `password_hash` VARCHAR(255) NOT NULL,
  `role` ENUM('admin', 'member') NOT NULL DEFAULT 'member',
  `status` ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_users_role` (`role`),
  INDEX `idx_users_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. BANG WEBSITE_PERMISSIONS: Phan quyen member theo tung website
CREATE TABLE IF NOT EXISTS `website_permissions` (
  `permission_id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NOT NULL,
  `website_id` INT NOT NULL,
  `can_view` TINYINT(1) NOT NULL DEFAULT 1,
  `can_manage_tracking` TINYINT(1) NOT NULL DEFAULT 0,
  `can_export` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY `uk_user_website` (`user_id`, `website_id`),
  CONSTRAINT `fk_perm_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE,
  CONSTRAINT `fk_perm_website` FOREIGN KEY (`website_id`) REFERENCES `websites` (`website_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. BANG EVENT_DEFINITIONS: Dinh nghia cac su kien hop le theo website
CREATE TABLE IF NOT EXISTS `event_definitions` (
  `definition_id` INT AUTO_INCREMENT PRIMARY KEY,
  `website_id` INT NOT NULL,
  `event_name` VARCHAR(64) NOT NULL,
  `description` VARCHAR(255) NULL,
  `enabled` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY `uk_website_event` (`website_id`, `event_name`),
  INDEX `idx_event_def_lookup` (`website_id`, `event_name`, `enabled`),
  CONSTRAINT `fk_event_def_website` FOREIGN KEY (`website_id`) REFERENCES `websites` (`website_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5. BANG SESSIONS: Luu phien truy cap gia danh (khong fingerprint, khong IP day du)
CREATE TABLE IF NOT EXISTS `sessions` (
  `session_id` VARCHAR(64) NOT NULL,
  `website_id` INT NOT NULL,
  `started_at` DATETIME NOT NULL,
  `last_seen_at` DATETIME NOT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`website_id`, `session_id`),
  INDEX `idx_session_lookup` (`session_id`),
  INDEX `idx_session_last_seen` (`website_id`, `last_seen_at`),
  CONSTRAINT `fk_session_website` FOREIGN KEY (`website_id`) REFERENCES `websites` (`website_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 6. BANG EVENTS: Luu cac su kien hop le da tiep nhan
CREATE TABLE IF NOT EXISTS `events` (
  `event_row_id` BIGINT AUTO_INCREMENT PRIMARY KEY,
  `website_id` INT NOT NULL,
  `event_id` VARCHAR(64) NOT NULL COMMENT 'UUID duy nhat tu client',
  `session_id` VARCHAR(64) NOT NULL,
  `event_name` VARCHAR(64) NOT NULL,
  `page_path` VARCHAR(255) NOT NULL,
  `occurred_at` DATETIME NOT NULL COMMENT 'Thoi diem su kien xay ra tai client',
  `received_at` DATETIME NOT NULL COMMENT 'Thoi diem may chu tiep nhan',
  `payload_hash` CHAR(64) NOT NULL COMMENT 'SHA-256 chuan hoa backend',
  `properties` JSON NULL COMMENT 'Chi luu cac thuoc tinh duoc phep, khong nhay cam',
  UNIQUE KEY `uk_site_event` (`website_id`, `event_id`),
  INDEX `idx_site_occurred` (`website_id`, `occurred_at`),
  INDEX `idx_site_name_occurred` (`website_id`, `event_name`, `occurred_at`),
  INDEX `idx_site_page` (`website_id`, `page_path`),
  INDEX `idx_site_session` (`website_id`, `session_id`, `occurred_at`),
  CONSTRAINT `fk_event_session` FOREIGN KEY (`website_id`, `session_id`) 
    REFERENCES `sessions` (`website_id`, `session_id`) ON DELETE CASCADE,
  CONSTRAINT `fk_event_name_def` FOREIGN KEY (`website_id`, `event_name`) 
    REFERENCES `event_definitions` (`website_id`, `event_name`) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 7. BANG EVENT_INGEST_ERRORS: Ghi nhan loi tiep nhan (chong do vo, giam sat tan suat)
CREATE TABLE IF NOT EXISTS `event_ingest_errors` (
  `error_id` BIGINT AUTO_INCREMENT PRIMARY KEY,
  `website_id` INT NULL,
  `event_id` VARCHAR(64) NULL,
  `error_code` VARCHAR(50) NOT NULL COMMENT 'PAYLOAD_CONFLICT, EVENT_NOT_DEFINED, LATE_EVENT_EXCEEDED...',
  `payload_hash` CHAR(64) NULL,
  `occurred_at` DATETIME NULL,
  `received_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `details` VARCHAR(255) NULL,
  INDEX `idx_err_site_time` (`website_id`, `received_at`),
  INDEX `idx_err_code` (`error_code`),
  CONSTRAINT `fk_err_website` FOREIGN KEY (`website_id`) REFERENCES `websites` (`website_id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 8. BANG REPORT_QUERIES: Nhat ky truy van bao cao (Audit Trail)
CREATE TABLE IF NOT EXISTS `report_queries` (
  `query_id` BIGINT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NOT NULL,
  `website_id` INT NULL,
  `query_type` VARCHAR(50) NOT NULL COMMENT 'overview, traffic, pages, events, funnels, export',
  `filters` JSON NULL,
  `executed_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_query_user` (`user_id`, `executed_at`),
  CONSTRAINT `fk_query_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE,
  CONSTRAINT `fk_query_website` FOREIGN KEY (`website_id`) REFERENCES `websites` (`website_id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
