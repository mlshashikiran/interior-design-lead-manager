# Streamline audit — Interior Design Lead Manager 1.103.0

Builder: Shashi. Branch `streamline/plugin`, based on `release/1.103.0` at 0fbcb0a. Audit date 2026-11-06.

Goal: one coherent, faster plugin, with no change to behaviour that works today. This report lists what was found, what was changed on this branch, and what needs a decision first.

## How this was measured

- **Structure.** The code-review graph was rebuilt for this branch (316 files, 4,160 nodes). Its architecture, community, hub, large-function and dead-code views were cross-checked with Grep. Its dead-code list is unreliable here: about 90% of the 147 PHP "dead" symbols are reached through `add_action`/`add_filter` string callbacks, `onclick` handlers or the `IDLM_Admin` facade. Nothing was removed on the graph's word alone.
- **Runtime.** `tests/performance/measure-screens.php` is new on this branch. It creates a new `idlm_test_*` database and installs WordPress 7.1.4 with the plugin active. It seeds the deterministic small synthetic profile (1,000 leads, 8,000 activities, 2,000 tasks, 500 quotations) and blocks outbound HTTP and mail. It then measures each screen and its first data request, each in a new PHP process with the full WordPress request lifecycle. There is no persistent object cache, PHP is 8.4 and MySQL is 8.4. Numbers are the median of three runs after one warm-up run.
- **Caveat.** The fixture is a fresh install, so options that a real site has autoloaded may be missing here, and a missing option also costs one query. Options that the plugin saves without autoload cost a query on every site. That covers the schema and backfill markers, access-policy and settings versions, and secrets. The savings below come from those options, so they apply to production.

### Before and after

The tables below are final. They were measured after every commit had been verified, with nothing else using the database server.

**Queries per request**

| Screen | Before | After | Plugin queries before | Plugin queries after |
|---|---:|---:|---:|---:|
| Workspace: dashboard | 80 | 43 | 71 | 34 |
| Workspace: leads | 121 | 84 | 112 | 75 |
| Workspace: pipeline (board) | 81 | 44 | 72 | 35 |
| Workspace: quotations | 79 | 42 | 70 | 33 |
| Workspace: settings | 181 | 140 | 172 | 131 |
| AJAX: dashboard stats (warm read-model cache) | 72 | 35 | 64 | 27 |
| AJAX: leads table, page 1 | 78 | 41 | 70 | 33 |
| AJAX: board columns | 75 | 38 | 67 | 30 |

The dashboard stats request makes 138 queries before and 101 after when its read-model cache is cold. The page HTML and every JSON response were byte-identical before and after on all eight screens.

**Asset bytes per screen** (uncompressed, CRM design layer on)

| Screen | CSS | JS |
|---|---:|---:|
| Dashboard, leads, pipeline | 690,216 | 755,479 → 755,058 |
| Quotations | 752,362 | 845,165 → 844,744 |
| Settings (adds the WordPress media library) | 764,561 | 1,296,059 → 1,295,638 |

Front-end bytes barely move on this branch. The large savings are in decision D1 below.

**Query time and wall time** (median of 3, local socket, measured in isolation)

| Screen | Query ms before | Query ms after | Wall ms before | Wall ms after |
|---|---:|---:|---:|---:|
| Workspace: dashboard | 3.42 | 2.79 | 107.53 | 105.45 |
| Workspace: leads | 6.06 | 5.41 | 122.6 | 122.34 |
| Workspace: pipeline | 4.04 | 3.52 | 110.6 | 111.13 |
| Workspace: quotations | 4.87 | 4.22 | 111.88 | 109.44 |
| Workspace: settings | 6.55 | 5.86 | 118.64 | 116.97 |
| AJAX: dashboard stats (warm) | 2.64 | 1.99 | 103.99 | 102.69 |
| AJAX: leads table | 12.59 | 12.07 | 117.03 | 122.49 |
| AJAX: board columns | 7.82 | 7.85 | 108.86 | 125.87 |

Wall time is dominated by WordPress boot on this machine and moves by up to ±15% between runs (the board request has the same queries and query time before and after). Each removed query costs about 0.05 ms on a local socket and about 0.3–1 ms against a networked database, so the 37 queries saved per request are worth roughly 11–37 ms per request on a typical host. Raw results: `php tests/performance/measure-screens.php --runs=3`.

## Architecture in one paragraph

