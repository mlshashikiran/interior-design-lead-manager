<?php
/** Per-request maintenance runs on a throttle and still sets everything up. Builder: Shashi. */
if (!defined('ABSPATH') || strpos(DB_NAME,'idlm_test_')!==0) { exit(1); }

/** Run the request-time maintenance paths exactly as a page view, admin, AJAX or cron request does. */
function idlm_maintenance_request(): void {
    IDLM_Integrations::init();
    IDLM_Scheduler::maybe_setup_schedules();
}

/** Remove what upkeep creates: the webhook credential and the outbox schedule. */
function idlm_maintenance_break(): void {
    global $wpdb;
    $wpdb->delete($wpdb->options, array('option_name' => 'idlm_webhook_api_key'));
    wp_cache_delete('idlm_webhook_api_key', 'options'); wp_cache_delete('alloptions', 'options'); wp_cache_delete('notoptions', 'options');
    wp_clear_scheduled_hook(IDLM_Lead_Outbox::HOOK);
    if (function_exists('as_unschedule_all_actions')) { as_unschedule_all_actions(IDLM_Lead_Outbox::HOOK); }
}

function idlm_maintenance_state(): array {
    $scheduled = function_exists('as_next_scheduled_action') ? (bool) as_next_scheduled_action(IDLM_Lead_Outbox::HOOK) : (bool) wp_next_scheduled(IDLM_Lead_Outbox::HOOK);
    return array('credential' => '' !== IDLM_Secret_Store::stored('idlm_webhook_api_key') && null !== IDLM_Secret_Store::stored('idlm_webhook_api_key'), 'scheduled' => $scheduled);
}

/** Move every throttled task's next run, keeping the recorded version unless one is given. */
function idlm_maintenance_shift(int $next_run, ?string $version = null): void {
    $checks = (array) get_option(IDLM_Scheduler::MAINTENANCE_OPTION, array());
    foreach ($checks as $task => $value) { $checks[$task] = ($version ?? strtok((string) $value, '|')) . '|' . $next_run; }
    update_option(IDLM_Scheduler::MAINTENANCE_OPTION, $checks, true);
}

idlm_integration_test('STREAMLINE-N-D3-maintenance-sets-up-a-fresh-install-throttles-and-self-repairs', static function(): void {
    global $wpdb;
    $saved_key = $wpdb->get_row($wpdb->prepare("SELECT option_value, autoload FROM {$wpdb->options} WHERE option_name = %s", 'idlm_webhook_api_key'), ARRAY_A);
    $saved_checks = get_option(IDLM_Scheduler::MAINTENANCE_OPTION, null);
    try {
        // Fresh install: nothing recorded yet, so the first request sets everything up.
        idlm_maintenance_break();
        delete_option(IDLM_Scheduler::MAINTENANCE_OPTION);
        idlm_maintenance_request();
        idlm_integration_assert_same(array('credential' => true, 'scheduled' => true), idlm_maintenance_state(), 'A fresh install was not set up on its first request.');
        $checks = get_option(IDLM_Scheduler::MAINTENANCE_OPTION);
        idlm_integration_assert_same(array('integration_upkeep', 'schedules'), array_values(array_intersect(array('integration_upkeep', 'schedules'), array_keys((array) $checks))), 'Both tasks record their next run.');

        // Inside the window a request does no maintenance work and no database reads for it.
        idlm_maintenance_break();
        $before = $wpdb->num_queries;
        idlm_maintenance_request();
        idlm_integration_assert_same(0, $wpdb->num_queries - $before, 'A throttled request still queried the database.');
        idlm_integration_assert_same(array('credential' => false, 'scheduled' => false), idlm_maintenance_state(), 'Maintenance ran inside the throttle window.');
        // A credential is still repaired the moment something uses it.
        // Each use (webhook, MCP and feed checks) repairs its credential itself.
        IDLM_Integrations::maybe_migrate_api_credentials();
        idlm_integration_assert_true(idlm_maintenance_state()['credential'], 'A use did not repair the credential.');
        idlm_maintenance_break();

        // After the window the next request repairs both.
        idlm_maintenance_shift(time() - 1);
        idlm_maintenance_request();
        idlm_integration_assert_same(array('credential' => true, 'scheduled' => true), idlm_maintenance_state(), 'The first request after the window did not repair.');

        // An update runs it on the first request even inside the window.
        idlm_maintenance_break();
        idlm_maintenance_shift(time() + 3600, '0.0.1');
        idlm_maintenance_request();
        idlm_integration_assert_same(array('credential' => true, 'scheduled' => true), idlm_maintenance_state(), 'The first request after an update did not repair.');
    } finally {
        if (null !== $saved_key) { $wpdb->replace($wpdb->options, array('option_name' => 'idlm_webhook_api_key') + $saved_key); }
        wp_cache_delete('idlm_webhook_api_key', 'options'); wp_cache_delete('alloptions', 'options');
        null === $saved_checks ? delete_option(IDLM_Scheduler::MAINTENANCE_OPTION) : update_option(IDLM_Scheduler::MAINTENANCE_OPTION, $saved_checks, true);
        IDLM_Scheduler::setup_schedules();
    }
});
