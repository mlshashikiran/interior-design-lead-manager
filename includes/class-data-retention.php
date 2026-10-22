<?php
/** Sensitive-field redaction and scheduled retention. @author Shashi */
if (!defined('ABSPATH')) { exit; }

final class IDLM_Data_Retention {
    const HOOK = 'idlm_prune_sensitive_data';
    const DIAGNOSTIC_LOG_RETENTION_VERSION = '1.0.0';
    const DIAGNOSTIC_LOG_RETENTION_DAYS = 7;
    const AUDIENCE_LOG_PAYLOAD_VERSION = '1.0.0';
    const AUDIENCE_LOG_PAYLOAD_MAX_BYTES = 4096;
    const AUDIENCE_LOG_COMPACTION_BATCH_SIZE = 100;

    const CONTINUE_HOOK = 'idlm_continue_sensitive_retention';
    const STATUS_OPTION = 'idlm_retention_progress';
    const BATCH_SIZE = 100;

    public static function init() {
        add_action(self::HOOK, array(__CLASS__, 'prune'));
        add_action(self::CONTINUE_HOOK, array(__CLASS__, 'prune'));
        // Bootstrap schedules work; it never purges data in an interactive request.
        if (!wp_next_scheduled(self::HOOK)) {
            wp_schedule_event(time() + HOUR_IN_SECONDS, 'daily', self::HOOK);
        }
    }

    public static function clear() {
        wp_clear_scheduled_hook(self::HOOK);
        wp_clear_scheduled_hook(self::CONTINUE_HOOK);
        if (function_exists('as_unschedule_all_actions')) { as_unschedule_all_actions(self::CONTINUE_HOOK, array(), 'idlm-retention'); }
        wp_clear_scheduled_hook(IDLM_Identity_Review::PRUNE_HOOK);
    }

    private static function schedule() {
        if (function_exists('as_schedule_single_action')) {
            if (!function_exists('as_has_scheduled_action') || !as_has_scheduled_action(self::CONTINUE_HOOK, array(), 'idlm-retention')) {
                as_schedule_single_action(time() + MINUTE_IN_SECONDS, self::CONTINUE_HOOK, array(), 'idlm-retention', true);
            }
        } elseif (!wp_next_scheduled(self::CONTINUE_HOOK)) {
            wp_schedule_single_event(time() + MINUTE_IN_SECONDS, self::CONTINUE_HOOK);
        }
    }

    public static function redact($value) {
        if (is_object($value)) { $value = get_object_vars($value); }
        if (!is_array($value)) { return IDLM_Secret_Store::redact($value); }
        foreach ($value as $key => $item) {
            $normalized_key = strtolower(preg_replace('/[^a-z0-9]/i', '', (string) $key));
            if ('name' === $normalized_key || preg_match('/(password|passwd|token|secret|authorization|apikey|email|phone|mobile|firstname|lastname|fullname)/', $normalized_key)) {
                $value[$key] = '[REDACTED]';
            } else {
                $value[$key] = self::redact($item);
            }
        }
        return $value;
    }

    /** Store a fixed-size, redacted audit summary instead of a whole audience batch. */
    public static function summarize_audience_payload($payload) {
        $redacted = self::redact($payload);
        $serialized = maybe_serialize($redacted);
        $summary = array(
            'storage' => 'summary',
            'original_bytes' => strlen((string) $serialized),
            'sha256' => hash('sha256', (string) $serialized),
            'type' => is_object($redacted) ? 'object' : gettype($redacted),
        );
        if (is_array($redacted)) {
            $summary['top_level_keys'] = array_slice(array_map('strval', array_keys($redacted)), 0, 20);
            foreach (array('data', 'payload', 'users') as $key) {
                if (isset($redacted[$key]) && is_array($redacted[$key])) {
                    $items = $redacted[$key];
                    $summary['item_count'] = isset($items['data']) && is_array($items['data']) ? count($items['data']) : count($items);
                    break;
                }
            }
        }
        return $summary;
    }

    /** One transaction/checkpoint per invocation. Deleting the first remaining
     * expired rows makes crash/retry safe without a cursor that could skip PII. */
    public static function prune() {
        $result = IDLM_Recovery_Guard::run(static function() { return self::prune_work(); });
        if (false === $result || is_wp_error($result) || empty($result['complete'])) { self::schedule(); }
        return $result;
    }

    private static function save_status($state) {
        global $wpdb;
        // Keep the checkpoint in the same transaction as the batch. Bypass the
        // option cache: a rollback must never publish an advanced checkpoint.
        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- REL007-SQL-0417: save_status(): targets fixes table/column pairs and activity predicates; cutoff, days, IDs and batch limits are prepared; worker/transaction checkpoint reads must be current.
        return IDLM_Lead_Transaction::required($wpdb->query($wpdb->prepare(
        // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
            "INSERT INTO {$wpdb->options} (option_name, option_value, autoload) VALUES (%s, %s, 'no')
             ON DUPLICATE KEY UPDATE option_value = VALUES(option_value), autoload = 'no'",
            self::STATUS_OPTION, maybe_serialize($state)
        )));
    }

