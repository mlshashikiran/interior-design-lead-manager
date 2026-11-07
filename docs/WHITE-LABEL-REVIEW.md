# White-label review

**Author:** Shashi
**Date:** 2026-11-07
**Branch:** `whitelabel/review` (cut from `streamline/plugin`)
**Scope:** white-label settings, token delivery, brand coverage, contrast, leftover product branding, and security. No option names, stored values, permissions or AJAX actions were changed.

## Verdict

The design is sound. There is one option (`idlm_white_label_settings`), it is validated in one place, saved only by `manage_options` behind a nonce, and colours are normalised to `#RRGGBB` before they reach any CSS. Tokens are scoped to plugin-owned roots.

Delivery and contrast were not right. The sidebar colour never reached the workspace sidebar. The accent colour is never contrast-checked, but it was used for the sidebar focus ring, the active icon and the text of the brand letter. The sidebar focus ring rendered in the primary colour at 2.5:1, even with the default brand. Those are fixed on this branch. The remaining gaps need a decision on validation thresholds (see the proposal at the end).

## How it works today

| Part | Where | Notes |
| --- | --- | --- |
| Option | `IDLM_Settings::WHITE_LABEL_OPTION` = `idlm_white_label_settings`, not autoloaded | Keys: `brand_name`, `logo_id`, `compact_logo_id`, `favicon_id`, `primary_color`, `accent_color`, `sidebar_color`, `workspace_title`, `login_heading`, `support_email`, `footer_text`, `show_plugin_attribution`. |
| Validation | `IDLM_Settings::validate_white_label()` | Text uses `sanitize_text_field` and `sanitize_textarea_field`, and the email uses `sanitize_email` plus `is_email`. Colours must match `^#[0-9A-F]{6}$`. Primary and sidebar must reach 4.5:1 against white. The accent is not checked. Images must be PNG, JPEG or WebP attachments of 2 MB or less that the current user can `edit_post`. |
| Save | `IDLM_Admin_Settings_Controller::process_request()` | `current_user_can('manage_options')`, then `check_admin_referer('idlm_settings_save_action')`. Any white-label error blocks the whole settings save. An unchanged asset ID skips the edit check, so another administrator's logo survives. |
| Read | `IDLM_Settings::get_white_label()` | Colours are normalised again on read, so a hand-edited option cannot inject CSS. |
| Tokens | `IDLM_Settings::white_label_style()` | Emits `--idlm-action`, `--idlm-action-hover` (darkened 18%), `--idlm-action-soft` (lightened 90%), `--idlm-focus-ring` (equal to primary), `--idlm-brand-gold` and `--idlm-sidebar`. **New:** `--idlm-sidebar-accent` and `--idlm-on-accent`. |
| Delivery | `IDLM_Admin_Assets::add_white_label_style()` (inline on `idlm-admin-styles` for `.idlm-wrap`, `.idlm-workspace`, `.idlm-access-restricted`, `.idlm-dialog`), plus a `style` attribute on `templates/workspace.php` and `templates/access-restricted.php`, plus `body.login` on the core password screens (`IDLM_Auth::enqueue_reset_assets()`) | Every route goes through `white_label_style()`, which outputs only validated hex. |

Hover and soft are derived in PHP by linear sRGB blending, not with `color-mix()`. That is deterministic and works in every browser. The hover is always safe: darkening a colour that reaches 4.5:1 against white keeps it at 4.5:1 or more. The soft tint is used only as a background. The on-colour text was always white (`--idlm-action-contrast`), which is safe for primary fills because primary is validated against white. It was not safe for accent fills.

## Findings

Severity: **High** means it breaks the feature or a WCAG AA requirement for a reasonable configuration. **Medium** means AA fails only for some valid configurations, or a brand surface is missed. **Low** means polish or defence in depth.

