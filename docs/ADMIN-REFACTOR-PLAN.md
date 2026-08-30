# Admin Facade Refactor Plan

Author / Builder: Shashi  
Status: Complete in version 1.98.1  
Prepared: 2026-08-30  
Baseline plugin version: 1.95.1

## Objective

Reduce `includes/class-admin.php` from 5,346 lines to a thin compatibility facade of approximately 500–800 lines. Move rendering, request coordination, asset handling, and presentation helpers into focused classes and guarded templates without changing public behavior.

This is a structural refactor, not a redesign. Existing WordPress hooks, shortcodes, AJAX actions, capabilities, nonces, option names, URLs, JavaScript globals, DOM IDs/classes, output fields, and stored values are compatibility contracts.

## Current baseline

`IDLM_Admin` currently contains 75 public methods. Thirty-six methods are already one-line delegates to `IDLM_Admin_Ajax`; the remaining file combines hook registration, roles/access helpers, WhatsApp read state, reminder calculations, asset registration, shortcode orchestration, account/settings request handling, menus, database reads, and large views.

Largest methods at the planning baseline:

| Method | Approximate lines | Main responsibility |
|---|---:|---|
| `render_settings_page()` | 2,288 | Settings POST handling, context loading, and all settings sections |
| `render_leads_list_page()` | 1,159 | Lead metrics, filters, table shell, modals, routing UI, imports, and exports |
| `render_dashboard_page()` | 350 | Dashboard context and markup |
| `render_kanban_page()` | 259 | Pipeline context and markup |
| `render_common_overlays()` | 158 | Shared lead/task/stage modals and sidebar markup |
| CAPI/Audience log renderers | 228 combined | Log queries, filters, and tables |

Existing extracted boundaries must be reused:

- `IDLM_Admin_Ajax`: authenticated AJAX transport and mutation handlers.
- `IDLM_Lead_Access_Policy`: lead visibility and object access.
- `IDLM_Settings`: grouped routing settings with standalone-key compatibility.
- `IDLM_View`: guarded plugin template rendering with a single explicit context array.
- `IDLM_Dashboard`: dashboard aggregate read model.
- Module assets: `dashboard`, `kanban`, `workspace`, `quotations`, and shared admin CSS/JS.

## Target structure

```text
includes/
  class-admin.php                    hook registration and public compatibility delegates
  class-admin-assets.php             asset registration and context-aware enqueueing
  class-admin-shortcodes.php         shortcode and workspace orchestration
  class-admin-presenter.php          formatting and display-only helper methods
  class-admin-dashboard-renderer.php dashboard context + template delegate
  class-admin-leads-renderer.php     lead-list context + template delegate
  class-admin-kanban-renderer.php    pipeline context + template delegate
  class-admin-logs-renderer.php      CAPI and audience log context + templates
  class-admin-settings-controller.php settings POST validation/persistence/redirect
  class-admin-settings-renderer.php  settings context construction + section templates

templates/admin/
  dashboard.php
  leads.php
  common-overlays.php
  kanban.php
  logs/capi.php
  logs/audience.php
  settings/shell.php
  settings/*.php
```

Names may be consolidated where a class would otherwise be trivial, but no replacement class should become another multi-thousand-line facade. Templates should normally remain below 500 lines; large settings sections should be separate files.

## Compatibility rules

1. Keep every existing `IDLM_Admin` public method callable. Extracted methods become one-line delegates until a separately approved breaking release.
2. Preserve all `add_action()`, `add_shortcode()`, menu slugs, page callbacks, AJAX action names, and priorities.
3. Preserve capability, nonce, and object-access checks at the same or earlier boundary.
4. Preserve shortcode return semantics versus wp-admin echo semantics and maintain output buffering behavior.
5. Preserve existing DOM IDs, classes, `data-*` attributes, inline event entry points, localized script objects, request fields, and query parameters.
6. Templates receive explicit context arrays and escape by output context. Do not use `extract()` or allow arbitrary template paths.
7. Keep database reads in existing read models where available. Do not introduce lifecycle writes or direct unprepared SQL during this refactor.
8. Keep standalone routing option keys compatible through `IDLM_Settings`; secrets remain in `IDLM_Secret_Store`.
9. Do not change visual design, responsive breakpoints, workflows, state labels, or business rules in the same pass.
10. Each independently completed release increments the plugin version and updates public and engineering changelogs with Shashi attribution.

