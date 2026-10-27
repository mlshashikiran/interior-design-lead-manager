<?php
/** QUO-010 exact historical state preservation. Builder: Shashi. */
if (!defined('ABSPATH') || strpos(DB_NAME, 'idlm_test_') !== 0) { exit(1); }

function idlm_history_fixture(string $status = 'Accepted'): array {
    global $wpdb;
    $fixture = idlm_classification_fixture();
    list($lead, $quote, $revision) = $fixture;
    $then = '2025-01-02 03:04:05';
    idlm_integration_assert_same(1, $wpdb->insert($wpdb->prefix.'idlm_quote_revisions', array('quote_id'=>$quote, 'revision_number'=>2, 'revision_name'=>wp_generate_uuid4(), 'status'=>'Accepted', 'customer_name'=>'Synthetic historical customer', 'grand_total'=>'125.00', 'created_at'=>$then, 'updated_at'=>$then, 'finalized_at'=>$then)));
    $accepted = (int) $wpdb->insert_id;
    idlm_schema_expect_ok(IDLM_Quotation_Repository::copy_revision_contents($revision, $accepted));
    idlm_integration_assert_same(1, $wpdb->insert($wpdb->prefix.'idlm_quote_payment_milestones', array('revision_id'=>$accepted, 'milestone_name'=>'Historical acceptance', 'amount_type'=>'percent', 'amount_value'=>'25.00', 'calculated_amount'=>'31.25', 'due_trigger'=>'Original contract', 'sort_order'=>0)));
    $wpdb->update($wpdb->prefix.'idlm_leads', array('quote_value'=>'380.00', 'quote_shared'=>1), array('id'=>$lead));
    $wpdb->update($wpdb->prefix.'idlm_quotes', array('status'=>$status, 'accepted_revision_id'=>$accepted, 'shared_at'=>'2025-01-03 04:05:06', 'accepted_at'=>'2025-01-04 05:06:07', 'rejected_at'=>'2025-01-05 06:07:08'), array('id'=>$quote));
    $wpdb->update($wpdb->prefix.'idlm_quote_revisions', array('status'=>$status, 'finalized_at'=>'2025-01-06 07:08:09'), array('id'=>$revision));
    IDLM_Quotation_Repository::insert_history($quote, $accepted, 'Shared', 'Accepted', 'Synthetic preserved commercial history');
    return $fixture;
}

function idlm_history_snapshot(array $fixture): array {
    global $wpdb;
    $quote = (int) $fixture[1];
    $snapshot = idlm_quo_snapshot($fixture);
    $snapshot['idlm_leads'] = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$wpdb->prefix}idlm_leads WHERE id=%d", $fixture[0]), ARRAY_A);
    $snapshot['idlm_quote_revisions'] = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}idlm_quote_revisions WHERE quote_id={$quote} ORDER BY id", ARRAY_A);
    foreach (array('idlm_quote_revision_items','idlm_quote_revision_sections','idlm_quote_payment_milestones') as $table) {
        $snapshot[$table] = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}{$table} WHERE revision_id IN (SELECT id FROM {$wpdb->prefix}idlm_quote_revisions WHERE quote_id={$quote}) ORDER BY id", ARRAY_A);
        idlm_integration_assert_same('', $wpdb->last_error);
    }
    return $snapshot;
}

idlm_integration_test('T10-T17-N-QUO010-upgrade-preserves-commercial-states-pointers-times-and-all-revisions', static function(): void {
    foreach (array('Shared','Accepted','Rejected','Cancelled','Canceled','Finalized','Legacy custom') as $status) {
        $fixture = idlm_history_fixture($status);
        $before = idlm_history_snapshot($fixture);
        update_option('idlm_quotation_schema_version', '2.5.2', false);
        delete_option('idlm_quotation_revision_lifecycle_migration_version');
        idlm_schema_expect_ok(IDLM_Quotations::install());
        idlm_schema_expect_ok(IDLM_Quotations::install());
        idlm_integration_assert_same($before, idlm_history_snapshot($fixture));
    }
});

