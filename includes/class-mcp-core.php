<?php
/**
 * Model Context Protocol (MCP) Shared Engine Class
 * Author: Shashi
 *
 * Provides a secure, read-only interface to the CRM leads database.
 */

if (!defined('ABSPATH')) {
    exit;
}

class IDLM_MCP_Core {

    /**
     * Get list of supported MCP tools and their schemas
     */
    public static function get_tools_list() {
        $tools = array(
            array(
                'name' => 'get_leads_summary',
                'description' => 'Get high-level summary metrics of CRM leads, including counts by stage, source, lead quality, total won deal value, and response time metrics.',
                'inputSchema' => array(
                    'type' => 'object',
                    'properties' => array(
                        'days_ago' => array(
                            'type' => 'integer',
                            'description' => 'Filter leads created within this many days ago (optional).'
                        )
                    )
                )
            ),
            array(
                'name' => 'query_leads',
                'description' => 'Query and search leads in the CRM database with filters.',
                'inputSchema' => array(
                    'type' => 'object',
                    'properties' => array(
                        'stage' => array(
                            'type' => 'string',
                            'description' => 'Filter by lead stage (e.g., \'New\', \'Contacted\', \'Qualified\', \'Won\', \'Lost\').'
                        ),
                        'source' => array(
                            'type' => 'string',
                            'description' => 'Filter by lead source (e.g., \'Meta Ads\', \'Google Ads\', \'Website\', \'Referral\').'
                        ),
                        'lead_quality' => array(
                            'type' => 'string',
                            'description' => 'Filter by lead quality (e.g., \'Genuine\', \'Junk\', \'Warm\').'
                        ),
                        'assigned_rep_id' => array(
                            'type' => 'integer',
                            'description' => 'Filter by assigned representative user ID.'
                        ),
                        'limit' => array(
                            'type' => 'integer',
                            'description' => 'Maximum number of leads to return (default 20, max 100).'
                        ),
                        'offset' => array(
                            'type' => 'integer',
                            'description' => 'Offset for pagination (default 0).'
                        )
                    )
                )
            ),
            array(
                'name' => 'get_lead_details',
                'description' => 'Get detailed profile information for a specific lead, including their 200 most recent activity records.',
                'inputSchema' => array(
                    'type' => 'object',
                    'properties' => array(
                        'id' => array(
                            'type' => 'integer',
                            'description' => 'The unique lead ID.'
                        )
                    ),
                    'required' => array('id')
                )
            ),
            array(
                'name' => 'get_reps_performance',
                'description' => 'Get performance statistics for all sales representatives, including assigned leads, won rate, total won deal values, and average follow-up/responsiveness speed.',
                'inputSchema' => array(
                    'type' => 'object',
                    'properties' => (object) array()
                )
            ),
            array(
                'name' => 'run_custom_query',
                'description' => 'Run a restricted SELECT with explicit approved fields, filters, aggregates and equality JOINs. Star, nested queries, comments and unsupported syntax are denied. Contact fields require a separate PII grant; row and MySQL statement-time budgets apply.',
                'inputSchema' => array(
                    'type' => 'object',
                    'properties' => array(
                        'sql' => array(
                            'type' => 'string',
                            'description' => 'The read-only SELECT SQL query to execute.'
                        )
                    ),
                    'required' => array('sql')
                )
            )
        );

        return array_values(array_filter($tools, static function($tool) {
            return IDLM_Production_Security::is_mcp_tool_allowed($tool['name']) &&
                (!in_array($tool['name'], array('query_leads', 'get_lead_details'), true) || IDLM_Production_Security::mcp_pii_allowed());
        }));
    }

