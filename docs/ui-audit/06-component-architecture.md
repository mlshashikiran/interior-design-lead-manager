# Proposed lightweight component architecture

## Foundation

Retain `--idlm-*` as the sole foundation namespace. Clarify the active compact/default/touch sizing policy, then use CSS layers in this order: foundation → primitives → shared patterns → domain patterns → screen modules → print. Existing aliases remain only while consumers migrate.

## Primitives

- Button: primary, secondary, danger, quiet/action-link, icon, compact; one visible-focus and disabled contract.
- Field: input, select, textarea, checkbox/radio/switch; compact inline-table and touch variants.
- Badge, Alert, Toast, Tooltip, Surface, Empty/Loading/Error state.

## Shared patterns

- PageHeader, Tabs, Toolbar/FilterBar, DataTable shell, Pagination shell, Dialog, work-modal shell, workspace navigation.
- `IDLMDialog` remains the behavioral authority for short blocking dialogs. It should not own business validation or full work surfaces.

## Domain patterns

- LeadDrawer (identity/status, tabs, activity/tasks, footer); LeadIdentityCell; LeadStageBadge; Task/Follow-up indicator; KanbanColumn/Card.
- QuoteSection, QuoteLineItem, QuoteTotals and Catalogue search result.

## Boundaries

Keep dashboard charts, quotation print, authentication context, health/readiness and white-label previews page-specific. A PHP template plus a named class contract is sufficient; no JavaScript framework or generic component registry is justified by this repository.

## Migration rule

For each pattern: document current selectors and behavior → create/confirm canonical contract → migrate one high-use consumer → regression-test desktop/mobile/role paths → remove superseded rules only after no callers remain. Do not extract markup merely because it is repeated if its state or data authority differs.
