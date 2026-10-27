<?php
/** Stable, retryable six-cycle qualification plans. Builder: Shashi. */
if (!defined('ABSPATH')) { exit; }

final class IDLM_Presales_Task_Plan {
    public static function ensure($lead_id, $assigned_rep_id) {
        $result = IDLM_Lead_Transaction::run(static function() use ($lead_id, $assigned_rep_id) {
            $lead = self::lock_lead($lead_id);
            if (!self::active_lead($lead)) { return true; }
            return self::fill($lead, absint($lead->assigned_rep_id) ?: absint($assigned_rep_id));
        });
        return !is_wp_error($result) && true === $result;
    }

    public static function sync($lead_id) {
        $result = IDLM_Lead_Transaction::run(static function() use ($lead_id) {
            $lead = self::lock_lead($lead_id);
            if (!self::active_lead($lead) || !absint($lead->assigned_rep_id)) { return true; }
            self::fill($lead, absint($lead->assigned_rep_id));
            $tasks = self::tasks($lead->id);
            $progress = IDLM_DB::get_followup_compliance($lead->id);
            $now = current_time('mysql');
            $changed = false;
            foreach ($tasks as $task) {
                $complete = (int) $task->qualification_cycle <= (int) $progress['complete_days'];
                $status = $complete ? 'completed' : 'pending';
                $values = array();
                if ($status !== $task->status) {
                    $values['status'] = $status;
                    $values['completed_at'] = $complete ? $now : null;
                }
                // Pending work follows the current owner; completed history stays attributed.
                if (!$complete && (int) $task->assigned_rep_id !== (int) $lead->assigned_rep_id) {
                    $values['assigned_rep_id'] = absint($lead->assigned_rep_id);
                }
                if ($values) {
                    $values['updated_at'] = $now;
                    IDLM_Lead_Transaction::required($GLOBALS['wpdb']->update($GLOBALS['wpdb']->prefix . 'idlm_tasks', $values, array('id' => absint($task->id))));
                    $changed = true;
                }
            }
            $changed = self::schedule($lead->id, $progress) || $changed;
            if ($changed) { IDLM_Lead_Transaction::invalidate('presales_task_plan', $lead->id); }
            return true;
        });
        return !is_wp_error($result) && true === $result;
    }

    private static function lock_lead($lead_id) {
        global $wpdb;
        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- REL007-SQL-0476: lock_lead(): fixed prefixed lead/task tables and qualification predicates; ID/date/cursor values bound; repair/scheduling state is current under the existing lead transaction.
        $lead = IDLM_Lead_Transaction::read($wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}idlm_leads WHERE id = %d FOR UPDATE", absint($lead_id))));
        // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
        IDLM_Lead_Transaction::required($lead);
        return $lead;
    }

    private static function active_lead($lead) {
        return empty($lead->is_duplicate)
            && !in_array($lead->lead_quality, array('Qualified', 'Unqualified'), true)
            && !in_array($lead->stage, array('Qualified', 'Visited', 'Quote Shared', 'Negotiation', 'Won', 'Lost'), true);
    }

    private static function tasks($lead_id) {
        global $wpdb;
        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- REL007-SQL-0477: tasks(): fixed prefixed lead/task tables and qualification predicates; ID/date/cursor values bound; repair/scheduling state is current under the existing lead transaction.
        return IDLM_Lead_Transaction::read($wpdb->get_results($wpdb->prepare(
        // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
            "SELECT * FROM {$wpdb->prefix}idlm_tasks WHERE lead_id = %d AND task_type = 'Qualification' ORDER BY due_date ASC, id ASC FOR UPDATE", absint($lead_id)
        )));
    }

