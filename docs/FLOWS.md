# Application Flows

## Inbound lead creation and repeat touch

**Trigger** REST `POST /interior-design-lead-manager/v1/webhook` → **Handler** `IDLM_Integrations::handle_webhook_request()` → **Validation** scoped webhook API key in the route permission callback and handler, rate limit, supported platform, non-empty/test payload → **Business logic** flatten field mappings, merge website attribution cookies, classify source, normalize rooms/property/budget → **Persistence** `IDLM_DB::upsert_inbound_lead()` finds a canonical identity, inserts or merges and records `idlm_lead_touchpoints` → **Side effects** new-lead routing, activities, qualification tasks, stage/CAPI hooks; attribution merges can re-fire prior successful conversion events → **Response** REST success with lead identity/status.

Manual creation and CSV import enter through `IDLM_Admin` AJAX handlers, require admin/CRM permissions as applicable, sanitize mapped fields, and call `IDLM_DB::insert_lead()`. Manual leads remain creator-owned and do not use automatic intake routing.

## Lead update and qualification

**Trigger** quick edit, lead drawer save, Kanban move, pre-sales decision, bulk action, or import → **Handler** corresponding `IDLM_Admin::ajax_*` method → **Validation** `idlm_admin_nonce`, CRM capability, object ownership/manager authority, allowed field/value and structured qualification gates → **Business logic** `IDLM_DB::update_lead()` normalizes stage/quality, disqualification, owner and handoff transitions → **Persistence** lead row plus activities/tasks/timestamps → **Side effects** `idlm_lead_stage_converted`, `idlm_lead_call_status_no_answer`, and configured Interakt hooks; CAPI jobs may be queued → **Response** JSON with message and refreshed counts/data.

Qualification is coupled to follow-up evidence and task completion. Qualified pre-sales leads enter `awaiting_manager`; a manager assignment or timed fallback completes handoff. Unqualification drives `Lost` plus mapped lost/disqualification reason. Duplicate early close has a dedicated policy.

## Assignment and handoff

**Trigger** inbound insert, manager assignment, hourly schedule, or “run fallback now” → **Handler** `route_lead_assignment()` or `run_assignment_fallback()` → **Validation** configured mode/pools, roles, capacity, studio mapping, working-hour threshold; transient prevents overlap → **Business logic** round-robin/source/studio selection or qualified OOO handoff → **Persistence** compare-and-set update in `assign_lead_atomically()` and handoff fields/tasks/activity → **Side effects** optional handoff email; throttled telemetry/admin email for routing exceptions → **Response** background completion or AJAX status.

## Follow-up and WhatsApp

Assignment to pre-sales creates four working-day qualification tasks. Touch logging records call/WhatsApp evidence; no-answer status emits `idlm_lead_call_status_no_answer`, which maps to an Interakt event. Interakt events are queued with Action Scheduler or WP-Cron and posted to Interakt.

Interakt and Meta WhatsApp REST callbacks validate configured HMAC signatures, normalize the sender phone, locate the newest matching lead by phone suffix, and append `whatsapp_reply` activity. Per-user `idlm_whatsapp_read_activity_ids` tracks read state. There is no outbound WhatsApp message endpoint in this code.

## Conversion events

**Trigger** lead stage/quality conversion hook → **Handler** `IDLM_CAPI::queue_capi_event()` → **Validation** configured mapping/enabled platform and prior-success/idempotency rules → **Business logic** schedule `process_capi_event()` → **Persistence** request/response/status in `idlm_capi_logs` → **Side effects** remote POST to Meta CAPI, Google Measurement Protocol, and/or LinkedIn Conversion Events → **Response** asynchronous audit entry. Admins can retry failed logs and export eligible Google offline conversions as CSV.

## Audience sync

**Trigger** daily schedule or admin manual queue → **Handler** `IDLM_Audiences::run_batch_sync()` → **Validation** enabled rules and configured credentials/target → **Business logic** query non-duplicate leads by stage/call/quality rule and merge queued lead IDs → **Persistence** sync status/payload/response log; clear processed queue → **Side effects** hashed identifiers posted to Meta/LinkedIn. Google customer match is exposed as an authenticated CSV feed rather than uploaded by the scheduled method → **Response** audit rows or streamed REST CSV.

## Quotation lifecycle and print/PDF

**Trigger** quotation form/admin-post or AJAX autosave → **Handler** `IDLM_Quotations::handle_save()` / `ajax_autosave_quote()` → **Validation** `IDLM_Quotation_Policy` capability and object-level lead visibility behind facade delegates, nonce, current-revision/edit authority, validated sections/items/decimals and idempotency key → **Business logic** `IDLM_Quotation_Calculator` deterministically normalizes rows and calculates line, wood discount, design fee, additional discount, taxable and GST totals behind `IDLM_Quotations` compatibility delegates → **Persistence** the facade coordinates the checked transaction while `IDLM_Quotation_Repository` performs idempotency and lead-snapshot reads, quote/revision writes, locked revision sequencing, section/item replacement or copying, history, and lead quote-value projection; autosave uses a seven-day transient → **Side effects** quote activity and autosave cleanup → **Response** redirect or JSON.

Lead picker search, initial editor choices, selected-lead prefilling, and object-access existence checks are read through `IDLM_Quotation_Lead_Read_Model`, which applies unrestricted administrator/assignment-manager scope or current representative ownership scope.

Catalogue handlers retain capability/nonce checks, field and uploaded-file validation, the 2,000-row import transaction, CSV formula neutralization, streamed output, redirects, and UI rendering. Catalogue term/item CRUD, bulk/category actions, duplicate-code checks, search/list pagination, and export chunks are delegated to `IDLM_Quotation_Repository`; the same repository supplies the scoped quotation-list count/page read model.

Print uses `admin-post.php?action=idlm_print_quote`, nonce, view/download capabilities, and lead access. It renders escaped HTML with print CSS and a “Print / Save PDF” browser action; the plugin does not generate or store a binary PDF server-side.

## Account update

Authenticated shortcode user submits `admin-post.php?action=idlm_update_my_account` → login and nonce validation → sanitize identity/phone and validate unique email/password → update WordPress user plus `idlm_phone`, optionally send core password-reset email → safe redirect with status.

Anonymous CRM user submits `admin-post.php?action=idlm_login` → nonce and safe-return validation → exact email lookup → WordPress `wp_signon()` authentication/cookie creation → generic failure or local workspace redirect. Password recovery uses the parallel request/reset handlers, generic request responses, WordPress-generated user-bound reset keys, core validation hooks, and core password/session reset behavior.
