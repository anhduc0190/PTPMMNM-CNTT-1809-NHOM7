<?php
// ====================================================================
// NHẬT KÝ SỰ KIỆN & LỖI TIẾP NHẬN (event-logs.php)
// Đối chiếu mã băm payload SHA-256, kiểm tra xung đột và sự kiện hợp lệ
// ====================================================================

$pageTitle = 'Nhật Ký Sự Kiện & Lỗi Tiếp Nhận';
require_once __DIR__ . '/includes/header.php';

$pdo = get_db_connection();

$viewTab   = $_GET['tab'] ?? 'valid'; // 'valid' | 'errors'
$filterEvent = trim($_GET['event_name'] ?? '');
$filterPage  = trim($_GET['page_path'] ?? '');

$fromDateTime = $fromDate . ' 00:00:00';
$toDateTime   = $toDate . ' 23:59:59';

// 1. Đếm tổng số lỗi tiếp nhận
$stmtErrCount = $pdo->prepare('SELECT COUNT(*) FROM `event_ingest_errors` WHERE `website_id` = ? AND `received_at` BETWEEN ? AND ?');
$stmtErrCount->execute([$selectedWebsiteId, $fromDateTime, $toDateTime]);
$totalErrors = (int)$stmtErrCount->fetchColumn();

// 2. Dữ liệu Tab 1: Sự kiện hợp lệ (events)
$eventsSql = '
    SELECT `event_row_id`, `event_id`, `session_id`, `event_name`, `page_path`, `occurred_at`, `received_at`, `payload_hash`, `properties`
    FROM `events`
    WHERE `website_id` = ? AND `occurred_at` BETWEEN ? AND ?
';
$params = [$selectedWebsiteId, $fromDateTime, $toDateTime];
if (!empty($filterEvent)) {
    $eventsSql .= ' AND `event_name` = ?';
    $params[] = $filterEvent;
}
if (!empty($filterPage)) {
    $eventsSql .= ' AND `page_path` LIKE ?';
    $params[] = '%' . $filterPage . '%';
}
$eventsSql .= ' ORDER BY `event_row_id` DESC, `occurred_at` DESC LIMIT 100';

$stmtEvents = $pdo->prepare($eventsSql);
$stmtEvents->execute($params);
$validEvents = $stmtEvents->fetchAll();

