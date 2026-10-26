<?php
declare(strict_types=1);
/**
 * EventCollector.php — Chức năng 2 & 3:
 * Tiếp nhận, validate và lưu sự kiện từ tracker.js.
 *
 * Các ràng buộc an toàn:
 * - Không lưu nội dung trường nhập (form fields).
 * - Chỉ lưu /24 của IP (bỏ octet cuối).
 * - Idempotency: cùng key → không tăng đếm; cùng key khác payload → CONFLICT.
 * - Sự kiện đến muộn: chấp nhận trong MAX_LATE_SECONDS (mặc định 3600s).
 */
require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/WebsiteRegistry.php';

const MAX_LATE_SECONDS  = 3600;   // Sự kiện đến muộn tối đa 1 giờ
const MAX_ERROR_ROWS    = 1000;   // Giới hạn bảng event_errors
const TIMEZONE_DECLARED = 'Asia/Ho_Chi_Minh';

/**
 * Kết quả tiếp nhận sự kiện.
 */
final class CollectResult
{
    public const OK        = 'ok';
    public const DUPLICATE = 'duplicate';   // Gửi lại đúng nội dung → bỏ qua, không đếm
    public const CONFLICT  = 'conflict';    // Cùng key nhưng khác payload → ghi lỗi, từ chối
    public const REJECTED  = 'rejected';    // Không hợp lệ
    public const ERROR     = 'error';

    public function __construct(
        public readonly string  $status,
        public readonly ?int    $eventId = null,
        public readonly string  $message = ''
    ) {}
}

/**
 * Xử lý một payload sự kiện thô từ tracker.js.
 *
 * @param array $payload Dữ liệu JSON đã decode:
 *   - api_key      string  (64 hex)
 *   - session_token string (64 hex — token gốc, sẽ được hash trước khi lưu)
 *   - event_type   string  pageview|click|form_submit|download
 *   - page_path    string  đường dẫn trang
 *   - target       string  selector/url của đối tượng (tuỳ chọn)
 *   - idempotency_key string SHA-256 do client tạo
 *   - client_ts    int     Unix timestamp (giây, UTC) của trình duyệt
 */
function collectEvent(PDO $pdo, array $payload, string $remoteIp = ''): CollectResult
{
    // --- 1. Xác thực website ---
    $apiKey = trim((string) ($payload['api_key'] ?? ''));
    $site   = authenticateByApiKey($pdo, $apiKey);
    if ($site === null) {
        return logAndReject($pdo, null, 'API_KEY_INVALID', $payload, 'API key không hợp lệ');
    }
    $siteId = (int) $site['id'];

    // --- 2. Kiểm tra loại sự kiện được phép ---
    $eventType = trim((string) ($payload['event_type'] ?? ''));
    $pagePath  = '/' . ltrim(trim((string) ($payload['page_path'] ?? '/')), '/');
    $target    = substr(trim((string) ($payload['target'] ?? '')), 0, 255);
    if (!isEventAllowed($pdo, $siteId, $eventType, $pagePath, $target)) {
        return logAndReject($pdo, $siteId, 'EVENT_NOT_CONFIGURED', $payload,
            "Sự kiện '$eventType' không được cấu hình cho website này");
    }

    // --- 3. Validate client_ts và kiểm tra đến muộn ---
    $clientTs = filter_var($payload['client_ts'] ?? 0, FILTER_VALIDATE_INT);
    if ($clientTs === false || $clientTs <= 0) {
        return logAndReject($pdo, $siteId, 'TIMESTAMP_INVALID', $payload, 'client_ts không hợp lệ');
    }
    $serverNow = time();
    $lateness  = $serverNow - $clientTs;
    if (empty($payload['skip_late_check']) && $lateness > MAX_LATE_SECONDS) {
        return logAndReject($pdo, $siteId, 'EVENT_TOO_LATE', $payload,
            "Sự kiện đến muộn {$lateness}s, vượt quá giới hạn " . MAX_LATE_SECONDS . 's');
    }
    // Sự kiện tương lai (clock skew) chấp nhận tối đa 60s
    if (empty($payload['skip_late_check']) && $clientTs - $serverNow > 60) {
        return logAndReject($pdo, $siteId, 'EVENT_FUTURE', $payload, 'client_ts ở tương lai');
    }

    // --- 4. Idempotency key ---
    $ikey = trim((string) ($payload['idempotency_key'] ?? ''));
    if (strlen($ikey) !== 64 || !ctype_xdigit($ikey)) {
        return logAndReject($pdo, $siteId, 'IDEMPOTENCY_KEY_INVALID', $payload, 'idempotency_key sai định dạng');
    }

    // Tạo fingerprint của payload (không bao gồm trường bị cấm)
    $payloadFingerprint = computePayloadFingerprint($eventType, $pagePath, $target, $clientTs);

    // Kiểm tra xem key đã tồn tại chưa
    $existing = dbQuery($pdo,
        'SELECT id, page_path, event_type, target_selector, client_ts FROM events WHERE website_id=? AND idempotency_key=?',
        [$siteId, $ikey]
    )->fetch();

    if ($existing !== false) {
        // So sánh fingerprint — dùng target_selector đã lưu và parse UTC
        $storedTs = (int) strtotime($existing['client_ts'] . ' UTC');
        $existingFP = computePayloadFingerprint(
            $existing['event_type'],
            $existing['page_path'],
            $existing['target_selector'] ?? '',
            $storedTs
        );
        if ($existingFP === $payloadFingerprint) {
            // Duplicate đúng → bỏ qua, trả DUPLICATE (không tăng đếm)
            return new CollectResult(CollectResult::DUPLICATE, (int) $existing['id'],
                'Sự kiện đã được ghi nhận trước đó (không tăng số đếm)');
        } else {
            // Conflict: cùng key nhưng khác nội dung → ghi lỗi, từ chối
            logError($pdo, $siteId, 'IDEMPOTENCY_CONFLICT', $payload);
            return new CollectResult(CollectResult::CONFLICT, null,
                'Cùng idempotency_key nhưng nội dung sự kiện khác — bị từ chối');
        }
    }

    // --- 5. Giải quyết session ---
    $sessionToken = trim((string) ($payload['session_token'] ?? ''));
    if (strlen($sessionToken) < 6) {
        return logAndReject($pdo, $siteId, 'SESSION_TOKEN_INVALID', $payload, 'session_token quá ngắn');
    }
    // Hash token trước khi lưu (không lưu token gốc)
    $sessionHash = hash('sha256', $siteId . ':' . $sessionToken);
    $sessionId   = resolveSession($pdo, $siteId, $sessionHash, $clientTs);

    // --- 6. Tìm event_definition phù hợp ---
    $defId = null;
    $defRow = dbQuery($pdo,
        "SELECT id FROM event_definitions
         WHERE website_id=? AND event_type=? AND (target=? OR target='*') AND active=1
         LIMIT 1",
        [$siteId, $eventType, $target ?: $pagePath]
    )->fetch();
    if ($defRow !== false) {
        $defId = (int) $defRow['id'];
    }

    // --- 7. Lưu sự kiện (không lưu nội dung trường nhập) ---
    $ipPrefix = anonymizeIp($remoteIp);
    $clientDt = gmdate('Y-m-d H:i:s', $clientTs);

    dbQuery($pdo,
        'INSERT INTO events
            (website_id, session_id, event_definition_id, event_type, page_path,
             target_selector, idempotency_key, client_ts, ip_prefix)
         VALUES (?,?,?,?,?,?,?,?,?)',
        [$siteId, $sessionId, $defId, $eventType, $pagePath,
         $target, $ikey, $clientDt, $ipPrefix]
    );
    $eventId = (int) $pdo->lastInsertId();

    return new CollectResult(CollectResult::OK, $eventId, 'Sự kiện đã được ghi nhận');
}

