<?php
// ====================================================================
// CÀI ĐẶT HỆ THỐNG & QUẢN LÝ DỮ LIỆU (settings.php) - ADMIN ONLY
// Thống kê server, 8 bảng CSDL và bảo trì dữ liệu thử nghiệm (SaaS)
// ====================================================================

$pageTitle = 'Cài Đặt Hệ Thống & Quản Lý Dữ Liệu';
require_once __DIR__ . '/includes/auth.php';
require_admin();
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/csrf.php';

$pdo = get_db_connection();
$notice = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf_token();
    $action = $_POST['action'] ?? '';

    // Xóa sạch dữ liệu sự kiện và lỗi tiếp nhận
    if ($action === 'clear_events') {
        $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
        $pdo->exec('TRUNCATE TABLE `events`');
        $pdo->exec('TRUNCATE TABLE `sessions`');
        $pdo->exec('TRUNCATE TABLE `event_ingest_errors`');
        $pdo->exec('TRUNCATE TABLE `report_queries`');
        $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
        $notice = ['type' => 'success', 'msg' => 'Đã xóa toàn bộ dữ liệu sự kiện, phiên truy cập và nhật ký lỗi thành công.'];
    }

    // Khôi phục lại seed gốc
    if ($action === 'reset_seed') {
        $seedPath = __DIR__ . '/database/seed.sql';
        if (file_exists($seedPath)) {
            $sql = file_get_contents($seedPath);
            $pdo->exec($sql);
            $notice = ['type' => 'success', 'msg' => 'Đã nạp lại dữ liệu Seed mặc định thành công.'];
        }
    }
}

// Lấy thống kê bảng hiện tại
$stats = [];
foreach (['websites', 'users', 'event_definitions', 'sessions', 'events', 'event_ingest_errors', 'report_queries'] as $tbl) {
    $stats[$tbl] = (int)$pdo->query("SELECT COUNT(*) FROM `{$tbl}`")->fetchColumn();
}
?>

<?php if ($notice): ?>
    <div style="background:<?= $notice['type'] === 'success' ? 'var(--accent-green-bg)' : 'var(--accent-red-bg)' ?>; border:1px solid <?= $notice['type'] === 'success' ? 'var(--accent-green-border)' : 'var(--accent-red-border)' ?>; color:<?= $notice['type'] === 'success' ? 'var(--accent-green-text)' : 'var(--accent-red-text)' ?>; padding:0.85rem 1.25rem; border-radius:var(--radius-md); margin-bottom:1.5rem; font-weight:600; display:flex; align-items:center; gap:0.5rem;">
        <?= render_icon($notice['type'] === 'success' ? 'check-circle' : 'alert-triangle', '', 16) ?>
        <?= e($notice['msg']) ?>
    </div>
<?php endif; ?>

<!-- THÔNG TIN MÔI TRƯỜNG MÁY CHỦ -->
<div class="card-section">
    <div class="card-title-bar">
        <h3>
            <?= render_icon('server', '', 18, 'var(--primary)') ?>
            Thông Tin Môi Trường Máy Chủ (XAMPP Windows)
        </h3>
        <span class="badge badge-green">
            <?= render_icon('check-circle', '', 12) ?>
            Đang hoạt động bình thường
        </span>
    </div>

    <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap:1rem;">
        <div style="padding:1.15rem; background:#ffffff; border:1px solid var(--border-color); border-radius:var(--radius-md); box-shadow:var(--shadow-xs);">
            <div style="color:var(--text-muted); font-size:0.75rem; font-weight:700; text-transform:uppercase; letter-spacing:0.04em;">PHIÊN BẢN PHP</div>
            <div style="font-size:1.35rem; font-weight:800; color:var(--primary); margin:0.35rem 0 0.15rem 0;"><?= phpversion() ?></div>
            <div style="font-size:0.76rem; color:var(--text-secondary);">Zend Engine v<?= zend_version() ?></div>
        </div>

        <div style="padding:1.15rem; background:#ffffff; border:1px solid var(--border-color); border-radius:var(--radius-md); box-shadow:var(--shadow-xs);">
            <div style="color:var(--text-muted); font-size:0.75rem; font-weight:700; text-transform:uppercase; letter-spacing:0.04em;">DATABASE SERVER</div>
            <div style="font-size:1.35rem; font-weight:800; color:var(--accent-green); margin:0.35rem 0 0.15rem 0;"><?= $pdo->getAttribute(PDO::ATTR_SERVER_VERSION) ?></div>
            <div style="font-size:0.76rem; color:var(--text-secondary);">MariaDB / Cổng kết nối 3306</div>
        </div>

        <div style="padding:1.15rem; background:#ffffff; border:1px solid var(--border-color); border-radius:var(--radius-md); box-shadow:var(--shadow-xs);">
            <div style="color:var(--text-muted); font-size:0.75rem; font-weight:700; text-transform:uppercase; letter-spacing:0.04em;">MÚI GIỜ HỆ THỐNG</div>
            <div style="font-size:1.35rem; font-weight:800; color:var(--accent-purple); margin:0.35rem 0 0.15rem 0;"><?= date_default_timezone_get() ?></div>
            <div style="font-size:0.76rem; color:var(--text-secondary);">Thời gian: <?= date('d/m/Y H:i:s') ?></div>
        </div>

        <div style="padding:1.15rem; background:#ffffff; border:1px solid var(--border-color); border-radius:var(--radius-md); box-shadow:var(--shadow-xs);">
            <div style="color:var(--text-muted); font-size:0.75rem; font-weight:700; text-transform:uppercase; letter-spacing:0.04em;">HẠN SỰ KIỆN TRỄ (MẶC ĐỊNH)</div>
            <div style="font-size:1.35rem; font-weight:800; color:var(--accent-yellow); margin:0.35rem 0 0.15rem 0;"><?= DEFAULT_LATE_WINDOW_HOURS ?> Giờ</div>
            <div style="font-size:0.76rem; color:var(--text-secondary);">Từ chối nếu xảy ra > 24h</div>
        </div>
    </div>
