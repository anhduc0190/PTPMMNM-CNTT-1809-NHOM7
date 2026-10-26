<?php
// ====================================================================
// INCLUDES: HEADER CHUNG (includes/header.php)
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
    <?php require_once __DIR__ . '/sidebar.php'; ?>
    <div class="main-wrapper">
        <header class="top-header">
            <div class="page-title">
                <?= e($pageTitle ?? 'Tổng quan') ?>
            </div>
            <div class="top-header-actions">
                <a href="<?= $baseUrl ?>/demo-sites/site-a/index.html" target="_blank" class="btn btn-outline btn-sm">
                    🛒 Mở Demo Site A
                </a>
                <a href="<?= $baseUrl ?>/demo-sites/site-b/index.html" target="_blank" class="btn btn-outline btn-sm">
                    🚀 Mở Demo Site B
                </a>
                <a href="<?= $baseUrl ?>/logout.php" class="btn btn-outline btn-sm" style="color:var(--danger); border-color:#fee2e2;">
                    🚪 Đăng xuất
                </a>
            </div>
        </header>

        <!-- Thanh Bo Loc Toan Cuc (Global Filter Bar) -->
        <div class="filter-bar">
            <form id="global-filter-form" method="GET" class="filter-group">
                <div class="filter-group">
                    <span class="filter-label">Website:</span>
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

                <div class="filter-group">
                    <span class="filter-label">Từ ngày:</span>
                    <input type="date" name="from_date" class="form-input" value="<?= e($fromDate) ?>">
                    <span class="filter-label">Đến:</span>
                    <input type="date" name="to_date" class="form-input" value="<?= e($toDate) ?>">
                    <button type="submit" class="btn btn-primary btn-sm">Lọc Dữ Liệu</button>
                </div>
            </form>

            <div class="filter-group">
                <a href="<?= $baseUrl ?>/api/analytics-export.php?website_id=<?= $selectedWebsiteId ?>&from_date=<?= e($fromDate) ?>&to_date=<?= e($toDate) ?>" class="btn btn-outline btn-sm">
                    📥 Xuất CSV
                </a>
            </div>
        </div>

        <main class="content-body">
