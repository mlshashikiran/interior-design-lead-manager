<?php
/** Administrator notification controls through the real settings handler. Builder: Shashi. */
if ('cli' !== PHP_SAPI || strpos($config['db_name'] ?? '', 'idlm_test_') !== 0) { exit(1); }
function idlm_notify_http_nonce(): string {
    $r=idlm_http_request('/settings-workspace?tab=general',array(),'admin',false);
    preg_match('/name="_wpnonce" value="([^"]+)"/',$r['body'],$m);idlm_http_check(!empty($m[1]),'Settings nonce unavailable');return $m[1];
}
idlm_http_case('NOTIFY-H settings expose one unchecked control per channel by default',static function():void {
    foreach(array('idlm_handoff_notifications_enabled','idlm_routing_exception_notifications_enabled','idlm_whatsapp_notifications_enabled')as$key){delete_option($key);}
    $r=idlm_http_request('/settings-workspace?tab=general',array(),'admin',false);$dom=new DOMDocument();@$dom->loadHTML($r['body']);$xpath=new DOMXPath($dom);
    foreach(array('handoff_notifications_enabled','routing_exception_notifications_enabled','whatsapp_notifications_enabled')as$key){$all=$xpath->query('//input[@name="'.$key.'"]');$in_card=$xpath->query('//*[@id="idlm-notification-settings"]//input[@name="'.$key.'"]');idlm_http_check($all->length===1&&$in_card->length===1&&!$all->item(0)->hasAttribute('checked'),'Missing, duplicated or enabled default notification control: '.$key);}
});
idlm_http_case('NOTIFY-H authorized opt-in persists independently and malformed WhatsApp choice disables',static function():void {
    global $wpdb;
    foreach(array(array('handoff_notifications_enabled'=>'1','whatsapp_notifications_enabled'=>'1'),array('routing_exception_notifications_enabled'=>'1','whatsapp_notifications_enabled'=>array('1')),array())as$choices){
        $r=idlm_http_request('/settings-workspace?tab=general',array_merge(array('idlm_save_settings'=>'1','_wpnonce'=>idlm_notify_http_nonce(),'white_label'=>IDLM_Settings::white_label_defaults()),$choices),'admin');idlm_http_check($r['status']===200,'Authorized notification settings save failed');
        foreach(array('handoff_notifications_enabled','routing_exception_notifications_enabled','whatsapp_notifications_enabled')as$key){$expected=($choices[$key]??null)==='1'?'1':'0';$actual=$wpdb->get_var($wpdb->prepare("SELECT option_value FROM {$wpdb->options} WHERE option_name=%s",'idlm_'.$key));idlm_http_check($actual===$expected,'Wrong persisted choice: '.$key);}
    }
});
idlm_http_case('NOTIFY-H denied actor and nonce cannot enable notifications',static function():void {
    global $wpdb;
    foreach(array(array('owner',idlm_notify_http_nonce()),array('admin','bad-nonce'))as$denial){
        $r=idlm_http_request('/settings-workspace?tab=general',array('idlm_save_settings'=>'1','_wpnonce'=>$denial[1],'handoff_notifications_enabled'=>'1','routing_exception_notifications_enabled'=>'1','whatsapp_notifications_enabled'=>'1'),$denial[0]);idlm_http_check($r['status']!==200,'Unauthorized notification settings accepted');
        foreach(array('handoff_notifications_enabled','routing_exception_notifications_enabled','whatsapp_notifications_enabled')as$key){idlm_http_check($wpdb->get_var($wpdb->prepare("SELECT option_value FROM {$wpdb->options} WHERE option_name=%s",'idlm_'.$key))==='0','Denied save changed notifications');}
    }
});
