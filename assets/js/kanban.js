jQuery(document).ready(function($) {
    var activeDragCard = null;
    var activeWonLeadId = null;
    var activeQuoteLeadId = null;
    var activeLostLeadId = null;
    var kanbanLeadLookup = {};
    var dragPointerX = null;
    var dragScrollFrame = null;
    var kanbanRequest = null;

    // Load Kanban board on startup
    loadKanbanBoard();

    // Trigger board reload on filter changes
    $('#idlm-kanban-filter-search').on('input', debounce(function() {
        loadKanbanBoard();
    }, 300));

    $('#idlm-kanban-filter-date-basis, #idlm-kanban-filter-date-range, #idlm-kanban-filter-rep, #idlm-kanban-filter-source, #idlm-kanban-filter-call-status, #idlm-kanban-filter-quality').on('change', function() {
        loadKanbanBoard();
    });

    // Native HTML5 Drag and Drop events delegated to the document
    $(document).on('dragstart', '.idlm-kanban-card', function(e) {
        activeDragCard = $(this);
        startKanbanDragScroll();
        $(this).addClass('dragging');
        if (e.originalEvent.dataTransfer) {
            e.originalEvent.dataTransfer.setData('text/plain', $(this).data('id'));
            e.originalEvent.dataTransfer.effectAllowed = 'move';
        }
    });

    $(document).on('dragend', '.idlm-kanban-card', function() {
        $(this).removeClass('dragging');
        $('.idlm-kanban-column').removeClass('drag-over');
        activeDragCard = null;
        stopKanbanDragScroll();
    });

    $(document).on('dragover', function(e) {
        if (activeDragCard && e.originalEvent) {
            dragPointerX = e.originalEvent.clientX;
        }
    });

    function startKanbanDragScroll() {
        stopKanbanDragScroll();

        function scrollBoardAtEdge() {
            var board = document.getElementById('idlm-kanban-board');
            if (!activeDragCard || !board || dragPointerX === null) {
                dragScrollFrame = window.requestAnimationFrame(scrollBoardAtEdge);
                return;
            }

            var bounds = board.getBoundingClientRect();
            var edgeSize = Math.min(120, Math.max(60, bounds.width * 0.12));
            var scrollAmount = 0;

            if (dragPointerX >= bounds.left && dragPointerX < bounds.left + edgeSize) {
                scrollAmount = -Math.ceil(4 + 20 * (bounds.left + edgeSize - dragPointerX) / edgeSize);
            } else if (dragPointerX <= bounds.right && dragPointerX > bounds.right - edgeSize) {
                scrollAmount = Math.ceil(4 + 20 * (dragPointerX - (bounds.right - edgeSize)) / edgeSize);
            }

            if (scrollAmount) {
                board.scrollLeft += scrollAmount;
            }
            dragScrollFrame = window.requestAnimationFrame(scrollBoardAtEdge);
        }

        dragScrollFrame = window.requestAnimationFrame(scrollBoardAtEdge);
    }

    function stopKanbanDragScroll() {
        if (dragScrollFrame !== null) {
            window.cancelAnimationFrame(dragScrollFrame);
            dragScrollFrame = null;
        }
        dragPointerX = null;
    }

    $(document).on('dragover', '.idlm-kanban-column', function(e) {
        e.preventDefault();
        $(this).addClass('drag-over');
        if (e.originalEvent.dataTransfer) {
            e.originalEvent.dataTransfer.dropEffect = 'move';
        }
    });

    $(document).on('dragleave', '.idlm-kanban-column', function() {
        $(this).removeClass('drag-over');
    });

    $(document).on('drop', '.idlm-kanban-column', function(e) {
        e.preventDefault();
        stopKanbanDragScroll();
        $(this).removeClass('drag-over');
        if (!activeDragCard) return;

        var leadId = activeDragCard.data('id');
        var oldStage = activeDragCard.closest('.idlm-kanban-column').data('stage');
        var newStage = $(this).data('stage');

        if (oldStage === newStage) return;

        if (newStage === 'Quote Shared') {
            openKanbanQuoteModal(leadId);
        } else if (newStage === 'Won') {
            openKanbanWonModal(leadId, newStage);
        } else if (newStage === 'Lost') {
            openKanbanLostModal(leadId);
        } else {
            updateLeadStage(leadId, newStage);
        }
    });

    // A quote value is mandatory before a lead can enter Quote Shared.
    $('#idlm-kanban-quote-save').click(function() {
        var quoteValue = $('#idlm-kanban-quote-input').val();
        if (!quoteValue || isNaN(quoteValue) || parseFloat(quoteValue) <= 0) {
            alert('Please enter a quote value greater than zero.');
            $('#idlm-kanban-quote-input').focus();
            return;
        }

        var leadId = activeQuoteLeadId;
        var quoteFile = $('#idlm-kanban-quote-file').val();
        closeKanbanQuoteModal();
        updateLeadStage(leadId, 'Quote Shared', undefined, undefined, undefined, undefined, parseFloat(quoteValue), quoteFile);
    });

    $('#idlm-kanban-quote-cancel, #idlm-kanban-quote-close').click(function() {
        closeKanbanQuoteModal();
        loadKanbanBoard();
    });

    function openKanbanQuoteModal(leadId) {
        activeQuoteLeadId = leadId;
        var lead = kanbanLeadLookup[String(leadId)] || {};
        var existingQuoteValue = parseFloat(lead.quote_value || 0);
        $('#idlm-kanban-quote-input').val(existingQuoteValue > 0 ? existingQuoteValue : '');
        setKanbanQuoteUploadState(lead.quote_file || '');
        $('#idlm-kanban-quote-modal').addClass('active');
        $('body').addClass('idlm-modal-open');
        $('#idlm-kanban-quote-input').focus();
    }

    function closeKanbanQuoteModal() {
        $('#idlm-kanban-quote-modal').removeClass('active');
        $('body').removeClass('idlm-modal-open');
        activeQuoteLeadId = null;
    }

    // Custom Kanban Won Modal Event Handlers
    $('#idlm-kanban-deal-save').click(function() {
        var dealVal = $('#idlm-kanban-deal-input').val();
        if (!dealVal || isNaN(dealVal) || parseFloat(dealVal) <= 0) {
            alert('Please enter a deal value greater than zero.');
            return;
        }

        var discountPercentage = $('#idlm-kanban-discount-input').val();
        if (discountPercentage === '' || isNaN(discountPercentage) || parseFloat(discountPercentage) < 0 || parseFloat(discountPercentage) > 100) {
            alert('Please enter a discount percentage between 0 and 100.');
            $('#idlm-kanban-discount-input').focus();
            return;
        }

        var propertyLocation = $.trim($('#idlm-kanban-property-location-input').val());

        var leadId = activeWonLeadId;
        closeKanbanWonModal();
        updateLeadStage(leadId, 'Won', parseFloat(dealVal), undefined, parseFloat(discountPercentage), propertyLocation);
    });

    $('#idlm-kanban-deal-cancel, #idlm-kanban-deal-close').click(function() {
        closeKanbanWonModal();
        loadKanbanBoard(); // Refresh board to restore card position
    });

    function openKanbanWonModal(leadId, stageName) {
        activeWonLeadId = leadId;
        var lead = kanbanLeadLookup[String(leadId)] || {};
        $('#idlm-kanban-deal-input').val('');
        $('#idlm-kanban-discount-input').val('');
        $('#idlm-kanban-property-location-input').val(lead.property_location || '');
        $('#idlm-kanban-deal-modal').addClass('active');
        $('body').addClass('idlm-modal-open');
    }

    function closeKanbanWonModal() {
        $('#idlm-kanban-deal-modal').removeClass('active');
        $('body').removeClass('idlm-modal-open');
        activeWonLeadId = null;
    }

    // A reason is mandatory before a lead can be moved to Lost.
    $('#idlm-kanban-lost-save').click(function() {
        var lostRemark = $.trim($('#idlm-kanban-lost-input').val());
        var lostReason = $('#idlm-kanban-lost-reason').val();
        if (!lostReason) {
            alert('Please select a lost reason.');
            $('#idlm-kanban-lost-reason').focus();
            return;
        }
        if (!lostRemark) {
            alert('Please enter a remark explaining why the lead was lost.');
            $('#idlm-kanban-lost-input').focus();
            return;
        }

        var leadId = activeLostLeadId;
        closeKanbanLostModal();
        updateLeadStage(leadId, 'Lost', undefined, lostRemark, undefined, undefined, undefined, undefined, lostReason);
    });

    $('#idlm-kanban-lost-cancel, #idlm-kanban-lost-close').click(function() {
        closeKanbanLostModal();
        loadKanbanBoard();
    });

    function openKanbanLostModal(leadId) {
        activeLostLeadId = leadId;
        $('#idlm-kanban-lost-reason').val('');
        $('#idlm-kanban-lost-input').val('');
        $('#idlm-kanban-lost-modal').addClass('active');
        $('body').addClass('idlm-modal-open');
        $('#idlm-kanban-lost-reason').focus();
    }

    function closeKanbanLostModal() {
        $('#idlm-kanban-lost-modal').removeClass('active');
        $('body').removeClass('idlm-modal-open');
        activeLostLeadId = null;
    }

    // Function to submit AJAX Stage updates on drag-and-drop
    function updateLeadStage(leadId, newStage, dealValue, lostRemark, discountPercentage, propertyLocation, quoteValue, quoteFile, lostReason) {
        var postData = {
            action: 'idlm_quick_edit_lead',
            nonce: idlm_vars.nonce,
            lead_id: leadId,
            field: 'stage',
            value: newStage
        };
        if (dealValue !== undefined) {
            postData.deal_value = dealValue;
        }
        if (lostRemark !== undefined) {
            postData.lost_remark = lostRemark;
        }
        if (lostReason !== undefined) {
            postData.lost_reason = lostReason;
        }
        if (discountPercentage !== undefined) {
            postData.discount_percentage = discountPercentage;
        }
        if (propertyLocation !== undefined) {
            postData.property_location = propertyLocation;
        }
        if (quoteValue !== undefined) {
            postData.quote_value = quoteValue;
        }
        if (quoteFile !== undefined) {
            postData.quote_file = quoteFile;
        }

        if (kanbanRequest && kanbanRequest.readyState !== 4) {
            kanbanRequest.abort();
        }

        kanbanRequest = $.ajax({
            url: idlm_vars.ajax_url,
            type: 'POST',
            data: postData,
            success: function(response) {
                if (response.success) {
                    if (typeof window.idlmShowToast === 'function') {
                        window.idlmShowToast('Stage updated successfully!', 'success');
                    }
                    loadKanbanBoard();
                } else {
                    if (typeof window.idlmShowToast === 'function') {
                        window.idlmShowToast(response.data.message || 'Stage update failed.', 'error');
                    }
                    loadKanbanBoard();
                }
            },
            error: function() {
                if (typeof window.idlmShowToast === 'function') {
                    window.idlmShowToast('Server error updating stage.', 'error');
                }
                loadKanbanBoard();
            }
        });
    }

    // AJAX fetching of leads grouped by stage
    function loadKanbanBoard() {
        var $board = $('#idlm-kanban-board');
        if (!$board.length) return;

        // Show inline column loaders
        $('.idlm-kanban-column-body').html('<div style="text-align:center; padding:30px; color:var(--idlm-text-muted); font-size:12px;"><span class="spinner is-active" style="float:none; margin:0 5px 0 0; vertical-align:middle;"></span>Loading...</div>');

        var filters = {
            action: 'idlm_get_kanban_leads',
            nonce: idlm_vars.nonce,
            search: $('#idlm-kanban-filter-search').val(),
            date_range: $('#idlm-kanban-filter-date-range').val(),
            date_basis: $('#idlm-kanban-filter-date-basis').val(),
            rep_id: $('#idlm-kanban-filter-rep').val(),
            source: $('#idlm-kanban-filter-source').val(),
            call_status: $('#idlm-kanban-filter-call-status').val(),
            quality: $('#idlm-kanban-filter-quality').val()
        };

        $.ajax({
            url: idlm_vars.ajax_url,
            type: 'POST',
            data: filters,
            success: function(response) {
                if (response.success) {
                    renderKanbanLeads(response.data.leads);
                    renderKanbanStats(response.data.stats || {});
                } else {
                    if (typeof window.idlmShowToast === 'function') {
                        window.idlmShowToast(response.data.message || 'Failed to load Kanban board.', 'error');
                    }
                }
            },
            error: function(xhr, status) {
                if (status === 'abort') return;
                if (typeof window.idlmShowToast === 'function') {
                    window.idlmShowToast('Server error loading Kanban board.', 'error');
                }
            },
            complete: function() {
                kanbanRequest = null;
            }
        });
    }

    function renderKanbanStats(stats) {
        var currencySymbol = $('#idlm-kanban-board').data('currency-symbol') || '';
        $('#idlm-stat-assigned-active').text(Number(stats.assigned_filtered) || 0);
        $('#idlm-stat-followups-due').text(Number(stats.followups_due) || 0);
        $('#idlm-stat-qualified-pipeline').text(Number(stats.qualified_filtered) || 0);
        $('#idlm-stat-pipeline-value').text(currencySymbol + formatPipelineValue(Number(stats.open_pipeline_value) || 0));
        $('#idlm-stat-won-month').text(Number(stats.won_filtered) || 0);
        $('#idlm-stat-won-revenue').text(currencySymbol + formatPipelineValue(Number(stats.won_revenue_filtered) || 0));

        $('.idlm-kanban-stat-attention').toggleClass('has-value', (Number(stats.followups_due) || 0) > 0);
    }

    // Render cards inside columns dynamically
    function renderKanbanLeads(leadsData) {
        kanbanLeadLookup = {};

        // Loop over each stage column present in the board
        $('.idlm-kanban-column').each(function() {
            var $col = $(this);
            var stage = $col.data('stage');
            var $body = $col.find('.idlm-kanban-column-body');
            var $count = $col.find('.idlm-kanban-column-count');
            var $pipelineValue = $col.find('.idlm-kanban-column-pipeline-value');
            var totalSource = $col.attr('data-total-source') || 'none';
            
            $body.empty();
            
            var leads = leadsData[stage] || [];
            $count.text(leads.length);
            if (totalSource === 'quote_value' || totalSource === 'deal_value') {
                var stageTotal = 0;
                $.each(leads, function(i, lead) {
                    var monetaryValue = parseFloat(lead[totalSource] || 0);
                    if (!isNaN(monetaryValue) && monetaryValue > 0) {
                        stageTotal += monetaryValue;
                    }
                });
                var totalLabel = totalSource === 'quote_value' ? 'Quote Total' : 'Deal Total';
                $pipelineValue
                    .text(totalLabel + ': ' + ($('#idlm-kanban-board').data('currency-symbol') || '') + formatPipelineValue(stageTotal))
                    .show();
            } else {
                $pipelineValue.hide().text('');
            }

            if (leads.length === 0) {
                $body.append('<div class="idlm-kanban-column-empty">No leads in this stage</div>');
                return;
            }

            $.each(leads, function(i, lead) {
                kanbanLeadLookup[String(lead.id)] = lead;

                var name = (lead.first_name || '') + ' ' + (lead.last_name || '');
                name = $.trim(name) || 'Unnamed Lead';

                var dupBadge = lead.is_duplicate === "1" || lead.is_duplicate === 1 ? '<span class="idlm-badge idlm-badge-warning" style="font-size:9px; padding:1px 4px; margin-left:4px;">DUP</span>' : '';
                
                // Construct badges
                var badgesHtml = '';
                if (lead.source) {
                    badgesHtml += '<span class="idlm-kanban-card-badge idlm-badge-' + lead.source.toLowerCase().replace(/[^a-z0-9]/g, '') + '">' + lead.source + '</span>';
                }
                if (lead.budget) {
                    badgesHtml += '<span class="idlm-kanban-card-badge" style="background:var(--idlm-gold-soft); color:var(--idlm-gold-dark); border:1px solid #fbdba3;">' + lead.budget + '</span>';
                }
                if (lead.call_status) {
                    badgesHtml += '<span class="idlm-kanban-card-badge" style="background:#f1f5f9; color:#475569; border:1px solid #cbd5e1;">' + lead.call_status + '</span>';
                }

                // Construct indicators
                var indicatorsHtml = '';
                var hasTasks = parseInt(lead.pending_tasks_count || 0) > 0;
                var hasOverdueTasks = parseInt(lead.overdue_tasks_count || 0) > 0;

                if (hasTasks) {
                    if (hasOverdueTasks) {
                        indicatorsHtml += '<span class="idlm-kanban-task-pill overdue" title="Overdue tasks present">⏳ Overdue</span>';
                    } else {
                        indicatorsHtml += '<span class="idlm-kanban-task-pill pending" title="Pending tasks count">📋 ' + lead.pending_tasks_count + ' Tasks</span>';
                    }
                } else {
                    indicatorsHtml += '<span></span>';
                }

                // Rep avatar bubble
                var repHtml = '';
                if (lead.assigned_rep_id && parseInt(lead.assigned_rep_id) > 0 && lead.rep_name) {
                    var initials = getInitials(lead.rep_name);
                    repHtml = '<div class="idlm-kanban-card-rep" title="Assigned to ' + lead.rep_name + '"><span class="idlm-kanban-card-rep-icon">' + initials + '</span></div>';
                } else {
                    repHtml = '<div class="idlm-kanban-card-rep" title="Unassigned"><span class="idlm-kanban-card-rep-icon" style="background:#f1f5f9; color:#94a3b8;">?</span></div>';
                }

                var cardHtml = 
                    '<div class="idlm-kanban-card" draggable="true" data-id="' + lead.id + '">' +
                        '<h4 class="idlm-kanban-card-title">' + name + dupBadge + '</h4>' +
                        '<div class="idlm-kanban-card-meta">' +
                            '<div>' + (lead.email ? lead.email : 'No Email') + '</div>' +
                            '<div>' + (lead.phone ? lead.phone : 'No Phone') + '</div>' +
                        '</div>' +
                        '<div class="idlm-kanban-card-badges">' + badgesHtml + '</div>' +
                        '<div class="idlm-kanban-card-indicators">' +
                            repHtml +
                            indicatorsHtml +
                        '</div>' +
                    '</div>';

                var $card = $(cardHtml);

                // Clicking the title opens the lead drawer modal using the global function from admin-app.js
                $card.find('.idlm-kanban-card-title').on('click', function(e) {
                    e.stopPropagation();
                    if (typeof window.openLeadDrawer === 'function') {
                        window.openLeadDrawer(lead.id);
                    }
                });

                $body.append($card);
            });
        });
    }

    // Helper functions
    function getInitials(name) {
        var parts = name.split(' ');
        var initials = '';
        $.each(parts, function(i, part) {
            if (part) initials += part[0].toUpperCase();
        });
        return initials.substring(0, 2);
    }

    function formatPipelineValue(value) {
        return Number(value || 0).toLocaleString(undefined, {
            minimumFractionDigits: 0,
            maximumFractionDigits: 2
        });
    }

    function setKanbanQuoteUploadState(quoteFile) {
        var $hiddenInput = $('#idlm-kanban-quote-file');
        var $preview = $('#idlm-kanban-quote-preview');
        var $uploadButton = $hiddenInput.siblings('.idlm-direct-upload-trigger');
        var $fileInput = $hiddenInput.siblings('.idlm-file-input');

        $hiddenInput.val(quoteFile || '');
        $fileInput.val('');

        if (quoteFile) {
            var $link = $('<a>', {
                href: quoteFile,
                target: '_blank',
                class: 'idlm-file-link',
                text: '📄 View File'
            }).css({ color: '#c99644', fontWeight: 500, textDecoration: 'none' });
            var $removeButton = $('<button>', {
                type: 'button',
                class: 'idlm-remove-file-btn',
                text: 'Remove'
            }).attr({
                'data-input-id': 'idlm-kanban-quote-file',
                'data-preview-id': 'idlm-kanban-quote-preview'
            }).css({ background: 'none', border: 'none', color: '#ef4444', cursor: 'pointer', fontSize: '12px', padding: 0, marginLeft: '5px', fontWeight: 500 });
            $preview.empty().append($link, $removeButton);
            $uploadButton.text('Change File');
        } else {
            $preview.text('No file uploaded');
            $uploadButton.text('Upload Quote');
        }
    }

    function debounce(func, wait) {
        var timeout;
        return function() {
            var context = this, args = arguments;
            clearTimeout(timeout);
            timeout = setTimeout(function() {
                func.apply(context, args);
            }, wait);
        };
    }
});
