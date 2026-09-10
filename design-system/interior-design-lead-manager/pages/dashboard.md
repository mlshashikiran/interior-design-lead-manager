# Dashboard — Page Rules

Phase 2 core workflow is implemented: Marketing/Sales use persistent keyboard-operable ARIA tabs, reporting periods are labelled and announced, and every canvas chart exposes an equivalent toggled data table.

- Default summary answers: volume, response, qualification, pipeline, Won, and exceptions. Separate marketing acquisition from sales execution with accessible tabs.
- Period selector has a visible label; custom dates are grouped and validated before updating.
- KPI cards name their time/cohort scope and show comparison direction with text plus icon, not colour alone.
- Use line charts for time, ranked horizontal bars for channels/reasons/reps, and a labelled funnel for lifecycle conversion.
- Every chart has loading, empty, and error states plus a toggled accessible data table.
- Limit the first dashboard view to decision-critical charts; move diagnostic distributions behind “More analysis” disclosure to reduce cognitive load.
- Two-column charts collapse at `1024px`; compact charts become one column below `782px`.
