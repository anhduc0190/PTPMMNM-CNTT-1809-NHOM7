<?php
// ====================================================================
// INCLUDES: HEADER CHUNG (includes/header.php)
// Thiết kế chuẩn xác theo Mockup Reference:
// - Header Greeting: "Xin chào, Admin!" + Subtitle
// - Action Pills: Mở Demo Site A, Mở Demo Site B, Date Range Pill
// - Sub-header Filter: Website Select, Nút Lọc dữ liệu (Xanh dương), Nút Xuất CSV (Xanh lá)
// ====================================================================

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/permissions.php';
require_once __DIR__ . '/../includes/helpers.php';

require_login();

$currentUser = get_auth_user();
$accessibleWebsites = get_accessible_websites();
$baseUrl = get_base_url();

$selectedWebsiteId = (int)($_GET['website_id'] ?? ($_SESSION['selected_website_id'] ?? 0));
if ($selectedWebsiteId <= 0 && !empty($accessibleWebsites)) {
    $selectedWebsiteId = (int)$accessibleWebsites[0]['website_id'];
}
$_SESSION['selected_website_id'] = $selectedWebsiteId;

if (!headers_sent()) {
    header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
    header("Cache-Control: post-check=0, pre-check=0", false);
    header("Pragma: no-cache");
    header("Expires: 0");
}

// Xử lý khoảng ngày thông minh & Presets
$todayDate     = date('Y-m-d');
$yesterdayDate = date('Y-m-d', strtotime('-1 day'));

// Tìm ngày sớm nhất có sự kiện để hỗ trợ preset "Toàn bộ thời gian"
$allTimeStartDate = date('Y-m-d', strtotime('-30 days'));
try {
    $pdoTmp = get_db_connection();
    $minRow = $pdoTmp->query("SELECT MIN(DATE(occurred_at)) AS min_d FROM events")->fetch();
    if ($minRow && !empty($minRow['min_d'])) {
        $allTimeStartDate = $minRow['min_d'];
    }
} catch (Exception $e) {}

$datePreset = $_GET['date_preset'] ?? '';

if ($datePreset === 'today') {
    $fromDate = $todayDate;
    $toDate   = $todayDate;
} elseif ($datePreset === 'yesterday') {
    $fromDate = $yesterdayDate;
    $toDate   = $yesterdayDate;
} elseif ($datePreset === '7_days') {
    $fromDate = date('Y-m-d', strtotime('-6 days'));
    $toDate   = $todayDate;
} elseif ($datePreset === '14_days') {
    $fromDate = date('Y-m-d', strtotime('-13 days'));
    $toDate   = $todayDate;
} elseif ($datePreset === '30_days') {
    $fromDate = date('Y-m-d', strtotime('-29 days'));
    $toDate   = $todayDate;
} elseif ($datePreset === 'all') {
    $fromDate = $allTimeStartDate;
    $toDate   = $todayDate;
} else {
    $fromDate = $_GET['from_date'] ?? date('Y-m-d', strtotime('-13 days'));
    $toDate   = $_GET['to_date'] ?? $todayDate;
}

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fromDate)) {
    $fromDate = date('Y-m-d', strtotime('-13 days'));
}
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $toDate)) {
    $toDate = $todayDate;
}

if (strtotime($fromDate) > strtotime($toDate)) {
    $tmp = $fromDate;
    $fromDate = $toDate;
    $toDate = $tmp;
}

