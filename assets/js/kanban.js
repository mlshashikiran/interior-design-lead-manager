jQuery(document).ready(function($) {
    var activeDragCard = null;
    var activeWonLeadId = null;
    var activeQuoteLeadId = null;
    var activeLostLeadId = null;
    var activeMoveLeadId = null;
    var kanbanLeadLookup = {};
    var dragPointerX = null;
    var dragScrollFrame = null;
    var kanbanRequest = null;
    var boardRequest = null;
    var columnStates = Object.create(null);
    var columnScrollPositions = Object.create(null);
    var boardFilters = null;
    var boardRequestSequence = 0;
    var kanbanFiltersReturnFocus = null;
    var kanbanDialogTrigger = null;
    var pendingFocusLeadId = null;
    var pendingAnnouncement = '';
    var kanbanPhoneMedia = window.matchMedia('(max-width: 782px)');
    var activeMobileStage = String($('.idlm-kanban-stage-tab').first().data('stage') || '');

    function openKanbanDialog(selector, trigger) {
        var dialog = document.querySelector(selector);
        if (!dialog || !window.IDLMDialog) return;
        $(dialog).find('[aria-invalid="true"]').each(function() { window.IDLMDialog.clearFieldError(this); });
        window.IDLMDialog.open(dialog, {
            trigger: trigger || kanbanDialogTrigger || (activeDragCard && activeDragCard.length ? activeDragCard.get(0) : document.activeElement)
        });
    }

    function showKanbanFieldError(selector, message) {
        return window.IDLMDialog ? window.IDLMDialog.setFieldError(selector, message) : false;
    }

    function activateMobileStage(stage, options) {
        options = options || {};
        var $tabs = $('.idlm-kanban-stage-tab');
        var $targetTab = $tabs.filter(function() { return String($(this).data('stage')) === String(stage); }).first();
        if (!$targetTab.length) $targetTab = $tabs.first();
        if (!$targetTab.length) return;

        activeMobileStage = String($targetTab.data('stage') || '');
        $tabs.each(function() {
            var selected = this === $targetTab.get(0);
            $(this).toggleClass('is-active', selected).attr('aria-selected', selected ? 'true' : 'false').attr('tabindex', selected ? '0' : '-1');
        });

        $('.idlm-kanban-column').each(function() {
            var columnStage = String($(this).data('stage'));
            var $body = $(this).find('.idlm-kanban-column-body');
            if ($body.get(0).clientHeight) columnScrollPositions[columnStage] = $body.scrollTop();
            var active = columnStage === activeMobileStage;
            $(this).toggleClass('is-mobile-active', active);
            if (kanbanPhoneMedia.matches) {
                $(this).attr({role: 'tabpanel', 'aria-hidden': active ? 'false' : 'true'});
            } else {
                $(this).removeAttr('role aria-hidden');
            }
            if (active || !kanbanPhoneMedia.matches) $body.scrollTop(columnScrollPositions[columnStage] || 0);
        });

        $('.idlm-kanban-card-drag-handle').attr('draggable', kanbanPhoneMedia.matches ? 'false' : 'true');
        $('#idlm-kanban-board').attr('aria-label', kanbanPhoneMedia.matches ? 'Sales pipeline stage list' : 'Sales pipeline board');
        if (options.focus) $targetTab.trigger('focus');
        if (options.announce) {
            var count = Number($targetTab.find('.idlm-kanban-stage-tab-count').text()) || 0;
            $('#idlm-kanban-live-status').text(activeMobileStage + ' stage selected. ' + count + ' lead' + (count === 1 ? '' : 's') + '.');
        }
    }

    function syncKanbanResponsiveMode() {
        activateMobileStage(activeMobileStage);
    }

    $('#idlm-kanban-stage-tabs').on('click', '.idlm-kanban-stage-tab', function() {
        activateMobileStage($(this).data('stage'), {announce: true});
    }).on('keydown', '.idlm-kanban-stage-tab', function(event) {
        if (!['ArrowLeft', 'ArrowRight', 'Home', 'End'].includes(event.key)) return;
        event.preventDefault();
        var $tabs = $('.idlm-kanban-stage-tab');
        var index = $tabs.index(this);
        if (event.key === 'Home') index = 0;
        if (event.key === 'End') index = $tabs.length - 1;
        if (event.key === 'ArrowLeft') index = (index - 1 + $tabs.length) % $tabs.length;
        if (event.key === 'ArrowRight') index = (index + 1) % $tabs.length;
        var $next = $tabs.eq(index);
        activateMobileStage($next.data('stage'), {focus: true, announce: true});
    });

    if (typeof kanbanPhoneMedia.addEventListener === 'function') {
        kanbanPhoneMedia.addEventListener('change', syncKanbanResponsiveMode);
    } else if (typeof kanbanPhoneMedia.addListener === 'function') {
        kanbanPhoneMedia.addListener(syncKanbanResponsiveMode);
    }
    syncKanbanResponsiveMode();

    function updateKanbanFilterCount() {
        var count = 0;
        $('#idlm-kanban-filters').find('input, select').each(function() {
            var id = this.id;
            var value = $(this).val();
            var isDefault = (id === 'idlm-kanban-filter-date-basis' && value === 'assigned_at') ||
                (id === 'idlm-kanban-filter-quality' && value === 'Qualified') ||
                (id === 'idlm-kanban-sort' && value === 'newest');
            if (value && !isDefault) count += 1;
        });
        $('.idlm-kanban-filter-count').text(count).attr('aria-label', count + ' active filter' + (count === 1 ? '' : 's')).prop('hidden', count === 0);
    }

    function openKanbanFilters() {
        kanbanFiltersReturnFocus = document.activeElement;
        $('#idlm-kanban-filters-backdrop').prop('hidden', false);
        $('#idlm-kanban-filters').addClass('is-open').attr('aria-hidden', 'false');
        $('#idlm-kanban-open-filters').attr('aria-expanded', 'true');
        $('body').addClass('idlm-filters-open');
        window.setTimeout(function() { $('#idlm-kanban-close-filters').trigger('focus'); }, 20);
    }

    function closeKanbanFilters() {
        $('#idlm-kanban-filters').removeClass('is-open').attr('aria-hidden', 'true');
        $('#idlm-kanban-filters-backdrop').prop('hidden', true);
        $('#idlm-kanban-open-filters').attr('aria-expanded', 'false');
        $('body').removeClass('idlm-filters-open');
        if (kanbanFiltersReturnFocus) $(kanbanFiltersReturnFocus).trigger('focus');
    }

    // Load Kanban board on startup
    loadKanbanBoard();

    // Trigger board reload on filter changes
    $('#idlm-kanban-filter-search').on('input', debounce(function() {
        updateKanbanFilterCount();
        loadKanbanBoard(true);
    }, 300));

    $('#idlm-kanban-filter-date-basis, #idlm-kanban-filter-date-range, #idlm-kanban-filter-rep, #idlm-kanban-filter-source, #idlm-kanban-filter-call-status, #idlm-kanban-filter-quality, #idlm-kanban-filter-attention, #idlm-kanban-sort').on('change', function() {
        updateKanbanFilterCount();
        loadKanbanBoard(true);
    });

    $('#idlm-kanban-open-filters').on('click', openKanbanFilters);
    $('#idlm-kanban-close-filters, #idlm-kanban-filters-backdrop').on('click', closeKanbanFilters);
    $('#idlm-kanban-clear-filters').on('click', function() {
        $('#idlm-kanban-filter-search').val('');
        $('#idlm-kanban-filter-date-basis').val('assigned_at');
        $('#idlm-kanban-filter-date-range, #idlm-kanban-filter-rep, #idlm-kanban-filter-source, #idlm-kanban-filter-call-status, #idlm-kanban-filter-attention').val('');
        $('#idlm-kanban-filter-quality').val('Qualified');
        $('#idlm-kanban-sort').val('newest');
        updateKanbanFilterCount();
        loadKanbanBoard(true);
    });
    $('#idlm-kanban-stale-leads-pill').on('click', function() {
        var isActive = $('#idlm-kanban-filter-attention').val() === 'stale_updates';
        if (isActive) {
            $('#idlm-kanban-filter-attention').val('');
            updateKanbanFilterCount();
            loadKanbanBoard(true);
            return;
        }
        $('#idlm-kanban-filter-search').val('');
        $('#idlm-kanban-filter-date-basis').val('assigned_at');
        $('#idlm-kanban-filter-date-range, #idlm-kanban-filter-rep, #idlm-kanban-filter-source, #idlm-kanban-filter-call-status').val('');
        $('#idlm-kanban-filter-quality').val('');
        $('#idlm-kanban-filter-attention').val('stale_updates');
        $('#idlm-kanban-sort').val('newest');
        updateKanbanFilterCount();
        loadKanbanBoard(true);
    });

    $(document).on('keydown', function(event) {
        if (!$('#idlm-kanban-filters').hasClass('is-open')) return;
        if (event.key === 'Escape') {
            closeKanbanFilters();
            return;
        }
        if (event.key === 'Tab') {
            var $focusable = $('#idlm-kanban-filters').find('button, input, select, [tabindex]:not([tabindex="-1"])').filter(':visible');
            if (!$focusable.length) return;
            var first = $focusable.get(0);
            var last = $focusable.get($focusable.length - 1);
            if (event.shiftKey && document.activeElement === first) {
                event.preventDefault();
                last.focus();
            } else if (!event.shiftKey && document.activeElement === last) {
                event.preventDefault();
                first.focus();
            }
        }
    });
    updateKanbanFilterCount();

    $(document).on('click', '.idlm-kanban-card-open', function() {
        var leadId = $(this).closest('.idlm-kanban-card').data('id');
        if (typeof window.openLeadDrawer === 'function') {
            window.openLeadDrawer(leadId);
        }
    });

    $(document).on('click', '.idlm-kanban-card-move', function() {
        var leadId = $(this).closest('.idlm-kanban-card').data('id');
        openKanbanMoveModal(leadId, this);
    });

    // Native HTML5 drag and drop remains a pointer enhancement on the explicit handle.
    $(document).on('dragstart', '.idlm-kanban-card-drag-handle', function(e) {
        if (kanbanPhoneMedia.matches) {
            e.preventDefault();
            return;
        }
        activeDragCard = $(this).closest('.idlm-kanban-card');
        startKanbanDragScroll();
        activeDragCard.addClass('dragging');
        if (e.originalEvent.dataTransfer) {
            e.originalEvent.dataTransfer.setData('text/plain', activeDragCard.data('id'));
            e.originalEvent.dataTransfer.effectAllowed = 'move';
        }
    });

    $(document).on('dragend', '.idlm-kanban-card-drag-handle', function() {
        if (activeDragCard) activeDragCard.removeClass('dragging');
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

        requestStageMove(leadId, newStage, activeDragCard.find('.idlm-kanban-card-drag-handle').get(0));
    });

    function openKanbanMoveModal(leadId, trigger) {
        activeMoveLeadId = leadId;
        kanbanDialogTrigger = trigger || document.activeElement;
        var currentStage = String((kanbanLeadLookup[String(leadId)] || {}).stage || '');
        $('#idlm-kanban-move-stage').val('').find('option').prop('disabled', false).filter(function() {
            return $(this).val() === currentStage;
        }).prop('disabled', true);
        openKanbanDialog('#idlm-kanban-move-modal', kanbanDialogTrigger);
    }

    $('#idlm-kanban-move-confirm').on('click', function() {
        var newStage = $('#idlm-kanban-move-stage').val();
        if (!newStage) {
            return showKanbanFieldError('#idlm-kanban-move-stage', 'Choose a destination stage.');
        }
        var leadId = activeMoveLeadId;
        var trigger = kanbanDialogTrigger;
        activeMoveLeadId = null;
        window.IDLMDialog.close('#idlm-kanban-move-modal', {reason: 'continue', restoreFocus: false});
        window.setTimeout(function() { requestStageMove(leadId, newStage, trigger); }, 0);
    });

    $('#idlm-kanban-move-modal').on('idlm:dialogclose', function() {
        activeMoveLeadId = null;
    });

    function requestStageMove(leadId, newStage, trigger) {
        var lead = kanbanLeadLookup[String(leadId)] || {};
        var currentStage = String(lead.stage || $('.idlm-kanban-card[data-id="' + leadId + '"]').closest('.idlm-kanban-column').data('stage') || '');
        if (!newStage || currentStage === newStage) {
            if (trigger) $(trigger).trigger('focus');
            return;
        }
        kanbanDialogTrigger = trigger || document.activeElement;
        if (newStage === 'Quote Shared') {
            openKanbanQuoteModal(leadId);
        } else if (newStage === 'Won') {
            openKanbanWonModal(leadId, newStage);
        } else if (newStage === 'Lost') {
            openKanbanLostModal(leadId);
        } else {
            updateLeadStage(leadId, newStage);
        }
    }

    // A quote value is mandatory before a lead can enter Quote Shared.
    $('#idlm-kanban-quote-save').click(function() {
        var quoteValue = $('#idlm-kanban-quote-input').val();
        if (!quoteValue || isNaN(quoteValue) || parseFloat(quoteValue) <= 0) {
            return showKanbanFieldError('#idlm-kanban-quote-input', 'Enter a quote value greater than zero.');
        }

        var leadId = activeQuoteLeadId;
        var quoteFile = $('#idlm-kanban-quote-file').val();
        activeQuoteLeadId = null;
        closeKanbanQuoteModal('submit');
        updateLeadStage(leadId, 'Quote Shared', undefined, undefined, undefined, undefined, parseFloat(quoteValue), quoteFile);
    });

    $('#idlm-kanban-quote-cancel, #idlm-kanban-quote-close').click(function() {
        closeKanbanQuoteModal('cancel');
    });

    function openKanbanQuoteModal(leadId) {
        activeQuoteLeadId = leadId;
        var lead = kanbanLeadLookup[String(leadId)] || {};
        var existingQuoteValue = parseFloat(lead.quote_value || 0);
        $('#idlm-kanban-quote-input').val(existingQuoteValue > 0 ? existingQuoteValue : '');
        setKanbanQuoteUploadState(lead.quote_file || '');
        openKanbanDialog('#idlm-kanban-quote-modal');
    }

    function closeKanbanQuoteModal(reason) {
        window.IDLMDialog.close('#idlm-kanban-quote-modal', {reason: reason || 'programmatic'});
    }

    $('#idlm-kanban-quote-modal').on('idlm:dialogclose', function() {
        if (activeQuoteLeadId !== null) { activeQuoteLeadId = null; loadKanbanBoard(); }
    });

    // Custom Kanban Won Modal Event Handlers
    $('#idlm-kanban-deal-save').click(function() {
        var dealVal = $('#idlm-kanban-deal-input').val();
        if (!dealVal || isNaN(dealVal) || parseFloat(dealVal) <= 0) {
            return showKanbanFieldError('#idlm-kanban-deal-input', 'Enter a deal value greater than zero.');
        }

        var discountPercentage = $('#idlm-kanban-discount-input').val();
        if (discountPercentage === '' || isNaN(discountPercentage) || parseFloat(discountPercentage) < 0 || parseFloat(discountPercentage) > 100) {
            return showKanbanFieldError('#idlm-kanban-discount-input', 'Enter a discount percentage between 0 and 100.');
        }

        var propertyLocation = $.trim($('#idlm-kanban-property-location-input').val());

        var leadId = activeWonLeadId;
        activeWonLeadId = null;
        closeKanbanWonModal('submit');
        updateLeadStage(leadId, 'Won', parseFloat(dealVal), undefined, parseFloat(discountPercentage), propertyLocation);
    });

    $('#idlm-kanban-deal-cancel, #idlm-kanban-deal-close').click(function() {
        closeKanbanWonModal('cancel');
    });

    function openKanbanWonModal(leadId, stageName) {
        activeWonLeadId = leadId;
        var lead = kanbanLeadLookup[String(leadId)] || {};
        $('#idlm-kanban-deal-input').val('');
        $('#idlm-kanban-discount-input').val('');
        $('#idlm-kanban-property-location-input').val(lead.property_location || '');
        openKanbanDialog('#idlm-kanban-deal-modal');
    }

    function closeKanbanWonModal(reason) {
        window.IDLMDialog.close('#idlm-kanban-deal-modal', {reason: reason || 'programmatic'});
    }

    $('#idlm-kanban-deal-modal').on('idlm:dialogclose', function() {
        if (activeWonLeadId !== null) { activeWonLeadId = null; loadKanbanBoard(); }
    });

    // A reason is mandatory before a lead can be moved to Lost.
    $('#idlm-kanban-lost-save').click(function() {
        var lostRemark = $.trim($('#idlm-kanban-lost-input').val());
        var lostReason = $('#idlm-kanban-lost-reason').val();
        if (!lostReason) {
            return showKanbanFieldError('#idlm-kanban-lost-reason', 'Select a lost reason.');
        }
        if (!lostRemark) {
            return showKanbanFieldError('#idlm-kanban-lost-input', 'Enter a remark explaining why the lead was lost.');
        }

        var leadId = activeLostLeadId;
        activeLostLeadId = null;
        closeKanbanLostModal('submit');
        updateLeadStage(leadId, 'Lost', undefined, lostRemark, undefined, undefined, undefined, undefined, lostReason);
    });

    $('#idlm-kanban-lost-cancel, #idlm-kanban-lost-close').click(function() {
        closeKanbanLostModal('cancel');
    });

    function openKanbanLostModal(leadId) {
        activeLostLeadId = leadId;
        $('#idlm-kanban-lost-reason').val('');
        $('#idlm-kanban-lost-input').val('');
        openKanbanDialog('#idlm-kanban-lost-modal');
    }

    function closeKanbanLostModal(reason) {
        window.IDLMDialog.close('#idlm-kanban-lost-modal', {reason: reason || 'programmatic'});
    }

    $('#idlm-kanban-lost-modal').on('idlm:dialogclose', function() {
        if (activeLostLeadId !== null) { activeLostLeadId = null; loadKanbanBoard(); }
    });

    // Function to submit AJAX Stage updates on drag-and-drop
    function updateLeadStage(leadId, newStage, dealValue, lostRemark, discountPercentage, propertyLocation, quoteValue, quoteFile, lostReason) {
        var leadBeforeMove = kanbanLeadLookup[String(leadId)] || {};
        var leadName = $.trim((leadBeforeMove.first_name || '') + ' ' + (leadBeforeMove.last_name || '')) || 'Lead';
        var oldStage = String(leadBeforeMove.stage || $('.idlm-kanban-card[data-id="' + leadId + '"]').closest('.idlm-kanban-column').data('stage') || 'previous stage');
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
            idlmKanbanMove: true,
            data: postData,
            success: function(response) {
                if (response.success) {
                    if (typeof window.idlmShowToast === 'function') {
                        window.idlmShowToast('Stage updated successfully!', 'success');
                    }
                    pendingFocusLeadId = Number(leadId);
                    pendingAnnouncement = leadName + ' moved from ' + oldStage + ' to ' + newStage + '. Board counts updated.';
                    activeMobileStage = String(newStage);
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

    // Each scroll area owns its continuation and permits only one request at a time.
    $(document).on('ajaxSuccess.idlmKanban', function(event, xhr, settings, response) {
        if (!response || !response.success || settings.idlmKanbanMove) return;
        var data = String(settings.data || '');
        if (/(?:^|&)action=idlm_(?:save_lead_details|quick_edit_lead)(?:&|$)/.test(data)) loadKanbanBoard();
    });
    $('.idlm-kanban-column-body').on('scroll', function() {
        if (!this.clientHeight) return;
        columnScrollPositions[String($(this).closest('.idlm-kanban-column').data('stage'))] = this.scrollTop;
        if (this.scrollTop > 0 && this.scrollHeight - this.scrollTop - this.clientHeight < 180) {
            loadKanbanColumn($(this).closest('.idlm-kanban-column'), false);
        }
    });
    $('#idlm-kanban-board').on('click', '.idlm-kanban-load-more', function() {
        var $col = $(this).closest('.idlm-kanban-column');
        if ($(this).attr('data-reload-board') === '1') loadKanbanBoard(true);
        else loadKanbanColumn($col, true);
    });

    function getKanbanFilters() {
        return {
            action: 'idlm_get_kanban_leads',
            per_column: '1',
            nonce: idlm_vars.nonce,
            search: $('#idlm-kanban-filter-search').val(),
            date_range: $('#idlm-kanban-filter-date-range').val(),
            date_basis: $('#idlm-kanban-filter-date-basis').val(),
            rep_id: $('#idlm-kanban-filter-rep').val(),
            source: $('#idlm-kanban-filter-source').val(),
            call_status: $('#idlm-kanban-filter-call-status').val(),
            quality: $('#idlm-kanban-filter-quality').val(),
            attention: $('#idlm-kanban-filter-attention').val(),
            sort: $('#idlm-kanban-sort').val()
        };
    }

    function updateColumnLoader($col) {
        var state = columnStates[String($col.data('stage'))];
        if (!state) return;
        var $body = $col.find('.idlm-kanban-column-body');
        var focused = $body.find('.idlm-kanban-load-more').is(':focus');
        $body.find('.idlm-kanban-column-loader').remove();
        $body.attr('aria-busy', state.loading ? 'true' : 'false');
        var loaded = $body.children('.idlm-kanban-card').length;
        var total = Number($col.find('.idlm-kanban-column-count').text()) || 0;
        var message = state.error || (state.loading ? 'Loading more leads…' : (state.next ? loaded + ' of ' + total + ' leads loaded' : 'All ' + loaded + ' leads loaded'));
        var $loader = $('<div class="idlm-kanban-column-loader"><p role="status" aria-live="polite"></p></div>');
        $loader.find('p').text(message);
        if (state.next || state.error) {
            $('<button type="button" class="idlm-btn idlm-btn-secondary idlm-kanban-load-more"></button>')
                .text(state.error ? 'Retry' : 'Load more')
                .attr('aria-label', (state.error ? 'Retry loading ' : 'Load more ') + String($col.data('stage')) + ' leads')
                .attr('data-reload-board', state.reloadBoard ? '1' : '0')
                .prop('disabled', state.loading).appendTo($loader);
        }
        $body.append($loader);
        if (focused) {
            var $next = $loader.find('button');
            if ($next.length && !$next.prop('disabled')) $next.trigger('focus');
            else $body.trigger('focus');
        }
    }

    function loadKanbanColumn($col, retry) {
        var stage = String($col.data('stage'));
        var state = columnStates[stage];
        if (!state || state.loading || !state.next || (state.error && !retry)) return;
        var sequence = boardRequestSequence;
        state.loading = true;
        state.error = '';
        updateColumnLoader($col);
        state.request = $.ajax({
            url: idlm_vars.ajax_url,
            type: 'POST',
            data: $.extend({}, boardFilters, {column_stage: stage, cursor: state.next}),
            success: function(response) {
                if (sequence !== boardRequestSequence) return;
                if (!response.success) {
                    state.error = (response.data || {}).message || 'Unable to load more leads.';
                    return;
                }
                state.next = ((response.data.stage_pages || {})[stage] || {}).next_cursor || '';
                renderKanbanLeads(response.data.leads, response.data.stage_summaries || {}, stage, true);
            },
            error: function(xhr, status) {
                if (sequence !== boardRequestSequence || status === 'abort') return;
                state.error = xhr.status === 400 ? 'This column has expired. Refresh the pipeline to continue.' : 'Unable to load more leads. Try again.';
                // An expired token needs a new board scope, not repeated requests with the old token.
                state.reloadBoard = xhr.status === 400;
            },
            complete: function() {
                if (sequence !== boardRequestSequence) return;
                state.loading = false;
                state.request = null;
                finishColumnRefresh($col);
            }
        });
    }

    function finishColumnRefresh($col) {
        var state = columnStates[String($col.data('stage'))];
        var $body = $col.find('.idlm-kanban-column-body');
        updateColumnLoader($col);
        if (!state.error && state.next && $body.children('.idlm-kanban-card').length < state.restoreCount) {
            loadKanbanColumn($col, false);
            return;
        }
        if (state.restoreCount) {
            columnScrollPositions[String($col.data('stage'))] = state.scrollTop;
            $body.scrollTop(state.scrollTop);
            state.restoreCount = 0;
        }
        if (pendingFocusLeadId) {
            var $focus = $body.find('.idlm-kanban-card[data-id="' + pendingFocusLeadId + '"] .idlm-kanban-card-move');
            if ($focus.length) { pendingFocusLeadId = null; $focus.trigger('focus'); }
            else {
                var refreshing = false;
                $.each(columnStates, function(stage, column) { if (column.loading || column.restoreCount) refreshing = true; });
                if (!refreshing) {
                    pendingFocusLeadId = null;
                    $('.idlm-kanban-column.is-mobile-active .idlm-kanban-column-body').trigger('focus');
                }
            }
        }
    }

    function loadKanbanBoard(reset) {
        if (!$('#idlm-kanban-board').length) return;
        var sequence = ++boardRequestSequence;
        if (boardRequest) boardRequest.abort();
        $.each(columnStates, function(stage, state) { if (state.request) state.request.abort(); });
        var previous = columnStates;
        columnStates = Object.create(null);
        if (reset) columnScrollPositions = Object.create(null);
        kanbanLeadLookup = {};
        boardFilters = getKanbanFilters();
        $('.idlm-kanban-column').each(function() {
            var $col = $(this), stage = String($col.data('stage'));
            var $body = $col.find('.idlm-kanban-column-body');
            columnStates[stage] = {
                next: '', loading: true, error: '', request: null,
                restoreCount: reset ? 0 : $body.children('.idlm-kanban-card').length,
                scrollTop: reset ? 0 : (columnScrollPositions[stage] || $body.scrollTop())
            };
            if (reset || !previous[stage]) $body.empty().scrollTop(0);
            updateColumnLoader($col);
        });

        boardRequest = $.ajax({
            url: idlm_vars.ajax_url,
            type: 'POST',
            data: boardFilters,
            success: function(response) {
                if (sequence !== boardRequestSequence) return;
                if (response.success) {
                    $.each(columnStates, function(stage, state) {
                        state.next = ((response.data.stage_pages || {})[stage] || {}).next_cursor || '';
                    });
                    renderKanbanLeads(response.data.leads, response.data.stage_summaries || {});
                    renderKanbanStats(response.data.stats || {});
                } else {
                    $.each(columnStates, function(stage, state) { state.error = (response.data || {}).message || 'Unable to load leads.'; state.reloadBoard = true; });
                }
            },
            error: function(xhr, status) {
                if (sequence !== boardRequestSequence || status === 'abort') return;
                $.each(columnStates, function(stage, state) { state.error = 'Unable to load leads. Try again.'; state.reloadBoard = true; });
            },
            complete: function() {
                if (sequence !== boardRequestSequence) return;
                boardRequest = null;
                $('.idlm-kanban-column').each(function() {
                    columnStates[String($(this).data('stage'))].loading = false;
                    finishColumnRefresh($(this));
                });
            }
        });
    }

    function renderKanbanStats(stats) {
        var currencySymbol = $('#idlm-kanban-board').data('currency-symbol') || '';
        $('#idlm-stat-overall-leads').text(Number(stats.total_filtered) || 0);
        $('#idlm-stat-followups-due').text(Number(stats.followups_due) || 0);
        updateKanbanStaleLeadsPill(stats.stale_lead_count);
        $('#idlm-stat-pipeline-value').text(currencySymbol + formatPipelineValue(Number(stats.open_pipeline_value) || 0));
        $('#idlm-stat-won-month').text(Number(stats.won_current_month) || 0);
        $('#idlm-stat-won-revenue').text(currencySymbol + formatPipelineValue(Number(stats.won_revenue_current_month) || 0));

        $('.idlm-kanban-stat-attention').toggleClass('has-value', (Number(stats.followups_due) || 0) > 0);
    }

    function updateKanbanStaleLeadsPill(count) {
        var $pill = $('#idlm-kanban-stale-leads-pill');
        count = Number(count) || 0;
        $pill.find('.idlm-pending-text strong').text(count);
        $pill.attr('title', count + ' pipeline leads need a fresh update');
        $pill.attr('aria-label', 'Show ' + count + ' pipeline leads that need a fresh update');
        $pill.toggle(count > 0);
    }

    // Render cards inside columns dynamically
    function renderKanbanLeads(leadsData, summaries, onlyStage, append) {

        // Loop over each stage column present in the board
        $('.idlm-kanban-column').each(function() {
            var $col = $(this);
            var stage = $col.data('stage');
            if (onlyStage !== undefined && String(stage) !== onlyStage) return;
            var $body = $col.find('.idlm-kanban-column-body');
            var $count = $col.find('.idlm-kanban-column-count');
            var $pipelineValue = $col.find('.idlm-kanban-column-pipeline-value');
            var totalSource = $col.attr('data-total-source') || 'none';
            
            if (!append) $body.empty();
            else $body.find('.idlm-kanban-column-empty, .idlm-kanban-column-loader').remove();
            
            var leads = leadsData[stage] || [];
            var summary = summaries[stage] || {};
            var stageCount = Number(summary.count) || leads.length;
            $count.text(stageCount);
            $('.idlm-kanban-stage-tab').filter(function() { return String($(this).data('stage')) === String(stage); }).find('.idlm-kanban-stage-tab-count').text(stageCount).attr('aria-label', stageCount + ' lead' + (stageCount === 1 ? '' : 's'));
            if (totalSource === 'quote_value' || totalSource === 'deal_value') {
                var stageTotal = 0;
                $.each(leads, function(i, lead) {
                    var monetaryValue = parseFloat(lead[totalSource] || 0);
                    if (!isNaN(monetaryValue) && monetaryValue > 0) {
                        stageTotal += monetaryValue;
                    }
                });
                if (summary[totalSource] !== undefined) stageTotal = Number(summary[totalSource]) || 0;
                var totalLabel = totalSource === 'quote_value' ? 'Quote Total' : 'Deal Total';
                $pipelineValue
                    .text(totalLabel + ': ' + ($('#idlm-kanban-board').data('currency-symbol') || '') + formatPipelineValue(stageTotal))
                    .show();
            } else {
                $pipelineValue.hide().text('');
            }

            if (leads.length === 0 && !append) {
                $body.append('<div class="idlm-kanban-column-empty">No leads in this stage</div>');
                return;
            }

            $.each(leads, function(i, lead) {
                if (append && $body.children('.idlm-kanban-card[data-id="' + Number(lead.id) + '"]').length) return;
                kanbanLeadLookup[String(lead.id)] = lead;

                var name = (lead.first_name || '') + ' ' + (lead.last_name || '');
                name = $.trim(name) || 'Unnamed Lead';

                var safeName = escapeKanbanHtml(name);
                var dupBadge = lead.is_duplicate === "1" || lead.is_duplicate === 1 ? '<span class="idlm-badge idlm-badge-warning idlm-kanban-duplicate-badge">Duplicate</span>' : '';
                
                // Construct badges
                var badgesHtml = '';
                if (lead.source) {
                    badgesHtml += '<span class="idlm-kanban-card-badge idlm-badge-' + lead.source.toLowerCase().replace(/[^a-z0-9]/g, '') + '">' + escapeKanbanHtml(lead.source) + '</span>';
                }
                if (lead.budget) {
                    badgesHtml += '<span class="idlm-kanban-card-badge idlm-kanban-card-badge-budget">' + escapeKanbanHtml(lead.budget) + '</span>';
                }
                if (lead.call_status) {
                    badgesHtml += '<span class="idlm-kanban-card-badge idlm-kanban-card-badge-status">' + escapeKanbanHtml(lead.call_status) + '</span>';
                }
                // Construct indicators
                var indicatorsHtml = '';
                var hasTasks = parseInt(lead.pending_tasks_count || 0) > 0;
                var hasOverdueTasks = parseInt(lead.overdue_tasks_count || 0) > 0;
                var needsUpdate = parseInt(lead.needs_update || 0) === 1;

                if (hasTasks) {
                    if (hasOverdueTasks) {
                        indicatorsHtml += '<span class="idlm-kanban-task-pill overdue" title="Overdue tasks present"><span class="dashicons dashicons-clock" aria-hidden="true"></span> Overdue</span>';
                    } else {
                        indicatorsHtml += '<span class="idlm-kanban-task-pill pending" title="Pending tasks count"><span class="dashicons dashicons-clipboard" aria-hidden="true"></span> ' + lead.pending_tasks_count + ' Tasks</span>';
                    }
                }
                if (needsUpdate) {
                    indicatorsHtml += '<span class="idlm-kanban-task-pill needs-update" title="No representative update in the last 7 days"><span class="dashicons dashicons-warning" aria-hidden="true"></span> Needs update</span>';
                }

                // Rep avatar bubble
                var repHtml = '';
                if (lead.assigned_rep_id && parseInt(lead.assigned_rep_id) > 0 && lead.rep_name) {
                    var initials = getInitials(lead.rep_name);
                    repHtml = '<span class="idlm-kanban-card-rep" title="Assigned to ' + escapeKanbanHtml(lead.rep_name) + '"><span class="idlm-kanban-card-rep-icon">' + escapeKanbanHtml(initials) + '</span><span class="screen-reader-text">Assigned to ' + escapeKanbanHtml(lead.rep_name) + '</span></span>';
                } else {
                    repHtml = '<span class="idlm-kanban-card-rep" title="Unassigned"><span class="idlm-kanban-card-rep-icon idlm-kanban-card-rep-icon-unassigned" aria-hidden="true">?</span><span class="screen-reader-text">Unassigned</span></span>';
                }

                var cardHtml = 
                    '<article class="idlm-kanban-card" data-id="' + Number(lead.id) + '" data-stage="' + escapeKanbanHtml(lead.stage || stage) + '">' +
                        '<button type="button" class="idlm-kanban-card-open" aria-label="Open lead ' + safeName + '">' +
                        '<span class="idlm-kanban-card-title">' + safeName + dupBadge + '</span>' +
                        '<span class="idlm-kanban-card-meta">' +
                            '<span>' + escapeKanbanHtml(lead.email || 'No email') + '</span>' +
                            '<span>' + escapeKanbanHtml(lead.phone || 'No phone') + '</span>' +
                        '</span>' +
                        '<span class="idlm-kanban-card-badges">' + badgesHtml + '</span>' +
                        '</button>' +
                        '<footer class="idlm-kanban-card-actions">' +
                            '<button type="button" class="idlm-kanban-card-drag-handle" draggable="true" aria-label="Drag ' + safeName + ' to another stage" title="Drag to another stage"><span class="dashicons dashicons-move" aria-hidden="true"></span></button>' +
                            '<button type="button" class="idlm-kanban-card-move" aria-label="Move ' + safeName + ' to another stage"><span class="dashicons dashicons-randomize" aria-hidden="true"></span><span>Move to stage</span></button>' +
                            '<span class="idlm-kanban-card-footer-meta">' +
                                indicatorsHtml +
                                repHtml +
                            '</span>' +
                        '</footer>' +
                    '</article>';

                var $card = $(cardHtml);
                $body.append($card);
            });
        });

        activateMobileStage(activeMobileStage);

        if (pendingAnnouncement) {
            $('#idlm-kanban-live-status').text(pendingAnnouncement);
            pendingAnnouncement = '';
        }
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

    function escapeKanbanHtml(value) {
        return $('<div>').text(value == null ? '' : String(value)).html();
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
                text: 'View file'
            }).css({ color: 'var(--idlm-brand-gold)', fontWeight: 500, textDecoration: 'none' });
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