    /**
     * Parse and handle standard JSON-RPC requests
     */
    public static function handle_json_rpc($request) {
        if (!IDLM_Production_Security::is_mcp_enabled()) {
            return array(
                'jsonrpc' => '2.0',
                'id' => isset($request['id']) ? $request['id'] : null,
                'error' => array(
                    'code' => -32003,
                    'message' => 'MCP access is disabled for this environment.'
                )
            );
        }
        if (!is_array($request) || !isset($request['method']) || !is_string($request['method'])) {
            return array(
                'jsonrpc' => '2.0',
                'id' => null,
                'error' => array(
                    'code' => -32600,
                    'message' => 'Invalid Request'
                )
            );
        }

        $id = isset($request['id']) ? $request['id'] : null;
        $method = $request['method'];
        $params = isset($request['params']) ? $request['params'] : array();

        try {
            switch ($method) {
                case 'initialize':
                    return array(
                        'jsonrpc' => '2.0',
                        'id' => $id,
                        'result' => array(
                            'protocolVersion' => '2024-11-05',
                            'capabilities' => array(
                                'tools' => (object) array()
                            ),
                            'serverInfo' => array(
                                'name' => 'interior-design-crm-mcp',
                                'version' => '1.0.0'
                            )
                        )
                    );

                case 'notifications/initialized':
                    return null;

                case 'tools/list':
                    return array(
                        'jsonrpc' => '2.0',
                        'id' => $id,
                        'result' => array(
                            'tools' => self::get_tools_list()
                        )
                    );

                case 'tools/call':
                    $tool_name = isset($params['name']) ? $params['name'] : '';
                    $tool_args = isset($params['arguments']) ? $params['arguments'] : array();
                    
                    $result = self::execute_tool($tool_name, $tool_args);
                    
                    if (isset($result['error'])) {
                        return array(
                            'jsonrpc' => '2.0',
                            'id' => $id,
                            'result' => array(
                                'content' => array(
                                    array(
                                        'type' => 'text',
                                        'text' => 'Error: ' . $result['error']
                                    )
                                ),
                                'isError' => true
                            )
                        );
                    } else {
                        return array(
                            'jsonrpc' => '2.0',
                            'id' => $id,
                            'result' => array(
                                'content' => array(
                                    array(
                                        'type' => 'text',
                                        'text' => json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
                                    )
                                ),
                                'isError' => false
                            )
                        );
                    }

                default:
                    return array(
                        'jsonrpc' => '2.0',
                        'id' => $id,
                        'error' => array(
                            'code' => -32601,
                            'message' => 'Method not found: ' . $method
                        )
                    );
            }
        } catch (Exception $e) {
            return array(
                'jsonrpc' => '2.0',
                'id' => $id,
                'error' => array(
                    'code' => -32603,
                    'message' => 'Internal error: ' . $e->getMessage()
                )
            );
        }
    }

    /**
     * Core execution engine for read-only database tools
     */
    private static function prepare_custom_query($sql, $allowed_tables) {
        require_once __DIR__ . '/class-mcp-query-policy.php';
        return IDLM_MCP_Query_Policy::prepare($sql, $allowed_tables);
    }

