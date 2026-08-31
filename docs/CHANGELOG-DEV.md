# Engineering Change Log

Use this log for meaningful architectural or behavioral engineering changes. Public release notes remain in `CHANGELOG.md`/`readme.txt`.

## 2026-08-31

- **Area:** Scoped frontend authentication and password recovery
- **Change:** Added email-only CRM sign-in plus branded password request and reset-completion states using plugin-specific anonymous handlers and WordPress core sign-on, reset-key, validation, password, cookie, and notification APIs. Builder: Shashi.
- **Files affected:** Authentication controller, bootstrap, access template, core reset styling, shared login styles, security/interface/flow documentation, tests, release metadata, and changelogs.
- **Compatibility notes:** Normal `wp-login.php` username/email authentication remains unchanged. Public CRM handlers use action nonces, generic failures, safe local redirects, and a hashed one-minute reset-request throttle. Plugin version advanced from `1.100.10` to `1.100.11`.

- **Area:** Frontend CRM login design
- **Change:** Reworked the anonymous login into a responsive split entry surface with product context, focused authentication hierarchy, native password recovery, and token-aligned responsive/focus states. Builder: Shashi.
- **Files affected:** Access template, shared design-system styles, fast contracts, release metadata, and changelogs.
- **Compatibility notes:** WordPress core continues to own credential submission, authentication cookies, password recovery, and redirects. Plugin version advanced from `1.100.9` to `1.100.10`.

- **Area:** Frontend account sign-out
- **Change:** Added a Sign Out action to My Account using WordPress core's nonce-protected logout URL and returning to the current CRM page. Builder: Shashi.
- **Files affected:** Account controller/template, workspace styles, fast contracts, release metadata, and changelogs.
- **Compatibility notes:** WordPress remains responsible for session invalidation and nonce verification. Plugin version advanced from `1.100.8` to `1.100.9`.

- **Area:** Frontend CRM authentication
- **Change:** Replaced the anonymous restricted-access message with WordPress core's login form while retaining the existing CRM role/capability denial for authenticated unauthorized accounts. Builder: Shashi.
- **Files affected:** Access template, shared admin styles, fast contracts, release metadata, and changelogs.
- **Compatibility notes:** Existing shortcodes, WordPress authentication cookies, login endpoint, roles, capabilities, and post-login request return behavior are preserved. Plugin version advanced from `1.100.7` to `1.100.8`.

- **Area:** Agent delegation and effort selection
- **Change:** Refined Sol orchestration and review verification boundaries, preserved Terra/Luna delegation fallback guidance, and standardized effort selection to Low, Medium, and High. Builder: Shashi.
- **Files affected:** `AGENTS.md`, release metadata, release-alignment tests, `CHANGELOG.md`, and `readme.txt`.
- **Compatibility notes:** Runtime behavior, hooks, routes, capabilities, schema, options, and stored data are unchanged. Plugin version advanced from `1.100.6` to `1.100.7`.

- **Area:** Agent delegation and effort selection
- **Change:** Added project guidance assigning architectural, security-sensitive, ambiguous, and final-review work to Sol; bounded routine implementation to Terra; and low-risk discovery, mechanical edits, tests, and documentation maintenance to Luna. Limited effort selection to Light, Medium, and High with risk-based boundaries. Builder: Shashi.
- **Files affected:** Codex project guide, release-alignment tests, production release references, changelogs, and release metadata.
- **Compatibility notes:** Runtime behavior, hooks, routes, capabilities, schema, options, and stored data are unchanged. Plugin version advanced from `1.100.5` to `1.100.6`.

- **Area:** Users & Access filter layout
- **Change:** Disabled wrapping for the inline directory filter controls and enabled narrow-screen horizontal scrolling. Builder: Shashi.
- **Files affected:** admin style sheet, changelogs, and release metadata.
- **Compatibility notes:** Filtering behavior and request parameters are unchanged. Plugin version advanced from `1.100.4` to `1.100.5`.

- **Area:** Users & Access filter layout
- **Change:** Changed the directory filter container to an inline control group while retaining its dedicated row. Builder: Shashi.
- **Files affected:** admin style sheet, changelogs, and release metadata.
- **Compatibility notes:** Filtering behavior and request parameters are unchanged. Plugin version advanced from `1.100.3` to `1.100.4`.

- **Area:** Users & Access filter layout
- **Change:** Moved the directory filter controls to a full-width row below the directory heading. Builder: Shashi.
- **Files affected:** admin style sheet, changelogs, and release metadata.
- **Compatibility notes:** Filtering behavior and request parameters are unchanged. Plugin version advanced from `1.100.2` to `1.100.3`.

- **Area:** Frontend Users & Access rendering
- **Change:** Replaced the wp-admin-only editable-role helper with the always-available WordPress role registry so `idlm_view=users` renders inside the frontend workspace. Builder: Shashi.
- **Files affected:** user access controller, changelogs, and release metadata.
- **Compatibility notes:** The CRM role allowlist and all user-management behavior remain unchanged. Plugin version advanced from `1.100.1` to `1.100.2`.

