# Interface Inventory

Authentication notation: **CRM** = effective `manage_options` or `idlm_access_leads`, without CRM suspension, as implemented by `IDLM_Admin`; **object** = ownership/visibility check; all AJAX actions are logged-in-only and use `idlm_admin_nonce` unless noted.

`idlm_create_user`, `idlm_update_user`, and `idlm_update_role_scopes`: administrator-only admin-post actions with their own nonces. They create or update only managed CRM roles, preserve historical CRM ownership, configure allowlisted role visibility scopes, and reject an administrator self-lockout or removal of the final administrator.

## Shortcodes

| Name | Location | Purpose | Access / output |
|---|---|---|---|
| `[idlm_leads_list]` | `IDLM_Admin::render_leads_list_shortcode()` | Lead table and drawer | CRM; HTML plus shared AJAX assets |
| `[idlm_dashboard]` | `IDLM_Admin::render_dashboard_shortcode()` | Analytics | CRM; HTML/Chart.js |
| `[idlm_kanban]` | `IDLM_Admin::render_kanban_shortcode()` | Pipeline board | CRM; HTML/AJAX |
| `[idlm_workspace]` | `IDLM_Admin::render_workspace_shortcode()` | Composite dashboard/leads/pipeline/quotes/settings shell | CRM; sections filtered by capabilities |
| `[idlm_quotations]` | `IDLM_Quotations::render_shortcode()` | Quotation workspace | Login + `idlm_view_quotes`; HTML |
| `[idlm_payment_structure]` | parsed by `IDLM_Quotations::render_payment_structure_shortcode()` | Expands configured after-quote HTML using current quote totals | Not globally registered; internal print-template token |

## REST API

| Method and route | Handler | Authentication | Input / output / side effects |
|---|---|---|---|
| `POST /interior-design-lead-manager/v1/webhook` | `handle_webhook_request()` | `X-IDLM-API-Key` equals the scoped webhook credential | `platform` plus JSON/form body; upserts/routes lead and returns JSON |
| `GET .../google-ads-conversions[.csv]` | `handle_google_ads_conversions_feed()` | Dedicated HTTP Basic credentials or the scoped token in `X-IDLM-API-Key` | Streams offline conversion CSV; reads leads/CAPI logs |
| `GET .../google-ads-audience` and `/google-ads-audience/{list_id}.csv` | `handle_google_ads_audience_feed()` | Same Google-feed credentials | `list_id`; streams the first enabled matching Google rule’s audience CSV; disabled/removed/unmatched rules return 404 before contact reads; no-store |
| `GET|POST|DELETE .../mcp` | MCP handlers in `IDLM_Integrations` | `X-IDLM-API-Key` equals the scoped MCP credential | Server info; JSON-RPC single/batch tool calls; session acknowledgment |
| `POST /idlm/v1/interakt-webhook` | `IDLM_Interakt::handle_webhook_request()` | Configured HMAC signature | Interakt inbound payload; appends WhatsApp activity |
| `GET /idlm/v1/meta-webhook` | `handle_meta_webhook_verification()` | Meta verify token | Nonempty string parameters; exact enabled verify-token match; escaped plain-text challenge, including `0`; malformed shapes deny 403 |
| `POST /idlm/v1/meta-webhook` | `handle_meta_webhook_request()` | `X-Hub-Signature-256` HMAC | Meta WhatsApp payload; appends WhatsApp activity |

The first namespace’s routes use dedicated permission callbacks and retain handler checks as defense in depth. Scoped credentials are encrypted at rest (or supplied by environment constants), independently rotatable, and never accepted in URL/query parameters. `bypass_rest_auth_for_webhook()` overrides global REST authentication errors for those external integration paths so their permission callbacks can evaluate integration credentials.

## AJAX actions

In 1.101.63, `idlm_upload_lead_file` additionally requires `lead_id` and current mutation authority; pre-sales cannot upload quote-purpose files. `idlm_sync_mapped_past_batch` accepts 1–50 lead IDs, a saved mapped `event_name`, and an 8–80 character alphanumeric/underscore/hyphen `request_id`. Reuse the request ID when retrying a submission. Responses provide `queued_count`, `existing_count`, and `failure_count`; delivery results are recorded asynchronously in lead activity. `idlm_async_interakt_batch_event` processes an opaque job key through Action Scheduler or WP-Cron.

