<?php
/** Design handoff rules that need no database. Builder: Shashi. */
function idlm_handoff_source(string $path): string { return (string) file_get_contents(dirname(__DIR__) . '/' . $path); }

idlm_test('HANDOFF states allow send, accept, assign, return and resend only', static function(): void {
    $new = IDLM_Design_Handoffs::STATUS_NEW; $accepted = IDLM_Design_Handoffs::STATUS_ACCEPTED; $back = IDLM_Design_Handoffs::STATUS_SENT_BACK;
    assert_true(IDLM_Design_Handoffs::can_transition('', 'send')); assert_true(IDLM_Design_Handoffs::can_transition($back, 'send'));
    assert_false(IDLM_Design_Handoffs::can_transition($new, 'send')); assert_false(IDLM_Design_Handoffs::can_transition($accepted, 'send'));
    assert_true(IDLM_Design_Handoffs::can_transition($new, 'accept')); assert_false(IDLM_Design_Handoffs::can_transition($accepted, 'accept'));
    assert_true(IDLM_Design_Handoffs::can_transition($accepted, 'assign')); assert_false(IDLM_Design_Handoffs::can_transition($new, 'assign'));
    assert_true(IDLM_Design_Handoffs::can_transition($new, 'send_back')); assert_false(IDLM_Design_Handoffs::can_transition($accepted, 'send_back'), 'An accepted handover is amended, never returned.');
    assert_false(IDLM_Design_Handoffs::can_transition($new, 'delete'));
});

idlm_test('HANDOFF target date must be a real calendar date', static function(): void {
    assert_same('2026-12-31', IDLM_Design_Handoffs::clean_target('2026-12-31'));
    foreach (array('', '2026-02-30', '31-12-2026', array('2026-12-31')) as $bad) { assert_true(is_wp_error(IDLM_Design_Handoffs::clean_target($bad))); }
});

idlm_test('HANDOFF notes and reasons cannot be blank or whitespace', static function(): void {
    assert_true(is_wp_error(IDLM_Design_Handoffs::required_text('', 'Notes')));
    assert_true(is_wp_error(IDLM_Design_Handoffs::required_text("  \n\t ", 'Notes')));
    assert_true(is_wp_error(IDLM_Design_Handoffs::required_text(array('x'), 'Notes')));
    assert_true(is_wp_error(IDLM_Design_Handoffs::required_text(str_repeat('a', IDLM_Design_Handoffs::MAX_TEXT_BYTES + 1), 'Notes')));
    assert_same('Keep the balcony', IDLM_Design_Handoffs::required_text('  Keep the balcony ', 'Notes'));
});

idlm_test('HANDOFF the advance is the first milestone with an amount', static function(): void {
    assert_same(0, IDLM_Design_Handoffs::first_payable_position(array(array('amount' => '100.00'), array('amount' => '900.00'))));
    assert_same(1, IDLM_Design_Handoffs::first_payable_position(array(array('amount' => '0.00'), array('amount' => '900.00'))));
    assert_same(null, IDLM_Design_Handoffs::first_payable_position(array(array('amount' => '0.00'))));
    assert_same(null, IDLM_Design_Handoffs::first_payable_position(array()));
});

idlm_test('HANDOFF permission is off by default and grants no lead access', static function(): void {
    assert_false(in_array(IDLM_Design_Handoffs::CAPABILITY, idlm_crm_default_capabilities(true), true));
    assert_false(in_array(IDLM_Design_Handoffs::REP_CAPABILITY, idlm_crm_default_capabilities(true), true));
    assert_contains(IDLM_Design_Handoffs::CAPABILITY, implode(',', array_keys(IDLM_Admin_Users_Controller::selectable_permissions('agent'))));
    assert_contains(IDLM_Design_Handoffs::REP_CAPABILITY, implode(',', array_keys(IDLM_Admin_Users_Controller::selectable_permissions('agent'))));
    assert_false(isset(IDLM_Admin_Users_Controller::selectable_permissions('idlm_pre_sales')[IDLM_Design_Handoffs::CAPABILITY]));
    $main = idlm_handoff_source('interior-design-lead-manager.php');
    assert_contains("array('read' => true, IDLM_Design_Handoffs::CAPABILITY => true)", $main, 'The manager role holds only read and its grant.');
    assert_contains("array('read' => true, IDLM_Design_Handoffs::REP_CAPABILITY => true)", $main, 'The rep role holds only read and its grant.');
    assert_false(in_array(IDLM_Design_Handoffs::REP_ROLE, idlm_get_crm_role_slugs(), true));
    assert_false(in_array(IDLM_Design_Handoffs::ROLE, idlm_get_crm_role_slugs(), true), 'The Design team role must not join sales rep pick lists.');
});

