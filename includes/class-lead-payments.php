<?php
/** Checked lead-owned payment operations; no project/lead lifecycle mutations. Builder: Shashi. */
if (!defined('ABSPATH')) { exit; }
final class IDLM_Lead_Payments {
    private static function table($key) {
        global $wpdb;
        $tables = array('schedules', 'confirmations', 'versions', 'allocation_sets', 'events');
        if (!in_array($key, $tables, true)) { throw new InvalidArgumentException('Unknown payment table.'); }
        return $wpdb->prefix . 'idlm_lead_payment_' . $key;
    }
    private static function error($code, $message) { return new WP_Error('idlm_payment_' . $code, $message); }
    public static function permitted($lead_id, $correction = false, $lock = false) {
        if (self::sales_permitted($lead_id, $correction, $lock)) { return true; }
        if ($correction) { return false; }
        if ($lock) { $actor = get_current_user_id(); wp_set_current_user(0); wp_set_current_user($actor); }
        return (bool) IDLM_Design_Handoffs::can_record_payment($lead_id);
    }
    // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared -- Custom IDLM tables named from $wpdb->prefix; values are bound with prepare(), cast to int, or come from fixed clause builders and generated placeholder lists; request-scoped reads and transactional writes are deliberately uncached. Builder: Shashi.
    private static function sales_permitted($lead_id, $correction, $lock) {
        global $wpdb;
        if ($lock) {
            $actor = get_current_user_id();
            if (!IDLM_Lead_Access_Policy::is_assignable_rep($actor, 'any', true)) { return false; }
            wp_set_current_user(0); wp_set_current_user($actor);
        }
        if (!IDLM_Lead_Access_Policy::has_rep_access() || IDLM_Lead_Access_Policy::is_pre_sales()) { return false; }
        $capability = $correction ? 'idlm_correct_lead_payments' : 'idlm_record_lead_payments';
        if (!current_user_can('manage_options') && !current_user_can($capability)) { return false; }
        $lead = IDLM_Lead_Transaction::read($wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}idlm_leads WHERE id = %d" . ($lock ? ' FOR UPDATE' : ''), $lead_id)));
        return $lead && (IDLM_Lead_Access_Policy::can_assign() || (int) $lead->assigned_rep_id === get_current_user_id());
    }
    // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared
    // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter -- Custom IDLM tables named from $wpdb->prefix; values are bound with prepare(), cast to int, or come from fixed clause builders and generated placeholder lists; request-scoped reads and transactional writes are deliberately uncached. Builder: Shashi.
    /** Current record version for a form, read without taking any lock. */
    public static function version($lead_id) {
        global $wpdb; $table = self::table('events');
        return (int) $wpdb->get_var($wpdb->prepare("SELECT id FROM $table WHERE lead_id = %d ORDER BY id DESC LIMIT 1", (int) $lead_id));
    }
    // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter
    // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter -- Custom IDLM tables named from $wpdb->prefix; values are bound with prepare(), cast to int, or come from fixed clause builders and generated placeholder lists; request-scoped reads and transactional writes are deliberately uncached. Builder: Shashi.
    private static function latest($lead_id, $key) {
        global $wpdb; $table = self::table($key);
        return IDLM_Lead_Transaction::read($wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE lead_id = %d ORDER BY id DESC LIMIT 1 FOR UPDATE", $lead_id), ARRAY_A));
    }
    // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter
    // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter -- Custom IDLM tables named from $wpdb->prefix; values are bound with prepare(), cast to int, or come from fixed clause builders and generated placeholder lists; request-scoped reads and transactional writes are deliberately uncached. Builder: Shashi.
    private static function receipts($lead_id) {
        global $wpdb; $versions = self::table('versions'); $confirmations = self::table('confirmations');
        return IDLM_Lead_Transaction::read($wpdb->get_results($wpdb->prepare("SELECT v.* FROM $confirmations c INNER JOIN $versions v ON v.id = c.current_version_id AND v.confirmation_id = c.id AND v.lead_id = c.lead_id WHERE c.lead_id = %d ORDER BY c.id FOR UPDATE", $lead_id), ARRAY_A));
    }
    // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter
    /** A read-only snapshot consistent across schedule/receipts/event pointers. */
    public static function read($lead_id) {
        if (!IDLM_Lead_Access_Policy::has_rep_access() || !IDLM_Lead_Access_Policy::can_access($lead_id, true)) { return self::error('denied', 'Payment access denied.'); }
        return self::operate($lead_id, null, static function() use ($lead_id) {
            $schedule = self::latest($lead_id, 'schedules'); $event = self::latest($lead_id, 'events');
            $set = self::latest($lead_id, 'allocation_sets');
            return array('version' => $event ? (int) $event['id'] : 0, 'schedule' => $schedule,
                'balance' => $set ? json_decode($set['snapshot'], true) : null, 'allocation_set_id' => $set ? (int) $set['id'] : 0,
                'receipts' => self::receipts($lead_id));
        });
    }
    // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter -- Custom IDLM tables named from $wpdb->prefix; values are bound with prepare(), cast to int, or come from fixed clause builders and generated placeholder lists; request-scoped reads and transactional writes are deliberately uncached. Builder: Shashi.
    /** Serialize on the lead, then refresh effective grants. No nested foreign owner. */
    private static function operate($lead_id, $correction, $callback) {
        global $wpdb;
        if (!is_int($lead_id) || $lead_id <= 0) { return self::error('identity', 'Select a valid lead.'); }
        $result = null;
        $transaction = IDLM_Lead_Transaction::run(static function() use ($lead_id, $correction, $callback, &$result, $wpdb) {
            // Pin every new table in this transaction before checking its engine.
            foreach (array_merge(IDLM_Lead_Payment_Schema::tables(), array('idlm_private_files', 'idlm_quotes', 'idlm_quote_revisions')) as $suffix) {
                $table = $wpdb->prefix . $suffix;
                IDLM_Lead_Transaction::read($wpdb->get_var("SELECT id FROM $table LIMIT 1"));
                $engine = IDLM_Lead_Transaction::read($wpdb->get_var($wpdb->prepare('SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = %s', $table)));
                IDLM_Lead_Transaction::required('INNODB' === strtoupper((string) $engine));
            }
            $lead = IDLM_Lead_Transaction::read($wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}idlm_leads WHERE id = %d FOR UPDATE", $lead_id)));
            if (null === $correction) {
                // Reads still refresh grants after the lock wait, including suspension.
                $actor = get_current_user_id();
                $fresh = IDLM_Lead_Access_Policy::is_assignable_rep($actor, 'any', true);
                wp_set_current_user(0); wp_set_current_user($actor);
                $allowed = $fresh && IDLM_Lead_Access_Policy::has_rep_access() && IDLM_Lead_Access_Policy::can_access($lead_id, true);
            } elseif ('design' === $correction) {
                // Customer approved: refresh live grants; the caller checks the handoff itself.
                $actor = get_current_user_id();
                IDLM_Lead_Access_Policy::is_assignable_rep($actor, 'any', true);
                wp_set_current_user(0); wp_set_current_user($actor);
                $allowed = IDLM_Design_Handoffs::can_manage();
            } else { $allowed = self::permitted($lead_id, $correction, true); }
            if (!$lead || !$allowed) { $result = self::error('denied', 'Payment access denied.'); return true; }
            $result = call_user_func($callback, $lead);
            return true;
        });
        return is_wp_error($transaction) ? $transaction : $result;
    }
    // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter
    // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter -- Custom IDLM tables named from $wpdb->prefix; values are bound with prepare(), cast to int, or come from fixed clause builders and generated placeholder lists; request-scoped reads and transactional writes are deliberately uncached. Builder: Shashi.
    private static function request($lead_id, $operation, $data, $version, $key) {
        global $wpdb;
        if (!is_int($version) || $version < 0 || !is_string($key) || !preg_match('/^[A-Za-z0-9_-]{16,64}$/D', $key)) { return self::error('request', 'A record version and unique request key are required.'); }
        $hash = hash('sha256', wp_json_encode(array($operation, $data, get_current_user_id())));
        $events = self::table('events');
        $prior = IDLM_Lead_Transaction::read($wpdb->get_row($wpdb->prepare("SELECT * FROM $events WHERE lead_id = %d AND request_key = %s FOR UPDATE", $lead_id, $key), ARRAY_A));
        if ($prior) {
            return hash_equals($prior['request_hash'], $hash) ? array('replayed' => true, 'event' => $prior) : self::error('request_reused', 'This request key belongs to different payment details.');
        }
        $latest = self::latest($lead_id, 'events');
        if ($version !== ($latest ? (int) $latest['id'] : 0)) { return self::error('stale', 'Payment details changed. Reload before saving.'); }
        return $hash;
    }
    // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter
    // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery -- Custom IDLM tables named from $wpdb->prefix; values are bound with prepare(), cast to int, or come from fixed clause builders and generated placeholder lists; request-scoped reads and transactional writes are deliberately uncached. Builder: Shashi.
    private static function insert($key, $values) {
        global $wpdb; IDLM_Lead_Transaction::required(1 === $wpdb->insert(self::table($key), $values));
        return (int) $wpdb->insert_id;
    }
    // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery
    private static function finish($lead_id, $schedule, $confirmation_id, $receipt_version_id, $type, $hash, $key) {
        $balance = IDLM_Lead_Payment_Ledger::balance(json_decode($schedule['snapshot'], true), self::receipts($lead_id));
        IDLM_Lead_Transaction::required($balance);
        $now = current_time('mysql', true); $actor = get_current_user_id();
        $set = self::insert('allocation_sets', array('lead_id' => $lead_id, 'schedule_id' => $schedule['id'], 'snapshot' => wp_json_encode($balance), 'actor_id' => $actor, 'created_at' => $now));
        $event = array('lead_id' => $lead_id, 'request_key' => $key, 'request_hash' => $hash, 'event_type' => $type, 'schedule_id' => $schedule['id'],
            'confirmation_id' => $confirmation_id, 'receipt_version_id' => $receipt_version_id, 'allocation_set_id' => $set, 'actor_id' => $actor, 'occurred_at' => $now);
        $event['id'] = self::insert('events', $event);
        // Versioned, minimal envelope; raw references, screenshots and customer details stay private.
        IDLM_Lead_Transaction::defer('idlm_lead_payment_' . $type, $lead_id, array('schema_version' => 1, 'event_id' => $event['id'], 'schedule_id' => (int) $schedule['id'], 'receipt_version_id' => $receipt_version_id, 'actor_id' => $actor, 'occurred_at' => $now));
        return array('replayed' => false, 'event' => $event, 'balance' => $balance);
    }
    public static function publish_schedule($lead_id, $revision_id, $advance_positions, $reason, $expected_version, $request_key) {
        return self::publish($lead_id, $revision_id, $advance_positions, $reason, $expected_version, $request_key);
    }
    public static function resolve_legacy_schedule($lead_id, $revision_id, $resolution, $reason, $expected_version, $request_key) {
        if (!is_array($resolution) || !is_string($reason) || strlen(trim(sanitize_textarea_field($reason))) < 10) { return self::error('reason', 'Record a meaningful reason for recovering the historic timeline.'); }
        return self::publish($lead_id, $revision_id, array(), $reason, $expected_version, $request_key, $resolution);
    }
    // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom IDLM tables named from $wpdb->prefix; values are bound with prepare(), cast to int, or come from fixed clause builders and generated placeholder lists; request-scoped reads and transactional writes are deliberately uncached. Builder: Shashi.
    private static function publish($lead_id, $revision_id, $advance_positions, $reason, $expected_version, $request_key, $resolution = null) {
        if (!is_int($revision_id) || $revision_id <= 0 || !is_string($reason) || !trim(sanitize_textarea_field($reason)) || strlen($reason) > 8000) { return self::error('schedule', 'Select the exact agreed revision and record a schedule publication reason.'); }
        return self::operate($lead_id, null !== $resolution, static function($lead) use ($lead_id, $revision_id, $advance_positions, $reason, $expected_version, $request_key, $resolution) {
            global $wpdb;
            $operation = null === $resolution ? 'publish' : 'resolve_legacy';
            $request_data = null === $resolution ? array($revision_id, $advance_positions, $reason) : array($revision_id, $resolution, $reason);
            $hash = self::request($lead_id, $operation, $request_data, $expected_version, $request_key);
            if (!is_string($hash)) { return $hash; }
            $prior = self::latest($lead_id, 'schedules');
            if ($prior && !self::permitted($lead_id, true)) { return self::error('denied', 'Schedule amendments require the separate payment-correction grant.'); }
            if ($prior && IDLM_Design_Handoffs::locks_schedule($lead_id)) { return self::error('design_locked', 'This project is with design. The schedule changes when a revision is marked Customer approved.'); }
            if ('Won' !== $lead->stage || !empty($lead->is_duplicate)) { return self::error('won', 'Record payments against a canonical Won lead.'); }
            // Lock order: lead / actor grants, schedule context, quote, exact revision.
            $quote = IDLM_Lead_Transaction::read($wpdb->get_row($wpdb->prepare("SELECT q.* FROM {$wpdb->prefix}idlm_quotes q INNER JOIN {$wpdb->prefix}idlm_quote_revisions r ON r.quote_id = q.id WHERE r.id = %d AND q.lead_id = %d FOR UPDATE", $revision_id, $lead_id)));
            $revision = IDLM_Lead_Transaction::read($wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}idlm_quote_revisions WHERE id = %d FOR UPDATE", $revision_id)));
            if (!$quote || !$revision) { return self::error('revision', 'The selected revision does not belong to this lead.'); }
            // A design revision is a draft price. Only Customer approved may move payments.
            if (IDLM_Design_Revisions::is_design_revision($revision_id)) { return self::error('revision', 'Payment schedules cannot use a design revision until the customer approves it.'); }
            $snapshot = null === $resolution ? IDLM_Lead_Payment_Ledger::schedule($revision, $advance_positions) : IDLM_Lead_Payment_Ledger::legacy_schedule($revision, $resolution);
            if (is_wp_error($snapshot)) { return $snapshot; }
            if (isset($snapshot['resolution'])) { $snapshot['resolution']['resolved_by'] = get_current_user_id(); $snapshot['resolution']['resolved_at'] = current_time('mysql', true); }
            if ($prior && $snapshot['currency'] !== $prior['currency']) { return self::error('currency', 'Schedule amendments must preserve the receipt currency.'); }
            // Validate the complete replacement allocation set before any write.
            $balance = IDLM_Lead_Payment_Ledger::balance($snapshot, self::receipts($lead_id));
            if (is_wp_error($balance)) { return $balance; }
            $id = self::insert('schedules', array('lead_id' => $lead_id, 'version_no' => $prior ? (int) $prior['version_no'] + 1 : 1, 'quote_id' => $quote->id,
                'revision_id' => $revision_id, 'currency' => $snapshot['currency'], 'basis_total' => $snapshot['basis_total'], 'snapshot' => wp_json_encode($snapshot),
                'supersedes_id' => $prior ? $prior['id'] : 0, 'reason' => sanitize_textarea_field($reason), 'actor_id' => get_current_user_id(), 'created_at' => current_time('mysql', true)));
            return self::finish($lead_id, array('id' => $id, 'snapshot' => wp_json_encode($snapshot)), 0, 0, 'schedule_published', $hash, $request_key);
        });
    }
    // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
    /**
     * Legacy deal with only a Won amount: a plan with no quotation (quote_id and
     * revision_id 0). The first plan needs the record grant; changing one needs the
     * correction grant and is refused once design has accepted. Never used when an
     * agreed quotation with milestones exists.
     */
    public static function publish_won_amount($lead_id, $milestones, $reason, $expected_version, $request_key) {
        if (!is_array($milestones) || !is_string($reason) || !trim(sanitize_textarea_field($reason)) || strlen($reason) > 8000) { return self::error('schedule', 'Enter the milestones and a reason.'); }
        return self::operate($lead_id, false, static function($lead) use ($lead_id, $milestones, $reason, $expected_version, $request_key) {
            $hash = self::request($lead_id, 'won_amount', array($milestones, $reason), $expected_version, $request_key);
            if (!is_string($hash)) { return $hash; }
            if ('Won' !== $lead->stage || !empty($lead->is_duplicate)) { return self::error('won', 'Record payments against a canonical Won lead.'); }
            $prior = self::latest($lead_id, 'schedules');
            if ($prior && !self::permitted($lead_id, true)) { return self::error('denied', 'Changing the payment plan requires the separate payment-correction grant.'); }
            if ($prior && IDLM_Design_Handoffs::locks_schedule($lead_id)) { return self::error('design_locked', 'This project is with design. The plan changes when a revision is marked Customer approved.'); }
            if (!$prior && IDLM_Design_Handoffs::basis($lead_id)) { return self::error('basis', 'This lead has an agreed quotation with milestones. Set the plan from it.'); }
            $currency = $prior ? $prior['currency'] : strtoupper((string) get_option('idlm_currency_code', 'INR'));
            $snapshot = IDLM_Lead_Payment_Ledger::won_schedule((string) $lead->deal_value, $currency, $milestones);
            if (is_wp_error($snapshot)) { return $snapshot; }
            $snapshot['resolution']['resolved_by'] = get_current_user_id(); $snapshot['resolution']['resolved_at'] = current_time('mysql', true);
            $balance = IDLM_Lead_Payment_Ledger::balance($snapshot, self::receipts($lead_id));
            if (is_wp_error($balance)) { return $balance; }
            $id = self::insert('schedules', array('lead_id' => $lead_id, 'version_no' => $prior ? (int) $prior['version_no'] + 1 : 1, 'quote_id' => 0,
                'revision_id' => 0, 'currency' => $snapshot['currency'], 'basis_total' => $snapshot['basis_total'], 'snapshot' => wp_json_encode($snapshot),
                'supersedes_id' => $prior ? $prior['id'] : 0, 'reason' => sanitize_textarea_field($reason), 'actor_id' => get_current_user_id(), 'created_at' => current_time('mysql', true)));
            return self::finish($lead_id, array('id' => $id, 'snapshot' => wp_json_encode($snapshot)), 0, 0, 'schedule_published', $hash, $request_key);
        });
    }
    // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom IDLM tables named from $wpdb->prefix; values are bound with prepare(), cast to int, or come from fixed clause builders and generated placeholder lists; request-scoped reads and transactional writes are deliberately uncached. Builder: Shashi.
    /**
     * Customer approved: one new schedule version on a design revision, plus the
     * caller's handoff writes, in one transaction. Receipts are not touched; the
     * complete allocation set is recalculated against the new amounts.
     */
    public static function publish_customer_approved($lead_id, $revision_id, $reason, $expected_version, $request_key, $apply) {
        return self::operate($lead_id, 'design', static function($lead) use ($lead_id, $revision_id, $reason, $expected_version, $request_key, $apply) {
            global $wpdb;
            $hash = self::request($lead_id, 'customer_approved', array($revision_id, $reason), $expected_version, $request_key);
            if (!is_string($hash)) { return $hash; }
            if ('Won' !== $lead->stage || !empty($lead->is_duplicate)) { return self::error('won', 'This lead is no longer Won. Review it with sales.'); }
            $row = IDLM_Design_Revisions::check_approval($lead_id, $revision_id);
            if (is_wp_error($row)) { return $row; }
            $quote = IDLM_Lead_Transaction::read($wpdb->get_row($wpdb->prepare("SELECT q.* FROM {$wpdb->prefix}idlm_quotes q INNER JOIN {$wpdb->prefix}idlm_quote_revisions r ON r.quote_id = q.id WHERE r.id = %d AND q.lead_id = %d FOR UPDATE", $revision_id, $lead_id)));
            $revision = IDLM_Lead_Transaction::read($wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}idlm_quote_revisions WHERE id = %d FOR UPDATE", $revision_id)));
            if (!$quote || !$revision) { return self::error('revision', 'The selected revision does not belong to this lead.'); }
            $prior = self::latest($lead_id, 'schedules');
            $snapshot = IDLM_Design_Revisions::approved_schedule($revision, $prior);
            if (is_wp_error($snapshot)) { return $snapshot; }
            if ($prior && $snapshot['currency'] !== $prior['currency']) { return self::error('currency', 'Schedule amendments must preserve the receipt currency.'); }
            $balance = IDLM_Lead_Payment_Ledger::balance($snapshot, self::receipts($lead_id));
            if (is_wp_error($balance)) { return $balance; }
            $id = self::insert('schedules', array('lead_id' => $lead_id, 'version_no' => $prior ? (int) $prior['version_no'] + 1 : 1, 'quote_id' => $quote->id,
                'revision_id' => $revision_id, 'currency' => $snapshot['currency'], 'basis_total' => $snapshot['basis_total'], 'snapshot' => wp_json_encode($snapshot),
                'supersedes_id' => $prior ? $prior['id'] : 0, 'reason' => sanitize_textarea_field($reason), 'actor_id' => get_current_user_id(), 'created_at' => current_time('mysql', true)));
            $result = self::finish($lead_id, array('id' => $id, 'snapshot' => wp_json_encode($snapshot)), 0, 0, 'schedule_published', $hash, $request_key);
            call_user_func($apply, $row, $revision, $id, $snapshot['basis_total']);
            return $result;
        });
    }
    // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
    public static function record($lead_id, $data, $expected_version, $request_key) {
        return self::write_receipt($lead_id, 0, $data, '', false, $expected_version, $request_key);
    }
    public static function correct($lead_id, $confirmation_id, $data, $reason, $expected_version, $request_key) {
        if (!is_int($confirmation_id) || $confirmation_id <= 0) { return self::error('identity', 'Select a receipt to correct.'); }
        return self::write_receipt($lead_id, $confirmation_id, $data, $reason, false, $expected_version, $request_key);
    }
    public static function revoke($lead_id, $confirmation_id, $reason, $expected_version, $request_key) {
        if (!is_int($confirmation_id) || $confirmation_id <= 0) { return self::error('identity', 'Select a receipt to revoke.'); }
        return self::write_receipt($lead_id, $confirmation_id, null, $reason, true, $expected_version, $request_key);
    }
    // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter -- Custom IDLM tables named from $wpdb->prefix; values are bound with prepare(), cast to int, or come from fixed clause builders and generated placeholder lists; request-scoped reads and transactional writes are deliberately uncached. Builder: Shashi.
    private static function write_receipt($lead_id, $confirmation_id, $data, $reason, $revoke, $expected_version, $request_key) {
        if ($confirmation_id && (!is_string($reason) || strlen(trim(sanitize_textarea_field($reason))) < 5 || strlen($reason) > 8000)) { return self::error('reason', 'A meaningful correction or revocation reason is required.'); }
        $receipt = $revoke ? null : IDLM_Lead_Payment_Ledger::receipt($data);
        if (is_wp_error($receipt)) { return $receipt; }
        return self::operate($lead_id, (bool) $confirmation_id, static function($lead) use ($lead_id, $confirmation_id, $receipt, $reason, $revoke, $expected_version, $request_key) {
            global $wpdb;
            $type = $revoke ? 'revoked' : ($confirmation_id ? 'corrected' : 'recorded');
            $hash = self::request($lead_id, $type, array($confirmation_id, $receipt, $reason), $expected_version, $request_key);
            if (!is_string($hash)) { return $hash; }
            $schedule = self::latest($lead_id, 'schedules');
            if (!$schedule) { return self::error('schedule_missing', 'Publish the exact agreed revision payment timeline first.'); }
            if (!$confirmation_id && ('Won' !== $lead->stage || !empty($lead->is_duplicate))) { return self::error('won', 'New receipts require a canonical Won lead.'); }
            $old = null;
            foreach (self::receipts($lead_id) as $row) { if ((int) $row['confirmation_id'] === $confirmation_id) { $old = $row; break; } }
            if ($confirmation_id && !$old) { return self::error('receipt_missing', 'Receipt unavailable for this lead.'); }
            if ($old && 'revoked' === $old['status']) { return self::error('revoked', 'A revoked receipt cannot be edited. Record a new receipt if needed.'); }
            $value = $revoke ? array_intersect_key($old, array_flip(array('amount', 'currency', 'received_date', 'method', 'reference', 'normalized_reference', 'attachment_id', 'note', 'duplicate_review_reason'))) : $receipt;
            if ($value['currency'] !== $schedule['currency']) { return self::error('currency', 'Receipt currency must match the saved payment schedule.'); }
            if (!$revoke && $value['attachment_id']) {
                $files = $wpdb->prefix . 'idlm_private_files';
                $file = IDLM_Lead_Transaction::read($wpdb->get_row($wpdb->prepare("SELECT * FROM $files WHERE attachment_id = %d AND lead_id = %d AND purpose = 'payment' AND state IN ('uploaded','active') FOR UPDATE", $value['attachment_id'], $lead_id)));
                if (!$file || !in_array($file->mime, array('image/jpeg', 'image/png', 'image/webp'), true) || $file->byte_size > IDLM_Private_Files::MAX_BYTES || ($file->state === 'uploaded' && strtotime($file->created_at) < strtotime(current_time('mysql')) - 7 * DAY_IN_SECONDS)) { return self::error('proof', 'Upload a current private payment screenshot for this lead.'); }
            }
            if (!$revoke && $value['normalized_reference']) {
                foreach (self::receipts($lead_id) as $existing) {
                    if ((int) $existing['confirmation_id'] !== $confirmation_id && $existing['status'] !== 'revoked' && $existing['method'] === $value['method'] && $existing['normalized_reference'] === $value['normalized_reference'] && strlen(trim($value['duplicate_review_reason'])) < 5) {
                        return self::error('duplicate_reference', 'This lead already has a receipt with that method and reference. Review the existing receipt and enter a meaningful duplicate reference review reason to confirm a separate receipt.');
                    }
                }
            }
            if (!$confirmation_id) { $confirmation_id = self::insert('confirmations', array('lead_id' => $lead_id, 'created_by' => get_current_user_id(), 'created_at' => current_time('mysql', true))); }
            $value += array('lead_id' => $lead_id, 'confirmation_id' => $confirmation_id, 'version_no' => $old ? (int) $old['version_no'] + 1 : 1,
                'status' => $revoke ? 'revoked' : 'confirmed', 'reason' => sanitize_textarea_field($reason), 'supersedes_id' => $old ? $old['id'] : 0,
                'actor_id' => get_current_user_id(), 'recorded_at' => current_time('mysql', true));
            $version_id = self::insert('versions', $value);
            IDLM_Lead_Transaction::required(1 === $wpdb->update(self::table('confirmations'), array('current_version_id' => $version_id), array('id' => $confirmation_id, 'lead_id' => $lead_id)));
            if (!$revoke && $value['attachment_id']) {
                IDLM_Lead_Transaction::required(false !== $wpdb->update($wpdb->prefix . 'idlm_private_files', array('state' => 'active'), array('attachment_id' => $value['attachment_id'], 'lead_id' => $lead_id, 'purpose' => 'payment')));
            }
            return self::finish($lead_id, $schedule, $confirmation_id, $version_id, $type, $hash, $request_key);
        });
    }
    // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter
    // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter -- Custom IDLM tables named from $wpdb->prefix; values are bound with prepare(), cast to int, or come from fixed clause builders and generated placeholder lists; request-scoped reads and transactional writes are deliberately uncached. Builder: Shashi.
    /** Historical proof remains protected and referenced after correction/revocation. */
    public static function references_file($lead_id, $attachment_id) {
        global $wpdb; $table = self::table('versions');
        return (bool) $wpdb->get_var($wpdb->prepare("SELECT id FROM $table WHERE lead_id = %d AND attachment_id = %d LIMIT 1", $lead_id, $attachment_id));
    }
    // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter
    // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter -- Custom IDLM tables named from $wpdb->prefix; values are bound with prepare(), cast to int, or come from fixed clause builders and generated placeholder lists; request-scoped reads and transactional writes are deliberately uncached. Builder: Shashi.
    /** Partial JSON recovery cannot safely replace the ledger or quote basis yet. */
    public static function partial_recovery_safe() {
        global $wpdb; $table = self::table('schedules');
        $count = $wpdb->get_var("SELECT COUNT(*) FROM $table");
        return !$wpdb->last_error && '0' === (string) $count && IDLM_Design_Handoffs::partial_recovery_safe();
    }
    // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter
}
