<?php
/** Native effective access/default migration contracts. Builder: Shashi. */
idlm_integration_test('fresh role setup excludes ordinary editorial CRM and catalogue grants', static function(): void {
    foreach(array('editor','author','contributor') as $name) {
        $role=get_role($name);
        foreach(idlm_crm_default_capabilities(true) as $cap) { if($cap!=='read') { idlm_integration_assert_true(!$role->has_cap($cap),'Automatic grant remains: '.$name.'/'.$cap); } }
    }
    idlm_integration_assert_same('1',get_option('idlm_crm_access_policy_version'));
});
idlm_integration_test('legacy access migration preserves explicit scopes and dedicated revocations', static function(): void {
    $scope=IDLM_Admin_Users_Controller::role_scopes();
    add_role('native_crm_sales','Sales Executive',array('read'=>true,'idlm_access_leads'=>false,'idlm_view_quotes'=>true));
    $before=get_role('native_crm_sales')->capabilities;
    try {
        foreach(array('editor','author','contributor') as $name) { $role=get_role($name); foreach(idlm_crm_default_capabilities() as $cap){$role->add_cap($cap);} }
        update_option('idlm_roles_version','1.102.42'); delete_option('idlm_initialized_crm_roles'); delete_option('idlm_crm_access_policy_version');
        update_option('idlm_reps_see_all_leads','1'); update_option(IDLM_Settings::ROUTING_OPTION,array('idlm_reps_see_all_leads'=>'1','idlm_assignment_mode'=>'manual'));
        update_option('idlm_role_lead_scopes',array('author'=>'all','sales_manager'=>'assigned','native_crm_sales'=>'all'));
        idlm_integration_assert_same(true,idlm_register_crm_roles_and_capabilities());
        idlm_integration_assert_same('0',IDLM_Settings::get('idlm_reps_see_all_leads'));
        idlm_integration_assert_same('0',get_option(IDLM_Settings::ROUTING_OPTION)['idlm_reps_see_all_leads']);
        idlm_integration_assert_same(array('author'=>'all','sales_manager'=>'assigned','native_crm_sales'=>'all'),IDLM_Admin_Users_Controller::role_scopes());
        idlm_integration_assert_same($before,get_role('native_crm_sales')->capabilities);
        foreach(array('editor','author','contributor') as $name){idlm_integration_assert_true(!get_role($name)->has_cap('idlm_access_leads') && !get_role($name)->has_cap('idlm_view_quotes'));}
        // Subsequent explicit visibility changes and removed grants stay changed.
        update_option('idlm_reps_see_all_leads','1'); get_role('administrator')->remove_cap('idlm_download_quote_pdf');
        idlm_integration_assert_same(true,idlm_register_crm_roles_and_capabilities());
        idlm_integration_assert_same('1',IDLM_Settings::get('idlm_reps_see_all_leads'));
        idlm_integration_assert_true(!get_role('administrator')->has_cap('idlm_download_quote_pdf'),'Version setup restored revoked quote grant');
    } finally { get_role('administrator')->add_cap('idlm_download_quote_pdf'); remove_role('native_crm_sales'); update_option('idlm_role_lead_scopes',$scope); update_option('idlm_reps_see_all_leads','0'); }
});
idlm_integration_test('failed stored-role migration leaves policy marker behind and retry converges', static function(): void {
    global $wpdb;
    get_role('author')->add_cap('idlm_access_leads'); delete_option('idlm_crm_access_policy_version');
    $original_user = get_current_user_id();
    $legacy_user = wp_insert_user(array('user_login'=>'failed_migration_author', 'user_pass'=>wp_generate_password(), 'role'=>'author'));
    $trigger=$wpdb->prefix.'idlm_access_migration_failure'; $role_option=$wpdb->prefix.'user_roles';
    idlm_integration_assert_true(false!==$wpdb->query($wpdb->prepare("CREATE TRIGGER {$trigger} BEFORE UPDATE ON {$wpdb->options} FOR EACH ROW BEGIN IF NEW.option_name = %s THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Synthetic role persistence failure'; END IF; END",$role_option)));
    $previous=$wpdb->suppress_errors(true);
    try {
        idlm_integration_assert_same(false,idlm_register_crm_roles_and_capabilities());
        idlm_integration_assert_same('',get_option('idlm_crm_access_policy_version',''),'Failed migration advanced marker');
        idlm_integration_assert_true(get_role('author')->has_cap('idlm_access_leads'),'Failure fixture did not retain old grant');
        wp_set_current_user($legacy_user);
        idlm_integration_assert_true(current_user_can('idlm_access_leads'));
        idlm_integration_assert_same(false,IDLM_Lead_Access_Policy::has_rep_access(),'Pending failed migration allowed legacy editorial access');
        idlm_integration_assert_same((int)$legacy_user,IDLM_Lead_Access_Policy::restriction_user_id());
    } finally { wp_set_current_user($original_user); $wpdb->query("DROP TRIGGER {$trigger}"); $wpdb->suppress_errors($previous); }
    idlm_integration_assert_same(true,idlm_register_crm_roles_and_capabilities());
    idlm_integration_assert_true(!get_role('author')->has_cap('idlm_access_leads'));
    idlm_integration_assert_same('1',get_option('idlm_crm_access_policy_version'));
});
idlm_integration_test('newly encountered partial CRM role grants are not reconciled upward on update',static function(): void {
    add_role('native_sparse_manager','Sales Manager',array('read'=>true,'idlm_access_leads'=>true,'idlm_assign_leads'=>false,'idlm_view_quotes'=>false));
    $before=get_role('native_sparse_manager')->capabilities;
    try {
        update_option('idlm_roles_version','1.102.43');
        idlm_integration_assert_same(true,idlm_register_crm_roles_and_capabilities());
        idlm_integration_assert_same($before,get_role('native_sparse_manager')->capabilities);
        idlm_integration_assert_same(true,get_option('idlm_initialized_crm_roles')['native_sparse_manager']);
    } finally { remove_role('native_sparse_manager'); }
});
