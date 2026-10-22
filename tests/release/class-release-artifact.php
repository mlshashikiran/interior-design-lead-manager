<?php
/**
 * Release artifact inventory, validation, and ZIP creation.
 *
 * Builder: Shashi
 */

declare(strict_types=1);

final class IDLM_Release_Artifact {
    public const PLUGIN_SLUG = 'interior-design-lead-manager';
    public const MANIFEST_SCHEMA = 'idlm-release-artifact/v1';

    /**
     * Runtime files that must be present in every distribution.
     *
     * @return array
     */
    public static function required_files(): array {
        return array(
            'interior-design-lead-manager.php',
            'readme.txt',
            'CHANGELOG.md',
            'PRIVACY.txt',
            'SUPPORT.txt',
            'assets/css/fonts.css',
            'assets/fonts/OFL.txt',
            'assets/fonts/NOTICE.txt',
            'assets/fonts/inter-provenance.json',
            'assets/fonts/inter-latin-300.woff2',
            'assets/fonts/inter-latin-400.woff2',
            'assets/fonts/inter-latin-500.woff2',
            'assets/fonts/inter-latin-600.woff2',
            'assets/fonts/inter-latin-700.woff2',
            'assets/css/admin-style.css',
            'assets/js/admin-app.js',
            'vendor/dompdf/vendor/autoload.php',
            'vendor/dompdf/vendor/composer/installed.json',
            'vendor/dompdf/LICENSE.LGPL',
            'vendor/dompdf/AUTHORS.md',
            'vendor/dompdf/vendor/dompdf/dompdf/LICENSE.LGPL',
            'vendor/dompdf/vendor/dompdf/dompdf/AUTHORS.md',
            'vendor/dompdf/vendor/dompdf/dompdf/lib/res/sRGB2014.icc',
            'vendor/dompdf/vendor/dompdf/dompdf/lib/res/sRGB2014.icc.LICENSE',
            'vendor/dompdf/vendor/composer/LICENSE',
            'vendor/dompdf/vendor/dompdf/php-font-lib/LICENSE',
            'vendor/dompdf/vendor/dompdf/php-svg-lib/LICENSE',
            'vendor/dompdf/vendor/masterminds/html5/LICENSE.txt',
            'vendor/dompdf/vendor/sabberworm/php-css-parser/LICENSE',
            'includes/class-admin.php',
            'includes/class-db.php',
            'includes/class-private-files.php',
            'includes/class-backup-validator.php',
            'includes/class-recovery-guard.php',
            'includes/class-capi-delivery.php',
            'includes/class-audience-dispatch.php',
            'includes/class-integrations.php',
            'includes/class-mcp-query-policy.php',
            'includes/class-quotations.php',
            'templates/workspace.php',
        );
    }

    /**
     * Load normalized distribution exclusion patterns.
     *
     * @param string $root Plugin root.
     * @return array
     */
    public static function distignore_patterns(string $root): array {
        $path = rtrim($root, '/\\') . '/.distignore';
        if (!is_file($path)) {
            throw new RuntimeException('Missing .distignore release policy.');
        }

        $patterns = array();
        foreach ((array) file($path, FILE_IGNORE_NEW_LINES) as $line) {
            $line = trim((string) $line);
            if ('' === $line || 0 === strpos($line, '#')) {
                continue;
            }
            $patterns[] = ltrim(str_replace('\\', '/', $line), '/');
        }
        return array_values(array_unique($patterns));
    }

    /**
     * Determine whether a relative file is excluded from distribution.
     *
     * @param string $relative_path Relative path.
     * @param array  $patterns      .distignore patterns.
     * @return bool
     */
    public static function is_excluded(string $relative_path, array $patterns): bool {
        $relative_path = ltrim(str_replace('\\', '/', $relative_path), '/');
        if ('.git' === $relative_path || 0 === strpos($relative_path, '.git/')) {
            return true;
        }

        foreach ($patterns as $pattern) {
            $pattern = ltrim(str_replace('\\', '/', (string) $pattern), '/');
            if ('' === $pattern) {
                continue;
            }
            if ('/' === substr($pattern, -1)) {
                $prefix = rtrim($pattern, '/');
                if ($relative_path === $prefix || 0 === strpos($relative_path, $prefix . '/')) {
                    return true;
                }
                continue;
            }
            if (false !== strpos($pattern, '/')) {
                if (fnmatch($pattern, $relative_path, FNM_PATHNAME)) {
                    return true;
                }
                continue;
            }
            if (fnmatch($pattern, basename($relative_path))) {
                return true;
            }
        }
        return false;
    }

