# Component inventory

## Consolidation candidates

| Canonical pattern | Existing implementations | Semantic equivalence | Recommendation | Affected files | Migration risk |
| --- | --- | --- | --- | --- | --- |
| Button / IconButton | `.idlm-btn*`; native `.button`, `.button-primary`, `.button-link`; login `.button-primary` | Primary/secondary/destructive actions are equivalent; print and WordPress-native actions are contextual | Partially consolidate: canonical CRM button contract; retain native print/admin compatibility wrappers | templates, `class-quotation-renderer.php`, `admin-style.css`, `quotations.css` | Medium |
| Form field | `.idlm-input`, `.idlm-drawer-control`, `.idlm-table-select`, native `.input` | Text/select controls are equivalent; dense inline table editing is a distinct density variant | Partially consolidate into base + compact variant | `admin-style.css`, access/account templates, quote/settings templates | Medium |
| Panel/card | `.idlm-card`, `.idlm-dashboard-panel`, `.idlm-quote-panel`, drawer panels, users cards | All group information, but dashboard metrics, drawer sections, and quotation total panel have different semantics | Keep metric/drawer/total variants; consolidate the shared surface, padding and heading contract | all CSS modules and renderers | Medium |
| Table | `.idlm-table`, quote items/table, native admin tables | Operational data lists are equivalent; quote item editor and print tables have domain/print constraints | Consolidate list-table shell only; keep quote-item and print table separate | `admin-style.css`, `quotations.css`, templates | Medium |
| Dialog | `IDLMDialog`, direct modal helpers in `admin-app.js`, Kanban move modal, quote catalogue overlays | Short blocking decisions are equivalent; lead drawer and full catalogue/editor work are not | Fully consolidate short dialogs on `IDLMDialog`; preserve drawer and work-modal variants | `dialog.js`, `admin-app.js`, `kanban.js`, `quotations.js` | High |
| Filter bar | Leads compact filters, dashboard filters, user/log filter drawer, quotation live-list search | All refine a collection, but user/log filters deliberately use an off-canvas pattern | Share controls/status/counts; retain inline vs drawer layout by context | templates/admin, `dashboard.js`, `users-logs.js`, `quotations.js` | Medium |
| Status badge | `.idlm-badge-*`, Kanban badges, drawer chips, user status | Source, lead stage, call status, and account status convey different domains | Use one semantic Badge primitive with domain mappings; do not collapse labels/states | `admin-style.css`, `kanban.js/css`, renderers | Medium |
| Pagination | Lead cursor buttons, quotation page-number/live pagination, dashboard records controls | Each represents a result set; cursor vs numbered state is materially different | Share layout/disabled/focus affordances, not the data model | `admin-app.js`, `quotations.js`, `dashboard.js/css` | Low |

## Reuse classification

### Global

Foundation tokens; Button/IconButton; Field (input/select/textarea + compact density); Alert/Toast; Badge; Card/Surface; PageHeader; Tabs; Toolbar/FilterBar; DataTable shell; Pagination shell; Dialog; Drawer shell; Empty/Loading/Error state.

### Domain-specific

LeadDrawer, LeadStageBadge, LeadIdentityCell, ActivityTimeline/Comment item, Task indicator, KanbanColumn/Card, QuoteSection/LineItem, QuoteTotals, Credential/Secret field. These have repeated business behavior and should not be flattened into generic markup.

### Page-specific

Dashboard chart cards and investigation records; System Health/readiness reporting; white-label preview; quotation print document; authentication context panel. Keep them local.

## Observed implementation boundary

`admin-style.css` is the shared UI implementation (7,804 lines); module files layer dashboard, Kanban, quotation and workspace rules. `dialog.js` is the accessible generic dialog authority, while `admin-app.js` (3,822 lines) still holds legacy shared lead/task/overlay behavior. Avoid introducing a framework: extract contracts only while migrating a concrete consumer.
