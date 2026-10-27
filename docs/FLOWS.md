# Application Flows

## Verification companion

[STATE-POLICY.md](STATE-POLICY.md) records the current state vocabularies, transition ownership, qualification gates, reversals and historical repair differences, checked for candidate 1.102.56. [LOGICS.md](LOGICS.md) is a static audit from 2026-09-09; its line references and issue descriptions are historical and must be checked against current source and remediation evidence.

## Inbound lead creation and repeat touch

Inbound checks durable review replay, then exact platform/external touchpoints (with legacy external-ID fallback), then the union of email and normalized phone candidates. One unique candidate enriches the canonical lead even if the other supplied contact identifier conflicts; existing populated contact fields are preserved. Multiple candidates retain a submission review and candidate activity outside the actionable queue. Explicit country codes survive normalization. The public webhook reports `requires_identity_review`; internal upsert uses `requires_duplicate_review`. Managers can resolve canonical/separate/reject decisions; see [identity-review lifecycle](AUDIT-REMEDIATION-IDENTITY-REVIEW-SLICE.md).

**Trigger** REST `POST /interior-design-lead-manager/v1/webhook` → **Handler** `IDLM_Integrations::handle_webhook_request()` → **Validation** scoped webhook API key in the route permission callback and handler, rate limit, supported platform, non-empty/test payload → **Business logic** flatten field mappings, merge website attribution cookies, classify source, normalize rooms/property/budget → **Persistence** `IDLM_DB::upsert_inbound_lead()` finds a canonical identity, inserts or merges and records `idlm_lead_touchpoints` → **Side effects** new-lead routing, activities, qualification tasks, stage/CAPI hooks; attribution merges do not resend prior successful conversions → **Response** REST success with lead identity/status.

Manual creation and CSV import enter through `IDLM_Admin` AJAX handlers, require admin/CRM permissions as applicable, sanitize mapped fields, and use the same inbound upsert policy. A matching record is enriched without a new owner/task/routing event; a submission matching multiple candidates is retained for review outside the lead queue. New manual leads remain creator-owned and do not use automatic intake routing.

## Lead update and qualification

**Trigger** quick edit, lead drawer save, Kanban move, pre-sales decision, bulk action, or import → **Handler** corresponding `IDLM_Admin::ajax_*` method → **Validation** `idlm_admin_nonce`, CRM capability, object ownership/manager authority, allowed field/value and structured qualification gates → **Business logic** `IDLM_DB::update_lead()` normalizes stage/quality, disqualification, owner and handoff transitions → **Persistence** lead row plus activities/tasks/timestamps and an immutable `idlm_lead_state_events` record for each stage/owner transition (including Won/Lost value snapshot when applicable) → **Side effects** `idlm_lead_stage_converted`, `idlm_lead_call_status_no_answer`, and configured Interakt hooks; CAPI jobs may be queued → **Response** JSON with message and refreshed counts/data.

Qualification requires project fields and the minimum budget; an explicit quality decision without a floor plan requires a handoff remark. Six outreach cycles plus closure gate ordinary Unqualified decisions, not qualification. Only entering Qualified implicitly qualifies a lead; downstream sales stages preserve undecided quality. [Transition and reversal details](STATE-POLICY.md). Qualified pre-sales leads enter `awaiting_manager`; a manager assignment or timed fallback completes handoff. Unqualification drives `Lost` plus mapped lost/disqualification reason. Duplicate early close has a dedicated policy.

Administrators can use the same lead-drawer follow-up and decision controls to cover an unavailable pre-sales representative. Qualification retains the normal validation, remark, actor attribution and owner-based handoff rules. Builder: Shashi.

## Assignment and handoff

**Trigger** inbound insert, manager assignment, hourly schedule, or “run fallback now” → **Handler** `route_lead_assignment()` or `run_assignment_fallback()` → **Validation** configured mode/pools, roles, capacity, studio mapping, working-hour threshold; transient prevents overlap → **Business logic** round-robin/source/studio selection or qualified OOO handoff → **Persistence** compare-and-set update in `assign_lead_atomically()` and handoff fields/tasks/activity plus an immutable assignment event → **Side effects** optional handoff email; throttled telemetry/admin email for routing exceptions → **Response** background completion or AJAX status.

