<?php
declare(strict_types=1);
/**
 * sql.php — Áp dụng schema SQL lên database đã cấu hình.
 */
if (PHP_SAPI !== 'cli') die("Chỉ chạy từ dòng lệnh.\n");
require_once __DIR__ . '/../src/Database.php';

$schemaPath = __DIR__ . '/../database/schema.sql';
if (!is_file($schemaPath)) {
    echo "Không tìm thấy: $schemaPath\n"; exit(2);
}

try {
    $pdo = db();
    // Tách từng statement theo dấu ";"
    $sql  = file_get_contents($schemaPath);
    // Loại comment dạng --
    $sql  = preg_replace('/--[^\n]*/', '', $sql);
    $stmts = array_filter(array_map('trim', explode(';', $sql)));

    $count = 0;
    foreach ($stmts as $stmt) {
        if ($stmt === '') continue;
        $pdo->exec($stmt);
        $count++;
    }
    echo "✅ Đã chạy $count câu lệnh SQL.\n";
    echo "Tạo dữ liệu mẫu: php fixtures/seed.php\n";
} catch (Throwable $e) {
    echo "❌ Lỗi: " . $e->getMessage() . "\n";
    exit(2);
}
