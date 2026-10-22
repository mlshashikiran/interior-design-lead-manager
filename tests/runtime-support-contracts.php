<?php
/** REL-005 support metadata and prerequisite behavior. Builder: Shashi. */
if (!defined('IDLM_TESTING')) { exit; }
function idlm_runtime_probe(string $mode, array $ini = array(), string $encrypted = ''): void {
    $command = array(PHP_BINARY);
    foreach ($ini as $setting) { $command[] = '-d'; $command[] = $setting; }
    $command[] = __DIR__ . '/runtime-prerequisite-probe.php'; $command[] = $mode;
    if ($encrypted !== '') { $command[] = $encrypted; }
    $process = proc_open($command, array(0=>array('pipe','r'),1=>array('pipe','w'),2=>array('pipe','w')), $pipes);
    assert_true(is_resource($process), 'Unable to start prerequisite probe'); fclose($pipes[0]);
    $output = stream_get_contents($pipes[1]); $errors = stream_get_contents($pipes[2]); fclose($pipes[1]); fclose($pipes[2]);
    assert_same(0, proc_close($process), $mode . ': ' . $errors . $output);
    assert_same('', $errors, $mode . ' emitted diagnostics');
    assert_same("PASS REL005 $mode\n", $output);
}
idlm_test('REL005 minimum metadata aligns and CI targets declared floor with resolved runtime evidence', static function(): void {
    $root = dirname(__DIR__); $header = file_get_contents($root . '/interior-design-lead-manager.php'); $readme = file_get_contents($root . '/readme.txt');
    foreach (array('Requires at least'=>'6.6','Requires PHP'=>'7.4') as $key=>$value) {
        assert_true((bool) preg_match('/'.preg_quote($key,'/').':\s*'.preg_quote($value,'/').'\s*$/m', $header));
        assert_contains($key . ': ' . $value, $readme);
    }
    $ci = file_get_contents($root . '/.github/workflows/tests.yml');
    assert_contains("php: ['7.4', '8.1', '8.4', '8.5']", $ci); assert_contains("wordpress: ['6.6', 'latest']", $ci);
    assert_contains('php tests/runtime-prerequisites.php', $ci); assert_contains('runtime-environment.json', $ci);
    foreach (array('OpenSSL','InnoDB','DOM','mbstring','iconv','GD','ZipArchive','temporary','WP-Cron') as $prerequisite) { assert_contains($prerequisite, $readme); }
});
idlm_test('REL005 missing PDF modules or renderer reject before vendor loading and binary output', static function(): void {
    foreach (array('dom','mbstring','iconv','gd','no-renderer') as $missing) { idlm_runtime_probe('pdf-' . $missing); }
});
idlm_test('REL005 missing ZIP or temporary storage returns checked XLSX failure', static function(): void {
    idlm_runtime_probe('xlsx-no-zip'); idlm_runtime_probe('xlsx-no-temp');
});
idlm_test('REL005 available ZIP produces a parsed workbook and removes temporary output', static function(): void { idlm_runtime_probe('xlsx-ok'); });
idlm_test('REL005 unavailable crypto preserves stored credentials and denies replacement or use', static function(): void {
    assert_true(IDLM_Secret_Store::set('idlm_meta_access_token', 'synthetic-runtime-token'));
    idlm_runtime_probe('crypto-disabled', array('disable_functions=openssl_encrypt,openssl_decrypt'), get_option('idlm_meta_access_token'));
});
