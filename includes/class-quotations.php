<?php
/**
 * Internal quotation module.
 *
 * Builder: Shashi
 */

if (!defined('ABSPATH')) {
    exit;
}

class IDLM_Quotations {
    const SCHEMA_VERSION = IDLM_Quotation_Schema::VERSION;
    public static function init() {
        if (self::SCHEMA_VERSION !== get_option('idlm_quotation_schema_version', '')) {
            self::install();
        }
        if ('1.1.0' !== get_option('idlm_legacy_quote_migration_version', '')) {
            self::migrate_legacy_quotes();
        }

        add_action('admin_menu', array(__CLASS__, 'register_menu'), 20);
        add_action('admin_enqueue_scripts', array(__CLASS__, 'enqueue_assets'));
        add_action('admin_post_idlm_save_quote', array(__CLASS__, 'handle_save'));
        add_action('admin_post_idlm_print_quote', array(__CLASS__, 'render_print_view'));
        add_action('admin_post_idlm_save_quote_settings', array(__CLASS__, 'handle_save_settings'));
        add_action('admin_post_idlm_save_catalogue_item', array(__CLASS__, 'handle_save_catalogue_item'));
        add_action('admin_post_idlm_save_catalogue_category', array(__CLASS__, 'handle_save_catalogue_category'));
        add_action('admin_post_idlm_save_catalogue_unit', array(__CLASS__, 'handle_save_catalogue_unit'));
        add_action('admin_post_idlm_archive_catalogue_item', array(__CLASS__, 'handle_archive_catalogue_item'));
        add_action('admin_post_idlm_bulk_catalogue_items', array(__CLASS__, 'handle_bulk_catalogue_items'));
        add_action('admin_post_idlm_catalogue_category_action', array(__CLASS__, 'handle_catalogue_category_action'));
        add_action('admin_post_idlm_import_catalogue_csv', array(__CLASS__, 'handle_import_catalogue_csv'));
        add_action('admin_post_idlm_export_catalogue_csv', array(__CLASS__, 'handle_export_catalogue_csv'));
        add_action('wp_ajax_idlm_search_quote_catalogue', array(__CLASS__, 'ajax_search_catalogue'));
        add_action('wp_ajax_idlm_search_quote_leads', array(__CLASS__, 'ajax_search_leads'));
        add_action('wp_ajax_idlm_autosave_quote', array(__CLASS__, 'ajax_autosave_quote'));
        add_action('wp_ajax_idlm_discard_quote_autosave', array(__CLASS__, 'ajax_discard_quote_autosave'));
        add_shortcode('idlm_quotations', array(__CLASS__, 'render_shortcode'));
    }

    public static function install() {
        IDLM_Quotation_Schema::install();
    }
    public static function register_menu() {
        add_submenu_page(
            'interior-design-lead-manager',
            'Quotations',
            'Quotations',
            'idlm_view_quotes',
            'idlm_quotations',
            array(__CLASS__, 'render_page')
        );
    }

    public static function enqueue_assets($hook) {
        if (false === strpos((string) $hook, 'idlm_quotations')) {
            return;
        }
        if (class_exists('IDLM_Admin')) {
            IDLM_Admin::register_core_assets();
            wp_enqueue_style('idlm-google-fonts');
            wp_enqueue_style('idlm-admin-styles');
        }
        wp_enqueue_style('idlm-quotations', IDLM_URL . 'assets/css/quotations.css', array(), IDLM_VERSION);
        wp_enqueue_script('idlm-quotations', IDLM_URL . 'assets/js/quotations.js', array(), IDLM_VERSION, true);
        wp_localize_script('idlm-quotations', 'idlmQuoteData', array('ajaxUrl' => admin_url('admin-ajax.php'), 'nonce' => wp_create_nonce('idlm_quote_catalogue_search'), 'autosaveNonce' => wp_create_nonce('idlm_quote_autosave')));
    }

    protected static function table($name) {
        return IDLM_Quotation_Repository::table($name);
    }

    protected static function autosave_key($revision_id, $user_id = 0) {
        return 'idlm_quote_autosave_' . absint($user_id ?: get_current_user_id()) . '_' . absint($revision_id);
    }

    protected static function can($capability) {
        return IDLM_Quotation_Policy::can($capability);
    }

    protected static function can_view_lead($lead_id) {
        return IDLM_Quotation_Policy::can_view_lead($lead_id);
    }

    protected static function redirect($args = array()) {
        $default = admin_url('admin.php?page=idlm_quotations');
        $return_url = isset($_POST['idlm_return_url']) ? esc_url_raw(wp_unslash($_POST['idlm_return_url'])) : '';
        $base = $return_url ? wp_validate_redirect($return_url, $default) : $default;
        wp_safe_redirect(add_query_arg($args, $base));
        exit;
    }

    protected static function view_url($args = array()) {
        $base = is_admin() ? admin_url('admin.php?page=idlm_quotations') : remove_query_arg(array('action', 'quote_id', 'lead_id', 'status', 'revision_id', 'idlm_notice', 'idlm_error'));
        return add_query_arg($args, $base);
    }

    protected static function return_url() {
        return is_admin() ? '' : self::view_url();
    }

    protected static function rollback_save($message, $redirect_args, $context) {
        global $wpdb;
        $database_error = sanitize_text_field((string) $wpdb->last_error);
        $wpdb->query('ROLLBACK');
        error_log('[IDLM Quotations] ' . sanitize_text_field($context) . ($database_error ? ': ' . $database_error : '')); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
        self::redirect(array_merge($redirect_args, array('idlm_error' => $message)));
    }

