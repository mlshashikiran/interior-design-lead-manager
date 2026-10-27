<?php
/** Checked, session-locked core schema migrations. Builder: Shashi. */
if (!defined('ABSPATH')) { exit; }

final class IDLM_Schema_Migration {
    private static $depth = 0;

    public static function lock_name() {
        global $wpdb;
        return 'idlm_schema_' . substr(hash('sha256', DB_NAME . '|' . $wpdb->prefix), 0, 40);
    }

    /** DDL commits independently. A disconnect releases this lock, never a marker. */
    public static function run($callback) {
        global $wpdb;
        $name = self::lock_name();
        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- REL007-SQL-0386: run(): installer declarations supply identifiers validated by the CREATE TABLE identifier regexp; metadata option values bound; physical schema/version checks must be live.
        if (!self::$depth && 1 !== (int) $wpdb->get_var($wpdb->prepare('SELECT GET_LOCK(%s, 0)', $name))) {
        // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
            return self::error('busy', 'Another CRM schema migration is running. Retry shortly.');
        }
        self::$depth++;
        $suppressed = $wpdb->suppress_errors(true);
        try {
            return call_user_func($callback);
        } finally {
            $wpdb->suppress_errors($suppressed);
            // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- REL007-SQL-0387: run(): installer declarations supply identifiers validated by the CREATE TABLE identifier regexp; metadata option values bound; physical schema/version checks must be live.
            if (!--self::$depth) { $wpdb->get_var($wpdb->prepare('SELECT RELEASE_LOCK(%s)', $name)); }
            // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
        }
    }

    public static function error($step, $message = '') {
        return new WP_Error('idlm_schema_' . sanitize_key($step), $message ?: 'CRM migration failed at ' . $step . '. Check database permissions and schema; retry after repair.');
    }

    /** Refresh stale cross-request caches after acquiring the migration lock. */
    public static function version($option) {
        global $wpdb;
        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- REL007-SQL-0388: version(): installer declarations supply identifiers validated by the CREATE TABLE identifier regexp; metadata option values bound; physical schema/version checks must be live.
        $value = $wpdb->get_var($wpdb->prepare("SELECT option_value FROM {$wpdb->options} WHERE option_name = %s", $option));
        // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
        return $wpdb->last_error ? null : (string) $value;
    }

    public static function publish($option, $version) {
        global $wpdb;
        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- REL007-SQL-0389: publish(): installer declarations supply identifiers validated by the CREATE TABLE identifier regexp; metadata option values bound; physical schema/version checks must be live.
        $owner = $wpdb->get_var($wpdb->prepare('SELECT IS_USED_LOCK(%s)', self::lock_name()));
        // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
        if ($wpdb->last_error || !$owner) { return self::error('lock_lost'); }
        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- REL007-SQL-0390: publish(): installer declarations supply identifiers validated by the CREATE TABLE identifier regexp; metadata option values bound; physical schema/version checks must be live.
        $connection = $wpdb->get_var('SELECT CONNECTION_ID()');
        // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
        if ($wpdb->last_error || !$connection || (int) $owner !== (int) $connection) {
            return self::error('lock_lost');
        }
        wp_cache_delete($option, 'options');
        wp_cache_delete('alloptions', 'options');
        update_option($option, $version, false);
        if (self::version($option) !== (string) $version) {
            wp_cache_delete($option, 'options');
            wp_cache_delete('alloptions', 'options');
            return self::error('marker_' . $option);
        }
        return true;
    }

    private static function type($value) {
        // MySQL 8 omits integer display widths; MariaDB/older MySQL retain them.
        return preg_replace('/\b(tinyint|smallint|mediumint|int|bigint)\(\d+\)/', '$1', strtolower(trim($value)));
    }

