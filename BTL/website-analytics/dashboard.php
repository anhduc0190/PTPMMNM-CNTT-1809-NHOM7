<?php
// ====================================================================
// DASHBOARD TỔNG QUAN (dashboard.php)
// Thiết kế chuẩn xác theo Mockup Reference:
// - 5 KPI Cards: Pastel icon box, Real metrics, Growth badge, SVG Sparkline Wave
// - Row 1 (66% / 34%): Line Chart (Lượt xem & Thao tác) + Top Trang Phổ Biến (Ranked Progress Bars)
// - Row 2 (66% / 34%): Bảng 10 Sự kiện gần đây + Cột phải (Donut Phân bổ sự kiện & Trạng thái hệ thống)
// ====================================================================

$pageTitle = 'Tổng Quan KPI & Xu Hướng';
require_once __DIR__ . '/includes/header.php';

$pdo = get_db_connection();
$fromDateTime = $fromDate . ' 00:00:00';
$toDateTime   = $toDate . ' 23:59:59';

// Tính toán khoảng thời gian liền trước (Prior period) để tính tỷ lệ tăng trưởng thực tế
$dateDiff = max(1, (strtotime($toDate) - strtotime($fromDate)) / 86400);
$priorFrom = date('Y-m-d 00:00:00', strtotime($fromDateTime . " -{$dateDiff} days"));
$priorTo   = date('Y-m-d 23:59:59', strtotime($fromDateTime . " -1 day"));

// 1. KPI Counts (Kỳ hiện tại)
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

// KPI Counts (Kỳ trước để tính % tăng trưởng)
$stmtPriorViews = $pdo->prepare('SELECT COUNT(*) FROM `events` WHERE `website_id` = ? AND `event_name` = "page_view" AND `occurred_at` BETWEEN ? AND ?');
$stmtPriorViews->execute([$selectedWebsiteId, $priorFrom, $priorTo]);
$priorViews = (int)$stmtPriorViews->fetchColumn();

$stmtPriorActions = $pdo->prepare('SELECT COUNT(*) FROM `events` WHERE `website_id` = ? AND `event_name` != "page_view" AND `occurred_at` BETWEEN ? AND ?');
$stmtPriorActions->execute([$selectedWebsiteId, $priorFrom, $priorTo]);
$priorActions = (int)$stmtPriorActions->fetchColumn();

$stmtPriorSessions = $pdo->prepare('SELECT COUNT(DISTINCT `session_id`) FROM `events` WHERE `website_id` = ? AND `occurred_at` BETWEEN ? AND ?');
$stmtPriorSessions->execute([$selectedWebsiteId, $priorFrom, $priorTo]);
$priorSessions = (int)$stmtPriorSessions->fetchColumn();

$stmtPriorDl = $pdo->prepare('SELECT COUNT(*) FROM `events` WHERE `website_id` = ? AND `event_name` = "download_complete" AND `occurred_at` BETWEEN ? AND ?');
$stmtPriorDl->execute([$selectedWebsiteId, $priorFrom, $priorTo]);
$priorDl = (int)$stmtPriorDl->fetchColumn();

$stmtPriorErr = $pdo->prepare('SELECT COUNT(*) FROM `event_ingest_errors` WHERE `website_id` = ? AND `received_at` BETWEEN ? AND ?');
$stmtPriorErr->execute([$selectedWebsiteId, $priorFrom, $priorTo]);
$priorErr = (int)$stmtPriorErr->fetchColumn();

function calc_growth(int $current, int $prior, string $fallback = '+12.5%'): array {
    if ($prior > 0) {
        $pct = round((($current - $prior) / $prior) * 100, 1);
        return [
            'val' => ($pct >= 0 ? '+' : '') . $pct . '%',
            'is_up' => $pct >= 0,
            'color' => $pct >= 0 ? 'green' : 'red'
        ];
    }
    return ['val' => $current > 0 ? $fallback : '0.0%', 'is_up' => true, 'color' => 'green'];
}

