# Interior Design Lead Manager: Codex Guide

Builder/author attribution for project metadata and engineering documentation is **Shashi**.

## Purpose and architecture

This WordPress plugin is a custom-table CRM for interior-design leads. It captures advertising/form webhooks, deduplicates and routes leads, supports pre-sales qualification and sales handoff, provides admin and authenticated front-end workspaces, sends conversion/audience data to advertising services, records WhatsApp replies, and manages versioned quotations.

Use the documentation as a targeted index, not a mandatory reading bundle. Consult [docs/PROJECT-MAP.md](docs/PROJECT-MAP.md) for unfamiliar areas; [docs/FEATURE-MAP.md](docs/FEATURE-MAP.md) before cross-module or feature work; [docs/FLOWS.md](docs/FLOWS.md) for lifecycle changes; [docs/INTERFACES.md](docs/INTERFACES.md) for hooks, AJAX, REST, webhooks, or MCP; [docs/DATA-MODEL.md](docs/DATA-MODEL.md) for schema or reporting work; and [docs/SECURITY.md](docs/SECURITY.md) for request handlers or data access. Read [docs/ARCHITECTURE.md](docs/ARCHITECTURE.md) when the change crosses module boundaries or conflicts with established structure.

## Runtime map

- `interior-design-lead-manager.php`: bootstrap, lifecycle registration, role/capability setup, and class loading.
- `includes/class-db.php`: canonical lead persistence and core schema. Route lifecycle writes through its approved methods.
- `includes/class-admin.php`: compatibility facade. Keep public callbacks stable and change narrowly.
- `includes/class-integrations.php`, `class-capi.php`, `class-audiences.php`, and `class-interakt.php`: inbound and outbound integrations; review authentication and audit behavior first.
- `includes/class-quotations.php`, `class-quotation-repository.php`, and `class-quotation-schema.php`: quotation transactions, persistence, and schema; preserve numbering, totals, history, and access rules.
- `includes/class-admin-assets.php`, `class-admin-shortcodes.php`, `templates/`, `assets/js/`, and `assets/css/`: shared admin/shortcode presentation contracts.
- `includes/class-scheduler.php`, `class-horizontal-readiness.php`, `class-operational-health.php`, and MCP/telemetry classes: operational behavior and diagnostics.

For the complete ownership map, read [docs/PROJECT-MAP.md](docs/PROJECT-MAP.md).

## Knowledge graph

When `graphify-out/graph.json` exists, use Graphify first for unfamiliar cross-module architecture, dependency, or change-impact questions. Query the focused question with a bounded result budget; state the query and its material finding in the task response before verifying implementation decisions against the cited source files and project documentation.

Refresh the graph with `/graphify . --update` after meaningful source or documentation changes. Do not rebuild it for small isolated edits, and do not rely on a graph snapshot that is missing or older than the relevant changes. Generated Graphify output remains local and is excluded from Git.

## Shared requirements

All agents must:

- follow this guide and relevant files under `docs/`;
- preserve existing project conventions and backward compatibility;
- inspect both the current implementation and documented contracts, investigating material discrepancies rather than assuming either is automatically correct;
- avoid unrelated refactoring;
- preserve unrelated working-tree changes;
- attribute authored metadata and engineering documentation to Shashi;
- apply the repository's required version and changelog policy.

## Engineering execution policy

- Follow the global model-routing policy. Model selection is configured outside this file; prefer one model completing a task end to end and delegate only when the work materially benefits from another capability or genuine parallelism.
- Before editing, inspect only enough of the repository to understand the relevant implementation, existing patterns and utilities, applicable documentation and tests, and version/changelog conventions. Do not perform broad audits unless the task requires one, and reuse findings already established in the current conversation.
- Make the smallest complete change that satisfies the requested behaviour. Reuse existing components, functions, APIs, design-system primitives, file organization, naming, and style. Do not add abstractions or dependencies when existing project mechanisms are adequate.
- Avoid unrelated refactoring, cleanup, formatting, modernization, renaming, and architectural changes. Fix the underlying cause of a defect when practical instead of masking its visible symptom.
- Preserve unrelated working-tree changes. If the requested approach conflicts with the existing architecture or creates meaningful risk, explain the conflict and use the safer compatible approach.
- For existing UI, preserve the established styling, CSS variables, components, markup conventions, responsive behaviour, and admin/shortcode parity. Avoid duplicate CSS or JavaScript, and check affected desktop and mobile contexts.

## Risk-proportionate verification

The model implementing a change should normally verify its own work. Prefer `changed behaviour -> targeted tests -> relevant validation`; do not create separate review phases or run the full suite by default.

