<?php
/** Returning customers: one enquiry across channels, a re-enquiry after the quiet period. Builder: Shashi. */

function idlm_reenquiry_age(string $lead_email, string $time): int {
    // Move the lead and its submissions back in time so the next submission arrives later.
    $db = $GLOBALS['wpdb'];
    $lead_id = 0;
    foreach ($db->tables['wp_idlm_leads'] as $index => $row) {
        if ($lead_email === $row['email']) { $lead_id = (int) $row['id']; $db->tables['wp_idlm_leads'][$index]['created_at'] = $time; }
    }
    foreach ($db->tables['wp_idlm_lead_touchpoints'] as $index => $row) {
        if ($lead_id === (int) $row['lead_id']) { $db->tables['wp_idlm_lead_touchpoints'][$index]['received_at'] = $time; }
    }
    return $lead_id;
}

function idlm_reenquiry_tasks(int $lead_id): array {
    return array_values(array_filter($GLOBALS['wpdb']->rows('idlm_tasks'), static fn($task) => $lead_id === (int) $task['lead_id'] && IDLM_Lead_Lifecycle_Service::REENQUIRY_TASK_TITLE === $task['title']));
}

idlm_test('REENQUIRY counts form channels after the quiet period and closed leads at once', static function(): void {
    $open = (object) array('stage' => 'Contacted', 'lead_quality' => '', 'created_at' => '2026-08-01 10:00:00');
    $now = '2026-08-28 12:00:00';
    assert_false(IDLM_Lead_Lifecycle_Service::is_reenquiry($open, 'website', '2026-08-27 12:00:00', $now, 3), 'Meta then website the next day is one enquiry.');
    assert_false(IDLM_Lead_Lifecycle_Service::is_reenquiry($open, 'website', '2026-08-25 12:00:01', $now, 3), 'Just under three days is still one enquiry.');
    assert_true(IDLM_Lead_Lifecycle_Service::is_reenquiry($open, 'website', '2026-08-25 12:00:00', $now, 3), 'Three days later is a re-enquiry.');
    assert_true(IDLM_Lead_Lifecycle_Service::is_reenquiry($open, 'meta', null, $now, 3), 'With no recorded submission the lead creation time is the last one.');
    foreach (array('csv', 'manual', 'interakt') as $platform) {
        assert_false(IDLM_Lead_Lifecycle_Service::is_reenquiry($open, $platform, '2026-01-01 00:00:00', $now, 3), $platform . ' is not a customer form.');
    }
    foreach (array(array('Lost', ''), array('Won', ''), array('Contacted', 'Unqualified')) as $closed) {
        $lead = (object) array('stage' => $closed[0], 'lead_quality' => $closed[1], 'created_at' => '2026-08-28 11:00:00');
        assert_true(IDLM_Lead_Lifecycle_Service::is_reenquiry($lead, 'google', '2026-08-28 11:59:00', $now, 3), $closed[0] . '/' . $closed[1] . ' counts at once.');
    }
    update_option('idlm_reenquiry_quiet_days', '0');
    assert_same(1, IDLM_Lead_Lifecycle_Service::reenquiry_quiet_days());
    update_option('idlm_reenquiry_quiet_days', '500');
    assert_same(90, IDLM_Lead_Lifecycle_Service::reenquiry_quiet_days());
    delete_option('idlm_reenquiry_quiet_days');
    assert_same(3, IDLM_Lead_Lifecycle_Service::reenquiry_quiet_days());
});

idlm_test('REENQUIRY a Meta lead filling the website form soon after stays quiet', static function(): void {
    $first = IDLM_DB::upsert_inbound_lead(array('email' => 'returning@example.test', 'source' => 'Meta Ads'), 'meta');
    assert_true($first['created']);
    $second = IDLM_DB::upsert_inbound_lead(array('email' => 'returning@example.test', 'source' => 'Website'), 'website');
    assert_true($second['matched']);
    assert_false($second['reenquiry']);
    $lead = $GLOBALS['wpdb']->rows('idlm_leads')[0];
    assert_true(empty($lead['reenquiry_at']));
    assert_same(array(), idlm_reenquiry_tasks((int) $lead['id']));
});

