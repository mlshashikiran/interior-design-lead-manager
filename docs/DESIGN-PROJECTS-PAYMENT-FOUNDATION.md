# Design Projects — payment foundation checkpoint

Builder: Shashi  
Date: 2026-11-05  
Status: Local implementation, first delivery slice. No deployment or release artifact.

The implementation authorization in this task supersedes the planning-only status of the two agreed planning documents. Those documents have been preserved. This checkpoint records actual shipped local code rather than changing the remaining requirements into completed work.

## Architecture lookup

Focused Graphify query: `quotation milestone snapshot private file reference lead transaction outbox extension` (budget 1,600). Material finding: the existing lead transaction/outbox and private-file reference owner are the integration boundaries, rather than quotation settings or a separate public payment endpoint. Verified against their source and `IDLM_Quotation_Document::read/capture`. The local graph receives a bounded structural refresh for the changed source and focused documentation; historical graph HTML is not a fresh release artifact.

## Implemented behaviour

The Payments workspace is available within `[idlm_workspace]` and as an administrator utility page. Administrators can grant **Record lead payments** (`idlm_record_lead_payments`) and the separate **Correct payments and amend payment schedules** (`idlm_correct_lead_payments`) through existing Users & Access role permissions. Existing role decisions are preserved; upgrades do not silently add grants. Enabled sales users require a live owned lead or existing assignment-manager scope in addition to the operation grant. Pre-sales and suspended users are denied.

A canonical Won lead receives an explicitly published schedule based on an exact saved quotation revision. The user explicitly maps one or more saved milestone positions as advance. The saved currency, grand total, terms and expected milestone amounts are copied without reading today's quotation settings. Missing historic terms require explicit authorized recovery as described below. Schedule amendments require the correction grant and a reason; they append a schedule version and allocation snapshot rather than changing historic rows.

Each actual receipt requires a positive amount, valid received date, method and private screenshot or URN/reference; both evidence types are allowed. The uploader accepts JPEG, PNG or WebP up to 10 MiB, stores bytes through the existing private-file service and checks the payment grant after that service's lead lock and actor refresh. The receipt service checks the same lead, private purpose, MIME, state and expiry. Temporary files expire under the existing seven-day policy; referenced historical evidence remains protected after correction or revocation. Downloads retain live lead access and private/no-store policies.

Shortfalls of any size are accepted. Balances show expected/received/pending advance, total received/outstanding, each milestone, unallocated credit, excess credit and unscheduled balance. The minor-unit allocator applies each distinct current non-revoked receipt once, in receipt identity order to oldest pending milestones. Subsequent receipts clear prior pending amounts without creating additional payable rows. Corrections and revocations append complete receipt versions with actor/time/reason, preserving their predecessors and prior allocation snapshots. A reused normalized reference on the same lead and method requires an explicit meaningful duplicate-review reason; it is not globally rejected.

Payment operations never update the sales owner, lead stage, Won value, sales events or quotation contents. They do not submit handover, create projects, assign design work or start it.

## Persistence and transaction contract

Core schema is **1.3.10**, independently of the unchanged local plugin release version. `IDLM_DB::create_tables()` declares and physically verifies five additive InnoDB tables:

| Table | Responsibility |
|---|---|
| `idlm_lead_payment_schedules` | Immutable versioned revision/terms/milestone/advance snapshots |
| `idlm_lead_payment_confirmations` | Stable receipt identity and current-version pointer |
| `idlm_lead_payment_versions` | Append-only amounts, evidence, dates, corrections and revocations |
| `idlm_lead_payment_allocation_sets` | Immutable complete balance/allocation snapshots for every mutation |
| `idlm_lead_payment_events` | Audit identity, optimistic context version, actor-bound idempotency key/hash and exact schedule/receipt/allocation references |

Milestones live in the immutable schedule snapshot, and allocations in a complete versioned set, rather than separate mutable milestone/allocation rows. Stable `position_N` keys preserve legacy saved timeline positions. This intentionally reduces the proposed table count while preserving schedule, receipt and allocation history.

All operations use the checked `IDLM_Lead_Transaction` owner with recovery fencing. New tables plus quotation and private-file metadata are pinned/checked for InnoDB before work. Lock order is lead, actor/grants, payment context and then quotation/revision or proof as applicable. Authoritative context/event/receipt reads use current locking reads: a repeatable-read snapshot established before a lead-lock wait must not authorize a second stale write. Required version, identity, allocation, event and durable-intent failures roll back together. Request keys are bound to actor/operation/normalized details; identical retries return the prior event without another receipt. Independent concurrent requests with the same expected version allow one writer and reject the stale writer.

The lead outbox explicitly permits `idlm_lead_payment_schedule_published`, `idlm_lead_payment_recorded`, `idlm_lead_payment_corrected` and `idlm_lead_payment_revoked`. Each hook receives lead ID plus a version-1 envelope with event/schedule/receipt-version IDs, actor and UTC occurrence time. No raw URNs, screenshots or customer details are dispatched. Delivery remains at least once; consumers must deduplicate by event identity. A callback failure retains intent and does not undo the committed payment.

Lead erasure/reset includes all five tables in its checked dependency manifest. Private-file erasure remains owned by its existing journal. Until the release-integration slice implements complete portable validation, partial JSON backup/recovery refuses a database containing ledger schedules, including a second restore check under the recovery fence. Snapshot refusal occurs within its consistent snapshot. Use a full WordPress database backup for payment history and private bytes.

## Verification

Local runtime: PHP 8.4.14, WordPress 7.1.3, disposable MySQL 8.4.0, WP-Cron and no persistent object cache. Configured-site data and external providers were not used.

