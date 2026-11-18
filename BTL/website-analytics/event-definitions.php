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

$demoSiteUrl = ($selectedWebsiteId == 2) ? 'demo-sites/site-b/index.html' : 'demo-sites/site-a/index.html';
?>

<?php if ($notice): ?>
    <div style="background:<?= $notice['type'] === 'success' ? 'var(--accent-green-bg)' : 'var(--accent-red-bg)' ?>; border:1px solid <?= $notice['type'] === 'success' ? 'var(--accent-green-border)' : 'var(--accent-red-border)' ?>; color:<?= $notice['type'] === 'success' ? 'var(--accent-green-text)' : 'var(--accent-red-text)' ?>; padding:0.85rem 1.25rem; border-radius:var(--radius-md); margin-bottom:1.5rem; font-weight:600; display:flex; align-items:center; gap:0.5rem;">
        <?= render_icon($notice['type'] === 'success' ? 'check-circle' : 'alert-triangle', '', 16) ?>
        <?= e($notice['msg']) ?>
    </div>
<?php endif; ?>

<!-- HƯỚNG DẪN THAO TÁC BÁO CÁO VỚI GIẢNG VIÊN -->
<div style="background:linear-gradient(135deg, #f0fdf4 0%, #eff6ff 100%); border:1px solid #bbf7d0; border-radius:var(--radius-md); padding:1rem 1.25rem; margin-bottom:1.5rem;">
    <div style="display:flex; align-items:center; gap:0.5rem; margin-bottom:0.6rem;">
        <?= render_icon('help-circle', '', 18, '#16a34a') ?>
        <strong style="color:#166534; font-size:0.92rem;">Quy Trình Khai Báo &amp; Thao Tác Thử Nghiệm Sự Kiện Trên Trang Web Demo (Dành Cho Buổi Báo Cáo):</strong>
    </div>
    <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(220px, 1fr)); gap:0.75rem; font-size:0.83rem; color:#1e293b;">
        <div style="background:#ffffff; padding:0.75rem; border-radius:6px; border:1px solid #e2e8f0; line-height:1.5;">
            <div style="font-weight:700; color:#2563eb; margin-bottom:0.25rem;">1. Khai báo sự kiện mới</div>
            Bấm <strong>"+ Thêm Sự Kiện Mới"</strong> và nhập tên (ví dụ: <code>xemchitiet_laptop</code>, <code>dat_hang_thanh_cong</code>). Sự kiện vừa tạo mặc định ở trạng thái <strong>Đang Bật</strong>.
        </div>
        <div style="background:#ffffff; padding:0.75rem; border-radius:6px; border:1px solid #e2e8f0; line-height:1.5;">
            <div style="font-weight:700; color:#2563eb; margin-bottom:0.25rem;">2. Gắn vào trang web / Thử nghiệm</div>
            Bấm nút <strong>"Thử trên Demo ↗"</strong> tại dòng sự kiện &rarr; Trang web Demo mở ra và tự động điền sẵn tên sự kiện vào <em>Bộ Kích Hoạt Sự Kiện Khai Báo</em>.
        </div>
        <div style="background:#ffffff; padding:0.75rem; border-radius:6px; border:1px solid #e2e8f0; line-height:1.5;">
            <div style="font-weight:700; color:#2563eb; margin-bottom:0.25rem;">3. Bắn sự kiện &amp; Xem CSDL</div>
            Bấm <strong>"Bắn Sự Kiện Này"</strong> trên Demo &rarr; Hệ thống phản hồi <code>HTTP 201 Created</code>. Số liệu trên <strong>Dashboard</strong> và bảng <strong>Nhật Ký Sự Kiện</strong> nhảy tăng tức thì.
        </div>
        <div style="background:#ffffff; padding:0.75rem; border-radius:6px; border:1px solid #e2e8f0; line-height:1.5;">
            <div style="font-weight:700; color:#dc2626; margin-bottom:0.25rem;">4. Biểu diễn Chống Gian Lận</div>
            Bấm <strong>"Vô hiệu hoá"</strong> sự kiện này tại đây, rồi quay lại trang Demo bắn lại &rarr; Hệ thống lập tức từ chối với mã <code>EVENT_NOT_DEFINED (HTTP 400)</code> và ghi log lỗi!
        </div>
    </div>
