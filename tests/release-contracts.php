<?php
/**
 * Production release artifact contracts.
 *
 * Builder: Shashi
 */

declare(strict_types=1);

require_once __DIR__ . '/release/class-release-artifact.php';
require_once __DIR__ . '/release/class-release-candidate.php';

idlm_test('Phase 9 distribution inventory includes runtime files and excludes development artifacts', static function(): void {
    $root = dirname(__DIR__);
    $files = IDLM_Release_Artifact::collect_files($root);
    $validation = IDLM_Release_Artifact::validate_inventory($root, $files);

    assert_true($validation['valid'], implode(', ', $validation['errors']));
    foreach (IDLM_Release_Artifact::required_files() as $required) {
        assert_true(in_array($required, $files, true), 'Missing release runtime file: ' . $required);
    }
    foreach (array('.distignore', '.gitignore', 'tests/run.php', '.github/workflows/tests.yml', 'AGENTS.md', 'docs/CHANGELOG-DEV.md', 'docs/TECH-DEBT.md') as $excluded) {
        assert_false(in_array($excluded, $files, true), 'Development-only file entered the release inventory: ' . $excluded);
    }
});

idlm_test('Phase 9 release metadata authorities remain aligned', static function(): void {
    $evidence = IDLM_Release_Artifact::version_evidence(dirname(__DIR__));
    assert_true($evidence['aligned']);
    assert_same('1.100.7', $evidence['version']);
    assert_count_value(5, $evidence['sources']);
    assert_count_value(1, array_unique(array_values($evidence['sources'])));
});

idlm_test('Phase 9 promotion accepts only a clean matrix-attested exact revision', static function(): void {
    $revision = str_repeat('a', 40);
    $sha256 = str_repeat('b', 64);
    $manifest = array(
        'schema' => IDLM_Release_Artifact::MANIFEST_SCHEMA,
        'author_builder' => 'Shashi',
        'artifact' => 'interior-design-lead-manager-1.100.7.zip',
        'version' => '1.100.7',
        'revision' => $revision,
        'git_clean' => true,
        'artifact_status' => 'ci_attested_candidate',
        'production_release_approved' => false,
        'blockers' => array(),
        'sha256' => $sha256,
        'file_count' => count(IDLM_Release_Artifact::required_files()),
        'ci' => array(
            'provider' => 'github_actions',
            'attested' => true,
            'run_id' => '123456',
            'matrix_passed' => true,
            'revision_matches' => true,
        ),
        'inspection' => array(
            'valid' => true,
            'single_plugin_directory' => true,
            'inventory_matches' => true,
        ),
    );

    assert_same(array(), IDLM_Release_Candidate::validate_manifest(
        $manifest,
        $manifest['artifact'],
        $sha256,
        $sha256,
        $revision
    ));

    $manifest['git_clean'] = false;
    $manifest['ci']['attested'] = false;
    $errors = IDLM_Release_Candidate::validate_manifest($manifest, $manifest['artifact'], $sha256, $sha256, $revision);
    assert_true(in_array('git_not_clean', $errors, true));
    assert_true(in_array('ci_evidence_invalid', $errors, true));
});

idlm_test('Phase 9 checksum parsing binds the digest to one safe artifact name', static function(): void {
    $artifact = 'interior-design-lead-manager-1.100.7.zip';
    $sha256 = str_repeat('c', 64);
    assert_same($sha256, IDLM_Release_Candidate::parse_checksum($sha256 . '  ' . $artifact . "\n", $artifact));

    $failed = false;
    try {
        IDLM_Release_Candidate::parse_checksum($sha256 . "  ../" . $artifact . "\n", $artifact);
    } catch (RuntimeException $error) {
        $failed = true;
    }
    assert_true($failed, 'Checksum parser accepted a path or mismatched artifact.');
});

idlm_test('Phase 9 staging report keeps every operational gate pending and production unapproved', static function(): void {
    $report = IDLM_Release_Candidate::staging_report(array(
        'verified' => true,
        'version' => '1.100.7',
        'revision' => str_repeat('d', 40),
        'artifact' => 'interior-design-lead-manager-1.100.7.zip',
        'sha256' => str_repeat('e', 64),
        'ci_run_id' => '987654',
    ));

    assert_true($report['candidate_verified']);
    assert_false($report['staging_rehearsal_ready']);
    assert_false($report['production_release_approved']);
    assert_count_value(12, $report['gates']);
    assert_count_value(1, array_unique(array_values($report['gates'])));
    assert_same('pending_operator_evidence', reset($report['gates']));
});

