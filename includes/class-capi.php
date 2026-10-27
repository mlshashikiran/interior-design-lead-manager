<?php
if (!defined('ABSPATH')) {
    exit;
}

class IDLM_CAPI {
    public static function init() {
        // Hook lead conversion stage changes
        add_action('idlm_lead_stage_converted', array(__CLASS__, 'queue_capi_event'), 10, 3);
        // Hook for Action Scheduler execution
        add_action('idlm_async_capi_upload_event', array(__CLASS__, 'process_capi_event'), 10, 3);
        // Hook for handling duplicate lead attribution merges
        add_action('idlm_lead_attribution_merged', array(__CLASS__, 'handle_attribution_merged'), 10, 2);
    }

    /** Preserve the hook callback without replaying an already reported conversion. */
    public static function handle_attribution_merged($lead_id, $merged_fields) {
        // New attribution is used by future events and explicitly rejected retries.
        // A second provider request is not a reliable update to an accepted event.
    }

    /**
     * Check if a successful CAPI event has already been sent for this lead, platform, and event name.
     */
    private static function is_event_already_sent($lead_id, $platform, $event_name, $uncertainty_only = false) {
        global $wpdb;
        $table_leads = $wpdb->prefix . 'idlm_leads';
        $table_capi = $wpdb->prefix . 'idlm_capi_logs';
        
        // Resolve canonical lead ID
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
        $lead_info = $wpdb->get_row($wpdb->prepare(
            // phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- REL007-SQL-0445: is_event_already_sent(): prefixed lead/log tables, fixed success/event predicates; values bound; IN placeholders match lead IDs plus platform/event parameters; receipts/history/current eligibility must not be cached.
            "SELECT id, is_duplicate, duplicate_of_id FROM {$table_leads} WHERE id = %d",
            // phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            $lead_id
        ));
        
        if ($wpdb->last_error) { throw new RuntimeException('CAPI history read failed.'); }
        if (!$lead_info) {
            return false;
        }
        
        $canonical_id = $lead_info->is_duplicate ? $lead_info->duplicate_of_id : $lead_info->id;
        
        // Fetch all related lead IDs (canonical + duplicates)
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
        $lead_ids = $wpdb->get_col($wpdb->prepare(
            // phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- REL007-SQL-0446: is_event_already_sent(): prefixed lead/log tables, fixed success/event predicates; values bound; IN placeholders match lead IDs plus platform/event parameters; receipts/history/current eligibility must not be cached.
            "SELECT id FROM {$table_leads} WHERE id = %d OR duplicate_of_id = %d",
            // phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            $canonical_id,
            $canonical_id
        ));
        
        if ($wpdb->last_error) { throw new RuntimeException('CAPI history read failed.'); }
        if (empty($lead_ids)) {
            $lead_ids = array($lead_id);
        }
        
        $placeholders = implode(',', array_fill(0, count($lead_ids), '%d'));
        
        // phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber -- REL007-SQL-0447: is_event_already_sent(): prefixed lead/log tables, fixed success/event predicates; values bound; IN placeholders match lead IDs plus platform/event parameters; receipts/history/current eligibility must not be cached.
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
        $exists = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$table_capi} WHERE lead_id IN ($placeholders) AND platform = %s AND event_name = %s AND status = 'success' LIMIT 1",
        // phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber
            array_merge($lead_ids, array($platform, $event_name))
        ));
        
        if ($wpdb->last_error) { throw new RuntimeException('CAPI history read failed.'); }
        // Minimal receipts survive log pruning and also cover related duplicate IDs.
        foreach ($lead_ids as $id) {
            $receipt = IDLM_CAPI_Delivery::read((int) $id, $platform, $event_name);
            if ($receipt && ((!$uncertainty_only && $receipt['sent']) || in_array($receipt['status'], array('processing', 'uncertain'), true))) { return true; }
        }
        return !$uncertainty_only && !empty($exists);
    }

    /**
     * Check if a lead is eligible for Google Ads Offline Conversions / CAPI.
     */
    public static function is_google_conversion_eligible($lead) {
        $source = !empty($lead->source) ? trim(strtolower($lead->source)) : '';
        $utm_source = !empty($lead->utm_source) ? trim(strtolower($lead->utm_source)) : '';
        $utm_medium = !empty($lead->utm_medium) ? trim(strtolower($lead->utm_medium)) : '';
        $gclid = !empty($lead->gclid) ? trim($lead->gclid) : '';
        $gbraid = !empty($lead->gbraid) ? trim($lead->gbraid) : '';
        $wbraid = !empty($lead->wbraid) ? trim($lead->wbraid) : '';
        $fbclid = !empty($lead->fbclid) ? trim($lead->fbclid) : '';
        $fb_lead_id = !empty($lead->fb_lead_id) ? trim($lead->fb_lead_id) : '';
        $lead_quality = !empty($lead->lead_quality) ? trim(strtolower($lead->lead_quality)) : '';
        $stage = !empty($lead->stage) ? trim(strtolower($lead->stage)) : '';

        // Exclusions:
        // 1. Rep marks invalid/no requirement (lead quality is unqualified, or stage is lost / invalid / no requirement)
        if ($lead_quality === 'unqualified' || $stage === 'lost' || $stage === 'invalid' || $stage === 'no requirement') {
            return false;
        }

        // Check if there is google/paid ads evidence
        $has_google_evidence = (
            !empty($gclid) || 
            !empty($gbraid) || 
            !empty($wbraid) || 
            strpos($source, 'google') !== false || 
            strpos($source, 'paid search') !== false || 
            strpos($source, 'paid-search') !== false ||
            strpos($utm_source, 'google') !== false || 
            $utm_medium === 'cpc'
        );

        // 2. Exclude if source is Meta Ads and no Google evidence exists
        if ((strpos($source, 'meta') !== false || strpos($source, 'facebook') !== false || strpos($source, 'instagram') !== false) && !$has_google_evidence) {
            return false;
        }

        // 3. Exclude if fbclid/fb_lead_id is present and no Google evidence exists
        if ((!empty($fbclid) || !empty($fb_lead_id)) && !$has_google_evidence) {
            return false;
        }

        // 4. Exclude if utm_source is facebook/instagram/meta and no Google evidence exists
        if ((strpos($utm_source, 'facebook') !== false || strpos($utm_source, 'instagram') !== false || strpos($utm_source, 'meta') !== false) && !$has_google_evidence) {
            return false;
        }

        // Inclusions:
        // Upload to Google only if:
        // - source/channel is Google Ads or Paid Search
        // - OR gclid is present
        // - OR gbraid is present
        // - OR wbraid is present
        // - OR landing session has google/cpc UTMs
        // - OR original lead attribution is unknown but utm_source/utm_medium suggests Google
        $is_google_ads = (
            strpos($source, 'google') !== false || 
            strpos($source, 'paid search') !== false || 
            strpos($source, 'paid-search') !== false
        );

        $has_google_utms = (
            strpos($utm_source, 'google') !== false && 
            $utm_medium === 'cpc'
        );

        $is_unknown_but_google = (
            ($source === '' || $source === 'unknown' || $source === 'organic' || $source === 'direct') && 
            (strpos($utm_source, 'google') !== false || $utm_medium === 'cpc')
        );

        $eligible = (
            $is_google_ads || 
            !empty($gclid) || 
            !empty($gbraid) || 
            !empty($wbraid) || 
            $has_google_utms || 
            $is_unknown_but_google
        );

        return $eligible;
    }

    /**
     * Queue Conversion API event using Action Scheduler
     */
    public static function queue_capi_event($lead_id, $stage, $old_lead) {
        $delay = ($stage === 'New') ? 0 : 300;

        if ($delay === 0) {
            // Fire immediately and synchronously for lead capture ('New' stage) to ensure CAPI is sent instantly
            IDLM_DB::log_activity($lead_id, 'capi_sync', "Executing Conversions API synchronously for stage: {$stage}.", 0);
            $processed = self::process_capi_event($lead_id, $stage);
            if (false === $processed && IDLM_Lead_Outbox::dispatching()) { throw new RuntimeException('CAPI dispatch is busy.'); }
            return;
        }

        $schedule_time = time() + $delay;
        $delay_msg = " (delayed by 5 mins)";

        // Cancel any pending CAPI uploads for this lead to prevent duplicate or conflicting events from firing on rapid stage changes.
        $all_stages = get_option('idlm_custom_stages', array('New', 'Contacted', 'Qualified', 'Quote Shared', 'Negotiation', 'Won', 'Lost'));
        $all_stages = array_unique(array_merge(array('New', 'Contacted', 'Qualified', 'Quote Shared', 'Negotiation', 'Won', 'Lost'), $all_stages));

        foreach ($all_stages as $stg) {
            $current_args = array('lead_id'=>$lead_id, 'stage'=>$stg, 'generation'=>IDLM_Recovery_Guard::generation());
            if (function_exists('as_unschedule_all_actions')) { as_unschedule_all_actions('idlm_async_capi_upload_event', $current_args); }
            wp_clear_scheduled_hook('idlm_async_capi_upload_event', $current_args);
            if (function_exists('as_unschedule_all_actions')) {
                as_unschedule_all_actions('idlm_async_capi_upload_event', array(
                    'lead_id' => $lead_id,
                    'stage' => $stg
                ));
            }
            wp_clear_scheduled_hook('idlm_async_capi_upload_event', array(
                'lead_id' => $lead_id,
                'stage' => $stg
            ));
        }

        if (function_exists('as_schedule_single_action')) {
            $queued = as_schedule_single_action($schedule_time, 'idlm_async_capi_upload_event', array(
                'lead_id' => $lead_id,
                'stage' => $stage,
                'generation' => IDLM_Recovery_Guard::generation()
            ));
            if (!$queued && IDLM_Lead_Outbox::dispatching()) { throw new RuntimeException('CAPI scheduling was not accepted.'); }
            IDLM_DB::log_activity($lead_id, 'capi_queue', "Queued Conversions API background sync via Action Scheduler for stage: {$stage}{$delay_msg}.", 0);
        } else {
            // Fallback to WP Cron if Action Scheduler is disabled or not booted yet
            $queued = wp_schedule_single_event($schedule_time, 'idlm_async_capi_upload_event', array(
                'lead_id' => $lead_id,
                'stage' => $stage,
                'generation' => IDLM_Recovery_Guard::generation()
            ));
            if (!$queued && IDLM_Lead_Outbox::dispatching()) { throw new RuntimeException('CAPI scheduling was not accepted.'); }
            IDLM_DB::log_activity($lead_id, 'capi_queue', "Queued Conversions API background sync via WP Cron for stage: {$stage}{$delay_msg}.", 0);
        }
    }

    /**
     * Background worker handling CAPI processing
     */
    public static function process_capi_event($lead_id, $stage, $generation = null) {
        $queued = doing_action('idlm_async_capi_upload_event');
        return IDLM_Recovery_Guard::run(static function() use ($lead_id, $stage) { return self::process_capi_event_work($lead_id, $stage); }, $generation, $queued, $queued ? 'idlm_async_capi_upload_event' : '', array($lead_id, $stage, $generation));
    }

    private static function process_capi_event_work($lead_id, $stage) {
        global $wpdb;
        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- REL007-SQL-0448: process_capi_event_work(): prefixed lead/log tables, fixed success/event predicates; values bound; IN placeholders match lead IDs plus platform/event parameters; receipts/history/current eligibility must not be cached.
        $lead = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}idlm_leads WHERE id = %d", $lead_id));
        // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
        if (!$lead) {
            return;
        }

        if (!self::lead_matches_event($lead, $stage)) { return; }

        // Fire for each enabled platform
        self::fire_meta_capi($lead, $stage);
        self::fire_google_capi($lead, $stage);
        self::fire_linkedin_capi($lead, $stage);
    }

    /** Worker and retry share this predicate; New remains the initial contact event. */
    private static function lead_matches_event($lead, $stage) {
        if (!$lead || !empty($lead->is_duplicate) || !in_array($stage, array_merge(array_values(IDLM_State_Registry::stages()), (array)self::runtime_option('idlm_custom_stages',array())), true)) { return false; }
        if ('Qualified' === $stage) { return 'Qualified' === $lead->lead_quality || in_array($lead->stage, array('Qualified','Quote Shared','Won'), true); }
        if ('Quote Shared' === $stage) { return in_array($lead->stage, array('Quote Shared','Won'), true); }
        if ('New' === $stage) { return true; }
        return $stage === $lead->stage;
    }

    private static function runtime_option($name, $default = false) {
        if (IDLM_Secret_Store::is_platform_secret($name)) { return IDLM_Secret_Store::get($name, '', true); }
        global $wpdb;
        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- REL007-SQL-0449: runtime_option(): prefixed lead/log tables, fixed success/event predicates; values bound; IN placeholders match lead IDs plus platform/event parameters; receipts/history/current eligibility must not be cached.
        $value = $wpdb->get_var($wpdb->prepare("SELECT option_value FROM {$wpdb->options} WHERE option_name = %s", $name));
        // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
        if ($wpdb->last_error) { throw new RuntimeException('CAPI settings read failed.'); }
        return null === $value ? $default : maybe_unserialize($value);
    }

    private static function event_mappings($platform) {
        $defaults = array('meta'=>array('New'=>'Lead','Qualified'=>'Contact','Quote Shared'=>'Subscribe','Won'=>'Purchase'), 'google'=>array('Qualified'=>'CRM Qualified Lead','Won'=>'Closed Won Interior Project'), 'linkedin'=>array('Qualified'=>'CRM Qualified Lead','Won'=>'Closed Won'));
        if (!isset($defaults[$platform])) { return array(); }
        $mappings = self::runtime_option('idlm_'.$platform.'_event_mappings', $defaults[$platform]);
        if (!is_array($mappings)) { return array(); }
        if ('meta' === $platform && !array_key_exists('New', $mappings)) { $mappings['New']='Lead'; }
        return array_map(static function($value) { return is_string($value) ? trim($value) : ''; }, $mappings);
    }

    private static function no_attempt($message) {
        return array('success'=>false, 'message'=>$message, 'attempted'=>false, 'log_id'=>0, 'attempt_id'=>'', 'retryable'=>true, 'outcome'=>'skipped');
    }

    /** A stored Meta lead ID takes precedence over a later/earlier WhatsApp referral. */
    private static function uses_whatsapp_attribution($lead, $attribution) {
        return IDLM_CAPI_Transport::uses_whatsapp_attribution($lead, $attribution);
    }

    /** Latest saved quotation revision is authoritative for a fresh Meta Purchase. */
    private static function purchase_amount($lead) {
        global $wpdb;
        $fallback=array('value'=>(float)($lead->deal_value ?? 0),'currency'=>self::runtime_option('idlm_currency_code','INR'));
        $quote=IDLM_Quotation_Repository::get_quote_for_lead($lead->id);
        if ($wpdb->last_error) { throw new RuntimeException('Purchase quotation read failed.'); }
        if (!$quote) { return $fallback; }
        $revision_id=absint($quote->current_revision_id ?? 0);
        $revision=$revision_id ? IDLM_Quotation_Repository::get_revision($revision_id) : null;
        if ($wpdb->last_error || !$revision || (int)$revision->quote_id!==(int)$quote->id) {
            throw new RuntimeException('Purchase quotation revision is unavailable.');
        }
        $value=$revision->grand_total ?? null;
        if (!is_numeric($value) || !is_finite((float)$value) || (float)$value<0) { throw new RuntimeException('Purchase quotation total is invalid.'); }
        // A zero-total draft is not a final priced quotation; retain the entered close value.
        if ((float)$value===0.0) { return $fallback; }
        $currency=$revision->currency_code ?? '';
        if (!is_string($currency) || !preg_match('/^[A-Z]{3}$/D',$currency)) { throw new RuntimeException('Purchase quotation currency is unavailable.'); }
        return array('value'=>(float)$value,'currency'=>$currency);
    }

    /** One locked dispatch boundary for ordinary, forced attribution and manual attempts. */
    private static function dispatch_platform($platform, $lead_id, $stage, $force = false, $expected = null) {
        global $wpdb;
        $suppressed=$wpdb->suppress_errors(true);
        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- REL007-SQL-0450: dispatch_platform(): prefixed lead/log tables, fixed success/event predicates; values bound; IN placeholders match lead IDs plus platform/event parameters; receipts/history/current eligibility must not be cached.
        $ready=1 === (int) $wpdb->get_var('SELECT @@autocommit') && false !== $wpdb->query('SET TRANSACTION READ WRITE');
        // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
        $wpdb->suppress_errors($suppressed);
        if (!$ready) { return self::no_attempt('Delivery requires an independent committed database context.'); }
        $attempt_started=false;
        $lock=IDLM_Recovery_Guard::name('ca_'.substr(hash('sha256', $platform.'|'.$lead_id),0,20));
        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- REL007-SQL-0451: dispatch_platform(): prefixed lead/log tables, fixed success/event predicates; values bound; IN placeholders match lead IDs plus platform/event parameters; receipts/history/current eligibility must not be cached.
        if (1 !== (int) $wpdb->get_var($wpdb->prepare('SELECT GET_LOCK(%s, 0)', $lock))) { return self::no_attempt('Another delivery is in progress. Retry later.'); }
        // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
        try {
            // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- REL007-SQL-0452: dispatch_platform(): prefixed lead/log tables, fixed success/event predicates; values bound; IN placeholders match lead IDs plus platform/event parameters; receipts/history/current eligibility must not be cached.
            $lead=$wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}idlm_leads WHERE id = %d", $lead_id));
            // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
            if ($wpdb->last_error) { throw new RuntimeException('CAPI lead read failed.'); }
            if (!self::lead_matches_event($lead,$stage)) { return self::no_attempt('The current lead is excluded or no longer meets this event condition.'); }
            if ('1' !== self::runtime_option('idlm_'.$platform.'_capi_enabled','0')) { return self::no_attempt('This integration is disabled.'); }
            $mappings=self::event_mappings($platform);
            if ('meta' === $platform) {
                // A lead ID already selects the Instant Form path; independent
                // webhook conversions must not depend on connector-only metadata.
                $attribution=empty($lead->fb_lead_id) ? IDLM_Lead_Connector::attribution($lead_id) : null;
                if (is_wp_error($attribution)) { return self::no_attempt('Lead attribution is unreadable. Review connector credentials.'); }
                if (self::uses_whatsapp_attribution($lead, $attribution)) {
                    $config=IDLM_Lead_Connector::config();
                    if (self::runtime_option('idlm_whatsapp_capi_enabled','0') !== '1' || empty($attribution['ctwa_clid']) || !IDLM_Lead_Connector::id($attribution['page_id'] ?? null)) { return self::no_attempt('WhatsApp conversion reporting needs explicit enablement, a click ID and Page ID.'); }
                    $mappings=$config['whatsapp_events'] ?? array('Won'=>'Purchase');
                }
            }
            $event=$mappings[$stage] ?? '';
            if ('' === $event) { return self::no_attempt('This stage has no configured event.'); }
            if ('google' === $platform && '' === IDLM_CAPI_Transport::google_event_name($event)) { return self::no_attempt('Use a Google event label of at most 40 letters, digits, underscores or spaces, beginning with a letter. GA4 sends spaces as underscores.'); }
            if ('google' === $platform && !self::is_google_conversion_eligible($lead)) { return self::no_attempt('The current lead is not eligible for Google attribution.'); }
            if ('linkedin' === $platform && empty($lead->li_fat_id)) { return self::no_attempt('The current lead has no LinkedIn attribution ID.'); }
            $keys=array('meta'=>array('idlm_meta_pixel_id','idlm_meta_access_token'), 'google'=>array('idlm_google_measurement_id','idlm_google_api_secret'), 'linkedin'=>array('idlm_linkedin_conversion_id','idlm_linkedin_access_token'));
            $destination=(string) self::runtime_option($keys[$platform][0],'');$credential=(string) self::runtime_option($keys[$platform][1],'');
            if ($expected && ($expected['event'] !== $event || (!empty($expected['destination']) && $expected['destination'] !== $destination))) { return self::no_attempt('Event mapping or destination changed. Review the original event before retrying.'); }
            $delivery=array('version'=>1,'platform'=>$platform,'lead_id'=>(int)$lead_id,'stage'=>$stage,'event'=>$event,'destination'=>$destination,'credential'=>$credential,'attempt_id'=>$expected['_attempt_id'] ?? bin2hex(random_bytes(16)),'event_id'=>$expected['event_id'] ?? ('idlm_lead_'.$lead_id.'_'.$event),'occurred_at'=>$expected['occurred_at'] ?? time(),'value'=>$expected['value'] ?? (float)($lead->deal_value ?? 0),'currency'=>$expected['currency'] ?? self::runtime_option('idlm_currency_code','INR'),'retry_root'=>$expected['retry_root'] ?? 0);
            if ($platform==='meta' && $event==='Purchase' && $expected===null) {
                $amount=self::purchase_amount($lead);$delivery['value']=$amount['value'];$delivery['currency']=$amount['currency'];
            }
            if ('' === $destination || '' === $credential) { return self::record_attempt($lead_id,$platform,$delivery,array(),'Missing integration destination or credentials.','failed',false,true); }
            // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- REL007-SQL-0453: dispatch_platform(): prefixed lead/log tables, fixed success/event predicates; values bound; IN placeholders match lead IDs plus platform/event parameters; receipts/history/current eligibility must not be cached.
            if (!IDLM_Recovery_Guard::lease_valid() || (int)$wpdb->get_var($wpdb->prepare('SELECT IS_USED_LOCK(%s)', $lock)) !== (int)$wpdb->get_var('SELECT CONNECTION_ID()')) { return self::no_attempt('Delivery session is no longer valid.'); }
            // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
            // The legacy force argument must not bypass accepted delivery evidence.
            $claim = IDLM_CAPI_Delivery::claim($delivery, false, static function() use ($lead_id, $platform, $event) { return self::is_event_already_sent($lead_id, $platform, $event); }, $lock);
            if (is_wp_error($claim)) { return self::no_attempt('Delivery claim could not be committed. No request was sent.'); }
            if (!$claim['allowed']) { return self::no_attempt($claim['message']); }
            $delivery['_durable_claim'] = true;
            $method='fire_'.$platform.'_capi_work';
            $attempt_started=true;
            return self::$method($lead,$stage,$delivery);
        } catch (Throwable $error) { return $attempt_started ? array_replace(self::no_attempt('Delivery outcome is uncertain; reconciliation is required.'),array('attempted'=>true,'outcome'=>'uncertain','retryable'=>false)) : self::no_attempt('Current delivery state could not be verified. Retry later.'); }
        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- REL007-SQL-0454: dispatch_platform(): prefixed lead/log tables, fixed success/event predicates; values bound; IN placeholders match lead IDs plus platform/event parameters; receipts/history/current eligibility must not be cached.
        finally { $wpdb->get_var($wpdb->prepare('SELECT RELEASE_LOCK(%s)', $lock)); }
        // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
    }

    /** Metadata is appended only to the local redacted log, never to provider JSON. */
    private static function record_attempt($lead_id,$platform,$delivery,$payload,$response,$status,$attempted,$retryable,$outcome = null) {
        return IDLM_CAPI_Transport::record_attempt($lead_id, $platform, $delivery, $payload, $response, $status, $attempted, $retryable, $outcome);
    }

    /** An HTTP failure is not necessarily proof that the provider did not ingest the event. */
    private static function record_provider_response($lead_id, $platform, $delivery, $payload, $body, $code, $status) {
        return IDLM_CAPI_Transport::record_provider_response($lead_id, $platform, $delivery, $payload, $body, $code, $status);
    }

    private static function log_payload($value) {
        if (!is_string($value)) { return is_array($value) ? $value : array(); }
        $json=json_decode($value,true);
        if (is_array($json)) { return $json; }
        $decoded=@unserialize($value,array('allowed_classes'=>false));
        return is_array($decoded) ? $decoded : array();
    }

    private static function retry_identity($log) {
        $payload=self::log_payload($log->payload);$identity=$payload['_idlm_delivery'] ?? null;
        if ($identity !== null) {
            if (!is_array($identity) || ($identity['version'] ?? 0)!==1 || ($identity['platform'] ?? '')!==$log->platform || (int)($identity['lead_id'] ?? 0)!==(int)$log->lead_id || ($identity['event'] ?? '')!==$log->event_name || !is_string($identity['stage'] ?? null) || !is_string($identity['destination'] ?? null) || (!is_int($identity['occurred_at'] ?? null) || $identity['occurred_at'] < 1) || !is_string($identity['event_id'] ?? null) || (!is_numeric($identity['value'] ?? null) || !is_finite((float)$identity['value']) || $identity['value'] < 0) || !is_string($identity['currency'] ?? null)) { return false; }
            if ('uncertain' === ($identity['outcome'] ?? '') || false === ($identity['retryable'] ?? true)) { return false; }
            return $identity;
        }
        // Legacy recovery is exact and unambiguous; never guess New or use substring aliases.
        $mappings=self::event_mappings($log->platform);$matches=array_keys($mappings,$log->event_name,true);
        $stage=$payload['data'][0]['custom_data']['stage'] ?? ($payload['events'][0]['params']['stage'] ?? null);
        if ($stage !== null) {
            if (!is_string($stage) || ($mappings[$stage] ?? '')!==$log->event_name) { return false; }
        } elseif (count($matches)===1) { $stage=$matches[0]; }
        else { return false; }
        if (!in_array($stage,array_merge(array_values(IDLM_State_Registry::stages()), (array)self::runtime_option('idlm_custom_stages',array())),true)) { return false; }
        $commercial=$payload['data'][0]['custom_data'] ?? ($payload['events'][0]['params'] ?? array());
        return array('stage'=>$stage,'event'=>$log->event_name,'destination'=>isset($payload['conversion']) ? str_replace('urn:lla:llaPartnerConversion:','',$payload['conversion']) : '', 'event_id'=>$payload['data'][0]['event_id'] ?? ('idlm_lead_'.$log->lead_id.'_'.$log->event_name),'occurred_at'=>$payload['data'][0]['event_time'] ?? (isset($payload['conversionHappenedAt']) ? (int)($payload['conversionHappenedAt']/1000) : time()),'value'=>(float)($commercial['value'] ?? ($payload['conversionValue']['amount'] ?? 0)), 'currency'=>$commercial['currency'] ?? ($payload['conversionValue']['currencyCode'] ?? self::runtime_option('idlm_currency_code','INR')),'retry_root'=>0);
    }

    /**
     * Meta Conversion API (CAPI) Upload
     */
    public static function fire_meta_capi($lead, $stage, $force = false) {
        return IDLM_Recovery_Guard::run(static function() use ($lead, $stage, $force) { return self::dispatch_platform('meta', $lead->id, $stage, $force); });
    }

    private static function fire_meta_capi_work($lead, $stage, $delivery) {
        return IDLM_CAPI_Transport::fire_meta_capi_work($lead, $stage, $delivery);
    }

    /**
     * Google Ads Offline Conversion (via GA4 Measurement Protocol)
     */
    public static function fire_google_capi($lead, $stage, $force = false) {
        return IDLM_Recovery_Guard::run(static function() use ($lead, $stage, $force) { return self::dispatch_platform('google', $lead->id, $stage, $force); });
    }

    private static function fire_google_capi_work($lead, $stage, $delivery) {
        return IDLM_CAPI_Transport::fire_google_capi_work($lead, $stage, $delivery);
    }

    /**
     * LinkedIn Conversion API Upload
     */
    public static function fire_linkedin_capi($lead, $stage, $force = false) {
        return IDLM_Recovery_Guard::run(static function() use ($lead, $stage, $force) { return self::dispatch_platform('linkedin', $lead->id, $stage, $force); });
    }

    private static function fire_linkedin_capi_work($lead, $stage, $delivery) {
        return IDLM_CAPI_Transport::fire_linkedin_capi_work($lead, $stage, $delivery);
    }

    /** Retry only a failed event, with a durable per-root attempt receipt. */
    public static function retry_capi_log($log_id) {
        $result=IDLM_Recovery_Guard::run(static function() use ($log_id) { return self::retry_capi_log_work(absint($log_id)); });
        return is_array($result) ? $result : self::no_attempt('Recovery is busy. Retry later.');
    }

    private static function retry_capi_log_work($log_id) {
        global $wpdb;
        try {
            // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- REL007-SQL-0455: retry_capi_log_work(): prefixed lead/log tables, fixed success/event predicates; values bound; IN placeholders match lead IDs plus platform/event parameters; receipts/history/current eligibility must not be cached.
            $log=$wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}idlm_capi_logs WHERE id = %d",$log_id));
            // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
            if ($wpdb->last_error || !$log || 'failed' !== $log->status || !in_array($log->platform,array('meta','google','linkedin'),true)) { return self::no_attempt('Select an existing failed conversion log.'); }
            $identity=self::retry_identity($log);
            if (!$identity) { return self::no_attempt('The original event is ambiguous, non-retryable or uncertain; review it before retrying.'); }
            $root=absint($identity['retry_root'] ?? 0) ?: $log_id;$generation=IDLM_Recovery_Guard::generation();
            if (null === $generation) { return self::no_attempt('Recovery state could not be verified.'); }
            $key='idlm_capi_retry_'.hash('sha256',$generation.'|'.$root);
            $lock=IDLM_Recovery_Guard::name('cr_'.substr(hash('sha256',$key),0,20));
            // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- REL007-SQL-0456: retry_capi_log_work(): prefixed lead/log tables, fixed success/event predicates; values bound; IN placeholders match lead IDs plus platform/event parameters; receipts/history/current eligibility must not be cached.
            if (1 !== (int)$wpdb->get_var($wpdb->prepare('SELECT GET_LOCK(%s, 0)',$lock))) { return self::no_attempt('This retry is already in progress.'); }
            // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
            try {
                $receipt=self::runtime_option($key,array('status'=>'ready','attempts'=>0,'root_log_id'=>$root,'generation'=>$generation));
                if (!is_array($receipt) || !isset($receipt['status'],$receipt['attempts']) || !is_int($receipt['attempts'])) { return self::no_attempt('Retry receipt is invalid; reconciliation is required.'); }
                if ('processing' === $receipt['status']) { $receipt['status']='uncertain';self::save_retry_receipt($key,$receipt); }
                if (in_array($receipt['status'],array('sent','uncertain','blocked'),true) || $receipt['attempts']>=3) { return self::no_attempt('This event was already retried or needs reconciliation; no new request was sent.'); }
                $identity['_attempt_id']=bin2hex(random_bytes(16));$identity['retry_root']=$root;
                $receipt['status']='processing';$receipt['attempts']++;$receipt['attempt_id']=$identity['_attempt_id'];$receipt['attempted_at']=time();
                if (!self::save_retry_receipt($key,$receipt)) { return self::no_attempt('Retry claim could not be persisted. No request was sent.'); }
                $result=self::dispatch_platform($log->platform,(int)$log->lead_id,$identity['stage'],false,$identity);
                if (!is_array($result) || !IDLM_Recovery_Guard::lease_valid()) { return self::no_attempt('Retry outcome is uncertain; reconciliation is required.'); }
                if (!$result['attempted']) { $receipt['attempts']--; }
                $receipt['status']=$result['success'] ? 'sent' : ($result['outcome']==='uncertain' ? 'uncertain' : ($result['attempted'] && !$result['retryable'] ? 'blocked' : 'failed'));
                $receipt['result_log_id']=$result['log_id'];
                if (!self::save_retry_receipt($key,$receipt)) { $result['success']=false;$result['message']='Retry acknowledgement failed; reconciliation is required.'; }
                return $result;
            // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- REL007-SQL-0457: retry_capi_log_work(): prefixed lead/log tables, fixed success/event predicates; values bound; IN placeholders match lead IDs plus platform/event parameters; receipts/history/current eligibility must not be cached.
            } finally { $wpdb->get_var($wpdb->prepare('SELECT RELEASE_LOCK(%s)',$lock)); }
            // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
        } catch (Throwable $error) { return self::no_attempt('Retry state could not be verified. No success was recorded.'); }
    }

    private static function save_retry_receipt($key,$receipt) {
        update_option($key,$receipt,false);
        return self::runtime_option($key) === $receipt;
    }

    /**
     * Get Google Ads Conversion events for leads with click IDs that transitioned to qualified/closed stages
     */
    public static function get_google_ads_conversion_events() {
        return IDLM_Google_Conversion_Feed::get_google_ads_conversion_events();
    }

    /**
     * Generate Google Ads Offline Conversion CSV response
     */
    public static function generate_google_ads_conversions_csv_response($terminate = true) {
        return IDLM_Google_Conversion_Feed::generate_google_ads_conversions_csv_response($terminate);
    }

    /**
     * Remove buffered notices or whitespace before a direct CSV download.
     */
    private static function clear_csv_output_buffers() {
        return IDLM_Google_Conversion_Feed::clear_csv_output_buffers();
    }

    /**
     * Format a WordPress-local database timestamp for Google Ads.
     *
     * Google requires a numeric UTC offset in yyyy-mm-dd HH:mm:ss+|-HH:mm form.
     */
    private static function format_google_ads_conversion_time($value) {
        return IDLM_Google_Conversion_Feed::format_google_ads_conversion_time($value);
    }

    /**
     * Format a phone number to E.164 standard.
     */
    public static function format_phone_e164($phone) {
        if (empty($phone)) {
            return '';
        }
        // Remove all characters except digits and '+'
        $cleaned = preg_replace('/[^0-9+]/', '', $phone);
        if (empty($cleaned)) {
            return '';
        }

        // If it starts with '00', replace it with '+'
        if (strpos($cleaned, '00') === 0) {
            $cleaned = '+' . substr($cleaned, 2);
        }

        // If it is 10 digits (no country code), prepend India prefix '+91'
        if (strlen($cleaned) === 10 && $cleaned[0] !== '+') {
            $cleaned = '+91' . $cleaned;
        }

        // If it starts with '91' and is 12 digits, prepend '+'
        if (strlen($cleaned) === 12 && strpos($cleaned, '91') === 0) {
            $cleaned = '+' . $cleaned;
        }

        // If it doesn't start with '+', prepend it
        if ($cleaned[0] !== '+') {
            $cleaned = '+' . $cleaned;
        }

        return $cleaned;
    }

    /**
     * Normalize and hash first/last name for Google Customer Match.
     */
    public static function normalize_name_for_hash($name) {
        if (empty($name)) {
            return '';
        }
        // Remove common prefixes (case-insensitive)
        $prefixes = array(
            '/^(mr\.|mrs\.|ms\.|dr\.|prof\.|shri\.|smt\.)\s+/i',
            '/^(mr|mrs|ms|dr|prof|shri|smt)\s+/i'
        );
        $cleaned = preg_replace($prefixes, '', trim($name));

        // Convert to lowercase
        $cleaned = mb_strtolower($cleaned, 'UTF-8');

        // Keep lowercase alphabetical characters only (a-z)
        $cleaned = preg_replace('/[^a-z]/', '', $cleaned);

        if (empty($cleaned)) {
            return '';
        }

        return hash('sha256', $cleaned);
    }
}
