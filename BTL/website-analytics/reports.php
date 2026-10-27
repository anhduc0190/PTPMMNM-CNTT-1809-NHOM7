<?php
// ====================================================================
// BÁO CÁO THỐNG KÊ CHI TIẾT (reports.php)
// Báo cáo theo thời gian, phân bổ loại sự kiện và tương tác theo trang
// ====================================================================

$pageTitle = 'Báo Cáo Thống Kê Chi Tiết';
require_once __DIR__ . '/includes/header.php';

$pdo = get_db_connection();
$fromDateTime = $fromDate . ' 00:00:00';
$toDateTime   = $toDate . ' 23:59:59';

$filterEvent = trim($_GET['event_name'] ?? '');
$filterPage  = trim($_GET['page_path'] ?? '');

// 1. Thống kê tổng thể theo Loại Sự Kiện
$stmtEvents = $pdo->prepare('
    SELECT `event_name`, COUNT(*) AS `total_events`, COUNT(DISTINCT `session_id`) AS `unique_sessions`
    FROM `events`
    WHERE `website_id` = ? AND `occurred_at` BETWEEN ? AND ?
    GROUP BY `event_name`
    ORDER BY `total_events` DESC
');
$stmtEvents->execute([$selectedWebsiteId, $fromDateTime, $toDateTime]);
$eventBreakdown = $stmtEvents->fetchAll();

// 2. Thống kê Trang phổ biến theo bộ lọc
$pagesSql = '
    SELECT `page_path`, 
           SUM(CASE WHEN `event_name` = "page_view" THEN 1 ELSE 0 END) AS `views_count`,
           SUM(CASE WHEN `event_name` != "page_view" THEN 1 ELSE 0 END) AS `actions_count`,
           COUNT(DISTINCT `session_id`) AS `sessions_count`
    FROM `events`
    WHERE `website_id` = ? AND `occurred_at` BETWEEN ? AND ?
';
$pageParams = [$selectedWebsiteId, $fromDateTime, $toDateTime];
if (!empty($filterPage)) {
    $pagesSql .= ' AND `page_path` LIKE ?';
    $pageParams[] = '%' . $filterPage . '%';
}
$pagesSql .= ' GROUP BY `page_path` ORDER BY `views_count` DESC';

$stmtPages = $pdo->prepare($pagesSql);
$stmtPages->execute($pageParams);
$pageStats = $stmtPages->fetchAll();

// 3. Dữ liệu biểu đồ theo ngày
$stmtDaily = $pdo->prepare('
    SELECT DATE(`occurred_at`) AS `day`, 
           SUM(CASE WHEN `event_name` = "page_view" THEN 1 ELSE 0 END) AS `views`,
           SUM(CASE WHEN `event_name` != "page_view" THEN 1 ELSE 0 END) AS `actions`
    FROM `events`
    WHERE `website_id` = ? AND `occurred_at` BETWEEN ? AND ?
    GROUP BY DATE(`occurred_at`)
    ORDER BY `day` ASC
');
$stmtDaily->execute([$selectedWebsiteId, $fromDateTime, $toDateTime]);
$dailyRows = $stmtDaily->fetchAll();

$dLabels  = [];
$dViews   = [];
$dActions = [];
foreach ($dailyRows as $dr) {
    $dLabels[]  = date('d/m/Y', strtotime($dr['day']));
    $dViews[]   = (int)$dr['views'];
    $dActions[] = (int)$dr['actions'];
}
?>

<!-- PHÂN BỔ LOẠI SỰ KIỆN -->
<div class="card-section">
    <div class="card-title-bar">
        <h3>
            <?= render_icon('chart-bar', '', 18, 'var(--primary)') ?>
            Phân Bổ Các Loại Sự Kiện Trong Kỳ Báo Cáo
        </h3>
        <a href="<?= $baseUrl ?>/api/analytics-export.php?website_id=<?= $selectedWebsiteId ?>&from_date=<?= e($fromDate) ?>&to_date=<?= e($toDate) ?>" class="btn btn-outline btn-sm">
            <?= render_icon('download', '', 14) ?>
            Xuất Toàn Bộ Dữ Liệu Nguồn Ra CSV
        </a>
    </div>

    <div class="kpi-grid" style="margin-bottom:0.5rem;">
        <?php foreach ($eventBreakdown as $eb): ?>
            <?php
            $icon = 'activity';
            $wrapClass = 'blue';
            if ($eb['event_name'] === 'page_view') { $icon = 'eye'; $wrapClass = 'blue'; }
            elseif ($eb['event_name'] === 'button_click') { $icon = 'zap'; $wrapClass = 'green'; }
            elseif ($eb['event_name'] === 'download_start') { $icon = 'download'; $wrapClass = 'yellow'; }
            elseif ($eb['event_name'] === 'download_complete') { $icon = 'check-circle'; $wrapClass = 'purple'; }
            ?>
            <div class="kpi-card">
                <div class="kpi-header">
                    <span class="kpi-title"><?= e($eb['event_name']) ?></span>
                    <span class="kpi-icon-wrap <?= $wrapClass ?>">
                        <?= render_icon($icon, '', 17) ?>
                    </span>
                </div>
                <div class="kpi-value"><?= number_format((int)$eb['total_events']) ?></div>
                <div class="kpi-sub">Trong <?= number_format((int)$eb['unique_sessions']) ?> phiên truy cập</div>
            </div>
        <?php endforeach; ?>
        <?php if (empty($eventBreakdown)): ?>
            <div style="grid-column: 1/-1;" class="empty-state">
                <div class="empty-icon"><?= render_icon('inbox', '', 24) ?></div>
                <h4>Không có dữ liệu sự kiện trong kỳ</h4>
                <p>Vui lòng chọn khoảng thời gian khác hoặc kiểm tra lại website đang chọn.</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- BIỂU ĐỒ THEO NGÀY -->
<div class="card-section">
    <div class="card-title-bar">
        <h3>
            <?= render_icon('calendar', '', 18, 'var(--accent-blue)') ?>
            Lượt Xem & Thao Tác Phân Bổ Theo Ngày Báo Cáo
        </h3>
        <span class="badge badge-gray">
            <?= render_icon('activity', '', 12) ?>
            Dữ liệu tổng hợp
        </span>
    </div>
    <div style="height: 280px; position:relative;">
        <canvas id="dailyReportChart"></canvas>
    </div>
</div>

<!-- BẢNG CHI TIẾT THEO TRANG -->
<div class="card-section">
    <div class="card-title-bar">
        <h3>
            <?= render_icon('file-text', '', 18, 'var(--text-secondary)') ?>
            Bảng Chi Tiết Thống Kê Theo Trang (Page Path)
        </h3>
        <span class="badge badge-blue">
            <?= count($pageStats) ?> đường dẫn
        </span>
    </div>

    <div class="table-responsive">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Đường Dẫn Trang (page_path)</th>
                    <th>Lượt Xem Trang (page_view)</th>
                    <th>Lần Thao Tác (Actions)</th>
                    <th>Phiên Hoạt Động (Unique Sessions)</th>
                    <th>Tỷ Lệ Tương Tác / Lượt Xem</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($pageStats as $p): ?>
                    <?php 
                    $v = (int)$p['views_count'];
                    $a = (int)$p['actions_count'];
                    $ratio = $v > 0 ? round(($a / $v) * 100, 1) : 0;
                    ?>
                    <tr>
                        <td><strong><code><?= e($p['page_path']) ?></code></strong></td>
                        <td><?= number_format($v) ?></td>
                        <td><?= number_format($a) ?></td>
                        <td><?= number_format((int)$p['sessions_count']) ?></td>
                        <td>
                            <span class="badge <?= $ratio > 50 ? 'badge-green' : 'badge-gray' ?>">
                                <?= render_icon($ratio > 50 ? 'zap' : 'activity', '', 11) ?>
                                <?= $ratio ?>%
                            </span>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (empty($pageStats)): ?>
                    <tr>
                        <td colspan="5" style="text-align:center; color:var(--text-muted); padding:2.5rem;">
                            Không có dữ liệu trang nào trong khoảng thời gian đã chọn
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const ctx = document.getElementById('dailyReportChart').getContext('2d');
    new Chart(ctx, {
        type: 'bar',
        data: {
            labels: <?= json_encode(!empty($dLabels) ? $dLabels : ['Chưa có dữ liệu']) ?>,
            datasets: [
                {
                    label: 'Lượt xem trang (page_view)',
                    data: <?= json_encode(!empty($dViews) ? $dViews : [0]) ?>,
                    backgroundColor: '#3b82f6',
                    borderRadius: 4,
                    barPercentage: 0.7
                },
                {
                    label: 'Lần thao tác (actions)',
                    data: <?= json_encode(!empty($dActions) ? $dActions : [0]) ?>,
                    backgroundColor: '#10b981',
                    borderRadius: 4,
                    barPercentage: 0.7
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'top',
                    align: 'end',
                    labels: {
                        boxWidth: 10,
                        boxHeight: 10,
                        usePointStyle: true,
                        pointStyle: 'circle'
                    }
                },
                tooltip: {
                    backgroundColor: '#0f172a',
                    titleColor: '#ffffff',
                    bodyColor: '#f1f5f9',
                    cornerRadius: 6,
                    padding: 10
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: { precision: 0 },
                    grid: { color: 'rgba(226, 232, 240, 0.8)' }
                },
                x: {
                    grid: { display: false }
                }
            }
        }
    });
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
