<?php
/** Direct Meta Lead Ads and Interakt intake. Builder: Shashi. */
if (!defined('ABSPATH')) { exit; }

final class IDLM_Lead_Connector {
    const HOOK = 'idlm_lead_connector_tick';
    const JOB = 'idlm_connector_job_';
    const RECEIPT = 'idlm_connector_receipt_';
    const ATTRIBUTION = 'idlm_connector_attribution_';
    const MAX_ATTEMPTS = 5;
    const META_BASE_FIELDS = 'id,created_time,form_id,ad_id,field_data';
    const META_ATTRIBUTION_FIELDS = 'id,created_time,form_id,ad_id,field_data,ad_name,adset_id,adset_name,campaign_id,campaign_name,is_organic,platform,partner_name';
    const META_FULL_FIELDS = 'id,created_time,form_id,ad_id,field_data,ad_name,adset_id,adset_name,campaign_id,campaign_name,is_organic,platform,partner_name,custom_disclaimer_responses,home_listing,post,post_submission_check_result,retailer_item_id,vehicle';
    private static $settings_error = '';
    private static $settings_draft = null;

    public static function init() {
        add_action('rest_api_init', array(__CLASS__, 'routes'));
        add_action(self::HOOK, array(__CLASS__, 'tick'));
        add_action('wp_ajax_idlm_test_lead_connector', array(__CLASS__, 'ajax_test'));
        add_action('wp_ajax_idlm_meta_form_mapping', array(__CLASS__, 'ajax_form_mapping'));
        add_filter('rest_pre_serve_request', array(__CLASS__, 'serve_challenge'), 10, 4);
    }

    public static function option($name, $default = false) {
        return IDLM_WhatsApp_Inbox::setting($name, $default);
    }

    public static function config() {
        $value = self::option('idlm_meta_lead_connector', array());
        return is_array($value) ? $value : array();
    }

    public static function id($value) {
        return is_string($value) && preg_match('/^[1-9][0-9]{0,31}$/D', $value) ? $value : '';
    }

    /** Called only from the capability/nonce-protected shared settings controller. */
    public static function save_settings($input) {
        self::$settings_error = '';
        self::$settings_draft = null;
        if (!isset($input['idlm_connector_settings'])) { return; }
        $existing = self::config();
        $hosted = ($existing['mode'] ?? '') === 'hosted';
        if ($hosted) {
            // A general settings save cannot replace the OAuth-selected source.
            foreach (array('page_id','app_id') as $field) { $input['idlm_connector_' . $field] = $existing[$field] ?? ''; }
            $input['idlm_connector_form_ids'] = implode(', ', $existing['forms'] ?? array());
            if (($existing['enabled'] ?? '') === '1') { $input['idlm_meta_lead_sync'] = '1'; } else { unset($input['idlm_meta_lead_sync']); }
        }
        $config = array('enabled'=>isset($input['idlm_meta_lead_sync']) ? '1' : '0');
        foreach (array('page_id','app_id','login_config_id','whatsapp_page_id') as $field) {
            $value = $input['idlm_connector_' . $field] ?? '';
            if (!is_string($value) || ($value !== '' && self::id($value) === '')) { return self::settings_failure('Enter valid numeric Meta identifiers.', $input); }
            $config[$field] = $value;
        }
        $forms = $input['idlm_connector_form_ids'] ?? '';
        if (!is_string($forms) || strlen($forms) > 1000) { return self::settings_failure('Enter up to 20 form IDs.', $input); }
        $config['forms'] = array_values(array_unique(array_filter(preg_split('/[\s,]+/', trim($forms)))));
        if (count($config['forms']) > 20 || array_filter($config['forms'], static function($id) { return self::id($id) === ''; })) { return self::settings_failure('Enter up to 20 valid numeric form IDs.', $input); }
        $config['mappings'] = array();
        foreach (self::mapping_fields() as $field => $default) {
            $value = $input['idlm_connector_map_' . $field] ?? $default;
            if (!is_string($value) || strlen($value) > 191) { return self::settings_failure('Enter a field name of at most 191 characters for each lead mapping.', $input); }
            $config['mappings'][$field] = sanitize_text_field($value);
        }
        if ($config['enabled'] === '1') {
            $missing = array();
            foreach (array('page_id'=>'Facebook Page ID', 'app_id'=>'Meta app ID', 'forms'=>'at least one Instant Form ID') as $field=>$label) {
                if (empty($config[$field])) { $missing[] = $label; }
            }
            foreach ($hosted ? array() : array('access_token'=>'lead retrieval access token', 'app_secret'=>'Meta app secret', 'verify_token'=>'webhook verify token') as $field=>$label) {
                $credential = self::option('idlm_meta_lead_' . $field, '');
                if (!is_string($credential) || $credential === '') { $missing[] = $label; }
            }
            if ($missing) {
                return self::settings_failure('Missing: ' . implode(', ', $missing) . '. Complete these fields, or uncheck direct Instant Form sync to save an unfinished setup. Any credentials already saved are retained.', $input);
            }
        }
        // Only Purchase is enabled by default; other business-messaging outcomes need an explicit mapping.
        $config['whatsapp_events']=array();
        foreach (array('New','Qualified','Quote Shared','Won') as $stage) {
            $field='idlm_whatsapp_event_'.sanitize_key($stage);
            $value=$input[$field] ?? ($stage === 'Won' ? 'Purchase' : '');
            if (!is_string($value) || strlen($value)>100 || !preg_match('/^[A-Za-z0-9_ ]*$/D',$value)) { return self::settings_failure('WhatsApp event names must contain only letters, numbers, spaces or underscores and be at most 100 characters.', $input); }
            $config['whatsapp_events'][$stage]=trim($value);
        }
        $old = self::config();
        // A changed source starts a new bounded catch-up window; never scan historic leads implicitly.
        $config['started_at'] = isset($old['started_at']) && ($old['page_id'] ?? '') === $config['page_id'] ? (int)$old['started_at'] : time();
        if ($hosted) { $config = array_merge($existing, $config); }
        elseif (($existing['mode'] ?? '') === 'direct' && ($existing['page_id'] ?? '') === $config['page_id'] && ($existing['app_id'] ?? '') === $config['app_id'] && ($existing['forms'] ?? array()) === $config['forms']) {
            foreach (array('mode','connection','page_name','form_names') as $field) { if (isset($existing[$field])) { $config[$field] = $existing[$field]; } }
        }
        update_option('idlm_meta_lead_connector', $config, false);
        if (self::config() !== $config) { return self::settings_failure('Connector settings could not be saved. Please retry.', $input); }
        update_option('idlm_interakt_lead_capture', isset($input['idlm_interakt_lead_capture']) ? '1' : '0', false);
        update_option('idlm_whatsapp_capi_enabled', isset($input['idlm_whatsapp_capi_enabled']) ? '1' : '0', false);
        if (isset($input['idlm_connector_retry'])) { self::retry_failed(); }
    }

