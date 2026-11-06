# Dashboard — Page Rules

Phase 2 core workflow is implemented: Marketing/Sales use persistent keyboard-operable ARIA tabs, reporting periods are labelled and announced, and every canvas chart exposes an equivalent toggled data table.

- Default summary answers: volume, response, qualification, pipeline, Won, and exceptions. Separate marketing acquisition from sales execution with accessible tabs.
- Period selector has a visible label; custom dates are grouped and validated before updating.
- KPI cards name their time/cohort scope and show comparison direction with text plus icon, not colour alone.
- Use line charts for time, ranked horizontal bars for channels/reasons/reps, and a labelled funnel for lifecycle conversion.
- Every chart has loading, empty, and error states plus a toggled accessible data table.
- Limit the first dashboard view to decision-critical charts; move diagnostic distributions behind “More analysis” disclosure to reduce cognitive load.
- Two-column charts collapse at `1024px`; compact charts become one column below `782px`.

## CRM design layer decisions (Shashi)

- First screen order is Act next, KPI cards, open pipeline beside the trend, then rep table. Diagnostic panels (channel mix, handoff, lost reasons, activity, business mix, campaign explorer, attribution health) sit in one "More analysis" disclosure per tab.
- Act next is a full-width row of queue buttons; each opens the matching filtered leads below. All-zero shows "Nothing needs action right now."
- KPI cards share one height; the comparison text is pinned to the bottom. Green/red text is only for a real previous baseline; otherwise it reads "No previous baseline" in muted ink.
- Dates read "1 Nov – 5 Nov 2026" in the period text and "5 Nov" on chart ticks. Trend charts keep a "Show as table" alternative.
- Chart colours come from tokens: leads and open use action, qualified uses success, Won uses info, closed uses muted text. Qualified differs by dash as well as colour.
- Loading shows KPI skeletons on first load and dims panels on refresh; errors use the shared danger alert and keep the last good figures.
