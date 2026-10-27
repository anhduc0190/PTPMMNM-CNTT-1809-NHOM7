<?php
// ====================================================================
// DASHBOARD TỔNG QUAN (dashboard.php)
// Hiển thị KPI Cards, Biểu đồ Chart.js, Hoạt động gần đây (Enterprise SaaS)
// ====================================================================

$pageTitle = 'Tổng Quan KPI & Xu Hướng';
require_once __DIR__ . '/includes/header.php';

$pdo = get_db_connection();
$fromDateTime = $fromDate . ' 00:00:00';
$toDateTime   = $toDate . ' 23:59:59';

// 1. KPI Counts
$stmtViews = $pdo->prepare('SELECT COUNT(*) FROM `events` WHERE `website_id` = ? AND `event_name` = "page_view" AND `occurred_at` BETWEEN ? AND ?');
$stmtViews->execute([$selectedWebsiteId, $fromDateTime, $toDateTime]);
$totalViews = (int)$stmtViews->fetchColumn();

$stmtActions = $pdo->prepare('SELECT COUNT(*) FROM `events` WHERE `website_id` = ? AND `event_name` != "page_view" AND `occurred_at` BETWEEN ? AND ?');
$stmtActions->execute([$selectedWebsiteId, $fromDateTime, $toDateTime]);
$totalActions = (int)$stmtActions->fetchColumn();

$stmtSessions = $pdo->prepare('SELECT COUNT(DISTINCT `session_id`) FROM `events` WHERE `website_id` = ? AND `occurred_at` BETWEEN ? AND ?');
$stmtSessions->execute([$selectedWebsiteId, $fromDateTime, $toDateTime]);
$activeSessions = (int)$stmtSessions->fetchColumn();

$stmtDl = $pdo->prepare('SELECT COUNT(*) FROM `events` WHERE `website_id` = ? AND `event_name` = "download_complete" AND `occurred_at` BETWEEN ? AND ?');
$stmtDl->execute([$selectedWebsiteId, $fromDateTime, $toDateTime]);
$downloadConversions = (int)$stmtDl->fetchColumn();

$stmtErr = $pdo->prepare('SELECT COUNT(*) FROM `event_ingest_errors` WHERE `website_id` = ? AND `received_at` BETWEEN ? AND ?');
$stmtErr->execute([$selectedWebsiteId, $fromDateTime, $toDateTime]);
$ingestErrors = (int)$stmtErr->fetchColumn();

