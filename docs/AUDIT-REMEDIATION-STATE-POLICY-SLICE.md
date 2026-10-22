# CORE-013 — State vocabulary and lifecycle policy

Author / Builder: **Shashi**. Date: **2026-10-07**. Candidate **1.102.56**, base `ff148ba62ec710a622d9b1af7e8f511b7e0b7385`; local working tree only. Core/routing/quotation schemas remain **1.3.9 / 1.0.1 / 2.5.0**.

## Finding and result

The October 6 CORE-013 finding identified conflicting workflow descriptions and incomplete registry vocabularies. Current source and behavioral characterization establish the implemented policy in [STATE-POLICY.md](STATE-POLICY.md). The registry owns labels/contextual subsets; canonical DB/lifecycle methods own transition invariants and effects; request handlers own actor/object/evidence authorization. This is not a permitted-edge finite-state machine.

Added the five missing manual task types and the spam/test, administrator-exception and historical legacy-unqualified reasons, preserving existing registry labels and persisted keys. Manual task form/validation and early-close form/validation now use shared contextual lists. Qualification's one quality-coupled stage and the broader supplied-quality validation stage set have explicit names and callers. No allowed transitions, evidence exceptions, roles, hooks, settings or existing stored values were changed.

Focused docs now describe single-identity inbound matching versus multi-candidate review, available review resolution, six-cycle task repair, qualification versus outreach gates, permitted reversals, historical migration differences and separate browser-print / Dompdf PDF-download paths. Current Draft/Superseded quotation writers are distinguished from the still-open QUO-010 historical-state preservation defect. LOGICS.md is explicitly marked as a historical static audit; October 6 audit findings and prior slice evidence remain unchanged.

## Verification

- **284 fast checks / 0 failed**, including five new behavioral contracts: accepted/reserved task types, early-close evidence/administrator boundaries, undecided quality across sales stages, supplied-quality versus stage-only validation, the legacy Visited versus undecided Visited Studio task guard, custom stages and terminal reversal history.
- **5 focused native checks / 0 failed**: fresh physical schema prerequisite plus four CORE-013 cases for stage/quality behavior, custom and terminal reversals (manual tasks stay cancelled), idempotent historical repair, and actual WordPress drawer DOM options/General default across pre-sales, mixed pre-sales/administrator and administrator actors.
- **14 existing task-plan native checks / 0 failed**: fresh schema plus thirteen CORE-006 cases, including legacy four-day plans, missing-slot repair, preserved history, required-write failures, evidence-driven reopening, live ownership, terminal policy, two-worker races, additive upgrade, sweep progress and backup compatibility. Across both native runs: **18 distinct cases**, with the fresh schema prerequisite run twice.
- **11 changed PHP files** pass syntax checks; `git diff --check` passes. No JS/CSS changes. Dropdown values, order, labels, grouping, default and role visibility retain their existing behavior; native DOM checks exercise the rendered request path. No new browser interaction/viewport or HTTP-transport claim is made by this slice.

Native environment: PHP 8.4.14, WordPress 7.1.3, MySQL 8.4.0; socket-only disposable MySQL, WP-Cron, no persistent cache. All native fixture databases were dropped and the owned server shut down. The configured site database was not used. Final native source includes the explicit cancelled-manual-task assertion.

Fast stderr contains the existing test-double warning about a missing `phone` property in a contact-match fixture; this preceded CORE-013 and is not a native runtime warning. Final native stderr is empty. New test expectations were corrected during characterization for WordPress empty `get_var()` results and the existing pre-sales-only drawer section; production policy was preserved.

[Machine verification](remediation-2026-10-07/state-policy-slice/verification.json), [fast log](remediation-2026-10-07/state-policy-slice/fast.log), [focused native log](remediation-2026-10-07/state-policy-slice/native.log), [task-plan native log](remediation-2026-10-07/state-policy-slice/task-repair-native.log), [cleanup](remediation-2026-10-07/state-policy-slice/cleanup.json), [graph refresh](remediation-2026-10-07/state-policy-slice/graph-refresh.json).

## Graph and remaining gates

The bounded Graphify query **“State registry qualification call status stages transition invariants documentation consistency”** connected the finding to the registry, DB normalization and lifecycle seams. Source inspection and executable contracts established the policy; graph edges were navigation aids. The refresh re-extracts changed PHP and explicit focused-document headings while retaining unrelated graph content. It does not infer new semantic relationships or regenerate HTML reports.

CORE-013 is **IMPLEMENTED, not CLOSED**. Supported runtime floors/matrix, real Action Scheduler, persistent cache, exact package/CI/artifact qualification and configured-site rehearsal remain open. The task-plan source still distinguishes legacy `Visited` (excluded) from undecided `Visited Studio` (active); the new fast characterization records that edge explicitly. Changing that behavior is outside this policy-preserving slice. This is documentation reconciliation and vocabulary reuse, not approval of a stronger stage-order FSM or a changed qualification policy. M3's listed findings now have local implementation evidence; its full release exit remains unqualified. There are **25 OPEN / 19 IMPLEMENTED** findings, none CLOSED. No commit, package or deployment was created.

Next bounded work: **QUO-004 — failed quotation migrations can be marked complete**, returning to the outstanding M2 quotation persistence/upgrade prerequisites before dependent quotation work.
