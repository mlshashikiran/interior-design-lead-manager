<?php
/**
 * Aggregate-only operational health and capacity signals.
 *
 * @package InteriorDesignLeadManager
 * @author Shashi
 */

if (!defined('ABSPATH')) {
    exit;
}

final class IDLM_Operational_Health {
    const QUEUE_WARNING_SECONDS = 300;
    const QUEUE_CRITICAL_SECONDS = 900;
    const FAILURE_CRITICAL_COUNT = 10;

    /**
     * Build a redacted, read-only health report.
     *
     * @param array|null $context Optional detected-state override for tests.
     * @return array
     */
    public static function report($context = null) {
        $context = is_array($context) ? $context : self::detect_context();
        $context = array_merge(array(
            'database_ready' => false,
            'core_schema_current' => false,
            'quotation_schema_current' => false,
            'scheduler_backend' => 'none',
            'recurring_jobs_scheduled' => false,
            'retention_scheduled' => false,
            'retention_failed' => false,
            'retention_continuation_missing' => false,
            'secrets_ready' => false,
            'secret_missing_count' => 0,
            'uploads_ready' => false,
            'cache_backend' => 'database',
            'queue_observable' => false,
            'queue_pending' => 0,
            'queue_failed' => 0,
            'queue_oldest_age_seconds' => null,
            'telemetry_errors_24h' => 0,
            'integration_failures_24h' => 0,
        ), $context);

        $queue_status = self::queue_status($context);
        $recent_faults = max(0, absint($context['telemetry_errors_24h']))
            + max(0, absint($context['integration_failures_24h']));
        $fault_status = self::count_status($recent_faults);

        $checks = array(
            self::check(
                'database',
                'Database connectivity',
                !empty($context['database_ready']) ? 'healthy' : 'critical',
                !empty($context['database_ready']) ? 'Reachable' : 'Unavailable',
                'Escalate immediately if WordPress cannot complete a read against the configured database.'
            ),
            self::check(
                'schema',
                'Plugin schema versions',
                !empty($context['core_schema_current']) && !empty($context['quotation_schema_current']) ? 'healthy' : 'critical',
                !empty($context['core_schema_current']) && !empty($context['quotation_schema_current']) ? 'Current' : 'Migration required',
                'Pause writes and follow the schema migration runbook when either independent schema version is behind.'
            ),
            self::check(
                'scheduler',
                'Recurring CRM jobs',
                !empty($context['recurring_jobs_scheduled']) ? 'healthy' : 'critical',
                self::scheduler_value($context),
                'Restore the configured Action Scheduler or WP-Cron recurring hooks before backlog grows.'
            ),
            self::check(
                'retention',
                'Sensitive-data retention job',
                !empty($context['retention_scheduled']) && empty($context['retention_failed']) && empty($context['retention_continuation_missing']) ? 'healthy' : 'critical',
                !empty($context['retention_continuation_missing']) ? 'Continuation stalled' : (!empty($context['retention_failed']) ? 'Last batch failed' : (!empty($context['retention_scheduled']) ? 'Scheduled' : 'Not scheduled')),
                'Restore the daily retention hook and any missing continuation, then verify bounded batches reach completion.'
            ),
            self::check(
                'secrets',
                'Enabled integration credentials',
                !empty($context['secrets_ready']) ? 'healthy' : 'critical',
                !empty($context['secrets_ready'])
                    ? 'Available'
                    : absint($context['secret_missing_count']) . ' incomplete',
                'Restore or rotate protected credentials; this check never exposes their values.'
            ),
            self::check(
                'storage',
                'WordPress upload storage',
                !empty($context['uploads_ready']) ? 'healthy' : 'critical',
                !empty($context['uploads_ready']) ? 'Writable' : 'Unavailable',
                'Restore writable WordPress media storage before accepting lead attachments.'
            ),
            self::check(
                'cache',
                'Read-model cache backend',
                'persistent' === $context['cache_backend'] ? 'healthy' : 'info',
                'persistent' === $context['cache_backend'] ? 'Persistent object cache' : 'Database transient fallback',
                'The transient fallback is correct for a single node; multi-node mode requires a proven shared object cache.'
            ),
            self::check(
                'queue',
                'Background queue',
                $queue_status,
                self::queue_value($context),
                'Investigate oldest pending work first, confirm worker health, and retry only idempotent actions.'
            ),
            self::check(
                'recent_faults',
                'Operational faults (24 hours)',
                $fault_status,
                $recent_faults . ' recorded',
                'Review aggregate telemetry and integration logs; do not copy sensitive payloads into incident notes.'
            ),
        );

        $counts = array('healthy' => 0, 'info' => 0, 'warning' => 0, 'critical' => 0);
        foreach ($checks as $check) {
            $counts[$check['status']]++;
        }

        return array(
            'status' => $counts['critical'] ? 'critical' : ($counts['warning'] ? 'warning' : 'healthy'),
            'checked_at' => current_time('mysql'),
            'counts' => $counts,
            'checks' => $checks,
            'signals' => array(
                'queue_pending' => max(0, absint($context['queue_pending'])),
                'queue_failed' => max(0, absint($context['queue_failed'])),
                'queue_oldest_age_seconds' => null === $context['queue_oldest_age_seconds']
                    ? null
                    : max(0, absint($context['queue_oldest_age_seconds'])),
                'telemetry_errors_24h' => max(0, absint($context['telemetry_errors_24h'])),
                'integration_failures_24h' => max(0, absint($context['integration_failures_24h'])),
            ),
        );
    }

