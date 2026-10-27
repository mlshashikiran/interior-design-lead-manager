# Feature Map

Use this map to identify the likely blast radius before changing a business feature. Follow links to [FLOWS.md](FLOWS.md), [INTERFACES.md](INTERFACES.md), and [DATA-MODEL.md](DATA-MODEL.md) for detailed behavior and contracts.

## Leads and pipeline

- **PHP:** `IDLM_DB` is the lifecycle/persistence authority; `IDLM_Admin_Leads_Renderer` builds the lead-list and shared-overlay contexts/templates behind `IDLM_Admin` delegates while `IDLM_Admin_Kanban_Renderer` builds the pipeline context/template; `IDLM_Admin_Lead_Reads` owns the extracted lead-detail read/drawer response; `IDLM_Admin_Activity_Controller` owns timeline reads and comment add/edit/delete, while `IDLM_Admin_Collection_Reads` owns list/Kanban/count reads and applies default manager lead keyset reads using `IDLM_Cursor_Pagination` with offset compatibility for other sorts/queues; `IDLM_Webhook_Intake` maps inbound fields behind the stable `IDLM_Integrations` facade, which routes ownership; `IDLM_Dashboard` reads aggregates.
- **Tables:** `idlm_leads`, `idlm_lead_activity_log`, immutable `idlm_lead_state_events`, `idlm_lead_touchpoints`; related `idlm_tasks`, `idlm_call_logs`, `idlm_capi_logs`, and quotation tables.
- **AJAX:** `idlm_get_lead_details`, `idlm_get_lead_activity`, `idlm_get_leads_table`, `idlm_get_kanban_leads`, `idlm_quick_edit_lead`, `idlm_save_lead_details`, `idlm_add_lead_manual`, `idlm_bulk_action`, `idlm_delete_lead`, `idlm_import_leads_batch`, `idlm_upload_lead_file`.
- **REST:** `POST /interior-design-lead-manager/v1/webhook`.
- **Hooks:** `idlm_lead_stage_converted`, `idlm_lead_attribution_merged`, `idlm_lead_call_status_no_answer`, `idlm_trigger_interakt_event`.
- **Shortcodes/UI:** `[idlm_leads_list]`, `[idlm_kanban]`, `[idlm_workspace]`; `assets/js/admin-app.js`, `assets/js/kanban.js`; `assets/css/admin-style.css`, `assets/css/kanban.css`, `assets/css/workspace.css`.
- **Verification:** the Phase 5 matrix covers the wp-admin and frontend dashboard, Leads, Pipeline, logs, Settings, account, and quotation surfaces at desktop/tablet/mobile widths; see [ADMIN-REFACTOR-VERIFICATION.md](ADMIN-REFACTOR-VERIFICATION.md).
- **High-risk coupling:** always route lifecycle mutations through `IDLM_DB::insert_lead()`, `upsert_inbound_lead()`, or `update_lead()` so duplicate, qualification, handoff, task, timing, activity, and integration behavior remains intact.

## Follow-ups, qualification and tasks

- **PHP:** `IDLM_DB` creates/reschedules/completes qualification and sales follow-up tasks and enforces qualification transitions; `IDLM_Admin_Ajax` handles outreach evidence, decisions and task CRUD behind facade delegates; `IDLM_Admin_Lead_Notifications` supplies reminder evaluation and pending-call counts; `IDLM_Integrations` performs assignment/handoff fallback.
- **Tables:** `idlm_tasks`, `idlm_leads`, `idlm_lead_activity_log`, `idlm_call_logs`.
- **AJAX:** `idlm_log_presales_touch`, `idlm_presales_decision`, `idlm_get_lead_tasks`, `idlm_add_lead_task`, `idlm_toggle_task_status`, `idlm_delete_lead_task`, `idlm_get_rep_tasks`, `idlm_run_fallback_now`.
- **Schedules:** `idlm_hourly_assignment_fallback` / `idlm_hourly_assignment_fallback_cron` call `IDLM_Integrations::run_assignment_fallback()` and `IDLM_DB::roll_forward_presales_tasks()`.
- **Settings:** outreach targets, working days/hours, reminder rules, assignment mode/pools, capacity, studio mappings, handoff policy and fallback thresholds.
- **JS/CSS:** task, activity and pre-sales behavior is primarily in `assets/js/admin-app.js` and `assets/css/admin-style.css`.
- **High-risk coupling:** quality, stage, call evidence, task completion, owner, `qualified_at`, and `handoff_status` are one workflow rather than independent fields.