## Follow-up and WhatsApp

Pending and completed task queues authorize customer data through current lead visibility, including existing historical pre-sales association; task attribution alone does not preserve access after reassignment. Manual task creation validates its type, priority, title and exact local due-date format. Qualification tasks are created/completed by the qualification workflow and cannot be manually added, toggled or deleted. Administrative historical Interakt sync now queues capped batches with request deduplication, worker overlap locks and up to three attempts; the UI reports queue acceptance separately from delivery.

Assignment to pre-sales creates six working-day qualification tasks. Touch logging records call/WhatsApp evidence; no-answer status emits `idlm_lead_call_status_no_answer`, which maps to an Interakt event. Interakt events are queued with Action Scheduler or WP-Cron and posted to Interakt.

Interakt and Meta WhatsApp REST callbacks validate current configured HMAC signatures, process all relevant messages, normalize the full sender phone and require one canonical identity. Each `whatsapp_reply` activity commits with a provider-ID receipt before successful acknowledgement; replay appends no second activity. Partial/unmatched/ambiguous or persistence failures return explicit non-200 outcomes. Per-user `idlm_whatsapp_read_activity_ids` tracks read state. There is no outbound WhatsApp message endpoint in this code.

## Conversion events

**Trigger** lead stage/quality conversion hook → **Handler** `IDLM_CAPI::queue_capi_event()` → **Validation** configured mapping/enabled platform and prior-success/idempotency rules → **Business logic** schedule `process_capi_event()` → **Persistence** request/response/status in `idlm_capi_logs` → **Side effects** remote POST to Meta CAPI, Google Measurement Protocol, and/or LinkedIn Conversion Events → **Response** asynchronous audit entry. Admins can retry failed logs and export eligible Google offline conversions as CSV.

## Audience sync

**Trigger** daily schedule or admin selected queue → **Handler** `IDLM_Audience_Dispatch` via `IDLM_Audiences::run_batch_sync()` → **Validation** current enabled definitions, credentials, nonduplicate and stage/call/quality filters → **Business logic** selected-only manual work and bounded scheduled keyset pages → **Persistence** checked claims before transport, audit/outcome acknowledgement together, failed/unknown selections retained → **Side effects** Meta/LinkedIn page upload; Google mapping audit only → **Response** truthful counts and retained-work summary; authenticated Google CSV remains a pull feed. See [INT-009 policy](AUDIT-REMEDIATION-AUDIENCE-RETRY-SLICE.md).

## Quotation lifecycle and print/PDF

**Trigger** quotation form/admin-post or AJAX autosave → **Handler** `IDLM_Quotations::handle_save()` / `ajax_autosave_quote()` → **Validation** a new quote requires `idlm_create_quotes`; a revision requires both draft-edit and revision capabilities, nonce, object-level lead visibility, a current Draft source revision, validated sections/items/decimals, and idempotency key → **Business logic** the browser autosaves only a temporary per-user working copy; saving a revision collects a required change remark and `IDLM_Quotation_Calculator` deterministically normalizes rows and calculates line, wood discount, design fee, additional discount, taxable and GST totals behind `IDLM_Quotations` compatibility delegates → **Persistence** the facade coordinates the checked transaction while `IDLM_Quotation_Repository` locks the quote before verifying its current Draft source and reserving R+1, then writes the revision, supersedes the source, replaces content, records history, and projects the lead quote value → **Side effects** quote activity and autosave cleanup → **Response** redirect or JSON.

**Trigger** a detail-view Download PDF/CSV/XLSX action → **Handler** `IDLM_Quotation_Exporter` admin-post endpoint → **Validation** `idlm_view_quotes`, `idlm_download_quote_pdf`, format-specific nonce, exact quote/revision ownership, and object-level lead visibility → **Read model** `IDLM_Quotation_Repository::get_quote_bundle()` loads only the requested persisted revision and its persisted item snapshots; autosaves/current lead values, internal notes, and activity are not read → **Response** streamed CSV or OOXML workbook, or an in-memory Dompdf PDF. PDF rendering disables remote resources and uses the plugin chroot; a generic detail-view fallback notice retains Preview / Print and tabular downloads if rendering fails.

