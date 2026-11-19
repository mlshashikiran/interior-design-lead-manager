# shadcn/ui foundations for the CRM design layer

**Builder:** Shashi
**Status:** In review on branch `feat/shadcn-ui-foundations`
**Contract:** `design-system/interior-design-lead-manager/STANDARD.md` wins every conflict below.

## Why not install shadcn/ui

shadcn/ui copies React and Tailwind source into a project. IDLM is PHP templates, vanilla JavaScript and hand-written CSS with no build step, and production (Hostinger) cannot run one. Installing it would add a second rendering path and a second style system, which STD-CSS-9 forbids.

We take what makes shadcn/ui good instead: a small set of semantic role tokens, a fixed anatomy per component, and named variants and sizes, implemented as gated CSS on the existing classes.

## Scope of this change

### 1. Role tokens (`crm-design.css`, section 1a)

shadcn role names, prefixed `--idlm-ui-*` so they never collide with host theme variables or with the older `--idlm-secondary` / `--idlm-muted` meanings. Each is an alias of an existing token: no new hex colours.

| Role | Resolves to |
|------|-------------|
| `--idlm-ui-background` / `-foreground` | canvas / text |
| `--idlm-ui-card` | surface |
| `--idlm-ui-muted` / `-muted-foreground` | readonly / text-muted |
| `--idlm-ui-accent` | hover tint |
| `--idlm-ui-primary` / `-primary-foreground` | action / action-contrast (white-label) |
| `--idlm-ui-destructive` / `-destructive-soft` | danger / danger-soft |
| `--idlm-ui-success`, `-warning`, `-info` (+ `-soft`) | semantic status tokens |
| `--idlm-ui-border` / `-input` / `-ring` | border / control-line / focus-ring |
| `--idlm-ui-radius`, `-radius-sm` | radius / radius-compact |

White-label tokens are set on `.idlm-wrap`, `.idlm-workspace`, `.idlm-access-restricted` and `.idlm-dialog`, not `body`, so the aliases are declared on those roots too. Declared on `body`, they would freeze the default brand colour.

### 2. Button (variants and sizes)

The existing classes stay (`idlm-btn-primary`, `-secondary`, `-quiet`, `-danger*`, `-icon`, `-compact`). Added to complete the shadcn set:

| shadcn | IDLM |
|--------|------|
| default | `idlm-btn-primary` |
| outline / secondary | `idlm-btn-secondary` |
| ghost | `idlm-btn-quiet` |
| destructive | `idlm-btn-danger-outline` (prepare), `idlm-btn-danger` in the final dialog |
| link | **new** `idlm-btn-link` |
| size sm | **new** `idlm-btn-sm` (28px on desktop, 44px on phones). The older `idlm-btn-compact` keeps 34px so existing screens do not change |
| size icon | `idlm-btn-icon` |
| icon slot | **new** `[data-icon="inline-start"|"inline-end"]` on a dashicon or SVG: sized by the button, never by the icon |

### 3. Badge

`idlm-badge` with `is-success`, `is-warning`, `is-danger`, `is-info`, `is-neutral` already exists. Added: `is-outline` (bordered, no fill, for identity such as source) and `is-strong` (Won). Stage and quality badges, `idlm-badge[data-idlm-stage]` and `[data-idlm-quality]`, use the STD-CRM-11 tones so badges, drawers and selects agree.

### 4. Field

shadcn's Field anatomy mapped onto existing classes:

| shadcn | IDLM |
|--------|------|
| `Field` | `idlm-form-group` (or new `idlm-field`) |
| `FieldLabel` | `label` / `idlm-form-label` |
| `FieldDescription` | `idlm-field-help` |
| `FieldError` | `idlm-field-error` |
| `FieldSet` + `FieldLegend` | **new** `idlm-fieldset` + `idlm-fieldset-legend` |
| validation | `data-invalid` on the field, `aria-invalid="true"` on the control |
| disabled | `data-disabled` on the field, `disabled` on the control |

`data-invalid` turns the label and error red and keeps the help text muted. `IDLMDialog.setFieldError()` keeps working unchanged.

### 5. Review page (local and development only)

A Design system view in the workspace (`?idlm_view=ui-kit`) renders every variant, size and state on live classes. It is registered only for administrators and only when `wp_get_environment_type()` is `local` or `development`, so production never shows it.

## Cleanup (legacy CSS)

`admin-style.css` loads under every CRM page and the design layer overrides it. Removing it in one step is unsafe: it owns the base tokens (spacing, radius, layers, status colours) and most of its ~1,440 rules still match live markup.

1. **Dead rules and selectors (done in 1.109.0).** Rules and selector-list entries whose `idlm-` classes and IDs appear nowhere in PHP, templates or JavaScript, with dynamic class prefixes such as `idlm-badge-`, `idlm-stage-` and `idlm-x-<?php …` counted as used. Removed 10 rules and 25 selectors from `admin-style.css` and 2 selectors from `crm-design-payments.css`. `quotations.css` and `payments.css` were left alone because they style printed and PDF quotations (STD-CSS-8).
2. **Overridden rules (done in 1.109.0).** Measured in Chrome on the local site, in iframes, on 13 view states (every workspace view, the lead drawer open, the quotation editor, catalogue and quotation settings) at 1440px and 390px: each top-level rule was removed from the live sheet alone and the computed value of every property it declares was compared on every element it matches. 269 rules matched elements and changed nothing. Rules were then kept if they guard `[hidden]` or `display: none`, use `data-idlm-ui` utilities, set size or position, use `!important`, contain any text a contract test asserts, or have a twin (the same selector with or without `.idlm-wrap`), because twins hide each other when removed one at a time. That left 113 rules (about 365 lines) removed.
   Final check: all four changed stylesheets swapped live against `origin/main`, comparing all 458 computed properties (excluding resolved size and position) on every element, on 16 view states at 1440, 1100, 782 and 390px. Result: no difference on any screen; the catalogue view was checked with a 42-property sample only (the full snapshot stalls Chrome there). Details in `docs/CHANGELOG-DEV.md`.
   Not measured: rules inside media queries, rules with state pseudo-classes or pseudo-elements (102), and 220 rules that matched nothing in any measured state. They stay.

Anything that cannot be measured stays. What remains is listed in `docs/TECH-DEBT.md`.

3. **Second pass (1.109.1).** Rules inside media queries measured at the widths where they apply, and every view also measured with modals, drawers, filter panels and `details` forced open. 49 more rules removed; rules naming a state class are now always kept, because forcing several open classes at once makes each look redundant. Details in `docs/CHANGELOG-DEV.md`; what remains and why in `docs/TECH-DEBT.md`.

## Out of scope, next

- A Cmd-K command palette (shadcn `Command` in a `Dialog`) for lead search and navigation.
- Moving screen markup onto the new Badge anatomy screen by screen. Field moved in 1.110.0 for Settings, Users and access, Leads dialogs and filters, My account and the log filters; the lead drawer, quotation editor, Pipeline, payments and design-team dialogs are still to do.
- Emptying `crm-legacy-shared.css` (what is left of the retired `admin-style.css`): see `docs/TECH-DEBT.md`.