## Quotations and catalogue

- **PHP:** `IDLM_Quotations` remains the compatibility facade for save/import transaction coordination, request/file/CSV validation, autosave, CSV streaming, list/editor/detail rendering and print output; `IDLM_Quotation_Policy` owns quotation capabilities and object-level lead access; `IDLM_Quotation_Lead_Read_Model` owns scoped lead existence/search/editor reads; `IDLM_Quotation_Calculator` owns deterministic item/section normalization and totals; `IDLM_Quotation_Schema` owns installation/backfills; `IDLM_Quotation_Repository` owns core table mapping, numbering, quotation/catalogue reads and writes, list read models, revision content replacement/copying, history, lead quote-value projection and cleanup persistence. `IDLM_Admin::ajax_delete_lead()` coordinates quote cleanup through the facade.
- **Tables:** `idlm_quotes`, `idlm_quote_revisions`, `idlm_quote_revision_sections`, `idlm_quote_revision_items`, `idlm_quote_payment_milestones` (legacy-retained), `idlm_quote_status_history`, `idlm_number_sequences`, `idlm_quote_catalogue_items`, `idlm_quote_item_categories`, `idlm_quote_item_units`; `idlm_leads` supplies the linked customer.
- **AJAX:** `idlm_search_quote_catalogue`, `idlm_search_quote_leads`, `idlm_autosave_quote`, `idlm_discard_quote_autosave`.
- **Admin-post:** `idlm_save_quote`, `idlm_print_quote`, `idlm_save_quote_settings`, catalogue item/category/unit save/archive/bulk/import/export actions.
- **Shortcodes/UI:** `[idlm_quotations]`; internal `[idlm_payment_structure]` expansion inside configured print HTML; quotations section of `[idlm_workspace]`.
- **JS/CSS:** `assets/js/quotations.js`, `assets/css/quotations.css`; shared workspace/admin assets.
- **Settings/transients:** `idlm_quote_settings`, quotation schema/backfill markers, `idlm_quote_autosave_*` transients.
- **High-risk coupling:** totals, item snapshots, numbering, current revision, idempotency keys and multi-table transactions must be reviewed together. Direct exports must use only the requested immutable revision and remain capability/nonce/lead-scope protected.

## WhatsApp and Interakt

- **PHP:** `IDLM_Interakt_Dispatch` owns opaque ordinary jobs and current eligibility; `IDLM_Interakt` sends outbound tracking events and accepts Interakt/Meta WhatsApp callbacks; `IDLM_DB` emits mapped events; `IDLM_Admin_Lead_Notifications` calculates unread/read state while `IDLM_Admin_Ajax` records manual WhatsApp touches behind facade delegates.
- **Tables/meta:** replies are represented in `idlm_lead_activity_log`; lead matching uses `idlm_leads.phone`; per-user read state is `idlm_whatsapp_read_activity_ids` user meta.
- **REST:** `POST /idlm/v1/interakt-webhook`; `GET|POST /idlm/v1/meta-webhook`.
- **Hooks:** `idlm_lead_call_status_no_answer`, `idlm_trigger_interakt_event`, `idlm_async_interakt_track_event`.
- **AJAX:** `idlm_log_presales_touch`; lead/activity endpoints surface replies and read state.
- **Settings:** Interakt enable/key/secret/event mappings; Meta webhook enable/verify token/app secret.
- **JS/CSS:** WhatsApp actions, the guarded Interakt workspace handoff, unread indicators and activity rendering live in `assets/js/admin-app.js` and `assets/css/admin-style.css`.
- **Boundary:** the plugin logs inbound replies and sends Interakt event triggers; it does not contain a general outbound WhatsApp chat API.

## Users, roles and access

