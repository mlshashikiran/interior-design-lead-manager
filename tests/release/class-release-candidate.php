<?php
/**
 * Release-candidate promotion and staging-rehearsal verification.
 *
 * Builder: Shashi
 */

declare(strict_types=1);

require_once __DIR__ . '/class-release-artifact.php';

final class IDLM_Release_Candidate {
    public const EVIDENCE_SCHEMA = 'idlm-staging-rehearsal/v1';

    /**
     * Verify one CI-built candidate directory without extracting the archive.
     *
     * @param string $artifact_dir      Directory containing ZIP, manifest, and checksum.
     * @param string $expected_revision Exact reviewed Git revision.
     * @return array
     */
    public static function verify_directory(string $artifact_dir, string $expected_revision): array {
        $artifact_dir = rtrim($artifact_dir, '/\\');
        if (!is_dir($artifact_dir)) {
            throw new RuntimeException('Release candidate directory does not exist.');
        }
        if (!preg_match('/^[a-f0-9]{40}$/', strtolower($expected_revision))) {
            throw new RuntimeException('Expected revision must be an exact 40-character Git SHA.');
        }

        $manifest_path = self::single_file($artifact_dir, '*.manifest.json', 'manifest');
        $manifest = self::read_manifest($manifest_path);
        $artifact_name = basename((string) ($manifest['artifact'] ?? ''));
        if ('' === $artifact_name || $artifact_name !== (string) ($manifest['artifact'] ?? '')) {
            throw new RuntimeException('Candidate manifest contains an invalid artifact name.');
        }

        $zip_path = $artifact_dir . DIRECTORY_SEPARATOR . $artifact_name;
        $checksum_path = self::single_file($artifact_dir, '*.sha256', 'checksum');
        if (!is_file($zip_path) || is_link($zip_path) || is_link($manifest_path) || is_link($checksum_path)) {
            throw new RuntimeException('Candidate files must be regular non-symlink files.');
        }

        $actual_sha256 = hash_file('sha256', $zip_path);
        if (false === $actual_sha256) {
            throw new RuntimeException('Unable to calculate candidate checksum.');
        }
        $recorded_sha256 = self::parse_checksum((string) file_get_contents($checksum_path), $artifact_name);
        $validation = self::validate_manifest($manifest, $artifact_name, $actual_sha256, $recorded_sha256, $expected_revision);
        $inspection = self::inspect_zip($zip_path, (int) ($manifest['file_count'] ?? 0));
        $errors = array_merge($validation, $inspection['errors']);
        if ($errors) {
            throw new RuntimeException('Release candidate verification failed: ' . implode(', ', array_values(array_unique($errors))));
        }

        return array(
            'verified' => true,
            'version' => (string) $manifest['version'],
            'revision' => strtolower((string) $manifest['revision']),
            'artifact' => $artifact_name,
            'sha256' => strtolower($actual_sha256),
            'file_count' => (int) $manifest['file_count'],
            'ci_run_id' => (string) $manifest['ci']['run_id'],
            'archive' => $inspection['summary'],
        );
    }

    /**
     * Validate promotion-critical manifest evidence.
     *
     * @return array Error codes.
     */
    public static function validate_manifest(
        array $manifest,
        string $artifact_name,
        string $actual_sha256,
        string $recorded_sha256,
        string $expected_revision
    ): array {
        $errors = array();
        if (IDLM_Release_Artifact::MANIFEST_SCHEMA !== (string) ($manifest['schema'] ?? '')) {
            $errors[] = 'manifest_schema_invalid';
        }
        if ('Shashi' !== (string) ($manifest['author_builder'] ?? '')) {
            $errors[] = 'author_builder_invalid';
        }
        if ($artifact_name !== (string) ($manifest['artifact'] ?? '')) {
            $errors[] = 'artifact_name_mismatch';
        }
        if (!preg_match('/^[0-9]+\.[0-9]+\.[0-9]+$/', (string) ($manifest['version'] ?? ''))) {
            $errors[] = 'version_invalid';
        }
        if (strtolower($expected_revision) !== strtolower((string) ($manifest['revision'] ?? ''))) {
            $errors[] = 'revision_mismatch';
        }
        if (empty($manifest['git_clean'])) {
            $errors[] = 'git_not_clean';
        }
        if ('ci_attested_candidate' !== (string) ($manifest['artifact_status'] ?? '')) {
            $errors[] = 'candidate_not_ci_attested';
        }
        if (!empty($manifest['production_release_approved'])) {
            $errors[] = 'unexpected_production_approval';
        }
        if (!empty($manifest['blockers'])) {
            $errors[] = 'candidate_has_blockers';
        }

        $ci = isset($manifest['ci']) && is_array($manifest['ci']) ? $manifest['ci'] : array();
        if ('github_actions' !== (string) ($ci['provider'] ?? '') || empty($ci['attested']) || empty($ci['matrix_passed']) || empty($ci['revision_matches'])) {
            $errors[] = 'ci_evidence_invalid';
        }
        if (!preg_match('/^[0-9]+$/', (string) ($ci['run_id'] ?? ''))) {
            $errors[] = 'ci_run_invalid';
        }

        $inspection = isset($manifest['inspection']) && is_array($manifest['inspection']) ? $manifest['inspection'] : array();
        if (empty($inspection['valid']) || empty($inspection['single_plugin_directory']) || empty($inspection['inventory_matches'])) {
            $errors[] = 'build_inspection_invalid';
        }
        if ((int) ($manifest['file_count'] ?? 0) < count(IDLM_Release_Artifact::required_files())) {
            $errors[] = 'file_count_invalid';
        }
        if (!preg_match('/^[a-f0-9]{64}$/', strtolower((string) ($manifest['sha256'] ?? '')))
            || !hash_equals(strtolower((string) ($manifest['sha256'] ?? '')), strtolower($actual_sha256))
            || !hash_equals(strtolower($recorded_sha256), strtolower($actual_sha256))) {
            $errors[] = 'checksum_mismatch';
        }

        return array_values(array_unique($errors));
    }

