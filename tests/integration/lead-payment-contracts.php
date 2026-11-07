<?php
/** PAYMENT checked persistence, upgrade and access contracts. Builder: Shashi. */
if (!defined('ABSPATH') || strpos(DB_NAME, 'idlm_test_') !== 0) { exit(1); }
function idlm_payment_native_test($name, $callback): void {
    idlm_integration_test($name, static function() use ($callback): void {
        global $wpdb; $GLOBALS['idlm_payment_native_leads'] = array(); $prior_actor=get_current_user_id();
        try { $callback(); } finally {
            foreach ($GLOBALS['idlm_payment_native_leads'] as $lead) {
                foreach (array_merge(IDLM_Lead_Payment_Schema::tables(), array('idlm_lead_outbox')) as $suffix) { $wpdb->delete($wpdb->prefix.$suffix, array('lead_id'=>$lead)); }
            }
            wp_set_current_user($prior_actor);
        }
    });
}
function idlm_payment_native_fixture(): array {
    global $wpdb;
    $admin = wp_insert_user(array('user_login'=>'payment-admin-'.wp_generate_uuid4(), 'user_pass'=>wp_generate_password(), 'role'=>'administrator'));
    $user = new WP_User($admin); $user->add_cap('manage_options'); wp_set_current_user($admin);
    $fixture = idlm_quo_migration_fixture(); $GLOBALS['idlm_payment_native_leads'][] = $fixture[0];
    $wpdb->update($wpdb->prefix.'idlm_leads', array('stage'=>'Won','deal_value'=>'1000000','assigned_rep_id'=>$admin), array('id'=>$fixture[0]));
    $terms = IDLM_Quotation_Document::capture(array('payment_structure'=>'[{"name":"Advance","percentage":10},{"name":"Next","percentage":40},{"name":"Final","percentage":50}]'), '1000000');
    $wpdb->update($wpdb->prefix.'idlm_quote_revisions', array('document_terms'=>$terms,'grand_total'=>'1000000','currency_code'=>'INR'), array('id'=>$fixture[2]));
    return $fixture;
}
function idlm_payment_native_data($reference = 'synthetic-urn', $amount = '99000'): array {
    return array('amount'=>$amount,'currency'=>'INR','received_date'=>'2026-11-05','method'=>'UPI','reference'=>$reference);
}
function idlm_payment_native_schedule($fixture): array {
    $result = IDLM_Lead_Payments::publish_schedule($fixture[0],$fixture[2],array(0),'Agreed saved sales revision',0,wp_generate_uuid4());
    idlm_integration_assert_true(is_array($result), is_wp_error($result) ? $result->get_error_message() : 'schedule failed'); return $result;
}
function idlm_payment_native_snapshot($lead): array {
    global $wpdb; $out = array();
    foreach (IDLM_Lead_Payment_Schema::tables() as $suffix) { $out[$suffix] = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$wpdb->prefix}{$suffix} WHERE lead_id=%d ORDER BY id",$lead),ARRAY_A); }
    return $out;
}
idlm_payment_native_test('PAYMENT saved basis partial receipts subsequent carry and sales invariants',static function():void {
    global $wpdb; $f=idlm_payment_native_fixture(); $lead_before=$wpdb->get_row("SELECT * FROM {$wpdb->prefix}idlm_leads WHERE id={$f[0]}",ARRAY_A); $quote_before=idlm_quo_snapshot($f);
    $schedule=idlm_payment_native_schedule($f); $key=wp_generate_uuid4();
    $first=IDLM_Lead_Payments::record($f[0],idlm_payment_native_data(),(int)$schedule['event']['id'],$key); idlm_integration_assert_true(is_array($first));
    idlm_integration_assert_same('1000.00',$first['balance']['advance_pending']); idlm_integration_assert_same('901000.00',$first['balance']['total_outstanding']);
    $retry=IDLM_Lead_Payments::record($f[0],idlm_payment_native_data(),(int)$schedule['event']['id'],$key); idlm_integration_assert_same(true,$retry['replayed']);
    $second=IDLM_Lead_Payments::record($f[0],idlm_payment_native_data('second','401000'),(int)$first['event']['id'],wp_generate_uuid4()); idlm_integration_assert_true(is_array($second));
    idlm_integration_assert_same('500000.00',$second['balance']['total_received']); idlm_integration_assert_same('500000.00',$second['balance']['total_outstanding']);
    $read=IDLM_Lead_Payments::read($f[0]); idlm_integration_assert_same(2,count($read['receipts'])); idlm_integration_assert_same($second['balance'],$read['balance']);
    idlm_integration_assert_same($lead_before,$wpdb->get_row("SELECT * FROM {$wpdb->prefix}idlm_leads WHERE id={$f[0]}",ARRAY_A)); idlm_integration_assert_same($quote_before,idlm_quo_snapshot($f));
});
idlm_payment_native_test('PAYMENT stale retry and duplicate reference review preserve one receipt identity',static function():void {
    $f=idlm_payment_native_fixture(); $schedule=idlm_payment_native_schedule($f); $version=(int)$schedule['event']['id']; $key=wp_generate_uuid4(); $data=idlm_payment_native_data();
    $first=IDLM_Lead_Payments::record($f[0],$data,$version,$key); $before=idlm_payment_native_snapshot($f[0]);
    idlm_integration_assert_true(is_wp_error(IDLM_Lead_Payments::record($f[0],$data,$version,wp_generate_uuid4())));
    idlm_integration_assert_true(is_wp_error(IDLM_Lead_Payments::record($f[0],idlm_payment_native_data('different'),$version,$key)));
    idlm_integration_assert_true(is_wp_error(IDLM_Lead_Payments::record($f[0],$data,(int)$first['event']['id'],wp_generate_uuid4())));
    idlm_integration_assert_same($before,idlm_payment_native_snapshot($f[0]));
    $data['duplicate_review_reason']='Customer reused reference for independently recorded second payment';
    idlm_integration_assert_true(is_array(IDLM_Lead_Payments::record($f[0],$data,(int)$first['event']['id'],wp_generate_uuid4())));
});
idlm_payment_native_test('PAYMENT correction revocation and schedule amendments preserve prior versions',static function():void {
    global $wpdb; $f=idlm_payment_native_fixture(); $schedule=idlm_payment_native_schedule($f); $first=IDLM_Lead_Payments::record($f[0],idlm_payment_native_data(),(int)$schedule['event']['id'],wp_generate_uuid4());
    $receipt=(int)$first['event']['confirmation_id']; $original=idlm_payment_native_snapshot($f[0]);
    $correct=IDLM_Lead_Payments::correct($f[0],$receipt,idlm_payment_native_data('corrected','100000'),'Amount verified against receipt',(int)$first['event']['id'],wp_generate_uuid4()); idlm_integration_assert_true(is_array($correct)); idlm_integration_assert_same('0.00',$correct['balance']['advance_pending']);
    $amended=IDLM_Lead_Payments::publish_schedule($f[0],$f[2],array(0),'Explicitly republished same immutable basis',(int)$correct['event']['id'],wp_generate_uuid4()); idlm_integration_assert_true(is_array($amended));
    $revoked=IDLM_Lead_Payments::revoke($f[0],$receipt,'Receipt entered against wrong customer',(int)$amended['event']['id'],wp_generate_uuid4()); idlm_integration_assert_true(is_array($revoked)); idlm_integration_assert_same('0.00',$revoked['balance']['total_received']);
    $after=idlm_payment_native_snapshot($f[0]); idlm_integration_assert_same($original['idlm_lead_payment_versions'][0],$after['idlm_lead_payment_versions'][0]); idlm_integration_assert_same(3,count($after['idlm_lead_payment_versions'])); idlm_integration_assert_same(5,count($after['idlm_lead_payment_allocation_sets']));
    idlm_integration_assert_true(!IDLM_Lead_Payments::partial_recovery_safe());
});
foreach (array('idlm_lead_payment_versions','idlm_lead_payment_allocation_sets','idlm_lead_payment_events','idlm_lead_outbox') as $table) {
    idlm_payment_native_test('PAYMENT required '.$table.' write failure rolls back receipt and allows retry',static function()use($table):void {
        $f=idlm_payment_native_fixture(); $s=idlm_payment_native_schedule($f); $before=idlm_payment_native_snapshot($f[0]); $key=wp_generate_uuid4();
        $result=idlm_lifecycle_failure($table,'INSERT',static function()use($f,$s,$key){return IDLM_Lead_Payments::record($f[0],idlm_payment_native_data(),(int)$s['event']['id'],$key);});
        idlm_integration_assert_true(is_wp_error($result)); idlm_integration_assert_same($before,idlm_payment_native_snapshot($f[0]));
        idlm_integration_assert_true(is_array(IDLM_Lead_Payments::record($f[0],idlm_payment_native_data(),(int)$s['event']['id'],$key)));
    });
}
idlm_payment_native_test('PAYMENT explicit grants ownership suspension and screenshot binding fail closed',static function():void {
    global $wpdb; $f=idlm_payment_native_fixture(); $s=idlm_payment_native_schedule($f); $actor=get_current_user_id();
    $user=wp_insert_user(array('user_login'=>'payment-'.wp_generate_uuid4(),'user_pass'=>wp_generate_password(),'role'=>'sales_executive')); $u=get_user_by('id',$user); $u->add_cap('idlm_access_leads'); $u->add_cap('idlm_record_lead_payments');
    wp_set_current_user($user); $before=idlm_payment_native_snapshot($f[0]); idlm_integration_assert_true(is_wp_error(IDLM_Lead_Payments::record($f[0],idlm_payment_native_data(),(int)$s['event']['id'],wp_generate_uuid4())));
    $wpdb->update($wpdb->prefix.'idlm_leads',array('assigned_rep_id'=>$user),array('id'=>$f[0]));
    $data=idlm_payment_native_data(); $data['attachment_id']=999999; idlm_integration_assert_true(is_wp_error(IDLM_Lead_Payments::record($f[0],$data,(int)$s['event']['id'],wp_generate_uuid4())));
    update_user_meta($user,'idlm_crm_access_disabled','1'); idlm_integration_assert_true(is_wp_error(IDLM_Lead_Payments::record($f[0],idlm_payment_native_data(),(int)$s['event']['id'],wp_generate_uuid4()))); idlm_integration_assert_same($before,idlm_payment_native_snapshot($f[0]));
    wp_set_current_user($actor);
});
idlm_payment_native_test('PAYMENT additive core upgrade is idempotent and preserves existing ledger',static function():void {
    global $wpdb; $f=idlm_payment_native_fixture(); idlm_payment_native_schedule($f); $before=idlm_payment_native_snapshot($f[0]);
    update_option('idlm_schema_version','1.3.9',false); idlm_integration_assert_same(true,IDLM_DB::create_tables()); idlm_integration_assert_same(true,IDLM_DB::create_tables()); idlm_integration_assert_same($before,idlm_payment_native_snapshot($f[0]));
});
idlm_payment_native_test('PAYMENT independent concurrent receipts serialize and stale writer cannot double allocate',static function():void {
    global $config; $f=idlm_payment_native_fixture(); $s=idlm_payment_native_schedule($f); $actor=get_current_user_id();
    @unlink(WP_CONTENT_DIR.'/payment-barrier'); $jobs=array();
    foreach(array(1,2)as$i){
        $cmd=escapeshellarg(PHP_BINARY).' '.escapeshellarg(__DIR__.'/worker.php').' payment-race '.(int)$actor.' '.(int)$f[0].' '.(int)$s['event']['id'].' '.escapeshellarg(wp_generate_uuid4());
        $proc=proc_open($cmd,array(1=>array('pipe','w'),2=>array('pipe','w')),$pipes);$jobs[]=array($proc,$pipes);
    }
    $success=0;$stale=0;
    foreach($jobs as $job){list($proc,$pipes)=$job;$out=stream_get_contents($pipes[1]);$err=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);idlm_integration_assert_same(0,proc_close($proc),$out.$err);$result=json_decode($out,true);if(isset($result['event_id'])){$success++;}if(($result['error']??'')==='idlm_payment_stale'){$stale++;}}
    idlm_integration_assert_same(1,$success);idlm_integration_assert_same(1,$stale);
    $context=IDLM_Lead_Payments::read($f[0]);idlm_integration_assert_same('1.00',$context['balance']['total_received']);idlm_integration_assert_same(1,count($context['receipts']));
});
idlm_payment_native_test('PAYMENT durable envelope retry keeps private receipt details out of hooks',static function():void {
    global $wpdb; $f=idlm_payment_native_fixture();$s=idlm_payment_native_schedule($f);$calls=0;
    $callback=static function($lead,$envelope)use(&$calls):void{$calls++;idlm_integration_assert_true(!isset($envelope['reference'])&&!isset($envelope['attachment_id']));if($calls===1){throw new RuntimeException('Synthetic downstream failure');}};
    add_action('idlm_lead_payment_recorded',$callback,10,2);
    try {
        $result=IDLM_Lead_Payments::record($f[0],idlm_payment_native_data(),(int)$s['event']['id'],wp_generate_uuid4());idlm_integration_assert_true(is_array($result));
        $outbox=(int)$wpdb->get_var($wpdb->prepare("SELECT id FROM {$wpdb->prefix}idlm_lead_outbox WHERE lead_id=%d AND hook='idlm_lead_payment_recorded'",$f[0]));idlm_integration_assert_true($outbox>0);$wpdb->update($wpdb->prefix.'idlm_lead_outbox',array('available_at'=>'2000-01-01 00:00:00'),array('id'=>$outbox));
        idlm_integration_assert_same(true,IDLM_Lead_Outbox::dispatch($outbox));idlm_integration_assert_same(2,$calls);idlm_integration_assert_same('99000.00',IDLM_Lead_Payments::read($f[0])['balance']['total_received']);
    } finally {remove_action('idlm_lead_payment_recorded',$callback,10);}
});
idlm_payment_native_test('PAYMENT lead erasure and JSON recovery refuse to orphan ledger history',static function():void {
    global $wpdb; $f=idlm_payment_native_fixture();$s=idlm_payment_native_schedule($f);IDLM_Lead_Payments::record($f[0],idlm_payment_native_data(),(int)$s['event']['id'],wp_generate_uuid4());
    $method=new ReflectionMethod('IDLM_Backup','snapshot');if(PHP_VERSION_ID<80100){$method->setAccessible(true);}$payload=$method->invoke(null);idlm_integration_assert_true(is_wp_error($payload));
    idlm_integration_assert_same(1,IDLM_Lead_Deletion_Service::delete($f[0]));foreach(idlm_payment_native_snapshot($f[0])as$rows){idlm_integration_assert_same(array(),$rows);}
});
function idlm_payment_native_legacy_input(): array { return array('confirmed'=>true,'source_reference'=>'Signed archived quote PDF agreement 2024-17','milestones'=>array(array('name'=>'Historic advance','percentage'=>'10','advance'=>'1'),array('name'=>'Historic final','percentage'=>'90'))); }
idlm_payment_native_test('PAYMENT legacy schedule resolution retains quotation and records past receipts once',static function():void {
    global $wpdb;$f=idlm_payment_native_fixture();$wpdb->update($wpdb->prefix.'idlm_quote_revisions',array('document_terms'=>null),array('id'=>$f[2]));$quote_before=idlm_quo_snapshot($f);$lead_before=$wpdb->get_row("SELECT * FROM {$wpdb->prefix}idlm_leads WHERE id={$f[0]}",ARRAY_A);$key=wp_generate_uuid4();
    $r=IDLM_Lead_Payments::resolve_legacy_schedule($f[0],$f[2],idlm_payment_native_legacy_input(),'Recovered from the signed archived agreement',0,$key);idlm_integration_assert_true(is_array($r),is_wp_error($r)?$r->get_error_message():'No result');idlm_integration_assert_same('100000.00',$r['balance']['expected_advance']);
    $retry=IDLM_Lead_Payments::resolve_legacy_schedule($f[0],$f[2],idlm_payment_native_legacy_input(),'Recovered from the signed archived agreement',0,$key);idlm_integration_assert_same(true,$retry['replayed']);
    $schedule=IDLM_Lead_Payments::read($f[0])['schedule'];$snapshot=json_decode($schedule['snapshot'],true);idlm_integration_assert_same(get_current_user_id(),$snapshot['resolution']['resolved_by']);idlm_integration_assert_true(!empty($snapshot['resolution']['resolved_at']));
    $past=idlm_payment_native_data('ARCHIVED-URN','99000');$past['received_date']='2024-02-15';$payment=IDLM_Lead_Payments::record($f[0],$past,(int)$r['event']['id'],wp_generate_uuid4());idlm_integration_assert_true(is_array($payment));idlm_integration_assert_same('1000.00',$payment['balance']['advance_pending']);idlm_integration_assert_same('901000.00',$payment['balance']['total_outstanding']);
    idlm_integration_assert_same($quote_before,idlm_quo_snapshot($f));idlm_integration_assert_same($lead_before,$wpdb->get_row("SELECT * FROM {$wpdb->prefix}idlm_leads WHERE id={$f[0]}",ARRAY_A));
    $old=idlm_payment_native_snapshot($f[0])['idlm_lead_payment_schedules'][0];$input=idlm_payment_native_legacy_input();$input['milestones'][0]['percentage']='20';$input['milestones'][1]['percentage']='80';$amended=IDLM_Lead_Payments::resolve_legacy_schedule($f[0],$f[2],$input,'Corrected recovered milestone from signed agreement',(int)$payment['event']['id'],wp_generate_uuid4());idlm_integration_assert_true(is_array($amended));idlm_integration_assert_same('99000.00',$amended['balance']['total_received']);idlm_integration_assert_same($old,idlm_payment_native_snapshot($f[0])['idlm_lead_payment_schedules'][0]);
});
idlm_payment_native_test('PAYMENT ordinary sales cannot resolve missing terms and known terms cannot be overridden',static function():void {
    global $wpdb;$f=idlm_payment_native_fixture();$admin=get_current_user_id();$before=idlm_payment_native_snapshot($f[0]);idlm_integration_assert_true(is_wp_error(IDLM_Lead_Payments::resolve_legacy_schedule($f[0],$f[2],idlm_payment_native_legacy_input(),'Attempt to replace known saved terms',0,wp_generate_uuid4())));idlm_integration_assert_same($before,idlm_payment_native_snapshot($f[0]));
    $user=wp_insert_user(array('user_login'=>'legacy-sales-'.wp_generate_uuid4(),'user_pass'=>wp_generate_password(),'role'=>'sales_executive'));$u=new WP_User($user);$u->add_cap('idlm_access_leads');$u->add_cap('idlm_record_lead_payments');$wpdb->update($wpdb->prefix.'idlm_leads',array('assigned_rep_id'=>$user),array('id'=>$f[0]));$wpdb->update($wpdb->prefix.'idlm_quote_revisions',array('document_terms'=>null),array('id'=>$f[2]));wp_set_current_user($user);
    idlm_integration_assert_true(is_wp_error(IDLM_Lead_Payments::resolve_legacy_schedule($f[0],$f[2],idlm_payment_native_legacy_input(),'Unauthorized historic terms recovery',0,wp_generate_uuid4())));idlm_integration_assert_same($before,idlm_payment_native_snapshot($f[0]));wp_set_current_user($admin);
});
idlm_payment_native_test('PAYMENT legacy resolution rollback preserves missing terms and permits retry',static function():void {
    global $wpdb;$f=idlm_payment_native_fixture();$wpdb->update($wpdb->prefix.'idlm_quote_revisions',array('document_terms'=>null),array('id'=>$f[2]));$before=idlm_payment_native_snapshot($f[0]);$key=wp_generate_uuid4();
    $r=idlm_lifecycle_failure('idlm_lead_payment_events','INSERT',static function()use($f,$key){return IDLM_Lead_Payments::resolve_legacy_schedule($f[0],$f[2],idlm_payment_native_legacy_input(),'Recovered terms with failure injection',0,$key);});idlm_integration_assert_true(is_wp_error($r));idlm_integration_assert_same($before,idlm_payment_native_snapshot($f[0]));
    idlm_integration_assert_true(is_array(IDLM_Lead_Payments::resolve_legacy_schedule($f[0],$f[2],idlm_payment_native_legacy_input(),'Recovered terms with failure injection',0,$key)));
});