- **PHP:** bootstrap functions `idlm_register_crm_roles_and_capabilities()` and `idlm_get_crm_role_slugs()`; CRM access decisions in `IDLM_Lead_Access_Policy` behind `IDLM_Admin` delegates; account presentation/updates in `IDLM_Admin_Account_Controller`; `IDLM_Quotation_Policy` behind the existing `IDLM_Quotations` delegates; scoped quotation lead reads in `IDLM_Quotation_Lead_Read_Model`; scoped dashboard queries in `IDLM_Dashboard`.
- **WordPress storage:** `wp_users`, roles/capabilities, `idlm_phone`, `billing_phone`, and `idlm_whatsapp_read_activity_ids` user meta. Custom tables store user IDs without foreign keys.
- **Role/capability groups:** `idlm_pre_sales`; recognized sales/marketing/manager roles; `idlm_access_leads`, `idlm_qualify_leads`, `idlm_assign_leads`, and quotation capability family.
- **Admin-post:** `idlm_update_my_account` delegates to `IDLM_Admin_Account_Controller`; administrator-only `idlm_create_user`, `idlm_update_user`, and `idlm_update_role_scopes` delegate to `IDLM_Admin_Users_Controller` for managed-role/user access changes.
- **Admin UI:** Lead Manager → Users & Access configures per-role lead visibility (`All leads`, `Assigned leads only`, or the backward-compatible CRM default), lists managed CRM users, creates accounts, changes roles, suspends CRM access, and sends reset links. Role scopes are stored in `idlm_role_lead_scopes`; suspension is stored in `idlm_crm_access_disabled`; accounts and historic ownership are retained.
- **Shortcodes/UI:** My Account inside `[idlm_workspace]`; administrators also receive Users & Access in that navigation. Visibility controls CRM/quotation reads; mutations separately require current ownership or explicit manager authority and operation capabilities. In 1.102.44, effective capability enrollment is mandatory, ordinary editorial role grants are removed once, default global visibility becomes assigned-only, explicit scopes are preserved, and version updates do not restore revoked grants. Existing CRM user creation/role changes individually enroll explicitly selected editorial users.
- **Settings:** `idlm_reps_see_all_leads`, active pre-sales/sales pools, capacity and studio assignments.
- **High-risk coupling:** capability checks must be paired with object-level lead visibility/ownership; recognized role names are dynamically resolved for compatibility.

## PDFs, print and exports

- **Quotation Preview / Print:** `IDLM_Quotations::render_print_view()` via `admin-post.php?action=idlm_print_quote`; requires view/download capabilities, nonce and lead visibility. Uses `assets/css/quotations.css` plus inline print markup/JavaScript.
- **Quotation downloads:** `IDLM_Quotation_Exporter` serves immutable requested-revision PDF (bundled Dompdf), UTF-8-BOM CSV, or typed OOXML XLSX via three authenticated admin-post routes. Every route requires `idlm_view_quotes`, `idlm_download_quote_pdf`, a format-specific nonce, revision ownership, and scoped lead visibility.
- **Lead CSV:** `IDLM_Admin::ajax_export_leads()` delegates to `IDLM_Admin_Ajax`, requiring `manage_options` and admin nonce with existing visibility/filtering. All text cells use `IDLM_Quotation_Exporter::csv_text()`; ID and decimal quote value preserve numeric representation. See [CORE-003 implementation and limits](AUDIT-REMEDIATION-CSV-SLICE.md).
- **Google conversion CSV:** `IDLM_CAPI::generate_google_ads_conversions_csv_response()` via admin AJAX and authenticated REST feed.
- **Google audience CSV:** `IDLM_Audiences::generate_google_ads_audience_csv_response()` via authenticated REST feed.
- **Catalogue CSV:** import/export admin-post handlers in `IDLM_Quotations` with formula-neutralization and file validation.
- **Tables read:** leads, CAPI logs, audience rules/options and quotation/catalogue tables depending on export.
- **High-risk coupling:** headers/output buffering, authorization, CSV formula safety, semantic quotation markup, and print CSS affect exported customer/integration documents.

## Settings and configuration

