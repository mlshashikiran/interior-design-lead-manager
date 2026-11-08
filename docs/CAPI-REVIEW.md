# Meta Conversions API review

Builder: Shashi. Reviewed 2026-11-08 against `main` at 377f25d (1.104.0), branch `capi/review`.

Scope: Meta CAPI event building, queueing, sending, retries, receipts, logging, the Ad conversions log screen, the shared Meta audience sync, and the tests for them. Google (GA4 Measurement Protocol) and LinkedIn share the dispatcher and appear only where the shared code matters.

PR #3 (`streamline/plugin`) was merged into `main` on 2026-11-07, so this branch already contains it. There is no branch overlap to reconcile.

## How it works today

1. A lead write calls `IDLM_Lead_Transaction::defer('idlm_lead_stage_converted', ...)` (`includes/class-lead-lifecycle-service.php:135`, `:764`, `:1139`, `:1141`). The intent goes into the durable outbox in the same transaction.
2. After commit, `IDLM_Lead_Transaction` dispatches each intent in the same PHP request (`includes/class-lead-transaction.php:80`). The outbox sweep picks up failures with backoff of 30 s doubling to 1 hour (`includes/class-lead-outbox.php:83`).
3. `IDLM_CAPI::queue_capi_event` (`includes/class-capi.php:167`) sends the `New` stage synchronously. Every other stage is scheduled 5 minutes later through Action Scheduler, or WP-Cron as a fallback. Pending jobs for the lead's other stages are cancelled first.
4. `dispatch_platform` (`includes/class-capi.php:307`) takes a MySQL named lock per platform and lead. It re-reads the lead, checks eligibility, mapping and credentials, and writes a durable "processing" receipt (`includes/class-capi-delivery.php:73`) before any HTTP request.
5. `IDLM_CAPI_Transport::fire_meta_capi_work` (`includes/class-capi-transport.php:47`) builds one event and POSTs it to `graph.facebook.com/v25.0/{pixel}/events` with a Bearer header and a 15 s timeout.
6. The outcome is logged to `idlm_capi_logs` and the receipt is finished in one transaction. A receipt in `sent`, `processing` or `uncertain` state blocks any later send of that lead, platform and event.
7. Admins can retry failed rows from the Ad conversions screen. Retries are capped at 3 per root log and are refused for uncertain outcomes (`includes/class-capi.php:445`).

The delivery core is strong. It has exactly-once claims with durable receipts, a lock-fenced worker lease, and no blind replay of ambiguous outcomes. Secrets are encrypted at rest (`IDLM_Secret_Store`), sent only in an `Authorization` header, and redacted from transport errors. The main gaps are in match quality and Meta's Conversion Leads conventions, not in reliability.

## Meta rules used

