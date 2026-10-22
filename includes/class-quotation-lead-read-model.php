<?php
/**
 * Scoped quotation lead queries.
 *
 * Builder: Shashi
 */

if (!defined('ABSPATH')) {
    exit;
}

final class IDLM_Quotation_Lead_Read_Model {
    /**
     * Append the current quotation lead-access scope to a query.
     *
     * @param string $alias  Lead table alias.
     * @param array  $params Prepared-query parameters.
     * @return string
     */
    private static function scope_sql($alias, &$params) {
        return IDLM_Lead_Access_Policy::scope_sql($alias, $params, false, false);
    }

    /**
     * Select the lead fields used by the quotation editor and search response.
     *
     * @return string
     */
    private static function editor_fields() {
        return 'l.id, l.first_name, l.last_name, l.phone, l.email, l.project_name, '
            . 'l.property_location, l.preferred_studio, l.assigned_rep_id, '
            . 'u.display_name AS assigned_rep_name';
    }

    /**
     * Determine whether the current user may access an existing lead.
     *
     * @param int $lead_id Lead ID.
     * @return bool
     */
    public static function lead_exists($lead_id) {
        global $wpdb;
        $lead_id = absint($lead_id);
        if (!$lead_id) {
            return false;
        }
        $params = array($lead_id);
        $scope = self::scope_sql('l', $params);
        $sql = "SELECT l.id FROM {$wpdb->prefix}idlm_leads l WHERE l.id = %d{$scope}";
        // phpcs:disable PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared -- REL007-SQL-0179: lead_exists(): fixed lead/user joins and projections; scope_sql uses existing live quote/lead policy, search/IDs/limits prepared; customer existence/scope must be current.
        return (bool) $wpdb->get_var($wpdb->prepare($sql, $params));
        // phpcs:enable PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared
    }

    /**
     * Search visible, canonical leads for the quotation lead picker.
     *
     * @param string $term Search term.
     * @param int    $limit Maximum rows.
     * @return array
     */
    public static function search($term, $limit = 20) {
        global $wpdb;
        $term = (string) $term;
        if (strlen($term) < 2) {
            return array();
        }
        $params = array();
        $scope = self::scope_sql('l', $params);
        $like = '%' . $wpdb->esc_like($term) . '%';
        array_push($params, $like, $like, $like, $like, $like, $like);
        $params[] = max(1, absint($limit));
        $sql = 'SELECT ' . self::editor_fields() . "
            FROM {$wpdb->prefix}idlm_leads l
            LEFT JOIN {$wpdb->users} u ON u.ID = l.assigned_rep_id
            WHERE l.is_duplicate = 0{$scope}
                AND (l.first_name LIKE %s OR l.last_name LIKE %s OR l.phone LIKE %s
                    OR l.email LIKE %s OR l.project_name LIKE %s OR l.property_location LIKE %s)
            ORDER BY l.first_name, l.last_name
            LIMIT %d";
        // phpcs:disable PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared -- REL007-SQL-0180: search(): fixed lead/user joins and projections; scope_sql uses existing live quote/lead policy, search/IDs/limits prepared; customer existence/scope must be current.
        return $wpdb->get_results($wpdb->prepare($sql, $params));
        // phpcs:enable PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared
    }

    /**
     * Load the initial visible lead choices for the quotation editor.
     *
     * @param int $limit Maximum rows.
     * @return array
     */
    public static function list_for_editor($limit = 50) {
        global $wpdb;
        $params = array();
        $scope = self::scope_sql('l', $params);
        $params[] = max(1, absint($limit));
        $sql = 'SELECT ' . self::editor_fields() . "
            FROM {$wpdb->prefix}idlm_leads l
            LEFT JOIN {$wpdb->users} u ON u.ID = l.assigned_rep_id
            WHERE l.is_duplicate = 0{$scope}
            ORDER BY l.first_name, l.last_name
            LIMIT %d";
        // phpcs:disable PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared -- REL007-SQL-0181: list_for_editor(): fixed lead/user joins and projections; scope_sql uses existing live quote/lead policy, search/IDs/limits prepared; customer existence/scope must be current.
        return $wpdb->get_results($wpdb->prepare($sql, $params));
        // phpcs:enable PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared
    }

    /**
     * Load one visible, canonical lead for editor selection and prefilling.
     *
     * @param int $lead_id Lead ID.
     * @return object|null
     */
    public static function get_selected_lead($lead_id) {
        global $wpdb;
        $lead_id = absint($lead_id);
        if (!$lead_id) {
            return null;
        }
        $params = array($lead_id);
        $scope = self::scope_sql('l', $params);
        $sql = 'SELECT ' . self::editor_fields() . "
            FROM {$wpdb->prefix}idlm_leads l
            LEFT JOIN {$wpdb->users} u ON u.ID = l.assigned_rep_id
            WHERE l.id = %d AND l.is_duplicate = 0{$scope}";
        // phpcs:disable PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared -- REL007-SQL-0182: get_selected_lead(): fixed lead/user joins and projections; scope_sql uses existing live quote/lead policy, search/IDs/limits prepared; customer existence/scope must be current.
        return $wpdb->get_row($wpdb->prepare($sql, $params));
        // phpcs:enable PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared
    }
}