A procedural bootstrap loads 97 `includes/` classes on every request. `idlm_boot_plugin()` on `plugins_loaded` then runs schema checks, credential migration, queue scrubbing and about 25 `::init()` calls. The `[idlm_workspace]` shortcode renders one section per request (`?idlm_view=`), but its enqueue path loads every section's CSS and JS. The CRM design layer (`IDLM_Admin_Assets::design_layer_enabled()`) is an overlay. The legacy CSS always loads, and seven `crm-design-*.css` files (about 350 KB) override it, gated by the `body.idlm-crm-design` class. JS and PHP templates branch on the same flag in about 140 places. Hotspots by graph degree: `IDLM_Dashboard::calculate_stats` (1,345 lines), `ajax_get_leads_table` (658 lines), `ajax_get_lead_details` (662 lines), `IDLM_Admin_Ajax` (1,676 lines) and `assets/js/admin-app.js` (4,200 lines).

---

## Safe now (done on this branch)

Each item is one commit. Each was verified with the fast suite, runtime prerequisites, Facebook runtime and UI, the native WordPress/MySQL suite and the disposable HTTP suite.

| # | Commit | What it was | Where | Impact | Risk | Change |
|---|---|---|---|---|---|---|
| S1 | 8c783eb | No repeatable per-screen numbers | `tests/performance/` | Measurement | None (test-only, not shipped) | Added `measure-screens.php` |
| S2 | 0ae55b8 | Quotation schema check ran 5 separate live option reads, twice per request | `includes/class-quotation-schema.php:20`, `includes/class-schema-migration.php:39` | Speed: −8 queries on every request | Low: same live, uncached read with the same absent/failed results | `IDLM_Schema_Migration::versions()` reads all markers in one query; contract compares it with the old per-marker check |
| S3 | f9f5119 | Platform-secret scan read 10 secrets one query at a time on every request | `includes/class-secret-store.php:118` | Speed: −9 queries on every request | Low: still uncached; encryption and readiness unchanged | `IDLM_Secret_Store::stored_many()`; contract covers plaintext encryption and failed reads |
| S4 | 06e2f57 | About 20 boot options read one by one, most stored without autoload | `interior-design-lead-manager.php:317`, `includes/class-db.php:12` | Speed: −20 queries on every request | Low: `wp_prime_option_caches()` fills the same cache `get_option()` uses | Prime them before the first read; `IDLM_DB::MIGRATION_STEPS` is now the one marker list; contract checks the boot path reads only primed options |
| S5 | 9312d74 | Quotation editor assets and `idlmQuoteData` were copied in two methods | `includes/class-quotations.php:60,121` | Maintainability | None: same calls, same order | `enqueue_editor_bundle()`; contract pins the order and localized keys |
| S6 | db97d21 | A quotation date check duplicated `IDLM_Date_Ranges::clean_date()`, and a third copy was unused | `includes/class-quotation-renderer.php:329`, `includes/class-quotation-controller.php:418` | Maintainability | None: equivalence contract over 16 inputs | Renderer delegates; unused copy removed |
| S7 | 6e4acfd | `getCurrentDateTimeString()` had no callers | `assets/js/admin-app.js:1746` | Size: −421 bytes | None: private to the closure | Removed |
| S8 | 43a45a1 | Nine inline `filemtime()` versions and a private copy of the version helper | design, payment and quotation classes | Maintainability | None: same values for shipped files | `IDLM_Admin_Assets::version()`; contract checks every asset |

`tests/streamline-contracts.php` holds the drift guards for S2 to S8 and runs in the fast suite.

---

## Needs a decision

Ordered by value. None of these was started.

### D1. Load only the active workspace section's assets (largest front-end win)
- **What.** Every workspace view loads `chart.js` (208 KB), `dashboard.js`, `kanban.js`, `settings.js`, `users-logs.js` and all seven `crm-design-*.css` area files, although the view renders one section.
- **Where.** `includes/class-admin-assets.php:186-216` (`enqueue_frontend_assets`) and `:219-247` (`enqueue_shortcode_assets('workspace')`). The view is chosen at `includes/class-admin-shortcodes.php:121`.
- **Impact.** On Leads, about 330 KB JS (chart.js, dashboard.js, kanban.js, settings.js) and about 180 KB of area CSS, out of 755 KB JS and 690 KB CSS.
- **Risk.** Medium. `kanban.js` binds a global `ajaxSuccess.idlmKanban` handler (`assets/js/kanban.js:585`) that reacts to drawer saves on any view. The lead drawer's styles live in `crm-design-leads.css` but the drawer opens from the dashboard and the board. Needs a browser pass per view and role.
- **Proposed.** A per-view manifest in `IDLM_Admin_Assets`: always load dialog, admin-app, workspace and the base layer, and add view-specific bundles. First move cross-view behaviour (the drawer-save handler and drawer styles) into the shared files.

