<?php
declare(strict_types=1);
/**
 * templates/layout.php — Layout chuẩn cho WebAnalytics
 * Dùng icon SVG vector chuyên nghiệp (không dùng emoji/sticker)
 */
require_once __DIR__ . '/icons.php';

function renderHead(string $pageTitle, string $extra = ''): void { ?>
<!DOCTYPE html>
<html lang="vi">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= htmlspecialchars($pageTitle) ?> — WebAnalytics</title>
  <meta name="description" content="Thống kê lượt truy cập và thao tác trên website theo thời gian thực">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link rel="stylesheet" href="assets/css/app.css">
  <?= $extra ?>
</head>
<body>
<?php }

function renderSidebar(string $activeTab = 'overview'): void {
  $menuItems = [
    'overview' => ['icon' => svgIcon('home', 'sidebar-svg', 18),     'label' => 'Tổng quan', 'link' => 'index.php?tab=overview'],
    'traffic'  => ['icon' => svgIcon('traffic', 'sidebar-svg', 18),  'label' => 'Lượt truy cập', 'link' => 'index.php?tab=traffic'],
    'users'    => ['icon' => svgIcon('user', 'sidebar-svg', 18),     'label' => 'Người dùng', 'link' => 'index.php?tab=users'],
    'actions'  => ['icon' => svgIcon('click', 'sidebar-svg', 18),    'label' => 'Thao tác trên website', 'link' => 'index.php?tab=actions'],
    'pages'    => ['icon' => svgIcon('pages', 'sidebar-svg', 18),    'label' => 'Trang phổ biến', 'link' => 'index.php?tab=pages'],
    'devices'  => ['icon' => svgIcon('device', 'sidebar-svg', 18),   'label' => 'Thiết bị', 'link' => 'index.php?tab=devices'],
    'geo'      => ['icon' => svgIcon('pin', 'sidebar-svg', 18),      'label' => 'Vị trí địa lý', 'link' => 'index.php?tab=geo'],
    'events'   => ['icon' => svgIcon('zap', 'sidebar-svg', 18),      'label' => 'Sự kiện (Events)', 'link' => 'register.php'],
    'reports'  => ['icon' => svgIcon('report', 'sidebar-svg', 18),   'label' => 'Báo cáo', 'link' => 'report.php'],
    'settings' => ['icon' => svgIcon('settings', 'sidebar-svg', 18), 'label' => 'Cài đặt', 'link' => 'index.php?tab=settings'],
  ];
?>
<aside class="sidebar" id="appSidebar">
  <a href="index.php" class="sidebar-brand">
    <div class="brand-icon">
      <div class="brand-bar"></div>
      <div class="brand-bar"></div>
      <div class="brand-bar"></div>
    </div>
    <span class="brand-title">WebAnalytics</span>
  </a>

  <ul class="sidebar-menu">
    <?php foreach ($menuItems as $key => $item): ?>
      <li class="sidebar-item <?= $activeTab === $key ? 'active' : '' ?>">
        <a href="<?= htmlspecialchars($item['link']) ?>">
          <span class="sidebar-icon"><?= $item['icon'] ?></span>
          <span><?= htmlspecialchars($item['label']) ?></span>
        </a>
      </li>
    <?php endforeach; ?>
  </ul>

  <div class="sidebar-footer">
    <p>FIT-DNU &bull; CNTT 18-09 &bull; Nhóm 7</p>
  </div>
</aside>
<?php }

