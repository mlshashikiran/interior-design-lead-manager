<?php
/** Requests and screens for the design handoff: lead card, send, Design team tab. Builder: Shashi. */
if (!defined('ABSPATH')) { exit; }

final class IDLM_Design_Handoff_Controller {
    const VIEW = 'design-team';
    /** Every query key the Design team view reads. Navigation drops them all. */
    const QUERY_KEYS = array('tab', 'queue', 'handoff', 'project', 'q', 'stage', 'designer', 'target', 'state', 'design_page', 'design_period', 'design_quote', 'design_approve', 'design_notice', 'design_error', 'design_settings');

    public static function init() {
        add_action('wp_ajax_idlm_design_handoff_send', array(__CLASS__, 'ajax_send'));
        add_action('admin_post_idlm_design_handoff_accept', array(__CLASS__, 'handle_decision'));
        add_action('admin_post_idlm_design_handoff_assign', array(__CLASS__, 'handle_decision'));
        add_action('admin_post_idlm_design_handoff_send_back', array(__CLASS__, 'handle_decision'));
        add_action('wp_ajax_idlm_design_handoff_decide', array(__CLASS__, 'ajax_decision'));
        add_action(IDLM_Design_Handoffs::NOTIFICATION_HOOK, array('IDLM_Design_Handoffs', 'notify'), 10, 2);
    }

    private static function text($input, $key) { return isset($input[$key]) && is_string($input[$key]) ? wp_unslash($input[$key]) : ''; }
    private static function id($input, $key) {
        $value = self::text($input, $key);
        return ctype_digit($value) && strlen($value) < 19 ? (int) $value : 0;
    }

    /** One multipart request from the dialog: permission, validation, upload, then save. */
    public static function ajax_send() {
        check_ajax_referer('idlm_design_handoff_send', 'nonce');
        $lead_id = self::id($_POST, 'lead_id');
        if (!$lead_id || !IDLM_Design_Handoffs::can_send($lead_id)) { wp_send_json_error(array('message' => 'You do not have permission to send this lead to design.'), 403); }
        $input = array('notes' => self::text($_POST, 'notes'), 'scope_summary' => self::text($_POST, 'scope_summary'), 'pending_items' => self::text($_POST, 'pending_items'), 'design_manager_id' => self::id($_POST, 'design_manager_id'), 'amount' => self::text($_POST, 'amount'), 'received_date' => self::text($_POST, 'received_date'), 'method' => self::text($_POST, 'method'), 'reference' => self::text($_POST, 'reference'), 'attachment_id' => 0);
        $key = self::text($_POST, 'request_key');
        $file = isset($_FILES['advance_screenshot']) && UPLOAD_ERR_NO_FILE !== (int) ($_FILES['advance_screenshot']['error'] ?? UPLOAD_ERR_NO_FILE) ? $_FILES['advance_screenshot'] : null;
        $result = IDLM_Recovery_Guard::run(static function() use ($lead_id, $input, $key, $file) {
            // Reject a bad form before storing any file.
            $submission = IDLM_Design_Handoffs::validate_submission($input);
            if (is_wp_error($submission)) { return $submission; }
            if ($file) {
                $upload = IDLM_Private_Files::upload($file, $lead_id, 'payment');
                if (is_wp_error($upload)) { return $upload; }
                $input['attachment_id'] = (int) $upload['attachment_id'];
            }
            return IDLM_Design_Handoffs::send($lead_id, $input, $key);
        });
        if (false === $result) { $result = new WP_Error('idlm_handoff_busy', 'The CRM is busy. Try again shortly.'); }
        if (is_wp_error($result)) { wp_send_json_error(array('message' => $result->get_error_message()), 422); }
        wp_send_json_success(array('lead_id' => $lead_id, 'message' => 'Sent to design.'));
    }

    const DECISIONS = array('idlm_design_handoff_accept' => 'accepted', 'idlm_design_handoff_assign' => 'assigned', 'idlm_design_handoff_send_back' => 'sent_back');

    /** Accept, assign or return. The caller has already checked the nonce for $action. */
    private static function run_decision($action, $input) {
        $handoff_id = self::id($input, 'handoff_id');
        $designer = self::id($input, 'designer_id'); $reason = self::text($input, 'reason'); $target = self::text($input, 'target_date');
        $result = IDLM_Recovery_Guard::run(static function() use ($action, $handoff_id, $designer, $reason, $target) {
            if ('idlm_design_handoff_accept' === $action) { return IDLM_Design_Handoffs::accept($handoff_id, $designer, $target); }
            if ('idlm_design_handoff_assign' === $action) { return IDLM_Design_Handoffs::assign($handoff_id, $designer, $target, $reason); }
            return IDLM_Design_Handoffs::send_back($handoff_id, $reason);
        });
        return true === $result ? true : (is_wp_error($result) ? sanitize_key(str_replace('idlm_handoff_', '', $result->get_error_code())) : 'busy');
    }

    public static function handle_decision() {
        $action = self::text($_POST, 'action');
        if (!isset(self::DECISIONS[$action])) { wp_die('Invalid request.', '', array('response' => 400)); }
        if (!IDLM_Design_Handoffs::can_manage()) { wp_die('You do not have permission to act on design handoffs.', '', array('response' => 403)); }
        check_admin_referer($action);
        $result = self::run_decision($action, $_POST);
        $fallback = add_query_arg('idlm_view', self::VIEW, IDLM_Admin_Shortcodes::workspace_url());
        $url = wp_validate_redirect(self::text($_POST, 'return_url'), $fallback);
        // The table stays on the table after a save; a refusal opens the project, which has the full form.
        if (true !== $result || '1' !== self::text($_POST, 'stay')) { $url = add_query_arg('handoff', self::id($_POST, 'handoff_id'), $url); }
        $url = true === $result ? add_query_arg('design_notice', self::DECISIONS[$action], $url) : add_query_arg('design_error', $result, $url);
        wp_safe_redirect($url); exit;
    }

