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
    assert_same(40, count($hooks), 'Every AJAX hook is still registered on the facade.');
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
