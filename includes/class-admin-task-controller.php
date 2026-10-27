<?php
/** Focused M6 responsibility owner. Builder: Shashi. */
if (!defined('ABSPATH')) { exit; }

final class IDLM_Admin_Task_Controller extends IDLM_Admin {

    public static function ajax_get_lead_tasks() {
        check_ajax_referer('idlm_admin_nonce', 'nonce');
        if (!self::current_user_has_rep_access()) {
            wp_send_json_error(array('message' => 'Permission denied.'));
        }

        $lead_id = isset($_POST['lead_id']) ? intval($_POST['lead_id']) : 0;
        if (!$lead_id) {
            wp_send_json_error(array('message' => 'Invalid Lead ID'));
        }
        if (!self::current_user_can_view_lead($lead_id)) {
            wp_send_json_error(array('message' => 'You cannot view tasks for this lead.'));
        }

        global $wpdb;
        $table_tasks = $wpdb->prefix . 'idlm_tasks';
        
        // phpcs:disable PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- REL007-SQL-0224: ajax_get_lead_tasks(): authenticated nonce/capability/object handlers; fixed date/sort/trigger maps; filter fragments prepared at construction, dynamic IN lists use integers/placeholders; export/task cursors are bounded and scoped.
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $tasks = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$table_tasks}
             WHERE lead_id = %d
             ORDER BY
                CASE
                    WHEN status = 'pending' AND due_date IS NOT NULL AND due_date <> '0000-00-00 00:00:00' AND due_date < %s THEN 0
                    WHEN status = 'pending' THEN 1
                    ELSE 2
                END ASC,
                CASE WHEN due_date IS NULL OR due_date = '0000-00-00 00:00:00' THEN 1 ELSE 0 END ASC,
                due_date ASC,
                id DESC",
        // phpcs:enable PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            $lead_id,
            current_time('mysql')
        ));

        // Format dates nicely
        foreach ($tasks as &$task) {
            $task->due_date_formatted = $task->due_date && $task->due_date !== '0000-00-00 00:00:00' ? self::format_datetime($task->due_date) : '';
            $task->due_date_raw = $task->due_date && $task->due_date !== '0000-00-00 00:00:00' ? wp_date('Y-m-d\TH:i', strtotime($task->due_date)) : '';
            $task->is_overdue = ($task->status === 'pending' && $task->due_date && $task->due_date !== '0000-00-00 00:00:00' && strtotime($task->due_date) < current_time('timestamp'));
        }

        wp_send_json_success(array('tasks' => $tasks));
    }

    public static function ajax_add_lead_task() {
        check_ajax_referer('idlm_admin_nonce', 'nonce');
        if (!self::current_user_has_rep_access()) {
            wp_send_json_error(array('message' => 'Permission denied.'));
        }

        $lead_id = isset($_POST['lead_id']) ? intval($_POST['lead_id']) : 0;
        $title = isset($_POST['title']) ? trim(sanitize_text_field(wp_unslash($_POST['title']))) : '';
        $due_date = isset($_POST['due_date']) ? sanitize_text_field(wp_unslash($_POST['due_date'])) : '';
        $priority = isset($_POST['priority']) ? sanitize_text_field(wp_unslash($_POST['priority'])) : 'Medium';
        $task_type = isset($_POST['task_type']) ? sanitize_text_field(wp_unslash($_POST['task_type'])) : 'General';

        if (!$lead_id || '' === $title) {
            wp_send_json_error(array('message' => 'Missing required fields.'));
        }
        if (strlen($title) > 255) {
            wp_send_json_error(array('message' => 'Task title must be 255 characters or fewer.'));
        }
        if (!in_array($priority, array('Low', 'Medium', 'High'), true)) {
            wp_send_json_error(array('message' => 'Invalid task priority.'));
        }
        if (!in_array($task_type, self::manual_task_types(), true)) {
            wp_send_json_error(array('message' => 'Invalid task type.'));
        }
        $formatted_due_date = self::normalize_manual_task_due_date($due_date);
        if (false === $formatted_due_date) {
            wp_send_json_error(array('message' => 'Please provide a valid due date and time.'));
        }
        if ($formatted_due_date && strtotime($formatted_due_date) <= current_time('timestamp')) {
            wp_send_json_error(array('message' => 'Please choose a future due date and time.'));
        }
        if (!self::current_user_can_access_lead($lead_id)) {
            wp_send_json_error(array('message' => 'You can add tasks only to leads currently assigned to you.'));
        }

        global $wpdb;
        $table_leads = $wpdb->prefix . 'idlm_leads';
        
        // phpcs:disable PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- REL007-SQL-0225: ajax_add_lead_task(): authenticated nonce/capability/object handlers; fixed date/sort/trigger maps; filter fragments prepared at construction, dynamic IN lists use integers/placeholders; export/task cursors are bounded and scoped.
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $lead = $wpdb->get_row($wpdb->prepare("SELECT assigned_rep_id, first_name, last_name FROM {$table_leads} WHERE id = %d", $lead_id));
        // phpcs:enable PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        if (!$lead) {
            wp_send_json_error(array('message' => 'Lead not found.'));
        }

        $assigned_rep_id = $lead->assigned_rep_id;

        $table_tasks = $wpdb->prefix . 'idlm_tasks';
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $result = $wpdb->insert($table_tasks, array(
            'lead_id' => $lead_id,
            'assigned_rep_id' => $assigned_rep_id,
            'title' => $title,
            'task_type' => $task_type,
            'priority' => $priority,
            'status' => 'pending',
            'due_date' => $formatted_due_date,
            'created_by' => get_current_user_id(),
            'created_at' => current_time('mysql'),
            'updated_at' => current_time('mysql')
        ));

        if ($result) {
            $task_id = $wpdb->insert_id;
            
            // Log in activity log
            $activity_details = sprintf('Added task: "%s" (Priority: %s, Due: %s)', $title, $priority, !empty($due_date) ? self::format_datetime($formatted_due_date) : 'No due date');
            IDLM_DB::log_activity($lead_id, 'task', $activity_details);

            wp_send_json_success(array('message' => 'Task added successfully', 'task_id' => $task_id));
        } else {
            wp_send_json_error(array('message' => 'Failed to insert task.'));
        }
    }

    public static function ajax_toggle_task_status() {
        check_ajax_referer('idlm_admin_nonce', 'nonce');
        if (!self::current_user_has_rep_access()) {
            wp_send_json_error(array('message' => 'Permission denied.'));
        }

        $task_id = isset($_POST['task_id']) ? intval($_POST['task_id']) : 0;
        $status = isset($_POST['status']) ? sanitize_key($_POST['status']) : 'pending';

        if (!$task_id) {
            wp_send_json_error(array('message' => 'Invalid Task ID'));
        }
        if (!in_array($status, array('pending', 'completed'), true)) {
            wp_send_json_error(array('message' => 'Invalid task status.'));
        }

        global $wpdb;
        $table_tasks = $wpdb->prefix . 'idlm_tasks';

        // phpcs:disable PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- REL007-SQL-0226: ajax_toggle_task_status(): authenticated nonce/capability/object handlers; fixed date/sort/trigger maps; filter fragments prepared at construction, dynamic IN lists use integers/placeholders; export/task cursors are bounded and scoped.
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $task = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table_tasks} WHERE id = %d", $task_id));
        // phpcs:enable PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        if (!$task) {
            wp_send_json_error(array('message' => 'Task not found.'));
        }
        if (!self::current_user_can_access_lead($task->lead_id)) {
            wp_send_json_error(array('message' => 'You can update tasks only for leads currently assigned to you.'));
        }
        if (self::is_system_qualification_task($task)) {
            wp_send_json_error(array('message' => 'Qualification tasks are updated automatically from outreach evidence and cannot be changed manually.'));
        }

        $completed_at = ($status === 'completed') ? current_time('mysql') : null;

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $result = $wpdb->update(
            $table_tasks,
            array(
                'status' => $status,
                'completed_at' => $completed_at,
                'updated_at' => current_time('mysql')
            ),
            array('id' => $task_id)
        );

        if ($result !== false) {
            // Log in activity log
            $activity_details = sprintf('Marked task "%s" as %s', $task->title, $status);
            IDLM_DB::log_activity($task->lead_id, 'task', $activity_details);

            wp_send_json_success(array('message' => 'Task status updated.', 'status' => $status));
        } else {
            wp_send_json_error(array('message' => 'Failed to update task status.'));
        }
    }

    public static function ajax_delete_lead_task() {
        check_ajax_referer('idlm_admin_nonce', 'nonce');
        if (!self::current_user_has_rep_access()) {
            wp_send_json_error(array('message' => 'Permission denied.'));
        }

        $task_id = isset($_POST['task_id']) ? intval($_POST['task_id']) : 0;
        if (!$task_id) {
            wp_send_json_error(array('message' => 'Invalid Task ID'));
        }

        global $wpdb;
        $table_tasks = $wpdb->prefix . 'idlm_tasks';

        // phpcs:disable PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- REL007-SQL-0227: ajax_delete_lead_task(): authenticated nonce/capability/object handlers; fixed date/sort/trigger maps; filter fragments prepared at construction, dynamic IN lists use integers/placeholders; export/task cursors are bounded and scoped.
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $task = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table_tasks} WHERE id = %d", $task_id));
        // phpcs:enable PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        if (!$task) {
            wp_send_json_error(array('message' => 'Task not found.'));
        }
        if (!self::current_user_can_access_lead($task->lead_id)) {
            wp_send_json_error(array('message' => 'You can delete tasks only for leads currently assigned to you.'));
        }
        if (self::is_system_qualification_task($task)) {
            wp_send_json_error(array('message' => 'Qualification tasks are managed automatically and cannot be deleted manually.'));
        }

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $result = $wpdb->delete($table_tasks, array('id' => $task_id));

        if ($result) {
            // Log in activity log
            $activity_details = sprintf('Deleted task: "%s"', $task->title);
            IDLM_DB::log_activity($task->lead_id, 'task', $activity_details);

            wp_send_json_success(array('message' => 'Task deleted successfully.'));
        } else {
            wp_send_json_error(array('message' => 'Failed to delete task.'));
        }
    }

    public static function manual_task_types() {
        return array_values(IDLM_State_Registry::manual_task_types());
    }

    public static function normalize_manual_task_due_date($due_date) {
        if ('' === $due_date) {
            return null;
        }
        $parsed = DateTime::createFromFormat('!Y-m-d\\TH:i', $due_date, wp_timezone());
        $errors = DateTime::getLastErrors();
        if (!$parsed || (is_array($errors) && ($errors['warning_count'] || $errors['error_count'])) || $parsed->format('Y-m-d\\TH:i') !== $due_date) {
            return false;
        }
        return $parsed->format('Y-m-d H:i:s');
    }

    public static function is_system_qualification_task($task) {
        return 'Qualification' === (string) ($task->task_type ?? '');
    }

    public static function ajax_get_rep_tasks() {
        check_ajax_referer('idlm_admin_nonce', 'nonce');
        $operation = isset($_POST['operation']) ? sanitize_key(wp_unslash($_POST['operation'])) : '';
        if ('reschedule' === $operation) {
            IDLM_Task_Queue_Service::ajax_reschedule();
        }
        if ('cleanup_stale' === $operation) {
            IDLM_Task_Queue_Service::ajax_cleanup_stale();
        }
        if (!self::current_user_has_rep_access()) {
            wp_send_json_error(array('message' => 'Permission denied.'));
        }

        $queue_deadline = microtime(true) + 5;
        $view = isset($_POST['view']) && is_scalar($_POST['view']) ? sanitize_key(wp_unslash($_POST['view'])) : 'attention';
        // phpcs:disable WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- REL007-BOUNDARY-0047: ajax_get_rep_tasks(): the registered authenticated handler checks the shared admin nonce/capabilities and live object policy; IDs are normalized/bounded, rep-studio maps filtered to eligible IDs, signed cursor decoded by Task_Queue_Service and uploads validated by Private_Files; CSV streams are bounded and staged before headers.
        $cursor = isset($_POST['cursor']) && is_string($_POST['cursor']) ? wp_unslash($_POST['cursor']) : '';
        // phpcs:enable WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
        if (isset($_POST['cursor']) && !is_string($_POST['cursor'])) { wp_send_json_error(array('message'=>'Invalid task page.'), 400); }
        $page = IDLM_Task_Queue_Service::page($view, $cursor, !empty($_POST['counts_only']));
        if (is_wp_error($page)) { wp_send_json_error(array('message'=>$page->get_error_message()), 400); }
        $view = $page['view']; $tasks = $page['tasks'];

        // Keep the six scheduled qualification tasks internally, but present one
        // actionable follow-up plan per lead for every representative.
        if ('completed' !== $view && $tasks) {
            $visible_tasks = array();
            $qualification_groups = array();
            foreach ($tasks as $task) {
                if ('Qualification' !== $task->task_type) {
                    $visible_tasks[] = $task;
                    continue;
                }
                $lead_id = absint($task->lead_id);
                if (!isset($qualification_groups[$lead_id])) {
                    $qualification_groups[$lead_id] = array(
                        'task' => $task,
                        'next_due' => $task->due_date,
                    );
                } elseif ($task->due_date && (!$qualification_groups[$lead_id]['next_due'] || strtotime($task->due_date) < strtotime($qualification_groups[$lead_id]['next_due']))) {
                    $qualification_groups[$lead_id]['next_due'] = $task->due_date;
                }
            }

            if ($qualification_groups) {
                foreach ($qualification_groups as $lead_id => $group) {
                    $summary = clone $group['task'];
                    $progress = IDLM_DB::get_followup_compliance($lead_id, true, $queue_deadline);
                    if (is_wp_error($progress)) { wp_send_json_error(array('message'=>$progress->get_error_message()), 503); }
                    $summary->id = 'qualification-summary-' . $lead_id;
                    $summary->title = 'Follow-up plan';
                    $summary->due_date = $group['next_due'];
                    $summary->is_qualification_summary = true;
                    $summary->complete_days = (int) $progress['complete_days'];
                    $summary->required_days = 6;
                    $summary->today_calls = (int) $progress['active_cycle']['calls'];
                    $summary->today_whatsapp = (int) $progress['active_cycle']['whatsapp'];
                    $summary->calls_target = (int) $progress['targets']['calls'];
                    $summary->whatsapp_target = (int) $progress['targets']['whatsapp'];
                    $visible_tasks[] = $summary;
                }
            }
            $tasks = $visible_tasks;
            usort($tasks, static function ($a, $b) {
                return strtotime($a->due_date ?: '9999-12-31') <=> strtotime($b->due_date ?: '9999-12-31');
            });
        }

        $today_start = (new DateTime(current_time('Y-m-d') . ' 00:00:00', wp_timezone()))->getTimestamp();
        $tomorrow_start = strtotime('+1 day', $today_start);
        $upcoming_end = strtotime('+8 days', $today_start);
        $attention = $page['attention'];
        $visible_tasks = array();

        // Format and group attention work. Undated backlog is deliberately not
        // shown as daily work; users can schedule it from the lead drawer.
        foreach ($tasks as &$task) {
            $task->lead_name = esc_html($task->first_name . ' ' . $task->last_name);
            $task->due_date_formatted = $task->due_date && $task->due_date !== '0000-00-00 00:00:00' ? self::format_datetime($task->due_date) : 'No due date';
            $task->is_overdue = ($task->status === 'pending' && $task->due_date && $task->due_date !== '0000-00-00 00:00:00' && strtotime($task->due_date) < current_time('timestamp'));
            $task->queue_group = '';
            if ('completed' === $view) {
                $visible_tasks[] = $task;
                continue;
            }
            if (!empty($task->is_qualification_summary)) {
                $task->queue_group = 'follow_up_plans';
            } elseif ($task->due_date) {
                $due_timestamp = strtotime($task->due_date);
                if ($due_timestamp < $today_start) {
                    $task->queue_group = 'overdue';
                } elseif ($due_timestamp < $tomorrow_start) {
                    $task->queue_group = 'due_today';
                } elseif ($due_timestamp < $upcoming_end) {
                    $task->queue_group = 'upcoming';
                }
            }
            if ($task->queue_group) {
                $visible_tasks[] = $task;
            }
        }

        wp_send_json_success(array('tasks' => $visible_tasks, 'attention' => $attention, 'view' => $view, 'next_cursor'=>$page['next_cursor'], 'has_more'=>$page['has_more']));
    }

}