    private static function check($key, $label, $status, $value, $guidance) {
        return array(
            'key' => $key,
            'label' => $label,
            'status' => $status,
            'value' => $value,
            'guidance' => $guidance,
        );
    }

    private static function scheduler_value($context) {
        if (empty($context['recurring_jobs_scheduled'])) {
            return 'Not scheduled';
        }
        return 'action_scheduler' === $context['scheduler_backend'] ? 'Action Scheduler' : 'WP-Cron fallback';
    }

    private static function queue_status($context) {
        if ('action_scheduler' !== $context['scheduler_backend']) {
            return 'info';
        }
        if (empty($context['queue_observable'])) {
            return 'warning';
        }

        $failed = max(0, absint($context['queue_failed']));
        $age = null === $context['queue_oldest_age_seconds'] ? 0 : max(0, absint($context['queue_oldest_age_seconds']));
        if ($failed >= self::FAILURE_CRITICAL_COUNT || $age >= self::QUEUE_CRITICAL_SECONDS) {
            return 'critical';
        }
        if ($failed > 0 || $age >= self::QUEUE_WARNING_SECONDS) {
            return 'warning';
        }
        return 'healthy';
    }

    private static function queue_value($context) {
        if ('action_scheduler' !== $context['scheduler_backend']) {
            return 'WP-Cron; backlog unavailable';
        }
        if (empty($context['queue_observable'])) {
            return 'Metrics unavailable';
        }

        $age = null === $context['queue_oldest_age_seconds']
            ? 'none pending'
            : self::human_duration(absint($context['queue_oldest_age_seconds'])) . ' oldest';
        return absint($context['queue_pending']) . ' pending / '
            . absint($context['queue_failed']) . ' failed (24h) / ' . $age;
    }

    private static function count_status($count) {
        $count = max(0, absint($count));
        if ($count >= self::FAILURE_CRITICAL_COUNT) {
            return 'critical';
        }
        return $count > 0 ? 'warning' : 'healthy';
    }

    private static function human_duration($seconds) {
        $seconds = max(0, absint($seconds));
        if ($seconds >= HOUR_IN_SECONDS) {
            return floor($seconds / HOUR_IN_SECONDS) . 'h';
        }
        if ($seconds >= MINUTE_IN_SECONDS) {
            return floor($seconds / MINUTE_IN_SECONDS) . 'm';
        }
        return $seconds . 's';
    }

    /** Detect booleans and aggregate counts only; never return configured values or paths. */
    private static function detect_context() {
        global $wpdb;

        $database_ready = false;
        if ($wpdb) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            $database_ready = '1' === (string) $wpdb->get_var('SELECT 1');
        }

        $action_scheduler = function_exists('as_next_scheduled_action');
        if ($action_scheduler) {
            $daily_scheduled = (bool) as_next_scheduled_action('idlm_daily_audience_sync');
            $hourly_scheduled = (bool) as_next_scheduled_action('idlm_hourly_assignment_fallback');
        } else {
            $daily_scheduled = (bool) wp_next_scheduled('idlm_daily_audience_sync_cron');
            $hourly_scheduled = (bool) wp_next_scheduled('idlm_hourly_assignment_fallback_cron');
        }

