# Runtime contracts catalogue

Author: Shashi. Reviewed working tree version 1.102.42; see source manifest for exact bytes.

Generated declarations are navigation evidence, not proof of authorization or successful execution. Each callback’s detailed chain is in the three module reviews. Dynamic exports and REST method variants are expanded below.

The [actual WordPress inventory](evidence/native-runtime-contracts.json) independently records the extracted candidate in the disposable fixture: 107 hook callbacks, five shortcodes and 11 REST method variants. Callback source, line, priority and accepted argument count are retained. Request handlers were not dispatched by discovery. The earlier [double inventory](evidence/runtime-contracts.json) has a narrower initialization scope; its repeated Interakt batch registration comes from explicitly initializing the batch after Interakt already initialized it. Actual WordPress registers that callback once. This is a discovery-harness duplicate, not evidence of double delivery. Core schema/boot hooks, cache invalidation, settings favicon and telemetry are better represented by the actual inventory.

For request chains, use the core review's **Request contracts**, the integration review's **Registered entry-point chains**, the quotation review's request/export contracts, and the architecture map's frontend/authentication/view contracts. The source declarations below preserve exact public signatures and registration expressions; initialized inventories resolve dynamically built export names and REST GET/POST/DELETE variants. Standard `__return_true` route permission callbacks are recorded by name in the native inventory; the corresponding webhook callback still enforces the configured integration/HMAC contract described in the review.

## PHP public compatibility methods