| # | Severity | Finding | Status |
| --- | --- | --- | --- |
| W1 | High | **The sidebar colour was ignored in the workspace.** `workspace.css` painted the sidebar with `var(--idlm-dark)`. `admin-style.css` defines `--idlm-dark: var(--idlm-sidebar)` at `:root`, so the alias resolved once to the default `#111A24` and never saw the per-workspace override. Measured: with the sidebar set to `#1F2937`, the rendered sidebar was still `rgb(17, 26, 36)`. | **Fixed** (`f0676d3`): both uses now read `var(--idlm-sidebar)`. |
| W2 | High | **The sidebar keyboard focus ring failed 3:1 even with the default brand.** The workspace-wide rule `.idlm-workspace :is(a, button, …):focus-visible` (specificity 0,3,2) beat the sidebar's own gold rule (0,3,1), so sidebar controls showed the primary colour on the dark sidebar: 2.50:1 by default, and 1.22:1 for a navy brand. | **Fixed** (`bd46261`): a more specific sidebar rule sets only `outline-color`, so the pointer-input reset still clears the ring after a click. Verified in Chrome: 7 sidebar controls now use the sidebar accent. |
| W3 | High | **The accent is never contrast-checked, but it marks the sidebar.** The active icon, the Today's work icon and the sidebar focus ring all used `--idlm-brand-gold` directly. A valid dark accent on a dark sidebar (`#3B2F80` on `#0B0A1F`) is 1.77:1. | **Fixed** (`bd46261`): new `--idlm-sidebar-accent` is the accent when it reaches 3:1 on both the sidebar and the active-item fill, and white otherwise (validation guarantees white at 4.5:1 on the sidebar). |
| W4 | Medium | **The brand letter was white on the accent.** When no compact logo is set, the letter is white on `--idlm-brand-gold`: 2.65:1 with the default gold and 1.47:1 with a light accent. | **Fixed** (`bd46261`): `--idlm-on-accent` is white, then the sidebar colour, then black, whichever first reaches 4.5:1. The default brand now shows a dark letter on gold. |
| W5 | Medium | **Mid-tone sidebars that pass the 4.5:1 rule still fail inside the sidebar.** Navigation text is 78% white (`workspace.css`, `!important`), and the active item is a 14% white fill. With `#737373` (4.74:1, accepted): nav labels 3.60:1, the active label 3.61:1. Login-panel copy on the sidebar uses fixed `#bdc8d5`, `#9baabd` and `#f5f3ef` and degrades the same way. The default and any sidebar darker than about 7:1 against white pass. | **Needs decision**. See P1. A layer fix would need `!important` (STD-CSS-3) or a rewrite of `workspace.css`. |
| W6 | Medium | **Primary as text on tinted surfaces.** Primary only has to reach 4.5:1 on white. As link or tab text on the canvas `#f5f3ef` or on `--idlm-action-soft`, a primary near the limit drops to about 4.0:1 to 4.3:1 (for example `#0E7C86`: 4.46:1 on canvas and 4.33:1 on soft). The default brand is 6.08:1 or better. | **Needs decision**. See P1. |
| W7 | Medium | **Fixed product copy on the sign-in panel.** `templates/access-restricted.php` printed "Lead Manager" (label and `aria-label`) instead of the workspace title. | **Fixed** (`552ffd9`). |
| W8 | Low | **The core login header was unescaped.** `wp-login.php` echoes the `login_headertext` filter without escaping, and `IDLM_Auth::login_header_text()` returned the raw brand name and title. `sanitize_text_field` already strips tags, so this is defence in depth. | **Fixed** (`552ffd9`): `esc_html()`. |
| W9 | Low | **The brand letter split multibyte names.** `strtoupper(substr(…, 0, 1))` printed half a character for names such as "Écrin" or Devanagari names. | **Fixed** (`552ffd9`): `mb_substr` and `mb_strtoupper`, with the old path as fallback. |
| W10 | Low | **The quotations page had no CRM favicon.** `render_frontend_favicon()` skipped `[idlm_quotations]`. | **Fixed** (this branch): the shortcode was added to the list. |
| W11 | Low | **Notification emails carry fixed product branding.** Subjects start with `[Lead Manager]` (`class-handoff-notifications.php`, `class-design-handoffs.php` ×4, `class-integrations.php`). Bodies are plain text with no brand. | **Needs decision**. Recipients may filter on the prefix. See P3. |
| W12 | Low | **The wp-admin menu and utilities page are titled "Lead Manager"** (`class-admin.php`). They are seen only by administrators. | Open. See P3. |
| W13 | Low | **The login panel has a fixed gold label colour `#e5c28a`** (`.idlm-login-product`) instead of a brand token. | Open. Tied to W5. It needs a text-safe sidebar token, and 3:1 is not enough for 12px text. |
| W14 | Low | **The settings preview does not show the derived tokens.** `settings.js` previews `--idlm-brand-gold` on the mock sidebar, so a low-contrast accent looks gold in the preview but renders white live. | Open. Cosmetic. Could mirror `sidebar_accent()` in JS. |
| W15 | Low | **Dead aliases.** `--idlm-primary`, `--idlm-primary-hover`, `--idlm-gold`, `--idlm-gold-dark` and `--idlm-gold-soft` in `admin-style.css :root` have no users and would freeze at the defaults like W1 if used. | Open. Safe to delete in a cleanup pass. |
| W16 | Info | **Quotation print and PDF** use their own Quotation Setup accent (default: the white-label accent), and only as a 3px header rule. `sanitize_hex_color` runs on save. The company name falls back from the brand name. This complies with STD-CSS-8. | No change. |
| W17 | Info | **Public forms.** The plugin has no public lead form: leads arrive through webhooks. There is nothing to brand. | No change. |
| W18 | Info | **Meta CAPI** sends `lead_event_source = 'Interior Design Lead Manager'`. That is outbound data, not presentation. | Do not change without the user. |

