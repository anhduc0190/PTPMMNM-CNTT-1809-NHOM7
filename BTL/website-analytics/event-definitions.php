<?php
// ====================================================================
// CẤU HÌNH ĐỊNH NGHĨA SỰ KIỆN (event-definitions.php)
// Bật/Tắt sự kiện hợp lệ cho từng website (Enterprise SaaS)
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
                $notice = ['type' => 'success', 'msg' => "Đã thêm định nghĩa sự kiện '{$name}' mới thành công."];
            } catch (PDOException $e) {
                $notice = ['type' => 'error', 'msg' => 'Sự kiện này đã tồn tại trên website.'];
            }
        } else {
            $notice = ['type' => 'error', 'msg' => 'Tên sự kiện chỉ bao gồm chữ thường, số và dấu gạch dưới (2-50 ký tự).'];
        }
    }
}

// Lấy danh sách định nghĩa sự kiện của website đang chọn
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
    <div style="background:<?= $notice['type'] === 'success' ? 'var(--accent-green-bg)' : 'var(--accent-red-bg)' ?>; border:1px solid <?= $notice['type'] === 'success' ? 'var(--accent-green-border)' : 'var(--accent-red-border)' ?>; color:<?= $notice['type'] === 'success' ? 'var(--accent-green-text)' : 'var(--accent-red-text)' ?>; padding:0.85rem 1.25rem; border-radius:var(--radius-md); margin-bottom:1.5rem; font-weight:600; display:flex; align-items:center; gap:0.5rem;">
        <?= render_icon($notice['type'] === 'success' ? 'check-circle' : 'alert-triangle', '', 16) ?>
        <?= e($notice['msg']) ?>
    </div>
<?php endif; ?>

<div class="card-section">
    <div class="card-title-bar">
        <h3>
            <?= render_icon('mouse-pointer-click', '', 18, 'var(--primary)') ?>
            Danh Sách Sự Kiện Được Khai Báo (Website #<?= $selectedWebsiteId ?>)
        </h3>
        <?php if ($canManage): ?>
            <button class="btn btn-primary btn-sm" onclick="openModal('modal-add-event')">
                <?= render_icon('plus', '', 14) ?>
                Thêm Sự Kiện Mới
            </button>
        <?php endif; ?>
    </div>

    <p style="color:var(--text-secondary); font-size:0.88rem; margin-bottom:1.25rem; line-height:1.5;">
        Hệ thống chỉ tiếp nhận các sự kiện đã được khai báo và đang ở trạng thái <b>Bật (Enabled)</b>. Nếu sự kiện chưa khai báo hoặc bị Tắt, API sẽ tự động từ chối với mã <code>EVENT_NOT_DEFINED</code> và ghi log lỗi.
    </p>

    <div class="table-responsive">
        <table class="data-table">
            <thead>
                <tr>
                    <th style="width:70px;">ID</th>
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
                                <span class="badge badge-green">
                                    <?= render_icon('check-circle', '', 11) ?>
                                    Đang Bật
                                </span>
                            <?php else: ?>
                                <span class="badge badge-red">
                                    <?= render_icon('x-circle', '', 11) ?>
                                    Đang Tắt
                                </span>
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
                                        <button type="submit" class="btn btn-outline btn-sm" style="color:var(--accent-red-text); border-color:var(--accent-red-border);">
                                            <?= render_icon('pause', '', 11) ?>
                                            Vô hiệu hoá
                                        </button>
                                    <?php else: ?>
                                        <input type="hidden" name="enabled" value="1">
                                        <button type="submit" class="btn btn-outline btn-sm" style="color:var(--accent-green-text); border-color:var(--accent-green-border);">
                                            <?= render_icon('play', '', 11) ?>
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

<!-- MODAL THÊM SỰ KIỆN MỚI -->
<?php if ($canManage): ?>
<div id="modal-add-event" class="modal-backdrop">
    <div class="modal-dialog">
        <div class="modal-header">
            <h3>
                <?= render_icon('plus', '', 18, 'var(--primary)') ?>
                Thêm Định Nghĩa Sự Kiện Mới
            </h3>
            <button type="button" class="modal-close-btn" onclick="closeModal('modal-add-event')" aria-label="Đóng">
                <?= render_icon('x', '', 16) ?>
            </button>
        </div>
        <form method="POST">
            <div class="modal-body">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="create">
                
                <div style="margin-bottom:1rem;">
                    <label style="display:block; font-weight:600; margin-bottom:0.35rem; font-size:0.84rem;">Tên Sự Kiện (event_name)</label>
                    <input type="text" name="event_name" class="form-input" style="width:100%;" placeholder="ví dụ: form_submit, video_play" required>
                    <span style="font-size:0.75rem; color:var(--text-muted); display:block; margin-top:0.25rem;">Chữ thường, số và gạch dưới (2-50 ký tự)</span>
                </div>

                <div>
                    <label style="display:block; font-weight:600; margin-bottom:0.35rem; font-size:0.84rem;">Mô Tả Nghiệp Vụ</label>
                    <textarea name="description" class="form-input" style="width:100%; height:80px; padding:0.5rem 0.75rem;" placeholder="Mô tả ý nghĩa của sự kiện này..."></textarea>
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeModal('modal-add-event')">Hủy</button>
                <button type="submit" class="btn btn-primary">
                    <?= render_icon('check', '', 14) ?>
                    Lưu Sự Kiện
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
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
