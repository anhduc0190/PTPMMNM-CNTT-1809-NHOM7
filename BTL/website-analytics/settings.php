<?php
// ====================================================================
// CAI DAT HE THONG & QUAN LY DU LIEU (settings.php) - ADMIN ONLY
// ====================================================================

$pageTitle = 'Cài Đặt Hệ Thống & Quản Lý Dữ Liệu';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/csrf.php';

require_admin();

$pdo = get_db_connection();
$notice = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf_token();
    $action = $_POST['action'] ?? '';

    // Xoa sach du lieu su kien va loi tiep nhan (Giu nguyen websites, users va event_definitions)
    if ($action === 'clear_events') {
        $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
        $pdo->exec('TRUNCATE TABLE `events`');
        $pdo->exec('TRUNCATE TABLE `sessions`');
        $pdo->exec('TRUNCATE TABLE `event_ingest_errors`');
        $pdo->exec('TRUNCATE TABLE `report_queries`');
        $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
        $notice = ['type' => 'success', 'msg' => 'Đã xóa toàn bộ dữ liệu sự kiện, phiên truy cập và nhật ký lỗi thành công.'];
    }

    // Khoi phuc lai seed goc
    if ($action === 'reset_seed') {
        $seedPath = __DIR__ . '/database/seed.sql';
        if (file_exists($seedPath)) {
            $sql = file_get_contents($seedPath);
            $pdo->exec($sql);
            $notice = ['type' => 'success', 'msg' => 'Đã nạp lại dữ liệu Seed mặc định thành công.'];
        }
    }
}

// Lay thong ke bang hien tai
$stats = [];
foreach (['websites', 'users', 'event_definitions', 'sessions', 'events', 'event_ingest_errors', 'report_queries'] as $tbl) {
    $stats[$tbl] = (int)$pdo->query("SELECT COUNT(*) FROM `{$tbl}`")->fetchColumn();
}
?>

<?php if ($notice): ?>
    <div style="background:<?= $notice['type'] === 'success' ? '#ecfdf5' : '#fef2f2' ?>; border:1px solid <?= $notice['type'] === 'success' ? '#a7f3d0' : '#fecaca' ?>; color:<?= $notice['type'] === 'success' ? '#065f46' : '#b91c1c' ?>; padding:0.85rem 1.25rem; border-radius:8px; margin-bottom:1.5rem; font-weight:600;">
        <?= e($notice['msg']) ?>
    </div>
<?php endif; ?>

<div class="card-section">
    <div class="card-title-bar">
        <h3>ℹ️ Thông Tin Môi Trường Máy Chủ (XAMPP Windows)</h3>
    </div>

    <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap:1.25rem;">
        <div style="padding:1rem; background:#f8fafc; border:1px solid var(--border-color); border-radius:8px;">
            <div style="color:var(--text-muted); font-size:0.8rem; font-weight:600;">PHIÊN BẢN PHP</div>
            <div style="font-size:1.15rem; font-weight:700; color:var(--primary);"><?= phpversion() ?></div>
            <div style="font-size:0.75rem; color:#64748b;">Zend Engine v<?= zend_version() ?></div>
        </div>

        <div style="padding:1rem; background:#f8fafc; border:1px solid var(--border-color); border-radius:8px;">
            <div style="color:var(--text-muted); font-size:0.8rem; font-weight:600;">DATABASE SERVER</div>
            <div style="font-size:1.15rem; font-weight:700; color:#10b981;"><?= $pdo->getAttribute(PDO::ATTR_SERVER_VERSION) ?></div>
            <div style="font-size:0.75rem; color:#64748b;">Cổng kết nối 3306</div>
        </div>

        <div style="padding:1rem; background:#f8fafc; border:1px solid var(--border-color); border-radius:8px;">
            <div style="color:var(--text-muted); font-size:0.8rem; font-weight:600;">MÚI GIỜ HỆ THỐNG</div>
            <div style="font-size:1.15rem; font-weight:700; color:#6366f1;"><?= date_default_timezone_get() ?></div>
            <div style="font-size:0.75rem; color:#64748b;">Thời gian: <?= date('d/m/Y H:i:s') ?></div>
        </div>

        <div style="padding:1rem; background:#f8fafc; border:1px solid var(--border-color); border-radius:8px;">
            <div style="color:var(--text-muted); font-size:0.8rem; font-weight:600;">HẠN SỰ KIỆN TRỄ (MẶC ĐỊNH)</div>
            <div style="font-size:1.15rem; font-weight:700; color:#f59e0b;"><?= DEFAULT_LATE_WINDOW_HOURS ?> Giờ</div>
            <div style="font-size:0.75rem; color:#64748b;">Từ chối nếu xảy ra > 24h</div>
        </div>
    </div>
</div>

<!-- THONG KE SO BAN GHI TRONG DATABASE -->
<div class="card-section">
    <div class="card-title-bar">
        <h3>🗄️ Thống Kê Số Bản Ghi Trong 8 Bảng Cơ Sở Dữ Liệu</h3>
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
                <tr><td><code>event_ingest_errors</code></td><td>Nhật ký lỗi tiếp nhận & xung đột</td><td><strong style="color:var(--danger);"><?= number_format($stats['event_ingest_errors']) ?></strong></td></tr>
                <tr><td><code>report_queries</code></td><td>Nhật ký audit truy vấn báo cáo</td><td><strong><?= number_format($stats['report_queries']) ?></strong></td></tr>
            </tbody>
        </table>
    </div>
</div>

<!-- CHINH SACH XOA DU LIEU THU NGHIEM -->
<div class="card-section" style="border:1px solid #fee2e2;">
    <div class="card-title-bar">
        <h3 style="color:#b91c1c;">🧹 Chính Sách Lưu Trữ & Xóa Dữ Liệu Thử Nghiệm</h3>
    </div>
    <p style="color:var(--text-secondary); font-size:0.9rem; margin-bottom:1.5rem; line-height:1.6;">
        Theo yêu cầu của đề tài, các dữ liệu kiểm thử (test events/sessions) cần có khả năng dọn dẹp hoặc khởi tạo lại bất kỳ lúc nào mà không làm ảnh hưởng đến cấu trúc bảng và tài khoản người dùng:
    </p>

    <div style="display:flex; gap:1rem; flex-wrap:wrap;">
        <form method="POST" onsubmit="return confirm('Bạn có chắc chắn muốn XÓA TOÀN BỘ dữ liệu sự kiện và phiên truy cập thử nghiệm không? Thao tác này không thể hoàn tác.');">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="clear_events">
            <button type="submit" class="btn btn-danger">
                🗑️ Xóa Sạch Dữ Liệu Sự Kiện (Events & Errors)
            </button>
        </form>

        <form method="POST" onsubmit="return confirm('Nạp lại dữ liệu Seed mặc định?');">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="reset_seed">
            <button type="submit" class="btn btn-outline" style="border-color:#cbd5e1;">
                🔄 Khôi Phục Dữ Liệu Seed Ban Đầu
            </button>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
