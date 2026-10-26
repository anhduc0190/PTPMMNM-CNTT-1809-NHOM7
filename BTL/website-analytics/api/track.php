<?php
// ====================================================================
// REST API: TIEP NHAN VA CHONG DEM TRUNG SU KIEN (INGESTION ENDPOINT)
// Endpoint: POST /api/track.php
// ====================================================================

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/helpers.php';

// 1. Thiet lap Header CORS cho phep JavaScript SDK goi tu cac website thu nghiem
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-Tracking-Key, X-Requested-With');
header('Content-Type: application/json; charset=utf-8');

// Xu ly pre-flight request cua trinh duyet
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// 2. Kiem tra HTTP Method bat buoc phai la POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response([
        'success' => false,
        'error'   => 'METHOD_NOT_ALLOWED',
        'message' => 'Chỉ chấp nhận phương thức POST.'
    ], 405);
}

// 3. Doc va kiem tra kich thuoc Payload JSON
$rawBody = file_get_contents('php://input');
if (strlen($rawBody) > MAX_PAYLOAD_BYTES) {
    json_response([
        'success' => false,
        'error'   => 'PAYLOAD_TOO_LARGE',
        'message' => 'Dung lượng payload vượt quá giới hạn 64KB cho phép.'
    ], 413);
}

$payload = json_decode($rawBody, true);
if (!is_array($payload)) {
    json_response([
        'success' => false,
        'error'   => 'INVALID_JSON',
        'message' => 'Dữ liệu gửi lên không đúng định dạng JSON.'
    ], 400);
}

// 4. Trich xuat cac truong co ban tu payload
$trackingKey = trim($payload['tracking_key'] ?? $_SERVER['HTTP_X_TRACKING_KEY'] ?? '');
$eventId     = trim($payload['event_id'] ?? '');
$sessionId   = trim($payload['session_id'] ?? '');
$eventName   = trim($payload['event_name'] ?? '');
$pagePath    = trim($payload['page_path'] ?? '/');
$occurredAt  = trim($payload['occurred_at'] ?? '');
$rawProps    = isset($payload['properties']) && is_array($payload['properties']) ? $payload['properties'] : [];

if (empty($trackingKey) || empty($eventId) || empty($sessionId) || empty($eventName) || empty($occurredAt)) {
    json_response([
        'success' => false,
        'error'   => 'MISSING_REQUIRED_FIELDS',
        'message' => 'Thiếu các trường bắt buộc: tracking_key, event_id, session_id, event_name, occurred_at.'
    ], 400);
}

// Chuan hoa dinh dang event_id va session_id (do dai toi da 64 ky tu)
if (strlen($eventId) > 64 || strlen($sessionId) > 64 || strlen($eventName) > 64) {
    json_response([
        'success' => false,
        'error'   => 'FIELD_LENGTH_EXCEEDED',
        'message' => 'Độ dài của event_id, session_id hoặc event_name vượt quá giới hạn 64 ký tự.'
    ], 400);
}

$pdo = get_db_connection();

