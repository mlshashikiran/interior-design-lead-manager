# Controlled Production Release

Author / Builder: Shashi  
Status: Release tooling implemented; current audit candidate blocked and unapproved
Release checkpoint: 1.102.88
Started: 2026-08-31

## Scope

The Phase 9 artifact builder creates a release candidate without deploying it. It:

- Reads `.distignore` and excludes source-control, CI, tests, internal engineering logs, local build output, logs, temporary files, and legacy development material.
- Requires the plugin bootstrap, public readme/changelog, critical runtime classes, assets, and workspace template.
- Aligns the plugin header, `IDLM_VERSION`, readme stable tag, latest changelog entry, and production-plan implementation release.
- Rejects symlinks, private key/database/credential-file extensions, and high-confidence private-key or provider-token signatures.
- Builds one `interior-design-lead-manager/` directory inside the ZIP, reopens the ZIP, and compares its inventory with the validated source inventory.
- Records the exact Git revision, clean/dirty state, CI attestation, file count, artifact size, and SHA-256 checksum without CRM data or credentials.

The staging-candidate verifier then independently reopens the CI output and binds the ZIP, checksum file, manifest, version, clean exact revision, completed matrix, GitHub run, file count, required runtime inventory, and single plugin directory. It emits a staging-rehearsal gate report with every operator-controlled backup, restore, deployment, state, monitoring, rollback, and smoke item still pending.

Neither gate approves a production deployment, creates a Git tag or GitHub Release, takes a backup, extracts or deploys the archive, changes the database, or sends traffic.

## Commands

Run the non-mutating preflight:

```bash
php tests/release/build-release.php
```

Build a local rehearsal artifact from the current dirty development checkout:

```bash
php tests/release/build-release.php \
  --build \
  --confirm=build-idlm-release-artifact \
  --allow-dirty
```

Rehearsal output is stored under `build/` and is always labeled `local_rehearsal_only`. It must not be deployed or promoted.

On a push, GitHub Actions waits for every PHP 7.4/8.1/8.4/8.5 × WordPress 6.6/latest test job, builds from the clean checked-out revision, verifies the recorded SHA-256 file, and uploads the ZIP, checksum, and manifest as one retained workflow artifact. The workflow is configured to emit `ci_attested_candidate`; the manifest still records `production_release_approved: false`.

The same job verifies candidate promotion evidence before upload:

```bash
php tests/release/verify-candidate.php \
  --artifact-dir=build \
  --expected-revision=<40-character-reviewed-git-sha>
```

Local rehearsal artifacts fail this promotion gate by design because they are not clean matrix-attested candidates.

## Candidate review

Before tagging or staging, the release owner must verify:

1. The remote matrix and release-artifact job passed for the same 40-character revision recorded in the manifest.
2. `staging-rehearsal-gates.json` reports `candidate_verified: true`; the checksum recomputes successfully and the ZIP contains exactly one plugin directory.
3. The artifact contains required runtime files and no `.git`, `.github`, `tests`, `AGENTS.md`, environment files, logs, database exports, private keys, or local build output.
4. Header, constant, stable tag, changelog, plan, manifest, and intended Git tag use the same version.
5. Every staging gate remains pending until an operator records the corresponding backup, restore, deployment, state, monitoring, rollback, and controlled-smoke evidence.
6. Phase 8 qualification evidence and production owner approvals are complete before production use.

## Remaining Phase 9 work

- Execute and review remote CI and the new promotion verifier from a committed revision.
- Tag the exact reviewed revision and attach the CI-built artifact/checksum.
- Verify database and media backups plus restore readiness.
- Deploy the exact artifact to staging and run the release matrix.
- Record schema, queue, cron, environment, monitoring, and rollback state.
- Perform the monitored production rollout, controlled lifecycle/quotation smoke, observation window, and rollback rehearsal required by the plan.

## Current audit checkpoint — 2026-10-09

