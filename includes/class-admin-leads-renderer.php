<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Builds the leads workspace context and delegates markup to guarded views.
 *
 * Builder: Shashi
 */
class IDLM_Admin_Leads_Renderer {
    public static function render() {
        if (!IDLM_Admin::current_user_has_rep_access()) {
            IDLM_View::display('access-restricted.php', array('admin' => true));
            return;
        }

        $context = self::build_context();
        $context['common_overlays_html'] = IDLM_View::render(
            'admin/common-overlays.php',
            self::build_common_overlays_context($context)
        );
        IDLM_View::display('admin/leads.php', $context);
    }

    public static function render_common_overlays() {
        IDLM_View::display('admin/common-overlays.php', self::build_common_overlays_context());
    }

    private static function build_context() {
        global $wpdb;

        $stages = get_option('idlm_custom_stages', array('New', 'Contacted', 'Qualified', 'Quote Shared', 'Negotiation', 'Won', 'Lost'));
        $call_statuses = get_option('idlm_custom_call_statuses', array('Not Called', 'No Answer', 'Call Back Requested', 'Connected - Interested', 'Connected - Not Interested', 'Wrong Number'));
        $studios = get_option('idlm_custom_studios', array('Downtown Studio', 'North Studio', 'South Studio'));
        $num_rooms_options = get_option('idlm_custom_num_rooms', array('1 BHK', '2 BHK', '3 BHK', '4 BHK', '4+ BHK'));
        $reps = get_users(array(
            'role__in' => IDLM_Admin::get_allowed_rep_roles(),
            'fields'   => array('ID', 'display_name'),
        ));
        $pre_sales_reps = get_users(array(
            'role'   => 'idlm_pre_sales',
            'fields' => array('ID', 'display_name'),
        ));
        $active_rep_ids = get_option('idlm_active_assignment_reps', array());
        $is_pre_sales = IDLM_Admin::current_user_is_pre_sales();
        $can_assign_leads = IDLM_Admin::current_user_can_assign_leads();
        $can_manage_options = current_user_can('manage_options');
        $reps_see_all = '1' === get_option('idlm_reps_see_all_leads', '1');
        $pending_calls_count = $is_pre_sales ? IDLM_Admin::get_pending_calls_count() : 0;
        $unread_whatsapp_count = IDLM_Admin::get_unread_whatsapp_count();

        $table_leads = $wpdb->prefix . 'idlm_leads';
        $restricted_user_id = IDLM_Lead_Access_Policy::restriction_user_id();
        $rep_clause = $restricted_user_id ? $wpdb->prepare(' AND assigned_rep_id = %d', $restricted_user_id) : '';

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
        $new_leads_count = intval($wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$table_leads} WHERE stage = %s AND is_duplicate = 0 {$rep_clause}", 'New')));
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
        $not_called_count = intval($wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$table_leads} WHERE call_status = %s AND stage NOT IN ('Won', 'Lost') AND is_duplicate = 0 {$rep_clause}", 'Not Called')));
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
        $followup_count = intval($wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$table_leads}
             WHERE call_status = %s AND stage NOT IN ('Won', 'Lost') AND is_duplicate = 0 {$rep_clause}",
            'Call Back Requested'
        )));
        $unassigned_count = 0;
        if ($can_assign_leads || (!$is_pre_sales && $reps_see_all)) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
            $unassigned_count = intval($wpdb->get_var("SELECT COUNT(*) FROM {$table_leads} WHERE assigned_rep_id = 0 AND stage NOT IN ('Won', 'Lost') AND is_duplicate = 0"));
        }
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
        $qualified_count = intval($wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$table_leads} WHERE lead_quality = %s AND stage NOT IN ('Won', 'Lost') AND is_duplicate = 0 {$rep_clause}", 'Qualified')));
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
        $pending_quality_count = intval($wpdb->get_var("SELECT COUNT(*) FROM {$table_leads} WHERE (lead_quality = '' OR lead_quality IS NULL) AND stage NOT IN ('Won', 'Lost') AND is_duplicate = 0 {$rep_clause}"));

        $qualified_handoff_count = 0;
        $overdue_handoff_count = 0;
        $handoff_exception_count = 0;
        $qualified_yesterday_count = 0;
        $delivered_yesterday_count = 0;
        $qualified_fallback_hours = get_option('idlm_qualified_fallback_hours', '4');
        $handoff_threshold_hours = max(1, intval($qualified_fallback_hours));
        if ($can_assign_leads) {
            $qualified_handoff_count = intval($wpdb->get_var(
                "SELECT COUNT(*) FROM {$table_leads}
                 WHERE lead_quality = 'Qualified' AND handoff_status IN ('awaiting_manager', 'exception')
                   AND stage NOT IN ('Won', 'Lost') AND is_duplicate = 0"
            ));
            $handoff_started_values = $wpdb->get_col(
                "SELECT COALESCE(qualified_at, updated_at, created_at) FROM {$table_leads}
                 WHERE lead_quality = 'Qualified' AND handoff_status IN ('awaiting_manager', 'exception')
                   AND stage NOT IN ('Won', 'Lost') AND is_duplicate = 0"
            );
            $handoff_now = current_time('mysql');
            $handoff_work_start = get_option('idlm_working_hours_start', '09:00');
            $handoff_work_end = get_option('idlm_working_hours_end', '18:00');
            $handoff_work_days = get_option('idlm_working_days', array(1, 2, 3, 4, 5, 6));
            foreach ($handoff_started_values as $handoff_started_at) {
                if (IDLM_Integrations::get_elapsed_working_hours($handoff_started_at, $handoff_now, $handoff_work_start, $handoff_work_end, $handoff_work_days) >= $handoff_threshold_hours) {
                    $overdue_handoff_count++;
                }
            }
            $handoff_exception_count = intval($wpdb->get_var(
                "SELECT COUNT(*) FROM {$table_leads}
                 WHERE lead_quality = 'Qualified' AND handoff_status = 'exception'
                   AND stage NOT IN ('Won', 'Lost') AND is_duplicate = 0"
            ));
            $timezone = wp_timezone();
            $yesterday = new DateTime('yesterday', $timezone);
            $yesterday_start = $yesterday->format('Y-m-d 00:00:00');
            $yesterday_end = $yesterday->format('Y-m-d 23:59:59');
            $qualified_yesterday_count = intval($wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$table_leads} WHERE qualified_at BETWEEN %s AND %s AND is_duplicate = 0",
                $yesterday_start,
                $yesterday_end
            )));
            $delivered_yesterday_count = intval($wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$table_leads}
                 WHERE handoff_at BETWEEN %s AND %s
                   AND handoff_status IN ('manually_assigned', 'auto_assigned') AND is_duplicate = 0",
                $yesterday_start,
                $yesterday_end
            )));
        }

        $assignment_mode = get_option('idlm_assignment_mode', 'manual');
        $active_pre_sales_reps = get_option('idlm_active_pre_sales_reps', null);
        if (null === $active_pre_sales_reps) {
            $active_pre_sales_reps = wp_list_pluck($pre_sales_reps, 'ID');
        }
        $assignment_mode_labels = array(
            'manual'       => 'Manual only',
            'pre_sales'    => 'Pre-Sales intake',
            'round_robin'  => 'Round robin',
            'source_based' => 'Source based',
            'studio_based' => 'Studio based',
        );

        return array(
            'stages'                                  => $stages,
            'call_statuses'                           => $call_statuses,
            'studios'                                 => $studios,
            'num_rooms_options'                       => $num_rooms_options,
            'reps'                                    => $reps,
            'pre_sales_reps'                          => $pre_sales_reps,
            'active_rep_ids'                          => $active_rep_ids,
            'is_pre_sales'                            => $is_pre_sales,
            'can_assign_leads'                        => $can_assign_leads,
            'can_manage_options'                      => $can_manage_options,
            'reps_see_all'                            => $reps_see_all,
            'pending_calls_count'                     => $pending_calls_count,
            'unread_whatsapp_count'                   => $unread_whatsapp_count,
            'new_leads_count'                         => $new_leads_count,
            'not_called_count'                        => $not_called_count,
            'followup_count'                          => $followup_count,
            'unassigned_count'                        => $unassigned_count,
            'qualified_count'                         => $qualified_count,
            'pending_quality_count'                   => $pending_quality_count,
            'qualified_handoff_count'                 => $qualified_handoff_count,
            'overdue_handoff_count'                   => $overdue_handoff_count,
            'handoff_exception_count'                 => $handoff_exception_count,
            'qualified_yesterday_count'               => $qualified_yesterday_count,
            'delivered_yesterday_count'               => $delivered_yesterday_count,
            'qualified_fallback_enabled'              => get_option('idlm_qualified_fallback_enabled', '0'),
            'qualified_fallback_hours'                => $qualified_fallback_hours,
            'qualified_fallback_end_at'               => get_option('idlm_qualified_fallback_end_at', ''),
            'qualified_fallback_end_formatted'        => self::format_fallback_end(),
            'assignment_mode'                         => $assignment_mode,
            'active_reps'                             => get_option('idlm_active_assignment_reps', array()),
            'rep_studios'                             => get_option('idlm_rep_studio_assignments', array()),
            'source_maps'                             => get_option('idlm_source_assignment_mappings', array()),
            'fallback_enabled'                        => get_option('idlm_fallback_assignment_enabled', '0'),
            'fallback_hours'                          => get_option('idlm_fallback_assignment_hours', '24'),
            'qualified_fallback_include_existing'     => get_option('idlm_qualified_fallback_include_existing', '0'),
            'studio_mismatch_behavior'                => get_option('idlm_studio_mismatch_behavior', 'general_pool'),
            'handoff_task_policy'                     => get_option('idlm_handoff_task_policy', 'transfer'),
            'handoff_notifications_enabled'           => get_option('idlm_handoff_notifications_enabled', '0'),
            'active_pre_sales_reps'                   => $active_pre_sales_reps,
            'fallback_last_completed'                 => get_option('idlm_fallback_last_completed_at', 'Never'),
            'fallback_last_status'                    => get_option('idlm_fallback_last_status', 'not_run'),
            'routing_exception_count'                 => intval($wpdb->get_var("SELECT COUNT(*) FROM {$table_leads} WHERE handoff_status = 'exception' AND stage NOT IN ('Won', 'Lost') AND is_duplicate = 0")),
            'unassigned_intake_count'                 => intval($wpdb->get_var("SELECT COUNT(*) FROM {$table_leads} WHERE assigned_rep_id = 0 AND stage NOT IN ('Won', 'Lost') AND is_duplicate = 0")),
            'active_pre_sales_count'                  => count(array_intersect(array_map('absint', (array) $active_pre_sales_reps), array_map('absint', wp_list_pluck($pre_sales_reps, 'ID')))),
            'assignment_mode_label'                   => isset($assignment_mode_labels[$assignment_mode]) ? $assignment_mode_labels[$assignment_mode] : 'Not configured',
            'working_hours_start'                     => get_option('idlm_working_hours_start', '09:00'),
            'working_hours_end'                       => get_option('idlm_working_hours_end', '18:00'),
            'presales_targets'                        => IDLM_DB::get_presales_outreach_targets(),
            'max_active_leads_per_rep'                => absint(get_option('idlm_max_active_leads_per_rep', 30)),
            'working_days'                            => get_option('idlm_working_days', array(1, 2, 3, 4, 5, 6)),
            'routing_exception_notifications_enabled' => get_option('idlm_routing_exception_notifications_enabled', '0'),
            'days_map'                                => array(1 => 'Mon', 2 => 'Tue', 3 => 'Wed', 4 => 'Thu', 5 => 'Fri', 6 => 'Sat', 0 => 'Sun'),
            'currency_code'                           => get_option('idlm_currency_code', 'INR'),
        );
    }

    private static function format_fallback_end() {
        $fallback_end = get_option('idlm_qualified_fallback_end_at', '');
        return $fallback_end ? IDLM_Admin::format_datetime($fallback_end) : '';
    }

    private static function build_common_overlays_context($leads_context = array()) {
        return array(
            'currency_code'      => isset($leads_context['currency_code']) ? $leads_context['currency_code'] : get_option('idlm_currency_code', 'INR'),
            'stages'             => isset($leads_context['stages']) ? $leads_context['stages'] : get_option('idlm_custom_stages', array('New', 'Contacted', 'Qualified', 'Quote Shared', 'Negotiation', 'Won', 'Lost')),
            'lost_reasons'       => get_option('idlm_custom_lost_reasons', array('Budget Too High', 'Lost to Competitor', 'Project Postponed', 'Unable to Contact', 'Location or Service Unavailable', 'Timeline Mismatch', 'Client Not Interested', 'Duplicate or Invalid Lead', 'Other')),
            'reps'               => isset($leads_context['reps']) ? $leads_context['reps'] : get_users(array('role__in' => IDLM_Admin::get_allowed_rep_roles(), 'fields' => array('ID', 'display_name'))),
            'can_assign_leads'   => isset($leads_context['can_assign_leads']) ? $leads_context['can_assign_leads'] : IDLM_Admin::current_user_can_assign_leads(),
            'can_manage_options' => isset($leads_context['can_manage_options']) ? $leads_context['can_manage_options'] : current_user_can('manage_options'),
        );
    }
}
