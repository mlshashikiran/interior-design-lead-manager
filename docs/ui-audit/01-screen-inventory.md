# Screen inventory

## Architecture

The CRM has two presentation surfaces sharing the same templates and assets: WordPress admin pages and authenticated shortcodes. `[idlm_workspace]` is the primary shell; the individual dashboard, leads, and Kanban shortcodes remain compatibility entry points. `IDLM_Admin` is a compatibility facade, while focused renderers prepare contexts for templates.

| Screen / workflow | Entry and presentation | Main interaction | Responsive evidence |
| --- | --- | --- | --- |
| Workspace shell | `class-admin-shortcodes.php`, `templates/workspace.php`, `workspace.js/css` | Role-aware sidebar, account menu, section navigation | Collapsible desktop sidebar; fixed mobile drawer at 782px |
| Dashboard | `class-admin-dashboard-renderer.php`, `templates/admin/dashboard.php`, `dashboard.js/css` | Role-scoped Sales/Marketing analytics, filters, drill-down records | KPI 6→3→2→1 grid; dashboard stacks at 1100/720/460px |
| Leads list | `class-admin-leads-renderer.php`, `templates/admin/leads.php`, `admin-app.js/css` | Search, filters, cursor paging, bulk actions, import/export, quick edits | Horizontal table plus priority/detail row disclosure |
| Lead detail | `class-admin-lead-reads.php`, `common-overlays.php`, `admin-app.js/css` | Shared lead drawer: details, activity, WhatsApp, tasks, quotes, pre-sales decisions | Wide work surface; forms collapse and drawer becomes full-width |
| Pipeline | `class-admin-kanban-renderer.php`, `templates/admin/kanban.php`, `kanban.js/css` | Stage filters, drag/drop, contextual stage transitions, shared lead drawer | Horizontally scrolling desktop board; mobile stage selector/move dialog |
| Quotations | `class-quotation-renderer.php`, `quotations.js/css` | Quote list, catalogue, editable sections/items, autosave, revisions, print | Item table turns into labelled cards at 782px |
| CRM settings | `class-admin-settings-renderer.php`, `templates/admin/settings/*`, `settings.js`, `admin-style.css` | Ten top-level tabs, nested MCP tabs, dynamic editors, save bar, diagnostics | Grid layouts collapse around 900/600px |
| Users and operational logs | `class-admin-users-controller.php`, `templates/admin/users.php`, log templates, `users-logs.js` | User create/edit, filter drawer, log payload dialog, retry operations | Details rows/forms reflow at 782px |
| Account and access | `my-account.php`, `access-restricted.php`, `access-account.js`, `login.css` | Login/reset, profile, password visibility/match feedback | Auth shell becomes one column; forms preserve autocomplete metadata |
| Global transient UI | `common-overlays.php`, `dialog.js`, `admin-app.js` | Confirmation/prompt dialogs, lead drawer, import/routing overlays, Today’s work sidebar, toasts | Dialog utility manages focus/scroll; individual overlays vary |

## States found

- Loading/error/empty: dashboard retry and chart states; lead drawer load/error; table and catalogue empty states; task and activity feedback.
- Notification: `window.IDLMDialog.notify()` plus server-rendered alerts/notices.
- Navigation: sidebar `aria-current`, account dropdown, settings/dashboard/drawer tabs, cursor and page-number pagination.
- Accessibility primitives: skip link, labelled password controls, `aria-sort`, tab semantics, dialog focus trap/inert background, reduced-motion queries.

## Coverage boundary

This is a source audit. It covers every repository-owned screen and declared responsive state. It does not certify theme interaction, real-user data density, browser/assistive-technology behavior, or print rendering because no authenticated running instance was supplied for manual exercise.
