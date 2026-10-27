<?php
/** QUO-005 historical classifications, additive upgrades and recovery. Builder: Shashi. */
if (!defined('ABSPATH') || strpos(DB_NAME, 'idlm_test_') !== 0) { exit(1); }

function idlm_classification_fixture(): array {
    global $wpdb;
    $fixture = idlm_quo_migration_fixture();
    idlm_schema_expect_ok(IDLM_Quotation_Schema::backfill_revision_snapshots_and_sections());
    $now = current_time('mysql');
    $section = $wpdb->get_var($wpdb->prepare("SELECT id FROM {$wpdb->prefix}idlm_quote_revision_sections WHERE revision_id=%d", $fixture[2]));
    foreach (array(0, 1) as $wood) {
        idlm_integration_assert_same(1, $wpdb->insert($wpdb->prefix.'idlm_quote_catalogue_items', array('item_code'=>wp_generate_uuid4(), 'item_name'=>'Synthetic changed catalogue', 'is_wood'=>$wood, 'created_at'=>$now, 'updated_at'=>$now)));
        $catalogue = $wpdb->insert_id;
        idlm_integration_assert_same(1, $wpdb->insert($wpdb->prefix.'idlm_quote_revision_items', array('revision_id'=>$fixture[2], 'section_id'=>$section, 'catalogue_item_id'=>$catalogue, 'is_wood'=>$wood, 'description'=>'Captured '.$wood, 'quantity'=>'2.000', 'unit_rate'=>'100.00', 'line_subtotal'=>'200.00', 'line_total'=>'200.00')));
        idlm_integration_assert_same(1, $wpdb->update($wpdb->prefix.'idlm_quote_catalogue_items', array('is_wood'=>1-$wood, 'status'=>'inactive', 'default_rate'=>'999.00'), array('id'=>$catalogue)));
    }
    idlm_integration_assert_same(1, $wpdb->update($wpdb->prefix.'idlm_quote_revisions', array('subtotal'=>'400.00', 'wooden_subtotal'=>'200.00', 'wooden_discount_percent'=>'10.00', 'wooden_discount_amount'=>'20.00', 'other_subtotal'=>'200.00', 'grand_total'=>'380.00'), array('id'=>$fixture[2])));
    return $fixture;
}

idlm_integration_test('T11-T17-N-QUO005-pre-2.5-upgrade-and-reinstall-preserve-every-item-and-monetary-field', static function(): void {
    $fixture = idlm_classification_fixture();
    $before = idlm_quo_snapshot($fixture);
    update_option('idlm_quotation_schema_version', '2.4.0', false);
    idlm_schema_expect_ok(IDLM_Quotations::install());
    idlm_schema_expect_ok(IDLM_Quotations::install());
    idlm_integration_assert_same($before, idlm_quo_snapshot($fixture));
});

/** Compare all pre-existing fields; the new provenance column is additive. */
function idlm_classification_without_source(array $snapshot): array {
    foreach ($snapshot['idlm_quote_revision_items'] as &$row) { unset($row['classification_source']); }
    unset($row);
    return $snapshot;
}

idlm_integration_test('T11-T17-N-QUO005-old-physical-inventories-preserve-zero-one-custom-optional-header-and-detached-items', static function(): void {
    global $wpdb;
    foreach (array(false, '2.4.0', '2.5.0', '2.5.1') as $version) {
        $fixture = idlm_classification_fixture();
        $section = $wpdb->get_var($wpdb->prepare("SELECT id FROM {$wpdb->prefix}idlm_quote_revision_sections WHERE revision_id=%d", $fixture[2]));
        foreach (array(array('is_wood'=>0), array('is_wood'=>1,'is_optional'=>1), array('row_type'=>'header'), array('catalogue_item_id'=>999999,'is_wood'=>1)) as $fields) {
            idlm_integration_assert_same(1, $wpdb->insert($wpdb->prefix.'idlm_quote_revision_items', array_merge(array('revision_id'=>$fixture[2], 'section_id'=>$section, 'description'=>'Synthetic legacy variant'), $fields)));
        }
        idlm_integration_assert_true(false !== $wpdb->query("ALTER TABLE {$wpdb->prefix}idlm_quote_revision_items DROP COLUMN classification_source"));
        $before = idlm_quo_snapshot($fixture);
        if ($version) { update_option('idlm_quotation_schema_version', $version, false); }
        else { delete_option('idlm_quotation_schema_version'); }
        idlm_schema_expect_ok(IDLM_Quotations::install());
        idlm_schema_expect_ok(IDLM_Quotations::install());
        $after = idlm_quo_snapshot($fixture);
        idlm_integration_assert_same($before, idlm_classification_without_source($after));
        foreach ($after['idlm_quote_revision_items'] as $row) { idlm_integration_assert_same('legacy_unverified', $row['classification_source']); }
        $posted = array('wooden_discount_percent'=>'10.00');
        idlm_integration_assert_same(IDLM_Quotation_Calculator::calculate_totals($before['idlm_quote_revision_items'], $posted), IDLM_Quotation_Calculator::calculate_totals($after['idlm_quote_revision_items'], $posted));
    }
});