    /** Keep only non-secret submitted values for redisplay after ordinary validation errors. */
    private static function settings_failure($message, $input) {
        self::$settings_error = $message;
        $config = self::config();
        $config['enabled'] = isset($input['idlm_meta_lead_sync']) ? '1' : '0';
        foreach (array('page_id','app_id','login_config_id','whatsapp_page_id') as $field) {
            $value = $input['idlm_connector_' . $field] ?? '';
            $config[$field] = is_string($value) ? sanitize_text_field(substr($value, 0, 100)) : '';
        }
        $forms = $input['idlm_connector_form_ids'] ?? '';
        $config['forms'] = is_string($forms) ? array_values(array_filter(preg_split('/[\s,]+/', sanitize_text_field(substr($forms, 0, 1000))))) : array();
        foreach (self::mapping_fields() as $field=>$default) {
            $value = $input['idlm_connector_map_' . $field] ?? $default;
            $config['mappings'][$field] = is_string($value) ? sanitize_text_field(substr($value, 0, 191)) : $default;
        }
        foreach (array('New','Qualified','Quote Shared','Won') as $stage) {
            $value = $input['idlm_whatsapp_event_' . sanitize_key($stage)] ?? ($stage === 'Won' ? 'Purchase' : '');
            $config['whatsapp_events'][$stage] = is_string($value) ? sanitize_text_field(substr($value, 0, 100)) : '';
        }
        self::$settings_draft = array('config'=>$config, 'interakt_capture'=>isset($input['idlm_interakt_lead_capture']) ? '1' : '0', 'whatsapp_capi'=>isset($input['idlm_whatsapp_capi_enabled']) ? '1' : '0');
        return new WP_Error('idlm_connector_settings', $message);
    }

    public static function settings_context() {
        $credentials=array();$managed_credentials=array();
        foreach (array('access_token','app_secret','verify_token') as $key) {
            $credentials[$key]=IDLM_Secret_Store::get('idlm_meta_lead_'.$key,'',true) !== '';
            $managed_credentials[$key]=IDLM_Secret_Store::is_environment_managed('idlm_meta_lead_'.$key);
        }
        return array('config'=>self::$settings_draft['config'] ?? self::config(),'error'=>self::$settings_error,'status'=>self::option('idlm_connector_last_status',array()),'credentials'=>$credentials,'managed_credentials'=>$managed_credentials,
            'interakt_capture'=>self::$settings_draft['interakt_capture'] ?? self::option('idlm_interakt_lead_capture','0'),'whatsapp_capi'=>self::$settings_draft['whatsapp_capi'] ?? self::option('idlm_whatsapp_capi_enabled','0'),'mapping_fields'=>self::mapping_fields(),'facebook'=>IDLM_Facebook_Connect::context());
    }

    public static function mapping_fields() {
        return array('first_name'=>'first_name','last_name'=>'last_name','email'=>'email','phone'=>'phone_number','budget'=>'budget','project_timeline'=>'project_timeline','property_location'=>'city','property_type'=>'property_type','num_rooms'=>'num_rooms','preferred_studio'=>'preferred_studio');
    }

    /** Only profile/project destinations are configurable; lifecycle fields remain internal. */
    public static function mapping_targets() {
        $targets=array('full_name'=>'Full name (split into first and last name)');
        foreach (self::mapping_fields() as $field=>$default) { $targets[$field]=ucwords(str_replace('_',' ',$field)); }
        return $targets;
    }

    private static function form_mapping_option($form,$config) {
        return 'idlm_meta_form_mapping_'.hash('sha256',($config['app_id'] ?? '').'|'.($config['page_id'] ?? '').'|'.$form);
    }

    public static function form_mapping($form,$config=null) {
        if ($config===null) { $config=self::config(); }
        $saved=self::option(self::form_mapping_option($form,$config),null);
        if (is_array($saved)) { return $saved; }
        return array_merge(self::mapping_fields(),is_array($config['mappings'] ?? null) ? $config['mappings'] : array(),array('full_name'=>'full_name'));
    }