idlm_test('HANDOFF server requests check permission and nonce, and never touch lifecycle fields', static function(): void {
    $controller = idlm_handoff_source('includes/class-design-handoff-controller.php');
    assert_contains("check_ajax_referer('idlm_design_handoff_send', 'nonce')", $controller);
    assert_contains('check_admin_referer($action)', $controller);
    assert_contains('IDLM_Design_Handoffs::can_send($lead_id)', $controller);
    assert_contains('IDLM_Design_Handoffs::can_manage()', $controller);
    assert_contains('IDLM_Design_Handoffs::can_see($row)', $controller);
    assert_false((bool) preg_match('/wp_ajax_nopriv|admin_post_nopriv/', $controller));
    $service = idlm_handoff_source('includes/class-design-handoffs.php');
    assert_false((bool) preg_match('/update_lead|insert_lead|idlm_lead_stage_converted|idlm_trigger_interakt_event|Interakt|CAPI/', $service), 'A handoff must not change the lead or fire outbound automations.');
    assert_contains('FOR UPDATE', $service);
    assert_contains("notification_enabled('idlm_handoff_notifications_enabled')", $service);
});

idlm_test('HANDOFF design schema is pinned to its schema version, so a new column always triggers the upgrade', static function(): void {
    // A column added without a version bump never reaches a site already on that version,
    // and every write naming it fails ("That change was not saved"). Change both together.
    $fingerprint = md5(implode("\n", array(IDLM_Design_Handoffs::schema(), IDLM_Design_Handoffs::versions_schema(), IDLM_Design_Handoffs::events_schema(), IDLM_Design_Revisions::schema(), IDLM_Design_Stages::schema())));
    assert_same(array('1.3.18', 'a9a56737b36fe084156f4f2f8695d34f'), array(IDLM_DB_VERSION, $fingerprint), 'Design schema changed: bump IDLM_DB_VERSION, then update this pin.');
});

idlm_test('HANDOFF table is additive and joins deletion, reset, recovery and outbox rules', static function(): void {
    assert_same('1.3.18', IDLM_DB_VERSION);
    assert_same(array('idlm_design_document_versions', 'idlm_design_revisions', 'idlm_design_handoff_events', 'idlm_design_handoff_versions', 'idlm_design_handoffs'), IDLM_Design_Handoffs::tables());
    assert_contains('IDLM_Design_Handoffs::schema()', idlm_handoff_source('includes/class-db.php'));
    assert_contains('UNIQUE KEY lead_id', idlm_handoff_source('includes/class-design-handoffs.php'));
    assert_contains('IDLM_Design_Handoffs::tables()', idlm_handoff_source('includes/class-lead-deletion-service.php'));
    assert_same(array(), array_diff(IDLM_Design_Handoffs::tables(), IDLM_Lead_Deletion_Service::reset_manifest()['lead']));
    assert_contains('IDLM_Design_Handoffs::partial_recovery_safe()', idlm_handoff_source('includes/class-lead-payments.php'));
    assert_contains('idlm_design_handoff_notification', idlm_handoff_source('includes/class-lead-outbox.php'));
    assert_contains('IDLM_Design_Handoffs::may_read_file', idlm_handoff_source('includes/class-private-files.php'));
});

idlm_test('DESIGNREV discount changes are found by comparing saved values', static function(): void {
    $source = (object) array('wooden_discount_percent' => '10.00', 'additional_discount_amount' => '500.00');
    assert_same(array(), IDLM_Design_Revisions::discount_changes($source, array('wooden_discount_percent' => '10', 'additional_discount_amount' => '500.00')));
    $changes = IDLM_Design_Revisions::discount_changes($source, array('wooden_discount_percent' => '12.5', 'additional_discount_amount' => '500'));
    assert_same(array('before' => '10.00', 'after' => '12.50'), $changes['wooden_discount_percent']);
    assert_same(array('wooden_discount_percent', 'additional_discount_amount'), array_keys(IDLM_Design_Revisions::discount_changes($source, array())));
    assert_true(is_wp_error(IDLM_Design_Revisions::discount_changes($source, array('wooden_discount_percent' => '-1'))));
});