// 3. Dữ liệu Tab 2: Nhật ký lỗi tiếp nhận (event_ingest_errors)
$stmtErrors = $pdo->prepare('
    SELECT `error_id`, `event_id`, `error_code`, `payload_hash`, `occurred_at`, `received_at`, `details`
    FROM `event_ingest_errors`
    WHERE `website_id` = ? AND `received_at` BETWEEN ? AND ?
    ORDER BY `received_at` DESC, `error_id` DESC
    LIMIT 100
');
$stmtErrors->execute([$selectedWebsiteId, $fromDateTime, $toDateTime]);
$errorLogs = $stmtErrors->fetchAll();
?>

<!-- TAB NAVIGATION -->
<div style="display:flex; gap:0.65rem; margin-bottom:1.5rem; border-bottom:1px solid var(--border-color); padding-bottom:0.75rem; flex-wrap:wrap;">
    <a href="?website_id=<?= $selectedWebsiteId ?>&from_date=<?= e($fromDate) ?>&to_date=<?= e($toDate) ?>&tab=valid" 
       class="btn <?= $viewTab === 'valid' ? 'btn-primary' : 'btn-outline' ?> btn-sm">
        <?= render_icon('check-circle', '', 14) ?>
        Sự Kiện Hợp Lệ Đã Tiếp Nhận (<?= count($validEvents) ?>)
    </a>
    <a href="?website_id=<?= $selectedWebsiteId ?>&from_date=<?= e($fromDate) ?>&to_date=<?= e($toDate) ?>&tab=errors" 
       class="btn <?= $viewTab === 'errors' ? 'btn-danger' : 'btn-outline' ?> btn-sm">
        <?= render_icon('alert-triangle', '', 14) ?>
        Nhật Ký Lỗi Tiếp Nhận & Xung Đột (<?= $totalErrors ?>)
    </a>
</div>

<?php if ($viewTab === 'valid'): ?>
    <!-- TAB 1: SỰ KIỆN HỢP LỆ -->
    <div class="card-section">
        <div class="card-title-bar">
            <h3>
                <?= render_icon('activity', '', 18, 'var(--primary)') ?>
                Danh Sách Sự Kiện Hợp Lệ (Tối đa 100 bản ghi mới nhất)
            </h3>
            <span class="badge badge-green">
                <?= render_icon('shield-check', '', 12) ?>
                Tự động hash SHA-256
            </span>
        </div>

        <!-- Bộ lọc bổ sung -->
        <form method="GET" style="display:flex; gap:0.75rem; margin-bottom:1.25rem; flex-wrap:wrap; align-items:center;">
            <input type="hidden" name="website_id" value="<?= $selectedWebsiteId ?>">
            <input type="hidden" name="from_date" value="<?= e($fromDate) ?>">
            <input type="hidden" name="to_date" value="<?= e($toDate) ?>">
            <input type="hidden" name="tab" value="valid">

            <select name="event_name" class="form-select">
                <option value="">-- Tất cả loại sự kiện --</option>
                <option value="page_view" <?= $filterEvent === 'page_view' ? 'selected' : '' ?>>page_view</option>
                <option value="button_click" <?= $filterEvent === 'button_click' ? 'selected' : '' ?>>button_click</option>
                <option value="download_start" <?= $filterEvent === 'download_start' ? 'selected' : '' ?>>download_start</option>
                <option value="download_complete" <?= $filterEvent === 'download_complete' ? 'selected' : '' ?>>download_complete</option>
            </select>

            <input type="text" name="page_path" class="form-input" placeholder="Lọc theo path (vd: /products)" value="<?= e($filterPage) ?>">
            <button type="submit" class="btn btn-primary btn-sm">
                <?= render_icon('filter', '', 13) ?>
                Lọc
            </button>
            <?php if (!empty($filterEvent) || !empty($filterPage)): ?>
                <a href="?website_id=<?= $selectedWebsiteId ?>&from_date=<?= e($fromDate) ?>&to_date=<?= e($toDate) ?>&tab=valid" class="btn btn-outline btn-sm">
                    <?= render_icon('refresh-cw', '', 12) ?>
                    Xóa lọc
                </a>
            <?php endif; ?>
        </form>

        <?php if (empty($validEvents)): ?>
            <div class="empty-state">
                <div class="empty-icon"><?= render_icon('search', '', 24) ?></div>
                <h4>Không tìm thấy sự kiện nào trong khoảng thời gian này</h4>
                <p>Hãy chọn khoảng ngày rộng hơn hoặc bấm nút xóa lọc để xem toàn bộ sự kiện.</p>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th style="width:75px;">Row ID</th>
                            <th>Thời Điểm Xảy Ra</th>
                            <th>Sự Kiện</th>
                            <th>Đường Dẫn</th>
                            <th>Event ID (Client UUID)</th>
                            <th>Session ID</th>
                            <th>Payload Hash (SHA-256)</th>
                            <th>Thuộc Tính (An Toàn)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($validEvents as $row): ?>
                            <tr>
                                <td><strong>#<?= (int)$row['event_row_id'] ?></strong></td>
                                <td style="white-space:nowrap; color:var(--text-secondary);">
                                    <?= date('H:i:s d/m/Y', strtotime($row['occurred_at'])) ?>
                                </td>
                                <td>
                                    <?php
                                    $bClass = 'badge-gray';
                                    $iconName = 'activity';
                                    if ($row['event_name'] === 'page_view') { $bClass = 'badge-blue'; $iconName = 'eye'; }
                                    elseif ($row['event_name'] === 'button_click') { $bClass = 'badge-green'; $iconName = 'zap'; }
                                    elseif ($row['event_name'] === 'download_start') { $bClass = 'badge-yellow'; $iconName = 'download'; }
                                    elseif ($row['event_name'] === 'download_complete') { $bClass = 'badge-purple'; $iconName = 'check-circle'; }
                                    ?>
                                    <span class="badge <?= $bClass ?>">
                                        <?= render_icon($iconName, '', 11) ?>
                                        <?= e($row['event_name']) ?>
                                    </span>
                                </td>
                                <td><code><?= e($row['page_path']) ?></code></td>
                                <td title="<?= e($row['event_id']) ?>">
                                    <span style="font-family:monospace; font-size:0.75rem;"><?= substr(e($row['event_id']), 0, 14) ?>...</span>
                                </td>
                                <td title="<?= e($row['session_id']) ?>">
                                    <span style="font-family:monospace; font-size:0.75rem;"><?= substr(e($row['session_id']), 0, 12) ?>...</span>
                                </td>
                                <td title="<?= e($row['payload_hash']) ?>">
                                    <span style="font-family:monospace; font-size:0.75rem; color:#64748b;">
                                        <?= substr(e($row['payload_hash']), 0, 10) ?>...<?= substr(e($row['payload_hash']), -6) ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if (!empty($row['properties'])): ?>
                                        <code style="font-size:0.75rem; color:#0f766e;"><?= e($row['properties']) ?></code>
                                    <?php else: ?>
                                        <span style="color:var(--text-muted); font-size:0.8rem;">null</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

<?php else: ?>
    <!-- TAB 2: NHẬT KÝ LỖI TIẾP NHẬN -->
    <div class="card-section">
        <div class="card-title-bar">
            <h3>
                <?= render_icon('alert-triangle', '', 18, 'var(--accent-red)') ?>
                Nhật Ký Lỗi Tiếp Nhận & Phát Hiện Xung Đột (event_ingest_errors)
            </h3>
            <span class="badge badge-red">
                <?= render_icon('shield', '', 12) ?>
                Giám sát bảo mật & Chống trùng
            </span>
        </div>

        <p style="color:var(--text-secondary); font-size:0.88rem; margin-bottom:1.25rem; line-height:1.5;">
            Bảng này ghi nhận các trường hợp gửi trùng cùng ID nhưng khác nội dung (<code>PAYLOAD_CONFLICT</code>), sự kiện đến muộn quá hạn (<code>LATE_EVENT_EXCEEDED</code>), hoặc sự kiện chưa được khai báo (<code>EVENT_NOT_DEFINED</code>).
        </p>

        <?php if (empty($errorLogs)): ?>
            <div class="empty-state">
                <div class="empty-icon" style="background:#ecfdf5; color:#10b981;">
                    <?= render_icon('check-circle', '', 24) ?>
                </div>
                <h4>Không có lỗi tiếp nhận nào</h4>
                <p>Tất cả sự kiện gửi đến đều hợp lệ và tuân thủ chặt chẽ chính sách tiếp nhận dữ liệu.</p>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th style="width:80px;">Mã Lỗi ID</th>
                            <th>Thời Điểm Nhận</th>
                            <th>Mã Lỗi (Error Code)</th>
                            <th>Event ID Gửi Lên</th>
                            <th>Mã Băm Payload</th>
                            <th>Chi Tiết Lỗi / Mô Tả</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($errorLogs as $err): ?>
                            <tr>
                                <td><strong>#<?= (int)$err['error_id'] ?></strong></td>
                                <td style="white-space:nowrap; color:var(--text-secondary);">
                                    <?= date('H:i:s d/m/Y', strtotime($err['received_at'])) ?>
                                </td>
                                <td>
                                    <?php if ($err['error_code'] === 'PAYLOAD_CONFLICT'): ?>
                                        <span class="badge badge-red">
                                            <?= render_icon('alert-circle', '', 11) ?>
                                            PAYLOAD_CONFLICT
                                        </span>
                                    <?php elseif ($err['error_code'] === 'LATE_EVENT_EXCEEDED'): ?>
                                        <span class="badge badge-yellow">
                                            <?= render_icon('clock', '', 11) ?>
                                            LATE_EVENT_EXCEEDED
                                        </span>
                                    <?php else: ?>
                                        <span class="badge badge-gray">
                                            <?= render_icon('alert-circle', '', 11) ?>
                                            <?= e($err['error_code']) ?>
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if (!empty($err['event_id'])): ?>
                                        <code style="font-size:0.75rem;"><?= e($err['event_id']) ?></code>
                                    <?php else: ?>
                                        <span style="color:var(--text-muted);">-</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if (!empty($err['payload_hash'])): ?>
                                        <span style="font-family:monospace; font-size:0.75rem; color:#64748b;">
                                            <?= substr(e($err['payload_hash']), 0, 12) ?>...
                                        </span>
                                    <?php else: ?>
                                        <span style="color:var(--text-muted);">-</span>
                                    <?php endif; ?>
                                </td>
                                <td style="color:var(--accent-red-text); font-weight:500;">
                                    <?= e($err['details'] ?? 'Không có chi tiết') ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
