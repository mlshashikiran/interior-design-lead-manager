# Project Map

```text
interior-design-lead-manager.php
    [Bootstrap] Header, constants, includes, activation/deactivation, roles and boot.

includes/class-db.php
    [Compatibility facade + data] Core schema, lifecycle delegates, activities, tasks and logs.

includes/class-lead-lifecycle-service.php
    [Domain service] Lead insert/upsert/update normalization, validation, persistence and hooks.
includes/class-lead-deletion-service.php, class-integrity-command.php
    [Integrity] Transactional dependent cleanup and WP-CLI orphan audit/repair.
includes/class-lead-access-policy.php, class-state-registry.php
    [Policies] Shared lead visibility/query scope and stable workflow keys/labels.
includes/class-secret-store.php, class-production-security.php, class-data-retention.php,
class-routing-state.php, class-operational-health.php, class-horizontal-readiness.php
    [Infrastructure] Encrypted/environment secrets, production MCP/proxy/upload policy,
    redaction/pruning, atomic counters/locks, aggregate operational health, and multi-node deployment preflight.
includes/class-settings.php, class-view.php; templates/
    [Infrastructure + presentation] Grouped non-secret routing settings with legacy mirrors,
    plus guarded escaped templates for reusable frontend/admin views.

includes/class-admin.php
    [Thin compatibility facade] Registrations, admin menus, and public
    AJAX/page/asset/shortcode/account/presentation delegates.
includes/class-admin-users-controller.php; templates/admin/users.php
    [User access] Administrator-only CRM user listing, account creation, managed role changes,
    access suspension/restoration, reset-link initiation, and self/final-admin safeguards.
includes/class-admin-assets.php, class-admin-shortcodes.php
    [Shell controllers] Shared asset registration/context enqueueing and frontend
    shortcode/composite-workspace orchestration.
includes/class-admin-presenter.php, class-admin-lead-notifications.php
    [Presentation support] Display formatting, Kanban value mappings, WhatsApp read state,
    reminder evaluation, and pending-call counts.
includes/class-admin-account-controller.php
    [Authenticated controller] Frontend account context and profile/password updates.
includes/class-admin-settings-controller.php
    [Authenticated controller] Settings POST detection, capability/nonce checks, sanitization,
    option and secret persistence, and grouped-routing synchronization.
includes/class-admin-settings-renderer.php; templates/admin/settings/
    [Page controller + presentation] Settings context/environment reads and guarded tab/section
    markup behind the established admin/frontend callback.
includes/class-admin-ajax.php
    [Compatibility controller] Authenticated AJAX delegates plus handlers not yet extracted.
includes/class-admin-lead-reads.php
    [Authenticated read controller] Lead-detail validation, scoped reads, drawer response,
    and WhatsApp read-state coordination behind both established admin delegate layers.
includes/class-cursor-pagination.php
    [Pagination support] Short-lived HMAC-signed cursor positions bound to the current user,
    authorization/filter scope, page size and stable sort contract.
includes/class-admin-dashboard-renderer.php, class-admin-kanban-renderer.php,
class-admin-logs-renderer.php, class-admin-leads-renderer.php; templates/admin/
    [Page controllers + presentation] Dashboard/Kanban/leads contexts, bounded audit-log reads,
    shared lead overlays, and guarded markup behind the established callbacks.

includes/class-dashboard-read-model.php, class-read-model-cache.php
    [Scoped read/cache] Authorization-safe dashboard context, short-lived object-cache/transient
    storage, stampede protection and generation invalidation.
includes/class-dashboard.php
    [Aggregate queries] Canonical CRM metrics and date-range comparisons behind the cached
    public read-model entry point.

includes/class-integrations.php
    [REST + domain + integrations] Attribution cookies, webhook ingestion, Google feeds,
    MCP transport, assignment pools/routing/fallbacks, handoff/routing email.

includes/class-capi.php
    [Integration] Meta CAPI, Google Measurement Protocol/offline CSV, LinkedIn conversion
    events, idempotency lookup, asynchronous dispatch and retries.

includes/class-audiences.php
    [Integration] Rule-based audience selection, Meta/LinkedIn uploads, Google CSV feeds,
    sync audit logging.

includes/class-interakt.php
    [Integration] Interakt tracking events and Interakt/Meta WhatsApp inbound webhooks.

includes/class-scheduler.php
    [Infrastructure] Action Scheduler and WP-Cron setup/cleanup.

includes/class-telemetry.php
    [Shared utility] PHP error/exception interception, persisted diagnostics and pruning.

includes/class-operational-health.php
    [Operations] Administrator-only aggregate checks for schema/database/schedules/secrets/storage/cache,
    Action Scheduler backlog age/failures, and recent operational fault thresholds.

includes/class-mcp-core.php
    [Public read API] MCP JSON-RPC tools and guarded read-only SQL execution.

includes/class-quotations.php
    [Compatibility facade] Public quotation API and focused-module delegates.
includes/class-quotation-controller.php, class-quotation-renderer.php
    [Controller + presentation] Request/transaction handlers and escaped semantic views/print output.

includes/class-quotation-policy.php
    [Authorization policy] Quotation capability checks, unrestricted manager scope and
    object-level current-user access to lead-linked quotations.

includes/class-quotation-lead-read-model.php
    [Scoped read model] Lead existence, search, initial editor choices and selected-lead
    prefilling under administrator/manager or representative-owner visibility.

includes/class-quotation-repository.php
    [Database repository] Table mapping, atomic numbering, quote/catalogue reads and writes,
    list read models, revision content copy, lead quote-value projection, cleanup and reset.

includes/class-quotation-schema.php
    [Database schema/migrations] Ten-table installation, schema version, legacy quote
    conversion, and revision snapshot/section backfills.

includes/class-quotation-calculator.php
    [Domain calculator] Deterministic item/section normalization, decimal policy and totals.

assets/js/admin-app.js
    [Shared client, legacy] Lead list/drawer, cursor/page history, settings, imports, tasks,
    activities and AJAX.
assets/js/dashboard.js
    [Client] Dashboard filters, metrics and Chart.js rendering.
assets/js/kanban.js
    [Client] Kanban loading, filtering and stage mutation.
assets/js/quotations.js
    [Client] Quote editor calculations, search, autosave and catalogue controls.
assets/js/workspace.js
    [Client] Composite workspace navigation state.
assets/js/chart.js
    [Vendored dependency] Minified Chart.js 4.4.1; do not hand-edit.

assets/css/admin-style.css
    [Shared presentation] CRM shell, list, drawer, forms and responsive styles.
assets/css/dashboard.css, kanban.css, quotations.css, workspace.css
    [Module presentation] Module-specific layouts and print rules.
assets/css/fonts.css; assets/fonts/
    [Static dependency] Locally hosted Inter font faces.

mcp-server.php
    [CLI integration entry] Local stdio MCP server; loads WordPress and MCP core. Optional
    `IDLM_MCP_WP_LOAD` and `IDLM_MCP_ERROR_LOG` environment variables override its paths.
docs/TELEPHONY-INTEGRATION.md
    [Existing design note] Provider-neutral call-log integration contract.
docs/DESIGN.md
    [Existing design history] UI/design direction; descriptive, not runtime authority.
readme.txt
    [Public documentation] WordPress plugin readme and release history.
CHANGELOG.md
    [Public changelog] Release-level changes.
docs/
    [Engineering documentation] Architecture, data, flows, interfaces, security and debt.
docs/ADMIN-REFACTOR-PLAN.md
    [Completed plan] Compatibility-first staged extraction and final verification of the admin facade.
docs/ADMIN-REFACTOR-VERIFICATION.md
    [Release evidence] Automated, live route, interaction, responsive, access, console and network matrix.
docs/PRODUCTION-SCALABILITY-PLAN.md
    [Approved plan] Production gates and evidence-led performance, capacity, resilience and horizontal-scaling work.
docs/PRODUCTION-RELEASE.md
    [Release operations] Phase 9 artifact, checksum, revision, CI-attestation, staging and rollout gates.
docs/HORIZONTAL-DEPLOYMENT.md
    [Operations] Phase 6 shared-infrastructure declarations, runtime-write inventory, qualification order and stop conditions.
tests/bootstrap.php, tests/run.php
    [Fast behavioral tests] Dependency-free WordPress/$wpdb doubles and observable contracts.
tests/integration/
    [Native integration tests] Guarded disposable WordPress/MySQL bootstrap, runner and workers.
tests/performance/
    [Phase 0/2/8 performance tooling] Aggregate-only environment/capacity and query-plan/index capture,
    critical benchmark manifests, deterministic synthetic seed tooling, and the guarded authenticated
    read-only load runner plus resilience qualification matrix.
tests/release/
    [Phase 9 release tooling] Dry-run preflight plus explicitly confirmed `.distignore` inventory,
    version, secret-signature, single-directory ZIP, revision, CI-attestation and checksum gates.
```

## Responsibility hotspots

The compatibility facades intentionally retain existing public methods/actions while focused admin AJAX and extracted page renderers, lifecycle, quotation controller/renderer, policy, repository, schema, security, routing, retention, settings, view, and integrity classes own their established behavior. Any future extraction from `class-integrations.php` must retain its route and hook contracts.
