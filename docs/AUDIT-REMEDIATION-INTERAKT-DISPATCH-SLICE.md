# INT-004 — Ordinary Interakt dispatch eligibility and credentials

Author / Builder: **Shashi**. Date: **2026-10-09**. Local candidate **1.102.65**, base `ff148ba62ec710a622d9b1af7e8f511b7e0b7385`. Core **1.3.9**, routing **1.0.1**, quotation **2.5.4** and lifecycle receipt **1.1.0** are unchanged.

## Finding and policy

The immutable [INT-004 finding](audit-2026-10-06/INTEGRATION-SECURITY-REVIEW.md#int-004--medium--ordinary-queued-interakt-ignores-disabling-and-retains-revoked-key) records contact payloads and a plaintext API key in ordinary scheduled arguments, with an existence-only worker check. The bounded Graphify query **INT-004 / process_async_track_event** located that finding, the ordinary worker and the prior lifecycle slice's deferred provider limits. Current source confirmed the copied key/payload and synchronous fallback.

Ordinary lifecycle delivery now belongs to `IDLM_Interakt_Dispatch`. Its nonautoload `idlm_interakt_dispatch_<sha256>` option stores a minimal job: lead ID, event, intended field/value, owner ID, recovery generation, enable epoch, timestamps, attempts and outcome. Scheduled arguments contain **one opaque option key**, using Action Scheduler's single-action API when available and **WP-Cron otherwise**. No synchronous ordinary send remains. Phone, email, name, property/budget traits and API credentials are assembled only at dispatch.

Current primary database reads bypass stale WordPress option caches. Dispatch requires enabled integration, a current nonempty key, matching enable epoch, an existing nonduplicate lead with usable phone, unchanged intended owner, and the original field/value rule still present and satisfied. Supported mapping fields are stage, call status and lead quality; the configured legacy No Answer event remains supported. Unconfigured events are skipped. Phone/trait edits use current values; owner changes and reversed/removed rules cancel the pending event. Key rotation uses the new key.

Enabling after a disabled state writes a fresh epoch through the WordPress option filter, cancelling earlier queued work even if the integration is enabled again before execution. A failed epoch write refuses enable. This is cooperative WordPress settings behavior; direct SQL edits bypass the enable hook. Current disabled state is still checked from primary SQL.

Canonical mapped intents retain their original rule/owner. Legacy No Answer intents also retain their original event name, so delayed outbox replay cannot reinterpret an old event under a new fallback name. Outbox handoffs derive a stable job identity from the durable outbox ID; rejected scheduling retains the intent for its existing bounded replay. Ineligible work is terminally skipped. Trusted direct hook calls represent new events and return a job key, `null` for ineligibility, or `false` for failed handoff.

## Claims, failures and compatibility

A MySQL advisory lock serializes each job. A checked durable `processing` claim and recovery/session-lock checks precede HTTP; callers with active transactions are refused. A final eligibility read precedes fresh payload construction and current-key lookup. A settings/lead change after the last check cannot recall an in-flight external request.

Accepted HTTP results become `sent`. A transport/provider failure is conservatively `uncertain`; no automatic resend follows. Replaying an interrupted `processing` claim also changes it to `uncertain` without HTTP. Failed outcome persistence leaves `processing`, preserving the same conservative behavior on replay. A failed claim write performs no HTTP. Lock contention requests a delayed callback; scheduler acceptance remains subject to the backend.

Inspect the opaque job with `IDLM_Interakt_Dispatch::inspect($key)` or privileged WordPress option tooling. The job stores `status`, `reason`, `attempts` and attempt time. `interakt_uncertain` lead activity includes the job ID for reconciliation. Check the provider's records before an explicitly authorized new event; an uncertain claim is never automatically reopened. No provider idempotency guarantee is asserted.

The existing async callback name/signature accepts the new single-key format and deliberately refuses old payload/key callbacks without sending or copying their arguments into a retry. **Previously queued ordinary automations using that legacy format will not deliver.** Existing copied credentials in Action Scheduler history/backups are not automatically erased by this slice; purge/rotation and remaining platform-secret migration belong to INT-010/release qualification. Administrative backfill delivery remains separate, with its existing retry/uncertainty limitations.

New dispatch options are excluded from plugin backup export/import and erased during successful restore/reset; the recovery generation fences stale callbacks. Public request handlers, nonce/capability policy and physical schemas are unchanged.

## Executed verification

- **318 fast / 0 failed**, including six new ordinary delivery cases.
- **240 full native / 0 failed**, including nine initial INT-004 cases, then **11 final focused native / 0 failed** (fresh schema plus ten INT-004 cases). Across those native runs, **241 distinct cases** passed. The focused final run qualifies the last fallback/active-transaction safeguards.
- The optional **Action Scheduler adapter contract passes** for opaque arguments, fresh key, replay, disable and enqueue failure, while retaining the existing batch retry contract. This uses a test adapter, not an installed live Action Scheduler runtime.
- Changed PHP files pass syntax checks; `git diff --check` passes.

Actual WordPress/MySQL evidence covers scheduled WP-Cron arguments; warmed stale credentials changed by an independent connection; fresh phone/name; disable, deletion, duplicate, reversal, ownership/mapping and invalid-phone cancellation; disable/re-enable epoch; failed enable write; malformed job; legacy callback refusal; claim/result write failures; rejected enqueue and stable outbox replay; two independent competing workers; simulated provider acceptance followed by process exit; backup exclusion, restore/reset erasure; delayed fallback identity; active-transaction refusal.

All provider calls and mail were intercepted. Crash tests simulate provider acceptance locally and do not establish external provider behavior. [Final fast log](remediation-2026-10-09/interakt-dispatch-slice/fast-final.log), [full native log](remediation-2026-10-09/interakt-dispatch-slice/native-full.log), [final focused native log](remediation-2026-10-09/interakt-dispatch-slice/native-final.log), [AS adapter log](remediation-2026-10-09/interakt-dispatch-slice/as-final.log), [commands, hashes and cleanup](remediation-2026-10-09/interakt-dispatch-slice/verification.json).

The initial native run had three fixture failures: Interakt hooks had not been initialized by the generic native loader, and prior direct deletion left an invalid aggregate for the backup case. Initializing the relevant hooks and constructing a clean backup aggregate fixed those fixture problems. Evidence is retained. Fast stderr retains its existing synthetic missing-phone warning; full native stderr contains the existing deliberate identity-review write-failure diagnostics. Final focused native and AS stderr are empty.

Environment: PHP **8.4.14**, WordPress **7.1.3**, MySQL **8.4.0**, WP-Cron, no persistent cache. Only newly owned `idlm_test_*` databases/content and synthetic identities were used. The sandboxed MySQL initialization crashed and socket access was denied; isolated test execution succeeded with approved sandbox escalation. Configured-site data was untouched. Owned schemas/content were removed, socket-only MySQL stopped and temporary data removed. No real provider send, commit, package or deployment.

## Qualification and next work

**INT-004 is IMPLEMENTED, not CLOSED**. Backlog: **13 OPEN / 31 IMPLEMENTED**, none CLOSED. Live Action Scheduler, provider acceptance/idempotency and reconciliation, persistent-cache/drop-in/distributed database behavior, volume/retention of minimal claims, legacy argument purge and exact artifact/CI/runtime qualification remain open. Current predicates do not provide a complete audit of intervening state changes that return to the same value. Whole T09/T14/M4 groups remain open. Graph refresh is scoped to changed source structure and explicit documentation headings, without full semantic/report regeneration.

Next: **INT-005 — manual CAPI retry defaults and worker eligibility**.
