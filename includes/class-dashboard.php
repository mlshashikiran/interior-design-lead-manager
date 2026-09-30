<?php
if (!defined('ABSPATH')) {
    exit;
}

class IDLM_Dashboard {
    /** Dashboard contexts use existing CRM role recognition and record visibility. */
    public static function access_context() {
        $allowed = IDLM_Lead_Access_Policy::has_rep_access();
        $manager = $allowed && IDLM_Lead_Access_Policy::can_assign();
        $marketing = false;
        $sales = false;
        $registry = wp_roles()->roles;
        foreach ((array) wp_get_current_user()->roles as $role) {
            $names = array($role, $registry[$role]['name'] ?? '');
            $is_marketing = false;
            foreach ($names as $name) {
                $normalized = preg_replace('/[^a-z0-9]/', '', strtolower(wp_strip_all_tags($name)));
                $is_marketing = $is_marketing || in_array($normalized, array('marketing', 'marketingmanager'), true);
            }
            $marketing = $marketing || $is_marketing;
            $sales = $sales || (!$is_marketing && in_array($role, IDLM_Lead_Access_Policy::allowed_rep_roles(), true));
        }
        $pre_sales = $allowed && !$manager && IDLM_Lead_Access_Policy::is_pre_sales();
        return array(
            'sales' => $allowed && ($manager || $sales),
            'marketing' => $allowed && ($manager || $marketing || $pre_sales),
            'manager' => $manager,
            'pre_sales' => $pre_sales,
            'scope' => IDLM_Lead_Access_Policy::restriction_user_id() ? 'Your currently assigned leads' : 'All leads permitted by your CRM visibility',
        );
    }

    /** Enforce context permissions on the payload, including direct/cached reads. */
    public static function filter_stats($stats) {
        $access = self::access_context();
        if (!$access['sales'] && !$access['marketing']) { return array('access' => $access); }
        if (!$access['marketing']) { unset($stats['marketing'], $stats['data_quality']); }
        if (!$access['sales']) { unset($stats['sales']); }
        if (!$access['manager']) {
            unset($stats['targets']);
            foreach (array('leads_by_rep', 'assignment_distribution', 'pre_sales_performance', 'studio_performance') as $key) {
                unset($stats['sales'][$key]);
            }
            if (!$access['pre_sales']) { unset($stats['sales']['pre_sales_activity']); }
        }
        if ($access['pre_sales']) {
            $stats['sales'] = array_intersect_key($stats['sales'], array_flip(array(
                'funnel_data', 'open_pipeline_total', 'open_pipeline_as_of', 'open_pipeline_health',
                'avg_time_connected', 'avg_time_connected_seconds', 'avg_time_connected_previous_seconds', 'plan_collection_rate', 'qualified_with_plan', 'qualified_with_plan_previous', 'qualified_total', 'qualified_total_previous', 'pending_qualification_total', 'pending_qualification_total_previous',
                'lead_quality_distribution', 'call_status_distribution', 'pipeline_by_channel',
                'lost_reasons', 'lost_reasons_basis', 'lost_reasons_legacy_count', 'business_mix',
                'total_won', 'total_won_previous', 'won_revenue_total', 'won_revenue_previous', 'won_legacy_count', 'estimated_pipeline_total',
                'estimated_pipeline_covered_leads', 'currency_symbol', 'pre_sales_portfolio_activity'
            )));
            $stats['marketing'] = array_intersect_key($stats['marketing'], array_flip(array(
                'total_leads_period', 'total_leads_previous', 'growth_pct', 'qualified_total', 'qualified_total_previous', 'cohort_won_current', 'cohort_won_previous', 'leads_by_channel',
                'leads_over_time', 'disqualification_reasons'
            )));
        }
        $stats['access'] = $access;
        return $stats;
    }

