<?php
declare(strict_types=1);
/**
 * funnel.php — Chức năng 5: Phân tích chuỗi thao tác (funnel)
 */
require_once __DIR__ . '/../src/Database.php';
require_once __DIR__ . '/../src/FunnelAnalyzer.php';
require_once __DIR__ . '/templates/layout.php';

$error   = '';
$sites   = [];
$result  = null;
$steps   = [];

$siteId   = (int) ($_GET['site'] ?? 0);
$funnelId = ($_GET['funnel'] ?? 'default');
$dateFrom = $_GET['date_from'] ?? date('Y-m-d', strtotime('-30 days'));
$dateTo   = $_GET['date_to']   ?? date('Y-m-d');

try {
    $pdo   = db();
    $sites = dbQuery($pdo, 'SELECT id, name FROM websites WHERE active=1')->fetchAll();
    if ($siteId === 0 && !empty($sites)) {
        $siteId = (int) $sites[0]['id'];
    }
} catch (Throwable $e) {
    $error = 'Không kết nối được database.';
}

// Chọn funnel
$funnelDefs = [
    'default'  => ['label' => 'Xem → Bắt đầu → Hoàn tất', 'steps' => defaultFunnel()],
    'download' => ['label' => 'Xem trang → Tải tài liệu',  'steps' => downloadFunnel()],
];
$steps = $funnelDefs[$funnelId]['steps'] ?? defaultFunnel();

if ($siteId && !$error) {
    try {
        $result = analyzeFunnel($pdo, $siteId,
            $steps, ['date_from' => $dateFrom, 'date_to' => $dateTo]);
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

renderHead('Phân tích Funnel', '<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>');
?>
<div class="app-container">
  <?php renderSidebar('actions'); ?>
  <div class="main-wrapper">
    <?php renderTopbar($sites, $siteId, $dateFrom, $dateTo); ?>
    <main class="content-body">
      <div class="page-title-section">
        <h1 class="page-title">Phân tích Chuỗi Thao tác (Funnel)</h1>
        <p class="page-subtitle">Đếm session hoàn tất đúng thứ tự đã định — thiếu bước hoặc sai thứ tự không tính</p>
      </div>

  <?php if ($error): ?>
    <div class="notice notice-error"><?= htmlspecialchars($error) ?></div>
  <?php endif; ?>

  <!-- Bộ lọc -->
  <form method="get" class="filter-bar fade-up">
    <div class="form-group">
      <label for="fn-site">Website</label>
      <select id="fn-site" name="site">
        <?php foreach ($sites as $s): ?>
          <option value="<?= $s['id'] ?>" <?= $s['id']==$siteId?'selected':'' ?>>
            <?= htmlspecialchars($s['name']) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="form-group">
      <label for="fn-funnel">Funnel</label>
      <select id="fn-funnel" name="funnel">
        <?php foreach ($funnelDefs as $fid => $fd): ?>
          <option value="<?= $fid ?>" <?= $fid===$funnelId?'selected':'' ?>>
            <?= htmlspecialchars($fd['label']) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="form-group">
      <label for="fn-from">Từ ngày</label>
      <input id="fn-from" type="date" name="date_from" value="<?= htmlspecialchars($dateFrom) ?>">
    </div>
    <div class="form-group">
      <label for="fn-to">Đến ngày</label>
      <input id="fn-to" type="date" name="date_to" value="<?= htmlspecialchars($dateTo) ?>">
    </div>
    <button type="submit" class="btn btn-primary" style="display:inline-flex;align-items:center;gap:6px;"><?= svgIcon('search', '', 16) ?> Phân tích</button>
  </form>

  <?php if ($result): ?>
  <div class="stats-grid fade-up">
    <div class="stat-card accent">
      <div class="stat-label">Tổng phiên</div>
      <div class="stat-value"><?= number_format($result->totalSessions) ?></div>
    </div>
    <div class="stat-card green">
      <div class="stat-label">Hoàn tất chuỗi</div>
      <div class="stat-value"><?= number_format($result->completedSessions) ?></div>
    </div>
    <div class="stat-card cyan">
      <div class="stat-label">Tỉ lệ hoàn tất</div>
      <div class="stat-value"><?= $result->completionRate ?>%</div>
    </div>
  </div>

  <div class="card fade-up" style="margin-bottom:24px">
    <div class="card-title"><?= htmlspecialchars($funnelDefs[$funnelId]['label'] ?? '') ?></div>
    <div class="notice notice-info" style="margin-bottom:16px">
      Mỗi bước chỉ tính khi xảy ra SAU bước trước trong cùng phiên.
      Gửi lặp không tạo thêm lượt. Một phiên chỉ tính hoàn tất 1 lần.
    </div>
    <div class="funnel">
      <?php
      $maxCount = $result->totalSessions ?: 1;
      foreach ($steps as $i => $step):
        $count = $result->stepCounts[$i] ?? 0;
        $pct   = $maxCount > 0 ? round($count / $maxCount * 100, 1) : 0;
      ?>
      <div class="funnel-step">
        <div class="funnel-label">
          <?= htmlspecialchars($step->eventType) ?>
          <?= $step->target !== '*' ? '<br><small>' . htmlspecialchars($step->target) . '</small>' : '' ?>
        </div>
        <div class="funnel-bar-wrap">
          <div class="funnel-bar" style="width:<?= max($pct,5) ?>%"><?= number_format($count) ?></div>
        </div>
        <div class="funnel-pct"><?= $pct ?>%</div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>

  <!-- Biểu đồ donut -->
  <div class="grid-2 fade-up">
    <div class="card">
      <div class="card-title">Tỉ lệ hoàn tất / chưa hoàn tất</div>
      <div class="chart-wrap" style="height:220px">
        <canvas id="chartFunnel"></canvas>
      </div>
    </div>
    <div class="card">
      <div class="card-title">Phiên đã hoàn tất (<?= count($result->completedSessionIds) ?>)</div>
      <div class="table-wrap">
        <table>
          <thead><tr><th>#</th><th>Session ID</th></tr></thead>
          <tbody>
            <?php foreach (array_slice($result->completedSessionIds, 0, 20) as $i => $sid): ?>
            <tr><td><?= $i+1 ?></td><td><code><?= $sid ?></code></td></tr>
            <?php endforeach; ?>
            <?php if (count($result->completedSessionIds) > 20): ?>
            <tr><td colspan="2" style="color:var(--text-muted)">… và <?= count($result->completedSessionIds)-20 ?> session khác</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
  <?php endif; ?>
</div>

<script>
(function(){
  <?php if ($result): ?>
  var completed = <?= $result->completedSessions ?>;
  var total     = <?= $result->totalSessions ?>;
  var ctx = document.getElementById('chartFunnel')?.getContext('2d');
  if (!ctx) return;
  new Chart(ctx, {
    type: 'doughnut',
    data: {
      labels: ['Hoàn tất', 'Chưa hoàn tất'],
      datasets: [{
        data: [completed, Math.max(total - completed, 0)],
        backgroundColor: ['rgba(16,185,129,0.75)', 'rgba(46,51,71,0.8)'],
        borderColor:     ['rgba(16,185,129,1)',     'rgba(46,51,71,1)'],
        borderWidth: 2,
      }]
    },
    options: {
      responsive: true, maintainAspectRatio: false,
      plugins: {
        legend: { labels: { color: '#94a3b8' } }
      }
    }
  });
  <?php endif; ?>
}();
</script>
    </main>
  </div>
</div>
<?php renderMobileNav('actions'); renderFoot(); ?>
