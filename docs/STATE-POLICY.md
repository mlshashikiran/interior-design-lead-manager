# Lead state and vocabulary policy

Author: **Shashi**. Source checked: **2026-10-07**, candidate **1.102.56**. This describes the current implementation; the audit and older slice reports remain historical evidence.

## Ownership and boundaries

`IDLM_State_Registry` owns built-in keys/labels and contextual subsets. It is an enumeration registry, not a permitted-edge finite-state machine. `IDLM_DB` owns normalization/quality validation; `IDLM_Lead_Lifecycle_Service` owns checked persistence and coupled effects. Request handlers additionally enforce nonce, actor, object access, accepted fields and evidence. A registry entry alone grants no mutation authority. Custom stages are accepted through the configured stage union and do not acquire built-in semantics.

| Domain | Current vocabulary / contextual limits |
|---|---|
| Stage | New, Contacted, Qualified, Visited Studio, Quote Shared, Negotiation, Won, Lost, plus configured custom labels |
| Quality | Empty/pending, Qualified, Unqualified; stored independently from sales stage |
| Handoff | not_ready, awaiting_manager, manually_assigned, auto_assigned, exception |
| Call | Default Not Called, No Answer, Call Back Requested, Connected - Interested, Connected - Not Interested, Wrong Number; handler configuration controls accepted call labels, and built-in behaviors depend on their literal labels |
| Task | pending, completed, cancelled; system types Qualification and Sales Follow-up; manual types General, Call, Email, Site Visit, Design Mockup, Quote Revision |
| Disqualification | spam_test, duplicate, invalid_contact, unrelated_enquiry, do_not_contact, budget_below_minimum, outside_service_area, unsupported_project_type, timeline_mismatch, not_interested, administrator_exception, unable_to_contact, legacy_unqualified |

Manual forms and the add-task handler share `manual_task_types()` and exclude both system types. Early-close forms and the handler share `early_close_reason_groups()`/`early_close_reasons()`. Persisted disqualification values are keys; task/stage values are labels. Existing registry labels are preserved. `unable_to_contact` belongs to the completed follow-up decision; `legacy_unqualified` belongs to historical repair. Neither is an early-close choice.

## Current update policy

| Operation | Validation, normalization and effects |
|---|---|
| Enter Qualified stage | Normalizes quality to Qualified. A qualifying transition checks project name, location, budget, possession date, property type and timeline, with budget at least ₹4L. |
| Supply Qualified quality | A new decision promotes stage to Qualified. An explicit quality decision without a floor plan requires a handoff remark. Repeating an unchanged quality decision within the validation stage set is not a full revalidation. |
| Enter another sales stage | Visited Studio, Quote Shared, Negotiation and Won do not implicitly qualify an undecided lead. A stage-only move preserves prior quality and does not revalidate qualification fields. If the update also supplies Qualified quality, entry from outside Qualified/Visited Studio/Quote Shared/Negotiation/Won revalidates qualification fields. |
| Enter Quote Shared | Requires the sharing flag and a positive structured quote value. Creating/revising a quotation projects value without moving the lead stage. |
| Enter Won | Requires positive deal value and numeric discount from 0 through 100. Sets current conversion time; captures an immutable state event including close value. |
| Enter Lost | Requires a lost reason and cancels pending tasks. A new Unqualified decision completes pending Qualification tasks before Lost archives remaining work. |
| Supply Unqualified quality | Forces Lost; defaults the reason to Unable to Contact. Ordinary decisions require six completed outreach cycles and closure. Structured early close uses its separately enforced handler evidence policy. |
| Qualify / clear quality | Qualification clears disqualification metadata and completes pending Qualification tasks. Pre-sales ownership enters awaiting_manager; a sales owner records direct manual handoff; no owner awaits manager. Clearing quality resets qualification/disqualification stamps and qualified handoff state. |
| Change owner | Rechecks live target eligibility/capacity. Qualified handoff records manual assignment and applies the configured task policy; ensures a sales follow-up task. Existing pre-sales plans reconcile pending ownership and preserve supported completed history. |
| Change away from Call Back Requested | Clears callback datetime. Default Connected labels can stamp connection timing; No Answer can enqueue its configured Interakt event after commit. |

These are cross-field invariants, not an exhaustive authorization matrix. The canonical facade is a trusted internal persistence boundary; callers must still use the authorized request policy. Required lead, activity, reporting, task and durable dispatch writes share the checked aggregate transaction. Hooks dispatch after commit with durable replay intent; delivery is at least once.

