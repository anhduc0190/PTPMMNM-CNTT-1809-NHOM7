<?php
// ====================================================================
// REST API: XUAT BAO CAO CSV (api/analytics-export.php)
// Ho tro loc, chong CSV Formula Injection va dinh dang UTF-8 BOM chuan Excel
// ====================================================================

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/permissions.php';

require_login();

$user = get_auth_user();
$pdo  = get_db_connection();

$websiteId = (int)($_GET['website_id'] ?? 0);
if ($websiteId <= 0) {
    $sites = get_accessible_websites();
    if (empty($sites)) {
        die('Khong co website nao de xuat.');
    }
    $websiteId = (int)$sites[0]['website_id'];
}

// Kiem tra quyen xuat du lieu (can_export)
require_website_access($websiteId, 'export');

$fromDate = $_GET['from_date'] ?? date('Y-m-d', strtotime('-30 days'));
$toDate   = $_GET['to_date'] ?? date('Y-m-d');
$eventName = trim($_GET['event_name'] ?? '');
$pagePath  = trim($_GET['page_path'] ?? '');

$fromDateTime = $fromDate . ' 00:00:00';
$toDateTime   = $toDate . ' 23:59:59';

// Xay dung cau truy van co dieu kien loc
$sql = '
    SELECT e.`event_row_id`, w.`name` AS `website_name`, e.`event_id`, e.`session_id`, 
           e.`event_name`, e.`page_path`, e.`occurred_at`, e.`received_at`, e.`payload_hash`, e.`properties`
    FROM `events` e
    INNER JOIN `websites` w ON e.`website_id` = w.`website_id`
    WHERE e.`website_id` = ? AND e.`occurred_at` BETWEEN ? AND ?
';
$params = [$websiteId, $fromDateTime, $toDateTime];

if (!empty($eventName)) {
    $sql .= ' AND e.`event_name` = ?';
    $params[] = $eventName;
}

if (!empty($pagePath)) {
    $sql .= ' AND e.`page_path` = ?';
    $params[] = $pagePath;
}

$sql .= ' ORDER BY e.`occurred_at` ASC, e.`event_row_id` ASC';

$stmt = $pdo->prepare($sql);
$stmt->execute($params);

// Ghi audit log
log_audit_query($user['user_id'], $websiteId, 'export_csv', [
    'from_date'  => $fromDate,
    'to_date'    => $toDate,
    'event_name' => $eventName,
    'page_path'  => $pagePath
]);

// Thiet lap HTTP Header cho file CSV
$filename = 'analytics_export_site' . $websiteId . '_' . date('Ymd_His') . '.csv';
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Pragma: no-cache');
header('Expires: 0');

$output = fopen('php://output', 'w');

// Xuat UTF-8 BOM de Excel nhan dien dung tieng Viet co dau
fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

// Dong tieu de CSV
fputcsv($output, [
    'STT (Row ID)',
    'TĂªn Website',
    'Event ID (Client UUID)',
    'Session ID (PhiĂªn)',
    'TĂªn Sá»± Kiá»‡n',
    'ÄÆ°á»ng Dáº«n Trang (Path)',
    'Thá»i Äiá»ƒm Xáº£y Ra (Client)',
    'Thá»i Äiá»ƒm Nháº­n (Server)',
    'MĂ£ BÄƒm Payload (SHA-256)',
    'Thuá»™c TĂ­nh Cho PhĂ©p (JSON)'
]);

/**
 * Phong ngua CSV Formula Injection: Neu ky tu dau tien la =, +, -, @ thi them dau nhay don '
 */
function sanitize_csv_cell($val) {
    $str = (string)$val;
    if (strlen($str) > 0 && in_array($str[0], ['=', '+', '-', '@'], true)) {
        return "'" . $str;
    }
    return $str;
}

while ($row = $stmt->fetch()) {
    fputcsv($output, [
        sanitize_csv_cell($row['event_row_id']),
        sanitize_csv_cell($row['website_name']),
        sanitize_csv_cell($row['event_id']),
        sanitize_csv_cell($row['session_id']),
        sanitize_csv_cell($row['event_name']),
        sanitize_csv_cell($row['page_path']),
        sanitize_csv_cell($row['occurred_at']),
        sanitize_csv_cell($row['received_at']),
        sanitize_csv_cell($row['payload_hash']),
        sanitize_csv_cell($row['properties'])
    ]);
}

fclose($output);
exit;
