# Settings — Page Rules

Phase 1 foundation is implemented for affected field labels, control associations, focus treatment, accessible action colours, and shared semantic tokens.

Phase 4 debt reduction is implemented: the settings templates contain no inline presentation styles; primary and MCP tabs expose keyboard-operable tab semantics; diagnostics and reset confirmation use the shared dialog controller; and common spacing, table, status, progress, and callout patterns use shared contracts.

Phase 6 usability is implemented: navigation uses task-oriented labels; pipeline, call-status, Lost-reason, studio, and room vocabularies use labelled add/remove option lists; the active editable tab retains a sticky save-state region; and credentials expose consistent configuration, reveal, copy, and nearby connection-test feedback.

White-label settings are implemented as a goal-oriented tab after General. The layout, states, responsive behavior, scoped preview tokens, security boundary, and delivery record are defined in `docs/WHITE-LABEL-SETTINGS-PLAN.md`.

The White label editor and every branded CRM surface remain independent from the active site theme. Plugin roots own their complete typography, colour, spacing, control, and component contracts; white-label fields may change documented plugin tokens only.

- Tabs are ordered by user goal: General, White label, Routing, Lead capture, Conversion tracking, Automation, Health, Help, AI access, Danger Zone.
- Each card begins with the outcome it controls, then fields, help, current state, and an optional test action.
- Save is sticky only within the settings form and reports saved/unsaved/error state. It must not apply to Danger Zone.
- Configuration vocabularies use add/remove token lists and retain their established server-side option format.
- Credentials show configured/not configured state, reveal/copy controls, nearby test results where supported, and scope explanation.
- Advanced diagnostic content uses disclosure and is read-only by default.
- Danger Zone is isolated, states affected records, requires typed confirmation, and never uses brand-gold styling.
