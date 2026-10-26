<?php
// ====================================================================
// PHAN TICH CHUOI THAO TAC (funnels.php)
// Chuoi tieu chuan: page_view -> download_start -> download_complete
// ====================================================================

$pageTitle = 'Phân Tích Chuỗi Thao Tác (Funnel Analysis)';
require_once __DIR__ . '/includes/header.php';

$pdo = get_db_connection();
$fromDateTime = $fromDate . ' 00:00:00';
$toDateTime   = $toDate . ' 23:59:59';

// 1. Lay danh sach su kien thuoc chuoi theo thu tu thoi gian va row_id
$stmt = $pdo->prepare('
    SELECT `session_id`, `event_name`, `page_path`, `occurred_at`, `event_row_id`
    FROM `events`
    WHERE `website_id` = ? 
      AND `event_name` IN ("page_view", "download_start", "download_complete")
      AND `occurred_at` BETWEEN ? AND ?
    ORDER BY `session_id` ASC, `occurred_at` ASC, `event_row_id` ASC
');
$stmt->execute([$selectedWebsiteId, $fromDateTime, $toDateTime]);
$events = $stmt->fetchAll();

$sessionGroups = [];
foreach ($events as $e) {
    $sessionGroups[$e['session_id']][] = $e;
}

$step1Sessions = 0; // page_view
$step2Sessions = 0; // download_start
$step3Sessions = 0; // download_complete

$sessionRecords = [];

foreach ($sessionGroups as $sessId => $sEvents) {
    $has1 = false;
    $has2 = false;
    $has3 = false;

    $t1 = null;
    $t2 = null;
    $t3 = null;

    foreach ($sEvents as $e) {
        $name = $e['event_name'];
        $time = strtotime($e['occurred_at']);

        if ($name === 'page_view' && !$has1) {
            $has1 = true;
            $t1   = $e['occurred_at'];
            continue;
        }

        if ($name === 'download_start' && $has1 && !$has2) {
            if ($time >= strtotime($t1)) {
                $has2 = true;
                $t2   = $e['occurred_at'];
                continue;
            }
        }

        if ($name === 'download_complete' && $has2 && !$has3) {
            if ($time >= strtotime($t2)) {
                $has3 = true;
                $t3   = $e['occurred_at'];
                break;
            }
        }
    }

    if ($has1) $step1Sessions++;
    if ($has2) $step2Sessions++;
    if ($has3) $step3Sessions++;

    $status = 'Bỏ dở tại Bước 1';
    $statusClass = 'badge-red';
    if ($has3) {
        $status = 'Hoàn tất chuỗi (Completed)';
        $statusClass = 'badge-green';
    } elseif ($has2) {
        $status = 'Bỏ dở sau Bước 2 (Chưa tải xong)';
        $statusClass = 'badge-yellow';
    }

    $sessionRecords[] = [
        'session_id' => $sessId,
        'has_step1'  => $has1,
        'has_step2'  => $has2,
        'has_step3'  => $has3,
        't1'         => $t1,
        't2'         => $t2,
        't3'         => $t3,
        'status'     => $status,
        'badge'      => $statusClass
    ];
}

$convRate = $step1Sessions > 0 ? round(($step3Sessions / $step1Sessions) * 100, 2) : 0;
$step2Pct = $step1Sessions > 0 ? round(($step2Sessions / $step1Sessions) * 100, 1) : 0;
$step3Pct = $step2Sessions > 0 ? round(($step3Sessions / $step2Sessions) * 100, 1) : 0;
?>

<!-- QUY TAC TINH TOAN CHUOI HOP LE -->
<div class="card-section" style="background:#eff6ff; border-color:#bfdbfe;">
    <h3 style="color:#1e40af; margin-bottom:0.5rem;">🎯 Định Nghĩa Chuỗi Thao Tác Chuyển Đổi (Conversion Funnel)</h3>
    <p style="color:#1e3a8a; font-size:0.9rem; line-height:1.6;">
        Một phiên truy cập được tính là <b>Hoàn tất chuỗi</b> khi và chỉ khi thỏa mãn đầy đủ các điều kiện nghiêm ngặt:
        <br>1. Phải bắt đầu bằng sự kiện xem trang (<code>page_view</code>).
        <br>2. Tiếp tục bằng thao tác bấm bắt đầu tải tài liệu (<code>download_start</code>) xảy ra sau hoặc cùng lúc với Bước 1.
        <br>3. Kết thúc bằng sự kiện tải file thành công (<code>download_complete</code>) xảy ra sau hoặc cùng lúc với Bước 2.
        <br>4. Tất cả các bước phải thuộc <b>cùng website</b> và <b>cùng session</b>. Nếu sai thứ tự hoặc thiếu bước sẽ không được tính.
    </p>
</div>

<!-- TRỰC QUAN HÓA PHỄU 3 BƯỚC -->
<div class="card-section">
    <div class="card-title-bar">
        <h3>📊 Trực Quan Hóa Tỷ Lệ Chuyển Đổi Qua Từng Bước</h3>
        <span class="badge badge-blue">Tỷ lệ hoàn tất toàn chuỗi: <strong><?= $convRate ?>%</strong></span>
    </div>

    <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap:1.5rem; margin-top:1rem;">
        <!-- Bước 1 -->
        <div style="background:#f8fafc; border:2px solid #93c5fd; border-radius:12px; padding:1.5rem; text-align:center;">
            <div style="font-size:0.8rem; font-weight:700; color:#2563eb; text-transform:uppercase;">BƯỚC 1: XEM TRANG</div>
            <div style="font-size:1.1rem; font-weight:700; margin:0.4rem 0;"><code>page_view</code></div>
            <div style="font-size:2.2rem; font-weight:800; color:#1e293b;"><?= number_format($step1Sessions) ?></div>
            <div style="font-size:0.85rem; color:#64748b;">Số phiên bắt đầu (100%)</div>
        </div>

        <!-- Bước 2 -->
        <div style="background:#f8fafc; border:2px solid #86efac; border-radius:12px; padding:1.5rem; text-align:center;">
            <div style="font-size:0.8rem; font-weight:700; color:#16a34a; text-transform:uppercase;">BƯỚC 2: BẮT ĐẦU TẢI</div>
            <div style="font-size:1.1rem; font-weight:700; margin:0.4rem 0;"><code>download_start</code></div>
            <div style="font-size:2.2rem; font-weight:800; color:#1e293b;"><?= number_format($step2Sessions) ?></div>
            <div style="font-size:0.85rem; color:#16a34a; font-weight:600;">
                <?= $step2Pct ?>% phiên đi tiếp (Rơi rớt: <?= 100 - $step2Pct ?>%)
            </div>
        </div>

        <!-- Bước 3 -->
        <div style="background:#f8fafc; border:2px solid #a78bfa; border-radius:12px; padding:1.5rem; text-align:center;">
            <div style="font-size:0.8rem; font-weight:700; color:#7c3aed; text-transform:uppercase;">BƯỚC 3: HOÀN TẤT TẢI</div>
            <div style="font-size:1.1rem; font-weight:700; margin:0.4rem 0;"><code>download_complete</code></div>
            <div style="font-size:2.2rem; font-weight:800; color:#10b981;"><?= number_format($step3Sessions) ?></div>
            <div style="font-size:0.85rem; color:#10b981; font-weight:700;">
                <?= $convRate ?>% hoàn tất toàn bộ chuỗi
            </div>
        </div>
    </div>
</div>

<!-- BANG DOI CHIEU TUNG PHIEN -->
<div class="card-section">
    <div class="card-title-bar">
        <h3>🔍 Đối Chiếu Sự Kiện Nguồn Từng Phiên Truy Cập (Audit Trail)</h3>
        <span class="badge badge-gray"><?= count($sessionRecords) ?> phiên phân tích</span>
    </div>

    <div class="table-responsive">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Session ID (Phiên)</th>
                    <th>Bước 1 (page_view)</th>
                    <th>Bước 2 (download_start)</th>
                    <th>Bước 3 (download_complete)</th>
                    <th>Kết Quả Chuỗi</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach (array_slice($sessionRecords, 0, 50) as $sr): ?>
                    <tr>
                        <td title="<?= e($sr['session_id']) ?>">
                            <span style="font-family:monospace; font-size:0.8rem; font-weight:600;">
                                <?= substr(e($sr['session_id']), 0, 18) ?>...
                            </span>
                        </td>
                        <td>
                            <?php if ($sr['has_step1']): ?>
                                <span class="badge badge-blue">✓ <?= date('H:i:s', strtotime($sr['t1'])) ?></span>
                            <?php else: ?>
                                <span style="color:#94a3b8;">✗ Thiếu</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($sr['has_step2']): ?>
                                <span class="badge badge-green">✓ <?= date('H:i:s', strtotime($sr['t2'])) ?></span>
                            <?php else: ?>
                                <span style="color:#94a3b8;">✗ Thiếu</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($sr['has_step3']): ?>
                                <span class="badge badge-purple" style="background:#f3e8ff; color:#7e22ce; border:1px solid #d8b4fe;">
                                    ✓ <?= date('H:i:s', strtotime($sr['t3'])) ?>
                                </span>
                            <?php else: ?>
                                <span style="color:#94a3b8;">✗ Thiếu</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="badge <?= $sr['badge'] ?>">
                                <?= e($sr['status']) ?>
                            </span>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (empty($sessionRecords)): ?>
                    <tr><td colspan="5" style="text-align:center; color:#94a3b8; padding:2rem;">Chưa có phiên nào có sự kiện thuộc chuỗi funnel</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