Current quotation writers expose Draft/Superseded revisions rather than approval/shared/rejection/expiry actions. Historical commercial states, pointers and dates are preserved; finalized or commercially evidenced quotations remain read-only under [QUO-010 policy](AUDIT-REMEDIATION-QUOTATION-HISTORY-SLICE.md). A new quote is R01 and revisions are R02, R03, and so on; successful revision writes make the new revision Draft and supersede its source. Each revision carries its creator, update author, required revision remark, status-history entry, and lead activity attribution. Quote creation is serialized per lead and an existing quote redirects users to that quote's revision workflow. Creating or revising a quote updates the lead's projected quote value but never changes its pipeline stage; moving a lead to Quote Shared records the sharing flag and requires the structured quote total to be positive.

Lead picker search, initial editor choices, selected-lead prefilling, and object-access existence checks are read through `IDLM_Quotation_Lead_Read_Model`, which applies unrestricted administrator/assignment-manager scope or current representative ownership scope.

Catalogue handlers retain capability/nonce checks, field and uploaded-file validation, the 2,000-row import transaction, CSV formula neutralization, streamed output, redirects, and UI rendering. Catalogue term/item CRUD, automatic `ITEM-000001`-style code assignment for new items with an empty code, bulk/category actions, duplicate-code checks, search/list pagination, and export chunks are delegated to `IDLM_Quotation_Repository`; the same repository supplies the scoped quotation-list count/page read model.

Print uses `admin-post.php?action=idlm_print_quote`, nonce, view/download capabilities, and lead access. It renders escaped HTML with print CSS and a “Print / Save PDF” browser action; the separate Download PDF endpoint renders an in-memory binary PDF with Dompdf and does not persist the generated download.

## Account update

Authenticated shortcode user submits `admin-post.php?action=idlm_update_my_account` → login and nonce validation → sanitize identity/phone and validate unique email/password → update WordPress user plus `idlm_phone`, optionally send core password-reset email → safe redirect with status.

Anonymous CRM user submits `admin-post.php?action=idlm_login` → nonce and safe-return validation → exact email lookup → WordPress `wp_signon()` authentication/cookie creation → generic failure or local workspace redirect. Password recovery uses the parallel request/reset handlers, generic request responses, an eight-character minimum, WordPress-generated user-bound reset keys, core validation hooks, and core password/session reset behavior.

## Private-file lifecycle — 1.102.47

Builder: Shashi. Authorized multipart upload → lock/recheck live owner or manager → private attachment/chunk transaction → stable protected URL. Saving a canonical lead pointer activates it; removal revokes immediately. Download checks live lead scope before and after verified chunk retrieval. Hourly/manual bounded migration copies local main/variants before URL replacement and journaled unlink, including tagged unsaved media. Unsaved uploads expire after seven days. Lead delete/reset erase bytes transactionally and clean filesystem/attachments after commit; rollback and cleanup retries retain journals. Partial JSON restore cannot replace private-file identities. [Fifth slice report](AUDIT-REMEDIATION-PRIVATE-FILE-SLICE.md).

### Backup recovery flow (1.102.48)

Shashi: authorize → validate complete same-site aggregate → refuse active workers/private-file partial recovery → stage constraints → checked transactional replacement/settings/generation → commit → clear jobs/autosaves/caches while holding recovery gate. Failed preflight/transaction leaves queues and data unchanged. Restore does not immediately prune historical logs; ordinary retention resumes later. [Evidence](AUDIT-REMEDIATION-RESTORE-SLICE.md).

## Checked persistence and delivery timing (1.102.50)

Shashi: canonical capture/merge/update and automatic assignment now check required lead, activity, reporting, task and attribution writes in one transaction. Routed webhook/CSV and creator-owned manual capture finish required work before success. External-ID repeats verify an existing canonical touchpoint; an actual write failure rolls back and returns error/retry guidance. Existing conversion/attribution/Interakt hook payloads run after commit with durable replay intent. Failures after commit retain delivery intent while the lead save remains successful; replay is at least once. Fresh six-task plans are atomic; the subsequent CORE-006 slice also implements historical partial-plan repair. [Detailed flow matrix](AUDIT-REMEDIATION-LIFECYCLE-SLICE.md).

