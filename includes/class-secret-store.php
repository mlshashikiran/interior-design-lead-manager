<?php
/**
 * Encrypted storage and transport redaction for integration credentials.
 *
 * @package InteriorDesignLeadManager
 * @author Shashi
 */

if (!defined('ABSPATH')) {
    exit;
}

final class IDLM_Secret_Store {
    const PREFIX = 'idlm:v1:';

    private static $environment_constants = array(
        'idlm_webhook_api_key'       => 'IDLM_WEBHOOK_API_KEY',
        'idlm_mcp_api_key'           => 'IDLM_MCP_API_KEY',
        'idlm_google_gadm_password'  => 'IDLM_GOOGLE_GADM_PASSWORD',
        'idlm_meta_lead_access_token' => 'IDLM_META_LEAD_ACCESS_TOKEN',
        'idlm_meta_lead_app_secret' => 'IDLM_META_LEAD_APP_SECRET',
        'idlm_meta_lead_verify_token' => 'IDLM_META_LEAD_VERIFY_TOKEN',
        'idlm_meta_access_token' => 'IDLM_META_ACCESS_TOKEN',
        'idlm_google_api_secret' => 'IDLM_GOOGLE_API_SECRET',
        'idlm_linkedin_access_token' => 'IDLM_LINKEDIN_ACCESS_TOKEN',
        'idlm_interakt_api_key' => 'IDLM_INTERAKT_API_KEY',
        'idlm_interakt_webhook_secret' => 'IDLM_INTERAKT_WEBHOOK_SECRET',
        'idlm_meta_webhook_verify_token' => 'IDLM_META_WEBHOOK_VERIFY_TOKEN',
        'idlm_meta_webhook_app_secret' => 'IDLM_META_WEBHOOK_APP_SECRET',
    );

    /** Read a secret, transparently encrypting an existing plaintext option. */
    public static function get($option_name, $default = '', $fresh = false) {
        $option_name = sanitize_key($option_name);
        $fresh = $fresh || self::is_platform_secret($option_name);
        $constant = self::$environment_constants[$option_name] ?? '';
        if ('' !== $constant && defined($constant)) {
            return trim((string) constant($constant));
        }

        $stored = $fresh ? self::stored($option_name) : get_option($option_name, '');
        if (!is_string($stored)) { return $default; }
        if ('' === $stored) {
            return $default;
        }
        if (0 !== strpos($stored, self::PREFIX)) {
            // Never use a plaintext credential unless its encrypted replacement was verified.
            return self::migrate($option_name, $stored) ? self::get($option_name, $default, true) : $default;
        }

        $plaintext = self::decrypt(substr($stored, strlen(self::PREFIX)));
        return false === $plaintext ? $default : $plaintext;
    }

    /** Encrypt and persist a secret; environment-owned secrets are read-only. */
    public static function set($option_name, $plaintext) {
        $option_name = sanitize_key($option_name);
        $constant = self::$environment_constants[$option_name] ?? '';
        if ('' !== $constant && defined($constant)) {
            return false;
        }
        $encrypted = self::encrypt((string) $plaintext);
        if (false === $encrypted) { return false; }
        $value = self::PREFIX . $encrypted;
        update_option($option_name, $value, false);
        return self::stored($option_name) === $value;
    }

    public static function is_environment_managed($option_name) {
        $constant = self::$environment_constants[sanitize_key($option_name)] ?? '';
        return '' !== $constant && defined($constant);
    }

    /** Registered platform secrets, also used by migration and write-only settings. */
    public static function platform_options() {
        return array('idlm_meta_lead_access_token','idlm_meta_lead_app_secret','idlm_meta_lead_verify_token','idlm_meta_access_token','idlm_google_api_secret','idlm_linkedin_access_token','idlm_interakt_api_key','idlm_interakt_webhook_secret','idlm_meta_webhook_verify_token','idlm_meta_webhook_app_secret');
    }

    public static function is_platform_secret($name) {
        return in_array($name, self::platform_options(), true);
    }

    /** Primary read: cached credentials must never survive revocation. Null means read failure. */
    public static function stored($name) {
        global $wpdb;
        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- REL007-SQL-0166: stored(): only prefixed options and the known Action Scheduler table are accessed; name/hook/cursor values are bound, optional extended_args comes from a fixed column check; credential/job state is deliberately fresh.
        $raw = $wpdb->get_var($wpdb->prepare("SELECT option_value FROM {$wpdb->options} WHERE option_name = %s", $name));
        // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
        return $wpdb->last_error ? null : (null === $raw ? '' : $raw);
    }

    private static function invalidate($name) {
        wp_cache_delete($name, 'options');
        wp_cache_delete('alloptions', 'options');
        wp_cache_delete('notoptions', 'options');
    }

