<?php
/**
 * Quotation persistence and aggregate retrieval.
 *
 * Builder: Shashi
 */

if (!defined('ABSPATH')) {
    exit;
}

final class IDLM_Quotation_Repository {
    /**
     * Resolve a quotation table name from the active WordPress prefix.
     *
     * @param string $name Table suffix without the idlm_ prefix.
     * @return string
     */
    public static function table($name) {
        global $wpdb;
        return $wpdb->prefix . 'idlm_' . $name;
    }

    /** Start an independent write transaction; unsupported engines never receive writes. */
    public static function begin_checked_transaction($scope = 'save') {
        global $wpdb;
        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- REL007-SQL-0087: begin_checked_transaction(): table() prefixes fixed suffixes; term/column and sort maps are closed allowlists; filters carry live lead scope, values/ID lists are prepared; current revision and transaction state must remain live.
        $autocommit = $wpdb->get_var('SELECT @@autocommit');
        if ($wpdb->last_error || 1 !== (int) $autocommit || false === $wpdb->query('SET TRANSACTION READ WRITE')) {
        // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
            return new WP_Error('idlm_quote_transaction_owner', 'Quotation changes require an independent database transaction. Retry outside the current transaction.');
        }
        // Engine metadata queries can establish a repeatable-read view before
        // the lead lock wait. Refreshing live user/role grants must see later commits.
        // This applies only to the next owned transaction, not the session default.
        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- REL007-SQL-0088: begin_checked_transaction(): table() prefixes fixed suffixes; term/column and sort maps are closed allowlists; filters carry live lead scope, values/ID lists are prepared; current revision and transaction state must remain live.
        if (false === $wpdb->query('SET TRANSACTION ISOLATION LEVEL READ COMMITTED')) {
        // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
            return new WP_Error('idlm_quote_transaction_isolation', 'Unable to verify live quotation write access. Please retry.');
        }
        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- REL007-SQL-0089: begin_checked_transaction(): table() prefixes fixed suffixes; term/column and sort maps are closed allowlists; filters carry live lead scope, values/ID lists are prepared; current revision and transaction state must remain live.
        if (false === $wpdb->query('START TRANSACTION')) {
        // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
            return new WP_Error('idlm_quote_transaction_start', 'Unable to start the quotation transaction. Please retry.');
        }
        $result = self::verify_transactional_tables($scope);
        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- REL007-SQL-0090: begin_checked_transaction(): table() prefixes fixed suffixes; term/column and sort maps are closed allowlists; filters carry live lead scope, values/ID lists are prepared; current revision and transaction state must remain live.
        if (is_wp_error($result)) { $wpdb->query('ROLLBACK'); }
        // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
        return $result;
    }

    /**
     * Called inside the owning transaction, including migration number reservations.
     * Zero-row locking reads pin metadata without creating a stale read view.
     * Locks remain until COMMIT/ROLLBACK. Check engines
     * after opening, so concurrent ALTER TABLE cannot invalidate verified engines.
     * Never cache a successful check across requests or transactions.
     */
    public static function verify_transactional_tables($scope = 'save') {
        global $wpdb;
        if ('number' === $scope) {
            $suffixes = array('number_sequences');
        } elseif ('catalogue' === $scope) {
            $suffixes = array('quote_item_categories', 'quote_item_units', 'quote_catalogue_items');
        } elseif ('working_copy' === $scope) {
            $suffixes = array('quotes', 'quote_revisions', 'quote_status_history', 'leads');
        } elseif ('save' === $scope) {
            $suffixes = array('quote_item_categories', 'quote_item_units', 'quote_catalogue_items', 'quotes', 'quote_revisions', 'quote_revision_sections', 'quote_revision_items', 'quote_payment_milestones', 'quote_status_history', 'number_sequences', 'leads', 'lead_activity_log');
        } else {
            return new WP_Error('idlm_quote_transaction_scope', 'Unknown quotation transaction scope.');
        }
        $tables = array_map(array(__CLASS__, 'table'), $suffixes);
        if (in_array($scope, array('save', 'working_copy'), true)) { $tables[] = $wpdb->options; }
        sort($tables, SORT_STRING);
        $suppressed = $wpdb->suppress_errors(true);
        try {
            foreach ($tables as $table) {
                // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- REL007-SQL-0091: verify_transactional_tables(): table() prefixes fixed suffixes; term/column and sort maps are closed allowlists; filters carry live lead scope, values/ID lists are prepared; current revision and transaction state must remain live.
                if (!preg_match('/^[a-zA-Z0-9_]+$/', $table) || false === $wpdb->query("SELECT * FROM `{$table}` LIMIT 0 FOR UPDATE /* idlm_quotation_engine_pin */")) {
                // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                    return self::engine_error();
                }
                // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- REL007-SQL-0092: verify_transactional_tables(): table() prefixes fixed suffixes; term/column and sort maps are closed allowlists; filters carry live lead scope, values/ID lists are prepared; current revision and transaction state must remain live.
                $engine = $wpdb->get_var($wpdb->prepare('SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = %s /* idlm_quotation_write_engine */', $table));
                // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
                if ($wpdb->last_error || 'INNODB' !== strtoupper((string) $engine)) { return self::engine_error(); }
            }
        } finally { $wpdb->suppress_errors($suppressed); }
        return true;
    }

    private static function engine_error() {
        return new WP_Error('idlm_quote_transaction_engine', 'Quotation changes require verified InnoDB tables. Ask the administrator to back up the database, repair missing tables or migrate unsupported engines, then retry.');
    }