### Security summary

- **Capability and nonce:** the save requires `manage_options` and the `idlm_settings_save_action` nonce. Integration cases cover denial without the capability and with a bad nonce (`tests/integration/attribution-opt-in-http-cases.php`).
- **CSS injection:** none possible. Colours pass a strict `#RRGGBB` regex on save and again on read, and every token is built from that value or from fixed literals.
- **Text:** text fields are sanitised on input and escaped on output (`esc_html` and `esc_attr`). The `mailto:` link uses `esc_attr` on a `sanitize_email` value, which is acceptable; `esc_url('mailto:' …)` would be more idiomatic.
- **Uploads:** the plugin does not upload logos itself. It references Media Library attachments chosen through `wp.media`, checks `edit_post`, checks the MIME type (`image/png`, `image/jpeg`, `image/webp`; no SVG) and checks a 2 MB size limit. Output uses `esc_url`.

## Fixes on this branch

| Commit | Change |
| --- | --- |
| `f0676d3` | The workspace sidebar uses `--idlm-sidebar` (W1). |
| `bd46261` | New `--idlm-sidebar-accent` and `--idlm-on-accent`, emitted by `white_label_style()`; the sidebar focus colour wins; the brand letter uses the on-accent colour (W2–W4). Nothing new is stored, and the layer defines no white-label token (STD-CSS-4). |
| `552ffd9` | The sign-in panel uses the workspace title, the core login header is escaped, and the brand letter is multibyte-safe (W7–W9). |
| `50733c1` and later | `tests/white-label-contracts.php`: a sweep of 8 sidebar colours × 10 accent colours proves the 3:1 and 4.5:1 guarantees; the delivery and copy contracts; the favicon shortcode (W10). |

Browser check: `/crm-workspace?idlm_view=leads` as a sales rep at 1440 and 390 px, with a light brand (`#0E7C86` / `#F4D35E` / `#1F2937`) and a dark brand (`#1E1B4B` / `#3B2F80` / `#0B0A1F`). The sidebar paints the configured colour, sidebar focus and the active icon reach 3:1 or more, an in-page contrast scan of the sidebar, top bar and header finds no text below 4.5:1, there is no horizontal scroll, and there are no script errors. The fixture serves the sign-in page without stylesheets, so the sign-in colours were reviewed statically.

## Proposal (needs the user's decision)

1. **P1. Tighten colour validation (fixes W5, W6, W13).** Require the sidebar to reach **7:1** against white, so 78% nav text and the active fill stay at 4.5:1 or more. Require the primary to reach **4.5:1 against the canvas `#F5F3EF`** (about 5:1 against white), so link and tab text pass on tinted surfaces. Optionally require the accent to reach 3:1 against the sidebar, then drop the white fallback. This changes what a save accepts. Saved values keep rendering, but an administrator re-saving another tab with a failing colour would be blocked (the whole form posts). The safer variant validates only when a colour field changes, the same pattern used for assets.
2. **P2. Mirror the derived tokens in the settings preview (W14)** so the preview matches the live sidebar.
3. **P3. Brand the remaining product strings (W11, W12).** Use `brand_name` or `workspace_title` for email subject prefixes and the wp-admin menu label. Email subjects are a visible change that can break recipients' mail filters, so this is the user's call.
4. **P4. Cleanup:** delete the dead `:root` aliases (W15), and use `esc_url('mailto:' …)` for the support link.
