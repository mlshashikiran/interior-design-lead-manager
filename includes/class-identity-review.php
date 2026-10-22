<?php
/** Identity-review presentation, replay and erasure policy. Builder: Shashi. */
if (!defined('ABSPATH')) { exit; }

final class IDLM_Identity_Review {
    const PRUNE_HOOK = 'idlm_prune_identity_reviews';

    public static function init() {
        add_action('admin_menu', array(__CLASS__, 'menu'));
        add_action('admin_post_idlm_resolve_identity_review', array(__CLASS__, 'handle'));
        add_action(self::PRUNE_HOOK, array(__CLASS__, 'prune'));
    }

    /** Keep only supported contact/project/attribution fields, never provider credentials or lifecycle input. */
    public static function submission($data) {
        $fields = explode(' ', 'first_name last_name email phone source project_name budget project_timeline possession_date property_location property_type num_rooms preferred_studio referrer_details fb_lead_id fbclid gclid gbraid wbraid li_fat_id utm_source utm_medium utm_campaign utm_adgroup utm_term utm_content landing_page');
        $out = array();
        foreach ($fields as $field) {
            if (isset($data[$field]) && is_scalar($data[$field])) { $out[$field] = 'email' === $field ? sanitize_email($data[$field]) : sanitize_text_field($data[$field]); }
        }
        return $out;
    }

    public static function data($review) {
        $stored = json_decode($review->submission_data ?? '', true);
        $data = self::submission(is_array($stored) ? $stored : array());
        // Old redacted payloads cannot reconstruct a name; retain their known contacts.
        $data['email'] = $review->email; $data['phone'] = $review->phone; $data['source'] = $review->source;
        if (!empty($review->submitted_at)) { $data['conversion_time'] = $review->submitted_at; }
        if (!empty($review->external_id)) { $data['fb_lead_id'] = $review->external_id; }
        return $data;
    }

    public static function candidate_ids($review) {
        $ids = json_decode($review->candidate_lead_ids, true);
        return is_array($ids) ? array_values(array_unique(array_filter(array_map('absint', $ids)))) : array();
    }

    /** Caller holds intake identity locks and a transaction. No external-ID replay may bypass a decision. */
    public static function replay($data, $platform) {
        global $wpdb;
        if (empty($data['fb_lead_id'])) { return null; }
        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- REL007-SQL-0303: replay(): fixed prefixed review/lead tables; status/platform/external-ID/date/cursor values bound and erasure lists absint-only; manager/replay/erasure decisions use live state.
        $review = IDLM_Lead_Transaction::read($wpdb->get_row($wpdb->prepare(
        // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
            "SELECT * FROM {$wpdb->prefix}idlm_inbound_submission_reviews WHERE platform = %s AND external_id = %s FOR UPDATE",
            $platform, sanitize_text_field($data['fb_lead_id'])
        )));
        if (!$review) { return null; }
        $target = absint($review->resolved_lead_id ?? 0);
        if ($target) {
            // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- REL007-SQL-0304: replay(): fixed prefixed review/lead tables; status/platform/external-ID/date/cursor values bound and erasure lists absint-only; manager/replay/erasure decisions use live state.
            $exists = IDLM_Lead_Transaction::read($wpdb->get_var($wpdb->prepare("SELECT id FROM {$wpdb->prefix}idlm_leads WHERE id = %d FOR UPDATE", $target)));
            // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
            IDLM_Lead_Transaction::required((bool) $exists);
        }
        return array('lead_id' => $target, 'created' => false, 'matched' => $target > 0,
            'requires_duplicate_review' => !$target, 'review_id' => absint($review->id), 'review_status' => $review->status);
    }