- **Area:** Users & Access administration
- **Change:** Added an administrator-only CRM user workspace with managed-role account creation and updates, phone metadata, CRM suspension/restoration, password-reset initiation, search/filtering, and self-lockout/final-administrator protection. CRM suspension is enforced by the representative and quotation policies without deleting WordPress users or altering historical lead ownership. Builder: Shashi.
- **Files affected:** admin user controller/template/styles, bootstrap/access policies, facade registrations, fast contracts, user/access architecture and interface documentation, changelogs, and release metadata.
- **Compatibility notes:** Existing CRM roles, lead ownership, quotation records, menus, AJAX actions, and settings are retained. Added the administrator-only `idlm_create_user` and `idlm_update_user` admin-post interfaces. Plugin version advanced from `1.99.10` to `1.100.0`.

- **Area:** Users & Access workspace experience
- **Change:** Reworked Users & Access into a responsive invite and team-directory interface with labelled inputs, filtering, active/suspended state, and native disclosure-based editing. Added the administrator-only section to the frontend `[idlm_workspace]` navigation. Builder: Shashi.
- **Files affected:** users template and admin styles, shortcode workspace section registration, changelogs, and release metadata.
- **Compatibility notes:** The existing user handlers, roles, access policy, option keys, and lead ownership remain unchanged. Plugin version advanced from `1.100.0` to `1.100.1`.

- **Area:** Controlled-release CI integration root handoff
- **Change:** Exported the disposable integration WordPress root as `IDLM_PERF_WP_ROOT` so the dataset and query-plan subprocesses validate and boot the same `/tmp/wordpress` fixture used by the GitHub Actions matrix. Builder: Shashi.
- **Files affected:** integration bootstrap, release regression contracts, controlled-release documentation, changelogs, and release metadata
- **Compatibility notes:** Production runtime code, hooks, routes, schema, options, and stored values are unchanged. Verified 99 fast contracts and 14 disposable native WordPress/MySQL tests. Plugin version advanced from `1.99.9` to `1.99.10`.

- **Area:** Production scalability Phase 9 staging-candidate promotion gate
- **Change:** Added independent verification that binds the CI-built ZIP, checksum file, manifest, version, clean exact revision, completed matrix, GitHub run, archive file count, required runtime inventory, and one-plugin-directory boundary before staging rehearsal. CI now emits an aggregate report with all operator-controlled staging gates explicitly pending. Builder: Shashi.
- **Files affected:** Phase 9 candidate verifier/contracts, CI release job, controlled-release/development/test/production-plan documentation, changelogs, and release metadata
- **Compatibility notes:** Production hooks, actions, routes, shortcodes, capabilities, nonces, payloads, selectors, schema, options, stored values, and runtime loading remain unchanged. The verifier does not extract or deploy archives, touch CRM data, perform backups, send traffic, or set production approval. Local rehearsal artifacts fail promotion by design. Verified full PHP syntax and 98 fast contracts. Remote CI, tag/release creation, operator backup/restore evidence, staging deployment, production smoke, monitored rollout, and rollback rehearsal remain pending. Plugin version advanced from `1.99.8` to `1.99.9`.

- **Area:** Production scalability Phase 9 controlled-release artifact gate
- **Change:** Added a guarded `.distignore`-driven artifact builder with release-version alignment, required/excluded inventory checks, high-confidence secret-signature rejection, one-plugin-directory ZIP inspection, exact-revision and clean-tree evidence, SHA-256 output, and GitHub Actions attestation after the complete PHP/WordPress matrix. Builder: Shashi.
- **Files affected:** Phase 9 release builder/contracts, distribution and CI policy, controlled-release/development/test/production-plan documentation, changelogs, and release metadata
- **Compatibility notes:** Production hooks, actions, routes, shortcodes, capabilities, nonces, payloads, selectors, schema, options, stored values, and runtime loading remain unchanged. Dirty local artifacts require an explicit override and are permanently labeled rehearsal-only; all manifests retain `production_release_approved: false`. Verified full PHP syntax, 93 fast contracts, and local artifact/checksum inspection. Remote CI, tag/release creation, backup, staging, production rollout, and rollback evidence remain pending. Plugin version advanced from `1.99.7` to `1.99.8`.

- **Area:** Production scalability Phase 8 load and resilience qualification baseline
- **Change:** Added a guarded authenticated HTTP runner for three established read-only CRM AJAX surfaces plus a deterministic dataset, traffic, role, concurrency, failure, recovery, and integrity matrix. The runner generates a short-lived WordPress cookie and nonce, bounds load, disables redirects, retains TLS verification, streams response bodies to anonymous temporary files, and records aggregate-only latency, throughput, size, outcome, and unsigned threshold evidence. Builder: Shashi.
- **Files affected:** Phase 8 performance runner/manifests/result recorder, fast contracts, load/performance/project/production-plan/test documentation, changelogs, and release metadata
- **Compatibility notes:** Production hooks, actions, routes, shortcodes, capabilities, nonces, request/response payloads, schema, options, stored values, and runtime initialization remain unchanged. The runner is dry-run by default, refuses production, calls only existing read actions after explicit confirmation, and excludes URLs, identities, cookies, nonces, request values, response bodies, contacts, and credentials from output. Target-scale, resilience, rollback, and restore qualification remain pending. Verified full PHP syntax, 89 fast contracts, 14 disposable native WordPress/MySQL tests, and one successful local dashboard warm-up plus two measured requests with zero errors; that tiny working-tree smoke is not target evidence. Plugin version advanced from `1.99.6` to `1.99.7`.

