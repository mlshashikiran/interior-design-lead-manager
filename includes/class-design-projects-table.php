<?php
/**
 * Design projects as one table: new handoffs and active projects together, with the
 * designer and target date set in the row. Same scope and server checks as the project page.
 * Builder: Shashi.
 */
if (!defined('ABSPATH')) { exit; }

final class IDLM_Design_Projects_Table {
    const PER_PAGE = 50;

    /** Queue chips. Designers only ever see their own accepted projects, so intake queues are for managers. */
    public static function queues($manager) {
        $queues = array('' => 'Active', 'new' => 'New', 'unassigned' => 'Needs a designer', 'overdue' => 'Overdue', 'week' => 'Due in 7 days', 'hold' => 'On hold', 'returned' => 'Returned', 'complete' => 'Complete');
        if (!$manager) { unset($queues['new'], $queues['unassigned'], $queues['returned']); }
        return $queues;
    }

    /** A known queue, or the one an older link meant (tab=new, state=hold, target=overdue). */
    public static function queue($input, $manager) {
        $read = static function($key) use ($input) { return isset($input[$key]) && is_string($input[$key]) ? sanitize_key(wp_unslash($input[$key])) : ''; };
        $queue = $read('queue');
        if ('' === $queue) {
            $older = array('tab' => array('new' => 'new', 'unassigned' => 'unassigned', 'returned' => 'returned'), 'state' => array('hold' => 'hold', 'complete' => 'complete'), 'target' => array('overdue' => 'overdue', 'week' => 'week'));
            foreach ($older as $key => $map) { if (isset($map[$read($key)])) { $queue = $map[$read($key)]; break; } }
        }
        return isset(self::queues($manager)[$queue]) ? $queue : '';
    }

    /** Which handoffs a queue holds. Scope is applied separately and always. */
    public static function queue_sql($queue, $today) {
        global $wpdb;
        $accepted = $wpdb->prepare('h.status = %s', IDLM_Design_Handoffs::STATUS_ACCEPTED); $open = "$accepted AND h.completed_at IS NULL";
        $new = $wpdb->prepare('h.status = %s', IDLM_Design_Handoffs::STATUS_NEW);
        switch ($queue) {
            case 'new': return $new;
            case 'unassigned': return "$open AND h.designer_id = 0";
            case 'overdue': return "$open AND " . $wpdb->prepare('h.target_date < %s', $today);
            case 'week': return "$open AND " . $wpdb->prepare('h.target_date BETWEEN %s AND %s', $today, gmdate('Y-m-d', strtotime($today . ' +7 days')));
            case 'hold': return "$open AND h.hold_at IS NOT NULL";
            case 'returned': return $wpdb->prepare('h.status = %s', IDLM_Design_Handoffs::STATUS_SENT_BACK);
            case 'complete': return "$accepted AND h.completed_at IS NOT NULL";
        }
        return "($new OR ($open))";
    }

