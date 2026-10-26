<?php
/** Focused M6 responsibility owner. Builder: Shashi. */
if (!defined('ABSPATH')) { exit; }

final class IDLM_Admin_Collection_Reads extends IDLM_Admin {

    public static function ajax_get_leads_table() {
        check_ajax_referer('idlm_admin_nonce', 'nonce');
        if (!self::current_user_has_rep_access()) {
            wp_send_json_error(array('message' => 'Permission denied.'));
        }

        global $wpdb;
        $table_leads = $wpdb->prefix . 'idlm_leads';

        $page = isset($_POST['page']) ? max(1, intval(wp_unslash($_POST['page']))) : 1;
        $allowed_limits = array(25, 50, 100);
        $requested_limit = isset($_POST['per_page']) ? absint(wp_unslash($_POST['per_page'])) : 25;
        $limit = in_array($requested_limit, $allowed_limits, true) ? $requested_limit : 25;
        $offset = ($page - 1) * $limit;
        $sort_by = isset($_POST['sort_by']) ? sanitize_key(wp_unslash($_POST['sort_by'])) : '';
        $sort_order = isset($_POST['sort_order']) ? strtoupper(sanitize_key(wp_unslash($_POST['sort_order']))) : 'ASC';
        $sort_order = in_array($sort_order, array('ASC', 'DESC'), true) ? $sort_order : 'ASC';
        $cursor_token = isset($_POST['cursor']) ? sanitize_text_field(wp_unslash($_POST['cursor'])) : '';

        $where = array();
        // Rep permissions check
        $current_user_id = get_current_user_id();
        $is_admin = current_user_can('manage_options');
        $is_pre_sales = self::current_user_is_pre_sales();
        $restricted_user_id = IDLM_Lead_Access_Policy::restriction_user_id();

        // Duplicate rows remain available to administrators for audit, but reps
        // always work from the single canonical lead.
        if (!$is_admin) {
            $where[] = 'l.is_duplicate = 0';
        }

        $requested_followup_filter = isset($_POST['followup_filter']) ? sanitize_text_field(wp_unslash($_POST['followup_filter'])) : '';
        $active_only = isset($_POST['active_only']) && '1' === sanitize_text_field(wp_unslash($_POST['active_only']));
        if ($is_pre_sales && 'my_qualified_history' === $requested_followup_filter) {
            $where[] = $wpdb->prepare(
                "l.lead_quality = 'Qualified' AND (l.qualified_by_user_id = %d OR l.intake_owner_id = %d)",
                $current_user_id,
                $current_user_id
            );
        } elseif ($restricted_user_id) {
            $where[] = $wpdb->prepare("assigned_rep_id = %d", $restricted_user_id);
        }
        if ($active_only) {
            $where[] = "COALESCE(l.stage, '') NOT IN ('Won', 'Lost')";
        }

        // Apply filters
        $date_columns = array(
            'assigned_at' => 'l.assigned_at', 'created_at' => 'l.created_at', 'handoff_at' => 'l.handoff_at',
            'updated_at' => 'l.updated_at', 'qualified_at' => 'l.qualified_at', 'conversion_time' => 'l.conversion_time',
        );
        $post_date_basis = isset($_POST['date_basis']) ? sanitize_key(wp_unslash($_POST['date_basis'])) : 'assigned_at';
        $date_column = $date_columns[$post_date_basis] ?? 'l.assigned_at';
        $post_date_range = isset($_POST['date_range']) ? sanitize_key(wp_unslash($_POST['date_range'])) : '';
        $cursor_date_anchor = '';
        if (!empty($post_date_range)) {
            $timezone = wp_timezone();
            $now = new DateTime('now', $timezone);
            $cursor_date_anchor = $timezone->getName() . '|' . $now->format('Y-m-d');
            if ($post_date_range === 'today') {
                // phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- REL007-SQL-0194: ajax_get_leads_table(): authenticated nonce/capability/object handlers; fixed date/sort/trigger maps; filter fragments prepared at construction, dynamic IN lists use integers/placeholders; export/task cursors are bounded and scoped.
                $where[] = $wpdb->prepare("{$date_column} >= %s", $now->format('Y-m-d 00:00:00'));
                // phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            } elseif ($post_date_range === 'yesterday') {
                $yesterday = clone $now;
                $yesterday->modify('-1 day');
                $where[] = $wpdb->prepare(
                    // phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- REL007-SQL-0195: ajax_get_leads_table(): authenticated nonce/capability/object handlers; fixed date/sort/trigger maps; filter fragments prepared at construction, dynamic IN lists use integers/placeholders; export/task cursors are bounded and scoped.
                    "{$date_column} >= %s AND {$date_column} <= %s",
                    // phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                    $yesterday->format('Y-m-d 00:00:00'),
                    $yesterday->format('Y-m-d 23:59:59')
                );
            } elseif ($post_date_range === 'week') {
                $start = clone $now;
                $start->modify('monday this week');
                // phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- REL007-SQL-0196: ajax_get_leads_table(): authenticated nonce/capability/object handlers; fixed date/sort/trigger maps; filter fragments prepared at construction, dynamic IN lists use integers/placeholders; export/task cursors are bounded and scoped.
                $where[] = $wpdb->prepare("{$date_column} >= %s", $start->format('Y-m-d 00:00:00'));
                // phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            } elseif ($post_date_range === 'month') {
                // phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- REL007-SQL-0197: ajax_get_leads_table(): authenticated nonce/capability/object handlers; fixed date/sort/trigger maps; filter fragments prepared at construction, dynamic IN lists use integers/placeholders; export/task cursors are bounded and scoped.
                $where[] = $wpdb->prepare("{$date_column} >= %s", $now->format('Y-m-01 00:00:00'));
                // phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            } elseif ($post_date_range === 'last_7_days') {
                $start = clone $now;
                $start->modify('-6 days');
                // phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- REL007-SQL-0198: ajax_get_leads_table(): authenticated nonce/capability/object handlers; fixed date/sort/trigger maps; filter fragments prepared at construction, dynamic IN lists use integers/placeholders; export/task cursors are bounded and scoped.
                $where[] = $wpdb->prepare("{$date_column} >= %s", $start->format('Y-m-d 00:00:00'));
                // phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            } elseif ($post_date_range === 'last_30_days') {
                $start = clone $now;
                $start->modify('-29 days');
                // phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- REL007-SQL-0199: ajax_get_leads_table(): authenticated nonce/capability/object handlers; fixed date/sort/trigger maps; filter fragments prepared at construction, dynamic IN lists use integers/placeholders; export/task cursors are bounded and scoped.
                $where[] = $wpdb->prepare("{$date_column} >= %s", $start->format('Y-m-d 00:00:00'));
                // phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            } elseif ($post_date_range === 'last_month') {
                $start = clone $now;
                $start->modify('first day of last month');
                $end = clone $now;
                $end->modify('last day of last month');
                $where[] = $wpdb->prepare(
                    // phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- REL007-SQL-0200: ajax_get_leads_table(): authenticated nonce/capability/object handlers; fixed date/sort/trigger maps; filter fragments prepared at construction, dynamic IN lists use integers/placeholders; export/task cursors are bounded and scoped.
                    "{$date_column} >= %s AND {$date_column} <= %s",
                    // phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                    $start->format('Y-m-d 00:00:00'),
                    $end->format('Y-m-d 23:59:59')
                );
            } elseif ($post_date_range === 'year' || $post_date_range === 'this_year') {
                // phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- REL007-SQL-0201: ajax_get_leads_table(): authenticated nonce/capability/object handlers; fixed date/sort/trigger maps; filter fragments prepared at construction, dynamic IN lists use integers/placeholders; export/task cursors are bounded and scoped.
                $where[] = $wpdb->prepare("{$date_column} >= %s", $now->format('Y-01-01 00:00:00'));
                // phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            }
        }

        $post_source          = isset($_POST['source']) ? sanitize_text_field(wp_unslash($_POST['source'])) : '';
        $post_rep_id          = isset($_POST['rep_id']) ? sanitize_text_field(wp_unslash($_POST['rep_id'])) : '';
        $post_stage           = isset($_POST['stage']) ? sanitize_text_field(wp_unslash($_POST['stage'])) : '';
        $post_call_status     = isset($_POST['call_status']) ? sanitize_text_field(wp_unslash($_POST['call_status'])) : '';
        $post_quality         = isset($_POST['quality']) ? sanitize_text_field(wp_unslash($_POST['quality'])) : '';
        $post_followup_filter = $requested_followup_filter;
        $post_search          = isset($_POST['search']) ? sanitize_text_field(wp_unslash($_POST['search'])) : '';

        if (!empty($post_source)) {
            $where[] = $wpdb->prepare("source = %s", $post_source);
        }

        if ($post_rep_id !== '') {
            $where[] = $wpdb->prepare("assigned_rep_id = %d", intval($post_rep_id));
        }

        if (!empty($post_stage)) {
            $where[] = $wpdb->prepare("stage = %s", $post_stage);
        }

        if (!empty($post_call_status)) {
            $where[] = $wpdb->prepare("call_status = %s", $post_call_status);
        }

        if (!empty($post_quality)) {
            if ('Qualified Awaiting Assignment' === $post_quality) {
                $where[] = "lead_quality = 'Qualified' AND handoff_status IN ('awaiting_manager', 'exception')";
            } elseif ('Routing Exception' === $post_quality) {
                $where[] = "lead_quality = 'Qualified' AND handoff_status = 'exception'";
            } elseif ('Qualified Plan Received' === $post_quality) {
                $where[] = "lead_quality = 'Qualified' AND floor_plan_received = 1";
            } elseif ('Qualified Plan Pending' === $post_quality) {
                $where[] = "lead_quality = 'Qualified' AND COALESCE(floor_plan_received, 0) = 0";
            } elseif ('Pending' === $post_quality) {
                $where[] = "(lead_quality = '' OR lead_quality IS NULL)";
            } else {
                $where[] = $wpdb->prepare("lead_quality = %s", $post_quality);
            }
        }

        if ('my_qualified_history' === $post_followup_filter && $is_pre_sales) {
            // Visibility and qualification predicates were applied above.
        } elseif ('action_required' === $post_followup_filter && $is_pre_sales) {
            $where[] = "l.is_duplicate = 0 AND COALESCE(l.lead_quality, '') NOT IN ('Qualified', 'Unqualified') AND COALESCE(l.stage, '') NOT IN ('Won', 'Lost')";
        } elseif (!empty($post_followup_filter) && $post_followup_filter === 'pending_calls') {
            $statuses = array('Not Called');
            $rules = self::get_reminder_rules();
            if (is_array($rules)) {
                foreach ($rules as $rule) {
                    if (!empty($rule['status'])) {
                        $statuses[] = $rule['status'];
                    }
                }
            }
            $statuses = array_unique($statuses);
            $in_clause = implode("','", array_map('esc_sql', $statuses));
            $where[] = "call_status IN ('{$in_clause}')";
        } elseif (!empty($post_followup_filter) && $post_followup_filter === 'whatsapp_received') {
            $where[] = IDLM_Admin_Lead_Notifications::whatsapp_exists_sql('l', false);
        } elseif (!empty($post_followup_filter) && $post_followup_filter === 'unread_whatsapp') {
            $where[] = IDLM_Admin_Lead_Notifications::whatsapp_exists_sql('l', true);
        } elseif (!empty($post_followup_filter) && $post_followup_filter === 'overdue') {
            $rules = self::get_reminder_rules();
            $or_clauses = array();
            $now_mysql = current_time('mysql');
            
            foreach ($rules as $rule) {
                $status = $rule['status'];
                $hours = intval($rule['hours']);
                
                if ($status === 'Call Back Requested') {
                    $or_clauses[] = $wpdb->prepare(
                        "(call_status = %s AND (
                            (callback_datetime IS NOT NULL AND callback_datetime < DATE_SUB(%s, INTERVAL %d HOUR)) OR
                            (callback_datetime IS NULL AND COALESCE(updated_at, created_at) < DATE_SUB(%s, INTERVAL %d HOUR))
                        ))",
                        $status, $now_mysql, $hours, $now_mysql, $hours
                    );
                } else {
                    $or_clauses[] = $wpdb->prepare(
                        "(call_status = %s AND COALESCE(updated_at, created_at) < DATE_SUB(%s, INTERVAL %d HOUR))",
                        $status, $now_mysql, $hours
                    );
                }
            }
            
            if (!empty($or_clauses)) {
                $where[] = "(" . implode(" OR ", $or_clauses) . ")";
            } else {
                $where[] = "1=0";
            }
        } elseif (!empty($post_followup_filter) && $post_followup_filter === 'stale_updates') {
            $cutoff = IDLM_Admin_Lead_Notifications::get_stale_lead_cutoff();
            $where[] = $wpdb->prepare(
                // phpcs:disable WordPress.DB.PreparedSQL.NotPrepared -- REL007-SQL-0202: ajax_get_leads_table(): authenticated nonce/capability/object handlers; fixed date/sort/trigger maps; filter fragments prepared at construction, dynamic IN lists use integers/placeholders; export/task cursors are bounded and scoped.
                "l.assigned_rep_id > 0 AND l.is_duplicate = 0 AND l.stage NOT IN ('Won', 'Lost') AND COALESCE(l.assigned_at, l.created_at) < %s AND " . IDLM_Admin_Lead_Notifications::get_no_recent_assigned_rep_action_sql($cutoff, 'l'),
                // phpcs:enable WordPress.DB.PreparedSQL.NotPrepared
                $cutoff
            );
        }

