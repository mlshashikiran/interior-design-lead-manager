# INT-005 — CAPI manual retry identity and eligibility

Author / Builder: **Shashi**. Date: **2026-10-09**. Local candidate **1.102.66**, base `ff148ba62ec710a622d9b1af7e8f511b7e0b7385`. Core **1.3.9**, routing **1.0.1**, quotation **2.5.4** and lifecycle receipt **1.1.0** are unchanged.

## Finding and current-source confirmation

The immutable [INT-005 finding](audit-2026-10-06/INTEGRATION-SECURITY-REVIEW.md#int-005--medium--manual-capi-retry-cannot-replay-current-google-defaults-and-bypasses-worker-gates) records hardcoded retry event aliases, guessed New-stage fallback, forced provider calls and a latest-history query that can report an older success as a new send. The bounded Graphify query **INT-005 / retry_capi_log / process_capi_event** located the finding and the separate worker/manual paths. Current source confirmed those defects. M4 and T08/T14 require explicit event/config identity, current dispatch eligibility and retry/uncertainty policy.

## Dispatch and identity policy

A shared platform dispatch boundary now reads current lead, enablement, mapping, destination and credentials directly from primary SQL. Ordinary worker calls, direct platform calls, forced attribution replay and manual retry share its lead/platform advisory lock. Missing/deleted/duplicate leads, reversed event conditions, disabled integrations and missing Google/LinkedIn attribution are refused. Qualified retains the existing quality-or-stage predicate; Quote Shared requires Quote Shared/Won, Won requires Won and custom stages require exact current stage. New remains the initial contact event. Forced attribution deliberately bypasses prior-success deduplication only. Successful-event read failures refuse dispatch.

Google's actual defaults **CRM Qualified Lead** and **Closed Won Interior Project** resolve exactly, as do configured platform event names. Manual retry accepts existing failed logs only and never guesses New or uses substring/legacy aliases. A legacy payload with an explicit stage must match the current mapping; otherwise exactly one mapping must match its event. Ambiguous or changed mappings refuse HTTP.

New local `_idlm_delivery` log metadata records platform/lead, stage/event, destination, event ID/time, value/currency, exact attempt ID, retry root and attempted/retryable/outcome state. It is added **after HTTP**, contains no credential and is never included in provider JSON. A retry preserves those original destination and commercial/event values while rebuilding current contact/attribution and using the current key. A destination or event-name change requires review instead of silently retargeting the old conversion. Existing provider endpoints, payload shapes and response acceptance parsers remain unchanged.

Legacy logs usually lack original destination and Google occurrence time. Those facts cannot be recovered: retry uses the current destination when the old one is unknown, retains payload timing/value where present, otherwise uses retry time/zero value and current currency. An exact mapping is not proof of historical configuration. Legacy malformed, ambiguous or obsolete identities need review; no historical facts are invented.

## Manual claims and reporting

Each root failed log has a minimal nonautoload `idlm_capi_retry_<sha256>` receipt bound to the recovery generation. A root advisory lock and checked durable processing receipt precede dispatch. Three **actual HTTP attempts** are allowed across the same root chain, including retries selected from newly failed result logs. Ineligible/no-send attempts do not consume that count. HTTP 401/403/429/5xx failures remain retryable; other permanent failures are blocked. Transport failure, failed outcome persistence and process interruption retain uncertainty and refuse automatic replay. Accepted outcomes must have a newly inserted log and a checked receipt acknowledgement to report success. If acceptance acknowledgement fails, success is withheld and the unresolved claim prevents another send.

Active caller transactions are refused at the provider boundary. Recovery/session locks fence restore/reset. The per-lead/platform lock also serializes competing manual and ordinary workers, but this is not a durable claim for every ordinary CAPI job or a provider exactly-once guarantee. A settings/lead change after the final check cannot recall an in-flight request.

`log_capi_event()` returns its exact inserted ID or zero. Manual retry returns that attempt's result directly, without querying old success history. The administrator AJAX action retains its nonce and capability checks, validates 1–50 scalar positive decimal IDs before dispatch, deduplicates them, preserves `data.message` and adds per-selection source/result log IDs, attempt IDs and success flags. Existing UI uses the accurate message. Bulk retry remains synchronous.

Retry receipts contain no contact payload/key, are excluded from backup export/import and erased on successful restore/reset. Log payload storage remains redacted PHP serialization; no columns or schema versions change. Minimal receipt retention is still pending.

## Executed verification

- **324 fast / 0 failed**, including six INT-005 cases.
- **11 focused native / 0 failed** (fresh schema plus ten INT-005 cases), then **251 full native / 0 failed** across the shared/core surfaces.
- **398 full actual HTTP / 0 failed**, including **6 focused actual HTTP / 0 failed** with anonymous/nonadministrator/nonce denials, no-write snapshots, bounded/malformed selections, both Google defaults, duplicate input, disabled dispatch and prior-success refusal.
- Actual composite CRM shortcode at desktop and **390 px mobile**: a pending event plus prior success reports **1 succeeded, 1 failed**; selecting the same original after delivery reports **0 succeeded, 1 failed**. Independent SQL shows exactly one new browser log (ID 9 for source ID 6), and intercepted HTTP captures show two acceptance-case requests plus that single browser request. No PHP request diagnostics.
- Changed PHP syntax and whitespace checks are recorded in final evidence.

Native cases exercise defaults/custom names across platforms; live duplicate/deletion/reversal/disable and attribution gates; legacy ambiguity; destination changes; independent primary credential rotation after warmed reads; saved value/currency/time/Meta event ID with fresh contact; bounded root-chain failure; permanent/transport outcomes; injected claim/log/acknowledgement failures; competing manual/manual and manual/ordinary processes; process exit 77 after simulated acceptance; real backup/restore/reset cleanup; active transactions and malformed metadata.

All provider calls/mail were intercepted, including independent worker/crash tests. Synthetic acceptance proves local control flow, not provider ingestion or idempotency. The initial fast fixture omitted loading `IDLM_CAPI`; adding its production class fixed the fixture before final successful runs. Full native stderr contains the existing intentional identity-review required-write diagnostics; focused native/HTTP stderr is empty, and fast stderr retains its existing synthetic missing-phone warning. [Fast log](remediation-2026-10-09/capi-retry-slice/fast-final.log), [full native log](remediation-2026-10-09/capi-retry-slice/native-full.log), [full HTTP log](remediation-2026-10-09/capi-retry-slice/http-full.log), [focused HTTP log](remediation-2026-10-09/capi-retry-slice/http.log), [browser and SQL evidence](remediation-2026-10-09/capi-retry-slice/browser-observations.json), [commands, hashes and cleanup](remediation-2026-10-09/capi-retry-slice/verification.json).

Environment: PHP **8.4.14**, WordPress **7.1.3**, MySQL **8.4.0**, WP-Cron, no persistent cache. Only newly owned disposable `idlm_test_*` databases/content and synthetic identities were used, with approved sandbox escalation for the owned socket. Configured-site data was untouched. Owned test schemas/content were removed, the socket-only server stopped and its temporary data erased. No real provider send, commit, package or deployment.

## Qualification and next work

**INT-005 is IMPLEMENTED, not CLOSED**. Backlog: **12 OPEN / 32 IMPLEMENTED**, none CLOSED. Live provider acceptance/idempotency/reconciliation, live Action Scheduler, persistent-cache/drop-in/distributed database behavior, receipt volume/retention and exact artifact/CI/runtime qualification remain open. Ordinary CAPI durable jobs/global retention and platform-secret migration remain INT-008/INT-010 work. Whole T08/T14/M4 groups remain open. Scoped graph refresh re-extracts changed source and explicit documentation headings; no full semantic/report regeneration.

Next: **INT-006 — Meta webhook complete batches and replay**.
