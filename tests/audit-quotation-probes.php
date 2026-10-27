<?php
/** Isolated audit observations; no WordPress/site DB boot. Author: Shashi. */
declare(strict_types=1);
require_once __DIR__ . '/bootstrap.php';

function quote_audit_observation(string $id, bool $safe, array $evidence): void {
    echo json_encode(array('scenario' => $id, 'status' => $safe ? 'PASS' : 'FAIL', 'evidence' => $evidence), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . PHP_EOL;
}
if (in_array('--forged-new-revision', $argv, true)) {
    idlm_test_reset_environment();
    idlm_test_login(7, array('sales_executive'), array('idlm_access_leads', 'idlm_view_quotes', 'idlm_create_quotes'));
    $GLOBALS['wpdb']->seed('idlm_leads', array(array('id' => 9, 'assigned_rep_id' => 7, 'first_name' => 'Own synthetic', 'last_name' => 'Lead'), array('id' => 10, 'assigned_rep_id' => 8)));
    $GLOBALS['wpdb']->seed('idlm_quotes', array(array('id' => 99, 'lead_id' => 10, 'quote_number' => 'VICTIM', 'current_revision_id' => 50)));
    $GLOBALS['wpdb']->seed('idlm_quote_revisions', array(array('id' => 50, 'quote_id' => 99, 'revision_number' => 1, 'description' => 'Victim synthetic revision')));
    $_POST = array('quote_id' => 0, 'revision_id' => 50, 'lead_id' => 9, 'items' => array(array('description' => 'Injected own item', 'quantity' => 1, 'unit_rate' => 100)));
    register_shutdown_function(static function(): void {
        $rows = $GLOBALS['wpdb']->rows('idlm_quote_revisions');
        $victim = array_values(array_filter($rows, static fn($row): bool => 50 === (int) $row['id']))[0];
        quote_audit_observation('QUO-S09-forged-new-revision', 99 === (int) $victim['quote_id'], array('victim_revision_after' => $victim, 'quotes_after' => $GLOBALS['wpdb']->rows('idlm_quotes')));
    });
    try {
        IDLM_Quotation_Controller::handle_save();
    } catch (IDLM_Test_Response $response) {
        if (403 !== $response->status) { throw $response; }
    }
    exit;
}
idlm_test_reset_environment();
idlm_test_login(7, array('sales_executive'), array('idlm_access_leads', 'idlm_view_quotes', 'idlm_create_quotes', 'idlm_edit_quote_drafts', 'idlm_create_quote_revisions'));
$GLOBALS['wpdb']->seed('idlm_leads', array(array('id' => 9, 'assigned_rep_id' => 8, 'first_name' => 'Synthetic', 'last_name' => 'Only')));
$GLOBALS['idlm_test_options']['idlm_role_lead_scopes'] = array('sales_executive' => 'all');
$view = IDLM_Quotation_Policy::can_view_lead(9);
$mutate = IDLM_Lead_Access_Policy::can_access(9);
$quotation_mutate = IDLM_Quotation_Policy::can_mutate_lead(9);
quote_audit_observation('QUO-S01-other-owner-mutation-gate', !$quotation_mutate || $mutate, array('quotation_read_gate' => $view, 'quotation_save_gate' => $quotation_mutate, 'canonical_lead_mutation_gate' => $mutate));

$filters = new ReflectionMethod('IDLM_Quotation_Repository', 'quotation_list_filters');
if (PHP_VERSION_ID < 80100) { $filters->setAccessible(true); }
$GLOBALS['idlm_test_options']['idlm_role_lead_scopes'] = array('sales_executive' => 'assigned');
list($where, $params) = $filters->invoke(null, array('owner_user_id' => 7));
quote_audit_observation('QUO-S02-reassignment-list-scope', false !== strpos($where, 'assigned_rep_id'), array('where' => $where, 'params' => $params, 'current_lead_authorization' => IDLM_Quotation_Policy::can_view_lead(9)));

$GLOBALS['idlm_test_options']['idlm_quote_settings'] = array('currency_code' => 'USD');
$money = new ReflectionMethod('IDLM_Quotation_Renderer', 'money');
if (PHP_VERSION_ID < 80100) { $money->setAccessible(true); }
$display = $money->invoke(null, 118, 'INR');
quote_audit_observation('QUO-S03-persisted-currency', false !== strpos($display, '₹'), array('persisted_currency' => 'INR', 'configured_currency' => 'USD', 'rendered' => $display));

$rows = IDLM_Quotation_Calculator::parse_items(array(array('description' => 'Synthetic tiny area', 'length' => '.01', 'height' => '.01', 'unit_rate' => 1000)));
quote_audit_observation('QUO-S04-area-minimum', $rows[0]['line_subtotal'] === round($rows[0]['quantity'] * $rows[0]['unit_rate'], 2), $rows[0]);
$huge = IDLM_Quotation_Calculator::clean_decimal('1e309');
quote_audit_observation('QUO-S05-finite-decimal', is_finite($huge), array('input' => '1e309', 'is_finite' => is_finite($huge)));

// Every write below is rejected by an isolated test double, never a DB driver.
$GLOBALS['wpdb'] = new class {
    public string $prefix = 'wp_';
    public array $dbdelta = array();
    public array $queries = array();
    public function get_charset_collate(): string { return 'DEFAULT CHARACTER SET utf8mb4'; }
    public function query($query) { $this->queries[] = $query; return false; }
    public function prepare($query, ...$values) { return $query; }
    public function get_results($query): array { return array(); }
};
$GLOBALS['idlm_test_options'] = array('idlm_legacy_quote_migration_version' => '1.1.0');
IDLM_Quotation_Schema::install();
quote_audit_observation('QUO-S06-failed-migration-recovery', !get_option('idlm_quotation_schema_version') && !get_option('idlm_quotation_snapshot_migration_version') && !get_option('idlm_quotation_revision_lifecycle_migration_version'), array('all_query_writes' => false, 'schema_marker' => get_option('idlm_quotation_schema_version'), 'snapshot_marker' => get_option('idlm_quotation_snapshot_migration_version'), 'lifecycle_marker' => get_option('idlm_quotation_revision_lifecycle_migration_version')));
quote_audit_observation('QUO-S07-transactional-DDL', count(array_filter($GLOBALS['wpdb']->dbdelta, static fn($ddl): bool => false !== stripos($ddl, 'ENGINE=InnoDB'))) === 10, array('tables' => count($GLOBALS['wpdb']->dbdelta), 'explicit_innodb_tables' => count(array_filter($GLOBALS['wpdb']->dbdelta, static fn($ddl): bool => false !== stripos($ddl, 'ENGINE=InnoDB')))));

foreach (array('=1+1', "\t=SUM(A1)", ' @SUM(A1)', '-2+3') as $formula) {
    $safe = IDLM_Quotation_Exporter::csv_text($formula);
    quote_audit_observation('QUO-S08-formula-' . bin2hex(substr($formula, 0, 1)), 0 === strpos($safe, "'"), array('input' => $formula, 'output' => $safe));
}
