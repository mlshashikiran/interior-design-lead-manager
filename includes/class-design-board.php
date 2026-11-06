<?php
/** Design projects as a board: same scope, same data, same server checks as the table. Builder: Shashi. */
if (!defined('ABSPATH')) { exit; }

final class IDLM_Design_Board {
    const BOARD_LIMIT = 300;

    public static function init() {
        add_action('wp_ajax_idlm_design_stage_move', array(__CLASS__, 'ajax_move'));
    }

    /** Read filters from the request into known values only. */
    public static function filters($input) {
        $text = static function($key) use ($input) { return isset($input[$key]) && is_string($input[$key]) ? sanitize_text_field(wp_unslash($input[$key])) : ''; };
        $stage = sanitize_key($text('stage'));
        $target = sanitize_key($text('target'));
        $state = sanitize_key($text('state'));
        $designer = $text('designer');
        return array('q' => substr($text('q'), 0, 100), 'stage' => isset(IDLM_Design_Stages::stages()[$stage]) ? $stage : '', 'target' => in_array($target, array('overdue', 'week', 'none'), true) ? $target : '', 'state' => in_array($state, array('hold', 'complete'), true) ? $state : '', 'designer' => ctype_digit($designer) ? (int) $designer : 0);
    }

    /** Extra WHERE clauses. Scope is applied separately and always. */
    public static function where($filters, $today) {
        global $wpdb; $clauses = array($wpdb->prepare('h.status = %s', IDLM_Design_Handoffs::STATUS_ACCEPTED));
        $clauses[] = 'complete' === $filters['state'] ? 'h.completed_at IS NOT NULL' : 'h.completed_at IS NULL';
        if ('hold' === $filters['state']) { $clauses[] = 'h.hold_at IS NOT NULL'; }
        return implode(' AND ', array_merge($clauses, self::narrow($filters, $today)));
    }

    /** Search, stage, designer and target clauses, shared with the projects table. */
    public static function narrow($filters, $today) {
        global $wpdb; $clauses = array();
        // Only accepted projects have a stage; a new handoff never matches a stage filter.
        if ($filters['stage']) { $clauses[] = $wpdb->prepare('h.status = %s', IDLM_Design_Handoffs::STATUS_ACCEPTED) . ' AND ' . (IDLM_Design_Stages::first() === $filters['stage'] ? $wpdb->prepare("(h.stage_key = '' OR h.stage_key = %s)", $filters['stage']) : $wpdb->prepare('h.stage_key = %s', $filters['stage'])); }
        if ($filters['designer']) { $clauses[] = $wpdb->prepare('h.designer_id = %d', $filters['designer']); }
        if ('overdue' === $filters['target']) { $clauses[] = $wpdb->prepare('h.target_date < %s', $today); }
        if ('week' === $filters['target']) { $clauses[] = $wpdb->prepare('h.target_date BETWEEN %s AND %s', $today, gmdate('Y-m-d', strtotime($today . ' +7 days'))); }
        if ('none' === $filters['target']) { $clauses[] = 'h.target_date IS NULL'; }
        if ('' !== $filters['q']) {
            $like = '%' . $wpdb->esc_like($filters['q']) . '%';
            $clauses[] = $wpdb->prepare("(CONCAT_WS(' ', l.first_name, l.last_name) LIKE %s OR l.phone LIKE %s OR l.project_name LIKE %s)", $like, $like, $like);
        }
        return $clauses;
    }

    // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter -- Custom IDLM tables named from $wpdb->prefix; values are bound with prepare(), cast to int, or come from fixed clause builders and generated placeholder lists; request-scoped reads and transactional writes are deliberately uncached. Builder: Shashi.
    /** Projects the current person can see. Returns rows and the true total. */
    public static function query($filters, $limit, $offset = 0) {
        global $wpdb; $table = $wpdb->prefix . 'idlm_design_handoffs'; $leads = $wpdb->prefix . 'idlm_leads';
        $where = IDLM_Design_Handoffs::scope_sql('h') . ' AND ' . self::where($filters, current_time('Y-m-d'));
        $total = (int) $wpdb->get_var("SELECT COUNT(*) FROM $table h INNER JOIN $leads l ON l.id = h.lead_id WHERE $where");
        $rows = (array) $wpdb->get_results($wpdb->prepare("SELECT h.*, l.first_name, l.last_name, l.phone, l.project_name, l.stage AS lead_stage FROM $table h INNER JOIN $leads l ON l.id = h.lead_id WHERE $where ORDER BY h.target_date IS NULL, h.target_date ASC, h.id ASC LIMIT %d OFFSET %d", (int) $limit, (int) $offset));
        return array('rows' => $rows, 'total' => $total);
    }
    // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter

    /** Pure: how a target date reads today. */
    public static function target_state($target, $today, $completed = false) {
        if (!$target) { return 'none'; }
        if ($completed) { return 'set'; }
        if ($target < $today) { return 'overdue'; }
        return $target <= gmdate('Y-m-d', strtotime($today . ' +7 days')) ? 'soon' : 'set';
    }

    private static function next_step($row, $context) {
        if ($row->completed_at) { return 'Complete'; }
        if ($row->hold_at) { return 'On hold: ' . $row->hold_reason; }
        $missing = IDLM_Design_Stages::unmet(IDLM_Design_Stages::checklist($row, null, $context));
        if ($missing) { return 'Needs: ' . implode(', ', array_column($missing, 'label')); }
        return IDLM_Design_Stages::neighbour(IDLM_Design_Stages::stage_of($row), 1) ? 'Ready to move on' : 'Ready to complete';
    }

    /** Everything a card shows, from one bulk read rather than a query per card. */
    public static function card($row, $context, $today) {
        $base = add_query_arg('idlm_view', IDLM_Design_Handoff_Controller::VIEW, IDLM_Admin_Shortcodes::workspace_url());
        $balance = $context['balance'];
        return array('name' => trim($row->first_name . ' ' . $row->last_name), 'url' => add_query_arg('handoff', $row->id, $base), 'next' => self::next_step($row, $context),
            'pending' => $balance ? IDLM_Design_Handoff_Controller::money($balance['total_outstanding'], $balance['currency']) : 'No schedule',
            'designer' => (int) $row->designer_id ? IDLM_Design_Handoff_Controller::person($row->designer_id) : '', 'target' => $row->target_date ? IDLM_Design_Handoff_Controller::date($row->target_date) : '',
            'target_state' => self::target_state($row->target_date, $today, !empty($row->completed_at)), 'flags' => count(IDLM_Design_Stages::open_flags($row, $context)), 'unreviewed' => !empty($context['unreviewed']));
    }

    // phpcs:disable WordPress.Security.NonceVerification.Recommended -- POST handlers verify the nonce (check_admin_referer or check_ajax_referer) and capability before acting, reading the action name first only to choose that nonce; GET values are display-only routing, cast or sanitized before use; uploads are validated by IDLM_Private_Files::upload(). Builder: Shashi.
    /** The board. Designers see only their own projects (scope); a manager sees all of theirs. */
    public static function render($base) {
        $filters = self::filters($_GET); $today = current_time('Y-m-d');
        self::render_filters($base, $filters);
        self::render_board($filters, $today);
    }
    // phpcs:enable WordPress.Security.NonceVerification.Recommended

