<?php
declare(strict_types=1);
/**
 * db-config.php — Cấu hình kết nối MySQL/MariaDB.
 * Tạo file .local/db.json từ biến môi trường hoặc nhập thủ công.
 */
if (PHP_SAPI !== 'cli') {
    die("Chỉ chạy từ dòng lệnh: php tools/db-config.php\n");
}

$configPath = __DIR__ . '/../.local/db.json';
@mkdir(dirname($configPath), 0700, true);

echo "=== Cấu hình kết nối MySQL/MariaDB ===\n";
echo "Nhấn Enter để dùng giá trị mặc định (trong ngoặc).\n\n";

function prompt(string $label, string $default = ''): string {
    $hint = $default !== '' ? " [$default]" : '';
    echo "$label$hint: ";
    $val = trim(fgets(STDIN));
    return $val !== '' ? $val : $default;
}

$config = [
    'DB_HOST' => prompt('Host',     '127.0.0.1'),
    'DB_PORT' => prompt('Port',     '3306'),
    'DB_NAME' => prompt('Database', 'web_analytics'),
    'DB_USER' => prompt('User',     'root'),
    'DB_PASS' => prompt('Password', ''),
];

file_put_contents($configPath, json_encode($config, JSON_PRETTY_PRINT));
chmod($configPath, 0600);

echo "\n✅ Đã lưu cấu hình tại: $configPath\n";
echo "Thử kết nối...\n";

// Thử kết nối
require_once __DIR__ . '/../src/Database.php';
foreach ($config as $k => $v) putenv("$k=$v");
try {
    $pdo = db();
    echo "✅ Kết nối thành công!\n";
    echo "Tạo schema: php tools/sql.php\n";
} catch (Throwable $e) {
    echo "❌ Lỗi kết nối: " . $e->getMessage() . "\n";
    echo "Kiểm tra lại thông tin database.\n";
    exit(2);
}