        if (!empty($post_search)) {
            $search = '%' . $wpdb->esc_like($post_search) . '%';
            $where[] = $wpdb->prepare("(first_name LIKE %s OR last_name LIKE %s OR email LIKE %s OR phone LIKE %s)", $search, $search, $search, $search);
        }

        $where_clause = !empty($where) ? "WHERE " . implode(" AND ", $where) : "";

        if (isset($_POST['count_only']) && '1' === sanitize_text_field(wp_unslash($_POST['count_only']))) {
            // Reuse the authenticated, capability-scoped table filters for export previews without rendering rows.
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
            $matched_count = intval($wpdb->get_var("SELECT COUNT(l.id) FROM {$table_leads} l {$where_clause}"));
            wp_send_json_success(array('total_items' => $matched_count));
        }

        // Metric-grid values use the same permissions and active filters as the
        // table, while retaining each card's documented workflow definition.
        $metric_yesterday = new DateTime('yesterday', wp_timezone());
        $metric_yesterday_start = $metric_yesterday->format('Y-m-d 00:00:00');
        $metric_yesterday_end = $metric_yesterday->format('Y-m-d 23:59:59');
        $metric_yesterday_start_sql = $wpdb->prepare('%s', $metric_yesterday_start);
        $metric_yesterday_end_sql = $wpdb->prepare('%s', $metric_yesterday_end);
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
        $metric_row = $wpdb->get_row(
            // phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- REL007-SQL-0203: ajax_get_leads_table(): authenticated nonce/capability/object handlers; fixed date/sort/trigger maps; filter fragments prepared at construction, dynamic IN lists use integers/placeholders; export/task cursors are bounded and scoped.
            "SELECT
                SUM(CASE WHEN l.is_duplicate = 0 AND l.stage = 'New' THEN 1 ELSE 0 END) AS new_count,
                SUM(CASE WHEN l.is_duplicate = 0 AND l.stage NOT IN ('Won', 'Lost') AND l.call_status = 'Not Called' THEN 1 ELSE 0 END) AS not_called_count,
                SUM(CASE WHEN l.is_duplicate = 0 AND l.stage NOT IN ('Won', 'Lost') AND l.call_status = 'Call Back Requested' THEN 1 ELSE 0 END) AS followup_count,
                SUM(CASE WHEN l.is_duplicate = 0 AND l.stage NOT IN ('Won', 'Lost') AND l.assigned_rep_id = 0 THEN 1 ELSE 0 END) AS unassigned_count,
                SUM(CASE WHEN l.is_duplicate = 0 AND l.stage NOT IN ('Won', 'Lost') AND l.lead_quality = 'Qualified' THEN 1 ELSE 0 END) AS qualified_count,
                SUM(CASE WHEN l.is_duplicate = 0 AND l.stage NOT IN ('Won', 'Lost') AND COALESCE(l.lead_quality, '') = '' THEN 1 ELSE 0 END) AS pending_quality_count,
                SUM(CASE WHEN l.is_duplicate = 0 AND l.stage NOT IN ('Won', 'Lost') AND l.lead_quality = 'Qualified' AND l.handoff_status IN ('awaiting_manager', 'exception') THEN 1 ELSE 0 END) AS qualified_handoff_count,
                SUM(CASE WHEN l.is_duplicate = 0 AND l.stage NOT IN ('Won', 'Lost') AND l.lead_quality = 'Qualified' AND l.handoff_status = 'exception' THEN 1 ELSE 0 END) AS routing_exception_count,
                SUM(CASE WHEN l.is_duplicate = 0 AND l.qualified_at BETWEEN {$metric_yesterday_start_sql} AND {$metric_yesterday_end_sql} THEN 1 ELSE 0 END) AS qualified_yesterday_count,
                SUM(CASE WHEN l.is_duplicate = 0 AND l.handoff_at BETWEEN {$metric_yesterday_start_sql} AND {$metric_yesterday_end_sql} AND l.handoff_status IN ('manually_assigned', 'auto_assigned') THEN 1 ELSE 0 END) AS delivered_yesterday_count
             FROM {$table_leads} l {$where_clause}"
            // phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        );
        $metric_stats = array(
            'new' => $metric_row ? intval($metric_row->new_count) : 0,
            'not_called' => $metric_row ? intval($metric_row->not_called_count) : 0,
            'followup' => $metric_row ? intval($metric_row->followup_count) : 0,
            'unassigned' => $metric_row ? intval($metric_row->unassigned_count) : 0,
            'qualified' => $metric_row ? intval($metric_row->qualified_count) : 0,
            'pending_quality' => $metric_row ? intval($metric_row->pending_quality_count) : 0,
            'qualified_handoffs' => $metric_row ? intval($metric_row->qualified_handoff_count) : 0,
            'routing_exceptions' => $metric_row ? intval($metric_row->routing_exception_count) : 0,
            'qualified_yesterday' => $metric_row ? intval($metric_row->qualified_yesterday_count) : 0,
            'delivered_yesterday' => $metric_row ? intval($metric_row->delivered_yesterday_count) : 0,
            'overdue_handoffs' => 0,
        );

        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQL.NotPrepared -- REL007-SQL-0204: ajax_get_leads_table(): authenticated nonce/capability/object handlers; fixed date/sort/trigger maps; filter fragments prepared at construction, dynamic IN lists use integers/placeholders; export/task cursors are bounded and scoped.
        $metric_handoff_starts = $wpdb->get_col(
            "SELECT COALESCE(l.qualified_at, l.updated_at, l.created_at) FROM {$table_leads} l {$where_clause}" .
            (empty($where) ? ' WHERE ' : ' AND ') .
        // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQL.NotPrepared
            "l.is_duplicate = 0 AND l.stage NOT IN ('Won', 'Lost') AND l.lead_quality = 'Qualified' AND l.handoff_status IN ('awaiting_manager', 'exception')"
        );
        $metric_now = current_time('mysql');
        foreach ($metric_handoff_starts as $metric_handoff_start) {
            if (IDLM_Integrations::get_elapsed_working_hours(
                $metric_handoff_start,
                $metric_now,
                get_option('idlm_working_hours_start', '09:00'),
                get_option('idlm_working_hours_end', '18:00'),
                get_option('idlm_working_days', array(1, 2, 3, 4, 5, 6))
            ) >= max(1, intval(get_option('idlm_qualified_fallback_hours', '4')))) {
                $metric_stats['overdue_handoffs']++;
            }
        }

        // Query leads
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
        $total_items = intval($wpdb->get_var("SELECT COUNT(l.id) FROM {$table_leads} l {$where_clause}"));
        $total_pages = ceil($total_items / $limit);

        $now_mysql = current_time('mysql');
        $whatsapp_reply_pattern = $wpdb->esc_like('[WhatsApp Reply]:') . '%';
        $direct_wa_reply_pattern = $wpdb->esc_like('[Direct WA Reply]:') . '%';
        // The dynamic WHERE clause contains only fixed SQL fragments and values escaped above with $wpdb->prepare().
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
        $sortable_columns = array(
            'name'          => "LOWER(CONCAT_WS(' ', l.first_name, l.last_name))",
            'source'        => "LOWER(COALESCE(l.source, ''))",
            'created_at'    => 'l.created_at',
            'assigned_at'   => 'l.assigned_at',
            'age'           => 'l.created_at',
            'budget'        => "LOWER(COALESCE(l.budget, ''))",
            'stage'         => "LOWER(COALESCE(l.stage, ''))",
            'assigned_rep'  => "LOWER(COALESCE(u.display_name, ''))",
            'lead_quality'  => "LOWER(COALESCE(l.lead_quality, ''))",
            'tasks'         => 'pending_tasks_count',
            'callback'      => 'l.callback_datetime',
            'response'      => "LOWER(COALESCE(l.call_status, ''))",
            'details'       => "((COALESCE(l.project_name, '') <> '') + (COALESCE(l.property_location, '') <> '') + (COALESCE(l.budget, '') <> '') + (COALESCE(l.possession_date, '') <> '') + (COALESCE(l.property_type, '') <> '') + (COALESCE(l.project_timeline, '') <> ''))",
        );
        $uses_default_queue_order = empty($sort_by) || !isset($sortable_columns[$sort_by]);
        $order_by = $is_pre_sales
            ? "CASE WHEN l.call_status = 'Call Back Requested' AND l.callback_datetime IS NOT NULL AND l.callback_datetime <= %s THEN 0 WHEN l.call_status = 'Not Called' THEN 1 ELSE 2 END ASC, COALESCE(l.callback_datetime, l.assigned_at, l.created_at) ASC, l.id ASC"
            : 'COALESCE(l.assigned_at, l.created_at) DESC, l.id DESC';
        if (!$uses_default_queue_order) {
            $sort_expression = $sortable_columns[$sort_by];
            if ('age' === $sort_by) {
                $sort_expression = 'l.created_at';
                $sort_order = 'ASC' === $sort_order ? 'DESC' : 'ASC';
            }
            $order_by = "{$sort_expression} {$sort_order}, l.id {$sort_order}";
        }

        $cursor_enabled = !$is_pre_sales && $uses_default_queue_order;
        $cursor_scope = array(
            'access' => array(
                'administrator' => $is_admin ? 1 : 0,
                'restricted_user_id' => intval($restricted_user_id),
            ),
            'filters' => array(
                'active_only' => $active_only ? 1 : 0,
                'call_status' => $post_call_status,
                'date_anchor' => $cursor_date_anchor,
                'date_basis' => $post_date_basis,
                'date_range' => $post_date_range,
                'followup_filter' => $post_followup_filter,
                'quality' => $post_quality,
                'rep_id' => $post_rep_id,
                'search' => $post_search,
                'source' => $post_source,
                'stage' => $post_stage,
            ),
            'limit' => $limit,
            'sort' => 'assigned_or_created_desc_id_desc',
        );
        if ('' !== $cursor_token && !$cursor_enabled) {
            wp_send_json_error(array('message' => 'This pagination cursor does not match the selected lead sort.'), 400);
        }

        $pagination_where_clause = $where_clause;
        $use_cursor_query = $cursor_enabled && (1 === $page || '' !== $cursor_token);
        if ('' !== $cursor_token) {
            $cursor_position = IDLM_Cursor_Pagination::decode($cursor_token, 'leads', $cursor_scope);
            if (is_wp_error($cursor_position)) {
                wp_send_json_error(array('message' => $cursor_position->get_error_message()), 400);
            }
            $cursor_sort_value = isset($cursor_position['sort_value']) ? (string) $cursor_position['sort_value'] : '';
            $cursor_id = isset($cursor_position['id']) ? absint($cursor_position['id']) : 0;
            if (!$cursor_id || !preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $cursor_sort_value)) {
                wp_send_json_error(array('message' => 'The pagination cursor position is invalid. Refresh the lead list and try again.'), 400);
            }
            $cursor_predicate = $wpdb->prepare(
                '(COALESCE(l.assigned_at, l.created_at) < %s OR (COALESCE(l.assigned_at, l.created_at) = %s AND l.id < %d))',
                $cursor_sort_value,
                $cursor_sort_value,
                $cursor_id
            );
            $pagination_where_clause .= (empty($where) ? ' WHERE ' : ' AND ') . $cursor_predicate;
        }

