<?php
/**
 * Quotation schema installation and versioned data migrations.
 *
 * Builder: Shashi
 */

if (!defined('ABSPATH')) {
    exit;
}

final class IDLM_Quotation_Schema {
    const VERSION = '2.5.4';
    const STATUS_OPTION = 'idlm_quotation_migration_status';
    const LEGACY_MIGRATION_VERSION = '1.1.0';
    const SNAPSHOT_MIGRATION_VERSION = '1.1.0';
    const REVISION_LIFECYCLE_MIGRATION_VERSION = '1.1.0';

    /** Completion includes independently checked backfill markers. */
    public static function is_current() {
        if ('ready' !== IDLM_Schema_Migration::version(self::STATUS_OPTION)) { return false; }
        foreach (self::markers() as $option => $version) {
            if ($version !== IDLM_Schema_Migration::version($option)) { return false; }
        }
        return true;
    }

    private static function markers() {
        return array('idlm_quotation_schema_version' => self::VERSION,
            'idlm_legacy_quote_migration_version' => self::LEGACY_MIGRATION_VERSION,
            'idlm_quotation_snapshot_migration_version' => self::SNAPSHOT_MIGRATION_VERSION,
            'idlm_quotation_revision_lifecycle_migration_version' => self::REVISION_LIFECYCLE_MIGRATION_VERSION);
    }

