<?php
/** UI-001 quantity form/persistence contracts. Builder: Shashi. */
if ('cli' !== PHP_SAPI || strpos($config['db_name'] ?? '', 'idlm_test_') !== 0) { exit(1); }
function idlm_quantity_http_payload(object $quote): array {
    $p=idlm_http_payload((int)$quote->lead_id,(int)$quote->id,(int)$quote->current_revision_id);
    $p['items']=array();
    foreach(array('1','2','0.01','2.05') as $i=>$quantity){
        $row=idlm_http_payload((int)$quote->lead_id)['items'][0];
        $row['description']='UI001 manual '.$quantity;$row['quantity']=$quantity;$p['items'][]=$row;
    }
    return $p;
}
function idlm_quantity_http_assert_rows(object $quote, array $quantities, array $prices): void {
    $bundle=IDLM_Quotation_Repository::get_quote_bundle($quote->id);
    idlm_http_check(array_map(static function($row){return (string)$row->quantity;},$bundle->items)===$quantities,'Stored quantities differ');
    idlm_http_check(array_map(static function($row){return (string)$row->line_subtotal;},$bundle->items)===$prices,'Stored line prices differ');
}
$now=current_time('mysql');
idlm_http_check(1===$wpdb->insert($wpdb->prefix.'idlm_leads',array('first_name'=>'Synthetic quantity fixture','assigned_rep_id'=>$users['owner'],'created_at'=>$now,'updated_at'=>$now)),'Quantity fixture lead failed');
$quantity_lead=(int)$wpdb->insert_id;
idlm_http_check(idlm_http_save(idlm_http_payload($quantity_lead))['status']===302,'Initial quantity fixture save failed');
$quantity_quote=IDLM_Quotation_Repository::get_quote_for_lead($quantity_lead);
idlm_http_case('T10-T18-H-UI001-manual-quantities-working-copy-save-and-repeat-retain-values',static function() use(&$quantity_quote): void {
    global $users;
    $p=idlm_quantity_http_payload($quantity_quote);
    $r=idlm_http_autosave($p);idlm_http_check($r['status']===200,'Ordinary quantity autosave failed');
    $copy=get_transient('idlm_quote_autosave_'.$users['owner'].'_'.$quantity_quote->current_revision_id);
    idlm_http_check(array_column($copy['items'],'quantity')===array(1.0,2.0,0.01,2.05),'Working-copy quantities changed');
    idlm_http_check(idlm_http_save($p)['status']===302,'Ordinary quantity save failed');
    $quantity_quote=IDLM_Quotation_Repository::get_quote($quantity_quote->id);
    idlm_quantity_http_assert_rows($quantity_quote,array('1.000','2.000','0.010','2.050'),array('100.00','200.00','1.00','205.00'));
    $original=IDLM_Quotation_Repository::get_quote_bundle($quantity_quote->id);
    idlm_http_check(idlm_http_save(idlm_quantity_http_payload($quantity_quote))['status']===302,'Repeated quantity save failed');
    $quantity_quote=IDLM_Quotation_Repository::get_quote($quantity_quote->id);
    idlm_quantity_http_assert_rows($quantity_quote,array('1.000','2.000','0.010','2.050'),array('100.00','200.00','1.00','205.00'));
    $old=IDLM_Quotation_Repository::get_quote_bundle($quantity_quote->id,$original->revision->id);
    idlm_http_check(wp_json_encode($old->items)===wp_json_encode($original->items),'Repeated save rewrote historical items');
});
idlm_http_case('T10-T18-H-UI001-tiny-dimension-derived-quantity-prices-the-normalized-value',static function() use(&$quantity_quote): void {
    $p=idlm_quantity_http_payload($quantity_quote);$row=$p['items'][0];
    $row['description']='UI001 tiny area';$row['length']='0.01';$row['height']='0.01';$row['quantity']='9';$p['items'][]=$row;
    idlm_http_check(idlm_http_save($p)['status']===302,'Tiny dimension save failed');
    $quantity_quote=IDLM_Quotation_Repository::get_quote($quantity_quote->id);
    idlm_quantity_http_assert_rows($quantity_quote,array('1.000','2.000','0.010','2.050','0.010'),array('100.00','200.00','1.00','205.00','1.00'));
});
idlm_http_case('T18-T19-H-UI001-rendered-admin-shortcode-new-and-edit-use-hundredth-step',static function() use($quantity_quote,$quantity_lead): void {
    $before=idlm_http_snapshot();
    foreach(array('/workspace','/admin-workspace') as $path){
        foreach(array(array('action'=>'new','lead_id'=>$quantity_lead),array('action'=>'edit','quote_id'=>$quantity_quote->id)) as $args){
            $r=idlm_http_request($path,$args,'owner',false);idlm_http_check($r['status']===200,'Quantity editor failed');
            $dom=new DOMDocument();libxml_use_internal_errors(true);$dom->loadHTML($r['body']);libxml_clear_errors();libxml_use_internal_errors(false);
            $nodes=(new DOMXPath($dom))->query('//input[@aria-label="Quantity"]');idlm_http_check($nodes->length===($args['action']==='new'?1:5),'Unexpected quantity input count');
            foreach($nodes as $node){idlm_http_check($node->getAttribute('type')==='number' && $node->getAttribute('min')==='0.01' && $node->getAttribute('step')==='0.01','Quantity lattice mismatch');}
        }
    }
    idlm_http_check($before===idlm_http_snapshot(),'Editor reads changed aggregate');
});
$quantity_evidence=getenv('IDLM_TEST_QUANTITY_EVIDENCE_DIR');
if($quantity_evidence){file_put_contents($quantity_evidence.'/browser-fixture.json',wp_json_encode(array('url'=>$config['http_url'],'lead_id'=>$quantity_lead,'quote_id'=>(int)$quantity_quote->id,'revision_id'=>(int)$quantity_quote->current_revision_id,'database'=>$config['db_name'],'content_dir'=>$config['content_dir'])));}
