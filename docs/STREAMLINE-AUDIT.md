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
| S9 | 2a66739 | Eight reads of plugin-owned option and log rows used `maybe_unserialize()`, which builds any object named in the stored string | `class-admin-logs-renderer.php:200`, `class-capi.php:264`, `class-assignment-capacity.php:44`, `class-data-retention.php:128,225`, `class-whatsapp-inbox.php:15`, `class-interakt-dispatch.php:35`, `class-lead-access-policy.php:161` | Security: no PHP object can be created from stored data (object-injection hardening) | None: the plugin only stores scalars and arrays; contract compares with core `maybe_unserialize()` and proves no object is woken | `IDLM_Schema_Migration::decode()` with `allowed_classes => false` |
| S10 | 2006452 | Meta and Interakt webhook HMAC checks were identical copies | `includes/class-interakt.php:261,372` | Maintainability | None: both public methods kept; contract runs the same accept/reject cases through both | Meta check delegates to the shared one |
| S11 | 50a089f | 84 CSS rules (12 KB) were exact copies of a later rule in the same file and media context, left by layered fixes | `assets/css/admin-style.css`, `assets/css/quotations.css` | Size: −12 KB CSS; clarity | None: the later copy always wins; computed styles of every element on 14 screens, CRM and legacy, 1440 and 390 px, identical | Earlier copies removed; contract keeps every stylesheet free of dead duplicates |
| S12 | 117dc9f | `admin-app.js` painted a glow on the Today's work button and `workspace.js` watched the button's style to strip it | `assets/js/admin-app.js:4149`, `assets/js/workspace.js:207` | One MutationObserver less; no paint-then-undo | None: computed styles identical; legacy button still glows | `admin-app.js` skips the glow on the workspace entry |
| S13 | 7524060 | The dashboard's business-mix table and segment switch were copied in the pre-sales and sales renderers | `assets/js/dashboard.js:233,280` | Maintainability | None: rendered dashboards identical | One `renderBusinessMix()` |
| S14 | c4a850a | 17 `IDLM_Admin_Ajax` methods only forwarded to a controller (D11) | `includes/class-admin-ajax.php`, `includes/class-admin.php:434` | One hop instead of three | None: hooks unchanged; `IDLM_Admin_Ajax` inherits the facade method, so old names still work | Facade calls controllers directly |
| S15 | c16aa59 | Two designs behind a switch; Payments kept a second, older page (D2) | `includes/class-admin-assets.php`, `includes/class-lead-payment-controller.php` | One design; −295 lines in Payments | Low: CRM markup unchanged; see Live update safety | Switch retired; old payment page and upload step removed; details only the old page showed added to the dialog page |
| S16 | 226ae9b | About 270 old-design branches in templates, controllers and scripts, plus six render-time rewrites of labels and inline styles | templates, `includes/class-admin-*`, `assets/js/*` | One rendering path | Low: byte-compared with the CRM baseline | Kept the CRM branch; rewrites moved into the templates |
| S17 | 77b75a0 | ~115 stylesheet rules matched only removed markup | `assets/css/*` | Size: −12 KB CSS | None: computed styles unchanged | Removed |
| S18 | 582546d | The Download OCI CSV button (`idlm_export_google_conversions`) checked `manage_options` but no request token (D10) | `includes/class-admin-ajax.php:380`, `templates/admin/settings/capi.php:138` | Security: another site cannot start the download in an administrator's browser | Low: the button carries the token; a bookmarked old link returns 403. The REST feed URL Google fetches is unchanged | Nonce on the link, checked after the capability |
| S19 | 4d0efcd | HTTP suite appended to a committed log (D9) | `tests/integration/quotation-engine-http-cases.php` | Clean tree after tests | None: test-only | Writes to `IDLM_TEST_ENGINE_EVIDENCE_DIR`; contract stops writes into `docs/` |
| S20 | 55886c4 | 9 unused public methods and 2 old all-roles handlers (D8, D12) | `class-admin`, `class-date-ranges`, `class-db`, `class-integrations`, `class-interakt`, `class-quotation-repository`, `class-quotations`, `class-admin-users-controller` | −80 lines; two fewer admin-post actions | Low: no plugin caller; the Roles tab uses `idlm_update_role_access`, whose tests now cover the same denials and grant rules | Removed |
| S21 | c730641 | Leads and Settings read 28 and 57 options one at a time (D7) | `class-admin-leads-renderer.php`, `class-admin-settings-renderer.php` | One query per screen for those options | None: same values and defaults | `SCREEN_OPTIONS` primed; drift contract |
| S22 | 3e5210c | Schedule lookups on every page view and a multi-table legacy queue scrub on every request (D3) | `class-scheduler.php`, `class-integrations.php` | Both at most every 10 minutes and on the first request after an update | Low: the minute cron interval is now registered on every request, cron included. S27 throttles the rest of the upkeep | `IDLM_Scheduler::maintenance_due()`, one autoloaded option |
| S23 | e02261a, 8fcda74 | 34 copies of nonce + permission checks with five refusal spellings; five bare-string errors (D6) | `IDLM_Admin::require_ajax_access()` after each handler's own nonce check, 8 controllers | One gate; every error is `{message}` | Low: nonce still first; specific refusal texts and 403s kept, generic ones now read "Permission denied." | Routing save and past-lead sync scripts read `{message}` (routing errors used to show a generic line) |
| S24 | 9205249 | Every workspace view loaded every view's scripts and styles (D1) | `IDLM_Admin_Assets::VIEW_ASSETS`, `IDLM_Admin_Shortcodes::workspace_sections()` | Plugin assets per view: Leads 1,159 → 667 KB, Pipeline → 761, Dashboard → 953, Quotations 1,303 → 861, Settings → 764 | Low: pages with other CRM shortcodes still load everything; computed styles unchanged; drawer behaviour checked in S28 | Per-view manifest |
| S25 | cfb25b8 | Rep board columns and the handoff queue scanned large index ranges (D4) | `class-db.php` | 200k synthetic leads: rep board column 26 → 0.04 ms, handoff queue 150 → 0.05 ms; other queries unchanged | Low: structure only, one-time; see Live update safety | Two composite indexes via migration marker `idlm_lead_index_version`; native test proves no row changes |
| S26 | 81c8c8a | Old declarations the CRM layer always overrides (D2b) | `admin-style`, `workspace`, `dashboard`, `kanban`, `quotations` CSS | −15.8 KB CSS (428 declarations) | None: removed only where the same selector behind the always-on gate sets the same property; computed styles unchanged | Removed |
| S27 | 61aae58 | `IDLM_Integrations::init()` still generated API credentials and swept platform secrets on every request, with uncached option reads; schedules were only checked on front-end page views (D3) | `class-integrations.php`, `class-scheduler.php`, activation | 5 fewer queries on every screen and AJAX request (table below); a throttled request makes no queries for upkeep | Low: in the 10 minutes between sweeps every use still repairs itself (credential checks migrate credentials first; `IDLM_Secret_Store::get()` encrypts plaintext before returning it). Admin, AJAX, REST and cron requests now repair schedules too | `IDLM_Integrations::run_upkeep()` on the throttle, on the first request after an update and on activation; native test `maintenance-contracts.php` covers fresh install, throttle window, repair after it and repair after an update |
| S28 | 6026cad | The D1 contract pinned 4 of 11 views (D1) | `tests/streamline-contracts.php` | Every view's exact styles and scripts are pinned, plus the unknown-view fallback | None: test-only | Browser check below |

