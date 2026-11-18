<?php
// ====================================================================
// QUẢN LÝ NGƯỜI DÙNG & PHÂN QUYỀN (users.php) - ADMIN ONLY
// Quản lý tài khoản, trạng thái và ma trận quyền RBAC (Enterprise SaaS)
// ====================================================================

$pageTitle = 'Quản Lý Người Dùng & Phân Quyền';
require_once __DIR__ . '/includes/auth.php';
require_admin(); // Bắt buộc chỉ Admin mới được truy cập
$currentUser = get_auth_user();
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/csrf.php';

$pdo = get_db_connection();
$notice = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf_token();
    $action = $_POST['action'] ?? '';

    // Thêm người dùng mới
    if ($action === 'create_user') {
        $name  = trim($_POST['full_name'] ?? '');
        $email = trim(filter_var($_POST['email'] ?? '', FILTER_SANITIZE_EMAIL));
        $pass  = $_POST['password'] ?? '';
        $role  = in_array($_POST['role'] ?? '', ['admin', 'member'], true) ? $_POST['role'] : 'member';

        if (!empty($name) && !empty($email) && strlen($pass) >= 6) {
            $hash = password_hash($pass, PASSWORD_BCRYPT);
            try {
                $stmt = $pdo->prepare('INSERT INTO `users` (`full_name`, `email`, `password_hash`, `role`, `status`, `created_at`) VALUES (?, ?, ?, ?, "active", NOW())');
                $stmt->execute([$name, $email, $hash, $role]);
                $notice = ['type' => 'success', 'msg' => "Đã tạo tài khoản '{$email}' ({$role}) thành công."];
            } catch (PDOException $e) {
                $notice = ['type' => 'error', 'msg' => 'Email này đã được sử dụng trong hệ thống.'];
            }
        } else {
            $notice = ['type' => 'error', 'msg' => 'Vui lòng điền đầy đủ thông tin và mật khẩu ít nhất 6 ký tự.'];
        }
    }

    // Khóa / Kích hoạt tài khoản
    if ($action === 'toggle_status') {
        $targetUserId = (int)($_POST['target_user_id'] ?? 0);
        $newStatus    = $_POST['new_status'] ?? 'active';

        // Không thể tự khóa tài khoản chính mình
        if ($targetUserId === (int)$currentUser['user_id']) {
            $notice = ['type' => 'error', 'msg' => 'Không thể tự khóa tài khoản Admin bạn đang đăng nhập!'];
        } else {
            $stmt = $pdo->prepare('UPDATE `users` SET `status` = ? WHERE `user_id` = ?');
            $stmt->execute([$newStatus, $targetUserId]);
            $notice = ['type' => 'success', 'msg' => 'Cập nhật trạng thái người dùng thành công.'];
        }
    }

    // Xóa vĩnh viễn tài khoản người dùng (Delete User)
    if ($action === 'delete_user') {
        $targetUserId = (int)($_POST['target_user_id'] ?? 0);

        // 1. Không thể tự xóa tài khoản của chính mình
        if ($targetUserId === (int)$currentUser['user_id']) {
            $notice = ['type' => 'error', 'msg' => 'Không thể tự xóa tài khoản Quản trị viên bạn đang đăng nhập!'];
        }
        // 2. Không thể xóa tài khoản Super Admin gốc (#1)
        elseif ($targetUserId === 1) {
            $notice = ['type' => 'error', 'msg' => 'Không thể xóa tài khoản Quản trị viên hệ thống gốc (#1)!'];
        } else {
            $stmtCheck = $pdo->prepare('SELECT `user_id`, `full_name`, `email`, `role` FROM `users` WHERE `user_id` = ? LIMIT 1');
            $stmtCheck->execute([$targetUserId]);
            $targetUser = $stmtCheck->fetch();

            if (!$targetUser) {
                $notice = ['type' => 'error', 'msg' => 'Người dùng không tồn tại hoặc đã bị xóa trước đó.'];
            } elseif ($targetUser['role'] === 'admin') {
                $stmtAdminCount = $pdo->query('SELECT COUNT(*) FROM `users` WHERE `role` = "admin"');
                $adminCount = (int)$stmtAdminCount->fetchColumn();
                if ($adminCount <= 1) {
                    $notice = ['type' => 'error', 'msg' => 'Không thể xóa Quản trị viên duy nhất còn lại của hệ thống!'];
                } else {
                    $pdo->beginTransaction();
                    $stmtDelPerms = $pdo->prepare('DELETE FROM `website_permissions` WHERE `user_id` = ?');
                    $stmtDelPerms->execute([$targetUserId]);
                    $stmtDelUser = $pdo->prepare('DELETE FROM `users` WHERE `user_id` = ?');
                    $stmtDelUser->execute([$targetUserId]);
                    $pdo->commit();
                    $notice = ['type' => 'success', 'msg' => "Đã xóa vĩnh viễn tài khoản Quản trị viên '{$targetUser['email']}' thành công."];
                }
            } else {
                $pdo->beginTransaction();
                $stmtDelPerms = $pdo->prepare('DELETE FROM `website_permissions` WHERE `user_id` = ?');
                $stmtDelPerms->execute([$targetUserId]);
                $stmtDelUser = $pdo->prepare('DELETE FROM `users` WHERE `user_id` = ?');
                $stmtDelUser->execute([$targetUserId]);
                $pdo->commit();
                $notice = ['type' => 'success', 'msg' => "Đã xóa vĩnh viễn tài khoản người dùng '{$targetUser['email']}' và toàn bộ phân quyền website liên quan."];
            }
        }
    }

    // Cấp / Thu hồi quyền trên Website cho Member
    if ($action === 'update_permission') {
        $targetUserId = (int)($_POST['target_user_id'] ?? 0);
        $targetSiteId = (int)($_POST['target_site_id'] ?? 0);
        $canView      = (int)($_POST['can_view'] ?? 0);
        $canTrack     = (int)($_POST['can_manage_tracking'] ?? 0);
        $canExport    = (int)($_POST['can_export'] ?? 0);

        // Kiểm tra xem user có phải Member không
        $stmtCheck = $pdo->prepare('SELECT `role`, `email` FROM `users` WHERE `user_id` = ? LIMIT 1');
        $stmtCheck->execute([$targetUserId]);
        $targetUser = $stmtCheck->fetch();

        if (!$targetUser) {
            $notice = ['type' => 'error', 'msg' => 'Người dùng không tồn tại.'];
        } elseif ($targetUser['role'] === 'admin') {
            $notice = ['type' => 'error', 'msg' => 'Tài khoản Quản trị viên (Admin) mặc định có toàn quyền truy cập toàn bộ hệ thống, không cần phân quyền thủ công.'];
        } else {
            // Quy tắc chặt chẽ: Nếu không có quyền Xem (can_view = 0) thì không thể có quyền Quản lý Tracking hay Xuất CSV
            if ($canView === 0) {
                $canTrack = 0;
                $canExport = 0;
            }

            if ($canView === 0 && $canTrack === 0 && $canExport === 0) {
                $stmt = $pdo->prepare('DELETE FROM `website_permissions` WHERE `user_id` = ? AND `website_id` = ?');
                $stmt->execute([$targetUserId, $targetSiteId]);
                $notice = ['type' => 'success', 'msg' => "Đã thu hồi toàn bộ quyền của '{$targetUser['email']}' trên website này."];
            } else {
                $stmt = $pdo->prepare('
                    INSERT INTO `website_permissions` (`user_id`, `website_id`, `can_view`, `can_manage_tracking`, `can_export`, `created_at`)
                    VALUES (?, ?, ?, ?, ?, NOW())
                    ON DUPLICATE KEY UPDATE 
                        `can_view` = VALUES(`can_view`),
                        `can_manage_tracking` = VALUES(`can_manage_tracking`),
                        `can_export` = VALUES(`can_export`)
                ');
                $stmt->execute([$targetUserId, $targetSiteId, $canView, $canTrack, $canExport]);
                $notice = ['type' => 'success', 'msg' => "Đã cập nhật phân quyền website cho '{$targetUser['email']}' thành công."];
            }
        }
    }
}

