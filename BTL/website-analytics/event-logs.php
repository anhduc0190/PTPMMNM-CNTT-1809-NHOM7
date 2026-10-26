<?php
// ====================================================================
// NHAT KY SU KIEN & LOI TIEP NHAN (event-logs.php)
// Doi chieu ma bam payload, kiem tra xung dot va su kien hop le
// ====================================================================

$pageTitle = 'Nhật Ký Sự Kiện & Lỗi Tiếp Nhận';
require_once __DIR__ . '/includes/header.php';

$pdo = get_db_connection();

$viewTab   = $_GET['tab'] ?? 'valid'; // 'valid' | 'errors'
$filterEvent = trim($_GET['event_name'] ?? '');
$filterPage  = trim($_GET['page_path'] ?? '');

$fromDateTime = $fromDate . ' 00:00:00';
$toDateTime   = $toDate . ' 23:59:59';

// 1. Dem tong so loi tiep nhan
$stmtErrCount = $pdo->prepare('SELECT COUNT(*) FROM `event_ingest_errors` WHERE `website_id` = ? AND `received_at` BETWEEN ? AND ?');
$stmtErrCount->execute([$selectedWebsiteId, $fromDateTime, $toDateTime]);
$totalErrors = (int)$stmtErrCount->fetchColumn();

// 2. Du lieu Tab 1: Su kien hop le (events)
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
$eventsSql .= ' ORDER BY `occurred_at` DESC, `event_row_id` DESC LIMIT 100';

$stmtEvents = $pdo->prepare($eventsSql);
$stmtEvents->execute($params);
$validEvents = $stmtEvents->fetchAll();

// 3. Du lieu Tab 2: Nhat ky loi tiep nhan (event_ingest_errors)
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
<div style="display:flex; gap:0.5rem; margin-bottom:1.5rem; border-bottom:1px solid var(--border-color); padding-bottom:0.5rem;">
    <a href="?website_id=<?= $selectedWebsiteId ?>&from_date=<?= e($fromDate) ?>&to_date=<?= e($toDate) ?>&tab=valid" 
       class="btn <?= $viewTab === 'valid' ? 'btn-primary' : 'btn-outline' ?> btn-sm">
        ✅ Sự Kiện Hợp Lệ Đã Tiếp Nhận (<?= count($validEvents) ?>)
    </a>
    <a href="?website_id=<?= $selectedWebsiteId ?>&from_date=<?= e($fromDate) ?>&to_date=<?= e($toDate) ?>&tab=errors" 
       class="btn <?= $viewTab === 'errors' ? 'btn-danger' : 'btn-outline' ?> btn-sm">
        ⚠️ Nhật Ký Lỗi Tiếp Nhận & Xung Đột (<?= $totalErrors ?>)
    </a>
</div>

