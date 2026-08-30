<?php
/** Sensitive-field redaction and scheduled retention. @author Shashi */
if (!defined('ABSPATH')) { exit; }

final class IDLM_Data_Retention {
    const HOOK = 'idlm_prune_sensitive_data';

    public static function init() {
        add_action(self::HOOK, array(__CLASS__, 'prune'));
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
    }
}