### D2. Merge the legacy UI and the CRM design layer
- **What.** CRM mode ships the legacy stylesheet set (admin-style 230 KB, dashboard, kanban, workspace and quotations CSS) plus about 350 KB of overrides. There are about 140 `design_layer_enabled`/`idlm-crm-design` branches in PHP and JS: 21 in `admin-app.js`, 31 in `kanban.js`, 41 in `quotations.js` and 11 in the lead payment controller. No template file is duplicated; the split lives inside shared templates.
- **Impact.** Roughly halves CSS and removes the second code path.
- **Risk.** High. It is a large structural move, and `?idlm_design=legacy`, `IDLM_DESIGN_LAYER` and the Utilities toggle are documented rollback paths.
- **Proposed.** Decide whether the legacy design is still a supported rollback. If not, remove it area by area (payments → settings → quotations → board → leads), deleting the overridden legacy rules each time.

### D3. Stop running maintenance work on every request
- `IDLM_Integrations::init()` runs `maybe_migrate_api_credentials()`, `migrate_platforms()` and `scrub_legacy_queues()` on every request (`includes/class-integrations.php:9-22`). With Action Scheduler installed, `scrub_legacy_queues()` adds `SHOW TABLES`, `SHOW COLUMNS`, `MAX(action_id)`, a 100-row select, an option write check and a live read on every request (`includes/class-secret-store.php:135-195`).
- `IDLM_Scheduler::setup_schedules()` runs on every front-end `wp` hook. With Action Scheduler it issues four `as_next_scheduled_action()` lookups per page view, including anonymous visits (`includes/class-scheduler.php:26-65`).
- **Risk.** Medium. These run every request so they repair themselves, for example a plaintext secret written directly or a deleted schedule. Gating them changes when that repair happens.
- **Proposed.** Run them on activation, upgrade, `admin_init` and cron, plus a 5–15 minute transient throttle; keep the per-request fast path a single option read.

### D4. Indexes on the plugin's own tables (schema change)
- `idlm_leads` has 21 single-column indexes (`includes/class-db.php`, `create_tables`). Almost every read filters `is_duplicate = 0` together with an owner, stage or date column, and lists sort on `created_at`. That calls for composite indexes, for example `(is_duplicate, created_at)` and `(assigned_rep_id, is_duplicate, stage)`. Several low-selectivity single indexes (`lost_reason`, `disqualification_reason`, `call_status`) add write cost.
- **Proposed.** Run `tests/performance/capture-query-plans.php` on the expected profile, then add composites in a `IDLM_DB_VERSION` bump. Needs your approval: it changes the schema.

### D5. Autoload policy for always-read options
- Schema markers, `idlm_crm_access_policy_version`, `idlm_design_role_registered` and `idlm_settings_version` are saved with `autoload = no` but read on every request. S4 already folds them into one query. Autoloading them would make it zero, but needs a data update of existing rows.

### D6. One AJAX guard and one error shape
- 40 handlers repeat `check_ajax_referer('idlm_admin_nonce', 'nonce')` with a capability check. Denials come back in five forms: `'Permission denied.'` (27), `'Access denied'` (4), `'Access Denied'` as a bare string (3), `'Unauthorized operation.'`, and `wp_die()` text in `ajax_export_leads` (`includes/class-admin-ajax.php:398`). 236 errors use `array('message' => …)`, 8 use a bare string.
- **Risk.** Changing the error shape can change what the JS shows, and this touches authorization.
- **Proposed.** `IDLM_Admin_Ajax::guard($capability_rule)` returning the existing message per handler at first, then normalize messages after a JS audit of `response.data` vs `response.data.message`.

### D7. Repeated settings reads on render
- The leads view reads about 30 options and the settings view about 67, one at a time (`IDLM_Admin_Leads_Renderer::build_context`, settings renderer). Many are missing or non-autoloaded on fresh installs. The proposal is priming per screen, like S4. It wasn't done here because on long-lived sites most of these are already autoloaded, and the measured gain would come mostly from the fixture.

