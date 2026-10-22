<?php
/**
 * Lead timeline and comment handlers behind the stable CRM AJAX facades.
 *
 * Builder: Shashi
 */
if (!defined('ABSPATH')) {
    exit;
}

final class IDLM_Admin_Activity_Controller extends IDLM_Admin {

    public static function ajax_get_lead_activity() {
        check_ajax_referer('idlm_admin_nonce', 'nonce');
        if (!self::current_user_has_rep_access()) {
            wp_send_json_error(array('message' => 'Permission denied.'));
        }
        
        $lead_id = isset($_POST['lead_id']) ? intval(wp_unslash($_POST['lead_id'])) : 0;
        if (!$lead_id) {
            wp_send_json_error(array('message' => 'Invalid Lead ID'));
        }
        if (!self::current_user_can_view_lead($lead_id)) {
            wp_send_json_error(array('message' => 'You can access only leads assigned to you.'));
        }

        global $wpdb;
        
        // Fetch activity logs
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $logs = $wpdb->get_results($wpdb->prepare(
            "SELECT a.*, u.display_name 
             FROM {$wpdb->prefix}idlm_lead_activity_log a 
             LEFT JOIN {$wpdb->users} u ON a.user_id = u.ID 
             WHERE a.lead_id = %d ORDER BY a.id DESC LIMIT 100",
            $lead_id
        ));

        // Include comments and protected Lost remarks in the Details tab sidebar.
        $comments = array();
        if (!empty($logs)) {
            foreach ($logs as $log) {
                if (in_array($log->activity_type, array('comment', 'lost_remark', 'whatsapp_reply'), true)) {
                    $comments[] = $log;
                }
            }
        }
        // Show only the recent 5 comments (3-5 comments limit)
        $recent_comments = array_slice($comments, 0, 5);

        // 1. Render all activities & comments (Full Timeline)
        ob_start();
        ?>
        <div class="idlm-timeline" style="margin-top:0;">
            <?php if (empty($logs)): ?>
                <div style="text-align: center; color: #64748b; padding: 20px 0;">No activity logs found for this lead.</div>
            <?php else: ?>
                <?php foreach ($logs as $log): 
                    $author = $log->display_name ? $log->display_name : 'System Pipeline';
                ?>
                    <div class="idlm-timeline-item">
                        <div class="idlm-timeline-meta" style="display: flex; justify-content: space-between; align-items: center;">
                            <div>
                                <strong><?php echo esc_html($author); ?></strong>
                                <span><?php echo esc_html(self::format_datetime($log->created_at)); ?></span>
                            </div>
                            <?php if ($log->activity_type === 'comment' && (current_user_can('manage_options') || get_current_user_id() == $log->user_id)): ?>
                                <div class="idlm-timeline-actions" style="font-size: 11px;">
                                    <a href="#" class="idlm-edit-comment-link" data-id="<?php echo esc_attr($log->id); ?>" style="color: var(--idlm-brand-gold); text-decoration: none; margin-right: 8px;">Edit</a>
                                    <a href="#" class="idlm-delete-comment-link" data-id="<?php echo esc_attr($log->id); ?>" style="color: #b5472e; text-decoration: none;">Delete</a>
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="idlm-timeline-details">
                            <?php // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Safely escaped and constructed by render_activity_details().
                            echo self::render_activity_details($log->details); ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
        <?php
        $full_html = ob_get_clean();

        // 2. Render only recent comments
        ob_start();
        ?>
        <div class="idlm-timeline" style="margin-top:0;">
            <?php if (empty($recent_comments)): ?>
                <div style="text-align: center; color: #64748b; padding: 20px 0;">No comments found for this lead.</div>
            <?php else: ?>
                <?php foreach ($recent_comments as $log): 
                    $author = $log->display_name ? $log->display_name : 'System Pipeline';
                ?>
                    <div class="idlm-timeline-item">
                        <div class="idlm-timeline-meta" style="display: flex; justify-content: space-between; align-items: center;">
                            <div>
                                <strong><?php echo esc_html($author); ?></strong>
                                <span><?php echo esc_html(self::format_datetime($log->created_at)); ?></span>
                            </div>
                            <?php if ($log->activity_type === 'comment' && (current_user_can('manage_options') || get_current_user_id() == $log->user_id)): ?>
                                <div class="idlm-timeline-actions" style="font-size: 11px;">
                                    <a href="#" class="idlm-edit-comment-link" data-id="<?php echo esc_attr($log->id); ?>" style="color: var(--idlm-brand-gold); text-decoration: none; margin-right: 8px;">Edit</a>
                                    <a href="#" class="idlm-delete-comment-link" data-id="<?php echo esc_attr($log->id); ?>" style="color: #b5472e; text-decoration: none;">Delete</a>
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="idlm-timeline-details">
                            <?php // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Safely escaped and constructed by render_activity_details().
                            echo self::render_activity_details($log->details); ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
        <?php
        $comments_html = ob_get_clean();

        wp_send_json_success(array(
            'html' => $full_html,
            'comments_html' => $comments_html
        ));
    }

