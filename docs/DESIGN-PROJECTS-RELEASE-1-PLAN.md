# Design Projects — First Release Plan

Author: Shashi  
Date: 2026-10-26  
Updated: 2026-11-05

Status: Implementation authorized by Shashi on 2026-11-05, one slice at a time. See section 0.

This document records the proposed first release discussed with Shashi. Quotation numbering has been updated to reflect the decision to keep the same quotation number and continue its revision sequence after handover. Manual handover after first-payment confirmation and restricted discount authority are also confirmed requirements. The design-phase upload/payment gates and editable design-item requirement below are also confirmed requirements. Other recommended defaults remain proposals where identified below.

Detailed fields, schema proposals, operations/hooks, workspace views and delivery slices are maintained in [the implementation blueprint](DESIGN-PROJECTS-IMPLEMENTATION-PLAN.md).

## 0. Decisions confirmed by Shashi, 2026-11-05

These override anything below that conflicts.

**Pricing and payments.** Keep three prices apart: the sales price (the revision handed over, locked at acceptance), the working design price (the latest design revision, which never moves payments) and the agreed price (the latest revision a Design Manager or administrator marks **Customer approved**, with a required note or the approval evidence). Marking it publishes a new payment schedule version: same percentages from the saved quotation, amounts recalculated on the new total, receipts unchanged and reallocated oldest milestone first. Excess shows as credit. Quotation Setup changes never affect a saved project. This settles the 10% basis: expected amounts use the agreed price at that time.

**Payment gates.** A short payment does not block a stage: a payment with evidence is required and the shortfall stays visible. Material Selection uses the advance milestone, Kick Off the second, P2P the final. Confirm the real payment structure when the stage slice starts.

**People.** Sales owner and Design Manager record later payments; only managers correct them; reps see balances only. P2P stays active until someone explicitly marks the project complete.

**Handover.** Sales picks the Design Manager and gives the advance, remarks, scope and pending items. Other checklist items are optional. The manager returns it with a reason, or accepts and assigns a rep in one step. An accepted handover is not returned; later changes use the amendment flow.

**Streamlining.** Release one has one project per lead, so it reuses the lead transaction and outbox. About five tables instead of thirteen. Six fixed stages with editable names and required documents; a workflow builder waits. Update 2026-11-06, on the owner's request: stages are now managed (add, rename, reorder, remove by archiving, per-stage requirements, added document types), still without versioned workflows.

**Build order.** 1 Handover versions and roles. 2 Design revisions and discount locks. 3 Customer approved and schedule versions. 4 Stages and private documents, including DWG. 5 Kanban and List. 6 Dashboards and release checks.

**Rates.** Design Reps keep the source rate on known lines and the catalogue rate on new catalogue lines. A new custom line can have its own rate and is listed in the audit. Sales Manager discount access is not built; it needs the scoped grant in section 3.

**Build status, 2026-11-06.** Slices 1 to 6 are built locally and uncommitted. Not built, because the business decision is open or the scope was deferred: the amendment flow for accepted handovers, cancelling a project, Sales Manager discount access, a project priority field, configurable custom fields and project tasks, target stage durations, and studio-scoped manager boundaries. Nothing has been deployed, and the plugin version is unchanged.

**Decisions, 2026-11-06 (Shashi).** (1) Keep the `-DES` marker on design revisions on screens, PDFs and exports; already built. (2) Design Reps may change line rates; a manager must review a design revision before it is shared with the customer. Built. (3) Payment corrections after acceptance: corrections and revocations keep history; a passed stage whose payment no longer holds shows a review flag everywhere the project appears; the Design Manager may clear it with a note; nothing moves automatically. Built, approved by Shashi. (4) Sales do not see the design team's internal documents for now; already enforced, since design files are readable only by the people who can see the project. (5) Hold reasons: a standard list plus reasons the administrator adds; the business timezone is the WordPress site timezone. Built.

**Risks to handle.** The design working revision lives on the project and must not become the quotation's current revision: Meta Purchase reads that revision for its amount. Design revisions need a narrow successor path, because locked quotations stay read-only.

## 1. Scope and boundaries

Introduce a Design Projects workspace linked to the existing lead and quotation. Sales retains its owner, Won stage, commercial history, and recorded Won amount. Design receives separate manager/representative assignments, workflow stages, documents, and revision permissions.

- One design project per lead in release one; allow future linked projects for execution or other teams through a team/workflow discriminator.
- Customer details and existing floor plans remain on the lead and are read-only for design users.
- Reuse the quotation editor, item calculator, contractual snapshots, autosave conventions, and revision history.
- Include handover, assignment, configurable stages, project fields, private versioned documents, design quotation revisions, activity history, and basic project queues.
- Defer a visual automation builder, external webhook configuration UI, customer portal, structured room-by-room measurement system, CAD viewing/editing, and execution management. Private DWG upload/version/download support is included for the confirmed design-phase requirements.
- Do not introduce a separate generic task engine in this release. Project task support, if included, must have explicit project scope and avoid qualification/sales-task lifecycle behavior; its inclusion is an open scope decision.

## 2. Handover

**2026-11-05, Shashi:** the owner replaced this section's first release with a simpler flow: one form on the Won lead and one shared Design team list. See `docs/FEATURE-MAP.md`, Design handoff. The detail below remains background for later work.

Confirmed flow: Won → sales receives/confirms the first customer payment → sales manually submits handover → manager review → acceptance → design representative assignment. Manager acceptance and assignment may occur together. Won alone or payment confirmation alone must never automatically submit handover, create an actionable design assignment, or start design work. Sales may retain the Won lead while awaiting payment or preparing handover.