    public static function render($base) {
        global $wpdb; $table = $wpdb->prefix . 'idlm_design_handoffs'; $leads = $wpdb->prefix . 'idlm_leads';
        $manager = IDLM_Design_Handoffs::can_manage(); $today = current_time('Y-m-d');
        $queue = self::queue($_GET, $manager); $filters = IDLM_Design_Board::filters($_GET);
        // The queue replaces the status filter; a "no target date" link from Overview still narrows.
        $filters['state'] = ''; if ('none' !== $filters['target']) { $filters['target'] = ''; }
        $scope = IDLM_Design_Handoffs::scope_sql('h');
        self::render_filters($base, $queue, $filters, $manager);
        echo '<nav class="idlm-design-queues" aria-label="Project queues">';
        foreach (self::queues($manager) as $key => $label) {
            $count = (int) $wpdb->get_var("SELECT COUNT(*) FROM $table h WHERE $scope AND " . self::queue_sql($key, $today));
            $link = add_query_arg(array_merge(self::kept($filters), array('tab' => 'projects', 'queue' => $key)), $base);
            echo '<a href="' . esc_url($link) . '"' . ($key === $queue ? ' class="is-active" aria-current="true"' : '') . '>' . esc_html($label) . ' <span>' . esc_html($count) . '</span></a> ';
        }
        echo '</nav>';
        $narrow = IDLM_Design_Board::narrow($filters, $today);
        $where = $scope . ' AND ' . self::queue_sql($queue, $today) . ($narrow ? ' AND ' . implode(' AND ', $narrow) : '');
        $page = max(1, min(1000, isset($_GET['design_page']) && is_string($_GET['design_page']) && ctype_digit($_GET['design_page']) ? (int) $_GET['design_page'] : 1));
        $total = (int) $wpdb->get_var("SELECT COUNT(*) FROM $table h INNER JOIN $leads l ON l.id = h.lead_id WHERE $where");
        // New handoffs first, oldest waiting at the top; then the earliest target date.
        $rows = (array) $wpdb->get_results($wpdb->prepare("SELECT h.*, l.first_name, l.last_name, l.phone, l.project_name, l.stage AS lead_stage FROM $table h INNER JOIN $leads l ON l.id = h.lead_id WHERE $where ORDER BY FIELD(h.status, %s, %s, %s), CASE WHEN h.status = %s THEN h.sent_at END ASC, h.target_date IS NULL, h.target_date ASC, h.id ASC LIMIT %d OFFSET %d", IDLM_Design_Handoffs::STATUS_NEW, IDLM_Design_Handoffs::STATUS_ACCEPTED, IDLM_Design_Handoffs::STATUS_SENT_BACK, IDLM_Design_Handoffs::STATUS_NEW, self::PER_PAGE, ($page - 1) * self::PER_PAGE));
        if (!$rows) { self::empty_state($queue, (bool) array_filter($filters)); return; }
        $contexts = IDLM_Design_Stages::contexts($rows); $designers = $manager ? IDLM_Design_Handoffs::designers() : array();
        wp_enqueue_script('idlm-design-table', IDLM_URL . 'assets/js/design-table.js', array('idlm-dialog'), (string) filemtime(IDLM_PATH . 'assets/js/design-table.js'), true);
        $nonces = array(); foreach (array('accept', 'assign', 'send_back') as $operation) { $nonces[$operation] = wp_create_nonce('idlm_design_handoff_' . $operation); }
        $return = remove_query_arg(array('design_notice', 'design_error', 'handoff', 'project'), wp_unslash($_SERVER['REQUEST_URI'] ?? ''));
        echo '<p class="idlm-design-muted">' . esc_html($total . (1 === $total ? ' project' : ' projects')) . '</p>';
        echo '<div class="idlm-table-wrapper" role="region" aria-label="Design projects table" tabindex="0"><table class="idlm-table idlm-design-table idlm-design-projects" data-ajax-url="' . esc_url(admin_url('admin-ajax.php')) . '"><caption class="screen-reader-text">Design projects</caption><thead><tr><th scope="col">Customer</th><th scope="col">Stage</th><th scope="col">Designer</th><th scope="col">Target</th><th scope="col">Next step</th><th scope="col">Pending</th><th scope="col"><span class="screen-reader-text">Actions</span></th></tr></thead><tbody>';
        foreach ($rows as $row) { self::render_row($row, $contexts[(int) $row->lead_id], $today, $designers, $nonces, $return); }
        echo '</tbody></table></div>';
        IDLM_Design_Project_Panel::shell();
        echo '<nav class="idlm-pagination" aria-label="Design project pages">';
        $link = array_merge(self::kept($filters), array('tab' => 'projects', 'queue' => $queue));
        if ($page > 1) { echo '<a class="idlm-btn idlm-btn-secondary" href="' . esc_url(add_query_arg(array_merge($link, array('design_page' => $page - 1)), $base)) . '">Previous</a>'; }
        if ($page * self::PER_PAGE < $total) { echo '<a class="idlm-btn idlm-btn-secondary" href="' . esc_url(add_query_arg(array_merge($link, array('design_page' => $page + 1)), $base)) . '">Next</a>'; }
        echo '</nav>';
    }

    /** Filters a queue link carries over. */
    private static function kept($filters) {
        return array_filter(array('q' => $filters['q'], 'stage' => $filters['stage'], 'designer' => $filters['designer'] ?: '', 'target' => $filters['target']));
    }

