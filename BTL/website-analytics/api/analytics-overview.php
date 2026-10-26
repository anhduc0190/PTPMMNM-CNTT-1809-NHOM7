<?php
// ====================================================================
// REST API: TONG QUAN CHI SO ANALYTICS (api/analytics-overview.php)
// ====================================================================

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/permissions.php';

header('Content-Type: application/json; charset=utf-8');
require_login();

$user = get_auth_user();
$pdo  = get_db_connection();

$websiteId = (int)($_GET['website_id'] ?? 0);
if ($websiteId <= 0) {
    // Neu khong truyen website_id, lay website dau tien ma user co quyen
    $sites = get_accessible_websites();
    if (empty($sites)) {
        json_response([
            'success' => true,
            'summary' => [
                'total_views'          => 0,
                'total_actions'        => 0,
                'active_sessions'      => 0,
                'download_conversions' => 0,
                'ingest_errors'        => 0
            ],
            'recent_events' => []
        ]);
    }
    $websiteId = (int)$sites[0]['website_id'];
}

require_website_access($websiteId, 'view');

// Doc bo loc thoi gian
$fromDate = $_GET['from_date'] ?? date('Y-m-d', strtotime('-30 days'));
$toDate   = $_GET['to_date'] ?? date('Y-m-d');
$fromDateTime = $fromDate . ' 00:00:00';
$toDateTime   = $toDate . ' 23:59:59';

// 1. Tong so Luot xem trang (page_view)
$stmtViews = $pdo->prepare('
    SELECT COUNT(*) FROM `events` 
    WHERE `website_id` = ? AND `event_name` = "page_view" AND `occurred_at` BETWEEN ? AND ?
');
$stmtViews->execute([$websiteId, $fromDateTime, $toDateTime]);
$totalViews = (int)$stmtViews->fetchColumn();

// 2. Tong so Lan thao tac nguoi dung (non-page_view: button_click, download_start, download_complete)
$stmtActions = $pdo->prepare('
    SELECT COUNT(*) FROM `events` 
    WHERE `website_id` = ? AND `event_name` != "page_view" AND `occurred_at` BETWEEN ? AND ?
');
$stmtActions->execute([$websiteId, $fromDateTime, $toDateTime]);
$totalActions = (int)$stmtActions->fetchColumn();

// 3. So phien truy cap co hoat dong (Active Sessions)
$stmtSessions = $pdo->prepare('
    SELECT COUNT(DISTINCT `session_id`) FROM `events` 
    WHERE `website_id` = ? AND `occurred_at` BETWEEN ? AND ?
');
$stmtSessions->execute([$websiteId, $fromDateTime, $toDateTime]);
$activeSessions = (int)$stmtSessions->fetchColumn();

// 4. So luot hoan tat tai tai lieu (download_complete conversions)
$stmtDl = $pdo->prepare('
    SELECT COUNT(*) FROM `events` 
    WHERE `website_id` = ? AND `event_name` = "download_complete" AND `occurred_at` BETWEEN ? AND ?
');
$stmtDl->execute([$websiteId, $fromDateTime, $toDateTime]);
$downloadConversions = (int)$stmtDl->fetchColumn();

// 5. So luot loi tiep nhan ghi nhan trong ky
$stmtErr = $pdo->prepare('
    SELECT COUNT(*) FROM `event_ingest_errors` 
    WHERE `website_id` = ? AND `received_at` BETWEEN ? AND ?
');
$stmtErr->execute([$websiteId, $fromDateTime, $toDateTime]);
$ingestErrors = (int)$stmtErr->fetchColumn();

// 6. Danh sach 10 su kien gan day nhat de kiem tra doi chieu
$stmtRecent = $pdo->prepare('
    SELECT `event_row_id`, `event_id`, `session_id`, `event_name`, `page_path`, `occurred_at`, `received_at`, `payload_hash`
    FROM `events`
    WHERE `website_id` = ?
    ORDER BY `occurred_at` DESC, `event_row_id` DESC
    LIMIT 10
');
$stmtRecent->execute([$websiteId]);
$recentEvents = $stmtRecent->fetchAll();

// Ghi audit log
log_audit_query($user['user_id'], $websiteId, 'overview', [
    'from_date' => $fromDate,
    'to_date'   => $toDate
]);

json_response([
    'success'    => true,
    'website_id' => $websiteId,
    'from_date'  => $fromDate,
    'to_date'    => $toDate,
    'summary'    => [
        'total_views'          => $totalViews,
        'total_actions'        => $totalActions,
        'active_sessions'      => $activeSessions,
        'download_conversions' => $downloadConversions,
        'ingest_errors'        => $ingestErrors,
        'total_events'         => $totalViews + $totalActions
    ],
    'recent_events' => $recentEvents
]);