    public static function execute_tool($name, $arguments) {
        global $wpdb;
        $table_leads = $wpdb->prefix . 'idlm_leads';

        if (!is_string($name) || !is_array($arguments)) {
            return array('error' => 'Invalid tool name or arguments.');
        }

        if (!IDLM_Production_Security::is_mcp_enabled()) {
            return array('error' => 'MCP access is disabled for this environment.');
        }
        if (!IDLM_Production_Security::is_mcp_tool_allowed($name)) {
            return array('error' => 'Tool access denied by the current MCP credential policy.');
        }

        if (in_array($name, array('query_leads', 'get_lead_details'), true) && !IDLM_Production_Security::mcp_pii_allowed()) {
            return array('error' => 'Tool access denied: An explicit MCP PII grant is required.');
        }

        switch ($name) {
            case 'get_leads_summary':
                $where = "is_duplicate = 0";
                $params = array();

                if (!empty($arguments['days_ago'])) {
                    $days = intval($arguments['days_ago']);
                    $where .= " AND created_at >= DATE_SUB(NOW(), INTERVAL %d DAY)";
                    $params[] = $days;
                }

                if (!empty($params)) {
                    // phpcs:disable PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- REL007-SQL-0377: execute_tool(): fixed typed-tool projections and bound values; custom SELECT passes MCP_Query_Policy restricted grammar/field/table/PII/time limits; live tool grants are checked before execution.
                    $total_leads = $wpdb->get_var("SELECT COUNT(*) FROM $table_leads");
                    $non_dup_leads = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $table_leads WHERE $where", $params));
                    $total_won_val = $wpdb->get_var($wpdb->prepare("SELECT SUM(deal_value) FROM $table_leads WHERE stage = 'Won' AND is_duplicate = 0 AND conversion_time >= DATE_SUB(NOW(), INTERVAL %d DAY)", $days)) ?: 0;
                    $avg_won_val = $wpdb->get_var($wpdb->prepare("SELECT AVG(deal_value) FROM $table_leads WHERE stage = 'Won' AND deal_value > 0 AND is_duplicate = 0 AND conversion_time >= DATE_SUB(NOW(), INTERVAL %d DAY)", $days)) ?: 0;
                    $stages = $wpdb->get_results($wpdb->prepare("SELECT stage, COUNT(*) as count FROM $table_leads WHERE $where GROUP BY stage", $params), ARRAY_A);
                    $sources = $wpdb->get_results($wpdb->prepare("SELECT source, COUNT(*) as count FROM $table_leads WHERE $where GROUP BY source", $params), ARRAY_A);
                    $qualities = $wpdb->get_results($wpdb->prepare("SELECT lead_quality, COUNT(*) as count FROM $table_leads WHERE $where GROUP BY lead_quality", $params), ARRAY_A);
                    $avg_connected = $wpdb->get_var($wpdb->prepare("SELECT AVG(time_to_connected) FROM $table_leads WHERE time_to_connected IS NOT NULL AND $where", $params)) ?: 0;
                    $avg_won = $wpdb->get_var($wpdb->prepare("SELECT AVG(time_to_won) FROM $table_leads WHERE time_to_won IS NOT NULL AND $where", $params)) ?: 0;
                    // phpcs:enable PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
                } else {
                    // phpcs:disable PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- REL007-SQL-0378: execute_tool(): fixed typed-tool projections and bound values; custom SELECT passes MCP_Query_Policy restricted grammar/field/table/PII/time limits; live tool grants are checked before execution.
                    $total_leads = $wpdb->get_var("SELECT COUNT(*) FROM $table_leads");
                    $non_dup_leads = $wpdb->get_var("SELECT COUNT(*) FROM $table_leads WHERE is_duplicate = 0");
                    $total_won_val = $wpdb->get_var("SELECT SUM(deal_value) FROM $table_leads WHERE stage = 'Won' AND is_duplicate = 0") ?: 0;
                    $avg_won_val = $wpdb->get_var("SELECT AVG(deal_value) FROM $table_leads WHERE stage = 'Won' AND deal_value > 0 AND is_duplicate = 0") ?: 0;
                    $stages = $wpdb->get_results("SELECT stage, COUNT(*) as count FROM $table_leads WHERE is_duplicate = 0 GROUP BY stage", ARRAY_A);
                    $sources = $wpdb->get_results("SELECT source, COUNT(*) as count FROM $table_leads WHERE is_duplicate = 0 GROUP BY source", ARRAY_A);
                    $qualities = $wpdb->get_results("SELECT lead_quality, COUNT(*) as count FROM $table_leads WHERE is_duplicate = 0 GROUP BY lead_quality", ARRAY_A);
                    $avg_connected = $wpdb->get_var("SELECT AVG(time_to_connected) FROM $table_leads WHERE time_to_connected IS NOT NULL AND is_duplicate = 0") ?: 0;
                    $avg_won = $wpdb->get_var("SELECT AVG(time_to_won) FROM $table_leads WHERE time_to_won IS NOT NULL AND is_duplicate = 0") ?: 0;
                    // phpcs:enable PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                }

                return array(
                    'total_leads_in_db' => intval($total_leads),
                    'non_duplicate_leads' => intval($non_dup_leads),
                    'financial_metrics' => array(
                        'total_won_deal_value_inr' => floatval($total_won_val),
                        'average_won_deal_value_inr' => round(floatval($avg_won_val), 2),
                        'currency_code' => get_option('idlm_currency_code', 'INR'),
                        'total_won_deal_value_' . strtolower(get_option('idlm_currency_code', 'INR')) => floatval($total_won_val),
                        'average_won_deal_value_' . strtolower(get_option('idlm_currency_code', 'INR')) => round(floatval($avg_won_val), 2)
                    ),
                    'leads_by_stage' => $stages,
                    'leads_by_source' => $sources,
                    'leads_by_quality' => $qualities,
                    'responsiveness_metrics' => array(
                        'avg_time_to_connected_hours' => round(floatval($avg_connected) / 3600, 2),
                        'avg_time_to_won_days' => round(floatval($avg_won) / 86400, 2)
                    )
                );

            case 'query_leads':
                $where = "is_duplicate = 0";
                $params = array();

                if (isset($arguments['stage']) && $arguments['stage'] !== '') {
                    $where .= " AND stage = %s";
                    $params[] = sanitize_text_field($arguments['stage']);
                }
                if (isset($arguments['source']) && $arguments['source'] !== '') {
                    $where .= " AND source = %s";
                    $params[] = sanitize_text_field($arguments['source']);
                }
                if (isset($arguments['lead_quality']) && $arguments['lead_quality'] !== '') {
                    $where .= " AND lead_quality = %s";
                    $params[] = sanitize_text_field($arguments['lead_quality']);
                }
                if (isset($arguments['assigned_rep_id'])) {
                    $where .= " AND assigned_rep_id = %d";
                    $params[] = intval($arguments['assigned_rep_id']);
                }

                $limit = isset($arguments['limit']) ? min(100, max(1, intval($arguments['limit']))) : 20;
                $offset = isset($arguments['offset']) ? max(0, intval($arguments['offset'])) : 0;

                $where .= " LIMIT %d OFFSET %d";
                $params[] = $limit;
                $params[] = $offset;

                $query = "SELECT id, first_name, last_name, email, phone, budget, deal_value, stage, source, lead_quality, assigned_rep_id, created_at FROM $table_leads WHERE $where";
                // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- REL007-SQL-0379: execute_tool(): fixed typed-tool projections and bound values; custom SELECT passes MCP_Query_Policy restricted grammar/field/table/PII/time limits; live tool grants are checked before execution.
                // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
                $results = $wpdb->get_results($wpdb->prepare($query, $params), ARRAY_A);
                // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
                
                foreach ($results as &$lead) {
                    $rep_id = intval($lead['assigned_rep_id']);
                    if ($rep_id === 0) {
                        $lead['assigned_rep_name'] = 'Unassigned';
                    } else {
                        $rep_user = get_userdata($rep_id);
                        $lead['assigned_rep_name'] = $rep_user ? $rep_user->display_name : 'Unknown Rep';
                    }
                }
                return $results;

            case 'get_lead_details':
                $lead_id = intval($arguments['id']);
                // phpcs:disable PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- REL007-SQL-0380: execute_tool(): fixed typed-tool projections and bound values; custom SELECT passes MCP_Query_Policy restricted grammar/field/table/PII/time limits; live tool grants are checked before execution.
                $lead = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_leads WHERE id = %d", $lead_id), ARRAY_A);
                // phpcs:enable PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                if (!$lead) {
                    return array('error' => "Lead not found for ID $lead_id");
                }

                $table_activity = $wpdb->prefix . 'idlm_lead_activity_log';
                // phpcs:disable PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- REL007-SQL-0381: execute_tool(): fixed typed-tool projections and bound values; custom SELECT passes MCP_Query_Policy restricted grammar/field/table/PII/time limits; live tool grants are checked before execution.
                $activities = $wpdb->get_results($wpdb->prepare("SELECT id, activity_type, details, created_at FROM $table_activity WHERE lead_id = %d ORDER BY created_at DESC LIMIT 200", $lead_id), ARRAY_A);
                // phpcs:enable PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared

                $rep = null;
                if (!empty($lead['assigned_rep_id'])) {
                    $rep_user = get_userdata($lead['assigned_rep_id']);
                    if ($rep_user) {
                        $rep = array(
                            'id' => $rep_user->ID,
                            'name' => $rep_user->display_name,
                            'email' => $rep_user->user_email
                        );
                    }
                }

                $lead['activity_log'] = $activities;
                $lead['assigned_rep'] = $rep;
                return $lead;

            case 'get_reps_performance':
                $timezone = wp_timezone();
                $yesterday = new DateTime('yesterday', $timezone);
                $yesterday_start = $yesterday->format('Y-m-d 00:00:00');
                $yesterday_end = $yesterday->format('Y-m-d 23:59:59');
                // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- REL007-SQL-0382: execute_tool(): fixed typed-tool projections and bound values; custom SELECT passes MCP_Query_Policy restricted grammar/field/table/PII/time limits; live tool grants are checked before execution.
                $query = $wpdb->prepare("
                    SELECT 
                        l.assigned_rep_id,
                        COUNT(l.id) as total_assigned_leads,
                        SUM(CASE WHEN l.stage NOT IN ('Won', 'Lost') AND l.lead_quality != 'Unqualified' THEN 1 ELSE 0 END) as active_assigned_leads,
                        SUM(CASE WHEN l.lead_quality = 'Qualified' THEN 1 ELSE 0 END) as qualified_assigned_leads,
                        SUM(CASE WHEN l.handoff_at BETWEEN %s AND %s AND l.handoff_status IN ('manually_assigned', 'auto_assigned') THEN 1 ELSE 0 END) as received_yesterday,
                        SUM(CASE WHEN l.stage = 'Won' THEN 1 ELSE 0 END) as won_leads,
                        SUM(CASE WHEN l.stage = 'Won' THEN l.deal_value ELSE 0 END) as total_deal_value,
                        AVG(l.time_to_connected) as avg_time_to_connected_sec,
                        AVG(l.time_to_won) as avg_time_to_won_sec
                    FROM $table_leads l
                    WHERE l.is_duplicate = 0
                    GROUP BY l.assigned_rep_id
                ", $yesterday_start, $yesterday_end);
                // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
                $performance_data = $wpdb->get_results($query, ARRAY_A);
                // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared

                $result_reps = array();
                foreach ($performance_data as $row) {
                    $rep_id = intval($row['assigned_rep_id']);
                    if ($rep_id === 0) {
                        $rep_name = 'Unassigned';
                    } else {
                        $rep_user = get_userdata($rep_id);
                        $rep_name = $rep_user ? $rep_user->display_name : 'Unknown User';
                    }
                    
                    $total = intval($row['total_assigned_leads']);
                    $won = intval($row['won_leads']);
                    $conversion_rate = $total > 0 ? round(($won / $total) * 100, 2) : 0.00;
                    
                    $result_reps[] = array(
                        'rep_id' => $rep_id,
                        'rep_name' => $rep_name,
                        'total_assigned_leads' => $total,
                        'active_assigned_leads' => intval($row['active_assigned_leads']),
                        'qualified_assigned_leads' => intval($row['qualified_assigned_leads']),
                        'received_yesterday' => intval($row['received_yesterday']),
                        'won_leads' => $won,
                        'conversion_rate_percent' => $conversion_rate,
                        'total_won_deal_value_inr' => floatval($row['total_deal_value']),
                        'total_won_deal_value_' . strtolower(get_option('idlm_currency_code', 'INR')) => floatval($row['total_deal_value']),
                        'avg_time_to_connected_hours' => $row['avg_time_to_connected_sec'] !== null ? round(floatval($row['avg_time_to_connected_sec']) / 3600, 2) : null,
                        'avg_time_to_won_days' => $row['avg_time_to_won_sec'] !== null ? round(floatval($row['avg_time_to_won_sec']) / 86400, 2) : null,
                    );
                }
                return $result_reps;

            case 'run_custom_query':
                $allowed_tables = array_map('strtolower', array(
                    $wpdb->prefix . 'idlm_leads',
                    $wpdb->prefix . 'idlm_lead_activity_log',
                    $wpdb->prefix . 'idlm_capi_logs',
                    $wpdb->prefix . 'idlm_audience_sync_logs',
                    $wpdb->prefix . 'idlm_telemetry_logs',
                    $wpdb->users,
                ));
                $prepared_query = self::prepare_custom_query($arguments['sql'] ?? '', $allowed_tables);
                if (isset($prepared_query['error'])) {
                    return $prepared_query;
                }

                $previous = $wpdb->suppress_errors(true);
                try {
                    // Compiler validates identifiers and prepares every literal.
                    // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- REL007-SQL-0383: execute_tool(): fixed typed-tool projections and bound values; custom SELECT passes MCP_Query_Policy restricted grammar/field/table/PII/time limits; live tool grants are checked before execution.
                    // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
                    $results = $wpdb->get_results($prepared_query['query'], ARRAY_A);
                    // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
                    if ($wpdb->last_error) { return array('error' => 'Query failed or exceeded its execution budget.'); }
                } catch (Throwable $error) {
                    return array('error' => 'Query failed or exceeded its execution budget.');
                } finally { $wpdb->suppress_errors($previous); }
                return array_slice((array) $results, 0, IDLM_Production_Security::mcp_query_row_limit());

            default:
                return array('error' => "Unknown tool: $name");
        }
    }
}
