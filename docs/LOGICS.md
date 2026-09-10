# Verified Runtime Logic Map

Author: Shashi  
Verified: 2026-09-09  
Scope: static source-and-documentation audit only; no plugin PHP, JavaScript, schema, settings, or runtime data was changed.

## Method and confidence

Graphify was queried with: **“Map the end-to-end plugin flow: entry points, lifecycle state transitions, persistence, hooks, integrations, permissions, failures, and redundant logic.”** It identified `IDLM_Admin`, `IDLM_Integrations`, `IDLM_Quotations`, `IDLM_Quotation_Repository`, `IDLM_Admin_Ajax`, and `IDLM_DB` as the core hubs. The graph report records 1,326 nodes and 1,494 edges; every runtime relationship below was then checked against the listed source location and the linked project contract. Graphify is an index, not evidence.

`docs/FLOWS.md` is the human lifecycle overview. This file is its source-verified companion and the change-impact index.

## Bootstrap and entry points

| Entry | Verified chain | Guard / failure boundary | Source |
|---|---|---|---|
| Plugin activation | `idlm_activate_plugin()` registers roles/capabilities, creates core/routing/quotation schema, then schedules work. | WordPress activation failure stops the install path; schema is versioned separately from the release version. | `interior-design-lead-manager.php:76-204`; `includes/class-db.php:12-63`; `docs/ARCHITECTURE.md` |
| Normal request boot | `plugins_loaded` → `idlm_boot_plugin()` → settings, DB, retention, integrations, CAPI, Interakt, scheduler, admin, quotations. | All module registrations occur during boot; no request mutation is implied by registration. | `interior-design-lead-manager.php:214-244` |
| Admin and workspace UI | `IDLM_Admin::init()` registers menus, shortcodes, admin-post handlers, and every authenticated AJAX action; facade delegates into focused controllers/readers/renderers. | WordPress login, nonce and capability/object checks are enforced by the target handler rather than by registration. | `includes/class-admin.php:9-62`; `docs/PROJECT-MAP.md` |
| Public inbound API | REST registration exposes CRM webhook, Google feeds, and MCP routes; Interakt separately exposes Interakt/Meta WhatsApp routes. | CRM webhook/feed/MCP permission callbacks authenticate before handlers; Interakt/Meta use public callbacks only because their handlers verify their provider signature/token. | `includes/class-integrations.php:110-173,344-389`; `includes/class-interakt.php:223-245`; `docs/INTERFACES.md`; `docs/SECURITY.md` |
| Background work | Scheduler binds Action Scheduler hooks, with WP-Cron fallback, to audiences, telemetry prune, assignment fallback, and task roll-forward. CAPI and Interakt use one-off asynchronous hooks. | Scheduler failure means delayed work; workers re-check state where implemented. | `includes/class-scheduler.php:9-52`; `includes/class-capi.php:220-307`; `includes/class-interakt.php:7-19` |
| Frontend authentication | Anonymous `admin-post` login/reset handlers use WordPress authentication/reset primitives; authenticated account update is registered by admin. | Nonces, safe redirects, generic reset responses, reset-key validation, and password policy prevent direct state changes on invalid input. | `includes/class-auth.php:12-105`; `includes/class-admin-account-controller.php:38+`; `docs/FLOWS.md` |

## Canonical lead lifecycle

### State model

Persisted stage labels are `New → Contacted → Qualified → Visited Studio → Quote Shared → Negotiation → Won`, with `Lost` terminal. Quality is empty, `Qualified`, or `Unqualified`; the latter normalizes stage to `Lost`. Handoff is `not_ready`, `awaiting_manager`, `manually_assigned`, `auto_assigned`, or `exception`. The registry is the stable-label authority; custom stage labels are additionally allowed by the DB validator.

Source: `includes/class-state-registry.php:7-82`; `includes/class-db.php:186-260`; `docs/DATA-MODEL.md`; `docs/FLOWS.md`.

### Inbound creation and repeat touch

```text
POST CRM webhook
  → permission_webhook(): scoped key + rate limit
  → handle_webhook_request(): platform/payload validation, field mapping,
    attribution merge, source/property/room/budget normalization
  → IDLM_DB::upsert_inbound_lead()
  → IDLM_Lead_Lifecycle_Service::upsert_inbound_lead()
     ├─ exact platform/external identity: canonical update + touchpoint
     ├─ one contact identity: canonical update + touchpoint
     ├─ conflicting/multiple identities: actionable duplicate-review result
     └─ no match: insert_lead() + touchpoint + automatic routing
```

