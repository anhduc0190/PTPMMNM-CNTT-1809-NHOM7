<?php
// ====================================================================
// REST API: QUAN LY WEBSITES (api/websites.php)
// ====================================================================

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/permissions.php';
require_once __DIR__ . '/../includes/csrf.php';

header('Content-Type: application/json; charset=utf-8');
require_login();

$user = get_auth_user();
$pdo  = get_db_connection();
$method = $_SERVER['REQUEST_METHOD'];

// GET: Lay danh sach website ma user co quyen xem
if ($method === 'GET') {
    $websites = get_accessible_websites();
    json_response([
        'success'  => true,
        'count'    => count($websites),
        'websites' => $websites
    ]);
}

// POST: Them moi hoac cap nhat website (Chi danh cho Admin)
if ($method === 'POST') {
    require_admin();
    require_csrf_token();

    $action = $_POST['action'] ?? 'create';

    if ($action === 'create') {
        $name     = trim($_POST['name'] ?? '');
        $domain   = trim($_POST['domain'] ?? '');
        $timezone = trim($_POST['timezone'] ?? 'Asia/Ho_Chi_Minh');
        $window   = (int)($_POST['late_event_window_hours'] ?? 24);

        if (empty($name) || empty($domain)) {
            json_response(['success' => false, 'error' => 'VALIDATION_ERROR', 'message' => 'TĂªn website vĂ  Domain khĂ´ng Ä‘Æ°á»£c Ä‘á»ƒ trá»‘ng.'], 400);
        }

        // Sinh tracking_key ngau nhien duy nhat
        $trackingKey = 'trk_' . bin2hex(random_bytes(16));

        $stmt = $pdo->prepare('
            INSERT INTO `websites` (`name`, `domain`, `tracking_key`, `timezone`, `status`, `late_event_window_hours`, `created_at`)
            VALUES (?, ?, ?, ?, "active", ?, NOW())
        ');
        $stmt->execute([$name, $domain, $trackingKey, $timezone, $window]);
        $newId = (int)$pdo->lastInsertId();

        // Mac dinh tu dong tao 4 dinh nghia su kien chuan cho website moi
        $standardEvents = [
            'page_view'         => 'Ghi nháº­n lÆ°á»£t xem trang tá»± Ä‘á»™ng',
            'button_click'      => 'Thao tĂ¡c báº¥m nĂºt hoáº·c liĂªn káº¿t hĂ nh Ä‘á»™ng',
            'download_start'    => 'Báº¯t Ä‘áº§u táº£i tĂ i liá»‡u hoáº·c file dá»¯ liá»‡u',
            'download_complete' => 'HoĂ n táº¥t táº£i file thĂ nh cĂ´ng'
        ];
        $stmtDef = $pdo->prepare('INSERT INTO `event_definitions` (`website_id`, `event_name`, `description`, `enabled`, `created_at`) VALUES (?, ?, ?, 1, NOW())');
        foreach ($standardEvents as $evtName => $desc) {
            $stmtDef->execute([$newId, $evtName, $desc]);
        }

        json_response([
            'success'      => true,
            'message'      => 'ThĂªm website má»›i thĂ nh cĂ´ng.',
            'website_id'   => $newId,
            'tracking_key' => $trackingKey
        ], 201);
    }

    if ($action === 'toggle_status') {
        $siteId = (int)($_POST['website_id'] ?? 0);
        $status = $_POST['status'] ?? 'active';
        if (!in_array($status, ['active', 'paused', 'archived'], true)) {
            json_response(['success' => false, 'error' => 'INVALID_STATUS', 'message' => 'Tráº¡ng thĂ¡i khĂ´ng há»£p lá»‡.'], 400);
        }

        $stmt = $pdo->prepare('UPDATE `websites` SET `status` = ? WHERE `website_id` = ?');
        $stmt->execute([$status, $siteId]);

        json_response([
            'success' => true,
            'message' => 'Cáº­p nháº­t tráº¡ng thĂ¡i website thĂ nh cĂ´ng.'
        ]);
    }

    json_response(['success' => false, 'error' => 'INVALID_ACTION', 'message' => 'HĂ nh Ä‘á»™ng khĂ´ng há»£p lá»‡.'], 400);
}

json_response(['success' => false, 'error' => 'METHOD_NOT_ALLOWED'], 405);
