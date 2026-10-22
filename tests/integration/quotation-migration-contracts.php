<?php
/** QUO-004 real DDL, data rollback, marker and retry contracts. Builder: Shashi. */
if (!defined('ABSPATH') || strpos(DB_NAME, 'idlm_test_') !== 0) { exit(1); }

function idlm_quo_migration_fixture(): array {
    global $wpdb;
    $lead = idlm_lifecycle_fixture(); $now = current_time('mysql');
    $number = 'M-' . wp_generate_uuid4();
    idlm_integration_assert_same(1, $wpdb->insert($wpdb->prefix.'idlm_quotes', array('lead_id'=>$lead,'quote_number'=>$number,'title'=>'Synthetic migration','created_by'=>0,'created_at'=>$now,'updated_at'=>$now)));
    $quote = (int) $wpdb->insert_id;
    idlm_integration_assert_same(1, $wpdb->insert($wpdb->prefix.'idlm_quote_revisions', array('quote_id'=>$quote,'revision_name'=>$number.'-R01','customer_name'=>'','created_by'=>0,'created_at'=>$now,'updated_by'=>0,'updated_at'=>$now)));
    $revision = (int) $wpdb->insert_id;
    idlm_integration_assert_same(1, $wpdb->update($wpdb->prefix.'idlm_quotes',array('current_revision_id'=>$revision),array('id'=>$quote)));
    idlm_integration_assert_same(1, $wpdb->insert($wpdb->prefix.'idlm_quote_revision_items',array('revision_id'=>$revision,'description'=>'Synthetic historical item','item_name'=>'Historical item','is_wood'=>1)));
    return array($lead,$quote,$revision);
}
function idlm_quo_snapshot(array $fixture): array {
    global $wpdb;
    list($lead,$quote,$revision) = $fixture; $snapshot=array();
    foreach(array('idlm_quotes'=>"id={$quote}",'idlm_quote_revisions'=>"id={$revision}",'idlm_quote_revision_items'=>"revision_id={$revision}",'idlm_quote_revision_sections'=>"revision_id={$revision}",'idlm_quote_status_history'=>"quote_id={$quote}") as $suffix=>$where) {
        $snapshot[$suffix]=$wpdb->get_results("SELECT * FROM {$wpdb->prefix}{$suffix} WHERE {$where} ORDER BY id",ARRAY_A);
        idlm_integration_assert_same('', $wpdb->last_error);
    }
    return $snapshot;
}
function idlm_quo_pending(string $marker): void { delete_option($marker); wp_cache_flush(); }
function idlm_quo_fault(string $needle, callable $operation) {
    return idlm_schema_filter(static function($sql) use($needle) { return strpos($sql,$needle)!==false ? 'SELECT idlm_synthetic_migration_fault FROM idlm_absent_table' : $sql; },$operation);
}

idlm_integration_test('T17-N-QUO004-physical manifest verifies ten InnoDB tables and independent keys', static function(): void {
    global $wpdb;
    idlm_schema_expect_ok(IDLM_Quotations::install());
    idlm_schema_expect_ok(IDLM_Schema_Migration::verify(IDLM_Quotation_Schema::schema_statements()));
    idlm_integration_assert_same(10, count(IDLM_Quotation_Schema::schema_statements()));
    idlm_integration_assert_same(array('quote_id','revision_number'),$wpdb->get_col("SHOW INDEX FROM {$wpdb->prefix}idlm_quote_revisions WHERE Key_name='quote_revision'",4));
    idlm_integration_assert_same('InnoDB',$wpdb->get_var("SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='{$wpdb->prefix}idlm_quote_revisions'"));
});

foreach(array('column','index') as $fault) {
    idlm_integration_test('T17-N-QUO004-denied-'.$fault.'-DDL-preserves-pending-version-and-retry-repairs',static function() use($fault): void {
        global $wpdb;
        update_option('idlm_quotation_schema_version','2.5.0',false);
        $table=$wpdb->prefix.'idlm_quote_revisions';
        idlm_integration_assert_true(false!==$wpdb->query($fault==='column'?"ALTER TABLE {$table} DROP COLUMN revision_reason":"ALTER TABLE {$table} DROP INDEX save_request_key"));
        $needle=$fault==='column'?'ADD COLUMN revision_reason':'ADD UNIQUE KEY `save_request_key`';
        $seen=0;
        $filter=static function($sql) use($table,$fault,&$seen) {
            if(strpos($sql,'ALTER TABLE '.$table)===0 && strpos($sql,$fault==='column'?'revision_reason':'save_request_key')!==false) {$seen++;return 'ALTER TABLE '.$table.' IDLM_SYNTHETIC_DDL_FAILURE';}
            return $sql;
        };
        $result=idlm_schema_filter($filter,static function(){return IDLM_Quotations::install();});
        idlm_integration_assert_true($seen>0);idlm_integration_assert_true(is_wp_error($result));
        idlm_integration_assert_same('2.5.0',IDLM_Schema_Migration::version('idlm_quotation_schema_version'));
        idlm_schema_expect_ok(IDLM_Quotations::install());
    });
}