## Task-plan recovery — 2026-10-07

Shashi: partial six-cycle plans now adopt stable identities and fill missing slots under one checked lead transaction. Active synchronization follows outreach evidence, distinct working days and the live pending-task owner; supported completed history retains attribution. Terminal/duplicate plans do not reopen. Permitted active reversal and both manual/automatic assignment reconcile existing work. Scheduled repair discovers empty plans in 100-lead checkpointed pages. Failed outreach reconciliation returns repair guidance while already recorded evidence remains durable. [Full state and ambiguity policy](AUDIT-REMEDIATION-TASK-PLAN-SLICE.md).

## Current assignment target policy — CORE-009

Shashi: all initial/fallback runtime pools and manual targets use the shared enabled CRM role/effective-access policy. Before new ownership, canonical persistence locks the lead then reads/locks current target account, metadata and role definitions; automatic persistence additionally enforces intake versus qualified-sales context. Ineligible targets and failed/nontransactional target reads deny ownership without required side effects. Unchanged historical owners and manual unassignment remain valid; saved pool IDs are filtered at runtime. [Details and qualification limits](AUDIT-REMEDIATION-ASSIGNMENT-ELIGIBILITY-SLICE.md).

## Current capacity behavior — CORE-010

Shashi: initial and fallback selection use advisory pools; final canonical/automatic persistence locks the target account and checks current settings/active count for every positive workload delta. Unassigned recovery refreshes the pool per lead. Zero is unlimited; Qualified counts active, while Won/Lost/Unqualified/duplicate work does not. Unchanged active owners and closed history remain valid at capacity, reopening needs a slot, and bulk reports per-lead committed successes/failures. [Executed policy and limits](AUDIT-REMEDIATION-CAPACITY-SLICE.md).

## Identity-review lifecycle (1.102.54)

Shashi: ambiguous intake holds sanitized supported input and creates no actionable work. Enabled assignment managers inspect the native admin review utility and POST a nonce-bound canonical/separate/reject decision. Identity/review locks and checked persistence commit the result once. Canonical matching fills empty fields without rerouting; separate creation is New/unassigned; rejection is audited without a lead. Terminal external-ID replay obeys the retained decision. Lead erasure deletes whole associated reviews; reset includes the holding table; retention deletes at most 100 expired reviews per checked batch. [Full policy](AUDIT-REMEDIATION-IDENTITY-REVIEW-SLICE.md).


## Quotation install/upgrade and retry (1.102.57)

Shashi: activation or pending normal boot → checked quotation installer → schema session lock/recovery registration → durable pending status → restartable dbDelta plus physical metadata verification → checked seed, legacy, snapshot and lifecycle transactions → atomic final schema-version/ready receipt. Each failed data phase rolls back its writes/marker, preserving independently successful phases. Incomplete CRM requests return retryable 503; administrators receive private repair guidance; later requests retry. Completion reads bypass stale option caches. Classification/history semantics remain separate QUO-005/010 findings. [Detailed policy/evidence](AUDIT-REMEDIATION-QUOTATION-MIGRATION-SLICE.md).


## Historical item classification preservation (1.102.58)

Shashi: old or current catalogue changes → quotation install/boot → additive classification provenance default plus existing physical/data verification → preserve every stored item flag and monetary field → checked completion. New saves derive provenance from normalized submitted classification; revision copies preserve stored provenance. Complete old backups add unverified provenance before existing core normalization and full preflight, with no catalogue inference. [QUO-005 policy/evidence](AUDIT-REMEDIATION-QUOTATION-CLASSIFICATION-SLICE.md); QUO-010 commercial history remains open.


## Historical commercial-state validation (1.102.59)