    public static function list_pending($after = 0, $status = 'pending') {
        global $wpdb;
        if (!IDLM_Lead_Access_Policy::can_assign()) { return new WP_Error('idlm_review_forbidden', 'Manager access is required.'); }
        if (!in_array($status, array('pending','matched','separate','rejected'), true)) { return new WP_Error('idlm_review_status', 'Invalid review status.'); }
        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- REL007-SQL-0305: list_pending(): fixed prefixed review/lead tables; status/platform/external-ID/date/cursor values bound and erasure lists absint-only; manager/replay/erasure decisions use live state.
        $rows = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$wpdb->prefix}idlm_inbound_submission_reviews WHERE status = %s AND id > %d ORDER BY id ASC LIMIT 25", $status, absint($after)));
        // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
        return $wpdb->last_error ? new WP_Error('idlm_review_read', 'Unable to read reviews. Please retry.') : $rows;
    }

    public static function candidate_lead($id) {
        global $wpdb;
        if (!IDLM_Lead_Access_Policy::can_assign()) { return null; }
        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- REL007-SQL-0306: candidate_lead(): fixed prefixed review/lead tables; status/platform/external-ID/date/cursor values bound and erasure lists absint-only; manager/replay/erasure decisions use live state.
        return $wpdb->get_row($wpdb->prepare("SELECT id, first_name, last_name, email, phone, is_duplicate FROM {$wpdb->prefix}idlm_leads WHERE id = %d", $id));
        // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
    }

    public static function menu() {
        if (!IDLM_Lead_Access_Policy::can_assign()) { return; }
        // Separate entry lets CRM managers reach this utility without administrator settings access.
        add_menu_page('Identity reviews', 'Identity reviews', current_user_can('manage_options') ? 'manage_options' : 'idlm_assign_leads', 'idlm_identity_reviews', array(__CLASS__, 'render'), 'dashicons-search', 27);
    }

    public static function render() {
        if (!IDLM_Lead_Access_Policy::can_assign()) { wp_die('Manager access is required.', '', array('response' => 403)); }
        nocache_headers();
        // phpcs:disable WordPress.Security.NonceVerification.Recommended -- REL007-BOUNDARY-0076: render(): manager-gated read-only review pagination/status use bounded scalar/enum values; resolution has its separate action nonce and current-manager check; these GET fields cannot resolve or mutate a review.
        $after = isset($_GET['after']) && is_scalar($_GET['after']) ? absint($_GET['after']) : 0;
        // phpcs:enable WordPress.Security.NonceVerification.Recommended
        // phpcs:disable WordPress.Security.NonceVerification.Recommended -- REL007-BOUNDARY-0077: render(): manager-gated read-only review pagination/status use bounded scalar/enum values; resolution has its separate action nonce and current-manager check; these GET fields cannot resolve or mutate a review.
        $status = isset($_GET['status']) && is_scalar($_GET['status']) ? sanitize_key($_GET['status']) : 'pending';
        // phpcs:enable WordPress.Security.NonceVerification.Recommended
        $rows = self::list_pending($after, $status);
        if (is_wp_error($rows)) { wp_die(esc_html($rows->get_error_message()), '', array('response' => 503)); }
        IDLM_View::display('admin/identity-reviews.php', array('rows' => $rows, 'after' => $after, 'status' => $status));
    }

    public static function nonce_action($id) {
        $generation = IDLM_Recovery_Guard::generation();
        return null === $generation ? '' : 'idlm_resolve_identity_review_' . absint($id) . '_g' . $generation;
    }

    public static function handle() {
        if ('POST' !== sanitize_text_field(wp_unslash($_SERVER['REQUEST_METHOD'] ?? ''))) { wp_die('POST is required.', '', array('response' => 405)); }
        if (!IDLM_Lead_Access_Policy::can_assign()) { wp_die('Manager access is required.', '', array('response' => 403)); }
        $input = wp_unslash($_POST);
        foreach (array('review_id', 'decision', 'lead_id', 'note', '_wpnonce') as $key) {
            if (isset($input[$key]) && !is_scalar($input[$key])) { wp_die('Invalid review request.', '', array('response' => 400)); }
        }
        $id = absint($input['review_id'] ?? 0);
        $nonce_action = self::nonce_action($id);
        if (!$nonce_action || !wp_verify_nonce($input['_wpnonce'] ?? '', $nonce_action)) { wp_die('Security check failed.', '', array('response' => 403)); }
        $result = IDLM_Lead_Lifecycle_Service::resolve_inbound_review($id, $input['decision'] ?? '', absint($input['lead_id'] ?? 0), $input['note'] ?? '');
        if (is_wp_error($result)) { wp_die(esc_html($result->get_error_message()), '', array('response' => 409)); }
        wp_safe_redirect(admin_url('admin.php?page=idlm_identity_reviews&resolved=1'));
        exit;
    }

    /** Erase the entire held submission when any associated identity is erased. Caller owns deletion transaction. */
    public static function erase_leads($ids) {
        global $wpdb;
        $ids = array_values(array_unique(array_filter(array_map('absint', $ids))));
        if (!$ids) { return true; }
        $sql_ids = implode(',', $ids);
        // phpcs:disable PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- REL007-SQL-0307: erase_leads(): fixed prefixed review/lead tables; status/platform/external-ID/date/cursor values bound and erasure lists absint-only; manager/replay/erasure decisions use live state.
        $leads = $wpdb->get_results("SELECT email, phone FROM {$wpdb->prefix}idlm_leads WHERE id IN ({$sql_ids}) FOR UPDATE");
        // phpcs:enable PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        if ($wpdb->last_error) { return false; }
        $emails = array(); $phones = array();
        foreach ($leads as $lead) {
            if ($lead->email) { $emails[] = strtolower(trim($lead->email)); }
            if ($lead->phone) { $phones[] = self::phone($lead->phone); }
        }
        $cursor = 0;
        do {
            // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- REL007-SQL-0308: erase_leads(): fixed prefixed review/lead tables; status/platform/external-ID/date/cursor values bound and erasure lists absint-only; manager/replay/erasure decisions use live state.
            $rows = $wpdb->get_results($wpdb->prepare("SELECT id, candidate_lead_ids, resolved_lead_id, email, phone FROM {$wpdb->prefix}idlm_inbound_submission_reviews WHERE id > %d ORDER BY id ASC LIMIT 100 FOR UPDATE", $cursor));
            // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
            if ($wpdb->last_error || !is_array($rows)) { return false; }
            foreach ($rows as $row) {
                $cursor = absint($row->id);
                $candidates = json_decode($row->candidate_lead_ids, true);
                // Malformed legacy references cannot be certified free of erased identity.
                $erase = !is_array($candidates) || array_intersect($ids, self::candidate_ids($row)) || in_array(absint($row->resolved_lead_id), $ids, true)
                    || ($row->email && in_array(strtolower(trim($row->email)), $emails, true)) || ($row->phone && in_array(self::phone($row->phone), $phones, true));
                // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- REL007-SQL-0309: erase_leads(): fixed prefixed review/lead tables; status/platform/external-ID/date/cursor values bound and erasure lists absint-only; manager/replay/erasure decisions use live state.
                if ($erase && 1 !== $wpdb->delete($wpdb->prefix . 'idlm_inbound_submission_reviews', array('id' => $cursor))) { return false; }
                // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
            }
        } while (count($rows) === 100);
        return true;
    }

    private static function phone($phone) {
        $phone = preg_replace('/[^0-9]/', '', $phone);
        if (strlen($phone) === 11 && $phone[0] === '0') { $phone = substr($phone, 1); }
        return strlen($phone) === 10 ? '91' . $phone : $phone;
    }

    /** One checked, overlap-safe 100-row retention batch; retry without advancing a lossy cursor. */
    public static function prune() {
        global $wpdb;
        $result = IDLM_Lead_Transaction::run(static function() {
            global $wpdb;
            $policy = (array) get_option('idlm_retention_policy', array());
            $days = max(1, absint($policy['touchpoints_days'] ?? 90));
            // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- REL007-SQL-0310: prune(): fixed prefixed review/lead tables; status/platform/external-ID/date/cursor values bound and erasure lists absint-only; manager/replay/erasure decisions use live state.
            return IDLM_Lead_Transaction::required($wpdb->query($wpdb->prepare(
            // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
                "DELETE FROM {$wpdb->prefix}idlm_inbound_submission_reviews WHERE received_at < DATE_SUB(%s, INTERVAL %d DAY) ORDER BY id ASC LIMIT 100", current_time('mysql'), $days
            )));
        });
        if (is_wp_error($result) || 100 === $result) {
            if (!wp_next_scheduled(self::PRUNE_HOOK)) { wp_schedule_single_event(time() + MINUTE_IN_SECONDS, self::PRUNE_HOOK); }
        }
        return $result;
    }
}
