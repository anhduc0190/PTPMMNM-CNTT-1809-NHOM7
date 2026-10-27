<?php
// ====================================================================
// SCRIPT: KHOI TAO CSDL VOI MA HOA UTF-8 CHUAN (scripts/init-db.php)
// Bao dam 100% tieng Viet co dau khong bao gio bi loi font '???'
// ====================================================================

require_once __DIR__ . '/../config/database.php';

echo "Dang khoi tao co so du lieu website_analytics (UTF-8)...\n";

try {
    // 1. Ket noi MySQL
    $pdo = new PDO(
        sprintf('mysql:host=%s;port=%s;charset=utf8mb4', DB_HOST, DB_PORT),
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci"
        ]
    );

    // 2. Doc va thuc thi schema.sql
    $schemaFile = __DIR__ . '/../database/schema.sql';
    if (!file_exists($schemaFile)) {
        throw new Exception("Khong tim thay file: {$schemaFile}");
    }
    $schemaSql = file_get_contents($schemaFile);
    $pdo->exec($schemaSql);
    echo " -> Da tao bang co so du lieu (schema.sql): THANH CONG\n";

    // 3. Ket noi truc tiep database website_analytics
    $pdo->exec("USE `website_analytics`; SET NAMES utf8mb4;");

    // 4. Doc va thuc thi seed.sql
    $seedFile = __DIR__ . '/../database/seed.sql';
    if (!file_exists($seedFile)) {
        throw new Exception("Khong tim thay file: {$seedFile}");
    }
    $seedSql = file_get_contents($seedFile);
    $pdo->exec($seedSql);
    echo " -> Da nap du lieu khoi tao (seed.sql) bang tieng Viet chuan: THANH CONG\n";

    echo "====================================================\n";
    echo "HOAN TAT KHOI TAO CSDL CHUAN UTF-8!\n";
    echo "====================================================\n";

} catch (Exception $e) {
    echo "LOI KHOI TAO CSDL: " . $e->getMessage() . "\n";
    exit(1);
}
