<?php
if (!defined('ABSPATH')) {
    exit;
}

class IDLM_Dashboard {
    /**
     * Get dashboard stats JSON data based on period
     */
    public static function get_stats($period = 'this_month', $custom_start = '', $custom_end = '') {
        global $wpdb;
        $table_leads = $wpdb->prefix . 'idlm_leads';

        // 1. Calculate Date Ranges (Current vs Previous)
        $ranges = self::get_date_ranges($period, $custom_start, $custom_end);
        $curr_start = $ranges['curr_start'];
        $curr_end = $ranges['curr_end'];
        $prev_start = $ranges['prev_start'];
        $prev_end = $ranges['prev_end'];

        // Rep visibility permissions check
        $rep_id = 0;
        if (!current_user_can('manage_options')) {
            // Check if reps are allowed to see all leads
            $reps_see_all = get_option('idlm_reps_see_all_leads', '1');
            if (!$reps_see_all) {
                $rep_id = get_current_user_id();
            }
        }

        $rep_clause = $rep_id ? $wpdb->prepare(" AND assigned_rep_id = %d", $rep_id) : "";

        // 2. Lead Volumes & Growth
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
        $total_curr = intval($wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(id) FROM $table_leads WHERE created_at BETWEEN %s AND %s $rep_clause",
            $curr_start, $curr_end
        )));

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
        $total_prev = intval($wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(id) FROM $table_leads WHERE created_at BETWEEN %s AND %s $rep_clause",
            $prev_start, $prev_end
        )));

        $percentage_change = 0;
        if ($total_prev > 0) {
            $percentage_change = (($total_curr - $total_prev) / $total_prev) * 100;
        }

        // 3. Leads by Channel (Current Period) with Lead Quality Breakdown
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
        $channel_leads_raw = $wpdb->get_results($wpdb->prepare(
            "SELECT source as channel, 
                    COUNT(id) as count,
                    SUM(CASE WHEN LOWER(lead_quality) = 'qualified' THEN 1 ELSE 0 END) as qualified,
                    SUM(CASE WHEN LOWER(lead_quality) = 'unqualified' THEN 1 ELSE 0 END) as unqualified,
                    SUM(CASE WHEN lead_quality = '' OR lead_quality IS NULL THEN 1 ELSE 0 END) as pending
             FROM $table_leads 
             WHERE created_at BETWEEN %s AND %s $rep_clause
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


        // 5. Cost Per Lead (Requires matching lead source count with wp_idlm_cost_per_lead table for active months)
        $cpl_data = self::calculate_cost_per_lead($curr_start, $curr_end, $rep_clause);

        // 6. Top UTM Campaigns and other UTM parameters (implemented by Shashi)
        $utm_fields = array('utm_campaign', 'utm_source', 'utm_medium', 'utm_adgroup', 'utm_term', 'utm_content');
        $utm_data = array();

        foreach ($utm_fields as $field) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
            $results = $wpdb->get_results($wpdb->prepare(
                "SELECT $field as utm_value, 
                        COUNT(id) as count,
                        SUM(CASE WHEN LOWER(lead_quality) = 'qualified' THEN 1 ELSE 0 END) as qualified,
                        SUM(CASE WHEN LOWER(lead_quality) = 'unqualified' THEN 1 ELSE 0 END) as unqualified,
                        SUM(CASE WHEN lead_quality = '' OR lead_quality IS NULL THEN 1 ELSE 0 END) as pending
                 FROM $table_leads 
                 WHERE created_at BETWEEN %s AND %s AND $field != '' $rep_clause
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
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
        $leads_by_stage_raw = $wpdb->get_results($wpdb->prepare(
            "SELECT stage, COUNT(id) as count 
             FROM $table_leads 
             WHERE created_at BETWEEN %s AND %s $rep_clause
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

        // 8. Leads by Rep
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
        $leads_by_rep = $wpdb->get_results($wpdb->prepare(
            "SELECT assigned_rep_id,
                    COUNT(id) as total_leads,
                    SUM(CASE WHEN call_status != 'Not Called' THEN 1 ELSE 0 END) as contacted,
                    SUM(CASE WHEN stage = 'Won' THEN 1 ELSE 0 END) as converted,
                    SUM(CASE WHEN LOWER(lead_quality) = 'qualified' THEN 1 ELSE 0 END) as qualified,
                    SUM(CASE WHEN LOWER(lead_quality) = 'unqualified' THEN 1 ELSE 0 END) as unqualified,
                    SUM(CASE WHEN lead_quality = '' OR lead_quality IS NULL THEN 1 ELSE 0 END) as pending,
                    AVG(time_to_connected) as avg_connected,
                    AVG(time_to_won) as avg_won
             FROM $table_leads 
             WHERE created_at BETWEEN %s AND %s AND assigned_rep_id > 0
             GROUP BY assigned_rep_id",
            $curr_start, $curr_end
        ));

        $rep_metrics = array();
        foreach ($leads_by_rep as $row) {
            $user = get_userdata($row->assigned_rep_id);
            $name = $user ? $user->display_name : 'Deleted Rep';
            $conv_rate = ($row->total_leads > 0) ? round(($row->converted / $row->total_leads) * 100, 1) : 0;
            $avg_connected = $row->avg_connected ? self::format_seconds($row->avg_connected) : 'N/A';
            $avg_won = $row->avg_won ? self::format_seconds($row->avg_won) : 'N/A';

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

        // 9. Averages Speeds to conversion (Speed to Lead)
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
        $avg_speeds = $wpdb->get_row($wpdb->prepare(
            "SELECT AVG(time_to_connected) as avg_connected, 
                    AVG(time_to_won) as avg_won 
             FROM $table_leads 
             WHERE created_at BETWEEN %s AND %s $rep_clause",
            $curr_start, $curr_end
        ));

        // Convert seconds to human readable hours/days
        $avg_time_connected = $avg_speeds->avg_connected ? self::format_seconds($avg_speeds->avg_connected) : 'N/A';
        $avg_time_won = $avg_speeds->avg_won ? self::format_seconds($avg_speeds->avg_won) : 'N/A';

        // 10. Call Status Distribution
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
        $call_status_dist = $wpdb->get_results($wpdb->prepare(
            "SELECT call_status, COUNT(id) as count 
             FROM $table_leads 
             WHERE created_at BETWEEN %s AND %s $rep_clause
             GROUP BY call_status",
            $curr_start, $curr_end
        ));

        // 10.2. Lead Quality Distribution
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
        $quality_dist_raw = $wpdb->get_results($wpdb->prepare(
            "SELECT lead_quality, COUNT(id) as count 
             FROM $table_leads 
             WHERE created_at BETWEEN %s AND %s $rep_clause
             GROUP BY lead_quality",
            $curr_start, $curr_end
        ));

        $quality_dist = array(
            'Qualified' => 0,
            'Unqualified' => 0,
            'Pending' => 0
        );
        foreach ($quality_dist_raw as $row) {
            $raw_val = isset($row->lead_quality) ? trim($row->lead_quality) : '';
            if (empty($raw_val)) {
                $label = 'Pending';
            } else {
                $label = ucfirst(strtolower($raw_val));
            }
            if (isset($quality_dist[$label])) {
                $quality_dist[$label] += intval($row->count);
            } else {
                $quality_dist['Pending'] += intval($row->count);
            }
        }

        // 10.5. Leads Over Time (Daily volume)
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
        $leads_over_time_raw = $wpdb->get_results($wpdb->prepare(
            "SELECT DATE(created_at) as lead_date, COUNT(id) as count 
             FROM $table_leads 
             WHERE created_at BETWEEN %s AND %s $rep_clause
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
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
        $leads_by_studio_raw = $wpdb->get_results($wpdb->prepare(
            "SELECT preferred_studio, COUNT(id) as count 
             FROM $table_leads 
             WHERE created_at BETWEEN %s AND %s $rep_clause
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

        // Studio Conversion Performance
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
        $studio_performance_raw = $wpdb->get_results($wpdb->prepare(
            "SELECT preferred_studio, 
                    COUNT(id) as total_leads,
                    SUM(CASE WHEN stage = 'Won' THEN 1 ELSE 0 END) as converted
             FROM $table_leads 
             WHERE created_at BETWEEN %s AND %s $rep_clause
             GROUP BY preferred_studio",
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
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
        $leads_by_property_raw = $wpdb->get_results($wpdb->prepare(
            "SELECT property_type, COUNT(id) as count 
             FROM $table_leads 
             WHERE created_at BETWEEN %s AND %s $rep_clause
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
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
        $leads_by_rooms_raw = $wpdb->get_results($wpdb->prepare(
            "SELECT num_rooms, COUNT(id) as count 
             FROM $table_leads 
             WHERE created_at BETWEEN %s AND %s $rep_clause
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

        // 11. Sales Conversion Funnel Data
        $funnel_stages = array('New', 'Contacted', 'Qualified', 'Quote Shared', 'Negotiation', 'Won');
        $funnel_counts_active = array_fill_keys($funnel_stages, 0);

        // Fetch counts for current period
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
        $funnel_raw = $wpdb->get_results($wpdb->prepare(
            "SELECT stage, COUNT(id) as count 
             FROM $table_leads 
             WHERE created_at BETWEEN %s AND %s $rep_clause
             GROUP BY stage",
            $curr_start, $curr_end
        ));

        foreach ($funnel_raw as $row) {
            $stage_key = empty($row->stage) ? 'New' : $row->stage;
            if (isset($funnel_counts_active[$stage_key])) {
                $funnel_counts_active[$stage_key] += intval($row->count);
            }
        }

        $total_won = isset($funnel_counts_active['Won']) ? $funnel_counts_active['Won'] : 0;
        $overall_conversion_rate = ($total_curr > 0) ? round(($total_won / $total_curr) * 100, 1) : 0;

        $funnel_cumulative = array();
        $running_sum = 0;
        for ($i = count($funnel_stages) - 1; $i >= 0; $i--) {
            $stage = $funnel_stages[$i];
            $running_sum += $funnel_counts_active[$stage];
            $funnel_cumulative[$stage] = $running_sum;
        }

        $funnel_data = array();
        $top_count = $funnel_cumulative['New'];
        $prev_count = 0;

        foreach ($funnel_stages as $stage) {
            $cum_count = $funnel_cumulative[$stage];
            $active_count = $funnel_counts_active[$stage];
            
            $rate_from_start = ($top_count > 0) ? round(($cum_count / $top_count) * 100, 1) : 0;
            
            if ($stage === 'New') {
                $step_rate = 100;
            } else {
                $step_rate = ($prev_count > 0) ? round(($cum_count / $prev_count) * 100, 1) : 0;
            }

            $funnel_data[] = array(
                'stage' => $stage,
                'active_count' => $active_count,
                'cumulative_count' => $cum_count,
                'rate_from_start' => $rate_from_start,
                'step_rate' => $step_rate
            );

            $prev_count = $cum_count;
        }

        return array(
            'marketing' => array(
                'total_leads_period' => $total_curr,
                'total_leads_previous' => $total_prev,
                'growth_pct' => round($percentage_change, 1),
                'leads_by_channel' => $leads_by_channel,
                'channel_rates' => array(),
                'cpl_data' => $cpl_data,
                'top_campaigns' => isset($utm_data['utm_campaign']) ? $utm_data['utm_campaign'] : array(),
                'utm_data' => $utm_data,
                'leads_over_time' => $leads_over_time,
                'leads_by_property' => $leads_by_property,
                'leads_by_rooms' => $leads_by_rooms
            ),
            'sales' => array(
                'leads_by_stage' => $leads_by_stage,
                'funnel_data' => $funnel_data,
                'leads_by_rep' => $rep_metrics,
                'avg_time_connected' => $avg_time_connected,
                'avg_time_won' => $avg_time_won,
                'call_status_distribution' => $call_status_dist,
                'lead_quality_distribution' => $quality_dist,
                'total_won' => $total_won,
                'overall_conversion_rate' => $overall_conversion_rate,
                'leads_by_studio' => $leads_by_studio,
                'studio_performance' => $studio_performance
            ),
            'targets' => array(
                'lead_target' => intval(get_option('idlm_monthly_lead_target', 100)),
                'conversion_target' => intval(get_option('idlm_monthly_conversion_target', 10)),
                'current_leads' => $total_curr,
                'current_conversions' => $total_won
            )
        );
    }

    /**
     * Compute Start & End timestamps for filters
     */
    private static function get_date_ranges($period, $custom_start = '', $custom_end = '') {
        $timezone = wp_timezone();
        $now = new DateTime('now', $timezone);
        
        $curr_start = '';
        $curr_end = $now->format('Y-m-d 23:59:59');
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
                
                $prev_month_end_dt = clone $now;
                $prev_month_end_dt->modify('last day of last month');
                $prev_end = $prev_month_end_dt->format('Y-m-d 23:59:59');
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
                $start->modify('-7 days');
                $curr_start = $start->format('Y-m-d 00:00:00');

                $prev_start_dt = clone $start;
                $prev_start_dt->modify('-8 days');
                $prev_start = $prev_start_dt->format('Y-m-d 00:00:00');

                $prev_end_dt = clone $start;
                $prev_end_dt->modify('-1 second');
                $prev_end = $prev_end_dt->format('Y-m-d H:i:s');
                break;

            case 'last_30_days':
                $start = clone $now;
                $start->modify('-30 days');
                $curr_start = $start->format('Y-m-d 00:00:00');

                $prev_start_dt = clone $start;
                $prev_start_dt->modify('-31 days');
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
                $prev_end = $prev_end_dt->format('Y-m-d 23:59:59');
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
     * Compute CPL for channels using manual cost inputs
     */
    private static function calculate_cost_per_lead($start, $end, $rep_clause = '') {
        global $wpdb;
        $table_leads = $wpdb->prefix . 'idlm_leads';
        $table_cost = $wpdb->prefix . 'idlm_cost_per_lead';

        // Extract year and months covered by period
        $start_month = gmdate('Y-m', strtotime($start));
        $end_month = gmdate('Y-m', strtotime($end));

        // Get total leads grouped by channel
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
        $channel_leads = $wpdb->get_results($wpdb->prepare(
            "SELECT source as channel, COUNT(id) as count 
             FROM $table_leads 
             WHERE created_at BETWEEN %s AND %s $rep_clause
             GROUP BY source",
            $start, $end
        ));

        // Get manual cost inputs for matching range
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
        $costs = $wpdb->get_results($wpdb->prepare(
            "SELECT channel, SUM(cost) as total_cost 
             FROM $table_cost 
             WHERE month_year BETWEEN %s AND %s 
             GROUP BY channel",
            $start_month, $end_month
        ));

        // Map cost to channel
        $channel_costs = array();
        foreach ($costs as $c) {
            $channel_costs[$c->channel] = floatval($c->total_cost);
        }

        $cpl_records = array();
        foreach ($channel_leads as $row) {
            $cost = isset($channel_costs[$row->channel]) ? $channel_costs[$row->channel] : 0.00;
            $cpl = ($row->count > 0) ? round($cost / $row->count, 2) : 0.00;

            $cpl_records[] = array(
                'channel' => $row->channel,
                'leads' => intval($row->count),
                'cost' => $cost,
                'cpl' => $cpl
            );
        }

        return $cpl_records;
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