        $queue = self::queue_context($action_scheduler);
        $secret_state = self::secret_context();
        $upload_state = self::upload_storage_ready();
        $since = wp_date('Y-m-d H:i:s', time() - DAY_IN_SECONDS);
        $retention = (array) get_option(IDLM_Data_Retention::STATUS_OPTION, array());

        return array_merge(array(
            'database_ready' => $database_ready,
            'core_schema_current' => defined('IDLM_DB_VERSION')
                && IDLM_DB_VERSION === get_option('idlm_schema_version', ''),
            'quotation_schema_current' => class_exists('IDLM_Quotation_Schema')
                && IDLM_Quotation_Schema::is_current(),
            'scheduler_backend' => $action_scheduler ? 'action_scheduler' : 'wp_cron',
            'recurring_jobs_scheduled' => $daily_scheduled && $hourly_scheduled,
            'retention_scheduled' => (bool) wp_next_scheduled(IDLM_Data_Retention::HOOK),
            'retention_failed' => !empty($retention['last_error']),
            'retention_continuation_missing' => self::retention_continuation_missing($retention),
            'uploads_ready' => $upload_state,
            'cache_backend' => function_exists('wp_using_ext_object_cache') && wp_using_ext_object_cache()
                ? 'persistent'
                : 'database',
            'telemetry_errors_24h' => self::recent_count('idlm_telemetry_logs', "severity = 'error'", $since),
            'integration_failures_24h' => self::recent_count('idlm_capi_logs', "status = 'failed'", $since)
                + self::recent_count('idlm_audience_sync_logs', "status = 'failed'", $since),
        ), $secret_state, $queue);
    }

    private static function retention_continuation_missing($state) {
        if (!$state || !empty($state['complete'])) { return false; }
        $updated = !empty($state['updated_at']) && is_string($state['updated_at']) ? strtotime($state['updated_at'] . ' UTC') : false;
        if ($updated && time() - $updated < self::QUEUE_WARNING_SECONDS) { return false; }
        if (wp_next_scheduled(IDLM_Data_Retention::CONTINUE_HOOK)) { return false; }
        try {
            if (function_exists('as_has_scheduled_action') && as_has_scheduled_action(IDLM_Data_Retention::CONTINUE_HOOK, array(), 'idlm-retention')) { return false; }
        } catch (Throwable $error) {
            // A missing/unreadable queue cannot establish a runnable successor.
        }
        return true;
    }

    private static function secret_context() {
        $requirements = array(
            array('enabled' => true, 'values' => array(self::secret_present('idlm_webhook_api_key'))),
            array('enabled' => IDLM_Production_Security::is_mcp_enabled(), 'values' => array(self::secret_present('idlm_mcp_api_key'))),
            array('enabled' => '1' === (string) get_option('idlm_meta_capi_enabled', '0'), 'values' => array(
                get_option('idlm_meta_pixel_id', ''), IDLM_Secret_Store::get('idlm_meta_access_token', '', true),
            )),
            array('enabled' => '1' === (string) get_option('idlm_google_capi_enabled', '0'), 'values' => array(
                get_option('idlm_google_measurement_id', ''), IDLM_Secret_Store::get('idlm_google_api_secret', '', true),
            )),
            array('enabled' => '1' === (string) get_option('idlm_linkedin_capi_enabled', '0'), 'values' => array(
                get_option('idlm_linkedin_conversion_id', ''), IDLM_Secret_Store::get('idlm_linkedin_access_token', '', true),
            )),
            array('enabled' => '1' === (string) get_option('idlm_interakt_enabled', '0'), 'values' => array(
                IDLM_Secret_Store::get('idlm_interakt_webhook_secret', '', true),
            )),
            array('enabled' => '1' === (string) get_option('idlm_interakt_enabled', '0') && IDLM_Settings::notification_enabled('idlm_whatsapp_notifications_enabled'), 'values' => array(
                IDLM_Secret_Store::get('idlm_interakt_api_key', '', true),
            )),
            array('enabled' => '1' === (string) get_option('idlm_meta_webhook_enabled', '0'), 'values' => array(
                IDLM_Secret_Store::get('idlm_meta_webhook_verify_token', '', true), IDLM_Secret_Store::get('idlm_meta_webhook_app_secret', '', true),
            )),
        );

        $missing = 0;
        foreach ($requirements as $requirement) {
            if (empty($requirement['enabled'])) {
                continue;
            }
            foreach ($requirement['values'] as $value) {
                if (true !== $value && '' === trim((string) $value)) {
                    $missing++;
                }
            }
        }

        return array(
            'secrets_ready' => 0 === $missing,
            'secret_missing_count' => $missing,
        );
    }

    /** Check encrypted/environment-backed presence without migrating or rewriting options. */
    private static function secret_present($option_name) {
        if (IDLM_Secret_Store::is_environment_managed($option_name)) {
            return '' !== trim((string) IDLM_Secret_Store::get($option_name, ''));
        }

        $stored = (string) get_option($option_name, '');
        if ('' === $stored) {
            return false;
        }
        if (0 !== strpos($stored, IDLM_Secret_Store::PREFIX)) {
            return true;
        }
        return '' !== trim((string) IDLM_Secret_Store::get($option_name, ''));
    }

    private static function upload_storage_ready() {
        if (!function_exists('wp_upload_dir')) {
            return false;
        }
        $uploads = wp_upload_dir(null, false);
        return empty($uploads['error'])
            && !empty($uploads['basedir'])
            && is_dir($uploads['basedir'])
            // phpcs:disable WordPress.WP.AlternativeFunctions.file_system_operations_is_writable -- REL007-BOUNDARY-0040: upload_storage_ready(): read-only storage readiness check of the WordPress uploads directory; is_writable tests an existing local directory without initiating a credential-prompting filesystem transport.
            && is_writable($uploads['basedir']);
            // phpcs:enable WordPress.WP.AlternativeFunctions.file_system_operations_is_writable
    }

    private static function queue_context($action_scheduler) {
        global $wpdb;
        $default = array(
            'queue_observable' => false,
            'queue_pending' => 0,
            'queue_failed' => 0,
            'queue_oldest_age_seconds' => null,
        );
        if (!$action_scheduler || !$wpdb) {
            return $default;
        }

        $table = $wpdb->prefix . 'actionscheduler_actions';
        if (!self::table_exists($table)) {
            return $default;
        }

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
        $rows = $wpdb->get_results(
            // phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- REL007-SQL-0085: queue_context(): fixed operational table/condition call sites; current queue/health counts use bound cutoff values; metadata and live queue state require primary reads.
            "SELECT status, COUNT(*) AS action_count, MIN(scheduled_date_gmt) AS oldest_at
             FROM {$table}
             WHERE hook LIKE 'idlm\\_%'
               AND (
                    (status = 'pending' AND scheduled_date_gmt <= UTC_TIMESTAMP())
                    OR (status = 'failed' AND last_attempt_gmt >= DATE_SUB(UTC_TIMESTAMP(), INTERVAL 1 DAY))
               )
             GROUP BY status"
            // phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        );
        if (!is_array($rows)) {
            return $default;
        }

        $context = $default;
        $context['queue_observable'] = true;
        foreach ($rows as $row) {
            $status = isset($row->status) ? (string) $row->status : '';
            if ('pending' === $status) {
                $context['queue_pending'] = absint($row->action_count ?? 0);
                if (!empty($row->oldest_at)) {
                    $oldest = strtotime((string) $row->oldest_at . ' UTC');
                    $context['queue_oldest_age_seconds'] = false === $oldest ? null : max(0, time() - $oldest);
                }
            } elseif ('failed' === $status) {
                $context['queue_failed'] = absint($row->action_count ?? 0);
            }
        }
        return $context;
    }

    private static function recent_count($table_suffix, $condition, $since) {
        global $wpdb;
        if (!$wpdb) {
            return 0;
        }
        $table = $wpdb->prefix . $table_suffix;
        if (!self::table_exists($table)) {
            return 0;
        }
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
        return absint($wpdb->get_var($wpdb->prepare(
            // phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- REL007-SQL-0086: recent_count(): fixed operational table/condition call sites; current queue/health counts use bound cutoff values; metadata and live queue state require primary reads.
            "SELECT COUNT(*) FROM {$table} WHERE {$condition} AND created_at >= %s",
            // phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            $since
        )));
    }

    private static function table_exists($table) {
        global $wpdb;
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        return $wpdb && $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table)) === $table;
    }
}
