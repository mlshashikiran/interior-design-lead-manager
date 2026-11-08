<?php
/**
 * Contracts that keep consolidated helpers identical to the code they replaced.
 * Builder: Shashi.
 */
if (!defined('IDLM_TESTING')) { exit; }

/** The pre-consolidation quotation completion check, one live read per marker. */
function idlm_streamline_legacy_quotation_current(): bool {
    if ('ready' !== IDLM_Schema_Migration::version(IDLM_Quotation_Schema::STATUS_OPTION)) { return false; }
    $markers = array(
        'idlm_quotation_schema_version' => IDLM_Quotation_Schema::VERSION,
        'idlm_legacy_quote_migration_version' => IDLM_Quotation_Schema::LEGACY_MIGRATION_VERSION,
        'idlm_quotation_snapshot_migration_version' => IDLM_Quotation_Schema::SNAPSHOT_MIGRATION_VERSION,
        'idlm_quotation_revision_lifecycle_migration_version' => IDLM_Quotation_Schema::REVISION_LIFECYCLE_MIGRATION_VERSION,
    );
    foreach ($markers as $option => $version) {
        if ($version !== IDLM_Schema_Migration::version($option)) { return false; }
    }
    return true;
}

idlm_test('STREAMLINE batched marker reads return exactly what single live reads return', static function (): void {
    $GLOBALS['idlm_test_options']['idlm_streamline_a'] = '1.2.3';
    $GLOBALS['idlm_test_options']['idlm_streamline_b'] = '';
    $names = array('idlm_streamline_a', 'idlm_streamline_b', 'idlm_streamline_missing');
    $batched = IDLM_Schema_Migration::versions($names);
    foreach ($names as $name) {
        assert_same(IDLM_Schema_Migration::version($name), $batched[$name], $name);
    }
    assert_same(array(), IDLM_Schema_Migration::versions(array()));
    $GLOBALS['wpdb']->query_fail_contains = 'option_name IN';
    assert_same(array('idlm_streamline_a' => null, 'idlm_streamline_b' => null), IDLM_Schema_Migration::versions(array('idlm_streamline_a', 'idlm_streamline_b')));
    $GLOBALS['wpdb']->query_fail_contains = '';
});

idlm_test('STREAMLINE quotation completion matches the per-marker check in one read', static function (): void {
    $complete = array(
        IDLM_Quotation_Schema::STATUS_OPTION => 'ready',
        'idlm_quotation_schema_version' => IDLM_Quotation_Schema::VERSION,
        'idlm_legacy_quote_migration_version' => IDLM_Quotation_Schema::LEGACY_MIGRATION_VERSION,
        'idlm_quotation_snapshot_migration_version' => IDLM_Quotation_Schema::SNAPSHOT_MIGRATION_VERSION,
        'idlm_quotation_revision_lifecycle_migration_version' => IDLM_Quotation_Schema::REVISION_LIFECYCLE_MIGRATION_VERSION,
    );
    $cases = array('complete' => $complete);
    foreach (array_keys($complete) as $name) {
        $cases['missing ' . $name] = array_diff_key($complete, array($name => true));
        $cases['stale ' . $name] = array_merge($complete, array($name => '0.0.1'));
    }
    foreach ($cases as $label => $options) {
        foreach (array_keys($complete) as $name) { unset($GLOBALS['idlm_test_options'][$name]); }
        foreach ($options as $name => $value) { $GLOBALS['idlm_test_options'][$name] = $value; }
        $GLOBALS['wpdb']->get_results_calls = array();
        assert_same(idlm_streamline_legacy_quotation_current(), IDLM_Quotation_Schema::is_current(), $label);
        assert_count_value(1, $GLOBALS['wpdb']->get_results_calls, $label . ' uses one marker read');
    }
    $GLOBALS['wpdb']->query_fail_contains = 'option_name IN';
    assert_false(IDLM_Quotation_Schema::is_current(), 'A failed marker read is never current.');
    $GLOBALS['wpdb']->query_fail_contains = '';
});

