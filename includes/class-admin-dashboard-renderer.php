<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Builds the dashboard presentation context and delegates markup to a guarded view.
 *
 * Builder: Shashi
 */
class IDLM_Admin_Dashboard_Renderer {
    public static function render() {
        if (!IDLM_Admin::current_user_has_rep_access()) {
            IDLM_View::display('access-restricted.php', array('admin' => true));
            return;
        }

        // Dashboard data remains owned by IDLM_Dashboard and is loaded through
        // the established idlm_get_dashboard_stats AJAX contract.
        IDLM_View::display('admin/dashboard.php', array(
            'access' => IDLM_Dashboard::access_context(),
            'reps' => get_users(array('role__in' => IDLM_Lead_Access_Policy::allowed_rep_roles(), 'fields' => array('ID', 'display_name'))),
            'studios' => get_option('idlm_custom_studios', array('Downtown Studio', 'North Studio', 'South Studio')),
        ));
        IDLM_Admin::render_common_overlays();
    }
}
