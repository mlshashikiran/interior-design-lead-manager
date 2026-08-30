<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Builds Kanban display context and delegates markup to a guarded view.
 *
 * Builder: Shashi
 */
class IDLM_Admin_Kanban_Renderer {
    public static function render() {
        if (!IDLM_Admin::current_user_has_rep_access()) {
            IDLM_View::display('access-restricted.php', array('admin' => true));
            return;
        }

        $stages = get_option('idlm_custom_stages', array('New', 'Contacted', 'Qualified', 'Quote Shared', 'Negotiation', 'Won', 'Lost'));
        $currency_code = get_option('idlm_currency_code', 'INR');
        $currency_symbols = array(
            'INR' => '₹',
            'USD' => '$',
            'EUR' => '€',
            'GBP' => '£',
            'AED' => 'د.إ',
            'CAD' => 'C$',
            'AUD' => 'A$',
            'SGD' => 'S$',
        );

        IDLM_View::display('admin/kanban.php', array(
            'stages'              => $stages,
            'kanban_stage_totals' => IDLM_Admin::get_kanban_stage_total_mappings($stages),
            'call_statuses'       => get_option('idlm_custom_call_statuses', array('Not Called', 'No Answer', 'Call Back Requested', 'Connected - Interested', 'Connected - Not Interested', 'Wrong Number')),
            'lost_reasons'        => get_option('idlm_custom_lost_reasons', array('Budget Too High', 'Lost to Competitor', 'Project Postponed', 'Unable to Contact', 'Location or Service Unavailable', 'Timeline Mismatch', 'Client Not Interested', 'Duplicate or Invalid Lead', 'Other')),
            'reps'                => get_users(array(
                'role__in' => IDLM_Admin::get_allowed_rep_roles(),
                'fields'   => array('ID', 'display_name'),
            )),
            'currency_symbol'     => isset($currency_symbols[$currency_code]) ? $currency_symbols[$currency_code] : $currency_code,
            'can_assign_leads'    => IDLM_Admin::current_user_can_assign_leads(),
            'ajax_url'            => admin_url('admin-ajax.php'),
            'nonce'               => wp_create_nonce('idlm_admin_nonce'),
        ));
        IDLM_Admin::render_common_overlays();
    }
}
