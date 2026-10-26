<?php
// ====================================================================
// SCRIPT: SINH BO DU LIEU KIEM THU 225 REQUESTS CO DAP AN XAC DINH
// (scripts/generate-test-data.php)
// Bao gom: 2 website, 6 trang moi web, 4 loai su kien, nua dem, trung,
// xung dot, su kien den muon, chuoi hoan tat, chuoi thieu buoc, sai thu tu.
// ====================================================================

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/helpers.php';

function run_test_data_generator(bool $silent = false): array {
    $pdo = get_db_connection();

    // 1. Lam sach du lieu su kien cu truoc khi chay
    $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
    $pdo->exec('TRUNCATE TABLE `events`');
    $pdo->exec('TRUNCATE TABLE `sessions`');
    $pdo->exec('TRUNCATE TABLE `event_ingest_errors`');
    $pdo->exec('TRUNCATE TABLE `report_queries`');
    $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');

    $siteAKey = 'trk_site_a_alpha_shop_2026';
    $siteBKey = 'trk_site_b_beta_portal_2026';

    $stats = [
        'total_requests'     => 0,
        'accepted_new'       => 0,
        'duplicate_case_a'   => 0,
        'conflict_case_b'    => 0,
        'rejected_late'      => 0,
        'rejected_undefined' => 0,
        'rejected_bad_key'   => 0,
        'site_a_events'      => 0,
        'site_b_events'      => 0,
        'funnel_completed_a' => 0,
        'funnel_completed_b' => 0
    ];

    $recordedEvents = []; // Luu de tao Case A va Case B

    // Ham gia lap goi API track.php truc tiep qua ham noi bo
    $processTrack = function(array $payload) use ($pdo, &$stats, &$recordedEvents) {
        $stats['total_requests']++;

        $trackingKey = trim($payload['tracking_key'] ?? '');
        $eventId     = trim($payload['event_id'] ?? '');
        $sessionId   = trim($payload['session_id'] ?? '');
        $eventName   = trim($payload['event_name'] ?? '');
        $pagePath    = trim($payload['page_path'] ?? '/');
        $occurredAt  = trim($payload['occurred_at'] ?? '');
        $rawProps    = isset($payload['properties']) && is_array($payload['properties']) ? $payload['properties'] : [];

        // 1. Tim website
        $stmtSite = $pdo->prepare('SELECT `website_id`, `status`, `late_event_window_hours` FROM `websites` WHERE `tracking_key` = ? LIMIT 1');
        $stmtSite->execute([$trackingKey]);
        $site = $stmtSite->fetch();

        if (!$site) {
            $stats['rejected_bad_key']++;
            return ['status' => 404, 'error' => 'WEBSITE_NOT_FOUND'];
        }
        $siteId = (int)$site['website_id'];

        // 2. Kiem tra dinh nghia su kien
        $stmtDef = $pdo->prepare('SELECT `definition_id`, `enabled` FROM `event_definitions` WHERE `website_id` = ? AND `event_name` = ? LIMIT 1');
        $stmtDef->execute([$siteId, $eventName]);
        $def = $stmtDef->fetch();

        if (!$def || (int)$def['enabled'] !== 1) {
            $stats['rejected_undefined']++;
            $stmtErr = $pdo->prepare('INSERT INTO `event_ingest_errors` (`website_id`, `event_id`, `error_code`, `occurred_at`, `details`) VALUES (?, ?, "EVENT_NOT_DEFINED", ?, ?)');
            $stmtErr->execute([$siteId, $eventId, date('Y-m-d H:i:s', strtotime($occurredAt)), "Sự kiện chưa được khai báo"]);
            return ['status' => 400, 'error' => 'EVENT_NOT_DEFINED'];
        }

        // 3. Kiem tra thoi gian & late window
        $occTime = strtotime($occurredAt);
        $now = time();
        $lateHours = (int)$site['late_event_window_hours'] ?: 24;
        if (($now - $occTime) > ($lateHours * 3600)) {
            $stats['rejected_late']++;
            $stmtErr = $pdo->prepare('INSERT INTO `event_ingest_errors` (`website_id`, `event_id`, `error_code`, `occurred_at`, `details`) VALUES (?, ?, "LATE_EVENT_EXCEEDED", ?, ?)');
            $stmtErr->execute([$siteId, $eventId, date('Y-m-d H:i:s', $occTime), "Sự kiện đến muộn quá {$lateHours} giờ"]);
            return ['status' => 422, 'error' => 'LATE_EVENT_EXCEEDED'];
        }

        $cleanProps = sanitize_properties($rawProps);
        $occFormatted = date('Y-m-d H:i:s', $occTime);
        $canonical = canonicalize_payload([
            'event_id'    => $eventId,
            'session_id'  => $sessionId,
            'event_name'  => $eventName,
            'page_path'   => $pagePath,
            'occurred_at' => $occFormatted,
            'properties'  => $cleanProps
        ]);
        $hash = $canonical['hash'];

        // 4. Kiem tra chong trung (Idempotency)
        $stmtCheck = $pdo->prepare('SELECT `payload_hash` FROM `events` WHERE `website_id` = ? AND `event_id` = ? LIMIT 1');
        $stmtCheck->execute([$siteId, $eventId]);
        $existing = $stmtCheck->fetch();

        if ($existing) {
            if ($existing['payload_hash'] === $hash) {
                // Case A
                $stats['duplicate_case_a']++;
                return ['status' => 200, 'result' => 'duplicate_idempotent'];
            } else {
                // Case B
                $stats['conflict_case_b']++;
                $stmtErr = $pdo->prepare('INSERT INTO `event_ingest_errors` (`website_id`, `event_id`, `error_code`, `payload_hash`, `occurred_at`, `details`) VALUES (?, ?, "PAYLOAD_CONFLICT", ?, ?, ?)');
                $stmtErr->execute([$siteId, $eventId, $hash, $occFormatted, "Cùng event_id nhưng khác payload"]);
                return ['status' => 409, 'error' => 'PAYLOAD_CONFLICT'];
            }
        }

        // Case C: Hop le
        $pdo->beginTransaction();
        $stmtSess = $pdo->prepare('INSERT INTO `sessions` (`website_id`, `session_id`, `started_at`, `last_seen_at`) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE `last_seen_at` = GREATEST(`last_seen_at`, VALUES(`last_seen_at`))');
        $stmtSess->execute([$siteId, $sessionId, $occFormatted, $occFormatted]);

        $stmtIns = $pdo->prepare('INSERT INTO `events` (`website_id`, `event_id`, `session_id`, `event_name`, `page_path`, `occurred_at`, `received_at`, `payload_hash`, `properties`) VALUES (?, ?, ?, ?, ?, ?, NOW(), ?, ?)');
        $stmtIns->execute([$siteId, $eventId, $sessionId, $eventName, $pagePath, $occFormatted, $hash, $cleanProps ? json_encode($cleanProps) : null]);
        $pdo->commit();

        $stats['accepted_new']++;
        if ($siteId === 1) $stats['site_a_events']++;
        if ($siteId === 2) $stats['site_b_events']++;

        $recordedEvents[] = $payload;
        return ['status' => 201, 'result' => 'created'];
    };

    // ================================================================
    // A. TAO SU KIEN CHUAN CHO WEBSITE A (ALPHA STORE)
    // ================================================================
    $today = date('Y-m-d');
    $yesterday = date('Y-m-d', strtotime('-1 day'));

    // 1. 15 Sessions hoan tat chuoi: page_view -> download_start -> download_complete (45 events)
    for ($i = 1; $i <= 15; $i++) {
        $sess = "sess_a_funnel_comp_{$i}";
        $t1 = date('Y-m-d H:i:s', strtotime("{$today} 09:00:00 + {$i} minutes"));
        $t2 = date('Y-m-d H:i:s', strtotime("{$t1} + 30 seconds"));
        $t3 = date('Y-m-d H:i:s', strtotime("{$t2} + 45 seconds"));

        $processTrack([
            'tracking_key' => $siteAKey,
            'event_id'     => "evt_a_f1_{$i}",
            'session_id'   => $sess,
            'event_name'   => 'page_view',
            'page_path'    => '/download.html',
            'occurred_at'  => $t1
        ]);
        $processTrack([
            'tracking_key' => $siteAKey,
            'event_id'     => "evt_a_f2_{$i}",
            'session_id'   => $sess,
            'event_name'   => 'download_start',
            'page_path'    => '/download.html',
            'occurred_at'  => $t2,
            'properties'   => ['file_name' => 'catalog-2026.pdf']
        ]);
        $processTrack([
            'tracking_key' => $siteAKey,
            'event_id'     => "evt_a_f3_{$i}",
            'session_id'   => $sess,
            'event_name'   => 'download_complete',
            'page_path'    => '/download.html',
            'occurred_at'  => $t3,
            'properties'   => ['file_name' => 'catalog-2026.pdf', 'file_size_kb' => 1250]
        ]);
        $stats['funnel_completed_a']++;
    }

    // 2. 10 Sessions bo do sau buoc 2: page_view -> download_start (20 events)
    for ($i = 1; $i <= 10; $i++) {
        $sess = "sess_a_funnel_drop_{$i}";
        $t1 = date('Y-m-d H:i:s', strtotime("{$today} 11:00:00 + {$i} minutes"));
        $t2 = date('Y-m-d H:i:s', strtotime("{$t1} + 20 seconds"));

        $processTrack([
            'tracking_key' => $siteAKey,
            'event_id'     => "evt_a_drop1_{$i}",
            'session_id'   => $sess,
            'event_name'   => 'page_view',
            'page_path'    => '/download.html',
            'occurred_at'  => $t1
        ]);
        $processTrack([
            'tracking_key' => $siteAKey,
            'event_id'     => "evt_a_drop2_{$i}",
            'session_id'   => $sess,
            'event_name'   => 'download_start',
            'page_path'    => '/download.html',
            'occurred_at'  => $t2,
            'properties'   => ['file_name' => 'catalog-2026.pdf']
        ]);
    }

    // 3. 15 Sessions xem trang chu, san pham va bam nut (45 events)
    for ($i = 1; $i <= 15; $i++) {
        $sess = "sess_a_browse_{$i}";
        $t = date('Y-m-d H:i:s', strtotime("{$today} 14:00:00 + " . ($i * 2) . " minutes"));

        $processTrack([
            'tracking_key' => $siteAKey,
            'event_id'     => "evt_a_br1_{$i}",
            'session_id'   => $sess,
            'event_name'   => 'page_view',
            'page_path'    => '/index.html',
            'occurred_at'  => $t
        ]);
        $processTrack([
            'tracking_key' => $siteAKey,
            'event_id'     => "evt_a_br2_{$i}",
            'session_id'   => $sess,
            'event_name'   => 'page_view',
            'page_path'    => '/products.html',
            'occurred_at'  => date('Y-m-d H:i:s', strtotime("{$t} + 40 seconds"))
        ]);
        $processTrack([
            'tracking_key' => $siteAKey,
            'event_id'     => "evt_a_br3_{$i}",
            'session_id'   => $sess,
            'event_name'   => 'button_click',
            'page_path'    => '/products.html',
            'occurred_at'  => date('Y-m-d H:i:s', strtotime("{$t} + 70 seconds")),
            'properties'   => ['element_id' => 'btn-prod-1-buy', 'button_text' => 'Chọn mua']
        ]);
    }

    // 4. 5 Sessions xay ra qua nua dem (10 events: 23:58 hom qua va 00:03 hom nay)
    for ($i = 1; $i <= 5; $i++) {
        $sess = "sess_a_midnight_{$i}";
        $tMidnight1 = "{$yesterday} 23:58:" . str_pad($i * 10, 2, '0', STR_PAD_LEFT);
        $tMidnight2 = "{$today} 00:03:" . str_pad($i * 10, 2, '0', STR_PAD_LEFT);

        $processTrack([
            'tracking_key' => $siteAKey,
            'event_id'     => "evt_a_mid1_{$i}",
            'session_id'   => $sess,
            'event_name'   => 'page_view',
            'page_path'    => '/product-detail.html',
            'occurred_at'  => $tMidnight1
        ]);
        $processTrack([
            'tracking_key' => $siteAKey,
            'event_id'     => "evt_a_mid2_{$i}",
            'session_id'   => $sess,
            'event_name'   => 'button_click',
            'page_path'    => '/product-detail.html',
            'occurred_at'  => $tMidnight2,
            'properties'   => ['element_id' => 'btn-add-cart-detail', 'button_text' => 'Thêm Vào Giỏ Hàng']
        ]);
    }

    // ================================================================
    // B. TAO SU KIEN CHUAN CHO WEBSITE B (BETA SERVICES)
    // ================================================================
    // 1. 10 Sessions hoan tat chuoi: page_view -> download_start -> download_complete (30 events)
    for ($i = 1; $i <= 10; $i++) {
        $sess = "sess_b_funnel_comp_{$i}";
        $t1 = date('Y-m-d H:i:s', strtotime("{$today} 10:00:00 + {$i} minutes"));
        $t2 = date('Y-m-d H:i:s', strtotime("{$t1} + 25 seconds"));
        $t3 = date('Y-m-d H:i:s', strtotime("{$t2} + 50 seconds"));

        $processTrack([
            'tracking_key' => $siteBKey,
            'event_id'     => "evt_b_f1_{$i}",
            'session_id'   => $sess,
            'event_name'   => 'page_view',
            'page_path'    => '/register.html',
            'occurred_at'  => $t1
        ]);
        $processTrack([
            'tracking_key' => $siteBKey,
            'event_id'     => "evt_b_f2_{$i}",
            'session_id'   => $sess,
            'event_name'   => 'download_start',
            'page_path'    => '/register.html',
            'occurred_at'  => $t2,
            'properties'   => ['file_name' => 'company-profile.pdf']
        ]);
        $processTrack([
            'tracking_key' => $siteBKey,
            'event_id'     => "evt_b_f3_{$i}",
            'session_id'   => $sess,
            'event_name'   => 'download_complete',
            'page_path'    => '/register.html',
            'occurred_at'  => $t3,
            'properties'   => ['file_name' => 'company-profile.pdf', 'file_size_kb' => 840]
        ]);
        $stats['funnel_completed_b']++;
    }

    // 2. 5 Sessions bo do tai buoc 2 (10 events)
    for ($i = 1; $i <= 5; $i++) {
        $sess = "sess_b_drop_{$i}";
        $t1 = date('Y-m-d H:i:s', strtotime("{$today} 13:00:00 + {$i} minutes"));
        $t2 = date('Y-m-d H:i:s', strtotime("{$t1} + 30 seconds"));

        $processTrack([
            'tracking_key' => $siteBKey,
            'event_id'     => "evt_b_drop1_{$i}",
            'session_id'   => $sess,
            'event_name'   => 'page_view',
            'page_path'    => '/register.html',
            'occurred_at'  => $t1
        ]);
        $processTrack([
            'tracking_key' => $siteBKey,
            'event_id'     => "evt_b_drop2_{$i}",
            'session_id'   => $sess,
            'event_name'   => 'download_start',
            'page_path'    => '/register.html',
            'occurred_at'  => $t2,
            'properties'   => ['file_name' => 'company-profile.pdf']
        ]);
    }

    // 3. 10 Sessions xem trang dich vu va bam nut tu van (20 events)
    for ($i = 1; $i <= 10; $i++) {
        $sess = "sess_b_browse_{$i}";
        $t = date('Y-m-d H:i:s', strtotime("{$today} 15:30:00 + " . ($i * 2) . " minutes"));

        $processTrack([
            'tracking_key' => $siteBKey,
            'event_id'     => "evt_b_br1_{$i}",
            'session_id'   => $sess,
            'event_name'   => 'page_view',
            'page_path'    => '/services.html',
            'occurred_at'  => $t
        ]);
        $processTrack([
            'tracking_key' => $siteBKey,
            'event_id'     => "evt_b_br2_{$i}",
            'session_id'   => $sess,
            'event_name'   => 'button_click',
            'page_path'    => '/services.html',
            'occurred_at'  => date('Y-m-d H:i:s', strtotime("{$t} + 45 seconds")),
            'properties'   => ['element_id' => 'btn-svc-1-contact', 'button_text' => 'Tư vấn ngay']
        ]);
    }

    // ================================================================
    // C. CAC TINH HUONG KIEM THU DAC BIET (TEST CASES)
    // ================================================================

    // 1. 10 Request Gui Lai Trung Lap Hoan Toan (Case A: Idempotent duplicate)
    // Gui lai 10 su kien dau tien cua Website A
    for ($i = 0; $i < 10; $i++) {
        $orig = $recordedEvents[$i];
        $processTrack($orig); // Gui lai dung event_id va dung payload
    }

    // 2. 5 Request Xung Dot (Case B: Cung ID nhung khac payload)
    // Lay 5 su kien va thay doi gia tri button_text hoac page_path
    for ($i = 10; $i < 15; $i++) {
        $conflictPayload = $recordedEvents[$i];
        $conflictPayload['properties'] = ['button_text' => 'MODIFIED_ATTACK_TEXT_' . rand(100, 999)];
        $processTrack($conflictPayload);
    }

    // 3. 5 Request ID Moi Cung Noi Dung (Case C: Tao event_id moi tinh nhung payload giong)
    for ($i = 15; $i < 20; $i++) {
        $newIdPayload = $recordedEvents[$i];
        $newIdPayload['event_id'] = 'evt_case_c_new_' . uniqid();
        $processTrack($newIdPayload);
    }

    // 4. 5 Request Su Kien Den Muon (Late events > 24 gio)
    $lateTime = date('Y-m-d H:i:s', strtotime('-48 hours'));
    for ($i = 1; $i <= 5; $i++) {
        $processTrack([
            'tracking_key' => $siteAKey,
            'event_id'     => "evt_late_{$i}",
            'session_id'   => "sess_late_{$i}",
            'event_name'   => 'page_view',
            'page_path'    => '/index.html',
            'occurred_at'  => $lateTime
        ]);
    }

    // 5. 5 Request Khoa Tracking Sai (Invalid key)
    for ($i = 1; $i <= 5; $i++) {
        $processTrack([
            'tracking_key' => 'trk_invalid_non_existent_key',
            'event_id'     => "evt_bad_key_{$i}",
            'session_id'   => "sess_bad_key_{$i}",
            'event_name'   => 'page_view',
            'page_path'    => '/index.html',
            'occurred_at'  => date('Y-m-d H:i:s')
        ]);
    }

    // 6. 5 Request Su Kien Khong Ton Tai / Bi Tat (Undefined event)
    for ($i = 1; $i <= 5; $i++) {
        $processTrack([
            'tracking_key' => $siteAKey,
            'event_id'     => "evt_undefined_{$i}",
            'session_id'   => "sess_undefined_{$i}",
            'event_name'   => 'malicious_injection_event',
            'page_path'    => '/index.html',
            'occurred_at'  => date('Y-m-d H:i:s')
        ]);
    }

    // 7. 5 Sessions Funnel Sai Thu Tu (10 events: download_complete xay ra TRUOC download_start)
    for ($i = 1; $i <= 5; $i++) {
        $sess = "sess_funnel_inverted_{$i}";
        $t = date('Y-m-d H:i:s', strtotime("{$today} 16:00:00 + {$i} minutes"));
        $tBefore = date('Y-m-d H:i:s', strtotime("{$t} - 30 seconds"));

        // download_complete xay ra truoc
        $processTrack([
            'tracking_key' => $siteAKey,
            'event_id'     => "evt_inv_comp_{$i}",
            'session_id'   => $sess,
            'event_name'   => 'download_complete',
            'page_path'    => '/download.html',
            'occurred_at'  => $tBefore
        ]);
        // download_start xay ra sau
        $processTrack([
            'tracking_key' => $siteAKey,
            'event_id'     => "evt_inv_start_{$i}",
            'session_id'   => $sess,
            'event_name'   => 'download_start',
            'page_path'    => '/download.html',
            'occurred_at'  => $t
        ]);
    }

    if (!$silent) {
        echo "====================================================\n";
        echo "   KET QUA SINH DU LIEU KIEM THU DETERMINISTIC (225 REQUESTS)\n";
        echo "====================================================\n";
        echo "Tong so request da gui:          " . $stats['total_requests'] . "\n";
        echo "So su kien moi duoc chap nhan:   " . $stats['accepted_new'] . "\n";
        echo " - Su kien thuoc Website A:      " . $stats['site_a_events'] . "\n";
        echo " - Su kien thuoc Website B:      " . $stats['site_b_events'] . "\n";
        echo "So request gui trung (Case A):    " . $stats['duplicate_case_a'] . "\n";
        echo "So request xung dot (Case B):     " . $stats['conflict_case_b'] . "\n";
        echo "So request den muon bi tu choi:   " . $stats['rejected_late'] . "\n";
        echo "So request su kien chua khai bao: " . $stats['rejected_undefined'] . "\n";
        echo "So request sai ma tracking key:   " . $stats['rejected_bad_key'] . "\n";
        echo "So phien hoan tat Funnel Site A:  " . $stats['funnel_completed_a'] . "\n";
        echo "So phien hoan tat Funnel Site B:  " . $stats['funnel_completed_b'] . "\n";
        echo "====================================================\n";
    }

    return $stats;
}

if (php_sapi_name() === 'cli' && isset($argv[0]) && realpath($argv[0]) === realpath(__FILE__)) {
    run_test_data_generator();
}
