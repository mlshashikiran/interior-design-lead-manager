<?php
/** Cooperative recovery/worker fence on one WordPress database. Builder: Shashi. */
if (!defined('ABSPATH')) { exit; }

final class IDLM_Recovery_Guard {
    const GENERATION = 'idlm_recovery_generation';
    const WORKER_SLOTS = 64;
    private static $depth = 0;
    private static $slot = null;
    private static $epoch = null;
    private static $request = false;

    public static function name($kind) {
        global $wpdb;
        return 'idlm_rg_' . substr(hash('sha256', DB_NAME . '|' . $wpdb->prefix), 0, 24) . '_' . $kind;
    }
    private static function lock($name, $timeout = 0) {
        global $wpdb;
        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- REL007-SQL-0075: lock(): WordPress options identifier and fixed control SQL; generation/name values prepared; named-lock ownership and generation must be current on this connection.
        return 1 === (int) $wpdb->get_var($wpdb->prepare('SELECT GET_LOCK(%s, %d)', $name, $timeout));
        // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
    }
    private static function release($name) {
        global $wpdb;
        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- REL007-SQL-0076: release(): WordPress options identifier and fixed control SQL; generation/name values prepared; named-lock ownership and generation must be current on this connection.
        $wpdb->get_var($wpdb->prepare('SELECT RELEASE_LOCK(%s)', $name));
        // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
    }
    private static function owner($name) {
        global $wpdb;
        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- REL007-SQL-0077: owner(): WordPress options identifier and fixed control SQL; generation/name values prepared; named-lock ownership and generation must be current on this connection.
        return $wpdb->get_var($wpdb->prepare('SELECT IS_USED_LOCK(%s)', $name));
        // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
    }
    private static function valid_epoch($value) {
        return (is_int($value) || is_string($value)) && preg_match('/^(?:0|[1-9][0-9]{0,14})$/D', (string) $value);
    }
    public static function generation() {
        global $wpdb;
        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- REL007-SQL-0078: generation(): WordPress options identifier and fixed control SQL; generation/name values prepared; named-lock ownership and generation must be current on this connection.
        $value = $wpdb->get_var($wpdb->prepare("SELECT option_value FROM {$wpdb->options} WHERE option_name = %s", self::GENERATION));
        // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
        if ($wpdb->last_error || (null !== $value && (!ctype_digit((string) $value) || strlen((string) $value) > 15))) { return null; }
        return null === $value ? 0 : (int) $value;
    }
    public static function restoring() {
        global $wpdb;
        $owner = self::owner(self::name('restore'));
        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- REL007-SQL-0079: restoring(): WordPress options identifier and fixed control SQL; generation/name values prepared; named-lock ownership and generation must be current on this connection.
        return $wpdb->last_error || ($owner && (int) $owner !== (int) $wpdb->get_var('SELECT CONNECTION_ID()'));
        // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
    }
    /** Session locks release on disconnect; no expiring lease can outlive its proof. */
    public static function enter_worker($generation = null, $legacy_queue = false) {
        global $wpdb;
        $epoch = self::generation();
        if (null === $epoch || (null !== $generation && !self::valid_epoch($generation)) || (($legacy_queue || null !== $generation) && (int) ($generation ?? 0) !== $epoch)) { return false; }
        if (self::$depth) {
            // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- REL007-SQL-0080: enter_worker(): WordPress options identifier and fixed control SQL; generation/name values prepared; named-lock ownership and generation must be current on this connection.
            if (self::restoring() || (int) self::$epoch !== $epoch || (int) self::owner(self::$slot) !== (int) $wpdb->get_var('SELECT CONNECTION_ID()')) { return false; }
            // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
            self::$depth++; return true;
        }
        $coordinator = self::name('coordinator');
        // Concurrent CRM requests briefly serialize registration. Do not reject
        // a request just because another worker is registering at the same time.
        if (!self::lock($coordinator, 2)) { return false; }
        try {
            if (self::restoring()) { return false; }
            // Refresh after registration lock, closing the restore-generation race.
            $epoch = self::generation();
            if (null === $epoch || (($legacy_queue || null !== $generation) && (int) ($generation ?? 0) !== $epoch)) { return false; }
            for ($i = 1; $i <= self::WORKER_SLOTS; $i++) {
                $slot = self::name('worker_' . $i);
                if (!self::lock($slot)) { continue; }
                // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- REL007-SQL-0081: enter_worker(): WordPress options identifier and fixed control SQL; generation/name values prepared; named-lock ownership and generation must be current on this connection.
                if ((int) self::owner($coordinator) !== (int) $wpdb->get_var('SELECT CONNECTION_ID()')) { self::release($slot); return false; } // Requires multiple named locks.
                // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
                self::$slot = $slot; self::$epoch = $epoch; self::$depth = 1; return true;
            }
            return false;
        } finally { self::release($coordinator); }
    }
    public static function lease_valid() {
        global $wpdb;
        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- REL007-SQL-0082: lease_valid(): WordPress options identifier and fixed control SQL; generation/name values prepared; named-lock ownership and generation must be current on this connection.
        return self::$depth > 0 && self::$epoch === self::generation() && !self::restoring() && (int) self::owner(self::$slot) === (int) $wpdb->get_var('SELECT CONNECTION_ID()');
        // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
    }
    public static function exit_worker() {
        if (!self::$depth || --self::$depth) { return; }
        self::release(self::$slot); self::$slot = null; self::$epoch = null;
    }
    public static function run($callback, $generation = null, $legacy_queue = false, $hook = '', $args = array()) {
        if (!self::enter_worker($generation, $legacy_queue)) {
            // Preserve busy current-generation queued work. Old epochs never retry.
            if ($hook && self::valid_epoch($generation ?? 0) && (int) ($generation ?? 0) === self::generation()) {
                if (function_exists('as_schedule_single_action')) { as_schedule_single_action(time() + 30, $hook, $args, strpos($hook, 'interakt') !== false ? 'idlm-interakt' : ''); }
                elseif (!wp_next_scheduled($hook, $args)) { wp_schedule_single_event(time() + 30, $hook, $args); }
            }
            return false;
        }
        try { return call_user_func($callback); } finally { self::exit_worker(); }
    }
    /** Called before any plugin bootstrap work; hold CRM requests through shutdown. */
    public static function begin_boot() {
        if (!self::enter_worker()) { wp_die('CRM recovery is busy. Retry shortly.', '', array('response' => 503)); }
        // phpcs:disable WordPress.Security.NonceVerification.Recommended -- REL007-BOUNDARY-0065: begin_boot(): typed sanitized request metadata classifies only worker lease lifetime; it grants no mutation access; public signed-webhook requests cannot require WordPress form nonces at this gate.
        $action = isset($_REQUEST['action']) && is_string($_REQUEST['action']) ? sanitize_text_field(wp_unslash($_REQUEST['action'])) : '';
        // phpcs:enable WordPress.Security.NonceVerification.Recommended
        $uri = isset($_SERVER['REQUEST_URI']) && is_string($_SERVER['REQUEST_URI']) ? sanitize_text_field(wp_unslash($_SERVER['REQUEST_URI'])) : '';
        // phpcs:disable WordPress.Security.NonceVerification.Recommended -- REL007-BOUNDARY-0066: begin_boot(): typed sanitized request metadata classifies only worker lease lifetime; it grants no mutation access; public signed-webhook requests cannot require WordPress form nonces at this gate.
        $rest_route = isset($_GET['rest_route']) && is_string($_GET['rest_route']) ? sanitize_text_field(wp_unslash($_GET['rest_route'])) : '';
        // phpcs:enable WordPress.Security.NonceVerification.Recommended
        $crm = strpos($action, 'idlm_') === 0 || strpos($uri, '/idlm/v1/') !== false || strpos($rest_route, '/idlm/v1/') === 0;
        if ('cli' !== PHP_SAPI && $crm && !in_array($action, array('idlm_restore_backup', 'idlm_export_backup', 'idlm_reset_crm_data'), true) && !self::$request) {
            self::$depth++; self::$request = true;
            register_shutdown_function(array(__CLASS__, 'release_request'));
        }
    }
    public static function release_request() {
        if (self::$request) { self::$request = false; self::exit_worker(); }
    }
    /** Restore refuses active workers and holds the gate through post-commit cleanup. */
    public static function begin_restore() {
        global $wpdb;
        $coordinator = self::name('coordinator');
        if (self::$depth || !self::lock($coordinator)) { return false; }
        $held = false;
        try {
            if (!self::lock(self::name('restore'))) { return false; }
            $held = true;
            // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- REL007-SQL-0083: begin_restore(): WordPress options identifier and fixed control SQL; generation/name values prepared; named-lock ownership and generation must be current on this connection.
            if ((int) self::owner($coordinator) !== (int) $wpdb->get_var('SELECT CONNECTION_ID()')) { return false; }
            // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
            for ($i = 1; $i <= self::WORKER_SLOTS; $i++) {
                if (self::owner(self::name('worker_' . $i)) || $wpdb->last_error) { return false; }
            }
            $held = false; return true;
        } finally { if ($held) { self::release(self::name('restore')); } self::release($coordinator); }
    }
    public static function end_restore() { self::release(self::name('restore')); }
    /** Caller owns the restore/reset transaction; protected state is never imported. */
    public static function advance_generation() {
        global $wpdb;
        $epoch = self::generation(); if (null === $epoch || $epoch >= 999999999999999) { return false; }
        $sql = $wpdb->prepare("INSERT INTO {$wpdb->options} (option_name,option_value,autoload) VALUES (%s,%s,'off') ON DUPLICATE KEY UPDATE option_value = VALUES(option_value), autoload = 'off'", self::GENERATION, (string) ($epoch + 1));
        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared -- REL007-SQL-0084: advance_generation(): WordPress options identifier and fixed control SQL; generation/name values prepared; named-lock ownership and generation must be current on this connection.
        return false !== $wpdb->query($sql) && $epoch + 1 === self::generation();
        // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared
    }
}
