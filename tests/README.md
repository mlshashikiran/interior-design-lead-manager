# Automated Test Baseline

Builder: Shashi

## Fast behavioral suite

Run the dependency-free suite from the plugin root:

```bash
php tests/run.php
```

The suite loads the production classes with small WordPress and `$wpdb` test doubles. It does not connect to or modify the site database. Its 99 contracts cover lead lifecycle and integrity, representative AJAX authorization, encrypted scoped REST/MCP authentication and limits, authorization/query scoping, signed cursor isolation/keyset compatibility, quotation calculations/persistence/autosave, schema migrations, scheduler fallback, retention redaction, stable states, guarded templates, grouped settings, environment-driven MCP paths, semantic print output, deterministic performance profiles/safety gates, read-only query-plan coverage/privacy gates, Phase 8 load-manifest/result/privacy safeguards, Phase 9 release inventory/version/private-file/CI-attestation, candidate-promotion, and disposable WordPress-root handoff gates, authorization-scoped read-cache reuse/invalidation, horizontal-readiness and operational-health evaluation/rendering, the admin facade's public/registration/asset/selector/capability contracts, the completed renderer/settings/shell boundaries, and the Phase 5 lead-detail AJAX read extraction.

## Native WordPress/MySQL integration suite

The opt-in integration suite boots the WordPress installation containing this plugin against a newly created disposable MySQL database:

```bash
IDLM_TEST_DB_USER=test_database_user \
IDLM_TEST_DB_PASSWORD=test_database_password \
IDLM_TEST_DB_HOST='localhost:/path/to/mysqld.sock' \
php tests/integration/run.php
```

Use `host:port` instead of a socket path when appropriate. `IDLM_TEST_WP_ROOT` may override the WordPress root; otherwise the suite derives it from the plugin location. `IDLM_TEST_DB_NAME` is optional and normally generated uniquely.

Safety guarantees:

- Database names must start with `idlm_test_`.
- The runner refuses any pre-existing database rather than reusing or dropping it.
- The database user must have `CREATE DATABASE` and `DROP DATABASE` privileges on the disposable server.
- A shutdown cleanup attempts to drop the generated database and remove its temporary `wp-content` directory even after a failed test.

The 14 native tests cover fresh schema installation, real `dbDelta()` idempotence and upgrade recovery, REST and authenticated AJAX dispatch, admin/frontend renderer selectors, settings-save security and persistence, lead lifecycle persistence and hooks, scoped MCP PII access, catalogue persistence/read models, the frontend quotation list route, concurrent quotation numbering, quotation persistence/totals/list reads, generated semantic print markup, database uniqueness constraints, real small-profile performance seed/verify/scoped-reset behavior, and read-only optimizer/index capture. They never load the configured site database. `.github/workflows/tests.yml` runs both suites across PHP 7.4/8.1/8.4 and WordPress 6.6/latest.

## Performance baseline tooling

Phase 0 inventory, deterministic synthetic datasets, benchmark definitions, the Phase 2 query-plan manifest, and Phase 8 read-only load tooling live under `tests/performance/`. `capture-baseline.php` emits aggregate-only runtime/capacity JSON; `capture-query-plans.php` emits aggregate-only optimizer/index JSON and refuses production. `seed-dataset.php` is dry-run by default and requires explicit confirmation in a non-production environment before adding or resetting one tagged PII-free dataset run. `run-load-qualification.php` is also dry-run by default and requires an exact confirmation plus a benchmark user before it can call the allowlisted authenticated read endpoints. See `tests/performance/README.md`, `docs/PERFORMANCE-BASELINE.md`, and `docs/LOAD-RESILIENCE-QUALIFICATION.md`.

Phase 9 release tooling lives under `tests/release/`. `build-release.php` performs a read-only preflight by default; an explicitly confirmed build validates the `.distignore` inventory, version alignment, private-file/secret-signature policy, single-directory ZIP, exact revision, checksum, and CI attestation. `verify-candidate.php` accepts only the clean matrix-attested candidate for the exact reviewed revision, independently reopens its archive, and emits aggregate pending staging gates without deploying or approving production. See `docs/PRODUCTION-RELEASE.md`.
