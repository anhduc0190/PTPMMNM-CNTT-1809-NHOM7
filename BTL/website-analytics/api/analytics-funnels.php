<?php
// ====================================================================
// REST API: PHAN TICH CHUOI THAO TAC (FUNNEL ANALYSIS)
// Chuoi mau: page_view -> download_start -> download_complete
// Chuan hoa theo cung website, cung session, dung thu tu thoi gian
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
        json_response([
            'success' => true,
            'funnel'  => [
                'step1_sessions' => 0,
                'step2_sessions' => 0,
                'step3_sessions' => 0,
                'conversion_rate' => 0
            ],
            'session_details' => []
        ]);
    }
    $websiteId = (int)$sites[0]['website_id'];
}

require_website_access($websiteId, 'view');

$fromDate = $_GET['from_date'] ?? date('Y-m-d', strtotime('-30 days'));
$toDate   = $_GET['to_date'] ?? date('Y-m-d');
$fromDateTime = $fromDate . ' 00:00:00';
$toDateTime   = $toDate . ' 23:59:59';

// 1. Lay tat ca su kien lien quan den chuoi funnel theo website va thoi gian, sap xep theo session va thoi gian xay ra
$stmt = $pdo->prepare('
    SELECT `session_id`, `event_name`, `page_path`, `occurred_at`, `event_id`, `event_row_id`
    FROM `events`
    WHERE `website_id` = ? 
      AND `event_name` IN ("page_view", "download_start", "download_complete")
      AND `occurred_at` BETWEEN ? AND ?
    ORDER BY `session_id` ASC, `occurred_at` ASC, `event_row_id` ASC
');
$stmt->execute([$websiteId, $fromDateTime, $toDateTime]);
$events = $stmt->fetchAll();

// 2. Nhom cac su kien theo session_id va danh gia thu tu hop le
$sessionGroups = [];
foreach ($events as $evt) {
    $sessionGroups[$evt['session_id']][] = $evt;
}

$step1Sessions = 0; // page_view
$step2Sessions = 0; // page_view -> download_start
$step3Sessions = 0; // page_view -> download_start -> download_complete (HOAN TAT)

$sessionDetails = [];

foreach ($sessionGroups as $sessId => $sEvents) {
    $hasStep1 = false;
    $hasStep2 = false;
    $hasStep3 = false;

    $tStep1 = null;
    $tStep2 = null;
    $tStep3 = null;

    $step1Event = null;
    $step2Event = null;
    $step3Event = null;

    // Duyet theo thu tu thoi gian thuc te
    foreach ($sEvents as $e) {
        $name = $e['event_name'];
        $time = strtotime($e['occurred_at']);

        // Buoc 1: page_view
        if ($name === 'page_view' && !$hasStep1) {
            $hasStep1 = true;
            $tStep1 = $time;
            $step1Event = $e;
            continue;
        }

        // Buoc 2: download_start (phai xay ra CUNG HOAC SAU step 1)
        if ($name === 'download_start' && $hasStep1 && !$hasStep2) {
            if ($time >= $tStep1) {
                $hasStep2 = true;
                $tStep2 = $time;
                $step2Event = $e;
                continue;
            }
        }

        // Buoc 3: download_complete (phai xay ra CUNG HOAC SAU step 2)
        if ($name === 'download_complete' && $hasStep2 && !$hasStep3) {
            if ($time >= $tStep2) {
                $hasStep3 = true;
                $tStep3 = $time;
                $step3Event = $e;
                break; // Da hoan tat chuoi, khong dem trung lap
            }
        }
    }

    if ($hasStep1) {
        $step1Sessions++;
    }
    if ($hasStep2) {
        $step2Sessions++;
    }
    if ($hasStep3) {
        $step3Sessions++;
    }

    $status = 'dropped_step_1';
    if ($hasStep3) {
        $status = 'completed';
    } elseif ($hasStep2) {
        $status = 'dropped_step_2';
    }

    $sessionDetails[] = [
        'session_id'   => $sessId,
        'has_step1'    => $hasStep1,
        'has_step2'    => $hasStep2,
        'has_step3'    => $hasStep3,
        'status'       => $status,
        't_step1'      => $step1Event ? $step1Event['occurred_at'] : null,
        't_step2'      => $step2Event ? $step2Event['occurred_at'] : null,
        't_step3'      => $step3Event ? $step3Event['occurred_at'] : null,
        'total_events' => count($sEvents)
    ];
}

$conversionRate = $step1Sessions > 0 ? round(($step3Sessions / $step1Sessions) * 100, 2) : 0;
$step2DropRate   = $step1Sessions > 0 ? round((($step1Sessions - $step2Sessions) / $step1Sessions) * 100, 2) : 0;
$step3DropRate   = $step2Sessions > 0 ? round((($step2Sessions - $step3Sessions) / $step2Sessions) * 100, 2) : 0;

log_audit_query($user['user_id'], $websiteId, 'funnels', [
    'from_date' => $fromDate,
    'to_date'   => $toDate
]);

json_response([
    'success'    => true,
    'website_id' => $websiteId,
    'funnel'     => [
        'step1_name'      => 'Xem trang (page_view)',
        'step1_count'     => $step1Sessions,
        'step2_name'      => 'Báº¯t Ä‘áº§u táº£i (download_start)',
        'step2_count'     => $step2Sessions,
        'step3_name'      => 'HoĂ n táº¥t táº£i (download_complete)',
        'step3_count'     => $step3Sessions,
        'completed_count' => $step3Sessions,
        'dropped_count'   => $step1Sessions - $step3Sessions,
        'conversion_rate' => $conversionRate,
        'step2_drop_rate' => $step2DropRate,
        'step3_drop_rate' => $step3DropRate
    ],
    'session_details' => array_slice($sessionDetails, 0, 50) // Giá»›i háº¡n 50 session Ä‘á»ƒ hiá»ƒn thá»‹ báº£ng
]);
