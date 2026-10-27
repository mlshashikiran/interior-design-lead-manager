<?php
/**
 * Lead lifecycle persistence service.
 *
 * @package InteriorDesignLeadManager
 * @author Shashi
 */

if (!defined('ABSPATH')) {
    exit;
}

final class IDLM_Lead_Lifecycle_Service extends IDLM_DB {
    public static function upsert_inbound_lead($data, $platform, $payload = array(), $options = array()) {
        self::$last_validation_error = '';
        $locks = self::acquire_inbound_identity_locks($data, $platform);
        if (false === $locks) { self::$last_validation_error = 'Lead matching is busy. Please retry the submission.'; return false; }
        try {
            return self::transaction_result(IDLM_Lead_Transaction::run(static function() use ($data, $platform, $payload, $options) { return self::upsert_inbound_lead_work($data, $platform, $payload, $options); }));
        } finally { self::release_inbound_identity_locks($locks); }
    }

    private static function transaction_result($result) {
        if (is_wp_error($result)) { if (!self::$last_validation_error) { self::$last_validation_error = $result->get_error_message(); } return false; }
        return $result;
    }

    private static function upsert_inbound_lead_work($data, $platform, $payload = array(), $options = array()) {
        global $wpdb;
        $table_leads = $wpdb->prefix . 'idlm_leads';
        $platform = sanitize_key($platform);
        $replay = IDLM_Identity_Review::replay($data, $platform);
        if (null !== $replay) { return $replay; }
        $match = self::find_inbound_canonical_id($data, $platform);
        if (isset($options['connector_queued_at'])) {
            IDLM_Lead_Transaction::required(IDLM_Lead_Connector::intake_allowed($data, $options['connector_queued_at']));
        }
        $canonical_id = absint($match['lead_id']);
        if ($canonical_id) {
            // phpcs:disable PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- REL007-SQL-0394: upsert_inbound_lead_work(): canonical prefixed tables; identity predicates are fixed with bound contact values; IDs/cursors are bound; replay/ownership/required-write reads remain in the checked aggregate transaction.
            $canonical = IDLM_Lead_Transaction::read($wpdb->get_row($wpdb->prepare("SELECT * FROM {$table_leads} WHERE id = %d FOR UPDATE", $canonical_id)));
            // phpcs:enable PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            IDLM_Lead_Transaction::required((bool) $canonical);
            $updates = self::build_inbound_canonical_updates($canonical, $data, $platform);
            if (!empty($updates)) {
                $updates['updated_at'] = current_time('mysql');
                // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- REL007-SQL-0395: upsert_inbound_lead_work(): canonical prefixed tables; identity predicates are fixed with bound contact values; IDs/cursors are bound; replay/ownership/required-write reads remain in the checked aggregate transaction.
                IDLM_Lead_Transaction::required($wpdb->update($table_leads, $updates, array('id' => $canonical_id)));
                // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
                IDLM_Lead_Transaction::required(self::log_activity(
                    $canonical_id,
                    'attribution',
                    sprintf('Matched a %s submission to this canonical lead. Updated: %s.', $platform, implode(', ', array_keys($updates)))
                ));
                IDLM_Lead_Transaction::defer('idlm_lead_attribution_merged', $canonical_id, $updates);
                IDLM_Lead_Transaction::invalidate('inbound_lead_update', $canonical_id);
            } else {
                IDLM_Lead_Transaction::required(self::log_activity($canonical_id, 'attribution', sprintf('Matched a %s submission to this canonical lead.', $platform)));
            }
            if (isset($options['connector_attribution'])) {
                IDLM_Lead_Transaction::required(IDLM_Lead_Connector::remember_attribution($canonical_id, $options['connector_attribution'], false));
            }
            if (isset($options['connector_message'])) {
                $message_result=IDLM_WhatsApp_Inbox::record($options['connector_message'], true);
                IDLM_Lead_Transaction::required(!is_wp_error($message_result) && in_array($message_result['status'] ?? '', array('processed','replayed'), true));
            }
            IDLM_Lead_Transaction::required(1 === self::record_lead_touchpoint($canonical_id, $platform, $data, $payload));
            return array('lead_id' => $canonical_id, 'created' => false, 'matched' => true);
        }

        $data['original_source'] = !empty($data['source']) ? sanitize_text_field($data['source']) : '';
        $data['latest_source'] = $data['original_source'];
        if (!empty($match['ambiguous_candidate_ids'])) {
            $review_id = self::record_inbound_review($data, $platform, $payload, $match['ambiguous_candidate_ids']);
            if (!$review_id) {
                self::$last_validation_error = 'Unable to retain the conflicting submission for review.';
                return false;
            }
            foreach ($match['ambiguous_candidate_ids'] as $candidate_id) {
                IDLM_Lead_Transaction::required(self::log_activity(
                    $candidate_id,
                    'duplicate_review',
                    sprintf('Inbound %s submission was retained for identity review as submission #%d; it matched more than one existing lead.', $platform, $review_id)
                ));
            }
            return array(
                'lead_id' => 0,
                'created' => false,
                'matched' => false,
                'requires_duplicate_review' => true,
                'review_id' => $review_id,
            );
        }

        $lead_id = self::insert_lead($data);
        if (!$lead_id) {
            return false;
        }
        if (isset($options['connector_attribution'])) {
            IDLM_Lead_Transaction::required(IDLM_Lead_Connector::remember_attribution($lead_id, $options['connector_attribution'], true));
        }
        if (isset($options['connector_message'])) {
            $message_result=IDLM_WhatsApp_Inbox::record($options['connector_message'], true);
            IDLM_Lead_Transaction::required(!is_wp_error($message_result) && in_array($message_result['status'] ?? '', array('processed','replayed'), true));
        }
        IDLM_Lead_Transaction::required(1 === self::record_lead_touchpoint($lead_id, $platform, $data, $payload));
        if (!empty($options['route'])) {
            IDLM_Lead_Transaction::required(IDLM_Integrations::route_lead_assignment($lead_id, $data['source'] ?? ''));
        }
        if ('manual' === $platform) {
            $creator = wp_get_current_user();
            IDLM_Lead_Transaction::required(self::log_activity($lead_id, 'assignment', sprintf('Lead manually created and assigned to %s.', $creator->display_name), get_current_user_id()));
        }
        if (!empty($options['capi']) && (in_array($data['stage'] ?? '', array('Qualified','Quote Shared','Won'), true) || 'Qualified' === ($data['lead_quality'] ?? ''))) {
            $stage = in_array($data['stage'] ?? '', array('Qualified','Quote Shared','Won'), true) ? $data['stage'] : 'Qualified';
            IDLM_Lead_Transaction::defer('idlm_lead_stage_converted', $lead_id, $stage, (object) array('stage'=>''));
        }
        return array(
            'lead_id' => $lead_id,
            'created' => true,
            'matched' => false,
            'requires_duplicate_review' => false,
        );
    }

    /** Acquire deterministic locks for every supplied identity, preventing concurrent repeat inserts. */
    private static function acquire_inbound_identity_locks($data, $platform) {
        global $wpdb;
        $lock_names = array();
        foreach (self::get_inbound_identity_keys($data, $platform) as $identity) {
            $lock_name = 'idlm_' . substr(hash('sha256', $identity), 0, 40);
            // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- REL007-SQL-0396: acquire_inbound_identity_locks(): canonical prefixed tables; identity predicates are fixed with bound contact values; IDs/cursors are bound; replay/ownership/required-write reads remain in the checked aggregate transaction.
            if (1 !== (int) $wpdb->get_var($wpdb->prepare('SELECT GET_LOCK(%s, 5)', $lock_name))) {
            // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
                self::release_inbound_identity_locks($lock_names);
                return false;
            }
            $lock_names[] = $lock_name;
        }
        return $lock_names;
    }