- 408 fast checks pass, including four new payment rule groups.
- 355 accumulated native checks pass at the initial payment checkpoint. Three additional focused checks then brought the payment run to 13 passing checks including its fresh-schema prerequisite: independent concurrent writers, durable callback replay and ledger erasure/JSON refusal.
- 483 accumulated HTTP checks pass, including five new payment groups and the diagnostic-free affected-request check at that checkpoint. A subsequent seven-check focused HTTP run additionally covers manager-only correction/revocation and retention of historical proof. Coverage includes actual cookie/nonce and ownership denials, saved schedule publication, retry-safe URN receipts, missing/forged evidence, multipart screenshot-only receipt, expiry-independent referenced download, anonymous/foreign download denial, unique labels and read-only views.
- Syntax checks cover every changed PHP file; whitespace validation passes.
- Browser inspection exercised the composite workspace at desktop and 390 px mobile, verified focusable labelled receipt controls and no horizontal mobile overflow. The redesigned view reuses CRM tokens and controls with a scoped Payments stylesheet; no JavaScript or framework dependency is introduced.

Use `php tests/run.php`, `IDLM_TEST_FILTER=PAYMENT php tests/integration/run.php` and `php tests/integration/quotation-http.php --payments-only`. Native/HTTP commands require disposable `IDLM_TEST_DB_*` credentials. On macOS set `TMPDIR=/private/tmp` and a nonempty test password so worker paths and inherited credentials match; `--hold` retains an owned HTTP fixture for at most fifteen minutes. All test databases use newly created `idlm_test_*` names and are removed by their runners.

The concurrency test originally exposed stale nonlocking reads and passed after those reads were changed to current locking reads. Early broad-run failures came from missing inherited test credentials and noncanonical temporary worker paths; corrected disposable runs pass. An HTTP denial snapshot initially included WordPress's first admin-post core-job initialization; the focused fixture now warms that path before testing domain-state invariants.

## Remaining delivery slices

This is not the complete Design Projects release. Manual submit/return/resubmit/accept/assign, mandatory Sales remarks and immutable handover snapshots, separately scoped Design Manager/Rep access, versioned workflows, private DWG/design documents and phase gates, design quotation successors/discount controls, Kanban/List projects and reporting remain to be implemented. Accepted-project correction/reversal review flags and project-linked payment visibility depend on that project model. No design capabilities or broad sales mutation rights have been added.

The proposed defaults remain recorded for those slices: completion/exit checklists, payment presence rather than invented later coverage thresholds, P2P Active until explicit completion, no rep rate overrides, and separate grants for design receipt mutation. Portable ledger recovery, full release qualification and versioned release packaging remain future work. The plugin release header/stable tag is deliberately unchanged for this unreleased local iteration.

## Payments design follow-up

The Payments surface has been redesigned after the initial foundation checkpoint. Three summary metrics and an advance-pending strip lead into a milestone table, a focused receipt panel and history grouped by receipt identity. Optional notes, schedule amendments, corrections and previous versions use native disclosure. The project picker and no-schedule state have dedicated layouts. See [the UI audit and responsive rules](../design-system/interior-design-lead-manager/pages/payments.md). Phone inputs use 16 px text; submit targets are 44 px. Browser checks at 375, 768, 1024 and 1440 px show no horizontal overflow, and keyboard checks cover notes/history/version disclosures. The existing seven actual payment HTTP groups still pass; payment persistence and permissions are unchanged.

## Legacy recovery and CRM consistency follow-up

Existing Won leads whose saved quotation revision has no usable timeline can now be resolved explicitly through `idlm_payment_resolve_legacy`. The operation uses its own nonce, a current record version/idempotency key, and the separate `idlm_correct_lead_payments` capability (or administrator authority), with refreshed live actor/lead scope under the existing lead lock. Ordinary sales recording permission is insufficient. It never overwrites a usable saved timeline through this recovery route.

The authorized actor enters agreed milestone names and percentages, explicitly maps advance milestones, supplies a historic document/agreement reference and a meaningful reason, and confirms the terms. Expected amounts use only the exact selected revision's stored total/currency and existing quotation rounding. Current Quotation Setup is never read. Recovery stores a version-1 resolution record, source reference, original-terms hash, actor/time and reason inside the immutable schedule/audit structure. Original quotation bytes and commercial history remain unchanged. Incomplete schedules expose unscheduled balance; totals above 100%, missing source/confirmation/advance, invalid amounts and scope violations fail before persistence. Amendments append another version; required-write failures roll back and retries retain one event.

Past actual receipts can then be entered with their original received dates, positive amounts, method and screenshot or URN. Recovery creates no receipts automatically. Short payments remain valid with the difference pending, and recording retains the original sales owner, Won amount and lead timestamps. A usable canonical quotation revision with a positive stored total and valid currency is still required; missing quotation identities or invalid monetary data are not guessed from today's settings or the lead's Won value.

The standalone payment styling has also been removed. The page now reuses the actual quotation header, summary, cards, layout, table wrapper, inputs and buttons. [Current page rules](../design-system/interior-design-lead-manager/pages/payments.md) supersede the earlier redesign description. Browser measurements match the existing quotation title size, header height, card padding and table-cell padding. Native recovery/receipt disclosures work by keyboard; phone fields use 16 px text and tables remain contained within their standard scrolling region.

Validation at this follow-up: 410 fast checks, 16 focused native checks (including the fresh schema prerequisite), and ten actual authenticated HTTP groups pass. The new cases cover missing-term recovery, unauthorized and malformed requests, unchanged source quotation/lead, retry/rollback, amendment history and backdated short receipts. All data are synthetic in owned disposable databases; there is no production deployment or schema/release-version change. A local development review-graph directory is excluded from the release inventory without removing its data.
