<?php
/** Add lead on the disposable schema: duplicates, the three save choices, return from pre-sales, emails. Builder: Shashi. */
if (!defined('ABSPATH') || strpos(DB_NAME, 'idlm_test_') !== 0) { exit(1); }

function idlm_manual_phone(): string { return '9' . str_pad((string) wp_rand(0, 999999999), 9, '0', STR_PAD_LEFT); }
function idlm_manual_full($extra = array()): array {
    return array_merge(array('first_name' => 'Synthetic Manual', 'phone' => idlm_manual_phone(), 'email' => '', 'source' => 'Offline/Walk-in', 'property_type' => 'Apartment', 'num_rooms' => '3 BHK', 'preferred_studio' => 'North Studio',
        'project_name' => 'Synthetic Residence', 'property_location' => '560001', 'budget' => '1500000', 'possession_date' => '2027-03-01', 'project_timeline' => '1-3 months', 'floor_plan_received' => '1'), $extra);
}
/** The Add lead handler's steps, as the signed-in user. Returns the lead row, or the error message. */
function idlm_manual_add($fields) {
    global $wpdb;
    $prepared = IDLM_Admin_Ajax::prepare_manual_lead($fields, IDLM_Lead_Access_Policy::is_pre_sales(), get_current_user_id());
    if (is_wp_error($prepared)) { return $prepared->get_error_message(); }
    if (IDLM_Contact_Identity::existing_leads($prepared['data']['phone'], $prepared['data']['email'])) { return 'existing'; }
    $payload = $prepared['data']; if ('pre_sales' === $prepared['choice']) { $payload['return_to_rep_id'] = get_current_user_id(); }
    $result = IDLM_DB::upsert_inbound_lead($prepared['data'], 'manual', $payload, array('capi' => true, 'route_pre_sales' => 'pre_sales' === $prepared['choice'], 'details_task' => 'later' === $prepared['choice'], 'manual_note' => $prepared['note']));
    if (!$result || empty($result['created'])) { return IDLM_DB::get_last_validation_error() ?: 'not created'; }
    return $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}idlm_leads WHERE id = %d", $result['lead_id']));
}

idlm_integration_test('MANUAL a rep\'s lead starts Qualified with every detail, stays theirs, and needs no pre-sales', static function(): void {
    global $wpdb; $rep = idlm_assignment_user(); $admin = get_current_user_id(); wp_set_current_user($rep);
    try {
        $lead = idlm_manual_add(idlm_manual_full(array('floor_plan_received' => '0', 'qualification_handoff_remark' => 'Plan after the site visit')));
        idlm_integration_assert_true(is_object($lead), is_string($lead) ? $lead : 'Not created');
        idlm_integration_assert_same(array('Qualified', 'Qualified', (string) $rep, 'manually_assigned', 'direct_sales_qualified', ''), array($lead->lead_quality, $lead->stage, (string) $lead->assigned_rep_id, $lead->handoff_status, $lead->handoff_method, (string) $lead->email));
        idlm_integration_assert_same(0, (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->prefix}idlm_tasks WHERE lead_id = %d AND task_type = 'Qualification'", $lead->id)), 'No pre-sales plan.');
        idlm_integration_assert_true((bool) $wpdb->get_var($wpdb->prepare("SELECT id FROM {$wpdb->prefix}idlm_lead_activity_log WHERE lead_id = %d AND details LIKE %s", $lead->id, '%Plan after the site visit%')), 'The no-floor-plan remark is kept.');
        idlm_integration_assert_true(false !== strpos((string) idlm_manual_add(idlm_manual_full(array('project_name' => ''))), 'project name'));
    } finally { wp_set_current_user($admin); }
});

idlm_integration_test('MANUAL a known phone or real email is refused and the existing lead is untouched; placeholder emails never match', static function(): void {
    global $wpdb; $rep = idlm_assignment_user(); $admin = get_current_user_id(); wp_set_current_user($rep);
    try {
        $first = idlm_manual_add(idlm_manual_full(array('email' => 'priya.synthetic@gmail.com', 'qualification_choice' => 'later')));
        idlm_integration_assert_true(is_object($first));
        $before = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}idlm_leads WHERE id = %d", $first->id), ARRAY_A); $count = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}idlm_leads");
        idlm_integration_assert_same('existing', idlm_manual_add(idlm_manual_full(array('phone' => '+91 ' . $first->phone, 'qualification_choice' => 'later'))), 'Same phone in another format.');
        idlm_integration_assert_same('existing', idlm_manual_add(idlm_manual_full(array('email' => 'Priya.Synthetic@gmail.com', 'qualification_choice' => 'later'))), 'Same real email.');
        idlm_integration_assert_same($before, $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}idlm_leads WHERE id = %d", $first->id), ARRAY_A)); idlm_integration_assert_same($count, (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}idlm_leads"));
        // Website leads with the same made-up email and different phones stay separate customers.
        $a = IDLM_DB::upsert_inbound_lead(array('first_name' => 'Synthetic A', 'phone' => idlm_manual_phone(), 'email' => 'na@gmail.com', 'source' => 'Website'), 'website');
        $b = IDLM_DB::upsert_inbound_lead(array('first_name' => 'Synthetic B', 'phone' => idlm_manual_phone(), 'email' => 'na@gmail.com', 'source' => 'Website'), 'website');
        idlm_integration_assert_true(!empty($a['created']) && !empty($b['created']) && (int) $a['lead_id'] !== (int) $b['lead_id'], 'A placeholder email merged two customers.');
        idlm_integration_assert_same(0, (int) $wpdb->get_var($wpdb->prepare("SELECT is_duplicate FROM {$wpdb->prefix}idlm_leads WHERE id = %d", $b['lead_id'])));
        idlm_integration_assert_same(array(), IDLM_Contact_Identity::existing_leads(idlm_manual_phone(), 'na@gmail.com'));
    } finally { wp_set_current_user($admin); }
});