    /** Read form metadata only, never respondent answers, through the selected Page token. */
    private static function form_questions($form,$config) {
        $data=self::graph($form,array('fields'=>'id,name,page,questions'));
        if (is_wp_error($data)) { return $data; }
        if (($data['id'] ?? '')!==$form || ($data['page']['id'] ?? '')!==($config['page_id'] ?? '') || !is_array($data['questions'] ?? null) || count($data['questions'])>100) {
            return new WP_Error('idlm_form_fields','Meta did not return a supported form belonging to the selected Page.');
        }
        $questions=array();$unavailable=0;
        foreach ($data['questions'] as $question) {
            $key=$question['key'] ?? null;
            if (!is_string($key) || $key==='' || strlen($key)>191 || preg_match('/[\x00-\x1f\x7f]/',$key)) { $unavailable++;continue; }
            $questions[$key]=array('key'=>$key,'label'=>sanitize_text_field(is_string($question['label'] ?? null) ? $question['label'] : $key),'type'=>sanitize_text_field(is_string($question['type'] ?? null) ? $question['type'] : ''));
        }
        if (!$questions) { return new WP_Error('idlm_form_fields','Meta returned no mappable question keys. Keep the existing defaults or check form access.'); }
        return array('form_id'=>$form,'name'=>sanitize_text_field(is_string($data['name'] ?? null) ? $data['name'] : $form),'fields'=>array_values($questions),'unavailable_count'=>$unavailable);
    }

    /** Administrator-only operation; AJAX transport additionally verifies the shared nonce. */
    public static function form_mapping_operation($op,$input) {
        if (!current_user_can('manage_options')) { return new WP_Error('idlm_form_forbidden','Administrator access required.'); }
        if (!in_array($op,array('load','save'),true)) { return new WP_Error('idlm_form_operation','Invalid mapping operation.'); }
        $form=self::id($input['form_id'] ?? null);$config=self::config();
        if (!$form || !self::id($config['app_id'] ?? null) || !self::id($config['page_id'] ?? null) || !is_array($config['forms'] ?? null) || !in_array($form,$config['forms'],true)) {
            return new WP_Error('idlm_form_scope','Choose a form saved for this connection first.');
        }
        $metadata=self::form_questions($form,$config);
        if (is_wp_error($metadata)) { return $metadata; }
        if (self::config()!==$config) { return new WP_Error('idlm_form_changed','The connection changed. Reload the form before saving.'); }
        if ($op==='load') { return array_merge($metadata,array('mappings'=>self::form_mapping($form,$config),'targets'=>self::mapping_targets())); }
        $json=$input['mappings'] ?? null;
        if (!is_string($json) || strlen($json)>65536) { return new WP_Error('idlm_form_mapping','Invalid form mapping.'); }
        $rows=json_decode($json,true,8);
        if (!is_array($rows) || count($rows)>100) { return new WP_Error('idlm_form_mapping','Choose up to 100 form fields.'); }
        $keys=array_column($metadata['fields'],'key');$targets=self::mapping_targets();
        $mapping=array_fill_keys(array_keys($targets),'');$used=array();$sources=array();
        foreach ($rows as $row) {
            if (!is_array($row) || !is_string($row['source'] ?? null) || !is_string($row['target'] ?? null) || !in_array($row['source'],$keys,true) || isset($sources[$row['source']]) || ($row['target']!=='' && !isset($targets[$row['target']]))) {
                return new WP_Error('idlm_form_mapping','Choose returned Meta fields and supported CRM destinations only.');
            }
            $sources[$row['source']]=true;
            if ($row['target']==='') { continue; }
            if (isset($used[$row['target']])) { return new WP_Error('idlm_form_mapping','Map each CRM destination only once.'); }
            $used[$row['target']]=true;$mapping[$row['target']]=$row['source'];
        }
        if ($mapping['phone']==='' && $mapping['email']==='') { return new WP_Error('idlm_form_contact','Map at least one phone or email field so the CRM can identify the lead.'); }
        $option=self::form_mapping_option($form,$config);
        update_option($option,$mapping,false);
        if (self::option($option,null)!==$mapping) { return new WP_Error('idlm_form_store','The form mapping could not be saved. Retry.'); }
        return array('form_id'=>$form,'mappings'=>$mapping,'message'=>'Mapping saved for this form. It applies to future imports; existing CRM leads were not changed.');
    }

    public static function ajax_form_mapping() {
        if (!current_user_can('manage_options')) { wp_send_json_error(array('message'=>'Administrator access required.'),403); }
        check_ajax_referer('idlm_admin_nonce','nonce');
        $input=wp_unslash($_POST);$op=$input['operation'] ?? '';
        if (!is_string($op)) { wp_send_json_error(array('message'=>'Invalid mapping operation.'),400); }
        $result=self::form_mapping_operation($op,$input);
        if (is_wp_error($result)) { wp_send_json_error(array('message'=>$result->get_error_message()),400); }
        wp_send_json_success($result);
    }

    public static function routes() {
        register_rest_route('idlm/v1', '/meta-leads', array(
            array('methods'=>'GET','callback'=>array(__CLASS__,'verify'),'permission_callback'=>array(__CLASS__,'permission_verify')),
            array('methods'=>'POST','callback'=>array(__CLASS__,'receive'),'permission_callback'=>array(__CLASS__,'permission_post')),
        ));
    }

    public static function permission_verify($request) {
        $config = self::config(); if (($config['mode'] ?? '') === 'hosted') { return false; } $token = self::option('idlm_meta_lead_verify_token','');
        // Verify setup before enabling imports; signed POST intake remains gated by enabled.
        if ((($config['enabled'] ?? '') !== '1' && !self::id($config['page_id'] ?? '')) || !is_string($token) || $token === '') { return false; }
        $params = $request->get_query_params();
        foreach (array('hub_mode','hub_verify_token','hub_challenge') as $key) {
            // PHP converts dots to underscores on real query strings.
            $value = $params[$key] ?? ($params['hub.'.substr($key,4)] ?? null);
            if (!is_string($value) || $value === '' || strlen($value) > 512) { return false; }
            $values[$key] = $value;
        }
        return $values['hub_mode'] === 'subscribe' && hash_equals($token,$values['hub_verify_token']) && ctype_digit($values['hub_challenge']);
    }

    public static function verify($request) {
        if (!self::permission_verify($request)) { return new WP_REST_Response(array('message'=>'Forbidden'),403); }
        $params = $request->get_query_params();
        return new WP_REST_Response($params['hub_challenge'] ?? $params['hub.challenge'],200,array('Content-Type'=>'text/plain; charset=utf-8','Cache-Control'=>'no-store'));
    }