if (empty($datePreset)) {
    if ($fromDate === $todayDate && $toDate === $todayDate) {
        $datePreset = 'today';
    } elseif ($fromDate === $yesterdayDate && $toDate === $yesterdayDate) {
        $datePreset = 'yesterday';
    } elseif ($fromDate === date('Y-m-d', strtotime('-6 days')) && $toDate === $todayDate) {
        $datePreset = '7_days';
    } elseif ($fromDate === date('Y-m-d', strtotime('-13 days')) && $toDate === $todayDate) {
        $datePreset = '14_days';
    } elseif ($fromDate === date('Y-m-d', strtotime('-29 days')) && $toDate === $todayDate) {
        $datePreset = '30_days';
    } elseif ($fromDate === $allTimeStartDate && $toDate === $todayDate) {
        $datePreset = 'all';
    } else {
        $datePreset = 'custom';
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($pageTitle ?? 'Dashboard') ?> — <?= APP_NAME ?></title>
    <link rel="stylesheet" href="<?= $baseUrl ?>/assets/css/dashboard.css?v=<?= file_exists(__DIR__ . '/../assets/css/dashboard.css') ? filemtime(__DIR__ . '/../assets/css/dashboard.css') : time() ?>_<?= time() ?>">
    <style>
<?php
$cssInlineFile = __DIR__ . '/../assets/css/dashboard.css';
if (file_exists($cssInlineFile)) {
    readfile($cssInlineFile);
}
?>
    </style>
    <!-- Chart.js CDN -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
</head>
<body>
<div class="app-layout">
    <div id="sidebar-overlay" class="sidebar-overlay"></div>
    <?php require_once __DIR__ . '/sidebar.php'; ?>
    <div class="main-wrapper">
        <!-- Top Header Bar -->
        <header class="top-header">
            <div class="top-header-left">
                <button type="button" class="mobile-menu-toggle" id="sidebar-toggle" aria-label="Mở menu điều hướng">
                    <?= render_icon('menu', '', 18) ?>
                </button>
                <div>
                    <h1 class="greeting-title">
                        Xin chào, <?= e($currentUser['full_name'] ?? 'Admin') ?>!
                    </h1>
                    <div class="greeting-sub">
                        Tổng quan hoạt động của các website được theo dõi
                    </div>
                </div>
            </div>

            <div class="top-header-actions">
                <a href="<?= $baseUrl ?>/demo-sites/site-a/index.html" target="_blank" class="pill-btn-site-a" title="Mở trang demo Thương Mại Điện Tử (Site A)">
                    <?= render_icon('store', '', 14) ?>
                    <span>Mở Demo Site A</span>
                </a>
                <a href="<?= $baseUrl ?>/demo-sites/site-b/index.html" target="_blank" class="pill-btn-site-b" title="Mở trang demo Tin Tức & Khóa Học (Site B)">
                    <?= render_icon('rocket', '', 14) ?>
                    <span>Mở Demo Site B</span>
                </a>

                <!-- Date Range Dropdown Popover Pill -->
                <div class="date-popover-wrapper">
                    <button type="button" class="pill-date-range" id="pill-date-range-btn" title="Bấm để chọn nhanh hoặc tùy chỉnh khoảng thời gian">
                        <?= render_icon('calendar', '', 14, 'var(--primary)') ?>
                        <span id="pill-date-range-text"><?= date('d/m/Y', strtotime($fromDate)) ?> &nbsp;&rarr;&nbsp; <?= date('d/m/Y', strtotime($toDate)) ?></span>
                        <?= render_icon('chevron-down', '', 14, 'var(--text-muted)') ?>
                    </button>

                    <!-- Popover Menu -->
                    <div class="date-popover-menu" id="date-popover-menu">
                        <div class="popover-section-title">Chọn nhanh khoảng thời gian</div>
                        <div class="popover-presets-grid">
                            <button type="button" class="popover-preset-btn <?= $datePreset === 'today' ? 'active' : '' ?>" data-preset="today">Hôm nay</button>
                            <button type="button" class="popover-preset-btn <?= $datePreset === 'yesterday' ? 'active' : '' ?>" data-preset="yesterday">Hôm qua</button>
                            <button type="button" class="popover-preset-btn <?= $datePreset === '7_days' ? 'active' : '' ?>" data-preset="7_days">7 ngày qua</button>
                            <button type="button" class="popover-preset-btn <?= $datePreset === '14_days' ? 'active' : '' ?>" data-preset="14_days">14 ngày</button>
                            <button type="button" class="popover-preset-btn <?= $datePreset === '30_days' ? 'active' : '' ?>" data-preset="30_days">30 ngày</button>
                            <button type="button" class="popover-preset-btn <?= $datePreset === 'all' ? 'active' : '' ?>" data-preset="all">Toàn bộ</button>
                        </div>
                        <div class="popover-divider"></div>
                        <div class="popover-section-title">Tùy chọn khoảng ngày</div>
                        <div class="popover-custom-inputs">
                            <div class="popover-field">
                                <label>Từ ngày</label>
                                <input type="date" id="popover-from-date" value="<?= e($fromDate) ?>">
                            </div>
                            <div class="popover-field">
                                <label>Đến ngày</label>
                                <input type="date" id="popover-to-date" value="<?= e($toDate) ?>">
                            </div>
                        </div>
                        <button type="button" class="btn-popover-apply" id="btn-popover-apply">
                            <?= render_icon('check', '', 13) ?>
                            Áp Dụng Khoảng Ngày
                        </button>
                    </div>
                </div>
            </div>
        </header>

        <!-- Thanh Bộ Lọc Phụ (Sub-header Filter Bar) -->
        <div class="filter-bar">
            <form id="global-filter-form" method="GET" class="filter-left-group" action="">
                <!-- Giữ lại các query parameters khác của trang (ví dụ tab, view_mode...) -->
                <?php foreach ($_GET as $k => $v): ?>
                    <?php if (!in_array($k, ['website_id', 'from_date', 'to_date', 'date_preset'], true) && is_scalar($v)): ?>
                        <input type="hidden" name="<?= e($k) ?>" value="<?= e($v) ?>">
                    <?php endif; ?>
                <?php endforeach; ?>

                <!-- 1. Chọn Website -->
                <div class="filter-item-wrap">
                    <span class="filter-label">Website</span>
                    <select name="website_id" id="filter-website-select" class="filter-website-select" title="Chọn website cần xem báo cáo">
                        <?php foreach ($accessibleWebsites as $site): ?>
                            <option value="<?= $site['website_id'] ?>" <?= $site['website_id'] == $selectedWebsiteId ? 'selected' : '' ?>>
                                <?= e($site['name']) ?> (ID: <?= $site['website_id'] ?>)
                            </option>
                        <?php endforeach; ?>
                        <?php if (empty($accessibleWebsites)): ?>
                            <option value="0">Chưa có website nào</option>
                        <?php endif; ?>
                    </select>
                </div>

                <!-- 2. Chọn Nhanh Khoảng Thời Gian -->
                <div class="filter-item-wrap">
                    <span class="filter-label">Khoảng ngày</span>
                    <select name="date_preset" id="filter-date-preset" class="filter-preset-select" title="Chọn nhanh khoảng thời gian">
                        <option value="14_days" <?= $datePreset === '14_days' ? 'selected' : '' ?>>14 ngày qua</option>
                        <option value="today" <?= $datePreset === 'today' ? 'selected' : '' ?>>Hôm nay</option>
                        <option value="yesterday" <?= $datePreset === 'yesterday' ? 'selected' : '' ?>>Hôm qua</option>
                        <option value="7_days" <?= $datePreset === '7_days' ? 'selected' : '' ?>>7 ngày qua</option>
                        <option value="30_days" <?= $datePreset === '30_days' ? 'selected' : '' ?>>30 ngày qua</option>
                        <option value="all" <?= $datePreset === 'all' ? 'selected' : '' ?>>Toàn bộ thời gian</option>
                        <option value="custom" <?= $datePreset === 'custom' ? 'selected' : '' ?>>Tùy chọn ngày...</option>
                    </select>
                </div>

                <!-- 3. Hai ô chọn ngày Từ -> Đến -->
                <div class="filter-item-wrap filter-dates-box" id="filter-dates-box" title="Chọn ngày bắt đầu và kết thúc">
                    <span class="filter-label" style="font-size:0.78rem;">Từ</span>
                    <input type="date" name="from_date" id="filter-from-date" class="filter-date-input" value="<?= e($fromDate) ?>">
                    <span class="filter-label" style="font-size:0.78rem;">Đến</span>
                    <input type="date" name="to_date" id="filter-to-date" class="filter-date-input" value="<?= e($toDate) ?>">
                </div>

                <!-- 4. Nút Lọc Dữ Liệu -->
                <button type="submit" class="btn-filter-submit" title="Áp dụng bộ lọc ngay">
                    <?= render_icon('filter', '', 14) ?>
                    <span>Lọc dữ liệu</span>
                </button>

                <!-- 5. Nút Làm Mới -->
                <button type="button" class="btn-filter-refresh" onclick="window.location.reload()" title="Làm mới số liệu ngay lập tức">
                    <?= render_icon('refresh-cw', '', 13) ?>
                    <span>Làm mới</span>
                </button>

                <!-- 6. Huy hiệu Live -->
                <div class="badge-live" title="Dữ liệu tự động đồng bộ thời gian thực khi bạn thao tác trên Website Demo">
                    <span class="pulse-dot"></span>
                    <span>Trực tiếp (Live)</span>
                </div>
            </form>

            <!-- 7. Nút Xuất CSV -->
            <div>
                <a href="<?= $baseUrl ?>/api/analytics-export.php?website_id=<?= $selectedWebsiteId ?>&from_date=<?= e($fromDate) ?>&to_date=<?= e($toDate) ?>" class="btn-export-csv" title="Xuất toàn bộ dữ liệu ra file CSV">
                    <?= render_icon('download', '', 14) ?>
                    <span>Xuất CSV</span>
                </a>
            </div>
        </div>

        <main class="content-body">