idlm_test('STREAMLINE platform secret scan reads every secret live in one query', static function (): void {
    $names = IDLM_Secret_Store::platform_options();
    $GLOBALS['idlm_test_options']['idlm_meta_access_token'] = 'synthetic-plaintext-token';
    $batched = IDLM_Secret_Store::stored_many($names);
    foreach ($names as $name) {
        assert_same(IDLM_Secret_Store::stored($name), $batched[$name], $name);
    }
    $GLOBALS['wpdb']->get_results_calls = array();
    assert_true(IDLM_Secret_Store::migrate_platforms());
    assert_count_value(1, array_filter($GLOBALS['wpdb']->get_results_calls, static function ($query) { return false !== strpos($query, 'option_name IN'); }));
    assert_same(0, strpos((string) get_option('idlm_meta_access_token'), IDLM_Secret_Store::PREFIX), 'Plaintext is still encrypted by the scan.');
    $GLOBALS['wpdb']->query_fail_contains = 'option_name IN';
    assert_false(IDLM_Secret_Store::migrate_platforms(), 'A failed scan read is never ready.');
    $GLOBALS['wpdb']->query_fail_contains = '';
});

/** Body of a top-level function or class method, for source contracts. */
function idlm_streamline_function_source(string $file, string $function): string {
    $source = (string) file_get_contents(dirname(__DIR__) . '/' . $file);
    $start = strpos($source, 'function ' . $function . '(');
    assert_true(false !== $start, $function . ' exists in ' . $file);
    $next = preg_match('/\n\s*(?:(?:public|private|protected)\s+)?(?:static\s+)?function\s/', $source, $match, PREG_OFFSET_CAPTURE, $start + 10) ? $match[0][1] : strlen($source);
    return substr($source, $start, $next - $start);
}

idlm_test('STREAMLINE boot priming covers every option the boot path reads', static function (): void {
    if (!function_exists('idlm_boot_option_names')) {
        // The fast suite does not load the bootstrap file; evaluate only this pure list function.
        eval(idlm_streamline_function_source('interior-design-lead-manager.php', 'idlm_boot_option_names')); // phpcs:ignore Squiz.PHP.Eval.Discouraged
    }
    $primed = idlm_boot_option_names();
    foreach (array_keys(IDLM_DB::MIGRATION_STEPS) as $marker) { assert_true(in_array($marker, $primed, true), $marker); }
    $paths = array(
        array('interior-design-lead-manager.php', 'idlm_boot_plugin'),
        array('interior-design-lead-manager.php', 'idlm_register_design_role'),
        array('includes/class-telemetry.php', 'init_handlers'),
        array('includes/class-settings.php', 'init'),
        array('includes/class-db.php', 'init'),
        array('includes/class-integration-auth.php', 'maybe_migrate_api_credentials'),
        array('includes/class-integrations.php', 'init'),
    );
    foreach ($paths as $path) {
        preg_match_all("/get_option\\('(idlm_[a-z0-9_]+)'/", idlm_streamline_function_source($path[0], $path[1]), $reads);
        foreach ($reads[1] as $name) { assert_true(in_array($name, $primed, true), $name . ' read by ' . $path[1] . ' is primed'); }
    }
    foreach (array('idlm_webhook_api_key', 'idlm_mcp_api_key', 'idlm_google_gadm_password', IDLM_Settings::VERSION_OPTION, IDLM_Production_Security::MCP_METADATA_OPTION) as $name) {
        assert_true(in_array($name, $primed, true), $name);
    }
    $boot = idlm_streamline_function_source('interior-design-lead-manager.php', 'idlm_boot_plugin');
    assert_true(strpos($boot, 'wp_prime_option_caches(idlm_boot_option_names())') < strpos($boot, 'IDLM_Auth::init()'), 'Options are primed before the first boot read.');
});

