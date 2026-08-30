jQuery(document).ready(function($) {
    var channelChart = null;
    var campaignChart = null;
    var stageChart = null;
    var statusChart = null;
    var leadsOverTimeChart = null;
    var propertyTypeChart = null;
    var roomConfigChart = null;
    var studioChart = null;
    var overallQualityChart = null;
    var revenueByChannelChart = null;
    var revenueSummaryChart = null;
    var lostReasonsChart = null;
    var salesAssignmentsChart = null;
    var activeUtmData = null;

    function escapePerformanceText(value) {
        return $('<div>').text(value == null ? '' : String(value)).html();
    }

    var centerTextPlugin = {
        id: 'centerText',
        beforeDraw: function(chart) {
            var ctx = chart.ctx;
            ctx.restore();
            
            var total = 0;
            chart.data.datasets[0].data.forEach(function(val) {
                total += Number(val) || 0;
            });
            
            var chartArea = chart.chartArea;
            if (!chartArea) return;
            var centerX = (chartArea.left + chartArea.right) / 2;
            var centerY = (chartArea.top + chartArea.bottom) / 2;
            
            ctx.font = "500 11px 'Outfit', sans-serif";
            ctx.fillStyle = "#64748b";
            ctx.textAlign = "center";
            ctx.textBaseline = "middle";
            ctx.fillText("Unique Leads", centerX, centerY - 10);
            
            ctx.font = "bold 20px 'Outfit', sans-serif";
            ctx.fillStyle = "#0f172a";
            ctx.fillText(total, centerX, centerY + 10);
            
            ctx.save();
        }
    };

    // Load initial stats if dashboard elements exist
    if ($('#idlm-period-selector').length > 0) {
        loadDashboardStats('this_month');
    }

    // Period selector change
    $('#idlm-period-selector').change(function() {
        var val = $(this).val();
        if (val === 'custom') {
            $('#idlm-custom-range-picker').css('display', 'inline-flex');
        } else {
            $('#idlm-custom-range-picker').hide();
            loadDashboardStats(val);
        }
    });

    // Custom date range submit
    $('#idlm-apply-custom').click(function() {
        var start = $('#idlm-start-date').val();
        var end = $('#idlm-end-date').val();
        if (start && end) {
            loadDashboardStats('custom', start, end);
        } else {
            alert('Please select both start and end dates.');
        }
    });

    /**
     * Fetch and render dashboard metrics
     */
    function loadDashboardStats(period, start, end) {
        var reportingPeriodLabel = $('#idlm-period-selector option:selected').text() || 'Selected Period';
        if (period === 'custom' && start && end) {
            reportingPeriodLabel = start + ' to ' + end;
        }

        $.ajax({
            url: idlm_vars.ajax_url,
            type: 'POST',
            data: {
                action: 'idlm_get_dashboard_stats',
                nonce: idlm_vars.nonce,
                period: period,
                start_date: start,
                end_date: end
            },
            success: function(response) {
                if (response.success) {
                    var stats = response.data;
                    updateDashboardUI(stats, reportingPeriodLabel);
                }
            }
        });
    }

    /**
     * Populates texts, charts and tables with AJAX response data
     */
    function updateDashboardUI(stats, reportingPeriodLabel) {
        var mkt = stats.marketing;
        var sales = stats.sales;
        var targets = stats.targets;

        // 1. Update Monthly Targets Progress Bars
        var leadProgress = (targets.lead_target > 0) ? (targets.current_leads / targets.lead_target) * 100 : 0;
        $('#lead-target-ratio').text(targets.current_leads + ' / ' + targets.lead_target);
        $('#lead-target-bar').css('width', Math.min(100, leadProgress) + '%');

        var convProgress = (targets.conversion_target > 0) ? (targets.current_conversions / targets.conversion_target) * 100 : 0;
        $('#conv-target-ratio').text(targets.month_label + ': ' + targets.current_conversions + ' / ' + targets.conversion_target);
        $('#conv-target-bar').css('width', Math.min(100, convProgress) + '%');

        // 2. Marketing KPI Cards
        $('#mkt-total-leads').text(mkt.total_leads_period);
        $('#mkt-prev-leads').text(mkt.total_leads_previous);
        
        var growth = mkt.growth_pct;
        var trendEl = $('#mkt-growth-pct');
        trendEl.removeClass('positive negative');
        if (growth === null || typeof growth === 'undefined') {
            trendEl.text(Number(mkt.total_leads_period) > 0 ? 'New' : 'N/A');
        } else if (growth >= 0) {
            trendEl.addClass('positive').text('+' + growth + '%');
        } else {
            trendEl.addClass('negative').text(growth + '%');
        }

        // Find Top Channel
        var topChannel = 'N/A';
        if (mkt.leads_by_channel.length > 0) {
            topChannel = mkt.leads_by_channel[0].channel;
        }
        $('#mkt-top-channel').text(topChannel);

        // Populate Duplicate Leads card
        $('#mkt-total-duplicates').text(mkt.total_duplicates_period);
        var dupGrowth = mkt.duplicates_growth_pct;
        var dupTrendEl = $('#mkt-duplicates-growth-pct');
        dupTrendEl.removeClass('positive negative');
        if (dupGrowth === null || typeof dupGrowth === 'undefined') {
            dupTrendEl.text(Number(mkt.total_duplicates_period) > 0 ? 'New' : 'N/A');
        } else if (dupGrowth >= 0) {
            dupTrendEl.addClass('positive').text('+' + dupGrowth + '%');
        } else {
            dupTrendEl.addClass('negative').text(dupGrowth + '%');
        }

        // 3. Render Marketing Charts
        renderLeadsByChannelChart(mkt.leads_by_channel);
        renderLeadsOverTimeChart(mkt.leads_over_time);
        renderPropertyTypeChart(mkt.leads_by_property);
        renderRoomConfigChart(mkt.leads_by_rooms);
        
        // Cache UTM datasets for dynamic, client-side interactive updates (implemented by Shashi)
        if (mkt.utm_data) {
            activeUtmData = mkt.utm_data;
        } else {
            // Fallback backward compatibility
            activeUtmData = {
                'utm_campaign': mkt.top_campaigns || []
            };
        }
        var selectedUtmType = $('#idlm-utm-type-selector').val() || 'utm_campaign';
        var utmLabel = getUtmLabel(selectedUtmType);
        $('#idlm-utm-chart-title').text('Top ' + utmLabel);
        renderCampaignsChart(activeUtmData[selectedUtmType] || [], utmLabel);

        // 4. Sales KPI Cards
        $('#sales-total-won-label').text('Total Won — ' + reportingPeriodLabel);
        $('#sales-total-won').text(sales.total_won);
        $('#sales-avg-connected').text(sales.avg_time_connected);
        $('#sales-avg-won').text(sales.avg_time_won);
        $('#sales-estimated-pipeline').text(formatRevenueValue(sales.estimated_pipeline_total, sales.currency_symbol));
        $('#sales-won-revenue').text(formatRevenueValue(sales.won_revenue_total, sales.currency_symbol));
        $('#sales-plan-collection-rate').text((parseFloat(sales.plan_collection_rate) || 0).toFixed(1).replace('.0', '') + '%');
        $('#sales-plan-collection-detail').text((parseInt(sales.qualified_with_plan, 10) || 0) + ' of ' + (parseInt(sales.qualified_total, 10) || 0) + ' qualified leads');

        // 6. Render Sales Charts
        renderPipelineStagesChart(sales.leads_by_stage);
        renderCallStatusChart(sales.call_status_distribution);
        renderOverallQualityChart(sales.lead_quality_distribution);
        renderSalesFunnel(sales.funnel_data);
        renderStudioChart(sales.leads_by_studio);
        renderRevenueByChannelChart(sales.revenue_by_channel || [], sales.currency_symbol || sales.currency_code || '', sales.currency_code || '');
        renderRevenueSummaryChart(sales.estimated_pipeline_total, sales.won_revenue_total, sales.currency_symbol || sales.currency_code || '', sales.currency_code || '');
        renderLostReasonsChart(sales.lost_reasons || [], sales.currency_symbol || sales.currency_code || '');
        renderSalesAssignmentsChart(sales.assignment_distribution || []);

        // Populate Studio Branch Table
        var studioHtml = '';
        if (sales.studio_performance.length === 0) {
            studioHtml = '<tr><td colspan="4" style="text-align:center;">No studio branch performance logged.</td></tr>';
        } else {
            sales.studio_performance.forEach(function(row) {
                studioHtml += '<tr>\
                    <td><strong>' + escapePerformanceText(row.studio) + '</strong></td>\
                    <td>' + row.total + '</td>\
                    <td>' + row.converted + '</td>\
                    <td><strong>' + row.conversion_rate + '%</strong></td>\
                </tr>';
            });
        }
        $('#studio-performance-table tbody').html(studioHtml);

        var preSalesHtml = '';
        var preSalesRows = sales.pre_sales_performance || [];
        if (preSalesRows.length === 0) {
            preSalesHtml = '<tr><td colspan="8" style="text-align:center;">No pre-sales performance logged for this period.</td></tr>';
        } else {
            preSalesRows.forEach(function(row) {
                preSalesHtml += '<tr>\
                    <td><strong>' + escapePerformanceText(row.rep_name) + '</strong></td>\
                    <td>' + row.total + '</td>\
                    <td>' + row.contacted + '</td>\
                    <td>' + row.qualified + '</td>\
                    <td>' + row.unqualified + '</td>\
                    <td>' + row.handed_off + '</td>\
                    <td><strong>' + row.qualification_rate + '%</strong></td>\
                    <td>' + escapePerformanceText(row.avg_time_to_qualify) + '</td>\
                </tr>';
            });
        }
        $('#pre-sales-performance-table tbody').html(preSalesHtml);

        // 7. Populate Sales Rep Table
        var repHtml = '';
        if (sales.leads_by_rep.length === 0) {
            repHtml = '<tr><td colspan="9" style="text-align:center;">No sales reps performance logged.</td></tr>';
        } else {
            sales.leads_by_rep.forEach(function(row) {
                repHtml += '<tr>\
                    <td><strong>' + escapePerformanceText(row.rep_name) + '</strong></td>\
                    <td>' + row.total + '</td>\
                    <td>' + row.contacted + '</td>\
                    <td>' + escapePerformanceText(row.avg_time_connected) + '</td>\
                    <td><span class="idlm-badge idlm-badge-success" style="background-color:rgba(16, 185, 129, 0.15); color:#10b981; border: none; font-size: 11px; padding: 2px 8px;">' + row.qualified + '</span></td>\
                    <td><span class="idlm-badge idlm-badge-danger" style="background-color:rgba(239, 68, 68, 0.15); color:#ef4444; border: none; font-size: 11px; padding: 2px 8px;">' + row.unqualified + '</span></td>\
                    <td>' + row.converted + '</td>\
                    <td>' + escapePerformanceText(row.avg_time_won) + '</td>\
                    <td><strong>' + row.conversion_rate + '%</strong></td>\
                </tr>';
            });
        }
        $('#sales-rep-table tbody').html(repHtml);
    }

    /* ==========================================================================
       ChartJS Rendering Functions (Premium Styling Options)
       ========================================================================== */

    function getPercentTooltipCallback() {
        return {
            callbacks: {
                label: function(context) {
                    var value = Number(context.raw) || 0;
                    var total = 0;
                    
                    var chartId = context.chart.canvas.id;
                    var isStackedChart = chartId === 'chart-leads-by-channel' || chartId === 'chart-utm-campaigns';
                    
                    if (isStackedChart) {
                        var dataIndex = context.dataIndex;
                        context.chart.data.datasets.forEach(function(dataset) {
                            total += Number(dataset.data[dataIndex]) || 0;
                        });
                    } else {
                        context.chart.data.datasets.forEach(function(dataset) {
                            dataset.data.forEach(function(val) {
                                total += Number(val) || 0;
                            });
                        });
                    }
                    
                    var percentage = total > 0 ? ((value / total) * 100).toFixed(1) : 0;
                    var label = '';
                    if (context.chart.config.type === 'doughnut' || context.chart.config.type === 'pie') {
                        label = context.label || '';
                    } else {
                        label = context.dataset.label || '';
                    }
                    
                    if (label) {
                        label = label.replace(/\s\(\d+\)$/, '');
                        label += ': ';
                    }
                    label += value + ' (' + percentage + '%)';
                    return label;
                }
            }
        };
    }

    function renderLeadsByChannelChart(data) {
        var ctx = document.getElementById('chart-leads-by-channel').getContext('2d');
        if (channelChart) channelChart.destroy();

        var labels = data.map(function(item) {
            var total = (item.qualified || 0) + (item.unqualified || 0) + (item.pending || 0);
            return (item.channel || 'Direct / Unknown') + ' (' + total + ')';
        });
        var qualified = data.map(function(item) { return item.qualified || 0; });
        var unqualified = data.map(function(item) { return item.unqualified || 0; });
        var pending = data.map(function(item) { return item.pending || 0; });

        channelChart = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: labels,
                datasets: [
                    {
                        label: 'Qualified',
                        data: qualified,
                        backgroundColor: 'rgba(16, 185, 129, 0.75)', // green
                        borderColor: 'rgba(16, 185, 129, 1)',
                        borderWidth: 1.5,
                        borderRadius: 4
                    },
                    {
                        label: 'Unqualified',
                        data: unqualified,
                        backgroundColor: 'rgba(239, 68, 68, 0.75)', // red
                        borderColor: 'rgba(239, 68, 68, 1)',
                        borderWidth: 1.5,
                        borderRadius: 4
                    },
                    {
                        label: 'Pending Evaluation',
                        data: pending,
                        backgroundColor: 'rgba(148, 163, 184, 0.75)', // gray
                        borderColor: 'rgba(148, 163, 184, 1)',
                        borderWidth: 1.5,
                        borderRadius: 4
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { 
                    legend: { 
                        display: true,
                        position: 'top',
                        labels: { boxWidth: 12, font: { size: 11 } }
                    },
                    tooltip: getPercentTooltipCallback()
                },
                scales: { 
                    x: { stacked: true },
                    y: { stacked: true, beginAtZero: true, grid: { color: '#f1f5f9' } } 
                }
            }
        });
    }


    

    function renderCampaignsChart(data, datasetLabel) {
        var ctx = document.getElementById('chart-utm-campaigns').getContext('2d');
        if (campaignChart) campaignChart.destroy();

        var labels = data.map(function(item) {
            var total = (item.qualified || 0) + (item.unqualified || 0) + (item.pending || 0);
            var name = item.utm_value || item.utm_campaign || item.utm_source || item.utm_medium || item.utm_adgroup || item.utm_term || item.utm_content || 'Direct / None';
            return name + ' (' + total + ')';
        });
        var qualified = data.map(function(item) { return item.qualified || 0; });
        var unqualified = data.map(function(item) { return item.unqualified || 0; });
        var pending = data.map(function(item) { return item.pending || 0; });

        campaignChart = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: labels,
                datasets: [
                    {
                        label: 'Qualified',
                        data: qualified,
                        backgroundColor: 'rgba(16, 185, 129, 0.75)', // green
                        borderColor: 'rgba(16, 185, 129, 1)',
                        borderWidth: 1.5,
                        borderRadius: 4
                    },
                    {
                        label: 'Unqualified',
                        data: unqualified,
                        backgroundColor: 'rgba(239, 68, 68, 0.75)', // red
                        borderColor: 'rgba(239, 68, 68, 1)',
                        borderWidth: 1.5,
                        borderRadius: 4
                    },
                    {
                        label: 'Pending Evaluation',
                        data: pending,
                        backgroundColor: 'rgba(148, 163, 184, 0.75)', // gray
                        borderColor: 'rgba(148, 163, 184, 1)',
                        borderWidth: 1.5,
                        borderRadius: 4
                    }
                ]
            },
            options: {
                indexAxis: 'y',
                responsive: true,
                maintainAspectRatio: false,
                plugins: { 
                    legend: { 
                        display: true,
                        position: 'top',
                        labels: { boxWidth: 12, font: { size: 11 } }
                    },
                    tooltip: getPercentTooltipCallback()
                },
                scales: { 
                    x: { stacked: true, beginAtZero: true },
                    y: { stacked: true } 
                }
            }
        });
    }

    function renderPipelineStagesChart(data) {
        var ctx = document.getElementById('chart-pipeline-stages').getContext('2d');
        if (stageChart) stageChart.destroy();

        var labels = data.map(function(item) { return item.stage + ' (' + item.count + ')'; });
        var values = data.map(function(item) { return item.count; });

        stageChart = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: labels,
                datasets: [{
                    label: 'Leads in Stage',
                    data: values,
                    backgroundColor: 'rgba(99, 102, 241, 0.65)',
                    borderColor: 'rgba(99, 102, 241, 0.95)',
                    borderWidth: 1.5,
                    borderRadius: 6
                }]
            },
            options: {
                indexAxis: 'y',
                responsive: true,
                maintainAspectRatio: false,
                plugins: { 
                    legend: { display: false },
                    tooltip: getPercentTooltipCallback()
                },
                scales: { x: { beginAtZero: true } }
            }
        });
    }

    function renderRevenueByChannelChart(data, currencySymbol, currencyCode) {
        var canvas = document.getElementById('chart-revenue-by-channel');
        if (!canvas) return;

        if (revenueByChannelChart) {
            revenueByChannelChart.destroy();
            revenueByChannelChart = null;
        }

        var $emptyState = $('#chart-revenue-by-channel-empty');
        if (!data || data.length === 0) {
            $(canvas).hide();
            $emptyState.css('display', 'flex');
            return;
        }

        $(canvas).show();
        $emptyState.hide();
        var labels = data.map(function(item) { return item.channel || 'Direct / Unknown'; });
        var estimatedValues = data.map(function(item) { return Number(item.estimated_pipeline) || 0; });
        var wonValues = data.map(function(item) { return Number(item.won_revenue) || 0; });
        var pipelineCounts = data.map(function(item) { return Number(item.pipeline_leads) || 0; });
        var wonCounts = data.map(function(item) { return Number(item.won_leads) || 0; });

        revenueByChannelChart = new Chart(canvas.getContext('2d'), {
            type: 'bar',
            data: {
                labels: labels,
                datasets: [
                    {
                        label: 'Estimated Open Pipeline',
                        data: estimatedValues,
                        leadCounts: pipelineCounts,
                        backgroundColor: 'rgba(201, 150, 68, 0.72)',
                        borderColor: '#a87328',
                        borderWidth: 1.5,
                        borderRadius: 5
                    },
                    {
                        label: 'Won Revenue',
                        data: wonValues,
                        leadCounts: wonCounts,
                        backgroundColor: 'rgba(59, 130, 246, 0.68)',
                        borderColor: '#2563eb',
                        borderWidth: 1.5,
                        borderRadius: 5
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: { mode: 'index', intersect: false },
                plugins: {
                    legend: {
                        display: true,
                        position: 'top',
                        align: 'start',
                        labels: { boxWidth: 12, usePointStyle: true, font: { size: 11 } }
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                return context.dataset.label + ': ' + formatRevenueValue(context.raw, currencySymbol);
                            },
                            afterLabel: function(context) {
                                var leadCount = context.dataset.leadCounts[context.dataIndex] || 0;
                                return leadCount + (leadCount === 1 ? ' lead' : ' leads');
                            }
                        }
                    }
                },
                scales: {
                    x: {
                        grid: { display: false },
                        ticks: {
                            maxRotation: 35,
                            minRotation: 0,
                            font: { size: 10 }
                        }
                    },
                    y: {
                        beginAtZero: true,
                        grid: { color: '#eef2f7' },
                        ticks: {
                            callback: function(value) {
                                return formatCompactRevenueValue(value, currencySymbol, currencyCode);
                            }
                        }
                    }
                }
            }
        });
    }

    function renderRevenueSummaryChart(estimatedPipeline, wonRevenue, currencySymbol, currencyCode) {
        var canvas = document.getElementById('chart-revenue-summary');
        if (!canvas) return;

        if (revenueSummaryChart) {
            revenueSummaryChart.destroy();
            revenueSummaryChart = null;
        }

        var values = [Number(estimatedPipeline) || 0, Number(wonRevenue) || 0];
        var $emptyState = $('#chart-revenue-summary-empty');
        if (values[0] <= 0 && values[1] <= 0) {
            $(canvas).hide();
            $emptyState.css('display', 'flex');
            return;
        }

        $(canvas).show();
        $emptyState.hide();

        revenueSummaryChart = new Chart(canvas.getContext('2d'), {
            type: 'bar',
            data: {
                labels: ['Estimated Open Pipeline', 'Won Revenue'],
                datasets: [{
                    data: values,
                    backgroundColor: ['rgba(201, 150, 68, 0.72)', 'rgba(59, 130, 246, 0.68)'],
                    borderColor: ['#a87328', '#2563eb'],
                    borderWidth: 1.5,
                    borderRadius: 6,
                    maxBarThickness: 90
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                return formatRevenueValue(context.raw, currencySymbol);
                            }
                        }
                    }
                },
                scales: {
                    x: {
                        grid: { display: false },
                        ticks: { font: { size: 10 } }
                    },
                    y: {
                        beginAtZero: true,
                        grid: { color: '#eef2f7' },
                        ticks: {
                            callback: function(value) {
                                return formatCompactRevenueValue(value, currencySymbol, currencyCode);
                            }
                        }
                    }
                }
            }
        });
    }

    function renderLostReasonsChart(data, currencySymbol) {
        var canvas = document.getElementById('chart-lost-reasons');
        if (!canvas) return;

        if (lostReasonsChart) {
            lostReasonsChart.destroy();
            lostReasonsChart = null;
        }

        var $emptyState = $('#chart-lost-reasons-empty');
        if (!data || data.length === 0) {
            $(canvas).hide();
            $emptyState.css('display', 'flex');
            return;
        }

        $(canvas).show();
        $emptyState.hide();

        var labels = data.map(function(item) { return item.reason || 'Uncategorized'; });
        var values = data.map(function(item) { return Number(item.count) || 0; });
        var quoteValues = data.map(function(item) { return Number(item.quote_value) || 0; });
        var totalLost = values.reduce(function(total, value) { return total + value; }, 0);

        lostReasonsChart = new Chart(canvas.getContext('2d'), {
            type: 'bar',
            data: {
                labels: labels,
                datasets: [{
                    label: 'Lost Leads',
                    data: values,
                    quoteValues: quoteValues,
                    backgroundColor: 'rgba(239, 68, 68, 0.68)',
                    borderColor: '#dc2626',
                    borderWidth: 1.5,
                    borderRadius: 6,
                    maxBarThickness: 44
                }]
            },
            options: {
                indexAxis: 'y',
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                var count = Number(context.raw) || 0;
                                var percentage = totalLost > 0 ? (count / totalLost * 100).toFixed(1) : '0.0';
                                return count + (count === 1 ? ' lost lead' : ' lost leads') + ' (' + percentage + '%)';
                            },
                            afterLabel: function(context) {
                                var quoteValue = context.dataset.quoteValues[context.dataIndex] || 0;
                                return 'Associated quote value: ' + formatRevenueValue(quoteValue, currencySymbol);
                            }
                        }
                    }
                },
                scales: {
                    x: {
                        beginAtZero: true,
                        ticks: { precision: 0, stepSize: 1 },
                        grid: { color: '#eef2f7' }
                    },
                    y: {
                        grid: { display: false },
                        ticks: { font: { size: 11 } }
                    }
                }
            }
        });
    }

    function formatRevenueValue(value, currencySymbol) {
        var numericValue = Number(value) || 0;
        return (currencySymbol || '') + numericValue.toLocaleString(undefined, {
            minimumFractionDigits: 0,
            maximumFractionDigits: 2
        });
    }

    function formatCompactRevenueValue(value, currencySymbol, currencyCode) {
        var numericValue = Number(value) || 0;
        if (currencyCode === 'INR') {
            if (Math.abs(numericValue) >= 10000000) {
                return (currencySymbol || '') + (numericValue / 10000000).toFixed(1).replace(/\.0$/, '') + 'Cr';
            }
            if (Math.abs(numericValue) >= 100000) {
                return (currencySymbol || '') + (numericValue / 100000).toFixed(1).replace(/\.0$/, '') + 'L';
            }
        } else {
            if (Math.abs(numericValue) >= 1000000000) {
                return (currencySymbol || '') + (numericValue / 1000000000).toFixed(1).replace(/\.0$/, '') + 'B';
            }
            if (Math.abs(numericValue) >= 1000000) {
                return (currencySymbol || '') + (numericValue / 1000000).toFixed(1).replace(/\.0$/, '') + 'M';
            }
        }
        if (Math.abs(numericValue) >= 1000) {
            return (currencySymbol || '') + (numericValue / 1000).toFixed(1).replace(/\.0$/, '') + 'K';
        }
        return (currencySymbol || '') + numericValue;
    }

    function renderSalesFunnel(funnelData) {
        var container = $('#sales-funnel-container');
        if (container.length === 0) return;

        if (!funnelData || funnelData.length === 0) {
            container.html('<p style="text-align:center; color:var(--idlm-text-muted);">No funnel data available for the selected period.</p>');
            return;
        }

        var html = '';
        var svgArrow = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path d="M11 4h2v12h3l-4 4-4-4h3V4z"/></svg>';

        funnelData.forEach(function(step, index) {
            if (index > 0) html += '<div class="idlm-funnel-connector">' + svgArrow + '</div>';

            html += '<div class="idlm-funnel-step-wrapper">';
            html += '    <div class="idlm-funnel-step">';
            // Progress background fill
            html += '        <div class="idlm-funnel-step-fill" style="height: ' + step.rate_from_start + '%;"></div>';
            html += '        <div class="idlm-funnel-step-content">';
            html += '            <div class="idlm-funnel-step-label">';
            html += '                <span class="idlm-funnel-step-badge">' + (index + 1) + '</span>';
            html += '                <span class="idlm-funnel-step-name">' + step.stage + '</span>';
            html += '            </div>';
            html += '            <div class="idlm-funnel-step-metrics">';
            html += '                <div class="idlm-funnel-metric">';
            html += '                    <span class="idlm-funnel-metric-val">' + step.cumulative_count + '</span>';
            html += '                    <span class="idlm-funnel-metric-label">Leads currently here</span>';
            html += '                </div>';
            html += '                <div class="idlm-funnel-metric">';
            html += '                    <span class="idlm-funnel-metric-val">' + step.rate_from_start + '%</span>';
            html += '                    <span class="idlm-funnel-metric-label">Share of visible pipeline</span>';
            html += '                </div>';
            html += '            </div>';
            html += '        </div>';
            html += '    </div>';
            html += '</div>';
        });

        container.html(html);
    }

    function renderCallStatusChart(data) {
        var ctx = document.getElementById('chart-call-status-dist').getContext('2d');
        if (statusChart) statusChart.destroy();

        var labels = data.map(function(item) { return item.call_status + ' (' + item.count + ')'; });
        var values = data.map(function(item) { return item.count; });

        var palette = ['#6366f1', '#10b981', '#f59e0b', '#ef4444', '#0ea5e9', '#8b5cf6', '#ec4899', '#64748b'];

        statusChart = new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: labels,
                datasets: [{
                    data: values,
                    backgroundColor: palette.slice(0, data.length),
                    borderWidth: 2,
                    borderColor: '#ffffff',
                    cutout: '70%'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { 
                    legend: { position: 'right', labels: { boxWidth: 12, font: { size: 11 } } },
                    tooltip: getPercentTooltipCallback()
                }
            },
            plugins: [centerTextPlugin]
        });
    }

    // UTM type selector change (implemented by Shashi)
    $('#idlm-utm-type-selector').change(function() {
        var selectedType = $(this).val();
        if (activeUtmData) {
            var label = getUtmLabel(selectedType);
            $('#idlm-utm-chart-title').text('Top ' + label);
            renderCampaignsChart(activeUtmData[selectedType] || [], label);
        }
    });

    function getUtmLabel(type) {
        var labels = {
            'utm_campaign': 'UTM Campaigns',
            'utm_source': 'UTM Sources',
            'utm_medium': 'UTM Mediums',
            'utm_adgroup': 'UTM Adgroups',
            'utm_term': 'UTM Terms',
            'utm_content': 'UTM Contents'
        };
        return labels[type] || 'UTM Campaigns';
    }

    function renderLeadsOverTimeChart(data) {
        var canvas = document.getElementById('chart-leads-over-time');
        if (!canvas) return;
        var ctx = canvas.getContext('2d');
        if (leadsOverTimeChart) leadsOverTimeChart.destroy();

        var labels = data.map(function(item) { return item.date; });
        var values = data.map(function(item) { return item.count; });

        var gradient = ctx.createLinearGradient(0, 0, 0, 300);
        gradient.addColorStop(0, 'rgba(99, 102, 241, 0.4)');
        gradient.addColorStop(1, 'rgba(99, 102, 241, 0.0)');

        leadsOverTimeChart = new Chart(ctx, {
            type: 'line',
            data: {
                labels: labels,
                datasets: [{
                    label: 'Leads Generated',
                    data: values,
                    borderColor: 'rgba(99, 102, 241, 1)',
                    borderWidth: 2.5,
                    backgroundColor: gradient,
                    fill: true,
                    tension: 0.35,
                    pointBackgroundColor: 'rgba(99, 102, 241, 1)',
                    pointHoverRadius: 6
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { 
                    legend: { display: false },
                    tooltip: getPercentTooltipCallback()
                },
                scales: {
                    y: { beginAtZero: true, ticks: { stepSize: 1 }, grid: { color: '#f1f5f9' } },
                    x: { grid: { display: false } }
                }
            }
        });
    }

    function renderSalesAssignmentsChart(data) {
        var canvas = document.getElementById('chart-sales-assignments');
        if (!canvas) return;
        var emptyState = document.getElementById('chart-sales-assignments-empty');
        if (salesAssignmentsChart) {
            salesAssignmentsChart.destroy();
            salesAssignmentsChart = null;
        }

        var rows = Array.isArray(data) ? data : [];
        var hasAssignments = rows.some(function(row) { return Number(row.total) > 0; });
        canvas.style.display = hasAssignments ? 'block' : 'none';
        if (emptyState) emptyState.style.display = hasAssignments ? 'none' : 'flex';
        if (!hasAssignments) return;

        var chartHeight = Math.max(280, Math.min(520, rows.length * 52 + 110));
        canvas.parentElement.style.height = chartHeight + 'px';
        salesAssignmentsChart = new Chart(canvas.getContext('2d'), {
            type: 'bar',
            data: {
                labels: rows.map(function(row) { return row.rep_name; }),
                datasets: [
                    {
                        label: 'Manager / direct',
                        data: rows.map(function(row) { return Number(row.manager_assigned) || 0; }),
                        backgroundColor: 'rgba(79, 70, 229, 0.82)',
                        borderColor: '#4f46e5',
                        borderWidth: 1,
                        borderRadius: 5,
                        barThickness: 24
                    },
                    {
                        label: 'OOO fallback',
                        data: rows.map(function(row) { return Number(row.ooo_assigned) || 0; }),
                        backgroundColor: 'rgba(245, 158, 11, 0.82)',
                        borderColor: '#d97706',
                        borderWidth: 1,
                        borderRadius: 5,
                        barThickness: 24
                    }
                ]
            },
            options: {
                indexAxis: 'y',
                responsive: true,
                maintainAspectRatio: false,
                interaction: { mode: 'index', intersect: false },
                plugins: {
                    legend: { position: 'bottom', labels: { usePointStyle: true, boxWidth: 8 } },
                    tooltip: {
                        callbacks: {
                            footer: function(items) {
                                return 'Total assigned: ' + items.reduce(function(total, item) {
                                    return total + (Number(item.raw) || 0);
                                }, 0);
                            }
                        }
                    }
                },
                scales: {
                    x: {
                        beginAtZero: true,
                        stacked: true,
                        ticks: { precision: 0, stepSize: 1 },
                        grid: { color: '#f1f5f9' },
                        title: { display: true, text: 'Leads assigned during selected period' }
                    },
                    y: { stacked: true, grid: { display: false } }
                }
            }
        });
    }

    function renderPropertyTypeChart(data) {
        var canvas = document.getElementById('chart-property-types');
        if (!canvas) return;
        var ctx = canvas.getContext('2d');
        if (propertyTypeChart) propertyTypeChart.destroy();

        var labels = data.map(function(item) { return item.property_type + ' (' + item.count + ')'; });
        var values = data.map(function(item) { return item.count; });

        propertyTypeChart = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: labels,
                datasets: [{
                    label: 'Leads Volume',
                    data: values,
                    backgroundColor: 'rgba(139, 92, 246, 0.7)',
                    borderColor: 'rgba(139, 92, 246, 1)',
                    borderWidth: 1.5,
                    borderRadius: 6
                }]
            },
            options: {
                indexAxis: 'y',
                responsive: true,
                maintainAspectRatio: false,
                plugins: { 
                    legend: { display: false },
                    tooltip: getPercentTooltipCallback()
                },
                scales: { x: { beginAtZero: true, ticks: { stepSize: 1 } } }
            }
        });
    }

    function renderRoomConfigChart(data) {
        var canvas = document.getElementById('chart-room-configs');
        if (!canvas) return;
        var ctx = canvas.getContext('2d');
        if (roomConfigChart) roomConfigChart.destroy();

        var labels = data.map(function(item) { return item.num_rooms + ' (' + item.count + ')'; });
        var values = data.map(function(item) { return item.count; });

        roomConfigChart = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: labels,
                datasets: [{
                    label: 'Leads Volume',
                    data: values,
                    backgroundColor: 'rgba(236, 72, 153, 0.7)',
                    borderColor: 'rgba(236, 72, 153, 1)',
                    borderWidth: 1.5,
                    borderRadius: 6
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { 
                    legend: { display: false },
                    tooltip: getPercentTooltipCallback()
                },
                scales: { y: { beginAtZero: true, ticks: { stepSize: 1 } } }
            }
        });
    }

    function renderStudioChart(data) {
        var canvas = document.getElementById('chart-studio-dist');
        if (!canvas) return;
        var ctx = canvas.getContext('2d');
        if (studioChart) studioChart.destroy();

        var labels = data.map(function(item) { return item.studio + ' (' + item.count + ')'; });
        var values = data.map(function(item) { return item.count; });

        var palette = ['#6366f1', '#8b5cf6', '#3b82f6', '#ec4899', '#f59e0b', '#10b981'];

        studioChart = new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: labels,
                datasets: [{
                    data: values,
                    backgroundColor: palette.slice(0, data.length),
                    borderWidth: 2,
                    borderColor: '#ffffff',
                    cutout: '70%'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { 
                    legend: { position: 'right', labels: { boxWidth: 12, font: { size: 11 } } },
                    tooltip: getPercentTooltipCallback()
                }
            },
            plugins: [centerTextPlugin]
        });
    }

    function renderOverallQualityChart(data) {
        var canvas = document.getElementById('chart-overall-quality');
        if (!canvas) return;
        var ctx = canvas.getContext('2d');
        if (overallQualityChart) overallQualityChart.destroy();

        var labels = Object.keys(data);
        var values = Object.values(data);

        var formattedLabels = labels.map(function(lbl) {
            var val = data[lbl] || 0;
            return lbl + ' (' + val + ')';
        });

        var palette = [];
        labels.forEach(function(lbl) {
            if (lbl === 'Qualified · Plan Received') {
                palette.push('#10b981');
            } else if (lbl === 'Qualified · Plan Pending') {
                palette.push('#f59e0b');
            } else if (lbl === 'Unqualified') {
                palette.push('#ef4444');
            } else {
                palette.push('#94a3b8');
            }
        });

        overallQualityChart = new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: formattedLabels,
                datasets: [{
                    data: values,
                    backgroundColor: palette,
                    borderWidth: 2,
                    borderColor: '#ffffff',
                    cutout: '70%'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { 
                    legend: { 
                        position: 'right', 
                        labels: { boxWidth: 12, font: { size: 11 } } 
                    },
                    tooltip: getPercentTooltipCallback()
                }
            },
            plugins: [centerTextPlugin]
        });
    }
});

// Global tab switcher
function switchDashboardTab(evt, tabId) {
    var $ = jQuery;
    $('.idlm-tab-content').removeClass('active');
    $('.idlm-tab-link').removeClass('active');
    $('#' + tabId).addClass('active');
    $(evt.currentTarget).addClass('active');

    // Trigger a resize event to recalculate chart dimensions for previously hidden tabs
    setTimeout(function() {
        if (typeof window.dispatchEvent === 'function') {
            window.dispatchEvent(new Event('resize'));
        }
    }, 50);
}