    private static function render_filters($base, $filters) {
        $hidden = array(); parse_str((string) wp_parse_url($base, PHP_URL_QUERY), $hidden); $hidden['tab'] = 'board';
        echo '<form class="idlm-design-filters" method="get" action="' . esc_url(strtok($base, '?')) . '" role="search" aria-label="Filter design projects">';
        foreach ($hidden as $key => $value) { echo '<input type="hidden" name="' . esc_attr($key) . '" value="' . esc_attr($value) . '">'; }
        echo '<label><span>Search</span><input class="idlm-input" type="search" name="q" value="' . esc_attr($filters['q']) . '" placeholder="Name, phone or project"></label>';
        echo '<label><span>Target date</span><select class="idlm-input" name="target"><option value="">Any date</option>';
        foreach (array('overdue' => 'Overdue', 'week' => 'Due in 7 days', 'none' => 'No target date') as $key => $label) { echo '<option value="' . esc_attr($key) . '"' . selected($filters['target'], $key, false) . '>' . esc_html($label) . '</option>'; }
        echo '</select></label><label><span>Status</span><select class="idlm-input" name="state"><option value="">Active</option><option value="hold"' . selected($filters['state'], 'hold', false) . '>On hold</option></select></label>';
        if (IDLM_Design_Handoffs::can_manage()) {
            echo '<label><span>Designer</span><select class="idlm-input" name="designer"><option value="">Everyone</option>';
            foreach (IDLM_Design_Handoffs::designers() as $user) { echo '<option value="' . esc_attr($user->ID) . '"' . selected($filters['designer'], (int) $user->ID, false) . '>' . esc_html($user->display_name) . '</option>'; }
            echo '</select></label>';
        }
        echo '<button class="idlm-btn idlm-btn-secondary">Apply</button>';
        if (array_filter($filters)) { echo '<a class="idlm-design-clear" href="' . esc_url(add_query_arg('tab', 'board', $base)) . '">Clear</a>'; }
        echo '</form>';
    }

    private static function empty_state($filters) {
        echo '<div class="idlm-state is-empty"><strong>' . (array_filter($filters) ? 'No projects match these filters' : 'No active projects') . '</strong><span>' . (array_filter($filters) ? 'Try clearing a filter.' : 'Projects appear here once a handoff is accepted.') . '</span></div>';
    }

    private static function render_board($filters, $today) {
        if ('complete' === $filters['state']) { $filters['state'] = ''; }
        $result = self::query($filters, self::BOARD_LIMIT);
        if (!$result['rows']) { self::empty_state($filters); return; }
        $contexts = IDLM_Design_Stages::contexts($result['rows']); $columns = array();
        foreach ($result['rows'] as $row) { $columns[IDLM_Design_Stages::stage_of($row)][] = $row; }
        wp_enqueue_script('idlm-design-board', IDLM_URL . 'assets/js/design-board.js', array('idlm-dialog'), IDLM_Admin_Assets::version('js/design-board.js'), true);
        if ($result['total'] > self::BOARD_LIMIT) { echo '<p class="idlm-design-note">Showing the ' . esc_html(self::BOARD_LIMIT) . ' earliest due of ' . esc_html($result['total']) . ' projects. Use a filter or the Projects table to see the rest.</p>'; }
        echo '<div class="idlm-design-board" role="region" aria-label="Design projects by stage" tabindex="0" data-ajax-url="' . esc_url(admin_url('admin-ajax.php')) . '" data-nonce="' . esc_attr(wp_create_nonce('idlm_design_stage_action')) . '">';
        // Removed stages show only while a project still sits in one.
        foreach (IDLM_Design_Stages::shown(array_keys($columns)) as $key => $stage) {
            $cards = $columns[$key] ?? array();
            $name = $stage['label'] . ($stage['archived'] ? ' (removed)' : '');
            echo '<section class="idlm-design-column" data-stage="' . esc_attr($key) . '"' . ($stage['archived'] ? ' data-archived="1"' : '') . ' aria-label="' . esc_attr($name) . '"><h2>' . esc_html($name) . ' <span>' . esc_html(count($cards)) . '</span></h2><ul>';
            foreach ($cards as $row) { self::render_card($row, $contexts[(int) $row->lead_id], $today, $key); }
            echo '</ul></section>';
        }
        echo '</div>';
        IDLM_Design_Project_Panel::shell();
    }

