# Interior Design Lead Manager: Codex Guide

Builder/author attribution for project metadata and engineering documentation is **Shashi**.

## Purpose and architecture

This WordPress plugin is a custom-table CRM for interior-design leads. It captures advertising/form webhooks, deduplicates and routes leads, supports pre-sales qualification and sales handoff, provides admin and authenticated front-end workspaces, sends conversion/audience data to advertising services, records WhatsApp replies, and manages versioned quotations.

Read [docs/ARCHITECTURE.md](docs/ARCHITECTURE.md), [docs/PROJECT-MAP.md](docs/PROJECT-MAP.md), and [docs/DATA-MODEL.md](docs/DATA-MODEL.md) before behavioral work. Start feature-specific reviews with [docs/FEATURE-MAP.md](docs/FEATURE-MAP.md) to identify the blast radius. Consult [docs/FLOWS.md](docs/FLOWS.md) for lifecycle changes, [docs/INTERFACES.md](docs/INTERFACES.md) before changing hooks/endpoints, and [docs/SECURITY.md](docs/SECURITY.md) for any request handler.

## Runtime map

- `interior-design-lead-manager.php`: constants, activation/deactivation, class loading, roles/capabilities, `plugins_loaded` boot.
- `includes/class-db.php`: canonical lead persistence, transition normalization, duplicate/touchpoint handling, activities, tasks, and core schema.
- `includes/class-admin.php`: thin compatibility facade for registrations, menus, and public delegates. Change narrowly.
- `includes/class-admin-lead-reads.php`: focused authenticated lead-detail read and drawer-response handler behind `IDLM_Admin` and `IDLM_Admin_Ajax` delegates.
- `includes/class-admin-assets.php`, `class-admin-shortcodes.php`: shared asset registration/context enqueueing and frontend shortcode/workspace orchestration behind facade callbacks.
- `includes/class-admin-presenter.php`, `class-admin-lead-notifications.php`: display formatting plus WhatsApp read-state/reminder presentation support behind facade delegates.
- `includes/class-admin-account-controller.php`: frontend account context and authenticated profile-update handling behind facade callbacks.
- `includes/class-admin-settings-controller.php`: authenticated settings POST validation, sanitization, option/secret persistence, and grouped-routing synchronization.
- `includes/class-admin-settings-renderer.php`, `templates/admin/settings/`: settings context/environment reads and guarded tab/section presentation behind the established callback.
- `includes/class-admin-dashboard-renderer.php`, `class-admin-kanban-renderer.php`, `class-admin-logs-renderer.php`, `class-admin-leads-renderer.php`: focused page context/query boundaries behind the established admin callbacks.
- `includes/class-integrations.php`: REST ingestion/feeds/MCP, attribution, lead routing, fallback handoff, and email notifications.
- `includes/class-capi.php`, `class-audiences.php`, `class-interakt.php`: outbound advertising and messaging integrations.
- `includes/class-quotations.php`: quotation compatibility facade, transaction/request coordination, CSV validation/streaming, rendering, browser-print/PDF view, and quotation AJAX/admin-post handlers.
- `includes/class-quotation-policy.php`, `class-quotation-lead-read-model.php`: quotation capabilities, object-level lead access, and scoped lead search/editor reads.
- `includes/class-quotation-repository.php`: quotation/catalogue table mapping, atomic numbering, aggregate and catalogue reads/writes, quotation/catalogue list read models, revision content replacement/copying, history, lead quote-value projection, cleanup, and reset persistence.
- `includes/class-quotation-schema.php`: quotation table installation, independent schema version, legacy conversion, and snapshot/section backfills.
- `includes/class-quotation-calculator.php`: deterministic quotation item/section normalization, decimal policy, and totals calculation.
- `includes/class-dashboard.php`: read-only aggregate reporting.
- `includes/class-settings.php`: grouped non-secret routing configuration with standalone-option compatibility mirrors.
- `includes/class-view.php`, `templates/`: guarded reusable presentation boundary for extracted views, including nested `templates/admin/` page templates.
- `includes/class-scheduler.php`: Action Scheduler/WP-Cron registration.
- `includes/class-horizontal-readiness.php`: aggregate-only multi-node deployment preflight for shared infrastructure declarations, cache/queue/cron detection, and streamed-export safety.
- `includes/class-operational-health.php`: aggregate-only administrator health checks and initial thresholds for schema/database/schedules/secrets/storage/cache, Action Scheduler backlog, and recent faults.
- `includes/class-telemetry.php`, `class-mcp-core.php`, `mcp-server.php`: diagnostics and read-only CRM MCP access.
- `assets/js/`: jQuery-based CRM clients; `admin-app.js` is shared by admin and shortcodes. `assets/css/`: shared and module styles. New reusable PHP views belong under `templates/` and receive a single context array.

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

For every successful change: follow the existing version pattern, increment the plugin version, keep the header/`IDLM_VERSION`/`readme.txt` stable tag aligned, update `CHANGELOG.md` with Shashi attribution, and record meaningful behavioral/architectural work in `docs/CHANGELOG-DEV.md`. Update the relevant focused document instead of duplicating details.

At minimum, run `php -l` on every PHP file changed, search registrations/callers for renamed interfaces, verify nonce + capability + object access on handlers, review prepared SQL and output escaping, and manually exercise the affected admin and shortcode context. For schema or lifecycle work, test install/upgrade on a copy of existing data and verify downstream activities, tasks, hooks, integrations, and rollback behavior. See [docs/DEVELOPMENT.md](docs/DEVELOPMENT.md).
