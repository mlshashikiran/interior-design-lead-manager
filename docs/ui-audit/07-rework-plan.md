# Dependency-aware rework plan

| Phase | Scope and purpose | Dependencies / affected areas | Expected consolidation | Risk | Recommended model |
| --- | --- | --- | --- | --- | --- |
| 0 | Capture a visual/functional baseline for admin and shortcode surfaces at desktop, tablet and mobile; include roles and lead/quote mutation paths | Existing UI regression contracts; running authenticated test data | None | Medium | Luna (low) discovery + Terra (medium) validation |
| 1 | Reconcile the existing foundation: token authority, control density policy, focus/disabled/semantic states, CSS layer ownership | DS-01–03; `admin-style.css`, module CSS, `docs/DESIGN.md` | Foundation and primitive contracts | High | Terra (medium) |
| 2 | Make `IDLMDialog` the short-dialog authority and document distinct drawer/work-modal contracts | CI-01; `dialog.js`, `admin-app.js`, Kanban, quotations, settings | Confirmation/prompt lifecycle | High | Terra (medium) |
| 3 | Establish shared Button, Field, Surface, Toolbar, Table/Pagination and Badge presentation adapters; migrate one consumer at a time | Phases 0–2; templates/renderers/CSS | High-use visual contracts without data-model changes | High | Terra (medium) |
| 4 | Rework the lead list and LeadDrawer first: dominant daily workflow, dense controls, shared overlays, task/activity state | Phases 1–3; leads renderer/reads/AJAX/client and role checks | Lead identity, drawer shell, activity/task patterns | High | Terra (medium) |
| 5 | Align Kanban with lead primitives while preserving stage transition policy and mobile movement behavior | Phase 4; Kanban renderer/client/CSS | Lead badge/card/drawer relationship | Medium | Terra (medium) |
| 6 | Align quotation list/editor/catalogue and remove verified CSS duplication; retain print and transaction contracts | Phases 1–3; quotation renderer/client/CSS | Table/dialog/field adapters; duplicate settings block | High | Terra (medium) + Luna (low) mechanical cleanup |
| 7 | Simplify dashboard/settings/users/logs based on validated usage, not aesthetics; reduce redundant surfaces/navigation only where evidence supports it | Baseline + stakeholder usage input | Page headers, filters, tables, alerts, settings IA | High | Terra (medium) |
| 8 | Remove deprecated selectors/inline presentation only after migrations; run visual, keyboard, responsive and behavior regression checks | All prior phases | Legacy CSS/markup cleanup | Medium | Luna (low) search/cleanup; Terra (medium) review |

## First implementation area

Start with Phase 0–3 and then the lead list/LeadDrawer. It is the highest-frequency operational workflow, shares the most client and presentation infrastructure, and exposes the greatest payoff without redesigning business logic.

## Guardrails

- Preserve `IDLM_DB` lifecycle writes, capability/object access, quotation transactions and shortcode compatibility.
- Do not merge cursor and numbered pagination data behavior, stage-transition rules, print markup, or role-specific dashboard content solely because their chrome resembles another screen.
- Treat the existing dirty worktree as out of scope. These audit documents propose work only; they make no UI implementation changes.
