<?php
/**
 * Verify a CI-built release candidate before staging rehearsal.
 *
 * Builder: Shashi
 */

declare(strict_types=1);

require_once __DIR__ . '/class-release-candidate.php';

$options = getopt('', array('artifact-dir:', 'expected-revision:', 'help'));

if (isset($options['help'])) {
    echo "IDLM release-candidate verifier\n\n";
    echo "php tests/release/verify-candidate.php --artifact-dir=build --expected-revision=<40-character-git-sha>\n";
    echo "Verifies the CI candidate and emits pending staging gates; it never deploys or approves production.\n";
    exit(0);
}

try {
    $artifact_dir = (string) ($options['artifact-dir'] ?? '');
    $expected_revision = strtolower((string) ($options['expected-revision'] ?? ''));
    if ('' === $artifact_dir || '' === $expected_revision) {
        throw new RuntimeException('Both --artifact-dir and --expected-revision are required.');
    }

    $candidate = IDLM_Release_Candidate::verify_directory($artifact_dir, $expected_revision);
    echo IDLM_Release_Artifact::encode_json(IDLM_Release_Candidate::staging_report($candidate)) . "\n";
} catch (Throwable $error) {
    fwrite(STDERR, IDLM_Release_Artifact::encode_json(array(
        'schema' => IDLM_Release_Candidate::EVIDENCE_SCHEMA,
        'status' => 'error',
        'message' => $error->getMessage(),
        'production_release_approved' => false,
    )) . "\n");
    exit(1);
}
