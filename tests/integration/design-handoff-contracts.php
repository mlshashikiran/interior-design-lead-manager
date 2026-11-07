<?php
/** HANDOFF real persistence and access contracts on the disposable schema. Builder: Shashi. */
if (!defined('ABSPATH') || strpos(DB_NAME, 'idlm_test_') !== 0) { exit(1); }
function idlm_handoff_native_test($name, $callback): void {
    idlm_payment_native_test($name, static function() use ($callback): void {
        global $wpdb;
        try { $callback(); } finally { foreach ($GLOBALS['idlm_payment_native_leads'] as $lead) { foreach (IDLM_Design_Handoffs::tables() as $suffix) { $wpdb->delete($wpdb->prefix.$suffix, array('lead_id'=>$lead)); } $wpdb->delete($wpdb->prefix.'idlm_private_files', array('lead_id'=>$lead)); } }
    });
}
function idlm_handoff_input($reference = 'HANDOFF-URN', $amount = '99000'): array {
    return array('notes'=>'Customer wants an oak finish','scope_summary'=>'Kitchen and two bedrooms','pending_items'=>'None','design_manager_id'=>get_current_user_id(),'amount'=>$amount,'received_date'=>'2026-11-05','method'=>'UPI','reference'=>$reference,'attachment_id'=>0);
}
function idlm_handoff_lead_state($lead): array {
    global $wpdb;
    return (array) $wpdb->get_row($wpdb->prepare("SELECT stage,lead_quality,assigned_rep_id,deal_value,handoff_status FROM {$wpdb->prefix}idlm_leads WHERE id=%d",$lead));
}
function idlm_handoff_user($role): int {
    return (int) wp_insert_user(array('user_login'=>'handoff-'.wp_generate_uuid4(),'user_pass'=>wp_generate_password(),'role'=>$role));
}
idlm_handoff_native_test('HANDOFF send saves the advance through the ledger and keeps the lead unchanged', static function(): void {
    global $wpdb; $f=idlm_payment_native_fixture(); $before=idlm_handoff_lead_state($f[0]); $key=wp_generate_uuid4();
    $sent=IDLM_Design_Handoffs::send($f[0],idlm_handoff_input(),$key); idlm_integration_assert_true(is_array($sent));
    idlm_integration_assert_same('new',$sent['handoff']->status); idlm_integration_assert_same($before,idlm_handoff_lead_state($f[0]));
    $read=IDLM_Lead_Payments::read($f[0]); idlm_integration_assert_same('99000.00',$read['balance']['total_received']); idlm_integration_assert_same('1000.00',$read['balance']['advance_pending']);
    idlm_integration_assert_true((int)$sent['handoff']->receipt_confirmation_id>0);
    $retry=IDLM_Design_Handoffs::send($f[0],idlm_handoff_input(),$key); idlm_integration_assert_same(true,$retry['replayed']);
    idlm_integration_assert_true(is_wp_error(IDLM_Design_Handoffs::send($f[0],idlm_handoff_input('OTHER'),wp_generate_uuid4())),'An open handoff refuses a second send.');
    idlm_integration_assert_same(1,count(IDLM_Lead_Payments::read($f[0])['receipts']));
    idlm_integration_assert_same('1',(string)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->prefix}idlm_design_handoffs WHERE lead_id=%d",$f[0])));
});
idlm_handoff_native_test('HANDOFF send refuses a lead that is not Won, blank remarks and missing evidence', static function(): void {
    global $wpdb; $f=idlm_payment_native_fixture();
    $input=idlm_handoff_input(); $input['notes']="  "; idlm_integration_assert_true(is_wp_error(IDLM_Design_Handoffs::send($f[0],$input,wp_generate_uuid4())));
    $input=idlm_handoff_input(''); idlm_integration_assert_true(is_wp_error(IDLM_Design_Handoffs::send($f[0],$input,wp_generate_uuid4())));
    idlm_integration_assert_same(null,IDLM_Lead_Payments::read($f[0])['schedule'],'A rejected form must not publish a schedule.');
    $wpdb->update($wpdb->prefix.'idlm_leads',array('stage'=>'Negotiation'),array('id'=>$f[0]));
    idlm_integration_assert_true(is_wp_error(IDLM_Design_Handoffs::send($f[0],idlm_handoff_input(),wp_generate_uuid4())));
    idlm_integration_assert_same(null,IDLM_Design_Handoffs::for_lead($f[0]));
});
idlm_handoff_native_test('HANDOFF without quotation terms is refused and records nothing', static function(): void {
    // Design needs a quotation with payment terms; the advance is never parked on a handoff.
    global $wpdb; $f=idlm_payment_native_fixture(); $wpdb->update($wpdb->prefix.'idlm_quote_revisions',array('document_terms'=>''),array('id'=>$f[2]));
    $sent=IDLM_Design_Handoffs::send($f[0],idlm_handoff_input(),wp_generate_uuid4()); idlm_integration_assert_true(is_wp_error($sent),'Send without payment terms must be refused.');
    idlm_integration_assert_same(null,IDLM_Design_Handoffs::for_lead($f[0]));
    idlm_integration_assert_same(array(),IDLM_Lead_Payments::read($f[0])['receipts']);
    idlm_integration_assert_same(null,IDLM_Lead_Payments::read($f[0])['schedule'],'A refused send must not publish a schedule.');
});
function idlm_handoff_person($capability): int {
    $id=idlm_handoff_user('subscriber'); (new WP_User($id))->add_cap($capability); return $id;
}
idlm_handoff_native_test('HANDOFF send requires a real Design Manager scope and pending items', static function(): void {
    $f=idlm_payment_native_fixture(); $rep=idlm_handoff_person(IDLM_Design_Handoffs::REP_CAPABILITY);
    foreach (array('scope_summary'=>' ','pending_items'=>'','design_manager_id'=>0) as $field=>$bad) { $input=idlm_handoff_input(); $input[$field]=$bad; idlm_integration_assert_true(is_wp_error(IDLM_Design_Handoffs::send($f[0],$input,wp_generate_uuid4())),$field); }
    $input=idlm_handoff_input(); $input['design_manager_id']=$rep; idlm_integration_assert_true(is_wp_error(IDLM_Design_Handoffs::send($f[0],$input,wp_generate_uuid4())),'A rep is not a Design Manager.');
    idlm_integration_assert_same(null,IDLM_Lead_Payments::read($f[0])['schedule'],'A rejected form must not publish a schedule.');
    idlm_integration_assert_same(null,IDLM_Design_Handoffs::for_lead($f[0]));
});
idlm_handoff_native_test('HANDOFF managers act only on their own, reps see only assigned, and receiving grants no lead access', static function(): void {
    $f=idlm_payment_native_fixture(); $admin=get_current_user_id();
    $manager=idlm_handoff_person(IDLM_Design_Handoffs::CAPABILITY); $other_manager=idlm_handoff_person(IDLM_Design_Handoffs::CAPABILITY);
    $rep=idlm_handoff_person(IDLM_Design_Handoffs::REP_CAPABILITY); $other_rep=idlm_handoff_person(IDLM_Design_Handoffs::REP_CAPABILITY); $stranger=idlm_handoff_user('subscriber');
    $input=idlm_handoff_input(); $input['design_manager_id']=$manager;
    wp_set_current_user($stranger); idlm_integration_assert_true(is_wp_error(IDLM_Design_Handoffs::send($f[0],$input,wp_generate_uuid4())));
    idlm_integration_assert_true(!IDLM_Design_Handoffs::can_receive());
    wp_set_current_user($manager); idlm_integration_assert_true(is_wp_error(IDLM_Design_Handoffs::send($f[0],$input,wp_generate_uuid4())),'A manager cannot send.');
    idlm_integration_assert_true(!IDLM_Lead_Access_Policy::has_rep_access()); idlm_integration_assert_true(!IDLM_Lead_Access_Policy::can_access($f[0],true));
    wp_set_current_user($admin); $sent=IDLM_Design_Handoffs::send($f[0],$input,wp_generate_uuid4()); $id=(int)$sent['handoff']->id; $target='2027-01-31';
    wp_set_current_user($other_manager); $row=IDLM_Design_Handoffs::find($id); idlm_integration_assert_true(!IDLM_Design_Handoffs::can_see($row)); idlm_integration_assert_true(!IDLM_Design_Handoffs::may_read_file($f[0]));
    idlm_integration_assert_true(is_wp_error(IDLM_Design_Handoffs::accept($id,$rep,$target)),'Another manager cannot accept.');
    wp_set_current_user($rep); idlm_integration_assert_true(!IDLM_Design_Handoffs::can_see($row),'Unassigned rep sees nothing.'); idlm_integration_assert_true(is_wp_error(IDLM_Design_Handoffs::accept($id,$rep,$target)),'A rep cannot accept.'); idlm_integration_assert_true(is_wp_error(IDLM_Design_Handoffs::send_back($id,'Rep tries')));
    wp_set_current_user($manager); idlm_integration_assert_true(IDLM_Design_Handoffs::can_see($row)); idlm_integration_assert_true(IDLM_Design_Handoffs::may_read_file($f[0]));
    idlm_integration_assert_true(is_wp_error(IDLM_Design_Handoffs::accept($id,$stranger,$target)),'A designer needs a design grant.');
    idlm_integration_assert_true(is_wp_error(IDLM_Design_Handoffs::accept($id,$rep,'')),'A designer needs a target date.');
    idlm_integration_assert_same(true,IDLM_Design_Handoffs::accept($id,$rep,$target)); $row=IDLM_Design_Handoffs::find($id);
    wp_set_current_user($rep); idlm_integration_assert_true(IDLM_Design_Handoffs::can_see($row)); idlm_integration_assert_true(IDLM_Design_Handoffs::may_read_file($f[0])); idlm_integration_assert_true(!IDLM_Design_Handoffs::can_decide($row));
    wp_set_current_user($manager); idlm_integration_assert_true(is_wp_error(IDLM_Design_Handoffs::assign($id,$other_rep,$target,' ')),'Reassigning needs a reason.');
    idlm_integration_assert_same(true,IDLM_Design_Handoffs::assign($id,$other_rep,$target,'Moved to the north studio')); $row=IDLM_Design_Handoffs::find($id);
    wp_set_current_user($rep); idlm_integration_assert_true(!IDLM_Design_Handoffs::can_see($row),'Reassignment removes the former rep.'); idlm_integration_assert_true(!IDLM_Design_Handoffs::may_read_file($f[0]));
    update_user_meta($manager,'idlm_crm_access_disabled','1'); wp_set_current_user($manager); idlm_integration_assert_true(!IDLM_Design_Handoffs::can_see($row),'Suspension removes access.'); idlm_integration_assert_true(is_wp_error(IDLM_Design_Handoffs::assign($id,$rep,$target,'Suspended')));
    wp_set_current_user($admin); idlm_integration_assert_true(IDLM_Design_Handoffs::can_decide($row),'An administrator can cover a suspended manager.');
});
idlm_handoff_native_test('HANDOFF return and resend keep each submission unchanged, and acceptance pins the sales price', static function(): void {
    global $wpdb; $f=idlm_payment_native_fixture(); $admin=get_current_user_id(); $before=idlm_handoff_lead_state($f[0]);
    $sent=IDLM_Design_Handoffs::send($f[0],idlm_handoff_input(),wp_generate_uuid4()); $id=(int)$sent['handoff']->id;
    idlm_integration_assert_same(1,(int)$sent['handoff']->version_no); idlm_integration_assert_same((string)$f[2],(string)$sent['handoff']->baseline_revision_id); idlm_integration_assert_same('1000000.00',$sent['handoff']->baseline_total);
    idlm_integration_assert_true(is_wp_error(IDLM_Design_Handoffs::send_back($id,'   ')));
    idlm_integration_assert_same(true,IDLM_Design_Handoffs::send_back($id,'Floor plan is missing')); $row=IDLM_Design_Handoffs::find($id);
    idlm_integration_assert_same('sent_back',$row->status); idlm_integration_assert_same('Floor plan is missing',$row->send_back_reason);
    idlm_integration_assert_true(is_wp_error(IDLM_Design_Handoffs::accept($id)),'A returned handoff cannot be accepted.');
    $input=idlm_handoff_input(); $input['notes']='Floor plan attached to the lead';
    $again=IDLM_Design_Handoffs::send($f[0],$input,wp_generate_uuid4()); idlm_integration_assert_true(is_array($again));
    idlm_integration_assert_same('new',$again['handoff']->status); idlm_integration_assert_same($id,(int)$again['handoff']->id); idlm_integration_assert_same(2,(int)$again['handoff']->version_no);
    idlm_integration_assert_same(1,count(IDLM_Lead_Payments::read($f[0])['receipts']),'A resend reuses the recorded advance.');
    $versions=IDLM_Design_Handoffs::versions($id); idlm_integration_assert_same(2,count($versions));
    idlm_integration_assert_same('Customer wants an oak finish',$versions[1]->notes); idlm_integration_assert_same('returned',$versions[1]->outcome); idlm_integration_assert_same('Floor plan is missing',$versions[1]->return_reason); idlm_integration_assert_same('pending',$versions[0]->outcome);
    idlm_integration_assert_same(true,IDLM_Design_Handoffs::accept($id)); $row=IDLM_Design_Handoffs::find($id);
    idlm_integration_assert_same('accepted',$row->status); idlm_integration_assert_same('0',(string)$row->designer_id,'Accepted can wait for a designer.');
    idlm_integration_assert_same('accepted',IDLM_Design_Handoffs::versions($id)[0]->outcome);
    idlm_integration_assert_true(is_wp_error(IDLM_Design_Handoffs::send_back($id,'Too late')),'An accepted handoff is not returned.');
    idlm_integration_assert_true(is_wp_error(IDLM_Design_Handoffs::send($f[0],$input,wp_generate_uuid4())));
    idlm_integration_assert_same(true,IDLM_Design_Handoffs::assign($id,$admin,'2027-01-31'));
    idlm_integration_assert_same($before,idlm_handoff_lead_state($f[0]));
    $types=$wpdb->get_col($wpdb->prepare("SELECT event_type FROM {$wpdb->prefix}idlm_design_handoff_events WHERE handoff_id=%d ORDER BY id",$id));
    idlm_integration_assert_same(array('sent','returned','sent','accepted','assigned'),$types);
    $assigned=$wpdb->get_row($wpdb->prepare("SELECT designer_before,designer_after,target_after FROM {$wpdb->prefix}idlm_design_handoff_events WHERE handoff_id=%d AND event_type='assigned'",$id));
    idlm_integration_assert_same((string)$admin,(string)$assigned->designer_after); idlm_integration_assert_same('2027-01-31',$assigned->target_after);
    idlm_integration_assert_same('5',(string)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->prefix}idlm_lead_activity_log WHERE lead_id=%d AND activity_type='design_handoff'",$f[0])));
});
idlm_handoff_native_test('HANDOFF lead leaving Won is flagged, cannot be accepted, and the row survives', static function(): void {
    global $wpdb; $f=idlm_payment_native_fixture();
    $sent=IDLM_Design_Handoffs::send($f[0],idlm_handoff_input(),wp_generate_uuid4()); $id=(int)$sent['handoff']->id;
    $wpdb->update($wpdb->prefix.'idlm_leads',array('stage'=>'Negotiation'),array('id'=>$f[0]));
    idlm_integration_assert_true(is_wp_error(IDLM_Design_Handoffs::accept($id)));
    idlm_integration_assert_same('new',IDLM_Design_Handoffs::find($id)->status);
    idlm_integration_assert_same(true,IDLM_Design_Handoffs::send_back($id,'Deal reopened by sales'));
});
idlm_handoff_native_test('HANDOFF deleting a lead removes its handoff, versions and events', static function(): void {
    global $wpdb; $f=idlm_payment_native_fixture(); IDLM_Design_Handoffs::send($f[0],idlm_handoff_input(),wp_generate_uuid4());
    idlm_integration_assert_true(!IDLM_Lead_Payments::partial_recovery_safe(),'Partial restore stays refused.');
    idlm_integration_assert_true(!is_wp_error(IDLM_Lead_Deletion_Service::delete($f[0])));
    foreach (IDLM_Design_Handoffs::tables() as $suffix) { idlm_integration_assert_same('0',(string)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->prefix}{$suffix} WHERE lead_id=%d",$f[0])),$suffix); }
});
idlm_handoff_native_test('HANDOFF save failure rolls back and retry succeeds with one receipt', static function(): void {
    $f=idlm_payment_native_fixture(); $key=wp_generate_uuid4();
    $failed=idlm_lifecycle_failure('idlm_design_handoffs','INSERT',static function() use ($f,$key) { return IDLM_Design_Handoffs::send($f[0],idlm_handoff_input(),$key); });
    idlm_integration_assert_true(is_wp_error($failed)); idlm_integration_assert_same(null,IDLM_Design_Handoffs::for_lead($f[0]));
    $retry=IDLM_Design_Handoffs::send($f[0],idlm_handoff_input(),$key); idlm_integration_assert_true(is_array($retry));
    idlm_integration_assert_same(1,count(IDLM_Lead_Payments::read($f[0])['receipts']));
});
idlm_handoff_native_test('HANDOFF queues a notification and the email switch silences it', static function(): void {
    global $wpdb; $f=idlm_payment_native_fixture();
    IDLM_Design_Handoffs::send($f[0],idlm_handoff_input(),wp_generate_uuid4());
    wp_update_user(array('ID'=>get_current_user_id(),'user_email'=>'handoff-test@example.test'));
    $mail=0; $capture=static function($short) use (&$mail) { $mail++; return true; }; add_filter('pre_wp_mail',$capture);
    try {
        update_option('idlm_handoff_notifications_enabled','0'); IDLM_Design_Handoffs::notify($f[0],'sent'); idlm_integration_assert_same(0,$mail);
        update_option('idlm_handoff_notifications_enabled','1'); IDLM_Design_Handoffs::notify($f[0],'sent'); idlm_integration_assert_same(1,$mail);
    } finally { remove_filter('pre_wp_mail',$capture); delete_option('idlm_handoff_notifications_enabled'); }
});

/** An accepted handoff with one saved sales line. */
function idlm_design_revision_fixture($designer = 0): array {
    global $wpdb; $f=idlm_payment_native_fixture(); $admin=get_current_user_id();
    $sections=IDLM_Quotation_Calculator::parse_sections(array(array('key'=>'general','section_name'=>'General')));
    $items=IDLM_Quotation_Calculator::parse_items(array(idlm_design_revision_line()));
    idlm_integration_assert_true(!is_wp_error(IDLM_Quotation_Repository::replace_revision_contents($f[2],$sections,$items)));
    $wpdb->update($wpdb->prefix.'idlm_quote_revisions',array('wooden_discount_percent'=>'5.00','additional_discount_amount'=>'100.00','soft_close_percent'=>'7.00'),array('id'=>$f[2]));
    $sent=IDLM_Design_Handoffs::send($f[0],idlm_handoff_input(),wp_generate_uuid4()); $id=(int)$sent['handoff']->id;
    idlm_integration_assert_same(true,IDLM_Design_Handoffs::accept($id,$designer ?: $admin,'2027-01-31'));
    return array('lead'=>$f[0],'quote'=>$f[1],'revision'=>$f[2],'handoff'=>$id);
}
function idlm_design_revision_line($rate='1000',$quantity='10',$name='Wardrobe'): array {
    return array('section_key'=>'general','row_type'=>'item','item_name'=>$name,'description'=>$name.' line','quantity'=>$quantity,'unit'=>'Nos','unit_rate'=>$rate,'is_wood'=>'1');
}
function idlm_design_revision_post($lines=null,$extra=array()): array {
    return array_merge(array('sections'=>array(array('key'=>'general','section_name'=>'General')),'items'=>$lines ?? array(idlm_design_revision_line('1000','12')),'wooden_discount_percent'=>'5.00','additional_discount_amount'=>'100.00','revision_reason'=>'Added two more units'),$extra);
}
function idlm_design_rep(): int { return idlm_handoff_person(IDLM_Design_Handoffs::REP_CAPABILITY); }
idlm_handoff_native_test('DESIGNREV a save keeps the quote number and sequence and leaves sales, lead and payments alone', static function(): void {
    global $wpdb; $d=idlm_design_revision_fixture(); $p=$wpdb->prefix;
    $quote=$wpdb->get_row($wpdb->prepare("SELECT * FROM {$p}idlm_quotes WHERE id=%d",$d['quote']),ARRAY_A); $sales=$wpdb->get_row($wpdb->prepare("SELECT * FROM {$p}idlm_quote_revisions WHERE id=%d",$d['revision']),ARRAY_A);
    $lead=$wpdb->get_row($wpdb->prepare("SELECT quote_value,deal_value,stage,assigned_rep_id FROM {$p}idlm_leads WHERE id=%d",$d['lead']),ARRAY_A); $schedules=$wpdb->get_results($wpdb->prepare("SELECT * FROM {$p}idlm_lead_payment_schedules WHERE lead_id=%d",$d['lead']),ARRAY_A); $sets=$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$p}idlm_lead_payment_allocation_sets WHERE lead_id=%d",$d['lead']));
    $key=wp_generate_uuid4(); $saved=IDLM_Design_Revisions::save($d['handoff'],$d['revision'],idlm_design_revision_post(),$key); idlm_integration_assert_true(is_array($saved));
    $new=IDLM_Quotation_Repository::get_revision($saved['revision_id']);
    idlm_integration_assert_same((int)$sales['revision_number']+1,(int)$new->revision_number); idlm_integration_assert_same($quote['quote_number'].'-R'.str_pad((string)$new->revision_number,2,'0',STR_PAD_LEFT).'-DES',$new->revision_name); idlm_integration_assert_same((string)$d['revision'],(string)$new->source_revision_id);
    idlm_integration_assert_same($quote,$wpdb->get_row($wpdb->prepare("SELECT * FROM {$p}idlm_quotes WHERE id=%d",$d['quote']),ARRAY_A),'The quotation row, including its current revision, must not change.');
    idlm_integration_assert_same($sales,$wpdb->get_row($wpdb->prepare("SELECT * FROM {$p}idlm_quote_revisions WHERE id=%d",$d['revision']),ARRAY_A),'The sales revision must not change.');
    idlm_integration_assert_same($lead,$wpdb->get_row($wpdb->prepare("SELECT quote_value,deal_value,stage,assigned_rep_id FROM {$p}idlm_leads WHERE id=%d",$d['lead']),ARRAY_A),'The lead must not change.');
    idlm_integration_assert_same($schedules,$wpdb->get_results($wpdb->prepare("SELECT * FROM {$p}idlm_lead_payment_schedules WHERE lead_id=%d",$d['lead']),ARRAY_A)); idlm_integration_assert_same($sets,$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$p}idlm_lead_payment_allocation_sets WHERE lead_id=%d",$d['lead'])));
    idlm_integration_assert_same((string)$saved['revision_id'],(string)IDLM_Design_Handoffs::find($d['handoff'])->design_revision_id);
    $terms=IDLM_Quotation_Document::read($new); idlm_integration_assert_same('10.00',$terms['milestones'][0]['percentage']); idlm_integration_assert_same('7.00',(string)$new->soft_close_percent,'The saved percentage carries over.');
    idlm_integration_assert_same(true,IDLM_Design_Revisions::save($d['handoff'],$d['revision'],idlm_design_revision_post(),$key)['replayed']);
    idlm_integration_assert_true(is_wp_error(IDLM_Design_Revisions::save($d['handoff'],$d['revision'],idlm_design_revision_post(),wp_generate_uuid4())),'A stale source is refused.');
    $next=IDLM_Design_Revisions::save($d['handoff'],$saved['revision_id'],idlm_design_revision_post(array(idlm_design_revision_line('1000','14'))),wp_generate_uuid4()); idlm_integration_assert_same((int)$new->revision_number+1,(int)IDLM_Quotation_Repository::get_revision($next['revision_id'])->revision_number);
    idlm_integration_assert_same(2,count(IDLM_Design_Revisions::history($d['handoff'])));
});
idlm_handoff_native_test('DESIGNREV a rep can change scope but not discounts, and only while assigned', static function(): void {
    $rep=idlm_design_rep(); $other=idlm_design_rep(); $d=idlm_design_revision_fixture($rep); $admin=get_current_user_id();
    wp_set_current_user($other); idlm_integration_assert_true(is_wp_error(IDLM_Design_Revisions::save($d['handoff'],$d['revision'],idlm_design_revision_post(),wp_generate_uuid4())),'An unassigned rep cannot save.');
    wp_set_current_user($rep);
    idlm_integration_assert_same('idlm_design_revision_discount',IDLM_Design_Revisions::save($d['handoff'],$d['revision'],idlm_design_revision_post(null,array('wooden_discount_percent'=>'6')),wp_generate_uuid4())->get_error_code());
    idlm_integration_assert_same('idlm_design_revision_discount',IDLM_Design_Revisions::save($d['handoff'],$d['revision'],idlm_design_revision_post(null,array('additional_discount_amount'=>'0')),wp_generate_uuid4())->get_error_code());
    $saved=IDLM_Design_Revisions::save($d['handoff'],$d['revision'],idlm_design_revision_post(array(idlm_design_revision_line('1000','12'),idlm_design_revision_line('400','1','New niche'))),wp_generate_uuid4()); idlm_integration_assert_true(is_array($saved));
    $meta=IDLM_Design_Revisions::meta($saved['revision_id']); idlm_integration_assert_same((string)$rep,(string)$meta->designer_id); idlm_integration_assert_same((string)$rep,(string)$meta->actor_id);
    idlm_integration_assert_true(!IDLM_Design_Revisions::can_change_pricing(IDLM_Design_Handoffs::find($d['handoff'])));
    wp_set_current_user($admin); idlm_integration_assert_same(true,IDLM_Design_Handoffs::assign($d['handoff'],$other,'2027-02-01','Moved to another studio'));
    wp_set_current_user($rep); idlm_integration_assert_true(is_wp_error(IDLM_Design_Revisions::save($d['handoff'],$saved['revision_id'],idlm_design_revision_post(),wp_generate_uuid4())),'Reassignment removes editing.');
    wp_set_current_user($admin); $on_behalf=IDLM_Design_Revisions::save($d['handoff'],$saved['revision_id'],idlm_design_revision_post(array(idlm_design_revision_line('800','12'))),wp_generate_uuid4()); idlm_integration_assert_true(is_array($on_behalf));
    $meta=IDLM_Design_Revisions::meta($on_behalf['revision_id']); idlm_integration_assert_same((string)$other,(string)$meta->designer_id,'The designer stays named when a manager saves.'); idlm_integration_assert_same((string)$admin,(string)$meta->actor_id);
});
idlm_handoff_native_test('DESIGNREV a manager discount change needs a remark and is audited', static function(): void {
    global $wpdb; $d=idlm_design_revision_fixture();
    idlm_integration_assert_same('idlm_design_revision_discount_remark',IDLM_Design_Revisions::save($d['handoff'],$d['revision'],idlm_design_revision_post(null,array('wooden_discount_percent'=>'8')),wp_generate_uuid4())->get_error_code());
    $saved=IDLM_Design_Revisions::save($d['handoff'],$d['revision'],idlm_design_revision_post(null,array('wooden_discount_percent'=>'8','discount_remark'=>'Approved for repeat customer')),wp_generate_uuid4()); idlm_integration_assert_true(is_array($saved));
    $event=$wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}idlm_design_handoff_events WHERE handoff_id=%d AND event_type='discount_changed'",$d['handoff'])); idlm_integration_assert_same('Approved for repeat customer',$event->reason);
    $details=json_decode($event->details,true); idlm_integration_assert_same(array('before'=>'5.00','after'=>'8.00'),$details['changes']['wooden_discount_percent']); idlm_integration_assert_same($saved['revision_id'],$details['revision_id']);
    idlm_integration_assert_same('5.00',(string)IDLM_Quotation_Repository::get_revision($d['revision'])->wooden_discount_percent,'History is not rewritten.');
});
idlm_handoff_native_test('DESIGNREV sales cannot edit while design holds the quote, and a return reopens it', static function(): void {
    $f=idlm_payment_native_fixture(); $bundle=static function() use ($f) { return IDLM_Quotation_Repository::get_quote_bundle($f[1]); };
    $editable=static function() use ($bundle) { $b=$bundle(); return IDLM_Quotation_Policy::is_editable_draft($b,$b->revision); };
    $before=$editable();
    $sent=IDLM_Design_Handoffs::send($f[0],idlm_handoff_input(),wp_generate_uuid4()); idlm_integration_assert_true(!$editable(),'Locked when sent.');
    idlm_integration_assert_same(true,IDLM_Design_Handoffs::send_back((int)$sent['handoff']->id,'Fix the scope')); idlm_integration_assert_same($before,$editable(),'A return reopens it.');
    IDLM_Design_Handoffs::send($f[0],idlm_handoff_input(),wp_generate_uuid4()); idlm_integration_assert_same(true,IDLM_Design_Handoffs::accept((int)$sent['handoff']->id)); idlm_integration_assert_true(!$editable(),'Locked when accepted.');
});
idlm_handoff_native_test('DESIGNREV a payment schedule cannot use a design revision yet', static function(): void {
    $d=idlm_design_revision_fixture(); $saved=IDLM_Design_Revisions::save($d['handoff'],$d['revision'],idlm_design_revision_post(),wp_generate_uuid4());
    $read=IDLM_Lead_Payments::read($d['lead']);
    $result=IDLM_Lead_Payments::publish_schedule($d['lead'],$saved['revision_id'],array(0),'Trying a draft design price',$read['version'],wp_generate_uuid4());
    idlm_integration_assert_true(is_wp_error($result)); idlm_integration_assert_same(1,(int)IDLM_Lead_Payments::read($d['lead'])['schedule']['version_no']);
});
idlm_handoff_native_test('DESIGNREV a failed save rolls back fully and retry succeeds', static function(): void {
    global $wpdb; $d=idlm_design_revision_fixture(); $key=wp_generate_uuid4();
    $failed=idlm_lifecycle_failure('idlm_design_handoff_events','INSERT',static function() use ($d,$key) { return IDLM_Design_Revisions::save($d['handoff'],$d['revision'],idlm_design_revision_post(),$key); });
    idlm_integration_assert_true(is_wp_error($failed)); idlm_integration_assert_same(null,IDLM_Quotation_Repository::get_revision_by_save_request_key($key)); idlm_integration_assert_same('0',(string)IDLM_Design_Handoffs::find($d['handoff'])->design_revision_id);
    idlm_integration_assert_same(array(),IDLM_Design_Revisions::history($d['handoff']));
    idlm_integration_assert_true(is_array(IDLM_Design_Revisions::save($d['handoff'],$d['revision'],idlm_design_revision_post(),$key)));
});

