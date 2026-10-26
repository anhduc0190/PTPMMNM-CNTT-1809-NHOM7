<?php
// ====================================================================
// CAU HINH DINH NGHIA SU KIEN (event-definitions.php)
// Bat/Tat su kien hop le cho tung website
// ====================================================================

$pageTitle = 'Cấu Hình Định Nghĩa Sự Kiện';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/csrf.php';

$pdo = get_db_connection();
$canManage = user_can_access_website($selectedWebsiteId, 'manage_tracking');
$notice = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $canManage) {
    require_csrf_token();
    $action = $_POST['action'] ?? '';

    if ($action === 'toggle') {
        $defId   = (int)($_POST['def_id'] ?? 0);
        $enabled = (int)($_POST['enabled'] ?? 1);

        $stmt = $pdo->prepare('UPDATE `event_definitions` SET `enabled` = ? WHERE `definition_id` = ? AND `website_id` = ?');
        $stmt->execute([$enabled ? 1 : 0, $defId, $selectedWebsiteId]);
        $notice = ['type' => 'success', 'msg' => 'Đã cập nhật trạng thái sự kiện thành công.'];
    }

    if ($action === 'create') {
        $name = trim($_POST['event_name'] ?? '');
        $desc = trim($_POST['description'] ?? '');

        if (preg_match('/^[a-z0-9_]{2,50}$/', $name)) {
            try {
                $stmt = $pdo->prepare('INSERT INTO `event_definitions` (`website_id`, `event_name`, `description`, `enabled`, `created_at`) VALUES (?, ?, ?, 1, NOW())');
                $stmt->execute([$selectedWebsiteId, $name, $desc]);
                $notice = ['type' => 'success', 'msg' => "Đã thêm định nghĩa sự kiện '{$name}' mới."];
            } catch (PDOException $e) {
                $notice = ['type' => 'error', 'msg' => 'Sự kiện này đã tồn tại trên website.'];
            }
        } else {
            $notice = ['type' => 'error', 'msg' => 'Tên sự kiện chỉ bao gồm chữ thường, số và dấu gạch dưới (2-50 ký tự).'];
        }
    }
}

// Lay danh sach dinh nghia su kien cua website dang chon
$stmt = $pdo->prepare('
    SELECT `definition_id`, `event_name`, `description`, `enabled`, `created_at`
    FROM `event_definitions`
    WHERE `website_id` = ?
    ORDER BY `definition_id` ASC
');
$stmt->execute([$selectedWebsiteId]);
$definitions = $stmt->fetchAll();
?>

<?php if ($notice): ?>
    <div style="background:<?= $notice['type'] === 'success' ? '#ecfdf5' : '#fef2f2' ?>; border:1px solid <?= $notice['type'] === 'success' ? '#a7f3d0' : '#fecaca' ?>; color:<?= $notice['type'] === 'success' ? '#065f46' : '#b91c1c' ?>; padding:0.85rem 1.25rem; border-radius:8px; margin-bottom:1.5rem; font-weight:600;">
        <?= e($notice['msg']) ?>
    </div>
<?php endif; ?>

<div class="card-section">
    <div class="card-title-bar">
        <h3>⚙️ Danh Sách Sự Kiện Được Khai Báo (Website #<?= $selectedWebsiteId ?>)</h3>
        <?php if ($canManage): ?>
            <button class="btn btn-primary btn-sm" onclick="document.getElementById('modal-add-event').style.display = 'block'">
                ➕ Thêm Sự Kiện Mới
            </button>
        <?php endif; ?>
    </div>

    <p style="color:var(--text-secondary); font-size:0.9rem; margin-bottom:1.25rem;">
        Hệ thống chỉ tiếp nhận các sự kiện đã được khai báo và đang ở trạng thái <b>Bật (Enabled)</b>. Nếu sự kiện chưa khai báo hoặc bị Tắt, API sẽ tự động từ chối với mã <code>EVENT_NOT_DEFINED</code> và ghi log lỗi.
    </p>

    <div class="table-responsive">
        <table class="data-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Tên Sự Kiện (event_name)</th>
                    <th>Mô Tả Nghiệp Vụ</th>
                    <th>Ngày Khai Báo</th>
                    <th>Trạng Thái</th>
                    <?php if ($canManage): ?><th>Hành Động</th><?php endif; ?>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($definitions as $def): ?>
                    <tr>
                        <td><strong>#<?= $def['definition_id'] ?></strong></td>
                        <td>
                            <code><?= e($def['event_name']) ?></code>
                        </td>
                        <td><?= e($def['description'] ?? 'Không có mô tả') ?></td>
                        <td><?= date('d/m/Y H:i', strtotime($def['created_at'])) ?></td>
                        <td>
                            <?php if ($def['enabled']): ?>
                                <span class="badge badge-green">Đang Bật (Enabled)</span>
                            <?php else: ?>
                                <span class="badge badge-red">Đang Tắt (Disabled)</span>
                            <?php endif; ?>
                        </td>
                        <?php if ($canManage): ?>
                            <td>
                                <form method="POST" style="display:inline;">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="toggle">
                                    <input type="hidden" name="def_id" value="<?= $def['definition_id'] ?>">
                                    <?php if ($def['enabled']): ?>
                                        <input type="hidden" name="enabled" value="0">
                                        <button type="submit" class="btn btn-outline btn-sm" style="color:var(--danger); border-color:#fee2e2;">
                                            Vô hiệu hoá
                                        </button>
                                    <?php else: ?>
                                        <input type="hidden" name="enabled" value="1">
                                        <button type="submit" class="btn btn-outline btn-sm" style="color:var(--accent); border-color:#a7f3d0;">
                                            Kích hoạt
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

<!-- MODAL THEM SU KIEN MOI -->
<?php if ($canManage): ?>
<div id="modal-add-event" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); z-index:9999; align-items:center; justify-content:center;">
    <div style="background:#fff; border-radius:12px; width:100%; max-width:480px; margin:7% auto; padding:2rem; box-shadow:var(--shadow-lg);">
        <h3 style="margin-bottom:1.25rem;">Thêm Định Nghĩa Sự Kiện Mới</h3>
        <form method="POST">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="create">
            
            <div style="margin-bottom:1rem;">
                <label style="display:block; font-weight:600; margin-bottom:0.3rem;">Tên Sự Kiện (event_name)</label>
                <input type="text" name="event_name" class="form-input" style="width:100%;" placeholder="ví dụ: form_submit, video_play" required>
                <span style="font-size:0.75rem; color:#64748b;">Chữ thường, số và gạch dưới (2-50 ký tự)</span>
            </div>

            <div style="margin-bottom:1.5rem;">
                <label style="display:block; font-weight:600; margin-bottom:0.3rem;">Mô Tả</label>
                <textarea name="description" class="form-input" style="width:100%; height:80px;" placeholder="Mô tả ý nghĩa của sự kiện này..."></textarea>
            </div>

            <div style="display:flex; justify-content:flex-end; gap:0.8rem;">
                <button type="button" class="btn btn-outline" onclick="document.getElementById('modal-add-event').style.display = 'none'">Hủy</button>
                <button type="submit" class="btn btn-primary">Lưu Sự Kiện</button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
