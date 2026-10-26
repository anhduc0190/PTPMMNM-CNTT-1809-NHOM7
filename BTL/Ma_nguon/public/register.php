<?php
declare(strict_types=1);
/**
 * register.php — Chức năng 1: Đăng ký website và cấu hình sự kiện
 */
require_once __DIR__ . '/../src/Database.php';
require_once __DIR__ . '/../src/WebsiteRegistry.php';
require_once __DIR__ . '/templates/layout.php';

$message = $msgType = '';
$sites   = [];

try {
    $pdo = db();
} catch (Throwable $e) {
    $dbError = 'Không kết nối được database. Chạy: bash lab.sh db-config';
}

// Xử lý POST đăng ký website
if (isset($_POST['action']) && !isset($dbError)) {
    try {
        if ($_POST['action'] === 'register_website') {
            $name     = trim($_POST['name'] ?? '');
            $origin   = trim($_POST['origin'] ?? '');
            $timezone = trim($_POST['timezone'] ?? 'Asia/Ho_Chi_Minh');
            $site     = registerWebsite($pdo, $name, $origin, $timezone);
            $message  = "Website đã đăng ký thành công! API Key: <code style='word-break:break-all'>{$site['api_key']}</code> — Lưu lại để dùng trong tracker.js";
            $msgType  = 'success';

        } elseif ($_POST['action'] === 'add_event_def') {
            $siteId  = (int) ($_POST['website_id'] ?? 0);
            $type    = trim($_POST['event_type'] ?? '');
            $target  = trim($_POST['target'] ?? '*');
            $desc    = trim($_POST['description'] ?? '');
            $defId   = addEventDefinition($pdo, $siteId, $type, $target ?: '*', $desc);
            $message = "Đã thêm định nghĩa sự kiện thành công (ID: $defId)";
            $msgType = 'success';
        }
    } catch (InvalidArgumentException $e) {
        $message = $e->getMessage();
        $msgType = 'error';
    } catch (Throwable $e) {
        $message = 'Lỗi: ' . $e->getMessage();
        $msgType = 'error';
    }
}

if (!isset($dbError)) {
    try {
        $sites = listWebsites($pdo);
    } catch (Throwable) {}
}

