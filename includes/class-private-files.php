<?php
/** WordPress-contained confidential file storage and lifecycle. Builder: Shashi. */
if (!defined('ABSPATH')) { exit; }

final class IDLM_Private_Files {
    const VERSION = '1';
    const CHUNK_BYTES = 65536;
    const MAX_BYTES = 10485760;
    const MAX_ARCHIVE_BYTES = 52428800;

    private static function table($chunks = false) {
        global $wpdb;
        return $wpdb->prefix . ($chunks ? 'idlm_private_file_chunks' : 'idlm_private_files');
    }

    /** Called only by the core schema installer; verify before advancing our marker. */
    public static function install() {
        global $wpdb;
        $collate = $wpdb->get_charset_collate(); $files = self::table(); $chunks = self::table(true);
        dbDelta("CREATE TABLE $files (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            lead_id bigint(20) unsigned NOT NULL,
            attachment_id bigint(20) unsigned NOT NULL,
            purpose varchar(20) NOT NULL,
            filename varchar(255) NOT NULL,
            mime varchar(100) NOT NULL,
            byte_size bigint(20) unsigned NOT NULL,
            sha256 char(64) NOT NULL,
            state varchar(20) NOT NULL DEFAULT 'active',
            archive longtext NOT NULL,
            created_at datetime NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY attachment_id (attachment_id),
            KEY lead_state (lead_id,state)
        ) ENGINE=InnoDB $collate;");
        dbDelta("CREATE TABLE $chunks (
            file_id bigint(20) unsigned NOT NULL,
            variant int unsigned NOT NULL DEFAULT 0,
            part int unsigned NOT NULL,
            data longtext NOT NULL,
            PRIMARY KEY  (file_id,variant,part)
        ) ENGINE=InnoDB $collate;");
        if (self::ready()) { update_option('idlm_private_file_schema_version', self::VERSION, false); return true; }
        return false;
    }

    public static function ready() {
        global $wpdb;
        $suppressed = $wpdb->suppress_errors(true);
        try {
        foreach (array(self::table() => array('id','lead_id','attachment_id','purpose','filename','mime','byte_size','sha256','state','archive','created_at'), self::table(true) => array('file_id','variant','part','data')) as $table => $required) {
            // phpcs:disable PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- REL007-SQL-0001: ready(): table(bool) selects only file/chunk tables; lead/attachment/variant limits are bound; live ownership/reference and engine checks govern confidential file reads.
            $columns = $wpdb->get_col("SHOW COLUMNS FROM $table", 0);
            // phpcs:enable PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            if (!$columns || array_diff($required, $columns)) { return false; }
            // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- REL007-SQL-0002: ready(): table(bool) selects only file/chunk tables; lead/attachment/variant limits are bound; live ownership/reference and engine checks govern confidential file reads.
            if ('INNODB' !== strtoupper((string) $wpdb->get_var($wpdb->prepare('SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = %s', $table)))) { return false; }
            // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
        }
        foreach (array(self::table() => array('PRIMARY' => array('id'), 'attachment_id' => array('attachment_id')), self::table(true) => array('PRIMARY' => array('file_id','variant','part'))) as $table => $required) {
            $indexes = array();
            // phpcs:disable PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- REL007-SQL-0003: ready(): table(bool) selects only file/chunk tables; lead/attachment/variant limits are bound; live ownership/reference and engine checks govern confidential file reads.
            foreach ((array) $wpdb->get_results("SHOW INDEX FROM $table") as $index) {
            // phpcs:enable PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                if (!(int) $index->Non_unique) { $indexes[$index->Key_name][(int) $index->Seq_in_index] = $index->Column_name; }
            }
            foreach ($required as $name => $columns) {
                if (!isset($indexes[$name])) { return false; }
                ksort($indexes[$name]); if (array_values($indexes[$name]) !== $columns) { return false; }
            }
        }
        return true;
        } finally { $wpdb->suppress_errors($suppressed); }
    }

    public static function init() {
        add_action('admin_post_idlm_download_lead_file', array(__CLASS__, 'download'));
        add_action('admin_post_nopriv_idlm_download_lead_file', array(__CLASS__, 'download'));
        add_action('idlm_private_file_maintenance', array(__CLASS__, 'maintenance'));
        add_filter('wp_get_attachment_url', array(__CLASS__, 'attachment_url'), 20, 2);
        if (!wp_next_scheduled('idlm_private_file_maintenance')) { wp_schedule_event(time() + 300, 'hourly', 'idlm_private_file_maintenance'); }
    }

    public static function url($attachment_id) {
        return admin_url('admin-post.php?action=idlm_download_lead_file&attachment_id=' . absint($attachment_id));
    }

    public static function attachment_url($url, $attachment_id) {
        // Metadata avoids querying a missing table during an incomplete upgrade.
        if (get_post_meta($attachment_id, '_idlm_private_file_id', true)) { return self::url($attachment_id); }
        return $url;
    }

    public static function attachment_id($url) {
        $parts = wp_parse_url((string) $url); $base = wp_parse_url(admin_url('admin-post.php'));
        if (!$parts || !$base || isset($parts['user']) || isset($parts['pass']) || ($parts['scheme'] ?? '') !== ($base['scheme'] ?? '') || ($parts['host'] ?? '') !== ($base['host'] ?? '') || ($parts['port'] ?? null) !== ($base['port'] ?? null) || ($parts['path'] ?? '') !== $base['path']) { return 0; }
        parse_str($parts['query'] ?? '', $query);
        return ($query['action'] ?? '') === 'idlm_download_lead_file' && isset($query['attachment_id']) && is_string($query['attachment_id']) && ctype_digit($query['attachment_id']) ? absint($query['attachment_id']) : 0;
    }