idlm_integration_test('T17-N-QUO005-missing-old-classification-column-is-unverified-without-catalogue-inference', static function(): void {
    global $wpdb;
    $fixture = idlm_classification_fixture();
    $table = $wpdb->prefix.'idlm_quote_revision_items';
    idlm_integration_assert_true(false !== $wpdb->query("ALTER TABLE {$table} DROP COLUMN is_wood, DROP COLUMN classification_source"));
    $before = idlm_quo_snapshot($fixture);
    update_option('idlm_quotation_schema_version', '2.4.0', false);
    idlm_schema_expect_ok(IDLM_Quotations::install());
    idlm_schema_expect_ok(IDLM_Quotations::install());
    $after = idlm_quo_snapshot($fixture);
    foreach ($after['idlm_quote_revision_items'] as &$row) {
        idlm_integration_assert_same('0', $row['is_wood']);
        idlm_integration_assert_same('legacy_unverified', $row['classification_source']);
        unset($row['is_wood'], $row['classification_source']);
    }
    unset($row);
    idlm_integration_assert_same($before, $after);
});

idlm_integration_test('T10-T11-N-QUO005-new-submissions-and-revision-copies-retain-source-and-financials', static function(): void {
    global $wpdb;
    $fixture = idlm_classification_fixture();
    $items = IDLM_Quotation_Calculator::parse_items(array(
        array('description'=>'Captured wood','is_wood'=>'1','quantity'=>2,'unit_rate'=>100,'classification_source'=>'forged'),
        array('description'=>'Captured nonwood','is_wood'=>'0','quantity'=>1,'unit_rate'=>50),
        array('description'=>'Custom default','quantity'=>1,'unit_rate'=>25),
    ));
    idlm_schema_expect_ok(IDLM_Quotation_Repository::replace_revision_contents($fixture[2], IDLM_Quotation_Calculator::parse_sections(array()), $items));
    $now = current_time('mysql');
    idlm_integration_assert_same(1, $wpdb->insert($wpdb->prefix.'idlm_quote_revisions', array('quote_id'=>$fixture[1], 'revision_number'=>2, 'revision_name'=>wp_generate_uuid4(), 'status'=>'Superseded','created_at'=>$now,'updated_at'=>$now,'finalized_at'=>$now)));
    $copy = (int) $wpdb->insert_id;
    idlm_schema_expect_ok(IDLM_Quotation_Repository::copy_revision_contents($fixture[2], $copy));
    $copied = $wpdb->get_results($wpdb->prepare("SELECT is_wood, classification_source, quantity, unit_rate, line_subtotal, line_total FROM {$wpdb->prefix}idlm_quote_revision_items WHERE revision_id=%d ORDER BY id", $copy), ARRAY_A);
    $source = $wpdb->get_results($wpdb->prepare("SELECT is_wood, classification_source, quantity, unit_rate, line_subtotal, line_total FROM {$wpdb->prefix}idlm_quote_revision_items WHERE revision_id=%d ORDER BY id", $fixture[2]), ARRAY_A);
    idlm_integration_assert_same(array('submitted','submitted','default_nonwood'), array_column($copied,'classification_source'));
    idlm_integration_assert_same($source, $copied);
    $before = idlm_quo_snapshot($fixture);
    idlm_schema_expect_ok(IDLM_Quotations::install());
    idlm_integration_assert_same($before, idlm_quo_snapshot($fixture));
    idlm_integration_assert_same($copied, $wpdb->get_results($wpdb->prepare("SELECT is_wood, classification_source, quantity, unit_rate, line_subtotal, line_total FROM {$wpdb->prefix}idlm_quote_revision_items WHERE revision_id=%d ORDER BY id", $copy), ARRAY_A));
});