// 2. Lấy dữ liệu biểu đồ theo ngày
$stmtTraffic = $pdo->prepare('
    SELECT 
        DATE(`occurred_at`) AS `r_date`,
        SUM(CASE WHEN `event_name` = "page_view" THEN 1 ELSE 0 END) AS `views`,
        SUM(CASE WHEN `event_name` != "page_view" THEN 1 ELSE 0 END) AS `actions`
    FROM `events`
    WHERE `website_id` = ? AND `occurred_at` BETWEEN ? AND ?
    GROUP BY DATE(`occurred_at`)
    ORDER BY `r_date` ASC
');
$stmtTraffic->execute([$selectedWebsiteId, $fromDateTime, $toDateTime]);
$trafficRows = $stmtTraffic->fetchAll();

$trafficMap = [];
foreach ($trafficRows as $tr) {
    $trafficMap[$tr['r_date']] = $tr;
}

$chartLabels  = [];
$chartViews   = [];
$chartActions = [];

$cur = strtotime($fromDate);
$end = strtotime($toDate);
while ($cur <= $end) {
    $d = date('Y-m-d', $cur);
    $chartLabels[]  = date('d/m', $cur);
    $chartViews[]   = isset($trafficMap[$d]) ? (int)$trafficMap[$d]['views'] : 0;
    $chartActions[] = isset($trafficMap[$d]) ? (int)$trafficMap[$d]['actions'] : 0;
    $cur = strtotime('+1 day', $cur);
}

// 3. Top 5 Trang phổ biến
$stmtTopPages = $pdo->prepare('
    SELECT `page_path`, COUNT(*) AS `views_count`, COUNT(DISTINCT `session_id`) AS `sessions_count`
    FROM `events`
    WHERE `website_id` = ? AND `event_name` = "page_view" AND `occurred_at` BETWEEN ? AND ?
    GROUP BY `page_path`
    ORDER BY `views_count` DESC
    LIMIT 5
');
$stmtTopPages->execute([$selectedWebsiteId, $fromDateTime, $toDateTime]);
$topPages = $stmtTopPages->fetchAll();

$pageLabels = [];
$pageCounts = [];
foreach ($topPages as $tp) {
    $pageLabels[] = $tp['page_path'];
    $pageCounts[] = (int)$tp['views_count'];
}

// 4. Danh sách 10 sự kiện gần nhất
$stmtRecent = $pdo->prepare('
    SELECT `event_row_id`, `event_id`, `session_id`, `event_name`, `page_path`, `occurred_at`, `payload_hash`
    FROM `events`
    WHERE `website_id` = ?
    ORDER BY `occurred_at` DESC, `event_row_id` DESC
    LIMIT 10
');
$stmtRecent->execute([$selectedWebsiteId]);
$recentEvents = $stmtRecent->fetchAll();
?>

<!-- 5 KPI CARDS HÀNG ĐẦU -->
<div class="kpi-grid">
    <div class="kpi-card">
        <div class="kpi-header">
            <span class="kpi-title">Lượt Xem Trang</span>
            <span class="kpi-icon-wrap blue">
                <?= render_icon('eye', '', 18) ?>
            </span>
        </div>
        <div class="kpi-value"><?= number_format($totalViews) ?></div>
        <div class="kpi-sub">Sự kiện <code>page_view</code> hợp lệ</div>
    </div>

    <div class="kpi-card">
        <div class="kpi-header">
            <span class="kpi-title">Lần Thao Tác</span>
            <span class="kpi-icon-wrap green">
                <?= render_icon('zap', '', 18) ?>
            </span>
        </div>
        <div class="kpi-value"><?= number_format($totalActions) ?></div>
        <div class="kpi-sub">Nút bấm & tương tác sự kiện</div>
    </div>

    <div class="kpi-card">
        <div class="kpi-header">
            <span class="kpi-title">Phiên Hoạt Động</span>
            <span class="kpi-icon-wrap purple">
                <?= render_icon('users-round', '', 18) ?>
            </span>
        </div>
        <div class="kpi-value"><?= number_format($activeSessions) ?></div>
        <div class="kpi-sub">Phiên truy cập ẩn danh (Unique)</div>
    </div>

    <div class="kpi-card">
        <div class="kpi-header">
            <span class="kpi-title">Hoàn Tất Tải File</span>
            <span class="kpi-icon-wrap yellow">
                <?= render_icon('download', '', 18) ?>
            </span>
        </div>
        <div class="kpi-value"><?= number_format($downloadConversions) ?></div>
        <div class="kpi-sub">Chuỗi <code>download_complete</code></div>
    </div>

    <div class="kpi-card">
        <div class="kpi-header">
            <span class="kpi-title">Lỗi Tiếp Nhận</span>
            <span class="kpi-icon-wrap red">
                <?= render_icon('alert-triangle', '', 18) ?>
            </span>
        </div>
        <div class="kpi-value" style="color: <?= $ingestErrors > 0 ? 'var(--accent-red)' : 'var(--text-primary)' ?>;">
            <?= number_format($ingestErrors) ?>
        </div>
        <div class="kpi-sub">Xung đột & Sự kiện từ chối</div>
    </div>
</div>

<!-- BIỂU ĐỒ CHART.JS -->
<div class="grid-2-col">
    <!-- Biểu đồ đường xu hướng thời gian -->
    <div class="card-section">
        <div class="card-title-bar">
            <h3>
                <?= render_icon('chart-line', '', 18, 'var(--primary)') ?>
                Xu Hướng Lượt Xem & Thao Tác Theo Ngày
            </h3>
            <span class="badge badge-blue">
                <?= render_icon('calendar', '', 12) ?>
                <?= count($chartLabels) ?> ngày
            </span>
        </div>
        <div style="height: 290px; position: relative;">
            <canvas id="trafficTrendChart"></canvas>
        </div>
    </div>

    <!-- Biểu đồ cột Top trang phổ biến -->
    <div class="card-section">
        <div class="card-title-bar">
            <h3>
                <?= render_icon('chart-bar', '', 18, 'var(--accent-green)') ?>
                Top Trang Phổ Biến
            </h3>
            <span class="badge badge-green">
                <?= render_icon('eye', '', 12) ?>
                Lượt xem
            </span>
        </div>
        <div style="height: 290px; position: relative;">
            <canvas id="topPagesChart"></canvas>
        </div>
    </div>
</div>

<!-- BẢNG HOẠT ĐỘNG GẦN ĐÂY -->
<div class="card-section">
    <div class="card-title-bar">
        <h3>
            <?= render_icon('clock', '', 18, 'var(--text-secondary)') ?>
            10 Sự Kiện Thu Thập Gần Đây Nhất
        </h3>
        <a href="<?= $baseUrl ?>/event-logs.php" class="btn btn-outline btn-sm">
            Xem Toàn Bộ Nhật Ký
            <?= render_icon('arrow-right', '', 13) ?>
        </a>
    </div>

    <?php if (empty($recentEvents)): ?>
        <div class="empty-state">
            <div class="empty-icon">
                <?= render_icon('inbox', '', 24) ?>
            </div>
            <h4>Chưa có sự kiện nào được ghi nhận</h4>
            <p>Hãy mở Website Demo và click thử một số nút hoặc chạy Kiểm thử tự động để sinh dữ liệu.</p>
            <div style="margin-top: 1rem;">
                <a href="<?= $baseUrl ?>/test-center.php" class="btn btn-primary btn-sm">
                    <?= render_icon('flask-conical', '', 14) ?>
                    Mở Trung Tâm Kiểm Thử
                </a>
            </div>
        </div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr>
                        <th style="width: 80px;">ID Hàng</th>
                        <th>Thời Điểm</th>
                        <th>Loại Sự Kiện</th>
                        <th>Đường Dẫn Trang</th>
                        <th>Session ID</th>
                        <th>Mã Băm Payload (Backend Hash)</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recentEvents as $row): ?>
                        <tr>
                            <td><strong>#<?= (int)$row['event_row_id'] ?></strong></td>
                            <td style="white-space:nowrap; color:var(--text-secondary);">
                                <?= date('H:i:s d/m/Y', strtotime($row['occurred_at'])) ?>
                            </td>
                            <td>
                                <?php
                                $badgeClass = 'badge-gray';
                                $iconName = 'activity';
                                if ($row['event_name'] === 'page_view') {
                                    $badgeClass = 'badge-blue';
                                    $iconName = 'eye';
                                } elseif ($row['event_name'] === 'button_click') {
                                    $badgeClass = 'badge-green';
                                    $iconName = 'zap';
                                } elseif ($row['event_name'] === 'download_start') {
                                    $badgeClass = 'badge-yellow';
                                    $iconName = 'download';
                                } elseif ($row['event_name'] === 'download_complete') {
                                    $badgeClass = 'badge-purple';
                                    $iconName = 'check-circle';
                                }
                                ?>
                                <span class="badge <?= $badgeClass ?>">
                                    <?= render_icon($iconName, '', 11) ?>
                                    <?= e($row['event_name']) ?>
                                </span>
                            </td>
                            <td><code><?= e($row['page_path']) ?></code></td>
                            <td title="<?= e($row['session_id']) ?>">
                                <span style="font-family:monospace; font-size:0.8rem;"><?= substr(e($row['session_id']), 0, 16) ?>...</span>
                            </td>
                            <td title="<?= e($row['payload_hash']) ?>">
                                <span style="font-family:monospace; font-size:0.75rem; color:#64748b;">
                                    <?= substr(e($row['payload_hash']), 0, 12) ?>...<?= substr(e($row['payload_hash']), -6) ?>
                                </span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Cấu hình chung cho Chart.js
    Chart.defaults.font.family = '-apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif';
    Chart.defaults.font.size = 12;
    Chart.defaults.color = '#64748b';

    // 1. Biểu đồ đường Traffic Trend
    const ctxTraffic = document.getElementById('trafficTrendChart').getContext('2d');
    new Chart(ctxTraffic, {
        type: 'line',
        data: {
            labels: <?= json_encode($chartLabels) ?>,
            datasets: [
                {
                    label: 'Lượt xem trang (page_view)',
                    data: <?= json_encode($chartViews) ?>,
                    borderColor: '#2563eb',
                    backgroundColor: 'rgba(37, 99, 235, 0.08)',
                    fill: true,
                    tension: 0.35,
                    borderWidth: 2.2,
                    pointRadius: 3,
                    pointHoverRadius: 5,
                    pointBackgroundColor: '#2563eb'
                },
                {
                    label: 'Lần thao tác (actions)',
                    data: <?= json_encode($chartActions) ?>,
                    borderColor: '#10b981',
                    backgroundColor: 'rgba(16, 185, 129, 0.08)',
                    fill: true,
                    tension: 0.35,
                    borderWidth: 2.2,
                    pointRadius: 3,
                    pointHoverRadius: 5,
                    pointBackgroundColor: '#10b981'
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            interaction: {
                mode: 'index',
                intersect: false
            },
            plugins: {
                legend: {
                    position: 'top',
                    align: 'end',
                    labels: {
                        boxWidth: 10,
                        boxHeight: 10,
                        usePointStyle: true,
                        pointStyle: 'circle',
                        padding: 15
                    }
                },
                tooltip: {
                    backgroundColor: '#0f172a',
                    titleColor: '#ffffff',
                    bodyColor: '#f1f5f9',
                    borderColor: '#334155',
                    borderWidth: 1,
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

    // 2. Biểu đồ cột Top Pages
    const ctxPages = document.getElementById('topPagesChart').getContext('2d');
    new Chart(ctxPages, {
        type: 'bar',
        data: {
            labels: <?= json_encode(!empty($pageLabels) ? $pageLabels : ['Chưa có dữ liệu']) ?>,
            datasets: [{
                label: 'Lượt xem',
                data: <?= json_encode(!empty($pageCounts) ? $pageCounts : [0]) ?>,
                backgroundColor: '#3b82f6',
                borderRadius: 4,
                barPercentage: 0.6
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            indexAxis: 'y',
            plugins: {
                legend: { display: false },
                tooltip: {
                    backgroundColor: '#0f172a',
                    titleColor: '#ffffff',
                    bodyColor: '#f1f5f9',
                    cornerRadius: 6,
                    padding: 10
                }
            },
            scales: {
                x: {
                    beginAtZero: true,
                    ticks: { precision: 0 },
                    grid: { color: 'rgba(226, 232, 240, 0.8)' }
                },
                y: {
                    grid: { display: false }
                }
            }
        }
    });
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