| Actions | Handler area | Authorization and purpose |
|---|---|---|
| `idlm_get_lead_details` | `class-admin.php` → `class-admin-ajax.php` → `class-admin-lead-reads.php` | CRM plus object visibility scoping; returns the established lead drawer JSON/HTML response |
| `idlm_mark_whatsapp_messages_read` | `class-admin.php` → `class-admin-lead-reads.php` | CRM nonce plus lead visibility; marks the selected lead's inbound WhatsApp activity as read for the current user |
| `idlm_get_leads_table`, `idlm_get_kanban_leads`, `idlm_get_dashboard_stats` | `class-admin.php` → `class-admin-ajax.php` | CRM plus visibility scoping; return activity/list/Kanban/dashboard JSON/HTML fragments |
| `idlm_quick_edit_lead`, `idlm_save_lead_details`, `idlm_bulk_action` | `class-admin.php` | CRM + object checks; manager capability for assignment, admin for deletion; update lifecycle |
| `idlm_log_presales_touch`, `idlm_presales_decision` | `class-admin.php` | CRM + assigned-lead/qualification policy; record outreach or qualify/close |
| `idlm_get_lead_activity` | `class-admin.php` → `class-admin-ajax.php` → `class-admin-activity-controller.php` | Shared nonce, CRM access and lead visibility; returns timeline and recent-comment HTML |
| `idlm_save_activity_comment`, `idlm_edit_activity_comment`, `idlm_delete_activity_comment` | `class-admin.php` → `class-admin-ajax.php` → `class-admin-activity-controller.php` | CRM + lead/activity ownership rules; mutate activity comments |
| `idlm_add_lead_manual` | `class-admin.php` | CRM; create manual lead |
| `idlm_export_leads` | `class-admin.php` → `IDLM_Admin_Ajax` | `manage_options` and admin nonce; existing visibility/GET filters; 40-column CSV with shared text-cell formula protection and numeric ID/quote value |
| `idlm_export_google_conversions` | `class-admin.php` | Administrator; stream Google conversion CSV |
| `idlm_trigger_manual_audience_sync` | `class-admin.php` | Administrator; queue/run audience sync |
| `idlm_get_capi_log_payload`, `idlm_get_audience_log_payload` | `class-admin.php` → `class-admin-logs-renderer.php` | Administrator; nonce-protected on-demand reads of one redacted request/response pair |
| `idlm_test_api_connection`, `idlm_retry_capi_events`, `idlm_save_routing_settings`, `idlm_run_fallback_now`, `idlm_get_mapped_past_leads`, `idlm_sync_mapped_past_batch` | `class-admin.php` | Administrator; integration/diagnostic/routing operations |
| `idlm_log_js_error` | `class-admin.php` → `class-admin-ajax.php` → `class-admin-telemetry-controller.php` | Shared CRM nonce; persists client telemetry |
| `idlm_clear_telemetry_logs`, `idlm_download_diagnostic_report` | `class-admin.php` → `class-admin-ajax.php` → `class-admin-telemetry-controller.php` | Shared nonce plus `manage_options`; clears telemetry or downloads the text report |
| `idlm_upload_lead_file` | `class-admin.php` | CRM; creates one validated Media Library attachment and returns URL/ID. The floor-plan client may call it sequentially for a bounded multi-file selection. |
| `idlm_get_total_leads_count`, `idlm_reset_crm_data`, `idlm_delete_lead`, `idlm_import_leads_batch` | `class-admin.php` | Administrator; destructive/count/import operations |
| `idlm_get_lead_tasks`, `idlm_add_lead_task`, `idlm_toggle_task_status`, `idlm_delete_lead_task`, `idlm_get_rep_tasks` | `class-admin.php` | CRM plus lead ownership/visibility; task CRUD/list |
| `idlm_search_quote_catalogue`, `idlm_search_quote_leads` | `class-quotations.php` | Quote search nonce + `idlm_create_quotes`; `IDLM_Quotation_Policy` capability check and lead results scoped by `IDLM_Quotation_Lead_Read_Model` to owner unless manager |
| `idlm_autosave_quote`, `idlm_discard_quote_autosave` | `class-quotations.php` | Quote autosave nonce + draft-edit capability + lead/revision access; transient working copy |