idlm_integration_test('T17-N-QUO005-denied-provenance-DDL-retains-history-and-retries', static function(): void {
    global $wpdb;
    $fixture = idlm_classification_fixture();
    $table = $wpdb->prefix.'idlm_quote_revision_items';
    $wpdb->query("ALTER TABLE {$table} DROP COLUMN classification_source");
    $before = idlm_quo_snapshot($fixture);
    update_option('idlm_quotation_schema_version', '2.5.1', false);
    $result = idlm_quo_fault('ADD COLUMN classification_source', static function() { return IDLM_Quotations::install(); });
    idlm_integration_assert_true(is_wp_error($result));
    idlm_integration_assert_same('2.5.1', IDLM_Schema_Migration::version('idlm_quotation_schema_version'));
    idlm_integration_assert_same(false, IDLM_Quotation_Schema::is_current());
    idlm_integration_assert_same($before, idlm_quo_snapshot($fixture));
    idlm_schema_expect_ok(IDLM_Quotations::install());
    idlm_integration_assert_same($before, idlm_classification_without_source(idlm_quo_snapshot($fixture)));
});

idlm_integration_test('T17-C-QUO005-process-death-after-additive-DDL-preserves-provenance-on-retry', static function(): void {
    global $wpdb;
    $fixture = idlm_classification_fixture();
    $wpdb->query("ALTER TABLE {$wpdb->prefix}idlm_quote_revision_items DROP COLUMN classification_source");
    $before = idlm_quo_snapshot($fixture);
    update_option('idlm_quotation_schema_version', '2.5.1', false);
    $r = idlm_integration_worker(array('quotation-schema-interrupt'));
    idlm_integration_assert_same(77, $r['status'], $r['stderr']);
    idlm_integration_assert_same('2.5.1', IDLM_Schema_Migration::version('idlm_quotation_schema_version'));
    idlm_integration_assert_same(false, IDLM_Quotation_Schema::is_current());
    $after_ddl = idlm_quo_snapshot($fixture);
    idlm_integration_assert_same($before, idlm_classification_without_source($after_ddl));
    idlm_schema_expect_ok(IDLM_Quotations::install());
    idlm_integration_assert_same($after_ddl, idlm_quo_snapshot($fixture));
});

idlm_integration_test('T17-C-QUO005-two-installers-converge-without-classification-or-money-drift', static function(): void {
    global $wpdb;
    $fixture = idlm_classification_fixture();
    $wpdb->query("ALTER TABLE {$wpdb->prefix}idlm_quote_revision_items DROP COLUMN classification_source");
    $before = idlm_quo_snapshot($fixture);
    update_option('idlm_quotation_schema_version', '2.5.1', false);
    @unlink(WP_CONTENT_DIR.'/lead-barrier');
    $results = idlm_lifecycle_pair(array('quotation-migration-race'), array('quotation-migration-race'));
    foreach ($results as $r) { idlm_integration_assert_same(0, $r['status'], $r['stderr']); }
    idlm_integration_assert_same($before, idlm_classification_without_source(idlm_quo_snapshot($fixture)));
    idlm_integration_assert_same(true, IDLM_Quotation_Schema::is_current());
});

