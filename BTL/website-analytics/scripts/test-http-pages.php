<?php
// ====================================================================
// SCRIPT KIỂM THỬ HTTP CÁC TRANG DASHBOARD SAU KHI REDESIGN
// ====================================================================

$baseUrl = 'http://localhost/website-analytics';
$cookieFile = __DIR__ . '/test-cookie.txt';
if (file_exists($cookieFile)) unlink($cookieFile);

function http_req($url, $method = 'GET', $data = [], $cookieFile = null) {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    if ($cookieFile) {
        curl_setopt($ch, CURLOPT_COOKIEJAR, $cookieFile);
        curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieFile);
    }
    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
    }
    $body = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return ['code' => $code, 'body' => $body];
}

echo "=== 1. KIỂM THỬ ĐĂNG NHẬP ADMIN ===\n";
$loginPage = http_req("{$baseUrl}/login.php", 'GET', [], $cookieFile);
preg_match('/name="csrf_token"\s+value="([^"]+)"/', $loginPage['body'], $m);
$csrfToken = $m[1] ?? '';

$loginResp = http_req("{$baseUrl}/login.php", 'POST', [
    'csrf_token' => $csrfToken,
    'email' => 'admin@analytics.local',
    'password' => 'Admin@123456'
], $cookieFile);

echo "Login status: {$loginResp['code']}\n";

$pages = [
    'dashboard.php',
    'reports.php',
    'funnels.php',
    'event-logs.php',
    'websites.php',
    'event-definitions.php',
    'test-center.php',
    'users.php',
    'settings.php'
];

echo "\n=== 2. KIỂM THỬ TRANG ADMIN GIAO DIỆN MỚI ===\n";
$allOk = true;
foreach ($pages as $page) {
    $r = http_req("{$baseUrl}/{$page}", 'GET', [], $cookieFile);
    $len = strlen($r['body']);
    $hasSvg = strpos($r['body'], '<svg') !== false;
    $hasEmoji = (bool)preg_match('/[\x{1F300}-\x{1FAD6}\x{1F900}-\x{1F9FF}\x{2600}-\x{26FF}\x{2700}-\x{27BF}]/u', $r['body']);
    
    echo sprintf("[CODE %d] %-22s: %5d bytes | SVG: %s | Has Emoji: %s\n", 
        $r['code'], $page, $len, $hasSvg ? 'YES' : 'NO', $hasEmoji ? 'YES (FAIL)' : 'NO (CLEAN)');
    
    if ($r['code'] !== 200 || !$hasSvg || $hasEmoji) {
        $allOk = false;
    }
}

// 3. Test Member Account
echo "\n=== 3. KIỂM THỬ TÀI KHOẢN MEMBER (RBAC) ===\n";
if (file_exists($cookieFile)) unlink($cookieFile);
$loginPage2 = http_req("{$baseUrl}/login.php", 'GET', [], $cookieFile);
preg_match('/name="csrf_token"\s+value="([^"]+)"/', $loginPage2['body'], $m2);
$csrfToken2 = $m2[1] ?? '';

http_req("{$baseUrl}/login.php", 'POST', [
    'csrf_token' => $csrfToken2,
    'email' => 'member@analytics.local',
    'password' => 'Member@123456'
], $cookieFile);

$dashMember = http_req("{$baseUrl}/dashboard.php", 'GET', [], $cookieFile);
echo "Member Dashboard: CODE {$dashMember['code']}\n";

$usersMember = http_req("{$baseUrl}/users.php", 'GET', [], $cookieFile);
echo "Member Access users.php (Must be 403): CODE {$usersMember['code']}\n";

$settingsMember = http_req("{$baseUrl}/settings.php", 'GET', [], $cookieFile);
echo "Member Access settings.php (Must be 403): CODE {$settingsMember['code']}\n";

if (file_exists($cookieFile)) unlink($cookieFile);

echo "\n=== KẾT QUẢ TỔNG QUAN: " . ($allOk ? "TẤT CẢ TRANG HOẠT ĐỘNG HOÀN HẢO 100%!" : "CÓ LỖI CẦN XỬ LÝ") . " ===\n";