    private static function release_inbound_identity_locks($lock_names) {
        global $wpdb;
        foreach ((array) $lock_names as $lock_name) {
            // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- REL007-SQL-0397: release_inbound_identity_locks(): canonical prefixed tables; identity predicates are fixed with bound contact values; IDs/cursors are bound; replay/ownership/required-write reads remain in the checked aggregate transaction.
            $wpdb->get_var($wpdb->prepare('SELECT RELEASE_LOCK(%s)', $lock_name));
            // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
        }
    }

    private static function get_inbound_identity_keys($data, $platform) {
        $identities = array();
        $phone = preg_replace('/[^0-9]/', '', (string) ($data['phone'] ?? ''));
        if (strlen($phone) >= 10) {
            $identities[] = 'phone:' . self::normalize_identity_phone($phone);
        }
        $email = strtolower(trim((string) ($data['email'] ?? '')));
        if ($email) {
            $identities[] = 'email:' . $email;
        }
        $external_id = self::get_inbound_external_id($data);
        if ($external_id) {
            $identities[] = 'external:' . sanitize_key($platform) . ':' . $external_id;
        }
        if (!$identities) {
            $identities[] = 'anonymous:' . wp_generate_uuid4();
        }
        sort($identities, SORT_STRING);
        return array_values(array_unique($identities));
    }

    private static function get_inbound_external_id($data) {
        return !empty($data['fb_lead_id']) ? sanitize_text_field($data['fb_lead_id']) : '';
    }

    /**
     * Return one canonical inbound match only when its identity is unambiguous.
     *
     * @return array{lead_id:int,ambiguous_candidate_ids:array<int,int>}
     */
    private static function find_inbound_canonical_id($data, $platform) {
        global $wpdb;
        $table_leads = $wpdb->prefix . 'idlm_leads';
        $external_id = self::get_inbound_external_id($data);
        if ($external_id) {
            // phpcs:disable PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- REL007-SQL-0398: find_inbound_canonical_id(): canonical prefixed tables; identity predicates are fixed with bound contact values; IDs/cursors are bound; replay/ownership/required-write reads remain in the checked aggregate transaction.
            $touchpoint_leads = IDLM_Lead_Transaction::read($wpdb->get_col($wpdb->prepare(
                "SELECT DISTINCT t.lead_id FROM {$wpdb->prefix}idlm_lead_touchpoints t INNER JOIN {$table_leads} l ON l.id = t.lead_id WHERE t.platform = %s AND t.external_id = %s AND l.is_duplicate = 0 LIMIT 2",
            // phpcs:enable PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                sanitize_key($platform),
                $external_id
            )));
            $touchpoint_leads = array_values(array_unique(array_filter(array_map('absint', $touchpoint_leads))));
            if (1 === count($touchpoint_leads)) {
                return array('lead_id' => $touchpoint_leads[0], 'ambiguous_candidate_ids' => array());
            }
            if (count($touchpoint_leads) > 1) {
                return array('lead_id' => 0, 'ambiguous_candidate_ids' => $touchpoint_leads);
            }

            // Preserve compatibility with legacy inbound rows that predate
            // touchpoints, but never select an arbitrary duplicate external ID.
            // phpcs:disable PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- REL007-SQL-0399: find_inbound_canonical_id(): canonical prefixed tables; identity predicates are fixed with bound contact values; IDs/cursors are bound; replay/ownership/required-write reads remain in the checked aggregate transaction.
            $external_matches = IDLM_Lead_Transaction::read($wpdb->get_col($wpdb->prepare(
                "SELECT id FROM {$table_leads} WHERE is_duplicate = 0 AND fb_lead_id = %s LIMIT 2",
            // phpcs:enable PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                $external_id
            )));
            $external_matches = array_values(array_unique(array_filter(array_map('absint', $external_matches))));
            if (1 === count($external_matches)) {
                return array('lead_id' => $external_matches[0], 'ambiguous_candidate_ids' => array());
            }
            if (count($external_matches) > 1) {
                return array('lead_id' => 0, 'ambiguous_candidate_ids' => $external_matches);
            }
        }

        $email = strtolower(trim((string) ($data['email'] ?? '')));
        $phone = preg_replace('/[^0-9]/', '', (string) ($data['phone'] ?? ''));
        $conditions = array();
        $params = array();
        if ($email) {
            $conditions[] = 'LOWER(email) = %s';
            $params[] = $email;
        }
        if (!empty($data['phone'])) {
            $conditions[] = 'phone = %s';
            $params[] = sanitize_text_field($data['phone']);
            if (strlen($phone) >= 10) {
                $conditions[] = 'phone LIKE %s';
                $params[] = '%' . $wpdb->esc_like(substr($phone, -10));
            }
        }
        if (empty($conditions)) {
            return array('lead_id' => 0, 'ambiguous_candidate_ids' => array());
        }
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        $sql = "SELECT id, email, phone FROM {$table_leads} WHERE is_duplicate = 0 AND (" . implode(' OR ', $conditions) . ') ORDER BY id ASC';
        // phpcs:disable PluginCheck.Security.DirectDB.UnescapedDBParameter -- REL007-SQL-0400: find_inbound_canonical_id(): canonical prefixed tables; identity predicates are fixed with bound contact values; IDs/cursors are bound; replay/ownership/required-write reads remain in the checked aggregate transaction.
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $candidates = IDLM_Lead_Transaction::read($wpdb->get_results($wpdb->prepare($sql, $params)));
        // phpcs:enable PluginCheck.Security.DirectDB.UnescapedDBParameter
        $matching_ids = array();
        foreach ($candidates as $candidate) {
            $candidate_email = strtolower(trim((string) $candidate->email));
            $normalized_phone = self::normalize_identity_phone($phone);
            $candidate_normalized_phone = self::normalize_identity_phone($candidate->phone);
            if ($email && $email === strtolower(trim((string) $candidate->email))) {
                $matching_ids[] = absint($candidate->id);
                continue;
            }
            if ($normalized_phone && $candidate_normalized_phone && $normalized_phone === $candidate_normalized_phone) {
                $matching_ids[] = absint($candidate->id);
            }
        }
        $matching_ids = array_values(array_unique(array_filter($matching_ids)));
        return array(
            'lead_id' => 1 === count($matching_ids) ? $matching_ids[0] : 0,
            'ambiguous_candidate_ids' => count($matching_ids) > 1 ? $matching_ids : array(),
        );
    }

    /** Canonical India-default phone identity without dropping explicit country codes. */
    public static function normalize_identity_phone($phone) {
        $digits = preg_replace('/[^0-9]/', '', (string) $phone);
        if (11 === strlen($digits) && '0' === $digits[0]) { $digits = substr($digits, 1); }
        return 10 === strlen($digits) ? '91' . $digits : $digits;
    }

    private static function build_inbound_canonical_updates($canonical, $data, $platform) {
        $updates = array();
        $fill_when_empty = array(
            'first_name', 'last_name', 'email', 'phone', 'project_name', 'budget',
            'project_timeline', 'possession_date', 'property_location', 'property_type',
            'num_rooms', 'preferred_studio', 'referrer_details', 'fb_lead_id', 'fbclid',
            'gclid', 'gbraid', 'wbraid', 'li_fat_id', 'utm_source', 'utm_medium',
            'utm_campaign', 'utm_adgroup', 'utm_term', 'utm_content', 'landing_page'
        );
        foreach ($fill_when_empty as $field) {
            if (empty($canonical->$field) && !empty($data[$field])) {
                $updates[$field] = sanitize_text_field($data[$field]);
            }
        }

        $incoming_source = !empty($data['source']) ? sanitize_text_field($data['source']) : '';
        if ($incoming_source) {
            $updates['latest_source'] = $incoming_source;
        }
        $generic_sources = array('', 'website', 'unknown', 'direct', 'organic');
        $current_primary = strtolower(trim((string) ($canonical->source ?? '')));
        $incoming_primary = strtolower(trim($incoming_source));
        if ('meta' === $platform && 'meta ads' === $incoming_primary && in_array($current_primary, $generic_sources, true)) {
            $updates['source'] = $incoming_source;
            $updates['original_source'] = $incoming_source;
        } elseif (empty($canonical->original_source ?? '') && $incoming_source) {
            $updates['original_source'] = $incoming_source;
        }
        return $updates;
    }

