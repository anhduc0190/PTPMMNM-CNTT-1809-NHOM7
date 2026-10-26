<?php
// ====================================================================
// UNIT TEST: CANONICALIZER & HASH GENERATOR
// ====================================================================

require_once __DIR__ . '/../../includes/helpers.php';

function test_canonicalization_key_order_independence() {
    $a = [
        'z_key' => 'last',
        'a_key' => 'first',
        'm_key' => ['b' => 2, 'a' => 1]
    ];
    $b = [
        'a_key' => 'first',
        'm_key' => ['a' => 1, 'b' => 2],
        'z_key' => 'last'
    ];

    $ca = canonicalize_payload($a);
    $cb = canonicalize_payload($b);

    assert($ca['hash'] === $cb['hash'], 'Hash must be identical regardless of key order');
    echo "✓ test_canonicalization_key_order_independence: PASSED\n";
}

function test_canonicalization_hash_changes_on_different_value() {
    $a = ['btn' => 'Submit'];
    $b = ['btn' => 'Cancel'];

    $ca = canonicalize_payload($a);
    $cb = canonicalize_payload($b);

    assert($ca['hash'] !== $cb['hash'], 'Hash must change when values differ');
    echo "✓ test_canonicalization_hash_changes_on_different_value: PASSED\n";
}

test_canonicalization_key_order_independence();
test_canonicalization_hash_changes_on_different_value();
