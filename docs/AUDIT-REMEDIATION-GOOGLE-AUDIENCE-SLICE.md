# INT-007 — Disabled Google audience pull feed

Author / Builder: **Shashi**. Date: **2026-10-09**. Candidate: **1.102.68**, local/uncommitted; schemas unchanged. Status: **IMPLEMENTED, not CLOSED**.

## Reproduction and correction

The baseline callback emitted the audience CSV header for a disabled Google rule when presented valid synthetic feed credentials. [Reproduction](remediation-2026-10-09/google-audience-slice/reproduction.log) records expected 404 versus actual CSV emission. The immutable October 6 audit remains unchanged.

The Graphify query “Google audience feed enabled rule scheduler”, bounded to 900 tokens, identified `IDLM_Integrations` and `IDLM_Audiences`. Current source verified the feed omitted enabled filtering while batch sync filtered enabled rules. `IDLM_Audiences::get_active_rules()` now owns that decision for scheduled/manual batch sync and `resolve_google_feed_rule()`. Existing AJAX/scheduler callbacks continue calling the same batch executor; REST routes, settings and CSV schema remain stable.

## Behavior and compatibility

- A valid credential remains necessary before rule resolution. Existing Basic and dedicated-header authentication, rotation and rate limits remain enforced.
- Disabled, missing-enabled, removed or unmatched rules return 404 before contact queries and CSV headers. Missing/non-string/blank list IDs return 400. Enabled rules preserve stage/call-status/quality AND filters and canonical nonduplicate selection.
- Disabled duplicate targets are skipped. The first enabled Google match in saved order wins; two enabled duplicates retain deterministic first-match selection. Bare IDs and full resource IDs retain their existing aliases; no union of duplicate filters is introduced.
- Legacy Meta/Google/LinkedIn fallback remains read-only and applies only when the saved rules option is absent. An explicit empty, disabled or corrupt saved list never resurrects legacy rules. Invalid rule structures are skipped; missing optional filters default to `all` consistently with the existing pull filter semantics.
- Confidential feed responses use `Cache-Control: private, no-store, max-age=0`. Already retrieved downloads and requests already authorized at selection cannot be recalled. Re-enabling affects the next pull. Existing legacy-only Google configuration can now use the same active rule as batch sync.
- No configured-site settings/data, real provider sends, schema changes, commit, package or deployment were performed. Manual delivery retries/exclusions and provider-side audience deletion remain outside INT-007.

## Executed verification

Environment: PHP **8.4.14**, WordPress **7.1.3**, MySQL **8.4.0**, WP-Cron, persistent cache off. Native/HTTP runs used a new owned `idlm_test_*` database each time and a dedicated temporary socket-only server; HTTP/mail interception remained active.

| Evidence | Result | Scenarios |
|---|---|---|
| `php tests/run.php` | 335 PASS, 0 FAIL | Five new INT007 cases: denial before contact reads, active duplicate/live toggle, explicit/legacy precedence, malformed structures and list input |
| `IDLM_TEST_FILTER=INT007 php tests/integration/run.php` | 4 PASS, 0 FAIL | Fresh schema plus T07-N disabled denial/no lead query; T08-N batch no disabled work or queue clearing, enabled Google logging; T07-N live explicit/legacy precedence |
| `php tests/integration/quotation-http.php --google-audience-only` | 7 PASS, 0 FAIL | T07/T08-H credential absence/wrong/query/rotation, both credential modes/routes, CSV parsing/length/hash/phone/filter parity, disable/re-enable/removal, duplicate order, legacy/malformed/missing/wrong-platform rules, no-store and rate limiting |
| Changed PHP files | Lint PASS | Runtime, bootstrap version and modified/new test PHP |

The first fast failure was test array ordering, corrected to compare the intended legacy field values. The initial HTTP run reached the production per-IP rate limit; tests now reset only their synthetic rate bucket between children and separately assert that a full bucket returns 429 without CSV. Socket-server initialization failed under the sandbox and succeeded under approved escalation. The fast runner retains seven pre-existing fixture warnings (`num_rooms`/`phone`), identical to the INT-006 final log; no new warning was introduced. Native and actual HTTP output is warning-free. Final logs and source hashes are indexed in [verification.json](remediation-2026-10-09/google-audience-slice/verification.json).

## Remaining qualification

No live Google Data Manager/provider acceptance, provider polling/cache behavior, removal/consent policy, persistent-cache or distributed settings races, supported PHP/WordPress matrix, volume qualification or exact release artifact is claimed. A configuration change after selection may race an in-flight response. INT-009 still owns failed manual-work retention and exclusions; INT-008 owns CAPI retention. Focused tests establish INT-007 implementation without closing T07/T08 globally. The backlog is **10 OPEN / 34 IMPLEMENTED**. Next: **INT-008**.
