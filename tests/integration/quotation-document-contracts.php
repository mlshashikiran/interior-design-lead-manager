<?php
/** QUO-006 physical snapshot upgrade/recovery and historical output. Builder: Shashi. */
if (!defined('ABSPATH') || strpos(DB_NAME,'idlm_test_')!==0) { exit(1); }
function idlm_document_native_settings(): array { return array('currency_code'=>'INR','cover_page_enabled'=>1,'cover_page_html'=>'<h1>Original captured cover</h1>','after_quote_enabled'=>1,'after_quote_html'=>'<p>Original captured closing</p>[idlm_payment_structure]','pdf_footer'=>'Original captured footer','payment_structure'=>'[{"name":"Original deposit","percentage":"50"},{"name":"Original completion","percentage":"50"}]'); }
function idlm_document_native_revision(): array {
    global $wpdb;$fixture=idlm_quo_migration_fixture();$terms=IDLM_Quotation_Document::capture(idlm_document_native_settings(),118);idlm_integration_assert_true(is_string($terms));
    $wpdb->update($wpdb->prefix.'idlm_quote_revisions',array('customer_name'=>'Synthetic historical customer','document_terms'=>$terms,'currency_code'=>'INR','subtotal'=>100,'taxable_total'=>100,'grand_total'=>118),array('id'=>$fixture[2]));
    idlm_integration_assert_same(true,IDLM_Quotation_Repository::replace_revision_contents($fixture[2],array('general'=>array('section_name'=>'General','sort_order'=>0)),IDLM_Quotation_Calculator::parse_items(array(array('description'=>'Synthetic contractual item','unit_rate'=>100)))));
    return $fixture;
}
idlm_integration_test('T10-T12-N-QUO006-saved-output-retains-currency-template-and-payments-after-settings-changes',static function():void {
    $fixture=idlm_document_native_revision();$before=idlm_quo_snapshot($fixture);$old=get_option('idlm_quote_settings');
    try {
        $changed=idlm_document_native_settings();$changed['currency_code']='USD';$changed['cover_page_html']='<h1>Changed live cover</h1>';$changed['after_quote_html']='<p>Changed live closing</p>';$changed['pdf_footer']='Changed live footer';$changed['company_name']='Changed live branding';$changed['payment_structure']='[{"name":"Changed live deposit","percentage":100}]';update_option('idlm_quote_settings',$changed,false);
        $bundle=IDLM_Quotation_Repository::get_quote_bundle($fixture[1],$fixture[2]);$html=IDLM_Quotation_Renderer::render_print_document($bundle);
        foreach(array('Original captured cover','Original captured closing','Original captured footer','Original deposit','Original completion','₹118.00','₹59.00','Changed live branding') as $token){idlm_integration_assert_true(strpos($html,$token)!==false,$token.' missing');}
        foreach(array('Changed live cover','Changed live closing','Changed live footer','Changed live deposit','$118.00','[idlm_payment_structure]') as $token){idlm_integration_assert_true(strpos($html,$token)===false,$token.' leaked');}
        $method=new ReflectionMethod(IDLM_Quotation_Exporter::class,'export_data');$data=$method->invoke(null,$bundle);$summary=array_column($data['summary'],1,0);
        idlm_integration_assert_same('INR',$summary['Currency']);idlm_integration_assert_same('50.00%',$summary['Payment percentage: Original deposit']);idlm_integration_assert_same(59.0,$summary['Payment amount: Original deposit']);idlm_integration_assert_same('Original captured footer',$summary['Footer terms']);idlm_integration_assert_same($before,idlm_quo_snapshot($fixture));
    } finally { update_option('idlm_quote_settings',$old,false); }
});
idlm_integration_test('T17-N-QUO006-additive-2.5.3-upgrade-preserves-exact-old-revisions-with-null-terms',static function():void {
    global $wpdb;$fixture=idlm_document_native_revision();$table=$wpdb->prefix.'idlm_quote_revisions';
    idlm_integration_assert_true(false!==$wpdb->query("ALTER TABLE {$table} DROP COLUMN document_terms"));$before=idlm_quo_snapshot($fixture);update_option('idlm_quotation_schema_version','2.5.3',false);
    idlm_schema_expect_ok(IDLM_Quotations::install());idlm_schema_expect_ok(IDLM_Quotations::install());$after=idlm_quo_snapshot($fixture);
    idlm_integration_assert_same(null,$after['idlm_quote_revisions'][0]['document_terms']);foreach($after['idlm_quote_revisions'] as &$row){unset($row['document_terms']);}unset($row);idlm_integration_assert_same($before,$after);
    $html=IDLM_Quotation_Renderer::render_print_document(IDLM_Quotation_Repository::get_quote_bundle($fixture[1],$fixture[2]));idlm_integration_assert_true(strpos($html,'Historical document terms were not captured')!==false);
});
idlm_integration_test('T17-N-QUO006-document-column-DDL-failure-does-not-publish-upgrade-and-retry-preserves-history',static function():void {
    global $wpdb;$fixture=idlm_document_native_revision();$table=$wpdb->prefix.'idlm_quote_revisions';$wpdb->query("ALTER TABLE {$table} DROP COLUMN document_terms");$before=idlm_quo_snapshot($fixture);update_option('idlm_quotation_schema_version','2.5.3',false);
    $result=idlm_schema_filter(static function($sql){return strpos($sql,'ALTER TABLE')!==false && strpos($sql,'document_terms')!==false ? 'SELECT idlm_synthetic_document_ddl_fault FROM idlm_absent_table' : $sql;},static function(){return IDLM_Quotations::install();});
    idlm_integration_assert_true(is_wp_error($result));idlm_integration_assert_same('2.5.3',IDLM_Schema_Migration::version('idlm_quotation_schema_version'));idlm_integration_assert_same($before,idlm_quo_snapshot($fixture));
    idlm_schema_expect_ok(IDLM_Quotations::install());$after=idlm_quo_snapshot($fixture);foreach($after['idlm_quote_revisions'] as &$row){unset($row['document_terms']);}unset($row);idlm_integration_assert_same($before,$after);
});
idlm_integration_test('T16-T17-N-QUO006-current-and-complete-2.5.3-backup-roundtrip-retain-terms-or-explicit-unknown',static function():void {
    global $wpdb;idlm_native_backup_fixture();$fixture=idlm_document_native_revision();$before=idlm_quo_snapshot($fixture);$payload=idlm_native_backup_call('snapshot');idlm_integration_assert_true(is_array($payload));
    idlm_integration_assert_same(true,idlm_native_backup_call('restore_payload',$payload));idlm_integration_assert_same($before,idlm_quo_snapshot($fixture));
    $old=$payload;$old['schema']['quotation']='2.5.3';unset($old['schema']['columns']['idlm_quote_revisions']['document_terms']);foreach($old['tables']['idlm_quote_revisions'] as &$row){unset($row['document_terms']);}unset($row);
    idlm_integration_assert_same(true,idlm_native_backup_call('restore_payload',$old));$after=idlm_quo_snapshot($fixture);idlm_integration_assert_same(null,$after['idlm_quote_revisions'][0]['document_terms']);$before['idlm_quote_revisions'][0]['document_terms']=null;idlm_integration_assert_same($before,$after);
    $invalid=$payload;foreach($invalid['tables']['idlm_quote_revisions'] as &$row){if((int)$row['id']===$fixture[2]){$row['document_terms']='{"version":2}';}}unset($row);$stable=idlm_quo_snapshot($fixture);
    idlm_integration_assert_true(is_wp_error(idlm_native_backup_call('restore_payload',$invalid)));idlm_integration_assert_same($stable,idlm_quo_snapshot($fixture));
});
