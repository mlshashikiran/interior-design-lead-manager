<?php
/** Site-owned Facebook Login and Page selection. Builder: Shashi. */
if (!defined('ABSPATH')) { exit; }

final class IDLM_Facebook_Direct {
    const PENDING = 'idlm_facebook_pending_direct_';

    public static function init() {
        add_action('admin_post_idlm_facebook_callback', array(__CLASS__, 'callback'));
    }
    public static function callback_url() {
        return admin_url('admin-post.php?action=idlm_facebook_callback');
    }
    public static function ready() {
        $config = IDLM_Lead_Connector::config();
        return IDLM_Lead_Connector::id($config['app_id'] ?? '') !== ''
            && IDLM_Lead_Connector::id($config['login_config_id'] ?? '') !== ''
            && IDLM_Secret_Store::get('idlm_meta_lead_app_secret', '', true) !== '';
    }
    /** Display-only state for the initiating administrator's completed consent. */
    public static function authorized() {
        if (!current_user_can('manage_options')) { return false; }
        $pending = self::pending();
        return !is_wp_error($pending) && !empty($pending['token']);
    }
    private static function key() { return self::PENDING . get_current_user_id(); }
    private static function fingerprint() {
        $config = IDLM_Lead_Connector::config();
        return hash('sha256', ($config['app_id'] ?? '').'|'.($config['login_config_id'] ?? '').'|'.IDLM_Secret_Store::get('idlm_meta_lead_app_secret', '', true));
    }
    private static function pending() {
        $pending = json_decode(IDLM_Secret_Store::get(self::key(), '', true), true);
        if (!is_array($pending) || ($pending['expires'] ?? 0) < time()
            || ($pending['generation'] ?? null) !== IDLM_Recovery_Guard::generation()
            || !hash_equals($pending['session'] ?? '', hash('sha256', wp_get_session_token()))
            || !hash_equals($pending['app'] ?? '', self::fingerprint())
            || !hash_equals($pending['epoch'] ?? '', IDLM_Secret_Store::get(self::PENDING.'epoch', '', true))) {
            return new WP_Error('idlm_facebook_session', 'Connection session expired. Connect Facebook again.');
        }
        return $pending;
    }
    private static function save($pending) {
        return IDLM_Secret_Store::set(self::key(), wp_json_encode($pending));
    }
    private static function failure() {
        return new WP_Error('idlm_facebook_direct', 'Facebook connection failed. Check the app configuration, Page access and lead permissions, then try again.');
    }
    /** Fixed Graph resources only; credentials stay in headers or POST bodies. */
    private static function meta($path, $query, $token = '', $method = 'GET') {
        if (!preg_match('#^(?:oauth/access_token|me/accounts|[1-9][0-9]{0,31}/(?:leadgen_forms|leads|subscribed_apps))$#D', $path)) { return self::failure(); }
        $args = array('timeout'=>20, 'redirection'=>0, 'limit_response_size'=>1048576);
        if ($token !== '') { $args['headers'] = array('Authorization'=>'Bearer '.$token); }
        $url = 'https://graph.facebook.com/v25.0/'.$path;
        if ($method === 'GET') { $url = add_query_arg($query, $url); } else { $args['body'] = $query; }
        // OAuth/Page responses contain new tokens; preserve those for encrypted storage.
        // Never surface raw provider errors or responses in diagnostics/browser output.
        try { $response = $method === 'GET' ? wp_remote_get($url, $args) : wp_remote_post($url, $args); }
        catch (Throwable $error) { return self::failure(); }
        if (is_wp_error($response)) { return self::failure(); }
        $data = json_decode(wp_remote_retrieve_body($response), true, 32);
        if (wp_remote_retrieve_response_code($response) !== 200 || !is_array($data) || isset($data['error'])) { return self::failure(); }
        return $data;
    }
    private static function locked($callback) {
        global $wpdb;
        $name = IDLM_Recovery_Guard::name('facebook_direct');
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Prepared site-scoped database session lock must be read live to serialize consent exchange and Page-token selection.
        if (1 !== (int)$wpdb->get_var($wpdb->prepare('SELECT GET_LOCK(%s,0)', $name))) {
            return new WP_Error('idlm_facebook_busy', 'A Facebook connection operation is already running. Try again.');
        }
        try { return $callback(); }
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Release the exact prepared session lock on every exit; lock state is not cacheable.
        finally { $wpdb->get_var($wpdb->prepare('SELECT RELEASE_LOCK(%s)', $name)); }
    }
    public static function exchange($input) {
        return self::locked(static function() use ($input) { return self::exchange_locked($input); });
    }
    private static function exchange_locked($input) {
        if (!current_user_can('manage_options')) { return self::failure(); }
        $pending = self::pending();
        if (is_wp_error($pending)) { return $pending; }
        $state = $input['state'] ?? null;
        if (!is_string($state) || !isset($pending['state']) || !hash_equals($pending['state'], $state)) { return self::failure(); }
        // The connection lock and persisted state consumption fence replay before HTTP.
        unset($pending['state']);
        if (!self::save($pending)) { return self::failure(); }
        $code = $input['code'] ?? null;
        if (!is_string($code) || $code === '' || strlen($code) > 4096 || isset($input['error'])) { return self::failure(); }
        $config = IDLM_Lead_Connector::config();
        $credentials = array('client_id'=>$config['app_id'], 'client_secret'=>IDLM_Secret_Store::get('idlm_meta_lead_app_secret', '', true));
        $result = self::meta('oauth/access_token', array_merge($credentials, array('redirect_uri'=>self::callback_url(), 'code'=>$code)), '', 'POST');
        if (is_wp_error($result) || !is_string($result['access_token'] ?? null) || $result['access_token'] === '') { return self::failure(); }
        $extended = self::meta('oauth/access_token', array_merge($credentials, array('grant_type'=>'fb_exchange_token', 'fb_exchange_token'=>$result['access_token'])), '', 'POST');
        if (is_wp_error($extended) || !is_string($extended['access_token'] ?? null) || $extended['access_token'] === '') { return self::failure(); }
        if (is_wp_error(self::pending())) { return self::failure(); }
        $pending['token'] = $extended['access_token'];
        if (!self::save($pending)) { return self::failure(); }
        return array('return_url'=>$pending['return_url']);
    }
    public static function callback() {
        nocache_headers();
        header('Referrer-Policy: no-referrer');
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- OAuth callback uses durable single-use state bound to administrator/session/app/epoch/recovery generation in exchange_locked(), consumed before code exchange.
        $result = self::exchange(wp_unslash($_GET));
        if (is_wp_error($result)) { wp_die(esc_html($result->get_error_message()), 'Facebook connection', array('response'=>400)); }
        wp_safe_redirect(add_query_arg('idlm_fb_return', '1', $result['return_url']));
        exit;
    }
    public static function operation($op, $input) {
        return self::locked(static function() use ($op, $input) { return self::operation_locked($op, $input); });
    }
    private static function operation_locked($op, $input) {
        if (!current_user_can('manage_options')) { return new WP_Error('idlm_facebook_forbidden', 'Administrator access required.'); }
        if ($op === 'start') {
            if (!self::ready()) { return new WP_Error('idlm_facebook_setup', 'Save this site’s Meta app ID, Facebook Login configuration ID and app secret first.'); }
            $requested = $input['return_url'] ?? '';
            if (!is_string($requested)) { return self::failure(); }
            $return = wp_validate_redirect($requested, admin_url('admin.php?page=idlm_settings'));
            $parts = wp_parse_url($requested); $home = wp_parse_url(home_url('/')); $callback = wp_parse_url(self::callback_url());
            if (($parts['scheme'] ?? '') !== 'https' || ($callback['scheme'] ?? '') !== 'https' || ($parts['host'] ?? '') !== ($home['host'] ?? '') || ($parts['port'] ?? 443) !== ($home['port'] ?? 443) || isset($parts['user']) || isset($parts['pass'])) {
                return new WP_Error('idlm_facebook_https', 'Facebook connection requires this CRM to use a public HTTPS URL.');
            }
            $generation = IDLM_Recovery_Guard::generation();
            if ($generation === null) { return self::failure(); }
            $state = bin2hex(random_bytes(32));
            if (!IDLM_Secret_Store::set(self::PENDING.'epoch', $state)) { return self::failure(); }
            $pending = array('epoch'=>$state, 'state'=>$state, 'expires'=>time()+900, 'generation'=>$generation, 'session'=>hash('sha256', wp_get_session_token()), 'app'=>self::fingerprint(), 'return_url'=>explode('#', $return, 2)[0]);
            if (!self::save($pending)) { return self::failure(); }
            $config = IDLM_Lead_Connector::config();
            return array('authorization_url'=>add_query_arg(array('client_id'=>$config['app_id'], 'config_id'=>$config['login_config_id'], 'redirect_uri'=>self::callback_url(), 'response_type'=>'code', 'state'=>$state), 'https://www.facebook.com/v25.0/dialog/oauth'));
        }
        if ($op === 'disconnect') {
            $config = IDLM_Lead_Connector::config(); $config['enabled'] = '0'; unset($config['connection']);
            update_option('idlm_meta_lead_connector', $config, false);
            if (IDLM_Lead_Connector::config() !== $config) { return self::failure(); }
            delete_option(self::key());
            delete_option(self::PENDING.'epoch');
            return array('disconnected'=>true);
        }
        if (!in_array($op, array('pages','forms','select'), true)) { return self::failure(); }
        $pending = self::pending();
        if (is_wp_error($pending)) { return $pending; }
        if (empty($pending['token'])) { return self::failure(); }
        $after = $input['after'] ?? '';
        if (!is_string($after) || strlen($after) > 2048) { return self::failure(); }
        if ($op === 'pages') {
            $result = self::meta('me/accounts', array('fields'=>'id,name,access_token', 'limit'=>50, 'after'=>$after), $pending['token']);
            if (is_wp_error($result)) { return $result; }
            $rows = array();
            foreach ($result['data'] ?? array() as $row) {
                if (!IDLM_Lead_Connector::id($row['id'] ?? null) || !is_string($row['name'] ?? null) || empty($row['access_token']) || !is_string($row['access_token'])) { continue; }
                $pending['pages'][$row['id']] = array('name'=>sanitize_text_field($row['name']), 'token'=>$row['access_token']);
                $rows[] = array('id'=>$row['id'], 'name'=>sanitize_text_field($row['name']));
            }
            if (count($pending['pages'] ?? array()) > 500 || !self::save($pending)) { return self::failure(); }
            return array('pages'=>$rows, 'after'=>self::cursor($result));
        }
        $page = IDLM_Lead_Connector::id($input['page_id'] ?? null);
        $saved = $pending['pages'][$page] ?? null;
        if (!$saved) { return self::failure(); }
        if ($op === 'forms') {
            $result = self::meta($page.'/leadgen_forms', array('fields'=>'id,name,status', 'limit'=>50, 'after'=>$after), $saved['token']);
            if (is_wp_error($result)) { return $result; }
            $rows = array();
            foreach ($result['data'] ?? array() as $row) {
                if (!IDLM_Lead_Connector::id($row['id'] ?? null) || !is_string($row['name'] ?? null) || ($row['status'] ?? '') !== 'ACTIVE') { continue; }
                $name = sanitize_text_field($row['name']); $pending['forms'][$page][$row['id']] = $name;
                $rows[] = array('id'=>$row['id'], 'name'=>$name);
            }
            if (count($pending['forms'][$page] ?? array()) > 1000 || !self::save($pending)) { return self::failure(); }
            return array('forms'=>$rows, 'after'=>self::cursor($result));
        }
        $forms = $input['forms'] ?? null;
        if (!is_array($forms) || count($forms) < 1 || count($forms) > 20) { return self::failure(); }
        $names = array();
        foreach ($forms as $form) {
            if (!IDLM_Lead_Connector::id($form) || !isset($pending['forms'][$page][$form])) { return self::failure(); }
            $names[$form] = $pending['forms'][$page][$form];
        }
        if (count($names) !== count($forms)) { return self::failure(); }
        if (IDLM_Secret_Store::is_environment_managed('idlm_meta_lead_access_token') || IDLM_Secret_Store::get('idlm_meta_lead_verify_token', '', true) === '') {
            return new WP_Error('idlm_facebook_credentials', 'Save a webhook verification token and remove the server-managed lead access token before using Facebook sign-in.');
        }
        foreach ($forms as $form) {
            if (is_wp_error(self::meta($form.'/leads', array('fields'=>'id', 'limit'=>1), $saved['token']))) { return self::failure(); }
        }
        $subscriptions = self::meta($page.'/subscribed_apps', array('fields'=>'id,subscribed_fields', 'limit'=>100), $saved['token']);
        if (is_wp_error($subscriptions)) { return $subscriptions; }
        if (!is_array($subscriptions['data'] ?? null)) { return self::failure(); }
        $fields = array('leadgen');
        $app_id = IDLM_Lead_Connector::config()['app_id'] ?? '';
        $found = false;
        foreach ($subscriptions['data'] as $subscription) {
            if (($subscription['id'] ?? '') !== $app_id) { continue; }
            $found = true;
            foreach ($subscription['subscribed_fields'] ?? array() as $field) {
                if (is_string($field) && preg_match('/^[a-z_]+$/D', $field)) { $fields[] = $field; }
            }
        }
        if (!$found && !empty($subscriptions['paging']['next'])) { return self::failure(); }
        // Preserve other Page webhook fields already used by this same Meta app.
        $result = self::meta($page.'/subscribed_apps', array('subscribed_fields'=>implode(',', array_unique($fields))), $saved['token'], 'POST');
        if (is_wp_error($result) || ($result['success'] ?? false) !== true) { return self::failure(); }
        if (is_wp_error(self::pending())) { return self::failure(); }
        // Pause intake before replacing credentials; failed persistence leaves sync stopped.
        $config = IDLM_Lead_Connector::config();
        $config['enabled'] = '0';
        update_option('idlm_meta_lead_connector', $config, false);
        if (IDLM_Lead_Connector::config() !== $config || !IDLM_Secret_Store::set('idlm_meta_lead_access_token', $saved['token'])) { return self::failure(); }
        $config = array_merge($config, array('enabled'=>'1', 'mode'=>'direct', 'connection'=>'direct', 'page_id'=>$page, 'forms'=>array_values($forms), 'page_name'=>$saved['name'], 'form_names'=>array_values($names), 'started_at'=>time()));
        update_option('idlm_meta_lead_connector', $config, false);
        if (IDLM_Lead_Connector::config() !== $config) { return self::failure(); }
        delete_option(self::key());
        return array('connected'=>true, 'page_name'=>$saved['name'], 'form_names'=>array_values($names));
    }
    private static function cursor($result) {
        $after = $result['paging']['cursors']['after'] ?? '';
        return !empty($result['paging']['next']) && is_string($after) && strlen($after) <= 2048 ? $after : '';
    }
}