    private static function render_filters($base, $queue, $filters, $manager) {
        $hidden = array(); parse_str((string) wp_parse_url($base, PHP_URL_QUERY), $hidden); $hidden['tab'] = 'projects'; $hidden['queue'] = $queue;
        if ($filters['target']) { $hidden['target'] = $filters['target']; }
        echo '<form class="idlm-design-filters" method="get" action="' . esc_url(strtok($base, '?')) . '" role="search" aria-label="Filter design projects">';
        foreach ($hidden as $key => $value) { echo '<input type="hidden" name="' . esc_attr($key) . '" value="' . esc_attr($value) . '">'; }
        echo '<label><span>Search</span><input class="idlm-input" type="search" name="q" value="' . esc_attr($filters['q']) . '" placeholder="Name, phone or project"></label><label><span>Stage</span><select class="idlm-input" name="stage"><option value="">All stages</option>';
        foreach (IDLM_Design_Stages::stages() as $key => $stage) { echo '<option value="' . esc_attr($key) . '"' . selected($filters['stage'], $key, false) . '>' . esc_html($stage['label'] . ($stage['archived'] ? ' (removed)' : '')) . '</option>'; }
        echo '</select></label>';
        if ($manager) {
            echo '<label><span>Designer</span><select class="idlm-input" name="designer"><option value="">Everyone</option>';
            foreach (IDLM_Design_Handoffs::designers() as $user) { echo '<option value="' . esc_attr($user->ID) . '"' . selected($filters['designer'], (int) $user->ID, false) . '>' . esc_html($user->display_name) . '</option>'; }
            echo '</select></label>';
        }
        echo '<button class="idlm-btn idlm-btn-secondary">Apply</button>';
        if (array_filter($filters)) { echo '<a class="idlm-design-clear" href="' . esc_url(add_query_arg(array('tab' => 'projects', 'queue' => $queue), $base)) . '">Clear</a>'; }
        echo '</form>';
    }

    private static function empty_state($queue, $filtered) {
        $empty = array('' => array('No active projects', 'New handoffs and accepted projects appear here.'), 'new' => array('No new handoffs', 'Won leads appear here when sales sends them to you.'), 'unassigned' => array('Nothing needs a designer', 'Accepted projects without a designer appear here.'), 'overdue' => array('Nothing overdue', 'Projects past their target date appear here.'), 'week' => array('Nothing due in 7 days', 'Projects due this week appear here.'), 'hold' => array('Nothing on hold', 'Projects put on hold appear here.'), 'returned' => array('Nothing returned', 'Handoffs returned to sales appear here until they are resent.'), 'complete' => array('Nothing complete yet', 'Finished projects appear here.'));
        $text = $filtered ? array('No projects match these filters', 'Try clearing a filter.') : $empty[$queue];
        echo '<div class="idlm-state is-empty"><strong>' . esc_html($text[0]) . '</strong><span>' . esc_html($text[1]) . '</span></div>';
    }

