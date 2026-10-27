# First remediation slice: quotation mutation authority

Author / Builder: **Shashi**. Implemented and verified locally: **2026-10-06**.
Candidate: **1.102.43**, dirty working tree on base `ff148ba62ec710a622d9b1af7e8f511b7e0b7385`. No release artifact, commit, deployment or production-readiness claim.

QUO-001 and QUO-003 are **IMPLEMENTED**, with local native/HTTP/browser evidence. They are not marked CLOSED: the advertised support floor, configured runtime matrix and exact-artifact qualification remain outstanding. The immutable [October 6 audit](audit-2026-10-06/FINDINGS.md) retains its original observations. Other findings remain OPEN.

## M0 baseline and scope

Before editing, 592 tracked/untracked candidate files were SHA-256 hashed, and the complete tracked dirty diff and status were saved under `/private/tmp/idlm-slice-baseline`. The original dirty candidate was reconstructed under `/private/tmp/idlm-slice-original` for before/after verification. No reset, checkout, stash or discard was applied to the user's workspace. Original source/vendor/asset changes remain part of the candidate. Only the listed slice files were subsequently edited; [baseline hashes](remediation-2026-10-06/quotation-slice/baseline-source-sha256.json) and [status](remediation-2026-10-06/quotation-slice/status.txt) identify the starting bytes.

The focused Graphify query **“Quotation mutation authorization revision request key”**, budget 1,400 tokens, linked the quotation repository, controller-facing audit, access policy and quotation JavaScript. Source verification established that read visibility was used by save/autosave/discard, and new saves could update an incoming persisted revision. The graph is navigation evidence, not the authorization proof.

M0 is complete **for this slice**, not globally. The audit's 16 operation families / twelve flow contracts remain the inventory index; this slice characterizes T10/GAP-10 and the exact-revision access children of T12/GAP-12. Existing fast doubles supply a deterministic synthetic clock. The disposable HTTP harness supplies real cookies, nonce generation, real WordPress dispatch, synthetic users/leads, independent HTTP workers and bounded file barriers for authority changes before locking reads. Expiry/fake-clock HTTP, aggregate fail-point injection, unrelated transports and full multi-worker durability qualification remain assigned to subsequent milestones.

## Authorization and identity contracts

| Actor / grant | Quotation reads | Create / save / autosave / discard |
|---|---|---|
| Current owner with required quote capabilities | Existing configured read scope | Allowed on live owned lead |
| Non-owner representative | Existing assigned/read-all policy | Denied on another owner's lead |
| Representative with explicit All leads read scope | Broader reads remain allowed | Only live owned leads |
| Explicit assignment manager (`idlm_assign_leads`) or administrator (`manage_options`) | Existing configured read scope | Allowed on existing leads, with required quote operation capabilities |
| Revoked quote capability, disabled CRM user or pre-sales user | Existing effective quote-capability check | Denied |

Read scope does not grant mutation authority. Explicit manager capability remains mutation authority even with an Assigned-only read preference; that preference still scopes read transports. Manager configuration/catalogue capabilities alone do not grant lead mutation authority. No roles, capability names or saved visibility defaults are migrated in this slice. CORE-001 and QUO-002 remain separate work.

- New quote requests must have `revision_id=0`; even an owned persisted revision is rejected before persistence.
- Existing requests must bind the posted lead to the quote and the source revision to that quote. The source must be the current Draft under lock for a new save; stale sources cannot create another revision.
- A completed request UUID is bound to the actor, lead and initial-create operation, or to the actor, lead, quote and exact source revision for a revision save. An authorized retry returns the existing result without writes, including after the source becomes Superseded. Cross-lead, cross-quote, cross-source and cross-actor substitutions fail with a generic denial.
- Missing/invalid request UUIDs retain the existing generated-key behavior; clients still need a valid stable UUID for retry recognition.
- Save locks the live lead before the quote and writes, then refreshes user and role grants after a lock wait. Autosave/discard use the same lead-before-quote locking order and recheck the current Draft relationship. These paths recheck current ownership, CRM suspension and operation capabilities.
- Initial number reservation now participates in the caller-owned authorized save transaction. A denial cannot advance numbering, and reservation cannot independently commit and release the lead lock. Standalone number reservation retains its existing own-transaction default.
- Every saved revision is inserted into its authorized aggregate. The request-selected update branch is removed. History, source supersession, totals, lead quote projection, own working-copy cleanup and successful redirects retain their established workflow.

Editor/detail controls use mutation authority; read-all users can read a visible quotation but do not receive its edit control or working copy. Existing AJAX/admin-post callback names, routes, nonces, payload keys, shortcodes, selectors and assets are unchanged. Corrected unauthorized requests intentionally stop succeeding. Schema versions are unchanged.

## Slice inventory and fixture safety

Affected public callbacks remain `IDLM_Quotations::handle_save`, `ajax_autosave_quote`, `ajax_discard_quote_autosave` and renderer delegates. Transport names remain `admin_post_idlm_save_quote`, `wp_ajax_idlm_autosave_quote` and `wp_ajax_idlm_discard_quote_autosave`. Exact-revision CSV/export authorization is characterized through its existing transport, not replaced.

