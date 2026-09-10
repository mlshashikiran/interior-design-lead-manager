# CRM Dashboard Analytics Audit

**Prepared by:** Shashi  
**Audit date:** 6 September 2026  
**Evidence:** Dashboard implementation review, local testing UI, and aggregate-only reads from the live CRM.

## Purpose and scope

This audit evaluates the Lead Management Dashboard as an operating surface for CRM, marketing, pre-sales, and sales management. It covers metric truthfulness, data quality, layout, interaction design, and implementation priorities. No production data or configuration was changed.

## Executive verdict

The dashboard has a good visual foundation, useful date controls, accessible chart data tables, and clear Marketing/Sales separation. It is not yet a dependable management cockpit because it mixes three different measurement grains:

1. **Acquisition cohort:** leads created in the selected period, shown with their current state.
2. **Current snapshot:** leads currently in a pipeline stage.
3. **Conversion event:** leads marked Won during the selected period.

Those populations cannot safely be compared or summed. The dashboard should become action-first: show operational risk and next actions above descriptive charts.

## Dashboard display policy

Keep metric-grain, date-anchor, denominator, event-coverage, and attribution caveats in this internal audit and implementation documentation. The CRM interface should use concise, action-oriented labels and meaningful empty states rather than exposing internal measurement methodology to end users.

## Live CRM snapshot

The following results are aggregate-only live CRM reads performed on 6 September 2026 at 23:05 local CRM time.

| Measure | Live result | Interpretation |
|---|---:|---|
| Canonical leads | 1,174 | Live CRM is active; the local test dataset is not representative. |
| New leads, last 7 days | 113 | Intake is active and daily monitoring is worthwhile. |
| New leads, last 30 days | 428 | A 30-day operating view is meaningful. |
| September MTD new leads | 94 | 18.8% of the 500 monthly lead target. |
| September MTD Won events | 4 | 20% of the 20 monthly Won target. |
| September-acquired leads currently Won | 2 | Different from Won events; do not combine these metrics. |
| Pending tasks | 313 | Material queue requiring manager attention. |
| Overdue tasks | 209 | 66.8% of pending tasks are overdue. |
| Current Lost leads | 736 | 62.7% of canonical leads; loss analysis must be a first-class diagnostic. |
| Current open leads | 408 | Requires pipeline-ageing and owner accountability views. |

### Live data quality and operational risks

| Finding | Evidence | Dashboard implication |
|---|---:|---|
| Missing intake ownership | 760 canonical leads, 64.7% | Pre-Sales Intake Cohort Performance is incomplete and can misattribute performance. |
| Missing floor plans on qualified leads | 302 of 470 qualified leads, 64.3% | Plan Collection Rate is a meaningful operational guardrail, but needs an SLA and drill-through. |
| Incomplete attribution | 205 leads lack campaign; 235 lack UTM source/medium | Show attribution coverage next to channel/UTM analysis. |
| Response-time coverage gap | 292 canonical leads have no connection time; 13 Won leads have no speed-to-Won | Average timing metrics are incomplete. Use coverage, median, p90, and SLA attainment. |
| Unstructured loss reasons | 458 of 736 Lost leads are `Other`; 205 are `Unable to Contact`; 54 are uncategorized | Current loss-reason analysis cannot reliably identify fixes. |
| Timestamp validity | No Won, qualification, or handoff timestamps precede lead creation | This portion of lifecycle data is trustworthy. |

## Metric-truthfulness issues

### P0: current-stage visuals are mixed-grain

`leads_by_stage` and the Sales Funnel query non-Won stages by `created_at`, but Won by `conversion_time`. The widgets are labeled as acquisition-cohort/current-stage views, yet the Won portion is a conversion-event population.

**Required change:** split this into two clearly named measures:

- **Current open pipeline snapshot:** all leads currently in each open stage, independently of acquisition date.
- **Won this period:** conversion events whose conversion timestamp falls in the selected period.

Do not place Won in the current open-pipeline funnel.

### P0: pipeline versus Won revenue is not a valid comparison

Open-pipeline value is calculated from leads created during the selected period and currently not Won/Lost. Won revenue is calculated from leads converted during the selected period. The bars use different denominators and time anchors.

**Required change:** show them as independent cards/sections, or label both population and date logic immediately next to each value. A period event chart should compare Won revenue with prior-period Won revenue, not with cohort pipeline.

### P1: target context is wrong on historical views

Monthly targets always use the current calendar month, even when a user selects a past/custom range. The current implementation correctly documents this behavior, but it is confusing and dominates the page.