idlm_integration_test('T17-N-QUO010-old-completed-receipts-do-not-authorize-loss-or-guess-invalid-acceptance-links', static function(): void {
    global $wpdb;
    foreach (array('2.4.0','2.5.0','2.5.1','2.5.2') as $version) {
        $fixture=idlm_history_fixture();$before=idlm_history_snapshot($fixture);
        update_option('idlm_quotation_schema_version',$version,false);
        update_option('idlm_quotation_revision_lifecycle_migration_version','1.0.0',false);
        idlm_schema_expect_ok(IDLM_Quotations::install());
        idlm_integration_assert_same($before,idlm_history_snapshot($fixture));
    }
    $fixture=idlm_history_fixture();$other=idlm_history_fixture();
    $wpdb->update($wpdb->prefix.'idlm_quotes',array('accepted_revision_id'=>$other[2]),array('id'=>$fixture[1]));
    $before=idlm_history_snapshot($fixture);delete_option('idlm_quotation_revision_lifecycle_migration_version');
    idlm_integration_assert_true(is_wp_error(IDLM_Quotation_Schema::normalize_revision_lifecycle()));
    idlm_integration_assert_same('',IDLM_Schema_Migration::version('idlm_quotation_revision_lifecycle_migration_version'));
    idlm_integration_assert_same($before,idlm_history_snapshot($fixture));
    $wpdb->update($wpdb->prefix.'idlm_quotes',array('accepted_revision_id'=>$fixture[2]),array('id'=>$fixture[1]));
    idlm_schema_expect_ok(IDLM_Quotations::install());
});

idlm_integration_test('T17-N-QUO010-lifecycle-read-marker-and-commit-failures-preserve-entire-commercial-aggregate',static function(): void {
    $fixture=idlm_history_fixture();$before=idlm_history_snapshot($fixture);
    foreach (array('read','marker','commit') as $fault) {
        delete_option('idlm_quotation_revision_lifecycle_migration_version');
        $filter=static function($sql) use ($fault) {
            $match=$fault==='read' ? strpos($sql,'idlm_quotation_lifecycle_integrity')!==false : ($fault==='commit' ? $sql==='COMMIT' : strpos($sql,'INSERT INTO')===0 && strpos($sql,'idlm_quotation_revision_lifecycle_migration_version')!==false);
            return $match ? 'SELECT idlm_synthetic_history_failure FROM idlm_absent_table' : $sql;
        };
        $result=idlm_schema_filter($filter,static function(){return IDLM_Quotation_Schema::normalize_revision_lifecycle();});
        idlm_integration_assert_true(is_wp_error($result));
        idlm_integration_assert_same('',IDLM_Schema_Migration::version('idlm_quotation_revision_lifecycle_migration_version'));
        idlm_integration_assert_same($before,idlm_history_snapshot($fixture));
        idlm_schema_expect_ok(IDLM_Quotation_Schema::normalize_revision_lifecycle());
        idlm_integration_assert_same($before,idlm_history_snapshot($fixture));
    }
});

idlm_integration_test('T17-C-QUO010-interrupted-and-competing-installers-never-rewrite-commercial-history',static function(): void {
    $fixture=idlm_history_fixture();$before=idlm_history_snapshot($fixture);
    update_option('idlm_quotation_schema_version','2.5.2',false);
    $r=idlm_integration_worker(array('quotation-schema-interrupt'));
    idlm_integration_assert_same(77,$r['status'],$r['stderr']);
    idlm_integration_assert_same('2.5.2',IDLM_Schema_Migration::version('idlm_quotation_schema_version'));
    idlm_integration_assert_same($before,idlm_history_snapshot($fixture));
    @unlink(WP_CONTENT_DIR.'/lead-barrier');
    foreach(idlm_lifecycle_pair(array('quotation-migration-race'),array('quotation-migration-race')) as $r) { idlm_integration_assert_same(0,$r['status'],$r['stderr']); }
    idlm_integration_assert_same($before,idlm_history_snapshot($fixture));
});

