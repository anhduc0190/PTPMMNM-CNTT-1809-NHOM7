<?php
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';

$pdo = get_db_connection();

echo "=== KIỂM THỬ MÔ PHỎNG LỖI TIẾP NHẬN & PHIÊN HOẠT ĐỘNG ===" . PHP_EOL;

// 1. Số đếm hiện tại
$siteId = 1;
$todayStart = date('Y-m-d 00:00:00');
$todayEnd = date('Y-m-d 23:59:59');

$stmt = $pdo->prepare("SELECT COUNT(DISTINCT session_id) FROM events WHERE website_id = ? AND occurred_at BETWEEN ? AND ?");
$stmt->execute([$siteId, $todayStart, $todayEnd]);
$sessionsBefore = (int)$stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COUNT(*) FROM event_ingest_errors WHERE website_id = ? AND received_at BETWEEN ? AND ?");
$stmt->execute([$siteId, $todayStart, $todayEnd]);
$errorsBefore = (int)$stmt->fetchColumn();

echo "Trước khi test:" . PHP_EOL;
echo "- Phiên hoạt động (Card 3): {$sessionsBefore}" . PHP_EOL;
echo "- Lỗi tiếp nhận (Card 5) : {$errorsBefore}" . PHP_EOL;

function postTrack($payload) {
    $ch = curl_init('http://localhost/website-analytics/api/track.php');
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $res = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return ['code' => $code, 'body' => json_decode($res, true)];
}

// 2. Thử nghiệm tạo 1 Session MỚI
$newSessionId = 'sess_test_new_' . time();
$eventId1 = 'ev_new_' . time();
$resNew = postTrack([
    'tracking_key' => 'trk_site_a_alpha_shop_2026',
    'event_id' => $eventId1,
    'session_id' => $newSessionId,
    'event_name' => 'page_view',
    'page_path' => '/test-new-session',
    'occurred_at' => date('c'),
    'properties' => ['test' => 1]
]);
echo "Test Tạo Phiên Mới: HTTP {$resNew['code']} - " . ($resNew['body']['message'] ?? '') . PHP_EOL;

// 3. Thử nghiệm 409 Conflict: Cùng event_id, khác properties
$conflictEventId = 'conflict_test_' . time();
$resOrig = postTrack([
    'tracking_key' => 'trk_site_a_alpha_shop_2026',
    'event_id' => $conflictEventId,
    'session_id' => $newSessionId,
    'event_name' => 'button_click',
    'page_path' => '/test-conflict',
    'occurred_at' => date('c'),
    'properties' => ['version' => 1]
]);
echo "Gửi gốc #1: HTTP {$resOrig['code']}" . PHP_EOL;

$resConflict = postTrack([
    'tracking_key' => 'trk_site_a_alpha_shop_2026',
    'event_id' => $conflictEventId,
    'session_id' => $newSessionId,
    'event_name' => 'button_click',
    'page_path' => '/test-conflict',
    'occurred_at' => date('c'),
    'properties' => ['version' => 2, 'tampered' => true]
]);
echo "Test Xung đột #2: HTTP {$resConflict['code']} (Mong muốn: 409) - " . ($resConflict['body']['error'] ?? '') . PHP_EOL;

// 4. Thử nghiệm 422 Late Event
$resLate = postTrack([
    'tracking_key' => 'trk_site_a_alpha_shop_2026',
    'event_id' => 'late_test_' . time(),
    'session_id' => $newSessionId,
    'event_name' => 'page_view',
    'page_path' => '/test-late',
    'occurred_at' => date('c', time() - (48 * 3600)),
    'properties' => ['late' => true]
]);
echo "Test Quá hạn 48h: HTTP {$resLate['code']} (Mong muốn: 422) - " . ($resLate['body']['error'] ?? '') . PHP_EOL;

// 5. Thử nghiệm 400 Undefined Event
$resUndef = postTrack([
    'tracking_key' => 'trk_site_a_alpha_shop_2026',
    'event_id' => 'undef_test_' . time(),
    'session_id' => $newSessionId,
    'event_name' => 'invalid_hacked_action',
    'page_path' => '/test-undef',
    'occurred_at' => date('c'),
    'properties' => ['hack' => true]
]);
echo "Test Chưa khai báo: HTTP {$resUndef['code']} (Mong muốn: 400) - " . ($resUndef['body']['error'] ?? '') . PHP_EOL;

// 6. Kiểm tra lại số đếm sau khi test
$stmt = $pdo->prepare("SELECT COUNT(DISTINCT session_id) FROM events WHERE website_id = ? AND occurred_at BETWEEN ? AND ?");
$stmt->execute([$siteId, $todayStart, $todayEnd]);
$sessionsAfter = (int)$stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COUNT(*) FROM event_ingest_errors WHERE website_id = ? AND received_at BETWEEN ? AND ?");
$stmt->execute([$siteId, $todayStart, $todayEnd]);
$errorsAfter = (int)$stmt->fetchColumn();

echo PHP_EOL . "Sau khi test:" . PHP_EOL;
echo "- Phiên hoạt động (Card 3): {$sessionsBefore} -> {$sessionsAfter} (Tăng " . ($sessionsAfter - $sessionsBefore) . ")" . PHP_EOL;
echo "- Lỗi tiếp nhận (Card 5) : {$errorsBefore} -> {$errorsAfter} (Tăng " . ($errorsAfter - $errorsBefore) . ")" . PHP_EOL;

echo "=== HOÀN TẤT KIỂM THỬ XÁC MINH ===" . PHP_EOL;