    /**
     * Collect distributable files without following symlinks.
     *
     * @param string $root Plugin root.
     * @return array
     */
    public static function collect_files(string $root): array {
        $root = rtrim($root, '/\\');
        $patterns = self::distignore_patterns($root);
        $files = array();
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::LEAVES_ONLY
        );

        foreach ($iterator as $file) {
            /** @var SplFileInfo $file */
            $absolute_path = $file->getPathname();
            $relative_path = ltrim(str_replace('\\', '/', substr($absolute_path, strlen($root))), '/');
            if (self::is_excluded($relative_path, $patterns)) {
                continue;
            }
            if ($file->isLink()) {
                throw new RuntimeException('Release inventory refuses symlink: ' . $relative_path);
            }
            if ($file->isFile()) {
                $files[] = $relative_path;
            }
        }

        sort($files, SORT_STRING);
        return $files;
    }

    /**
     * Read and compare all release-version authorities.
     *
     * @param string $root Plugin root.
     * @return array
     */
    public static function version_evidence(string $root): array {
        $root = rtrim($root, '/\\');
        $main = self::read_required($root . '/interior-design-lead-manager.php');
        $readme = self::read_required($root . '/readme.txt');
        $changelog = self::read_required($root . '/CHANGELOG.md');
        $plan = self::read_required($root . '/docs/PRODUCTION-SCALABILITY-PLAN.md');

        $versions = array(
            'plugin_header' => self::match_version('/^\s*\*\s*Version:\s*([0-9]+\.[0-9]+\.[0-9]+)\s*$/m', $main, 'plugin header'),
            'plugin_constant' => self::match_version("/define\(\s*'IDLM_VERSION'\s*,\s*'([0-9]+\.[0-9]+\.[0-9]+)'\s*\)/", $main, 'IDLM_VERSION'),
            'readme_stable_tag' => self::match_version('/^Stable tag:\s*([0-9]+\.[0-9]+\.[0-9]+)\s*$/mi', $readme, 'readme stable tag'),
            'changelog_release' => self::match_version('/^## \[([0-9]+\.[0-9]+\.[0-9]+)\]/m', $changelog, 'changelog release'),
            'plan_release' => self::match_version('/^Current implementation release:\s*([0-9]+\.[0-9]+\.[0-9]+)\s*$/mi', $plan, 'plan release'),
        );
        $unique = array_values(array_unique(array_values($versions)));
        if (1 !== count($unique)) {
            throw new RuntimeException('Release version metadata is not aligned.');
        }

        return array(
            'version' => $unique[0],
            'sources' => $versions,
            'aligned' => true,
        );
    }

    /**
     * Validate the complete distribution inventory.
     *
     * @param string $root  Plugin root.
     * @param array  $files Relative paths.
     * @return array
     */
    public static function validate_inventory(string $root, array $files): array {
        $root = rtrim($root, '/\\');
        $errors = array();
        $required_files = array_merge(self::required_files(), self::bootstrap_required_files($root));
        foreach (array_values(array_unique($required_files)) as $required) {
            if (!in_array($required, $files, true)) {
                $errors[] = 'missing_required:' . $required;
            }
        }

        foreach ($files as $relative_path) {
            $reason = self::forbidden_path_reason((string) $relative_path);
            if ('' !== $reason) {
                $errors[] = $reason . ':' . $relative_path;
                continue;
            }
            $absolute_path = $root . '/' . $relative_path;
            if (self::is_text_file((string) $relative_path)) {
                $content = (string) file_get_contents($absolute_path);
                foreach (self::secret_pattern_names($content) as $name) {
                    $errors[] = 'secret_pattern_' . $name . ':' . $relative_path;
                }
            }
        }

        return array(
            'valid' => empty($errors),
            'errors' => array_values(array_unique($errors)),
            'file_count' => count($files),
            'total_bytes' => array_sum(array_map(static function(string $relative_path) use ($root): int {
                return (int) filesize($root . '/' . $relative_path);
            }, $files)),
        );
    }

    /**
     * Return aggregate Git evidence for the current checkout.
     *
     * @param string $root Plugin root.
     * @return array
     */
    public static function git_evidence(string $root): array {
        $revision = trim(self::run_command(array('git', '-C', $root, 'rev-parse', 'HEAD')));
        $status = trim(self::run_command(array('git', '-C', $root, 'status', '--porcelain=v1')));
        if (!preg_match('/^[a-f0-9]{40}$/', $revision)) {
            throw new RuntimeException('Unable to resolve an exact Git revision.');
        }
        return array(
            'revision' => $revision,
            'clean' => '' === $status,
            'changed_entry_count' => '' === $status ? 0 : count(preg_split('/\r?\n/', $status)),
        );
    }

    /**
     * Determine whether this process is attested by GitHub Actions for HEAD.
     *
     * @param string $revision Current revision.
     * @param array  $environment Environment map.
     * @return array
     */
    public static function ci_evidence(string $revision, array $environment): array {
        $github_actions = 'true' === strtolower((string) ($environment['GITHUB_ACTIONS'] ?? ''));
        $matrix_passed = 'true' === strtolower((string) ($environment['IDLM_RELEASE_MATRIX_PASSED'] ?? ''));
        $sha = strtolower((string) ($environment['GITHUB_SHA'] ?? ''));
        $run_id = (string) ($environment['GITHUB_RUN_ID'] ?? '');
        $attested = $github_actions && $matrix_passed && strtolower($revision) === $sha && 1 === preg_match('/^[0-9]+$/', $run_id);
        return array(
            'provider' => $github_actions ? 'github_actions' : 'local',
            'attested' => $attested,
            'run_id' => $attested ? $run_id : '',
            'matrix_passed' => $github_actions && $matrix_passed,
            'revision_matches' => $github_actions && strtolower($revision) === $sha,
        );
    }

    /**
     * Build and inspect a release ZIP.
     *
     * @param string $root       Plugin root.
     * @param string $output_dir Artifact output directory.
     * @param array  $files      Validated inventory.
     * @param array  $metadata   Manifest metadata.
     * @return array
     */
    public static function build(string $root, string $output_dir, array $files, array $metadata): array {
        if (!class_exists('ZipArchive')) {
            throw new RuntimeException('The PHP zip extension is required to build a release artifact.');
        }
        if (!is_dir($output_dir) && !mkdir($output_dir, 0755, true) && !is_dir($output_dir)) {
            throw new RuntimeException('Unable to create the release output directory.');
        }

        $version = (string) ($metadata['version'] ?? '');
        $base_name = self::PLUGIN_SLUG . '-' . $version;
        $zip_path = rtrim($output_dir, '/\\') . '/' . $base_name . '.zip';
        $zip = new ZipArchive();
        if (true !== $zip->open($zip_path, ZipArchive::CREATE | ZipArchive::OVERWRITE)) {
            throw new RuntimeException('Unable to create the release ZIP.');
        }
        if (!$zip->addEmptyDir(self::PLUGIN_SLUG)) {
            $zip->close();
            throw new RuntimeException('Unable to add the release root directory.');
        }
        sort($files, SORT_STRING);
        foreach ($files as $relative_path) {
            $archive_path = self::PLUGIN_SLUG . '/' . $relative_path;
            if (!$zip->addFile(rtrim($root, '/\\') . '/' . $relative_path, $archive_path)) {
                $zip->close();
                throw new RuntimeException('Unable to add a release file: ' . $relative_path);
            }
        }
        // Normalize every entry, including the empty root. File contents and
        // paths are the build inputs; filesystem times/modes are not.
        for ($index = 0; $index < $zip->numFiles; $index++) {
            $name = (string) $zip->getNameIndex($index);
            $directory = '/' === substr($name, -1);
            if (!$zip->setMtimeName($name, 946684800)
                || !$zip->setExternalAttributesName($name, ZipArchive::OPSYS_UNIX, ($directory ? 0040755 : 0100644) << 16)
                || (!$directory && !$zip->setCompressionName($name, ZipArchive::CM_DEFLATE, 9))) {
                $zip->close();
                throw new RuntimeException('Unable to normalize release ZIP metadata: ' . $name);
            }
        }
        if (!$zip->close()) {
            throw new RuntimeException('Unable to finalize the release ZIP.');
        }

        $inspection = self::inspect_zip($zip_path, $files);
        if (!$inspection['valid']) {
            throw new RuntimeException('Built ZIP failed inspection: ' . implode(', ', $inspection['errors']));
        }

        $checksum = hash_file('sha256', $zip_path);
        if (false === $checksum) {
            throw new RuntimeException('Unable to calculate the artifact checksum.');
        }
        $manifest = array_merge(array(
            'schema' => self::MANIFEST_SCHEMA,
            'author_builder' => 'Shashi',
            'created_at_utc' => gmdate('c'),
            'artifact' => basename($zip_path),
            'sha256' => $checksum,
            'size_bytes' => (int) filesize($zip_path),
            'file_count' => count($files),
            'top_level_directory' => self::PLUGIN_SLUG,
            'inspection' => $inspection,
        ), $metadata);

        $manifest_path = rtrim($output_dir, '/\\') . '/' . $base_name . '.manifest.json';
        $checksum_path = rtrim($output_dir, '/\\') . '/' . $base_name . '.sha256';
        self::write_file($manifest_path, self::encode_json($manifest) . "\n");
        self::write_file($checksum_path, $checksum . '  ' . basename($zip_path) . "\n");

        return array(
            'artifact' => basename($zip_path),
            'manifest' => basename($manifest_path),
            'checksum_file' => basename($checksum_path),
            'sha256' => $checksum,
            'size_bytes' => (int) filesize($zip_path),
            'file_count' => count($files),
            'inspection_valid' => true,
        );
    }

    /**
     * Inspect ZIP paths and compare them with the intended inventory.
     *
     * @param string $zip_path ZIP path.
     * @param array  $files    Expected relative files.
     * @return array
     */
    public static function inspect_zip(string $zip_path, array $files): array {
        $zip = new ZipArchive();
        if (true !== $zip->open($zip_path, ZipArchive::RDONLY)) {
            throw new RuntimeException('Unable to inspect the release ZIP.');
        }

        $actual_files = array();
        $errors = array();
        for ($index = 0; $index < $zip->numFiles; $index++) {
            $name = str_replace('\\', '/', (string) $zip->getNameIndex($index));
            if (self::PLUGIN_SLUG . '/' === $name) {
                continue;
            }
            if (0 !== strpos($name, self::PLUGIN_SLUG . '/')) {
                $errors[] = 'unexpected_top_level:' . $name;
                continue;
            }
            if ('/' === substr($name, -1)) {
                continue;
            }
            $relative_path = substr($name, strlen(self::PLUGIN_SLUG) + 1);
            $reason = self::forbidden_path_reason($relative_path);
            if ('' !== $reason) {
                $errors[] = $reason . ':' . $relative_path;
            }
            $actual_files[] = $relative_path;
        }
        $zip->close();
        sort($actual_files, SORT_STRING);
        $expected_files = array_values($files);
        sort($expected_files, SORT_STRING);
        if ($expected_files !== $actual_files) {
            $errors[] = 'inventory_mismatch';
        }

        return array(
            'valid' => empty($errors),
            'errors' => array_values(array_unique($errors)),
            'single_plugin_directory' => empty(array_filter($errors, static function(string $error): bool {
                return 0 === strpos($error, 'unexpected_top_level:');
            })),
            'inventory_matches' => !in_array('inventory_mismatch', $errors, true),
        );
    }

    /**
     * Return a forbidden-path reason, or an empty string.
     *
     * @param string $relative_path Relative path.
     * @return string
     */
    public static function forbidden_path_reason(string $relative_path): string {
        $relative_path = ltrim(str_replace('\\', '/', $relative_path), '/');
        $segments = explode('/', strtolower($relative_path));
        if (in_array('..', $segments, true)) {
            return 'forbidden_path_traversal';
        }
        if (in_array('.git', $segments, true) || in_array('.github', $segments, true) || in_array('tests', $segments, true)) {
            return 'forbidden_runtime_artifact';
        }
        $basename = basename($relative_path);
        $lower_basename = strtolower($basename);
        if (strpos(strtolower($relative_path), 'services/facebook-connect/') === 0) { return 'forbidden_development_artifact'; }
        if (in_array($lower_basename, array('.graphifyignore', 'design.md', 'quote-builder-redesign.html'), true)
            || strpos($lower_basename, '.graphify_') === 0 || in_array('graphify-out', $segments, true)) {
            return 'forbidden_development_artifact';
        }
        if ('.env' === $lower_basename || 0 === strpos($lower_basename, '.env.') || in_array($basename, array('AGENTS.md'), true)) {
            return 'forbidden_private_file';
        }
        $extension = strtolower((string) pathinfo($relative_path, PATHINFO_EXTENSION));
        if (in_array($extension, array('log', 'tmp', 'sql', 'sqlite', 'db', 'pem', 'key', 'p12', 'pfx'), true)) {
            return 'forbidden_private_extension';
        }
        return '';
    }

    /**
     * Find high-confidence secret signatures in text.
     *
     * @param string $content File content.
     * @return array
     */
    public static function secret_pattern_names(string $content): array {
        $patterns = array(
            'private_key' => '/-----BEGIN (?:RSA |EC |OPENSSH )?PRIVATE KEY-----/',
            'aws_access_key' => '/\bAKIA[0-9A-Z]{16}\b/',
            'google_api_key' => '/\bAIza[0-9A-Za-z_-]{30,}\b/',
            'github_token' => '/\bgh[pousr]_[A-Za-z0-9]{30,}\b/',
            'stripe_live_secret' => '/\bsk_live_[0-9A-Za-z]{20,}\b/',
            'slack_token' => '/\bxox[baprs]-[0-9A-Za-z-]{20,}\b/',
        );
        $matches = array();
        foreach ($patterns as $name => $pattern) {
            if (1 === preg_match($pattern, $content)) {
                $matches[] = $name;
            }
        }
        return $matches;
    }

    /**
     * Encode JSON consistently.
     *
     * @param array $value Data.
     * @return string
     */
    public static function encode_json(array $value): string {
        $encoded = json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        if (false === $encoded) {
            throw new RuntimeException('Unable to encode release evidence.');
        }
        return $encoded;
    }

    private static function is_text_file(string $relative_path): bool {
        $extension = strtolower((string) pathinfo($relative_path, PATHINFO_EXTENSION));
        return in_array($extension, array('php', 'js', 'css', 'json', 'md', 'txt', 'yml', 'yaml', 'xml', 'html'), true);
    }

    private static function bootstrap_required_files(string $root): array {
        $source = self::read_required(rtrim($root, '/\\') . '/interior-design-lead-manager.php');
        preg_match_all("/require_once\s+IDLM_PATH\s*\.\s*'([^']+)'\s*;/", $source, $matches);
        return array_values(array_unique(array_map(static function(string $path): string {
            return ltrim(str_replace('\\', '/', $path), '/');
        }, $matches[1] ?? array())));
    }

    private static function read_required(string $path): string {
        if (!is_file($path)) {
            throw new RuntimeException('Missing release metadata file: ' . basename($path));
        }
        return (string) file_get_contents($path);
    }

    private static function match_version(string $pattern, string $content, string $label): string {
        if (1 !== preg_match($pattern, $content, $matches)) {
            throw new RuntimeException('Unable to read ' . $label . ' version.');
        }
        return (string) $matches[1];
    }

    private static function run_command(array $command): string {
        $descriptor_spec = array(
            1 => array('pipe', 'w'),
            2 => array('pipe', 'w'),
        );
        $process = proc_open($command, $descriptor_spec, $pipes);
        if (!is_resource($process)) {
            throw new RuntimeException('Unable to execute Git release check.');
        }
        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $status = proc_close($process);
        if (0 !== $status) {
            throw new RuntimeException('Git release check failed: ' . trim((string) $stderr));
        }
        return (string) $stdout;
    }

    private static function write_file(string $path, string $content): void {
        if (false === file_put_contents($path, $content, LOCK_EX)) {
            throw new RuntimeException('Unable to write release evidence: ' . basename($path));
        }
    }
}
