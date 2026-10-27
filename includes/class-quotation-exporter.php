<?php
/**
 * Secure, revision-scoped quotation downloads.
 *
 * Builder: Shashi
 */

if (!defined('ABSPATH')) {
    exit;
}

final class IDLM_Quotation_Exporter {
    const DOMPDF_VERSION = '3.1.6';
    const DOMPDF_SHA256 = '05df8ee4907325ed2e09a139de9784325f761b43a049297155499270163ac94d';

    /** Create a signed export URL for the exact persisted revision. */
    public static function export_url($quote_id, $revision_id, $format) {
        $format = self::format($format);
        if (!$format) {
            return '';
        }
        $url = admin_url('admin-post.php?action=idlm_download_quote_' . $format . '&quote_id=' . absint($quote_id) . '&revision_id=' . absint($revision_id));
        return wp_nonce_url($url, 'idlm_download_quote_' . $format);
    }

    /**
     * Resolve an export only after capability, nonce, revision ownership, and
     * object-level lead visibility have all been checked.
     *
     * @return object|WP_Error Quote bundle on success.
     */
    public static function authorize($quote_id, $revision_id, $format, $verify_nonce = true) {
        $format = self::format($format);
        if (!$format || !IDLM_Quotation_Policy::can('idlm_view_quotes') || !IDLM_Quotation_Policy::can('idlm_download_quote_pdf')) {
            return new WP_Error('idlm_quote_export_denied', 'You do not have permission to download this quotation.');
        }
        foreach (array($quote_id, $revision_id) as $identifier) {
            if ((!is_int($identifier) && (!is_string($identifier) || !ctype_digit($identifier))) || $identifier <= 0) {
                return new WP_Error('idlm_quote_export_not_found', 'Quotation not found.');
            }
        }
        $quote_id = absint($quote_id);
        $revision_id = absint($revision_id);
        if (!$quote_id || !$revision_id) {
            return new WP_Error('idlm_quote_export_not_found', 'Quotation not found.');
        }
        if ($verify_nonce) {
            check_admin_referer('idlm_download_quote_' . $format, '_wpnonce');
        }
        $bundle = IDLM_Quotation_Repository::get_quote_bundle($quote_id, $revision_id);
        if (!$bundle || !$bundle->revision || absint($bundle->revision->quote_id) !== absint($quote_id) || !IDLM_Quotation_Policy::can_view_lead($bundle->lead_id)) {
            return new WP_Error('idlm_quote_export_not_found', 'Quotation not found.');
        }
        return $bundle;
    }

    /** Admin-post callback shared by PDF, CSV, and XLSX endpoints. */
    public static function download($format) {
        $format = self::format($format);
        // phpcs:disable WordPress.Security.NonceVerification.Recommended,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- REL007-BOUNDARY-0034: download(): authorize checks capabilities, dedicated format nonce, decimal identifier types and revision/lead relationship before output; native CSV/XLSX temporary handles preserve binary bytes, enforce extension availability and remove only owned temporary paths.
        $bundle = self::authorize(wp_unslash($_GET['quote_id'] ?? 0), wp_unslash($_GET['revision_id'] ?? 0), $format);
        // phpcs:enable WordPress.Security.NonceVerification.Recommended,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
        if (is_wp_error($bundle)) {
            wp_die(esc_html($bundle->get_error_message()), 'Quotation download', array('response' => 'idlm_quote_export_nonce' === $bundle->get_error_code() ? 403 : 404));
        }
        if ('pdf' === $format) {
            self::download_pdf($bundle);
        } elseif ('csv' === $format) {
            self::download_csv($bundle);
        } else {
            self::download_xlsx($bundle);
        }
    }

    /** Return the safe customer-facing filename stem (without extension). */
    public static function filename_stem($bundle) {
        $revision = $bundle->revision;
        $name = sanitize_file_name((string) ($revision->customer_name ?: 'customer'));
        $name = trim($name, '.-_');
        $name = $name ?: 'customer';
        return sanitize_file_name((string) $bundle->quote_number) . '-' . self::revision_label($revision) . '-' . $name;
    }

