<?php
/** Owned REL-004 focused release runner. Builder: Shashi. */
require_once '/Users/shashi_protecto/Local Sites/testing/app/public/wp-content/plugins/interior-design-lead-manager/tests/bootstrap.php';
$tests = array();

function idlm_test(string $name, callable $callback): void {
    global $tests;
    $tests[$name] = $callback;
}

function assert_true($condition, string $message = 'Expected condition to be true.'): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function assert_false($condition, string $message = 'Expected condition to be false.'): void {
    if ($condition) {
        throw new RuntimeException($message);
    }
}

function assert_same($expected, $actual, string $message = ''): void {
    if ($expected !== $actual) {
        throw new RuntimeException(($message ? $message . ' ' : '') . 'Expected ' . var_export($expected, true) . ', got ' . var_export($actual, true) . '.');
    }
}

function assert_contains(string $needle, string $haystack, string $message = ''): void {
    if (false === strpos($haystack, $needle)) {
        throw new RuntimeException(($message ? $message . ' ' : '') . 'Did not find ' . var_export($needle, true) . '.');
    }
}

function assert_count_value(int $expected, array $actual, string $message = ''): void {
    assert_same($expected, count($actual), $message);
}


require_once '/Users/shashi_protecto/Local Sites/testing/app/public/wp-content/plugins/interior-design-lead-manager/tests/release-contracts.php';
$failed=0;foreach($tests as $name=>$test){try{$test();echo 'PASS '.$name.PHP_EOL;}catch(Throwable $e){$failed++;echo 'FAIL '.$name.': '.$e->getMessage().PHP_EOL;}}echo (count($tests)-$failed).' passed, '.$failed.' failed'.PHP_EOL;exit($failed?1:0);
