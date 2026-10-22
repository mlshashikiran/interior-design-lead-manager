<?php
/** Focused runtime qualification entry point. Builder: Shashi. */
declare(strict_types=1);
require __DIR__ . '/bootstrap.php';
$failed=0; $passed=0;
function assert_true($value, string $message='Assertion failed'): void { if (!$value) { throw new RuntimeException($message); } }
function assert_same($expected,$actual,string $message=''): void { assert_true($expected===$actual,$message.' Expected '.var_export($expected,true).', got '.var_export($actual,true)); }
function assert_contains(string $needle,string $haystack): void { assert_true(strpos($haystack,$needle)!==false,'Missing '.$needle); }
function idlm_test(string $name, callable $test): void {
    global $failed,$passed; idlm_test_reset_environment();
    try { $test(); $passed++; echo "PASS $name\n"; } catch(Throwable $error) { $failed++; echo "FAIL $name: {$error->getMessage()}\n"; }
}
require __DIR__.'/runtime-support-contracts.php';
echo "$passed passed, $failed failed\n"; exit($failed ? 1 : 0);