    public static function serve_challenge($served,$result,$request,$server) {
        if ($request->get_route() === '/idlm/v1/meta-leads' && $request->get_method() === 'GET' && $result->get_status() === 200 && is_string($result->get_data()) && ctype_digit($result->get_data())) {
            echo esc_html($result->get_data()); // Numeric verification challenge, not JSON.
            return true;
        }
        return $served;
    }

    public static function permission_post($request) {
        $config = self::config(); if (($config['mode'] ?? '') === 'hosted') { return false; } $secret = self::option('idlm_meta_lead_app_secret','');
        $body = $request->get_body(); $signature = $request->get_header('X-Hub-Signature-256');
        return ($config['enabled'] ?? '') === '1' && is_string($secret) && $secret !== '' && strlen($body) <= 262144
            && is_string($signature) && preg_match('/^sha256=[a-f0-9]{64}$/D',$signature)
            && hash_equals('sha256='.hash_hmac('sha256',$body,$secret),$signature);
    }

    public static function receive($request) {
        if (!self::permission_post($request)) { return new WP_REST_Response(array('message'=>'Invalid signature or disabled connector'),401); }
        $data = json_decode($request->get_body(),true,32); $config = self::config(); $notifications = array();
        if (!is_array($data) || ($data['object'] ?? '') !== 'page' || !is_array($data['entry'] ?? null)) { return new WP_REST_Response(array('message'=>'Invalid Page notification'),400); }
        foreach ($data['entry'] as $entry) {
            if (!is_array($entry) || !is_array($entry['changes'] ?? null)) { return new WP_REST_Response(array('message'=>'Invalid entry'),400); }
            foreach ($entry['changes'] as $change) {
                if (!is_array($change)) { return new WP_REST_Response(array('message'=>'Invalid change'),400); }
                if (($change['field'] ?? '') !== 'leadgen') { continue; }
                $value = $change['value'] ?? null;
                if (!is_array($value) || !self::id($value['leadgen_id'] ?? null) || !self::id($value['form_id'] ?? null) || !self::id($value['page_id'] ?? null) || ($entry['id'] ?? '') !== $value['page_id']) { return new WP_REST_Response(array('message'=>'Invalid lead identifiers'),400); }
                if ($value['page_id'] !== ($config['page_id'] ?? '') || !in_array($value['form_id'],$config['forms'] ?? array(),true)) { continue; }
                $notifications[] = array('id'=>$value['leadgen_id'],'page_id'=>$value['page_id'],'form_id'=>$value['form_id']);
                if (count($notifications) > 100) { return new WP_REST_Response(array('message'=>'Batch too large'),413); }
            }
        }
        foreach ($notifications as $notification) {
            $saved = self::enqueue('meta',$notification['id'],$notification);
            if ($saved !== true) { return new WP_REST_Response(array('message'=>'Notification could not be retained; retry required'),503); }
        }
        return new WP_REST_Response(array('success'=>true,'accepted'=>count($notifications)),200);
    }

    private static function lock($key) {
        global $wpdb;
        $name = IDLM_Recovery_Guard::name('lc_'.substr(hash('sha256',$key),0,20));
        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- REL007-SQL-0543: lock(): fixed prefixed options/lead tables and hashed job/erasure keys; LIKE stem escaped, cursor/IDs/marker values prepared; job/engine/erasure/lock checks deliberately read current state.
        return 1 === (int)$wpdb->get_var($wpdb->prepare('SELECT GET_LOCK(%s,0)',$name)) ? $name : false;
        // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
    }
    // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- REL007-SQL-0544: unlock(): fixed prefixed options/lead tables and hashed job/erasure keys; LIKE stem escaped, cursor/IDs/marker values prepared; job/engine/erasure/lock checks deliberately read current state.
    private static function unlock($name) { global $wpdb; $wpdb->get_var($wpdb->prepare('SELECT RELEASE_LOCK(%s)',$name)); }
    // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
    private static function read_job($key) {
        $raw = IDLM_Secret_Store::get($key,'',true);
        return is_string($raw) ? json_decode($raw,true) : null;
    }
    private static function write_job($key,$job) { return IDLM_Secret_Store::set($key,wp_json_encode($job)); }

    /** Encrypted payloads stay out of scheduler arguments and diagnostic logs. */
    public static function enqueue($provider,$id,$payload) {
        if (!in_array($provider,array('meta','interakt'),true) || !is_string($id) || $id === '' || strlen($id)>255 || !is_array($payload) || strlen(wp_json_encode($payload))>32768) { return new WP_Error('idlm_connector_input','Invalid intake event.'); }
        return IDLM_Recovery_Guard::run(static function() use ($provider,$id,$payload) {
            $hash = hash('sha256',$provider.'|'.$id); $key = self::JOB.$hash; $receipt_key=self::RECEIPT.$hash;
            $lock=self::lock($key); if (!$lock) { return new WP_Error('idlm_connector_busy','Intake is busy.'); }
            try {
                $generation=IDLM_Recovery_Guard::generation(); if (null === $generation) { return new WP_Error('idlm_connector_generation','Recovery state unavailable.'); }
                $identity=$provider === 'interakt' ? array('id'=>$payload['id'] ?? '', 'phone'=>$payload['phone'] ?? '', 'text'=>$payload['text'] ?? '') : $payload;
                $fingerprint=hash_hmac('sha256',wp_json_encode($identity),wp_salt('auth'));
                $receipt=self::option($receipt_key,false);
                if (is_wp_error($receipt)) { return $receipt; }
                if ($receipt !== false) { return is_array($receipt) && ($receipt['fingerprint'] ?? '') === $fingerprint ? true : new WP_Error('idlm_connector_conflict','Event identity conflict.'); }
                $existing=self::read_job($key);
                if ($existing) { return ($existing['fingerprint'] ?? '') === $fingerprint ? true : new WP_Error('idlm_connector_conflict','Event identity conflict.'); }
                if (IDLM_Secret_Store::stored($key) !== '') { return new WP_Error('idlm_connector_unreadable','Intake record is unreadable.'); }
                $job=array('provider'=>$provider,'payload'=>$payload,'fingerprint'=>$fingerprint,'generation'=>$generation,'attempts'=>0,'state'=>'queued','created_at'=>time(),'next_at'=>time());
                return self::write_job($key,$job) ? true : new WP_Error('idlm_connector_store','Intake could not be saved.');
            } finally { self::unlock($lock); }
        });
    }