- **PHP/UI:** `IDLM_Admin_Settings_Controller` owns CRM/integration settings POST validation, persistence, and authenticated API-credential rotation; `IDLM_Admin_Settings_Renderer` builds settings context and delegates to guarded tab/section templates behind `IDLM_Admin::render_settings_page()`. `ajax_save_routing_settings()` covers routing controls. `IDLM_Quotations::render_settings_page()` / `handle_save_settings()` own quote configuration.
- **Storage:** `idlm_*` WordPress options; major groups are vocabularies, targets/reminders, visibility, routing/pools/fallback, field mappings, platform credentials/event mappings, audience rules, telemetry, `idlm_quote_settings`, and the non-secret `idlm_white_label_settings` branding snapshot.
- **Authorization:** `manage_options` for CRM/integration settings; `idlm_manage_quote_settings` for quote settings; nonce required.
- **JS/CSS:** settings tabs, mapping builders, white-label media/preview/contrast controls, tests and batch sync controls use the guarded settings templates plus `assets/js/admin-app.js` and `assets/js/settings.js`; presentation is in `admin-style.css`, `workspace.css`, and `quotations.css`.
- **Side effects:** settings can change routing, visible states, CAPI/Interakt dispatch, scheduled audience behavior, print output, and plugin-owned sign-in/workspace/account branding without code changes. White-label presentation is scoped to plugin roots and does not consume or modify site theme settings.
- **High-risk coupling:** several configurable labels are also hard-coded workflow literals. Secrets are ordinary WordPress options and must never be exposed to localized JS, logs or diagnostics.

## Reporting, dashboards and diagnostics

Dashboard body contexts are selected by `IDLM_Dashboard::access_context()` and enforced on the response with `filter_stats()`. Administrators/assignment managers have Sales and Marketing; marketing roles have Marketing; remaining CRM roles have Sales, with pre-sales limited to operational/qualification data. All aggregates and investigations use existing current-owner/read-all visibility. Named representative breakdowns and organization targets require assignment-manager authority. Source/campaign reporting uses creation cohorts with present-day outcomes, while Sales queues use the current snapshot and Won/Lost counts use recorded events.

`idlm_get_dashboard_stats` also accepts an allowlisted `investigate` kind, exact `value`, and `records_page` (25 rows, one lookahead). The existing period validation, nonce, role context and record scope apply on every request. The returned minimal lead identities open the existing permission-checked drawer. No spend or multi-touch attribution is inferred.


- **PHP:** `IDLM_Dashboard::get_stats()` provides period comparisons, funnel/channel/rep metrics and response times; `IDLM_Admin_Dashboard_Renderer` renders the dashboard and `IDLM_Admin_Logs_Renderer` owns bounded CAPI/audience log reads and templates behind `IDLM_Admin` delegates; `IDLM_Telemetry` captures/prunes diagnostics; `IDLM_Operational_Health` reports aggregate operational gates and queue/fault thresholds in administrator System Health; `IDLM_MCP_Core` exposes analytical tools.
- **Tables:** primarily `idlm_leads`; also `idlm_capi_logs`, `idlm_audience_sync_logs`, `idlm_telemetry_logs`, `idlm_lead_activity_log`, and `wp_users`.
- **AJAX:** `idlm_get_dashboard_stats`, `idlm_get_capi_log_payload`, `idlm_get_audience_log_payload`, `idlm_retry_capi_events`, `idlm_clear_telemetry_logs`, `idlm_download_diagnostic_report`, `idlm_log_js_error`.
- **REST/CLI:** `GET|POST|DELETE /interior-design-lead-manager/v1/mcp`; `mcp-server.php` stdio transport; MCP tools for summaries, lead queries/details, rep performance and restricted SELECT.
- **Shortcodes/UI:** `[idlm_dashboard]`, dashboard/audit sections in `[idlm_workspace]`; `assets/js/dashboard.js`, bundled `assets/js/chart.js`, `assets/css/dashboard.css`.
- **Schedules:** daily telemetry pruning shares the audience-sync schedule.
- **High-risk coupling:** reporting repeats visibility SQL and literal statuses; MCP can return PII and must retain strict authentication/table/query restrictions.

