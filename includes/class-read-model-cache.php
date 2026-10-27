<?php
/**
 * Versioned read-model cache with object-cache and transient storage.
 *
 * @package InteriorDesignLeadManager
 * @author Shashi
 */

if (!defined('ABSPATH')) {
    exit;
}

final class IDLM_Read_Model_Cache {
    const GROUP = 'idlm_read_models';
    const GENERATION_OPTION = 'idlm_read_cache_generation';
    const DEFAULT_TTL = 60;
    const DEFAULT_STALE_TTL = 180;
    const LOCK_TTL = 30;

    private static $initialized = false;

    /**
     * Register cache invalidation for aggregate-affecting configuration and retention work.
     */
    public static function init() {
        if (self::$initialized) {
            return;
        }
        self::$initialized = true;

        add_action('added_option', array(__CLASS__, 'invalidate_on_option_change'), 10, 2);
        add_action('updated_option', array(__CLASS__, 'invalidate_on_option_change'), 10, 3);
        add_action('deleted_option', array(__CLASS__, 'invalidate_on_option_change'), 10, 1);
        add_action('added_user_meta', array(__CLASS__, 'invalidate_on_user_access_change'), 10, 4);
        add_action('updated_user_meta', array(__CLASS__, 'invalidate_on_user_access_change'), 10, 4);
        add_action('deleted_user_meta', array(__CLASS__, 'invalidate_on_user_access_change'), 10, 4);
        add_action(IDLM_Data_Retention::HOOK, array(__CLASS__, 'invalidate_after_retention'), 20);
    }

    /**
     * Cache an expensive read while retaining a stale value for lock contention.
     *
     * @param string   $namespace Logical read-model name.
     * @param array    $context   Authorization-safe query context.
     * @param callable $builder   Database read callback.
     * @param int      $ttl       Fresh lifetime in seconds.
     * @param int      $stale_ttl Maximum stored lifetime in seconds.
     * @return mixed
     */
    public static function remember($namespace, $context, $builder, $ttl = self::DEFAULT_TTL, $stale_ttl = self::DEFAULT_STALE_TTL) {
        $ttl = max(1, absint($ttl));
        $stale_ttl = max($ttl, absint($stale_ttl));
        $key = self::cache_key($namespace, $context);
        $entry = self::get($key);
        $now = time();

        if (self::is_entry($entry) && $entry['fresh_until'] >= $now) {
            return $entry['value'];
        }

        $has_lock = self::acquire_lock($key);
        if (!$has_lock) {
            if (self::is_entry($entry) && $entry['stale_until'] >= $now) {
                return $entry['value'];
            }

            // A first-fill cache miss must remain correct even if the lock store fails.
            return call_user_func($builder);
        }

        try {
            // A prior request may have completed between the initial read and lock acquisition.
            $entry = self::get($key);
            if (self::is_entry($entry) && $entry['fresh_until'] >= $now) {
                return $entry['value'];
            }

            $value = call_user_func($builder);
            self::set($key, array(
                'value' => $value,
                'fresh_until' => $now + $ttl,
                'stale_until' => $now + $stale_ttl,
            ), $stale_ttl);
            return $value;
        } finally {
            self::release_lock($key);
        }
    }

    /**
     * Change the generation token so all prior cache entries become unreachable.
     */
    public static function invalidate($reason = '') {
        $generation = sprintf('%.6F-%s', microtime(true), wp_generate_password(12, false, false));
        update_option(self::GENERATION_OPTION, $generation, false);
        return $generation;
    }

    /**
     * Invalidate only for settings that affect dashboard values or visibility.
     */
    public static function invalidate_on_option_change($option) {
        $options = array(
            'idlm_custom_stages',
            'idlm_kanban_stage_totals',
            'idlm_currency_code',
            'idlm_monthly_lead_target',
            'idlm_monthly_conversion_target',
            'idlm_reps_see_all_leads',
            'idlm_role_lead_scopes',
        );
        global $wpdb;
        if (in_array((string) $option, $options, true) || (string) $option === $wpdb->prefix . 'user_roles') {
            self::invalidate('settings');
        }
    }

    /** Capability, role and suspension edits must invalidate warmed reads. */
    public static function invalidate_on_user_access_change($meta_id, $user_id, $meta_key, $meta_value = null) {
        global $wpdb;
        if (in_array((string) $meta_key, array($wpdb->prefix . 'capabilities', 'idlm_crm_access_disabled'), true)) {
            self::invalidate('user_access');
        }
    }

    public static function invalidate_after_retention() {
        self::invalidate('retention');
    }

    public static function generation() {
        return (string) get_option(self::GENERATION_OPTION, '1');
    }

    private static function cache_key($namespace, $context) {
        $payload = array(
            'namespace' => sanitize_key($namespace),
            'generation' => self::generation(),
            'context' => $context,
        );
        return 'idlm_rc_' . md5(wp_json_encode($payload));
    }

    private static function is_entry($entry) {
        return is_array($entry)
            && array_key_exists('value', $entry)
            && isset($entry['fresh_until'], $entry['stale_until']);
    }

    private static function uses_object_cache() {
        return function_exists('wp_using_ext_object_cache') && wp_using_ext_object_cache();
    }

    private static function get($key) {
        if (self::uses_object_cache()) {
            return wp_cache_get($key, self::GROUP);
        }
        return get_transient($key);
    }

    private static function set($key, $value, $expiration) {
        if (self::uses_object_cache()) {
            return wp_cache_set($key, $value, self::GROUP, $expiration);
        }
        return set_transient($key, $value, $expiration);
    }

    private static function acquire_lock($key) {
        $lock_key = 'lock_' . md5($key);
        if (self::uses_object_cache()) {
            return wp_cache_add($lock_key, time() + self::LOCK_TTL, self::GROUP, self::LOCK_TTL);
        }

        $option = 'idlm_rc_lock_' . md5($key);
        $expires = absint(get_option($option, 0));
        if ($expires && $expires < time()) {
            delete_option($option);
        }
        return add_option($option, time() + self::LOCK_TTL, '', false);
    }

    private static function release_lock($key) {
        $lock_key = 'lock_' . md5($key);
        if (self::uses_object_cache()) {
            wp_cache_delete($lock_key, self::GROUP);
            return;
        }
        delete_option('idlm_rc_lock_' . md5($key));
    }
}
