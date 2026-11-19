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

            // Tự động tạo thư mục trang demo tĩnh độc lập cho website này
            $demoDir = __DIR__ . '/demo-sites/site-' . $newId;
            if (!is_dir($demoDir)) {
                @mkdir($demoDir, 0777, true);
                $demoHtml = '<!DOCTYPE html><html lang="vi"><head><meta charset="UTF-8"><title>' . htmlspecialchars($name) . ' - Demo Site</title><link rel="stylesheet" href="../demo.css"></head><body><header class="navbar"><div class="brand">' . htmlspecialchars($name) . ' (Site #' . $newId . ')</div><div class="tracking-badge"><span class="dot"></span><span>Tracking Active</span></div></header><main class="container"><section class="hero"><h1>' . htmlspecialchars($name) . '</h1><p>Website đã sẵn sàng chạy thử và tiếp nhận dữ liệu theo dõi theo chuẩn REST API.</p><button class="btn btn-outline" data-track-click data-track-id="btn-hero-action">Thao tác thử nghiệm</button></section></main><script src="../../sdk/tracker.js" data-tracking-key="' . $trackingKey . '" data-api-endpoint="../../api/track.php"></script></body></html>';
                @file_put_contents($demoDir . '/index.html', $demoHtml);
            }

            $notice = [
                'type' => 'success', 
                'msg' => "Website '{$name}' đã được thêm thành công với Khoá Tracking: {$trackingKey}",
                'new_site_id' => $newId,
                'new_site_name' => $name
            ];
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

    if ($action === 'delete') {
        $siteId = (int)($_POST['site_id'] ?? 0);
        if ($siteId === 1 || $siteId === 2) {
            $notice = ['type' => 'error', 'msg' => 'Không thể xóa 2 Website mẫu mặc định của đồ án (Site #1 và Site #2)!'];
        } else {
            $stmtSite = $pdo->prepare('SELECT `name` FROM `websites` WHERE `website_id` = ? LIMIT 1');
            $stmtSite->execute([$siteId]);
            $siteInfo = $stmtSite->fetch();
            if ($siteInfo) {
                $pdo->beginTransaction();
                $pdo->prepare('DELETE FROM `events` WHERE `website_id` = ?')->execute([$siteId]);
                $pdo->prepare('DELETE FROM `event_ingest_errors` WHERE `website_id` = ?')->execute([$siteId]);
                $pdo->prepare('DELETE FROM `sessions` WHERE `website_id` = ?')->execute([$siteId]);
                $pdo->prepare('DELETE FROM `website_permissions` WHERE `website_id` = ?')->execute([$siteId]);
                $pdo->prepare('DELETE FROM `event_definitions` WHERE `website_id` = ?')->execute([$siteId]);
                $pdo->prepare('DELETE FROM `websites` WHERE `website_id` = ?')->execute([$siteId]);
                $pdo->commit();
                $notice = ['type' => 'success', 'msg' => "Đã xóa vĩnh viễn website '{$siteInfo['name']}' và toàn bộ dữ liệu liên quan thành công."];
                $accessibleWebsites = get_accessible_websites();
            } else {
                $notice = ['type' => 'error', 'msg' => 'Website không tồn tại hoặc đã bị xóa trước đó.'];
            }
        }
    }
}
?>

<?php if ($notice): ?>
    <div style="background:<?= $notice['type'] === 'success' ? 'var(--accent-green-bg)' : 'var(--accent-red-bg)' ?>; border:1px solid <?= $notice['type'] === 'success' ? 'var(--accent-green-border)' : 'var(--accent-red-border)' ?>; color:<?= $notice['type'] === 'success' ? 'var(--accent-green-text)' : 'var(--accent-red-text)' ?>; padding:0.85rem 1.25rem; border-radius:var(--radius-md); margin-bottom:1.5rem; font-weight:600; display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:0.75rem;">
        <div style="display:flex; align-items:center; gap:0.5rem;">
            <?= render_icon($notice['type'] === 'success' ? 'check-circle' : 'alert-triangle', '', 16) ?>
            <span><?= e($notice['msg']) ?></span>
        </div>
        <?php if (!empty($notice['new_site_id'])): ?>
            <a href="live-demo.php?website_id=<?= (int)$notice['new_site_id'] ?>" target="_blank" class="btn btn-primary btn-sm" style="background:#16a34a; border-color:#16a34a; text-decoration:none; display:inline-flex; align-items:center; gap:0.4rem; font-weight:700;">
                <?= render_icon('rocket', '', 13) ?>
                Mở Chạy Thử Ngay (Live Demo) ↗
            </a>
        <?php endif; ?>
    </div>