Submission requires mandatory Sales remarks to Design alongside the payment details, a Design Manager, exact quotation/revision identity, scope summary, exclusions/commitments where applicable, and pending-information notes. Existing lead floor plans are shown without duplicating files. Record the source sales owner and relevant customer/project snapshot at submission.

### First-payment confirmation and proposed handover checklist

Sales must enter the actual advance payment received before manually submitting the closed/Won lead to the Design Manager. The form preloads the expected advance from the selected agreed quotation revision’s saved payment timeline; expected amounts are calculated values and never evidence that payment occurred. Proposed structured fields: confirmed amount, currency, payment date, payment method, URN/transaction/receipt reference, confirmation note, confirming user/time, and optional private payment screenshot. Every collected payment requires the received date and actual amount plus at least one evidence option: an uploaded screenshot (SS) or a recorded URN/transaction reference; both may be supplied. This is a handover prerequisite and evidence record, not an accounting/payment-gateway integration. Whether confirmation means a customer-provided receipt or independently verified receipt of funds must be decided; do not imply bank verification that the plugin has not performed. Any later correction/reversal preserves the original confirmation and flags accepted design work for manager review.

### Payment timeline and saved payment fields

Use the exact agreed quotation revision as the payment basis. The current quotation document snapshot already stores milestone names, percentages and calculated amounts in `document_terms.milestones` through `IDLM_Quotation_Document::capture()`; `read()` retrieves those saved terms. Reuse the saved amounts and existing decimal/rounding rules rather than calculating from today's Quotation Setup. This is an implementation reference, not authorization to change quotation persistence.

At handover, show the payment timeline and its expected amounts, then require sales to record the advance actually received. Save structured payment fields associated with the lead so they exist before project creation, and reference their version in the handover snapshot:

| Field group | Saved values / behaviour |
|---|---|
| Payment basis | Canonical quotation ID, exact revision ID/reference, currency and saved grand total |
| Timeline snapshot | Ordered milestone identity, name, percentage and expected amount from that revision; preserve snapshot/version and source milestone position where a legacy snapshot lacks stable milestone IDs |
| Advance requirement | Explicitly mapped advance milestone(s), expected advance amount and percentage; do not infer which milestone is advance from free-text labels alone |
| Actual advance received | Mandatory positive amount, received date and method; require an uploaded private screenshot (SS) or a URN/transaction reference, allowing both; confirming actor and recorded time captured automatically |
| Calculated progress | Confirmed received amount, amount allocated to each milestone, remaining advance, total remaining against the selected quote, and payment status (Unpaid/Partial/Met/Excess) |
| Corrections | Original values, corrected/revoked values, actor/time and reason; preserve prior evidence and handover snapshots |

Only expected amounts come from the timeline. Actual receipts are entered and confirmed by an authorized sales user; never auto-mark a milestone Paid from its percentage or due amount. Multiple receipts may contribute to the advance, but each receipt has one identity and must not be counted twice across milestones. Enforce matching currency and preserve excess amounts explicitly rather than producing a negative outstanding balance. Do not treat a schedule totalling less than 100% as a fully allocated collection plan; show any unscheduled balance separately. Date-based due dates remain unspecified unless the saved terms or an explicit business rule provides them.

Confirmed handover policy: require the actual advance receipt fields and evidence (screenshot or URN/reference), but allow handover when the amount received is below the expected advance. No minimum coverage percentage, rupee tolerance threshold, manager exception or short-payment approval is required. Differences of ₹1, ₹10, ₹100, ₹1,000 or other amounts remain pending collection and must not block handover. This does not waive the requirement to record an actual positive advance payment. Recording payment does not automatically hand over the lead or assign a Design Rep; sales submits to the Design Manager, who accepts and assigns as already planned.

Carry the unpaid advance forward as a separately visible pending balance in the handover summary, Design Projects workspace and collection view. Show expected advance, actual received, advance pending, subsequent scheduled payments and total outstanding. Later receipts reduce the pending balance through explicit allocations; show any combined next-collection amount as prior pending plus the next scheduled amount, without rewriting the agreed timeline, recording fictitious receipts, forgiving the difference or counting the carried balance twice in total outstanding. Payment status remains Partial until the expected amount is covered, even though handover proceeds. Apply the date/amount plus screenshot-or-URN evidence rule to every subsequent payment collection as well.

A design revision or later settings change must not silently recalculate the accepted handover's payment schedule or reallocate receipts. An explicitly approved schedule amendment creates a new version with its quote basis, before/after amounts and reason; original receipts and historical balances remain traceable. Stage payment gates use explicit milestone mappings. These do not introduce a full-advance-payment gate on sales-to-design handover. The confirmed 10% Material Selection rule remains in force; a different advance percentage must be surfaced for business resolution rather than silently replacing that rule. Kick Off Meeting and P2P payment thresholds still require business decisions.

For legacy quotation revisions without a usable saved timeline, show the payment basis as missing and require an explicit authorized resolution before handover; never substitute current settings or fabricate historic payment terms. This remains receipt-confirmation tracking, without bank reconciliation or an accounting integration.

The exact additional information and mandatory/optional policy must be finalized with the business. Proposed checklist:

| Information | Proposed policy |
|---|---|
| First-payment confirmation | Actual positive advance amount/date/method and screenshot or URN/reference required before submission; expected amount comes from the agreed revision’s saved timeline; short payments allowed with balance pending collection |
| Exact agreed quotation revision | Required; amount/currency derived from saved revision |
| Included rooms/work, exclusions, special commitments | Scope required; explicit None permitted for exclusions/commitments |
| Customer decision-maker and preferred coordination contact/time | Confirm existing lead details; record project-specific coordination notes without duplicating editable lead identity |
| Existing floor plans and site/measurement readiness | Show linked plans; record whether site measurement is pending and known access constraints |
| Site address/access, possession or readiness date | Confirm existing address; record missing information and responsibility to obtain it |
| Design preferences and agreed materials/specifications | Record known requirements or explicitly mark Not yet discussed |
| Promised design timeline and important customer dates | Record commitments; distinguish requested dates from confirmed commitments |
| Payment terms or exceptions agreed with customer | Reference saved quotation terms; record additional commitments explicitly |
| Pending questions, risks, next action and responsible person | Explicit None or actionable details |
| Sales remarks to Design and receiving Design Manager | Required; dedicated remarks field alongside payment details, visible in manager review and the design project |

Configure business checklist requirements within a bounded field/document vocabulary; do not hard-code every proposed checklist item as mandatory. The payment prerequisite, exact revision identity, authorized recipient, and audit requirements remain enforced system controls. Store a versioned submission snapshot so later lead/configuration changes do not rewrite what was handed over. Sales remarks to Design are a dedicated required long-text field for customer commitments, context, special requests and pending points. Reject empty/whitespace-only remarks; payment details do not substitute for remarks. Sales can edit remarks before submission and on return/resubmission, with each submission retaining its own author/time and remarks snapshot. After acceptance, changes use the audited handover-amendment flow. Show the accepted remarks to the assigned Design Rep and scoped Design Manager in the handover summary; preserve previous versions in history.

System handover states are Pending, Returned, and Accepted; these are separate from business-configurable design stages. A returned handover requires a reason. Resubmission preserves prior versions. An accepted handover can only change through an audited amendment; it must never silently replace the agreed baseline.

Repeated requests must not create duplicate projects or handovers. Existing Won leads are not automatically backfilled. Reversing Won flags the existing project for manager review rather than deleting or automatically cancelling it.

Acceptance records actor/time and fixes the handover quotation baseline. Assignment records representative, target completion date, actor/time, and prior assignment. Reassignment requires a reason.

## 3. Roles and permissions

Design authority is project/team-scoped and does not grant organization-wide sales assignment or CRM management access. Administrators configure roles and workflow settings; a manager may receive a separate workflow-configuration capability.

| Action | Sales owner | Design Manager | Design Rep |
|---|---|---|---|
| Read linked lead and sales history | Existing permissions | Managed projects | Assigned projects |
| Edit lead/customer fields | Existing permissions | No | No |
| Submit/resubmit handover | Yes | No by default | No |
| Accept/return handover | Read outcome | Yes | No |
| Assign/reassign designer | No | Yes, within scope | No |
| Edit design project fields | Read by default | Yes | Assigned projects |
| Change design stage | Read | Permitted transitions | Permitted transitions |
| Upload/version design documents | Handover files only | Yes | Assigned projects |
| Create design quotation revisions | Read by default | Yes | Assigned projects |
| Change any discount on a design revision | No for sales reps; Sales Manager only with scoped grant and remark | Yes, with remark | No |
| Manage shared catalogue/standard pricing | Existing permissions | Separate grant | Separate grant |
| Configure workflow | No | Separate grant | No |

Discount changes are permitted only for an authorized Design Manager, Sales Manager, or administrator, with live project/quotation scope and a mandatory meaningful remark. Sales Manager discount access is a narrow grant and does not grant design assignment, lead mutation, or unrestricted quotation management.

Viewing customer details does not grant bulk export. Reassignment revokes the former rep's working access while preserving authorship. Any sales visibility into internal design documents must be explicit. A lead-correction request is an audited request, not permission to edit lead data.

Enforce access for every read, mutation, download, search, autosave, and export on the server. Do not infer lead-mutation authority from design quote-edit authority or reuse unrestricted sales manager grants for design managers.

## 4. Business-configurable workflow

Business users can configure stage name, description, colour, order, stable identifier, start stage, reporting category (Active/Completed/Cancelled), allowed next stages, permitted actors, entry requirements, optional target duration, and active/archive state.

Confirmed design-phase sequence:

New Project / Lead → Material Selection → Masking → Kick Off Meeting → Design Quality Check (DQC) → P2P.

The design team workspace defaults to Kanban with a List toggle. New Project / Lead is the first column for accepted handovers before Material Selection; pending/returned handovers remain in separate intake queues. Assigned reps see their projects; managers also see accepted unassigned projects in their scope. Acceptance can place a project in this column without automatically starting design work.

### Confirmed phase requirements

| Design phase | Required uploads | Payment requirement |
|---|---|---|
| New Project / Lead | Accepted handover, including payment details and Sales remarks to Design | An actual advance with required evidence is recorded at handover; shortfall remains pending and does not block handover |
| Material Selection | Payment screenshot or recorded URN/reference for the 10% milestone, with received date and actual amount | Payment mandatory; preserve the 10% expected amount and show any shortfall separately |
| Masking | Final render PDF and masking DWG | No additional payment requirement specified |
| Kick Off Meeting | Tentative quote and masking-changes DWG | Payment mandatory; amount/percentage and evidence policy remain to be finalized |
| Design Quality Check (DQC) | Tentative quote, DWG, MOM (minutes of meeting), and approval-mail screenshot | No additional payment requirement specified |
| P2P | Final quote, DWG, and payment evidence (screenshot or recorded URN/reference, with received date and actual amount) | Payment evidence required; expected amount/percentage and confirmation policy remain to be finalized |

Each listed document/evidence slot is a distinct requirement; payment evidence may be a structured URN/reference instead of a file upload; one uploaded file must not silently satisfy unrelated evidence slots. Store the exact document version and, for quotation evidence, the exact quotation/revision identity used to satisfy a gate. Quotation evidence must use the existing canonical quotation and continuous revision history, not create another quotation number. Do not invent percentage thresholds for Kick Off Meeting or P2P, or expand the P2P acronym without a business decision.

