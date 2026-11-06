<?php
/**
 * Which contact details identify a customer. A made-up email such as na@gmail.com, or one
 * typed for several different phone numbers, says nothing about who the customer is, so it
 * never matches leads together and is never sent to ad platforms. Phone numbers stay the
 * main identity. Builder: Shashi.
 */
if (!defined('ABSPATH')) { exit; }

final class IDLM_Contact_Identity {
    public static function init() {
        add_action('admin_post_idlm_clear_placeholder_emails', array(__CLASS__, 'handle_clear'));
    }

    public static function handle_clear() {
        check_admin_referer('idlm_clear_placeholder_emails');
        if (!current_user_can('manage_options')) { wp_die('Only an administrator can clear emails.', '', array('response' => 403)); }
        // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Each ID is absint()ed and checked against the review list under a lock.
        $ids = isset($_POST['lead_ids']) && is_array($_POST['lead_ids']) ? array_map('absint', wp_unslash($_POST['lead_ids'])) : array();
        $result = $ids ? self::clear_emails($ids) : 0;
        $fallback = add_query_arg(array('idlm_view' => 'settings', 'tab' => 'health'), IDLM_Admin_Shortcodes::workspace_url());
        $base = wp_validate_redirect(isset($_POST['idlm_return_url']) ? esc_url_raw(wp_unslash($_POST['idlm_return_url'])) : '', $fallback);
        if (is_wp_error($result)) { wp_die(esc_html($result->get_error_message()), 'Not saved', array('response' => 422, 'back_link' => true)); }
        wp_safe_redirect(add_query_arg(array('tab' => 'health', 'email_cleared' => (int) $result), $base)); exit;
    }

    /** Local parts people type when a customer gave no email. */
    const PLACEHOLDER_LOCALS = array(
        'na', 'n.a', 'n/a', 'nan', 'nil', 'null', 'none', 'no', 'nomail', 'noemail', 'no.email', 'no-email', 'no_email',
        'noid', 'notavailable', 'not.available', 'notprovided', 'unknown', 'test', 'testing', 'demo', 'dummy', 'sample',
        'abc', 'abcd', 'xyz', 'asdf', 'qwerty', 'customer', 'client', 'email', 'mail', 'info', 'user', 'name', 'noreply', 'no-reply',
    );
    /** Domains that never deliver to a real customer. */
    const PLACEHOLDER_DOMAINS = array('example.com', 'example.org', 'test.com', 'email.com', 'domain.com', 'abc.com', 'xyz.com', 'mailinator.com');

    /**
     * Pure: true when an email cannot be a real customer's address. $phone, when given,
     * catches an email made from the customer's own number (9876543210@gmail.com).
     */
    public static function is_placeholder_email($email, $phone = '') {
        $email = strtolower(trim((string) $email));
        if ('' === $email) { return false; }
        if (!is_email($email)) { return true; }
        list($local, $domain) = explode('@', $email, 2);
        $bare = preg_replace('/[^a-z0-9]/', '', $local);
        if (in_array($local, self::PLACEHOLDER_LOCALS, true) || in_array($bare, self::PLACEHOLDER_LOCALS, true)) { return true; }
        if (in_array($domain, self::PLACEHOLDER_DOMAINS, true)) { return true; }
        // A single character, one character repeated (x@, aaa@, 0000@), or a short run of digits (123@).
        if ('' === $bare || preg_match('/^(.)\1*$/', $bare) || preg_match('/^[0-9]{1,5}$/', $bare)) { return true; }
        $digits = preg_replace('/[^0-9]/', '', (string) $phone);
        if (strlen($digits) >= 10 && false !== strpos($bare, substr($digits, -10))) { return true; }
        return false;
    }

    /** An email that may identify a customer, lower-cased, or '' when it cannot. */
    public static function matchable_email($email, $phone = '') {
        $email = strtolower(trim((string) $email));
        return '' === $email || self::is_placeholder_email($email, $phone) ? '' : $email;
    }

    // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom IDLM tables named from $wpdb->prefix; values are bound with prepare(), cast to int, or come from fixed clause builders and generated placeholder lists; request-scoped reads and transactional writes are deliberately uncached. Builder: Shashi.
    /**
     * An email already on leads with two or more different phone numbers is shared (a team
     * address, or one typed for everyone), so it no longer matches leads together.
     */
    public static function is_shared_email($email) {
        global $wpdb;
        $email = strtolower(trim((string) $email));
        if ('' === $email) { return false; }
        $phones = (array) $wpdb->get_col($wpdb->prepare("SELECT phone FROM {$wpdb->prefix}idlm_leads WHERE LOWER(email) = %s AND is_duplicate = 0 AND phone <> '' LIMIT 50", $email));
        $distinct = array();
        foreach ($phones as $phone) { $digits = preg_replace('/[^0-9]/', '', (string) $phone); if (strlen($digits) >= 10) { $distinct[substr($digits, -10)] = true; } }
        return count($distinct) >= 2;
    }
    // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching

    /** The email used to match a submission to existing leads, or '' when it should not be. */
    public static function email_for_matching($email, $phone = '') {
        $email = self::matchable_email($email, $phone);
        return '' !== $email && !self::is_shared_email($email) ? $email : '';
    }

    /** The email sent, hashed, to ad platforms: never a placeholder. */
    public static function email_for_ads($lead) {
        return self::matchable_email($lead->email ?? '', $lead->phone ?? '');
    }

    /** Last ten digits, the part every stored format of an Indian number shares. */
    public static function phone_key($phone) {
        $digits = preg_replace('/[^0-9]/', '', (string) $phone);
        return strlen($digits) >= 10 ? substr($digits, -10) : '';
    }

    // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter -- Custom IDLM tables named from $wpdb->prefix; values are bound with prepare(), cast to int, or come from fixed clause builders and generated placeholder lists; request-scoped reads and transactional writes are deliberately uncached. Builder: Shashi.
    /**
     * Existing leads with this phone, or this real email. Returns rows with id, names, stage,
     * assigned_rep_id and what matched ('phone' or 'email').
     */
    public static function existing_leads($phone, $email) {
        global $wpdb;
        $table = $wpdb->prefix . 'idlm_leads'; $found = array();
        $key = self::phone_key($phone);
        if ('' !== $key) {
            foreach ((array) $wpdb->get_results($wpdb->prepare("SELECT id, first_name, last_name, stage, assigned_rep_id, phone FROM $table WHERE is_duplicate = 0 AND phone LIKE %s ORDER BY id ASC LIMIT 20", '%' . $wpdb->esc_like($key))) as $row) {
                if (self::phone_key($row->phone) === $key) { $row->matched = 'phone'; $found[(int) $row->id] = $row; }
            }
        }
        $email = self::email_for_matching($email, $phone);
        if ('' !== $email) {
            foreach ((array) $wpdb->get_results($wpdb->prepare("SELECT id, first_name, last_name, stage, assigned_rep_id, phone FROM $table WHERE is_duplicate = 0 AND LOWER(email) = %s ORDER BY id ASC LIMIT 20", $email)) as $row) {
                if (!isset($found[(int) $row->id])) { $row->matched = 'email'; $found[(int) $row->id] = $row; }
            }
        }
        return array_values($found);
    }
    // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter

    // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom IDLM tables named from $wpdb->prefix; values are bound with prepare(), cast to int, or come from fixed clause builders and generated placeholder lists; request-scoped reads and transactional writes are deliberately uncached. Builder: Shashi.
    /**
     * Leads whose email is a placeholder or shared across different phone numbers, for the
     * administrator's review. Read-only; nothing is cleared without a separate request.
     */
    public static function review_rows($limit = 500) {
        global $wpdb;
        $rows = (array) $wpdb->get_results($wpdb->prepare("SELECT id, first_name, last_name, email, phone, stage, assigned_rep_id FROM {$wpdb->prefix}idlm_leads WHERE email <> '' AND is_duplicate = 0 ORDER BY id DESC LIMIT %d", 20000));
        $by_email = array();
        foreach ($rows as $row) { $key = self::phone_key($row->phone); if ('' !== $key) { $by_email[strtolower(trim($row->email))][$key] = true; } }
        $out = array();
        foreach ($rows as $row) {
            $email = strtolower(trim($row->email));
            $reason = self::is_placeholder_email($email, $row->phone) ? 'Placeholder' : (count($by_email[$email] ?? array()) >= 2 ? 'Shared by different phone numbers' : '');
            if ('' === $reason) { continue; }
            $row->reason = $reason; $out[] = $row;
            if (count($out) >= $limit) { break; }
        }
        return $out;
    }
    // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching

    // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom IDLM tables named from $wpdb->prefix; values are bound with prepare(), cast to int, or come from fixed clause builders and generated placeholder lists; request-scoped reads and transactional writes are deliberately uncached. Builder: Shashi.
    /**
     * Administrator only: clear the email on the chosen leads, if it still qualifies for review.
     * Logged on each lead. Returns the number cleared or WP_Error.
     */
    public static function clear_emails($lead_ids) {
        global $wpdb;
        if (!current_user_can('manage_options')) { return new WP_Error('idlm_identity_denied', 'Only an administrator can clear emails.'); }
        $allowed = array();
        foreach (self::review_rows(5000) as $row) { $allowed[(int) $row->id] = $row; }
        $cleared = 0;
        foreach (array_unique(array_map('absint', (array) $lead_ids)) as $id) {
            if (!isset($allowed[$id])) { continue; }
            $result = IDLM_Lead_Transaction::run(static function() use ($wpdb, $id, $allowed) {
                $current = IDLM_Lead_Transaction::read($wpdb->get_row($wpdb->prepare("SELECT id, email FROM {$wpdb->prefix}idlm_leads WHERE id = %d FOR UPDATE", $id)));
                if (!$current || strtolower(trim($current->email)) !== strtolower(trim($allowed[$id]->email))) { return false; }
                IDLM_Lead_Transaction::required($wpdb->update($wpdb->prefix . 'idlm_leads', array('email' => '', 'updated_at' => current_time('mysql')), array('id' => $id)));
                IDLM_Lead_Transaction::required(IDLM_DB::log_activity($id, 'update', 'Email ' . $allowed[$id]->email . ' cleared: ' . strtolower($allowed[$id]->reason) . ' email.', get_current_user_id()));
                return true;
            });
            if (true === $result) { $cleared++; }
        }
        return $cleared;
    }
    // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching

    // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom IDLM tables named from $wpdb->prefix; values are bound with prepare(), cast to int, or come from fixed clause builders and generated placeholder lists; request-scoped reads and transactional writes are deliberately uncached. Builder: Shashi.
    /**
     * The sales rep who asked pre-sales to call a lead they added, kept on that lead's manual
     * submission. Returns the user ID while they can still own leads, else 0.
     */
    public static function requested_rep($lead_id) {
        global $wpdb;
        $payload = $wpdb->get_var($wpdb->prepare("SELECT payload FROM {$wpdb->prefix}idlm_lead_touchpoints WHERE lead_id = %d AND platform = 'manual' ORDER BY id ASC LIMIT 1", (int) $lead_id));
        $data = json_decode((string) $payload, true);
        $rep = absint($data['return_to_rep_id'] ?? 0);
        if (!$rep) { return 0; }
        $user = get_userdata($rep);
        return $user && !in_array('idlm_pre_sales', (array) $user->roles, true) && IDLM_Lead_Access_Policy::is_assignable_rep($rep, 'any', IDLM_Lead_Transaction::active()) ? $rep : 0;
    }
    // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching

    // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom IDLM tables named from $wpdb->prefix; values are bound with prepare(), cast to int, or come from fixed clause builders and generated placeholder lists; request-scoped reads and transactional writes are deliberately uncached. Builder: Shashi.
    /** First recorded submission was the Add lead form. */
    public static function is_manual_lead($lead_id) {
        global $wpdb;
        return 'manual' === (string) $wpdb->get_var($wpdb->prepare("SELECT platform FROM {$wpdb->prefix}idlm_lead_touchpoints WHERE lead_id = %d ORDER BY id ASC LIMIT 1", (int) $lead_id));
    }
    // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching

    const AD_SOURCES = array('Meta Ads', 'Google Ads', 'LinkedIn Ads');
    const MANUAL_CONVERSIONS_OPTION = 'idlm_manual_lead_conversions';

    /**
     * Pure: may a lead added by hand send conversions? 'ad_sources' (default) sends only when
     * the customer came from an ad or carries an ad click; 'all' and 'none' are the plain choices.
     */
    public static function manual_conversion_allowed($lead, $policy) {
        if ('none' === $policy) { return false; }
        if ('all' === $policy) { return true; }
        foreach (array('gclid', 'gbraid', 'wbraid', 'fbclid', 'li_fat_id') as $click) { if (!empty($lead->$click)) { return true; } }
        return in_array((string) ($lead->original_source ?: $lead->source), self::AD_SOURCES, true);
    }
}
