<?php
/** Authenticated sales payment workspace and operation-specific forms. Builder: Shashi. */
if (!defined('ABSPATH')) { exit; }
final class IDLM_Lead_Payment_Controller {
    public static function init() {
        foreach (array('schedule', 'resolve_legacy', 'won_plan', 'record', 'correct', 'revoke', 'upload') as $operation) {
            add_action('admin_post_idlm_payment_' . $operation, array(__CLASS__, 'handle'));
        }
        add_action('admin_menu', array(__CLASS__, 'menu'));
    }
    public static function menu() {
        $hook = add_submenu_page('idlm_utilities', 'Payments', 'Payments', 'manage_options', 'idlm-payments', array(__CLASS__, 'render'));
        add_action('load-' . $hook, static function() {
            IDLM_Admin_Assets::enqueue_shortcode_assets('workspace');
            self::enqueue_payment_assets();
            if (self::design_active()) {
                add_filter('admin_body_class', static function($classes) { return $classes . ' idlm-crm-design'; });
            }
        });
    }
    private static function design_active() {
        return method_exists('IDLM_Admin_Assets', 'design_layer_enabled') && method_exists('IDLM_Admin_Assets', 'add_design_body_class') &&
            is_file(IDLM_PATH . 'assets/css/crm-design.css') && IDLM_Admin_Assets::design_layer_enabled();
    }
    /** Direct shortcode/admin renders need the same gated design as ordinary page renders. */
    private static function enqueue_payment_assets() {
        wp_enqueue_style('idlm-quotations', IDLM_URL . 'assets/css/quotations.css', array('idlm-admin-styles'), IDLM_VERSION);
        wp_enqueue_style('idlm-payments', IDLM_URL . 'assets/css/payments.css', array('idlm-quotations'), IDLM_Admin_Assets::version('css/payments.css'));
        if (self::design_active()) {
            wp_enqueue_style('idlm-crm-design', IDLM_URL . 'assets/css/crm-design.css', array('idlm-payments'), IDLM_Admin_Assets::version('css/crm-design.css'));
            wp_enqueue_script('idlm-payments', IDLM_URL . 'assets/js/payments.js', array(), IDLM_Admin_Assets::version('js/payments.js'), true);
            add_filter('body_class', array('IDLM_Admin_Assets', 'add_design_body_class'));
        }
    }
    private static function text($input, $key, $default = '') {
        return isset($input[$key]) && is_string($input[$key]) ? wp_unslash($input[$key]) : $default;
    }
    private static function id($input, $key) {
        $value = self::text($input, $key);
        return ctype_digit($value) && strlen($value) < 19 ? (int) $value : 0;
    }
    private static function visible() {
        return IDLM_Lead_Access_Policy::has_rep_access() && !IDLM_Lead_Access_Policy::is_pre_sales() &&
            (current_user_can('manage_options') || current_user_can('idlm_record_lead_payments') || current_user_can('idlm_correct_lead_payments'));
    }
    /**
     * The workspace Payments tab and its lead list are retired in the design layer:
     * each lead's payment page opens from the lead drawer and the Design project.
     * The legacy design keeps the tab. Filter `idlm_show_payments_tab` true restores it.
     */
    public static function tab_shown() {
        return !IDLM_Admin_Assets::design_layer_enabled() || (bool) apply_filters('idlm_show_payments_tab', false);
    }
    /** May open this lead's payment page: same checks the page itself applies. */
    public static function can_open($lead_id) {
        return self::visible() && IDLM_Lead_Access_Policy::can_access((int) $lead_id, true);
    }
    public static function details_url($lead_id) {
        return add_query_arg(array('idlm_view' => 'payments', 'payment_lead_id' => (int) $lead_id), IDLM_Admin_Shortcodes::workspace_url());
    }
    /** Lead drawer panel: received and outstanding from the latest ledger snapshot (no locks), and the details link. */
    public static function drawer_summary($lead) {
        if (!$lead || !self::can_open($lead->id)) { return ''; }
        $balance = IDLM_Design_Handoffs::balance($lead->id);
        if (!$balance && ('Won' !== $lead->stage || !empty($lead->is_duplicate))) { return ''; }
        $html = '<section class="idlm-drawer-panel idlm-design-card idlm-payment-drawer"><div class="idlm-drawer-panel-head"><h3 class="idlm-drawer-panel-title">Payments</h3>';
        if ($balance) {
            $currency = $balance['currency'];
            $tone = 'Met' === $balance['advance_status'] ? 'is-success' : ('Partial' === $balance['advance_status'] ? 'is-warning' : 'is-neutral');
            $html .= '<span class="idlm-badge ' . esc_attr($tone) . '">' . esc_html('Advance ' . strtolower($balance['advance_status'])) . '</span></div><div class="idlm-design-card-body">';
            $html .= '<p>' . esc_html(self::money($balance['total_received'], $currency) . ' received of ' . self::money($balance['basis_total'], $currency) . '. Outstanding ' . self::money($balance['total_outstanding'], $currency) . '.') . '</p>';
        } else {
            $html .= '</div><div class="idlm-design-card-body"><p>No payment plan yet. Set it from the agreed quotation or the Won amount in Payment details.</p>';
        }
        return $html . '<a class="idlm-btn idlm-btn-secondary" href="' . esc_url(self::details_url($lead->id)) . '">Payment details</a></div></section>';
    }
    // phpcs:disable WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.MissingUnslash,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- POST handlers verify the nonce (check_admin_referer or check_ajax_referer) and capability before acting, reading the action name first only to choose that nonce; GET values are display-only routing, cast or sanitized before use; uploads are validated by IDLM_Private_Files::upload(). Builder: Shashi.
    public static function handle() {
        $operation = str_replace('idlm_payment_', '', self::text($_POST, 'action'));
        if (!in_array($operation, array('schedule', 'resolve_legacy', 'won_plan', 'record', 'correct', 'revoke', 'upload'), true) || !self::visible()) { wp_die('Payment access denied.', '', array('response' => 403)); }
        check_admin_referer('idlm_payment_' . $operation);
        $lead_id = self::id($_POST, 'payment_lead_id');
        $version = self::id($_POST, 'payment_version');
        if (!isset($_POST['payment_version']) || !is_string($_POST['payment_version']) || !ctype_digit($_POST['payment_version'])) { wp_die('Invalid payment version.', '', array('response' => 400)); }
        $key = self::text($_POST, 'request_key');
        $reason = self::text($_POST, 'reason');
        $result = IDLM_Recovery_Guard::run(static function() use ($operation, $lead_id, $version, $key, $reason) {
            if ('upload' === $operation) {
                if (!IDLM_Lead_Payments::permitted($lead_id)) { return new WP_Error('idlm_payment_denied', 'Payment upload access denied.'); }
                return IDLM_Private_Files::upload($_FILES['payment_screenshot'] ?? null, $lead_id, 'payment');
            }
            if ('resolve_legacy' === $operation) {
                $resolution = array('milestones' => $_POST['legacy_milestones'] ?? null, 'source_reference' => self::text($_POST, 'source_reference'), 'confirmed' => '1' === self::text($_POST, 'legacy_confirmed'));
                if (is_array($resolution['milestones'])) { $resolution['milestones'] = wp_unslash($resolution['milestones']); }
                return IDLM_Lead_Payments::resolve_legacy_schedule($lead_id, self::id($_POST, 'payment_revision_id'), $resolution, $reason, $version, $key);
            }
            if ('won_plan' === $operation) {
                $rows = $_POST['legacy_milestones'] ?? null;
                return IDLM_Lead_Payments::publish_won_amount($lead_id, is_array($rows) ? wp_unslash($rows) : array(), $reason, $version, $key);
            }
            if ('schedule' === $operation) {
                return IDLM_Lead_Payments::publish_schedule($lead_id, self::id($_POST, 'payment_revision_id'), $_POST['advance_positions'] ?? array(), $reason, $version, $key);
            }
            $confirmation_id = self::id($_POST, 'confirmation_id');
            if ('revoke' === $operation) { return IDLM_Lead_Payments::revoke($lead_id, $confirmation_id, $reason, $version, $key); }
            $data = array();
            foreach (array('amount', 'received_date', 'method', 'reference', 'currency', 'note', 'duplicate_review_reason') as $field) { $data[$field] = self::text($_POST, $field); }
            $data['attachment_id'] = '1' === self::text($_POST, 'remove_screenshot') ? 0 : self::id($_POST, 'attachment_id');
            // The simple page sends the screenshot with the receipt. Check the typed fields and the grant first, so a bad form stores no file.
            $file = isset($_FILES['payment_screenshot']) && UPLOAD_ERR_NO_FILE !== (int) ($_FILES['payment_screenshot']['error'] ?? UPLOAD_ERR_NO_FILE) ? $_FILES['payment_screenshot'] : null;
            if ($file) {
                $checked = IDLM_Lead_Payment_Ledger::receipt(array_merge($data, array('attachment_id' => 1)));
                if (is_wp_error($checked)) { return $checked; }
                if (!IDLM_Lead_Payments::permitted($lead_id, 'correct' === $operation)) { return new WP_Error('idlm_payment_denied', 'Payment access denied.'); }
                $upload = IDLM_Private_Files::upload($file, $lead_id, 'payment');
                if (is_wp_error($upload)) { return $upload; }
                $data['attachment_id'] = (int) $upload['attachment_id'];
            }
            return 'correct' === $operation ? IDLM_Lead_Payments::correct($lead_id, $confirmation_id, $data, $reason, $version, $key) : IDLM_Lead_Payments::record($lead_id, $data, $version, $key);
        });
        if (false === $result) { $result = new WP_Error('idlm_payment_busy', 'CRM recovery is busy. Retry shortly.'); }
        if (is_wp_error($result)) { wp_die(esc_html($result->get_error_message()), 'Payment not saved', array('response' => 422, 'back_link' => true)); }
        $url = wp_validate_redirect(self::text($_POST, 'return_url'), admin_url('admin.php?page=idlm-payments'));
        $url = add_query_arg(array('payment_lead_id' => $lead_id, 'payment_saved' => '1', 'payment_done' => $operation), $url);
        if ('upload' === $operation) { $url = add_query_arg('payment_attachment_id', $result['attachment_id'], $url); }
        wp_safe_redirect($url); exit;
    }
    // phpcs:enable WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.MissingUnslash,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
    private static function form($operation, $lead_id, $version, $return_url) {
        echo '<form class="idlm-payment-form" method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
        self::hidden('action', 'idlm_payment_' . $operation); self::hidden('payment_lead_id', $lead_id);
        self::hidden('payment_version', $version); self::hidden('request_key', wp_generate_uuid4()); self::hidden('return_url', $return_url);
        wp_nonce_field('idlm_payment_' . $operation);
    }
    private static function hidden($name, $value) { echo '<input type="hidden" name="' . esc_attr($name) . '" value="' . esc_attr($value) . '">'; }
    private static function field($name, $label, $value = '', $type = 'text', $required = false, $help = '') {
        static $sequence = 0; $id = 'payment-' . $name . '-' . ++$sequence;
        echo '<div class="idlm-payment-field"><label for="' . esc_attr($id) . '">' . esc_html($label) . ($required ? ' <span class="idlm-payment-required" aria-hidden="true">*</span>' : '') . '</label>';
        $attributes = ' class="idlm-input" id="' . esc_attr($id) . '" name="' . esc_attr($name) . '"' . ($required ? ' required' : '') . ($help ? ' aria-describedby="' . esc_attr($id . '-help') . '"' : '');
        if (in_array($name, array('note', 'duplicate_review_reason', 'reason'), true)) {
            // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped when built: helpers and variables here concatenate only esc_html/esc_attr/esc_url values, nonce fields and fixed markup. Builder: Shashi.
            echo '<textarea' . $attributes . ' rows="2">' . esc_textarea($value) . '</textarea>';
        } else {
            // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped when built: helpers and variables here concatenate only esc_html/esc_attr/esc_url values, nonce fields and fixed markup. Builder: Shashi.
            echo '<input' . $attributes . ' type="' . esc_attr($type) . '" value="' . esc_attr($value) . '"' . ('amount' === $name || strpos($name, '[percentage]') !== false ? ' inputmode="decimal" autocomplete="off"' : '') . '>';
        }
        if ($help) { echo '<small id="' . esc_attr($id . '-help') . '">' . esc_html($help) . '</small>'; }
        echo '</div>';
    }
    private static function money($value, $currency) {
        $parts = explode('.', (string) $value, 2);
        $integer = preg_replace('/\B(?=(\d{3})+(?!\d))/', ',', $parts[0]);
        $symbol = array('INR' => '₹', 'USD' => '$', 'EUR' => '€', 'GBP' => '£');
        return ($symbol[$currency] ?? $currency . ' ') . $integer . '.' . str_pad($parts[1] ?? '', 2, '0');
    }
    private static function icon($name) { return '<span class="dashicons dashicons-' . esc_attr($name) . '" aria-hidden="true"></span>'; }
    private static function date($date) { return mysql2date('d M Y', $date . (strlen($date) === 10 ? ' 00:00:00' : '')); }
    // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom IDLM tables named from $wpdb->prefix; values are bound with prepare(), cast to int, or come from fixed clause builders and generated placeholder lists; request-scoped reads and transactional writes are deliberately uncached. Builder: Shashi.
    // phpcs:disable WordPress.Security.NonceVerification.Recommended -- POST handlers verify the nonce (check_admin_referer or check_ajax_referer) and capability before acting, reading the action name first only to choose that nonce; GET values are display-only routing, cast or sanitized before use; uploads are validated by IDLM_Private_Files::upload(). Builder: Shashi.
    public static function render() {
        global $wpdb;
        if (!self::visible()) { echo '<p>Payment access denied.</p>'; return; }
        self::enqueue_payment_assets();
        $lead_id = self::id($_GET, 'payment_lead_id');
        $base = is_admin() ? admin_url('admin.php?page=idlm-payments') : add_query_arg('idlm_view', 'payments', IDLM_Admin_Shortcodes::workspace_url());
        echo '<div class="wrap idlm-wrap idlm-quotes-wrap idlm-payments">';
        $leads_url = add_query_arg('idlm_view', 'leads', IDLM_Admin_Shortcodes::workspace_url());
        $retired = !is_admin() && !self::tab_shown();
        if (!$lead_id && $retired) {
            echo '<header class="idlm-header"><div><h1>Payments</h1></div></header><div class="idlm-card"><div class="idlm-state is-empty"><strong>Payments open from each lead</strong><span>Open a Won lead and choose Payment details, or use the project in Design team.</span><a class="idlm-btn idlm-btn-secondary" href="' . esc_url($leads_url) . '">Go to leads</a></div></div></div>';
            return;
        }
        if (!$lead_id) { self::render_leads($base); echo '</div>'; return; }
        $context = IDLM_Lead_Payments::read($lead_id);
        if (is_wp_error($context)) { echo '<div class="idlm-payment-empty' . (IDLM_Admin_Assets::design_layer_enabled() ? ' idlm-state is-error' : '') . '"><h1>Payments</h1><p>' . esc_html($context->get_error_message()) . '</p></div></div>'; return; }
        $lead = $wpdb->get_row($wpdb->prepare("SELECT first_name,last_name,project_name,deal_value FROM {$wpdb->prefix}idlm_leads WHERE id=%d", $lead_id));
        $return_url = add_query_arg('payment_lead_id', $lead_id, $base);
        $version = $context['version']; $schedule = $context['schedule'];
        $can_record = IDLM_Lead_Payments::permitted($lead_id); $can_correct = IDLM_Lead_Payments::permitted($lead_id, true);
        $customer = $lead ? trim($lead->first_name . ' ' . $lead->last_name) : '';
        if (IDLM_Admin_Assets::design_layer_enabled()) {
            // One summary, one milestone table, one payments list; every form in a dialog. The legacy page below is unchanged.
            self::render_simple($lead_id, ($lead->project_name ?? '') ?: $customer, $customer, $context, $return_url, $retired ? $leads_url : $base, $retired, $can_record, $can_correct, (string) ($lead->deal_value ?? '0'));
            echo '</div>'; return;
        }
        echo '<header class="idlm-header"><div><h1>Payments</h1></div><div class="idlm-header-actions"><a class="idlm-btn idlm-btn-secondary" href="' . esc_url($retired ? $leads_url : $base) . '">' . ($retired ? 'All leads' : 'All payment leads') . '</a></div></header><p class="idlm-payment-context">' . esc_html((($lead->project_name ?? '') ?: $customer) . ' · ' . $customer . ' · Lead #' . $lead_id) . '</p>';
        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped when built: helpers and variables here concatenate only esc_html/esc_attr/esc_url values, nonce fields and fixed markup. Builder: Shashi.
        if (isset($_GET['payment_saved'])) { echo '<div class="idlm-alert idlm-alert-success" role="status">' . self::icon('yes-alt') . 'Payment operation saved.</div>'; }
        if ($schedule) {
            self::render_summary($context);
            echo '<div class="idlm-payment-layout' . ($can_record ? ' idlm-quote-layout has-entry' : '') . '"><main class="idlm-payment-main">';
            self::render_timeline($context);
            self::render_history($lead_id, $context, $return_url, $can_correct);
            if ($can_record && $can_correct) { self::render_schedule($lead_id, $version, $return_url, true); }
            echo '</main>';
            if ($can_record) { self::render_entry($lead_id, $context, $return_url); }
            echo '</div>';
        } else {
            // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped when built: helpers and variables here concatenate only esc_html/esc_attr/esc_url values, nonce fields and fixed markup. Builder: Shashi.
            echo '<div class="idlm-payment-setup"><div class="idlm-payment-setup-intro">' . self::icon('media-spreadsheet') . '<h2>Start with the agreed quotation</h2><p>Select the saved revision and identify its advance milestones. Your collection timeline will use those exact amounts.</p><p class="idlm-payment-muted">Publishing a timeline does not record a payment or submit a design handover.</p></div>';
            if ($can_record || $can_correct) { self::render_schedule($lead_id, $version, $return_url, false); }
            else { echo '<p>A payment-authorized sales user must publish the agreed timeline first.</p>'; }
            echo '</div>';
        }
        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped when built: helpers and variables here concatenate only esc_html/esc_attr/esc_url values, nonce fields and fixed markup. Builder: Shashi.
        echo '<p class="idlm-payment-footnote">' . self::icon('lock') . 'Private receipt records. Evidence does not imply bank verification.</p></div>';
    }
    // phpcs:enable WordPress.Security.NonceVerification.Recommended
    // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
    // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared -- Custom IDLM tables named from $wpdb->prefix; values are bound with prepare(), cast to int, or come from fixed clause builders and generated placeholder lists; request-scoped reads and transactional writes are deliberately uncached. Builder: Shashi.
    // phpcs:disable WordPress.Security.NonceVerification.Recommended -- POST handlers verify the nonce (check_admin_referer or check_ajax_referer) and capability before acting, reading the action name first only to choose that nonce; GET values are display-only routing, cast or sanitized before use; uploads are validated by IDLM_Private_Files::upload(). Builder: Shashi.
    private static function render_leads($base) {
        global $wpdb;
        echo '<header class="idlm-header"><div><h1>Payments</h1></div></header>';
        $sql = "SELECT id,first_name,last_name,project_name FROM {$wpdb->prefix}idlm_leads WHERE stage='Won' AND is_duplicate=0";
        if (!IDLM_Lead_Access_Policy::can_assign()) { $sql .= $wpdb->prepare(' AND assigned_rep_id=%d', get_current_user_id()); }
        $page = max(1, min(1000, self::id($_GET, 'payment_page')));
        $leads = $wpdb->get_results($wpdb->prepare($sql . ' ORDER BY id DESC LIMIT 51 OFFSET %d', ($page - 1) * 50));
        if (IDLM_Admin_Assets::design_layer_enabled()) {
            echo '<div class="idlm-card idlm-payment-list"><div class="idlm-table-wrapper"><table class="idlm-table idlm-payment-leads"><caption class="screen-reader-text">Won leads eligible for payment recording, including existing leads.</caption><thead><tr><th scope="col">Lead</th><th scope="col">Customer</th><th scope="col">Project</th><th scope="col"><span class="screen-reader-text">Action</span></th></tr></thead><tbody>';
            if (!$leads) { echo '<tr><td colspan="4"><div class="idlm-state is-empty"><strong>No Won leads in your payment scope</strong><span>Leads appear here once they reach the Won stage and are assigned to you.</span></div></td></tr>'; }
            foreach (array_slice($leads, 0, 50) as $lead) {
                $name = trim($lead->first_name . ' ' . $lead->last_name); $open = esc_url(add_query_arg('payment_lead_id', $lead->id, $base));
                // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped when built: helpers and variables here concatenate only esc_html/esc_attr/esc_url values, nonce fields and fixed markup. Builder: Shashi.
                echo '<tr class="idlm-payment-lead-row"><td class="idlm-payment-lead-id">#' . esc_html($lead->id) . '</td><td class="idlm-payment-lead-name"><a class="idlm-payment-row-link" href="' . $open . '"><strong>' . esc_html($name) . '</strong></a></td><td class="idlm-payment-lead-project">' . esc_html($lead->project_name) . '</td><td class="idlm-payment-lead-action"><a class="idlm-btn idlm-btn-secondary" href="' . $open . '" aria-label="' . esc_attr('Open payments for ' . $name . ', lead #' . $lead->id) . '">Open</a></td></tr>';
            }
        } else {
        echo '<div class="idlm-card"><div class="idlm-table-wrapper"><table class="idlm-table"><caption class="screen-reader-text">Won leads eligible for payment recording, including existing leads.</caption><thead><tr><th scope="col">Lead</th><th scope="col">Customer / Project</th><th scope="col">Action</th></tr></thead><tbody>';
        if (!$leads) { echo '<tr><td colspan="3">No Won leads in your payment scope.</td></tr>'; }
        foreach (array_slice($leads, 0, 50) as $lead) {
            echo '<tr><td>#' . esc_html($lead->id) . '</td><td><strong>' . esc_html(trim($lead->first_name . ' ' . $lead->last_name)) . '</strong><br><small>' . esc_html($lead->project_name) . '</small></td><td><a class="idlm-btn idlm-btn-secondary" href="' . esc_url(add_query_arg('payment_lead_id', $lead->id, $base)) . '">Open</a></td></tr>';
        }
        }
        echo '</tbody></table></div></div><nav class="idlm-pagination" aria-label="Payment leads pages">';
        if ($page > 1) { echo '<a class="idlm-btn idlm-btn-secondary" href="' . esc_url(add_query_arg('payment_page', $page - 1, $base)) . '">Previous</a>'; }
        if (count($leads) > 50) { echo '<a class="idlm-btn idlm-btn-secondary" href="' . esc_url(add_query_arg('payment_page', $page + 1, $base)) . '">Next</a>'; }
        echo '</nav>';
    }
    // phpcs:enable WordPress.Security.NonceVerification.Recommended
    // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared
    private static function render_summary($context) {
        $balance = $context['balance']; $currency = $balance['currency'];
        echo '<section class="idlm-quote-summary" aria-label="Collection summary">';
        foreach (array('basis_total' => 'Agreed value', 'total_received' => 'Total received', 'total_outstanding' => 'Outstanding', 'advance_pending' => 'Advance pending') as $key => $label) {
            echo '<div><span>' . esc_html($label) . '</span><strong>' . esc_html(self::money($balance[$key], $currency)) . '</strong></div>';
        }
        echo '</section><p class="idlm-payment-context">Advance status: <strong>' . esc_html($balance['advance_status']) . '</strong> · ' . esc_html(self::money($balance['advance_received'], $currency) . ' received of ' . self::money($balance['expected_advance'], $currency)) . '. Short payments do not block design handover.</p>';
    }
    // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom IDLM tables named from $wpdb->prefix; values are bound with prepare(), cast to int, or come from fixed clause builders and generated placeholder lists; request-scoped reads and transactional writes are deliberately uncached. Builder: Shashi.
    private static function render_timeline($context) {
        global $wpdb;
        $schedule = $context['schedule']; $balance = $context['balance']; $currency = $balance['currency'];
        $basis = (int) $schedule['revision_id'] ? (string) $wpdb->get_var($wpdb->prepare("SELECT revision_name FROM {$wpdb->prefix}idlm_quote_revisions WHERE id=%d", $schedule['revision_id'])) : 'Won amount on lead';
        echo '<section class="idlm-payment-panel"><div class="idlm-panel-heading idlm-payment-panel-heading"><div><h2>Payment schedule</h2><p>' . esc_html($basis) . '<span class="idlm-payment-separator">·</span>Schedule version ' . esc_html($schedule['version_no']) . '</p></div><span class="idlm-payment-currency">' . esc_html($currency) . '</span></div>';
        echo '<div class="idlm-table-wrapper"><table class="idlm-table idlm-payment-timeline"><caption class="screen-reader-text">Expected, received and pending amounts from the saved payment schedule.</caption><thead><tr><th scope="col">Milestone</th><th scope="col">Expected</th><th scope="col">Received</th><th scope="col">Pending</th></tr></thead><tbody>';
        foreach ($balance['milestones'] as $i => $milestone) {
            $pending = IDLM_Lead_Payment_Ledger::money($milestone['pending']);
            echo '<tr><td class="idlm-payment-milestone"><strong>' . esc_html($milestone['name']) . '</strong><small>' . esc_html(rtrim(rtrim($milestone['percentage'], '0'), '.') . '%' . ($milestone['advance'] ? ' · Advance' : '')) . '</small></td>';
            foreach (array('amount' => 'Expected', 'received' => 'Received', 'pending' => 'Pending') as $key => $label) {
                echo '<td data-label="' . esc_attr($label) . '"' . ($key === 'pending' && $pending ? ' class="idlm-payment-pending-amount"' : '') . '>' . esc_html(self::money($milestone[$key], $currency)) . '</td>';
            }
            echo '</tr>';
        }
        echo '</tbody></table></div>';
        $snapshot = json_decode($schedule['snapshot'], true);
        if (isset($snapshot['resolution'])) {
            $resolver = get_userdata($snapshot['resolution']['resolved_by']);
            echo '<details class="idlm-payment-audit"><summary>Recovered historic terms</summary><p>Source: ' . esc_html($snapshot['resolution']['source_reference']) . '</p><p>' . esc_html(($resolver ? $resolver->display_name : 'Authorized user') . ' · ' . $snapshot['resolution']['resolved_at'] . ' UTC') . '</p><p>Reason: ' . esc_html($schedule['reason']) . '</p></details>';
        }
        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped when built: helpers and variables here concatenate only esc_html/esc_attr/esc_url values, nonce fields and fixed markup. Builder: Shashi.
        echo '<details class="idlm-payment-more"><summary>Balance details ' . self::icon('arrow-down-alt2') . '</summary><dl>';
        foreach (array('unallocated_credit' => 'Unallocated credit', 'excess_credit' => 'Excess credit', 'unscheduled_balance' => 'Unscheduled balance') as $key => $label) { echo '<div><dt>' . esc_html($label) . '</dt><dd>' . esc_html(self::money($balance[$key], $currency)) . '</dd></div>'; }
        echo '</dl><p>Pending amounts remain in the timeline. Credits and carried balances are never counted twice.</p></details></section>';
    }
    // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
    // phpcs:disable WordPress.Security.NonceVerification.Recommended -- POST handlers verify the nonce (check_admin_referer or check_ajax_referer) and capability before acting, reading the action name first only to choose that nonce; GET values are display-only routing, cast or sanitized before use; uploads are validated by IDLM_Private_Files::upload(). Builder: Shashi.
    private static function render_entry($lead_id, $context, $return_url) {
        $version = $context['version']; $schedule = $context['schedule']; $attachment = self::id($_GET, 'payment_attachment_id');
        echo '<aside class="idlm-card idlm-payment-entry" aria-labelledby="payment-entry-title"><div class="idlm-panel-heading idlm-payment-panel-heading"><div><h2 id="payment-entry-title">Record a receipt</h2><p>Enter the amount actually received.</p></div></div><div class="idlm-payment-entry-body">';
        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped when built: helpers and variables here concatenate only esc_html/esc_attr/esc_url values, nonce fields and fixed markup. Builder: Shashi.
        echo '<details class="idlm-payment-upload"' . ($attachment ? ' open' : '') . '><summary>' . self::icon('upload') . '<span>' . ($attachment ? 'Screenshot ready' : 'Using a screenshot?') . '<small>' . ($attachment ? 'Review the selected private evidence.' : 'Upload it before entering the receipt.') . '</small></span>' . self::icon('arrow-down-alt2') . '</summary>';
        echo '<form method="post" enctype="multipart/form-data" action="' . esc_url(admin_url('admin-post.php')) . '">';
        self::hidden('action', 'idlm_payment_upload'); self::hidden('payment_lead_id', $lead_id); self::hidden('payment_version', $version); self::hidden('return_url', $return_url); wp_nonce_field('idlm_payment_upload');
        echo '<label class="idlm-payment-file-label" for="payment-screenshot">Payment screenshot <small>JPEG, PNG or WebP · Up to 10 MB</small></label><input id="payment-screenshot" type="file" name="payment_screenshot" accept="image/jpeg,image/png,image/webp" required><button class="idlm-btn idlm-btn-secondary">Upload screenshot</button></form></details>';
        self::form('record', $lead_id, $version, $return_url); self::receipt_fields($schedule['currency'], $attachment);
        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped when built: helpers and variables here concatenate only esc_html/esc_attr/esc_url values, nonce fields and fixed markup. Builder: Shashi.
        echo '<div class="idlm-payment-form-footer"><button class="idlm-btn idlm-btn-primary">Record receipt</button><p>' . self::icon('lock') . 'Saved privately to this project.</p></div></form></div></aside>';
    }
    // phpcs:enable WordPress.Security.NonceVerification.Recommended
    // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom IDLM tables named from $wpdb->prefix; values are bound with prepare(), cast to int, or come from fixed clause builders and generated placeholder lists; request-scoped reads and transactional writes are deliberately uncached. Builder: Shashi.
    // phpcs:disable WordPress.Security.NonceVerification.Recommended -- POST handlers verify the nonce (check_admin_referer or check_ajax_referer) and capability before acting, reading the action name first only to choose that nonce; GET values are display-only routing, cast or sanitized before use; uploads are validated by IDLM_Private_Files::upload(). Builder: Shashi.
    private static function render_schedule($lead_id, $version, $return_url, $amendment) {
        global $wpdb;
        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped when built: helpers and variables here concatenate only esc_html/esc_attr/esc_url values, nonce fields and fixed markup. Builder: Shashi.
        echo '<details class="idlm-card idlm-payment-settings"' . (!$amendment || self::id($_GET, 'payment_revision_id') ? ' open' : '') . '><summary>' . self::icon('media-spreadsheet') . '<span>' . ($amendment ? 'Amend payment schedule' : 'Select agreed revision and advance milestones') . '</span>' . self::icon('arrow-down-alt2') . '</summary><div class="idlm-payment-settings-body">';
        if ($amendment) { echo '<p>Publish a new schedule with a reason. Existing schedules and receipts remain in history.</p>'; }
        $revisions = $wpdb->get_results($wpdb->prepare("SELECT r.id,r.revision_name FROM {$wpdb->prefix}idlm_quote_revisions r INNER JOIN {$wpdb->prefix}idlm_quotes q ON q.id=r.quote_id LEFT JOIN {$wpdb->prefix}idlm_design_revisions d ON d.revision_id=r.id WHERE q.lead_id=%d AND d.revision_id IS NULL ORDER BY r.id DESC LIMIT 100", $lead_id));
        echo '<div class="idlm-payment-revisions">';
        $revision_id = self::id($_GET, 'payment_revision_id');
        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped when built: helpers and variables here concatenate only esc_html/esc_attr/esc_url values, nonce fields and fixed markup. Builder: Shashi.
        foreach ($revisions as $revision) { echo '<a class="idlm-payment-revision ' . ((int) $revision->id === $revision_id ? 'is-selected' : '') . '" href="' . esc_url(add_query_arg('payment_revision_id', $revision->id, $return_url)) . '">' . self::icon('media-document') . esc_html($revision->revision_name) . self::icon('arrow-right-alt2') . '</a>'; }
        echo '</div>';
        if (!$revisions) { echo '<p>No saved quotation revision is available for this lead.</p>'; }
        $revision = $revision_id ? $wpdb->get_row($wpdb->prepare("SELECT r.* FROM {$wpdb->prefix}idlm_quote_revisions r INNER JOIN {$wpdb->prefix}idlm_quotes q ON q.id=r.quote_id WHERE r.id=%d AND q.lead_id=%d", $revision_id, $lead_id)) : null;
        if ($revision) {
            $terms = IDLM_Quotation_Document::read($revision);
            if (!$terms || is_wp_error($terms) || !$terms['milestones']) {
                echo '<p class="idlm-payment-context">This revision has no usable saved timeline. Recover the agreed historic terms before recording receipts.</p>';
                if (IDLM_Lead_Payments::permitted($lead_id, true)) { self::render_legacy_resolution($lead_id, $revision, $version, $return_url); }
                else { echo '<p>An authorized manager with payment-correction permission must resolve this legacy quotation. Current settings are not substituted.</p>'; }
            }
            elseif (IDLM_Lead_Payments::permitted($lead_id)) {
                self::form('schedule', $lead_id, $version, $return_url); self::hidden('payment_revision_id', $revision_id);
                echo '<fieldset><legend>Which milestones are the advance?</legend><p class="idlm-payment-muted">Select from the saved timeline for ' . esc_html($revision->revision_name) . '.</p>';
                foreach ($terms['milestones'] as $i => $milestone) { echo '<label class="idlm-payment-mapping"><input type="checkbox" name="advance_positions[]" value="' . esc_attr($i) . '"><span>' . esc_html($milestone['name']) . '<small>' . esc_html($milestone['percentage'] . '%') . '</small></span><strong>' . esc_html(self::money($milestone['amount'], $revision->currency_code)) . '</strong></label>'; }
                echo '</fieldset>'; self::field('reason', 'Agreed schedule / amendment reason', '', 'text', true);
                echo '<button class="idlm-btn idlm-btn-primary">Publish schedule version</button></form>';
            }
        }
        echo '</div></details>';
    }
    // phpcs:enable WordPress.Security.NonceVerification.Recommended
    // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
    private static function render_legacy_resolution($lead_id, $revision, $version, $return_url) {
        self::form('resolve_legacy', $lead_id, $version, $return_url); self::hidden('payment_revision_id', $revision->id);
        echo '<h3>Recover historic payment terms</h3><p class="idlm-payment-context">' . esc_html($revision->revision_name . ' · Saved total ' . self::money($revision->grand_total, $revision->currency_code)) . '. Enter percentages from the agreed document. Expected amounts use this saved total and quotation rounding.</p><fieldset><legend>Agreed milestones</legend><div class="idlm-payment-legacy-rows">';
        for ($i = 0; $i < 8; $i++) {
            if ($i === 3) { echo '<details><summary>Additional milestones (4–8)</summary>'; }
            echo '<div class="idlm-payment-legacy-row"><div>';
            self::field('legacy_milestones[' . $i . '][name]', 'Milestone ' . ($i + 1) . ' name');
            echo '</div><div>'; self::field('legacy_milestones[' . $i . '][percentage]', 'Percentage');
            echo '</div><label class="idlm-payment-checkbox"><input type="checkbox" name="legacy_milestones[' . esc_attr($i) . '][advance]" value="1"> Advance</label></div>';
        }
        echo '</details></div></fieldset>';
        self::field('source_reference', 'Historic document / agreement reference', '', 'text', true, 'Identify the signed quotation, email or agreement used to recover these terms.');
        self::field('reason', 'Resolution reason', '', 'text', true);
        echo '<label class="idlm-payment-checkbox"><input type="checkbox" name="legacy_confirmed" value="1" required> I confirm these are the agreed historic percentages and advance milestones for the selected revision.</label><p class="idlm-payment-context">This saves a versioned payment schedule. The original quotation is preserved and no receipt is created.</p><button class="idlm-btn idlm-btn-primary">Save recovered timeline</button></form>';
    }
    // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom IDLM tables named from $wpdb->prefix; values are bound with prepare(), cast to int, or come from fixed clause builders and generated placeholder lists; request-scoped reads and transactional writes are deliberately uncached. Builder: Shashi.
    // phpcs:disable WordPress.Security.NonceVerification.Recommended -- POST handlers verify the nonce (check_admin_referer or check_ajax_referer) and capability before acting, reading the action name first only to choose that nonce; GET values are display-only routing, cast or sanitized before use; uploads are validated by IDLM_Private_Files::upload(). Builder: Shashi.
    private static function render_history($lead_id, $context, $return_url, $can_correct) {
        global $wpdb;
        $versions = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$wpdb->prefix}idlm_lead_payment_versions WHERE lead_id=%d ORDER BY id DESC LIMIT 100", $lead_id));
        $groups = array(); foreach ($versions as $receipt) { $groups[$receipt->confirmation_id][] = $receipt; }
        echo '<section class="idlm-card idlm-payment-panel"><div class="idlm-panel-heading idlm-payment-panel-heading"><div><h2>Receipt history</h2><p>Recorded payments and their audit trail.</p></div><span class="idlm-payment-count">' . esc_html(count($context['receipts'])) . '</span></div>';
        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped when built: helpers and variables here concatenate only esc_html/esc_attr/esc_url values, nonce fields and fixed markup. Builder: Shashi.
        if (!$groups) { echo '<div class="idlm-payment-empty' . (IDLM_Admin_Assets::design_layer_enabled() ? ' idlm-state is-empty' : '') . '">' . self::icon('media-text') . '<h3>No receipts recorded</h3><p>Record the first actual payment to start tracking collections.</p></div>'; }
        foreach ($groups as $group) {
            $receipt = $group[0]; $revoked = $receipt->status === 'revoked';
            // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped when built: helpers and variables here concatenate only esc_html/esc_attr/esc_url values, nonce fields and fixed markup. Builder: Shashi.
            echo '<details class="idlm-payment-receipt"><summary><span class="idlm-payment-receipt-icon">' . self::icon($revoked ? 'undo' : 'saved') . '</span><span class="idlm-payment-receipt-title"><strong>' . esc_html($receipt->method) . '</strong><small>' . esc_html(self::date($receipt->received_date) . ' · Receipt #' . $receipt->confirmation_id) . '</small></span><span class="idlm-payment-receipt-value"><strong>' . esc_html(self::money($receipt->amount, $receipt->currency)) . '</strong><span class="idlm-badge idlm-payment-status ' . ($revoked ? 'is-revoked' . (IDLM_Admin_Assets::design_layer_enabled() ? ' is-neutral' : '') : 'is-success') . '">' . ($revoked ? 'Revoked' : 'Recorded') . '</span></span>' . self::icon('arrow-down-alt2') . '</summary><div class="idlm-payment-receipt-body">';
            self::receipt_detail($receipt);
            if (count($group) > 1) {
                echo '<details class="idlm-payment-audit"><summary>Previous versions (' . esc_html(count($group) - 1) . ')</summary>';
                foreach (array_slice($group, 1) as $historic) { echo '<div class="idlm-payment-audit-version"><strong>' . esc_html('Version ' . $historic->version_no . ' · ' . self::money($historic->amount, $historic->currency)) . '</strong>'; self::receipt_detail($historic); echo '</div>'; }
                echo '</details>';
            }
            $current = false; foreach ($context['receipts'] as $live) { if ((int) $live['id'] === (int) $receipt->id) { $current = true; break; } }
            if ($can_correct && $current && !$revoked) {
                echo '<details class="idlm-payment-correction"><summary>Correct this receipt</summary>';
                self::form('correct', $lead_id, $context['version'], $return_url); self::hidden('confirmation_id', $receipt->confirmation_id);
                self::receipt_fields($context['schedule']['currency'], self::id($_GET, 'payment_attachment_id') ?: $receipt->attachment_id, $receipt); self::field('reason', 'Correction reason', '', 'text', true);
                echo '<button class="idlm-btn idlm-btn-secondary">Save corrected version</button></form></details><details class="idlm-payment-revoke"><summary>Revoke this receipt</summary><p>The receipt will stop counting toward collections. Its history will be preserved.</p>';
                self::form('revoke', $lead_id, $context['version'], $return_url); self::hidden('confirmation_id', $receipt->confirmation_id); self::field('reason', 'Revocation reason', '', 'text', true);
                echo '<button class="idlm-btn idlm-btn-danger-outline">Revoke receipt</button></form></details>';
            }
            echo '</div></details>';
        }
        echo '</section>';
    }
    // phpcs:enable WordPress.Security.NonceVerification.Recommended
    // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
    private static function receipt_detail($receipt) {
        $actor = get_userdata($receipt->actor_id);
        echo '<dl class="idlm-payment-receipt-meta">';
        foreach (array('reference' => 'Transaction reference', 'note' => 'Confirmation note', 'reason' => 'Change reason', 'duplicate_review_reason' => 'Duplicate review') as $key => $label) {
            if ($receipt->$key !== '') { echo '<div><dt>' . esc_html($label) . '</dt><dd>' . esc_html($receipt->$key) . '</dd></div>'; }
        }
        echo '<div><dt>Recorded by</dt><dd>' . esc_html(($actor ? $actor->display_name : 'Team member') . ' · ' . self::date($receipt->recorded_at) . ' · Version ' . $receipt->version_no) . '</dd></div></dl>';
        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped when built: helpers and variables here concatenate only esc_html/esc_attr/esc_url values, nonce fields and fixed markup. Builder: Shashi.
        if ($receipt->attachment_id) { echo '<a class="idlm-payment-proof-link" href="' . esc_url(IDLM_Private_Files::url($receipt->attachment_id)) . '">' . self::icon('format-image') . 'Private screenshot ' . self::icon('external') . '</a>'; }
    }
    private static function receipt_fields($currency, $attachment_id, $receipt = null) {
        self::hidden('currency', $currency);
        echo '<div class="idlm-payment-form-grid">';
        self::field('amount', 'Actual amount', $receipt->amount ?? '', 'text', true, 'Amount received in ' . $currency . '.');
        self::field('received_date', 'Received date', $receipt->received_date ?? '', 'date', true);
        echo '</div>';
        self::field('method', 'Payment method', $receipt->method ?? '', 'text', true, 'For example, UPI, bank transfer or cash.');
        self::field('reference', 'URN / transaction reference', $receipt->reference ?? '', 'text', !$attachment_id, 'Required unless a payment screenshot is selected.');
        echo '<details class="idlm-payment-optional"><summary>Notes and duplicate reference review</summary>';
        self::field('note', 'Confirmation note', $receipt->note ?? '');
        self::field('duplicate_review_reason', 'Duplicate reference review reason', $receipt->duplicate_review_reason ?? '', 'text', false, 'Only needed when a separate receipt uses the same reference.');
        echo '</details>';
        self::hidden('attachment_id', $attachment_id);
        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped when built: helpers and variables here concatenate only esc_html/esc_attr/esc_url values, nonce fields and fixed markup. Builder: Shashi.
        if ($attachment_id) { echo '<div class="idlm-payment-selected-proof">' . self::icon('yes-alt') . '<a href="' . esc_url(IDLM_Private_Files::url($attachment_id)) . '">View selected screenshot</a></div><label class="idlm-payment-checkbox"><input type="checkbox" name="remove_screenshot" value="1"> Use URN only for this version</label>'; }
    }

    /* ---- Simple page (design layer only) ---------------------------------- */
    const METHODS = array('UPI', 'Bank transfer', 'Cheque', 'Card', 'Cash');
    const DONE = array('record' => 'Payment recorded.', 'schedule' => 'Payment plan set.', 'resolve_legacy' => 'Payment plan saved from the old quotation.', 'won_plan' => 'Payment plan set from the Won amount.', 'correct' => 'Payment corrected.', 'revoke' => 'Payment revoked.');
    private static function advance_badge($status) {
        $badges = array('Met' => array('is-success', 'Advance paid'), 'Partial' => array('is-warning', 'Advance partly paid'), 'Unpaid' => array('is-neutral', 'Advance not paid'));
        $badge = $badges[$status] ?? array('is-neutral', 'Advance ' . strtolower((string) $status));
        return '<span class="idlm-badge ' . esc_attr($badge[0]) . '">' . esc_html($badge[1]) . '</span>';
    }
    private static function dialog_open($id, $title, $description, $operation, $lead_id, $version, $return_url, $multipart = false, $attributes = '') {
        wp_enqueue_script('idlm-design-detail', IDLM_URL . 'assets/js/design-detail.js', array('idlm-dialog'), IDLM_Admin_Assets::version('js/design-detail.js'), true);
        echo '<div class="idlm-modal idlm-dialog idlm-design-dialog" id="' . esc_attr($id) . '" role="dialog" aria-modal="true" aria-labelledby="' . esc_attr($id . '-title') . '"' . ($description ? ' aria-describedby="' . esc_attr($id . '-description') . '"' : '') . ' aria-hidden="true" hidden data-idlm-dialog data-idlm-initial-focus=".idlm-dialog__body :is(textarea, select, input:not([type=hidden]))"><div class="idlm-modal-content idlm-dialog__panel"><header class="idlm-modal-header idlm-dialog__header"><div><h2 class="idlm-dialog__title" id="' . esc_attr($id . '-title') . '">' . esc_html($title) . '</h2>' . ($description ? '<p class="idlm-dialog__description" id="' . esc_attr($id . '-description') . '">' . esc_html($description) . '</p>' : '') . '</div><button type="button" class="idlm-dialog__close" data-idlm-dialog-close aria-label="Close dialog"><span class="dashicons dashicons-no-alt" aria-hidden="true"></span></button></header>';
        if (null === $operation) { echo '<div class="idlm-dialog__body">'; return; }
        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped when built: helpers and variables here concatenate only esc_html/esc_attr/esc_url values, nonce fields and fixed markup. Builder: Shashi.
        echo '<form class="idlm-design-dialog-form idlm-payment-form" method="post"' . ($multipart ? ' enctype="multipart/form-data"' : '') . ' action="' . esc_url(admin_url('admin-post.php')) . '"' . $attributes . '>';
        self::hidden('action', 'idlm_payment_' . $operation); self::hidden('payment_lead_id', $lead_id);
        self::hidden('payment_version', $version); self::hidden('request_key', wp_generate_uuid4()); self::hidden('return_url', $return_url);
        wp_nonce_field('idlm_payment_' . $operation);
        echo '<div class="idlm-dialog__body">';
    }
    private static function dialog_close($submit, $danger = false) {
        if (null === $submit) { echo '</div><footer class="idlm-dialog__footer"><button type="button" class="idlm-btn idlm-btn-secondary" data-idlm-dialog-close>Close</button></footer></div></div>'; return; }
        echo '</div><footer class="idlm-dialog__footer"><button type="button" class="idlm-btn idlm-btn-secondary" data-idlm-dialog-close>Cancel</button><button class="idlm-btn ' . ($danger ? 'idlm-btn-danger' : 'idlm-btn-primary') . '">' . esc_html($submit) . '</button></footer></form></div></div>';
    }
    private static function group($id, $label, $control, $help = '') {
        return '<div class="idlm-form-group"><label for="' . esc_attr($id) . '">' . $label . '</label>' . $control . ($help ? '<small id="' . esc_attr($id . '-help') . '">' . esc_html($help) . '</small>' : '') . '</div>';
    }
    /** Amount, date, method, reference or screenshot, note; the repeated-reference field shows only when payments.js finds a match. */
    private static function simple_receipt_fields($prefix, $currency, $receipt = null) {
        self::hidden('currency', $currency);
        $method = $receipt->method ?? '';
        $options = '';
        foreach (array_unique(array_merge(self::METHODS, '' !== $method ? array($method) : array())) as $option) { $options .= '<option value="' . esc_attr($option) . '"' . selected($option, $method, false) . '>' . esc_html($option) . '</option>'; }
        echo '<div class="idlm-design-dialog-grid">';
        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped when built: helpers and variables here concatenate only esc_html/esc_attr/esc_url values, nonce fields and fixed markup. Builder: Shashi.
        echo self::group($prefix . '-amount', esc_html('Amount received (' . $currency . ')'), '<input class="idlm-input" id="' . esc_attr($prefix . '-amount') . '" name="amount" inputmode="decimal" autocomplete="off" required value="' . esc_attr($receipt->amount ?? '') . '">');
        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped when built: helpers and variables here concatenate only esc_html/esc_attr/esc_url values, nonce fields and fixed markup. Builder: Shashi.
        echo self::group($prefix . '-date', 'Date received', '<input class="idlm-input" type="date" id="' . esc_attr($prefix . '-date') . '" name="received_date" required value="' . esc_attr($receipt->received_date ?? current_time('Y-m-d')) . '">');
        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped when built: helpers and variables here concatenate only esc_html/esc_attr/esc_url values, nonce fields and fixed markup. Builder: Shashi.
        echo self::group($prefix . '-method', 'Method', '<select class="idlm-input" id="' . esc_attr($prefix . '-method') . '" name="method" required>' . $options . '</select>');
        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped when built: helpers and variables here concatenate only esc_html/esc_attr/esc_url values, nonce fields and fixed markup. Builder: Shashi.
        echo self::group($prefix . '-reference', 'Transaction reference', '<input class="idlm-input" id="' . esc_attr($prefix . '-reference') . '" name="reference" maxlength="255" autocomplete="off" value="' . esc_attr($receipt->reference ?? '') . '">');
        echo '</div>';
        $current = $receipt ? (int) $receipt->attachment_id : 0;
        self::hidden('attachment_id', $current);
        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped when built: helpers and variables here concatenate only esc_html/esc_attr/esc_url values, nonce fields and fixed markup. Builder: Shashi.
        echo self::group($prefix . '-file', $current ? 'Replace screenshot' : 'Or payment screenshot', '<input class="idlm-input" type="file" id="' . esc_attr($prefix . '-file') . '" name="payment_screenshot" accept="image/jpeg,image/png,image/webp" aria-describedby="' . esc_attr($prefix . '-file-help') . '">', 'A reference or a screenshot is required. JPEG, PNG or WebP, up to 10 MB.');
        if ($current) { echo '<p><a href="' . esc_url(IDLM_Private_Files::url($current)) . '" target="_blank" rel="noopener">View current screenshot</a></p><label class="idlm-payment-checkbox"><input type="checkbox" name="remove_screenshot" value="1"> Remove the current screenshot</label>'; }
        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped when built: helpers and variables here concatenate only esc_html/esc_attr/esc_url values, nonce fields and fixed markup. Builder: Shashi.
        echo self::group($prefix . '-note', 'Note (optional)', '<textarea class="idlm-input" id="' . esc_attr($prefix . '-note') . '" name="note" rows="2" maxlength="8000">' . esc_textarea($receipt->note ?? '') . '</textarea>');
        $reviewed = '' !== (string) ($receipt->duplicate_review_reason ?? '');
        echo '<div class="idlm-form-group" data-payment-duplicate' . ($reviewed ? '' : ' hidden') . '><label for="' . esc_attr($prefix . '-duplicate') . '">This reference was used for an earlier payment. Why is this a separate payment?</label><textarea class="idlm-input" id="' . esc_attr($prefix . '-duplicate') . '" name="duplicate_review_reason" rows="2" maxlength="8000"' . ($reviewed ? '' : ' disabled') . '>' . esc_textarea($receipt->duplicate_review_reason ?? '') . '</textarea></div>';
    }
    private static function reason_group($id, $label) {
        return self::group($id, esc_html($label), '<textarea class="idlm-input" id="' . esc_attr($id) . '" name="reason" rows="2" maxlength="8000" required></textarea>');
    }
    // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom IDLM tables named from $wpdb->prefix; values are bound with prepare(), cast to int, or come from fixed clause builders and generated placeholder lists; request-scoped reads and transactional writes are deliberately uncached. Builder: Shashi.
    private static function simple_revisions($lead_id) {
        global $wpdb;
        $rows = $wpdb->get_results($wpdb->prepare("SELECT r.* FROM {$wpdb->prefix}idlm_quote_revisions r INNER JOIN {$wpdb->prefix}idlm_quotes q ON q.id=r.quote_id LEFT JOIN {$wpdb->prefix}idlm_design_revisions d ON d.revision_id=r.id WHERE q.lead_id=%d AND d.revision_id IS NULL ORDER BY r.id DESC LIMIT 20", $lead_id));
        $usable = array(); $legacy = array();
        foreach ((array) $rows as $revision) {
            $terms = IDLM_Quotation_Document::read($revision);
            if ($terms && !is_wp_error($terms) && !empty($terms['milestones'])) { $usable[] = array('revision' => $revision, 'milestones' => $terms['milestones']); } else { $legacy[] = $revision; }
        }
        return array($usable, $legacy);
    }
    // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
    // phpcs:disable WordPress.Security.NonceVerification.Recommended -- POST handlers verify the nonce (check_admin_referer or check_ajax_referer) and capability before acting, reading the action name first only to choose that nonce; GET values are display-only routing, cast or sanitized before use; uploads are validated by IDLM_Private_Files::upload(). Builder: Shashi.
    private static function render_simple($lead_id, $title, $customer, $context, $return_url, $back_url, $retired, $can_record, $can_correct, $won = '0') {
        $version = $context['version']; $schedule = $context['schedule']; $balance = $context['balance'];
        $locked = $schedule && IDLM_Design_Handoffs::locks_schedule($lead_id);
        $back = $retired ? 'All leads' : 'All payment leads';
        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped when built: helpers and variables here concatenate only esc_html/esc_attr/esc_url values, nonce fields and fixed markup. Builder: Shashi.
        echo '<div class="idlm-payment-simple"><header class="idlm-header"><div><h1>Payments</h1><p class="idlm-payment-context">' . esc_html($title . ($customer && $customer !== $title ? ' · ' . $customer : '') . ' · Lead #' . $lead_id) . ($balance ? ' ' . self::advance_badge($balance['advance_status']) : '') . '</p></div><div class="idlm-header-actions"><a class="idlm-btn idlm-btn-secondary" href="' . esc_url($back_url) . '">' . esc_html($back) . '</a>';
        if ($schedule && $can_record) { echo '<button type="button" class="idlm-btn idlm-btn-primary" data-design-dialog="idlm-payment-record" aria-haspopup="dialog">Record payment</button>'; }
        echo '</div></header>';
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Display-only notice after a checked redirect.
        $done = isset($_GET['payment_saved']) ? sanitize_key(wp_unslash($_GET['payment_done'] ?? '')) : '';
        if (isset($_GET['payment_saved'])) { echo '<div class="idlm-alert idlm-alert-success" role="status">' . esc_html(self::DONE[$done] ?? 'Saved.') . '</div>'; }
        list($usable, $legacy) = ($can_record || $can_correct) ? self::simple_revisions($lead_id) : array(array(), array());
        $won_units = IDLM_Lead_Payment_Ledger::money($won); $won = (!is_wp_error($won_units) && $won_units > 0) ? IDLM_Lead_Payment_Ledger::decimal($won_units) : '';
        $snapshot = $schedule ? json_decode($schedule['snapshot'], true) : null;
        $won_plan = 'won_amount' === ($snapshot['resolution']['type'] ?? '');
        if (!$schedule) {
            self::render_simple_setup($lead_id, $version, $return_url, $can_record, $can_correct, $usable, $legacy, $won);
        } else {
            $currency = $balance['currency'];
            echo '<section class="idlm-quote-summary" aria-label="Payment summary">';
            foreach (array('basis_total' => 'Total', 'total_received' => 'Received', 'total_outstanding' => 'Outstanding') as $key => $label) { echo '<div><span>' . esc_html($label) . '</span><strong>' . esc_html(self::money($balance[$key], $currency)) . '</strong></div>'; }
            echo '</section>';
            $change = ($can_correct && !$locked) ? ($won_plan ? ($won ? 'idlm-payment-won' : '') : ($usable ? 'idlm-payment-plan' : '')) : '';
            self::render_simple_milestones($context, $locked, $change);
            self::render_handoff_advance($lead_id, $context, $can_record);
            self::render_simple_payments($lead_id, $context, $return_url, $can_correct);
            if ($can_record) {
                $references = array();
                foreach ($context['receipts'] as $receipt) { if ('revoked' !== $receipt['status'] && '' !== $receipt['normalized_reference']) { $references[] = $receipt['method'] . '|' . $receipt['normalized_reference']; } }
                self::dialog_open('idlm-payment-record', 'Record payment', 'It is applied to the oldest unpaid milestone first.', 'record', $lead_id, $version, $return_url, true, ' data-payment-references="' . esc_attr(wp_json_encode($references)) . '"');
                self::simple_receipt_fields('idlm-payment-new', $schedule['currency']);
                self::dialog_close('Record payment');
            }
            if ('idlm-payment-plan' === $change) { self::render_plan_dialog($lead_id, $version, $return_url, $usable, true); }
            if ('idlm-payment-won' === $change && $won) { self::render_won_dialog($lead_id, $version, $return_url, $won, $schedule['currency'], $balance['milestones']); }
        }
        if ($can_correct && $legacy) { self::render_legacy_dialog($lead_id, $version, $return_url, $legacy, (bool) $schedule); }
        echo '</div>';
    }
    // phpcs:enable WordPress.Security.NonceVerification.Recommended
    private static function render_simple_setup($lead_id, $version, $return_url, $can_record, $can_correct, $usable, $legacy, $won = '') {
        $basis = IDLM_Design_Handoffs::basis($lead_id);
        echo '<section class="idlm-card idlm-payment-panel idlm-payment-start"><h2>Set the payment plan</h2>';
        if (!$can_record && !$can_correct) { echo '<p>Someone with payment permission sets the plan from the agreed quotation.</p></section>'; return; }
        if ($basis && $can_record) {
            $currency = $basis['snapshot']['currency'];
            echo '<p>' . esc_html('Use ' . $basis['revision']->revision_name . ' · total ' . self::money($basis['snapshot']['basis_total'], $currency) . '. Advance: ' . $basis['milestone']['name'] . ', ' . self::money($basis['milestone']['amount'], $currency) . '.') . '</p>';
            self::form('schedule', $lead_id, $version, $return_url);
            self::hidden('payment_revision_id', $basis['revision']->id); self::hidden('advance_positions[]', $basis['position']); self::hidden('reason', 'Set from the agreed quotation');
            echo '<button class="idlm-btn idlm-btn-primary">Set payment plan</button></form>';
        } elseif (!$basis && $usable) {
            echo '<p>The agreed quotation could not be found. Choose the quotation the customer agreed to.</p>';
        } elseif (!$basis) {
            // Legacy deals: an old quotation without milestones, or only the Won amount on the lead.
            $currency = strtoupper((string) get_option('idlm_currency_code', 'INR'));
            if ($won) { echo '<p>' . esc_html('Won amount: ' . self::money($won, $currency) . '. Enter how it is paid in parts, such as the advance.') . '</p>'; }
            if ($legacy) { echo '<p>' . esc_html('An old quotation without payment milestones is on this lead.' . ($can_correct ? '' : ' A manager with payment-correction permission can enter its percentages.')) . '</p>'; }
            if (!$won && !$legacy) { echo '<p>This lead has no quotation and no Won amount yet. Add the Won amount on the lead, or create a quotation.</p>'; }
            $actions = '';
            if ($won && $can_record) { $actions .= '<button type="button" class="idlm-btn idlm-btn-primary" data-design-dialog="idlm-payment-won" aria-haspopup="dialog">Set payment plan</button>'; self::render_won_dialog($lead_id, $version, $return_url, $won, $currency); }
            if ($legacy && $can_correct) { $actions .= '<button type="button" class="idlm-btn ' . ($actions ? 'idlm-btn-quiet' : 'idlm-btn-primary') . '" data-design-dialog="idlm-payment-legacy" aria-haspopup="dialog">Use the old quotation</button>'; }
            // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped when built: helpers and variables here concatenate only esc_html/esc_attr/esc_url values, nonce fields and fixed markup. Builder: Shashi.
            if ($actions) { echo '<p class="idlm-payment-links">' . $actions . '</p>'; }
            echo '</section>'; return;
        }
        $links = array();
        if ($usable && (($basis && $can_correct) || (!$basis && $can_record))) {
            $links[] = '<button type="button" class="idlm-btn idlm-btn-quiet" data-design-dialog="idlm-payment-plan" aria-haspopup="dialog">' . ($basis ? 'Choose a different quotation' : 'Choose a quotation') . '</button>';
            self::render_plan_dialog($lead_id, $version, $return_url, $usable, false);
        }
        if ($can_correct && $legacy) { $links[] = '<button type="button" class="idlm-btn idlm-btn-quiet" data-design-dialog="idlm-payment-legacy" aria-haspopup="dialog">Old quotation without milestones</button>'; }
        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped when built: helpers and variables here concatenate only esc_html/esc_attr/esc_url values, nonce fields and fixed markup. Builder: Shashi.
        if ($links) { echo '<p class="idlm-payment-links">' . implode('', $links) . '</p>'; }
        echo '</section>';
    }
    private static function render_simple_milestones($context, $locked, $change = '') {
        $schedule = $context['schedule']; $balance = $context['balance']; $currency = $balance['currency'];
        $snapshot = json_decode($schedule['snapshot'], true);
        $type = $snapshot['resolution']['type'] ?? '';
        $note = $locked ? 'The plan follows the Customer approved price in Design.' : ('won_amount' === $type ? 'Set from the Won amount on the lead.' : ($type ? 'Recovered from ' . $snapshot['resolution']['source_reference'] . '.' : ''));
        echo '<section class="idlm-card idlm-payment-panel"><div class="idlm-panel-heading idlm-payment-panel-heading"><div><h2>Milestones</h2>' . ($note ? '<p>' . esc_html($note) . '</p>' : '') . '</div>';
        if ($change) { echo '<button type="button" class="idlm-btn idlm-btn-secondary" data-design-dialog="' . esc_attr($change) . '" aria-haspopup="dialog">Change payment plan</button>'; }
        echo '</div><div class="idlm-table-wrapper"><table class="idlm-table idlm-payment-timeline"><caption class="screen-reader-text">Expected, received and pending amounts for each milestone</caption><thead><tr><th scope="col">Milestone</th><th scope="col">Expected</th><th scope="col">Received</th><th scope="col">Pending</th></tr></thead><tbody>';
        foreach ($balance['milestones'] as $milestone) {
            echo '<tr><td class="idlm-payment-milestone"><strong>' . esc_html($milestone['name']) . '</strong>' . ($milestone['advance'] ? '<small>Advance</small>' : '') . '</td>';
            foreach (array('amount' => 'Expected', 'received' => 'Received', 'pending' => 'Pending') as $key => $label) { echo '<td data-label="' . esc_attr($label) . '"' . ('pending' === $key && IDLM_Lead_Payment_Ledger::money($milestone['pending']) ? ' class="idlm-payment-pending-amount"' : '') . '>' . esc_html(self::money($milestone[$key], $currency)) . '</td>'; }
            echo '</tr>';
        }
        echo '</tbody></table></div>';
        $extra = array();
        foreach (array('excess_credit' => 'Extra received (credit)', 'unallocated_credit' => 'Not matched to a milestone', 'unscheduled_balance' => 'Not in any milestone') as $key => $label) {
            $units = IDLM_Lead_Payment_Ledger::money($balance[$key]);
            if (!is_wp_error($units) && $units > 0) { $extra[] = '<div><dt>' . esc_html($label) . '</dt><dd>' . esc_html(self::money($balance[$key], $currency)) . '</dd></div>'; }
        }
        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped when built: helpers and variables here concatenate only esc_html/esc_attr/esc_url values, nonce fields and fixed markup. Builder: Shashi.
        if ($extra) { echo '<dl class="idlm-payment-extra">' . implode('', $extra) . '</dl>'; }
        echo '</section>';
    }
    // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom IDLM tables named from $wpdb->prefix; values are bound with prepare(), cast to int, or come from fixed clause builders and generated placeholder lists; request-scoped reads and transactional writes are deliberately uncached. Builder: Shashi.
    private static function render_simple_payments($lead_id, $context, $return_url, $can_correct) {
        global $wpdb;
        $versions = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$wpdb->prefix}idlm_lead_payment_versions WHERE lead_id=%d ORDER BY id DESC LIMIT 100", $lead_id));
        $groups = array(); foreach ((array) $versions as $row) { $groups[$row->confirmation_id][] = $row; }
        $current = array(); foreach ($context['receipts'] as $live) { $current[(int) $live['id']] = true; }
        echo '<section class="idlm-card idlm-payment-panel"><div class="idlm-panel-heading idlm-payment-panel-heading"><div><h2>Payments</h2></div></div>';
        if (!$groups) { echo '<div class="idlm-state is-empty"><strong>No payments recorded</strong><span>Record the first payment to start tracking what is received.</span></div></section>'; return; }
        echo '<div class="idlm-table-wrapper"><table class="idlm-table idlm-payment-list-table"><caption class="screen-reader-text">Payments received</caption><thead><tr><th scope="col">Date</th><th scope="col">Amount</th><th scope="col">Method</th><th scope="col">Reference</th><th scope="col">Status</th><th scope="col"><span class="screen-reader-text">Actions</span></th></tr></thead><tbody>';
        $dialogs = array();
        foreach ($groups as $confirmation_id => $group) {
            $receipt = $group[0]; $revoked = 'revoked' === $receipt->status; $id = (int) $confirmation_id;
            $actions = array();
            if (count($group) > 1) { $actions[] = '<button type="button" class="idlm-btn idlm-btn-quiet" data-design-dialog="' . esc_attr('idlm-payment-changes-' . $id) . '" aria-haspopup="dialog">' . esc_html('Changes (' . (count($group) - 1) . ')') . '</button>'; $dialogs[] = array('changes', $group); }
            if ($can_correct && !$revoked && isset($current[(int) $receipt->id])) {
                $actions[] = '<button type="button" class="idlm-btn idlm-btn-quiet" data-design-dialog="' . esc_attr('idlm-payment-correct-' . $id) . '" aria-haspopup="dialog">Correct</button>';
                $actions[] = '<button type="button" class="idlm-btn idlm-btn-quiet" data-design-dialog="' . esc_attr('idlm-payment-revoke-' . $id) . '" aria-haspopup="dialog">Revoke</button>';
                $dialogs[] = array('edit', $group);
            }
            $proof = (int) $receipt->attachment_id ? ' <a href="' . esc_url(IDLM_Private_Files::url((int) $receipt->attachment_id)) . '" target="_blank" rel="noopener">Screenshot</a>' : '';
            // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped when built: helpers and variables here concatenate only esc_html/esc_attr/esc_url values, nonce fields and fixed markup. Builder: Shashi.
            echo '<tr' . ($revoked ? ' class="is-revoked"' : '') . '><td data-label="Date">' . esc_html(self::date($receipt->received_date)) . '</td><td data-label="Amount"><strong>' . esc_html(self::money($receipt->amount, $receipt->currency)) . '</strong></td><td data-label="Method">' . esc_html($receipt->method) . '</td><td data-label="Reference">' . esc_html($receipt->reference) . $proof . ('' !== $receipt->note ? '<small>' . esc_html($receipt->note) . '</small>' : '') . '</td><td data-label="Status"><span class="idlm-badge ' . ($revoked ? 'is-neutral' : 'is-success') . '">' . ($revoked ? 'Revoked' : 'Recorded') . '</span></td><td class="idlm-payment-row-actions">' . implode('', $actions) . '</td></tr>';
        }
        echo '</tbody></table></div></section>';
        foreach ($dialogs as $dialog) {
            $group = $dialog[1]; $receipt = $group[0]; $id = (int) $receipt->confirmation_id;
            if ('changes' === $dialog[0]) {
                self::dialog_open('idlm-payment-changes-' . $id, 'Changes to this payment', 'Newest first. Earlier versions are kept for the record.', null, $lead_id, 0, '');
                echo '<ol class="idlm-payment-changes">';
                foreach ($group as $row) { $actor = get_userdata($row->actor_id); echo '<li><strong>' . esc_html('Version ' . $row->version_no . ' · ' . self::money($row->amount, $row->currency) . ' · ' . self::date($row->received_date) . ' · ' . $row->method . ('revoked' === $row->status ? ' · Revoked' : '')) . '</strong><small>' . esc_html(($actor ? $actor->display_name : 'Team member') . ', ' . self::date($row->recorded_at) . ('' !== $row->reason ? ': ' . $row->reason : '')) . '</small></li>'; }
                echo '</ol>'; self::dialog_close(null);
                continue;
            }
            self::dialog_open('idlm-payment-correct-' . $id, 'Correct payment', 'Saves a new version. The earlier one stays in the record.', 'correct', $lead_id, $context['version'], $return_url, true);
            self::hidden('confirmation_id', $id); self::simple_receipt_fields('idlm-payment-fix-' . $id, $context['schedule']['currency'], $receipt);
            // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped when built: helpers and variables here concatenate only esc_html/esc_attr/esc_url values, nonce fields and fixed markup. Builder: Shashi.
            echo self::reason_group('idlm-payment-fix-reason-' . $id, 'Why is it being corrected?');
            self::dialog_close('Save correction');
            self::dialog_open('idlm-payment-revoke-' . $id, 'Revoke payment', self::money($receipt->amount, $receipt->currency) . ' on ' . self::date($receipt->received_date) . ' will stop counting. Its record is kept.', 'revoke', $lead_id, $context['version'], $return_url);
            // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped when built: helpers and variables here concatenate only esc_html/esc_attr/esc_url values, nonce fields and fixed markup. Builder: Shashi.
            self::hidden('confirmation_id', $id); echo self::reason_group('idlm-payment-revoke-reason-' . $id, 'Why is it being revoked?');
            self::dialog_close('Revoke payment', true);
        }
    }
    // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
    /** Pick a quotation, then its advance milestones. Fieldsets for other quotations stay disabled, so only one set posts. */
    private static function render_plan_dialog($lead_id, $version, $return_url, $usable, $amendment) {
        if (!$usable) { return; }
        self::dialog_open('idlm-payment-plan', $amendment ? 'Change payment plan' : 'Choose a quotation', $amendment ? 'Saves a new plan version. Payments already recorded are kept and re-applied.' : 'The plan uses the saved milestones of the quotation you choose.', 'schedule', $lead_id, $version, $return_url, false, ' data-payment-plan');
        $options = '';
        foreach ($usable as $i => $item) { $options .= '<option value="' . esc_attr($item['revision']->id) . '"' . (0 === $i ? ' selected' : '') . '>' . esc_html($item['revision']->revision_name . ' · ' . self::money($item['revision']->grand_total, $item['revision']->currency_code)) . '</option>'; }
        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped when built: helpers and variables here concatenate only esc_html/esc_attr/esc_url values, nonce fields and fixed markup. Builder: Shashi.
        echo self::group('idlm-payment-plan-revision', 'Quotation', '<select class="idlm-input" id="idlm-payment-plan-revision" name="payment_revision_id" required>' . $options . '</select>');
        foreach ($usable as $i => $item) {
            $first = IDLM_Design_Handoffs::first_payable_position($item['milestones']);
            echo '<fieldset class="idlm-payment-advance" data-revision="' . esc_attr($item['revision']->id) . '"' . (0 === $i ? '' : ' disabled hidden') . '><legend>Which milestones are the advance?</legend>';
            foreach ($item['milestones'] as $position => $milestone) { echo '<label class="idlm-payment-checkbox"><input type="checkbox" name="advance_positions[]" value="' . esc_attr($position) . '"' . checked($position, $first, false) . '> ' . esc_html($milestone['name'] . ' · ' . self::money($milestone['amount'], $item['revision']->currency_code)) . '</label>'; }
            echo '</fieldset>';
        }
        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped when built: helpers and variables here concatenate only esc_html/esc_attr/esc_url values, nonce fields and fixed markup. Builder: Shashi.
        if ($amendment) { echo self::reason_group('idlm-payment-plan-reason', 'Why is the plan changing?'); }
        else { self::hidden('reason', 'Chosen quotation for the payment plan'); }
        self::dialog_close($amendment ? 'Save new plan' : 'Set payment plan');
    }
    /** Old quotations saved before milestones: enter the agreed percentages once, with the source. */
    private static function render_legacy_dialog($lead_id, $version, $return_url, $legacy, $has_schedule) {
        if ($has_schedule) { return; }
        self::dialog_open('idlm-payment-legacy', 'Old quotation without milestones', 'Enter the percentages from the agreed document. Amounts use the saved quotation total.', 'resolve_legacy', $lead_id, $version, $return_url, false, ' data-payment-legacy');
        $options = '';
        foreach ($legacy as $revision) { $options .= '<option value="' . esc_attr($revision->id) . '">' . esc_html($revision->revision_name . ' · ' . self::money($revision->grand_total, $revision->currency_code)) . '</option>'; }
        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped when built: helpers and variables here concatenate only esc_html/esc_attr/esc_url values, nonce fields and fixed markup. Builder: Shashi.
        echo self::group('idlm-payment-legacy-revision', 'Quotation', '<select class="idlm-input" id="idlm-payment-legacy-revision" name="payment_revision_id" required>' . $options . '</select>');
        echo '<fieldset><legend>Milestones</legend>';
        for ($i = 0; $i < 8; $i++) {
            echo '<div class="idlm-payment-legacy-row"' . ($i > 2 ? ' hidden' : '') . ' data-payment-legacy-row><input class="idlm-input" name="legacy_milestones[' . esc_attr($i) . '][name]" aria-label="' . esc_attr('Milestone ' . ($i + 1) . ' name') . '" placeholder="Name"' . ($i > 2 ? ' disabled' : '') . '><input class="idlm-input" name="legacy_milestones[' . esc_attr($i) . '][percentage]" inputmode="decimal" aria-label="' . esc_attr('Milestone ' . ($i + 1) . ' percentage') . '" placeholder="%"' . ($i > 2 ? ' disabled' : '') . '><label class="idlm-payment-checkbox"><input type="checkbox" name="legacy_milestones[' . esc_attr($i) . '][advance]" value="1"' . (0 === $i ? ' checked' : '') . ($i > 2 ? ' disabled' : '') . '> Advance</label></div>';
        }
        echo '<button type="button" class="idlm-btn idlm-btn-quiet" data-payment-add-row>Add milestone</button></fieldset>';
        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped when built: helpers and variables here concatenate only esc_html/esc_attr/esc_url values, nonce fields and fixed markup. Builder: Shashi.
        echo self::group('idlm-payment-legacy-source', 'Where are these terms from?', '<input class="idlm-input" id="idlm-payment-legacy-source" name="source_reference" maxlength="1000" required placeholder="For example, signed quotation emailed on 3 March">');
        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped when built: helpers and variables here concatenate only esc_html/esc_attr/esc_url values, nonce fields and fixed markup. Builder: Shashi.
        echo self::reason_group('idlm-payment-legacy-reason', 'Reason');
        echo '<label class="idlm-payment-checkbox"><input type="checkbox" name="legacy_confirmed" value="1" required> These are the agreed percentages and advance.</label>';
        self::dialog_close('Save payment plan');
    }
    /** Percentages of the lead's Won amount. Same rows as old-quotation recovery; amounts use quotation rounding. */
    private static function render_won_dialog($lead_id, $version, $return_url, $won, $currency, $current = null) {
        $amendment = null !== $current;
        self::dialog_open('idlm-payment-won', $amendment ? 'Change payment plan' : 'Set payment plan', 'Percentages of the Won amount, ' . self::money($won, $currency) . '. Tick the parts that count as the advance.', 'won_plan', $lead_id, $version, $return_url, false, ' data-payment-legacy');
        $rows = array();
        foreach ((array) $current as $milestone) { $rows[] = array('name' => $milestone['name'], 'percentage' => rtrim(rtrim((string) $milestone['percentage'], '0'), '.'), 'advance' => !empty($milestone['advance'])); }
        if (!$rows) { $rows[] = array('name' => 'Full amount', 'percentage' => '100', 'advance' => true); }
        $shown = max(3, count($rows));
        echo '<fieldset><legend>Milestones</legend>';
        for ($i = 0; $i < max(8, $shown); $i++) {
            $row = $rows[$i] ?? array('name' => '', 'percentage' => '', 'advance' => false); $off = $i >= $shown ? ' disabled' : '';
            // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped when built: helpers and variables here concatenate only esc_html/esc_attr/esc_url values, nonce fields and fixed markup. Builder: Shashi.
            echo '<div class="idlm-payment-legacy-row"' . ($off ? ' hidden' : '') . ' data-payment-legacy-row><input class="idlm-input" name="legacy_milestones[' . esc_attr($i) . '][name]" aria-label="' . esc_attr('Milestone ' . ($i + 1) . ' name') . '" placeholder="Name" value="' . esc_attr($row['name']) . '"' . $off . '><input class="idlm-input" name="legacy_milestones[' . esc_attr($i) . '][percentage]" inputmode="decimal" aria-label="' . esc_attr('Milestone ' . ($i + 1) . ' percentage') . '" placeholder="%" value="' . esc_attr($row['percentage']) . '"' . $off . '><label class="idlm-payment-checkbox"><input type="checkbox" name="legacy_milestones[' . esc_attr($i) . '][advance]" value="1"' . ($row['advance'] ? ' checked' : '') . $off . '> Advance</label></div>';
        }
        echo '<button type="button" class="idlm-btn idlm-btn-quiet" data-payment-add-row>Add milestone</button></fieldset><small>Percentages can add up to less than 100%. The rest shows as not in any milestone.</small>';
        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped when built: helpers and variables here concatenate only esc_html/esc_attr/esc_url values, nonce fields and fixed markup. Builder: Shashi.
        if ($amendment) { echo self::reason_group('idlm-payment-won-reason', 'Why is the plan changing?'); }
        else { self::hidden('reason', 'Set from the Won amount'); }
        self::dialog_close($amendment ? 'Save new plan' : 'Set payment plan');
    }
    /** Advance taken with Send to design before a plan existed: offer to record it once, prefilled. */
    private static function render_handoff_advance($lead_id, $context, $can_record) {
        if (!$can_record) { return; }
        foreach ($context['receipts'] as $receipt) { if ('revoked' !== $receipt['status']) { return; } }
        $row = IDLM_Design_Handoffs::for_lead($lead_id);
        if (!$row || (int) $row->receipt_confirmation_id || (float) $row->advance_amount <= 0) { return; }
        $currency = $context['schedule']['currency'];
        echo '<section class="idlm-card idlm-payment-panel idlm-payment-start"><h2>Advance from the design handoff</h2><p>' . esc_html(self::money($row->advance_amount, $row->advance_currency ?: $currency) . ' received on ' . self::date((string) $row->advance_date) . ' was entered with Send to design, before this plan existed. It is not counted yet.') . '</p><button type="button" class="idlm-btn idlm-btn-primary" data-design-dialog="idlm-payment-handoff-advance" aria-haspopup="dialog">Record this advance</button></section>';
        self::dialog_open('idlm-payment-handoff-advance', 'Record the advance', 'Filled in from the design handoff. Check it and save.', 'record', $lead_id, $context['version'], add_query_arg('payment_lead_id', $lead_id, add_query_arg('idlm_view', 'payments', IDLM_Admin_Shortcodes::workspace_url())), true, ' data-payment-references="[]"');
        self::simple_receipt_fields('idlm-payment-handoff', $currency, (object) array('amount' => $row->advance_amount, 'received_date' => (string) $row->advance_date, 'method' => $row->advance_method, 'reference' => $row->advance_reference, 'attachment_id' => (int) $row->advance_attachment_id, 'note' => 'Advance entered with Send to design', 'duplicate_review_reason' => ''));
        self::dialog_close('Record advance');
    }
}
