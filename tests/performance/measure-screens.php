<?php
/**
 * Per-screen query, time and asset-byte measurement on a disposable fixture.
 *
 * Creates a new idlm_test_* database, installs WordPress with this plugin
 * active, seeds the deterministic small synthetic profile, then measures each
 * CRM screen and its first data request in a fresh PHP process with the full
 * WordPress request lifecycle. Synthetic data only; outbound HTTP/mail is
 * blocked; the database and content directory are removed on exit.
 *
 * Usage (same disposable IDLM_TEST_* environment as tests/integration/run.php):
 *   php tests/performance/measure-screens.php [--runs=3] > screens.json
 *
 * Builder: Shashi
 */

declare(strict_types=1);

if ('cli' !== PHP_SAPI) { exit(1); }

const IDLM_MEASURE_SCREENS = array(
    'workspace-dashboard' => array('type' => 'page', 'view' => 'dashboard'),
    'workspace-leads' => array('type' => 'page', 'view' => 'leads'),
    'workspace-pipeline' => array('type' => 'page', 'view' => 'pipeline'),
    'workspace-quotations' => array('type' => 'page', 'view' => 'quotations'),
    'workspace-settings' => array('type' => 'page', 'view' => 'settings'),
    'ajax-dashboard-stats' => array('type' => 'ajax', 'action' => 'idlm_get_dashboard_stats', 'post' => array('period' => 'this_year')),
    'ajax-leads-table' => array('type' => 'ajax', 'action' => 'idlm_get_leads_table', 'post' => array('idlm_design' => 'crm', 'page' => '1', 'date_range' => '', 'date_basis' => 'assigned_at', 'active_only' => '0', 'sort_by' => 'created_at', 'sort_order' => 'DESC', 'per_page' => '25')),
    'ajax-kanban-board' => array('type' => 'ajax', 'action' => 'idlm_get_kanban_leads', 'post' => array()),
);

