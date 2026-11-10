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

idlm_test('QUO cover is live branding: new versions save no cover and print uses current settings', static function(): void {
    $terms=IDLM_Quotation_Document::read((object)array('document_terms'=>IDLM_Quotation_Document::capture(idlm_document_settings(),118)));
    assert_same(0,$terms['cover_page_enabled']);assert_same('',$terms['cover_page_html']);
    // The footer is still captured.
    assert_same('Original footer terms',$terms['pdf_footer']);
    $renderer=file_get_contents(dirname(__DIR__).'/includes/class-quotation-renderer.php');
    assert_false(strpos($renderer,"foreach (array('cover_page_enabled','cover_page_html','after_quote_enabled'")!==false);
    assert_contains('Changes apply to every quotation, including earlier revisions',$renderer);
    $exporter=file_get_contents(dirname(__DIR__).'/includes/class-quotation-exporter.php');
    assert_false(strpos($exporter,"'cover_page_html'=>'Cover content'")!==false);
});

idlm_test('QUO full-bleed cover is opt-in, sized to the paper and applied to print and PDF', static function(): void {
    $bleed = '<div class="idlm-quotation-cover idlm-cover-full-bleed"><img src="x" alt=""></div>';
    assert_same('', IDLM_Quotation_Renderer::full_bleed_cover_css(array('cover_page_enabled'=>1,'cover_page_html'=>'<div class="idlm-quotation-cover">x</div>','paper_size'=>'A4')));
    assert_same('', IDLM_Quotation_Renderer::full_bleed_cover_css(array('cover_page_enabled'=>0,'cover_page_html'=>$bleed,'paper_size'=>'A4')));
    foreach (array('A4'=>'width:210mm!important;height:296.5mm!important;','Letter'=>'width:215.9mm!important;height:278.9mm!important;','A3'=>'width:297mm!important;height:419.5mm!important;','Legal'=>'width:215.9mm!important;height:355.1mm!important;','bogus'=>'width:210mm!important;height:296.5mm!important;') as $paper=>$size) {
        $css = IDLM_Quotation_Renderer::full_bleed_cover_css(array('cover_page_enabled'=>1,'cover_page_html'=>$bleed,'paper_size'=>$paper));
        assert_contains('@page :first{margin:0}', $css); assert_contains($size, $css);
        // Printed through esc_html: no character that escaping would change.
        assert_same($css, esc_html($css));
    }
    $root = dirname(__DIR__);
    assert_contains('<style id="idlm-cover-full-bleed"><?php echo esc_html($idlm_full_bleed); ?></style>', file_get_contents($root . '/includes/class-quotation-renderer.php'));
    assert_contains("IDLM_Quotation_Renderer::full_bleed_cover_css();", file_get_contents($root . '/includes/class-quotation-exporter.php'));
});

idlm_test('QUO content after the quote is live: new versions save none, print uses current settings, milestones stay saved', static function(): void {
    $terms=IDLM_Quotation_Document::read((object)array('document_terms'=>IDLM_Quotation_Document::capture(idlm_document_settings(),118)));
    assert_same(0,$terms['after_quote_enabled']);assert_same('',$terms['after_quote_html']);
    assert_same(array('Deposit','Completion'),array_column($terms['milestones'],'name'));assert_same('Original footer terms',$terms['pdf_footer']);
    $renderer=file_get_contents(dirname(__DIR__).'/includes/class-quotation-renderer.php');
    assert_contains("\$settings['pdf_footer'] = \$terms_known ? \$document_terms['pdf_footer'] : '';",$renderer);
    assert_false(strpos($renderer,"foreach (array('after_quote_enabled','after_quote_html','pdf_footer') as \$field) {")!==false);
    assert_contains('Payment milestone amounts stay as saved with each revision.',$renderer);
    $exporter=file_get_contents(dirname(__DIR__).'/includes/class-quotation-exporter.php');
    assert_false(strpos($exporter,"'after_quote_html'=>'Closing content'")!==false);
});

