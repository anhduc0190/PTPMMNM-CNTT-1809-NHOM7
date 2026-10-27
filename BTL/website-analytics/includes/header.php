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

$fromDate = $_GET['from_date'] ?? date('Y-m-d', strtotime('-14 days'));
$toDate   = $_GET['to_date'] ?? date('Y-m-d');
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
                <div class="pill-date-range" title="Khoảng thời gian báo cáo">
                    <?= render_icon('calendar', '', 14, 'var(--text-muted)') ?>
                    <span><?= date('d/m/Y', strtotime($fromDate)) ?> &nbsp;&rarr;&nbsp; <?= date('d/m/Y', strtotime($toDate)) ?></span>
                    <?= render_icon('chevron-down', '', 14, 'var(--text-muted)') ?>
                </div>
            </div>
        </header>

        <!-- Thanh Bộ Lọc Phụ (Sub-header Filter Bar) -->
        <div class="filter-bar">
            <form id="global-filter-form" method="GET" class="filter-left-group">
                <span class="filter-website-label">Website</span>
                <select name="website_id" class="filter-website-select" onchange="this.form.submit()">
                    <?php foreach ($accessibleWebsites as $site): ?>
                        <option value="<?= $site['website_id'] ?>" <?= $site['website_id'] == $selectedWebsiteId ? 'selected' : '' ?>>
                            <?= e($site['name']) ?> (ID: <?= $site['website_id'] ?>)
                        </option>
                    <?php endforeach; ?>
                    <?php if (empty($accessibleWebsites)): ?>
                        <option value="0">Chưa có website nào</option>
                    <?php endif; ?>
                </select>

                <!-- Hidden date inputs for form submit -->
                <input type="hidden" name="from_date" value="<?= e($fromDate) ?>">
                <input type="hidden" name="to_date" value="<?= e($toDate) ?>">

                <button type="submit" class="btn-filter-submit">
                    <?= render_icon('filter', '', 14) ?>
                    <span>Lọc dữ liệu</span>
                </button>

                <button type="button" class="btn-filter-refresh" onclick="window.location.reload()" title="Làm mới số liệu ngay lập tức">
                    <?= render_icon('refresh-cw', '', 13) ?>
                    <span>Làm mới</span>
                </button>

                <div class="badge-live" title="Dữ liệu tự động đồng bộ thời gian thực khi bạn thao tác trên Website Demo">
                    <span class="pulse-dot"></span>
                    <span>Trực tiếp (Live)</span>
                </div>
            </form>

            <div>
                <a href="<?= $baseUrl ?>/api/analytics-export.php?website_id=<?= $selectedWebsiteId ?>&from_date=<?= e($fromDate) ?>&to_date=<?= e($toDate) ?>" class="btn-export-csv" title="Xuất toàn bộ dữ liệu ra file CSV">
                    <?= render_icon('download', '', 14) ?>
                    <span>Xuất CSV</span>
                </a>
            </div>
        </div>

        <main class="content-body">
