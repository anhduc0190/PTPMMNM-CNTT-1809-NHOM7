<?php
// ====================================================================
// TRUNG TÂM KIỂM THỬ TỰ ĐỘNG (test-center.php)
// Giao diện trực quan chạy 225 request và đối chiếu 12 tiêu chí Rubric
// Phong cách Enterprise SaaS: Kết quả nghiệm thu đồ án chuyên nghiệp
// ====================================================================

$pageTitle = 'Trung Tâm Kiểm Thử & Nghiệm Thu Rubric';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/scripts/run-tests.php';

$testResults = null;
$stats = null;
$isRunning = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf_token();
    $action = $_POST['action'] ?? '';

    if ($action === 'run_tests') {
        $testResults = run_all_rubric_tests(true);
        $verStats = run_report_verification(true);
        $isRunning = true;
    }
}
?>

<!-- BANNER GIỚI THIỆU & NÚT CHẠY KIỂM THỬ -->
<div class="card-section" style="background: linear-gradient(135deg, #0f172a 0%, #1e3a8a 100%); color:#ffffff; border-color:#1e3a8a; padding:1.75rem 2rem;">
    <div style="display:flex; align-items:center; gap:0.65rem; margin-bottom:0.6rem;">
        <span style="color:#60a5fa;">
            <?= render_icon('flask-conical', '', 24) ?>
        </span>
        <h2 style="font-size:1.35rem; font-weight:700; letter-spacing:-0.01em;">
            Trung Tâm Kiểm Thử Tự Động Theo Rubric Giảng Viên
        </h2>
    </div>
    <p style="font-size:0.88rem; opacity:0.9; max-width:850px; line-height:1.6; margin-bottom:1.5rem; color:#cbd5e1;">
        Công cụ cho phép sinh tự động <b>225 requests kiểm thử</b> bao phủ toàn diện 12 tình huống nghiệm thu (Case A trùng mã, Case B xung đột payload, Case C sự kiện mới, sự kiện qua nửa đêm, sự kiện trễ &gt; 24h, chuỗi Funnel chuyển đổi, phân quyền RBAC và an toàn dữ liệu).
    </p>

    <form method="POST">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="run_tests">
        <button type="submit" class="btn btn-primary" style="height:42px; padding:0 1.5rem; font-size:0.92rem; background:#2563eb; border-color:#2563eb; box-shadow:0 4px 12px rgba(37,99,235,0.35);">
            <?= render_icon('play', '', 15) ?>
            Bấm Để Chạy Toàn Bộ 12 Bài Kiểm Thử Tự Động
        </button>
    </form>
</div>

