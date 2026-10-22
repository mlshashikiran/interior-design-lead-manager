<?php
/** Isolated missing-prerequisite probes. Builder: Shashi. */
declare(strict_types=1);
require __DIR__ . '/bootstrap.php';
idlm_test_reset_environment();
$mode = $argv[1] ?? '';
$GLOBALS['idlm_runtime_probe_mode'] = $mode;
function is_admin() { return true; }
// Namespaced PHP API seams model absent modules without changing this host's PHP.
// Production source is evaluated verbatim; normal calls still use real PHP APIs.
eval('namespace IDLM_Runtime_Probe;
use \\WP_Error; use \\ZipArchive; use \\IDLM_Quotation_Document; use \\IDLM_Quotations;
function extension_loaded($name) { return $GLOBALS["idlm_runtime_probe_mode"] !== "pdf-" . $name && \\extension_loaded($name); }
function class_exists($name) { return !("xlsx-no-zip" === $GLOBALS["idlm_runtime_probe_mode"] && "ZipArchive" === $name) && \\class_exists($name); }
function function_exists($name) { return "get_temp_dir" === $name || \\function_exists($name); }
function is_readable($path) { return !("pdf-no-renderer" === $GLOBALS["idlm_runtime_probe_mode"] && substr($path, -16) === "autoload.inc.php") && \\is_readable($path); }
function get_temp_dir() { return "xlsx-no-temp" === $GLOBALS["idlm_runtime_probe_mode"] ? sys_get_temp_dir() . "/idlm-absent-" . bin2hex(random_bytes(8)) : sys_get_temp_dir(); }
function wp_safe_redirect($url) { if (strpos($url, "PDF+download+could+not+be+generated") === false && strpos($url, "PDF%20download%20could%20not%20be%20generated") === false) { throw new \\RuntimeException("Unexpected fallback"); } throw new \\RuntimeException("expected-pdf-fallback"); }
' . substr(file_get_contents(dirname(__DIR__) . '/includes/class-quotation-exporter.php'), 5));
$bundle = (object) array('id'=>41, 'quote_number'=>'Q-2026-000041', 'items'=>array(), 'revision'=>(object) array('id'=>71,'quote_id'=>41,'revision_number'=>1,'customer_name'=>'Synthetic','project_name'=>'Runtime probe','currency_code'=>'INR','created_at'=>'2026-10-09 10:00:00','subtotal'=>0,'taxable_total'=>0,'cgst_total'=>0,'sgst_total'=>0,'igst_total'=>0,'grand_total'=>0));
$before = serialize($GLOBALS['idlm_test_options']);
set_error_handler(static function($severity, $message): void { throw new RuntimeException($message); });
try {
    if (strpos($mode, 'pdf-') === 0) {
        ob_start();
        try {
            $method = new ReflectionMethod('IDLM_Runtime_Probe\\IDLM_Quotation_Exporter', 'download_pdf');
            if (PHP_VERSION_ID < 80100) { $method->setAccessible(true); } $method->invoke(null, $bundle);
            throw new RuntimeException('PDF unexpectedly generated');
        } catch (RuntimeException $error) {
            if ($error->getMessage() !== 'expected-pdf-fallback') { throw $error; }
        }
        $output = ob_get_clean();
        if ($output !== '' || class_exists('Dompdf\\Dompdf', false)) { throw new RuntimeException('PDF sent bytes or loaded renderer before rejection'); }
        // Alternative export data remains usable without the renderer.
        if (count(IDLM_Quotation_Exporter::export_data($bundle)['summary']) < 14) { throw new RuntimeException('Tabular fallback unavailable'); }
    } elseif (strpos($mode, 'xlsx-') === 0) {
        $path = IDLM_Runtime_Probe\IDLM_Quotation_Exporter::build_xlsx($bundle);
        if ($mode === 'xlsx-ok') {
            if (is_wp_error($path)) { throw new RuntimeException('XLSX unavailable'); }
            try {
                $zip = new ZipArchive();
                if ($zip->open($path) !== true || strpos($zip->getFromName('xl/workbook.xml'), 'Summary') === false) { throw new RuntimeException('Invalid XLSX'); }
                $zip->close();
            } finally { unlink($path); }
        } else {
            $expected = $mode === 'xlsx-no-zip' ? 'idlm_xlsx_unavailable' : 'idlm_xlsx_temp';
            if (!is_wp_error($path) || $path->get_error_code() !== $expected) { throw new RuntimeException('XLSX failure not controlled'); }
        }
    } elseif ($mode === 'crypto-disabled') {
        update_option('idlm_meta_access_token', 'synthetic-original');
        if (IDLM_Secret_Store::set('idlm_meta_access_token', 'replacement') !== false || IDLM_Secret_Store::get('idlm_meta_access_token') !== '' || get_option('idlm_meta_access_token') !== 'synthetic-original') { throw new RuntimeException('Missing crypto did not preserve credential'); }
        $encrypted = $argv[2] ?? '';
        if (strpos($encrypted, IDLM_Secret_Store::PREFIX) !== 0) { throw new RuntimeException('Missing encrypted fixture'); }
        update_option('idlm_meta_access_token', $encrypted);
        if (IDLM_Secret_Store::get('idlm_meta_access_token') !== '' || IDLM_Secret_Store::set('idlm_meta_access_token', 'replacement') !== false || get_option('idlm_meta_access_token') !== $encrypted) { throw new RuntimeException('Unavailable decryption changed encrypted bytes'); }
        $before = serialize($GLOBALS['idlm_test_options']);
    } else { throw new RuntimeException('Unknown probe'); }
    if ($before !== serialize($GLOBALS['idlm_test_options'])) { throw new RuntimeException('Probe changed settings'); }
    echo "PASS REL005 $mode\n";
} finally { restore_error_handler(); }