idlm_integration_test('MANUAL "add details later" keeps the lead with the rep and sets a two-day task', static function(): void {
    global $wpdb; $rep = idlm_assignment_user(); $admin = get_current_user_id(); wp_set_current_user($rep);
    try {
        $lead = idlm_manual_add(array('first_name' => 'Synthetic Walk-in', 'phone' => idlm_manual_phone(), 'qualification_choice' => 'later', 'source' => 'Offline/Walk-in'));
        idlm_integration_assert_true(is_object($lead), is_string($lead) ? $lead : '');
        idlm_integration_assert_same(array('New', '', (string) $rep), array($lead->stage, (string) $lead->lead_quality, (string) $lead->assigned_rep_id));
        $task = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}idlm_tasks WHERE lead_id = %d", $lead->id));
        idlm_integration_assert_same(array('Complete qualification details', (string) $rep, 'pending'), array($task->title, (string) $task->assigned_rep_id, $task->status));
        idlm_integration_assert_true(strtotime($task->due_date) > time() + DAY_IN_SECONDS, 'Due in about two days.');
    } finally { wp_set_current_user($admin); }
});

idlm_integration_test('MANUAL "ask pre-sales to call" goes to pre-sales and returns to the same rep once qualified', static function(): void {
    global $wpdb; $rep = idlm_assignment_user(); $pre = idlm_assignment_user('idlm_pre_sales'); $admin = get_current_user_id();
    idlm_capacity_settings(array('idlm_max_active_leads_per_rep' => 50, 'idlm_assignment_mode' => 'pre_sales', 'idlm_active_pre_sales_reps' => array($pre)));
    wp_set_current_user($rep);
    try {
        $lead = idlm_manual_add(array('first_name' => 'Synthetic Referral', 'phone' => idlm_manual_phone(), 'qualification_choice' => 'pre_sales', 'source' => 'Referral'));
        idlm_integration_assert_true(is_object($lead), is_string($lead) ? $lead : '');
        idlm_integration_assert_same(array((string) $pre, (string) $pre), array((string) $lead->assigned_rep_id, (string) $lead->intake_owner_id), 'Pre-sales owns the intake.');
        idlm_integration_assert_same($rep, IDLM_Contact_Identity::requested_rep($lead->id));
        wp_set_current_user($pre);
        $updated = IDLM_DB::update_lead($lead->id, array('project_name' => 'Synthetic Residence', 'property_location' => '560001', 'budget' => '1500000', 'possession_date' => '2027-03-01', 'property_type' => 'Apartment', 'project_timeline' => '1-3 months', 'floor_plan_received' => 1));
        idlm_integration_assert_true(false !== $updated, IDLM_DB::get_last_validation_error());
        idlm_integration_assert_true(false !== IDLM_DB::update_lead($lead->id, array('lead_quality' => 'Qualified')), IDLM_DB::get_last_validation_error());
        $after = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}idlm_leads WHERE id = %d", $lead->id));
        idlm_integration_assert_same(array('Qualified', (string) $rep, 'manually_assigned', 'returned_to_requesting_rep', (string) $pre), array($after->lead_quality, (string) $after->assigned_rep_id, $after->handoff_status, $after->handoff_method, (string) $after->intake_owner_id));
        idlm_integration_assert_true((bool) $wpdb->get_var($wpdb->prepare("SELECT id FROM {$wpdb->prefix}idlm_tasks WHERE lead_id = %d AND assigned_rep_id = %d AND status = 'pending'", $lead->id, $rep)), 'The rep gets the qualified-lead task.');
        idlm_integration_assert_same(0, (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->prefix}idlm_tasks WHERE lead_id = %d AND assigned_rep_id = %d AND status = 'pending'", $lead->id, $pre)), 'Pre-sales work is closed.');
        // Without anyone in pre-sales, the choice is refused before anything is saved.
        update_option('idlm_active_pre_sales_reps', array()); IDLM_Settings::sync_routing_from_legacy(); wp_set_current_user($rep);
        idlm_integration_assert_true(false !== strpos((string) idlm_manual_add(array('first_name' => 'Synthetic', 'phone' => idlm_manual_phone(), 'qualification_choice' => 'pre_sales')), 'No one in pre-sales'));
    } finally { wp_set_current_user($admin); update_option('idlm_assignment_mode', 'manual'); IDLM_Settings::sync_routing_from_legacy(); }
});