</div>

<!-- THỐNG KÊ SỐ BẢN GHI TRONG DATABASE -->
<div class="card-section">
    <div class="card-title-bar">
        <h3>
            <?= render_icon('database', '', 18, 'var(--primary)') ?>
            Thống Kê Số Bản Ghi Trong 8 Bảng Cơ Sở Dữ Liệu
        </h3>
        <span class="badge badge-blue">Engine InnoDB / UTF8MB4</span>
    </div>
    <div class="table-responsive">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Tên Bảng CSDL</th>
                    <th>Ý Nghĩa Lưu Trữ</th>
                    <th>Số Lượng Bản Ghi Hiện Tại</th>
                </tr>
            </thead>
            <tbody>
                <tr><td><code>websites</code></td><td>Danh sách website theo dõi</td><td><strong><?= number_format($stats['websites']) ?></strong></td></tr>
                <tr><td><code>users</code></td><td>Tài khoản quản trị và thành viên</td><td><strong><?= number_format($stats['users']) ?></strong></td></tr>
                <tr><td><code>event_definitions</code></td><td>Cấu hình sự kiện hợp lệ</td><td><strong><?= number_format($stats['event_definitions']) ?></strong></td></tr>
                <tr><td><code>sessions</code></td><td>Phiên truy cập ẩn danh</td><td><strong><?= number_format($stats['sessions']) ?></strong></td></tr>
                <tr><td><code>events</code></td><td>Sự kiện hợp lệ đã tiếp nhận</td><td><strong style="color:var(--primary);"><?= number_format($stats['events']) ?></strong></td></tr>
                <tr><td><code>event_ingest_errors</code></td><td>Nhật ký lỗi tiếp nhận & xung đột</td><td><strong style="color:var(--accent-red);"><?= number_format($stats['event_ingest_errors']) ?></strong></td></tr>
                <tr><td><code>report_queries</code></td><td>Nhật ký audit truy vấn báo cáo</td><td><strong><?= number_format($stats['report_queries']) ?></strong></td></tr>
            </tbody>
        </table>
    </div>
</div>

<!-- CHÍNH SÁCH XÓA DỮ LIỆU THỬ NGHIỆM -->
<div class="card-section" style="border:1px solid var(--accent-red-border);">
    <div class="card-title-bar">
        <h3 style="color:var(--accent-red-text);">
            <?= render_icon('trash-2', '', 18, 'var(--accent-red)') ?>
            Chính Sách Lưu Trữ & Xóa Dữ Liệu Thử Nghiệm
        </h3>
        <span class="badge badge-red">Danger Zone</span>
    </div>
    <p style="color:var(--text-secondary); font-size:0.88rem; margin-bottom:1.5rem; line-height:1.6;">
        Theo yêu cầu của đề tài, các dữ liệu kiểm thử (test events/sessions) cần có khả năng dọn dẹp hoặc khởi tạo lại bất kỳ lúc nào mà không làm ảnh hưởng đến cấu trúc bảng và tài khoản người dùng:
    </p>

    <div style="display:flex; gap:0.85rem; flex-wrap:wrap;">
        <form method="POST" onsubmit="return confirm('Bạn có chắc chắn muốn XÓA TOÀN BỘ dữ liệu sự kiện và phiên truy cập thử nghiệm không? Thao tác này không thể hoàn tác.');">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="clear_events">
            <button type="submit" class="btn btn-danger">
                <?= render_icon('trash-2', '', 14) ?>
                Xóa Sạch Dữ Liệu Sự Kiện (Events & Errors)
            </button>
        </form>

        <form method="POST" onsubmit="return confirm('Nạp lại dữ liệu Seed mặc định?');">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="reset_seed">
            <button type="submit" class="btn btn-outline">
                <?= render_icon('refresh-cw', '', 14) ?>
                Khôi Phục Dữ Liệu Seed Ban Đầu
            </button>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
