# Direct Meta lead sync review

Review of the Meta Lead Ads (Instant Form) direct sync in `interior-design-lead-manager`, on branch `leadsync/review` from `main` at 615ebad (1.104.0, after the CAPI review in PR #9). Synthetic data only; no Graph request was sent.

## How it works today

- **Connect.** `IDLM_Facebook_Direct` runs Facebook Login for Business with a single-use state bound to the administrator, session, app and recovery generation. It exchanges the code for a long-lived user token, lists Pages and active forms, checks lead access for each chosen form, subscribes the app to the Page's `leadgen` field (keeping fields the same app already uses), and stores the Page token encrypted. A manual mode takes the Page ID, app ID, form IDs, token, app secret and verify token directly. A legacy hosted mode relays through a signed service.
- **Webhook.** `POST /idlm/v1/meta-leads` checks `X-Hub-Signature-256` (HMAC-SHA256 of the raw body with the app secret) before reading anything. It accepts only `leadgen` changes for the saved Page and forms, at most 100 per batch, and stores each as an encrypted job keyed by a hash of the lead ID. `GET` answers the verification challenge with the verify token.
- **Import.** `idlm_lead_connector_tick` runs every 60 seconds. It processes up to 10 jobs: it fetches the lead (full fields, then attribution fields, then base fields), maps answers through the per-form mapping, checks erasure markers, and calls `IDLM_DB::upsert_inbound_lead` with the attribution kept in an encrypted per-lead option. A failure is retried with backoff (2, 4, 8, 16 minutes) up to 5 attempts, then the job waits as `failed` for the "retry failed" setting. Jobs older than 7 days expire.
- **Catch-up.** Each tick also pages one form's `/{form}/leads` with `time_created > since`, 25 at a time, where `since` is never more than 7 days back, and enqueues anything new. The same dedup key makes webhook and catch-up arrivals one job.

## Meta rules used

| Rule | Source |
|---|---|
| Webhook payloads are signed with `X-Hub-Signature-256`; verification uses `hub.mode`, `hub.verify_token`, `hub.challenge`. Failed deliveries are retried for up to 36 hours. | https://developers.facebook.com/docs/graph-api/webhooks/getting-started |
| Leads are retrieved by ID or from `/{form-id}/leads`, filterable by `time_created`, and are available for 90 days. | https://developers.facebook.com/docs/marketing-api/guides/lead-ads/retrieving |
| `is_organic` marks a lead that did not come from an ad. | https://developers.facebook.com/docs/marketing-api/reference/user-lead-gen-info/ |
| Page tokens obtained with a long-lived user token do not expire, but are invalidated by password changes, revoked permissions or app removal (error 190). | https://developers.facebook.com/docs/facebook-login/guides/access-tokens/get-long-lived |
| Error 100 is an invalid parameter or field; 190 is an invalid token; 4, 17, 32 and 613 are rate limits. | https://developers.facebook.com/docs/graph-api/guides/error-handling |

## Findings

### Safe now (implemented on `leadsync/review`)

**L-S1 (P2, reliability): the field fallback triples requests when Meta is throttling or the token is broken.**
- Where: `includes/class-lead-connector.php:430-432` and `graph()` at `:395`.
- What: if the full-fields request fails for any reason, the import immediately makes two more requests with fewer fields. During a rate limit (codes 4, 17, 32, 613) or with an invalid token (190), that is three failing calls per attempt, which deepens the throttling.
- Fix: `graph()` keeps Meta's error code on the `WP_Error`. The fallback runs only for error 100 (unsupported field) or when no code is known (hosted relay, unexpected bodies), which keeps today's behaviour for those.
- Risk: none for data; fewer requests on throttling and token errors.

**L-S2 (P2, operations): a lead with no mapped phone or email is retried four more times before it waits for an admin.**
- Where: `process()` at `:359-365`, with `map_meta()` at `:423`.
- What: a missing contact, a malformed record or an identity mismatch cannot succeed on retry until the form mapping is fixed. Today it takes 5 attempts (about 30 minutes) to reach `failed`.
- Fix: these errors move the job straight to `failed` with status `mapping_failed`. It stays retryable through "Retry failed imports" after the mapping is fixed; nothing is discarded.
- Risk: none for data.

### Needs a decision

**L-D1 (P1, attribution): Meta's submission time is dropped.**
- Where: `map_meta()` and `import_meta()` (`:416`, `:438`).
- What: `created_time` is fetched but not mapped. The lead's `created_at` is the import time, the touchpoint has no `submitted_at`, and the CAPI `New` event (D5 in the CAPI review) uses the import time. After an outage or catch-up, these can be hours or days late.
- Fix: map `created_time` to `conversion_time`, which the lifecycle already turns into the touchpoint `submitted_at`, and let the CAPI `New` event prefer it.
- Risk: changes stored data and the `event_time` sent to Meta.

**L-D2 (P1, reliability): a broken token is retried like a network error and leads are lost after 7 days.**
- Where: `process()` and `catch_up()`.
- What: error 190 or a lost permission fails every job 5 times, then each waits as `failed`; the only signal is the last status `retry_required`. Jobs expire after 7 days, and catch-up never looks back more than 7 days, although Meta keeps leads for 90.
- Fix: on 190 or a permission error, pause imports, record `auth_failed`, show an admin notice, and after reconnecting let catch-up look back up to 30 days (or 89).
- Risk: adds an admin notice and changes retry timing and the catch-up window.

**L-D3 (P2, attribution): organic form leads are labelled "Meta Ads".**
- Where: `map_meta()` sets `source` to `Meta Ads` for every lead.
- What: leads with `is_organic=true` came from the Page, not an ad. Reports overstate ad leads, and these leads count for ad-source rules (CAPI, manual-lead reporting).
- Fix: use a source such as `Meta (organic)` when `is_organic` is true.
- Risk: changes source values used in reports and filters.

**L-D4 (P2, privacy): more lead fields are requested and stored than the CRM uses.**
- Where: `META_FULL_FIELDS` at `:13` and the encrypted snapshot at `:450`.
- What: `home_listing`, `vehicle`, `retailer_item_id`, `post`, `post_submission_check_result` and `partner_name` do not apply to interior design forms. `custom_disclaimer_responses` holds consent checkbox answers, which may be worth keeping as evidence.
- Fix: request base and attribution fields plus `custom_disclaimer_responses` only.
- Risk: changes stored data.

**L-D5 (P3, operations): admins see only the last status.**
- Where: `settings_context()` and the connector card.
- What: there is no count of queued or failed jobs and no last-success time, so a stuck queue is invisible.
- Fix: show queued, failed and last-imported counts with a "Retry failed imports" button.
- Risk: UI change.

**L-D6 (P3, housekeeping): import receipts are never pruned.**
- Where: `finish()` at `:370`.
- What: one `idlm_connector_receipt_*` option per imported lead is kept forever. It holds only a keyed hash and an outcome, so it is not personal data, but the options table grows with every lead.
- Fix: prune receipts older than the 7-day replay window (30 days if L-D2 widens it). Erasure markers already stop re-import of deleted leads.
- Risk: changes retention.

### Leave alone

- Webhook signature check, verify challenge and 100-change batch limit. A malformed change rejects the whole batch, but Meta retries for 36 hours and catch-up recovers the valid leads.
- Encrypted jobs and attribution, the dedup fingerprint shared by webhook, catch-up and hosted pull, and erasure markers checked before import.
- Facebook Login: single-use state, long-lived Page token, existing subscribed fields preserved, intake paused before credentials are replaced.
- Catch-up paging with cursors only, never following provider URLs.
- Graph version from `IDLM_CAPI_Transport::GRAPH_VERSION`.

## Tests

Existing coverage is strong: native tests cover webhook and direct arrival order, replay, erasure, failed retrieval with rotated credentials, and the field fallback. Added for L-S1 and L-S2: fallback is skipped on rate-limit and token errors and kept on error 100 or unknown errors; mapping errors go straight to `failed` and recover through retry.