`idlm_get_leads_table` retains its established `page`, `per_page`, filter, sort, HTML, `current_page`, and `total_pages` contract. For the default non-pre-sales queue it may additionally accept a server-issued `cursor` and returns additive `pagination_mode` and `next_cursor` fields. Cursors are opaque, signed, user/filter/access-bound, and short-lived. Custom sorts, pre-sales ordering, and callers that omit a cursor retain page-offset behavior.

`idlm_get_kanban_leads` accepts an optional `sort` value of `newest`, `oldest`, `name_asc`, or `name_desc`. The sales-board response omits the persisted New and Contacted lifecycle stages; those stages remain available to other lead workflows.

## Admin-post actions

- `idlm_login`: anonymous, nonce-protected CRM email/password sign-in; exact email lookup followed by WordPress core `wp_signon()` and a safe local redirect.
- `idlm_request_password_reset`: anonymous, nonce-protected email request with a generic response and hashed short-lived throttle; WordPress generates the reset key and sends the CRM reset URL.
- `idlm_reset_password`: anonymous, nonce-protected reset completion; requires at least eight characters, then WordPress validates the user-bound key and password hooks before resetting sessions/password.
- `idlm_update_my_account`: logged-in user + `idlm_update_my_account` nonce; updates own WordPress profile/phone/password or sends reset link.
- My Account Sign Out uses WordPress core's nonce-protected `wp_logout_url()` and returns to the CRM page; no plugin logout handler is registered.
- `idlm_rotate_api_credential`: `manage_options` plus a scope-specific nonce; rotates the webhook or MCP credential and ends that scope’s legacy grace window.
- `idlm_export_backup`: `manage_options` plus `idlm_export_backup` nonce; streams a JSON export of IDLM custom tables and non-secret settings.
- `idlm_restore_backup`: `manage_options` plus `idlm_restore_backup` nonce, a validated JSON upload smaller than 100 MB, and an exact `RESTORE` confirmation; atomically replaces IDLM custom-table data and non-secret settings, preserves current credentials, and clears queued outbound jobs.
- `idlm_save_quote`, `idlm_print_quote`, `idlm_download_quote_pdf`, `idlm_download_quote_csv`, `idlm_download_quote_xlsx`, `idlm_save_quote_settings`: quotation-specific nonce and capabilities; transactional save, print HTML, exact-revision PDF/CSV/XLSX download, or settings update. Every download requires `idlm_view_quotes`, `idlm_download_quote_pdf`, nonce validation, revision-to-quote ownership, and scoped lead visibility. Existing quotes use this single revision path and require draft-edit plus revision capability; no separate create-revision action is registered.
- `idlm_save_catalogue_item`, `idlm_save_catalogue_category`, `idlm_save_catalogue_unit`, `idlm_archive_catalogue_item`, `idlm_bulk_catalogue_items`, `idlm_catalogue_category_action`, `idlm_import_catalogue_csv`, `idlm_export_catalogue_csv`: `idlm_manage_quote_items`, action nonce, validated form/CSV; mutate or export catalogue.

## Hooks and schedules

| Hook | Producer → consumer | Purpose |
|---|---|---|
| `idlm_lead_stage_converted` | `IDLM_DB`/import → `IDLM_CAPI::queue_capi_event` | Queue mapped conversion event |
| `idlm_lead_attribution_merged` | `IDLM_DB` → `IDLM_CAPI::handle_attribution_merged` | Re-fire prior conversion with improved attribution |
| `idlm_lead_call_status_no_answer` | `IDLM_DB` → `IDLM_Interakt::trigger_no_answer_automation` | Trigger no-answer message event |
| `idlm_trigger_interakt_event` | `IDLM_DB` → `IDLM_Interakt::trigger_generic_event` | Configured lifecycle message event |
| `idlm_async_capi_upload_event` | scheduled by CAPI → `process_capi_event` | Background platform dispatch |
| `idlm_async_interakt_track_event` | scheduled by Interakt → `process_async_track_event` | Background Interakt dispatch |
| `idlm_daily_audience_sync` / `_cron` | scheduler → audiences + telemetry | Daily sync/prune |
| `idlm_hourly_assignment_fallback` / `_cron` | scheduler → integrations + DB | Assignment fallback and task rollover |

