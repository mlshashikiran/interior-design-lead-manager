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
    const VERSION = '2.4.0';
    const LEGACY_MIGRATION_VERSION = '1.1.0';
    const SNAPSHOT_MIGRATION_VERSION = '1.1.0';

    /**
     * Install or upgrade the quotation tables and run idempotent backfills.
     *
     * @return void
     */
    public static function install() {
        global $wpdb;
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
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

        dbDelta("CREATE TABLE {$quotes} (
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
        ) {$collate};");

        dbDelta("CREATE TABLE {$revisions} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            quote_id bigint(20) unsigned NOT NULL,
            revision_number int(11) unsigned NOT NULL DEFAULT 1,
            revision_name varchar(80) NOT NULL,
            status varchar(30) NOT NULL DEFAULT 'Draft',
            source_revision_id bigint(20) unsigned DEFAULT 0,
            revision_reason varchar(500) DEFAULT '',
            currency_code varchar(10) NOT NULL DEFAULT 'INR',
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
        ) {$collate};");

        dbDelta("CREATE TABLE {$items} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            revision_id bigint(20) unsigned NOT NULL,
            section_id bigint(20) unsigned DEFAULT 0,
            row_type varchar(20) NOT NULL DEFAULT 'item',
            catalogue_item_id bigint(20) unsigned DEFAULT 0,
            is_wood tinyint(1) NOT NULL DEFAULT 0,
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
        ) {$collate};");

        dbDelta("CREATE TABLE {$sections} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            revision_id bigint(20) unsigned NOT NULL,
            section_name varchar(255) NOT NULL,
            section_description text DEFAULT NULL,
            sort_order int(11) unsigned NOT NULL DEFAULT 0,
            PRIMARY KEY (id),
            KEY revision_order (revision_id, sort_order)
        ) {$collate};");

        dbDelta("CREATE TABLE {$catalogue} (
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
        ) {$collate};");

        dbDelta("CREATE TABLE {$categories} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            category_name varchar(100) NOT NULL,
            status varchar(10) NOT NULL DEFAULT 'active',
            created_by bigint(20) unsigned NOT NULL DEFAULT 0,
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY category_name (category_name),
            KEY status_name (status, category_name)
        ) {$collate};");

        dbDelta("CREATE TABLE {$units} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            unit_name varchar(30) NOT NULL,
            status varchar(10) NOT NULL DEFAULT 'active',
            created_by bigint(20) unsigned NOT NULL DEFAULT 0,
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY unit_name (unit_name),
            KEY status_name (status, unit_name)
        ) {$collate};");

        $now = current_time('mysql');
        $wpdb->query($wpdb->prepare("INSERT IGNORE INTO {$categories} (category_name, status, created_by, created_at, updated_at) SELECT DISTINCT category, 'active', 0, %s, %s FROM {$catalogue} WHERE category <> ''", $now, $now));
        $wpdb->query($wpdb->prepare("INSERT IGNORE INTO {$units} (unit_name, status, created_by, created_at, updated_at) SELECT DISTINCT unit, 'active', 0, %s, %s FROM {$catalogue} WHERE unit <> ''", $now, $now));
        $wpdb->query($wpdb->prepare("INSERT IGNORE INTO {$units} (unit_name, status, created_by, created_at, updated_at) VALUES ('Nos', 'active', 0, %s, %s)", $now, $now));

        dbDelta("CREATE TABLE {$milestones} (
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
        ) {$collate};");

        dbDelta("CREATE TABLE {$history} (
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
        ) {$collate};");

        dbDelta("CREATE TABLE {$sequences} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            sequence_type varchar(50) NOT NULL,
            sequence_year int(11) unsigned NOT NULL,
            last_number bigint(20) unsigned NOT NULL DEFAULT 0,
            updated_at datetime NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY sequence_year (sequence_type, sequence_year)
        ) {$collate};");

        $quote_settings = (array) get_option('idlm_quote_settings', array());
        unset($quote_settings['category_discounts'], $quote_settings['per_item_tax_enabled'], $quote_settings['global_tax_enabled'], $quote_settings['global_tax_type'], $quote_settings['global_tax_rate']);
        update_option('idlm_quote_settings', $quote_settings, false);
        $wpdb->query("UPDATE {$items} AS revision_item INNER JOIN {$catalogue} AS catalogue_item ON catalogue_item.id = revision_item.catalogue_item_id SET revision_item.is_wood = catalogue_item.is_wood WHERE revision_item.catalogue_item_id > 0");
        update_option('idlm_quotation_schema_version', self::VERSION);
        self::migrate_legacy_quotes();
        self::backfill_revision_snapshots_and_sections();
    }

    /**
     * Backfill immutable customer snapshots and a default section for old revisions.
     *
     * @return void
     */
    public static function backfill_revision_snapshots_and_sections() {
        if (self::SNAPSHOT_MIGRATION_VERSION === get_option('idlm_quotation_snapshot_migration_version', '')) {
            return;
        }
        global $wpdb;
        $revisions = $wpdb->get_results("SELECT r.id, r.customer_name, q.lead_id, l.first_name, l.last_name, l.phone, l.email, l.project_name, l.property_location FROM " . IDLM_Quotation_Repository::table('quote_revisions') . " r JOIN " . IDLM_Quotation_Repository::table('quotes') . " q ON q.id = r.quote_id LEFT JOIN {$wpdb->prefix}idlm_leads l ON l.id = q.lead_id");
        foreach ($revisions as $revision) {
            if ('' === (string) $revision->customer_name) {
                $wpdb->update(IDLM_Quotation_Repository::table('quote_revisions'), array('customer_name' => trim($revision->first_name . ' ' . $revision->last_name), 'customer_phone' => $revision->phone, 'customer_email' => $revision->email, 'project_name' => $revision->project_name, 'property_location' => $revision->property_location), array('id' => $revision->id));
            }
            $section_id = absint($wpdb->get_var($wpdb->prepare("SELECT id FROM " . IDLM_Quotation_Repository::table('quote_revision_sections') . " WHERE revision_id = %d ORDER BY id LIMIT 1", $revision->id)));
            if (!$section_id) {
                $wpdb->insert(IDLM_Quotation_Repository::table('quote_revision_sections'), array('revision_id' => $revision->id, 'section_name' => 'General', 'section_description' => '', 'sort_order' => 0));
                $section_id = absint($wpdb->insert_id);
            }
            $wpdb->query($wpdb->prepare("UPDATE " . IDLM_Quotation_Repository::table('quote_revision_items') . " SET section_id = %d WHERE revision_id = %d AND section_id = 0", $section_id, $revision->id));
        }
        update_option('idlm_quotation_snapshot_migration_version', self::SNAPSHOT_MIGRATION_VERSION);
    }

    /**
     * Migrate legacy quotation values stored directly on lead rows.
     *
     * @return void
     */
    public static function migrate_legacy_quotes() {
        if (self::LEGACY_MIGRATION_VERSION === get_option('idlm_legacy_quote_migration_version', '')) {
            return;
        }
        global $wpdb;
        $leads = $wpdb->get_results("SELECT id, first_name, last_name, phone, email, project_name, property_location, assigned_rep_id, quote_shared, quote_value, quote_file, created_at, updated_at FROM {$wpdb->prefix}idlm_leads WHERE COALESCE(quote_value, 0) > 0 OR COALESCE(quote_file, '') <> ''"); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        $migration_complete = true;
        foreach ($leads as $lead) {
            $existing = $wpdb->get_row($wpdb->prepare(
                "SELECT q.id, q.quote_number, q.current_revision_id, r.id AS valid_revision_id, (SELECT MAX(fallback.id) FROM " . IDLM_Quotation_Repository::table('quote_revisions') . " fallback WHERE fallback.quote_id = q.id) AS fallback_revision_id FROM " . IDLM_Quotation_Repository::table('quotes') . " q LEFT JOIN " . IDLM_Quotation_Repository::table('quote_revisions') . " r ON r.id = q.current_revision_id AND r.quote_id = q.id WHERE q.lead_id = %d ORDER BY q.id LIMIT 1",
                $lead->id
            ));
            if ($existing && !empty($existing->valid_revision_id)) {
                continue;
            }
            if ($existing && !empty($existing->fallback_revision_id)) {
                $repaired = $wpdb->update(IDLM_Quotation_Repository::table('quotes'), array('current_revision_id' => absint($existing->fallback_revision_id), 'updated_at' => current_time('mysql')), array('id' => absint($existing->id)), array('%d', '%s'), array('%d'));
                if (false !== $repaired) {
                    continue;
                }
                $migration_complete = false;
                error_log('[IDLM Quotations] Legacy current-revision repair failed for lead ' . absint($lead->id)); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
                continue;
            }
            $number = $existing ? $existing->quote_number : IDLM_Quotation_Repository::reserve_quote_number(self::numbering_settings());
            if (!$number || is_wp_error($number)) {
                $migration_complete = false;
                error_log('[IDLM Quotations] Legacy migration could not reserve a quotation number for lead ' . absint($lead->id)); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
                continue;
            }
            $now = $lead->updated_at ?: ($lead->created_at ?: current_time('mysql'));
            $status = $lead->quote_shared ? 'Shared' : 'Draft';
            if (false === $wpdb->query('START TRANSACTION')) {
                $migration_complete = false;
                error_log('[IDLM Quotations] Legacy migration transaction could not start for lead ' . absint($lead->id)); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
                continue;
            }
            if ($existing) {
                $quote_id = absint($existing->id);
                $quote_saved = $wpdb->update(IDLM_Quotation_Repository::table('quotes'), array('status' => $status, 'updated_at' => $now), array('id' => $quote_id), array('%s', '%s'), array('%d'));
            } else {
                $quote_saved = $wpdb->insert(IDLM_Quotation_Repository::table('quotes'), array('lead_id' => $lead->id, 'quote_number' => $number, 'title' => ($lead->project_name ?: trim($lead->first_name . ' ' . $lead->last_name)) . ' Proposal', 'status' => $status, 'owner_user_id' => $lead->assigned_rep_id, 'created_by' => 0, 'shared_at' => $lead->quote_shared ? $now : null, 'created_at' => $now, 'updated_at' => $now));
                $quote_id = absint($wpdb->insert_id);
            }
            if (false === $quote_saved || !$quote_id) {
                $wpdb->query('ROLLBACK');
                $migration_complete = false;
                error_log('[IDLM Quotations] Legacy quote row migration failed for lead ' . absint($lead->id)); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
                continue;
            }
            $revision_saved = $wpdb->insert(IDLM_Quotation_Repository::table('quote_revisions'), array('quote_id' => $quote_id, 'revision_number' => 1, 'revision_name' => $number . '-R01', 'status' => $status, 'revision_reason' => 'Legacy quote migration', 'currency_code' => get_option('idlm_currency_code', 'INR'), 'customer_name' => trim($lead->first_name . ' ' . $lead->last_name), 'customer_phone' => $lead->phone, 'customer_email' => $lead->email, 'project_name' => $lead->project_name, 'property_location' => $lead->property_location, 'subtotal' => (float) $lead->quote_value, 'taxable_total' => (float) $lead->quote_value, 'grand_total' => (float) $lead->quote_value, 'internal_notes' => $lead->quote_file ? 'Legacy quote file: ' . esc_url_raw($lead->quote_file) : '', 'created_by' => 0, 'created_at' => $now, 'updated_by' => 0, 'updated_at' => $now, 'finalized_at' => $lead->quote_shared ? $now : null));
            $revision_id = absint($wpdb->insert_id);
            $pointer_saved = $revision_saved && $revision_id ? $wpdb->update(IDLM_Quotation_Repository::table('quotes'), array('current_revision_id' => $revision_id), array('id' => $quote_id), array('%d'), array('%d')) : false;
            $history_saved = false !== $pointer_saved ? IDLM_Quotation_Repository::insert_history($quote_id, $revision_id, '', $status, 'Migrated from legacy lead quote fields.') : false;
            if (!$revision_saved || !$revision_id || false === $pointer_saved || false === $history_saved || false === $wpdb->query('COMMIT')) {
                $wpdb->query('ROLLBACK');
                $migration_complete = false;
                error_log('[IDLM Quotations] Legacy revision migration rolled back for lead ' . absint($lead->id)); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
            }
        }
        if ($migration_complete) {
            update_option('idlm_legacy_quote_migration_version', self::LEGACY_MIGRATION_VERSION);
        }
    }

    /**
     * Return complete numbering settings without depending on the UI facade.
     *
     * @return array
     */
    private static function numbering_settings() {
        return wp_parse_args((array) get_option('idlm_quote_settings', array()), array(
            'number_prefix' => 'Q',
            'number_separator' => '-',
            'number_padding' => 6,
        ));
    }
}
