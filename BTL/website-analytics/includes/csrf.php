<?php
// ====================================================================
// INCLUDES: CSRF PROTECTION
// ====================================================================

require_once __DIR__ . '/../config/app.php';

/**
 * Sinh ma CSRF Token va luu vao session
 */
function get_csrf_token(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Xuat the input an CSRF cho form HTML
 */
function csrf_field(): string {
    $token = get_csrf_token();
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($token, ENT_QUOTES, 'UTF-8') . '">';
}

/**
 * Kiem tra tinh hop le cua token CSRF gui len tu POST hoac Header
 */
function verify_csrf_token(?string $token = null): bool {
    if ($token === null) {
        $token = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;
    }
    if (empty($token) || empty($_SESSION['csrf_token'])) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Chan request neu ma CSRF khong hop le
 */
function require_csrf_token(): void {
    if (!verify_csrf_token()) {
        if (is_api_request()) {
            json_response([
                'success' => false,
                'error'   => 'CSRF_TOKEN_INVALID',
                'message' => 'Mã xác thực CSRF không hợp lệ hoặc phiên làm việc đã hết hạn.'
            ], 403);
        } else {
            http_response_code(403);
            echo "<h1>403 Forbidden</h1><p>Mã bảo mật CSRF không hợp lệ. Vui lòng tải lại trang.</p>";
            exit;
        }
    }
}
