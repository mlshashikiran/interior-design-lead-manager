<?php
if (!defined('ABSPATH')) {
    exit;
}

class IDLM_Scheduler {
    public static function init() {
        // Register the activation schedule setup
        add_action('wp', array(__CLASS__, 'setup_schedules'));

        add_action(IDLM_Audience_Dispatch::HOOK, array('IDLM_Audience_Dispatch', 'resume'), 10, 3);

        // Action Scheduler hooks
        add_action('idlm_daily_audience_sync', array('IDLM_Audiences', 'run_batch_sync'));
        add_action('idlm_daily_audience_sync', array('IDLM_Telemetry', 'prune_logs'));
        add_action('idlm_hourly_assignment_fallback', array('IDLM_Integrations', 'run_assignment_fallback'));
        add_action('idlm_hourly_assignment_fallback', array('IDLM_DB', 'roll_forward_presales_tasks'));

        // WP Cron fallbacks
        add_action('idlm_daily_audience_sync_cron', array('IDLM_Audiences', 'run_batch_sync'));
        add_action('idlm_daily_audience_sync_cron', array('IDLM_Telemetry', 'prune_logs'));
        add_action('idlm_hourly_assignment_fallback_cron', array('IDLM_Integrations', 'run_assignment_fallback'));
        add_action('idlm_hourly_assignment_fallback_cron', array('IDLM_DB', 'roll_forward_presales_tasks'));
    }

    public static function setup_schedules() {
        add_filter('cron_schedules', array(__CLASS__, 'cron_schedules'));
        if (function_exists('as_next_scheduled_action')) {
            if (!as_next_scheduled_action(IDLM_Lead_Outbox::HOOK)) { as_schedule_recurring_action(time() + 60, 60, IDLM_Lead_Outbox::HOOK); }
        } elseif (!wp_next_scheduled(IDLM_Lead_Outbox::HOOK)) {
            wp_schedule_event(time() + 60, 'idlm_minute', IDLM_Lead_Outbox::HOOK);
        }
        if (function_exists('as_next_scheduled_action')) {
            if (!as_next_scheduled_action(IDLM_Lead_Connector::HOOK)) { as_schedule_recurring_action(time()+60,60,IDLM_Lead_Connector::HOOK); }
        } elseif (!wp_next_scheduled(IDLM_Lead_Connector::HOOK)) {
            wp_schedule_event(time()+60,'idlm_minute',IDLM_Lead_Connector::HOOK);
        }
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
            if (!as_next_scheduled_action('idlm_hourly_assignment_fallback')) {
                as_schedule_recurring_action(time(), HOUR_IN_SECONDS, 'idlm_hourly_assignment_fallback');
            }
        } else {
            // 2. WP Cron Fallback
            if (!wp_next_scheduled('idlm_daily_audience_sync_cron')) {
                $timezone = wp_timezone();
                $target = new DateTime('tomorrow 02:00:00', $timezone);
                $timestamp = $target->getTimestamp();
                
                wp_schedule_event($timestamp, 'daily', 'idlm_daily_audience_sync_cron');
            }
            if (!wp_next_scheduled('idlm_hourly_assignment_fallback_cron')) {
                wp_schedule_event(time(), 'hourly', 'idlm_hourly_assignment_fallback_cron');
            }
        }
    }

    public static function cron_schedules($schedules) {
        $schedules['idlm_minute'] = array('interval'=>60, 'display'=>'Lead intent recovery every minute');
        return $schedules;
    }

    public static function clear_schedules() {
        if (function_exists('as_unschedule_all_actions')) { as_unschedule_all_actions(IDLM_Lead_Connector::HOOK); }
        wp_clear_scheduled_hook(IDLM_Lead_Connector::HOOK);
        if (function_exists('as_unschedule_all_actions')) { as_unschedule_all_actions(IDLM_Lead_Outbox::HOOK); }
        wp_clear_scheduled_hook(IDLM_Lead_Outbox::HOOK);
        if (function_exists('as_unschedule_all_actions')) { as_unschedule_all_actions(IDLM_Audience_Dispatch::HOOK); }
        wp_clear_scheduled_hook(IDLM_Audience_Dispatch::HOOK);
        if (function_exists('as_unschedule_all_actions')) {
            as_unschedule_all_actions('idlm_daily_audience_sync');
            as_unschedule_all_actions('idlm_hourly_assignment_fallback');
        }
        wp_clear_scheduled_hook('idlm_daily_audience_sync_cron');
        wp_clear_scheduled_hook('idlm_hourly_assignment_fallback_cron');
    }
}