## Phase 0 — Strengthen the baseline

Purpose: make markup movement observable before changing ownership.

Work:

- Add fast contracts proving every `IDLM_Admin` public method remains available.
- Add selector contracts for dashboard, leads, overlays, Kanban, log pages, settings tabs, workspace navigation, and account output.
- Add capability matrix coverage for administrator, assignment manager, representative/pre-sales, unauthorized user, and object-owner cases.
- Add settings-save coverage for nonce failure, capability failure, sanitization, exact option writes, grouped settings mirroring, and secret handling.
- Add asset coverage for wp-admin pages and each shortcode/workspace context.
- Extend the native suite to render each admin/frontend page and assert critical selectors without snapshotting entire fragile HTML documents.
- Capture the current method count, line count, hooks, shortcode registrations, asset handles, and templates as architectural contracts.

Exit criteria:

- Existing 57 fast tests and 10 native tests remain green.
- New characterization tests pass against the pre-extraction implementation.
- No production behavior changes.

Completion evidence (version 1.95.2): 64 fast contracts and 12 disposable native WordPress/MySQL tests pass, including the full facade surface, registrations, asset contexts, selector families, capability/object-access matrix, native page/shortcode rendering, and settings-save security/persistence characterization. Production behavior remains unchanged.

## Phase 1 — Prove the renderer pattern on smaller pages

Purpose: validate context-builder and template conventions before moving the two largest views.

Work:

- Add `IDLM_Admin_Logs_Renderer` for CAPI and audience log queries/context.
- Add guarded CAPI and audience templates.
- Add `IDLM_Admin_Dashboard_Renderer` using `IDLM_Dashboard` for data and a dashboard template for markup.
- Add `IDLM_Admin_Kanban_Renderer` and Kanban template.
- Convert the four original `IDLM_Admin` methods to delegates.
- Move static presentation styles to existing module CSS when selector-safe; do not redesign.

Exit criteria:

- Admin menu and workspace callbacks render identical critical selectors and filters.
- Date/query filters remain sanitized and prepared.
- Dashboard and Kanban JavaScript initialize without console errors.
- `IDLM_Admin` is reduced by roughly 700–900 lines.

Completion evidence (version 1.96.0): the dashboard, Kanban, CAPI log, and audience log callbacks are compatibility delegates to three focused renderers and four guarded templates. Capabilities, nonces, bounded queries, selectors, JavaScript entry points, and shortcode/admin output contracts remain covered. `class-admin.php` decreased by 809 lines to 4,537 lines; 65 fast contracts and 12 disposable native WordPress/MySQL tests pass. Live wp-admin smoke checks rendered all four extracted pages with their critical controls and no browser console errors.

## Phase 2 — Extract Leads and common overlays

Purpose: isolate the central CRM workspace without changing the JavaScript contract.

Work:

- Add `IDLM_Admin_Leads_Renderer` with a dedicated context builder.
- Separate data preparation from `templates/admin/leads.php`.
- Move `render_common_overlays()` markup to `templates/admin/common-overlays.php` with explicit currency, stages, lost reasons, roles, and capability context.
- Reuse access/reminder/presentation services instead of duplicating calculations.
- Preserve table headers, filter values, modal IDs, bulk controls, routing controls, upload/import controls, and drawer entry points exactly.
- Keep `render_leads_list_page()` and `render_common_overlays()` as delegates.

Exit criteria:

- Lead list, filtering, imports, exports, bulk actions, routing settings, drawers, tasks, uploads, Won/Lost/Quote Shared modals, and responsive behavior smoke-test successfully.
- Representative visibility and object access remain unchanged.
- No JavaScript selector or localized-data changes.
- `IDLM_Admin` is reduced by roughly another 1,200–1,400 lines.