idlm_integration_test('T17-N-QUO004-MyISAM-refused-without-conversion-or-data-loss',static function(): void {
    global $wpdb;
    $fixture=idlm_quo_migration_fixture();$before=idlm_quo_snapshot($fixture);
    update_option('idlm_quotation_schema_version','2.5.0',false);
    $table=$wpdb->prefix.'idlm_quote_revision_items';$wpdb->query("ALTER TABLE {$table} ENGINE=MyISAM");
    try {
        idlm_integration_assert_true(is_wp_error(IDLM_Quotations::install()));
        idlm_integration_assert_same('2.5.0',IDLM_Schema_Migration::version('idlm_quotation_schema_version'));
        idlm_integration_assert_same($before,idlm_quo_snapshot($fixture));
        idlm_integration_assert_same('MyISAM',$wpdb->get_var("SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='{$table}'"));
    } finally { $wpdb->query("ALTER TABLE {$table} ENGINE=InnoDB"); }
    idlm_schema_expect_ok(IDLM_Quotations::install());
});

foreach(array('snapshot_read','snapshot_update','section_lookup','section_insert','item_bind','marker','COMMIT') as $fault) {
    idlm_integration_test('T17-N-QUO004-snapshot-'.$fault.'-rolls-back-and-retries-without-duplicate-sections',static function() use($fault): void {
        global $wpdb;
        $fixture=idlm_quo_migration_fixture();$before=idlm_quo_snapshot($fixture);
        $marker='idlm_quotation_snapshot_migration_version';idlm_quo_pending($marker);
        $needles=array('snapshot_read'=>'SELECT r.id, r.customer_name','snapshot_update'=>"UPDATE `{$wpdb->prefix}idlm_quote_revisions`",'section_lookup'=>"SELECT id FROM {$wpdb->prefix}idlm_quote_revision_sections WHERE revision_id",'section_insert'=>"INSERT INTO `{$wpdb->prefix}idlm_quote_revision_sections`",'item_bind'=>"UPDATE {$wpdb->prefix}idlm_quote_revision_items SET section_id",'marker'=>"'{$marker}'",'COMMIT'=>'COMMIT');
        // A marker fault must reject the INSERT, not the preliminary marker read.
        $filter=static function($sql) use($fault,$marker,$needles) {
            $match=$fault==='marker' ? (strpos($sql,'INSERT INTO')===0 && strpos($sql,$marker)!==false) : strpos($sql,$needles[$fault])!==false;
            return $match?'SELECT idlm_synthetic_migration_fault FROM idlm_absent_table':$sql;
        };
        $result=idlm_schema_filter($filter,static function(){return IDLM_Quotation_Schema::backfill_revision_snapshots_and_sections();});
        idlm_integration_assert_true(is_wp_error($result));
        idlm_integration_assert_same('',IDLM_Schema_Migration::version($marker));
        idlm_integration_assert_same($before,idlm_quo_snapshot($fixture));
        idlm_schema_expect_ok(IDLM_Quotation_Schema::backfill_revision_snapshots_and_sections());
        $after=idlm_quo_snapshot($fixture);
        idlm_integration_assert_same(1,count($after['idlm_quote_revision_sections']));
        idlm_integration_assert_true((int)$after['idlm_quote_revision_items'][0]['section_id']>0);
        idlm_integration_assert_same('Synthetic atomic lead',$after['idlm_quote_revisions'][0]['customer_name']);
        idlm_schema_expect_ok(IDLM_Quotation_Schema::backfill_revision_snapshots_and_sections());
        idlm_integration_assert_same($after,idlm_quo_snapshot($fixture));
    });
}

