<?php
if (!defined('ABSPATH')) {
    exit;
}

class IDLM_Scheduler {
    public static function init() {
        // Register the activation schedule setup
        add_action('wp', array(__CLASS__, 'setup_schedules'));

        // Action Scheduler hooks
        add_action('idlm_daily_audience_sync', array('IDLM_Audiences', 'run_batch_sync'));
        // WP Cron fallbacks
        add_action('idlm_daily_audience_sync_cron', array('IDLM_Audiences', 'run_batch_sync'));
    }

    /**
     * Set up recurring schedules (Action Scheduler preferred, WP Cron fallback)
     */
    public static function setup_schedules() {
        // 1. Action Scheduler
        if (function_exists('as_next_scheduled_action')) {
            if (!as_next_scheduled_action('idlm_daily_audience_sync')) {
                // Calculate next 2:00 AM timestamp
                $timezone = wp_timezone();
                $now = new DateTime('now', $timezone);
                $target = new DateTime('tomorrow 02:00:00', $timezone);
                $timestamp = $target->getTimestamp();

                as_schedule_recurring_action($timestamp, DAY_IN_SECONDS, 'idlm_daily_audience_sync');
            }
        } else {
            // 2. WP Cron Fallback
            if (!wp_next_scheduled('idlm_daily_audience_sync_cron')) {
                $timezone = wp_timezone();
                $target = new DateTime('tomorrow 02:00:00', $timezone);
                $timestamp = $target->getTimestamp();
                
                wp_schedule_event($timestamp, 'daily', 'idlm_daily_audience_sync_cron');
            }
        }
    }

    /**
     * Clear schedules on plugin deactivation
     */
    public static function clear_schedules() {
        if (function_exists('as_unschedule_all_actions')) {
            as_unschedule_all_actions('idlm_daily_audience_sync');
        }
        wp_clear_scheduled_hook('idlm_daily_audience_sync_cron');
    }
}