<?php if ($viewTab === 'valid'): ?>
    <!-- TAB 1: SU KIEN HOP LE -->
    <div class="card-section">
        <div class="card-title-bar">
            <h3>📜 Danh Sách Sự Kiện Hợp Lệ (Tối đa 100 bản ghi mới nhất)</h3>
            <span class="badge badge-green">Tự động hash SHA-256</span>
        </div>

        <!-- Bo loc bo sung -->
        <form method="GET" style="display:flex; gap:1rem; margin-bottom:1.25rem; flex-wrap:wrap; align-items:center;">
            <input type="hidden" name="website_id" value="<?= $selectedWebsiteId ?>">
            <input type="hidden" name="from_date" value="<?= e($fromDate) ?>">
            <input type="hidden" name="to_date" value="<?= e($toDate) ?>">
            <input type="hidden" name="tab" value="valid">

            <select name="event_name" class="form-select">
                <option value="">-- Tất cả sự kiện --</option>
                <option value="page_view" <?= $filterEvent === 'page_view' ? 'selected' : '' ?>>page_view</option>
                <option value="button_click" <?= $filterEvent === 'button_click' ? 'selected' : '' ?>>button_click</option>
                <option value="download_start" <?= $filterEvent === 'download_start' ? 'selected' : '' ?>>download_start</option>
                <option value="download_complete" <?= $filterEvent === 'download_complete' ? 'selected' : '' ?>>download_complete</option>
            </select>

            <input type="text" name="page_path" class="form-input" placeholder="Lọc theo path (vd: /products)" value="<?= e($filterPage) ?>">
            <button type="submit" class="btn btn-primary btn-sm">Áp Dụng Lọc</button>
            <?php if (!empty($filterEvent) || !empty($filterPage)): ?>
                <a href="?website_id=<?= $selectedWebsiteId ?>&from_date=<?= e($fromDate) ?>&to_date=<?= e($toDate) ?>&tab=valid" class="btn btn-outline btn-sm">Xóa lọc</a>
            <?php endif; ?>
        </form>

        <?php if (empty($validEvents)): ?>
            <div class="empty-state">
                <div class="icon">🔍</div>
                <h4>Không tìm thấy sự kiện nào trong khoảng thời gian này</h4>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Row ID</th>
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
                                <td>#<?= (int)$row['event_row_id'] ?></td>
                                <td style="white-space:nowrap;"><?= date('H:i:s d/m/Y', strtotime($row['occurred_at'])) ?></td>
                                <td>
                                    <?php
                                    $bClass = 'badge-gray';
                                    if ($row['event_name'] === 'page_view') $bClass = 'badge-blue';
                                    elseif ($row['event_name'] === 'button_click') $bClass = 'badge-green';
                                    elseif ($row['event_name'] === 'download_start') $bClass = 'badge-yellow';
                                    elseif ($row['event_name'] === 'download_complete') $bClass = 'badge-purple';
                                    ?>
                                    <span class="badge <?= $bClass ?>"><?= e($row['event_name']) ?></span>
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
                                        <span style="color:#94a3b8; font-size:0.8rem;">null</span>
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
    <!-- TAB 2: NHAT KY LOI TIEP NHAN -->
    <div class="card-section">
        <div class="card-title-bar">
            <h3>⚠️ Nhật Ký Lỗi Tiếp Nhận & Phát Hiện Xung Đột (event_ingest_errors)</h3>
            <span class="badge badge-red">Giám sát bảo mật & Chống trùng</span>
        </div>

        <p style="color:var(--text-secondary); font-size:0.9rem; margin-bottom:1.25rem;">
            Bảng này ghi nhận các trường hợp gửi trùng cùng ID nhưng khác nội dung (<code>PAYLOAD_CONFLICT</code>), sự kiện đến muộn quá hạn (<code>LATE_EVENT_EXCEEDED</code>), hoặc sự kiện chưa được khai báo (<code>EVENT_NOT_DEFINED</code>).
        </p>

        <?php if (empty($errorLogs)): ?>
            <div class="empty-state">
                <div class="icon">✨</div>
                <h4>Không có lỗi tiếp nhận nào</h4>
                <p>Tất cả sự kiện gửi đến đều hợp lệ hoặc chưa có yêu cầu xung đột nào.</p>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Mã Lỗi ID</th>
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
                                <td>#<?= (int)$err['error_id'] ?></td>
                                <td style="white-space:nowrap;"><?= date('H:i:s d/m/Y', strtotime($err['received_at'])) ?></td>
                                <td>
                                    <?php if ($err['error_code'] === 'PAYLOAD_CONFLICT'): ?>
                                        <span class="badge badge-red">PAYLOAD_CONFLICT (Xung đột)</span>
                                    <?php elseif ($err['error_code'] === 'LATE_EVENT_EXCEEDED'): ?>
                                        <span class="badge badge-yellow">LATE_EVENT_EXCEEDED (Quá hạn)</span>
                                    <?php else: ?>
                                        <span class="badge badge-gray"><?= e($err['error_code']) ?></span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if (!empty($err['event_id'])): ?>
                                        <code style="font-size:0.75rem;"><?= e($err['event_id']) ?></code>
                                    <?php else: ?>
                                        <span style="color:#94a3b8;">-</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if (!empty($err['payload_hash'])): ?>
                                        <span style="font-family:monospace; font-size:0.75rem; color:#64748b;">
                                            <?= substr(e($err['payload_hash']), 0, 12) ?>...
                                        </span>
                                    <?php else: ?>
                                        <span style="color:#94a3b8;">-</span>
                                    <?php endif; ?>
                                </td>
                                <td style="color:#dc2626; font-weight:500;">
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