## 2026-08-30

- **Area:** Production scalability Phase 7 operational health baseline
- **Change:** Added `IDLM_Operational_Health` and an administrator System Health card for aggregate database/schema, recurring/retention schedule, enabled-credential, upload-storage, cache-backend, Action Scheduler queue, and recent-fault checks. Added initial queue/fault thresholds and critical-health, schema, queue, and credential/third-party runbooks. Builder: Shashi.
- **Files affected:** operational-health class/bootstrap, settings renderer/health template, fast test bootstrap/contracts, operations/architecture/project/feature/production-plan/test documentation, changelogs, and release metadata
- **Compatibility notes:** Existing hooks, actions, routes, shortcodes, capabilities, settings fields, options, schema, stored values, schedules, integrations, and selectors remain unchanged. The report is administrator-only, read-only, and excludes credentials, contacts, payloads, hosts, database names, and paths. Verified full PHP syntax, 86 fast contracts, and 14 disposable native WordPress/MySQL tests. Plugin version advanced from `1.99.5` to `1.99.6`.

- **Area:** Production scalability Phase 6 horizontal deployment preflight
- **Change:** Added `IDLM_Horizontal_Readiness` and an administrator System Health card that evaluates shared database/media/configuration declarations, persistent object cache, Action Scheduler, managed cron, and streamed-export assumptions using aggregate boolean state only. Added the runtime-write inventory and protected deployment qualification guide. Builder: Shashi.
- **Files affected:** horizontal-readiness class/bootstrap, settings renderer/health template, fast test bootstrap/contracts, architecture/project/production-plan/horizontal-deployment/test documentation, changelogs, and release metadata
- **Compatibility notes:** Existing hooks, actions, routes, shortcodes, capabilities, settings fields, options, schema, stored values, exports, uploads, jobs, and selectors remain unchanged. The preflight is read-only and does not provision infrastructure or expose database hosts, paths, salts, credentials, or secret values. Verified full PHP syntax, 84 fast contracts, and 14 disposable native WordPress/MySQL tests. Plugin version advanced from `1.99.4` to `1.99.5`.

- **Area:** Production scalability Phase 5 lead-detail request boundary
- **Change:** Extracted the authenticated lead-detail read and drawer-response implementation into `IDLM_Admin_Lead_Reads`; retained the established `IDLM_Admin` and `IDLM_Admin_Ajax` compatibility methods as delegates. Builder: Shashi.
- **Files affected:** admin AJAX/bootstrap classes, fast test bootstrap/contracts, architecture/project/feature/interface/debt/production-plan documentation, changelogs, and release metadata
- **Compatibility notes:** The `idlm_get_lead_details` action, `idlm_admin_nonce`, CRM capability and object-level visibility checks, request fields, SQL reads, response payload, drawer selectors/markup, quote/workspace URLs, read-state side effect, schema, options, and stored values remain unchanged. The focused class is 727 lines and the remaining AJAX coordinator decreased from 3,807 to 3,097 lines. Verified full PHP syntax, 82 fast contracts, 14 disposable native WordPress/MySQL tests, and the live frontend lead drawer without browser warnings/errors. Plugin version advanced from `1.99.3` to `1.99.4`.

- **Area:** Production scalability Phase 4 lead-list keyset pagination
- **Change:** Added reusable HMAC-signed cursor tokens and default manager lead-queue keyset reads over the established `(COALESCE(assigned_at, created_at), id)` descending tuple. The shared admin/frontend client stores page-entry cursors for sequential next/previous navigation and safely returns to page one after an expired or invalid cursor. Builder: Shashi.
- **Files affected:** cursor support class/bootstrap, lead-list AJAX/client, fast contracts, architecture/project/feature/interface/security/production-plan documentation, changelogs, and release metadata
- **Compatibility notes:** The `idlm_get_leads_table` action, nonce, authorization/visibility rules, existing `page`/`per_page` and filter inputs, sort order, response HTML/page metadata, selectors, database schema, and stored values remain compatible. Custom sorts, pre-sales queue order, direct non-cursor requests, and page-number callers retain `LIMIT/OFFSET`. Verified full PHP/JavaScript syntax, 81 fast contracts, 14 disposable native WordPress/MySQL tests, and live wp-admin/frontend page 1→2 navigation, previous-page history, and custom-sort fallback with no console warnings/errors. Plugin version advanced from `1.99.2` to `1.99.3`.

