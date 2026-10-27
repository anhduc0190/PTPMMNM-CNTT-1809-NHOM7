<?php
// ====================================================================
// INCLUDES: THANH ĐIỀU HƯỚNG SIDEBAR (includes/sidebar.php)
// Thiết kế chuẩn xác theo Mockup Reference:
// - Brand: Web Analytics (Thống kê & Phân tích Website)
// - Danh mục: TỔNG QUAN, CẤU HÌNH & KIỂM THỬ, QUẢN TRỊ HỆ THỐNG
// - Menu Active: Nền xanh hoàng gia (#1a56db), bo góc 10px
// - User Card: Avatar tròn chữ cái + Tên + Chức vụ + Arrow + Nút Đăng xuất
// ====================================================================

$currentScript = basename($_SERVER['SCRIPT_NAME'] ?? '');
$isAdmin = is_admin();
?>
<aside id="app-sidebar" class="sidebar">
    <!-- Brand Box -->
    <a href="<?= $baseUrl ?>/dashboard.php" class="sidebar-brand-box">
        <div class="sidebar-brand-icon">
            <?= render_icon('chart-no-axes', '', 18, '#ffffff') ?>
        </div>
        <div class="sidebar-brand-info">
            <div class="sidebar-brand-title">Web Analytics</div>
            <div class="sidebar-brand-sub">Thống kê & Phân tích Website</div>
        </div>
    </a>

    <!-- Navigation Menu -->
    <ul class="sidebar-menu">
        <li class="menu-category">TỔNG QUAN</li>
        <li>
            <a href="<?= $baseUrl ?>/dashboard.php" class="<?= $currentScript === 'dashboard.php' ? 'active' : '' ?>">
                <span class="nav-icon"><?= render_icon('chart-bar', '', 17) ?></span>
                <span>Tổng quan KPI</span>
            </a>
        </li>
        <li>
            <a href="<?= $baseUrl ?>/reports.php" class="<?= $currentScript === 'reports.php' ? 'active' : '' ?>">
                <span class="nav-icon"><?= render_icon('calendar', '', 17) ?></span>
                <span>Báo cáo thời gian</span>
            </a>
        </li>
        <li>
            <a href="<?= $baseUrl ?>/funnels.php" class="<?= $currentScript === 'funnels.php' ? 'active' : '' ?>">
                <span class="nav-icon"><?= render_icon('filter', '', 17) ?></span>
                <span>Chuỗi thao tác (Funnel)</span>
            </a>
        </li>
        <li>
            <a href="<?= $baseUrl ?>/event-logs.php" class="<?= $currentScript === 'event-logs.php' ? 'active' : '' ?>">
                <span class="nav-icon"><?= render_icon('file-text', '', 17) ?></span>
                <span>Nhật ký sự kiện & Lỗi</span>
            </a>
        </li>

        <li class="menu-category">CẤU HÌNH & KIỂM THỬ</li>
        <li>
            <a href="<?= $baseUrl ?>/websites.php" class="<?= $currentScript === 'websites.php' ? 'active' : '' ?>">
                <span class="nav-icon"><?= render_icon('globe', '', 17) ?></span>
                <span>Quản lý Website</span>
            </a>
        </li>
        <li>
            <a href="<?= $baseUrl ?>/event-definitions.php" class="<?= $currentScript === 'event-definitions.php' ? 'active' : '' ?>">
                <span class="nav-icon"><?= render_icon('settings', '', 17) ?></span>
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
            <li class="menu-category">QUẢN TRỊ HỆ THỐNG</li>
            <li>
                <a href="<?= $baseUrl ?>/users.php" class="<?= $currentScript === 'users.php' ? 'active' : '' ?>">
                    <span class="nav-icon"><?= render_icon('users', '', 17) ?></span>
                    <span>Quản lý người dùng & Quyền</span>
                </a>
            </li>
            <li>
                <a href="<?= $baseUrl ?>/settings.php" class="<?= $currentScript === 'settings.php' ? 'active' : '' ?>">
                    <span class="nav-icon"><?= render_icon('database', '', 17) ?></span>
                    <span>Cài đặt & Dữ liệu</span>
                </a>
            </li>
        <?php endif; ?>
    </ul>

    <!-- Footer User Profile -->
    <div class="sidebar-footer">
        <div class="sidebar-user-card">
            <div class="avatar">
                <?= strtoupper(mb_substr($currentUser['full_name'] ?? 'U', 0, 1, 'UTF-8')) ?>
            </div>
            <div class="info">
                <div class="name"><?= e($currentUser['full_name'] ?? 'Admin') ?></div>
                <div class="role"><?= $currentUser['role'] === 'admin' ? 'Quản trị viên' : 'Thành viên' ?></div>
            </div>
            <span class="arrow">
                <?= render_icon('chevron-right', '', 14) ?>
            </span>
        </div>

        <a href="<?= $baseUrl ?>/logout.php" class="sidebar-logout-link" title="Đăng xuất khỏi hệ thống">
            <?= render_icon('log-out', '', 15) ?>
            <span>Đăng xuất</span>
        </a>
    </div>
</aside>
