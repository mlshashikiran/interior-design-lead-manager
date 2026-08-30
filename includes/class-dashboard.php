<?php
if (!defined('ABSPATH')) {
    exit;
}

class IDLM_Dashboard {
    /**
     * Get dashboard stats JSON data based on period
     */
    public static function get_stats($period = 'this_month', $custom_start = '', $custom_end = '') {
        return IDLM_Dashboard_Read_Model::get_stats($period, $custom_start, $custom_end);
    }

    /**
     * Build dashboard statistics from the canonical database reads.
     *
     * Callers should use get_stats() so identical scoped reads can be reused.
     */
    public static function calculate_stats($period = 'this_month', $custom_start = '', $custom_end = '') {
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

        // 2. Lead Volumes & Growth
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
        $total_curr = intval($wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(id) FROM $table_leads WHERE is_duplicate = 0 AND created_at BETWEEN %s AND %s $rep_clause",
            $curr_start, $curr_end
        )));

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
        $total_prev = intval($wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(id) FROM $table_leads WHERE is_duplicate = 0 AND created_at BETWEEN %s AND %s $rep_clause",
            $prev_start, $prev_end
        )));

        $percentage_change = null;
        if ($total_prev > 0) {
            $percentage_change = (($total_curr - $total_prev) / $total_prev) * 100;
        }

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

        // 3. Leads by Channel (Current Period) with Lead Quality Breakdown
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
        $channel_leads_raw = $wpdb->get_results($wpdb->prepare(
            "SELECT source as channel, 
                    COUNT(id) as count,
                    SUM(CASE WHEN LOWER(lead_quality) = 'qualified' THEN 1 ELSE 0 END) as qualified,
                    SUM(CASE WHEN LOWER(lead_quality) = 'unqualified' THEN 1 ELSE 0 END) as unqualified,
                    SUM(CASE WHEN lead_quality = '' OR lead_quality IS NULL THEN 1 ELSE 0 END) as pending
             FROM $table_leads 
             WHERE is_duplicate = 0 AND created_at BETWEEN %s AND %s $rep_clause
             GROUP BY source 
             ORDER BY count DESC",
            $curr_start, $curr_end
        ));

        $leads_by_channel = array();
        if ($channel_leads_raw) {
            foreach ($channel_leads_raw as $row) {
                $leads_by_channel[] = (object) array(
                    'channel' => empty($row->channel) ? 'Direct / Unknown' : $row->channel,
                    'count' => intval($row->count),
                    'qualified' => intval($row->qualified),
                    'unqualified' => intval($row->unqualified),
                    'pending' => intval($row->pending)
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
                        'count' => intval($row->count),
                        'qualified' => intval($row->qualified),
                        'unqualified' => intval($row->unqualified),
                        'pending' => intval($row->pending)
                    );
                }
            }
            $utm_data[$field] = $formatted_results;
        }

        // --- SALES PIPELINE METRICS ---

        // 7. Leads by Stage (grouping empty stages as 'New')
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
        $leads_by_stage_raw = $wpdb->get_results($wpdb->prepare(
            "SELECT stage, COUNT(id) as count 
             FROM $table_leads 
             WHERE is_duplicate = 0 AND created_at BETWEEN %s AND %s $rep_clause
             GROUP BY stage",
            $curr_start, $curr_end
        ));

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

        // Categorized reasons for leads currently in the Lost stage.
        // Keep uncategorized legacy records visible so totals remain complete.
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
        $lost_reasons_raw = $wpdb->get_results($wpdb->prepare(
            "SELECT CASE WHEN lost_reason = '' OR lost_reason IS NULL THEN 'Uncategorized' ELSE lost_reason END AS reason,
                    COUNT(id) AS count,
                    SUM(COALESCE(quote_value, 0)) AS quote_value
             FROM $table_leads
             WHERE is_duplicate = 0 AND stage = 'Lost' AND created_at BETWEEN %s AND %s $rep_clause
             GROUP BY reason
             ORDER BY count DESC, reason ASC",
            $curr_start,
            $curr_end
        ));

        $lost_reasons = array();
        foreach ($lost_reasons_raw as $row) {
            $lost_reasons[] = array(
                'reason' => $row->reason,
                'count' => intval($row->count),
                'quote_value' => round(floatval($row->quote_value), 2),
            );
        }

        // Open pipeline remains an acquisition-cohort metric. Won revenue is an
        // event metric and therefore belongs to the period in which it was won.
        $configured_stages = get_option('idlm_custom_stages', array('New', 'Contacted', 'Qualified', 'Quote Shared', 'Negotiation', 'Won', 'Lost'));
        $stage_total_mappings = class_exists('IDLM_Admin') ? IDLM_Admin::get_kanban_stage_total_mappings($configured_stages) : array();
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
        $revenue_by_channel_raw = $wpdb->get_results($wpdb->prepare(
            "SELECT source, stage, COUNT(id) as lead_count,
                    SUM(COALESCE(quote_value, 0)) as quote_total,
                    SUM(COALESCE(deal_value, 0)) as deal_total,
                    SUM(CASE WHEN quote_value > 0 THEN 1 ELSE 0 END) as quoted_leads,
                    SUM(CASE WHEN deal_value > 0 THEN 1 ELSE 0 END) as valued_deals
             FROM $table_leads
             WHERE is_duplicate = 0 AND created_at BETWEEN %s AND %s $rep_clause AND stage NOT IN ('Won', 'Lost')
             GROUP BY source, stage",
            $curr_start,
            $curr_end
        ));

        $revenue_channel_map = array();
        $estimated_pipeline_total = 0.0;
        $won_revenue_total = 0.0;

        foreach ($revenue_by_channel_raw as $row) {
            $channel = empty($row->source) ? 'Direct / Unknown' : $row->source;
            $stage = empty($row->stage) ? 'New' : $row->stage;
            if (!isset($revenue_channel_map[$channel])) {
                $revenue_channel_map[$channel] = array(
                    'channel' => $channel,
                    'estimated_pipeline' => 0.0,
                    'won_revenue' => 0.0,
                    'pipeline_leads' => 0,
                    'won_leads' => 0,
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
            }
        }

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
        $won_revenue_rows = $wpdb->get_results($wpdb->prepare(
            "SELECT source, COUNT(id) AS won_count, SUM(COALESCE(deal_value, 0)) AS won_revenue
             FROM $table_leads
             WHERE is_duplicate = 0 AND stage = 'Won' AND conversion_time BETWEEN %s AND %s $rep_clause
             GROUP BY source",
            $curr_start,
            $curr_end
        ));
        foreach ($won_revenue_rows as $row) {
            $channel = empty($row->source) ? 'Direct / Unknown' : $row->source;
            if (!isset($revenue_channel_map[$channel])) {
                $revenue_channel_map[$channel] = array(
                    'channel' => $channel,
                    'estimated_pipeline' => 0.0,
                    'won_revenue' => 0.0,
                    'pipeline_leads' => 0,
                    'won_leads' => 0,
                );
            }
            $won_value = floatval($row->won_revenue);
            $revenue_channel_map[$channel]['won_revenue'] = $won_value;
            $revenue_channel_map[$channel]['won_leads'] = intval($row->won_count);
            $won_revenue_total += $won_value;
        }

        $revenue_by_channel = array_values(array_filter($revenue_channel_map, function($row) {
            return $row['estimated_pipeline'] > 0 || $row['won_revenue'] > 0;
        }));
        usort($revenue_by_channel, function($a, $b) {
            $a_total = $a['estimated_pipeline'] + $a['won_revenue'];
            $b_total = $b['estimated_pipeline'] + $b['won_revenue'];
            return $b_total <=> $a_total;
        });

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
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
        $leads_by_rep = $wpdb->get_results($wpdb->prepare(
            "SELECT assigned_rep_id,
                    COUNT(id) as total_leads,
                    SUM(CASE WHEN time_to_connected IS NOT NULL THEN 1 ELSE 0 END) as contacted,
                    SUM(CASE WHEN stage = 'Won' THEN 1 ELSE 0 END) as converted,
                    SUM(CASE WHEN LOWER(lead_quality) = 'qualified' THEN 1 ELSE 0 END) as qualified,
                    SUM(CASE WHEN LOWER(lead_quality) = 'unqualified' THEN 1 ELSE 0 END) as unqualified,
                    SUM(CASE WHEN lead_quality = '' OR lead_quality IS NULL THEN 1 ELSE 0 END) as pending,
                    AVG(time_to_connected) as avg_connected,
                    AVG(CASE WHEN stage = 'Won' THEN time_to_won END) as avg_won
             FROM $table_leads 
             WHERE is_duplicate = 0 AND created_at BETWEEN %s AND %s AND assigned_rep_id > 0 $rep_clause
             GROUP BY assigned_rep_id
             ORDER BY converted DESC, total_leads DESC",
            $curr_start, $curr_end
        ));

        $rep_metrics = array();
        foreach ($leads_by_rep as $row) {
            $user = get_userdata($row->assigned_rep_id);
            if ($user && in_array('idlm_pre_sales', (array) $user->roles, true)) {
                continue;
            }

            $name = $user ? $user->display_name : 'Deleted Rep';
            $conv_rate = ($row->total_leads > 0) ? round(($row->converted / $row->total_leads) * 100, 1) : 0;
            $avg_connected = null !== $row->avg_connected ? self::format_seconds($row->avg_connected) : 'N/A';
            $avg_won = null !== $row->avg_won ? self::format_seconds($row->avg_won) : 'N/A';

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
            );
        }

        // 8a. Current qualified sales ownership assigned during the selected period.
        $assignment_rep_clause = $rep_id ? $wpdb->prepare(' AND l.assigned_rep_id = %d', $rep_id) : '';
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $assignment_rows = $wpdb->get_results($wpdb->prepare(
            "SELECT l.assigned_rep_id,
                    COUNT(l.id) AS total_assigned,
                    SUM(CASE WHEN COALESCE(l.handoff_status, '') <> 'auto_assigned' THEN 1 ELSE 0 END) AS manager_assigned,
                    SUM(l.handoff_status = 'auto_assigned') AS ooo_assigned
             FROM {$table_leads} l
             WHERE l.is_duplicate = 0
               AND COALESCE(l.handoff_at, l.assigned_at) BETWEEN %s AND %s
               AND l.lead_quality = 'Qualified'
               AND l.assigned_rep_id > 0{$assignment_rep_clause}
             GROUP BY l.assigned_rep_id
             ORDER BY total_assigned DESC, l.assigned_rep_id ASC",
            $curr_start,
            $curr_end
        ));

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

        // 10. Call Status Distribution
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
        $call_status_dist = $wpdb->get_results($wpdb->prepare(
            "SELECT call_status, COUNT(id) as count 
             FROM $table_leads 
             WHERE is_duplicate = 0 AND created_at BETWEEN %s AND %s $rep_clause
             GROUP BY call_status",
            $curr_start, $curr_end
        ));

        // 10.2. Lead Quality Distribution
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
        $quality_dist_raw = $wpdb->get_results($wpdb->prepare(
            "SELECT lead_quality, floor_plan_received, COUNT(id) as count 
             FROM $table_leads 
             WHERE is_duplicate = 0 AND created_at BETWEEN %s AND %s $rep_clause
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

        // 10.5. Leads Over Time (Daily volume)
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
        $leads_over_time_raw = $wpdb->get_results($wpdb->prepare(
            "SELECT DATE(created_at) as lead_date, COUNT(id) as count 
             FROM $table_leads 
             WHERE is_duplicate = 0 AND created_at BETWEEN %s AND %s $rep_clause
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
            $timeline_counts[$date->format('Y-m-d')] = 0;
        }

        foreach ($leads_over_time_raw as $row) {
            $timeline_counts[$row->lead_date] = intval($row->count);
        }

        $leads_over_time = array();
        foreach ($timeline_counts as $date => $count) {
            $leads_over_time[] = array(
                'date' => wp_date('M d', strtotime($date)),
                'count' => $count
            );
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

        // 11. Current-stage distribution. Historical stage progression cannot be
        // inferred reliably from a lead's current stage alone.
        $funnel_stages = array_values(array_filter($configured_stages, static function($stage) {
            return 'Lost' !== $stage;
        }));
        $funnel_counts_active = array_fill_keys($funnel_stages, 0);

        // Fetch counts for current period
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
        $funnel_raw = $wpdb->get_results($wpdb->prepare(
            "SELECT stage, COUNT(id) as count 
             FROM $table_leads 
             WHERE is_duplicate = 0 AND created_at BETWEEN %s AND %s $rep_clause
             GROUP BY stage",
            $curr_start, $curr_end
        ));

        foreach ($funnel_raw as $row) {
            $stage_key = empty($row->stage) ? 'New' : $row->stage;
            if (isset($funnel_counts_active[$stage_key])) {
                $funnel_counts_active[$stage_key] += intval($row->count);
            }
        }

        // Won cards are event based, using the actual conversion timestamp.
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
        $total_won = intval($wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(id) FROM $table_leads WHERE is_duplicate = 0 AND stage = 'Won' AND conversion_time BETWEEN %s AND %s $rep_clause",
            $curr_start,
            $curr_end
        )));
        $cohort_won_count = isset($stage_map['Won']) ? intval($stage_map['Won']) : 0;
        $overall_conversion_rate = ($total_curr > 0) ? round(($cohort_won_count / $total_curr) * 100, 1) : 0;
        $funnel_data = array();
        $visible_pipeline_total = array_sum($funnel_counts_active);

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

        // Monthly targets are calendar-month metrics and must not change with
        // the dashboard's selected reporting period.
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
            "SELECT COUNT(id) FROM $table_leads WHERE is_duplicate = 0 AND stage = 'Won' AND conversion_time BETWEEN %s AND %s $rep_clause",
            $month_start,
            $month_end
        )));

        return array(
            'marketing' => array(
                'total_leads_period' => $total_curr,
                'total_leads_previous' => $total_prev,
                'growth_pct' => null === $percentage_change ? null : round($percentage_change, 1),
                'total_duplicates_period' => $dup_curr,
                'total_duplicates_previous' => $dup_prev,
                'duplicates_growth_pct' => null === $dup_percentage_change ? null : round($dup_percentage_change, 1),
                'leads_by_channel' => $leads_by_channel,
                'channel_rates' => array(),
                'top_campaigns' => isset($utm_data['utm_campaign']) ? $utm_data['utm_campaign'] : array(),
                'utm_data' => $utm_data,
                'leads_over_time' => $leads_over_time,
                'leads_by_property' => $leads_by_property,
                'leads_by_rooms' => $leads_by_rooms
            ),
            'sales' => array(
                'leads_by_stage' => $leads_by_stage,
                'lost_reasons' => $lost_reasons,
                'funnel_data' => $funnel_data,
                'leads_by_rep' => $rep_metrics,
                'assignment_distribution' => $assignment_distribution,
                'pre_sales_performance' => $pre_sales_metrics,
                'avg_time_connected' => $avg_time_connected,
                'avg_time_won' => $avg_time_won,
                'call_status_distribution' => $call_status_dist,
                'lead_quality_distribution' => $quality_dist,
                'plan_collection_rate' => $plan_collection_rate,
                'qualified_with_plan' => $qualified_with_plan,
                'qualified_total' => $qualified_total,
                'total_won' => $total_won,
                'overall_conversion_rate' => $overall_conversion_rate,
                'leads_by_studio' => $leads_by_studio,
                'studio_performance' => $studio_performance,
                'revenue_by_channel' => $revenue_by_channel,
                'estimated_pipeline_total' => round($estimated_pipeline_total, 2),
                'won_revenue_total' => round($won_revenue_total, 2),
                'currency_code' => $currency_code,
                'currency_symbol' => $currency_symbol
            ),
            'targets' => array(
                'lead_target' => intval(get_option('idlm_monthly_lead_target', 100)),
                'conversion_target' => intval(get_option('idlm_monthly_conversion_target', 10)),
                'current_leads' => $monthly_leads,
                'current_conversions' => $monthly_conversions,
                'month_label' => $now_month->format('F Y')
            )
        );
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
