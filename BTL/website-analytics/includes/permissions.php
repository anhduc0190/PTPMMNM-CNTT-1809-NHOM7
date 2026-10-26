<?php
// ====================================================================
// INCLUDES: PHAN QUYEN THEO WEBSITE (PERMISSIONS & RBAC)
// ====================================================================

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/auth.php';

/**
 * Kiem tra xem nguoi dung hien tai co quyen thao tac tren website nay hay khong.
 * 
 * @param int $websiteId ID website can kiem tra
 * @param string $action 'view' | 'manage_tracking' | 'export'
 * @return bool
 */
function user_can_access_website(int $websiteId, string $action = 'view'): bool {
    $user = get_auth_user();
    if (!$user) {
        return false;
    }

    // Admin co toan quyen tren tat ca website
    if ($user['role'] === 'admin') {
        return true;
    }

    // Member: kiem tra ban ghi trong bang website_permissions
    $pdo = get_db_connection();
    $stmt = $pdo->prepare('
        SELECT `can_view`, `can_manage_tracking`, `can_export` 
        FROM `website_permissions` 
        WHERE `user_id` = ? AND `website_id` = ? 
        LIMIT 1
    ');
    $stmt->execute([$user['user_id'], $websiteId]);
    $perm = $stmt->fetch();

    if (!$perm) {
        return false;
    }

    switch ($action) {
        case 'manage_tracking':
            return (bool)$perm['can_manage_tracking'];
        case 'export':
            return (bool)$perm['can_export'];
        case 'view':
        default:
            return (bool)$perm['can_view'];
    }
}

/**
 * Bat buoc nguoi dung phai co quyen tren website, neu khong chan dung va tra 403.
 */
function require_website_access(int $websiteId, string $action = 'view'): void {
    if (!user_can_access_website($websiteId, $action)) {
        if (is_api_request()) {
            json_response([
                'success' => false,
                'error'   => 'PERMISSION_DENIED',
                'message' => 'Bạn không được phân quyền truy cập dữ liệu của website này.'
            ], 403);
        } else {
            http_response_code(403);
            echo "<h1>403 Forbidden</h1><p>Bạn không được cấp quyền truy cập website này.</p>";
            exit;
        }
    }
}

/**
 * Lay danh sach tat ca website ma user hien tai co quyen xem.
 * Phuc vu hien thi bo loc dropdown tren Dashboard va bao cao.
 */
function get_accessible_websites(): array {
    $user = get_auth_user();
    if (!$user) {
        return [];
    }

    $pdo = get_db_connection();
    if ($user['role'] === 'admin') {
        $stmt = $pdo->query('
            SELECT `website_id`, `name`, `domain`, `tracking_key`, `timezone`, `status`, `late_event_window_hours`, `created_at` 
            FROM `websites` 
            ORDER BY `website_id` ASC
        ');
        return $stmt->fetchAll();
    } else {
        $stmt = $pdo->prepare('
            SELECT w.`website_id`, w.`name`, w.`domain`, w.`tracking_key`, w.`timezone`, w.`status`, w.`late_event_window_hours`, w.`created_at`,
                   p.`can_view`, p.`can_manage_tracking`, p.`can_export`
            FROM `websites` w
            INNER JOIN `website_permissions` p ON w.`website_id` = p.`website_id`
            WHERE p.`user_id` = ? AND p.`can_view` = 1 AND w.`status` = "active"
            ORDER BY w.`website_id` ASC
        ');
        $stmt->execute([$user['user_id']]);
        return $stmt->fetchAll();
    }
}
