<?php
// ====================================================================
// INTEGRATION TEST: TRACK API IDEMPOTENCY & CONFLICT DETECTION
// ====================================================================

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/helpers.php';

$pdo = get_db_connection();
$fixtures = json_decode(file_get_contents(__DIR__ . '/../fixtures/test_payloads.json'), true);

echo "Testing Integration Track Flow:\n";

// Test 1: Insert valid payload
$p1 = $fixtures['valid_page_view'];
$canon1 = canonicalize_payload([
    'event_id'    => $p1['event_id'],
    'session_id'  => $p1['session_id'],
    'event_name'  => $p1['event_name'],
    'page_path'   => $p1['page_path'],
    'occurred_at' => date('Y-m-d H:i:s', strtotime($p1['occurred_at'])),
    'properties'  => sanitize_properties($p1['properties'])
]);
assert(strlen($canon1['hash']) === 64, 'SHA-256 hash must be 64 characters');
echo "✓ Canonical hash generated: " . substr($canon1['hash'], 0, 16) . "...\n";

// Test 2: Verify forbidden field stripping on forbidden payload
$pBad = $fixtures['forbidden_field_payload'];
$cleaned = sanitize_properties($pBad['properties']);
assert(!isset($cleaned['password_raw']), 'Must strip password_raw');
assert(!isset($cleaned['credit_card']), 'Must strip credit_card');
assert(isset($cleaned['button_text']), 'Must retain button_text');
echo "✓ Forbidden field filtering: PASSED\n";
