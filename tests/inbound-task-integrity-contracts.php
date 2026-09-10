<?php
/**
 * Targeted regression coverage for conservative inbound matching and task guards.
 *
 * Builder: Shashi
 */

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

function idlm_integrity_assert(bool $condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function idlm_integrity_response(callable $callback): IDLM_Test_Response {
    try {
        $callback();
    } catch (IDLM_Test_Response $response) {
        return $response;
    }
    throw new RuntimeException('Expected an AJAX JSON response.');
}

idlm_test_reset_environment();
$GLOBALS['wpdb']->seed('idlm_leads', array(
    array('id' => 7, 'email' => 'exact@example.test', 'phone' => '+91 90000 00007', 'source' => 'Website', 'original_source' => 'Website', 'is_duplicate' => 0),
));
$GLOBALS['wpdb']->seed('idlm_lead_touchpoints', array(
    array('id' => 1, 'lead_id' => 7, 'platform' => 'meta', 'external_id' => 'meta-lead-7'),
));
$external_match = IDLM_DB::upsert_inbound_lead(array(
    'fb_lead_id' => 'meta-lead-7',
    'email' => 'different@example.test',
), 'meta');
idlm_integrity_assert(7 === $external_match['lead_id'] && !$external_match['created'], 'Exact platform external IDs must safely resolve to their touchpoint lead.');

idlm_test_reset_environment();
$GLOBALS['wpdb']->seed('idlm_leads', array(
    array('id' => 1, 'email' => 'one@example.test', 'phone' => '+91 98765 43210', 'is_duplicate' => 0),
    array('id' => 2, 'email' => 'two@example.test', 'phone' => '9876543210', 'is_duplicate' => 0),
));
$ambiguous = IDLM_DB::upsert_inbound_lead(array(
    'first_name' => 'New',
    'email' => 'new@example.test',
    'phone' => '9876543210',
), 'website');
idlm_integrity_assert($ambiguous['created'] && $ambiguous['requires_duplicate_review'], 'Ambiguous normalized identities must create a reviewable inquiry instead of merging.');
$new_lead = $GLOBALS['wpdb']->rows('idlm_leads')[2];
idlm_integrity_assert(empty($new_lead['is_duplicate']) && empty($new_lead['duplicate_of_id']), 'Ambiguous inquiries must not be linked to an arbitrary canonical lead.');
idlm_integrity_assert('duplicate_review' === $GLOBALS['wpdb']->rows('idlm_lead_activity_log')[1]['activity_type'], 'Ambiguous inquiries must retain duplicate-review evidence.');

idlm_test_reset_environment();
idlm_test_login(7, array('sales_executive'), array('idlm_access_leads'));
$GLOBALS['wpdb']->seed('idlm_leads', array(array('id' => 1, 'assigned_rep_id' => 7)));
$_POST = array('lead_id' => 1, 'title' => 'Call client', 'priority' => 'Critical', 'task_type' => 'General');
$invalid_priority = idlm_integrity_response(static fn() => IDLM_Admin_Ajax::ajax_add_lead_task());
idlm_integrity_assert(!$invalid_priority->success && false !== strpos($invalid_priority->data['message'], 'priority'), 'Manual tasks must reject invalid priorities.');

$_POST = array('lead_id' => 1, 'title' => 'Call client', 'priority' => 'High', 'task_type' => 'Qualification', 'due_date' => '2026-08-28T12:00');
$reserved_type = idlm_integrity_response(static fn() => IDLM_Admin_Ajax::ajax_add_lead_task());
idlm_integrity_assert(!$reserved_type->success && false !== strpos($reserved_type->data['message'], 'type'), 'Manual tasks must not create lifecycle-owned task types.');

$_POST = array('lead_id' => 1, 'title' => 'Call client', 'priority' => 'High', 'task_type' => 'Call', 'due_date' => 'not-a-date');
$invalid_date = idlm_integrity_response(static fn() => IDLM_Admin_Ajax::ajax_add_lead_task());
idlm_integrity_assert(!$invalid_date->success && false !== strpos($invalid_date->data['message'], 'due date'), 'Manual tasks must reject malformed due dates.');

$_POST = array('lead_id' => 1, 'title' => 'Confirm site visit', 'priority' => 'High', 'task_type' => 'Site Visit', 'due_date' => '2026-08-30T09:15');
$valid_task = idlm_integrity_response(static fn() => IDLM_Admin_Ajax::ajax_add_lead_task());
idlm_integrity_assert($valid_task->success, 'Valid manual task values must remain accepted.');
$saved_task = $GLOBALS['wpdb']->rows('idlm_tasks')[0];
idlm_integrity_assert('2026-08-30 09:15:00' === $saved_task['due_date'] && 'Site Visit' === $saved_task['task_type'], 'Manual task dates and types must be stored in their normalized contract form.');

$GLOBALS['wpdb']->seed('idlm_tasks', array(array('id' => 1, 'lead_id' => 1, 'task_type' => 'Qualification', 'status' => 'pending')));
$_POST = array('task_id' => 1, 'status' => 'completed');
$toggle = idlm_integrity_response(static fn() => IDLM_Admin_Ajax::ajax_toggle_task_status());
idlm_integrity_assert(!$toggle->success && false !== strpos($toggle->data['message'], 'automatically'), 'Manual toggles must not alter system qualification tasks.');

$_POST = array('task_id' => 1);
$delete = idlm_integrity_response(static fn() => IDLM_Admin_Ajax::ajax_delete_lead_task());
idlm_integrity_assert(!$delete->success && false !== strpos($delete->data['message'], 'automatically'), 'Manual deletion must not remove system qualification tasks.');

echo "Inbound/task integrity contracts passed.\n";
