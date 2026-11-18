<?php
/** Authenticated sales payment workspace and operation-specific forms. Builder: Shashi. */
if (!defined('ABSPATH')) { exit; }
final class IDLM_Lead_Payment_Controller {
    /** Every query key the Payments view reads. Sidebar navigation drops them all, so Payments opens the list. */
    const QUERY_KEYS = array('payment_lead_id', 'payment_revision_id', 'payment_saved', 'payment_done', 'payment_q', 'payment_balance', 'payment_sort', 'payment_page', 'payment_error');
    /** Typed values a refused save hands back to its dialog (STD-CRM-10). Files cannot be refilled. */
    const RETRY_FIELDS = array('amount', 'received_date', 'method', 'reference', 'note', 'duplicate_review_reason', 'reason', 'source_reference');
    private static $retry = null;
    private static $open_dialog = '';
    private static $retry_shown = false;
    public static function init() {
        foreach (array('schedule', 'resolve_legacy', 'won_plan', 'record', 'correct', 'revoke') as $operation) {
            add_action('admin_post_idlm_payment_' . $operation, array(__CLASS__, 'handle'));
        }
        add_action('admin_menu', array(__CLASS__, 'menu'));
    }
    /** wp-admin entry for administrators; IDLM_Admin::redirect_legacy_admin_page() sends it to the workspace page. */
    public static function menu() {
        add_submenu_page('idlm_utilities', 'Payments', 'Payments', 'manage_options', 'idlm-payments', array(__CLASS__, 'render'));
    }
    /** Direct shortcode renders need the payment styles and dialogs; the workspace already loads the rest. */
    private static function enqueue_payment_assets() {
        wp_enqueue_style('idlm-quotations', IDLM_URL . 'assets/css/quotations.css', array('idlm-admin-styles'), IDLM_VERSION);
        wp_enqueue_style('idlm-payments', IDLM_URL . 'assets/css/payments.css', array('idlm-quotations'), IDLM_Admin_Assets::version('css/payments.css'));
        wp_enqueue_style('idlm-crm-design', IDLM_URL . 'assets/css/crm-design.css', array('idlm-payments'), IDLM_Admin_Assets::version('css/crm-design.css'));
        wp_enqueue_script('idlm-payments', IDLM_URL . 'assets/js/payments.js', array(), IDLM_Admin_Assets::version('js/payments.js'), true);
        add_filter('body_class', array('IDLM_Admin_Assets', 'add_design_body_class'));
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
     * The workspace Payments tab and its lead list show to administrators and managers (people who can
     * assign leads); representatives open each lead's payment page from the lead drawer or the Design project.
     * Filter `idlm_show_payments_tab` overrides it either way. The list itself still needs visible().
     */
    public static function tab_shown() {
        return (bool) apply_filters('idlm_show_payments_tab', current_user_can('manage_options') || IDLM_Lead_Access_Policy::can_assign());
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
        if (!in_array($operation, array('schedule', 'resolve_legacy', 'won_plan', 'record', 'correct', 'revoke'), true) || !self::visible()) { wp_die('Payment access denied.', '', array('response' => 403)); }
        check_admin_referer('idlm_payment_' . $operation);
        $lead_id = self::id($_POST, 'payment_lead_id');
        $version = self::id($_POST, 'payment_version');
        if (!isset($_POST['payment_version']) || !is_string($_POST['payment_version']) || !ctype_digit($_POST['payment_version'])) { wp_die('Invalid payment version.', '', array('response' => 400)); }
        $key = self::text($_POST, 'request_key');
        $reason = self::text($_POST, 'reason');
        $result = IDLM_Recovery_Guard::run(static function() use ($operation, $lead_id, $version, $key, $reason) {
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
            // The screenshot comes with the receipt. Check the typed fields and the grant first, so a bad form stores no file.
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
        $url = wp_validate_redirect(self::text($_POST, 'return_url'), admin_url('admin.php?page=idlm-payments'));
        // Access refusals stay a hard stop: there is nothing to retype.
        if (is_wp_error($result) && in_array($result->get_error_code(), array('idlm_payment_denied', 'idlm_file_denied', 'idlm_payment_busy'), true)) { wp_die(esc_html($result->get_error_message()), 'Payment not saved', array('response' => 422, 'back_link' => true)); }
        if (is_wp_error($result)) {
            // Back to the payment page with the reason and what was typed, not a WordPress error page.
            $file_chosen = isset($_FILES['payment_screenshot']) && UPLOAD_ERR_NO_FILE !== (int) ($_FILES['payment_screenshot']['error'] ?? UPLOAD_ERR_NO_FILE);
            $token = self::save_retry($result->get_error_message() . ($file_chosen ? ' Choose the screenshot again before saving.' : ''), self::text($_POST, 'payment_dialog'), $_POST);
            wp_safe_redirect(add_query_arg(array('payment_lead_id' => $lead_id, 'payment_error' => $token), $url)); exit;
        }
        $url = add_query_arg(array('payment_lead_id' => $lead_id, 'payment_saved' => '1', 'payment_done' => $operation), $url);
        wp_safe_redirect($url); exit;
    }
    // phpcs:enable WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.MissingUnslash,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
    /** Keep a refused save for ten minutes, for this person only; the page reads it once. Returns the token for the address. */
    public static function save_retry($message, $dialog, $input) {
        $values = array();
        foreach (self::RETRY_FIELDS as $field) { if (isset($input[$field]) && is_string($input[$field])) { $values[$field] = sanitize_textarea_field(wp_unslash($input[$field])); } }
        $token = strtolower(wp_generate_password(20, false, false));
        set_transient('idlm_payment_retry_' . get_current_user_id() . '_' . $token, array('message' => sanitize_text_field((string) $message), 'dialog' => preg_match('/^idlm-payment-[a-z0-9-]{1,60}$/D', (string) $dialog) ? (string) $dialog : '', 'values' => $values), 10 * MINUTE_IN_SECONDS);
        return $token;
    }
    /** The refused save for this address and person, or null. Read once. */
    public static function take_retry($token) {
        $token = is_string($token) ? strtolower($token) : '';
        if (!preg_match('/^[a-z0-9]{20}$/D', $token)) { return null; }
        $key = 'idlm_payment_retry_' . get_current_user_id() . '_' . $token;
        $retry = get_transient($key); delete_transient($key);
        return is_array($retry) && isset($retry['message'], $retry['dialog'], $retry['values']) ? $retry : null;
    }
    /** A value typed before a refused save in the dialog being printed, else the default. */
    private static function retried($field, $default = '') {
        return self::$retry && self::$open_dialog && self::$retry['dialog'] === self::$open_dialog && isset(self::$retry['values'][$field]) ? self::$retry['values'][$field] : $default;
    }
    private static function form($operation, $lead_id, $version, $return_url) {
        echo '<form class="idlm-payment-form" method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
        self::hidden('action', 'idlm_payment_' . $operation); self::hidden('payment_lead_id', $lead_id);
        self::hidden('payment_version', $version); self::hidden('request_key', wp_generate_uuid4()); self::hidden('return_url', $return_url);
        wp_nonce_field('idlm_payment_' . $operation);
    }
    private static function hidden($name, $value) { echo '<input type="hidden" name="' . esc_attr($name) . '" value="' . esc_attr($value) . '">'; }
    private static function money($value, $currency) {
        $parts = explode('.', (string) $value, 2);
        $integer = preg_replace('/\B(?=(\d{3})+(?!\d))/', ',', $parts[0]);
        $symbol = array('INR' => '₹', 'USD' => '$', 'EUR' => '€', 'GBP' => '£');
        return ($symbol[$currency] ?? $currency . ' ') . $integer . '.' . str_pad($parts[1] ?? '', 2, '0');
    }
    private static function date($date) { return mysql2date('d M Y', $date . (strlen($date) === 10 ? ' 00:00:00' : '')); }
    // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom IDLM tables named from $wpdb->prefix; values are bound with prepare(), cast to int, or come from fixed clause builders and generated placeholder lists; request-scoped reads and transactional writes are deliberately uncached. Builder: Shashi.
    // phpcs:disable WordPress.Security.NonceVerification.Recommended -- POST handlers verify the nonce (check_admin_referer or check_ajax_referer) and capability before acting, reading the action name first only to choose that nonce; GET values are display-only routing, cast or sanitized before use; uploads are validated by IDLM_Private_Files::upload(). Builder: Shashi.
    public static function render() {
        global $wpdb;
        if (!self::visible()) { echo '<p>Payment access denied.</p>'; return; }
        self::enqueue_payment_assets();
        $lead_id = self::id($_GET, 'payment_lead_id');
        $base = add_query_arg('idlm_view', 'payments', IDLM_Admin_Shortcodes::workspace_url());
        echo '<div class="wrap idlm-wrap idlm-quotes-wrap idlm-payments">';
        $leads_url = add_query_arg('idlm_view', 'leads', IDLM_Admin_Shortcodes::workspace_url());
        $retired = !self::tab_shown();
        if (!$lead_id && $retired) {
            echo '<header class="idlm-header"><div><h1>Payments</h1></div></header><div class="idlm-card"><div class="idlm-state is-empty"><strong>Payments open from each lead</strong><span>Open a Won lead and choose Payment details, or use the project in Design team.</span><a class="idlm-btn idlm-btn-secondary" href="' . esc_url($leads_url) . '">Go to leads</a></div></div></div>';
            return;
        }
        if (!$lead_id) { self::render_leads($base); echo '</div>'; return; }
        $context = IDLM_Lead_Payments::read($lead_id);
        if (is_wp_error($context)) { echo '<div class="idlm-payment-empty idlm-state is-error"><h1>Payments</h1><p>' . esc_html($context->get_error_message()) . '</p></div></div>'; return; }
        $lead = $wpdb->get_row($wpdb->prepare("SELECT first_name,last_name,project_name,deal_value FROM {$wpdb->prefix}idlm_leads WHERE id=%d", $lead_id));
        $return_url = add_query_arg('payment_lead_id', $lead_id, $base);
        $can_record = IDLM_Lead_Payments::permitted($lead_id); $can_correct = IDLM_Lead_Payments::permitted($lead_id, true);
        $customer = $lead ? trim($lead->first_name . ' ' . $lead->last_name) : '';
        self::$retry = self::take_retry(self::text($_GET, 'payment_error'));
        // One summary, one milestone table, one payments list; every form in a dialog.
        self::render_simple($lead_id, ($lead->project_name ?? '') ?: $customer, $customer, $context, $return_url, $retired ? $leads_url : $base, $retired, $can_record, $can_correct, (string) ($lead->deal_value ?? '0'));
        // A refused save whose dialog is not on the page still says why.
        if (self::$retry && !self::$retry_shown) { echo '<div class="idlm-alert idlm-alert-error" role="alert">' . esc_html(self::$retry['message']) . '</div>'; }
        echo '</div>';
    }
    // phpcs:enable WordPress.Security.NonceVerification.Recommended
    // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
    const LIST_SORTS = array('newest' => 'Newest first', 'outstanding' => 'Outstanding first');
    const LIST_BALANCES = array('' => 'All balances', 'outstanding' => 'Outstanding', 'paid' => 'Paid in full', 'none' => 'No schedule');
    const LIST_PAGE_SIZE = 50;
    /** Payments list filters from GET: search text, balance filter, sort and page. Unknown values fall back to the defaults. */
    public static function list_filters($input) {
        $q = trim(sanitize_text_field(self::text($input, 'payment_q')));
        $q = function_exists('mb_substr') ? mb_substr($q, 0, 100) : substr($q, 0, 100);
        $balance = sanitize_key(self::text($input, 'payment_balance'));
        $sort = sanitize_key(self::text($input, 'payment_sort'));
        return array('q' => $q, 'balance' => isset(self::LIST_BALANCES[$balance]) ? $balance : '',
            'sort' => isset(self::LIST_SORTS[$sort]) ? $sort : 'newest', 'page' => max(1, min(1000, self::id($input, 'payment_page'))));
    }
    /** Query arguments that keep the current filters on paging links; defaults are left out. */
    private static function list_args($filters, $page) {
        return array_filter(array('payment_q' => $filters['q'], 'payment_balance' => $filters['balance'], 'payment_sort' => 'newest' === $filters['sort'] ? '' : $filters['sort'], 'payment_page' => $page > 1 ? (string) $page : ''), 'strlen');
    }
    // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare,PluginCheck.Security.DirectDB.UnescapedDBParameter -- Custom IDLM tables named from $wpdb->prefix; values are bound with prepare(), cast to int, or come from fixed clause builders and generated placeholder lists; request-scoped reads and transactional writes are deliberately uncached. Builder: Shashi.
    /**
     * One page of Won leads in the caller's payment scope, with each lead's balance.
     * Two queries whatever the page size: the scoped lead page, then the latest ledger snapshot
     * for those leads (the same snapshot the lead's payment page and drawer show).
     */
    public static function list_page($filters) {
        global $wpdb;
        $empty = array('rows' => array(), 'more' => false, 'balances' => array());
        if (!self::visible()) { return $empty; }
        $p = $wpdb->prefix;
        $where = array("l.stage='Won'", 'l.is_duplicate=0');
        if (!IDLM_Lead_Access_Policy::can_assign()) { $where[] = $wpdb->prepare('l.assigned_rep_id=%d', get_current_user_id()); }
        // Never list (or price) a lead its payment page would refuse: same scope as can_open()'s can_access($id, true).
        $params = array(); $scope = IDLM_Lead_Access_Policy::scope_sql('l', $params, true);
        if ('' !== $scope) { $where[] = $params ? $wpdb->prepare(substr($scope, 5), $params) : substr($scope, 5); }
        if ('' !== $filters['q']) {
            $like = '%' . $wpdb->esc_like($filters['q']) . '%';
            $match = $wpdb->prepare("CONCAT_WS(' ', l.first_name, l.last_name) LIKE %s OR l.email LIKE %s OR l.phone LIKE %s OR l.project_name LIKE %s OR EXISTS (SELECT 1 FROM {$p}idlm_quotes q WHERE q.lead_id = l.id AND q.quote_number LIKE %s)", $like, $like, $like, $like, $like);
            $digits = preg_replace('/\D+/', '', $filters['q']);
            if (strlen($digits) >= 4 && $digits !== $filters['q']) { $match .= $wpdb->prepare(' OR l.phone LIKE %s', '%' . $wpdb->esc_like($digits) . '%'); }
            $id = ltrim($filters['q'], '#');
            if ('' !== $id && ctype_digit($id) && strlen($id) < 19) { $match .= $wpdb->prepare(' OR l.id = %d', (int) $id); }
            $where[] = '(' . $match . ')';
        }
        // Balance from the ledger's own inputs: latest schedule total less current, unrevoked receipts.
        $join = ''; $order = 'l.id DESC';
        if ('outstanding' === $filters['sort'] || '' !== $filters['balance']) {
            $join = " LEFT JOIN (SELECT s.lead_id, s.basis_total FROM {$p}idlm_lead_payment_schedules s INNER JOIN (SELECT lead_id, MAX(id) AS id FROM {$p}idlm_lead_payment_schedules GROUP BY lead_id) ls ON ls.id = s.id) sch ON sch.lead_id = l.id"
                . " LEFT JOIN (SELECT c.lead_id, SUM(v.amount) AS received FROM {$p}idlm_lead_payment_confirmations c INNER JOIN {$p}idlm_lead_payment_versions v ON v.id = c.current_version_id AND v.confirmation_id = c.id AND v.lead_id = c.lead_id WHERE v.status <> 'revoked' GROUP BY c.lead_id) rec ON rec.lead_id = l.id";
            $owed = 'GREATEST(0, sch.basis_total - COALESCE(rec.received, 0))';
            $clauses = array('outstanding' => "sch.lead_id IS NOT NULL AND $owed > 0", 'paid' => "sch.lead_id IS NOT NULL AND $owed = 0", 'none' => 'sch.lead_id IS NULL');
            if ('' !== $filters['balance']) { $where[] = $clauses[$filters['balance']]; }
            if ('outstanding' === $filters['sort']) { $order = "(sch.lead_id IS NULL), $owed DESC, l.id DESC"; }
        }
        $size = self::LIST_PAGE_SIZE;
        $rows = (array) $wpdb->get_results($wpdb->prepare("SELECT l.id,l.first_name,l.last_name,l.project_name FROM {$p}idlm_leads l$join WHERE " . implode(' AND ', $where) . " ORDER BY $order LIMIT %d OFFSET %d", $size + 1, ($filters['page'] - 1) * $size));
        $more = count($rows) > $size; $rows = array_slice($rows, 0, $size);
        return array('rows' => $rows, 'more' => $more, 'balances' => self::page_balances(array_map(static function($row) { return (int) $row->id; }, $rows)));
    }
    /** Latest allocation snapshot per lead, one query for the whole page. Callers pass only IDs from the scoped list. */
    private static function page_balances($lead_ids) {
        global $wpdb;
        $lead_ids = array_values(array_unique(array_filter(array_map('intval', $lead_ids))));
        if (!$lead_ids) { return array(); }
        $table = $wpdb->prefix . 'idlm_lead_payment_allocation_sets';
        $in = implode(',', array_fill(0, count($lead_ids), '%d'));
        $sets = (array) $wpdb->get_results($wpdb->prepare("SELECT a.lead_id, a.snapshot FROM $table a INNER JOIN (SELECT lead_id, MAX(id) AS id FROM $table WHERE lead_id IN ($in) GROUP BY lead_id) latest ON latest.id = a.id", $lead_ids));
        $balances = array();
        foreach ($sets as $set) {
            $set = (object) $set;
            $balance = json_decode((string) $set->snapshot, true);
            if (is_array($balance)) { $balances[(int) $set->lead_id] = $balance; }
        }
        return $balances;
    }
    // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare,PluginCheck.Security.DirectDB.UnescapedDBParameter
    /** List label for a balance: written status first (STD-CRM-4), amount in the payment money format. */
    public static function balance_cell($balance) {
        if (!is_array($balance) || !isset($balance['total_outstanding'], $balance['currency'])) { return '<span class="idlm-badge is-neutral">No schedule</span>'; }
        $owed = IDLM_Lead_Payment_Ledger::money($balance['total_outstanding']);
        if (is_wp_error($owed) || !$owed) { return '<span class="idlm-badge is-success">Paid in full</span>'; }
        return '<span class="idlm-badge is-warning">Outstanding</span> <span class="idlm-payment-balance-amount">' . esc_html(self::money($balance['total_outstanding'], $balance['currency'])) . '</span><small class="idlm-payment-balance-basis">' . esc_html('of ' . self::money($balance['basis_total'], $balance['currency'])) . '</small>';
    }
    /** GET form for the list; keeps the workspace's own query arguments (page ID, view) as hidden fields. */
    private static function list_toolbar($base, $filters) {
        $parts = wp_parse_url($base); $query = array();
        if (!empty($parts['query'])) { wp_parse_str($parts['query'], $query); }
        $action = strtok($base, '?');
        echo '<form class="idlm-payment-toolbar" method="get" action="' . esc_url($action) . '" role="search" aria-label="Find payment leads">';
        foreach ($query as $key => $value) { if (is_string($value)) { self::hidden($key, $value); } }
        echo '<div class="idlm-field idlm-payment-search-field"><label for="idlm-payment-q">Search</label><input class="idlm-input" id="idlm-payment-q" type="search" name="payment_q" value="' . esc_attr($filters['q']) . '" placeholder="Name, phone, email or quote number" maxlength="100"></div>';
        echo '<div class="idlm-field"><label for="idlm-payment-balance">Balance</label><select class="idlm-select" id="idlm-payment-balance" name="payment_balance">';
        foreach (self::LIST_BALANCES as $value => $label) { echo '<option value="' . esc_attr($value) . '"' . selected($filters['balance'], $value, false) . '>' . esc_html($label) . '</option>'; }
        echo '</select></div><div class="idlm-field"><label for="idlm-payment-sort">Sort</label><select class="idlm-select" id="idlm-payment-sort" name="payment_sort">';
        foreach (self::LIST_SORTS as $value => $label) { echo '<option value="' . esc_attr($value) . '"' . selected($filters['sort'], $value, false) . '>' . esc_html($label) . '</option>'; }
        echo '</select></div><div class="idlm-payment-toolbar-actions"><button type="submit" class="idlm-btn idlm-btn-secondary idlm-btn-sm">Apply</button>';
        if ('' !== $filters['q'] || '' !== $filters['balance'] || 'newest' !== $filters['sort']) { echo '<a class="idlm-btn idlm-btn-quiet idlm-btn-sm" href="' . esc_url($base) . '">Clear</a>'; }
        echo '</div></form>';
    }
    // phpcs:disable WordPress.Security.NonceVerification.Recommended -- POST handlers verify the nonce (check_admin_referer or check_ajax_referer) and capability before acting, reading the action name first only to choose that nonce; GET values are display-only routing, cast or sanitized before use; uploads are validated by IDLM_Private_Files::upload(). Builder: Shashi.
    private static function render_leads($base) {
        $filters = self::list_filters($_GET);
        $page = self::list_page($filters);
        $filtered = '' !== $filters['q'] || '' !== $filters['balance'];
        echo '<header class="idlm-header"><div><h1>Payments</h1></div></header>';
        self::list_toolbar($base, $filters);
        echo '<div class="idlm-card idlm-payment-list"><div class="idlm-table-wrapper"><table class="idlm-table idlm-payment-leads"><caption class="screen-reader-text">Won leads eligible for payment recording, including existing leads, with the balance each still owes.</caption><thead><tr><th scope="col">Lead</th><th scope="col">Customer</th><th scope="col">Project</th><th scope="col">Balance</th><th scope="col"><span class="screen-reader-text">Action</span></th></tr></thead><tbody>';
        if (!$page['rows']) {
            echo $filtered
                ? '<tr><td colspan="5"><div class="idlm-state is-empty"><strong>No Won leads match</strong><span>Try another name, phone, email or quote number, or clear the filters.</span><a class="idlm-btn idlm-btn-secondary idlm-btn-sm" href="' . esc_url($base) . '">Clear filters</a></div></td></tr>'
                : '<tr><td colspan="5"><div class="idlm-state is-empty"><strong>No Won leads in your payment scope</strong><span>Leads appear here once they reach the Won stage and are assigned to you.</span></div></td></tr>';
        }
        foreach ($page['rows'] as $lead) {
            $name = trim($lead->first_name . ' ' . $lead->last_name); $open = esc_url(add_query_arg('payment_lead_id', $lead->id, $base));
            // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped when built: helpers and variables here concatenate only esc_html/esc_attr/esc_url values, nonce fields and fixed markup. Builder: Shashi.
            echo '<tr class="idlm-payment-lead-row"><td class="idlm-payment-lead-id">#' . esc_html($lead->id) . '</td><td class="idlm-payment-lead-name"><a class="idlm-payment-row-link" href="' . $open . '"><strong>' . esc_html($name) . '</strong></a></td><td class="idlm-payment-lead-project">' . esc_html($lead->project_name) . '</td><td class="idlm-payment-lead-balance">' . self::balance_cell($page['balances'][(int) $lead->id] ?? null) . '</td><td class="idlm-payment-lead-action"><a class="idlm-btn idlm-btn-secondary" href="' . $open . '" aria-label="' . esc_attr('Open payments for ' . $name . ', lead #' . $lead->id) . '">Open</a></td></tr>';
        }
        echo '</tbody></table></div></div><nav class="idlm-pagination" aria-label="Payment leads pages">';
        if ($filters['page'] > 1) { echo '<a class="idlm-btn idlm-btn-secondary" href="' . esc_url(add_query_arg(self::list_args($filters, $filters['page'] - 1), $base)) . '">Previous</a>'; }
        if ($page['more']) { echo '<a class="idlm-btn idlm-btn-secondary" href="' . esc_url(add_query_arg(self::list_args($filters, $filters['page'] + 1), $base)) . '">Next</a>'; }
        echo '</nav>';
    }
    // phpcs:enable WordPress.Security.NonceVerification.Recommended
    const METHODS = array('UPI', 'Bank transfer', 'Cheque', 'Card', 'Cash');
    const DONE = array('record' => 'Payment recorded.', 'schedule' => 'Payment plan set.', 'resolve_legacy' => 'Payment plan saved from the old quotation.', 'won_plan' => 'Payment plan set from the Won amount.', 'correct' => 'Payment corrected.', 'revoke' => 'Payment revoked.');
    private static function advance_badge($status) {
        $badges = array('Met' => array('is-success', 'Advance paid'), 'Partial' => array('is-warning', 'Advance partly paid'), 'Unpaid' => array('is-neutral', 'Advance not paid'));
        $badge = $badges[$status] ?? array('is-neutral', 'Advance ' . strtolower((string) $status));
        return '<span class="idlm-badge ' . esc_attr($badge[0]) . '">' . esc_html($badge[1]) . '</span>';
    }
    private static function dialog_open($id, $title, $description, $operation, $lead_id, $version, $return_url, $multipart = false, $attributes = '') {
        wp_enqueue_script('idlm-design-detail', IDLM_URL . 'assets/js/design-detail.js', array('idlm-dialog'), IDLM_Admin_Assets::version('js/design-detail.js'), true);
        self::$open_dialog = $id;
        echo '<div class="idlm-modal idlm-dialog idlm-design-dialog" id="' . esc_attr($id) . '"' . (self::$retry && self::$retry['dialog'] === $id ? ' data-payment-reopen' : '') . ' role="dialog" aria-modal="true" aria-labelledby="' . esc_attr($id . '-title') . '"' . ($description ? ' aria-describedby="' . esc_attr($id . '-description') . '"' : '') . ' aria-hidden="true" hidden data-idlm-dialog data-idlm-initial-focus=".idlm-dialog__body :is(textarea, select, input:not([type=hidden]))"><div class="idlm-modal-content idlm-dialog__panel"><header class="idlm-modal-header idlm-dialog__header"><div><h2 class="idlm-dialog__title" id="' . esc_attr($id . '-title') . '">' . esc_html($title) . '</h2>' . ($description ? '<p class="idlm-dialog__description" id="' . esc_attr($id . '-description') . '">' . esc_html($description) . '</p>' : '') . '</div><button type="button" class="idlm-dialog__close" data-idlm-dialog-close aria-label="Close dialog"><span class="dashicons dashicons-no-alt" aria-hidden="true"></span></button></header>';
        if (null === $operation) { echo '<div class="idlm-dialog__body">'; return; }
        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped when built: helpers and variables here concatenate only esc_html/esc_attr/esc_url values, nonce fields and fixed markup. Builder: Shashi.
        echo '<form class="idlm-design-dialog-form idlm-payment-form" method="post"' . ($multipart ? ' enctype="multipart/form-data"' : '') . ' action="' . esc_url(admin_url('admin-post.php')) . '"' . $attributes . '>';
        self::hidden('action', 'idlm_payment_' . $operation); self::hidden('payment_lead_id', $lead_id);
        self::hidden('payment_version', $version); self::hidden('request_key', wp_generate_uuid4()); self::hidden('return_url', $return_url); self::hidden('payment_dialog', $id);
        wp_nonce_field('idlm_payment_' . $operation);
        echo '<div class="idlm-dialog__body">';
        // The dialog that was refused shows why, inside itself, above what was typed.
        if (self::$retry && self::$retry['dialog'] === $id) { self::$retry_shown = true; echo '<div class="idlm-alert idlm-alert-error" role="alert" data-idlm-notice-inline>' . esc_html(self::$retry['message']) . '</div>'; }
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
        $method = self::retried('method', $receipt->method ?? '');
        $options = '';
        foreach (array_unique(array_merge(self::METHODS, '' !== $method ? array($method) : array())) as $option) { $options .= '<option value="' . esc_attr($option) . '"' . selected($option, $method, false) . '>' . esc_html($option) . '</option>'; }
        echo '<div class="idlm-design-dialog-grid">';
        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped when built: helpers and variables here concatenate only esc_html/esc_attr/esc_url values, nonce fields and fixed markup. Builder: Shashi.
        echo self::group($prefix . '-amount', esc_html('Amount received (' . $currency . ')'), '<input class="idlm-input" id="' . esc_attr($prefix . '-amount') . '" name="amount" inputmode="decimal" autocomplete="off" required value="' . esc_attr(self::retried('amount', $receipt->amount ?? '')) . '">');
        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped when built: helpers and variables here concatenate only esc_html/esc_attr/esc_url values, nonce fields and fixed markup. Builder: Shashi.
        echo self::group($prefix . '-date', 'Date received', '<input class="idlm-input" type="date" id="' . esc_attr($prefix . '-date') . '" name="received_date" required value="' . esc_attr(self::retried('received_date', $receipt->received_date ?? current_time('Y-m-d'))) . '">');
        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped when built: helpers and variables here concatenate only esc_html/esc_attr/esc_url values, nonce fields and fixed markup. Builder: Shashi.
        echo self::group($prefix . '-method', 'Method', '<select class="idlm-input" id="' . esc_attr($prefix . '-method') . '" name="method" required>' . $options . '</select>');
        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped when built: helpers and variables here concatenate only esc_html/esc_attr/esc_url values, nonce fields and fixed markup. Builder: Shashi.
        echo self::group($prefix . '-reference', 'Transaction reference', '<input class="idlm-input" id="' . esc_attr($prefix . '-reference') . '" name="reference" maxlength="255" autocomplete="off" value="' . esc_attr(self::retried('reference', $receipt->reference ?? '')) . '">');
        echo '</div>';
        $current = $receipt ? (int) $receipt->attachment_id : 0;
        self::hidden('attachment_id', $current);
        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped when built: helpers and variables here concatenate only esc_html/esc_attr/esc_url values, nonce fields and fixed markup. Builder: Shashi.
        echo self::group($prefix . '-file', $current ? 'Replace screenshot' : 'Or payment screenshot', '<input class="idlm-input" type="file" id="' . esc_attr($prefix . '-file') . '" name="payment_screenshot" accept="image/jpeg,image/png,image/webp" aria-describedby="' . esc_attr($prefix . '-file-help') . '">', 'A reference or a screenshot is required. JPEG, PNG or WebP, up to 10 MB.');
        if ($current) { echo '<p><a href="' . esc_url(IDLM_Private_Files::url($current)) . '" target="_blank" rel="noopener">View current screenshot</a></p><label class="idlm-payment-checkbox"><input type="checkbox" name="remove_screenshot" value="1"> Remove the current screenshot</label>'; }
        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped when built: helpers and variables here concatenate only esc_html/esc_attr/esc_url values, nonce fields and fixed markup. Builder: Shashi.
        echo self::group($prefix . '-note', 'Note (optional)', '<textarea class="idlm-input" id="' . esc_attr($prefix . '-note') . '" name="note" rows="2" maxlength="8000">' . esc_textarea(self::retried('note', $receipt->note ?? '')) . '</textarea>');
        $duplicate = self::retried('duplicate_review_reason', (string) ($receipt->duplicate_review_reason ?? '')); $reviewed = '' !== $duplicate;
        echo '<div class="idlm-form-group" data-payment-duplicate' . ($reviewed ? '' : ' hidden') . '><label for="' . esc_attr($prefix . '-duplicate') . '">This reference was used for an earlier payment. Why is this a separate payment?</label><textarea class="idlm-input" id="' . esc_attr($prefix . '-duplicate') . '" name="duplicate_review_reason" rows="2" maxlength="8000"' . ($reviewed ? '' : ' disabled') . '>' . esc_textarea($duplicate) . '</textarea></div>';
    }
    private static function reason_group($id, $label) {
        return self::group($id, esc_html($label), '<textarea class="idlm-input" id="' . esc_attr($id) . '" name="reason" rows="2" maxlength="8000" required>' . esc_textarea(self::retried('reason')) . '</textarea>');
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
    /** Which quotation revision and plan version the schedule comes from. */
    private static function plan_source($schedule) {
        $revision = (int) $schedule['revision_id'] ? IDLM_Quotation_Repository::get_revision((int) $schedule['revision_id']) : null;
        $name = $revision ? (string) $revision->revision_name : '';
        return ('' !== $name ? 'From quotation ' . $name . ', plan' : 'Plan') . ' version ' . (int) $schedule['version_no'] . '.';
    }
    /** Who entered recovered historic terms, when, and why. */
    private static function recovery_detail($resolution, $reason) {
        $resolver = get_userdata((int) ($resolution['resolved_by'] ?? 0));
        return 'Entered by ' . ($resolver ? $resolver->display_name : 'an authorized user') . ' on ' . self::date(substr((string) ($resolution['resolved_at'] ?? ''), 0, 10)) . ('' !== $reason ? '. Reason: ' . $reason : '') . '.';
    }
    private static function render_simple_milestones($context, $locked, $change = '') {
        $schedule = $context['schedule']; $balance = $context['balance']; $currency = $balance['currency'];
        $snapshot = json_decode($schedule['snapshot'], true);
        $type = $snapshot['resolution']['type'] ?? '';
        $note = $locked ? 'The plan follows the Customer approved price in Design.' : ('won_amount' === $type ? 'Set from the Won amount on the lead.' : ($type ? 'Recovered from ' . $snapshot['resolution']['source_reference'] . '.' : ''));
        $note = trim(self::plan_source($schedule) . ' ' . $note);
        $recovered = ('' !== $type && 'won_amount' !== $type) ? self::recovery_detail($snapshot['resolution'], (string) $schedule['reason']) : '';
        echo '<section class="idlm-card idlm-payment-panel"><div class="idlm-panel-heading idlm-payment-panel-heading"><div><h2>Milestones</h2>' . ($note ? '<p>' . esc_html($note) . '</p>' : '') . ($recovered ? '<p>' . esc_html($recovered) . '</p>' : '') . '<p>' . esc_html('Advance: ' . self::money($balance['advance_received'], $currency) . ' received of ' . self::money($balance['expected_advance'], $currency) . '.') . '</p></div>';
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
            // Every payment opens its record: receipt number, who recorded it, reasons and earlier versions.
            $actions[] = '<button type="button" class="idlm-btn idlm-btn-quiet" data-design-dialog="' . esc_attr('idlm-payment-changes-' . $id) . '" aria-haspopup="dialog">' . esc_html(count($group) > 1 ? 'Changes (' . (count($group) - 1) . ')' : 'Details') . '</button>'; $dialogs[] = array('changes', $group);
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
                self::dialog_open('idlm-payment-changes-' . $id, count($group) > 1 ? 'Changes to this payment' : 'Payment details', 'Receipt #' . $id . (count($group) > 1 ? '. Newest first. Earlier versions are kept for the record.' : '.'), null, $lead_id, 0, '');
                echo '<ol class="idlm-payment-changes">';
                foreach ($group as $row) { $actor = get_userdata($row->actor_id); echo '<li><strong>' . esc_html('Version ' . $row->version_no . ' · ' . self::money($row->amount, $row->currency) . ' · ' . self::date($row->received_date) . ' · ' . $row->method . ('revoked' === $row->status ? ' · Revoked' : '')) . '</strong><small>' . esc_html('Recorded by ' . ($actor ? $actor->display_name : 'a team member') . ', ' . self::date($row->recorded_at) . ('' !== $row->reason ? ': ' . $row->reason : '')) . '</small>' . ('' !== $row->duplicate_review_reason ? '<small>' . esc_html('Duplicate reference reviewed: ' . $row->duplicate_review_reason) . '</small>' : '') . '</li>'; }
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
        echo self::group('idlm-payment-legacy-source', 'Where are these terms from?', '<input class="idlm-input" id="idlm-payment-legacy-source" name="source_reference" maxlength="1000" required placeholder="For example, signed quotation emailed on 3 March" value="' . esc_attr(self::retried('source_reference')) . '">');
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
        self::simple_receipt_fields('idlm-payment-handoff', $currency, (object) array('amount' => $row->advance_amount, 'received_date' => (string) $row->advance_date, 'method' => $row->advance_method, 'reference' => $row->advance_reference, 'attachment_id' => IDLM_Lead_Payments::usable_proof($lead_id, (int) $row->advance_attachment_id) ? (int) $row->advance_attachment_id : 0, 'note' => 'Advance entered with Send to design', 'duplicate_review_reason' => ''));
        self::dialog_close('Record advance');
    }
}