idlm_test('DESIGNREV a designer may change rates; every changed rate and new custom line is listed for review', static function(): void {
    $source = array((object) array('row_type' => 'item', 'catalogue_item_id' => 7, 'item_name' => 'Wardrobe', 'item_code' => 'W1', 'unit_rate' => '1000.00'), (object) array('row_type' => 'item', 'catalogue_item_id' => 0, 'item_name' => 'Custom shelf', 'item_code' => '', 'unit_rate' => '250.00'), (object) array('row_type' => 'header', 'description' => 'Room'));
    $line = static function($id, $name, $rate) { return array('row_type' => 'item', 'catalogue_item_id' => $id, 'item_name' => $name, 'item_code' => 7 === $id ? 'W1' : '', 'description' => $name, 'unit_rate' => $rate); };
    $same = IDLM_Design_Revisions::rate_review(array($line(7, 'Wardrobe', 1000), $line(0, 'Custom shelf', 250)), $source, array(7 => '1100.00'));
    assert_same(array(), $same['changed']); assert_same(array(), $same['custom']);
    assert_same(array(), IDLM_Design_Revisions::rate_review(array($line(7, 'Wardrobe', 1100)), $source, array(7 => '1100.00'))['changed'], 'Today\'s catalogue rate is not a change.');
    assert_same(array(array('name' => 'Wardrobe', 'before' => '1000.00', 'after' => '900.00')), IDLM_Design_Revisions::rate_review(array($line(7, 'Wardrobe', 900)), $source, array(7 => '1100.00'))['changed']);
    assert_same(array(array('name' => 'Custom shelf', 'before' => '250.00', 'after' => '100.00')), IDLM_Design_Revisions::rate_review(array($line(0, 'Custom shelf', 100)), $source, array())['changed']);
    assert_same(array(array('name' => 'Bed', 'before' => '500.00', 'after' => '100.00')), IDLM_Design_Revisions::rate_review(array($line(9, 'Bed', 100)), $source, array(9 => '500.00'))['changed'], 'A new catalogue line is compared with its catalogue rate.');
    assert_same(array('New niche'), IDLM_Design_Revisions::rate_review(array($line(0, 'New niche', 400)), $source, array())['custom']);
    $revisions = idlm_handoff_source('includes/class-design-revisions.php');
    assert_false(strpos($revisions, "error('rate'") !== false, 'A rate change is no longer refused.');
    assert_contains("error('discount', 'Only a Design Manager or administrator can change a discount.')", $revisions);
    assert_false(strpos(idlm_handoff_source('includes/class-quotation-renderer.php'), 'data-lock-rates="1"') !== false, 'The design editor no longer locks rate inputs.');
});

idlm_test('DESIGNREV a design revision leaves the system only after a manager review', static function(): void {
    $revisions = idlm_handoff_source('includes/class-design-revisions.php');
    assert_true(IDLM_Design_Revisions::is_reviewed((object) array('reviewed_at' => '2026-11-06 10:00:00', 'approved_at' => null)));
    assert_true(IDLM_Design_Revisions::is_reviewed((object) array('reviewed_at' => null, 'approved_at' => '2026-11-01 10:00:00')), 'Customer approved before reviews existed counts as reviewed.');
    assert_false(IDLM_Design_Revisions::is_reviewed((object) array('reviewed_at' => null, 'approved_at' => null)));
    assert_false(IDLM_Design_Revisions::is_reviewed(null));
    // Every way a quotation leaves: the design print, the sales print view and PDF/CSV/XLSX export.
    assert_contains('if (!self::may_share($revision_id))', $revisions);
    assert_contains('IDLM_Design_Revisions::may_share($bundle->revision->id)', idlm_handoff_source('includes/class-quotation-renderer.php'));
    assert_contains('IDLM_Design_Revisions::may_share($bundle->revision->id)', idlm_handoff_source('includes/class-quotation-exporter.php'));
    // Customer approved needs the review, checked again under the lock.
    assert_contains('if (!self::is_reviewed($meta)) { return self::error(\'review\'', $revisions);
    assert_contains("add_action('admin_post_idlm_design_quote_review'", $revisions);
    assert_contains("check_admin_referer('idlm_design_quote_review')", $revisions);
    assert_contains('IDLM_Design_Handoffs::can_decide($locked) ? array(\'reviewed_by\'', $revisions);
});

idlm_test('STAGES hold reasons: a standard list, the administrator\'s own, and Other with a note', static function(): void {
    assert_same(array(), IDLM_Design_Stages::custom_hold_reasons_from(array('version' => 2, 'stages' => array())));
    assert_same(array('Vastu check'), IDLM_Design_Stages::custom_hold_reasons_from(array('hold_reasons' => array(' Vastu check ', '', 5))));
    assert_same(array('Vastu check', 'Society approval'), IDLM_Design_Stages::parse_hold_reasons("Vastu check\r\n\nwaiting for customer\nSociety approval\nvastu check\nOther"), 'Standard reasons, Other and repeats are dropped.');
    assert_true(is_wp_error(IDLM_Design_Stages::parse_hold_reasons(str_repeat('x', 81))));
    assert_true(is_wp_error(IDLM_Design_Stages::parse_hold_reasons(implode("\n", array_map(static function($i) { return 'Reason ' . $i; }, range(1, 21))))));
    assert_same('Waiting for customer', IDLM_Design_Stages::hold_reason_text('Waiting for customer', ''));
    assert_same('Site not ready: Plastering pending', IDLM_Design_Stages::hold_reason_text('Site not ready', 'Plastering pending'));
    assert_same('Family function', IDLM_Design_Stages::hold_reason_text('Other', 'Family function'));
    assert_true(is_wp_error(IDLM_Design_Stages::hold_reason_text('Other', ' ')), 'Other needs the note.');
    assert_true(is_wp_error(IDLM_Design_Stages::hold_reason_text('Made up', '')), 'Only listed reasons are accepted.');
    assert_true(is_wp_error(IDLM_Design_Stages::hold_reason_text('', 'Some text')));
});