    /** The table's inline saves: the same nonce, capability and scope checks as the form post. */
    public static function ajax_decision() {
        $action = 'idlm_design_handoff_' . sanitize_key(self::text($_POST, 'operation'));
        if (!isset(self::DECISIONS[$action])) { wp_send_json_error(array('message' => 'Invalid request.'), 400); }
        if (!IDLM_Design_Handoffs::can_manage()) { wp_send_json_error(array('message' => self::error_message('denied'), 'code' => 'denied'), 403); }
        check_ajax_referer($action, '_wpnonce');
        $result = self::run_decision($action, $_POST);
        if (true !== $result) { wp_send_json_error(array('message' => self::error_message($result), 'code' => $result), 422); }
        $messages = array('accepted' => 'Handoff accepted.', 'assigned' => 'Designer saved.', 'sent_back' => 'Handoff returned to sales.');
        $row = IDLM_Design_Handoffs::find(self::id($_POST, 'handoff_id'));
        wp_send_json_success(array('message' => $messages[self::DECISIONS[$action]], 'designer' => $row ? (int) $row->designer_id : 0, 'target' => $row ? (string) $row->target_date : ''));
    }

    private static function error_message($code) {
        $messages = array(
            'denied' => 'You do not have permission to act on design handoffs.', 'missing' => 'This handoff is no longer available.',
            'state' => 'This handoff has already changed. Check its current status.', 'won' => 'This lead is no longer Won. Return it or ask sales to review it.',
            'designer' => 'Choose a designer who works on design projects.', 'target' => 'Choose a target completion date with the designer.', 'reason' => 'Give a reason for changing the designer.', 'text' => 'A reason is required to return a handoff.', 'busy' => 'The CRM is busy. Try again shortly.', 'quotation' => 'This lead has no quotation with payment terms. Send it back so sales can attach one.',
        );
        return $messages[$code] ?? 'That change was not saved. Try again.';
    }

    public static function money($value, $currency) {
        $parts = explode('.', number_format((float) $value, 2, '.', ''), 2);
        $integer = preg_replace('/\B(?=(\d{3})+(?!\d))/', ',', $parts[0]);
        $symbols = array('INR' => '₹', 'USD' => '$', 'EUR' => '€', 'GBP' => '£');
        return ($symbols[$currency] ?? $currency . ' ') . $integer . '.' . $parts[1];
    }
    public static function date($value) { return $value ? mysql2date('d M Y', $value) : ''; }
    public static function person($user_id) {
        $user = get_userdata((int) $user_id);
        return $user ? $user->display_name : 'Team member';
    }

    /** Lead drawer panel. Hidden from pre-sales, and until there is something to do or see. */
    public static function drawer_card($lead) {
        if (IDLM_Lead_Access_Policy::is_pre_sales()) { return ''; }
        $row = IDLM_Design_Handoffs::for_lead($lead->id);
        $eligible = 'Won' === $lead->stage && empty($lead->is_duplicate);
        $can_send = $eligible && IDLM_Design_Handoffs::can_send($lead->id);
        if (!$row && !$can_send) { return ''; }
        $status = $row ? $row->status : '';
        $line = 'Nothing goes to design until you send it.'; $tone = 'is-neutral'; $label = 'Not sent';
        if (IDLM_Design_Handoffs::STATUS_NEW === $status) { $tone = 'is-info'; $label = 'Waiting for design'; $line = 'Sent to ' . self::person($row->design_manager_id) . ' on ' . self::date($row->sent_at) . '. Waiting to be accepted.'; }
        if (IDLM_Design_Handoffs::STATUS_ACCEPTED === $status) { $tone = 'is-success'; $label = 'With design'; $line = ((int) $row->designer_id ? 'Assigned to ' . self::person($row->designer_id) . '.' : 'Accepted. A designer is being assigned.') . ((int) $row->agreed_revision_id ? ' Customer-approved price: ' . self::money($row->agreed_total, $row->advance_currency ?: 'INR') . '.' : ''); }
        if (IDLM_Design_Handoffs::STATUS_SENT_BACK === $status) { $tone = 'is-warning'; $label = 'Returned'; $line = 'Returned by ' . self::person($row->decided_by) . ': ' . $row->send_back_reason; }
        $html = '<section class="idlm-drawer-panel idlm-design-card"><div class="idlm-drawer-panel-head"><h3 class="idlm-drawer-panel-title">Design handoff</h3><span class="idlm-badge ' . esc_attr($tone) . '">' . esc_html($label) . '</span></div><div class="idlm-design-card-body"><p>' . esc_html($line) . '</p>';
        if ($row && !$eligible && IDLM_Design_Handoffs::STATUS_SENT_BACK !== $status) { $html .= '<p class="idlm-design-card-warning">This lead is no longer Won. The design team has been shown this.</p>'; }
        $missing = $can_send && IDLM_Design_Handoffs::can_transition($status, 'send') ? IDLM_Design_Handoffs::quotation_missing($lead->id) : '';
        if ('' !== $missing) {
            // Sending is refused anyway; say why and point to the lead's quotations.
            $html .= '<p class="idlm-design-card-warning">' . esc_html($missing) . '</p><a class="idlm-btn idlm-btn-secondary" href="' . esc_url(add_query_arg(array('idlm_view' => 'quotations', 'lead_id' => (int) $lead->id), IDLM_Admin_Shortcodes::workspace_url())) . '">Open quotations</a>';
        } elseif ($can_send && IDLM_Design_Handoffs::can_transition($status, 'send')) {
            $html .= '<button type="button" class="idlm-btn idlm-btn-primary idlm-design-send-open" data-idlm-handoff="' . esc_attr(wp_json_encode(self::form_data($lead, $row))) . '">' . ($row ? 'Fix and resend' : 'Send to design') . '</button>';
        }
        return $html . '</div></section>';
    }

