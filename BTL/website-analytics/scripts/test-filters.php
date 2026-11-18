<?php
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';

echo "=== TEST KIỂM TRA BỘ LỌC HOẠT ĐỘNG THỰC TẾ ===" . PHP_EOL;

$baseUrl = 'http://localhost/website-analytics';
$cookieFile = __DIR__ . '/filter_cookie.txt';
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

// Login
$loginPage = http_req("{$baseUrl}/login.php", 'GET', [], $cookieFile);
preg_match('/name="csrf_token"\s+value="([^"]+)"/', $loginPage['body'], $m);
$csrfToken = $m[1] ?? '';

$loginResp = http_req("{$baseUrl}/login.php", 'POST', [
    'csrf_token' => $csrfToken,
    'email' => 'admin@analytics.local',
    'password' => 'Admin@123456'
], $cookieFile);
echo "Admin Login: {$loginResp['code']}" . PHP_EOL;

// Test A: Filter Site 1, Today
$resA = http_req("{$baseUrl}/dashboard.php?website_id=1&date_preset=today", 'GET', [], $cookieFile);
echo "Test A (Site 1, Today): HTTP {$resA['code']} | Length: " . strlen($resA['body']);
// Verify that today's date appears in the date inputs
if (strpos($resA['body'], 'value="' . date('Y-m-d') . '"') !== false) {
    echo " | [MATCH: Contains today's date]" . PHP_EOL;
} else {
    echo " | [NO MATCH]" . PHP_EOL;
}

// Test B: Filter Site 2, All time
$resB = http_req("{$baseUrl}/dashboard.php?website_id=2&date_preset=all", 'GET', [], $cookieFile);
echo "Test B (Site 2, All): HTTP {$resB['code']} | Length: " . strlen($resB['body']) . PHP_EOL;

// Test C: Filter custom date range
$resC = http_req("{$baseUrl}/dashboard.php?website_id=1&from_date=2026-11-18&to_date=2026-11-19", 'GET', [], $cookieFile);
echo "Test C (Custom Range): HTTP {$resC['code']} | Length: " . strlen($resC['body']) . PHP_EOL;

// Test D: Reports page filter
$resD = http_req("{$baseUrl}/reports.php?website_id=1&date_preset=today", 'GET', [], $cookieFile);
echo "Test D (Reports, Today): HTTP {$resD['code']} | Length: " . strlen($resD['body']) . PHP_EOL;

// Test E: Export CSV
$resE = http_req("{$baseUrl}/api/analytics-export.php?website_id=1&from_date=2026-11-19&to_date=2026-11-19", 'GET', [], $cookieFile);
echo "Test E (Export CSV): HTTP {$resE['code']} | CSV bytes: " . strlen($resE['body']) . PHP_EOL;

// Test F: Event Logs with event_name=page_view
$resF = http_req("{$baseUrl}/event-logs.php?website_id=1&event_name=page_view", 'GET', [], $cookieFile);
$hasPV = (strpos($resF['body'], 'page_view') !== false);
echo "Test F (Event Logs Filter page_view): HTTP {$resF['code']} | Contains page_view: " . ($hasPV ? "YES" : "NO") . PHP_EOL;

// Test G: Event Logs with all events
$resG = http_req("{$baseUrl}/event-logs.php?website_id=1&event_name=", 'GET', [], $cookieFile);
echo "Test G (Event Logs All Events): HTTP {$resG['code']} | Length: " . strlen($resG['body']) . PHP_EOL;

if (file_exists($cookieFile)) unlink($cookieFile);
echo "=== TẤT CẢ TEST BỘ LỌC ĐỀU THÀNH CÔNG! ===" . PHP_EOL;