    private static function render_row($row, $context, $today, $designers, $nonces, $return) {
        $status = $row->status; $name = trim($row->first_name . ' ' . $row->last_name);
        $url = add_query_arg('handoff', $row->id, add_query_arg('idlm_view', IDLM_Design_Handoff_Controller::VIEW, IDLM_Admin_Shortcodes::workspace_url()));
        $is_new = IDLM_Design_Handoffs::STATUS_NEW === $status; $accepted = IDLM_Design_Handoffs::STATUS_ACCEPTED === $status;
        $won = 'Won' === $row->lead_stage;
        // Only the handoff's own Design Manager (or an administrator) edits it; open, unfinished work only.
        $editable = IDLM_Design_Handoffs::can_decide($row) && (($is_new && $won) || ($accepted && !$row->completed_at));
        $form = 'idlm-design-row-' . (int) $row->id;
        $card = $accepted ? IDLM_Design_Board::card($row, $context, $today) : null;
        $balance = $context['balance'];
        $pending = $balance ? IDLM_Design_Handoff_Controller::money($balance['total_outstanding'], $balance['currency']) : 'No schedule';
        echo '<tr data-handoff="' . esc_attr($row->id) . '" data-status="' . esc_attr($status) . '" data-designer="' . esc_attr((int) $row->designer_id) . '" data-target="' . esc_attr((string) $row->target_date) . '" data-name="' . esc_attr($name) . '">';
        echo '<td><a href="' . esc_url($url) . '" data-design-panel="' . esc_attr($row->id) . '"><strong>' . esc_html($name) . '</strong></a> <small>' . esc_html($row->project_name) . '</small></td><td>' . self::stage_cell($row) . '</td>';
        if ($editable) {
            echo '<td><select class="idlm-input idlm-design-inline" name="designer_id" form="' . esc_attr($form) . '" aria-label="' . esc_attr('Designer for ' . $name) . '"' . ($accepted ? ' required' : '') . '><option value="">' . ($is_new ? 'Assign later' : 'Choose a designer') . '</option>';
            foreach ($designers as $user) { echo '<option value="' . esc_attr($user->ID) . '"' . selected((int) $user->ID, (int) $row->designer_id, false) . '>' . esc_html($user->display_name) . '</option>'; }
            echo '</select></td><td><input class="idlm-input idlm-design-inline" type="date" name="target_date" form="' . esc_attr($form) . '" value="' . esc_attr((string) $row->target_date) . '" aria-label="' . esc_attr('Target date for ' . $name) . '"' . ($accepted ? ' required' : '') . '></td>';
        } else {
            echo '<td>' . esc_html((int) $row->designer_id ? IDLM_Design_Handoff_Controller::person($row->designer_id) : ($accepted ? 'Not assigned' : '—')) . '</td><td>' . esc_html($row->target_date ? IDLM_Design_Handoff_Controller::date($row->target_date) : ($accepted ? 'Not set' : '—')) . '</td>';
        }
        echo '<td>' . esc_html($card ? $card['next'] : ($is_new ? ($won ? 'Accept and assign a designer' : 'Lead is no longer Won. Return it to sales.') : 'Waiting for sales: ' . $row->send_back_reason));
        if ($card) { IDLM_Design_Board::badges($row, $card); }
        echo '</td><td>' . esc_html($pending) . '</td><td><div class="idlm-design-row-actions">';
        if ($editable) {
            $operation = $is_new ? 'accept' : 'assign';
            echo '<form id="' . esc_attr($form) . '" method="post" action="' . esc_url(admin_url('admin-post.php')) . '"><input type="hidden" name="action" value="' . esc_attr('idlm_design_handoff_' . $operation) . '"><input type="hidden" name="operation" value="' . esc_attr($operation) . '"><input type="hidden" name="handoff_id" value="' . esc_attr($row->id) . '"><input type="hidden" name="_wpnonce" value="' . esc_attr($nonces[$operation]) . '"><input type="hidden" name="return_url" value="' . esc_attr($return) . '"><input type="hidden" name="stay" value="1"><input type="hidden" name="reason" value="">';
            echo $is_new ? '<button class="idlm-btn idlm-btn-primary">Accept</button>' : '<button class="idlm-btn idlm-btn-secondary idlm-design-row-save">Save</button>';
            echo '</form>';
        }
        if ($is_new && IDLM_Design_Handoffs::can_decide($row)) {
            // Without scripts this opens the project, where the return form is.
            echo '<a class="idlm-btn idlm-btn-secondary" href="' . esc_url($url) . '" data-design-send-back data-nonce="' . esc_attr($nonces['send_back']) . '">Send back</a>';
        }
        echo '<a class="idlm-btn idlm-btn-secondary" href="' . esc_url($url) . '" data-design-panel="' . esc_attr($row->id) . '" aria-label="' . esc_attr('Open project for ' . $name) . '">Open</a></div></td></tr>';
    }

    private static function stage_cell($row) {
        if (IDLM_Design_Handoffs::STATUS_NEW === $row->status) { return '<span class="idlm-badge is-info">New handoff</span>' . ('Won' !== $row->lead_stage ? ' <span class="idlm-badge is-warning">Lead no longer Won</span>' : ''); }
        if (IDLM_Design_Handoffs::STATUS_SENT_BACK === $row->status) { return '<span class="idlm-badge is-warning">Returned</span>'; }
        if ($row->completed_at) { return esc_html('Complete'); }
        return esc_html(IDLM_Design_Stages::stages()[IDLM_Design_Stages::stage_of($row)]['label']);
    }
}