Proposed gate timing: require the phase checklist before leaving/completing that phase, so its evidence can be produced during the work. The business must confirm timing before implementation; published rules explicitly distinguish entry and completion gates. Show missing requirements in the workspace and enforce the configured gate on the server. Missing required evidence or mandatory payment confirmation blocks the gated transition. The Material Selection payment may reference the first-payment confirmation used for handover with its actual amount, date and screenshot or URN/reference; show progress against the expected 10% amount and any pending balance, without implying a short first payment fully satisfies 10% or blocking sales handover for that difference. Define the quotation/revision amount used as the percentage basis before implementation. Later corrections/revocations preserve evidence history and flag affected work for manager review.

Stage names, descriptions, colours and permitted transitions remain configurable through versioned workflows. Confirm whether P2P marks completion or remains an active stage; do not infer a terminal category from its position in the sequence. Release one supports a bounded rule vocabulary: required project fields, current documents by category/evidence slot and allowed file type, linked payment confirmations, required transition remark, and actor permissions. It does not execute arbitrary user-defined code.

On Hold is a separate flag with reason, actor/time, and optional expected resume date; the actual stage remains visible. Hold/reopen/completion permissions and reasons are explicit. Completion records actual completion time; reopening preserves prior completion history.

Used stages are archived rather than deleted, and archived stages cannot receive new entries. Publish workflow changes as versions. Existing projects retain their workflow version unless explicitly migrated with a stage mapping. Historical events snapshot stage labels and stable identities. Renaming a stage must not break integrations or change prior history.

## 5. Project fields

| Group | Fields | Requirement/default |
|---|---|---|
| Identity | ID, project number, title, lead ID, team type | Generated; title editable |
| Ownership | Sales owner at handover, Design Manager, Design Rep, studio | Manager at submission; rep before assigned work |
| Handover | Source quote/revision, Sales remarks to Design, scope, exclusions, commitments, pending information | Revision, sales remarks and scope required |
| Payment confirmation | Source quote/revision and timeline snapshot; expected advance; actual amount/currency, date/method, screenshot or URN/reference, confirmer/time, note and calculated balances/status | Actual positive advance and evidence required before manual submission; short payments allowed without exception approval |
| Dates | Expected start, target design completion, actual start/completion | Target at rep assignment |
| Design brief | Requirements, preferences, measurement summary, site constraints | Optional initially; stage rules may require |
| Progress | Workflow version, stage, stage-entry time, priority | Controlled/defaulted |
| Hold | Flag, reason, held by/at, expected resume | Reason required on hold |
| Audit | Created/updated by/at, concurrency version | Automatic |

Customer identity/contact and existing lead project fields are read from the lead, not independently editable duplicates. Historical handover and quotation snapshots preserve the values used at that time. Baseline money/currency are derived from the selected revision, never manually re-entered.

Proposed additional-field support: text, long text, number, date, and dropdown, with stable field keys, validation, visibility/edit permissions, and optional stage requirements. Ownership, stages, financial totals, and other lifecycle controls remain system fields. Measurement detail stays in versioned documents; the project stores a summary.

### Editable design items

Include an item field/editor for the design team where the assigned Design Rep can add items and edit the item name, description, and other details. Design Managers can also manage items within their project scope. Reuse the existing quotation item editor and item snapshots when these are quotation items; item edits create permitted design revisions and never overwrite the sales handover baseline or protected revisions.

Name and description are confirmed editable fields. Finalize the remaining item-detail fields before implementation, reusing supported dimensions, quantities, materials/specifications and notes where appropriate. Item editing does not grant shared-catalogue access or discount authority; the existing manager-only discount rule applies, and rate-override authority remains an open decision. Confirm whether any non-priced, project-only items are needed before introducing separate item storage.

## 6. Documents

Show Lead Documents (existing floor plans/sales attachments, read-only) separately from Design Documents. The existing lead supports multiple floor-plan links; do not create a duplicate floor-plan field on the project.

Configurable categories include Measurement, Site Photos, Layout, Electrical/Plumbing, 3D Design, Material Specification, and Other, plus explicit evidence slots for 10% Payment Evidence, Final Render PDF, Masking DWG, Tentative Quote, Masking Changes DWG, DQC DWG, MOM, Approval Mail Screenshot, Final Quote, P2P DWG, and P2P Payment Evidence. Stage requirements bind to stable category/evidence-slot identities and exact versions rather than display labels. Kick Off Meeting also links its mandatory payment confirmation. All payment-evidence slots accept a structured URN/reference instead of a screenshot upload, with amount and received date required in either case; a URN-only receipt does not create a placeholder file.

Document metadata: title/category (required), optional room/area and description, current version, state, uploader/time, private file identity, prior version, and optional related quotation revision. Uploading a new version retains previous bytes/metadata. Archive instead of ordinary destructive deletion; administrative erasure remains an explicit governed operation.

First-release types include PDF/JPG/PNG and the explicitly required DWG uploads, retaining the current 10 MB per-file ceiling. Implement an explicit DWG extension/MIME/content-validation policy compatible with supported WordPress environments before enabling upload; do not broadly allow arbitrary CAD or executable formats. DWG support covers private upload, versioning and download only, without CAD preview, parsing or editing. Evidence slots restrict allowed file types: final renders require PDF, drawing slots require DWG, and screenshot slots require supported image types; finalize quote/MOM formats before implementation. Validate ownership and document reference state on upload/download and after reassignment.

Reuse the private-file storage foundation but extend project-purpose authorization and reference tracking explicitly. A design document upload must not require permission to modify the lead. Existing lead file compatibility fields stay intact. Stage requirements reference an active document/category, not a filename match; superseding/archiving a required document must be validated.

