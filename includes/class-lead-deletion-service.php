<?php
/** Transactional lead deletion and orphan auditing. @author Shashi */
if (!defined('ABSPATH')) { exit; }

final class IDLM_Lead_Deletion_Service {
    private static function dependent_tables() {
        return array('idlm_lead_activity_log', 'idlm_capi_logs', 'idlm_tasks', 'idlm_lead_touchpoints', 'idlm_call_logs');
    }

    public static function delete($lead_id) { return self::delete_many(array($lead_id)); }

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