    /** Keep only valid provider submission times; never create zero-date records. */
    private static function inbound_submission_time($value) {
        if (!is_string($value) || $value === '') { return null; }
        $value = trim($value);
        try {
            if (preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/D', $value)) {
                $date = DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', $value);
                return $date && $date->format('Y-m-d H:i:s') === $value && (int)$date->format('Y') >= 1000 ? $value : null;
            }
            if (!preg_match('/^\d{4}-\d{2}-\d{2}[T ]\d{2}:\d{2}:\d{2}(?:\.\d{1,6})?(?:Z|[+-]\d{2}:?\d{2})$/D', $value)) { return null; }
            $date = new DateTimeImmutable($value);
            $errors = DateTimeImmutable::getLastErrors();
            if ($errors && ($errors['warning_count'] || $errors['error_count'])) { return null; }
            $date = $date->setTimezone(function_exists('wp_timezone') ? wp_timezone() : new DateTimeZone('UTC'));
            return (int)$date->format('Y') >= 1000 ? $date->format('Y-m-d H:i:s') : null;
        } catch (Exception $error) { return null; }
    }

    private static function record_lead_touchpoint($lead_id, $platform, $data, $payload = array(), $external_id = '') {
        global $wpdb;
        $table = $wpdb->prefix . 'idlm_lead_touchpoints';
        if (!$external_id) {
            $external_id = !empty($data['fb_lead_id']) ? (string) $data['fb_lead_id'] : null;
        }
        $row = array(
            'lead_id' => absint($lead_id),
            'platform' => sanitize_key($platform),
            'source' => sanitize_text_field($data['source'] ?? ''),
            'external_id' => $external_id ? sanitize_text_field($external_id) : null,
            'payload' => wp_json_encode(IDLM_Data_Retention::redact($payload ?: $data)),
            'submitted_at' => self::inbound_submission_time($data['conversion_time'] ?? null),
            'received_at' => current_time('mysql'),
        );
        $attribution_fields = array('utm_source', 'utm_medium', 'utm_campaign', 'utm_adgroup', 'utm_term', 'utm_content', 'gclid', 'gbraid', 'wbraid', 'fbclid', 'li_fat_id', 'landing_page');
        foreach ($attribution_fields as $field) {
            $row[$field] = sanitize_text_field($data[$field] ?? '');
        }
        // External replay is benign only when the durable tuple belongs to this lead.
        if ($row['external_id']) {
            // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- REL007-SQL-0401: record_lead_touchpoint(): canonical prefixed tables; identity predicates are fixed with bound contact values; IDs/cursors are bound; replay/ownership/required-write reads remain in the checked aggregate transaction.
            $existing = $wpdb->get_row($wpdb->prepare("SELECT lead_id, utm_source, utm_medium, utm_campaign, utm_adgroup, utm_term, utm_content, gclid, gbraid, wbraid, fbclid, li_fat_id, landing_page FROM {$table} WHERE platform = %s AND external_id = %s FOR UPDATE", $row['platform'], $row['external_id']));
            // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            if ($wpdb->last_error) { return false; }
            if (null !== $existing) {
                if (absint($existing->lead_id) !== absint($lead_id)) { return false; }
                // A second trusted intake path may know attribution absent from the
                // first. Preserve existing values and backfill only missing fields.
                $updates = array();
                foreach ($attribution_fields as $field) {
                    if (($existing->$field === null || $existing->$field === '') && $row[$field] !== '') { $updates[$field] = $row[$field]; }
                }
                // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- REL007-SQL-0402: record_lead_touchpoint(): canonical prefixed tables; identity predicates are fixed with bound contact values; IDs/cursors are bound; replay/ownership/required-write reads remain in the checked aggregate transaction.
                if ($updates && 1 !== $wpdb->update($table, $updates, array('platform'=>$row['platform'], 'external_id'=>$row['external_id'], 'lead_id'=>absint($lead_id)))) { return false; }
                // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
                return 1;
            }
        }
        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery -- REL007-SQL-0403: record_lead_touchpoint(): canonical prefixed tables; identity predicates are fixed with bound contact values; IDs/cursors are bound; replay/ownership/required-write reads remain in the checked aggregate transaction.
        $inserted = $wpdb->insert($table, $row);
        // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery
        if (false === $inserted && $row['external_id']) {
            // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- REL007-SQL-0404: record_lead_touchpoint(): canonical prefixed tables; identity predicates are fixed with bound contact values; IDs/cursors are bound; replay/ownership/required-write reads remain in the checked aggregate transaction.
            $existing = $wpdb->get_var($wpdb->prepare("SELECT lead_id FROM {$table} WHERE platform = %s AND external_id = %s FOR UPDATE", $row['platform'], $row['external_id']));
            // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            if (null !== $existing && absint($existing) === absint($lead_id)) { return 1; }
        }
        return $inserted;
    }

    /** Retain conflicting submissions without creating an actionable lead record. */
    private static function record_inbound_review($data, $platform, $payload, $candidate_ids) {
        global $wpdb;
        $table = $wpdb->prefix . 'idlm_inbound_submission_reviews';
        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery -- REL007-SQL-0405: record_inbound_review(): canonical prefixed tables; identity predicates are fixed with bound contact values; IDs/cursors are bound; replay/ownership/required-write reads remain in the checked aggregate transaction.
        $inserted = $wpdb->insert($table, array(
        // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery
            'platform' => sanitize_key($platform),
            'source' => sanitize_text_field($data['source'] ?? ''),
            'email' => sanitize_email($data['email'] ?? ''),
            'phone' => sanitize_text_field($data['phone'] ?? ''),
            'candidate_lead_ids' => wp_json_encode(array_values(array_unique(array_map('absint', $candidate_ids)))),
            'submission_data' => wp_json_encode(IDLM_Identity_Review::submission($data)),
            'external_id' => self::get_inbound_external_id($data) ?: null,
            'payload' => wp_json_encode(IDLM_Data_Retention::redact($payload ?: $data)),
            'submitted_at' => self::inbound_submission_time($data['conversion_time'] ?? null),
            'received_at' => current_time('mysql'),
            'status' => 'pending',
        ));
        return $inserted ? absint($wpdb->insert_id) : 0;
    }

