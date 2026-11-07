<?php
/** Payment rules independent of database/settings. Builder: Shashi. */
function idlm_payment_test_schedule($total = '1000000.00', $percentages = array(10, 40, 50)) {
    $rows = array(); foreach ($percentages as $i => $percentage) { $rows[] = array('name' => 'Milestone ' . $i, 'percentage' => $percentage); }
    $terms = IDLM_Quotation_Document::capture(array('payment_structure' => wp_json_encode($rows)), $total);
    return IDLM_Lead_Payment_Ledger::schedule((object) array('grand_total' => $total, 'currency_code' => 'INR', 'document_terms' => $terms), array(0));
}
function idlm_payment_test_receipt($id, $amount, $status = 'confirmed') {
    return array('confirmation_id' => $id, 'id' => $id + 100, 'amount' => $amount, 'currency' => 'INR', 'status' => $status);
}
idlm_test('PAYMENT saved terms and explicit advance mapping never read current settings', static function(): void {
    $schedule = idlm_payment_test_schedule();
    assert_same('100000.00', $schedule['milestones'][0]['amount']);
    assert_same('position_0', $schedule['milestones'][0]['key']);
    assert_true($schedule['milestones'][0]['advance']);
    assert_true(is_wp_error(IDLM_Lead_Payment_Ledger::schedule((object) array('grand_total'=>'10', 'currency_code'=>'INR'), array(0))));
    $revision = (object) array('grand_total'=>'100', 'currency_code'=>'INR', 'document_terms'=>IDLM_Quotation_Document::capture(array('payment_structure'=>'[{"name":"Advance","percentage":10}]'), 100));
    assert_true(is_wp_error(IDLM_Lead_Payment_Ledger::schedule($revision, array())));
    assert_true(is_wp_error(IDLM_Lead_Payment_Ledger::schedule($revision, array(99))));
});
idlm_test('PAYMENT every positive short receipt remains valid and pending without tolerance', static function(): void {
    $schedule = idlm_payment_test_schedule();
    foreach (array('99999.00'=>'1.00', '99990.00'=>'10.00', '99900.00'=>'100.00', '99000.00'=>'1000.00', '0.01'=>'99999.99') as $received => $pending) {
        $balance = IDLM_Lead_Payment_Ledger::balance($schedule, array(idlm_payment_test_receipt(1, (string) $received)));
        assert_same($pending, $balance['advance_pending']); assert_same('Partial', $balance['advance_status']);
    }
    $balance = IDLM_Lead_Payment_Ledger::balance($schedule, array(idlm_payment_test_receipt(1, '99000.00'), idlm_payment_test_receipt(2, '401000.00')));
    assert_same('0.00', $balance['advance_pending']); assert_same('500000.00', $balance['total_received']); assert_same('500000.00', $balance['total_outstanding']);
    assert_same('1000.00', $balance['allocations'][1]['amount']); assert_same('400000.00', $balance['allocations'][2]['amount']);
});
idlm_test('PAYMENT excess, incomplete schedule, revoked and duplicate identities are explicit', static function(): void {
    $schedule = idlm_payment_test_schedule('1000.00', array(10, 40));
    $balance = IDLM_Lead_Payment_Ledger::balance($schedule, array(idlm_payment_test_receipt(1, '1200.00'), idlm_payment_test_receipt(2, '100.00', 'revoked')));
    assert_same('1200.00', $balance['total_received']); assert_same('200.00', $balance['excess_credit']); assert_same('700.00', $balance['unallocated_credit']); assert_same('500.00', $balance['unscheduled_balance']); assert_same('0.00', $balance['total_outstanding']);
    assert_true(is_wp_error(IDLM_Lead_Payment_Ledger::balance($schedule, array(idlm_payment_test_receipt(1,'10.00'), idlm_payment_test_receipt(1,'10.00')))));
    $receipt = idlm_payment_test_receipt(1,'10.00'); $receipt['currency']='USD'; assert_true(is_wp_error(IDLM_Lead_Payment_Ledger::balance($schedule,array($receipt))));
});
idlm_test('PAYMENT date amount method and screenshot or URN required for every receipt', static function(): void {
    $data = array('amount'=>'0.01','currency'=>'INR','received_date'=>'2026-11-05','method'=>'UPI','reference'=>' Txn 42 ');
    assert_same('txn42', IDLM_Lead_Payment_Ledger::receipt($data)['normalized_reference']);
    $ss = $data; $ss['reference']=''; $ss['attachment_id']=12; assert_same(12, IDLM_Lead_Payment_Ledger::receipt($ss)['attachment_id']);
    $both = $data; $both['attachment_id']=12; assert_same(12, IDLM_Lead_Payment_Ledger::receipt($both)['attachment_id']);
    foreach (array(array('amount'=>'0'),array('amount'=>'-1'),array('received_date'=>'2026-02-30'),array('method'=>''),array('reference'=>' '),array('received_date'=>array()),array('currency'=>'USDollars'),array('attachment_id'=>-1)) as $invalid) {
        assert_true(is_wp_error(IDLM_Lead_Payment_Ledger::receipt(array_merge($data,$invalid))));
    }
});
function idlm_payment_legacy_input(): array {
    return array('confirmed'=>true,'source_reference'=>'Signed quotation PDF, archived agreement 2024-17','milestones'=>array(
        array('name'=>'Agreed advance','percentage'=>'10','advance'=>'1'),
        array('name'=>'Agreed progress','percentage'=>'40'),
        array('name'=>'Agreed final','percentage'=>'50'),
        array('name'=>'','percentage'=>''),
    ));
}
idlm_test('PAYMENT legacy recovery uses entered historic terms and preserves exact saved basis',static function():void {
    $revision=(object)array('grand_total'=>'1000000','currency_code'=>'INR','document_terms'=>null);
    $before=clone $revision;$snapshot=IDLM_Lead_Payment_Ledger::legacy_schedule($revision,idlm_payment_legacy_input());
    assert_true(is_array($snapshot));assert_same('100000.00',$snapshot['milestones'][0]['amount']);assert_same('400000.00',$snapshot['milestones'][1]['amount']);assert_same('INR',$snapshot['currency']);assert_same(null,$snapshot['source_terms']);assert_same('explicit_legacy_recovery',$snapshot['resolution']['type']);assert_same((array)$before,(array)$revision);
    $partial=idlm_payment_legacy_input();array_pop($partial['milestones']);array_pop($partial['milestones']);$s=IDLM_Lead_Payment_Ledger::legacy_schedule($revision,$partial);assert_same('500000.00',IDLM_Lead_Payment_Ledger::balance($s,array())['unscheduled_balance']);
});
idlm_test('PAYMENT legacy recovery requires confirmation source valid percentages and explicit advance',static function():void {
    $revision=(object)array('grand_total'=>'1000','currency_code'=>'INR','document_terms'=>null);
    foreach(array(array('confirmed'=>false),array('source_reference'=>' '),array('milestones'=>array(array('name'=>'Advance','percentage'=>'101','advance'=>'1'))),array('milestones'=>array(array('name'=>'Advance','percentage'=>'10'))),array('milestones'=>array(array('name'=>'Advance','percentage'=>array(),'advance'=>'1'))))as$bad){assert_true(is_wp_error(IDLM_Lead_Payment_Ledger::legacy_schedule($revision,array_merge(idlm_payment_legacy_input(),$bad))));}
    $revision->document_terms=IDLM_Quotation_Document::capture(array('payment_structure'=>'[{"name":"Original advance","percentage":10}]'),'1000');assert_true(is_wp_error(IDLM_Lead_Payment_Ledger::legacy_schedule($revision,idlm_payment_legacy_input())));
});
idlm_test('PAYMENT tab is retired while each lead page, data and grants stay',static function():void {
    $root=dirname(__DIR__).'/';$read=static function(string $path)use($root):string{return (string)file_get_contents($root.$path);};
    $controller=$read('includes/class-lead-payment-controller.php');$nav=$read('includes/class-admin-shortcodes.php');$template=$read('templates/workspace.php');
    assert_contains("return (bool) apply_filters('idlm_show_payments_tab', false);",$controller);
    assert_contains("array('IDLM_Lead_Payment_Controller', 'render'), !IDLM_Lead_Payment_Controller::tab_shown())",$nav);
    assert_contains("if (!empty(\$idlm_section[3])) { continue; }",$template);
    // The per-lead page keeps its own checks; the drawer and Design links reuse them.
    assert_contains("return self::visible() && IDLM_Lead_Access_Policy::can_access((int) \$lead_id, true);",$controller);
    assert_contains("if (!\$lead || !self::can_open(\$lead->id)) { return ''; }",$controller);
    assert_contains("if (\$design_layer) { echo IDLM_Lead_Payment_Controller::drawer_summary(\$lead); }",$read('includes/class-admin-lead-reads.php'));
    assert_contains("if (!IDLM_Lead_Payment_Controller::can_open(\$lead_id)) { return ''; }",$read('includes/class-design-handoff-controller.php'));
    assert_contains("IDLM_Lead_Payment_Controller::tab_shown() ? 'payments' : 'leads'",$read('includes/class-design-dashboard.php'));
    // Retirement writes nothing: handlers, grants and tables are unchanged.
    foreach(array('schedule','resolve_legacy','record','correct','revoke') as $operation){assert_contains("'".$operation."'",$controller);}
    assert_contains("'Payments' => array('idlm_record_lead_payments', 'idlm_correct_lead_payments')",$read('includes/class-admin-users-controller.php'));
    assert_contains('IDLM_Lead_Payment_Schema::statements()',$read('includes/class-db.php'));
});
idlm_test('PAYMENT page is the only payment page, posts to the same handlers, and checks before storing a screenshot',static function():void {
    $root=dirname(__DIR__).'/';$c=(string)file_get_contents($root.'includes/class-lead-payment-controller.php');
    assert_contains("// One summary, one milestone table, one payments list; every form in a dialog.",$c);
    // The retired page, its separate screenshot upload step and its wp-admin copy are gone.
    foreach(array('render_history','render_entry','render_timeline','render_schedule(','idlm_payment_upload',"'upload'",'design_active','design_layer_enabled') as $retired){assert_true(false===strpos($c,$retired),'Retired payment page code is back: '.$retired);}
    assert_contains("'idlm-payments' => array('view' => 'payments', 'capability' => 'manage_options')",(string)file_get_contents(dirname(__DIR__).'/includes/class-admin.php'));
    foreach(array("'record', \$lead_id","'correct', \$lead_id","'revoke', \$lead_id","'schedule', \$lead_id","'resolve_legacy', \$lead_id") as $form){assert_contains($form,$c);}
    $check=strpos($c,"\$checked = IDLM_Lead_Payment_Ledger::receipt(array_merge(\$data, array('attachment_id' => 1)));");$grant=strpos($c,"if (!IDLM_Lead_Payments::permitted(\$lead_id, 'correct' === \$operation))");$store=strpos($c,"\$upload = IDLM_Private_Files::upload(\$file, \$lead_id, 'payment');");
    assert_true(false!==$check&&false!==$grant&&false!==$store&&$check<$grant&&$grant<$store,'Screenshot stored before checks');
    assert_contains("if ('idlm-payment-plan' === \$change) { self::render_plan_dialog(",$c);
    assert_contains("' disabled hidden') . '><legend>Which milestones are the advance?</legend>'",$c);
    $js=(string)file_get_contents($root.'assets/js/payments.js');assert_contains('set.disabled = !on;',$js);assert_contains('field.required = match;',$js);
    $css=(string)file_get_contents($root.'assets/css/crm-design-payments.css');assert_true(false===strpos($css,'!important'),'Layer uses !important');
});
idlm_test('PAYMENT Won-amount plan splits the saved Won amount by entered percentages and needs an advance',static function():void {
    $rows=array(array('name'=>'Advance','percentage'=>'25','advance'=>'1'),array('name'=>'Balance','percentage'=>'75','advance'=>''));
    $s=IDLM_Lead_Payment_Ledger::won_schedule('480000.00','INR',$rows);
    assert_true(is_array($s));assert_same('480000.00',$s['basis_total']);assert_same('120000.00',$s['milestones'][0]['amount']);assert_same('360000.00',$s['milestones'][1]['amount']);
    assert_true($s['milestones'][0]['advance']);assert_same('won_amount',$s['resolution']['type']);assert_same(null,$s['source_terms']);
    $partial=IDLM_Lead_Payment_Ledger::won_schedule('1000','INR',array(array('name'=>'Advance','percentage'=>'10','advance'=>'1')));
    assert_same('900.00',IDLM_Lead_Payment_Ledger::balance($partial,array())['unscheduled_balance']);
    assert_true(is_wp_error(IDLM_Lead_Payment_Ledger::won_schedule('0','INR',$rows)));
    assert_true(is_wp_error(IDLM_Lead_Payment_Ledger::won_schedule('1000','inr',$rows)));
    assert_true(is_wp_error(IDLM_Lead_Payment_Ledger::won_schedule('1000','INR',array(array('name'=>'Balance','percentage'=>'100','advance'=>'')))));
    assert_true(is_wp_error(IDLM_Lead_Payment_Ledger::won_schedule('1000','INR',array(array('name'=>'Too much','percentage'=>'120','advance'=>'1')))));
});
idlm_test('PAYMENT Won-amount plan writes no quotation, keeps first-plan and change grants, and is refused when a quotation basis exists',static function():void {
    $root=dirname(__DIR__).'/';$p=(string)file_get_contents($root.'includes/class-lead-payments.php');$c=(string)file_get_contents($root.'includes/class-lead-payment-controller.php');
    assert_contains("'quote_id' => 0,\n                'revision_id' => 0,",$p);
    assert_contains("if (\$prior && !self::permitted(\$lead_id, true)) { return self::error('denied', 'Changing the payment plan requires the separate payment-correction grant.'); }",$p);
    assert_contains("if (\$prior && IDLM_Design_Handoffs::locks_schedule(\$lead_id))",$p);
    assert_contains("if (!\$prior && IDLM_Design_Handoffs::basis(\$lead_id))",$p);
    assert_contains("return self::operate(\$lead_id, false, static function(\$lead) use (\$lead_id, \$milestones",$p);
    assert_contains("'won_plan', 'record', 'correct', 'revoke'",$c);
    assert_contains("if (\$won && \$can_record) {",$c);
    assert_contains('$name = $revision ? (string) $wpdb->get_var(',$c);
});
