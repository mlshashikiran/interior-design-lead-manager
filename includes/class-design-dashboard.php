<?php
/**
 * Design overview for managers and administrators. Counts come from the same scoped
 * query as the board and list, so a card always matches the list it opens. These are
 * operational figures, not revenue or designer scores. Builder: Shashi.
 */
if (!defined('ABSPATH')) { exit; }

final class IDLM_Design_Dashboard {
    const ROW_LIMIT = 1000;

    /** Pure: median of numbers, or null when there is nothing to measure. */
    public static function median($values) {
        $values = array_values(array_map('floatval', (array) $values));
        if (!$values) { return null; }
        sort($values); $middle = (int) floor(count($values) / 2);
        return count($values) % 2 ? $values[$middle] : ($values[$middle - 1] + $values[$middle]) / 2;
    }

    private static function days($from, $to) {
        $start = strtotime((string) $from . ' UTC'); $end = strtotime((string) $to . ' UTC');
        return $start && $end ? max(0, ($end - $start) / DAY_IN_SECONDS) : null;
    }

    private static function count_list($filters) {
        return IDLM_Design_Board::query(array_merge(array('q' => '', 'stage' => '', 'target' => '', 'state' => '', 'designer' => 0), $filters), 1)['total'];
    }

    // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter -- Custom IDLM tables named from $wpdb->prefix; values are bound with prepare(), cast to int, or come from fixed clause builders and generated placeholder lists; request-scoped reads and transactional writes are deliberately uncached. Builder: Shashi.
    private static function count_status($status) {
        global $wpdb; $table = $wpdb->prefix . 'idlm_design_handoffs'; $scope = IDLM_Design_Handoffs::scope_sql('h');
        return (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $table h WHERE $scope AND h.status = %s", $status));
    }
    // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter

    // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter -- Custom IDLM tables named from $wpdb->prefix; values are bound with prepare(), cast to int, or come from fixed clause builders and generated placeholder lists; request-scoped reads and transactional writes are deliberately uncached. Builder: Shashi.
    public static function cards() {
        global $wpdb; $table = $wpdb->prefix . 'idlm_design_handoffs'; $scope = IDLM_Design_Handoffs::scope_sql('h');
        return array(
            'pending' => self::count_status(IDLM_Design_Handoffs::STATUS_NEW),
            'unassigned' => (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $table h WHERE $scope AND h.status = %s AND h.designer_id = 0 AND h.completed_at IS NULL", IDLM_Design_Handoffs::STATUS_ACCEPTED)),
            'returned' => self::count_status(IDLM_Design_Handoffs::STATUS_SENT_BACK),
            'active' => self::count_list(array()), 'overdue' => self::count_list(array('target' => 'overdue')), 'week' => self::count_list(array('target' => 'week')),
            'hold' => self::count_list(array('state' => 'hold')), 'no_target' => self::count_list(array('target' => 'none')),
        );
    }
    // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter

    /** Active projects by stage and by person, plus what needs a decision. */
    public static function operations() {
        $filters = array('q' => '', 'stage' => '', 'target' => '', 'state' => '', 'designer' => 0);
        $result = IDLM_Design_Board::query($filters, self::ROW_LIMIT); $rows = $result['rows']; $now = current_time('mysql', true); $today = current_time('Y-m-d');
        $contexts = IDLM_Design_Stages::contexts($rows); $stages = array(); $people = array(); $attention = array();
        foreach (array_keys(IDLM_Design_Stages::stages()) as $key) { $stages[$key] = array('count' => 0, 'hold' => 0, 'ages' => array()); }
        foreach ($rows as $row) {
            $key = IDLM_Design_Stages::stage_of($row); $stages[$key]['count']++; if ($row->hold_at) { $stages[$key]['hold']++; }
            $age = self::days($row->stage_entered_at ?: $row->decided_at, $now); if (null !== $age) { $stages[$key]['ages'][] = $age; }
            $person = (int) $row->designer_id; $target = IDLM_Design_Board::target_state($row->target_date, $today);
            if (!isset($people[$person])) { $people[$person] = array('active' => 0, 'overdue' => 0, 'soon' => 0, 'hold' => 0, 'no_target' => 0); }
            $people[$person]['active']++; if ('overdue' === $target) { $people[$person]['overdue']++; } if ('soon' === $target) { $people[$person]['soon']++; } if ($row->hold_at) { $people[$person]['hold']++; } if ('none' === $target) { $people[$person]['no_target']++; }
            $context = $contexts[(int) $row->lead_id]; $reasons = array();
            if ('overdue' === $target) { $reasons[] = 'Overdue since ' . IDLM_Design_Handoff_Controller::date($row->target_date); }
            if ('Won' !== $row->lead_stage) { $reasons[] = 'Lead no longer Won'; }
            $flags = IDLM_Design_Stages::open_flags($row, $context); if ($flags) { $reasons[] = 'Review: ' . implode(', ', $flags); }
            if (!empty($context['unreviewed'])) { $reasons[] = 'Design quotation waiting for review'; }
            if ('overdue' === $target) { $missing = IDLM_Design_Stages::unmet(IDLM_Design_Stages::checklist($row, null, $context)); if ($missing) { $reasons[] = 'Needs: ' . implode(', ', array_column($missing, 'label')); } }
            if ($reasons) { $attention[] = array('row' => $row, 'reasons' => $reasons); }
        }
        return array('stages' => $stages, 'people' => $people, 'attention' => $attention, 'truncated' => $result['total'] > self::ROW_LIMIT, 'total' => $result['total']);
    }

    // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter -- Custom IDLM tables named from $wpdb->prefix; values are bound with prepare(), cast to int, or come from fixed clause builders and generated placeholder lists; request-scoped reads and transactional writes are deliberately uncached. Builder: Shashi.
    /** Elapsed calendar days from recorded events. Legacy rows without events are counted as unknown. */
    /** $window: a number of days back from now, or a period window from IDLM_Date_Ranges::resolve() in site time. */
    public static function period_metrics($window) {
        global $wpdb; $p = $wpdb->prefix; $scope = IDLM_Design_Handoffs::scope_sql('h');
        // Design events are stored in UTC.
        if (is_array($window)) { $since = get_gmt_from_date($window['curr_start']); $until = get_gmt_from_date($window['curr_end']); }
        else { $since = gmdate('Y-m-d H:i:s', time() - (int) $window * DAY_IN_SECONDS); $until = gmdate('Y-m-d H:i:s'); }
        $events = (array) $wpdb->get_results($wpdb->prepare("SELECT e.handoff_id, e.event_type, e.occurred_at FROM {$p}idlm_design_handoff_events e INNER JOIN {$p}idlm_design_handoffs h ON h.id = e.handoff_id WHERE $scope AND e.event_type IN ('sent','accepted') ORDER BY e.id LIMIT %d", 10000));
        $last_sent = array(); $first_accepted = array(); $accept = array();
        foreach ($events as $event) {
            if ('sent' === $event->event_type) { $last_sent[$event->handoff_id] = $event->occurred_at; continue; }
            if (!isset($first_accepted[$event->handoff_id])) { $first_accepted[$event->handoff_id] = $event->occurred_at; }
            if ($event->occurred_at >= $since && $event->occurred_at <= $until && isset($last_sent[$event->handoff_id])) { $accept[] = self::days($last_sent[$event->handoff_id], $event->occurred_at); }
        }
        $completed = (array) $wpdb->get_results($wpdb->prepare("SELECT h.id, h.completed_at FROM {$p}idlm_design_handoffs h WHERE $scope AND h.completed_at >= %s AND h.completed_at <= %s LIMIT %d", $since, $until, 5000));
        $turnaround = array(); $unknown = 0;
        foreach ($completed as $row) { if (isset($first_accepted[$row->id])) { $turnaround[] = self::days($first_accepted[$row->id], $row->completed_at); } else { $unknown++; } }
        $values = array();
        foreach ((array) $wpdb->get_results("SELECT h.baseline_total, r.grand_total, r.currency_code FROM {$p}idlm_design_handoffs h INNER JOIN {$p}idlm_quote_revisions r ON r.id = h.design_revision_id WHERE $scope AND h.status = 'accepted' LIMIT 5000") as $row) {
            $currency = (string) $row->currency_code; if (!isset($values[$currency])) { $values[$currency] = array('projects' => 0, 'up' => 0.0, 'down' => 0.0); }
            $change = (float) $row->grand_total - (float) $row->baseline_total; $values[$currency]['projects']++; if ($change > 0) { $values[$currency]['up'] += $change; } else { $values[$currency]['down'] += abs($change); }
        }
        $discounts = (array) $wpdb->get_results($wpdb->prepare("SELECT e.handoff_id, e.actor_id, e.reason, e.occurred_at FROM {$p}idlm_design_handoff_events e INNER JOIN {$p}idlm_design_handoffs h ON h.id = e.handoff_id WHERE $scope AND e.event_type = 'discount_changed' AND e.occurred_at >= %s AND e.occurred_at <= %s ORDER BY e.id DESC LIMIT 50", $since, $until));
        return array('accepted' => count($accept), 'accept_median' => self::median($accept), 'completed' => count($turnaround), 'completed_unknown' => $unknown, 'turnaround_median' => self::median($turnaround), 'values' => $values, 'discounts' => $discounts);
    }
    // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter

    // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter -- Custom IDLM tables named from $wpdb->prefix; values are bound with prepare(), cast to int, or come from fixed clause builders and generated placeholder lists; request-scoped reads and transactional writes are deliberately uncached. Builder: Shashi.
    public static function recent_activity($limit = 12) {
        global $wpdb; $p = $wpdb->prefix; $scope = IDLM_Design_Handoffs::scope_sql('h');
        return (array) $wpdb->get_results($wpdb->prepare("SELECT e.*, l.first_name, l.last_name FROM {$p}idlm_design_handoff_events e INNER JOIN {$p}idlm_design_handoffs h ON h.id = e.handoff_id INNER JOIN {$p}idlm_leads l ON l.id = h.lead_id WHERE $scope ORDER BY e.id DESC LIMIT %d", (int) $limit));
    }
    // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter

    // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter -- Custom IDLM tables named from $wpdb->prefix; values are bound with prepare(), cast to int, or come from fixed clause builders and generated placeholder lists; request-scoped reads and transactional writes are deliberately uncached. Builder: Shashi.
    /** Administrator only: the sales side of the pipeline, including Won leads older than this feature. */
    public static function sales_queues() {
        global $wpdb; $p = $wpdb->prefix;
        $receipt = "EXISTS (SELECT 1 FROM {$p}idlm_lead_payment_confirmations c INNER JOIN {$p}idlm_lead_payment_versions v ON v.id = c.current_version_id WHERE c.lead_id = l.id AND v.status <> 'revoked')";
        $handoff = "EXISTS (SELECT 1 FROM {$p}idlm_design_handoffs h WHERE h.lead_id = l.id)";
        $won = "l.stage = 'Won' AND l.is_duplicate = 0";
        $gaps = array();
        foreach ((array) $wpdb->get_results("SELECT h.id, MIN(v.recorded_at) AS paid, (SELECT MIN(e.occurred_at) FROM {$p}idlm_design_handoff_events e WHERE e.handoff_id = h.id AND e.event_type = 'sent') AS sent FROM {$p}idlm_design_handoffs h INNER JOIN {$p}idlm_lead_payment_versions v ON v.lead_id = h.lead_id GROUP BY h.id LIMIT 5000") as $row) {
            if ($row->sent && $row->paid) { $gaps[] = self::days($row->paid, $row->sent); }
        }
        return array(
            'awaiting_payment' => (int) $wpdb->get_var("SELECT COUNT(*) FROM {$p}idlm_leads l WHERE $won AND NOT $receipt AND NOT $handoff"),
            'not_handed_over' => (int) $wpdb->get_var("SELECT COUNT(*) FROM {$p}idlm_leads l WHERE $won AND $receipt AND NOT $handoff"),
            'payment_to_handoff_median' => self::median($gaps), 'payment_to_handoff_count' => count($gaps),
        );
    }
    // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter

    // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Custom IDLM tables named from $wpdb->prefix; values are bound with prepare(), cast to int, or come from fixed clause builders and generated placeholder lists; request-scoped reads and transactional writes are deliberately uncached. Builder: Shashi.
    /** Administrator only. Empty manager or studio stays visible as its own row. */
    public static function comparisons() {
        global $wpdb; $p = $wpdb->prefix; $today = current_time('Y-m-d');
        $sum = $wpdb->prepare("COUNT(*) AS total, SUM(h.status = 'new') AS pending, SUM(h.status = 'accepted' AND h.completed_at IS NULL) AS active, SUM(h.status = 'accepted' AND h.completed_at IS NULL AND h.target_date < %s) AS overdue, SUM(h.completed_at IS NOT NULL) AS completed", $today);
        return array(
            'managers' => (array) $wpdb->get_results("SELECT h.design_manager_id AS manager, $sum FROM {$p}idlm_design_handoffs h GROUP BY h.design_manager_id ORDER BY total DESC LIMIT 100"),
            'studios' => (array) $wpdb->get_results("SELECT COALESCE(NULLIF(l.preferred_studio, ''), 'No studio') AS studio, $sum FROM {$p}idlm_design_handoffs h INNER JOIN {$p}idlm_leads l ON l.id = h.lead_id GROUP BY studio ORDER BY total DESC LIMIT 100"),
        );
    }
    // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared

    private static function days_text($value) {
        if (null === $value) { return 'Not enough data'; }
        return number_format((float) $value, 1) . ' days';
    }

    private static function event_label($event) {
        $labels = array('sent' => 'Sent to design', 'returned' => 'Returned to sales', 'accepted' => 'Accepted', 'assigned' => 'Designer changed', 'stage_changed' => 'Stage moved', 'stage_moved_back' => 'Stage moved back', 'hold_started' => 'Put on hold', 'hold_ended' => 'Resumed', 'completed' => 'Completed', 'reopened' => 'Reopened', 'document_added' => 'Document added', 'document_archived' => 'Document archived', 'design_revision' => 'Design revision saved', 'discount_changed' => 'Discount changed', 'customer_approved' => 'Customer approved', 'design_revision_reviewed' => 'Design revision reviewed', 'review_cleared' => 'Review flag cleared');
        return $labels[$event->event_type] ?? 'Updated';
    }

    public static function render($base) {
        if (!IDLM_Design_Handoffs::can_manage()) { return; }
        // The shared period list; This quarter by default.
        // phpcs:disable WordPress.Security.NonceVerification.Recommended,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Read-only report filter, normalized and date-validated.
        $period = IDLM_Date_Ranges::normalize(wp_unslash($_GET['design_period'] ?? '')) ?: 'this_quarter';
        $custom = array(IDLM_Date_Ranges::clean_date(wp_unslash($_GET['design_start'] ?? '')), IDLM_Date_Ranges::clean_date(wp_unslash($_GET['design_end'] ?? '')));
        // phpcs:enable WordPress.Security.NonceVerification.Recommended,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
        $window = IDLM_Date_Ranges::resolve($period, $custom[0], $custom[1]);
        if (!$window) { $period = 'this_quarter'; $window = IDLM_Date_Ranges::resolve($period); }
        $cards = self::cards(); $ops = self::operations(); $metrics = self::period_metrics($window); $stages = IDLM_Design_Stages::stages();
        if (current_user_can('manage_options')) { self::render_sales_queues($cards); }
        $link = static function($args) use ($base) { return add_query_arg($args, $base); };
        $items = array(array('New handoffs', $cards['pending'], array('tab' => 'projects', 'queue' => 'new')), array('Needs a designer', $cards['unassigned'], array('tab' => 'projects', 'queue' => 'unassigned')), array('Active projects', $cards['active'], array('tab' => 'projects')), array('Overdue', $cards['overdue'], array('tab' => 'projects', 'queue' => 'overdue')), array('Due in 7 days', $cards['week'], array('tab' => 'projects', 'queue' => 'week')), array('On hold', $cards['hold'], array('tab' => 'projects', 'queue' => 'hold')), array('Returned', $cards['returned'], array('tab' => 'projects', 'queue' => 'returned')), array('No target date', $cards['no_target'], array('tab' => 'projects', 'target' => 'none')));
        echo '<section class="idlm-design-metrics" aria-label="Current projects">';
        foreach ($items as $item) { echo '<a class="idlm-design-metric" href="' . esc_url($link($item[2])) . '"><span>' . esc_html($item[0]) . '</span><strong>' . esc_html($item[1]) . '</strong></a>'; }
        echo '</section><p class="idlm-design-muted">Right now. Active projects include those on hold.</p>';
        self::render_attention($ops, $link);
        echo '<div class="idlm-design-overview-grid"><section class="idlm-card idlm-design-panel"><h2>Stages</h2><div class="idlm-table-wrapper"><table class="idlm-table"><caption class="screen-reader-text">Active projects by stage</caption><thead><tr><th scope="col">Stage</th><th scope="col">Projects</th><th scope="col">On hold</th><th scope="col">Median time here</th></tr></thead><tbody>';
        foreach ($ops['stages'] as $key => $stage) { if ($stages[$key]['archived'] && !$stage['count']) { continue; } echo '<tr><td><a href="' . esc_url($link(array('tab' => 'projects', 'stage' => $key))) . '">' . esc_html($stages[$key]['label']) . '</a></td><td>' . esc_html($stage['count']) . '</td><td>' . esc_html($stage['hold']) . '</td><td>' . esc_html(self::days_text(self::median($stage['ages']))) . '</td></tr>'; }
        echo '</tbody></table></div></section><section class="idlm-card idlm-design-panel"><h2>Designer workload</h2><div class="idlm-table-wrapper"><table class="idlm-table"><caption class="screen-reader-text">Active projects by designer</caption><thead><tr><th scope="col">Designer</th><th scope="col">Active</th><th scope="col">Overdue</th><th scope="col">Due soon</th><th scope="col">On hold</th></tr></thead><tbody>';
        if (!$ops['people']) { echo '<tr><td colspan="5">No active projects.</td></tr>'; }
        foreach ($ops['people'] as $person => $load) { echo '<tr><td>' . ($person ? '<a href="' . esc_url($link(array('tab' => 'projects', 'designer' => $person))) . '">' . esc_html(IDLM_Design_Handoff_Controller::person($person)) . '</a>' : 'Not assigned') . '</td><td>' . esc_html($load['active']) . '</td><td>' . esc_html($load['overdue']) . '</td><td>' . esc_html($load['soon']) . '</td><td>' . esc_html($load['hold']) . '</td></tr>'; }
        echo '</tbody></table></div></section></div>';
        if ($ops['truncated']) { echo '<p class="idlm-design-note">Stage and workload tables use the ' . esc_html(self::ROW_LIMIT) . ' earliest due of ' . esc_html($ops['total']) . ' active projects.</p>'; }
        self::render_period($metrics, $period, $link, $window, $custom);
        if (current_user_can('manage_options')) { self::render_comparisons(); }
        echo '<section class="idlm-card idlm-design-panel"><h2>Recent activity</h2><ul class="idlm-design-revisions">';
        $recent = self::recent_activity();
        if (!$recent) { echo '<li>Nothing yet.</li>'; }
        foreach ($recent as $event) { echo '<li><a href="' . esc_url($link(array('handoff' => $event->handoff_id))) . '">' . esc_html(trim($event->first_name . ' ' . $event->last_name)) . '</a> · ' . esc_html(self::event_label($event)) . '<small>' . esc_html(IDLM_Design_Handoff_Controller::person($event->actor_id) . ' · ' . IDLM_Design_Handoff_Controller::date($event->occurred_at)) . '</small></li>'; }
        echo '</ul></section>';
    }

    private static function render_attention($ops, $link) {
        echo '<section class="idlm-card idlm-design-panel"><h2>Needs attention</h2>';
        if (!$ops['attention']) { echo '<p class="idlm-design-muted">Nothing overdue or flagged for review.</p></section>'; return; }
        echo '<ul class="idlm-design-revisions">';
        foreach (array_slice($ops['attention'], 0, 15) as $item) { $row = $item['row']; echo '<li><a href="' . esc_url($link(array('handoff' => $row->id))) . '">' . esc_html(trim($row->first_name . ' ' . $row->last_name)) . '</a><small>' . esc_html(implode(' · ', $item['reasons'])) . '</small></li>'; }
        echo '</ul>';
        if (count($ops['attention']) > 15) { echo '<p class="idlm-design-muted">' . esc_html('Showing 15 of ' . count($ops['attention']) . '.') . '</p>'; }
        echo '</section>';
    }

    private static function render_sales_queues($cards) {
        $queues = self::sales_queues(); $payments = add_query_arg('idlm_view', IDLM_Lead_Payment_Controller::tab_shown() ? 'payments' : 'leads', IDLM_Admin_Shortcodes::workspace_url());
        echo '<section class="idlm-card idlm-design-panel"><h2>Sales to design</h2><div class="idlm-table-wrapper"><table class="idlm-table"><caption class="screen-reader-text">Sales to design queues</caption><thead><tr><th scope="col">Waiting for</th><th scope="col">Leads</th></tr></thead><tbody>';
        echo '<tr><td><a href="' . esc_url($payments) . '">Won, no payment recorded</a></td><td>' . esc_html($queues['awaiting_payment']) . '</td></tr><tr><td><a href="' . esc_url($payments) . '">Payment recorded, not sent to design</a></td><td>' . esc_html($queues['not_handed_over']) . '</td></tr><tr><td>Sent, waiting for a Design Manager</td><td>' . esc_html($cards['pending']) . '</td></tr><tr><td>Returned, waiting for sales</td><td>' . esc_html($cards['returned']) . '</td></tr><tr><td>Accepted, waiting for a designer</td><td>' . esc_html($cards['unassigned']) . '</td></tr></tbody></table></div>';
        echo '<p class="idlm-design-muted">The first two rows include Won leads from before design handoff existed. Median time from first payment to first send: ' . esc_html(self::days_text($queues['payment_to_handoff_median'])) . ' (' . esc_html($queues['payment_to_handoff_count']) . (1 === $queues['payment_to_handoff_count'] ? ' project' : ' projects') . ').</p></section>';
    }

    private static function render_period($metrics, $period, $link, $window = null, $custom = array('', '')) {
        $title = 'custom' === $period && $window ? wp_date('j M Y', strtotime($window['curr_start'])) . ' – ' . wp_date('j M Y', strtotime($window['curr_end'])) : (IDLM_Date_Ranges::OPTIONS[$period] ?? 'This quarter');
        $action = $link(array('tab' => 'overview', 'design_period' => false, 'design_start' => false, 'design_end' => false));
        parse_str((string) wp_parse_url($action, PHP_URL_QUERY), $keep);
        echo '<section class="idlm-card idlm-design-panel"><div class="idlm-design-period"><h2>' . esc_html($title) . '</h2><form method="get" action="' . esc_url(strtok($action, '?')) . '" class="idlm-design-period-form" aria-label="Reporting period">';
        foreach ($keep as $key => $value) { if (is_scalar($value)) { echo '<input type="hidden" name="' . esc_attr($key) . '" value="' . esc_attr($value) . '">'; } }
        echo '<label class="screen-reader-text" for="idlm-design-period">Reporting period</label><select class="idlm-input" id="idlm-design-period" name="design_period" data-idlm-date-range data-idlm-date-submit>'; IDLM_Date_Ranges::print_options($period); echo '</select>';
        echo '<span data-idlm-date-custom-for="idlm-design-period"' . ('custom' === $period ? '' : ' hidden') . '><label class="screen-reader-text" for="idlm-design-start">From</label><input class="idlm-input" type="date" id="idlm-design-start" name="design_start" value="' . esc_attr($custom[0]) . '"><label class="screen-reader-text" for="idlm-design-end">To</label><input class="idlm-input" type="date" id="idlm-design-end" name="design_end" value="' . esc_attr($custom[1]) . '"><button type="submit" class="idlm-btn idlm-btn-secondary">Apply</button></span>';
        echo '</form>';
        echo '</div><dl class="idlm-design-facts"><div><dt>Handoffs accepted</dt><dd>' . esc_html($metrics['accepted']) . '</dd></div><div><dt>Median time to accept</dt><dd>' . esc_html(self::days_text($metrics['accept_median'])) . '</dd></div><div><dt>Projects completed</dt><dd>' . esc_html($metrics['completed']) . ($metrics['completed_unknown'] ? ' <small>' . esc_html($metrics['completed_unknown'] . ' more without a start date') . '</small>' : '') . '</dd></div><div><dt>Median acceptance to completion</dt><dd>' . esc_html(self::days_text($metrics['turnaround_median'])) . '</dd></div></dl>';
        echo '<h3>Quotation value changes</h3><p class="idlm-design-muted">Latest design revision against the sales price, for all accepted projects. Not revenue, cash collected or customer approval.</p>';
        if (!$metrics['values']) { echo '<p class="idlm-design-muted">No design revisions yet.</p>'; }
        else {
            echo '<div class="idlm-table-wrapper"><table class="idlm-table"><caption class="screen-reader-text">Quotation value changes by currency</caption><thead><tr><th scope="col">Currency</th><th scope="col">Projects</th><th scope="col">Increases</th><th scope="col">Decreases</th></tr></thead><tbody>';
            foreach ($metrics['values'] as $currency => $value) { echo '<tr><td>' . esc_html($currency) . '</td><td>' . esc_html($value['projects']) . '</td><td>' . esc_html(IDLM_Design_Handoff_Controller::money($value['up'], $currency)) . '</td><td>' . esc_html(IDLM_Design_Handoff_Controller::money($value['down'], $currency)) . '</td></tr>'; }
            echo '</tbody></table></div>';
        }
        echo '<h3>Discount changes</h3>';
        if (!$metrics['discounts']) { echo '<p class="idlm-design-muted">None in this period.</p>'; }
        else { echo '<ul class="idlm-design-revisions">'; foreach ($metrics['discounts'] as $event) { echo '<li><a href="' . esc_url($link(array('handoff' => $event->handoff_id))) . '">' . esc_html($event->reason) . '</a><small>' . esc_html(IDLM_Design_Handoff_Controller::person($event->actor_id) . ' · ' . IDLM_Design_Handoff_Controller::date($event->occurred_at)) . '</small></li>'; } echo '</ul>'; }
        echo '</section>';
    }

    private static function render_comparisons() {
        $compare = self::comparisons();
        foreach (array('managers' => array('By Design Manager', 'manager'), 'studios' => array('By studio', 'studio')) as $key => $meta) {
            echo '<section class="idlm-card idlm-design-panel"><h2>' . esc_html($meta[0]) . '</h2><div class="idlm-table-wrapper"><table class="idlm-table"><caption class="screen-reader-text">' . esc_html($meta[0]) . '</caption><thead><tr><th scope="col">Name</th><th scope="col">New</th><th scope="col">Active</th><th scope="col">Overdue</th><th scope="col">Completed</th></tr></thead><tbody>';
            if (!$compare[$key]) { echo '<tr><td colspan="5">Nothing yet.</td></tr>'; }
            foreach ($compare[$key] as $row) {
                $name = 'manager' === $meta[1] ? ((int) $row->manager ? IDLM_Design_Handoff_Controller::person($row->manager) : 'No manager') : $row->studio;
                echo '<tr><td>' . esc_html($name) . '</td><td>' . esc_html((int) $row->pending) . '</td><td>' . esc_html((int) $row->active) . '</td><td>' . esc_html((int) $row->overdue) . '</td><td>' . esc_html((int) $row->completed) . '</td></tr>';
            }
            echo '</tbody></table></div></section>';
        }
    }
}
