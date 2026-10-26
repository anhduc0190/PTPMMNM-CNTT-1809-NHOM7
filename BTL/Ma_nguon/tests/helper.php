<?php
declare(strict_types=1);
/**
 * helper.php — Test helper (phong cách nhất quán với các Lab 01-05)
 */
$tests = 0; $failed = 0;

function same(mixed $actual, mixed $expected, string $name): void {
    global $tests, $failed;
    $tests++;
    if ($actual === $expected) {
        echo "PASS $name\n";
    } else {
        $failed++;
        echo "FAIL $name expected=" . var_export($expected, true)
            . " actual=" . var_export($actual, true) . "\n";
    }
}

function assertTrue(bool $cond, string $name): void {
    same($cond, true, $name);
}

function assertContains(mixed $needle, array $haystack, string $name): void {
    same(in_array($needle, $haystack, true), true, $name);
}

function finish(): never {
    global $tests, $failed;
    echo "TESTS=$tests FAILED=$failed\n";
    exit($failed === 0 ? 0 : 1);
}
