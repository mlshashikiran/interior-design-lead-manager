# Controlled Production Release

Author / Builder: Shashi  
Status: Phase 9 staging-candidate gate implemented; production rollout authorized by Shashi
Release: 1.100.11
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

On a push, GitHub Actions waits for every PHP 7.4/8.1/8.4 × WordPress 6.6/latest test job, builds from the clean checked-out revision, verifies the recorded SHA-256 file, and uploads the ZIP, checksum, and manifest as one retained workflow artifact. Only that job can emit `ci_attested_candidate`; the manifest still records `production_release_approved: false`.

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
