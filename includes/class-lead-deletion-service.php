<?php
/** Transactional lead deletion and orphan auditing. @author Shashi */
if (!defined('ABSPATH')) { exit; }

final class IDLM_Lead_Deletion_Service {
    private static function dependent_tables() {
        return array('idlm_lead_activity_log', 'idlm_lead_state_events', 'idlm_capi_logs', 'idlm_tasks', 'idlm_lead_touchpoints', 'idlm_call_logs');
    }

    public static function delete($lead_id) { return self::delete_many(array($lead_id)); }

    /**
     * Runtime data removed by the administrator CRM reset.
     *
     * Catalogue and configuration tables are deliberately absent. DELETE is
     * used instead of TRUNCATE so every table participates in one transaction
     * and lead AUTO_INCREMENT values are not recycled into stale async jobs.
     *
     * @return array<string,array<int,string>>
     */
    public static function reset_manifest() {
        return array(
            'quote' => array(
                'idlm_quote_revision_items',
                'idlm_quote_revision_sections',
                'idlm_quote_payment_milestones',
                'idlm_quote_status_history',
                'idlm_quote_revisions',
                'idlm_quotes',
                'idlm_number_sequences',
            ),
            'call' => array('idlm_call_logs'),
            'log' => array('idlm_lead_activity_log', 'idlm_lead_state_events', 'idlm_capi_logs', 'idlm_audience_sync_logs'),
            'telemetry' => array('idlm_telemetry_logs'),
            'routing' => array('idlm_routing_state'),
            'lead' => array('idlm_tasks', 'idlm_lead_touchpoints', 'idlm_leads'),
        );
    }

    /**
     * Delete all runtime CRM data atomically after rechecking the confirmation count.
     *
     * @param string|null $expected_lead_count Administrator-entered lead count.
     * @return array|WP_Error Reset result manifest or an error after rollback.
     */
    public static function reset_all($expected_lead_count = null) {
        global $wpdb;

        $tables = array($wpdb->prefix . 'options');
        foreach (self::reset_manifest() as $suffixes) {
            foreach ($suffixes as $suffix) { $tables[] = $wpdb->prefix . $suffix; }
        }
        foreach ($tables as $table) {
            $engine = $wpdb->get_var($wpdb->prepare('SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = %s', $table));
            if ('INNODB' !== strtoupper((string) $engine)) {
                return new WP_Error('idlm_reset_engine', 'CRM reset requires all runtime tables to exist and use InnoDB. No data was removed.');
            }
        }

        if (false === $wpdb->query('START TRANSACTION')) {
            return new WP_Error('idlm_reset_transaction', 'Unable to start the CRM reset transaction.');
        }

        $leads_table = $wpdb->prefix . 'idlm_leads';
        // Lock the verified lead set so an inbound insert cannot silently widen the reset.
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
        $actual_count = $wpdb->get_var("SELECT COUNT(id) FROM {$leads_table} FOR UPDATE");
        if (null === $actual_count || (null !== $expected_lead_count && (string) $expected_lead_count !== (string) intval($actual_count))) {
            $wpdb->query('ROLLBACK');
            return new WP_Error('idlm_reset_count_mismatch', 'Verification failed. The entered count does not match the actual number of leads.');
        }

        $deleted_rows = array();
        foreach (self::reset_manifest() as $category => $table_suffixes) {
            $deleted_rows[$category] = 0;
            foreach ($table_suffixes as $table_suffix) {
                $table = $wpdb->prefix . $table_suffix;
                // Static table names come only from reset_manifest().
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
                $deleted = $wpdb->query("DELETE FROM {$table}");
                if (false === $deleted) {
                    $wpdb->query('ROLLBACK');
                    return new WP_Error('idlm_reset_failed', 'CRM reset failed and all database changes were rolled back.');
                }
                $deleted_rows[$category] += intval($deleted);
            }
        }

        $runtime_options = $wpdb->get_col($wpdb->prepare(
            "SELECT option_name FROM {$wpdb->prefix}options WHERE option_name IN ('idlm_audience_sync_queue', 'idlm_legacy_quote_migration_version', 'idlm_quotation_snapshot_migration_version') OR option_name LIKE %s",
            $wpdb->esc_like('idlm_interakt_batch_') . '%'
        ));
        if ($wpdb->last_error) {
            $wpdb->query('ROLLBACK');
            return new WP_Error('idlm_reset_options', 'Unable to read queued CRM runtime state. Reset rolled back.');
        }
        foreach ($runtime_options as $option_name) {
            if (false === $wpdb->delete($wpdb->prefix . 'options', array('option_name' => $option_name))) {
                $wpdb->query('ROLLBACK');
                return new WP_Error('idlm_reset_options', 'Unable to remove queued CRM runtime state. Reset rolled back.');
            }
        }

        if (false === $wpdb->query('COMMIT')) {
            $wpdb->query('ROLLBACK');
            return new WP_Error('idlm_reset_commit', 'CRM reset failed and all database changes were rolled back.');
        }

        // Preserve integration rules and all other configuration; only queued
        // lead IDs and quotation migration markers are runtime reset state.
        foreach ($runtime_options as $option_name) { wp_cache_delete($option_name, 'options'); }
        wp_cache_delete('alloptions', 'options');
        wp_cache_delete('notoptions', 'options');

        $scheduled_jobs = self::cancel_scheduled_lead_jobs();
        IDLM_Read_Model_Cache::invalidate('crm_reset');

        return array(
            'confirmed_lead_count' => intval($actual_count),
            'deleted_rows' => $deleted_rows,
            'audience_queue_cleared' => true,
            'scheduled_jobs' => $scheduled_jobs,
        );
    }

