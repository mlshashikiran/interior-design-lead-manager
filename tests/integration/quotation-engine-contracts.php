<?php
/** QUO-007 actual engine drift, metadata locks and recovery. Builder: Shashi. */
if (!defined('ABSPATH') || strpos(DB_NAME, 'idlm_test_') !== 0) { exit(1); }

/** Preserve rows; shorten only the disposable catalogue index for MyISAM's key limit. */
function idlm_engine_fixture_convert(string $table, string $engine): void {
    global $wpdb;
    $catalogue=$table===$wpdb->prefix.'idlm_quote_catalogue_items';
    if ($catalogue && 'MyISAM'===$engine) { idlm_integration_assert_true(false!==$wpdb->query("ALTER TABLE {$table} DROP INDEX status_name, ADD INDEX status_name (status, item_name(100))")); }
    idlm_integration_assert_true(false!==$wpdb->query("ALTER TABLE {$table} ENGINE={$engine}"),'Fixture engine conversion failed for '.$table.': '.$wpdb->last_error);
    if ($catalogue && 'InnoDB'===$engine) { idlm_integration_assert_true(false!==$wpdb->query("ALTER TABLE {$table} DROP INDEX status_name, ADD INDEX status_name (status, item_name)")); }
}

idlm_integration_test('T10-N-QUO007-standalone-number-reservation-refuses-MyISAM-without-advancing-sequence', static function(): void {
    global $wpdb;
    $table=$wpdb->prefix.'idlm_number_sequences';
    $before=$wpdb->get_results("SELECT * FROM {$table} ORDER BY id", ARRAY_A);
    idlm_engine_fixture_convert($table,'MyISAM');
    try {
        $result=IDLM_Quotation_Repository::reserve_quote_number(array());
        idlm_integration_assert_true(is_wp_error($result),'Unsupported reservation was acknowledged; nontransactional numbering can advance.');
        idlm_integration_assert_same($before,$wpdb->get_results("SELECT * FROM {$table} ORDER BY id",ARRAY_A));
    } finally { $wpdb->query('ROLLBACK'); idlm_engine_fixture_convert($table,'InnoDB'); }
});

idlm_integration_test('T10-T20-N-QUO007-every-save-dependency-rejects-post-install-engine-drift-and-rechecks-after-repair', static function(): void {
    global $wpdb;
    idlm_schema_expect_ok(IDLM_Quotations::install());
    $tables=array($wpdb->options,$wpdb->prefix.'idlm_leads',$wpdb->prefix.'idlm_lead_activity_log');
    foreach(IDLM_Quotation_Schema::schema_statements() as $sql) { preg_match('/^CREATE TABLE ([a-zA-Z0-9_]+)/',$sql,$m);$tables[]=$m[1]; }
    foreach($tables as $table) {
        $before=$wpdb->get_results("SELECT * FROM {$table}",ARRAY_A);
        idlm_engine_fixture_convert($table,'MyISAM');
        try {
            idlm_integration_assert_true(IDLM_Quotation_Schema::is_current(),'Installed markers unexpectedly changed');
            $result=IDLM_Quotation_Repository::begin_checked_transaction();
            idlm_integration_assert_same('idlm_quote_transaction_engine',$result->get_error_code());
            idlm_integration_assert_same($before,$wpdb->get_results("SELECT * FROM {$table}",ARRAY_A));
            idlm_integration_assert_same('MyISAM',$wpdb->get_var("SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='{$table}'"));
        } finally { $wpdb->query('ROLLBACK');idlm_engine_fixture_convert($table,'InnoDB'); }
        idlm_integration_assert_same(true,IDLM_Quotation_Repository::begin_checked_transaction());$wpdb->query('ROLLBACK');
    }
});

idlm_integration_test('T10-N-QUO007-failed-pins-metadata-reads-missing-tables-and-foreign-owners-never-acknowledge-writes', static function(): void {
    global $wpdb;
    foreach(array('idlm_quotation_engine_pin','idlm_quotation_write_engine') as $fault) {
        $result=idlm_quo_fault($fault,static function(){return IDLM_Quotation_Repository::begin_checked_transaction();});
        idlm_integration_assert_true(is_wp_error($result));
        idlm_integration_assert_same(true,IDLM_Quotation_Repository::begin_checked_transaction());$wpdb->query('ROLLBACK');
    }
    $table=$wpdb->prefix.'idlm_number_sequences';$wpdb->query("RENAME TABLE {$table} TO {$table}_held");
    try {
        $result=idlm_schema_filter(static function($sql){return $sql;},static function(){return IDLM_Quotation_Repository::reserve_quote_number(array());});
        idlm_integration_assert_true(is_wp_error($result));
    } finally { $wpdb->query('ROLLBACK');$wpdb->query("RENAME TABLE {$table}_held TO {$table}"); }
    $before=(int)$wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}idlm_leads");
    $wpdb->query('START TRANSACTION');idlm_lifecycle_fixture();
    $r=idlm_schema_filter(static function($sql){return $sql;},static function(){return IDLM_Quotation_Repository::begin_checked_transaction();});
    idlm_integration_assert_same('idlm_quote_transaction_owner',$r->get_error_code());
    idlm_integration_assert_same($before+1,(int)$wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}idlm_leads"));
    $wpdb->query('ROLLBACK');idlm_integration_assert_same($before,(int)$wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}idlm_leads"));
});

