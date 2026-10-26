<?php
// ====================================================================
// SCRIPT: DOI CHIEU VA XAC THUC BAO CAO (scripts/verify-reports.php)
// Doi chieu giua CSDL thuc te va Ground Truth (Dap an xac dinh)
// ====================================================================

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';

function run_report_verification(bool $silent = false): array {
    $pdo = get_db_connection();

    // 1. Kiem tra tong su kien hop le trong bang events
    $totalEvents = (int)$pdo->query('SELECT COUNT(*) FROM `events`')->fetchColumn();
    $eventsSiteA = (int)$pdo->query('SELECT COUNT(*) FROM `events` WHERE `website_id` = 1')->fetchColumn();
    $eventsSiteB = (int)$pdo->query('SELECT COUNT(*) FROM `events` WHERE `website_id` = 2')->fetchColumn();

    // 2. Kiem tra so loi ghi nhan trong event_ingest_errors
    $totalErrors = (int)$pdo->query('SELECT COUNT(*) FROM `event_ingest_errors`')->fetchColumn();
    $conflicts   = (int)$pdo->query('SELECT COUNT(*) FROM `event_ingest_errors` WHERE `error_code` = "PAYLOAD_CONFLICT"')->fetchColumn();
    $lateErrors  = (int)$pdo->query('SELECT COUNT(*) FROM `event_ingest_errors` WHERE `error_code` = "LATE_EVENT_EXCEEDED"')->fetchColumn();
    $undefErrors = (int)$pdo->query('SELECT COUNT(*) FROM `event_ingest_errors` WHERE `error_code` = "EVENT_NOT_DEFINED"')->fetchColumn();

    // 3. Kiem tra phan bo theo ngay cho su kien qua nua dem
    $yesterday = date('Y-m-d', strtotime('-1 day'));
    $today     = date('Y-m-d');
    $stmtMidY = $pdo->prepare('SELECT COUNT(*) FROM `events` WHERE `website_id` = 1 AND DATE(`occurred_at`) = ?');
    $stmtMidY->execute([$yesterday]);
    $countYesterday = (int)$stmtMidY->fetchColumn();

    $stmtMidT = $pdo->prepare('SELECT COUNT(*) FROM `events` WHERE `website_id` = 1 AND DATE(`occurred_at`) = ?');
    $stmtMidT->execute([$today]);
    $countToday = (int)$stmtMidT->fetchColumn();

    // 4. Kiem tra chuoi funnel Site A
    // Tinh so phien hoan tat tren CSDL
    $stmtF = $pdo->query('
        SELECT `session_id`, `event_name`, `occurred_at`
        FROM `events`
        WHERE `website_id` = 1 AND `event_name` IN ("page_view", "download_start", "download_complete")
        ORDER BY `session_id` ASC, `occurred_at` ASC
    ');
    $fRows = $stmtF->fetchAll();
    $sMap = [];
    foreach ($fRows as $r) {
        $sMap[$r['session_id']][] = $r;
    }
    $funnelCompleteSiteA = 0;
    foreach ($sMap as $sess => $evts) {
        $h1 = false; $h2 = false; $h3 = false;
        $t1 = 0; $t2 = 0;
        foreach ($evts as $e) {
            $t = strtotime($e['occurred_at']);
            if ($e['event_name'] === 'page_view' && !$h1) { $h1 = true; $t1 = $t; continue; }
            if ($e['event_name'] === 'download_start' && $h1 && !$h2 && $t >= $t1) { $h2 = true; $t2 = $t; continue; }
            if ($e['event_name'] === 'download_complete' && $h2 && !$h3 && $t >= $t2) { $h3 = true; break; }
        }
        if ($h3) $funnelCompleteSiteA++;
    }

    $results = [
        'total_events'           => $totalEvents,
        'events_site_a'          => $eventsSiteA,
        'events_site_b'          => $eventsSiteB,
        'total_errors'           => $totalErrors,
        'conflicts'              => $conflicts,
        'late_errors'            => $lateErrors,
        'undef_errors'           => $undefErrors,
        'count_yesterday'        => $countYesterday,
        'count_today'            => $countToday,
        'funnel_completed_a'     => $funnelCompleteSiteA,
        'expected_total_events'  => 195,
        'expected_site_a'        => 135,
        'expected_site_b'        => 60,
        'expected_conflicts'     => 5,
        'expected_late_errors'   => 5,
        'expected_undef_errors'  => 5,
        'expected_funnel_a'      => 15
    ];

    if (!$silent) {
        echo "====================================================\n";
        echo "        KET QUA DOI CHIEU VA XAC THUC BAO CAO\n";
        echo "====================================================\n";
        printf("1. Tong su kien hop le:      %d / %d [%s]\n", $totalEvents, 195, $totalEvents === 195 ? 'KHOP CHUAN' : 'LECH');
        printf("2. Su kien Website A:        %d / %d [%s]\n", $eventsSiteA, 135, $eventsSiteA === 135 ? 'KHOP CHUAN' : 'LECH');
        printf("3. Su kien Website B:        %d / %d [%s]\n", $eventsSiteB, 60,  $eventsSiteB === 60 ? 'KHOP CHUAN' : 'LECH');
        printf("4. Xung dot (Case B):        %d / %d [%s]\n", $conflicts, 5,     $conflicts === 5 ? 'KHOP CHUAN' : 'LECH');
        printf("5. Su kien den muon tu choi: %d / %d [%s]\n", $lateErrors, 5,    $lateErrors === 5 ? 'KHOP CHUAN' : 'LECH');
        printf("6. Su kien chua khai bao:    %d / %d [%s]\n", $undefErrors, 5,   $undefErrors === 5 ? 'KHOP CHUAN' : 'LECH');
        printf("7. Chuoi hoan tat Site A:    %d / %d [%s]\n", $funnelCompleteSiteA, 15, $funnelCompleteSiteA === 15 ? 'KHOP CHUAN' : 'LECH');
        echo "====================================================\n";
    }

    return $results;
}

if (php_sapi_name() === 'cli' && isset($argv[0]) && realpath($argv[0]) === realpath(__FILE__)) {
    run_report_verification();
}