try {
    // 5. Xac dinh website tu tracking_key
    $stmtSite = $pdo->prepare('SELECT `website_id`, `name`, `status`, `late_event_window_hours`, `timezone` FROM `websites` WHERE `tracking_key` = ? LIMIT 1');
    $stmtSite->execute([$trackingKey]);
    $website = $stmtSite->fetch();

    if (!$website) {
        json_response([
            'success' => false,
            'error'   => 'WEBSITE_NOT_FOUND',
            'message' => 'Khoá tracking_key không hợp lệ hoặc website không tồn tại trong hệ thống.'
        ], 404);
    }

    $websiteId = (int)$website['website_id'];

    if ($website['status'] !== 'active') {
        json_response([
            'success' => false,
            'error'   => 'WEBSITE_INACTIVE',
            'message' => 'Website đang ở trạng thái tạm dừng (paused/archived), không tiếp nhận sự kiện mới.'
        ], 403);
    }

    // 6. Kiem tra dinh nghia su kien (event_definitions)
    $stmtDef = $pdo->prepare('SELECT `definition_id`, `enabled` FROM `event_definitions` WHERE `website_id` = ? AND `event_name` = ? LIMIT 1');
    $stmtDef->execute([$websiteId, $eventName]);
    $definition = $stmtDef->fetch();

    if (!$definition || (int)$definition['enabled'] !== 1) {
        // Ghi log loi tiep nhan: Su kien chua duoc khai bao hoac bi tat
        $stmtErr = $pdo->prepare('
            INSERT INTO `event_ingest_errors` (`website_id`, `event_id`, `error_code`, `occurred_at`, `details`)
            VALUES (?, ?, "EVENT_NOT_DEFINED", ?, ?)
        ');
        $stmtErr->execute([
            $websiteId, 
            $eventId, 
            date('Y-m-d H:i:s', strtotime($occurredAt) ?: time()),
            "Sự kiện '{$eventName}' chưa được cấu hình hoặc đã bị vô hiệu hoá trên website."
        ]);

        json_response([
            'success' => false,
            'error'   => 'EVENT_NOT_DEFINED',
            'message' => "Sự kiện '{$eventName}' chưa được cấu hình hoặc đã bị vô hiệu hoá."
        ], 400);
    }

    // 7. Kiem tra thoi diem xay ra su kien (occurred_at) va chinh sach su kien den muon (late window)
    $occurredTimestamp = strtotime($occurredAt);
    if ($occurredTimestamp === false) {
        json_response([
            'success' => false,
            'error'   => 'INVALID_TIMESTAMP',
            'message' => 'Trường occurred_at không đúng định dạng ngày giờ hợp lệ.'
        ], 400);
    }

    $now = time();
    $serverReceivedAt = date('Y-m-d H:i:s', $now);
    $occurredAtFormatted = date('Y-m-d H:i:s', $occurredTimestamp);

    // Chan su kien xay ra trong tuong lai vuot qua do lech cho phep (5 phut)
    if ($occurredTimestamp > ($now + 300)) {
        json_response([
            'success' => false,
            'error'   => 'FUTURE_EVENT_NOT_ALLOWED',
            'message' => 'Thời điểm xảy ra sự kiện ở tương lai vượt quá dung sai đồng hồ cho phép.'
        ], 400);
    }

    // Kiem tra khoang tre duoc phep (late event window)
    $lateWindowHours = (int)$website['late_event_window_hours'] ?: DEFAULT_LATE_WINDOW_HOURS;
    $maxAgeSeconds = $lateWindowHours * 3600;
    if (($now - $occurredTimestamp) > $maxAgeSeconds) {
        // Ghi log loi su kien den muon vuot nguong
        $stmtErr = $pdo->prepare('
            INSERT INTO `event_ingest_errors` (`website_id`, `event_id`, `error_code`, `occurred_at`, `details`)
            VALUES (?, ?, "LATE_EVENT_EXCEEDED", ?, ?)
        ');
        $stmtErr->execute([
            $websiteId, 
            $eventId, 
            $occurredAtFormatted,
            "Sự kiện đến trễ hơn chính sách cho phép ({$lateWindowHours} giờ)."
        ]);

        json_response([
            'success' => false,
            'error'   => 'LATE_EVENT_EXCEEDED',
            'message' => "Sự kiện bị từ chối do đến muộn quá thời hạn cấu hình ({$lateWindowHours} giờ)."
        ], 422);
    }

    // 8. Loc sach properties an toan, khong luu truong nhay cam
    $cleanProperties = sanitize_properties($rawProps);

    // 9. Chuan hoa payload va tinh payload_hash o backend
    $canonicalInfo = canonicalize_payload([
        'event_id'    => $eventId,
        'session_id'  => $sessionId,
        'event_name'  => $eventName,
        'page_path'   => $pagePath,
        'occurred_at' => $occurredAtFormatted,
        'properties'  => $cleanProperties
    ]);
    $payloadHash = $canonicalInfo['hash'];

    // Bat dau Transaction an toan
    $pdo->beginTransaction();

    // 10. Kiem tra su ton tai cua event_id tren cung website (QUY TAC CHONG TRUNG RUBRIC B)
    $stmtCheck = $pdo->prepare('SELECT `event_row_id`, `payload_hash`, `occurred_at`, `received_at` FROM `events` WHERE `website_id` = ? AND `event_id` = ? LIMIT 1');
    $stmtCheck->execute([$websiteId, $eventId]);
    $existing = $stmtCheck->fetch();

    if ($existing) {
        // TRUONG HOP A: Cung website, cung event_id, CUNG payload_hash (Idempotent duplicate)
        if ($existing['payload_hash'] === $payloadHash) {
            $pdo->commit();
            json_response([
                'success'      => true,
                'status'       => 'duplicate_idempotent',
                'is_duplicate' => true,
                'message'      => 'Sự kiện gửi lại hợp lệ (trùng event_id và trùng nội dung). Bỏ qua không tăng số đếm.',
                'event_id'     => $eventId,
                'received_at'  => $existing['received_at']
            ], 200);
        }

        // TRUONG HOP B: Cung website, cung event_id, KHAC payload_hash (Conflict error)
        $stmtConflict = $pdo->prepare('
            INSERT INTO `event_ingest_errors` (`website_id`, `event_id`, `error_code`, `payload_hash`, `occurred_at`, `details`)
            VALUES (?, ?, "PAYLOAD_CONFLICT", ?, ?, ?)
        ');
        $stmtConflict->execute([
            $websiteId,
            $eventId,
            $payloadHash,
            $occurredAtFormatted,
            'Cùng website và event_id nhưng nội dung payload khác với bản ghi gốc đã lưu.'
        ]);
        $pdo->commit();

        json_response([
            'success'  => false,
            'error'    => 'PAYLOAD_CONFLICT',
            'message'  => 'Xung đột dữ liệu: event_id đã tồn tại nhưng có nội dung payload khác với sự kiện ban đầu.',
            'event_id' => $eventId
        ], 409);
    }

    // TRUONG HOP C: event_id moi hoan toan (hop le)
    // 11. Tao hoac cap nhat phien truy cap (sessions)
    $stmtSession = $pdo->prepare('
        INSERT INTO `sessions` (`website_id`, `session_id`, `started_at`, `last_seen_at`)
        VALUES (?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE 
            `last_seen_at` = GREATEST(`last_seen_at`, VALUES(`last_seen_at`))
    ');
    $stmtSession->execute([$websiteId, $sessionId, $occurredAtFormatted, $occurredAtFormatted]);

    // 12. Luu su kien vao bang events
    $stmtInsert = $pdo->prepare('
        INSERT INTO `events` (`website_id`, `event_id`, `session_id`, `event_name`, `page_path`, `occurred_at`, `received_at`, `payload_hash`, `properties`)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
    ');
    $stmtInsert->execute([
        $websiteId,
        $eventId,
        $sessionId,
        $eventName,
        $pagePath,
        $occurredAtFormatted,
        $serverReceivedAt,
        $payloadHash,
        $cleanProperties ? json_encode($cleanProperties, JSON_UNESCAPED_UNICODE) : null
    ]);

    $eventRowId = $pdo->lastInsertId();
    $pdo->commit();

    json_response([
        'success'      => true,
        'status'       => 'created',
        'is_duplicate' => false,
        'message'      => 'Sự kiện mới được tiếp nhận và lưu trữ thành công.',
        'event_id'     => $eventId,
        'event_row_id' => (int)$eventRowId,
        'received_at'  => $serverReceivedAt
    ], 201);

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log("[TRACK API ERROR] " . $e->getMessage());

    json_response([
        'success' => false,
        'error'   => 'INTERNAL_SERVER_ERROR',
        'message' => 'Lỗi máy chủ nội bộ trong quá trình tiếp nhận sự kiện.'
    ], 500);
}