idlm_integration_test('T17-T20-N-QUO007-fresh-declarations-ignore-nontransactional-default-without-converting-existing-tables', static function(): void {
    global $wpdb;
    $default=$wpdb->get_var('SELECT @@SESSION.default_storage_engine');$prefix=$wpdb->prefix;$tables=array();
    try {
        $wpdb->query('SET SESSION default_storage_engine=MyISAM');
        $wpdb->prefix=$prefix.'quo007_';$statements=IDLM_Quotation_Schema::schema_statements();$wpdb->prefix=$prefix;
        foreach($statements as $sql){preg_match('/^CREATE TABLE ([a-zA-Z0-9_]+)/',$sql,$m);$tables[]=$m[1];dbDelta($sql);}
        idlm_schema_expect_ok(IDLM_Schema_Migration::verify($statements));
        idlm_integration_assert_same(10,count($tables));
        foreach($tables as $table){idlm_integration_assert_same('InnoDB',$wpdb->get_var("SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='{$table}'"));}
    } finally {
        $wpdb->prefix=$prefix;$wpdb->query($wpdb->prepare('SET SESSION default_storage_engine=%s',$default));
        foreach($tables as $table){$wpdb->query("DROP TABLE {$table}");}
    }
});

idlm_integration_test('T10-C-QUO007-independent-engine-ALTER-waits-for-verified-transaction-to-finish', static function(): void {
    global $wpdb;
    $other=new wpdb(DB_USER,DB_PASSWORD,DB_NAME,DB_HOST);$other->suppress_errors(true);$other->query('SET SESSION lock_wait_timeout=1');
    $table=$wpdb->prefix.'idlm_number_sequences';$before=$wpdb->get_results("SELECT * FROM {$table} ORDER BY id",ARRAY_A);
    try {
        $probe=idlm_lifecycle_fixture();
        $isolation=$wpdb->get_var('SELECT @@SESSION.transaction_isolation');
        idlm_integration_assert_same(true,IDLM_Quotation_Repository::begin_checked_transaction());
        idlm_integration_assert_same($isolation,$wpdb->get_var('SELECT @@SESSION.transaction_isolation'));
        $wpdb->get_var("SELECT first_name FROM {$wpdb->prefix}idlm_leads WHERE id={$probe}");
        $other->query("UPDATE {$wpdb->prefix}idlm_leads SET first_name='Synthetic later commit' WHERE id={$probe}");
        idlm_integration_assert_same('Synthetic later commit',$wpdb->get_var("SELECT first_name FROM {$wpdb->prefix}idlm_leads WHERE id={$probe}"),'Owned transaction retained a stale repeatable-read snapshot');
        idlm_integration_assert_same(false,$other->query("ALTER TABLE {$table} ENGINE=MyISAM"),'Engine changed after verification while transaction remained open');
        idlm_integration_assert_true(stripos($other->last_error,'lock wait timeout')!==false,'Expected actual metadata lock timeout: '.$other->last_error);
        $number=IDLM_Quotation_Repository::reserve_quote_number(array(),false);idlm_integration_assert_true(is_string($number));
        $wpdb->query('ROLLBACK');idlm_integration_assert_same($isolation,$wpdb->get_var('SELECT @@SESSION.transaction_isolation'));idlm_integration_assert_same($before,$wpdb->get_results("SELECT * FROM {$table} ORDER BY id",ARRAY_A));
        idlm_integration_assert_true(false!==$other->query("ALTER TABLE {$table} ENGINE=MyISAM"));
        idlm_integration_assert_true(is_wp_error(IDLM_Quotation_Repository::reserve_quote_number(array())));
    } finally { $wpdb->query('ROLLBACK');$other->query("ALTER TABLE {$table} ENGINE=InnoDB");$other->close(); }
    idlm_integration_assert_true(is_string(IDLM_Quotation_Repository::reserve_quote_number(array())));
});