idlm_test('STAGES a cleared review flag is hidden until a payment or document changes, and clearing needs a manager and a note', static function(): void {
    $stages = idlm_handoff_source('includes/class-design-stages.php');
    assert_contains('return self::locked($handoff_id, array(__CLASS__, \'can_manage\')', substr($stages, strpos($stages, 'public static function clear_flags')));
    assert_contains('IDLM_Design_Handoffs::required_text($note, \'A note\')', $stages);
    assert_contains('hash_equals((string) ($cleared[\'mark\'] ?? \'\'), self::review_mark($row, $context))', $stages);
    foreach (array('includes/class-design-board.php', 'includes/class-design-dashboard.php', 'includes/class-design-stage-controller.php', 'includes/class-design-project-panel.php') as $file) { assert_contains('IDLM_Design_Stages::open_flags(', idlm_handoff_source($file), $file . ' shows only flags not cleared.'); }
});

idlm_test('DESIGNREV new amounts use the source percentages, not Quotation Setup', static function(): void {
    $terms = IDLM_Quotation_Document::capture(array('payment_structure' => '[{"name":"Advance","percentage":10},{"name":"Final","percentage":90}]'), '1000000');
    $source = (object) array('document_terms' => $terms);
    $next = json_decode(IDLM_Design_Revisions::terms_for_total($source, 1250000), true);
    assert_same('125000.00', $next['milestones'][0]['amount']); assert_same('1125000.00', $next['milestones'][1]['amount']); assert_same('10.00', $next['milestones'][0]['percentage']);
    $unknown = (object) array('document_terms' => null);
    assert_same(null, IDLM_Design_Revisions::terms_for_total($unknown, 1000));
});

idlm_test('DESIGNREV the save path never moves sales pointers, the lead value or payments', static function(): void {
    $source = idlm_handoff_source('includes/class-design-revisions.php');
    foreach (array('set_current_revision', 'supersede_revision', 'update_lead_quote_value', 'update_lead(', 'publish_schedule', 'idlm_lead_stage_converted') as $forbidden) { assert_false(strpos($source, $forbidden) !== false, 'Found ' . $forbidden); }
    assert_contains("check_admin_referer('idlm_design_quote_save')", $source);
    assert_contains("check_ajax_referer('idlm_design_quote_autosave', 'nonce')", $source);
    assert_contains('IDLM_Design_Handoffs::locks_sales_quote', idlm_handoff_source('includes/class-quotation-policy.php'));
    assert_contains('IDLM_Design_Revisions::is_design_revision', idlm_handoff_source('includes/class-lead-payments.php'));
    assert_same('Q-1-R05-DES', IDLM_Design_Revisions::revision_name('Q-1', 5));
});

idlm_test('DESIGNREV the editor script tolerates the locked discount control', static function(): void {
    assert_contains('if (addAdditionalDiscount) addAdditionalDiscount.addEventListener', idlm_handoff_source('assets/js/quotations.js'));
});

idlm_test('APPROVED the new schedule keeps percentages and advance mapping on the new total', static function(): void {
    $prior = array('snapshot' => wp_json_encode(array('milestones' => array(array('name' => 'Advance', 'percentage' => '10.00', 'advance' => true), array('name' => 'Second', 'percentage' => '40.00', 'advance' => false), array('name' => 'Final', 'percentage' => '50.00', 'advance' => false)))));
    $schedule = IDLM_Design_Revisions::approved_schedule((object) array('grand_total' => '1250000.00', 'currency_code' => 'INR'), $prior);
    assert_same('125000.00', $schedule['milestones'][0]['amount']); assert_same('500000.00', $schedule['milestones'][1]['amount']); assert_same('625000.00', $schedule['milestones'][2]['amount']);
    assert_true($schedule['milestones'][0]['advance']); assert_false($schedule['milestones'][1]['advance']);
    $balance = IDLM_Lead_Payment_Ledger::balance($schedule, array(array('confirmation_id' => 1, 'id' => 9, 'amount' => '99000.00', 'currency' => 'INR', 'status' => 'confirmed')));
    assert_same('26000.00', $balance['advance_pending']); assert_same('1151000.00', $balance['total_outstanding']);
    $down = IDLM_Design_Revisions::approved_schedule((object) array('grand_total' => '400000.00', 'currency_code' => 'INR'), $prior);
    $credit = IDLM_Lead_Payment_Ledger::balance($down, array(array('confirmation_id' => 1, 'id' => 9, 'amount' => '500000.00', 'currency' => 'INR', 'status' => 'confirmed')));
    assert_same('100000.00', $credit['excess_credit']); assert_same('0.00', $credit['total_outstanding']);
    assert_true(is_wp_error(IDLM_Design_Revisions::approved_schedule((object) array('grand_total' => '10.00', 'currency_code' => 'INR', 'document_terms' => null), null)));
});