`tests/streamline-contracts.php` holds the drift guards for S2 to S13 and runs in the fast suite.

S11 to S13 change front-end files, so each was also checked in headless Chrome against a disposable HTTP fixture: computed styles of every element and `::before`/`::after` on 14 screens (six workspace views, the tasks, leads, settings and quotation fixtures, and four legacy-design views), at 1440 and 390 px, before and after. All 36,899 element views matched, with no script errors. A deliberate 1px change was caught on 331 elements, so the comparison is sensitive.


### D1 and D3, before and after

Measured with `php tests/performance/measure-screens.php --runs=5` (median of 5, same synthetic data) on c730641, just before D3 and D1, and on 61aae58 with both. The asset columns are every stylesheet and script on the page, WordPress core included.

| Screen | Queries | Plugin queries | CSS KB | JS KB | Wall ms |
|---|---:|---:|---:|---:|---:|
| Workspace: dashboard | 42 → 37 | 33 → 28 | 648 → 515 | 730 → 587 | 107 → 112 |
| Workspace: leads | 58 → 53 | 49 → 44 | 648 → 482 | 730 → 336 | 110 → 112 |
| Workspace: pipeline | 43 → 38 | 34 → 29 | 648 → 512 | 730 → 394 | 106 → 105 |
| Workspace: quotations | 41 → 36 | 32 → 27 | 707 → 590 | 815 → 421 | 112 → 108 |
| Workspace: settings | 88 → 83 | 79 → 74 | 721 → 592 | 1,258 → 922 | 115 → 115 |
| AJAX: dashboard stats | 35 → 30 | 27 → 22 | – | – | 113 → 103 |
| AJAX: leads table | 40 → 35 | 32 → 27 | – | – | 123 → 116 |
| AJAX: board columns | 38 → 33 | 30 → 25 | – | – | 116 → 112 |

