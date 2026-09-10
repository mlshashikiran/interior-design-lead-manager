# White Label Settings Plan

Builder: Shashi

Status: Implemented in 1.101.29

Target: Settings > White label

## 1. Design direction

This is an extension of the existing CRM Settings experience. It uses the current Inter type family, configurable colour tokens, 8px control and card radius, border-first surfaces, sticky save feedback, and accessible tab behavior.

- Design variance: 4. Structured with one asymmetric preview layout.
- Motion intensity: 2. State feedback and hover transitions only.
- Visual density: 6. Compact enough for administrators, with related controls grouped by outcome.
- Theme: Current light CRM theme. The saved brand colours affect branded surfaces only and do not recolour the Settings editor.
- Accent: Existing `--idlm-action` and `--idlm-brand-gold`. Customer-selected colours appear only in the live preview.

## 2. Information architecture

Add **White label** after **General** in the goal-oriented Settings tabs. Keep it inside the existing `idlm-settings-form` so it inherits the nonce, capability check, dirty-state tracking, and sticky Save settings action.

The section has four outcome-based groups:

1. **Brand identity**
   - Brand name
   - Logo for light surfaces
   - Optional compact logo or mark
   - Browser icon
2. **Brand colours**
   - Primary action colour
   - Accent colour
   - Sidebar colour
   - Accessible contrast result beside each relevant colour
3. **Workspace identity**
   - Workspace title
   - Sign-in heading
   - Support email
   - Optional footer text
4. **Brand visibility**
   - Show or hide the plugin attribution
   - Restore default branding

Phase 1 should not include custom CSS, arbitrary JavaScript, font uploads, email-template branding, domain mapping, or per-role branding. These increase security, support, and consistency risk and should be considered separately.

## 3. Desktop layout

Use a two-column composition at 1024px and above:

```text
+---------------------------------------------------------------+
| White label                                                   |
| Apply your company identity to CRM entry and workspace areas. |
+--------------------------------------+------------------------+
| Brand identity                       | Live preview           |
| [Brand name_______________________]   | +--------------------+ |
| [Logo upload] [Replace] [Remove]      | | sidebar | workspace | |
| [Compact mark] [Replace] [Remove]     | | logo    | title     | |
| [Browser icon] [Replace] [Remove]     | |         | button    | |
|                                      | +--------------------+ |
+--------------------------------------+ Preview updates locally |
| Brand colours                        | and is not saved until  |
| [swatch] Primary [#7A5017________]    | Save settings.          |
| Contrast: Pass                       |                        |
| [swatch] Accent  [#C99644________]    | [Reset preview]        |
| [swatch] Sidebar [#111A24________]    |                        |
+--------------------------------------+------------------------+
| Workspace identity                                            |
| [Workspace title____________] [Support email_______________]  |
| [Sign-in heading___________________________________________]  |
| [Footer text_______________________________________________]  |
+---------------------------------------------------------------+
| Brand visibility                                              |
| [ ] Show "Powered by Interior Design Lead Manager"           |
| [Restore default branding]                                    |
+---------------------------------------------------------------+
| No unsaved changes                           [Save settings]   |
+---------------------------------------------------------------+
```

The editor column uses existing card, field, help, button, callout, and savebar classes. The preview is a real miniature rendering of the branded shell, not an image or simulated dashboard. It stays sticky only when the viewport has enough height and never overlaps the Settings savebar.

## 4. Responsive behavior

- Below 1024px, place the preview above the fields so branding changes remain visible while editing.
- Below 782px, collapse every multi-column field group to one column and make upload actions full width.
- Below 480px, use the existing full-width save button. Keep colour text inputs visible beside or below their native colour controls.
- Do not use horizontal scrolling for the editor or preview.
- The preview may simplify to header, mark, workspace name, and primary action on phones.

## 5. Interaction and states

### Upload controls

Use the WordPress media library. Each asset control shows a reserved preview area, file requirements, Replace, and Remove actions. It must support:

