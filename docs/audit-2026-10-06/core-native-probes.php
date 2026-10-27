<?php
/** Audit-only native probes using a fresh idlm_test_* database. Author: Shashi. */
function wp_mail($to, $subject, $message, $headers = '', $attachments = array()) { return true; }
define('DOING_AJAX', true);
require dirname(__DIR__, 2) . '/tests/integration/bootstrap.php';
final class Core_Audit_Ajax_Die extends RuntimeException {}
$config=idlm_integration_config();
$database=new IDLM_Integration_Database($config);
$results=array();
function native_result($name,$expected,$actual) { global $results; $results[]=array('scenario'=>$name,'status'=>$expected===$actual?'PASS':'FAIL','expected'=>$expected,'actual'=>$actual); }
try {
    $admin=idlm_integration_boot_wordpress($config,true);
    add_filter('pre_http_request',static function(){return new WP_Error('audit_network_disabled','External requests disabled for audit');});
    idlm_integration_load_plugin($config);
    wp_set_current_user($admin);
    idlm_activate_plugin();
    idlm_boot_plugin();
    $old=wp_insert_user(array('user_login'=>'audit_old','user_pass'=>'synthetic-password','role'=>'author'));
    $new=wp_insert_user(array('user_login'=>'audit_new','user_pass'=>'synthetic-password','role'=>'author'));
    update_option('idlm_role_lead_scopes',array('author'=>'assigned'));
    $lead=IDLM_DB::insert_lead(array('first_name'=>'Synthetic owner change','email'=>'pii-audit@example.test','phone'=>'9999990001','assigned_rep_id'=>$old));
    $wpdb->insert($wpdb->prefix.'idlm_tasks',array('lead_id'=>$lead,'assigned_rep_id'=>$old,'title'=>'Synthetic task','task_type'=>'General','priority'=>'High','status'=>'pending','due_date'=>current_time('mysql'),'created_at'=>current_time('mysql'),'updated_at'=>current_time('mysql')));
    IDLM_DB::update_lead($lead,array('assigned_rep_id'=>$new));
    wp_set_current_user($old);
    native_result('CORE-N01 reassigned lead drawer denied former owner',false,IDLM_Lead_Access_Policy::can_access($lead,true));
    add_filter('wp_die_ajax_handler',static function(){return static function(){throw new Core_Audit_Ajax_Die();};});
    add_filter('wp_die_handler',static function(){return static function(){throw new Core_Audit_Ajax_Die();};});
    $_POST=array('nonce'=>wp_create_nonce('idlm_admin_nonce')); $_REQUEST=$_POST;
    ob_start(); try { IDLM_Admin_Ajax::ajax_get_rep_tasks(); } catch(Core_Audit_Ajax_Die $e) {}
    $payload=json_decode(ob_get_clean(),true);
    $leaks=array_values(array_filter($payload['data']['tasks']??array(),static function($row)use($lead){return (int)$row['lead_id']===(int)$lead;}));
    native_result('CORE-N02 former owner queue contains no reassigned lead PII',0,count($leaks));
    wp_set_current_user($admin);
    $trigger=$wpdb->prefix.'audit_event_insert_failure';
    $wpdb->query("CREATE TRIGGER {$trigger} BEFORE INSERT ON {$wpdb->prefix}idlm_lead_state_events FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Synthetic audit event failure'");
    $previous=$wpdb->suppress_errors(true);
    $r=IDLM_DB::update_lead($lead,array('stage'=>'Contacted'));
    native_result('CORE-N03 failed event write rejects lifecycle update',false,$r);
    native_result('CORE-N04 failed event write rolls stage back','New',$wpdb->get_var($wpdb->prepare("SELECT stage FROM {$wpdb->prefix}idlm_leads WHERE id=%d",$lead)));
    $wpdb->query("DROP TRIGGER {$trigger}"); $wpdb->suppress_errors($previous);
    $wpdb->insert($wpdb->prefix.'idlm_inbound_submission_reviews',array('platform'=>'website','email'=>'review-pii@example.test','phone'=>'9999990002','candidate_lead_ids'=>'['.$lead.']','payload'=>'{}','status'=>'pending','received_at'=>current_time('mysql')));
    $r=IDLM_Lead_Deletion_Service::reset_all((string)$wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}idlm_leads"));
    native_result('CORE-N05 reset completes',false,is_wp_error($r));
    native_result('CORE-N06 reset clears identity-review PII',0,(int)$wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}idlm_inbound_submission_reviews"));
    update_option('idlm_assignment_mode','round_robin');
    update_option('idlm_active_assignment_reps',array($new));
    update_option('idlm_max_active_leads_per_rep',1);
    update_option('idlm_fallback_assignment_enabled','1');
    update_option('idlm_fallback_assignment_hours','1');
    for($i=0;$i<3;$i++) {
        $id=IDLM_DB::insert_lead(array('first_name'=>'Synthetic capacity '.$i,'phone'=>'999998000'.$i));
        $wpdb->update($wpdb->prefix.'idlm_leads',array('created_at'=>'2026-09-01 09:00:00'),array('id'=>$id));
    }
    IDLM_Integrations::run_assignment_fallback();
    native_result('CORE-N07 unassigned fallback respects one-lead capacity',1,(int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->prefix}idlm_leads WHERE assigned_rep_id=%d",$new)));
    echo json_encode(array('environment'=>array('database'=>$config['db_name'],'PHP'=>PHP_VERSION,'WordPress'=>$GLOBALS['wp_version'],'external_services'=>'blocked','uploads'=>'isolated temp directory'),'results'=>$results),JSON_PRETTY_PRINT).PHP_EOL;
} finally {
    $database->drop();
    idlm_integration_remove_directory($config['content_dir']);
}
