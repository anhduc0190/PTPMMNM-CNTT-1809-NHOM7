<?php
// ====================================================================
// UNIT TEST: SANITIZER & PRIVACY SAFEGUARD
// ====================================================================

require_once __DIR__ . '/../../includes/helpers.php';

function test_sanitizer_removes_forbidden_fields() {
    $raw = [
        'password'      => 'SuperSecret123',
        'user_pass'     => '123456',
        'credit_card'   => '4111111111111111',
        'cvv_number'    => '123',
        'input_message' => 'Lưu tin nhắn người dùng',
        'button_text'   => 'Gửi liên hệ',
        'element_id'    => 'btn-send'
    ];

    $clean = sanitize_properties($raw);

    assert(!isset($clean['password']), 'password must be removed');
    assert(!isset($clean['user_pass']), 'user_pass must be removed');
    assert(!isset($clean['credit_card']), 'credit_card must be removed');
    assert(!isset($clean['cvv_number']), 'cvv_number must be removed');
    assert(!isset($clean['input_message']), 'input_message must be removed');
    assert(isset($clean['button_text']) && $clean['button_text'] === 'Gửi liên hệ', 'safe field button_text must be kept');
    assert(isset($clean['element_id']) && $clean['element_id'] === 'btn-send', 'safe field element_id must be kept');

    echo "✓ test_sanitizer_removes_forbidden_fields: PASSED\n";
}

test_sanitizer_removes_forbidden_fields();
