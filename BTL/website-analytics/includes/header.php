<?php
// ====================================================================
// INCLUDES: HEADER CHUNG (includes/header.php)
// Giao diện Enterprise SaaS: Hamburger Drawer, Breadcrumb, Bộ Lọc Toàn Cục
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

$fromDate = $_GET['from_date'] ?? date('Y-m-d', strtotime('-14 days'));
$toDate   = $_GET['to_date'] ?? date('Y-m-d');
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($pageTitle ?? 'Dashboard') ?> — <?= APP_NAME ?></title>
    <link rel="stylesheet" href="<?= $baseUrl ?>/assets/css/dashboard.css">
    <!-- Chart.js CDN -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
</head>
<body>
<div class="app-layout">
    <div id="sidebar-overlay" class="sidebar-overlay"></div>
    <?php require_once __DIR__ . '/sidebar.php'; ?>
    <div class="main-wrapper">
        <header class="top-header">
            <div class="top-header-left">
                <button type="button" class="mobile-menu-toggle" id="sidebar-toggle" aria-label="Mở menu điều hướng">
                    <?= render_icon('menu', '', 18) ?>
                </button>
                <div class="page-title">
                    <?= e($pageTitle ?? 'Tổng quan') ?>
                </div>
            </div>
            <div class="top-header-actions">
                <a href="<?= $baseUrl ?>/demo-sites/site-a/index.html" target="_blank" class="btn btn-outline btn-sm" title="Mở trang demo Thương Mại Điện Tử (Site A)">
                    <?= render_icon('external-link', '', 13) ?>
                    <span class="btn-text">Demo Site A</span>
                </a>
                <a href="<?= $baseUrl ?>/demo-sites/site-b/index.html" target="_blank" class="btn btn-outline btn-sm" title="Mở trang demo Tin Tức & Khóa Học (Site B)">
                    <?= render_icon('external-link', '', 13) ?>
                    <span class="btn-text">Demo Site B</span>
                </a>
                <a href="<?= $baseUrl ?>/logout.php" class="btn btn-outline btn-sm" style="color:var(--accent-red-text); border-color:var(--accent-red-border);" title="Đăng xuất khỏi hệ thống">
                    <?= render_icon('log-out', '', 13) ?>
                    <span class="btn-text">Đăng xuất</span>
                </a>
            </div>
        </header>

        <!-- Thanh Bộ Lọc Toàn Cục (Global Filter Bar) -->
        <div class="filter-bar">
            <form id="global-filter-form" method="GET" class="filter-bar-form">
                <div class="filter-item">
                    <span class="filter-label">
                        <?= render_icon('globe', '', 14, 'var(--text-secondary)') ?>
                        Website:
                    </span>
                    <select name="website_id" class="form-select" onchange="this.form.submit()">
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

                <div class="filter-item">
                    <span class="filter-label">
                        <?= render_icon('calendar', '', 14, 'var(--text-secondary)') ?>
                        Từ:
                    </span>
                    <input type="date" name="from_date" class="form-input" value="<?= e($fromDate) ?>">
                    <span class="filter-label">Đến:</span>
                    <input type="date" name="to_date" class="form-input" value="<?= e($toDate) ?>">
                    <button type="submit" class="btn btn-primary btn-sm">
                        <?= render_icon('filter', '', 13) ?>
                        Áp Dụng
                    </button>
                </div>
            </form>

            <div class="filter-item">
                <a href="<?= $baseUrl ?>/api/analytics-export.php?website_id=<?= $selectedWebsiteId ?>&from_date=<?= e($fromDate) ?>&to_date=<?= e($toDate) ?>" class="btn btn-outline btn-sm">
                    <?= render_icon('download', '', 13) ?>
                    Xuất CSV
                </a>
            </div>
        </div>

        <main class="content-body">