    // phpcs:disable WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- POST handlers verify the nonce (check_admin_referer or check_ajax_referer) and capability before acting, reading the action name first only to choose that nonce; GET values are display-only routing, cast or sanitized before use; uploads are validated by IDLM_Private_Files::upload(). Builder: Shashi.
    private static function render_card($row, $context, $today, $stage_key) {
        $card = self::card($row, $context, $today); $stages = IDLM_Design_Stages::stages(); $next = IDLM_Design_Stages::neighbour($stage_key, 1);
        $can_move = $next && !$row->hold_at && IDLM_Design_Revisions::can_edit($row);
        echo '<li class="idlm-design-card-item" data-handoff="' . esc_attr($row->id) . '" data-stage="' . esc_attr($stage_key) . '" data-url="' . esc_url($card['url']) . '"' . ($can_move ? ' draggable="true"' : '') . '><a class="idlm-design-card-title" href="' . esc_url($card['url']) . '" data-design-panel="' . esc_attr($row->id) . '">' . esc_html($card['name']) . '</a><small>' . esc_html($row->project_name) . '</small><p>' . esc_html($card['next']) . '</p><dl><div><dt>Designer</dt><dd>' . esc_html($card['designer'] ?: 'Not assigned') . '</dd></div><div><dt>Target</dt><dd>' . esc_html($card['target'] ?: 'Not set') . '</dd></div><div><dt>Pending</dt><dd>' . esc_html($card['pending']) . '</dd></div></dl>';
        self::badges($row, $card);
        if ($can_move) {
            echo '<form class="idlm-design-move" method="post" action="' . esc_url(admin_url('admin-post.php')) . '"><input type="hidden" name="action" value="idlm_design_stage_action"><input type="hidden" name="stage_action" value="advance"><input type="hidden" name="handoff_id" value="' . esc_attr($row->id) . '"><input type="hidden" name="from_stage" value="' . esc_attr($stage_key) . '"><input type="hidden" name="return_url" value="' . esc_attr(remove_query_arg(array('design_notice', 'design_error', 'handoff', 'project'), wp_unslash($_SERVER['REQUEST_URI'] ?? ''))) . '">';
            wp_nonce_field('idlm_design_stage_action', '_wpnonce', false);
            echo '<button class="idlm-btn idlm-btn-secondary">' . esc_html('Move to ' . $stages[$next]['label']) . '</button></form>';
        }
        echo '</li>';
    }
    // phpcs:enable WordPress.Security.ValidatedSanitizedInput.InputNotSanitized

    public static function badges($row, $card) {
        $badges = array();
        if ('overdue' === $card['target_state']) { $badges[] = array('is-danger', 'Overdue'); }
        if ('soon' === $card['target_state']) { $badges[] = array('is-warning', 'Due soon'); }
        if ($row->hold_at) { $badges[] = array('is-warning', 'On hold'); }
        if ($card['flags']) { $badges[] = array('is-warning', 'Review'); }
        if (!empty($card['unreviewed'])) { $badges[] = array('is-info', 'Quote to review'); }
        if ('Won' !== $row->lead_stage) { $badges[] = array('is-warning', 'Lead no longer Won'); }
        if (!$badges) { return; }
        echo '<div class="idlm-design-badges">';
        foreach ($badges as $badge) { echo '<span class="idlm-badge ' . esc_attr($badge[0]) . '">' . esc_html($badge[1]) . '</span> '; }
        echo '</div>';
    }

    /** Same check as the project page. A refused move leaves the card where it is. */
    public static function ajax_move() {
        check_ajax_referer('idlm_design_stage_action', 'nonce');
        $id = absint($_POST['handoff_id'] ?? 0); $from = sanitize_key(wp_unslash($_POST['from_stage'] ?? ''));
        $row = IDLM_Design_Handoffs::find($id);
        if (!$row || !IDLM_Design_Handoffs::can_see($row)) { wp_send_json_error(array('message' => 'You do not have permission to move this project.'), 403); }
        $result = IDLM_Recovery_Guard::run(static function() use ($id, $from) { return IDLM_Design_Stages::advance($id, $from); });
        if (true === $result) { wp_send_json_success(array('message' => 'Stage updated.')); }
        wp_send_json_error(array('message' => is_wp_error($result) ? $result->get_error_message() : 'The CRM is busy. Try again shortly.', 'code' => is_wp_error($result) ? $result->get_error_code() : 'busy'), 422);
    }
}
