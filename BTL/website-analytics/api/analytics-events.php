<?php
// ====================================================================
// REST API: PHAN BO LOAI SU KIEN (api/analytics-events.php)
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
        json_response(['success' => true, 'events' => []]);
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
        `event_name`,
        COUNT(*) AS `total_count`,
        COUNT(DISTINCT `session_id`) AS `unique_sessions`,
        MIN(`occurred_at`) AS `first_seen`,
        MAX(`occurred_at`) AS `last_seen`
    FROM `events`
    WHERE `website_id` = ? AND `occurred_at` BETWEEN ? AND ?
    GROUP BY `event_name`
    ORDER BY `total_count` DESC
');
$stmt->execute([$websiteId, $fromDateTime, $toDateTime]);
$events = $stmt->fetchAll();

log_audit_query($user['user_id'], $websiteId, 'events', [
    'from_date' => $fromDate,
    'to_date'   => $toDate
]);

json_response([
    'success'    => true,
    'website_id' => $websiteId,
    'events'     => $events
]);
