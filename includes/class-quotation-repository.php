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

    /**
     * Reserve and format the next yearly quotation number atomically.
     *
     * @param array $settings Quotation numbering settings.
     * @return string|WP_Error
     */
    public static function reserve_quote_number($settings) {
        global $wpdb;
        $table = self::table('number_sequences');
        $year = absint(wp_date('Y'));
        if (false === $wpdb->query('START TRANSACTION')) {
            return new WP_Error('sequence_failed', 'Unable to start the quotation number reservation.');
        }
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
        // Table name is generated exclusively from the active WordPress prefix.
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT id, last_number FROM {$table} WHERE sequence_type = %s AND sequence_year = %d FOR UPDATE",
            'quotation',
            $year
        ));
        if ($row) {
            $next = absint($row->last_number) + 1;
            $ok = $wpdb->update($table, array('last_number' => $next, 'updated_at' => current_time('mysql')), array('id' => absint($row->id)), array('%d', '%s'), array('%d'));
        } else {
            $next = 1;
            $ok = $wpdb->insert($table, array('sequence_type' => 'quotation', 'sequence_year' => $year, 'last_number' => $next, 'updated_at' => current_time('mysql')), array('%s', '%d', '%d', '%s'));
        }
        if (false === $ok) {
            $wpdb->query('ROLLBACK');
            return new WP_Error('sequence_failed', 'Unable to reserve a quotation number.');
        }
        if (false === $wpdb->query('COMMIT')) {
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
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter
        return $wpdb->get_row($wpdb->prepare("SELECT * FROM " . self::table('quotes') . " WHERE id = %d", absint($quote_id)));
    }

    /**
     * Fetch a quotation revision row.
     *
     * @param int $revision_id Revision ID.
     * @return object|null
     */
    public static function get_revision($revision_id) {
        global $wpdb;
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter
        return $wpdb->get_row($wpdb->prepare("SELECT * FROM " . self::table('quote_revisions') . " WHERE id = %d", absint($revision_id)));
    }

    /**
     * Fetch the revision created for an idempotent save request.
     *
     * @param string $save_request_key UUID request key.
     * @return object|null
     */
    public static function get_revision_by_save_request_key($save_request_key) {
        global $wpdb;
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter
        return $wpdb->get_row($wpdb->prepare("SELECT id, quote_id FROM " . self::table('quote_revisions') . " WHERE save_request_key = %s", (string) $save_request_key));
    }

    /**
     * Fetch the lead fields snapshotted into a quotation revision.
     *
     * @param int $lead_id Lead ID.
     * @return object|null
     */
    public static function get_lead_snapshot($lead_id) {
        global $wpdb;
        return $wpdb->get_row($wpdb->prepare(
            "SELECT assigned_rep_id, first_name, last_name, phone, email, project_name, property_location FROM {$wpdb->prefix}idlm_leads WHERE id = %d",
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
        $inserted = $wpdb->insert(self::table('quotes'), $data, $format);
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
        return $wpdb->update(self::table('quotes'), $data, array('id' => absint($quote_id)), $format, array('%d'));
    }

    /**
     * Reserve the next revision number while the caller's transaction is active.
     *
     * @param int $quote_id Quotation ID.
     * @return int
     */
    public static function next_revision_number($quote_id) {
        global $wpdb;
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter
        return absint($wpdb->get_var($wpdb->prepare("SELECT MAX(revision_number) FROM " . self::table('quote_revisions') . " WHERE quote_id = %d FOR UPDATE", absint($quote_id)))) + 1;
    }

    /**
     * Insert a quotation revision and return its ID.
     *
     * @param array $data Revision row data.
     * @return int|false
     */
    public static function insert_revision($data) {
        global $wpdb;
        $inserted = $wpdb->insert(self::table('quote_revisions'), $data);
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
        return $wpdb->update(self::table('quote_revisions'), $data, array('id' => absint($revision_id)), null, array('%d'));
    }

    /**
     * Mark a revision as superseded.
     *
     * @return int|false
     */
    public static function supersede_revision($revision_id, $finalized_at) {
        global $wpdb;
        return $wpdb->update(
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
        if (false === $wpdb->delete(self::table('quote_revision_items'), array('revision_id' => $revision_id), array('%d'))) {
            return new WP_Error('revision_items_delete_failed', 'Unable to replace the quotation items.', 'Revision item cleanup failed');
        }
        if (false === $wpdb->delete(self::table('quote_revision_sections'), array('revision_id' => $revision_id), array('%d'))) {
            return new WP_Error('revision_sections_delete_failed', 'Unable to replace the quotation sections.', 'Revision section cleanup failed');
        }

        $section_ids = array();
        foreach ($sections as $key => $section) {
            $section['revision_id'] = $revision_id;
            if (!$wpdb->insert(self::table('quote_revision_sections'), $section)) {
                return new WP_Error('revision_section_insert_failed', 'Unable to save quotation sections.', 'Revision section insert failed');
            }
            $section_ids[$key] = absint($wpdb->insert_id);
        }

        foreach ($items as $item) {
            $section_key = $item['section_key'];
            unset($item['section_key']);
            $item['section_id'] = $section_ids[$section_key] ?? reset($section_ids);
            $item['revision_id'] = $revision_id;
            if (!$wpdb->insert(self::table('quote_revision_items'), $item)) {
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
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter
        return $wpdb->get_row($wpdb->prepare("SELECT * FROM " . self::table('quotes') . " WHERE id = %d FOR UPDATE", absint($quote_id)));
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
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter
        $source_sections = $wpdb->get_results($wpdb->prepare("SELECT * FROM " . self::table('quote_revision_sections') . " WHERE revision_id = %d ORDER BY sort_order, id", $source_revision_id), ARRAY_A);
        foreach ($source_sections as $section) {
            $old_section_id = absint($section['id']);
            unset($section['id']);
            $section['revision_id'] = $new_revision_id;
            if (!$wpdb->insert(self::table('quote_revision_sections'), $section)) {
                return new WP_Error('revision_section_copy_failed', 'Unable to copy the quotation sections.', 'Revision section copy failed');
            }
            $section_map[$old_section_id] = absint($wpdb->insert_id);
        }

        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter
        $items = $wpdb->get_results($wpdb->prepare("SELECT * FROM " . self::table('quote_revision_items') . " WHERE revision_id = %d ORDER BY sort_order, id", $source_revision_id), ARRAY_A);
        foreach ($items as $item) {
            unset($item['id']);
            $item['revision_id'] = $new_revision_id;
            $item['section_id'] = $section_map[absint($item['section_id'] ?? 0)] ?? 0;
            if (!$wpdb->insert(self::table('quote_revision_items'), $item)) {
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
        return $wpdb->update(
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
        return $wpdb->get_results($wpdb->prepare($query, $like, $like, $like, $per_page, $offset));
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
        return $wpdb->insert(self::table($definition['table']), array(
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
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        return (bool) $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM " . self::table($definition['table']) . " WHERE {$definition['column']} = %s AND status = 'active'",
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
        return $wpdb->query($wpdb->prepare($sql, (string) $name, absint($user_id), $now, $now));
    }

    /**
     * Fetch one catalogue item.
     *
     * @return object|null
     */
    public static function get_catalogue_item($item_id) {
        global $wpdb;
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter
        return $wpdb->get_row($wpdb->prepare("SELECT * FROM " . self::table('quote_catalogue_items') . " WHERE id = %d", absint($item_id)));
    }

    /**
     * Fetch the stored status for one catalogue item.
     *
     * @return string|null
     */
    public static function get_catalogue_item_status($item_id) {
        global $wpdb;
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter
        return $wpdb->get_var($wpdb->prepare("SELECT status FROM " . self::table('quote_catalogue_items') . " WHERE id = %d", absint($item_id)));
    }

    /**
     * Determine whether a catalogue item code already exists.
     *
     * @return bool
     */
    public static function catalogue_item_code_exists($item_code) {
        global $wpdb;
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter
        return (bool) $wpdb->get_var($wpdb->prepare("SELECT id FROM " . self::table('quote_catalogue_items') . " WHERE item_code = %s", (string) $item_code));
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
        $inserted = $wpdb->insert($table, $data);
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
        if (false === $wpdb->update($table, array('item_code' => $item_code), array('id' => $item_id), array('%s'), array('%d'))) {
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
        return $wpdb->update(self::table('quote_catalogue_items'), $data, array('id' => absint($item_id)));
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
            return $wpdb->query($wpdb->prepare($sql, array_merge(array($now), $item_ids)));
        }
        $sql = "DELETE FROM " . self::table('quote_catalogue_items') . " WHERE id IN ({$placeholders})";
        return $wpdb->query($wpdb->prepare($sql, $item_ids));
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
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $categories = $wpdb->get_results($wpdb->prepare("SELECT id, category_name FROM " . self::table('quote_item_categories') . " WHERE id IN ({$placeholders})", $category_ids));
        foreach ($categories as $category) {
            if ('delete' === $action) {
                // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter
                $in_use = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM " . self::table('quote_catalogue_items') . " WHERE category = %s", $category->category_name));
                if ($in_use) {
                    $result['blocked']++;
                    continue;
                }
                $write = $wpdb->delete(self::table('quote_item_categories'), array('id' => absint($category->id)), array('%d'));
            } else {
                $write = $wpdb->update(self::table('quote_item_categories'), array('status' => 'inactive', 'updated_at' => $now), array('id' => absint($category->id)), array('%s', '%s'), array('%d'));
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
            return (int) $wpdb->get_var("SELECT COUNT(*) FROM " . self::table('quote_catalogue_items'));
        }
        $like = '%' . $wpdb->esc_like($search) . '%';
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter
        return (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM " . self::table('quote_catalogue_items') . " WHERE item_name LIKE %s OR item_code LIKE %s OR category LIKE %s",
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
        return $wpdb->get_results($wpdb->prepare($query, $params));
    }

    /**
     * Fetch every catalogue category for management and active selection.
     *
     * @return array
     */
    public static function list_catalogue_categories() {
        global $wpdb;
        return $wpdb->get_results("SELECT id, category_name, status FROM " . self::table('quote_item_categories') . " ORDER BY status, category_name");
    }

    /**
     * Fetch active catalogue unit names.
     *
     * @return array
     */
    public static function list_active_catalogue_units() {
        global $wpdb;
        return $wpdb->get_col("SELECT unit_name FROM " . self::table('quote_item_units') . " WHERE status = 'active' ORDER BY unit_name");
    }

    /**
     * Fetch a bounded export page using keyset pagination.
     *
     * @return array
     */
    public static function get_catalogue_export_chunk($after_id, $limit = 500) {
        global $wpdb;
        $limit = min(500, max(1, absint($limit)));
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter
        return $wpdb->get_results($wpdb->prepare(
            "SELECT id, item_code, category, item_name, description, unit, IF(is_wood = 1, 'Yes', 'No') AS is_wood, default_rate, status FROM " . self::table('quote_catalogue_items') . " WHERE id > %d ORDER BY id LIMIT %d",
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
        $owner_user_id = absint($filters['owner_user_id'] ?? 0);
        if ($owner_user_id) {
            $where .= ' AND q.owner_user_id = %d';
            $params[] = $owner_user_id;
        }
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
        return (int) ($params ? $wpdb->get_var($wpdb->prepare($query, $params)) : $wpdb->get_var($query));
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
        $query = "SELECT q.*, r.revision_name, r.grand_total, r.valid_until, l.first_name, l.last_name, l.email, l.phone, l.project_name, u.display_name AS creator_name
                FROM " . self::table('quotes') . " q
                JOIN {$wpdb->prefix}idlm_leads l ON l.id = q.lead_id
                LEFT JOIN " . self::table('quote_revisions') . " r ON r.id = q.current_revision_id
                LEFT JOIN {$wpdb->users} u ON u.ID = q.created_by
                WHERE {$where} ORDER BY q.updated_at DESC LIMIT %d OFFSET %d";
        $params[] = $per_page;
        $params[] = ($page - 1) * $per_page;
        return $wpdb->get_results($wpdb->prepare($query, $params));
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
        $quote->lead = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}idlm_leads WHERE id = %d", $quote->lead_id));
        $revision_id = absint($revision_id);
        $quote->revision = self::get_revision($revision_id ?: $quote->current_revision_id);
        if ($quote->revision && absint($quote->revision->quote_id) !== absint($quote->id)) {
            return null;
        }
        if ($quote->revision) {
            $quote->sections = $wpdb->get_results($wpdb->prepare("SELECT * FROM " . self::table('quote_revision_sections') . " WHERE revision_id = %d ORDER BY sort_order, id", $quote->revision->id));
            // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter
            $quote->items = $wpdb->get_results($wpdb->prepare("SELECT i.*, s.section_name FROM " . self::table('quote_revision_items') . " i LEFT JOIN " . self::table('quote_revision_sections') . " s ON s.id = i.section_id WHERE i.revision_id = %d ORDER BY s.sort_order, i.sort_order, i.id", $quote->revision->id));
            // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter
        }
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter
        $quote->revisions = $wpdb->get_results($wpdb->prepare("SELECT * FROM " . self::table('quote_revisions') . " WHERE quote_id = %d ORDER BY revision_number DESC", absint($quote_id)));
        return $quote;
    }

    /**
     * Insert a quotation status-history record.
     *
     * @return int|false
     */
    public static function insert_history($quote_id, $revision_id, $from, $to, $note) {
        global $wpdb;
        return $wpdb->insert(self::table('quote_status_history'), array(
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
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter
        return (bool) $wpdb->get_var($wpdb->prepare("SELECT id FROM " . self::table('quotes') . " WHERE lead_id = %d LIMIT 1", absint($lead_id)));
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
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter
        $quote_ids = array_map('absint', (array) $wpdb->get_col($wpdb->prepare("SELECT id FROM " . self::table('quotes') . " WHERE lead_id = %d", absint($lead_id))));
        foreach ($quote_ids as $quote_id) {
            // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter
            $revision_ids = array_map('absint', (array) $wpdb->get_col($wpdb->prepare("SELECT id FROM " . self::table('quote_revisions') . " WHERE quote_id = %d", $quote_id)));
            if ($revision_ids) {
                $placeholders = implode(',', array_fill(0, count($revision_ids), '%d'));
                // Placeholders are generated from an absint-normalized ID array.
                // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter
                if (false === $wpdb->query($wpdb->prepare("DELETE FROM " . self::table('quote_revision_items') . " WHERE revision_id IN ({$placeholders})", $revision_ids))) { return false; }
                if (false === $wpdb->query($wpdb->prepare("DELETE FROM " . self::table('quote_revision_sections') . " WHERE revision_id IN ({$placeholders})", $revision_ids))) { return false; }
                // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter
                if (false === $wpdb->query($wpdb->prepare("DELETE FROM " . self::table('quote_payment_milestones') . " WHERE revision_id IN ({$placeholders})", $revision_ids))) { return false; }
            }
            if (false === $wpdb->delete(self::table('quote_status_history'), array('quote_id' => $quote_id), array('%d'))) { return false; }
            if (false === $wpdb->delete(self::table('quote_revisions'), array('quote_id' => $quote_id), array('%d'))) { return false; }
            if (false === $wpdb->delete(self::table('quotes'), array('id' => $quote_id), array('%d'))) { return false; }
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

        if (false === $wpdb->query('START TRANSACTION')) {
            return false;
        }
        foreach (array('quote_revision_items', 'quote_revision_sections', 'quote_payment_milestones', 'quote_status_history', 'quote_revisions', 'quotes', 'number_sequences') as $table) {
            // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter
            if (false === $wpdb->query('DELETE FROM ' . self::table($table))) {
                $wpdb->query('ROLLBACK');
                return false;
            }
        }
        if (false === $wpdb->query('COMMIT')) {
            $wpdb->query('ROLLBACK');
            return false;
        }
        delete_option('idlm_legacy_quote_migration_version');
        delete_option('idlm_quotation_snapshot_migration_version');
        return true;
    }
}
