<?php
// ====================================================================
// TRANG ĐĂNG NHẬP (login.php)
// Thiết kế Enterprise SaaS: Đăng nhập an toàn, nút điền nhanh phục vụ bảo vệ
// ====================================================================

require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/includes/helpers.php';

$base = get_base_url();

if (is_authenticated()) {
    header("Location: {$base}/dashboard.php");
    exit;
}

$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf_token();
    $email    = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';

    $res = login_user($email, $password);
    if ($res['success']) {
        header("Location: {$base}/dashboard.php");
        exit;
    } else {
        $error = $res['message'];
    }
}

if (!headers_sent()) {
    header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
    header("Cache-Control: post-check=0, pre-check=0", false);
    header("Pragma: no-cache");
    header("Expires: 0");
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Đăng Nhập — <?= APP_NAME ?></title>
    <link rel="stylesheet" href="<?= $base ?>/assets/css/dashboard.css?v=<?= file_exists(__DIR__ . '/assets/css/dashboard.css') ? filemtime(__DIR__ . '/assets/css/dashboard.css') : time() ?>_<?= time() ?>">
    <style>
<?php
$cssLoginFile = __DIR__ . '/assets/css/dashboard.css';
if (file_exists($cssLoginFile)) {
    readfile($cssLoginFile);
}
?>
        body {
            background-color: #0b1727;
            background-image: radial-gradient(rgba(37, 99, 235, 0.12) 1px, transparent 0);
            background-size: 24px 24px;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            padding: 1.5rem;
        }
        .login-wrapper {
            width: 100%;
            max-width: 420px;
        }
        .login-card {
            background: #ffffff;
            border-radius: var(--radius-lg);
            box-shadow: 0 20px 25px -5px rgba(0,0,0,0.3), 0 8px 10px -6px rgba(0,0,0,0.2);
            border: 1px solid #142236;
            padding: 2.25rem;
        }
        .login-brand {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.65rem;
            margin-bottom: 0.35rem;
        }
        .login-brand-mark {
            width: 36px;
            height: 36px;
            background: #1a56db;
            border-radius: 9px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #ffffff;
            box-shadow: 0 4px 10px rgba(26, 86, 219, 0.35);
        }
        .login-brand-title {
            font-size: 1.45rem;
            font-weight: 800;
            color: #0f172a;
            letter-spacing: -0.01em;
        }
        .login-brand-title span { color: #2563eb; }
        .login-desc {
            text-align: center;
            color: #64748b;
            font-size: 0.85rem;
            margin-bottom: 1.75rem;
            line-height: 1.4;
        }
        .form-group { margin-bottom: 1.15rem; }
        .form-group label {
            display: block;
            font-size: 0.82rem;
            font-weight: 600;
            color: #334155;
            margin-bottom: 0.35rem;
        }
        .form-control {
            width: 100%;
            height: 38px;
            padding: 0 0.85rem;
            border: 1px solid #cbd5e1;
            border-radius: var(--radius-sm);
            font-size: 0.9rem;
            outline: none;
            transition: all 0.15s;
            font-family: inherit;
        }
        .form-control:focus {
            border-color: #2563eb;
            box-shadow: 0 0 0 3px rgba(37,99,235,0.15);
        }
        .alert-error {
            background-color: var(--accent-red-bg);
            border: 1px solid var(--accent-red-border);
            color: var(--accent-red-text);
            padding: 0.75rem 0.95rem;
            border-radius: var(--radius-sm);
            font-size: 0.84rem;
            font-weight: 500;
            margin-bottom: 1.25rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        .quick-accounts {
            margin-top: 1.75rem;
            padding-top: 1.25rem;
            border-top: 1px dashed #e2e8f0;
            font-size: 0.8rem;
            color: #64748b;
        }
        .quick-accounts-label {
            font-weight: 600;
            color: #475569;
            margin-bottom: 0.5rem;
            display: flex;
            align-items: center;
            gap: 0.35rem;
        }
        .quick-buttons {
            display: flex;
            flex-direction: column;
            gap: 0.45rem;
        }
        .quick-btn {
            display: flex;
            align-items: center;
            gap: 0.45rem;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            padding: 0.45rem 0.75rem;
            border-radius: var(--radius-sm);
            cursor: pointer;
            font-size: 0.8rem;
            font-weight: 500;
            color: #334155;
            transition: all 0.15s;
            text-align: left;
            font-family: inherit;
        }
        .quick-btn:hover {
            background: #f1f5f9;
            border-color: #cbd5e1;
            color: #0f172a;
        }
    </style>
</head>
<body>
    <div class="login-wrapper">
        <div class="login-card">
            <div class="login-brand">
                <div class="login-brand-mark">
                    <?= render_icon('chart-no-axes', '', 18, '#ffffff') ?>
                </div>
                <div class="login-brand-title">
                    Web Analytics
                </div>
            </div>
            <div class="login-desc">
                Hệ thống Thống Kê Lượt Truy Cập & Thao Tác (Đề tài 17)
            </div>

            <?php if ($error): ?>
                <div class="alert-error">
                    <?= render_icon('alert-triangle', '', 16) ?>
                    <span><?= e($error) ?></span>
                </div>
            <?php endif; ?>

            <form method="POST" action="login.php">
                <?= csrf_field() ?>
                <div class="form-group">
                    <label for="email">Địa Chỉ Email</label>
                    <input type="email" id="email" name="email" class="form-control" placeholder="admin@analytics.local" required autofocus>
                </div>

                <div class="form-group">
                    <label for="password">Mật Khẩu</label>
                    <input type="password" id="password" name="password" class="form-control" placeholder="••••••••" required>
                </div>

                <button type="submit" class="btn btn-primary" style="width: 100%; height: 40px; font-size: 0.92rem; margin-top: 0.5rem;">
                    <?= render_icon('log-in', '', 16) ?>
                    Đăng Nhập Dashboard
                </button>
            </form>

            <div class="quick-accounts">
                <div class="quick-accounts-label">
                    <?= render_icon('users', '', 14) ?>
                    Tài khoản thử nghiệm sẵn có:
                </div>
                <div class="quick-buttons">
                    <button type="button" class="quick-btn" onclick="fillAccount('admin@analytics.local', 'Admin@123456')">
                        <?= render_icon('shield', '', 14, 'var(--primary)') ?>
                        <span>Tài khoản Admin (Toàn quyền quản trị)</span>
                    </button>
                    <button type="button" class="quick-btn" onclick="fillAccount('member@analytics.local', 'Member@123456')">
                        <?= render_icon('user', '', 14, 'var(--accent-green)') ?>
                        <span>Tài khoản Member (Chỉ xem Website A)</span>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script>
        function fillAccount(email, pass) {
            document.getElementById('email').value = email;
            document.getElementById('password').value = pass;
        }
    </script>
</body>
</html>
