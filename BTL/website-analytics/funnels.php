<?php
// ====================================================================
// PHÂN TÍCH CHUỖI THAO TÁC (funnels.php)
// Chuỗi tiêu chuẩn: page_view -> download_start -> download_complete
// Phong cách Enterprise SaaS: Trực quan hóa phễu chuyển đổi, Audit Trail
// ====================================================================

$pageTitle = 'Phân Tích Chuỗi Thao Tác (Funnel Analysis)';
require_once __DIR__ . '/includes/header.php';

$pdo = get_db_connection();
$fromDateTime = $fromDate . ' 00:00:00';
$toDateTime   = $toDate . ' 23:59:59';

// 1. Lấy danh sách sự kiện thuộc chuỗi theo thứ tự thời gian và row_id
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
        $status = 'Hoàn tất chuỗi';
        $statusClass = 'badge-green';
    } elseif ($has2) {
        $status = 'Bỏ dở sau Bước 2 (Chưa tải xong)';
        $statusClass = 'badge-yellow';
    }
    $firstTime = null;
    $latestTime = null;
    foreach ($sEvents as $e) {
        $t = strtotime($e['occurred_at']);
        if ($firstTime === null || $t < $firstTime) $firstTime = $t;
        if ($latestTime === null || $t > $latestTime) $latestTime = $t;
    }
    $durationSec = ($latestTime && $firstTime) ? ($latestTime - $firstTime) : 0;

    $sessionRecords[] = [
        'session_id'   => $sessId,
        'has_step1'    => $has1,
        'has_step2'    => $has2,
        'has_step3'    => $has3,
        't1'           => $t1,
        't2'           => $t2,
        't3'           => $t3,
        'latest_time'  => $latestTime,
        'duration_sec' => $durationSec,
        'status'       => $status,
        'badge'        => $statusClass
    ];
}

// Sắp xếp các phiên có tương tác mới nhất lên ĐẦU TIÊN (Row #1)
usort($sessionRecords, function($a, $b) {
    return ($b['latest_time'] ?? 0) <=> ($a['latest_time'] ?? 0);
});

$convRate = $step1Sessions > 0 ? round(($step3Sessions / $step1Sessions) * 100, 2) : 0;
$step2Pct = $step1Sessions > 0 ? round(($step2Sessions / $step1Sessions) * 100, 1) : 0;
$step3Pct = $step2Sessions > 0 ? round(($step3Sessions / $step2Sessions) * 100, 1) : 0;
?>

<!-- QUY TẮC TÍNH TOÁN CHUỖI HỢP LỆ -->
<div class="card-section" style="background:var(--accent-blue-bg); border-color:var(--accent-blue-border);">
    <div style="display:flex; align-items:flex-start; gap:0.85rem;">
        <div style="color:var(--accent-blue-text); margin-top:2px;">
            <?= render_icon('filter', '', 20) ?>
        </div>
        <div>
            <h3 style="color:var(--accent-blue-text); margin-bottom:0.4rem; font-size:1.05rem;">
                Định Nghĩa Chuỗi Thao Tác Chuyển Đổi (Conversion Funnel)
            </h3>
            <p style="color:var(--text-secondary); font-size:0.88rem; line-height:1.6;">
                Một phiên truy cập được tính là <b>Hoàn tất chuỗi</b> khi và chỉ khi thỏa mãn đầy đủ 4 điều kiện nghiêm ngặt:
                <br><b>1.</b> Bắt đầu bằng sự kiện xem trang (<code>page_view</code>).
                <br><b>2.</b> Tiếp tục bằng thao tác bấm bắt đầu tải tài liệu (<code>download_start</code>) xảy ra sau hoặc cùng lúc với Bước 1.
                <br><b>3.</b> Kết thúc bằng sự kiện tải file thành công (<code>download_complete</code>) xảy ra sau hoặc cùng lúc với Bước 2.
                <br><b>4.</b> Tất cả các bước phải thuộc <b>cùng website</b> và <b>cùng session</b>. Nếu sai thứ tự hoặc thiếu bước sẽ không được tính.
            </p>
        </div>
    </div>