    /** What the dialog needs: only this lead's data, for someone already allowed to send it. */
    private static function form_data($lead, $row) {
        $receipts = IDLM_Design_Handoffs::current_receipts($lead->id);
        $balance = IDLM_Design_Handoffs::balance($lead->id);
        $basis = $balance ? null : IDLM_Design_Handoffs::basis($lead->id);
        $data = array('lead_id' => (int) $lead->id, 'lead_name' => trim($lead->first_name . ' ' . $lead->last_name), 'nonce' => wp_create_nonce('idlm_design_handoff_send'), 'request_key' => wp_generate_uuid4(), 'notes' => $row ? $row->notes : '', 'scope_summary' => $row ? $row->scope_summary : '', 'pending_items' => $row ? $row->pending_items : '', 'design_manager_id' => $row ? (int) $row->design_manager_id : 0, 'managers' => array_map(static function($user) { return array('id' => (int) $user->ID, 'name' => $user->display_name); }, IDLM_Design_Handoffs::managers()), 'on_file' => '', 'basis' => '', 'amount' => '', 'received_date' => current_time('Y-m-d'), 'method' => '', 'reference' => '');
        if ($receipts) {
            $last = end($receipts); $currency = $last->currency;
            $data['on_file'] = self::money($balance['total_received'] ?? $last->amount, $currency) . ' already recorded. Last payment on ' . self::date($last->received_date) . '.';
        } elseif ($row && (float) $row->advance_amount > 0) {
            $data = array_merge($data, array('amount' => $row->advance_amount, 'received_date' => $row->advance_date, 'method' => $row->advance_method, 'reference' => $row->advance_reference));
        }
        if ($balance && !$receipts) { $data['basis'] = 'Expected advance: ' . self::money($balance['expected_advance'] ?? 0, $balance['currency'] ?? 'INR') . '.'; }
        if ($basis) { $data['basis'] = $basis['revision']->revision_name . ' · ' . self::money($basis['revision']->grand_total, $basis['snapshot']['currency']) . '. Expected advance: ' . self::money($basis['milestone']['amount'], $basis['snapshot']['currency']) . ' (' . $basis['milestone']['name'] . ').'; }
        return $data;
    }

    private static function workspace_base() { return add_query_arg('idlm_view', self::VIEW, IDLM_Admin_Shortcodes::workspace_url()); }

    public static function render() {
        if (!IDLM_Design_Handoffs::can_receive()) { echo '<div class="idlm-state is-error"><strong>Design team access is required</strong></div>'; return; }
        $handoff_id = self::id($_GET, 'handoff');
        // Load before any output, so the editor's scripts are queued in time.
        $editing = $handoff_id && 'edit' === sanitize_key(self::text($_GET, 'design_quote'));
        if ($editing) { IDLM_Quotations::load_editor_assets(); }
        echo '<div class="wrap idlm-wrap idlm-design-team">';
        $notice = sanitize_key(self::text($_GET, 'design_notice')); $error = sanitize_key(self::text($_GET, 'design_error'));
        $notices = array('stage' => 'Stage updated.', 'hold' => 'Project on hold.', 'resume' => 'Project resumed.', 'completed' => 'Project marked complete.', 'reopened' => 'Project reopened.', 'document' => 'Document uploaded.', 'archived' => 'Document archived.', 'settings' => 'Design stages saved.', 'approved' => 'Customer approved saved. The payment schedule now uses this price.', 'payment' => 'Payment recorded.', 'revision' => 'Design revision saved. The payment schedule has not changed.', 'accepted' => 'Handoff accepted.', 'assigned' => 'Designer saved.', 'sent_back' => 'Handoff returned to sales.', 'reviewed' => 'Design revision reviewed. It can now be printed and shared.', 'cleared' => 'Review flag cleared.');
        if (isset($notices[$notice])) { echo '<div class="idlm-alert idlm-alert-success" role="status">' . esc_html($notices[$notice]) . '</div>'; }
        if ($error) { echo '<div class="idlm-alert idlm-alert-error" role="alert">' . esc_html(self::error_message($error)) . '</div>'; }
        if (isset($_GET['design_settings']) && IDLM_Design_Stage_Controller::render_settings()) { echo '</div>'; return; }
        if ($editing && self::render_quote_editor($handoff_id)) { echo '</div>'; return; }
        $approve = self::id($_GET, 'design_approve');
        if ($handoff_id && $approve && self::render_approval($handoff_id, $approve)) { echo '</div>'; return; }
        if ($handoff_id) { self::render_detail($handoff_id); } else { self::render_list(); }
        echo '</div>';
    }

    /** Managers land on the projects table; designers on a board of their own projects. */
    private static function render_list() {
        $base = self::workspace_base(); $manager = IDLM_Design_Handoffs::can_manage();
        $tabs = $manager ? array('projects' => 'Projects', 'board' => 'Board', 'overview' => 'Overview') : array('board' => 'My board', 'projects' => 'My projects');
        $tab = sanitize_key(self::text($_GET, 'tab'));
        // Links from before the table (queues and the old list) open the matching table view.
        if (in_array($tab, array('list', 'new', 'unassigned', 'returned'), true)) { $tab = 'projects'; }
        if (!isset($tabs[$tab])) { $tab = $manager ? 'projects' : 'board'; }
        echo '<header class="idlm-header"><div><h1>Design team</h1></div>' . (current_user_can('manage_options') ? '<div class="idlm-header-actions"><a class="idlm-btn idlm-btn-secondary" href="' . esc_url(add_query_arg('design_settings', '1', $base)) . '">Design stages</a></div>' : '') . '</header><nav class="idlm-design-tabs" aria-label="Design team views">';
        foreach ($tabs as $key => $label) { echo '<a href="' . esc_url(add_query_arg('tab', $key, $base)) . '"' . ($key === $tab ? ' class="is-active" aria-current="page"' : '') . '>' . esc_html($label) . '</a> '; }
        echo '</nav>';
        if ('overview' === $tab) { IDLM_Design_Dashboard::render($base); return; }
        if ('board' === $tab) { IDLM_Design_Board::render($base); return; }
        IDLM_Design_Projects_Table::render($base);
    }