Wall time is within this machine's run-to-run noise. These query counts are lower than the table above because that table's "before" is the 1.103.0 release and the fixture differs (5 runs here, 3 there).

**Browser check for D1.** On a disposable HTTP fixture, headless Chrome opened eight views (dashboard, leads, pipeline, quotations, settings, design team, CAPI logs, payments) as administrator, sales manager, sales executive, pre-sales and design, at 1440 and 390 px, before D1 and after it: 80 page loads each. On each it opened the lead drawer where the view offers one, saved it at 1440 px and recorded the requests that followed. Every case matched before and after: the drawer opened and saved, then reloaded itself and the leads table, and on the pipeline also the board. There were no console errors. The drawer did not open for pre-sales on the fixture's leads, and the design role and the administrator's quotations, settings, design-team, logs and payments views render no drawer; both were the same before D1.
---

## Decisions (all resolved)

| # | Outcome |
|---|---|
| D1 | Done (S24, S28). No view script held cross-view behaviour: the `ajaxSuccess.idlmKanban` handler in `kanban.js` only reloads the board and returns early when there is none, and the dashboard's drawer code is scoped to its own cards. The lead-drawer styles stay in the `leads` and `payments` design areas, which every view loads. Splitting them into their own file would save at most about 17 KB gzipped (cached after the first view) and risks unstyled states in rarely opened overlays, so it was left out. |
| D2 | Done (S15–S17, S26). Of the old stylesheet, only declarations provably overridden in every state were removed. The rest of `admin-style.css` still styles shared classes the CRM layer builds on; removing more needs a state-by-state visual pass. |
| D3 | Done (S22, S27). One autoloaded option read per request; the throttle is an option rather than a transient so an object-cache eviction cannot make it run on every request. |
| D4 | Done (S25). A `(created_at)`-led composite was measured and left out: MySQL sometimes chose it for the all-leads count and list and ran slower. |
| D5 | Not done, no benefit: the boot list also holds secrets and schema markers that must never autoload, so its one priming query stays either way. |
| D6 | Done (S23). Three handlers keep their own checks on purpose: the lead CSV export (a download link, refuses with a page), JS error logging (any signed-in user) and the task router (delegates per operation). |
| D7 | Done (S21). |
| D8 | Done (S20). |
| D9 | Done (S19). |
| D10 | Done (S18). |
| D11 | Done (S14). |
| D12 | Handlers done (S20). The `is_admin()` branches in quotation URLs stay: every AJAX request is `is_admin()` and live search builds its links through them. |

---

## Leave alone (reviewed, intentionally unchanged)