idlm_test('STREAMLINE both quotation editor entry points enqueue the same bundle', static function (): void {
    $GLOBALS['idlm_test_asset_calls'] = array();
    IDLM_Quotations::enqueue_assets('toplevel_page_other');
    assert_same(array(), $GLOBALS['idlm_test_asset_calls'], 'Other admin pages load nothing.');
    IDLM_Quotations::enqueue_assets('toplevel_page_idlm_quotations');
    $admin = $GLOBALS['idlm_test_asset_calls'];
    $GLOBALS['idlm_test_asset_calls'] = array();
    IDLM_Quotations::load_editor_assets();
    $editor = $GLOBALS['idlm_test_asset_calls'];
    assert_same(array('type' => 'enqueue_style', 'args' => array('dashicons')), array_shift($editor));
    assert_same($admin, $editor);
    $sequence = array_map(static function ($call) { return $call['type'] . ':' . $call['args'][0] . (('localize_script' === $call['type']) ? ':' . $call['args'][1] : ''); },
        array_values(array_filter($admin, static function ($call) { return 0 !== strpos($call['type'], 'register') && 'inline_script' !== $call['type'] && !('localize_script' === $call['type'] && 'idlm-admin-app' === $call['args'][0]); })));
    assert_same(array('enqueue_style:idlm-google-fonts', 'enqueue_style:idlm-admin-styles', 'enqueue_script:idlm-dialog', 'enqueue_style:idlm-quotations', 'enqueue_script:idlm-quotations', 'localize_script:idlm-quotations:idlmQuoteData'), $sequence);
    $settings = end($admin)['args'][2];
    assert_same(array('ajaxUrl', 'nonce', 'autosaveNonce', 'currencyCode', 'numericLimits'), array_keys($settings));
    assert_same(array('maxAmount', 'maxMeasure', 'maxRows', 'maxSections'), array_keys($settings['numericLimits']));
});

idlm_test('STREAMLINE quotation list dates use the shared check with the same results', static function (): void {
    $legacy = static function ($value) {
        $value = sanitize_text_field($value);
        $date = DateTime::createFromFormat('Y-m-d', $value, wp_timezone());
        return $date && $date->format('Y-m-d') === $value ? $value : null;
    };
    $method = new ReflectionMethod('IDLM_Quotation_Renderer', 'clean_date');
    $method->setAccessible(true);
    $inputs = array('2026-11-06', ' 2026-11-06 ', '2026-02-29', '2028-02-29', '2026-02-30', '2026-13-01', '26-11-06', '2026-1-6',
        '2026-11-06 10:00', '', '0', '0000-00-00', '<b>2026-11-06</b>', "2026-11-06\n", 'not a date', array('2026-11-06'));
    foreach ($inputs as $input) {
        assert_same($legacy($input), $method->invoke(null, $input), var_export($input, true));
    }
    assert_false(strpos((string) file_get_contents(dirname(__DIR__) . '/includes/class-quotation-controller.php'), 'function clean_date('), 'The unused controller copy stays removed.');
});

idlm_test('STREAMLINE every plugin asset version comes from one helper with unchanged values', static function (): void {
    $root = dirname(__DIR__) . '/';
    foreach (array_merge(glob($root . 'assets/js/*.js'), glob($root . 'assets/css/*.css')) as $file) {
        $relative = substr($file, strlen($root . 'assets/'));
        assert_same((string) filemtime($file), IDLM_Admin_Assets::version($relative), $relative);
    }
    assert_same(IDLM_VERSION, IDLM_Admin_Assets::version('js/absent-asset.js'));
    foreach (glob($root . 'includes/*.php') as $file) {
        if ('class-admin-assets.php' === basename($file)) { continue; }
        assert_false(strpos((string) file_get_contents($file), 'filemtime('), basename($file) . ' versions assets through IDLM_Admin_Assets::version().');
    }
});

/** Records whether PHP ever built this class from stored data. */
final class IDLM_Streamline_Wakeup_Probe {
    public function __wakeup() { $GLOBALS['idlm_streamline_wakeups'] = ($GLOBALS['idlm_streamline_wakeups'] ?? 0) + 1; }
}