    public static function status_badge($row, $stage) {
        $tones = array(IDLM_Design_Handoffs::STATUS_NEW => 'is-info', IDLM_Design_Handoffs::STATUS_ACCEPTED => 'is-success', IDLM_Design_Handoffs::STATUS_SENT_BACK => 'is-warning');
        $label = IDLM_Design_Handoffs::status_label($row->status);
        if (IDLM_Design_Handoffs::STATUS_ACCEPTED === $row->status) { $label = (int) $row->designer_id ? 'With designer: ' . self::person($row->designer_id) : 'Needs a designer'; }
        $html = '<span class="idlm-badge ' . esc_attr($tones[$row->status] ?? 'is-neutral') . '">' . esc_html($label) . '</span>';
        if (IDLM_Design_Handoffs::STATUS_ACCEPTED === $row->status) {
            $stages = IDLM_Design_Stages::stages();
            $html .= ' <small>' . esc_html($row->completed_at ? 'Complete' : $stages[IDLM_Design_Stages::stage_of($row)]['label']) . '</small>';
            if ($row->hold_at) { $html .= ' <span class="idlm-badge is-warning">On hold</span>'; }
        }
        if ('Won' !== $stage && IDLM_Design_Handoffs::STATUS_SENT_BACK !== $row->status) { $html .= ' <span class="idlm-badge is-warning">Lead no longer Won</span>'; }
        return $html;
    }

