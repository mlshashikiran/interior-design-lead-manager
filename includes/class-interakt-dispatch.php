<?php
/** Opaque ordinary Interakt jobs with live eligibility and conservative recovery. Builder: Shashi. */
if (!defined('ABSPATH')) { exit; }

final class IDLM_Interakt_Dispatch {
    const PREFIX = 'idlm_interakt_dispatch_';
    const HOOK = 'idlm_async_interakt_track_event';
    const EPOCH = 'idlm_interakt_dispatch_epoch';

    public static function init() {
        add_filter('pre_update_option_idlm_interakt_enabled', array(__CLASS__, 'enable_epoch'), 10, 2);
        add_filter('pre_update_option_idlm_whatsapp_notifications_enabled', array(__CLASS__, 'enable_epoch'), 10, 2);
    }

    /** Re-enabling invalidates earlier work; refuse enable if the epoch cannot be persisted. */
    public static function enable_epoch($value, $old) {
        if ('1' === $value && '1' !== $old) {
            try {
                $epoch = bin2hex(random_bytes(16));
                update_option(self::EPOCH, $epoch, false);
                if (self::option(self::EPOCH, '') !== $epoch) { return '0'; }
            } catch (Throwable $error) { return '0'; }
        }
        return $value;
    }

    /** Read committed primary values rather than cached options/queued credentials. */
    private static function option($name, $default = false) {
        if (IDLM_Secret_Store::is_platform_secret($name)) { return IDLM_Secret_Store::get($name, '', true); }
        global $wpdb;
        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- REL007-SQL-0551: option(): fixed options/lead identifiers; option names/IDs prepared; current credentials, cancellation and lead state must be reloaded before dispatch.
        $raw = $wpdb->get_var($wpdb->prepare("SELECT option_value FROM {$wpdb->options} WHERE option_name = %s", $name));
        // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
        if ($wpdb->last_error) { throw new RuntimeException('Interakt settings read failed.'); }
        return null === $raw ? $default : maybe_unserialize($raw);
    }

    public static function inspect($key) {
        if (!is_string($key) || !preg_match('/^idlm_interakt_dispatch_[a-f0-9]{64}$/D', $key)) { return false; }
        $job = self::option($key, false);
        if (!is_array($job) || !is_int($job['lead_id'] ?? null) || $job['lead_id'] < 1 || !is_int($job['owner'] ?? null) || !is_int($job['generation'] ?? null) || $job['generation'] < 0 || !is_string($job['epoch'] ?? null) || !is_string($job['event'] ?? null) || !is_int($job['attempts'] ?? null) || !in_array($job['status'] ?? '', array('queued','processing','sent','cancelled','uncertain'), true)) { return false; }
        $rule = $job['rule'] ?? null;
        if (!is_array($rule) || !in_array($rule['field'] ?? '', array('stage','call_status','lead_quality'), true) || !is_string($rule['value'] ?? null)) { return false; }
        return $job;
    }

    private static function write($key, $job) {
        update_option($key, $job, false);
        return self::inspect($key) === $job;
    }

    private static function schedule($key, $delay = 0) {
        if (function_exists('as_schedule_single_action')) {
            return (bool) as_schedule_single_action(time() + $delay, self::HOOK, array($key), 'idlm-interakt');
        }
        $result = wp_schedule_single_event(time() + $delay, self::HOOK, array($key), true);
        return !is_wp_error($result) && false !== $result;
    }