    /** Manager decisions share intake identity locks and the checked aggregate owner. */
    public static function resolve_inbound_review($review_id, $decision, $lead_id = 0, $note = '') {
        global $wpdb;
        if (!IDLM_Lead_Access_Policy::can_assign()) { return new WP_Error('idlm_review_forbidden', 'Manager access is required.'); }
        if (!absint($review_id) || !in_array($decision, array('matched', 'separate', 'rejected'), true) || !is_string($note) || !trim(sanitize_textarea_field($note)) || strlen($note) > 1000) {
            return new WP_Error('idlm_review_invalid', 'Select a decision and enter a reason of at most 1000 bytes.');
        }
        $table = $wpdb->prefix . 'idlm_inbound_submission_reviews';
        // phpcs:disable PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- REL007-SQL-0406: resolve_inbound_review(): canonical prefixed tables; identity predicates are fixed with bound contact values; IDs/cursors are bound; replay/ownership/required-write reads remain in the checked aggregate transaction.
        $hint = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE id = %d", $review_id));
        // phpcs:enable PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        if ($wpdb->last_error || !$hint) { return new WP_Error('idlm_review_missing', 'Review is unavailable.'); }
        $data = IDLM_Identity_Review::data($hint);
        $locks = self::acquire_inbound_identity_locks($data, $hint->platform);
        if (false === $locks) { return new WP_Error('idlm_review_busy', 'Lead matching is busy. Please retry.'); }
        try {
            return IDLM_Lead_Transaction::run(static function() use ($review_id, $decision, $lead_id, $note, $table, $data, $hint) {
                global $wpdb;
                // phpcs:disable PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- REL007-SQL-0407: resolve_inbound_review(): canonical prefixed tables; identity predicates are fixed with bound contact values; IDs/cursors are bound; replay/ownership/required-write reads remain in the checked aggregate transaction.
                $review = IDLM_Lead_Transaction::read($wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE id = %d FOR UPDATE", $review_id)));
                // phpcs:enable PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                if (!$review || 'pending' !== $review->status) { return new WP_Error('idlm_review_stale', 'Review is unavailable or already resolved.'); }
                if ($review->platform !== $hint->platform || IDLM_Identity_Review::data($review) !== $data) { return new WP_Error('idlm_review_changed', 'Review changed during recovery. Reload and retry.'); }
                if (!IDLM_Lead_Access_Policy::refresh_review_actor()) { return new WP_Error('idlm_review_forbidden', 'Manager access is required.'); }
                $data = IDLM_Identity_Review::data($review);
                $target = 0;
                if ('matched' === $decision) {
                    $ids = IDLM_Identity_Review::candidate_ids($review);
                    if (!in_array(absint($lead_id), $ids, true)) { return new WP_Error('idlm_review_target', 'Select a live candidate from this review.'); }
                    // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- REL007-SQL-0408: resolve_inbound_review(): canonical prefixed tables; identity predicates are fixed with bound contact values; IDs/cursors are bound; replay/ownership/required-write reads remain in the checked aggregate transaction.
                    $lead = IDLM_Lead_Transaction::read($wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}idlm_leads WHERE id = %d FOR UPDATE", $lead_id)));
                    // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
                    if (!$lead || !empty($lead->is_duplicate)) { return new WP_Error('idlm_review_target', 'Select a live canonical candidate.'); }
                    $target = absint($lead_id);
                    $updates = self::build_inbound_canonical_updates($lead, $data, $review->platform);
                    if ($updates) {
                        // Attribution only: never overwrite lifecycle, ownership or existing contact values.
                        $updates['updated_at'] = current_time('mysql');
                        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- REL007-SQL-0409: resolve_inbound_review(): canonical prefixed tables; identity predicates are fixed with bound contact values; IDs/cursors are bound; replay/ownership/required-write reads remain in the checked aggregate transaction.
                        IDLM_Lead_Transaction::required($wpdb->update($wpdb->prefix . 'idlm_leads', $updates, array('id' => $target)));
                        // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
                        IDLM_Lead_Transaction::defer('idlm_lead_attribution_merged', $target, $updates);
                    }
                } elseif ('separate' === $decision) {
                    // An explicit reviewed identity decision is the only duplicate bypass here.
                    $target = self::insert_lead($data, true);
                    IDLM_Lead_Transaction::required($target > 0);
                }
                if ($target) {
                    IDLM_Lead_Transaction::required(1 === self::record_lead_touchpoint($target, $review->platform, $data, json_decode($review->payload, true) ?: array(), $review->external_id ?? ''));
                    IDLM_Lead_Transaction::required(self::log_activity($target, 'identity_review', sprintf('Identity review #%d: %s. %s', $review_id, $decision, sanitize_textarea_field($note)), get_current_user_id()));
                    IDLM_Lead_Transaction::invalidate('identity_review', $target);
                }
                // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- REL007-SQL-0410: resolve_inbound_review(): canonical prefixed tables; identity predicates are fixed with bound contact values; IDs/cursors are bound; replay/ownership/required-write reads remain in the checked aggregate transaction.
                IDLM_Lead_Transaction::required(1 === $wpdb->update($table, array(
                // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
                    'status' => $decision, 'resolved_lead_id' => $target ?: null,
                    'resolved_by' => get_current_user_id(), 'resolved_at' => current_time('mysql'),
                    'resolution_note' => sanitize_textarea_field($note),
                ), array('id' => absint($review_id), 'status' => 'pending')));
                return array('review_id' => absint($review_id), 'status' => $decision, 'lead_id' => $target);
            });
        } finally { self::release_inbound_identity_locks($locks); }
    }

    protected static function backfill_duplicate_touchpoints() {
        global $wpdb;
        $cursor_option = 'idlm_duplicate_touchpoint_backfill_cursor_v2';
        $cursor = IDLM_Schema_Migration::version($cursor_option);
        if (null === $cursor || ($cursor !== '' && !ctype_digit($cursor))) { return false; }
        $table_leads = $wpdb->prefix . 'idlm_leads';
        $table_touches = $wpdb->prefix . 'idlm_lead_touchpoints';
        // phpcs:disable PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- REL007-SQL-0411: backfill_duplicate_touchpoints(): canonical prefixed tables; identity predicates are fixed with bound contact values; IDs/cursors are bound; replay/ownership/required-write reads remain in the checked aggregate transaction.
        $duplicates = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$table_leads} WHERE is_duplicate = 1 AND duplicate_of_id > 0 AND id > %d ORDER BY id ASC LIMIT 100", (int) $cursor), ARRAY_A);
        // phpcs:enable PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        if ($wpdb->last_error || !is_array($duplicates)) { return false; }
        foreach ($duplicates as $duplicate) {
            $external_id = 'legacy-duplicate-' . absint($duplicate['id']);
            // phpcs:disable PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- REL007-SQL-0412: backfill_duplicate_touchpoints(): canonical prefixed tables; identity predicates are fixed with bound contact values; IDs/cursors are bound; replay/ownership/required-write reads remain in the checked aggregate transaction.
            $existing = $wpdb->get_row($wpdb->prepare("SELECT lead_id FROM {$table_touches} WHERE platform = 'legacy_duplicate' AND external_id = %s", $external_id));
            // phpcs:enable PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            if ($wpdb->last_error) { return false; }
            if ($existing) {
                if ((int) $existing->lead_id !== (int) $duplicate['duplicate_of_id']) { return false; }
                continue;
            }
            if (1 !== self::record_lead_touchpoint(
                absint($duplicate['duplicate_of_id']), 'legacy_duplicate', $duplicate,
                array('legacy_duplicate_lead_id' => absint($duplicate['id'])), $external_id
            )) { return false; }
        }
        if ($duplicates) {
            $last = end($duplicates);
            $result = IDLM_Schema_Migration::publish($cursor_option, (string) $last['id']);
            if (is_wp_error($result)) { return $result; }
        }
        return count($duplicates) < 100 ? true : IDLM_Schema_Migration::error('touchpoint_batch', 'CRM attribution migration is progressing in batches of 100. Retry to continue.');
    }

    /**
     * Add lead and run duplicate detection
     */
    public static function insert_lead($data, $skip_duplicate_detection = false) {
        self::$last_validation_error = '';
        // Inbound upsert already owns identity locks through its outer commit.
        $locks = IDLM_Lead_Transaction::active() ? array() : self::acquire_inbound_identity_locks($data, '');
        if (false === $locks) { self::$last_validation_error = 'Lead matching is busy. Please retry the submission.'; return false; }
        try {
            return self::transaction_result(IDLM_Lead_Transaction::run(static function() use ($data, $skip_duplicate_detection) { return self::insert_lead_work($data, $skip_duplicate_detection); }));
        } finally { self::release_inbound_identity_locks($locks); }
    }

