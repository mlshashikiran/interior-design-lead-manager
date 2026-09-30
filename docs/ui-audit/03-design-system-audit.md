# Design-system audit

## What is already usable

`assets/css/admin-style.css:7-105` supplies a credible foundation: semantic color tokens, surface/text tokens, 4px spacing scale, type/radius/shadow/layer/motion tokens, responsive references and legacy aliases. Inter is locally hosted. `dialog.js` supplies focus trapping, inert background, scroll locking, Escape and focus restoration.

## Findings

| ID | Area | Current implementation | Problem | Evidence | Recommendation | Classification | Scope | Impact | Risk | Dependencies |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| DS-01 | Tokens | Token layer plus direct values | Tokens are not consistently the implementation authority | Direct colors/radii/shadows in `admin-style.css`, `kanban.css`, PHP inline styles; e.g. drawer and badge literals | Retain existing tokens; replace literals only when the same semantic decision recurs | MERGE | GLOBAL | High | Medium | Inventory selector ownership |
| DS-02 | Controls | `--idlm-control-height:34px`; local 29/30/32/36/38/40px controls | The documented `docs/DESIGN.md` says 40px while runtime declares 34px, and contextual overrides proliferate | `admin-style.css:46`, 1777–85, 1815, 2185; `quotations.css:66,107` | Choose and document desktop base/compact/touch sizes; map current classes before changing sizes | SIMPLIFY | GLOBAL | High | Medium | DS-01; task analysis |
| DS-03 | CSS cascade | Repeated core selectors and late overrides | Shared contracts are distributed through a 7,804-line cascade, making screen changes unpredictable | Multiple definitions for `.idlm-table*`, `.idlm-drawer-content-wrapper`, `.idlm-card`; 61 media queries | Establish CSS layers by foundation/primitives/patterns/modules and migrate incrementally; no wholesale rewrite | MERGE | GLOBAL | High | High | visual regression baseline |
| DS-04 | Quotation settings | Same `.idlm-settings-inline*` block twice | Exact duplicated implementation adds divergence risk | `assets/css/quotations.css:127-128` | Remove the duplicate as a safe legacy-cleanup phase, after checking stylesheet order | REMOVE | PAGE | Low | Low | visual smoke check |
| DS-05 | Native/CRM vocabulary | `.idlm-btn` mixed with WordPress `.button*` and auth `.input` | Visually similar actions and fields do not share a reliable contract | auth templates; quotation renderer; `quotations.js` generated buttons | Add compatibility styling/adapters rather than replacing WordPress semantics wholesale | MERGE | GLOBAL | Medium | Medium | Button/Field contract |

## Token direction

Keep the existing semantic palette and compact operational intent. Formalize only decisions already represented broadly: action/info/success/warning/danger, canvas/surface/text/border, space scale, compact/default/touch control sizes, radius compact/default/panel/pill, elevation, focus, layers, and the existing four breakpoints. Do not tokenise every one-off print or Kanban stage color.

## Accessibility notes

The baseline is strong where `dialog.js` and recent templates are used. Rework should specifically verify legacy inline handlers, direct modal paths, icon-only action labels, dynamically generated controls, visible focus after table/drawer refresh, and color-plus-text status treatment. The source review found no basis to claim all such paths meet the same standard.