idlm_test('APPROVED only Customer approved can replace a schedule once design holds the project', static function(): void {
    $source = idlm_handoff_source('includes/class-lead-payments.php');
    assert_contains('IDLM_Design_Handoffs::locks_schedule($lead_id)', $source);
    assert_contains('IDLM_Design_Revisions::is_design_revision($revision_id)', $source);
    assert_false(strpos(idlm_handoff_source('includes/class-design-revisions.php'), 'set_current_revision') !== false);
    assert_contains("check_admin_referer('idlm_design_quote_approve')", idlm_handoff_source('includes/class-design-revisions.php'));
    assert_contains("check_admin_referer('idlm_design_payment_record')", idlm_handoff_source('includes/class-design-revisions.php'));
});

idlm_test('STAGES the six defaults keep their checks, and older saved names read as before', static function(): void {
    $defaults = IDLM_Design_Stages::merge_settings(array());
    assert_same(array('new_project', 'material_selection', 'masking', 'kick_off', 'dqc', 'p2p'), array_keys($defaults));
    assert_same(array('final_render_pdf', 'masking_dwg'), $defaults['masking']['slots']);
    assert_same(array('dqc_dwg', 'mom', 'approval_mail'), $defaults['dqc']['slots']);
    assert_true($defaults['new_project']['requires']['designer']); assert_same('advance', $defaults['material_selection']['requires']['payment']);
    assert_same('second', $defaults['kick_off']['requires']['payment']); assert_true($defaults['kick_off']['requires']['tentative_quote']); assert_true($defaults['dqc']['requires']['tentative_quote']);
    assert_same('final', $defaults['p2p']['requires']['payment']); assert_true($defaults['p2p']['requires']['final_quote']);
    $older = IDLM_Design_Stages::merge_settings(array('masking' => array('label' => ' Masking review ', 'slots' => array('masking_dwg', 'made_up')), 'invented' => array('label' => 'X'), 'dqc' => array('label' => '  ')));
    assert_same('Masking review', $older['masking']['label']); assert_same(array('masking_dwg'), $older['masking']['slots']);
    assert_false(isset($older['invented'])); assert_same('Design Quality Check', $older['dqc']['label']);
});

idlm_test('STAGES a managed list keeps its order, archives instead of deleting, and refuses bad rows', static function(): void {
    $saved = array('version' => 2, 'documents' => array(array('key' => 'doc_site_drawing', 'label' => 'Site drawing', 'type' => 'dwg'), array('key' => 'masking_dwg', 'label' => 'Clash', 'type' => 'pdf'), array('key' => 'doc_bad', 'label' => 'Bad', 'type' => 'exe')), 'stages' => array(
        array('key' => 'new_project', 'label' => 'Intake', 'requires' => array('designer' => true)),
        array('key' => 'stage_site_visit', 'label' => 'Site visit', 'slots' => array('doc_site_drawing', 'made_up'), 'requires' => array('payment' => 'nonsense')),
        array('key' => 'masking', 'label' => 'Masking', 'archived' => true),
        array('key' => "x' OR 1=1", 'label' => 'Injected'), array('key' => 'new_project', 'label' => 'Duplicate'), array('key' => 'blank', 'label' => '  '),
    ));
    $stages = IDLM_Design_Stages::merge_settings($saved);
    assert_same(array('new_project', 'stage_site_visit', 'masking'), array_keys($stages));
    assert_same(array('doc_site_drawing'), $stages['stage_site_visit']['slots']); assert_same('', $stages['stage_site_visit']['requires']['payment']);
    assert_true($stages['masking']['archived']); assert_false($stages['new_project']['requires']['final_quote']);
    assert_same(array('doc_site_drawing'), array_keys(IDLM_Design_Stages::custom_documents_from($saved)), 'Built-in keys and unknown file types are refused.');
    assert_same(array('dwg'), IDLM_Design_Stages::slots_from($saved)['doc_site_drawing']['extensions']);
    $none = IDLM_Design_Stages::merge_settings(array('version' => 2, 'stages' => array(array('key' => 'masking', 'label' => 'Masking', 'archived' => true))));
    assert_same(array('new_project', 'material_selection', 'masking', 'kick_off', 'dqc', 'p2p'), array_keys($none), 'A list with no active stage falls back to the defaults.');
    $GLOBALS['idlm_test_options'][IDLM_Design_Stages::OPTION] = $saved;
    try {
        assert_same('new_project', IDLM_Design_Stages::first()); assert_same(array('new_project', 'stage_site_visit'), array_keys(IDLM_Design_Stages::active()));
        assert_same(null, IDLM_Design_Stages::neighbour('stage_site_visit', 1), 'A removed stage is never entered.');
        assert_same('stage_site_visit', IDLM_Design_Stages::neighbour('masking', -1), 'A project left in a removed stage can still move.');
        assert_same(array('new_project', 'stage_site_visit', 'masking'), array_keys(IDLM_Design_Stages::shown(array('masking'))));
        assert_same(array('new_project', 'stage_site_visit'), array_keys(IDLM_Design_Stages::shown(array())));
        assert_same('masking', IDLM_Design_Stages::stage_of((object) array('stage_key' => 'masking')));
        assert_same('new_project', IDLM_Design_Stages::stage_of((object) array('stage_key' => 'gone')));
    } finally { unset($GLOBALS['idlm_test_options'][IDLM_Design_Stages::OPTION]); }
    assert_same('masking', IDLM_Design_Stages::neighbour('material_selection', 1)); assert_same(null, IDLM_Design_Stages::neighbour('p2p', 1)); assert_same(null, IDLM_Design_Stages::neighbour('new_project', -1));
    assert_same('stage_site_visit_2', IDLM_Design_Stages::new_key('stage_', 'Site visit!', 30, array('stage_site_visit')));
    assert_true(strlen(IDLM_Design_Stages::new_key('stage_', str_repeat('Long name ', 10), 30, array())) <= 30);
    assert_same('doc_item', IDLM_Design_Stages::new_key('doc_', '***', 40, array()));
});