| File | Line | Method / signature |
|---|---:|---|
| `interior-design-lead-manager.php` | 80 | `function idlm_activate_plugin() {` |
| `interior-design-lead-manager.php` | 105 | `function idlm_register_crm_roles_and_capabilities() {` |
| `interior-design-lead-manager.php` | 187 | `function idlm_get_crm_role_slugs($scope = 'all') {` |
| `interior-design-lead-manager.php` | 217 | `function idlm_deactivate_plugin() {` |
| `interior-design-lead-manager.php` | 225 | `function idlm_boot_plugin() {` |
| `includes/class-admin-account-controller.php` | 14 | `public static function render() {` |
| `includes/class-admin-account-controller.php` | 34 | `public static function handle_update() {` |
| `includes/class-admin-ajax.php` | 13 | `public static function ajax_get_lead_details() {` |
| `includes/class-admin-ajax.php` | 20 | `public static function ajax_get_lead_activity() {` |
| `includes/class-admin-ajax.php` | 135 | `public static function ajax_quick_edit_lead() {` |
| `includes/class-admin-ajax.php` | 268 | `public static function ajax_log_presales_touch() {` |
| `includes/class-admin-ajax.php` | 434 | `public static function ajax_presales_decision() {` |
| `includes/class-admin-ajax.php` | 474 | `public static function ajax_save_activity_comment() {` |
| `includes/class-admin-ajax.php` | 521 | `public static function ajax_edit_activity_comment() {` |
| `includes/class-admin-ajax.php` | 564 | `public static function ajax_delete_activity_comment() {` |
| `includes/class-admin-ajax.php` | 605 | `public static function ajax_export_google_conversions() {` |
| `includes/class-admin-ajax.php` | 619 | `public static function ajax_export_leads() {` |
| `includes/class-admin-ajax.php` | 891 | `public static function ajax_add_lead_manual() {` |
| `includes/class-admin-ajax.php` | 969 | `public static function ajax_trigger_manual_audience_sync() {` |
| `includes/class-admin-ajax.php` | 984 | `public static function ajax_test_api_connection() {` |
| `includes/class-admin-ajax.php` | 1103 | `public static function ajax_retry_capi_events() {` |
| `includes/class-admin-ajax.php` | 1140 | `public static function ajax_log_js_error() {` |
| `includes/class-admin-ajax.php` | 1160 | `public static function ajax_clear_telemetry_logs() {` |
| `includes/class-admin-ajax.php` | 1178 | `public static function ajax_download_diagnostic_report() {` |
| `includes/class-admin-ajax.php` | 1279 | `public static function ajax_save_routing_settings() {` |
| `includes/class-admin-ajax.php` | 1372 | `public static function ajax_run_fallback_now() {` |
| `includes/class-admin-ajax.php` | 1397 | `public static function ajax_get_mapped_past_leads() {` |
| `includes/class-admin-ajax.php` | 1426 | `public static function ajax_sync_mapped_past_batch() {` |
| `includes/class-admin-ajax.php` | 1461 | `public static function ajax_bulk_action() {` |
| `includes/class-admin-ajax.php` | 1596 | `public static function ajax_get_leads_table() {` |
| `includes/class-admin-ajax.php` | 2239 | `public static function ajax_get_kanban_leads() {` |
| `includes/class-admin-ajax.php` | 2486 | `public static function ajax_get_dashboard_stats() {` |
| `includes/class-admin-ajax.php` | 2531 | `public static function ajax_save_lead_details() {` |
| `includes/class-admin-ajax.php` | 2642 | `public static function ajax_upload_lead_file() {` |
| `includes/class-admin-ajax.php` | 2701 | `public static function ajax_get_total_leads_count() {` |
| `includes/class-admin-ajax.php` | 2720 | `public static function ajax_reset_crm_data() {` |
| `includes/class-admin-ajax.php` | 2746 | `public static function ajax_delete_lead() {` |
| `includes/class-admin-ajax.php` | 2768 | `public static function ajax_import_leads_batch() {` |
| `includes/class-admin-ajax.php` | 2973 | `public static function ajax_get_lead_tasks() {` |
| `includes/class-admin-ajax.php` | 3020 | `public static function ajax_add_lead_task() {` |
| `includes/class-admin-ajax.php` | 3097 | `public static function ajax_toggle_task_status() {` |
| `includes/class-admin-ajax.php` | 3155 | `public static function ajax_delete_lead_task() {` |
| `includes/class-admin-ajax.php` | 3218 | `public static function ajax_get_rep_tasks() {` |
| `includes/class-admin-assets.php` | 32 | `public static function register_core_assets() {` |
| `includes/class-admin-assets.php` | 86 | `public static function enqueue_assets($hook) {` |
| `includes/class-admin-assets.php` | 91 | `public static function enqueue_frontend_assets() {` |
| `includes/class-admin-assets.php` | 131 | `public static function enqueue_shortcode_assets($context) {` |
| `includes/class-admin-dashboard-renderer.php` | 12 | `public static function render() {` |
| `includes/class-admin-kanban-renderer.php` | 12 | `public static function render() {` |
| `includes/class-admin-lead-notifications.php` | 27 | `public static function get_whatsapp_read_activity_ids() {` |
| `includes/class-admin-lead-notifications.php` | 41 | `public static function mark_whatsapp_reply_read($lead_id, $activity_id) {` |
| `includes/class-admin-lead-notifications.php` | 121 | `public static function get_unread_whatsapp_lead_ids() {` |
| `includes/class-admin-lead-notifications.php` | 127 | `public static function get_unread_whatsapp_count() {` |
| `includes/class-admin-lead-notifications.php` | 133 | `public static function get_whatsapp_received_lead_ids() {` |
| `includes/class-admin-lead-notifications.php` | 146 | `public static function get_reminder_rules() {` |
| `includes/class-admin-lead-notifications.php` | 160 | `public static function check_lead_overdue_status($lead, $rules = null) {` |
| `includes/class-admin-lead-notifications.php` | 207 | `public static function get_pending_calls_count() {` |
| `includes/class-admin-lead-notifications.php` | 242 | `public static function get_stale_lead_count($sales_board_only = false) {` |
| `includes/class-admin-lead-notifications.php` | 267 | `public static function get_no_recent_assigned_rep_action_sql($since, $lead_alias = '') {` |
| `includes/class-admin-lead-notifications.php` | 290 | `public static function get_stale_lead_cutoff() {` |
| `includes/class-admin-lead-reads.php` | 13 | `public static function ajax_mark_whatsapp_messages_read() {` |
| `includes/class-admin-lead-reads.php` | 44 | `public static function ajax_get_lead_whatsapp() {` |
| `includes/class-admin-lead-reads.php` | 100 | `public static function ajax_get_lead_details() {` |
| `includes/class-admin-leads-renderer.php` | 12 | `public static function render() {` |
| `includes/class-admin-leads-renderer.php` | 26 | `public static function render_common_overlays() {` |
| `includes/class-admin-logs-renderer.php` | 12 | `public static function render_capi() {` |
| `includes/class-admin-logs-renderer.php` | 48 | `public static function render_audience() {` |
| `includes/class-admin-logs-renderer.php` | 82 | `public static function ajax_get_capi_log_payload() {` |
| `includes/class-admin-logs-renderer.php` | 89 | `public static function ajax_get_audience_log_payload() {` |
| `includes/class-admin-presenter.php` | 14 | `public static function normalize_floor_plan_files($value, $legacy_url = '') {` |
| `includes/class-admin-presenter.php` | 40 | `public static function get_qualification_status_label($lead) {` |
| `includes/class-admin-presenter.php` | 54 | `public static function format_datetime($mysql_date, $format = 'M d, Y h:i A') {` |
| `includes/class-admin-presenter.php` | 67 | `public static function render_lead_table_datetime($mysql_date) {` |
| `includes/class-admin-presenter.php` | 80 | `public static function get_business_day_age($created_at) {` |
| `includes/class-admin-presenter.php` | 103 | `public static function render_activity_details($details) {` |
| `includes/class-admin-presenter.php` | 119 | `public static function get_kanban_stage_total_mappings($stages) {` |
| `includes/class-admin-settings-controller.php` | 16 | `public static function white_label_errors() {` |
| `includes/class-admin-settings-controller.php` | 21 | `public static function white_label_values() {` |
| `includes/class-admin-settings-controller.php` | 39 | `public static function handle_rotate_api_credential() {` |
| `includes/class-admin-settings-controller.php` | 69 | `public static function process_request() {` |
| `includes/class-admin-settings-renderer.php` | 12 | `public static function render() {` |
| `includes/class-admin-settings-renderer.php` | 31 | `public static function credential_value($credential) {` |
| `includes/class-admin-shortcodes.php` | 14 | `public static function workspace_url() {` |
| `includes/class-admin-shortcodes.php` | 41 | `public static function render_leads_list_shortcode() {` |
| `includes/class-admin-shortcodes.php` | 53 | `public static function render_dashboard_shortcode() {` |
| `includes/class-admin-shortcodes.php` | 65 | `public static function render_kanban_shortcode() {` |
| `includes/class-admin-shortcodes.php` | 77 | `public static function render_workspace_shortcode() {` |
| `includes/class-admin-users-controller.php` | 9 | `public static function render() {` |
| `includes/class-admin-users-controller.php` | 35 | `public static function handle_create() {` |
| `includes/class-admin-users-controller.php` | 54 | `public static function handle_update() {` |
| `includes/class-admin-users-controller.php` | 77 | `public static function handle_update_role_scopes() {` |
| `includes/class-admin-users-controller.php` | 93 | `public static function is_crm_access_disabled($user_id) { return '1' === (string) get_user_meta(absint($user_id), self::DISABLED_META_KEY, true); }` |
| `includes/class-admin-users-controller.php` | 95 | `public static function role_scopes() {` |
| `includes/class-admin-users-controller.php` | 100 | `public static function role_scope($roles) {` |
| `includes/class-admin-users-controller.php` | 113 | `public static function managed_roles() {` |
| `includes/class-admin.php` | 7 | `public static function init() {` |
| `includes/class-admin.php` | 71 | `public static function get_allowed_rep_roles() {` |
| `includes/class-admin.php` | 78 | `public static function current_user_can_assign_leads() {` |
| `includes/class-admin.php` | 85 | `public static function current_user_is_pre_sales() {` |
| `includes/class-admin.php` | 92 | `public static function get_qualification_status_label($lead) {` |
| `includes/class-admin.php` | 99 | `public static function is_assignable_rep($user_id) {` |
| `includes/class-admin.php` | 106 | `public static function get_pre_sales_user_ids() {` |
| `includes/class-admin.php` | 113 | `public static function current_user_can_access_lead($lead_id) {` |
| `includes/class-admin.php` | 121 | `public static function current_user_can_view_lead($lead_id) {` |
| `includes/class-admin.php` | 128 | `public static function format_datetime($mysql_date, $format = 'M d, Y h:i A') {` |
| `includes/class-admin.php` | 141 | `public static function render_activity_details($details) {` |
| `includes/class-admin.php` | 148 | `public static function current_user_has_rep_access() {` |
| `includes/class-admin.php` | 155 | `public static function get_whatsapp_read_activity_ids() {` |
| `includes/class-admin.php` | 162 | `public static function mark_whatsapp_reply_read($lead_id, $activity_id) {` |
| `includes/class-admin.php` | 169 | `public static function get_unread_whatsapp_lead_ids() {` |
| `includes/class-admin.php` | 178 | `public static function get_unread_whatsapp_count() {` |
| `includes/class-admin.php` | 185 | `public static function get_whatsapp_received_lead_ids() {` |
| `includes/class-admin.php` | 192 | `public static function get_kanban_stage_total_mappings($stages) {` |
| `includes/class-admin.php` | 200 | `public static function get_reminder_rules() {` |
| `includes/class-admin.php` | 207 | `public static function check_lead_overdue_status($lead, $rules = null) {` |
| `includes/class-admin.php` | 211 | `public static function get_pending_calls_count() {` |
| `includes/class-admin.php` | 218 | `public static function register_core_assets() {` |
| `includes/class-admin.php` | 225 | `public static function enqueue_assets($hook) {` |
| `includes/class-admin.php` | 232 | `public static function enqueue_frontend_assets() {` |
| `includes/class-admin.php` | 239 | `public static function render_leads_list_shortcode() {` |
| `includes/class-admin.php` | 246 | `public static function render_dashboard_shortcode() {` |
| `includes/class-admin.php` | 253 | `public static function render_kanban_shortcode() {` |
| `includes/class-admin.php` | 263 | `public static function render_workspace_shortcode() {` |
| `includes/class-admin.php` | 270 | `public static function render_my_account_page() {` |
| `includes/class-admin.php` | 277 | `public static function handle_update_my_account() {` |
| `includes/class-admin.php` | 282 | `public static function handle_rotate_api_credential() {` |
| `includes/class-admin.php` | 285 | `public static function handle_create_user() { return IDLM_Admin_Users_Controller::handle_create(); }` |
| `includes/class-admin.php` | 286 | `public static function handle_update_user() { return IDLM_Admin_Users_Controller::handle_update(); }` |
| `includes/class-admin.php` | 287 | `public static function handle_update_role_scopes() { return IDLM_Admin_Users_Controller::handle_update_role_scopes(); }` |
| `includes/class-admin.php` | 290 | `public static function register_admin_menus() {` |
| `includes/class-admin.php` | 303 | `public static function redirect_legacy_admin_page() {` |
| `includes/class-admin.php` | 348 | `public static function admin_utility_url() {` |
| `includes/class-admin.php` | 353 | `public static function render_admin_utility_page() {` |
| `includes/class-admin.php` | 377 | `public static function render_dashboard_page() {` |
| `includes/class-admin.php` | 380 | `public static function render_users_access_page() { return IDLM_Admin_Users_Controller::render(); }` |
| `includes/class-admin.php` | 385 | `public static function render_leads_list_page() {` |
| `includes/class-admin.php` | 392 | `public static function render_common_overlays() {` |
| `includes/class-admin.php` | 399 | `public static function render_kanban_page() {` |
| `includes/class-admin.php` | 406 | `public static function render_capi_logs_page() {` |
| `includes/class-admin.php` | 413 | `public static function render_audience_sync_logs_page() {` |
| `includes/class-admin.php` | 420 | `public static function render_settings_page() {` |
| `includes/class-admin.php` | 423 | `public static function ajax_get_lead_details() { return IDLM_Admin_Ajax::ajax_get_lead_details(); }` |
| `includes/class-admin.php` | 424 | `public static function ajax_get_lead_whatsapp() { return IDLM_Admin_Lead_Reads::ajax_get_lead_whatsapp(); }` |
| `includes/class-admin.php` | 425 | `public static function ajax_mark_whatsapp_messages_read() { return IDLM_Admin_Lead_Reads::ajax_mark_whatsapp_messages_read(); }` |
| `includes/class-admin.php` | 426 | `public static function ajax_get_lead_activity() { return IDLM_Admin_Ajax::ajax_get_lead_activity(); }` |
| `includes/class-admin.php` | 427 | `public static function ajax_get_capi_log_payload() { return IDLM_Admin_Logs_Renderer::ajax_get_capi_log_payload(); }` |
| `includes/class-admin.php` | 428 | `public static function ajax_get_audience_log_payload() { return IDLM_Admin_Logs_Renderer::ajax_get_audience_log_payload(); }` |
| `includes/class-admin.php` | 429 | `public static function ajax_quick_edit_lead() { return IDLM_Admin_Ajax::ajax_quick_edit_lead(); }` |
| `includes/class-admin.php` | 430 | `public static function ajax_log_presales_touch() { return IDLM_Admin_Ajax::ajax_log_presales_touch(); }` |
| `includes/class-admin.php` | 431 | `public static function ajax_presales_decision() { return IDLM_Admin_Ajax::ajax_presales_decision(); }` |
| `includes/class-admin.php` | 432 | `public static function ajax_save_activity_comment() { return IDLM_Admin_Ajax::ajax_save_activity_comment(); }` |
| `includes/class-admin.php` | 433 | `public static function ajax_edit_activity_comment() { return IDLM_Admin_Ajax::ajax_edit_activity_comment(); }` |
| `includes/class-admin.php` | 434 | `public static function ajax_delete_activity_comment() { return IDLM_Admin_Ajax::ajax_delete_activity_comment(); }` |
| `includes/class-admin.php` | 435 | `public static function ajax_export_google_conversions() { return IDLM_Admin_Ajax::ajax_export_google_conversions(); }` |
| `includes/class-admin.php` | 436 | `public static function ajax_export_leads() { return IDLM_Admin_Ajax::ajax_export_leads(); }` |
| `includes/class-admin.php` | 437 | `public static function ajax_add_lead_manual() { return IDLM_Admin_Ajax::ajax_add_lead_manual(); }` |
| `includes/class-admin.php` | 438 | `public static function ajax_trigger_manual_audience_sync() { return IDLM_Admin_Ajax::ajax_trigger_manual_audience_sync(); }` |
| `includes/class-admin.php` | 439 | `public static function ajax_test_api_connection() { return IDLM_Admin_Ajax::ajax_test_api_connection(); }` |
| `includes/class-admin.php` | 440 | `public static function ajax_retry_capi_events() { return IDLM_Admin_Ajax::ajax_retry_capi_events(); }` |
| `includes/class-admin.php` | 441 | `public static function ajax_log_js_error() { return IDLM_Admin_Ajax::ajax_log_js_error(); }` |
| `includes/class-admin.php` | 442 | `public static function ajax_clear_telemetry_logs() { return IDLM_Admin_Ajax::ajax_clear_telemetry_logs(); }` |
| `includes/class-admin.php` | 443 | `public static function ajax_download_diagnostic_report() { return IDLM_Admin_Ajax::ajax_download_diagnostic_report(); }` |
| `includes/class-admin.php` | 444 | `public static function ajax_save_routing_settings() { return IDLM_Admin_Ajax::ajax_save_routing_settings(); }` |
| `includes/class-admin.php` | 445 | `public static function ajax_run_fallback_now() { return IDLM_Admin_Ajax::ajax_run_fallback_now(); }` |
| `includes/class-admin.php` | 446 | `public static function ajax_get_mapped_past_leads() { return IDLM_Admin_Ajax::ajax_get_mapped_past_leads(); }` |
| `includes/class-admin.php` | 447 | `public static function ajax_sync_mapped_past_batch() { return IDLM_Admin_Ajax::ajax_sync_mapped_past_batch(); }` |
| `includes/class-admin.php` | 448 | `public static function ajax_bulk_action() { return IDLM_Admin_Ajax::ajax_bulk_action(); }` |
| `includes/class-admin.php` | 449 | `public static function ajax_get_leads_table() { return IDLM_Admin_Ajax::ajax_get_leads_table(); }` |
| `includes/class-admin.php` | 450 | `public static function ajax_get_kanban_leads() { return IDLM_Admin_Ajax::ajax_get_kanban_leads(); }` |
| `includes/class-admin.php` | 451 | `public static function ajax_get_dashboard_stats() { return IDLM_Admin_Ajax::ajax_get_dashboard_stats(); }` |
| `includes/class-admin.php` | 452 | `public static function ajax_save_lead_details() { return IDLM_Admin_Ajax::ajax_save_lead_details(); }` |
| `includes/class-admin.php` | 453 | `public static function ajax_upload_lead_file() { return IDLM_Admin_Ajax::ajax_upload_lead_file(); }` |
| `includes/class-admin.php` | 454 | `public static function ajax_get_total_leads_count() { return IDLM_Admin_Ajax::ajax_get_total_leads_count(); }` |
| `includes/class-admin.php` | 455 | `public static function ajax_reset_crm_data() { return IDLM_Admin_Ajax::ajax_reset_crm_data(); }` |
| `includes/class-admin.php` | 456 | `public static function ajax_delete_lead() { return IDLM_Admin_Ajax::ajax_delete_lead(); }` |
| `includes/class-admin.php` | 457 | `public static function ajax_import_leads_batch() { return IDLM_Admin_Ajax::ajax_import_leads_batch(); }` |
| `includes/class-admin.php` | 458 | `public static function ajax_get_lead_tasks() { return IDLM_Admin_Ajax::ajax_get_lead_tasks(); }` |
| `includes/class-admin.php` | 459 | `public static function ajax_add_lead_task() { return IDLM_Admin_Ajax::ajax_add_lead_task(); }` |
| `includes/class-admin.php` | 460 | `public static function ajax_toggle_task_status() { return IDLM_Admin_Ajax::ajax_toggle_task_status(); }` |
| `includes/class-admin.php` | 461 | `public static function ajax_delete_lead_task() { return IDLM_Admin_Ajax::ajax_delete_lead_task(); }` |
| `includes/class-admin.php` | 462 | `public static function ajax_get_rep_tasks() { return IDLM_Admin_Ajax::ajax_get_rep_tasks(); }` |
| `includes/class-audiences.php` | 10 | `public static function run_batch_sync() {` |
| `includes/class-audiences.php` | 200 | `public static function get_leads_matching_rule($rule) {` |
| `includes/class-audiences.php` | 225 | `public static function generate_google_ads_audience_csv_response($leads, $filename = 'google-ads-audience', $terminate = true) {` |
| `includes/class-auth.php` | 16 | `public static function init() {` |
| `includes/class-auth.php` | 26 | `public static function handle_login() {` |
| `includes/class-auth.php` | 47 | `public static function handle_password_reset_request() {` |
| `includes/class-auth.php` | 73 | `public static function handle_password_reset() {` |
| `includes/class-auth.php` | 106 | `public static function password_reset_message($message, $key, $user_login, $user_data) {` |
| `includes/class-auth.php` | 121 | `public static function reset_user($key, $login) {` |
| `includes/class-auth.php` | 129 | `public static function enqueue_reset_assets() {` |
| `includes/class-auth.php` | 142 | `public static function login_header_url($url) {` |
| `includes/class-auth.php` | 146 | `public static function login_header_text($text) {` |
| `includes/class-backup.php` | 10 | `public static function init() {` |
| `includes/class-backup.php` | 45 | `public static function export() {` |
| `includes/class-backup.php` | 89 | `public static function restore() {` |
| `includes/class-capi.php` | 7 | `public static function init() {` |
| `includes/class-capi.php` | 19 | `public static function handle_attribution_merged($lead_id, $merged_fields) {` |
| `includes/class-capi.php` | 136 | `public static function is_google_conversion_eligible($lead) {` |
| `includes/class-capi.php` | 220 | `public static function queue_capi_event($lead_id, $stage, $old_lead) {` |
| `includes/class-capi.php` | 269 | `public static function process_capi_event($lead_id, $stage) {` |
| `includes/class-capi.php` | 312 | `public static function fire_meta_capi($lead, $stage, $force = false) {` |
| `includes/class-capi.php` | 451 | `public static function fire_google_capi($lead, $stage, $force = false) {` |
| `includes/class-capi.php` | 546 | `public static function fire_linkedin_capi($lead, $stage, $force = false) {` |
| `includes/class-capi.php` | 645 | `public static function retry_capi_log($log_id) {` |
| `includes/class-capi.php` | 711 | `public static function get_google_ads_conversion_events() {` |
| `includes/class-capi.php` | 867 | `public static function generate_google_ads_conversions_csv_response($terminate = true) {` |
| `includes/class-capi.php` | 972 | `public static function format_phone_e164($phone) {` |
| `includes/class-capi.php` | 1008 | `public static function normalize_name_for_hash($name) {` |
| `includes/class-cursor-pagination.php` | 26 | `public static function encode($context, array $position, array $scope, $ttl = self::DEFAULT_TTL) {` |
| `includes/class-cursor-pagination.php` | 49 | `public static function decode($token, $context, array $scope) {` |
| `includes/class-dashboard-read-model.php` | 17 | `public static function get_stats($period, $custom_start = '', $custom_end = '', $filters = array()) {` |
| `includes/class-dashboard-read-model.php` | 33 | `public static function cache_context($period, $custom_start = '', $custom_end = '') {` |
| `includes/class-dashboard.php` | 8 | `public static function access_context() {` |
| `includes/class-dashboard.php` | 35 | `public static function filter_stats($stats) {` |
| `includes/class-dashboard.php` | 66 | `public static function get_records($kind, $value, $period, $start = '', $end = '', $page = 1) {` |
| `includes/class-dashboard.php` | 130 | `public static function get_stats($period = 'this_month', $custom_start = '', $custom_end = '', $filters = array()) {` |
| `includes/class-dashboard.php` | 139 | `public static function calculate_stats($period = 'this_month', $custom_start = '', $custom_end = '', $filters = array()) {` |
| `includes/class-dashboard.php` | 1524 | `public static function get_date_ranges($period, $custom_start = '', $custom_end = '') {` |
| `includes/class-data-retention.php` | 13 | `public static function init() {` |
| `includes/class-data-retention.php` | 26 | `public static function clear() {` |
| `includes/class-data-retention.php` | 30 | `public static function redact($value) {` |
| `includes/class-data-retention.php` | 45 | `public static function summarize_audience_payload($payload) {` |
| `includes/class-data-retention.php` | 67 | `public static function prune() {` |
| `includes/class-db.php` | 9 | `public static function get_last_validation_error() {` |
| `includes/class-db.php` | 12 | `public static function init() {` |
| `includes/class-db.php` | 80 | `public static function cleanup_stale_pending_tasks() {` |
| `includes/class-db.php` | 108 | `public static function archive_pending_tasks_for_lost_lead($lead_id) {` |
| `includes/class-db.php` | 359 | `public static function create_tables() {` |
| `includes/class-db.php` | 687 | `public static function budget_to_rupees($budget) {` |
| `includes/class-db.php` | 710 | `public static function get_presales_outreach_targets() {` |
| `includes/class-db.php` | 721 | `public static function calculate_presales_outreach_progress($daily_rows, $targets = null) {` |
| `includes/class-db.php` | 766 | `public static function refresh_presales_task_titles() {` |
| `includes/class-db.php` | 805 | `public static function get_followup_compliance($lead_id) {` |
| `includes/class-db.php` | 864 | `public static function create_presales_followup_tasks($lead_id, $assigned_rep_id) {` |
| `includes/class-db.php` | 910 | `public static function ensure_qualified_handoff_task($lead_id, $assigned_rep_id) {` |
| `includes/class-db.php` | 946 | `public static function sync_presales_task_status($lead_id) {` |
| `includes/class-db.php` | 992 | `public static function reschedule_pending_presales_tasks($lead_id) {` |
| `includes/class-db.php` | 1024 | `public static function roll_forward_presales_tasks() {` |
| `includes/class-db.php` | 1095 | `public static function upsert_inbound_lead($data, $platform, $payload = array()) {` |
| `includes/class-db.php` | 1102 | `public static function insert_lead($data) {` |
| `includes/class-db.php` | 1109 | `public static function update_lead($lead_id, $data) {` |
| `includes/class-db.php` | 1123 | `public static function find_canonical_duplicate_id($lead_id) {` |
| `includes/class-db.php` | 1155 | `public static function log_activity($lead_id, $activity_type, $details, $user_id = 0) {` |
| `includes/class-db.php` | 1178 | `public static function record_reporting_event($lead_id, $event_type, $from_value, $to_value, $reason = '', $event_origin = '', $actor_user_id = 0, $occurred_at = null, $amount = null) {` |
| `includes/class-db.php` | 1207 | `public static function log_capi_event($lead_id, $platform, $event_name, $payload, $response, $status) {` |
| `includes/class-db.php` | 1226 | `public static function log_audience_sync($platform, $audience_id, $lead_count, $payload, $response, $status) {` |
| `includes/class-horizontal-readiness.php` | 20 | `public static function report($context = null) {` |
| `includes/class-integrations.php` | 9 | `public static function init() {` |
| `includes/class-integrations.php` | 23 | `public static function add_google_feed_auth_challenge($response, $server, $request) {` |
| `includes/class-integrations.php` | 36 | `public static function maybe_migrate_api_credentials() {` |
| `includes/class-integrations.php` | 72 | `public static function rotate_api_credential($scope) {` |
| `includes/class-integrations.php` | 139 | `public static function permission_webhook(WP_REST_Request $request) {` |
| `includes/class-integrations.php` | 150 | `public static function permission_google_feed(WP_REST_Request $request) {` |
| `includes/class-integrations.php` | 161 | `public static function permission_mcp(WP_REST_Request $request) {` |
| `includes/class-integrations.php` | 236 | `public static function bypass_rest_auth_for_webhook($errors) {` |
| `includes/class-integrations.php` | 282 | `public static function inject_utm_tracker_script() {` |
| `includes/class-integrations.php` | 316 | `public static function get_captured_attribution() {` |
| `includes/class-integrations.php` | 344 | `public static function register_webhook_routes() {` |
| `includes/class-integrations.php` | 484 | `public static function handle_google_ads_conversions_feed(WP_REST_Request $request) {` |
| `includes/class-integrations.php` | 516 | `public static function handle_google_ads_audience_feed(WP_REST_Request $request) {` |
| `includes/class-integrations.php` | 578 | `public static function handle_webhook_request(WP_REST_Request $request) {` |
| `includes/class-integrations.php` | 844 | `public static function normalize_num_rooms($val) {` |
| `includes/class-integrations.php` | 913 | `public static function normalize_budget($val) {` |
| `includes/class-integrations.php` | 1088 | `public static function rep_has_capacity($rep_id) {` |
| `includes/class-integrations.php` | 1093 | `public static function get_rep_capacity_status($rep_id) {` |
| `includes/class-integrations.php` | 1318 | `public static function route_lead_assignment($lead_id, $source) {` |
| `includes/class-integrations.php` | 1574 | `public static function run_assignment_fallback() {` |
| `includes/class-integrations.php` | 1603 | `public static function get_elapsed_working_hours($start_time_str, $end_time_str, $work_start_str, $work_end_str, $work_days) {` |
| `includes/class-integrations.php` | 1685 | `public static function handle_mcp_get_info(WP_REST_Request $request) {` |
| `includes/class-integrations.php` | 1719 | `public static function handle_mcp_post(WP_REST_Request $request) {` |
| `includes/class-integrations.php` | 1856 | `public static function handle_mcp_delete_session(WP_REST_Request $request) {` |
| `includes/class-integrity-command.php` | 7 | `public function audit($args, $assoc_args) {` |
| `includes/class-interakt-batch.php` | 8 | `public static function init() {` |
| `includes/class-interakt-batch.php` | 12 | `public static function allowed_event($event) {` |
| `includes/class-interakt-batch.php` | 28 | `public static function enqueue($lead_id, $event, $request_id, $delay = 0) {` |
| `includes/class-interakt-batch.php` | 45 | `public static function process($key) {` |
| `includes/class-interakt.php` | 7 | `public static function init() {` |
| `includes/class-interakt.php` | 23 | `public static function inbox_url($phone) {` |
| `includes/class-interakt.php` | 40 | `public static function trigger_no_answer_automation($lead_id, $old_lead) {` |
| `includes/class-interakt.php` | 48 | `public static function trigger_generic_event($lead_id, $event_name, $old_lead = null) {` |
| `includes/class-interakt.php` | 130 | `public static function process_async_track_event($payload, $api_key, $lead_id = 0) {` |
| `includes/class-interakt.php` | 141 | `public static function format_phone_for_interakt($phone) {` |
| `includes/class-interakt.php` | 176 | `public static function sanitize_trait_value($val) {` |
| `includes/class-interakt.php` | 190 | `public static function send_track_event_to_interakt($payload, $api_key, $lead_id = 0) {` |
| `includes/class-interakt.php` | 238 | `public static function register_webhook_route() {` |
| `includes/class-interakt.php` | 262 | `public static function handle_webhook_request(WP_REST_Request $request) {` |
| `includes/class-interakt.php` | 328 | `public static function verify_webhook_signature($payload, $signature, $secret) {` |
| `includes/class-interakt.php` | 340 | `public static function log_customer_reply_to_lead($phone, $text) {` |
| `includes/class-interakt.php` | 368 | `public static function handle_meta_webhook_verification(WP_REST_Request $request) {` |
| `includes/class-interakt.php` | 403 | `public static function handle_meta_webhook_request(WP_REST_Request $request) {` |
| `includes/class-interakt.php` | 452 | `public static function verify_meta_webhook_signature($payload, $signature, $secret) {` |
| `includes/class-interakt.php` | 464 | `public static function log_meta_customer_reply_to_lead($phone, $text) {` |
| `includes/class-lead-access-policy.php` | 6 | `public static function allowed_rep_roles() {` |
| `includes/class-lead-access-policy.php` | 13 | `public static function has_rep_access() {` |
| `includes/class-lead-access-policy.php` | 20 | `public static function can_assign() {` |
| `includes/class-lead-access-policy.php` | 25 | `public static function is_pre_sales() {` |
| `includes/class-lead-access-policy.php` | 30 | `public static function validate_detail_fields($fields, $existing) {` |
| `includes/class-lead-access-policy.php` | 50 | `public static function is_assignable_rep($user_id) {` |
| `includes/class-lead-access-policy.php` | 57 | `public static function pre_sales_user_ids() {` |
| `includes/class-lead-access-policy.php` | 64 | `public static function restriction_user_id($respect_global_visibility = true) {` |
| `includes/class-lead-access-policy.php` | 74 | `public static function scope_sql($alias, &$params, $view_history = false, $respect_global_visibility = true) {` |
| `includes/class-lead-access-policy.php` | 86 | `public static function can_access($lead_id, $view_history = false) {` |
| `includes/class-lead-deletion-service.php` | 10 | `public static function delete($lead_id) { return self::delete_many(array($lead_id)); }` |
| `includes/class-lead-deletion-service.php` | 21 | `public static function reset_manifest() {` |
| `includes/class-lead-deletion-service.php` | 46 | `public static function reset_all($expected_lead_count = null) {` |
| `includes/class-lead-deletion-service.php` | 157 | `public static function delete_many($lead_ids) {` |
| `includes/class-lead-deletion-service.php` | 177 | `public static function audit_orphans($repair = false) {` |
| `includes/class-lead-lifecycle-service.php` | 14 | `public static function upsert_inbound_lead($data, $platform, $payload = array()) {` |
| `includes/class-lead-lifecycle-service.php` | 311 | `public static function insert_lead($data, $skip_duplicate_detection = false) {` |
| `includes/class-lead-lifecycle-service.php` | 541 | `public static function update_lead($lead_id, $data) {` |
| `includes/class-mcp-core.php` | 18 | `public static function get_tools_list() {` |
| `includes/class-mcp-core.php` | 112 | `public static function handle_json_rpc($request) {` |
| `includes/class-mcp-core.php` | 316 | `public static function execute_tool($name, $arguments) {` |
| `includes/class-operational-health.php` | 24 | `public static function report($context = null) {` |
| `includes/class-production-security.php` | 18 | `public static function environment_type() {` |
| `includes/class-production-security.php` | 26 | `public static function is_mcp_enabled() {` |
| `includes/class-production-security.php` | 39 | `public static function mcp_allowed_tools() {` |
| `includes/class-production-security.php` | 62 | `public static function is_mcp_tool_allowed($tool_name) {` |
| `includes/class-production-security.php` | 66 | `public static function mcp_payload_limit() {` |
| `includes/class-production-security.php` | 73 | `public static function mcp_batch_limit() {` |
| `includes/class-production-security.php` | 80 | `public static function mcp_query_row_limit() {` |
| `includes/class-production-security.php` | 87 | `public static function mcp_query_length_limit() {` |
| `includes/class-production-security.php` | 94 | `public static function mcp_request_time_limit() {` |
| `includes/class-production-security.php` | 103 | `public static function client_ip($server = null) {` |
| `includes/class-production-security.php` | 131 | `public static function trusted_proxies() {` |
| `includes/class-production-security.php` | 142 | `public static function is_trusted_proxy($address) {` |
| `includes/class-production-security.php` | 187 | `public static function upload_policy($purpose) {` |
| `includes/class-production-security.php` | 209 | `public static function validate_upload($file, $purpose) {` |
| `includes/class-production-security.php` | 247 | `public static function credential_fingerprint($credential) {` |
| `includes/class-production-security.php` | 251 | `public static function record_mcp_credential($credential, $previous_credential = '') {` |
| `includes/class-production-security.php` | 267 | `public static function audit_mcp($request, $rpc_request, $outcome, $elapsed_ms) {` |
| `includes/class-quotation-calculator.php` | 20 | `public static function clean_decimal($value, $minimum = 0) {` |
| `includes/class-quotation-calculator.php` | 35 | `public static function parse_items($raw_items, $quote_settings = array()) {` |
| `includes/class-quotation-calculator.php` | 100 | `public static function parse_sections($raw_sections) {` |
| `includes/class-quotation-calculator.php` | 128 | `public static function calculate_totals($items, $posted, $quote_settings = array()) {` |
| `includes/class-quotation-controller.php` | 13 | `public static function handle_save() {` |
| `includes/class-quotation-controller.php` | 195 | `public static function ajax_autosave_quote() {` |
| `includes/class-quotation-controller.php` | 241 | `public static function ajax_discard_quote_autosave() {` |
| `includes/class-quotation-controller.php` | 290 | `public static function ajax_search_catalogue() {` |
| `includes/class-quotation-controller.php` | 303 | `public static function ajax_search_leads() {` |
| `includes/class-quotation-controller.php` | 318 | `public static function ajax_filter_quotation_list() {` |
| `includes/class-quotation-controller.php` | 345 | `public static function ajax_filter_catalogue_list() {` |
| `includes/class-quotation-controller.php` | 377 | `public static function handle_save_catalogue_category() {` |
| `includes/class-quotation-controller.php` | 394 | `public static function handle_save_catalogue_unit() {` |
| `includes/class-quotation-controller.php` | 411 | `public static function handle_save_catalogue_item() {` |
| `includes/class-quotation-controller.php` | 452 | `public static function handle_archive_catalogue_item() {` |
| `includes/class-quotation-controller.php` | 462 | `public static function handle_bulk_catalogue_items() {` |
| `includes/class-quotation-controller.php` | 477 | `public static function handle_catalogue_category_action() {` |
| `includes/class-quotation-controller.php` | 495 | `public static function handle_import_catalogue_csv() {` |
| `includes/class-quotation-controller.php` | 594 | `public static function handle_export_catalogue_csv() {` |
| `includes/class-quotation-controller.php` | 618 | `public static function handle_save_settings() {` |
| `includes/class-quotation-exporter.php` | 17 | `public static function export_url($quote_id, $revision_id, $format) {` |
| `includes/class-quotation-exporter.php` | 32 | `public static function authorize($quote_id, $revision_id, $format, $verify_nonce = true) {` |
| `includes/class-quotation-exporter.php` | 53 | `public static function download($format) {` |
| `includes/class-quotation-exporter.php` | 69 | `public static function filename_stem($bundle) {` |
| `includes/class-quotation-exporter.php` | 78 | `public static function export_data($bundle) {` |
| `includes/class-quotation-exporter.php` | 152 | `public static function build_xlsx($bundle) {` |
| `includes/class-quotation-exporter.php` | 319 | `public static function csv_text($value) {` |
| `includes/class-quotation-lead-read-model.php` | 41 | `public static function lead_exists($lead_id) {` |
| `includes/class-quotation-lead-read-model.php` | 60 | `public static function search($term, $limit = 20) {` |
| `includes/class-quotation-lead-read-model.php` | 88 | `public static function list_for_editor($limit = 50) {` |
| `includes/class-quotation-lead-read-model.php` | 108 | `public static function get_selected_lead($lead_id) {` |
| `includes/class-quotation-policy.php` | 19 | `public static function can($capability) {` |
| `includes/class-quotation-policy.php` | 34 | `public static function can_manage_all_leads() {` |
| `includes/class-quotation-policy.php` | 44 | `public static function can_view_lead($lead_id) {` |
| `includes/class-quotation-renderer.php` | 13 | `public static function render_settings_page() {` |
| `includes/class-quotation-renderer.php` | 34 | `public static function render_page() {` |
| `includes/class-quotation-renderer.php` | 411 | `public static function render_print_view() {` |
| `includes/class-quotation-renderer.php` | 427 | `public static function render_print_document($bundle) {` |
| `includes/class-quotation-repository.php` | 19 | `public static function table($name) {` |
| `includes/class-quotation-repository.php` | 30 | `public static function reserve_quote_number($settings) {` |
| `includes/class-quotation-repository.php` | 73 | `public static function get_quote($quote_id) {` |
| `includes/class-quotation-repository.php` | 85 | `public static function get_quote_for_lead($lead_id) {` |
| `includes/class-quotation-repository.php` | 92 | `public static function get_lead_quote_value($lead_id) {` |
| `includes/class-quotation-repository.php` | 98 | `public static function acquire_lead_quote_lock($lead_id, $timeout = 5) {` |
| `includes/class-quotation-repository.php` | 105 | `public static function release_lead_quote_lock($lead_id) {` |
| `includes/class-quotation-repository.php` | 117 | `public static function get_revision($revision_id) {` |
| `includes/class-quotation-repository.php` | 129 | `public static function get_revision_by_save_request_key($save_request_key) {` |
| `includes/class-quotation-repository.php` | 141 | `public static function get_lead_snapshot($lead_id) {` |
| `includes/class-quotation-repository.php` | 156 | `public static function insert_quote($data, $format = null) {` |
| `includes/class-quotation-repository.php` | 168 | `public static function update_quote($quote_id, $data, $format = null) {` |
| `includes/class-quotation-repository.php` | 179 | `public static function next_revision_number($quote_id) {` |
| `includes/class-quotation-repository.php` | 191 | `public static function insert_revision($data) {` |
| `includes/class-quotation-repository.php` | 203 | `public static function update_revision($revision_id, $data) {` |
| `includes/class-quotation-repository.php` | 213 | `public static function supersede_revision($revision_id, $finalized_at) {` |
| `includes/class-quotation-repository.php` | 229 | `public static function set_current_revision($quote_id, $revision_id, $status, $updated_at) {` |
| `includes/class-quotation-repository.php` | 245 | `public static function replace_revision_contents($revision_id, $sections, $items) {` |
| `includes/class-quotation-repository.php` | 282 | `public static function lock_quote($quote_id) {` |
| `includes/class-quotation-repository.php` | 295 | `public static function copy_revision_contents($source_revision_id, $new_revision_id) {` |
| `includes/class-quotation-repository.php` | 330 | `public static function update_lead_quote_value($lead_id, $grand_total, $updated_at) {` |
| `includes/class-quotation-repository.php` | 346 | `public static function search_catalogue($term, $page, $per_page) {` |
| `includes/class-quotation-repository.php` | 376 | `public static function insert_catalogue_term($type, $name, $user_id, $now) {` |
| `includes/class-quotation-repository.php` | 396 | `public static function catalogue_term_exists($type, $name) {` |
| `includes/class-quotation-repository.php` | 415 | `public static function ensure_catalogue_term($type, $name, $user_id, $now) {` |
| `includes/class-quotation-repository.php` | 432 | `public static function get_catalogue_item($item_id) {` |
| `includes/class-quotation-repository.php` | 443 | `public static function get_catalogue_item_status($item_id) {` |
| `includes/class-quotation-repository.php` | 454 | `public static function catalogue_item_code_exists($item_code) {` |
| `includes/class-quotation-repository.php` | 465 | `public static function insert_catalogue_item($data) {` |
| `includes/class-quotation-repository.php` | 497 | `public static function update_catalogue_item($item_id, $data) {` |
| `includes/class-quotation-repository.php` | 507 | `public static function archive_catalogue_item($item_id, $now) {` |
| `includes/class-quotation-repository.php` | 516 | `public static function bulk_catalogue_items($item_ids, $action, $now) {` |
| `includes/class-quotation-repository.php` | 536 | `public static function apply_catalogue_category_action($category_ids, $action, $now) {` |
| `includes/class-quotation-repository.php` | 571 | `public static function count_catalogue_items($search = '') {` |
| `includes/class-quotation-repository.php` | 592 | `public static function list_catalogue_items($search, $page, $per_page, $sort_by = 'status', $sort_order = 'asc') {` |
| `includes/class-quotation-repository.php` | 627 | `public static function list_catalogue_categories() {` |
| `includes/class-quotation-repository.php` | 637 | `public static function list_active_catalogue_units() {` |
| `includes/class-quotation-repository.php` | 647 | `public static function get_catalogue_export_chunk($after_id, $limit = 500) {` |
| `includes/class-quotation-repository.php` | 704 | `public static function count_quotations($filters) {` |
| `includes/class-quotation-repository.php` | 716 | `public static function list_quotations($filters, $page, $per_page) {` |
| `includes/class-quotation-repository.php` | 739 | `public static function get_quote_bundle($quote_id, $revision_id = 0) {` |
| `includes/class-quotation-repository.php` | 767 | `public static function insert_history($quote_id, $revision_id, $from, $to, $note) {` |
| `includes/class-quotation-repository.php` | 786 | `public static function lead_has_quotes($lead_id) {` |
| `includes/class-quotation-repository.php` | 801 | `public static function delete_for_lead($lead_id) {` |
| `includes/class-quotation-repository.php` | 831 | `public static function reset_data() {` |
| `includes/class-quotation-schema.php` | 23 | `public static function install() {` |
| `includes/class-quotation-schema.php` | 254 | `public static function normalize_revision_lifecycle() {` |
| `includes/class-quotation-schema.php` | 273 | `public static function backfill_revision_snapshots_and_sections() {` |
| `includes/class-quotation-schema.php` | 298 | `public static function migrate_legacy_quotes() {` |
| `includes/class-quotations.php` | 21 | `public static function init() {` |
| `includes/class-quotations.php` | 52 | `public static function install() {` |
| `includes/class-quotations.php` | 59 | `public static function register_menu() {}` |
| `includes/class-quotations.php` | 61 | `public static function enqueue_assets($hook) {` |
| `includes/class-quotations.php` | 100 | `public static function view_url($args = array()) {` |
| `includes/class-quotations.php` | 117 | `public static function render_shortcode() {` |
| `includes/class-quotations.php` | 172 | `public static function handle_save() { return IDLM_Quotation_Controller::handle_save(); }` |
| `includes/class-quotations.php` | 173 | `public static function ajax_autosave_quote() { return IDLM_Quotation_Controller::ajax_autosave_quote(); }` |
| `includes/class-quotations.php` | 174 | `public static function ajax_discard_quote_autosave() { return IDLM_Quotation_Controller::ajax_discard_quote_autosave(); }` |
| `includes/class-quotations.php` | 175 | `public static function ajax_search_catalogue() { return IDLM_Quotation_Controller::ajax_search_catalogue(); }` |
| `includes/class-quotations.php` | 176 | `public static function ajax_search_leads() { return IDLM_Quotation_Controller::ajax_search_leads(); }` |
| `includes/class-quotations.php` | 177 | `public static function ajax_filter_quotation_list() { return IDLM_Quotation_Controller::ajax_filter_quotation_list(); }` |
| `includes/class-quotations.php` | 178 | `public static function ajax_filter_catalogue_list() { return IDLM_Quotation_Controller::ajax_filter_catalogue_list(); }` |
| `includes/class-quotations.php` | 179 | `public static function handle_save_catalogue_category() { return IDLM_Quotation_Controller::handle_save_catalogue_category(); }` |
| `includes/class-quotations.php` | 180 | `public static function handle_save_catalogue_unit() { return IDLM_Quotation_Controller::handle_save_catalogue_unit(); }` |
| `includes/class-quotations.php` | 181 | `public static function handle_save_catalogue_item() { return IDLM_Quotation_Controller::handle_save_catalogue_item(); }` |
| `includes/class-quotations.php` | 182 | `public static function handle_archive_catalogue_item() { return IDLM_Quotation_Controller::handle_archive_catalogue_item(); }` |
| `includes/class-quotations.php` | 183 | `public static function handle_bulk_catalogue_items() { return IDLM_Quotation_Controller::handle_bulk_catalogue_items(); }` |
| `includes/class-quotations.php` | 184 | `public static function handle_catalogue_category_action() { return IDLM_Quotation_Controller::handle_catalogue_category_action(); }` |
| `includes/class-quotations.php` | 185 | `public static function handle_import_catalogue_csv() { return IDLM_Quotation_Controller::handle_import_catalogue_csv(); }` |
| `includes/class-quotations.php` | 186 | `public static function handle_export_catalogue_csv() { return IDLM_Quotation_Controller::handle_export_catalogue_csv(); }` |
| `includes/class-quotations.php` | 187 | `public static function handle_save_settings() { return IDLM_Quotation_Controller::handle_save_settings(); }` |
| `includes/class-quotations.php` | 189 | `public static function render_settings_page() { return IDLM_Quotation_Renderer::render_settings_page(); }` |
| `includes/class-quotations.php` | 190 | `public static function render_page() { return IDLM_Quotation_Renderer::render_page(); }` |
| `includes/class-quotations.php` | 191 | `public static function render_print_view() { return IDLM_Quotation_Renderer::render_print_view(); }` |
| `includes/class-quotations.php` | 213 | `public static function lead_has_quotes($lead_id) {` |
| `includes/class-quotations.php` | 217 | `public static function delete_for_lead($lead_id) {` |
| `includes/class-quotations.php` | 221 | `public static function reset_data() {` |
| `includes/class-read-model-cache.php` | 25 | `public static function init() {` |
| `includes/class-read-model-cache.php` | 47 | `public static function remember($namespace, $context, $builder, $ttl = self::DEFAULT_TTL, $stale_ttl = self::DEFAULT_STALE_TTL) {` |
| `includes/class-read-model-cache.php` | 90 | `public static function invalidate($reason = '') {` |
| `includes/class-read-model-cache.php` | 99 | `public static function invalidate_on_option_change($option) {` |
| `includes/class-read-model-cache.php` | 113 | `public static function invalidate_after_retention() {` |
| `includes/class-read-model-cache.php` | 117 | `public static function generation() {` |
| `includes/class-routing-state.php` | 6 | `public static function install() {` |
| `includes/class-routing-state.php` | 20 | `public static function next_index($state_key, $modulus) {` |
| `includes/class-routing-state.php` | 39 | `public static function acquire_lock($name, $timeout = 0) {` |
| `includes/class-routing-state.php` | 44 | `public static function release_lock($name) {` |
| `includes/class-scheduler.php` | 7 | `public static function init() {` |
| `includes/class-scheduler.php` | 24 | `public static function setup_schedules() {` |
| `includes/class-scheduler.php` | 54 | `public static function clear_schedules() {` |
| `includes/class-secret-store.php` | 23 | `public static function get($option_name, $default = '') {` |
| `includes/class-secret-store.php` | 44 | `public static function set($option_name, $plaintext) {` |
| `includes/class-secret-store.php` | 54 | `public static function is_environment_managed($option_name) {` |
| `includes/class-settings.php` | 13 | `public static function init() {` |
| `includes/class-settings.php` | 22 | `public static function white_label_defaults() {` |
| `includes/class-settings.php` | 40 | `public static function get_white_label() {` |
| `includes/class-settings.php` | 55 | `public static function validate_white_label($raw) {` |
| `includes/class-settings.php` | 109 | `public static function brand_asset($attachment_id) {` |
| `includes/class-settings.php` | 124 | `public static function white_label_style($settings = null) {` |
| `includes/class-settings.php` | 135 | `public static function render_frontend_favicon() {` |
| `includes/class-settings.php` | 223 | `public static function routing_defaults() {` |
| `includes/class-settings.php` | 250 | `public static function get($option_name, $default = null) {` |
| `includes/class-settings.php` | 271 | `public static function sync_routing_from_legacy() {` |
| `includes/class-state-registry.php` | 26 | `public static function call_statuses() {` |
| `includes/class-state-registry.php` | 36 | `public static function handoff_statuses() {` |
| `includes/class-state-registry.php` | 44 | `public static function task_types() {` |
| `includes/class-state-registry.php` | 48 | `public static function disqualification_reasons() {` |
| `includes/class-state-registry.php` | 58 | `public static function values($domain) {` |
| `includes/class-state-registry.php` | 67 | `public static function stages() {` |
| `includes/class-state-registry.php` | 76 | `public static function stage_label($key_or_label) {` |
| `includes/class-state-registry.php` | 80 | `public static function label($domain, $key_or_label) {` |
| `includes/class-state-registry.php` | 86 | `public static function stage_key($label) {` |
| `includes/class-task-queue-service.php` | 13 | `public static function ajax_reschedule() {` |
| `includes/class-task-queue-service.php` | 39 | `public static function ajax_cleanup_stale() {` |
| `includes/class-telemetry.php` | 24 | `public static function init_handlers() {` |
| `includes/class-telemetry.php` | 41 | `public static function error_handler($errno, $errstr, $errfile, $errline) {` |
| `includes/class-telemetry.php` | 112 | `public static function exception_handler($exception) {` |
| `includes/class-telemetry.php` | 161 | `public static function log($type, $severity, $message, $file = '', $line = null, $trace = '') {` |
| `includes/class-telemetry.php` | 204 | `public static function prune_logs() {` |
| `includes/class-view.php` | 8 | `public static function render($template, $context = array()) {` |
| `includes/class-view.php` | 26 | `public static function display($template, $context = array()) {` |

