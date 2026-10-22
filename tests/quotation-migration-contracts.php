<?php
/** QUO-004 failure propagation; native tests establish physical/data outcomes. Builder: Shashi. */
if (!defined('IDLM_TESTING')) { exit; }

idlm_test('QUO004 schema lock and future markers reject work before DDL', static function(): void {
    $GLOBALS['wpdb']->deny_schema_lock = true;
    assert_true(is_wp_error(IDLM_Quotations::install()));
    assert_count_value(0, $GLOBALS['wpdb']->dbdelta);
    $GLOBALS['wpdb']->deny_schema_lock = false;
    update_option('idlm_quotation_schema_version', '99.0.0');
    assert_same('idlm_schema_quotation_future_version', IDLM_Quotations::install()->get_error_code());
    assert_count_value(0, $GLOBALS['wpdb']->dbdelta);
});

idlm_test('QUO004 seed failure preserves schema marker and retry completes all flags', static function(): void {
    $GLOBALS['wpdb']->custom_get_var_result = 0;
    update_option('idlm_quotation_schema_version', '2.5.0');
    $GLOBALS['wpdb']->query_fail_contains = 'INSERT IGNORE INTO wp_idlm_quote_item_units';
    assert_true(is_wp_error(IDLM_Quotations::install()));
    assert_same('2.5.0', get_option('idlm_quotation_schema_version'));
    assert_false(IDLM_Quotation_Schema::is_current());
    $GLOBALS['wpdb']->query_fail_contains = '';
    assert_same(true, IDLM_Quotations::install());
    assert_true(IDLM_Quotation_Schema::is_current());
});

idlm_test('QUO004 physical engine mismatch and dependency engines block completion', static function(): void {
    foreach (array('wp_idlm_quote_revisions', 'wp_options', 'wp_idlm_leads') as $table) {
        idlm_test_reset_environment();
        $GLOBALS['wpdb']->custom_get_var_result = 0;
        $GLOBALS['wpdb']->schema_engines[$table] = 'MyISAM';
        assert_true(is_wp_error(IDLM_Quotations::install()));
        assert_same(false, get_option('idlm_quotation_schema_version'));
    }
});

idlm_test('QUO004 marker failure and commit failure cannot publish successful migration', static function(): void {
    foreach (array('idlm_legacy_quote_migration_version', 'idlm_quotation_snapshot_migration_version', 'idlm_quotation_revision_lifecycle_migration_version', 'idlm_quotation_schema_version', 'COMMIT') as $failure) {
        idlm_test_reset_environment();
        $GLOBALS['wpdb']->custom_get_var_result = 0;
        update_option('idlm_quotation_schema_version', '2.5.0');
        if ('COMMIT' === $failure) { $GLOBALS['wpdb']->query_fail_contains = 'COMMIT'; }
        else { $GLOBALS['idlm_test_option_write_failures'] = array($failure); }
        assert_true(is_wp_error(IDLM_Quotations::install()));
        assert_same('2.5.0', get_option('idlm_quotation_schema_version'));
        $GLOBALS['wpdb']->query_fail_contains = ''; $GLOBALS['idlm_test_option_write_failures'] = array();
        assert_same(true, IDLM_Quotations::install());
    }
});

idlm_test('QUO004 late lifecycle failure retries even with the schema version already current', static function(): void {
    $GLOBALS['wpdb']->custom_get_var_result = 0;
    update_option('idlm_quotation_schema_version', IDLM_Quotation_Schema::VERSION);
    $GLOBALS['idlm_test_option_write_failures'] = array('idlm_quotation_revision_lifecycle_migration_version');
    assert_true(is_wp_error(IDLM_Quotations::init()));
    assert_same(false, get_option('idlm_quotation_revision_lifecycle_migration_version'));
    assert_false(in_array('admin_post_idlm_save_quote', array_column($GLOBALS['idlm_test_hooks'], 'hook'), true));
    $GLOBALS['idlm_test_option_write_failures'] = array();
    assert_same(true, IDLM_Quotations::install());
    assert_true(IDLM_Quotation_Schema::is_current());
});

idlm_test('QUO004 refuses foreign transaction before quotation DDL or implicit commit', static function(): void {
    $GLOBALS['wpdb']->query('START TRANSACTION');
    $GLOBALS['wpdb']->insert('wp_idlm_leads', array('first_name' => 'Uncommitted'));
    assert_true(is_wp_error(IDLM_Quotations::install()));
    assert_count_value(0, $GLOBALS['wpdb']->dbdelta);
    $GLOBALS['wpdb']->query('ROLLBACK');
    assert_count_value(0, $GLOBALS['wpdb']->rows('idlm_leads'));
});

idlm_test('QUO004 failed explicit reinstall remains pending at an already current version',static function(): void {
    $GLOBALS['wpdb']->custom_get_var_result=0;
    assert_same(true,IDLM_Quotations::install());
    $GLOBALS['wpdb']->query_fail_contains='INSERT IGNORE INTO wp_idlm_quote_item_units';
    assert_true(is_wp_error(IDLM_Quotations::install()));
    assert_same(IDLM_Quotation_Schema::VERSION,get_option('idlm_quotation_schema_version'));
    assert_same('pending',IDLM_Schema_Migration::version(IDLM_Quotation_Schema::STATUS_OPTION));
    assert_false(IDLM_Quotation_Schema::is_current());
    $GLOBALS['wpdb']->query_fail_contains='';
    assert_same(true,IDLM_Quotations::install());
    assert_true(IDLM_Quotation_Schema::is_current());
});
