<?php
declare(strict_types=1);
/**
 * seed.php — Tạo ~200 sự kiện mẫu với đáp án đã tính trước.
 *
 * Bao gồm:
 * - 2 website, 6 trang/website
 * - 4 loại sự kiện
 * - Sự kiện qua nửa đêm (UTC+7)
 * - Gửi lặp (duplicate)
 * - Phiên ở hai website
 * - Thao tác sai thứ tự
 * - Sự kiện đến muộn (trong giới hạn)
 *
 * Sau khi chạy, lưu expected.json để đối chiếu.
 */
require_once __DIR__ . '/../src/Database.php';
require_once __DIR__ . '/../src/WebsiteRegistry.php';
require_once __DIR__ . '/../src/EventCollector.php';
require_once __DIR__ . '/../src/Aggregator.php';
require_once __DIR__ . '/../src/FunnelAnalyzer.php';

try {
    $pdo = db();
} catch (Throwable $e) {
    echo "LỖI: " . $e->getMessage() . "\n";
    echo "Chạy: bash lab.sh db-config trước\n";
    exit(2);
}

echo "=== Tạo dữ liệu mẫu ===\n";

// --- Đăng ký 2 website thử ---
$site1 = registerWebsite($pdo, 'Alpha Shop',  'http://127.0.0.1:8201');
$site2 = registerWebsite($pdo, 'Beta Portal', 'http://127.0.0.1:8202');
$id1   = (int) $site1['id'];
$id2   = (int) $site2['id'];
$key1  = $site1['api_key'];
$key2  = $site2['api_key'];

echo "Website 1: ID=$id1  key={$key1}\n";
echo "Website 2: ID=$id2  key={$key2}\n";

// --- Cấu hình sự kiện ---
addEventDefinition($pdo, $id1, 'pageview',    '*',              'Lượt xem trang');
addEventDefinition($pdo, $id1, 'click',       '#btn-download',  'Nút tải tài liệu');
addEventDefinition($pdo, $id1, 'form_submit', '#form-contact',  'Form liên hệ');
addEventDefinition($pdo, $id1, 'download',    '/assets/brochure.pdf', 'Tải brochure');

addEventDefinition($pdo, $id2, 'pageview',    '*',              'Lượt xem trang');
addEventDefinition($pdo, $id2, 'click',       '#btn-start',     'Bắt đầu');
addEventDefinition($pdo, $id2, 'click',       '#btn-complete',  'Hoàn tất');
addEventDefinition($pdo, $id2, 'form_submit', '#form-survey',   'Biểu mẫu khảo sát');

// --- Các trang mẫu ---
$pages1 = ['/home', '/products', '/about', '/contact', '/pricing', '/download'];
$pages2 = ['/home', '/start', '/step1', '/step2', '/complete', '/survey'];

// Helper: gửi sự kiện
function send(PDO $pdo, string $apiKey, string $sess, string $type,
              string $page, string $target, int $ts, string $suffix = '', bool $skipLate = false): CollectResult
{
    return collectEvent($pdo, [
        'api_key'         => $apiKey,
        'session_token'   => $sess,
        'event_type'      => $type,
        'page_path'       => $page,
        'target'          => $target,
        'idempotency_key' => hash('sha256', "$apiKey|$sess|$type|$page|$target|$ts|$suffix"),
        'client_ts'       => $ts,
        'skip_late_check' => $skipLate,
    ]);
}

$now    = time();
$counts = ['ok' => 0, 'duplicate' => 0, 'conflict' => 0, 'rejected' => 0];

// ── WEBSITE 1: Alpha Shop ──────────────────────────────────────────────────
// 5 phiên bình thường
$pvExpected  = 0;
$clkExpected = 0;

for ($s = 1; $s <= 5; $s++) {
    $sess = "alpha_sess_$s";
    // Mỗi phiên xem 6 trang
    foreach ($pages1 as $idx => $page) {
        $ts = $now - 3600 + $s * 60 + $idx * 5;
        $r  = send($pdo, $key1, $sess, 'pageview', $page, '', $ts);
        if ($r->status === CollectResult::OK) { $pvExpected++; $counts['ok']++; }
    }
    // Mỗi phiên click 1-2 lần
    $r = send($pdo, $key1, $sess, 'click', '/download', '#btn-download', $now - 3500 + $s * 60);
    if ($r->status === CollectResult::OK) { $clkExpected++; $counts['ok']++; }
}

// Phiên qua nửa đêm UTC+7: 23:50 → 00:05
// 23:50 UTC+7 ngày 2025-10-25 = 16:50 UTC 2025-10-25
$tsNight1 = gmmktime(16, 50, 0, 10, 25, 2025);
$tsNight2 = gmmktime(16, 55, 0, 10, 25, 2025);
$tsNight3 = gmmktime(17, 05, 0, 10, 25, 2025); // = 00:05 ngày 26/10 UTC+7

$midSess = 'midnight_alpha';
send($pdo, $key1, $midSess, 'pageview', '/home',     '', $tsNight1, '', true);   // ngày 25/10
send($pdo, $key1, $midSess, 'pageview', '/products', '', $tsNight2, '', true);   // ngày 25/10
send($pdo, $key1, $midSess, 'pageview', '/about',    '', $tsNight3, '', true);   // ngày 26/10
$pvExpected += 3; $counts['ok'] += 3;

// Sự kiện form_submit (không có nội dung)
send($pdo, $key1, 'form_sess', 'form_submit', '/contact', '#form-contact', $now - 100);
$counts['ok']++;