## Registrations and emissions

The complete registration call-site catalogue includes boot hooks, lifecycle hooks, shortcodes, admin menus, anonymous admin-post authentication handlers, AJAX, REST, and CLI. Call-site context is retained to show multiline and facade registrations.

| File | Line | Registration source |
|---|---:|---|
| `interior-design-lead-manager.php` | 79 | `register_activation_hook(__FILE__, 'idlm_activate_plugin'); function idlm_activate_plugin() {     // 1. Run custom database migrations & table creations     IDLM_DB::create_tables();     if ('1.0.0' !== get_option('idlm_routing_schema_version', '')) {         IDLM_Routing_State::install();` |
| `interior-design-lead-manager.php` | 216 | `register_deactivation_hook(__FILE__, 'idlm_deactivate_plugin'); function idlm_deactivate_plugin() {     // Clear scheduled tasks to keep the database tidy     IDLM_Scheduler::clear_schedules();     IDLM_Data_Retention::clear(); }` |
| `interior-design-lead-manager.php` | 224 | `add_action('plugins_loaded', 'idlm_boot_plugin'); function idlm_boot_plugin() {     IDLM_Auth::init();      if (get_option('idlm_roles_version') !== IDLM_VERSION) {         idlm_register_crm_roles_and_capabilities();` |
| `includes/class-admin.php` | 8 | `add_action('admin_menu', array(__CLASS__, 'register_admin_menus'));         add_action('admin_init', array(__CLASS__, 'redirect_legacy_admin_page'));         add_action('wp_enqueue_scripts', array(__CLASS__, 'enqueue_frontend_assets'));         add_action('admin_post_idlm_update_my_account', array(__CLASS__, 'handle_update_my_account'));         add_action('admin_post_idlm_rotate_api_credential', array(__CLASS__, 'handle_rotate_api_credential'));         add_action('admin_post_idlm_create_user', array(__CLASS__, 'handle_create_user'));` |
| `includes/class-admin.php` | 9 | `add_action('admin_init', array(__CLASS__, 'redirect_legacy_admin_page'));         add_action('wp_enqueue_scripts', array(__CLASS__, 'enqueue_frontend_assets'));         add_action('admin_post_idlm_update_my_account', array(__CLASS__, 'handle_update_my_account'));         add_action('admin_post_idlm_rotate_api_credential', array(__CLASS__, 'handle_rotate_api_credential'));         add_action('admin_post_idlm_create_user', array(__CLASS__, 'handle_create_user'));         add_action('admin_post_idlm_update_user', array(__CLASS__, 'handle_update_user'));` |
| `includes/class-admin.php` | 10 | `add_action('wp_enqueue_scripts', array(__CLASS__, 'enqueue_frontend_assets'));         add_action('admin_post_idlm_update_my_account', array(__CLASS__, 'handle_update_my_account'));         add_action('admin_post_idlm_rotate_api_credential', array(__CLASS__, 'handle_rotate_api_credential'));         add_action('admin_post_idlm_create_user', array(__CLASS__, 'handle_create_user'));         add_action('admin_post_idlm_update_user', array(__CLASS__, 'handle_update_user'));         add_action('admin_post_idlm_update_role_scopes', array(__CLASS__, 'handle_update_role_scopes'));` |
| `includes/class-admin.php` | 11 | `add_action('admin_post_idlm_update_my_account', array(__CLASS__, 'handle_update_my_account'));         add_action('admin_post_idlm_rotate_api_credential', array(__CLASS__, 'handle_rotate_api_credential'));         add_action('admin_post_idlm_create_user', array(__CLASS__, 'handle_create_user'));         add_action('admin_post_idlm_update_user', array(__CLASS__, 'handle_update_user'));         add_action('admin_post_idlm_update_role_scopes', array(__CLASS__, 'handle_update_role_scopes'));` |
| `includes/class-admin.php` | 12 | `add_action('admin_post_idlm_rotate_api_credential', array(__CLASS__, 'handle_rotate_api_credential'));         add_action('admin_post_idlm_create_user', array(__CLASS__, 'handle_create_user'));         add_action('admin_post_idlm_update_user', array(__CLASS__, 'handle_update_user'));         add_action('admin_post_idlm_update_role_scopes', array(__CLASS__, 'handle_update_role_scopes'));          // Shortcodes for frontend lead manager & dashboard` |
| `includes/class-admin.php` | 13 | `add_action('admin_post_idlm_create_user', array(__CLASS__, 'handle_create_user'));         add_action('admin_post_idlm_update_user', array(__CLASS__, 'handle_update_user'));         add_action('admin_post_idlm_update_role_scopes', array(__CLASS__, 'handle_update_role_scopes'));          // Shortcodes for frontend lead manager & dashboard         add_shortcode('idlm_leads_list', array(__CLASS__, 'render_leads_list_shortcode'));` |
| `includes/class-admin.php` | 14 | `add_action('admin_post_idlm_update_user', array(__CLASS__, 'handle_update_user'));         add_action('admin_post_idlm_update_role_scopes', array(__CLASS__, 'handle_update_role_scopes'));          // Shortcodes for frontend lead manager & dashboard         add_shortcode('idlm_leads_list', array(__CLASS__, 'render_leads_list_shortcode'));         add_shortcode('idlm_dashboard', array(__CLASS__, 'render_dashboard_shortcode'));` |
| `includes/class-admin.php` | 15 | `add_action('admin_post_idlm_update_role_scopes', array(__CLASS__, 'handle_update_role_scopes'));          // Shortcodes for frontend lead manager & dashboard         add_shortcode('idlm_leads_list', array(__CLASS__, 'render_leads_list_shortcode'));         add_shortcode('idlm_dashboard', array(__CLASS__, 'render_dashboard_shortcode'));         add_shortcode('idlm_kanban', array(__CLASS__, 'render_kanban_shortcode'));` |
| `includes/class-admin.php` | 18 | `add_shortcode('idlm_leads_list', array(__CLASS__, 'render_leads_list_shortcode'));         add_shortcode('idlm_dashboard', array(__CLASS__, 'render_dashboard_shortcode'));         add_shortcode('idlm_kanban', array(__CLASS__, 'render_kanban_shortcode'));         add_shortcode('idlm_workspace', array(__CLASS__, 'render_workspace_shortcode'));          // AJAX endpoints` |
| `includes/class-admin.php` | 19 | `add_shortcode('idlm_dashboard', array(__CLASS__, 'render_dashboard_shortcode'));         add_shortcode('idlm_kanban', array(__CLASS__, 'render_kanban_shortcode'));         add_shortcode('idlm_workspace', array(__CLASS__, 'render_workspace_shortcode'));          // AJAX endpoints         add_action('wp_ajax_idlm_get_lead_details', array(__CLASS__, 'ajax_get_lead_details'));` |
| `includes/class-admin.php` | 20 | `add_shortcode('idlm_kanban', array(__CLASS__, 'render_kanban_shortcode'));         add_shortcode('idlm_workspace', array(__CLASS__, 'render_workspace_shortcode'));          // AJAX endpoints         add_action('wp_ajax_idlm_get_lead_details', array(__CLASS__, 'ajax_get_lead_details'));         add_action('wp_ajax_idlm_get_lead_whatsapp', array(__CLASS__, 'ajax_get_lead_whatsapp'));` |
| `includes/class-admin.php` | 21 | `add_shortcode('idlm_workspace', array(__CLASS__, 'render_workspace_shortcode'));          // AJAX endpoints         add_action('wp_ajax_idlm_get_lead_details', array(__CLASS__, 'ajax_get_lead_details'));         add_action('wp_ajax_idlm_get_lead_whatsapp', array(__CLASS__, 'ajax_get_lead_whatsapp'));         add_action('wp_ajax_idlm_mark_whatsapp_messages_read', array(__CLASS__, 'ajax_mark_whatsapp_messages_read'));` |
| `includes/class-admin.php` | 24 | `add_action('wp_ajax_idlm_get_lead_details', array(__CLASS__, 'ajax_get_lead_details'));         add_action('wp_ajax_idlm_get_lead_whatsapp', array(__CLASS__, 'ajax_get_lead_whatsapp'));         add_action('wp_ajax_idlm_mark_whatsapp_messages_read', array(__CLASS__, 'ajax_mark_whatsapp_messages_read'));         add_action('wp_ajax_idlm_get_lead_activity', array(__CLASS__, 'ajax_get_lead_activity'));         add_action('wp_ajax_idlm_get_capi_log_payload', array(__CLASS__, 'ajax_get_capi_log_payload'));         add_action('wp_ajax_idlm_get_audience_log_payload', array(__CLASS__, 'ajax_get_audience_log_payload'));` |
| `includes/class-admin.php` | 25 | `add_action('wp_ajax_idlm_get_lead_whatsapp', array(__CLASS__, 'ajax_get_lead_whatsapp'));         add_action('wp_ajax_idlm_mark_whatsapp_messages_read', array(__CLASS__, 'ajax_mark_whatsapp_messages_read'));         add_action('wp_ajax_idlm_get_lead_activity', array(__CLASS__, 'ajax_get_lead_activity'));         add_action('wp_ajax_idlm_get_capi_log_payload', array(__CLASS__, 'ajax_get_capi_log_payload'));         add_action('wp_ajax_idlm_get_audience_log_payload', array(__CLASS__, 'ajax_get_audience_log_payload'));         add_action('wp_ajax_idlm_quick_edit_lead', array(__CLASS__, 'ajax_quick_edit_lead'));` |
| `includes/class-admin.php` | 26 | `add_action('wp_ajax_idlm_mark_whatsapp_messages_read', array(__CLASS__, 'ajax_mark_whatsapp_messages_read'));         add_action('wp_ajax_idlm_get_lead_activity', array(__CLASS__, 'ajax_get_lead_activity'));         add_action('wp_ajax_idlm_get_capi_log_payload', array(__CLASS__, 'ajax_get_capi_log_payload'));         add_action('wp_ajax_idlm_get_audience_log_payload', array(__CLASS__, 'ajax_get_audience_log_payload'));         add_action('wp_ajax_idlm_quick_edit_lead', array(__CLASS__, 'ajax_quick_edit_lead'));         add_action('wp_ajax_idlm_save_activity_comment', array(__CLASS__, 'ajax_save_activity_comment'));` |
| `includes/class-admin.php` | 27 | `add_action('wp_ajax_idlm_get_lead_activity', array(__CLASS__, 'ajax_get_lead_activity'));         add_action('wp_ajax_idlm_get_capi_log_payload', array(__CLASS__, 'ajax_get_capi_log_payload'));         add_action('wp_ajax_idlm_get_audience_log_payload', array(__CLASS__, 'ajax_get_audience_log_payload'));         add_action('wp_ajax_idlm_quick_edit_lead', array(__CLASS__, 'ajax_quick_edit_lead'));         add_action('wp_ajax_idlm_save_activity_comment', array(__CLASS__, 'ajax_save_activity_comment'));         add_action('wp_ajax_idlm_edit_activity_comment', array(__CLASS__, 'ajax_edit_activity_comment'));` |
| `includes/class-admin.php` | 28 | `add_action('wp_ajax_idlm_get_capi_log_payload', array(__CLASS__, 'ajax_get_capi_log_payload'));         add_action('wp_ajax_idlm_get_audience_log_payload', array(__CLASS__, 'ajax_get_audience_log_payload'));         add_action('wp_ajax_idlm_quick_edit_lead', array(__CLASS__, 'ajax_quick_edit_lead'));         add_action('wp_ajax_idlm_save_activity_comment', array(__CLASS__, 'ajax_save_activity_comment'));         add_action('wp_ajax_idlm_edit_activity_comment', array(__CLASS__, 'ajax_edit_activity_comment'));         add_action('wp_ajax_idlm_delete_activity_comment', array(__CLASS__, 'ajax_delete_activity_comment'));` |
| `includes/class-admin.php` | 29 | `add_action('wp_ajax_idlm_get_audience_log_payload', array(__CLASS__, 'ajax_get_audience_log_payload'));         add_action('wp_ajax_idlm_quick_edit_lead', array(__CLASS__, 'ajax_quick_edit_lead'));         add_action('wp_ajax_idlm_save_activity_comment', array(__CLASS__, 'ajax_save_activity_comment'));         add_action('wp_ajax_idlm_edit_activity_comment', array(__CLASS__, 'ajax_edit_activity_comment'));         add_action('wp_ajax_idlm_delete_activity_comment', array(__CLASS__, 'ajax_delete_activity_comment'));         add_action('wp_ajax_idlm_export_leads', array(__CLASS__, 'ajax_export_leads'));` |
| `includes/class-admin.php` | 30 | `add_action('wp_ajax_idlm_quick_edit_lead', array(__CLASS__, 'ajax_quick_edit_lead'));         add_action('wp_ajax_idlm_save_activity_comment', array(__CLASS__, 'ajax_save_activity_comment'));         add_action('wp_ajax_idlm_edit_activity_comment', array(__CLASS__, 'ajax_edit_activity_comment'));         add_action('wp_ajax_idlm_delete_activity_comment', array(__CLASS__, 'ajax_delete_activity_comment'));         add_action('wp_ajax_idlm_export_leads', array(__CLASS__, 'ajax_export_leads'));         add_action('wp_ajax_idlm_export_google_conversions', array(__CLASS__, 'ajax_export_google_conversions'));` |
| `includes/class-admin.php` | 31 | `add_action('wp_ajax_idlm_save_activity_comment', array(__CLASS__, 'ajax_save_activity_comment'));         add_action('wp_ajax_idlm_edit_activity_comment', array(__CLASS__, 'ajax_edit_activity_comment'));         add_action('wp_ajax_idlm_delete_activity_comment', array(__CLASS__, 'ajax_delete_activity_comment'));         add_action('wp_ajax_idlm_export_leads', array(__CLASS__, 'ajax_export_leads'));         add_action('wp_ajax_idlm_export_google_conversions', array(__CLASS__, 'ajax_export_google_conversions'));         add_action('wp_ajax_idlm_add_lead_manual', array(__CLASS__, 'ajax_add_lead_manual'));` |
| `includes/class-admin.php` | 32 | `add_action('wp_ajax_idlm_edit_activity_comment', array(__CLASS__, 'ajax_edit_activity_comment'));         add_action('wp_ajax_idlm_delete_activity_comment', array(__CLASS__, 'ajax_delete_activity_comment'));         add_action('wp_ajax_idlm_export_leads', array(__CLASS__, 'ajax_export_leads'));         add_action('wp_ajax_idlm_export_google_conversions', array(__CLASS__, 'ajax_export_google_conversions'));         add_action('wp_ajax_idlm_add_lead_manual', array(__CLASS__, 'ajax_add_lead_manual'));         add_action('wp_ajax_idlm_trigger_manual_audience_sync', array(__CLASS__, 'ajax_trigger_manual_audience_sync'));` |
| `includes/class-admin.php` | 33 | `add_action('wp_ajax_idlm_delete_activity_comment', array(__CLASS__, 'ajax_delete_activity_comment'));         add_action('wp_ajax_idlm_export_leads', array(__CLASS__, 'ajax_export_leads'));         add_action('wp_ajax_idlm_export_google_conversions', array(__CLASS__, 'ajax_export_google_conversions'));         add_action('wp_ajax_idlm_add_lead_manual', array(__CLASS__, 'ajax_add_lead_manual'));         add_action('wp_ajax_idlm_trigger_manual_audience_sync', array(__CLASS__, 'ajax_trigger_manual_audience_sync'));         add_action('wp_ajax_idlm_bulk_action', array(__CLASS__, 'ajax_bulk_action'));` |
| `includes/class-admin.php` | 34 | `add_action('wp_ajax_idlm_export_leads', array(__CLASS__, 'ajax_export_leads'));         add_action('wp_ajax_idlm_export_google_conversions', array(__CLASS__, 'ajax_export_google_conversions'));         add_action('wp_ajax_idlm_add_lead_manual', array(__CLASS__, 'ajax_add_lead_manual'));         add_action('wp_ajax_idlm_trigger_manual_audience_sync', array(__CLASS__, 'ajax_trigger_manual_audience_sync'));         add_action('wp_ajax_idlm_bulk_action', array(__CLASS__, 'ajax_bulk_action'));         add_action('wp_ajax_idlm_get_leads_table', array(__CLASS__, 'ajax_get_leads_table'));` |
| `includes/class-admin.php` | 35 | `add_action('wp_ajax_idlm_export_google_conversions', array(__CLASS__, 'ajax_export_google_conversions'));         add_action('wp_ajax_idlm_add_lead_manual', array(__CLASS__, 'ajax_add_lead_manual'));         add_action('wp_ajax_idlm_trigger_manual_audience_sync', array(__CLASS__, 'ajax_trigger_manual_audience_sync'));         add_action('wp_ajax_idlm_bulk_action', array(__CLASS__, 'ajax_bulk_action'));         add_action('wp_ajax_idlm_get_leads_table', array(__CLASS__, 'ajax_get_leads_table'));         add_action('wp_ajax_idlm_get_kanban_leads', array(__CLASS__, 'ajax_get_kanban_leads'));` |
| `includes/class-admin.php` | 36 | `add_action('wp_ajax_idlm_add_lead_manual', array(__CLASS__, 'ajax_add_lead_manual'));         add_action('wp_ajax_idlm_trigger_manual_audience_sync', array(__CLASS__, 'ajax_trigger_manual_audience_sync'));         add_action('wp_ajax_idlm_bulk_action', array(__CLASS__, 'ajax_bulk_action'));         add_action('wp_ajax_idlm_get_leads_table', array(__CLASS__, 'ajax_get_leads_table'));         add_action('wp_ajax_idlm_get_kanban_leads', array(__CLASS__, 'ajax_get_kanban_leads'));         add_action('wp_ajax_idlm_get_dashboard_stats', array(__CLASS__, 'ajax_get_dashboard_stats'));` |
| `includes/class-admin.php` | 37 | `add_action('wp_ajax_idlm_trigger_manual_audience_sync', array(__CLASS__, 'ajax_trigger_manual_audience_sync'));         add_action('wp_ajax_idlm_bulk_action', array(__CLASS__, 'ajax_bulk_action'));         add_action('wp_ajax_idlm_get_leads_table', array(__CLASS__, 'ajax_get_leads_table'));         add_action('wp_ajax_idlm_get_kanban_leads', array(__CLASS__, 'ajax_get_kanban_leads'));         add_action('wp_ajax_idlm_get_dashboard_stats', array(__CLASS__, 'ajax_get_dashboard_stats'));         add_action('wp_ajax_idlm_save_lead_details', array(__CLASS__, 'ajax_save_lead_details'));` |
| `includes/class-admin.php` | 38 | `add_action('wp_ajax_idlm_bulk_action', array(__CLASS__, 'ajax_bulk_action'));         add_action('wp_ajax_idlm_get_leads_table', array(__CLASS__, 'ajax_get_leads_table'));         add_action('wp_ajax_idlm_get_kanban_leads', array(__CLASS__, 'ajax_get_kanban_leads'));         add_action('wp_ajax_idlm_get_dashboard_stats', array(__CLASS__, 'ajax_get_dashboard_stats'));         add_action('wp_ajax_idlm_save_lead_details', array(__CLASS__, 'ajax_save_lead_details'));         add_action('wp_ajax_idlm_delete_lead', array(__CLASS__, 'ajax_delete_lead'));` |
| `includes/class-admin.php` | 39 | `add_action('wp_ajax_idlm_get_leads_table', array(__CLASS__, 'ajax_get_leads_table'));         add_action('wp_ajax_idlm_get_kanban_leads', array(__CLASS__, 'ajax_get_kanban_leads'));         add_action('wp_ajax_idlm_get_dashboard_stats', array(__CLASS__, 'ajax_get_dashboard_stats'));         add_action('wp_ajax_idlm_save_lead_details', array(__CLASS__, 'ajax_save_lead_details'));         add_action('wp_ajax_idlm_delete_lead', array(__CLASS__, 'ajax_delete_lead'));         add_action('wp_ajax_idlm_import_leads_batch', array(__CLASS__, 'ajax_import_leads_batch'));` |
| `includes/class-admin.php` | 40 | `add_action('wp_ajax_idlm_get_kanban_leads', array(__CLASS__, 'ajax_get_kanban_leads'));         add_action('wp_ajax_idlm_get_dashboard_stats', array(__CLASS__, 'ajax_get_dashboard_stats'));         add_action('wp_ajax_idlm_save_lead_details', array(__CLASS__, 'ajax_save_lead_details'));         add_action('wp_ajax_idlm_delete_lead', array(__CLASS__, 'ajax_delete_lead'));         add_action('wp_ajax_idlm_import_leads_batch', array(__CLASS__, 'ajax_import_leads_batch'));         add_action('wp_ajax_idlm_upload_lead_file', array(__CLASS__, 'ajax_upload_lead_file'));` |
| `includes/class-admin.php` | 41 | `add_action('wp_ajax_idlm_get_dashboard_stats', array(__CLASS__, 'ajax_get_dashboard_stats'));         add_action('wp_ajax_idlm_save_lead_details', array(__CLASS__, 'ajax_save_lead_details'));         add_action('wp_ajax_idlm_delete_lead', array(__CLASS__, 'ajax_delete_lead'));         add_action('wp_ajax_idlm_import_leads_batch', array(__CLASS__, 'ajax_import_leads_batch'));         add_action('wp_ajax_idlm_upload_lead_file', array(__CLASS__, 'ajax_upload_lead_file'));         add_action('wp_ajax_idlm_get_total_leads_count', array(__CLASS__, 'ajax_get_total_leads_count'));` |
| `includes/class-admin.php` | 42 | `add_action('wp_ajax_idlm_save_lead_details', array(__CLASS__, 'ajax_save_lead_details'));         add_action('wp_ajax_idlm_delete_lead', array(__CLASS__, 'ajax_delete_lead'));         add_action('wp_ajax_idlm_import_leads_batch', array(__CLASS__, 'ajax_import_leads_batch'));         add_action('wp_ajax_idlm_upload_lead_file', array(__CLASS__, 'ajax_upload_lead_file'));         add_action('wp_ajax_idlm_get_total_leads_count', array(__CLASS__, 'ajax_get_total_leads_count'));         add_action('wp_ajax_idlm_reset_crm_data', array(__CLASS__, 'ajax_reset_crm_data'));` |
| `includes/class-admin.php` | 43 | `add_action('wp_ajax_idlm_delete_lead', array(__CLASS__, 'ajax_delete_lead'));         add_action('wp_ajax_idlm_import_leads_batch', array(__CLASS__, 'ajax_import_leads_batch'));         add_action('wp_ajax_idlm_upload_lead_file', array(__CLASS__, 'ajax_upload_lead_file'));         add_action('wp_ajax_idlm_get_total_leads_count', array(__CLASS__, 'ajax_get_total_leads_count'));         add_action('wp_ajax_idlm_reset_crm_data', array(__CLASS__, 'ajax_reset_crm_data'));         add_action('wp_ajax_idlm_test_api_connection', array(__CLASS__, 'ajax_test_api_connection'));` |
| `includes/class-admin.php` | 44 | `add_action('wp_ajax_idlm_import_leads_batch', array(__CLASS__, 'ajax_import_leads_batch'));         add_action('wp_ajax_idlm_upload_lead_file', array(__CLASS__, 'ajax_upload_lead_file'));         add_action('wp_ajax_idlm_get_total_leads_count', array(__CLASS__, 'ajax_get_total_leads_count'));         add_action('wp_ajax_idlm_reset_crm_data', array(__CLASS__, 'ajax_reset_crm_data'));         add_action('wp_ajax_idlm_test_api_connection', array(__CLASS__, 'ajax_test_api_connection'));         add_action('wp_ajax_idlm_retry_capi_events', array(__CLASS__, 'ajax_retry_capi_events'));` |
| `includes/class-admin.php` | 45 | `add_action('wp_ajax_idlm_upload_lead_file', array(__CLASS__, 'ajax_upload_lead_file'));         add_action('wp_ajax_idlm_get_total_leads_count', array(__CLASS__, 'ajax_get_total_leads_count'));         add_action('wp_ajax_idlm_reset_crm_data', array(__CLASS__, 'ajax_reset_crm_data'));         add_action('wp_ajax_idlm_test_api_connection', array(__CLASS__, 'ajax_test_api_connection'));         add_action('wp_ajax_idlm_retry_capi_events', array(__CLASS__, 'ajax_retry_capi_events'));         add_action('wp_ajax_idlm_log_js_error', array(__CLASS__, 'ajax_log_js_error'));` |
| `includes/class-admin.php` | 46 | `add_action('wp_ajax_idlm_get_total_leads_count', array(__CLASS__, 'ajax_get_total_leads_count'));         add_action('wp_ajax_idlm_reset_crm_data', array(__CLASS__, 'ajax_reset_crm_data'));         add_action('wp_ajax_idlm_test_api_connection', array(__CLASS__, 'ajax_test_api_connection'));         add_action('wp_ajax_idlm_retry_capi_events', array(__CLASS__, 'ajax_retry_capi_events'));         add_action('wp_ajax_idlm_log_js_error', array(__CLASS__, 'ajax_log_js_error'));         add_action('wp_ajax_idlm_clear_telemetry_logs', array(__CLASS__, 'ajax_clear_telemetry_logs'));` |
| `includes/class-admin.php` | 47 | `add_action('wp_ajax_idlm_reset_crm_data', array(__CLASS__, 'ajax_reset_crm_data'));         add_action('wp_ajax_idlm_test_api_connection', array(__CLASS__, 'ajax_test_api_connection'));         add_action('wp_ajax_idlm_retry_capi_events', array(__CLASS__, 'ajax_retry_capi_events'));         add_action('wp_ajax_idlm_log_js_error', array(__CLASS__, 'ajax_log_js_error'));         add_action('wp_ajax_idlm_clear_telemetry_logs', array(__CLASS__, 'ajax_clear_telemetry_logs'));         add_action('wp_ajax_idlm_download_diagnostic_report', array(__CLASS__, 'ajax_download_diagnostic_report'));` |
| `includes/class-admin.php` | 48 | `add_action('wp_ajax_idlm_test_api_connection', array(__CLASS__, 'ajax_test_api_connection'));         add_action('wp_ajax_idlm_retry_capi_events', array(__CLASS__, 'ajax_retry_capi_events'));         add_action('wp_ajax_idlm_log_js_error', array(__CLASS__, 'ajax_log_js_error'));         add_action('wp_ajax_idlm_clear_telemetry_logs', array(__CLASS__, 'ajax_clear_telemetry_logs'));         add_action('wp_ajax_idlm_download_diagnostic_report', array(__CLASS__, 'ajax_download_diagnostic_report'));         add_action('wp_ajax_idlm_save_routing_settings', array(__CLASS__, 'ajax_save_routing_settings'));` |
| `includes/class-admin.php` | 49 | `add_action('wp_ajax_idlm_retry_capi_events', array(__CLASS__, 'ajax_retry_capi_events'));         add_action('wp_ajax_idlm_log_js_error', array(__CLASS__, 'ajax_log_js_error'));         add_action('wp_ajax_idlm_clear_telemetry_logs', array(__CLASS__, 'ajax_clear_telemetry_logs'));         add_action('wp_ajax_idlm_download_diagnostic_report', array(__CLASS__, 'ajax_download_diagnostic_report'));         add_action('wp_ajax_idlm_save_routing_settings', array(__CLASS__, 'ajax_save_routing_settings'));         add_action('wp_ajax_idlm_run_fallback_now', array(__CLASS__, 'ajax_run_fallback_now'));` |
| `includes/class-admin.php` | 50 | `add_action('wp_ajax_idlm_log_js_error', array(__CLASS__, 'ajax_log_js_error'));         add_action('wp_ajax_idlm_clear_telemetry_logs', array(__CLASS__, 'ajax_clear_telemetry_logs'));         add_action('wp_ajax_idlm_download_diagnostic_report', array(__CLASS__, 'ajax_download_diagnostic_report'));         add_action('wp_ajax_idlm_save_routing_settings', array(__CLASS__, 'ajax_save_routing_settings'));         add_action('wp_ajax_idlm_run_fallback_now', array(__CLASS__, 'ajax_run_fallback_now'));         add_action('wp_ajax_idlm_log_presales_touch', array(__CLASS__, 'ajax_log_presales_touch'));` |
| `includes/class-admin.php` | 51 | `add_action('wp_ajax_idlm_clear_telemetry_logs', array(__CLASS__, 'ajax_clear_telemetry_logs'));         add_action('wp_ajax_idlm_download_diagnostic_report', array(__CLASS__, 'ajax_download_diagnostic_report'));         add_action('wp_ajax_idlm_save_routing_settings', array(__CLASS__, 'ajax_save_routing_settings'));         add_action('wp_ajax_idlm_run_fallback_now', array(__CLASS__, 'ajax_run_fallback_now'));         add_action('wp_ajax_idlm_log_presales_touch', array(__CLASS__, 'ajax_log_presales_touch'));         add_action('wp_ajax_idlm_presales_decision', array(__CLASS__, 'ajax_presales_decision'));` |
| `includes/class-admin.php` | 52 | `add_action('wp_ajax_idlm_download_diagnostic_report', array(__CLASS__, 'ajax_download_diagnostic_report'));         add_action('wp_ajax_idlm_save_routing_settings', array(__CLASS__, 'ajax_save_routing_settings'));         add_action('wp_ajax_idlm_run_fallback_now', array(__CLASS__, 'ajax_run_fallback_now'));         add_action('wp_ajax_idlm_log_presales_touch', array(__CLASS__, 'ajax_log_presales_touch'));         add_action('wp_ajax_idlm_presales_decision', array(__CLASS__, 'ajax_presales_decision'));         add_action('wp_ajax_idlm_get_mapped_past_leads', array(__CLASS__, 'ajax_get_mapped_past_leads'));` |
| `includes/class-admin.php` | 53 | `add_action('wp_ajax_idlm_save_routing_settings', array(__CLASS__, 'ajax_save_routing_settings'));         add_action('wp_ajax_idlm_run_fallback_now', array(__CLASS__, 'ajax_run_fallback_now'));         add_action('wp_ajax_idlm_log_presales_touch', array(__CLASS__, 'ajax_log_presales_touch'));         add_action('wp_ajax_idlm_presales_decision', array(__CLASS__, 'ajax_presales_decision'));         add_action('wp_ajax_idlm_get_mapped_past_leads', array(__CLASS__, 'ajax_get_mapped_past_leads'));         add_action('wp_ajax_idlm_sync_mapped_past_batch', array(__CLASS__, 'ajax_sync_mapped_past_batch'));` |
| `includes/class-admin.php` | 54 | `add_action('wp_ajax_idlm_run_fallback_now', array(__CLASS__, 'ajax_run_fallback_now'));         add_action('wp_ajax_idlm_log_presales_touch', array(__CLASS__, 'ajax_log_presales_touch'));         add_action('wp_ajax_idlm_presales_decision', array(__CLASS__, 'ajax_presales_decision'));         add_action('wp_ajax_idlm_get_mapped_past_leads', array(__CLASS__, 'ajax_get_mapped_past_leads'));         add_action('wp_ajax_idlm_sync_mapped_past_batch', array(__CLASS__, 'ajax_sync_mapped_past_batch'));` |
| `includes/class-admin.php` | 55 | `add_action('wp_ajax_idlm_log_presales_touch', array(__CLASS__, 'ajax_log_presales_touch'));         add_action('wp_ajax_idlm_presales_decision', array(__CLASS__, 'ajax_presales_decision'));         add_action('wp_ajax_idlm_get_mapped_past_leads', array(__CLASS__, 'ajax_get_mapped_past_leads'));         add_action('wp_ajax_idlm_sync_mapped_past_batch', array(__CLASS__, 'ajax_sync_mapped_past_batch'));          // Task Management Endpoints` |
| `includes/class-admin.php` | 56 | `add_action('wp_ajax_idlm_presales_decision', array(__CLASS__, 'ajax_presales_decision'));         add_action('wp_ajax_idlm_get_mapped_past_leads', array(__CLASS__, 'ajax_get_mapped_past_leads'));         add_action('wp_ajax_idlm_sync_mapped_past_batch', array(__CLASS__, 'ajax_sync_mapped_past_batch'));          // Task Management Endpoints         add_action('wp_ajax_idlm_get_lead_tasks', array(__CLASS__, 'ajax_get_lead_tasks'));` |
| `includes/class-admin.php` | 57 | `add_action('wp_ajax_idlm_get_mapped_past_leads', array(__CLASS__, 'ajax_get_mapped_past_leads'));         add_action('wp_ajax_idlm_sync_mapped_past_batch', array(__CLASS__, 'ajax_sync_mapped_past_batch'));          // Task Management Endpoints         add_action('wp_ajax_idlm_get_lead_tasks', array(__CLASS__, 'ajax_get_lead_tasks'));         add_action('wp_ajax_idlm_add_lead_task', array(__CLASS__, 'ajax_add_lead_task'));` |
| `includes/class-admin.php` | 58 | `add_action('wp_ajax_idlm_sync_mapped_past_batch', array(__CLASS__, 'ajax_sync_mapped_past_batch'));          // Task Management Endpoints         add_action('wp_ajax_idlm_get_lead_tasks', array(__CLASS__, 'ajax_get_lead_tasks'));         add_action('wp_ajax_idlm_add_lead_task', array(__CLASS__, 'ajax_add_lead_task'));         add_action('wp_ajax_idlm_toggle_task_status', array(__CLASS__, 'ajax_toggle_task_status'));` |
| `includes/class-admin.php` | 61 | `add_action('wp_ajax_idlm_get_lead_tasks', array(__CLASS__, 'ajax_get_lead_tasks'));         add_action('wp_ajax_idlm_add_lead_task', array(__CLASS__, 'ajax_add_lead_task'));         add_action('wp_ajax_idlm_toggle_task_status', array(__CLASS__, 'ajax_toggle_task_status'));         add_action('wp_ajax_idlm_delete_lead_task', array(__CLASS__, 'ajax_delete_lead_task'));         add_action('wp_ajax_idlm_get_rep_tasks', array(__CLASS__, 'ajax_get_rep_tasks'));     }` |
| `includes/class-admin.php` | 62 | `add_action('wp_ajax_idlm_add_lead_task', array(__CLASS__, 'ajax_add_lead_task'));         add_action('wp_ajax_idlm_toggle_task_status', array(__CLASS__, 'ajax_toggle_task_status'));         add_action('wp_ajax_idlm_delete_lead_task', array(__CLASS__, 'ajax_delete_lead_task'));         add_action('wp_ajax_idlm_get_rep_tasks', array(__CLASS__, 'ajax_get_rep_tasks'));     }` |
| `includes/class-admin.php` | 63 | `add_action('wp_ajax_idlm_toggle_task_status', array(__CLASS__, 'ajax_toggle_task_status'));         add_action('wp_ajax_idlm_delete_lead_task', array(__CLASS__, 'ajax_delete_lead_task'));         add_action('wp_ajax_idlm_get_rep_tasks', array(__CLASS__, 'ajax_get_rep_tasks'));     }      /**` |
| `includes/class-admin.php` | 64 | `add_action('wp_ajax_idlm_delete_lead_task', array(__CLASS__, 'ajax_delete_lead_task'));         add_action('wp_ajax_idlm_get_rep_tasks', array(__CLASS__, 'ajax_get_rep_tasks'));     }      /**      * Get list of allowed roles for representative users` |
| `includes/class-admin.php` | 65 | `add_action('wp_ajax_idlm_get_rep_tasks', array(__CLASS__, 'ajax_get_rep_tasks'));     }      /**      * Get list of allowed roles for representative users      */` |
| `includes/class-admin.php` | 291 | `add_menu_page(             'Lead Manager utilities',             'Lead Manager',             'manage_options',             'idlm_utilities',             array(__CLASS__, 'render_admin_utility_page'),` |
| `includes/class-auth.php` | 17 | `add_action('admin_post_nopriv_idlm_login', array(__CLASS__, 'handle_login'));         add_action('admin_post_nopriv_idlm_request_password_reset', array(__CLASS__, 'handle_password_reset_request'));         add_action('admin_post_nopriv_idlm_reset_password', array(__CLASS__, 'handle_password_reset'));         add_action('login_enqueue_scripts', array(__CLASS__, 'enqueue_reset_assets'));         add_filter('login_headerurl', array(__CLASS__, 'login_header_url'));         add_filter('login_headertext', array(__CLASS__, 'login_header_text'));` |
| `includes/class-auth.php` | 18 | `add_action('admin_post_nopriv_idlm_request_password_reset', array(__CLASS__, 'handle_password_reset_request'));         add_action('admin_post_nopriv_idlm_reset_password', array(__CLASS__, 'handle_password_reset'));         add_action('login_enqueue_scripts', array(__CLASS__, 'enqueue_reset_assets'));         add_filter('login_headerurl', array(__CLASS__, 'login_header_url'));         add_filter('login_headertext', array(__CLASS__, 'login_header_text'));     }` |
| `includes/class-auth.php` | 19 | `add_action('admin_post_nopriv_idlm_reset_password', array(__CLASS__, 'handle_password_reset'));         add_action('login_enqueue_scripts', array(__CLASS__, 'enqueue_reset_assets'));         add_filter('login_headerurl', array(__CLASS__, 'login_header_url'));         add_filter('login_headertext', array(__CLASS__, 'login_header_text'));     }` |
| `includes/class-auth.php` | 20 | `add_action('login_enqueue_scripts', array(__CLASS__, 'enqueue_reset_assets'));         add_filter('login_headerurl', array(__CLASS__, 'login_header_url'));         add_filter('login_headertext', array(__CLASS__, 'login_header_text'));     }      /** Authenticate this CRM form by email while preserving WordPress auth hooks and cookies. */` |
| `includes/class-auth.php` | 21 | `add_filter('login_headerurl', array(__CLASS__, 'login_header_url'));         add_filter('login_headertext', array(__CLASS__, 'login_header_text'));     }      /** Authenticate this CRM form by email while preserving WordPress auth hooks and cookies. */     public static function handle_login() {` |
| `includes/class-auth.php` | 22 | `add_filter('login_headertext', array(__CLASS__, 'login_header_text'));     }      /** Authenticate this CRM form by email while preserving WordPress auth hooks and cookies. */     public static function handle_login() {         check_admin_referer('idlm_login');` |
| `includes/class-auth.php` | 57 | `add_filter('retrieve_password_message', array(__CLASS__, 'password_reset_message'), 10, 4);                     try {                         retrieve_password($user->user_login);                     } finally {                         remove_filter('retrieve_password_message', array(__CLASS__, 'password_reset_message'), 10);                         self::$reset_return_url = '';` |
| `includes/class-backup.php` | 11 | `add_action('admin_post_idlm_export_backup', array(__CLASS__, 'export'));         add_action('admin_post_idlm_restore_backup', array(__CLASS__, 'restore'));     }      /** Tables are static plugin-owned names, ordered for a safe restore. */     private static function table_suffixes() {` |
| `includes/class-backup.php` | 12 | `add_action('admin_post_idlm_restore_backup', array(__CLASS__, 'restore'));     }      /** Tables are static plugin-owned names, ordered for a safe restore. */     private static function table_suffixes() {         return array(` |
| `includes/class-capi.php` | 9 | `add_action('idlm_lead_stage_converted', array(__CLASS__, 'queue_capi_event'), 10, 3);         // Hook for Action Scheduler execution         add_action('idlm_async_capi_upload_event', array(__CLASS__, 'process_capi_event'), 10, 2);         // Hook for handling duplicate lead attribution merges         add_action('idlm_lead_attribution_merged', array(__CLASS__, 'handle_attribution_merged'), 10, 2);     }` |
| `includes/class-capi.php` | 11 | `add_action('idlm_async_capi_upload_event', array(__CLASS__, 'process_capi_event'), 10, 2);         // Hook for handling duplicate lead attribution merges         add_action('idlm_lead_attribution_merged', array(__CLASS__, 'handle_attribution_merged'), 10, 2);     }      /**` |
| `includes/class-capi.php` | 13 | `add_action('idlm_lead_attribution_merged', array(__CLASS__, 'handle_attribution_merged'), 10, 2);     }      /**      * Re-fire CAPI events with updated attribution data when a duplicate lead merges its click IDs/UTMs.      */` |
| `includes/class-data-retention.php` | 14 | `add_action(self::HOOK, array(__CLASS__, 'prune'));         if (self::DIAGNOSTIC_LOG_RETENTION_VERSION !== get_option('idlm_diagnostic_log_retention_version', '')) {             self::prune_diagnostic_logs();             update_option('idlm_diagnostic_log_retention_version', self::DIAGNOSTIC_LOG_RETENTION_VERSION);             IDLM_Read_Model_Cache::invalidate('diagnostic_log_retention');         }` |
| `includes/class-integrations.php` | 11 | `add_action('wp_head', array(__CLASS__, 'inject_utm_tracker_script'));         add_action('rest_api_init', array(__CLASS__, 'register_webhook_routes'));         add_filter('rest_authentication_errors', array(__CLASS__, 'bypass_rest_auth_for_webhook'), 99999);         add_filter('rest_post_dispatch', array(__CLASS__, 'add_google_feed_auth_challenge'), 10, 3);          if ('1.0.1' !== get_option('idlm_handoff_reconciliation_version', '')) {` |
| `includes/class-integrations.php` | 12 | `add_action('rest_api_init', array(__CLASS__, 'register_webhook_routes'));         add_filter('rest_authentication_errors', array(__CLASS__, 'bypass_rest_auth_for_webhook'), 99999);         add_filter('rest_post_dispatch', array(__CLASS__, 'add_google_feed_auth_challenge'), 10, 3);          if ('1.0.1' !== get_option('idlm_handoff_reconciliation_version', '')) {             self::reconcile_legacy_qualified_handoffs();` |
| `includes/class-integrations.php` | 13 | `add_filter('rest_authentication_errors', array(__CLASS__, 'bypass_rest_auth_for_webhook'), 99999);         add_filter('rest_post_dispatch', array(__CLASS__, 'add_google_feed_auth_challenge'), 10, 3);          if ('1.0.1' !== get_option('idlm_handoff_reconciliation_version', '')) {             self::reconcile_legacy_qualified_handoffs();             update_option('idlm_handoff_reconciliation_version', '1.0.1');` |
| `includes/class-integrations.php` | 14 | `add_filter('rest_post_dispatch', array(__CLASS__, 'add_google_feed_auth_challenge'), 10, 3);          if ('1.0.1' !== get_option('idlm_handoff_reconciliation_version', '')) {             self::reconcile_legacy_qualified_handoffs();             update_option('idlm_handoff_reconciliation_version', '1.0.1');         }` |
| `includes/class-integrations.php` | 345 | `register_rest_route('interior-design-lead-manager/v1', '/webhook', array(             'methods' => 'POST',             'callback' => array(__CLASS__, 'handle_webhook_request'),             'permission_callback' => array(__CLASS__, 'permission_webhook')         ));` |
| `includes/class-integrations.php` | 351 | `register_rest_route('interior-design-lead-manager/v1', '/google-ads-conversions', array(             'methods' => 'GET',             'callback' => array(__CLASS__, 'handle_google_ads_conversions_feed'),             'permission_callback' => array(__CLASS__, 'permission_google_feed')         ));` |
| `includes/class-integrations.php` | 357 | `register_rest_route('interior-design-lead-manager/v1', '/google-ads-conversions\.csv', array(             'methods' => 'GET',             'callback' => array(__CLASS__, 'handle_google_ads_conversions_feed'),             'permission_callback' => array(__CLASS__, 'permission_google_feed')         ));` |
| `includes/class-integrations.php` | 363 | `register_rest_route('interior-design-lead-manager/v1', '/google-ads-audience', array(             'methods' => 'GET',             'callback' => array(__CLASS__, 'handle_google_ads_audience_feed'),             'permission_callback' => array(__CLASS__, 'permission_google_feed')         ));` |
| `includes/class-integrations.php` | 369 | `register_rest_route('interior-design-lead-manager/v1', '/google-ads-audience/(?P<list_id>[a-zA-Z0-9_-]+)\.csv', array(             'methods' => 'GET',             'callback' => array(__CLASS__, 'handle_google_ads_audience_feed'),             'permission_callback' => array(__CLASS__, 'permission_google_feed')         ));` |
| `includes/class-integrations.php` | 375 | `register_rest_route('interior-design-lead-manager/v1', '/mcp', array(             array(                 'methods' => 'GET',                 'callback' => array(__CLASS__, 'handle_mcp_get_info'),                 'permission_callback' => array(__CLASS__, 'permission_mcp')             ),` |
| `includes/class-integrity-command.php` | 18 | `WP_CLI::add_command('idlm integrity', 'IDLM_Integrity_Command'); }` |
| `includes/class-interakt-batch.php` | 9 | `add_action(self::HOOK, array(__CLASS__, 'process'), 10, 1);     }      public static function allowed_event($event) {         foreach ((array) get_option('idlm_interakt_mappings', array()) as $mapping) {             if (isset($mapping['event']) && $event === $mapping['event']) { return true; }` |
| `includes/class-interakt.php` | 10 | `add_action('idlm_lead_call_status_no_answer', array(__CLASS__, 'trigger_no_answer_automation'), 10, 2);          // Generalized hook for mapped Interakt triggers         add_action('idlm_trigger_interakt_event', array(__CLASS__, 'trigger_generic_event'), 10, 3);                  // Hook for Action Scheduler execution` |
| `includes/class-interakt.php` | 13 | `add_action('idlm_trigger_interakt_event', array(__CLASS__, 'trigger_generic_event'), 10, 3);                  // Hook for Action Scheduler execution         add_action('idlm_async_interakt_track_event', array(__CLASS__, 'process_async_track_event'), 10, 3);          // Hook for incoming WhatsApp Webhook listener` |
| `includes/class-interakt.php` | 16 | `add_action('idlm_async_interakt_track_event', array(__CLASS__, 'process_async_track_event'), 10, 3);          // Hook for incoming WhatsApp Webhook listener         add_action('rest_api_init', array(__CLASS__, 'register_webhook_route'));     }` |
| `includes/class-interakt.php` | 19 | `add_action('rest_api_init', array(__CLASS__, 'register_webhook_route'));     }      /** Return the filtered Interakt Inbox URL for a customer phone number. */     public static function inbox_url($phone) {         $formatted_phone = self::format_phone_for_interakt($phone);` |
| `includes/class-interakt.php` | 239 | `register_rest_route('idlm/v1', '/interakt-webhook', array(             'methods'             => 'POST',             'callback'            => array(__CLASS__, 'handle_webhook_request'),             'permission_callback' => '__return_true', // Validated via HMAC signature         ));` |
| `includes/class-interakt.php` | 245 | `register_rest_route('idlm/v1', '/meta-webhook', array(             array(                 'methods'             => 'GET',                 'callback'            => array(__CLASS__, 'handle_meta_webhook_verification'),                 'permission_callback' => '__return_true',             ),` |
| `includes/class-quotations.php` | 29 | `add_action('admin_post_idlm_save_quote', array(__CLASS__, 'handle_save'));         add_action('admin_post_idlm_print_quote', array(__CLASS__, 'render_print_view'));         add_action('admin_post_idlm_download_quote_pdf', static function() { IDLM_Quotation_Exporter::download('pdf'); });         add_action('admin_post_idlm_download_quote_csv', static function() { IDLM_Quotation_Exporter::download('csv'); });         add_action('admin_post_idlm_download_quote_xlsx', static function() { IDLM_Quotation_Exporter::download('xlsx'); });         add_action('admin_post_idlm_save_quote_settings', array(__CLASS__, 'handle_save_settings'));` |
| `includes/class-quotations.php` | 30 | `add_action('admin_post_idlm_print_quote', array(__CLASS__, 'render_print_view'));         add_action('admin_post_idlm_download_quote_pdf', static function() { IDLM_Quotation_Exporter::download('pdf'); });         add_action('admin_post_idlm_download_quote_csv', static function() { IDLM_Quotation_Exporter::download('csv'); });         add_action('admin_post_idlm_download_quote_xlsx', static function() { IDLM_Quotation_Exporter::download('xlsx'); });         add_action('admin_post_idlm_save_quote_settings', array(__CLASS__, 'handle_save_settings'));         add_action('admin_post_idlm_save_catalogue_item', array(__CLASS__, 'handle_save_catalogue_item'));` |
| `includes/class-quotations.php` | 31 | `add_action('admin_post_idlm_download_quote_pdf', static function() { IDLM_Quotation_Exporter::download('pdf'); });         add_action('admin_post_idlm_download_quote_csv', static function() { IDLM_Quotation_Exporter::download('csv'); });         add_action('admin_post_idlm_download_quote_xlsx', static function() { IDLM_Quotation_Exporter::download('xlsx'); });         add_action('admin_post_idlm_save_quote_settings', array(__CLASS__, 'handle_save_settings'));         add_action('admin_post_idlm_save_catalogue_item', array(__CLASS__, 'handle_save_catalogue_item'));         add_action('admin_post_idlm_save_catalogue_category', array(__CLASS__, 'handle_save_catalogue_category'));` |
| `includes/class-quotations.php` | 32 | `add_action('admin_post_idlm_download_quote_csv', static function() { IDLM_Quotation_Exporter::download('csv'); });         add_action('admin_post_idlm_download_quote_xlsx', static function() { IDLM_Quotation_Exporter::download('xlsx'); });         add_action('admin_post_idlm_save_quote_settings', array(__CLASS__, 'handle_save_settings'));         add_action('admin_post_idlm_save_catalogue_item', array(__CLASS__, 'handle_save_catalogue_item'));         add_action('admin_post_idlm_save_catalogue_category', array(__CLASS__, 'handle_save_catalogue_category'));         add_action('admin_post_idlm_save_catalogue_unit', array(__CLASS__, 'handle_save_catalogue_unit'));` |
| `includes/class-quotations.php` | 33 | `add_action('admin_post_idlm_download_quote_xlsx', static function() { IDLM_Quotation_Exporter::download('xlsx'); });         add_action('admin_post_idlm_save_quote_settings', array(__CLASS__, 'handle_save_settings'));         add_action('admin_post_idlm_save_catalogue_item', array(__CLASS__, 'handle_save_catalogue_item'));         add_action('admin_post_idlm_save_catalogue_category', array(__CLASS__, 'handle_save_catalogue_category'));         add_action('admin_post_idlm_save_catalogue_unit', array(__CLASS__, 'handle_save_catalogue_unit'));         add_action('admin_post_idlm_archive_catalogue_item', array(__CLASS__, 'handle_archive_catalogue_item'));` |
| `includes/class-quotations.php` | 34 | `add_action('admin_post_idlm_save_quote_settings', array(__CLASS__, 'handle_save_settings'));         add_action('admin_post_idlm_save_catalogue_item', array(__CLASS__, 'handle_save_catalogue_item'));         add_action('admin_post_idlm_save_catalogue_category', array(__CLASS__, 'handle_save_catalogue_category'));         add_action('admin_post_idlm_save_catalogue_unit', array(__CLASS__, 'handle_save_catalogue_unit'));         add_action('admin_post_idlm_archive_catalogue_item', array(__CLASS__, 'handle_archive_catalogue_item'));         add_action('admin_post_idlm_bulk_catalogue_items', array(__CLASS__, 'handle_bulk_catalogue_items'));` |
| `includes/class-quotations.php` | 35 | `add_action('admin_post_idlm_save_catalogue_item', array(__CLASS__, 'handle_save_catalogue_item'));         add_action('admin_post_idlm_save_catalogue_category', array(__CLASS__, 'handle_save_catalogue_category'));         add_action('admin_post_idlm_save_catalogue_unit', array(__CLASS__, 'handle_save_catalogue_unit'));         add_action('admin_post_idlm_archive_catalogue_item', array(__CLASS__, 'handle_archive_catalogue_item'));         add_action('admin_post_idlm_bulk_catalogue_items', array(__CLASS__, 'handle_bulk_catalogue_items'));         add_action('admin_post_idlm_catalogue_category_action', array(__CLASS__, 'handle_catalogue_category_action'));` |
| `includes/class-quotations.php` | 36 | `add_action('admin_post_idlm_save_catalogue_category', array(__CLASS__, 'handle_save_catalogue_category'));         add_action('admin_post_idlm_save_catalogue_unit', array(__CLASS__, 'handle_save_catalogue_unit'));         add_action('admin_post_idlm_archive_catalogue_item', array(__CLASS__, 'handle_archive_catalogue_item'));         add_action('admin_post_idlm_bulk_catalogue_items', array(__CLASS__, 'handle_bulk_catalogue_items'));         add_action('admin_post_idlm_catalogue_category_action', array(__CLASS__, 'handle_catalogue_category_action'));         add_action('admin_post_idlm_import_catalogue_csv', array(__CLASS__, 'handle_import_catalogue_csv'));` |
| `includes/class-quotations.php` | 37 | `add_action('admin_post_idlm_save_catalogue_unit', array(__CLASS__, 'handle_save_catalogue_unit'));         add_action('admin_post_idlm_archive_catalogue_item', array(__CLASS__, 'handle_archive_catalogue_item'));         add_action('admin_post_idlm_bulk_catalogue_items', array(__CLASS__, 'handle_bulk_catalogue_items'));         add_action('admin_post_idlm_catalogue_category_action', array(__CLASS__, 'handle_catalogue_category_action'));         add_action('admin_post_idlm_import_catalogue_csv', array(__CLASS__, 'handle_import_catalogue_csv'));         add_action('admin_post_idlm_export_catalogue_csv', array(__CLASS__, 'handle_export_catalogue_csv'));` |
| `includes/class-quotations.php` | 38 | `add_action('admin_post_idlm_archive_catalogue_item', array(__CLASS__, 'handle_archive_catalogue_item'));         add_action('admin_post_idlm_bulk_catalogue_items', array(__CLASS__, 'handle_bulk_catalogue_items'));         add_action('admin_post_idlm_catalogue_category_action', array(__CLASS__, 'handle_catalogue_category_action'));         add_action('admin_post_idlm_import_catalogue_csv', array(__CLASS__, 'handle_import_catalogue_csv'));         add_action('admin_post_idlm_export_catalogue_csv', array(__CLASS__, 'handle_export_catalogue_csv'));         add_action('wp_ajax_idlm_search_quote_catalogue', array(__CLASS__, 'ajax_search_catalogue'));` |
| `includes/class-quotations.php` | 39 | `add_action('admin_post_idlm_bulk_catalogue_items', array(__CLASS__, 'handle_bulk_catalogue_items'));         add_action('admin_post_idlm_catalogue_category_action', array(__CLASS__, 'handle_catalogue_category_action'));         add_action('admin_post_idlm_import_catalogue_csv', array(__CLASS__, 'handle_import_catalogue_csv'));         add_action('admin_post_idlm_export_catalogue_csv', array(__CLASS__, 'handle_export_catalogue_csv'));         add_action('wp_ajax_idlm_search_quote_catalogue', array(__CLASS__, 'ajax_search_catalogue'));         add_action('wp_ajax_idlm_search_quote_leads', array(__CLASS__, 'ajax_search_leads'));` |
| `includes/class-quotations.php` | 40 | `add_action('admin_post_idlm_catalogue_category_action', array(__CLASS__, 'handle_catalogue_category_action'));         add_action('admin_post_idlm_import_catalogue_csv', array(__CLASS__, 'handle_import_catalogue_csv'));         add_action('admin_post_idlm_export_catalogue_csv', array(__CLASS__, 'handle_export_catalogue_csv'));         add_action('wp_ajax_idlm_search_quote_catalogue', array(__CLASS__, 'ajax_search_catalogue'));         add_action('wp_ajax_idlm_search_quote_leads', array(__CLASS__, 'ajax_search_leads'));         add_action('wp_ajax_idlm_filter_quotation_list', array(__CLASS__, 'ajax_filter_quotation_list'));` |
| `includes/class-quotations.php` | 41 | `add_action('admin_post_idlm_import_catalogue_csv', array(__CLASS__, 'handle_import_catalogue_csv'));         add_action('admin_post_idlm_export_catalogue_csv', array(__CLASS__, 'handle_export_catalogue_csv'));         add_action('wp_ajax_idlm_search_quote_catalogue', array(__CLASS__, 'ajax_search_catalogue'));         add_action('wp_ajax_idlm_search_quote_leads', array(__CLASS__, 'ajax_search_leads'));         add_action('wp_ajax_idlm_filter_quotation_list', array(__CLASS__, 'ajax_filter_quotation_list'));         add_action('wp_ajax_idlm_filter_catalogue_list', array(__CLASS__, 'ajax_filter_catalogue_list'));` |
| `includes/class-quotations.php` | 42 | `add_action('admin_post_idlm_export_catalogue_csv', array(__CLASS__, 'handle_export_catalogue_csv'));         add_action('wp_ajax_idlm_search_quote_catalogue', array(__CLASS__, 'ajax_search_catalogue'));         add_action('wp_ajax_idlm_search_quote_leads', array(__CLASS__, 'ajax_search_leads'));         add_action('wp_ajax_idlm_filter_quotation_list', array(__CLASS__, 'ajax_filter_quotation_list'));         add_action('wp_ajax_idlm_filter_catalogue_list', array(__CLASS__, 'ajax_filter_catalogue_list'));         add_action('wp_ajax_idlm_autosave_quote', array(__CLASS__, 'ajax_autosave_quote'));` |
| `includes/class-quotations.php` | 43 | `add_action('wp_ajax_idlm_search_quote_catalogue', array(__CLASS__, 'ajax_search_catalogue'));         add_action('wp_ajax_idlm_search_quote_leads', array(__CLASS__, 'ajax_search_leads'));         add_action('wp_ajax_idlm_filter_quotation_list', array(__CLASS__, 'ajax_filter_quotation_list'));         add_action('wp_ajax_idlm_filter_catalogue_list', array(__CLASS__, 'ajax_filter_catalogue_list'));         add_action('wp_ajax_idlm_autosave_quote', array(__CLASS__, 'ajax_autosave_quote'));         add_action('wp_ajax_idlm_discard_quote_autosave', array(__CLASS__, 'ajax_discard_quote_autosave'));` |
| `includes/class-quotations.php` | 44 | `add_action('wp_ajax_idlm_search_quote_leads', array(__CLASS__, 'ajax_search_leads'));         add_action('wp_ajax_idlm_filter_quotation_list', array(__CLASS__, 'ajax_filter_quotation_list'));         add_action('wp_ajax_idlm_filter_catalogue_list', array(__CLASS__, 'ajax_filter_catalogue_list'));         add_action('wp_ajax_idlm_autosave_quote', array(__CLASS__, 'ajax_autosave_quote'));         add_action('wp_ajax_idlm_discard_quote_autosave', array(__CLASS__, 'ajax_discard_quote_autosave'));         add_shortcode('idlm_quotations', array(__CLASS__, 'render_shortcode'));` |
| `includes/class-quotations.php` | 45 | `add_action('wp_ajax_idlm_filter_quotation_list', array(__CLASS__, 'ajax_filter_quotation_list'));         add_action('wp_ajax_idlm_filter_catalogue_list', array(__CLASS__, 'ajax_filter_catalogue_list'));         add_action('wp_ajax_idlm_autosave_quote', array(__CLASS__, 'ajax_autosave_quote'));         add_action('wp_ajax_idlm_discard_quote_autosave', array(__CLASS__, 'ajax_discard_quote_autosave'));         add_shortcode('idlm_quotations', array(__CLASS__, 'render_shortcode'));     }` |
| `includes/class-quotations.php` | 46 | `add_action('wp_ajax_idlm_filter_catalogue_list', array(__CLASS__, 'ajax_filter_catalogue_list'));         add_action('wp_ajax_idlm_autosave_quote', array(__CLASS__, 'ajax_autosave_quote'));         add_action('wp_ajax_idlm_discard_quote_autosave', array(__CLASS__, 'ajax_discard_quote_autosave'));         add_shortcode('idlm_quotations', array(__CLASS__, 'render_shortcode'));     }` |
| `includes/class-quotations.php` | 47 | `add_action('wp_ajax_idlm_autosave_quote', array(__CLASS__, 'ajax_autosave_quote'));         add_action('wp_ajax_idlm_discard_quote_autosave', array(__CLASS__, 'ajax_discard_quote_autosave'));         add_shortcode('idlm_quotations', array(__CLASS__, 'render_shortcode'));     }      public static function install() {` |
| `includes/class-quotations.php` | 48 | `add_action('wp_ajax_idlm_discard_quote_autosave', array(__CLASS__, 'ajax_discard_quote_autosave'));         add_shortcode('idlm_quotations', array(__CLASS__, 'render_shortcode'));     }      public static function install() {         IDLM_Quotation_Schema::install();` |
| `includes/class-quotations.php` | 49 | `add_shortcode('idlm_quotations', array(__CLASS__, 'render_shortcode'));     }      public static function install() {         IDLM_Quotation_Schema::install();     }` |
| `includes/class-read-model-cache.php` | 31 | `add_action('added_option', array(__CLASS__, 'invalidate_on_option_change'), 10, 2);         add_action('updated_option', array(__CLASS__, 'invalidate_on_option_change'), 10, 3);         add_action('deleted_option', array(__CLASS__, 'invalidate_on_option_change'), 10, 1);         add_action(IDLM_Data_Retention::HOOK, array(__CLASS__, 'invalidate_after_retention'), 20);     }` |
| `includes/class-read-model-cache.php` | 32 | `add_action('updated_option', array(__CLASS__, 'invalidate_on_option_change'), 10, 3);         add_action('deleted_option', array(__CLASS__, 'invalidate_on_option_change'), 10, 1);         add_action(IDLM_Data_Retention::HOOK, array(__CLASS__, 'invalidate_after_retention'), 20);     }      /**` |
| `includes/class-read-model-cache.php` | 33 | `add_action('deleted_option', array(__CLASS__, 'invalidate_on_option_change'), 10, 1);         add_action(IDLM_Data_Retention::HOOK, array(__CLASS__, 'invalidate_after_retention'), 20);     }      /**      * Cache an expensive read while retaining a stale value for lock contention.` |
| `includes/class-read-model-cache.php` | 34 | `add_action(IDLM_Data_Retention::HOOK, array(__CLASS__, 'invalidate_after_retention'), 20);     }      /**      * Cache an expensive read while retaining a stale value for lock contention.      *` |
| `includes/class-scheduler.php` | 9 | `add_action('wp', array(__CLASS__, 'setup_schedules'));          // Action Scheduler hooks         add_action('idlm_daily_audience_sync', array('IDLM_Audiences', 'run_batch_sync'));         add_action('idlm_daily_audience_sync', array('IDLM_Telemetry', 'prune_logs'));         add_action('idlm_hourly_assignment_fallback', array('IDLM_Integrations', 'run_assignment_fallback'));` |
| `includes/class-scheduler.php` | 12 | `add_action('idlm_daily_audience_sync', array('IDLM_Audiences', 'run_batch_sync'));         add_action('idlm_daily_audience_sync', array('IDLM_Telemetry', 'prune_logs'));         add_action('idlm_hourly_assignment_fallback', array('IDLM_Integrations', 'run_assignment_fallback'));         add_action('idlm_hourly_assignment_fallback', array('IDLM_DB', 'roll_forward_presales_tasks'));          // WP Cron fallbacks` |
| `includes/class-scheduler.php` | 13 | `add_action('idlm_daily_audience_sync', array('IDLM_Telemetry', 'prune_logs'));         add_action('idlm_hourly_assignment_fallback', array('IDLM_Integrations', 'run_assignment_fallback'));         add_action('idlm_hourly_assignment_fallback', array('IDLM_DB', 'roll_forward_presales_tasks'));          // WP Cron fallbacks         add_action('idlm_daily_audience_sync_cron', array('IDLM_Audiences', 'run_batch_sync'));` |
| `includes/class-scheduler.php` | 14 | `add_action('idlm_hourly_assignment_fallback', array('IDLM_Integrations', 'run_assignment_fallback'));         add_action('idlm_hourly_assignment_fallback', array('IDLM_DB', 'roll_forward_presales_tasks'));          // WP Cron fallbacks         add_action('idlm_daily_audience_sync_cron', array('IDLM_Audiences', 'run_batch_sync'));         add_action('idlm_daily_audience_sync_cron', array('IDLM_Telemetry', 'prune_logs'));` |
| `includes/class-scheduler.php` | 15 | `add_action('idlm_hourly_assignment_fallback', array('IDLM_DB', 'roll_forward_presales_tasks'));          // WP Cron fallbacks         add_action('idlm_daily_audience_sync_cron', array('IDLM_Audiences', 'run_batch_sync'));         add_action('idlm_daily_audience_sync_cron', array('IDLM_Telemetry', 'prune_logs'));         add_action('idlm_hourly_assignment_fallback_cron', array('IDLM_Integrations', 'run_assignment_fallback'));` |
| `includes/class-scheduler.php` | 18 | `add_action('idlm_daily_audience_sync_cron', array('IDLM_Audiences', 'run_batch_sync'));         add_action('idlm_daily_audience_sync_cron', array('IDLM_Telemetry', 'prune_logs'));         add_action('idlm_hourly_assignment_fallback_cron', array('IDLM_Integrations', 'run_assignment_fallback'));         add_action('idlm_hourly_assignment_fallback_cron', array('IDLM_DB', 'roll_forward_presales_tasks'));     }` |
| `includes/class-scheduler.php` | 19 | `add_action('idlm_daily_audience_sync_cron', array('IDLM_Telemetry', 'prune_logs'));         add_action('idlm_hourly_assignment_fallback_cron', array('IDLM_Integrations', 'run_assignment_fallback'));         add_action('idlm_hourly_assignment_fallback_cron', array('IDLM_DB', 'roll_forward_presales_tasks'));     }      public static function setup_schedules() {` |
| `includes/class-scheduler.php` | 20 | `add_action('idlm_hourly_assignment_fallback_cron', array('IDLM_Integrations', 'run_assignment_fallback'));         add_action('idlm_hourly_assignment_fallback_cron', array('IDLM_DB', 'roll_forward_presales_tasks'));     }      public static function setup_schedules() {         // 1. Action Scheduler` |
| `includes/class-scheduler.php` | 21 | `add_action('idlm_hourly_assignment_fallback_cron', array('IDLM_DB', 'roll_forward_presales_tasks'));     }      public static function setup_schedules() {         // 1. Action Scheduler         if (function_exists('as_next_scheduled_action')) {` |
| `includes/class-settings.php` | 18 | `add_action('wp_head', array(__CLASS__, 'render_frontend_favicon'), 2);     }      /** Return the stable white-label defaults used by every plugin-owned surface. */     public static function white_label_defaults() {         return array(` |