- Low-risk documentation, copy, CSS, template, mechanical, and isolated UI changes require lightweight targeted checks.
- Normal-risk bug fixes, features, application logic, JavaScript behaviour, refactors, and integrations require targeted regression tests where appropriate plus relevant syntax, lint, and build checks.
- High-risk authentication, authorization, capabilities, nonces, secrets, database/schema, public API, transaction/concurrency, destructive, and cross-cutting changes require stronger reasoning, targeted tests, and broader verification proportional to the affected surface. Independent review is appropriate when production risk justifies it.
- Run the full test suite when shared/core behaviour or multiple modules are materially affected, targeted tests are insufficient, release requirements demand it, or the user explicitly requests it.
- When a test fails, investigate the relevant failure, fix failures caused by the change, rerun the failed or affected test first, and broaden verification only when necessary. Do not repeat successful tests or duplicate verification across agents without a concrete reason.

## Implementation rules

- Preserve procedural bootstrap plus static `IDLM_*` classes unless an explicitly planned migration changes them. There is no Composer, npm, autoloader, namespace, or build pipeline; use the committed fast and disposable native test suites.
- Prefix global names, hooks, options, transients, handles, and capabilities with `idlm_`/`IDLM_`.
- Use `IDLM_DB::insert_lead()`, `upsert_inbound_lead()`, and `update_lead()` for lifecycle writes. Direct writes can skip duplicate policy, timestamps, activity logs, task state, handoff state, and `idlm_lead_stage_converted`/Interakt hooks.
- Treat stage, quality, call status, ownership, handoff, quote totals/status, and attribution as coupled domain state. Trace callers and side effects before changing them.
- Match existing WordPress APIs and formatting. Guard PHP files with `ABSPATH`; sanitize on input, validate domain values, use `$wpdb->prepare()` for variable SQL, and escape at output.
- Keep admin-only pages under `admin_menu`; shortcode output must enforce CRM/quotation capabilities and enqueue only required assets. Existing shortcodes are compatibility contracts.
- Register new authenticated mutations through a dedicated handler with a nonce and capability check. Add object-level lead access checks for non-manager users. Never rely on `is_admin()` for authorization.
- Public REST routes must have explicit authentication inside a `permission_callback` or documented callback validation. Prefer headers over query-string secrets. Webhooks require key/HMAC verification before parsing or persistence.
- Store secrets only in protected server-side settings; never localize them to JavaScript, log them, or include them in diagnostics/URLs.
- Uploads must use WordPress media/file APIs plus explicit extension, MIME, size, and authorization rules appropriate to the field.
- Schema changes belong in `IDLM_DB::create_tables()` or `IDLM_Quotation_Schema::install()` behind `IDLM_Quotations::install()`, using `dbDelta()`. Increment `IDLM_DB_VERSION` or `IDLM_Quotation_Schema::VERSION`/`IDLM_Quotations::SCHEMA_VERSION`, add an idempotent backfill when needed, and preserve existing columns/data. Do not tie schema migration state to the release version.
- Use Action Scheduler when available and retain WP-Cron fallback conventions. Activation and deactivation schedule/clear jobs; handlers must be idempotent and overlap-safe.
- Do not casually modify `class-admin.php`, `IDLM_DB::update_lead()`, `IDLM_DB::insert_lead()`, assignment atomics/fallbacks, REST authentication bypass, quotation save transactions/totals, reset/delete flows, or MCP SQL validation.

## Verification and documentation

## Production deployment target

- When Shashi requests deployment of this plugin to LIVE, use the saved production deployment MCP connection.
- Confirm the MCP target domain before mutating production, deploy only the reviewed release artifact, and report the deployed version and verification result.

For a release-ready or user-requested release change, follow the existing version pattern; keep the header/`IDLM_VERSION`/`readme.txt` stable tag aligned; update `CHANGELOG.md` with Shashi attribution; and record meaningful behavioral or architectural work in `docs/CHANGELOG-DEV.md`. For an unreleased local iteration or low-impact documentation/style change, update only the focused documentation needed to keep it accurate.

Run `php -l` for every changed PHP file. Search registrations and callers only when changing an interface; review nonce, capability, object access, SQL, and escaping only where the affected code handles requests or data; and manually exercise the affected admin or shortcode context when the change alters it. For schema or lifecycle work, test install/upgrade on a copy of existing data and verify downstream activities, tasks, hooks, integrations, and rollback behavior. See [docs/DEVELOPMENT.md](docs/DEVELOPMENT.md).