    public static function tick() {
        return IDLM_Recovery_Guard::run(static function() {
            global $wpdb;
            IDLM_Facebook_Connect::pull();
            $cursor=self::option('idlm_connector_cursor',''); if (!is_string($cursor)) { return false; }
            // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- REL007-SQL-0545: tick(): fixed prefixed options/lead tables and hashed job/erasure keys; LIKE stem escaped, cursor/IDs/marker values prepared; job/engine/erasure/lock checks deliberately read current state.
            $keys=$wpdb->get_col($wpdb->prepare("SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s AND option_name > %s ORDER BY option_name LIMIT 10",$wpdb->esc_like(self::JOB).'%', $cursor));
            // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
            if ($wpdb->last_error) { return false; }
            foreach ($keys as $key) { self::process($key); }
            update_option('idlm_connector_cursor',count($keys) === 10 ? end($keys) : '',false);
            self::catch_up();
            return true;
        });
    }

    public static function process($key) {
        if (!is_string($key) || !preg_match('/^idlm_connector_job_[a-f0-9]{64}$/D',$key)) { return false; }
        return IDLM_Recovery_Guard::run(static function() use ($key) {
            $lock=self::lock($key); if (!$lock) { return false; }
            try {
                $job=self::read_job($key);
                if (!is_array($job) || !isset($job['provider'],$job['payload'],$job['fingerprint'],$job['generation'],$job['attempts'],$job['state'],$job['created_at'],$job['next_at'])) { return false; }
                $receipt=self::option(self::RECEIPT.substr($key,strlen(self::JOB)),false);
                if (is_wp_error($receipt)) { return false; }
                if ($receipt !== false) { return is_array($receipt) && ($receipt['fingerprint'] ?? '') === $job['fingerprint'] ? delete_option($key) : false; }
                if ($job['generation'] !== IDLM_Recovery_Guard::generation() || $job['created_at'] < time()-7*DAY_IN_SECONDS) { return self::finish($key,$job,'expired'); }
                if (is_wp_error(self::option('idlm_meta_lead_connector',array()))) { return false; }
                $interakt_enabled=self::option('idlm_interakt_enabled','0');
                $interakt_capture=self::option('idlm_interakt_lead_capture','0');
                if (is_wp_error($interakt_enabled) || is_wp_error($interakt_capture)) { return false; }
                $config=self::config();
                $enabled=$job['provider'] === 'meta' ? (($config['enabled'] ?? '') === '1' && ($job['payload']['page_id'] ?? '') === ($config['page_id'] ?? '') && in_array($job['payload']['form_id'] ?? '',$config['forms'] ?? array(),true)) : ($interakt_enabled === '1' && $interakt_capture === '1');
                if (!$enabled) { return self::finish($key,$job,'cancelled'); }
                if ($job['state'] === 'failed' || $job['next_at'] > time()) { return false; }
                $job['attempts']++; $job['next_at']=time()+300;
                if (!self::write_job($key,$job)) { return false; }
                $result=$job['provider'] === 'meta' ? self::import_meta($job['payload'],$job['created_at']) : self::import_interakt($job['payload'],$job['created_at']);
                if (is_wp_error($result) && $result->get_error_code() === 'idlm_connector_erased') { return self::finish($key,$job,'cancelled'); }
                if ($result && !is_wp_error($result)) { return self::finish($key,$job,is_array($result) && !empty($result['requires_duplicate_review']) ? 'review' : 'imported'); }
                $job['state']=$job['attempts'] >= self::MAX_ATTEMPTS ? 'failed' : 'queued';
                $job['next_at']=time()+min(3600,60*(2 ** $job['attempts']));
                self::write_job($key,$job); self::status($job['provider'],'retry_required');
                return false;
            } finally { self::unlock($lock); }
        });
    }

    private static function finish($key,$job,$outcome) {
        $receipt_key=self::RECEIPT.substr($key,strlen(self::JOB));
        $receipt=array('fingerprint'=>$job['fingerprint'],'outcome'=>$outcome);
        update_option($receipt_key,$receipt,false);
        if (self::option($receipt_key) !== $receipt) { return false; }
        delete_option($key);
        self::status($job['provider'],$outcome);
        return true;
    }
    private static function status($provider,$state) { update_option('idlm_connector_last_status',array('provider'=>$provider,'state'=>$state,'at'=>time()),false); }

    public static function retry_failed() {
        global $wpdb;
        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- REL007-SQL-0546: retry_failed(): fixed prefixed options/lead tables and hashed job/erasure keys; LIKE stem escaped, cursor/IDs/marker values prepared; job/engine/erasure/lock checks deliberately read current state.
        $keys=$wpdb->get_col($wpdb->prepare("SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s ORDER BY option_name LIMIT 100",$wpdb->esc_like(self::JOB).'%'));
        // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
        foreach ($keys as $key) {
            $lock=self::lock($key); if (!$lock) { continue; }
            try {
                $job=self::read_job($key);
                if (is_array($job) && ($job['state'] ?? '') === 'failed') { $job['state']='queued'; $job['attempts']=0; $job['next_at']=time(); self::write_job($key,$job); }
            } finally { self::unlock($lock); }
        }
    }