`insert_lead()` defaults the lifecycle, rejects invalid initial qualification, performs manual/import duplicate policy, inserts `idlm_leads`, logs creation/reporting/assignment events, may merge attribution to a canonical record, emits attribution and initial-stage hooks, and invalidates lead reads. Inbound upsert keeps repeated external IDs idempotent while submissions without external IDs remain individual touches.

Writes: `idlm_leads`, `idlm_lead_touchpoints`, `idlm_lead_activity_log`, `idlm_lead_state_events`; attribution can update the canonical lead.  
Hooks: `idlm_lead_attribution_merged`, `idlm_lead_stage_converted`.  
Sources: `includes/class-integrations.php:578-646,648-960`; `includes/class-lead-lifecycle-service.php:14-267,268-496`; `includes/class-db.php:1004-1095`; `docs/FLOWS.md`.

### User mutation, qualification, and audit effects

All normal lead edits route through `IDLM_DB::update_lead()` to `IDLM_Lead_Lifecycle_Service::update_lead()`. It reads the old row, normalizes/validates cross-field state, timestamps assignment/qualification/disqualification/conversion, reconciles handoff state, calculates timing metrics, writes the lead, records changed fields as activities, writes immutable reporting events, emits conversion/Interakt hooks, and invalidates cached read models.

```text
AJAX quick edit / detail save / Kanban / pre-sales decision / bulk action
  → idlm_admin_nonce + role/capability + object access
  → field/domain validation and workflow normalization
  → update_lead()
  → idlm_leads + activity log + state-event log
  → conversion and Interakt hooks; read-cache invalidation
```

Qualification requires project details and the minimum budget, promotes stage to `Qualified`, creates/updates qualification state and handoff state, and pairs with task/follow-up evidence. Unqualification is forced to `Lost` with a reason. `Won` gets conversion time; qualified sales states imply qualified quality. The initialization repair routine corrects legacy inconsistent rows and removes orphan tasks.

Sources: `includes/class-admin.php:23-62`; `includes/class-admin-ajax.php:135-472,1460-1594,2531-2641`; `includes/class-lead-access-policy.php:16-113`; `includes/class-db.php:112-288,947-1062`; `includes/class-lead-lifecycle-service.php:498-836`; `docs/FLOWS.md`; `docs/SECURITY.md`.

### Assignment, handoff, tasks, and fallback

Inbound routing chooses a configured pre-sales/sales/source/studio pool subject to capacity and round-robin state. `assign_lead_atomically()` compares the owner it observed before updating ownership, logs assignment/state event, invalidates reads, creates pre-sales tasks or reconciles qualified handoff tasks, and may email handoff recipients. The hourly job handles unassigned recovery, qualified fallback, lock/error reporting, and task roll-forward.

Writes: `idlm_leads`, `idlm_tasks`, `idlm_lead_activity_log`, `idlm_lead_state_events`, `idlm_routing_state`; routing status/error options.  
Failures: a failed compare-and-set simply abandons that assignment attempt; pool/capacity/fallback errors are recorded and throttled for notification.  
Sources: `includes/class-integrations.php:977-1223,1261-1585`; `includes/class-db.php:611-947`; `includes/class-routing-state.php`; `includes/class-scheduler.php:12-21`; `docs/FLOWS.md`.

## Integrations and external effects