// ---------------------------------------------------------------------------
// Các hàm nội bộ
// ---------------------------------------------------------------------------

/**
 * Tạo hoặc cập nhật session; trả về session.id.
 */
function resolveSession(PDO $pdo, int $siteId, string $sessionHash, int $clientTs): int
{
    $clientDt = gmdate('Y-m-d H:i:s', $clientTs);
    $row = dbQuery($pdo,
        'SELECT id FROM sessions WHERE website_id=? AND session_token=?',
        [$siteId, $sessionHash]
    )->fetch();

    if ($row !== false) {
        // Cập nhật last_seen
        dbQuery($pdo,
            'UPDATE sessions SET last_seen_at=? WHERE id=?',
            [$clientDt, $row['id']]
        );
        return (int) $row['id'];
    }

    dbQuery($pdo,
        'INSERT INTO sessions (website_id, session_token, started_at, last_seen_at) VALUES (?,?,?,?)',
        [$siteId, $sessionHash, $clientDt, $clientDt]
    );
    return (int) $pdo->lastInsertId();
}

/**
 * Ẩn danh hoá IP: chỉ giữ 3 octet đầu (IPv4) hoặc /48 (IPv6).
 */
function anonymizeIp(string $ip): string
{
    if ($ip === '') {
        return '';
    }
    if (str_contains($ip, ':')) {
        // IPv6: giữ 3 nhóm đầu
        $parts = explode(':', $ip);
        return implode(':', array_slice($parts, 0, 3)) . '::/48';
    }
    $parts = explode('.', $ip);
    if (count($parts) === 4) {
        return $parts[0] . '.' . $parts[1] . '.' . $parts[2] . '.0/24';
    }
    return '';
}

/**
 * Tính fingerprint của payload (không bao gồm nội dung form).
 */
function computePayloadFingerprint(
    string $eventType,
    string $pagePath,
    string $target,
    int    $clientTs
): string {
    return hash('sha256', implode('|', [$eventType, $pagePath, $target, $clientTs]));
}

/**
 * Ghi lỗi và trả về CollectResult::REJECTED.
 */
function logAndReject(
    PDO     $pdo,
    ?int    $siteId,
    string  $code,
    array   $payload,
    string  $msg
): CollectResult {
    logError($pdo, $siteId, $code, $payload);
    return new CollectResult(CollectResult::REJECTED, null, $msg);
}

/**
 * Ghi một dòng vào event_errors; tự xoay vòng nếu vượt MAX_ERROR_ROWS.
 */
function logError(PDO $pdo, ?int $siteId, string $code, array $payload): void
{
    try {
        $count = (int) dbQuery($pdo, 'SELECT COUNT(*) AS c FROM event_errors')->fetch()['c'];
        if ($count >= MAX_ERROR_ROWS) {
            // Xoá 100 dòng cũ nhất
            dbQuery($pdo, 'DELETE FROM event_errors ORDER BY id ASC LIMIT 100');
        }
        $hash = hash('sha256', json_encode($payload, JSON_UNESCAPED_UNICODE));
        dbQuery($pdo,
            'INSERT INTO event_errors (website_id, error_code, payload_hash) VALUES (?,?,?)',
            [$siteId, $code, $hash]
        );
    } catch (Exception) {
        // Không để lỗi logging làm hỏng luồng chính
    }
}