idlm_test('STAGES payment checks look at the advance, the next or the final milestone', static function(): void {
    $rows = array(array('advance' => true), array('advance' => false), array('advance' => false));
    assert_same(0, IDLM_Design_Stages::payment_milestone('advance', $rows)); assert_same(1, IDLM_Design_Stages::payment_milestone('second', $rows)); assert_same(2, IDLM_Design_Stages::payment_milestone('final', $rows));
    assert_same(1, IDLM_Design_Stages::payment_milestone('second', array(array('advance' => true), array('advance' => false))));
    assert_same(null, IDLM_Design_Stages::payment_milestone('final', array())); assert_same(null, IDLM_Design_Stages::payment_milestone('', $rows));
});

idlm_test('STAGES file rules: DWG needs its signature and each slot limits its types', static function(): void {
    assert_true(IDLM_Production_Security::is_dwg_head('AC1032')); assert_true(IDLM_Production_Security::is_dwg_head('AC1015'));
    foreach (array('', 'MZ9000', '%PDF-1', 'AC', 'ACXXXX', '<?php ') as $bad) { assert_false(IDLM_Production_Security::is_dwg_head($bad)); }
    assert_same(array('pdf'), IDLM_Design_Stages::allowed_extensions('final_render_pdf')); assert_same(array('dwg'), IDLM_Design_Stages::allowed_extensions('p2p_dwg'));
    assert_false(in_array('pdf', IDLM_Design_Stages::allowed_extensions('approval_mail'), true)); assert_same(array(), IDLM_Design_Stages::allowed_extensions('made_up'));
    assert_false(in_array('php', IDLM_Production_Security::upload_policy('design')['extensions'], true));
});

idlm_test('BOARD filters accept only known values and read target dates correctly', static function(): void {
    $filters = IDLM_Design_Board::filters(array('q' => ' Asha ', 'stage' => 'masking', 'target' => 'overdue', 'state' => 'hold', 'designer' => '12'));
    assert_same(array('q' => 'Asha', 'stage' => 'masking', 'target' => 'overdue', 'state' => 'hold', 'designer' => 12), $filters);
    assert_same(array('q' => '', 'stage' => '', 'target' => '', 'state' => '', 'designer' => 0), IDLM_Design_Board::filters(array('stage' => "x' OR 1=1", 'target' => 'nope', 'state' => 'nope', 'designer' => '1 OR 1', 'q' => array('a'))));
    $today = '2026-11-06';
    assert_same('overdue', IDLM_Design_Board::target_state('2026-11-05', $today)); assert_same('soon', IDLM_Design_Board::target_state('2026-11-06', $today)); assert_same('soon', IDLM_Design_Board::target_state('2026-11-13', $today));
    assert_same('set', IDLM_Design_Board::target_state('2026-11-14', $today)); assert_same('none', IDLM_Design_Board::target_state(null, $today)); assert_same('set', IDLM_Design_Board::target_state('2020-01-01', $today, true), 'A finished project is not overdue.');
});

idlm_test('BOARD moves use the same server rule, and dragging is not the only way', static function(): void {
    $board = idlm_handoff_source('includes/class-design-board.php');
    assert_contains("check_ajax_referer('idlm_design_stage_action', 'nonce')", $board);
    assert_contains('IDLM_Design_Stages::advance($id, $from)', $board);
    assert_contains('IDLM_Design_Handoffs::scope_sql', $board);
    assert_contains('idlm-design-move', $board);
    $script = idlm_handoff_source('assets/js/design-board.js');
    assert_contains('Open checklist', $script); assert_false((bool) preg_match('/\\b(alert|confirm|prompt)\\(/', str_replace('IDLMDialog.confirm(', '', $script)));
});