- **Area:** Production scalability Phase 3 dashboard read model and cache
- **Change:** Added an authorization-scoped dashboard read model with minute-aligned reporting ranges, role/owner/settings/schema cache context, short-lived WordPress object-cache storage, database-transient fallback, stale-while-rebuild locking, and generation-based invalidation across lead lifecycle, assignment/handoff, deletion/reset, quotation value, relevant settings, and retention paths. Builder: Shashi.
- **Files affected:** read-model cache/dashboard classes, dashboard compatibility entry point, lifecycle/routing/deletion/quotation/reset invalidation paths, bootstrap, fast test doubles/contracts, architecture/project/production-plan/test documentation, changelogs, and release metadata
- **Compatibility notes:** The established `IDLM_Dashboard::get_stats()` method, `idlm_get_dashboard_stats` AJAX action, nonce, authorization policy, response payload, dashboard selectors, database schema, and stored CRM values remain unchanged. Restricted representatives use owner-specific cache entries; manager/global entries cannot serve restricted data. Cache failure performs the canonical database read. Verified 79 fast contracts, 14 disposable native WordPress/MySQL tests, and PHP syntax. Plugin version advanced from `1.99.1` to `1.99.2`.

- **Area:** Production scalability Phase 2 query evidence
- **Change:** Added a ten-shape SQL manifest and read-only CLI that captures normalized EXPLAIN steps, plugin index/cardinality inventory, full-scan/filesort/temporary summaries, build/dataset/date metadata, and explicit privacy exclusions. Builder: Shashi.
- **Files affected:** `tests/performance/`, fast/native test suites, performance/production-plan documentation, changelogs, plugin/test/readme release metadata
- **Compatibility notes:** Runtime handlers, public callbacks, SQL behavior, schema version, tables, indexes, options, payloads, and selectors remain unchanged. Local evidence used only 117 leads and therefore does not approve an index migration. Verified 75 fast contracts, 14 disposable native WordPress/MySQL tests, command help, a live local optimizer capture, and full PHP syntax. Plugin version advanced from `1.99.0` to `1.99.1`.

- **Area:** Production scalability Phase 1 security boundaries
- **Change:** Added a production security policy with fail-closed MCP enablement, emergency disable, per-tool grants, bounded payload/batch/query budgets, key rotation/revocation fingerprints, aggregate access attribution, trusted-proxy-aware client identity, and field-specific lead attachment validation/ownership metadata. Builder: Shashi.
- **Files affected:** production security policy/bootstrap, MCP core/REST transport, lead upload handler/client, fast contracts, production/security/project/debt documentation, and release metadata
- **Compatibility notes:** Existing REST routes, AJAX action, nonces, response data fields, attachment URL storage, and WordPress media validation remain. Production MCP now requires explicit enablement and grants; untrusted forwarded IP headers are ignored; uploads now require a field purpose and allowed extension/MIME/size. Verified 73 fast contracts, 13 disposable native WordPress/MySQL tests, and full PHP syntax. Plugin version advanced from `1.98.4` to `1.99.0`.

- **Area:** Production scalability Phase 0 deterministic dataset tooling
- **Change:** Added PII-free small, expected, and provisional-maximum dataset profiles for leads, activities, tasks, touchpoints, retained CAPI/audience/telemetry logs, and complete quotation revision/section/item graphs; added a dry-run manifest plus guarded batch seeding, tagged verification, partial-run recovery, and scoped reset. Builder: Shashi.
- **Files affected:** `tests/performance/`, fast contracts, performance/production-plan/test documentation, public and engineering changelogs, plugin/readme release metadata
- **Compatibility notes:** Production hooks, actions, routes, shortcodes, capabilities, selectors, schema, and runtime initialization remain unchanged. The CLI refuses production environments, requires exact mutation confirmation, uses only reserved synthetic identifiers and `.invalid` email addresses, and removes only the selected run's linked/tagged rows. Verified PHP syntax, command help/dry-run JSON, 70 fast contracts, and 13 disposable native WordPress/MySQL tests including real seed/verify/reset. Plugin version advanced from `1.98.3` to `1.98.4`.

- **Area:** Production scalability Phase 0 baseline tooling
- **Change:** Added an aggregate-only CLI capture for runtime/schema versions, plugin table/index size estimates, autoloaded option size, WP-Cron state, and Action Scheduler backlog; added a 12-scenario critical benchmark manifest and structured target/result/query-plan/capacity/ownership evidence template. Builder: Shashi.
- **Files affected:** `tests/performance/`, performance and production/scalability documentation, test/project documentation, public and engineering changelogs, plugin/test/readme release metadata
- **Compatibility notes:** Production hooks, actions, routes, shortcodes, capabilities, selectors, options, schema, stored values, and runtime behavior remain unchanged. The capture exports aggregates only and excludes CRM rows, contacts, payloads, responses, credentials, URLs, filesystem paths, and database names. Verified all PHP syntax, 67 fast contracts, 12 disposable native WordPress/MySQL tests, command help, and a live aggregate capture. Plugin version advanced from `1.98.2` to `1.98.3`.

