<?php
// ====================================================================
// BAO CAO THONG KE THEO THOI GIAN & TRANG (reports.php)
// Ho tro loc theo ngay, trang, su kien va xuat du lieu
// ====================================================================

$pageTitle = 'Báo Cáo Thống Kê Chi Tiết';
require_once __DIR__ . '/includes/header.php';

$pdo = get_db_connection();
$fromDateTime = $fromDate . ' 00:00:00';
$toDateTime   = $toDate . ' 23:59:59';

$filterEvent = trim($_GET['event_name'] ?? '');
$filterPage  = trim($_GET['page_path'] ?? '');

// 1. Thong ke tong the theo Loai Su Kien
$stmtEvents = $pdo->prepare('
    SELECT `event_name`, COUNT(*) AS `total_events`, COUNT(DISTINCT `session_id`) AS `unique_sessions`
    FROM `events`
    WHERE `website_id` = ? AND `occurred_at` BETWEEN ? AND ?
    GROUP BY `event_name`
    ORDER BY `total_events` DESC
');
$stmtEvents->execute([$selectedWebsiteId, $fromDateTime, $toDateTime]);
$eventBreakdown = $stmtEvents->fetchAll();

// 2. Thong ke Trang pho bien theo bo loc
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

// 3. Du lieu bieu do theo ngay
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

$dLabels = [];
$dViews  = [];
$dActions = [];
foreach ($dailyRows as $dr) {
    $dLabels[]  = date('d/m/Y', strtotime($dr['day']));
    $dViews[]   = (int)$dr['views'];
    $dActions[] = (int)$dr['actions'];
}
?>

<div class="card-section">
    <div class="card-title-bar">
        <h3>📊 Phân Bổ Các Loại Sự Kiện Trong Kỳ</h3>
        <a href="<?= $baseUrl ?>/api/analytics-export.php?website_id=<?= $selectedWebsiteId ?>&from_date=<?= e($fromDate) ?>&to_date=<?= e($toDate) ?>" class="btn btn-outline btn-sm">
            📥 Xuất Toàn Bộ Dữ Liệu Nguồn Ra CSV
        </a>
    </div>

    <div class="kpi-grid" style="margin-bottom:1rem;">
        <?php foreach ($eventBreakdown as $eb): ?>
            <div class="kpi-card">
                <div class="kpi-header">
                    <span class="kpi-title"><?= e($eb['event_name']) ?></span>
                    <span class="kpi-icon blue">📌</span>
                </div>
                <div class="kpi-value"><?= number_format((int)$eb['total_events']) ?></div>
                <div class="kpi-sub">Trong <?= number_format((int)$eb['unique_sessions']) ?> phiên truy cập</div>
            </div>
        <?php endforeach; ?>
        <?php if (empty($eventBreakdown)): ?>
            <div style="grid-column: 1/-1; color:#64748b; padding:1rem 0;">Chưa có dữ liệu sự kiện trong kỳ báo cáo đã chọn.</div>
        <?php endif; ?>
    </div>
</div>

<!-- BIEU DO THEO NGAY -->
<div class="card-section">
    <div class="card-title-bar">
        <h3>📅 Lượt Xem & Thao Tác Phân Bổ Theo Ngày Báo Cáo</h3>
    </div>
    <div style="height: 280px; position:relative;">
        <canvas id="dailyReportChart"></canvas>
    </div>
</div>

<!-- BANG CHI TIET THEO TRANG -->
<div class="card-section">
    <div class="card-title-bar">
        <h3>📄 Bảng Chi Tiết Thống Kê Theo Trang (Page Path)</h3>
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
                                <?= $ratio ?>%
                            </span>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (empty($pageStats)): ?>
                    <tr><td colspan="5" style="text-align:center; color:#94a3b8; padding:2rem;">Không có dữ liệu trang</td></tr>
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
                    borderRadius: 4
                },
                {
                    label: 'Lần thao tác (actions)',
                    data: <?= json_encode(!empty($dActions) ? $dActions : [0]) ?>,
                    backgroundColor: '#10b981',
                    borderRadius: 4
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: { beginAtZero: true, ticks: { precision: 0 } }
            }
        }
    });
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
