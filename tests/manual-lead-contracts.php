<?php
/** Add lead: placeholder emails, the three save choices, duplicates and conversions. Builder: Shashi. */

idlm_test('IDENTITY placeholder emails are recognised and never used to match or report', static function(): void {
    foreach (array('na@gmail.com', 'NA@Gmail.com', 'n.a@yahoo.com', 'noemail@gmail.com', 'none@gmail.com', 'test@gmail.com', 'abc@gmail.com', 'xyz@gmail.com', '123@gmail.com', 'aaa@gmail.com', 'x@gmail.com', 'priya@example.com', 'not-an-email', '9876543210@gmail.com') as $email) {
        assert_true(IDLM_Contact_Identity::is_placeholder_email($email, '+91 98765 43210'), $email . ' is a placeholder.');
    }
    foreach (array('priya.sharma@gmail.com', 'rahul1984@yahoo.in', 'a.kumar@company.co.in') as $email) {
        assert_false(IDLM_Contact_Identity::is_placeholder_email($email, '+91 98765 43210'), $email . ' is real.');
    }
    assert_false(IDLM_Contact_Identity::is_placeholder_email('', ''), 'An empty email is simply absent.');
    assert_same('', IDLM_Contact_Identity::matchable_email('na@gmail.com'));
    assert_same('priya.sharma@gmail.com', IDLM_Contact_Identity::matchable_email(' Priya.Sharma@Gmail.com '));
    assert_same('', IDLM_Contact_Identity::email_for_ads((object) array('email' => 'noemail@gmail.com', 'phone' => '9876543210')));
    assert_same('9876543210', IDLM_Contact_Identity::phone_key('+91 98765-43210'));
    assert_same('', IDLM_Contact_Identity::phone_key('12345'));
});

idlm_test('IDENTITY matching, locks and ad uploads all go through the placeholder rule', static function(): void {
    $lifecycle = file_get_contents(dirname(__DIR__) . '/includes/class-lead-lifecycle-service.php');
    assert_contains("IDLM_Contact_Identity::matchable_email(\$data['email'] ?? '', \$data['phone'] ?? '')", $lifecycle, 'Identity locks');
    assert_same(2, substr_count($lifecycle, 'IDLM_Contact_Identity::email_for_matching('), 'Inbound matching and duplicate detection');
    assert_same(2, substr_count(file_get_contents(dirname(__DIR__) . '/includes/class-capi-transport.php'), 'IDLM_Contact_Identity::email_for_ads($lead)'));
    assert_same(3, substr_count(file_get_contents(dirname(__DIR__) . '/includes/class-audiences.php'), 'IDLM_Contact_Identity::email_for_ads($lead)'));
    assert_false(false !== strpos(file_get_contents(dirname(__DIR__) . '/includes/class-capi-transport.php'), "hash('sha256', strtolower(trim(\$lead->email)))"), 'No raw email is hashed for ads.');
});