    private static function insert_lead_work($data, $skip_duplicate_detection = false) {
        global $wpdb;
        $table_leads = $wpdb->prefix . 'idlm_leads';

        self::$last_validation_error = '';
        $data['stage'] = !empty($data['stage']) ? $data['stage'] : 'New';
        $data['call_status'] = !empty($data['call_status']) ? $data['call_status'] : 'Not Called';

        $new_lead_defaults = (object) array(
            'stage' => 'New', 'lead_quality' => '', 'deal_value' => 0, 'discount_percentage' => 0,
            'quote_shared' => 0, 'quote_value' => 0,
        );
        $data = self::normalize_workflow_transition($new_lead_defaults, $data);
        if (false === $data) {
            return false;
        }

        if ('Unqualified' === ($data['lead_quality'] ?? '')) {
            self::$last_validation_error = 'A new lead cannot start as Unqualified. Use the six-day workflow or a manager junk override after creation.';
            return false;
        }
        if ('Qualified' === ($data['lead_quality'] ?? '') || 'Qualified' === ($data['stage'] ?? '')) {
            $required = array('project_name', 'property_location', 'budget', 'possession_date', 'property_type', 'project_timeline');
            foreach ($required as $field) {
                if (empty($data[$field])) {
                    self::$last_validation_error = 'A new lead cannot start as Qualified until all required project details are recorded.';
                    return false;
                }
            }
            if (self::budget_to_rupees($data['budget']) < 400000) {
                self::$last_validation_error = 'A new lead cannot start as Qualified below the ₹4,00,000 minimum budget.';
                return false;
            }
        }

        $data['created_at'] = current_time('mysql');
        $data['updated_at'] = current_time('mysql');
        // The lead column is the Won close timestamp, not the inbound form's
        // submission timestamp (which is kept on its touchpoint). In particular,
        // a blank webhook mapping must not become a zero MySQL datetime.
        if ('Won' !== $data['stage']) {
            unset($data['conversion_time']);
        } elseif (empty($data['conversion_time'])) {
            $data['conversion_time'] = current_time('mysql');
        }
        if (!empty($data['assigned_rep_id']) && !IDLM_Lead_Access_Policy::is_assignable_rep($data['assigned_rep_id'], 'any', true)) {
            self::$last_validation_error = 'Please select an enabled CRM representative with current lead access.';
            return false;
        }
        if (!empty($data['assigned_rep_id'])) {
            $data['assigned_at'] = current_time('mysql');
        }

        if (empty($data['qualified_at']) && ('Qualified' === ($data['lead_quality'] ?? '') || 'Qualified' === ($data['stage'] ?? ''))) {
            $data['qualified_at'] = current_time('mysql');
        }

        if ('Qualified' === ($data['lead_quality'] ?? '') || 'Qualified' === ($data['stage'] ?? '')) {
            $data['qualified_by_user_id'] = isset($data['qualified_by_user_id']) ? absint($data['qualified_by_user_id']) : get_current_user_id();
            $assigned_user = !empty($data['assigned_rep_id']) ? get_userdata(absint($data['assigned_rep_id'])) : false;
            if ($assigned_user && in_array('idlm_pre_sales', (array) $assigned_user->roles, true)) {
                $data['handoff_status'] = 'awaiting_manager';
                $data['intake_owner_id'] = absint($data['assigned_rep_id']);
            } elseif ($assigned_user) {
                $data['handoff_status'] = 'manually_assigned';
                $data['handoff_at'] = current_time('mysql');
                $data['handoff_method'] = 'direct_sales_qualified';
                $data['handoff_exception_reason'] = '';
            } else {
                $data['handoff_status'] = 'awaiting_manager';
            }
        }

        if ('Qualified' === ($data['lead_quality'] ?? '') && 'New' === $data['stage']) {
            $data['stage'] = 'Qualified';
        }

        // Duplicate lead detection using email or phone number
        $is_duplicate = 0;
        $duplicate_of_id = 0;

        if (!$skip_duplicate_detection && (!empty($data['email']) || !empty($data['phone']))) {
            $conditions = array();
            $params = array();

            if (!empty($data['email'])) {
                $conditions[] = "email = %s";
                $params[] = sanitize_email($data['email']);
            }

            if (!empty($data['phone'])) {
                $conditions[] = "phone = %s";
                $params[] = sanitize_text_field($data['phone']);

                $incoming_digits = preg_replace('/[^0-9]/', '', $data['phone']);
                if (strlen($incoming_digits) >= 10) {
                    $last_10 = substr($incoming_digits, -10);
                    // Match any phone ending with these 10 digits
                    $conditions[] = "phone LIKE %s";
                    $params[] = '%' . $wpdb->esc_like($last_10);
                }
            }

            // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
            $duplicate_query = "SELECT id, email, phone FROM $table_leads WHERE is_duplicate = 0 AND (" . implode(" OR ", $conditions) . ")";
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
            $candidates = IDLM_Lead_Transaction::read($wpdb->get_results($wpdb->prepare($duplicate_query, $params)));

            if (!empty($candidates)) {
                $matching_ids = array();
                foreach ($candidates as $cand) {
                    // 1. If email matches exactly
                    if (!empty($data['email']) && !empty($cand->email) && strtolower(trim($data['email'])) === strtolower(trim($cand->email))) {
                        $matching_ids[] = absint($cand->id);
                        continue;
                    }

                    // 2. If phone matches with smart phone digits matching (ignoring format and international code)
                    if (!empty($data['phone']) && !empty($cand->phone)) {
                        $incoming_clean = preg_replace('/[^0-9]/', '', $data['phone']);
                        $cand_clean = preg_replace('/[^0-9]/', '', $cand->phone);

                        if ($incoming_clean === $cand_clean) {
                            $matching_ids[] = absint($cand->id);
                            continue;
                        }

                        if (strlen($incoming_clean) >= 10 && strlen($cand_clean) >= 10) {
                            if (substr($incoming_clean, -10) === substr($cand_clean, -10)) {
                                $matching_ids[] = absint($cand->id);
                            }
                        }
                    }
                }
                $matching_ids = array_values(array_unique(array_filter($matching_ids)));
                if (1 === count($matching_ids)) {
                    $is_duplicate = 1;
                    $duplicate_of_id = $matching_ids[0];
                }
            }
        }

        $data['is_duplicate'] = $is_duplicate;
        $data['duplicate_of_id'] = $duplicate_of_id;

        if ($is_duplicate) {
            $data['lead_quality'] = 'Unqualified';
            $data['stage'] = 'Lost';
            $data['lost_reason'] = 'Duplicate or Invalid Lead';
            $data['disqualification_reason'] = 'duplicate';
            $data['disqualification_method'] = 'duplicate_detection';
            $data['disqualified_at'] = current_time('mysql');
            $data['disqualified_by_user_id'] = get_current_user_id();
            $data['handoff_status'] = 'not_ready';
            $data['handoff_at'] = null;
            $data['handoff_method'] = '';
            $data['handoff_exception_reason'] = '';
        }

        $capacity = IDLM_Assignment_Capacity::validate_transition(null, $data);
        if (is_wp_error($capacity)) { self::$last_validation_error = $capacity->get_error_message(); return false; }

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $inserted = $wpdb->insert($table_leads, $data);
        if ($inserted) {
            $lead_id = $wpdb->insert_id;

            // Log activity
            $log_msg = "Lead created.";
            if ($is_duplicate) {
                $log_msg .= " Marked as duplicate of Lead ID: " . $duplicate_of_id;
            }
            IDLM_Lead_Transaction::required(self::log_activity($lead_id, 'creation', $log_msg));
            IDLM_Lead_Transaction::required(self::record_reporting_event(
                $lead_id,
                'stage_change',
                '',
                $data['stage'],
                isset($data['lost_reason']) ? $data['lost_reason'] : '',
                'creation',
                0,
                null,
                'Won' === $data['stage'] ? ($data['deal_value'] ?? 0) : ('Lost' === $data['stage'] ? ($data['quote_value'] ?? 0) : null)
            ));
            if (!empty($data['assigned_rep_id'])) {
                IDLM_Lead_Transaction::required(self::record_reporting_event($lead_id, 'assignment', '', $data['assigned_rep_id'], '', 'creation'));
            }

            if (!$is_duplicate && !empty($data['assigned_rep_id'])) {
                $owner = get_userdata(absint($data['assigned_rep_id']));
                if ($owner && in_array('idlm_pre_sales', (array) $owner->roles, true)) {
                    IDLM_Lead_Transaction::required(self::create_presales_followup_tasks($lead_id, $data['assigned_rep_id']));
                }
            }

            if ($is_duplicate && $duplicate_of_id > 0) {
                // Fetch original lead to compare and merge attribution details
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
                $original_lead = IDLM_Lead_Transaction::read($wpdb->get_row($wpdb->prepare("SELECT * FROM {$table_leads} WHERE id = %d FOR UPDATE", $duplicate_of_id)));
                if ($original_lead) {
                    $attribution_fields = array(
                        'fb_lead_id', 'fbclid', 'gclid', 'gbraid', 'wbraid', 'li_fat_id',
                        'utm_source', 'utm_medium', 'utm_campaign', 'utm_adgroup', 'utm_term', 'utm_content', 'landing_page'
                    );
                    $original_updates = array();
                    foreach ($attribution_fields as $field) {
                        if (empty($original_lead->$field) && !empty($data[$field])) {
                            $original_updates[$field] = sanitize_text_field($data[$field]);
                        }
                    }

                    // Source promotion logic:
                    // If original source is generic/organic/website/unknown/direct, and duplicate source is a paid channel
                    $generic_sources = array('', 'website', 'unknown', 'direct', 'organic');
                    $original_source_clean = strtolower(trim($original_lead->source));
                    $duplicate_source = isset($data['source']) ? trim($data['source']) : '';
                    $duplicate_source_clean = strtolower($duplicate_source);

                    if (in_array($original_source_clean, $generic_sources) && !empty($duplicate_source) && !in_array($duplicate_source_clean, $generic_sources)) {
                        $original_updates['source'] = sanitize_text_field($duplicate_source);
                    }

                    if (!empty($original_updates)) {
                        $original_updates['updated_at'] = current_time('mysql');
                        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
                        IDLM_Lead_Transaction::required($wpdb->update($table_leads, $original_updates, array('id' => $duplicate_of_id)));
                        IDLM_Lead_Transaction::required(self::log_activity($duplicate_of_id, 'update', "Merged attribution data from duplicate Lead ID: {$lead_id}. Fields: " . implode(', ', array_keys($original_updates))));
                        
                        // Trigger hook for CAPI re-fire
                        IDLM_Lead_Transaction::defer('idlm_lead_attribution_merged', $duplicate_of_id, $original_updates);
                    }
                }
            }

            // Trigger CAPI for initial lead event only if it's NOT a duplicate (implemented by Shashi)
            if (!$is_duplicate) {
                IDLM_Lead_Transaction::defer('idlm_lead_stage_converted', $lead_id, 'New', (object) array('stage' => ''));
            }

            IDLM_Lead_Transaction::invalidate('lead_insert', $lead_id);
            return $lead_id;
        }

        return false;
    }