    /** Canonical declarations are also the physical verification manifest. */
    public static function schema_statements() {
        global $wpdb;
        $collate = $wpdb->get_charset_collate();
        $quotes = IDLM_Quotation_Repository::table('quotes');
        $revisions = IDLM_Quotation_Repository::table('quote_revisions');
        $items = IDLM_Quotation_Repository::table('quote_revision_items');
        $milestones = IDLM_Quotation_Repository::table('quote_payment_milestones');
        $history = IDLM_Quotation_Repository::table('quote_status_history');
        $sequences = IDLM_Quotation_Repository::table('number_sequences');
        $sections = IDLM_Quotation_Repository::table('quote_revision_sections');
        $catalogue = IDLM_Quotation_Repository::table('quote_catalogue_items');
        $categories = IDLM_Quotation_Repository::table('quote_item_categories');
        $units = IDLM_Quotation_Repository::table('quote_item_units');
        return array(
            "CREATE TABLE {$quotes} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            lead_id bigint(20) unsigned NOT NULL,
            quote_number varchar(50) NOT NULL,
            title varchar(255) NOT NULL,
            status varchar(30) NOT NULL DEFAULT 'Draft',
            current_revision_id bigint(20) unsigned DEFAULT 0,
            owner_user_id bigint(20) unsigned DEFAULT 0,
            created_by bigint(20) unsigned NOT NULL,
            accepted_revision_id bigint(20) unsigned DEFAULT 0,
            shared_at datetime DEFAULT NULL,
            accepted_at datetime DEFAULT NULL,
            rejected_at datetime DEFAULT NULL,
            save_request_key varchar(64) DEFAULT NULL,
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY quote_number (quote_number),
            KEY lead_status (lead_id, status),
            KEY owner_status (owner_user_id, status),
            KEY current_revision_id (current_revision_id),
            UNIQUE KEY save_request_key (save_request_key)
        ) ENGINE=InnoDB {$collate};",
            "CREATE TABLE {$revisions} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            quote_id bigint(20) unsigned NOT NULL,
            revision_number int(11) unsigned NOT NULL DEFAULT 1,
            revision_name varchar(80) NOT NULL,
            status varchar(30) NOT NULL DEFAULT 'Draft',
            source_revision_id bigint(20) unsigned DEFAULT 0,
            revision_reason varchar(500) DEFAULT '',
            currency_code varchar(10) NOT NULL DEFAULT 'INR',
            document_terms longtext DEFAULT NULL,
            valid_until date DEFAULT NULL,
            customer_intro text DEFAULT NULL,
            customer_name varchar(255) DEFAULT '',
            customer_phone varchar(50) DEFAULT '',
            customer_email varchar(255) DEFAULT '',
            project_name varchar(255) DEFAULT '',
            property_location text DEFAULT NULL,
            internal_notes longtext DEFAULT NULL,
            subtotal decimal(14,2) NOT NULL DEFAULT 0.00,
            wooden_subtotal decimal(14,2) NOT NULL DEFAULT 0.00,
            wooden_discount_percent decimal(5,2) NOT NULL DEFAULT 0.00,
            wooden_discount_amount decimal(14,2) NOT NULL DEFAULT 0.00,
            subtotal_after_wooden_discount decimal(14,2) NOT NULL DEFAULT 0.00,
            other_subtotal decimal(14,2) NOT NULL DEFAULT 0.00,
            soft_close_enabled tinyint(1) NOT NULL DEFAULT 0,
            soft_close_percent decimal(5,2) NOT NULL DEFAULT 0.00,
            soft_close_amount decimal(14,2) NOT NULL DEFAULT 0.00,
            design_fee_percent decimal(5,2) NOT NULL DEFAULT 0.00,
            design_fee_amount decimal(14,2) NOT NULL DEFAULT 0.00,
            total_before_additional_discount decimal(14,2) NOT NULL DEFAULT 0.00,
            additional_discount_amount decimal(14,2) NOT NULL DEFAULT 0.00,
            taxable_total decimal(14,2) NOT NULL DEFAULT 0.00,
            cgst_total decimal(14,2) NOT NULL DEFAULT 0.00,
            sgst_total decimal(14,2) NOT NULL DEFAULT 0.00,
            igst_total decimal(14,2) NOT NULL DEFAULT 0.00,
            round_off_amount decimal(14,2) NOT NULL DEFAULT 0.00,
            grand_total decimal(14,2) NOT NULL DEFAULT 0.00,
            created_by bigint(20) unsigned NOT NULL,
            created_at datetime NOT NULL,
            updated_by bigint(20) unsigned NOT NULL,
            updated_at datetime NOT NULL,
            finalized_at datetime DEFAULT NULL,
            save_request_key varchar(64) DEFAULT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY quote_revision (quote_id, revision_number),
            KEY quote_status (quote_id, status),
            KEY created_by (created_by),
            UNIQUE KEY save_request_key (save_request_key)
        ) ENGINE=InnoDB {$collate};",
            "CREATE TABLE {$items} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            revision_id bigint(20) unsigned NOT NULL,
            section_id bigint(20) unsigned DEFAULT 0,
            row_type varchar(20) NOT NULL DEFAULT 'item',
            catalogue_item_id bigint(20) unsigned DEFAULT 0,
            is_wood tinyint(1) NOT NULL DEFAULT 0,
            classification_source varchar(30) NOT NULL DEFAULT 'legacy_unverified',
            category varchar(100) DEFAULT '',
            room_area varchar(100) DEFAULT '',
            item_code varchar(100) DEFAULT '',
            item_name varchar(255) DEFAULT '',
            description text NOT NULL,
            specification text DEFAULT NULL,
            length decimal(12,3) NOT NULL DEFAULT 0.000,
            height decimal(12,3) NOT NULL DEFAULT 0.000,
            quantity decimal(12,3) NOT NULL DEFAULT 1.000,
            unit varchar(30) DEFAULT 'Nos',
            unit_rate decimal(14,2) NOT NULL DEFAULT 0.00,
            discount_type varchar(10) NOT NULL DEFAULT 'fixed',
            discount_value decimal(14,2) NOT NULL DEFAULT 0.00,
            discount_amount decimal(14,2) NOT NULL DEFAULT 0.00,
            line_subtotal decimal(14,2) NOT NULL DEFAULT 0.00,
            line_total decimal(14,2) NOT NULL DEFAULT 0.00,
            is_optional tinyint(1) NOT NULL DEFAULT 0,
            sort_order int(11) unsigned NOT NULL DEFAULT 0,
            PRIMARY KEY (id),
            KEY revision_order (revision_id, sort_order)
        ) ENGINE=InnoDB {$collate};",
            "CREATE TABLE {$sections} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            revision_id bigint(20) unsigned NOT NULL,
            section_name varchar(255) NOT NULL,
            section_description text DEFAULT NULL,
            sort_order int(11) unsigned NOT NULL DEFAULT 0,
            PRIMARY KEY (id),
            KEY revision_order (revision_id, sort_order)
        ) ENGINE=InnoDB {$collate};",
            "CREATE TABLE {$catalogue} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            item_code varchar(100) NOT NULL,
            category varchar(100) DEFAULT '',
            item_name varchar(255) NOT NULL,
            description text DEFAULT NULL,
            unit varchar(30) DEFAULT 'Nos',
            is_wood tinyint(1) NOT NULL DEFAULT 0,
            default_rate decimal(14,2) NOT NULL DEFAULT 0.00,
            status varchar(10) NOT NULL DEFAULT 'active',
            created_by bigint(20) unsigned NOT NULL DEFAULT 0,
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY item_code (item_code),
            KEY status_name (status, item_name),
            KEY category (category)
        ) ENGINE=InnoDB {$collate};",
            "CREATE TABLE {$categories} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            category_name varchar(100) NOT NULL,
            status varchar(10) NOT NULL DEFAULT 'active',
            created_by bigint(20) unsigned NOT NULL DEFAULT 0,
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY category_name (category_name),
            KEY status_name (status, category_name)
        ) ENGINE=InnoDB {$collate};",
            "CREATE TABLE {$units} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            unit_name varchar(30) NOT NULL,
            status varchar(10) NOT NULL DEFAULT 'active',
            created_by bigint(20) unsigned NOT NULL DEFAULT 0,
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY unit_name (unit_name),
            KEY status_name (status, unit_name)
        ) ENGINE=InnoDB {$collate};",
            "CREATE TABLE {$milestones} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            revision_id bigint(20) unsigned NOT NULL,
            milestone_name varchar(255) NOT NULL,
            amount_type varchar(10) NOT NULL DEFAULT 'percent',
            amount_value decimal(14,2) NOT NULL DEFAULT 0.00,
            calculated_amount decimal(14,2) NOT NULL DEFAULT 0.00,
            due_trigger varchar(255) DEFAULT '',
            sort_order int(11) unsigned NOT NULL DEFAULT 0,
            PRIMARY KEY (id),
            KEY revision_order (revision_id, sort_order)
        ) ENGINE=InnoDB {$collate};",
            "CREATE TABLE {$history} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            quote_id bigint(20) unsigned NOT NULL,
            revision_id bigint(20) unsigned DEFAULT 0,
            from_status varchar(30) DEFAULT '',
            to_status varchar(30) NOT NULL,
            note varchar(500) DEFAULT '',
            changed_by bigint(20) unsigned NOT NULL,
            changed_at datetime NOT NULL,
            PRIMARY KEY (id),
            KEY quote_history (quote_id, id),
            KEY revision_id (revision_id)
        ) ENGINE=InnoDB {$collate};",
            "CREATE TABLE {$sequences} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            sequence_type varchar(50) NOT NULL,
            sequence_year int(11) unsigned NOT NULL,
            last_number bigint(20) unsigned NOT NULL DEFAULT 0,
            updated_at datetime NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY sequence_year (sequence_type, sequence_year)
        ) ENGINE=InnoDB {$collate};"
        );
    }

    /** DDL is restartable, not rollbackable; publish only after physical/data checks. */
    public static function install() {
        return self::locked(static function() {
            global $wpdb;
            foreach (self::markers() as $option => $expected) {
                $saved = IDLM_Schema_Migration::version($option);
                if (null === $saved) { return IDLM_Schema_Migration::error('quotation_marker_read'); }
                if ($saved && version_compare($saved, $expected, '>')) { return IDLM_Schema_Migration::error('quotation_future_version', 'Quotation schema or migration is newer than this plugin. Use a matching plugin or database backup.'); }
            }
            $pending = IDLM_Schema_Migration::publish(self::STATUS_OPTION, 'pending');
            if (is_wp_error($pending)) { return $pending; }
            require_once ABSPATH . 'wp-admin/includes/upgrade.php';
            foreach (self::schema_statements() as $statement) {
                dbDelta($statement);
                if ($wpdb->last_error) { return IDLM_Schema_Migration::error('quotation_ddl'); }
            }
            $verified = IDLM_Schema_Migration::verify(self::schema_statements());
            if (is_wp_error($verified)) { return $verified; }
            $seeded = self::transaction(static function() {
                global $wpdb;
                $categories = IDLM_Quotation_Repository::table('quote_item_categories');
                $units = IDLM_Quotation_Repository::table('quote_item_units');
                $catalogue = IDLM_Quotation_Repository::table('quote_catalogue_items');
                $now = current_time('mysql');
                // phpcs:disable PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- REL007-SQL-0485: install(): schema/migration identifiers are fixed declarations or repository table constants; row/cursor/date values are bound; physical and historical postconditions require live reads.
                self::required($wpdb->query($wpdb->prepare("INSERT IGNORE INTO {$categories} (category_name, status, created_by, created_at, updated_at) SELECT DISTINCT category, 'active', 0, %s, %s FROM {$catalogue} WHERE category <> ''", $now, $now)));
                self::required($wpdb->query($wpdb->prepare("INSERT IGNORE INTO {$units} (unit_name, status, created_by, created_at, updated_at) SELECT DISTINCT unit, 'active', 0, %s, %s FROM {$catalogue} WHERE unit <> ''", $now, $now)));
                self::required($wpdb->query($wpdb->prepare("INSERT IGNORE INTO {$units} (unit_name, status, created_by, created_at, updated_at) VALUES ('Nos', 'active', 0, %s, %s)", $now, $now)));
                // phpcs:enable PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                $before = (array) get_option('idlm_quote_settings', array());
                $settings = $before;
                unset($settings['category_discounts'], $settings['per_item_tax_enabled'], $settings['global_tax_enabled'], $settings['global_tax_type'], $settings['global_tax_rate']);
                if ($settings !== $before) { self::required(update_option('idlm_quote_settings', $settings, false)); }
                // The additive provenance default marks old/missing snapshots as unverified.
                // Never infer historical classification from the mutable catalogue.
                return true;
            });
            if (is_wp_error($seeded)) { return $seeded; }
            foreach (array('migrate_legacy_quotes', 'backfill_revision_snapshots_and_sections', 'normalize_revision_lifecycle') as $method) {
                $result = self::$method();
                if (is_wp_error($result)) { return $result; }
            }
            return self::transaction(static function() {
                self::required(IDLM_Schema_Migration::publish('idlm_quotation_schema_version', self::VERSION));
                return IDLM_Schema_Migration::publish(self::STATUS_OPTION, 'ready');
            });
        });
    }

    /** Refuse another transaction owner before any implicit-commit DDL. */
    private static function locked($callback) {
        return IDLM_Schema_Migration::run(static function() use ($callback) {
            global $wpdb;
            // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- REL007-SQL-0486: locked(): schema/migration identifiers are fixed declarations or repository table constants; row/cursor/date values are bound; physical and historical postconditions require live reads.
            if (1 !== (int) $wpdb->get_var('SELECT @@autocommit') || false === $wpdb->query('SET TRANSACTION READ WRITE')) { return IDLM_Schema_Migration::error('quotation_transaction_owner'); }
            // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
            if (!IDLM_Recovery_Guard::enter_worker()) { return IDLM_Schema_Migration::error('quotation_recovery_busy'); }
            try { return $callback(); }
            finally { IDLM_Recovery_Guard::exit_worker(); }
        });
    }

    private static function required($value) {
        if (false === $value || is_wp_error($value)) { throw new RuntimeException('Required quotation migration operation failed.'); }
        return $value;
    }

    private static function read($value) {
        global $wpdb;
        self::required('' === (string) $wpdb->last_error);
        return $value;
    }

    /** All affected tables and marker storage must roll back together. */
    private static function transaction($callback) {
        global $wpdb;
        foreach (array($wpdb->options, $wpdb->prefix . 'idlm_leads') as $table) {
            // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- REL007-SQL-0487: transaction(): schema/migration identifiers are fixed declarations or repository table constants; row/cursor/date values are bound; physical and historical postconditions require live reads.
            $engine = $wpdb->get_var($wpdb->prepare('SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = %s', $table));
            // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
            if ($wpdb->last_error || 'INNODB' !== strtoupper((string) $engine)) { return IDLM_Schema_Migration::error('quotation_dependency_engine', 'Quotation migration requires InnoDB lead and option tables. Back up, repair the engine, and retry.'); }
        }
        $started = false; $committed = false;
        $retries = property_exists($wpdb, 'reconnect_retries') ? $wpdb->reconnect_retries : null;
        try {
            // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- REL007-SQL-0488: transaction(): schema/migration identifiers are fixed declarations or repository table constants; row/cursor/date values are bound; physical and historical postconditions require live reads.
            self::required(1 === (int) $wpdb->get_var('SELECT @@autocommit') && false !== $wpdb->query('SET TRANSACTION READ WRITE'));
            $connection = self::read($wpdb->get_var('SELECT CONNECTION_ID()'));
            // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
            self::required((bool) $connection);
            if (null !== $retries) { $wpdb->reconnect_retries = 0; }
            // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery -- REL007-SQL-0489: transaction(): schema/migration identifiers are fixed declarations or repository table constants; row/cursor/date values are bound; physical and historical postconditions require live reads.
            self::required($wpdb->query('START TRANSACTION')); $started = true;
            // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery
            $result = self::required($callback());
            // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- REL007-SQL-0490: transaction(): schema/migration identifiers are fixed declarations or repository table constants; row/cursor/date values are bound; physical and historical postconditions require live reads.
            self::required((string) $connection === (string) self::read($wpdb->get_var('SELECT CONNECTION_ID()')) && IDLM_Recovery_Guard::lease_valid());
            self::required($wpdb->query('COMMIT')); $committed = true;
            // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
            return $result;
        } catch (Throwable $error) {
            return IDLM_Schema_Migration::error('quotation_data', 'Quotation migration did not complete. Check database permissions/schema and retry; completion was not acknowledged.');
        } finally {
            // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery -- REL007-SQL-0491: transaction(): schema/migration identifiers are fixed declarations or repository table constants; row/cursor/date values are bound; physical and historical postconditions require live reads.
            if ($started && !$committed) { $wpdb->query('ROLLBACK'); }
            // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery
            if (null !== $retries) { $wpdb->reconnect_retries = $retries; }
            foreach (array_merge(array_keys(self::markers()), array('idlm_quote_settings', self::STATUS_OPTION)) as $option) { wp_cache_delete($option, 'options'); }
            wp_cache_delete('alloptions', 'options'); wp_cache_delete('notoptions', 'options');
        }
    }

    private static function step($option, $version, $work, $complete) {
        return self::locked(static function() use ($option, $version, $work, $complete) {
            $verified = IDLM_Schema_Migration::verify(self::schema_statements());
            if (is_wp_error($verified)) { return $verified; }
            $saved = IDLM_Schema_Migration::version($option);
            if (null === $saved) { return IDLM_Schema_Migration::error('quotation_marker_read'); }
            if ($saved && version_compare($saved, $version, '>')) { return IDLM_Schema_Migration::error('quotation_future_version'); }
            try {
                if ($version === $saved && $complete()) { return true; }
            } catch (Throwable $error) { return IDLM_Schema_Migration::error('quotation_read'); }
            return self::transaction(static function() use ($option, $version, $work, $complete) {
                self::required($work()); self::required($complete());
                return IDLM_Schema_Migration::publish($option, $version);
            });
        });
    }

    /** Compatibility entry point: preserve commercial state; verify links only. */
    public static function normalize_revision_lifecycle() {
        $quotes = IDLM_Quotation_Repository::table('quotes');
        $revisions = IDLM_Quotation_Repository::table('quote_revisions');
        $complete = static function() use ($quotes, $revisions) {
            global $wpdb;
            // phpcs:disable PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- REL007-SQL-0492: normalize_revision_lifecycle(): schema/migration identifiers are fixed declarations or repository table constants; row/cursor/date values are bound; physical and historical postconditions require live reads.
            $quote_links = self::read($wpdb->get_var("SELECT COUNT(*) /* idlm_quotation_lifecycle_integrity */ FROM {$quotes} q LEFT JOIN {$revisions} current_revision ON current_revision.id = q.current_revision_id AND current_revision.quote_id = q.id LEFT JOIN {$revisions} accepted_revision ON accepted_revision.id = q.accepted_revision_id AND accepted_revision.quote_id = q.id WHERE (COALESCE(q.current_revision_id, 0) > 0 AND current_revision.id IS NULL) OR (COALESCE(q.accepted_revision_id, 0) > 0 AND accepted_revision.id IS NULL)"));
            $revision_links = self::read($wpdb->get_var("SELECT COUNT(*) FROM {$revisions} r LEFT JOIN {$quotes} q ON q.id = r.quote_id LEFT JOIN {$revisions} source_revision ON source_revision.id = r.source_revision_id AND source_revision.quote_id = r.quote_id WHERE q.id IS NULL OR (COALESCE(r.source_revision_id, 0) > 0 AND (source_revision.id IS NULL OR source_revision.id = r.id))"));
            // phpcs:enable PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            return null !== $quote_links && null !== $revision_links && 0 === (int) $quote_links && 0 === (int) $revision_links;
        };
        // Identity mapping is reversible: no original status/pointer/date is replaced.
        return self::step('idlm_quotation_revision_lifecycle_migration_version', self::REVISION_LIFECYCLE_MIGRATION_VERSION, static function() { return true; }, $complete);
    }

    /** Page historical snapshots; all writes and its completion marker share one transaction. */
    public static function backfill_revision_snapshots_and_sections() {
        $revisions = IDLM_Quotation_Repository::table('quote_revisions');
        $quotes = IDLM_Quotation_Repository::table('quotes');
        $sections = IDLM_Quotation_Repository::table('quote_revision_sections');
        $items = IDLM_Quotation_Repository::table('quote_revision_items');
        $complete = static function() use ($revisions, $quotes, $sections, $items) {
            global $wpdb;
            // phpcs:disable PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- REL007-SQL-0493: backfill_revision_snapshots_and_sections(): schema/migration identifiers are fixed declarations or repository table constants; row/cursor/date values are bound; physical and historical postconditions require live reads.
            $missing = self::read($wpdb->get_var("SELECT COUNT(*) FROM {$revisions} r LEFT JOIN {$quotes} q ON q.id = r.quote_id LEFT JOIN {$wpdb->prefix}idlm_leads l ON l.id = q.lead_id WHERE q.id IS NULL OR NOT EXISTS (SELECT 1 FROM {$sections} s WHERE s.revision_id = r.id) OR (COALESCE(r.customer_name, '') = '' AND TRIM(CONCAT(COALESCE(l.first_name, ''), ' ', COALESCE(l.last_name, ''))) <> '')"));
            $unbound = self::read($wpdb->get_var("SELECT COUNT(*) FROM {$items} i LEFT JOIN {$sections} s ON s.id = i.section_id AND s.revision_id = i.revision_id WHERE s.id IS NULL"));
            // phpcs:enable PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            return null !== $missing && null !== $unbound && 0 === (int) $missing && 0 === (int) $unbound;
        };
        return self::step('idlm_quotation_snapshot_migration_version', self::SNAPSHOT_MIGRATION_VERSION, static function() use ($revisions, $quotes, $sections, $items) {
            global $wpdb;
            $cursor = 0;
            do {
                // phpcs:disable PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- REL007-SQL-0494: backfill_revision_snapshots_and_sections(): schema/migration identifiers are fixed declarations or repository table constants; row/cursor/date values are bound; physical and historical postconditions require live reads.
                $rows = self::read($wpdb->get_results($wpdb->prepare("SELECT r.id, r.customer_name, q.lead_id, l.first_name, l.last_name, l.phone, l.email, l.project_name, l.property_location FROM {$revisions} r JOIN {$quotes} q ON q.id = r.quote_id LEFT JOIN {$wpdb->prefix}idlm_leads l ON l.id = q.lead_id WHERE r.id > %d ORDER BY r.id LIMIT 100", $cursor)));
                // phpcs:enable PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                self::required(is_array($rows));
                foreach ($rows as $revision) {
                    if ('' === (string) $revision->customer_name) {
                        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- REL007-SQL-0495: backfill_revision_snapshots_and_sections(): schema/migration identifiers are fixed declarations or repository table constants; row/cursor/date values are bound; physical and historical postconditions require live reads.
                        self::required($wpdb->update($revisions, array('customer_name' => trim(($revision->first_name ?? '') . ' ' . ($revision->last_name ?? '')), 'customer_phone' => $revision->phone ?? '', 'customer_email' => $revision->email ?? '', 'project_name' => $revision->project_name ?? '', 'property_location' => $revision->property_location ?? ''), array('id' => $revision->id)));
                        // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
                    }
                    // phpcs:disable PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- REL007-SQL-0496: backfill_revision_snapshots_and_sections(): schema/migration identifiers are fixed declarations or repository table constants; row/cursor/date values are bound; physical and historical postconditions require live reads.
                    $section = absint(self::read($wpdb->get_var($wpdb->prepare("SELECT id FROM {$sections} WHERE revision_id = %d ORDER BY id LIMIT 1", $revision->id))));
                    // phpcs:enable PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                    if (!$section) {
                        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery -- REL007-SQL-0497: backfill_revision_snapshots_and_sections(): schema/migration identifiers are fixed declarations or repository table constants; row/cursor/date values are bound; physical and historical postconditions require live reads.
                        self::required(1 === $wpdb->insert($sections, array('revision_id' => $revision->id, 'section_name' => 'General', 'section_description' => '', 'sort_order' => 0)));
                        // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery
                        $section = absint($wpdb->insert_id); self::required($section > 0);
                    }
                    // phpcs:disable PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- REL007-SQL-0498: backfill_revision_snapshots_and_sections(): schema/migration identifiers are fixed declarations or repository table constants; row/cursor/date values are bound; physical and historical postconditions require live reads.
                    self::required($wpdb->query($wpdb->prepare("UPDATE {$items} SET section_id = %d WHERE revision_id = %d AND section_id = 0", $section, $revision->id)));
                    // phpcs:enable PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                    $cursor = absint($revision->id);
                }
            } while (count($rows) === 100);
            return true;
        }, $complete);
    }

    /** Retry legacy aggregate creation without nested transactions or numbering gaps on rollback. */
    public static function migrate_legacy_quotes() {
        $quotes = IDLM_Quotation_Repository::table('quotes');
        $revisions = IDLM_Quotation_Repository::table('quote_revisions');
        $complete = static function() use ($quotes, $revisions) {
            global $wpdb;
            // phpcs:disable PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- REL007-SQL-0499: migrate_legacy_quotes(): schema/migration identifiers are fixed declarations or repository table constants; row/cursor/date values are bound; physical and historical postconditions require live reads.
            $missing = self::read($wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}idlm_leads l WHERE (COALESCE(l.quote_value, 0) > 0 OR COALESCE(l.quote_file, '') <> '') AND NOT EXISTS (SELECT 1 FROM {$quotes} q INNER JOIN {$revisions} r ON r.id = q.current_revision_id AND r.quote_id = q.id WHERE q.lead_id = l.id)"));
            // phpcs:enable PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            return null !== $missing && 0 === (int) $missing;
        };
        return self::step('idlm_legacy_quote_migration_version', self::LEGACY_MIGRATION_VERSION, static function() use ($quotes, $revisions) {
            global $wpdb;
            $cursor = 0;
            do {
                // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- REL007-SQL-0500: migrate_legacy_quotes(): schema/migration identifiers are fixed declarations or repository table constants; row/cursor/date values are bound; physical and historical postconditions require live reads.
                $leads = self::read($wpdb->get_results($wpdb->prepare("SELECT id, first_name, last_name, phone, email, project_name, property_location, assigned_rep_id, quote_shared, quote_value, quote_file, created_at, updated_at FROM {$wpdb->prefix}idlm_leads WHERE id > %d AND (COALESCE(quote_value, 0) > 0 OR COALESCE(quote_file, '') <> '') ORDER BY id LIMIT 100", $cursor)));
                // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
                self::required(is_array($leads));
                foreach ($leads as $lead) {
                    $cursor = absint($lead->id);
                    // phpcs:disable PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- REL007-SQL-0501: migrate_legacy_quotes(): schema/migration identifiers are fixed declarations or repository table constants; row/cursor/date values are bound; physical and historical postconditions require live reads.
                    $existing = self::read($wpdb->get_row($wpdb->prepare("SELECT q.id, q.quote_number, q.current_revision_id, q.status, q.accepted_revision_id, q.shared_at, q.accepted_at, q.rejected_at, r.id AS valid_revision_id, (SELECT MAX(fallback.id) FROM {$revisions} fallback WHERE fallback.quote_id = q.id) AS fallback_revision_id FROM {$quotes} q LEFT JOIN {$revisions} r ON r.id = q.current_revision_id AND r.quote_id = q.id WHERE q.lead_id = %d ORDER BY q.id LIMIT 1", $lead->id)));
                    // phpcs:enable PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                    if ($existing && !empty($existing->valid_revision_id)) { continue; }
                    if ($existing && ('Draft' !== $existing->status || !empty($existing->accepted_revision_id) || !empty($existing->shared_at) || !empty($existing->accepted_at) || !empty($existing->rejected_at) || IDLM_Quotation_Repository::has_commercial_history($existing->id))) {
                        self::required(false); // Do not guess a replacement for a historical pointer.
                    }
                    $legacy_status = in_array(strtolower(trim((string) $lead->quote_shared)), array('yes', '1', 'true'), true) ? 'Shared' : 'Draft';
                    if ($existing && !empty($existing->fallback_revision_id)) {
                        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- REL007-SQL-0502: migrate_legacy_quotes(): schema/migration identifiers are fixed declarations or repository table constants; row/cursor/date values are bound; physical and historical postconditions require live reads.
                        self::required($wpdb->update($quotes, array('current_revision_id' => absint($existing->fallback_revision_id), 'updated_at' => current_time('mysql')), array('id' => absint($existing->id))));
                        // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
                        continue;
                    }
                    $number = $existing ? $existing->quote_number : IDLM_Quotation_Repository::reserve_quote_number(self::numbering_settings(), false);
                    self::required(!is_wp_error($number) && !empty($number));
                    $now = $lead->updated_at ?: ($lead->created_at ?: current_time('mysql'));
                    if ($existing) {
                        $quote_id = absint($existing->id);
                        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- REL007-SQL-0503: migrate_legacy_quotes(): schema/migration identifiers are fixed declarations or repository table constants; row/cursor/date values are bound; physical and historical postconditions require live reads.
                        self::required($wpdb->update($quotes, array('status' => $legacy_status, 'updated_at' => $now), array('id' => $quote_id)));
                        // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
                    } else {
                        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery -- REL007-SQL-0504: migrate_legacy_quotes(): schema/migration identifiers are fixed declarations or repository table constants; row/cursor/date values are bound; physical and historical postconditions require live reads.
                        self::required(1 === $wpdb->insert($quotes, array('lead_id' => $lead->id, 'quote_number' => $number, 'title' => ($lead->project_name ?: trim($lead->first_name . ' ' . $lead->last_name)) . ' Proposal', 'status' => $legacy_status, 'owner_user_id' => $lead->assigned_rep_id, 'created_by' => 0, 'created_at' => $now, 'updated_at' => $now)));
                        // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery
                        $quote_id = absint($wpdb->insert_id); self::required($quote_id > 0);
                    }
                    // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery -- REL007-SQL-0505: migrate_legacy_quotes(): schema/migration identifiers are fixed declarations or repository table constants; row/cursor/date values are bound; physical and historical postconditions require live reads.
                    self::required(1 === $wpdb->insert($revisions, array('quote_id' => $quote_id, 'revision_number' => 1, 'revision_name' => $number . '-R01', 'status' => $legacy_status, 'revision_reason' => 'Legacy quote migration', 'currency_code' => get_option('idlm_currency_code', 'INR'), 'customer_name' => trim($lead->first_name . ' ' . $lead->last_name), 'customer_phone' => $lead->phone, 'customer_email' => $lead->email, 'project_name' => $lead->project_name, 'property_location' => $lead->property_location, 'subtotal' => (float) $lead->quote_value, 'taxable_total' => (float) $lead->quote_value, 'grand_total' => (float) $lead->quote_value, 'internal_notes' => $lead->quote_file ? 'Legacy quote file: ' . esc_url_raw($lead->quote_file) : '', 'created_by' => 0, 'created_at' => $now, 'updated_by' => 0, 'updated_at' => $now)));
                    // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery
                    $revision_id = absint($wpdb->insert_id); self::required($revision_id > 0);
                    // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- REL007-SQL-0506: migrate_legacy_quotes(): schema/migration identifiers are fixed declarations or repository table constants; row/cursor/date values are bound; physical and historical postconditions require live reads.
                    self::required($wpdb->update($quotes, array('current_revision_id' => $revision_id), array('id' => $quote_id)));
                    // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
                    self::required(IDLM_Quotation_Repository::insert_history($quote_id, $revision_id, '', $legacy_status, 'Migrated from legacy lead quote fields; original dates and acceptance are not inferred.'));
                }
            } while (count($leads) === 100);
            return true;
        }, $complete);
    }

    private static function numbering_settings() {
        return wp_parse_args((array) get_option('idlm_quote_settings', array()), array('number_prefix' => 'Q', 'number_separator' => '-', 'number_padding' => 6));
    }
}
