<?php
/**
 * Read-only Phase 2 query-plan scenario manifest.
 *
 * Builder: Shashi
 */

if (!defined('ABSPATH') && 'cli' !== PHP_SAPI) {
    exit;
}

return array(
    array(
        'id' => 'dashboard-global-period-volume',
        'scenario' => 'dashboard-period-refresh',
        'variant' => 'global-period-count',
        'tables' => array('idlm_leads'),
        'sql' => 'SELECT COUNT(id) FROM {{leads}} WHERE is_duplicate = 0 AND created_at BETWEEN %s AND %s',
        'params' => array('start', 'end'),
    ),
    array(
        'id' => 'dashboard-restricted-period-volume',
        'scenario' => 'dashboard-period-refresh',
        'variant' => 'representative-period-count',
        'tables' => array('idlm_leads'),
        'sql' => 'SELECT COUNT(id) FROM {{leads}} WHERE is_duplicate = 0 AND assigned_rep_id = %d AND created_at BETWEEN %s AND %s',
        'params' => array('rep_id', 'start', 'end'),
    ),
    array(
        'id' => 'lead-list-global-default-order',
        'scenario' => 'lead-list-filter-sort',
        'variant' => 'manager-default-queue',
        'tables' => array('idlm_leads'),
        'sql' => 'SELECT id FROM {{leads}} WHERE is_duplicate = 0 ORDER BY COALESCE(assigned_at, created_at) DESC, id DESC LIMIT 25',
        'params' => array(),
    ),
    array(
        'id' => 'lead-list-restricted-default-order',
        'scenario' => 'lead-list-filter-sort',
        'variant' => 'representative-default-queue',
        'tables' => array('idlm_leads'),
        'sql' => 'SELECT id FROM {{leads}} WHERE is_duplicate = 0 AND assigned_rep_id = %d ORDER BY COALESCE(assigned_at, created_at) DESC, id DESC LIMIT 25',
        'params' => array('rep_id'),
    ),
    array(
        'id' => 'qualified-handoff-queue',
        'scenario' => 'lead-list-filter-sort',
        'variant' => 'awaiting-or-exception',
        'tables' => array('idlm_leads'),
        'sql' => "SELECT id, qualified_at FROM {{leads}} WHERE is_duplicate = 0 AND stage NOT IN ('Won', 'Lost') AND lead_quality = 'Qualified' AND handoff_status IN ('awaiting_manager', 'exception') ORDER BY qualified_at ASC, id ASC LIMIT 100",
        'params' => array(),
    ),
    array(
        'id' => 'kanban-active-stage-load',
        'scenario' => 'kanban-stage-load',
        'variant' => 'active-canonical-leads',
        'tables' => array('idlm_leads'),
        'sql' => "SELECT id, stage FROM {{leads}} WHERE is_duplicate = 0 AND stage NOT IN ('Won', 'Lost') ORDER BY id DESC",
        'params' => array(),
    ),
    array(
        'id' => 'representative-task-queue',
        'scenario' => 'lead-list-filter-sort',
        'variant' => 'pending-tasks-by-owner',
        'tables' => array('idlm_tasks'),
        'sql' => "SELECT id, lead_id, due_date FROM {{tasks}} WHERE assigned_rep_id = %d AND status = 'pending' ORDER BY status ASC, due_date ASC, id DESC LIMIT 100",
        'params' => array('rep_id'),
    ),
    array(
        'id' => 'lead-activity-timeline',
        'scenario' => 'lead-detail-activity',
        'variant' => 'latest-first',
        'tables' => array('idlm_lead_activity_log'),
        'sql' => 'SELECT id, activity_type, created_at FROM {{activity}} WHERE lead_id = %d ORDER BY id DESC LIMIT 100',
        'params' => array('lead_id'),
    ),
    array(
        'id' => 'capi-failed-retry-queue',
        'scenario' => 'capi-retry',
        'variant' => 'failed-oldest-first',
        'tables' => array('idlm_capi_logs'),
        'sql' => "SELECT id, lead_id, created_at FROM {{capi}} WHERE status = 'failed' ORDER BY created_at ASC, id ASC LIMIT 100",
        'params' => array(),
    ),
    array(
        'id' => 'quotation-list-default-order',
        'scenario' => 'quotation-list',
        'variant' => 'global-updated-first',
        'tables' => array('idlm_quotes', 'idlm_quote_revisions', 'idlm_leads'),
        'sql' => 'SELECT q.id, q.updated_at FROM {{quotes}} q JOIN {{leads}} l ON l.id = q.lead_id LEFT JOIN {{revisions}} r ON r.id = q.current_revision_id ORDER BY q.updated_at DESC LIMIT 25 OFFSET 0',
        'params' => array(),
    ),
);