idlm_integration_test('T17-N-QUO004-current-but-falsely-completed-snapshot-marker-is-rechecked',static function(): void {
    $fixture=idlm_quo_migration_fixture();
    update_option('idlm_quotation_snapshot_migration_version',IDLM_Quotation_Schema::SNAPSHOT_MIGRATION_VERSION,false);
    idlm_schema_expect_ok(IDLM_Quotations::install());
    idlm_integration_assert_same(1,count(idlm_quo_snapshot($fixture)['idlm_quote_revision_sections']));
});

idlm_integration_test('T17-N-QUO004-lifecycle-verification-read-failure-preserves-complete-original-state',static function(): void {
    global $wpdb;
    $fixture=idlm_quo_migration_fixture();list($lead,$quote,$revision)=$fixture;
    $wpdb->update($wpdb->prefix.'idlm_quotes',array('status'=>'Shared','shared_at'=>current_time('mysql')),array('id'=>$quote));
    $wpdb->update($wpdb->prefix.'idlm_quote_revisions',array('status'=>'Shared'),array('id'=>$revision));
    $before=idlm_quo_snapshot($fixture);idlm_quo_pending('idlm_quotation_revision_lifecycle_migration_version');
    $r=idlm_quo_fault('idlm_quotation_lifecycle_integrity',static function(){return IDLM_Quotation_Schema::normalize_revision_lifecycle();});
    idlm_integration_assert_true(is_wp_error($r));idlm_integration_assert_same($before,idlm_quo_snapshot($fixture));
    idlm_integration_assert_same('',IDLM_Schema_Migration::version('idlm_quotation_revision_lifecycle_migration_version'));
    idlm_schema_expect_ok(IDLM_Quotation_Schema::normalize_revision_lifecycle());
    idlm_integration_assert_same($before,idlm_quo_snapshot($fixture));
});

foreach(array('read','quotes','revisions','pointer','history') as $fault) {
    idlm_integration_test('T17-N-QUO004-legacy-'.$fault.'-failure-preserves-numbering-and-retry-creates-one-aggregate',static function() use($fault): void {
        global $wpdb;
        $lead=idlm_lifecycle_fixture(array('quote_value'=>125000));
        idlm_quo_pending('idlm_legacy_quote_migration_version');
        $before=$wpdb->get_results("SELECT * FROM {$wpdb->prefix}idlm_number_sequences ORDER BY id",ARRAY_A);
        $needles=array('read'=>'SELECT id, first_name, last_name, phone','quotes'=>"INSERT INTO `{$wpdb->prefix}idlm_quotes`",'revisions'=>"INSERT INTO `{$wpdb->prefix}idlm_quote_revisions`",'pointer'=>"UPDATE `{$wpdb->prefix}idlm_quotes`",'history'=>"INSERT INTO `{$wpdb->prefix}idlm_quote_status_history`");
        $r=idlm_quo_fault($needles[$fault],static function(){return IDLM_Quotation_Schema::migrate_legacy_quotes();});
        idlm_integration_assert_true(is_wp_error($r));
        idlm_integration_assert_same(0,(int)$wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}idlm_quotes WHERE lead_id={$lead}"));
        idlm_integration_assert_same($before,$wpdb->get_results("SELECT * FROM {$wpdb->prefix}idlm_number_sequences ORDER BY id",ARRAY_A));
        idlm_integration_assert_same('',IDLM_Schema_Migration::version('idlm_legacy_quote_migration_version'));
        idlm_schema_expect_ok(IDLM_Quotation_Schema::migrate_legacy_quotes());
        idlm_schema_expect_ok(IDLM_Quotation_Schema::migrate_legacy_quotes());
        idlm_integration_assert_same(1,(int)$wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}idlm_quotes q INNER JOIN {$wpdb->prefix}idlm_quote_revisions r ON r.id=q.current_revision_id AND r.quote_id=q.id WHERE q.lead_id={$lead}"));
    });
}

idlm_integration_test('T20-N-QUO004-foreign-transaction-and-competing-installer-refused-before-DDL',static function(): void {
    global $wpdb,$config;
    $wpdb->query('START TRANSACTION');$lead=idlm_lifecycle_fixture();
    idlm_integration_assert_true(is_wp_error(IDLM_Quotations::install()));$wpdb->query('ROLLBACK');
    idlm_integration_assert_same(null,$wpdb->get_var("SELECT id FROM {$wpdb->prefix}idlm_leads WHERE id={$lead}"));
    $other=new wpdb($config['db_user'],$config['db_password'],$config['db_name'],$config['db_host']);
    $name=IDLM_Schema_Migration::lock_name();idlm_integration_assert_same('1',(string)$other->get_var($other->prepare('SELECT GET_LOCK(%s,0)',$name)));
    try { idlm_integration_assert_same('idlm_schema_busy',IDLM_Quotations::install()->get_error_code()); }
    finally {$other->get_var($other->prepare('SELECT RELEASE_LOCK(%s)',$name));$other->close();}
    idlm_schema_expect_ok(IDLM_Quotations::install());
});