## Permitted reversals

There is no global monotonic stage-order rule. Authorized callers may reverse stages when the candidate fields satisfy the above gates and live workload capacity.

- Leaving Won clears current `conversion_time`; historical `time_to_won` and immutable close events remain. Later Won entry records a new close event.
- Leaving Lost clears `lost_reason`. Retained Unqualified quality forces the candidate back to Lost; clear quality or make a valid Qualified decision to leave that decision state.
- Retained Qualified quality prevents a return to empty/New/Contacted: normalization returns Qualified. Clearing quality permits an active pre-qualification state. Qualification/disqualification history remains in activities/events.
- An existing six-cycle plan can resume after a permitted active reversal. Synchronization uses retained outreach evidence, fills stable missing cycles, reopens unsupported completed/cancelled qualification slots as pending, advances pending dates across working days and follows the live owner. It preserves supported completed attribution. Duplicate or decision-complete leads, and stages Qualified/Quote Shared/Negotiation/Won/Lost plus legacy `Visited`, do not reopen qualification work. The current task guard checks legacy `Visited`, not the built-in `Visited Studio`: an undecided Visited Studio lead can retain or receive qualification cycles. This is the current source behavior, not a claim that every commercial stage is excluded; changing it needs a separately reviewed task-stage policy. Ordinary manual tasks cancelled by Lost are not automatically reopened. See [task-plan policy](AUDIT-REMEDIATION-TASK-PLAN-SLICE.md).

## Structured early close

Objective reasons are spam_test, duplicate and invalid_contact. Duplicate requires a matching canonical lead. Invalid contact requires a logged outbound attempt or Wrong Number unless the actor is an administrator. Confirmed-response reasons require a Connected call or recorded WhatsApp reply unless the actor is an administrator. The budget reason additionally requires recorded budget below ₹4L. Every early close requires at least ten characters of evidence. Administrator exception and early closing a previously Qualified lead require `manage_options`.

The drawer displays this section to pre-sales actors. Its administrator exception option appears only if that actor also has `manage_options`; an administrator without a pre-sales role does not see the section. The handler accepts administrators under its own capability checks. Reason visibility does not replace server validation.

## Historical repair differs from current writers

The versioned `repair_workflow_integrity()` migration intentionally promotes nonduplicate historical Qualified/Quote Shared/Negotiation/Won rows to Qualified quality, except structured early closes. Visited Studio is absent from that historical promotion set. It normalizes legacy Unqualified rows to Lost and records `legacy_unqualified` / `legacy_import` when metadata is missing; duplicate repairs and invalid commercial-state downgrades have their own migration rules. It is not invoked as an every-save stage policy. Native contracts assert this distinction and idempotence; no new migration or schema change is introduced here.

## Identity and quotation boundaries

Inbound replay checks durable identity-review decisions first, then platform/external touchpoints and legacy external IDs, then the union of exact email and normalized phone candidates. One unique candidate is enriched even if the other supplied identifier conflicts; filled canonical contact fields are preserved. Multiple candidates produce a review outside the actionable queue. Phone normalization keeps explicit country codes (bare ten-digit numbers default to India); a suffix alone does not establish cross-country identity. The public webhook reports `requires_identity_review`; the internal upsert contract uses `requires_duplicate_review`. Managers can resolve reviews as canonical/separate/reject with replay and erasure rules described in the [identity-review policy](AUDIT-REMEDIATION-IDENTITY-REVIEW-SLICE.md).

Preview/Print returns escaped HTML for browser printing. The separate PDF download endpoint renders a binary PDF in memory with Dompdf; it disables remote resources and applies a plugin chroot. It does not persist the generated download. Current quotation writers use Draft/Superseded revision behavior. Legacy commercial states/pointers/dates are preserved. Finalized current revisions, acceptance/date metadata, commercial history or non-Draft/Superseded revisions make the quotation read-only. Ordinary prior Superseded finalizations do not prevent subsequent draft edits. No legacy conversion action exists; see [QUO-010 policy and limits](AUDIT-REMEDIATION-QUOTATION-HISTORY-SLICE.md).

## Verification scope

[CORE-013 evidence and qualification limits](AUDIT-REMEDIATION-STATE-POLICY-SLICE.md). Behavioral contracts exercise handlers and canonical persistence; source-string assertions alone do not establish these rules. Supported runtime/package/provider qualification remains pending under M7/M8.