    /**
     * Parse the standard SHA-256 file and bind it to the artifact filename.
     */
    public static function parse_checksum(string $content, string $artifact_name): string {
        $line = trim($content);
        if (!preg_match('/^([a-fA-F0-9]{64})\s{2}([^\r\n]+)$/', $line, $matches)) {
            throw new RuntimeException('Candidate checksum file has an invalid format.');
        }
        if ($artifact_name !== $matches[2] || basename($matches[2]) !== $matches[2]) {
            throw new RuntimeException('Candidate checksum is not bound to the expected artifact.');
        }
        return strtolower($matches[1]);
    }

    /**
     * Produce an aggregate-only staging gate report. No gate is auto-approved.
     */
    public static function staging_report(array $candidate): array {
        $gates = array();
        foreach (array(
            'database_backup_verified',
            'media_backup_verified',
            'restore_readiness_verified',
            'staging_deployment_completed',
            'release_matrix_passed',
            'schema_state_recorded',
            'queue_state_recorded',
            'cron_state_recorded',
            'environment_state_recorded',
            'monitoring_ready',
            'rollback_command_recorded',
            'controlled_smoke_passed',
        ) as $gate) {
            $gates[$gate] = 'pending_operator_evidence';
        }

        return array(
            'schema' => self::EVIDENCE_SCHEMA,
            'author_builder' => 'Shashi',
            'candidate_verified' => !empty($candidate['verified']),
            'candidate' => array(
                'version' => (string) ($candidate['version'] ?? ''),
                'revision' => (string) ($candidate['revision'] ?? ''),
                'artifact' => (string) ($candidate['artifact'] ?? ''),
                'sha256' => (string) ($candidate['sha256'] ?? ''),
                'ci_run_id' => (string) ($candidate['ci_run_id'] ?? ''),
            ),
            'gates' => $gates,
            'staging_rehearsal_ready' => false,
            'production_release_approved' => false,
        );
    }

    /**
     * Inspect archive paths and required runtime inventory without extraction.
     */
    private static function inspect_zip(string $zip_path, int $expected_file_count): array {
        if (!class_exists('ZipArchive')) {
            throw new RuntimeException('The PHP zip extension is required to verify a release candidate.');
        }
        $zip = new ZipArchive();
        if (true !== $zip->open($zip_path, ZipArchive::RDONLY)) {
            throw new RuntimeException('Unable to open the release candidate ZIP.');
        }

        $files = array();
        $errors = array();
        for ($index = 0; $index < $zip->numFiles; $index++) {
            $name = str_replace('\\', '/', (string) $zip->getNameIndex($index));
            if (IDLM_Release_Artifact::PLUGIN_SLUG . '/' === $name || '/' === substr($name, -1)) {
                continue;
            }
            if (0 !== strpos($name, IDLM_Release_Artifact::PLUGIN_SLUG . '/')) {
                $errors[] = 'unexpected_top_level';
                continue;
            }
            $relative_path = substr($name, strlen(IDLM_Release_Artifact::PLUGIN_SLUG) + 1);
            if ('' === $relative_path || '' !== IDLM_Release_Artifact::forbidden_path_reason($relative_path)) {
                $errors[] = 'forbidden_archive_path';
                continue;
            }
            if (in_array($relative_path, $files, true)) {
                $errors[] = 'duplicate_archive_path';
            }
            $files[] = $relative_path;
        }
        $zip->close();

        foreach (IDLM_Release_Artifact::required_files() as $required) {
            if (!in_array($required, $files, true)) {
                $errors[] = 'missing_required_runtime_file';
                break;
            }
        }
        if ($expected_file_count !== count($files)) {
            $errors[] = 'archive_file_count_mismatch';
        }

        return array(
            'errors' => array_values(array_unique($errors)),
            'summary' => array(
                'single_plugin_directory' => !in_array('unexpected_top_level', $errors, true),
                'required_runtime_files_present' => !in_array('missing_required_runtime_file', $errors, true),
                'file_count_matches' => !in_array('archive_file_count_mismatch', $errors, true),
                'file_count' => count($files),
            ),
        );
    }

    private static function single_file(string $directory, string $pattern, string $label): string {
        $matches = glob($directory . DIRECTORY_SEPARATOR . $pattern);
        if (false === $matches || 1 !== count($matches)) {
            throw new RuntimeException('Candidate directory must contain exactly one ' . $label . ' file.');
        }
        return (string) $matches[0];
    }

    private static function read_manifest(string $path): array {
        $decoded = json_decode((string) file_get_contents($path), true);
        if (!is_array($decoded)) {
            throw new RuntimeException('Candidate manifest is not valid JSON.');
        }
        return $decoded;
    }
}