    /** Read-all never grants upload authority; lock callers own a transaction. */
    public static function allowed($lead_id, $write = false, $lock = false) {
        global $wpdb;
        if (!IDLM_Lead_Access_Policy::has_rep_access() || !absint($lead_id)) { return false; }
        if (!$write) { return IDLM_Lead_Access_Policy::can_access($lead_id, true); }
        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared -- REL007-SQL-0004: allowed(): table(bool) selects only file/chunk tables; lead/attachment/variant limits are bound; live ownership/reference and engine checks govern confidential file reads.
        $lead = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}idlm_leads WHERE id = %d" . ($lock ? ' FOR UPDATE' : ''), absint($lead_id)));
        // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared
        if ($lock) {
            $user = get_current_user_id(); clean_user_cache($user);
            $roles = wp_roles(); wp_cache_delete($roles->role_key, 'options'); wp_cache_delete('alloptions', 'options'); $roles->for_site();
            wp_set_current_user(0); wp_set_current_user($user);
        }
        return $lead && IDLM_Lead_Access_Policy::has_rep_access() && (IDLM_Lead_Access_Policy::can_assign() || absint($lead->assigned_rep_id) === get_current_user_id());
    }

    private static function refresh_actor() {
        $user = get_current_user_id(); clean_user_cache($user);
        $roles = wp_roles(); wp_cache_delete($roles->role_key, 'options'); wp_cache_delete('alloptions', 'options'); $roles->for_site();
        wp_set_current_user(0); wp_set_current_user($user);
    }

    private static function referenced_or_temporary($file) {
        global $wpdb;
        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- REL007-SQL-0005: referenced_or_temporary(): table(bool) selects only file/chunk tables; lead/attachment/variant limits are bound; live ownership/reference and engine checks govern confidential file reads.
        $lead = $wpdb->get_row($wpdb->prepare("SELECT floor_plan_file,floor_plan_files,quote_file FROM {$wpdb->prefix}idlm_leads WHERE id = %d", $file->lead_id));
        // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
        if (!$lead) { return false; }
        $urls = 'quote' === $file->purpose ? array($lead->quote_file) : IDLM_Admin_Presenter::normalize_floor_plan_files($lead->floor_plan_files, $lead->floor_plan_file);
        foreach ($urls as $url) { if (self::attachment_id($url) === (int) $file->attachment_id) { return true; } }
        return in_array($file->state, array('uploaded', 'pending_upload'), true) && strtotime($file->created_at) >= strtotime(current_time('mysql')) - 7 * DAY_IN_SECONDS;
    }

    /** Pointer removal revokes reads immediately; journal retries handle physical erasure. */
    public static function reconcile_lead($lead_id) {
        global $wpdb;
        $table = self::table();
        // phpcs:disable PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- REL007-SQL-0006: reconcile_lead(): table(bool) selects only file/chunk tables; lead/attachment/variant limits are bound; live ownership/reference and engine checks govern confidential file reads.
        $rows = $wpdb->get_results($wpdb->prepare("SELECT * FROM $table WHERE lead_id = %d AND state IN ('uploaded','active') LIMIT 100", absint($lead_id)));
        // phpcs:enable PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        foreach ($rows as $row) {
            if (self::referenced_or_temporary($row)) {
                if ('uploaded' === $row->state) {
                    $old = clone $row; $old->state = 'active';
                    // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- REL007-SQL-0007: reconcile_lead(): table(bool) selects only file/chunk tables; lead/attachment/variant limits are bound; live ownership/reference and engine checks govern confidential file reads.
                    if (self::referenced_or_temporary($old)) { $wpdb->update($table, array('state' => 'active'), array('id' => $row->id)); }
                    // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
                }
            } else {
                // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- REL007-SQL-0008: reconcile_lead(): table(bool) selects only file/chunk tables; lead/attachment/variant limits are bound; live ownership/reference and engine checks govern confidential file reads.
                $wpdb->update($table, array('state' => 'erasing', 'filename' => '', 'mime' => '', 'byte_size' => 0, 'sha256' => ''), array('id' => $row->id));
                // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
            }
        }
    }

    private static function transactional() {
        global $wpdb;
        if (!self::ready()) { return false; }
        foreach (array($wpdb->posts, $wpdb->postmeta, $wpdb->prefix . 'idlm_leads') as $table) {
            // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- REL007-SQL-0009: transactional(): table(bool) selects only file/chunk tables; lead/attachment/variant limits are bound; live ownership/reference and engine checks govern confidential file reads.
            if ('INNODB' !== strtoupper((string) $wpdb->get_var($wpdb->prepare('SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = %s', $table)))) { return false; }
            // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
        }
        return true;
    }

    /** Validate with WordPress file APIs; never move raw upload bytes into uploads. */
    public static function upload($file, $lead_id, $purpose) {
        global $wpdb;
        if (!is_array($file) || !is_uploaded_file($file['tmp_name'] ?? '')) { return new WP_Error('idlm_file_upload', 'Invalid uploaded file.'); }
        $valid = IDLM_Production_Security::validate_upload($file, $purpose);
        if (is_wp_error($valid)) { return $valid; }
        $name = sanitize_file_name(wp_unslash($file['name']));
        $type = wp_check_filetype_and_ext($file['tmp_name'], $name);
        if (!$type['ext'] || !$type['type'] || filesize($file['tmp_name']) !== (int) $file['size']) { return new WP_Error('idlm_file_type', 'The file contents or size are invalid.'); }
        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery -- REL007-SQL-0010: upload(): table(bool) selects only file/chunk tables; lead/attachment/variant limits are bound; live ownership/reference and engine checks govern confidential file reads.
        if (!self::transactional() || false === $wpdb->query('START TRANSACTION')) { return new WP_Error('idlm_file_storage', 'Private file storage is unavailable.'); }
        // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery
        $attachment = 0; $suppressed = $wpdb->suppress_errors(true);
        try {
            if (!self::allowed($lead_id, true, true) || ('quote' === $purpose && IDLM_Lead_Access_Policy::is_pre_sales())) { throw new RuntimeException('authority'); }
            $attachment = wp_insert_attachment(array('post_title' => 'Protected lead file', 'post_status' => 'private', 'post_mime_type' => $type['type'], 'guid' => ''), '', 0, true, false);
            if (is_wp_error($attachment) || !$attachment) { $attachment = 0; throw new RuntimeException('attachment'); }
            $id = self::store($lead_id, $attachment, $purpose, $name, $type['type'], array(array('source' => $file['tmp_name'], 'path' => '')), 'uploaded');
            if (!$id) { throw new RuntimeException('storage'); }
            foreach (array('_idlm_private_file_id' => $id, '_idlm_attachment_lead_id' => absint($lead_id), '_idlm_attachment_purpose' => $purpose, '_idlm_attachment_owner_id' => get_current_user_id(), '_idlm_attachment_created_at' => current_time('mysql')) as $key => $value) {
                if (!update_post_meta($attachment, $key, $value)) { throw new RuntimeException('metadata'); }
            }
            // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery -- REL007-SQL-0011: upload(): table(bool) selects only file/chunk tables; lead/attachment/variant limits are bound; live ownership/reference and engine checks govern confidential file reads.
            if (is_wp_error(wp_update_post(array('ID' => $attachment, 'guid' => self::url($attachment)), true, false)) || false === $wpdb->query('COMMIT')) { throw new RuntimeException('commit'); }
            // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery
            clean_post_cache($attachment);
            return array('url' => self::url($attachment), 'attachment_id' => (int) $attachment);
        } catch (Throwable $error) {
            // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery -- REL007-SQL-0012: upload(): table(bool) selects only file/chunk tables; lead/attachment/variant limits are bound; live ownership/reference and engine checks govern confidential file reads.
            $wpdb->query('ROLLBACK'); if ($attachment) { clean_post_cache($attachment); }
            // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery
            return new WP_Error('authority' === $error->getMessage() ? 'idlm_file_denied' : 'idlm_file_failed', 'Unable to save this private file. No file was published.');
        } finally { $wpdb->suppress_errors($suppressed); }
    }

    /** Caller owns the transaction. Store verified base64 chunks below packet budgets. */
    private static function store($lead, $attachment, $purpose, $name, $mime, $paths, $state) {
        global $wpdb;
        $archive = array(); $total = 0;
        foreach ($paths as $variant => $path) {
            $size = filesize($path['source']); $total += $size;
            if (!$size || ($variant === 0 && $size > self::MAX_BYTES) || $total > self::MAX_ARCHIVE_BYTES) { return 0; }
            $archive[] = array('variant' => $variant, 'path' => $path['path'], 'size' => $size, 'sha256' => hash_file('sha256', $path['source']));
        }
        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery -- REL007-SQL-0013: store(): table(bool) selects only file/chunk tables; lead/attachment/variant limits are bound; live ownership/reference and engine checks govern confidential file reads.
        if (false === $wpdb->insert(self::table(), array('lead_id' => absint($lead), 'attachment_id' => absint($attachment), 'purpose' => $purpose, 'filename' => $name, 'mime' => $mime, 'byte_size' => $archive[0]['size'], 'sha256' => $archive[0]['sha256'], 'state' => $state, 'archive' => wp_json_encode($archive), 'created_at' => current_time('mysql')))) { return 0; }
        // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery
        $id = (int) $wpdb->insert_id;
        foreach ($paths as $variant => $path) {
            // phpcs:disable WordPress.WP.AlternativeFunctions.file_system_operations_fopen -- REL007-BOUNDARY-0001: store(): download validates decimal attachment IDs before SQL and rechecks live cookie/capability/lead/reference access; store streams only validated uploaded/verified local archive paths in bounded chunks, preserving binary bytes.
            $stream = fopen($path['source'], 'rb'); if (!$stream) { return 0; } $part = 0;
            // phpcs:enable WordPress.WP.AlternativeFunctions.file_system_operations_fopen
            try {
                while (!feof($stream)) {
                    // phpcs:disable WordPress.WP.AlternativeFunctions.file_system_operations_fread -- REL007-BOUNDARY-0002: store(): download validates decimal attachment IDs before SQL and rechecks live cookie/capability/lead/reference access; store streams only validated uploaded/verified local archive paths in bounded chunks, preserving binary bytes.
                    $data = fread($stream, self::CHUNK_BYTES); if ($data === false) { return 0; } if ($data === '') { break; }
                    // phpcs:enable WordPress.WP.AlternativeFunctions.file_system_operations_fread
                    // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery -- REL007-SQL-0014: store(): table(bool) selects only file/chunk tables; lead/attachment/variant limits are bound; live ownership/reference and engine checks govern confidential file reads.
                    if (++$part > ceil(self::MAX_ARCHIVE_BYTES / self::CHUNK_BYTES) || false === $wpdb->insert(self::table(true), array('file_id' => $id, 'variant' => $variant, 'part' => $part, 'data' => base64_encode($data)))) { return 0; }
                    // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery
                }
            // phpcs:disable WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- REL007-BOUNDARY-0003: store(): download validates decimal attachment IDs before SQL and rechecks live cookie/capability/lead/reference access; store streams only validated uploaded/verified local archive paths in bounded chunks, preserving binary bytes.
            } finally { fclose($stream); }
            // phpcs:enable WordPress.WP.AlternativeFunctions.file_system_operations_fclose
            if (false === self::bytes($id, $archive[$variant])) { return 0; }
        }
        return $id;
    }

    private static function bytes($id, $descriptor) {
        global $wpdb;
        $size = (int) ($descriptor['size'] ?? 0); $variant = (int) ($descriptor['variant'] ?? 0);
        if ($size < 1 || $size > self::MAX_ARCHIVE_BYTES || $variant < 0 || $variant > 100) { return false; }
        $limit = (int) ceil($size / self::CHUNK_BYTES); $table = self::table(true);
        // phpcs:disable PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- REL007-SQL-0015: bytes(): table(bool) selects only file/chunk tables; lead/attachment/variant limits are bound; live ownership/reference and engine checks govern confidential file reads.
        $rows = (array) $wpdb->get_results($wpdb->prepare("SELECT part,data FROM $table WHERE file_id = %d AND variant = %d ORDER BY part LIMIT %d", $id, $variant, $limit + 1));
        // phpcs:enable PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        if (count($rows) !== $limit || $wpdb->last_error) { return false; }
        $data = ''; foreach ($rows as $i => $row) {
            $chunk = base64_decode($row->data, true);
            if ((int) $row->part !== $i + 1 || false === $chunk || strlen($chunk) > self::CHUNK_BYTES) { return false; }
            $data .= $chunk;
        }
        return strlen($data) === $size && hash_equals((string) $descriptor['sha256'], hash('sha256', $data)) ? $data : false;
    }

    /** No nonce on this read-only URL: live cookie/capability/lead access is authority. */
    public static function download() {
        // phpcs:disable WordPress.Security.NonceVerification.Recommended,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- REL007-BOUNDARY-0004: download(): download validates decimal attachment IDs before SQL and rechecks live cookie/capability/lead/reference access; store streams only validated uploaded/verified local archive paths in bounded chunks, preserving binary bytes.
        $raw = wp_unslash($_GET['attachment_id'] ?? '');
        // phpcs:enable WordPress.Security.NonceVerification.Recommended,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
        if (!is_user_logged_in() || !IDLM_Lead_Access_Policy::has_rep_access()) { self::deny(403); }
        if (!is_scalar($raw) || !ctype_digit((string) $raw) || !absint($raw) || !self::ready()) { self::deny(404); }
        global $wpdb; $table = self::table();
        // phpcs:disable PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- REL007-SQL-0016: download(): table(bool) selects only file/chunk tables; lead/attachment/variant limits are bound; live ownership/reference and engine checks govern confidential file reads.
        $file = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE attachment_id = %d AND state IN ('uploaded','active','pending','pending_upload')", absint($raw)));
        // phpcs:enable PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        if (!$file || !self::allowed($file->lead_id) || !self::referenced_or_temporary($file) || (int) $file->byte_size > self::MAX_BYTES) { self::deny(404); }
        $data = self::bytes($file->id, array('variant' => 0, 'size' => $file->byte_size, 'sha256' => $file->sha256));
        self::refresh_actor();
        if (false === $data || !self::allowed($file->lead_id) || !self::referenced_or_temporary($file)) { self::deny(404); }
        while (ob_get_level()) { ob_end_clean(); }
        nocache_headers(); header('Cache-Control: private, no-store, max-age=0'); header('X-Content-Type-Options: nosniff'); header('X-Frame-Options: SAMEORIGIN'); header('Referrer-Policy: no-referrer'); header("Content-Security-Policy: sandbox; default-src 'none'");
        header('Content-Type: ' . sanitize_mime_type($file->mime)); header('Content-Length: ' . strlen($data));
        $disposition = in_array($file->mime, array('application/pdf','image/jpeg','image/png','image/webp'), true) ? 'inline' : 'attachment';
        header('Content-Disposition: ' . $disposition . '; filename="' . str_replace(array('"',"\r","\n"), '', sanitize_file_name($file->filename)) . '"');
        echo $data; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Verified binary download.
        exit;
    }

    private static function deny($status) {
        nocache_headers(); header('Cache-Control: private, no-store, max-age=0'); wp_die('File unavailable.', '', array('response' => (int) $status));
    }

    /** Validate submitted URLs against canonical file lead/purpose, not just Media URLs. */
    public static function validate_urls($lead, $urls, $purpose) {
        global $wpdb;
        foreach ((array) $urls as $url) {
            $attachment = self::attachment_id($url);
            if (!$attachment) {
                // Existing legacy references can be preserved until maintenance
                // migrates them; no new legacy reference is accepted here.
                // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- REL007-SQL-0017: validate_urls(): table(bool) selects only file/chunk tables; lead/attachment/variant limits are bound; live ownership/reference and engine checks govern confidential file reads.
                $old = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}idlm_leads WHERE id = %d", absint($lead)));
                // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
                $existing = $old ? ('quote' === $purpose ? array($old->quote_file) : IDLM_Admin_Presenter::normalize_floor_plan_files($old->floor_plan_files, $old->floor_plan_file)) : array();
                $legacy = attachment_url_to_postid($url); $owner = $legacy ? get_post_meta($legacy, '_idlm_attachment_lead_id', true) : 0;
                if (in_array($url, $existing, true) && (!$owner || (int) $owner === (int) $lead)) { continue; }
                return new WP_Error('idlm_file_binding', 'Upload files through this lead before saving them.');
            }
            $table = self::table();
            // phpcs:disable PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- REL007-SQL-0018: validate_urls(): table(bool) selects only file/chunk tables; lead/attachment/variant limits are bound; live ownership/reference and engine checks govern confidential file reads.
            if (!$wpdb->get_var($wpdb->prepare("SELECT id FROM $table WHERE attachment_id = %d AND lead_id = %d AND purpose = %s AND state IN ('uploaded','active','pending','pending_upload')", $attachment, absint($lead), $purpose))) { return new WP_Error('idlm_file_binding', 'This file does not belong to the selected lead and field.'); }
            // phpcs:enable PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        }
        return true;
    }

    /** Guard private references on every canonical update/import path. */
    public static function validate_fields($lead, $data) {
        foreach (array('floor_plan_file' => 'floor_plan', 'floor_plan_files' => 'floor_plan', 'quote_file' => 'quote') as $field => $purpose) {
            if (!isset($data[$field]) || strpos((string) $data[$field], 'idlm_download_lead_file') === false) { continue; }
            $urls = 'floor_plan_files' === $field ? IDLM_Admin_Presenter::normalize_floor_plan_files($data[$field]) : array($data[$field]);
            $valid = self::validate_urls($lead, $urls, $purpose); if (is_wp_error($valid)) { return $valid; }
        }
        return true;
    }

    /** Resolve only local, non-symlink paths beneath the WordPress uploads directory. */
    private static function local_path($relative) {
        $uploads = wp_upload_dir(); $base = realpath($uploads['basedir']);
        if (!$base || !is_string($relative) || !$relative || strpos($relative, '..') !== false || strpos($relative, "\0") !== false || preg_match('~^[\\\\/]~', $relative)) { return false; }
        $candidate = $base . '/' . $relative; $parent = realpath(dirname($candidate));
        if (!$parent || strpos($parent . '/', $base . '/') !== 0 || is_link($candidate)) { return false; }
        if (file_exists($candidate)) { $real = realpath($candidate); return $real && strpos($real, $base . '/') === 0 && is_file($real) ? $real : false; }
        return $candidate;
    }

    private static function legacy_paths($attachment) {
        $uploads = wp_upload_dir(); $base = realpath($uploads['basedir']); $source = get_attached_file($attachment, true);
        $main = $source && !is_link($source) ? realpath($source) : false;
        if (!$base || !$main || strpos($main, $base . '/') !== 0) { return false; }
        $relative = substr($main, strlen($base) + 1); $names = array($relative); $meta = wp_get_attachment_metadata($attachment);
        foreach ((array) ($meta['sizes'] ?? array()) as $size) { if (!empty($size['file'])) { $names[] = dirname($relative) . '/' . $size['file']; } }
        if (!empty($meta['original_image'])) { $names[] = dirname($relative) . '/' . $meta['original_image']; }
        foreach ((array) get_post_meta($attachment, '_wp_attachment_backup_sizes', true) as $size) { if (!empty($size['file'])) { $names[] = dirname($relative) . '/' . $size['file']; } }
        $paths = array(); foreach (array_unique($names) as $name) {
            $path = self::local_path($name); if (!$path || !is_file($path)) { return false; }
            $paths[] = array('source' => $path, 'path' => $name);
        }
        return count($paths) <= 101 ? $paths : false;
    }

    /** Refuse shared/misbound media rather than erasing another lead's file. */
    private static function unique_reference($url, $lead) {
        global $wpdb;
        $like = '%' . $wpdb->esc_like($url) . '%'; $escaped = '%' . $wpdb->esc_like(str_replace('/', '\\/', $url)) . '%';
        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- REL007-SQL-0019: unique_reference(): table(bool) selects only file/chunk tables; lead/attachment/variant limits are bound; live ownership/reference and engine checks govern confidential file reads.
        $rows = $wpdb->get_results($wpdb->prepare("SELECT id,floor_plan_file,floor_plan_files,quote_file FROM {$wpdb->prefix}idlm_leads WHERE floor_plan_file = %s OR quote_file = %s OR floor_plan_files LIKE %s OR floor_plan_files LIKE %s LIMIT 101", $url, $url, $like, $escaped));
        // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
        if (count($rows) > 100 || $wpdb->last_error) { return false; }
        foreach ($rows as $row) {
            $urls = IDLM_Admin_Presenter::normalize_floor_plan_files($row->floor_plan_files, $row->floor_plan_file); $urls[] = $row->quote_file;
            if (in_array($url, $urls, true) && (int) $row->id !== (int) $lead) { return false; }
        }
        return true;
    }

    private static function tagged_attachments($lead) {
        global $wpdb;
        $files = self::table();
        // phpcs:disable PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- REL007-SQL-0020: tagged_attachments(): table(bool) selects only file/chunk tables; lead/attachment/variant limits are bound; live ownership/reference and engine checks govern confidential file reads.
        return $wpdb->get_col($wpdb->prepare("SELECT DISTINCT p.ID FROM {$wpdb->posts} p JOIN {$wpdb->postmeta} m ON m.post_id = p.ID LEFT JOIN $files f ON f.attachment_id = p.ID WHERE f.id IS NULL AND p.post_type = 'attachment' AND m.meta_key = '_idlm_attachment_lead_id' AND m.meta_value = %s ORDER BY p.ID LIMIT 101", (string) absint($lead)));
        // phpcs:enable PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
    }

    private static function copy_legacy($lead, $attachment, $purpose, $state) {
        $paths = self::legacy_paths($attachment); if (!$paths) { return false; }
        $type = wp_check_filetype_and_ext($paths[0]['source'], basename($paths[0]['source']));
        if (!$type['type'] || is_wp_error(IDLM_Production_Security::validate_upload(array('name' => basename($paths[0]['source']), 'tmp_name' => $paths[0]['source'], 'type' => $type['type'], 'size' => filesize($paths[0]['source']), 'error' => 0), $purpose))) { return false; }
        $id = self::store($lead, $attachment, $purpose, basename($paths[0]['source']), $type['type'], $paths, $state);
        return $id && update_post_meta($attachment, '_idlm_private_file_id', $id);
    }

    /** Migrate one lead transactionally; the public-file cleanup journal survives crashes. */
    public static function migrate_lead($lead_id) {
        global $wpdb;
        if (!self::transactional()) { return false; }
        $touched = array();
        $result = IDLM_Lead_Transaction::run(static function() use ($lead_id, &$touched) {
            global $wpdb;
            // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- REL007-SQL-0021: migrate_lead(): table(bool) selects only file/chunk tables; lead/attachment/variant limits are bound; live ownership/reference and engine checks govern confidential file reads.
            $lead = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}idlm_leads WHERE id = %d FOR UPDATE", absint($lead_id)));
            // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
            if (!$lead) { throw new RuntimeException('missing'); }
            $floor = IDLM_Admin_Presenter::normalize_floor_plan_files($lead->floor_plan_files ?? '', $lead->floor_plan_file ?? ''); $quote = $lead->quote_file ? array($lead->quote_file) : array(); $changed = false;
            foreach (array('floor_plan' => &$floor, 'quote' => &$quote) as $purpose => &$urls) {
                foreach ($urls as &$url) {
                    if (self::attachment_id($url)) { continue; }
                    $attachment = attachment_url_to_postid($url); $owner = $attachment ? get_post_meta($attachment, '_idlm_attachment_lead_id', true) : 0;
                    if (!$attachment || ($owner && (int) $owner !== (int) $lead_id) || !self::unique_reference($url, $lead_id)) { throw new RuntimeException('binding'); }
                    $touched[] = $attachment;
                    if (!self::copy_legacy($lead_id, $attachment, $purpose, 'pending')) { throw new RuntimeException('copy'); }
                    $url = self::url($attachment); $changed = true;
                }
                unset($url);
            }
            unset($urls);
            if ($changed && false === IDLM_DB::update_lead($lead_id, array('floor_plan_file' => $floor[0] ?? '', 'floor_plan_files' => $floor ? wp_json_encode($floor) : null, 'quote_file' => $quote[0] ?? ''))) { throw new RuntimeException('reference'); }
            $tagged = self::tagged_attachments($lead_id);
            if (count($tagged) > 100 || $wpdb->last_error) { throw new RuntimeException('tagged'); }
            foreach ($tagged as $attachment) {
                // phpcs:disable PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared -- REL007-SQL-0022: migrate_lead(): table(bool) selects only file/chunk tables; lead/attachment/variant limits are bound; live ownership/reference and engine checks govern confidential file reads.
                if ($wpdb->get_var($wpdb->prepare('SELECT id FROM ' . self::table() . ' WHERE attachment_id = %d', $attachment))) { continue; }
                // phpcs:enable PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared
                $url = wp_get_attachment_url($attachment);
                if (!$url || !self::unique_reference($url, $lead_id)) { throw new RuntimeException('binding'); }
                $purpose = get_post_meta($attachment, '_idlm_attachment_purpose', true);
                if (!in_array($purpose, array('floor_plan','quote'), true)) { $purpose = 'floor_plan'; }
                $touched[] = $attachment;
                if (!self::copy_legacy($lead_id, $attachment, $purpose, 'pending_upload')) { throw new RuntimeException('copy'); }
            }
            IDLM_Lead_Transaction::invalidate('private_file_migration', $lead_id); return true;
        });
        if (is_wp_error($result)) { foreach ($touched as $attachment) { clean_post_cache($attachment); } return false; }
        return $result;
    }

    /** Bounded/resumable scans; unresolved external/missing/shared files are retained. */
    public static function maintenance() {
        return IDLM_Recovery_Guard::run(static function() { return self::maintenance_work(); }, null, false, '', array());
    }

    private static function maintenance_work() {
        global $wpdb;
        if (!self::ready()) { return array('scanned' => 0, 'failed' => 1, 'pending' => 0); }
        $cursor = absint(get_option('idlm_private_file_migration_cursor', 0));
        $files = self::table();
        // phpcs:disable PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- REL007-SQL-0023: maintenance_work(): table(bool) selects only file/chunk tables; lead/attachment/variant limits are bound; live ownership/reference and engine checks govern confidential file reads.
        $ids = $wpdb->get_col($wpdb->prepare("SELECT id FROM {$wpdb->prefix}idlm_leads WHERE id > %d AND (floor_plan_file <> '' OR floor_plan_files IS NOT NULL OR quote_file <> '' OR EXISTS (SELECT 1 FROM $files f WHERE f.lead_id = {$wpdb->prefix}idlm_leads.id) OR EXISTS (SELECT 1 FROM {$wpdb->postmeta} m WHERE m.meta_key = '_idlm_attachment_lead_id' AND m.meta_value = CAST({$wpdb->prefix}idlm_leads.id AS CHAR))) ORDER BY id LIMIT 10", $cursor));
        // phpcs:enable PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $failed = 0; foreach ($ids as $id) { if (!self::migrate_lead($id)) { $failed++; } self::reconcile_lead($id); $cursor = (int) $id; }
        update_option('idlm_private_file_migration_cursor', count($ids) === 10 ? $cursor : 0, false);
        $pending = self::cleanup(10); $result = array('scanned' => count($ids), 'failed' => $failed, 'pending' => $pending);
        update_option('idlm_private_file_migration_status', $result, false); return $result;
    }

    /** Caller owns lead deletion/reset transaction. Revoke bytes before filesystem work. */
    public static function erase_leads($ids) {
        global $wpdb;
        if (!self::ready()) { return false; }
        $files = self::table(); $chunks = self::table(true);
        foreach ((array) $ids as $lead_id) {
            // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- REL007-SQL-0024: erase_leads(): table(bool) selects only file/chunk tables; lead/attachment/variant limits are bound; live ownership/reference and engine checks govern confidential file reads.
            $lead = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}idlm_leads WHERE id = %d FOR UPDATE", absint($lead_id)));
            // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
            if ($lead) {
                $urls = IDLM_Admin_Presenter::normalize_floor_plan_files($lead->floor_plan_files ?? '', $lead->floor_plan_file ?? ''); if (!empty($lead->quote_file)) { $urls[] = $lead->quote_file; }
                $tagged = self::tagged_attachments($lead_id);
                if (count($tagged) > 100 || $wpdb->last_error) { return false; }
                foreach ($tagged as $attachment) { $url = wp_get_attachment_url($attachment); if ($url) { $urls[] = $url; } }
                foreach (array_unique($urls) as $url) {
                    if (self::attachment_id($url)) { continue; }
                    $attachment = attachment_url_to_postid($url); if (!$attachment) { continue; } // Unmanaged remote files are not ours to erase.
                    // phpcs:disable PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- REL007-SQL-0025: erase_leads(): table(bool) selects only file/chunk tables; lead/attachment/variant limits are bound; live ownership/reference and engine checks govern confidential file reads.
                    if ($wpdb->get_var($wpdb->prepare("SELECT id FROM $files WHERE attachment_id = %d", $attachment))) { continue; }
                    // phpcs:enable PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                    $owner = get_post_meta($attachment, '_idlm_attachment_lead_id', true);
                    if (($owner && (int) $owner !== (int) $lead_id) || !self::unique_reference($url, $lead_id)) { return false; }
                    $paths = self::legacy_paths($attachment); if (!$paths) { return false; }
                    $archive = array(); foreach ($paths as $variant => $path) { $archive[] = array('variant' => $variant, 'path' => $path['path'], 'size' => filesize($path['source']), 'sha256' => hash_file('sha256', $path['source'])); }
                    // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery -- REL007-SQL-0026: erase_leads(): table(bool) selects only file/chunk tables; lead/attachment/variant limits are bound; live ownership/reference and engine checks govern confidential file reads.
                    if (false === $wpdb->insert($files, array('lead_id' => absint($lead_id), 'attachment_id' => $attachment, 'purpose' => 'legacy', 'filename' => '', 'mime' => '', 'byte_size' => 0, 'sha256' => '', 'state' => 'erasing', 'archive' => wp_json_encode($archive), 'created_at' => current_time('mysql')))) { return false; }
                    // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery
                }
            }
            // phpcs:disable PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- REL007-SQL-0027: erase_leads(): table(bool) selects only file/chunk tables; lead/attachment/variant limits are bound; live ownership/reference and engine checks govern confidential file reads.
            $rows = $wpdb->get_results($wpdb->prepare("SELECT id FROM $files WHERE lead_id = %d FOR UPDATE", absint($lead_id)));
            // phpcs:enable PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            if ($wpdb->last_error) { return false; }
            foreach ($rows as $row) {
                // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- REL007-SQL-0028: erase_leads(): table(bool) selects only file/chunk tables; lead/attachment/variant limits are bound; live ownership/reference and engine checks govern confidential file reads.
                if (false === $wpdb->delete($chunks, array('file_id' => $row->id)) || false === $wpdb->update($files, array('state' => 'erasing', 'filename' => '', 'mime' => '', 'byte_size' => 0, 'sha256' => ''), array('id' => $row->id))) { return false; }
                // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
            }
        }
        return true;
    }

    public static function erase_all() {
        global $wpdb;
        // phpcs:disable PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared -- REL007-SQL-0029: erase_all(): table(bool) selects only file/chunk tables; lead/attachment/variant limits are bound; live ownership/reference and engine checks govern confidential file reads.
        if (!self::erase_leads($wpdb->get_col("SELECT id FROM {$wpdb->prefix}idlm_leads ORDER BY id FOR UPDATE"))) { return false; }
        return false !== $wpdb->query('DELETE FROM ' . self::table(true)) && false !== $wpdb->query("UPDATE " . self::table() . " SET state = 'erasing', filename = '', mime = '', byte_size = 0, sha256 = ''");
        // phpcs:enable PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared
    }

    /** Cleanup only after commit; protected variants are verified before old paths vanish. */
    public static function cleanup($limit = 10) {
        global $wpdb; $table = self::table();
        // phpcs:disable PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- REL007-SQL-0030: cleanup(): table(bool) selects only file/chunk tables; lead/attachment/variant limits are bound; live ownership/reference and engine checks govern confidential file reads.
        $rows = $wpdb->get_results($wpdb->prepare("SELECT * FROM $table WHERE state IN ('pending','pending_upload','erasing') ORDER BY id LIMIT %d", max(1, min(50, (int) $limit))));
        // phpcs:enable PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        foreach ($rows as $row) {
            // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery -- REL007-SQL-0031: cleanup(): table(bool) selects only file/chunk tables; lead/attachment/variant limits are bound; live ownership/reference and engine checks govern confidential file reads.
            if (!self::transactional() || false === $wpdb->query('START TRANSACTION')) { break; }
            // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery
            $attachment = (int) $row->attachment_id; $committed = false;
            try {
            // phpcs:disable PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- REL007-SQL-0032: cleanup(): table(bool) selects only file/chunk tables; lead/attachment/variant limits are bound; live ownership/reference and engine checks govern confidential file reads.
            $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id = %d FOR UPDATE", $row->id));
            // phpcs:enable PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            if (!$row || !in_array($row->state, array('pending','pending_upload','erasing'), true)) { continue; }
            $archive = json_decode($row->archive, true); if (!is_array($archive)) { continue; } $safe = true;
            foreach ($archive as $descriptor) {
                if (in_array($row->state, array('pending','pending_upload'), true) && false === self::bytes($row->id, $descriptor)) { $safe = false; break; }
                if (!$descriptor['path']) { continue; } $path = self::local_path($descriptor['path']);
                if (!$path || (in_array($row->state, array('pending','pending_upload'), true) && is_file($path) && !hash_equals($descriptor['sha256'], hash_file('sha256', $path)))) { $safe = false; break; }
            }
            if (!$safe) { continue; }
            foreach ($archive as $descriptor) {
                if (!$descriptor['path']) { continue; } $path = self::local_path($descriptor['path']);
                if (is_file($path)) { wp_delete_file($path); } if (is_file($path)) { $safe = false; break; }
            }
            if (!$safe) { continue; }
            foreach (array('_wp_attached_file','_wp_attachment_metadata','_wp_attachment_backup_sizes') as $meta) { delete_post_meta($row->attachment_id, $meta); }
            if ('erasing' === $row->state) {
                // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery -- REL007-SQL-0033: cleanup(): table(bool) selects only file/chunk tables; lead/attachment/variant limits are bound; live ownership/reference and engine checks govern confidential file reads.
                if (false === $wpdb->delete(self::table(true), array('file_id' => $row->id))) { continue; }
                // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery
                if (get_post($row->attachment_id) && !wp_delete_attachment($row->attachment_id, true)) { continue; }
                // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery -- REL007-SQL-0034: cleanup(): table(bool) selects only file/chunk tables; lead/attachment/variant limits are bound; live ownership/reference and engine checks govern confidential file reads.
                if (false === $wpdb->delete($table, array('id' => $row->id))) { continue; }
                // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery
            } else {
                $updated = wp_update_post(array('ID' => $row->attachment_id, 'post_title' => 'Protected lead file', 'post_status' => 'private', 'guid' => self::url($row->attachment_id)), true, false);
                // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery -- REL007-SQL-0035: cleanup(): table(bool) selects only file/chunk tables; lead/attachment/variant limits are bound; live ownership/reference and engine checks govern confidential file reads.
                if (is_wp_error($updated) || !$updated || false === $wpdb->update($table, array('state' => 'pending_upload' === $row->state ? 'uploaded' : 'active'), array('id' => $row->id, 'state' => $row->state))) { continue; }
                // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery
            }
            // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery -- REL007-SQL-0036: cleanup(): table(bool) selects only file/chunk tables; lead/attachment/variant limits are bound; live ownership/reference and engine checks govern confidential file reads.
            $committed = false !== $wpdb->query('COMMIT');
            // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery
            } finally {
                // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery -- REL007-SQL-0037: cleanup(): table(bool) selects only file/chunk tables; lead/attachment/variant limits are bound; live ownership/reference and engine checks govern confidential file reads.
                if (!$committed) { $wpdb->query('ROLLBACK'); }
                // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery
                clean_post_cache($attachment);
            }
        }
        // phpcs:disable PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- REL007-SQL-0038: cleanup(): table(bool) selects only file/chunk tables; lead/attachment/variant limits are bound; live ownership/reference and engine checks govern confidential file reads.
        return (int) $wpdb->get_var("SELECT COUNT(*) FROM $table WHERE state IN ('pending','pending_upload','erasing')");
        // phpcs:enable PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
    }

    /** Partial plugin backups exclude WordPress file bytes/attachment identities. */
    public static function restore_safe($payload) {
        global $wpdb;
        if (get_option('idlm_private_file_schema_version') && !self::ready()) { return false; }
        // phpcs:disable PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared -- REL007-SQL-0039: restore_safe(): table(bool) selects only file/chunk tables; lead/attachment/variant limits are bound; live ownership/reference and engine checks govern confidential file reads.
        if (self::ready() && (int) $wpdb->get_var('SELECT COUNT(*) FROM ' . self::table()) > 0) { return false; }
        // phpcs:enable PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared
        foreach ((array) ($payload['tables']['idlm_leads'] ?? array()) as $lead) {
            foreach (array('floor_plan_file','floor_plan_files','quote_file') as $field) {
                if (!empty($lead[$field]) && strpos((string) $lead[$field], 'idlm_download_lead_file') !== false) { return false; }
            }
        }
        return true;
    }
}
