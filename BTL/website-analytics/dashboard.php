<?php
// ====================================================================
// DASHBOARD TONG QUAN (dashboard.php)
// Hien thi KPI Cards, bieu do Chart.js, hoat dong gan day
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

// 2. Lay du lieu bieu do theo ngay
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

// 3. Top 5 Trang pho bien
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

// 4. Danh sach 10 su kien gan nhat
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

<!-- KPI CARDS -->
<div class="kpi-grid">
    <div class="kpi-card">
        <div class="kpi-header">
            <span class="kpi-title">Lượt Xem Trang</span>
            <span class="kpi-icon blue">👁️</span>
        </div>
        <div class="kpi-value"><?= number_format($totalViews) ?></div>
        <div class="kpi-sub">Sự kiện <code>page_view</code> hợp lệ</div>
    </div>

    <div class="kpi-card">
        <div class="kpi-header">
            <span class="kpi-title">Lần Thao Tác</span>
            <span class="kpi-icon green">⚡</span>
        </div>
        <div class="kpi-value"><?= number_format($totalActions) ?></div>
        <div class="kpi-sub">Nút bấm & tải tài liệu</div>
    </div>

    <div class="kpi-card">
        <div class="kpi-header">
            <span class="kpi-title">Phiên Hoạt Động</span>
            <span class="kpi-icon indigo">👥</span>
        </div>
        <div class="kpi-value"><?= number_format($activeSessions) ?></div>
        <div class="kpi-sub">Phiên truy cập ẩn danh (Unique)</div>
    </div>

    <div class="kpi-card">
        <div class="kpi-header">
            <span class="kpi-title">Hoàn Tất Tải File</span>
            <span class="kpi-icon amber">📥</span>
        </div>
        <div class="kpi-value"><?= number_format($downloadConversions) ?></div>
        <div class="kpi-sub">Chuỗi <code>download_complete</code></div>
    </div>

    <div class="kpi-card">
        <div class="kpi-header">
            <span class="kpi-title">Lỗi Tiếp Nhận</span>
            <span class="kpi-icon red">⚠️</span>
        </div>
        <div class="kpi-value" style="color: <?= $ingestErrors > 0 ? 'var(--danger)' : 'var(--text-primary)' ?>;">
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
            <h3>📈 Xu Hướng Lượt Xem & Thao Tác Theo Ngày</h3>
            <span class="badge badge-blue"><?= count($chartLabels) ?> ngày</span>
        </div>
        <div style="height: 290px; position: relative;">
            <canvas id="trafficTrendChart"></canvas>
        </div>
    </div>

    <!-- Biểu đồ cột Top trang phổ biến -->
    <div class="card-section">
        <div class="card-title-bar">
            <h3>🏆 Top Trang Phổ Biến</h3>
            <span class="badge badge-green">Lượt xem</span>
        </div>
        <div style="height: 290px; position: relative;">
            <canvas id="topPagesChart"></canvas>
        </div>
    </div>
</div>

<!-- BẢNG HOẠT ĐỘNG GẦN ĐÂY -->
<div class="card-section">
    <div class="card-title-bar">
        <h3>⏱️ 10 Sự Kiện Thu Thập Gần Đây Nhất</h3>
        <a href="<?= $baseUrl ?>/event-logs.php" class="btn btn-outline btn-sm">Xem Toàn Bộ Nhật Ký &rarr;</a>
    </div>

    <?php if (empty($recentEvents)): ?>
        <div class="empty-state">
            <div class="icon">📭</div>
            <h4>Chưa có sự kiện nào được ghi nhận</h4>
            <p>Hãy mở Website Demo và click thử một số nút hoặc chạy Kiểm thử tự động để sinh dữ liệu.</p>
            <div style="margin-top: 1rem;">
                <a href="<?= $baseUrl ?>/test-center.php" class="btn btn-primary btn-sm">Mở Trung Tâm Kiểm Thử</a>
            </div>
        </div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>ID Hàng</th>
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
                            <td>#<?= (int)$row['event_row_id'] ?></td>
                            <td style="white-space:nowrap;"><?= date('H:i:s d/m/Y', strtotime($row['occurred_at'])) ?></td>
                            <td>
                                <?php
                                $badgeClass = 'badge-gray';
                                if ($row['event_name'] === 'page_view') $badgeClass = 'badge-blue';
                                elseif ($row['event_name'] === 'button_click') $badgeClass = 'badge-green';
                                elseif ($row['event_name'] === 'download_start') $badgeClass = 'badge-yellow';
                                elseif ($row['event_name'] === 'download_complete') $badgeClass = 'badge-purple';
                                ?>
                                <span class="badge <?= $badgeClass ?>">
                                    <?= e($row['event_name']) ?>
                                </span>
                            </td>
                            <td><code><?= e($row['page_path']) ?></code></td>
                            <td title="<?= e($row['session_id']) ?>">
                                <span style="font-family:monospace;"><?= substr(e($row['session_id']), 0, 16) ?>...</span>
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
                    tension: 0.3,
                    borderWidth: 2.5
                },
                {
                    label: 'Lần thao tác (actions)',
                    data: <?= json_encode($chartActions) ?>,
                    borderColor: '#10b981',
                    backgroundColor: 'rgba(16, 185, 129, 0.08)',
                    fill: true,
                    tension: 0.3,
                    borderWidth: 2.5
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { position: 'top', labels: { boxWidth: 12, font: { size: 12 } } }
            },
            scales: {
                y: { beginAtZero: true, ticks: { precision: 0 } },
                x: { grid: { display: false } }
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
                borderRadius: 6
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            indexAxis: 'y',
            plugins: {
                legend: { display: false }
            },
            scales: {
                x: { beginAtZero: true, ticks: { precision: 0 } }
            }
        }
    });
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