## Advertising attribution, conversions and audiences

- **PHP:** `IDLM_Integrations` captures UTM/click-ID cookies, maps inbound payloads and classifies sources; `IDLM_CAPI` dispatches conversions; `IDLM_Audiences` selects and syncs customer lists.
- **Tables:** attribution on `idlm_leads` and `idlm_lead_touchpoints`; delivery audits in `idlm_capi_logs` and `idlm_audience_sync_logs`.
- **REST:** lead webhook plus Google conversion/audience CSV feeds.
- **Hooks/schedules:** lifecycle conversion and attribution-merge hooks; async CAPI hook; daily audience hooks.
- **Settings:** platform credentials/IDs, field and event mappings, audience rules/queue, currency and source classification inputs.
- **External services:** Meta Graph API, Google Measurement Protocol/Data Manager feeds, LinkedIn conversion/audience APIs.
- **High-risk coupling:** deduplication can merge attribution and intentionally re-fire successful conversion events; source classification, click IDs, event idempotency and logs must be assessed together.

## Telephony and call records

- **PHP/UI:** current call/outreach request handling is in `IDLM_Admin_Ajax`, reminder presentation support is in `IDLM_Admin_Lead_Notifications`, and lead lifecycle logic is in `IDLM_DB`; provider-neutral future contract is described in `docs/TELEPHONY-INTEGRATION.md`.
- **Tables:** `idlm_call_logs`, plus lead call status and activity/task records.
- **AJAX:** `idlm_log_presales_touch` records current manual evidence; no provider webhook route is registered in the inspected implementation.
- **Hooks:** no-answer transitions can trigger Interakt.
- **Status:** schema and manual workflow exist; automated cloud telephony ingestion remains a future integration boundary.

MCP analytics retain five tool names and HTTP/stdio transports. Explicit PII enrollment gates customer tools; restricted custom SQL has field grants, associated-user display projection and MySQL statement bounds. [Third remediation slice](AUDIT-REMEDIATION-MCP-SLICE.md).

Private lead files (Builder: Shashi, candidate 1.102.47): `class-private-files.php` owns WordPress-contained chunk storage, authenticated retrieval, verified legacy migration, expiry and erasure; existing admin AJAX, DB facade, deletion/reset and backup delegate narrowly. [Policy/evidence](AUDIT-REMEDIATION-PRIVATE-FILE-SLICE.md).

Backup/restore `1.102.48` (Shashi): consistent complete JSON export; same-site aggregate validation and staging; credentials/private-file exclusions; active-worker refusal and obsolete-intent rejection. [Sixth slice evidence](AUDIT-REMEDIATION-RESTORE-SLICE.md).

## CORE-006 task-plan repair — 2026-10-07

Shashi: follow-up/task persistence delegates from `IDLM_DB` to `IDLM_Presales_Task_Plan`; canonical update and automatic assignment share its checked transaction. Stable nullable cycle identities, legacy adoption, missing fill, evidence/reopening/owner/date synchronization and a checkpointed scheduled sweep are documented and tested in [the task-plan slice](AUDIT-REMEDIATION-TASK-PLAN-SLICE.md). Public AJAX/task facade contracts remain in place.

## Current assignment eligibility slice — CORE-009

Shashi: `IDLM_Lead_Access_Policy::is_assignable_rep()` owns target eligibility and contexts; integrations filter each assignment pool and canonical lifecycle/automatic assignment recheck locked live grants. Mutation choices exclude ineligible alternatives, while owner history/filters remain available. Public handler names and request contracts remain stable. [Scope and verification](AUDIT-REMEDIATION-ASSIGNMENT-ELIGIBILITY-SLICE.md).

## Current capacity slice — CORE-010

Shashi: `IDLM_Assignment_Capacity::validate_transition()` is shared by lead lifecycle insertion/update and automatic assignment. It serializes positive workload for each representative and reads current settings/count under locks. Integrations refresh recovery pools; manual handlers retain actor/nonce/object checks and delegate capacity to canonical persistence. [Scope, evidence and remaining gates](AUDIT-REMEDIATION-CAPACITY-SLICE.md).