idlm_handoff_native_test('DESIGNREV a rep\'s rate change saves and is listed, and the revision is shared only after a manager reviews it', static function(): void {
    global $wpdb; $rep=idlm_design_rep(); $d=idlm_design_revision_fixture($rep); $admin=get_current_user_id();
    wp_set_current_user($rep);
    $saved=IDLM_Design_Revisions::save($d['handoff'],$d['revision'],idlm_design_revision_post(array(idlm_design_revision_line('900','12'))),wp_generate_uuid4()); idlm_integration_assert_true(is_array($saved),'A designer may change a rate.');
    $meta=IDLM_Design_Revisions::meta($saved['revision_id']); idlm_integration_assert_same(array(array('name'=>'Wardrobe','before'=>'1000.00','after'=>'900.00')),json_decode($meta->rate_changes,true));
    idlm_integration_assert_same(null,$meta->reviewed_at); idlm_integration_assert_true(!IDLM_Design_Revisions::may_share($saved['revision_id']),'The designer cannot share it yet.');
    idlm_integration_assert_true(IDLM_Design_Revisions::may_share($d['revision']),'The sales revision is always shareable.');
    $event=json_decode($wpdb->get_var($wpdb->prepare("SELECT details FROM {$wpdb->prefix}idlm_design_handoff_events WHERE handoff_id=%d AND event_type='design_revision' ORDER BY id DESC LIMIT 1",$d['handoff'])),true); idlm_integration_assert_same('900.00',$event['rate_changes'][0]['after']);
    idlm_integration_assert_same('idlm_design_revision_denied',IDLM_Design_Revisions::review($d['handoff'],$saved['revision_id'])->get_error_code(),'A designer cannot review.');
    wp_set_current_user($admin); idlm_integration_assert_true(IDLM_Design_Revisions::may_share($saved['revision_id']),'The reviewing manager may look first.');
    idlm_integration_assert_same('idlm_design_revision_review',IDLM_Design_Revisions::customer_approve($d['handoff'],$saved['revision_id'],'Approved by email',IDLM_Lead_Payments::version($d['lead']),wp_generate_uuid4())->get_error_code());
    idlm_integration_assert_same('idlm_design_revision_review',IDLM_Design_Revisions::preview(IDLM_Design_Handoffs::find($d['handoff']),$saved['revision_id'])->get_error_code());
    idlm_integration_assert_same('idlm_design_revision_source',IDLM_Design_Revisions::review($d['handoff'],$d['revision'])->get_error_code(),'Only design revisions are reviewed.');
    idlm_integration_assert_same(true,IDLM_Design_Revisions::review($d['handoff'],$saved['revision_id'])); idlm_integration_assert_same(true,IDLM_Design_Revisions::review($d['handoff'],$saved['revision_id']),'Reviewing twice is harmless.');
    idlm_integration_assert_same(1,(int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->prefix}idlm_design_handoff_events WHERE handoff_id=%d AND event_type='design_revision_reviewed'",$d['handoff'])));
    idlm_integration_assert_same((string)$admin,(string)IDLM_Design_Revisions::meta($saved['revision_id'])->reviewed_by);
    wp_set_current_user($rep); idlm_integration_assert_true(IDLM_Design_Revisions::may_share($saved['revision_id']));
    idlm_integration_assert_true(!is_wp_error(IDLM_Quotation_Exporter::authorize($d['quote'],$saved['revision_id'],'pdf',false)) || 'idlm_quote_export_review'!==IDLM_Quotation_Exporter::authorize($d['quote'],$saved['revision_id'],'pdf',false)->get_error_code());
    wp_set_current_user($admin); $own=IDLM_Design_Revisions::save($d['handoff'],$saved['revision_id'],idlm_design_revision_post(array(idlm_design_revision_line('950','12'))),wp_generate_uuid4());
    idlm_integration_assert_true(IDLM_Design_Revisions::is_reviewed(IDLM_Design_Revisions::meta($own['revision_id'])),'A manager\'s own revision is reviewed on save.');
    $wpdb->update($wpdb->prefix.'idlm_design_revisions',array('reviewed_at'=>null,'reviewed_by'=>0),array('revision_id'=>$own['revision_id']));
    $export=IDLM_Quotation_Exporter::authorize($d['quote'],$own['revision_id'],'pdf',false);
    idlm_integration_assert_true(!is_wp_error($export) || 'idlm_quote_export_review'!==$export->get_error_code(),'The manager of the project may export while reviewing.');
});

function idlm_approved_revision($d,$rate='100000',$quantity='12'): array {
    $source=IDLM_Design_Revisions::working_revision_id(IDLM_Design_Handoffs::find($d['handoff']));
    $saved=IDLM_Design_Revisions::save($d['handoff'],$source,idlm_design_revision_post(array(idlm_design_revision_line($rate,$quantity)),array('wooden_discount_percent'=>'0','additional_discount_amount'=>'0','discount_remark'=>'Reset for the test')),wp_generate_uuid4());
    idlm_integration_assert_true(is_array($saved)); return $saved;
}
idlm_handoff_native_test('APPROVED a new schedule version moves amounts, keeps receipts and leaves sales records alone', static function(): void {
    global $wpdb; $d=idlm_design_revision_fixture(); $p=$wpdb->prefix; $saved=idlm_approved_revision($d); $revision=IDLM_Quotation_Repository::get_revision($saved['revision_id']);
    $old=$wpdb->get_row($wpdb->prepare("SELECT * FROM {$p}idlm_lead_payment_schedules WHERE lead_id=%d",$d['lead']),ARRAY_A); $receipts=$wpdb->get_results($wpdb->prepare("SELECT * FROM {$p}idlm_lead_payment_versions WHERE lead_id=%d",$d['lead']),ARRAY_A);
    $quote=$wpdb->get_row($wpdb->prepare("SELECT * FROM {$p}idlm_quotes WHERE id=%d",$d['quote']),ARRAY_A); $lead=$wpdb->get_row($wpdb->prepare("SELECT quote_value,deal_value,stage,assigned_rep_id FROM {$p}idlm_leads WHERE id=%d",$d['lead']),ARRAY_A);
    $unsaved=IDLM_Design_Handoffs::find($d['handoff']); idlm_integration_assert_same('0',(string)$unsaved->agreed_revision_id,'A saved revision alone does not agree a price.');
    $version=IDLM_Lead_Payments::version($d['lead']); $key=wp_generate_uuid4();
    idlm_integration_assert_same('idlm_design_revision_note',IDLM_Design_Revisions::customer_approve($d['handoff'],$saved['revision_id'],'  ',$version,$key)->get_error_code());
    $result=IDLM_Design_Revisions::customer_approve($d['handoff'],$saved['revision_id'],'Approved by email on 3 November',$version,$key); idlm_integration_assert_same(false,$result['replayed']);
    $read=IDLM_Lead_Payments::read($d['lead']); $balance=$read['balance']; $total=(float)$revision->grand_total;
    idlm_integration_assert_same(2,(int)$read['schedule']['version_no']); idlm_integration_assert_same((string)$saved['revision_id'],(string)$read['schedule']['revision_id']);
    idlm_integration_assert_same(number_format($total,2,'.',''),$balance['basis_total']); idlm_integration_assert_same(number_format($total/10,2,'.',''),$balance['milestones'][0]['amount']); idlm_integration_assert_same('99000.00',$balance['total_received']);
    idlm_integration_assert_same(number_format($total/10-99000,2,'.',''),$balance['advance_pending']);
    idlm_integration_assert_same($old,$wpdb->get_row($wpdb->prepare("SELECT * FROM {$p}idlm_lead_payment_schedules WHERE id=%d",$old['id']),ARRAY_A),'The old schedule stays in history.');
    idlm_integration_assert_same($receipts,$wpdb->get_results($wpdb->prepare("SELECT * FROM {$p}idlm_lead_payment_versions WHERE lead_id=%d",$d['lead']),ARRAY_A),'Receipts are not touched.');
    idlm_integration_assert_same($quote,$wpdb->get_row($wpdb->prepare("SELECT * FROM {$p}idlm_quotes WHERE id=%d",$d['quote']),ARRAY_A)); idlm_integration_assert_same($lead,$wpdb->get_row($wpdb->prepare("SELECT quote_value,deal_value,stage,assigned_rep_id FROM {$p}idlm_leads WHERE id=%d",$d['lead']),ARRAY_A));
    $row=IDLM_Design_Handoffs::find($d['handoff']); idlm_integration_assert_same((string)$saved['revision_id'],(string)$row->agreed_revision_id); idlm_integration_assert_same($balance['basis_total'],$row->agreed_total);
    idlm_integration_assert_same('1000000.00',$row->baseline_total,'The sales price stays.');
    idlm_integration_assert_same(true,IDLM_Design_Revisions::customer_approve($d['handoff'],$saved['revision_id'],'Approved by email on 3 November',$version,$key)['replayed']);
    idlm_integration_assert_same('idlm_design_revision_already',IDLM_Design_Revisions::customer_approve($d['handoff'],$saved['revision_id'],'Again',IDLM_Lead_Payments::version($d['lead']),wp_generate_uuid4())->get_error_code());
    $event=$wpdb->get_row($wpdb->prepare("SELECT * FROM {$p}idlm_design_handoff_events WHERE handoff_id=%d AND event_type='customer_approved'",$d['handoff'])); idlm_integration_assert_same('Approved by email on 3 November',$event->reason); idlm_integration_assert_same('1000000.00',json_decode($event->details,true)['total_before']);
    idlm_integration_assert_true(!empty(IDLM_Design_Revisions::meta($saved['revision_id'])->approved_at));
    // A later working revision does not move payments until it too is approved.
    $next=idlm_approved_revision($d,'50000','12'); idlm_integration_assert_same($balance,IDLM_Lead_Payments::read($d['lead'])['balance']);
    idlm_integration_assert_true(is_array(IDLM_Design_Revisions::customer_approve($d['handoff'],$next['revision_id'],'Scope reduced, customer confirmed',IDLM_Lead_Payments::version($d['lead']),wp_generate_uuid4())));
    idlm_integration_assert_same(3,(int)IDLM_Lead_Payments::read($d['lead'])['schedule']['version_no']);
    idlm_integration_assert_true(is_array(IDLM_Design_Revisions::customer_approve($d['handoff'],$saved['revision_id'],'Customer went back to the earlier option',IDLM_Lead_Payments::version($d['lead']),wp_generate_uuid4())),'An earlier revision can be approved again.');
});
idlm_handoff_native_test('APPROVED a lower price shows credit and never a negative balance', static function(): void {
    $d=idlm_design_revision_fixture(); $saved=idlm_approved_revision($d,'1000','1');
    IDLM_Design_Revisions::customer_approve($d['handoff'],$saved['revision_id'],'Reduced to a small job',IDLM_Lead_Payments::version($d['lead']),wp_generate_uuid4());
    $balance=IDLM_Lead_Payments::read($d['lead'])['balance']; $total=(float)$balance['basis_total'];
    idlm_integration_assert_same('0.00',$balance['total_outstanding']); idlm_integration_assert_same(number_format(99000-$total,2,'.',''),$balance['excess_credit']);
});
idlm_handoff_native_test('APPROVED only the addressed manager or an administrator can approve, and only design revisions of this handoff', static function(): void {
    global $wpdb; $rep=idlm_design_rep(); $d=idlm_design_revision_fixture($rep); $admin=get_current_user_id(); $saved=idlm_approved_revision($d); $other_manager=idlm_handoff_person(IDLM_Design_Handoffs::CAPABILITY); $version=IDLM_Lead_Payments::version($d['lead']);
    foreach(array($rep,$other_manager,idlm_handoff_user('subscriber')) as $person){ wp_set_current_user($person); idlm_integration_assert_true(is_wp_error(IDLM_Design_Revisions::customer_approve($d['handoff'],$saved['revision_id'],'Not allowed',$version,wp_generate_uuid4())),'Person '.$person); }
    wp_set_current_user($admin); idlm_integration_assert_same('0',(string)IDLM_Design_Handoffs::find($d['handoff'])->agreed_revision_id);
    idlm_integration_assert_same('idlm_design_revision_source',IDLM_Design_Revisions::customer_approve($d['handoff'],$d['revision'],'Sales revision',$version,wp_generate_uuid4())->get_error_code());
    idlm_integration_assert_true(is_wp_error(IDLM_Design_Revisions::customer_approve($d['handoff'],$saved['revision_id'],'Stale form',max(0,$version-1),wp_generate_uuid4())),'A stale form is refused.');
    $wpdb->update($wpdb->prefix.'idlm_leads',array('stage'=>'Negotiation'),array('id'=>$d['lead'])); idlm_integration_assert_true(is_wp_error(IDLM_Design_Revisions::customer_approve($d['handoff'],$saved['revision_id'],'Lead reopened',$version,wp_generate_uuid4())));
});
idlm_handoff_native_test('APPROVED a failure rolls the whole approval back', static function(): void {
    $d=idlm_design_revision_fixture(); $saved=idlm_approved_revision($d); $key=wp_generate_uuid4(); $version=IDLM_Lead_Payments::version($d['lead']);
    $failed=idlm_lifecycle_failure('idlm_design_handoff_events','INSERT',static function() use ($d,$saved,$version,$key) { return IDLM_Design_Revisions::customer_approve($d['handoff'],$saved['revision_id'],'Approved by phone',$version,$key); });
    idlm_integration_assert_true(is_wp_error($failed)); idlm_integration_assert_same(1,(int)IDLM_Lead_Payments::read($d['lead'])['schedule']['version_no']); idlm_integration_assert_same('0',(string)IDLM_Design_Handoffs::find($d['handoff'])->agreed_revision_id);
    idlm_integration_assert_true(is_array(IDLM_Design_Revisions::customer_approve($d['handoff'],$saved['revision_id'],'Approved by phone',$version,$key)));
});
idlm_handoff_native_test('APPROVED sales cannot amend the schedule while design holds the project', static function(): void {
    $d=idlm_design_revision_fixture(); $read=IDLM_Lead_Payments::read($d['lead']);
    $result=IDLM_Lead_Payments::publish_schedule($d['lead'],$d['revision'],array(0),'Trying to amend from sales',$read['version'],wp_generate_uuid4());
    idlm_integration_assert_same('idlm_payment_design_locked',$result->get_error_code());
});
idlm_handoff_native_test('HANDOFF Accept refuses a lead whose quotation lost its payment terms', static function(): void {
    global $wpdb; $f=idlm_payment_native_fixture(); $admin=get_current_user_id(); $good=$wpdb->get_var($wpdb->prepare("SELECT document_terms FROM {$wpdb->prefix}idlm_quote_revisions WHERE id=%d",$f[2]));
    $sent=IDLM_Design_Handoffs::send($f[0],idlm_handoff_input(),wp_generate_uuid4()); idlm_integration_assert_true(is_array($sent)); $id=(int)$sent['handoff']->id;
    idlm_integration_assert_true((int)$sent['handoff']->receipt_confirmation_id>0,'With terms, the advance is recorded in Payments at send.');
    $wpdb->update($wpdb->prefix.'idlm_quote_revisions',array('document_terms'=>''),array('id'=>$f[2]));
    idlm_integration_assert_true(is_wp_error(IDLM_Design_Handoffs::accept($id,$admin,'2027-01-31')),'Accept needs a quotation with payment terms.');
    idlm_integration_assert_same('new',IDLM_Design_Handoffs::find($id)->status);
    $wpdb->update($wpdb->prefix.'idlm_quote_revisions',array('document_terms'=>$good),array('id'=>$f[2]));
    idlm_integration_assert_true(!is_wp_error(IDLM_Design_Handoffs::accept($id,$admin,'2027-01-31')));
    idlm_integration_assert_same('accepted',IDLM_Design_Handoffs::find($id)->status);
    idlm_integration_assert_same('99000.00',IDLM_Lead_Payments::read($f[0])['balance']['total_received']);
});
idlm_handoff_native_test('APPROVED a Design Manager records later payments, and reps and others cannot', static function(): void {
    $manager=idlm_handoff_person(IDLM_Design_Handoffs::CAPABILITY); $rep=idlm_design_rep(); $f=idlm_payment_native_fixture(); $admin=get_current_user_id();
    $input=idlm_handoff_input(); $input['design_manager_id']=$manager; $sent=IDLM_Design_Handoffs::send($f[0],$input,wp_generate_uuid4()); $id=(int)$sent['handoff']->id; $pay=idlm_payment_native_data('LATER-URN','25000');
    wp_set_current_user($manager); $version=IDLM_Lead_Payments::version($f[0]); idlm_integration_assert_true(is_wp_error(IDLM_Lead_Payments::record($f[0],$pay,$version,wp_generate_uuid4())),'Not before acceptance.');
    idlm_integration_assert_same(true,IDLM_Design_Handoffs::accept($id,$rep,'2027-01-31'));
    wp_set_current_user($rep); idlm_integration_assert_true(is_wp_error(IDLM_Lead_Payments::record($f[0],$pay,$version,wp_generate_uuid4())),'Reps see balances only.');
    wp_set_current_user(idlm_handoff_person(IDLM_Design_Handoffs::CAPABILITY)); idlm_integration_assert_true(is_wp_error(IDLM_Lead_Payments::record($f[0],$pay,$version,wp_generate_uuid4())),'Another manager cannot.');
    wp_set_current_user($manager); $recorded=IDLM_Lead_Payments::record($f[0],$pay,$version,wp_generate_uuid4()); idlm_integration_assert_true(is_array($recorded)); idlm_integration_assert_same('124000.00',IDLM_Design_Handoffs::balance($f[0])['total_received']);
    idlm_integration_assert_true(is_wp_error(IDLM_Lead_Payments::correct($f[0],(int)$recorded['event']['confirmation_id'],$pay,'Trying to correct',IDLM_Lead_Payments::version($f[0]),wp_generate_uuid4())),'Corrections keep their own grant.');
    idlm_integration_assert_true(!IDLM_Lead_Access_Policy::can_access($f[0],true),'Recording grants no lead access.');
    wp_set_current_user($admin);
});

function idlm_stage_file($lead,$name='drawing.dwg',$purpose='design'): int {
    global $wpdb; static $next=900000; $next++; $now=current_time('mysql');
    $wpdb->insert($wpdb->prefix.'idlm_private_files',array('lead_id'=>$lead,'attachment_id'=>$next,'purpose'=>$purpose,'filename'=>$name,'mime'=>'application/acad','byte_size'=>6,'sha256'=>str_repeat('a',64),'state'=>'uploaded','archive'=>'[]','created_at'=>$now));
    return $next;
}
function idlm_stage_doc($d,$slot,$name='drawing.dwg') { return IDLM_Design_Stages::add_document($d['handoff'],$slot,'',idlm_stage_file($d['lead'],$name)); }
function idlm_stage_revision($d) { $saved=IDLM_Design_Revisions::save($d['handoff'],IDLM_Design_Revisions::working_revision_id(IDLM_Design_Handoffs::find($d['handoff'])),idlm_design_revision_post(),wp_generate_uuid4()); idlm_integration_assert_true(is_array($saved)); return $saved['revision_id']; }
function idlm_stage_row($d) { return IDLM_Design_Handoffs::find($d['handoff']); }
function idlm_stage_pay($d,$reference,$amount='500') { $r=IDLM_Lead_Payments::record($d['lead'],idlm_payment_native_data($reference,$amount),IDLM_Lead_Payments::version($d['lead']),wp_generate_uuid4()); idlm_integration_assert_true(is_array($r)); return $r; }
idlm_handoff_native_test('STAGES a project walks all six stages only when each checklist is met, with exact evidence saved', static function(): void {
    global $wpdb; $d=idlm_design_revision_fixture(); $id=$d['handoff']; $lead=idlm_handoff_lead_state($d['lead']);
    idlm_integration_assert_same('new_project',idlm_stage_row($d)->stage_key);
    idlm_integration_assert_same(true,IDLM_Design_Stages::advance($id),'Designer assigned.');
    idlm_integration_assert_same('material_selection',idlm_stage_row($d)->stage_key);
    idlm_integration_assert_same(true,IDLM_Design_Stages::advance($id),'The short advance still counts: a payment with evidence is enough.');
    idlm_integration_assert_same('masking',idlm_stage_row($d)->stage_key);
    $blocked=IDLM_Design_Stages::advance($id); idlm_integration_assert_same('idlm_design_stage_checklist',$blocked->get_error_code()); idlm_integration_assert_true(false!==strpos($blocked->get_error_message(),'Masking DWG'));
    idlm_integration_assert_same('masking',idlm_stage_row($d)->stage_key);
    idlm_integration_assert_true(is_wp_error(idlm_stage_doc($d,'final_render_pdf','wrong.dwg')),'The slot refuses the wrong type.');
    idlm_integration_assert_true(is_int(idlm_stage_doc($d,'final_render_pdf','render.pdf'))); idlm_integration_assert_true(is_int(idlm_stage_doc($d,'masking_dwg')));
    idlm_integration_assert_same(true,IDLM_Design_Stages::advance($id));
    idlm_stage_doc($d,'masking_changes_dwg'); $message=IDLM_Design_Stages::advance($id)->get_error_message(); idlm_integration_assert_true(false!==strpos($message,'Tentative quote'));
    idlm_stage_revision($d); idlm_integration_assert_true(is_wp_error(IDLM_Design_Stages::advance($id)),'Kick Off needs a new payment.');
    idlm_stage_pay($d,'KICK-OFF-1'); idlm_integration_assert_same(true,IDLM_Design_Stages::advance($id));
    idlm_stage_doc($d,'dqc_dwg'); idlm_stage_doc($d,'mom','minutes.pdf'); idlm_integration_assert_true(is_wp_error(IDLM_Design_Stages::advance($id)));
    idlm_stage_doc($d,'approval_mail','mail.png'); idlm_integration_assert_same(true,IDLM_Design_Stages::advance($id)); idlm_integration_assert_same('p2p',idlm_stage_row($d)->stage_key);
    idlm_integration_assert_same('idlm_design_stage_last',IDLM_Design_Stages::advance($id)->get_error_code());
    idlm_stage_doc($d,'p2p_dwg'); $message=IDLM_Design_Stages::complete($id)->get_error_message(); idlm_integration_assert_true(false!==strpos($message,'Final quote')&&false!==strpos($message,'Payment'));
    $approved=IDLM_Design_Revisions::customer_approve($id,idlm_stage_row($d)->design_revision_id,'Approved by email',IDLM_Lead_Payments::version($d['lead']),wp_generate_uuid4()); idlm_integration_assert_true(is_array($approved));
    idlm_stage_pay($d,'P2P-1'); idlm_integration_assert_same(true,IDLM_Design_Stages::complete($id)); idlm_integration_assert_true(!empty(idlm_stage_row($d)->completed_at));
    idlm_integration_assert_true(!IDLM_Design_Revisions::can_edit(idlm_stage_row($d)),'A complete project is read-only.');
    idlm_integration_assert_true(is_wp_error(IDLM_Design_Stages::reopen($id,' '))); idlm_integration_assert_same(true,IDLM_Design_Stages::reopen($id,'Customer asked for a change')); idlm_integration_assert_true(IDLM_Design_Revisions::can_edit(idlm_stage_row($d)));
    idlm_integration_assert_same($lead,idlm_handoff_lead_state($d['lead']),'Stages never change the lead.');
    $event=$wpdb->get_row($wpdb->prepare("SELECT details FROM {$wpdb->prefix}idlm_design_handoff_events WHERE handoff_id=%d AND event_type='stage_changed' AND details LIKE %s",$id,'%"from":"masking"%')); $details=json_decode($event->details,true);
    idlm_integration_assert_true((int)$details['evidence']['masking_dwg']['document_version_id']>0); idlm_integration_assert_same('Masking',$details['from_label']);
});
idlm_handoff_native_test('STAGES a new version keeps the old one, and archiving flags the earlier stage', static function(): void {
    global $wpdb; $d=idlm_design_revision_fixture(); $id=$d['handoff']; IDLM_Design_Stages::advance($id); IDLM_Design_Stages::advance($id);
    $first=idlm_stage_doc($d,'masking_dwg'); $second=idlm_stage_doc($d,'masking_dwg'); idlm_stage_doc($d,'final_render_pdf','render.pdf');
    $rows=$wpdb->get_results($wpdb->prepare("SELECT id,version_no,state FROM {$wpdb->prefix}idlm_design_document_versions WHERE handoff_id=%d AND document_key='masking_dwg' ORDER BY id",$id));
    idlm_integration_assert_same(2,count($rows)); idlm_integration_assert_same('superseded',$rows[0]->state); idlm_integration_assert_same('current',$rows[1]->state); idlm_integration_assert_same('2',(string)$rows[1]->version_no);
    idlm_integration_assert_same(true,IDLM_Design_Stages::advance($id)); idlm_integration_assert_same(array(),IDLM_Design_Stages::review_flags(idlm_stage_row($d)));
    idlm_integration_assert_same(true,IDLM_Design_Stages::archive_document($id,$second)); $flags=IDLM_Design_Stages::review_flags(idlm_stage_row($d)); idlm_integration_assert_same(1,count($flags)); idlm_integration_assert_true(false!==strpos($flags[0],'Masking DWG'));
    idlm_integration_assert_same('kick_off',idlm_stage_row($d)->stage_key,'Archiving does not silently undo work.');
    idlm_integration_assert_true(IDLM_Design_Stages::references_file($d['lead'],$wpdb->get_var($wpdb->prepare("SELECT attachment_id FROM {$wpdb->prefix}idlm_design_document_versions WHERE id=%d",$first))),'Old files stay referenced.');
});
idlm_handoff_native_test('STAGES permissions: reps work on assigned projects, managers control moves back and completion', static function(): void {
    $rep=idlm_design_rep(); $other=idlm_design_rep(); $d=idlm_design_revision_fixture($rep); $admin=get_current_user_id(); $id=$d['handoff'];
    wp_set_current_user($other); idlm_integration_assert_same('idlm_design_stage_denied',IDLM_Design_Stages::advance($id)->get_error_code()); idlm_integration_assert_true(is_wp_error(idlm_stage_doc($d,'masking_dwg')));
    idlm_integration_assert_true(is_wp_error(IDLM_Design_Stages::hold($id,'Not mine')));
    wp_set_current_user($rep); idlm_integration_assert_same(true,IDLM_Design_Stages::advance($id)); idlm_integration_assert_true(is_int(idlm_stage_doc($d,'masking_dwg')));
    idlm_integration_assert_same('idlm_design_stage_denied',IDLM_Design_Stages::move_back($id,'Rep tries')->get_error_code()); idlm_integration_assert_same('idlm_design_stage_denied',IDLM_Design_Stages::complete($id)->get_error_code());
    idlm_integration_assert_true(IDLM_Design_Stages::can_upload($d['lead']));
    wp_set_current_user($admin); idlm_integration_assert_true(is_wp_error(IDLM_Design_Stages::move_back($id,'  '))); idlm_integration_assert_same(true,IDLM_Design_Stages::move_back($id,'Customer changed scope')); idlm_integration_assert_same('new_project',idlm_stage_row($d)->stage_key);
    idlm_integration_assert_same(true,IDLM_Design_Handoffs::assign($id,$other,'2027-02-01','Moved studio'));
    wp_set_current_user($rep); idlm_integration_assert_same('idlm_design_stage_denied',IDLM_Design_Stages::advance($id)->get_error_code(),'Reassignment removes moves.');
});
idlm_handoff_native_test('STAGES hold stops moves and keeps the stage, a lead leaving Won stops them too, and a revoked payment is flagged', static function(): void {
    global $wpdb; $d=idlm_design_revision_fixture(); $id=$d['handoff'];
    idlm_integration_assert_true(is_wp_error(IDLM_Design_Stages::hold($id,' '))); idlm_integration_assert_true(is_wp_error(IDLM_Design_Stages::hold($id,'Waiting','not-a-date')));
    idlm_integration_assert_same(true,IDLM_Design_Stages::hold($id,'Customer travelling','2027-03-01')); idlm_integration_assert_same('idlm_design_stage_hold',IDLM_Design_Stages::advance($id)->get_error_code()); idlm_integration_assert_same('new_project',idlm_stage_row($d)->stage_key);
    idlm_integration_assert_same(true,IDLM_Design_Stages::resume($id)); idlm_integration_assert_same(null,idlm_stage_row($d)->hold_at);
    IDLM_Design_Stages::advance($id); IDLM_Design_Stages::advance($id); idlm_integration_assert_same('masking',idlm_stage_row($d)->stage_key);
    $receipt=IDLM_Design_Handoffs::current_receipts($d['lead'])[0]; idlm_integration_assert_true(is_array(IDLM_Lead_Payments::revoke($d['lead'],(int)$receipt->confirmation_id,'Payment bounced',IDLM_Lead_Payments::version($d['lead']),wp_generate_uuid4())));
    $flags=IDLM_Design_Stages::review_flags(idlm_stage_row($d)); idlm_integration_assert_true((bool)array_filter($flags,static function($flag){return false!==strpos($flag,'Payment');}),'A revoked payment flags Material Selection.');
    $wpdb->update($wpdb->prefix.'idlm_leads',array('stage'=>'Negotiation'),array('id'=>$d['lead'])); idlm_stage_doc($d,'masking_dwg'); idlm_stage_doc($d,'final_render_pdf','render.pdf'); idlm_integration_assert_same('idlm_design_stage_won',IDLM_Design_Stages::advance($id)->get_error_code());
});idlm_handoff_native_test('STAGES hold reasons: the administrator adds reasons, the picker accepts them, and a project keeps its saved text', static function(): void {
    $d=idlm_design_revision_fixture(); $id=$d['handoff'];
    idlm_integration_assert_same('saved',idlm_stage_error(IDLM_Design_Stages::save_settings(idlm_stage_form(),idlm_stage_documents_form(),IDLM_Design_Stages::fingerprint(),"Vastu check\nSociety approval")));
    idlm_integration_assert_same(array('Vastu check','Society approval'),IDLM_Design_Stages::custom_hold_reasons()); idlm_integration_assert_true(in_array('Vastu check',IDLM_Design_Stages::hold_reasons(),true));
    idlm_integration_assert_same(array('label'=>'New Project / Lead'),array('label'=>IDLM_Design_Stages::stages()['new_project']['label']),'Saving reasons leaves stages as they were.');
    $reason=IDLM_Design_Stages::hold_reason_text('Vastu check','Pandit visiting on Friday'); idlm_integration_assert_same(true,IDLM_Design_Stages::hold($id,$reason));
    idlm_integration_assert_same('Vastu check: Pandit visiting on Friday',idlm_stage_row($d)->hold_reason);
    idlm_integration_assert_same('saved',idlm_stage_error(IDLM_Design_Stages::save_settings(idlm_stage_form(),idlm_stage_documents_form(),IDLM_Design_Stages::fingerprint(),'')));
    idlm_integration_assert_same(array(),IDLM_Design_Stages::custom_hold_reasons()); idlm_integration_assert_same('Vastu check: Pandit visiting on Friday',idlm_stage_row($d)->hold_reason,'Removing a reason never rewrites a hold.');
    idlm_integration_assert_true(is_wp_error(IDLM_Design_Stages::hold_reason_text('Vastu check','')),'A removed reason is no longer offered.');
    idlm_integration_assert_same('saved',idlm_stage_error(IDLM_Design_Stages::save_settings(idlm_stage_form(),idlm_stage_documents_form(),IDLM_Design_Stages::fingerprint(),"Kept reason")));
    idlm_integration_assert_same('saved',idlm_stage_error(IDLM_Design_Stages::save_settings(idlm_stage_form(),idlm_stage_documents_form(),IDLM_Design_Stages::fingerprint())));
    idlm_integration_assert_same(array('Kept reason'),IDLM_Design_Stages::custom_hold_reasons(),'A form without the field keeps the reasons.');
    $stale=IDLM_Design_Stages::fingerprint(); IDLM_Design_Stages::save_settings(idlm_stage_form(),idlm_stage_documents_form(),$stale,"Another");
    idlm_integration_assert_same('idlm_design_stage_stale',IDLM_Design_Stages::save_settings(idlm_stage_form(),idlm_stage_documents_form(),$stale,"Late")->get_error_code(),'Reasons are part of the stale check.');
    delete_option(IDLM_Design_Stages::OPTION);
});
idlm_handoff_native_test('STAGES a manager clears a review flag with a note; it returns when a payment or document changes', static function(): void {
    global $wpdb; $rep=idlm_design_rep(); $d=idlm_design_revision_fixture($rep); $id=$d['handoff']; $admin=get_current_user_id();
    IDLM_Design_Stages::advance($id); IDLM_Design_Stages::advance($id); idlm_integration_assert_same('masking',idlm_stage_row($d)->stage_key);
    idlm_integration_assert_same('idlm_design_stage_flags',IDLM_Design_Stages::clear_flags($id,'Nothing to clear')->get_error_code());
    $receipt=IDLM_Design_Handoffs::current_receipts($d['lead'])[0]; IDLM_Lead_Payments::revoke($d['lead'],(int)$receipt->confirmation_id,'Payment bounced',IDLM_Lead_Payments::version($d['lead']),wp_generate_uuid4());
    idlm_integration_assert_same(1,count(IDLM_Design_Stages::open_flags(idlm_stage_row($d))));
    wp_set_current_user($rep); idlm_integration_assert_same('idlm_design_stage_denied',IDLM_Design_Stages::clear_flags($id,'Rep tries')->get_error_code());
    wp_set_current_user($admin); idlm_integration_assert_true(is_wp_error(IDLM_Design_Stages::clear_flags($id,'  ')),'A note is required.');
    $before=$wpdb->get_row($wpdb->prepare("SELECT stage_key,hold_at,completed_at FROM {$wpdb->prefix}idlm_design_handoffs WHERE id=%d",$id),ARRAY_A);
    idlm_integration_assert_same(true,IDLM_Design_Stages::clear_flags($id,'Customer pays again at Kick Off'));
    idlm_integration_assert_same(array(),IDLM_Design_Stages::open_flags(idlm_stage_row($d))); idlm_integration_assert_same(1,count(IDLM_Design_Stages::review_flags(idlm_stage_row($d))),'The underlying check is unchanged.');
    idlm_integration_assert_same($before,$wpdb->get_row($wpdb->prepare("SELECT stage_key,hold_at,completed_at FROM {$wpdb->prefix}idlm_design_handoffs WHERE id=%d",$id),ARRAY_A),'Clearing moves nothing.');
    idlm_integration_assert_same('Customer pays again at Kick Off',$wpdb->get_var($wpdb->prepare("SELECT reason FROM {$wpdb->prefix}idlm_design_handoff_events WHERE handoff_id=%d AND event_type='review_cleared'",$id)));
    $rows=array(idlm_stage_row($d)); $context=IDLM_Design_Stages::contexts($rows)[$d['lead']]; idlm_integration_assert_same(array(),IDLM_Design_Stages::open_flags($rows[0],$context),'The bulk read agrees.');
    idlm_stage_doc($d,'masking_dwg'); idlm_integration_assert_same(1,count(IDLM_Design_Stages::open_flags(idlm_stage_row($d))),'A later change shows the flag again.');
});

function idlm_stage_form(): array {
    $rows=array();
    foreach (IDLM_Design_Stages::stages() as $key=>$stage) { $r=$stage['requires']; $rows[]=array('key'=>$key,'label'=>$stage['label'],'slots'=>$stage['slots'],'designer'=>$r['designer']?'1':'','payment'=>$r['payment'],'tentative_quote'=>$r['tentative_quote']?'1':'','final_quote'=>$r['final_quote']?'1':'','archived'=>$stage['archived']?'1':''); }
    return $rows;
}
function idlm_stage_documents_form(): array { $rows=array(); foreach (IDLM_Design_Stages::custom_documents() as $key=>$document) { $rows[]=array('key'=>$key,'label'=>$document['label'],'type'=>$document['type']); } return $rows; }
function idlm_stage_error($result): string { return is_wp_error($result) ? $result->get_error_code() : 'saved'; }
idlm_handoff_native_test('STAGES administrators add, rename, reorder and remove stages without moving a project or rewriting history', static function(): void {
    global $wpdb; $d=idlm_design_revision_fixture(); $id=$d['handoff']; IDLM_Design_Stages::advance($id); idlm_integration_assert_same('material_selection',idlm_stage_row($d)->stage_key);
    try {
        $form=idlm_stage_form(); $bad=$form; $bad[0]['label']='';
        idlm_integration_assert_same('idlm_design_stage_label',idlm_stage_error(IDLM_Design_Stages::save_settings($bad,array(),IDLM_Design_Stages::fingerprint())),'Blank names are refused.');
        $dup=$form; $dup[1]['label']='masking'; idlm_integration_assert_same('idlm_design_stage_label',idlm_stage_error(IDLM_Design_Stages::save_settings($dup)),'Two stages cannot share a name.');
        // Add a stage after Masking with a new document, and rename Masking.
        $form[2]['label']='Masking review'; $form[2]['slots']=array('masking_dwg');
        array_splice($form,3,0,array(array('key'=>'','label'=>'Site visit','slots'=>array(),'payment'=>'advance')));
        idlm_integration_assert_same('saved',idlm_stage_error(IDLM_Design_Stages::save_settings($form,array(array('key'=>'','label'=>'Site drawing','type'=>'dwg')),IDLM_Design_Stages::fingerprint())));
        idlm_integration_assert_same(array('new_project','material_selection','masking','stage_site_visit','kick_off','dqc','p2p'),array_keys(IDLM_Design_Stages::stages()));
        idlm_integration_assert_same('Masking review',IDLM_Design_Stages::stages()['masking']['label']); idlm_integration_assert_same('stage_site_visit',IDLM_Design_Stages::neighbour('masking',1));
        idlm_integration_assert_same('material_selection',idlm_stage_row($d)->stage_key,'Saving never moves a project.');
        idlm_integration_assert_same(array('doc_site_drawing'),array_keys(IDLM_Design_Stages::custom_documents())); idlm_integration_assert_same(array('dwg'),IDLM_Design_Stages::allowed_extensions('doc_site_drawing'));
        // Require the new document on the new stage; its checklist follows.
        $form=idlm_stage_form(); $form[3]['slots']=array('doc_site_drawing');
        idlm_integration_assert_same('saved',idlm_stage_error(IDLM_Design_Stages::save_settings($form,idlm_stage_documents_form(),IDLM_Design_Stages::fingerprint())));
        $items=IDLM_Design_Stages::checklist((object)array_merge((array)idlm_stage_row($d),array('stage_key'=>'stage_site_visit')));
        idlm_integration_assert_same(array('payment','doc_site_drawing'),array_column($items,'key'));
        // A form opened before another save, or missing a stage, is refused.
        idlm_integration_assert_same('idlm_design_stage_stale',idlm_stage_error(IDLM_Design_Stages::save_settings($form,idlm_stage_documents_form(),'an-older-fingerprint')));
        $short=$form; array_pop($short); idlm_integration_assert_same('idlm_design_stage_stale',idlm_stage_error(IDLM_Design_Stages::save_settings($short,idlm_stage_documents_form())));
        // A stage with an unfinished project cannot be removed; an empty one can, and comes back.
        $occupied=$form; $occupied[1]['archived']='1'; $result=IDLM_Design_Stages::save_settings($occupied,idlm_stage_documents_form());
        idlm_integration_assert_same('idlm_design_stage_occupied',idlm_stage_error($result)); idlm_integration_assert_true(false!==strpos($result->get_error_message(),'1 unfinished project'));
        $form[5]['archived']='1'; idlm_integration_assert_same('saved',idlm_stage_error(IDLM_Design_Stages::save_settings($form,idlm_stage_documents_form())));
        idlm_integration_assert_true(IDLM_Design_Stages::stages()['dqc']['archived']); idlm_integration_assert_same('p2p',IDLM_Design_Stages::neighbour('kick_off',1),'Moves skip a removed stage.');
        $form=idlm_stage_form(); $form[5]['archived']=''; idlm_integration_assert_same('saved',idlm_stage_error(IDLM_Design_Stages::save_settings($form,idlm_stage_documents_form())));
        idlm_integration_assert_same('dqc',IDLM_Design_Stages::neighbour('kick_off',1),'A removed stage can be brought back.');
        $all=idlm_stage_form(); foreach ($all as $i=>$row) { $all[$i]['archived']='1'; } $all[1]['archived']='';
        idlm_integration_assert_same('saved',idlm_stage_error(IDLM_Design_Stages::save_settings($all,idlm_stage_documents_form())),'Every empty stage can be removed while one stays.');
        idlm_integration_assert_same('material_selection',IDLM_Design_Stages::first(),'The first active stage becomes the start.');
        $form=idlm_stage_form(); foreach ($form as $i=>$row) { $form[$i]['archived']=''; } idlm_integration_assert_same('saved',idlm_stage_error(IDLM_Design_Stages::save_settings($form,idlm_stage_documents_form())));
        // Order numbers reorder stages.
        $form=idlm_stage_form(); foreach ($form as $i=>$row) { $form[$i]['position']=(string)($i+1); } $form[3]['position']='2.5';
        idlm_integration_assert_same('saved',idlm_stage_error(IDLM_Design_Stages::save_settings($form,idlm_stage_documents_form())));
        idlm_integration_assert_same(array('new_project','material_selection','stage_site_visit','masking'),array_slice(array_keys(IDLM_Design_Stages::stages()),0,4));
        // A document with files stays.
        idlm_integration_assert_true(is_int(idlm_stage_doc($d,'doc_site_drawing'))); $docs=idlm_stage_documents_form(); $docs[0]['remove']='1';
        idlm_integration_assert_same('idlm_design_stage_document_used',idlm_stage_error(IDLM_Design_Stages::save_settings(idlm_stage_form(),$docs)));
        // History keeps the name a stage had at the time.
        $event=$wpdb->get_row($wpdb->prepare("SELECT details FROM {$wpdb->prefix}idlm_design_handoff_events WHERE handoff_id=%d AND event_type='stage_changed'",$id)); idlm_integration_assert_same('New Project / Lead',json_decode($event->details,true)['from_label']);
        $rep=idlm_design_rep(); wp_set_current_user($rep); idlm_integration_assert_same('idlm_design_stage_denied',idlm_stage_error(IDLM_Design_Stages::save_settings(idlm_stage_form())),'Administrators only.');
    } finally { delete_option(IDLM_Design_Stages::OPTION); }
});
idlm_handoff_native_test('STAGES a project stranded in a removed stage still shows and moves on', static function(): void {
    global $wpdb; $d=idlm_design_revision_fixture(); $id=$d['handoff'];
    try {
        // Simulates the moment a project moved in while the stage was being removed.
        $form=idlm_stage_form(); array_splice($form,1,0,array(array('key'=>'','label'=>'Briefing','slots'=>array())));
        idlm_integration_assert_same('saved',idlm_stage_error(IDLM_Design_Stages::save_settings($form)));
        $form=idlm_stage_form(); $form[1]['archived']='1'; idlm_integration_assert_same('saved',idlm_stage_error(IDLM_Design_Stages::save_settings($form)));
        $wpdb->update($wpdb->prefix.'idlm_design_handoffs',array('stage_key'=>'stage_briefing'),array('id'=>$id));
        idlm_integration_assert_same('stage_briefing',IDLM_Design_Stages::stage_of(idlm_stage_row($d)));
        idlm_integration_assert_same(true,IDLM_Design_Stages::advance($id,'stage_briefing'));
        idlm_integration_assert_same('material_selection',idlm_stage_row($d)->stage_key,'It moves on to the next active stage.');
    } finally { delete_option(IDLM_Design_Stages::OPTION); }
});

function idlm_board_keys($filters=array()): array {
    $defaults=array('q'=>'','stage'=>'','target'=>'','state'=>'','designer'=>0);
    return array_map(static function($row){return (int)$row->id;},IDLM_Design_Board::query(array_merge($defaults,$filters),300)['rows']);
}
idlm_handoff_native_test('BOARD shows only accepted projects in scope, and filters narrow them', static function(): void {
    global $wpdb; $rep=idlm_design_rep(); $other=idlm_design_rep(); $d=idlm_design_revision_fixture($rep); $admin=get_current_user_id(); $id=$d['handoff'];
    $wpdb->update($wpdb->prefix.'idlm_leads',array('first_name'=>'Zubin','last_name'=>'Quest','project_name'=>'Lakeview Unique','phone'=>'+915550001'),array('id'=>$d['lead']));
    idlm_integration_assert_true(in_array($id,idlm_board_keys(),true),'Administrator sees it.');
    wp_set_current_user($rep); idlm_integration_assert_same(array($id),idlm_board_keys(),'A rep sees only assigned projects.');
    wp_set_current_user($other); idlm_integration_assert_same(array(),idlm_board_keys());
    wp_set_current_user(idlm_handoff_person(IDLM_Design_Handoffs::CAPABILITY)); idlm_integration_assert_same(array(),idlm_board_keys(),'Another manager sees nothing of this.');
    wp_set_current_user($admin);
    foreach (array('Zubin Qu','Lakeview Uni','5550001') as $term){ idlm_integration_assert_true(in_array($id,idlm_board_keys(array('q'=>$term)),true),$term); }
    idlm_integration_assert_true(!in_array($id,idlm_board_keys(array('q'=>"no%such'name")),true));
    idlm_integration_assert_true(in_array($id,idlm_board_keys(array('stage'=>'new_project')),true)); idlm_integration_assert_true(!in_array($id,idlm_board_keys(array('stage'=>'masking')),true));
    idlm_integration_assert_true(in_array($id,idlm_board_keys(array('designer'=>$rep)),true)); idlm_integration_assert_true(!in_array($id,idlm_board_keys(array('designer'=>$other)),true));
    idlm_integration_assert_true(!in_array($id,idlm_board_keys(array('target'=>'overdue')),true)); $wpdb->update($wpdb->prefix.'idlm_design_handoffs',array('target_date'=>'2020-01-01'),array('id'=>$id)); idlm_integration_assert_true(in_array($id,idlm_board_keys(array('target'=>'overdue')),true));
    IDLM_Design_Stages::hold($id,'Waiting for customer'); idlm_integration_assert_true(in_array($id,idlm_board_keys(array('state'=>'hold')),true)); IDLM_Design_Stages::resume($id); idlm_integration_assert_true(!in_array($id,idlm_board_keys(array('state'=>'hold')),true));
    $f=idlm_payment_native_fixture(); $sent=IDLM_Design_Handoffs::send($f[0],idlm_handoff_input(),wp_generate_uuid4()); idlm_integration_assert_true(!in_array((int)$sent['handoff']->id,idlm_board_keys(),true),'A pending handoff stays off the board.');
});
idlm_handoff_native_test('BOARD bulk data gives the same checklist as the project page', static function(): void {
    $d=idlm_design_revision_fixture(); $id=$d['handoff']; IDLM_Design_Stages::advance($id); IDLM_Design_Stages::advance($id); idlm_stage_doc($d,'masking_dwg');
    $row=idlm_stage_row($d); $contexts=IDLM_Design_Stages::contexts(array($row)); $context=$contexts[(int)$row->lead_id];
    foreach (array('masking','kick_off','material_selection','p2p') as $stage){ idlm_integration_assert_same(wp_json_encode(IDLM_Design_Stages::checklist($row,$stage)),wp_json_encode(IDLM_Design_Stages::checklist($row,$stage,$context)),$stage); }
    idlm_integration_assert_same(IDLM_Design_Handoffs::balance($d['lead']),$context['balance']);
});
idlm_handoff_native_test('BOARD a stale or repeated move is refused', static function(): void {
    $d=idlm_design_revision_fixture(); $id=$d['handoff'];
    idlm_integration_assert_same(true,IDLM_Design_Stages::advance($id,'new_project'));
    idlm_integration_assert_same('idlm_design_stage_stale',IDLM_Design_Stages::advance($id,'new_project')->get_error_code()); idlm_integration_assert_same('material_selection',idlm_stage_row($d)->stage_key);
});

idlm_handoff_native_test('OVERVIEW cards match the lists they open, and each person sees only their scope', static function(): void {
    global $wpdb; $rep=idlm_design_rep(); $d=idlm_design_revision_fixture($rep); $admin=get_current_user_id(); $id=$d['handoff'];
    $before=IDLM_Design_Dashboard::cards(); $defaults=array('q'=>'','stage'=>'','target'=>'','state'=>'','designer'=>0);
    idlm_integration_assert_same(count(idlm_board_keys()),$before['active']);
    idlm_integration_assert_same(IDLM_Design_Board::query(array_merge($defaults,array('target'=>'overdue')),300)['total'],$before['overdue']);
    $wpdb->update($wpdb->prefix.'idlm_design_handoffs',array('target_date'=>'2020-01-01'),array('id'=>$id));
    $cards=IDLM_Design_Dashboard::cards(); idlm_integration_assert_same($before['overdue']+1,$cards['overdue']);
    $ops=IDLM_Design_Dashboard::operations(); idlm_integration_assert_true($ops['stages']['new_project']['count']>=1); idlm_integration_assert_same(1,$ops['people'][$rep]['overdue']);
    idlm_integration_assert_true((bool)array_filter($ops['attention'],static function($item)use($id){return (int)$item['row']->id===$id && false!==strpos($item['reasons'][0],'Overdue');}));
    wp_set_current_user(idlm_handoff_person(IDLM_Design_Handoffs::CAPABILITY)); $other=IDLM_Design_Dashboard::cards(); idlm_integration_assert_same(0,$other['active']); idlm_integration_assert_same(array(),IDLM_Design_Dashboard::recent_activity());
    idlm_integration_assert_same(array(),IDLM_Design_Dashboard::operations()['people']);
    wp_set_current_user($admin); $recent=IDLM_Design_Dashboard::recent_activity(50); idlm_integration_assert_true(in_array($id,array_map(static function($event){return (int)$event->handoff_id;},$recent),true));
});
idlm_handoff_native_test('OVERVIEW period figures use recorded events and keep currencies apart', static function(): void {
    global $wpdb; $d=idlm_design_revision_fixture(); $id=$d['handoff']; $p=$wpdb->prefix;
    $wpdb->query($wpdb->prepare("UPDATE {$p}idlm_design_handoff_events SET occurred_at = %s WHERE handoff_id = %d AND event_type = 'sent'",gmdate('Y-m-d H:i:s',time()-3*DAY_IN_SECONDS),$id));
    $metrics=IDLM_Design_Dashboard::period_metrics(30); idlm_integration_assert_true($metrics['accepted']>=1); idlm_integration_assert_true($metrics['accept_median']>0);
    $before=$metrics['values']['INR']['projects']??0; $saved=IDLM_Design_Revisions::save($id,$d['revision'],idlm_design_revision_post(null,array('wooden_discount_percent'=>'8','discount_remark'=>'Approved for repeat customer')),wp_generate_uuid4()); idlm_integration_assert_true(is_array($saved));
    $after=IDLM_Design_Dashboard::period_metrics(30); idlm_integration_assert_same($before+1,$after['values']['INR']['projects']); idlm_integration_assert_true($after['values']['INR']['down']>0); idlm_integration_assert_same('Approved for repeat customer',$after['discounts'][0]->reason);
    $wpdb->update($p.'idlm_design_handoffs',array('completed_at'=>current_time('mysql',true)),array('id'=>$id)); $done=IDLM_Design_Dashboard::period_metrics(30); idlm_integration_assert_true($done['completed']>=1);
});
idlm_handoff_native_test('OVERVIEW administrator queues separate unpaid, paid and handed-over Won leads', static function(): void {
    global $wpdb; $start=IDLM_Design_Dashboard::sales_queues(); $f=idlm_payment_native_fixture(); $paid=idlm_payment_native_schedule($f);
    $after=IDLM_Design_Dashboard::sales_queues(); idlm_integration_assert_same($start['awaiting_payment']+1,$after['awaiting_payment']);
    IDLM_Lead_Payments::record($f[0],idlm_payment_native_data('QUEUE-1'),(int)$paid['event']['id'],wp_generate_uuid4()); $with=IDLM_Design_Dashboard::sales_queues(); idlm_integration_assert_same($start['not_handed_over']+1,$with['not_handed_over']); idlm_integration_assert_same($start['awaiting_payment'],$with['awaiting_payment']);
    IDLM_Design_Handoffs::send($f[0],array('notes'=>'Queue test','scope_summary'=>'Kitchen','pending_items'=>'None','design_manager_id'=>get_current_user_id()),wp_generate_uuid4());
    idlm_integration_assert_same($start['not_handed_over'],IDLM_Design_Dashboard::sales_queues()['not_handed_over']);
    $compare=IDLM_Design_Dashboard::comparisons(); idlm_integration_assert_true((bool)$compare['managers']); idlm_integration_assert_true((bool)$compare['studios']);
});
idlm_handoff_native_test('RELEASE every project change queues one versioned event, including design revisions', static function(): void {
    $seen=array(); $listener=static function($lead,$event) use (&$seen) { $seen[]=$event; }; add_action('idlm_design_project_event',$listener,10,2);
    try {
        $d=idlm_design_revision_fixture(); $types=array_column($seen,'event_type'); idlm_integration_assert_same(array('sent','accepted'),$types); idlm_integration_assert_same(1,$seen[0]['schema_version']); idlm_integration_assert_true($seen[0]['event_id']>0);
        idlm_integration_assert_true(!array_diff(array_keys($seen[0]),array('schema_version','event_id','event_type','handoff_id','actor_id','occurred_at')),'No customer fields.');
        $saved=IDLM_Design_Revisions::save($d['handoff'],$d['revision'],idlm_design_revision_post(),wp_generate_uuid4()); idlm_integration_assert_true(is_array($saved)); idlm_integration_assert_same('design_revision',end($seen)['event_type']);
        IDLM_Design_Stages::advance($d['handoff']); idlm_integration_assert_same('stage_changed',end($seen)['event_type']);
        IDLM_Design_Revisions::customer_approve($d['handoff'],$saved['revision_id'],'Approved by email',IDLM_Lead_Payments::version($d['lead']),wp_generate_uuid4()); idlm_integration_assert_same('customer_approved',end($seen)['event_type']);
    } finally { remove_action('idlm_design_project_event',$listener,10); }
});
idlm_handoff_native_test('RELEASE upgrading adds tables and columns, keeps data, and changes no role or setting', static function(): void {
    global $wpdb; $d=idlm_design_revision_fixture(); $id=$d['handoff']; $p=$wpdb->prefix; $roles=get_option($wpdb->prefix.'user_roles'); $leads=$wpdb->get_var("SELECT COUNT(*) FROM {$p}idlm_leads");
    delete_option(IDLM_Design_Stages::OPTION);
    idlm_integration_assert_true(false!==$wpdb->query("ALTER TABLE {$p}idlm_design_handoffs DROP COLUMN stage_receipt_mark, DROP COLUMN completed_at"));
    idlm_integration_assert_true(false!==$wpdb->query("DROP TABLE {$p}idlm_design_document_versions"));
    update_option('idlm_schema_version','1.3.12',false);
    $result=IDLM_DB::init(); idlm_integration_assert_true(!is_wp_error($result)); idlm_integration_assert_same(IDLM_DB_VERSION,IDLM_Schema_Migration::version('idlm_schema_version'));
    idlm_integration_assert_true((bool)$wpdb->get_var("SHOW COLUMNS FROM {$p}idlm_design_handoffs LIKE 'completed_at'")); idlm_integration_assert_true((bool)$wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s',$p.'idlm_design_document_versions')));
    idlm_integration_assert_same((string)$id,(string)IDLM_Design_Handoffs::find($id)->id,'The handoff survives.'); idlm_integration_assert_same($leads,$wpdb->get_var("SELECT COUNT(*) FROM {$p}idlm_leads"));
    idlm_integration_assert_same($roles,get_option($wpdb->prefix.'user_roles'),'Upgrading changes no role.'); idlm_integration_assert_same(false,get_option(IDLM_Design_Stages::OPTION),'Upgrading writes no stage settings.');
});
idlm_handoff_native_test('RELEASE a site already on 1.3.15 gains stage_receipt_mark, and accepting then saves', static function(): void {
    global $wpdb; $p=$wpdb->prefix; $f=idlm_payment_native_fixture();
    $sent=IDLM_Design_Handoffs::send($f[0],idlm_handoff_input(),wp_generate_uuid4()); $d=array('handoff'=>(int)$sent['handoff']->id);
    idlm_integration_assert_true(false!==$wpdb->query("ALTER TABLE {$p}idlm_design_handoffs DROP COLUMN stage_receipt_mark"));
    // Before the upgrade, accepting names a missing column and the whole change rolls back.
    $suppress=$wpdb->suppress_errors(true); $broken=IDLM_Design_Handoffs::accept($d['handoff']); $wpdb->suppress_errors($suppress);
    idlm_integration_assert_same('idlm_lead_write_failed',is_wp_error($broken)?$broken->get_error_code():'saved');
    idlm_integration_assert_same(IDLM_Design_Handoffs::STATUS_NEW,IDLM_Design_Handoffs::find($d['handoff'])->status);
    update_option('idlm_schema_version','1.3.15',false);
    $result=IDLM_DB::init(); idlm_integration_assert_true(!is_wp_error($result)); idlm_integration_assert_same(IDLM_DB_VERSION,IDLM_Schema_Migration::version('idlm_schema_version'));
    idlm_integration_assert_true((bool)$wpdb->get_var("SHOW COLUMNS FROM {$p}idlm_design_handoffs LIKE 'stage_receipt_mark'"),'The upgrade adds the column.');
    $accepted=IDLM_Design_Handoffs::accept($d['handoff']);
    idlm_integration_assert_true(true===$accepted,'Accept failed after upgrade: '.(is_wp_error($accepted)?$accepted->get_error_code():var_export($accepted,true)));
});
idlm_handoff_native_test('RELEASE file upkeep keeps referenced design files and clears old unreferenced ones', static function(): void {
    global $wpdb; $d=idlm_design_revision_fixture(); $files=$wpdb->prefix.'idlm_private_files';
    $kept=idlm_stage_file($d['lead']); idlm_integration_assert_true(is_int(IDLM_Design_Stages::add_document($d['handoff'],'masking_dwg','',$kept)));
    $fresh=idlm_stage_file($d['lead']); $stale=idlm_stage_file($d['lead']); $wpdb->update($files,array('created_at'=>gmdate('Y-m-d H:i:s',time()-10*DAY_IN_SECONDS)),array('attachment_id'=>$stale));
    IDLM_Private_Files::reconcile_lead($d['lead']); $state=static function($attachment) use ($wpdb,$files) { return $wpdb->get_var($wpdb->prepare("SELECT state FROM $files WHERE attachment_id=%d",$attachment)); };
    idlm_integration_assert_same('active',$state($kept)); idlm_integration_assert_true(in_array($state($fresh),array('uploaded','active'),true),'A recent upload waits to be attached.'); idlm_integration_assert_same('erasing',$state($stale),'An old orphan is cleared.');
});