idlm_test('TABLE queues are known values, older links still open the right queue, and designers get no intake queues', static function(): void {
    assert_same(array('', 'new', 'unassigned', 'overdue', 'week', 'hold', 'returned', 'complete'), array_keys(IDLM_Design_Projects_Table::queues(true)));
    assert_same(array('', 'overdue', 'week', 'hold', 'complete'), array_keys(IDLM_Design_Projects_Table::queues(false)));
    assert_same('unassigned', IDLM_Design_Projects_Table::queue(array('queue' => 'unassigned'), true));
    assert_same('', IDLM_Design_Projects_Table::queue(array('queue' => 'unassigned'), false), 'A designer cannot open a manager queue.');
    assert_same('new', IDLM_Design_Projects_Table::queue(array('tab' => 'new'), true));
    assert_same('hold', IDLM_Design_Projects_Table::queue(array('tab' => 'list', 'state' => 'hold'), true));
    assert_same('overdue', IDLM_Design_Projects_Table::queue(array('target' => 'overdue'), false));
    assert_same('', IDLM_Design_Projects_Table::queue(array('queue' => "x' OR 1=1"), true));
    assert_same('', IDLM_Design_Projects_Table::queue(array('queue' => array('new')), true));
});

idlm_test('TABLE managers land on the table and designers on their own board', static function(): void {
    $controller = idlm_handoff_source('includes/class-design-handoff-controller.php');
    assert_contains("\$tab = \$manager ? 'projects' : 'board'", $controller);
    assert_contains("array('board' => 'My board', 'projects' => 'My projects')", $controller);
    // A designer's board is their own projects because the scope says so, not a filter they can clear.
    assert_contains('IDLM_Design_Handoffs::scope_sql', idlm_handoff_source('includes/class-design-board.php'));
});

idlm_test('TABLE inline saves keep the form post checks and ask for a reason in a dialog', static function(): void {
    $controller = idlm_handoff_source('includes/class-design-handoff-controller.php');
    $start = strpos($controller, 'public static function ajax_decision'); $ajax = substr($controller, $start, 900);
    assert_contains("wp_ajax_idlm_design_handoff_decide", $controller);
    assert_contains('IDLM_Design_Handoffs::can_manage()', $ajax);
    assert_contains("check_ajax_referer(\$action, '_wpnonce')", $ajax);
    assert_contains('self::run_decision($action, $_POST)', $ajax);
    assert_contains('self::run_decision($action, $_POST)', substr($controller, strpos($controller, 'public static function handle_decision'), 900));
    $table = idlm_handoff_source('includes/class-design-projects-table.php');
    assert_contains('IDLM_Design_Handoffs::can_decide($row)', $table);
    assert_contains('IDLM_Design_Handoffs::scope_sql', $table);
    assert_contains('<input type="hidden" name="stay" value="1">', $table, 'Without scripts each row is still a working form.');
    assert_false(strpos($table, 'wp_nonce_field') !== false, 'One nonce field per row would repeat the same id.');
    $script = idlm_handoff_source('assets/js/design-table.js');
    assert_contains('IDLMDialog.prompt(', $script); assert_contains("'0' === row.dataset.designer", $script);
    assert_false((bool) preg_match('/(?<![.\w])(alert|confirm|prompt)\(/', $script), 'No browser dialogs.');
});

idlm_test('NAV leaving a project or filter: navigation drops every Design team query key', static function(): void {
    foreach (array('tab', 'queue', 'handoff', 'project', 'q', 'stage', 'designer', 'target', 'state', 'design_page', 'design_quote', 'design_approve') as $key) { assert_true(in_array($key, IDLM_Design_Handoff_Controller::QUERY_KEYS, true), $key); }
    assert_contains('IDLM_Design_Handoff_Controller::QUERY_KEYS', idlm_handoff_source('includes/class-admin-shortcodes.php'));
});

idlm_test('PANEL quick panel checks nonce and scope, hides what an ID cannot see, and leaves links working', static function(): void {
    $panel = idlm_handoff_source('includes/class-design-project-panel.php');
    $ajax = substr($panel, strpos($panel, 'public static function ajax_panel'), 900);
    assert_contains("check_ajax_referer(self::NONCE, 'nonce')", $ajax);
    assert_contains('IDLM_Design_Handoffs::can_see($row)', $ajax);
    assert_contains("'This project is not available to you.'), 404", $ajax);
    assert_contains('IDLM_Design_Stages::unmet($items)', $panel, 'The move waits for the checklist, as on the project page.');
    // Same structure as the lead popup, opened and trapped by the shared dialog script.
    foreach (array('class="idlm-drawer idlm-design-project-drawer"', 'data-idlm-dialog data-idlm-backdrop-close="true"', 'idlm-drawer-header', 'idlm-drawer-status-primary', 'idlm-drawer-panel-title', 'data-idlm-dialog-close') as $part) { assert_contains($part, $panel, $part); }
    assert_contains('body.idlm-crm-design #idlm-design-project-panel .idlm-design-panel-grid', idlm_handoff_source('assets/css/crm-design-payments.css'));
    $script = idlm_handoff_source('assets/js/design-panel.js');
    assert_contains('event.metaKey || event.ctrlKey || event.shiftKey || event.altKey', $script, 'A modified click still opens the full page.');
    assert_contains("action: 'idlm_design_stage_move'", $script, 'Moves use the board rule.');
    assert_false((bool) preg_match('/(?<![.\w])(alert|confirm|prompt)\(/', $script));
    foreach (array('includes/class-design-projects-table.php', 'includes/class-design-board.php') as $file) { assert_contains('data-design-panel=', idlm_handoff_source($file), $file); assert_contains('IDLM_Design_Project_Panel::shell()', idlm_handoff_source($file), $file); }
});

