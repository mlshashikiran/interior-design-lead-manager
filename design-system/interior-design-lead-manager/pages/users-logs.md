# Users and Logs — Page Rules

Phase 7 is implemented. Users & Access and both operational log views now follow the responsive, semantic, feedback, and shared-dialog rules below; administrator capabilities, action-specific user nonces, log-read nonce checks, and retry behavior remain unchanged.

- Users directory keeps invite and directory tasks distinct. Search/filter controls wrap cleanly on phones rather than relying on page-level horizontal scrolling.
- A user row exposes name, email, role, access state, and Edit disclosure. Suspended state includes text and is never colour-only.
- Prevent self-lockout and last-administrator changes in both UI copy and server policy.
- Log pages default to event, object/lead, status, time, and concise result. Large payloads load on demand in a labelled, focus-managed detail dialog.
- Logs provide date/status/search filters, copyable request identifiers, wrap long tokens safely, and distinguish empty results from loading or permission errors.

## Design-layer decisions (Shashi, 2026-11-05)

- Users & Access order: header with the one primary action (Invite team member) → "Showing N of T people" → directory with an always-visible search/role/access toolbar → folded setup (Invite, Role lead visibility, Role permissions). Setup folds are `details`; Invite opens from the header button and when invite validation fails.
- Directory rows are one line: name, "You" tag, role, Active/Suspended badge (text first), Edit. Suspended rows are dimmed. A pager appears only when the unfiltered total exceeds one page.
- Suspending someone asks for confirmation through `IDLMDialog.confirm`; the server safeguards are unchanged.
- Logs: status tabs (All / Failed / Succeeded, server filter links) → counts from the rows on the page only, labelled "Latest 50 matching entries" → table. Failed rows are tinted, phone cards lead with status. "Select failed" selects rows only; retry now confirms with the count and warns that events may be counted twice, then toasts the result.
- Monospace is for tokens, event names, audience IDs and payloads only.
- The wp-admin Identity reviews page does not load the design layer (no body class, no stylesheet), so it keeps WordPress styling.

## Team and Roles & permissions (Shashi, 2026-11-05)

- Daily people work and rarely changed role setup are peer views: Team (`?idlm_view=users`) and Roles & permissions (`&users_tab=roles`). The view is read-only navigation and never affects saved values.
- `idlm_update_role_scopes` resets any role missing from the post, and `idlm_update_role_permissions` removes any capability not ticked. Any form that posts to them must render every scope select for all roles and every permission checkbox for the role. The old design still does.
- Ticks change nothing until Save, unsaved changes are stated beside Save, and leaving with unsaved ticks asks first.

## Access flow (Shashi, 2026-11-05)

- Updating the plugin never changes access. Roles, capabilities, user meta and lead visibility change only on an administrator's save. Nothing on activation, upgrade or page load calls the new write code, and a contract test checks that. No migration: stale grants on old accounts are shown, not removed.
- Team, Roles & permissions and Activity are peer views. `&users_tab=roles` lists roles; `&role_edit=<slug>` opens one editor with one Save (`idlm_update_role_access`). That handler changes only its own role's key in `idlm_role_lead_scopes`. Administrator is read-only and website roles are scope only.
- Job-role names are display only and come from `IDLM_Admin_Users_Controller::role_label()`. Stored slugs never change, and the old design shows stored names.
- Pick lists show Administrator last. Website roles stay out unless the person holds one, and "Show website roles" adds them from a `template`. Filters keep every role, so anyone can be found.
- A person with no managed role gets a required "Choose a role" instead of silently taking the first option.
- Never say "Use CRM default". State the real outcome through `default_scope_outcome()`, and link the setting when the outcome depends on it. "Reps see all leads" sits on Settings > General.
- Say how many people a save affects before saving. Warnings about permissions that need another never block the save. Removing Access CRM leads from a role with people asks through `IDLMDialog.confirm`, naming the role and count.
- The access summary in a person's disclosure is read-only and comes from `IDLM_Lead_Access_Policy::describe_user_access()`, which must keep matching the live policy. A test runs both across every branch.
- Suspending never reassigns leads, because assignment has lifecycle and outbound effects. The row, the inline note and the confirmation give the open count (not Won or Lost, not duplicates), and the note links to `?idlm_view=leads&rep=<id>`. That link is a read-only filter preset, and the server still applies the viewer's scope. Administrators can see duplicate rows on Leads, so its total can be higher than the count.
- The invite shows no username unless "Set a username" is opened. An empty username comes from the email's local part. A rejected invite restores what was typed from `sessionStorage` once, then clears it.
- Activity is read-only and reads `idlm_access_change_log`. See `docs/DATA-MODEL.md`.