    private static function lead($id) {
        global $wpdb;
        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- REL007-SQL-0552: lead(): fixed options/lead identifiers; option names/IDs prepared; current credentials, cancellation and lead state must be reloaded before dispatch.
        $lead = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}idlm_leads WHERE id = %d", $id));
        // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
        if ($wpdb->last_error) { throw new RuntimeException('Interakt lead read failed.'); }
        return $lead;
    }

    private static function rules($event) {
        $rules = array();
        foreach ((array) self::option('idlm_interakt_mappings', array()) as $mapping) {
            if (is_array($mapping) && ($mapping['event'] ?? '') === $event && in_array($mapping['type'] ?? '', array('stage', 'call_status', 'lead_quality'), true) && is_string($mapping['value'] ?? null)) {
                $rules[] = array('field'=>$mapping['type'], 'value'=>$mapping['value']);
            }
        }
        if ($event === self::option('idlm_interakt_event_name', 'lead_no_answer')) {
            $rules[] = array('field'=>'call_status', 'value'=>'No Answer');
        }
        return $rules;
    }

    private static function eligible($job, $lead) {
        if (!$lead || !empty($lead->is_duplicate) || empty($lead->phone) || (int) ($lead->assigned_rep_id ?? 0) !== $job['owner']) { return false; }
        if (!IDLM_Settings::notification_enabled('idlm_whatsapp_notifications_enabled') || '1' !== self::option('idlm_interakt_enabled', '0') || !self::option('idlm_interakt_api_key', '') || self::option(self::EPOCH, '') !== $job['epoch']) { return false; }
        $rule = $job['rule'];
        return in_array($rule, self::rules($job['event']), true) && (string) ($lead->{$rule['field']} ?? '') === $rule['value'];
    }

    public static function enqueue($lead_id, $event, $old_lead = null, $rule = null) {
        try {
            if (!is_string($event) || '' === $event || sanitize_key($event) !== $event) { return null; }
            $lead = self::lead(absint($lead_id));
            if (!$lead) { return null; }
            $owner = is_array($rule) && isset($rule['owner']) ? (int) $rule['owner'] : (int) ($lead->assigned_rep_id ?? 0);
            if (is_array($rule)) { unset($rule['owner']); }
            if (null === $rule) {
                foreach (self::rules($event) as $candidate) {
                    if ((string) ($lead->{$candidate['field']} ?? '') === $candidate['value'] && (!$old_lead || (string) ($old_lead->{$candidate['field']} ?? '') !== $candidate['value'])) { $rule = $candidate; break; }
                }
            }
            if (!is_array($rule) || !in_array($rule, self::rules($event), true)) { return null; }
            $generation = IDLM_Recovery_Guard::generation();
            if (null === $generation) { return false; }
            $job = array('lead_id'=>absint($lead_id), 'event'=>$event, 'rule'=>$rule, 'owner'=>$owner, 'epoch'=>self::option(self::EPOCH, ''), 'generation'=>$generation, 'status'=>'queued', 'attempts'=>0, 'created_at'=>time(), 'reason'=>'');
            if (!self::eligible($job, $lead)) { return null; }
            // Outbox retries hand off the same event once; trusted direct calls are new events.
            $identity = IDLM_Lead_Outbox::current_id() ?: bin2hex(random_bytes(16));
            $key = self::PREFIX . hash('sha256', $generation . '|' . $identity . '|' . $lead_id . '|' . $event . '|' . wp_json_encode($rule));
            if (!add_option($key, $job, '', false)) {
                $existing = self::inspect($key);
                if (!$existing) { return false; }
                if ('queued' !== $existing['status']) { return $key; }
            }
            if (!self::schedule($key)) { return false; } // Keep durable queued intent for an outbox retry.
            IDLM_DB::log_activity($lead_id, 'interakt_queued', 'Queued Interakt automation job ' . $key . '.', 0);
            return $key;
        } catch (Throwable $error) { return false; }
    }

    public static function process($key) {
        global $wpdb;
        // Never dispatch from a caller's uncommitted/repeatable-read snapshot.
        $suppressed = $wpdb->suppress_errors(true);
        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- REL007-SQL-0553: process(): fixed options/lead identifiers; option names/IDs prepared; current credentials, cancellation and lead state must be reloaded before dispatch.
        $ready = 1 === (int) $wpdb->get_var('SELECT @@autocommit') && false !== $wpdb->query('SET TRANSACTION READ WRITE');
        // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
        $wpdb->suppress_errors($suppressed);
        if (!$ready) { return false; }
        try {
            $job = self::inspect($key);
            if (!is_array($job)) { return false; }
            return IDLM_Recovery_Guard::run(static function() use ($key) { return self::work($key); }, $job['generation'], true, self::HOOK, array($key));
        } catch (Throwable $error) { return false; }
    }

    private static function work($key) {
        global $wpdb;
        $lock = IDLM_Recovery_Guard::name('it_' . substr(hash('sha256', $key), 0, 20));
        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- REL007-SQL-0554: work(): fixed options/lead identifiers; option names/IDs prepared; current credentials, cancellation and lead state must be reloaded before dispatch.
        if (1 !== (int) $wpdb->get_var($wpdb->prepare('SELECT GET_LOCK(%s, 0)', $lock))) { self::schedule($key, 30); return false; }
        // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
        try {
            $job = self::inspect($key);
            if (!$job || in_array($job['status'], array('sent','cancelled','uncertain'), true)) { return true; }
            if ('processing' === $job['status']) {
                $job['status'] = 'uncertain'; $job['reason'] = 'interrupted_attempt';
                $saved = self::write($key, $job);
                IDLM_DB::log_activity($job['lead_id'], 'interakt_uncertain', 'Interakt job requires reconciliation: ' . $key . '.', 0);
                return $saved;
            }
            $lead = self::lead($job['lead_id']);
            if (!self::eligible($job, $lead)) { $job['status']='cancelled'; $job['reason']='current_eligibility'; return self::write($key, $job); }
            $job['status']='processing'; $job['attempts']++; $job['attempted_at']=time();
            // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- REL007-SQL-0555: work(): fixed options/lead identifiers; option names/IDs prepared; current credentials, cancellation and lead state must be reloaded before dispatch.
            if (!self::write($key, $job) || !IDLM_Recovery_Guard::lease_valid() || (int) $wpdb->get_var($wpdb->prepare('SELECT IS_USED_LOCK(%s)', $lock)) !== (int) $wpdb->get_var('SELECT CONNECTION_ID()')) { return false; }
            // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
            // Final live gate; settings changes after this point cannot recall an in-flight request.
            $lead = self::lead($job['lead_id']);
            if (!self::eligible($job, $lead)) { $job['status']='cancelled'; $job['reason']='current_eligibility'; return self::write($key, $job); }
            $phone = IDLM_Interakt::format_phone_for_interakt($lead->phone);
            if (!preg_match('/^[0-9]{7,15}$/D', $phone['phoneNumber'])) { $job['status']='cancelled'; $job['reason']='invalid_phone'; return self::write($key, $job); }
            $traits = array('lead_id'=>(int) $lead->id, 'triggered_at'=>current_time('mysql'));
            foreach (array('email'=>'email','project_location'=>'property_location','budget'=>'budget','property_type'=>'property_type','source'=>'source') as $trait=>$field) { $traits[$trait]=IDLM_Interakt::sanitize_trait_value($lead->$field ?? ''); }
            $traits['name']=IDLM_Interakt::sanitize_trait_value(($lead->first_name ?? '') . ' ' . ($lead->last_name ?? ''));
            $payload=array('userId'=>(string) $lead->id, 'countryCode'=>$phone['countryCode'], 'phoneNumber'=>$phone['phoneNumber'], 'event'=>$job['event'], 'traits'=>$traits, 'createdAt'=>gmdate('c'));
            $sent = IDLM_Interakt::send_track_event_to_interakt($payload, self::option('idlm_interakt_api_key', ''), $job['lead_id']);
            if (!IDLM_Recovery_Guard::lease_valid()) { return false; }
            $job['status']=$sent ? 'sent' : 'uncertain'; $job['reason']=$sent ? 'accepted' : 'provider_outcome';
            $saved=self::write($key, $job);
            if (!$sent || !$saved) { IDLM_DB::log_activity($job['lead_id'], 'interakt_uncertain', 'Interakt job requires reconciliation: ' . $key . '.', 0); }
            return $saved;
        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- REL007-SQL-0556: work(): fixed options/lead identifiers; option names/IDs prepared; current credentials, cancellation and lead state must be reloaded before dispatch.
        } finally { $wpdb->get_var($wpdb->prepare('SELECT RELEASE_LOCK(%s)', $lock)); }
        // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
    }
}
