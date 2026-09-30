# Feature Map

Use this map to identify the likely blast radius before changing a business feature. Follow links to [FLOWS.md](FLOWS.md), [INTERFACES.md](INTERFACES.md), and [DATA-MODEL.md](DATA-MODEL.md) for detailed behavior and contracts.

## Leads and pipeline

- **PHP:** `IDLM_DB` is the lifecycle/persistence authority; `IDLM_Admin_Leads_Renderer` builds the lead-list and shared-overlay contexts/templates behind `IDLM_Admin` delegates while `IDLM_Admin_Kanban_Renderer` builds the pipeline context/template; `IDLM_Admin_Lead_Reads` owns the extracted lead-detail read/drawer response, while `IDLM_Admin_Ajax` retains the other handlers and applies default manager lead keyset reads using `IDLM_Cursor_Pagination` with offset compatibility for other sorts/queues; `IDLM_Integrations` maps inbound fields and routes ownership; `IDLM_Dashboard` reads aggregates.
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
- **High-risk coupling:** totals, item snapshots, numbering, current revision, idempotency keys and multi-table transactions must be reviewed together. Current “PDF” delivery is browser print/save, not server-generated binary PDF.

## WhatsApp and Interakt

- **PHP:** `IDLM_Interakt` queues/sends outbound tracking events and accepts Interakt/Meta WhatsApp callbacks; `IDLM_DB` emits mapped events; `IDLM_Admin_Lead_Notifications` calculates unread/read state while `IDLM_Admin_Ajax` records manual WhatsApp touches behind facade delegates.
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
- **Shortcodes/UI:** My Account inside `[idlm_workspace]`; administrators also receive Users & Access in that navigation. Visibility affects every CRM and quotation view/action.
- **Settings:** `idlm_reps_see_all_leads`, active pre-sales/sales pools, capacity and studio assignments.
- **High-risk coupling:** capability checks must be paired with object-level lead visibility/ownership; recognized role names are dynamically resolved for compatibility.

## PDFs, print and exports

- **Quotation print/PDF:** `IDLM_Quotations::render_print_view()` via `admin-post.php?action=idlm_print_quote`; requires view/download capabilities, nonce and lead visibility. Uses `assets/css/quotations.css` plus inline print markup/JavaScript and browser “Print / Save PDF.”
- **Lead CSV:** `IDLM_Admin::ajax_export_leads()` with user visibility filtering.
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