renderHead('Quản lý Website & Sự kiện');
?>
<div class="app-container">
  <?php renderSidebar('events'); ?>
  <div class="main-wrapper">
    <?php renderTopbar($sites, 0); ?>
    <main class="content-body">
      <div class="page-title-section">
        <h1 class="page-title">Quản lý Website & Sự kiện</h1>
        <p class="page-subtitle">Đăng ký website thử và cấu hình các sự kiện được phép theo dõi</p>
      </div>

  <?php if (isset($dbError)): ?>
    <div class="notice notice-error"><?= htmlspecialchars($dbError) ?></div>
  <?php endif; ?>
  <?php if ($message): ?>
    <div class="notice notice-<?= $msgType === 'success' ? 'success' : 'error' ?>">
      <?= $message ?>
    </div>
  <?php endif; ?>

  <div class="grid-2" style="margin-bottom:28px">
    <!-- Form đăng ký website -->
    <div class="card fade-up">
      <div class="card-title">Đăng ký Website mới</div>
      <form method="post">
        <input type="hidden" name="action" value="register_website">
        <div class="form-group">
          <label for="reg-name">Tên website</label>
          <input id="reg-name" type="text" name="name" placeholder="Alpha Shop" required>
        </div>
        <div class="form-group">
          <label for="reg-origin">Origin (URL gốc)</label>
          <input id="reg-origin" type="url" name="origin" placeholder="http://127.0.0.1:8201" required>
        </div>
        <div class="form-group">
          <label for="reg-tz">Múi giờ</label>
          <select id="reg-tz" name="timezone">
            <option value="Asia/Ho_Chi_Minh" selected>Asia/Ho_Chi_Minh (UTC+7)</option>
            <option value="UTC">UTC</option>
          </select>
        </div>
        <button type="submit" class="btn btn-primary" style="display:inline-flex;align-items:center;gap:6px;"><?= svgIcon('plus', '', 16) ?> Đăng ký</button>
      </form>
    </div>

    <!-- Form thêm định nghĩa sự kiện -->
    <div class="card fade-up">
      <div class="card-title">Thêm Định nghĩa Sự kiện</div>
      <form method="post">
        <input type="hidden" name="action" value="add_event_def">
        <div class="form-group">
          <label for="def-site">Website</label>
          <select id="def-site" name="website_id">
            <?php foreach ($sites as $s): ?>
              <option value="<?= $s['id'] ?>"><?= htmlspecialchars($s['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label for="def-type">Loại sự kiện</label>
          <select id="def-type" name="event_type">
            <option value="pageview">pageview</option>
            <option value="click">click</option>
            <option value="form_submit">form_submit</option>
            <option value="download">download</option>
          </select>
        </div>
        <div class="form-group">
          <label for="def-target">Target (selector/path, * = mọi trang)</label>
          <input id="def-target" type="text" name="target" placeholder="#btn-download" value="*">
        </div>
        <div class="form-group">
          <label for="def-desc">Mô tả</label>
          <input id="def-desc" type="text" name="description" placeholder="Nút tải tài liệu">
        </div>
        <button type="submit" class="btn btn-primary" style="display:inline-flex;align-items:center;gap:6px;"><?= svgIcon('plus', '', 16) ?> Thêm sự kiện</button>
      </form>
    </div>
  </div>

  <!-- Danh sách websites -->
  <?php foreach ($sites as $site): ?>
  <div class="card fade-up" style="margin-bottom:20px">
    <div style="display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:8px">
      <div>
        <div class="card-title"><?= htmlspecialchars($site['name']) ?></div>
        <p style="color:var(--text-dim);font-size:.88rem;display:flex;align-items:center;gap:6px;flex-wrap:wrap">
          <?= svgIcon('globe', '', 15) ?> <a href="<?= htmlspecialchars($site['origin']) ?>" target="_blank" style="color:var(--accent2)">
            <?= htmlspecialchars($site['origin']) ?></a>
          &nbsp;·&nbsp; <?= svgIcon('clock', '', 15) ?> <?= htmlspecialchars($site['timezone']) ?>
          &nbsp;·&nbsp; <?= svgIcon('calendar', '', 15) ?> <?= $site['created_at'] ?>
        </p>
      </div>
      <span class="badge badge-ok">ID: <?= $site['id'] ?></span>
    </div>

    <!-- Snippet gắn tracker -->
    <details style="margin-top:16px">
      <summary style="cursor:pointer;font-size:.85rem;color:var(--accent);font-weight:600;display:inline-flex;align-items:center;gap:6px">
        <?= svgIcon('file', '', 15) ?> Xem đoạn code gắn tracker
      </summary>
      <p style="font-size:.8rem;color:var(--text-dim);margin:8px 0 4px">
        Dán vào &lt;head&gt; của website thử (thay YOUR_API_KEY):
      </p>
      <div class="code-block">&lt;script src="http://127.0.0.1:8200/assets/js/tracker.js"&gt;&lt;/script&gt;
&lt;script&gt;
  WA.init({
    apiKey:       'YOUR_API_KEY',
    collectUrl:   'http://127.0.0.1:8200/collect.php',
    sessionToken: localStorage.getItem('wa_session') || WA.newSession(),
  });
&lt;/script&gt;</div>
    </details>

    <!-- Event definitions -->
    <?php if (!empty($site['event_definitions'])): ?>
    <div style="margin-top:16px">
      <div class="card-title">Sự kiện được cấu hình</div>
      <div class="table-wrap">
        <table>
          <thead><tr><th>ID</th><th>Loại</th><th>Target</th><th>Mô tả</th></tr></thead>
          <tbody>
            <?php foreach ($site['event_definitions'] as $def): ?>
            <tr>
              <td><?= $def['id'] ?></td>
              <td><span class="badge badge-ok"><?= htmlspecialchars($def['event_type']) ?></span></td>
              <td><code><?= htmlspecialchars($def['target']) ?></code></td>
              <td><?= htmlspecialchars($def['description']) ?></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
    <?php endif; ?>
  </div>
  <?php endforeach; ?>
    </main>
  </div>
</div>
<?php renderMobileNav('events'); renderFoot(); ?>
