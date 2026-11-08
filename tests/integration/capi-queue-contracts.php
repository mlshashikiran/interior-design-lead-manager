<?php
/** CAPI review D4 and D12 on native WordPress cron. Synthetic leads; no provider HTTP. Builder: Shashi. */

function idlm_capi_queue_pending(int $lead): array {
    $found = array();
    foreach ((array) _get_cron_array() as $time => $hooks) {
        foreach ((array) ($hooks['idlm_async_capi_upload_event'] ?? array()) as $event) {
            if ((int) ($event['args']['lead_id'] ?? 0) === $lead) { $found[$event['args']['stage']] = (int) $time; }
        }
    }
    ksort($found);
    return $found;
}

idlm_integration_test('CAPI D12: New is queued with no delay and never sends inside the intake request', static function(): void {
    $lead = idlm_lifecycle_fixture();
    $http = 0; $count = static function($pre) use (&$http) { $http++; return new WP_Error('blocked', 'No provider HTTP in tests.'); };
    add_filter('pre_http_request', $count, 1);
    try { IDLM_CAPI::queue_capi_event($lead, 'New', (object) array('stage'=>'')); }
    finally { remove_filter('pre_http_request', $count, 1); }
    idlm_integration_assert_same(0, $http);
    $pending = idlm_capi_queue_pending($lead);
    idlm_integration_assert_same(array('New'), array_keys($pending));
    idlm_integration_assert_true($pending['New'] <= time() + 1, 'New must be due immediately.');
    wp_clear_scheduled_hook('idlm_async_capi_upload_event', array('lead_id'=>$lead, 'stage'=>'New', 'generation'=>IDLM_Recovery_Guard::generation()));
});

idlm_integration_test('CAPI D4: a later stage keeps earlier pending stages; the same stage replaces its own job', static function(): void {
    $lead = idlm_lifecycle_fixture();
    IDLM_CAPI::queue_capi_event($lead, 'Qualified', (object) array('stage'=>'New'));
    IDLM_CAPI::queue_capi_event($lead, 'Quote Shared', (object) array('stage'=>'Qualified'));
    IDLM_CAPI::queue_capi_event($lead, 'Won', (object) array('stage'=>'Quote Shared'));
    idlm_integration_assert_same(array('Qualified', 'Quote Shared', 'Won'), array_keys(idlm_capi_queue_pending($lead)));
    IDLM_CAPI::queue_capi_event($lead, 'Won', (object) array('stage'=>'Lost'));
    $won = 0;
    foreach ((array) _get_cron_array() as $hooks) { foreach ((array) ($hooks['idlm_async_capi_upload_event'] ?? array()) as $event) { if ((int) $event['args']['lead_id'] === $lead && 'Won' === $event['args']['stage']) { $won++; } } }
    idlm_integration_assert_same(1, $won, 'Re-entering a stage keeps one pending job.');
    foreach (array('Qualified', 'Quote Shared', 'Won') as $stage) { wp_clear_scheduled_hook('idlm_async_capi_upload_event', array('lead_id'=>$lead, 'stage'=>$stage, 'generation'=>IDLM_Recovery_Guard::generation())); }
    idlm_integration_assert_same(array(), idlm_capi_queue_pending($lead));
});