| Flow | Trigger → effects | Persistence / failure controls | Source |
|---|---|---|---|
| CAPI | `idlm_lead_stage_converted` → `IDLM_CAPI::queue_capi_event()` → synchronous `New` delivery or five-minute Action Scheduler/WP-Cron event → Meta, Google MP, LinkedIn HTTP POSTs. | CAPI logs plus activities; state re-check and duplicate suppression occur at worker execution; idempotency check protects already-sent events. | `includes/class-capi.php:7-19,92-307,312-644`; `includes/class-db.php:1116-1134` |
| Attribution re-fire | Canonical attribution merge emits `idlm_lead_attribution_merged`; CAPI determines whether eligible events need reprocessing. | Existing success/idempotency rules apply. | `includes/class-lead-lifecycle-service.php:450-485`; `includes/class-capi.php:19-91` |
| Interakt tracking | Call/status/field mapping emits `idlm_trigger_interakt_event`; no-answer fallback emits `idlm_lead_call_status_no_answer`; Interakt queues/executes provider event POST. | Activities/audit where provided; disabled/missing configuration stops delivery. | `includes/class-lead-lifecycle-service.php:794-828`; `includes/class-interakt.php:7-222` |
| WhatsApp inbound | Provider callback → enabled/configured check → HMAC or verification-token validation → parse sender/message → newest phone-suffix lead → `whatsapp_reply` activity. | Invalid signatures: 401; disabled/missing config: 403; invalid body: 400. No general outbound-chat endpoint exists. | `includes/class-interakt.php:223-475`; `docs/FLOWS.md` |
| Audience sync | Daily/manual queue → enabled rules → lead selection → Meta or LinkedIn hashed upload; Google remains a protected CSV feed. | Sync log records provider result; queue is an option. See confirmed issue L-01 below. | `includes/class-audiences.php:10-331`; `includes/class-integrations.php:516-576`; `includes/class-db.php:1135-1152` |
| Notifications | Assignment/handoff routing can call `wp_mail()`. | Opt-in settings, recipient de-duplication, and exception-notification throttling. | `includes/class-integrations.php:1229-1307` |
| MCP | REST MCP routes authenticate using a scoped key and delegate JSON-RPC/read-only tools to MCP core. CLI server loads WordPress then the same core. | Disabled/missing/invalid credential returns REST error; SQL is policy-limited by MCP core. | `includes/class-integrations.php:161-173,1644-1846`; `includes/class-mcp-core.php`; `mcp-server.php`; `docs/SECURITY.md` |

## Quotations and catalogue

```text
admin-post save / revision request
  → quotation capability + nonce + lead object visibility
  → validate current revision, idempotency key, dates/sections/items/decimals
  → calculator normalizes items and totals
  → SQL transaction: quote + revision + sections + items + history
     + idlm_leads.quote_value projection
  → COMMIT → cache invalidation + activity + redirect
```

Quotation number reservation and revision sequencing lock their sequence/quote rows. The repository owns table access; the controller rolls back on any failed write. Autosave is transient-only. Print is a nonce/capability/lead-access-guarded HTML browser-print endpoint, not server-side PDF generation. Catalogue CRUD/import/export uses the same policy/nonce boundaries, upload validation, transaction handling, and CSV formula neutralization.

Writes: `idlm_quotes`, `idlm_quote_revisions`, `idlm_quote_revision_sections`, `idlm_quote_revision_items`, `idlm_quote_status_history`, `idlm_number_sequences`, catalogue tables, and the denormalized `idlm_leads.quote_value`.  
Sources: `includes/class-quotations.php:14-207`; `includes/class-quotation-controller.php:13-630`; `includes/class-quotation-repository.php:19-310,314-800`; `includes/class-quotation-policy.php`; `includes/class-quotation-schema.php:37-238`; `docs/FLOWS.md`; `docs/DATA-MODEL.md`.

## Authorization and failure matrix

| Surface | Authentication/authorization | Object scope | Main failure response |
|---|---|---|---|
| CRM AJAX | `idlm_admin_nonce`; capability/role checks | `IDLM_Lead_Access_Policy`: managers/assigners broadly; reps/ pre-sales owner/history scope | `wp_send_json_error()` / `wp_die()` |
| Lead file upload, reset, users, settings, telemetry | Nonce + administrator or specific CRM capability | Target-specific | JSON error or 403 |
| Quotation admin-post/AJAX | Quotation capability + nonce | `IDLM_Quotation_Policy` and lead visibility | Redirect with error or 403 |
| CRM webhook, Google feeds, MCP | Header scoped API credential + rate limit; MCP enabled state | Service scope | `WP_Error` / REST 401/429/disabled error |
| Interakt/Meta callback | Handler validates enabled config and HMAC/token | Phone match only after validation | REST 401/403/400 |
| Account/auth flows | Admin-post nonce; WordPress sign-on/reset-key controls | Own account | Safe redirect, generic reset result |

Sources: `includes/class-admin-ajax.php:13-3229`; `includes/class-lead-access-policy.php:1-113`; `includes/class-quotation-controller.php:13-630`; `includes/class-integrations.php:103-173`; `includes/class-interakt.php:247-449`; `includes/class-auth.php:12-105`; `docs/SECURITY.md`.

## Confirmed issues, redundancy, and performance risks

### L-01 — manually queued audience sync can include duplicate contacts (P1)

**Evidence.** `run_batch_sync()` builds the rule predicate beginning with `is_duplicate = 0`, but when the manual queue is non-empty it selects `id IN (queue) OR (rule predicate)`. A manually queued duplicate therefore bypasses the duplicate exclusion. The documented flow says audience sync queries non-duplicate leads.