No plugin-defined extension filters, custom post types, WP-CLI commands, or outbound webhook dispatcher were found.

## Public PHP / third-party entry points

Static public methods on `IDLM_DB`, `IDLM_CAPI`, `IDLM_Audiences`, `IDLM_Dashboard`, `IDLM_Integrations`, `IDLM_Interakt`, `IDLM_MCP_Core`, and `IDLM_Quotations` are callable after `plugins_loaded`, but only the methods listed in flows/hooks are intentional integration seams. `mcp-server.php` is a CLI-only stdio MCP entry and supports explicit WordPress-loader/error-log environment overrides. The selected loader initializes ABSPATH. MCP tools are `get_leads_summary`, `query_leads`, `get_lead_details`, `get_reps_performance`, and restricted `run_custom_query`.

MCP customer/profile tools require an explicit PII grant in addition to their tool grants. Custom SELECTs compile through bounded table/field grammar and safe representative projection; callback names, SQL argument and HTTP/stdio envelopes remain. See [current MCP contracts and compatibility](AUDIT-REMEDIATION-MCP-SLICE.md).

## Private lead files — 1.102.47

Builder: Shashi. Existing `idlm_upload_lead_file` callback/action and `url`/`attachment_id` payload remain; owner or explicit assignment capability authorizes upload independently of read-all. Read-only `admin_post[_nopriv]_idlm_download_lead_file` uses cookie authentication and live lead scope, scalar attachment identity, generic denials and no-store headers. No download nonce is required. `idlm_private_file_maintenance` and `wp idlm integrity files` run bounded migration/reconciliation/cleanup. JSON export declares private-file exclusions; JSON restore denies private files/pointers before clearing data. See [operational and verification limits](AUDIT-REMEDIATION-PRIVATE-FILE-SLICE.md).

### Recovery compatibility (1.102.48)

Shashi: existing backup callbacks, nonce/form fields, JSON version 1 and redirects remain stable. Complete same-site legacy v1 payloads have strict current-schema/user validation. Internal `idlm_async_capi_upload_event` appends optional third generation; `idlm_async_interakt_track_event` appends optional fourth generation. Old queued callbacks require epoch zero; live synchronous operations retain their original arguments. Batch job options add generation without changing their key callback. [Details](AUDIT-REMEDIATION-RESTORE-SLICE.md).

## Identity review utility (1.102.54)

Shashi: native `admin.php?page=idlm_identity_reviews` supports status filters and an `after` ID cursor (25 rows). `admin_post_idlm_resolve_identity_review` is POST-only and requires live assignment-manager access plus a nonce bound to actor/review/recovery generation. Inputs: `review_id`, `decision` (matched/separate/rejected), candidate `lead_id` for matching, and required `note` (≤1000 bytes). Success redirects to the utility; invalid/stale/persistence errors deny without mutation. `IDLM_Lead_Lifecycle_Service::resolve_inbound_review()` checks authority and owns canonical resolution; `IDLM_Identity_Review` owns presentation/replay/erasure/retention. New continuation hook `idlm_prune_identity_reviews` has no PII arguments. [Policy](AUDIT-REMEDIATION-IDENTITY-REVIEW-SLICE.md).

## Bounded retention and reads (`1.102.55`)

Shashi: retention runs one checked 100-row batch with transactional `idlm_retention_progress`, recovery/session fencing, fair target rotation, continuation hook `idlm_continue_sensitive_retention`, and operational failure status. Task responses use 50-card signed keysets and scoped totals (`counts_only` skips cards); queue qualification history reads 100 dates at a time. WhatsApp filters/counts stay in SQL; compatibility ID helpers page 100 by `after`. Lead CSV stages 100-row reads, capped at 10,000 rows/32 MiB/20 seconds of application work, before headers. Core/routing/quotation schemas unchanged. [Policy, verification, API details and remaining production gates](AUDIT-REMEDIATION-BOUNDED-WORK-SLICE.md).


