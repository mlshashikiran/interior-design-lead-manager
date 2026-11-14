<?php
/** CORE-012 bootstrap and fail-closed read contracts. Builder: Shashi. */
if (!defined('ABSPATH')) { exit; }
idlm_test('retention initialization only schedules and never purges or advances completion markers',static function():void {
    $before=$GLOBALS['wpdb']->queries;update_option('idlm_diagnostic_log_retention_version','old');update_option('idlm_audience_log_payload_version','old');IDLM_Data_Retention::init();assert_same($before,$GLOBALS['wpdb']->queries);assert_same('old',get_option('idlm_diagnostic_log_retention_version'));assert_same('old',get_option('idlm_audience_log_payload_version'));
});
idlm_test('task and WhatsApp direct readers deny revoked CRM access without customer queries',static function():void {
    idlm_test_login(7,array('sales_executive'),array('read'));$queries=$GLOBALS['wpdb']->queries;assert_true(is_wp_error(IDLM_Task_Queue_Service::page()));assert_same(0,IDLM_Admin_Lead_Notifications::get_unread_whatsapp_count());assert_same(array(),IDLM_Admin_Lead_Notifications::get_unread_whatsapp_lead_ids());assert_same($queries,$GLOBALS['wpdb']->queries);
});
idlm_test('WhatsApp EXISTS predicates correlate to the outer lead and sanitize stored read positions',static function():void {
    idlm_test_login(7,array('sales_executive'),array('idlm_access_leads'));update_user_meta(7,'idlm_whatsapp_read_activity_ids',array('12 OR 1=1'=>'34 UNION SELECT secret'));
    $sql=IDLM_Admin_Lead_Notifications::whatsapp_exists_sql('l',true);assert_contains('wa.lead_id = l.id',$sql);assert_contains('WHEN 12 THEN 34',$sql);assert_false(strpos($sql,'UNION')!==false);assert_contains('wa.lead_id = wp_idlm_leads.id',IDLM_Admin_Lead_Notifications::whatsapp_exists_sql('',false));
});
idlm_test('retention health reports a failed batch even when the daily hook remains scheduled',static function():void {
    $report=IDLM_Operational_Health::report(array('retention_scheduled'=>true,'retention_failed'=>true));$retention=array_values(array_filter($report['checks'],static function($check){return $check['key']==='retention';}));assert_same('critical',$retention[0]['status']);
});
idlm_test('retention health reports a missing continuation even when the daily hook remains scheduled',static function():void {
    $report=IDLM_Operational_Health::report(array('retention_scheduled'=>true,'retention_continuation_missing'=>true));
    $retention=array_values(array_filter($report['checks'],static function($check){return $check['key']==='retention';}));
    assert_same('critical',$retention[0]['status']);assert_same('Continuation stalled',$retention[0]['value']);
});

idlm_test('RETENTION background tasks: completed and cancelled Action Scheduler tasks keep 14 days and clean up 100 per run', static function(): void {
    $saved_hooks=$GLOBALS['idlm_test_hooks'] ?? array();$GLOBALS['idlm_test_hooks']=array();IDLM_Data_Retention::init();
    $filters=array();foreach($GLOBALS['idlm_test_hooks'] as $hook){if('filter'===$hook['type']){$filters[$hook['hook']]=$hook['callback'];}}
    assert_same(array('IDLM_Data_Retention','background_task_retention'),$filters['action_scheduler_retention_period']);
    assert_same(array('IDLM_Data_Retention','background_task_cleanup_batch'),$filters['action_scheduler_cleanup_batch_size']);
    // Action Scheduler's 30-day default drops to 14; a shorter period set elsewhere is kept; a broken value falls back to 14.
    assert_same(14*DAY_IN_SECONDS,IDLM_Data_Retention::background_task_retention(30*DAY_IN_SECONDS));
    assert_same(7*DAY_IN_SECONDS,IDLM_Data_Retention::background_task_retention(7*DAY_IN_SECONDS));
    assert_same(14*DAY_IN_SECONDS,IDLM_Data_Retention::background_task_retention(0));
    assert_same(14*DAY_IN_SECONDS,IDLM_Data_Retention::background_task_retention('bad'));
    // The default batch of 20 rises to 100; a larger batch set elsewhere is kept.
    assert_same(100,IDLM_Data_Retention::background_task_cleanup_batch(20));
    assert_same(250,IDLM_Data_Retention::background_task_cleanup_batch(250));
    // Only the retention window changes: the plugin never filters which statuses are purged, so failed tasks stay.
    assert_false(false!==strpos(file_get_contents(dirname(__DIR__).'/includes/class-data-retention.php'),'action_scheduler_default_cleaner_statuses'));
    $GLOBALS['idlm_test_hooks']=$saved_hooks;
});