**Impact.** A duplicate contact may be hashed and sent to Meta or LinkedIn when manually queued, creating unnecessary external data processing and inconsistent audience semantics.

**Safe repair direction.** Apply `is_duplicate = 0` outside both branches, e.g. make the queue/rule grouping the second predicate. Add a targeted contract test for a queued duplicate plus a queued canonical lead. Do not change this without confirming whether an explicit administrative override is intended.

**Sources.** `includes/class-audiences.php:76-99`; `docs/FLOWS.md` (Audience sync).

### L-02 — audience queue acknowledgement is not delivery-aware (P2)

**Evidence.** Provider sync methods do not report an aggregate success value to `run_batch_sync()`. Once a run reaches its final block, it clears `idlm_audience_sync_queue` merely because rules were processed; a provider failure is logged but is not used to retain/retry individual queue items.

**Impact.** Manual audience requests can be lost from the queue after a failed remote delivery, while logs are the only recovery signal.

**Safe repair direction.** Return structured outcomes per rule/provider, clear only successfully delivered queued IDs, and retain failed IDs with bounded retry metadata. This needs an idempotency/remote-provider semantics decision before implementation.

**Sources.** `includes/class-audiences.php:71-126,131-331`; `includes/class-db.php:1135-1152`.

### L-03 — synchronous `New`-lead CAPI is an intake latency and availability coupling (P2)

**Evidence.** A `New` conversion invokes `process_capi_event()` inline; that process can issue up to three external HTTP POSTs. Later conversions are intentionally asynchronous and state-checked.

**Impact.** Webhook/manual creation latency and availability are coupled to third-party network behaviour, even though this appears intentional for immediate capture.

**Safe repair direction.** Measure webhook p95/p99 and provider timeout/error behaviour before changing. If unacceptable, enqueue `New` with a near-immediate worker and preserve idempotency/audit semantics. Do not alter without product confirmation because timing affects attribution.

**Sources.** `includes/class-lead-lifecycle-service.php:479-485`; `includes/class-capi.php:220-307`; `docs/PERFORMANCE-BASELINE.md`.

### Observed duplication that is intentional compatibility, not a defect

- `IDLM_DB` remains a compatibility facade over `IDLM_Lead_Lifecycle_Service`; admin and quotation facades preserve public callbacks while focused classes own work. Do not collapse these until compatibility callers are mapped. (`includes/class-db.php:1004-1152`; `includes/class-admin.php`; `includes/class-quotations.php`; `docs/PROJECT-MAP.md`.)
- Action Scheduler and WP-Cron registrations are intentional fallbacks, not duplicate scheduled execution: the scheduler chooses one backend at setup. (`includes/class-scheduler.php:24-52`.)
- CAPI clears pending events across stages before queuing the newest state to avoid stale rapid-transition uploads; this is intentional coalescing but its work scales with configured stage count. (`includes/class-capi.php:233-263`.)

## File ownership reference

| Concern | Primary files |
|---|---|
| Bootstrap/roles | `interior-design-lead-manager.php` |
| Lead persistence/lifecycle | `includes/class-db.php`, `includes/class-lead-lifecycle-service.php` |
| Lead access and state labels | `includes/class-lead-access-policy.php`, `includes/class-state-registry.php` |
| Admin request facade/handlers | `includes/class-admin.php`, `includes/class-admin-ajax.php`, `includes/class-admin-lead-reads.php` |
| Inbound, routing, feeds, MCP | `includes/class-integrations.php`, `includes/class-routing-state.php`, `includes/class-mcp-core.php` |
| CAPI/audiences/WhatsApp | `includes/class-capi.php`, `includes/class-audiences.php`, `includes/class-interakt.php` |
| Background/operations | `includes/class-scheduler.php`, `includes/class-data-retention.php`, `includes/class-telemetry.php`, `includes/class-operational-health.php` |
| Quotation transaction | `includes/class-quotations.php`, `includes/class-quotation-controller.php`, `includes/class-quotation-repository.php`, `includes/class-quotation-policy.php`, `includes/class-quotation-schema.php` |
| Authoritative supporting contracts | `docs/PROJECT-MAP.md`, `docs/FEATURE-MAP.md`, `docs/FLOWS.md`, `docs/INTERFACES.md`, `docs/DATA-MODEL.md`, `docs/SECURITY.md`, `docs/ARCHITECTURE.md` |