## 7. Quotation identity and continuous revisions — updated decision

Keep one canonical quotation and its existing base quotation number. Continue the same monotonically increasing revision sequence after handover; do not create another quote number, reset numbering, or create a separate design quotation history.

Example (illustrative base number):

| Revision | Phase | Display reference | Meaning |
|---|---|---|---|
| R04 | Sales | Q-2026-00123-R04 | Exact sales revision selected for handover |
| R05 | Design | Q-2026-00123-R05-DES | First saved design revision |
| R06 | Design | Q-2026-00123-R06-DES | Subsequent design revision |

`DES` is the recommended display suffix; final wording/placement remains configurable. The canonical quotation number and numeric revision identity do not depend on that decoration. Persist the display designation with each revision so later settings changes do not rename historical references. Existing sales revision references remain unchanged. The first design save reserves the next globally available revision under the quote lock; if R04 is handed over while R05 already exists, never reuse R05 or silently change the source baseline.

### Sales preservation and editable design work

- The accepted handover stores an immutable pointer to the exact sales baseline, its amount/currency, and commercial evidence. Previous sales revisions remain available.
- Create a new design-phase revision by copying persisted baseline content. Never edit/reopen the sales baseline to enable design work.
- Preserve all original sales statuses, accepted/shared pointers, dates, history, terms, and monetary values. A design revision does not mean the customer has accepted a price change.
- Maintain separate concepts for latest saved revision, sales handover baseline, current design working revision, and any commercially accepted revision. Do not overload one current-revision pointer to represent all four.
- Current implementation only permits eligible Draft quotations/revisions and protects commercial history. Implementation must add a narrow, project-authorized successor-revision path from a protected sales source; simply relaxing the existing immutable-draft policy is unacceptable.
- Separate the sales commercial lock from design working eligibility. Once a design revision itself becomes commercially protected, it also remains immutable; any later work creates a permitted successor, never edits protected history.
- Existing sales writers/readers must continue resolving the correct sales context after design revisions exist. Listing, editor, autosave, exact-revision downloads, history, and source validation all need explicit phase/context behavior.
- After accepted handover, sales quote mutation is read-only by default. Any later correction/reversal is an explicit manager workflow, preventing simultaneous competing sales/design branches.

### Revision content, attribution, and pricing

- Continue existing save/revision numbering, required change remarks, deterministic totals, item snapshots, and per-user autosave behavior.
- First design content copies saved source items, rates, currency, and terms; never refresh silently from catalogue/current defaults. Later contractual changes follow the existing explicit revision snapshot contract.
- Every new design revision stores phase, project ID, source revision ID, designer user ID/name snapshot, and the actual save actor separately. If a manager saves on behalf of a designer, do not label the manager as the designer implicitly.
- Design quotation views and outputs show Sales Representative and Design Consultant. New design attribution never rewrites old sales documents or revision author history.
- Show sales handover amount, latest design amount, and delta. Design revision saves must not overwrite lead sales quote-value projections or the recorded Won amount.
- Reassignment changes future revision attribution, not older documents. Historical revisions use persisted identities/content for print/PDF/CSV/XLSX.
- Confirmed discount policy: Design Reps cannot add, remove, increase, decrease, or otherwise change discount settings. Only an authorized Design Manager, Sales Manager, or administrator may change any discount, with a mandatory meaningful remark. This covers item-level discounts, wood discounts, additional discounts, and any other discount-bearing control supported by the quotation engine.
- Record discount changes in the new revision and audit history: actor/role, timestamp, source/new revision, each changed field and its before/after value, and the required remark. Do not mutate historical sales/design revisions. A normal revision remark can serve as the discount remark only when it explicitly explains the discount change.
- Designers inherit existing discount settings when revising. Server-side save, autosave, import/copy, and other writer paths reject unauthorized discount-setting changes; disabling editor controls alone is insufficient. Totals may recalculate from permitted scope/quantity edits while the approved discount settings remain unchanged; no new discount approval is implied by such recalculation.
- Designer item/dimension/quantity changes remain in scope. Rate-override authority is unresolved: unrestricted rate reductions could bypass the discount restriction, so explicitly decide rate permissions before implementation rather than treating lower rates as an alternate discount mechanism. Shared catalogue mutation requires a separate grant.
- Concurrent saves lock the quote and check expected source/context; stale saves fail with a conflict rather than overwriting work or reserving duplicate revision numbers.

## 8. Proposed database management

All changes are future additive migrations, following existing custom-table/static-service conventions. Names below are design proposals, not installed schema.

| Table | Responsibility |
|---|---|
| `idlm_projects` | Lead/team linkage, assignments, workflow/stage, dates, brief, hold, concurrency version |
| `idlm_project_handovers` | Versioned submissions, exact baseline, scope/payment-confirmation snapshot and protected proof reference, return/acceptance actors/times |
| `idlm_project_workflows` | Workflow identities and published versions |
| `idlm_project_stages` | Stage configuration per workflow version |
| `idlm_project_transitions` | Allowed moves, actors, bounded requirements |
| `idlm_project_events` | Append-only business history and historical label/identity snapshots |
| `idlm_project_documents` | Logical documents, category, current version, archive state |
| `idlm_project_document_versions` | Private file reference, version chain, uploader/time, optional quote revision |
| `idlm_project_field_definitions` | Additional fields, types, choices, validation and permissions |
| `idlm_project_field_values` | Validated project values |

Extend the existing quote/revision schema only as needed for phase/project linkage, baseline/design pointers, designer snapshots, and preserved display designations. Reuse all existing quote revision/section/item tables and global revision sequence. No second quotation per lead is needed.

