<?php
/** Real cookies, nonces and requests through the design handoff. Builder: Shashi. */
if ('cli' !== PHP_SAPI || strpos($config['db_name'] ?? '', 'idlm_test_') !== 0) { exit(1); }
function idlm_handoff_http_nonce($html, $action): string {
    $dom=new DOMDocument(); libxml_use_internal_errors(true); $dom->loadHTML($html); libxml_clear_errors(); libxml_use_internal_errors(false);
    $xpath=new DOMXPath($dom); $found=$xpath->query('//form[input[@name="action" and @value="'.$action.'"]]//input[@name="_wpnonce"]');
    idlm_http_check($found->length>0,'Form missing: '.$action); return $found->item(0)->getAttribute('value');
}
function idlm_handoff_http_send($lead, $actor, $nonce, $file=null, $fields=array()): array {
    global $config; $body=tempnam($config['content_dir'],'handoff-'); $form=array_merge(array('action'=>'idlm_design_handoff_send','nonce'=>$nonce,'lead_id'=>$lead,'request_key'=>wp_generate_uuid4(),'notes'=>'Synthetic promise: oak wardrobes','scope_summary'=>'Synthetic scope: kitchen','pending_items'=>'None','design_manager_id'=>$GLOBALS['handoff_design'],'amount'=>'99000','received_date'=>'2026-11-05','method'=>'UPI','reference'=>'HANDOFF-'.wp_generate_uuid4()),$fields);
    $args=array('curl','--silent','--show-error','--max-time','15','--cookie',$config['content_dir'].'/'.$actor.'.cookies','--output',$body,'--write-out','%{http_code}');
    foreach($form as $key=>$value){$args[]='-F';$args[]=$key.'='.$value;} if($file){$args[]='-F';$args[]='advance_screenshot=@'.$file.';type=image/png';} $args[]=$config['http_url'].'/wp-admin/admin-ajax.php';
    $proc=proc_open(implode(' ',array_map('escapeshellarg',$args)),array(1=>array('pipe','w'),2=>array('pipe','w')),$pipes);$status=stream_get_contents($pipes[1]);fclose($pipes[1]);fclose($pipes[2]);proc_close($proc);
    $result=array('status'=>(int)$status,'body'=>(string)file_get_contents($body));unlink($body);return $result;
}
function idlm_handoff_http_doc($handoff, $slot, $path, $actor, $nonce, $title=''): array {
    global $config; $body=tempnam($config['content_dir'],'doc-');
    $args=array('curl','--silent','--show-error','--max-time','20','--cookie',$config['content_dir'].'/'.$actor.'.cookies','--output',$body,'--write-out','%{http_code}');
    foreach(array('action'=>'idlm_design_doc_upload','_wpnonce'=>$nonce,'handoff_id'=>$handoff,'slot_key'=>$slot,'title'=>$title) as $key=>$value){$args[]='-F';$args[]=$key.'='.$value;} $args[]='-F';$args[]='design_file=@'.$path; $args[]=$config['http_url'].'/wp-admin/admin-post.php';
    $proc=proc_open(implode(' ',array_map('escapeshellarg',$args)),array(1=>array('pipe','w'),2=>array('pipe','w')),$pipes);$status=stream_get_contents($pipes[1]);fclose($pipes[1]);fclose($pipes[2]);proc_close($proc);
    $result=array('status'=>(int)$status,'body'=>(string)file_get_contents($body));unlink($body);return $result;
}
function idlm_handoff_http_row($lead) { global $wpdb; wp_cache_flush(); return $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}idlm_design_handoffs WHERE lead_id=%d",$lead)); }
$handoff_owner=new WP_User($users['owner']); $handoff_owner->add_cap('idlm_record_lead_payments');
$handoff_other=new WP_User($users['other']); $handoff_other->add_cap('idlm_record_lead_payments');
$handoff_now=current_time('mysql');
$wpdb->insert($wpdb->prefix.'idlm_leads',array('first_name'=>'Handoff Synthetic','phone'=>'+15550009001','project_name'=>'Handoff Residence','stage'=>'Won','deal_value'=>'1000000','assigned_rep_id'=>$users['owner'],'created_at'=>$handoff_now));
$handoff_lead=(int)$wpdb->insert_id;
$wpdb->insert($wpdb->prefix.'idlm_quotes',array('lead_id'=>$handoff_lead,'quote_number'=>'HO-'.wp_generate_uuid4(),'title'=>'Handoff quote','created_by'=>$users['owner'],'created_at'=>$handoff_now,'updated_at'=>$handoff_now)); $handoff_quote=(int)$wpdb->insert_id;
$wpdb->insert($wpdb->prefix.'idlm_quote_revisions',array('quote_id'=>$handoff_quote,'revision_name'=>'Handoff R01','document_terms'=>IDLM_Quotation_Document::capture(array('payment_structure'=>'[{"name":"Booking advance","percentage":10},{"name":"Final","percentage":90}]'),'1000000'),'grand_total'=>'1000000','currency_code'=>'INR','created_by'=>$users['owner'],'created_at'=>$handoff_now)); $handoff_revision=(int)$wpdb->insert_id;
$wpdb->update($wpdb->prefix.'idlm_quotes',array('current_revision_id'=>$handoff_revision),array('id'=>$handoff_quote));
// Design-only person: the optional role plus nothing else.
idlm_register_design_role();
$handoff_design=(int)wp_insert_user(array('user_login'=>'fixture_design','user_pass'=>'idlm-synthetic-password','user_email'=>'design@example.test','role'=>IDLM_Design_Handoffs::ROLE)); $GLOBALS['handoff_design']=$handoff_design;
$handoff_rep=(int)wp_insert_user(array('user_login'=>'fixture_rep','user_pass'=>'idlm-synthetic-password','user_email'=>'rep@example.test','role'=>IDLM_Design_Handoffs::REP_ROLE));
idlm_http_check($handoff_rep>0&&idlm_http_request('/login',array('user_login'=>'fixture_rep','user_password'=>'idlm-synthetic-password'),'rep')['status']===302,'Rep login failed');
idlm_http_check($handoff_design>0&&idlm_http_request('/login',array('user_login'=>'fixture_design','user_password'=>'idlm-synthetic-password'),'design')['status']===302,'Design login failed');
foreach (array('owner','other','design','rep') as $handoff_actor) { idlm_http_request('/wp-admin/admin-post.php',array('action'=>'idlm_fixture_noop'),$handoff_actor); }
$handoff_png=$config['content_dir'].'/handoff.png'; file_put_contents($handoff_png,base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+aKfoAAAAASUVORK5CYII='));
$handoff_sessions=array(); foreach (array('owner','other','design') as $handoff_actor) { $handoff_sessions[$handoff_actor]=json_decode(idlm_http_request('/session',array(),$handoff_actor,false)['body'],true); }
idlm_http_case('HANDOFF HTTP bad nonce, foreign rep and receiver cannot send',static function()use($handoff_lead,$handoff_sessions):void{
    idlm_http_check(idlm_handoff_http_send($handoff_lead,'owner','invalid')['status']===403,'Bad nonce accepted');
    idlm_http_check(idlm_handoff_http_send($handoff_lead,'other',$handoff_sessions['other']['design_send'])['status']===403,'Foreign rep sent');
    idlm_http_check(idlm_handoff_http_send($handoff_lead,'design',$handoff_sessions['design']['design_send'])['status']===403,'Receiver sent');
    idlm_http_check(null===idlm_handoff_http_row($handoff_lead),'Denied send wrote a row');
});
idlm_http_case('HANDOFF HTTP missing notes and evidence keep the form and save nothing',static function()use($handoff_lead,$handoff_sessions):void{
    $r=idlm_handoff_http_send($handoff_lead,'owner',$handoff_sessions['owner']['design_send'],null,array('notes'=>'  ')); idlm_http_check($r['status']===422&&strpos($r['body'],'Remarks for design')!==false,'Blank notes accepted');
    $r=idlm_handoff_http_send($handoff_lead,'owner',$handoff_sessions['owner']['design_send'],null,array('reference'=>'')); idlm_http_check($r['status']===422&&strpos($r['body'],'transaction reference')!==false,'Missing evidence accepted');
    idlm_http_check(null===idlm_handoff_http_row($handoff_lead),'Rejected send wrote a row');
});
idlm_http_case('HANDOFF HTTP screenshot send records the advance and leaves the lead unchanged',static function()use($handoff_lead,$handoff_sessions,$handoff_png):void{
    global $wpdb; $before=$wpdb->get_row($wpdb->prepare("SELECT stage,assigned_rep_id,deal_value,lead_quality FROM {$wpdb->prefix}idlm_leads WHERE id=%d",$handoff_lead),ARRAY_A);
    $r=idlm_handoff_http_send($handoff_lead,'owner',$handoff_sessions['owner']['design_send'],$handoff_png,array('reference'=>'')); idlm_http_check($r['status']===200,'Send failed: '.$r['body']);
    $row=idlm_handoff_http_row($handoff_lead); idlm_http_check($row&&'new'===$row->status&&(int)$row->receipt_confirmation_id>0&&(int)$row->advance_attachment_id>0,'Handoff or receipt missing');
    idlm_http_check($before===$wpdb->get_row($wpdb->prepare("SELECT stage,assigned_rep_id,deal_value,lead_quality FROM {$wpdb->prefix}idlm_leads WHERE id=%d",$handoff_lead),ARRAY_A),'Send changed the lead');
    idlm_http_check(idlm_handoff_http_send($handoff_lead,'owner',$handoff_sessions['owner']['design_send'])['status']===422,'Second send accepted');
});
idlm_http_case('HANDOFF HTTP design-only person sees the queue and proof but no lead screens',static function()use($handoff_lead):void{
    $row=idlm_handoff_http_row($handoff_lead);
    $page=idlm_http_request('/crm-workspace',array('tab'=>'new','idlm_view'=>'design-team'),'design',false); idlm_http_check($page['status']===200&&strpos($page['body'],'Handoff Synthetic')!==false,'Queue missing'); 
    foreach(array('idlm_view=leads','idlm_view=pipeline','idlm_view=payments','idlm_view=quotations') as $hidden){idlm_http_check(strpos($page['body'],$hidden)===false,'Lead screen shown: '.$hidden);}
    $forced=idlm_http_request('/crm-workspace',array('idlm_view'=>'leads'),'design',false); idlm_http_check(strpos($forced['body'],'idlm-design-team')!==false,'Forced view was not contained');
    $detail=idlm_http_request('/crm-workspace',array('idlm_view'=>'design-team','handoff'=>$row->id),'design',false); foreach(array('Synthetic promise','+15550009001','99,000.00','1,000.00') as $text){idlm_http_check(strpos($detail['body'],$text)!==false,'Detail missing '.$text);}
    idlm_http_check(idlm_http_request('/wp-admin/admin-post.php',array('action'=>'idlm_download_lead_file','attachment_id'=>$row->advance_attachment_id),'design',false)['status']===200,'Receiver cannot open proof');
});
idlm_http_case('HANDOFF HTTP a sales rep without the permission has no tab, no proof and cannot decide',static function()use($handoff_lead):void{
    $row=idlm_handoff_http_row($handoff_lead);
    $page=idlm_http_request('/crm-workspace',array('idlm_view'=>'design-team','handoff'=>$row->id),'other',false); idlm_http_check(strpos($page['body'],'Synthetic promise')===false&&strpos($page['body'],'idlm-design-team')===false,'Queue disclosed');
    idlm_http_check(idlm_http_request('/wp-admin/admin-post.php',array('action'=>'idlm_design_handoff_accept','handoff_id'=>$row->id,'designer_id'=>1),'other')['status']===403,'Rep decided');
    idlm_http_check(idlm_http_request('/wp-admin/admin-post.php',array('action'=>'idlm_download_lead_file','attachment_id'=>$row->advance_attachment_id),'other',false)['status']===404,'Foreign rep opened proof');
    idlm_http_check('new'===idlm_handoff_http_row($handoff_lead)->status,'Status changed');
});
idlm_http_case('HANDOFF HTTP return, resend, accept and assign from real forms, with rep scope',static function()use($handoff_lead,$handoff_sessions,$handoff_design,$handoff_rep):void{
    $row=idlm_handoff_http_row($handoff_lead); $detail=array('idlm_view'=>'design-team','handoff'=>$row->id); $html=idlm_http_request('/crm-workspace',$detail,'design',false)['body'];
    $page=idlm_http_request('/crm-workspace',$detail,'rep',false); idlm_http_check(strpos($page['body'],'Synthetic promise')===false,'Unassigned rep saw the handoff');
    idlm_http_check(idlm_http_request('/wp-admin/admin-post.php',array('action'=>'idlm_design_handoff_accept','handoff_id'=>$row->id),'rep')['status']===403,'Rep decided');
    idlm_http_check(idlm_http_request('/wp-admin/admin-post.php',array('action'=>'idlm_design_handoff_accept','handoff_id'=>$row->id),'design')['status']===403,'Accept without nonce');
    idlm_http_check(idlm_http_request('/wp-admin/admin-post.php',array('action'=>'idlm_design_handoff_send_back','_wpnonce'=>idlm_handoff_http_nonce($html,'idlm_design_handoff_send_back'),'handoff_id'=>$row->id,'reason'=>'Floor plan missing'),'design')['status']===302,'Return failed');
    idlm_http_check('sent_back'===idlm_handoff_http_row($handoff_lead)->status,'Not returned');
    $r=idlm_handoff_http_send($handoff_lead,'owner',$handoff_sessions['owner']['design_send'],null,array('notes'=>'Floor plan added')); idlm_http_check($r['status']===200,'Resend failed: '.$r['body']);
    $row=idlm_handoff_http_row($handoff_lead); idlm_http_check('new'===$row->status&&'Floor plan added'===$row->notes&&2===(int)$row->version_no,'Resend did not reopen');
    $html=idlm_http_request('/crm-workspace',$detail,'design',false)['body']; idlm_http_check(strpos($html,'Synthetic promise')!==false&&strpos($html,'Earlier submissions')!==false,'History missing');
    idlm_http_check(idlm_http_request('/wp-admin/admin-post.php',array('action'=>'idlm_design_handoff_accept','_wpnonce'=>idlm_handoff_http_nonce($html,'idlm_design_handoff_accept'),'handoff_id'=>$row->id,'designer_id'=>$handoff_rep,'target_date'=>'2027-01-31'),'design')['status']===302,'Accept failed');
    $row=idlm_handoff_http_row($handoff_lead); idlm_http_check('accepted'===$row->status&&(int)$row->designer_id===$handoff_rep&&'2027-01-31'===$row->target_date,'Not accepted and assigned');
    $page=idlm_http_request('/crm-workspace',$detail,'rep',false); idlm_http_check(strpos($page['body'],'Floor plan added')!==false,'Assigned rep cannot see');
    idlm_http_check(strpos($page['body'],'idlm_design_handoff_assign')===false,'Rep offered manager actions');
    foreach(array('idlm_view=leads','idlm_view=payments') as $hidden){idlm_http_check(strpos($page['body'],$hidden)===false,'Rep shown '.$hidden);}
    idlm_http_check(idlm_http_request('/wp-admin/admin-post.php',array('action'=>'idlm_download_lead_file','attachment_id'=>$row->advance_attachment_id),'rep',false)['status']===200,'Assigned rep cannot open proof');
});
idlm_http_case('DESIGNREV HTTP a rep edits with real forms, cannot change a discount, and sales stays untouched',static function()use($handoff_lead,$handoff_rep,$handoff_quote,$handoff_revision):void{
    global $wpdb,$config,$handoff_sessions; $row=idlm_handoff_http_row($handoff_lead); $p=$wpdb->prefix; wp_cache_flush();
    $quote_before=$wpdb->get_row($wpdb->prepare("SELECT * FROM {$p}idlm_quotes WHERE id=%d",$handoff_quote),ARRAY_A); $lead_before=$wpdb->get_row($wpdb->prepare("SELECT quote_value,deal_value,stage FROM {$p}idlm_leads WHERE id=%d",$handoff_lead),ARRAY_A);
    $sections=array(array('key'=>'general','section_name'=>'General')); $line=array('section_key'=>'general','row_type'=>'item','item_name'=>'Wardrobe','description'=>'Wardrobe line','quantity'=>'2','unit'=>'Nos','unit_rate'=>'500','is_wood'=>'1');
    $page=idlm_http_request('/crm-workspace',array('idlm_view'=>'design-team','handoff'=>$row->id,'design_quote'=>'edit'),'rep',false); idlm_http_check($page['status']===200&&strpos($page['body'],'You can change line rates')!==false&&strpos($page['body'],'data-lock-rates')===false,'Rep editor missing or rates still locked');
    foreach(array('idlm-lead-search','idlm-quote-lead','idlm-quote-sections','idlm-add-section','idlm-open-bulk-items','idlm-bulk-items-modal','idlm-add-bulk-items','idlm-live-totals','idlm-wood-totals','idlm-section-totals') as $anchor){idlm_http_check(strpos($page['body'],'id="'.$anchor.'"')!==false,'Editor script anchor missing: '.$anchor);}
    idlm_http_check(strpos($page['body'],'name="wooden_discount_percent"')!==false&&(bool)preg_match('/name="wooden_discount_percent"[^>]*readonly/',$page['body']),'Discount not read-only');
    $nonce=idlm_handoff_http_nonce($page['body'],'idlm_design_quote_save');
    $form=array('action'=>'idlm_design_quote_save','_wpnonce'=>$nonce,'handoff_id'=>$row->id,'revision_id'=>$handoff_revision,'sections'=>$sections,'items'=>array($line),'wooden_discount_percent'=>'0','additional_discount_amount'=>'0','revision_reason'=>'Added scope from the site visit');
    $denied=$form; $denied['wooden_discount_percent']='20'; $denied['save_request_key']=wp_generate_uuid4(); idlm_http_check(idlm_http_request('/wp-admin/admin-post.php',$denied,'rep')['status']===422,'Rep discount accepted');
    $form['save_request_key']=wp_generate_uuid4(); $saved=idlm_http_request('/wp-admin/admin-post.php',$form,'rep'); idlm_http_check($saved['status']===302,'Rep save failed '.$saved['status'].' '.substr($saved['body'],0,200));
    wp_cache_flush(); $row=idlm_handoff_http_row($handoff_lead); idlm_http_check((int)$row->design_revision_id>0,'Pointer not set');
    idlm_http_check($quote_before===$wpdb->get_row($wpdb->prepare("SELECT * FROM {$p}idlm_quotes WHERE id=%d",$handoff_quote),ARRAY_A),'Quotation row changed');
    idlm_http_check($lead_before===$wpdb->get_row($wpdb->prepare("SELECT quote_value,deal_value,stage FROM {$p}idlm_leads WHERE id=%d",$handoff_lead),ARRAY_A),'Lead changed');
    $detail=idlm_http_request('/crm-workspace',array('idlm_view'=>'design-team','handoff'=>$row->id),'rep',false)['body']; idlm_http_check(strpos($detail,'-DES')!==false&&strpos($detail,'Latest design price')!==false,'Revision not shown');
    // Waiting for the manager's review: no print link for the rep yet.
    $print_link='~/wp-admin/admin-post\.php\?[^"\']*action=idlm_design_quote_print[^"\']*revision_id='.(int)$row->design_revision_id.'[^"\']*~';
    idlm_http_check(!preg_match($print_link,$detail)&&strpos($detail,'Waiting for review')!==false&&strpos($detail,'idlm_design_quote_review')===false,'Unreviewed revision printable by the rep, or rep offered the review');
    $manager=idlm_http_request('/crm-workspace',array('idlm_view'=>'design-team','handoff'=>$row->id),'design',false)['body']; idlm_http_check(strpos($manager,'idlm_design_quote_review')!==false,'Manager not offered the review');
    $reviewed=idlm_http_request('/wp-admin/admin-post.php',array('action'=>'idlm_design_quote_review','_wpnonce'=>idlm_handoff_http_nonce($manager,'idlm_design_quote_review'),'handoff_id'=>$row->id,'revision_id'=>$row->design_revision_id),'design'); idlm_http_check($reviewed['status']===302,'Review failed '.$reviewed['status']);
    $detail=idlm_http_request('/crm-workspace',array('idlm_view'=>'design-team','handoff'=>$row->id),'rep',false)['body'];
    idlm_http_check((bool)preg_match($print_link,$detail,$link),'Print link missing after review');
    $print=idlm_http_request(html_entity_decode($link[0]),array(),'rep',false); idlm_http_check($print['status']===200&&strpos($print['body'],'<dt>Designer:</dt>')!==false&&strpos($print['body'],'-DES')!==false,'Design print failed '.$print['status']);
    $foreign=$form; $foreign['save_request_key']=wp_generate_uuid4(); $foreign['_wpnonce']=$handoff_sessions['other']['design_send']; $other=idlm_http_request('/wp-admin/admin-post.php',$foreign,'other'); idlm_http_check($other['status']===403,'Foreign rep saved '.$other['status']);
});
idlm_http_case('APPROVED HTTP preview, confirm, later payment and rep limits use real forms',static function()use($handoff_lead):void{
    global $wpdb; wp_cache_flush(); $row=idlm_handoff_http_row($handoff_lead); $revision=(int)$row->design_revision_id; $detail=array('idlm_view'=>'design-team','handoff'=>$row->id);
    $rep=idlm_http_request('/crm-workspace',array_merge($detail,array('design_approve'=>$revision)),'rep',false)['body']; idlm_http_check(strpos($rep,'idlm_design_quote_approve')===false,'Rep offered approval');
    $page=idlm_http_request('/crm-workspace',$detail,'rep',false)['body']; idlm_http_check(strpos($page,'Total outstanding')!==false,'Rep cannot see balances'); idlm_http_check(strpos($page,'idlm_design_payment_record')===false,'Rep offered payment form');
    $preview=idlm_http_request('/crm-workspace',array_merge($detail,array('design_approve'=>$revision)),'design',false)['body']; idlm_http_check(strpos($preview,'New agreed price')!==false,'Preview missing');
    $version=(int)$wpdb->get_var($wpdb->prepare("SELECT id FROM {$wpdb->prefix}idlm_lead_payment_events WHERE lead_id=%d ORDER BY id DESC LIMIT 1",$handoff_lead));
    $form=array('action'=>'idlm_design_quote_approve','_wpnonce'=>idlm_handoff_http_nonce($preview,'idlm_design_quote_approve'),'handoff_id'=>$row->id,'approve_revision_id'=>$revision,'payment_version'=>$version,'request_key'=>wp_generate_uuid4(),'approval_note'=>'Approved by email');
    idlm_http_check(idlm_http_request('/wp-admin/admin-post.php',$form,'rep')['status']===403,'Rep approved');
    idlm_http_check(idlm_http_request('/wp-admin/admin-post.php',$form,'design')['status']===302,'Approval failed');
    wp_cache_flush(); $row=idlm_handoff_http_row($handoff_lead); idlm_http_check((int)$row->agreed_revision_id===$revision,'Agreed price not set');
    $page=idlm_http_request('/crm-workspace',$detail,'design',false)['body']; idlm_http_check(strpos($page,'Agreed price')!==false,'Agreed price not shown');
    $version=(int)$wpdb->get_var($wpdb->prepare("SELECT id FROM {$wpdb->prefix}idlm_lead_payment_events WHERE lead_id=%d ORDER BY id DESC LIMIT 1",$handoff_lead));
    $pay=array('action'=>'idlm_design_payment_record','_wpnonce'=>idlm_handoff_http_nonce($page,'idlm_design_payment_record'),'handoff_id'=>$row->id,'payment_version'=>$version,'request_key'=>wp_generate_uuid4(),'amount'=>'100','received_date'=>'2026-11-05','method'=>'UPI','reference'=>'HTTP-LATER-1');
    idlm_http_check(idlm_http_request('/wp-admin/admin-post.php',$pay,'design')['status']===302,'Later payment failed');
    idlm_http_check(idlm_http_request('/wp-admin/admin-post.php',array_merge($pay,array('reference'=>'','request_key'=>wp_generate_uuid4())),'design')['status']===422,'Missing evidence accepted');
    idlm_http_check(idlm_http_request('/wp-admin/admin-post.php',array_merge($pay,array('request_key'=>wp_generate_uuid4())),'rep')['status']===403,'Rep recorded');
});
idlm_http_case('STAGES HTTP real DWG upload, type checks, private download and stage move',static function()use($handoff_lead):void{
    global $wpdb,$config,$handoff_rep; wp_cache_flush(); $row=idlm_handoff_http_row($handoff_lead); $detail=array('idlm_view'=>'design-team','handoff'=>$row->id);
    $page=idlm_http_request('/crm-workspace',$detail,'rep',false)['body']; idlm_http_check(strpos($page,'Move to')!==false&&strpos($page,'idlm-design-steps')!==false,'Stage panel missing');
    idlm_http_check(strpos($page,'value="back"')===false,'Rep offered move back');
    $nonce=idlm_handoff_http_nonce($page,'idlm_design_stage_action'); idlm_http_check(idlm_http_request('/wp-admin/admin-post.php',array('action'=>'idlm_design_stage_action','_wpnonce'=>$nonce,'handoff_id'=>$row->id,'stage_action'=>'advance'),'rep')['status']===302,'First move failed');
    idlm_http_check(idlm_http_request('/wp-admin/admin-post.php',array('action'=>'idlm_design_stage_action','_wpnonce'=>$nonce,'handoff_id'=>$row->id,'stage_action'=>'advance'),'rep')['status']===302,'Second move failed');
    $dir=$config['content_dir']; file_put_contents($dir.'/good.dwg','AC1032'.str_repeat("\0",200)); file_put_contents($dir.'/fake.dwg','<?php echo 1;'); file_put_contents($dir.'/script.php','<?php echo 1;');
    $page=idlm_http_request('/crm-workspace',$detail,'rep',false)['body']; $upload=idlm_handoff_http_nonce($page,'idlm_design_doc_upload');
    idlm_http_check(idlm_handoff_http_doc($row->id,'masking_dwg',$dir.'/fake.dwg','rep',$upload)['status']===422,'Fake DWG accepted');
    idlm_http_check(idlm_handoff_http_doc($row->id,'masking_dwg',$dir.'/script.php','rep',$upload)['status']===422,'PHP accepted');
    $good=idlm_handoff_http_doc($row->id,'masking_dwg',$dir.'/good.dwg','rep',$upload); idlm_http_check($good['status']===302||$good['status']===303,'DWG rejected '.$good['status'].' '.substr($good['body'],0,200));
    wp_cache_flush(); $attachment=(int)$wpdb->get_var($wpdb->prepare("SELECT attachment_id FROM {$wpdb->prefix}idlm_design_document_versions WHERE handoff_id=%d AND slot_key='masking_dwg' AND state='current'",$row->id)); idlm_http_check($attachment>0,'Version not saved');
    $download=array('action'=>'idlm_download_lead_file','attachment_id'=>$attachment);
    $file=idlm_http_request('/wp-admin/admin-post.php',$download,'rep',false); idlm_http_check($file['status']===200&&strpos($file['body'],'AC1032')===0&&stripos($file['headers'],'attachment')!==false,'Rep download failed');
    idlm_http_check(idlm_http_request('/wp-admin/admin-post.php',$download,'owner',false)['status']===404,'Sales owner opened an internal design file');
    idlm_http_check(idlm_http_request('/wp-admin/admin-post.php',$download,'other',false)['status']===404,'Foreign rep opened a design file');
    idlm_http_check(idlm_http_request('/wp-admin/admin-post.php',$download,'design',false)['status']===200,'Manager download failed');
    $settings=idlm_http_request('/crm-workspace',array('idlm_view'=>'design-team','design_settings'=>'1'),'design',false)['body']; idlm_http_check(strpos($settings,'idlm_design_stage_settings')===false,'Manager offered stage settings');
});
idlm_http_case('BOARD HTTP board and table by role, search, and a refused move',static function()use($handoff_lead):void{
    wp_cache_flush(); $row=idlm_handoff_http_row($handoff_lead); $view=array('idlm_view'=>'design-team');
    $board=idlm_http_request('/crm-workspace',$view,'rep',false)['body']; idlm_http_check(strpos($board,'idlm-design-board')!==false&&strpos($board,'Handoff Synthetic')!==false,'Rep board missing');
    idlm_http_check(strpos($board,'tab=new')===false,'Rep offered manager queues'); idlm_http_check(strpos($board,'design-board.js')!==false,'Board script not loaded');
    $list=idlm_http_request('/crm-workspace',array_merge($view,array('tab'=>'list')),'rep',false)['body']; idlm_http_check(strpos($list,'idlm-design-projects')!==false&&strpos($list,'Handoff Synthetic')!==false,'List missing');
    $none=idlm_http_request('/crm-workspace',array_merge($view,array('tab'=>'list','q'=>'no such customer')),'rep',false)['body']; idlm_http_check(strpos($none,'No projects match')!==false,'Search did not narrow');
    $manager=idlm_http_request('/crm-workspace',$view,'design',false)['body']; idlm_http_check(strpos($manager,'idlm-design-projects')!==false&&strpos($manager,'queue=new')!==false&&strpos($manager,'Needs a designer')!==false&&strpos($manager,'design-table.js')!==false,'Manager table missing');
    idlm_http_check(strpos($board,'queue=new')===false&&strpos($board,'idlm-design-inline')===false,'Rep offered manager queues or inline edits');
    idlm_http_check(strpos(idlm_http_request('/crm-workspace',array_merge($view,array('tab'=>'list')),'owner',false)['body'],'idlm-design-projects')===false,'Sales rep saw the project list');
    $nonce=json_decode(idlm_http_request('/session',array(),'rep',false)['body'],true)['stage_action'];
    $stage=IDLM_Design_Stages::stage_of($row); $move=array('action'=>'idlm_design_stage_move','nonce'=>$nonce,'handoff_id'=>$row->id,'from_stage'=>$stage);
    idlm_http_check(idlm_http_request('/wp-admin/admin-ajax.php',array_merge($move,array('nonce'=>'bad')),'rep')['status']===403,'Bad nonce accepted');
    $refused=idlm_http_request('/wp-admin/admin-ajax.php',$move,'rep'); idlm_http_check($refused['status']===422&&strpos($refused['body'],'idlm_design_stage_checklist')!==false&&strpos($refused['body'],'Still needed')!==false,'Unmet move not refused: '.$refused['status'].' '.substr($refused['body'],0,200));
    wp_cache_flush(); idlm_http_check($stage===IDLM_Design_Stages::stage_of(idlm_handoff_http_row($handoff_lead)),'Refused move changed the stage');
    $foreign=json_decode(idlm_http_request('/session',array(),'other',false)['body'],true)['stage_action']; idlm_http_check(idlm_http_request('/wp-admin/admin-ajax.php',array_merge($move,array('nonce'=>$foreign)),'other')['status']===403,'Foreign rep moved a card');
});
idlm_http_case('TABLE HTTP inline designer saves: manager only, valid nonce, reason to reassign',static function()use($handoff_lead):void{
    wp_cache_flush(); $row=idlm_handoff_http_row($handoff_lead); $rep=(int)get_user_by('login','fixture_rep')->ID; $manager=(int)$GLOBALS['handoff_design'];
    idlm_http_check((int)$row->designer_id===$rep,'Fixture is not assigned to the rep');
    $nonce=json_decode(idlm_http_request('/session',array(),'design',false)['body'],true)['design_assign'];
    $save=array('action'=>'idlm_design_handoff_decide','operation'=>'assign','_wpnonce'=>$nonce,'handoff_id'=>$row->id,'designer_id'=>$manager,'target_date'=>(string)$row->target_date);
    $repnonce=json_decode(idlm_http_request('/session',array(),'rep',false)['body'],true)['design_assign'];
    idlm_http_check(idlm_http_request('/wp-admin/admin-ajax.php',array_merge($save,array('_wpnonce'=>$repnonce)),'rep')['status']===403,'Designer reassigned a project');
    idlm_http_check(idlm_http_request('/wp-admin/admin-ajax.php',array_merge($save,array('_wpnonce'=>'bad')),'design')['status']===403,'Bad nonce accepted');
    idlm_http_check(idlm_http_request('/wp-admin/admin-ajax.php',array_merge($save,array('operation'=>'delete')),'design')['status']===400,'Unknown operation accepted');
    $refused=idlm_http_request('/wp-admin/admin-ajax.php',$save,'design'); idlm_http_check($refused['status']===422&&strpos($refused['body'],'"code":"reason"')!==false,'Reassign without a reason: '.$refused['status'].' '.substr($refused['body'],0,200));
    wp_cache_flush(); idlm_http_check((int)idlm_handoff_http_row($handoff_lead)->designer_id===$rep,'Refused reassign changed the designer');
    $saved=idlm_http_request('/wp-admin/admin-ajax.php',array_merge($save,array('reason'=>'Synthetic workload balance')),'design'); idlm_http_check($saved['status']===200&&strpos($saved['body'],'Designer saved')!==false,'Reassign failed: '.substr($saved['body'],0,200));
    wp_cache_flush(); idlm_http_check((int)idlm_handoff_http_row($handoff_lead)->designer_id===$manager,'Reassign not stored');
    $back=idlm_http_request('/wp-admin/admin-post.php',array_merge($save,array('action'=>'idlm_design_handoff_assign','designer_id'=>$rep,'reason'=>'Synthetic restore','stay'=>'1','return_url'=>'/crm-workspace?idlm_view=design-team&tab=projects')),'design');
    idlm_http_check($back['status']===302&&strpos($back['headers'],'design_notice=assigned')!==false&&strpos($back['headers'],'handoff=')===false,'Form post did not stay on the table');
    wp_cache_flush(); idlm_http_check((int)idlm_handoff_http_row($handoff_lead)->designer_id===$rep,'Form post did not restore the designer');
});
idlm_http_case('PANEL HTTP quick panel by scope, project page dialogs, and navigation that starts fresh',static function()use($handoff_lead):void{
    wp_cache_flush(); $row=idlm_handoff_http_row($handoff_lead); $panel=array('action'=>'idlm_design_project_panel','handoff_id'=>$row->id);
    $nonce=static function($actor){ return json_decode(idlm_http_request('/session',array(),$actor,false)['body'],true)['design_panel']; };
    $rep=idlm_http_request('/wp-admin/admin-ajax.php',array_merge($panel,array('nonce'=>$nonce('rep'))),'rep'); idlm_http_check($rep['status']===200&&strpos($rep['body'],'Handoff Synthetic')!==false&&strpos($rep['body'],'Open full project')!==false,'Designer panel missing: '.$rep['status']);
    idlm_http_check(idlm_http_request('/wp-admin/admin-ajax.php',array_merge($panel,array('nonce'=>$nonce('design'))),'design')['status']===200,'Manager panel missing');
    $foreign=idlm_http_request('/wp-admin/admin-ajax.php',array_merge($panel,array('nonce'=>$nonce('other'))),'other'); idlm_http_check($foreign['status']===404&&strpos($foreign['body'],'Handoff Synthetic')===false,'Out-of-scope panel shown: '.$foreign['status']);
    idlm_http_check(idlm_http_request('/wp-admin/admin-ajax.php',array_merge($panel,array('handoff_id'=>'999999','nonce'=>$nonce('design'))),'design')['status']===404,'Missing project not refused');
    idlm_http_check(idlm_http_request('/wp-admin/admin-ajax.php',array_merge($panel,array('nonce'=>'bad')),'rep')['status']===403,'Bad nonce accepted');
    $page=idlm_http_request('/crm-workspace',array('idlm_view'=>'design-team','handoff'=>$row->id,'tab'=>'board'),'design',false)['body'];
    idlm_http_check(strpos($page,'id="idlm-design-back-dialog"')!==false&&strpos($page,'design-detail.js')!==false&&strpos($page,'<summary>Put on hold')===false,'Project page dialogs missing');
    preg_match_all('/class="idlm-workspace-nav-item[^"]*"[^>]*href="([^"]+)"|href="([^"]+)"[^>]*class="idlm-workspace-nav-item/',$page,$links); $hrefs=array_filter(array_merge($links[1],$links[2]));
    idlm_http_check($hrefs!==array(),'Navigation links not found');
    foreach($hrefs as $href){ idlm_http_check(strpos($href,'handoff=')===false&&strpos($href,'tab=')===false,'Navigation kept the open project: '.$href); }
    $board=idlm_http_request('/crm-workspace',array('idlm_view'=>'design-team'),'rep',false)['body']; idlm_http_check(strpos($board,'data-design-panel=')!==false&&strpos($board,'id="idlm-design-project-panel"')!==false&&strpos($board,'design-panel.js')!==false,'Board panel missing');
});
idlm_http_case('STAGES HTTP administrators add and remove stages from the settings screen; others cannot',static function()use($handoff_lead):void{
    idlm_http_check(idlm_http_request('/login',array('user_login'=>'idlm_admin','user_password'=>'idlm-integration-password'),'admin')['status']===302,'Administrator login failed');
    $view=array('idlm_view'=>'design-team','design_settings'=>'1');
    $page=idlm_http_request('/crm-workspace',$view,'admin',false)['body'];
    idlm_http_check(strpos($page,'data-design-stage-settings')!==false&&strpos($page,'design-stage-settings.js')!==false&&strpos($page,'New stage name')!==false,'Stage settings screen missing');
    preg_match('/name="settings_fingerprint" value="([a-f0-9]{32})"/',$page,$print); idlm_http_check(!empty($print[1]),'Fingerprint missing');
    $post=array('action'=>'idlm_design_stage_settings','_wpnonce'=>idlm_handoff_http_nonce($page,'idlm_design_stage_settings'),'settings_fingerprint'=>$print[1],'return_url'=>'/crm-workspace?idlm_view=design-team');
    $i=0; foreach (IDLM_Design_Stages::stages() as $key=>$stage) { $r=$stage['requires']; $post["stages[$i][key]"]=$key; $post["stages[$i][label]"]=$stage['label']; $post["stages[$i][payment]"]=$r['payment']; if ($r['designer']) { $post["stages[$i][designer]"]='1'; } if ($r['tentative_quote']) { $post["stages[$i][tentative_quote]"]='1'; } if ($r['final_quote']) { $post["stages[$i][final_quote]"]='1'; } foreach ($stage['slots'] as $n=>$slot) { $post["stages[$i][slots][$n]"]=$slot; } $i++; }
    $post["stages[$i][key]"]=''; $post["stages[$i][label]"]='Handover review';
    try {
        idlm_http_check(idlm_http_request('/wp-admin/admin-post.php',$post,'design')['status']===403,'A Design Manager changed stages');
        $saved=idlm_http_request('/wp-admin/admin-post.php',$post,'admin'); idlm_http_check($saved['status']===302&&strpos($saved['headers'],'design_notice=settings')!==false,'Stage save failed: '.$saved['status'].' '.substr(strip_tags($saved['body']),0,200));
        wp_cache_flush(); idlm_http_check(isset(IDLM_Design_Stages::stages()['stage_handover_review']),'New stage not stored');
        $again=idlm_http_request('/wp-admin/admin-post.php',$post,'admin'); idlm_http_check($again['status']===422&&strpos($again['body'],'changed in another window')!==false,'A stale form overwrote stages');
        $board=idlm_http_request('/crm-workspace',array('idlm_view'=>'design-team','tab'=>'board'),'design',false)['body']; idlm_http_check(strpos($board,'Handover review')!==false,'New stage column missing');
    } finally { delete_option(IDLM_Design_Stages::OPTION); wp_cache_flush(); }
});
idlm_http_case('OVERVIEW HTTP managers and administrators only, with drill-down links',static function():void{
    $view=array('idlm_view'=>'design-team','tab'=>'overview');
    $manager=idlm_http_request('/crm-workspace',$view,'design',false); idlm_http_check($manager['status']===200&&strpos($manager['body'],'Needs attention')!==false&&strpos($manager['body'],'queue=overdue')!==false,'Manager overview missing');
    idlm_http_check(strpos($manager['body'],'Sales to design')===false,'Manager saw administrator queues');
    $rep=idlm_http_request('/crm-workspace',$view,'rep',false)['body']; idlm_http_check(strpos($rep,'idlm-design-metrics')===false&&strpos($rep,'>Overview<')===false,'Rep saw the overview');
    idlm_http_check(strpos(idlm_http_request('/crm-workspace',$view,'other',false)['body'],'idlm-design-metrics')===false,'Sales rep saw the overview');
});
