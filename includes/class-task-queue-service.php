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
    public static function ajax_reschedule() {
        if (!IDLM_Admin::current_user_has_rep_access()) {
            wp_send_json_error(array('message' => 'Permission denied.'));
        }
        $task_id = isset($_POST['task_id']) ? absint(wp_unslash($_POST['task_id'])) : 0;
        $due_date = isset($_POST['due_date']) ? sanitize_text_field(wp_unslash($_POST['due_date'])) : '';
        $parsed = DateTime::createFromFormat('!Y-m-d\\TH:i', $due_date, wp_timezone());
        if (!$task_id || !$parsed || $parsed->format('Y-m-d\\TH:i') !== $due_date || $parsed->getTimestamp() <= current_time('timestamp')) {
            wp_send_json_error(array('message' => 'Choose a future due date and time.'));
        }
        global $wpdb;
        $tasks = $wpdb->prefix . 'idlm_tasks';
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $task = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$tasks} WHERE id = %d", $task_id));
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
        wp_send_json_success(array('message' => sprintf('Archived %d Lost-lead task(s) and %d task(s) assigned to removed users.', $result['lost'], $result['orphaned']), 'result' => $result));
    }
}
