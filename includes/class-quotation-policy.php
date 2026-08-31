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
}