## Quotation schema completion contract (1.102.57)

Shashi: `IDLM_Quotations::install()` and `IDLM_Quotation_Schema::install()` return `true`/`WP_Error`; compatibility migration delegates propagate the same checked result. `IDLM_Quotation_Schema::is_current()` reads committed schema/substep/status receipts, including `idlm_quotation_migration_status=ready`. Pending quotation migration participates in the existing CRM boot 503/private-administrator-notice boundary. No AJAX action, route, nonce or capability was renamed. [Policy and evidence](AUDIT-REMEDIATION-QUOTATION-MIGRATION-SLICE.md).


## Ordinary Interakt jobs — 1.102.65

Builder: Shashi. `idlm_async_interakt_track_event` now receives one opaque job key. Existing four-argument payload/key callbacks are refused. `idlm_trigger_interakt_event` accepts an optional fourth intended-rule/owner context; `idlm_lead_call_status_no_answer` accepts an optional third context including the original event name. Existing hook names and shorter caller signatures remain. Canonical outbox handoff supplies this context, and exposes its current durable ID to the dispatcher. [Delivery, cancellation and reconciliation contracts](AUDIT-REMEDIATION-INTERAKT-DISPATCH-SLICE.md).


## CAPI manual retry contract (INT-005, Shashi)

`idlm_retry_capi_events` retains the existing administrator nonce and `manage_options` checks. It accepts 1–50 scalar positive decimal log IDs, validates the entire selection before dispatch and deduplicates IDs. Its existing `data.message` reports actual new attempts; additive `data.attempts` entries contain `source_log_id`, `log_id`, `attempt_id` and `success`. Prior success is a refusal, never evidence of a new attempt. `IDLM_CAPI::retry_capi_log()` returns the exact attempt result; existing three-argument `fire_*_capi()` methods now return the same typed result. `IDLM_DB::log_capi_event()` returns the inserted log ID or zero. Forced attribution bypasses prior-success deduplication but shares current eligibility checks. [Identity, retry and compatibility policy](AUDIT-REMEDIATION-CAPI-RETRY-SLICE.md).


## WhatsApp inbound receipt contract (INT-006, Shashi)

Existing Meta/Interakt REST paths and HMAC headers remain. Current primary enablement/secret reads precede parsing. Signed Meta payloads iterate all `entry[].changes[].value.messages[]`; status-only changes produce no reply. Interakt `message_received` uses documented `data.message.id`. Responses add `processed`, `replayed`, `failed`: 200 only if every relevant message is durably handled or already handled; 400 for malformed identity/content, 413 for over 1 MiB/100 messages, 409 for unmatched/ambiguous phone or changed-ID content, 503 for busy/unverified/failed persistence. Complete validation precedes writes; partial committed messages remain replay-safe. Public reply helpers accept a third provider message ID and refuse the old ID-less call. [Compatibility and remaining qualification](AUDIT-REMEDIATION-WHATSAPP-INBOUND-SLICE.md).

## Audience queue dispatch — INT-009

Shashi: existing `idlm_bulk_action` adds `bulk_audience_cancel` alongside `bulk_audience_queue`, using its nonce and per-lead access checks, with 100-ID dispatch-owner limits. Existing administrator `idlm_trigger_manual_audience_sync` returns selected-only counts (`accepted`, `mapped`, `excluded`, `failed`, `uncertain`, `pending`, `pages`, `mode`); busy/persistence errors return HTTP 409. `idlm_audience_continue(generation, manual_only, UTC_day)` resumes checked work under the recovery owner; stale epochs/days do not send. Daily hooks call scheduled mode. [Policy](AUDIT-REMEDIATION-AUDIENCE-RETRY-SLICE.md).

## Local unreleased direct Meta lead route

Builder: Shashi. `GET /idlm/v1/meta-leads` requires enabled sync, exact verify token and scalar numeric challenge; it serves a plain-text challenge. `POST` requires the enabled connector and SHA-256 HMAC before bounded Page/leadgen parsing, accepts only the configured Page/form IDs, and returns 200 only after all accepted notifications are durably retained. The REST-auth bypass adds only this exact route; its permission callbacks enforce authentication. Existing signed Interakt POST optionally queues normalized incoming messages when contact capture is enabled. The administrator-only `idlm_test_lead_connector` AJAX action requires `idlm_admin_nonce` and reads saved Page/app/first-form access without provider mutations. [Full contract](META-LEAD-CONNECTOR.md).

