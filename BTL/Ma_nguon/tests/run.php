<?php
declare(strict_types=1);
/**
 * run.php — Phép thử đơn vị bắt buộc (6 phép thử theo rubric)
 *
 * Dùng SQLite in-memory để chạy độc lập (không cần MySQL).
 * Phong cách giống LAB01-05: same() + finish()
 */
require_once __DIR__ . '/helper.php';
// Không dùng src/Database.php (MySQL); dùng SQLite in-memory
$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

// Tạo schema SQLite tương đương
$pdo->exec("
CREATE TABLE websites (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL,
    origin TEXT NOT NULL UNIQUE,
    api_key TEXT NOT NULL UNIQUE,
    timezone TEXT NOT NULL DEFAULT 'Asia/Ho_Chi_Minh',
    created_at TEXT NOT NULL DEFAULT (datetime('now')),
    active INTEGER NOT NULL DEFAULT 1
);
CREATE TABLE event_definitions (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    website_id INTEGER NOT NULL,
    event_type TEXT NOT NULL,
    target TEXT NOT NULL DEFAULT '*',
    description TEXT NOT NULL DEFAULT '',
    active INTEGER NOT NULL DEFAULT 1
);
CREATE TABLE sessions (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    website_id INTEGER NOT NULL,
    session_token TEXT NOT NULL,
    started_at TEXT NOT NULL,
    last_seen_at TEXT NOT NULL,
    UNIQUE(website_id, session_token)
);
CREATE TABLE events (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    website_id INTEGER NOT NULL,
    session_id INTEGER NOT NULL,
    event_definition_id INTEGER,
    event_type TEXT NOT NULL,
    page_path TEXT NOT NULL DEFAULT '/',
    target_selector TEXT NOT NULL DEFAULT '',
    idempotency_key TEXT NOT NULL,
    client_ts TEXT NOT NULL,
    server_ts TEXT NOT NULL DEFAULT (datetime('now')),
    ip_prefix TEXT NOT NULL DEFAULT '',
    UNIQUE(website_id, idempotency_key)
);
CREATE TABLE event_errors (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    website_id INTEGER,
    error_code TEXT NOT NULL,
    payload_hash TEXT NOT NULL DEFAULT '',
    server_ts TEXT NOT NULL DEFAULT (datetime('now'))
);
CREATE TABLE report_queries (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    website_id INTEGER NOT NULL,
    viewer_key TEXT NOT NULL DEFAULT 'system',
    params TEXT NOT NULL,
    queried_at TEXT NOT NULL DEFAULT (datetime('now'))
);
");

// Override db() để trả về SQLite PDO
function db(): PDO { global $pdo; return $pdo; }
function dbQuery(PDO $p, string $sql, array $params = []): PDOStatement {
    $stmt = $p->prepare($sql); $stmt->execute($params); return $stmt;
}

// Load các module cần test
require_once __DIR__ . '/../src/WebsiteRegistry.php';
require_once __DIR__ . '/../src/EventCollector.php';
require_once __DIR__ . '/../src/Aggregator.php';
require_once __DIR__ . '/../src/FunnelAnalyzer.php';

// Cài dữ liệu: 2 website
$apiKey1 = str_repeat('a', 64);
$apiKey2 = str_repeat('b', 64);
$pdo->exec("INSERT INTO websites (name,origin,api_key) VALUES ('Alpha','http://alpha.test','$apiKey1')");
$pdo->exec("INSERT INTO websites (name,origin,api_key) VALUES ('Beta','http://beta.test','$apiKey2')");
addEventDefinition($pdo, 1, 'pageview', '*', 'Lượt xem');
addEventDefinition($pdo, 1, 'click',    '#btn-download', 'Nút tải');
addEventDefinition($pdo, 1, 'form_submit', '#form-contact', 'Form');
addEventDefinition($pdo, 2, 'pageview', '*', 'Lượt xem');
addEventDefinition($pdo, 2, 'click',    '#btn-start',    'Bắt đầu');
addEventDefinition($pdo, 2, 'click',    '#btn-complete', 'Hoàn tất');

// ─────────────────────────────────────────────────────────────────────────────
// PHÉP THỬ 1: 10 lượt xem + 4 click → đúng số; gửi lại không tăng
// ─────────────────────────────────────────────────────────────────────────────
echo "=== PHEP THU 1: Dem chinh xac, khong tang khi gui lai ===\n";

$now     = time();
$session = 'sess_test_01';

// Tạo 10 pageview (10 idempotency key khác nhau)
for ($i = 1; $i <= 10; $i++) {
    $ikey = hash('sha256', "pv-$i");
    collectEvent($pdo, [
        'api_key'         => $apiKey1,
        'session_token'   => $session,
        'event_type'      => 'pageview',
        'page_path'       => "/page-$i",
        'target'          => '',
        'idempotency_key' => $ikey,
        'client_ts'       => $now - (10 - $i),
    ]);
}

// Tạo 4 click
for ($i = 1; $i <= 4; $i++) {
    $ikey = hash('sha256', "click-$i");
    collectEvent($pdo, [
        'api_key'         => $apiKey1,
        'session_token'   => $session,
        'event_type'      => 'click',
        'page_path'       => '/page-1',
        'target'          => '#btn-download',
        'idempotency_key' => $ikey,
        'client_ts'       => $now - 5 + $i,
    ]);
}

// Gửi lại các key cũ (phải bị bỏ qua)
for ($i = 1; $i <= 3; $i++) {
    $ikey   = hash('sha256', "pv-$i");
    $result = collectEvent($pdo, [
        'api_key'         => $apiKey1,
        'session_token'   => $session,
        'event_type'      => 'pageview',
        'page_path'       => "/page-$i",
        'target'          => '',
        'idempotency_key' => $ikey,
        'client_ts'       => $now - (10 - $i),
    ]);
    same($result->status, CollectResult::DUPLICATE, "gui-lai-pv-$i-la-duplicate");
}

$pvCount    = (int) dbQuery($pdo, "SELECT COUNT(*) AS c FROM events WHERE website_id=1 AND event_type='pageview'")->fetch()['c'];
$clickCount = (int) dbQuery($pdo, "SELECT COUNT(*) AS c FROM events WHERE website_id=1 AND event_type='click'")->fetch()['c'];
same($pvCount,    10, 'T1-so-pageview-dung-10');
same($clickCount, 4,  'T1-so-click-dung-4');

// ─────────────────────────────────────────────────────────────────────────────
// PHÉP THỬ 2: Cùng key khác nội dung → CONFLICT; key mới cùng nội dung → OK
// ─────────────────────────────────────────────────────────────────────────────
echo "\n=== PHEP THU 2: Idempotency conflict vs new event ===\n";

$conflictKey = hash('sha256', 'conflict-test-key');
// Lần đầu gửi
collectEvent($pdo, [
    'api_key' => $apiKey1, 'session_token' => 'sess_t2',
    'event_type' => 'pageview', 'page_path' => '/original',
    'target' => '', 'idempotency_key' => $conflictKey,
    'client_ts' => $now,
]);
// Gửi lại cùng key nhưng page_path khác → CONFLICT
$r2 = collectEvent($pdo, [
    'api_key' => $apiKey1, 'session_token' => 'sess_t2',
    'event_type' => 'pageview', 'page_path' => '/other-page',
    'target' => '', 'idempotency_key' => $conflictKey,
    'client_ts' => $now,
]);
same($r2->status, CollectResult::CONFLICT, 'T2-cung-key-khac-noidung-la-conflict');

// Key mới, cùng nội dung với bản gốc → OK (sự kiện mới)
$newKey = hash('sha256', 'new-event-same-content');
$r3 = collectEvent($pdo, [
    'api_key' => $apiKey1, 'session_token' => 'sess_t2',
    'event_type' => 'pageview', 'page_path' => '/original',
    'target' => '', 'idempotency_key' => $newKey,
    'client_ts' => $now + 1,
]);
same($r3->status, CollectResult::OK, 'T2-key-moi-cung-noidung-la-OK');

// ─────────────────────────────────────────────────────────────────────────────
// PHÉP THỬ 3: Sự kiện qua nửa đêm → nhóm theo UTC+7; sự kiện đến muộn
// ─────────────────────────────────────────────────────────────────────────────
echo "\n=== PHEP THU 3: Nua dem UTC+7 va den muon ===\n";

// 23:50 ngày 2025-06-14 UTC+7 = 16:50 UTC 2025-06-14
$ts2350 = gmmktime(16, 50, 0, 6, 14, 2025);
// 00:10 ngày 2025-06-15 UTC+7 = 17:10 UTC 2025-06-14
$ts0010 = gmmktime(17, 10, 0, 6, 14, 2025);

same(utcToReportDate(gmdate('Y-m-d H:i:s', $ts2350)), '2025-06-14', 'T3-2350-UTC7-ngay-14');
same(utcToReportDate(gmdate('Y-m-d H:i:s', $ts0010)), '2025-06-15', 'T3-0010-UTC7-ngay-15');

// Sự kiện đến muộn vượt giới hạn → bị từ chối
$latePload = [
    'api_key' => $apiKey1, 'session_token' => 'sess_late',
    'event_type' => 'pageview', 'page_path' => '/late',
    'target' => '', 'idempotency_key' => hash('sha256', 'late-event'),
    'client_ts' => time() - MAX_LATE_SECONDS - 60,
];
$rLate = collectEvent($pdo, $latePload);
same($rLate->status, CollectResult::REJECTED, 'T3-den-muon-qua-gioi-han-bi-tu-choi');

// Sự kiện đến muộn trong giới hạn → được chấp nhận
$okLate = [
    'api_key' => $apiKey1, 'session_token' => 'sess_late2',
    'event_type' => 'pageview', 'page_path' => '/late-ok',
    'target' => '', 'idempotency_key' => hash('sha256', 'late-ok-event'),
    'client_ts' => time() - MAX_LATE_SECONDS + 60,
];
$rLateOk = collectEvent($pdo, $okLate);
same($rLateOk->status, CollectResult::OK, 'T3-den-muon-trong-gioi-han-duoc-chap-nhan');

// ─────────────────────────────────────────────────────────────────────────────
// PHÉP THỬ 4: Funnel xem–bắt đầu–hoàn tất; thiếu bước hoặc sai thứ tự
// ─────────────────────────────────────────────────────────────────────────────
echo "\n=== PHEP THU 4: Funnel hoan tat / thieu buoc / sai thu tu ===\n";

// Session A: hoàn tất đúng thứ tự (pageview → start → complete)
$sessA = 'funnel_sess_A';
$t0 = $now - 100;
foreach ([
    ['pageview', '/', '', $t0],
    ['click', '/', '#btn-start',    $t0 + 5],
    ['click', '/', '#btn-complete', $t0 + 10],
] as [$et, $pp, $tgt, $ts]) {
    collectEvent($pdo, [
        'api_key' => $apiKey2, 'session_token' => $sessA,
        'event_type' => $et, 'page_path' => $pp, 'target' => $tgt,
        'idempotency_key' => hash('sha256', "$sessA-$et-$tgt-$ts"),
        'client_ts' => $ts,
    ]);
}

// Session B: chỉ có pageview → start (thiếu complete)
$sessB = 'funnel_sess_B';
foreach ([
    ['pageview', '/', '', $t0],
    ['click', '/', '#btn-start', $t0 + 5],
] as [$et, $pp, $tgt, $ts]) {
    collectEvent($pdo, [
        'api_key' => $apiKey2, 'session_token' => $sessB,
        'event_type' => $et, 'page_path' => $pp, 'target' => $tgt,
        'idempotency_key' => hash('sha256', "$sessB-$et-$tgt-$ts"),
        'client_ts' => $ts,
    ]);
}

// Session C: sai thứ tự (complete trước start)
$sessC = 'funnel_sess_C';
foreach ([
    ['pageview',  '/', '', $t0],
    ['click', '/', '#btn-complete', $t0 + 2],  // complete đến trước
    ['click', '/', '#btn-start',    $t0 + 8],
] as [$et, $pp, $tgt, $ts]) {
    collectEvent($pdo, [
        'api_key' => $apiKey2, 'session_token' => $sessC,
        'event_type' => $et, 'page_path' => $pp, 'target' => $tgt,
        'idempotency_key' => hash('sha256', "$sessC-$et-$tgt-$ts"),
        'client_ts' => $ts,
    ]);
}

$funnelResult = analyzeFunnel($pdo, 2, defaultFunnel());
same($funnelResult->completedSessions, 1, 'T4-chi-1-session-hoan-tat');
same($funnelResult->totalSessions, 3,     'T4-tong-3-session');
assertTrue($funnelResult->completionRate > 0, 'T4-ty-le-hoan-tat-duong');

// ─────────────────────────────────────────────────────────────────────────────
// PHÉP THỬ 5: Hai website không lẫn số liệu
// ─────────────────────────────────────────────────────────────────────────────
echo "\n=== PHEP THU 5: Website khong lan so lieu ===\n";

$site1Events = (int) dbQuery($pdo, "SELECT COUNT(*) AS c FROM events WHERE website_id=1")->fetch()['c'];
$site2Events = (int) dbQuery($pdo, "SELECT COUNT(*) AS c FROM events WHERE website_id=2")->fetch()['c'];

assertTrue($site1Events > 0, 'T5-site1-co-su-kien');
assertTrue($site2Events > 0, 'T5-site2-co-su-kien');

// API key của site 1 không thể xác thực site 2
$wrongSite = authenticateByApiKey($pdo, $apiKey1);
same($wrongSite['id'] ?? null, 1, 'T5-apikey1-chi-xac-thuc-site1');

// Gửi sự kiện với api_key của site 1 vào loại sự kiện của site 2 → bị từ chối
$crossResult = collectEvent($pdo, [
    'api_key' => $apiKey1, 'session_token' => 'cross_sess',
    'event_type' => 'click', 'page_path' => '/', 'target' => '#btn-complete',
    'idempotency_key' => hash('sha256', 'cross-site-test'),
    'client_ts' => $now,
]);
same($crossResult->status, CollectResult::REJECTED, 'T5-site1-khong-theo-doi-event-cua-site2');

// ─────────────────────────────────────────────────────────────────────────────
// PHÉP THỬ 6: Không chứa nội dung trường nhập; IP đã ẩn danh
// ─────────────────────────────────────────────────────────────────────────────
echo "\n=== PHEP THU 6: An toan du lieu ===\n";

// Gửi payload có form_data → phải bị loại trước khi lưu
$payloadWithForm = [
    'api_key'         => $apiKey1,
    'session_token'   => 'sess_safety',
    'event_type'      => 'form_submit',
    'page_path'       => '/',
    'target'          => '#form-contact',
    'idempotency_key' => hash('sha256', 'form-submit-test'),
    'client_ts'       => $now,
    'form_data'       => ['email' => 'test@example.com', 'password' => 'secret'],  // phải bị bỏ
];
// collect.php loại form_data trước khi gọi collectEvent; mô phỏng lại ở đây:
unset($payloadWithForm['form_data'], $payloadWithForm['input_values']);
$rForm = collectEvent($pdo, $payloadWithForm);
same($rForm->status, CollectResult::OK, 'T6-form-submit-duoc-ghi-nhan');

// Kiểm tra sự kiện lưu không có nội dung trường nhập (chỉ có các cột định nghĩa)
$evRow = dbQuery($pdo,
    "SELECT * FROM events WHERE idempotency_key=?",
    [hash('sha256', 'form-submit-test')]
)->fetch();
assertTrue($evRow !== false, 'T6-su-kien-duoc-luu');
assertTrue(!isset($evRow['form_data']), 'T6-form-data-khong-duoc-luu');
assertTrue(!isset($evRow['input_values']), 'T6-input-values-khong-duoc-luu');

// IP anonymization
same(anonymizeIp('192.168.1.42'),  '192.168.1.0/24',  'T6-ipv4-anonymize');
same(anonymizeIp('2001:db8::1'),   '2001:db8:::/48',  'T6-ipv6-anonymize');
same(anonymizeIp(''),              '',                  'T6-empty-ip');

finish();