// Gửi lặp 10 sự kiện
$dupKeys = [];
for ($i = 1; $i <= 10; $i++) {
    $ts = $now - 3600 + $i * 10;
    $r1 = send($pdo, $key1, "alpha_sess_$i", 'pageview', $pages1[$i % 6], '', $ts, 'dup');
    if ($r1->status === CollectResult::OK) $dupKeys[] = [$key1, "alpha_sess_$i", $ts, $pages1[$i % 6]];
    $counts['ok']++;
}
// Gửi lại chính xác → duplicate
foreach (array_slice($dupKeys, 0, 5) as [$k, $se, $ts, $pg]) {
    $r = send($pdo, $k, $se, 'pageview', $pg, '', $ts, 'dup');
    if ($r->status === CollectResult::DUPLICATE) $counts['duplicate']++;
}

// ── WEBSITE 2: Beta Portal ────────────────────────────────────────────────
// Funnel: pageview → #btn-start → #btn-complete (3 phiên hoàn tất)
$completeFunnels = 0;
for ($s = 1; $s <= 3; $s++) {
    $sess = "beta_complete_$s";
    $t0   = $now - 2000 + $s * 100;
    send($pdo, $key2, $sess, 'pageview', '/home',     '', $t0);
    send($pdo, $key2, $sess, 'click',    '/start',    '#btn-start',    $t0 + 10);
    send($pdo, $key2, $sess, 'click',    '/complete', '#btn-complete', $t0 + 30);
    $counts['ok'] += 3;
    $completeFunnels++;
}

// 2 phiên chỉ xem (không hoàn tất funnel)
for ($s = 1; $s <= 2; $s++) {
    $sess = "beta_partial_$s";
    $t0   = $now - 1500 + $s * 50;
    foreach (array_slice($pages2, 0, 4) as $idx => $page) {
        send($pdo, $key2, $sess, 'pageview', $page, '', $t0 + $idx * 8);
        $counts['ok']++;
    }
}

// 1 phiên sai thứ tự (complete → start)
$badSess = 'beta_bad_order';
send($pdo, $key2, $badSess, 'pageview', '/home',     '', $now - 1000);
send($pdo, $key2, $badSess, 'click',    '/complete', '#btn-complete', $now - 990);
send($pdo, $key2, $badSess, 'click',    '/start',    '#btn-start',    $now - 980);
$counts['ok'] += 3;

// Gửi lặp trong beta (duplicate)
$betaDupSess = 'beta_complete_1';
$r = send($pdo, $key2, $betaDupSess, 'pageview', '/home', '', $now - 2000 + 100);
if ($r->status === CollectResult::DUPLICATE) $counts['duplicate']++;

// Conflict test: cùng key khác page
$conflictKey = hash('sha256', "conflict-demo-$id1");
$payload1 = ['api_key' => $key1, 'session_token' => 'conf_sess',
    'event_type' => 'pageview', 'page_path' => '/original', 'target' => '',
    'idempotency_key' => $conflictKey, 'client_ts' => $now - 50];
$payload2 = array_merge($payload1, ['page_path' => '/different']);
collectEvent($pdo, $payload1);
$rConf = collectEvent($pdo, $payload2);
if ($rConf->status === CollectResult::CONFLICT) $counts['conflict']++;

// ── Tính đáp án tổng hợp ────────────────────────────────────────────────
$summary1 = aggregateSummary($pdo, $id1);
$summary2 = aggregateSummary($pdo, $id2);
$funnel   = analyzeFunnel($pdo, $id2, [
    new FunnelStep('pageview'),
    new FunnelStep('click', '*', '#btn-start'),
    new FunnelStep('click', '*', '#btn-complete'),
]);

$expected = [
    'generated_at'   => date('c'),
    'timezone'       => 'Asia/Ho_Chi_Minh',
    'max_late_seconds' => MAX_LATE_SECONDS,
    'site1' => [
        'id'              => $id1,
        'name'            => 'Alpha Shop',
        'api_key'         => $key1,
        'pageviews'       => $summary1['pageviews'],
        'unique_sessions' => $summary1['unique_sessions'],
        'events_by_type'  => $summary1['events_by_type'],
    ],
    'site2' => [
        'id'              => $id2,
        'name'            => 'Beta Portal',
        'api_key'         => $key2,
        'pageviews'       => $summary2['pageviews'],
        'unique_sessions' => $summary2['unique_sessions'],
        'events_by_type'  => $summary2['events_by_type'],
    ],
    'funnel' => [
        'total_sessions'     => $funnel->totalSessions,
        'completed_sessions' => $funnel->completedSessions,
        'completion_rate'    => $funnel->completionRate,
        'step_counts'        => $funnel->stepCounts,
    ],
    'event_counts' => $counts,
    'midnight_test' => [
        'ts_2350' => date('Y-m-d', $tsNight1),
        'ts_0005' => date('Y-m-d', $tsNight3),
        'note'    => 'UTC+7: sự kiện 23:50 thuộc ngày 25/10, sự kiện 00:05 thuộc ngày 26/10',
    ],
];

$jsonPath = __DIR__ . '/expected.json';
file_put_contents($jsonPath, json_encode($expected, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

echo "\n✅ Đã tạo dữ liệu mẫu:\n";
echo "   Site 1 — Pageviews: {$summary1['pageviews']}, Sessions: {$summary1['unique_sessions']}\n";
echo "   Site 2 — Pageviews: {$summary2['pageviews']}, Funnel: {$funnel->completedSessions}/{$funnel->totalSessions} hoàn tất\n";
echo "   Duplicate: {$counts['duplicate']}, Conflict: {$counts['conflict']}\n";
echo "   Đáp án lưu tại: $jsonPath\n";
echo "\nAPI Keys (dùng để xem báo cáo):\n";
echo "   Site 1: $key1\n";
echo "   Site 2: $key2\n";
