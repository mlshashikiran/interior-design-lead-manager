<?php
/** Bounded, retryable administrative Interakt backfills. Builder: Shashi. */
if (!defined('ABSPATH')) { exit; }

final class IDLM_Interakt_Batch {
    const HOOK = 'idlm_async_interakt_batch_event';

    public static function init() {
        add_action(self::HOOK, array(__CLASS__, 'process'), 10, 1);
    }

    public static function allowed_event($event) {
        foreach ((array) get_option('idlm_interakt_mappings', array()) as $mapping) {
            if (isset($mapping['event']) && $event === $mapping['event']) { return true; }
        }
        return false;
    }

    private static function schedule($key, $delay) {
        $args = array($key);
        if (function_exists('as_schedule_single_action')) {
            return (bool) as_schedule_single_action(time() + $delay, self::HOOK, $args, 'idlm-interakt');
        }
        $result = wp_schedule_single_event(time() + $delay, self::HOOK, $args, true);
        return !is_wp_error($result) && false !== $result;
    }

    public static function enqueue($lead_id, $event, $request_id, $delay = 0) {
        global $wpdb;
        if (!self::allowed_event($event)) { return false; }
        $lead = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}idlm_leads WHERE id = %d", $lead_id));
        if (!$lead || empty($lead->phone) || !empty($lead->is_duplicate)) { return false; }
        $key = 'idlm_interakt_batch_' . hash('sha256', $request_id . '|' . $lead_id . '|' . $event);
        $job = array('lead_id' => (int) $lead_id, 'event' => $event, 'status' => 'queued', 'attempts' => 0);
        // Unique option insertion makes repeated/concurrent HTTP submissions idempotent.
        if (!add_option($key, $job, '', false)) { return 'existing'; }
        if (!self::schedule($key, $delay)) {
            delete_option($key);
            return false;
        }
        IDLM_DB::log_activity($lead_id, 'interakt_queued', "Queued Interakt backfill '{$event}'.", 0);
        return 'queued';
    }

    public static function process($key) {
        if (!is_string($key) || !preg_match('/^idlm_interakt_batch_[a-f0-9]{64}$/D', $key)) { return; }
        global $wpdb;
        $lock = 'idlm_ib_' . substr(hash('sha256', $key), 0, 40);
        if (1 !== (int) $wpdb->get_var($wpdb->prepare('SELECT GET_LOCK(%s, 0)', $lock))) { return; }
        try {
            // Read after the lock: another worker may already have completed this job.
            wp_cache_delete($key, 'options');
            $job = get_option($key, array());
            if (!$job || in_array($job['status'], array('sent', 'failed', 'cancelled'), true)) { return; }
            $lead = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}idlm_leads WHERE id = %d", $job['lead_id']));
            $api_key = get_option('idlm_interakt_api_key', '');
            if (!$lead || empty($lead->phone) || !empty($lead->is_duplicate) || '1' !== get_option('idlm_interakt_enabled', '0') || !$api_key || !self::allowed_event($job['event'])) {
                $job['status'] = 'cancelled';
                update_option($key, $job, false);
                return;
            }
            $phone = IDLM_Interakt::format_phone_for_interakt($lead->phone);
            $traits = array('lead_id' => (int) $lead->id, 'triggered_at' => current_time('mysql'));
            foreach (array('email' => 'email', 'project_location' => 'property_location', 'budget' => 'budget', 'property_type' => 'property_type', 'source' => 'source') as $trait => $field) {
                $traits[$trait] = IDLM_Interakt::sanitize_trait_value($lead->$field ?? '');
            }
            $traits['name'] = IDLM_Interakt::sanitize_trait_value(($lead->first_name ?? '') . ' ' . ($lead->last_name ?? ''));
            $payload = array('userId' => (string) $lead->id, 'countryCode' => $phone['countryCode'], 'phoneNumber' => $phone['phoneNumber'], 'event' => $job['event'], 'traits' => $traits, 'createdAt' => gmdate('c'));
            $job['attempts']++;
            $job['status'] = 'processing';
            update_option($key, $job, false);
            if (IDLM_Interakt::send_track_event_to_interakt($payload, $api_key, $lead->id)) {
                $job['status'] = 'sent';
            } elseif ($job['attempts'] < 3) {
                $job['status'] = 'retrying';
                if (!self::schedule($key, 60 * pow(2, $job['attempts']))) { $job['status'] = 'failed'; }
            } else {
                $job['status'] = 'failed';
            }
            update_option($key, $job, false);
        } finally {
            $wpdb->get_var($wpdb->prepare('SELECT RELEASE_LOCK(%s)', $lock));
        }
    }
}