idlm_test('REENQUIRY a later submission flags the lead, logs it and gives the owner one call-back task', static function(): void {
    $GLOBALS['idlm_test_users'][41] = (object) array('ID' => 41, 'display_name' => 'Asha', 'roles' => array('idlm_sales_rep'));
    IDLM_DB::upsert_inbound_lead(array('email' => 'returning@example.test', 'source' => 'Meta Ads'), 'meta');
    $lead_id = idlm_reenquiry_age('returning@example.test', '2026-08-20 09:00:00');
    foreach ($GLOBALS['wpdb']->tables['wp_idlm_leads'] as $index => $row) { $GLOBALS['wpdb']->tables['wp_idlm_leads'][$index]['assigned_rep_id'] = 41; }

    $result = IDLM_DB::upsert_inbound_lead(array('email' => 'returning@example.test', 'source' => 'Website'), 'website');
    assert_true($result['reenquiry']);
    $lead = $GLOBALS['wpdb']->rows('idlm_leads')[0];
    assert_same('2026-08-28 12:00:00', $lead['reenquiry_at']);
    assert_same(1, (int) $lead['reenquiry_count']);
    $tasks = idlm_reenquiry_tasks($lead_id);
    assert_same(1, count($tasks));
    assert_same(array(41, 'pending', 'High', 'Call'), array((int) $tasks[0]['assigned_rep_id'], $tasks[0]['status'], $tasks[0]['priority'], $tasks[0]['task_type']));
    $logged = array_filter($GLOBALS['wpdb']->rows('idlm_lead_activity_log'), static fn($row) => 'reenquiry' === $row['activity_type']);
    assert_same('Customer enquired again via Website.', array_values($logged)[0]['details']);

    // A further return keeps one open task and counts again.
    idlm_reenquiry_age('returning@example.test', '2026-08-20 09:00:00');
    assert_true(IDLM_DB::upsert_inbound_lead(array('email' => 'returning@example.test', 'source' => 'Google Ads'), 'google')['reenquiry']);
    assert_same(2, (int) $GLOBALS['wpdb']->rows('idlm_leads')[0]['reenquiry_count']);
    assert_same(1, count(idlm_reenquiry_tasks($lead_id)));
});

idlm_test('REENQUIRY a Lost lead that submits again is flagged at once and the note says it was Lost', static function(): void {
    IDLM_DB::upsert_inbound_lead(array('email' => 'lost@example.test', 'source' => 'Website'), 'website');
    foreach ($GLOBALS['wpdb']->tables['wp_idlm_leads'] as $index => $row) { $GLOBALS['wpdb']->tables['wp_idlm_leads'][$index]['stage'] = 'Lost'; }
    $result = IDLM_DB::upsert_inbound_lead(array('email' => 'lost@example.test', 'source' => 'Meta Ads'), 'meta');
    assert_true($result['reenquiry']);
    $logged = array_values(array_filter($GLOBALS['wpdb']->rows('idlm_lead_activity_log'), static fn($row) => 'reenquiry' === $row['activity_type']));
    assert_same('Customer enquired again via Meta Ads. The lead was Lost.', $logged[0]['details']);
    assert_same('Lost', $GLOBALS['wpdb']->rows('idlm_leads')[0]['stage'], 'The stage is the rep\'s decision.');
    assert_same(0, (int) idlm_reenquiry_tasks((int) $result['lead_id'])[0]['assigned_rep_id'], 'No owner: the task waits unassigned.');
});

idlm_test('REENQUIRY CSV imports never flag a lead', static function(): void {
    IDLM_DB::upsert_inbound_lead(array('email' => 'csv@example.test', 'source' => 'Website'), 'website');
    idlm_reenquiry_age('csv@example.test', '2026-01-01 09:00:00');
    assert_false(IDLM_DB::upsert_inbound_lead(array('email' => 'csv@example.test', 'source' => 'CSV Import'), 'csv')['reenquiry']);
});

idlm_test('REENQUIRY leads sit at the top until a call, a stage move or the task clears them', static function(): void {
    $root = dirname(__DIR__) . '/';
    $reads = file_get_contents($root . 'includes/class-admin-collection-reads.php');
    assert_contains("'COALESCE(l.reenquiry_at, l.assigned_at, l.created_at) DESC, l.id DESC'", $reads, 'Default order');
    assert_contains("'sort' => 'reenquiry_or_assigned_or_created_desc_id_desc'", $reads, 'Old cursors are refused, not misread');
    assert_contains("CASE WHEN l.reenquiry_at IS NOT NULL THEN 0", $reads, 'Pre-sales queue');
    assert_contains("(COALESCE(l.stage, '') NOT IN ('Won', 'Lost') OR l.reenquiry_at IS NOT NULL)", $reads, 'Active view keeps closed returns');
    assert_contains("'reenquired' === \$post_followup_filter", str_replace("\$post_followup_filter === 'reenquired'", "'reenquired' === \$post_followup_filter", $reads), 'Filter');
    assert_contains('self::reenquiry_badge($l)', $reads);
    assert_same(2, substr_count($reads, 'self::reenquiry_badge($l)'), 'Standard and pre-sales rows');
    assert_contains('value="reenquired"', file_get_contents($root . 'templates/admin/leads.php'));
    assert_contains("IDLM_Lead_Lifecycle_Service::clear_reenquiry(\$task->lead_id)", file_get_contents($root . 'includes/class-admin-task-controller.php'));
    $lifecycle = file_get_contents($root . 'includes/class-lead-lifecycle-service.php');
    assert_contains("if ((\$call_changed || \$stage_changed) && !empty(\$old_lead->reenquiry_at)) {", $lifecycle);
    assert_contains('name="reenquiry_quiet_days"', file_get_contents($root . 'templates/admin/settings/routing.php'));

    $badge = IDLM_Admin_Collection_Reads::reenquiry_badge((object) array('reenquiry_at' => '2026-08-28 10:00:00'));
    assert_contains('class="idlm-row-handoff-badge idlm-row-reenquiry-badge"', $badge);
    assert_contains('Re-enquired', $badge);
    assert_same('', IDLM_Admin_Collection_Reads::reenquiry_badge((object) array('reenquiry_at' => null)));
});