- **Area:** Production readiness and scalability planning
- **Change:** Added a staged, measurement-first remediation plan covering release operations, security/configuration, performance baselines, query/index work, read models/caching, cursor pagination, resumable bulk jobs, code hotspot extraction, multi-node infrastructure, observability, resilience testing, rollout, rollback, and definition of done. Builder: Shashi.
- **Files affected:** production/scalability plan, technical-debt/project/architecture/development documentation, public and engineering changelogs, plugin/test/readme release metadata
- **Compatibility notes:** Documentation and release metadata only; runtime hooks, actions, routes, shortcodes, capabilities, selectors, options, schema, stored values, and integration behavior remain unchanged. Plugin version advanced from `1.98.1` to `1.98.2`.

- **Area:** Admin facade refactor Phase 5 and release verification
- **Change:** Completed the automated and live verification matrix for the 432-line `IDLM_Admin` compatibility facade and closed the evidence-backed technical-debt item. Builder: Shashi.
- **Files affected:** test/release metadata, admin refactor plan and verification evidence, architecture/project/feature/development/debt documentation, public and engineering changelogs
- **Compatibility notes:** All 75 public methods, hooks, AJAX actions, shortcodes, menu slugs/callbacks, asset handles/contexts, capabilities, selectors, options, schema, and stored values remain unchanged. Full PHP lint, 67 fast contracts, and 12 disposable native WordPress/MySQL tests pass. Live verification covered six wp-admin pages, eight frontend workspace routes, administrator and unauthenticated output, desktop/tablet/mobile layouts, dashboard/settings/sidebar/Leads/Pipeline interactions, expected assets, and 14-route console/network diagnostics with no warnings, errors, or failed requests. Plugin version advanced from `1.98.0` to `1.98.1`.

- **Area:** Admin facade refactor Phase 4
- **Change:** Extracted shared asset registration/context enqueueing into `IDLM_Admin_Assets`, frontend shortcode/workspace orchestration into `IDLM_Admin_Shortcodes`, account handling into `IDLM_Admin_Account_Controller`, display formatting into `IDLM_Admin_Presenter`, and WhatsApp read-state/reminder counts into `IDLM_Admin_Lead_Notifications`; credential rotation and representative authorization helpers now delegate to the settings controller and access policy. Retained every established `IDLM_Admin` public method as a compatibility entry point. Builder: Shashi.
- **Files affected:** admin facade and bootstrap, focused shell/support classes, settings/access boundaries, fast contracts, architecture/project/feature/debt/development/refactor documentation, and release metadata
- **Compatibility notes:** All 75 public methods, hooks, shortcode registrations/callbacks, asset handles/dependencies/contexts, quotation shared-asset calls, capability/object access, account/credential handling, query routing, selectors, output, options, schema, and stored values remain unchanged. `class-admin.php` decreased from 949 to 432 lines. Verified 67 fast contracts, 12 disposable native WordPress/MySQL tests, and full PHP syntax checks. Plugin version advanced from `1.97.2` to `1.98.0`.

- **Area:** Admin facade refactor Phase 3, settings rendering boundary
- **Change:** Extracted settings context construction, environment/database reads, and presentation into `IDLM_Admin_Settings_Renderer` plus ten guarded tab/section templates; reduced `IDLM_Admin::render_settings_page()` to a compatibility delegate. Builder: Shashi.
- **Files affected:** admin facade and bootstrap, settings renderer/templates, tests, architecture/project/feature/debt/refactor documentation, and release metadata
- **Compatibility notes:** Existing settings fields, tabs, selectors, form/request fields, option keys, callback, notice, capability/nonce order, admin/frontend output, credential reads, schema, and stored values remain unchanged; protected credential values stay outside the shared template context. `class-admin.php` decreased from 2,950 to 949 lines. Verified 66 fast contracts, 12 disposable native WordPress/MySQL tests without warnings, and full PHP syntax checks. Plugin version advanced from `1.97.1` to `1.97.2`.

- **Area:** Admin facade refactor Phase 3, settings request boundary
- **Change:** Extracted settings POST detection, capability/nonce validation, sanitization, option and secret persistence, grouped-routing synchronization, and save-result coordination into `IDLM_Admin_Settings_Controller`; retained the established `IDLM_Admin::render_settings_page()` callback and success notice. Builder: Shashi.
- **Files affected:** admin facade and bootstrap, settings controller, tests, architecture/project/feature/debt/refactor documentation, and release metadata
- **Compatibility notes:** Existing settings fields, option keys, form action, nonce, capability, validation defaults, success notice, routing mirrors, secret-store behavior, schema, and stored values remain unchanged. `class-admin.php` decreased from 3,234 to 2,950 lines. Verified 66 fast contracts, 12 disposable native WordPress/MySQL tests, and full PHP syntax checks. Plugin version advanced from `1.97.0` to `1.97.1`.

