<?php
declare(strict_types=1);
/**
 * WebsiteRegistry.php — Chức năng 1:
 * Đăng ký website thử, định nghĩa sự kiện và quản lý quyền xem báo cáo.
 */
require_once __DIR__ . '/Database.php';

/**
 * Đăng ký một website mới. Trả về website_id và api_key đã tạo.
 * Nếu origin đã tồn tại thì trả về bản ghi hiện có.
 */
function registerWebsite(PDO $pdo, string $name, string $origin, string $timezone = 'Asia/Ho_Chi_Minh'): array
{
    $name   = trim($name);
    $origin = rtrim(trim($origin), '/');

    if ($name === '' || $origin === '') {
        throw new InvalidArgumentException('WEBSITE_NAME_AND_ORIGIN_REQUIRED');
    }
    if (!filter_var($origin, FILTER_VALIDATE_URL)) {
        throw new InvalidArgumentException('WEBSITE_ORIGIN_INVALID');
    }

    // Kiểm tra đã tồn tại chưa
    $existing = dbQuery($pdo,
        'SELECT id, name, origin, api_key, timezone FROM websites WHERE origin = ?',
        [$origin]
    )->fetch();
    if ($existing !== false) {
        return $existing;
    }

    $apiKey = bin2hex(random_bytes(32)); // 64-char hex
    dbQuery($pdo,
        'INSERT INTO websites (name, origin, api_key, timezone) VALUES (?, ?, ?, ?)',
        [$name, $origin, $apiKey, $timezone]
    );
    return [
        'id'       => (int) $pdo->lastInsertId(),
        'name'     => $name,
        'origin'   => $origin,
        'api_key'  => $apiKey,
        'timezone' => $timezone,
    ];
}

/**
 * Thêm định nghĩa sự kiện được phép theo dõi trên một website.
 * Trả về definition id. Nếu đã tồn tại (website+type+target) thì trả về id cũ.
 */
function addEventDefinition(
    PDO    $pdo,
    int    $websiteId,
    string $eventType,
    string $target      = '*',
    string $description = ''
): int {
    $allowed = ['pageview', 'click', 'form_submit', 'download'];
    if (!in_array($eventType, $allowed, true)) {
        throw new InvalidArgumentException('EVENT_TYPE_UNKNOWN: ' . $eventType);
    }

    $existing = dbQuery($pdo,
        'SELECT id FROM event_definitions WHERE website_id=? AND event_type=? AND target=?',
        [$websiteId, $eventType, $target]
    )->fetch();
    if ($existing !== false) {
        return (int) $existing['id'];
    }

    dbQuery($pdo,
        'INSERT INTO event_definitions (website_id, event_type, target, description) VALUES (?,?,?,?)',
        [$websiteId, $eventType, $target, $description]
    );
    return (int) $pdo->lastInsertId();
}

/**
 * Lấy danh sách website còn hoạt động cùng các định nghĩa sự kiện.
 */
function listWebsites(PDO $pdo): array
{
    $sites = dbQuery($pdo,
        'SELECT id, name, origin, timezone, created_at FROM websites WHERE active=1 ORDER BY id'
    )->fetchAll();

    foreach ($sites as &$site) {
        $site['event_definitions'] = dbQuery($pdo,
            'SELECT id, event_type, target, description FROM event_definitions
             WHERE website_id=? AND active=1 ORDER BY id',
            [$site['id']]
        )->fetchAll();
    }
    unset($site);
    return $sites;
}

/**
 * Xác thực API key; trả về mảng website hoặc null nếu không hợp lệ.
 */
function authenticateByApiKey(PDO $pdo, string $apiKey): ?array
{
    if (strlen($apiKey) !== 64) {
        return null;
    }
    $row = dbQuery($pdo,
        'SELECT id, name, origin, timezone FROM websites WHERE api_key=? AND active=1',
        [$apiKey]
    )->fetch();
    return $row !== false ? $row : null;
}

/**
 * Kiểm tra xem event_type + page_path có được cấu hình cho website không.
 * - target='*' trong event_definitions cho phép mọi trang và mọi target.
 * - Nếu định nghĩa có target cụ thể thì kiểm tra khớp với target sự kiện.
 */
function isEventAllowed(PDO $pdo, int $websiteId, string $eventType, string $pagePath, string $eventTarget = ''): bool
{
    $rows = dbQuery($pdo,
        'SELECT target FROM event_definitions
         WHERE website_id=? AND event_type=? AND active=1',
        [$websiteId, $eventType]
    )->fetchAll();

    if (empty($rows)) {
        return false;
    }
    foreach ($rows as $row) {
        $defTarget = $row['target'];
        // Wildcard: cho phép mọi trang và mọi target
        if ($defTarget === '*') {
            return true;
        }
        // Target là selector (đầu bằng '#' hoặc '.'): so khớp với event target
        if (str_starts_with($defTarget, '#') || str_starts_with($defTarget, '.')) {
            if ($defTarget === $eventTarget) {
                return true;
            }
        } else {
            // Target là path: so khớp với page_path
            if ($defTarget === $pagePath || str_starts_with($pagePath, $defTarget)) {
                return true;
            }
        }
    }
    return false;
}
