<?php
/**
 * Read-only authenticated Phase 8 load qualification scenarios.
 *
 * Builder: Shashi
 */

if (!defined('ABSPATH') && 'cli' !== PHP_SAPI) {
    exit;
}

return array(
    array(
        'id' => 'dashboard-period-refresh',
        'surface' => 'Dashboard',
        'transport' => 'admin-ajax.php',
        'action' => 'idlm_get_dashboard_stats',
        'request' => array('period' => 'this_month'),
        'roles' => array('administrator', 'assignment_manager', 'representative'),
        'read_only' => true,
        'cache_state' => 'warm_after_warmup',
        'provisional_thresholds' => array('p95_ms' => 1000, 'error_rate_percent' => 1),
    ),
    array(
        'id' => 'lead-list-filter-sort',
        'surface' => 'Lead list',
        'transport' => 'admin-ajax.php',
        'action' => 'idlm_get_leads_table',
        'request' => array(
            'page' => 1,
            'per_page' => 25,
            'date_basis' => 'assigned_at',
            'sort_by' => '',
            'sort_order' => 'ASC',
        ),
        'roles' => array('administrator', 'assignment_manager', 'representative', 'pre_sales'),
        'read_only' => true,
        'cache_state' => 'not_applicable',
        'provisional_thresholds' => array('p95_ms' => 1500, 'p99_ms' => 3000, 'error_rate_percent' => 1),
    ),
    array(
        'id' => 'kanban-stage-load',
        'surface' => 'Pipeline',
        'transport' => 'admin-ajax.php',
        'action' => 'idlm_get_kanban_leads',
        'request' => array('date_basis' => 'assigned_at'),
        'roles' => array('administrator', 'assignment_manager', 'representative'),
        'read_only' => true,
        'cache_state' => 'not_applicable',
        'provisional_thresholds' => array('error_rate_percent' => 1),
    ),
);