- **Area:** Admin facade refactor Phase 2
- **Change:** Extracted lead-list context/query preparation into `IDLM_Admin_Leads_Renderer` and moved the leads workspace plus shared drawer/modal/task overlays into guarded templates with explicit contexts. Retained both established `IDLM_Admin` methods as compatibility delegates. Builder: Shashi.
- **Files affected:** admin facade and bootstrap, leads renderer, leads/common-overlay templates, tests, architecture/project/feature/debt/refactor documentation, and release metadata
- **Compatibility notes:** Capabilities, visibility scoping, callbacks, shortcodes, routing/import/export fields, selectors, JavaScript entry points, options, schema, and stored values remain unchanged. `class-admin.php` decreased from 4,537 to 3,234 lines. Verified 66 fast contracts, 12 disposable native WordPress/MySQL tests, full PHP syntax checks, and live Leads filtering/routing/import/export modal interactions with all shared overlay selectors present and no console errors. Plugin version advanced from `1.96.0` to `1.97.0`.

- **Area:** Admin facade refactor Phase 1
- **Change:** Extracted dashboard, Kanban, CAPI audit log, and audience sync log context/rendering into `IDLM_Admin_Dashboard_Renderer`, `IDLM_Admin_Kanban_Renderer`, `IDLM_Admin_Logs_Renderer`, and guarded nested templates. Retained the established `IDLM_Admin` public callbacks as one-line delegates and kept dynamic dashboard data in `IDLM_Dashboard`. Builder: Shashi.
- **Files affected:** admin facade and bootstrap, guarded view resolver, three renderer classes, four admin templates, tests, architecture/project/feature/debt/refactor documentation, and release metadata
- **Compatibility notes:** Capabilities, nonces, bounded log queries, callbacks, shortcodes, selectors, JavaScript globals/entry points, module assets, schema, options, and stored values remain unchanged. `class-admin.php` decreased from 5,346 to 4,537 lines. Verified 65 fast contracts, 12 disposable native WordPress/MySQL tests, and live dashboard/Kanban/CAPI/audience wp-admin rendering without browser console errors. Plugin version advanced from `1.95.2` to `1.96.0`.

- **Area:** Admin facade refactor Phase 0
- **Change:** Added extraction-safe characterization coverage for the 75-method/5,346-line admin baseline, registrations, menus, asset handles and contexts, critical selectors, capability/object access, native page and shortcode output, and settings capability/nonce/sanitization/mirroring/secret persistence. Builder: Shashi.
- **Files affected:** `tests/admin-refactor-contracts.php`, behavioral/native test support and runners, admin refactor plan, test/release documentation, and release metadata
- **Compatibility notes:** Test and documentation changes only; production hooks, shortcodes, actions, selectors, options, assets, rendering, schema, and stored behavior are unchanged. Verified 64 fast tests and 12 disposable native WordPress/MySQL tests. Plugin version advanced from `1.95.1` to `1.95.2`.

- **Area:** Admin facade refactor planning
- **Change:** Documented the current 5,346-line baseline, target class/template structure, compatibility contracts, phased extraction order, test gates, security checks, manual matrix, stop conditions, and definition of done for the next implementation pass. Reopened the low-priority admin-facade debt until evidence-based completion. Builder: Shashi.
- **Files affected:** `docs/ADMIN-REFACTOR-PLAN.md`, technical debt/project map, plugin/test/readme/changelog release metadata
- **Compatibility notes:** Documentation and release metadata only; production hooks, actions, shortcodes, rendering, schema, options, assets, and stored behavior are unchanged. Plugin version advanced from `1.95.0` to `1.95.1`.

- **Area:** Low-priority maintainability and release hygiene
- **Change:** Added guarded workspace/account/access templates and asset-based workspace boot behavior; introduced grouped non-secret routing settings with legacy mirrors; relocated design notes; removed Local-specific MCP paths and root runtime artifacts; added distribution exclusions; and archived full public history under `docs/legacy/`. Builder: Shashi.
- **Files affected:** template/settings infrastructure, admin workspace/account rendering, routing consumers and save handlers, MCP CLI entry, assets, tests, release/docs layout, and release metadata
- **Compatibility notes:** Existing shortcodes, capabilities, selectors, standalone routing option keys, settings forms, MCP JSON-RPC behavior, and stored values remain compatible. Plugin version advanced from `1.94.1` to `1.95.0`.

## 2026-08-29

- **Area:** Frontend quotation workspace regression
- **Change:** Restored the missing renderer-side date-filter normalizer introduced by the quotation class split, normalized empty pagination output for PHP 8.x, and added direct/native route regression coverage. Builder: Shashi.
- **Files affected:** `includes/class-quotation-renderer.php`, `tests/`, plugin/readme/changelog release metadata
- **Compatibility notes:** The existing `idlm_view=quotations` URL, shortcode, filters, capabilities, query fields, and rendered markup remain unchanged. Plugin version advanced from `1.94.0` to `1.94.1`.

- **Area:** Medium-priority security, integrity, state, output, and test debt
- **Change:** Added encrypted/environment-backed scoped credentials with header-only authentication; shared workflow/access policies; atomic routing state; transactional deletion and CLI orphan audit/repair; payload redaction and scheduled retention; semantic server-rendered quotation printing; and PHP/WordPress matrix CI. Builder: Shashi.
- **Files affected:** new secret/state/access/routing/retention/deletion/integrity classes, integrations/admin/dashboard/lifecycle/quotation modules, schema/bootstrap, tests/CI, engineering documentation, and release metadata
- **Compatibility notes:** REST route names and response contracts remain unchanged, but legacy shared/query credentials are intentionally removed and clients must use scoped headers or Google Basic Auth. Core schema advanced from `1.1.0` to `1.2.0`; plugin version advanced from `1.93.0` to `1.94.0`.