// Lấy danh sách tất cả Users
$stmtUsers = $pdo->query('SELECT `user_id`, `full_name`, `email`, `role`, `status`, `created_at` FROM `users` ORDER BY `user_id` ASC');
$allUsers = $stmtUsers->fetchAll();

// Lấy tất cả website
$stmtSites = $pdo->query('SELECT `website_id`, `name`, `domain` FROM `websites` ORDER BY `website_id` ASC');
$allSites = $stmtSites->fetchAll();

// Lấy tất cả permissions hiện tại
$stmtPerms = $pdo->query('SELECT `user_id`, `website_id`, `can_view`, `can_manage_tracking`, `can_export` FROM `website_permissions`');
$rawPerms = $stmtPerms->fetchAll();
$permMatrix = [];
foreach ($rawPerms as $p) {
    $permMatrix[$p['user_id']][$p['website_id']] = $p;
}
?>

<?php if ($notice): ?>
    <div style="background:<?= $notice['type'] === 'success' ? 'var(--accent-green-bg)' : 'var(--accent-red-bg)' ?>; border:1px solid <?= $notice['type'] === 'success' ? 'var(--accent-green-border)' : 'var(--accent-red-border)' ?>; color:<?= $notice['type'] === 'success' ? 'var(--accent-green-text)' : 'var(--accent-red-text)' ?>; padding:0.85rem 1.25rem; border-radius:var(--radius-md); margin-bottom:1.5rem; font-weight:600; display:flex; align-items:center; gap:0.5rem;">
        <?= render_icon($notice['type'] === 'success' ? 'check-circle' : 'alert-triangle', '', 16) ?>
        <?= e($notice['msg']) ?>
    </div>
