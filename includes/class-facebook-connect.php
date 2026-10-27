<?php
/** Direct Facebook connection facade and legacy hosted transport. Builder: Shashi. */
if (!defined('ABSPATH')) { exit; }

require_once __DIR__ . '/class-facebook-direct.php';

final class IDLM_Facebook_Connect {
    const PENDING = 'idlm_facebook_pending_';
    public static function init() { IDLM_Facebook_Direct::init(); add_action('wp_ajax_idlm_facebook_connect',array(__CLASS__,'ajax')); }
    public static function ready() { return IDLM_Facebook_Direct::ready(); }
    private static function service_ready() {
        return defined('IDLM_FACEBOOK_SERVICE_URL') && is_string(IDLM_FACEBOOK_SERVICE_URL) && self::valid_service_url(IDLM_FACEBOOK_SERVICE_URL)
            && defined('IDLM_FACEBOOK_SITE_ID') && is_string(IDLM_FACEBOOK_SITE_ID) && preg_match('/^[a-f0-9]{32}$/D',IDLM_FACEBOOK_SITE_ID)
            && defined('IDLM_FACEBOOK_SITE_KEY') && is_string(IDLM_FACEBOOK_SITE_KEY) && preg_match('/^[a-f0-9]{64}$/D',IDLM_FACEBOOK_SITE_KEY);
    }
    public static function valid_service_url($url) {
        if (!is_string($url) || preg_match('/[\x00-\x20\x7f\\\\]/',$url)) { return false; } $p=wp_parse_url($url);
        return is_array($p) && ($p['scheme'] ?? '')==='https' && !empty($p['host']) && !isset($p['user']) && !isset($p['pass']) && !isset($p['query']) && !isset($p['fragment']);
    }
    public static function hosted() { return (IDLM_Lead_Connector::config()['mode'] ?? '') === 'hosted'; }
    public static function request($action,$data=array()) {
        if (!self::service_ready()) { return new WP_Error('idlm_facebook_setup','Facebook connection service is not configured for this CRM. Contact the site administrator.'); }
        $body=wp_json_encode(array('action'=>$action,'data'=>(object)$data));$nonce=bin2hex(random_bytes(16));$stamp=(string)time();
        $signature=hash_hmac('sha256',$stamp."\n".$nonce."\n".$body,IDLM_FACEBOOK_SITE_KEY);
        // Safe HTTP rejects internal destinations, redirects and credentials in URLs.
        $args=array('headers'=>array('Content-Type'=>'application/json','X-IDLM-Site'=>IDLM_FACEBOOK_SITE_ID,'X-IDLM-Time'=>$stamp,'X-IDLM-Nonce'=>$nonce,'X-IDLM-Signature'=>$signature),'body'=>$body,'timeout'=>20,'redirection'=>0,'limit_response_size'=>1048576);
        try { $response=wp_safe_remote_post(rtrim(IDLM_FACEBOOK_SERVICE_URL,'/').'/api',$args); }
        catch (Throwable $error) { return new WP_Error('idlm_facebook_service','Facebook connection service could not be reached. Try again.'); }
        if (is_wp_error($response)) { return new WP_Error('idlm_facebook_service','Facebook connection service could not be reached. Try again.'); }
        $raw=wp_remote_retrieve_body($response);$code=wp_remote_retrieve_response_code($response);$proof=wp_remote_retrieve_header($response,'x-idlm-signature');
        if (!is_string($proof) || !hash_equals(hash_hmac('sha256',$nonce."\n".$code."\n".$raw,IDLM_FACEBOOK_SITE_KEY),$proof)) { return new WP_Error('idlm_facebook_proof','Connection service response could not be verified.'); }
        $result=json_decode($raw,true,32);
        if (!is_array($result)) { return new WP_Error('idlm_facebook_response','Invalid connection service response.'); }
        if ($code!==200 || isset($result['error'])) { return new WP_Error('idlm_facebook_response',is_string($result['error'] ?? null) ? sanitize_text_field($result['error']) : 'Facebook connection failed.'); }
        return $result;
    }
    public static function context() {
        $config=IDLM_Lead_Connector::config();
        $connected=in_array($config['mode'] ?? '', array('hosted','direct'), true) && !empty($config['connection']);
        return array('ready'=>(bool)self::ready(),'connected'=>$connected,'authorized'=>$connected || IDLM_Facebook_Direct::authorized(),'page_name'=>$config['page_name'] ?? '','form_names'=>$config['form_names'] ?? array());
    }
    public static function valid_authorization_url($url) {
        if (!is_string($url)) { return false; }$p=wp_parse_url($url);parse_str($p['query'] ?? '',$query);
        return ($p['scheme'] ?? '')==='https' && ($p['host'] ?? '')==='www.facebook.com' && ($p['path'] ?? '')==='/v25.0/dialog/oauth' && !isset($p['user']) && !isset($p['pass']) && !isset($p['fragment'])
            && !array_diff(array_keys($query),array('client_id','redirect_uri','config_id','response_type','state')) && ($query['response_type'] ?? '')==='code' && is_string($query['state'] ?? null) && preg_match('/^[a-f0-9]{64}$/D',$query['state']);
    }
    /** All operations bind to the current WordPress administrator and session. */
    public static function operation($op,$input) {
        if (!current_user_can('manage_options')) { return new WP_Error('idlm_facebook_forbidden','Administrator access required.'); }
        if ($op !== 'disconnect' || !self::hosted()) { return IDLM_Facebook_Direct::operation($op, $input); }
        if (!in_array($op,array('start','pages','forms','select','disconnect'),true)) { return new WP_Error('idlm_facebook_operation','Invalid connection operation.'); }
        if ($op==='disconnect') {
            // Stop local imports immediately, even if the service is unavailable.
            $config=IDLM_Lead_Connector::config();$config['enabled']='0';update_option('idlm_meta_lead_connector',$config,false);
            if (IDLM_Lead_Connector::config()!==$config) { return new WP_Error('idlm_facebook_store','Could not stop local sync. Please retry.'); }
            $result=self::request('disconnect'); // Local migration must remain possible if the old service is unavailable.
            unset($config['connection']);$config['mode']='manual';update_option('idlm_meta_lead_connector',$config,false);
            if (IDLM_Lead_Connector::config()!==$config) { return new WP_Error('idlm_facebook_store','Sync is stopped, but the disconnected state could not be saved. Retry Disconnect.'); }
            delete_option(self::PENDING.get_current_user_id());delete_option(IDLM_Facebook_Direct::PENDING.'epoch');
            return array('disconnected'=>true,'service_disconnected'=>!is_wp_error($result));
        }
        return new WP_Error('idlm_facebook_operation','Invalid connection operation.');
    }
    public static function ajax() {
        if (!current_user_can('manage_options')) { wp_send_json_error(array('message'=>'Administrator access required.'),403); }
        check_ajax_referer('idlm_admin_nonce','nonce');$input=wp_unslash($_POST);$op=$input['operation'] ?? '';
        if (!is_string($op)) { wp_send_json_error(array('message'=>'Invalid operation.'),400); }
        $result=self::operation($op,$input);
        if (is_wp_error($result)) { wp_send_json_error(array('message'=>$result->get_error_message()),400); }
        wp_send_json_success($result);
    }
    public static function pull() {
        $config=IDLM_Lead_Connector::config();if (($config['mode'] ?? '')!=='hosted' || ($config['enabled'] ?? '')!=='1') { return; }
        $data=array('connection'=>$config['connection']);$result=self::request('events',$data);if (is_wp_error($result)) { return; }$ids=array();
        foreach ($result['events'] ?? array() as $event) {
            if (!is_array($event) || !is_numeric($event['id'] ?? null) || ($event['page'] ?? '')!==$config['page_id'] || !in_array($event['form'] ?? '',$config['forms'],true) || !IDLM_Lead_Connector::id($event['lead'] ?? null)) { return; }
            $saved=IDLM_Lead_Connector::enqueue('meta',$event['lead'],array('id'=>$event['lead'],'page_id'=>$event['page'],'form_id'=>$event['form']));
            if ($saved!==true) { return; }$ids[]=(int)$event['id'];
        }
        if ($ids) { self::request('ack',array_merge($data,array('ids'=>$ids))); }
    }
    public static function graph($path,$query) {
        $config=IDLM_Lead_Connector::config();return self::request('graph',array('connection'=>$config['connection'] ?? '', 'path'=>$path,'query'=>(object)$query));
    }
}