    /** Adopt only unambiguous legacy day/cycle labels; never delete or merge task history. */
    private static function fill($lead, $owner) {
        global $wpdb;
        IDLM_Lead_Transaction::required($owner > 0);
        $tasks = self::tasks($lead->id);
        $slots = array();
        $adopt = array();
        foreach ($tasks as $task) {
            $cycle = isset($task->qualification_cycle) ? (int) $task->qualification_cycle : 0;
            if (!$cycle && preg_match('/^Qualification (?:day|cycle) ([1-6]):/i', (string) $task->title, $match)) { $cycle = (int) $match[1]; }
            if ($cycle < 1 || $cycle > 6 || isset($slots[$cycle])) {
                return IDLM_Lead_Transaction::required(new WP_Error('idlm_task_plan_ambiguous', 'Qualification task history needs administrator review before repair.'));
            }
            $slots[$cycle] = $task;
            if (empty($task->qualification_cycle)) { $adopt[] = array('id' => absint($task->id), 'cycle' => $cycle); }
        }
        foreach ($adopt as $row) {
            // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- REL007-SQL-0478: fill(): fixed prefixed lead/task tables and qualification predicates; ID/date/cursor values bound; repair/scheduling state is current under the existing lead transaction.
            IDLM_Lead_Transaction::required(1 === $wpdb->update($wpdb->prefix . 'idlm_tasks', array('qualification_cycle' => $row['cycle']), array('id' => $row['id'])));
            // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
        }
        $cursor = new DateTime($lead->created_at ?: current_time('mysql'), wp_timezone());
        $cursor->setTime(0, 0, 0);
        $days = IDLM_DB::get_normalized_working_days();
        $end = self::work_end();
        $targets = IDLM_DB::get_presales_outreach_targets();
        $created = 0;
        for ($cycle = 1; $cycle <= 6; $cycle++) {
            while (!in_array((int) $cursor->format('N'), $days, true)) { $cursor->modify('+1 day'); }
            if (!isset($slots[$cycle])) {
                // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery -- REL007-SQL-0479: fill(): fixed prefixed lead/task tables and qualification predicates; ID/date/cursor values bound; repair/scheduling state is current under the existing lead transaction.
                IDLM_Lead_Transaction::required(1 === $wpdb->insert($wpdb->prefix . 'idlm_tasks', array(
                // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery
                    'lead_id' => absint($lead->id), 'assigned_rep_id' => $owner,
                    'title' => sprintf('Qualification cycle %d: log %d call%s and %d WhatsApp', $cycle, $targets['calls'], 1 === $targets['calls'] ? '' : 's', $targets['whatsapp']),
                    'task_type' => 'Qualification', 'qualification_cycle' => $cycle, 'priority' => 'High', 'status' => 'pending',
                    'due_date' => $cursor->format('Y-m-d') . ' ' . $end . ':00', 'created_by' => 0,
                    'created_at' => current_time('mysql'), 'updated_at' => current_time('mysql'),
                )));
                $created++;
            }
            $cursor->modify('+1 day');
        }
        if ($created) {
            $message = empty($tasks) ? 'Created the six-cycle pre-sales qualification task plan.' : sprintf('Repaired the six-cycle pre-sales qualification task plan: added %d missing cycle%s.', $created, 1 === $created ? '' : 's');
            IDLM_Lead_Transaction::required(IDLM_DB::log_activity($lead->id, 'task', $message, 0));
        }
        if ($created || $adopt) { IDLM_Lead_Transaction::invalidate('presales_task_plan', $lead->id); }
        return true;
    }

    private static function work_end() {
        $end = substr((string) IDLM_Settings::get('idlm_working_hours_end', '18:00'), 0, 5);
        return preg_match('/^(?:[01][0-9]|2[0-3]):[0-5][0-9]$/D', $end) ? $end : '18:00';
    }

    private static function schedule($lead_id, $progress) {
        global $wpdb;
        $days = IDLM_DB::get_normalized_working_days();
        $cursor = new DateTime('today', wp_timezone());
        $last = !empty($progress['complete_days']) ? $progress['days'][$progress['complete_days'] - 1] : null;
        if ($last && current_time('Y-m-d') === $last['date'] && empty($progress['active_cycle']['calls']) && empty($progress['active_cycle']['whatsapp'])) { $cursor->modify('+1 day'); }
        $changed = false;
        $tasks = self::tasks($lead_id);
        usort($tasks, static function($a, $b) { return (int) $a->qualification_cycle <=> (int) $b->qualification_cycle; });
        foreach ($tasks as $task) {
            if ('pending' !== $task->status) { continue; }
            while (!in_array((int) $cursor->format('N'), $days, true)) { $cursor->modify('+1 day'); }
            $due = $cursor->format('Y-m-d') . ' ' . self::work_end() . ':00';
            if ($due !== $task->due_date) {
                // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- REL007-SQL-0480: schedule(): fixed prefixed lead/task tables and qualification predicates; ID/date/cursor values bound; repair/scheduling state is current under the existing lead transaction.
                IDLM_Lead_Transaction::required($wpdb->update($wpdb->prefix . 'idlm_tasks', array('due_date' => $due, 'updated_at' => current_time('mysql')), array('id' => absint($task->id))));
                // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
                $changed = true;
            }
            $cursor->modify('+1 day');
        }
        return $changed;
    }