    /**
     * Update an existing lead, tracking specific status changes
     */
    public static function update_lead($lead_id, $data) {
        self::$last_validation_error = '';
        return self::transaction_result(IDLM_Lead_Transaction::run(static function() use ($lead_id, $data) { return self::update_lead_work($lead_id, $data); }));
    }

    private static function update_lead_work($lead_id, $data) {
        global $wpdb;
        $table_leads = $wpdb->prefix . 'idlm_leads';

        // Fetch old data to detect changes
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
        $old_lead = IDLM_Lead_Transaction::read($wpdb->get_row($wpdb->prepare("SELECT * FROM $table_leads WHERE id = %d FOR UPDATE", $lead_id)));
        if (!$old_lead) {
            return false;
        }

        self::$last_validation_error = '';
        if (isset($data['assigned_rep_id']) && absint($data['assigned_rep_id']) !== absint($old_lead->assigned_rep_id)
            && !IDLM_Lead_Access_Policy::is_assignable_rep($data['assigned_rep_id'], 'any', true)) {
            self::$last_validation_error = 'Please select an enabled CRM representative with current lead access.';
            return false;
        }
        // Entering Qualified normalizes quality; downstream sales stages do not.
        // The handoff remark belongs to an explicit quality decision only.
        $explicit_quality_update = array_key_exists('lead_quality', $data);
        $data = self::normalize_workflow_transition($old_lead, $data);
        if (false === $data) {
            return false;
        }
        if (!self::validate_quality_transition($lead_id, $old_lead, $data, $explicit_quality_update)) {
            return false;
        }

        $qualification_handoff_remark = isset($data['qualification_handoff_remark'])
            ? sanitize_textarea_field($data['qualification_handoff_remark'])
            : '';
        unset($data['qualification_handoff_remark']);
        $lost_remark = isset($data['lost_remark']) ? sanitize_textarea_field($data['lost_remark']) : '';
        unset($data['lost_remark']);

        $data['updated_at'] = current_time('mysql');

        if (isset($data['assigned_rep_id']) && absint($data['assigned_rep_id']) !== absint($old_lead->assigned_rep_id)) {
            $data['assigned_at'] = absint($data['assigned_rep_id']) > 0 ? current_time('mysql') : null;
        }

        // Keep the sales pipeline aligned with lead qualification decisions.
        // A lead newly marked Qualified must also enter the Qualified stage.
        $quality_promoted_stage = false;
        $became_qualified = (
            isset($data['lead_quality']) &&
            'Qualified' === $data['lead_quality'] &&
            'Qualified' !== $old_lead->lead_quality
        ) || (
            isset($data['stage']) &&
            'Qualified' === $data['stage'] &&
            'Qualified' !== $old_lead->stage
        );

        if ($became_qualified) {
            $data['qualified_at'] = current_time('mysql');
            $data['qualified_by_user_id'] = get_current_user_id();
            $assigned_user = !empty($old_lead->assigned_rep_id) ? get_userdata(absint($old_lead->assigned_rep_id)) : false;
            if ($assigned_user && in_array('idlm_pre_sales', (array) $assigned_user->roles, true)) {
                $data['handoff_status'] = 'awaiting_manager';
                $data['handoff_at'] = null;
                $data['handoff_method'] = '';
                $data['handoff_exception_reason'] = '';
                $data['intake_owner_id'] = absint($old_lead->assigned_rep_id);
            } elseif ($assigned_user) {
                $data['handoff_status'] = 'manually_assigned';
                $data['handoff_at'] = current_time('mysql');
                $data['handoff_method'] = 'direct_sales_qualified';
                $data['handoff_exception_reason'] = '';
            } else {
                $data['handoff_status'] = 'awaiting_manager';
                $data['handoff_at'] = null;
                $data['handoff_method'] = '';
                $data['handoff_exception_reason'] = '';
            }
        }

        if (isset($data['lead_quality']) && 'Qualified' !== $data['lead_quality'] && 'Qualified' === $old_lead->lead_quality) {
            $data['handoff_status'] = 'not_ready';
            $data['handoff_at'] = null;
            $data['handoff_method'] = '';
            $data['handoff_exception_reason'] = '';
        }

        if (isset($data['lead_quality']) && 'Qualified' === $data['lead_quality']) {
            $data['disqualification_reason'] = '';
            $data['disqualification_method'] = '';
            $data['disqualified_at'] = null;
            $data['disqualified_by_user_id'] = 0;
        } elseif (isset($data['lead_quality']) && '' === $data['lead_quality']) {
            $data['qualified_at'] = null;
            $data['qualified_by_user_id'] = 0;
            $data['disqualification_reason'] = '';
            $data['disqualification_method'] = '';
            $data['disqualified_at'] = null;
            $data['disqualified_by_user_id'] = 0;
        }

        if (isset($data['lead_quality']) && 'Unqualified' === $data['lead_quality'] && 'Unqualified' !== $old_lead->lead_quality) {
            $data['disqualification_reason'] = $data['disqualification_reason'] ?? 'unable_to_contact';
            $data['disqualification_method'] = $data['disqualification_method'] ?? 'six_cycle_followup';
            $data['disqualified_at'] = $data['disqualified_at'] ?? current_time('mysql');
            $data['disqualified_by_user_id'] = $data['disqualified_by_user_id'] ?? get_current_user_id();
        }

        if (isset($data['assigned_rep_id']) && absint($data['assigned_rep_id']) !== absint($old_lead->assigned_rep_id) && 'Qualified' === $old_lead->lead_quality) {
            $new_owner = get_userdata(absint($data['assigned_rep_id']));
            if ($new_owner && !in_array('idlm_pre_sales', (array) $new_owner->roles, true)) {
                $data['handoff_status'] = 'manually_assigned';
                $data['handoff_at'] = current_time('mysql');
                $data['handoff_method'] = 'manual_manager';
                $data['handoff_exception_reason'] = '';
            }
        }

        if (
            isset($data['lead_quality']) &&
            'Qualified' === $data['lead_quality'] &&
            'Qualified' !== $old_lead->lead_quality &&
            'Qualified' !== $old_lead->stage
        ) {
            $data['stage'] = 'Qualified';
            $quality_promoted_stage = true;
        }

        // Check if Call Status is transition to Connected
        if (isset($data['call_status']) && $data['call_status'] !== $old_lead->call_status) {
            $connected_statuses = array('Connected - Interested', 'Connected - Not Interested');
            if (in_array($data['call_status'], $connected_statuses) && empty($old_lead->time_to_connected)) {
                $work_start = IDLM_Settings::get('idlm_working_hours_start', '09:00');
                $work_end = IDLM_Settings::get('idlm_working_hours_end', '18:00');
                $work_days = IDLM_Settings::get('idlm_working_days', array(1, 2, 3, 4, 5, 6));

                $elapsed_hours = IDLM_Integrations::get_elapsed_working_hours(
                    $old_lead->created_at,
                    current_time('mysql'),
                    $work_start,
                    $work_end,
                    $work_days
                );
                $data['time_to_connected'] = round($elapsed_hours * 3600);
            }
        }

        // Check if Stage transitions to Won
        if (isset($data['stage']) && $data['stage'] !== $old_lead->stage) {
            if ('Won' === $data['stage']) {
                $data['conversion_time'] = current_time('mysql');
            } elseif ('Won' === $old_lead->stage) {
                // conversion_time describes a current Won close, not a past stage.
                $data['conversion_time'] = null;
            }
            if ($data['stage'] === 'Won' && empty($old_lead->time_to_won) && !empty($old_lead->time_to_connected)) {
                // Find when call status was set to Connected
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
                $activity_connected = IDLM_Lead_Transaction::read($wpdb->get_var($wpdb->prepare(
                    "SELECT created_at FROM {$wpdb->prefix}idlm_lead_activity_log 
                     WHERE lead_id = %d AND activity_type = 'call_status_change' 
                     AND details LIKE %s ORDER BY id ASC LIMIT 1", 
                    $lead_id,
                    '%' . $wpdb->esc_like('Connected') . '%'
                )));
                $connected_time = $activity_connected ? $activity_connected : $old_lead->created_at;

                $work_start = IDLM_Settings::get('idlm_working_hours_start', '09:00');
                $work_end = IDLM_Settings::get('idlm_working_hours_end', '18:00');
                $work_days = IDLM_Settings::get('idlm_working_days', array(1, 2, 3, 4, 5, 6));

                $elapsed_hours = IDLM_Integrations::get_elapsed_working_hours(
                    $connected_time,
                    current_time('mysql'),
                    $work_start,
                    $work_end,
                    $work_days
                );
                $data['time_to_won'] = round($elapsed_hours * 3600);
            }
        }

        $capacity = IDLM_Assignment_Capacity::validate_transition($old_lead, array_merge((array) $old_lead, $data));
        if (is_wp_error($capacity)) { self::$last_validation_error = $capacity->get_error_message(); return false; }

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $updated = IDLM_Lead_Transaction::required($wpdb->update($table_leads, $data, array('id' => $lead_id)));

        if ($updated !== false) {
            $user_id = get_current_user_id();
            if ('Lost' === ($data['stage'] ?? '') && '' !== trim($lost_remark)) {
                IDLM_Lead_Transaction::required(self::log_activity($lead_id, 'lost_remark', 'Lost reason: ' . ($data['lost_reason'] ?? $old_lead->lost_reason) . '. Remark: ' . $lost_remark, $user_id));
            }

            if (isset($data['stage']) && (string) $data['stage'] !== (string) $old_lead->stage) {
                IDLM_Lead_Transaction::required(self::record_reporting_event(
                    $lead_id,
                    'stage_change',
                    $old_lead->stage,
                    $data['stage'],
                    'Lost' === $data['stage'] ? (isset($data['lost_reason']) ? $data['lost_reason'] : $old_lead->lost_reason) : '',
                    'manual',
                    $user_id,
                    $data['updated_at'],
                    'Won' === $data['stage'] ? ($data['deal_value'] ?? $old_lead->deal_value) : ('Lost' === $data['stage'] ? ($data['quote_value'] ?? $old_lead->quote_value) : null)
                ));
            }
            if (isset($data['assigned_rep_id']) && absint($data['assigned_rep_id']) !== absint($old_lead->assigned_rep_id)) {
                IDLM_Lead_Transaction::required(self::record_reporting_event(
                    $lead_id,
                    'assignment',
                    $old_lead->assigned_rep_id,
                    $data['assigned_rep_id'],
                    '',
                    'manual',
                    $user_id,
                    $data['assigned_at'] ?? $data['updated_at']
                ));
            }

            // Qualification ends the pre-sales sequence; never send unfinished
            // qualification tasks into the sales executive's daily backlog.
            if ($became_qualified) {
                // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- REL007-SQL-0413: update_lead_work(): canonical prefixed tables; identity predicates are fixed with bound contact values; IDs/cursors are bound; replay/ownership/required-write reads remain in the checked aggregate transaction.
                IDLM_Lead_Transaction::required($wpdb->query($wpdb->prepare(
                // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
                    "UPDATE {$wpdb->prefix}idlm_tasks
                     SET status = 'completed', completed_at = %s, updated_at = %s
                     WHERE lead_id = %d AND status = 'pending' AND task_type = 'Qualification'",
                    current_time('mysql'), current_time('mysql'), absint($lead_id)
                )));
                if (empty($old_lead->floor_plan_received) && '' !== trim($qualification_handoff_remark)) {
                    IDLM_Lead_Transaction::required(self::log_activity(
                        $lead_id,
                        'qualification_handoff_remark',
                        'Qualified without a floor plan. Handoff remark: ' . $qualification_handoff_remark,
                        $user_id
                    ));
                }
            }

            if (isset($data['lead_quality']) && 'Unqualified' === $data['lead_quality']) {
                // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- REL007-SQL-0414: update_lead_work(): canonical prefixed tables; identity predicates are fixed with bound contact values; IDs/cursors are bound; replay/ownership/required-write reads remain in the checked aggregate transaction.
                IDLM_Lead_Transaction::required($wpdb->query($wpdb->prepare(
                // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
                    "UPDATE {$wpdb->prefix}idlm_tasks
                     SET status = 'completed', completed_at = %s, updated_at = %s
                     WHERE lead_id = %d AND status = 'pending' AND task_type = 'Qualification'",
                    current_time('mysql'), current_time('mysql'), absint($lead_id)
                )));
            }

            if (isset($data['stage']) && 'Lost' === $data['stage'] && 'Lost' !== $old_lead->stage) {
                IDLM_Lead_Transaction::required(self::archive_pending_tasks_for_lost_lead($lead_id));
            }

            if (isset($data['assigned_rep_id']) && absint($data['assigned_rep_id']) !== absint($old_lead->assigned_rep_id) && 'Qualified' === $old_lead->lead_quality) {
                $task_policy = IDLM_Settings::get('idlm_handoff_task_policy', 'transfer');
                $table_tasks = $wpdb->prefix . 'idlm_tasks';
                if ('transfer' === $task_policy) {
                    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
                    IDLM_Lead_Transaction::required($wpdb->update(
                        $table_tasks,
                        array('assigned_rep_id' => absint($data['assigned_rep_id']), 'updated_at' => current_time('mysql')),
                        array('lead_id' => absint($lead_id), 'assigned_rep_id' => absint($old_lead->assigned_rep_id), 'status' => 'pending')
                    ));
                } elseif ('complete_qualification' === $task_policy) {
                    // phpcs:disable PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- REL007-SQL-0415: update_lead_work(): canonical prefixed tables; identity predicates are fixed with bound contact values; IDs/cursors are bound; replay/ownership/required-write reads remain in the checked aggregate transaction.
                    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
                    IDLM_Lead_Transaction::required($wpdb->query($wpdb->prepare(
                        "UPDATE {$table_tasks}
                         SET status = 'completed', completed_at = %s, updated_at = %s
                         WHERE lead_id = %d AND assigned_rep_id = %d AND status = 'pending'
                           AND LOWER(task_type) = 'qualification'",
                    // phpcs:enable PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                        current_time('mysql'),
                        current_time('mysql'),
                        absint($lead_id),
                        absint($old_lead->assigned_rep_id)
                    )));
                }
                IDLM_Lead_Transaction::required(self::ensure_qualified_handoff_task($lead_id, absint($data['assigned_rep_id'])));
            }

            // Reopened plans and pending pre-sales work follow the live owner under this same lock.
            $plan_transition = (isset($data['assigned_rep_id']) && absint($data['assigned_rep_id']) !== absint($old_lead->assigned_rep_id))
                || (isset($data['stage']) && in_array($old_lead->stage, array('Won', 'Lost'), true) && !in_array($data['stage'], array('Won', 'Lost'), true))
                || (isset($data['lead_quality']) && in_array($old_lead->lead_quality, array('Qualified', 'Unqualified'), true) && !in_array($data['lead_quality'], array('Qualified', 'Unqualified'), true));
            if ($plan_transition) {
                // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- REL007-SQL-0416: update_lead_work(): canonical prefixed tables; identity predicates are fixed with bound contact values; IDs/cursors are bound; replay/ownership/required-write reads remain in the checked aggregate transaction.
                $existing_plan = IDLM_Lead_Transaction::read($wpdb->get_var($wpdb->prepare(
                // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
                    "SELECT id FROM {$wpdb->prefix}idlm_tasks WHERE lead_id = %d AND task_type = 'Qualification' LIMIT 1", absint($lead_id)
                )));
                if ($existing_plan || !empty($old_lead->intake_owner_id)) {
                    IDLM_Lead_Transaction::required(self::sync_presales_task_status($lead_id));
                }
            }

            // Log details of changes
            foreach ($data as $key => $value) {
                if (in_array($key, array('updated_at', 'assigned_at', 'qualified_at', 'qualified_by_user_id', 'disqualified_at', 'disqualified_by_user_id', 'disqualification_method', 'intake_owner_id', 'handoff_status', 'handoff_at', 'handoff_method', 'handoff_exception_reason', 'time_to_connected', 'time_to_won'), true)) {
                    continue;
                }

                $old_val = isset($old_lead->$key) ? $old_lead->$key : '';
                
                $is_changed = false;
                if ($key === 'callback_datetime') {
                    $old_ts = (!empty($old_val) && $old_val !== '0000-00-00 00:00:00') ? strtotime($old_val) : false;
                    $new_ts = (!empty($value) && $value !== '0000-00-00 00:00:00') ? strtotime($value) : false;
                    $is_changed = ($old_ts !== $new_ts);
                } else {
                    $is_changed = ($old_val != $value);
                }

                if ($is_changed) {
                    $type = 'update';
                    $msg = "Field '$key' changed from '$old_val' to '$value'.";

                    if ($key === 'assigned_rep_id') {
                        $type = 'assignment';
                        $old_rep_obj = $old_val ? get_userdata($old_val) : false;
                        $old_rep = $old_rep_obj ? $old_rep_obj->display_name : 'Unassigned';
                        $new_rep_obj = $value ? get_userdata($value) : false;
                        $new_rep = $new_rep_obj ? $new_rep_obj->display_name : 'Unassigned';
                        $msg = "Assigned representative changed from $old_rep to $new_rep.";
                    } elseif ($key === 'call_status') {
                        $type = 'call_status_change';
                    } elseif ($key === 'stage') {
                        $type = 'stage_change';
                    } elseif ($key === 'floor_plan_file' || $key === 'floor_plan_files') {
                        $msg = "Floor Plan files updated.";
                    } elseif ($key === 'quote_file') {
                        $msg = "Quote file updated.";
                    } elseif ($key === 'callback_datetime') {
                        $old_formatted = $old_ts ? wp_date('Y-m-d h:i A', $old_ts) : 'None';
                        $new_formatted = $new_ts ? wp_date('Y-m-d h:i A', $new_ts) : 'None';
                        $msg = "Callback Date/Time changed from $old_formatted to $new_formatted.";
                    }

                    IDLM_Lead_Transaction::required(self::log_activity($lead_id, $type, $msg, $user_id));

                    // Trigger server-side Conversion API events if stage, status, or lead quality matches
                    if ($key === 'stage' && in_array($value, array(IDLM_State_Registry::STAGE_QUALIFIED, IDLM_State_Registry::STAGE_QUOTE_SHARED, IDLM_State_Registry::STAGE_WON), true)) {
                        IDLM_Lead_Transaction::defer('idlm_lead_stage_converted', $lead_id, $value, $old_lead);
                    } elseif ($key === 'lead_quality' && $value === 'Qualified' && !$quality_promoted_stage) {
                        IDLM_Lead_Transaction::defer('idlm_lead_stage_converted', $lead_id, 'Qualified', $old_lead);
                    }

                    // Trigger mapped Interakt events if field, value, and transition match
                    $interakt_enabled = get_option('idlm_interakt_enabled', '0');
                    $mapped_triggered = false;
                    if ($interakt_enabled === '1') {
                        $mappings = get_option('idlm_interakt_mappings', array());
                        if (is_array($mappings)) {
                            foreach ($mappings as $mapping) {
                                $m_type = isset($mapping['type']) ? $mapping['type'] : '';
                                $m_val  = isset($mapping['value']) ? $mapping['value'] : '';
                                $m_evt  = isset($mapping['event']) ? $mapping['event'] : '';

                                if ($key === $m_type && $value === $m_val) {
                                    $old_val = isset($old_lead->$key) ? $old_lead->$key : '';
                                    if ($old_val !== $value) {
                                        IDLM_Lead_Transaction::defer('idlm_trigger_interakt_event', $lead_id, $m_evt, $old_lead, array('field'=>$key, 'value'=>$value, 'owner'=>(int) ($data['assigned_rep_id'] ?? $old_lead->assigned_rep_id)));
                                        if ($key === 'call_status' && $value === 'No Answer') {
                                            $mapped_triggered = true;
                                        }
                                    }
                                }
                            }
                        }
                    }

                    // Legacy call status fallback (only trigger if not already handled by mapped triggers)
                    if ($key === 'call_status' && $value === 'No Answer' && !$mapped_triggered) {
                        IDLM_Lead_Transaction::defer('idlm_lead_call_status_no_answer', $lead_id, $old_lead, array('field'=>'call_status', 'value'=>'No Answer', 'owner'=>(int) ($data['assigned_rep_id'] ?? $old_lead->assigned_rep_id), 'event'=>get_option('idlm_interakt_event_name', 'lead_no_answer')));
                    }
                }
            }

            IDLM_Lead_Transaction::invalidate('lead_update', $lead_id);
            return true;
        }

        return false;
    }
}
