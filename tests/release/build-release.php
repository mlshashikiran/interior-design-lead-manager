<?php
/**
 * Guarded release artifact builder.
 *
 * Builder: Shashi
 */

declare(strict_types=1);

require_once __DIR__ . '/class-release-artifact.php';

$options = getopt('', array('build', 'confirm:', 'allow-dirty', 'output-dir:', 'help'));
$root = dirname(__DIR__, 2);
$build_requested = isset($options['build']);
$allow_dirty = isset($options['allow-dirty']);
$output_dir = isset($options['output-dir']) ? (string) $options['output-dir'] : $root . '/build';

if (isset($options['help'])) {
    echo "IDLM release artifact builder\n\n";
    echo "Dry-run preflight:\n  php tests/release/build-release.php\n\n";
    echo "Local rehearsal artifact:\n  php tests/release/build-release.php --build --confirm=build-idlm-release-artifact --allow-dirty\n\n";
    echo "A CI-attested candidate is built only by GitHub Actions after the full test matrix.\n";
    exit(0);
}

try {
    $version = IDLM_Release_Artifact::version_evidence($root);
    $files = IDLM_Release_Artifact::collect_files($root);
    $inventory = IDLM_Release_Artifact::validate_inventory($root, $files);
    $git = IDLM_Release_Artifact::git_evidence($root);
    $ci = IDLM_Release_Artifact::ci_evidence($git['revision'], array(
        'GITHUB_ACTIONS' => (string) getenv('GITHUB_ACTIONS'),
        'GITHUB_SHA' => (string) getenv('GITHUB_SHA'),
        'GITHUB_RUN_ID' => (string) getenv('GITHUB_RUN_ID'),
        'IDLM_RELEASE_MATRIX_PASSED' => (string) getenv('IDLM_RELEASE_MATRIX_PASSED'),
    ));

    $blockers = array();
    if (!$inventory['valid']) {
        $blockers[] = 'distribution_inventory_invalid';
    }
    if (!$git['clean']) {
        $blockers[] = 'working_tree_dirty';
    }
    if (!$ci['attested']) {
        $blockers[] = 'ci_attestation_missing';
    }

    $report = array(
        'schema' => IDLM_Release_Artifact::MANIFEST_SCHEMA,
        'mode' => $build_requested ? 'build' : 'dry_run',
        'author_builder' => 'Shashi',
        'version' => $version['version'],
        'version_aligned' => $version['aligned'],
        'revision' => $git['revision'],
        'git_clean' => $git['clean'],
        'changed_entry_count' => $git['changed_entry_count'],
        'ci' => $ci,
        'inventory' => $inventory,
        'artifact_status' => $git['clean'] && $ci['attested'] ? 'ci_attested_candidate' : 'local_rehearsal_only',
        'production_release_approved' => false,
        'blockers' => $blockers,
        'build_requested' => $build_requested,
    );

    if (!$build_requested) {
        echo IDLM_Release_Artifact::encode_json($report) . "\n";
        exit($inventory['valid'] ? 0 : 1);
    }

    if ('build-idlm-release-artifact' !== (string) ($options['confirm'] ?? '')) {
        throw new RuntimeException('Build requires --confirm=build-idlm-release-artifact.');
    }
    if (!$inventory['valid']) {
        throw new RuntimeException('Distribution inventory validation failed.');
    }
    if (!$git['clean'] && !$allow_dirty) {
        throw new RuntimeException('Dirty working tree refused; use --allow-dirty only for a local rehearsal artifact.');
    }

    $report['artifact'] = IDLM_Release_Artifact::build($root, $output_dir, $files, array(
        'version' => $version['version'],
        'revision' => $git['revision'],
        'git_clean' => $git['clean'],
        'ci' => $ci,
        'artifact_status' => $report['artifact_status'],
        'production_release_approved' => false,
        'blockers' => $blockers,
    ));
    echo IDLM_Release_Artifact::encode_json($report) . "\n";
} catch (Throwable $error) {
    fwrite(STDERR, IDLM_Release_Artifact::encode_json(array(
        'schema' => IDLM_Release_Artifact::MANIFEST_SCHEMA,
        'status' => 'error',
        'message' => $error->getMessage(),
    )) . "\n");
    exit(1);
}
