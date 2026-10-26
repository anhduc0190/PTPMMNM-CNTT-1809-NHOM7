<?php
declare(strict_types=1);
/**
 * report.php — Chức năng 4 & 5: Xem & xuất báo cáo chi tiết
 * Kiểm tra quyền bằng API key trước khi hiển thị dữ liệu.
 */
require_once __DIR__ . '/../src/Database.php';
require_once __DIR__ . '/../src/Aggregator.php';
require_once __DIR__ . '/../src/ReportExporter.php';
require_once __DIR__ . '/templates/layout.php';

$error  = '';
$sites  = [];
$events = [];

// Tham số
$siteId   = (int) ($_GET['site'] ?? 0);
$apiKey   = trim($_GET['api_key'] ?? $_POST['api_key'] ?? '');
$dateFrom = $_GET['date_from'] ?? date('Y-m-d', strtotime('-7 days'));
$dateTo   = $_GET['date_to']   ?? date('Y-m-d');
$format   = in_array($_GET['format'] ?? '', ['csv','json']) ? $_GET['format'] : '';

try {
    $pdo   = db();
    $sites = dbQuery($pdo, 'SELECT id, name FROM websites WHERE active=1')->fetchAll();
} catch (Throwable $e) {
    $error = 'Không kết nối được database.';
}

