<?php
// ====================================================================
// INCLUDES: THANH ĐIỀU HƯỚNG SIDEBAR (includes/sidebar.php)
// ====================================================================

$currentScript = basename($_SERVER['SCRIPT_NAME'] ?? '');
$isAdmin = is_admin();
?>
<aside class="sidebar">
    <a href="<?= $baseUrl ?>/dashboard.php" class="sidebar-brand">
        📊 <span>Web</span>Analytics
    </a>

    <ul class="sidebar-menu">
        <li class="menu-category">Theo Dõi & Báo Cáo</li>
        <li>
            <a href="<?= $baseUrl ?>/dashboard.php" class="<?= $currentScript === 'dashboard.php' ? 'active' : '' ?>">
                📈 Tổng quan KPI
            </a>
        </li>
        <li>
            <a href="<?= $baseUrl ?>/reports.php" class="<?= $currentScript === 'reports.php' ? 'active' : '' ?>">
                📊 Báo cáo thời gian
            </a>
        </li>
        <li>
            <a href="<?= $baseUrl ?>/funnels.php" class="<?= $currentScript === 'funnels.php' ? 'active' : '' ?>">
                🎯 Chuỗi thao tác (Funnel)
            </a>
        </li>
        <li>
            <a href="<?= $baseUrl ?>/event-logs.php" class="<?= $currentScript === 'event-logs.php' ? 'active' : '' ?>">
                📜 Nhật ký sự kiện & Lỗi
            </a>
        </li>

        <li class="menu-category">Cấu Hình & Kiểm Thử</li>
        <li>
            <a href="<?= $baseUrl ?>/websites.php" class="<?= $currentScript === 'websites.php' ? 'active' : '' ?>">
                🌐 Quản lý Website
            </a>
        </li>
        <li>
            <a href="<?= $baseUrl ?>/event-definitions.php" class="<?= $currentScript === 'event-definitions.php' ? 'active' : '' ?>">
                ⚙️ Cấu hình sự kiện
            </a>
        </li>
        <li>
            <a href="<?= $baseUrl ?>/test-center.php" class="<?= $currentScript === 'test-center.php' ? 'active' : '' ?>">
                🧪 Trung tâm kiểm thử
            </a>
        </li>

        <?php if ($isAdmin): ?>
            <li class="menu-category">Quản Trị Hệ Thống</li>
            <li>
                <a href="<?= $baseUrl ?>/users.php" class="<?= $currentScript === 'users.php' ? 'active' : '' ?>">
                    👥 Quản lý người dùng & Quyền
                </a>
            </li>
            <li>
                <a href="<?= $baseUrl ?>/settings.php" class="<?= $currentScript === 'settings.php' ? 'active' : '' ?>">
                    🛠️ Cài đặt & Dữ liệu
                </a>
            </li>
        <?php endif; ?>
    </ul>

    <div class="sidebar-user">
        <div class="avatar">
            <?= strtoupper(substr($currentUser['full_name'] ?? 'U', 0, 1)) ?>
        </div>
        <div class="info">
            <div class="name"><?= e($currentUser['full_name']) ?></div>
            <div class="role"><?= e($currentUser['role']) ?></div>
        </div>
    </div>
</aside>