    /** Verify canonical declarations against physical metadata, not dbDelta notices. */
    public static function verify($statements) {
        global $wpdb;
        foreach ($statements as $sql) {
            if (!preg_match('/^CREATE TABLE ([a-zA-Z0-9_]+) \((.*)\)\s*(?:ENGINE=InnoDB )?/s', $sql, $table_match)) { return self::error('declaration'); }
            $table = $table_match[1];
            // phpcs:disable PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- REL007-SQL-0391: verify(): installer declarations supply identifiers validated by the CREATE TABLE identifier regexp; metadata option values bound; physical schema/version checks must be live.
            $columns = $wpdb->get_results("SHOW COLUMNS FROM `{$table}`", ARRAY_A);
            // phpcs:enable PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            if ($wpdb->last_error || !$columns) { return self::error($table . '_columns'); }
            $columns = array_column($columns, null, 'Field');
            // phpcs:disable PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- REL007-SQL-0392: verify(): installer declarations supply identifiers validated by the CREATE TABLE identifier regexp; metadata option values bound; physical schema/version checks must be live.
            $indexes = $wpdb->get_results("SHOW INDEX FROM `{$table}`", ARRAY_A);
            // phpcs:enable PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            if ($wpdb->last_error || !$indexes) { return self::error($table . '_indexes'); }
            $physical_indexes = array();
            foreach ($indexes as $index) {
                $physical_indexes[$index['Key_name']][(int) $index['Seq_in_index']] = array($index['Column_name'], (int) $index['Non_unique'], $index['Sub_part']);
            }
            // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- REL007-SQL-0393: verify(): installer declarations supply identifiers validated by the CREATE TABLE identifier regexp; metadata option values bound; physical schema/version checks must be live.
            $engine = $wpdb->get_var($wpdb->prepare('SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = %s', $table));
            // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
            if ($wpdb->last_error || 'INNODB' !== strtoupper((string) $engine)) {
                return self::error($table . '_engine', 'CRM requires InnoDB for ' . $table . '. Back up the database and have an operator migrate the table engine, then retry.');
            }
            foreach (explode("\n", trim($table_match[2])) as $line) {
                $line = rtrim(trim($line), ',');
                if (preg_match('/^(PRIMARY KEY|UNIQUE KEY ([a-z_]+)|KEY ([a-z_]+))\s*\(([^)]+)\)$/i', $line, $key)) {
                    $name = 'PRIMARY KEY' === $key[1] ? 'PRIMARY' : ($key[2] ?: $key[3]);
                    $expected = array_map('trim', explode(',', $key[4]));
                    $actual = $physical_indexes[$name] ?? array(); ksort($actual);
                    if (count($actual) !== count($expected)) { return self::error($table . '_' . $name); }
                    foreach (array_values($actual) as $i => $part) {
                        if ($part[0] !== $expected[$i] || $part[1] !== (strpos($key[1], 'KEY ') === 0 ? 1 : 0) || null !== $part[2]) { return self::error($table . '_' . $name); }
                    }
                } elseif (preg_match('/^([a-z_]+) ([a-z]+(?:\([0-9,]+\))?(?: unsigned)?)(.*)$/i', $line, $column)) {
                    $actual = $columns[$column[1]] ?? null;
                    $nullable = strpos($column[3], 'NOT NULL') === false ? 'YES' : 'NO';
                    $auto = strpos($column[3], 'AUTO_INCREMENT') !== false;
                    if (!$actual || self::type($actual['Type']) !== self::type($column[2]) || $actual['Null'] !== $nullable || $auto !== (strpos($actual['Extra'], 'auto_increment') !== false)) { return self::error($table . '_' . $column[1]); }
                    if (preg_match("/DEFAULT (NULL|'[^']*'|[0-9.]+)/", $column[3], $default)) {
                        $value = 'NULL' === $default[1] ? null : trim($default[1], "'");
                        if ((null === $value) !== (null === $actual['Default']) || (null !== $value && (string) $actual['Default'] !== $value && !(is_numeric($value) && is_numeric($actual['Default']) && (float) $value === (float) $actual['Default']))) { return self::error($table . '_' . $column[1] . '_default'); }
                    }
                } else { return self::error('declaration'); }
            }
        }
        return true;
    }
}
