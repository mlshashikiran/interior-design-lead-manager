<?php
/** Decode the pinned checker's JSON or exact clean CLI response. Builder: Shashi. */
declare(strict_types=1);

final class IDLM_Package_Check_Result {
    public static function decode(string $stdout, int $exit_code, string $stderr): array {
        if ('' !== trim($stderr)) { throw new RuntimeException('Plugin Check wrote stderr; inspect the retained output.'); }
        $text = trim($stdout);
        // Plugin Check 2.1.0 uses WP_CLI::success before its format switch,
        // but only when BOTH its errors and warnings arrays are empty.
        if (0 === $exit_code && 'Success: Checks complete. No errors found.' === $text) {
            return array('diagnostics' => array(), 'output_format' => 'exact_clean_cli_success');
        }
        $diagnostics = json_decode($text, true);
        if ('[' !== substr($text, 0, 1) || JSON_ERROR_NONE !== json_last_error() || !is_array($diagnostics) || array_values($diagnostics) !== $diagnostics) {
            throw new RuntimeException('Plugin Check did not return a diagnostic list or exact clean success; inspect retained stdout.');
        }
        foreach ($diagnostics as $diagnostic) {
            if (!is_array($diagnostic) || !isset($diagnostic['file'], $diagnostic['line'], $diagnostic['column'], $diagnostic['type'], $diagnostic['code'], $diagnostic['message'])
                || !in_array($diagnostic['type'], array('ERROR', 'WARNING'), true)) {
                throw new RuntimeException('Plugin Check returned an invalid diagnostic record.');
            }
        }
        if (0 !== $exit_code && !$diagnostics) { throw new RuntimeException('Plugin Check failed without diagnostic records.'); }
        return array('diagnostics' => $diagnostics, 'output_format' => 'strict_json_diagnostics');
    }
}
