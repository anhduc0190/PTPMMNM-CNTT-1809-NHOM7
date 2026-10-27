<?php
// ====================================================================
// INCLUDES: CAC HAM BO TRO (HELPERS)
// ====================================================================

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/icons.php';

/**
 * Xoa bo ma doc XSS khi xuat chuoi ra HTML.
 */
function e(?string $str): string {
    return htmlspecialchars((string)$str, ENT_QUOTES, 'UTF-8');
}

/**
 * Tra ve phan hoi JSON chuan REST API.
 */
function json_response(array $data, int $statusCode = 200): void {
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

/**
 * Chuan hoa (canonicalize) payload de tao ma bam (hash) chong trung nhat quan.
 * Quy tac:
 *  1. Sap xep khoa thuoc tinh (ksort) de quy.
 *  2. Loai bo cac khoang trang thua va volatile headers.
 *  3. Encode JSON co dinh dang khong format.
 *  4. Tinh SHA-256 tren chuoi JSON da chuan hoa.
 */
function canonicalize_payload(array $payload): array {
    $normalized = sort_array_recursive($payload);
    $canonicalJson = json_encode($normalized, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $hash = hash('sha256', $canonicalJson);

    return [
        'normalized_data' => $normalized,
        'canonical_json'  => $canonicalJson,
        'hash'            => $hash
    ];
}

/**
 * Ham de quy sap xep khoa array
 */
function sort_array_recursive(array $array): array {
    foreach ($array as $key => $value) {
        if (is_array($value)) {
            $array[$key] = sort_array_recursive($value);
        }
    }
    ksort($array, SORT_STRING);
    return $array;
}

/**
 * Loc bo cac truong nhay cam va chi giu lai cac thuoc tinh hop le.
 * Cam tuyet doi: mat khau, so the, input text, email ca nhan, IP day du.
 */
function sanitize_properties(?array $properties): ?array {
    if (empty($properties)) {
        return null;
    }

    $sanitized = [];
    $forbiddenSubstrings = ['pass', 'pwd', 'secret', 'token', 'credit', 'card', 'cvv', 'ssn', 'phone', 'email', 'input', 'field'];

    foreach ($properties as $key => $value) {
        $lowerKey = strtolower((string)$key);

        // Kiem tra tu khoa cam
        $isForbidden = false;
        foreach ($forbiddenSubstrings as $badWord) {
            if (strpos($lowerKey, $badWord) !== false) {
                $isForbidden = true;
                break;
            }
        }
        if ($isForbidden) {
            continue;
        }

        // Chi chap nhan cac khoa trong ALLOWED_PROPERTIES hoac khoa an toan co ban
        if (in_array($lowerKey, ALLOWED_PROPERTIES, true) || preg_match('/^[a-z0-9_]{2,30}$/', $lowerKey)) {
            if (is_scalar($value)) {
                // Gioi han do dai chuoi gia tri khong qua 255 ky tu
                $sanitized[$lowerKey] = mb_substr((string)$value, 0, 255, 'UTF-8');
            } elseif (is_null($value)) {
                $sanitized[$lowerKey] = null;
            }
        }
    }

    return empty($sanitized) ? null : $sanitized;
}

/**
 * Ghi nhat ky audit query cho cac truy van bao cao
 */
function log_audit_query(int $userId, ?int $websiteId, string $queryType, ?array $filters = null): void {
    try {
        $pdo = get_db_connection();
        $stmt = $pdo->prepare('
            INSERT INTO `report_queries` (`user_id`, `website_id`, `query_type`, `filters`, `executed_at`)
            VALUES (?, ?, ?, ?, NOW())
        ');
        $stmt->execute([
            $userId,
            $websiteId,
            $queryType,
            $filters ? json_encode($filters, JSON_UNESCAPED_UNICODE) : null
        ]);
    } catch (Exception $e) {
        error_log("[AUDIT LOG ERROR] " . $e->getMessage());
    }
}

/**
 * Tinh base URL tu dong theo thu muc trien khai tren XAMPP hoac PHP server.
 */
function get_base_url(): string {
    $scriptDir = dirname($_SERVER['SCRIPT_NAME'] ?? '');
    $scriptDir = str_replace('\\', '/', $scriptDir);
    
    // Loai bo cac thu muc con neu file dang chay trong /api hoac /includes hoac /demo-sites
    $cleanDir = preg_replace('#/(api|includes|sdk|demo-sites.*|tests.*|scripts.*)$#', '', $scriptDir);
    $cleanDir = rtrim($cleanDir, '/');

    return $cleanDir;
}
