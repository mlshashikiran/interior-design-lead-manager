<?php
/** Transactional lead deletion and orphan auditing. @author Shashi */
if (!defined('ABSPATH')) { exit; }

final class IDLM_Lead_Deletion_Service {
    private static function dependent_tables() {
        return array('idlm_lead_activity_log', 'idlm_lead_state_events', 'idlm_capi_logs', 'idlm_tasks', 'idlm_lead_touchpoints', 'idlm_call_logs', 'idlm_lead_outbox');
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
            'lead' => array('idlm_inbound_submission_reviews', 'idlm_tasks', 'idlm_lead_touchpoints', 'idlm_lead_outbox', 'idlm_leads'),
        );
    }

    /**
     * Delete all runtime CRM data atomically after rechecking the confirmation count.
     *
     * @param string|null $expected_lead_count Administrator-entered lead count.
     * @return array|WP_Error Reset result manifest or an error after rollback.
     */
    public static function reset_all($expected_lead_count = null) {
        if (!IDLM_Recovery_Guard::begin_restore()) { return new WP_Error('idlm_reset_busy', 'CRM recovery is busy. Retry after active work finishes.'); }
        try { return self::reset_all_work($expected_lead_count); } finally { IDLM_Recovery_Guard::end_restore(); }
    }

    private static function reset_all_work($expected_lead_count = null) {
        global $wpdb;

        $tables = array($wpdb->prefix . 'options');
        foreach (self::reset_manifest() as $suffixes) {
            foreach ($suffixes as $suffix) { $tables[] = $wpdb->prefix . $suffix; }
        }
        foreach ($tables as $table) {
            // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- REL007-SQL-0252: reset_all_work(): dependent/reset/relation manifests fix table and foreign-key identifiers; IN lists contain absint values only; checked transactional erasure and orphan verification require live state.
            $engine = $wpdb->get_var($wpdb->prepare('SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = %s', $table));
            // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
            if ('INNODB' !== strtoupper((string) $engine)) {
                return new WP_Error('idlm_reset_engine', 'CRM reset requires all runtime tables to exist and use InnoDB. No data was removed.');
            }
        }

        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery -- REL007-SQL-0253: reset_all_work(): dependent/reset/relation manifests fix table and foreign-key identifiers; IN lists contain absint values only; checked transactional erasure and orphan verification require live state.
        if (false === $wpdb->query('START TRANSACTION')) {
        // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery
            return new WP_Error('idlm_reset_transaction', 'Unable to start the CRM reset transaction.');
        }

        $leads_table = $wpdb->prefix . 'idlm_leads';
        // Lock the verified lead set so an inbound insert cannot silently widen the reset.
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
        $actual_count = $wpdb->get_var("SELECT COUNT(id) FROM {$leads_table} FOR UPDATE");
        if (null === $actual_count || (null !== $expected_lead_count && (string) $expected_lead_count !== (string) intval($actual_count))) {
            // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery -- REL007-SQL-0254: reset_all_work(): dependent/reset/relation manifests fix table and foreign-key identifiers; IN lists contain absint values only; checked transactional erasure and orphan verification require live state.
            $wpdb->query('ROLLBACK');
            // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery
            return new WP_Error('idlm_reset_count_mismatch', 'Verification failed. The entered count does not match the actual number of leads.');
        }

        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery -- REL007-SQL-0255: reset_all_work(): dependent/reset/relation manifests fix table and foreign-key identifiers; IN lists contain absint values only; checked transactional erasure and orphan verification require live state.
        if (!IDLM_Private_Files::erase_all()) { $wpdb->query('ROLLBACK'); return new WP_Error('idlm_reset_files', 'Private file cleanup could not be prepared. Reset rolled back.'); }
        // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery
        $deleted_rows = array();
        foreach (self::reset_manifest() as $category => $table_suffixes) {
            $deleted_rows[$category] = 0;
            foreach ($table_suffixes as $table_suffix) {
                $table = $wpdb->prefix . $table_suffix;
                // Static table names come only from reset_manifest().
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
                $deleted = $wpdb->query("DELETE FROM {$table}");
                if (false === $deleted) {
                    // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery -- REL007-SQL-0256: reset_all_work(): dependent/reset/relation manifests fix table and foreign-key identifiers; IN lists contain absint values only; checked transactional erasure and orphan verification require live state.
                    $wpdb->query('ROLLBACK');
                    // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery
                    return new WP_Error('idlm_reset_failed', 'CRM reset failed and all database changes were rolled back.');
                }
                $deleted_rows[$category] += intval($deleted);
            }
        }

        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- REL007-SQL-0257: reset_all_work(): dependent/reset/relation manifests fix table and foreign-key identifiers; IN lists contain absint values only; checked transactional erasure and orphan verification require live state.
        $runtime_options = $wpdb->get_col($wpdb->prepare(
        // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
            "SELECT option_name FROM {$wpdb->prefix}options WHERE option_name IN ('idlm_audience_sync_queue', 'idlm_audience_scan', 'idlm_legacy_quote_migration_version', 'idlm_quotation_snapshot_migration_version') OR option_name LIKE %s OR option_name LIKE %s OR option_name LIKE %s OR option_name LIKE %s OR option_name LIKE %s OR option_name LIKE %s",
            $wpdb->esc_like('idlm_connector_attribution_') . '%',
            $wpdb->esc_like('idlm_interakt_batch_') . '%',
            $wpdb->esc_like('idlm_interakt_dispatch_') . '%',
            $wpdb->esc_like('idlm_capi_retry_') . '%',
            $wpdb->esc_like(IDLM_CAPI_Delivery::PREFIX) . '%',
            $wpdb->esc_like(IDLM_Audience_Dispatch::PREFIX) . '%'
        ));
        if ($wpdb->last_error) {
            // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery -- REL007-SQL-0258: reset_all_work(): dependent/reset/relation manifests fix table and foreign-key identifiers; IN lists contain absint values only; checked transactional erasure and orphan verification require live state.
            $wpdb->query('ROLLBACK');
            // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery
            return new WP_Error('idlm_reset_options', 'Unable to read queued CRM runtime state. Reset rolled back.');
        }
        foreach ($runtime_options as $option_name) {
            // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery -- REL007-SQL-0259: reset_all_work(): dependent/reset/relation manifests fix table and foreign-key identifiers; IN lists contain absint values only; checked transactional erasure and orphan verification require live state.
            if (false === $wpdb->delete($wpdb->prefix . 'options', array('option_name' => $option_name))) {
            // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery
                $wpdb->query('ROLLBACK');
                return new WP_Error('idlm_reset_options', 'Unable to remove queued CRM runtime state. Reset rolled back.');
            }
        }

        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery -- REL007-SQL-0260: reset_all_work(): dependent/reset/relation manifests fix table and foreign-key identifiers; IN lists contain absint values only; checked transactional erasure and orphan verification require live state.
        if (!IDLM_Recovery_Guard::advance_generation() || false === $wpdb->query('COMMIT')) {
        // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery
            $wpdb->query('ROLLBACK');
            return new WP_Error('idlm_reset_commit', 'CRM reset failed and all database changes were rolled back.');
        }

        // Preserve integration rules and all other configuration; only queued
        // lead IDs and quotation migration markers are runtime reset state.
        foreach ($runtime_options as $option_name) { wp_cache_delete($option_name, 'options'); }
        wp_cache_delete(IDLM_Recovery_Guard::GENERATION, 'options');
        wp_cache_delete('alloptions', 'options');
        wp_cache_delete('notoptions', 'options');

        IDLM_Private_Files::cleanup(50);
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
     * Active guarded callbacks block reset. The committed generation rejects stale
     * queued callbacks even when cancellation cannot recall them.
     *
     * @return array<string,array<int,string>>
     */
    private static function cancel_scheduled_lead_jobs() {
        $action_scheduler_hooks = array(
            array('idlm_async_capi_upload_event', ''),
            array('idlm_async_interakt_track_event', 'idlm-interakt'),
            array('idlm_async_interakt_batch_event', 'idlm-interakt'),
            array(IDLM_Audience_Dispatch::HOOK, ''),
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
        $result = IDLM_Recovery_Guard::run(static function() use ($lead_ids) { return self::delete_many_work($lead_ids); });
        return false === $result ? new WP_Error('idlm_delete_busy', 'CRM recovery is busy. Please retry.') : $result;
    }

    private static function delete_many_work($lead_ids) {
        global $wpdb;
        $ids = array_values(array_unique(array_filter(array_map('absint', (array) $lead_ids))));
        if (!$ids) { return new WP_Error('idlm_invalid_lead_ids', 'No valid leads were selected.'); }
        $id_sql = implode(',', $ids);
        // phpcs:disable PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- REL007-SQL-0261: delete_many_work(): dependent/reset/relation manifests fix table and foreign-key identifiers; IN lists contain absint values only; checked transactional erasure and orphan verification require live state.
        $external = $wpdb->get_col("SELECT id FROM {$wpdb->prefix}idlm_leads WHERE duplicate_of_id IN ({$id_sql}) AND id NOT IN ({$id_sql})");
        // phpcs:enable PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        if ($wpdb->last_error) { return new WP_Error('idlm_delete_read', 'Unable to verify linked leads. No data was removed.'); }
        if ($external) { return new WP_Error('idlm_linked_duplicates', 'Delete linked duplicate records before deleting their canonical lead.'); }
        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- REL007-SQL-0262: delete_many_work(): dependent/reset/relation manifests fix table and foreign-key identifiers; IN lists contain absint values only; checked transactional erasure and orphan verification require live state.
        $engine = $wpdb->get_var($wpdb->prepare('SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = %s', $wpdb->prefix . 'idlm_inbound_submission_reviews'));
        // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
        if ($wpdb->last_error || 'INNODB' !== strtoupper((string) $engine)) { return new WP_Error('idlm_delete_review_engine', 'Review erasure requires InnoDB. No data was removed.'); }
        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- REL007-SQL-0263: delete_many_work(): dependent/reset/relation manifests fix table and foreign-key identifiers; IN lists contain absint values only; checked transactional erasure and orphan verification require live state.
        if (false === $wpdb->query('START TRANSACTION')) { return new WP_Error('idlm_delete_transaction', 'Unable to start the lead deletion transaction.'); }
        if (!IDLM_Audience_Dispatch::erase_leads($ids)) { $wpdb->query('ROLLBACK'); return new WP_Error('idlm_delete_audience', 'Audience work erasure failed. Deletion rolled back.'); }
        if (!IDLM_Lead_Connector::erase_attribution($ids)) { $wpdb->query('ROLLBACK'); return new WP_Error('idlm_delete_connector', 'Connector attribution erasure failed. Deletion rolled back.'); }
        if (!IDLM_CAPI_Delivery::erase_leads($ids)) { $wpdb->query('ROLLBACK'); return new WP_Error('idlm_delete_capi', 'CAPI delivery evidence erasure failed. Deletion rolled back.'); }
        if (!IDLM_Identity_Review::erase_leads($ids)) { $wpdb->query('ROLLBACK'); return new WP_Error('idlm_delete_review', 'Identity review erasure failed. Deletion rolled back.'); }
        if (!IDLM_Private_Files::erase_leads($ids)) { $wpdb->query('ROLLBACK'); return new WP_Error('idlm_delete_files', 'Private file cleanup could not be prepared. Deletion rolled back.'); }
        // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
        foreach ($ids as $id) {
            foreach (self::dependent_tables() as $suffix) {
                // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- REL007-SQL-0264: delete_many_work(): dependent/reset/relation manifests fix table and foreign-key identifiers; IN lists contain absint values only; checked transactional erasure and orphan verification require live state.
                if (false === $wpdb->delete($wpdb->prefix . $suffix, array('lead_id' => $id))) { $wpdb->query('ROLLBACK'); return new WP_Error('idlm_delete_failed', 'Lead deletion failed and was rolled back.'); }
                // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
            }
            // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- REL007-SQL-0265: delete_many_work(): dependent/reset/relation manifests fix table and foreign-key identifiers; IN lists contain absint values only; checked transactional erasure and orphan verification require live state.
            if (class_exists('IDLM_Quotations') && !IDLM_Quotations::delete_for_lead($id)) { $wpdb->query('ROLLBACK'); return new WP_Error('idlm_delete_failed', 'Quotation cleanup failed and was rolled back.'); }
            if (false === $wpdb->delete($wpdb->prefix . 'idlm_leads', array('id' => $id))) { $wpdb->query('ROLLBACK'); return new WP_Error('idlm_delete_failed', 'Lead deletion failed and was rolled back.'); }
            // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
        }
        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- REL007-SQL-0266: delete_many_work(): dependent/reset/relation manifests fix table and foreign-key identifiers; IN lists contain absint values only; checked transactional erasure and orphan verification require live state.
        if (false === $wpdb->query('COMMIT')) { $wpdb->query('ROLLBACK'); return new WP_Error('idlm_delete_commit', 'Lead deletion failed and was rolled back.'); }
        // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
        IDLM_Private_Files::cleanup(50);
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
            // phpcs:disable PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- REL007-SQL-0267: audit_orphans(): dependent/reset/relation manifests fix table and foreign-key identifiers; IN lists contain absint values only; checked transactional erasure and orphan verification require live state.
            $result[$label] = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$child} child LEFT JOIN {$parent} parent ON parent.id = child.{$foreign_key} WHERE child.{$foreign_key} > 0 AND parent.id IS NULL");
            // phpcs:enable PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            if ($repair && $result[$label]) {
                // phpcs:disable PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- REL007-SQL-0268: audit_orphans(): dependent/reset/relation manifests fix table and foreign-key identifiers; IN lists contain absint values only; checked transactional erasure and orphan verification require live state.
                $wpdb->query("DELETE child FROM {$child} child LEFT JOIN {$parent} parent ON parent.id = child.{$foreign_key} WHERE child.{$foreign_key} > 0 AND parent.id IS NULL");
                // phpcs:enable PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            }
        }
        return $result;
    }
}