Shashi: upgrade/boot → existing migration lock and physical verification → preserve all original states/pointers/dates while verifying relationships → checked lifecycle 1.1.0/schema 2.5.3 receipt. Save/autosave/discard and editor access share immutable-draft policy, rechecked beneath the quote lock for mutations. Historical reads remain authorized; old backups retain exact history; incomplete commercial pointer relationships fail instead of guessing. [Evidence and recovery limits](AUDIT-REMEDIATION-QUOTATION-HISTORY-SLICE.md).


## Quotation engine drift and repair (1.102.60)

Shashi: Authorize request → refuse a foreign transaction owner → next-transaction READ COMMITTED → START → pin required table metadata/check InnoDB → lock and recheck live lead/grants/history → existing checked writes/COMMIT. Unsupported/missing/failed checks roll back preflight and return repair guidance; authorized persisted reads remain available. Operator-backed engine/table repair → fresh check → ordinary retry. Number reservations inside a save/migration retain that owner. [Scope, executed checks and limits](AUDIT-REMEDIATION-QUOTATION-ENGINE-SLICE.md).

## Quotation numeric refusal and normalized pricing

Save/revise validates rows, sections, adjustments and representable totals before numbering/writes; invalid input redirects with an error. Autosave rolls back on invalid input and returns JSON 422 without replacing a prior working copy. Derive and round area, clamp billable quantity to .01, then price; sum rounded line cents, apply rounded percentages, split GST and round final total to whole units. Browser preflight matches server rules; server validation remains authoritative. [Numeric policy and qualification](AUDIT-REMEDIATION-QUOTATION-NUMERIC-SLICE.md).

## Quotation contractual revisions (1.102.62)

Shashi: new quotation → current currency/current contractual snapshot. Edit/autosave → original saved currency/current terms; final save → new immutable contractual snapshot, previous revision retained under existing supersession rules. Print/PDF/CSV/XLSX → exact selected revision currency/known terms/payments; missing legacy terms → explicit unknown notice, no current-template substitution. Current company branding and paper layout remain live. [Detailed contract](AUDIT-REMEDIATION-QUOTATION-DOCUMENT-SLICE.md).


## Ordinary Interakt delivery — 1.102.65

Shashi: after-commit lifecycle intent → stable opaque dispatch job → Action Scheduler or WP-Cron → current primary credential/enable/epoch/lead/owner/rule checks → checked processing claim → fresh contact traits and HTTP → sent or uncertain. Disable/re-enable cancels older work; reversal/ownership changes cancel pending work; legacy copied-key callbacks are refused. Interrupted attempts require reconciliation. [Executed evidence and limits](AUDIT-REMEDIATION-INTERAKT-DISPATCH-SLICE.md).

## CAPI retention and replay (`1.102.69`)

Builder: Shashi. Current eligibility → platform/lead lock → checked transaction commits minimal processing claim → HTTP outside transaction → checked log plus receipt acknowledgement → response. Interrupted or ambiguous responses and older failed receipts without proof of rejection become uncertain and block resend. Accepted events are never forced again after attribution changes; selected explicit rejections remain manually retryable under existing limits. Google Measurement Protocol non-2xx responses are uncertain. Retention reads at most 100 expired CAPI metadata rows, copies legacy successful identities and deletes exactly those IDs with the checkpoint in one transaction. Explicit erasure removes receipts; same-site restore preserves local delivery evidence. Historical logs without a receipt remain an unresolved reconciliation risk. [Prior policy and evidence](AUDIT-REMEDIATION-CAPI-RETENTION-SLICE.md).

## Meta Purchase commercial value — 1.102.95

Builder: Shashi. `IDLM_CAPI::dispatch_platform()` resolves only fresh Meta Purchase commercial data through `purchase_amount()`: existing quotation repository reads select the linked quote’s current persisted revision, its grand_total and currency_code. Zero/no quotation falls back to the lead’s final entered Won deal_value and CRM currency. The delivery claim/log snapshot retains the selected amount; retries never re-read quotation prices. Existing accepted-event suppression, lifecycle eligibility, canonical quotation writes, lead revenue fields and Google/LinkedIn/non-Purchase values are unchanged. Malformed/unreadable priced quotations fail before provider transport.