idlm_test('DETAIL secondary actions open dialogs with the same forms, and archiving asks first', static function(): void {
    $stage = idlm_handoff_source('includes/class-design-stage-controller.php'); $controller = idlm_handoff_source('includes/class-design-handoff-controller.php');
    foreach (array('Put on hold', 'Move back a stage', 'Reopen project', 'Add another file') as $label) { assert_false(strpos($stage, '<summary>' . $label) !== false, $label); }
    foreach (array('Record a payment', 'Return to sales') as $label) { assert_false(strpos($controller, '<summary>' . $label) !== false, $label); }
    foreach (array('idlm-design-hold-dialog', 'idlm-design-back-dialog', 'idlm-design-reopen-dialog', 'idlm-design-doc-dialog') as $id) { assert_contains("self::dialog_open('" . $id . "'", $stage); }
    foreach (array('idlm-design-pay-dialog', 'idlm-design-return-dialog') as $id) { assert_contains("IDLM_Design_Stage_Controller::dialog_open('" . $id . "'", $controller); }
    assert_contains('self::open_form($action, $row, \'idlm-design-dialog-form\', $multipart)', $stage, 'Each dialog holds the real form, with its nonce.');
    assert_contains('data-idlm-confirm-submit data-idlm-confirm-title="Archive document"', $stage);
    $script = idlm_handoff_source('assets/js/design-detail.js');
    assert_contains('IDLMDialog.open(dialog', $script); assert_false((bool) preg_match('/(?<![.\w])(alert|confirm|prompt)\(/', $script));
});

idlm_test('OVERVIEW medians handle odd, even and empty groups', static function(): void {
    assert_same(null, IDLM_Design_Dashboard::median(array())); assert_same(2.0, IDLM_Design_Dashboard::median(array(3, 1, 2))); assert_same(2.5, IDLM_Design_Dashboard::median(array(4, 1, 2, 3)));
});

idlm_test('RELEASE new design files use no syntax above the PHP 7.4 minimum and events carry no customer data', static function(): void {
    foreach (array('class-design-handoffs.php', 'class-design-handoff-controller.php', 'class-design-revisions.php', 'class-design-stages.php', 'class-design-stage-controller.php', 'class-design-board.php', 'class-design-projects-table.php', 'class-design-project-panel.php', 'class-design-dashboard.php') as $file) {
        assert_false((bool) preg_match('/\\b(str_contains|str_starts_with|str_ends_with)\\(|\\bmatch\\s*\\(|\\?->|\\bfn\\s*\\(|\\breadonly\\s/', idlm_handoff_source('includes/' . $file)), $file);
    }
    $service = idlm_handoff_source('includes/class-design-handoffs.php');
    $start = strpos($service, 'public static function publish_event'); $body = substr($service, $start, 420);
    foreach (array('first_name', 'phone', 'email', 'notes', 'reference') as $field) { assert_false(strpos($body, $field) !== false, $field); }
    assert_contains('idlm_design_project_event', idlm_handoff_source('includes/class-lead-outbox.php'));
});
idlm_test('DESIGN send and accept require a quotation with payment terms, not a Won amount or imported quote',static function():void {
    $root=dirname(__DIR__).'/';$h=(string)file_get_contents($root.'includes/class-design-handoffs.php');$c=(string)file_get_contents($root.'includes/class-design-handoff-controller.php');
    assert_contains("return self::basis(\$lead_id) ? '' :",$h);
    assert_contains("\$missing = self::quotation_missing(\$lead_id);\n        if ('' !== \$missing) { return self::error('quotation', \$missing); }\n        \$advance = self::settle_advance(",$h);
    assert_contains("if ('' !== self::quotation_missing(\$row->lead_id)) { return self::error('quotation',",$h);
    assert_contains("IDLM_Design_Handoffs::quotation_missing(\$lead->id) : '';",$c);
    assert_contains("'quotation' => 'This lead has no quotation with payment terms.",$c);
    assert_contains('Send it back to sales: they attach a quotation with payment terms and send it again.',$c);
});
