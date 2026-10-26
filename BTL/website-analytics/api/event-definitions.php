<?php
// ====================================================================
// REST API: QUAN LY DINH NGHIA SU KIEN (api/event-definitions.php)
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

// GET: Lay danh sach event definitions cua 1 website
if ($method === 'GET') {
    $websiteId = (int)($_GET['website_id'] ?? 0);
    if ($websiteId <= 0) {
        json_response(['success' => false, 'error' => 'MISSING_PARAM', 'message' => 'Vui lĂ²ng cung cáº¥p website_id.'], 400);
    }

    require_website_access($websiteId, 'view');

    $stmt = $pdo->prepare('
        SELECT `definition_id`, `website_id`, `event_name`, `description`, `enabled`, `created_at`
        FROM `event_definitions`
        WHERE `website_id` = ?
        ORDER BY `definition_id` ASC
    ');
    $stmt->execute([$websiteId]);
    $definitions = $stmt->fetchAll();

    json_response([
        'success'     => true,
        'website_id'  => $websiteId,
        'definitions' => $definitions
    ]);
}

// POST: Them moi hoac Bat/Tat su kien
if ($method === 'POST') {
    $websiteId = (int)($_POST['website_id'] ?? 0);
    if ($websiteId <= 0) {
        json_response(['success' => false, 'error' => 'MISSING_PARAM', 'message' => 'Vui lĂ²ng cung cáº¥p website_id.'], 400);
    }

    // Member phai co quyen can_manage_tracking hoac la Admin
    require_website_access($websiteId, 'manage_tracking');
    require_csrf_token();

    $action = $_POST['action'] ?? 'toggle';

    if ($action === 'toggle') {
        $defId   = (int)($_POST['definition_id'] ?? 0);
        $enabled = (int)($_POST['enabled'] ?? 1);

        $stmt = $pdo->prepare('UPDATE `event_definitions` SET `enabled` = ? WHERE `definition_id` = ? AND `website_id` = ?');
        $stmt->execute([$enabled ? 1 : 0, $defId, $websiteId]);

        json_response([
            'success' => true,
            'message' => 'Cáº­p nháº­t tráº¡ng thĂ¡i sá»± kiá»‡n thĂ nh cĂ´ng.'
        ]);
    }

    if ($action === 'create') {
        $eventName = trim($_POST['event_name'] ?? '');
        $desc      = trim($_POST['description'] ?? '');

        if (!preg_match('/^[a-z0-9_]{2,50}$/', $eventName)) {
            json_response(['success' => false, 'error' => 'INVALID_NAME', 'message' => 'TĂªn sá»± kiá»‡n chá»‰ gá»“m chá»¯ cĂ¡i thÆ°á»ng, sá»‘ vĂ  dáº¥u gáº¡ch dÆ°á»›i (2-50 kĂ½ tá»±).'], 400);
        }

        try {
            $stmt = $pdo->prepare('INSERT INTO `event_definitions` (`website_id`, `event_name`, `description`, `enabled`, `created_at`) VALUES (?, ?, ?, 1, NOW())');
            $stmt->execute([$websiteId, $eventName, $desc]);

            json_response([
                'success'       => true,
                'message'       => 'Äá»‹nh nghÄ©a sá»± kiá»‡n má»›i thĂ nh cĂ´ng.',
                'definition_id' => (int)$pdo->lastInsertId()
            ], 201);
        } catch (PDOException $e) {
            if ($e->getCode() === '23000') {
                json_response(['success' => false, 'error' => 'DUPLICATE_NAME', 'message' => 'TĂªn sá»± kiá»‡n nĂ y Ä‘Ă£ Ä‘Æ°á»£c khai bĂ¡o trĂªn website.'], 409);
            }
            throw $e;
        }
    }

    json_response(['success' => false, 'error' => 'INVALID_ACTION'], 400);
}

json_response(['success' => false, 'error' => 'METHOD_NOT_ALLOWED'], 405);