- Empty: upload prompt and accepted dimensions/types.
- Selecting: reserved skeleton with status announcement.
- Ready: image preview, filename, dimensions, Replace, and Remove.
- Error: nearby actionable error that keeps the previous saved asset.

Recommended validation:

- Main logo: PNG, JPEG, WebP, or SVG only if sanitized through an approved SVG path; maximum 2 MB.
- Compact mark: square image, recommended 256 x 256px or larger.
- Browser icon: square PNG, recommended 512 x 512px.

### Colour controls

Pair a native colour input with an editable hexadecimal value. Validate and normalize to six-digit uppercase hex on blur. Show a labelled WCAG result for primary button text and sidebar navigation text. Reject combinations that fail the minimum contrast contract instead of silently changing the chosen colour.

### Preview

Update the preview locally as valid fields change. Do not write options until the existing Settings form is submitted. The preview needs a short notice: "Preview only. Save settings to publish these changes."

### Reset

"Restore default branding" opens the shared confirmation dialog and lists what will reset. It changes the form values and preview first; the administrator must still choose Save settings to persist the reset.

### Feedback

Reuse the existing dirty, saving, saved, and error savebar states. Focus the first invalid control after a failed submission and provide a linked error summary when more than one field fails.

## 6. Component and styling contract

Add only white-label-specific layout classes. Reuse all current primitives for the rest.

- `.idlm-white-label-layout`: editor and preview grid.
- `.idlm-white-label-preview`: contained preview surface with `12px` panel radius.
- `.idlm-brand-asset-control`: asset preview and actions.
- `.idlm-brand-colour-control`: swatch, hex value, and contrast status.
- `.idlm-brand-contrast`: text plus icon status. Colour is supplementary.

Use CSS custom properties scoped to the preview for unsaved values:

```css
.idlm-white-label-preview {
  --idlm-preview-primary: var(--idlm-action);
  --idlm-preview-accent: var(--idlm-brand-gold);
  --idlm-preview-sidebar: var(--idlm-sidebar);
}
```

No glow, gradients, glass effects, decorative status dots, or additional card radius system. Transitions are limited to 150-200ms colour, border, shadow, and opacity changes and must respect reduced motion.

## 7. Theme isolation contract

The plugin UI must be visually independent from the active WordPress theme. Site theme settings and global page-builder styles must not determine the plugin's typography, colours, spacing, radii, shadows, controls, links, buttons, tables, dialogs, or responsive behavior.

- Every frontend CRM surface must render beneath one plugin-owned root such as `.idlm-workspace` or `.idlm-access-shell`.
- All plugin component selectors must be scoped beneath a plugin-owned root. Do not publish generic selectors such as `.button`, `.card`, `input`, or `table` without that root.
- Define the complete plugin token set on the plugin root. White-label values may override only documented `--idlm-*` semantic tokens.
- Set the local Inter family, text colour, line height, font smoothing, box sizing, and form font inheritance explicitly at the plugin root.
- Normalize headings, paragraphs, lists, links, buttons, inputs, selects, textareas, tables, and images inside the plugin root so inherited theme margins and presentation cannot alter layouts.
- Use plugin classes for controls and components. Do not depend on theme classes, theme CSS variables, Elementor Kit variables, or block-theme `--wp--preset-*` values.
- Enqueue plugin styles only on plugin-owned admin and frontend CRM screens. Load the plugin's component stylesheet after ordinary theme styles, while keeping selectors narrowly scoped.
- Avoid broad `!important` rules. Where a theme uses high-specificity global selectors, use a deliberate plugin-root specificity layer such as `.idlm-workspace.idlm-workspace` for the affected component contract.
- Do not use `all: initial` or `all: unset` on the entire plugin tree. These can remove accessibility and browser behavior. Apply explicit, scoped normalization instead.
- WordPress admin chrome remains WordPress-owned. The plugin controls only the content inside `.idlm-wrap`.
- The live white-label preview must read only plugin preview tokens. It must never resolve colours or typography from the active theme.

