<?php
/** MCP restricted grammar and data-grant contracts. Builder: Shashi. */
require_once __DIR__ . '/mcp-query-cases.php';
foreach (idlm_mcp_rejected_queries() as $scenario => $sql) {
    idlm_test('MCP rejects ' . $scenario . ' before database execution', static function() use ($sql): void {
        $before = $GLOBALS['wpdb']->last_get_results_query;
        $result = IDLM_MCP_Core::execute_tool('run_custom_query', array('sql' => $sql));
        assert_true(isset($result['error']));
        assert_same($before, $GLOBALS['wpdb']->last_get_results_query);
        assert_false(strpos($result['error'], 'SYNTHETIC-SECRET') !== false);
    });
}
idlm_test('MCP customer contact grant is independent from SQL and typed-tool grants', static function(): void {
    foreach (array('SELECT email FROM wp_idlm_leads','SELECT id FROM wp_idlm_leads WHERE phone = \'123\'','SELECT id FROM wp_idlm_leads ORDER BY email') as $sql) {
        assert_true(isset(IDLM_MCP_Core::execute_tool('run_custom_query', array('sql'=>$sql))['error']));
    }
    foreach (array('query_leads','get_lead_details') as $tool) {
        assert_true(isset(IDLM_MCP_Core::execute_tool($tool,array('id'=>1))['error']));
        assert_false(in_array($tool,array_column(IDLM_MCP_Core::get_tools_list(),'name'),true));
    }
    update_option('idlm_mcp_allow_pii','1');
    assert_false(isset(IDLM_MCP_Core::execute_tool('run_custom_query',array('sql'=>'SELECT email FROM wp_idlm_leads'))['error']));
    update_option(IDLM_Production_Security::MCP_GRANTS_OPTION,array('run_custom_query'));
    assert_true(isset(IDLM_MCP_Core::execute_tool('get_lead_details',array('id'=>1))['error']));
    update_option('idlm_mcp_allow_pii','0');
    assert_true(isset(IDLM_MCP_Core::execute_tool('run_custom_query',array('sql'=>'SELECT email FROM wp_idlm_leads'))['error']));
});
idlm_test('MCP safe CRM representative join supports explicit aggregates and pagination',static function(): void {
    $result=IDLM_MCP_Core::execute_tool('run_custom_query',array('sql'=>"SELECT u.ID AS rep_id, u.display_name AS rep_name, COUNT(l.id) AS count FROM wp_idlm_leads l LEFT JOIN wp_users u ON u.ID=l.assigned_rep_id WHERE l.stage IN ('Won','Qualified') GROUP BY u.ID,u.display_name ORDER BY count DESC LIMIT 999 OFFSET 2"));
    assert_false(isset($result['error']));
    assert_contains('LIMIT 200 OFFSET 2',$GLOBALS['wpdb']->last_get_results_query);
    assert_false(strpos($GLOBALS['wpdb']->last_get_results_query,'user_pass')!==false);
});
idlm_test('MCP parser consumes full bounded input and safely compiles quoted literal keywords',static function(): void {
    foreach (array("SELECT `id` FROM `wp_idlm_leads` WHERE stage = 'DROP JOIN FROM; -- O''Brien'", 'SELECT DATE(created_at),COUNT(*) FROM wp_idlm_leads GROUP BY created_at LIMIT 2,3', 'SELECT id FROM wp_idlm_leads WHERE (deal_value BETWEEN -2 AND 5 OR stage IS NOT NULL) AND id NOT IN (1,2)') as $sql) {
        assert_false(isset(IDLM_MCP_Core::execute_tool('run_custom_query',array('sql'=>$sql))['error']));
    }
    assert_true(isset(IDLM_MCP_Core::execute_tool('run_custom_query',array('sql'=>array()))['error']));
    assert_true(isset(IDLM_MCP_Core::execute_tool('run_custom_query',array('sql'=>str_repeat(' ',10001)))['error']));
    assert_true(isset(IDLM_MCP_Core::execute_tool('run_custom_query','bad-arguments')['error']));
});
idlm_test('MCP custom query driver exceptions stay private and restore error suppression',static function(): void {
    $original=$GLOBALS['wpdb'];
    $driver=new class {
        public $prefix='wp_'; public $users='wp_users'; public $suppressed=false;
        public function suppress_errors($value=true){$previous=$this->suppressed;$this->suppressed=$value;return $previous;}
        public function get_results($sql,$format){throw new RuntimeException('SYNTHETIC-SECRET driver details');}
    };
    $GLOBALS['wpdb']=$driver;
    try {
        $response=IDLM_MCP_Core::handle_json_rpc(array('jsonrpc'=>'2.0','id'=>9,'method'=>'tools/call','params'=>array('name'=>'run_custom_query','arguments'=>array('sql'=>'SELECT id FROM wp_idlm_leads'))));
        assert_true($response['result']['isError']);
        assert_contains('Query failed or exceeded its execution budget.',$response['result']['content'][0]['text']);
        assert_false(strpos(wp_json_encode($response),'SYNTHETIC-SECRET')!==false);
        assert_false($driver->suppressed);
    } finally {$GLOBALS['wpdb']=$original;}
});
