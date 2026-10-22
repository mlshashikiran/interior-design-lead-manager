<?php
/**
 * Static Plugin Check against an extracted ZIP in a disposable WordPress install.
 * Builder: Shashi
 */
declare(strict_types=1);
require_once dirname(__DIR__) . '/integration/bootstrap.php';
require_once __DIR__ . '/class-package-check-result.php';

$options = getopt('', array('artifact:', 'checker-zip:', 'wp-cli:', 'output-dir:'));
foreach (array('artifact', 'checker-zip', 'wp-cli', 'output-dir') as $required) {
    if (empty($options[$required])) { throw new RuntimeException('Missing --' . $required); }
}
foreach (array('artifact', 'checker-zip', 'wp-cli') as $input) {
    if (!is_file((string) $options[$input])) { throw new RuntimeException('Missing file for --' . $input); }
}
$config = idlm_integration_config();
$core = realpath($config['wp_root']);
$temporary_roots = array_filter(array(realpath(sys_get_temp_dir()), realpath('/tmp')));
$temporary_core = false;
foreach ($temporary_roots as $temporary_root) {
    if (false !== $core && strpos($core . '/', $temporary_root . '/') === 0) { $temporary_core = true; }
}
// Never write a configuration beside a configured site or reuse an existing one.
if (!$temporary_core
    || is_file($core . '/wp-config.php') || is_file(dirname($core) . '/wp-config.php')) {
    throw new RuntimeException('Use a fresh temporary WordPress core with no wp-config.php.');
}
$output = rtrim((string) $options['output-dir'], '/');
if (!is_dir($output) && !mkdir($output, 0700, true)) { throw new RuntimeException('Cannot create evidence directory.'); }
$database = new IDLM_Integration_Database($config);
$configuration_written = false;
define('WP_DISABLE_FATAL_ERROR_HANDLER', true);
try {
    idlm_integration_boot_wordpress($config, true);
    foreach (array('artifact' => 'interior-design-lead-manager', 'checker-zip' => 'plugin-check') as $option => $slug) {
        $zip = new ZipArchive();
        if (true !== $zip->open((string) $options[$option])) { throw new RuntimeException('Cannot open ' . $option); }
        for ($index = 0; $index < $zip->numFiles; $index++) {
            $name = $zip->getNameIndex($index);
            $system = $attributes = 0;
            $zip->getExternalAttributesIndex($index, $system, $attributes);
            if (!is_string($name) || strpos($name, $slug . '/') !== 0 || strpos($name, '\\') !== false
                || preg_match('~(^|/)\.\.?(/|$)~', $name) || (($attributes >> 16) & 0170000) === 0120000) {
                throw new RuntimeException('Unsafe ZIP member.');
            }
        }
        if (!$zip->extractTo($config['content_dir'] . '/plugins')) { throw new RuntimeException('Extraction failed.'); }
        $zip->close();
    }
    update_option('active_plugins', array('plugin-check/plugin.php'));
    $constants = array('DB_NAME' => $config['db_name'], 'DB_USER' => $config['db_user'],
        'DB_PASSWORD' => $config['db_password'], 'DB_HOST' => $config['db_host'], 'DB_CHARSET' => 'utf8mb4',
        'DB_COLLATE' => '', 'WP_CONTENT_DIR' => $config['content_dir'], 'WP_HOME' => 'http://idlm-integration.test',
        'WP_SITEURL' => 'http://idlm-integration.test', 'DISABLE_WP_CRON' => true, 'WP_DEBUG' => true,
        'WP_DEBUG_DISPLAY' => false, 'WP_ENVIRONMENT_TYPE' => 'development');
    $configuration = "<?php\n";
    foreach ($constants as $name => $value) { $configuration .= 'define(' . var_export($name, true) . ', ' . var_export($value, true) . ");\n"; }
    $configuration .= '$table_prefix = \'wp_\';' . "\n" . 'if (!defined(\'ABSPATH\')) { define(\'ABSPATH\', __DIR__ . \'/\'); }' . "\nrequire ABSPATH . 'wp-settings.php';\n";
    $handle = fopen($core . '/wp-config.php', 'x');
    if (false === $handle) { throw new RuntimeException('Cannot exclusively create fixture configuration.'); }
    $configuration_written = true;
    chmod($core . '/wp-config.php', 0600);
    if (strlen($configuration) !== fwrite($handle, $configuration)) { throw new RuntimeException('Cannot write fixture configuration.'); }
    fclose($handle);
    $command = array(PHP_BINARY, (string) $options['wp-cli'], '--path=' . $core,
        '--skip-plugins=interior-design-lead-manager', '--skip-themes', 'plugin', 'check',
        'interior-design-lead-manager', '--format=strict-json', '--fields=file,line,column,type,code,message,docs');
    $process = proc_open($command, array(0 => array('pipe', 'r'), 1 => array('file', $output . '/plugin-check.stdout.txt', 'w'),
        2 => array('file', $output . '/plugin-check.stderr.txt', 'w')), $pipes);
    if (!is_resource($process)) { throw new RuntimeException('Cannot launch Plugin Check.'); }
    fclose($pipes[0]);
    $status = proc_close($process);
    $decoded = IDLM_Package_Check_Result::decode((string) file_get_contents($output . '/plugin-check.stdout.txt'), $status,
        (string) file_get_contents($output . '/plugin-check.stderr.txt'));
    $diagnostics = $decoded['diagnostics'];
    file_put_contents($output . '/plugin-check.json', json_encode($diagnostics, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
    // A diagnostic failure is retained as evidence, never converted to a passing check.
    $types = array_count_values(array_column($diagnostics, 'type'));
    $codes = array_count_values(array_column($diagnostics, 'code'));
    arsort($codes);
    $checker_header = file_get_contents($config['content_dir'] . '/plugins/plugin-check/plugin.php');
    preg_match('/Version:\s*(\S+)/', (string) $checker_header, $version);
    $report = array('builder' => 'Shashi', 'mode' => 'static_default_checks_no_exclusions',
        'artifact_sha256' => hash_file('sha256', (string) $options['artifact']),
        'checker_zip_sha256' => hash_file('sha256', (string) $options['checker-zip']),
        'checker_version' => $version[1] ?? '', 'wordpress' => $GLOBALS['wp_version'], 'php' => PHP_VERSION,
        'command' => $command, 'exit_code' => $status, 'output_format' => $decoded['output_format'],
        'runner_exit_code' => $status !== 0 ? $status : (empty($diagnostics) ? 0 : 1),
        'diagnostics_pass' => empty($diagnostics),
        'diagnostic_count' => count($diagnostics), 'types' => $types,
        'codes' => $codes, 'production_release_approved' => false);
    file_put_contents($output . '/plugin-check-summary.json', json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
    if (is_file($config['content_dir'] . '/outbound-attempts.log')) {
        copy($config['content_dir'] . '/outbound-attempts.log', $output . '/outbound-attempts.log');
    }
    echo json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
} catch (Throwable $error) {
    fwrite(STDERR, 'Package check failed: ' . $error->getMessage() . "\n");
    $status = 2;
} finally {
    if ($configuration_written) { unlink($core . '/wp-config.php'); }
    $database->drop();
    idlm_integration_remove_directory($config['content_dir']);
}
// Plugin Check's strict-json CLI can exit zero with findings; preserve a failing gate.
exit($status !== 0 ? $status : (empty($diagnostics) ? 0 : 1));
