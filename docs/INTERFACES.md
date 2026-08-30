# Interface Inventory

Authentication notation: **CRM** = `manage_options`, `idlm_access_leads`, or recognized CRM role access as implemented by `IDLM_Admin`; **object** = ownership/visibility check; all AJAX actions are logged-in-only and use `idlm_admin_nonce` unless noted.

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
| `GET .../google-ads-audience` and `/google-ads-audience/{list_id}.csv` | `handle_google_ads_audience_feed()` | Same Google-feed credentials | `list_id`; streams matched rule’s hashed audience CSV |
| `GET|POST|DELETE .../mcp` | MCP handlers in `IDLM_Integrations` | `X-IDLM-API-Key` equals the scoped MCP credential | Server info; JSON-RPC single/batch tool calls; session acknowledgment |
| `POST /idlm/v1/interakt-webhook` | `IDLM_Interakt::handle_webhook_request()` | Configured HMAC signature | Interakt inbound payload; appends WhatsApp activity |
| `GET /idlm/v1/meta-webhook` | `handle_meta_webhook_verification()` | Meta verify token | Returns challenge text |
| `POST /idlm/v1/meta-webhook` | `handle_meta_webhook_request()` | `X-Hub-Signature-256` HMAC | Meta WhatsApp payload; appends WhatsApp activity |

The first namespace’s routes use dedicated permission callbacks and retain handler checks as defense in depth. Scoped credentials are encrypted at rest (or supplied by environment constants), independently rotatable, and never accepted in URL/query parameters. `bypass_rest_auth_for_webhook()` overrides global REST authentication errors for those external integration paths so their permission callbacks can evaluate integration credentials.

## AJAX actions

| Actions | Handler area | Authorization and purpose |
|---|---|---|
| `idlm_get_lead_details` | `class-admin.php` → `class-admin-ajax.php` → `class-admin-lead-reads.php` | CRM plus object visibility scoping; returns the established lead drawer JSON/HTML response |
| `idlm_get_lead_activity`, `idlm_get_leads_table`, `idlm_get_kanban_leads`, `idlm_get_dashboard_stats` | `class-admin.php` → `class-admin-ajax.php` | CRM plus visibility scoping; return activity/list/Kanban/dashboard JSON/HTML fragments |
| `idlm_quick_edit_lead`, `idlm_save_lead_details`, `idlm_bulk_action` | `class-admin.php` | CRM + object checks; manager capability for assignment, admin for deletion; update lifecycle |
| `idlm_log_presales_touch`, `idlm_presales_decision` | `class-admin.php` | CRM + assigned-lead/qualification policy; record outreach or qualify/close |
| `idlm_save_activity_comment`, `idlm_edit_activity_comment`, `idlm_delete_activity_comment` | `class-admin.php` | CRM + lead/activity ownership rules; mutate activity comments |
| `idlm_add_lead_manual` | `class-admin.php` | CRM; create manual lead |
| `idlm_export_leads` | `class-admin.php` | CRM/visibility filters; stream CSV |
| `idlm_export_google_conversions` | `class-admin.php` | Administrator; stream Google conversion CSV |
| `idlm_trigger_manual_audience_sync` | `class-admin.php` | Administrator; queue/run audience sync |
| `idlm_test_api_connection`, `idlm_retry_capi_events`, `idlm_clear_telemetry_logs`, `idlm_download_diagnostic_report`, `idlm_save_routing_settings`, `idlm_run_fallback_now`, `idlm_get_mapped_past_leads`, `idlm_sync_mapped_past_batch` | `class-admin.php` | Administrator; integration/diagnostic/routing operations |
| `idlm_log_js_error` | `class-admin.php` | CRM nonce; persists client telemetry |
| `idlm_upload_lead_file` | `class-admin.php` | CRM; creates Media Library attachment and returns URL/ID |
| `idlm_get_total_leads_count`, `idlm_reset_crm_data`, `idlm_delete_lead`, `idlm_import_leads_batch` | `class-admin.php` | Administrator; destructive/count/import operations |
| `idlm_get_lead_tasks`, `idlm_add_lead_task`, `idlm_toggle_task_status`, `idlm_delete_lead_task`, `idlm_get_rep_tasks` | `class-admin.php` | CRM plus lead ownership/visibility; task CRUD/list |
| `idlm_search_quote_catalogue`, `idlm_search_quote_leads` | `class-quotations.php` | Quote search nonce + `idlm_create_quotes`; `IDLM_Quotation_Policy` capability check and lead results scoped by `IDLM_Quotation_Lead_Read_Model` to owner unless manager |
| `idlm_autosave_quote`, `idlm_discard_quote_autosave` | `class-quotations.php` | Quote autosave nonce + draft-edit capability + lead/revision access; transient working copy |

`idlm_get_leads_table` retains its established `page`, `per_page`, filter, sort, HTML, `current_page`, and `total_pages` contract. For the default non-pre-sales queue it may additionally accept a server-issued `cursor` and returns additive `pagination_mode` and `next_cursor` fields. Cursors are opaque, signed, user/filter/access-bound, and short-lived. Custom sorts, pre-sales ordering, and callers that omit a cursor retain page-offset behavior.

## Admin-post actions

- `idlm_update_my_account`: logged-in user + `idlm_update_my_account` nonce; updates own WordPress profile/phone/password or sends reset link.
- `idlm_rotate_api_credential`: `manage_options` plus a scope-specific nonce; rotates the webhook or MCP credential and ends that scope’s legacy grace window.
- `idlm_save_quote`, `idlm_print_quote`, `idlm_save_quote_settings`: quotation-specific nonce and capabilities; transactional save, print HTML, or settings update.
- `idlm_save_catalogue_item`, `idlm_save_catalogue_category`, `idlm_save_catalogue_unit`, `idlm_archive_catalogue_item`, `idlm_bulk_catalogue_items`, `idlm_catalogue_category_action`, `idlm_import_catalogue_csv`, `idlm_export_catalogue_csv`: `idlm_manage_quote_items`, action nonce, validated form/CSV; mutate or export catalogue.
- `IDLM_Quotations::handle_create_revision()` exists as a public PHP method but is not registered by `init()` in the inspected version; treat as dormant/legacy.

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

Static public methods on `IDLM_DB`, `IDLM_CAPI`, `IDLM_Audiences`, `IDLM_Dashboard`, `IDLM_Integrations`, `IDLM_Interakt`, `IDLM_MCP_Core`, and `IDLM_Quotations` are callable after `plugins_loaded`, but only the methods listed in flows/hooks are intentional integration seams. `mcp-server.php` is a CLI-only stdio MCP entry and contains a Local-specific DB host/path fallback. MCP tools are `get_leads_summary`, `query_leads`, `get_lead_details`, `get_reps_performance`, and restricted `run_custom_query`.
