<?php
declare(strict_types=1);
/**
 * Database.php — Kết nối MySQL/MariaDB bằng PDO.
 * Đọc cấu hình từ .local/db.json (không commit file này).
 */

$_dbConfigPath = __DIR__ . '/../.local/db.json';
if (is_file($_dbConfigPath)) {
    $__cfg = json_decode((string) file_get_contents($_dbConfigPath), true);
    if (!is_array($__cfg)) {
        throw new RuntimeException('DATABASE_CONFIG_INVALID');
    }
    foreach (['DB_HOST', 'DB_PORT', 'DB_NAME', 'DB_USER', 'DB_PASS'] as $__k) {
        if (isset($__cfg[$__k]) && is_string($__cfg[$__k])) {
            putenv($__k . '=' . $__cfg[$__k]);
        }
    }
    unset($__cfg, $__k);
}
unset($_dbConfigPath);

if (!function_exists('db')) {
    function db(): PDO
    {
        static $pdo = null;
        if ($pdo !== null) {
            return $pdo;
        }
        // Cấu hình mặc định chuẩn theo XAMPP (127.0.0.1, 3306, root, không mật khẩu)
        $host = getenv('DB_HOST') ?: '127.0.0.1';
        $port = getenv('DB_PORT') ?: '3306';
        $name = getenv('DB_NAME') ?: 'web_analytics';
        $user = getenv('DB_USER') ?: 'root';
        $pass = getenv('DB_PASS') !== false ? (string) getenv('DB_PASS') : '';

        $dsn = "mysql:host={$host};port={$port};dbname={$name};charset=utf8mb4";
        try {
            $pdo = new PDO($dsn, $user, $pass, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
        } catch (PDOException $e) {
            // Tự động tạo database web_analytics trên XAMPP nếu chưa tồn tại
            if ($e->getCode() === 1049 || str_contains($e->getMessage(), 'Unknown database')) {
                $rootPdo = new PDO("mysql:host={$host};port={$port};charset=utf8mb4", $user, $pass);
                $rootPdo->exec("CREATE DATABASE IF NOT EXISTS `{$name}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
                $pdo = new PDO($dsn, $user, $pass, [
                    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES   => false,
                ]);
            } else {
                throw $e;
            }
        }

        // Đặt múi giờ session về UTC để lưu nhất quán
        $pdo->exec("SET time_zone = '+00:00'");
        return $pdo;
    }
}

/**
 * Thực thi câu truy vấn có tham số; trả về PDOStatement.
 */
if (!function_exists('dbQuery')) {
    function dbQuery(PDO $pdo, string $sql, array $params = []): PDOStatement
    {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }
}