    public static function graph($path,$query=array()) {
        if (IDLM_Facebook_Connect::hosted()) { return IDLM_Facebook_Connect::graph($path,$query); }
        $token=self::option('idlm_meta_lead_access_token','');
        if (!is_string($token) || $token === '' || !preg_match('#^[1-9][0-9]{0,31}(?:/(?:leads|subscribed_apps))?$#D',$path)) { return new WP_Error('idlm_connector_config','Lead access token or resource is unavailable.'); }
        $url='https://graph.facebook.com/v25.0/'.$path;
        if ($query) { $url=add_query_arg($query,$url); }
        $response=IDLM_Secret_Store::request($url,array('headers'=>array('Authorization'=>'Bearer '.$token),'timeout'=>10,'limit_response_size'=>524288),array($token),'GET');
        if (is_wp_error($response)) { return new WP_Error('idlm_connector_api','Meta lead retrieval failed; check connectivity and lead permissions.'); }
        $data=json_decode(wp_remote_retrieve_body($response),true,32);
        if (wp_remote_retrieve_response_code($response)!==200 || !is_array($data) || isset($data['error'])) { return new WP_Error('idlm_connector_api','Meta refused lead access. Check the token, Page, form and lead permissions.'); }
        return $data;
    }

    public static function map_meta($record,$mappings=array()) {
        if (!is_array($record) || !self::id($record['id'] ?? null) || !is_array($record['field_data'] ?? null)) { return new WP_Error('idlm_connector_lead','Invalid lead record.'); }
        $answers=array();
        foreach ($record['field_data'] as $field) {
            if (!is_array($field) || !is_string($field['name'] ?? null) || !is_array($field['values'] ?? null)) { return new WP_Error('idlm_connector_lead','Invalid lead answer.'); }
            $values=array(); foreach ($field['values'] as $value) { if (!is_string($value)) { return new WP_Error('idlm_connector_lead','Invalid lead answer value.'); } $values[]=sanitize_text_field($value); }
            $answers[$field['name']]=implode(', ',$values);
        }
        $data=array('source'=>'Meta Ads','stage'=>'New','fb_lead_id'=>$record['id']);
        foreach (self::mapping_fields() as $field=>$default) { $name=$mappings[$field] ?? $default; $data[$field]=is_string($name) ? ($answers[$name] ?? '') : ''; }
        $full_name=$mappings['full_name'] ?? 'full_name';
        if ($data['first_name'] === '' && is_string($full_name) && !empty($answers[$full_name])) {
            $parts=preg_split('/\s+/',trim($answers[$full_name]),2); $data['first_name']=$parts[0]; if ($data['last_name']==='') { $data['last_name']=$parts[1] ?? ''; }
        }
        $data['email']=sanitize_email($data['email']);
        if ($data['phone'] === '' && $data['email'] === '') { return new WP_Error('idlm_connector_contact','Lead has no mapped email or phone.'); }
        return $data;
    }

    private static function import_meta($notification,$queued_at) {
        // Optional fields may be unavailable for a particular token, API version or lead.
        // Never let enrichment failure prevent the established lead retrieval path.
        $record=self::graph($notification['id'],array('fields'=>self::META_FULL_FIELDS));
        if (is_wp_error($record)) { $record=self::graph($notification['id'],array('fields'=>self::META_ATTRIBUTION_FIELDS)); }
        if (is_wp_error($record)) { $record=self::graph($notification['id'],array('fields'=>self::META_BASE_FIELDS)); }
        if (is_wp_error($record)) { return $record; }
        if (($record['id'] ?? '') !== $notification['id'] || ($record['form_id'] ?? '') !== $notification['form_id']) { return new WP_Error('idlm_connector_identity','Retrieved lead identity differs from the notification.'); }
        $config=self::config(); $data=self::map_meta($record,self::form_mapping($notification['form_id'],$config));
        if (is_wp_error($data)) { return $data; }
        $allowed=self::intake_allowed($data,$queued_at); if (is_wp_error($allowed)) { return $allowed; }
        $attribution=array('channel'=>'instant_form','page_id'=>$notification['page_id'],'form_id'=>$notification['form_id'],'lead_id'=>$record['id'],'created_time'=>is_string($record['created_time'] ?? null) ? $record['created_time'] : '', 'ad_id'=>self::id($record['ad_id'] ?? null));
        // The provider response includes form answers and must remain encrypted and
        // subject to the connector's existing lead erasure and backup policy.
        $stored_record=self::meta_lead_snapshot($record);
        if (is_wp_error($stored_record)) { return $stored_record; }
        // The lifecycle payload is also written to touchpoint/review logs. Keep the
        // full response exclusively in the encrypted per-lead attribution option.
        $private_attribution=$attribution; $private_attribution['meta_lead']=$stored_record;
        return IDLM_DB::upsert_inbound_lead($data,'meta',$attribution,array('route'=>true,'connector_attribution'=>$private_attribution,'connector_queued_at'=>$queued_at));
    }

    /** Retain only requested Lead fields; bound the encrypted snapshot before persistence. */
    public static function meta_lead_snapshot($record) {
        if (!is_array($record)) { return new WP_Error('idlm_connector_lead','Invalid lead record.'); }
        $snapshot=array_intersect_key($record,array_flip(explode(',',self::META_FULL_FIELDS)));
        $json=wp_json_encode($snapshot);
        if (!is_string($json) || strlen($json)>524288) { return new WP_Error('idlm_connector_lead','Meta lead record exceeds the supported size.'); }
        return $snapshot;
    }