/** Child: one request, measured from wp-settings.php to shutdown. */
if (isset($argv[1]) && '--child' === $argv[1]) {
    $state = json_decode((string) file_get_contents((string) getenv('IDLM_MEASURE_STATE')), true);
    $screen = IDLM_MEASURE_SCREENS[$argv[2]];
    $config = $state['config'];
    define('SAVEQUERIES', true);
    $_SERVER['HTTP_HOST'] = 'idlm-integration.test';
    $_SERVER['SERVER_NAME'] = 'idlm-integration.test';
    $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
    $_COOKIE = $state['cookies'];
    if ('page' === $screen['type']) {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['SCRIPT_NAME'] = '/index.php';
        $_GET = array('page_id' => (string) $state['page_id'], 'idlm_view' => $screen['view']);
        $_SERVER['REQUEST_URI'] = '/?' . http_build_query($_GET);
        $_SERVER['QUERY_STRING'] = http_build_query($_GET);
    } else {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['SCRIPT_NAME'] = '/wp-admin/admin-ajax.php';
        $_SERVER['REQUEST_URI'] = '/wp-admin/admin-ajax.php';
        define('DOING_AJAX', true);
        define('WP_ADMIN', true);
    }
    $start = microtime(true);
    $report = static function() use ($start, $screen) {
        global $wpdb;
        $total_ms = 0.0; $plugin = 0; $plugin_ms = 0.0;
        foreach ((array) $wpdb->queries as $query) {
            $total_ms += (float) $query[1] * 1000;
            if (false !== strpos((string) $query[0], 'idlm_') || false !== strpos((string) $query[2], 'IDLM_')) { $plugin++; $plugin_ms += (float) $query[1] * 1000; }
        }
        $assets = array('css' => array(), 'js' => array());
        if ('page' === $screen['type']) {
            foreach (array('css' => wp_styles(), 'js' => wp_scripts()) as $kind => $registry) {
                $registry->all_deps($registry->queue);
                foreach ($registry->to_do as $handle) {
                    $src = isset($registry->registered[$handle]) ? (string) $registry->registered[$handle]->src : '';
                    $path = '';
                    if (false !== strpos($src, '/assets/')) { $path = IDLM_PATH . 'assets/' . explode('?', substr($src, strpos($src, '/assets/') + 8))[0]; }
                    elseif ('' !== $src && '/' === $src[0]) { $path = ABSPATH . ltrim($src, '/'); }
                    $assets[$kind][$handle] = ('' !== $path && is_file($path)) ? filesize($path) : 0;
                }
            }
        }
        $line = json_encode(array(
            'queries' => (int) $wpdb->num_queries,
            'query_ms' => round($total_ms, 2),
            'plugin_queries' => $plugin,
            'plugin_query_ms' => round($plugin_ms, 2),
            'wall_ms' => round((microtime(true) - $start) * 1000, 2),
            'peak_mb' => round(memory_get_peak_usage(true) / 1048576, 1),
            'assets' => $assets,
            'user' => get_current_user_id(),
            'html_bytes' => (int) ($GLOBALS['idlm_measure_html'] ?? 0),
        ));
        file_put_contents((string) getenv('IDLM_MEASURE_OUT'), $line);
        if (getenv('IDLM_MEASURE_SHAPES')) {
            // Normalized query shapes (literals removed) for repeated-query review; no values are printed.
            $shapes = array();
            foreach ((array) $wpdb->queries as $query) {
                $shape = preg_replace(array("/'(?:[^'\\\\]|\\\\.)*'/", '/\\b\\d+\\b/', '/\\s+/'), array('?', 'N', ' '), (string) $query[0]);
                $caller = preg_replace('/^.*?((?:IDLM_|idlm_)[A-Za-z_:>-]+)[^,]*$/', '$1', (string) $query[2]);
                $key = substr($shape, 0, 150) . '  <= ' . substr($caller, 0, 80);
                $shapes[$key] = ($shapes[$key] ?? 0) + 1;
            }
            arsort($shapes);
            $names = array();
            foreach ((array) $wpdb->queries as $query) {
                if (preg_match("/FROM {$wpdb->options} WHERE option_name = '([a-z0-9_]+)'/", (string) $query[0], $m)) {
                    $caller = preg_replace('/^.*?((?:IDLM_|idlm_)[A-Za-z_:>-]+)[^,]*$/', '$1', (string) $query[2]);
                    $names[$m[1] . ' <= ' . substr($caller, -60)] = ($names[$m[1] . ' <= ' . substr($caller, -60)] ?? 0) + 1;
                }
            }
            arsort($names);
            $shapes = array('options' => $names) + $shapes;
            file_put_contents((string) getenv('IDLM_MEASURE_OUT') . '.shapes', json_encode($shapes, JSON_PRETTY_PRINT));
        }
    };
    register_shutdown_function($report);
    require_once dirname(__DIR__) . '/integration/bootstrap.php';
    define('WP_PLUGIN_URL_OVERRIDE', true);
    if ('page' === $screen['type']) {
        // Theme-less front end: run the main query and the shortcode render with head/footer hooks.
        idlm_integration_boot_wordpress($config, false);
        if (getenv('IDLM_MEASURE_DEBUG')) { fwrite(STDERR, 'cookie-valid=' . var_export(wp_validate_auth_cookie($_COOKIE[LOGGED_IN_COOKIE] ?? '', 'logged_in'), true) . ' names=' . implode(',', array_keys($_COOKIE)) . ' expect=' . LOGGED_IN_COOKIE . "\n"); }
        wp();
        ob_start();
        do_action('wp_enqueue_scripts');
        $post = get_post((int) $state['page_id']);
        $GLOBALS['post'] = $post;
        setup_postdata($post);
        echo do_shortcode($post->post_content); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        $GLOBALS['idlm_measure_html'] = strlen((string) ob_get_clean());
        exit;
    }
    $_POST = array_merge($screen['post'], array('action' => $screen['action']));
    $_REQUEST = $_POST;
    idlm_integration_boot_wordpress($config, false);
    $_POST['nonce'] = wp_create_nonce('idlm_admin_nonce');
    $_REQUEST['nonce'] = $_POST['nonce'];
    add_filter('wp_die_ajax_handler', static function() { return static function() { $GLOBALS['idlm_measure_html'] = ob_get_length(); exit; }; });
    ob_start();
    do_action('admin_init');
    do_action('wp_ajax_' . $screen['action']);
    $GLOBALS['idlm_measure_html'] = strlen((string) ob_get_clean());
    exit;
}

/** Orchestrator. */
require_once dirname(__DIR__) . '/integration/bootstrap.php';
$runs = 3;
foreach ($argv as $argument) { if (0 === strpos($argument, '--runs=')) { $runs = max(1, (int) substr($argument, 7)); } }
$config = idlm_integration_config(false);
idlm_integration_export_config($config);
$database = new IDLM_Integration_Database($config);
register_shutdown_function(static function() use (&$database, $config) {
    @unlink($config['content_dir'] . '/plugins/interior-design-lead-manager');
    if ($database) { $database->drop(); }
    idlm_integration_remove_directory($config['content_dir']);
});