idlm_test('STREAMLINE stored values decode like maybe_unserialize but never build objects', static function (): void {
    $core = static function ($value) { return is_serialized($value) ? @unserialize(trim($value)) : $value; }; // phpcs:ignore
    $inputs = array(null, '', '0', 'plain text', 'N;', 'b:0;', 'b:1;', 'i:42;', 'd:1.5;', 's:3:"abc";', serialize(array('a' => array(1, '2', true))),
        ' ' . serialize(array('padded')) . "\n", 'a:1:{', 's:9:"short";', serialize(serialize(array('double'))), array('already' => 'decoded'), 7, false);
    foreach ($inputs as $input) {
        assert_same($core($input), IDLM_Schema_Migration::decode($input), var_export($input, true));
    }
    $GLOBALS['idlm_streamline_wakeups'] = 0;
    $decoded = IDLM_Schema_Migration::decode(serialize(array('probe' => new IDLM_Streamline_Wakeup_Probe())));
    assert_same(0, $GLOBALS['idlm_streamline_wakeups'], 'No stored object is ever woken.');
    assert_true($decoded['probe'] instanceof __PHP_Incomplete_Class);
    foreach (glob(dirname(__DIR__) . '/includes/*.php') as $file) {
        if ('class-schema-migration.php' === basename($file)) { continue; }
        assert_false(strpos((string) file_get_contents($file), 'maybe_unserialize('), basename($file) . ' decodes stored rows through IDLM_Schema_Migration::decode().');
    }
});

idlm_test('STREAMLINE Meta and Interakt webhooks verify signatures with one check', static function (): void {
    $body = '{"synthetic":true}';
    $good = hash_hmac('sha256', $body, 'synthetic-secret');
    $cases = array(array($body, 'sha256=' . $good, 'synthetic-secret', true), array($body, $good, 'synthetic-secret', true),
        array($body, 'sha256=' . $good, 'wrong-secret', false), array($body . ' ', 'sha256=' . $good, 'synthetic-secret', false),
        array($body, 'sha256=' . strtoupper($good), 'synthetic-secret', false), array($body, 'sha1=' . $good, 'synthetic-secret', false));
    foreach ($cases as $index => $case) {
        assert_same($case[3], IDLM_Interakt::verify_webhook_signature($case[0], $case[1], $case[2]), 'Interakt case ' . $index);
        assert_same($case[3], IDLM_Interakt::verify_meta_webhook_signature($case[0], $case[1], $case[2]), 'Meta case ' . $index);
    }
});

idlm_test('STREAMLINE no stylesheet keeps a rule that an identical later rule always overrides', static function (): void {
    require_once __DIR__ . '/support/css-duplicates.php';
    $sample = ".a{color:red}\n@media (max-width:600px){.b{margin:0}}\n.c{x:1}\n.a { color: red; }\n@media (max-width: 600px){ .b{ margin:0 } }\n@media print{.a{color:red}}";
    $dead = idlm_css_dead_duplicates($sample);
    assert_same(2, count($dead), 'The parser finds duplicates across whitespace and repeated media blocks.');
    assert_same(".a{color:red}", substr($sample, $dead[0]['start'], $dead[0]['end'] - $dead[0]['start']));
    foreach (glob(dirname(__DIR__) . '/assets/css/*.css') as $file) {
        $dead = idlm_css_dead_duplicates((string) file_get_contents($file));
        assert_same(array(), array_map(static function ($rule) { return strtok($rule['key'], "\n") . ' ' . substr($rule['key'], strpos($rule['key'], "\n") + 1, 80); }, $dead), basename($file));
    }
});

