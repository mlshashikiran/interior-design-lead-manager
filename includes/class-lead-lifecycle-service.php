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
    public static function upsert_inbound_lead($data, $platform, $payload = array()) {
        global $wpdb;
        $table_leads = $wpdb->prefix . 'idlm_leads';
        $platform = sanitize_key($platform);
        $identity = self::get_inbound_identity_key($data);
        $lock_name = 'idlm_' . substr(hash('sha256', $identity), 0, 40);
        $locked = (int) $wpdb->get_var($wpdb->prepare('SELECT GET_LOCK(%s, 5)', $lock_name));

        if (1 !== $locked) {
            self::$last_validation_error = 'Lead matching is busy. Please retry the submission.';
            return false;
        }

        try {
            $match = self::find_inbound_canonical_id($data, $platform);
            $canonical_id = absint($match['lead_id']);
            if ($canonical_id) {
                $canonical = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table_leads} WHERE id = %d", $canonical_id));
                $updates = self::build_inbound_canonical_updates($canonical, $data, $platform);
                if (!empty($updates)) {
                    $updates['updated_at'] = current_time('mysql');
                    $wpdb->update($table_leads, $updates, array('id' => $canonical_id));
                    self::log_activity(
                        $canonical_id,
                        'attribution',
                        sprintf('Matched a %s submission to this canonical lead. Updated: %s.', $platform, implode(', ', array_keys($updates)))
                    );
                    do_action('idlm_lead_attribution_merged', $canonical_id, $updates);
                    IDLM_Read_Model_Cache::invalidate('inbound_lead_update');
                } else {
                    self::log_activity($canonical_id, 'attribution', sprintf('Matched a %s submission to this canonical lead.', $platform));
                }
                self::record_lead_touchpoint($canonical_id, $platform, $data, $payload);
                return array('lead_id' => $canonical_id, 'created' => false, 'matched' => true);
            }

            $data['original_source'] = !empty($data['source']) ? sanitize_text_field($data['source']) : '';
            $data['latest_source'] = $data['original_source'];
            // Do not turn an uncertain identity into a duplicate relationship. The
            // original inquiry remains actionable and an administrator can use the
            // existing duplicate-review workflow to resolve it deliberately.
            $lead_id = self::insert_lead($data, !empty($match['ambiguous_candidate_ids']));
            if (!$lead_id) {
                return false;
            }
            if (!empty($match['ambiguous_candidate_ids'])) {
                self::log_activity(
                    $lead_id,
                    'duplicate_review',
                    sprintf(
                        'Inbound %s inquiry requires duplicate review; uncertain or conflicting identity candidates: %s.',
                        $platform,
                        implode(', ', array_map('absint', $match['ambiguous_candidate_ids']))
                    )
                );
            }
            self::record_lead_touchpoint($lead_id, $platform, $data, $payload);
            return array(
                'lead_id' => $lead_id,
                'created' => true,
                'matched' => false,
                'requires_duplicate_review' => !empty($match['ambiguous_candidate_ids']),
            );
        } finally {
            $wpdb->get_var($wpdb->prepare('SELECT RELEASE_LOCK(%s)', $lock_name));
        }
    }

    private static function get_inbound_identity_key($data) {
        $phone = preg_replace('/[^0-9]/', '', (string) ($data['phone'] ?? ''));
        if (strlen($phone) >= 10) {
            return 'phone:' . substr($phone, -10);
        }
        $email = strtolower(trim((string) ($data['email'] ?? '')));
        if ($email) {
            return 'email:' . $email;
        }
        $external_id = self::get_inbound_external_id($data);
        return $external_id ? 'external:' . $external_id : 'anonymous:' . wp_generate_uuid4();
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
            $touchpoint_leads = $wpdb->get_col($wpdb->prepare(
                "SELECT DISTINCT t.lead_id FROM {$wpdb->prefix}idlm_lead_touchpoints t INNER JOIN {$table_leads} l ON l.id = t.lead_id WHERE t.platform = %s AND t.external_id = %s AND l.is_duplicate = 0 LIMIT 2",
                sanitize_key($platform),
                $external_id
            ));
            $touchpoint_leads = array_values(array_unique(array_filter(array_map('absint', $touchpoint_leads))));
            if (1 === count($touchpoint_leads)) {
                return array('lead_id' => $touchpoint_leads[0], 'ambiguous_candidate_ids' => array());
            }
            if (count($touchpoint_leads) > 1) {
                return array('lead_id' => 0, 'ambiguous_candidate_ids' => $touchpoint_leads);
            }

            // Preserve compatibility with legacy inbound rows that predate
            // touchpoints, but never select an arbitrary duplicate external ID.
            $external_matches = $wpdb->get_col($wpdb->prepare(
                "SELECT id FROM {$table_leads} WHERE is_duplicate = 0 AND fb_lead_id = %s LIMIT 2",
                $external_id
            ));
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
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $candidates = $wpdb->get_results($wpdb->prepare($sql, $params));
        $matching_ids = array();
        $conflicting_ids = array();
        foreach ($candidates as $candidate) {
            $candidate_email = strtolower(trim((string) $candidate->email));
            $normalized_phone = self::normalize_identity_phone($phone);
            $candidate_normalized_phone = self::normalize_identity_phone($candidate->phone);
            if (($email && $candidate_email && $email !== $candidate_email) ||
                ($normalized_phone && $candidate_normalized_phone && $normalized_phone !== $candidate_normalized_phone)) {
                $conflicting_ids[] = absint($candidate->id);
                continue;
            }
            if ($email && $email === strtolower(trim((string) $candidate->email))) {
                $matching_ids[] = absint($candidate->id);
                continue;
            }
            $candidate_phone = preg_replace('/[^0-9]/', '', (string) $candidate->phone);
            if ($phone && $candidate_phone && ($phone === $candidate_phone || (strlen($phone) >= 10 && strlen($candidate_phone) >= 10 && substr($phone, -10) === substr($candidate_phone, -10)))) {
                $matching_ids[] = absint($candidate->id);
            }
        }
        $matching_ids = array_values(array_unique(array_filter($matching_ids)));
        if ($conflicting_ids) {
            return array('lead_id' => 0, 'ambiguous_candidate_ids' => array_values(array_unique(array_merge($matching_ids, $conflicting_ids))));
        }
        return array(
            'lead_id' => 1 === count($matching_ids) ? $matching_ids[0] : 0,
            'ambiguous_candidate_ids' => count($matching_ids) > 1 ? $matching_ids : array(),
        );
    }

    /** Canonical India-default phone identity without dropping explicit country codes. */
    private static function normalize_identity_phone($phone) {
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
        $current_primary = strtolower(trim((string) $canonical->source));
        $incoming_primary = strtolower(trim($incoming_source));
        if ('meta' === $platform && 'meta ads' === $incoming_primary && in_array($current_primary, $generic_sources, true)) {
            $updates['source'] = $incoming_source;
            $updates['original_source'] = $incoming_source;
        } elseif (empty($canonical->original_source) && $incoming_source) {
            $updates['original_source'] = $incoming_source;
        }
        return $updates;
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
            'submitted_at' => !empty($data['conversion_time']) ? sanitize_text_field($data['conversion_time']) : null,
            'received_at' => current_time('mysql'),
        );
        foreach (array('utm_source', 'utm_medium', 'utm_campaign', 'utm_adgroup', 'utm_term', 'utm_content', 'gclid', 'gbraid', 'wbraid', 'fbclid', 'li_fat_id', 'landing_page') as $field) {
            $row[$field] = sanitize_text_field($data[$field] ?? '');
        }
        // A repeated external webhook is idempotent; submissions without an external ID remain distinct touches.
        $wpdb->insert($table, $row);
    }

    protected static function backfill_duplicate_touchpoints() {
        global $wpdb;
        $table_leads = $wpdb->prefix . 'idlm_leads';
        $duplicates = $wpdb->get_results("SELECT * FROM {$table_leads} WHERE is_duplicate = 1 AND duplicate_of_id > 0 ORDER BY id ASC", ARRAY_A);
        foreach ($duplicates as $duplicate) {
            self::record_lead_touchpoint(
                absint($duplicate['duplicate_of_id']),
                'legacy_duplicate',
                $duplicate,
                array('legacy_duplicate_lead_id' => absint($duplicate['id'])),
                'legacy-duplicate-' . absint($duplicate['id'])
            );
        }
    }

    /**
     * Add lead and run duplicate detection
     */
    public static function insert_lead($data, $skip_duplicate_detection = false) {
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
        if ('Won' === $data['stage'] && empty($data['conversion_time'])) {
            $data['conversion_time'] = current_time('mysql');
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
            $candidates = $wpdb->get_results($wpdb->prepare($duplicate_query, $params));

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

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $inserted = $wpdb->insert($table_leads, $data);
        if ($inserted) {
            $lead_id = $wpdb->insert_id;

            // Log activity
            $log_msg = "Lead created.";
            if ($is_duplicate) {
                $log_msg .= " Marked as duplicate of Lead ID: " . $duplicate_of_id;
            }
            self::log_activity($lead_id, 'creation', $log_msg);
            self::record_reporting_event(
                $lead_id,
                'stage_change',
                '',
                $data['stage'],
                isset($data['lost_reason']) ? $data['lost_reason'] : '',
                'creation',
                0,
                null,
                'Won' === $data['stage'] ? ($data['deal_value'] ?? 0) : ('Lost' === $data['stage'] ? ($data['quote_value'] ?? 0) : null)
            );
            if (!empty($data['assigned_rep_id'])) {
                self::record_reporting_event($lead_id, 'assignment', '', $data['assigned_rep_id'], '', 'creation');
            }

            if ($is_duplicate && $duplicate_of_id > 0) {
                // Fetch original lead to compare and merge attribution details
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
                $original_lead = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table_leads} WHERE id = %d", $duplicate_of_id));
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
                        $wpdb->update($table_leads, $original_updates, array('id' => $duplicate_of_id));
                        self::log_activity($duplicate_of_id, 'update', "Merged attribution data from duplicate Lead ID: {$lead_id}. Fields: " . implode(', ', array_keys($original_updates)));
                        
                        // Trigger hook for CAPI re-fire
                        do_action('idlm_lead_attribution_merged', $duplicate_of_id, $original_updates);
                    }
                }
            }

            // Trigger CAPI for initial lead event only if it's NOT a duplicate (implemented by Shashi)
            if (!$is_duplicate) {
                do_action('idlm_lead_stage_converted', $lead_id, 'New', (object) array('stage' => ''));
            }

            IDLM_Read_Model_Cache::invalidate('lead_insert');
            return $lead_id;
        }

        return false;
    }

    /**
     * Update an existing lead, tracking specific status changes
     */
    public static function update_lead($lead_id, $data) {
        global $wpdb;
        $table_leads = $wpdb->prefix . 'idlm_leads';

        // Fetch old data to detect changes
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
        $old_lead = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_leads WHERE id = %d", $lead_id));
        if (!$old_lead) {
            return false;
        }

        self::$last_validation_error = '';
        $data = self::normalize_workflow_transition($old_lead, $data);
        if (false === $data) {
            return false;
        }
        if (!self::validate_quality_transition($lead_id, $old_lead, $data)) {
            return false;
        }

        $qualification_handoff_remark = isset($data['qualification_handoff_remark'])
            ? sanitize_textarea_field($data['qualification_handoff_remark'])
            : '';
        unset($data['qualification_handoff_remark']);

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
                $activity_connected = $wpdb->get_var($wpdb->prepare(
                    "SELECT created_at FROM {$wpdb->prefix}idlm_lead_activity_log 
                     WHERE lead_id = %d AND activity_type = 'call_status_change' 
                     AND details LIKE %s ORDER BY id ASC LIMIT 1", 
                    $lead_id,
                    '%' . $wpdb->esc_like('Connected') . '%'
                ));
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

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $updated = $wpdb->update($table_leads, $data, array('id' => $lead_id));

        if ($updated !== false) {
            $user_id = get_current_user_id();

            if (isset($data['stage']) && (string) $data['stage'] !== (string) $old_lead->stage) {
                self::record_reporting_event(
                    $lead_id,
                    'stage_change',
                    $old_lead->stage,
                    $data['stage'],
                    'Lost' === $data['stage'] ? (isset($data['lost_reason']) ? $data['lost_reason'] : $old_lead->lost_reason) : '',
                    'manual',
                    $user_id,
                    $data['updated_at'],
                    'Won' === $data['stage'] ? ($data['deal_value'] ?? $old_lead->deal_value) : ('Lost' === $data['stage'] ? ($data['quote_value'] ?? $old_lead->quote_value) : null)
                );
            }
            if (isset($data['assigned_rep_id']) && absint($data['assigned_rep_id']) !== absint($old_lead->assigned_rep_id)) {
                self::record_reporting_event(
                    $lead_id,
                    'assignment',
                    $old_lead->assigned_rep_id,
                    $data['assigned_rep_id'],
                    '',
                    'manual',
                    $user_id,
                    $data['assigned_at'] ?? $data['updated_at']
                );
            }

            // Qualification ends the pre-sales sequence; never send unfinished
            // qualification tasks into the sales executive's daily backlog.
            if ($became_qualified) {
                $wpdb->query($wpdb->prepare(
                    "UPDATE {$wpdb->prefix}idlm_tasks
                     SET status = 'completed', completed_at = %s, updated_at = %s
                     WHERE lead_id = %d AND status = 'pending' AND task_type = 'Qualification'",
                    current_time('mysql'), current_time('mysql'), absint($lead_id)
                ));
                if (empty($old_lead->floor_plan_received) && '' !== trim($qualification_handoff_remark)) {
                    self::log_activity(
                        $lead_id,
                        'qualification_handoff_remark',
                        'Qualified without a floor plan. Handoff remark: ' . $qualification_handoff_remark,
                        $user_id
                    );
                }
            }

            if (isset($data['lead_quality']) && 'Unqualified' === $data['lead_quality']) {
                $wpdb->query($wpdb->prepare(
                    "UPDATE {$wpdb->prefix}idlm_tasks
                     SET status = 'completed', completed_at = %s, updated_at = %s
                     WHERE lead_id = %d AND status = 'pending' AND task_type = 'Qualification'",
                    current_time('mysql'), current_time('mysql'), absint($lead_id)
                ));
            }

            if (isset($data['stage']) && 'Lost' === $data['stage'] && 'Lost' !== $old_lead->stage) {
                self::archive_pending_tasks_for_lost_lead($lead_id);
            }

            if (isset($data['assigned_rep_id']) && absint($data['assigned_rep_id']) !== absint($old_lead->assigned_rep_id) && 'Qualified' === $old_lead->lead_quality) {
                $task_policy = IDLM_Settings::get('idlm_handoff_task_policy', 'transfer');
                $table_tasks = $wpdb->prefix . 'idlm_tasks';
                if ('transfer' === $task_policy) {
                    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
                    $wpdb->update(
                        $table_tasks,
                        array('assigned_rep_id' => absint($data['assigned_rep_id']), 'updated_at' => current_time('mysql')),
                        array('lead_id' => absint($lead_id), 'assigned_rep_id' => absint($old_lead->assigned_rep_id), 'status' => 'pending')
                    );
                } elseif ('complete_qualification' === $task_policy) {
                    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
                    $wpdb->query($wpdb->prepare(
                        "UPDATE {$table_tasks}
                         SET status = 'completed', completed_at = %s, updated_at = %s
                         WHERE lead_id = %d AND assigned_rep_id = %d AND status = 'pending'
                           AND LOWER(task_type) = 'qualification'",
                        current_time('mysql'),
                        current_time('mysql'),
                        absint($lead_id),
                        absint($old_lead->assigned_rep_id)
                    ));
                }
                self::ensure_qualified_handoff_task($lead_id, absint($data['assigned_rep_id']));
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

                    self::log_activity($lead_id, $type, $msg, $user_id);

                    // Trigger server-side Conversion API events if stage, status, or lead quality matches
                    if ($key === 'stage' && in_array($value, array(IDLM_State_Registry::STAGE_QUALIFIED, IDLM_State_Registry::STAGE_QUOTE_SHARED, IDLM_State_Registry::STAGE_WON), true)) {
                        do_action('idlm_lead_stage_converted', $lead_id, $value, $old_lead);
                    } elseif ($key === 'lead_quality' && $value === 'Qualified' && !$quality_promoted_stage) {
                        do_action('idlm_lead_stage_converted', $lead_id, 'Qualified', $old_lead);
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
                                        do_action('idlm_trigger_interakt_event', $lead_id, $m_evt, $old_lead);
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
                        do_action('idlm_lead_call_status_no_answer', $lead_id, $old_lead);
                    }
                }
            }

            IDLM_Read_Model_Cache::invalidate('lead_update');
            return true;
        }

        return false;
    }
}
