<?php
if (!defined('ABSPATH')) {
    exit;
}

$can_assign_leads = !empty($context['can_assign_leads']);
?>
        <div class="wrap idlm-wrap">
            <header class="idlm-header">
                <div class="idlm-header-meta">
                    <h1>Lead Management Dashboard</h1>
                    <p class="idlm-header-subtitle">Performance Analytics and Conversion Pipelines</p>
                </div>
                <div class="idlm-header-actions">
                    <select id="idlm-period-selector" class="idlm-input">
                        <option value="today">Today</option>
                        <option value="yesterday">Yesterday</option>
                        <option value="this_week">This Week</option>
                        <option value="last_7_days">Last 7 Days</option>
                        <option value="this_month" selected>This Month</option>
                        <option value="last_30_days">Last 30 Days</option>
                        <option value="last_month">Last Month</option>
                        <option value="this_year">This Year</option>
                        <option value="custom">Custom Range</option>
                    </select>
                    <div id="idlm-custom-range-picker" style="display:none; align-items:center; gap: 5px;">
                        <input type="date" id="idlm-start-date" class="idlm-input" style="width:130px;">
                        <span>to</span>
                        <input type="date" id="idlm-end-date" class="idlm-input" style="width:130px;">
                        <button id="idlm-apply-custom" class="idlm-btn idlm-btn-primary">Apply</button>
                    </div>
                </div>
            </header>

            <!-- Dashboard Tabs -->
            <div class="idlm-tabs">
                <button class="idlm-tab-link active" onclick="switchDashboardTab(event, 'idlm-marketing-tab')">Marketing Section (Source Performance)</button>
                <button class="idlm-tab-link" onclick="switchDashboardTab(event, 'idlm-sales-tab')">Sales Section (Pipeline Performance)</button>
            </div>

            <p class="idlm-muted" style="margin: 8px 0 16px;">
                Metric scope: unless explicitly labeled as a Won event or calendar-month target, dashboard figures use non-duplicate leads created in the selected period and show their current CRM state and owner.
            </p>

            <!-- Targets Progress Area -->
            <div id="idlm-targets-container" class="idlm-card idlm-targets-card">
                <h3>Monthly Conversion Progress</h3>
                <div class="idlm-progress-wrapper">
                    <div class="idlm-progress-item">
                        <div class="idlm-progress-header">
                            <span>Leads Goal</span>
                            <strong id="lead-target-ratio">0/0</strong>
                        </div>
                        <div class="idlm-progress-bar-bg">
                            <div id="lead-target-bar" class="idlm-progress-bar" style="width: 0%;"></div>
                        </div>
                    </div>
                    <div class="idlm-progress-item">
                        <div class="idlm-progress-header">
                            <span>This Month's Won Goal</span>
                            <strong id="conv-target-ratio">0/0</strong>
                        </div>
                        <div class="idlm-progress-bar-bg">
                            <div id="conv-target-bar" class="idlm-progress-bar idlm-progress-bar-success" style="width: 0%;"></div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Marketing Tab -->
            <div id="idlm-marketing-tab" class="idlm-tab-content active">
                <div class="idlm-grid-stats">
                    <div class="idlm-stat-card">
                        <span class="idlm-stat-label">Unique Leads (Period)</span>
                        <div class="idlm-stat-val-wrapper">
                            <span id="mkt-total-leads" class="idlm-stat-val">0</span>
                            <span id="mkt-growth-pct" class="idlm-stat-trend">0%</span>
                        </div>
                    </div>
                    <div class="idlm-stat-card">
                        <span class="idlm-stat-label">Unique Leads (Previous Period)</span>
                        <span id="mkt-prev-leads" class="idlm-stat-val-secondary">0</span>
                    </div>
                    <div class="idlm-stat-card">
                        <span class="idlm-stat-label">Top Channel</span>
                        <span id="mkt-top-channel" class="idlm-stat-val-secondary">N/A</span>
                    </div>
                    <div class="idlm-stat-card">
                        <span class="idlm-stat-label">Duplicate Leads (Period)</span>
                        <div class="idlm-stat-val-wrapper">
                            <span id="mkt-total-duplicates" class="idlm-stat-val">0</span>
                            <span id="mkt-duplicates-growth-pct" class="idlm-stat-trend">0%</span>
                        </div>
                    </div>
                </div>

                <div class="idlm-card idlm-chart-card" style="margin-top: 25px;">
                    <div class="idlm-chart-card-header">
                        <h3>Leads Over Time</h3>
                    </div>
                    <div class="idlm-chart-container" style="height: 320px;">
                        <canvas id="chart-leads-over-time"></canvas>
                    </div>
                </div>

                <div class="idlm-card idlm-chart-card" style="margin-top: 25px;">
                    <div class="idlm-chart-card-header">
                        <h3>Lead Quality by Channel</h3>
                    </div>
                    <div class="idlm-chart-container">
                        <canvas id="chart-leads-by-channel"></canvas>
                    </div>
                </div>

                <div class="idlm-grid-charts" style="margin-top: 25px;">
                    <div class="idlm-card idlm-chart-card">
                        <div class="idlm-chart-card-header">
                            <h3>Leads by Property Type</h3>
                        </div>
                        <div class="idlm-chart-container">
                            <canvas id="chart-property-types"></canvas>
                        </div>
                    </div>
                    <div class="idlm-card idlm-chart-card">
                        <div class="idlm-chart-card-header">
                            <h3>Leads by Room Configuration (BHK)</h3>
                        </div>
                        <div class="idlm-chart-container">
                            <canvas id="chart-room-configs"></canvas>
                        </div>
                    </div>
                </div>

                <div class="idlm-grid-charts" style="grid-template-columns: 1fr; margin-top: 25px;">
                    <div class="idlm-card idlm-chart-card">
                        <div class="idlm-chart-card-header idlm-chart-card-toolbar">
                            <h3 id="idlm-utm-chart-title">Top UTM Campaigns</h3>
                            <select id="idlm-utm-type-selector" class="idlm-input" style="width: 180px;">
                                <option value="utm_campaign" selected>UTM Campaign</option>
                                <option value="utm_source">UTM Source</option>
                                <option value="utm_medium">UTM Medium</option>
                                <option value="utm_adgroup">UTM Adgroup</option>
                                <option value="utm_term">UTM Term</option>
                                <option value="utm_content">UTM Content</option>
                            </select>
                        </div>
                        <div class="idlm-chart-container">
                            <canvas id="chart-utm-campaigns"></canvas>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Sales Tab -->
            <div id="idlm-sales-tab" class="idlm-tab-content">
                <div class="idlm-grid-stats">
                    <div class="idlm-stat-card">
                        <span id="sales-total-won-label" class="idlm-stat-label">Total Won — This Month</span>
                        <span id="sales-total-won" class="idlm-stat-val">0</span>
                    </div>
                    <div class="idlm-stat-card">
                        <span class="idlm-stat-label">Avg Speed to Connected — Acquisition Cohort</span>
                        <span id="sales-avg-connected" class="idlm-stat-val-secondary">N/A</span>
                    </div>
                    <div class="idlm-stat-card">
                        <span class="idlm-stat-label">Avg Connected to Won — Acquisition Cohort</span>
                        <span id="sales-avg-won" class="idlm-stat-val-secondary">N/A</span>
                    </div>
                    <div class="idlm-stat-card">
                        <span class="idlm-stat-label">Estimated Open Pipeline</span>
                        <span id="sales-estimated-pipeline" class="idlm-stat-val-secondary">0</span>
                    </div>
                    <div class="idlm-stat-card">
                        <span class="idlm-stat-label">Won Revenue</span>
                        <span id="sales-won-revenue" class="idlm-stat-val-secondary">0</span>
                    </div>
                    <div class="idlm-stat-card">
                        <span class="idlm-stat-label">Plan Collection Rate — Acquisition Cohort</span>
                        <span id="sales-plan-collection-rate" class="idlm-stat-val-secondary">0%</span>
                        <small id="sales-plan-collection-detail" class="idlm-muted">0 of 0 qualified leads</small>
                    </div>
                </div>

                <?php if ($can_assign_leads): ?>
                <div class="idlm-card idlm-chart-card" style="margin-bottom: 25px;">
                    <div class="idlm-chart-card-header">
                        <h3>Qualified Sales Assignments by Representative</h3>
                        <p class="idlm-chart-card-subtitle">Qualified leads assigned to their current sales representative during the selected period, using sales handoff date when available and current assignment date otherwise.</p>
                    </div>
                    <div class="idlm-chart-container" style="height: 340px;">
                        <canvas id="chart-sales-assignments"></canvas>
                        <div id="chart-sales-assignments-empty" class="idlm-chart-empty" style="display:none;">No qualified sales assignments match the selected period.</div>
                    </div>
                </div>
                <?php endif; ?>

                <div class="idlm-card idlm-chart-card" style="margin-bottom: 25px;">
                    <div class="idlm-chart-card-header">
                        <h3>Current Sales Stage Distribution</h3>
                        <p class="idlm-chart-card-subtitle">
                            See where leads acquired in the selected period currently sit in the configured sales lifecycle.
                        </p>
                    </div>
                    <div class="idlm-funnel-wrapper" id="sales-funnel-container">
                        <!-- Populated dynamically via JS -->
                    </div>
                </div>

                <div class="idlm-grid-charts">
                    <div class="idlm-card idlm-chart-card">
                        <div class="idlm-chart-card-header">
                            <h3>Revenue Estimate by Channel</h3>
                            <p class="idlm-chart-card-subtitle">
                                Open-pipeline estimates and Won revenue split by source channel.
                            </p>
                        </div>
                        <div class="idlm-chart-container idlm-revenue-chart-container">
                            <canvas id="chart-revenue-by-channel"></canvas>
                            <div id="chart-revenue-by-channel-empty" class="idlm-chart-empty" style="display:none;">No quote or deal values are available for the selected period.</div>
                        </div>
                    </div>
                    <div class="idlm-card idlm-chart-card">
                        <div class="idlm-chart-card-header">
                            <h3>Open Pipeline vs Won Revenue</h3>
                            <p class="idlm-chart-card-subtitle">
                                Open value from leads created in the selected period versus revenue won during that period.
                            </p>
                        </div>
                        <div class="idlm-chart-container idlm-revenue-chart-container">
                            <canvas id="chart-revenue-summary"></canvas>
                            <div id="chart-revenue-summary-empty" class="idlm-chart-empty" style="display:none;">No quote or deal values are available for the selected period.</div>
                        </div>
                    </div>
                </div>

                <div class="idlm-grid-charts">
                    <div class="idlm-card idlm-chart-card">
                        <div class="idlm-chart-card-header">
                            <h3>Current Stage Distribution — Acquisition Cohort</h3>
                        </div>
                        <div class="idlm-chart-container">
                            <canvas id="chart-pipeline-stages"></canvas>
                        </div>
                    </div>
                    <div class="idlm-card idlm-chart-card">
                        <div class="idlm-chart-card-header">
                            <h3>Current Call Status — Acquisition Cohort</h3>
                        </div>
                        <div class="idlm-chart-container">
                            <canvas id="chart-call-status-dist"></canvas>
                        </div>
                    </div>
                </div>

                <div class="idlm-grid-charts" style="margin-top: 25px;">
                    <div class="idlm-card idlm-chart-card">
                        <div class="idlm-chart-card-header">
                            <h3>Qualification Readiness</h3>
                            <p class="idlm-chart-card-subtitle">Qualified leads are split by floor-plan availability; both remain Qualified in conversion reporting.</p>
                        </div>
                        <div class="idlm-chart-container">
                            <canvas id="chart-overall-quality"></canvas>
                        </div>
                    </div>
                    <div class="idlm-card idlm-chart-card">
                        <div class="idlm-chart-card-header">
                            <h3>Preferred Studio Distribution</h3>
                        </div>
                        <div class="idlm-chart-container">
                            <canvas id="chart-studio-dist"></canvas>
                        </div>
                    </div>
                </div>

                <div class="idlm-card idlm-chart-card" style="margin-top: 25px;">
                    <div class="idlm-chart-card-header">
                        <h3>Lost Leads by Reason</h3>
                        <p class="idlm-chart-card-subtitle">
                            Categorized reasons for Lost leads created in the selected period, including their associated quote value.
                        </p>
                    </div>
                    <div class="idlm-chart-container" style="height: 360px;">
                        <canvas id="chart-lost-reasons"></canvas>
                        <div id="chart-lost-reasons-empty" class="idlm-chart-empty" style="display:none;">No Lost leads are available for the selected period.</div>
                    </div>
                </div>

                <div class="idlm-card" style="margin-top: 25px;">
                    <h3>Studio Acquisition Cohort Performance</h3>
                    <p class="idlm-chart-card-subtitle">Leads created in the selected period, grouped by their current preferred studio.</p>
                    <div class="idlm-table-wrapper">
                        <table class="idlm-table" id="studio-performance-table">
                            <thead>
                                <tr>
                                    <th>Studio Branch</th>
                                    <th>Leads Acquired</th>
                                    <th>Currently Won</th>
                                    <th>Cohort Win Rate (%)</th>
                                </tr>
                            </thead>
                            <tbody>
                                <!-- Populated dynamically -->
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="idlm-card" style="margin-top: 25px;">
                    <h3>Pre-Sales Intake Cohort Performance</h3>
                    <p class="idlm-chart-card-subtitle">Non-duplicate intake leads created in the selected period, permanently attributed to their original intake owner.</p>
                    <div class="idlm-table-wrapper">
                        <table class="idlm-table" id="pre-sales-performance-table">
                            <thead><tr><th>Pre-Sales User</th><th>Intake Leads</th><th>Outreach Logged</th><th>Ever Qualified</th><th>Currently Unqualified</th><th>Completed Handoffs</th><th>Ever-Qualified Rate</th><th>Avg Calendar Time to Qualify</th></tr></thead>
                            <tbody></tbody>
                        </table>
                    </div>
                </div>

                <div class="idlm-card" style="margin-top: 25px;">
                    <h3>Current Sales Ownership Cohort Performance</h3>
                    <p class="idlm-chart-card-subtitle">Leads created in the selected period, grouped by their current assigned representative. Reassignment intentionally moves the complete cohort row.</p>
                    <div class="idlm-table-wrapper">
                        <table class="idlm-table" id="sales-rep-table">
                            <thead>
                                <tr>
                                    <th>Rep Name</th>
                                    <th>Currently Assigned</th>
                                    <th>Successfully Connected</th>
                                    <th>Avg Working Time to Connect</th>
                                    <th>Quality: Qualified</th>
                                    <th>Quality: Unqualified</th>
                                    <th>Currently Won</th>
                                    <th>Avg Working Time Connected to Won</th>
                                    <th>Cohort Win Rate (%)</th>
                                </tr>
                            </thead>
                            <tbody>
                                <!-- Populated dynamically -->
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

