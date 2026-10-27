<?php
/** Real MySQL projection, pre-execution rejection and budget contracts. Shashi. */
require_once dirname(__DIR__) . '/mcp-query-cases.php';
if (!defined('IDLM_MCP_MAX_REQUEST_SECONDS')) { define('IDLM_MCP_MAX_REQUEST_SECONDS', 0.25); }
idlm_integration_test('MCP adversarial grammar and authentication fields reject before native SQL with either PII grant',static function(): void {
    global $wpdb;
    update_option('idlm_mcp_enabled','1');
    update_option(IDLM_Production_Security::MCP_GRANTS_OPTION,array('run_custom_query','query_leads','get_lead_details'));
    foreach(array('0','1') as $pii) {
        update_option('idlm_mcp_allow_pii',$pii);
        IDLM_Production_Security::mcp_allowed_tools(); IDLM_Production_Security::mcp_pii_allowed();
        $queries=array(); $observe=static function($sql) use (&$queries) { $queries[]=$sql; return $sql; };
        add_filter('query',$observe);
        try {
            foreach(idlm_mcp_rejected_queries($wpdb->prefix,$wpdb->users) as $id=>$sql) {
                $result=IDLM_MCP_Core::execute_tool('run_custom_query',array('sql'=>$sql));
                idlm_integration_assert_true(isset($result['error']),'Accepted '.$id.' pii='.$pii);
                idlm_integration_assert_same(array(),$queries,'Rejection executed SQL: '.$id);
                idlm_integration_assert_true(strpos($result['error'],'SYNTHETIC-SECRET')===false);
            }
        } finally { remove_filter('query',$observe); }
    }
});
idlm_integration_test('MCP safe representative projection, literal encoding, PII revocation and real row budgets',static function(): void {
    global $wpdb;
    $rep=wp_insert_user(array('user_login'=>'mcp_synthetic_rep','user_pass'=>'SYNTHETIC-AUTH-ONLY','display_name'=>'MCP Synthetic Rep'));
    $unrelated=wp_insert_user(array('user_login'=>'mcp_unrelated_user','user_pass'=>'SYNTHETIC-UNRELATED','display_name'=>'PRIVATE-SYNTHETIC-UNRELATED'));
    $values=array();
    for($i=0;$i<205;$i++){ $values[]=$wpdb->prepare('(%s,%s,%s,%d,%s,%s)','MCP-SYNTHETIC','mcp.synthetic@example.test',"O'Brien; -- DROP",$rep,'Won','Qualified'); }
    idlm_integration_assert_true(false!==$wpdb->query("INSERT INTO {$wpdb->prefix}idlm_leads (source,email,project_name,assigned_rep_id,stage,lead_quality) VALUES ".implode(',',$values)));
    update_option('idlm_mcp_allow_pii','0');
    $rows=IDLM_MCP_Core::execute_tool('run_custom_query',array('sql'=>"SELECT u.ID AS rep_id,u.display_name AS rep_name,COUNT(l.id) AS total FROM {$wpdb->prefix}idlm_leads l JOIN {$wpdb->users} u ON u.ID=l.assigned_rep_id WHERE l.source='MCP-SYNTHETIC' GROUP BY u.ID,u.display_name ORDER BY total DESC"));
    idlm_integration_assert_same(1,count($rows)); idlm_integration_assert_same((string)$rep,$rows[0]['rep_id']); idlm_integration_assert_same('205',$rows[0]['total']);
    $safe=IDLM_MCP_Core::execute_tool('run_custom_query',array('sql'=>"SELECT ID,display_name FROM {$wpdb->users}"));
    idlm_integration_assert_true(!in_array((string)$unrelated,array_column($safe,'ID'),true));
    $query="SELECT id FROM {$wpdb->prefix}idlm_leads WHERE source='MCP-SYNTHETIC' ORDER BY id LIMIT 999";
    idlm_integration_assert_same(200,count(IDLM_MCP_Core::execute_tool('run_custom_query',array('sql'=>$query))));
    idlm_integration_assert_same(5,count(IDLM_MCP_Core::execute_tool('run_custom_query',array('sql'=>$query.' OFFSET 200'))));
    $labels=IDLM_MCP_Core::execute_tool('run_custom_query',array('sql'=>"SELECT COUNT(id), MAX(`id`) FROM {$wpdb->prefix}idlm_leads WHERE source='MCP-SYNTHETIC'"));
    idlm_integration_assert_same(array('COUNT(id)','MAX(`id`)'),array_keys($labels[0]));
    idlm_integration_assert_true(isset(IDLM_MCP_Core::execute_tool('run_custom_query',array('sql'=>"SELECT email FROM {$wpdb->prefix}idlm_leads"))['error']));
    update_option('idlm_mcp_allow_pii','1');
    $query="SELECT email FROM {$wpdb->prefix}idlm_leads WHERE project_name='O''Brien; -- DROP' LIMIT 1";
    foreach(array('', 'ANSI_QUOTES,NO_BACKSLASH_ESCAPES') as $mode) {
        $old=$wpdb->get_var('SELECT @@SESSION.sql_mode'); $wpdb->query($wpdb->prepare('SET SESSION sql_mode = %s',$mode));
        try { idlm_integration_assert_same('mcp.synthetic@example.test',IDLM_MCP_Core::execute_tool('run_custom_query',array('sql'=>$query))[0]['email']); }
        finally { $wpdb->query($wpdb->prepare('SET SESSION sql_mode = %s',$old)); }
    }
    update_option('idlm_mcp_allow_pii','0');
    idlm_integration_assert_true(isset(IDLM_MCP_Core::execute_tool('run_custom_query',array('sql'=>$query))['error']));
});
idlm_integration_test('MCP MySQL statement budget terminates expensive aggregate and redacts database errors',static function(): void {
    global $wpdb;
    $table=$wpdb->prefix.'idlm_leads';
    $sql="SELECT COUNT(*) FROM $table l JOIN $table x ON x.source=l.source JOIN $table y ON y.source=x.source JOIN $table z ON z.source=y.source WHERE l.source='MCP-SYNTHETIC'";
    $start=microtime(true); $result=IDLM_MCP_Core::execute_tool('run_custom_query',array('sql'=>$sql)); $elapsed=microtime(true)-$start;
    idlm_integration_assert_same('Query failed or exceeded its execution budget.',$result['error'] ?? '');
    idlm_integration_assert_true($elapsed<2.0,'MySQL statement budget did not stop expensive query');
    idlm_integration_assert_true(stripos($wpdb->last_error,'maximum statement execution time')!==false,'Query failed for a reason other than statement time');
    idlm_integration_assert_true(strpos(wp_json_encode($result),'MCP-SYNTHETIC')===false);
    $driver=new mysqli_driver(); $previous=$driver->report_mode;
    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
    try {
        $strict=IDLM_MCP_Core::execute_tool('run_custom_query',array('sql'=>$sql));
        idlm_integration_assert_same('Query failed or exceeded its execution budget.',$strict['error'] ?? '');
    } finally {mysqli_report($previous);}
    // Suppression state is restored, and the connection remains usable.
    idlm_integration_assert_same('1',$wpdb->get_var('SELECT 1'));
});
