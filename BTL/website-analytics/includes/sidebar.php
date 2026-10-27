<?php
// ====================================================================
// INCLUDES: THANH ĐIỀU HƯỚNG SIDEBAR (includes/sidebar.php)
// Phong cách Enterprise SaaS: Icon Lucide SVG, Phân nhóm rõ ràng, User Card
// ====================================================================

$currentScript = basename($_SERVER['SCRIPT_NAME'] ?? '');
$isAdmin = is_admin();
?>
<aside id="app-sidebar" class="sidebar">
    <a href="<?= $baseUrl ?>/dashboard.php" class="sidebar-brand">
        <div class="sidebar-brand-mark">
            <?= render_icon('activity', '', 18, '#ffffff') ?>
        </div>
        <div class="sidebar-brand-text">
            <span>Web</span>Analytics
        </div>
        <span class="sidebar-brand-badge">SaaS</span>
    </a>

    <ul class="sidebar-menu">
        <li class="menu-category">Theo Dõi & Báo Cáo</li>
        <li>
            <a href="<?= $baseUrl ?>/dashboard.php" class="<?= $currentScript === 'dashboard.php' ? 'active' : '' ?>">
                <span class="nav-icon"><?= render_icon('layout-dashboard', '', 17) ?></span>
                <span>Tổng quan KPI</span>
            </a>
        </li>
        <li>
            <a href="<?= $baseUrl ?>/reports.php" class="<?= $currentScript === 'reports.php' ? 'active' : '' ?>">
                <span class="nav-icon"><?= render_icon('chart-bar', '', 17) ?></span>
                <span>Báo cáo thời gian</span>
            </a>
        </li>
        <li>
            <a href="<?= $baseUrl ?>/funnels.php" class="<?= $currentScript === 'funnels.php' ? 'active' : '' ?>">
                <span class="nav-icon"><?= render_icon('filter', '', 17) ?></span>
                <span>Chuỗi thao tác Funnel</span>
            </a>
        </li>
        <li>
            <a href="<?= $baseUrl ?>/event-logs.php" class="<?= $currentScript === 'event-logs.php' ? 'active' : '' ?>">
                <span class="nav-icon"><?= render_icon('activity', '', 17) ?></span>
                <span>Nhật ký sự kiện & Lỗi</span>
            </a>
        </li>

        <li class="menu-category">Cấu Hình & Kiểm Thử</li>
        <li>
            <a href="<?= $baseUrl ?>/websites.php" class="<?= $currentScript === 'websites.php' ? 'active' : '' ?>">
                <span class="nav-icon"><?= render_icon('globe', '', 17) ?></span>
                <span>Quản lý Website</span>
            </a>
        </li>
        <li>
            <a href="<?= $baseUrl ?>/event-definitions.php" class="<?= $currentScript === 'event-definitions.php' ? 'active' : '' ?>">
                <span class="nav-icon"><?= render_icon('mouse-pointer-click', '', 17) ?></span>
                <span>Cấu hình sự kiện</span>
            </a>
        </li>
        <li>
            <a href="<?= $baseUrl ?>/test-center.php" class="<?= $currentScript === 'test-center.php' ? 'active' : '' ?>">
                <span class="nav-icon"><?= render_icon('flask-conical', '', 17) ?></span>
                <span>Trung tâm kiểm thử</span>
            </a>
        </li>

        <?php if ($isAdmin): ?>
            <li class="menu-category">Quản Trị Hệ Thống</li>
            <li>
                <a href="<?= $baseUrl ?>/users.php" class="<?= $currentScript === 'users.php' ? 'active' : '' ?>">
                    <span class="nav-icon"><?= render_icon('users', '', 17) ?></span>
                    <span>Quản lý người dùng & Quyền</span>
                </a>
            </li>
            <li>
                <a href="<?= $baseUrl ?>/settings.php" class="<?= $currentScript === 'settings.php' ? 'active' : '' ?>">
                    <span class="nav-icon"><?= render_icon('settings', '', 17) ?></span>
                    <span>Cài đặt & Dữ liệu</span>
                </a>
            </li>
        <?php endif; ?>
    </ul>

    <div class="sidebar-user">
        <div class="avatar" title="<?= e($currentUser['email'] ?? '') ?>">
            <?= strtoupper(mb_substr($currentUser['full_name'] ?? 'U', 0, 1, 'UTF-8')) ?>
        </div>
        <div class="info">
            <div class="name" title="<?= e($currentUser['full_name']) ?>"><?= e($currentUser['full_name']) ?></div>
            <div class="role-badge"><?= e($currentUser['role']) ?></div>
        </div>
        <a href="<?= $baseUrl ?>/logout.php" class="logout-btn" title="Đăng xuất">
            <?= render_icon('log-out', '', 16) ?>
        </a>
    </div>
</aside>
