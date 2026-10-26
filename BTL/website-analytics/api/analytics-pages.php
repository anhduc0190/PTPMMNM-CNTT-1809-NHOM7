<?php
// ====================================================================
// REST API: TRANG PHO BIEN VA THONG KE THEO PAGE_PATH (api/analytics-pages.php)
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
        json_response(['success' => true, 'pages' => []]);
    }
    $websiteId = (int)$sites[0]['website_id'];
}

require_website_access($websiteId, 'view');

$fromDate = $_GET['from_date'] ?? date('Y-m-d', strtotime('-30 days'));
$toDate   = $_GET['to_date'] ?? date('Y-m-d');
$fromDateTime = $fromDate . ' 00:00:00';
$toDateTime   = $toDate . ' 23:59:59';

$stmt = $pdo->prepare('
    SELECT 
        `page_path`,
        SUM(CASE WHEN `event_name` = "page_view" THEN 1 ELSE 0 END) AS `views_count`,
        SUM(CASE WHEN `event_name` != "page_view" THEN 1 ELSE 0 END) AS `actions_count`,
        COUNT(DISTINCT `session_id`) AS `unique_sessions`,
        MAX(`occurred_at`) AS `last_visited`
    FROM `events`
    WHERE `website_id` = ? AND `occurred_at` BETWEEN ? AND ?
    GROUP BY `page_path`
    ORDER BY `views_count` DESC, `actions_count` DESC
    LIMIT 50
');
$stmt->execute([$websiteId, $fromDateTime, $toDateTime]);
$pages = $stmt->fetchAll();

log_audit_query($user['user_id'], $websiteId, 'pages', [
    'from_date' => $fromDate,
    'to_date'   => $toDate
]);

json_response([
    'success'    => true,
    'website_id' => $websiteId,
    'count'      => count($pages),
    'pages'      => $pages
]);
