<?php
// ====================================================================
// CONFIG: CAU HINH CHUNG UNG DUNG
// ====================================================================

define('APP_NAME', 'WebAnalytics Platform');
define('APP_VERSION', '1.0.0');
define('APP_DEFAULT_TIMEZONE', 'Asia/Ho_Chi_Minh');

// Thiet lap mui gio mac dinh PHP
date_default_timezone_set(APP_DEFAULT_TIMEZONE);

// Gioi han thoi gian su kien den muon mac dinh (gio)
define('DEFAULT_LATE_WINDOW_HOURS', 24);

// Gioi han kich thuoc JSON payload (64 KB)
define('MAX_PAYLOAD_BYTES', 65536);

// Danh sach thuoc tinh duoc phep (Whitelisting properties - phong ngua du lieu nhay cam)
define('ALLOWED_PROPERTIES', [
    'element_id',
    'element_class',
    'button_text',
    'target_url',
    'file_name',
    'file_size_kb',
    'download_url',
    'screen_width',
    'screen_height',
    'viewport_width',
    'viewport_height',
    'referrer'
]);

// Khoi tao session an toan neu chua co
if (session_status() === PHP_SESSION_NONE && php_sapi_name() !== 'cli') {
    ini_set('session.cookie_httponly', 1);
    ini_set('session.use_only_cookies', 1);
    ini_set('session.cookie_samesite', 'Lax');
    session_start();
}
