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

    public static function init() {
        add_action(self::HOOK, array(__CLASS__, 'prune'));
        if (self::DIAGNOSTIC_LOG_RETENTION_VERSION !== get_option('idlm_diagnostic_log_retention_version', '')) {
            self::prune_diagnostic_logs();
            update_option('idlm_diagnostic_log_retention_version', self::DIAGNOSTIC_LOG_RETENTION_VERSION);
            IDLM_Read_Model_Cache::invalidate('diagnostic_log_retention');
        }
        self::maybe_compact_legacy_audience_payloads();
        if (!wp_next_scheduled(self::HOOK)) {
            wp_schedule_event(time() + HOUR_IN_SECONDS, 'daily', self::HOOK);
        }
    }

    public static function clear() {
        wp_clear_scheduled_hook(self::HOOK);
    }

    public static function redact($value) {
        if (is_object($value)) { $value = get_object_vars($value); }
        if (!is_array($value)) { return $value; }
        foreach ($value as $key => $item) {
            $normalized_key = strtolower(preg_replace('/[^a-z0-9]/i', '', (string) $key));
            if ('name' === $normalized_key || preg_match('/(password|passwd|token|secret|authorization|apikey|email|phone|mobile|firstname|lastname|fullname)/', $normalized_key)) {
                $value[$key] = '[REDACTED]';
            } elseif (is_array($item) || is_object($item)) {
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

    public static function prune() {
        global $wpdb;
        $policy = wp_parse_args((array) get_option('idlm_retention_policy', array()), array(
            'touchpoints_days' => 90, 'integration_logs_days' => 180, 'telemetry_days' => 14,
        ));
        $targets = array(
            'idlm_lead_touchpoints' => array('received_at', $policy['touchpoints_days']),
            'idlm_capi_logs' => array('created_at', $policy['integration_logs_days']),
            'idlm_audience_sync_logs' => array('created_at', $policy['integration_logs_days']),
            'idlm_telemetry_logs' => array('created_at', $policy['telemetry_days']),
        );
        foreach ($targets as $suffix => $rule) {
            $days = max(1, absint($rule[1]));
            $wpdb->query($wpdb->prepare("DELETE FROM {$wpdb->prefix}{$suffix} WHERE {$rule[0]} < DATE_SUB(%s, INTERVAL {$days} DAY)", current_time('mysql')));
        }
        self::prune_diagnostic_logs();
        self::maybe_compact_legacy_audience_payloads();
    }

    /** Gradually compact historical batch payloads without an unbounded scheduled request. */
    private static function maybe_compact_legacy_audience_payloads() {
        if (self::AUDIENCE_LOG_PAYLOAD_VERSION === get_option('idlm_audience_log_payload_version', '')) {
            return;
        }
        global $wpdb;
        $table = $wpdb->prefix . 'idlm_audience_sync_logs';
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT id, payload FROM {$table} WHERE CHAR_LENGTH(payload) > %d ORDER BY id ASC LIMIT %d",
            self::AUDIENCE_LOG_PAYLOAD_MAX_BYTES,
            self::AUDIENCE_LOG_COMPACTION_BATCH_SIZE
        ));
        foreach ((array) $rows as $row) {
            $payload = maybe_unserialize($row->payload);
            $wpdb->update(
                $table,
                array('payload' => maybe_serialize(self::summarize_audience_payload($payload))),
                array('id' => absint($row->id)),
                array('%s'),
                array('%d')
            );
        }
        if (count((array) $rows) < self::AUDIENCE_LOG_COMPACTION_BATCH_SIZE) {
            update_option('idlm_audience_log_payload_version', self::AUDIENCE_LOG_PAYLOAD_VERSION);
            IDLM_Read_Model_Cache::invalidate('audience_log_payload_compaction');
        }
    }

    /** Retain short-lived diagnostic detail without growing the CRM activity history indefinitely. */
    private static function prune_diagnostic_logs() {
        global $wpdb;
        $days = self::DIAGNOSTIC_LOG_RETENTION_DAYS;
        $cutoff = current_time('mysql');
        $activity_types = array('audience_sync', 'capi_sync', 'capi_queue', 'capi_abort');
        $type_placeholders = implode(', ', array_fill(0, count($activity_types), '%s'));
        $activity_sql = "DELETE FROM {$wpdb->prefix}idlm_lead_activity_log
            WHERE activity_type IN ({$type_placeholders})
              AND created_at < DATE_SUB(%s, INTERVAL {$days} DAY)";
        $activity_args = array_merge($activity_types, array($cutoff));
        // Activity types and interval are fixed internally; only values use placeholders.
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        $wpdb->query($wpdb->prepare($activity_sql, $activity_args));
        $wpdb->query($wpdb->prepare(
            "DELETE FROM {$wpdb->prefix}idlm_capi_logs WHERE created_at < DATE_SUB(%s, INTERVAL {$days} DAY)",
            $cutoff
        ));
    }
}