idlm_integration_test('T17-N-QUO004-denied-table-creation-remains-pending-until-physical-retry',static function(): void {
    global $wpdb;
    $table=$wpdb->prefix.'idlm_quote_item_units';
    update_option('idlm_quotation_schema_version','2.5.0',false);
    $wpdb->query("DROP TABLE {$table}");
    $r=idlm_quo_fault('CREATE TABLE '.$table,static function(){return IDLM_Quotations::install();});
    idlm_integration_assert_true(is_wp_error($r));
    idlm_integration_assert_same('2.5.0',IDLM_Schema_Migration::version('idlm_quotation_schema_version'));
    idlm_schema_expect_ok(IDLM_Quotations::install());
    idlm_integration_assert_same(1,(int)$wpdb->get_var("SELECT COUNT(*) FROM {$table} WHERE unit_name='Nos'"));
});

idlm_integration_test('T17-N-QUO004-final-schema-marker-write-failure-retains-completed-substeps-and-retries',static function(): void {
    update_option('idlm_quotation_schema_version','2.5.0',false);
    $filter=static function($sql) {return strpos($sql,'UPDATE')===0 && strpos($sql,'idlm_quotation_schema_version')!==false ? 'SELECT idlm_synthetic_marker_failure FROM idlm_absent_table' : $sql;};
    idlm_integration_assert_true(is_wp_error(idlm_schema_filter($filter,static function(){return IDLM_Quotations::install();})));
    idlm_integration_assert_same('2.5.0',IDLM_Schema_Migration::version('idlm_quotation_schema_version'));
    idlm_integration_assert_same(IDLM_Quotation_Schema::SNAPSHOT_MIGRATION_VERSION,IDLM_Schema_Migration::version('idlm_quotation_snapshot_migration_version'));
    idlm_schema_expect_ok(IDLM_Quotations::install());
});

idlm_integration_test('T17-C-QUO004-process-death-before-backfill-commit-rolls-back-data-and-marker',static function(): void {
    $fixture=idlm_quo_migration_fixture();$before=idlm_quo_snapshot($fixture);
    idlm_quo_pending('idlm_quotation_snapshot_migration_version');
    $r=idlm_integration_worker(array('quotation-migration-interrupt'));
    idlm_integration_assert_same(77,$r['status'],$r['stderr']);
    idlm_integration_assert_same($before,idlm_quo_snapshot($fixture));
    idlm_integration_assert_same('',IDLM_Schema_Migration::version('idlm_quotation_snapshot_migration_version'));
    idlm_schema_expect_ok(IDLM_Quotation_Schema::backfill_revision_snapshots_and_sections());
});

idlm_integration_test('T17-C-QUO004-two-installers-converge-on-one-legacy-aggregate-and-completion',static function(): void {
    global $wpdb;
    $lead=idlm_lifecycle_fixture(array('quote_value'=>250000));
    update_option('idlm_quotation_schema_version','2.5.0',false);
    $results=idlm_lifecycle_pair(array('quotation-migration-race'),array('quotation-migration-race'));
    foreach($results as $r) {idlm_integration_assert_same(0,$r['status'],$r['stderr']);idlm_integration_assert_same('complete',$r['stdout']);}
    idlm_integration_assert_same(1,(int)$wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}idlm_quotes q INNER JOIN {$wpdb->prefix}idlm_quote_revisions r ON r.id=q.current_revision_id AND r.quote_id=q.id WHERE q.lead_id={$lead}"));
    wp_cache_flush();idlm_integration_assert_same(true,IDLM_Quotation_Schema::is_current());
});

idlm_integration_test('T20-N-QUO004-uncommitted-cached-marker-cannot-acknowledge-boot-completion',static function(): void {
    update_option('idlm_quotation_schema_version','2.5.0',false);wp_cache_flush();
    wp_cache_set('idlm_quotation_schema_version',IDLM_Quotation_Schema::VERSION,'options');
    idlm_integration_assert_same(IDLM_Quotation_Schema::VERSION,get_option('idlm_quotation_schema_version'));
    idlm_integration_assert_same(false,IDLM_Quotation_Schema::is_current());
    wp_cache_flush();idlm_schema_expect_ok(IDLM_Quotations::install());
});

