# UI Visual Regression Matrix

**Author / builder:** Shashi  
**Release:** 1.101.23  
**Scope:** Phase 4 design-debt, Phase 5 access/account, Phase 6 Settings, Phase 7 Users/logs, and frontend host-theme isolation regression coverage

Use the same representative CRM records and capture each row at `375`, `768`, `1024`, and `1440px`, then repeat the focused interaction at 200% zoom and with reduced motion.

| Surface | Administrator | Pre-sales | Representative | Empty | Loading | Error | Success |
|---|---:|---:|---:|---:|---:|---:|---:|
| Workspace shell/navigation | Yes | Yes | Yes | — | — | Restricted state | Active view |
| Authentication/My Account | Yes | Yes | Yes | Blank optional password | Submit | Field/auth failure | Updated/reset sent |
| Leads/list/drawer | Yes | Yes | Yes | No matches | Refresh/import | Field/import failure | Save/import |
| Kanban | Yes | Yes | Yes | Empty stage | Board refresh | Move rejected | Move complete |
| Dashboard | Yes | Yes | Yes | No chart data | Range refresh | Data request | Updated range |
| Quotations | Yes | — | Yes | No quotations/items | Catalogue search | Validation/autosave | Autosaved |
| Settings/health | Yes | — | — | Empty diagnostics | Diagnostic request | Connection/health failure | Saved/healthy |
| Users & Access | Yes | — | — | No matching users | Form submit | Account/safeguard failure | Created/updated |
| CAPI/Audience logs | Yes | — | — | No matching logs | Payload/retry request | Load/retry failure | Loaded/retried |

For Settings, include populated and empty option editors, duplicate/comma validation, keyboard add/remove, dirty/saving/saved states, configured/unconfigured credentials, reveal/copy controls, and connection-test success/failure at each canonical viewport.

For Users & Access, verify labelled filters at desktop and one-column mobile reflow, disclosure editing, active/suspended text, autofill metadata, pending form state, focused redirect feedback, and self-lockout/final-administrator messaging.

For CAPI and audience logs, verify filtered/unfiltered and empty results, keyboard-labelled selection, responsive record cards, inline retry states, shared-dialog focus trapping/restoration, lazy success/failure payload loading, copy feedback, long-token wrapping, and redacted request/response output.

For the frontend Leads view, repeat metric cards and lead-row open controls with an Elementor Kit global `button` rule enabled; CRM backgrounds, text, radii, and spacing must remain unchanged.

For lead drawers with conditional status context, verify the four operational fields remain visually primary, supporting reasons stay on one quiet truncated line with full values preserved in markup, no status scrollbar appears, and the header remains compact at every canonical viewport.

## Automated guardrails

- `tests/ui-regression-contracts.php` preserves the canonical viewport, role, state, motion, tab, dialog, token, and presentation-debt hooks.
- Shared state selectors are `.idlm-state.is-empty`, `.idlm-state.is-loading`, `.idlm-state.is-error`, and `.idlm-state.is-success`.
- The workspace exposes `data-idlm-role="administrator|pre-sales|representative"` so role variants can be targeted without changing capability enforcement.
- Settings templates must remain free of inline presentation styles; aggregate inline styles, hard-coded colours, and `!important` uses may only decrease from the Phase 4 budgets.
- Access/account contracts cover password visibility, requirement/help relationships, nearby match errors, busy submission state, redirect notice focus, autofill metadata, and the focusable skip destination.
- Users/log contracts cover labelled responsive filters, account pending/focus states, semantic captions and selection labels, lazy administrator payload reads, shared dialog/copy controls, mobile record cards, and inline retry feedback.