**Required change:** show the target card only on the default MTD view, or make it period-aware for whole calendar months. Add expected pace: as of 6 September, 94 leads are 6 leads behind a straight-line monthly pace of 100; Won events are on pace at 4 of an expected 4.

### P1: duplicate trend semantics are inverted

Positive duplicate-growth is styled with the same positive/green cue used for lead growth. An increase in duplicate leads is a negative quality outcome.

**Required change:** report duplicate rate and direction; increase is amber/red, reduction is green.

## Recommended dashboard structure

### 1. Management overview: action required

Place these above all charts, with click-through to filtered Leads/Tasks views:

- Overdue tasks: 209
- Pending tasks: 313
- Leads with no intake owner: 760
- Qualified leads missing floor plan: 302
- Open leads aged 61+ days: 38
- Attribution-incomplete leads: 235

### 2. Acquisition and marketing

Use a selected acquisition-date cohort.

- Leads acquired and prior comparable period.
- Qualified-lead rate.
- Connected-within-SLA rate and median/p90 speed to first contact.
- Channel scorecard: leads, qualified rate, cohort win rate, sample size, attribution coverage.
- Lead-volume trend, daily only for short ranges and weekly for ranges over 45 days.

Live channel evidence supports a rate-based scorecard rather than a `Top Channel` card:

| Channel | Leads | Qualified rate | Current win rate |
|---|---:|---:|---:|
| Meta Ads | 919 | 39.5% | 1.4% |
| Website | 151 | 44.4% | 3.3% |
| Google Ads | 77 | 19.5% | 9.1% |
| Offline / Walk-in | 25 | 92.0% | 20.0% |

Rates should always carry their denominators. They are descriptive cohort results, not causal profitability estimates.

### 3. Pipeline operations

Use a current-state snapshot.

- Open leads by current stage.
- Open-pipeline ageing: 0–7, 8–30, 31–60, and 61+ days.
- Leads with no next task / overdue task.
- Ownership and handoff completeness.
- Floor-plan collection backlog.

Live ageing profile: 90 open leads are 0–7 days old, 166 are 8–30 days, 114 are 31–60 days, and 38 are 61+ days old.

### 4. Sales outcomes

Use event dates.

- Won deals and Won revenue in the selected period, with prior-period comparison.
- Win rate among closed leads, separately from acquisition-cohort maturation.
- Lost reasons with reason completeness.
- Time from connection to Won, including calculation coverage.

## Layout and interaction changes

### Keep

- Marketing and Sales tabs.
- Reporting-period and custom-range controls.
- Keyboard-accessible tabs.
- Accessible data tables behind charts.

### Change

- Make each headline card clickable to the relevant filtered Leads or Tasks queue.
- Expose the user’s active visibility scope and latest refresh timestamp.
- Replace zero-filled charts with explicit empty states that state whether there were no records or data is unavailable.
- Aggregate line charts weekly for ranges longer than 45 days; the existing daily 90-day view is too dense.
- Use no more than five hero cards per tab; move low-action segmentation to lower sections.
- Add tooltips/inline definitions stating population, date anchor, denominator, and exclusions.

### Move or remove from the primary surface

- Keep only one stage-distribution visual; the funnel and Current Stage Distribution repeat the same underlying concept.
- Move Property Type, BHK, Preferred Studio Distribution, and UTM detail below the operational/readout layer.
- Replace `Top Channel` with the channel scorecard.
- Keep UTM analysis, but show attribution completeness and suppress empty UTM groupings.

## Implementation sequence

1. Correct metric grain and labels for stage/funnel, Won, and revenue widgets.
2. Add the action-required row and lead/task drill-through links.
3. Add data freshness, attribution completeness, ownership completeness, and response-time coverage guardrails.
4. Add pipeline ageing and task/SLA operational views.
5. Normalize/enforce loss reasons; reduce use of `Other`.
6. Make monthly targets pace-aware and restrict them to an appropriate time context.
7. Rework marketing scorecards to show quality and denominator, not just volume.

## Relevant implementation paths

- `includes/class-dashboard.php` — aggregate definitions, date windows, targets, and metric grain.
- `templates/admin/dashboard.php` — dashboard hierarchy, labels, tabs, and cards.
- `assets/js/dashboard.js` — chart rendering, interactions, drill-through behavior, and accessible tables.
- `assets/css/dashboard.css` — responsive layout and visual semantics.
- `includes/class-admin-ajax.php` — dashboard AJAX entry point.
