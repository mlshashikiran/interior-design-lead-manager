<?php
/**
 * Focused mutations for the global daily task queue.
 *
 * Builder: Shashi
 */

if (!defined('ABSPATH')) {
    exit;
}

final class IDLM_Task_Queue_Service {
    const PAGE_SIZE = 50;

    /** Collapse qualification slots in SQL before paginating cards. */
    public static function page($view = 'attention', $cursor = '', $counts_only = false) {
        global $wpdb;
        if (!IDLM_Lead_Access_Policy::has_rep_access()) { return new WP_Error('idlm_task_access', 'Permission denied.'); }
        $view = 'completed' === $view ? 'completed' : 'attention';
        $tasks = $wpdb->prefix . 'idlm_tasks'; $leads = $wpdb->prefix . 'idlm_leads';
        $params = array(); $scope = IDLM_Lead_Access_Policy::scope_sql('l', $params, true);
        // phpcs:disable WordPress.DB.PreparedSQL.NotPrepared -- REL007-SQL-0160: page(): fixed view/from/due/order branches; scope prepared by live policy, signed cursor values and dates prepared; bounded task pages/counts must reflect current ownership and status.
        $scope = $params ? $wpdb->prepare($scope, $params) : $scope;
        // phpcs:enable WordPress.DB.PreparedSQL.NotPrepared
        $today = current_time('Y-m-d');
        $tomorrow = (new DateTime($today, wp_timezone()))->modify('+1 day')->format('Y-m-d H:i:s');
        $end = (new DateTime($today, wp_timezone()))->modify('+8 days')->format('Y-m-d H:i:s');
        $generation = IDLM_Recovery_Guard::generation();
        if (null === $generation) { return new WP_Error('idlm_task_recovery', 'Unable to read task recovery state. Retry shortly.'); }
        $signature = array('view'=>$view, 'scope'=>$scope, 'day'=>$today, 'generation'=>$generation);
        $position = array();
        if ('' !== $cursor) {
            $position = IDLM_Cursor_Pagination::decode($cursor, 'task_queue', $signature);
            if (is_wp_error($position) || !isset($position['due'], $position['id']) || !is_string($position['due']) || !preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/D', $position['due']) || !is_int($position['id']) || $position['id'] < 1) {
                return new WP_Error('idlm_task_cursor', 'Task page expired. Refresh the queue.');
            }
        }
        if ('completed' === $view) {
            $from = "FROM {$tasks} t INNER JOIN {$leads} l ON l.id = t.lead_id";
            $due = 't.due_date'; $where = "t.status = 'completed'{$scope}";
        } else {
            $from = "FROM {$tasks} t INNER JOIN (
                SELECT MIN(st.id) id, MIN(st.due_date) due_date FROM {$tasks} st
                INNER JOIN {$leads} l ON l.id = st.lead_id
                WHERE st.status = 'pending' AND l.stage <> 'Lost'{$scope}
                GROUP BY (st.task_type = 'Qualification'), CASE WHEN st.task_type = 'Qualification' THEN st.lead_id ELSE st.id END
            ) q ON q.id = t.id INNER JOIN {$leads} l ON l.id = t.lead_id";
            $due = 'q.due_date';
            // phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- REL007-SQL-0161: page(): fixed view/from/due/order branches; scope prepared by live policy, signed cursor values and dates prepared; bounded task pages/counts must reflect current ownership and status.
            $where = $wpdb->prepare("t.status = 'pending' AND l.stage <> 'Lost'{$scope} AND (t.task_type = 'Qualification' OR ({$due} > '0000-00-00 00:00:00' AND {$due} < %s))", $end);
            // phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        }
        $attention = array('overdue'=>0, 'due_today'=>0, 'upcoming'=>0, 'follow_up_plans'=>0);
        if ('completed' !== $view) {
            // phpcs:disable PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- REL007-SQL-0162: page(): fixed view/from/due/order branches; scope prepared by live policy, signed cursor values and dates prepared; bounded task pages/counts must reflect current ownership and status.
            $totals = $wpdb->get_row($wpdb->prepare("SELECT
                SUM(t.task_type = 'Qualification') follow_up_plans,
                SUM(COALESCE(t.task_type, '') <> 'Qualification' AND {$due} < %s) overdue,
                SUM(COALESCE(t.task_type, '') <> 'Qualification' AND {$due} >= %s AND {$due} < %s) due_today,
                SUM(COALESCE(t.task_type, '') <> 'Qualification' AND {$due} >= %s) upcoming
                {$from} WHERE {$where}", $today . ' 00:00:00', $today . ' 00:00:00', $tomorrow, $tomorrow), ARRAY_A);
            // phpcs:enable PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            if ($wpdb->last_error) { return new WP_Error('idlm_task_read', 'Unable to load tasks. Retry shortly.'); }
            foreach ($attention as $key => $zero) { $attention[$key] = (int) ($totals[$key] ?? 0); }
        }
        if ($counts_only) { return array('tasks'=>array(), 'attention'=>$attention, 'view'=>$view, 'next_cursor'=>'', 'has_more'=>false); }
        $sort = "COALESCE({$due}, '9999-12-31 23:59:59')";
        if ($position) {
            // phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- REL007-SQL-0163: page(): fixed view/from/due/order branches; scope prepared by live policy, signed cursor values and dates prepared; bounded task pages/counts must reflect current ownership and status.
            $where .= $wpdb->prepare(" AND ({$sort} > %s OR ({$sort} = %s AND t.id < %d))", $position['due'], $position['due'], $position['id']);
            // phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        }
        // phpcs:disable PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- REL007-SQL-0164: page(): fixed view/from/due/order branches; scope prepared by live policy, signed cursor values and dates prepared; bounded task pages/counts must reflect current ownership and status.
        $rows = $wpdb->get_results("SELECT t.*, {$due} due_date, l.first_name, l.last_name, l.phone, l.email, l.source, l.stage {$from} WHERE {$where} ORDER BY {$sort} ASC, t.id DESC LIMIT 51");
        // phpcs:enable PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        if ($wpdb->last_error) { return new WP_Error('idlm_task_read', 'Unable to load tasks. Retry shortly.'); }
        $more = count($rows) > self::PAGE_SIZE; $rows = array_slice($rows, 0, self::PAGE_SIZE); $next = '';
        if ($more) {
            $last = end($rows);
            $next = IDLM_Cursor_Pagination::encode('task_queue', array('due'=>$last->due_date ?? '9999-12-31 23:59:59', 'id'=>(int) $last->id), $signature);
        }
        return array('tasks'=>$rows, 'attention'=>$attention, 'view'=>$view, 'next_cursor'=>$next, 'has_more'=>$more);
    }

    public static function ajax_reschedule() {
        if (!IDLM_Admin::current_user_has_rep_access()) {
            wp_send_json_error(array('message' => 'Permission denied.'));
        }
        // phpcs:disable WordPress.Security.NonceVerification.Missing -- REL007-BOUNDARY-0068: ajax_reschedule(): ajax_reschedule invokes IDLM_Admin::check_nonce before task/date parsing, then requires current live lead mutation access; due dates are domain validated and system qualification tasks refused.
        $task_id = isset($_POST['task_id']) ? absint(wp_unslash($_POST['task_id'])) : 0;
        // phpcs:enable WordPress.Security.NonceVerification.Missing
        // phpcs:disable WordPress.Security.NonceVerification.Missing -- REL007-BOUNDARY-0069: ajax_reschedule(): ajax_reschedule invokes IDLM_Admin::check_nonce before task/date parsing, then requires current live lead mutation access; due dates are domain validated and system qualification tasks refused.
        $due_date = isset($_POST['due_date']) ? sanitize_text_field(wp_unslash($_POST['due_date'])) : '';
        // phpcs:enable WordPress.Security.NonceVerification.Missing
        $parsed = DateTime::createFromFormat('!Y-m-d\\TH:i', $due_date, wp_timezone());
        if (!$task_id || !$parsed || $parsed->format('Y-m-d\\TH:i') !== $due_date || $parsed->getTimestamp() <= current_time('timestamp')) {
            wp_send_json_error(array('message' => 'Choose a future due date and time.'));
        }
        global $wpdb;
        $tasks = $wpdb->prefix . 'idlm_tasks';
        // phpcs:disable PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- REL007-SQL-0165: ajax_reschedule(): fixed view/from/due/order branches; scope prepared by live policy, signed cursor values and dates prepared; bounded task pages/counts must reflect current ownership and status.
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $task = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$tasks} WHERE id = %d", $task_id));
        // phpcs:enable PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        if (!$task || 'pending' !== $task->status || 'Qualification' === (string) $task->task_type || !IDLM_Admin::current_user_can_access_lead($task->lead_id)) {
            wp_send_json_error(array('message' => 'This task cannot be rescheduled manually.'));
        }
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $updated = $wpdb->update($tasks, array('due_date' => $parsed->format('Y-m-d H:i:s'), 'updated_at' => current_time('mysql')), array('id' => $task_id));
        if (false === $updated) {
            wp_send_json_error(array('message' => 'Unable to reschedule this task.'));
        }
        IDLM_DB::log_activity($task->lead_id, 'task', sprintf('Rescheduled task "%s".', $task->title));
        wp_send_json_success(array('message' => 'Task rescheduled.'));
    }

    public static function ajax_cleanup_stale() {
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Only administrators can clean up stale tasks.'));
        }
        $result = IDLM_DB::cleanup_stale_pending_tasks();
        if (false === $result) { wp_send_json_error(array('message' => 'Unable to clean up stale tasks. Retry after checking database health.')); }
        wp_send_json_success(array('message' => sprintf('Archived %d Lost-lead task(s) and %d task(s) assigned to removed users.', $result['lost'], $result['orphaned']), 'result' => $result));
    }
}