White labeling customizes the plugin design system; it does not connect the plugin to the site's theme customizer, `theme.json`, global styles, or page-builder design settings.

## 8. Data and security plan

Store non-secret settings in one grouped `idlm_white_label_settings` option with explicit defaults and an independent settings schema version if future migrations require it.

Suggested keys:

```text
brand_name
logo_id
compact_logo_id
favicon_id
primary_color
accent_color
sidebar_color
workspace_title
login_heading
support_email
footer_text
show_plugin_attribution
```

- Store media attachment IDs, not raw URLs.
- Sanitize text with field-appropriate WordPress functions.
- Validate media ownership, MIME, extension, size, and image dimensions server-side.
- Require `manage_options` and the existing settings nonce for writes.
- Escape every value at output and never render administrator-provided HTML.
- Provide a single helper that returns defaults merged with saved values. Consumer screens should not read the raw option directly.
- Keep the plugin name and version unchanged in WordPress plugin metadata. White labeling applies only to CRM-facing presentation.

## 9. Planned application surfaces

Phase 1 applies saved branding to:

- CRM sign-in and password recovery.
- Frontend workspace sidebar and header.
- My Account identity area.
- Browser icon on plugin-owned frontend CRM pages when configured.

Wp-admin menu labels, capability names, integration payloads, logs, REST routes, database names, and plugin metadata remain unchanged.

## 10. Implementation map

1. Add white-label defaults/read helper and validated persistence in the Settings boundary.
2. Add renderer context for values, media metadata, and default contrast results.
3. Add `templates/admin/settings/white-label.php` and register its tab in the Settings shell.
4. Extend `assets/js/settings.js` for media selection, local preview, hex synchronization, contrast checks, and reset confirmation.
5. Add scoped layout and state rules to `assets/css/admin-style.css`.
6. Strengthen plugin-root normalization and token ownership across every Phase 1 frontend surface without introducing broad global selectors.
7. Consume the centralized helper on sign-in, recovery, workspace, and account templates.
8. Add fast contracts for tab semantics, authorization, nonce use, sanitization, output escaping, media validation, no raw custom CSS, responsive classes, theme-variable independence, scoped selectors, and unchanged plugin metadata.
9. Update Settings, interface, feature-map, design-system, and release documentation during implementation.

## 11. Acceptance criteria

- An administrator can configure, preview, save, replace, remove, and restore every supported brand field.
- Invalid colours, email values, and media are rejected with nearby errors and no loss of prior saved values.
- Primary action and sidebar combinations meet WCAG 2.2 AA contrast.
- The new tab is keyboard operable and bookmarkable like existing Settings tabs.
- The editor works at 375px, 782px, 1024px, and 1440px without horizontal overflow.
- The same plugin screen has matching typography, spacing, controls, and layout under at least one classic theme, one block theme, and Elementor global styles.
- Changing the site's theme colours, typography, button radius, form styling, or content width does not change the plugin UI.
- Plugin styles do not leak into ordinary site content outside a plugin-owned root.
- Unsaved preview changes never leak to other users or screens.
- Saved branding appears consistently on the defined Phase 1 surfaces.
- Removing or resetting branding returns every surface to the plugin defaults.
- Reduced motion, focus visibility, save feedback, nonce, capability, sanitization, and escaping contracts remain intact.
- Plugin metadata, routes, capabilities, stored lead data, and database schema remain unchanged.

## 12. Delivery sequence

- Release A: Settings model, tab, fields, preview, validation, save, and reset. Completed in 1.101.29.
- Release B: Sign-in, recovery, workspace, and account consumption. Completed in 1.101.29.
- Release C: Cross-theme regression contracts, accessibility verification, and administrator documentation. Completed in 1.101.29.

Each release increments the plugin version and records Builder: Shashi in the changelogs.
