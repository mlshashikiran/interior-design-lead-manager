<?php
/**
 * A project's quick panel, opened from the projects table and the board. It uses the lead
 * popup's structure (header, status strip, titled panels), so both read the same way.
 * Read-only apart from the stage move the board already offers; everything else stays on the
 * full project page, which the panel links to. Builder: Shashi.
 */
if (!defined('ABSPATH')) { exit; }

final class IDLM_Design_Project_Panel {
    const NONCE = 'idlm_design_project_panel';
    const DIALOG = 'idlm-design-project-panel';

    public static function init() {
        add_action('wp_ajax_idlm_design_project_panel', array(__CLASS__, 'ajax_panel'));
    }

    /** One shell per page; the script fills it. Pages that list projects call this once. */
    public static function shell() {
        static $printed = false;
        if ($printed) { return; }
        $printed = true;
        wp_enqueue_script('idlm-design-panel', IDLM_URL . 'assets/js/design-panel.js', array('idlm-dialog'), (string) filemtime(IDLM_PATH . 'assets/js/design-panel.js'), true);
        echo '<div class="idlm-drawer idlm-design-project-drawer" id="' . esc_attr(self::DIALOG) . '" role="dialog" aria-modal="true" aria-labelledby="' . esc_attr(self::DIALOG) . '-title" aria-hidden="true" hidden tabindex="-1" data-idlm-dialog data-idlm-backdrop-close="true" data-ajax-url="' . esc_url(admin_url('admin-ajax.php')) . '" data-nonce="' . esc_attr(wp_create_nonce(self::NONCE)) . '" data-move-nonce="' . esc_attr(wp_create_nonce('idlm_design_stage_action')) . '"><div class="idlm-drawer-content-wrapper" tabindex="-1" data-design-panel-content></div></div>';
    }

    // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom IDLM tables named from $wpdb->prefix; values are bound with prepare(), cast to int, or come from fixed clause builders and generated placeholder lists; request-scoped reads and transactional writes are deliberately uncached. Builder: Shashi.
    // phpcs:disable WordPress.Security.ValidatedSanitizedInput.MissingUnslash,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- POST handlers verify the nonce (check_admin_referer or check_ajax_referer) and capability before acting, reading the action name first only to choose that nonce; GET values are display-only routing, cast or sanitized before use; uploads are validated by IDLM_Private_Files::upload(). Builder: Shashi.
    public static function ajax_panel() {
        check_ajax_referer(self::NONCE, 'nonce');
        $id = isset($_POST['handoff_id']) && is_string($_POST['handoff_id']) && ctype_digit($_POST['handoff_id']) ? (int) $_POST['handoff_id'] : 0;
        $row = $id ? IDLM_Design_Handoffs::find($id) : null;
        global $wpdb;
        $lead = $row && IDLM_Design_Handoffs::can_see($row) ? $wpdb->get_row($wpdb->prepare("SELECT id, first_name, last_name, phone, email, project_name, property_location, preferred_studio, stage FROM {$wpdb->prefix}idlm_leads WHERE id = %d", $row->lead_id)) : null;
        // The same answer for missing and out of scope, so an ID reveals nothing.
        if (!$lead) { wp_send_json_error(array('message' => 'This project is not available to you.'), 404); }
        wp_send_json_success(array('html' => self::render($row, $lead)));
    }
    // phpcs:enable WordPress.Security.ValidatedSanitizedInput.MissingUnslash,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
    // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching

