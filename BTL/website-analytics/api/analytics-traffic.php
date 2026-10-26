<?php
// ====================================================================
// REST API: XU HUONG LUOT TRUY CAP THEO THOI GIAN (api/analytics-traffic.php)
// Ho tro bieu do duong Chart.js, phan biet page_view vs actions theo ngay
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
    $sites = get_accessible_websites();
    if (empty($sites)) {
        json_response(['success' => true, 'labels' => [], 'views' => [], 'actions' => [], 'sessions' => []]);
    }
    $websiteId = (int)$sites[0]['website_id'];
}

require_website_access($websiteId, 'view');

$fromDate = $_GET['from_date'] ?? date('Y-m-d', strtotime('-14 days'));
$toDate   = $_GET['to_date'] ?? date('Y-m-d');
$fromDateTime = $fromDate . ' 00:00:00';
$toDateTime   = $toDate . ' 23:59:59';

// Truy van gom nhom theo ngay (DATE(occurred_at))
$stmt = $pdo->prepare('
    SELECT 
        DATE(`occurred_at`) AS `report_date`,
        SUM(CASE WHEN `event_name` = "page_view" THEN 1 ELSE 0 END) AS `views_count`,
        SUM(CASE WHEN `event_name` != "page_view" THEN 1 ELSE 0 END) AS `actions_count`,
        COUNT(DISTINCT `session_id`) AS `sessions_count`
    FROM `events`
    WHERE `website_id` = ? AND `occurred_at` BETWEEN ? AND ?
    GROUP BY DATE(`occurred_at`)
    ORDER BY `report_date` ASC
');
$stmt->execute([$websiteId, $fromDateTime, $toDateTime]);
$rows = $stmt->fetchAll();

// Xay dung chuoi ngay lien tuc de bieu do Chart.js khong bi thung ngay
$dateMap = [];
foreach ($rows as $r) {
    $dateMap[$r['report_date']] = [
        'views'    => (int)$r['views_count'],
        'actions'  => (int)$r['actions_count'],
        'sessions' => (int)$r['sessions_count']
    ];
}

$current = strtotime($fromDate);
$end     = strtotime($toDate);
$labels  = [];
$views   = [];
$actions = [];
$sessions = [];

while ($current <= $end) {
    $dStr = date('Y-m-d', $current);
    $labels[]   = date('d/m', $current);
    $views[]    = $dateMap[$dStr]['views'] ?? 0;
    $actions[]  = $dateMap[$dStr]['actions'] ?? 0;
    $sessions[] = $dateMap[$dStr]['sessions'] ?? 0;
    $current    = strtotime('+1 day', $current);
}

log_audit_query($user['user_id'], $websiteId, 'traffic', [
    'from_date' => $fromDate,
    'to_date'   => $toDate
]);

json_response([
    'success'    => true,
    'website_id' => $websiteId,
    'labels'     => $labels,
    'views'      => $views,
    'actions'    => $actions,
    'sessions'   => $sessions
]);