Completion evidence (version 1.97.0): `render_leads_list_page()` and `render_common_overlays()` are compatibility delegates to `IDLM_Admin_Leads_Renderer`; data preparation is isolated from guarded leads and common-overlay templates with explicit contexts. Lead filters, table headers, routing/import/export controls, drawers, tasks, modal IDs, visibility scoping, shortcode behavior, and JavaScript selectors remain covered. `class-admin.php` decreased by 1,303 lines to 3,234 lines; 66 fast contracts, 12 disposable native WordPress/MySQL tests, full PHP syntax checks, and a live Leads-page interaction smoke check without console errors pass.

## Phase 3 — Split settings processing and rendering

Purpose: remove the largest mixed-responsibility method.

Work order:

1. Move POST detection, nonce/capability validation, sanitization, option persistence, secret persistence, grouped-settings synchronization, and success/error coordination into `IDLM_Admin_Settings_Controller`.
2. Preserve the existing form fields and option keys; use a redirect-after-post only if regression coverage proves current notices and return URLs remain compatible.
3. Build one explicit settings context in `IDLM_Admin_Settings_Renderer`.
4. Split presentation by established tabs/sections, such as general CRM, routing/handoff, stages/vocabularies, Meta, Google, LinkedIn, audiences, Interakt/WhatsApp, retention/telemetry, and diagnostics/reset.
5. Keep `IDLM_Admin::render_settings_page()` as the public delegate.

Security checklist:

- Nonce before processing posted settings.
- `manage_options` or existing narrower capability at every mutation boundary.
- Type-specific sanitization and allowed-value validation.
- No secrets localized to JavaScript, logs, diagnostics, template context, or URLs.
- Existing encrypted credential rotation remains POST-only and nonce-protected.
- Destructive reset/diagnostic actions remain separate authenticated handlers.

Completion evidence (version 1.97.2): `IDLM_Admin_Settings_Controller` owns authenticated settings POST persistence, while `IDLM_Admin_Settings_Renderer` builds the explicit presentation context and delegates to ten guarded settings templates. Protected credential values remain outside the shared template context, and the established callback, fields, tabs, selectors, notices, option keys, credential handling, and admin/frontend output remain covered. `class-admin.php` decreased from 3,234 lines at the Phase 3 start to 949 lines; 66 fast contracts, 12 disposable native WordPress/MySQL tests without warnings, and full PHP syntax checks pass.

Exit criteria:

- Exact settings round trips pass for defaults, populated values, malformed values, and empty arrays.
- Routing standalone keys and grouped values remain synchronized.
- Secrets remain encrypted/environment-managed.
- All tabs render in wp-admin and the frontend workspace.
- `IDLM_Admin` is reduced by roughly 2,200–2,400 lines.

## Phase 4 — Extract shell responsibilities

Purpose: leave the facade with registration and compatibility only.

Work:

- Move asset registration/enqueue decisions to `IDLM_Admin_Assets` while keeping `IDLM_Admin::register_core_assets()`, `enqueue_assets()`, and `enqueue_frontend_assets()` delegates because quotations call the public asset method.
- Move shortcode and composite workspace orchestration to `IDLM_Admin_Shortcodes` while preserving shortcode callbacks and query routing.
- Move display-only formatting, activity details, qualification labels, reminders, pending-call counts, and WhatsApp read-state presentation helpers into appropriately focused support/presenter classes.
- Keep authorization decisions in `IDLM_Lead_Access_Policy` or dedicated policy code, not renderers.
- Keep menu slugs and callbacks stable; menu registration may remain in the facade if extraction adds no meaningful boundary.