    /** Compare-and-swap avoids overwriting a concurrent rotation with old plaintext. */
    private static function migrate($name, $old) {
        global $wpdb;
        $encrypted = self::encrypt($old);
        if (false === $encrypted) { return false; }
        $value = self::PREFIX . $encrypted;
        // wpdb diagnostics can include the plaintext comparison; never publish that SQL.
        $suppressed = $wpdb->suppress_errors(true);
        try {
            // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- REL007-SQL-0167: migrate(): only prefixed options and the known Action Scheduler table are accessed; name/hook/cursor values are bound, optional extended_args comes from a fixed column check; credential/job state is deliberately fresh.
            $saved = $wpdb->update($wpdb->options, array('option_value'=>$value,'autoload'=>'no'), array('option_name'=>$name,'option_value'=>$old));
            // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
        } finally { $wpdb->suppress_errors($suppressed); }
        self::invalidate($name);
        if (false === $saved) { return false; }
        $current = self::stored($name);
        return is_string($current) && strpos($current, self::PREFIX) === 0 && false !== self::decrypt(substr($current, strlen(self::PREFIX)));
    }

    /** Bounded, repeatable migration: no completion marker advances on failure. */
    public static function migrate_platforms() {
        $ready = true;
        foreach (self::platform_options() as $name) {
            $raw = self::stored($name);
            if (null === $raw) { $ready = false; continue; }
            if ('' !== $raw && self::is_environment_managed($name)) {
                if (0 !== strpos($raw, self::PREFIX) && !self::migrate($name, $raw)) { $ready = false; }
            } elseif ('' !== $raw && '' === self::get($name, '', true)) {
                $current = self::stored($name);
                if (!is_string($current) || strpos($current, self::PREFIX) !== 0 || false === self::decrypt(substr($current, strlen(self::PREFIX)))) { $ready = false; }
            }
        }
        return $ready;
    }

