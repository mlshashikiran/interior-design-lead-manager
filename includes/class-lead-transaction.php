<?php
/** Checked core lead aggregate transaction ownership. Builder: Shashi. */
if (!defined('ABSPATH')) { exit; }

final class IDLM_Lead_Transaction {
    private static $depth = 0;
    private static $connection = null;
    private static $intents = array();
    private static $cache_reasons = array();

    public static function active() { return self::$depth > 0; }

    /** Only this owner may nest. An existing quotation/third-party transaction is refused. */
    public static function run($callback) {
        global $wpdb;
        if (self::active()) {
            $result = call_user_func($callback);
            self::required($result);
            return $result;
        }
        if (!IDLM_Recovery_Guard::enter_worker()) { return self::error(); }
        $suppressed = $wpdb->suppress_errors(true);
        $started = false;
        $committed = false;
        $reconnect_retries = null;
        self::$intents = array(); self::$cache_reasons = array();
        try {
            // SET TRANSACTION without SESSION refuses an already active transaction
            // instead of START TRANSACTION silently committing its owner's work.
            // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- REL007-SQL-0521: run(): fixed transaction/control statements and canonical engine manifest; table names are bound in metadata lookups; transaction ownership cannot be cached.
            if (1 !== (int) $wpdb->get_var('SELECT @@autocommit') || false === $wpdb->query('SET TRANSACTION READ WRITE')) {
            // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
                return new WP_Error('idlm_lead_transaction_owner', 'Lead changes require an independent database transaction. Retry outside the current transaction.');
            }
            foreach (array('idlm_leads','idlm_lead_activity_log','idlm_lead_state_events','idlm_tasks','idlm_lead_touchpoints','idlm_inbound_submission_reviews','idlm_lead_outbox','idlm_routing_state') as $suffix) {
                // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- REL007-SQL-0522: run(): fixed transaction/control statements and canonical engine manifest; table names are bound in metadata lookups; transaction ownership cannot be cached.
                $engine = $wpdb->get_var($wpdb->prepare('SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = %s', $wpdb->prefix . $suffix));
                // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
                if ($wpdb->last_error || 'INNODB' !== strtoupper((string) $engine)) {
                    return new WP_Error('idlm_lead_transaction_engine', 'Lead changes require verified InnoDB tables. Contact the administrator to repair the database, then retry.');
                }
            }
            // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- REL007-SQL-0523: run(): fixed transaction/control statements and canonical engine manifest; table names are bound in metadata lookups; transaction ownership cannot be cached.
            self::$connection = $wpdb->get_var('SELECT CONNECTION_ID()');
            // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
            // Core wpdb retries a lost connection's SQL on a new autocommit
            // session. Disable that retry while this aggregate owns a transaction.
            if (property_exists($wpdb, 'reconnect_retries')) {
                $reconnect_retries = $wpdb->reconnect_retries;
                $wpdb->reconnect_retries = 0;
            }
            // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- REL007-SQL-0524: run(): fixed transaction/control statements and canonical engine manifest; table names are bound in metadata lookups; transaction ownership cannot be cached.
            self::required(self::$connection && false !== $wpdb->query('START TRANSACTION'));
            // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
            $started = true; self::$depth = 1;
            $result = call_user_func($callback);
            self::required($result);
            self::required(self::session_valid());
            // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- REL007-SQL-0525: run(): fixed transaction/control statements and canonical engine manifest; table names are bound in metadata lookups; transaction ownership cannot be cached.
            self::required(false !== $wpdb->query('COMMIT'));
            // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
            $committed = true;
        } catch (Throwable $error) {
            $result = self::error();
        } finally {
            // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- REL007-SQL-0526: run(): fixed transaction/control statements and canonical engine manifest; table names are bound in metadata lookups; transaction ownership cannot be cached.
            if ($started && !$committed) { $wpdb->query('ROLLBACK'); }
            // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
            if (null !== $reconnect_retries) { $wpdb->reconnect_retries = $reconnect_retries; }
            self::$depth = 0; self::$connection = null;
            $wpdb->suppress_errors($suppressed);
            // Keep recovery fenced until post-commit work has finished.
            if (!$committed) { IDLM_Recovery_Guard::exit_worker(); }
        }
        if ($committed) {
            $reasons = self::$cache_reasons; $intents = self::$intents;
            self::$intents = array(); self::$cache_reasons = array();
            try {
                foreach (array_unique($reasons) as $reason) { IDLM_Read_Model_Cache::invalidate($reason); }
                foreach ($intents as $id) { IDLM_Lead_Outbox::dispatch($id); }
            } catch (Throwable $error) {
                // A committed save remains successful; durable intents await the sweep.
            } finally { IDLM_Recovery_Guard::exit_worker(); }
        }
        self::$intents = array(); self::$cache_reasons = array();
        return $result;
    }

    public static function required($result) {
        if (false === $result || null === $result || is_wp_error($result)) { throw new RuntimeException('Required lead persistence failed.'); }
        return $result;
    }
    public static function read($result) {
        global $wpdb;
        self::required('' === (string) $wpdb->last_error);
        return $result;
    }
    public static function session_valid() {
        global $wpdb;
        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- REL007-SQL-0527: session_valid(): fixed transaction/control statements and canonical engine manifest; table names are bound in metadata lookups; transaction ownership cannot be cached.
        return self::$connection && (string) self::$connection === (string) $wpdb->get_var('SELECT CONNECTION_ID()') && IDLM_Recovery_Guard::lease_valid();
        // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
    }
    public static function defer($hook, ...$args) {
        self::required(self::active());
        $id = IDLM_Lead_Outbox::record($hook, $args);
        self::required($id > 0); self::$intents[] = $id;
    }
    public static function invalidate($reason, $lead_id) {
        if (self::active()) {
            self::defer('idlm_lead_cache_invalidation', absint($lead_id), $reason);
            self::$cache_reasons[] = $reason;
        }
        else { IDLM_Read_Model_Cache::invalidate($reason); }
    }
    private static function error() { return new WP_Error('idlm_lead_write_failed', 'Unable to save the complete lead change. Please retry.'); }
}
