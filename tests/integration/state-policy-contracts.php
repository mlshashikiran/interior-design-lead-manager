<?php
/** CORE-013 native transition, historical repair and rendered vocabularies. Builder: Shashi. */
if (!defined('ABSPATH') || strpos(DB_NAME, 'idlm_test_') !== 0) { exit(1); }

idlm_integration_test('T03-N-CORE013-stage and supplied-quality validation matrix', static function(): void {
    global $wpdb;
    foreach (array('Visited Studio', 'Quote Shared', 'Negotiation', 'Won', 'Qualified') as $stage) {
        $lead = idlm_lifecycle_fixture(array('project_name' => '', 'budget' => '1 lakh'));
        $result = IDLM_DB::update_lead($lead, array('stage' => $stage, 'quote_shared' => 1, 'quote_value' => 125000, 'deal_value' => 100000));
        idlm_integration_assert_same('Qualified' !== $stage, $result);
        idlm_integration_assert_same('', (string) $wpdb->get_var("SELECT lead_quality FROM {$wpdb->prefix}idlm_leads WHERE id={$lead}"));
    }
    $lead = idlm_lifecycle_fixture(array('stage' => 'Contacted', 'lead_quality' => 'Qualified', 'budget' => '1 lakh'));
    idlm_integration_assert_same(false, IDLM_DB::update_lead($lead, array('stage' => 'Negotiation', 'lead_quality' => 'Qualified')));
    idlm_integration_assert_same(true, IDLM_DB::update_lead($lead, array('stage' => 'Negotiation')));
});

idlm_integration_test('T04-N-CORE013-custom stage and terminal reversal cleanup preserve events', static function(): void {
    global $wpdb;
    $prior = get_option('idlm_custom_stages');
    update_option('idlm_custom_stages', array('Design Review'));
    try {
        $lead = idlm_lifecycle_fixture();
        idlm_integration_assert_same(true, IDLM_DB::update_lead($lead, array('stage' => 'Design Review')));
        idlm_integration_assert_same(false, IDLM_DB::update_lead($lead, array('stage' => 'Unconfigured')));
        idlm_integration_assert_same(true, IDLM_DB::update_lead($lead, array('stage' => 'Won', 'deal_value' => 100000)));
        idlm_integration_assert_same(true, IDLM_DB::update_lead($lead, array('stage' => 'Negotiation')));
        idlm_integration_assert_same(null, $wpdb->get_var("SELECT conversion_time FROM {$wpdb->prefix}idlm_leads WHERE id={$lead}"));
        idlm_integration_assert_same(1, $wpdb->insert($wpdb->prefix . 'idlm_tasks', array('lead_id' => $lead, 'title' => 'Synthetic manual task', 'task_type' => 'Call', 'status' => 'pending')));
        $manual = (int) $wpdb->insert_id;
        idlm_integration_assert_same(true, IDLM_DB::update_lead($lead, array('stage' => 'Lost', 'lost_reason' => 'Other')));
        idlm_integration_assert_same(true, IDLM_DB::update_lead($lead, array('stage' => 'Design Review')));
        idlm_integration_assert_same('', (string) $wpdb->get_var("SELECT lost_reason FROM {$wpdb->prefix}idlm_leads WHERE id={$lead}"));
        idlm_integration_assert_same('cancelled', $wpdb->get_var("SELECT status FROM {$wpdb->prefix}idlm_tasks WHERE id={$manual}"));
        idlm_integration_assert_same(5, (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}idlm_lead_state_events WHERE lead_id={$lead}"));
    } finally { false === $prior ? delete_option('idlm_custom_stages') : update_option('idlm_custom_stages', $prior); }
});

idlm_integration_test('T17-N-CORE013-historical repair has distinct idempotent quality rules', static function(): void {
    global $wpdb;
    $advanced = idlm_lifecycle_fixture(array('stage' => 'Negotiation'));
    $visited = idlm_lifecycle_fixture(array('stage' => 'Visited Studio'));
    $legacy = idlm_lifecycle_fixture(array('stage' => 'New', 'lead_quality' => 'Unqualified'));
    $repair = new ReflectionMethod('IDLM_DB', 'repair_workflow_integrity');
    if (PHP_VERSION_ID < 80100) { $repair->setAccessible(true); }
    idlm_integration_assert_same(true, $repair->invoke(null));
    idlm_integration_assert_same('Qualified', $wpdb->get_var("SELECT lead_quality FROM {$wpdb->prefix}idlm_leads WHERE id={$advanced}"));
    idlm_integration_assert_same('', (string) $wpdb->get_var("SELECT lead_quality FROM {$wpdb->prefix}idlm_leads WHERE id={$visited}"));
    idlm_integration_assert_same('legacy_unqualified', $wpdb->get_var("SELECT disqualification_reason FROM {$wpdb->prefix}idlm_leads WHERE id={$legacy}"));
    $before = idlm_lifecycle_snapshot($legacy);
    idlm_integration_assert_same(true, $repair->invoke(null));
    idlm_integration_assert_same($before, idlm_lifecycle_snapshot($legacy));
});

idlm_integration_test('T04-N-CORE013-drawer options share registry and hide administrator override from pre-sales', static function(): void {
    global $administrator_id;
    if (!defined('DOING_AJAX')) { define('DOING_AJAX', true); }
    $pre = idlm_assignment_user('idlm_pre_sales');
    $mixed = idlm_assignment_user('idlm_pre_sales');
    (new WP_User($mixed))->add_role('administrator');
    $lead = idlm_lifecycle_fixture(array('assigned_rep_id' => $pre));
    $handler = static function() { return static function() { throw new IDLM_Integration_Ajax_Die(); }; };
    add_filter('wp_die_ajax_handler', $handler);
    try {
        foreach (array($pre, $mixed, $administrator_id) as $actor) {
            wp_set_current_user($actor);
            $_POST = array('lead_id' => $lead, 'nonce' => wp_create_nonce('idlm_admin_nonce'));
            $_REQUEST = $_POST;
            ob_start();
            try { IDLM_Admin_Lead_Reads::ajax_get_lead_details(); } catch (IDLM_Integration_Ajax_Die $e) {}
            $response = json_decode((string) ob_get_clean(), true);
            idlm_integration_assert_true(!empty($response['success']));
            $document = new DOMDocument();
            @$document->loadHTML($response['data']['html']);
            $xpath = new DOMXPath($document);
            $manual = array();
            foreach ($xpath->query('//select[@id="idlm-new-task-type"]/option') as $option) { $manual[] = $option->getAttribute('value'); }
            idlm_integration_assert_same(array_values(IDLM_State_Registry::manual_task_types()), $manual);
            $early = array();
            foreach ($xpath->query('//select[contains(@class,"idlm-early-close-reason")]//option[@value!=""]') as $option) { $early[] = $option->getAttribute('value'); }
            $expected = array_keys(IDLM_State_Registry::early_close_reasons());
            // Administrators retain the existing collapsed pre-sales panel and all registry reasons.
            if ($actor === $administrator_id) { idlm_integration_assert_same(1, $xpath->query('//details[contains(@class,"idlm-presales-accordion")]')->length); }
            if ($actor === $pre) { $expected = array_values(array_diff($expected, array('administrator_exception'))); }
            idlm_integration_assert_same($expected, $early);
            idlm_integration_assert_same(1, $xpath->query('//select[@id="idlm-new-task-type"]/option[@value="General"][@selected]')->length);
        }
    } finally { remove_filter('wp_die_ajax_handler', $handler); wp_set_current_user($administrator_id); }
});
