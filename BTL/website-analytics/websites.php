<?php
// ====================================================================
// QUAN LY WEBSITES (websites.php)
// Xem danh sach, dang ky website moi, lay ma tracking key
// ====================================================================

$pageTitle = 'Quản Lý Website Theo Dõi';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/csrf.php';

$isAdmin = is_admin();
$pdo = get_db_connection();
$notice = null;

// Xu ly them website moi
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

            // Tao 4 dinh nghia su kien mac dinh
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
            // Reload danh sach
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
        $notice = ['type' => 'success', 'msg' => 'Đã cập nhật trạng thái website.'];
        $accessibleWebsites = get_accessible_websites();
    }
}
?>

<?php if ($notice): ?>
    <div style="background:<?= $notice['type'] === 'success' ? '#ecfdf5' : '#fef2f2' ?>; border:1px solid <?= $notice['type'] === 'success' ? '#a7f3d0' : '#fecaca' ?>; color:<?= $notice['type'] === 'success' ? '#065f46' : '#b91c1c' ?>; padding:0.85rem 1.25rem; border-radius:8px; margin-bottom:1.5rem; font-weight:600;">
        <?= e($notice['msg']) ?>
    </div>
<?php endif; ?>

<div class="card-section">
    <div class="card-title-bar">
        <h3>🌐 Danh Sách Website Được Quản Lý</h3>
        <?php if ($isAdmin): ?>
            <button class="btn btn-primary btn-sm" onclick="document.getElementById('modal-create').style.display = 'block'">
                ➕ Thêm Website Mới
            </button>
        <?php endif; ?>
    </div>

    <div class="table-responsive">
        <table class="data-table">
            <thead>
                <tr>
                    <th>ID</th>
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
                                <button type="button" class="btn btn-outline btn-sm" style="padding:0.2rem 0.4rem;" onclick="copyToClipboard('<?= e($site['tracking_key']) ?>')">
                                    📋
                                </button>
                            </div>
                        </td>
                        <td><?= e($site['timezone']) ?></td>
                        <td><?= (int)$site['late_event_window_hours'] ?> giờ</td>
                        <td>
                            <?php if ($site['status'] === 'active'): ?>
                                <span class="badge badge-green">Đang chạy (Active)</span>
                            <?php elseif ($site['status'] === 'paused'): ?>
                                <span class="badge badge-yellow">Tạm dừng (Paused)</span>
                            <?php else: ?>
                                <span class="badge badge-red">Lưu trữ (Archived)</span>
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
                                        <button type="submit" class="btn btn-outline btn-sm" style="color:var(--warning); border-color:#fde68a;">
                                            ⏸️ Tạm Dừng
                                        </button>
                                    <?php else: ?>
                                        <input type="hidden" name="new_status" value="active">
                                        <button type="submit" class="btn btn-outline btn-sm" style="color:var(--accent); border-color:#a7f3d0;">
                                            ▶️ Kích Hoạt
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

<!-- MA HUONG DAN GẮN TRACKING SDK -->
<div class="card-section">
    <div class="card-title-bar">
        <h3>💻 Hướng Dẫn Tích Hợp JavaScript SDK Vào Website</h3>
    </div>
    <p style="color:var(--text-secondary); margin-bottom:1rem; font-size:0.9rem;">
        Sao chép đoạn mã sau và dán vào phần trước thẻ đóng <code>&lt;/body&gt;</code> của trang web bạn muốn theo dõi:
    </p>
    <pre style="background:#0f172a; color:#e2e8f0; padding:1.2rem; border-radius:8px; font-size:0.85rem; overflow-x:auto;">
&lt;!-- WEB TRACKING SDK ĐỀ TÀI 17 --&gt;
&lt;script src="<?= $baseUrl ?>/sdk/tracker.js"
        data-tracking-key="&lt;YOUR_TRACKING_KEY&gt;"
        data-api-endpoint="<?= $baseUrl ?>/api/track.php"&gt;
&lt;/script&gt;
    </pre>
</div>

<!-- MODAL THEM WEBSITE -->
<?php if ($isAdmin): ?>
<div id="modal-create" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); z-index:9999; align-items:center; justify-content:center;">
    <div style="background:#fff; border-radius:12px; width:100%; max-width:500px; margin:5% auto; padding:2rem; box-shadow:var(--shadow-lg);">
        <h3 style="margin-bottom:1.25rem;">Thêm Website Mới Cần Theo Dõi</h3>
        <form method="POST">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="create">
            
            <div style="margin-bottom:1rem;">
                <label style="display:block; font-weight:600; margin-bottom:0.3rem;">Tên Website</label>
                <input type="text" name="name" class="form-input" style="width:100%;" placeholder="Ví dụ: Cửa hàng Chi nhánh 2" required>
            </div>

            <div style="margin-bottom:1rem;">
                <label style="display:block; font-weight:600; margin-bottom:0.3rem;">Tên Miền / Hostname</label>
                <input type="text" name="domain" class="form-input" style="width:100%;" placeholder="Ví dụ: localhost hoặc store.example.com" required>
            </div>

            <div style="margin-bottom:1rem;">
                <label style="display:block; font-weight:600; margin-bottom:0.3rem;">Múi Giờ</label>
                <select name="timezone" class="form-select" style="width:100%;">
                    <option value="Asia/Ho_Chi_Minh" selected>Asia/Ho_Chi_Minh (GMT+7)</option>
                    <option value="UTC">UTC (GMT+0)</option>
                </select>
            </div>

            <div style="margin-bottom:1.5rem;">
                <label style="display:block; font-weight:600; margin-bottom:0.3rem;">Hạn Chấp Nhận Sự Kiện Trễ (Giờ)</label>
                <input type="number" name="late_window" class="form-input" style="width:100%;" value="24" min="1" max="168" required>
            </div>

            <div style="display:flex; justify-content:flex-end; gap:0.8rem;">
                <button type="button" class="btn btn-outline" onclick="document.getElementById('modal-create').style.display = 'none'">Hủy</button>
                <button type="submit" class="btn btn-primary">Lưu Website</button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<script>
function copyToClipboard(text) {
    navigator.clipboard.writeText(text).then(function() {
        showToast('Đã sao chép Khoá Tracking vào bộ nhớ tạm: ' + text);
    });
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
