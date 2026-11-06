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