<?php endif; ?>

<!-- DANH SÁCH TÀI KHOẢN -->
<div class="card-section">
    <div class="card-title-bar">
        <h3>
            <?= render_icon('users', '', 18, 'var(--primary)') ?>
            Danh Sách Tài Khoản Người Dùng
        </h3>
        <button class="btn btn-primary btn-sm" onclick="openModal('modal-create-user')">
            <?= render_icon('plus', '', 14) ?>
            Thêm Người Dùng Mới
        </button>
    </div>

    <div class="table-responsive">
        <table class="data-table">
            <thead>
                <tr>
                    <th style="width:70px;">ID</th>
                    <th>Họ và Tên</th>
                    <th>Email Đăng Nhập</th>
                    <th>Vai Trò (Role)</th>
                    <th>Trạng Thái</th>
                    <th>Ngày Tạo</th>
                    <th>Thao Tác</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($allUsers as $u): ?>
                    <tr>
                        <td><strong>#<?= $u['user_id'] ?></strong></td>
                        <td>
                            <div style="display:flex; align-items:center; gap:0.55rem;">
                                <div style="width:28px; height:28px; border-radius:50%; background:var(--bg-muted); display:flex; align-items:center; justify-content:center; font-size:0.75rem; font-weight:700; color:var(--text-secondary);">
                                    <?= strtoupper(mb_substr($u['full_name'], 0, 1, 'UTF-8')) ?>
                                </div>
                                <strong><?= e($u['full_name']) ?></strong>
                            </div>
                        </td>
                        <td><?= e($u['email']) ?></td>
                        <td>
                            <?php if ($u['role'] === 'admin'): ?>
                                <span class="badge badge-blue">
                                    <?= render_icon('shield', '', 11) ?>
                                    Admin (Toàn quyền)
                                </span>
                            <?php else: ?>
                                <span class="badge badge-gray">
                                    <?= render_icon('user', '', 11) ?>
                                    Member (Thành viên)
                                </span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($u['status'] === 'active'): ?>
                                <span class="badge badge-green">
                                    <?= render_icon('check-circle', '', 11) ?>
                                    Hoạt động
                                </span>
                            <?php else: ?>
                                <span class="badge badge-red">
                                    <?= render_icon('x-circle', '', 11) ?>
                                    Đã khóa
                                </span>
                            <?php endif; ?>
                        </td>
                        <td><?= date('d/m/Y', strtotime($u['created_at'])) ?></td>
                        <td>
                            <?php if ((int)$u['user_id'] === (int)$currentUser['user_id']): ?>
                                <span style="color:var(--text-muted); font-size:0.8rem; font-style:italic;">(Đang đăng nhập)</span>
                            <?php else: ?>
                                <div style="display:inline-flex; align-items:center; gap:0.4rem; flex-wrap:wrap;">
                                    <form method="POST" style="display:inline; margin:0;">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="action" value="toggle_status">
                                        <input type="hidden" name="target_user_id" value="<?= (int)$u['user_id'] ?>">
                                        <?php if ($u['status'] === 'active'): ?>
                                            <input type="hidden" name="new_status" value="inactive">
                                            <button type="submit" class="btn btn-outline btn-sm" style="color:var(--accent-red-text); border-color:var(--accent-red-border);" title="Khóa tài khoản này">
                                                <?= render_icon('lock', '', 11) ?>
                                                Khóa
                                            </button>
                                        <?php else: ?>
                                            <input type="hidden" name="new_status" value="active">
                                            <button type="submit" class="btn btn-outline btn-sm" style="color:var(--accent-green-text); border-color:var(--accent-green-border);" title="Mở khóa tài khoản">
                                                <?= render_icon('check', '', 11) ?>
                                                Mở khóa
                                            </button>
                                        <?php endif; ?>
                                    </form>

                                    <?php if ((int)$u['user_id'] === 1): ?>
                                        <span class="badge badge-gray" style="font-size:0.75rem;" title="Quản trị viên hệ thống gốc - được bảo vệ an toàn">Gốc</span>
                                    <?php else: ?>
                                        <button type="button" class="btn btn-outline btn-sm" style="color:#ef4444; border-color:#fca5a5;" onclick="openDeleteModal(<?= (int)$u['user_id'] ?>, '<?= e(addslashes($u['full_name'])) ?>', '<?= e(addslashes($u['email'])) ?>', '<?= e($u['role']) ?>')" title="Xóa vĩnh viễn tài khoản">
                                            <?= render_icon('trash-2', '', 11) ?>
                                            Xóa
                                        </button>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- MA TRẬN PHÂN QUYỀN WEBSITE CHO MEMBER (STRICT RBAC) -->