- **Area:** High-priority module boundaries
- **Change:** Split authenticated admin AJAX handlers, quotation controllers/renderers, and lead lifecycle persistence into focused classes while retaining the established facades and action registrations. Routed structured early-close writes through the lifecycle service. Builder: Shashi.
- **Files affected:** `includes/class-admin.php`, `includes/class-admin-ajax.php`, `includes/class-quotations.php`, `includes/class-quotation-controller.php`, `includes/class-quotation-renderer.php`, `includes/class-db.php`, `includes/class-lead-lifecycle-service.php`, plugin/test bootstraps, debt and release documentation
- **Compatibility notes:** Public methods, hooks, actions, nonces, capabilities, request/response fields, schema, stored values, and UI selectors remain compatible. Plugin version advanced from `1.92.5` to `1.93.0`.

- **Area:** Quotation authorization and lead read boundary
- **Change:** Added `IDLM_Quotation_Policy` for quotation capabilities, unrestricted manager detection, and object-level lead access. Added `IDLM_Quotation_Lead_Read_Model` for scoped lead existence, server-side search, initial editor choices, and selected-lead prefilling. Kept the quotation facade's handlers, actions, nonces, response formats, and authorization delegates unchanged. Builder: Shashi.
- **Files affected:** `includes/class-quotation-policy.php`, `includes/class-quotation-lead-read-model.php`, `includes/class-quotations.php`, `interior-design-lead-manager.php`, `tests/`, `AGENTS.md`, architecture/project/feature/security/debt documentation, `CHANGELOG.md`, `readme.txt`
- **Compatibility notes:** Hooks, AJAX/admin-post actions, nonces, capabilities, request fields, response payloads/statuses, lead fields/order/limits, database schema, and facade entry points remain unchanged. Administrators and assignment managers retain unrestricted existing-lead access; representatives remain limited to their assigned leads. Plugin version advanced from `1.92.4` to `1.92.5`.

- **Area:** Quotation catalogue and list-query boundary
- **Change:** Moved catalogue term/item CRUD, bulk/category actions, CSV import persistence helpers, keyset export chunks, catalogue search/pagination, and scoped quotation-list count/page queries into `IDLM_Quotation_Repository`. Kept capabilities, nonces, request/file/CSV validation, transaction coordination, CSV streaming, redirects, rendering, and public handlers in `IDLM_Quotations`. Builder: Shashi.
- **Files affected:** `includes/class-quotation-repository.php`, `includes/class-quotations.php`, `tests/`, `AGENTS.md`, architecture/project/feature/flow/debt documentation, `CHANGELOG.md`, `readme.txt`, `interior-design-lead-manager.php`
- **Compatibility notes:** Hooks, actions, capabilities, nonces, request fields, database schema, catalogue snapshots, import limits, export columns/order, transaction boundaries, filters, pagination sizes, redirect messages, and UI output remain unchanged. Plugin version advanced from `1.92.3` to `1.92.4` for the production structural change.

- **Area:** Quotation aggregate persistence boundary
- **Change:** Moved save-request idempotency reads, lead snapshot reads, quote/revision writes, locked revision sequencing, section/item replacement and copying, current/superseded revision updates, and the lead quote-value projection into `IDLM_Quotation_Repository`. Kept request security, normalization, redirects, and transaction start/commit/rollback coordination in `IDLM_Quotations`. Builder: Shashi.
- **Files affected:** `includes/class-quotation-repository.php`, `includes/class-quotations.php`, `tests/`, `AGENTS.md`, architecture/project/feature/debt documentation, `CHANGELOG.md`, `readme.txt`, `interior-design-lead-manager.php`
- **Compatibility notes:** Hooks, actions, capabilities, nonces, request fields, database schema, transaction boundaries, numbering, revision graph, totals, stored values, redirects, and customer-facing behavior remain unchanged. Plugin version advanced from `1.92.2` to `1.92.3` for the production structural change.

- **Area:** Quotation repository and schema boundaries
- **Change:** Extracted ten-table installation, the independent schema version, legacy quote conversion, and revision snapshot/section backfills into `IDLM_Quotation_Schema`. Added `IDLM_Quotation_Repository` for prefixed table mapping, atomic yearly numbering, quote/revision aggregate reads, history insertion, lead-scoped deletion, and reset persistence. Kept `IDLM_Quotations` methods as compatibility delegates and retained save transaction orchestration in the facade. Builder: Shashi.
- **Files affected:** `includes/class-quotation-repository.php`, `includes/class-quotation-schema.php`, `includes/class-quotations.php`, `interior-design-lead-manager.php`, `tests/`, `AGENTS.md`, architecture/data/flow/development/debt documentation, `CHANGELOG.md`, `readme.txt`
- **Compatibility notes:** Database DDL, table/column/index names, quotation schema version, migration markers, numbering format and locking, transaction boundaries, totals, public methods, hooks, actions, capabilities, request fields, and stored behavior remain unchanged. Plugin version advanced from `1.92.1` to `1.92.2` for the production structural change.

