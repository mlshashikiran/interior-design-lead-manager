<?php
/**
 * Critical Phase 0 benchmark scenario manifest.
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
        'operation' => 'idlm_get_dashboard_stats',
        'roles' => array('administrator', 'assignment_manager', 'representative'),
        'priority' => 'critical',
    ),
    array(
        'id' => 'lead-list-filter-sort',
        'surface' => 'Lead list',
        'transport' => 'admin-ajax.php',
        'operation' => 'idlm_get_leads_table',
        'roles' => array('administrator', 'assignment_manager', 'representative'),
        'priority' => 'critical',
    ),
    array(
        'id' => 'lead-detail-activity',
        'surface' => 'Lead drawer',
        'transport' => 'admin-ajax.php',
        'operation' => 'idlm_get_lead_details + idlm_get_lead_activity',
        'roles' => array('administrator', 'representative'),
        'priority' => 'critical',
    ),
    array(
        'id' => 'kanban-stage-load',
        'surface' => 'Pipeline',
        'transport' => 'admin-ajax.php',
        'operation' => 'idlm_get_kanban_leads',
        'roles' => array('administrator', 'assignment_manager', 'representative'),
        'priority' => 'critical',
    ),
    array(
        'id' => 'quotation-list',
        'surface' => 'Quotation workspace',
        'transport' => 'authenticated page request',
        'operation' => 'idlm_view=quotations',
        'roles' => array('administrator', 'assignment_manager', 'representative'),
        'priority' => 'critical',
    ),
    array(
        'id' => 'quotation-save',
        'surface' => 'Quotation editor',
        'transport' => 'admin-post.php',
        'operation' => 'idlm_save_quote',
        'roles' => array('administrator', 'assignment_manager', 'representative'),
        'priority' => 'critical',
    ),
    array(
        'id' => 'webhook-ingestion',
        'surface' => 'Inbound integration',
        'transport' => 'REST',
        'operation' => 'POST /interior-design-lead-manager/v1/webhook',
        'roles' => array('scoped_webhook_key'),
        'priority' => 'critical',
    ),
    array(
        'id' => 'lead-import-batch',
        'surface' => 'Lead import',
        'transport' => 'admin-ajax.php',
        'operation' => 'idlm_import_leads_batch',
        'roles' => array('administrator'),
        'priority' => 'high',
    ),
    array(
        'id' => 'lead-export',
        'surface' => 'Lead export',
        'transport' => 'admin-ajax.php',
        'operation' => 'idlm_export_leads',
        'roles' => array('administrator', 'assignment_manager', 'representative'),
        'priority' => 'high',
    ),
    array(
        'id' => 'capi-retry',
        'surface' => 'CAPI operations',
        'transport' => 'admin-ajax.php / scheduled action',
        'operation' => 'idlm_retry_capi_events',
        'roles' => array('administrator'),
        'priority' => 'high',
    ),
    array(
        'id' => 'audience-sync',
        'surface' => 'Audience operations',
        'transport' => 'admin-ajax.php / scheduled action',
        'operation' => 'audience batch sync',
        'roles' => array('administrator', 'scheduler'),
        'priority' => 'high',
    ),
    array(
        'id' => 'mcp-summary-query',
        'surface' => 'MCP analytics',
        'transport' => 'REST / stdio JSON-RPC',
        'operation' => 'summary and bounded query tools',
        'roles' => array('scoped_mcp_key'),
        'priority' => 'high',
    ),
);