idlm_test('Phase 9 candidate verifier reopens and independently inventories the CI archive', static function(): void {
    $directory = sys_get_temp_dir() . '/idlm-release-candidate-' . bin2hex(random_bytes(6));
    assert_true(mkdir($directory, 0700));
    $artifact = 'interior-design-lead-manager-1.100.7.zip';
    $zip_path = $directory . '/' . $artifact;
    $manifest_path = $directory . '/interior-design-lead-manager-1.100.7.manifest.json';
    $checksum_path = $directory . '/interior-design-lead-manager-1.100.7.sha256';
    $revision = str_repeat('f', 40);

    try {
        $zip = new ZipArchive();
        assert_true(true === $zip->open($zip_path, ZipArchive::CREATE | ZipArchive::OVERWRITE));
        $zip->addEmptyDir(IDLM_Release_Artifact::PLUGIN_SLUG);
        foreach (IDLM_Release_Artifact::required_files() as $required) {
            $zip->addFromString(IDLM_Release_Artifact::PLUGIN_SLUG . '/' . $required, 'fixture');
        }
        assert_true($zip->close());
        $sha256 = hash_file('sha256', $zip_path);
        assert_true(is_string($sha256));

        $manifest = array(
            'schema' => IDLM_Release_Artifact::MANIFEST_SCHEMA,
            'author_builder' => 'Shashi',
            'artifact' => $artifact,
            'version' => '1.100.7',
            'revision' => $revision,
            'git_clean' => true,
            'artifact_status' => 'ci_attested_candidate',
            'production_release_approved' => false,
            'blockers' => array(),
            'sha256' => $sha256,
            'file_count' => count(IDLM_Release_Artifact::required_files()),
            'ci' => array(
                'provider' => 'github_actions',
                'attested' => true,
                'run_id' => '24680',
                'matrix_passed' => true,
                'revision_matches' => true,
            ),
            'inspection' => array(
                'valid' => true,
                'single_plugin_directory' => true,
                'inventory_matches' => true,
            ),
        );
        file_put_contents($manifest_path, IDLM_Release_Artifact::encode_json($manifest) . "\n");
        file_put_contents($checksum_path, $sha256 . '  ' . $artifact . "\n");

        $verified = IDLM_Release_Candidate::verify_directory($directory, $revision);
        assert_true($verified['verified']);
        assert_same($sha256, $verified['sha256']);
        assert_true($verified['archive']['single_plugin_directory']);
        assert_true($verified['archive']['required_runtime_files_present']);
    } finally {
        foreach (array($zip_path, $manifest_path, $checksum_path) as $path) {
            if (is_file($path)) {
                unlink($path);
            }
        }
        if (is_dir($directory)) {
            rmdir($directory);
        }
    }
});

idlm_test('Phase 9 CI verifies the candidate before exposing staging evidence', static function(): void {
    $workflow = (string) file_get_contents(dirname(__DIR__) . '/.github/workflows/tests.yml');
    $command = (string) file_get_contents(__DIR__ . '/release/verify-candidate.php');
    assert_contains('verify-candidate.php --artifact-dir=build --expected-revision="${GITHUB_SHA}"', $workflow);
    assert_contains('production_release_approved', $command);
    assert_contains('IDLM_Release_Candidate::verify_directory', $command);
});

idlm_test('native performance subprocesses inherit the disposable CI WordPress root', static function(): void {
    $bootstrap = (string) file_get_contents(__DIR__ . '/integration/bootstrap.php');
    assert_contains("putenv('IDLM_PERF_WP_ROOT=' . \$config['wp_root']);", $bootstrap);
});

idlm_test('Phase 9 artifact policy rejects private files and high-confidence credential signatures', static function(): void {
    assert_same('forbidden_runtime_artifact', IDLM_Release_Artifact::forbidden_path_reason('tests/run.php'));
    assert_same('forbidden_private_file', IDLM_Release_Artifact::forbidden_path_reason('.env'));
    assert_same('forbidden_private_file', IDLM_Release_Artifact::forbidden_path_reason('.env.production'));
    assert_same('forbidden_private_extension', IDLM_Release_Artifact::forbidden_path_reason('backup.sql'));
    assert_same('forbidden_path_traversal', IDLM_Release_Artifact::forbidden_path_reason('../outside.php'));
    assert_same('', IDLM_Release_Artifact::forbidden_path_reason('includes/class-db.php'));
    assert_same(array('private_key'), IDLM_Release_Artifact::secret_pattern_names('-----BEGIN PRIVATE KEY-----'));
    assert_same(array(), IDLM_Release_Artifact::secret_pattern_names("IDLM_Secret_Store::get('idlm_webhook_api_key')"));
});

idlm_test('Phase 9 CI attestation binds a GitHub run to the exact revision', static function(): void {
    $revision = str_repeat('a', 40);
    $valid = IDLM_Release_Artifact::ci_evidence($revision, array(
        'GITHUB_ACTIONS' => 'true',
        'GITHUB_SHA' => $revision,
        'GITHUB_RUN_ID' => '123456',
        'IDLM_RELEASE_MATRIX_PASSED' => 'true',
    ));
    assert_true($valid['attested']);
    assert_same('123456', $valid['run_id']);

    $without_matrix_gate = IDLM_Release_Artifact::ci_evidence($revision, array(
        'GITHUB_ACTIONS' => 'true',
        'GITHUB_SHA' => $revision,
        'GITHUB_RUN_ID' => '123456',
    ));
    assert_false($without_matrix_gate['attested']);

    $mismatch = IDLM_Release_Artifact::ci_evidence($revision, array(
        'GITHUB_ACTIONS' => 'true',
        'GITHUB_SHA' => str_repeat('b', 40),
        'GITHUB_RUN_ID' => '123456',
        'IDLM_RELEASE_MATRIX_PASSED' => 'true',
    ));
    assert_false($mismatch['attested']);
    assert_same('', $mismatch['run_id']);
});