    /** Build only the permitted persisted revision fields for tabular exports. */
    public static function export_data($bundle) {
        $revision = $bundle->revision;
        $line_items = array();
        foreach ((array) $bundle->items as $item) {
            // Headers and optional/internal calculation rows are not exported.
            if ('header' === ($item->row_type ?? 'item') || !empty($item->is_optional)) {
                continue;
            }
            $line_items[] = array(
                'section' => (string) ($item->section_name ?: 'General'),
                'item_code_name' => trim((string) ($item->item_code ?: '') . (($item->item_code && $item->item_name) ? ' — ' : '') . (string) ($item->item_name ?: '')),
                'description' => (string) $item->description,
                'width' => (float) $item->length,
                'height' => (float) $item->height,
                'quantity' => (float) $item->quantity,
                'unit' => (string) $item->unit,
                'unit_rate' => (float) $item->unit_rate,
                'line_total' => (float) $item->line_subtotal,
            );
        }
        $tax_total = (float) $revision->cgst_total + (float) $revision->sgst_total + (float) $revision->igst_total;
        $summary = array(
            array('Quote number', (string) $bundle->quote_number, 'text'),
            array('Revision', self::revision_label($revision), 'text'),
            array('Customer', (string) $revision->customer_name, 'text'),
            array('Project', (string) $revision->project_name, 'text'),
            array('Currency', (string) $revision->currency_code, 'text'),
            array('Created', (string) $revision->created_at, 'text'),
            array('Subtotal', (float) $revision->subtotal, 'currency'),
            array('Wooden subtotal', (float) ($revision->wooden_subtotal ?? 0), 'currency'),
            array('Wooden discount', (float) ($revision->wooden_discount_amount ?? 0), 'currency'),
            array('Total', (float) ($revision->total_before_additional_discount ?? $revision->subtotal), 'currency'),
            array('Additional discount', (float) ($revision->additional_discount_amount ?? 0), 'currency'),
            array('Taxable total', (float) $revision->taxable_total, 'currency'),
            array('GST (18%)', $tax_total, 'currency'),
            array('Grand total', (float) $revision->grand_total, 'currency'),
        );
        $terms = IDLM_Quotation_Document::read($revision);
        $summary[] = array('Document terms', is_array($terms) ? 'Saved snapshot v1' : (is_wp_error($terms) ? 'Invalid saved snapshot' : 'Historical terms not captured'), 'text');
        if (is_array($terms)) {
            foreach (array('cover_page_enabled'=>'Cover enabled','cover_page_html'=>'Cover content','after_quote_enabled'=>'Closing enabled','after_quote_html'=>'Closing content','pdf_footer'=>'Footer terms') as $field=>$label) {
                $summary[] = array($label, (string) $terms[$field], 'text');
            }
            foreach ($terms['milestones'] as $row) {
                $summary[] = array('Payment percentage: ' . $row['name'], $row['percentage'] . '%', 'text');
                $summary[] = array('Payment amount: ' . $row['name'], (float) $row['amount'], 'currency');
            }
        }
        return array('line_items' => $line_items, 'summary' => $summary);
    }

    /** RFC 4180-compatible streamed CSV with Excel-safe strings. */
    private static function download_csv($bundle) {
        self::send_download_headers(self::filename_stem($bundle) . '.csv', 'text/csv; charset=UTF-8');
        $out = fopen('php://output', 'w');
        echo "\xEF\xBB\xBF";
        $data = self::export_data($bundle);
        fputcsv($out, array('Line items'), ',', '"', '');
        fputcsv($out, array('Section', 'Item code/name', 'Description', 'Width', 'Height', 'Quantity', 'Unit', 'Unit rate', 'Line total'), ',', '"', '');
        foreach ($data['line_items'] as $row) {
            fputcsv($out, array(self::csv_text($row['section']), self::csv_text($row['item_code_name']), self::csv_text($row['description']), $row['width'], $row['height'], $row['quantity'], self::csv_text($row['unit']), $row['unit_rate'], $row['line_total']), ',', '"', '');
        }
        fputcsv($out, array(), ',', '"', '');
        fputcsv($out, array('Summary'), ',', '"', '');
        fputcsv($out, array('Field', 'Value'), ',', '"', '');
        foreach ($data['summary'] as $row) {
            fputcsv($out, array(self::csv_text($row[0]), 'currency' === $row[2] ? $row[1] : self::csv_text($row[1])), ',', '"', '');
        }
        // phpcs:disable WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- REL007-BOUNDARY-0035: download_csv(): authorize checks capabilities, dedicated format nonce, decimal identifier types and revision/lead relationship before output; native CSV/XLSX temporary handles preserve binary bytes, enforce extension availability and remove only owned temporary paths.
        fclose($out);
        // phpcs:enable WordPress.WP.AlternativeFunctions.file_system_operations_fclose
        exit;
    }