    /** Scrub copied-key legacy queues in bounded batches; retain opaque current work.
     * AS action IDs/status/schedules remain, but obsolete refused payloads lose secrets.
     */
    public static function scrub_legacy_queues() {
        global $wpdb;
        $ready = true;
        $hook = 'idlm_async_interakt_track_event';
        if (function_exists('_get_cron_array')) {
            $removed = 0;
            foreach ((array)_get_cron_array() as $time => $hooks) {
                foreach ((array)($hooks[$hook] ?? array()) as $event) {
                    $args = $event['args'] ?? array();
                    if (count($args) === 1 && is_string($args[0] ?? null) && preg_match('/^idlm_interakt_dispatch_[a-f0-9]{64}$/D', $args[0])) { continue; }
                    if (++$removed > 100) { return false; }
                    if (!wp_unschedule_event($time, $hook, $args)) { $ready = false; }
                }
            }
        }
        $table = $wpdb->prefix . 'actionscheduler_actions';
        $suppressed = $wpdb->suppress_errors(true);
        try {
            // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- REL007-SQL-0168: scrub_legacy_queues(): only prefixed options and the known Action Scheduler table are accessed; name/hook/cursor values are bound, optional extended_args comes from a fixed column check; credential/job state is deliberately fresh.
            $exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $wpdb->esc_like($table)));
            // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
            if ($wpdb->last_error) { return false; }
            if (!$exists) { return $ready; }
            // phpcs:disable PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- REL007-SQL-0169: scrub_legacy_queues(): only prefixed options and the known Action Scheduler table are accessed; name/hook/cursor values are bound, optional extended_args comes from a fixed column check; credential/job state is deliberately fresh.
            $columns = $wpdb->get_col("SHOW COLUMNS FROM `{$table}`", 0);
            // phpcs:enable PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            if ($wpdb->last_error || array_diff(array('action_id','hook','args'), $columns)) { return false; }
            $cursor_name = 'idlm_secret_queue_scrub_cursor';
            $cursor = (int)get_option($cursor_name, 0);
            // phpcs:disable PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- REL007-SQL-0170: scrub_legacy_queues(): only prefixed options and the known Action Scheduler table are accessed; name/hook/cursor values are bound, optional extended_args comes from a fixed column check; credential/job state is deliberately fresh.
            $max = $wpdb->get_var("SELECT MAX(action_id) FROM `{$table}`");
            // phpcs:enable PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            if ($wpdb->last_error) { return false; }
            if ($cursor > (int)$max) { $cursor = 0; }
            // phpcs:disable PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- REL007-SQL-0171: scrub_legacy_queues(): only prefixed options and the known Action Scheduler table are accessed; name/hook/cursor values are bound, optional extended_args comes from a fixed column check; credential/job state is deliberately fresh.
            $rows = $wpdb->get_results($wpdb->prepare("SELECT * FROM `{$table}` WHERE hook=%s AND action_id>%d ORDER BY action_id LIMIT 100", $hook, $cursor), ARRAY_A);
            // phpcs:enable PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            if ($wpdb->last_error) { return false; }
            foreach ($rows as $row) {
                $args = json_decode($row['args'], true);
                $extended = !empty($row['extended_args']);
                $current = is_array($args) && count($args) === 1 && is_string($args[0] ?? null) && preg_match('/^idlm_interakt_dispatch_[a-f0-9]{64}$/D', $args[0]);
                if (!$current || $extended) {
                    $data = array('args'=>'["legacy_redacted"]');
                    $where = array('action_id'=>$row['action_id'],'args'=>$row['args']);
                    if (in_array('extended_args', $columns, true)) { $data['extended_args'] = null; $where['extended_args'] = $row['extended_args']; }
                    // phpcs:disable PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQL.NotPrepared -- REL007-SQL-0172: scrub_legacy_queues(): only prefixed options and the known Action Scheduler table are accessed; name/hook/cursor values are bound, optional extended_args comes from a fixed column check; credential/job state is deliberately fresh.
                    if (false === $wpdb->update($table, $data, $where)) { return false; }
                    $check = $wpdb->get_row($wpdb->prepare("SELECT args" . (in_array('extended_args',$columns,true) ? ',extended_args' : '') . " FROM `{$table}` WHERE action_id=%d",$row['action_id']), ARRAY_A);
                    // phpcs:enable PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQL.NotPrepared
                    if ($wpdb->last_error || ($check && ($check['args'] !== $data['args'] || !empty($check['extended_args'])))) { return false; }
                }
                $cursor = (int)$row['action_id'];
            }
            update_option($cursor_name, $cursor, false);
            return $ready && (int)self::stored($cursor_name) === $cursor;
        } finally { $wpdb->suppress_errors($suppressed); }
    }

    /** Scrub provider echoes and URL/auth diagnostics before local persistence. */
    public static function redact($value, $secrets = array()) {
        if (is_array($value)) {
            foreach ($value as $key => $item) {
                $value[$key] = preg_match('/(authorization|access.?token|api.?secret|api.?key|password)/i', (string)$key) ? '[REDACTED]' : self::redact($item, $secrets);
            }
            return $value;
        }
        if (!is_string($value)) { return $value; }
        foreach ($secrets as $secret) {
            if (!is_string($secret) || '' === $secret) { continue; }
            $value = str_replace(array($secret,rawurlencode($secret),urlencode($secret),substr(json_encode($secret),1,-1),base64_encode($secret.':'),base64_encode($secret)), '[REDACTED]', $value);
        }
        $value = preg_replace('/([?&](?:access_token|api_secret|api_key|token|key|password)=)[^&\s"<>]*/i', '$1[REDACTED]', $value);
        return preg_replace('/(Authorization\s*[:=]\s*(?:Bearer|Basic)\s+)[^\s"<>]+/i', '$1[REDACTED]', $value);
    }

    /** No redirects with credentials; provider errors never carry credentials into logs. */
    public static function request($url, $args, $secrets, $method = 'POST') {
        $args['redirection'] = 0;
        try { $response = 'GET' === $method ? wp_remote_get($url, $args) : wp_remote_post($url, $args); }
        catch (Throwable $error) { return new WP_Error('idlm_provider_transport', 'Provider request failed; delivery may be uncertain.'); }
        if (is_wp_error($response)) { return new WP_Error('idlm_provider_transport', self::redact($response->get_error_message(), $secrets)); }
        return self::redact($response, $secrets);
    }

    private static function key() {
        if (defined('IDLM_SECRET_KEY') && '' !== (string) IDLM_SECRET_KEY) {
            return hash('sha256', (string) IDLM_SECRET_KEY, true);
        }
        $material = '';
        foreach (array('AUTH_KEY', 'SECURE_AUTH_KEY', 'LOGGED_IN_KEY', 'NONCE_KEY') as $constant) {
            if (defined($constant)) {
                $material .= (string) constant($constant);
            }
        }
        if (function_exists('wp_salt')) {
            $material .= (string) wp_salt('auth');
        }
        if ('' === $material) {
            return false;
        }
        return hash('sha256', 'idlm-secret-store|' . $material, true);
    }

    /** PHP 7.4 still reports disabled functions as existing. Check both gates. */
    private static function crypto_available() {
        $disabled = array_map('trim', explode(',', strtolower((string) ini_get('disable_functions'))));
        foreach (array('openssl_encrypt', 'openssl_decrypt', 'openssl_get_cipher_methods') as $function) {
            if (!function_exists($function) || in_array($function, $disabled, true)) {
                return false;
            }
        }
        return in_array('aes-256-gcm', openssl_get_cipher_methods(), true);
    }

    private static function encrypt($plaintext) {
        if (!self::crypto_available() || false === self::key()) {
            return false;
        }
        try { $iv = random_bytes(12); } catch (Throwable $error) { return false; }
        $tag = '';
        $ciphertext = openssl_encrypt($plaintext, 'aes-256-gcm', self::key(), OPENSSL_RAW_DATA, $iv, $tag);
        if (false === $ciphertext) {
            return false;
        }
        return base64_encode($iv . $tag . $ciphertext);
    }

    private static function decrypt($payload) {
        if (!self::crypto_available() || false === self::key()) {
            return false;
        }
        $decoded = base64_decode($payload, true);
        if (false === $decoded || strlen($decoded) < 28) {
            return false;
        }
        $iv = substr($decoded, 0, 12);
        $tag = substr($decoded, 12, 16);
        return openssl_decrypt(substr($decoded, 28), 'aes-256-gcm', self::key(), OPENSSL_RAW_DATA, $iv, $tag);
    }
}