idlm_test('QUO accepted closing: winning locks current setup once, legacy saved closing is kept, and only finalised or locked terms print saved content', static function(): void {
    $live=idlm_document_settings(array('after_quote_html'=>'<p>Terms on the day the lead was won</p>'));
    // A 1.105.5 revision saves no closing: winning copies current setup.
    $revision=(object)array('document_terms'=>IDLM_Quotation_Document::capture(idlm_document_settings(),118));
    assert_same(null,IDLM_Quotation_Document::closing(IDLM_Quotation_Document::read($revision),true));
    $locked=IDLM_Quotation_Document::accept_closing($revision,$live);
    assert_true(is_string($locked));
    $terms=IDLM_Quotation_Document::read((object)array('document_terms'=>$locked));
    assert_same(2,$terms['version']);assert_same('settings',$terms['accepted']['source']);
    assert_same(array('enabled'=>1,'html'=>'<p>Terms on the day the lead was won</p>'),IDLM_Quotation_Document::closing($terms,false));
    assert_same(array('Deposit','Completion'),array_column($terms['milestones'],'name'));assert_same('Original footer terms',$terms['pdf_footer']);
    // Locking again is a no-op, whatever setup says now.
    assert_same(false,IDLM_Quotation_Document::accept_closing((object)array('document_terms'=>$locked),idlm_document_settings(array('after_quote_html'=>'<p>Later edit</p>'))));
    // A pre-1.105.5 revision kept the closing it was shared with: winning keeps that copy.
    $legacy=json_decode($revision->document_terms,true);$legacy['after_quote_enabled']=1;$legacy['after_quote_html']='<p>Shared closing</p>';
    $legacy_revision=(object)array('document_terms'=>wp_json_encode($legacy));
    $legacy_terms=IDLM_Quotation_Document::read($legacy_revision);
    assert_same(null,IDLM_Quotation_Document::closing($legacy_terms,false));
    assert_same('<p>Shared closing</p>',IDLM_Quotation_Document::closing($legacy_terms,true)['html']);
    $kept=IDLM_Quotation_Document::read((object)array('document_terms'=>IDLM_Quotation_Document::accept_closing($legacy_revision,$live)));
    assert_same('saved',$kept['accepted']['source']);assert_same('<p>Shared closing</p>',$kept['after_quote_html']);
    // Unknown terms are never locked; malformed acceptance records are refused.
    assert_same(false,IDLM_Quotation_Document::accept_closing((object)array('document_terms'=>null),$live));
    foreach(array(array('at'=>'yesterday','source'=>'settings'),array('at'=>'2026-11-10 10:00:00','source'=>'guess'),array('source'=>'saved','at'=>'2026-11-10 10:00:00')) as $bad){
        $row=$terms;$row['accepted']=$bad;assert_true(is_wp_error(IDLM_Quotation_Document::read((object)array('document_terms'=>wp_json_encode($row)))));
    }
    $row=$terms;unset($row['accepted']);assert_true(is_wp_error(IDLM_Quotation_Document::read((object)array('document_terms'=>wp_json_encode($row)))));
    $row=json_decode($revision->document_terms,true);$row['accepted']=$terms['accepted'];assert_true(is_wp_error(IDLM_Quotation_Document::read((object)array('document_terms'=>wp_json_encode($row)))));
    // Wiring: the won hook locks after commit, the print view applies the rule, setup says so.
    $quotations=file_get_contents(dirname(__DIR__).'/includes/class-quotations.php');
    assert_contains("add_action('idlm_lead_stage_converted', array(__CLASS__, 'lock_accepted_closing'), 10, 2);",$quotations);
    assert_contains("IDLM_Quotation_Repository::replace_document_terms(\$bundle->revision->id, (string) \$bundle->revision->document_terms, \$terms);",$quotations);
    $renderer=file_get_contents(dirname(__DIR__).'/includes/class-quotation-renderer.php');
    assert_contains("\$closing = IDLM_Quotation_Document::closing(\$terms_known ? \$document_terms : null, \$finalised);",$renderer);
    assert_contains('A quotation marked Sales finalised keeps the terms from when the lead was won.',$renderer);
});

idlm_test('QUO client header and summary: dates, owners, GSTIN, split GST, total in words and savings', static function(): void {
    $r='IDLM_Quotation_Renderer';
    assert_same(array('issued'=>'31 Jan 2026','valid_until'=>'28 Feb 2026'),invoke_private($r,'quote_dates',array('2026-01-31 18:20:00')));
    assert_same(array('issued'=>'10 Nov 2026','valid_until'=>'10 Dec 2026'),invoke_private($r,'quote_dates',array('2026-11-10 09:00:00')));
    assert_same(array('issued'=>'—','valid_until'=>'—'),invoke_private($r,'quote_dates',array('')));
    assert_same('Rupees Five Lakh Twelve Thousand Three Hundred Only',invoke_private($r,'amount_in_words',array(512300,'INR')));
    assert_same('Rupees One Hundred Twenty Crore Forty Five Lakh Only',invoke_private($r,'amount_in_words',array(1204500000,'INR')));
    assert_same('Rupees Ninety Nine and Fifty Paise Only',invoke_private($r,'amount_in_words',array(99.5,'INR')));
    assert_same('One Million Two Hundred Thousand Eleven US Dollars Only',invoke_private($r,'amount_in_words',array(1200011,'USD')));
    assert_same('Rupees Zero Only',invoke_private($r,'amount_in_words',array(0,'INR')));
    assert_same(array(array('CGST (9%)',900.0),array('SGST (9%)',900.0)),invoke_private($r,'tax_rows',array((object)array('cgst_total'=>900,'sgst_total'=>900,'igst_total'=>0))));
    assert_same(array(array('IGST (18%)',1800.0)),invoke_private($r,'tax_rows',array((object)array('cgst_total'=>0,'sgst_total'=>0,'igst_total'=>1800))));
    $GLOBALS['idlm_test_users'][71]=(object)array('ID'=>71,'display_name'=>'Asha Rao');
    $GLOBALS['idlm_test_user_meta'][71]['idlm_phone']='+91 98450 00000';
    assert_same('Asha Rao · +91 98450 00000',invoke_private($r,'person_line',array(71)));
    assert_same('Kept Name · +91 98450 00000',invoke_private($r,'person_line',array(71,'Kept Name')));
    assert_same('Saved Designer',invoke_private($r,'person_line',array(0,'Saved Designer')));
    assert_same('',invoke_private($r,'person_line',array(0)));
    $source=file_get_contents(dirname(__DIR__).'/includes/class-quotation-renderer.php');
    foreach(array('<dt>Quote date:</dt>','<dt>Valid until:</dt>','<dt>Sales owner:</dt>','<dt>Designer:</dt>','<dt>GSTIN:</dt>','idlm-print-grand-box','You save ','self::amount_in_words($revision->grand_total, $currency)','name="company_gstin"') as $needle){assert_contains($needle,$source);}
    assert_false(false!==strpos($source,"'GST (18%)'"));
    $controller=file_get_contents(dirname(__DIR__).'/includes/class-quotation-controller.php');
    assert_contains("preg_match('/^[0-9]{2}[A-Z0-9]{13}$/', \$gstin)",$controller);
    $exporter=file_get_contents(dirname(__DIR__).'/includes/class-quotation-exporter.php');
    assert_contains("array('CGST', (float) \$revision->cgst_total, 'currency')",$exporter);
    assert_contains('.idlm-print-grand-box{',$exporter);
});