idlm_integration_test('MANUAL an administrator reviews made-up and shared emails and clears them one lead at a time', static function(): void {
    global $wpdb;
    $made_up = IDLM_DB::upsert_inbound_lead(array('first_name' => 'Synthetic Fake', 'phone' => idlm_manual_phone(), 'email' => 'noemail@gmail.com', 'source' => 'Website'), 'website');
    $shared = 'shared.synthetic@gmail.com';
    $one = IDLM_DB::upsert_inbound_lead(array('first_name' => 'Synthetic One', 'phone' => idlm_manual_phone(), 'email' => $shared, 'source' => 'Website'), 'website');
    $wpdb->insert($wpdb->prefix . 'idlm_leads', array('first_name' => 'Synthetic Two', 'phone' => idlm_manual_phone(), 'email' => $shared, 'stage' => 'New', 'created_at' => current_time('mysql'), 'updated_at' => current_time('mysql'))); $two = (int) $wpdb->insert_id;
    $reasons = array(); foreach (IDLM_Contact_Identity::review_rows(5000) as $row) { $reasons[(int) $row->id] = $row->reason; }
    idlm_integration_assert_same('Placeholder', $reasons[(int) $made_up['lead_id']] ?? ''); idlm_integration_assert_same('Shared by different phone numbers', $reasons[$two] ?? '');
    idlm_integration_assert_true(IDLM_Contact_Identity::is_shared_email($shared)); idlm_integration_assert_same(array(), IDLM_Contact_Identity::existing_leads(idlm_manual_phone(), $shared), 'A shared email matches no one.');
    $rep = idlm_assignment_user(); $admin = idlm_assignment_user('administrator'); $previous = get_current_user_id(); wp_set_current_user($rep);
    idlm_integration_assert_true(is_wp_error(IDLM_Contact_Identity::clear_emails(array($two)))); wp_set_current_user($admin);
    idlm_integration_assert_same(2, IDLM_Contact_Identity::clear_emails(array((int) $made_up['lead_id'], $two, 999999999)));
    idlm_integration_assert_same('', (string) $wpdb->get_var($wpdb->prepare("SELECT email FROM {$wpdb->prefix}idlm_leads WHERE id = %d", $two)));
    idlm_integration_assert_same($shared, (string) $wpdb->get_var($wpdb->prepare("SELECT email FROM {$wpdb->prefix}idlm_leads WHERE id = %d", $one['lead_id'])), 'Only chosen leads change.');
    idlm_integration_assert_true((bool) $wpdb->get_var($wpdb->prepare("SELECT id FROM {$wpdb->prefix}idlm_lead_activity_log WHERE lead_id = %d AND details LIKE %s", $two, '%cleared%')));
    wp_set_current_user($previous);
});

idlm_integration_test('MANUAL hand-added leads report conversions only as the administrator chose', static function(): void {
    $rep = idlm_assignment_user(); $admin = get_current_user_id(); wp_set_current_user($rep);
    try {
        $walk_in = idlm_manual_add(idlm_manual_full()); $ad = idlm_manual_add(idlm_manual_full(array('source' => 'Meta Ads')));
        idlm_integration_assert_true(is_object($walk_in) && is_object($ad));
        idlm_integration_assert_true(IDLM_Contact_Identity::is_manual_lead($walk_in->id));
        $matches = new ReflectionMethod('IDLM_CAPI', 'lead_matches_event'); $matches->setAccessible(true);
        idlm_integration_assert_same(array(false, true), array($matches->invoke(null, $walk_in, 'Qualified'), $matches->invoke(null, $ad, 'Qualified')));
        update_option(IDLM_Contact_Identity::MANUAL_CONVERSIONS_OPTION, 'all'); idlm_integration_assert_true($matches->invoke(null, $walk_in, 'Qualified'));
        update_option(IDLM_Contact_Identity::MANUAL_CONVERSIONS_OPTION, 'none'); idlm_integration_assert_true(!$matches->invoke(null, $ad, 'Qualified'));
    } finally { delete_option(IDLM_Contact_Identity::MANUAL_CONVERSIONS_OPTION); wp_set_current_user($admin); }
});