<?php
$memberUsers = array_filter($allUsers, fn($u) => $u['role'] === 'member');
?>
<div class="card-section">
    <div class="card-title-bar">
        <h3>
            <?= render_icon('shield-check', '', 18, 'var(--primary)') ?>
            Ma Trận Phân Quyền Website Theo Người Dùng
        </h3>
        <span class="badge badge-blue">RBAC Permissions</span>
    </div>

    <!-- Security Policy Banner -->
    <div style="background:rgba(59, 130, 246, 0.05); border:1px solid rgba(59, 130, 246, 0.2); border-radius:10px; padding:1rem 1.25rem; margin-bottom:1.25rem; display:flex; align-items:flex-start; gap:0.75rem;">
        <div style="color:var(--primary); flex-shrink:0; margin-top:2px;">
            <?= render_icon('shield-check', '', 20) ?>
        </div>
        <div style="font-size:0.85rem; line-height:1.55; color:var(--text-secondary);">
            <strong style="color:var(--text-primary); font-size:0.88rem; display:block; margin-bottom:0.25rem;">
                Chính sách bảo mật phân quyền nghiêm ngặt (Strict RBAC Policy):
            </strong>
            <ul style="margin:0; padding-left:1.2rem;">
                <li><strong>Quản Trị Viên (Admin):</strong> Tự động có toàn quyền truy cập, theo dõi và cấu hình mọi website trong hệ thống mà không cần phân quyền thủ công.</li>
                <li><strong>Thành Viên (Member):</strong> Bị hạn chế theo nguyên tắc đặc quyền tối thiểu (Least Privilege). Chỉ xem và thao tác trên đúng website được Admin cấp phép dưới đây.</li>
                <li><strong>Ràng buộc logic:</strong> Nếu tắt quyền <em>"Cho phép xem"</em>, hệ thống sẽ tự động thu hồi và vô hiệu hóa mọi quyền phụ (Quản lý Tracking, Xuất CSV).</li>
            </ul>
        </div>
    </div>

    <div class="table-responsive">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Tài Khoản Member</th>
                    <th>Website Phân Quyền</th>
                    <th>Quyền Xem Báo Cáo</th>
                    <th>Quản Lý Tracking</th>
                    <th>Xuất CSV</th>
                    <th>Cập Nhật</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($memberUsers)): ?>
                    <tr>
                        <td colspan="6" style="text-align:center; padding:2rem; color:var(--text-muted);">
                            Chưa có tài khoản Member nào trong hệ thống. Hãy bấm <strong>"Thêm Người Dùng Mới"</strong> với vai trò Member để thực hiện phân quyền.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($memberUsers as $u): ?>
                        <?php foreach ($allSites as $s): ?>
                            <?php 
                            $p = $permMatrix[$u['user_id']][$s['website_id']] ?? null;
                            $canV = $p ? (int)$p['can_view'] : 0;
                            $canT = $p ? (int)$p['can_manage_tracking'] : 0;
                            $canE = $p ? (int)$p['can_export'] : 0;
                            $isUserInactive = ($u['status'] === 'inactive');
                            ?>
                            <tr style="<?= $isUserInactive ? 'opacity:0.75; background:rgba(239,68,68,0.02);' : '' ?>">
                                <td>
                                    <div style="display:flex; align-items:center; gap:0.5rem;">
                                        <div>
                                            <strong><?= e($u['full_name']) ?></strong>
                                            <?php if ($isUserInactive): ?>
                                                <span class="badge badge-red" style="font-size:0.68rem; margin-left:0.35rem;">Đã khóa</span>
                                            <?php endif; ?>
                                            <br>
                                            <span style="font-size:0.75rem; color:#64748b;"><?= e($u['email']) ?></span>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <strong><?= e($s['name']) ?></strong>
                                    <span style="font-size:0.75rem; color:var(--text-muted); display:block;"><?= e($s['domain'] ?? '') ?> (ID: <?= $s['website_id'] ?>)</span>
                                </td>
                                <form method="POST">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="update_permission">
                                    <input type="hidden" name="target_user_id" value="<?= (int)$u['user_id'] ?>">
                                    <input type="hidden" name="target_site_id" value="<?= (int)$s['website_id'] ?>">

                                    <td>
                                        <label style="display:inline-flex; align-items:center; gap:0.4rem; cursor:pointer;">
                                            <input type="checkbox" name="can_view" value="1" <?= $canV ? 'checked' : '' ?> onchange="handleViewToggle(this)">
                                            <span style="font-weight:500;">Cho phép xem</span>
                                        </label>
                                    </td>
                                    <td>
                                        <label style="display:inline-flex; align-items:center; gap:0.4rem; cursor:<?= $canV ? 'pointer' : 'not-allowed' ?>;">
                                            <input type="checkbox" name="can_manage_tracking" value="1" class="perm-child" <?= ($canV && $canT) ? 'checked' : '' ?> <?= !$canV ? 'disabled' : '' ?>>
                                            <span>Bật/Tắt sự kiện</span>
                                        </label>
                                    </td>
                                    <td>
                                        <label style="display:inline-flex; align-items:center; gap:0.4rem; cursor:<?= $canV ? 'pointer' : 'not-allowed' ?>;">
                                            <input type="checkbox" name="can_export" value="1" class="perm-child" <?= ($canV && $canE) ? 'checked' : '' ?> <?= !$canV ? 'disabled' : '' ?>>
                                            <span>Cho phép xuất</span>
                                        </label>
                                    </td>
                                    <td>
                                        <button type="submit" class="btn btn-primary btn-sm">
                                            <?= render_icon('check', '', 12) ?>
                                            Lưu Quyền
                                        </button>
                                    </td>
                                </form>
                            </tr>
                        <?php endforeach; ?>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- MODAL XÓA VĨNH VIỄN NGƯỜI DÙNG -->