## Identity review ownership

Shashi: `IDLM_Identity_Review` + `templates/admin/identity-reviews.php` own native admin inspection, nonce transport, external replay and erasure/retention policy. `IDLM_Lead_Lifecycle_Service::resolve_inbound_review()` owns checked canonical/separate/reject decisions. Schema/backup/deletion owners include the holding data; frontend lead editing/assignment completes separate prospects normally. [CORE-008](AUDIT-REMEDIATION-IDENTITY-REVIEW-SLICE.md).

## Bounded retention and reads (`1.102.55`)

Shashi: retention runs one checked 100-row batch with transactional `idlm_retention_progress`, recovery/session fencing, fair target rotation, continuation hook `idlm_continue_sensitive_retention`, and operational failure status. Task responses use 50-card signed keysets and scoped totals (`counts_only` skips cards); queue qualification history reads 100 dates at a time. WhatsApp filters/counts stay in SQL; compatibility ID helpers page 100 by `after`. Lead CSV stages 100-row reads, capped at 10,000 rows/32 MiB/20 seconds of application work, before headers. Core/routing/quotation schemas unchanged. [Policy, verification, API details and remaining production gates](AUDIT-REMEDIATION-BOUNDED-WORK-SLICE.md).


## State vocabulary and transition ownership (1.102.56)

Shashi: `IDLM_State_Registry` owns built-in labels and contextual manual-task/early-close/qualification subsets. Forms and handlers share those lists; DB normalization and the checked lifecycle service own coupled effects, with request authorization/evidence guards outside the registry. It is not a permitted-edge FSM. [Current transition/reversal and historical repair policy](STATE-POLICY.md), [CORE-013 evidence](AUDIT-REMEDIATION-STATE-POLICY-SLICE.md).


## Quotation migration durability (1.102.57)

Shashi: quotation schema 2.5.1 owns canonical declarations, physical verification and checked phase receipts behind the existing quotation facade. Boot/activation/health consume committed pending/ready completion; backup excludes runtime status and accepts complete 2.5.0 column-compatible manifests. Historical snapshot classification, commercial-state preservation and runtime engine drift remain QUO-005/010/007. [Implementation and verification](AUDIT-REMEDIATION-QUOTATION-MIGRATION-SLICE.md).


## Quotation commercial history (1.102.59)

Shashi: `normalize_revision_lifecycle()` now verifies relationships and preserves commercial data; lifecycle receipt is 1.1.0 and schema 2.5.3 with unchanged columns. Reuse `IDLM_Quotation_Policy::is_editable_draft()` at mutation/UI boundaries and keep live authorization plus quote locking. Preserve commercial evidence, never reconstruct erased pointers/dates by guessing. Existing standard fast/native/HTTP suites include the history modules; 296 / 219 / 326 cases pass locally. Complete 2.5.2 backups remain supported. [Policy, named evidence and limitations](AUDIT-REMEDIATION-QUOTATION-HISTORY-SLICE.md).


## Runtime quotation transactional engine gate (1.102.60)

Shashi: Quotation controller transactions and repository numbering share current physical engine validation, sorted zero-row metadata pins, and transaction-local READ COMMITTED for live grant reads. Installed markers do not bypass runtime validation. Save/working-copy/import errors preserve data and expose operator repair guidance. [Scope, executed checks and limits](AUDIT-REMEDIATION-QUOTATION-ENGINE-SLICE.md).

## Quotation numeric validation

Owner: calculator; request handling: quotation controller; presentation: quotation renderer and quotations.js; shared limits: quotation localization. Boundary tests: quotation-numeric-contracts.php and native/HTTP numeric children; JS golden vectors and disposable admin/mobile browser checks. Historical reads do not recalculate rows. [Numeric policy and qualification](AUDIT-REMEDIATION-QUOTATION-NUMERIC-SLICE.md).

## Historical document preservation (1.102.62)