// Xuất file nếu có format
if ($format && $siteId && $apiKey && !$error) {
    try {
        if (!checkReportPermission($pdo, $siteId, $apiKey)) {
            http_response_code(403);
            die(json_encode(['error' => 'Forbidden: API key không hợp lệ cho website này']));
        }
        $content = exportReport($pdo, $siteId, $format, [
            'date_from' => $dateFrom, 'date_to' => $dateTo,
        ]);
        if ($format === 'csv') {
            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename="report-site' . $siteId . '-' . $dateTo . '.csv"');
        } else {
            header('Content-Type: application/json; charset=utf-8');
            header('Content-Disposition: attachment; filename="report-site' . $siteId . '-' . $dateTo . '.json"');
        }
        echo $content;
        exit;
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

// Xem báo cáo trên giao diện (cũng cần API key)
$permitted = false;
$rawEvents = [];
$summary   = [];

if ($siteId && $apiKey && !$error) {
    try {
        $permitted = checkReportPermission($pdo, $siteId, $apiKey);
        if ($permitted) {
            $filter    = ['date_from' => $dateFrom, 'date_to' => $dateTo];
            $rawEvents = getRawEvents($pdo, $siteId, $filter);
            $summary   = aggregateSummary($pdo, $siteId, $filter);
            // Ghi audit log
            dbQuery($pdo,
                'INSERT INTO report_queries (website_id, viewer_key, params) VALUES (?,?,?)',
                [$siteId, 'web-ui', json_encode($filter)]
            );
        } else {
            $error = 'API key không hợp lệ cho website này.';
        }
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

renderHead('Báo cáo Chi tiết');
?>
<div class="app-container">
  <?php renderSidebar('reports'); ?>
  <div class="main-wrapper">
    <?php renderTopbar($sites, $siteId, $dateFrom, $dateTo); ?>
    <main class="content-body">
      <div class="page-title-section">
        <h1 class="page-title">Báo cáo Chi tiết & Xuất dữ liệu</h1>
        <p class="page-subtitle">Truy xuất sự kiện thô, đối chiếu dữ liệu nguồn và xuất CSV / JSON</p>
      </div>

  <?php if ($error): ?>
    <div class="notice notice-error"><?= htmlspecialchars($error) ?></div>
  <?php endif; ?>

  <!-- Form truy vấn -->
  <form method="get" class="card fade-up" style="margin-bottom:24px">
    <div class="card-title">Tham số báo cáo</div>
    <div class="form-row">
      <div class="form-group">
        <label for="rp-site">Website</label>
        <select id="rp-site" name="site">
          <option value="">— Chọn website —</option>
          <?php foreach ($sites as $s): ?>
            <option value="<?= $s['id'] ?>" <?= $s['id']==$siteId?'selected':'' ?>>
              <?= htmlspecialchars($s['name']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-group">
        <label for="rp-key">API Key</label>
        <input id="rp-key" type="password" name="api_key" value="<?= htmlspecialchars($apiKey) ?>"
               placeholder="64-char hex key" autocomplete="off">
      </div>
    </div>
    <div class="form-row">
      <div class="form-group">
        <label for="rp-from">Từ ngày</label>
        <input id="rp-from" type="date" name="date_from" value="<?= htmlspecialchars($dateFrom) ?>">
      </div>
      <div class="form-group">
        <label for="rp-to">Đến ngày</label>
        <input id="rp-to" type="date" name="date_to" value="<?= htmlspecialchars($dateTo) ?>">
      </div>
    </div>
    <div style="display:flex;gap:10px;flex-wrap:wrap">
      <button type="submit" class="btn btn-primary" style="display:inline-flex;align-items:center;gap:6px;"><?= svgIcon('search', '', 16) ?> Xem báo cáo</button>
      <button type="submit" name="format" value="csv"  class="btn btn-outline" style="display:inline-flex;align-items:center;gap:6px;"><?= svgIcon('download', '', 14) ?> CSV</button>
      <button type="submit" name="format" value="json" class="btn btn-outline" style="display:inline-flex;align-items:center;gap:6px;"><?= svgIcon('download', '', 14) ?> JSON</button>
    </div>
  </form>

  <?php if ($permitted && $summary): ?>
  <!-- Summary stats -->
  <div class="stats-grid fade-up">
    <div class="stat-card accent">
      <div class="stat-label">Lượt xem</div>
      <div class="stat-value"><?= number_format($summary['pageviews']) ?></div>
    </div>
    <div class="stat-card cyan">
      <div class="stat-label">Phiên duy nhất</div>
      <div class="stat-value"><?= number_format($summary['unique_sessions']) ?></div>
    </div>
    <?php foreach ($summary['events_by_type'] as $type => $cnt): ?>
    <div class="stat-card green">
      <div class="stat-label"><?= htmlspecialchars($type) ?></div>
      <div class="stat-value"><?= number_format($cnt) ?></div>
    </div>
    <?php endforeach; ?>
  </div>

  <!-- Bảng sự kiện thô -->
  <div class="card fade-up">
    <div class="card-title">Sự kiện thô (<?= count($rawEvents) ?> dòng)</div>
    <div class="notice notice-info" style="margin-bottom:12px;display:flex;align-items:center;gap:8px">
      <span><?= svgIcon('info', '', 16) ?></span>
      <span>Báo cáo được tính từ sự kiện thực. Không chứa nội dung trường nhập liệu. IP đã được ẩn danh (/24). Múi giờ hiển thị: UTC+7.</span>
    </div>
    <div class="table-wrap">
      <table>
        <thead>
          <tr>
            <th>ID</th><th>Session</th><th>Loại</th><th>Trang</th>
            <th>Target</th><th>Thời điểm (client, UTC)</th><th>Nhận (server, UTC)</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($rawEvents as $ev): ?>
          <tr>
            <td><?= $ev['id'] ?></td>
            <td><code><?= substr((string)$ev['session_id'], 0, 8) ?>…</code></td>
            <td><span class="badge badge-ok"><?= htmlspecialchars($ev['event_type']) ?></span></td>
            <td><?= htmlspecialchars($ev['page_path']) ?></td>
            <td><?= htmlspecialchars($ev['target_selector']) ?></td>
            <td><?= $ev['client_ts'] ?></td>
            <td><?= $ev['server_ts'] ?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
  <?php endif; ?>
    </main>
  </div>
</div>
<?php renderMobileNav('reports'); renderFoot(); ?>
