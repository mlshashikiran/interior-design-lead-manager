<?php
/**
 * Guarded authenticated load qualification runner for read-only CRM endpoints.
 *
 * Builder: Shashi
 */

declare(strict_types=1);

if ('cli' !== PHP_SAPI) {
    fwrite(STDERR, "Load qualification must run from the command line.\n");
    exit(1);
}

require_once __DIR__ . '/class-qualification-result.php';

$scenarios = require __DIR__ . '/qualification-scenarios.php';
$matrix = require __DIR__ . '/qualification-matrix.php';
$options = getopt('', array(
    'help', 'execute', 'confirm:', 'wp-root:', 'scenario:', 'user-id:', 'requests:',
    'concurrency:', 'warmup:', 'revision:', 'dataset:',
));

if (isset($options['help'])) {
    fwrite(STDOUT, implode("\n", array(
        'Usage:',
        '  php tests/performance/run-load-qualification.php',
        '  php tests/performance/run-load-qualification.php --execute --confirm=run-idlm-load-qualification --user-id=1 --scenario=all --requests=20 --concurrency=2 --warmup=2 --dataset=small-baseline-01 --revision=build-id',
        '',
        'The default mode prints the Phase 8 plan without booting WordPress or making requests.',
        'Execution is read-only, refuses production, uses a generated WordPress login cookie and nonce,',
        'requires PHP cURL, and emits aggregate results without URLs, identities, cookies, nonces, or bodies.',
        'Use --wp-root=/path/to/wordpress or IDLM_PERF_WP_ROOT to select WordPress.',
        '',
    )));
    exit(0);
}

$execute = isset($options['execute']);
$selected_id = isset($options['scenario']) ? (string) $options['scenario'] : 'all';
$requests = isset($options['requests']) ? (int) $options['requests'] : 20;
$concurrency = isset($options['concurrency']) ? (int) $options['concurrency'] : 2;
$warmup = isset($options['warmup']) ? (int) $options['warmup'] : 2;
$revision = idlm_qualification_safe_label(isset($options['revision']) ? (string) $options['revision'] : '');
$dataset = idlm_qualification_safe_label(isset($options['dataset']) ? (string) $options['dataset'] : '');

try {
    $selected_scenarios = idlm_qualification_select_scenarios($scenarios, $selected_id);
    idlm_qualification_validate_limits($requests, $concurrency, $warmup);
    idlm_qualification_assert_read_only_actions($selected_scenarios);
} catch (InvalidArgumentException $error) {
    fwrite(STDERR, $error->getMessage() . "\n");
    exit(1);
}

if (!$execute) {
    idlm_qualification_write_json(array(
        'schema' => 'idlm-load-qualification-plan/v1',
        'scenarios' => array_map('idlm_qualification_public_scenario', $selected_scenarios),
        'matrix' => $matrix,
        'configuration' => array(
            'requests_per_scenario' => $requests,
            'concurrency' => $concurrency,
            'warmup_requests_per_scenario' => $warmup,
            'dataset' => $dataset,
            'revision' => $revision,
        ),
        'performs_requests' => false,
        'writes_crm_data' => false,
        'qualification_status' => 'not_executed',
    ));
    exit(0);
}

if ('run-idlm-load-qualification' !== (string) ($options['confirm'] ?? '')) {
    fwrite(STDERR, "Refusing load execution without --confirm=run-idlm-load-qualification.\n");
    exit(1);
}
if (!function_exists('curl_multi_init')) {
    fwrite(STDERR, "PHP cURL with multi-request support is required.\n");
    exit(1);
}

$wp_root = isset($options['wp-root']) ? (string) $options['wp-root'] : (string) getenv('IDLM_PERF_WP_ROOT');
if ('' === trim($wp_root)) {
    $wp_root = dirname(__DIR__, 5);
}
$wp_root = rtrim($wp_root, '/\\') . DIRECTORY_SEPARATOR;
if (!is_file($wp_root . 'wp-load.php')) {
    fwrite(STDERR, "WordPress root not found. Pass --wp-root or set IDLM_PERF_WP_ROOT.\n");
    exit(1);
}

if (!defined('WP_USE_THEMES')) {
    define('WP_USE_THEMES', false);
}
require_once $wp_root . 'wp-load.php';

if (!defined('IDLM_VERSION')) {
    fwrite(STDERR, "Interior Design Lead Manager must be active in the selected WordPress environment.\n");
    exit(1);
}
$environment = function_exists('wp_get_environment_type') ? wp_get_environment_type() : 'production';
if (!in_array($environment, array('local', 'development', 'staging'), true)) {
    fwrite(STDERR, "Load qualification is forbidden in the {$environment} environment.\n");
    exit(1);
}

$user_id = isset($options['user-id']) ? absint($options['user-id']) : 0;
$user = $user_id > 0 ? get_user_by('id', $user_id) : false;
if (!$user instanceof WP_User) {
    fwrite(STDERR, "Pass an existing benchmark --user-id.\n");
    exit(1);
}
if (!user_can($user, 'manage_options') && !user_can($user, 'idlm_access_leads')) {
    fwrite(STDERR, "The benchmark user lacks CRM access.\n");
    exit(1);
}

