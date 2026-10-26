<?php
// ====================================================================
// DANG XUAT NGUOI DUNG (logout.php)
// ====================================================================

require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/helpers.php';

logout_user();

$base = get_base_url();
header("Location: {$base}/login.php");
exit;
