<?php
/**
 * Production release artifact contracts.
 *
 * Builder: Shashi
 */

declare(strict_types=1);

require_once __DIR__ . '/release/class-release-artifact.php';
require_once __DIR__ . '/release/class-release-candidate.php';
require_once __DIR__ . '/release/class-package-check-result.php';

idlm_test('REL007 package checker decodes exact clean success and preserves every diagnostic', static function(): void {
    assert_same(array(), IDLM_Package_Check_Result::decode("Success: Checks complete. No errors found.\n", 0, '')['diagnostics']);
    assert_same(array(), IDLM_Package_Check_Result::decode('[]', 0, '')['diagnostics']);
    $rows = array(array('file'=>'includes/example.php','line'=>1,'column'=>1,'type'=>'WARNING','code'=>'Example.Rule','message'=>'Still fails'));
    assert_same($rows, IDLM_Package_Check_Result::decode(json_encode($rows), 0, '')['diagnostics']);
});

idlm_test('REL007 package checker refuses malformed or failed clean-looking output', static function(): void {
    foreach (array(array('',0,''), array('{}',0,''), array('[{}]',0,''), array('Success: Checks complete. No errors found.',1,''), array('Success: Checks complete. No errors found.',0,'warning'), array('noise Success: Checks complete. No errors found.',0,''), array('[]',1,'')) as $input) {
        try { IDLM_Package_Check_Result::decode($input[0],$input[1],$input[2]); }
        catch (RuntimeException $error) { continue; }
        throw new RuntimeException('Accepted malformed or failed checker output');
    }
});

idlm_test('Phase 9 distribution inventory includes runtime files and excludes development artifacts', static function(): void {
    $root = dirname(__DIR__);
    $files = IDLM_Release_Artifact::collect_files($root);
    $validation = IDLM_Release_Artifact::validate_inventory($root, $files);

    assert_true($validation['valid'], implode(', ', $validation['errors']));
    foreach (IDLM_Release_Artifact::required_files() as $required) {
        assert_true(in_array($required, $files, true), 'Missing release runtime file: ' . $required);
    }
    foreach (array('.distignore', '.gitignore', '.graphifyignore', 'design.md', 'quote-builder-redesign.html', 'includes/graphify-out/cache/example.json', 'tests/run.php', '.github/workflows/tests.yml', 'AGENTS.md', 'docs/CHANGELOG-DEV.md', 'docs/TECH-DEBT.md', 'services/facebook-connect/config.php', 'services/facebook-connect/public/index.php') as $excluded) {
        assert_false(in_array($excluded, $files, true), 'Development-only file entered the release inventory: ' . $excluded);
        assert_true(IDLM_Release_Artifact::is_excluded($excluded, IDLM_Release_Artifact::distignore_patterns($root)), 'Exclusion policy missing for ' . $excluded);
    }
});

idlm_test('T22-A-REL007 independent archive policy refuses local design and graph artifacts', static function(): void {
    foreach (array('.graphifyignore', '.graphify_cache', 'design.md', 'quote-builder-redesign.html', 'graphify-out/graph.json', 'services/facebook-connect/config.php', 'services/facebook-connect/public/index.php') as $path) {
        assert_same('forbidden_development_artifact', IDLM_Release_Artifact::forbidden_path_reason($path));
    }
    foreach (array('PRIVACY.txt', 'SUPPORT.txt', 'vendor/dompdf/AUTHORS.md', 'assets/fonts/inter-provenance.json') as $path) {
        assert_same('', IDLM_Release_Artifact::forbidden_path_reason($path));
    }
});

idlm_test('Phase 9 release metadata authorities remain aligned', static function(): void {
    $evidence = IDLM_Release_Artifact::version_evidence(dirname(__DIR__));
    assert_true($evidence['aligned']);
    assert_same('1.102.91', $evidence['version']);
    assert_count_value(5, $evidence['sources']);
    assert_count_value(1, array_unique(array_values($evidence['sources'])));
});