Completion evidence (version 1.98.0): `IDLM_Admin_Assets` owns shared registration and context-aware enqueueing, `IDLM_Admin_Shortcodes` owns the four frontend shortcode/workspace flows, `IDLM_Admin_Account_Controller` owns account context and authenticated updates, `IDLM_Admin_Presenter` owns display-only formatting, and `IDLM_Admin_Lead_Notifications` owns WhatsApp read-state and reminder counts. Credential rotation delegates to the settings controller and representative authorization helpers delegate to `IDLM_Lead_Access_Policy`. All 75 public facade methods, registrations, asset contracts, quotation compatibility calls, access behavior, selectors, and output remain covered. `class-admin.php` decreased from 949 to 432 lines; 67 fast contracts, 12 disposable native WordPress/MySQL tests, and full PHP syntax checks pass.

Exit criteria:

- `class-admin.php` contains approximately 500–800 lines.
- Its remaining methods are hook/menu registration or public delegates.
- No extracted class exceeds a reasonable single-responsibility size.
- Quotations still register/enqueue shared assets through the compatibility API.

## Phase 5 — Final verification and release

Automated verification:

```bash
find . -type f -name '*.php' -print0 | xargs -0 -n1 php -l
php tests/run.php
IDLM_TEST_DB_USER=... IDLM_TEST_DB_PASSWORD=... IDLM_TEST_DB_HOST=... php tests/integration/run.php
```

Manual matrix:

- wp-admin: Dashboard, Leads, Pipeline, CAPI logs, Audience logs, Settings.
- Frontend: `[idlm_dashboard]`, `[idlm_leads_list]`, `[idlm_kanban]`, `[idlm_workspace]`, `[idlm_quotations]`.
- Roles: administrator, assignment manager, sales representative, pre-sales representative, logged-out/unauthorized.
- Viewports: desktop, tablet, mobile; verify drawer, sidebar, modals, tables, Kanban, charts, and settings forms.
- Browser checks: no console errors, failed network calls, duplicate asset loads, missing selectors, or flash caused by workspace collapse state.

Documentation/release work:

- Update `AGENTS.md`, `ARCHITECTURE.md`, `PROJECT-MAP.md`, `FEATURE-MAP.md`, `DEVELOPMENT.md`, and `TECH-DEBT.md` as boundaries move.
- Update plugin header, `IDLM_VERSION`, test bootstrap version, `readme.txt` stable tag, `CHANGELOG.md`, and `docs/CHANGELOG-DEV.md`.
- Attribute Author / Builder to Shashi.

Completion evidence (version 1.98.1): the 432-line facade retains all 75 public methods and the focused class/template boundaries established in Phases 1–4. Full PHP lint, 67 fast contracts, and 12 disposable native WordPress/MySQL tests pass. Live checks covered all six wp-admin pages and eight frontend workspace routes, dashboard/settings/sidebar/Leads modal and drawer interactions, Pipeline cards and overlays, desktop/tablet/mobile layouts, administrator and unauthenticated output, expected module assets, and 14-route console/network diagnostics with no warnings, errors, or failed requests. The capability/object-access contract continues to cover administrator, assignment manager, sales representative, pre-sales representative, owner, and unauthorized cases. Release metadata and engineering documentation are aligned at 1.98.1 with Shashi attribution; detailed evidence is recorded in [ADMIN-REFACTOR-VERIFICATION.md](ADMIN-REFACTOR-VERIFICATION.md).

## Stop conditions

Stop the active extraction and diagnose before continuing if any phase causes:

- Changed capability or object-level access behavior.
- Missing nonce validation or secret exposure.
- Changed AJAX/shortcode/menu/asset names or request fields.
- Changed lifecycle, routing, quotation, or database behavior.
- Lost DOM selectors required by existing JavaScript.
- Different shortcode return/echo behavior.
- A failing baseline test that cannot be explained by an intentionally updated structural contract.

Do not combine an unrelated feature, visual redesign, schema migration, integration change, or lifecycle rule change with this refactor.

## Definition of done

- `class-admin.php` is a documented thin compatibility facade of approximately 500–800 lines.
- All extracted classes and templates have clear single responsibilities and direct test coverage.
- All established public and UI contracts remain compatible.
- Full lint, fast, native, and manual matrices pass.
- Version and changelog metadata are aligned and attributed to Shashi.
- The admin-facade item can then be removed from `TECH-DEBT.md` with evidence, not merely because templates exist.