        $query_params = array($now_mysql, $whatsapp_reply_pattern, $direct_wa_reply_pattern);
        if ($is_pre_sales && $uses_default_queue_order) {
            $query_params[] = $now_mysql;
        }
        $query_params[] = $limit;
        if (!$use_cursor_query) {
            $query_params[] = $offset;
        }
        $pagination_limit_sql = $use_cursor_query ? 'LIMIT %d' : 'LIMIT %d OFFSET %d';
        // Every dynamic filter fragment in $where_clause is fixed SQL or was
        // prepared at construction time; remaining placeholders are prepared here.
        // phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber -- REL007-SQL-0205: ajax_get_leads_table(): authenticated nonce/capability/object handlers; fixed date/sort/trigger maps; filter fragments prepared at construction, dynamic IN lists use integers/placeholders; export/task cursors are bounded and scoped.
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
        $leads = $wpdb->get_results($wpdb->prepare(
            "SELECT l.*, u.display_name as rep_name,
                    (SELECT COUNT(t.id) FROM {$wpdb->prefix}idlm_tasks t WHERE t.lead_id = l.id AND t.status = 'pending') as pending_tasks_count,
                    (SELECT COUNT(t.id) FROM {$wpdb->prefix}idlm_tasks t WHERE t.lead_id = l.id AND t.status = 'pending' AND t.due_date IS NOT NULL AND t.due_date < %s) as overdue_tasks_count,
                    (SELECT MAX(a.id) FROM {$wpdb->prefix}idlm_lead_activity_log a
                     WHERE a.lead_id = l.id AND (
                        a.activity_type = 'whatsapp_reply' OR
                        (a.activity_type = 'comment' AND (a.details LIKE %s OR a.details LIKE %s))
                     )) as latest_whatsapp_reply_id
             FROM {$table_leads} l 
             LEFT JOIN {$wpdb->users} u ON l.assigned_rep_id = u.ID 
             {$pagination_where_clause} ORDER BY {$order_by} {$pagination_limit_sql}",
        // phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber
            $query_params
        ));
        $outreach_by_lead = array();
        if ($is_pre_sales && $leads) {
            $lead_ids = array_map('absint', wp_list_pluck($leads, 'id'));
            $id_sql = implode(',', $lead_ids);
            // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- REL007-SQL-0206: ajax_get_leads_table(): authenticated nonce/capability/object handlers; fixed date/sort/trigger maps; filter fragments prepared at construction, dynamic IN lists use integers/placeholders; export/task cursors are bounded and scoped.
            $outreach_rows = $wpdb->get_results(
                "SELECT lead_id, DATE(created_at) activity_date,
                        SUM(activity_type = 'outbound_call') calls,
                        SUM(activity_type = 'whatsapp_outbound') whatsapp
                 FROM {$wpdb->prefix}idlm_lead_activity_log
                 WHERE lead_id IN ({$id_sql}) AND activity_type IN ('outbound_call', 'whatsapp_outbound')
                 GROUP BY lead_id, DATE(created_at)
                 ORDER BY lead_id ASC, DATE(created_at) ASC"
            // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            );
            foreach ($outreach_rows as $row) {
                $outreach_by_lead[absint($row->lead_id)][$row->activity_date] = array(
                    'activity_date' => $row->activity_date,
                    'calls' => (int) $row->calls,
                    'whatsapp' => (int) $row->whatsapp,
                );
            }
        }

        // Get config data for inline edit options
        $stages = get_option('idlm_custom_stages', array('New', 'Contacted', 'Qualified', 'Quote Shared', 'Negotiation', 'Won', 'Lost'));
        $reps = get_users(array(
            'role__in' => self::get_allowed_rep_roles(),
            'fields'   => array('ID', 'display_name')
        ));
        $active_rep_ids = get_option('idlm_active_assignment_reps', array());
        $whatsapp_read_ids = self::get_whatsapp_read_activity_ids();
        ob_start();
        if (empty($leads)) {
            echo '<tr><td colspan="' . ($is_pre_sales ? '12' : '11') . '" style="text-align:center;">No leads found matching your active filters.</td></tr>';
        } else {
            foreach ($leads as $l) {
                $name = esc_html($l->first_name . ' ' . $l->last_name);
                if (empty($name)) {
                    $name = 'Unnamed Lead';
                }
                $dup_badge = $l->is_duplicate ? '<span class="idlm-badge idlm-badge-warning" style="margin-left:5px; font-size:9px; padding:2px 6px;">DUP</span>' : '';
                $last_read_whatsapp_id = isset($whatsapp_read_ids[$l->id]) ? absint($whatsapp_read_ids[$l->id]) : 0;
                $has_unread_whatsapp = !empty($l->latest_whatsapp_reply_id) && absint($l->latest_whatsapp_reply_id) > $last_read_whatsapp_id;
                $responsive_row_id = 'idlm-lead-row-' . absint($l->id);
                if ($is_pre_sales) {
                    $outreach_targets = IDLM_DB::get_presales_outreach_targets();
                    $calculated_progress = IDLM_DB::calculate_presales_outreach_progress(
                        array_values($outreach_by_lead[absint($l->id)] ?? array()),
                        $outreach_targets
                    );
                    $complete_cycles = min(6, count($calculated_progress['completed_cycles']));
                    $cycle_outreach = $calculated_progress['active_cycle'];
                    $details_values = array($l->project_name, $l->property_location, $l->budget, $l->possession_date, $l->property_type, $l->project_timeline);
                    $details_complete = count(array_filter($details_values));
                    $has_response = in_array($l->call_status, array('Connected - Interested', 'Connected - Not Interested'), true) || !empty($l->latest_whatsapp_reply_id);
                    $callback_due = 'Call Back Requested' === $l->call_status && !empty($l->callback_datetime) && strtotime($l->callback_datetime) <= current_time('timestamp');
                    if ('Qualified' === $l->lead_quality) {
                        $next_action = 'Qualified — awaiting handoff';
                        if (!empty($l->handoff_status) && 'awaiting_manager' !== $l->handoff_status) {
                            $next_action = 'Qualified — handoff complete';
                        }
                        $sla_label = 'Decision complete';
                        $sla_class = 'is-complete';
                    } elseif ('Unqualified' === $l->lead_quality) {
                        $next_action = 'Closed as unqualified';
                        $sla_label = 'Decision complete';
                        $sla_class = 'is-complete';
                    } elseif ($callback_due) {
                        $next_action = 'Call back now';
                        $sla_label = 'Overdue callback';
                        $sla_class = 'is-overdue';
                    } elseif ('Wrong Number' === $l->call_status) {
                        $next_action = 'Review invalid contact';
                        $sla_label = 'Decision ready';
                        $sla_class = 'is-response';
                    } elseif ('Connected - Not Interested' === $l->call_status) {
                        $next_action = 'Review early close';
                        $sla_label = 'Decision ready';
                        $sla_class = 'is-response';
                    } elseif ($has_response && $details_complete < 6) {
                        $next_action = 'Complete project details';
                        $sla_label = 'Response received';
                        $sla_class = 'is-response';
                    } elseif ($has_response && $l->budget && IDLM_DB::budget_to_rupees($l->budget) < 400000) {
                        $next_action = 'Review budget mismatch';
                        $sla_label = 'Decision ready';
                        $sla_class = 'is-response';
                    } elseif ($has_response && IDLM_DB::budget_to_rupees($l->budget) >= 400000) {
                        $next_action = 'Ready to qualify';
                        $sla_label = 'Decision ready';
                        $sla_class = 'is-complete';
                    } elseif ($complete_cycles >= 6) {
                        $next_action = 'Send final closure';
                        $sla_label = 'Closure ready';
                        $sla_class = 'is-response';
                    } elseif ((int) $cycle_outreach['calls'] < (int) $outreach_targets['calls']) {
                        $next_action = 'Log call ' . ((int) $cycle_outreach['calls'] + 1) . ' of ' . (int) $outreach_targets['calls'];
                        $sla_label = (int) $cycle_outreach['calls'] > 0 || (int) $cycle_outreach['whatsapp'] > 0 ? 'Cycle in progress' : 'Due today';
                        $sla_class = (int) $cycle_outreach['calls'] > 0 || (int) $cycle_outreach['whatsapp'] > 0 ? 'is-progress' : 'is-due';
                    } elseif ((int) $cycle_outreach['whatsapp'] < (int) $outreach_targets['whatsapp']) {
                        $next_action = 'Log WhatsApp';
                        $sla_label = 'Cycle in progress';
                        $sla_class = 'is-progress';
                    } else {
                        $next_action = 'Today complete';
                        $sla_label = 'On track';
                        $sla_class = 'is-complete';
                    }
                    $response_label = !empty($l->latest_whatsapp_reply_id) && !in_array($l->call_status, array('Connected - Interested', 'Connected - Not Interested'), true)
                        ? 'WhatsApp reply'
                        : ($l->call_status ?: 'No response');
                    $budget_pass = IDLM_DB::budget_to_rupees($l->budget) >= 400000;
                    $decision_ready = in_array($next_action, array('Ready to qualify', 'Review invalid contact', 'Review early close', 'Review budget mismatch', 'Send final closure'), true);
                    $decision_complete = in_array($l->lead_quality, array('Qualified', 'Unqualified'), true);
                    ?>
                    <tr id="<?php echo esc_attr($responsive_row_id); ?>" data-id="<?php echo esc_attr($l->id); ?>" class="idlm-presales-queue-row idlm-responsive-lead-row <?php echo $l->is_duplicate ? 'idlm-duplicate-row' : ''; ?>">
                        <td class="idlm-selection-column idlm-col-priority-primary" data-label="Select"><input type="checkbox" class="idlm-row-selector" value="<?php echo esc_attr($l->id); ?>" aria-label="Select lead <?php echo esc_attr($name); ?>"></td>
                        <td class="idlm-lead-identity-cell idlm-col-priority-primary" data-label="Lead" data-lead-drawer-id="<?php echo esc_attr($l->id); ?>">
                            <button type="button" class="idlm-lead-row-open" onclick="openLeadDrawer(<?php echo esc_attr($l->id); ?>)"><strong><?php echo esc_html($name); ?></strong><span class="screen-reader-text"> — open lead details</span></button> <?php echo wp_kses_post($dup_badge); ?>
                            <?php if ($has_unread_whatsapp): ?><span class="idlm-whatsapp-row-badge">New WhatsApp</span><?php endif; ?>
                            <small><?php echo esc_html($l->phone); ?><?php echo $l->project_name ? ' · ' . esc_html($l->project_name) : ''; ?></small>
                            <button type="button" class="idlm-lead-row-expand" aria-expanded="false" aria-controls="<?php echo esc_attr($responsive_row_id); ?>"><span class="dashicons dashicons-arrow-down-alt2" aria-hidden="true"></span><span class="idlm-lead-row-expand-label">Show details</span></button>
                        </td>
                        <td class="idlm-col-priority-low" data-label="Channel"><span class="idlm-badge idlm-badge-<?php echo esc_attr(strtolower(str_replace(array(' ', '/', '-'), '', $l->source))); ?>"><?php echo esc_html($l->source ?: 'Unknown'); ?></span></td>
                        <td class="idlm-col-priority-low" data-label="Studio"><?php echo esc_html($l->preferred_studio ?: '—'); ?></td>
                        <td class="idlm-col-created-at idlm-col-priority-low" data-label="Assigned date"><?php echo wp_kses_post(IDLM_Admin_Presenter::render_lead_table_datetime($l->assigned_at)); ?></td>
                        <td class="idlm-col-priority-low" data-label="Age"><strong><?php echo esc_html(self::get_business_day_age($l->created_at)); ?></strong><small>business days</small></td>
                        <td class="idlm-col-priority-low" data-label="Today"><span class="idlm-progress-chip"><?php echo esc_html($cycle_outreach['calls']); ?>/<?php echo esc_html($outreach_targets['calls']); ?> calls</span><span class="idlm-progress-chip"><?php echo esc_html($cycle_outreach['whatsapp']); ?>/<?php echo esc_html($outreach_targets['whatsapp']); ?> WA</span><small><?php echo esc_html(min(6, $complete_cycles)); ?>/6 cycles</small></td>
                        <td class="idlm-col-priority-secondary" data-label="Response"><span class="idlm-response-chip"><?php echo esc_html($response_label); ?></span></td>
                        <td class="idlm-col-priority-secondary" data-label="Budget"><strong><?php echo esc_html($l->budget ?: 'Missing'); ?></strong><span class="idlm-budget-check <?php echo $budget_pass ? 'is-pass' : 'is-review'; ?>"><?php echo $budget_pass ? '₹4L+' : 'Review'; ?></span></td>
                        <td class="idlm-col-priority-low" data-label="Project details"><strong><?php echo esc_html($details_complete); ?>/6</strong><small><?php echo empty($l->floor_plan_received) ? 'Plan pending' : 'Plan received'; ?></small></td>
                        <td class="idlm-col-priority-primary" data-label="Follow-up status"><span class="idlm-sla-chip <?php echo esc_attr($sla_class); ?>"><?php echo esc_html($sla_label); ?></span></td>
                        <td class="idlm-col-priority-primary idlm-lead-row-actions" data-label="Actions"><div class="idlm-queue-actions"><button type="button" class="idlm-btn idlm-btn-secondary idlm-presales-row-touch" data-kind="call" data-lead-id="<?php echo esc_attr($l->id); ?>" <?php disabled($decision_complete); ?>>Log call</button><button type="button" class="idlm-btn idlm-btn-secondary idlm-presales-row-touch" data-kind="whatsapp" data-lead-id="<?php echo esc_attr($l->id); ?>" <?php disabled($decision_complete); ?>>Log WA</button><?php if ($decision_ready): ?><button type="button" class="idlm-btn idlm-btn-primary" onclick="openLeadDrawer(<?php echo esc_attr($l->id); ?>)">Decide</button><?php endif; ?></div></td>
                    </tr>
                    <?php
                    continue;
                }
                ?>
                <tr id="<?php echo esc_attr($responsive_row_id); ?>" data-id="<?php echo esc_attr($l->id); ?>" class="idlm-responsive-lead-row <?php echo $l->is_duplicate ? 'idlm-duplicate-row' : ''; ?>">
                    <td class="idlm-selection-column idlm-col-priority-primary" data-label="Select"><input type="checkbox" class="idlm-row-selector" value="<?php echo esc_attr($l->id); ?>" aria-label="Select lead <?php echo esc_attr($name); ?>"></td>
                    <td class="idlm-lead-identity-cell idlm-col-priority-primary" data-label="Lead" data-lead-drawer-id="<?php echo esc_attr($l->id); ?>">
                        <button type="button" class="idlm-lead-row-open" onclick="openLeadDrawer(<?php echo esc_attr($l->id); ?>)"><strong><?php echo esc_html($name); ?></strong><span class="screen-reader-text"> — open lead details</span></button> <?php echo wp_kses_post($dup_badge); ?>
                        <?php if ($has_unread_whatsapp): ?>
                            <span class="idlm-whatsapp-row-badge" data-lead-id="<?php echo esc_attr($l->id); ?>" title="This lead sent a new WhatsApp message">New WhatsApp</span>
                        <?php endif; ?>
                        <div class="idlm-lead-row-contact"><?php echo esc_html($l->email); ?> | <?php echo esc_html($l->phone); ?></div>
                        <?php if ('Qualified' === $l->lead_quality && 'awaiting_manager' === $l->handoff_status):
                            $handoff_started_at = $l->qualified_at ?: ($l->updated_at ?: $l->created_at);
                            $handoff_wait_hours = IDLM_Integrations::get_elapsed_working_hours(
                                $handoff_started_at,
                                current_time('mysql'),
                                get_option('idlm_working_hours_start', '09:00'),
                                get_option('idlm_working_hours_end', '18:00'),
                                get_option('idlm_working_days', array(1, 2, 3, 4, 5, 6))
                            );
                            $handoff_is_overdue = $handoff_wait_hours >= max(1, intval(get_option('idlm_qualified_fallback_hours', '4')));
                        ?>
                            <span class="idlm-row-handoff-badge <?php echo $handoff_is_overdue ? 'is-overdue' : ''; ?>">Awaiting assignment · <?php echo esc_html($handoff_wait_hours < 1 ? '<1 working hour' : floor($handoff_wait_hours) . ' working hours'); ?></span>
                            <?php if (!empty($l->property_location)): ?><small class="idlm-row-handoff-location"><?php echo esc_html($l->property_location); ?></small><?php endif; ?>
                        <?php endif; ?>
                        <button type="button" class="idlm-lead-row-expand" aria-expanded="false" aria-controls="<?php echo esc_attr($responsive_row_id); ?>"><span class="dashicons dashicons-arrow-down-alt2" aria-hidden="true"></span><span class="idlm-lead-row-expand-label">Show details</span></button>
                    </td>
                    <td class="idlm-col-priority-low" data-label="Channel"><span class="idlm-badge idlm-badge-<?php echo esc_attr(strtolower(str_replace(' ', '', $l->source))); ?>"><?php echo esc_html($l->source); ?></span></td>
                    <td class="idlm-col-priority-low" data-label="Studio"><?php echo esc_html($l->preferred_studio ?: '—'); ?></td>
                    <td class="idlm-col-priority-secondary" data-label="Budget"><strong><?php echo !empty($l->budget) ? esc_html($l->budget) : 'N/A'; ?></strong></td>
                    
                    <!-- Stage Quick Edit dropdown -->
                    <td class="idlm-col-priority-primary" data-label="Stage">
                        <select class="idlm-table-select idlm-quick-edit" data-field="stage" data-id="<?php echo esc_attr($l->id); ?>" data-property-location="<?php echo esc_attr($l->property_location); ?>" data-quote-value="<?php echo esc_attr(isset($l->quote_value) ? $l->quote_value : ''); ?>" data-quote-file="<?php echo esc_url(isset($l->quote_file) ? $l->quote_file : ''); ?>">
                            <?php foreach ($stages as $st): ?>
                                <option value="<?php echo esc_attr($st); ?>" <?php selected($l->stage, $st); ?>><?php echo esc_html($st); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </td>
 
                    <!-- Rep Quick Edit dropdown -->
                    <td class="idlm-col-priority-secondary" data-label="Assigned representative">
                        <?php if (self::current_user_can_assign_leads()): ?>
                            <select class="idlm-table-select idlm-quick-edit" data-field="assigned_rep_id" data-id="<?php echo esc_attr($l->id); ?>">
                                <option value="0">Unassigned</option>
                                <?php foreach ($reps as $rep): 
                                    if (!in_array($rep->ID, $active_rep_ids) && $l->assigned_rep_id != $rep->ID) {
                                        continue;
                                    }
                                ?>
                                    <?php $assignable = IDLM_Lead_Access_Policy::is_assignable_rep($rep->ID); if (!$assignable && $l->assigned_rep_id != $rep->ID) { continue; } ?>
                                    <option value="<?php echo esc_attr($rep->ID); ?>" <?php selected($l->assigned_rep_id, $rep->ID); ?> <?php disabled(!$assignable); ?>><?php echo esc_html($rep->display_name); ?></option>
                                  <?php endforeach; ?>
                            </select>
                        <?php else: ?>
                            <?php 
                            $assigned = !empty($l->rep_name) ? $l->rep_name : 'Unassigned';
                            echo esc_html($assigned);
                            ?>
                        <?php endif; ?>
                    </td>
 
                    <!-- Lead Quality dropdown -->
                    <td class="idlm-col-priority-primary" data-label="Lead quality">
                        <select class="idlm-table-select idlm-quick-edit" data-field="lead_quality" data-id="<?php echo esc_attr($l->id); ?>">
                            <option value="" <?php selected($l->lead_quality, ''); ?>>Pending Qualification</option>
                            <option value="Qualified" <?php selected($l->lead_quality, 'Qualified'); ?>>✅ <?php echo esc_html('Qualified' === $l->lead_quality ? self::get_qualification_status_label($l) : 'Qualified'); ?></option>
                            <option value="Unqualified" <?php selected($l->lead_quality, 'Unqualified'); ?>>❌ Unqualified</option>
                        </select>
                    </td>
 
                    <!-- Tasks Indicator -->
                    <td class="idlm-col-priority-secondary" data-label="Tasks">
                        <?php 
                        $pending_count = intval($l->pending_tasks_count);
                        $overdue_count = intval($l->overdue_tasks_count);
                        if ($pending_count > 0): 
                            $badge_class = ($overdue_count > 0) ? 'idlm-task-badge-overdue' : 'idlm-task-badge-active';
                            $title_text = sprintf('%d pending tasks (%d overdue)', $pending_count, $overdue_count);
                        ?>
                            <div class="idlm-table-task-badge <?php echo esc_attr($badge_class); ?>" title="<?php echo esc_attr($title_text); ?>" onclick="event.stopPropagation(); openLeadDrawer(<?php echo esc_attr($l->id); ?>, 'drawer-tab-tasks');" style="cursor: pointer; display: inline-flex; align-items: center; gap: 4px; font-size: 11px; padding: 3px 8px; border-radius: 12px; font-weight: 600;">
                                <span class="dashicons dashicons-editor-ul" style="font-size: 14px; width: 14px; height: 14px; line-height: 14px;"></span>
                                <span><?php echo esc_html($pending_count); ?></span>
                            </div>
                        <?php else: ?>
                            <span style="color: #cbd5e1; font-size: 11px;">None</span>
                        <?php endif; ?>
                    </td>

                    <td class="idlm-col-created-at idlm-col-priority-low" data-label="Assigned at"><?php echo wp_kses_post(IDLM_Admin_Presenter::render_lead_table_datetime($l->assigned_at)); ?></td>
                    <td class="idlm-col-created-at idlm-col-priority-low" data-label="Created at"><?php echo wp_kses_post(IDLM_Admin_Presenter::render_lead_table_datetime($l->created_at)); ?></td>
                </tr>
                <?php
            }
        }
        $html = ob_get_clean();

        $next_cursor = '';
        if ($cursor_enabled && $page < $total_pages && !empty($leads)) {
            $last_lead = end($leads);
            $next_sort_value = null !== $last_lead->assigned_at ? $last_lead->assigned_at : $last_lead->created_at;
            if (is_string($next_sort_value) && preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $next_sort_value)) {
                $next_cursor = IDLM_Cursor_Pagination::encode(
                    'leads',
                    array('sort_value' => $next_sort_value, 'id' => absint($last_lead->id)),
                    $cursor_scope
                );
            }
        }

