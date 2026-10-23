<?php
/** INT-010 native credential migration and request privacy. Builder: Shashi. */
if (!defined('ABSPATH') || strpos(DB_NAME,'idlm_test_')!==0) { exit(1); }
function idlm_secret_other() { return idlm_integration_other_connection(); }
function idlm_secret_fail($needle, callable $callback) {
    global $wpdb;$filter=static function($sql)use($needle){return strpos($sql,$needle)!==false ? 'UPDATE idlm_int010_missing_table SET x=1' : $sql;};
    $old=$wpdb->suppress_errors(true);add_filter('query',$filter);
    try{return $callback();}finally{remove_filter('query',$filter);$wpdb->suppress_errors($old);$wpdb->last_error='';}
}
idlm_integration_test('T20-N-INT010 all platform legacy values migrate without autoload or plaintext',static function():void {
    global $wpdb;
    foreach(IDLM_Secret_Store::platform_options() as $name){delete_option($name);add_option($name,'synthetic-'.$name,'','yes');}
    idlm_integration_assert_same(true,IDLM_Secret_Store::migrate_platforms());
    foreach(IDLM_Secret_Store::platform_options() as $name){$row=$wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->options} WHERE option_name=%s",$name));idlm_integration_assert_same('synthetic-'.$name,IDLM_Secret_Store::get($name,'',true));idlm_integration_assert_same('no',$row->autoload);idlm_integration_assert_true(strpos($row->option_value,'synthetic-')===false);}
});
idlm_integration_test('T20-N-INT010 failed write retains plaintext and blocks use until retry',static function():void {
    update_option('idlm_google_api_secret','synthetic-write-fault');
    $r=idlm_secret_fail('UPDATE `'. $GLOBALS['wpdb']->options .'`',static function(){return IDLM_Secret_Store::get('idlm_google_api_secret','',true);});
    idlm_integration_assert_same('',$r);idlm_integration_assert_same('synthetic-write-fault',IDLM_Secret_Store::stored('idlm_google_api_secret'));
    idlm_integration_assert_same('synthetic-write-fault',IDLM_Secret_Store::get('idlm_google_api_secret','',true));
});
idlm_integration_test('T20-N-INT010 damaged ciphertext does not overwrite credentials or advance scoped migration',static function():void {
    $damaged=IDLM_Secret_Store::PREFIX.base64_encode(str_repeat('x',40));update_option('idlm_webhook_api_key',$damaged);delete_option('idlm_api_credentials_version');update_option('idlm_api_webhook_key','synthetic-legacy-retained');
    IDLM_Integrations::maybe_migrate_api_credentials();idlm_integration_assert_same($damaged,IDLM_Secret_Store::stored('idlm_webhook_api_key'));idlm_integration_assert_same('synthetic-legacy-retained',get_option('idlm_api_webhook_key'));idlm_integration_assert_same(false,get_option('idlm_api_credentials_version'));
    IDLM_Secret_Store::set('idlm_webhook_api_key','synthetic-replacement');IDLM_Integrations::maybe_migrate_api_credentials();idlm_integration_assert_same(false,get_option('idlm_api_webhook_key'));
});
idlm_integration_test('T14-C-INT010 competing rotation wins plaintext compare and swap',static function():void {
    global $wpdb;update_option('idlm_meta_access_token','synthetic-old');IDLM_Secret_Store::set('idlm_linkedin_access_token','synthetic-rotated');$replacement=IDLM_Secret_Store::stored('idlm_linkedin_access_token');
    $other=idlm_secret_other();$once=false;$filter=static function($sql)use(&$once,$other,$replacement,$wpdb){if(!$once && strpos($sql,'UPDATE `'.$wpdb->options.'`')===0 && strpos($sql,'idlm_meta_access_token')!==false){$once=true;$stmt=$other->prepare("UPDATE {$wpdb->options} SET option_value=? WHERE option_name='idlm_meta_access_token'");$stmt->bind_param('s',$replacement);$stmt->execute();$stmt->close();}return $sql;};
    add_filter('query',$filter);try{$value=IDLM_Secret_Store::get('idlm_meta_access_token','',true);}finally{remove_filter('query',$filter);$other->close();}
    idlm_integration_assert_same(true,$once);idlm_integration_assert_same('synthetic-rotated',$value);idlm_integration_assert_same($replacement,IDLM_Secret_Store::stored('idlm_meta_access_token'));
});
idlm_integration_test('T14-N-INT010 current primary credential overrides warmed cache then revocation',static function():void {
    global $wpdb;IDLM_Secret_Store::set('idlm_interakt_api_key','synthetic-before');get_option('idlm_interakt_api_key');$other=idlm_secret_other();
    try{$other->query("UPDATE {$wpdb->options} SET option_value='' WHERE option_name='idlm_interakt_api_key'");idlm_integration_assert_same('',IDLM_Secret_Store::get('idlm_interakt_api_key','',true));}finally{$other->close();}
});
idlm_integration_test('T08-N-INT010 Meta Google LinkedIn requests use current auth and scrub provider echoes',static function():void {
    global $wpdb;
    foreach(array('meta','google','linkedin') as $platform){
        [$lead,$log]=idlm_capi_native_fixture($platform,'Qualified');$name=array('meta'=>'idlm_meta_access_token','google'=>'idlm_google_api_secret','linkedin'=>'idlm_linkedin_access_token')[$platform];$secret='synthetic&010/key+';IDLM_Secret_Store::set($name,$secret);
        $calls=array();$f=static function($pre,$args,$url)use(&$calls,$secret){$calls[]=array($url,$args);return new WP_Error('synthetic','Failure '.$url.' Authorization: Bearer '.$secret.' Basic '.base64_encode($secret.':'));};add_filter('pre_http_request',$f,PHP_INT_MAX,3);
        try{$result=IDLM_CAPI::retry_capi_log($log);}finally{remove_filter('pre_http_request',$f,PHP_INT_MAX);}
        idlm_integration_assert_same(1,count($calls));[$url,$args]=$calls[0];idlm_integration_assert_same(0,$args['redirection']);
        if($platform==='google'){parse_str(parse_url($url,PHP_URL_QUERY),$query);idlm_integration_assert_same($secret,$query['api_secret']);}else{idlm_integration_assert_true(strpos($url,'access_token')===false);idlm_integration_assert_same('Bearer '.$secret,$args['headers']['Authorization']);}
        $response=$wpdb->get_var($wpdb->prepare("SELECT response FROM {$wpdb->prefix}idlm_capi_logs WHERE id=%d",$result['log_id']));foreach(array($secret,rawurlencode($secret),base64_encode($secret.':'))as$marker){idlm_integration_assert_true(strpos($response,$marker)===false);}
    }
});
idlm_integration_test('T08-T09-N-INT010 audience Interakt request echoes and backups contain no token',static function():void {
    global $wpdb;update_option('idlm_whatsapp_notifications_enabled','1');$secret='synthetic-audience-010';IDLM_Secret_Store::set('idlm_meta_access_token',$secret);$lead=idlm_aud_lead();$rule=idlm_aud_rule();
    $calls=array();$f=static function($pre,$args,$url)use(&$calls,$secret){$calls[]=array($url,$args);return array('response'=>array('code'=>400),'body'=>'{"error":{"message":"Echo '.$secret.'"}}','headers'=>array());};add_filter('pre_http_request',$f,PHP_INT_MAX,3);
    try{$r=IDLM_Recovery_Guard::run(static function()use($wpdb,$lead,$rule){return IDLM_Audiences::send_page(array($wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}idlm_leads WHERE id=%d",$lead))),$rule);});$sent=IDLM_Interakt::send_track_event_to_interakt(array('synthetic'=>true),$secret,$lead);}finally{remove_filter('pre_http_request',$f,PHP_INT_MAX);}
    idlm_integration_assert_same(2,count($calls));idlm_integration_assert_true(strpos($calls[0][0],'access_token')===false);idlm_integration_assert_same('Bearer '.$secret,$calls[0][1]['headers']['Authorization']);idlm_integration_assert_true(strpos(wp_json_encode($r),$secret)===false);
    $backup=idlm_native_backup_call('snapshot');idlm_integration_assert_true(!is_wp_error($backup));idlm_integration_assert_true(strpos(wp_json_encode($backup),$secret)===false);
});
idlm_integration_test('T20-N-INT010 settings blank retains replace clears and failed save is explicit',static function():void {
    global $administrator_id;wp_set_current_user($administrator_id);$_REQUEST=array();$_SERVER['REQUEST_METHOD']='POST';
    $base=array('idlm_save_settings'=>'1','_wpnonce'=>wp_create_nonce('idlm_settings_save_action'));
    $guard=static function(){return static function($message){throw new IDLM_Integration_WP_Die((string)$message);};};add_filter('wp_die_handler',$guard);add_filter('wp_die_ajax_handler',$guard);
    idlm_integration_assert_true(IDLM_Secret_Store::set('idlm_meta_access_token','synthetic-settings-010'),'Initial credential save failed');$_POST=$base;$_REQUEST=$_POST;IDLM_Admin_Settings_Controller::process_request();idlm_integration_assert_same('synthetic-settings-010',IDLM_Secret_Store::get('idlm_meta_access_token','',true));
    $_POST=$base+array('meta_access_token'=>'synthetic-replaced-010');$_REQUEST=$_POST;IDLM_Admin_Settings_Controller::process_request();idlm_integration_assert_same('synthetic-replaced-010',IDLM_Secret_Store::get('idlm_meta_access_token','',true));
    $_POST=$base+array('meta_access_token_clear'=>'1');$_REQUEST=$_POST;IDLM_Admin_Settings_Controller::process_request();idlm_integration_assert_same('',IDLM_Secret_Store::get('idlm_meta_access_token','default',true));
    $_POST=$base+array('meta_access_token'=>'synthetic-failure-010');$die=static function(){return static function($message){throw new IDLM_Integration_WP_Die($message);};};add_filter('wp_die_handler',$die);add_filter('wp_die_ajax_handler',$die);
    try{$failed=false;try{idlm_secret_fail('UPDATE `'.$GLOBALS['wpdb']->options.'`',static function(){$_REQUEST=$_POST;IDLM_Admin_Settings_Controller::process_request();});}catch(IDLM_Integration_WP_Die $e){$failed=true;idlm_integration_assert_true(strpos($e->getMessage(),'synthetic-failure')===false);}idlm_integration_assert_same(true,$failed);}finally{remove_filter('wp_die_handler',$die);remove_filter('wp_die_ajax_handler',$die);remove_filter('wp_die_handler',$guard);remove_filter('wp_die_ajax_handler',$guard);$_POST=array();$_REQUEST=array();$_SERVER['REQUEST_METHOD']='GET';}
});
idlm_integration_test('T14-T20-N-INT010 legacy WP Cron copied secrets disappear while opaque jobs remain',static function():void {
    $hook='idlm_async_interakt_track_event';$now=time()+600;$key='idlm_interakt_dispatch_'.str_repeat('a',64);$old=array(array('event'=>'synthetic'),'synthetic-old-queued-key',123);
    wp_schedule_single_event($now,$hook,$old);wp_schedule_single_event($now,$hook,array($key));
    idlm_integration_assert_same(true,IDLM_Secret_Store::scrub_legacy_queues());idlm_integration_assert_same(false,wp_next_scheduled($hook,$old));idlm_integration_assert_same($now,wp_next_scheduled($hook,array($key)));wp_unschedule_event($now,$hook,array($key));
});
idlm_integration_test('T14-T20-N-INT010 AS historical secret batches preserve current jobs statuses and checkpoint on failure',static function():void {
    global $wpdb;$table=$wpdb->prefix.'actionscheduler_actions';idlm_integration_assert_true(!idlm_integration_table_exists($table),'Fixture requires absent AS table');
    $wpdb->query("CREATE TABLE `$table` (action_id bigint unsigned AUTO_INCREMENT PRIMARY KEY, hook varchar(191), status varchar(30), args text, extended_args text NULL) ENGINE=InnoDB");delete_option('idlm_secret_queue_scrub_cursor');
    try {
        for($i=0;$i<101;$i++){$wpdb->insert($table,array('hook'=>'idlm_async_interakt_track_event','status'=>$i%2?'complete':'pending','args'=>wp_json_encode(array(array('event'=>'synthetic'),'synthetic-queue-secret',$i))));}
        $key='idlm_interakt_dispatch_'.str_repeat('b',64);$wpdb->insert($table,array('hook'=>'idlm_async_interakt_track_event','status'=>'pending','args'=>wp_json_encode(array($key))));$opaque=$wpdb->insert_id;
        $wpdb->insert($table,array('hook'=>'unrelated_hook','status'=>'complete','args'=>'["unrelated"]'));$other=$wpdb->insert_id;
        idlm_integration_assert_same(true,IDLM_Secret_Store::scrub_legacy_queues());idlm_integration_assert_same(100,(int)get_option('idlm_secret_queue_scrub_cursor'));idlm_integration_assert_same(1,(int)$wpdb->get_var("SELECT COUNT(*) FROM `$table` WHERE args LIKE '%synthetic-queue-secret%'"));
        idlm_integration_assert_same(false,idlm_secret_fail('UPDATE `'.$table.'`',static function(){return IDLM_Secret_Store::scrub_legacy_queues();}));idlm_integration_assert_same(100,(int)get_option('idlm_secret_queue_scrub_cursor'));
        idlm_integration_assert_same(true,IDLM_Secret_Store::scrub_legacy_queues());idlm_integration_assert_same(0,(int)$wpdb->get_var("SELECT COUNT(*) FROM `$table` WHERE args LIKE '%synthetic-queue-secret%'"));idlm_integration_assert_same(wp_json_encode(array($key)),$wpdb->get_var("SELECT args FROM `$table` WHERE action_id=$opaque"));idlm_integration_assert_same('["unrelated"]',$wpdb->get_var("SELECT args FROM `$table` WHERE action_id=$other"));idlm_integration_assert_same(50,(int)$wpdb->get_var("SELECT COUNT(*) FROM `$table` WHERE status='complete' AND hook='idlm_async_interakt_track_event'"));
    } finally {$wpdb->query("DROP TABLE `$table`");delete_option('idlm_secret_queue_scrub_cursor');}
});
idlm_integration_test('T08-N-INT010 thrown transport exceptions never expose credential or stack',static function():void {
    $filter=static function(){throw new RuntimeException('synthetic-thrown-secret');};add_filter('pre_http_request',$filter,PHP_INT_MAX,3);
    try{$r=IDLM_Secret_Store::request('https://www.google-analytics.com/mp/collect?api_secret=synthetic-thrown-secret',array(),array('synthetic-thrown-secret'));}finally{remove_filter('pre_http_request',$filter,PHP_INT_MAX);}
    idlm_integration_assert_true(is_wp_error($r));idlm_integration_assert_true(strpos($r->get_error_message(),'synthetic-thrown-secret')===false);
});
