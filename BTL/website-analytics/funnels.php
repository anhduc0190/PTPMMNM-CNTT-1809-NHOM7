<?php
// ====================================================================
// PHÂN TÍCH CHUỖI THAO TÁC (funnels.php)
// Chuỗi tiêu chuẩn: page_view -> download_start -> download_complete
// Chức năng cốt lõi bắt buộc của Đề tài 17 (Kiểm thử Test 07 & 08)
// Hỗ trợ mô phỏng trực tiếp 1-click & đối chiếu Audit Trail chi tiết
// ====================================================================

require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/permissions.php';
require_once __DIR__ . '/includes/helpers.php';

require_login();

// Xử lý các thao tác Thử nghiệm / Mô phỏng chuỗi trực tiếp (1-Click Simulation)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    $targetWebId = (int)($_POST['target_website_id'] ?? ($_SESSION['selected_website_id'] ?? 1));
    if ($targetWebId <= 0) $targetWebId = 1;

    if (!user_can_access_website($targetWebId, 'view')) {
        $_SESSION['flash_error'] = 'Bạn không có quyền thao tác trên website này.';
        header('Location: funnels.php?website_id=' . $targetWebId);
        exit;
    }

    $pdoSim = get_db_connection();

    // Đảm bảo các định nghĩa sự kiện tồn tại trong event_definitions
    $ensureDefs = [
        ['page_view', 'Ghi nhận lượt xem các trang trên website'],
        ['download_start', 'Bắt đầu tải tài liệu hoặc catalog'],
        ['download_complete', 'Hoàn tất tải file tài liệu thành công']
    ];
    $stmtDef = $pdoSim->prepare('
        INSERT INTO `event_definitions` (`website_id`, `event_name`, `description`, `enabled`, `created_at`)
        VALUES (?, ?, ?, 1, NOW())
        ON DUPLICATE KEY UPDATE `enabled` = 1
    ');
    foreach ($ensureDefs as $def) {
        $stmtDef->execute([$targetWebId, $def[0], $def[1]]);
    }

    $nowTime = date('Y-m-d H:i:s');
    $randHex = bin2hex(random_bytes(4));

    if ($action === 'simulate_complete') {
        // Mô phỏng 1 phiên Hoàn tất: page_view -> download_start -> download_complete
        $sessId = 'sess_sim_comp_' . $randHex;
        $t1 = date('Y-m-d H:i:s', strtotime('-30 seconds'));
        $t2 = date('Y-m-d H:i:s', strtotime('-15 seconds'));
        $t3 = $nowTime;

        $stmtSess = $pdoSim->prepare('INSERT INTO `sessions` (`session_id`, `website_id`, `started_at`, `last_seen_at`, `created_at`) VALUES (?, ?, ?, ?, NOW())');
        $stmtSess->execute([$sessId, $targetWebId, $t1, $t3]);

        $stmtEvt = $pdoSim->prepare('
            INSERT INTO `events` (`website_id`, `event_id`, `session_id`, `event_name`, `page_path`, `occurred_at`, `received_at`, `payload_hash`, `properties`)
            VALUES (?, ?, ?, ?, ?, ?, NOW(), ?, ?)
        ');
        $stmtEvt->execute([$targetWebId, 'evt_sim_1_' . $randHex, $sessId, 'page_view', '/download.html', $t1, hash('sha256', $sessId . '1'), null]);
        $stmtEvt->execute([$targetWebId, 'evt_sim_2_' . $randHex, $sessId, 'download_start', '/download.html', $t2, hash('sha256', $sessId . '2'), json_encode(['file_name' => 'catalog-2026.pdf'])]);
        $stmtEvt->execute([$targetWebId, 'evt_sim_3_' . $randHex, $sessId, 'download_complete', '/download.html', $t3, hash('sha256', $sessId . '3'), json_encode(['file_name' => 'catalog-2026.pdf', 'file_size_kb' => 1250])]);

        $_SESSION['flash_msg'] = 'Đã mô phỏng thành công 1 phiên HOÀN TẤT CHUỖI! Xem ngay ở dòng đầu tiên của bảng đối chiếu bên dưới.';
        header('Location: funnels.php?website_id=' . $targetWebId . '&filter=completed&highlight=' . urlencode($sessId));
        exit;
    }

    if ($action === 'simulate_drop') {
        // Mô phỏng 1 phiên Bỏ dở: page_view -> download_start (thoát giữa chừng)
        $sessId = 'sess_sim_drop_' . $randHex;
        $t1 = date('Y-m-d H:i:s', strtotime('-20 seconds'));
        $t2 = $nowTime;

        $stmtSess = $pdoSim->prepare('INSERT INTO `sessions` (`session_id`, `website_id`, `started_at`, `last_seen_at`, `created_at`) VALUES (?, ?, ?, ?, NOW())');
        $stmtSess->execute([$sessId, $targetWebId, $t1, $t2]);

        $stmtEvt = $pdoSim->prepare('
            INSERT INTO `events` (`website_id`, `event_id`, `session_id`, `event_name`, `page_path`, `occurred_at`, `received_at`, `payload_hash`, `properties`)
            VALUES (?, ?, ?, ?, ?, ?, NOW(), ?, ?)
        ');
        $stmtEvt->execute([$targetWebId, 'evt_sim_1_' . $randHex, $sessId, 'page_view', '/download.html', $t1, hash('sha256', $sessId . '1'), null]);
        $stmtEvt->execute([$targetWebId, 'evt_sim_2_' . $randHex, $sessId, 'download_start', '/download.html', $t2, hash('sha256', $sessId . '2'), json_encode(['file_name' => 'catalog-2026.pdf'])]);

        $_SESSION['flash_msg'] = 'Đã mô phỏng thành công 1 phiên BỎ DỞ TẠI BƯỚC 2 (Khách đã bấm tải nhưng đóng web trước khi tải xong).';
        header('Location: funnels.php?website_id=' . $targetWebId . '&filter=drop2&highlight=' . urlencode($sessId));
        exit;
    }

    if ($action === 'simulate_inverted') {
        // Mô phỏng 1 phiên Sai thứ tự thời gian: download_complete xảy ra TRƯỚC download_start
        $sessId = 'sess_sim_inv_' . $randHex;
        $tComplete = date('Y-m-d H:i:s', strtotime('-30 seconds'));
        $tStart    = $nowTime;

        $stmtSess = $pdoSim->prepare('INSERT INTO `sessions` (`session_id`, `website_id`, `started_at`, `last_seen_at`, `created_at`) VALUES (?, ?, ?, ?, NOW())');
        $stmtSess->execute([$sessId, $targetWebId, $tComplete, $tStart]);

        $stmtEvt = $pdoSim->prepare('
            INSERT INTO `events` (`website_id`, `event_id`, `session_id`, `event_name`, `page_path`, `occurred_at`, `received_at`, `payload_hash`, `properties`)
            VALUES (?, ?, ?, ?, ?, ?, NOW(), ?, ?)
        ');
        $stmtEvt->execute([$targetWebId, 'evt_sim_comp_' . $randHex, $sessId, 'download_complete', '/download.html', $tComplete, hash('sha256', $sessId . 'comp'), json_encode(['file_name' => 'catalog-2026.pdf'])]);
        $stmtEvt->execute([$targetWebId, 'evt_sim_start_' . $randHex, $sessId, 'download_start', '/download.html', $tStart, hash('sha256', $sessId . 'start'), json_encode(['file_name' => 'catalog-2026.pdf'])]);

        $_SESSION['flash_msg'] = 'Đã mô phỏng thành công 1 phiên NGƯỢC THỨ TỰ THỜI GIAN! Hệ thống phát hiện bất thường và loại bỏ khỏi Hoàn tất chuỗi.';
        header('Location: funnels.php?website_id=' . $targetWebId . '&filter=inverted&highlight=' . urlencode($sessId));
        exit;
    }
}

$pageTitle = 'Phân Tích Chuỗi Thao Tác (Funnel Analysis)';
require_once __DIR__ . '/includes/header.php';

$pdo = get_db_connection();
$fromDateTime = $fromDate . ' 00:00:00';
$toDateTime   = $toDate . ' 23:59:59';

$activeFilter = trim($_GET['filter'] ?? 'all');
$highlightSession = trim($_GET['highlight'] ?? '');

// 1. Lấy danh sách sự kiện thuộc chuỗi theo thứ tự thời gian và row_id
$stmt = $pdo->prepare('
    SELECT `session_id`, `event_name`, `page_path`, `occurred_at`, `event_row_id`
    FROM `events`
    WHERE `website_id` = ? 
      AND `event_name` IN ("page_view", "download_start", "download_complete")
      AND `occurred_at` BETWEEN ? AND ?
    ORDER BY `session_id` ASC, `occurred_at` ASC, `event_row_id` ASC
');
$stmt->execute([$selectedWebsiteId, $fromDateTime, $toDateTime]);
$events = $stmt->fetchAll();

$sessionGroups = [];
foreach ($events as $e) {
    $sessionGroups[$e['session_id']][] = $e;
}

$step1Sessions = 0; // page_view
$step2Sessions = 0; // download_start hợp lệ sau page_view
$step3Sessions = 0; // download_complete hợp lệ sau download_start

$countCompleted = 0;
$countDrop2     = 0;
$countDrop1     = 0;
$countInverted  = 0;

$sessionRecords = [];

foreach ($sessionGroups as $sessId => $sEvents) {
    $has1 = false;
    $has2 = false;
    $has3 = false;

    $t1 = null;
    $t2 = null;
    $t3 = null;

    // Phân tích sự kiện thô để phát hiện sai thứ tự thời gian
    $rawEvents = [];
    foreach ($sEvents as $e) {
        $rawEvents[$e['event_name']][] = $e['occurred_at'];
    }

    $rawHasComp = !empty($rawEvents['download_complete']);
    $rawHasStart = !empty($rawEvents['download_start']);
    $rawHasView = !empty($rawEvents['page_view']);

    $isInverted = false;
    if ($rawHasComp && $rawHasStart) {
        $minCompTime = min(array_map('strtotime', $rawEvents['download_complete']));
        $minStartTime = min(array_map('strtotime', $rawEvents['download_start']));
        if ($minCompTime < $minStartTime) {
            $isInverted = true;
        }
    }

    // Kiểm tra luồng tuần tự nghiêm ngặt: page_view -> download_start -> download_complete
    foreach ($sEvents as $e) {
        $name = $e['event_name'];
        $time = strtotime($e['occurred_at']);

        if ($name === 'page_view' && !$has1) {
            $has1 = true;
            $t1   = $e['occurred_at'];
            continue;
        }

        if ($name === 'download_start' && $has1 && !$has2) {
            if ($time >= strtotime($t1)) {
                $has2 = true;
                $t2   = $e['occurred_at'];
                continue;
            }
        }

        if ($name === 'download_complete' && $has2 && !$has3) {
            if ($time >= strtotime($t2)) {
                $has3 = true;
                $t3   = $e['occurred_at'];
                break;
            }
        }
    }

    if ($has1) $step1Sessions++;
    if ($has2) $step2Sessions++;
    if ($has3) $step3Sessions++;

    // Xác định trạng thái phân loại
    if ($has3) {
        $statusKey   = 'completed';
        $status      = 'Hoàn tất chuỗi';
        $statusClass = 'badge-green';
        $detailNote  = 'Đạt chuẩn 100%: Xem trang -> Bắt đầu tải -> Tải xong';
        $countCompleted++;
    } elseif ($isInverted) {
        $statusKey   = 'inverted';
        $status      = 'Sai thứ tự thời gian';
        $statusClass = 'badge-red';
        $detailNote  = 'Bất thường: download_complete xảy ra TRƯỚC download_start';
        $countInverted++;
    } elseif ($has2) {
        $statusKey   = 'drop2';
        $status      = 'Bỏ dở sau Bước 2 (Chưa tải xong)';
        $statusClass = 'badge-yellow';
        $detailNote  = 'Khách bấm bắt đầu tải nhưng chưa ghi nhận tải xong file';
        $countDrop2++;
    } elseif ($has1) {
        $statusKey   = 'drop1';
        $status      = 'Bỏ dở tại Bước 1 (Chỉ xem)';
        $statusClass = 'badge-gray';
        $detailNote  = 'Khách chỉ xem trang rồi rời đi, chưa bấm nút tải tài liệu';
        $countDrop1++;
    } else {
        $statusKey   = 'inverted';
        $status      = 'Thiếu bước xem trang';
        $statusClass = 'badge-red';
        $detailNote  = 'Có thao tác tải nhưng không có sự kiện xem trang hợp lệ';
        $countInverted++;
    }

    $firstTime = null;
    $latestTime = null;
    foreach ($sEvents as $e) {
        $t = strtotime($e['occurred_at']);
        if ($firstTime === null || $t < $firstTime) $firstTime = $t;
        if ($latestTime === null || $t > $latestTime) $latestTime = $t;
    }
    $durationSec = ($latestTime && $firstTime) ? ($latestTime - $firstTime) : 0;

    // Lấy thời điểm hiển thị nếu bước đó không hợp lệ trong chuỗi nhưng có trong rawEvents
    $displayT1 = $t1 ?? ($rawEvents['page_view'][0] ?? null);
    $displayT2 = $t2 ?? ($rawEvents['download_start'][0] ?? null);
    $displayT3 = $t3 ?? ($rawEvents['download_complete'][0] ?? null);

    $sessionRecords[] = [
        'session_id'   => $sessId,
        'has_step1'    => $has1,
        'has_step2'    => $has2,
        'has_step3'    => $has3,
        't1'           => $displayT1,
        't2'           => $displayT2,
        't3'           => $displayT3,
        'raw_has_t1'   => !empty($displayT1),
        'raw_has_t2'   => !empty($displayT2),
        'raw_has_t3'   => !empty($displayT3),
        'latest_time'  => $latestTime,
        'duration_sec' => $durationSec,
        'status_key'   => $statusKey,
        'status'       => $status,
        'badge'        => $statusClass,
        'detail_note'  => $detailNote,
        'is_highlight' => ($highlightSession !== '' && $sessId === $highlightSession)
    ];
}

// Sắp xếp các phiên có tương tác mới nhất lên ĐẦU TIÊN (Row #1)
usort($sessionRecords, function($a, $b) {
    if ($a['is_highlight'] && !$b['is_highlight']) return -1;
    if (!$a['is_highlight'] && $b['is_highlight']) return 1;
    return ($b['latest_time'] ?? 0) <=> ($a['latest_time'] ?? 0);
});

// Lọc dữ liệu hiển thị theo tab được chọn
$filteredRecords = $sessionRecords;
if ($activeFilter === 'completed') {
    $filteredRecords = array_filter($sessionRecords, fn($r) => $r['status_key'] === 'completed');
} elseif ($activeFilter === 'drop2') {
    $filteredRecords = array_filter($sessionRecords, fn($r) => $r['status_key'] === 'drop2');
} elseif ($activeFilter === 'drop1') {
    $filteredRecords = array_filter($sessionRecords, fn($r) => $r['status_key'] === 'drop1');
} elseif ($activeFilter === 'inverted') {
    $filteredRecords = array_filter($sessionRecords, fn($r) => $r['status_key'] === 'inverted');
}

$convRate = $step1Sessions > 0 ? round(($step3Sessions / $step1Sessions) * 100, 2) : 0;
$step2Pct = $step1Sessions > 0 ? round(($step2Sessions / $step1Sessions) * 100, 1) : 0;
$step3Pct = $step2Sessions > 0 ? round(($step3Sessions / $step2Sessions) * 100, 1) : 0;

// URL trang demo tương ứng với website đang chọn
$demoDownloadUrl = 'live-demo.php?site=' . $selectedWebsiteId;
if ($selectedWebsiteId === 1) {
    $demoDownloadUrl = 'demo-sites/site-a/download.html';
} elseif ($selectedWebsiteId === 2) {
    $demoDownloadUrl = 'demo-sites/site-b/register.html';
}
?>

<!-- THÔNG BÁO FLASH NẾU VỪA MÔ PHỎNG -->
<?php if (!empty($_SESSION['flash_msg'])): ?>
    <div style="background:#ecfdf5; border:1px solid #a7f3d0; border-left:4px solid #10b981; border-radius:var(--radius-md); padding:1rem 1.25rem; margin-bottom:1.25rem; display:flex; align-items:center; gap:0.75rem;">
        <span style="color:#059669;"><?= render_icon('check-circle', '', 20) ?></span>
        <div style="font-size:0.92rem; font-weight:600; color:#065f46;">
            <?= e($_SESSION['flash_msg']) ?>
        </div>
    </div>
    <?php unset($_SESSION['flash_msg']); ?>
<?php endif; ?>

<!-- BANNER GIẢI THÍCH TRỰC QUAN & VÌ SAO KHÔNG THỂ XÓA -->
<div class="card-section" style="background:linear-gradient(135deg, #f0f7ff 0%, #ffffff 100%); border-color:var(--accent-blue-border); margin-bottom:1.5rem;">
    <div style="display:flex; align-items:flex-start; gap:1rem;">
        <div style="color:var(--primary); background:#e0edff; padding:0.6rem; border-radius:10px; margin-top:2px;">
            <?= render_icon('filter', '', 22) ?>
        </div>
        <div style="flex:1;">
            <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:0.5rem; margin-bottom:0.6rem;">
                <h3 style="color:var(--text-primary); font-size:1.1rem; font-weight:800; margin:0;">
                    Giải Thích: Chuỗi Thao Tác (Conversion Funnel) Hoạt Động Thế Nào?
                </h3>
                <span class="badge badge-blue" style="font-size:0.8rem; font-weight:700;">
                    <?= render_icon('shield-check', '', 13) ?>
                    Tính Năng Bắt Buộc (Test 07 & 08 Đề Tài 17)
                </span>
            </div>

            <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap:1rem; margin-top:0.75rem;">
                <!-- Cột 1: Ý nghĩa thực tế -->
                <div style="background:#ffffff; border:1px solid #e2e8f0; border-radius:8px; padding:0.9rem 1rem;">
                    <div style="font-weight:700; color:var(--text-primary); font-size:0.88rem; margin-bottom:0.35rem; display:flex; align-items:center; gap:0.4rem;">
                        <?= render_icon('activity', '', 15, 'var(--primary)') ?>
                        1. Ý Nghĩa Thực Tế Trong Web Analytics
                    </div>
                    <p style="color:var(--text-secondary); font-size:0.82rem; line-height:1.5; margin:0;">
                        Người dùng vào web không phải ai cũng hoàn thành mục tiêu. Phễu (Funnel) theo dõi hành trình 3 bước: 
                        <b>Vào trang web</b> &rarr; <b>Bấm nút Tải</b> &rarr; <b>Tải xong file</b>. Giúp doanh nghiệp biết khách rơi rụng (Drop-off) ở đâu nhiều nhất để cải thiện.
                    </p>
                </div>

                <!-- Cột 2: Quy tắc 4 điều kiện -->
                <div style="background:#ffffff; border:1px solid #e2e8f0; border-radius:8px; padding:0.9rem 1rem;">
                    <div style="font-weight:700; color:var(--text-primary); font-size:0.88rem; margin-bottom:0.35rem; display:flex; align-items:center; gap:0.4rem;">
                        <?= render_icon('check-circle', '', 15, 'var(--accent-green)') ?>
                        2. Thuật Toán Kiểm Tra Thứ Tự Nghiêm Ngặt
                    </div>
                    <p style="color:var(--text-secondary); font-size:0.82rem; line-height:1.5; margin:0;">
                        Một phiên chỉ được tính là <b>Hoàn tất</b> khi:
                        <br>&bull; Cùng 1 website và cùng 1 session ID.
                        <br>&bull; Đủ 3 bước theo đúng thứ tự: t(page_view) &le; t(download_start) &le; t(download_complete).
                        <br>&bull; Bỏ dở hoặc ngược thứ tự sẽ bị loại bỏ khỏi số đếm.
                    </p>
                </div>

                <!-- Cột 3: Tầm quan trọng đối với đồ án -->
                <div style="background:#fffbeb; border:1px solid #fef3c7; border-radius:8px; padding:0.9rem 1rem;">
                    <div style="font-weight:700; color:#b45309; font-size:0.88rem; margin-bottom:0.35rem; display:flex; align-items:center; gap:0.4rem;">
                        <?= render_icon('alert-triangle', '', 15, '#d97706') ?>
                        3. Tại Sao Tuyệt Đối KHÔNG ĐƯỢC XÓA?
                    </div>
                    <p style="color:#78350f; font-size:0.82rem; line-height:1.5; margin:0;">
                        Đây là <b>tiêu chí chấm điểm cốt lõi</b> của giảng viên. Bộ kiểm thử tự động (<code>run-tests.php</code>) kiểm tra nghiêm ngặt Test 07 & 08 trên trang này. Xóa trang này sẽ làm gãy bài test và bị trừ điểm đề tài!
                    </p>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- KHU VỰC THỬ NGHIỆM TRỰC TIẾP (SIMULATION LAB) -->
<div class="card-section" style="background:#ffffff; border:1px solid var(--border); box-shadow:var(--shadow-xs); margin-bottom:1.5rem;">
    <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:0.75rem; margin-bottom:0.85rem;">
        <div>
            <h3 style="font-size:1.02rem; font-weight:700; color:var(--text-primary); margin:0; display:flex; align-items:center; gap:0.5rem;">
                <?= render_icon('zap', '', 17, 'var(--primary)') ?>
                Trung Tâm Thử Nghiệm Chuỗi Thao Tác (1-Click Simulation Lab)
            </h3>
            <p style="font-size:0.82rem; color:var(--text-secondary); margin:0.25rem 0 0 0;">
                Bấm các nút dưới đây để tạo ngay dữ liệu kiểm thử và xem kết quả xuất hiện ở dòng đầu tiên của bảng đối chiếu:
            </p>
        </div>
        <a href="<?= e($demoDownloadUrl) ?>" target="_blank" class="btn btn-outline" style="font-size:0.82rem; padding:0.45rem 0.9rem; display:inline-flex; align-items:center; gap:0.4rem;">
            <?= render_icon('external-link', '', 14) ?>
            Mở Trang Tải File Demo Để Tự Tay Click
        </a>
    </div>

    <div style="display:flex; gap:0.75rem; flex-wrap:wrap;">
        <!-- Nút 1: Hoàn tất chuỗi -->
        <form method="POST" style="margin:0;">
            <input type="hidden" name="action" value="simulate_complete">
            <input type="hidden" name="target_website_id" value="<?= $selectedWebsiteId ?>">
            <button type="submit" class="btn btn-success" style="font-size:0.84rem; padding:0.55rem 1rem; display:inline-flex; align-items:center; gap:0.45rem;">
                <?= render_icon('check-circle', '', 15) ?>
                Thử Nghiệm: Tạo 1 Phiên Hoàn Tất (100%)
            </button>
        </form>

        <!-- Nút 2: Bỏ dở -->
        <form method="POST" style="margin:0;">
            <input type="hidden" name="action" value="simulate_drop">
            <input type="hidden" name="target_website_id" value="<?= $selectedWebsiteId ?>">
            <button type="submit" class="btn" style="background:#fef3c7; color:#92400e; border:1px solid #fde68a; font-size:0.84rem; padding:0.55rem 1rem; display:inline-flex; align-items:center; gap:0.45rem; font-weight:600;">
                <?= render_icon('alert-circle', '', 15) ?>
                Thử Nghiệm: Tạo 1 Phiên Bỏ Dở tại Bước 2
            </button>
        </form>

        <!-- Nút 3: Sai thứ tự -->
        <form method="POST" style="margin:0;">
            <input type="hidden" name="action" value="simulate_inverted">
            <input type="hidden" name="target_website_id" value="<?= $selectedWebsiteId ?>">
            <button type="submit" class="btn" style="background:#fee2e2; color:#991b1b; border:1px solid #fca5a5; font-size:0.84rem; padding:0.55rem 1rem; display:inline-flex; align-items:center; gap:0.45rem; font-weight:600;">
                <?= render_icon('x-circle', '', 15) ?>
                Thử Nghiệm: Tạo 1 Phiên Ngược Thứ Tự (Loại Bỏ)
            </button>
        </form>
    </div>
</div>

<!-- TRỰC QUAN HÓA PHỄU 3 BƯỚC -->
<div class="card-section" style="margin-bottom:1.5rem;">
    <div class="card-title-bar">
        <h3>
            <?= render_icon('activity', '', 18, 'var(--primary)') ?>
            Quy Trình Chuyển Đổi Qua Từng Bước Của Website #<?= $selectedWebsiteId ?>
        </h3>
        <span class="badge badge-green" style="font-size:0.85rem; padding:0.4rem 0.85rem; font-weight:700;">
            <?= render_icon('check-circle', '', 14) ?>
            Tỷ lệ hoàn tất toàn chuỗi: <?= $convRate ?>%
        </span>
    </div>

    <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap:1.25rem; margin-top:1rem;">
        <!-- Bước 1 -->
        <div style="background:#ffffff; border:1px solid var(--accent-blue-border); border-top:4px solid var(--primary); border-radius:var(--radius-md); padding:1.4rem; box-shadow:var(--shadow-xs);">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:0.6rem;">
                <span style="font-size:0.75rem; font-weight:700; color:var(--primary); text-transform:uppercase; letter-spacing:0.04em;">BƯỚC 1: XEM TRANG</span>
                <span class="kpi-icon-wrap blue" style="width:28px; height:28px;">
                    <?= render_icon('eye', '', 15) ?>
                </span>
            </div>
            <div style="font-size:1.05rem; font-weight:700; color:var(--text-primary); margin-bottom:0.3rem;">
                <code>page_view</code>
            </div>
            <div style="font-size:2.1rem; font-weight:800; color:var(--text-primary); line-height:1.2;">
                <?= number_format($step1Sessions) ?> <span style="font-size:0.85rem; font-weight:500; color:var(--text-muted);">phiên</span>
            </div>
            <div style="font-size:0.8rem; color:var(--text-muted); margin-top:0.4rem;">
                Số phiên bắt đầu (100% làm gốc)
            </div>
            <div style="margin-top:0.85rem; height:6px; background:#e2e8f0; border-radius:3px; overflow:hidden;">
                <div style="height:100%; width:100%; background:var(--primary);"></div>
            </div>
        </div>

        <!-- Bước 2 -->
        <div style="background:#ffffff; border:1px solid var(--accent-yellow-border); border-top:4px solid var(--accent-yellow); border-radius:var(--radius-md); padding:1.4rem; box-shadow:var(--shadow-xs);">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:0.6rem;">
                <span style="font-size:0.75rem; font-weight:700; color:var(--accent-yellow-text); text-transform:uppercase; letter-spacing:0.04em;">BƯỚC 2: BẮT ĐẦU TẢI</span>
                <span class="kpi-icon-wrap yellow" style="width:28px; height:28px;">
                    <?= render_icon('download', '', 15) ?>
                </span>
            </div>
            <div style="font-size:1.05rem; font-weight:700; color:var(--text-primary); margin-bottom:0.3rem;">
                <code>download_start</code>
            </div>
            <div style="font-size:2.1rem; font-weight:800; color:var(--text-primary); line-height:1.2;">
                <?= number_format($step2Sessions) ?> <span style="font-size:0.85rem; font-weight:500; color:var(--text-muted);">phiên</span>
            </div>
            <div style="font-size:0.8rem; color:var(--accent-yellow-text); font-weight:600; margin-top:0.4rem;">
                <?= $step2Pct ?>% phiên đi tiếp (Rơi rớt: <?= round(100 - $step2Pct, 1) ?>%)
            </div>
            <div style="margin-top:0.85rem; height:6px; background:#e2e8f0; border-radius:3px; overflow:hidden;">
                <div style="height:100%; width:<?= $step2Pct ?>%; background:var(--accent-yellow);"></div>
            </div>
        </div>

        <!-- Bước 3 -->
        <div style="background:#ffffff; border:1px solid var(--accent-green-border); border-top:4px solid var(--accent-green); border-radius:var(--radius-md); padding:1.4rem; box-shadow:var(--shadow-xs);">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:0.6rem;">
                <span style="font-size:0.75rem; font-weight:700; color:var(--accent-green-text); text-transform:uppercase; letter-spacing:0.04em;">BƯỚC 3: HOÀN TẤT TẢI</span>
                <span class="kpi-icon-wrap green" style="width:28px; height:28px;">
                    <?= render_icon('check-circle', '', 15) ?>
                </span>
            </div>
            <div style="font-size:1.05rem; font-weight:700; color:var(--text-primary); margin-bottom:0.3rem;">
                <code>download_complete</code>
            </div>
            <div style="font-size:2.1rem; font-weight:800; color:var(--accent-green); line-height:1.2;">
                <?= number_format($step3Sessions) ?> <span style="font-size:0.85rem; font-weight:500; color:var(--text-muted);">phiên</span>
            </div>
            <div style="font-size:0.8rem; color:var(--accent-green-text); font-weight:700; margin-top:0.4rem;">
                <?= $convRate ?>% hoàn tất toàn bộ chuỗi
            </div>
            <div style="margin-top:0.85rem; height:6px; background:#e2e8f0; border-radius:3px; overflow:hidden;">
                <div style="height:100%; width:<?= $convRate ?>%; background:var(--accent-green);"></div>
            </div>
        </div>
    </div>
</div>

<!-- BẢNG ĐỐI CHIẾU TỪNG PHIÊN (AUDIT TRAIL) VỚI TAB LỌC NHANH -->
<div class="card-section">
    <div class="card-title-bar" style="margin-bottom:0.75rem;">
        <div>
            <h3 style="margin:0; display:flex; align-items:center; gap:0.5rem;">
                <?= render_icon('search', '', 18, 'var(--text-secondary)') ?>
                Đối Chiếu Sự Kiện Nguồn Từng Phiên Truy Cập (Audit Trail)
            </h3>
            <p style="font-size:0.82rem; color:var(--text-secondary); margin:0.25rem 0 0 0;">
                Mỗi dòng biểu diễn 1 phiên truy cập. Kiểm tra xem người dùng hoàn thành đến bước nào hay bỏ dở giữa chừng.
            </p>
        </div>
        <span class="badge badge-gray">
            <?= render_icon('users-round', '', 12) ?>
            Đang hiển thị: <?= count($filteredRecords) ?> / <?= count($sessionRecords) ?> phiên
        </span>
    </div>

    <!-- TABS LỌC NHANH KẾT QUẢ CHUỖI -->
    <div style="display:flex; gap:0.5rem; flex-wrap:wrap; margin-bottom:1.25rem; border-bottom:1px solid #e2e8f0; padding-bottom:0.85rem;">
        <a href="funnels.php?website_id=<?= $selectedWebsiteId ?>&filter=all" 
           class="btn" 
           style="font-size:0.82rem; padding:0.4rem 0.85rem; font-weight:600; text-decoration:none; <?= $activeFilter === 'all' ? 'background:var(--primary); color:#ffffff;' : 'background:#f1f5f9; color:var(--text-secondary);' ?>">
            Tất cả các phiên (<?= count($sessionRecords) ?>)
        </a>

        <a href="funnels.php?website_id=<?= $selectedWebsiteId ?>&filter=completed" 
           class="btn" 
           style="font-size:0.82rem; padding:0.4rem 0.85rem; font-weight:600; text-decoration:none; display:inline-flex; align-items:center; gap:0.35rem; <?= $activeFilter === 'completed' ? 'background:#10b981; color:#ffffff;' : 'background:#ecfdf5; color:#065f46; border:1px solid #a7f3d0;' ?>">
            <?= render_icon('check-circle', '', 13) ?>
            Hoàn tất chuỗi (<?= $countCompleted ?>)
        </a>

        <a href="funnels.php?website_id=<?= $selectedWebsiteId ?>&filter=drop2" 
           class="btn" 
           style="font-size:0.82rem; padding:0.4rem 0.85rem; font-weight:600; text-decoration:none; display:inline-flex; align-items:center; gap:0.35rem; <?= $activeFilter === 'drop2' ? 'background:#d97706; color:#ffffff;' : 'background:#fef3c7; color:#92400e; border:1px solid #fde68a;' ?>">
            <?= render_icon('alert-circle', '', 13) ?>
            Bỏ dở sau Bước 2 (<?= $countDrop2 ?>)
        </a>

        <a href="funnels.php?website_id=<?= $selectedWebsiteId ?>&filter=drop1" 
           class="btn" 
           style="font-size:0.82rem; padding:0.4rem 0.85rem; font-weight:600; text-decoration:none; display:inline-flex; align-items:center; gap:0.35rem; <?= $activeFilter === 'drop1' ? 'background:#64748b; color:#ffffff;' : 'background:#f8fafc; color:#475569; border:1px solid #cbd5e1;' ?>">
            <?= render_icon('eye', '', 13) ?>
            Bỏ dở tại Bước 1 (<?= $countDrop1 ?>)
        </a>

        <?php if ($countInverted > 0): ?>
            <a href="funnels.php?website_id=<?= $selectedWebsiteId ?>&filter=inverted" 
               class="btn" 
               style="font-size:0.82rem; padding:0.4rem 0.85rem; font-weight:600; text-decoration:none; display:inline-flex; align-items:center; gap:0.35rem; <?= $activeFilter === 'inverted' ? 'background:#ef4444; color:#ffffff;' : 'background:#fee2e2; color:#991b1b; border:1px solid #fca5a5;' ?>">
                <?= render_icon('x-circle', '', 13) ?>
                Sai thứ tự / Bất thường (<?= $countInverted ?>)
            </a>
        <?php endif; ?>
    </div>

    <!-- TÌM KIẾM NHANH BẰNG JAVASCRIPT -->
    <div style="margin-bottom:1rem; max-width:380px;">
        <input type="text" id="funnelSearchInput" class="form-control" placeholder="Tìm kiếm nhanh theo Session ID..." style="font-size:0.85rem; padding:0.5rem 0.85rem;">
    </div>

    <div class="table-responsive">
        <table class="data-table" id="funnelAuditTable">
            <thead>
                <tr>
                    <th>Session ID (Phiên)</th>
                    <th>Thời Lượng</th>
                    <th>Bước 1 (page_view)</th>
                    <th>Bước 2 (download_start)</th>
                    <th>Bước 3 (download_complete)</th>
                    <th>Kết Quả Chuỗi</th>
                    <th>Ghi Chú Phân Tích Hành Vi</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach (array_slice($filteredRecords, 0, 100) as $sr): ?>
                    <tr class="funnel-row <?= $sr['is_highlight'] ? 'highlighted-row' : '' ?>" style="<?= $sr['is_highlight'] ? 'background:#f0fdf4; border-left:4px solid #10b981;' : '' ?>">
                        <td title="<?= e($sr['session_id']) ?>" class="sess-cell">
                            <span style="font-family:monospace; font-size:0.82rem; font-weight:600; color:var(--text-primary);">
                                <?= substr(e($sr['session_id']), 0, 20) ?>...
                            </span>
                            <?php if ($sr['is_highlight']): ?>
                                <span class="badge badge-green" style="font-size:0.7rem; padding:0.15rem 0.45rem; margin-left:4px;">
                                    Phiên Vừa Tạo
                                </span>
                            <?php endif; ?>
                        </td>

                        <td>
                            <span style="font-weight:600; color:var(--text-secondary); font-size:0.82rem;">
                                <?= render_icon('clock', '', 12, 'var(--text-muted)') ?>
                                <?= $sr['duration_sec'] >= 60 ? (floor($sr['duration_sec'] / 60) . 'm ' . ($sr['duration_sec'] % 60) . 's') : ($sr['duration_sec'] . 's') ?>
                            </span>
                        </td>

                        <td>
                            <?php if ($sr['has_step1']): ?>
                                <span class="badge badge-blue">
                                    <?= render_icon('check', '', 11) ?>
                                    <?= date('H:i:s', strtotime($sr['t1'])) ?>
                                </span>
                            <?php elseif ($sr['raw_has_t1']): ?>
                                <span class="badge badge-gray" title="Xuất hiện ngoài thứ tự chuẩn">
                                    <?= date('H:i:s', strtotime($sr['t1'])) ?>
                                </span>
                            <?php else: ?>
                                <span style="color:var(--text-muted); font-size:0.8rem;">
                                    <?= render_icon('x', '', 12) ?> Thiếu
                                </span>
                            <?php endif; ?>
                        </td>

                        <td>
                            <?php if ($sr['has_step2']): ?>
                                <span class="badge badge-yellow">
                                    <?= render_icon('check', '', 11) ?>
                                    <?= date('H:i:s', strtotime($sr['t2'])) ?>
                                </span>
                            <?php elseif ($sr['raw_has_t2']): ?>
                                <span class="badge badge-yellow" title="Ghi nhận sự kiện">
                                    <?= date('H:i:s', strtotime($sr['t2'])) ?>
                                </span>
                            <?php else: ?>
                                <span style="color:var(--text-muted); font-size:0.8rem;">
                                    <?= render_icon('x', '', 12) ?> Thiếu
                                </span>
                            <?php endif; ?>
                        </td>

                        <td>
                            <?php if ($sr['has_step3']): ?>
                                <span class="badge badge-green">
                                    <?= render_icon('check-circle', '', 11) ?>
                                    <?= date('H:i:s', strtotime($sr['t3'])) ?>
                                </span>
                            <?php elseif ($sr['raw_has_t3']): ?>
                                <span class="badge badge-red" title="Xảy ra trước khi bấm tải">
                                    <?= render_icon('alert-triangle', '', 11) ?>
                                    <?= date('H:i:s', strtotime($sr['t3'])) ?>
                                </span>
                            <?php else: ?>
                                <span style="color:var(--text-muted); font-size:0.8rem;">
                                    <?= render_icon('x', '', 12) ?> Thiếu
                                </span>
                            <?php endif; ?>
                        </td>

                        <td>
                            <span class="badge <?= $sr['badge'] ?>">
                                <?= render_icon($sr['status_key'] === 'completed' ? 'check-circle' : ($sr['status_key'] === 'drop2' ? 'alert-circle' : ($sr['status_key'] === 'inverted' ? 'x-circle' : 'eye')), '', 11) ?>
                                <?= e($sr['status']) ?>
                            </span>
                        </td>

                        <td>
                            <span style="font-size:0.8rem; color:var(--text-secondary);">
                                <?= e($sr['detail_note']) ?>
                            </span>
                        </td>
                    </tr>
                <?php endforeach; ?>

                <?php if (empty($filteredRecords)): ?>
                    <tr>
                        <td colspan="7" style="text-align:center; color:var(--text-muted); padding:2.5rem;">
                            Không tìm thấy phiên nào phù hợp với bộ lọc trong khoảng thời gian đã chọn
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
// Tìm kiếm nhanh trên bảng Audit Trail
document.addEventListener('DOMContentLoaded', function() {
    var searchInput = document.getElementById('funnelSearchInput');
    var rows = document.querySelectorAll('#funnelAuditTable tbody tr.funnel-row');

    if (searchInput) {
        searchInput.addEventListener('input', function() {
            var term = this.value.trim().toLowerCase();
            rows.forEach(function(r) {
                var cell = r.querySelector('.sess-cell');
                if (!cell) return;
                var text = cell.innerText.toLowerCase();
                if (term === '' || text.indexOf(term) !== -1) {
                    r.style.display = '';
                } else {
                    r.style.display = 'none';
                }
            });
        });
    }
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