    /** Pure markup from a visible row and its lead. */
    public static function render($row, $lead) {
        $c = 'IDLM_Design_Handoff_Controller';
        $url = add_query_arg(array('idlm_view' => $c::VIEW, 'handoff' => (int) $row->id), IDLM_Admin_Shortcodes::workspace_url());
        $accepted = IDLM_Design_Handoffs::STATUS_ACCEPTED === $row->status; $stages = IDLM_Design_Stages::stages();
        $name = trim($lead->first_name . ' ' . $lead->last_name);
        $items = $accepted && !$row->completed_at ? IDLM_Design_Stages::checklist($row) : array();
        $html = self::header($row, $lead, $name, $accepted, $stages);
        $html .= '<div class="idlm-drawer-body"><div class="idlm-design-panel-grid">';
        $html .= self::section('Next step', self::next_block($row, $items));
        $html .= self::section('Price and payments', self::facts(self::money_facts($row)));
        $project = array();
        foreach (array('project_name' => 'Project', 'property_location' => 'Location', 'preferred_studio' => 'Studio', 'email' => 'Email') as $field => $label) { if (!empty($lead->$field)) { $project[$label] = esc_html($lead->$field); } }
        $project['Sales owner'] = esc_html((int) $row->sales_owner_id ? $c::person($row->sales_owner_id) : 'Unassigned');
        $project['Sent to design'] = esc_html($c::date($row->sent_at) . ' by ' . $c::person($row->sent_by));
        $html .= self::section('Project', self::facts($project));
        $html .= '</div></div><footer class="idlm-design-panel-footer">';
        if ($accepted && IDLM_Design_Revisions::can_edit($row) && (int) $row->baseline_revision_id) { $html .= '<a class="idlm-btn idlm-btn-secondary" href="' . esc_url(add_query_arg('design_quote', 'edit', $url)) . '">Edit quotation</a>'; }
        $next = $accepted && !$row->completed_at ? IDLM_Design_Stages::neighbour(IDLM_Design_Stages::stage_of($row), 1) : '';
        if ($next && !$row->hold_at && IDLM_Design_Revisions::can_edit($row)) {
            // Same rule as the project page: the move waits until the checklist is met.
            $ready = !IDLM_Design_Stages::unmet($items);
            $html .= '<button type="button" class="idlm-btn idlm-btn-secondary"' . ($ready ? '' : ' disabled title="Finish the checklist first"') . ' data-design-panel-move data-handoff="' . esc_attr($row->id) . '" data-stage="' . esc_attr(IDLM_Design_Stages::stage_of($row)) . '">' . esc_html('Move to ' . $stages[$next]['label']) . '</button>';
        }
        return $html . '<a class="idlm-btn idlm-btn-primary" href="' . esc_url($url) . '">Open full project</a></footer>';
    }

    /** Identity on the left, then the status strip, as in the lead popup. */
    private static function header($row, $lead, $name, $accepted, $stages) {
        $c = 'IDLM_Design_Handoff_Controller';
        $contact = array();
        if ('' !== (string) $lead->phone) { $contact[] = '<a href="tel:' . esc_attr(preg_replace('/[^0-9+]/', '', (string) $lead->phone)) . '">' . esc_html($lead->phone) . '</a>'; }
        if (!empty($lead->project_name)) { $contact[] = '<span>' . esc_html($lead->project_name) . '</span>'; }
        $badges = '';
        if ('Won' !== $lead->stage && IDLM_Design_Handoffs::STATUS_SENT_BACK !== $row->status) { $badges .= '<span class="idlm-badge is-warning">Lead no longer Won</span>'; }
        if ($accepted && !$row->completed_at && $row->target_date && $row->target_date < current_time('Y-m-d')) { $badges .= '<span class="idlm-badge is-danger">Overdue</span>'; }
        $status = array(
            'Status' => self::status_text($row),
            'Stage' => $accepted ? ($row->completed_at ? 'Complete' : $stages[IDLM_Design_Stages::stage_of($row)]['label']) : '—',
            'Designer' => (int) $row->designer_id ? $c::person($row->designer_id) : ($accepted ? 'Not assigned' : '—'),
            'Target date' => $row->target_date ? $c::date($row->target_date) : ($accepted ? 'Not set' : '—'),
        );
        $html = '<header class="idlm-drawer-header"><div class="idlm-drawer-identity"><div class="idlm-drawer-avatar" aria-hidden="true">' . esc_html(self::initial($lead->first_name)) . '</div><div class="idlm-drawer-identity-text"><h2 id="' . esc_attr(self::DIALOG) . '-title">' . esc_html($name) . '</h2>' . $badges . ($contact ? '<div class="idlm-drawer-contact">' . implode('', $contact) . '</div>' : '') . '</div></div>';
        $html .= '<div class="idlm-drawer-status-grid" role="group" aria-label="Project status summary"><div class="idlm-drawer-status-primary">';
        foreach ($status as $label => $value) { $html .= '<div class="idlm-drawer-status-item"><span class="idlm-drawer-status-label">' . esc_html($label) . '</span><span class="idlm-drawer-status-value">' . esc_html($value) . '</span></div>'; }
        $html .= '</div><div class="idlm-drawer-status-context"><span class="idlm-drawer-status-context-item"><span class="idlm-drawer-status-context-label">Design Manager:</span> <span class="idlm-drawer-status-context-value">' . esc_html((int) $row->design_manager_id ? $c::person($row->design_manager_id) : 'Not chosen') . '</span></span></div></div>';
        return $html . '<button type="button" class="idlm-close" data-idlm-dialog-close aria-label="Close project">&times;</button></header>';
    }

