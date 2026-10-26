<?php
// ====================================================================
// TRANG DIEU HUONG CHINH (index.php)
// ====================================================================

require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/helpers.php';

$base = get_base_url();

if (is_authenticated()) {
    header("Location: {$base}/dashboard.php");
    exit;
} else {
    header("Location: {$base}/login.php");
    exit;
}