<?php endif; ?>

<!-- HƯỚNG DẪN THAO TÁC & CHẠY THỬ WEBSITE -->
<div style="background:rgba(37,99,235,0.04); border:1px solid rgba(37,99,235,0.18); border-radius:10px; padding:1rem 1.25rem; margin-bottom:1.25rem; display:flex; align-items:flex-start; gap:0.75rem;">
    <div style="color:var(--primary); flex-shrink:0; margin-top:2px;">
        <?= render_icon('globe', '', 20) ?>
    </div>
    <div style="font-size:0.85rem; line-height:1.55; color:var(--text-secondary);">
        <strong style="color:var(--text-primary); font-size:0.88rem; display:block; margin-bottom:0.25rem;">
            Quy trình thêm website và đưa vào vận hành ngay (Zero-Configuration):
        </strong>
        <ul style="margin:0; padding-left:1.2rem;">
            <li><strong>Thêm website mới:</strong> Bấm <em>"+ Thêm Website Mới"</em> &rarr; Hệ thống tự động tạo mã Tracking Key và nạp 4 sự kiện chuẩn (<code>page_view</code>, <code>button_click</code>, <code>download_start</code>, <code>download_complete</code>).</li>
            <li><strong>Chạy thử ngay lập tức (Không cần code thêm):</strong> Bấm nút <strong style="color:#2563eb;">[ Chạy Thử ]</strong> tại dòng website tương ứng để mở môi trường Live Sandbox tương tác, mô phỏng bấm nút và xem dữ liệu đổ về Dashboard theo thời gian thực.</li>
            <li><strong>Tích hợp vào website thật:</strong> Bấm nút <strong>[ Mã Nhúng ]</strong> để sao chép đoạn mã JavaScript SDK dán vào thẻ <code>&lt;body&gt;</code> của trang web bất kỳ.</li>
        </ul>
    </div>
