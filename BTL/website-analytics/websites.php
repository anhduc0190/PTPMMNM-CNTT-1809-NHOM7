<?php
// ====================================================================
// QUẢN LÝ WEBSITES (websites.php)
// Xem danh sách, đăng ký website mới, lấy mã tracking key (Enterprise SaaS)
// ====================================================================

$pageTitle = 'Quản Lý Website Theo Dõi';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/csrf.php';

$isAdmin = is_admin();
$pdo = get_db_connection();
$notice = null;

// Xử lý thêm website mới
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $isAdmin) {
    require_csrf_token();
    $action = $_POST['action'] ?? '';

    if ($action === 'create') {
        $name     = trim($_POST['name'] ?? '');
        $domain   = trim($_POST['domain'] ?? '');
        $timezone = trim($_POST['timezone'] ?? 'Asia/Ho_Chi_Minh');
        $window   = (int)($_POST['late_window'] ?? 24);

        if (!empty($name) && !empty($domain)) {
            $trackingKey = 'trk_' . bin2hex(random_bytes(16));
            $stmt = $pdo->prepare('
                INSERT INTO `websites` (`name`, `domain`, `tracking_key`, `timezone`, `status`, `late_event_window_hours`, `created_at`)
                VALUES (?, ?, ?, ?, "active", ?, NOW())
            ');
            $stmt->execute([$name, $domain, $trackingKey, $timezone, $window]);
            $newId = (int)$pdo->lastInsertId();

            // Tạo 4 định nghĩa sự kiện mặc định
            $standardEvents = [
                'page_view'         => 'Ghi nhận lượt xem trang tự động',
                'button_click'      => 'Thao tác bấm nút hoặc liên kết hành động',
                'download_start'    => 'Bắt đầu tải tài liệu hoặc file',
                'download_complete' => 'Hoàn tất tải file thành công'
            ];
            $stmtDef = $pdo->prepare('INSERT INTO `event_definitions` (`website_id`, `event_name`, `description`, `enabled`, `created_at`) VALUES (?, ?, ?, 1, NOW())');
            foreach ($standardEvents as $evtName => $desc) {
                $stmtDef->execute([$newId, $evtName, $desc]);
            }

            $notice = ['type' => 'success', 'msg' => "Website '{$name}' đã được thêm thành công với Khoá Tracking: {$trackingKey}"];
            $accessibleWebsites = get_accessible_websites();
        } else {
            $notice = ['type' => 'error', 'msg' => 'Vui lòng điền đầy đủ Tên Website và Tên Miền.'];
        }
    }

    if ($action === 'toggle_status') {
        $siteId = (int)($_POST['site_id'] ?? 0);
        $newStatus = $_POST['new_status'] ?? 'active';
        $stmt = $pdo->prepare('UPDATE `websites` SET `status` = ? WHERE `website_id` = ?');
        $stmt->execute([$newStatus, $siteId]);
        $notice = ['type' => 'success', 'msg' => 'Đã cập nhật trạng thái website thành công.'];
        $accessibleWebsites = get_accessible_websites();
    }
}
?>

<?php if ($notice): ?>
    <div style="background:<?= $notice['type'] === 'success' ? 'var(--accent-green-bg)' : 'var(--accent-red-bg)' ?>; border:1px solid <?= $notice['type'] === 'success' ? 'var(--accent-green-border)' : 'var(--accent-red-border)' ?>; color:<?= $notice['type'] === 'success' ? 'var(--accent-green-text)' : 'var(--accent-red-text)' ?>; padding:0.85rem 1.25rem; border-radius:var(--radius-md); margin-bottom:1.5rem; font-weight:600; display:flex; align-items:center; gap:0.5rem;">
        <?= render_icon($notice['type'] === 'success' ? 'check-circle' : 'alert-triangle', '', 16) ?>
        <?= e($notice['msg']) ?>
    </div>
<?php endif; ?>

<div class="card-section">
    <div class="card-title-bar">
        <h3>
            <?= render_icon('globe', '', 18, 'var(--primary)') ?>
            Danh Sách Website Được Quản Lý
        </h3>
        <?php if ($isAdmin): ?>
            <button class="btn btn-primary btn-sm" onclick="openModal('modal-create')">
                <?= render_icon('plus', '', 14) ?>
                Thêm Website Mới
            </button>
        <?php endif; ?>
    </div>

    <div class="table-responsive">
        <table class="data-table">
            <thead>
                <tr>
                    <th style="width:70px;">ID</th>
                    <th>Tên Website</th>
                    <th>Tên Miền / Origin</th>
                    <th>Khoá Tracking Key (Công Khai)</th>
                    <th>Múi Giờ</th>
                    <th>Hạn Sự Kiện Trễ</th>
                    <th>Trạng Thái</th>
                    <?php if ($isAdmin): ?><th>Thao Tác</th><?php endif; ?>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($accessibleWebsites as $site): ?>
                    <tr>
                        <td><strong>#<?= $site['website_id'] ?></strong></td>
                        <td>
                            <strong><?= e($site['name']) ?></strong>
                        </td>
                        <td><code><?= e($site['domain']) ?></code></td>
                        <td>
                            <div style="display:flex; align-items:center; gap:0.4rem;">
                                <code style="font-size:0.8rem;"><?= e($site['tracking_key']) ?></code>
                                <button type="button" class="btn btn-outline btn-sm" style="padding:0.2rem 0.45rem; height:24px;" onclick="copyToClipboard('<?= e($site['tracking_key']) ?>')" title="Sao chép khóa tracking">
                                    <?= render_icon('copy', '', 12) ?>
                                </button>
                            </div>
                        </td>
                        <td><?= e($site['timezone']) ?></td>
                        <td><?= (int)$site['late_event_window_hours'] ?> giờ</td>
                        <td>
                            <?php if ($site['status'] === 'active'): ?>
                                <span class="badge badge-green">
                                    <?= render_icon('check-circle', '', 11) ?>
                                    Đang chạy (Active)
                                </span>
                            <?php elseif ($site['status'] === 'paused'): ?>
                                <span class="badge badge-yellow">
                                    <?= render_icon('pause', '', 11) ?>
                                    Tạm dừng (Paused)
                                </span>
                            <?php else: ?>
                                <span class="badge badge-red">
                                    <?= render_icon('x-circle', '', 11) ?>
                                    Lưu trữ (Archived)
                                </span>
                            <?php endif; ?>
                        </td>
                        <?php if ($isAdmin): ?>
                            <td>
                                <form method="POST" style="display:inline;">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="toggle_status">
                                    <input type="hidden" name="site_id" value="<?= $site['website_id'] ?>">
                                    <?php if ($site['status'] === 'active'): ?>
                                        <input type="hidden" name="new_status" value="paused">
                                        <button type="submit" class="btn btn-outline btn-sm" style="color:var(--accent-yellow-text); border-color:var(--accent-yellow-border);">
                                            <?= render_icon('pause', '', 11) ?>
                                            Tạm Dừng
                                        </button>
                                    <?php else: ?>
                                        <input type="hidden" name="new_status" value="active">
                                        <button type="submit" class="btn btn-outline btn-sm" style="color:var(--accent-green-text); border-color:var(--accent-green-border);">
                                            <?= render_icon('play', '', 11) ?>
                                            Kích Hoạt
                                        </button>
                                    <?php endif; ?>
                                </form>
                            </td>
                        <?php endif; ?>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- MÃ HƯỚNG DẪN GẮN TRACKING SDK -->
<div class="card-section">
    <div class="card-title-bar">
        <h3>
            <?= render_icon('code', '', 18, 'var(--primary)') ?>
            Hướng Dẫn Tích Hợp JavaScript SDK Vào Website
        </h3>
    </div>
    <p style="color:var(--text-secondary); margin-bottom:1rem; font-size:0.88rem; line-height:1.5;">
        Sao chép đoạn mã sau và dán vào phần trước thẻ đóng <code>&lt;/body&gt;</code> của trang web bạn muốn theo dõi:
    </p>
    <pre style="background:#0f172a; color:#e2e8f0; padding:1.2rem; border-radius:var(--radius-md); font-size:0.85rem; overflow-x:auto; border:1px solid #1e293b; line-height:1.6;">
&lt;!-- WEB TRACKING SDK ĐỀ TÀI 17 --&gt;
&lt;script src="<?= $baseUrl ?>/sdk/tracker.js"
        data-tracking-key="&lt;YOUR_TRACKING_KEY&gt;"
        data-api-endpoint="<?= $baseUrl ?>/api/track.php"&gt;
&lt;/script&gt;</pre>
</div>

<!-- MODAL THÊM WEBSITE -->
<?php if ($isAdmin): ?>
<div id="modal-create" class="modal-backdrop">
    <div class="modal-dialog">
        <div class="modal-header">
            <h3>
                <?= render_icon('globe', '', 18, 'var(--primary)') ?>
                Thêm Website Mới Cần Theo Dõi
            </h3>
            <button type="button" class="modal-close-btn" onclick="closeModal('modal-create')" aria-label="Đóng">
                <?= render_icon('x', '', 16) ?>
            </button>
        </div>
        <form method="POST">
            <div class="modal-body">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="create">
                
                <div style="margin-bottom:1rem;">
                    <label style="display:block; font-weight:600; margin-bottom:0.35rem; font-size:0.84rem;">Tên Website</label>
                    <input type="text" name="name" class="form-input" style="width:100%;" placeholder="Ví dụ: Cửa hàng Chi nhánh 2" required>
                </div>

                <div style="margin-bottom:1rem;">
                    <label style="display:block; font-weight:600; margin-bottom:0.35rem; font-size:0.84rem;">Tên Miền / Hostname</label>
                    <input type="text" name="domain" class="form-input" style="width:100%;" placeholder="Ví dụ: localhost hoặc store.example.com" required>
                </div>

                <div style="margin-bottom:1rem;">
                    <label style="display:block; font-weight:600; margin-bottom:0.35rem; font-size:0.84rem;">Múi Giờ</label>
                    <select name="timezone" class="form-select" style="width:100%;">
                        <option value="Asia/Ho_Chi_Minh" selected>Asia/Ho_Chi_Minh (GMT+7)</option>
                        <option value="UTC">UTC (GMT+0)</option>
                    </select>
                </div>

                <div>
                    <label style="display:block; font-weight:600; margin-bottom:0.35rem; font-size:0.84rem;">Hạn Chấp Nhận Sự Kiện Trễ (Giờ)</label>
                    <input type="number" name="late_window" class="form-input" style="width:100%;" value="24" min="1" max="168" required>
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeModal('modal-create')">Hủy</button>
                <button type="submit" class="btn btn-primary">
                    <?= render_icon('check', '', 14) ?>
                    Lưu Website
                </button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<script>
function openModal(id) {
    const m = document.getElementById(id);
    if (m) m.classList.add('active');
}
function closeModal(id) {
    const m = document.getElementById(id);
    if (m) m.classList.remove('active');
}
function copyToClipboard(text) {
    navigator.clipboard.writeText(text).then(function() {
        showToast('Đã sao chép Khoá Tracking vào bộ nhớ tạm: ' + text);
    });
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
