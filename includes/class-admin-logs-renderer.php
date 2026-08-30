<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Owns the read-only CAPI and audience log page queries and contexts.
 *
 * Builder: Shashi
 */
class IDLM_Admin_Logs_Renderer {
    public static function render_capi() {
        if (!current_user_can('manage_options')) {
            wp_die('Access Denied');
        }

        global $wpdb;
        // Static, bounded admin query; no request values are interpolated.
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $logs = $wpdb->get_results("
            SELECT c.*, l.first_name, l.last_name, l.email
            FROM {$wpdb->prefix}idlm_capi_logs c
            LEFT JOIN {$wpdb->prefix}idlm_leads l ON c.lead_id = l.id
            ORDER BY c.id DESC LIMIT 50
        ");

        IDLM_View::display('admin/logs/capi.php', array(
            'logs'        => is_array($logs) ? $logs : array(),
            'retry_nonce' => wp_create_nonce('idlm_admin_nonce'),
        ));
    }

    public static function render_audience() {
        if (!current_user_can('manage_options')) {
            wp_die('Access Denied');
        }

        global $wpdb;
        // Static, bounded admin query; no request values are interpolated.
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $logs = $wpdb->get_results("
            SELECT *
            FROM {$wpdb->prefix}idlm_audience_sync_logs
            ORDER BY id DESC LIMIT 50
        ");

        IDLM_View::display('admin/logs/audience.php', array(
            'logs' => is_array($logs) ? $logs : array(),
        ));
    }
}

