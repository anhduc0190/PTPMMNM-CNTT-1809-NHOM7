<?php
// ====================================================================
// CONFIG: DATABASE CONNECTION (PDO)
// ====================================================================

define('DB_HOST', getenv('DB_HOST') ?: '127.0.0.1');
define('DB_PORT', getenv('DB_PORT') ?: '3306');
define('DB_NAME', getenv('DB_NAME') ?: 'website_analytics');
define('DB_USER', getenv('DB_USER') ?: 'root');
define('DB_PASS', getenv('DB_PASS') !== false ? getenv('DB_PASS') : '');
define('DB_CHARSET', 'utf8mb4');

/**
 * Tra ve ket noi PDO toi CSDL MariaDB/MySQL tren XAMPP.
 * @return PDO
 */
function get_db_connection(): PDO {
    static $pdo = null;

    if ($pdo === null) {
        $dsn = sprintf(
            'mysql:host=%s;port=%s;dbname=%s;charset=%s',
            DB_HOST,
            DB_PORT,
            DB_NAME,
            DB_CHARSET
        );

        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES " . DB_CHARSET . " COLLATE utf8mb4_unicode_ci"
        ];

        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            // An chi tiet loi noi bo khi tra ve phia client
            error_log("[DB CONNECTION ERROR] " . $e->getMessage());
            if (php_sapi_name() === 'cli') {
                throw $e;
            }
            http_response_code(500);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                'success' => false,
                'error'   => 'DATABASE_CONNECTION_ERROR',
                'message' => 'Không thể kết nối đến cơ sở dữ liệu. Vui lòng kiểm tra dịch vụ MySQL trên XAMPP.'
            ]);
            exit;
        }
    }

    return $pdo;
}