Shashi: quotation renderer/controller/repository/exporter share saved revision currency and `IDLM_Quotation_Document` contractual snapshots. New-revision terms are reviewable in editor; older print/PDF/CSV/XLSX retain known content/payments. Unknown historical terms are explicit. Quotation schema/backup validator own additive NULL compatibility and strict snapshot preflight. [QUO-006 qualification](AUDIT-REMEDIATION-QUOTATION-DOCUMENT-SLICE.md).


## CAPI retry eligibility (INT-005, Shashi)

The CAPI worker, direct platform methods and administrator retry share a locked current-lead/platform dispatch boundary. Accepted conversions cannot be forced or resent by attribution merges. Manual retry resolves the original exact event/stage, respects current enablement and attribution, uses current credentials, preserves new-log destination/commercial identity and reports its own attempt. Durable per-root receipts cap actual manual attempts at three and refuse uncertain replay. [Earlier evidence](AUDIT-REMEDIATION-CAPI-RETRY-SLICE.md); the current response policy is in [flows](FLOWS.md).


## Inbound WhatsApp batches and identity (INT-006, Shashi)

`IDLM_WhatsApp_Inbox` validates normalized message identity, maps full phones to one canonical lead, and atomically persists activity plus a minimal replay receipt. Meta and Interakt retain their request authentication/parser boundaries in `IDLM_Interakt`; Meta loops every batch entry/change/message and includes button/list/media placeholders. Distinct canonical matches require review; duplicate records resolve only to a matching canonical phone. [Policy and evidence](AUDIT-REMEDIATION-WHATSAPP-INBOUND-SLICE.md).

## Retention-independent CAPI delivery (`1.102.69`)

Builder: Shashi. `IDLM_CAPI_Delivery` owns minimal claims/receipts; `IDLM_CAPI` retains current eligibility and provider mapping/transport; `IDLM_Data_Retention` migrates expired success evidence before bounded deletion. Ordinary sends and manual retry share this boundary; accepted events are not replayed for attribution changes. Payload expiry never permits a new ordinary send; prior acceptance and interrupted uncertainty remain independent of logs. Lead deletion/reset erases receipts and same-site restore preserves local ones. Provider acceptance, receipt reconstruction after database loss and exact runtime/artifact qualification remain separate. [Earlier INT-008 evidence](AUDIT-REMEDIATION-CAPI-RETENTION-SLICE.md); see [flows](FLOWS.md) for the current policy.

## Audience work and exclusions — INT-009

Shashi: `IDLM_Audience_Dispatch` owns enqueue/cancel, current per-rule exclusions, committed claims, bounded pages/retries, unknown outcomes, scheduled cursor and recovery cleanup. `IDLM_Audiences` resolves rules and returns adapter outcomes. The shared Leads bulk selector queues/removes manual work; administrator settings process selected IDs only. Google reports mapped rather than provider acceptance. [Policy and evidence](AUDIT-REMEDIATION-AUDIENCE-RETRY-SLICE.md).

## Local unreleased Meta/Interakt lead intake

Builder: Shashi. Direct Page/form lead sync and Interakt contact creation are independently opt-in. Existing inbound upsert and message receipt policy remain authoritative; WhatsApp reporting additionally requires explicit opt-in and original click/Page identifiers. [Configuration and limits](META-LEAD-CONNECTOR.md).

## Facebook Connect for customer installations (local unreleased)

Builder: Shashi. New Facebook sign-in connections use each site's Meta app and Login for Business configuration. `IDLM_Facebook_Direct` owns session-bound consent, code exchange, encrypted local Page tokens, Page/form selection and subscription; `IDLM_Facebook_Connect` retains the AJAX facade and legacy hosted transport; `IDLM_Lead_Connector` owns signed webhook intake and canonical import. Token-based setup and existing hosted connections remain compatible. Public HTTPS and approved Meta access are external prerequisites; see META-LEAD-CONNECTOR.md.

## M6 handoff notification ownership — 2026-10-18

Builder: Shashi. `IDLM_Integrations::send_handoff_notifications()` remains the four-argument priority-10 callback for `idlm_qualified_handoff_notification`. It delegates to `IDLM_Handoff_Notifications::send()`, which owns the opt-in, current lead/recipient lookup, recipient deduplication, subject/body and `wp_mail()` result. `IDLM_Lead_Transaction` and `IDLM_Lead_Outbox` retain commit timing, durable intent, retry and acknowledgement. No routes, selectors, policy or schema change. [Evidence and remaining M6 work](AUDIT-REMEDIATION-HANDOFF-NOTIFICATIONS-SLICE.md).