idlm_test('MANUAL a sales-side lead starts Qualified only with every detail, or chooses later or pre-sales', static function(): void {
    $base = array('first_name' => 'Priya', 'phone' => '9876543210', 'email' => '', 'source' => 'Offline/Walk-in', 'property_type' => 'Apartment', 'num_rooms' => '3 BHK', 'preferred_studio' => 'North Studio');
    $full = array_merge($base, array('project_name' => 'Lakeside 1203', 'property_location' => '560001', 'budget' => '1500000', 'possession_date' => '2027-03-01', 'project_timeline' => '1-3 months', 'floor_plan_received' => '1'));
    $ok = IDLM_Admin_Ajax::prepare_manual_lead($full, false, 7);
    assert_true(is_array($ok)); assert_same('qualified', $ok['choice']); assert_same('Qualified', $ok['data']['lead_quality']); assert_same(7, $ok['data']['assigned_rep_id']); assert_same('', $ok['data']['email']);
    $missing = IDLM_Admin_Ajax::prepare_manual_lead(array_merge($base, array('budget' => '1500000')), false, 7);
    assert_true(is_wp_error($missing)); assert_contains('project name', $missing->get_error_message()); assert_same('project_name', $missing->get_error_data()['field']);
    assert_same('budget', IDLM_Admin_Ajax::prepare_manual_lead(array_merge($full, array('budget' => '300000')), false, 7)->get_error_data()['field'], 'The ₹4L minimum applies.');
    assert_same('floor_plan_received', IDLM_Admin_Ajax::prepare_manual_lead(array_merge($full, array('floor_plan_received' => '')), false, 7)->get_error_data()['field']);
    assert_same('qualification_handoff_remark', IDLM_Admin_Ajax::prepare_manual_lead(array_merge($full, array('floor_plan_received' => '0')), false, 7)->get_error_data()['field']);
    $no_plan = IDLM_Admin_Ajax::prepare_manual_lead(array_merge($full, array('floor_plan_received' => '0', 'qualification_handoff_remark' => 'Sharing after the site visit')), false, 7);
    assert_contains('Sharing after the site visit', $no_plan['note']);
    $later = IDLM_Admin_Ajax::prepare_manual_lead(array_merge($base, array('qualification_choice' => 'later')), false, 7);
    assert_same('later', $later['choice']); assert_false(isset($later['data']['lead_quality']), 'A later lead has no quality yet.'); assert_same(7, $later['data']['assigned_rep_id']);
    assert_same('qualification_choice', IDLM_Admin_Ajax::prepare_manual_lead(array_merge($base, array('qualification_choice' => 'other')), false, 7)->get_error_data()['field']);
    $intake = IDLM_Admin_Ajax::prepare_manual_lead($base, true, 9);
    assert_same('intake', $intake['choice'], 'Pre-sales add leads for their own intake as before.'); assert_false(isset($intake['data']['lead_quality']));
    assert_same('email', IDLM_Admin_Ajax::prepare_manual_lead(array_merge($base, array('email' => 'na@gmail.com')), true, 9)->get_error_data()['field'], 'A made-up email is refused.');
    assert_same('phone', IDLM_Admin_Ajax::prepare_manual_lead(array_merge($base, array('phone' => '12345')), true, 9)->get_error_data()['field']);
    assert_same('first_name', IDLM_Admin_Ajax::prepare_manual_lead(array_merge($base, array('first_name' => ' ')), true, 9)->get_error_data()['field']);
});

idlm_test('MANUAL conversions for hand-added leads follow the administrator\'s choice', static function(): void {
    $walk_in = (object) array('original_source' => 'Offline/Walk-in', 'source' => 'Offline/Walk-in', 'gclid' => '', 'fbclid' => '');
    $meta = (object) array('original_source' => 'Meta Ads', 'source' => 'Meta Ads');
    $clicked = (object) array('original_source' => 'Referral', 'source' => 'Referral', 'gclid' => 'abc');
    assert_false(IDLM_Contact_Identity::manual_conversion_allowed($walk_in, 'ad_sources'));
    assert_true(IDLM_Contact_Identity::manual_conversion_allowed($meta, 'ad_sources'));
    assert_true(IDLM_Contact_Identity::manual_conversion_allowed($clicked, 'ad_sources'), 'An ad click counts.');
    assert_true(IDLM_Contact_Identity::manual_conversion_allowed($walk_in, 'all'));
    assert_false(IDLM_Contact_Identity::manual_conversion_allowed($meta, 'none'));
    assert_contains('IDLM_Contact_Identity::is_manual_lead($lead->id) && !IDLM_Contact_Identity::manual_conversion_allowed(', file_get_contents(dirname(__DIR__) . '/includes/class-capi.php'), 'Worker and retry share the gate.');
});

idlm_test('MANUAL the form asks for qualification details only outside pre-sales, and duplicates are refused', static function(): void {
    $template = file_get_contents(dirname(__DIR__) . '/templates/admin/leads.php');
    assert_contains('<?php if (!$idlm_is_pre_sales): ?>', $template);
    foreach (array('name="qualification_choice" value="qualified"', 'name="qualification_choice" value="later"', 'name="qualification_choice" value="pre_sales"', 'name="project_name"', 'name="possession_date"', 'name="floor_plan_received"', 'name="qualification_handoff_remark"') as $needle) { assert_contains($needle, $template); }
    assert_false(false !== strpos($template, 'id="idlm-add-lead-quality"'), 'The quality picker is gone.');
    assert_false((bool) preg_match('/name="email"[^>]*required/', $template), 'Email is optional.');
    $ajax = file_get_contents(dirname(__DIR__) . '/includes/class-admin-ajax.php');
    assert_contains('$existing = IDLM_Contact_Identity::existing_leads(', $ajax);
    assert_contains("IDLM_Lead_Access_Policy::can_access((int) \$lead->id)", $ajax, 'Details only for a lead the rep may see.');
});