idlm_test('STREAMLINE AJAX hooks reach their controller in one hop and the old AJAX class names still answer', static function (): void {
    $facade = (string) file_get_contents(dirname(__DIR__) . '/includes/class-admin.php');
    $ajax = (string) file_get_contents(dirname(__DIR__) . '/includes/class-admin-ajax.php');
    preg_match_all('/add_action\(\'wp_ajax_idlm_(\w+)\', array\(__CLASS__, \'(ajax_\w+)\'\)\);/', $facade, $hooks, PREG_SET_ORDER);
    assert_same(41, count($hooks), 'Every AJAX hook is still registered on the facade.');
    foreach ($hooks as $hook) {
        assert_same('ajax_' . $hook[1], $hook[2]);
        assert_true(is_callable(array('IDLM_Admin', $hook[2])) && is_callable(array('IDLM_Admin_Ajax', $hook[2])), $hook[2] . ' stays callable on both classes.');
        assert_true(1 === preg_match('/public static function ' . $hook[2] . '\(\) \{ return (IDLM_\w+)::' . $hook[2] . '\(\); \}/', $facade, $target), $hook[2] . ' delegates once.');
        if ('IDLM_Admin_Ajax' !== $target[1]) {
            assert_true(method_exists($target[1], $hook[2]), $hook[2] . ' reaches ' . $target[1]);
            assert_true(false === strpos($ajax, 'function ' . $hook[2] . '('), $hook[2] . ' has no pass-through left in the AJAX class.');
        } else {
            assert_true(false !== strpos($ajax, 'function ' . $hook[2] . '('), $hook[2] . ' keeps its handler in the AJAX class.');
        }
    }
});

idlm_test('STREAMLINE Google conversions download needs the Settings button token', static function (): void {
    $root = dirname(__DIR__);
    $ajax = (string) file_get_contents($root . '/includes/class-admin-ajax.php');
    $start = strpos($ajax, 'public static function ajax_export_google_conversions()');
    $body = substr($ajax, $start, strpos($ajax, 'public static function ajax_export_leads()') - $start);
    $capability = strpos($body, "current_user_can('manage_options')");
    $nonce = strpos($body, "check_ajax_referer('idlm_export_google_conversions');");
    $stream = strpos($body, 'generate_google_ads_conversions_csv_response');
    assert_true(false !== $capability && false !== $nonce && $capability < $nonce && $nonce < $stream, 'Capability, then token, then the CSV.');
    assert_contains("wp_nonce_url(admin_url('admin-ajax.php?action=idlm_export_google_conversions'), 'idlm_export_google_conversions')", (string) file_get_contents($root . '/templates/admin/settings/capi.php'));
});

idlm_test('STREAMLINE test suites never write into tracked docs', static function (): void {
    foreach (glob(dirname(__DIR__) . '/tests/integration/*.php') as $file) {
        $source = (string) file_get_contents($file);
        assert_false((bool) preg_match('#file_put_contents\([^;]*/docs/#', $source), basename($file) . ' writes into docs/.');
    }
});

idlm_test('STREAMLINE leads and settings screens prime every option they read in one query', static function (): void {
    foreach (array('IDLM_Admin_Leads_Renderer' => 'class-admin-leads-renderer.php', 'IDLM_Admin_Settings_Renderer' => 'class-admin-settings-renderer.php') as $class => $file) {
        $source = (string) file_get_contents(dirname(__DIR__) . '/includes/' . $file);
        preg_match_all("/get_option\\('(idlm_[a-zA-Z0-9_]*[a-zA-Z0-9])'/", $source, $reads);
        $missing = array_diff(array_unique($reads[1]), constant($class . '::SCREEN_OPTIONS'));
        assert_same(array(), array_values($missing), $file . ' reads options it does not prime.');
        assert_true(false !== strpos($source, "private static function build_context() {\n        wp_prime_option_caches(self::SCREEN_OPTIONS);"), $file . ' must prime before reading.');
    }
});