### D8. Unused public methods
- These have no callers in the plugin, templates, JS or MCP: `IDLM_Admin::get_pre_sales_user_ids`, `IDLM_Date_Ranges::options_html`, `IDLM_DB::reschedule_pending_presales_tasks`, `IDLM_Integrations::rep_has_capacity`, `IDLM_Interakt::log_customer_reply_to_lead` and `log_meta_customer_reply_to_lead`, `IDLM_Quotation_Repository::update_revision`, `IDLM_Integrations::get_rep_capacity_status`, and the no-op `IDLM_Quotations::register_menu`.
- **Risk.** Low, but they are public static methods that site code or snippets could call.
- **Proposed.** Remove after confirming nothing outside the plugin uses them.

### D9. Test writes into a tracked file
- `tests/integration/quotation-engine-http-cases.php:26` appends to `docs/remediation-2026-10-07/quotation-engine-slice/expected-http-fault-diagnostics.log` on every HTTP-suite run, which dirties the tree.
- **Proposed.** Write to `IDLM_TEST_*_EVIDENCE_DIR` like the document cases do.

---

## Leave alone (reviewed, intentionally unchanged)

- **`IDLM_Recovery_Guard`** named locks (`GET_LOCK`/`IS_USED_LOCK`/`RELEASE_LOCK`, 6 queries per request) and its live generation read. They are the restore/reset fence, and the cost buys correctness.
- **`IDLM_Schema_Migration::version()` / `IDLM_Secret_Store::stored()` live reads.** Their uncached design is deliberate: revocation and multi-node correctness. S2 and S3 batch them and keep them live.
- **Dashboard SQL** (`IDLM_Dashboard::calculate_stats`, 52 queries cold). It is already cached per actor and scope by `IDLM_Dashboard_Read_Model` (138 → 72 queries warm before this branch, 101 → 35 after). The Won basis is shared through one set definition. Splitting the 1,345-line method is a maintainability task with high regression surface and no speed gain.
- **`IDLM_Date_Ranges`.** No screen bypasses it for reporting periods. The remaining `new DateTime('yesterday'/'today')` uses are fixed metric definitions ("yesterday's deliveries", task due dates), not user-chosen periods: `class-admin-collection-reads.php:190`, `class-admin-leads-renderer.php:119`, `class-mcp-core.php:387`, `class-db.php:894`, `class-presales-task-plan.php:132`.
- **JS helpers.** `escapeHtml` exists four times with different edge behaviour. The global in `admin-app.js:4176` returns `''` for `0` and is reachable from inline handlers. `debounce` exists twice. Merging them changes edge output; low value.
- **MutationObservers.** `dialog.js:520` (body subtree, promotes inline notices to toasts in the design layer) and `workspace.js:205-213` (badge sync) are scoped and cheap. The 15-minute leads refresh (`admin-app.js:1835`) skips hidden tabs.
- **Toasts vs inline notices.** Quotation and payment screens still use `?idlm_notice=` server notices, which `dialog.js` promotes to toasts in the design layer. They are consistent in CRM mode, and changing it is part of D2.
- **`IDLM_Admin` facade** delegates (class-admin.php:430-469). They are compatibility contracts per AGENTS.md.
- **CSS pruning.** A class-name scan flags 71 of 1,064 `.idlm-*` selectors as unreferenced, but many are built dynamically (`idlm-badge-<source>`, `idlm-col-<n>`). Prune only with D1/D2 and a browser pass.

## Plugin Check

Plugin Check 2.1.0 and WP-CLI are not installed on this machine, so the release-gate sniff run could not be repeated locally. The CI release gate runs it on push. New code follows the existing annotated-SQL conventions; one new `phpcs:disable` block (in `IDLM_Schema_Migration::versions()`) reuses the existing `REL007-SQL-0388` justification.

## Verification environment

- PHP 8.4.14 and a private MySQL 8.4.0 on a socket in `~/idlm-worktrees`, using a dedicated test user.
- WordPress core was copied from the Local site without `wp-config.php`.
- Every suite creates and drops its own `idlm_test_*` database.
- On macOS, set `TMPDIR` to a real path. The native suite's worker guard compares `/var` with `/private/var`, so 24 cases fail under the default `TMPDIR`.
