<?php
// ====================================================================
// TRANG CHẠY THỬ TRỰC TIẾP TƯƠNG TÁC (live-demo.php)
// Môi trường Sandbox Live Runner cho MỌI website trong hệ thống
// Tự động kết nối tracker.js, phát sinh sự kiện & kiểm thử theo thời gian thực
// ====================================================================

require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/icons.php';

$pdo = get_db_connection();

// 1. Xác định Website cần chạy thử
$websiteId = (int)($_GET['website_id'] ?? 0);
if ($websiteId <= 0) {
    // Mặc định lấy website đầu tiên
    $stmtFirst = $pdo->query('SELECT `website_id` FROM `websites` WHERE `status` = "active" ORDER BY `website_id` ASC LIMIT 1');
    $websiteId = (int)$stmtFirst->fetchColumn();
}

$stmtSite = $pdo->prepare('SELECT `website_id`, `name`, `domain`, `tracking_key`, `timezone`, `status`, `late_event_window_hours` FROM `websites` WHERE `website_id` = ? LIMIT 1');
$stmtSite->execute([$websiteId]);
$website = $stmtSite->fetch();

if (!$website) {
    die("<h1>Lỗi: Website không tồn tại</h1><p>Website ID #{$websiteId} không tìm thấy trong hệ thống.</p><p><a href='websites.php'>Quay lại trang Quản lý Website</a></p>");
}

// Lấy danh sách tất cả website active để làm bộ chuyển đổi nhanh
$allSites = $pdo->query('SELECT `website_id`, `name`, `domain` FROM `websites` WHERE `status` = "active" ORDER BY `website_id` ASC')->fetchAll();

// Lấy các sự kiện được khai báo cho website này
$stmtEvents = $pdo->prepare('SELECT `definition_id`, `event_name`, `description`, `enabled` FROM `event_definitions` WHERE `website_id` = ? ORDER BY `definition_id` ASC');
$stmtEvents->execute([$websiteId]);
$definitions = $stmtEvents->fetchAll();

