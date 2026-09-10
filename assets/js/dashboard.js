/* Role-scoped dashboard contexts and record investigations. Builder: Shashi. */
jQuery(function($) {
    'use strict';
    var $root = $('.idlm-dashboard');
    if (!$root.length) return;
    var request, recordRequest, chart, salesChart, pipelineChannelChart, stats, marketingTrend = 'all', disqualificationView = 'overall';
    var applied = { period: 'this_month', start_date: '', end_date: '' };
    var investigation = null;
    var returnFocus = null;
    var sequence = 0;
    var recordSequence = 0;
    var tab = $root.find('[role="tab"][aria-selected="true"]').data('dashboard-tab');
    function filterValues() { return { rep_id: $('#idlm-dashboard-rep').val() || '', studio: $('#idlm-dashboard-studio').val() || '', source: $('#idlm-dashboard-source').val() || '', campaign: $('#idlm-dashboard-campaign').val() || '' }; }
    function updateResetState() {
        var hasChanges = $('#idlm-period-selector').val() !== 'this_month' || !!$('#idlm-start-date').val() || !!$('#idlm-end-date').val() || Object.keys(filterValues()).some(function(key) { return !!filterValues()[key]; });
        $('#idlm-dashboard-reset').toggleClass('is-active', hasChanges).prop('disabled', !hasChanges).attr('aria-disabled', hasChanges ? 'false' : 'true');
    }
    function syncFilterVisibility() { $('.idlm-sales-filter').prop('hidden', tab !== 'sales'); $('.idlm-marketing-filter').prop('hidden', tab !== 'marketing'); }
    function text(value) { return $('<span>').text(value == null ? '' : String(value)).html().replace(/"/g, '&quot;').replace(/'/g, '&#39;'); }
    function num(value) { return Number(value) || 0; }
    function count(value) { return num(value).toLocaleString(); }
    function rate(part, total) { return num(total) ? (num(part) / num(total) * 100).toFixed(1) + '%' : '—'; }
    function dashboardToken(name, fallback) { return getComputedStyle($root[0]).getPropertyValue(name).trim() || fallback; }
    function money(value, symbol) { return (symbol || '') + num(value).toLocaleString(undefined, { maximumFractionDigits: 0 }); }
    function summary(label, value, note) { return '<article><span>' + text(label) + '</span><strong>' + text(value) + '</strong><small>' + text(note) + '</small></article>'; }
    function kpi(label, value, note, kind, title) { return '<button type="button" class="idlm-dashboard-kpi" data-investigate="' + text(kind) + '" data-title="' + text(title || label) + '"' + (kind ? '' : ' disabled') + '><span class="idlm-dashboard-kpi-label">' + text(label) + '</span><strong class="idlm-dashboard-kpi-value">' + text(value) + '</strong><small class="idlm-dashboard-kpi-meta">' + text(note) + '</small></button>'; }
    function empty(cols, message) { return '<tr><td colspan="' + cols + '"><p class="idlm-dashboard-empty">' + text(message) + '</p></td></tr>'; }
    function link(kind, value, label) { return '<button type="button" class="idlm-dashboard-link" data-investigate="' + text(kind) + '" data-value="' + text(value) + '" data-title="' + text(label) + '">' + text(label) + '</button>'; }
    function queue(kind, value, label, note, critical) { return '<button type="button" class="idlm-dashboard-action' + (critical ? ' is-critical' : '') + '" data-investigate="' + kind + '" data-title="' + text(label) + '"' + (num(value) ? '' : ' disabled') + '><strong>' + count(value) + '</strong><span><b>' + text(label) + '</b><small>' + text(note) + '</small></span><i aria-hidden="true">›</i></button>'; }
    function closeRecords(restore) {
        ++recordSequence;
        if (recordRequest) recordRequest.abort();
        $('#idlm-dashboard-records').prop('hidden', true);
        investigation = null;
        if (restore && returnFocus && document.contains(returnFocus)) returnFocus.focus();
    }
    function activate(next, focus) {
        var $tabs = $root.find('[role="tab"]');
        var $selected = $tabs.filter('[data-dashboard-tab="' + next + '"]');
        if (!$selected.length) return;
        tab = next;
        closeRecords(false);
        $tabs.removeClass('active').attr({ 'aria-selected': 'false', tabindex: -1 });
        $selected.addClass('active').attr({ 'aria-selected': 'true', tabindex: 0 });
        $root.find('[role="tabpanel"]').prop('hidden', true);
        $('#idlm-' + next + '-tab').prop('hidden', false);
        if (focus) $selected.trigger('focus');
        if (chart) chart.resize();
        if (salesChart) salesChart.resize();
        if (pipelineChannelChart) pipelineChannelChart.resize();
        syncFilterVisibility();
    }
    $root.on('click', '[data-dashboard-tab]', function() { activate($(this).data('dashboard-tab'), false); });
    $root.on('keydown', '[data-dashboard-tab]', function(e) {
        var $tabs = $root.find('[role="tab"]'), index = $tabs.index(this);
        if (e.key === 'ArrowRight') index = (index + 1) % $tabs.length;
        else if (e.key === 'ArrowLeft') index = (index + $tabs.length - 1) % $tabs.length;
        else if (e.key === 'Home') index = 0;
        else if (e.key === 'End') index = $tabs.length - 1;
        else return;
        e.preventDefault(); activate($tabs.eq(index).data('dashboard-tab'), true);
    });
    function load(options) {
        options = $.extend(filterValues(), options || {});
        var token = ++sequence;
        if (request) request.abort();
        closeRecords(false);
        $('#idlm-dashboard-error').prop('hidden', true);
        $root.find('[role="tabpanel"]').attr('aria-busy', 'true').find('button').prop('disabled', true);
        $('#idlm-dashboard-range-status').text('Updating dashboard…');
        request = $.ajax({ url: idlm_vars.ajax_url, type: 'POST', data: $.extend({ action: 'idlm_get_dashboard_stats', nonce: idlm_vars.nonce }, options) })
            .done(function(response) {
                if (token !== sequence) return;
                if (!response.success) { fail(response.data && response.data.message); return; }
                applied = $.extend({}, options);
                stats = response.data;
                render(stats);
                // Loading temporarily disables every panel button. Restore only
                // persistent selector controls; data rows render their own
                // intentionally-disabled empty/zero states.
                $root.find('[data-sales-trend], [data-marketing-trend], [data-disqualification-view], [data-segment]').prop('disabled', false);
                updateResetState();
                var ranges = stats.ranges || {};
                $('#idlm-dashboard-range-status').text('Period: ' + (ranges.curr_start || '').slice(0, 10) + ' – ' + (ranges.curr_end || '').slice(0, 10) + '.');
            }).fail(function(xhr, status) { if (status !== 'abort' && token === sequence) fail(); })
            .always(function() { if (token === sequence) $root.find('[role="tabpanel"]').removeAttr('aria-busy'); });
    }
    function fail(message) {
        $('#idlm-dashboard-error').prop('hidden', false).find('span').text(message || 'Dashboard could not be loaded. Try again.');
        $('#idlm-dashboard-range-status').text(stats ? 'The report below is from the last successful load. Investigations are paused until refresh succeeds.' : 'No report loaded.');
    }
    $('#idlm-period-selector').on('change', function() {
        var custom = this.value === 'custom';
        $('#idlm-custom-range-picker').prop('hidden', !custom);
        $('#idlm-dashboard-date-error').prop('hidden', true);
        updateResetState();
        if (!custom) load({ period: this.value, start_date: '', end_date: '' });
    });
    $('#idlm-dashboard-rep, #idlm-dashboard-studio, #idlm-dashboard-source, #idlm-dashboard-campaign').on('change', function() {
        updateResetState();
        load({ period: $('#idlm-period-selector').val(), start_date: applied.start_date || '', end_date: applied.end_date || '' });
    });
    $('#idlm-dashboard-reset').on('click', function() {
        $('#idlm-dashboard-rep, #idlm-dashboard-studio, #idlm-dashboard-source, #idlm-dashboard-campaign').val('');
        $('#idlm-period-selector').val('this_month');
        $('#idlm-custom-range-picker').prop('hidden', true);
        $('#idlm-start-date, #idlm-end-date').val('');
        updateResetState();
        load({ period: 'this_month', start_date: '', end_date: '' });
    });
    $('#idlm-start-date, #idlm-end-date').on('change input', updateResetState);
    $('#idlm-apply-custom').on('click', function() {
        var start = $('#idlm-start-date').val(), end = $('#idlm-end-date').val();
        if (!start || !end || start > end) {
            $('#idlm-dashboard-date-error').text('Choose valid start and end dates, with the start on or before the end.').prop('hidden', false); return;
        }
        $('#idlm-dashboard-date-error').prop('hidden', true);
        load({ period: 'custom', start_date: start, end_date: end });
    });
    $('#idlm-dashboard-refresh, #idlm-dashboard-retry').on('click', function() {
        var period = $('#idlm-period-selector').val();
        if (period === 'custom') $('#idlm-apply-custom').trigger('click');
        else load({ period: period, start_date: '', end_date: '' });
    });
    function render(data) {
        if (data.sales) renderSales(data.sales, data.marketing || {}, data.access, data.ranges);
        if (data.marketing) renderMarketing(data.marketing, data.ranges);
    }
    function renderSales(s, marketing, access, ranges) {
        var health = s.open_pipeline_health || {};
        var avgDeal = num(s.total_won) ? num(s.won_revenue_total) / num(s.total_won) : 0;
        var wonDetail = num(s.won_legacy_count) ? 'Includes legacy closes by recorded conversion time' : 'Transitions in selected period';
        $('#idlm-sales-kpis').html(
            kpi('Active Opportunities', count(s.open_pipeline_total), 'Current open pipeline', '', '') +
            kpi(access.pre_sales ? 'Qualified Leads' : 'Won Deals', count(access.pre_sales ? s.qualified_total : s.total_won), access.pre_sales ? 'Created in selected period' : wonDetail, '', '') +
            kpi(access.pre_sales ? 'Plans Collected' : 'Win Rate', access.pre_sales ? count(s.qualified_with_plan) : rate(s.total_won, num(s.total_won) + num(s.open_pipeline_total)), access.pre_sales ? rate(s.qualified_with_plan, s.qualified_total) + ' of qualified leads' : 'Won vs open and won opportunities', '', '') +
            kpi(access.pre_sales ? 'Avg. Time to Connect' : 'Revenue Won', access.pre_sales ? s.avg_time_connected : money(s.won_revenue_total, s.currency_symbol), 'Selected reporting period', '', '') +
            kpi(access.pre_sales ? 'Awaiting Handoff' : 'Avg. Deal Value', access.pre_sales ? count(health.awaiting_manager) : money(avgDeal, s.currency_symbol), access.pre_sales ? 'Current snapshot' : 'Recorded won value ÷ won deals', access.pre_sales ? 'handoff' : '', 'Awaiting sales handoff')
        );
        var queues = queue('overdue', health.overdue_task, 'Callbacks overdue', 'Pending task due date has passed', true) +
            queue('no_task', health.without_next_task, 'No next task', 'Set a next step for these open leads') +
            queue('aged', health.aged_61_plus, 'Open more than 60 days', 'Review older opportunities; age is since creation');
        if (access.manager) queues += queue('handoff', health.awaiting_manager, 'Awaiting sales assignment', 'Qualified leads waiting for manager handoff');
        $('#idlm-attention-list').html(queues);
        $('#sales-open-pipeline-total').text(count(s.open_pipeline_total) + ' leads');
        var stages = (s.funnel_data || []).filter(function(row) { return num(row.active_count) > 0; });
        var max = Math.max.apply(null, stages.map(function(row) { return num(row.active_count); }).concat([1]));
        $('#sales-funnel-container').html(stages.length ? stages.map(function(row) {
            return '<button type="button" class="idlm-dashboard-bar" data-investigate="stage" data-value="' + text(row.stage) + '" data-title="' + text(row.stage) + ' · open leads"' + (num(row.active_count) ? '' : ' disabled') + '><span>' + text(row.stage) + '</span><span class="idlm-dashboard-track" aria-hidden="true"><span style="width:' + (num(row.active_count) / max * 100) + '%"></span></span><strong>' + count(row.active_count) + '</strong></button>';
        }).join('') : '<p class="idlm-dashboard-empty">No open leads in your scope.</p>');
        renderPipelineByChannel(s);
        var assignments = s.assignment_distribution || [], assigned = assignments.reduce(function(total, row) { return total + num(row.total); }, 0);
        var assignmentDetail = num(s.assignment_distribution_legacy_count) ? 'Includes ' + count(s.assignment_distribution_legacy_count) + ' legacy assigned-at records' : 'Qualified sales assignments';
        $('#idlm-handoff-health').html(summary('Qualified', count(s.qualified_total), 'Created in selected period') + summary('Assigned', count(assigned), assignmentDetail) + summary('Awaiting', count(health.awaiting_manager), 'Needs manager assignment') + summary('Exceptions', count(health.unassigned), 'Open and unassigned'));
        var preSales = s.pre_sales_activity || {}, preSalesTeam = preSales.team || [];
        $('#idlm-pre-sales-activity').html(summary('Leads touched', count(preSales.leads_touched), 'Unique leads with direct activity') + summary('Outbound calls', count(preSales.calls), 'Logged calls in selected period') + summary('WhatsApp', count(preSales.whatsapp_outbound), count(preSales.whatsapp_replies) + ' customer replies') + summary('Follow-up tasks', count(preSales.tasks), count(preSales.status_updates) + ' call-status updates') + summary('Qualified', count(preSales.qualified), count(preSales.unqualified) + ' marked unqualified') + summary('Handed off', count(preSales.handed_off), 'Transferred to sales'));
        $('#idlm-pre-sales-activity-rows').html(preSalesTeam.length ? preSalesTeam.map(function(row) {
            return '<tr><th scope="row">' + text(row.name) + '</th><td>' + count(row.leads_touched) + '</td><td>' + count(row.calls) + '</td><td>' + count(row.whatsapp_outbound) + '</td><td>' + count(row.whatsapp_replies) + '</td><td>' + count(row.status_updates) + '</td><td>' + count(row.tasks) + '</td><td>' + count(row.qualified) + '</td><td>' + count(row.handed_off) + '</td><td>' + text(row.avg_time_to_qualify || '—') + '</td></tr>';
        }).join('') : empty(10, 'No users have the Pre-sales CRM role. Assign that role to attribute activity here.'));
        var mixes = s.business_mix || marketing.business_mix || {};
        function renderMix(kind) {
            var rows = mixes[kind] || [], total = rows.reduce(function(sum, row) { return sum + num(row.count); }, 0);
            $('#idlm-business-mix').html(rows.length ? rows.map(function(row) {
                return '<tr><th scope="row">' + text(row.segment) + '</th><td>' + count(row.count) + '</td><td>' + count(row.opportunities) + '</td><td>' + count(row.won) + '</td><td>' + money(row.revenue, s.currency_symbol) + '</td></tr>';
            }).join('') : empty(5, 'No ' + kind + ' segment data is available for this period.'));
        }
        renderMix('rooms');
        $root.find('[data-segment]').prop('disabled', false);
        $root.off('click.idlmMix').on('click.idlmMix', '[data-segment]', function() { $root.find('[data-segment]').removeClass('active'); $(this).addClass('active'); renderMix($(this).data('segment')); });
        $('#idlm-sales-period-note').text((ranges.curr_start || '').slice(0, 10) + ' – ' + (ranges.curr_end || '').slice(0, 10));
        if (access.pre_sales) {
            $('#idlm-sales-summary').html(summary('Qualified leads', count(s.qualified_total), 'Currently qualified, created in the period') + summary('Plans collected', count(s.qualified_with_plan), rate(s.qualified_with_plan, s.qualified_total) + ' of qualified leads in the cohort') + summary('Average time to connect', s.avg_time_connected, 'Recorded timing for leads created in the period'));
            $('#idlm-events-note').text('Readiness uses your currently assigned leads. Completed handoffs remain in the pre-sales history workspace.');
        } else {
            $('#idlm-sales-summary').html(summary('Won deals', count(s.total_won), wonDetail) + summary('Won value', money(s.won_revenue_total, s.currency_symbol), wonDetail) + summary('Avg. time to close', s.avg_time_to_close || '—', 'Lead creation to recorded close'));
            $('#idlm-events-note').text(num(s.won_legacy_count) ? 'Includes ' + count(s.won_legacy_count) + ' legacy Won records grouped by their canonical conversion time; immutable events cover newer transitions.' : 'Event reporting begins ' + (s.reporting_events_started_at || 'when event tracking was installed') + '. Reopened and re-won leads may contribute more than one transition. Earlier history is not reconstructed.');
            $('#sales-estimated-pipeline').text(money(s.estimated_pipeline_total, s.currency_symbol));
            $('#sales-estimated-pipeline-detail').text(count(s.estimated_pipeline_covered_leads) + ' of ' + count(s.open_pipeline_total) + ' open leads have a positive value in a configured stage.');
            renderSalesTrend(s, 'revenue');
        }
        var reasons = s.lost_reasons || [], reasonMax = Math.max.apply(null, reasons.map(function(row) { return num(row.count); }).concat([1]));
        var lostEmpty = num(s.lost_reasons_legacy_count) ? 'No untracked Lost leads were created in this period.' : 'No Lost transitions recorded in this period.';
        $('#idlm-lost-reasons').html(reasons.length ? reasons.map(function(row) { return '<div class="idlm-dashboard-bar"><span>' + text(row.reason) + '</span><span class="idlm-dashboard-track" aria-hidden="true"><span style="width:' + (num(row.count) / reasonMax * 100) + '%"></span></span><strong>' + count(row.count) + '</strong></div>'; }).join('') : '<p class="idlm-dashboard-empty">' + lostEmpty + '</p>');
        $('#idlm-lost-reasons-note').text(num(s.lost_reasons_legacy_count) ? 'Includes ' + count(s.lost_reasons_legacy_count) + ' current-state Lost records without a transition event.' : 'Recorded Lost transitions in the selected period.');
        $('#idlm-rep-outcomes').html((s.leads_by_rep || []).length ? s.leads_by_rep.map(function(row) { var average = num(row.converted) ? num(row.revenue) / num(row.converted) : 0; return '<tr><th scope="row">' + text(row.rep_name) + '</th><td>' + count(row.total) + '</td><td>' + count(row.converted) + '</td><td>' + rate(row.converted, row.total) + '</td><td>' + money(row.revenue, s.currency_symbol) + '</td><td>' + money(average, s.currency_symbol) + '</td><td>' + text(row.median_cycle || '—') + '</td></tr>'; }).join('') : empty(7, 'No assigned leads or Won deals fall in this period.'));
    }
    function renderPipelineByChannel(s) {
        var channels = s.pipeline_by_channel || [];
        var labels = channels.map(function(row) { return row.channel; });
        var hasData = channels.some(function(row) { return num(row.open) || num(row.closed); });
        $('#idlm-pipeline-channel-chart').prop('hidden', !hasData || !window.Chart);
        $('#idlm-pipeline-channel-empty').prop('hidden', hasData && !!window.Chart).text(hasData ? 'Chart unavailable; use the channel table below.' : 'No pipeline records in your scope.');
        $('#idlm-pipeline-channel-rows').html(channels.length ? channels.map(function(row) {
            return '<tr><th scope="row">' + text(row.channel) + '</th><td>' + count(row.open) + '</td><td>' + count(row.closed) + '</td><td>' + count(num(row.open) + num(row.closed)) + '</td></tr>';
        }).join('') : empty(4, 'No pipeline records in your scope.'));
        if (pipelineChannelChart) { pipelineChannelChart.destroy(); pipelineChannelChart = null; }
        if (hasData && window.Chart) pipelineChannelChart = new Chart(document.getElementById('idlm-pipeline-channel-chart'), {
            type: 'bar', data: { labels: labels, datasets: [
                { label: 'Open', data: channels.map(function(row) { return num(row.open); }), backgroundColor: dashboardToken('--idlm-action', '#7a5136'), borderRadius: 4 },
                { label: 'Closed', data: channels.map(function(row) { return num(row.closed); }), backgroundColor: dashboardToken('--idlm-text-muted', '#9aa0aa'), borderRadius: 4 }
            ] },
            options: { responsive: true, maintainAspectRatio: false, animation: false, plugins: { legend: { position: 'bottom', labels: { boxWidth: 10, usePointStyle: true } } }, scales: { x: { grid: { display: false }, ticks: { autoSkip: false, maxRotation: 45, minRotation: 0 } }, y: { beginAtZero: true, ticks: { precision: 0 }, grid: { color: dashboardToken('--idlm-border', '#e2e8f0') } } } }
        });
    }
    function renderSalesTrend(s, metric) {
        var days = s.won_over_time || [], cumulativeWon = 0, cumulativeRevenue = 0;
        var values = days.map(function(day) {
            if (metric === 'won') return num(day.won);
            if (metric === 'average') {
                // Average Deal Value is a period-to-date metric. Carry the
                // selected timeline's average forward between closes instead
                // of plotting each day's individual deal size.
                cumulativeWon += num(day.won);
                cumulativeRevenue += num(day.revenue);
                return cumulativeWon ? cumulativeRevenue / cumulativeWon : null;
            }
            return num(day.revenue);
        });
        if (salesChart) { salesChart.destroy(); salesChart = null; }
        var hasData = values.some(function(value) { return value > 0; });
        $('#idlm-sales-trend-chart').prop('hidden', !hasData || !window.Chart);
        $('#idlm-sales-trend-empty').prop('hidden', hasData && !!window.Chart).text(hasData ? 'Chart unavailable; use the summary values below.' : 'No Won transitions were recorded in this period.');
        if (hasData && window.Chart) salesChart = new Chart(document.getElementById('idlm-sales-trend-chart'), {
            type: 'line', data: { labels: days.map(function(day) { return day.date; }), datasets: [{ label: metric === 'won' ? 'Won Deals' : (metric === 'average' ? 'Cumulative Avg. Deal Value' : 'Revenue'), data: values, borderColor: dashboardToken('--idlm-action', '#7a5136'), backgroundColor: 'rgba(122,81,54,.09)', borderWidth: 2.5, pointRadius: days.length > 31 ? 0 : 3, pointBackgroundColor: '#fff', fill: metric === 'revenue', tension: 0, spanGaps: false }] },
            options: { responsive: true, maintainAspectRatio: false, animation: false, plugins: { legend: { display: false } }, scales: { x: { grid: { display: false }, ticks: { maxTicksLimit: 7 } }, y: { beginAtZero: true, grid: { color: dashboardToken('--idlm-border', '#e7e8ec') } } } }
        });
    }
    $root.on('click', '[data-sales-trend]', function() { $root.find('[data-sales-trend]').removeClass('active'); $(this).addClass('active'); if (stats && stats.sales) renderSalesTrend(stats.sales, $(this).data('sales-trend')); });
    function renderMarketing(m, ranges) {
        var channels = m.leads_by_channel || [], total = num(m.total_leads_period);
        var $source = $('#idlm-dashboard-source'), sourceValue = $source.val();
        channels.forEach(function(row) { if (!$source.find('option').filter(function() { return this.value === String(row.source_value); }).length) $source.append($('<option>').val(row.source_value).text(row.channel)); });
        $source.val(sourceValue);
        var $campaign = $('#idlm-dashboard-campaign'), campaignValue = $campaign.val();
        (m.top_campaigns || []).forEach(function(row) { if (!$campaign.find('option').filter(function() { return this.value === String(row.utm_value); }).length) $campaign.append($('<option>').val(row.utm_value).text(row.utm_value)); });
        $campaign.val(campaignValue);
        var qualified = 0, pending = 0, attributed = 0, won = 0;
        channels.forEach(function(row) { qualified += num(row.qualified); pending += num(row.pending); attributed += num(row.attributed); won += num(row.cohort_won); });
        var comparison = m.growth_pct == null ? 'No previous-period baseline (' + count(m.total_leads_previous) + ' leads)' : (num(m.growth_pct) > 0 ? '+' : '') + m.growth_pct + '% vs ' + count(m.total_leads_previous) + ' previously';
        comparison += ' · ' + (ranges.prev_start || '').slice(0, 10) + ' – ' + (ranges.prev_end || '').slice(0, 10);
        $('#idlm-marketing-kpis').html(kpi('Leads Generated', count(total), comparison, '', '') + kpi('Qualified Leads', count(qualified), 'Current quality of this cohort', '', '') + kpi('Qualification Rate', rate(qualified, total), count(pending) + ' pending assessment', '', '') + kpi('Won Customers', count(won), 'Current outcomes of this cohort', '', '') + kpi('Lead → Win Rate', rate(won, total), 'Current won ÷ leads generated', '', ''));
        $('#idlm-source-quality').html(channels.length ? channels.map(function(row) {
            var detail = count(row.count) + ' leads · ' + count(row.qualified) + ' qualified · ' + count(row.unqualified) + ' unqualified · ' + count(row.pending) + ' pending';
            return '<button type="button" class="idlm-dashboard-quality-row" data-investigate="channel" data-value="' + text(row.source_value) + '" data-title="' + text(row.channel) + ' · leads" aria-label="' + text(row.channel + ': ' + detail + '. Open matching leads.') + '"><span>' + text(row.channel) + '</span><span class="idlm-dashboard-quality" aria-hidden="true"><i class="is-qualified" style="width:' + (num(row.count) ? num(row.qualified) / num(row.count) * 100 : 0) + '%"></i><i class="is-unqualified" style="width:' + (num(row.count) ? num(row.unqualified) / num(row.count) * 100 : 0) + '%"></i><i class="is-pending" style="width:' + (num(row.count) ? num(row.pending) / num(row.count) * 100 : 0) + '%"></i></span><span class="idlm-dashboard-quality-detail" role="tooltip">' + text(detail) + '</span></button>';
        }).join('') : '<p class="idlm-dashboard-empty">No source data in this period.</p>');
        $('#channel-scorecard-table tbody').html(channels.length ? channels.map(function(row) {
            var mix = '<div class="idlm-dashboard-quality" role="img" aria-label="' + count(row.qualified) + ' qualified, ' + count(row.unqualified) + ' unqualified, ' + count(row.pending) + ' pending">';
            ['qualified', 'unqualified', 'pending'].forEach(function(key) { mix += '<span class="is-' + key + '" style="width:' + (num(row.count) ? num(row[key]) / num(row.count) * 100 : 0) + '%"></span>'; });
            return '<tr><th scope="row">' + link('channel', row.source_value, row.channel) + '</th><td>' + count(row.count) + '</td><td>' + count(row.qualified) + '</td><td>' + rate(row.qualified, row.count) + '</td><td>' + count(row.won) + '</td><td>' + money(row.revenue, m.currency_symbol || '') + '</td></tr>';
        }).join('') : empty(6, 'No acquired leads or Won deals fall in this period.'));
        $('#idlm-marketing-investigations').html(summary('Source captured', rate(attributed, total), count(attributed) + ' of ' + count(total) + ' leads') + summary('UTM campaign', rate((m.top_campaigns || []).reduce(function(sum, row) { return sum + num(row.count); }, 0), total), 'Campaign attribution coverage') + summary('Click ID captured', rate(m.click_id_count, total), 'Google, Meta or LinkedIn click ID') + summary('Landing page', rate((m.landing_pages || []).reduce(function(sum, row) { return sum + num(row.count); }, 0), total), 'Landing-page coverage') + summary('Duplicate rate', total + num(m.total_duplicates_period) ? rate(m.total_duplicates_period, total + num(m.total_duplicates_period)) : '—', 'Duplicate records created in period'));
        var campaigns = m.top_campaigns || [], adgroups = m.campaign_adgroups || [];
        $('#idlm-campaign-outcomes').html(campaigns.length ? campaigns.map(function(row, index) {
            var key = 'campaign-' + index;
            var parent = '<tr><th scope="row"><button type="button" class="idlm-dashboard-campaign-toggle" data-campaign-toggle="' + key + '" aria-expanded="false">＋</button> ' + link('campaign', row.utm_value, row.utm_value) + '</th><td>' + count(row.count) + '</td><td>' + count(row.qualified) + '</td><td>' + rate(row.qualified, row.count) + '</td><td>' + count(row.won) + '</td><td>' + rate(row.won, row.count) + '</td></tr>';
            return parent + adgroups.filter(function(child) { return child.utm_campaign === row.utm_value; }).map(function(child) { return '<tr class="idlm-dashboard-campaign-child" data-campaign-child="' + key + '" hidden><td>' + text(child.adgroup) + '</td><td>' + count(child.count) + '</td><td>' + count(child.qualified) + '</td><td>' + rate(child.qualified, child.count) + '</td><td>' + count(child.won) + '</td><td>' + rate(child.won, child.count) + '</td></tr>'; }).join('');
        }).join('') : empty(6, 'No UTM campaign values were recorded for this cohort.'));
        var reasonSets = { overall: m.disqualification_reasons || [], source: m.disqualification_reasons_by_source || [], campaign: m.disqualification_reasons_by_campaign || [] };
        var reasons = reasonSets[disqualificationView] || [], reasonMax = Math.max.apply(null, reasons.map(function(row) { return num(row.count); }).concat([1]));
        $('#idlm-disqualification-reasons').html(reasons.length ? reasons.map(function(row) { return '<div class="idlm-dashboard-bar"><span>' + text(row.reason) + '</span><span class="idlm-dashboard-track"><span style="width:' + (num(row.count) / reasonMax * 100) + '%"></span></span><strong>' + count(row.count) + '</strong></div>'; }).join('') : '<p class="idlm-dashboard-empty">No disqualification reasons were recorded in this period.</p>');
        var days = m.leads_over_time || [];
        if (chart) { chart.destroy(); chart = null; }
        var hasLeads = days.some(function(day) { return num(day.count) > 0; });
        $('#chart-leads-over-time').prop('hidden', !hasLeads || !window.Chart);
        $('#idlm-trend-empty').prop('hidden', hasLeads && !!window.Chart).text(hasLeads ? 'Use the daily counts table below to explore the trend.' : 'No leads were created in this period.');
        if (hasLeads && window.Chart) chart = new Chart(document.getElementById('chart-leads-over-time'), {
            type: 'line', data: { labels: days.map(function(day) { return day.date; }), datasets: [
                { label: 'Leads Acquired', data: days.map(function(day) { return num(day.count); }), borderColor: dashboardToken('--idlm-action', '#7a5136'), borderWidth: 2.5, pointRadius: days.length > 31 ? 0 : 3, fill: false, tension: 0, hidden: marketingTrend !== 'all' && marketingTrend !== 'leads' },
                { label: 'Qualified', data: days.map(function(day) { return num(day.qualified); }), borderColor: dashboardToken('--idlm-brand-gold', '#2f7a55'), borderWidth: 2, borderDash: [5, 5], pointRadius: days.length > 31 ? 0 : 3, pointBackgroundColor: '#fff', fill: false, tension: 0, hidden: marketingTrend !== 'all' && marketingTrend !== 'qualified' },
                { label: 'Won Closed', data: days.map(function(day) { return num(day.won); }), borderColor: dashboardToken('--idlm-text-muted', '#9aa0aa'), borderWidth: 2, pointRadius: days.length > 31 ? 0 : 3, pointBackgroundColor: '#fff', fill: false, tension: 0, hidden: marketingTrend !== 'all' && marketingTrend !== 'won' }
            ] },
            options: { responsive: true, maintainAspectRatio: false, animation: false, plugins: { legend: { display: false } }, scales: { x: { grid: { display: false }, ticks: { maxTicksLimit: 7 } }, y: { beginAtZero: true, ticks: { precision: 0 }, grid: { color: dashboardToken('--idlm-border', '#e2e8f0') } } } }
        });
    }
    $root.on('click', '[data-investigate]', function() {
        returnFocus = this;
        investigation = { investigate: $(this).attr('data-investigate'), value: $(this).attr('data-value') || '', records_page: 1 };
        $('#idlm-records-title').text($(this).attr('data-title'));
        $('#idlm-dashboard-records').prop('hidden', false);
        $('#idlm-dashboard-insight-title').text($(this).attr('data-title') || 'Filter applied');
        $('#idlm-dashboard-insight').prop('hidden', false);
        $('#idlm-records-title')[0].focus();
        $('#idlm-dashboard-records')[0].scrollIntoView({ block: 'start', behavior: 'auto' });
        loadRecords();
    });
    function loadRecords() {
        var token = ++recordSequence;
        if (recordRequest) recordRequest.abort();
        $('#idlm-records-status').text('Loading matching leads…');
        $('#idlm-records-body').empty();
        $('#idlm-records-prev, #idlm-records-next').prop('disabled', true);
        recordRequest = $.ajax({ url: idlm_vars.ajax_url, type: 'POST', data: $.extend({ action: 'idlm_get_dashboard_stats', nonce: idlm_vars.nonce }, applied, investigation) })
            .done(function(response) {
                if (token !== recordSequence || !investigation) return;
                if (!response.success) { $('#idlm-records-status').text(response.data.message || 'Could not load these leads. Close and reopen the investigation to retry.'); return; }
                var data = response.data, rows = data.records || [];
                $('#idlm-records-body').html(rows.length ? rows.map(function(row) {
                    var name = [row.first_name, row.last_name].filter(Boolean).join(' ') || 'Lead #' + row.id;
                    return '<tr><th scope="row"><button type="button" class="idlm-dashboard-link" data-dashboard-lead="' + num(row.id) + '">' + text(name) + '</button></th><td>' + text(row.stage || 'New') + '</td><td>' + text(row.lead_quality || 'Pending') + '</td><td>' + text(row.created_at) + '</td><td>' + text(row.next_due || 'No dated pending task') + '</td></tr>';
                }).join('') : empty(5, 'No leads match this investigation. The queue may have changed since the report loaded.'));
                $('#idlm-records-status').text('Oldest first · ' + rows.length + (rows.length === 1 ? ' lead' : ' leads') + ' on this page. Open a lead to review its details and permitted actions.');
                $('#idlm-records-page').text('Page ' + data.page);
                $('#idlm-records-prev').prop('disabled', data.page <= 1);
                $('#idlm-records-next').prop('disabled', !data.has_more);
            }).fail(function(xhr, status) { if (status !== 'abort' && token === recordSequence) $('#idlm-records-status').text('Could not load leads. Close and reopen this investigation to retry.'); });
    }
    $('#idlm-records-prev, #idlm-records-next').on('click', function() { if (!investigation) return; investigation.records_page += this.id === 'idlm-records-next' ? 1 : -1; loadRecords(); });
    $('#idlm-records-close').on('click', function() { closeRecords(true); });
    $('#idlm-dashboard-insight-clear').on('click', function() { $('#idlm-dashboard-insight').prop('hidden', true); closeRecords(true); });
    $root.on('click', '[data-campaign-toggle]', function(event) {
        event.stopPropagation();
        var key = $(this).data('campaign-toggle'), expanded = $(this).attr('aria-expanded') !== 'true';
        $(this).attr('aria-expanded', expanded ? 'true' : 'false').text(expanded ? '−' : '＋');
        $root.find('[data-campaign-child="' + key + '"]').prop('hidden', !expanded);
    });
    $root.on('click', '[data-marketing-trend]', function() { marketingTrend = $(this).data('marketing-trend'); $root.find('[data-marketing-trend]').removeClass('active'); $(this).addClass('active'); if (stats && stats.marketing) renderMarketing(stats.marketing, stats.ranges || {}); });
    $root.on('click', '[data-disqualification-view]', function() { disqualificationView = $(this).data('disqualification-view'); $root.find('[data-disqualification-view]').removeClass('active'); $(this).addClass('active'); if (stats && stats.marketing) renderMarketing(stats.marketing, stats.ranges || {}); });
    // Keep dashboard-launched legacy drawers exposed to assistive technology.
    var drawer = document.getElementById('idlm-lead-drawer');
    var drawerTrigger = null;
    $root.on('click', '[data-dashboard-lead]', function() {
        if (!drawer || typeof window.openLeadDrawer !== 'function') return;
        drawerTrigger = this;
        window.openLeadDrawer(num($(this).attr('data-dashboard-lead')));
        drawer.setAttribute('aria-hidden', 'false');
        drawer.focus({ preventScroll: true });
    });
    if (drawer) {
        new MutationObserver(function() {
            if (drawerTrigger && !drawer.classList.contains('active')) {
                drawer.setAttribute('aria-hidden', 'true');
                if (document.contains(drawerTrigger)) drawerTrigger.focus();
                drawerTrigger = null;
            }
        }).observe(drawer, { attributes: true, attributeFilter: ['class'] });
        $(drawer).on('keydown', function(event) {
            if (!drawerTrigger || event.key !== 'Tab') return;
            var controls = $(drawer).find('button, a[href], input, select, textarea, [tabindex="0"]').filter(':visible').not(':disabled').toArray();
            if (!controls.length) { event.preventDefault(); return; }
            var first = controls[0], last = controls[controls.length - 1];
            if (event.shiftKey && (event.target === first || event.target === drawer)) { event.preventDefault(); last.focus(); }
            else if (!event.shiftKey && event.target === last) { event.preventDefault(); first.focus(); }
        });
    }
    syncFilterVisibility();
    load(applied);
});