    public static function render_shortcode() {
        if (!is_user_logged_in() || !self::can('idlm_view_quotes')) {
            return '<p class="idlm-quotation-denied">You do not have permission to view quotations.</p>';
        }
        wp_enqueue_style('dashicons');
        if (class_exists('IDLM_Admin')) {
            IDLM_Admin::register_core_assets();
            wp_enqueue_style('idlm-google-fonts');
            wp_enqueue_style('idlm-admin-styles');
        }
        wp_enqueue_style('idlm-quotations', IDLM_URL . 'assets/css/quotations.css', array(), IDLM_VERSION);
        wp_enqueue_script('idlm-quotations', IDLM_URL . 'assets/js/quotations.js', array(), IDLM_VERSION, true);
        wp_localize_script('idlm-quotations', 'idlmQuoteData', array('ajaxUrl' => admin_url('admin-ajax.php'), 'nonce' => wp_create_nonce('idlm_quote_catalogue_search'), 'autosaveNonce' => wp_create_nonce('idlm_quote_autosave')));
        ob_start();
        self::render_page();
        return ob_get_clean();
    }

    protected static function settings() {
        return wp_parse_args((array) get_option('idlm_quote_settings', array()), array(
            'company_name' => get_bloginfo('name'), 'company_logo' => '', 'accent_color' => '#111827',
            'pdf_footer' => '', 'paper_size' => 'A4',
            'cover_page_enabled' => 0, 'cover_page_html' => '',
            'after_quote_enabled' => 0, 'after_quote_html' => '',
            'payment_structure' => '[]',
            'number_prefix' => 'Q', 'number_separator' => '-', 'number_padding' => 6,
        ));
    }

    protected static function next_quote_number() {
        return IDLM_Quotation_Repository::reserve_quote_number(self::settings());
    }

    protected static function clean_decimal($value, $minimum = 0) {
        return IDLM_Quotation_Calculator::clean_decimal($value, $minimum);
    }

    protected static function parse_items($raw_items, $quote_settings = array()) {
        return IDLM_Quotation_Calculator::parse_items($raw_items, $quote_settings);
    }

    protected static function parse_sections($raw_sections) {
        return IDLM_Quotation_Calculator::parse_sections($raw_sections);
    }

    protected static function calculate_totals($items, $posted) {
        return IDLM_Quotation_Calculator::calculate_totals($items, $posted);
    }


    public static function handle_save() { return IDLM_Quotation_Controller::handle_save(); }
    public static function ajax_autosave_quote() { return IDLM_Quotation_Controller::ajax_autosave_quote(); }
    public static function ajax_discard_quote_autosave() { return IDLM_Quotation_Controller::ajax_discard_quote_autosave(); }
    public static function handle_create_revision() { return IDLM_Quotation_Controller::handle_create_revision(); }
    public static function ajax_search_catalogue() { return IDLM_Quotation_Controller::ajax_search_catalogue(); }
    public static function ajax_search_leads() { return IDLM_Quotation_Controller::ajax_search_leads(); }
    public static function handle_save_catalogue_category() { return IDLM_Quotation_Controller::handle_save_catalogue_category(); }
    public static function handle_save_catalogue_unit() { return IDLM_Quotation_Controller::handle_save_catalogue_unit(); }
    public static function handle_save_catalogue_item() { return IDLM_Quotation_Controller::handle_save_catalogue_item(); }
    public static function handle_archive_catalogue_item() { return IDLM_Quotation_Controller::handle_archive_catalogue_item(); }
    public static function handle_bulk_catalogue_items() { return IDLM_Quotation_Controller::handle_bulk_catalogue_items(); }
    public static function handle_catalogue_category_action() { return IDLM_Quotation_Controller::handle_catalogue_category_action(); }
    public static function handle_import_catalogue_csv() { return IDLM_Quotation_Controller::handle_import_catalogue_csv(); }
    public static function handle_export_catalogue_csv() { return IDLM_Quotation_Controller::handle_export_catalogue_csv(); }
    public static function handle_save_settings() { return IDLM_Quotation_Controller::handle_save_settings(); }

    public static function render_settings_page() { return IDLM_Quotation_Renderer::render_settings_page(); }
    public static function render_page() { return IDLM_Quotation_Renderer::render_page(); }
    public static function render_print_view() { return IDLM_Quotation_Renderer::render_print_view(); }

    protected static function get_quote($quote_id) {
        return IDLM_Quotation_Repository::get_quote($quote_id);
    }

    protected static function get_revision($revision_id) {
        return IDLM_Quotation_Repository::get_revision($revision_id);
    }

    protected static function get_quote_bundle($quote_id, $revision_id = 0) {
        return IDLM_Quotation_Repository::get_quote_bundle($quote_id, $revision_id);
    }

    private static function backfill_revision_snapshots_and_sections() {
        IDLM_Quotation_Schema::backfill_revision_snapshots_and_sections();
    }

    private static function migrate_legacy_quotes() {
        IDLM_Quotation_Schema::migrate_legacy_quotes();
    }

    public static function lead_has_quotes($lead_id) {
        return IDLM_Quotation_Repository::lead_has_quotes($lead_id);
    }

    public static function delete_for_lead($lead_id) {
        return IDLM_Quotation_Repository::delete_for_lead($lead_id);
    }

    public static function reset_data() {
        IDLM_Quotation_Repository::reset_data();
    }
}