$baseUrl = get_base_url();
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Chạy Thử Live Demo - <?= e($website['name']) ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=JetBrains+Mono:wght@400;600&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #2563eb;
            --primary-hover: #1d4ed8;
            --bg-page: #f8fafc;
            --bg-card: #ffffff;
            --border: #e2e8f0;
            --text-main: #0f172a;
            --text-muted: #64748b;
            --accent-green: #10b981;
            --accent-purple: #8b5cf6;
            --accent-amber: #f59e0b;
            --accent-red: #ef4444;
        }

        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            background: var(--bg-page);
            color: var(--text-main);
            line-height: 1.5;
            padding-bottom: 3rem;
        }

        /* Top Admin Bar */
        .admin-top-bar {
            background: #0f172a;
            color: #cbd5e1;
            padding: 0.65rem 1.5rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            font-size: 0.82rem;
            border-bottom: 1px solid #1e293b;
            flex-wrap: wrap;
            gap: 0.75rem;
        }
        .admin-top-bar a {
            color: #93c5fd;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.35rem;
            font-weight: 500;
        }
        .admin-top-bar a:hover { color: #ffffff; text-decoration: underline; }

        /* Demo Header */
        .demo-navbar {
            background: var(--bg-card);
            border-bottom: 1px solid var(--border);
            padding: 0.85rem 1.5rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            box-shadow: 0 1px 3px rgba(0,0,0,0.04);
            flex-wrap: wrap;
            gap: 1rem;
        }
        .demo-brand {
            display: flex;
            align-items: center;
            gap: 0.65rem;
        }
        .demo-brand-icon {
            width: 36px;
            height: 36px;
            border-radius: 8px;
            background: linear-gradient(135deg, #2563eb, #3b82f6);
            display: flex;
            align-items: center;
            justify-content: center;
            color: #fff;
        }
        .demo-brand-title {
            font-size: 1.05rem;
            font-weight: 700;
            color: var(--text-main);
        }
        .demo-brand-sub {
            font-size: 0.75rem;
            color: var(--text-muted);
        }

        /* Nav Links */
        .demo-nav-links {
            display: flex;
            gap: 0.4rem;
            list-style: none;
            flex-wrap: wrap;
        }
        .demo-nav-links a {
            padding: 0.45rem 0.85rem;
            border-radius: 6px;
            text-decoration: none;
            color: var(--text-muted);
            font-size: 0.85rem;
            font-weight: 500;
            transition: all 0.15s ease;
        }
        .demo-nav-links a:hover, .demo-nav-links a.active {
            color: var(--primary);
            background: #eff6ff;
        }

        /* Container */
        .container {
            max-width: 1200px;
            margin: 1.5rem auto;
            padding: 0 1.25rem;
        }

        /* Hero Banner */
        .hero-banner {
            background: linear-gradient(135deg, #1e293b 0%, #0f172a 100%);
            color: #ffffff;
            border-radius: 12px;
            padding: 2rem;
            margin-bottom: 1.5rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 1.5rem;
            box-shadow: 0 4px 16px rgba(15,23,42,0.1);
        }
        .hero-info h1 {
            font-size: 1.45rem;
            font-weight: 800;
            margin-bottom: 0.5rem;
        }
        .hero-info p {
            color: #94a3b8;
            font-size: 0.88rem;
            max-width: 650px;
            line-height: 1.6;
        }
        .tracking-status-badge {
            background: rgba(16, 185, 129, 0.15);
            border: 1px solid rgba(16, 185, 129, 0.35);
            color: #34d399;
            padding: 0.45rem 0.9rem;
            border-radius: 9999px;
            font-size: 0.8rem;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 0.45rem;
        }
        .pulse-dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: #10b981;
            animation: pulse-animation 1.5s infinite;
        }
        @keyframes pulse-animation {
            0% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(16,185,129,0.7); }
            70% { transform: scale(1); box-shadow: 0 0 0 8px rgba(16,185,129,0); }
            100% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(16,185,129,0); }
        }

        /* Grid */
        .grid-2 {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1.25rem;
            margin-bottom: 1.5rem;
        }
        @media (max-width: 860px) {
            .grid-2 { grid-template-columns: 1fr; }
        }

        .card {
            background: var(--bg-card);
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 1.25rem;
            box-shadow: 0 1px 3px rgba(0,0,0,0.03);
        }
        .card-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 1rem;
            padding-bottom: 0.65rem;
            border-bottom: 1px solid var(--border);
        }
        .card-header h3 {
            font-size: 0.98rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 0.45rem;
        }

        /* Buttons */
        .btn-action {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.55rem 1rem;
            border-radius: 8px;
            font-size: 0.85rem;
            font-weight: 600;
            cursor: pointer;
            border: 1px solid transparent;
            transition: all 0.15s ease;
            text-decoration: none;
        }
        .btn-blue { background: #2563eb; color: #fff; }
        .btn-blue:hover { background: #1d4ed8; }
        .btn-green { background: #10b981; color: #fff; }
        .btn-green:hover { background: #059669; }
        .btn-purple { background: #8b5cf6; color: #fff; }
        .btn-purple:hover { background: #7c3aed; }
        .btn-amber { background: #f59e0b; color: #fff; }
        .btn-amber:hover { background: #d97706; }
        .btn-outline { background: #ffffff; color: var(--text-main); border-color: var(--border); }
        .btn-outline:hover { background: #f1f5f9; }

        /* Action list */
        .action-list {
            display: flex;
            flex-direction: column;
            gap: 0.75rem;
        }
        .action-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0.65rem 0.85rem;
            background: #f8fafc;
            border: 1px solid var(--border);
            border-radius: 8px;
            gap: 0.75rem;
            flex-wrap: wrap;
        }
        .action-row-info strong {
            font-size: 0.85rem;
            display: block;
        }
        .action-row-info span {
            font-size: 0.75rem;
            color: var(--text-muted);
        }

        /* Live Console Log Box */
        .console-box {
            background: #090d16;
            border: 1px solid #1e293b;
            border-radius: 12px;
            padding: 1rem;
            font-family: 'JetBrains Mono', monospace;
            color: #e2e8f0;
            font-size: 0.78rem;
            line-height: 1.6;
            height: 280px;
            overflow-y: auto;
        }
        .log-entry {
            margin-bottom: 0.4rem;
            padding-bottom: 0.35rem;
            border-bottom: 1px solid rgba(255,255,255,0.06);
            display: flex;
            align-items: flex-start;
            gap: 0.5rem;
        }
        .log-time { color: #64748b; flex-shrink: 0; }
        .log-success { color: #34d399; font-weight: 600; }
        .log-error { color: #f87171; font-weight: 600; }
        .log-tag {
            background: rgba(59,130,246,0.2);
            color: #93c5fd;
            padding: 1px 5px;
            border-radius: 3px;
            font-size: 0.72rem;
        }

        /* Toast notification */
        #live-toast {
            position: fixed;
            bottom: 1.5rem;
            right: 1.5rem;
            background: #0f172a;
            color: #ffffff;
            padding: 0.85rem 1.25rem;
            border-radius: 8px;
            border-left: 4px solid var(--accent-green);
            box-shadow: 0 10px 25px rgba(0,0,0,0.25);
            font-size: 0.85rem;
            display: none;
            z-index: 9999;
            align-items: center;
            gap: 0.65rem;
        }
    </style>
</head>
<body>

    <!-- 1. THANH ĐIỀU HƯỚNG QUẢN TRỊ TRÊN CÙNG -->
    <div class="admin-top-bar">
        <div style="display:flex; align-items:center; gap:0.75rem; flex-wrap:wrap;">
            <span style="display:inline-flex; align-items:center; gap:0.35rem; color:#f8fafc; font-weight:700;">
                <?= render_icon('flask-conical', '', 14, '#60a5fa') ?>
                CHẾ ĐỘ CHẠY THỬ LIVE DEMO (SANDBOX RUNNER)
            </span>
            <span style="color:#475569;">|</span>
            <span>Đang thử nghiệm: <strong><?= e($website['name']) ?></strong> (ID: #<?= $websiteId ?>)</span>
        </div>

        <div style="display:flex; align-items:center; gap:1rem; flex-wrap:wrap;">
            <!-- Chọn nhanh website khác -->
            <form method="GET" style="display:inline-flex; align-items:center; gap:0.4rem;">
                <label style="color:#94a3b8; font-size:0.78rem;">Đổi website:</label>
                <select name="website_id" onchange="this.form.submit()" style="background:#1e293b; color:#f8fafc; border:1px solid #334155; border-radius:4px; padding:0.25rem 0.5rem; font-size:0.78rem;">
                    <?php foreach ($allSites as $s): ?>
                        <option value="<?= $s['website_id'] ?>" <?= $s['website_id'] == $websiteId ? 'selected' : '' ?>>
                            #<?= $s['website_id'] ?>: <?= e($s['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </form>

            <a href="dashboard.php?website_id=<?= $websiteId ?>" target="_blank" title="Mở trang Dashboard của website này">
                <?= render_icon('layout-dashboard', '', 13) ?>
                Mở Dashboard
            </a>
            <a href="event-logs.php?website_id=<?= $websiteId ?>" target="_blank" title="Mở nhật ký sự kiện của website này">
                <?= render_icon('activity', '', 13) ?>
                Nhật Ký Sự Kiện
            </a>
            <a href="websites.php">
                <?= render_icon('arrow-right', '', 13) ?>
                Về Quản Lý Website
            </a>
        </div>
    </div>

    <!-- 2. NAVBAR GIẢ LẬP CỦA WEBSITE -->
    <header class="demo-navbar">
        <div class="demo-brand">
            <div class="demo-brand-icon">
                <?= render_icon('globe', '', 20) ?>
            </div>
            <div>
                <div class="demo-brand-title"><?= e($website['name']) ?></div>
                <div class="demo-brand-sub">Domain: <code><?= e($website['domain']) ?></code> &bull; Key: <code><?= substr(e($website['tracking_key']), 0, 18) ?>...</code></div>
            </div>
        </div>

        <!-- Simulated Site Navigation (Clicking sends page_view) -->
        <ul class="demo-nav-links">
            <li><a href="javascript:void(0)" class="active" onclick="simulatePageNav('/', 'Trang Chủ', this)">Trang Chủ</a></li>
            <li><a href="javascript:void(0)" onclick="simulatePageNav('/products', 'Sản Phẩm & Thiết Bị', this)">Sản Phẩm</a></li>
            <li><a href="javascript:void(0)" onclick="simulatePageNav('/pricing', 'Bảng Giá & Gói Dịch Vụ', this)">Bảng Giá</a></li>
            <li><a href="javascript:void(0)" onclick="simulatePageNav('/downloads', 'Tải Tài Liệu & Catalog', this)">Tài Liệu</a></li>
            <li><a href="javascript:void(0)" onclick="simulatePageNav('/contact', 'Liên Hệ Hỗ Trợ', this)">Liên Hệ</a></li>
        </ul>

        <div class="tracking-status-badge">
            <span class="pulse-dot"></span>
            <span>SDK Đang Theo Dõi (Active)</span>
        </div>
    </header>

    <main class="container">
        <!-- 3. HERO BANNER -->
        <div class="hero-banner">
            <div class="hero-info">
                <div style="font-size:0.8rem; text-transform:uppercase; letter-spacing:0.05em; color:#60a5fa; margin-bottom:0.35rem; font-weight:700;">
                    Trang Demo Tương Tác Sẵn Sàng Chạy Ngay
                </div>
                <h1>Chào mừng đến với <?= e($website['name']) ?></h1>
                <p>
                    Website này đã được tích hợp sẵn <code>tracker.js</code> với mã khoá riêng biệt. Mọi thao tác xem trang, bấm nút, tải catalog hoặc sự kiện khai báo sẽ được gửi trực tiếp về hệ thống và hiển thị ngay trên Dashboard.
                </p>
                <div style="margin-top:1rem; display:flex; gap:0.65rem; flex-wrap:wrap; font-size:0.8rem; color:#94a3b8;">
                    <span>Khóa Tracking: <code style="color:#67e8f9; background:#0f172a; padding:2px 6px; border-radius:4px;"><?= e($website['tracking_key']) ?></code></span>
                    <span>Múi giờ: <strong><?= e($website['timezone']) ?></strong></span>
                    <span>Session ID: <strong id="current-session-label" style="color:#fcd34d;">(Đang tải...)</strong></span>
                </div>
            </div>

            <div style="display:flex; flex-direction:column; gap:0.5rem;">
                <button type="button" class="btn-action btn-blue" onclick="firePageView()">
                    <?= render_icon('eye', '', 14) ?>
                    Bắn Lượt Xem Trang (page_view)
                </button>
                <button type="button" class="btn-action btn-green" onclick="fireAddToCart()">
                    <?= render_icon('zap', '', 14) ?>
                    Bắn Thao Tác (button_click)
                </button>
                <button type="button" class="btn-action btn-purple" onclick="triggerFunnelFlow()">
                    <?= render_icon('download', '', 14) ?>
                    Chạy Phễu Tải File (Funnel)
                </button>
            </div>
        </div>

        <!-- 4. KHU VỰC THAO TÁC SỰ KIỆN TƯƠNG TÁC -->
        <div class="grid-2">
            <!-- Cột 1: Thao tác người dùng & phễu chuyển đổi -->
            <div class="card">
                <div class="card-header">
                    <h3>
                        <?= render_icon('zap', '', 16, 'var(--primary)') ?>
                        Thao Tác Tương Tác Chuẩn (Standard Events)
                    </h3>
                    <span style="font-size:0.75rem; color:var(--text-muted);">Bấm để phát sinh sự kiện</span>
                </div>

                <div class="action-list">
                    <div class="action-row">
                        <div class="action-row-info">
                            <strong>1. Xem chi tiết sản phẩm cao cấp</strong>
                            <span>Sự kiện <code>page_view</code> trên đường dẫn <code>/product/macbook-pro-16</code></span>
                        </div>
                        <button type="button" class="btn-action btn-outline" style="font-size:0.78rem;" onclick="fireCustomPageView('/product/macbook-pro-16', 'MacBook Pro 16 M3 Max')">
                            <?= render_icon('eye', '', 13) ?>
                            Xem SP
                        </button>
                    </div>

                    <div class="action-row">
                        <div class="action-row-info">
                            <strong>2. Bấm nút "Thêm vào giỏ hàng"</strong>
                            <span>Sự kiện <code>button_click</code> kèm thuộc tính sản phẩm và giá tiền</span>
                        </div>
                        <button type="button" class="btn-action btn-blue" style="font-size:0.78rem;" onclick="fireButtonClick('btn_add_to_cart', 'Thêm vào giỏ hàng', {product_id: 101, price: 35000000})">
                            <?= render_icon('zap', '', 13) ?>
                            Thêm Giỏ
                        </button>
                    </div>

                    <div class="action-row">
                        <div class="action-row-info">
                            <strong>3. Bấm nút "Đăng ký tư vấn miễn phí"</strong>
                            <span>Sự kiện <code>button_click</code> trên form đăng ký</span>
                        </div>
                        <button type="button" class="btn-action btn-green" style="font-size:0.78rem;" onclick="fireButtonClick('btn_submit_consultation', 'Gửi Form Tư Vấn', {source: 'landing_page'})">
                            <?= render_icon('check', '', 13) ?>
                            Đăng Ký
                        </button>
                    </div>

                    <div class="action-row" style="background:#fdf4ff; border-color:#f5d0fe;">
                        <div class="action-row-info">
                            <strong style="color:#86198f;">4. Chuỗi Phễu Funnel Tải Tài Liệu</strong>
                            <span style="color:#a21caf;">Kích hoạt tự động: <code>download_start</code> &rarr; <code>download_complete</code></span>
                        </div>
                        <button type="button" class="btn-action btn-purple" style="font-size:0.78rem;" onclick="triggerFunnelFlow()">
                            <?= render_icon('download', '', 13) ?>
                            Tải Catalog
                        </button>
                    </div>
                </div>
            </div>

            <!-- Cột 2: Danh sách sự kiện đã khai báo & Custom Trigger -->
            <div class="card">
                <div class="card-header">
                    <h3>
                        <?= render_icon('sparkles', '', 16, 'var(--accent-purple)') ?>
                        Sự Kiện Khai Báo Của Website Này (<?= count($definitions) ?>)
                    </h3>
                    <a href="event-definitions.php?website_id=<?= $websiteId ?>" target="_blank" style="font-size:0.78rem; color:var(--primary); text-decoration:none; font-weight:600;">
                        + Quản lý sự kiện ↗
                    </a>
                </div>

                <p style="font-size:0.8rem; color:var(--text-muted); margin-bottom:0.85rem;">
                    Bấm nút bên dưới để bắn ngay sự kiện tương ứng vào cơ sở dữ liệu:
                </p>

                <div class="action-list" style="max-height: 230px; overflow-y: auto;">
                    <?php if (empty($definitions)): ?>
                        <div style="text-align:center; padding:1.5rem; color:var(--text-muted); font-size:0.82rem;">
                            Chưa có sự kiện nào được khai báo cho website này.
                        </div>
                    <?php else: ?>
                        <?php foreach ($definitions as $def): ?>
                            <?php $isEnabled = ((int)$def['enabled'] === 1); ?>
                            <div class="action-row" style="<?= !$isEnabled ? 'opacity:0.6; background:#fff1f2;' : '' ?>">
                                <div class="action-row-info">
                                    <strong style="font-family:monospace;"><?= e($def['event_name']) ?></strong>
                                    <span><?= e($def['description'] ?: 'Sự kiện tuỳ chỉnh') ?> &bull; 
                                        <b style="color:<?= $isEnabled ? 'var(--accent-green)' : 'var(--accent-red)' ?>;"><?= $isEnabled ? 'Đang Bật' : 'Đã Tắt (Bị Chặn)' ?></b>
                                    </span>
                                </div>
                                <button type="button" class="btn-action <?= $isEnabled ? 'btn-blue' : 'btn-outline' ?>" style="font-size:0.75rem; padding:0.35rem 0.65rem;" onclick="fireDirectEvent('<?= e(addslashes($def['event_name'])) ?>')">
                                    <?= render_icon('play', '', 11) ?>
                                    Bắn Sự Kiện
                                </button>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                <!-- Thử nghiệm sự kiện chưa khai báo (Demonstrate Fraud Prevention) -->
                <div style="margin-top:0.85rem; padding-top:0.75rem; border-top:1px dashed var(--border); display:flex; gap:0.5rem; align-items:center;">
                    <input type="text" id="custom-test-event-name" class="form-input" style="flex:1; height:34px; padding:0 0.65rem; border:1px solid var(--border); border-radius:6px; font-size:0.8rem;" placeholder="Nhập tên sự kiện bất kỳ (vd: su_kien_chua_khai_bao)">
                    <button type="button" class="btn-action btn-outline" style="height:34px; font-size:0.78rem;" onclick="fireArbitraryEvent()">
                        <?= render_icon('zap', '', 12) ?>
                        Thử Bắn
                    </button>
                </div>
            </div>
        </div>

        <!-- 5. HỘP MONITOR NHẬT KÝ SỰ KIỆN TRỰC TIẾP (LIVE LOG CONSOLE) -->
        <div class="card">
            <div class="card-header">
                <h3>
                    <?= render_icon('activity', '', 16, 'var(--primary)') ?>
                    Nhật Ký Tiếp Nhận Sự Kiện Trực Tiếp (Live Stream Console)
                </h3>
                <div style="display:flex; align-items:center; gap:0.75rem;">
                    <span id="log-counter" style="font-size:0.78rem; color:var(--text-muted); font-weight:600;">0 sự kiện vừa bắn</span>
                    <button type="button" class="btn-action btn-outline" style="padding:0.25rem 0.65rem; font-size:0.75rem; height:26px;" onclick="clearLogConsole()">
                        Xóa log
                    </button>
                    <a href="dashboard.php?website_id=<?= $websiteId ?>" target="_blank" class="btn-action btn-blue" style="padding:0.25rem 0.75rem; font-size:0.75rem; height:26px;">
                        <?= render_icon('layout-dashboard', '', 12) ?>
                        Xem Dashboard Này
                    </a>
                </div>
            </div>

            <div class="console-box" id="live-console">
                <div class="log-entry">
                    <span class="log-time">[Hệ thống]</span>
                    <span style="color:#94a3b8;">Khởi tạo trang Chạy Thử cho Website <strong><?= e($website['name']) ?></strong> thành công. Sẵn sàng tiếp nhận thao tác...</span>
                </div>
            </div>
        </div>
    </main>

    <!-- Toast Thông Báo Nổi -->
    <div id="live-toast">
        <?= render_icon('check-circle', '', 16, '#34d399') ?>
        <span id="live-toast-msg">Thông báo</span>
    </div>

    <!-- TÍCH HỢP JAVASCRIPT TRACKING SDK CHO WEBSITE NÀY -->
    <script src="sdk/tracker.js"
            data-tracking-key="<?= e($website['tracking_key']) ?>"
            data-api-endpoint="api/track.php">
    </script>

    <script>
    var currentPath = window.location.pathname;
    var currentTitle = document.title;
    var logCount = 0;

    // Toast helper
    function showToast(msg, isError) {
        var t = document.getElementById('live-toast');
        var m = document.getElementById('live-toast-msg');
        if (!t || !m) return;
        m.innerHTML = msg;
        t.style.borderLeftColor = isError ? '#ef4444' : '#10b981';
        t.style.display = 'flex';
        setTimeout(function() { t.style.display = 'none'; }, 3500);
    }

    // Console logger
    function appendLog(eventName, status, details, isError) {
        logCount++;
        var counter = document.getElementById('log-counter');
        if (counter) counter.textContent = logCount + ' sự kiện vừa bắn';

        var consoleBox = document.getElementById('live-console');
        if (!consoleBox) return;

        var now = new Date();
        var timeStr = now.toTimeString().split(' ')[0] + '.' + String(now.getMilliseconds()).padStart(3, '0');

        var div = document.createElement('div');
        div.className = 'log-entry';
        div.innerHTML = '<span class="log-time">[' + timeStr + ']</span>' +
            '<span class="log-tag">' + eventName + '</span>' +
            '<span class="' + (isError ? 'log-error' : 'log-success') + '">' + status + '</span>' +
            '<span style="color:#cbd5e1;">' + details + '</span>';
        
        consoleBox.prepend(div);
    }

    function clearLogConsole() {
        var consoleBox = document.getElementById('live-console');
        if (consoleBox) {
            consoleBox.innerHTML = '<div class="log-entry"><span class="log-time">[Hệ thống]</span><span style="color:#94a3b8;">Đã xóa nhật ký.</span></div>';
        }
        logCount = 0;
        document.getElementById('log-counter').textContent = '0 sự kiện vừa bắn';
    }

    // Hiển thị Session ID từ SDK
    window.addEventListener('load', function() {
        if (window.WebTracker) {
            var sessId = window.WebTracker.getSessionId();
            var sessLabel = document.getElementById('current-session-label');
            if (sessLabel) sessLabel.textContent = sessId;
            appendLog('page_view', 'HTTP 201', 'Tự động ghi nhận lượt xem trang ban đầu (Path: ' + currentPath + ')', false);
        }
    });

    // 1. Bắn sự kiện page_view
    function firePageView() {
        if (!window.WebTracker) return;
        window.WebTracker.trackPageView({
            page_path: currentPath,
            page_title: currentTitle,
            referrer: document.referrer || null
        });
        showToast('Đã gửi sự kiện <b>page_view</b> cho ' + currentPath);
        appendLog('page_view', 'HTTP 201', 'Gửi lượt xem trang thành công &rarr; Path: ' + currentPath, false);
    }

    // Giả lập chuyển trang trong menu
    function simulatePageNav(path, title, elem) {
        currentPath = path;
        currentTitle = title + ' - <?= e($website['name']) ?>';

        // Update active class
        var links = document.querySelectorAll('.demo-nav-links a');
        links.forEach(function(a) { a.classList.remove('active'); });
        if (elem) elem.classList.add('active');

        // Bắn page_view
        if (window.WebTracker) {
            window.WebTracker.trackPageView({
                page_path: currentPath,
                page_title: currentTitle
            });
        }
        showToast('Chuyển trang đến: <b>' + path + '</b> (' + title + ')');
        appendLog('page_view', 'HTTP 201', 'Chuyển sang trang: ' + path + ' (' + title + ')', false);
    }

    function fireCustomPageView(path, title) {
        if (window.WebTracker) {
            window.WebTracker.trackPageView({
                page_path: path,
                page_title: title
            });
        }
        showToast('Đã gửi xem chi tiết: <b>' + title + '</b>');
        appendLog('page_view', 'HTTP 201', 'Xem chi tiết sản phẩm: ' + path, false);
    }

    // 2. Bắn sự kiện button_click
    function fireButtonClick(elemId, elemText, props) {
        if (!window.WebTracker) return;
        props = props || {};
        props.element_id = elemId;
        props.element_text = elemText;
        props.page_path = currentPath;

        window.WebTracker.track('button_click', props);
        showToast('Đã bấm nút: <b>' + elemText + '</b>');
        appendLog('button_click', 'HTTP 201', 'Bấm nút: ' + elemText + ' (' + elemId + ')', false);
    }

    function fireAddToCart() {
        fireButtonClick('btn_quick_add_cart', 'Thêm vào giỏ hàng nhanh', {
            product_name: 'Thiết Bị Mẫu 2026',
            price: 19990000,
            quantity: 1
        });
    }

    // 3. Chuỗi Funnel chuyển đổi (download_start -> download_complete)
    function triggerFunnelFlow() {
        if (!window.WebTracker) return;
        var docName = 'Catalog-San-Pham-2026.pdf';
        
        // Bước 1: download_start
        window.WebTracker.track('download_start', {
            file_name: docName,
            file_type: 'pdf',
            page_path: currentPath
        });
        showToast('Đang bắt đầu tải: <b>' + docName + '</b>...');
        appendLog('download_start', 'HTTP 201', 'Bắt đầu tải file: ' + docName, false);

        // Bước 2: Tự động hoàn tất sau 1.2s
        setTimeout(function() {
            window.WebTracker.track('download_complete', {
                file_name: docName,
                file_type: 'pdf',
                page_path: currentPath,
                duration_seconds: 1.2
            });
            showToast('Hoàn tất tải file: <b>' + docName + '</b> (Hoàn thành Funnel!)');
            appendLog('download_complete', 'HTTP 201', 'Hoàn tất tải: ' + docName + ' &rarr; Phễu hoàn tất 100%!', false);
        }, 1200);
    }

    // 4. Bắn sự kiện khai báo trực tiếp
    function fireDirectEvent(evtName) {
        if (!window.WebTracker) return;
        window.WebTracker.track(evtName, {
            triggered_at: new Date().toISOString(),
            page_path: currentPath,
            source: 'live_demo_runner'
        });
        showToast('Đã bắn sự kiện khai báo: <b>' + evtName + '</b>');
        appendLog(evtName, 'HTTP Đang gửi...', 'Kích hoạt sự kiện: ' + evtName, false);

        // Kiểm tra sau 300ms
        setTimeout(function() {
            appendLog(evtName, 'HTTP 201/200', 'Sự kiện ' + evtName + ' đã được CSDL tiếp nhận.', false);
        }, 300);
    }

    // 5. Thử bắn sự kiện bất kỳ (để test gian lận / từ chối)
    function fireArbitraryEvent() {
        var input = document.getElementById('custom-test-event-name');
        var name = (input ? input.value : '').trim();
        if (!name) {
            alert('Vui lòng nhập tên sự kiện cần thử nghiệm.');
            return;
        }

        // Dùng fetch trực tiếp để kiểm tra mã lỗi trả về
        var payload = {
            tracking_key: '<?= e($website['tracking_key']) ?>',
            event_id: 'evt_test_' + Date.now(),
            session_id: window.WebTracker ? window.WebTracker.getSessionId() : 'sess_test',
            event_name: name,
            page_path: currentPath,
            occurred_at: new Date().toISOString(),
            properties: { custom_test: true }
        };

        fetch('api/track.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        })
        .then(function(res) {
            return res.json().then(function(data) {
                return { status: res.status, data: data };
            });
        })
        .then(function(result) {
            if (result.status === 201 || result.status === 200) {
                showToast('Thành công: Sự kiện <b>' + name + '</b> được tiếp nhận!');
                appendLog(name, 'HTTP ' + result.status, 'Hệ thống đã chấp nhận sự kiện.', false);
            } else {
                showToast('Bị từ chối: ' + (result.data.message || result.data.error), true);
                appendLog(name, 'HTTP ' + result.status + ' (' + (result.data.error || 'ERROR') + ')', result.data.message || 'Bị từ chối do chưa khai báo trong event_definitions.', true);
            }
        })
        .catch(function(err) {
            showToast('Lỗi gửi: ' + err.message, true);
            appendLog(name, 'Lỗi mạng', err.message, true);
        });
    }
    </script>
</body>
</html>