idlm_test('STREAMLINE maintenance checks run once per interval and again right after an update', static function (): void {
    unset($GLOBALS['idlm_test_options'][IDLM_Scheduler::MAINTENANCE_OPTION]);
    assert_true(IDLM_Scheduler::maintenance_due('schedules'), 'First request runs the check.');
    assert_false(IDLM_Scheduler::maintenance_due('schedules'), 'The next request inside the interval skips it.');
    assert_true(IDLM_Scheduler::maintenance_due('integration_upkeep'), 'Tasks are throttled separately.');
    $GLOBALS['idlm_test_options'][IDLM_Scheduler::MAINTENANCE_OPTION]['schedules'] = IDLM_VERSION . '|' . (time() - 1);
    assert_true(IDLM_Scheduler::maintenance_due('schedules'), 'An expired interval runs again.');
    $GLOBALS['idlm_test_options'][IDLM_Scheduler::MAINTENANCE_OPTION]['schedules'] = '0.0.1|' . (time() + 3600);
    assert_true(IDLM_Scheduler::maintenance_due('schedules'), 'A plugin update runs it at once.');
    $GLOBALS['idlm_test_options'][IDLM_Scheduler::MAINTENANCE_OPTION] = 'corrupt';
    assert_true(IDLM_Scheduler::maintenance_due('schedules'), 'A damaged value fails open and runs the check.');

    $source = (string) file_get_contents(dirname(__DIR__) . '/includes/class-integrations.php');
    assert_contains("if (IDLM_Scheduler::maintenance_due('integration_upkeep')) {\n            self::run_upkeep();", $source, 'Credential, secret and queue upkeep is throttled.');
    $start = strpos($source, 'public static function init()');
    $init = substr($source, $start, strpos($source, 'public static function run_upkeep') - $start);
    foreach (array('maybe_migrate_api_credentials()', 'migrate_platforms()', 'scrub_legacy_queues()') as $call) {
        assert_false(false !== strpos($init, $call), $call . ' runs on every request again.');
    }
    $plugin = (string) file_get_contents(dirname(__DIR__) . '/interior-design-lead-manager.php');
    assert_contains("IDLM_Scheduler::setup_schedules();\n    IDLM_Integrations::run_upkeep();", $plugin, 'Activation sets everything up at once.');
    $GLOBALS['idlm_test_hooks'] = array();
    IDLM_Scheduler::init();
    $hooks = array_column($GLOBALS['idlm_test_hooks'], 'hook');
    assert_true(in_array('cron_schedules', $hooks, true), 'The minute interval is registered on every request.');
    assert_true(in_array('init', $hooks, true) && !in_array('wp', $hooks, true), 'Schedules are checked on every kind of request, not only page views.');
    unset($GLOBALS['idlm_test_options'][IDLM_Scheduler::MAINTENANCE_OPTION]);
});

idlm_test('STREAMLINE CRM AJAX checks the nonce before access and refuses with one error shape', static function (): void {
    foreach (glob(dirname(__DIR__) . '/includes/*.php') as $file) {
        $source = (string) file_get_contents($file);
        assert_false((bool) preg_match("/wp_send_json_error\\(\\s*['\"]/", $source), basename($file) . ' sends a bare-string error.');
        // Every shared access check comes straight after the handler's own nonce check.
        $calls = substr_count($source, 'IDLM_Admin::require_ajax_access(');
        assert_same($calls, preg_match_all("/check_ajax_referer\\('idlm_admin_nonce', 'nonce'\\);\\n\\s*IDLM_Admin::require_ajax_access\\(/", $source), basename($file) . ' checks access without the nonce first.');
    }
    $caps = $GLOBALS['idlm_test_caps'] ?? array();
    try {
        $GLOBALS['idlm_test_caps'] = array();
        $refused = expect_json_response(static function () { IDLM_Admin::require_ajax_access('admin', 'Only administrators can do this.', 403); }, false, 403);
        assert_same(array('message' => 'Only administrators can do this.'), $refused->data);
        assert_same(array('message' => 'Permission denied.'), expect_json_response(static function () { IDLM_Admin::require_ajax_access('unknown-rule'); }, false)->data);
        $GLOBALS['idlm_test_caps'] = array('manage_options' => true);
        IDLM_Admin::require_ajax_access('admin');
        assert_same(array('message' => 'Permission denied.'), expect_json_response(static function () { IDLM_Admin::require_ajax_access('unknown-rule'); }, false)->data, 'Unknown rules refuse even administrators.');
    } finally { $GLOBALS['idlm_test_caps'] = $caps; }
});