Database requirements:

- Indexed ownership/stage/date/lead queries; unique lead/team project identity for release one and idempotent submission/save identities.
- Relational identifiers and indexed lifecycle fields remain columns; bounded snapshots/configuration may use validated structured values. Avoid storing whole projects as opaque option blobs.
- Application-managed integrity, matching the existing absence of foreign-key cascades; validate every lead/project/revision/document relationship.
- Atomic project/history/required delivery-intent writes, live permission rechecks, consistent lock ordering, and stale-update detection.
- Decimal monetary conventions remain those of the quotation engine.
- Independent schema markers, coordinated installer ownership, physical verification, restartable migrations and idempotent backfills; final table ownership/version allocation is an implementation design step.
- Explicit integration with deletion/reset, retention, integrity checks, backup manifests and recovery fencing. No fabricated historical projects/events during upgrade.
- Private database file chunks remain subject to current recovery limits. Plugin JSON restore must not claim to restore excluded private bytes; full database recovery is required where that is the existing contract.
- Bounded lists/history reads and operational checks for failed migrations or document/event delivery work.

## 9. Hooks and future workflow/team integration

Expose focused project service operations for create, submit/accept/return handover, assign, transition, hold/resume, add document version, and create design revision. Future teams/integrations call these operations with actor/context and validation rather than writing tables directly. Follow existing facade/repository/policy/controller boundaries; avoid a wholesale framework migration.

Proposed after-commit hooks:

| Hook | Event |
|---|---|
| `idlm_project_created` | Project creation |
| `idlm_project_handover_submitted` | Submission/resubmission |
| `idlm_project_handover_accepted` | Acceptance |
| `idlm_project_handover_returned` | Return with reason |
| `idlm_project_assignment_changed` | Manager/rep reassignment |
| `idlm_project_stage_changed` | Stage transition |
| `idlm_project_hold_changed` | Hold/resume |
| `idlm_project_document_version_added` | New document version |
| `idlm_project_quote_revision_created` | New design revision |
| `idlm_project_completed` | Entry into completion category |

Use a versioned event envelope: event ID/schema version, project/lead IDs, team type, actor, occurrence timestamp, and relevant before/after identities. Avoid unnecessary customer information, raw document bytes, or secrets. Events dispatch after commit with durable retry intent; consumers deduplicate by event ID. Recovery/deletion and reassignment rules must be respected during replay. Do not assume the lead-only outbox already supports project aggregate transactions; reuse its proven pattern through an explicitly owned project delivery boundary.

Provide narrow pre-write validation extension points for stage transitions, additional fields, and document categories/types. Extensions cannot bypass core authorization, immutable history, file validation, or relationship integrity. Stable identifiers, not stage display names, drive integrations.

Future example: an execution module subscribes to completion and creates a linked execution work item with its own workflow. Release one supplies the contracts; configurable external webhook delivery and a full automation builder are deferred.

## 10. Design Manager and administrator dashboards

### Views and access

The Design Manager dashboard is operational and limited to projects within the manager's current authorized team/project scope. The administrator dashboard provides organization-wide sales-to-design queues and comparisons by manager/studio. Aggregates, drill-down lists, histories, downloads, and filter choices must all enforce the same scope. Historical attribution never grants current access to a former assignee. Unassigned and missing-studio records remain visible in explicit buckets rather than disappearing from totals.

Manager layout: top cards for Pending Handovers, Accepted but Unassigned, Active Projects, Overdue Projects, and Due in Next 7 Days; followed by Needs Attention, stage counts/aging, representative workload, and recent activity. Needs Attention includes old handovers, overdue projects, missing stage-required documents, and exceeded stage-duration targets. Each metric opens a filtered, permission-checked project list.

Administrator layout: sales-to-design queues first, then organization totals, completion/turnaround trends, manager/studio workload, bottlenecks, quotation value changes, and discount activity. Use current-state cards separately from period-based completed/event metrics; never imply they share a cohort or denominator.

### Metrics and definitions

| Metric | Definition and required distinction | Breakdown/view |
|---|---|---|
| Pending handovers | Submitted/returned-resubmitted requests currently awaiting review; show count and age since latest submission | Manager scope; administrator by manager/studio |
| Handover acceptance time | Accepted time minus latest submitted time for that accepted submission; also show first-submission-to-acceptance elapsed time including return cycles | Median plus accepted count, by manager/studio |
| Accepted but unassigned | Accepted projects with no current eligible Design Rep; show time since acceptance/unassignment | Manager allocation queue; administrator backlog |
| Active projects | Accepted, nonterminal projects, including on-hold projects; pending/returned handovers excluded | Stage, rep, manager, studio; held subset identified |
| Overdue projects | Active projects past current target completion; count and percentage of active projects with a target; also show missing-target count | Manager action list; administrator team comparison |
| Upcoming deadlines | Active projects due from today through the next 7 local calendar days, excluding already-overdue dates | Rep/team; show held flag |
| On-hold projects | Current held count, current hold age, reason, responsible party and cumulative hold duration | Manager follow-up; administrator blocker distribution |
| Stage aging | Current stage entry to now; closed stage intervals for historic analysis; flag targets using the workflow/version effective for the interval | Stable stage/category and workflow version; avoid merging unrelated stages by name |
| Completed projects | Distinct projects whose latest lifecycle state at the period end is Completed and whose most recent completion entry is in the selected period; show reopen events separately | Trend, manager/studio; no duplicate completion count per project |
| Design turnaround | First accepted handover to most recent completion for that completed-project cohort; show gross elapsed and held duration separately | Median and cohort size by manager/studio |
| Representative workload | Current active project count, overdue count, upcoming deadlines, review-pending and on-hold counts; show missing targets | Rep within scope; administrator by team; no inferred utilization percentage |
| Design quotation revisions | Persisted design-phase revision count per project/period; exclude autosaves, sales revisions and failed saves | Project/rep/team; not a performance score |
| Quotation value change | Latest persisted design revision grand total minus exact original handover baseline in the same currency; disclose baseline amendments separately | Per project, increase/decrease and totals by currency/team |
| Discount changes | Successful authorized changes with approver/actor, reason, before/after settings and monetary effect where deterministically available | Manager activity; administrator across authorized managers/admins |
| Recent activity | Assignment, handover, stage, document, quotation, hold, target-date and discount events | Current project scope; newest first |