    /** Only explicit referral objects are trusted as ad evidence; arbitrary traits are not. */
    public static function interakt_payload($customer,$message,$reply) {
        $base=IDLM_WhatsApp_Inbox::message('interakt',$message['id'] ?? null,$customer['channel_phone_number'] ?? null,$reply);
        if (!$base) { return false; }
        $traits=is_array($customer['traits'] ?? null) ? $customer['traits'] : array();
        $base['name']=is_string($traits['name'] ?? null) ? sanitize_text_field($traits['name']) : '';
        $metadata=is_array($message['meta_data'] ?? null) ? $message['meta_data'] : array();
        $referral=$message['referral'] ?? ($metadata['referral'] ?? array());
        if (!is_array($referral)) { return false; }
        $base['referral']=array();
        foreach (array('ctwa_clid','source_id','source_url') as $field) {
            $value=$referral[$field] ?? '';
            if (!is_string($value) || strlen($value)>2048) { return false; }
            $base['referral'][$field]=$field === 'source_url' ? esc_url_raw($value) : sanitize_text_field($value);
        }
        return $base;
    }

    private static function import_interakt($payload,$queued_at) {
        // Historical acknowledged message receipts must not recreate erased leads.
        $receipt=self::option('idlm_whatsapp_receipt_'.hash('sha256','interakt|'.$payload['id']),false);
        if (is_wp_error($receipt)) { return $receipt; }
        if ($receipt !== false) {
            $recorded=IDLM_WhatsApp_Inbox::record($payload);
            return !is_wp_error($recorded) && in_array($recorded['status'] ?? '',array('processed','replayed'),true) ? true : new WP_Error('idlm_connector_reply','Message receipt requires review.');
        }
        $config=self::config(); $referral=$payload['referral'];
        $attribution=array('channel'=>'whatsapp','ctwa_clid'=>$referral['ctwa_clid'],'page_id'=>$config['whatsapp_page_id'] ?? '', 'ad_id'=>$referral['source_id'],'source_url'=>$referral['source_url']);
        $names=preg_split('/\s+/',trim($payload['name']),2);
        $data=array('first_name'=>$names[0] ?? '', 'last_name'=>$names[1] ?? '', 'phone'=>$payload['phone'],'source'=>$referral['ctwa_clid'] !== '' ? 'WhatsApp Ads' : 'WhatsApp','stage'=>'New');
        $allowed=self::intake_allowed($data,$queued_at); if (is_wp_error($allowed)) { return $allowed; }
        return IDLM_DB::upsert_inbound_lead($data,'interakt',$attribution,array('route'=>true,'connector_attribution'=>$attribution,'connector_message'=>$payload,'connector_queued_at'=>$queued_at));
    }

    /** Attribution is committed with the lead, before deferred CAPI hooks run. */
    public static function remember_attribution($lead_id,$attribution,$created) {
        global $wpdb;
        if (!IDLM_Lead_Transaction::active() || !is_array($attribution)) { return false; }
        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- REL007-SQL-0547: remember_attribution(): fixed prefixed options/lead tables and hashed job/erasure keys; LIKE stem escaped, cursor/IDs/marker values prepared; job/engine/erasure/lock checks deliberately read current state.
        $engine=IDLM_Lead_Transaction::read($wpdb->get_var($wpdb->prepare('SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=%s',$wpdb->options)));
        // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
        if (strtoupper((string)$engine)!=='INNODB') { return false; }
        $key=self::ATTRIBUTION.absint($lead_id); $existing=self::attribution($lead_id);
        if (is_wp_error($existing)) { return false; }
        // Plain follow-up messages do not replace an existing acquisition source.
        if (!$created && ($attribution['channel'] ?? '')==='whatsapp' && empty($attribution['ctwa_clid'])) { return true; }
        if (is_array($existing) && ($existing['channel'] ?? '')==='instant_form' && ($attribution['channel'] ?? '')==='whatsapp') { return true; }
        return IDLM_Secret_Store::set($key,wp_json_encode($attribution));
    }
    public static function attribution($lead_id) {
        $key=self::ATTRIBUTION.absint($lead_id); $stored=IDLM_Secret_Store::stored($key);
        if ($stored === '') { return null; }
        $value=json_decode(IDLM_Secret_Store::get($key,'',true),true);
        return is_array($value) ? $value : new WP_Error('idlm_connector_attribution','Attribution is unavailable.');
    }
    private static function erasure_keys($data) {
        $keys=array();
        if (!empty($data['phone'])) { $keys[]='phone:'.IDLM_Lead_Lifecycle_Service::normalize_identity_phone($data['phone']); }
        if (!empty($data['email'])) { $keys[]='email:'.strtolower(trim($data['email'])); }
        if (!empty($data['fb_lead_id'])) { $keys[]='meta:'.$data['fb_lead_id']; }
        return array_map(static function($key) { return 'idlm_connector_erased_'.hash_hmac('sha256',$key,wp_salt('auth')); },$keys);
    }
    public static function intake_allowed($data,$queued_at) {
        foreach (self::erasure_keys($data) as $key) {
            $erased=self::option($key,0);
            if (is_wp_error($erased)) { return $erased; }
            if (is_numeric($erased) && (float)$erased >= $queued_at) { return new WP_Error('idlm_connector_erased','Pending input predates contact erasure.'); }
        }
        return true;
    }
    public static function erase_attribution($ids) {
        global $wpdb;
        foreach ($ids as $id) {
            // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- REL007-SQL-0548: erase_attribution(): fixed prefixed options/lead tables and hashed job/erasure keys; LIKE stem escaped, cursor/IDs/marker values prepared; job/engine/erasure/lock checks deliberately read current state.
            $lead=$wpdb->get_row($wpdb->prepare("SELECT phone,email,fb_lead_id FROM {$wpdb->prefix}idlm_leads WHERE id=%d FOR UPDATE",$id),ARRAY_A);
            // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
            if ($wpdb->last_error) { return false; }
            foreach (self::erasure_keys($lead ?: array()) as $marker) {
                $value=(string)microtime(true);
                // Do not let a rolled-back option-cache value suppress the retry's write.
                // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery -- REL007-SQL-0549: erase_attribution(): fixed prefixed options/lead tables and hashed job/erasure keys; LIKE stem escaped, cursor/IDs/marker values prepared; job/engine/erasure/lock checks deliberately read current state.
                $saved=$wpdb->query($wpdb->prepare("INSERT INTO {$wpdb->options} (option_name,option_value,autoload) VALUES (%s,%s,'no') ON DUPLICATE KEY UPDATE option_value=VALUES(option_value),autoload='no'",$marker,$value));
                // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery
                wp_cache_delete($marker,'options'); wp_cache_delete('notoptions','options');
                if (false === $saved || (string)self::option($marker) !== $value) { return false; }
            }
            $key=self::ATTRIBUTION.absint($id);
            // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery -- REL007-SQL-0550: erase_attribution(): fixed prefixed options/lead tables and hashed job/erasure keys; LIKE stem escaped, cursor/IDs/marker values prepared; job/engine/erasure/lock checks deliberately read current state.
            if (false === $wpdb->delete($wpdb->options,array('option_name'=>$key))) { return false; }
            // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery
            wp_cache_delete($key,'options');
        }
        return true;
    }

