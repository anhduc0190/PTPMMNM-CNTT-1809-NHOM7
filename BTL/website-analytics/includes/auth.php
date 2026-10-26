<?php
// ====================================================================
// INCLUDES: XAC THUC NGUOI DUNG (AUTHENTICATION)
// ====================================================================

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/helpers.php';

/**
 * Kiem tra trang thai dang nhap
 */
function is_authenticated(): bool {
    return !empty($_SESSION['user_id']) && !empty($_SESSION['user_email']);
}

/**
 * Tra ve thong tin user hien tai tu session
 */
function get_auth_user(): ?array {
    if (!is_authenticated()) {
        return null;
    }
    return [
        'user_id'   => (int)$_SESSION['user_id'],
        'email'     => $_SESSION['user_email'],
        'full_name' => $_SESSION['user_name'] ?? 'User',
        'role'      => $_SESSION['user_role'] ?? 'member'
    ];
}

/**
 * Kiem tra co phai Admin hay khong
 */
function is_admin(): bool {
    $user = get_auth_user();
    return $user !== null && $user['role'] === 'admin';
}

/**
 * Bat buoc dang nhap. Neu chua dang nhap se chuyen huong hoac tra 401.
 */
function require_login(): void {
    if (!is_authenticated()) {
        if (is_api_request()) {
            json_response([
                'success' => false,
                'error'   => 'UNAUTHORIZED',
                'message' => 'Yêu cầu đăng nhập để truy cập tài nguyên này.'
            ], 401);
        } else {
            $base = get_base_url();
            header("Location: {$base}/login.php");
            exit;
        }
    }
}

/**
 * Bat buoc phai co quyen Admin
 */
function require_admin(): void {
    require_login();
    if (!is_admin()) {
        if (is_api_request()) {
            json_response([
                'success' => false,
                'error'   => 'FORBIDDEN',
                'message' => 'Bạn không có quyền thực hiện thao tác quản trị này.'
            ], 403);
        } else {
            http_response_code(403);
            echo "<h1>403 Forbidden</h1><p>Bạn không có quyền truy cập trang quản trị này.</p>";
            exit;
        }
    }
}

/**
 * Kiem tra co phai request dang JSON API hay khong
 */
function is_api_request(): bool {
    $uri = $_SERVER['REQUEST_URI'] ?? '';
    return strpos($uri, '/api/') !== false || 
           (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false);
}

/**
 * Xu ly dang nhap bang Email va Mat khau
 */
function login_user(string $email, string $password): array {
    $email = trim(filter_var($email, FILTER_SANITIZE_EMAIL));
    if (empty($email) || empty($password)) {
        return ['success' => false, 'message' => 'Vui lòng nhập đầy đủ Email và Mật khẩu.'];
    }

    $pdo = get_db_connection();
    $stmt = $pdo->prepare('SELECT `user_id`, `full_name`, `email`, `password_hash`, `role`, `status` FROM `users` WHERE `email` = ? LIMIT 1');
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if (!$user) {
        return ['success' => false, 'message' => 'Email hoặc mật khẩu không chính xác.'];
    }

    if ($user['status'] !== 'active') {
        return ['success' => false, 'message' => 'Tài khoản của bạn đã bị khóa hoặc chưa kích hoạt.'];
    }

    if (!password_verify($password, $user['password_hash'])) {
        return ['success' => false, 'message' => 'Email hoặc mật khẩu không chính xác.'];
    }

    // Luu thong tin vao session an toan
    $_SESSION['user_id']   = (int)$user['user_id'];
    $_SESSION['user_email'] = $user['email'];
    $_SESSION['user_name'] = $user['full_name'];
    $_SESSION['user_role'] = $user['role'];

    // Tai tao session ID de chong session fixation
    session_regenerate_id(true);

    return ['success' => true, 'user' => $user];
}

/**
 * Dang xuat va xoa bo session
 */
function logout_user(): void {
    $_SESSION = [];
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params["path"],
            $params["domain"],
            $params["secure"],
            $params["httponly"]
        );
    }
    session_destroy();
}
