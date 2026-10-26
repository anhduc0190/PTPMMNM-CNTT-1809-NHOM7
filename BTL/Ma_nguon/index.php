<?php
declare(strict_types=1);
/**
 * Chuyển hướng tự động vào thư mục public/ khi mở qua XAMPP Apache
 * Ví dụ: http://localhost/web-analytics/ -> http://localhost/web-analytics/public/
 */
header('Location: public/index.php');
exit;