## M6 connection diagnostic ownership — 2026-10-18

Builder: Shashi. `IDLM_Admin_Connection_Controller::ajax_test_api_connection()` owns the complete `idlm_test_api_connection` handler: shared nonce, administrator capability, typed sanitization, posted/stored credential selection, Meta/LinkedIn request through `IDLM_Secret_Store`, Google credential-format checks and JSON messages. `IDLM_Admin` and `IDLM_Admin_Ajax` retain their existing delegates; JavaScript fields/selectors and action registration are unchanged. [Verification and remaining M6 work](AUDIT-REMEDIATION-CONNECTION-CONTROLLER-SLICE.md).

## M6 telemetry request ownership — 2026-10-20

Builder: Shashi. `IDLM_Admin_Telemetry_Controller` owns `ajax_log_js_error()`, `ajax_clear_telemetry_logs()` and `ajax_download_diagnostic_report()`. Both established facade layers and authenticated action registrations remain callable. Client logging retains the shared nonce; clearing/downloading additionally retain `manage_options`. `IDLM_Telemetry` still owns row normalization/storage, PHP error interception and pruning. The controller preserves the report's aggregate counts, latest 50 logs, text headers and messages. No schema, client or policy change. [Evidence and remaining M6 work](AUDIT-REMEDIATION-TELEMETRY-CONTROLLER-SLICE.md).

## M6 activity request ownership — 2026-10-21

Builder: Shashi. `IDLM_Admin_Activity_Controller` owns `ajax_get_lead_activity()`, `ajax_save_activity_comment()`, `ajax_edit_activity_comment()` and `ajax_delete_activity_comment()`. Both established facade layers and authenticated action registrations are unchanged. `IDLM_Lead_Access_Policy` remains the permission owner through inherited `IDLM_Admin` helpers; `IDLM_Admin_Presenter` still formats timestamps and escapes details. Adds still use `IDLM_DB::log_activity()`; existing prepared timeline/duplicate reads and comment update/delete queries move unchanged. Keep 100 timeline rows, five recent comment/Lost remark/WhatsApp entries, 15-second duplicate suppression and author/admin edit controls. No lifecycle, schema, queue or UI change. [Evidence](AUDIT-REMEDIATION-ACTIVITY-CONTROLLER-SLICE.md).

## Completed M6 boundaries — 1.102.88

Shashi: task transport lives in `IDLM_Admin_Task_Controller`; integration credentials/permissions in `IDLM_Integration_Auth`; feed transport in `IDLM_Google_Feed_Controller`; CAPI provider requests in `IDLM_CAPI_Transport`; conversion CSV data in `IDLM_Google_Conversion_Feed`. Public callbacks retain original signatures. Kanban now returns up to 100 cards plus signed next-page metadata and full filtered stage summaries. [Exit and production evidence](M6-M7-RELEASE-CANDIDATE.md).

## Notification controls — 1.102.89

Builder: Shashi. General settings expose independent existing handoff/routing email preferences and default-off outgoing WhatsApp consent. `IDLM_Settings::notification_enabled()` reads committed allowlisted consent for workers and direct delivery. `IDLM_Interakt_Dispatch` rotates its epoch on outgoing opt-in; ordinary and batch delivery refuse stale epochs, while incoming Interakt webhook authentication remains controlled separately. [Behavior, tests and portable hosting contract](NOTIFICATIONS-AND-HOSTING.md).

## Form-level Meta mapping — 1.102.94

Builder: Shashi. `IDLM_Lead_Connector` owns administrator/nonce-protected question discovery, Page/form identity validation, source-scoped mapping persistence and effective mapping resolution in canonical import. The shared lead-connector template and settings.js render one independent editor per selected form. Existing generic/default mappings, import enablement and encrypted snapshots remain independent. See META-LEAD-CONNECTOR.md.
