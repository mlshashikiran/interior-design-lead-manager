<?php
/** Exercise optional Action Scheduler delivery without contacting Interakt. Builder: Shashi. */
declare(strict_types=1);
require_once __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__) . '/includes/class-telemetry.php';

function as_schedule_single_action($timestamp, $hook, $args, $group = '', $unique = false) {
    $GLOBALS['idlm_test_as_jobs'][] = compact('timestamp', 'hook', 'args', 'group', 'unique');
    return count($GLOBALS['idlm_test_as_jobs']);
}
function wp_remote_post($url, $args) {
    return array('response' => array('code' => 503), 'body' => '{}');
}
function wp_remote_retrieve_response_code($response) { return $response['response']['code']; }
function wp_remote_retrieve_body($response) { return $response['body']; }

idlm_test_reset_environment();
$GLOBALS['idlm_test_as_jobs'] = array();
$GLOBALS['wpdb']->seed('idlm_leads', array(array('id' => 1, 'first_name' => 'Test', 'phone' => '9999900011', 'is_duplicate' => 0)));
update_option('idlm_interakt_enabled', '1');
update_option('idlm_interakt_api_key', 'synthetic-key');
update_option('idlm_interakt_mappings', array(array('event' => 'follow_up')));
if ('queued' !== IDLM_Interakt_Batch::enqueue(1, 'follow_up', 'as_request_123')) {
    throw new RuntimeException('Action Scheduler enqueue failed.');
}
$job = $GLOBALS['idlm_test_as_jobs'][0];
if ('idlm-interakt' !== $job['group'] || IDLM_Interakt_Batch::HOOK !== $job['hook'] || 1 !== count($job['args'])) {
    throw new RuntimeException('Unexpected scheduled callback contract.');
}
IDLM_Interakt_Batch::process($job['args'][0]);
if (2 !== count($GLOBALS['idlm_test_as_jobs']) || 'retrying' !== get_option($job['args'][0])['status']) {
    throw new RuntimeException('A retry must be scheduled while the initial Action Scheduler callback is running.');
}
if ($GLOBALS['idlm_test_as_jobs'][1]['unique']) {
    throw new RuntimeException('An active unique action would suppress the retry.');
}
echo "Action Scheduler Interakt contracts passed.\n";
