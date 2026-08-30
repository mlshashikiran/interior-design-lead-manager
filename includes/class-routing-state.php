<?php
/** Atomic routing counters and locks. @author Shashi */
if (!defined('ABSPATH')) { exit; }

final class IDLM_Routing_State {
    public static function install() {
        global $wpdb;
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        $table = $wpdb->prefix . 'idlm_routing_state';
        $collate = $wpdb->get_charset_collate();
        dbDelta("CREATE TABLE {$table} (
            state_key varchar(191) NOT NULL,
            state_value bigint(20) unsigned NOT NULL DEFAULT 0,
            updated_at datetime DEFAULT NULL,
            PRIMARY KEY  (state_key)
        ) {$collate};");
        update_option('idlm_routing_schema_version', '1.0.0', false);
    }

    public static function next_index($state_key, $modulus) {
        global $wpdb;
        $modulus = max(1, absint($modulus));
        $key = sanitize_key($state_key);
        $table = $wpdb->prefix . 'idlm_routing_state';
        $sql = $wpdb->prepare(
            "INSERT INTO {$table} (state_key, state_value, updated_at) VALUES (%s, LAST_INSERT_ID(0), %s)
             ON DUPLICATE KEY UPDATE state_value = LAST_INSERT_ID(state_value + 1), updated_at = VALUES(updated_at)",
            $key, current_time('mysql')
        );
        if (false !== $wpdb->query($sql)) {
            return ((int) $wpdb->get_var('SELECT LAST_INSERT_ID()')) % $modulus;
        }
        $last = (int) get_option('idlm_routing_fallback_' . md5($key), -1);
        $next = ($last + 1) % $modulus;
        update_option('idlm_routing_fallback_' . md5($key), $next, false);
        return $next;
    }

    public static function acquire_lock($name, $timeout = 0) {
        global $wpdb;
        return 1 === (int) $wpdb->get_var($wpdb->prepare('SELECT GET_LOCK(%s, %d)', 'idlm_' . sanitize_key($name), absint($timeout)));
    }

    public static function release_lock($name) {
        global $wpdb;
        $wpdb->get_var($wpdb->prepare('SELECT RELEASE_LOCK(%s)', 'idlm_' . sanitize_key($name)));
    }
}