<div id="modal-delete-user" class="modal-backdrop">
    <div class="modal-dialog">
        <div class="modal-header">
            <h3>
                <?= render_icon('trash-2', '', 18, '#ef4444') ?>
                Xác Nhận Xóa Vĩnh Viễn Tài Khoản
            </h3>
            <button type="button" class="modal-close-btn" onclick="closeModal('modal-delete-user')" aria-label="Đóng">
                <?= render_icon('x', '', 16) ?>
            </button>
        </div>
        <form method="POST">
            <div class="modal-body">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="delete_user">
                <input type="hidden" name="target_user_id" id="delete-user-id" value="0">

                <div style="background:#fee2e2; border:1px solid #fecaca; border-radius:8px; padding:1rem; margin-bottom:1rem; color:#991b1b; font-size:0.88rem; line-height:1.5;">
                    <div style="display:flex; align-items:center; gap:0.5rem; font-weight:700; margin-bottom:0.4rem;">
                        <?= render_icon('alert-triangle', '', 16, '#dc2626') ?>
                        <span>Cảnh báo hành động không thể khôi phục!</span>
                    </div>
                    <div>
                        Bạn đang chuẩn bị xóa vĩnh viễn tài khoản:
                        <div style="font-weight:700; color:#7f1d1d; margin:0.4rem 0; padding:0.4rem 0.6rem; background:rgba(255,255,255,0.7); border-radius:4px;" id="delete-user-info"></div>
                        Hành động này sẽ:
                        <ul style="margin:0.25rem 0 0 1.2rem; padding:0;">
                            <li>Xóa hoàn toàn tài khoản khỏi cơ sở dữ liệu hệ thống.</li>
                            <li>Tự động thu hồi và xóa toàn bộ phân quyền website của tài khoản này.</li>
                            <li>Dữ liệu đã xóa <strong>không thể khôi phục lại</strong>.</li>
                        </ul>
                    </div>
                </div>
                <p style="font-size:0.84rem; color:var(--text-secondary); margin:0;">
                    Vui lòng xác nhận rằng bạn thực sự muốn tiếp tục thao tác xóa này.
                </p>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeModal('modal-delete-user')">Hủy bỏ</button>
                <button type="submit" class="btn btn-primary" style="background:#dc2626; border-color:#dc2626;">
                    <?= render_icon('trash-2', '', 14) ?>
                    Xác Nhận Xóa
                </button>
            </div>
        </form>
    </div>