Builder: Shashi. REL-001 remains **IN PROGRESS, prerequisite-blocked**. Current `1.102.71` source is dirty/uncommitted, has no matching completed CI and retains OPEN REL-002–REL-007 plus M6/M7 qualification. The guarded local 450-file rehearsal is byte-identical to its source after isolated extraction, but independent ZIP builds differ. Ten existing release contracts pass; expected promotion rejection is negative-gate evidence, not release approval. [Current hashes, scope and next steps](AUDIT-REMEDIATION-RELEASE-ATTESTATION-SLICE.md).

Reconcile the complete reviewed source/dependency tree only after the prerequisites are satisfied. Verify authentic CI run/artifact origin for the exact committed revision; manifest booleans and local environment flags cannot establish a remote run or replace audit closure. The automated builder blocker list does not certify licenses, disclosures, runtime qualification or Plugin Check triage. Final-artifact native/HTTP/browser/upgrade/restore/output checks and rollback rehearsal remain required before promotion. Next executable finding is REL-002.

## Inter notice package gate — 1.102.72

Builder: Shashi. REL-002 is IMPLEMENTED locally: release inventory requires the five unchanged Inter font copies, existing font CSS, complete matching OFL, readable notice and machine-readable source/hash record. Twelve release contracts and a 453-file rehearsal/extraction identity pass. The exact upstream/Google source match is recorded in [the font notice slice](AUDIT-REMEDIATION-FONT-NOTICE-SLICE.md). Final clean candidate/CI, other release findings and M6/M7 qualifications remain open. Next executable finding: REL-003; REL-001 is revisited at final release.

## Public disclosure package gate — 1.102.74

Builder: Shashi. REL-004 is IMPLEMENTED locally: the public readme plus required [privacy](../PRIVACY.txt) and [support/update](../SUPPORT.txt) guides describe actual provider data transfers, cookies, credential/retention/erasure/recovery limits and owner-supplied contact. Twelve focused release contracts and a 455-file independent ZIP/source comparison pass. This is a local unapproved rehearsal, not an installed WordPress release. Consent and replay-receipt policy, support-floor/matrix, reproducibility, Plugin Check and final artifact qualification remain pending. [Evidence and limits](AUDIT-REMEDIATION-PUBLIC-DISCLOSURES-SLICE.md). Next executable finding: REL-005.

## Deterministic ZIP inputs — REL-006 (2026-10-11)

Builder: Shashi. Candidate 1.102.76 normalizes every ZIP entry, including the root: fixed epoch 946684800, UNIX directory/file modes 040755/0100644, sorted filenames and DEFLATE level 9. Every metadata operation is checked. CI release packaging sets TZ=UTC; use `TZ=UTC php tests/release/build-release.php` for preflight/manual build commands and keep PHP zip/libzip/zlib versions fixed for byte comparisons. Manifest build timestamps/attestation remain real and may differ across builds; the ZIP and checksum are the reproducibility target. Matching ZIPs do not approve dirty source or replace exact CI/installed-artifact qualification. [Named evidence and remaining limits](AUDIT-REMEDIATION-ZIP-REPRODUCIBILITY-SLICE.md).

## Static package gate — REL-007 checkpoint (2026-10-11)

Builder: Shashi. Candidate 1.102.77 removes local design/graph artifacts and protects bundled runtime/license inventory. CI checks its actual ZIP with pinned Plugin Check 2.1.0 in disposable WordPress 6.6; full default static JSON uploads on failure and candidate upload requires the checker to pass. The wrapper fails on diagnostics even when strict-json exits zero. **Historical checkpoint: 138 errors / 2,032 warnings; REL-007 was IN PROGRESS.** Neither the ledger's local justifications nor the passing release tests approve outstanding SQL/request source findings. Continue the [named review queue and qualification](AUDIT-REMEDIATION-PACKAGE-CHECK-SLICE.md) before exact clean CI/installed artifact promotion.

