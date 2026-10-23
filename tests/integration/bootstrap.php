<?php
/**
 * Disposable WordPress/MySQL integration-test bootstrap.
 *
 * Builder: Shashi
 */

declare(strict_types=1);

if ('cli' !== PHP_SAPI && !('cli-server' === PHP_SAPI && defined('IDLM_DISPOSABLE_HTTP_FIXTURE'))) {
    fwrite(STDERR, "Integration tests must run from the command line.\n");
    exit(1);
}

final class IDLM_Integration_Database {
    private mysqli $connection;
    private string $database_name;
    private bool $created = false;

    public function __construct(array $config) {
        $this->database_name = (string) $config['db_name'];
        if (!preg_match('/^idlm_test_[a-z0-9_]+$/', $this->database_name)) {
            throw new RuntimeException('Refusing unsafe integration database name. Use the idlm_test_* prefix.');
        }

        [$host, $port, $socket] = self::parse_host((string) $config['db_host']);
        mysqli_report(MYSQLI_REPORT_OFF);
        $this->connection = @new mysqli(
            $host,
            (string) $config['db_user'],
            (string) $config['db_password'],
            '',
            $port,
            $socket
        );
        if ($this->connection->connect_errno) {
            throw new RuntimeException('Unable to connect to the disposable MySQL test server: ' . $this->connection->connect_error);
        }

        $statement = $this->connection->prepare('SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = ?');
        $statement->bind_param('s', $this->database_name);
        $statement->execute();
        $statement->store_result();
        if ($statement->num_rows > 0) {
            $statement->close();
            throw new RuntimeException('Refusing to use or drop a pre-existing integration database: ' . $this->database_name);
        }
        $statement->close();

        if (!$this->connection->query('CREATE DATABASE `' . $this->database_name . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci')) {
            throw new RuntimeException('Unable to create the disposable integration database: ' . $this->connection->error);
        }
        $this->created = true;
    }

    public function drop(): void {
        if (!$this->created || !preg_match('/^idlm_test_[a-z0-9_]+$/', $this->database_name)) {
            return;
        }
        $this->connection->query('DROP DATABASE `' . $this->database_name . '`');
        $this->created = false;
    }

    private static function parse_host(string $db_host): array {
        $host = $db_host;
        $port = (int) ini_get('mysqli.default_port');
        $socket = null;

        if (preg_match('/^([^:]+):(\/.*)$/', $db_host, $matches)) {
            $host = $matches[1];
            $socket = $matches[2];
        } elseif (preg_match('/^([^:]+):(\d+)$/', $db_host, $matches)) {
            $host = $matches[1];
            $port = (int) $matches[2];
        }

        return [$host, $port, $socket];
    }
}

/** Open an independent session using the same socket/TCP endpoint as WordPress. */
function idlm_integration_other_connection(): mysqli {
    global $wpdb;
    list($host, $port, $socket) = $wpdb->parse_db_host(DB_HOST);
    $connection = new mysqli($host, DB_USER, DB_PASSWORD, DB_NAME, $port ?: 0, $socket ?: null);
    if ($connection->connect_errno) {
        throw new RuntimeException('Unable to connect an independent disposable database session: ' . $connection->connect_error);
    }
    return $connection;
}

function idlm_integration_config(bool $require_database_name = false): array {
    $wp_root = getenv('IDLM_TEST_WP_ROOT');
    if (false === $wp_root || '' === trim($wp_root)) {
        $wp_root = dirname(__DIR__, 5);
    }
    $wp_root = rtrim((string) $wp_root, '/\\') . DIRECTORY_SEPARATOR;
    if (!is_file($wp_root . 'wp-settings.php')) {
        throw new RuntimeException('IDLM_TEST_WP_ROOT must point to a WordPress installation containing wp-settings.php.');
    }

    $db_name = getenv('IDLM_TEST_DB_NAME');
    if ((false === $db_name || '' === trim($db_name)) && !$require_database_name) {
        $db_name = 'idlm_test_' . getmypid() . '_' . bin2hex(random_bytes(4));
    }
    if (false === $db_name || '' === trim((string) $db_name)) {
        throw new RuntimeException('IDLM_TEST_DB_NAME is required for integration workers.');
    }

    $db_user = getenv('IDLM_TEST_DB_USER');
    $db_password = getenv('IDLM_TEST_DB_PASSWORD');
    $db_host = getenv('IDLM_TEST_DB_HOST');
    if (false === $db_user || false === $db_password || false === $db_host || '' === trim($db_host)) {
        throw new RuntimeException('Set IDLM_TEST_DB_USER, IDLM_TEST_DB_PASSWORD, and IDLM_TEST_DB_HOST for a disposable MySQL server.');
    }

    return array(
        'wp_root' => $wp_root,
        'plugin_root' => dirname(__DIR__, 2) . DIRECTORY_SEPARATOR,
        'db_name' => (string) $db_name,
        'db_user' => (string) $db_user,
        'db_password' => (string) $db_password,
        'db_host' => (string) $db_host,
        'table_prefix' => 'wp_',
        'content_dir' => sys_get_temp_dir() . DIRECTORY_SEPARATOR . (string) $db_name . '-content',
    );
}

function idlm_integration_export_config(array $config): void {
    putenv('IDLM_TEST_WP_ROOT=' . $config['wp_root']);
    putenv('IDLM_PERF_WP_ROOT=' . $config['wp_root']);
    putenv('IDLM_TEST_DB_NAME=' . $config['db_name']);
    putenv('IDLM_TEST_DB_USER=' . $config['db_user']);
    putenv('IDLM_TEST_DB_PASSWORD=' . $config['db_password']);
    putenv('IDLM_TEST_DB_HOST=' . $config['db_host']);
}

function idlm_integration_boot_wordpress(array $config, bool $install): int {
    if (!is_dir($config['content_dir']) && !mkdir($config['content_dir'], 0700, true) && !is_dir($config['content_dir'])) {
        throw new RuntimeException('Unable to create the disposable WordPress content directory.');
    }
    foreach (array('plugins', 'themes', 'uploads') as $directory) {
        $path = $config['content_dir'] . DIRECTORY_SEPARATOR . $directory;
        if (!is_dir($path) && !mkdir($path, 0700, true) && !is_dir($path)) {
            throw new RuntimeException('Unable to create disposable WordPress directory: ' . $directory);
        }
    }

    $mu_dir = $config['content_dir'] . DIRECTORY_SEPARATOR . 'mu-plugins';
    if (!is_dir($mu_dir)) { mkdir($mu_dir, 0700, true); }
    $guard = <<<'PHP'
<?php
if (!defined('ABSPATH')) { exit; }
add_filter('pre_http_request', static function() {
    file_put_contents(WP_CONTENT_DIR . '/outbound-attempts.log', "HTTP blocked\n", FILE_APPEND);
    return new WP_Error('idlm_fixture_outbound_blocked', 'Disposable fixture blocks outbound HTTP.');
}, PHP_INT_MAX);
add_filter('pre_wp_mail', static function() {
    file_put_contents(WP_CONTENT_DIR . '/outbound-attempts.log', "Mail intercepted\n", FILE_APPEND);
    return true;
}, PHP_INT_MAX);
PHP;
    file_put_contents($mu_dir . '/idlm-outbound-guard.php', $guard);

    define('DB_NAME', $config['db_name']);
    define('DB_USER', $config['db_user']);
    define('DB_PASSWORD', $config['db_password']);
    define('DB_HOST', $config['db_host']);
    define('DB_CHARSET', 'utf8mb4');
    define('DB_COLLATE', '');
    define('AUTH_KEY', 'idlm-integration-auth-key');
    define('SECURE_AUTH_KEY', 'idlm-integration-secure-auth-key');
    define('LOGGED_IN_KEY', 'idlm-integration-logged-in-key');
    define('NONCE_KEY', 'idlm-integration-nonce-key');
    define('AUTH_SALT', 'idlm-integration-auth-salt');
    define('SECURE_AUTH_SALT', 'idlm-integration-secure-auth-salt');
    define('LOGGED_IN_SALT', 'idlm-integration-logged-in-salt');
    define('NONCE_SALT', 'idlm-integration-nonce-salt');
    define('WP_HOME', $config['http_url'] ?? 'http://idlm-integration.test');
    define('WP_SITEURL', $config['http_url'] ?? 'http://idlm-integration.test');
    define('WP_CONTENT_DIR', $config['content_dir']);
    define('WP_CONTENT_URL', ($config['http_url'] ?? 'http://idlm-integration.test') . '/wp-content');
    define('WP_PLUGIN_DIR', $config['content_dir'] . DIRECTORY_SEPARATOR . 'plugins');
    define('WP_PLUGIN_URL', 'http://idlm-integration.test/wp-content/plugins');
    define('WP_USE_THEMES', false);
    define('DISABLE_WP_CRON', true);
    define('WP_DEBUG', true);
    define('WP_DEBUG_DISPLAY', false);
    define('WP_ENVIRONMENT_TYPE', 'development');
    if ($install) {
        define('WP_INSTALLING', true);
    }
    define('ABSPATH', $config['wp_root']);

    $table_prefix = $config['table_prefix'];
    require $config['wp_root'] . 'wp-settings.php';

    if (!$install) {
        return 0;
    }

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    $installed = wp_install(
        'IDLM Integration Tests',
        'idlm_admin',
        'idlm-admin@example.test',
        true,
        '',
        'idlm-integration-password'
    );
    wp_installing(false);
    if (empty($installed['user_id'])) {
        throw new RuntimeException('WordPress installation did not return an administrator user ID.');
    }
    return (int) $installed['user_id'];
}

function idlm_integration_load_plugin(array $config): void {
    require_once $config['plugin_root'] . 'interior-design-lead-manager.php';
}

function idlm_integration_remove_directory(string $directory): void {
    if ('' === $directory || false === strpos(basename($directory), 'idlm_test_') || !is_dir($directory)) {
        return;
    }
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($iterator as $item) {
        if ($item->isDir()) {
            rmdir($item->getPathname());
        } else {
            unlink($item->getPathname());
        }
    }
    rmdir($directory);
}