Internal/customer review workload uses a configurable stage-purpose tag (for example Internal Review or Customer Review), separate from the stage's display name and Active/Completed/Cancelled category. Without a configured tag, show the business's stage names rather than guessing review meaning.

Administrator sales-to-design queues (keep these distinct):

| Queue | Eligibility and age |
|---|---|
| Won, awaiting first-payment confirmation | Currently Won leads without a valid first-payment confirmation and without an accepted handover; days since current Won entry; no overdue label without a defined payment expectation |
| Payment confirmed, not handed over | Currently Won leads with valid payment confirmation and no submitted/accepted handover; confirmation-recorded time to now; returned requests shown separately |
| Submitted, awaiting manager acceptance | Current Pending handovers; age from latest submission; returned handovers are a separate sales-action queue |
| Accepted, awaiting designer assignment | Accepted, nonterminal projects without an eligible rep; age since acceptance or latest unassignment |

Also measure payment-confirmation-recorded time to initial manual submission for submitted handovers. Retain payment received date separately: a backdated payment date must not imply sales could have handed over before confirmation was actually recorded. Revoked confirmations and sales-stage reversals create explicit exception queues instead of falsely appearing as ordinary pending intake.

### Data capture required for reporting

Store raw facts and event intervals; calculate dashboard counts/durations from them. Current snapshots alone cannot reconstruct historic ownership, stages, holds, or deadline changes.

| Area | Required fields/history | Proposed storage |
|---|---|---|
| Sales eligibility | Lead ID; current Won entry event/time and cycle identity; sales owner/studio; current stage | Reuse canonical lead/state events; add only missing reporting links, never fabricate old events |
| First-payment confirmation | Received amount/currency/date, confirmation status, confirmed-by and recorded-at, reference/proof; corrections/revocation actor/time/reason and prior values | Versioned confirmation record associated with lead, with its ID/snapshot referenced by handover; available before a design project exists |
| Handover timing | Submission/version ID, first/latest submitted-at, submitting actor, returned-at/by/reason, accepted-at/by; baseline quote/revision; manager/studio at event | Handover rows and immutable project/lead-linked events; derive first/latest summaries |
| Assignment | Manager and rep IDs, assigned/unassigned-at, previous/new IDs, actor/reason; studio/team effective at event | Project snapshot plus assignment event intervals |
| Workflow/stages | Workflow/version/stage IDs, stable stage-purpose/category, label snapshot, entered/exited-at, actor, applicable target-duration snapshot | Versioned workflow tables and stage transition events |
| Dates | Original and current target completion dates, date-change history with old/new value, actor/time and required reason; expected/actual start and completion/reopen/cancel events | Project columns plus immutable events |
| Hold/blockers | Hold start/end, stable reason code and label, explanation, responsible party (customer/internal/sales/site/other), actor, expected resume; overlapping intervals prohibited | Hold/resume event pairs; project current hold snapshot |
| Completion attribution | Completion event/time and cycle, manager/rep/studio at completion, prior completion and reopen events | Lifecycle events; historic outcomes use event-time ownership |
| Documents/completeness | Document category/version/state, project link, upload/archive times, actor; applicable workflow requirement version | Existing proposed document/version tables and events |
| Quote activity | Quote/revision IDs and number, phase, project link, persisted-at, author/actual actor, designer identity snapshot, currency, baseline/current grand totals | Existing quote/revision tables with planned additive fields; baseline pointer on handover |
| Discount audit | Changed discount field, old/new setting, before/after monetary values where comparable, actor/authorized role at event, required remark, timestamp and source/new revision IDs | Immutable revision-linked audit details; no separate editable discount total |
| Integration consistency | Event ID, schema version, occurrence/recorded time, project/lead IDs, actor and relevant event-time ownership | Project event/delivery boundary; retry delivery does not create duplicate reporting events |

First-payment confirmation must exist independently of handover submission so the administrator can report paid-but-not-handed-over leads. A handover snapshot alone is insufficient. Proposed schema addition: a dedicated versioned `idlm_lead_payment_confirmations` table (or equivalent canonical lead-owned structured record), retaining corrections/revocations and a stable confirmation ID. It records receipt-confirmation evidence and allocations against versioned expected milestones, including the agreed quote/revision basis, calculated balances and correction history; it is not an accounting ledger. Extend the proposed lead-owned storage with versioned schedule/milestone and receipt-allocation records as needed, reusing quotation snapshots rather than duplicating editable quotation terms. Do not couple the record's creation to project creation or automatic handover.

The original accepted sales baseline remains the comparison default. If an amendment changes the operational baseline, retain both revision identities/amounts and disclose the amendment; never rewrite historic value-change metrics silently. For discount changes, distinguish deliberate changes to discount settings from automatic amount recalculation caused by scope or quantity changes. A monetary discount-impact comparison must identify its calculation basis; do not attribute the entire total-price difference to discount when other inputs changed.

### Reporting conventions and safeguards