wp_set_current_user($user_id);
$expiration = time() + HOUR_IN_SECONDS;
$cookie = wp_generate_auth_cookie($user_id, $expiration, 'logged_in');
if (!is_string($cookie) || '' === $cookie) {
    fwrite(STDERR, "Unable to generate an authenticated benchmark session.\n");
    exit(1);
}
$_COOKIE[LOGGED_IN_COOKIE] = $cookie;
$nonce = wp_create_nonce('idlm_admin_nonce');
$benchmark_session_token = wp_get_session_token();
if ('' === $benchmark_session_token) {
    fwrite(STDERR, "Unable to resolve the generated benchmark session.\n");
    exit(1);
}
register_shutdown_function(static function() use ($user_id, $benchmark_session_token): void {
    if (class_exists('WP_Session_Tokens')) {
        WP_Session_Tokens::get_instance($user_id)->destroy($benchmark_session_token);
    }
    unset($_COOKIE[LOGGED_IN_COOKIE]);
});
$endpoint = admin_url('admin-ajax.php');
$endpoint_parts = wp_parse_url($endpoint);
if (!is_array($endpoint_parts) || !in_array($endpoint_parts['scheme'] ?? '', array('http', 'https'), true) || empty($endpoint_parts['host'])) {
    fwrite(STDERR, "The WordPress admin AJAX endpoint is not a valid HTTP(S) URL.\n");
    exit(1);
}

$role_scope = array_values(array_intersect(
    array('administrator', 'assignment_manager', 'sales_manager', 'sales-manager', 'idlm_pre_sales', 'sales_executive', 'sales-executive', 'agent'),
    array_map('sanitize_key', (array) $user->roles)
));
sort($role_scope, SORT_STRING);

$results = array();
$has_request_errors = false;
foreach ($selected_scenarios as $scenario) {
    if ($warmup > 0) {
        idlm_qualification_execute_requests($endpoint, $cookie, $nonce, $scenario, $warmup, 1);
    }
    $started = microtime(true);
    $samples = idlm_qualification_execute_requests($endpoint, $cookie, $nonce, $scenario, $requests, $concurrency);
    $wall_seconds = max(0.000001, microtime(true) - $started);
    $summary = IDLM_Performance_Qualification_Result::summarize($samples, $scenario['provisional_thresholds']);
    $summary['throughput_requests_per_second'] = round($requests / $wall_seconds, 3);
    if ($summary['error_count'] > 0) {
        $has_request_errors = true;
    }
    $results[] = array(
        'scenario' => $scenario['id'],
        'measurement_status' => 0 === $summary['error_count'] ? 'measured' : 'measured_with_errors',
        'summary' => $summary,
    );
}

idlm_qualification_write_json(array(
    'schema' => 'idlm-load-qualification-result/v1',
    'captured_at_utc' => gmdate('c'),
    'build' => array(
        'revision' => $revision,
        'plugin_version' => IDLM_VERSION,
        'dataset' => $dataset,
        'environment_type' => $environment,
    ),
    'actor_scope' => array('roles' => $role_scope, 'identity_included' => false),
    'configuration' => array(
        'requests_per_scenario' => $requests,
        'concurrency' => $concurrency,
        'warmup_requests_per_scenario' => $warmup,
    ),
    'results' => $results,
    'run_status' => $has_request_errors ? 'completed_with_errors' : 'complete',
    'qualification_status' => 'evidence_only_targets_not_signed',
    'privacy' => array(
        'aggregate_only' => true,
        'response_storage' => 'anonymous_temporary_files_deleted_after_each_batch',
        'excluded' => array('URLs', 'user identity', 'cookies', 'nonces', 'request payload values', 'response bodies', 'contacts', 'credentials'),
    ),
));
exit($has_request_errors ? 2 : 0);

/** @return string */
function idlm_qualification_safe_label(string $value): string {
    return preg_match('/^[a-zA-Z0-9._\/-]{1,80}$/', $value) ? $value : '';
}

/**
 * @param array<int,array<string,mixed>> $scenarios Scenario manifest.
 * @return array<int,array<string,mixed>>
 */
function idlm_qualification_select_scenarios(array $scenarios, string $selected_id): array {
    if ('all' === $selected_id) {
        return $scenarios;
    }
    foreach ($scenarios as $scenario) {
        if ($scenario['id'] === $selected_id) {
            return array($scenario);
        }
    }
    throw new InvalidArgumentException('Unknown qualification scenario.');
}

function idlm_qualification_validate_limits(int $requests, int $concurrency, int $warmup): void {
    if ($requests < 1 || $requests > 5000) {
        throw new InvalidArgumentException('Requests must be between 1 and 5000 per scenario.');
    }
    if ($concurrency < 1 || $concurrency > 50 || $concurrency > $requests) {
        throw new InvalidArgumentException('Concurrency must be between 1 and 50 and cannot exceed requests.');
    }
    if ($warmup < 0 || $warmup > 100) {
        throw new InvalidArgumentException('Warmup requests must be between 0 and 100 per scenario.');
    }
}