    /**
     * Cancel queued callbacks that can retain deleted lead IDs or lead payloads.
     *
     * Running callbacks cannot be recalled; preserving AUTO_INCREMENT prevents
     * them from ever resolving an old ID to a newly created lead.
     *
     * @return array<string,array<int,string>>
     */
    private static function cancel_scheduled_lead_jobs() {
        $action_scheduler_hooks = array(
            array('idlm_async_capi_upload_event', ''),
            array('idlm_async_interakt_track_event', 'idlm-interakt'),
            array('idlm_async_interakt_batch_event', 'idlm-interakt'),
        );
        $wp_cron_hooks = array_column($action_scheduler_hooks, 0);

        if (function_exists('as_unschedule_all_actions')) {
            foreach ($action_scheduler_hooks as $scheduled_hook) {
                as_unschedule_all_actions($scheduled_hook[0], array(), $scheduled_hook[1]);
            }
        }
        foreach ($wp_cron_hooks as $hook) {
            wp_unschedule_hook($hook);
        }

        return array(
            'action_scheduler' => array_column($action_scheduler_hooks, 0),
            'wp_cron' => $wp_cron_hooks,
        );
    }

    public static function delete_many($lead_ids) {
        global $wpdb;
        $ids = array_values(array_unique(array_filter(array_map('absint', (array) $lead_ids))));
        if (!$ids) { return new WP_Error('idlm_invalid_lead_ids', 'No valid leads were selected.'); }
        $id_sql = implode(',', $ids);
        $external = $wpdb->get_col("SELECT id FROM {$wpdb->prefix}idlm_leads WHERE duplicate_of_id IN ({$id_sql}) AND id NOT IN ({$id_sql})");
        if ($external) { return new WP_Error('idlm_linked_duplicates', 'Delete linked duplicate records before deleting their canonical lead.'); }
        if (false === $wpdb->query('START TRANSACTION')) { return new WP_Error('idlm_delete_transaction', 'Unable to start the lead deletion transaction.'); }
        foreach ($ids as $id) {
            foreach (self::dependent_tables() as $suffix) {
                if (false === $wpdb->delete($wpdb->prefix . $suffix, array('lead_id' => $id))) { $wpdb->query('ROLLBACK'); return new WP_Error('idlm_delete_failed', 'Lead deletion failed and was rolled back.'); }
            }
            if (class_exists('IDLM_Quotations') && !IDLM_Quotations::delete_for_lead($id)) { $wpdb->query('ROLLBACK'); return new WP_Error('idlm_delete_failed', 'Quotation cleanup failed and was rolled back.'); }
            if (false === $wpdb->delete($wpdb->prefix . 'idlm_leads', array('id' => $id))) { $wpdb->query('ROLLBACK'); return new WP_Error('idlm_delete_failed', 'Lead deletion failed and was rolled back.'); }
        }
        if (false === $wpdb->query('COMMIT')) { $wpdb->query('ROLLBACK'); return new WP_Error('idlm_delete_commit', 'Lead deletion failed and was rolled back.'); }
        IDLM_Read_Model_Cache::invalidate('lead_delete');
        return count($ids);
    }

    public static function audit_orphans($repair = false) {
        global $wpdb;
        $result = array();
        $relations = array();
        foreach (self::dependent_tables() as $suffix) {
            $relations[$suffix . '.lead_id'] = array($suffix, 'lead_id', 'idlm_leads');
        }
        $relations += array(
            'quote_revision_items.revision_id' => array('idlm_quote_revision_items', 'revision_id', 'idlm_quote_revisions'),
            'quote_revision_items.section_id' => array('idlm_quote_revision_items', 'section_id', 'idlm_quote_revision_sections'),
            'quote_revision_sections.revision_id' => array('idlm_quote_revision_sections', 'revision_id', 'idlm_quote_revisions'),
            'quote_payment_milestones.revision_id' => array('idlm_quote_payment_milestones', 'revision_id', 'idlm_quote_revisions'),
            'quote_status_history.quote_id' => array('idlm_quote_status_history', 'quote_id', 'idlm_quotes'),
            'quote_revisions.quote_id' => array('idlm_quote_revisions', 'quote_id', 'idlm_quotes'),
            'quotes.lead_id' => array('idlm_quotes', 'lead_id', 'idlm_leads'),
        );
        foreach ($relations as $label => $relation) {
            list($child_suffix, $foreign_key, $parent_suffix) = $relation;
            $child = $wpdb->prefix . $child_suffix;
            $parent = $wpdb->prefix . $parent_suffix;
            $result[$label] = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$child} child LEFT JOIN {$parent} parent ON parent.id = child.{$foreign_key} WHERE child.{$foreign_key} > 0 AND parent.id IS NULL");
            if ($repair && $result[$label]) {
                $wpdb->query("DELETE child FROM {$child} child LEFT JOIN {$parent} parent ON parent.id = child.{$foreign_key} WHERE child.{$foreign_key} > 0 AND parent.id IS NULL");
            }
        }
        return $result;
    }
}
