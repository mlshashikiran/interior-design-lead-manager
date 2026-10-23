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