/** @param array<int,array<string,mixed>> $scenarios Selected scenario definitions. */
function idlm_qualification_assert_read_only_actions(array $scenarios): void {
    $allowed_actions = array(
        'idlm_get_dashboard_stats',
        'idlm_get_leads_table',
        'idlm_get_kanban_leads',
    );
    foreach ($scenarios as $scenario) {
        if (empty($scenario['read_only']) || !in_array($scenario['action'] ?? '', $allowed_actions, true)) {
            throw new InvalidArgumentException('Qualification execution is limited to the read-only action allowlist.');
        }
    }
}

/**
 * @param array<string,mixed> $scenario Internal scenario.
 * @return array<string,mixed>
 */
function idlm_qualification_public_scenario(array $scenario): array {
    unset($scenario['request']);
    return $scenario;
}

/**
 * Execute bounded cURL batches and retain only aggregate-safe request samples.
 *
 * @param array<string,mixed> $scenario Scenario definition.
 * @return array<int,array<string,mixed>>
 */
function idlm_qualification_execute_requests(string $endpoint, string $cookie, string $nonce, array $scenario, int $requests, int $concurrency): array {
    $samples = array();
    for ($offset = 0; $offset < $requests; $offset += $concurrency) {
        $batch_size = min($concurrency, $requests - $offset);
        $multi = curl_multi_init();
        $batch = array();
        for ($index = 0; $index < $batch_size; $index++) {
            $temporary = tmpfile();
            if (false === $temporary) {
                throw new RuntimeException('Unable to allocate protected temporary response storage.');
            }
            $handle = curl_init();
            $body = array_merge(
                array('action' => $scenario['action'], 'nonce' => $nonce),
                (array) $scenario['request']
            );
            curl_setopt_array($handle, array(
                CURLOPT_URL => $endpoint,
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => http_build_query($body, '', '&'),
                CURLOPT_FILE => $temporary,
                CURLOPT_FOLLOWLOCATION => false,
                CURLOPT_CONNECTTIMEOUT => 5,
                CURLOPT_TIMEOUT => 60,
                CURLOPT_NOSIGNAL => true,
                CURLOPT_COOKIE => LOGGED_IN_COOKIE . '=' . $cookie,
                CURLOPT_USERAGENT => 'IDLM-Load-Qualification/' . IDLM_VERSION,
                CURLOPT_HTTPHEADER => array('Accept: application/json', 'Content-Type: application/x-www-form-urlencoded'),
            ));
            if (defined('CURLOPT_PROTOCOLS') && defined('CURLPROTO_HTTP') && defined('CURLPROTO_HTTPS')) {
                curl_setopt($handle, CURLOPT_PROTOCOLS, CURLPROTO_HTTP | CURLPROTO_HTTPS);
            }
            curl_multi_add_handle($multi, $handle);
            $batch[] = array('handle' => $handle, 'temporary' => $temporary);
        }

        $running = null;
        do {
            $status = curl_multi_exec($multi, $running);
        } while (CURLM_CALL_MULTI_PERFORM === $status);
        while ($running && CURLM_OK === $status) {
            if (-1 === curl_multi_select($multi, 1.0)) {
                usleep(1000);
            }
            do {
                $status = curl_multi_exec($multi, $running);
            } while (CURLM_CALL_MULTI_PERFORM === $status);
        }

        foreach ($batch as $request) {
            $handle = $request['handle'];
            $temporary = $request['temporary'];
            rewind($temporary);
            $prefix = (string) fread($temporary, 8192);
            $curl_error = curl_errno($handle);
            $http_code = (int) curl_getinfo($handle, CURLINFO_HTTP_CODE);
            $duration_ms = (float) curl_getinfo($handle, CURLINFO_TOTAL_TIME) * 1000;
            $response_bytes = (int) round((float) curl_getinfo($handle, CURLINFO_SIZE_DOWNLOAD));
            $outcome = 'success';
            if (0 !== $curl_error || CURLM_OK !== $status) {
                $outcome = 'transport_error';
            } elseif ($http_code < 200 || $http_code >= 300) {
                $outcome = 'http_error';
            } elseif (preg_match('/^\s*\{\s*"success"\s*:\s*false\b/', $prefix)) {
                $outcome = 'application_error';
            } elseif (!preg_match('/^\s*\{\s*"success"\s*:\s*true\b/', $prefix)) {
                $outcome = 'invalid_response';
            }
            $samples[] = array(
                'duration_ms' => round($duration_ms, 3),
                'response_bytes' => $response_bytes,
                'outcome' => $outcome,
            );
            curl_multi_remove_handle($multi, $handle);
            curl_close($handle);
            fclose($temporary);
        }
        curl_multi_close($multi);
    }

    return $samples;
}

/** @param array<string,mixed> $payload */
function idlm_qualification_write_json(array $payload): void {
    $json = function_exists('wp_json_encode')
        ? wp_json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
        : json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    if (false === $json) {
        fwrite(STDERR, "Unable to encode qualification evidence.\n");
        exit(1);
    }
    fwrite(STDOUT, $json . "\n");
}