</div>

<!-- TRỰC QUAN HÓA PHỄU 3 BƯỚC -->
<div class="card-section">
    <div class="card-title-bar">
        <h3>
            <?= render_icon('activity', '', 18, 'var(--primary)') ?>
            Trực Quan Hóa Tỷ Lệ Chuyển Đổi Qua Từng Bước
        </h3>
        <span class="badge badge-green" style="font-size:0.82rem; padding:0.35rem 0.75rem;">
            <?= render_icon('check-circle', '', 14) ?>
            Tỷ lệ hoàn tất toàn chuỗi: <strong><?= $convRate ?>%</strong>
        </span>
    </div>

    <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap:1.25rem; margin-top:1rem;">
        <!-- Bước 1 -->
        <div style="background:#ffffff; border:1px solid var(--accent-blue-border); border-top:4px solid var(--primary); border-radius:var(--radius-md); padding:1.4rem; box-shadow:var(--shadow-xs);">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:0.6rem;">
                <span style="font-size:0.75rem; font-weight:700; color:var(--primary); text-transform:uppercase; letter-spacing:0.04em;">BƯỚC 1: XEM TRANG</span>
                <span class="kpi-icon-wrap blue" style="width:28px; height:28px;">
                    <?= render_icon('eye', '', 15) ?>
                </span>
            </div>
            <div style="font-size:1.05rem; font-weight:700; color:var(--text-primary); margin-bottom:0.3rem;">
                <code>page_view</code>
            </div>
            <div style="font-size:2rem; font-weight:800; color:var(--text-primary); line-height:1.2;">
                <?= number_format($step1Sessions) ?>
            </div>
            <div style="font-size:0.8rem; color:var(--text-muted); margin-top:0.4rem;">
                Số phiên bắt đầu (100% làm gốc)
            </div>
            <div style="margin-top:0.85rem; height:6px; background:#e2e8f0; border-radius:3px; overflow:hidden;">
                <div style="height:100%; width:100%; background:var(--primary);"></div>
            </div>
        </div>

        <!-- Bước 2 -->
        <div style="background:#ffffff; border:1px solid var(--accent-yellow-border); border-top:4px solid var(--accent-yellow); border-radius:var(--radius-md); padding:1.4rem; box-shadow:var(--shadow-xs);">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:0.6rem;">
                <span style="font-size:0.75rem; font-weight:700; color:var(--accent-yellow-text); text-transform:uppercase; letter-spacing:0.04em;">BƯỚC 2: BẮT ĐẦU TẢI</span>
                <span class="kpi-icon-wrap yellow" style="width:28px; height:28px;">
                    <?= render_icon('download', '', 15) ?>
                </span>
            </div>
            <div style="font-size:1.05rem; font-weight:700; color:var(--text-primary); margin-bottom:0.3rem;">
                <code>download_start</code>
            </div>
            <div style="font-size:2rem; font-weight:800; color:var(--text-primary); line-height:1.2;">
                <?= number_format($step2Sessions) ?>
            </div>
            <div style="font-size:0.8rem; color:var(--accent-yellow-text); font-weight:600; margin-top:0.4rem;">
                <?= $step2Pct ?>% phiên đi tiếp (Rơi rớt: <?= round(100 - $step2Pct, 1) ?>%)
            </div>
            <div style="margin-top:0.85rem; height:6px; background:#e2e8f0; border-radius:3px; overflow:hidden;">
                <div style="height:100%; width:<?= $step2Pct ?>%; background:var(--accent-yellow);"></div>
            </div>
        </div>

        <!-- Bước 3 -->
        <div style="background:#ffffff; border:1px solid var(--accent-green-border); border-top:4px solid var(--accent-green); border-radius:var(--radius-md); padding:1.4rem; box-shadow:var(--shadow-xs);">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:0.6rem;">
                <span style="font-size:0.75rem; font-weight:700; color:var(--accent-green-text); text-transform:uppercase; letter-spacing:0.04em;">BƯỚC 3: HOÀN TẤT TẢI</span>
                <span class="kpi-icon-wrap green" style="width:28px; height:28px;">
                    <?= render_icon('check-circle', '', 15) ?>
                </span>
            </div>
            <div style="font-size:1.05rem; font-weight:700; color:var(--text-primary); margin-bottom:0.3rem;">
                <code>download_complete</code>
            </div>
            <div style="font-size:2rem; font-weight:800; color:var(--accent-green); line-height:1.2;">
                <?= number_format($step3Sessions) ?>
            </div>
            <div style="font-size:0.8rem; color:var(--accent-green-text); font-weight:700; margin-top:0.4rem;">
                <?= $convRate ?>% hoàn tất toàn bộ chuỗi
            </div>
            <div style="margin-top:0.85rem; height:6px; background:#e2e8f0; border-radius:3px; overflow:hidden;">
                <div style="height:100%; width:<?= $convRate ?>%; background:var(--accent-green);"></div>
            </div>
        </div>
    </div>