    private static function initial($first_name) {
        $first = function_exists('mb_substr') ? mb_substr(trim((string) $first_name), 0, 1) : substr(trim((string) $first_name), 0, 1);
        return '' === $first ? '?' : (function_exists('mb_strtoupper') ? mb_strtoupper($first) : strtoupper($first));
    }

    private static function status_text($row) {
        if (IDLM_Design_Handoffs::STATUS_NEW === $row->status) { return 'New handoff'; }
        if (IDLM_Design_Handoffs::STATUS_SENT_BACK === $row->status) { return 'Returned to sales'; }
        if ($row->completed_at) { return 'Complete'; }
        return $row->hold_at ? 'On hold' : 'In progress';
    }

    /** What happens next: the stage checklist, the hold, or the handoff decision. */
    private static function next_block($row, $items) {
        $c = 'IDLM_Design_Handoff_Controller';
        if (IDLM_Design_Handoffs::STATUS_NEW === $row->status) { return self::note('Waiting for the Design Manager to accept it.') . $c::submission_stack($row); }
        if (IDLM_Design_Handoffs::STATUS_SENT_BACK === $row->status) { return self::note('Returned to sales: ' . $row->send_back_reason); }
        if ($row->completed_at) { return self::note('Completed on ' . $c::date($row->completed_at) . '.'); }
        $html = $row->hold_at ? self::note('On hold: ' . $row->hold_reason . ($row->hold_resume_date ? ' Expected back ' . $c::date($row->hold_resume_date) . '.' : '')) : '';
        foreach (IDLM_Design_Stages::open_flags($row) as $flag) { $html .= '<p class="idlm-design-note"><span class="idlm-badge is-warning">Review</span> ' . esc_html($flag . ' no longer holds.') . '</p>'; }
        if (!$items) { return $html . self::note('Nothing is needed before the next stage.'); }
        $html .= '<ul class="idlm-design-checklist">';
        foreach ($items as $item) { $html .= '<li><span class="idlm-badge ' . ($item['met'] ? 'is-success">Done' : 'is-neutral">Needed') . '</span> ' . esc_html($item['label']) . ($item['detail'] ? ' <small>' . esc_html($item['detail']) . '</small>' : '') . '</li>'; }
        return $html . '</ul>';
    }

    private static function money_facts($row) {
        $c = 'IDLM_Design_Handoff_Controller'; $facts = array();
        $sales = (int) $row->baseline_revision_id ? IDLM_Quotation_Repository::get_revision((int) $row->baseline_revision_id) : null;
        $currency = $sales ? $sales->currency_code : ($row->advance_currency ?: 'INR');
        $facts['Sales price'] = $sales ? esc_html($c::money($row->baseline_total, $currency)) : 'No quotation';
        if ((int) $row->agreed_revision_id) { $facts['Agreed price'] = esc_html($c::money($row->agreed_total, $currency)); }
        $latest = (int) $row->design_revision_id ? IDLM_Quotation_Repository::get_revision((int) $row->design_revision_id) : null;
        if ($latest && (int) $latest->id !== (int) $row->agreed_revision_id) { $facts['Latest design price'] = esc_html($c::money($latest->grand_total, $currency)) . (IDLM_Design_Revisions::is_reviewed(IDLM_Design_Revisions::meta($latest->id)) ? '' : ' <span class="idlm-badge is-warning">Waiting for review</span>'); }
        $balance = IDLM_Design_Handoffs::balance($row->lead_id);
        if ($balance) {
            $facts['Received'] = esc_html($c::money($balance['total_received'], $balance['currency']));
            $facts['Outstanding'] = esc_html($c::money($balance['total_outstanding'], $balance['currency']));
        } else { $facts['Payments'] = 'No payment schedule yet'; }
        return $facts;
    }

    private static function section($title, $body) {
        return '<section class="idlm-drawer-panel"><div class="idlm-drawer-panel-head"><h3 class="idlm-drawer-panel-title">' . esc_html($title) . '</h3></div><div class="idlm-design-panel-section">' . $body . '</div></section>';
    }

    private static function note($text) { return '<p class="idlm-design-note">' . esc_html($text) . '</p>'; }

    /** Values are already escaped markup. */
    private static function facts($facts) {
        $html = '<dl class="idlm-design-panel-facts">';
        foreach ($facts as $label => $value) { $html .= '<div><dt>' . esc_html($label) . '</dt><dd>' . $value . '</dd></div>'; }
        return $html . '</dl>';
    }
}
