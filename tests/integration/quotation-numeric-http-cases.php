<?php
/** QUO-008 actual rejection, totals, exact revision exports and recovery. Builder: Shashi. */
if (!defined('ABSPATH') || strpos(DB_NAME,'idlm_test_')!==0) { exit(1); }

function idlm_numeric_http_denials(callable $mutate, ?callable $beforeDenials=null): void {
    global $wpdb,$users;
    $q=idlm_history_http_fixture();$edit=idlm_http_payload((int)$q->lead_id,(int)$q->id,(int)$q->current_revision_id);
    idlm_http_check(idlm_http_autosave($edit)['status']===200,'Working-copy fixture failed');
    $now=current_time('mysql');$wpdb->insert($wpdb->prefix.'idlm_leads',array('first_name'=>'Synthetic numeric create','assigned_rep_id'=>$users['owner'],'created_at'=>$now,'updated_at'=>$now));$create=idlm_http_payload((int)$wpdb->insert_id);
    if ($beforeDenials) { $beforeDenials(); }
    foreach(array('owner','admin') as $actor) {
        foreach(array($edit,$create) as $p) {$p=$mutate($p);$before=idlm_http_snapshot();$r=idlm_http_save($p,$actor);idlm_http_check($r['status']===302 && strpos($r['headers'],'idlm_error=')!==false,'Invalid number save was acknowledged: '.$r['headers']);idlm_http_check($before===idlm_http_snapshot(),'Invalid save changed numbers, revisions, lead values, history or working copies');}
        $before=idlm_http_snapshot();$r=idlm_http_autosave($mutate($edit),$actor);$json=json_decode($r['body'],true);
        idlm_http_check($r['status']===422 && $json['success']===false && !empty($json['data']['message']),'Invalid autosave was acknowledged: '.$r['body']);idlm_http_check($before===idlm_http_snapshot(),'Invalid autosave changed existing working copy or aggregate');
    }
}
foreach(array('1e309','1e2','INF','NaN','-1','1x','array') as $token) {
    idlm_http_case('T10-H-QUO008-reject-all-item-numeric-fields-'.$token,static function() use($token):void {
        foreach(array('length','height','quantity','unit_rate') as $field) {
            idlm_numeric_http_denials(static function($p) use($field,$token){$p['items'][0][$field]=$token==='array'?array('100'):$token;return $p;});
        }
    });
}
$numeric_bad=array(
    'dimension-range'=>array('length'=>'1000000000'),
    'quantity-range'=>array('quantity'=>'1000000000'),
    'rate-rounding-overflow'=>array('unit_rate'=>'9999999999.995'),
    'area-overflow'=>array('length'=>'999999999.99','height'=>'2'),
    'line-overflow'=>array('quantity'=>'2','unit_rate'=>'9999999999.99'),
    'grand-rounding-overflow'=>array('unit_rate'=>'8474576271'),
);
foreach($numeric_bad as $name=>$fields) {
    idlm_http_case('T10-H-QUO008-reject-'.$name,static function() use($fields):void {idlm_numeric_http_denials(static function($p) use($fields){$p['items'][0]=array_merge($p['items'][0],$fields);return $p;});});
}
foreach(array('wooden_discount_percent','additional_discount_amount') as $field) {
    idlm_http_case('T10-H-QUO008-reject-adjustment-'.$field,static function() use($field):void {foreach(array('1e309','-1',array('10'),$field==='wooden_discount_percent'?'100.01':'10000000000') as $bad){idlm_numeric_http_denials(static function($p) use($field,$bad){$p[$field]=$bad;return $p;});}});
}
idlm_http_case('T10-H-QUO008-reject-subtotal-and-row-section-limits',static function():void {
    foreach(array('sum','rows','sections','row-shape','section-shape') as $kind){idlm_numeric_http_denials(static function($p) use($kind){
        if($kind==='sum'){$p['items']=array(array('description'=>'A','unit_rate'=>'9999999999.99'),array('description'=>'B','unit_rate'=>'.01'));}
        elseif($kind==='rows'){$p['items']=array_fill(0,201,array('description'=>'Too many','unit_rate'=>'1'));}
        elseif($kind==='sections'){$p['sections']=array_fill(0,51,array('key'=>'extra','section_name'=>'Extra'));}
        elseif($kind==='row-shape'){$p['items']=array('malformed');}
        else{$p['sections']=array('malformed');}return $p;});}
});
idlm_http_case('T10-H-QUO008-invalid-stored-soft-close-is-refused-without-history-loss',static function():void {
    $before=get_option('idlm_quote_settings');$settings=(array)$before;$settings['soft_close_percent']='1e309';
    try {idlm_numeric_http_denials(static function($p){$p['soft_close_enabled']='1';return $p;},static function() use($settings){update_option('idlm_quote_settings',$settings);});}
    finally {update_option('idlm_quote_settings',$before);}
});
idlm_http_case('T10-T12-H-QUO008-tiny-area-save-autosave-and-exact-revision-CSV-remain-consistent',static function():void {
    global $wpdb,$sessions;
    $q=idlm_history_http_fixture();$p=idlm_http_payload((int)$q->lead_id,(int)$q->id,(int)$q->current_revision_id);
    $p['items'][0]=array('description'=>'Synthetic tiny area','length'=>'.01','height'=>'.01','quantity'=>'7','unit_rate'=>'100');
    idlm_http_check(idlm_http_autosave($p)['status']===200,'Valid tiny area autosave failed');$r=idlm_http_save($p);idlm_http_check($r['status']===302 && strpos($r['headers'],'idlm_notice=')!==false,'Tiny area revision failed');
    $latest=IDLM_Quotation_Repository::get_quote($q->id);$row=$wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}idlm_quote_revision_items WHERE revision_id=%d",$latest->current_revision_id));
    idlm_http_check($row->quantity==='0.010' && $row->unit_rate==='100.00' && $row->line_subtotal==='1.00','Stored tiny area and price diverged');
    idlm_http_check($wpdb->get_var($wpdb->prepare("SELECT quote_value FROM {$wpdb->prefix}idlm_leads WHERE id=%d",$q->lead_id))==='1.00','Tiny area projected wrong lead total');
    $csv=idlm_http_request('/wp-admin/admin-post.php',array('action'=>'idlm_download_quote_csv','quote_id'=>$q->id,'revision_id'=>$latest->current_revision_id,'_wpnonce'=>$sessions['owner']['csv']),'owner',false);
    idlm_http_check($csv['status']===200 && strpos($csv['body'],'Synthetic tiny area')!==false && strpos($csv['body'],'0.01')!==false,'Exact revision CSV lost tiny quantity');
    $old=IDLM_Quotation_Repository::get_quote_bundle($q->id,(int)$q->current_revision_id);idlm_http_check($old->items[0]->description==='SYNTHETIC-ITEM-ORIGINAL' && $old->items[0]->line_subtotal==='100.00','Numeric save changed original revision');
});
idlm_http_case('T10-H-QUO008-highest-valid-grand-total-and-200-rows-persist-without-truncation',static function():void {
    global $wpdb;
    foreach(array('boundary','rows') as $kind){$q=idlm_history_http_fixture();$p=idlm_http_payload((int)$q->lead_id,(int)$q->id,(int)$q->current_revision_id);
        $p['items']=$kind==='boundary'?array(array('description'=>'Supported boundary','unit_rate'=>'8474576270')):array_fill(0,200,array('description'=>'Synthetic bounded row','quantity'=>'1','unit_rate'=>'1'));
        $r=idlm_http_save($p);idlm_http_check($r['status']===302 && strpos($r['headers'],'idlm_notice=')!==false,'Valid boundary/row cap save failed');$saved=IDLM_Quotation_Repository::get_quote($q->id);
        idlm_http_check((int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->prefix}idlm_quote_revision_items WHERE revision_id=%d",$saved->current_revision_id))===($kind==='boundary'?1:200),'Request silently truncated rows');
        $total=$wpdb->get_var($wpdb->prepare("SELECT grand_total FROM {$wpdb->prefix}idlm_quote_revisions WHERE id=%d",$saved->current_revision_id));idlm_http_check($total===($kind==='boundary'?'9999999999.00':'236.00'),'Boundary/row total drift');
    }
});
idlm_http_case('T10-H-QUO008-rounded-lines-percentages-optionals-and-GST-match-server',static function():void {
    global $wpdb;
    $before=get_option('idlm_quote_settings');$settings=(array)$before;$settings['soft_close_percent']='10';update_option('idlm_quote_settings',$settings);
    try{$q=idlm_history_http_fixture();$p=idlm_http_payload((int)$q->lead_id,(int)$q->id,(int)$q->current_revision_id);$p['items']=array(array('description'=>'Wood A','unit_rate'=>'1.005','is_wood'=>'1'),array('description'=>'Wood B','unit_rate'=>'1.005','is_wood'=>'1'),array('description'=>'Other','unit_rate'=>'1.005'),array('description'=>'Optional','unit_rate'=>'9999999999.99','is_optional'=>'1'));$p['wooden_discount_percent']='12.5';$p['additional_discount_amount']='.025';$p['soft_close_enabled']='1';$r=idlm_http_save($p);idlm_http_check($r['status']===302 && strpos($r['headers'],'idlm_notice=')!==false,'Rounded split save failed');$saved=IDLM_Quotation_Repository::get_quote($q->id);$rev=IDLM_Quotation_Repository::get_revision($saved->current_revision_id);idlm_http_check($rev->subtotal==='3.03' && $rev->wooden_discount_amount==='0.25' && $rev->soft_close_amount==='0.18' && $rev->grand_total==='3.00' && $rev->round_off_amount==='-0.46','Rounded aggregate diverged');}
    finally{update_option('idlm_quote_settings',$before);}
});

idlm_http_case('T11-H-QUO008-settings-reject-invalid-and-sum-milestones-in-integer-hundredths',static function():void {
    global $sessions,$wpdb;
    $original_raw=$wpdb->get_var("SELECT option_value FROM {$wpdb->options} WHERE option_name='idlm_quote_settings'");
    $original=maybe_unserialize($original_raw);
    try {
        foreach(array('1e2','101','-1') as $bad) {
            $r=idlm_http_request('/wp-admin/admin-post.php',array('action'=>'idlm_save_quote_settings','_wpnonce'=>$sessions['admin']['quote_settings'],'soft_close_percent'=>$bad),'admin');
            idlm_http_check($r['status']===302 && strpos($r['headers'],'idlm_error=')!==false && $original_raw===$wpdb->get_var("SELECT option_value FROM {$wpdb->options} WHERE option_name='idlm_quote_settings'"),'Invalid settings response/state for '.$bad.': '.$r['headers']);
        }
        foreach(array('1e2','100.01') as $bad) {
            $r=idlm_http_request('/wp-admin/admin-post.php',array('action'=>'idlm_save_quote_settings','_wpnonce'=>$sessions['admin']['quote_settings'],'payment_structure'=>array(array('name'=>'Synthetic invalid','percentage'=>$bad))),'admin');
            idlm_http_check($r['status']===302 && strpos($r['headers'],'idlm_error=')!==false && $original_raw===$wpdb->get_var("SELECT option_value FROM {$wpdb->options} WHERE option_name='idlm_quote_settings'"),'Invalid milestone response/state for '.$bad.': '.$r['headers']);
        }
        $r=idlm_http_request('/wp-admin/admin-post.php',array('action'=>'idlm_save_quote_settings','_wpnonce'=>$sessions['admin']['quote_settings'],'soft_close_percent'=>'1.005','payment_structure'=>array(array('name'=>'A','percentage'=>'33.33'),array('name'=>'B','percentage'=>'33.33'),array('name'=>'C','percentage'=>'33.34'))),'admin');
        $saved=maybe_unserialize($wpdb->get_var("SELECT option_value FROM {$wpdb->options} WHERE option_name='idlm_quote_settings'"));
        idlm_http_check($r['status']===302 && strpos($r['headers'],'idlm_notice=')!==false && is_array($saved) && $saved['soft_close_percent']==1.01 && count(json_decode($saved['payment_structure'],true))===3,'Valid decimal settings failed: '.$r['headers']);
    } finally { wp_cache_flush(); if ($original_raw===null) { delete_option('idlm_quote_settings'); } else { update_option('idlm_quote_settings',$original,false); } }
});
idlm_http_case('T11-H-QUO008-catalogue-rejects-invalid-rates-before-item-or-term-writes',static function():void {
    global $sessions;
    foreach(array('1e309','10000000000','-1') as $bad) {
        $before=idlm_http_snapshot();
        $r=idlm_http_request('/wp-admin/admin-post.php',array('action'=>'idlm_save_catalogue_item','_wpnonce'=>$sessions['admin']['catalogue_save'],'item_code'=>wp_generate_uuid4(),'item_name'=>'Synthetic invalid rate','category'=>'QUO008 invalid new term','unit'=>'QUO008 invalid unit','default_rate'=>$bad),'admin');
        idlm_http_check($r['status']===302 && strpos($r['headers'],'idlm_error=')!==false && $before===idlm_http_snapshot(),'Invalid catalogue rate wrote item or terms');
    }
});