    /** One overlap-safe page; advance past failed rows only after recording failure for retry next pass. */
    public static function sweep() {
        if (!IDLM_Recovery_Guard::enter_worker()) { return false; }
        global $wpdb;
        $lock = IDLM_Recovery_Guard::name('presales_plan_sweep');
        $held = false;
        try {
            // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- REL007-SQL-0481: sweep(): fixed prefixed lead/task tables and qualification predicates; ID/date/cursor values bound; repair/scheduling state is current under the existing lead transaction.
            if (1 !== (int) $wpdb->get_var($wpdb->prepare('SELECT GET_LOCK(%s, 0)', $lock))) { return false; }
            // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
            $held = true;
            $saved_cursor = IDLM_Schema_Migration::version('idlm_presales_plan_cursor');
            if (null === $saved_cursor) { return false; }
            $cursor = absint($saved_cursor);
            // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- REL007-SQL-0482: sweep(): fixed prefixed lead/task tables and qualification predicates; ID/date/cursor values bound; repair/scheduling state is current under the existing lead transaction.
            $ids = $wpdb->get_col($wpdb->prepare(
            // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
                "SELECT id FROM {$wpdb->prefix}idlm_leads WHERE id > %d AND assigned_rep_id > 0 AND is_duplicate = 0
                 AND lead_quality NOT IN ('Qualified', 'Unqualified') AND stage NOT IN ('Qualified', 'Visited', 'Quote Shared', 'Negotiation', 'Won', 'Lost')
                 ORDER BY id ASC LIMIT 100", $cursor
            ));
            if ($wpdb->last_error) { return false; }
            $ok = true;
            foreach ($ids as $id) {
                if (!IDLM_Recovery_Guard::lease_valid()) { return false; }
                if (!self::sync($id)) {
                    $ok = false;
                    // No contact data in operational diagnostics; the next full pass retries this lead.
                    // phpcs:disable WordPress.PHP.DevelopmentFunctions.error_log_error_log -- REL007-BOUNDARY-0067: sweep(): server-side repair failure log contains fixed text plus absint lead ID, with no contact details, credentials or payloads; logging is deliberate operational failure reporting.
                    error_log('IDLM qualification task-plan repair failed for lead ' . absint($id) . '.');
                    // phpcs:enable WordPress.PHP.DevelopmentFunctions.error_log_error_log
                }
                $cursor = absint($id);
            }
            // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- REL007-SQL-0483: sweep(): fixed prefixed lead/task tables and qualification predicates; ID/date/cursor values bound; repair/scheduling state is current under the existing lead transaction.
            if (!IDLM_Recovery_Guard::lease_valid() || (string) $wpdb->get_var($wpdb->prepare('SELECT IS_USED_LOCK(%s)', $lock)) !== (string) $wpdb->get_var('SELECT CONNECTION_ID()')) { return false; }
            // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
            $next = count($ids) < 100 ? 0 : $cursor;
            update_option('idlm_presales_plan_cursor', $next, false);
            return $ok && (string) $next === IDLM_Schema_Migration::version('idlm_presales_plan_cursor');
        } finally {
            // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- REL007-SQL-0484: sweep(): fixed prefixed lead/task tables and qualification predicates; ID/date/cursor values bound; repair/scheduling state is current under the existing lead transaction.
            if ($held) { $wpdb->get_var($wpdb->prepare('SELECT RELEASE_LOCK(%s)', $lock)); }
            // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
            IDLM_Recovery_Guard::exit_worker();
        }
    }
}