    /** One form/page of results per tick; use cursors, never follow provider URLs. */
    private static function catch_up() {
        $config=self::config(); if (($config['enabled'] ?? '')!=='1' || empty($config['forms'])) { return; }
        $lock=self::lock('catchup'); if (!$lock) { return; }
        try {
            $index=(int)self::option('idlm_connector_form_index',0); $form=$config['forms'][$index % count($config['forms'])];
            $key='idlm_connector_scan_'.$form; $scan=self::option($key,array()); if (!is_array($scan)) { return; }
            if (($scan['next_at'] ?? 0)>time()) { update_option('idlm_connector_form_index',$index+1,false); return; }
            $since=max((int)($config['started_at'] ?? time()),(int)($scan['since'] ?? 0),time()-7*DAY_IN_SECONDS);
            $started=(int)($scan['started'] ?? time());
            $query=array('fields'=>'id','limit'=>25,'filtering'=>wp_json_encode(array(array('field'=>'time_created','operator'=>'GREATER_THAN','value'=>$since))));
            if (!empty($scan['after'])) { $query['after']=$scan['after']; }
            $data=self::graph($form.'/leads',$query);
            if (is_wp_error($data) || !is_array($data['data'] ?? null)) {
                $failures=(int)($scan['failures'] ?? 0)+1;
                update_option($key,array('since'=>$since,'started'=>$started,'after'=>$failures >= 3 ? '' : ($scan['after'] ?? ''),'failures'=>$failures,'next_at'=>time()+900),false);
                update_option('idlm_connector_form_index',$index+1,false);
                self::status('meta','catchup_failed'); return;
            }
            foreach ($data['data'] as $lead) {
                if (!self::id($lead['id'] ?? null)) { return; }
                $saved=self::enqueue('meta',$lead['id'],array('id'=>$lead['id'],'page_id'=>$config['page_id'],'form_id'=>$form));
                if (is_wp_error($saved) || $saved !== true) { return; }
            }
            $after=$data['paging']['cursors']['after'] ?? '';
            $more=!empty($data['paging']['next']);
            if ($more && (!is_string($after) || $after === '' || strlen($after)>2048)) { return; }
            update_option($key,array('since'=>$more ? $since : max($since,$started-3600),'started'=>$more ? $started : time(),'after'=>$more ? $after : '', 'next_at'=>$more ? time() : time()+900),false);
            update_option('idlm_connector_form_index',$index+1,false);
        } finally { self::unlock($lock); }
    }

    public static function ajax_test() {
        if (!current_user_can('manage_options')) { wp_send_json_error(array('message'=>'Administrator access required.'),403); }
        check_ajax_referer('idlm_admin_nonce','nonce');
        $config=self::config();
        if (($config['mode'] ?? '') === 'hosted') {
            $result=IDLM_Facebook_Connect::request('check',array('connection'=>$config['connection'] ?? ''));
            if (is_wp_error($result)) { wp_send_json_error(array('message'=>$result->get_error_message()),400); }
            wp_send_json_success(array('message'=>'Facebook Page subscription and lead access verified.'));
        }
        if (!self::id($config['page_id'] ?? null) || !self::id($config['app_id'] ?? null) || empty($config['forms'])) { wp_send_json_error(array('message'=>'Save the Page, app, forms and lead token first.'),400); }
        $subscription=self::graph($config['page_id'].'/subscribed_apps',array('fields'=>'id,subscribed_fields','limit'=>100));
        if (is_wp_error($subscription)) { wp_send_json_error(array('message'=>$subscription->get_error_message()),400); }
        $found=false;
        foreach ($subscription['data'] ?? array() as $app) { if (($app['id'] ?? '')===$config['app_id'] && in_array('leadgen',$app['subscribed_fields'] ?? array(),true)) { $found=true; } }
        if (!$found) { wp_send_json_error(array('message'=>'The configured app is not subscribed to this Page’s leadgen events.'),400); }
        $form=self::graph($config['forms'][0],array('fields'=>'id,page'));
        if (is_wp_error($form) || ($form['page']['id'] ?? '')!==$config['page_id']) { wp_send_json_error(array('message'=>'The first form is unavailable or belongs to a different Page.'),400); }
        $result=self::graph($config['forms'][0].'/leads',array('fields'=>'id','limit'=>1));
        if (is_wp_error($result)) { wp_send_json_error(array('message'=>$result->get_error_message()),400); }
        wp_send_json_success(array('message'=>'Page subscription and first-form lead access verified. Send a test lead to validate mapping and delivery.'));
    }
}