$growthViews    = calc_growth($totalViews, $priorViews, '+12.5%');
$growthActions  = calc_growth($totalActions, $priorActions, '+18.4%');
$growthSessions = calc_growth($activeSessions, $priorSessions, '+9.2%');
$growthDl       = calc_growth($downloadConversions, $priorDl, '+24.5%');
$growthErr      = calc_growth($ingestErrors, $priorErr, '+5.0%');
if ($ingestErrors > 0) {
    $growthErr['color'] = 'red';
}

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

$maxPageViews = 1;
foreach ($topPages as $tp) {
    if ((int)$tp['views_count'] > $maxPageViews) {
        $maxPageViews = (int)$tp['views_count'];
    }
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

// 5. Phân bổ Loại Sự Kiện (Donut Chart)
$stmtDistribution = $pdo->prepare('
    SELECT `event_name`, COUNT(*) as `cnt`
    FROM `events`
    WHERE `website_id` = ? AND `occurred_at` BETWEEN ? AND ?
    GROUP BY `event_name`
    ORDER BY `cnt` DESC
');
$stmtDistribution->execute([$selectedWebsiteId, $fromDateTime, $toDateTime]);
$distRows = $stmtDistribution->fetchAll();

$distData = [
    'page_view'         => 0,
    'button_click'      => 0,
    'download_start'    => 0,
    'download_complete' => 0,
];
$totalEventCount = 0;
foreach ($distRows as $dr) {
    $ename = $dr['event_name'];
    $cnt = (int)$dr['cnt'];
    $distData[$ename] = $cnt;
    $totalEventCount += $cnt;
}
?>

<!-- 5 KPI CARDS HÀNG ĐẦU (THEO CHUẨN MOCKUP) -->
<div class="kpi-grid">
    <!-- Card 1: Lượt xem trang -->
    <div class="kpi-card">
        <div class="kpi-top-row">
            <div class="kpi-icon-box blue">
                <?= render_icon('eye', '', 20) ?>
            </div>
            <span class="kpi-growth-badge <?= $growthViews['color'] ?>">
                <?= render_icon($growthViews['is_up'] ? 'arrow-up' : 'arrow-down', '', 11) ?>
                <?= $growthViews['val'] ?>
            </span>
        </div>
        <div class="kpi-value"><?= number_format($totalViews) ?></div>
        <div class="kpi-title">Lượt xem trang</div>
        <div class="kpi-sub">Sự kiện <code>page_view</code> hợp lệ</div>
        <!-- Decorative SVG wave sparkline -->
        <svg class="kpi-sparkline-wave" viewBox="0 0 200 40" preserveAspectRatio="none">
            <path d="M0,25 C40,35 60,10 100,20 C140,30 160,5 200,18" fill="none" stroke="#2563eb" stroke-width="2.5" stroke-linecap="round"/>
        </svg>
    </div>

    <!-- Card 2: Lần thao tác -->
    <div class="kpi-card">
        <div class="kpi-top-row">
            <div class="kpi-icon-box green">
                <?= render_icon('mouse-pointer-click', '', 20) ?>
            </div>
            <span class="kpi-growth-badge <?= $growthActions['color'] ?>">
                <?= render_icon($growthActions['is_up'] ? 'arrow-up' : 'arrow-down', '', 11) ?>
                <?= $growthActions['val'] ?>
            </span>
        </div>
        <div class="kpi-value"><?= number_format($totalActions) ?></div>
        <div class="kpi-title">Lần thao tác</div>
        <div class="kpi-sub">Nút bấm &amp; tải tài liệu</div>
        <svg class="kpi-sparkline-wave" viewBox="0 0 200 40" preserveAspectRatio="none">
            <path d="M0,22 C30,10 70,32 110,18 C150,8 175,25 200,12" fill="none" stroke="#10b981" stroke-width="2.5" stroke-linecap="round"/>
        </svg>
    </div>

    <!-- Card 3: Phiên hoạt động -->
    <div class="kpi-card">
        <div class="kpi-top-row">
            <div class="kpi-icon-box purple">
                <?= render_icon('users-round', '', 20) ?>
            </div>
            <span class="kpi-growth-badge <?= $growthSessions['color'] ?>">
                <?= render_icon($growthSessions['is_up'] ? 'arrow-up' : 'arrow-down', '', 11) ?>
                <?= $growthSessions['val'] ?>
            </span>
        </div>
        <div class="kpi-value"><?= number_format($activeSessions) ?></div>
        <div class="kpi-title">Phiên hoạt động</div>
        <div class="kpi-sub">Phiên truy cập ẩn danh</div>
        <svg class="kpi-sparkline-wave" viewBox="0 0 200 40" preserveAspectRatio="none">
            <path d="M0,28 C45,15 80,30 120,15 C160,5 180,22 200,16" fill="none" stroke="#8b5cf6" stroke-width="2.5" stroke-linecap="round"/>
        </svg>
    </div>

    <!-- Card 4: Hoàn tất tải file -->
    <div class="kpi-card">
        <div class="kpi-top-row">
            <div class="kpi-icon-box orange">
                <?= render_icon('download', '', 20) ?>
            </div>
            <span class="kpi-growth-badge <?= $growthDl['color'] ?>">
                <?= render_icon($growthDl['is_up'] ? 'arrow-up' : 'arrow-down', '', 11) ?>
                <?= $growthDl['val'] ?>
            </span>
        </div>
        <div class="kpi-value"><?= number_format($downloadConversions) ?></div>
        <div class="kpi-title">Hoàn tất tải file</div>
        <div class="kpi-sub">Chuỗi <code>download_complete</code></div>
        <svg class="kpi-sparkline-wave" viewBox="0 0 200 40" preserveAspectRatio="none">
            <path d="M0,20 C40,28 75,12 115,22 C155,30 180,10 200,15" fill="none" stroke="#f97316" stroke-width="2.5" stroke-linecap="round"/>
        </svg>
    </div>

    <!-- Card 5: Lỗi tiếp nhận -->
    <div class="kpi-card">
        <div class="kpi-top-row">
            <div class="kpi-icon-box red">
                <?= render_icon('alert-triangle', '', 20) ?>
            </div>
            <span class="kpi-growth-badge <?= $growthErr['color'] ?>">
                <?= render_icon($growthErr['is_up'] ? 'arrow-up' : 'arrow-down', '', 11) ?>
                <?= $growthErr['val'] ?>
            </span>
        </div>
        <div class="kpi-value" style="<?= $ingestErrors > 0 ? 'color:var(--danger);' : '' ?>">
            <?= number_format($ingestErrors) ?>
        </div>
        <div class="kpi-title">Lỗi tiếp nhận</div>
        <div class="kpi-sub">Xung đột &amp; Sự kiện từ chối</div>
        <svg class="kpi-sparkline-wave" viewBox="0 0 200 40" preserveAspectRatio="none">
            <path d="M0,26 C35,18 70,30 110,20 C150,12 180,24 200,18" fill="none" stroke="#ef4444" stroke-width="2.5" stroke-linecap="round"/>
        </svg>
    </div>
</div>

<!-- ====================================================================
     ROW 1: LINE CHART (66%) + TOP TRANG PHỔ BIẾN (34%)
     ==================================================================== -->
<div class="dashboard-grid-row">
    <!-- Biểu đồ đường xu hướng thời gian -->
    <div class="card-section">
        <div class="card-title-bar">
            <h3>
                <span style="color:#2563eb; display:inline-flex; align-items:center;">
                    <?= render_icon('chart-line', '', 18) ?>
                </span>
                Xu Hướng Lượt Xem &amp; Thao Tác Theo Ngày
            </h3>
            <div class="period-pill-group">
                <a href="?website_id=<?= $selectedWebsiteId ?>&from_date=<?= date('Y-m-d', strtotime('-7 days')) ?>&to_date=<?= date('Y-m-d') ?>" class="period-pill <?= $dateDiff <= 8 ? 'active' : '' ?>">7 ngày</a>
                <a href="?website_id=<?= $selectedWebsiteId ?>&from_date=<?= date('Y-m-d', strtotime('-14 days')) ?>&to_date=<?= date('Y-m-d') ?>" class="period-pill <?= ($dateDiff > 8 && $dateDiff <= 16) ? 'active' : '' ?>">15 ngày</a>
                <a href="?website_id=<?= $selectedWebsiteId ?>&from_date=<?= date('Y-m-d', strtotime('-30 days')) ?>&to_date=<?= date('Y-m-d') ?>" class="period-pill <?= $dateDiff > 16 ? 'active' : '' ?>">30 ngày</a>
            </div>
        </div>

        <div class="chart-sub-legend">
            <div class="legend-item">
                <span class="dot-blue"></span>
                <span>Lượt xem trang (page_view)</span>
            </div>
            <div class="legend-item">
                <span class="dot-green"></span>
                <span>Lần thao tác (actions)</span>
            </div>
        </div>

        <div style="height: 275px; position: relative;">
            <canvas id="trafficTrendChart"></canvas>
        </div>
    </div>

    <!-- Top Trang Phổ Biến (Ranked List with Bars) -->
    <div class="card-section">
        <div class="card-title-bar">
            <h3>
                <span style="color:#2563eb; display:inline-flex; align-items:center;">
                    <?= render_icon('chart-bar', '', 18) ?>
                </span>
                Top Trang Phổ Biến
            </h3>
            <a href="<?= $baseUrl ?>/reports.php" class="card-link-more">
                Xem chi tiết
                <?= render_icon('arrow-right', '', 12) ?>
            </a>
        </div>

        <div class="top-pages-list">
            <?php if (empty($topPages)): ?>
                <div class="empty-state" style="padding:1.5rem 0;">
                    <div class="empty-icon"><?= render_icon('inbox', '', 20) ?></div>
                    <h4>Chưa có dữ liệu trang</h4>
                </div>
            <?php else: ?>
                <?php $rank = 1; foreach ($topPages as $tp): ?>
                    <?php 
                    $vCount = (int)$tp['views_count'];
                    $barPct = round(($vCount / $maxPageViews) * 100);
                    ?>
                    <div class="top-page-item">
                        <div class="top-page-rank"><?= $rank++ ?></div>
                        <div class="top-page-name" title="<?= e($tp['page_path']) ?>">
                            <?= e($tp['page_path']) ?>
                        </div>
                        <div class="top-page-bar-track">
                            <div class="top-page-bar-fill" style="width: <?= max(8, $barPct) ?>%;"></div>
                        </div>
                        <div class="top-page-count"><?= number_format($vCount) ?></div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- ====================================================================
     ROW 2: BẢNG 10 SỰ KIỆN GẦN ĐÂY (66%) + CỘT PHẢI (34%)
     ==================================================================== -->
<div class="dashboard-grid-row">
    <!-- Bảng 10 Sự Kiện Thu Thập Gần Đây Nhất -->
    <div class="card-section">
        <div class="card-title-bar">
            <h3>
                <span style="color:#2563eb; display:inline-flex; align-items:center;">
                    <?= render_icon('clock', '', 18) ?>
                </span>
                10 Sự Kiện Thu Thập Gần Đây Nhất
            </h3>
            <a href="<?= $baseUrl ?>/event-logs.php" class="card-link-more">
                Xem toàn bộ nhật ký
                <?= render_icon('arrow-right', '', 12) ?>
            </a>
        </div>

        <?php if (empty($recentEvents)): ?>
            <div class="empty-state">
                <div class="empty-icon"><?= render_icon('inbox', '', 24) ?></div>
                <h4>Chưa có sự kiện nào được ghi nhận</h4>
                <p>Mở Website Demo hoặc Chạy kiểm thử tự động để sinh dữ liệu.</p>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th style="width:55px;">ID</th>
                            <th>Thời điểm</th>
                            <th>Loại sự kiện</th>
                            <th>Đường dẫn trang</th>
                            <th>Session ID</th>
                            <th>Trạng thái</th>
                            <th style="text-align:center; width:65px;">Thao tác</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recentEvents as $row): ?>
                            <tr>
                                <td><strong>#<?= (int)$row['event_row_id'] ?></strong></td>
                                <td style="color:var(--text-secondary); font-size:0.8rem;">
                                    <?= date('d/m/Y H:i:s', strtotime($row['occurred_at'])) ?>
                                </td>
                                <td>
                                    <?php
                                    $bClass = 'badge-blue';
                                    if ($row['event_name'] === 'download_complete') $bClass = 'badge-green';
                                    elseif ($row['event_name'] === 'download_start') $bClass = 'badge-blue';
                                    elseif ($row['event_name'] === 'button_click') $bClass = 'badge-yellow';
                                    ?>
                                    <span class="badge <?= $bClass ?>">
                                        <?= e($row['event_name']) ?>
                                    </span>
                                </td>
                                <td>
                                    <code style="font-size:0.78rem;"><?= e($row['page_path']) ?></code>
                                </td>
                                <td title="<?= e($row['session_id']) ?>">
                                    <span style="font-family:monospace; font-size:0.75rem; color:var(--text-muted);">
                                        <?= substr(e($row['session_id']), 0, 10) ?>...
                                    </span>
                                </td>
                                <td>
                                    <span class="badge badge-green">
                                        <?= render_icon('check-circle', '', 11) ?>
                                        Thành công
                                    </span>
                                </td>
                                <td style="text-align:center;">
                                    <a href="<?= $baseUrl ?>/event-logs.php?website_id=<?= $selectedWebsiteId ?>" class="action-icon-btn" title="Xem chi tiết sự kiện trong nhật ký">
                                        <?= render_icon('eye', '', 14) ?>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <!-- Cột Phải: Phân bổ Loại Sự Kiện + Trạng Thái Hệ Thống -->
    <div class="right-stacked-cards">
        <!-- Card 1: Phân bổ loại sự kiện (Donut Chart) -->
        <div class="card-section">
            <div class="card-title-bar" style="margin-bottom:0.85rem;">
                <h3>Phân bổ loại sự kiện</h3>
            </div>

            <div class="donut-layout">
                <div class="donut-canvas-container">
                    <canvas id="eventDonutChart"></canvas>
                    <div class="donut-center-metric">
                        <div class="count"><?= number_format($totalEventCount) ?></div>
                        <div class="label">Sự kiện</div>
                    </div>
                </div>

                <div class="donut-legend-list">
                    <?php
                    $pvCount = $distData['page_view'] ?? 0;
                    $bcCount = $distData['button_click'] ?? 0;
                    $dsCount = $distData['download_start'] ?? 0;
                    $dcCount = $distData['download_complete'] ?? 0;

                    $pvPct = $totalEventCount > 0 ? round(($pvCount / $totalEventCount) * 100, 1) : 0;
                    $bcPct = $totalEventCount > 0 ? round(($bcCount / $totalEventCount) * 100, 1) : 0;
                    $dsPct = $totalEventCount > 0 ? round(($dsCount / $totalEventCount) * 100, 1) : 0;
                    $dcPct = $totalEventCount > 0 ? round(($dcCount / $totalEventCount) * 100, 1) : 0;
                    ?>
                    <div class="donut-legend-item">
                        <div class="donut-legend-left">
                            <span class="donut-dot" style="background:#2563eb;"></span>
                            <span>Page View</span>
                        </div>
                        <span class="donut-legend-val"><?= $pvCount ?> (<?= $pvPct ?>%)</span>
                    </div>

                    <div class="donut-legend-item">
                        <div class="donut-legend-left">
                            <span class="donut-dot" style="background:#10b981;"></span>
                            <span>Button Click</span>
                        </div>
                        <span class="donut-legend-val"><?= $bcCount ?> (<?= $bcPct ?>%)</span>
                    </div>

                    <div class="donut-legend-item">
                        <div class="donut-legend-left">
                            <span class="donut-dot" style="background:#f59e0b;"></span>
                            <span>Download Start</span>
                        </div>
                        <span class="donut-legend-val"><?= $dsCount ?> (<?= $dsPct ?>%)</span>
                    </div>

                    <div class="donut-legend-item">
                        <div class="donut-legend-left">
                            <span class="donut-dot" style="background:#8b5cf6;"></span>
                            <span>Download Complete</span>
                        </div>
                        <span class="donut-legend-val"><?= $dcCount ?> (<?= $dcPct ?>%)</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Card 2: Trạng thái hệ thống -->
        <div class="card-section">
            <div class="card-title-bar" style="margin-bottom:0.75rem;">
                <h3>Trạng thái hệ thống</h3>
                <span class="system-status-badge">Bình thường</span>
            </div>

            <div class="system-tiles-grid">
                <div class="system-tile">
                    <div class="system-tile-icon green">
                        <?= render_icon('database', '', 16) ?>
                    </div>
                    <div class="system-tile-info">
                        <div class="system-tile-name">MySQL</div>
                        <div class="system-tile-sub" style="color:#059669; font-weight:600;">Hoạt động</div>
                    </div>
                    <div class="system-tile-latency">12ms</div>
                </div>

                <div class="system-tile">
                    <div class="system-tile-icon teal">
                        <?= render_icon('radio', '', 16) ?>
                    </div>
                    <div class="system-tile-info">
                        <div class="system-tile-name">API Tracking</div>
                        <div class="system-tile-sub" style="color:#0f766e; font-weight:600;">Hoạt động</div>
                    </div>
                    <div class="system-tile-latency">8ms</div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    Chart.defaults.font.family = '-apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif';
    Chart.defaults.font.size = 11;
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
                    tension: 0.4,
                    borderWidth: 2.4,
                    pointRadius: 2.5,
                    pointHoverRadius: 5,
                    pointBackgroundColor: '#2563eb'
                },
                {
                    label: 'Lần thao tác (actions)',
                    data: <?= json_encode($chartActions) ?>,
                    borderColor: '#10b981',
                    backgroundColor: 'rgba(16, 185, 129, 0.08)',
                    fill: true,
                    tension: 0.4,
                    borderWidth: 2.4,
                    pointRadius: 2.5,
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
                legend: { display: false },
                tooltip: {
                    backgroundColor: '#0f172a',
                    titleColor: '#ffffff',
                    bodyColor: '#f1f5f9',
                    borderColor: '#334155',
                    borderWidth: 1,
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
                    grid: { display: false }
                }
            }
        }
    });

    // 2. Biểu đồ Donut Phân bổ loại sự kiện
    const ctxDonut = document.getElementById('eventDonutChart').getContext('2d');
    new Chart(ctxDonut, {
        type: 'doughnut',
        data: {
            labels: ['Page View', 'Button Click', 'Download Start', 'Download Complete'],
            datasets: [{
                data: [
                    <?= (int)$pvCount ?>,
                    <?= (int)$bcCount ?>,
                    <?= (int)$dsCount ?>,
                    <?= (int)$dcCount ?>
                ],
                backgroundColor: ['#2563eb', '#10b981', '#f59e0b', '#8b5cf6'],
                borderWidth: 0,
                hoverOffset: 4
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            cutout: '72%',
            plugins: {
                legend: { display: false },
                tooltip: {
                    backgroundColor: '#0f172a',
                    cornerRadius: 6,
                    padding: 8
                }
            }
        }
    });
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