    public static function ajax_save_activity_comment() {
        check_ajax_referer('idlm_admin_nonce', 'nonce');
        if (!self::current_user_has_rep_access()) {
            wp_send_json_error(array('message' => 'Permission denied.'));
        }

        $lead_id = isset($_POST['lead_id']) ? intval(wp_unslash($_POST['lead_id'])) : 0;
        $comment = isset($_POST['comment']) ? sanitize_textarea_field(wp_unslash($_POST['comment'])) : '';

        if (!$lead_id || empty($comment)) {
            wp_send_json_error(array('message' => 'Empty comment or invalid Lead ID'));
        }
        if (!self::current_user_can_access_lead($lead_id)) {
            wp_send_json_error(array('message' => 'You can comment only on leads currently assigned to you.'));
        }

        global $wpdb;
        $table_activity = $wpdb->prefix . 'idlm_lead_activity_log';
        $user_id = get_current_user_id();

        // Prevent duplicate comment within 15 seconds timeframe
        $now = current_time('mysql');
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
        $duplicate = $wpdb->get_var($wpdb->prepare(
            // phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- REL007-SQL-0187: ajax_save_activity_comment(): authenticated nonce/capability/object handlers; fixed date/sort/trigger maps; filter fragments prepared at construction, dynamic IN lists use integers/placeholders; export/task cursors are bounded and scoped.
            "SELECT COUNT(*) FROM {$table_activity} 
             WHERE lead_id = %d 
               AND user_id = %d 
               AND activity_type = 'comment' 
               AND details = %s 
               AND created_at >= DATE_SUB(%s, INTERVAL 15 SECOND)",
            // phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            $lead_id,
            $user_id,
            $comment,
            $now
        ));

        if ($duplicate > 0) {
            wp_send_json_success(array('message' => 'Comment added successfully'));
        } else {
            IDLM_DB::log_activity($lead_id, 'comment', $comment);
            wp_send_json_success(array('message' => 'Comment added successfully'));
        }
    }

    public static function ajax_edit_activity_comment() {
        check_ajax_referer('idlm_admin_nonce', 'nonce');
        if (!self::current_user_has_rep_access()) {
            wp_send_json_error(array('message' => 'Permission denied.'));
        }

        $log_id = isset($_POST['log_id']) ? intval(wp_unslash($_POST['log_id'])) : 0;
        $comment = isset($_POST['comment']) ? sanitize_textarea_field(wp_unslash($_POST['comment'])) : '';

        if (!$log_id || empty($comment)) {
            wp_send_json_error(array('message' => 'Invalid parameters.'));
        }

        global $wpdb;
        $table_activity = $wpdb->prefix . 'idlm_lead_activity_log';

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
        $log = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table_activity} WHERE id = %d", $log_id));
        if (!$log || $log->activity_type !== 'comment') {
            wp_send_json_error(array('message' => 'Comment not found.'));
        }

        if (!self::current_user_can_access_lead($log->lead_id)) {
            wp_send_json_error(array('message' => 'You cannot modify comments on this lead.'));
        }

        if (!current_user_can('manage_options') && get_current_user_id() != $log->user_id) {
            wp_send_json_error(array('message' => 'Unauthorized operation.'));
        }

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $updated = $wpdb->update($table_activity, array('details' => $comment), array('id' => $log_id));

        if ($updated !== false) {
            wp_send_json_success(array('message' => 'Comment updated successfully.'));
        }

        wp_send_json_error(array('message' => 'Failed to update comment.'));
    }

    public static function ajax_delete_activity_comment() {
        check_ajax_referer('idlm_admin_nonce', 'nonce');
        if (!self::current_user_has_rep_access()) {
            wp_send_json_error(array('message' => 'Permission denied.'));
        }

        $log_id = isset($_POST['log_id']) ? intval(wp_unslash($_POST['log_id'])) : 0;
        if (!$log_id) {
            wp_send_json_error(array('message' => 'Invalid parameters.'));
        }

        global $wpdb;
        $table_activity = $wpdb->prefix . 'idlm_lead_activity_log';

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
        $log = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table_activity} WHERE id = %d", $log_id));
        if (!$log || $log->activity_type !== 'comment') {
            wp_send_json_error(array('message' => 'Comment not found.'));
        }

        if (!self::current_user_can_access_lead($log->lead_id)) {
            wp_send_json_error(array('message' => 'You cannot modify comments on this lead.'));
        }

        if (!current_user_can('manage_options') && get_current_user_id() != $log->user_id) {
            wp_send_json_error(array('message' => 'Unauthorized operation.'));
        }

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $deleted = $wpdb->delete($table_activity, array('id' => $log_id));

        if ($deleted !== false) {
            wp_send_json_success(array('message' => 'Comment deleted successfully.'));
        }

        wp_send_json_error(array('message' => 'Failed to delete comment.'));
    }
}
