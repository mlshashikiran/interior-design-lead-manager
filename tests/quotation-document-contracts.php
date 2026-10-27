<?php
/** QUO-006 document snapshot contracts. Builder: Shashi. */
if (!defined('IDLM_TESTING')) { exit; }
function idlm_document_settings(array $extra=array()): array {
    return array_merge(array('cover_page_enabled'=>1,'cover_page_html'=>'<h1>Original cover terms</h1>','after_quote_enabled'=>1,'after_quote_html'=>'<p>Original closing terms</p>[idlm_payment_structure]','pdf_footer'=>'Original footer terms','payment_structure'=>'[{"name":"Deposit","percentage":"50"},{"name":"Completion","percentage":"50"}]'),$extra);
}
idlm_test('QUO006 captures sanitized contractual content and exact milestone values independently of live settings',static function():void {
    $json=IDLM_Quotation_Document::capture(idlm_document_settings(),118);
    assert_true(is_string($json));$revision=(object)array('document_terms'=>$json);
    $terms=IDLM_Quotation_Document::read($revision);
    assert_same(1,$terms['version']);assert_same('59.00',$terms['milestones'][0]['amount']);assert_same('50.00',$terms['milestones'][1]['percentage']);
    update_option('idlm_quote_settings',idlm_document_settings(array('pdf_footer'=>'Changed footer','currency_code'=>'USD')));
    assert_same($terms,IDLM_Quotation_Document::read($revision));
    assert_same($json,$revision->document_terms);
});
idlm_test('QUO006 unknown or corrupt historical terms never resolve against current settings',static function():void {
    update_option('idlm_quote_settings',idlm_document_settings());
    assert_same(null,IDLM_Quotation_Document::read((object)array()));assert_same(null,IDLM_Quotation_Document::read((object)array('document_terms'=>null)));
    foreach(array('{bad','{}','{"version":2}',str_repeat('x',IDLM_Quotation_Document::MAX_BYTES+1)) as $bad){assert_true(is_wp_error(IDLM_Quotation_Document::read((object)array('document_terms'=>$bad))));}
    foreach(array(array('payment_structure'=>'bad'),array('payment_structure'=>'[{"name":"Bad","percentage":"1e309"}]'),array('payment_structure'=>'[{"name":"A","percentage":60},{"name":"B","percentage":60}]'),array('after_quote_html'=>str_repeat('x',32001))) as $bad){assert_true(is_wp_error(IDLM_Quotation_Document::capture(idlm_document_settings($bad),118)));}
});
idlm_test('QUO006 money honors explicit historical currencies while uncaptured catalogue prices use current currency',static function():void {
    update_option('idlm_quote_settings',array('currency_code'=>'USD'));
    $money=new ReflectionMethod(IDLM_Quotation_Renderer::class,'money');
    if (PHP_VERSION_ID < 80100) { $money->setAccessible(true); }
    assert_same('₹118.00',$money->invoke(null,118,'INR'));assert_same('$118.00',$money->invoke(null,118,'USD'));assert_same('$118.00',$money->invoke(null,118));assert_same('EUR118.00',str_replace('€','EUR',$money->invoke(null,118,'EUR')));
});
idlm_test('QUO006 complete older quotation backup inventories gain unknown terms without invented content',static function():void {
    $schema=IDLM_Backup_Validator::columns();
    foreach(array('2.5.0','2.5.1','2.5.2','2.5.3') as $version) {
        // columns() is a field list, while normalization receives physical keyed metadata.
        $physical=array();foreach($schema as $table=>$fields){$physical[$table]=array_fill_keys($fields,array('Type'=>'longtext','Null'=>'YES'));}
        $legacy=$physical;unset($legacy['idlm_quote_revisions']['document_terms']);if(in_array($version,array('2.5.0','2.5.1'),true)){unset($legacy['idlm_quote_revision_items']['classification_source']);}
        $row=array_fill_keys(array_keys($legacy['idlm_quote_revisions']),null);$row['currency_code']='INR';$row['grand_total']='118.00';
        $payload=array('schema'=>array('core'=>IDLM_DB_VERSION,'quotation'=>$version,'columns'=>$legacy),'tables'=>array('idlm_quote_revisions'=>array($row)));
        $normalized=IDLM_Backup_Validator::normalize_document_terms($payload,$physical);
        assert_same(null,$normalized['tables']['idlm_quote_revisions'][0]['document_terms']);assert_same('INR',$normalized['tables']['idlm_quote_revisions'][0]['currency_code']);assert_same('118.00',$normalized['tables']['idlm_quote_revisions'][0]['grand_total']);
        unset($payload['tables']['idlm_quote_revisions'][0]['grand_total']);assert_same($payload,IDLM_Backup_Validator::normalize_document_terms($payload,$physical));
    }
});

idlm_test('QUO006 milestone rounding allocates cents without exceeding the agreed percentage total',static function():void {
    $settings=idlm_document_settings(array('payment_structure'=>'[{"name":"A","percentage":"33.33"},{"name":"B","percentage":"33.33"},{"name":"C","percentage":"33.34"}]'));
    $terms=IDLM_Quotation_Document::read((object)array('document_terms'=>IDLM_Quotation_Document::capture($settings,1)));
    assert_same(array('0.33','0.33','0.34'),array_column($terms['milestones'],'amount'));
    $settings['payment_structure']='[{"name":"A","percentage":"50"},{"name":"B","percentage":"50"}]';
    $terms=IDLM_Quotation_Document::read((object)array('document_terms'=>IDLM_Quotation_Document::capture($settings,.01)));
    assert_same(array('0.01','0.00'),array_column($terms['milestones'],'amount'));
});
