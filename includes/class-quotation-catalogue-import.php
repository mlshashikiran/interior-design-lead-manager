<?php
/** Shared catalogue CSV preview and checked import. Builder: Shashi. */
if (!defined('ABSPATH')) { exit; }

final class IDLM_Quotation_Catalogue_Import {
    /** Accept explicit western/Indian thousands groups, without loosening quote numeric inputs. */
    public static function rate_units($value) {
        $value = trim((string) $value);
        if (strpos($value, ',') !== false) {
            if (!preg_match('/^\+?(?:[0-9]{1,3}(?:,[0-9]{3})+|[0-9]{1,2}(?:,[0-9]{2})*,[0-9]{3})(?:\.[0-9]+)?$/D', $value)) {
                return new WP_Error('idlm_catalogue_rate', 'Default rate has invalid thousands grouping. Use 1790.00, 1,790.00 or 1,00,000.00.');
            }
            $value = str_replace(',', '', $value);
        }
        return IDLM_Quotation_Calculator::decimal_units($value, 0, IDLM_Quotation_Calculator::MAX_AMOUNT_UNITS, 'Default rate');
    }

    /** Read only: no terms, items, transients or uploaded files are persisted by preview. */
    public static function inspect($path) {
        // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen -- Native CSV streaming reads only the temporary file validated by the upload boundary; WP_Filesystem does not expose a CSV stream.
        $handle = fopen($path, 'r');
        if (!$handle) { return new WP_Error('idlm_catalogue_file', 'Unable to read the CSV file.'); }
        try {
            $header = fgetcsv($handle, 0, ',', '"', '');
            if (!$header) { return new WP_Error('idlm_catalogue_header', 'The CSV is empty.'); }
            $header[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string) $header[0]);
            $headers = array_map(static function($v) { return sanitize_key(str_replace(array(' ', '-'), '_', trim((string) $v))); }, $header);
            $missing = array_diff(array('item_code', 'item_name', 'category'), $headers);
            if ($missing || in_array('', $headers, true) || count($headers) !== count(array_unique($headers)) || count($headers) > 20) {
                return new WP_Error('idlm_catalogue_header', $missing ? 'Missing required CSV headers: ' . implode(', ', $missing) . '.' : 'CSV headers must be nonempty and unique, with no more than 20 columns.');
            }
            $report = array('total'=>0, 'valid'=>0, 'invalid'=>0, 'duplicates'=>0, 'generated_codes'=>0, 'rows'=>array());
            $seen = array(); $line = 1;
            while (($values = fgetcsv($handle, 0, ',', '"', '')) !== false) {
                $line++;
                if ($line > 2001) { return new WP_Error('idlm_catalogue_limit', 'Catalogue imports are limited to 2,000 rows per file.'); }
                if (count($values) === 1 && trim((string) $values[0]) === '') { continue; }
                $report['total']++;
                $errors = array(); $state = 'valid';
                if (count($values) !== count($headers)) { $errors[] = sprintf('Expected %d columns; found %d. Check CSV quoting and commas.', count($headers), count($values)); }
                if (array_filter($values, static function($v) { return strlen((string) $v) > 5000; })) { $errors[] = 'A cell exceeds the 5,000-byte limit.'; }
                $row = array_combine($headers, array_slice(array_pad($values, count($headers), ''), 0, count($headers)));
                $data = array();
                foreach (array('item_code'=>100, 'category'=>100, 'item_name'=>255, 'unit'=>30) as $field=>$max) {
                    $data[$field] = sanitize_text_field($row[$field] ?? ('unit' === $field ? 'Nos' : ''));
                    if ('item_code' !== $field && $data[$field] === '') { $errors[] = str_replace('_', ' ', ucfirst($field)) . ' is required.'; }
                    $length = function_exists('mb_strlen') ? mb_strlen($data[$field], 'UTF-8') : strlen($data[$field]);
                    if ($length > $max) { $errors[] = str_replace('_', ' ', ucfirst($field)) . " exceeds {$max} characters."; }
                }
                $data['description'] = sanitize_textarea_field($row['description'] ?? '');
                $wood = strtolower(trim((string) ($row['is_wood'] ?? 'no')));
                if (!in_array($wood, array('', 'yes', 'no', '1', '0', 'true', 'false'), true)) { $errors[] = 'Wood must be Yes/No, True/False or 1/0.'; }
                $data['is_wood'] = in_array($wood, array('yes', '1', 'true'), true) ? 1 : 0;
                $data['status'] = strtolower(trim((string) ($row['status'] ?? 'active')));
                if (!in_array($data['status'], array('active', 'inactive'), true)) { $errors[] = 'Status must be active or inactive.'; }
                $rate = self::rate_units($row['default_rate'] ?? '0');
                if (is_wp_error($rate)) { $errors[] = $rate->get_error_message(); }
                $data['default_rate'] = is_wp_error($rate) ? '' : number_format($rate / 100, 2, '.', '');
                $code_key = strtolower($data['item_code']);
                if ($errors) { $state = 'invalid'; $report['invalid']++; }
                elseif ($code_key !== '' && (isset($seen[$code_key]) || IDLM_Quotation_Repository::catalogue_item_code_exists($data['item_code']))) {
                    $state = 'duplicate'; $report['duplicates']++;
                    $errors[] = isset($seen[$code_key]) ? 'Item code repeats CSV row ' . $seen[$code_key] . '; this row will be skipped.' : 'Item code already exists in the catalogue; this row will be skipped.';
                } else {
                    $report['valid']++;
                    if ($code_key === '') { $report['generated_codes']++; } else { $seen[$code_key] = $line; }
                }
                $report['rows'][] = array('row'=>$line, 'state'=>$state, 'messages'=>$errors, 'data'=>$data);
            }
            if (!$report['total']) { return new WP_Error('idlm_catalogue_empty', 'The CSV contains no item rows.'); }
            return $report;
        // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- Close the owned temporary CSV stream on every exit.
        } finally { fclose($handle); }
    }

    /** Revalidate inside the existing engine-checked transaction; never trust preview rows from the browser. */
    // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- import_file(): literal COMMIT/ROLLBACK control only for the engine-checked owned transaction; row writes delegate to the prepared repository and cannot use query caching.
    public static function import_file($path) {
        global $wpdb;
        $transaction = IDLM_Quotation_Repository::begin_checked_transaction('catalogue');
        if (is_wp_error($transaction)) { return $transaction; }
        try {
            $report = self::inspect($path);
            if (is_wp_error($report)) { $wpdb->query('ROLLBACK'); return $report; }
            $report['imported'] = 0;
            foreach ($report['rows'] as &$row) {
                if ($row['state'] !== 'valid') { continue; }
                $data = $row['data'];
                // Recheck after earlier inserts, including generated codes and database collation equivalents.
                if ($data['item_code'] !== '' && IDLM_Quotation_Repository::catalogue_item_code_exists($data['item_code'])) {
                    $row['state'] = 'duplicate'; $row['messages'][] = 'Item code now exists; this row was skipped.';
                    $report['duplicates']++; $report['valid']--; continue;
                }
                $now = current_time('mysql');
                if (false === IDLM_Quotation_Repository::ensure_catalogue_term('category', $data['category'], get_current_user_id(), $now)
                    || false === IDLM_Quotation_Repository::ensure_catalogue_term('unit', $data['unit'], get_current_user_id(), $now)) {
                    $wpdb->query('ROLLBACK');
                    return new WP_Error('idlm_catalogue_write', 'CSV row ' . $row['row'] . ': unable to save category or unit. The entire import was rolled back; no items were imported.');
                }
                $data['created_by'] = get_current_user_id(); $data['created_at'] = $now; $data['updated_at'] = $now;
                if (!IDLM_Quotation_Repository::insert_catalogue_item($data)) {
                    $wpdb->query('ROLLBACK');
                    return new WP_Error('idlm_catalogue_write', 'CSV row ' . $row['row'] . ': unable to save catalogue item. The entire import was rolled back; no items were imported.');
                }
                $row['state'] = 'imported'; $report['imported']++;
            }
            unset($row);
            if (false === $wpdb->query('COMMIT')) {
                $wpdb->query('ROLLBACK');
                return new WP_Error('idlm_catalogue_commit', 'Unable to commit the catalogue import; no items were imported.');
            }
            return $report;
        } catch (Throwable $error) {
            $wpdb->query('ROLLBACK');
            return new WP_Error('idlm_catalogue_write', 'The import could not be completed and was rolled back; no items were imported.');
        }
    }
    // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
}