</div>

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
                    <th style="width:65px;">ID</th>
                    <th>Tên Website</th>
                    <th>Tên Miền / Origin</th>
                    <th>Khoá Tracking Key (Công Khai)</th>
                    <th>Múi Giờ</th>
                    <th>Hạn Sự Kiện Trễ</th>
                    <th>Trạng Thái</th>
                    <th style="min-width:240px;">Thao Tác</th>
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
                        <td>
                            <div style="display:inline-flex; align-items:center; gap:0.35rem; flex-wrap:wrap;">
                                <!-- 1. Nút Chạy Thử Ngay -->
                                <a href="live-demo.php?website_id=<?= (int)$site['website_id'] ?>" target="_blank" class="btn btn-primary btn-sm" style="padding:0.28rem 0.6rem; text-decoration:none; display:inline-flex; align-items:center; gap:0.3rem;" title="Mở trang web chạy thử tương tác ngay">
                                    <?= render_icon('rocket', '', 12) ?>
                                    Chạy Thử
                                </a>

                                <!-- 2. Nút Xem Mã Nhúng SDK -->
                                <button type="button" class="btn btn-outline btn-sm" style="padding:0.28rem 0.5rem; display:inline-flex; align-items:center; gap:0.3rem;" onclick="openEmbedModal(<?= (int)$site['website_id'] ?>, '<?= e(addslashes($site['name'])) ?>', '<?= e($site['tracking_key']) ?>')" title="Xem mã nhúng JavaScript SDK">
                                    <?= render_icon('code', '', 12) ?>
                                    Mã Nhúng
                                </button>

                                <?php if ($isAdmin): ?>
                                    <!-- 3. Nút Tạm Dừng / Kích Hoạt -->
                                    <form method="POST" style="display:inline; margin:0;">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="action" value="toggle_status">
                                        <input type="hidden" name="site_id" value="<?= (int)$site['website_id'] ?>">
                                        <?php if ($site['status'] === 'active'): ?>
                                            <input type="hidden" name="new_status" value="paused">
                                            <button type="submit" class="btn btn-outline btn-sm" style="color:var(--accent-yellow-text); border-color:var(--accent-yellow-border); padding:0.28rem 0.5rem;" title="Tạm dừng tiếp nhận sự kiện">
                                                <?= render_icon('pause', '', 11) ?>
                                                Dừng
                                            </button>
                                        <?php else: ?>
                                            <input type="hidden" name="new_status" value="active">
                                            <button type="submit" class="btn btn-outline btn-sm" style="color:var(--accent-green-text); border-color:var(--accent-green-border); padding:0.28rem 0.5rem;" title="Kích hoạt lại website">
                                                <?= render_icon('play', '', 11) ?>
                                                Bật
                                            </button>
                                        <?php endif; ?>
                                    </form>

                                    <!-- 4. Nút Xóa Website (Bảo vệ Site 1 & 2 của đề tài) -->
                                    <?php if ((int)$site['website_id'] > 2): ?>
                                        <button type="button" class="btn btn-outline btn-sm" style="color:#ef4444; border-color:#fca5a5; padding:0.28rem 0.5rem;" onclick="openDeleteSiteModal(<?= (int)$site['website_id'] ?>, '<?= e(addslashes($site['name'])) ?>')" title="Xóa vĩnh viễn website này">
                                            <?= render_icon('trash-2', '', 11) ?>
                                            Xóa
                                        </button>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
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
                    <input type="text" name="name" class="form-input" style="width:100%;" placeholder="Ví dụ: Website C - Cửa hàng Công nghệ (Gamma Store)" required>
                </div>

                <div style="margin-bottom:1rem;">
                    <label style="display:block; font-weight:600; margin-bottom:0.35rem; font-size:0.84rem;">Tên Miền / Hostname</label>
                    <input type="text" name="domain" class="form-input" style="width:100%;" placeholder="Ví dụ: localhost hoặc store.gamma.com" required>
                </div>

                <div style="margin-bottom:1rem;">
                    <label style="display:block; font-weight:600; margin-bottom:0.35rem; font-size:0.84rem;">Múi Giờ Báo Cáo</label>
                    <select name="timezone" class="form-select" style="width:100%;">
                        <option value="Asia/Ho_Chi_Minh" selected>Asia/Ho_Chi_Minh (GMT+7)</option>
                        <option value="UTC">UTC (GMT+0)</option>
                    </select>
                </div>

                <div style="margin-bottom:1rem;">
                    <label style="display:block; font-weight:600; margin-bottom:0.35rem; font-size:0.84rem;">Hạn Chấp Nhận Sự Kiện Trễ (Giờ)</label>
                    <input type="number" name="late_window" class="form-input" style="width:100%;" value="24" min="1" max="168" required>
                </div>

                <div style="background:#f0fdf4; border:1px solid #bbf7d0; border-radius:6px; padding:0.75rem; font-size:0.8rem; color:#166534; line-height:1.5;">
                    <?= render_icon('check-circle', '', 14, '#16a34a') ?>
                    <strong>Kích hoạt chạy được luôn:</strong> Khi lưu, hệ thống tự động sinh mã khoá Tracking Key, nạp sẵn 4 sự kiện chuẩn và kích hoạt ngay môi trường chạy thử Live Sandbox cho website này!
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeModal('modal-create')">Hủy</button>
                <button type="submit" class="btn btn-primary">
                    <?= render_icon('check', '', 14) ?>
                    Lưu & Kích Hoạt Chạy Ngay
                </button>
            </div>
        </form>
    </div>
</div>

<!-- MODAL XEM MÃ NHÚNG SDK -->
<div id="modal-embed" class="modal-backdrop">
    <div class="modal-dialog" style="max-width:580px;">
        <div class="modal-header">
            <h3>
                <?= render_icon('code', '', 18, 'var(--primary)') ?>
                Mã Nhúng JavaScript Tracking SDK
            </h3>
            <button type="button" class="modal-close-btn" onclick="closeModal('modal-embed')" aria-label="Đóng">
                <?= render_icon('x', '', 16) ?>
            </button>
        </div>
        <div class="modal-body">
            <p style="font-size:0.85rem; color:var(--text-secondary); margin-bottom:0.75rem;">
                Website: <strong id="embed-site-name" style="color:var(--text-primary);"></strong>
            </p>
            <p style="font-size:0.82rem; color:var(--text-muted); margin-bottom:0.5rem;">
                Dán đoạn mã sau vào trước thẻ đóng <code>&lt;/body&gt;</code> của bất kỳ website nào bạn muốn theo dõi:
            </p>
            <div style="position:relative;">
                <textarea id="embed-code-textarea" readonly style="width:100%; height:130px; font-family:'JetBrains Mono', monospace; font-size:0.78rem; background:#0f172a; color:#e2e8f0; border-radius:8px; padding:0.85rem; border:1px solid #334155; line-height:1.6; resize:none;"></textarea>
            </div>
            <div style="margin-top:1rem; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:0.5rem;">
                <button type="button" class="btn btn-primary btn-sm" onclick="copyEmbedCode()">
                    <?= render_icon('copy', '', 13) ?>
                    Sao Chép Đoạn Mã
                </button>
                <a id="embed-open-demo-btn" href="#" target="_blank" class="btn btn-outline btn-sm" style="color:var(--primary); border-color:var(--primary); text-decoration:none;">
                    <?= render_icon('rocket', '', 13) ?>
                    Mở Chạy Thử Trực Tiếp (Không Cần Nhúng) ↗
                </a>
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-outline" onclick="closeModal('modal-embed')">Đóng</button>
        </div>
    </div>
