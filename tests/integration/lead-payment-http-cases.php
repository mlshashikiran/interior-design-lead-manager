<?php
/** Actual cookie, form nonce, multipart proof and immutable history. Builder: Shashi. */
if ('cli' !== PHP_SAPI || strpos($config['db_name'] ?? '', 'idlm_test_') !== 0) { exit(1); }
function idlm_payment_http_form($html, $operation): array {
    $dom=new DOMDocument(); libxml_use_internal_errors(true); $dom->loadHTML($html); libxml_clear_errors(); libxml_use_internal_errors(false);
    $xpath=new DOMXPath($dom); $forms=$xpath->query('//form[input[@name="action" and @value="idlm_payment_'.$operation.'"]]');
    idlm_http_check($forms->length>0,'Payment '.$operation.' form missing'); $data=array();
    foreach($xpath->query('.//input',$forms->item(0)) as $input){ if($input->getAttribute('type')==='hidden'){$data[$input->getAttribute('name')]=$input->getAttribute('value');} }
    // Keep the fixture redirect within its actual composite workspace.
    $data['return_url']='/crm-workspace?idlm_view=payments'; return $data;
}
function idlm_payment_http_page($lead, $extra=array(), $actor='owner'): array {
    // These cases read the full payment page (schedule, upload, legacy recovery and balance text).
    // The CRM design layer shows a simpler page, so pin the previous design; the server rules are the same.
    return idlm_http_request('/crm-workspace',array_merge(array('idlm_view'=>'payments','payment_lead_id'=>$lead,'idlm_design'=>'legacy'),$extra),$actor,false);
}
function idlm_payment_http_multipart($form, $file, $actor='owner'): array {
    global $config; $body=tempnam($config['content_dir'],'payment-proof-');$head=$body.'.headers';
    $args=array('curl','--silent','--show-error','--max-time','15','--cookie',$config['content_dir'].'/'.$actor.'.cookies','--dump-header',$head,'--output',$body,'--write-out','%{http_code}');
    foreach($form as $key=>$value){$args[]='-F';$args[]=$key.'='.$value;} $args[]='-F';$args[]='payment_screenshot=@'.$file.';type=image/png';$args[]=$config['http_url'].'/wp-admin/admin-post.php';
    $proc=proc_open(implode(' ',array_map('escapeshellarg',$args)),array(1=>array('pipe','w'),2=>array('pipe','w')),$pipes);$status=stream_get_contents($pipes[1]);$errors=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);$exit=proc_close($proc);
    $result=array('status'=>(int)$status,'body'=>(string)file_get_contents($body),'headers'=>(string)file_get_contents($head));unlink($body);unlink($head);idlm_http_check($exit===0,'Multipart failed '.$errors);return $result;
}
$payment_user=new WP_User($users['owner']);$payment_user->add_cap('idlm_record_lead_payments');
$payment_other=new WP_User($users['other']);$payment_other->add_cap('idlm_record_lead_payments');
$now=current_time('mysql');
$wpdb->insert($wpdb->prefix.'idlm_leads',array('first_name'=>'Payment Synthetic','project_name'=>'Payment Residence','stage'=>'Won','deal_value'=>'1000000','assigned_rep_id'=>$users['owner'],'created_at'=>$now,'updated_at'=>$now));$payment_lead=(int)$wpdb->insert_id;
$wpdb->insert($wpdb->prefix.'idlm_quotes',array('lead_id'=>$payment_lead,'quote_number'=>'PAY-'.wp_generate_uuid4(),'title'=>'Payment synthetic quote','created_by'=>$users['owner'],'created_at'=>$now,'updated_at'=>$now));$payment_quote=(int)$wpdb->insert_id;
$terms=IDLM_Quotation_Document::capture(array('payment_structure'=>'[{"name":"Saved advance","percentage":10},{"name":"Saved next","percentage":40},{"name":"Saved final","percentage":50}]'),'1000000');
$wpdb->insert($wpdb->prefix.'idlm_quote_revisions',array('quote_id'=>$payment_quote,'revision_name'=>'Payment R01','document_terms'=>$terms,'grand_total'=>'1000000','currency_code'=>'INR','created_by'=>$users['owner'],'created_at'=>$now,'updated_at'=>$now));$payment_revision=(int)$wpdb->insert_id;
$wpdb->update($wpdb->prefix.'idlm_quotes',array('current_revision_id'=>$payment_revision),array('id'=>$payment_quote));
// Warm WordPress admin-post core scheduling before asserting mutation-free denials.
foreach (array('owner','other') as $payment_actor) { idlm_http_request('/wp-admin/admin-post.php', array('action'=>'idlm_fixture_noop'), $payment_actor); }
// Warm the effective-access reconciliation after explicit fixture grant changes.
idlm_payment_http_page($payment_lead, array(), 'other');
$payment_form=idlm_payment_http_form(idlm_payment_http_page($payment_lead,array('payment_revision_id'=>$payment_revision))['body'],'schedule');
$payment_form+=array('payment_revision_id'=>$payment_revision,'advance_positions'=>array('0'),'reason'=>'Exact agreed saved sales revision');
idlm_http_case('PAYMENT HTTP nonce and foreign owner denials leave all state unchanged',static function()use($payment_form):void{
    global $sessions;
    $bad=$payment_form;$bad['_wpnonce']='invalid';idlm_http_denied(static function()use($bad){return idlm_http_request('/wp-admin/admin-post.php',$bad);},array(403));
    $foreign=$payment_form; $foreign['_wpnonce']=$sessions['other']['payment_schedule'];
    idlm_http_denied(static function()use($foreign){return idlm_http_request('/wp-admin/admin-post.php',$foreign,'other');},array(422));
});
idlm_http_case('PAYMENT HTTP saved timeline publication and URN partial receipt',static function()use($payment_form,$payment_lead):void{
    idlm_http_check(idlm_http_request('/wp-admin/admin-post.php',$payment_form)['status']===302,'Schedule did not publish');
    $page=idlm_payment_http_page($payment_lead);$form=idlm_payment_http_form($page['body'],'record');$form+=array('amount'=>'99000','currency'=>'INR','received_date'=>'2026-11-05','method'=>'UPI','reference'=>'SYNTHETIC-URN-1');
    idlm_http_check(idlm_http_request('/wp-admin/admin-post.php',$form)['status']===302,'Partial receipt rejected');
    $page=idlm_payment_http_page($payment_lead);foreach(array('1000.00','901000.00','Partial','Saved advance')as$text){idlm_http_check(strpos(str_replace(',', '', $page['body']),$text)!==false,'Missing pending summary '.$text);}
    $before=idlm_http_snapshot();idlm_http_check(idlm_http_request('/wp-admin/admin-post.php',$form)['status']===302,'Retry failed');idlm_http_check($before===idlm_http_snapshot(),'Retry counted receipt twice');
});
idlm_http_case('PAYMENT HTTP missing proof date amount and forged cross lead file rejected',static function()use($payment_lead):void{
    $form=idlm_payment_http_form(idlm_payment_http_page($payment_lead)['body'],'record');$form+=array('amount'=>'1','currency'=>'INR','received_date'=>'2026-11-05','method'=>'Cash');
    foreach(array(array(),array('reference'=>'x','received_date'=>'2026-02-30'),array('reference'=>'x','amount'=>'0'),array('reference'=>'x','attachment_id'=>'999999'))as$invalid){$p=array_merge($form,$invalid);idlm_http_denied(static function()use($p){return idlm_http_request('/wp-admin/admin-post.php',$p);},array(422));}
});
$payment_png=base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+aKfoAAAAASUVORK5CYII=');file_put_contents($config['content_dir'].'/payment.png',$payment_png);
$payment_attachment=0;
idlm_http_case('PAYMENT HTTP private screenshot upload and screenshot only receipt',static function()use($payment_lead,$payment_png,&$payment_attachment):void{
    global $config,$wpdb;
    $form=idlm_payment_http_form(idlm_payment_http_page($payment_lead)['body'],'upload');$uploaded=idlm_payment_http_multipart($form,$config['content_dir'].'/payment.png');idlm_http_check($uploaded['status']===302,'Payment upload failed '.$uploaded['body']);
    $payment_attachment=(int)$wpdb->get_var($wpdb->prepare("SELECT attachment_id FROM {$wpdb->prefix}idlm_private_files WHERE lead_id=%d AND purpose='payment' ORDER BY id DESC LIMIT 1",$payment_lead));idlm_http_check($payment_attachment>0,'No private screenshot identity');
    $form=idlm_payment_http_form(idlm_payment_http_page($payment_lead)['body'],'record');$form=array_merge($form,array('amount'=>'1000','currency'=>'INR','received_date'=>'2026-11-05','method'=>'Bank','attachment_id'=>$payment_attachment));
    idlm_http_check(idlm_http_request('/wp-admin/admin-post.php',$form)['status']===302,'SS-only receipt rejected');
    // A referenced screenshot stays available beyond the temporary seven-day window.
    $wpdb->update($wpdb->prefix.'idlm_private_files',array('created_at'=>'2020-01-01 00:00:00'),array('attachment_id'=>$payment_attachment));IDLM_Private_Files::reconcile_lead($payment_lead);
    $path='/wp-admin/admin-post.php?action=idlm_download_lead_file&attachment_id='.$payment_attachment;$r=idlm_http_request($path,array(),'owner',false);
    idlm_http_check($r['status']===200 && $r['body']===$payment_png,'Saved screenshot lost or changed');idlm_http_check(stripos($r['headers'],'private, no-store')!==false,'Missing private cache policy');
    foreach(array('other','anonymous')as$actor){idlm_http_denied(static function()use($path,$actor){return idlm_http_request($path,array(),$actor,false);},array(403,404));}
});
idlm_http_case('PAYMENT HTTP correction grant appends proof history and revocation retains evidence',static function()use($payment_lead,$payment_attachment,$payment_png):void{
    global $users,$sessions,$wpdb;
    $manager=new WP_User($users['manager']);$manager->add_cap('idlm_record_lead_payments');$manager->add_cap('idlm_correct_lead_payments');
    $page=idlm_payment_http_page($payment_lead,array(),'manager');$form=idlm_payment_http_form($page['body'],'correct');
    $form=array_merge($form,array('amount'=>'1001','currency'=>'INR','received_date'=>'2026-11-05','method'=>'Bank','reference'=>'CORRECTED-SYNTHETIC','remove_screenshot'=>'1','reason'=>'Corrected actual amount and evidence option'));
    $owner=$form;$owner['_wpnonce']=$sessions['owner']['payment_correct'];idlm_http_denied(static function()use($owner){return idlm_http_request('/wp-admin/admin-post.php',$owner);},array(422));
    idlm_http_check(idlm_http_request('/wp-admin/admin-post.php',$form,'manager')['status']===302,'Manager correction rejected');
    $count=(int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->prefix}idlm_lead_payment_versions WHERE confirmation_id=%d",$form['confirmation_id']));idlm_http_check($count===2,'Correction replaced receipt history');
    $form=idlm_payment_http_form(idlm_payment_http_page($payment_lead,array(),'manager')['body'],'revoke');$form['reason']='Receipt recorded for wrong collection';
    idlm_http_check(idlm_http_request('/wp-admin/admin-post.php',$form,'manager')['status']===302,'Revocation rejected');
    $page=idlm_payment_http_page($payment_lead);idlm_http_check(strpos(str_replace(',', '', $page['body']),'901000.00')!==false,'Revocation did not restore pending balance');
    $r=idlm_http_request('/wp-admin/admin-post.php',array('action'=>'idlm_download_lead_file','attachment_id'=>$payment_attachment),'owner',false);idlm_http_check($r['status']===200 && $r['body']===$payment_png,'Historical proof disappeared after correction and revocation');
});
idlm_http_case('PAYMENT HTTP rendered labels unique and read scope excludes foreign receipt history',static function()use($payment_lead):void{
    $before=idlm_http_snapshot();$page=idlm_payment_http_page($payment_lead);$dom=new DOMDocument();libxml_use_internal_errors(true);$dom->loadHTML($page['body']);libxml_clear_errors();libxml_use_internal_errors(false);$xpath=new DOMXPath($dom);$ids=array();
    foreach($xpath->query('//input[starts-with(@id,"payment-")]')as$input){$id=$input->getAttribute('id');idlm_http_check(!isset($ids[$id]),'Duplicate payment input ID');$ids[$id]=true;idlm_http_check($xpath->query('//label[@for="'.$id.'"]')->length===1,'Missing input label');}
    $foreign=idlm_payment_http_page($payment_lead,array(),'other');idlm_http_check(strpos($foreign['body'],'SYNTHETIC-URN-1')===false,'Foreign receipt disclosed');idlm_http_check($before===idlm_http_snapshot(),'Read changed ledger or business state');
});
// Existing Won lead with a saved revision that predates contractual timeline snapshots.
$wpdb->insert($wpdb->prefix.'idlm_leads',array('first_name'=>'Historic Customer','project_name'=>'Existing Won Project','stage'=>'Won','deal_value'=>'1000000','assigned_rep_id'=>$users['owner'],'created_at'=>'2024-01-01 10:00:00','updated_at'=>'2024-01-01 10:00:00'));$payment_legacy_lead=(int)$wpdb->insert_id;
$wpdb->insert($wpdb->prefix.'idlm_quotes',array('lead_id'=>$payment_legacy_lead,'quote_number'=>'LEGACY-'.wp_generate_uuid4(),'title'=>'Historic agreed quote','created_by'=>$users['owner'],'created_at'=>'2024-01-01 10:00:00','updated_at'=>'2024-01-01 10:00:00'));$payment_legacy_quote=(int)$wpdb->insert_id;
$wpdb->insert($wpdb->prefix.'idlm_quote_revisions',array('quote_id'=>$payment_legacy_quote,'revision_name'=>'Historic R01','document_terms'=>null,'grand_total'=>'1000000','currency_code'=>'INR','created_by'=>$users['owner'],'created_at'=>'2024-01-01 10:00:00','updated_at'=>'2024-01-01 10:00:00'));$payment_legacy_revision=(int)$wpdb->insert_id;$wpdb->update($wpdb->prefix.'idlm_quotes',array('current_revision_id'=>$payment_legacy_revision),array('id'=>$payment_legacy_quote));
$legacy_payload=idlm_payment_http_form(idlm_payment_http_page($payment_legacy_lead,array('payment_revision_id'=>$payment_legacy_revision),'manager')['body'],'resolve_legacy');
$legacy_payload+=array('legacy_milestones'=>array(array('name'=>'Archived advance','percentage'=>'10','advance'=>'1'),array('name'=>'Archived final','percentage'=>'90')),'source_reference'=>'Signed legacy quote PDF archive 2024-17','reason'=>'Historic payment terms confirmed against signed quote','legacy_confirmed'=>'1');
idlm_http_case('PAYMENT HTTP legacy resolution rejects bad nonce ordinary sales and invalid agreed terms',static function()use($legacy_payload,$payment_legacy_lead,$payment_legacy_revision):void{
    global $sessions;
    $owner_page=idlm_payment_http_page($payment_legacy_lead,array('payment_revision_id'=>$payment_legacy_revision));idlm_http_check(strpos($owner_page['body'],'must resolve this legacy quotation')!==false,'Owner lacks recovery guidance');idlm_http_check(strpos($owner_page['body'],'value="idlm_payment_resolve_legacy"')===false,'Recovery action exposed to ordinary sales');
    $bad=$legacy_payload;$bad['_wpnonce']='invalid';idlm_http_denied(static function()use($bad){return idlm_http_request('/wp-admin/admin-post.php',$bad,'manager');},array(403));
    $owner=$legacy_payload;$owner['_wpnonce']=$sessions['owner']['payment_resolve_legacy'];idlm_http_denied(static function()use($owner){return idlm_http_request('/wp-admin/admin-post.php',$owner);},array(422));
    foreach(array(array('legacy_confirmed'=>''),array('source_reference'=>' '),array('legacy_milestones'=>array(array('name'=>'Too much','percentage'=>'101','advance'=>'1'))),array('legacy_milestones'=>array(array('name'=>'No advance selected','percentage'=>'10'))))as$invalid){$p=array_merge($legacy_payload,$invalid);idlm_http_denied(static function()use($p){return idlm_http_request('/wp-admin/admin-post.php',$p,'manager');},array(422));}
});
idlm_http_case('PAYMENT HTTP explicit historic timeline is versioned retry safe and leaves old quote untouched',static function()use($legacy_payload,$payment_legacy_lead,$payment_legacy_revision):void{
    global $wpdb;
    $before=$wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}idlm_quote_revisions WHERE id=%d",$payment_legacy_revision),ARRAY_A);
    idlm_http_check(idlm_http_request('/wp-admin/admin-post.php',$legacy_payload,'manager')['status']===302,'Authorized legacy recovery failed');$snapshot=idlm_http_snapshot();idlm_http_check(idlm_http_request('/wp-admin/admin-post.php',$legacy_payload,'manager')['status']===302,'Legacy retry rejected');idlm_http_check($snapshot===idlm_http_snapshot(),'Legacy retry appended schedule twice');
    idlm_http_check($before===$wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}idlm_quote_revisions WHERE id=%d",$payment_legacy_revision),ARRAY_A),'Historic quotation was rewritten');
    $page=idlm_payment_http_page($payment_legacy_lead);foreach(array('Recovered historic terms','Signed legacy quote PDF archive 2024-17','Archived advance','100000.00')as$token){idlm_http_check(strpos(str_replace(',','',$page['body']),$token)!==false,'Recovery details missing '.$token);}
});
idlm_http_case('PAYMENT HTTP existing Won lead accepts a past positive short receipt with original ownership',static function()use($payment_legacy_lead):void{
    global $wpdb;
    $before=$wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}idlm_leads WHERE id=%d",$payment_legacy_lead),ARRAY_A);$form=idlm_payment_http_form(idlm_payment_http_page($payment_legacy_lead)['body'],'record');$form=array_merge($form,array('amount'=>'99000','currency'=>'INR','received_date'=>'2024-02-15','method'=>'Bank','reference'=>'HISTORIC-URN-2024'));
    idlm_http_check(idlm_http_request('/wp-admin/admin-post.php',$form)['status']===302,'Backdated actual receipt rejected');idlm_http_check($before===$wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}idlm_leads WHERE id=%d",$payment_legacy_lead),ARRAY_A),'Historical Won state or ownership changed');
    $page=idlm_payment_http_page($payment_legacy_lead);foreach(array('901000.00','1000.00','15 Feb 2024')as$token){idlm_http_check(strpos(str_replace(',','',$page['body']),$token)!==false,'Historic balance/date missing '.$token);}
});