### REL-007 source review continuation — 2026-10-11

Builder: Shashi. Local candidate 1.102.78 fixes Meta verification input shapes/unslashing and CLI global prefixes. Final default static check still fails with **138 errors / 2,018 warnings**; 1,484 diagnostics still require source review. The source-specific ledger does not bypass the CI diagnostic gate. [Current source checkpoint and exact artifact](AUDIT-REMEDIATION-PACKAGE-SOURCE-SLICE.md).

## Private managed ZIP / REL-007 completion — 2026-10-18

Builder: Shashi. [REL-007 static completion, candidate 1.102.83](AUDIT-REMEDIATION-PACKAGE-STATIC-COMPLETION-SLICE.md). Official Plugin Check 2.1.0 default static gate PASS on the 453-file source-matching ZIP: zero errors, zero warnings, zero review-required diagnostics. 385 fast (including 16 release), 331 native and 445 HTTP/stdio cases PASS; 87 changed PHP files lint clean. Source fixes resolve 438 diagnostic instances; 694 bounded, source-specific review exceptions account for 1,740 instances. Private managed ZIP selected by Shashi. REL-007 IMPLEMENTED locally; REL-001, M6/M7 and exact clean CI/installed-artifact qualification remain IN PROGRESS. The artifact remains a dirty-tree local rehearsal, not a CI-attested release. Its SHA-256 is `c970e2751c67b8c7ce8b27cbcea5da285cafbd6b21896dea8d750634fb2173d1`. Public privacy/support guides are bundled as TXT with the same disclosures; root Markdown notices triggered directory diagnostics. The short readme retains current entries; full CHANGELOG and the exact pre-trim readme are retained. Final distribution/update/support ownership, complete clean commit/matching CI and installed artifact checks remain required. No publication or deployment performed.

## Current candidate — 1.102.88

Shashi: M6 is complete; selected-topology local qualification and exact installed-runtime evidence are in [the candidate report](M6-M7-RELEASE-CANDIDATE.md). Historical checkpoints below retain original outcomes. Source reconciliation and authentic CI for the reviewed revision are still required. This is a public GitHub repository: pushing requires explicit permission under workspace AGENTS.md. No manifest flag can substitute for the remote matrix. Production deployment and real credential/provider acceptance remain separately controlled.

## CI build-host prerequisite — 2026-10-23

Builder: Shashi. Deterministic release packaging requires `ZipArchive::setMtimeName` (PHP 8.0+ or PECL zip 1.16+, built with libzip 1.0+; [PHP API reference](https://www.php.net/manual/en/ziparchive.setmtimename.php)); the CI release job uses PHP 8.1. The builder refuses an unavailable metadata API before creating output. PHP 7.4 remains a supported plugin runtime and runs the rejection contract; newer matrix cells run the complete separated-build reproducibility contract.

The CI test runners install Poppler (`pdftotext` / `pdfinfo`) for independent PDF content, multipage and paper-size assertions and retain the resolved version. These are test-host tools; plugin PDF generation continues to use bundled dompdf. Crash recovery waits at most five seconds for MySQL to finish dead-session cleanup and retries only the explicit busy result, never a persistence error.

The legacy WordPress 6.6 / PHP 8.5 combination emits an upstream `WP_Theme_JSON` null-offset deprecation at line 2792 even without plugin behavior. The disposable test bootstrap records that one exact severity/message/file/line under `known-core-diagnostics.jsonl`, only for WordPress 6.6, PHP 8.5 and core file SHA-256 `743dac6c9bac3b829d0eccf981b08b3c6ea109f7a837103d72d0ba50c12ccd12`. Every other diagnostic remains a failure. Core files are unmodified, and the exception is excluded from the plugin ZIP. This legacy combination is not warning-free; the selected latest WordPress production runtime has no exception. Initial failing logs remain in GitHub run 39905525734.