</div>

<!-- MODAL XÁC NHẬN XÓA WEBSITE -->
<div id="modal-delete-site" class="modal-backdrop">
    <div class="modal-dialog">
        <div class="modal-header">
            <h3>
                <?= render_icon('trash-2', '', 18, '#ef4444') ?>
                Xác Nhận Xóa Vĩnh Viễn Website
            </h3>
            <button type="button" class="modal-close-btn" onclick="closeModal('modal-delete-site')" aria-label="Đóng">
                <?= render_icon('x', '', 16) ?>
            </button>
        </div>
        <form method="POST">
            <div class="modal-body">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="site_id" id="delete-site-id" value="0">

                <div style="background:#fee2e2; border:1px solid #fecaca; border-radius:8px; padding:1rem; margin-bottom:1rem; color:#991b1b; font-size:0.88rem; line-height:1.5;">
                    <div style="display:flex; align-items:center; gap:0.5rem; font-weight:700; margin-bottom:0.4rem;">
                        <?= render_icon('alert-triangle', '', 16, '#dc2626') ?>
                        <span>Cảnh báo xóa toàn bộ dữ liệu!</span>
                    </div>
                    <div>
                        Bạn đang chuẩn bị xóa website:
                        <div style="font-weight:700; color:#7f1d1d; margin:0.4rem 0; padding:0.4rem 0.6rem; background:rgba(255,255,255,0.7); border-radius:4px;" id="delete-site-name"></div>
                        Hành động này sẽ xóa vĩnh viễn:
                        <ul style="margin:0.25rem 0 0 1.2rem; padding:0;">
                            <li>Bản ghi cấu hình website và mã khoá tracking.</li>
                            <li>Toàn bộ sự kiện đã thu thập (<code>events</code>) và phiên (<code>sessions</code>).</li>
                            <li>Toàn bộ định nghĩa sự kiện và phân quyền thành viên liên quan.</li>
                        </ul>
                    </div>
                </div>
                <p style="font-size:0.84rem; color:var(--text-secondary); margin:0;">
                    Dữ liệu đã xóa không thể khôi phục lại. Bạn có chắc chắn muốn xóa?
                </p>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeModal('modal-delete-site')">Hủy bỏ</button>
                <button type="submit" class="btn btn-primary" style="background:#dc2626; border-color:#dc2626;">
                    <?= render_icon('trash-2', '', 14) ?>
                    Xác Nhận Xóa
                </button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<script>
var currentBaseUrl = '<?= rtrim($baseUrl, '/') ?>';

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
function openEmbedModal(siteId, siteName, trackingKey) {
    document.getElementById('embed-site-name').textContent = '#' + siteId + ' - ' + siteName;
    var snippet = '<!-- Web Tracking SDK -->\n' +
        '<script src="' + currentBaseUrl + '/sdk/tracker.js"\n' +
        '        data-tracking-key="' + trackingKey + '"\n' +
        '        data-api-endpoint="' + currentBaseUrl + '/api/track.php">\n' +
        '<\/script>';
    document.getElementById('embed-code-textarea').value = snippet;
    document.getElementById('embed-open-demo-btn').href = 'live-demo.php?website_id=' + siteId;
    openModal('modal-embed');
}
function copyEmbedCode() {
    var ta = document.getElementById('embed-code-textarea');
    ta.select();
    navigator.clipboard.writeText(ta.value).then(function() {
        showToast('Đã sao chép toàn bộ mã nhúng SDK vào bộ nhớ tạm!');
    });
}
function openDeleteSiteModal(siteId, siteName) {
    document.getElementById('delete-site-id').value = siteId;
    document.getElementById('delete-site-name').textContent = '#' + siteId + ' - ' + siteName;
    openModal('modal-delete-site');
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