function renderTopbar(array $sites = [], int $selectedSiteId = 0, string $dateFrom = '', string $dateTo = ''): void { 
  $displayDate = '01/10/2026 - 25/10/2026';
  if ($dateFrom && $dateTo) {
    $displayDate = date('d/m/Y', strtotime($dateFrom)) . ' - ' . date('d/m/Y', strtotime($dateTo));
  }
?>
<header class="topbar">
  <div class="topbar-left">
    <button class="menu-toggle" id="menuToggleBtn" title="Thu gọn / Mở rộng menu">
      <?= svgIcon('menu', '', 20) ?>
    </button>
    
    <div class="website-select-pill">
      <span style="display:flex;align-items:center;color:var(--text-muted);"><?= svgIcon('globe', '', 16) ?></span>
      <select id="globalSiteSelect" onchange="window.location.href='index.php?site='+this.value">
        <option value="0" <?= $selectedSiteId === 0 ? 'selected' : '' ?>>Tất cả Website (Tổng hợp)</option>
        <?php foreach ($sites as $s): ?>
          <option value="<?= $s['id'] ?>" <?= $s['id'] == $selectedSiteId ? 'selected' : '' ?>>
            <?= htmlspecialchars($s['name']) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>
  </div>

  <div class="topbar-right">
    <button class="date-range-picker" id="datePickerBtn" onclick="toggleDateModal()">
      <span style="display:flex;align-items:center;color:var(--text-muted);"><?= svgIcon('calendar', '', 16) ?></span>
      <span id="dateRangeText"><?= $displayDate ?></span>
      <span style="display:flex;align-items:center;color:var(--text-muted);"><?= svgIcon('chevron-down', '', 14) ?></span>
    </button>

    <div class="user-profile" title="Tài khoản quản trị viên">
      <div class="user-avatar">
        <span>NĐ</span>
      </div>
      <span class="user-name">Nguyễn Đức</span>
      <span style="display:flex;align-items:center;color:var(--text-muted);"><?= svgIcon('chevron-down', '', 14) ?></span>
    </div>
  </div>
</header>
<?php }

function renderMobileNav(string $activeTab = 'overview'): void { ?>
<nav class="mobile-nav-bar">
  <a href="index.php?tab=overview" class="mobile-nav-item <?= $activeTab === 'overview' ? 'active' : '' ?>">
    <span class="mobile-nav-icon"><?= svgIcon('home', '', 20) ?></span>
    <span>Tổng quan</span>
  </a>
  <a href="index.php?tab=traffic" class="mobile-nav-item <?= $activeTab === 'traffic' ? 'active' : '' ?>">
    <span class="mobile-nav-icon"><?= svgIcon('traffic', '', 20) ?></span>
    <span>Lượt truy cập</span>
  </a>
  <a href="index.php?tab=actions" class="mobile-nav-item <?= $activeTab === 'actions' ? 'active' : '' ?>">
    <span class="mobile-nav-icon"><?= svgIcon('click', '', 20) ?></span>
    <span>Thao tác</span>
  </a>
  <a href="report.php" class="mobile-nav-item <?= $activeTab === 'reports' ? 'active' : '' ?>">
    <span class="mobile-nav-icon"><?= svgIcon('report', '', 20) ?></span>
    <span>Báo cáo</span>
  </a>
  <a href="index.php?tab=settings" class="mobile-nav-item <?= $activeTab === 'settings' ? 'active' : '' ?>">
    <span class="mobile-nav-icon"><?= svgIcon('settings', '', 20) ?></span>
    <span>Cài đặt</span>
  </a>
</nav>
<?php }

function renderNav(string $active = 'dashboard'): void {
    // Tương thích ngược nếu các trang con gọi renderNav()
}

function renderFoot(): void { ?>
<script>
  // Mobile sidebar toggle
  const toggleBtn = document.getElementById('menuToggleBtn');
  const sidebar = document.getElementById('appSidebar');
  if (toggleBtn && sidebar) {
    toggleBtn.addEventListener('click', () => {
      sidebar.classList.toggle('open');
    });
  }

  function toggleDateModal() {
    const modal = document.getElementById('dateFilterModal');
    if (modal) {
      modal.style.display = modal.style.display === 'flex' ? 'none' : 'flex';
    }
  }
</script>
</body>
</html>
<?php }