idlm_integration_test('T10-N-QUO010-read-only-evidence-and-query-failures-do-not-block-ordinary-revision-history',static function(): void {
    global $wpdb;
    $fixture=idlm_classification_fixture();
    $quote=IDLM_Quotation_Repository::get_quote($fixture[1]);$revision=IDLM_Quotation_Repository::get_revision($fixture[2]);
    idlm_integration_assert_same(true,IDLM_Quotation_Policy::is_editable_draft($quote,$revision));
    foreach (array('wp_idlm_quote_status_history','wp_idlm_quote_revisions WHERE quote_id') as $table) {
        $result=idlm_quo_fault('SELECT COUNT(*) FROM '.$table,static function() use ($quote,$revision){return IDLM_Quotation_Policy::is_editable_draft($quote,$revision);});
        idlm_integration_assert_same(false,$result);
    }
    IDLM_Quotation_Repository::insert_history($fixture[1],$fixture[2],'Shared','Accepted','Synthetic prior evidence');
    idlm_integration_assert_same(false,IDLM_Quotation_Policy::is_editable_draft($quote,$revision));
    $before=idlm_history_snapshot($fixture);
    idlm_schema_expect_ok(IDLM_Quotations::install());
    idlm_integration_assert_same($before,idlm_history_snapshot($fixture));
    idlm_integration_assert_same(false,IDLM_Quotation_Policy::is_editable_draft($quote,$revision));
});

idlm_integration_test('T17-N-QUO010-legacy-shared-intake-retains-shared-flag-and-refuses-historical-pointer-guessing',static function(): void {
    global $wpdb;
    $lead=idlm_lifecycle_fixture(array('quote_value'=>1000,'quote_shared'=>1));
    delete_option('idlm_legacy_quote_migration_version');
    idlm_schema_expect_ok(IDLM_Quotation_Schema::migrate_legacy_quotes());
    $quote=IDLM_Quotation_Repository::get_quote_for_lead($lead);$revision=IDLM_Quotation_Repository::get_revision($quote->current_revision_id);
    idlm_integration_assert_same('Shared',$quote->status);idlm_integration_assert_same('Shared',$revision->status);
    idlm_integration_assert_same(null,$quote->shared_at);idlm_integration_assert_same(null,$revision->finalized_at);
    idlm_integration_assert_same('1000.00',$revision->grand_total);
    $fixture=array($lead,(int)$quote->id,(int)$revision->id);
    $wpdb->update($wpdb->prefix.'idlm_quotes',array('current_revision_id'=>0),array('id'=>$quote->id));
    $before=idlm_history_snapshot($fixture);delete_option('idlm_legacy_quote_migration_version');
    idlm_integration_assert_true(is_wp_error(IDLM_Quotation_Schema::migrate_legacy_quotes()));
    idlm_integration_assert_same($before,idlm_history_snapshot($fixture));
    $wpdb->update($wpdb->prefix.'idlm_quotes',array('current_revision_id'=>$revision->id),array('id'=>$quote->id));
    idlm_schema_expect_ok(IDLM_Quotations::install());
});

idlm_integration_test('T16-T17-N-QUO010-2.5.2-backup-roundtrip-and-recovery-retain-commercial-state-exactly',static function(): void {
    idlm_native_backup_fixture();$fixture=idlm_history_fixture();$before=idlm_history_snapshot($fixture);
    $payload=idlm_native_backup_call('snapshot');idlm_integration_assert_true(is_array($payload));$payload['schema']['quotation']='2.5.2';unset($payload['schema']['columns']['idlm_quote_revisions']['document_terms']);foreach($payload['tables']['idlm_quote_revisions'] as &$row){unset($row['document_terms']);}unset($row);
    idlm_integration_assert_same(true,idlm_native_backup_call('restore_payload',$payload));
    idlm_schema_expect_ok(IDLM_Quotations::install());
    idlm_integration_assert_same($before,idlm_history_snapshot($fixture));
    idlm_integration_assert_same(true,idlm_native_backup_call('restore_payload',$payload));
    idlm_integration_assert_same($before,idlm_history_snapshot($fixture));
});
