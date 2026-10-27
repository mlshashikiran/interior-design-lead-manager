<?php
/** Real Action Scheduler retention continuation acceptance. Builder: Shashi. */
declare(strict_types=1);
require_once __DIR__ . '/bootstrap.php';

$database = null;
$config = array();
register_shutdown_function(static function() use (&$database, &$config): void {
    if ($database instanceof IDLM_Integration_Database) { $database->drop(); }
    if (!empty($config['content_dir'])) { idlm_integration_remove_directory($config['content_dir']); }
});

function idlm_retention_assert($condition, string $message): void {
    if (!$condition) { throw new RuntimeException($message); }
}
function idlm_retention_pending(): array {
    return as_get_scheduled_actions(array('hook'=>IDLM_Data_Retention::CONTINUE_HOOK, 'args'=>array(), 'group'=>'idlm-retention', 'status'=>'pending', 'per_page'=>100), 'ids');
}
function idlm_retention_check(): array {
    $report = IDLM_Operational_Health::report();
    foreach ($report['checks'] as $check) {
        if ('retention' === $check['key']) { return $check; }
    }
    throw new RuntimeException('Missing retention health check.');
}

try {
    $scheduler = getenv('IDLM_TEST_ACTION_SCHEDULER');
    idlm_retention_assert(is_string($scheduler) && is_file($scheduler), 'Set IDLM_TEST_ACTION_SCHEDULER to the verified official action-scheduler.php.');
    $config = idlm_integration_config();
    idlm_integration_export_config($config);
    $database = new IDLM_Integration_Database($config);
    idlm_integration_boot_wordpress($config, true);
    require $scheduler;
    idlm_retention_assert(ActionScheduler::is_initialized(), 'Real Action Scheduler did not initialize.');
    idlm_integration_load_plugin($config);
    IDLM_DB::create_tables();
    IDLM_Routing_State::install();
    IDLM_Data_Retention::init();
    global $wpdb;
    for ($i = 0; $i < 201; $i++) {
        idlm_retention_assert(1 === $wpdb->insert($wpdb->prefix . 'idlm_lead_touchpoints', array('lead_id'=>1, 'platform'=>'website', 'received_at'=>'2000-01-01 00:00:00')), 'Cannot seed expired synthetic touchpoints.');
    }
    $wpdb->insert($wpdb->prefix . 'idlm_lead_touchpoints', array('lead_id'=>1, 'platform'=>'website', 'received_at'=>current_time('mysql')));
    $retained = $wpdb->insert_id;
    $state = IDLM_Data_Retention::prune();
    idlm_retention_assert(100 === $state['processed'], 'Initial batch exceeded or missed its 100-row boundary.');
    $executions = 0;
    do {
        $pending = idlm_retention_pending();
        idlm_retention_assert(1 === count($pending), 'Incomplete batch must have exactly one pending continuation, even after an in-progress callback.');
        ActionScheduler_QueueRunner::instance()->process_action((int) $pending[0], 'IDLM acceptance');
        idlm_retention_assert('complete' === ActionScheduler::store()->get_status($pending[0]), 'Real scheduler callback failed.');
        $state = get_option(IDLM_Data_Retention::STATUS_OPTION);
        idlm_retention_assert(++$executions < 30, 'Retention did not finish within bounded iterations.');
    } while (empty($state['complete']));
    idlm_retention_assert(201 === $state['processed'] && array() === idlm_retention_pending(), 'Completed chain lost rows or queued unnecessary work.');
    idlm_retention_assert((string) $retained === $wpdb->get_var("SELECT id FROM {$wpdb->prefix}idlm_lead_touchpoints"), 'Retention removed an unexpired touchpoint.');
    echo "PASS real AS in-progress continuation, bounded batches, completion and unexpired-row protection\n";

    // A stale incomplete checkpoint is unhealthy even with a daily job queued.
    $state['complete'] = false;
    $state['updated_at'] = gmdate('Y-m-d H:i:s', time() - 600);
    update_option(IDLM_Data_Retention::STATUS_OPTION, $state, false);
    idlm_retention_assert('critical' === idlm_retention_check()['status'], 'Stalled continuation was reported healthy.');
    $id = as_schedule_single_action(time() + 60, IDLM_Data_Retention::CONTINUE_HOOK, array(), 'idlm-retention');
    idlm_retention_assert('healthy' === idlm_retention_check()['status'], 'Pending continuation was incorrectly marked stalled.');
    ActionScheduler::store()->log_execution($id);
    idlm_retention_assert('healthy' === idlm_retention_check()['status'], 'In-progress continuation was incorrectly marked stalled.');
    ActionScheduler::store()->mark_complete($id);
    wp_schedule_single_event(time() + 60, IDLM_Data_Retention::CONTINUE_HOOK);
    idlm_retention_assert('healthy' === idlm_retention_check()['status'], 'WP-Cron fallback was incorrectly marked stalled.');
    wp_clear_scheduled_hook(IDLM_Data_Retention::CONTINUE_HOOK);
    unset($state['updated_at']);
    update_option(IDLM_Data_Retention::STATUS_OPTION, $state, false);
    idlm_retention_assert('critical' === idlm_retention_check()['status'], 'Incomplete checkpoint without a timestamp was reported healthy.');
    $state['updated_at'] = gmdate('Y-m-d H:i:s');
    update_option(IDLM_Data_Retention::STATUS_OPTION, $state, false);
    idlm_retention_assert('healthy' === idlm_retention_check()['status'], 'Fresh checkpoint was incorrectly marked stalled.');
    echo "PASS stalled retention health and pending/running/fallback/fresh checkpoint detection\n";

    $schedule = new ReflectionMethod(IDLM_Data_Retention::class, 'schedule');
    $schedule->setAccessible(true);
    for ($i = 0; $i < 5; $i++) { $schedule->invoke(null); }
    idlm_retention_assert(1 === count(idlm_retention_pending()), 'Repeated scheduling created duplicate pending successors.');
    $other = idlm_integration_other_connection();
    $lock = IDLM_Recovery_Guard::name('retention_schedule');
    $statement = $other->prepare('SELECT GET_LOCK(?, 0)');
    $statement->bind_param('s', $lock);
    $statement->execute();
    idlm_retention_assert('1' === (string) $statement->get_result()->fetch_row()[0], 'Cannot hold independent scheduling fence.');
    try {
        $schedule->invoke(null);
        idlm_retention_assert(1 === count(idlm_retention_pending()), 'Scheduling ignored the independent worker fence.');
        idlm_retention_assert((bool) wp_next_scheduled(IDLM_Data_Retention::CONTINUE_HOOK), 'Contended fence lost its fallback.');
    } finally { $other->close(); }
    as_unschedule_all_actions(IDLM_Data_Retention::CONTINUE_HOOK, array(), 'idlm-retention');
    wp_clear_scheduled_hook(IDLM_Data_Retention::CONTINUE_HOOK);
    echo "PASS pending deduplication and independent-session scheduling fence\n";

    // Reject a real AS insert: incomplete work must remain runnable via WP-Cron.
    add_filter('pre_as_schedule_single_action', static function() { return 0; });
    $schedule->invoke(null);
    idlm_retention_assert((bool) wp_next_scheduled(IDLM_Data_Retention::CONTINUE_HOOK), 'Rejected AS enqueue lost its WP-Cron fallback.');
    echo "PASS rejected AS enqueue retains WP-Cron fallback\n";
    echo "4 real Action Scheduler acceptance checks passed.\n";
} catch (Throwable $error) {
    fwrite(STDERR, 'FAIL ' . $error->getMessage() . "\n");
    exit(1);
}