        wp_send_json_success(array(
            'html' => $html,
            'total_items' => $total_items,
            'total_pages' => max(1, $total_pages),
            'current_page' => $page,
            'pagination_mode' => $use_cursor_query ? 'cursor' : 'offset',
            'next_cursor' => $next_cursor,
            'metric_stats' => $metric_stats,
            'pending_calls_count' => self::get_pending_calls_count(),
            'unread_whatsapp_count' => self::get_unread_whatsapp_count(),
            'stale_lead_count' => IDLM_Admin_Lead_Notifications::get_stale_lead_count()
        ));
    }

    public static function ajax_get_kanban_leads() {
        check_ajax_referer('idlm_admin_nonce', 'nonce');
        if (!self::current_user_has_rep_access()) {
            wp_send_json_error(array('message' => 'Permission denied.'));
        }

        global $wpdb;
        $table_leads = $wpdb->prefix . 'idlm_leads';

        $where = array();
        $where[] = "is_duplicate = 0"; // Exclude duplicate leads

        // Rep permissions check
        $current_user_id = get_current_user_id();
        $is_admin = current_user_can('manage_options');
        $restricted_user_id = IDLM_Lead_Access_Policy::restriction_user_id();

        if ($restricted_user_id) {
            $where[] = $wpdb->prepare("assigned_rep_id = %d", $restricted_user_id);
        }

        // Apply filters
        $date_columns = array(
            'assigned_at' => 'assigned_at', 'created_at' => 'created_at', 'handoff_at' => 'handoff_at',
            'updated_at' => 'updated_at', 'qualified_at' => 'qualified_at', 'conversion_time' => 'conversion_time',
        );
        $post_date_basis = isset($_POST['date_basis']) ? sanitize_key(wp_unslash($_POST['date_basis'])) : 'assigned_at';
        $date_column = $date_columns[$post_date_basis] ?? 'assigned_at';
        $post_date_range = isset($_POST['date_range']) ? sanitize_key(wp_unslash($_POST['date_range'])) : '';
        $date_conditions = array();
        if (!empty($post_date_range)) {
            $timezone = wp_timezone();
            $now = new DateTime('now', $timezone);
            if ($post_date_range === 'today') {
                // phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- REL007-SQL-0207: ajax_get_kanban_leads(): authenticated nonce/capability/object handlers; fixed date/sort/trigger maps; filter fragments prepared at construction, dynamic IN lists use integers/placeholders; export/task cursors are bounded and scoped.
                $date_conditions[] = $wpdb->prepare("{$date_column} >= %s", $now->format('Y-m-d 00:00:00'));
                // phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            } elseif ($post_date_range === 'yesterday') {
                $yesterday = clone $now;
                $yesterday->modify('-1 day');
                $date_conditions[] = $wpdb->prepare(
                    // phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- REL007-SQL-0208: ajax_get_kanban_leads(): authenticated nonce/capability/object handlers; fixed date/sort/trigger maps; filter fragments prepared at construction, dynamic IN lists use integers/placeholders; export/task cursors are bounded and scoped.
                    "{$date_column} >= %s AND {$date_column} <= %s",
                    // phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                    $yesterday->format('Y-m-d 00:00:00'),
                    $yesterday->format('Y-m-d 23:59:59')
                );
            } elseif ($post_date_range === 'week') {
                $start = clone $now;
                $start->modify('monday this week');
                // phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- REL007-SQL-0209: ajax_get_kanban_leads(): authenticated nonce/capability/object handlers; fixed date/sort/trigger maps; filter fragments prepared at construction, dynamic IN lists use integers/placeholders; export/task cursors are bounded and scoped.
                $date_conditions[] = $wpdb->prepare("{$date_column} >= %s", $start->format('Y-m-d 00:00:00'));
                // phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            } elseif ($post_date_range === 'month') {
                // phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- REL007-SQL-0210: ajax_get_kanban_leads(): authenticated nonce/capability/object handlers; fixed date/sort/trigger maps; filter fragments prepared at construction, dynamic IN lists use integers/placeholders; export/task cursors are bounded and scoped.
                $date_conditions[] = $wpdb->prepare("{$date_column} >= %s", $now->format('Y-m-01 00:00:00'));
                // phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            } elseif ($post_date_range === 'last_7_days') {
                $start = clone $now;
                $start->modify('-6 days');
                // phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- REL007-SQL-0211: ajax_get_kanban_leads(): authenticated nonce/capability/object handlers; fixed date/sort/trigger maps; filter fragments prepared at construction, dynamic IN lists use integers/placeholders; export/task cursors are bounded and scoped.
                $date_conditions[] = $wpdb->prepare("{$date_column} >= %s", $start->format('Y-m-d 00:00:00'));
                // phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            } elseif ($post_date_range === 'last_30_days') {
                $start = clone $now;
                $start->modify('-29 days');
                // phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- REL007-SQL-0212: ajax_get_kanban_leads(): authenticated nonce/capability/object handlers; fixed date/sort/trigger maps; filter fragments prepared at construction, dynamic IN lists use integers/placeholders; export/task cursors are bounded and scoped.
                $date_conditions[] = $wpdb->prepare("{$date_column} >= %s", $start->format('Y-m-d 00:00:00'));
                // phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            } elseif ($post_date_range === 'last_month') {
                $start = clone $now;
                $start->modify('first day of last month');
                $end = clone $now;
                $end->modify('last day of last month');
                $date_conditions[] = $wpdb->prepare(
                    // phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- REL007-SQL-0213: ajax_get_kanban_leads(): authenticated nonce/capability/object handlers; fixed date/sort/trigger maps; filter fragments prepared at construction, dynamic IN lists use integers/placeholders; export/task cursors are bounded and scoped.
                    "{$date_column} >= %s AND {$date_column} <= %s",
                    // phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                    $start->format('Y-m-d 00:00:00'),
                    $end->format('Y-m-d 23:59:59')
                );
            } elseif ($post_date_range === 'year' || $post_date_range === 'this_year') {
                // phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- REL007-SQL-0214: ajax_get_kanban_leads(): authenticated nonce/capability/object handlers; fixed date/sort/trigger maps; filter fragments prepared at construction, dynamic IN lists use integers/placeholders; export/task cursors are bounded and scoped.
                $date_conditions[] = $wpdb->prepare("{$date_column} >= %s", $now->format('Y-01-01 00:00:00'));
                // phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            }
        }

        $post_source      = isset($_POST['source']) ? sanitize_text_field(wp_unslash($_POST['source'])) : '';
        $post_rep_id      = isset($_POST['rep_id']) ? sanitize_text_field(wp_unslash($_POST['rep_id'])) : '';
        $post_call_status = isset($_POST['call_status']) ? sanitize_text_field(wp_unslash($_POST['call_status'])) : '';
        $post_quality     = isset($_POST['quality']) ? sanitize_text_field(wp_unslash($_POST['quality'])) : '';
        $post_attention   = isset($_POST['attention']) ? sanitize_key(wp_unslash($_POST['attention'])) : '';
        $post_search      = isset($_POST['search']) ? sanitize_text_field(wp_unslash($_POST['search'])) : '';
        $post_sort        = isset($_POST['sort']) ? sanitize_key(wp_unslash($_POST['sort'])) : 'newest';
        $sort_options     = array(
            'newest'   => 'l.created_at DESC, l.id DESC',
            'oldest'   => 'l.created_at ASC, l.id ASC',
            'name_asc' => 'l.first_name ASC, l.last_name ASC, l.id DESC',
            'name_desc' => 'l.first_name DESC, l.last_name DESC, l.id DESC',
        );
        $order_by = isset($sort_options[$post_sort]) ? $sort_options[$post_sort] : $sort_options['newest'];

        // New and Contacted remain valid lifecycle stages but are intentionally omitted from this sales board.
        $where[] = "l.stage NOT IN ('New', 'Contacted')";

        if (!empty($post_source)) {
            $where[] = $wpdb->prepare("source = %s", $post_source);
        }
        if ($post_rep_id !== '') {
            $where[] = $wpdb->prepare("assigned_rep_id = %d", intval($post_rep_id));
        }
        if (!empty($post_call_status)) {
            $where[] = $wpdb->prepare("call_status = %s", $post_call_status);
        }
        if (!empty($post_quality)) {
            if ('Qualified Awaiting Assignment' === $post_quality) {
                $where[] = "lead_quality = 'Qualified' AND handoff_status IN ('awaiting_manager', 'exception')";
            } elseif ('Routing Exception' === $post_quality) {
                $where[] = "lead_quality = 'Qualified' AND handoff_status = 'exception'";
            } elseif ('Qualified Plan Received' === $post_quality) {
                $where[] = "lead_quality = 'Qualified' AND floor_plan_received = 1";
            } elseif ('Qualified Plan Pending' === $post_quality) {
                $where[] = "lead_quality = 'Qualified' AND COALESCE(floor_plan_received, 0) = 0";
            } elseif ('Pending' === $post_quality) {
                $where[] = "(lead_quality = '' OR lead_quality IS NULL)";
            } else {
                $where[] = $wpdb->prepare("lead_quality = %s", $post_quality);
            }
        }
        if ('stale_updates' === $post_attention) {
            $cutoff = IDLM_Admin_Lead_Notifications::get_stale_lead_cutoff();
            $where[] = $wpdb->prepare(
                // phpcs:disable WordPress.DB.PreparedSQL.NotPrepared -- REL007-SQL-0215: ajax_get_kanban_leads(): authenticated nonce/capability/object handlers; fixed date/sort/trigger maps; filter fragments prepared at construction, dynamic IN lists use integers/placeholders; export/task cursors are bounded and scoped.
                "l.assigned_rep_id > 0 AND l.stage NOT IN ('Won', 'Lost') AND COALESCE(l.assigned_at, l.created_at) < %s AND " . IDLM_Admin_Lead_Notifications::get_no_recent_assigned_rep_action_sql($cutoff, 'l'),
                // phpcs:enable WordPress.DB.PreparedSQL.NotPrepared
                $cutoff
            );
        }
        if (!empty($post_search)) {
            $search = '%' . $wpdb->esc_like($post_search) . '%';
            $where[] = $wpdb->prepare("(first_name LIKE %s OR last_name LIKE %s OR email LIKE %s OR phone LIKE %s)", $search, $search, $search, $search);
        }

        $where = array_merge($where, $date_conditions);
        $where_clause = !empty($where) ? "WHERE " . implode(" AND ", $where) : "";

        $stages = get_option('idlm_custom_stages', array('New', 'Contacted', 'Qualified', 'Quote Shared', 'Negotiation', 'Won', 'Lost'));
        $stages = array_values(array_diff($stages, array('New', 'Contacted')));
        if (isset($_POST['per_column']) && !is_string($_POST['per_column'])) {
            wp_send_json_error(array('message' => 'Invalid pipeline loading mode.'), 400);
        }
        $column_mode = isset($_POST['per_column']) && '1' === sanitize_text_field(wp_unslash($_POST['per_column']));
        if (isset($_POST['column_stage']) && !is_string($_POST['column_stage'])) {
            wp_send_json_error(array('message' => 'Invalid pipeline column.'), 400);
        }
        $column_stage = isset($_POST['column_stage']) ? sanitize_text_field(wp_unslash($_POST['column_stage'])) : '';
        if ('' !== $column_stage && (!$column_mode || !in_array($column_stage, $stages, true))) {
            wp_send_json_error(array('message' => 'Invalid pipeline column.'), 400);
        }
        $board_limit = $column_mode ? 20 : 100;

        // Bind every batch to the live actor, filters, sort and requested column.
        $board_scope = array('where' => $where, 'sort' => $post_sort);
        if ($column_mode) {
            $board_scope['column_stage'] = $column_stage;
            $board_scope['limit'] = $board_limit;
        }
        $board_offset = 0;
        if (isset($_POST['cursor']) && !is_string($_POST['cursor'])) {
            wp_send_json_error(array('message' => 'Invalid board page.'), 400);
        }
        if (!empty($_POST['cursor'])) {
            if ($column_mode && '' === $column_stage) {
                wp_send_json_error(array('message' => 'Choose a pipeline column before loading more leads.'), 400);
            }
            // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Signed opaque token is validated by Cursor_Pagination before any SQL use.
            $position = IDLM_Cursor_Pagination::decode(wp_unslash($_POST['cursor']), 'kanban_board', $board_scope);
            if (is_wp_error($position) || !isset($position['offset']) || !is_int($position['offset']) || $position['offset'] < 0) {
                wp_send_json_error(array('message' => 'Board page expired. Refresh the board.'), 400);
            }
            $board_offset = $position['offset'];
        }

        // Query leads
        $now_mysql = current_time('mysql');
        $stale_cutoff = IDLM_Admin_Lead_Notifications::get_stale_lead_cutoff();
        $needs_update_sql = $wpdb->prepare(
            // phpcs:disable WordPress.DB.PreparedSQL.NotPrepared -- REL007-SQL-0216: ajax_get_kanban_leads(): authenticated nonce/capability/object handlers; fixed date/sort/trigger maps; filter fragments prepared at construction, dynamic IN lists use integers/placeholders; export/task cursors are bounded and scoped.
            "CASE WHEN l.assigned_rep_id > 0
                AND l.stage NOT IN ('Won', 'Lost')
                AND COALESCE(l.assigned_at, l.created_at) < %s
                AND " . IDLM_Admin_Lead_Notifications::get_no_recent_assigned_rep_action_sql($stale_cutoff, 'l') . "
             THEN 1 ELSE 0 END",
            // phpcs:enable WordPress.DB.PreparedSQL.NotPrepared
            $stale_cutoff
        );
        $stage_pages = array();
        $leads = array();
        $board_more = false;
        $query_stages = $column_mode ? ('' !== $column_stage ? array($column_stage) : $stages) : array('');
        foreach ($query_stages as $query_stage) {
            $card_where = $where_clause;
            if ($column_mode) {
                $card_where .= $wpdb->prepare(' AND l.stage = %s', $query_stage);
            }
            // phpcs:disable PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- REL007-SQL-0217: Authenticated, scoped filters and allowlisted sort; column equality is prepared above; limit/offset are bounded server values or signed positions.
            $batch = $wpdb->get_results($wpdb->prepare(
                "SELECT l.id, l.first_name, l.last_name, l.email, l.phone, l.budget, l.quote_value, l.deal_value, l.quote_file, l.property_location, l.source, l.stage, l.call_status, l.assigned_rep_id, l.is_duplicate,
                    u.display_name as rep_name,
                    {$needs_update_sql} as needs_update,
                    (SELECT COUNT(t.id) FROM {$wpdb->prefix}idlm_tasks t WHERE t.lead_id = l.id AND t.status = 'pending') as pending_tasks_count,
                    (SELECT COUNT(t.id) FROM {$wpdb->prefix}idlm_tasks t WHERE t.lead_id = l.id AND t.status = 'pending' AND t.due_date IS NOT NULL AND t.due_date < %s) as overdue_tasks_count
             FROM {$table_leads} l
             LEFT JOIN {$wpdb->users} u ON l.assigned_rep_id = u.ID
             {$card_where} ORDER BY {$order_by} LIMIT %d OFFSET %d",
            // phpcs:enable PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                $now_mysql,
                $board_limit + 1,
                $board_offset
            ));
            if ($wpdb->last_error) { wp_send_json_error(array('message' => 'Unable to load the board. Retry shortly.'), 503); }
            $has_more = count($batch) > $board_limit;
            $batch = array_slice($batch, 0, $board_limit);
            $leads = array_merge($leads, $batch);
            $board_more = $board_more || $has_more;
            if ($column_mode) {
                $column_scope = $board_scope;
                $column_scope['column_stage'] = $query_stage;
                $stage_pages[$query_stage] = array(
                    'offset' => $board_offset,
                    'size' => count($batch),
                    'has_more' => $has_more,
                    'next_cursor' => $has_more ? IDLM_Cursor_Pagination::encode('kanban_board', array('offset' => $board_offset + $board_limit), $column_scope) : '',
                );
            }
        }

        // Group leads by stage
        $grouped_leads = array();
        foreach ($stages as $stage) {
            $grouped_leads[$stage] = array();
        }
        if (!empty($leads)) {
            foreach ($leads as $lead) {
                $stage = !empty($lead->stage) ? $lead->stage : 'New';
                if (!isset($grouped_leads[$stage])) {
                    $grouped_leads[$stage] = array();
                }
                $grouped_leads[$stage][] = $lead;
            }
        }

        // Board summary cards describe the same filtered population as the board.
        $stats_conditions = $where;
        $stats_conditions[] = 'l.is_duplicate = 0';
        $stats_where = 'WHERE ' . implode(' AND ', $stats_conditions);
        $active_stage_sql = "l.stage NOT IN ('Won', 'Lost')";

        // Every dynamic filter fragment is fixed SQL or was individually prepared when constructed above.
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
        $summary = $wpdb->get_row(
            // phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- REL007-SQL-0218: ajax_get_kanban_leads(): authenticated nonce/capability/object handlers; fixed date/sort/trigger maps; filter fragments prepared at construction, dynamic IN lists use integers/placeholders; export/task cursors are bounded and scoped.
            "SELECT
                COUNT(*) AS total_filtered
             FROM {$table_leads} l {$stats_where}"
            // phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        );

        // Current-month wins stay within the board's active filters, including its date range.
        $monthly_won_conditions = $where;
        $month_start = new DateTime('first day of this month 00:00:00', wp_timezone());
        $next_month_start = clone $month_start;
        $next_month_start->modify('first day of next month');
        $monthly_won_conditions[] = "l.stage = 'Won'";
        $monthly_won_conditions[] = $wpdb->prepare('l.conversion_time >= %s', $month_start->format('Y-m-d H:i:s'));
        $monthly_won_conditions[] = $wpdb->prepare('l.conversion_time < %s', $next_month_start->format('Y-m-d H:i:s'));
        $monthly_won_where = 'WHERE ' . implode(' AND ', $monthly_won_conditions);
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
        $monthly_won_summary = $wpdb->get_row(
            // phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- REL007-SQL-0219: ajax_get_kanban_leads(): authenticated nonce/capability/object handlers; fixed date/sort/trigger maps; filter fragments prepared at construction, dynamic IN lists use integers/placeholders; export/task cursors are bounded and scoped.
            "SELECT COUNT(*) AS won_current_month, SUM(COALESCE(l.deal_value, 0)) AS won_revenue_current_month
             FROM {$table_leads} l {$monthly_won_where}"
            // phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        );

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
        $pipeline_rows = $wpdb->get_results(
            // phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- REL007-SQL-0220: ajax_get_kanban_leads(): authenticated nonce/capability/object handlers; fixed date/sort/trigger maps; filter fragments prepared at construction, dynamic IN lists use integers/placeholders; export/task cursors are bounded and scoped.
            "SELECT l.stage, COUNT(*) AS stage_count, SUM(COALESCE(l.quote_value, 0)) AS quote_total, SUM(COALESCE(l.deal_value, 0)) AS deal_total, SUM(GREATEST(COALESCE(l.quote_value, 0), 0)) AS stage_quote_total, SUM(GREATEST(COALESCE(l.deal_value, 0), 0)) AS stage_deal_total
             FROM {$table_leads} l {$stats_where}
             GROUP BY l.stage"
            // phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        );
        $stage_total_mappings = self::get_kanban_stage_total_mappings($stages);
        $stage_summaries = array();
        $open_pipeline_value = 0.0;
        foreach ($pipeline_rows as $pipeline_row) {
            $stage_summaries[$pipeline_row->stage] = array('count' => (int) $pipeline_row->stage_count, 'quote_value' => (float) $pipeline_row->stage_quote_total, 'deal_value' => (float) $pipeline_row->stage_deal_total);
            if (in_array($pipeline_row->stage, array('Won', 'Lost'), true)) { continue; }
            $total_source = isset($stage_total_mappings[$pipeline_row->stage]) ? $stage_total_mappings[$pipeline_row->stage] : 'none';
            if ('quote_value' === $total_source) {
                $open_pipeline_value += floatval($pipeline_row->quote_total);
            } elseif ('deal_value' === $total_source) {
                $open_pipeline_value += floatval($pipeline_row->deal_total);
            }
        }

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
        $followup_leads = $wpdb->get_results(
            // phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- REL007-SQL-0221: ajax_get_kanban_leads(): authenticated nonce/capability/object handlers; fixed date/sort/trigger maps; filter fragments prepared at construction, dynamic IN lists use integers/placeholders; export/task cursors are bounded and scoped.
            "SELECT l.call_status, l.callback_datetime, l.updated_at, l.created_at
             FROM {$table_leads} l {$stats_where} AND {$active_stage_sql}"
            // phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        );
        $reminder_rules = self::get_reminder_rules();
        $today_end = strtotime(current_time('Y-m-d 23:59:59'));
        $followups_due = 0;
        foreach ($followup_leads as $followup_lead) {
            $callback_due = 'Call Back Requested' === $followup_lead->call_status && !empty($followup_lead->callback_datetime) && strtotime($followup_lead->callback_datetime) <= $today_end;
            $overdue = self::check_lead_overdue_status($followup_lead, $reminder_rules);
            if ($callback_due || ($overdue && !empty($overdue['overdue']))) {
                $followups_due++;
            }
        }

        wp_send_json_success(array(
            'leads' => $grouped_leads,
            'page' => array('offset' => $board_offset, 'size' => count($leads), 'has_more' => $board_more, 'next_cursor' => !$column_mode && $board_more ? IDLM_Cursor_Pagination::encode('kanban_board', array('offset' => $board_offset + $board_limit), $board_scope) : ''),
            'stage_pages' => $stage_pages,
            'stage_summaries' => $stage_summaries,
            'stats' => array(
                'total_filtered' => $summary ? intval($summary->total_filtered) : 0,
                'followups_due' => $followups_due,
                'open_pipeline_value' => round($open_pipeline_value, 2),
                'won_current_month' => $monthly_won_summary ? intval($monthly_won_summary->won_current_month) : 0,
                'won_revenue_current_month' => $monthly_won_summary ? round(floatval($monthly_won_summary->won_revenue_current_month), 2) : 0,
                'stale_lead_count' => IDLM_Admin_Lead_Notifications::get_stale_lead_count(true),
            ),
        ));
    }

    public static function ajax_get_total_leads_count() {
        check_ajax_referer('idlm_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Permission denied. Only administrators can perform this action.'));
        }

        global $wpdb;
        $table_leads = $wpdb->prefix . 'idlm_leads';
        
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
        $total_leads = intval($wpdb->get_var("SELECT COUNT(id) FROM {$table_leads}"));

        wp_send_json_success(array('count' => $total_leads));
    }

}
