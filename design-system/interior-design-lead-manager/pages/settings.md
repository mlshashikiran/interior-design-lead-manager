# Settings — Page Rules

Phase 1 foundation is implemented for affected field labels, control associations, focus treatment, accessible action colours, and shared semantic tokens.

Phase 4 debt reduction is implemented: the settings templates contain no inline presentation styles; primary and MCP tabs expose keyboard-operable tab semantics; diagnostics and reset confirmation use the shared dialog controller; and common spacing, table, status, progress, and callout patterns use shared contracts.

Phase 6 usability is implemented: navigation uses task-oriented labels; pipeline, call-status, Lost-reason, studio, and room vocabularies use labelled add/remove option lists; the active editable tab retains a sticky save-state region; and credentials expose consistent configuration, reveal, copy, and nearby connection-test feedback.

White-label settings are implemented as a goal-oriented tab after General. The layout, states, responsive behavior, scoped preview tokens, security boundary, and delivery record are defined in `docs/WHITE-LABEL-SETTINGS-PLAN.md`.

The White label editor and every branded CRM surface remain independent from the active site theme. Plugin roots own their complete typography, colour, spacing, control, and component contracts; white-label fields may change documented plugin tokens only.

- Tabs are ordered by user goal and grouped by a divider in one horizontal, sticky, self-scrolling bar (design layer only): setup (General, White label, Routing), connections (Lead capture, Conversion tracking, Automation, AI access), upkeep (Health, Help, Backup & restore), then Danger Zone alone at the far end in danger red. Group order is rendered in `shell.php` from one array; tab IDs and `switchSettingsTab` calls are unchanged. The page title is "Settings" in the design layer.
- Labels are 13px/600 in the text colour and always outrank help text (12px/400 muted). Field grids use auto-fit columns so no card has a half-empty row; token editors keep their own widths. General pairs Notifications with Firm metrics; the two vocabulary editors span the row.
- Settings confirmations use `IDLMDialog.confirm({...}).then(...)`. Never test the return value of `confirm` directly: it is a Promise, so a string argument would run the action without waiting (this was the case for Clear telemetry logs and Sync past; fixed by Shashi).
- The save bar has clean, dirty, saving, saved and error states; an invalid save focuses the first invalid field. The form reserves space below so the bar never covers the last field. Danger Zone and Backup hide it.
- Each card begins with the outcome it controls, then fields, help, current state, and an optional test action.
- Save is sticky only within the settings form and reports saved/unsaved/error state. It must not apply to Danger Zone.
- Configuration vocabularies use add/remove token lists and retain their established server-side option format.
- Credentials show configured/not configured state, reveal/copy controls, nearby test results where supported, and scope explanation.
- Advanced diagnostic content uses disclosure and is read-only by default.
- Danger Zone is isolated, states affected records, requires typed confirmation, and never uses brand-gold styling.