    private static function render_detail($handoff_id) {
        global $wpdb; $base = self::workspace_base();
        $row = IDLM_Design_Handoffs::find($handoff_id);
        // The same message for missing and out of scope, so an ID reveals nothing.
        $lead = $row && IDLM_Design_Handoffs::can_see($row) ? $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}idlm_leads WHERE id = %d", $row->lead_id)) : null;
        if (!$lead) { echo '<div class="idlm-state is-error"><strong>This handoff is not available to you</strong><a href="' . esc_url($base) . '">Back to Design team</a></div>'; return; }
        $name = trim($lead->first_name . ' ' . $lead->last_name);
        echo '<header class="idlm-header"><div><h1>' . esc_html($name) . '</h1></div><div class="idlm-header-actions"><a class="idlm-btn idlm-btn-secondary" href="' . esc_url($base) . '">All handoffs</a></div></header>';
        echo '<p class="idlm-design-status">' . self::status_badge($row, $lead->stage) . '</p>';
        if (IDLM_Design_Handoffs::STATUS_SENT_BACK === $row->status) { echo '<div class="idlm-alert idlm-alert-warning" data-idlm-notice-inline role="status">Returned by ' . esc_html(self::person($row->decided_by)) . ': ' . esc_html($row->send_back_reason) . '</div>'; }
        echo '<div class="idlm-design-layout"><div class="idlm-design-main">';
        IDLM_Design_Stage_Controller::render_stage_panel($row);
        echo '<section class="idlm-card idlm-design-panel"><h2>From sales</h2>' . self::submission_stack($row) . '<p class="idlm-design-muted">Sent by ' . esc_html(self::person($row->sent_by)) . ' on ' . esc_html(self::date($row->sent_at)) . '. Sales owner: ' . esc_html((int) $row->sales_owner_id ? self::person($row->sales_owner_id) : 'Unassigned') . '.' . ((int) $row->design_manager_id ? ' Design Manager: ' . esc_html(self::person($row->design_manager_id)) . '.' : '') . '</p></section>';
        self::render_customer($lead);
        self::render_quotation($row);
        IDLM_Design_Stage_Controller::render_documents_panel($row);
        self::render_advance($row);
        self::render_versions($row);
        echo '</div>';
        self::render_actions($row, $lead);
        echo '</div>';
    }

    /** Handoffs from the first local build have no scope or pending items, so blanks are skipped. */
    public static function submission_stack($record) {
        $html = '<dl class="idlm-design-stack">';
        foreach (array('notes' => 'Remarks', 'scope_summary' => 'Scope', 'pending_items' => 'Pending items') as $field => $label) {
            if ('' !== (string) $record->$field) { $html .= '<div><dt>' . esc_html($label) . '</dt><dd>' . nl2br(esc_html($record->$field)) . '</dd></div>'; }
        }
        return $html . '</dl>';
    }

    private static function render_quote_editor($handoff_id) {
        $row = IDLM_Design_Handoffs::find($handoff_id);
        if (!IDLM_Design_Revisions::can_edit($row)) { return false; }
        $source_id = IDLM_Design_Revisions::working_revision_id($row);
        $bundle = $source_id ? IDLM_Quotation_Repository::get_quote_bundle((int) $row->baseline_quote_id, $source_id) : null;
        if (!$bundle || !$bundle->revision) { return false; }
        IDLM_Quotation_Renderer::render_design_editor($row, $bundle->revision, $bundle, IDLM_Design_Revisions::can_change_pricing($row));
        return true;
    }

    private static function render_quotation($row) {
        echo '<section class="idlm-card idlm-design-panel"><h2>Quotation</h2>';
        if (!(int) $row->baseline_revision_id) { echo '<p class="idlm-design-muted">This project was sent without a quotation, so the quote checks on its stages cannot be met. Send it back to sales: they attach a quotation with payment terms and send it again.</p></section>'; return; }
        $sales = IDLM_Quotation_Repository::get_revision((int) $row->baseline_revision_id);
        $latest = (int) $row->design_revision_id ? IDLM_Quotation_Repository::get_revision((int) $row->design_revision_id) : null;
        $currency = $sales ? $sales->currency_code : 'INR';
        $base = self::workspace_base();
        echo '<dl class="idlm-design-facts"><div><dt>Sales price</dt><dd>' . esc_html(self::money($row->baseline_total, $currency)) . ($sales ? ' <small>' . esc_html($sales->revision_name) . '</small>' : '') . '</dd></div>';
        if ((int) $row->agreed_revision_id) { echo '<div><dt>Agreed price</dt><dd>' . esc_html(self::money($row->agreed_total, $currency)) . ' <small>Customer approved on ' . esc_html(self::date($row->agreed_at)) . '</small></dd></div>'; }
        if ($latest) {
            $difference = (float) $latest->grand_total - (float) $row->baseline_total;
            echo '<div><dt>Latest design price</dt><dd>' . esc_html(self::money($latest->grand_total, $currency)) . ' <small>' . esc_html($latest->revision_name) . '</small>' . (IDLM_Design_Revisions::is_reviewed(IDLM_Design_Revisions::meta($latest->id)) ? '' : ' <span class="idlm-badge is-warning">Waiting for review</span>') . '</dd></div><div><dt>Difference</dt><dd>' . esc_html(($difference < 0 ? '-' : ($difference > 0 ? '+' : '')) . self::money(abs($difference), $currency)) . '</dd></div>';
        }
        echo '</dl><p class="idlm-design-muted">Payments use the ' . ((int) $row->agreed_revision_id ? 'agreed' : 'sales') . ' price. A design revision changes them only when it is marked Customer approved.</p><div class="idlm-design-quote-actions">';
        if (IDLM_Design_Revisions::can_edit($row)) { echo '<a class="idlm-btn idlm-btn-primary" href="' . esc_url(add_query_arg(array('handoff' => $row->id, 'design_quote' => 'edit'), $base)) . '">' . ($latest ? 'Edit quotation' : 'Start design revision') . '</a>'; }
        $can_approve = IDLM_Design_Handoffs::STATUS_ACCEPTED === $row->status && IDLM_Design_Handoffs::can_decide($row);
        $latest_reviewed = $latest && IDLM_Design_Revisions::is_reviewed(IDLM_Design_Revisions::meta($latest->id));
        if ($latest && $can_approve && !$latest_reviewed) { echo self::review_form($row, $latest->id, 'idlm-btn idlm-btn-primary', 'Mark latest revision reviewed'); }
        if ($latest && $can_approve && $latest_reviewed && (int) $row->agreed_revision_id !== (int) $latest->id) { echo '<a class="idlm-btn idlm-btn-secondary" href="' . esc_url(add_query_arg(array('handoff' => $row->id, 'design_approve' => $latest->id), $base)) . '">Mark customer approved</a>'; }
        if ($sales) { echo '<a class="idlm-btn idlm-btn-secondary" target="_blank" rel="noopener" href="' . esc_url(IDLM_Design_Revisions::print_url($row->id, $sales->id)) . '">Print sales quotation</a>'; }
        echo '</div>';
        $history = IDLM_Design_Revisions::history($row->id);
        if ($history) {
            echo '<details class="idlm-design-versions"><summary>Design revisions (' . esc_html(count($history)) . ')</summary><ul class="idlm-design-revisions">';
            foreach ($history as $revision) {
                $reviewed = IDLM_Design_Revisions::is_reviewed($revision); $shareable = $reviewed || $can_approve;
                echo '<li>' . ($shareable ? '<a target="_blank" rel="noopener" href="' . esc_url(IDLM_Design_Revisions::print_url($row->id, $revision->id)) . '">' . esc_html($revision->revision_name) . '</a>' : '<span>' . esc_html($revision->revision_name) . '</span>') . ' ' . esc_html(self::money($revision->grand_total, $revision->currency_code));
                echo ' ' . ($reviewed ? '<span class="idlm-badge is-success">Reviewed</span>' : '<span class="idlm-badge is-warning">Waiting for review</span>') . '<small>' . esc_html(self::person($revision->actor_id) . ' · ' . $revision->revision_reason) . '</small>' . self::custom_lines_note($revision) . self::rate_changes_note($revision, $revision->currency_code);
                if ((int) $revision->id === (int) $row->agreed_revision_id) { echo '<span class="idlm-badge is-success">Agreed price</span> '; }
                if ($can_approve && !$reviewed) { echo self::review_form($row, $revision->id, 'idlm-design-text-button', 'Mark reviewed'); }
                elseif ($can_approve && (int) $revision->id !== (int) $row->agreed_revision_id && (int) $revision->id !== (int) ($latest->id ?? 0)) { echo '<a href="' . esc_url(add_query_arg(array('handoff' => $row->id, 'design_approve' => $revision->id), $base)) . '">Mark this one customer approved</a>'; }
                echo '</li>';
            }
            echo '</ul></details>';
        }
        echo '</section>';
    }

    /** One screen: what changes, then a required note. Nothing saves until confirmed. */
    private static function render_approval($handoff_id, $revision_id) {
        $row = IDLM_Design_Handoffs::find($handoff_id);
        if (!$row || IDLM_Design_Handoffs::STATUS_ACCEPTED !== $row->status || !IDLM_Design_Handoffs::can_decide($row)) { return false; }
        $back = add_query_arg('handoff', $row->id, self::workspace_base());
        $preview = IDLM_Design_Revisions::preview($row, $revision_id);
        echo '<header class="idlm-header"><div><h1>Customer approved</h1></div><div class="idlm-header-actions"><a class="idlm-btn idlm-btn-secondary" href="' . esc_url($back) . '">Back to handoff</a></div></header>';
        if (is_wp_error($preview)) { echo '<div class="idlm-state is-error"><strong>' . esc_html($preview->get_error_message()) . '</strong></div>'; return true; }
        $after = $preview['after']; $before = $preview['before'] ?: array(); $currency = $after['currency'];
        $old = array(); foreach ((array) ($before['milestones'] ?? array()) as $milestone) { $old[$milestone['name']] = $milestone['amount']; }
        echo '<section class="idlm-card idlm-design-panel"><h2>' . esc_html($preview['revision']->revision_name) . '</h2><dl class="idlm-design-facts"><div><dt>Current price for payments</dt><dd>' . esc_html(isset($before['basis_total']) ? self::money($before['basis_total'], $currency) : 'None yet') . '</dd></div><div><dt>New agreed price</dt><dd>' . esc_html(self::money($after['basis_total'], $currency)) . '</dd></div><div><dt>Received so far</dt><dd>' . esc_html(self::money($after['total_received'], $currency)) . '</dd></div><div><dt>Outstanding after this</dt><dd>' . esc_html(self::money($after['total_outstanding'], $currency)) . '</dd></div>';
        if ((float) $after['excess_credit'] > 0) { echo '<div><dt>Extra received (credit)</dt><dd>' . esc_html(self::money($after['excess_credit'], $currency)) . '</dd></div>'; }
        echo '</dl></section><div class="idlm-table-wrapper"><table class="idlm-table idlm-design-milestones"><caption class="screen-reader-text">Payment milestones before and after</caption><thead><tr><th scope="col">Milestone</th><th scope="col">Now expected</th><th scope="col">New expected</th><th scope="col">Received</th><th scope="col">Pending</th></tr></thead><tbody>';
        foreach ($after['milestones'] as $milestone) { echo '<tr><td>' . esc_html($milestone['name']) . ' <small>' . esc_html(rtrim(rtrim((string) $milestone['percentage'], '0'), '.')) . '%</small></td><td>' . esc_html(isset($old[$milestone['name']]) ? self::money($old[$milestone['name']], $currency) : '—') . '</td><td>' . esc_html(self::money($milestone['amount'], $currency)) . '</td><td>' . esc_html(self::money($milestone['received'], $currency)) . '</td><td>' . esc_html(self::money($milestone['pending'], $currency)) . '</td></tr>'; }
        echo '</tbody></table></div><form class="idlm-card idlm-design-panel idlm-design-approve" method="post" action="' . esc_url(admin_url('admin-post.php')) . '"><input type="hidden" name="action" value="idlm_design_quote_approve"><input type="hidden" name="handoff_id" value="' . esc_attr($row->id) . '"><input type="hidden" name="approve_revision_id" value="' . esc_attr($preview['revision']->id) . '"><input type="hidden" name="payment_version" value="' . esc_attr(IDLM_Lead_Payments::version($row->lead_id)) . '"><input type="hidden" name="request_key" value="' . esc_attr(wp_generate_uuid4()) . '"><input type="hidden" name="return_url" value="' . esc_attr(remove_query_arg(array('design_approve', 'design_notice', 'design_error', 'handoff'), wp_unslash($_SERVER['REQUEST_URI'] ?? ''))) . '">';
        wp_nonce_field('idlm_design_quote_approve');
        echo '<label for="idlm-design-approval-note">How did the customer approve this?</label><textarea class="idlm-input" id="idlm-design-approval-note" name="approval_note" rows="3" maxlength="2000" required placeholder="For example, approved by email on 12 March"></textarea><p class="idlm-design-muted">Payments already received stay as they are. They are applied to the new amounts, oldest milestone first.</p><button class="idlm-btn idlm-btn-primary">Confirm customer approved</button></form>';
        return true;
    }

    /** A one-button form: the manager confirms a revision may go to the customer. */
    private static function review_form($row, $revision_id, $class, $label) {
        $html = '<form class="idlm-design-inline-form" method="post" action="' . esc_url(admin_url('admin-post.php')) . '"><input type="hidden" name="action" value="idlm_design_quote_review"><input type="hidden" name="handoff_id" value="' . esc_attr($row->id) . '"><input type="hidden" name="revision_id" value="' . esc_attr($revision_id) . '"><input type="hidden" name="return_url" value="' . esc_attr(remove_query_arg(array('design_notice', 'design_error', 'handoff'), wp_unslash($_SERVER['REQUEST_URI'] ?? ''))) . '">';
        return $html . wp_nonce_field('idlm_design_quote_review', '_wpnonce', true, false) . '<button class="' . esc_attr($class) . '">' . esc_html($label) . '</button></form>';
    }

    /** Rates the designer changed from the revision before, for the manager's review. */
    private static function rate_changes_note($revision, $currency) {
        $changes = json_decode((string) ($revision->rate_changes ?? ''), true);
        if (!is_array($changes) || !$changes) { return ''; }
        $parts = array();
        foreach ($changes as $change) { $parts[] = $change['name'] . ' ' . ('' === (string) $change['before'] ? '' : self::money($change['before'], $currency) . ' to ') . self::money($change['after'], $currency); }
        return '<small>' . esc_html('Rates changed: ' . implode('; ', $parts)) . '</small>';
    }

    /** New custom lines carry a rate the rep chose, so a manager can check them here. */
    private static function custom_lines_note($revision) {
        $names = json_decode((string) $revision->custom_lines, true);
        return is_array($names) && $names ? '<small>' . esc_html('New custom lines to check: ' . implode(', ', $names)) . '</small>' : '';
    }

    private static function render_versions($row) {
        $earlier = array_filter(IDLM_Design_Handoffs::versions($row->id), static function($version) use ($row) { return (int) $version->version_no !== (int) $row->version_no; });
        if (!$earlier) { return; }
        echo '<details class="idlm-card idlm-design-panel idlm-design-versions"><summary>Earlier submissions (' . esc_html(count($earlier)) . ')</summary>';
        foreach ($earlier as $version) {
            echo '<div class="idlm-design-version"><strong>Submission ' . esc_html($version->version_no) . '</strong><p class="idlm-design-muted">Sent by ' . esc_html(self::person($version->sent_by)) . ' on ' . esc_html(self::date($version->sent_at)) . '. Returned by ' . esc_html(self::person($version->outcome_by)) . ': ' . esc_html($version->return_reason) . '</p>' . self::submission_stack($version) . '</div>';
        }
        echo '</details>';
    }

    private static function render_customer($lead) {
        echo '<section class="idlm-card idlm-design-panel"><h2>Customer and project</h2><dl class="idlm-design-facts">';
        // Phones hide the page title, so the name also sits with the customer details.
        echo '<div><dt>Customer</dt><dd>' . esc_html(trim($lead->first_name . ' ' . $lead->last_name)) . '</dd></div>';
        $phone = (string) $lead->phone;
        if ('' !== $phone) { echo '<div><dt>Phone</dt><dd><a href="tel:' . esc_attr(preg_replace('/[^0-9+]/', '', $phone)) . '">' . esc_html($phone) . '</a></dd></div>'; }
        foreach (array('email' => 'Email', 'project_name' => 'Project', 'property_type' => 'Project type', 'property_location' => 'Location', 'possession_date' => 'Possession date', 'project_timeline' => 'Timeline', 'preferred_studio' => 'Studio') as $field => $label) {
            if (!empty($lead->$field)) { echo '<div><dt>' . esc_html($label) . '</dt><dd>' . esc_html($lead->$field) . '</dd></div>'; }
        }
        echo '</dl><h3>Floor plans</h3>';
        $plans = IDLM_Admin_Presenter::normalize_floor_plan_files($lead->floor_plan_files ?? '', $lead->floor_plan_file ?? '');
        if (!$plans) { echo '<p class="idlm-design-muted">No floor plan on this lead.</p>'; }
        else { echo '<ul class="idlm-design-files">'; foreach (array_values($plans) as $index => $url) { echo '<li><a href="' . esc_url($url) . '" target="_blank" rel="noopener">Floor plan ' . esc_html($index + 1) . '</a></li>'; } echo '</ul>'; }
        echo '</section>';
    }

    private static function render_advance($row) {
        $balance = IDLM_Design_Handoffs::balance($row->lead_id); $receipts = IDLM_Design_Handoffs::current_receipts($row->lead_id);
        echo '<section class="idlm-card idlm-design-panel"><h2>Payments</h2>';
        if (!$receipts || !$balance) {
            echo '<dl class="idlm-design-facts">';
            foreach (array('Advance recorded' => self::money($row->advance_amount, $row->advance_currency), 'Received on' => self::date($row->advance_date), 'Method' => $row->advance_method, 'Reference' => $row->advance_reference) as $label => $value) { if ('' !== (string) $value) { echo '<div><dt>' . esc_html($label) . '</dt><dd>' . esc_html($value) . '</dd></div>'; } }
            echo '</dl>';
            if ((int) $row->advance_attachment_id) { echo '<p><a href="' . esc_url(IDLM_Private_Files::url((int) $row->advance_attachment_id)) . '" target="_blank" rel="noopener">View payment screenshot</a></p>'; }
            if (!$balance) { echo '<p class="idlm-design-muted">Not in Payments yet: this lead has no quotation payment terms. Set the plan from the Won amount in Payment details, then record this advance there.</p>' . self::payment_details_link($row->lead_id) . '</section>'; return; }
        }
        if ($balance) {
            $currency = $balance['currency'];
            echo '<dl class="idlm-design-facts"><div><dt>Price used for payments</dt><dd>' . esc_html(self::money($balance['basis_total'], $currency)) . '</dd></div><div><dt>Total received</dt><dd>' . esc_html(self::money($balance['total_received'], $currency)) . '</dd></div><div><dt>Total outstanding</dt><dd>' . esc_html(self::money($balance['total_outstanding'], $currency)) . '</dd></div>';
            if ((float) $balance['excess_credit'] > 0) { echo '<div><dt>Extra received (credit)</dt><dd>' . esc_html(self::money($balance['excess_credit'], $currency)) . '</dd></div>'; }
            echo '</dl><div class="idlm-table-wrapper"><table class="idlm-table idlm-design-milestones"><caption class="screen-reader-text">Payment milestones</caption><thead><tr><th scope="col">Milestone</th><th scope="col">Expected</th><th scope="col">Received</th><th scope="col">Pending</th></tr></thead><tbody>';
            foreach ((array) $balance['milestones'] as $milestone) { echo '<tr><td>' . esc_html($milestone['name']) . (!empty($milestone['advance']) ? ' <small>Advance</small>' : '') . '</td><td>' . esc_html(self::money($milestone['amount'], $currency)) . '</td><td>' . esc_html(self::money($milestone['received'], $currency)) . '</td><td>' . esc_html(self::money($milestone['pending'], $currency)) . '</td></tr>'; }
            echo '</tbody></table></div>';
        }
        if ($receipts) {
            echo '<details class="idlm-design-versions"><summary>Payments received (' . esc_html(count($receipts)) . ')</summary><ul class="idlm-design-revisions">';
            foreach (array_reverse($receipts) as $receipt) { echo '<li>' . esc_html(self::money($receipt->amount, $receipt->currency) . ' · ' . self::date($receipt->received_date) . ' · ' . $receipt->method) . '<small>' . esc_html($receipt->reference) . '</small>' . ((int) $receipt->attachment_id ? '<a href="' . esc_url(IDLM_Private_Files::url((int) $receipt->attachment_id)) . '" target="_blank" rel="noopener">View screenshot</a>' : '') . '</li>'; }
            echo '</ul></details>';
        }
        if ($balance && IDLM_Design_Handoffs::can_record_payment($row->lead_id)) { self::render_payment_form($row); }
        echo self::payment_details_link($row->lead_id) . '</section>';
    }

    /** Corrections, revocations, receipt history and old-terms recovery live on the lead's payment page. */
    private static function payment_details_link($lead_id) {
        if (!IDLM_Lead_Payment_Controller::can_open($lead_id)) { return ''; }
        return '<p><a href="' . esc_url(IDLM_Lead_Payment_Controller::details_url($lead_id)) . '">Payment details and corrections</a></p>';
    }

    private static function render_payment_form($row) {
        echo '<div class="idlm-design-quote-actions">' . IDLM_Design_Stage_Controller::dialog_button('idlm-design-pay-dialog', 'Record a payment') . '</div>';
        IDLM_Design_Stage_Controller::dialog_open('idlm-design-pay-dialog', 'Record a payment', 'It is applied to the oldest unpaid milestone first.', 'idlm_design_payment_record', $row, true);
        echo '<input type="hidden" name="payment_version" value="' . esc_attr(IDLM_Lead_Payments::version($row->lead_id)) . '"><input type="hidden" name="request_key" value="' . esc_attr(wp_generate_uuid4()) . '">';
        echo '<div class="idlm-design-dialog-grid"><div class="idlm-form-group"><label for="idlm-design-pay-amount">Amount received</label><input class="idlm-input" id="idlm-design-pay-amount" name="amount" inputmode="decimal" autocomplete="off" required></div><div class="idlm-form-group"><label for="idlm-design-pay-date">Date received</label><input class="idlm-input" type="date" id="idlm-design-pay-date" name="received_date" value="' . esc_attr(current_time('Y-m-d')) . '" required></div><div class="idlm-form-group"><label for="idlm-design-pay-method">Paid by</label><select class="idlm-input" id="idlm-design-pay-method" name="method">';
        foreach (array('UPI', 'Bank transfer', 'Cheque', 'Card', 'Cash') as $method) { echo '<option value="' . esc_attr($method) . '">' . esc_html($method) . '</option>'; }
        echo '</select></div><div class="idlm-form-group"><label for="idlm-design-pay-reference">Transaction reference</label><input class="idlm-input" id="idlm-design-pay-reference" name="reference" maxlength="255" autocomplete="off"></div></div><div class="idlm-form-group"><label for="idlm-design-pay-file">Or payment screenshot</label><input class="idlm-input" type="file" id="idlm-design-pay-file" name="payment_screenshot" accept="image/jpeg,image/png,image/webp"></div>';
        IDLM_Design_Stage_Controller::dialog_close('Record payment');
    }

    private static function hidden_fields($action, $row, $return) {
        echo '<input type="hidden" name="action" value="' . esc_attr($action) . '"><input type="hidden" name="handoff_id" value="' . esc_attr($row->id) . '"><input type="hidden" name="return_url" value="' . esc_attr($return) . '">';
        wp_nonce_field($action);
    }

    private static function designer_fields($row, $optional) {
        echo '<label for="idlm-design-designer">Designer</label><select class="idlm-input" id="idlm-design-designer" name="designer_id"' . ($optional ? '' : ' required') . '>';
        echo $optional ? '<option value="">Assign later</option>' : '<option value="">Choose a designer</option>';
        foreach (IDLM_Design_Handoffs::designers() as $user) { echo '<option value="' . esc_attr($user->ID) . '"' . selected((int) $user->ID, (int) $row->designer_id, false) . '>' . esc_html($user->display_name) . '</option>'; }
        echo '</select><label for="idlm-design-target">Target completion date</label><input class="idlm-input" type="date" id="idlm-design-target" name="target_date" value="' . esc_attr((string) $row->target_date) . '"' . ($optional ? ' aria-describedby="idlm-design-target-help"' : ' required') . '>';
        if ($optional) { echo '<small id="idlm-design-target-help">Required when you choose a designer.</small>'; }
    }

    private static function render_actions($row, $lead) {
        // Return to the page that rendered this form, whichever URL hosts the workspace.
        $return = remove_query_arg(array('design_notice', 'design_error', 'handoff'), wp_unslash($_SERVER['REQUEST_URI'] ?? ''));
        $post = esc_url(admin_url('admin-post.php')); $status = $row->status;
        echo '<aside class="idlm-card idlm-design-actions" aria-label="Handoff actions">';
        if ((int) $row->designer_id) { echo '<p class="idlm-design-muted">Designer: ' . esc_html(self::person($row->designer_id)) . ($row->target_date ? '. Target: ' . esc_html(self::date($row->target_date)) : '') . '.</p>'; }
        if (!IDLM_Design_Handoffs::can_decide($row)) { echo '</aside>'; return; }
        if (IDLM_Design_Handoffs::STATUS_SENT_BACK === $status) { echo '<p class="idlm-design-muted">Waiting for sales to fix this and resend it.</p></aside>'; return; }
        if (IDLM_Design_Handoffs::STATUS_NEW === $status) {
            if ('Won' === $lead->stage) {
                echo '<form method="post" action="' . $post . '">'; self::hidden_fields('idlm_design_handoff_accept', $row, $return); self::designer_fields($row, true);
                echo '<button class="idlm-btn idlm-btn-primary">Accept</button></form>';
            } else { echo '<p class="idlm-design-card-warning">This lead is no longer Won, so it cannot be accepted. Return it or talk to sales.</p>'; }
            echo '<div class="idlm-design-back">' . IDLM_Design_Stage_Controller::dialog_button('idlm-design-return-dialog', 'Return to sales') . '</div></aside>';
            IDLM_Design_Stage_Controller::dialog_open('idlm-design-return-dialog', 'Return to sales', 'Sales fixes the handoff and sends it again.', 'idlm_design_handoff_send_back', $row);
            echo '<div class="idlm-form-group"><label for="idlm-design-reason">What needs fixing?</label><textarea class="idlm-input" id="idlm-design-reason" name="reason" rows="3" maxlength="2000" required></textarea></div>';
            IDLM_Design_Stage_Controller::dialog_close('Return to sales');
            return;
        }
        if ('Won' !== $lead->stage) { echo '<p class="idlm-design-card-warning">This lead is no longer Won. Review it with sales.</p>'; }
        echo '<form method="post" action="' . $post . '">'; self::hidden_fields('idlm_design_handoff_assign', $row, $return); self::designer_fields($row, false);
        if ((int) $row->designer_id) { echo '<label for="idlm-design-reassign">Reason, if changing the designer</label><textarea class="idlm-input" id="idlm-design-reassign" name="reason" rows="2" maxlength="2000"></textarea>'; }
        echo '<button class="idlm-btn idlm-btn-primary">' . ((int) $row->designer_id ? 'Save designer' : 'Assign designer') . '</button></form></aside>';
    }
}