idlm_test('Phase 9 promotion accepts only a clean matrix-attested exact revision', static function(): void {
    $revision = str_repeat('a', 40);
    $sha256 = str_repeat('b', 64);
    $manifest = array(
        'schema' => IDLM_Release_Artifact::MANIFEST_SCHEMA,
        'author_builder' => 'Shashi',
        'artifact' => 'interior-design-lead-manager-1.101.19.zip',
        'version' => '1.101.19',
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
    $artifact = 'interior-design-lead-manager-1.101.19.zip';
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
        'version' => '1.101.19',
        'revision' => str_repeat('d', 40),
        'artifact' => 'interior-design-lead-manager-1.101.19.zip',
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
    $artifact = 'interior-design-lead-manager-1.101.19.zip';
    $zip_path = $directory . '/' . $artifact;
    $manifest_path = $directory . '/interior-design-lead-manager-1.101.19.manifest.json';
    $checksum_path = $directory . '/interior-design-lead-manager-1.101.19.sha256';
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
            'version' => '1.101.19',
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

idlm_test('T22-F-REL002 Inter notice matches verified font and complete upstream license bytes', static function(): void {
    $root = dirname(__DIR__);
    foreach (array('assets/fonts/OFL.txt', 'assets/fonts/NOTICE.txt', 'assets/fonts/inter-provenance.json') as $path) {
        assert_true(is_file($root . '/' . $path), 'Missing font distribution notice: ' . $path);
    }
    $provenance = json_decode((string) file_get_contents($root . '/assets/fonts/inter-provenance.json'), true);
    assert_true(is_array($provenance));
    assert_same('66647c0bbbe41a850d79d9c76fb13add3378940f', $provenance['upstream_revision']);
    assert_same('4.001', $provenance['font_version']);
    assert_same('262481e844521b326f5ecd053e59b98c8b2da78c8ee1bdbb6e8174305e54935a', hash_file('sha256', $root . '/assets/fonts/OFL.txt'));
    assert_same($provenance['license']['sha256'], hash_file('sha256', $root . '/assets/fonts/OFL.txt'));
    assert_count_value(5, $provenance['files']);
    $files = IDLM_Release_Artifact::collect_files($root);
    $css = (string) file_get_contents($root . '/assets/css/fonts.css');
    foreach ($provenance['files'] as $path => $hash) {
        assert_same('c940764593d0fe5d596be327ca7558855e018039fb78509aa21921fd3644c3e4', $hash);
        assert_same($hash, hash_file('sha256', $root . '/' . $path));
        assert_true(in_array($path, $files, true), 'Font missing from package inventory.');
        assert_contains(basename($path), $css);
    }
    $notice = (string) file_get_contents($root . '/assets/fonts/NOTICE.txt');
    assert_contains('OFL.txt', $notice);
    assert_contains($provenance['upstream_revision'], $notice);
    assert_contains($provenance['distribution']['url'], $notice);
});

idlm_test('T22-F-REL002 distribution refuses missing Inter resources or notices', static function(): void {
    $root = dirname(__DIR__);
    $files = IDLM_Release_Artifact::collect_files($root);
    foreach (array('assets/css/fonts.css', 'assets/fonts/OFL.txt', 'assets/fonts/NOTICE.txt', 'assets/fonts/inter-provenance.json', 'assets/fonts/inter-latin-300.woff2', 'assets/fonts/inter-latin-400.woff2', 'assets/fonts/inter-latin-500.woff2', 'assets/fonts/inter-latin-600.woff2', 'assets/fonts/inter-latin-700.woff2') as $path) {
        $validation = IDLM_Release_Artifact::validate_inventory($root, array_values(array_diff($files, array($path))));
        assert_false($validation['valid'], 'Distribution accepted a missing font notice.');
        assert_true(in_array('missing_required:' . $path, $validation['errors'], true));
    }
});

idlm_test('T22-A-REL006 repeated ZIP builds ignore source times permissions and inventory order', static function(): void {
    $directory = sys_get_temp_dir() . '/idlm-reproducible-' . bin2hex(random_bytes(6));
    assert_true(mkdir($directory, 0700));
    assert_true(mkdir($directory . '/source', 0700));
    assert_true(mkdir($directory . '/source/assets', 0700));
    $files = array('readme.txt', 'assets/example.txt');
    $metadata = array('version'=>'fixture', 'artifact_status'=>'local_rehearsal_only', 'production_release_approved'=>false);
    try {
        foreach ($files as $file) {
            assert_true(false !== file_put_contents($directory . '/source/' . $file, 'Synthetic release bytes: ' . $file));
            assert_true(touch($directory . '/source/' . $file, 1609459200));
            assert_true(chmod($directory . '/source/' . $file, 0600));
        }
        if (!method_exists('ZipArchive', 'setMtimeName')) {
            try { IDLM_Release_Artifact::build($directory . '/source', $directory . '/first', $files, $metadata); }
            catch (RuntimeException $error) {
                assert_contains('Release packaging requires ZipArchive::setMtimeName', $error->getMessage());
                assert_false(is_dir($directory . '/first'), 'Unsupported builder wrote output before refusing.');
                return;
            }
            throw new RuntimeException('Unsupported ZIP metadata API was accepted.');
        }
        $first = IDLM_Release_Artifact::build($directory . '/source', $directory . '/first', $files, $metadata);
        // ZIP DOS timestamps resolve to two seconds; cross that boundary to
        // reproduce the audit's current-time root entry without a fake clock.
        usleep(2100000);
        foreach ($files as $file) {
            assert_true(touch($directory . '/source/' . $file, 1735689600));
            assert_true(chmod($directory . '/source/' . $file, 0644));
        }
        clearstatcache();
        $second = IDLM_Release_Artifact::build($directory . '/source', $directory . '/second', array_reverse($files), $metadata);
        assert_same($first['sha256'], $second['sha256'], 'Identical source produced different ZIP bytes.');
        assert_same(file_get_contents($directory . '/first/' . $first['artifact']), file_get_contents($directory . '/second/' . $second['artifact']));
        $zip = new ZipArchive();
        assert_same(true, $zip->open($directory . '/first/' . $first['artifact'], ZipArchive::RDONLY));
        try {
            assert_same(3, $zip->numFiles);
            assert_same(IDLM_Release_Artifact::PLUGIN_SLUG . '/', $zip->getNameIndex(0));
            for ($index = 0; $index < $zip->numFiles; $index++) {
                $stat = $zip->statIndex($index);
                assert_same(946684800, $stat['mtime'], 'Entry timestamp was not normalized.');
                assert_true($zip->getExternalAttributesIndex($index, $operating_system, $attributes));
                assert_same(ZipArchive::OPSYS_UNIX, $operating_system);
                assert_same(($index === 0 ? 0040755 : 0100644) << 16, $attributes);
                if ($index > 0) {
                    $relative = substr($stat['name'], strlen(IDLM_Release_Artifact::PLUGIN_SLUG) + 1);
                    assert_same(file_get_contents($directory . '/source/' . $relative), $zip->getFromIndex($index));
                }
            }
        } finally { $zip->close(); }
        assert_same($files, array('readme.txt', 'assets/example.txt'), 'Builder mutated caller inventory.');
    } finally {
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($iterator as $entry) { $entry->isDir() ? rmdir($entry->getPathname()) : unlink($entry->getPathname()); }
        rmdir($directory);
    }
});