| Rule | Source |
|---|---|
| `ph` must include the country code and only digits, with no leading zeros. `fn`/`ln` must be lowercase with no punctuation, UTF-8. `external_id` hashing is recommended. `fbc`/`fbp`/`lead_id` are not hashed. | [Customer information parameters](https://developers.facebook.com/docs/marketing-api/conversions-api/parameters/customer-information-parameters) |
| `event_time` can be up to 7 days old. If any event in a request is older, the whole request is rejected. `event_source_url` is required only for `website` events. | [Server event parameters](https://developers.facebook.com/docs/marketing-api/conversions-api/parameters/server-event) |
| CRM events use free-form CRM stage names as `event_name`, `action_source=system_generated`, `custom_data.event_source=crm` and `lead_event_source`. Send every stage, including the raw lead. `lead_id` is the 15–17 digit Lead Ads ID. The payload is only for Meta Lead Ads leads. | [Conversion Leads: implementing the CRM integration](https://developers.facebook.com/documentation/ads-commerce/conversions-api/conversion-leads-integration/crm-integration/3-implementing-the-crm-integration), [configure your sales funnel](https://developers.facebook.com/documentation/ads-commerce/conversions-api/conversion-leads-integration/crm-integration/5-configure-your-sales-funnel) |
| Up to 1,000 events per request. A repeat `event_id` and `event_name` pair is kept only once, "That makes batch retries safe". `test_event_code` is for testing only and must be removed in production. | [Using the API](https://developers.facebook.com/docs/marketing-api/conversions-api/using-the-api) |
| Pixel and server dedup needs a matching `event_id`/`eventID` and `event_name` within 48 hours. | [Deduplicate Pixel and server events](https://developers.facebook.com/documentation/ads-commerce/conversions-api/deduplicate-pixel-and-server-events) |
| Error codes 4 and 17 (and 1, 2, 341, 368) are transient: "Wait and retry". Rate-limit errors also include 32, 613 and 800xx business-use-case codes. | [Graph API error handling](https://developers.facebook.com/docs/graph-api/guides/error-handling), [rate limiting](https://developers.facebook.com/docs/graph-api/overview/rate-limiting) |
| Graph API v25.0 was released 2026-02-18 and is available until 2028-07-29. The Marketing API v25.0 expiry is not yet announced. v26.0 shipped 2026-07-29. | [Graph API versions](https://developers.facebook.com/docs/graph-api/changelog/versions) |

## Findings

Priority is P1 (material loss of attribution or optimisation, or a privacy issue), P2 (measurable improvement) or P3 (hygiene).

### Safe now (implemented on `capi/review`)

These change no outbound request, setting, schema or public name.

**S1 (P1, security and privacy): hashed customer identifiers are stored in the CAPI log.**
- Where: `includes/class-capi-transport.php:17` (record_attempt), `includes/class-db.php:1225`, `includes/class-data-retention.php:64`.
- What: `IDLM_Data_Retention::redact()` masks keys such as `email`, `phone` and `first_name`. Meta's short keys `em`, `ph`, `fn` and `ln` do not match, so the SHA-256 hashes are stored for 180 days and shown in "Inspect payload". An unsalted SHA-256 of a 10–12 digit phone number can be reversed by brute force in minutes, so these hashes are effectively personal data.
- Impact: security and privacy. Match quality is unchanged.
- Risk of changing: low. Retry rebuilds `user_data` from the live lead (`retry_identity` reads only `_idlm_delivery` and `custom_data`), so nothing reads the logged hashes.
- Fix: before logging, replace the hashed `user_data` values (`em`, `ph`, `fn`, `ln`, `ct`, `st`, `zp`, `country`, `external_id`, `db`, `ge`) with `[HASHED]`. Admins can still see which match keys were sent. `lead_id`, `ctwa_clid` and `page_id` stay for diagnostics. Existing rows age out under the normal retention policy.

**S2 (P3, operations): the Graph API version is hard-coded in six places.**
- Where: `includes/class-capi-transport.php:123`, `includes/class-audiences.php:179`, `includes/class-admin-connection-controller.php:29`, `includes/class-lead-connector.php:399`, `includes/class-facebook-direct.php:53`. `services/facebook-connect/src/Service.php:58` is a separate service and stays as it is.
- Impact: reliability. A missed file during a version upgrade means mixed versions or a call to an expired version.
- Risk of changing: none at runtime, because the value stays `v25.0`.
- Fix: one `IDLM_CAPI_Transport::GRAPH_VERSION` constant used by the plugin's five call sites, plus a test that no plugin file hard-codes another version. Moving to v26.0 then becomes a one-line decision (see D10).

**S3 (P2, tests): the fast suite has no Meta payload contract.**
- Where: `tests/capi-retry-contracts.php` covers Google retries. The Meta payload shape is asserted only in the native suite (`tests/integration/lead-connector-contracts.php`), which needs MySQL.
- Fix: fast-suite tests that pin today's Meta request: endpoint and version, Bearer header and no token in the URL or body, `action_source=system_generated`, `custom_data.event_source=crm` and `lead_event_source`, no `event_source_url`, no `test_event_code`, `em`/`ph` as arrays of 64-hex hashes, `lead_id` as an exact string, stable `event_id`, and the S1 log masking. Any later change from the decision list then shows up as a deliberate test change.

### Needs a decision

Each of these changes what is sent, when it is sent, a setting, or the schema. The owner approved the recommendations on 2026-11-08; the table shows what was implemented on `capi/review`. The original findings follow it.

| Item | Status | Commit | What changed |
|---|---|---|---|
| D1 | Done | `571c5c2` | Meta phone hashes include the country code: 10-digit and 0-prefixed 11-digit numbers get 91. CAPI and audience sync. |
| D7 | Done | `571c5c2` | Names use UTF-8 lowercase with punctuation and symbols removed. CAPI and audience sync. |
| D6 | Done | `5ce3cfe`, see D6 phase 2 commit | Phase 1: hashed `external_id` (site host and lead ID) on every event, hashed `country` (`in`) when the phone has the 91 prefix. Phase 2: the website webhook accepts `fbc`/`_fbc` and `fbp`/`_fbp` (or a mapped key), keeps only values in the Pixel's `fb.<n>.<time>.<value>` format, stores them in new `fbc` and `fbp` lead columns (schema 1.3.18), fills them on a repeat submission only when empty, and sends them unhashed in Meta `user_data`. |
| D3 | Done (option c, narrowed) | `5ce3cfe` | Non-Lead-Ads leads are still sent, but without `event_source=crm` and `lead_event_source`, so only Lead Ads leads claim the Conversion Leads payload. Option (a), stopping those events, was not chosen because it drops all website-lead signal. |
| D5 | Done | `0b77947` | `event_time` comes from the lead's creation (New), the latest `stage_change` event into the stage, or `qualified_at`; it falls back to now when unknown, in the future or older than Meta's 7-day window. |
| D4 | Done | `0cc1238` | A stage change replaces only the same stage's pending job, so earlier funnel stages are still sent. |
| D12 | Done | `0cc1238` | New is queued with no delay instead of sent inside the intake request. |
| D8 | Done | `62c44d3` | Meta timeouts, 408, 5xx, `is_transient` and codes 1, 2, 4, 17, 32, 341, 368, 613 and 80000–80099 are a `transient` outcome. The receipt allows a replay and an automatic retry runs 5, then 30 minutes later, sharing the 3-attempt cap with manual retry and stopping 47 hours after the first attempt. Google and LinkedIn are unchanged. |
| D2 | Done (option a) | `9d97198` | A first-ever activation saves New→Lead, Qualified, Quote Shared and Won as the Meta mapping. Existing installs keep their saved or legacy mapping; updates change nothing. The Won close value is sent whatever the Won event is called. Events Manager funnel setup is still needed. |
| D14 | Done (exclude) | `128f28e` | Value-based audiences leave out leads with no deal value; the daily resync adds them once a value exists. |
| D13 | Done | `cce08d5` | The log status shows Succeeded, Rejected, Retrying, Uncertain or Not sent with Meta's error message and `fbtrace_id`; rows that cannot be retried have no checkbox. |
| D11 | Done | `be95035` | A "Send test event" button on the Meta CAPI card sends one synthetic event with the admin's Events Manager test code. No lead data, log, receipt or saved code. |
| D9 | Closed: no consent gate | | The owner decided on 2026-11-08 that no consent gate is required. Events are sent for every eligible lead source, as today. |
| D10 | No change (as recommended) | | Stay on v25.0 until Meta announces its expiry. |

Not verified: no event was sent to a real Meta pixel, and Plugin Check was not rerun. Before release, use the new test-event button against a test pixel and set up the CRM stage funnel in Events Manager.

**D1 (P1, match quality): phone numbers are hashed without a country code.**
- Where: `includes/class-capi-transport.php:53` and `includes/class-audiences.php:159`.
- What: the code hashes whatever digits are stored. A lead stored as `98xxxxxxxx` or `098xxxxxxxx` is hashed without `91` or with a leading zero, which never matches Meta's record. Meta requires the country code.
- Fix: hash `ltrim(IDLM_CAPI::format_phone_e164($phone), '+')`. The helper already exists at `includes/class-capi.php:520` and defaults 10-digit numbers to +91. Apply the same change to audience sync.
- Risk: low. It changes the hashed value sent for some leads, and accepted events are not re-sent. Needs approval because it changes the data sent.

**D2 (P1, attribution and optimisation): stage events use standard event names, not CRM stage names.**
- Where: the default mapping at `includes/class-capi.php:268`: New→`Lead`, Qualified→`Contact`, Quote Shared→`Subscribe`, Won→`Purchase`.
- What: Conversion Leads optimisation expects free-form CRM stage names, such as `Qualified` and `Converted`, set up as a funnel in Events Manager. `Contact` and `Subscribe` misdescribe the stages, and `Purchase` from a CRM source can pollute any website Purchase optimisation on the same dataset.
- Fix options: (a) default new installs to stage names and leave existing saved mappings alone, or (b) offer a one-click "Use CRM stage names" setting.
- Risk: medium. It changes event names and needs matching setup in Events Manager. Receipts are keyed by event name, so renamed events can be sent again for leads whose old-named event was accepted.

**D3 (P1, attribution): non-Lead-Ads leads get the CRM payload.**
- Where: `includes/class-capi-transport.php:104`. Every lead without a WhatsApp click is sent as `system_generated` with `event_source=crm`.
- What: Meta says the Conversion Leads payload is only for Meta Lead Ads leads. Website-form, manual and organic leads have no `lead_id` and no browser signals (`fbc`, `fbp`, IP, user agent), so they match poorly and can count against data-quality diagnostics.
- Fix options: (a) send CRM events only when `fb_lead_id` is present, (b) send other leads as `website` events once D6 captures browser signals, or (c) keep as is.
- Risk: medium. It changes which events are sent.

**D4 (P2, attribution): rapid stage changes drop intermediate stages.**
- Where: `includes/class-capi.php:181-199`.
- What: each stage change cancels pending jobs for every other stage. New→Qualified→Won within 5 minutes sends only `Lead` and `Won`. Meta asks for every stage, and the funnel model needs the middle steps.
- Fix: cancel only the same stage's pending job. `lead_matches_event` already sends Qualified when the lead is at Quote Shared or Won.
- Risk: low to medium. More events are sent.

**D5 (P2, attribution): `event_time` is when the event is dispatched, not when the stage changed.**
- Where: `includes/class-capi.php:347` (`time()` inside the dispatcher).
- What: with the 5-minute delay and outbox backoff of up to an hour, event times are later than the real change. Retries keep the first value, which is good.
- Fix: carry the stage-change timestamp through the outbox arguments, or read it from the activity log.
- Risk: low. It changes the `event_time` sent.

**D6 (P2, match quality): no `external_id`, `fbc`, `fbp`, `ct`, `st`, `zp` or `country`.**
- Where: `includes/class-capi-transport.php:56-81`.
- What: Meta recommends a hashed `external_id`; a stable value is SHA-256 of the site and lead ID. `country` could be hashed `in` for this India-only business. `fbc` and `fbp` need form-side cookie capture and a new lead column, which is a schema change. The code deliberately refuses to fabricate `fbc` from `fbclid` and the lead time (`:74-76`). Meta allows building `fbc` from `fbclid` only with the time the click was first seen, so that refusal is correct until capture exists.
- Fix: phase 1 adds `external_id` and `country`. Phase 2 captures `_fbc` and `_fbp` on website forms (schema, plus a consent decision in D9).
- Risk: low for phase 1 (data sent changes). Phase 2 needs a schema change.

**D7 (P2, match quality): names are lowercased with byte `strtolower` and keep punctuation.**
- Where: `includes/class-capi-transport.php:68`, `:71` and `includes/class-audiences.php:160-161`.
- What: non-ASCII names hash differently from Meta's normalisation, and so do names with dots or apostrophes.
- Fix: `mb_strtolower` and strip punctuation. Do not reuse `normalize_name_for_hash`, which strips all non a–z letters, removes honorifics and suits Google rather than Meta.
- Risk: low. It changes the data sent.

**D8 (P1, reliability): Meta transient errors are never retried, and rate limits are treated as permanent rejections.**
- Where: `includes/class-capi-transport.php:40-45`.
- What: timeouts and 5xx responses are marked `uncertain`, which blocks both automatic and manual retry permanently. Graph API rate limits (codes 4, 17, 32, 613, 800xx) usually come back as HTTP 400 or 403. Only HTTP 429 counts as retryable here, so a real rate-limit response becomes a confirmed rejection. Meta keeps only the first copy of an event with the same `event_id` and `event_name`, so a replay with the same `event_id` within 48 hours is safe for Meta. That makes "uncertain" too strict for Meta, though it is still right for GA4 and LinkedIn. Audience sync (`includes/class-audiences.php:129`) already treats 5xx, 429 and `is_transient` as retryable.
- Fix: for Meta only, treat timeouts, 5xx and error codes 1, 2, 4, 17, 32, 341, 368, 613 and 800xx, or `is_transient=true`, as retryable. Retry automatically with bounded backoff inside 48 hours, reusing the original `event_id` and `event_time`.
- Risk: medium. It changes when events are sent and touches the receipt state machine. It needs native-suite coverage.

**D9 (P2, compliance): there is no consent gate.**
- What: Lead Ads leads consent inside Meta's form. Website, manual and WhatsApp leads are sent with no recorded consent basis. The only gate is the manual-lead "ad sources" option (`includes/class-capi.php:250`).
- Fix: a per-source policy or a stored consent flag. This is a business and legal decision.

**D10 (P3, operations): the Graph API version.**
- What: v25.0 is supported until at least 2028-07-29 for Graph, and the Marketing API expiry is "TBD". v26.0 is available. After S2 this is a one-line change, but the facebook-connect service and its tests also pin v25.0. Recommend leaving v25.0 until Meta announces its expiry, then moving everything together.

**D11 (P2, operations): there is no `test_event_code` workflow.**
- What: admins cannot verify delivery in Events Manager's Test Events without sending production events.
- Fix: a "Send test event" button that posts one synthetic event with a short-lived `test_event_code` typed by the admin. The code is never stored as a persistent setting, and the event gets no receipt and no lead link.
- Risk: it adds a new admin action and an outbound request. Needs approval.

**D12 (P2, performance): `New` leads are sent synchronously inside the request that created them.**
- Where: `includes/class-capi.php:170-175` with `includes/class-lead-transaction.php:80`.
- What: webhook and form requests wait for up to three provider calls (Meta, Google, LinkedIn) of up to 15 s each. Slow providers slow lead intake, and webhook senders may time out and resend. Dedup protects the data, but intake latency suffers.
- Fix: schedule `New` with no delay through Action Scheduler, as the other stages already are.
- Risk: low. Events are sent seconds later instead of during the request.

**D13 (P3, operations): the log screen shows only Success or Failed.**
- Where: `templates/admin/logs/capi.php:117`.
- What: rejected (fix and retry), uncertain (cannot retry) and skipped outcomes all show as "Failed". Admins find the difference only by retrying or opening the payload. Meta's `error.message`, `error_user_msg` and `fbtrace_id` are buried in the raw response.
- Fix: read `_idlm_delivery.outcome` and the Meta error fields, show an outcome badge and message, and hide the retry checkbox for uncertain rows. This is a UI change under the design STANDARD, and the list query must start reading payloads (bounded to 50 rows).

**D14 (P3, audiences): value-based audiences invent a value of 100.0.**
- Where: `includes/class-audiences.php:164`.
- What: leads with no deal value are uploaded with `LOOKALIKE_VALUE` 100.0, which skews value-based lookalikes. Excluding them or using a median would be more honest.

### Leave alone

- **No batching** (`includes/class-capi-transport.php:126`). CRM volume is one event per stage change, and the per-event claim and receipt is what makes delivery exactly-once. Batching up to 1,000 would add little and weaken the per-event outcome.
- **`event_id` = `idlm_lead_{id}_{event}`** (`includes/class-capi.php:347`). It is deterministic, stable across retries and unique per lead and event. The plugin fires no browser Pixel. If the site's theme fires a Pixel `Lead`, its `eventID` would need to match for dedup. Note this for whoever manages the Pixel; the plugin needs no change.
- **No `event_source_url`, `client_ip_address` or `client_user_agent`** for `system_generated` events. This is correct per Meta; the CRM worker has no browser context.
- **Not fabricating `fbc` from `fbclid`** (`:74-76`). This is correct until D6 phase 2.
- **WhatsApp (`business_messaging`) events with `ctwa_clid` and `page_id`** (`:116-120`). These match Meta's business messaging spec and need explicit enablement.
- **Purchase value from the latest quotation revision** (`includes/class-capi.php:286`). It is careful and handles currency correctly.
- **Secrets.** Tokens are encrypted at rest, sent only in the `Authorization` header, removed from receipts and logs, and redacted from transport errors. The GA4 `api_secret` must be in the URL, which Google requires, and is also redacted.
- **Receipt and lock design.** It is complex but well tested, and it prevents double conversions. Do not simplify it without the native suite.
- **Log retention.** Logs are kept 180 days by default, configurable in `idlm_retention_policy`, pruned in batches, and minimal receipts are kept past pruning. This is appropriate.

## Test coverage gaps

- Fast suite: Meta request shape (closed by S3), log masking (closed by S1), and version consistency (closed by S2).
- Not covered anywhere: Meta error-code classification (needed before D8), the cancel-other-stages behaviour (needed before D4), and phone normalisation for non-Indian numbers (needed before D1).
