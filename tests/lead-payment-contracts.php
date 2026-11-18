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
idlm_test('PAYMENT tab shows to administrators and managers while each lead page, data and grants stay',static function():void {
    $root=dirname(__DIR__).'/';$read=static function(string $path)use($root):string{return (string)file_get_contents($root.$path);};
    $controller=$read('includes/class-lead-payment-controller.php');$nav=$read('includes/class-admin-shortcodes.php');$template=$read('templates/workspace.php');
    assert_contains("return (bool) apply_filters('idlm_show_payments_tab', current_user_can('manage_options') || IDLM_Lead_Access_Policy::can_assign());",$controller);
    assert_contains("array('IDLM_Lead_Payment_Controller', 'render'), !IDLM_Lead_Payment_Controller::tab_shown())",$nav);
    assert_contains("if (!empty(\$idlm_section[3])) { continue; }",$template);
    // The per-lead page keeps its own checks; the drawer and Design links reuse them.
    assert_contains("return self::visible() && IDLM_Lead_Access_Policy::can_access((int) \$lead_id, true);",$controller);
    assert_contains("if (!\$lead || !self::can_open(\$lead->id)) { return ''; }",$controller);
    assert_contains("echo IDLM_Lead_Payment_Controller::drawer_summary(\$lead);",$read('includes/class-admin-lead-reads.php'));
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
    assert_contains('IDLM_Quotation_Repository::get_revision((int) $schedule[\'revision_id\']) : null;',$c);
});
/** Records every list read and answers it: lead rows for the page query, latest snapshots for the balance query. Builder: Shashi. */
final class IDLM_Payment_List_Test_DB {
    public $prefix = 'wp_';
    public $reads = array();
    public $inner;
    public $leads;
    public $snapshots;
    public function __construct($inner, array $leads, array $snapshots) { $this->inner = $inner; $this->leads = $leads; $this->snapshots = $snapshots; }
    public function esc_like($value) { return $this->inner->esc_like($value); }
    public function prepare($query, ...$args) { return $this->inner->prepare($query, ...$args); }
    public function __call($name, $args) { return $this->inner->$name(...$args); }
    public function get_results($query, $output = OBJECT) {
        $this->reads[] = $query;
        if (false !== strpos($query, 'idlm_lead_payment_allocation_sets a')) {
            preg_match('/lead_id IN \(([^)]*)\)/', $query, $m); $rows = array();
            foreach (array_map('intval', explode(',', $m[1])) as $id) { if (isset($this->snapshots[$id])) { $rows[] = (object) array('lead_id' => (string) $id, 'snapshot' => wp_json_encode($this->snapshots[$id])); } }
            return $rows;
        }
        preg_match('/LIMIT (\d+)/', $query, $m);
        return array_slice(array_map(static function($id) { return (object) array('id' => (string) $id, 'first_name' => 'Lead', 'last_name' => (string) $id, 'project_name' => 'Flat ' . $id); }, $this->leads), 0, (int) $m[1]);
    }
}
function idlm_payment_list_db(array $leads, array $snapshots): IDLM_Payment_List_Test_DB {
    $db = new IDLM_Payment_List_Test_DB($GLOBALS['wpdb'], $leads, $snapshots); $GLOBALS['wpdb'] = $db; return $db;
}
idlm_test('PAYMENT list balance is the same ledger snapshot the payment page and drawer show', static function(): void {
    idlm_test_reset_environment(); idlm_test_login(1, array('administrator'), array('manage_options'));
    $schedule = idlm_payment_test_schedule('1000.00', array(10, 90));
    $part = IDLM_Lead_Payment_Ledger::balance($schedule, array(idlm_payment_test_receipt(1, '250.00')));
    $paid = IDLM_Lead_Payment_Ledger::balance($schedule, array(idlm_payment_test_receipt(2, '1000.00')));
    $inner = $GLOBALS['wpdb'];
    try {
        idlm_payment_list_db(array(7, 8, 9), array(7 => $part, 8 => $paid));
        $page = IDLM_Lead_Payment_Controller::list_page(IDLM_Lead_Payment_Controller::list_filters(array()));
        // The panel reads the latest allocation set (IDLM_Lead_Payments::read()['balance']); the drawer reads IDLM_Design_Handoffs::balance(). Both are this snapshot.
        assert_same($part, $page['balances'][7]); assert_same($paid, $page['balances'][8]); assert_false(isset($page['balances'][9]));
        $cell = IDLM_Lead_Payment_Controller::balance_cell($page['balances'][7]);
        assert_contains('idlm-badge is-warning">Outstanding', $cell); assert_contains('₹750.00', $cell); assert_contains('of ₹1,000.00', $cell);
        assert_contains('is-success">Paid in full', IDLM_Lead_Payment_Controller::balance_cell($page['balances'][8]));
        assert_contains('is-neutral">No schedule', IDLM_Lead_Payment_Controller::balance_cell(null));
    } finally { $GLOBALS['wpdb'] = $inner; }
});
idlm_test('PAYMENT list reads a page in two queries whatever its size', static function(): void {
    idlm_test_reset_environment(); idlm_test_login(1, array('administrator'), array('manage_options'));
    $inner = $GLOBALS['wpdb'];
    try {
        foreach (array(1, 10, 60) as $count) {
            $leads = range(1, $count); $snapshots = array_fill_keys($leads, IDLM_Lead_Payment_Ledger::balance(idlm_payment_test_schedule('100.00', array(100)), array()));
            $db = idlm_payment_list_db($leads, $snapshots);
            $page = IDLM_Lead_Payment_Controller::list_page(IDLM_Lead_Payment_Controller::list_filters(array('payment_sort' => 'outstanding', 'payment_balance' => 'outstanding')));
            assert_same(2, count($db->reads), "Page of $count leads.");
            assert_same(min($count, 50), count($page['rows'])); assert_same($count > 50, $page['more']); assert_same(min($count, 50), count($page['balances']));
            $GLOBALS['wpdb'] = $inner;
        }
        $db = idlm_payment_list_db(array(), array());
        IDLM_Lead_Payment_Controller::list_page(IDLM_Lead_Payment_Controller::list_filters(array()));
        assert_same(1, count($db->reads), 'An empty page skips the balance read.');
    } finally { $GLOBALS['wpdb'] = $inner; }
});
idlm_test('PAYMENT list search is prepared, sanitized and kept inside the payment access scope', static function(): void {
    idlm_test_reset_environment(); $inner = $GLOBALS['wpdb'];
    try {
        $filters = IDLM_Lead_Payment_Controller::list_filters(array('payment_q' => " <b>Asha</b> 50%_ ", 'payment_balance' => 'bogus', 'payment_sort' => 'DROP', 'payment_page' => '-3'));
        assert_same('Asha 50%_', $filters['q']); assert_same('', $filters['balance']); assert_same('newest', $filters['sort']); assert_same(1, $filters['page']);
        // Sales representative: own assigned leads only.
        idlm_test_login(5, array('idlm_sales_rep'), array('idlm_access_leads', 'idlm_record_lead_payments'));
        $db = idlm_payment_list_db(array(), array());
        IDLM_Lead_Payment_Controller::list_page($filters);
        $sql = $db->reads[0];
        assert_contains('l.assigned_rep_id=5', $sql); assert_contains("LIKE '%Asha 50", $sql); assert_contains('50\\\\%\\\\_%', $sql, 'LIKE wildcards in the search text are escaped.'); assert_contains('q.quote_number LIKE', $sql); assert_contains('l.email LIKE', $sql); assert_contains("l.stage='Won'", $sql);
        $GLOBALS['wpdb'] = $inner;
        // Assigner whose role is limited to assigned leads: the list no longer shows leads the payment page refuses.
        $GLOBALS['idlm_test_options']['idlm_role_lead_scopes'] = array('idlm_sales_manager' => 'assigned');
        idlm_test_login(6, array('idlm_sales_manager'), array('idlm_access_leads', 'idlm_assign_leads', 'idlm_record_lead_payments'));
        $db = idlm_payment_list_db(array(), array());
        IDLM_Lead_Payment_Controller::list_page(IDLM_Lead_Payment_Controller::list_filters(array('payment_q' => '#42')));
        assert_contains('l.assigned_rep_id = 6', $db->reads[0]); assert_contains('l.id = 42', $db->reads[0]);
        $GLOBALS['wpdb'] = $inner;
        // Pre-sales and people without a payment grant read nothing.
        foreach (array(array(array('idlm_pre_sales'), array('idlm_access_leads', 'idlm_record_lead_payments')), array(array('idlm_sales_rep'), array('idlm_access_leads'))) as $who) {
            idlm_test_login(7, $who[0], $who[1]); $GLOBALS['idlm_test_users'][7]->roles = $who[0];
            $db = idlm_payment_list_db(array(1), array());
            assert_same(array(), IDLM_Lead_Payment_Controller::list_page($filters)['rows']); assert_same(0, count($db->reads));
            $GLOBALS['wpdb'] = $inner; unset($GLOBALS['idlm_test_users'][7]);
        }
    } finally { $GLOBALS['wpdb'] = $inner; }
});
idlm_test('PAYMENT list sorts and filters on the ledger inputs without dropping existing order', static function(): void {
    idlm_test_reset_environment(); idlm_test_login(1, array('administrator'), array('manage_options')); $inner = $GLOBALS['wpdb'];
    try {
        $db = idlm_payment_list_db(array(), array());
        IDLM_Lead_Payment_Controller::list_page(IDLM_Lead_Payment_Controller::list_filters(array()));
        assert_contains('ORDER BY l.id DESC LIMIT 51 OFFSET 0', $db->reads[0]); assert_false(strpos($db->reads[0], 'idlm_lead_payment_schedules'));
        $GLOBALS['wpdb'] = $inner;
        $db = idlm_payment_list_db(array(), array());
        IDLM_Lead_Payment_Controller::list_page(IDLM_Lead_Payment_Controller::list_filters(array('payment_sort' => 'outstanding', 'payment_balance' => 'paid', 'payment_page' => '2')));
        $sql = $db->reads[0];
        assert_contains("v.status <> 'revoked'", $sql); assert_contains('v.id = c.current_version_id AND v.confirmation_id = c.id', $sql);
        assert_contains('GREATEST(0, sch.basis_total - COALESCE(rec.received, 0)) = 0', $sql);
        assert_contains('ORDER BY (sch.lead_id IS NULL), GREATEST(0, sch.basis_total - COALESCE(rec.received, 0)) DESC, l.id DESC LIMIT 51 OFFSET 50', $sql);
    } finally { $GLOBALS['wpdb'] = $inner; }
});
idlm_test('PAYMENT list is on the menu for administrators and managers, not representatives', static function(): void {
    idlm_test_reset_environment();
    idlm_test_login(1, array('administrator'), array('manage_options')); assert_true(IDLM_Lead_Payment_Controller::tab_shown());
    idlm_test_login(4, array('idlm_sales_manager'), array('idlm_access_leads', 'idlm_assign_leads', 'idlm_record_lead_payments')); assert_true(IDLM_Lead_Payment_Controller::tab_shown());
    idlm_test_login(5, array('idlm_sales_rep'), array('idlm_access_leads', 'idlm_record_lead_payments')); assert_false(IDLM_Lead_Payment_Controller::tab_shown());
    // A suspended manager loses it with the rest of their access.
    $GLOBALS['idlm_test_user_meta'][4][IDLM_Admin_Users_Controller::DISABLED_META_KEY] = '1';
    idlm_test_login(4, array('idlm_sales_manager'), array('idlm_access_leads', 'idlm_assign_leads', 'idlm_record_lead_payments')); assert_false(IDLM_Lead_Payment_Controller::tab_shown());
});
idlm_test('PAYMENT sidebar navigation drops payment keys so Payments opens the list', static function(): void {
    foreach (array('payment_lead_id', 'payment_revision_id', 'payment_saved', 'payment_done', 'payment_q', 'payment_balance', 'payment_sort', 'payment_page') as $key) { assert_true(in_array($key, IDLM_Lead_Payment_Controller::QUERY_KEYS, true), $key); }
    assert_contains('IDLM_Design_Handoff_Controller::QUERY_KEYS, IDLM_Lead_Payment_Controller::QUERY_KEYS)', (string) file_get_contents(dirname(__DIR__) . '/includes/class-admin-shortcodes.php'));
});
idlm_test('PAYMENT proof check accepts current private payment images only', static function(): void {
    $file = static function(array $over = array()) { return (object) array_merge(array('mime' => 'image/png', 'byte_size' => 1000, 'state' => 'active', 'created_at' => '2020-01-01 00:00:00'), $over); };
    assert_true(IDLM_Lead_Payments::proof_ok($file()));
    assert_true(IDLM_Lead_Payments::proof_ok($file(array('state' => 'uploaded', 'created_at' => current_time('mysql')))));
    assert_false(IDLM_Lead_Payments::proof_ok(null), 'A screenshot whose file record is gone is refused.');
    assert_false(IDLM_Lead_Payments::proof_ok($file(array('mime' => 'application/pdf'))));
    assert_false(IDLM_Lead_Payments::proof_ok($file(array('byte_size' => IDLM_Private_Files::MAX_BYTES + 1))));
    assert_false(IDLM_Lead_Payments::proof_ok($file(array('state' => 'uploaded'))), 'An unused upload older than 7 days is refused.');
    $source = (string) file_get_contents(dirname(__DIR__) . '/includes/class-lead-payments.php');
    assert_contains('if (!self::proof_ok($file)) {', $source);
    // Forms prefill a handoff screenshot only when the receipt check would accept it.
    assert_contains("IDLM_Lead_Payments::usable_proof(\$lead_id, (int) \$row->advance_attachment_id) ? (int) \$row->advance_attachment_id : 0", (string) file_get_contents(dirname(__DIR__) . '/includes/class-lead-payment-controller.php'));
    assert_contains("IDLM_Lead_Payments::usable_proof(\$lead_id, (int) \$row->advance_attachment_id)", (string) file_get_contents(dirname(__DIR__) . '/includes/class-design-handoffs.php'));
});
idlm_test('PAYMENT refused save returns to the page with the reason and typed values, once, for the same person', static function(): void {
    idlm_test_reset_environment(); idlm_test_login(1, array('administrator'), array('manage_options'));
    $token = IDLM_Lead_Payment_Controller::save_retry('Receipt refused.', 'idlm-payment-record', array('amount' => '10,000', 'note' => "<b>Paid</b>\nby UPI", 'reference' => array('x'), 'payment_lead_id' => '9', 'attachment_id' => '7'));
    assert_true(1 === preg_match('/^[a-z0-9]{20}$/', $token));
    idlm_test_login(2, array('idlm_sales_rep'), array('idlm_access_leads'));
    assert_same(null, IDLM_Lead_Payment_Controller::take_retry($token), 'Another person cannot read it.');
    idlm_test_login(1, array('administrator'), array('manage_options'));
    $retry = IDLM_Lead_Payment_Controller::take_retry($token);
    assert_same('Receipt refused.', $retry['message']); assert_same('idlm-payment-record', $retry['dialog']);
    assert_same(array('amount' => '10,000', 'note' => "Paid\nby UPI"), $retry['values'], 'Only typed text fields are kept, sanitized; IDs and arrays are not.');
    assert_same(null, IDLM_Lead_Payment_Controller::take_retry($token), 'It is read once.');
    assert_same('', IDLM_Lead_Payment_Controller::take_retry(IDLM_Lead_Payment_Controller::save_retry('x', 'other-dialog"><script>', array()))['dialog']);
    assert_same(null, IDLM_Lead_Payment_Controller::take_retry('../../etc'));
    $source = (string) file_get_contents(dirname(__DIR__) . '/includes/class-lead-payment-controller.php');
    assert_false(strpos($source, "'Payment not saved'"), 'No WordPress error page for a refused save.');
    assert_contains("wp_safe_redirect(add_query_arg(array('payment_lead_id' => \$lead_id, 'payment_error' => \$token), \$url)); exit;", $source);
    assert_contains("self::hidden('payment_dialog', \$id);", $source);
    assert_contains("document.querySelector('[data-payment-reopen]')", (string) file_get_contents(dirname(__DIR__) . '/assets/js/design-detail.js'));
});