    /**
     * Reserve and format the next yearly quotation number atomically.
     *
     * @param array $settings Quotation numbering settings.
     * @param bool $own_transaction False only when the caller owns the save transaction.
     * @return string|WP_Error
     */
    public static function reserve_quote_number($settings, $own_transaction = true) {
        global $wpdb;
        $table = self::table('number_sequences');
        $year = absint(wp_date('Y'));
        $verified = $own_transaction ? self::begin_checked_transaction('number') : self::verify_transactional_tables('number');
        if (is_wp_error($verified)) { return $verified; }
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
        // Table name is generated exclusively from the active WordPress prefix.
        // phpcs:disable PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- REL007-SQL-0093: reserve_quote_number(): table() prefixes fixed suffixes; term/column and sort maps are closed allowlists; filters carry live lead scope, values/ID lists are prepared; current revision and transaction state must remain live.
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT id, last_number FROM {$table} WHERE sequence_type = %s AND sequence_year = %d FOR UPDATE",
        // phpcs:enable PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            'quotation',
            $year
        ));
        if ($row) {
            $next = absint($row->last_number) + 1;
            // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- REL007-SQL-0094: reserve_quote_number(): table() prefixes fixed suffixes; term/column and sort maps are closed allowlists; filters carry live lead scope, values/ID lists are prepared; current revision and transaction state must remain live.
            $ok = $wpdb->update($table, array('last_number' => $next, 'updated_at' => current_time('mysql')), array('id' => absint($row->id)), array('%d', '%s'), array('%d'));
            // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
        } else {
            $next = 1;
            // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery -- REL007-SQL-0095: reserve_quote_number(): table() prefixes fixed suffixes; term/column and sort maps are closed allowlists; filters carry live lead scope, values/ID lists are prepared; current revision and transaction state must remain live.
            $ok = $wpdb->insert($table, array('sequence_type' => 'quotation', 'sequence_year' => $year, 'last_number' => $next, 'updated_at' => current_time('mysql')), array('%s', '%d', '%d', '%s'));
            // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery
        }
        if (false === $ok) {
            // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- REL007-SQL-0096: reserve_quote_number(): table() prefixes fixed suffixes; term/column and sort maps are closed allowlists; filters carry live lead scope, values/ID lists are prepared; current revision and transaction state must remain live.
            $wpdb->query('ROLLBACK');
            // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
            return new WP_Error('sequence_failed', 'Unable to reserve a quotation number.');
        }
        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- REL007-SQL-0097: reserve_quote_number(): table() prefixes fixed suffixes; term/column and sort maps are closed allowlists; filters carry live lead scope, values/ID lists are prepared; current revision and transaction state must remain live.
        if ($own_transaction && false === $wpdb->query('COMMIT')) {
        // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
            $wpdb->query('ROLLBACK');
            return new WP_Error('sequence_failed', 'Unable to commit the quotation number reservation.');
        }
        $prefix = preg_replace('/[^A-Za-z0-9_-]/', '', (string) ($settings['number_prefix'] ?? 'Q'));
        $separator_setting = $settings['number_separator'] ?? '-';
        $separator = in_array($separator_setting, array('-', '/', '_'), true) ? $separator_setting : '-';
        $padding = min(10, max(3, absint($settings['number_padding'] ?? 6)));
        return $prefix . $separator . $year . $separator . str_pad((string) $next, $padding, '0', STR_PAD_LEFT);
    }

    /**
     * Fetch a quotation row.
     *
     * @param int $quote_id Quotation ID.
     * @return object|null
     */
    public static function get_quote($quote_id) {
        global $wpdb;
        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- REL007-SQL-0098: get_quote(): table() prefixes fixed suffixes; term/column and sort maps are closed allowlists; filters carry live lead scope, values/ID lists are prepared; current revision and transaction state must remain live.
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter
        return $wpdb->get_row($wpdb->prepare("SELECT * FROM " . self::table('quotes') . " WHERE id = %d", absint($quote_id)));
        // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
    }

    /** Unknown/commercial history or a failed read prevents reopening a legacy quote. */
    public static function has_commercial_history($quote_id) {
        global $wpdb;
        // phpcs:disable PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared -- REL007-SQL-0099: has_commercial_history(): table() prefixes fixed suffixes; term/column and sort maps are closed allowlists; filters carry live lead scope, values/ID lists are prepared; current revision and transaction state must remain live.
        $count = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM " . self::table('quote_status_history') . " WHERE quote_id = %d AND (LOWER(TRIM(from_status)) NOT IN ('', 'draft', 'superseded') OR LOWER(TRIM(to_status)) NOT IN ('', 'draft', 'superseded'))", absint($quote_id)));
        // phpcs:enable PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared
        if ('' !== (string) $wpdb->last_error || null === $count || (int) $count > 0) { return true; }
        // phpcs:disable PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared -- REL007-SQL-0100: has_commercial_history(): table() prefixes fixed suffixes; term/column and sort maps are closed allowlists; filters carry live lead scope, values/ID lists are prepared; current revision and transaction state must remain live.
        $count = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM " . self::table('quote_revisions') . " WHERE quote_id = %d AND LOWER(TRIM(status)) NOT IN ('draft', 'superseded')", absint($quote_id)));
        // phpcs:enable PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared
        return '' !== (string) $wpdb->last_error || null === $count || (int) $count > 0;
    }

    /**
     * Return the canonical quotation for a lead. Revisions, rather than
     * additional quotation rows, represent changes to a lead's proposal.
     *
     * @return object|null
     */
    public static function get_quote_for_lead($lead_id) {
        global $wpdb;
        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- REL007-SQL-0101: get_quote_for_lead(): table() prefixes fixed suffixes; term/column and sort maps are closed allowlists; filters carry live lead scope, values/ID lists are prepared; current revision and transaction state must remain live.
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter
        return $wpdb->get_row($wpdb->prepare("SELECT * FROM " . self::table('quotes') . " WHERE lead_id = %d ORDER BY id ASC LIMIT 1", absint($lead_id)));
        // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
    }

    /** Return the lead-level quotation projection used by pipeline validation. */
    public static function get_lead_quote_value($lead_id) {
        global $wpdb;
        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- REL007-SQL-0102: get_lead_quote_value(): table() prefixes fixed suffixes; term/column and sort maps are closed allowlists; filters carry live lead scope, values/ID lists are prepared; current revision and transaction state must remain live.
        return (float) $wpdb->get_var($wpdb->prepare("SELECT quote_value FROM {$wpdb->prefix}idlm_leads WHERE id = %d", absint($lead_id)));
        // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
    }

    /** Serialize new quotation creation for one lead across concurrent requests. */
    public static function acquire_lead_quote_lock($lead_id, $timeout = 5) {
        global $wpdb;
        $lock_name = 'idlm_quote_lead_' . absint($lead_id);
        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- REL007-SQL-0103: acquire_lead_quote_lock(): table() prefixes fixed suffixes; term/column and sort maps are closed allowlists; filters carry live lead scope, values/ID lists are prepared; current revision and transaction state must remain live.
        return 1 === (int) $wpdb->get_var($wpdb->prepare('SELECT GET_LOCK(%s, %d)', $lock_name, min(15, max(1, absint($timeout)))));
        // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
    }

    /** Release a quotation-creation lock when the request completed normally. */
    public static function release_lead_quote_lock($lead_id) {
        global $wpdb;
        $lock_name = 'idlm_quote_lead_' . absint($lead_id);
        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- REL007-SQL-0104: release_lead_quote_lock(): table() prefixes fixed suffixes; term/column and sort maps are closed allowlists; filters carry live lead scope, values/ID lists are prepared; current revision and transaction state must remain live.
        $wpdb->get_var($wpdb->prepare('SELECT RELEASE_LOCK(%s)', $lock_name));
        // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
    }

    /**
     * Fetch a quotation revision row.
     *
     * @param int $revision_id Revision ID.
     * @return object|null
     */
    public static function get_revision($revision_id) {
        global $wpdb;
        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- REL007-SQL-0105: get_revision(): table() prefixes fixed suffixes; term/column and sort maps are closed allowlists; filters carry live lead scope, values/ID lists are prepared; current revision and transaction state must remain live.
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter
        return $wpdb->get_row($wpdb->prepare("SELECT * FROM " . self::table('quote_revisions') . " WHERE id = %d", absint($revision_id)));
        // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
    }

    /**
     * Fetch the revision created for an idempotent save request.
     *
     * @param string $save_request_key UUID request key.
     * @return object|null
     */
    public static function get_revision_by_save_request_key($save_request_key) {
        global $wpdb;
        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- REL007-SQL-0106: get_revision_by_save_request_key(): table() prefixes fixed suffixes; term/column and sort maps are closed allowlists; filters carry live lead scope, values/ID lists are prepared; current revision and transaction state must remain live.
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter
        return $wpdb->get_row($wpdb->prepare("SELECT id, quote_id, source_revision_id, created_by FROM " . self::table('quote_revisions') . " WHERE save_request_key = %s", (string) $save_request_key));
        // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
    }

    /**
     * Fetch the lead fields snapshotted into a quotation revision.
     *
     * @param int $lead_id Lead ID.
     * @param bool $lock Caller-owned transaction: lock the live lead before mutation.
     * @return object|null
     */
    public static function get_lead_snapshot($lead_id, $lock = false) {
        global $wpdb;
        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared -- REL007-SQL-0107: get_lead_snapshot(): table() prefixes fixed suffixes; term/column and sort maps are closed allowlists; filters carry live lead scope, values/ID lists are prepared; current revision and transaction state must remain live.
        return $wpdb->get_row($wpdb->prepare(
            "SELECT assigned_rep_id, first_name, last_name, phone, email, project_name, property_location FROM {$wpdb->prefix}idlm_leads WHERE id = %d" . ($lock ? " FOR UPDATE" : ""),
        // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared
            absint($lead_id)
        ));
    }

    /**
     * Insert a quotation shell and return its ID.
     *
     * @param array $data Quotation row data.
     * @param array $format Optional WordPress value formats.
     * @return int|false
     */
    public static function insert_quote($data, $format = null) {
        global $wpdb;
        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery -- REL007-SQL-0108: insert_quote(): table() prefixes fixed suffixes; term/column and sort maps are closed allowlists; filters carry live lead scope, values/ID lists are prepared; current revision and transaction state must remain live.
        $inserted = $wpdb->insert(self::table('quotes'), $data, $format);
        // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery
        $quote_id = absint($wpdb->insert_id);
        return $inserted && $quote_id ? $quote_id : false;
    }

    /**
     * Update a quotation row.
     *
     * @return int|false
     */
    public static function update_quote($quote_id, $data, $format = null) {
        global $wpdb;
        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- REL007-SQL-0109: update_quote(): table() prefixes fixed suffixes; term/column and sort maps are closed allowlists; filters carry live lead scope, values/ID lists are prepared; current revision and transaction state must remain live.
        return $wpdb->update(self::table('quotes'), $data, array('id' => absint($quote_id)), $format, array('%d'));
        // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
    }

    /**
     * Reserve the next revision number while the caller's transaction is active.
     *
     * @param int $quote_id Quotation ID.
     * @return int
     */
    public static function next_revision_number($quote_id) {
        global $wpdb;
        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- REL007-SQL-0110: next_revision_number(): table() prefixes fixed suffixes; term/column and sort maps are closed allowlists; filters carry live lead scope, values/ID lists are prepared; current revision and transaction state must remain live.
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter
        return absint($wpdb->get_var($wpdb->prepare("SELECT MAX(revision_number) FROM " . self::table('quote_revisions') . " WHERE quote_id = %d FOR UPDATE", absint($quote_id)))) + 1;
        // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
    }

    /**
     * Insert a quotation revision and return its ID.
     *
     * @param array $data Revision row data.
     * @return int|false
     */
    public static function insert_revision($data) {
        global $wpdb;
        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery -- REL007-SQL-0111: insert_revision(): table() prefixes fixed suffixes; term/column and sort maps are closed allowlists; filters carry live lead scope, values/ID lists are prepared; current revision and transaction state must remain live.
        $inserted = $wpdb->insert(self::table('quote_revisions'), $data);
        // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery
        $revision_id = absint($wpdb->insert_id);
        return $inserted && $revision_id ? $revision_id : false;
    }

    /**
     * Update a quotation revision row.
     *
     * @return int|false
     */
    public static function update_revision($revision_id, $data) {
        global $wpdb;
        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- REL007-SQL-0112: update_revision(): table() prefixes fixed suffixes; term/column and sort maps are closed allowlists; filters carry live lead scope, values/ID lists are prepared; current revision and transaction state must remain live.
        return $wpdb->update(self::table('quote_revisions'), $data, array('id' => absint($revision_id)), null, array('%d'));
        // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
    }

    /**
     * Mark a revision as superseded.
     *
     * @return int|false
     */
    public static function supersede_revision($revision_id, $finalized_at) {
        global $wpdb;
        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- REL007-SQL-0113: supersede_revision(): table() prefixes fixed suffixes; term/column and sort maps are closed allowlists; filters carry live lead scope, values/ID lists are prepared; current revision and transaction state must remain live.
        return $wpdb->update(
        // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
            self::table('quote_revisions'),
            array('status' => 'Superseded', 'finalized_at' => $finalized_at),
            array('id' => absint($revision_id)),
            array('%s', '%s'),
            array('%d')
        );
    }

    /**
     * Point a quotation at its current revision and status.
     *
     * @return int|false
     */
    public static function set_current_revision($quote_id, $revision_id, $status, $updated_at) {
        return self::update_quote(
            $quote_id,
            array('current_revision_id' => absint($revision_id), 'status' => (string) $status, 'updated_at' => $updated_at),
            array('%d', '%s', '%s')
        );
    }

    /**
     * Replace every section and item belonging to a revision.
     *
     * The caller owns the surrounding transaction. A WP_Error identifies the
     * exact write that should trigger the caller's rollback path.
     *
     * @return true|WP_Error
     */
    public static function replace_revision_contents($revision_id, $sections, $items) {
        global $wpdb;
        $revision_id = absint($revision_id);
        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- REL007-SQL-0114: replace_revision_contents(): table() prefixes fixed suffixes; term/column and sort maps are closed allowlists; filters carry live lead scope, values/ID lists are prepared; current revision and transaction state must remain live.
        if (false === $wpdb->delete(self::table('quote_revision_items'), array('revision_id' => $revision_id), array('%d'))) {
        // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
            return new WP_Error('revision_items_delete_failed', 'Unable to replace the quotation items.', 'Revision item cleanup failed');
        }
        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- REL007-SQL-0115: replace_revision_contents(): table() prefixes fixed suffixes; term/column and sort maps are closed allowlists; filters carry live lead scope, values/ID lists are prepared; current revision and transaction state must remain live.
        if (false === $wpdb->delete(self::table('quote_revision_sections'), array('revision_id' => $revision_id), array('%d'))) {
        // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
            return new WP_Error('revision_sections_delete_failed', 'Unable to replace the quotation sections.', 'Revision section cleanup failed');
        }

        $section_ids = array();
        foreach ($sections as $key => $section) {
            $section['revision_id'] = $revision_id;
            // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery -- REL007-SQL-0116: replace_revision_contents(): table() prefixes fixed suffixes; term/column and sort maps are closed allowlists; filters carry live lead scope, values/ID lists are prepared; current revision and transaction state must remain live.
            if (!$wpdb->insert(self::table('quote_revision_sections'), $section)) {
            // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery
                return new WP_Error('revision_section_insert_failed', 'Unable to save quotation sections.', 'Revision section insert failed');
            }
            $section_ids[$key] = absint($wpdb->insert_id);
        }

        foreach ($items as $item) {
            $section_key = $item['section_key'];
            unset($item['section_key']);
            $item['section_id'] = $section_ids[$section_key] ?? reset($section_ids);
            $item['revision_id'] = $revision_id;
            // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery -- REL007-SQL-0117: replace_revision_contents(): table() prefixes fixed suffixes; term/column and sort maps are closed allowlists; filters carry live lead scope, values/ID lists are prepared; current revision and transaction state must remain live.
            if (!$wpdb->insert(self::table('quote_revision_items'), $item)) {
            // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery
                return new WP_Error('revision_item_insert_failed', 'Unable to save quotation items.', 'Revision item insert failed');
            }
        }
        return true;
    }

    /**
     * Lock and fetch a quotation while the caller's transaction is active.
     *
     * @param int $quote_id Quotation ID.
     * @return object|null
     */
    public static function lock_quote($quote_id) {
        global $wpdb;
        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- REL007-SQL-0118: lock_quote(): table() prefixes fixed suffixes; term/column and sort maps are closed allowlists; filters carry live lead scope, values/ID lists are prepared; current revision and transaction state must remain live.
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter
        return $wpdb->get_row($wpdb->prepare("SELECT * FROM " . self::table('quotes') . " WHERE id = %d FOR UPDATE", absint($quote_id)));
        // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
    }

    /**
     * Copy a revision's sections and items into a new revision.
     *
     * The caller owns the surrounding transaction.
     *
     * @return true|WP_Error
     */
    public static function copy_revision_contents($source_revision_id, $new_revision_id) {
        global $wpdb;
        $source_revision_id = absint($source_revision_id);
        $new_revision_id = absint($new_revision_id);
        $section_map = array();
        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- REL007-SQL-0119: copy_revision_contents(): table() prefixes fixed suffixes; term/column and sort maps are closed allowlists; filters carry live lead scope, values/ID lists are prepared; current revision and transaction state must remain live.
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter
        $source_sections = $wpdb->get_results($wpdb->prepare("SELECT * FROM " . self::table('quote_revision_sections') . " WHERE revision_id = %d ORDER BY sort_order, id", $source_revision_id), ARRAY_A);
        // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
        foreach ($source_sections as $section) {
            $old_section_id = absint($section['id']);
            unset($section['id']);
            $section['revision_id'] = $new_revision_id;
            // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery -- REL007-SQL-0120: copy_revision_contents(): table() prefixes fixed suffixes; term/column and sort maps are closed allowlists; filters carry live lead scope, values/ID lists are prepared; current revision and transaction state must remain live.
            if (!$wpdb->insert(self::table('quote_revision_sections'), $section)) {
            // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery
                return new WP_Error('revision_section_copy_failed', 'Unable to copy the quotation sections.', 'Revision section copy failed');
            }
            $section_map[$old_section_id] = absint($wpdb->insert_id);
        }

        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- REL007-SQL-0121: copy_revision_contents(): table() prefixes fixed suffixes; term/column and sort maps are closed allowlists; filters carry live lead scope, values/ID lists are prepared; current revision and transaction state must remain live.
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter
        $items = $wpdb->get_results($wpdb->prepare("SELECT * FROM " . self::table('quote_revision_items') . " WHERE revision_id = %d ORDER BY sort_order, id", $source_revision_id), ARRAY_A);
        // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
        foreach ($items as $item) {
            unset($item['id']);
            $item['revision_id'] = $new_revision_id;
            $item['section_id'] = $section_map[absint($item['section_id'] ?? 0)] ?? 0;
            // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery -- REL007-SQL-0122: copy_revision_contents(): table() prefixes fixed suffixes; term/column and sort maps are closed allowlists; filters carry live lead scope, values/ID lists are prepared; current revision and transaction state must remain live.
            if (!$wpdb->insert(self::table('quote_revision_items'), $item)) {
            // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery
                return new WP_Error('revision_item_copy_failed', 'Unable to copy the quotation items.', 'Revision item copy failed');
            }
        }
        return true;
    }

    /**
     * Update the lead's denormalized quotation value inside the save transaction.
     *
     * @return int|false
     */
    public static function update_lead_quote_value($lead_id, $grand_total, $updated_at) {
        global $wpdb;
        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- REL007-SQL-0123: update_lead_quote_value(): table() prefixes fixed suffixes; term/column and sort maps are closed allowlists; filters carry live lead scope, values/ID lists are prepared; current revision and transaction state must remain live.
        return $wpdb->update(
        // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
            $wpdb->prefix . 'idlm_leads',
            array('quote_value' => (float) $grand_total, 'updated_at' => $updated_at),
            array('id' => absint($lead_id)),
            array('%f', '%s'),
            array('%d')
        );
    }

    /**
     * Search active catalogue items for the quotation editor.
     *
     * @return array
     */
    public static function search_catalogue($term, $page, $per_page) {
        global $wpdb;
        $page = max(1, absint($page));
        $per_page = max(1, absint($per_page));
        $offset = ($page - 1) * $per_page;
        $like = '%' . $wpdb->esc_like((string) $term) . '%';
        $query = "SELECT id, item_code, category, item_name, description, unit, is_wood, default_rate FROM " . self::table('quote_catalogue_items') . " WHERE status = 'active' AND (item_name LIKE %s OR item_code LIKE %s OR category LIKE %s) ORDER BY category, item_name LIMIT %d OFFSET %d";
        // phpcs:disable PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared -- REL007-SQL-0124: search_catalogue(): table() prefixes fixed suffixes; term/column and sort maps are closed allowlists; filters carry live lead scope, values/ID lists are prepared; current revision and transaction state must remain live.
        return $wpdb->get_results($wpdb->prepare($query, $like, $like, $like, $per_page, $offset));
        // phpcs:enable PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared
    }

    /**
     * Resolve an allowlisted catalogue term table and name column.
     *
     * @return array|null
     */
    private static function catalogue_term_definition($type) {
        if ('category' === $type) {
            return array('table' => 'quote_item_categories', 'column' => 'category_name');
        }
        if ('unit' === $type) {
            return array('table' => 'quote_item_units', 'column' => 'unit_name');
        }
        return null;
    }

    /**
     * Insert an active catalogue category or unit.
     *
     * @return int|false
     */
    public static function insert_catalogue_term($type, $name, $user_id, $now) {
        global $wpdb;
        $definition = self::catalogue_term_definition($type);
        if (!$definition) {
            return false;
        }
        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery -- REL007-SQL-0125: insert_catalogue_term(): table() prefixes fixed suffixes; term/column and sort maps are closed allowlists; filters carry live lead scope, values/ID lists are prepared; current revision and transaction state must remain live.
        return $wpdb->insert(self::table($definition['table']), array(
        // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery
            $definition['column'] => (string) $name,
            'status' => 'active',
            'created_by' => absint($user_id),
            'created_at' => $now,
            'updated_at' => $now,
        ), array('%s', '%s', '%d', '%s', '%s'));
    }

    /**
     * Determine whether an active category or unit exists.
     *
     * @return bool
     */
    public static function catalogue_term_exists($type, $name) {
        global $wpdb;
        $definition = self::catalogue_term_definition($type);
        if (!$definition || '' === (string) $name) {
            return false;
        }
        // Table and column identifiers come only from catalogue_term_definition().
        // phpcs:disable PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQL.NotPrepared -- REL007-SQL-0126: catalogue_term_exists(): table() prefixes fixed suffixes; term/column and sort maps are closed allowlists; filters carry live lead scope, values/ID lists are prepared; current revision and transaction state must remain live.
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        return (bool) $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM " . self::table($definition['table']) . " WHERE {$definition['column']} = %s AND status = 'active'",
        // phpcs:enable PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQL.NotPrepared
            (string) $name
        ));
    }

    /**
     * Ensure a catalogue term exists during CSV import.
     *
     * @return int|false
     */
    public static function ensure_catalogue_term($type, $name, $user_id, $now) {
        global $wpdb;
        $definition = self::catalogue_term_definition($type);
        if (!$definition) {
            return false;
        }
        // Table and column identifiers come only from catalogue_term_definition().
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $sql = "INSERT IGNORE INTO " . self::table($definition['table']) . " ({$definition['column']}, status, created_by, created_at, updated_at) VALUES (%s, 'active', %d, %s, %s)";
        // phpcs:disable PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared -- REL007-SQL-0127: ensure_catalogue_term(): table() prefixes fixed suffixes; term/column and sort maps are closed allowlists; filters carry live lead scope, values/ID lists are prepared; current revision and transaction state must remain live.
        return $wpdb->query($wpdb->prepare($sql, (string) $name, absint($user_id), $now, $now));
        // phpcs:enable PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared
    }

    /**
     * Fetch one catalogue item.
     *
     * @return object|null
     */
    public static function get_catalogue_item($item_id) {
        global $wpdb;
        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- REL007-SQL-0128: get_catalogue_item(): table() prefixes fixed suffixes; term/column and sort maps are closed allowlists; filters carry live lead scope, values/ID lists are prepared; current revision and transaction state must remain live.
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter
        return $wpdb->get_row($wpdb->prepare("SELECT * FROM " . self::table('quote_catalogue_items') . " WHERE id = %d", absint($item_id)));
        // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
    }

    /**
     * Fetch the stored status for one catalogue item.
     *
     * @return string|null
     */
    public static function get_catalogue_item_status($item_id) {
        global $wpdb;
        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- REL007-SQL-0129: get_catalogue_item_status(): table() prefixes fixed suffixes; term/column and sort maps are closed allowlists; filters carry live lead scope, values/ID lists are prepared; current revision and transaction state must remain live.
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter
        return $wpdb->get_var($wpdb->prepare("SELECT status FROM " . self::table('quote_catalogue_items') . " WHERE id = %d", absint($item_id)));
        // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
    }

    /**
     * Determine whether a catalogue item code already exists.
     *
     * @return bool
     */
    public static function catalogue_item_code_exists($item_code) {
        global $wpdb;
        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- REL007-SQL-0130: catalogue_item_code_exists(): table() prefixes fixed suffixes; term/column and sort maps are closed allowlists; filters carry live lead scope, values/ID lists are prepared; current revision and transaction state must remain live.
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter
        return (bool) $wpdb->get_var($wpdb->prepare("SELECT id FROM " . self::table('quote_catalogue_items') . " WHERE item_code = %s", (string) $item_code));
        // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
    }

    /**
     * Insert a catalogue item.
     *
     * @return int|false
     */
    public static function insert_catalogue_item($data) {
        global $wpdb;
        $table = self::table('quote_catalogue_items');
        $generate_code = '' === trim((string) ($data['item_code'] ?? ''));
        if ($generate_code) {
            $data['item_code'] = 'AUTO-' . wp_generate_uuid4();
        }
        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery -- REL007-SQL-0131: insert_catalogue_item(): table() prefixes fixed suffixes; term/column and sort maps are closed allowlists; filters carry live lead scope, values/ID lists are prepared; current revision and transaction state must remain live.
        $inserted = $wpdb->insert($table, $data);
        // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery
        if (!$inserted || !$generate_code) {
            return $inserted;
        }

        $item_id = absint($wpdb->insert_id);
        $base_code = 'ITEM-' . str_pad((string) $item_id, 6, '0', STR_PAD_LEFT);
        $item_code = $base_code;
        $suffix = 1;
        while (self::catalogue_item_code_exists($item_code)) {
            $item_code = $base_code . '-' . $suffix;
            $suffix++;
        }
        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- REL007-SQL-0132: insert_catalogue_item(): table() prefixes fixed suffixes; term/column and sort maps are closed allowlists; filters carry live lead scope, values/ID lists are prepared; current revision and transaction state must remain live.
        if (false === $wpdb->update($table, array('item_code' => $item_code), array('id' => $item_id), array('%s'), array('%d'))) {
        // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
            $wpdb->delete($table, array('id' => $item_id), array('%d'));
            return false;
        }
        return $inserted;
    }

    /**
     * Update a catalogue item.
     *
     * @return int|false
     */
    public static function update_catalogue_item($item_id, $data) {
        global $wpdb;
        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- REL007-SQL-0133: update_catalogue_item(): table() prefixes fixed suffixes; term/column and sort maps are closed allowlists; filters carry live lead scope, values/ID lists are prepared; current revision and transaction state must remain live.
        return $wpdb->update(self::table('quote_catalogue_items'), $data, array('id' => absint($item_id)));
        // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
    }

    /**
     * Archive a catalogue item.
     *
     * @return int|false
     */
    public static function archive_catalogue_item($item_id, $now) {
        return self::update_catalogue_item($item_id, array('status' => 'inactive', 'updated_at' => $now));
    }

    /**
     * Archive or delete a set of catalogue items.
     *
     * @return int|false
     */
    public static function bulk_catalogue_items($item_ids, $action, $now) {
        global $wpdb;
        $item_ids = array_values(array_filter(array_map('absint', (array) $item_ids)));
        if (!$item_ids || !in_array($action, array('archive', 'delete'), true)) {
            return false;
        }
        $placeholders = implode(',', array_fill(0, count($item_ids), '%d'));
        if ('archive' === $action) {
            $sql = "UPDATE " . self::table('quote_catalogue_items') . " SET status = 'inactive', updated_at = %s WHERE id IN ({$placeholders})";
            // phpcs:disable PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared -- REL007-SQL-0134: bulk_catalogue_items(): table() prefixes fixed suffixes; term/column and sort maps are closed allowlists; filters carry live lead scope, values/ID lists are prepared; current revision and transaction state must remain live.
            return $wpdb->query($wpdb->prepare($sql, array_merge(array($now), $item_ids)));
            // phpcs:enable PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared
        }
        $sql = "DELETE FROM " . self::table('quote_catalogue_items') . " WHERE id IN ({$placeholders})";
        // phpcs:disable PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared -- REL007-SQL-0135: bulk_catalogue_items(): table() prefixes fixed suffixes; term/column and sort maps are closed allowlists; filters carry live lead scope, values/ID lists are prepared; current revision and transaction state must remain live.
        return $wpdb->query($wpdb->prepare($sql, $item_ids));
        // phpcs:enable PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared
    }

    /**
     * Archive categories or delete only categories that are not in use.
     *
     * @return array
     */
    public static function apply_catalogue_category_action($category_ids, $action, $now) {
        global $wpdb;
        $category_ids = array_values(array_filter(array_map('absint', (array) $category_ids)));
        $result = array('affected' => 0, 'blocked' => 0);
        if (!$category_ids || !in_array($action, array('archive', 'delete'), true)) {
            return $result;
        }
        $placeholders = implode(',', array_fill(0, count($category_ids), '%d'));
        // Placeholders are generated from absint-normalized IDs.
        // phpcs:disable PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- REL007-SQL-0136: apply_catalogue_category_action(): table() prefixes fixed suffixes; term/column and sort maps are closed allowlists; filters carry live lead scope, values/ID lists are prepared; current revision and transaction state must remain live.
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $categories = $wpdb->get_results($wpdb->prepare("SELECT id, category_name FROM " . self::table('quote_item_categories') . " WHERE id IN ({$placeholders})", $category_ids));
        // phpcs:enable PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
        foreach ($categories as $category) {
            if ('delete' === $action) {
                // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- REL007-SQL-0137: apply_catalogue_category_action(): table() prefixes fixed suffixes; term/column and sort maps are closed allowlists; filters carry live lead scope, values/ID lists are prepared; current revision and transaction state must remain live.
                // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter
                $in_use = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM " . self::table('quote_catalogue_items') . " WHERE category = %s", $category->category_name));
                // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
                if ($in_use) {
                    $result['blocked']++;
                    continue;
                }
                // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- REL007-SQL-0138: apply_catalogue_category_action(): table() prefixes fixed suffixes; term/column and sort maps are closed allowlists; filters carry live lead scope, values/ID lists are prepared; current revision and transaction state must remain live.
                $write = $wpdb->delete(self::table('quote_item_categories'), array('id' => absint($category->id)), array('%d'));
                // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
            } else {
                // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- REL007-SQL-0139: apply_catalogue_category_action(): table() prefixes fixed suffixes; term/column and sort maps are closed allowlists; filters carry live lead scope, values/ID lists are prepared; current revision and transaction state must remain live.
                $write = $wpdb->update(self::table('quote_item_categories'), array('status' => 'inactive', 'updated_at' => $now), array('id' => absint($category->id)), array('%s', '%s'), array('%d'));
                // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
            }
            if (false !== $write) {
                $result['affected'] += (int) $write;
            }
        }
        return $result;
    }

    /**
     * Count catalogue items matching an optional search term.
     *
     * @return int
     */
    public static function count_catalogue_items($search = '') {
        global $wpdb;
        $search = (string) $search;
        if ('' === $search) {
            // phpcs:disable PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared -- REL007-SQL-0140: count_catalogue_items(): table() prefixes fixed suffixes; term/column and sort maps are closed allowlists; filters carry live lead scope, values/ID lists are prepared; current revision and transaction state must remain live.
            return (int) $wpdb->get_var("SELECT COUNT(*) FROM " . self::table('quote_catalogue_items'));
            // phpcs:enable PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared
        }
        $like = '%' . $wpdb->esc_like($search) . '%';
        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared -- REL007-SQL-0141: count_catalogue_items(): table() prefixes fixed suffixes; term/column and sort maps are closed allowlists; filters carry live lead scope, values/ID lists are prepared; current revision and transaction state must remain live.
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter
        return (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM " . self::table('quote_catalogue_items') . " WHERE item_name LIKE %s OR item_code LIKE %s OR category LIKE %s",
        // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared
            $like,
            $like,
            $like
        ));
    }

    /**
     * Fetch one page of catalogue items.
     *
     * @return array
     */
    public static function list_catalogue_items($search, $page, $per_page, $sort_by = 'status', $sort_order = 'asc') {
        global $wpdb;
        $search = (string) $search;
        $page = max(1, absint($page));
        $per_page = max(1, absint($per_page));
        $sort_columns = array(
            'code' => 'item_code', 'category' => 'category', 'item' => 'item_name', 'unit' => 'unit',
            'wood' => 'is_wood', 'rate' => 'default_rate', 'status' => 'status',
        );
        $sort_by = sanitize_key($sort_by);
        $sort_column = $sort_columns[$sort_by] ?? $sort_columns['status'];
        $sort_order = 'desc' === strtolower((string) $sort_order) ? 'DESC' : 'ASC';
        $params = array();
        $where = '';
        if ('' !== $search) {
            $like = '%' . $wpdb->esc_like($search) . '%';
            $where = ' WHERE item_name LIKE %s OR item_code LIKE %s OR category LIKE %s';
            $params = array($like, $like, $like);
        }
        $order_by = 'status' === $sort_by
            ? "status {$sort_order}, category ASC, item_name ASC, id ASC"
            : "{$sort_column} {$sort_order}, id ASC";
        // Sort identifiers are selected only from the fixed allowlist above.
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $query = "SELECT * FROM " . self::table('quote_catalogue_items') . $where . " ORDER BY {$order_by} LIMIT %d OFFSET %d";
        $params[] = $per_page;
        $params[] = ($page - 1) * $per_page;
        // phpcs:disable PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared -- REL007-SQL-0142: list_catalogue_items(): table() prefixes fixed suffixes; term/column and sort maps are closed allowlists; filters carry live lead scope, values/ID lists are prepared; current revision and transaction state must remain live.
        return $wpdb->get_results($wpdb->prepare($query, $params));
        // phpcs:enable PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared
    }

    /**
     * Fetch every catalogue category for management and active selection.
     *
     * @return array
     */
    public static function list_catalogue_categories() {
        global $wpdb;
        // phpcs:disable PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared -- REL007-SQL-0143: list_catalogue_categories(): table() prefixes fixed suffixes; term/column and sort maps are closed allowlists; filters carry live lead scope, values/ID lists are prepared; current revision and transaction state must remain live.
        return $wpdb->get_results("SELECT id, category_name, status FROM " . self::table('quote_item_categories') . " ORDER BY status, category_name");
        // phpcs:enable PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared
    }

    /**
     * Fetch active catalogue unit names.
     *
     * @return array
     */
    public static function list_active_catalogue_units() {
        global $wpdb;
        // phpcs:disable PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared -- REL007-SQL-0144: list_active_catalogue_units(): table() prefixes fixed suffixes; term/column and sort maps are closed allowlists; filters carry live lead scope, values/ID lists are prepared; current revision and transaction state must remain live.
        return $wpdb->get_col("SELECT unit_name FROM " . self::table('quote_item_units') . " WHERE status = 'active' ORDER BY unit_name");
        // phpcs:enable PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared
    }

    /**
     * Fetch a bounded export page using keyset pagination.
     *
     * @return array
     */
    public static function get_catalogue_export_chunk($after_id, $limit = 500) {
        global $wpdb;
        $limit = min(500, max(1, absint($limit)));
        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared -- REL007-SQL-0145: get_catalogue_export_chunk(): table() prefixes fixed suffixes; term/column and sort maps are closed allowlists; filters carry live lead scope, values/ID lists are prepared; current revision and transaction state must remain live.
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter
        return $wpdb->get_results($wpdb->prepare(
            "SELECT id, item_code, category, item_name, description, unit, IF(is_wood = 1, 'Yes', 'No') AS is_wood, default_rate, status FROM " . self::table('quote_catalogue_items') . " WHERE id > %d ORDER BY id LIMIT %d",
        // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared
            absint($after_id),
            $limit
        ), ARRAY_A);
    }

    /**
     * Build the prepared filtering fragment shared by quotation list queries.
     *
     * @return array
     */
    private static function quotation_list_filters($filters) {
        global $wpdb;
        $where = '1=1';
        $params = array();
        // Quote ownership is attribution. Visibility follows the live lead,
        // including explicit read-all grants and assigned-only manager scopes.
        $where .= IDLM_Lead_Access_Policy::scope_sql('l', $params, false, false);
        $search = (string) ($filters['search'] ?? '');
        if ('' !== $search) {
            $like = '%' . $wpdb->esc_like($search) . '%';
            $where .= ' AND (q.quote_number LIKE %s OR l.first_name LIKE %s OR l.last_name LIKE %s OR l.phone LIKE %s OR l.project_name LIKE %s)';
            array_push($params, $like, $like, $like, $like, $like);
        }
        $created_by = absint($filters['created_by'] ?? 0);
        if ($created_by) {
            $where .= ' AND q.created_by = %d';
            $params[] = $created_by;
        }
        if (!empty($filters['date_from'])) {
            $where .= ' AND q.created_at >= %s';
            $params[] = (string) $filters['date_from'] . ' 00:00:00';
        }
        if (!empty($filters['date_to'])) {
            $where .= ' AND q.created_at <= %s';
            $params[] = (string) $filters['date_to'] . ' 23:59:59';
        }
        $lead_id = absint($filters['lead_id'] ?? 0);
        if ($lead_id) {
            $where .= ' AND q.lead_id = %d';
            $params[] = $lead_id;
        }
        return array($where, $params);
    }

    /**
     * Count quotations matching the list read-model filters.
     *
     * @return int
     */
    public static function count_quotations($filters) {
        global $wpdb;
        list($where, $params) = self::quotation_list_filters($filters);
        $query = "SELECT COUNT(*) FROM " . self::table('quotes') . " q JOIN {$wpdb->prefix}idlm_leads l ON l.id = q.lead_id WHERE {$where}";
        // phpcs:disable PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared -- REL007-SQL-0146: count_quotations(): table() prefixes fixed suffixes; term/column and sort maps are closed allowlists; filters carry live lead scope, values/ID lists are prepared; current revision and transaction state must remain live.
        return (int) ($params ? $wpdb->get_var($wpdb->prepare($query, $params)) : $wpdb->get_var($query));
        // phpcs:enable PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared
    }

    /**
     * Fetch one page of the quotation list read model.
     *
     * @return array
     */
    public static function list_quotations($filters, $page, $per_page) {
        global $wpdb;
        list($where, $params) = self::quotation_list_filters($filters);
        $page = max(1, absint($page));
        $per_page = max(1, absint($per_page));
        $query = "SELECT q.*, r.revision_name, r.grand_total, r.currency_code, r.valid_until, l.first_name, l.last_name, l.email, l.phone, l.project_name, u.display_name AS creator_name
                FROM " . self::table('quotes') . " q
                JOIN {$wpdb->prefix}idlm_leads l ON l.id = q.lead_id
                LEFT JOIN " . self::table('quote_revisions') . " r ON r.id = q.current_revision_id
                LEFT JOIN {$wpdb->users} u ON u.ID = q.created_by
                WHERE {$where} ORDER BY q.updated_at DESC LIMIT %d OFFSET %d";
        $params[] = $per_page;
        $params[] = ($page - 1) * $per_page;
        // phpcs:disable PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared -- REL007-SQL-0147: list_quotations(): table() prefixes fixed suffixes; term/column and sort maps are closed allowlists; filters carry live lead scope, values/ID lists are prepared; current revision and transaction state must remain live.
        return $wpdb->get_results($wpdb->prepare($query, $params));
        // phpcs:enable PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared
    }

    /**
     * Fetch a quotation with its lead, selected revision, sections, items and revisions.
     *
     * @param int $quote_id Quotation ID.
     * @param int $revision_id Optional revision ID.
     * @return object|null
     */
    public static function get_quote_bundle($quote_id, $revision_id = 0) {
        global $wpdb;
        $quote = self::get_quote($quote_id);
        if (!$quote) {
            return null;
        }
        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- REL007-SQL-0148: get_quote_bundle(): table() prefixes fixed suffixes; term/column and sort maps are closed allowlists; filters carry live lead scope, values/ID lists are prepared; current revision and transaction state must remain live.
        $quote->lead = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}idlm_leads WHERE id = %d", $quote->lead_id));
        // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
        $revision_id = absint($revision_id);
        $quote->revision = self::get_revision($revision_id ?: $quote->current_revision_id);
        if ($quote->revision && absint($quote->revision->quote_id) !== absint($quote->id)) {
            return null;
        }
        if ($quote->revision) {
            // phpcs:disable PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared -- REL007-SQL-0149: get_quote_bundle(): table() prefixes fixed suffixes; term/column and sort maps are closed allowlists; filters carry live lead scope, values/ID lists are prepared; current revision and transaction state must remain live.
            $quote->sections = $wpdb->get_results($wpdb->prepare("SELECT * FROM " . self::table('quote_revision_sections') . " WHERE revision_id = %d ORDER BY sort_order, id", $quote->revision->id));
            // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter
            $quote->items = $wpdb->get_results($wpdb->prepare("SELECT i.*, s.section_name FROM " . self::table('quote_revision_items') . " i LEFT JOIN " . self::table('quote_revision_sections') . " s ON s.id = i.section_id WHERE i.revision_id = %d ORDER BY s.sort_order, i.sort_order, i.id", $quote->revision->id));
            // phpcs:enable PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared
            // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter
        }
        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- REL007-SQL-0150: get_quote_bundle(): table() prefixes fixed suffixes; term/column and sort maps are closed allowlists; filters carry live lead scope, values/ID lists are prepared; current revision and transaction state must remain live.
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter
        $quote->revisions = $wpdb->get_results($wpdb->prepare("SELECT * FROM " . self::table('quote_revisions') . " WHERE quote_id = %d ORDER BY revision_number DESC", absint($quote_id)));
        // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
        return $quote;
    }

    /**
     * Insert a quotation status-history record.
     *
     * @return int|false
     */
    public static function insert_history($quote_id, $revision_id, $from, $to, $note) {
        global $wpdb;
        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery -- REL007-SQL-0151: insert_history(): table() prefixes fixed suffixes; term/column and sort maps are closed allowlists; filters carry live lead scope, values/ID lists are prepared; current revision and transaction state must remain live.
        return $wpdb->insert(self::table('quote_status_history'), array(
        // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery
            'quote_id' => absint($quote_id),
            'revision_id' => absint($revision_id),
            'from_status' => sanitize_text_field($from),
            'to_status' => sanitize_text_field($to),
            'note' => sanitize_text_field($note),
            'changed_by' => get_current_user_id(),
            'changed_at' => current_time('mysql'),
        ));
    }

    /**
     * Determine whether a lead has quotation rows.
     *
     * @param int $lead_id Lead ID.
     * @return bool
     */
    public static function lead_has_quotes($lead_id) {
        global $wpdb;
        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- REL007-SQL-0152: lead_has_quotes(): table() prefixes fixed suffixes; term/column and sort maps are closed allowlists; filters carry live lead scope, values/ID lists are prepared; current revision and transaction state must remain live.
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter
        return (bool) $wpdb->get_var($wpdb->prepare("SELECT id FROM " . self::table('quotes') . " WHERE lead_id = %d LIMIT 1", absint($lead_id)));
        // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
    }

    /**
     * Delete every quotation aggregate belonging to a lead.
     *
     * The caller owns the surrounding transaction so this method returns on
     * the first failed statement without committing or rolling back.
     *
     * @param int $lead_id Lead ID.
     * @return bool
     */
    public static function delete_for_lead($lead_id) {
        global $wpdb;
        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- REL007-SQL-0153: delete_for_lead(): table() prefixes fixed suffixes; term/column and sort maps are closed allowlists; filters carry live lead scope, values/ID lists are prepared; current revision and transaction state must remain live.
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter
        $quote_ids = array_map('absint', (array) $wpdb->get_col($wpdb->prepare("SELECT id FROM " . self::table('quotes') . " WHERE lead_id = %d", absint($lead_id))));
        // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
        foreach ($quote_ids as $quote_id) {
            // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- REL007-SQL-0154: delete_for_lead(): table() prefixes fixed suffixes; term/column and sort maps are closed allowlists; filters carry live lead scope, values/ID lists are prepared; current revision and transaction state must remain live.
            // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter
            $revision_ids = array_map('absint', (array) $wpdb->get_col($wpdb->prepare("SELECT id FROM " . self::table('quote_revisions') . " WHERE quote_id = %d", $quote_id)));
            // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
            if ($revision_ids) {
                $placeholders = implode(',', array_fill(0, count($revision_ids), '%d'));
                // Placeholders are generated from an absint-normalized ID array.
                // phpcs:disable PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- REL007-SQL-0155: delete_for_lead(): table() prefixes fixed suffixes; term/column and sort maps are closed allowlists; filters carry live lead scope, values/ID lists are prepared; current revision and transaction state must remain live.
                // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter
                if (false === $wpdb->query($wpdb->prepare("DELETE FROM " . self::table('quote_revision_items') . " WHERE revision_id IN ({$placeholders})", $revision_ids))) { return false; }
                if (false === $wpdb->query($wpdb->prepare("DELETE FROM " . self::table('quote_revision_sections') . " WHERE revision_id IN ({$placeholders})", $revision_ids))) { return false; }
                // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter
                if (false === $wpdb->query($wpdb->prepare("DELETE FROM " . self::table('quote_payment_milestones') . " WHERE revision_id IN ({$placeholders})", $revision_ids))) { return false; }
                // phpcs:enable PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
            }
            // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- REL007-SQL-0156: delete_for_lead(): table() prefixes fixed suffixes; term/column and sort maps are closed allowlists; filters carry live lead scope, values/ID lists are prepared; current revision and transaction state must remain live.
            if (false === $wpdb->delete(self::table('quote_status_history'), array('quote_id' => $quote_id), array('%d'))) { return false; }
            if (false === $wpdb->delete(self::table('quote_revisions'), array('quote_id' => $quote_id), array('%d'))) { return false; }
            if (false === $wpdb->delete(self::table('quotes'), array('id' => $quote_id), array('%d'))) { return false; }
            // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
        }
        return true;
    }

    /**
     * Delete runtime quotation data atomically and clear data-migration markers.
     *
     * Catalogue rows and quotation configuration are intentionally preserved.
     *
     * @return bool
     */
    public static function reset_data() {
        global $wpdb;

        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- REL007-SQL-0157: reset_data(): table() prefixes fixed suffixes; term/column and sort maps are closed allowlists; filters carry live lead scope, values/ID lists are prepared; current revision and transaction state must remain live.
        if (false === $wpdb->query('START TRANSACTION')) {
        // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
            return false;
        }
        foreach (array('quote_revision_items', 'quote_revision_sections', 'quote_payment_milestones', 'quote_status_history', 'quote_revisions', 'quotes', 'number_sequences') as $table) {
            // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- REL007-SQL-0158: reset_data(): table() prefixes fixed suffixes; term/column and sort maps are closed allowlists; filters carry live lead scope, values/ID lists are prepared; current revision and transaction state must remain live.
            // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter
            if (false === $wpdb->query('DELETE FROM ' . self::table($table))) {
            // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
                $wpdb->query('ROLLBACK');
                return false;
            }
        }
        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- REL007-SQL-0159: reset_data(): table() prefixes fixed suffixes; term/column and sort maps are closed allowlists; filters carry live lead scope, values/ID lists are prepared; current revision and transaction state must remain live.
        if (false === $wpdb->query('COMMIT')) {
        // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
            $wpdb->query('ROLLBACK');
            return false;
        }
        delete_option('idlm_legacy_quote_migration_version');
        delete_option('idlm_quotation_snapshot_migration_version');
        return true;
    }
}