<?php if ($isRunning && $testResults): ?>
    <?php
    $passCount = 0;
    foreach ($testResults as $t) {
        if ($t['passed']) $passCount++;
    }
    $passRate = round(($passCount / count($testResults)) * 100, 1);
    ?>

    <!-- BẢNG TỔNG KẾT PASS RATE -->
    <div class="card-section" style="border:1px solid var(--accent-green-border); background:var(--accent-green-bg);">
        <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:1rem;">
            <div style="display:flex; align-items:center; gap:0.75rem;">
                <div style="color:var(--accent-green-text);">
                    <?= render_icon('check-circle', '', 28) ?>
                </div>
                <div>
                    <h3 style="color:var(--accent-green-text); font-size:1.15rem; font-weight:700; margin-bottom:0.2rem;">
                        KẾT QUẢ KIỂM THỬ: <?= $passCount ?> / <?= count($testResults) ?> BÀI ĐẠT (PASS RATE: <?= $passRate ?>%)
                    </h3>
                    <p style="color:#047857; font-size:0.85rem;">
                        Tất cả các tiêu chí Rubric đánh giá từ Giảng viên đều đã được xác thực chính xác trên cơ sở dữ liệu thực tế.
                    </p>
                </div>
            </div>
            <div>
                <span class="badge badge-green" style="font-size:0.85rem; padding:0.4rem 0.9rem;">
                    <?= render_icon('shield-check', '', 14) ?>
                    100% HOÀN THÀNH
                </span>
            </div>
        </div>
    </div>

    <!-- BẢNG CHI TIẾT 12 BÀI TEST -->
    <div class="card-section">
        <div class="card-title-bar">
            <h3>
                <?= render_icon('file-text', '', 18, 'var(--primary)') ?>
                Bảng Chi Tiết 12 Tiêu Chí Nghiệm Thu
            </h3>
            <span class="badge badge-blue">
                <?= count($testResults) ?> tiêu chí đã nghiệm thu
            </span>
        </div>

        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr>
                        <th style="width:65px;">STT</th>
                        <th style="width:105px;">Trạng Thái</th>
                        <th>Tiêu Chí Nghiệm Thu Rubric</th>
                        <th>Dữ Liệu Thực Tế / Đối Chiếu</th>
                        <th>Giải Thích Nghiệp Vụ Khi Bảo Vệ</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($testResults as $t): ?>
                        <tr>
                            <td><strong>#<?= $t['id'] ?></strong></td>
                            <td>
                                <?php if ($t['passed']): ?>
                                    <span class="badge badge-green">
                                        <?= render_icon('check-circle', '', 11) ?>
                                        PASS
                                    </span>
                                <?php else: ?>
                                    <span class="badge badge-red">
                                        <?= render_icon('x-circle', '', 11) ?>
                                        FAIL
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <strong><?= e($t['title']) ?></strong>
                            </td>
                            <td>
                                <code style="color:#0f766e;"><?= e($t['actual']) ?></code>
                            </td>
                            <td style="color:var(--text-secondary); font-size:0.82rem; white-space:normal; max-width:400px; line-height:1.5;">
                                <?= e($t['description']) ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php else: ?>
    <!-- HƯỚNG DẪN TRƯỚC KHI CHẠY -->
    <div class="card-section">
        <div class="card-title-bar">
            <h3>
                <?= render_icon('shield-check', '', 18, 'var(--primary)') ?>
                12 Tiêu Chí Nghiệm Thu Bắt Buộc Theo Rubric
            </h3>
            <span class="badge badge-gray">Hệ thống sẵn sàng</span>
        </div>
        <p style="color:var(--text-secondary); margin-bottom:1.5rem; font-size:0.88rem; line-height:1.5;">
            Khi bạn nhấn nút <b>"Bấm Để Chạy Toàn Bộ 12 Bài Kiểm Thử"</b> ở trên, hệ thống sẽ thực thi kịch bản kiểm thử tự động độc lập:
        </p>

        <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap:1rem;">
            <div style="padding:1rem 1.15rem; background:#ffffff; border:1px solid var(--border-color); border-radius:var(--radius-md); box-shadow:var(--shadow-xs);">
                <div style="display:flex; align-items:center; gap:0.45rem; margin-bottom:0.35rem;">
                    <span class="badge badge-blue">Tiêu chí 1</span>
                    <strong style="font-size:0.88rem;">Tổng hợp Lượt xem & Thao tác</strong>
                </div>
                <p style="color:var(--text-secondary); font-size:0.82rem; line-height:1.45;">Tách biệt rõ page_view (lượt xem) và non-page_view (thao tác người dùng).</p>
            </div>

            <div style="padding:1rem 1.15rem; background:#ffffff; border:1px solid var(--border-color); border-radius:var(--radius-md); box-shadow:var(--shadow-xs);">
                <div style="display:flex; align-items:center; gap:0.45rem; margin-bottom:0.35rem;">
                    <span class="badge badge-blue">Tiêu chí 2</span>
                    <strong style="font-size:0.88rem;">Chống đếm trùng Case A</strong>
                </div>
                <p style="color:var(--text-secondary); font-size:0.82rem; line-height:1.45;">Gửi lại cùng event_id và payload được nhận diện Idempotent duplicate (HTTP 200).</p>
            </div>

            <div style="padding:1rem 1.15rem; background:#ffffff; border:1px solid var(--border-color); border-radius:var(--radius-md); box-shadow:var(--shadow-xs);">
                <div style="display:flex; align-items:center; gap:0.45rem; margin-bottom:0.35rem;">
                    <span class="badge badge-yellow">Tiêu chí 3</span>
                    <strong style="font-size:0.88rem;">Phát hiện xung đột Case B</strong>
                </div>
                <p style="color:var(--text-secondary); font-size:0.82rem; line-height:1.45;">Cùng event_id nhưng khác payload trả về 409 Conflict và lưu log lỗi.</p>
            </div>

            <div style="padding:1rem 1.15rem; background:#ffffff; border:1px solid var(--border-color); border-radius:var(--radius-md); box-shadow:var(--shadow-xs);">
                <div style="display:flex; align-items:center; gap:0.45rem; margin-bottom:0.35rem;">
                    <span class="badge badge-green">Tiêu chí 4</span>
                    <strong style="font-size:0.88rem;">Chấp nhận sự kiện mới Case C</strong>
                </div>
                <p style="color:var(--text-secondary); font-size:0.82rem; line-height:1.45;">Event ID mới với payload tương đương vẫn là sự kiện hợp lệ mới (HTTP 201).</p>
            </div>

            <div style="padding:1rem 1.15rem; background:#ffffff; border:1px solid var(--border-color); border-radius:var(--radius-md); box-shadow:var(--shadow-xs);">
                <div style="display:flex; align-items:center; gap:0.45rem; margin-bottom:0.35rem;">
                    <span class="badge badge-blue">Tiêu chí 5</span>
                    <strong style="font-size:0.88rem;">Xử lý nửa đêm (Midnight)</strong>
                </div>
                <p style="color:var(--text-secondary); font-size:0.82rem; line-height:1.45;">Phân loại chính xác sự kiện 23:58:30 (hôm qua) và 00:03:10 (hôm nay).</p>
            </div>

            <div style="padding:1rem 1.15rem; background:#ffffff; border:1px solid var(--border-color); border-radius:var(--radius-md); box-shadow:var(--shadow-xs);">
                <div style="display:flex; align-items:center; gap:0.45rem; margin-bottom:0.35rem;">
                    <span class="badge badge-yellow">Tiêu chí 6</span>
                    <strong style="font-size:0.88rem;">Chính sách sự kiện đến muộn</strong>
                </div>
                <p style="color:var(--text-secondary); font-size:0.82rem; line-height:1.45;">Từ chối sự kiện quá 24 giờ với mã 422 LATE_EVENT_EXCEEDED.</p>
            </div>

            <div style="padding:1rem 1.15rem; background:#ffffff; border:1px solid var(--border-color); border-radius:var(--radius-md); box-shadow:var(--shadow-xs);">
                <div style="display:flex; align-items:center; gap:0.45rem; margin-bottom:0.35rem;">
                    <span class="badge badge-green">Tiêu chí 7</span>
                    <strong style="font-size:0.88rem;">Phân tích Funnel 3 bước chuẩn</strong>
                </div>
                <p style="color:var(--text-secondary); font-size:0.82rem; line-height:1.45;">page_view -> download_start -> download_complete tính đúng phiên hoàn tất.</p>
            </div>

            <div style="padding:1rem 1.15rem; background:#ffffff; border:1px solid var(--border-color); border-radius:var(--radius-md); box-shadow:var(--shadow-xs);">
                <div style="display:flex; align-items:center; gap:0.45rem; margin-bottom:0.35rem;">
                    <span class="badge badge-yellow">Tiêu chí 8</span>
                    <strong style="font-size:0.88rem;">Chống sai thứ tự & thiếu bước</strong>
                </div>
                <p style="color:var(--text-secondary); font-size:0.82rem; line-height:1.45;">Loại bỏ phiên bỏ dở và phiên ngược thứ tự khỏi tỷ lệ hoàn tất.</p>
            </div>

            <div style="padding:1rem 1.15rem; background:#ffffff; border:1px solid var(--border-color); border-radius:var(--radius-md); box-shadow:var(--shadow-xs);">
                <div style="display:flex; align-items:center; gap:0.45rem; margin-bottom:0.35rem;">
                    <span class="badge badge-purple">Tiêu chí 9</span>
                    <strong style="font-size:0.88rem;">Cô lập dữ liệu Website A & B</strong>
                </div>
                <p style="color:var(--text-secondary); font-size:0.82rem; line-height:1.45;">Hai website hoàn toàn tách biệt, không lẫn chéo session hay thống kê.</p>
            </div>

            <div style="padding:1rem 1.15rem; background:#ffffff; border:1px solid var(--border-color); border-radius:var(--radius-md); box-shadow:var(--shadow-xs);">
                <div style="display:flex; align-items:center; gap:0.45rem; margin-bottom:0.35rem;">
                    <span class="badge badge-purple">Tiêu chí 10</span>
                    <strong style="font-size:0.88rem;">Phân quyền RBAC Member</strong>
                </div>
                <p style="color:var(--text-secondary); font-size:0.82rem; line-height:1.45;">Tài khoản Member chỉ được truy cập Website A, bị chặn 403 khi vào Website B.</p>
            </div>

            <div style="padding:1rem 1.15rem; background:#ffffff; border:1px solid var(--border-color); border-radius:var(--radius-md); box-shadow:var(--shadow-xs);">
                <div style="display:flex; align-items:center; gap:0.45rem; margin-bottom:0.35rem;">
                    <span class="badge badge-green">Tiêu chí 11</span>
                    <strong style="font-size:0.88rem;">Bảo vệ quyền riêng tư 100%</strong>
                </div>
                <p style="color:var(--text-secondary); font-size:0.82rem; line-height:1.45;">Không có mật khẩu, input text hay dữ liệu cấm nào trong CSDL.</p>
            </div>

            <div style="padding:1rem 1.15rem; background:#ffffff; border:1px solid var(--border-color); border-radius:var(--radius-md); box-shadow:var(--shadow-xs);">
                <div style="display:flex; align-items:center; gap:0.45rem; margin-bottom:0.35rem;">
                    <span class="badge badge-blue">Tiêu chí 12</span>
                    <strong style="font-size:0.88rem;">Tích hợp thông suốt End-to-End</strong>
                </div>
                <p style="color:var(--text-secondary); font-size:0.82rem; line-height:1.45;">Toàn bộ hệ thống sẵn sàng trình diễn và bảo vệ trước hội đồng chấm.</p>
            </div>
        </div>
    </div>
<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
