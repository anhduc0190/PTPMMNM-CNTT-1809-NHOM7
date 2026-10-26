<?php
declare(strict_types=1);
/**
 * collect.php — API tiếp nhận sự kiện từ tracker.js
 * POST /collect.php  (Content-Type: application/json)
 *
 * Phản hồi JSON:
 *   {"status":"ok|duplicate|conflict|rejected|error", "message":"...", "event_id":null|int}
 *
 * Bảo mật:
 * - CORS chỉ cho phép origin đã đăng ký.
 * - Không ghi nội dung trường nhập (form fields).
 * - Giới hạn kích thước payload 8 KB.
 */
require_once __DIR__ . '/../src/EventCollector.php';

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');

// Chỉ chấp nhận POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Method not allowed']);
    exit;
}

// Giới hạn kích thước payload
$rawBody = file_get_contents('php://input', false, null, 0, 8192);
if ($rawBody === false || strlen($rawBody) === 0) {
    http_response_code(400);
    echo json_encode(['status' => 'rejected', 'message' => 'Empty body']);
    exit;
}

$payload = json_decode($rawBody, true);
if (!is_array($payload)) {
    http_response_code(400);
    echo json_encode(['status' => 'rejected', 'message' => 'Invalid JSON']);
    exit;
}

// Loại bỏ mọi trường có thể chứa nội dung nhập liệu của người dùng
unset($payload['form_data'], $payload['input_values'], $payload['field_content']);

// Kiểm tra CORS: origin phải được đăng ký
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
try {
    $pdo  = db();
    $site = null;
    if ($origin !== '') {
        $row = dbQuery($pdo,
            'SELECT id FROM websites WHERE origin=? AND active=1',
            [rtrim($origin, '/')]
        )->fetch();
        if ($row !== false) {
            header('Access-Control-Allow-Origin: ' . $origin);
            header('Vary: Origin');
        }
    }

    $remoteIp = $_SERVER['REMOTE_ADDR'] ?? '';
    $result   = collectEvent($pdo, $payload, $remoteIp);

    $code = match ($result->status) {
        CollectResult::OK        => 200,
        CollectResult::DUPLICATE => 200,
        CollectResult::CONFLICT  => 409,
        CollectResult::REJECTED  => 400,
        default                  => 500,
    };
    http_response_code($code);
    echo json_encode([
        'status'   => $result->status,
        'message'  => $result->message,
        'event_id' => $result->eventId,
    ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'status'  => 'error',
        'message' => 'Internal server error',
    ]);
}
