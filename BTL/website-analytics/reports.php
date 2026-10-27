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

// 4. Thống kê lượng tương tác theo 24 khung giờ trong ngày (Hourly interaction traffic)
$stmtHourly = $pdo->prepare('
    SELECT 
        HOUR(`occurred_at`) AS `hr`,
        SUM(CASE WHEN `event_name` = "page_view" THEN 1 ELSE 0 END) AS `views`,
        SUM(CASE WHEN `event_name` != "page_view" THEN 1 ELSE 0 END) AS `actions`,
        COUNT(DISTINCT `session_id`) AS `sessions`,
        COUNT(*) AS `total`
    FROM `events`
    WHERE `website_id` = ? AND `occurred_at` BETWEEN ? AND ?
    GROUP BY `hr`
    ORDER BY `hr` ASC
');
$stmtHourly->execute([$selectedWebsiteId, $fromDateTime, $toDateTime]);
$hourlyRows = $stmtHourly->fetchAll();

$hourMap = [];
$peakHour = 0;
$peakCount = 0;
$totalHourlyEvents = 0;
foreach ($hourlyRows as $hr) {
    $h = (int)$hr['hr'];
    $hourMap[$h] = $hr;
    $cnt = (int)$hr['total'];
    $totalHourlyEvents += $cnt;
    if ($cnt > $peakCount) {
        $peakCount = $cnt;
        $peakHour = $h;
    }
}

$hLabels  = [];
$hViews   = [];
$hActions = [];
for ($i = 0; $i < 24; $i++) {
    $hLabels[]  = sprintf('%02dh', $i);
    $hViews[]   = isset($hourMap[$i]) ? (int)$hourMap[$i]['views'] : 0;
    $hActions[] = isset($hourMap[$i]) ? (int)$hourMap[$i]['actions'] : 0;
}
?>

<!-- PHÂN BỔ LOẠI SỰ KIỆN -->
<div class="card-section">
    <div class="card-title-bar">
        <h3>
            <?= render_icon('chart-bar', '', 18, 'var(--primary)') ?>
            Phân Bổ Các Loại Sự Kiện Trong Kỳ Báo Cáo
        </h3>
        <a href="<?= $baseUrl ?>/api/analytics-export.php?website_id=<?= $selectedWebsiteId ?>&from_date=<?= e($fromDate) ?>&to_date=<?= e($toDate) ?>" class="btn-export-csv" title="Xuất toàn bộ dữ liệu ra file CSV">
            <?= render_icon('download', '', 14) ?>
            <span>Xuất Toàn Bộ Dữ Liệu Ra CSV</span>
        </a>
    </div>

    <div class="kpi-grid" style="margin-bottom:0.5rem;">
        <?php foreach ($eventBreakdown as $eb): ?>
            <?php
            $icon = 'activity';
            $wrapClass = 'blue';
            if ($eb['event_name'] === 'page_view') { $icon = 'eye'; $wrapClass = 'blue'; }
            elseif ($eb['event_name'] === 'button_click') { $icon = 'mouse-pointer-click'; $wrapClass = 'green'; }
            elseif ($eb['event_name'] === 'download_start') { $icon = 'download'; $wrapClass = 'orange'; }
            elseif ($eb['event_name'] === 'download_complete') { $icon = 'check-circle'; $wrapClass = 'purple'; }
            ?>
            <div class="kpi-card">
                <div class="kpi-top-row">
                    <div class="kpi-icon-box <?= $wrapClass ?>">
                        <?= render_icon($icon, '', 20) ?>
                    </div>
                    <span class="badge badge-<?= $wrapClass ?>">
                        <?= e($eb['event_name']) ?>
                    </span>
                </div>
                <div class="kpi-value"><?= number_format((int)$eb['total_events']) ?></div>
                <div class="kpi-title"><?= e($eb['event_name']) ?></div>
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

<!-- BÁO CÁO PHÂN BỔ TƯƠNG TÁC THEO 24 KHUNG GIỜ TRONG NGÀY -->
<div class="card-section">
    <div class="card-title-bar">
        <div>
            <h3>
                <?= render_icon('clock', '', 18, 'var(--primary)') ?>
                Phân Bổ Lượng Tương Tác Theo Khung Giờ (24 Khung Giờ Trong Ngày)
            </h3>
            <div style="font-size:0.82rem; color:var(--text-muted); margin-top:2px;">
                Theo dõi thời điểm tương tác, giờ cao điểm và lượng thao tác tăng dần theo từng giờ
            </div>
        </div>
        <div style="display:flex; gap:0.5rem; align-items:center; flex-wrap:wrap;">
            <span class="badge badge-blue">
                <?= render_icon('zap', '', 11) ?>
                Giờ cao điểm: <strong><?= sprintf('%02d:00 - %02d:00', $peakHour, ($peakHour + 1) % 24) ?></strong> (<?= number_format($peakCount) ?> tương tác)
            </span>
            <span class="badge badge-green">
                <?= render_icon('radio', '', 11) ?>
                Khung giờ hiện tại: <strong><?= date('H:00') ?></strong>
            </span>
        </div>
    </div>

    <div class="chart-sub-legend" style="margin-bottom:0.85rem;">
        <div class="legend-item">
            <span class="dot-blue"></span>
            <span>Lượt xem trang (page_view)</span>
        </div>
        <div class="legend-item">
            <span class="dot-green"></span>
            <span>Lần thao tác (actions)</span>
        </div>
    </div>

    <div style="height: 280px; position:relative;">
        <canvas id="hourlyReportChart"></canvas>
    </div>
</div>

<!-- BIỂU ĐỒ THEO NGÀY -->
<div class="card-section">
    <div class="card-title-bar">
        <h3>
            <?= render_icon('calendar', '', 18, 'var(--primary)') ?>
            Lượt Xem &amp; Thao Tác Phân Bổ Theo Ngày Báo Cáo
        </h3>
        <span class="badge badge-gray">
            <?= render_icon('activity', '', 12) ?>
            Dữ liệu tổng hợp theo ngày
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
    // 1. Biểu đồ 24 khung giờ trong ngày (Hourly interaction chart)
    const ctxHourly = document.getElementById('hourlyReportChart').getContext('2d');
    new Chart(ctxHourly, {
        type: 'bar',
        data: {
            labels: <?= json_encode($hLabels) ?>,
            datasets: [
                {
                    label: 'Lượt xem trang (page_view)',
                    data: <?= json_encode($hViews) ?>,
                    backgroundColor: '#2563eb',
                    borderRadius: 4,
                    barPercentage: 0.65
                },
                {
                    label: 'Lần thao tác (actions)',
                    data: <?= json_encode($hActions) ?>,
                    backgroundColor: '#10b981',
                    borderRadius: 4,
                    barPercentage: 0.65
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false },
                tooltip: {
                    backgroundColor: '#0f172a',
                    titleColor: '#ffffff',
                    bodyColor: '#f1f5f9',
                    cornerRadius: 6,
                    padding: 8
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: { precision: 0 },
                    grid: { color: 'rgba(232, 237, 245, 0.9)' }
                },
                x: {
                    grid: { display: false },
                    ticks: { maxRotation: 0, autoSkip: false, font: { size: 10 } }
                }
            }
        }
    });

    // 2. Biểu đồ tổng hợp theo ngày (Daily report chart)
    const ctx = document.getElementById('dailyReportChart').getContext('2d');
    new Chart(ctx, {
        type: 'bar',
        data: {
            labels: <?= json_encode(!empty($dLabels) ? $dLabels : ['Chưa có dữ liệu']) ?>,
            datasets: [
                {
                    label: 'Lượt xem trang (page_view)',
                    data: <?= json_encode(!empty($dViews) ? $dViews : [0]) ?>,
                    backgroundColor: '#2563eb',
                    borderRadius: 4,
                    barPercentage: 0.6
                },
                {
                    label: 'Lần thao tác (actions)',
                    data: <?= json_encode(!empty($dActions) ? $dActions : [0]) ?>,
                    backgroundColor: '#10b981',
                    borderRadius: 4,
                    barPercentage: 0.6
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
                    grid: { color: 'rgba(232, 237, 245, 0.9)' }
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