</div>

<!-- MODAL THÊM NGƯỜI DÙNG -->
<div id="modal-create-user" class="modal-backdrop">
    <div class="modal-dialog">
        <div class="modal-header">
            <h3>
                <?= render_icon('users', '', 18, 'var(--primary)') ?>
                Tạo Tài Khoản Người Dùng Mới
            </h3>
            <button type="button" class="modal-close-btn" onclick="closeModal('modal-create-user')" aria-label="Đóng">
                <?= render_icon('x', '', 16) ?>
            </button>
        </div>
        <form method="POST">
            <div class="modal-body">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="create_user">

                <div style="margin-bottom:1rem;">
                    <label style="display:block; font-weight:600; margin-bottom:0.35rem; font-size:0.84rem;">Họ và Tên</label>
                    <input type="text" name="full_name" class="form-input" style="width:100%;" placeholder="Nguyễn Văn A" required>
                </div>

                <div style="margin-bottom:1rem;">
                    <label style="display:block; font-weight:600; margin-bottom:0.35rem; font-size:0.84rem;">Địa Chỉ Email</label>
                    <input type="email" name="email" class="form-input" style="width:100%;" placeholder="member2@analytics.local" required>
                </div>

                <div style="margin-bottom:1rem;">
                    <label style="display:block; font-weight:600; margin-bottom:0.35rem; font-size:0.84rem;">Mật Khẩu Ban Đầu</label>
                    <input type="password" name="password" class="form-input" style="width:100%;" placeholder="Ít nhất 6 ký tự" required>
                </div>

                <div>
                    <label style="display:block; font-weight:600; margin-bottom:0.35rem; font-size:0.84rem;">Vai Trò (Role)</label>
                    <select name="role" class="form-select" style="width:100%;">
                        <option value="member" selected>Member (Thành viên được cấp quyền)</option>
                        <option value="admin">Admin (Quản trị viên toàn hệ thống)</option>
                    </select>
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeModal('modal-create-user')">Hủy</button>
                <button type="submit" class="btn btn-primary">
                    <?= render_icon('check', '', 14) ?>
                    Tạo Tài Khoản
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function openModal(id) {
    const m = document.getElementById(id);
    if (m) m.classList.add('active');
}
function closeModal(id) {
    const m = document.getElementById(id);
    if (m) m.classList.remove('active');
}
function openDeleteModal(userId, name, email, role) {
    document.getElementById('delete-user-id').value = userId;
    document.getElementById('delete-user-info').textContent = name + ' (' + email + ') — Vai trò: ' + role.toUpperCase();
    openModal('modal-delete-user');
}
function handleViewToggle(checkbox) {
    const row = checkbox.closest('tr');
    if (!row) return;
    const childCheckboxes = row.querySelectorAll('.perm-child');
    childCheckboxes.forEach(cb => {
        if (!checkbox.checked) {
            cb.checked = false;
            cb.disabled = true;
            if (cb.parentElement) cb.parentElement.style.cursor = 'not-allowed';
        } else {
            cb.disabled = false;
            if (cb.parentElement) cb.parentElement.style.cursor = 'pointer';
        }
    });
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
