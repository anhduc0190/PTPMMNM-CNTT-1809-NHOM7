<?php
declare(strict_types=1);
/**
 * db-check.php — Kiểm tra kết nối và trạng thái database.
 */
require_once __DIR__ . '/../src/Database.php';
try {
    $pdo = db();
    $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
    echo "KET_NOI=OK\n";
    echo "BANG=" . implode(',', $tables) . "\n";
    if (in_array('websites', $tables)) {
        $count = $pdo->query("SELECT COUNT(*) FROM websites")->fetchColumn();
        echo "WEBSITE_COUNT=$count\n";
        $count = $pdo->query("SELECT COUNT(*) FROM events")->fetchColumn();
        echo "EVENT_COUNT=$count\n";
    }
} catch (Throwable $e) {
    echo "KET_NOI=LOI: " . $e->getMessage() . "\n";
    exit(2);
}
