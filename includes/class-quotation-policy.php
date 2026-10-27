<?php
/**
 * Quotation capability and object-level lead access policy.
 *
 * Builder: Shashi
 */

if (!defined('ABSPATH')) {
    exit;
}

final class IDLM_Quotation_Policy {
    /** Commercial evidence stays read-only even when an old migration erased status. */
    public static function is_editable_draft($quote, $revision) {
        if (!$quote || !$revision || 'Draft' !== ($quote->status ?? '') || 'Draft' !== ($revision->status ?? '') ||
            !absint($quote->id ?? 0) || absint($revision->quote_id ?? 0) !== absint($quote->id) ||
            !absint($revision->id ?? 0) || absint($quote->current_revision_id ?? 0) !== absint($revision->id) ||
            !empty($revision->finalized_at) || !empty($quote->accepted_revision_id) ||
            !empty($quote->shared_at) || !empty($quote->accepted_at) || !empty($quote->rejected_at)) {
            return false;
        }
        return !IDLM_Quotation_Repository::has_commercial_history($quote->id);
    }

    /**
     * Check a quotation capability for the current user.
     *
     * @param string $capability Capability name.
     * @return bool
     */
    public static function can($capability) {
        if (class_exists('IDLM_Admin_Users_Controller') && IDLM_Admin_Users_Controller::is_crm_access_disabled(get_current_user_id())) {
            return false;
        }
        if (IDLM_Lead_Access_Policy::is_pre_sales()) {
            return false;
        }
        // Catalogue/settings grants can stand alone. Customer quotation access
        // also requires current CRM enrollment, so its revocation is effective.
        if (!in_array((string) $capability, array('idlm_manage_quote_items', 'idlm_manage_quote_settings'), true) && !IDLM_Lead_Access_Policy::has_rep_access()) {
            return false;
        }
        return current_user_can((string) $capability);
    }

    /**
     * Determine whether the current user has unrestricted lead scope.
     *
     * @return bool
     */
    public static function can_manage_all_leads() {
        return IDLM_Lead_Access_Policy::can_assign();
    }

    /**
     * Determine whether the current user may view a lead in quotations.
     *
     * @param int $lead_id Lead ID.
     * @return bool
     */
    public static function can_view_lead($lead_id) {
        $lead_id = absint($lead_id);
        if (!$lead_id || !self::can('idlm_view_quotes')) {
            return false;
        }
        return IDLM_Quotation_Lead_Read_Model::lead_exists($lead_id);
    }

    /** Mutation scope is live ownership or an explicit assignment-manager grant.
     * $lock is reserved for callers owning a transaction; use a current locking
     * read so a repeatable-read snapshot cannot conceal reassignment.
     */
    public static function can_mutate_lead($lead_id, $lock = false) {
        if (!absint($lead_id) || !IDLM_Lead_Access_Policy::has_rep_access() || !self::can('idlm_view_quotes')) {
            return false;
        }
        $lead = IDLM_Quotation_Repository::get_lead_snapshot($lead_id, $lock);
        if ($lock && function_exists('clean_user_cache')) {
            // A lock wait may span a capability revocation in another request.
            $user_id = get_current_user_id();
            clean_user_cache($user_id);
            // Role grants are also cached for the lifetime of a request.
            if (function_exists('wp_roles')) {
                $roles = wp_roles();
                wp_cache_delete($roles->role_key, 'options');
                wp_cache_delete('alloptions', 'options');
                $roles->for_site();
            }
            wp_set_current_user(0);
            wp_set_current_user($user_id);
        }
        return IDLM_Lead_Access_Policy::has_rep_access() && self::can('idlm_view_quotes') && $lead && (self::can_manage_all_leads() || absint($lead->assigned_rep_id) === get_current_user_id());
    }

}