- **Area:** Quotation calculation boundary
- **Change:** Extracted deterministic item/section normalization, decimal handling, and totals calculation into `IDLM_Quotation_Calculator`; retained the existing `IDLM_Quotations` methods as delegates so save/autosave behavior and internal callers remain compatible. Added repeatability and delegate-parity regression coverage. Builder: Shashi.
- **Files affected:** `includes/class-quotation-calculator.php`, `includes/class-quotations.php`, `interior-design-lead-manager.php`, `tests/`, `AGENTS.md`, quotation architecture/flow/debt documentation, `CHANGELOG.md`, `readme.txt`
- **Compatibility notes:** Hooks, actions, request fields, persistence schema, rounding order, totals keys, save/autosave behavior, and stored values are unchanged. Plugin version advanced from `1.92.0` to `1.92.1` for the production structural change.

- **Area:** Native WordPress/MySQL regression baseline
- **Change:** Added a dependency-free, opt-in integration runner that installs WordPress into a uniquely named disposable MySQL database and exercises real schema installation/upgrades, REST permission dispatch, lead lifecycle persistence, scoped MCP access, concurrent quotation numbering, quotation persistence/totals, and uniqueness constraints. Builder: Shashi.
- **Files affected:** `tests/integration/`, `tests/README.md`, `docs/ARCHITECTURE.md`, `docs/PROJECT-MAP.md`, `docs/DEVELOPMENT.md`, `docs/TECH-DEBT.md`
- **Compatibility notes:** Test and documentation changes only. Production PHP, JavaScript, database schema, hooks, routes, actions, and stored behavior are unchanged; version remains `1.92.0` under the project rule for test-only work.

## 2026-08-28

- **Area:** REST and MCP trust boundary
- **Change:** Split webhook, MCP, and Google feed credentials into scoped values with administrator rotation, moved authentication into route-specific permission callbacks, added constant-time comparisons and per-IP rate limits, and retained a 30-day legacy migration window. Restricted custom MCP SQL to allowlisted bounded SELECT queries and capped MCP payload, batch, query-result, and activity-history sizes. Added regression coverage for the new security contracts. Builder: Shashi.
- **Files affected:** `includes/class-integrations.php`, `includes/class-mcp-core.php`, `includes/class-admin.php`, `tests/`, `docs/ARCHITECTURE.md`, `docs/DATA-MODEL.md`, `docs/FLOWS.md`, `docs/INTERFACES.md`, `docs/SECURITY.md`, `docs/TECH-DEBT.md`, `interior-design-lead-manager.php`, `CHANGELOG.md`, `readme.txt`
- **Compatibility notes:** REST routes, methods, AJAX actions, hooks, capabilities, database tables, and response contracts remain unchanged. Existing webhook header clients keep the same value; legacy shared/query credential use receives a 30-day grace window unless an administrator rotates that scope earlier. Production authentication behavior changed, so the plugin version advanced from `1.91.3` to `1.92.0`.

- **Area:** Production-readiness and regression-safety baseline
- **Change:** Added a dependency-free behavioral test harness covering lead lifecycle transitions and side effects, representative AJAX authorization, REST/MCP authentication, quotation calculations and autosave authorization, and core/quotation schema migration contracts. Builder: Shashi.
- **Files affected:** `tests/bootstrap.php`, `tests/run.php`, `tests/README.md`
- **Compatibility notes:** Tests use isolated WordPress and database doubles and do not connect to or modify the site database.

- **Area:** Bulk lead authorization
- **Change:** Enforced object-level access for every lead targeted by a bulk lifecycle action, closing a path where a restricted representative with a valid nonce could mutate a guessed lead ID outside their configured visibility. Builder: Shashi.
- **Files affected:** `includes/class-admin.php`, `interior-design-lead-manager.php`, `CHANGELOG.md`, `readme.txt`
- **Compatibility notes:** Existing AJAX action names, request fields, capabilities, lifecycle behavior, hooks, and database semantics are unchanged. Authorized users retain the same behavior; plugin version advanced from `1.91.2` to `1.91.3` because production authorization behavior changed.

- **Area:** Feature discovery
- **Change:** Added a business-feature-to-implementation blast-radius map and linked it from the primary Codex guide. Builder: Shashi.
- **Files affected:** `docs/FEATURE-MAP.md`, `AGENTS.md`
- **Compatibility notes:** Documentation and release metadata only; no production behavior or public interface changed.

- **Area:** Repository documentation
- **Change:** Added a source-verified Codex guide and focused architecture, project map, data model, flow, interface, security, development, and technical-debt references. Recorded the current legacy boundaries and a compatibility-preserving future structure. Builder: Shashi.
- **Files affected:** `AGENTS.md`, `docs/*.md`
- **Compatibility notes:** Documentation and release metadata only; no production behavior, public interface, schema, or stored data changed.