The quote aggregate includes leads, shells, revisions, sections, items, milestones, history, number sequences and lead activity. Working-copy keys remain server-derived `idlm_quote_autosave_{user}_{revision}`. The denial oracle snapshots **every actual `wp_idlm_*` table**, user-scoped working-copy option rows, WP-Cron state and intercepted outbound-attempt records; snapshot SQL errors fail the test. It also rejects synthetic PII in denied response bodies/headers. This inventory is independent of the plugin's backup/reset table manifest.

`tests/integration/quotation-http.php` creates a new `idlm_test_*` database through the existing refusal/ownership guard, copies WordPress core to an owned temporary tree without the configured site's content/configuration, and uses isolated content/uploads, cookies and a loopback server. A generated MU plugin intercepts HTTP/mail before WordPress install; cron spawning is disabled. The complete plugin runtime is booted for the final HTTP run. Shutdown cleanup terminates the owned PHP server, drops only the newly created fixture database and removes the owned content tree, including after handler exits/errors. An owned MySQL 8.4 server was initialized in `/private/tmp/idlm-slice-mysql`, with network listening disabled; no configured-site database was used.

The original candidate was verified with unchanged runtime files and the newer fixture-only outbound guard. The first same-harness HTTP baseline had **34 PASS / 12 FAIL** (46 cases), exposing read-all writes, new-revision/key substitution and absent locking-read barriers. Failed malicious baseline cases can contaminate later cases in that deliberately vulnerable fixture; this count is not 12 independent defect claims. The final expanded suite has additional nonce, role-revocation and alternate-mutation cases.

## Executed verification

Evidence directory: [quotation slice](remediation-2026-10-06/quotation-slice/verification.json). Runtime: PHP **8.4.14**, WordPress **7.1.2** from local core, MySQL **8.4.0**, default InnoDB, WP-Cron, no persistent object cache. CLI/native/HTTP run only against owned disposable fixtures.

| Check | Actual result | Evidence |
|---|---|---|
| Original fast suite | 174 PASS / 0 FAIL | `fast.log` |
| Original native suite | 17 PASS / 0 FAIL | `native-original.log` |
| Original same-harness HTTP characterization | 34 PASS / 12 FAIL | `http-original.log` |
| Final fast suite | 177 PASS / 0 FAIL | `fast-final.log` |
| Final broader native suite | 17 PASS / 0 FAIL | `native-verified.log` |
| Final complete-runtime HTTP suite | 68 PASS / 0 FAIL | `http-verified.log` |
| Inbound/task and Action Scheduler contracts | PASS | `inbound.log`, `interakt.log` |
| Unrelated integration audit | 6 PASS / 7 FAIL, unchanged open findings | `audit-integration.log` |
| Quotation audit after repair | QUO-S01 and forged QUO-S09 PASS; six unrelated observations still FAIL | `audit-quotation-after.log`, `forged-after.log` |
| Changed-file PHP lint and whitespace checks | PASS | `php-lint.log`, `verification.json` |

Named HTTP cases map directly to T10/GAP-10 (`T10-H-*`, `T10-C-*`) and T12/GAP-12 (`T12-H-*`). They cover owners/non-owners/read-all/managers; foreign lead/quote/revision/key combinations; foreign autosave/discard identities; missing/cross-user nonces; user and role capability revocation; reassignment; authorized owner/read-all-owner/manager saves; autosave/discard; initial/revision retries; stale revisions; parsed exact persisted old-revision CSV; denied exports; no denial writes/jobs/outbound/PII. Nine independent-worker barriers cover create/autosave/discard × reassignment/user-capability revocation/role-capability revocation. HTTP regressions are added to the existing CI runtime jobs; CI itself has not run here.

Browser checks used the Codex in-app Chromium browser and the disposable fixture: owner detail/editor, autosave, working-copy restoration, revision remark dialog and actual form submission creating R02; administrator utility detail/editor parity; read-all foreign detail with zero edit controls. The saved browser revision retained R01 and produced quantity 2.01 / subtotal 201 / grand total 237. Screenshots: [owner revision](remediation-2026-10-06/quotation-slice/owner-revision.png), [read-all detail](remediation-2026-10-06/quotation-slice/readall-detail.png). Console errors were absent after completing the fixture's registered dialog assets. Initial fixture-only missing-dialog/admin-screen errors were corrected; the final automated HTTP run is warning-free. The direct browser denial redirect reached an initially absent fixture legacy-admin destination, after which Browser Use's generated error-page policy prevented continuing that tab; the equivalent HTTP editor denial passed. No browser denial completion is claimed.

UI-001 is still reproducible: quantity 2.05 (and ordinary 1/2 per the audit) does not satisfy the existing browser step rules. Browser save used 2.01, a currently accepted quantity, without changing that unrelated field contract. Full mobile/accessibility/layout qualification is NOT RUN here. PDF/XLSX format parsing, exact-artifact installation, Action Scheduler native HTTP mode, persistent-cache/multi-node revocation, MyISAM/engine remediation, WP 5.8 and PHP 7.4/8.1 × WP 6.6/latest qualification are NOT RUN. This does not close T10/T12 as entire groups or establish production readiness.

## Next slice

Address **CORE-001, QUO-002 and CORE-011** together: effective capability/default scope, live ownership in quotation list/count and task queue reads, mixed-role/read-all/manager read parity, warmed cache/cursor revocation. Reuse this fixture, and add independently synchronized read/cache tests. Engine/durability work (QUO-007 and M2) and UI-001 remain separate planned slices.
