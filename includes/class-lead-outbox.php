<?php
/** Durable after-commit hook intent and bounded replay. Builder: Shashi. */
if (!defined('ABSPATH')) { exit; }

final class IDLM_Lead_Outbox {
    const HOOK = 'idlm_dispatch_lead_intents';
    private static $dispatching = false;
    private static $current_id = 0;
    private static function hooks() {
        return array('idlm_lead_stage_converted','idlm_lead_attribution_merged','idlm_trigger_interakt_event','idlm_lead_call_status_no_answer','idlm_qualified_handoff_notification','idlm_lead_cache_invalidation');
    }
    public static function init() {
        add_action(self::HOOK, array(__CLASS__, 'drain'));
    }
    public static function current_id() { return self::$current_id; }
    public static function dispatching() { return self::$dispatching; }
    public static function schema() {
        global $wpdb;
        $collate = $wpdb->get_charset_collate();
        return "CREATE TABLE {$wpdb->prefix}idlm_lead_outbox (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            lead_id bigint(20) unsigned NOT NULL,
            generation bigint(20) unsigned NOT NULL,
            hook varchar(80) NOT NULL,
            arguments longtext NOT NULL,
            attempts int(10) unsigned NOT NULL DEFAULT 0,
            available_at datetime NOT NULL,
            created_at datetime NOT NULL,
            PRIMARY KEY  (id),
            KEY available_id (available_at, id),
            KEY lead_id (lead_id)
        ) ENGINE=InnoDB $collate;";
    }
    /** Only called inside the owning aggregate transaction. No copied credentials in jobs. */
    public static function record($hook, $args) {
        global $wpdb;
        if (!IDLM_Lead_Transaction::active() || !in_array($hook, self::hooks(), true)) { return 0; }
        $epoch = IDLM_Recovery_Guard::generation();
        $typed = array();
        foreach ($args as $arg) { $typed[] = array('object'=>is_object($arg), 'value'=>$arg); }
        $json = wp_json_encode($typed);
        if (null === $epoch || false === $json) { return 0; }
        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery -- REL007-SQL-0460: record(): fixed prefixed outbox/lead tables and hook allowlist; IDs/due times bound; generation/receipt/availability reads are live worker state.
        $ok = $wpdb->insert($wpdb->prefix . 'idlm_lead_outbox', array('lead_id'=>absint($args[0]),'generation'=>$epoch,'hook'=>$hook,'arguments'=>$json,'attempts'=>0,'available_at'=>current_time('mysql', true),'created_at'=>current_time('mysql', true)));
        // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery
        return 1 === $ok ? absint($wpdb->insert_id) : 0;
    }
    /** At-least-once hook handoff: failed acknowledgement remains durable for retry. */
    public static function dispatch($id) {
        if (IDLM_Lead_Transaction::active()) { return false; }
        return IDLM_Recovery_Guard::run(static function() use ($id) { return self::dispatch_work(absint($id)); });
    }
    private static function dispatch_work($id) {
        global $wpdb;
        $lock = IDLM_Recovery_Guard::name('outbox_' . $id);
        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- REL007-SQL-0461: dispatch_work(): fixed prefixed outbox/lead tables and hook allowlist; IDs/due times bound; generation/receipt/availability reads are live worker state.
        if (1 !== (int) $wpdb->get_var($wpdb->prepare('SELECT GET_LOCK(%s, 0)', $lock))) { return false; }
        // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
        $suppressed = $wpdb->suppress_errors(true);
        try {
            $table = $wpdb->prefix . 'idlm_lead_outbox';
            // phpcs:disable PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- REL007-SQL-0462: dispatch_work(): fixed prefixed outbox/lead tables and hook allowlist; IDs/due times bound; generation/receipt/availability reads are live worker state.
            $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE id = %d", $id));
            // phpcs:enable PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            if ($wpdb->last_error || !$row) { return !$wpdb->last_error; }
            $epoch = IDLM_Recovery_Guard::generation();
            if (null === $epoch) { return false; }
            // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- REL007-SQL-0463: dispatch_work(): fixed prefixed outbox/lead tables and hook allowlist; IDs/due times bound; generation/receipt/availability reads are live worker state.
            $lead = $wpdb->get_var($wpdb->prepare("SELECT id FROM {$wpdb->prefix}idlm_leads WHERE id = %d", $row->lead_id));
            // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
            if ($wpdb->last_error) { return false; }
            // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- REL007-SQL-0464: dispatch_work(): fixed prefixed outbox/lead tables and hook allowlist; IDs/due times bound; generation/receipt/availability reads are live worker state.
            if ((int) $row->generation !== $epoch || !$lead) { return false !== $wpdb->delete($table, array('id'=>$id)); }
            // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
            if ($row->available_at > current_time('mysql', true)) { return false; }
            $typed = json_decode($row->arguments, true);
            if (!in_array($row->hook, self::hooks(), true) || !is_array($typed)) { return false; }
            $args = array();
            foreach ($typed as $arg) {
                if (!is_array($arg) || !array_key_exists('value', $arg) || !isset($arg['object'])) { return false; }
                $args[] = $arg['object'] ? (object) $arg['value'] : $arg['value'];
            }
            if (absint($args[0] ?? 0) !== absint($row->lead_id) || !IDLM_Recovery_Guard::lease_valid()) { return false; }
            $attempts = (int) $row->attempts + 1;
            $delay = min(3600, 30 * (2 ** min(7, $attempts - 1)));
            // Persist a bounded retry time before callbacks: crashes never lose intent.
            // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- REL007-SQL-0465: dispatch_work(): fixed prefixed outbox/lead tables and hook allowlist; IDs/due times bound; generation/receipt/availability reads are live worker state.
            if (false === $wpdb->update($table, array('attempts'=>$attempts,'available_at'=>gmdate('Y-m-d H:i:s', time() + $delay)), array('id'=>$id))) { return false; }
            // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
            self::$dispatching = true; self::$current_id = $id;
            try {
                if ('idlm_lead_cache_invalidation' === $row->hook) { IDLM_Read_Model_Cache::invalidate('lead_intent_recovery'); }
                // phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.DynamicHooknameFound -- REL007-BOUNDARY-0138: dispatch_work(): dispatch_work rejects every hook outside the fixed hooks() allowlist, validates typed arguments and lead identity, and checks live generation/lease before do_action; this is replay of the existing prefixed internal domain hooks.
                else { do_action($row->hook, ...$args); }
                // phpcs:enable WordPress.NamingConventions.PrefixAllGlobals.DynamicHooknameFound
            }
            catch (Throwable $error) { return false; }
            finally { self::$dispatching = false; self::$current_id = 0; }
            // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- REL007-SQL-0466: dispatch_work(): fixed prefixed outbox/lead tables and hook allowlist; IDs/due times bound; generation/receipt/availability reads are live worker state.
            if (!IDLM_Recovery_Guard::lease_valid() || (int) $wpdb->get_var($wpdb->prepare('SELECT IS_USED_LOCK(%s)', $lock)) !== (int) $wpdb->get_var('SELECT CONNECTION_ID()')) { return false; }
            return 1 === $wpdb->delete($table, array('id'=>$id));
            // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
        } finally {
            $wpdb->suppress_errors($suppressed);
            // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- REL007-SQL-0467: dispatch_work(): fixed prefixed outbox/lead tables and hook allowlist; IDs/due times bound; generation/receipt/availability reads are live worker state.
            $wpdb->get_var($wpdb->prepare('SELECT RELEASE_LOCK(%s)', $lock));
            // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
        }
    }
    public static function drain() {
        return IDLM_Recovery_Guard::run(static function() {
            global $wpdb;
            // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- REL007-SQL-0468: drain(): fixed prefixed outbox/lead tables and hook allowlist; IDs/due times bound; generation/receipt/availability reads are live worker state.
            $ids = $wpdb->get_col($wpdb->prepare("SELECT id FROM {$wpdb->prefix}idlm_lead_outbox WHERE available_at <= %s ORDER BY available_at ASC, id ASC LIMIT 25", current_time('mysql', true)));
            // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
            if ($wpdb->last_error) { return false; }
            foreach ($ids as $id) { self::dispatch($id); }
            return true;
        });
    }
}