$admin_id = idlm_integration_boot_wordpress($config, true);
symlink(dirname(__DIR__, 2), $config['content_dir'] . '/plugins/interior-design-lead-manager');
require_once $config['content_dir'] . '/plugins/interior-design-lead-manager/interior-design-lead-manager.php';
idlm_activate_plugin();
update_option('active_plugins', array('interior-design-lead-manager/interior-design-lead-manager.php'));
idlm_boot_plugin();
update_option('idlm_max_active_leads_per_rep', 0);
$page_id = wp_insert_post(array('post_type' => 'page', 'post_status' => 'publish', 'post_title' => 'CRM', 'post_content' => '[idlm_workspace]'));
wp_set_current_user($admin_id);
$expiration = time() + DAY_IN_SECONDS;
$manager = WP_Session_Tokens::get_instance($admin_id);
$token = $manager->create($expiration);
// The installer defines cookie names before siteurl exists; derive them as a normal request does.
$cookie_hash = md5(WP_SITEURL);
$cookies = array('wordpress_logged_in_' . $cookie_hash => wp_generate_auth_cookie($admin_id, $expiration, 'logged_in', $token), 'wordpress_' . $cookie_hash => wp_generate_auth_cookie($admin_id, $expiration, 'auth', $token));

$seed = proc_open(
    escapeshellarg(PHP_BINARY) . ' -d ' . escapeshellarg('auto_prepend_file=' . dirname(__DIR__) . '/integration/performance-bootstrap.php') . ' '
        . escapeshellarg(__DIR__ . '/seed-dataset.php') . ' --profile=small --seed=screens-01 --batch-size=500 --execute --confirm=seed-idlm-performance-data',
    array(1 => array('pipe', 'w'), 2 => array('pipe', 'w')), $pipes, null, getenv() + array('IDLM_TEST_DB_NAME' => $config['db_name'])
);
$seed_out = stream_get_contents($pipes[1]); $seed_err = stream_get_contents($pipes[2]);
fclose($pipes[1]); fclose($pipes[2]);
if (0 !== proc_close($seed)) { fwrite(STDERR, "Seed failed: $seed_err\n"); exit(1); }
fwrite(STDERR, 'Seed: ' . substr((string) $seed_out, 0, 400) . "\n");

$state_file = $config['content_dir'] . '/measure-state.json';
file_put_contents($state_file, json_encode(array('config' => $config, 'cookies' => $cookies, 'page_id' => $page_id)));
$results = array();
foreach (array_keys(IDLM_MEASURE_SCREENS) as $screen) {
    $samples = array();
    for ($run = 0; $run < $runs + 1; $run++) {
        $out = $config['content_dir'] . '/measure-' . $screen . '.json';
        @unlink($out);
        $child = proc_open(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(__FILE__) . ' --child ' . escapeshellarg($screen),
            array(1 => array('file', '/dev/null', 'w'), 2 => array('pipe', 'w')), $child_pipes, null,
            getenv() + array('IDLM_TEST_DB_NAME' => $config['db_name'], 'IDLM_MEASURE_STATE' => $state_file, 'IDLM_MEASURE_OUT' => $out));
        $child_err = stream_get_contents($child_pipes[2]);
        fclose($child_pipes[2]); proc_close($child);
        if (getenv('IDLM_MEASURE_DEBUG')) { fwrite(STDERR, $child_err); }
        $sample = is_file($out) ? json_decode((string) file_get_contents($out), true) : null;
        if (!is_array($sample)) { fwrite(STDERR, "Measurement failed for $screen: $child_err\n"); exit(1); }
        if (getenv('IDLM_MEASURE_SHAPES') && is_file($out . '.shapes')) { fwrite(STDERR, "== $screen run $run\n" . file_get_contents($out . '.shapes') . "\n"); }
        if (0 === $run) { $cold = $sample; continue; } // first run warms caches and opcode files
        $samples[] = $sample;
    }
    $median = static function($key) use ($samples) { $v = array_column($samples, $key); sort($v); return $v[(int) floor(count($v) / 2)]; };
    $last = end($samples);
    $results[$screen] = array(
        'cold_queries' => $cold['queries'],
        'queries' => $last['queries'],
        'plugin_queries' => $last['plugin_queries'],
        'query_ms_median' => $median('query_ms'),
        'wall_ms_median' => $median('wall_ms'),
        'peak_mb' => $last['peak_mb'],
        'user' => $last['user'],
        'response_bytes' => $last['html_bytes'],
        'css_bytes' => array_sum($last['assets']['css']),
        'js_bytes' => array_sum($last['assets']['js']),
        'assets' => $last['assets'],
    );
}
echo json_encode(array('php' => PHP_VERSION, 'wp' => $GLOBALS['wp_version'], 'runs' => $runs, 'dataset' => 'small screens-01 (1000 leads)', 'screens' => $results), JSON_PRETTY_PRINT), "\n";
