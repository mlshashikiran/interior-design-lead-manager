# Development Guide

## Environment and initialization

The plugin requires PHP 7.4+ and WordPress. It has no Composer/npm dependencies or build pipeline. Chart.js and Inter fonts are committed assets. Action Scheduler is optional; WP-Cron is the fallback. The current checkout is commonly run under Local, but production code should not assume that environment.

WordPress loads `interior-design-lead-manager.php`, which requires static classes and boots them on `plugins_loaded`. Activation performs schema installation, schedules jobs, and adds roles. Normal requests also run versioned, idempotent schema/backfill checks; login requests intentionally skip the runtime.

## Automated tests

Run the fast, dependency-free behavioral suite for every change:

```bash
php tests/run.php
```

For schema, REST, lead-persistence, MCP, or quotation-persistence changes, also run the opt-in native suite against a disposable MySQL server:

```bash
IDLM_TEST_DB_USER=test_database_user \
IDLM_TEST_DB_PASSWORD=test_database_password \
IDLM_TEST_DB_HOST='localhost:/path/to/mysqld.sock' \
php tests/integration/run.php
```

CI runs both suites across the supported PHP 7.4/8.1/8.4 and WordPress 6.6/latest matrix. Keep scheduler, authorization, schema, secret migration, integrity, and semantic print contracts in the fast suite when changing those boundaries.

Phase 8 read-only HTTP load measurements use `tests/performance/run-load-qualification.php`. It is dry-run by default and requires a non-production WordPress environment, explicit execution confirmation, and a dedicated benchmark user. Follow [LOAD-RESILIENCE-QUALIFICATION.md](LOAD-RESILIENCE-QUALIFICATION.md); do not run it against production or treat unsigned provisional thresholds as release approval.

Phase 9 release packaging uses `tests/release/build-release.php`. Run it without flags for a read-only preflight. A local dirty artifact requires the exact build confirmation plus `--allow-dirty`, remains rehearsal-only, and must not be deployed. CI waits for the complete PHP/WordPress matrix before producing the clean-revision ZIP, checksum, and manifest, then `tests/release/verify-candidate.php` independently binds that candidate to the reviewed revision and emits pending staging gates. See [PRODUCTION-RELEASE.md](PRODUCTION-RELEASE.md).

The native runner derives the WordPress root from the plugin location unless `IDLM_TEST_WP_ROOT` is supplied. It accepts only newly created `idlm_test_*` databases and drops them after the run. Full safety and coverage details are in [`tests/README.md`](../tests/README.md).

## Coding patterns

- Prefix with `IDLM_`/`idlm_`, guard PHP files with `ABSPATH`, and use WordPress APIs/coding conventions already present.
- Static service methods and direct `$wpdb` are the existing style. New code should isolate responsibilities where possible without breaking public names.
- Keep shared CRM asset changes in `IDLM_Admin_Assets`, frontend shortcode/workspace routing in `IDLM_Admin_Shortcodes`, and public compatibility entry points in `IDLM_Admin`.
- Extracted page templates live under `templates/admin/`, receive one explicit context array through `IDLM_View`, and must not perform request parsing or mutation.
- Sanitize → validate domain value/authorization → persist with prepared SQL → escape at render.
- Use `current_time('mysql')`/WordPress timezone for stored CRM times; use `wp_timezone()` for date calculations.
- Route lead writes through `IDLM_DB`; log material lifecycle changes with `IDLM_DB::log_activity()`.

## Adding functionality

**Feature:** identify its domain owner first. Put lifecycle invariants/data mutation in a focused service or `IDLM_DB`, thin transport/UI code in its handler, and invoke through an existing compatibility method. Review [FLOWS.md](FLOWS.md) for side effects.

**Shortcode:** register through the established facade callback, coordinate frontend rendering in `IDLM_Admin_Shortcodes`, enforce login/capability before rendering, return buffered/escaped markup, enqueue only its `IDLM_Admin_Assets` context, and keep handler names and HTML selectors stable.

**REST endpoint:** register on `rest_api_init`; use a real `permission_callback` where possible. Define methods/arguments, authenticate before reading the body, sanitize/validate, return `WP_REST_Response`/`WP_Error`, and document the route in [INTERFACES.md](INTERFACES.md). Never add it to the authentication-bypass filter casually.

**AJAX endpoint:** register only `wp_ajax_idlm_*` unless anonymous access is explicitly designed; verify a dedicated/shared nonce first, check capability, then object access. Return `wp_send_json_*`. Add the action to the client only after localizing the nonce/URL.

**Database column/table:** change `IDLM_DB::create_tables()` or `IDLM_Quotation_Schema::install()` behind `IDLM_Quotations::install()`, increment only the corresponding schema version, use `dbDelta()`, index real query patterns, and add an idempotent bounded backfill. Test activation and upgrade with existing rows. Add explicit cleanup to repository reset/delete logic because there are no foreign-key cascades.

**Setting:** use an `idlm_` name, capability + nonce protected save, type-specific sanitization, a safe default, and server-side retrieval. Do not send secrets to the browser or diagnostics. Group it with an existing settings array when that reduces option sprawl.

**JavaScript/CSS:** register through the relevant module with `IDLM_VERSION`, scope selectors under `.idlm-*`, preserve `idlm_vars`/`idlmQuoteData` contracts, and test both wp-admin and shortcode contexts. Do not edit `assets/js/chart.js` manually.

## Backward compatibility

Public contracts include class/function names, hooks, capabilities, shortcodes, AJAX action names and payloads, REST namespaces/routes/authentication, option keys, table/column names, stored state literals, JS globals/selectors, and scheduled hook names. Preserve them or add adapters/migrations. Never delete legacy database columns merely because current UI no longer uses them.

## Verification checklist

1. Inspect `git diff` first and preserve unrelated/user changes.
2. Run `php -l` for every PHP file changed and ideally all plugin PHP files.
3. Search registration and call sites for affected hooks/actions/methods/options/tables.
4. Verify nonce, capability, object access, allowed values, prepared SQL, escaping, and error responses.
5. Exercise the affected wp-admin page and front-end shortcode; check browser console/network and responsive/print presentation where relevant.
6. For lifecycle work, test new, repeat/duplicate, qualified, unqualified, owner change, Won/Lost, and rollback/error paths; inspect activities/tasks and queued hooks.
7. For integrations, use test credentials/payloads and confirm audit logging without exposing secrets.
8. For migrations, test fresh activation and upgrade on a database copy; confirm idempotence.
9. Keep plugin header, `IDLM_VERSION`, `readme.txt` stable tag, `CHANGELOG.md`, and `docs/CHANGELOG-DEV.md` aligned. Attribute author/builder entries to Shashi.

The current debt status and boundaries are recorded in [TECH-DEBT.md](TECH-DEBT.md).
The completed admin facade release matrix is recorded in [ADMIN-REFACTOR-VERIFICATION.md](ADMIN-REFACTOR-VERIFICATION.md) and should be repeated when shared callbacks, selectors, or asset contexts change.
Production and scalability work must follow the baselines, phase order, compatibility contracts, and stop conditions in [PRODUCTION-SCALABILITY-PLAN.md](PRODUCTION-SCALABILITY-PLAN.md).
