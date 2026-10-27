<?php
/** Atomic routing counters and locks. @author Shashi */
if (!defined('ABSPATH')) { exit; }

final class IDLM_Routing_State {
    const VERSION = '1.0.1';

    public static function install() {
        return IDLM_Schema_Migration::run(static function () {
            global $wpdb;
            $version = IDLM_Schema_Migration::version('idlm_routing_schema_version');
            if (null === $version) { return IDLM_Schema_Migration::error('routing_version_read'); }
            if ($version && version_compare($version, self::VERSION, '>')) { return IDLM_Schema_Migration::error('future_routing_version', 'CRM routing schema is newer than this plugin. Install a matching plugin version or recover the matching database backup.'); }
            require_once ABSPATH . 'wp-admin/includes/upgrade.php';
            $table = $wpdb->prefix . 'idlm_routing_state';
            $collate = $wpdb->get_charset_collate();
            $sql = "CREATE TABLE {$table} (
                state_key varchar(191) NOT NULL,
                state_value bigint(20) unsigned NOT NULL DEFAULT 0,
                updated_at datetime DEFAULT NULL,
                PRIMARY KEY  (state_key)
            ) ENGINE=InnoDB {$collate};";
            dbDelta($sql);
            $result = IDLM_Schema_Migration::verify(array($sql));
            return is_wp_error($result) ? $result : IDLM_Schema_Migration::publish('idlm_routing_schema_version', self::VERSION);
        });
    }

    public static function next_index($state_key, $modulus) {
        global $wpdb;
        $modulus = max(1, absint($modulus));
        $key = sanitize_key($state_key);
        $table = $wpdb->prefix . 'idlm_routing_state';
        $sql = $wpdb->prepare(
            // phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- REL007-SQL-0040: next_index(): fixed prefixed routing table; state key/time/lock values prepared; LAST_INSERT_ID and session locks must be read from the current connection.
            "INSERT INTO {$table} (state_key, state_value, updated_at) VALUES (%s, LAST_INSERT_ID(0), %s)
             ON DUPLICATE KEY UPDATE state_value = LAST_INSERT_ID(state_value + 1), updated_at = VALUES(updated_at)",
            // phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            $key, current_time('mysql')
        );
        // phpcs:disable PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared -- REL007-SQL-0041: next_index(): fixed prefixed routing table; state key/time/lock values prepared; LAST_INSERT_ID and session locks must be read from the current connection.
        if (false !== $wpdb->query($sql)) {
        // phpcs:enable PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared
            $index = $wpdb->get_var('SELECT LAST_INSERT_ID()');
            if (IDLM_Lead_Transaction::active()) { IDLM_Lead_Transaction::read($index); }
            return ((int) $index) % $modulus;
        }
        if (IDLM_Lead_Transaction::active()) { IDLM_Lead_Transaction::required(false); }
        $last = (int) get_option('idlm_routing_fallback_' . md5($key), -1);
        $next = ($last + 1) % $modulus;
        update_option('idlm_routing_fallback_' . md5($key), $next, false);
        return $next;
    }

    public static function acquire_lock($name, $timeout = 0) {
        global $wpdb;
        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- REL007-SQL-0042: acquire_lock(): fixed prefixed routing table; state key/time/lock values prepared; LAST_INSERT_ID and session locks must be read from the current connection.
        return 1 === (int) $wpdb->get_var($wpdb->prepare('SELECT GET_LOCK(%s, %d)', 'idlm_' . sanitize_key($name), absint($timeout)));
        // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
    }

    public static function release_lock($name) {
        global $wpdb;
        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- REL007-SQL-0043: release_lock(): fixed prefixed routing table; state key/time/lock values prepared; LAST_INSERT_ID and session locks must be read from the current connection.
        $wpdb->get_var($wpdb->prepare('SELECT RELEASE_LOCK(%s)', 'idlm_' . sanitize_key($name)));
        // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
    }
}
