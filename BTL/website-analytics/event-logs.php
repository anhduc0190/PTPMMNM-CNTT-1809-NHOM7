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

// Đếm tổng số sự kiện hợp lệ trong khoảng thời gian này (không bị giới hạn bởi bộ lọc)
$stmtTotalEvents = $pdo->prepare('SELECT COUNT(*) FROM `events` WHERE `website_id` = ? AND `occurred_at` BETWEEN ? AND ?');
$stmtTotalEvents->execute([$selectedWebsiteId, $fromDateTime, $toDateTime]);
$totalAllValidEvents = (int)$stmtTotalEvents->fetchColumn();

// Thống kê số lượng từng loại sự kiện (theo website và khoảng thời gian)
$stmtEvtStats = $pdo->prepare('
    SELECT `event_name`, COUNT(*) as cnt 
    FROM `events` 
    WHERE `website_id` = ? AND `occurred_at` BETWEEN ? AND ? 
    GROUP BY `event_name` 
    ORDER BY cnt DESC
');
$stmtEvtStats->execute([$selectedWebsiteId, $fromDateTime, $toDateTime]);
$eventCountsRaw = $stmtEvtStats->fetchAll();
$eventCounts = [];
foreach ($eventCountsRaw as $er) {
    $eventCounts[$er['event_name']] = (int)$er['cnt'];
}

// Lấy thêm các sự kiện đã khai báo trong event_definitions (kể cả sự kiện mới chưa phát sinh dữ liệu)
$stmtDefs = $pdo->prepare('SELECT `event_name` FROM `event_definitions` WHERE `website_id` = ? AND `enabled` = 1 ORDER BY `event_name` ASC');
$stmtDefs->execute([$selectedWebsiteId]);
$definedEvents = $stmtDefs->fetchAll(PDO::FETCH_COLUMN);

// Hợp nhất danh sách tất cả loại sự kiện có sẵn
$allEventTypes = array_unique(array_merge(array_keys($eventCounts), $definedEvents));
sort($allEventTypes);

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

// 3. Dữ liệu Tab 2: Nhật ký lỗi tiếp nhận (event_ingest_errors) kết hợp bản ghi gốc
$stmtErrors = $pdo->prepare('
    SELECT 
        err.`error_id`, 
        err.`website_id`,
        err.`event_id`, 
        err.`error_code`, 
        err.`payload_hash` AS `err_hash`, 
        err.`occurred_at` AS `err_occurred_at`, 
        err.`received_at`, 
        err.`details`,
        orig.`event_row_id` AS `orig_row_id`,
        orig.`event_name` AS `orig_event_name`,
        orig.`page_path` AS `orig_page_path`,
        orig.`payload_hash` AS `orig_hash`,
        orig.`properties` AS `orig_properties`,
        orig.`occurred_at` AS `orig_occurred_at`
    FROM `event_ingest_errors` err
    LEFT JOIN `events` orig ON orig.`website_id` = err.`website_id` AND orig.`event_id` = err.`event_id`
    WHERE err.`website_id` = ? AND err.`received_at` BETWEEN ? AND ?
    ORDER BY err.`received_at` DESC, err.`error_id` DESC
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
        Sự Kiện Hợp Lệ Đã Tiếp Nhận (<?= number_format($totalAllValidEvents) ?>)
    </a>
    <a href="?website_id=<?= $selectedWebsiteId ?>&from_date=<?= e($fromDate) ?>&to_date=<?= e($toDate) ?>&tab=errors" 
       class="btn <?= $viewTab === 'errors' ? 'btn-danger' : 'btn-outline' ?> btn-sm">
        <?= render_icon('alert-triangle', '', 14) ?>
        Nhật Ký Lỗi Tiếp Nhận & Xung Đột (<?= number_format($totalErrors) ?>)
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

        <!-- KHU VỰC TÌM KIẾM & BỘ LỌC SỰ KIỆN -->
        <div style="background:var(--bg-muted); border:1px solid var(--border-color); border-radius:10px; padding:1.1rem 1.25rem; margin-bottom:1.25rem;">
            <form method="GET" id="event-filter-form" style="display:flex; gap:0.75rem; flex-wrap:wrap; align-items:flex-end;">
                <input type="hidden" name="website_id" value="<?= $selectedWebsiteId ?>">
                <input type="hidden" name="from_date" value="<?= e($fromDate) ?>">
                <input type="hidden" name="to_date" value="<?= e($toDate) ?>">
                <input type="hidden" name="tab" value="valid">

                <!-- 1. Dropdown chọn loại sự kiện -->
                <div style="min-width:260px; flex:1;">
                    <label style="display:flex; align-items:center; gap:0.35rem; font-size:0.78rem; font-weight:700; color:var(--text-secondary); margin-bottom:0.35rem;">
                        <?= render_icon('filter', '', 12) ?>
                        Chọn loại sự kiện (Tự động lọc ngay):
                    </label>
                    <select name="event_name" id="select-event-name" class="form-select" style="width:100%; height:38px; font-weight:500;" onchange="this.form.submit()">
                        <option value="">-- Tất cả loại sự kiện (<?= number_format($totalAllValidEvents) ?>) --</option>
                        <?php foreach ($allEventTypes as $evt): ?>
                            <?php $cnt = $eventCounts[$evt] ?? 0; ?>
                            <option value="<?= e($evt) ?>" <?= $filterEvent === $evt ? 'selected' : '' ?>>
                                <?= e($evt) ?> (<?= number_format($cnt) ?> sự kiện)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- 2. Lọc theo Path -->
                <div style="min-width:200px; flex:1;">
                    <label style="display:flex; align-items:center; gap:0.35rem; font-size:0.78rem; font-weight:700; color:var(--text-secondary); margin-bottom:0.35rem;">
                        <?= render_icon('globe', '', 12) ?>
                        Đường dẫn trang (Path):
                    </label>
                    <input type="text" name="page_path" class="form-input" style="width:100%; height:38px;" placeholder="vd: /products, /checkout" value="<?= e($filterPage) ?>">
                </div>

                <!-- 3. Nút Lọc và Nút Hiện Tất Cả -->
                <div style="display:flex; gap:0.5rem; align-items:center;">
                    <button type="submit" class="btn btn-primary btn-sm" style="height:38px; padding:0 1.1rem; display:inline-flex; align-items:center; gap:0.4rem;">
                        <?= render_icon('filter', '', 13) ?>
                        Lọc Dữ Liệu
                    </button>
                    <?php if (!empty($filterEvent) || !empty($filterPage)): ?>
                        <a href="?website_id=<?= $selectedWebsiteId ?>&from_date=<?= e($fromDate) ?>&to_date=<?= e($toDate) ?>&tab=valid" 
                           class="btn btn-outline btn-sm" 
                           style="height:38px; display:inline-flex; align-items:center; gap:0.4rem; color:var(--primary); border-color:var(--primary);"
                           title="Hiển thị lại toàn bộ sự kiện">
                            <?= render_icon('refresh-cw', '', 12) ?>
                            Hiện tất cả sự kiện
                        </a>
                    <?php endif; ?>
                </div>
            </form>

            <!-- 4. HÀNG NÚT BẤM NHANH (QUICK-FILTER PILLS) -->
            <div style="margin-top:0.9rem; padding-top:0.75rem; border-top:1px dashed var(--border-color); display:flex; align-items:center; gap:0.5rem; flex-wrap:wrap;">
                <span style="font-size:0.78rem; font-weight:700; color:var(--text-secondary); display:inline-flex; align-items:center; gap:0.35rem; margin-right:0.25rem;">
                    <?= render_icon('sparkles', '', 13, 'var(--primary)') ?>
                    Chọn nhanh:
                </span>

                <!-- Pill Tất Cả -->
                <a href="?website_id=<?= $selectedWebsiteId ?>&from_date=<?= e($fromDate) ?>&to_date=<?= e($toDate) ?>&tab=valid&page_path=<?= urlencode($filterPage) ?>" 
                   class="badge <?= empty($filterEvent) ? 'badge-blue' : 'badge-gray' ?>" 
                   style="cursor:pointer; text-decoration:none; padding:0.38rem 0.75rem; font-size:0.78rem; border-radius:6px; font-weight:<?= empty($filterEvent) ? '700' : '500' ?>; border:1px solid <?= empty($filterEvent) ? 'var(--primary)' : 'var(--border-color)' ?>;"
                   title="Xem tất cả loại sự kiện">
                    <?= render_icon('check-circle', '', 11) ?>
                    Tất cả (<?= number_format($totalAllValidEvents) ?>)
                </a>

                <!-- Pills từng loại sự kiện -->
                <?php foreach ($allEventTypes as $evt): ?>
                    <?php 
                    $cnt = $eventCounts[$evt] ?? 0;
                    $isActive = ($filterEvent === $evt);
                    $iconPill = 'activity';
                    if ($evt === 'page_view') $iconPill = 'eye';
                    elseif ($evt === 'button_click') $iconPill = 'zap';
                    elseif ($evt === 'download_start') $iconPill = 'download';
                    elseif ($evt === 'download_complete') $iconPill = 'check';
                    ?>
                    <a href="?website_id=<?= $selectedWebsiteId ?>&from_date=<?= e($fromDate) ?>&to_date=<?= e($toDate) ?>&tab=valid&event_name=<?= urlencode($evt) ?>&page_path=<?= urlencode($filterPage) ?>" 
                       class="badge <?= $isActive ? 'badge-blue' : 'badge-gray' ?>" 
                       style="cursor:pointer; text-decoration:none; padding:0.38rem 0.75rem; font-size:0.78rem; border-radius:6px; font-weight:<?= $isActive ? '700' : '500' ?>; border:1px solid <?= $isActive ? 'var(--primary)' : 'var(--border-color)' ?>;"
                       title="Chỉ xem sự kiện <?= e($evt) ?>">
                        <?= render_icon($iconPill, '', 11) ?>
                        <?= e($evt) ?> (<?= number_format($cnt) ?>)
                    </a>
                <?php endforeach; ?>
            </div>

            <!-- 5. TÌM KIẾM TRỰC TIẾP TỨC THÌ TRÊN BẢNG (CLIENT-SIDE LIVE SEARCH) -->
            <div style="margin-top:0.85rem; padding-top:0.75rem; border-top:1px dashed var(--border-color); display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:0.65rem;">
                <div style="position:relative; width:100%; max-width:440px;">
                    <input type="text" id="live-table-search" class="form-input" style="width:100%; height:36px; padding-left:2.3rem; font-size:0.82rem;" placeholder="Tìm nhanh theo từ khóa (sự kiện, path, UUID, session ID...)" onkeyup="filterTableRows(this.value)">
                    <div style="position:absolute; left:0.75rem; top:50%; transform:translateY(-50%); color:var(--text-muted); pointer-events:none;">
                        <?= render_icon('search', '', 14) ?>
                    </div>
                </div>
                <div id="live-search-count" style="font-size:0.8rem; color:var(--text-secondary); font-weight:500;">
                    Hiển thị <strong><?= count($validEvents) ?></strong> / <?= number_format($totalAllValidEvents) ?> bản ghi
                </div>
            </div>
        </div>

        <?php if (!empty($filterEvent) || !empty($filterPage)): ?>
            <!-- Thanh thông báo trạng thái lọc đang hoạt động -->
            <div style="background:rgba(59, 130, 246, 0.08); border:1px solid rgba(59, 130, 246, 0.25); border-radius:8px; padding:0.65rem 1rem; margin-bottom:1rem; display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:0.5rem;">
                <div style="display:flex; align-items:center; gap:0.5rem; font-size:0.85rem;">
                    <?= render_icon('filter', '', 15, 'var(--primary)') ?>
                    <span>
                        Đang lọc: 
                        <?php if (!empty($filterEvent)): ?>
                            Loại sự kiện <strong style="color:var(--primary); font-family:monospace;"><?= e($filterEvent) ?></strong>
                        <?php endif; ?>
                        <?php if (!empty($filterPage)): ?>
                            <?= !empty($filterEvent) ? ' &bull; ' : '' ?>Đường dẫn chứa <code style="color:var(--primary);"><?= e($filterPage) ?></code>
                        <?php endif; ?>
                        &mdash; Tìm thấy <strong><?= count($validEvents) ?></strong> bản ghi
                    </span>
                </div>
                <a href="?website_id=<?= $selectedWebsiteId ?>&from_date=<?= e($fromDate) ?>&to_date=<?= e($toDate) ?>&tab=valid" class="btn btn-outline btn-sm" style="font-size:0.78rem;">
                    <?= render_icon('x', '', 12) ?>
                    Xem tất cả loại sự kiện
                </a>
            </div>
        <?php endif; ?>

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
                    <tbody id="valid-events-tbody">
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
                            <th style="width:75px;">Mã Lỗi ID</th>
                            <th>Thời Điểm Nhận</th>
                            <th>Mã Lỗi (Error Code)</th>
                            <th>Event ID Gửi Lên</th>
                            <th>Mã Băm Payload</th>
                            <th>Chi Tiết Lỗi &amp; Đối Chiếu Xung Đột</th>
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
                                            <?= render_icon('alert-triangle', '', 11) ?>
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
                                        <code style="font-size:0.75rem; font-weight:600;"><?= e($err['event_id']) ?></code>
                                    <?php else: ?>
                                        <span style="color:var(--text-muted);">-</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if (!empty($err['err_hash'])): ?>
                                        <span style="font-family:monospace; font-size:0.75rem; color:#b91c1c; font-weight:600;">
                                            <?= substr(e($err['err_hash']), 0, 10) ?>...<?= substr(e($err['err_hash']), -6) ?>
                                        </span>
                                    <?php else: ?>
                                        <span style="color:var(--text-muted);">-</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($err['error_code'] === 'PAYLOAD_CONFLICT'): ?>
                                        <div style="color:#b91c1c; font-weight:600; margin-bottom:0.25rem;">
                                            <?= e($err['details'] ?? 'Cùng event_id nhưng khác nội dung payload') ?>
                                        </div>

                                        <?php if (!empty($err['orig_row_id'])): ?>
                                            <details class="conflict-inspect-details" style="margin-top:0.4rem; cursor:pointer;">
                                                <summary style="font-size:0.78rem; font-weight:600; color:#2563eb; outline:none; user-select:none; display:flex; align-items:center; gap:0.35rem;">
                                                    <?= render_icon('search', '', 12) ?>
                                                    Bấm xem đối chiếu: Bản ghi gốc (Row #<?= (int)$err['orig_row_id'] ?>) vs Yêu cầu xung đột
                                                </summary>
                                                <div style="margin-top:0.5rem; background:#f8fafc; border:1px solid #cbd5e1; border-radius:8px; padding:0.75rem; font-size:0.78rem; cursor:default;">
                                                    <div style="display:grid; grid-template-columns:1fr 1fr; gap:0.65rem;">
                                                        <!-- Cột 1: Bản ghi gốc đã lưu trong CSDL -->
                                                        <div style="background:#ffffff; border:1px solid #a7f3d0; border-left:3px solid #10b981; padding:0.6rem; border-radius:6px;">
                                                            <div style="font-weight:700; color:#047857; margin-bottom:0.35rem; display:flex; align-items:center; gap:0.3rem;">
                                                                <?= render_icon('check-circle', '', 12, '#047857') ?>
                                                                Bản Ghi Gốc Trong CSDL (#<?= (int)$err['orig_row_id'] ?>)
                                                            </div>
                                                            <div style="margin-bottom:0.2rem;"><strong>Sự kiện:</strong> <code><?= e($err['orig_event_name']) ?></code></div>
                                                            <div style="margin-bottom:0.2rem;"><strong>Đường dẫn:</strong> <code><?= e($err['orig_page_path']) ?></code></div>
                                                            <div style="margin-bottom:0.2rem;">
                                                                <strong>Thuộc tính (Props):</strong>
                                                                <code style="display:block; margin-top:2px; word-break:break-all; color:#065f46; background:#ecfdf5; padding:0.25rem; border-radius:4px;">
                                                                    <?= e($err['orig_properties'] ?: 'null') ?>
                                                                </code>
                                                            </div>
                                                            <div><strong>Mã băm gốc:</strong> <code style="font-size:0.72rem; color:#64748b;"><?= substr(e($err['orig_hash']), 0, 16) ?>...</code></div>
                                                        </div>

                                                        <!-- Cột 2: Yêu cầu gửi lại bị xung đột -->
                                                        <div style="background:#ffffff; border:1px solid #fecaca; border-left:3px solid #ef4444; padding:0.6rem; border-radius:6px;">
                                                            <div style="font-weight:700; color:#b91c1c; margin-bottom:0.35rem; display:flex; align-items:center; gap:0.3rem;">
                                                                <?= render_icon('alert-triangle', '', 12, '#b91c1c') ?>
                                                                Lần Gửi Lại Bị Xung Đột (HTTP 409)
                                                            </div>
                                                            <div style="margin-bottom:0.2rem;"><strong>Cùng Event ID:</strong> <code><?= e($err['event_id']) ?></code></div>
                                                            <div style="margin-bottom:0.2rem;"><strong>Thời điểm gửi:</strong> <?= date('H:i:s d/m/Y', strtotime($err['err_occurred_at'])) ?></div>
                                                            <div style="margin-bottom:0.2rem;">
                                                                <strong>Nội dung gửi lên:</strong>
                                                                <span style="display:block; margin-top:2px; color:#991b1b; font-style:italic;">(Nội dung payload bị can thiệp/sửa đổi)</span>
                                                            </div>
                                                            <div><strong>Mã băm mới:</strong> <code style="font-size:0.72rem; color:#b91c1c;"><?= substr(e($err['err_hash']), 0, 16) ?>...</code></div>
                                                        </div>
                                                    </div>
                                                    <div style="margin-top:0.5rem; padding-top:0.4rem; border-top:1px dashed #cbd5e1; font-size:0.75rem; color:#475569; display:flex; align-items:center; gap:0.35rem;">
                                                        <?= render_icon('shield-check', '', 14, '#047857') ?>
                                                        <span><strong>Chốt chặn an toàn:</strong> Do mã SHA-256 mới khác với mã gốc, backend từ chối ghi đè sự kiện và trả về HTTP 409 để chống gian lận dữ liệu.</span>
                                                    </div>
                                                </div>
                                            </details>
                                        <?php else: ?>
                                            <div style="font-size:0.78rem; color:#64748b; font-style:italic;">
                                                Phát hiện gửi lại cùng Event ID nhưng nội dung bị sai lệch SHA-256.
                                            </div>
                                        <?php endif; ?>

                                    <?php elseif ($err['error_code'] === 'LATE_EVENT_EXCEEDED'): ?>
                                        <div style="color:#b45309; font-weight:600;">
                                            <?= e($err['details'] ?? 'Sự kiện đến muộn quá 24 giờ') ?>
                                        </div>
                                        <div style="font-size:0.75rem; color:#64748b; margin-top:0.2rem;">
                                            Thời điểm xảy ra: <code><?= date('H:i:s d/m/Y', strtotime($err['err_occurred_at'])) ?></code> (Cũ hơn 24 giờ so với quy định)
                                        </div>

                                    <?php else: ?>
                                        <div style="color:var(--text-secondary); font-weight:500;">
                                            <?= e($err['details'] ?? 'Sự kiện chưa được cấu hình hoặc đã bị vô hiệu hoá') ?>
                                        </div>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
<?php endif; ?>

<script>
function filterTableRows(query) {
    const q = (query || '').toLowerCase().trim();
    const tbody = document.getElementById('valid-events-tbody');
    if (!tbody) return;
    const rows = tbody.querySelectorAll('tr');
    let visibleCount = 0;
    let actualRowsCount = 0;
    
    rows.forEach(row => {
        if (row.id === 'live-no-match-row') return;
        actualRowsCount++;
        const text = row.textContent.toLowerCase();
        if (!q || text.includes(q)) {
            row.style.display = '';
            visibleCount++;
        } else {
            row.style.display = 'none';
        }
    });

    let noMatchRow = document.getElementById('live-no-match-row');
    if (visibleCount === 0 && actualRowsCount > 0) {
        if (!noMatchRow) {
            noMatchRow = document.createElement('tr');
            noMatchRow.id = 'live-no-match-row';
            noMatchRow.innerHTML = '<td colspan="8" style="text-align:center; padding:2rem; color:var(--text-muted); font-size:0.88rem;">Không tìm thấy sự kiện nào khớp với từ khóa: <strong></strong></td>';
            tbody.appendChild(noMatchRow);
        }
        noMatchRow.style.display = '';
        noMatchRow.querySelector('strong').textContent = query;
    } else if (noMatchRow) {
        noMatchRow.style.display = 'none';
    }

    const countElem = document.getElementById('live-search-count');
    if (countElem) {
        if (!q) {
            countElem.innerHTML = 'Hiển thị <strong>' + actualRowsCount + '</strong> bản ghi';
        } else {
            countElem.innerHTML = 'Tìm thấy <strong>' + visibleCount + '</strong> / ' + actualRowsCount + ' bản ghi';
        }
    }
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