idlm_integration_test('T17-C-QUO004-process-death-before-final-marker-commit-remains-pending',static function(): void {
    update_option('idlm_quotation_schema_version','2.5.0',false);
    $r=idlm_integration_worker(array('quotation-schema-interrupt'));
    idlm_integration_assert_same(77,$r['status'],$r['stderr']);
    idlm_integration_assert_same('2.5.0',IDLM_Schema_Migration::version('idlm_quotation_schema_version'));
    idlm_integration_assert_same(false,IDLM_Quotation_Schema::is_current());
    idlm_schema_expect_ok(IDLM_Quotations::install());
});

idlm_integration_test('T16-N-QUO004-complete-2.5.0-backup-restores-existing-values-with-additive-unverified-fields',static function(): void {
    $payload=idlm_native_backup_fixture();idlm_integration_assert_true(!is_wp_error($payload));
    $payload['schema']['quotation']='2.5.0';unset($payload['schema']['columns']['idlm_quote_revisions']['document_terms']);foreach($payload['tables']['idlm_quote_revisions'] as &$row){unset($row['document_terms']);}unset($row);
    unset($payload['schema']['columns']['idlm_quote_revision_items']['classification_source']);foreach($payload['tables']['idlm_quote_revision_items'] as &$row){unset($row['classification_source']);}unset($row);
    idlm_integration_assert_same(true,idlm_native_backup_call('restore_payload',$payload));
    $after=idlm_native_backup_call('snapshot');
    foreach($after['tables']['idlm_quote_revisions'] as &$row){idlm_integration_assert_same(null,$row['document_terms']);unset($row['document_terms']);}unset($row);
    foreach($after['tables']['idlm_quote_revision_items'] as &$row){idlm_integration_assert_same('legacy_unverified',$row['classification_source']);unset($row['classification_source']);}unset($row);
    idlm_integration_assert_same($payload['tables'],$after['tables']);
});

idlm_integration_test('T20-N-QUO004-explicit-reinstall-failure-is-pending-at-current-version-until-retry',static function(): void {
    idlm_schema_expect_ok(IDLM_Quotations::install());
    $r=idlm_quo_fault('INSERT IGNORE INTO wp_idlm_quote_item_units',static function(){return IDLM_Quotations::install();});
    idlm_integration_assert_true(is_wp_error($r));
    idlm_integration_assert_same(IDLM_Quotation_Schema::VERSION,IDLM_Schema_Migration::version('idlm_quotation_schema_version'));
    idlm_integration_assert_same('pending',IDLM_Schema_Migration::version(IDLM_Quotation_Schema::STATUS_OPTION));
    idlm_integration_assert_same(false,IDLM_Quotation_Schema::is_current());
    idlm_schema_expect_ok(IDLM_Quotations::install());
    idlm_integration_assert_same('ready',IDLM_Schema_Migration::version(IDLM_Quotation_Schema::STATUS_OPTION));
});

idlm_integration_test('T17-N-QUO004-verification-only-upgrade-preserves-known-item-classification',static function(): void {
    global $wpdb;
    $fixture=idlm_quo_migration_fixture();
    idlm_schema_expect_ok(IDLM_Quotation_Schema::backfill_revision_snapshots_and_sections());
    $now=current_time('mysql');
    idlm_integration_assert_same(1,$wpdb->insert($wpdb->prefix.'idlm_quote_catalogue_items',array('item_code'=>'V-'.wp_generate_uuid4(),'item_name'=>'Synthetic current nonwood catalogue','is_wood'=>0,'created_by'=>0,'created_at'=>$now,'updated_at'=>$now)));
    $catalogue=(int)$wpdb->insert_id;
    $wpdb->update($wpdb->prefix.'idlm_quote_revision_items',array('catalogue_item_id'=>$catalogue,'is_wood'=>1),array('revision_id'=>$fixture[2]));
    $before=idlm_quo_snapshot($fixture);
    update_option('idlm_quotation_schema_version','2.5.0',false);
    idlm_schema_expect_ok(IDLM_Quotations::install());
    idlm_schema_expect_ok(IDLM_Quotations::install());
    idlm_integration_assert_same($before,idlm_quo_snapshot($fixture));
});