function idlm_classification_old_backup(array $payload, string $quotation, string $core): array {
    $payload['schema']['quotation'] = $quotation;
    $payload['schema']['core'] = $core;
    unset($payload['schema']['columns']['idlm_quote_revisions']['document_terms']);
    foreach ($payload['tables']['idlm_quote_revisions'] as &$row) { unset($row['document_terms']); } unset($row);
    unset($payload['schema']['columns']['idlm_quote_revision_items']['classification_source']);
    foreach ($payload['tables']['idlm_quote_revision_items'] as &$row) { unset($row['classification_source']); }
    unset($row);
    if ($core !== IDLM_DB_VERSION) {
        foreach (array('submission_data','external_id','resolved_lead_id','resolved_by','resolved_at','resolution_note') as $field) {
            unset($payload['schema']['columns']['idlm_inbound_submission_reviews'][$field]);
            foreach ($payload['tables']['idlm_inbound_submission_reviews'] as &$row) { unset($row[$field]); }
            unset($row);
        }
        if ('1.3.8' !== $core) {
            unset($payload['schema']['columns']['idlm_tasks']['qualification_cycle']);
            foreach ($payload['tables']['idlm_tasks'] as &$row) { unset($row['qualification_cycle']); }
            unset($row);
        }
    }
    return $payload;
}

idlm_integration_test('T16-T17-N-QUO005-complete-old-quotation-and-core-backups-normalize-without-changing-bits-or-money', static function(): void {
    global $wpdb;
    idlm_native_backup_fixture();
    $lead = (int) $wpdb->get_var("SELECT id FROM {$wpdb->prefix}idlm_leads LIMIT 1");
    idlm_schema_expect_ok(IDLM_DB::create_presales_followup_tasks($lead, 0));
    $revision = (int) $wpdb->get_var("SELECT id FROM {$wpdb->prefix}idlm_quote_revisions LIMIT 1");
    $wpdb->update($wpdb->prefix.'idlm_quote_revision_items', array('is_wood'=>1, 'classification_source'=>'submitted'), array('revision_id'=>$revision));
    $payload = idlm_native_backup_call('snapshot');
    foreach (array('2.5.0','2.5.1') as $quotation) {
        foreach (array(IDLM_DB_VERSION,'1.3.8','1.3.7','1.3.6','1.3.5') as $core) {
            $old = idlm_classification_old_backup($payload, $quotation, $core);
            idlm_integration_assert_same(true, idlm_native_backup_call('restore_payload', $old));
            $after = idlm_native_backup_call('snapshot');
            $expected = $payload['tables'];
            foreach ($expected['idlm_quote_revision_items'] as &$row) { $row['classification_source']='legacy_unverified'; }
            unset($row);
            if ($core !== IDLM_DB_VERSION && $core !== '1.3.8') {
                foreach ($expected['idlm_tasks'] as &$row) { $row['qualification_cycle']=null; }
                unset($row);
            }
            idlm_integration_assert_same($expected, $after['tables']);
        }
    }
    // A new-format round trip preserves provenance exactly.
    idlm_integration_assert_same(true, idlm_native_backup_call('restore_payload', $payload));
    idlm_integration_assert_same($payload['tables'], idlm_native_backup_call('snapshot')['tables']);
});

idlm_integration_test('T16-N-QUO005-current-and-incomplete-old-backups-cannot-fabricate-provenance', static function(): void {
    $payload = idlm_native_backup_fixture();
    $cases = array();
    $bad = $payload; unset($bad['tables']['idlm_quote_revision_items'][0]['classification_source']); $cases[]=$bad;
    $bad = $payload; $bad['tables']['idlm_quote_revision_items'][0]['classification_source']='catalogue_inferred'; $cases[]=$bad;
    $bad = idlm_classification_old_backup($payload,'2.5.1',IDLM_DB_VERSION); unset($bad['tables']['idlm_quote_revision_items'][0]['is_wood']); $cases[]=$bad;
    $bad = idlm_classification_old_backup($payload,'2.5.1',IDLM_DB_VERSION); unset($bad['schema']['columns']['idlm_quote_revisions']['grand_total']); $cases[]=$bad;
    $bad = idlm_classification_old_backup($payload,IDLM_Quotation_Schema::VERSION,IDLM_DB_VERSION); $cases[]=$bad;
    foreach ($cases as $bad) {
        $before = idlm_native_backup_digest();
        idlm_integration_assert_true(is_wp_error(idlm_native_backup_call('restore_payload', $bad)));
        idlm_integration_assert_same($before, idlm_native_backup_digest());
    }
});