</div>

<!-- BẢNG ĐỐI CHIẾU TỪNG PHIÊN -->
<div class="card-section">
    <div class="card-title-bar">
        <h3>
            <?= render_icon('search', '', 18, 'var(--text-secondary)') ?>
            Đối Chiếu Sự Kiện Nguồn Từng Phiên Truy Cập (Audit Trail)
        </h3>
        <span class="badge badge-gray">
            <?= render_icon('users-round', '', 12) ?>
            <?= count($sessionRecords) ?> phiên phân tích
        </span>
    </div>

    <div class="table-responsive">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Session ID (Phiên)</th>
                    <th>Thời Lượng Phiên</th>
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
                            <span style="font-weight:600; color:var(--text-secondary); font-size:0.82rem;">
                                <?= render_icon('clock', '', 12, 'var(--text-muted)') ?>
                                <?= $sr['duration_sec'] >= 60 ? (floor($sr['duration_sec'] / 60) . 'm ' . ($sr['duration_sec'] % 60) . 's') : ($sr['duration_sec'] . 's') ?>
                            </span>
                        </td>
                        <td>
                            <?php if ($sr['has_step1']): ?>
                                <span class="badge badge-blue">
                                    <?= render_icon('check', '', 11) ?>
                                    <?= date('H:i:s', strtotime($sr['t1'])) ?>
                                </span>
                            <?php else: ?>
                                <span style="color:var(--text-muted); font-size:0.8rem;">
                                    <?= render_icon('x', '', 12) ?> Thiếu
                                </span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($sr['has_step2']): ?>
                                <span class="badge badge-yellow">
                                    <?= render_icon('check', '', 11) ?>
                                    <?= date('H:i:s', strtotime($sr['t2'])) ?>
                                </span>
                            <?php else: ?>
                                <span style="color:var(--text-muted); font-size:0.8rem;">
                                    <?= render_icon('x', '', 12) ?> Thiếu
                                </span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($sr['has_step3']): ?>
                                <span class="badge badge-green">
                                    <?= render_icon('check-circle', '', 11) ?>
                                    <?= date('H:i:s', strtotime($sr['t3'])) ?>
                                </span>
                            <?php else: ?>
                                <span style="color:var(--text-muted); font-size:0.8rem;">
                                    <?= render_icon('x', '', 12) ?> Thiếu
                                </span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="badge <?= $sr['badge'] ?>">
                                <?= render_icon($sr['has_step3'] ? 'check-circle' : ($sr['has_step2'] ? 'alert-circle' : 'x-circle'), '', 11) ?>
                                <?= e($sr['status']) ?>
                            </span>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (empty($sessionRecords)): ?>
                    <tr>
                        <td colspan="6" style="text-align:center; color:var(--text-muted); padding:2.5rem;">
                            Chưa có phiên nào có sự kiện thuộc chuỗi funnel trong kỳ báo cáo đã chọn
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