</div>

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
                    <th style="width:60px;">ID</th>
                    <th>Tên Sự Kiện (event_name)</th>
                    <th>Mô Tả Nghiệp Vụ</th>
                    <th>Mã Nhúng Vào Nút Website (HTML / JS)</th>
                    <th style="width:110px;">Trạng Thái</th>
                    <th style="width:230px;">Hành Động &amp; Thử Nghiệm</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($definitions as $def): ?>
                    <tr>
                        <td><strong>#<?= $def['definition_id'] ?></strong></td>
                        <td>
                            <code style="font-weight:700; color:var(--primary); font-size:0.86rem;"><?= e($def['event_name']) ?></code>
                        </td>
                        <td style="color:var(--text-secondary);"><?= e($def['description'] ?? 'Không có mô tả') ?></td>
                        <td>
                            <div style="display:flex; flex-direction:column; gap:4px;">
                                <div style="display:flex; align-items:center; gap:6px;">
                                    <span style="font-size:0.72rem; font-weight:700; color:var(--text-secondary); width:32px;">HTML:</span>
                                    <code style="font-size:0.75rem; background:#f1f5f9; padding:2px 6px; border-radius:4px; border:1px solid #e2e8f0; color:#0f172a;">data-track-event="<?= e($def['event_name']) ?>"</code>
                                    <button type="button" class="btn btn-outline btn-sm" style="padding:2px 5px; height:24px; line-height:1;" onclick="copyCode('data-track-event=&quot;<?= e($def['event_name']) ?>&quot;', this)" title="Sao chép thuộc tính HTML">
                                        <?= render_icon('copy', '', 12) ?>
                                    </button>
                                </div>
                                <div style="display:flex; align-items:center; gap:6px;">
                                    <span style="font-size:0.72rem; font-weight:700; color:var(--text-secondary); width:32px;">JS:</span>
                                    <code style="font-size:0.75rem; background:#f1f5f9; padding:2px 6px; border-radius:4px; border:1px solid #e2e8f0; color:#0f172a;">WebTracker.track('<?= e($def['event_name']) ?>')</code>
                                    <button type="button" class="btn btn-outline btn-sm" style="padding:2px 5px; height:24px; line-height:1;" onclick="copyCode('WebTracker.track(\'<?= e($def['event_name']) ?>\')', this)" title="Sao chép hàm JS">
                                        <?= render_icon('copy', '', 12) ?>
                                    </button>
                                </div>
                            </div>
                        </td>
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
                        <td>
                            <div style="display:flex; align-items:center; gap:6px; flex-wrap:wrap;">
                                <?php if ($canManage): ?>
                                    <form method="POST" style="display:inline; margin:0;">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="action" value="toggle">
                                        <input type="hidden" name="def_id" value="<?= $def['definition_id'] ?>">
                                        <?php if ($def['enabled']): ?>
                                            <input type="hidden" name="enabled" value="0">
                                            <button type="submit" class="btn btn-outline btn-sm" style="color:var(--accent-red-text); border-color:var(--accent-red-border);" title="Tạm dừng tiếp nhận sự kiện này">
                                                <?= render_icon('pause', '', 11) ?>
                                                Vô hiệu hoá
                                            </button>
                                        <?php else: ?>
                                            <input type="hidden" name="enabled" value="1">
                                            <button type="submit" class="btn btn-outline btn-sm" style="color:var(--accent-green-text); border-color:var(--accent-green-border);" title="Kích hoạt lại sự kiện này">
                                                <?= render_icon('play', '', 11) ?>
                                                Kích hoạt
                                            </button>
                                        <?php endif; ?>
                                    </form>
                                <?php endif; ?>
                                <a href="<?= $demoSiteUrl ?>?test_event=<?= urlencode($def['event_name']) ?>" target="_blank" class="btn btn-primary btn-sm" style="display:inline-flex; align-items:center; gap:4px;" title="Mở trang Demo và thử nghiệm bắn sự kiện này ngay lập tức">
                                    <?= render_icon('external-link', '', 11) ?>
                                    Thử trên Demo
                                </a>
                            </div>
                        </td>
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
function copyCode(text, btn) {
    if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(text).then(function() {
            var orig = btn.innerHTML;
            btn.innerHTML = '<?= render_icon('check', '', 12, '#16a34a') ?>';
            setTimeout(function() { btn.innerHTML = orig; }, 1500);
        });
    } else {
        var input = document.createElement('input');
        input.value = text;
        document.body.appendChild(input);
        input.select();
        document.execCommand('copy');
        document.body.removeChild(input);
        var orig = btn.innerHTML;
        btn.innerHTML = '<?= render_icon('check', '', 12, '#16a34a') ?>';
        setTimeout(function() { btn.innerHTML = orig; }, 1500);
    }
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