## Hosted Facebook connection interfaces (local unreleased)

Builder: Shashi. Authenticated `wp_ajax_idlm_facebook_connect` requires `manage_options` and `idlm_admin_nonce`; operations start, pages, forms, select and disconnect return metadata only. The protected installation client signs `/api` requests with site ID, timestamp, nonce and body HMAC and verifies signed responses. Hosted public endpoints are `/oauth/callback` (single-use state), `/meta/webhook` (Meta challenge/HMAC), `/meta/deauthorize` and `/meta/delete-data` (Meta signed requests), and opaque `/deletion/status` receipts. Browser selection is validated against current consent grants. Import and acknowledgement are scoped to the installation and current connection. Full contract and provisioning: services/facebook-connect/README.md.

Manual Meta webhook setup: the GET verification challenge may succeed while sync is off when a Page ID and matching verification token are saved. This supports setup before activation. Hosted mode still rejects the local callback, malformed/mismatched verification fails, and signed POST intake still requires enabled sync. Builder: Shashi, local unreleased, 2026-10-17.

## Connection-test compatibility chain — M6

Builder: Shashi. Authenticated `wp_ajax_idlm_test_api_connection` remains registered to `IDLM_Admin::ajax_test_api_connection()`, which delegates through `IDLM_Admin_Ajax` to `IDLM_Admin_Connection_Controller`. The controller owns the existing `idlm_admin_nonce`/`nonce` and `manage_options` checks before credentials or provider calls. Fields `platform`, `test_type`, `target_id`, `access_token`, `google_gadm_username` and `google_gadm_password`, JSON success/error envelopes and status behavior remain unchanged. No anonymous action is registered. [Slice evidence](AUDIT-REMEDIATION-CONNECTION-CONTROLLER-SLICE.md).

## Telemetry compatibility chain — M6

Builder: Shashi. The three `wp_ajax_idlm_log_js_error`, `wp_ajax_idlm_clear_telemetry_logs` and `wp_ajax_idlm_download_diagnostic_report` registrations still target `IDLM_Admin`, delegating through `IDLM_Admin_Ajax` to `IDLM_Admin_Telemetry_Controller`. Client logging retains POST fields `message`, `file`, `line`, `col`, `stack` and `nonce`; it does not acquire a new capability guard in this extraction. Clearing retains POST nonce plus `manage_options` and the existing JSON message. Download retains GET nonce plus `manage_options`, 403 text denials, text/plain attachment headers, environment/settings/count sections and at most 50 recent entries. No anonymous callback is added. [Slice evidence](AUDIT-REMEDIATION-TELEMETRY-CONTROLLER-SLICE.md).

## Activity compatibility chain — M6

Builder: Shashi. Existing `wp_ajax_idlm_get_lead_activity`, `wp_ajax_idlm_save_activity_comment`, `wp_ajax_idlm_edit_activity_comment` and `wp_ajax_idlm_delete_activity_comment` still register to `IDLM_Admin` and delegate through `IDLM_Admin_Ajax` to `IDLM_Admin_Activity_Controller`. Preserve `idlm_admin_nonce`/`nonce`, POST `lead_id`/`log_id`/`comment`, JSON envelopes/messages and the `html`/`comments_html` keys. Reads require representative access and lead visibility; mutations require current lead mutation authority, with author or administrator authority additionally required for edit/delete. Only `comment` rows are editable/deletable. No anonymous action is added. The existing timeline/edit/delete selectors and client refresh requests remain unchanged. [Characterization, bounds and error evidence](AUDIT-REMEDIATION-ACTIVITY-CONTROLLER-SLICE.md).

## 1.102.88 owner and paging contracts