    /** Real OOXML workbook with typed numeric/currency cells. */
    private static function download_xlsx($bundle) {
        $path = self::build_xlsx($bundle);
        if (is_wp_error($path)) {
            wp_die('Unable to generate this spreadsheet. Please try again.', 'Quotation download', array('response' => 500));
        }
        self::send_download_headers(self::filename_stem($bundle) . '.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        // phpcs:disable WordPress.WP.AlternativeFunctions.file_system_operations_readfile -- REL007-BOUNDARY-0036: download_xlsx(): authorize checks capabilities, dedicated format nonce, decimal identifier types and revision/lead relationship before output; native CSV/XLSX temporary handles preserve binary bytes, enforce extension availability and remove only owned temporary paths.
        readfile($path);
        // phpcs:enable WordPress.WP.AlternativeFunctions.file_system_operations_readfile
        // phpcs:disable WordPress.WP.AlternativeFunctions.unlink_unlink -- REL007-BOUNDARY-0037: download_xlsx(): authorize checks capabilities, dedicated format nonce, decimal identifier types and revision/lead relationship before output; native CSV/XLSX temporary handles preserve binary bytes, enforce extension availability and remove only owned temporary paths.
        @unlink($path);
        // phpcs:enable WordPress.WP.AlternativeFunctions.unlink_unlink
        exit;
    }

    /** Build the workbook in a temporary file and return its path. */
    public static function build_xlsx($bundle) {
        if (!class_exists('ZipArchive')) {
            return new WP_Error('idlm_xlsx_unavailable', 'Spreadsheet support is unavailable.');
        }
        $temp_dir = function_exists('get_temp_dir') ? get_temp_dir() : sys_get_temp_dir();
        // phpcs:disable WordPress.WP.AlternativeFunctions.file_system_operations_is_writable -- REL007-BOUNDARY-0038: build_xlsx(): authorize checks capabilities, dedicated format nonce, decimal identifier types and revision/lead relationship before output; native CSV/XLSX temporary handles preserve binary bytes, enforce extension availability and remove only owned temporary paths.
        if (!is_dir($temp_dir) || !is_writable($temp_dir)) {
        // phpcs:enable WordPress.WP.AlternativeFunctions.file_system_operations_is_writable
            return new WP_Error('idlm_xlsx_temp', 'Unable to prepare spreadsheet download.');
        }
        $path = @tempnam($temp_dir, 'idlm-xlsx-');
        if (!$path) {
            return new WP_Error('idlm_xlsx_temp', 'Unable to prepare spreadsheet download.');
        }
        $zip = new ZipArchive();
        if (true !== $zip->open($path, ZipArchive::OVERWRITE)) {
            // phpcs:disable WordPress.WP.AlternativeFunctions.unlink_unlink -- REL007-BOUNDARY-0039: build_xlsx(): authorize checks capabilities, dedicated format nonce, decimal identifier types and revision/lead relationship before output; native CSV/XLSX temporary handles preserve binary bytes, enforce extension availability and remove only owned temporary paths.
            @unlink($path);
            // phpcs:enable WordPress.WP.AlternativeFunctions.unlink_unlink
            return new WP_Error('idlm_xlsx_zip', 'Unable to prepare spreadsheet download.');
        }
        $data = self::export_data($bundle);
        $zip->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/><Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/><Override PartName="/xl/worksheets/sheet2.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/></Types>');
        $zip->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>');
        $zip->addFromString('xl/workbook.xml', '<?xml version="1.0" encoding="UTF-8"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="Summary" sheetId="1" r:id="rId1"/><sheet name="Line items" sheetId="2" r:id="rId2"/></sheets></workbook>');
        $zip->addFromString('xl/_rels/workbook.xml.rels', '<?xml version="1.0" encoding="UTF-8"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/><Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet2.xml"/><Relationship Id="rId3" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/></Relationships>');
        $zip->addFromString('xl/styles.xml', self::xlsx_styles($bundle->revision->currency_code));
        $zip->addFromString('xl/worksheets/sheet1.xml', self::xlsx_summary_sheet($data['summary']));
        $zip->addFromString('xl/worksheets/sheet2.xml', self::xlsx_line_items_sheet($data['line_items']));
        $zip->close();
        return $path;
    }

    /** Check renderer prerequisites before loading vendor code or sending binary headers. */
    private static function pdf_available() {
        foreach (array('dom', 'mbstring', 'iconv', 'gd') as $extension) {
            if (!extension_loaded($extension)) {
                return false;
            }
        }
        return is_readable(IDLM_PATH . 'vendor/dompdf/autoload.inc.php');
    }

    private static function download_pdf($bundle) {
        $autoload = IDLM_PATH . 'vendor/dompdf/autoload.inc.php';
        if (!self::pdf_available()) {
            self::pdf_failure_notice($bundle);
        }
        try {
            require_once $autoload;
            $options = new Dompdf\Options();
            $options->set('isRemoteEnabled', false);
            $options->set('chroot', IDLM_PATH);
            $options->set('defaultFont', 'DejaVu Sans');
            $options->set('defaultMediaType', 'print');
            $dompdf = new Dompdf\Dompdf($options);
            $html = self::dompdf_document_html($bundle);
            $dompdf->setPaper(IDLM_Quotation_Exporter::paper_size(), 'portrait');
            $dompdf->loadHtml($html, 'UTF-8');
            $dompdf->render();
            $pdf = $dompdf->output();
            if (!$pdf) {
                throw new RuntimeException('Empty renderer output.');
            }
            self::send_download_headers(self::filename_stem($bundle) . '.pdf', 'application/pdf');
            // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Authorized binary PDF; HTML escaping corrupts the file.
            echo $pdf;
            exit;
        } catch (Throwable $exception) {
            self::pdf_failure_notice($bundle);
        }
    }

    /** Deliberately generic: renderer failures never reach the browser. */
    private static function pdf_failure_notice($bundle) {
        $fallback = add_query_arg(array('action' => 'view', 'quote_id' => absint($bundle->id), 'revision_id' => absint($bundle->revision->id), 'idlm_error' => 'PDF download could not be generated. You can still use Preview / Print or download CSV/XLSX.'), IDLM_Quotations::view_url());
        wp_safe_redirect($fallback);
        exit;
    }

    /**
     * Dompdf does not implement browser Grid/Flex layout. Keep the exact
     * customer-facing print markup, then add its table-based equivalent so
     * the downloaded PDF retains the preview's document hierarchy and tables.
     */
    private static function dompdf_document_html($bundle) {
        $html = preg_replace('#<button[^>]*class="button no-print"[^>]*>.*?</button>#s', '', IDLM_Quotation_Renderer::render_print_document($bundle));
        $html = self::inline_customer_document_images($html);
        $css = '<style id="idlm-dompdf-layout">'
            . '@page{margin:10mm}body,body *{font-family:DejaVu Sans,sans-serif!important}body{font-size:10px;color:#1f2937;line-height:1.35}.idlm-print,.idlm-print-custom{width:100%;max-width:none;margin:0;padding:0}.idlm-print-cover{page-break-after:always;page-break-inside:auto;break-inside:auto}.idlm-print-cover>.idlm-quotation-cover>table,.idlm-print-cover>.idlm-quotation-cover>table>tbody,.idlm-print-cover>.idlm-quotation-cover>table>tbody>tr,.idlm-print-cover>.idlm-quotation-cover>table>tbody>tr>td{display:block!important;width:100%!important;height:auto!important;page-break-inside:auto!important;break-inside:auto!important}.idlm-print-cover .idlm-quotation-cover>table>tbody>tr>td{padding:8px 0 4px!important}.idlm-print-cover .idlm-quotation-cover div{line-height:1.2!important}.idlm-print-cover .idlm-quotation-cover img[width="42"]{width:34px!important;height:34px!important;margin-bottom:2px!important}.idlm-print-cover .idlm-quotation-cover table[style*="border-spacing"]{margin-bottom:7px!important;border-spacing:4px!important}.idlm-print-cover .idlm-quotation-cover td[style*="padding:14px 10px"]{padding:5px 6px!important}.idlm-print-after{page-break-before:always;font-size:9px!important;line-height:1.35!important}.idlm-print-after p,.idlm-print-after li,.idlm-print-after td,.idlm-print-after th,.idlm-print-after div,.idlm-print-after span{font-size:9px!important;line-height:1.35!important}.idlm-print-after h1{font-size:15px!important;line-height:1.2!important}.idlm-print-after h2{font-size:13px!important;line-height:1.25!important}.idlm-print-after h3,.idlm-print-after h4{font-size:11px!important;line-height:1.3!important}.idlm-print-document-head{display:table!important;width:100%;margin:0 0 18px;padding:0 0 14px;border-bottom:3px solid #334155}.idlm-print-document-head>.idlm-print-logo-box,.idlm-print-document-head>.idlm-print-document-meta{display:table-cell!important;vertical-align:top}.idlm-print-logo-box{width:34%;padding:8px 14px 8px 0!important;text-align:left}.idlm-print-logo{max-width:160px;max-height:65px}.idlm-print-logo-fallback{margin:0;font-size:17px}.idlm-print-document-meta{width:66%;margin:0}.idlm-print-document-meta div{display:block!important;min-height:16px;padding:2px 0}.idlm-print-document-meta dt{float:left;width:37%;font-weight:700}.idlm-print-document-meta dd{margin:0 0 0 38%;text-align:right}.idlm-print-document-meta div:after,.idlm-readonly-totals div:after{display:block;clear:both;content:""}.idlm-print-customer-details,.idlm-print-summary,.idlm-print-breakdown,.idlm-readonly-section{width:100%;margin:0 0 16px;border:1px solid #94a3b8;border-radius:0;overflow:visible}.idlm-print-customer-details>h2,.idlm-print-summary>h2,.idlm-print-breakdown>h2,.idlm-readonly-section>header{margin:0;padding:9px 12px;border-bottom:1px solid #94a3b8;background:#f8fafc;color:#1f2937;font-size:13px}.idlm-print-summary>h2{background:#334155;color:#fff}.idlm-print table,.idlm-print .idlm-quote-items{display:table!important;width:100%!important;min-width:0!important;max-width:none!important;table-layout:fixed!important;border-spacing:0;border-collapse:collapse!important}.idlm-print .idlm-quote-items thead{display:table-header-group!important}.idlm-print .idlm-quote-items tbody{display:table-row-group!important}.idlm-print .idlm-quote-items tfoot{display:table-footer-group!important}.idlm-print .idlm-quote-items tr{display:table-row!important}.idlm-print .idlm-quote-items th,.idlm-print .idlm-quote-items td{display:table-cell!important;padding:5px 3px!important;border:0;border-bottom:1px solid #d7dde5;vertical-align:top;overflow-wrap:anywhere;word-wrap:break-word}.idlm-print .idlm-quote-items th{background:#f8fafc;color:#475569;font-size:7px;font-weight:700;letter-spacing:0;text-transform:uppercase}.idlm-print .idlm-quote-items th:nth-child(n+4),.idlm-print .idlm-quote-items td:nth-child(n+4){text-align:right}.idlm-print .idlm-quote-items th:nth-child(7),.idlm-print .idlm-quote-items td:nth-child(7){text-align:left}.idlm-print .idlm-quote-items td small{display:block;margin-top:2px;color:#64748b;font-size:7px;line-height:1.25}.idlm-print .idlm-quote-items tfoot th{padding:7px 5px!important;background:#f8fafc;color:#1f2937;font-size:9px}.idlm-print .idlm-readonly-header-row th{display:table-cell!important;padding:7px 10px!important;border-left:3px solid #c99644;background:#f8fafc;color:#1f2937;font-size:9px;text-align:left!important}.idlm-print-summary-grid{display:block!important}.idlm-print-summary-grid>div:nth-child(2){display:none!important}.idlm-print-summary-grid h3{margin:0;padding:8px 10px;background:#f8fafc;font-size:9px}.idlm-print-summary-grid table th,.idlm-print-summary-grid table td,.idlm-print-customer-details th,.idlm-print-customer-details td{padding:6px 7px;border-bottom:1px solid #d7dde5;vertical-align:top;overflow-wrap:anywhere}.idlm-print-customer-details th{background:#f8fafc;color:#475569;font-size:8px;text-transform:uppercase}.idlm-readonly-totals{width:48%;margin:12px 12px 12px auto}.idlm-readonly-totals div{display:block!important;padding:5px 0;border-bottom:1px solid #d7dde5}.idlm-readonly-totals dt{float:left;width:68%;color:#475569}.idlm-readonly-totals dd{margin:0 0 0 69%;font-weight:700;text-align:right}.idlm-grand-total{font-size:12px}.idlm-print-footer{margin-top:24px;padding-top:8px;border-top:1px solid #d7dde5;color:#64748b;font-size:8px;text-align:center}.idlm-print-after table{width:100%;border-collapse:collapse}.idlm-print-after th,.idlm-print-after td{padding:6px;border:1px solid #d7dde5;vertical-align:top}.idlm-print-after img{max-width:100%;height:auto}.no-print{display:none!important}'
            . '</style>';
        // These are the same nine visual proportions used by the browser
        // print table. Dompdf needs explicit table widths after Grid is
        // replaced with its compatible table layout.
        $css .= '<style id="idlm-dompdf-columns">.idlm-print .idlm-readonly-section .idlm-quote-items th:nth-child(1){width:3%!important}.idlm-print .idlm-readonly-section .idlm-quote-items th:nth-child(2){width:14%!important}.idlm-print .idlm-readonly-section .idlm-quote-items th:nth-child(3){width:19%!important}.idlm-print .idlm-readonly-section .idlm-quote-items th:nth-child(4),.idlm-print .idlm-readonly-section .idlm-quote-items th:nth-child(5){width:6%!important}.idlm-print .idlm-readonly-section .idlm-quote-items th:nth-child(6){width:8%!important}.idlm-print .idlm-readonly-section .idlm-quote-items th:nth-child(7){width:6%!important}.idlm-print .idlm-readonly-section .idlm-quote-items th:nth-child(8){width:10%!important}.idlm-print .idlm-readonly-section .idlm-quote-items th:nth-child(9){width:12%!important}</style>';
        return str_replace('</head>', $css . '</head>', $html);
    }

    /**
     * Preserve customer-uploaded branding without letting Dompdf fetch an
     * arbitrary URL. Only paths resolved below the active uploads directory
     * may become data URIs; every other remote image remains unavailable.
     */
    private static function inline_customer_document_images($html) {
        $uploads = function_exists('wp_upload_dir') ? wp_upload_dir() : array();
        $base_dir = realpath((string) ($uploads['basedir'] ?? ''));
        $base_url = rtrim((string) ($uploads['baseurl'] ?? ''), '/');
        $base_prefix = $base_dir ? rtrim($base_dir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR : '';
        $allowed_remote_urls = self::configured_customer_image_urls();
        return preg_replace_callback('~(<img\\b[^>]*?\\bsrc\\s*=\\s*)(["\\\'])([^"\\\']+)\\2~i', static function($matches) use ($base_url, $base_dir, $base_prefix, $allowed_remote_urls) {
            $url = html_entity_decode((string) $matches[3], ENT_QUOTES, 'UTF-8');
            if (0 === strpos($url, 'data:')) {
                return $matches[0];
            }
            $path = strtok($url, '?');
            if ($base_dir && $base_url && 0 === strpos($path, $base_url . '/')) {
                $relative = rawurldecode(substr($path, strlen($base_url) + 1));
                $candidate = realpath($base_dir . DIRECTORY_SEPARATOR . ltrim($relative, '/\\'));
            } else {
                $url_path = (string) wp_parse_url($path, PHP_URL_PATH);
                $marker = '/wp-content/uploads/';
                $position = strpos($url_path, $marker);
                $candidate = (!$base_dir || false === $position) ? false : realpath($base_dir . DIRECTORY_SEPARATOR . rawurldecode(substr($url_path, $position + strlen($marker))));
            }
            if ($candidate && 0 === strpos($candidate, $base_prefix) && is_file($candidate) && is_readable($candidate)) {
                $data_uri = self::local_image_data_uri($candidate);
                if ($data_uri) {
                    return $matches[1] . $matches[2] . $data_uri . $matches[2];
                }
            }
            $data_uri = self::remote_customer_image_data_uri($url, $allowed_remote_urls);
            return $data_uri ? $matches[1] . $matches[2] . $data_uri . $matches[2] : $matches[0];
        }, $html);
    }

    /** Return the exact image URLs configured by an administrator for this customer document. */
    private static function configured_customer_image_urls() {
        $settings = (array) get_option('idlm_quote_settings', array());
        $html = (string) ($settings['cover_page_html'] ?? '') . '<img src="' . esc_attr((string) ($settings['company_logo'] ?? '')) . '">';
        preg_match_all('~<img\\b[^>]*?\\bsrc\\s*=\\s*["\\\']([^"\\\']+)["\\\']~i', $html, $matches);
        return array_values(array_unique(array_map(static function($url) {
            return html_entity_decode((string) $url, ENT_QUOTES, 'UTF-8');
        }, $matches[1] ?? array())));
    }

    private static function local_image_data_uri($path) {
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $mimes = array('jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'gif' => 'image/gif', 'webp' => 'image/webp');
        if (!isset($mimes[$extension])) {
            return '';
        }
        $contents = file_get_contents($path);
        return false === $contents ? '' : 'data:' . $mimes[$extension] . ';base64,' . base64_encode($contents);
    }

    /**
     * Remote customer artwork is permitted only when it exactly matches the
     * saved cover/logo URL. WordPress rejects unsafe targets; redirects and
     * oversized responses are refused before data reaches the renderer.
     */
    private static function remote_customer_image_data_uri($url, $allowed_urls) {
        if (!function_exists('wp_safe_remote_get') || !in_array($url, $allowed_urls, true) || 'https' !== strtolower((string) wp_parse_url($url, PHP_URL_SCHEME))) {
            return '';
        }
        $response = wp_safe_remote_get($url, array('timeout' => 10, 'redirection' => 0, 'limit_response_size' => 2 * MB_IN_BYTES, 'reject_unsafe_urls' => true));
        if (is_wp_error($response) || 200 !== absint(wp_remote_retrieve_response_code($response))) {
            return '';
        }
        $content_type = strtolower(trim((string) wp_remote_retrieve_header($response, 'content-type')));
        $allowed_types = array('image/jpeg', 'image/png', 'image/gif', 'image/webp');
        if (!in_array($content_type, $allowed_types, true)) {
            return '';
        }
        $body = (string) wp_remote_retrieve_body($response);
        return '' === $body ? '' : 'data:' . $content_type . ';base64,' . base64_encode($body);
    }

    private static function send_download_headers($filename, $content_type) {
        while (ob_get_level()) { ob_end_clean(); }
        nocache_headers();
        header('Content-Type: ' . $content_type);
        header('X-Content-Type-Options: nosniff');
        header('Content-Disposition: attachment; filename="' . str_replace('"', '', $filename) . '"; filename*=UTF-8\'\'' . rawurlencode($filename));
    }

    public static function csv_text($value) {
        $value = (string) $value;
        // Preserve the source bytes, placing the text marker before any
        // spreadsheet-ignored whitespace/control/format prefix. The byte
        // check also protects ASCII formulas when input is invalid UTF-8.
        $unsafe = preg_match('/^[\x00-\x20\x7f]*[=+\-@\t\r\n]/', $value)
            || preg_match('/^[\p{Z}\p{Cf}\x00-\x20\x7f]*[=+\-@＝＋－＠\t\r\n]/u', $value);
        return $unsafe ? "'" . $value : $value;
    }

    private static function format($format) {
        $format = sanitize_key((string) $format);
        return in_array($format, array('pdf', 'csv', 'xlsx'), true) ? $format : '';
    }

    private static function revision_label($revision) { return 'R' . str_pad((string) max(1, absint($revision->revision_number ?? 1)), 2, '0', STR_PAD_LEFT); }
    private static function paper_size() {
        $settings = (array) get_option('idlm_quote_settings', array());
        return IDLM_Quotations::normalize_paper_size($settings['paper_size'] ?? 'A4');
    }
    private static function xml($value) { return htmlspecialchars((string) $value, ENT_XML1 | ENT_QUOTES, 'UTF-8'); }
    private static function xlsx_cell($reference, $value, $style = 0, $numeric = false) { return $numeric ? '<c r="' . $reference . '" s="' . $style . '" t="n"><v>' . (float) $value . '</v></c>' : '<c r="' . $reference . '" s="' . $style . '" t="inlineStr"><is><t>' . self::xml(self::csv_text($value)) . '</t></is></c>'; }
    private static function xlsx_row($number, $cells) { return '<row r="' . $number . '">' . implode('', $cells) . '</row>'; }
    private static function xlsx_styles($currency) { $currency = preg_replace('/[^A-Z]/', '', strtoupper((string) $currency)); $currency = $currency ?: 'USD'; return '<?xml version="1.0" encoding="UTF-8"?><styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><numFmts count="1"><numFmt numFmtId="164" formatCode="[$' . self::xml($currency) . ']#,##0.00"/></numFmts><fonts count="2"><font><sz val="11"/><name val="Calibri"/></font><font><b/><sz val="11"/><name val="Calibri"/></font></fonts><fills count="2"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill></fills><borders count="1"><border/></borders><cellStyleXfs count="1"><xf/></cellStyleXfs><cellXfs count="3"><xf xfId="0"/><xf xfId="0" applyFont="1" fontId="1"/><xf xfId="0" applyNumberFormat="1" numFmtId="164"/></cellXfs></styleSheet>'; }
    private static function xlsx_summary_sheet($summary) { $rows = array(self::xlsx_row(1, array(self::xlsx_cell('A1', 'Field', 1), self::xlsx_cell('B1', 'Value', 1)))); foreach ($summary as $index => $item) { $row = $index + 2; $rows[] = self::xlsx_row($row, array(self::xlsx_cell('A' . $row, $item[0]), self::xlsx_cell('B' . $row, $item[1], 'currency' === $item[2] ? 2 : 0, 'currency' === $item[2]))); } return '<?xml version="1.0" encoding="UTF-8"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>' . implode('', $rows) . '</sheetData></worksheet>'; }
    private static function xlsx_line_items_sheet($items) { $headers = array('Section', 'Item code/name', 'Description', 'Width', 'Height', 'Quantity', 'Unit', 'Unit rate', 'Line total'); $cells = array(); foreach ($headers as $i => $header) { $cells[] = self::xlsx_cell(chr(65 + $i) . '1', $header, 1); } $rows = array(self::xlsx_row(1, $cells)); foreach ($items as $index => $item) { $row = $index + 2; $rows[] = self::xlsx_row($row, array(self::xlsx_cell('A' . $row, $item['section']), self::xlsx_cell('B' . $row, $item['item_code_name']), self::xlsx_cell('C' . $row, $item['description']), self::xlsx_cell('D' . $row, $item['width'], 0, true), self::xlsx_cell('E' . $row, $item['height'], 0, true), self::xlsx_cell('F' . $row, $item['quantity'], 0, true), self::xlsx_cell('G' . $row, $item['unit']), self::xlsx_cell('H' . $row, $item['unit_rate'], 2, true), self::xlsx_cell('I' . $row, $item['line_total'], 2, true))); } return '<?xml version="1.0" encoding="UTF-8"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>' . implode('', $rows) . '</sheetData></worksheet>'; }
}
