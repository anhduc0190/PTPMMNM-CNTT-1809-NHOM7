<?php
// ====================================================================
// TRANG DANG NHAP (login.php)
// Giao dien hien dai, ho tro nut dien nhanh Admin & Member phuc vu bao ve
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
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Đăng Nhập — WebAnalytics Platform</title>
    <link rel="stylesheet" href="<?= $base ?>/assets/css/dashboard.css">
    <style>
        body {
            background: linear-gradient(135deg, #0f172a 0%, #1e293b 50%, #1e3a8a 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            padding: 1.5rem;
        }
        .login-card {
            background: #ffffff;
            border-radius: 14px;
            box-shadow: 0 20px 25px -5px rgba(0,0,0,0.3), 0 8px 10px -6px rgba(0,0,0,0.2);
            width: 100%;
            max-width: 440px;
            padding: 2.5rem;
        }
        .login-brand {
            font-size: 1.6rem;
            font-weight: 800;
            color: #0f172a;
            text-align: center;
            margin-bottom: 0.3rem;
        }
        .login-brand span { color: #2563eb; }
        .login-desc {
            text-align: center;
            color: #64748b;
            font-size: 0.88rem;
            margin-bottom: 2rem;
        }
        .form-group { margin-bottom: 1.25rem; }
        .form-group label {
            display: block;
            font-size: 0.85rem;
            font-weight: 600;
            color: #334155;
            margin-bottom: 0.4rem;
        }
        .form-control {
            width: 100%;
            padding: 0.75rem 0.9rem;
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            font-size: 0.95rem;
            outline: none;
            transition: all 0.15s;
        }
        .form-control:focus {
            border-color: #2563eb;
            box-shadow: 0 0 0 3px rgba(37,99,235,0.15);
        }
        .alert-error {
            background-color: #fef2f2;
            border: 1px solid #fecaca;
            color: #b91c1c;
            padding: 0.75rem 1rem;
            border-radius: 8px;
            font-size: 0.85rem;
            margin-bottom: 1.25rem;
        }
        .quick-accounts {
            margin-top: 1.8rem;
            padding-top: 1.5rem;
            border-top: 1px dashed #e2e8f0;
            font-size: 0.82rem;
            color: #64748b;
        }
        .quick-btn {
            display: inline-block;
            background: #f1f5f9;
            border: 1px solid #cbd5e1;
            padding: 0.35rem 0.65rem;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 600;
            color: #1e293b;
            margin: 0.3rem 0.2rem 0 0;
            transition: all 0.15s;
        }
        .quick-btn:hover {
            background: #e2e8f0;
            color: #0f172a;
        }
    </style>
</head>
<body>
    <div class="login-card">
        <div class="login-brand">
            📊 <span>Web</span>Analytics
        </div>
        <div class="login-desc">
            Hệ thống Thống Kê Lượt Truy Cập & Thao Tác (Đề tài 17)
        </div>

        <?php if ($error): ?>
            <div class="alert-error">
                ⚠️ <?= e($error) ?>
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

            <button type="submit" class="btn btn-primary" style="width: 100%; padding: 0.8rem; font-size: 0.95rem; justify-content: center; margin-top: 0.5rem;">
                🔐 Đăng Nhập Dashboard
            </button>
        </form>

        <div class="quick-accounts">
            <b>Tài khoản thử nghiệm sẵn có:</b><br>
            <button type="button" class="quick-btn" onclick="fillAccount('admin@analytics.local', 'Admin@123456')">
                🛡️ Tài khoản Admin (Toàn quyền)
            </button>
            <button type="button" class="quick-btn" onclick="fillAccount('member@analytics.local', 'Member@123456')">
                👤 Tài khoản Member (Chỉ Site A)
            </button>
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