- **`IDLM_Recovery_Guard`** named locks (`GET_LOCK`/`IS_USED_LOCK`/`RELEASE_LOCK`, 6 queries per request) and its live generation read. They are the restore/reset fence, and the cost buys correctness.
- **`IDLM_Schema_Migration::version()` / `IDLM_Secret_Store::stored()` live reads.** Their uncached design is deliberate: revocation and multi-node correctness. S2 and S3 batch them and keep them live.
- **Dashboard SQL** (`IDLM_Dashboard::calculate_stats`, 52 queries cold). It is already cached per actor and scope by `IDLM_Dashboard_Read_Model` (138 → 72 queries warm before this branch, 101 → 35 after). The Won basis is shared through one set definition. Splitting the 1,345-line method is a maintainability task with high regression surface and no speed gain.
- **`IDLM_Date_Ranges`.** No screen bypasses it for reporting periods. The remaining `new DateTime('yesterday'/'today')` uses are fixed metric definitions ("yesterday's deliveries", task due dates), not user-chosen periods: `class-admin-collection-reads.php:190`, `class-admin-leads-renderer.php:119`, `class-mcp-core.php:387`, `class-db.php:894`, `class-presales-task-plan.php:132`.
- **JS helpers.** `escapeHtml` exists four times with different edge behaviour. The global in `admin-app.js:4176` returns `''` for `0` and is reachable from inline handlers. `debounce` exists twice. Merging them changes edge output; low value.
- **MutationObservers.** `dialog.js:520` (body subtree, promotes inline notices to toasts in the design layer) and `workspace.js` (badge count and panel state sync) are scoped and cheap. The button-style observer was removed in S12. The 15-minute leads refresh (`admin-app.js:1835`) skips hidden tabs.
- **Toasts vs inline notices.** Quotation and payment screens still use `?idlm_notice=` server notices, which `dialog.js` promotes to toasts in the design layer. They are consistent in CRM mode, and changing it is part of D2.
- **`IDLM_Admin` facade** delegates (class-admin.php:430-469). They are compatibility contracts per AGENTS.md.
- **CSS pruning.** A class-name scan flags 71 of 1,064 `.idlm-*` selectors as unreferenced, but many are built dynamically (`idlm-badge-<source>`, `idlm-col-<n>`). Prune only with D1/D2 and a browser pass.

## Plugin Check

Official Plugin Check 2.1.0 (ZIP SHA-256 `6ff4bd21…3fc4`, the CI pin) with WP-CLI 2.12.0 (SHA-512 checked against the release) ran the default static checks, with no exclusions, on release ZIPs built by `tests/release/build-release.php`:

| Revision | Diagnostics | Exit |
|---|---:|---:|
| 13d741d (S1–S8) | 0 | 0 |
| 7524060 (S1–S13) | 0 | 0 |

The release ZIP contains no test files. Local runs used WordPress 7.1.4; CI uses 6.6.

## Live update safety

No lead, quotation, payment or setting value is changed on update. REST routes and shortcodes are unchanged.

What does change on update:
- **Two indexes are added once** to `idlm_leads` by the new migration marker `idlm_lead_index_version`. It only runs `ALTER TABLE … ADD KEY` for a missing index; it reads and writes no row, and the schema version is not bumped, so no older repair re-runs. On 200,000 rows adding them took under a second. The database user needs the ALTER privilege, as for any plugin schema update. Take a backup before updating, as with any schema change.
- **One new option** `idlm_maintenance_checks` (autoloaded) records when the schedule check and the credential, secret and legacy queue upkeep next run.
- **The design.** Sites on the CRM design (the default) see the same pages. A site switched to the previous design by the Utilities switch or `IDLM_DESIGN_LAYER` will show the CRM design; the stored option is left in place but not read.
- **Each workspace view loads fewer files.** Browsers fetch only the changed files.
- **Retired entry points.** `?idlm_design=…` is ignored; the Utilities "Interface design" form, `admin_post_idlm_set_design_layer`, `admin_post_idlm_payment_upload`, `admin_post_idlm_update_role_scopes` and `admin_post_idlm_update_role_permissions` are gone; wp-admin → Lead Manager → Payments opens the workspace page; a saved Download OCI CSV link without its token returns 403.
- **Removed public methods** (no caller in the plugin): listed in S20. Site code calling them would need updating.
- **Refusal text.** Generic AJAX refusals now read "Permission denied." instead of "Access denied"/"Access Denied".
- **Public code kept.** `IDLM_Admin_Assets::design_layer_enabled()` and every other `IDLM_Admin`/`IDLM_Admin_Ajax` method remain callable.

## Verification environment

- PHP 8.4.14 and a private MySQL 8.4.0 on a socket in `~/idlm-worktrees`, using a dedicated test user.
- WordPress core was copied from the Local site without `wp-config.php`.
- Every suite creates and drops its own `idlm_test_*` database.
- On macOS, set `TMPDIR` to a real path. The native suite's worker guard compares `/var` with `/private/var`, so 24 cases fail under the default `TMPDIR`.
