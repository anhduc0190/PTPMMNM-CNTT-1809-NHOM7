<?php
declare(strict_types=1);
/**
 * integration.php — Test tích hợp với MySQL thật
 * Chạy sau khi đã cấu hình db-config và seed dữ liệu.
 *
 * Tình huống 1: Đăng ký → gắn tracker → ghi sự kiện → xem báo cáo đúng số
 * Tình huống 2: Chuỗi xem–bắt đầu–hoàn tất có sự kiện lặp và đến muộn
 */
require_once __DIR__ . '/helper.php';
require_once __DIR__ . '/../src/Database.php';
require_once __DIR__ . '/../src/WebsiteRegistry.php';
require_once __DIR__ . '/../src/EventCollector.php';
require_once __DIR__ . '/../src/Aggregator.php';
require_once __DIR__ . '/../src/FunnelAnalyzer.php';
require_once __DIR__ . '/../src/ReportExporter.php';

try {
    $pdo = db();
} catch (Throwable $e) {
    echo "SKIP integration.php: " . $e->getMessage() . "\n";
    exit(0);
}

echo "=== TINH HUONG 1: Dang ky → ghi su kien → bao cao dung so ===\n";

// Đăng ký website tích hợp
$siteName = 'Integration Test Site ' . time();
$siteOrigin = 'http://integration-' . time() . '.test';
$site = registerWebsite($pdo, $siteName, $siteOrigin);
$siteId = (int) $site['id'];
$apiKey = $site['api_key'];

addEventDefinition($pdo, $siteId, 'pageview',    '*',              'Lượt xem tích hợp');
addEventDefinition($pdo, $siteId, 'click',       '#btn-download',  'Tải tài liệu');
addEventDefinition($pdo, $siteId, 'click',       '#btn-start',     'Bắt đầu');
addEventDefinition($pdo, $siteId, 'click',       '#btn-complete',  'Hoàn tất');

$now  = time();
$sess = 'integ_sess_' . time();

// Gửi 10 pageview
for ($i = 1; $i <= 10; $i++) {
    collectEvent($pdo, [
        'api_key'         => $apiKey,
        'session_token'   => $sess,
        'event_type'      => 'pageview',
        'page_path'       => "/page-$i",
        'target'          => '',
        'idempotency_key' => hash('sha256', "integ-pv-$i-$siteId"),
        'client_ts'       => $now - 200 + $i,
    ]);
}

// Gửi 4 click
for ($i = 1; $i <= 4; $i++) {
    collectEvent($pdo, [
        'api_key'         => $apiKey,
        'session_token'   => $sess,
        'event_type'      => 'click',
        'page_path'       => '/page-1',
        'target'          => '#btn-download',
        'idempotency_key' => hash('sha256', "integ-click-$i-$siteId"),
        'client_ts'       => $now - 50 + $i,
    ]);
}

// Gửi lại 5 key cũ → tất cả phải là DUPLICATE
$dupCount = 0;
for ($i = 1; $i <= 5; $i++) {
    $r = collectEvent($pdo, [
        'api_key' => $apiKey, 'session_token' => $sess,
        'event_type' => 'pageview', 'page_path' => "/page-$i",
        'target' => '', 'idempotency_key' => hash('sha256', "integ-pv-$i-$siteId"),
        'client_ts' => $now - 200 + $i,
    ]);
    if ($r->status === CollectResult::DUPLICATE) $dupCount++;
}
same($dupCount, 5, 'TH1-5-gui-lai-deu-la-duplicate');

$summary = aggregateSummary($pdo, $siteId);
same($summary['pageviews'],       10, 'TH1-tong-10-pageview');
same($summary['events_by_type']['click'] ?? 0, 4, 'TH1-tong-4-click');
same($summary['unique_sessions'], 1,  'TH1-1-session-duy-nhat');

// Kiểm tra xuất báo cáo JSON
$json = exportReport($pdo, $siteId, 'json', [], $apiKey);
$decoded = json_decode($json, true);
assertTrue(is_array($decoded), 'TH1-xuat-json-hop-le');
assertTrue(isset($decoded['days']), 'TH1-json-co-truong-days');

echo "\n=== TINH HUONG 2: Chuoi su kien co lap va den muon ===\n";

// Session hoàn tất chuỗi đúng
$sessOK = 'th2_ok_' . time();
$t0 = $now - 500;
foreach ([
    ['pageview', '/',  '',             $t0],
    ['click',    '/',  '#btn-start',   $t0 + 10],
    ['click',    '/',  '#btn-complete',$t0 + 30],
] as [$et, $pp, $tgt, $ts]) {
    collectEvent($pdo, [
        'api_key' => $apiKey, 'session_token' => $sessOK,
        'event_type' => $et, 'page_path' => $pp, 'target' => $tgt,
        'idempotency_key' => hash('sha256', "$sessOK-$et-$tgt-$ts"),
        'client_ts' => $ts,
    ]);
}

// Gửi lặp 2 bước đầu → không tạo thêm session hoàn tất
for ($i = 0; $i < 2; $i++) {
    collectEvent($pdo, [
        'api_key' => $apiKey, 'session_token' => $sessOK,
        'event_type' => 'pageview', 'page_path' => '/', 'target' => '',
        'idempotency_key' => hash('sha256', "$sessOK-pageview--$t0"),
        'client_ts' => $t0,
    ]);
}

// Session sai thứ tự
$sessBad = 'th2_bad_' . time();
foreach ([
    ['pageview', '/', '',             $t0],
    ['click',    '/', '#btn-complete',$t0 + 5],  // sai thứ tự
    ['click',    '/', '#btn-start',   $t0 + 10],
] as [$et, $pp, $tgt, $ts]) {
    collectEvent($pdo, [
        'api_key' => $apiKey, 'session_token' => $sessBad,
        'event_type' => $et, 'page_path' => $pp, 'target' => $tgt,
        'idempotency_key' => hash('sha256', "$sessBad-$et-$tgt-$ts"),
        'client_ts' => $ts,
    ]);
}

$fSteps = [
    new FunnelStep('pageview'),
    new FunnelStep('click', '*', '#btn-start'),
    new FunnelStep('click', '*', '#btn-complete'),
];
$fr = analyzeFunnel($pdo, $siteId, $fSteps);
same($fr->completedSessions, 1, 'TH2-chi-1-session-hoan-tat-dung');

// Kiểm tra sự kiện đến muộn bị từ chối
$rTooLate = collectEvent($pdo, [
    'api_key' => $apiKey, 'session_token' => 'late_sess',
    'event_type' => 'pageview', 'page_path' => '/old',
    'target' => '', 'idempotency_key' => hash('sha256', 'too-late-integ'),
    'client_ts' => time() - MAX_LATE_SECONDS - 120,
]);
same($rTooLate->status, CollectResult::REJECTED, 'TH2-den-muon-bi-tu-choi');

// Cleanup: xóa website thử
dbQuery($pdo, "DELETE FROM events WHERE website_id=?", [$siteId]);
dbQuery($pdo, "DELETE FROM sessions WHERE website_id=?", [$siteId]);
dbQuery($pdo, "DELETE FROM event_definitions WHERE website_id=?", [$siteId]);
dbQuery($pdo, "DELETE FROM report_queries WHERE website_id=?", [$siteId]);
dbQuery($pdo, "DELETE FROM websites WHERE id=?", [$siteId]);

finish();