## Emitted hooks and scheduling calls

| File | Line | Source |
|---|---:|---|
| `includes/class-admin-ajax.php` | 2915 | `do_action('idlm_lead_stage_converted', $lead_id, $capi_stage, (object) array('stage' => ''));                 }             } else {                 $failed++;                 $errors[] = array(                     'index' => absint($lead_index),` |
| `includes/class-auth.php` | 95 | `do_action('validate_password_reset', $errors, $user);         if ($errors->has_errors()) {             wp_safe_redirect(add_query_arg('idlm_auth_error', 'password-invalid', $reset_url));             exit;         }         reset_password($user, $password);` |
| `includes/class-capi.php` | 251 | `as_schedule_single_action($schedule_time, 'idlm_async_capi_upload_event', array(                 'lead_id' => $lead_id,                 'stage' => $stage             ));             IDLM_DB::log_activity($lead_id, 'capi_queue', "Queued Conversions API background sync via Action Scheduler for stage: {$stage}{$delay_msg}.", 0);         } else {` |
| `includes/class-capi.php` | 258 | `wp_schedule_single_event($schedule_time, 'idlm_async_capi_upload_event', array(                 'lead_id' => $lead_id,                 'stage' => $stage             ));             IDLM_DB::log_activity($lead_id, 'capi_queue', "Queued Conversions API background sync via WP Cron for stage: {$stage}{$delay_msg}.", 0);         }` |
| `includes/class-data-retention.php` | 22 | `wp_schedule_event(time() + HOUR_IN_SECONDS, 'daily', self::HOOK);         }     }      public static function clear() {         wp_clear_scheduled_hook(self::HOOK);` |
| `includes/class-interakt-batch.php` | 22 | `return (bool) as_schedule_single_action(time() + $delay, self::HOOK, $args, 'idlm-interakt');         }         $result = wp_schedule_single_event(time() + $delay, self::HOOK, $args, true);         return !is_wp_error($result) && false !== $result;     }` |
| `includes/class-interakt-batch.php` | 24 | `$result = wp_schedule_single_event(time() + $delay, self::HOOK, $args, true);         return !is_wp_error($result) && false !== $result;     }      public static function enqueue($lead_id, $event, $request_id, $delay = 0) {         global $wpdb;` |
| `includes/class-interakt.php` | 113 | `as_enqueue_async_action('idlm_async_interakt_track_event', array(                 'payload' => $payload,                 'api_key' => $api_key,                 'lead_id' => $lead_id             ), 'idlm-interakt');             IDLM_DB::log_activity($lead_id, 'interakt_queued', "Queued Interakt WhatsApp automation event '{$event_name}' via Action Scheduler.", 0);` |
| `includes/class-lead-lifecycle-service.php` | 38 | `do_action('idlm_lead_attribution_merged', $canonical_id, $updates);                     IDLM_Read_Model_Cache::invalidate('inbound_lead_update');                 } else {                     self::log_activity($canonical_id, 'attribution', sprintf('Matched a %s submission to this canonical lead.', $platform));                 }                 self::record_lead_touchpoint($canonical_id, $platform, $data, $payload);` |
| `includes/class-lead-lifecycle-service.php` | 521 | `do_action('idlm_lead_attribution_merged', $duplicate_of_id, $original_updates);                     }                 }             }              // Trigger CAPI for initial lead event only if it's NOT a duplicate (implemented by Shashi)` |
| `includes/class-lead-lifecycle-service.php` | 528 | `do_action('idlm_lead_stage_converted', $lead_id, 'New', (object) array('stage' => ''));             }              IDLM_Read_Model_Cache::invalidate('lead_insert');             return $lead_id;         }` |
| `includes/class-lead-lifecycle-service.php` | 850 | `do_action('idlm_lead_stage_converted', $lead_id, $value, $old_lead);                     } elseif ($key === 'lead_quality' && $value === 'Qualified' && !$quality_promoted_stage) {                         do_action('idlm_lead_stage_converted', $lead_id, 'Qualified', $old_lead);                     }                      // Trigger mapped Interakt events if field, value, and transition match` |
| `includes/class-lead-lifecycle-service.php` | 852 | `do_action('idlm_lead_stage_converted', $lead_id, 'Qualified', $old_lead);                     }                      // Trigger mapped Interakt events if field, value, and transition match                     $interakt_enabled = get_option('idlm_interakt_enabled', '0');                     $mapped_triggered = false;` |
| `includes/class-lead-lifecycle-service.php` | 869 | `do_action('idlm_trigger_interakt_event', $lead_id, $m_evt, $old_lead);                                         if ($key === 'call_status' && $value === 'No Answer') {                                             $mapped_triggered = true;                                         }                                     }                                 }` |
| `includes/class-lead-lifecycle-service.php` | 881 | `do_action('idlm_lead_call_status_no_answer', $lead_id, $old_lead);                     }                 }             }              IDLM_Read_Model_Cache::invalidate('lead_update');` |
| `includes/class-scheduler.php` | 34 | `as_schedule_recurring_action($timestamp, DAY_IN_SECONDS, 'idlm_daily_audience_sync');             }             if (!as_next_scheduled_action('idlm_hourly_assignment_fallback')) {                 as_schedule_recurring_action(time(), HOUR_IN_SECONDS, 'idlm_hourly_assignment_fallback');             }         } else {` |
| `includes/class-scheduler.php` | 37 | `as_schedule_recurring_action(time(), HOUR_IN_SECONDS, 'idlm_hourly_assignment_fallback');             }         } else {             // 2. WP Cron Fallback             if (!wp_next_scheduled('idlm_daily_audience_sync_cron')) {                 $timezone = wp_timezone();` |
| `includes/class-scheduler.php` | 46 | `wp_schedule_event($timestamp, 'daily', 'idlm_daily_audience_sync_cron');             }             if (!wp_next_scheduled('idlm_hourly_assignment_fallback_cron')) {                 wp_schedule_event(time(), 'hourly', 'idlm_hourly_assignment_fallback_cron');             }         }` |
| `includes/class-scheduler.php` | 49 | `wp_schedule_event(time(), 'hourly', 'idlm_hourly_assignment_fallback_cron');             }         }     }      public static function clear_schedules() {` |