- Store instants consistently using the plugin's canonical timestamp convention with explicit timezone conversion; display dates/ranges using the configured business timezone. First release uses elapsed calendar time, not working-hour SLA calculations.
- Date-only completion targets expire at the end of the local target day. Show gross elapsed time, hold duration and any explicitly labelled elapsed-minus-hold measure separately; do not present the latter as working time.
- Filters: period, manager, rep, studio, workflow/stage, hold reason and priority where appropriate. Current-state cards are as-of-now; the period applies to event/cohort metrics unless explicitly stated otherwise.
- Historical outcomes use manager/rep/studio at the event. Current workload uses current assignment. Counts use distinct projects/leads; joins to revisions/files must not multiply counts.
- Show original-target and current-target performance separately. A deadline change must not erase previous overdue exposure. Show completed-on-time rates only with an explicit target basis, denominator and missing-target count.
- Keep currencies separate; no cross-currency totals without an explicit exchange-rate policy. Quote changes are not revenue, collected cash, profit or customer acceptance. Confirmation records do not imply bank reconciliation.
- Use medians with sample sizes for elapsed-time comparisons. No designer performance score or utilization metric without reliable complexity/capacity data. Revision count alone is not a quality measure.
- Missing legacy events/timestamps are Unknown, excluded from duration calculations with an excluded count. Do not backfill fabricated acceptance/payment/completion times from updated-at.
- Reporting must handle resubmission, reassignments, repeated stage visits, multiple holds, reopening, cancelled projects, payment corrections, baseline amendments and suspended users. Audit actor identities remain historical even if accounts change.
- Dashboard reads are bounded and indexed. Any aggregate cache must be permission/scoping-aware and invalidated after relevant committed changes. Queue totals and drill-downs must agree under the same filters and as-of semantics.

## 11. Verification and release acceptance

Before implementation is considered complete, verify:

- Won → first-payment confirmation → manual submission → return/resubmit → acceptance → assignment, including missing payment evidence, duplicates, confirmation correction/reversal, and no automatic handover on Won/payment confirmation.
- Advance receipt required at manual handover; expected amounts match the selected revision’s saved timeline and decimal rounding. Verify that short advances (including ₹1/₹10/₹100/₹1,000 differences) allow handover without manager approval and remain pending; verify screenshot-only, URN-only and both-evidence receipts, missing evidence/date/amount rejection, later balance collection without double counting, partial/multiple/excess receipts, duplicate prevention, currency mismatch, missing legacy terms, corrections, explicit schedule amendments and unchanged historical snapshots.
- Discount immutability for Design Reps across every writer; authorized manager/admin changes require remarks and preserve before/after audit history, sales history and revision sequencing.
- Rep/manager/sales permissions across workspace, direct requests, search, autosave, uploads and downloads; immediate reassignment/suspension effects.
- Workflow publication, archive rules, explicit version migration, permitted transitions, hold/resume and completion/reopening history.
- All five confirmed design phases and their separate evidence slots; missing/wrong-type/archived evidence blocks the configured gate, payment gates validate linked confirmation, and payment corrections flag affected work. Verify the 10% percentage basis and screenshot requirement without assuming an arbitrary first payment is sufficient.
- Assigned Design Rep item creation and name/description/detail edits; scoped manager access, immutable source revisions, unchanged discount restrictions, and the finalized rate policy.
- Document versioning, category requirements, private retrieval, archive and erasure/recovery integrity.
- Same quotation base number and global revision sequence across handover; saved design designation/designer identity; exact old sales outputs and commercial evidence preserved.
- No design write to sales quote-value projections/Won reporting; safe context-aware pointers and concurrent save conflicts.
- Atomic rollback, retryable event delivery, consumer deduplication, migration retry, deletion/reset, and truthful backup/restore behavior.
- Existing sales quotation editing, exports, totals, numbering, and protected-history regressions; frontend/admin compatibility where applicable.
- Dashboard counts/drill-down consistency and role scope; intake queues before project creation; payment confirmation/reversal timing; resubmission, reassignment, repeat stage visits, holds, deadline changes, completion/reopening, currency separation and unknown legacy data.
- Reporting uses committed business facts once despite event-delivery retries, and design activity does not alter sales conversion/revenue attribution.

## 12. Open business decisions

1. Confirm the recommended `DES` suffix and whether it is shown on all exports (recommended yes).
2. Decide designer rate-override authority consistently with the confirmed discount restriction, and whether manager review is required before customer sharing.
3. Finalize the proposed handover checklist, mandatory fields and payment verification policy. The five design-phase upload requirements and mandatory payments at Material Selection and Kick Off Meeting are confirmed; decide entry-versus-completion gate timing, the 10% quotation/revision basis, Kick Off Meeting payment amount/evidence, P2P payment amount/confirmation policy, quote/MOM file formats, and whether P2P marks completion.
4. Decide correction/reversal handling for first-payment confirmation after accepted handover, advance-milestone mappings and receipt-verification policy. Actual positive advance entry with date, amount and screenshot or URN/reference before manual submission is confirmed. Short payments permit handover without manager approval; carry the unpaid balance forward and show pending collection.
5. Confirm manager team/studio boundaries and sales visibility into internal design documents.
6. Confirm additional-field support and basic project tasks as release-one scope. Design Rep item creation and editable item names/descriptions/details are confirmed; finalize remaining item-detail fields and whether project-only, non-priced items are needed.
7. Define the manager exception flow for post-handover sales corrections and baseline amendments.
8. Confirm business timezone, stage-purpose tags and target durations, hold-reason vocabulary, and whether payment expectations should create overdue intake indicators. Elapsed calendar time is the proposed first-release duration basis.

These decisions do not authorize implementation. Preserve this plan until implementation is explicitly requested.