idlm_test('STREAMLINE each workspace view loads the shared shell plus only its own assets', static function (): void {
    $caps = $GLOBALS['idlm_test_caps'] ?? array(); $get = $_GET; $logged_in = $GLOBALS['idlm_test_logged_in'] ?? false;
    try {
        $GLOBALS['idlm_test_logged_in'] = true;
        $GLOBALS['idlm_test_caps'] = array('manage_options' => true, 'idlm_access_leads' => true, 'idlm_view_quotes' => true);
        $shared = array('idlm-google-fonts', 'idlm-admin-styles', 'idlm-workspace-styles', 'idlm-crm-design', 'idlm-crm-design-leads', 'idlm-crm-design-payments');
        // Every workspace view, pinned: the shared shell plus only that view's own bundles.
        $shell = array('idlm-dialog', 'idlm-admin-app', 'idlm-access-account', 'idlm-workspace-app');
        $own = array(
            'leads'         => array(array(), array()),
            'pipeline'      => array(array('idlm-kanban-styles', 'idlm-crm-design-pipeline'), array('idlm-kanban-app')),
            'dashboard'     => array(array('idlm-dashboard-styles', 'idlm-crm-design-dashboard'), array('idlm-chartjs', 'idlm-dashboard-app')),
            'quotations'    => array(array('idlm-crm-design-quotations'), array()),
            'payments'      => array(array(), array()),
            'design-team'   => array(array(), array()),
            'capi-logs'     => array(array('idlm-crm-design-admin'), array('idlm-users-logs')),
            'audience-logs' => array(array('idlm-crm-design-admin'), array('idlm-users-logs')),
            'users'         => array(array('idlm-crm-design-admin'), array('idlm-users-logs')),
            'settings'      => array(array('idlm-crm-design-settings', 'idlm-crm-design-admin'), array('idlm-settings')),
            'my-account'    => array(array('idlm-crm-design-admin'), array()),
            // An unknown view renders the default dashboard, so it gets the dashboard's bundles.
            'no-such-view'  => array(array('idlm-dashboard-styles', 'idlm-crm-design-dashboard'), array('idlm-chartjs', 'idlm-dashboard-app')),
        );
        $cases = array();
        foreach ($own as $view => $extra) { $cases[$view] = array(array_merge($shared, $extra[0]), array_merge($shell, $extra[1])); }
        foreach ($cases as $view => $expected) {
            $_GET = array('idlm_view' => $view);
            $GLOBALS['idlm_test_asset_calls'] = array();
            $GLOBALS['post'] = new WP_Post('[idlm_workspace]');
            IDLM_Admin::enqueue_frontend_assets();
            $styles = idlm_admin_asset_handles('enqueue_style'); $scripts = idlm_admin_asset_handles('enqueue_script');
            sort($styles); $want = $expected[0]; sort($want);
            assert_same($want, $styles, $view . ' styles');
            assert_same($expected[1], $scripts, $view . ' scripts');
        }
        // Every manifest handle is registered, and every view the workspace offers has an entry.
        foreach (IDLM_Admin_Assets::VIEW_ASSETS as $view => $assets) {
            foreach (array_merge($assets['styles'], $assets['scripts']) as $handle) {
                assert_true(false !== strpos((string) file_get_contents(dirname(__DIR__) . '/includes/class-admin-assets.php'), "'" . $handle . "', \$asset_url"), $view . ': ' . $handle . ' is not registered.');
            }
            assert_same(array(), array_diff($assets['areas'], IDLM_Admin_Assets::DESIGN_AREAS), $view . ' names an unknown design area.');
        }
        $_GET = array();
        $sections = array_keys(IDLM_Admin_Shortcodes::workspace_sections()['sections']);
        assert_same(array(), array_values(array_diff($sections, array_keys(IDLM_Admin_Assets::VIEW_ASSETS))), 'A workspace view has no asset entry.');
    } finally { $GLOBALS['idlm_test_caps'] = $caps; $_GET = $get; $GLOBALS['idlm_test_logged_in'] = $logged_in; }
});