    /** Bounded record investigation, sharing the aggregate's current-owner scope. */
    public static function get_records($kind, $value, $period, $start = '', $end = '', $page = 1) {
        global $wpdb;
        $access = self::access_context();
        $marketing_kinds = array('channel', 'campaign', 'untracked', 'pending_quality');
        $sales_kinds = array('stage', 'overdue', 'no_task', 'aged', 'handoff');
        if ((!in_array($kind, $marketing_kinds, true) && !in_array($kind, $sales_kinds, true)) ||
            (in_array($kind, $marketing_kinds, true) && !$access['marketing']) ||
            (in_array($kind, $sales_kinds, true) && !$access['sales']) ||
            ('handoff' === $kind && !$access['manager'])) {
            return new WP_Error('idlm_dashboard_access', 'This dashboard investigation is not available for your account.');
        }
        $params = array();
        $where = 'l.is_duplicate = 0' . IDLM_Lead_Access_Policy::scope_sql('l', $params);
        $tasks = $wpdb->prefix . 'idlm_tasks';
        if (in_array($kind, $marketing_kinds, true)) {
            $ranges = self::get_date_ranges($period, $start, $end);
            $where .= ' AND l.created_at BETWEEN %s AND %s';
            array_push($params, $ranges['curr_start'], $ranges['curr_end']);
            if ('channel' === $kind || 'campaign' === $kind) {
                $field = 'channel' === $kind ? 'source' : 'utm_campaign';
                $where .= " AND COALESCE(l.{$field}, '') = %s";
                $params[] = $value;
            } elseif ('untracked' === $kind) {
                $where .= " AND (COALESCE(l.utm_source, '') = '' OR COALESCE(l.utm_medium, '') = '')";
            } else {
                $where .= " AND COALESCE(l.lead_quality, '') = ''";
            }
        } else {
            $where .= " AND COALESCE(l.stage, '') NOT IN ('Won', 'Lost')";
            if ('stage' === $kind) {
                $where .= " AND COALESCE(NULLIF(l.stage, ''), 'New') = %s";
                $params[] = $value;
            } elseif ('overdue' === $kind) {
                $where .= " AND EXISTS (SELECT 1 FROM {$tasks} t WHERE t.lead_id = l.id AND t.status = 'pending' AND t.due_date < %s)";
                $params[] = current_time('mysql');
            } elseif ('no_task' === $kind) {
                $where .= " AND NOT EXISTS (SELECT 1 FROM {$tasks} t WHERE t.lead_id = l.id AND t.status = 'pending')";
            } elseif ('aged' === $kind) {
                $where .= ' AND l.created_at < %s';
                $params[] = (new DateTime('now', wp_timezone()))->modify('-60 days')->format('Y-m-d H:i:s');
            } else {
                $where .= " AND l.handoff_status = 'awaiting_manager'";
            }
        }
        $page = max(1, min(10000, absint($page)));
        $params[] = ($page - 1) * 25;
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT l.id, l.first_name, l.last_name, l.stage, l.lead_quality, l.created_at,
                (SELECT MIN(t.due_date) FROM {$tasks} t WHERE t.lead_id = l.id AND t.status = 'pending') AS next_due
             FROM {$wpdb->prefix}idlm_leads l WHERE {$where}
             ORDER BY l.created_at ASC, l.id ASC LIMIT 26 OFFSET %d", $params
        ));
        return array('records' => array_slice($rows ?: array(), 0, 25), 'has_more' => count($rows ?: array()) > 25, 'page' => $page);
    }

    /** @var bool|null Cached compatibility check for installations upgraded before event amounts existed. */
    private static $state_event_amount_supported = null;

    /** @var array<string,bool> Cached presence of immutable event history by type. */
    private static $reporting_event_history = array();

    /**
     * Get dashboard stats JSON data based on period
     */
    public static function get_stats($period = 'this_month', $custom_start = '', $custom_end = '', $filters = array()) {
        return self::filter_stats(IDLM_Dashboard_Read_Model::get_stats($period, $custom_start, $custom_end, $filters));
    }

    /**
     * Build dashboard statistics from the canonical database reads.
     *
     * Callers should use get_stats() so identical scoped reads can be reused.
     */
    public static function calculate_stats($period = 'this_month', $custom_start = '', $custom_end = '', $filters = array()) {
        if (!IDLM_Lead_Access_Policy::has_rep_access()) { return array(); }
        global $wpdb;
        $table_leads = $wpdb->prefix . 'idlm_leads';

        // 1. Calculate Date Ranges (Current vs Previous)
        $ranges = self::get_date_ranges($period, $custom_start, $custom_end);
        $curr_start = $ranges['curr_start'];
        $curr_end = $ranges['curr_end'];
        $prev_start = $ranges['prev_start'];
        $prev_end = $ranges['prev_end'];

        // Rep visibility permissions check
        $rep_id = IDLM_Lead_Access_Policy::restriction_user_id();

        $rep_clause = $rep_id ? $wpdb->prepare(" AND assigned_rep_id = %d", $rep_id) : "";
        $filters = wp_parse_args((array) $filters, array('rep_id' => 0, 'studio' => '', 'source' => '', 'campaign' => ''));
        $filters['rep_id'] = absint($filters['rep_id']);
        if (!self::access_context()['manager']) { $filters['rep_id'] = 0; }
        foreach (array('studio', 'source', 'campaign') as $filter_key) { $filters[$filter_key] = sanitize_text_field($filters[$filter_key]); }
        if ($filters['rep_id']) { $rep_clause .= $wpdb->prepare(' AND assigned_rep_id = %d', $filters['rep_id']); }
        if ('' !== $filters['studio']) { $rep_clause .= $wpdb->prepare(' AND preferred_studio = %s', $filters['studio']); }
        if ('' !== $filters['source']) { $rep_clause .= $wpdb->prepare(' AND source = %s', $filters['source']); }
        if ('' !== $filters['campaign']) { $rep_clause .= $wpdb->prepare(' AND utm_campaign = %s', $filters['campaign']); }

        // Keep the live work queue scoped to currently assigned leads, while
        // preserving completed handoffs in pre-sales reporting. A qualified
        // lead is commonly reassigned to Sales, so assigned_rep_id alone would
        // otherwise erase its qualification and revenue outcome.
        $reporting_rep_clause = $rep_clause;
        if ($rep_id && IDLM_Lead_Access_Policy::is_pre_sales()) {
            $reporting_rep_clause = $wpdb->prepare(
                ' AND (assigned_rep_id = %d OR intake_owner_id = %d OR qualified_by_user_id = %d)',
                $rep_id,
                $rep_id,
                $rep_id
            );
            if ('' !== $filters['studio']) { $reporting_rep_clause .= $wpdb->prepare(' AND preferred_studio = %s', $filters['studio']); }
            if ('' !== $filters['source']) { $reporting_rep_clause .= $wpdb->prepare(' AND source = %s', $filters['source']); }
            if ('' !== $filters['campaign']) { $reporting_rep_clause .= $wpdb->prepare(' AND utm_campaign = %s', $filters['campaign']); }
        }

        // 2. Lead Volumes & Growth
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
        $total_curr = intval($wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(id) FROM $table_leads WHERE is_duplicate = 0 AND created_at BETWEEN %s AND %s $reporting_rep_clause",
            $curr_start, $curr_end
        )));

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
        $total_prev = intval($wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(id) FROM $table_leads WHERE is_duplicate = 0 AND created_at BETWEEN %s AND %s $reporting_rep_clause",
            $prev_start, $prev_end
        )));

        $percentage_change = null;
        if ($total_prev > 0) {
            $percentage_change = (($total_curr - $total_prev) / $total_prev) * 100;
        }
        $percentage_delta = static function($current, $previous) {
            return $previous > 0 ? round((($current - $previous) / $previous) * 100, 1) : null;
        };

        // Duplicate Lead Volumes & Growth
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
        $dup_curr = intval($wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(id) FROM $table_leads WHERE is_duplicate = 1 AND created_at BETWEEN %s AND %s $rep_clause",
            $curr_start, $curr_end
        )));

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
        $dup_prev = intval($wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(id) FROM $table_leads WHERE is_duplicate = 1 AND created_at BETWEEN %s AND %s $rep_clause",
            $prev_start, $prev_end
        )));

        $dup_percentage_change = null;
        if ($dup_prev > 0) {
            $dup_percentage_change = (($dup_curr - $dup_prev) / $dup_prev) * 100;
        }

        // 3. Leads by Channel: acquisition quality plus selected-period closes.
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
        $channel_leads_raw = $wpdb->get_results($wpdb->prepare(
            "SELECT source as channel, 
                    SUM(CASE WHEN created_at BETWEEN %s AND %s THEN 1 ELSE 0 END) as count,
                    SUM(CASE WHEN created_at BETWEEN %s AND %s AND LOWER(lead_quality) = 'qualified' THEN 1 ELSE 0 END) as qualified,
                    SUM(CASE WHEN created_at BETWEEN %s AND %s AND LOWER(lead_quality) = 'unqualified' THEN 1 ELSE 0 END) as unqualified,
                    SUM(CASE WHEN created_at BETWEEN %s AND %s AND (lead_quality = '' OR lead_quality IS NULL) THEN 1 ELSE 0 END) as pending,
                    SUM(CASE WHEN created_at BETWEEN %s AND %s AND stage = 'Won' THEN 1 ELSE 0 END) as cohort_won,
                    SUM(CASE WHEN stage = 'Won' AND conversion_time BETWEEN %s AND %s THEN 1 ELSE 0 END) as won,
                    SUM(CASE WHEN stage = 'Won' AND conversion_time BETWEEN %s AND %s THEN COALESCE(deal_value, 0) ELSE 0 END) as revenue,
                    SUM(CASE WHEN created_at BETWEEN %s AND %s AND stage = 'Lost' THEN 1 ELSE 0 END) as lost,
                    SUM(CASE WHEN created_at BETWEEN %s AND %s AND utm_source <> '' AND utm_medium <> '' THEN 1 ELSE 0 END) as attributed
             FROM $table_leads 
             WHERE is_duplicate = 0 $reporting_rep_clause
               AND (created_at BETWEEN %s AND %s OR (stage = 'Won' AND conversion_time BETWEEN %s AND %s))
             GROUP BY source 
             ORDER BY won DESC, count DESC",
            $curr_start, $curr_end, $curr_start, $curr_end, $curr_start, $curr_end,
            $curr_start, $curr_end, $curr_start, $curr_end, $curr_start, $curr_end,
            $curr_start, $curr_end, $curr_start, $curr_end, $curr_start, $curr_end,
            $curr_start, $curr_end, $curr_start, $curr_end
        ));

        $leads_by_channel = array();
        if ($channel_leads_raw) {
            foreach ($channel_leads_raw as $row) {
                $leads_by_channel[] = (object) array(
                    'source_value' => (string) $row->channel,
                    'cohort_won' => intval($row->cohort_won),
                    'won' => intval($row->won),
                    'revenue' => round(floatval($row->revenue), 2),
                    'lost' => intval($row->lost),
                    'channel' => empty($row->channel) ? 'Direct / Unknown' : $row->channel,
                    'count' => intval($row->count),
                    'qualified' => intval($row->qualified),
                    'unqualified' => intval($row->unqualified),
                    'pending' => intval($row->pending),
                    'attributed' => intval($row->attributed)
                );
            }
        }

        // 4. Qualified Lead Rate by Channel (Current Period)


        // 5. Top UTM Campaigns and other UTM parameters (implemented by Shashi)
        $utm_fields = array('utm_campaign', 'utm_source', 'utm_medium', 'utm_adgroup', 'utm_term', 'utm_content');
        $utm_data = array();

        foreach ($utm_fields as $field) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
            $results = $wpdb->get_results($wpdb->prepare(
                "SELECT $field as utm_value,
                        SUM(CASE WHEN stage = 'Won' THEN 1 ELSE 0 END) as won,
                        COUNT(id) as count,
                        SUM(CASE WHEN LOWER(lead_quality) = 'qualified' THEN 1 ELSE 0 END) as qualified,
                        SUM(CASE WHEN LOWER(lead_quality) = 'unqualified' THEN 1 ELSE 0 END) as unqualified,
                        SUM(CASE WHEN lead_quality = '' OR lead_quality IS NULL THEN 1 ELSE 0 END) as pending
                 FROM $table_leads 
                 WHERE is_duplicate = 0 AND created_at BETWEEN %s AND %s AND $field != '' $rep_clause
                 GROUP BY $field 
                 ORDER BY count DESC 
                 LIMIT 5",
                $curr_start, $curr_end
            ));

            $formatted_results = array();
            if ($results) {
                foreach ($results as $row) {
                    $formatted_results[] = array(
                        $field => $row->utm_value,
                        'utm_value' => $row->utm_value,
                        'won' => intval($row->won),
                        'count' => intval($row->count),
                        'qualified' => intval($row->qualified),
                        'unqualified' => intval($row->unqualified),
                        'pending' => intval($row->pending)
                    );
                }
            }
            $utm_data[$field] = $formatted_results;
        }
        // Campaign Explorer retains campaign-to-ad-group hierarchy from the reference body.
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
        $campaign_adgroups = $wpdb->get_results($wpdb->prepare(
            "SELECT utm_campaign, COALESCE(NULLIF(TRIM(utm_adgroup), ''), 'Unspecified ad group') AS adgroup, COUNT(id) AS count, SUM(LOWER(lead_quality) = 'qualified') AS qualified, SUM(stage = 'Won') AS won FROM {$table_leads} WHERE is_duplicate = 0 AND utm_campaign <> '' AND created_at BETWEEN %s AND %s {$rep_clause} GROUP BY utm_campaign, adgroup ORDER BY utm_campaign, count DESC",
            $curr_start,
            $curr_end
        ), ARRAY_A);

        // --- SALES PIPELINE METRICS ---

        // 7. Current open-pipeline snapshot (grouping empty stages as 'New').
        // This deliberately has no reporting-period condition: a lead's current
        // stage is a snapshot, while Won is reported separately as an event.
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
        $leads_by_stage_raw = $wpdb->get_results(
            "SELECT stage, COUNT(id) as count 
             FROM $table_leads 
             WHERE is_duplicate = 0 AND COALESCE(stage, '') NOT IN ('Won', 'Lost') $rep_clause
             GROUP BY stage"
        );

        $stage_map = array();
        foreach ($leads_by_stage_raw as $row) {
            $stage_name = empty($row->stage) ? 'New' : $row->stage;
            if (isset($stage_map[$stage_name])) {
                $stage_map[$stage_name] += intval($row->count);
            } else {
                $stage_map[$stage_name] = intval($row->count);
            }
        }
        $leads_by_stage = array();
        foreach ($stage_map as $st => $cnt) {
            $leads_by_stage[] = (object) array('stage' => $st, 'count' => $cnt);
        }
        // Order by count DESC
        usort($leads_by_stage, function($a, $b) {
            return $b->count - $a->count;
        });

        // Lost events are measured from immutable stage-transition records, not
        // from a lead's current state. On sites upgraded before event tracking
        // was active, retain a clearly-labelled cohort fallback so the widget
        // is useful without inventing historical transition timestamps.
        $table_state_events = $wpdb->prefix . 'idlm_lead_state_events';
        $won_revenue_field = self::state_events_have_amount($table_state_events) ? 'e.amount' : 'l.deal_value';
        $event_rep_clause = $rep_id ? $wpdb->prepare(' AND l.assigned_rep_id = %d', $rep_id) : '';
        if ($filters['rep_id']) { $event_rep_clause .= $wpdb->prepare(' AND l.assigned_rep_id = %d', $filters['rep_id']); }
        if ('' !== $filters['studio']) { $event_rep_clause .= $wpdb->prepare(' AND l.preferred_studio = %s', $filters['studio']); }
        if ('' !== $filters['source']) { $event_rep_clause .= $wpdb->prepare(' AND l.source = %s', $filters['source']); }
        if ('' !== $filters['campaign']) { $event_rep_clause .= $wpdb->prepare(' AND l.utm_campaign = %s', $filters['campaign']); }
        $reporting_event_rep_clause = $event_rep_clause;
        if ($rep_id && IDLM_Lead_Access_Policy::is_pre_sales()) {
            $reporting_event_rep_clause = $wpdb->prepare(
                ' AND (l.assigned_rep_id = %d OR l.intake_owner_id = %d OR l.qualified_by_user_id = %d)',
                $rep_id,
                $rep_id,
                $rep_id
            );
            if ('' !== $filters['studio']) { $reporting_event_rep_clause .= $wpdb->prepare(' AND l.preferred_studio = %s', $filters['studio']); }
            if ('' !== $filters['source']) { $reporting_event_rep_clause .= $wpdb->prepare(' AND l.source = %s', $filters['source']); }
            if ('' !== $filters['campaign']) { $reporting_event_rep_clause .= $wpdb->prepare(' AND l.utm_campaign = %s', $filters['campaign']); }
        }
        $lost_reasons_basis = 'recorded_transitions';
        if (self::has_reporting_event_history($table_state_events, 'stage_change')) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
            $lost_reasons_raw = $wpdb->get_results($wpdb->prepare(
                "SELECT CASE WHEN e.reason = '' OR e.reason IS NULL THEN 'Uncategorized' ELSE e.reason END AS reason,
                        COUNT(e.id) AS count
                 FROM {$table_state_events} e
                 INNER JOIN {$table_leads} l ON l.id = e.lead_id
                 WHERE e.event_type = 'stage_change' AND e.to_value = 'Lost'
                   AND e.occurred_at BETWEEN %s AND %s AND l.is_duplicate = 0{$reporting_event_rep_clause}
                 GROUP BY reason
                 ORDER BY count DESC, reason ASC",
                $curr_start,
                $curr_end
            ));
        } else {
            $lost_reasons_basis = 'current_lost_cohort';
            // Legacy records have no loss-transition timestamp. Use the selected
            // acquisition cohort and label it accordingly in the response/UI.
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
            $lost_reasons_raw = $wpdb->get_results($wpdb->prepare(
                "SELECT COALESCE(NULLIF(TRIM(l.lost_reason), ''), COALESCE(NULLIF(TRIM(l.disqualification_reason), ''), 'Uncategorized')) AS reason,
                        COUNT(l.id) AS count
                 FROM {$table_leads} l
                 WHERE l.is_duplicate = 0 AND l.stage = 'Lost' AND l.created_at BETWEEN %s AND %s{$reporting_event_rep_clause}
                 GROUP BY reason
                 ORDER BY count DESC, reason ASC",
                $curr_start,
                $curr_end
            ));
        }

        $lost_reasons = array();
        foreach ($lost_reasons_raw as $row) {
            $lost_reasons[] = array(
                'reason' => $row->reason,
                'count' => intval($row->count),
            );
        }

        // A partially-populated event table must not suppress legacy records.
        // Include only current Lost leads with no corresponding Lost event, so
        // recorded transitions remain authoritative and cannot be double-counted.
        if ('current_lost_cohort' === $lost_reasons_basis) { $lost_reasons = array(); }
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
        $lost_legacy_rows = $wpdb->get_results($wpdb->prepare(
            "SELECT COALESCE(NULLIF(TRIM(l.lost_reason), ''), COALESCE(NULLIF(TRIM(l.disqualification_reason), ''), 'Uncategorized')) AS reason, COUNT(l.id) AS count
             FROM {$table_leads} l
             WHERE l.is_duplicate = 0 AND l.stage = 'Lost' AND l.created_at BETWEEN %s AND %s{$reporting_event_rep_clause}
               AND NOT EXISTS (SELECT 1 FROM {$table_state_events} legacy_event WHERE legacy_event.lead_id = l.id AND legacy_event.event_type = 'stage_change' AND legacy_event.to_value = 'Lost')
             GROUP BY reason ORDER BY count DESC, reason ASC",
            $curr_start,
            $curr_end
        ));
        $lost_legacy_count = 0;
        foreach ($lost_legacy_rows as $row) {
            $count = intval($row->count);
            $matched = false;
            foreach ($lost_reasons as &$reason_row) {
                if ($reason_row['reason'] === $row->reason) {
                    $reason_row['count'] += $count;
                    $matched = true;
                    break;
                }
            }
            unset($reason_row);
            if (!$matched) { $lost_reasons[] = array('reason' => $row->reason, 'count' => $count); }
            $lost_legacy_count += $count;
        }
        usort($lost_reasons, function($a, $b) { return $b['count'] <=> $a['count']; });
        if ($lost_legacy_count) { $lost_reasons_basis = 'recorded_transitions_with_legacy_fallback'; }

        // Open pipeline is a current snapshot. Won revenue remains an event
        // metric and is intentionally returned separately below.
        $configured_stages = get_option('idlm_custom_stages', array('New', 'Contacted', 'Qualified', 'Quote Shared', 'Negotiation', 'Won', 'Lost'));
        $stage_total_mappings = class_exists('IDLM_Admin') ? IDLM_Admin::get_kanban_stage_total_mappings($configured_stages) : array();
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
        $revenue_by_channel_raw = $wpdb->get_results(
            "SELECT source, stage, COUNT(id) as lead_count,
                    SUM(COALESCE(quote_value, 0)) as quote_total,
                    SUM(COALESCE(deal_value, 0)) as deal_total,
                    SUM(CASE WHEN quote_value > 0 THEN 1 ELSE 0 END) as quoted_leads,
                    SUM(CASE WHEN deal_value > 0 THEN 1 ELSE 0 END) as valued_deals
             FROM $table_leads
             WHERE is_duplicate = 0 AND COALESCE(stage, '') NOT IN ('Won', 'Lost') $reporting_rep_clause
             GROUP BY source, stage"
        );

        $revenue_channel_map = array();
        $estimated_pipeline_total = 0.0;
        $estimated_pipeline_covered_leads = 0;
        $estimated_pipeline_stages = array();
        $won_revenue_total = 0.0;

        foreach ($revenue_by_channel_raw as $row) {
            $channel = empty($row->source) ? 'Direct / Unknown' : $row->source;
            $stage = empty($row->stage) ? 'New' : $row->stage;
            if (!isset($revenue_channel_map[$channel])) {
                $revenue_channel_map[$channel] = array(
                    'channel' => $channel,
                    'estimated_pipeline' => 0.0,
                    'pipeline_leads' => 0,
                );
            }

            $total_source = isset($stage_total_mappings[$stage]) ? $stage_total_mappings[$stage] : 'none';
            if ('quote_value' === $total_source) {
                $estimated_value = floatval($row->quote_total);
                $estimated_leads = intval($row->quoted_leads);
            } elseif ('deal_value' === $total_source) {
                $estimated_value = floatval($row->deal_total);
                $estimated_leads = intval($row->valued_deals);
            } else {
                $estimated_value = 0.0;
                $estimated_leads = 0;
            }

            if ($estimated_value > 0) {
                $revenue_channel_map[$channel]['estimated_pipeline'] += $estimated_value;
                $revenue_channel_map[$channel]['pipeline_leads'] += $estimated_leads;
                $estimated_pipeline_total += $estimated_value;
                $estimated_pipeline_covered_leads += $estimated_leads;
                $estimated_pipeline_stages[$stage] = true;
            }
        }

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
        $won_revenue_total = floatval($wpdb->get_var($wpdb->prepare(
            "SELECT SUM(COALESCE({$won_revenue_field}, 0)) FROM {$table_state_events} e
             INNER JOIN {$table_leads} l ON l.id = e.lead_id
             WHERE e.event_type = 'stage_change' AND e.to_value = 'Won'
               AND e.occurred_at BETWEEN %s AND %s AND l.is_duplicate = 0{$reporting_event_rep_clause}",
            $curr_start,
            $curr_end
        )));

        $revenue_by_channel = array_values(array_filter($revenue_channel_map, function($row) {
            return $row['estimated_pipeline'] > 0;
        }));
        usort($revenue_by_channel, function($a, $b) {
            return $b['estimated_pipeline'] <=> $a['estimated_pipeline'];
        });

        // Current-state channel snapshot: unlike the Won trend, this intentionally
        // has no reporting-period filter. Closed comprises current Won and Lost leads.
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
        $pipeline_by_channel_raw = $wpdb->get_results(
            "SELECT COALESCE(NULLIF(TRIM(source), ''), 'Direct / Unknown') AS channel,
                    SUM(CASE WHEN COALESCE(stage, '') NOT IN ('Won', 'Lost') THEN 1 ELSE 0 END) AS open_count,
                    SUM(CASE WHEN stage IN ('Won', 'Lost') THEN 1 ELSE 0 END) AS closed_count
             FROM $table_leads
             WHERE is_duplicate = 0 $reporting_rep_clause
             GROUP BY COALESCE(NULLIF(TRIM(source), ''), 'Direct / Unknown')
             HAVING open_count > 0 OR closed_count > 0
             ORDER BY (open_count + closed_count) DESC, channel ASC"
        );
        $pipeline_by_channel = array_map(function($row) {
            return array(
                'channel' => (string) $row->channel,
                'open' => intval($row->open_count),
                'closed' => intval($row->closed_count),
            );
        }, $pipeline_by_channel_raw ?: array());

        $currency_code = get_option('idlm_currency_code', 'INR');
        $currency_symbols = array(
            'INR' => '₹',
            'USD' => '$',
            'EUR' => '€',
            'GBP' => '£',
            'AED' => 'د.إ',
            'CAD' => 'C$',
            'AUD' => 'A$',
            'SGD' => 'S$',
        );
        $currency_symbol = isset($currency_symbols[$currency_code]) ? $currency_symbols[$currency_code] : $currency_code . ' ';

        // 8. Leads by Rep
        // Acquisition measures retain the selected created-date cohort. Won outcomes
        // are close-date measures, matching the primary Won KPI and trend.
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
        $leads_by_rep = $wpdb->get_results($wpdb->prepare(
            "SELECT assigned_rep_id,
                    SUM(CASE WHEN created_at BETWEEN %s AND %s THEN 1 ELSE 0 END) as total_leads,
                    SUM(CASE WHEN created_at BETWEEN %s AND %s AND time_to_connected IS NOT NULL THEN 1 ELSE 0 END) as contacted,
                    SUM(CASE WHEN created_at BETWEEN %s AND %s AND LOWER(lead_quality) = 'qualified' THEN 1 ELSE 0 END) as qualified,
                    SUM(CASE WHEN created_at BETWEEN %s AND %s AND LOWER(lead_quality) = 'unqualified' THEN 1 ELSE 0 END) as unqualified,
                    SUM(CASE WHEN created_at BETWEEN %s AND %s AND (lead_quality = '' OR lead_quality IS NULL) THEN 1 ELSE 0 END) as pending,
                    SUM(CASE WHEN stage = 'Won' AND conversion_time BETWEEN %s AND %s THEN 1 ELSE 0 END) as converted,
                    SUM(CASE WHEN stage = 'Won' AND conversion_time BETWEEN %s AND %s THEN COALESCE(deal_value, 0) ELSE 0 END) as revenue,
                    AVG(CASE WHEN created_at BETWEEN %s AND %s THEN time_to_connected END) as avg_connected,
                    AVG(CASE WHEN stage = 'Won' AND conversion_time BETWEEN %s AND %s THEN time_to_won END) as avg_won
             FROM $table_leads 
             WHERE is_duplicate = 0 AND assigned_rep_id > 0 $rep_clause
               AND (created_at BETWEEN %s AND %s OR (stage = 'Won' AND conversion_time BETWEEN %s AND %s))
             GROUP BY assigned_rep_id
             ORDER BY converted DESC, total_leads DESC",
            $curr_start, $curr_end, $curr_start, $curr_end, $curr_start, $curr_end,
            $curr_start, $curr_end, $curr_start, $curr_end, $curr_start, $curr_end,
            $curr_start, $curr_end, $curr_start, $curr_end, $curr_start, $curr_end,
            $curr_start, $curr_end, $curr_start, $curr_end
        ));

        $rep_metrics = array();
        $rep_cycle_rows = $wpdb->get_results($wpdb->prepare(
            "SELECT assigned_rep_id, time_to_won FROM {$table_leads} WHERE is_duplicate = 0 AND stage = 'Won' AND time_to_won IS NOT NULL AND conversion_time BETWEEN %s AND %s AND assigned_rep_id > 0 {$rep_clause} ORDER BY assigned_rep_id, time_to_won",
            $curr_start,
            $curr_end
        ));
        $rep_cycles = array();
        foreach ($rep_cycle_rows as $cycle_row) { $rep_cycles[intval($cycle_row->assigned_rep_id)][] = intval($cycle_row->time_to_won); }
        foreach ($leads_by_rep as $row) {
            $user = get_userdata($row->assigned_rep_id);
            if ($user && in_array('idlm_pre_sales', (array) $user->roles, true)) {
                continue;
            }

            $name = $user ? $user->display_name : 'Deleted Rep';
            $conv_rate = ($row->total_leads > 0) ? round(($row->converted / $row->total_leads) * 100, 1) : 0;
            $avg_connected = null !== $row->avg_connected ? self::format_seconds($row->avg_connected) : 'N/A';
            $avg_won = null !== $row->avg_won ? self::format_seconds($row->avg_won) : 'N/A';
            $cycles = $rep_cycles[intval($row->assigned_rep_id)] ?? array();
            $cycle_count = count($cycles);
            $median_cycle = $cycle_count ? ($cycle_count % 2 ? $cycles[intdiv($cycle_count, 2)] : ($cycles[$cycle_count / 2 - 1] + $cycles[$cycle_count / 2]) / 2) : null;

            $rep_metrics[] = array(
                'rep_name' => $name,
                'rep_id' => $row->assigned_rep_id,
                'total' => intval($row->total_leads),
                'contacted' => intval($row->contacted),
                'converted' => intval($row->converted),
                'qualified' => intval($row->qualified),
                'unqualified' => intval($row->unqualified),
                'pending' => intval($row->pending),
                'conversion_rate' => $conv_rate,
                'avg_time_connected' => $avg_connected,
                'avg_time_won' => $avg_won
                ,'revenue' => round(floatval($row->revenue), 2)
                ,'median_cycle' => null === $median_cycle ? '—' : self::format_seconds($median_cycle)
            );
        }

        // 8a. Assignment events use their durable event time and target owner.
        // Current ownership is joined only to preserve the dashboard visibility scope.
        $assignment_rep_clause = $event_rep_clause;
        $assignment_distribution_basis = 'recorded_assignments';
        if (self::has_reporting_event_history($table_state_events, 'assignment')) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            $assignment_rows = $wpdb->get_results($wpdb->prepare(
                "SELECT CAST(e.to_value AS UNSIGNED) AS assigned_rep_id,
                        COUNT(e.id) AS total_assigned,
                        SUM(CASE WHEN e.event_origin LIKE 'automatic_%' THEN 0 ELSE 1 END) AS manager_assigned,
                        SUM(CASE WHEN e.event_origin LIKE 'automatic_%' THEN 1 ELSE 0 END) AS ooo_assigned
                 FROM {$table_state_events} e
                 INNER JOIN {$table_leads} l ON l.id = e.lead_id
                 WHERE e.event_type = 'assignment' AND CAST(e.to_value AS UNSIGNED) > 0
                   AND e.occurred_at BETWEEN %s AND %s AND l.is_duplicate = 0{$assignment_rep_clause}
                 GROUP BY CAST(e.to_value AS UNSIGNED)
                 ORDER BY total_assigned DESC, assigned_rep_id ASC",
                $curr_start,
                $curr_end
            ));
        } else {
            $assignment_distribution_basis = 'assigned_at_cohort';
            // The legacy timestamp establishes when a lead was assigned, but not
            // whether a manager or automatic fallback made the assignment.
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
            $assignment_rows = $wpdb->get_results($wpdb->prepare(
                "SELECT l.assigned_rep_id, COUNT(l.id) AS total_assigned, 0 AS manager_assigned, 0 AS ooo_assigned
                 FROM {$table_leads} l
                 WHERE l.is_duplicate = 0 AND l.assigned_rep_id > 0 AND l.assigned_at BETWEEN %s AND %s{$assignment_rep_clause}
                 GROUP BY l.assigned_rep_id
                 ORDER BY total_assigned DESC, assigned_rep_id ASC",
                $curr_start,
                $curr_end
            ));
        }

        $assignment_distribution = array();
        foreach ($assignment_rows as $row) {
            $user = get_userdata($row->assigned_rep_id);
            if ($user && in_array('idlm_pre_sales', (array) $user->roles, true)) {
                continue;
            }
            $assignment_distribution[] = array(
                'rep_id' => absint($row->assigned_rep_id),
                'rep_name' => $user ? $user->display_name : 'Former Sales Rep',
                'total' => intval($row->total_assigned),
                'manager_assigned' => intval($row->manager_assigned),
                'ooo_assigned' => intval($row->ooo_assigned),
            );
        }

        // Legacy `assigned_at` rows fill only the untracked part of the cohort.
        // An event for the same lead/current assignee takes precedence.
        if ('assigned_at_cohort' === $assignment_distribution_basis) { $assignment_distribution = array(); }
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
        $assignment_legacy_rows = $wpdb->get_results($wpdb->prepare(
            "SELECT l.assigned_rep_id, COUNT(l.id) AS total_assigned
             FROM {$table_leads} l
             WHERE l.is_duplicate = 0 AND l.assigned_rep_id > 0 AND l.assigned_at BETWEEN %s AND %s{$assignment_rep_clause}
               AND NOT EXISTS (SELECT 1 FROM {$table_state_events} legacy_event WHERE legacy_event.lead_id = l.id AND legacy_event.event_type = 'assignment' AND CAST(legacy_event.to_value AS UNSIGNED) = l.assigned_rep_id)
             GROUP BY l.assigned_rep_id ORDER BY total_assigned DESC, assigned_rep_id ASC",
            $curr_start,
            $curr_end
        ));
        $assignment_legacy_count = 0;
        foreach ($assignment_legacy_rows as $row) {
            $rep_id = absint($row->assigned_rep_id);
            $count = intval($row->total_assigned);
            $user = get_userdata($rep_id);
            if ($user && in_array('idlm_pre_sales', (array) $user->roles, true)) { continue; }
            $matched = false;
            foreach ($assignment_distribution as &$assignment) {
                if ($assignment['rep_id'] === $rep_id) {
                    $assignment['total'] += $count;
                    $matched = true;
                    break;
                }
            }
            unset($assignment);
            if (!$matched) {
                $assignment_distribution[] = array('rep_id' => $rep_id, 'rep_name' => $user ? $user->display_name : 'Former Sales Rep', 'total' => $count, 'manager_assigned' => 0, 'ooo_assigned' => 0);
            }
            $assignment_legacy_count += $count;
        }
        usort($assignment_distribution, function($a, $b) { return $b['total'] <=> $a['total']; });
        if ($assignment_legacy_count) { $assignment_distribution_basis = 'recorded_assignments_with_legacy_fallback'; }

        // 8b. Pre-sales performance remains attributed to the intake owner after sales handoff.
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $pre_sales_clause = $rep_id ? $wpdb->prepare(' AND l.intake_owner_id = %d', $rep_id) : '';
        $pre_sales_rows = $wpdb->get_results($wpdb->prepare(
            "SELECT l.intake_owner_id,
                    COUNT(l.id) AS total_intake,
                    SUM(CASE WHEN EXISTS (
                        SELECT 1 FROM {$wpdb->prefix}idlm_lead_activity_log a
                        WHERE a.lead_id = l.id AND a.user_id = l.intake_owner_id
                          AND a.activity_type IN ('outbound_call', 'whatsapp_outbound')
                    ) THEN 1 ELSE 0 END) AS contacted,
                    SUM(CASE WHEN l.qualified_at IS NOT NULL THEN 1 ELSE 0 END) AS qualified,
                    SUM(CASE WHEN LOWER(l.lead_quality) = 'unqualified' THEN 1 ELSE 0 END) AS unqualified,
                    SUM(CASE WHEN l.handoff_at IS NOT NULL AND l.handoff_status IN ('auto_assigned', 'manually_assigned') THEN 1 ELSE 0 END) AS handed_off,
                    AVG(CASE WHEN l.qualified_at IS NOT NULL THEN TIMESTAMPDIFF(SECOND, l.created_at, l.qualified_at) END) AS avg_qualification_seconds
             FROM {$table_leads} l
             WHERE l.is_duplicate = 0 AND l.created_at BETWEEN %s AND %s AND l.intake_owner_id > 0 $pre_sales_clause
             GROUP BY l.intake_owner_id
             ORDER BY qualified DESC, total_intake DESC",
            $curr_start,
            $curr_end
        ));

        $pre_sales_metrics = array();
        foreach ($pre_sales_rows as $row) {
            $user = get_userdata($row->intake_owner_id);
            $pre_sales_metrics[] = array(
                'rep_id' => intval($row->intake_owner_id),
                'rep_name' => $user ? $user->display_name : 'Former Pre-Sales User',
                'total' => intval($row->total_intake),
                'contacted' => intval($row->contacted),
                'qualified' => intval($row->qualified),
                'unqualified' => intval($row->unqualified),
                'handed_off' => intval($row->handed_off),
                'qualification_rate' => $row->total_intake > 0 ? round(($row->qualified / $row->total_intake) * 100, 1) : 0,
                'avg_time_to_qualify' => null !== $row->avg_qualification_seconds ? self::format_seconds($row->avg_qualification_seconds) : 'N/A',
            );
        }

        // Direct pre-sales work is attributed to users with the dedicated CRM role.
        // This deliberately excludes system events and sales-owned activity.
        $pre_sales_activity = array(
            'leads_touched' => 0,
            'calls' => 0,
            'whatsapp_outbound' => 0,
            'whatsapp_replies' => 0,
            'status_updates' => 0,
            'tasks' => 0,
            'qualified' => 0,
            'unqualified' => 0,
            'handed_off' => 0,
            'team' => array(),
        );
        $pre_sales_ids = IDLM_Lead_Access_Policy::pre_sales_user_ids();
        if ($pre_sales_ids) {
            $pre_sales_placeholders = implode(', ', array_fill(0, count($pre_sales_ids), '%d'));
            $pre_sales_team = array();
            foreach ($pre_sales_ids as $pre_sales_user_id) {
                $user = get_userdata($pre_sales_user_id);
                $pre_sales_team[$pre_sales_user_id] = array(
                    'user_id' => $pre_sales_user_id,
                    'name' => $user ? $user->display_name : 'Former Pre-Sales User',
                    'leads_touched' => 0,
                    'calls' => 0,
                    'whatsapp_outbound' => 0,
                    'whatsapp_replies' => 0,
                    'status_updates' => 0,
                    'tasks' => 0,
                    'qualified' => 0,
                    'unqualified' => 0,
                    'handed_off' => 0,
                    'avg_qualification_seconds' => null,
                );
            }
            $activity_sql = "SELECT a.user_id, COUNT(DISTINCT a.lead_id) AS leads_touched,
                    SUM(CASE WHEN a.activity_type = 'outbound_call' THEN 1 ELSE 0 END) AS calls,
                    SUM(CASE WHEN a.activity_type = 'whatsapp_outbound' THEN 1 ELSE 0 END) AS whatsapp_outbound,
                    SUM(CASE WHEN a.activity_type = 'whatsapp_reply' THEN 1 ELSE 0 END) AS whatsapp_replies,
                    SUM(CASE WHEN a.activity_type = 'call_status_change' THEN 1 ELSE 0 END) AS status_updates,
                    SUM(CASE WHEN a.activity_type = 'task' THEN 1 ELSE 0 END) AS tasks
                FROM {$wpdb->prefix}idlm_lead_activity_log a
                INNER JOIN {$table_leads} l ON l.id = a.lead_id
                WHERE l.is_duplicate = 0 AND a.created_at BETWEEN %s AND %s
                  AND a.user_id IN ({$pre_sales_placeholders}){$reporting_event_rep_clause}
                GROUP BY a.user_id";
            // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            $activity_rows = $wpdb->get_results($wpdb->prepare($activity_sql, array_merge(array($curr_start, $curr_end), $pre_sales_ids)));
            foreach ($activity_rows as $row) {
                $user_id = absint($row->user_id);
                if (!isset($pre_sales_team[$user_id])) { continue; }
                foreach (array('leads_touched', 'calls', 'whatsapp_outbound', 'whatsapp_replies', 'status_updates', 'tasks') as $key) {
                    $pre_sales_team[$user_id][$key] = intval($row->$key);
                }
            }
            $qualification_sql = "SELECT l.qualified_by_user_id AS user_id, COUNT(l.id) AS qualified,
                    AVG(TIMESTAMPDIFF(SECOND, l.created_at, l.qualified_at)) AS avg_qualification_seconds
                FROM {$table_leads} l
                WHERE l.is_duplicate = 0 AND l.qualified_at BETWEEN %s AND %s
                  AND l.qualified_by_user_id IN ({$pre_sales_placeholders}){$reporting_rep_clause}
                GROUP BY l.qualified_by_user_id";
            // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            $qualification_rows = $wpdb->get_results($wpdb->prepare($qualification_sql, array_merge(array($curr_start, $curr_end), $pre_sales_ids)));
            foreach ($qualification_rows as $row) {
                $user_id = absint($row->user_id);
                if (isset($pre_sales_team[$user_id])) {
                    $pre_sales_team[$user_id]['qualified'] = intval($row->qualified);
                    $pre_sales_team[$user_id]['avg_qualification_seconds'] = null === $row->avg_qualification_seconds ? null : intval($row->avg_qualification_seconds);
                }
            }
            $unqualified_sql = "SELECT l.disqualified_by_user_id AS user_id, COUNT(l.id) AS unqualified
                FROM {$table_leads} l
                WHERE l.is_duplicate = 0 AND l.disqualified_at BETWEEN %s AND %s
                  AND l.disqualified_by_user_id IN ({$pre_sales_placeholders}){$reporting_rep_clause}
                GROUP BY l.disqualified_by_user_id";
            // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            $unqualified_rows = $wpdb->get_results($wpdb->prepare($unqualified_sql, array_merge(array($curr_start, $curr_end), $pre_sales_ids)));
            foreach ($unqualified_rows as $row) {
                $user_id = absint($row->user_id);
                if (isset($pre_sales_team[$user_id])) { $pre_sales_team[$user_id]['unqualified'] = intval($row->unqualified); }
            }
            $handoff_sql = "SELECT l.intake_owner_id AS user_id, COUNT(l.id) AS handed_off
                FROM {$table_leads} l
                WHERE l.is_duplicate = 0 AND l.handoff_at BETWEEN %s AND %s
                  AND l.handoff_status IN ('auto_assigned', 'manually_assigned')
                  AND l.intake_owner_id IN ({$pre_sales_placeholders}){$reporting_rep_clause}
                GROUP BY l.intake_owner_id";
            // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            $handoff_rows = $wpdb->get_results($wpdb->prepare($handoff_sql, array_merge(array($curr_start, $curr_end), $pre_sales_ids)));
            foreach ($handoff_rows as $row) {
                $user_id = absint($row->user_id);
                if (isset($pre_sales_team[$user_id])) { $pre_sales_team[$user_id]['handed_off'] = intval($row->handed_off); }
            }
            foreach ($pre_sales_team as $member) {
                foreach (array('leads_touched', 'calls', 'whatsapp_outbound', 'whatsapp_replies', 'status_updates', 'tasks', 'qualified', 'unqualified', 'handed_off') as $key) {
                    $pre_sales_activity[$key] += $member[$key];
                }
                $member['avg_time_to_qualify'] = null === $member['avg_qualification_seconds'] ? 'N/A' : self::format_seconds($member['avg_qualification_seconds']);
                unset($member['avg_qualification_seconds']);
                $pre_sales_activity['team'][] = $member;
            }
            usort($pre_sales_activity['team'], function($a, $b) {
                return ($b['calls'] + $b['whatsapp_outbound'] + $b['tasks']) <=> ($a['calls'] + $a['whatsapp_outbound'] + $a['tasks']);
            });
        }

        // Older records did not always retain the acting pre-sales user. The
        // individual workspace therefore reports recorded activity and outcomes
        // on the user's attributed lead portfolio. The manager team widget
        // above intentionally remains actor-attributed.
        $pre_sales_portfolio_activity = array();
        if ($rep_id && IDLM_Lead_Access_Policy::is_pre_sales()) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            $pre_sales_portfolio_activity = (array) $wpdb->get_row($wpdb->prepare(
                "SELECT
                    COUNT(DISTINCT CASE WHEN a.id IS NOT NULL THEN l.id END) AS leads_touched,
                    SUM(CASE WHEN a.activity_type = 'outbound_call' THEN 1 ELSE 0 END) AS calls,
                    SUM(CASE WHEN a.activity_type = 'whatsapp_outbound' THEN 1 ELSE 0 END) AS whatsapp_outbound,
                    SUM(CASE WHEN a.activity_type = 'whatsapp_reply' THEN 1 ELSE 0 END) AS whatsapp_replies,
                    SUM(CASE WHEN a.activity_type = 'call_status_change' THEN 1 ELSE 0 END) AS status_updates,
                    SUM(CASE WHEN a.activity_type = 'task' THEN 1 ELSE 0 END) AS tasks,
                    COUNT(DISTINCT CASE WHEN l.qualified_at BETWEEN %s AND %s THEN l.id END) AS qualified,
                    COUNT(DISTINCT CASE WHEN l.disqualified_at BETWEEN %s AND %s THEN l.id END) AS unqualified,
                    COUNT(DISTINCT CASE WHEN l.handoff_at BETWEEN %s AND %s AND l.handoff_status IN ('auto_assigned', 'manually_assigned') THEN l.id END) AS handed_off
                 FROM {$table_leads} l
                 LEFT JOIN {$wpdb->prefix}idlm_lead_activity_log a
                    ON a.lead_id = l.id AND a.created_at BETWEEN %s AND %s
                 WHERE l.is_duplicate = 0{$reporting_event_rep_clause}",
                $curr_start, $curr_end,
                $curr_start, $curr_end,
                $curr_start, $curr_end,
                $curr_start, $curr_end
            ));
        }

        // 9. Averages Speeds to conversion (Speed to Lead)
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
        $avg_speeds = $wpdb->get_row($wpdb->prepare(
            "SELECT AVG(time_to_connected) as avg_connected, 
                    AVG(time_to_won) as avg_won 
             FROM $table_leads 
             WHERE is_duplicate = 0 AND created_at BETWEEN %s AND %s $rep_clause",
            $curr_start, $curr_end
        ));

        // Convert seconds to human readable hours/days
        $avg_time_connected = $avg_speeds->avg_connected ? self::format_seconds($avg_speeds->avg_connected) : 'N/A';
        $avg_time_won = $avg_speeds->avg_won ? self::format_seconds($avg_speeds->avg_won) : 'N/A';
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
        $avg_time_connected_previous_seconds = floatval($wpdb->get_var($wpdb->prepare(
            "SELECT AVG(time_to_connected) FROM $table_leads WHERE is_duplicate = 0 AND created_at BETWEEN %s AND %s $reporting_rep_clause",
            $prev_start, $prev_end
        )));

        // 10. Call Status Distribution
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
        $call_status_dist = $wpdb->get_results($wpdb->prepare(
            "SELECT call_status, COUNT(id) as count 
             FROM $table_leads 
             WHERE is_duplicate = 0 AND created_at BETWEEN %s AND %s $reporting_rep_clause
             GROUP BY call_status",
            $curr_start, $curr_end
        ));

        // 10.2. Lead Quality Distribution
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
        $quality_dist_raw = $wpdb->get_results($wpdb->prepare(
            "SELECT lead_quality, floor_plan_received, COUNT(id) as count 
             FROM $table_leads 
             WHERE is_duplicate = 0 AND created_at BETWEEN %s AND %s $reporting_rep_clause
             GROUP BY lead_quality, floor_plan_received",
            $curr_start, $curr_end
        ));

        $quality_dist = array(
            'Qualified · Plan Received' => 0,
            'Qualified · Plan Pending' => 0,
            'Unqualified' => 0,
            'Pending Qualification' => 0
        );
        foreach ($quality_dist_raw as $row) {
            $raw_val = isset($row->lead_quality) ? trim($row->lead_quality) : '';
            if ('qualified' === strtolower($raw_val)) {
                $label = !empty($row->floor_plan_received) ? 'Qualified · Plan Received' : 'Qualified · Plan Pending';
            } elseif ('unqualified' === strtolower($raw_val)) {
                $label = 'Unqualified';
            } else {
                $label = 'Pending Qualification';
            }
            $quality_dist[$label] += intval($row->count);
        }
        $qualified_with_plan = $quality_dist['Qualified · Plan Received'];
        $qualified_total = $qualified_with_plan + $quality_dist['Qualified · Plan Pending'];
        $plan_collection_rate = $qualified_total > 0 ? round(($qualified_with_plan / $qualified_total) * 100, 1) : 0;
        // Compare the same acquisition-cohort quality fields against the
        // equivalent previous window. These are intentionally separate from
        // the current pipeline snapshots below.
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
        $cohort_comparison = $wpdb->get_row($wpdb->prepare(
            "SELECT
                SUM(CASE WHEN created_at BETWEEN %s AND %s AND LOWER(lead_quality) = 'qualified' THEN 1 ELSE 0 END) AS qualified_previous,
                SUM(CASE WHEN created_at BETWEEN %s AND %s AND LOWER(lead_quality) = 'qualified' AND COALESCE(floor_plan_received, 0) != 0 THEN 1 ELSE 0 END) AS qualified_with_plan_previous,
                SUM(CASE WHEN created_at BETWEEN %s AND %s AND (lead_quality = '' OR lead_quality IS NULL) THEN 1 ELSE 0 END) AS pending_qualification_current,
                SUM(CASE WHEN created_at BETWEEN %s AND %s AND (lead_quality = '' OR lead_quality IS NULL) THEN 1 ELSE 0 END) AS pending_qualification_previous,
                SUM(CASE WHEN created_at BETWEEN %s AND %s AND stage = 'Won' THEN 1 ELSE 0 END) AS cohort_won_previous
             FROM $table_leads
             WHERE is_duplicate = 0 AND (created_at BETWEEN %s AND %s OR created_at BETWEEN %s AND %s)$reporting_rep_clause",
            $prev_start, $prev_end,
            $prev_start, $prev_end,
            $curr_start, $curr_end,
            $prev_start, $prev_end,
            $prev_start, $prev_end,
            $curr_start, $curr_end,
            $prev_start, $prev_end
        ));
        $qualified_total_previous = intval($cohort_comparison->qualified_previous ?? 0);
        $qualified_with_plan_previous = intval($cohort_comparison->qualified_with_plan_previous ?? 0);
        $pending_qualification_total = intval($cohort_comparison->pending_qualification_current ?? 0);
        $pending_qualification_total_previous = intval($cohort_comparison->pending_qualification_previous ?? 0);
        $cohort_won_previous = intval($cohort_comparison->cohort_won_previous ?? 0);

        // 10.5. Leads Over Time: acquisition volume/quality and Won closes.
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
        $leads_over_time_raw = $wpdb->get_results($wpdb->prepare(
            "SELECT DATE(created_at) as lead_date, COUNT(id) as count,
                    SUM(LOWER(lead_quality) = 'qualified') AS qualified
             FROM $table_leads 
             WHERE is_duplicate = 0 AND created_at BETWEEN %s AND %s $reporting_rep_clause
             GROUP BY DATE(created_at) 
             ORDER BY lead_date ASC",
            $curr_start, $curr_end
        ));

        $start_date = new DateTime(substr($curr_start, 0, 10));
        $end_date = new DateTime(substr($curr_end, 0, 10));
        $interval = new DateInterval('P1D');
        $date_range = new DatePeriod($start_date, $interval, $end_date->modify('+1 day'));

        $timeline_counts = array();
        foreach ($date_range as $date) {
            $timeline_counts[$date->format('Y-m-d')] = array('count' => 0, 'qualified' => 0, 'won' => 0);
        }

        foreach ($leads_over_time_raw as $row) {
            $timeline_counts[$row->lead_date]['count'] = intval($row->count);
            $timeline_counts[$row->lead_date]['qualified'] = intval($row->qualified);
        }

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
        $won_over_time_raw = $wpdb->get_results($wpdb->prepare(
            "SELECT DATE(conversion_time) AS close_date, COUNT(id) AS won
             FROM {$table_leads}
             WHERE is_duplicate = 0 AND stage = 'Won' AND conversion_time BETWEEN %s AND %s {$reporting_rep_clause}
             GROUP BY DATE(conversion_time) ORDER BY close_date ASC",
            $curr_start,
            $curr_end
        ));
        foreach ($won_over_time_raw as $row) {
            if (isset($timeline_counts[$row->close_date])) {
                $timeline_counts[$row->close_date]['won'] = intval($row->won);
            }
        }

        $leads_over_time = array();
        foreach ($timeline_counts as $date => $day_counts) {
            $leads_over_time[] = array(
                'date' => wp_date('M d', strtotime($date)),
                'count' => $day_counts['count'],
                'qualified' => $day_counts['qualified'],
                'won' => $day_counts['won']
            );
        }

        // Won-event trend powers the Sales dashboard's revenue / deal / average selector.
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
        $won_trend_raw = $wpdb->get_results($wpdb->prepare(
            "SELECT DATE(e.occurred_at) AS event_date, COUNT(e.id) AS won, SUM(COALESCE({$won_revenue_field}, 0)) AS revenue
             FROM {$table_state_events} e INNER JOIN {$table_leads} l ON l.id = e.lead_id
             WHERE e.event_type = 'stage_change' AND e.to_value = 'Won' AND e.occurred_at BETWEEN %s AND %s AND l.is_duplicate = 0{$reporting_event_rep_clause}
             GROUP BY DATE(e.occurred_at) ORDER BY event_date ASC",
            $curr_start,
            $curr_end
        ));
        $won_trend_index = array();
        foreach ($won_trend_raw as $row) {
            $won_trend_index[$row->event_date] = array('won' => intval($row->won), 'revenue' => round(floatval($row->revenue), 2));
        }
        // Existing installations store canonical conversion_time even when
        // immutable reporting began later. Use it only for Won leads with no
        // Won event, preserving event records as the authoritative source.
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
        $won_legacy_rows = $wpdb->get_results($wpdb->prepare(
            "SELECT DATE(l.conversion_time) AS event_date, COUNT(l.id) AS won, SUM(COALESCE(l.deal_value, 0)) AS revenue
             FROM {$table_leads} l
             WHERE l.is_duplicate = 0 AND l.stage = 'Won' AND l.conversion_time BETWEEN %s AND %s{$reporting_event_rep_clause}
               AND NOT EXISTS (SELECT 1 FROM {$table_state_events} legacy_event WHERE legacy_event.lead_id = l.id AND legacy_event.event_type = 'stage_change' AND legacy_event.to_value = 'Won')
             GROUP BY DATE(l.conversion_time) ORDER BY event_date ASC",
            $curr_start,
            $curr_end
        ));
        $won_legacy_count = 0;
        $won_legacy_revenue = 0.0;
        foreach ($won_legacy_rows as $row) {
            $date = $row->event_date;
            $won = intval($row->won);
            $revenue = round(floatval($row->revenue), 2);
            $point = $won_trend_index[$date] ?? array('won' => 0, 'revenue' => 0);
            $won_trend_index[$date] = array('won' => $point['won'] + $won, 'revenue' => $point['revenue'] + $revenue);
            $won_legacy_count += $won;
            $won_legacy_revenue += $revenue;
        }
        $won_over_time = array();
        $won_timeline_date = new DateTime(substr($curr_start, 0, 10), wp_timezone());
        $won_timeline_end = new DateTime(substr($curr_end, 0, 10), wp_timezone());
        while ($won_timeline_date <= $won_timeline_end) {
            $date = $won_timeline_date->format('Y-m-d');
            $point = $won_trend_index[$date] ?? array('won' => 0, 'revenue' => 0);
            $won_over_time[] = array('date' => wp_date('M d', strtotime($date)), 'won' => $point['won'], 'revenue' => $point['revenue']);
            $won_timeline_date->modify('+1 day');
        }

        // Reference-body marketing panels use these cohort dimensions directly.
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
        $disqualification_reasons = $wpdb->get_results($wpdb->prepare(
            "SELECT COALESCE(NULLIF(TRIM(disqualification_reason), ''), 'Not specified') AS reason, COUNT(id) AS count FROM {$table_leads} WHERE is_duplicate = 0 AND LOWER(lead_quality) = 'unqualified' AND created_at BETWEEN %s AND %s {$reporting_rep_clause} GROUP BY reason ORDER BY count DESC LIMIT 5",
            $curr_start,
            $curr_end
        ), ARRAY_A);
        // Keep the source and campaign selector views constrained to the same
        // selected cohort as the overall reason list.
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
        $disqualification_reasons_by_source = $wpdb->get_results($wpdb->prepare(
            "SELECT CONCAT(COALESCE(NULLIF(TRIM(source), ''), 'Unknown source'), ' — ', COALESCE(NULLIF(TRIM(disqualification_reason), ''), 'Not specified')) AS reason, COUNT(id) AS count FROM {$table_leads} WHERE is_duplicate = 0 AND LOWER(lead_quality) = 'unqualified' AND created_at BETWEEN %s AND %s {$rep_clause} GROUP BY source, disqualification_reason ORDER BY count DESC, reason ASC LIMIT 5",
            $curr_start,
            $curr_end
        ), ARRAY_A);
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
        $disqualification_reasons_by_campaign = $wpdb->get_results($wpdb->prepare(
            "SELECT CONCAT(COALESCE(NULLIF(TRIM(utm_campaign), ''), 'No campaign'), ' — ', COALESCE(NULLIF(TRIM(disqualification_reason), ''), 'Not specified')) AS reason, COUNT(id) AS count FROM {$table_leads} WHERE is_duplicate = 0 AND LOWER(lead_quality) = 'unqualified' AND created_at BETWEEN %s AND %s {$rep_clause} GROUP BY utm_campaign, disqualification_reason ORDER BY count DESC, reason ASC LIMIT 5",
            $curr_start,
            $curr_end
        ), ARRAY_A);
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
        $landing_pages = $wpdb->get_results($wpdb->prepare(
            "SELECT landing_page, COUNT(id) AS count, SUM(LOWER(lead_quality) = 'qualified') AS qualified, SUM(stage = 'Won') AS won FROM {$table_leads} WHERE is_duplicate = 0 AND landing_page <> '' AND created_at BETWEEN %s AND %s {$rep_clause} GROUP BY landing_page ORDER BY count DESC LIMIT 10",
            $curr_start,
            $curr_end
        ), ARRAY_A);
        $business_mix = array();
        foreach (array('rooms' => 'num_rooms', 'property' => 'property_type', 'location' => 'property_location', 'studio' => 'preferred_studio', 'timeline' => 'project_timeline') as $mix_key => $mix_field) {
            // Field names come from the fixed whitelist above; all values remain bound.
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
            $business_mix[$mix_key] = $wpdb->get_results($wpdb->prepare(
                "SELECT COALESCE(NULLIF(TRIM({$mix_field}), ''), 'Not specified') AS segment,
                        SUM(CASE WHEN created_at BETWEEN %s AND %s THEN 1 ELSE 0 END) AS count,
                        SUM(CASE WHEN created_at BETWEEN %s AND %s AND COALESCE(stage, '') NOT IN ('New', 'Lost') THEN 1 ELSE 0 END) AS opportunities,
                        SUM(CASE WHEN stage = 'Won' AND conversion_time BETWEEN %s AND %s THEN 1 ELSE 0 END) AS won,
                        SUM(CASE WHEN stage = 'Won' AND conversion_time BETWEEN %s AND %s THEN COALESCE(deal_value, 0) ELSE 0 END) AS revenue
                 FROM {$table_leads}
                 WHERE is_duplicate = 0 {$reporting_rep_clause}
                   AND (created_at BETWEEN %s AND %s OR (stage = 'Won' AND conversion_time BETWEEN %s AND %s))
                 GROUP BY segment ORDER BY won DESC, count DESC LIMIT 10",
                $curr_start, $curr_end, $curr_start, $curr_end, $curr_start, $curr_end,
                $curr_start, $curr_end, $curr_start, $curr_end, $curr_start, $curr_end
            ), ARRAY_A);
        }

        // Leads by Studio (Distribution)
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
        $leads_by_studio_raw = $wpdb->get_results($wpdb->prepare(
            "SELECT preferred_studio, COUNT(id) as count 
             FROM $table_leads 
             WHERE is_duplicate = 0 AND created_at BETWEEN %s AND %s $rep_clause
             GROUP BY preferred_studio 
             ORDER BY count DESC",
            $curr_start, $curr_end
        ));

        $leads_by_studio = array();
        foreach ($leads_by_studio_raw as $row) {
            $studio_name = empty($row->preferred_studio) ? 'Unassigned' : $row->preferred_studio;
            $leads_by_studio[] = array(
                'studio' => $studio_name,
                'count' => intval($row->count)
            );
        }

        // Studio conversion performance for the selected acquisition cohort.
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
        $studio_performance_raw = $wpdb->get_results($wpdb->prepare(
            "SELECT NULLIF(TRIM(preferred_studio), '') AS preferred_studio, 
                    COUNT(id) as total_leads,
                    SUM(CASE WHEN stage = 'Won' THEN 1 ELSE 0 END) as converted
             FROM $table_leads 
             WHERE is_duplicate = 0 AND created_at BETWEEN %s AND %s $rep_clause
             GROUP BY NULLIF(TRIM(preferred_studio), '')",
            $curr_start, $curr_end
        ));

        $studio_performance = array();
        foreach ($studio_performance_raw as $row) {
            $studio_name = empty($row->preferred_studio) ? 'Unassigned' : $row->preferred_studio;
            $conv_rate = ($row->total_leads > 0) ? round(($row->converted / $row->total_leads) * 100, 1) : 0;
            $studio_performance[] = array(
                'studio' => $studio_name,
                'total' => intval($row->total_leads),
                'converted' => intval($row->converted),
                'conversion_rate' => $conv_rate
            );
        }
        usort($studio_performance, function($a, $b) {
            return $b['total'] - $a['total'];
        });

        // Leads by Property Type
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
        $leads_by_property_raw = $wpdb->get_results($wpdb->prepare(
            "SELECT property_type, COUNT(id) as count 
             FROM $table_leads 
             WHERE is_duplicate = 0 AND created_at BETWEEN %s AND %s $rep_clause
             GROUP BY property_type 
             ORDER BY count DESC",
            $curr_start, $curr_end
        ));

        $leads_by_property = array();
        foreach ($leads_by_property_raw as $row) {
            $prop_name = empty($row->property_type) ? 'Not Specified' : $row->property_type;
            $leads_by_property[] = array(
                'property_type' => $prop_name,
                'count' => intval($row->count)
            );
        }

        // Leads by Room Configuration (BHK)
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
        $leads_by_rooms_raw = $wpdb->get_results($wpdb->prepare(
            "SELECT num_rooms, COUNT(id) as count 
             FROM $table_leads 
             WHERE is_duplicate = 0 AND created_at BETWEEN %s AND %s $rep_clause
             GROUP BY num_rooms 
             ORDER BY count DESC",
            $curr_start, $curr_end
        ));

        $leads_by_rooms = array();
        foreach ($leads_by_rooms_raw as $row) {
            $rooms_name = empty($row->num_rooms) ? 'Not Specified' : $row->num_rooms;
            $leads_by_rooms[] = array(
                'num_rooms' => $rooms_name,
                'count' => intval($row->count)
            );
        }

        // 11. Current open-pipeline distribution. Historical progression cannot
        // be inferred reliably from a lead's current stage alone.
        $funnel_stages = array_values(array_filter($configured_stages, static function($stage) {
            return !in_array($stage, array('Won', 'Lost'), true);
        }));
        $funnel_counts_active = array_fill_keys($funnel_stages, 0);

        // Fetch the current snapshot, independently of selected acquisition dates.
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
        $funnel_raw = $wpdb->get_results(
            "SELECT stage, COUNT(id) as count 
             FROM $table_leads 
             WHERE is_duplicate = 0 AND COALESCE(stage, '') NOT IN ('Won', 'Lost') $rep_clause
             GROUP BY stage"
        );

        foreach ($funnel_raw as $row) {
            $stage_key = empty($row->stage) ? 'New' : $row->stage;
            if (!isset($funnel_counts_active[$stage_key])) {
                $funnel_counts_active[$stage_key] = 0;
                $funnel_stages[] = $stage_key;
            }
            $funnel_counts_active[$stage_key] += intval($row->count);
        }

        // Won cards are event based. The amount snapshot is recorded with the
        // transition, so later edits to a lead cannot rewrite past revenue.
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
        $total_won = intval($wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(e.id) FROM {$table_state_events} e
             INNER JOIN {$table_leads} l ON l.id = e.lead_id
             WHERE e.event_type = 'stage_change' AND e.to_value = 'Won'
               AND e.occurred_at BETWEEN %s AND %s AND l.is_duplicate = 0{$reporting_event_rep_clause}",
            $curr_start,
            $curr_end
        )));
        $total_won += $won_legacy_count;
        $won_revenue_total += $won_legacy_revenue;
        // The prior Won baseline uses the same event-first, legacy-fallback
        // reporting basis as the current period.
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
        $total_won_previous = intval($wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(e.id) FROM {$table_state_events} e
             INNER JOIN {$table_leads} l ON l.id = e.lead_id
             WHERE e.event_type = 'stage_change' AND e.to_value = 'Won'
               AND e.occurred_at BETWEEN %s AND %s AND l.is_duplicate = 0{$reporting_event_rep_clause}",
            $prev_start,
            $prev_end
        )));
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
        $won_revenue_previous = floatval($wpdb->get_var($wpdb->prepare(
            "SELECT SUM(COALESCE({$won_revenue_field}, 0)) FROM {$table_state_events} e
             INNER JOIN {$table_leads} l ON l.id = e.lead_id
             WHERE e.event_type = 'stage_change' AND e.to_value = 'Won'
               AND e.occurred_at BETWEEN %s AND %s AND l.is_duplicate = 0{$reporting_event_rep_clause}",
            $prev_start,
            $prev_end
        )));
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
        $won_legacy_previous = $wpdb->get_row($wpdb->prepare(
            "SELECT COUNT(l.id) AS count, SUM(COALESCE(l.deal_value, 0)) AS revenue FROM {$table_leads} l
             WHERE l.is_duplicate = 0 AND l.stage = 'Won' AND l.conversion_time BETWEEN %s AND %s{$reporting_event_rep_clause}
               AND NOT EXISTS (SELECT 1 FROM {$table_state_events} legacy_event WHERE legacy_event.lead_id = l.id AND legacy_event.event_type = 'stage_change' AND legacy_event.to_value = 'Won')",
            $prev_start,
            $prev_end
        ));
        $total_won_previous += intval($won_legacy_previous->count ?? 0);
        $won_revenue_previous += floatval($won_legacy_previous->revenue ?? 0);
        $won_reporting_basis = $won_legacy_count ? 'recorded_transitions_with_conversion_time_fallback' : 'recorded_transitions';
        // Use elapsed calendar time from lead creation to the actual close
        // timestamp. Event occurrence is authoritative; conversion_time fills
        // only legacy Won records that have no corresponding event.
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
        $won_event_close_seconds = floatval($wpdb->get_var($wpdb->prepare(
            "SELECT SUM(GREATEST(0, TIMESTAMPDIFF(SECOND, l.created_at, e.occurred_at))) FROM {$table_state_events} e
             INNER JOIN {$table_leads} l ON l.id = e.lead_id
             WHERE e.event_type = 'stage_change' AND e.to_value = 'Won' AND e.occurred_at BETWEEN %s AND %s AND l.is_duplicate = 0{$reporting_event_rep_clause}",
            $curr_start,
            $curr_end
        )));
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
        $won_legacy_close_seconds = floatval($wpdb->get_var($wpdb->prepare(
            "SELECT SUM(GREATEST(0, TIMESTAMPDIFF(SECOND, l.created_at, l.conversion_time))) FROM {$table_leads} l
             WHERE l.is_duplicate = 0 AND l.stage = 'Won' AND l.conversion_time BETWEEN %s AND %s{$reporting_event_rep_clause}
               AND NOT EXISTS (SELECT 1 FROM {$table_state_events} legacy_event WHERE legacy_event.lead_id = l.id AND legacy_event.event_type = 'stage_change' AND legacy_event.to_value = 'Won')",
            $curr_start,
            $curr_end
        )));
        $avg_time_to_close = $total_won ? self::format_seconds(($won_event_close_seconds + $won_legacy_close_seconds) / $total_won) : '—';
        // This is deliberately a current result for the acquisition cohort,
        // not a selected-period Won-event count divided by new leads.
        // Keep the event count above separate for the sales-event cards.
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
        $cohort_won_current = intval($wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(id) FROM $table_leads
             WHERE is_duplicate = 0 AND stage = 'Won' AND created_at BETWEEN %s AND %s $rep_clause",
            $curr_start,
            $curr_end
        )));
        $overall_conversion_rate = ($total_curr > 0) ? round(($cohort_won_current / $total_curr) * 100, 1) : 0;
        $funnel_data = array();
        $visible_pipeline_total = array_sum($funnel_counts_active);

        // Actionability is a current snapshot too. Keep it separate from the
        // selected-period acquisition and conversion metrics above.
        $table_tasks = $wpdb->prefix . 'idlm_tasks';
        $now = current_time('mysql');
        $snapshot_time = new DateTime($now, wp_timezone());
        $age_30_days_ago = (clone $snapshot_time)->modify('-30 days')->format('Y-m-d H:i:s');
        $age_60_days_ago = (clone $snapshot_time)->modify('-60 days')->format('Y-m-d H:i:s');
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
        $open_pipeline_health = $wpdb->get_row($wpdb->prepare(
            "SELECT
                SUM(CASE WHEN l.created_at < %s AND l.created_at >= %s THEN 1 ELSE 0 END) AS aged_31_to_60,
                SUM(CASE WHEN l.created_at < %s THEN 1 ELSE 0 END) AS aged_61_plus,
                SUM(CASE WHEN l.assigned_rep_id = 0 THEN 1 ELSE 0 END) AS unassigned,
                SUM(CASE WHEN l.handoff_status = 'awaiting_manager' THEN 1 ELSE 0 END) AS awaiting_manager,
                SUM(CASE WHEN NOT EXISTS (
                    SELECT 1 FROM {$table_tasks} t WHERE t.lead_id = l.id AND t.status = 'pending'
                ) THEN 1 ELSE 0 END) AS without_next_task,
                SUM(CASE WHEN EXISTS (
                    SELECT 1 FROM {$table_tasks} t
                    WHERE t.lead_id = l.id AND t.status = 'pending' AND t.due_date IS NOT NULL AND t.due_date < %s
                ) THEN 1 ELSE 0 END) AS overdue_task
             FROM {$table_leads} l
             WHERE l.is_duplicate = 0 AND COALESCE(l.stage, '') NOT IN ('Won', 'Lost') {$rep_clause}",
            $age_30_days_ago,
            $age_60_days_ago,
            $age_60_days_ago,
            $now
        ));

        foreach ($funnel_stages as $stage) {
            $active_count = $funnel_counts_active[$stage];
            $funnel_data[] = array(
                'stage' => $stage,
                'active_count' => $active_count,
                'cumulative_count' => $active_count,
                'rate_from_start' => $visible_pipeline_total > 0 ? round(($active_count / $visible_pipeline_total) * 100, 1) : 0,
                'step_rate' => null
            );
        }

        // Monthly targets are only shown for the default current-month view.
        $now_month = new DateTime('now', wp_timezone());
        $month_start = $now_month->format('Y-m-01 00:00:00');
        $month_end = $now_month->format('Y-m-d H:i:s');
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
        $monthly_leads = intval($wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(id) FROM $table_leads WHERE is_duplicate = 0 AND created_at BETWEEN %s AND %s $rep_clause",
            $month_start,
            $month_end
        )));
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
        $monthly_conversions = intval($wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(e.id) FROM {$table_state_events} e
             INNER JOIN {$table_leads} l ON l.id = e.lead_id
             WHERE e.event_type = 'stage_change' AND e.to_value = 'Won'
               AND e.occurred_at BETWEEN %s AND %s AND l.is_duplicate = 0{$event_rep_clause}",
            $month_start,
            $month_end
        )));
        $days_elapsed = intval($now_month->format('j'));
        $days_in_month = intval($now_month->format('t'));
        $lead_target = intval(get_option('idlm_monthly_lead_target', 100));
        $conversion_target = intval(get_option('idlm_monthly_conversion_target', 10));

        // Operational coverage is a current visible-scope snapshot, rather than
        // an acquisition cohort. This makes missing data explicit beside the
        // descriptive analytics that depend on it.
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $data_quality = $wpdb->get_row("SELECT
                COUNT(id) AS total_leads,
                SUM(CASE WHEN COALESCE(intake_owner_id, 0) > 0 THEN 1 ELSE 0 END) AS owned_leads,
                SUM(CASE WHEN utm_source <> '' AND utm_medium <> '' THEN 1 ELSE 0 END) AS attributed_leads,
                SUM(CASE WHEN time_to_connected IS NOT NULL THEN 1 ELSE 0 END) AS response_timed_leads,
                MAX(created_at) AS latest_created_at,
                MAX(updated_at) AS latest_updated_at
            FROM $table_leads
            WHERE is_duplicate = 0{$rep_clause}");
        $data_quality_total = intval($data_quality->total_leads ?? 0);

        return self::filter_stats(array(
            'ranges' => $ranges,
            'marketing' => array(
                'total_leads_period' => $total_curr,
                'total_leads_previous' => $total_prev,
                'growth_pct' => null === $percentage_change ? null : round($percentage_change, 1),
                'qualified_total' => $qualified_total,
                'qualified_total_previous' => $qualified_total_previous,
                'cohort_won_current' => $cohort_won_current,
                'cohort_won_previous' => $cohort_won_previous,
                'total_duplicates_period' => $dup_curr,
                'total_duplicates_previous' => $dup_prev,
                'duplicates_growth_pct' => null === $dup_percentage_change ? null : round($dup_percentage_change, 1),
                'leads_by_channel' => $leads_by_channel,
                'channel_rates' => array(),
                'top_campaigns' => isset($utm_data['utm_campaign']) ? $utm_data['utm_campaign'] : array(),
                'campaign_adgroups' => $campaign_adgroups,
                'utm_data' => $utm_data,
                'leads_over_time' => $leads_over_time,
                'disqualification_reasons' => $disqualification_reasons,
                'disqualification_reasons_by_source' => $disqualification_reasons_by_source,
                'disqualification_reasons_by_campaign' => $disqualification_reasons_by_campaign,
                'landing_pages' => $landing_pages,
                'business_mix' => $business_mix,
                'click_id_count' => intval($wpdb->get_var($wpdb->prepare(
                    "SELECT COUNT(id) FROM {$table_leads} WHERE is_duplicate = 0 AND created_at BETWEEN %s AND %s {$rep_clause} AND (gclid <> '' OR fbclid <> '' OR li_fat_id <> '' OR gbraid <> '' OR wbraid <> '')",
                    $curr_start,
                    $curr_end
                ))),
                'currency_symbol' => $currency_symbol,
                'leads_by_property' => $leads_by_property,
                'leads_by_rooms' => $leads_by_rooms
            ),
            'sales' => array(
                'leads_by_stage' => $leads_by_stage,
                'lost_reasons' => $lost_reasons,
                'lost_reasons_basis' => $lost_reasons_basis,
                'lost_reasons_legacy_count' => $lost_legacy_count,
                'funnel_data' => $funnel_data,
                'leads_by_rep' => $rep_metrics,
                'assignment_distribution' => $assignment_distribution,
                'assignment_distribution_basis' => $assignment_distribution_basis,
                'assignment_distribution_legacy_count' => $assignment_legacy_count,
                'reporting_events_started_at' => get_option('idlm_reporting_events_started_at', ''),
                'pre_sales_performance' => $pre_sales_metrics,
                'pre_sales_activity' => $pre_sales_activity,
                'avg_time_connected' => $avg_time_connected,
                'avg_time_connected_seconds' => floatval($avg_speeds->avg_connected ?? 0),
                'avg_time_connected_previous_seconds' => $avg_time_connected_previous_seconds,
                'avg_time_won' => $avg_time_won,
                'avg_time_to_close' => $avg_time_to_close,
                'call_status_distribution' => $call_status_dist,
                'lead_quality_distribution' => $quality_dist,
                'plan_collection_rate' => $plan_collection_rate,
                'qualified_with_plan' => $qualified_with_plan,
                'qualified_with_plan_previous' => $qualified_with_plan_previous,
                'qualified_total' => $qualified_total,
                'qualified_total_previous' => $qualified_total_previous,
                'pending_qualification_total' => $pending_qualification_total,
                'pending_qualification_total_previous' => $pending_qualification_total_previous,
                'total_won' => $total_won,
                'total_won_previous' => $total_won_previous,
                'cohort_won_current' => $cohort_won_current,
                'overall_conversion_rate' => $overall_conversion_rate,
                'leads_by_studio' => $leads_by_studio,
                'studio_performance' => $studio_performance,
                'revenue_by_channel' => $revenue_by_channel,
                'pipeline_by_channel' => $pipeline_by_channel,
                'estimated_pipeline_total' => round($estimated_pipeline_total, 2),
                'estimated_pipeline_covered_leads' => $estimated_pipeline_covered_leads,
                'pre_sales_portfolio_activity' => $pre_sales_portfolio_activity,
                'estimated_pipeline_stages' => array_keys($estimated_pipeline_stages),
                'open_pipeline_total' => $visible_pipeline_total,
                'open_pipeline_as_of' => $now,
                'open_pipeline_health' => array(
                    'aged_31_to_60' => intval($open_pipeline_health->aged_31_to_60 ?? 0),
                    'aged_61_plus' => intval($open_pipeline_health->aged_61_plus ?? 0),
                    'unassigned' => intval($open_pipeline_health->unassigned ?? 0),
                    'awaiting_manager' => intval($open_pipeline_health->awaiting_manager ?? 0),
                    'without_next_task' => intval($open_pipeline_health->without_next_task ?? 0),
                    'overdue_task' => intval($open_pipeline_health->overdue_task ?? 0),
                ),
                'won_revenue_total' => round($won_revenue_total, 2),
                'won_revenue_previous' => round($won_revenue_previous, 2),
                'won_over_time' => $won_over_time,
                'won_reporting_basis' => $won_reporting_basis,
                'won_legacy_count' => $won_legacy_count,
                'business_mix' => $business_mix,
                'currency_code' => $currency_code,
                'currency_symbol' => $currency_symbol
            ),
            'targets' => array(
                'lead_target' => $lead_target,
                'conversion_target' => $conversion_target,
                'current_leads' => $monthly_leads,
                'current_conversions' => $monthly_conversions,
                'month_label' => $now_month->format('F Y'),
                'lead_expected' => round(($lead_target * $days_elapsed) / $days_in_month, 1),
                'conversion_expected' => round(($conversion_target * $days_elapsed) / $days_in_month, 1),
                'is_applicable' => 'this_month' === $period
            ),
            'data_quality' => array(
                'total_leads' => $data_quality_total,
                'owned_leads' => intval($data_quality->owned_leads ?? 0),
                'attributed_leads' => intval($data_quality->attributed_leads ?? 0),
                'response_timed_leads' => intval($data_quality->response_timed_leads ?? 0),
                'latest_created_at' => $data_quality->latest_created_at ?? '',
                'latest_updated_at' => $data_quality->latest_updated_at ?? '',
            )
        ));
    }

    /**
     * Support installations whose state-event migration marker was recorded
     * before dbDelta added the immutable event amount column. Those sites can
     * still render the dashboard from the linked lead value until upgraded.
     */
    private static function state_events_have_amount($table_state_events) {
        if (null !== self::$state_event_amount_supported) {
            return self::$state_event_amount_supported;
        }

        global $wpdb;
        // The table name is derived solely from the WordPress table prefix.
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        self::$state_event_amount_supported = (bool) $wpdb->get_var("SHOW COLUMNS FROM {$table_state_events} LIKE 'amount'");

        return self::$state_event_amount_supported;
    }

    /**
     * Whether an installation has started recording a given immutable event.
     * Empty selected periods must remain empty; only entirely legacy histories
     * may use the explicitly-labelled current-state fallback above.
     */
    private static function has_reporting_event_history($table_state_events, $event_type) {
        $event_type = sanitize_key($event_type);
        if (isset(self::$reporting_event_history[$event_type])) {
            return self::$reporting_event_history[$event_type];
        }

        global $wpdb;
        // The table name is built exclusively from the WordPress table prefix.
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        self::$reporting_event_history[$event_type] = (bool) $wpdb->get_var($wpdb->prepare(
            "SELECT 1 FROM {$table_state_events} WHERE event_type = %s LIMIT 1",
            $event_type
        ));

        return self::$reporting_event_history[$event_type];
    }

    /**
     * Compute Start & End timestamps for filters
     */
    public static function get_date_ranges($period, $custom_start = '', $custom_end = '') {
        $timezone = wp_timezone();
        $now = new DateTime('now', $timezone);
        
        $curr_start = '';
        $curr_end = $now->format('Y-m-d H:i:s');
        $prev_start = '';
        $prev_end = '';

        switch ($period) {
            case 'today':
                $curr_start = $now->format('Y-m-d 00:00:00');
                
                $yesterday = clone $now;
                $yesterday->modify('-1 day');
                $prev_start = $yesterday->format('Y-m-d 00:00:00');
                $prev_end = $yesterday->format('Y-m-d 23:59:59');
                break;

            case 'yesterday':
                $yesterday = clone $now;
                $yesterday->modify('-1 day');
                $curr_start = $yesterday->format('Y-m-d 00:00:00');
                $curr_end = $yesterday->format('Y-m-d 23:59:59');

                $day_before = clone $yesterday;
                $day_before->modify('-1 day');
                $prev_start = $day_before->format('Y-m-d 00:00:00');
                $prev_end = $day_before->format('Y-m-d 23:59:59');
                break;

            case 'this_week':
                $start_of_week = clone $now;
                $start_of_week->modify('monday this week');
                $curr_start = $start_of_week->format('Y-m-d 00:00:00');

                $days_diff = intval($now->diff($start_of_week)->format('%a')) + 1;
                $prev_start_dt = clone $start_of_week;
                $prev_start_dt->modify("-{$days_diff} days");
                $prev_start = $prev_start_dt->format('Y-m-d 00:00:00');
                
                $prev_end_dt = clone $start_of_week;
                $prev_end_dt->modify('-1 second');
                $prev_end = $prev_end_dt->format('Y-m-d H:i:s');
                break;

            case 'this_month':
                $curr_start = $now->format('Y-m-01 00:00:00');

                $prev_month_dt = clone $now;
                $prev_month_dt->modify('first day of last month');
                $prev_start = $prev_month_dt->format('Y-m-d 00:00:00');
                
                $prev_month_end_dt = clone $prev_month_dt;
                $comparison_day = min(intval($now->format('j')), intval($prev_month_end_dt->format('t')));
                $prev_month_end_dt->setDate(
                    intval($prev_month_end_dt->format('Y')),
                    intval($prev_month_end_dt->format('m')),
                    $comparison_day
                );
                $prev_end = $prev_month_end_dt->format('Y-m-d') . ' ' . $now->format('H:i:s');
                break;

            case 'last_month':
                $last_month_start = clone $now;
                $last_month_start->modify('first day of last month');
                $curr_start = $last_month_start->format('Y-m-d 00:00:00');
                
                $last_month_end = clone $now;
                $last_month_end->modify('last day of last month');
                $curr_end = $last_month_end->format('Y-m-d 23:59:59');

                $prev_prev_month = clone $last_month_start;
                $prev_prev_month->modify('-1 month');
                $prev_start = $prev_prev_month->format('Y-m-d 00:00:00');
                
                $prev_prev_end = clone $last_month_start;
                $prev_prev_end->modify('-1 second');
                $prev_end = $prev_prev_end->format('Y-m-d H:i:s');
                break;

            case 'last_7_days':
                $start = clone $now;
                $start->modify('-6 days');
                $curr_start = $start->format('Y-m-d 00:00:00');

                $prev_start_dt = clone $start;
                $prev_start_dt->modify('-7 days');
                $prev_start = $prev_start_dt->format('Y-m-d 00:00:00');

                $prev_end_dt = clone $start;
                $prev_end_dt->modify('-1 second');
                $prev_end = $prev_end_dt->format('Y-m-d H:i:s');
                break;

            case 'last_30_days':
                $start = clone $now;
                $start->modify('-29 days');
                $curr_start = $start->format('Y-m-d 00:00:00');

                $prev_start_dt = clone $start;
                $prev_start_dt->modify('-30 days');
                $prev_start = $prev_start_dt->format('Y-m-d 00:00:00');

                $prev_end_dt = clone $start;
                $prev_end_dt->modify('-1 second');
                $prev_end = $prev_end_dt->format('Y-m-d H:i:s');
                break;

            case 'year':
            case 'this_year':
                $curr_start = $now->format('Y-01-01 00:00:00');

                $prev_start = (intval($now->format('Y')) - 1) . '-01-01 00:00:00';
                $prev_end_dt = clone $now;
                $prev_end_dt->modify('-1 year');
                $prev_end = $prev_end_dt->format('Y-m-d H:i:s');
                break;

            case 'custom':
                if (!empty($custom_start) && !empty($custom_end)) {
                    $timezone = wp_timezone();
                    $start_dt = new DateTime($custom_start, $timezone);
                    $end_dt = new DateTime($custom_end, $timezone);
                    $curr_start = $start_dt->format('Y-m-d 00:00:00');
                    $curr_end = $end_dt->format('Y-m-d 23:59:59');

                    // Calculate same number of days preceding
                    $curr_start_dt = new DateTime($curr_start, $timezone);
                    $curr_end_dt = new DateTime($curr_end, $timezone);
                    $diff_days = intval($curr_end_dt->diff($curr_start_dt)->format('%a')) + 1;

                    $prev_start_dt = clone $curr_start_dt;
                    $prev_start_dt->modify("-{$diff_days} days");
                    $prev_start = $prev_start_dt->format('Y-m-d 00:00:00');

                    $prev_end_dt = clone $curr_start_dt;
                    $prev_end_dt->modify('-1 second');
                    $prev_end = $prev_end_dt->format('Y-m-d H:i:s');
                } else {
                    // Fallback to this month
                    $curr_start = $now->format('Y-m-01 00:00:00');
                }
                break;
        }

        return array(
            'curr_start' => $curr_start,
            'curr_end' => $curr_end,
            'prev_start' => $prev_start,
            'prev_end' => $prev_end
        );
    }

    /**
     * Format time difference in seconds to human readable hours/days
     */
    private static function format_seconds($seconds) {
        $seconds = intval($seconds);
        if ($seconds < 3600) {
            return round($seconds / 60) . ' mins';
        } elseif ($seconds < 86400) {
            return round($seconds / 3600, 1) . ' hrs';
        } else {
            return round($seconds / 86400, 1) . ' days';
        }
    }
}