Shashi: existing route/AJAX registrations and old PHP facade signatures are unchanged. Integrations delegate authentication to `IDLM_Integration_Auth`, intake to `IDLM_Webhook_Intake` and Google feed responses to `IDLM_Google_Feed_Controller`. CAPI delegates provider work to `IDLM_CAPI_Transport` and conversion CSV data to `IDLM_Google_Conversion_Feed`. Admin_Ajax delegates tasks to `IDLM_Admin_Task_Controller` and collection reads to `IDLM_Admin_Collection_Reads`.

`idlm_get_kanban_leads` accepts an optional opaque `cursor`. Each response retains `leads`, `stats`, `page` (`offset`, `size`, `has_more`, `next_cursor`) and `stage_summaries` keyed by stage (`count`, `quote_value`, `deal_value`). Legacy callers receive at most 100 cards across the board. The shared pipeline client uses `per_column=1`: initial responses contain at most twenty cards per configured sales stage and `stage_pages` keyed by stage with the same page fields. A continuation supplies `column_stage` and that stage's token, returning at most twenty cards for that column only. Column tokens additionally bind stage and batch size. All cursors bind the current actor, live visibility, filters and sort; malformed/mismatched tokens return 400 before card reads. Each column scrolls independently and appends on approaching the bottom, with accessible load/retry controls. Full totals remain independent of loaded cards. Offset paging reflects live data and does not promise a frozen snapshot while leads change; the client deduplicates appended IDs.

REST rate budgets are charged once per actual WP_REST_Request, including core Allow-header rechecks, through server-owned request attributes. Credential checks run each time; client body/query fields cannot supply the memo. Network activation rejects before any migration or data write; the zero-argument activation callback remains compatible.

Legacy CRM wp-admin URLs are checked at `admin_menu` priority zero as well as the preserved `admin_init` callback. Core validates plugin-page registration before `admin_init`; early redirection avoids its unregistered-page error while preserving the existing page/capability map, pre-sales destination, local workspace target and sanitized query allowlist. The actual route acceptance probe uses WordPress admin core, not a substitute router.

## Direct Facebook consent — local unreleased, 2026-10-27

Builder: Shashi. The existing `wp_ajax_idlm_facebook_connect` action retains administrator plus `idlm_admin_nonce` protection and `start/pages/forms/select/disconnect` operations. New attempts use the site's Meta app directly. `admin_post_idlm_facebook_callback` accepts Facebook's GET `code/state` response only for the authenticated initiating administrator/session, with expiring one-use state and recovery/app/epoch fencing. It exchanges codes server-side, then redirects to the same-origin saved settings URL with `idlm_fb_return=1`; errors render a generic no-cache response. There is no anonymous callback registration. The existing signed `/idlm/v1/meta-leads` webhook and importer contracts are unchanged. [Configuration and migration](META-LEAD-CONNECTOR.md).

## Meta form mapping — 1.102.94

Builder: Shashi. `wp_ajax_idlm_meta_form_mapping` in `IDLM_Lead_Connector::ajax_form_mapping()` requires manage_options plus idlm_admin_nonce. `load` accepts a scalar saved form_id and returns verified form question keys/labels/types, allowlisted CRM destinations and the effective mapping. `save` accepts bounded JSON mapping rows, rechecks form/Page ownership and current source, rejects unsupported/duplicate targets or missing contact mapping and persists one source-scoped setting. It never reads respondent answers, modifies lifecycle state or enables import. No anonymous action/REST route was added.

## Catalogue CSV preview/import — 1.102.97, 2026-10-27

Builder: Shashi. Authenticated `wp_ajax_idlm_import_catalogue_csv` requires `idlm_manage_quote_items` and `_wpnonce` for `idlm_import_catalogue_csv`. Multipart `catalogue_csv` uses the existing 5 MB CSV upload checks; scalar `import_mode` is `preview` or `import`. Preview returns `total`, `valid`, `invalid`, `duplicates`, `generated_codes` and bounded `rows` with CSV record number, state, messages and sanitized catalogue values. Import revalidates the uploaded file/current codes, adds `imported` and changes successful row states to `imported`; persistence failures return a safe specific error and roll back all writes. CSV records are numbered from header row 1, including records with quoted multiline descriptions. Legacy admin-post import remains available. Neither endpoint accepts browser-supplied parsed rows or stores preview files.