    private static function prune_work() {
        global $wpdb;
        $lock = IDLM_Recovery_Guard::name('retention');
        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- REL007-SQL-0418: prune_work(): targets fixes table/column pairs and activity predicates; cutoff, days, IDs and batch limits are prepared; worker/transaction checkpoint reads must be current.
        if (1 !== (int) $wpdb->get_var($wpdb->prepare('SELECT GET_LOCK(%s, 0)', $lock))) { return false; }
        // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
        try {
            // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- REL007-SQL-0419: prune_work(): targets fixes table/column pairs and activity predicates; cutoff, days, IDs and batch limits are prepared; worker/transaction checkpoint reads must be current.
            $raw = $wpdb->get_var($wpdb->prepare("SELECT option_value FROM {$wpdb->options} WHERE option_name = %s", self::STATUS_OPTION));
            // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
            if ($wpdb->last_error) { return new WP_Error('idlm_retention_read', 'Unable to read retention progress.'); }
            $state = maybe_unserialize($raw);
            $generation = IDLM_Recovery_Guard::generation();
            if (!is_array($state) || !empty($state['complete']) || ($state['generation'] ?? null) !== $generation) {
                $state = array('generation' => $generation, 'stage' => 0, 'processed' => 0, 'cutoff' => current_time('mysql'), 'complete' => false, 'remaining' => array_fill(0, 7, true));
            }
            $policy = wp_parse_args((array) get_option('idlm_retention_policy', array()), array('touchpoints_days'=>90, 'integration_logs_days'=>180, 'telemetry_days'=>14));
            $targets = array(
                array('idlm_lead_touchpoints', 'received_at', $policy['touchpoints_days']),
                array('idlm_inbound_submission_reviews', 'received_at', $policy['touchpoints_days']),
                array('idlm_capi_logs', 'created_at', $policy['integration_logs_days']),
                array('idlm_audience_sync_logs', 'created_at', $policy['integration_logs_days']),
                array('idlm_telemetry_logs', 'created_at', $policy['telemetry_days']),
                array('idlm_lead_activity_log', 'created_at', self::DIAGNOSTIC_LOG_RETENTION_DAYS),
                array('idlm_audience_sync_logs', 'payload', 0),
            );
            $stage = min(count($targets) - 1, max(0, (int) $state['stage']));
            $target = $targets[$stage];
            $deadline = microtime(true) + 5;
            $result = IDLM_Lead_Transaction::run(static function() use (&$state, $target, $stage, $targets, $lock, $deadline) {
                global $wpdb;
                $table = $wpdb->prefix . $target[0];
                // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- REL007-SQL-0420: prune_work(): targets fixes table/column pairs and activity predicates; cutoff, days, IDs and batch limits are prepared; worker/transaction checkpoint reads must be current.
                $option_engine = $wpdb->get_var($wpdb->prepare('SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = %s', $wpdb->options));
                // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
                IDLM_Lead_Transaction::required(!$wpdb->last_error && strtoupper((string) $option_engine) === 'INNODB');
                // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- REL007-SQL-0421: prune_work(): targets fixes table/column pairs and activity predicates; cutoff, days, IDs and batch limits are prepared; worker/transaction checkpoint reads must be current.
                $engine = $wpdb->get_var($wpdb->prepare('SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = %s', $table));
                // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
                IDLM_Lead_Transaction::required(!$wpdb->last_error && strtoupper((string) $engine) === 'INNODB');
                // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- REL007-SQL-0422: prune_work(): targets fixes table/column pairs and activity predicates; cutoff, days, IDs and batch limits are prepared; worker/transaction checkpoint reads must be current.
                IDLM_Lead_Transaction::required((int) $wpdb->get_var($wpdb->prepare('SELECT IS_USED_LOCK(%s)', $lock)) === (int) $wpdb->get_var('SELECT CONNECTION_ID()'));
                // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
                if ($stage === count($targets) - 1) {
                    // Hash/size in SQL: even a multi-megabyte historical payload
                    // cannot force PHP to materialize its body during compaction.
                    // phpcs:disable PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- REL007-SQL-0423: prune_work(): targets fixes table/column pairs and activity predicates; cutoff, days, IDs and batch limits are prepared; worker/transaction checkpoint reads must be current.
                    $rows = IDLM_Lead_Transaction::read($wpdb->get_results($wpdb->prepare(
                        "SELECT id, OCTET_LENGTH(payload) bytes, SHA2(payload, 256) digest FROM {$table} WHERE OCTET_LENGTH(payload) > %d ORDER BY id LIMIT %d",
                    // phpcs:enable PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                        self::AUDIENCE_LOG_PAYLOAD_MAX_BYTES, self::BATCH_SIZE
                    )));
                    $count = 0;
                    foreach ($rows as $row) {
                        if (microtime(true) >= $deadline) { break; }
                        $summary = array('storage'=>'summary', 'original_bytes'=>(int) $row->bytes, 'sha256'=>$row->digest, 'type'=>'legacy');
                        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- REL007-SQL-0424: prune_work(): targets fixes table/column pairs and activity predicates; cutoff, days, IDs and batch limits are prepared; worker/transaction checkpoint reads must be current.
                        IDLM_Lead_Transaction::required($wpdb->update($table, array('payload'=>maybe_serialize($summary)), array('id'=>(int) $row->id)));
                        // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
                        $count++;
                    }
                    $more = count($rows) === self::BATCH_SIZE || $count < count($rows);
                } else {
                    $types = $stage === 5 ? " AND activity_type IN ('audience_sync', 'capi_sync', 'capi_queue', 'capi_abort')" : '';
                    if ($target[0] === 'idlm_capi_logs') {
                        // phpcs:disable PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- REL007-SQL-0425: prune_work(): targets fixes table/column pairs and activity predicates; cutoff, days, IDs and batch limits are prepared; worker/transaction checkpoint reads must be current.
                        $rows = IDLM_Lead_Transaction::read($wpdb->get_results($wpdb->prepare(
                            "SELECT id, lead_id, platform, event_name, status, created_at FROM {$table} WHERE created_at < DATE_SUB(%s, INTERVAL %d DAY) ORDER BY created_at, id LIMIT %d FOR UPDATE",
                        // phpcs:enable PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                            $state['cutoff'], max(1, absint($target[2])), self::BATCH_SIZE
                        )));
                        $ids = array();
                        foreach ($rows as $row) {
                            if (microtime(true) >= $deadline) { break; }
                            if ($row->status === 'success') { IDLM_CAPI_Delivery::remember_success((int) $row->lead_id, $row->platform, $row->event_name, $row->created_at); }
                            $ids[] = (int) $row->id;
                        }
                        // phpcs:disable PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- REL007-SQL-0426: prune_work(): targets fixes table/column pairs and activity predicates; cutoff, days, IDs and batch limits are prepared; worker/transaction checkpoint reads must be current.
                        $count = $ids ? IDLM_Lead_Transaction::required($wpdb->query($wpdb->prepare(
                            "DELETE FROM {$table} WHERE id IN (" . implode(',', array_fill(0, count($ids), '%d')) . ")", $ids
                        // phpcs:enable PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                        ))) : 0;
                        $more = count($rows) === self::BATCH_SIZE || count($ids) < count($rows);
                    } else {
                        // phpcs:disable PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- REL007-SQL-0427: prune_work(): targets fixes table/column pairs and activity predicates; cutoff, days, IDs and batch limits are prepared; worker/transaction checkpoint reads must be current.
                        $count = IDLM_Lead_Transaction::required($wpdb->query($wpdb->prepare(
                            "DELETE FROM {$table} WHERE {$target[1]} < DATE_SUB(%s, INTERVAL %d DAY){$types} ORDER BY {$target[1]}, id LIMIT %d",
                        // phpcs:enable PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                            $state['cutoff'], max(1, absint($target[2])), self::BATCH_SIZE
                        )));
                    }
                }
                $more = isset($more) ? $more : $count === self::BATCH_SIZE;
                $state['processed'] += $count;
                $state['remaining'] = $state['remaining'] ?? array_fill(0, count($targets), true);
                $state['remaining'][$stage] = $more;
                $state['stage'] = ($stage + 1) % count($targets);
                $state['complete'] = !in_array(true, $state['remaining'], true);
                $state['last_error'] = '';
                $state['updated_at'] = current_time('mysql', true);
                self::save_status($state);
                return $state;
            });
            // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- REL007-SQL-0428: prune_work(): targets fixes table/column pairs and activity predicates; cutoff, days, IDs and batch limits are prepared; worker/transaction checkpoint reads must be current.
            if (is_wp_error($result) && IDLM_Recovery_Guard::lease_valid() && (int) $wpdb->get_var($wpdb->prepare('SELECT IS_USED_LOCK(%s)', $lock)) === (int) $wpdb->get_var('SELECT CONNECTION_ID()')) {
            // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
                // The committed checkpoint remains authoritative. Only publish a
                // generic fault code; never retain SQL, contacts, or payloads.
                $saved = maybe_unserialize($wpdb->get_var($wpdb->prepare("SELECT option_value FROM {$wpdb->options} WHERE option_name = %s", self::STATUS_OPTION)));
                $fault = is_array($saved) ? $saved : array('generation'=>$generation, 'stage'=>0, 'processed'=>0, 'cutoff'=>$state['cutoff'], 'complete'=>false);
                $fault['last_error'] = 'batch_failed'; $fault['updated_at'] = current_time('mysql', true);
                IDLM_Lead_Transaction::run(static function() use ($fault) { return self::save_status($fault); });
            } elseif (!is_wp_error($result)) {
                IDLM_Read_Model_Cache::invalidate('diagnostic_log_retention');
            }
            wp_cache_delete(self::STATUS_OPTION, 'options');
            wp_cache_delete('notoptions', 'options');
            return $result;
        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- REL007-SQL-0429: prune_work(): targets fixes table/column pairs and activity predicates; cutoff, days, IDs and batch limits are prepared; worker/transaction checkpoint reads must be current.
        } finally { $wpdb->get_var($wpdb->prepare('SELECT RELEASE_LOCK(%s)', $lock)); }
        // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
    }
}
