jQuery(document).ready(function($) {
    var leadsTableRequest = null;
    var leadsActiveOnly = false;
    var filtersReturnFocus = null;

    function updateActiveFilterCount() {
        var count = 0;
        $('.idlm-filters-panel').find('input, select').each(function() {
            var id = this.id;
            var value = $(this).val();
            var isDefault = (id === 'filter-date-basis' && value === 'assigned_at') ||
                (id === 'filter-followup' && value === 'action_required');
            if (value && !isDefault) count += 1;
        });
        var $count = $('.idlm-filter-count');
        $count.text(count).attr('aria-label', count + ' active filter' + (count === 1 ? '' : 's')).prop('hidden', count === 0);
    }

    function openFiltersPanel() {
        var $panel = $('#idlm-lead-filters');
        if (!$panel.length) return;
        filtersReturnFocus = document.activeElement;
        $('#idlm-filters-backdrop').prop('hidden', false);
        $panel.addClass('is-open').attr('aria-hidden', 'false');
        $('#idlm-open-filters').attr('aria-expanded', 'true');
        $('body').addClass('idlm-filters-open');
        window.setTimeout(function() { $('#idlm-close-filters').trigger('focus'); }, 20);
    }

    function closeFiltersPanel() {
        var $panel = $('#idlm-lead-filters');
        $panel.removeClass('is-open').attr('aria-hidden', 'true');
        $('#idlm-filters-backdrop').prop('hidden', true);
        $('#idlm-open-filters').attr('aria-expanded', 'false');
        $('body').removeClass('idlm-filters-open');
        if (filtersReturnFocus) $(filtersReturnFocus).trigger('focus');
    }

    function ensurePresalesActivityModal() {
        if ($('#idlm-presales-activity-modal').length) return;

        var statuses = Array.isArray(idlm_vars.call_statuses) ? idlm_vars.call_statuses : [];
        var options = statuses.map(function(status) {
            return '<option value="' + $('<div>').text(status).html() + '">' + $('<div>').text(status).html() + '</option>';
        }).join('');
        $('body').append(
            '<div class="idlm-modal idlm-presales-activity-modal" id="idlm-presales-activity-modal" role="dialog" aria-modal="true" aria-labelledby="idlm-presales-activity-title">' +
                '<div class="idlm-modal-content">' +
                    '<div class="idlm-modal-header">' +
                        '<div><h2 id="idlm-presales-activity-title">Log outreach</h2><p>Record the customer touch and its outcome.</p></div>' +
                        '<button type="button" class="idlm-close idlm-presales-activity-cancel" aria-label="Close">&times;</button>' +
                    '</div>' +
                    '<form id="idlm-presales-activity-form">' +
                        '<div class="idlm-form-group idlm-presales-call-outcome"><label for="idlm-presales-call-status">Call outcome <span aria-hidden="true">*</span></label>' +
                            '<select id="idlm-presales-call-status" class="idlm-input"><option value="">Select an outcome</option>' + options + '</select></div>' +
                        '<div class="idlm-form-group idlm-presales-callback-field"><label for="idlm-presales-callback-time">Callback date and time <span aria-hidden="true">*</span></label>' +
                            '<input type="datetime-local" id="idlm-presales-callback-time" class="idlm-input"></div>' +
                        '<div class="idlm-form-group"><label for="idlm-presales-activity-note">Note <span class="idlm-muted">(optional)</span></label>' +
                            '<textarea id="idlm-presales-activity-note" class="idlm-input" rows="4" placeholder="Add useful context for the next action"></textarea></div>' +
                        '<div class="idlm-presales-activity-error" role="alert"></div>' +
                        '<div class="idlm-presales-activity-footer"><button type="button" class="idlm-btn idlm-btn-secondary idlm-presales-activity-cancel">Cancel</button>' +
                            '<button type="submit" class="idlm-btn idlm-btn-primary">Save activity</button></div>' +
                    '</form>' +
                '</div>' +
            '</div>'
        );
    }

    function openPresalesActivityModal(kind, onSubmit) {
        ensurePresalesActivityModal();
        var $modal = $('#idlm-presales-activity-modal');
        var isCall = kind === 'call';
        $modal.data('submit-handler', onSubmit).data('kind', kind);
        $modal.find('#idlm-presales-activity-title').text(isCall ? 'Log outbound call' : 'Log WhatsApp');
        $modal.find('.idlm-presales-call-outcome').toggle(isCall);
        $modal.find('#idlm-presales-call-status').val('');
        $modal.find('#idlm-presales-callback-time').val('');
        $modal.find('#idlm-presales-activity-note').val('');
        $modal.find('.idlm-presales-callback-field, .idlm-presales-activity-error').hide();
        if (isCall && $modal.find('#idlm-presales-call-status option').length <= 1) {
            $modal.find('.idlm-presales-activity-error').text('No call outcomes are configured. Ask an administrator to add one.').show();
        }
        $modal.addClass('active');
        $('body').addClass('idlm-modal-open');
        window.setTimeout(function() {
            (isCall ? $modal.find('#idlm-presales-call-status') : $modal.find('#idlm-presales-activity-note')).trigger('focus');
        }, 50);
    }

    function closePresalesActivityModal() {
        $('#idlm-presales-activity-modal').removeClass('active').removeData('submit-handler');
        if (!$('.idlm-modal.active, .idlm-drawer.active, #idlm-import-modal.active').length) {
            $('body').removeClass('idlm-modal-open');
        }
    }

    $(document).on('change', '#idlm-presales-call-status', function() {
        var needsCallback = $(this).val() === 'Call Back Requested';
        $('.idlm-presales-callback-field').toggle(needsCallback);
        if (needsCallback) $('#idlm-presales-callback-time').trigger('focus');
    });

    $(document).on('click', '.idlm-presales-activity-cancel', closePresalesActivityModal);

    $(document).on('submit', '#idlm-presales-activity-form', function(e) {
        e.preventDefault();
        var $modal = $('#idlm-presales-activity-modal');
        var kind = $modal.data('kind');
        var callStatus = kind === 'call' ? $modal.find('#idlm-presales-call-status').val() : '';
        var callbackTime = callStatus === 'Call Back Requested' ? $modal.find('#idlm-presales-callback-time').val() : '';
        var $error = $modal.find('.idlm-presales-activity-error');
        if (kind === 'call' && !callStatus) {
            $error.text('Select a call outcome.').show();
            return;
        }
        if (callStatus === 'Call Back Requested' && !callbackTime) {
            $error.text('Choose a callback date and time.').show();
            return;
        }
        var submitHandler = $modal.data('submit-handler');
        if (typeof submitHandler === 'function') {
            submitHandler({
                call_status: callStatus,
                callback_time: callbackTime,
                note: $.trim($modal.find('#idlm-presales-activity-note').val())
            });
        }
        closePresalesActivityModal();
    });

    $(document).on('keydown', function(e) {
        if (e.key === 'Escape' && $('#idlm-presales-activity-modal').hasClass('active')) {
            closePresalesActivityModal();
        }
    });

    function submitPresalesTouch($button, leadId, kind, activity, onSuccess) {
        $button.prop('disabled', true);
        $.post(idlm_vars.ajax_url, {
            action: 'idlm_log_presales_touch', nonce: idlm_vars.nonce,
            lead_id: leadId, kind: kind, note: activity.note,
            call_status: activity.call_status, callback_time: activity.callback_time
        }).done(function(response) {
            if (response.success) {
                showToast(response.data.message, 'success');
                onSuccess();
            } else {
                showToast(response.data.message || 'Unable to log outreach.', 'error');
                $button.prop('disabled', false);
            }
        }).fail(function() {
            showToast('Server error logging outreach.', 'error');
            $button.prop('disabled', false);
        });
    }

    $(document).on('click', '.idlm-log-presales-touch', function() {
        var $button = $(this);
        var kind = $button.data('kind');
        var $panel = $button.closest('.idlm-presales-compliance');
        var leadId = $panel.data('lead-id');
        var reason = '';
        var note = '';
        if (kind === 'early_close') {
            reason = $panel.find('.idlm-early-close-reason').val();
            note = $.trim($panel.find('.idlm-early-close-note').val());
            if (!reason) { alert('Select an early-close reason.'); return; }
            if (note.length < 10) { alert('Document the evidence in at least 10 characters.'); return; }
            if (!window.confirm('Close this lead early as Unqualified? This action will be recorded in the audit log.')) return;
        } else {
            openPresalesActivityModal(kind, function(activity) {
                submitPresalesTouch($button, leadId, kind, activity, function() {
                    openLeadDrawer(leadId);
                    if (typeof window.idlmLoadLeadsTable === 'function') window.idlmLoadLeadsTable(1);
                });
            });
            return;
        }
        $button.prop('disabled', true);
        $.post(idlm_vars.ajax_url, {
            action: 'idlm_log_presales_touch', nonce: idlm_vars.nonce,
            lead_id: leadId, kind: kind, reason: reason, note: note,
            call_status: typeof callStatus === 'undefined' ? '' : callStatus,
            callback_time: typeof callbackTime === 'undefined' ? '' : callbackTime
        }).done(function(response) {
            if (response.success) {
                if (typeof window.idlmShowToast === 'function') window.idlmShowToast(response.data.message, 'success');
                openLeadDrawer(leadId);
                if (typeof window.idlmLoadLeadsTable === 'function') window.idlmLoadLeadsTable(1);
            } else {
                alert(response.data.message || 'Unable to log follow-up.');
                $button.prop('disabled', false);
            }
        }).fail(function() {
            alert('Server error logging follow-up.');
            $button.prop('disabled', false);
        });
    });

    $(document).on('click', '.idlm-presales-row-touch', function(e) {
        e.preventDefault();
        e.stopPropagation();
        var $button = $(this);
        var leadId = $button.data('lead-id');
        var kind = $button.data('kind');
        openPresalesActivityModal(kind, function(activity) {
            submitPresalesTouch($button, leadId, kind, activity, function() {
                loadLeadsTable(currentPage);
                updateFloatingTasksCount();
            });
        });
    });

    $(document).on('click', '.idlm-presales-decision', function() {
        var $button = $(this);
        var $panel = $button.closest('.idlm-presales-compliance');
        var leadId = $panel.data('lead-id');
        var decision = $button.data('decision');
        var handoffRemark = '';
        if (decision === 'qualify' && String($panel.data('has-floor-plan')) !== '1') {
            handoffRemark = window.prompt('No floor plan is attached. Add a remark for the sales handoff:') || '';
            handoffRemark = $.trim(handoffRemark);
            if (!handoffRemark) {
                alert('A handoff remark is required when qualifying without a floor plan.');
                return;
            }
        }
        var confirmText = decision === 'qualify'
            ? 'Qualify this lead and send it to the qualified handoff workflow?'
            : 'Close this lead as Unqualified — Unable to Contact?';
        if (!window.confirm(confirmText)) return;
        $button.prop('disabled', true);
        $.post(idlm_vars.ajax_url, {
            action: 'idlm_presales_decision', nonce: idlm_vars.nonce,
            lead_id: leadId, decision: decision, handoff_remark: handoffRemark
        }).done(function(response) {
            if (response.success) {
                showToast(response.data.message, 'success');
                closeLeadDrawer();
                loadLeadsTable(1);
                updateFloatingTasksCount();
            } else {
                alert(response.data.message || 'Unable to save the lead decision.');
                $button.prop('disabled', false);
            }
        }).fail(function() {
            alert('Server error saving the lead decision.');
            $button.prop('disabled', false);
        });
    });

    var currentPage = 1;
    var leadsPageCursors = { 1: '' };
    var leadsSortBy = '';
    var leadsSortOrder = 'asc';
    var activeCallbackSelect = null;
    var activeWonSelect = null;
    var activeQuoteSelect = null;
    var activeLostSelect = null;
    var activeLostBulkAction = null;
    window.idlmHasUnsavedChanges = false;
    window.idlmChangedFields = {};

    $(document).on('click', '.idlm-run-fallback-now', function() {
        var $button = $(this);
        $button.prop('disabled', true).text('Running…');
        $.post(idlm_vars.ajax_url, {
            action: 'idlm_run_fallback_now',
            nonce: idlm_vars.nonce
        }).done(function(response) {
            if (response.success) {
                showToast(response.data.message + ' Open exceptions: ' + response.data.exception_count, 'success');
            } else {
                showToast((response.data && response.data.message) || 'Fallback routing failed.', 'error');
            }
        }).fail(function() {
            showToast('Fallback routing request failed.', 'error');
        }).always(function() {
            $button.prop('disabled', false).text('Run Fallback Now');
        });
    });

    // Load table on start if present
    if ($('#idlm-leads-table').length > 0) {
        loadLeadsTable(1);
    }

    $('#idlm-leads-table').on('click keydown', 'th.idlm-sortable-column', function(event) {
        if (event.type === 'keydown' && event.key !== 'Enter' && event.key !== ' ') {
            return;
        }
        event.preventDefault();

        var $header = $(this);
        var requestedSort = $header.data('sort');

        if (leadsSortBy === requestedSort) {
            leadsSortOrder = leadsSortOrder === 'asc' ? 'desc' : 'asc';
        } else {
            leadsSortBy = requestedSort;
            leadsSortOrder = 'asc';
        }

        $('#idlm-leads-table th.idlm-sortable-column').attr('aria-sort', 'none');
        $header.attr('aria-sort', leadsSortOrder === 'asc' ? 'ascending' : 'descending');
        loadLeadsTable(1);
    });

    // Filter changes trigger table refresh
    $('.idlm-filters-panel select').change(function() {
        leadsActiveOnly = false;
        updateActiveFilterCount();
        loadLeadsTable(1);
    });

    // Search input changes trigger table refresh with a small debounce
    var searchTimeout;
    $('#filter-search').on('input keyup', function() {
        leadsActiveOnly = false;
        updateActiveFilterCount();
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(function() {
            loadLeadsTable(1);
        }, 300);
    });

    // Clear all filters
    $('#btn-clear-filters').click(function() {
        leadsActiveOnly = false;
        $('#filter-search').val('');
        $('#filter-date-basis').val('assigned_at');
        $('#filter-date-range').val('');
        $('#filter-stage').val('');
        $('#filter-source').val('');
        $('#filter-rep').val('');
        $('#filter-call-status').val('');
        $('#filter-quality').val('');
        $('#filter-followup').val('');
        $('#idlm-secondary-filters-container').slideUp(200);
        $('#btn-toggle-more-filters').removeClass('active');
        updateActiveFilterCount();
        loadLeadsTable(1);
    });

    $('#idlm-open-filters').on('click', openFiltersPanel);
    $('#idlm-close-filters, #idlm-filters-backdrop').on('click', closeFiltersPanel);
    $(document).on('keydown', function(event) {
        if (event.key === 'Escape' && $('#idlm-lead-filters').hasClass('is-open')) {
            closeFiltersPanel();
            return;
        }
        if (event.key === 'Tab' && $('#idlm-lead-filters').hasClass('is-open')) {
            var $focusable = $('#idlm-lead-filters').find('button, input, select, [tabindex]:not([tabindex="-1"])').filter(':visible');
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
    updateActiveFilterCount();

    // Toggle secondary advanced filters panel
    $('#btn-toggle-more-filters').click(function() {
        var $container = $('#idlm-secondary-filters-container');
        var $btn = $(this);
        $container.slideToggle(200, function() {
            if ($container.is(':visible')) {
                $btn.addClass('active');
            } else {
                $btn.removeClass('active');
            }
        });
    });

    // Metric Summary card clicks trigger filtration
    $('.idlm-metric[data-filter]').click(function() {
        var filter = $(this).data('filter');
        leadsActiveOnly = filter !== 'New';
        
        // Refine the current filtered population so the opened table continues
        // to match the number displayed on the selected metric card.
        if (filter === 'New') {
            $('#filter-stage').val('New');
        } else if (filter === 'Not Called') {
            $('#filter-call-status').val('Not Called');
        } else if (filter === 'Follow-up') {
            $('#filter-call-status').val('Call Back Requested');
        } else if (filter === 'Unassigned') {
            $('#filter-rep').val('0');
        } else if (filter === 'Qualified Handoffs') {
            $('#filter-quality').val('Qualified Awaiting Assignment');
            $('#idlm-secondary-filters-container').slideDown(200);
            $('#btn-toggle-more-filters').addClass('active');
        } else if (filter === 'Routing Exceptions') {
            $('#filter-quality').val('Routing Exception');
            $('#idlm-secondary-filters-container').slideDown(200);
            $('#btn-toggle-more-filters').addClass('active');
        } else if (filter === 'Qualified') {
            $('#filter-quality').val('Qualified');
            // Show secondary filters row since Quality is in it
            $('#idlm-secondary-filters-container').slideDown(200);
            $('#btn-toggle-more-filters').addClass('active');
        } else if (filter === 'Pending Quality') {
            $('#filter-quality').val('Pending');
            // Show secondary filters row since Quality is in it
            $('#idlm-secondary-filters-container').slideDown(200);
            $('#btn-toggle-more-filters').addClass('active');
        }
        
        loadLeadsTable(1);
    });

    $(document).on('click', '#idlm-qualified-handoffs-pill', function() {
        $('.idlm-metric[data-filter="Qualified Handoffs"]').trigger('click');
    });

    // Pagination events
    $('#prev-page').click(function() {
        if (currentPage > 1) {
            loadLeadsTable(currentPage - 1);
        }
    });

    $('#next-page').click(function() {
        loadLeadsTable(currentPage + 1);
    });

    $('#idlm-leads-per-page').change(function() {
        loadLeadsTable(1);
    });

    // Helper function to submit Inline Quick Edits via AJAX
    function submitQuickEdit(leadId, field, val, callbackTime, $select, dealValue, lostRemark, discountPercentage, propertyLocation, quoteValue, quoteFile, lostReason) {
        var ajaxData = {
            action: 'idlm_quick_edit_lead',
            nonce: idlm_vars.nonce,
            lead_id: leadId,
            field: field,
            value: val,
            callback_time: callbackTime
        };
        if (dealValue !== undefined) {
            ajaxData.deal_value = dealValue;
        }
        if (lostRemark !== undefined) {
            ajaxData.lost_remark = lostRemark;
        }
        if (lostReason !== undefined) {
            ajaxData.lost_reason = lostReason;
        }
        if (discountPercentage !== undefined) {
            ajaxData.discount_percentage = discountPercentage;
        }
        if (propertyLocation !== undefined) {
            ajaxData.property_location = propertyLocation;
        }
        if (quoteValue !== undefined) {
            ajaxData.quote_value = quoteValue;
        }
        if (quoteFile !== undefined) {
            ajaxData.quote_file = quoteFile;
        }
        $.ajax({
            url: idlm_vars.ajax_url,
            type: 'POST',
            data: ajaxData,
            success: function(response) {
                if (response.success) {
                    showToast('Changes saved successfully!', 'success');
                    if ($select) {
                        if (field === 'stage' && val === 'Won' && propertyLocation !== undefined) {
                            $select.attr('data-property-location', propertyLocation);
                        }
                        if (field === 'stage' && val === 'Quote Shared' && quoteValue !== undefined) {
                            $select.attr('data-quote-value', quoteValue);
                            $select.attr('data-quote-file', quoteFile || '');
                        }

                        // Add micro-flash animation to row
                        var $row = $select.closest('tr');
                        $row.css('background-color', '#f0fdf4');
                        setTimeout(function() {
                            $row.css('background-color', '');
                        }, 1000);
                    }

                    // Reload table if call status changed so overdue/reminders badge updates dynamically
                    if (field === 'call_status') {
                        loadLeadsTable(currentPage);
                    }

                    // Update pending calls count pill
                    if (response.data && response.data.hasOwnProperty('pending_calls_count')) {
                        updatePendingCallsPill(response.data.pending_calls_count);
                    }
                    if (response.data && response.data.hasOwnProperty('unread_whatsapp_count')) {
                        updateUnreadWhatsAppPill(response.data.unread_whatsapp_count);
                    }
                } else {
                    showToast(response.data.message || 'Save failed.', 'error');
                    loadLeadsTable(currentPage);
                }
            },
            error: function() {
                showToast('Server error saving changes.', 'error');
                loadLeadsTable(currentPage);
            }
        });
    }

    // Handle Inline Quick Edit dropdown changes in Table Rows
    $(document).on('change', '.idlm-quick-edit', function() {
        var $select = $(this);
        var field = $select.data('field');
        var leadId = $select.data('id');
        var val = $select.val();

        // If callback status chosen, request datetime callback scheduler using custom modal
        if (field === 'call_status' && val === 'Call Back Requested') {
            activeCallbackSelect = {
                select: $select,
                leadId: leadId,
                val: val
            };
            
            // Set current time as default value in native HTML5 datetime picker
            var nowStr = getCurrentDateTimeStringForInput();
            $('#idlm-callback-datetime-input').val(nowStr);
            
            // Open callback modal
            $('#idlm-callback-modal').addClass('active');
            updateBodyScrollLock();
            return;
        }

        // If Won stage chosen, request deal value popup
        if (field === 'stage' && val === 'Won') {
            activeWonSelect = {
                select: $select,
                leadId: leadId,
                val: val
            };
            $('#idlm-deal-value-input').val('');
            $('#idlm-discount-percentage-input').val('');
            $('#idlm-won-property-location-input').val($select.attr('data-property-location') || '');
            $('#idlm-deal-value-modal').addClass('active');
            updateBodyScrollLock();
            return;
        }

        // If Quote Shared is chosen, request the estimated quote value.
        if (field === 'stage' && val === 'Quote Shared') {
            activeQuoteSelect = {
                select: $select,
                leadId: leadId,
                val: val
            };
            var existingQuoteValue = parseFloat($select.attr('data-quote-value') || 0);
            $('#idlm-quote-value-input').val(existingQuoteValue > 0 ? existingQuoteValue : '');
            setQuoteUploadState('idlm-stage-quote-file', 'idlm-stage-quote-preview', $select.attr('data-quote-file') || '');
            $('#idlm-quote-value-modal').addClass('active');
            updateBodyScrollLock();
            $('#idlm-quote-value-input').focus();
            return;
        }

        // A reason is mandatory before a lead can be moved to Lost.
        if (field === 'stage' && val === 'Lost') {
            activeLostSelect = {
                select: $select,
                leadId: leadId,
                val: val
            };
            $('#idlm-lost-reason-select').val('');
            $('#idlm-lost-remark-input').val('');
            $('#idlm-lost-remark-modal').addClass('active');
            updateBodyScrollLock();
            $('#idlm-lost-reason-select').focus();
            return;
        }

        // Standard AJAX call for other status/field edits
        submitQuickEdit(leadId, field, val, '', $select);
    });

    // Callback Modal Event Handlers
    $('#idlm-callback-modal-save').click(function() {
        if (!activeCallbackSelect) return;
        
        var callbackVal = $('#idlm-callback-datetime-input').val();
        if (!callbackVal) {
            alert('Please select a valid date and time.');
            return;
        }

        // Convert YYYY-MM-DDTHH:MM back to YYYY-MM-DD HH:MM for backend consistency
        var formattedTime = callbackVal.replace('T', ' ');

        $('#idlm-callback-modal').removeClass('active');
        submitQuickEdit(activeCallbackSelect.leadId, 'call_status', activeCallbackSelect.val, formattedTime, activeCallbackSelect.select);
        activeCallbackSelect = null;
    });

    $('#idlm-callback-modal-cancel, #idlm-callback-modal-close').click(function() {
        $('#idlm-callback-modal').removeClass('active');
        updateBodyScrollLock();
        if (activeCallbackSelect) {
            // Reset dropdown select to database state
            loadLeadsTable(currentPage);
            activeCallbackSelect = null;
        }
    });

    // Deal Value Modal Event Handlers
    $('#idlm-deal-value-modal-save').click(function() {
        if (!activeWonSelect) return;
        
        var dealValue = $('#idlm-deal-value-input').val();
        if (!dealValue || isNaN(dealValue) || parseFloat(dealValue) <= 0) {
            alert('Please enter a deal value greater than zero.');
            return;
        }

        var discountPercentage = $('#idlm-discount-percentage-input').val();
        if (discountPercentage === '' || isNaN(discountPercentage) || parseFloat(discountPercentage) < 0 || parseFloat(discountPercentage) > 100) {
            alert('Please enter a discount percentage between 0 and 100.');
            $('#idlm-discount-percentage-input').focus();
            return;
        }

        var propertyLocation = $.trim($('#idlm-won-property-location-input').val());

        $('#idlm-deal-value-modal').removeClass('active');
        updateBodyScrollLock();
        submitQuickEdit(activeWonSelect.leadId, 'stage', 'Won', '', activeWonSelect.select, parseFloat(dealValue), undefined, parseFloat(discountPercentage), propertyLocation);
        activeWonSelect = null;
    });

    $('#idlm-deal-value-modal-cancel, #idlm-deal-value-modal-close').click(function() {
        $('#idlm-deal-value-modal').removeClass('active');
        updateBodyScrollLock();
        if (activeWonSelect) {
            // Reset dropdown select to database state
            loadLeadsTable(currentPage);
            activeWonSelect = null;
        }
    });

    // Quote Value Modal Event Handlers
    $('#idlm-quote-value-modal-save').click(function() {
        if (!activeQuoteSelect) return;

        var quoteValue = $('#idlm-quote-value-input').val();
        if (!quoteValue || isNaN(quoteValue) || parseFloat(quoteValue) <= 0) {
            alert('Please enter a quote value greater than zero.');
            $('#idlm-quote-value-input').focus();
            return;
        }

        var quoteFile = $('#idlm-stage-quote-file').val();

        $('#idlm-quote-value-modal').removeClass('active');
        updateBodyScrollLock();
        submitQuickEdit(activeQuoteSelect.leadId, 'stage', 'Quote Shared', '', activeQuoteSelect.select, undefined, undefined, undefined, undefined, parseFloat(quoteValue), quoteFile);
        activeQuoteSelect = null;
    });

    $('#idlm-quote-value-modal-cancel, #idlm-quote-value-modal-close').click(function() {
        $('#idlm-quote-value-modal').removeClass('active');
        updateBodyScrollLock();
        if (activeQuoteSelect) {
            loadLeadsTable(currentPage);
            activeQuoteSelect = null;
        }
    });

    // Lost Remark Modal Event Handlers
    $('#idlm-lost-remark-modal-save').click(function() {
        if (!activeLostSelect && !activeLostBulkAction) return;

        var lostRemark = $.trim($('#idlm-lost-remark-input').val());
        var lostReason = $('#idlm-lost-reason-select').val();
        if (!lostReason) {
            alert('Please select a lost reason.');
            $('#idlm-lost-reason-select').focus();
            return;
        }
        if (!lostRemark) {
            alert('Please enter a remark explaining why the lead was lost.');
            $('#idlm-lost-remark-input').focus();
            return;
        }

        $('#idlm-lost-remark-modal').removeClass('active');
        updateBodyScrollLock();
        if (activeLostSelect) {
            submitQuickEdit(activeLostSelect.leadId, 'stage', 'Lost', '', activeLostSelect.select, undefined, lostRemark, undefined, undefined, undefined, undefined, lostReason);
            activeLostSelect = null;
        } else if (activeLostBulkAction) {
            submitBulkAction(
                activeLostBulkAction.action,
                activeLostBulkAction.selectedIds,
                activeLostBulkAction.repId,
                activeLostBulkAction.stage,
                lostRemark,
                lostReason
            );
            activeLostBulkAction = null;
        }
    });

    $('#idlm-lost-remark-modal-cancel, #idlm-lost-remark-modal-close').click(function() {
        $('#idlm-lost-remark-modal').removeClass('active');
        updateBodyScrollLock();
        if (activeLostSelect) {
            loadLeadsTable(currentPage);
            activeLostSelect = null;
        }
        activeLostBulkAction = null;
    });

    function getCurrentDateTimeStringForInput() {
        var now = new Date();
        var yyyy = now.getFullYear();
        var mm = String(now.getMonth() + 1).padStart(2, '0');
        var dd = String(now.getDate()).padStart(2, '0');
        var hh = String(now.getHours()).padStart(2, '0');
        var min = String(now.getMinutes()).padStart(2, '0');
        return yyyy + '-' + mm + '-' + dd + 'T' + hh + ':' + min;
    }

    // Checkbox selections for Bulk actions
    $(document).on('change', '#idlm-select-all', function() {
        var checked = $(this).prop('checked');
        $('.idlm-row-selector').prop('checked', checked).trigger('change');
    });

    $(document).on('change', '.idlm-row-selector', function() {
        var selectedCount = $('.idlm-row-selector:checked').length;
        if (selectedCount > 0) {
            $('.idlm-selected-count').text(selectedCount + ' items selected');
            $('.idlm-bulk-bar').slideDown(150);
        } else {
            $('.idlm-bulk-bar').slideUp(150);
            $('#idlm-select-all').prop('checked', false);
        }
    });

    // Show dynamic input fields in Bulk Actions based on selected operation
    $('#idlm-bulk-action-select').change(function() {
        var action = $(this).val();
        $('#idlm-bulk-rep').hide();
        $('#idlm-bulk-stage').hide();

        if (action === 'bulk_assign') {
            $('#idlm-bulk-rep').show();
        } else if (action === 'bulk_stage') {
            $('#idlm-bulk-stage').show();
        }
    });

    function submitBulkAction(action, selectedIds, repId, stage, lostRemark, lostReason) {
        $.ajax({
            url: idlm_vars.ajax_url,
            type: 'POST',
            data: {
                action: 'idlm_bulk_action',
                nonce: idlm_vars.nonce,
                ids: selectedIds,
                bulk_action: action,
                rep_id: repId,
                stage: stage,
                lost_remark: lostRemark || '',
                lost_reason: lostReason || ''
            },
            success: function(response) {
                if (response.success) {
                    showToast(response.data.message, 'success');
                    $('.idlm-row-selector').prop('checked', false);
                    $('.idlm-bulk-bar').slideUp(150);
                    loadLeadsTable(currentPage);
                } else {
                    showToast(response.data.message || 'Bulk operation failed.', 'error');
                }
            }
        });
    }

    // Apply Bulk actions to selected leads
    $('#idlm-apply-bulk').click(function() {
        var action = $('#idlm-bulk-action-select').val();
        var selectedIds = [];
        $('.idlm-row-selector:checked').each(function() {
            selectedIds.push($(this).val());
        });

        if (selectedIds.length === 0 || !action) {
            alert('Please select leads and an action to apply.');
            return;
        }

        var repId = $('#idlm-bulk-rep').val();
        var stage = $('#idlm-bulk-stage').val();

        if (action === 'bulk_stage' && stage === 'Lost') {
            activeLostBulkAction = {
                action: action,
                selectedIds: selectedIds,
                repId: repId,
                stage: stage
            };
            $('#idlm-lost-reason-select').val('');
            $('#idlm-lost-remark-input').val('');
            $('#idlm-lost-remark-modal').addClass('active');
            updateBodyScrollLock();
            $('#idlm-lost-reason-select').focus();
            return;
        }

        // Prompt confirmation for bulk delete
        if (action === 'bulk_delete') {
            if (!confirm('Are you absolutely sure you want to permanently delete these ' + selectedIds.length + ' selected leads? This action cannot be undone and will also delete all associated logs.')) {
                return;
            }
        }

        submitBulkAction(action, selectedIds, repId, stage, '');
    });

    // Export CSV hook: Open Export Options Modal
    $('#idlm-export-btn').click(function(e) {
        e.preventDefault();
        openExportModal();
    });

    // Handle confirming download inside the export modal
    $(document).on('click', '#idlm-confirm-export-btn', function() {
        var scope = $('input[name="idlm-export-scope"]:checked').val();
        var exportUrl = idlm_vars.ajax_url + '?action=idlm_export_leads&nonce=' + idlm_vars.nonce;

        if (scope === 'selected') {
            var selectedIds = [];
            $('.idlm-row-selector:checked').each(function() {
                selectedIds.push($(this).val());
            });
            if (selectedIds.length > 0) {
                exportUrl += '&ids=' + selectedIds.join(',');
            }
        } else if (scope === 'filtered') {
            var dateRange = $('#export-filter-date-range').val();
            var dateBasis = $('#export-filter-date-basis').val();
            var source = $('#export-filter-source').val();
            var repId = $('#export-filter-rep').val();
            var stage = $('#export-filter-stage').val();
            var callStatus = $('#export-filter-call-status').val();
            var quality = $('#export-filter-quality').val();
            var followup = $('#export-filter-followup').val();
            var search = $('#export-filter-search').val();

            if (dateRange) exportUrl += '&date_range=' + encodeURIComponent(dateRange);
            if (dateBasis) exportUrl += '&date_basis=' + encodeURIComponent(dateBasis);
            if (source) exportUrl += '&source=' + encodeURIComponent(source);
            if (repId) exportUrl += '&rep_id=' + encodeURIComponent(repId);
            if (stage) exportUrl += '&stage=' + encodeURIComponent(stage);
            if (callStatus) exportUrl += '&call_status=' + encodeURIComponent(callStatus);
            if (quality) exportUrl += '&quality=' + encodeURIComponent(quality);
            if (followup) exportUrl += '&followup_filter=' + encodeURIComponent(followup);
            if (search) exportUrl += '&search=' + encodeURIComponent(search);
        }

        window.open(exportUrl, '_blank');
        closeExportModal();
    });

    // Listen for scope changes to visually disable filters panel
    $(document).on('change', 'input[name="idlm-export-scope"]', function() {
        var scope = $(this).val();
        if (scope === 'filtered') {
            $('#idlm-export-filters-section').css('opacity', '1').find('input, select').prop('disabled', false);
        } else {
            $('#idlm-export-filters-section').css('opacity', '0.5').find('input, select').prop('disabled', true);
        }
    });

    // Manual Lead source trigger: Show/hide Referrer text block
    $('#idlm-lead-source-select').change(function() {
        if ($(this).val() === 'Referral') {
            $('#idlm-referrer-field').slideDown(200);
        } else {
            $('#idlm-referrer-field').slideUp(200);
        }
    });

    // Manual Lead Form submit handler
    $('#idlm-add-lead-form').submit(function(e) {
        e.preventDefault();
        var formData = $(this).serialize();

        $.ajax({
            url: idlm_vars.ajax_url,
            type: 'POST',
            data: {
                action: 'idlm_add_lead_manual',
                nonce: idlm_vars.nonce,
                form_data: formData
            },
            success: function(response) {
                if (response.success) {
                    showToast(response.data.message, 'success');
                    closeLeadModal();
                    $('#idlm-add-lead-form')[0].reset();
                    loadLeadsTable(1);

                    // Update pending calls count pill
                    if (response.data && response.data.hasOwnProperty('pending_calls_count')) {
                        updatePendingCallsPill(response.data.pending_calls_count);
                    }
                } else {
                    showToast(response.data.message || 'Creation failed.', 'error');
                }
            }
        });
    });

    // Settings Sync Now trigger
    $('#idlm-manual-sync-trigger').click(function(e) {
        e.preventDefault();
        var $btn = $(this);
        $btn.text('Syncing Custom Audiences...').prop('disabled', true);

        $.ajax({
            url: idlm_vars.ajax_url,
            type: 'POST',
            data: {
                action: 'idlm_trigger_manual_audience_sync',
                nonce: idlm_vars.nonce
            },
            success: function(response) {
                if (response.success) {
                    showToast(response.data.message, 'success');
                } else {
                    showToast('Sync failed.', 'error');
                }
                $btn.text('Run Custom Audience Sync Job Now').prop('disabled', false);
            },
            error: function() {
                showToast('Server sync failed.', 'error');
                $btn.text('Run Custom Audience Sync Job Now').prop('disabled', false);
            }
        });
    });

    // Test Meta CAPI Connection Click Handler
    $(document).on('click', '#idlm-test-meta-capi', function(e) {
        e.preventDefault();
        var $btn = $(this);
        var pixelId = $('input[name="meta_pixel_id"]').val();
        var accessToken = $('input[name="meta_access_token"]').val();

        if (!pixelId || !accessToken) {
            showToast('Please enter both Meta Pixel ID and Meta Access Token to test.', 'error');
            return;
        }

        $btn.text('Testing Meta CAPI...').prop('disabled', true);

        $.ajax({
            url: idlm_vars.ajax_url,
            type: 'POST',
            data: {
                action: 'idlm_test_api_connection',
                nonce: idlm_vars.nonce,
                platform: 'meta',
                test_type: 'capi',
                target_id: pixelId,
                access_token: accessToken
            },
            success: function(response) {
                if (response.success) {
                    showToast(response.data.message, 'success');
                } else {
                    showToast(response.data.message || 'Connection test failed.', 'error');
                }
                $btn.text('Test Meta CAPI Connection').prop('disabled', false);
            },
            error: function() {
                showToast('Server connection test failed.', 'error');
                $btn.text('Test Meta CAPI Connection').prop('disabled', false);
            }
        });
    });

    // Test Audience Rule Connection Click Handler
    $(document).on('click', '.idlm-test-audience-rule', function(e) {
        e.preventDefault();
        var $btn = $(this);
        var $row = $btn.closest('tr');
        var platform = $row.find('select[name="audience_rules[platform][]"]').val();
        var audienceId = $row.find('input[name="audience_rules[audience_id][]"]').val();

        if (!audienceId) {
            showToast('Please enter an Audience/Segment ID first.', 'error');
            return;
        }

        var accessToken = '';
        if (platform === 'meta') {
            accessToken = $('input[name="meta_access_token"]').val();
        } else if (platform === 'linkedin') {
            accessToken = $('input[name="linkedin_access_token"]').val();
        }

        if (platform !== 'google' && !accessToken) {
            showToast('Please enter an access token for ' + platform + ' under platform settings first.', 'error');
            return;
        }

        $btn.text('Testing...').prop('disabled', true);

        $.ajax({
            url: idlm_vars.ajax_url,
            type: 'POST',
            data: {
                action: 'idlm_test_api_connection',
                nonce: idlm_vars.nonce,
                platform: platform,
                test_type: 'audience',
                target_id: audienceId,
                access_token: accessToken,
                google_developer_token: $('input[name="google_developer_token"]').val() || '',
                google_client_id: $('input[name="google_client_id"]').val() || '',
                google_client_secret: $('input[name="google_client_secret"]').val() || '',
                google_refresh_token: $('input[name="google_refresh_token"]').val() || '',
                google_customer_id: $('input[name="google_customer_id"]').val() || '',
                google_manager_customer_id: $('input[name="google_manager_customer_id"]').val() || ''
            },
            success: function(response) {
                if (response.success) {
                    showToast(response.data.message, 'success');
                } else {
                    showToast(response.data.message || 'Audience connection test failed.', 'error');
                }
                $btn.text('Test').prop('disabled', false);
            },
            error: function() {
                showToast('Server audience test failed.', 'error');
                $btn.text('Test').prop('disabled', false);
            }
        });
    });

    // Query leads list from AJAX endpoint
    function loadLeadsTable(page) {
        page = Math.max(1, Number(page) || 1);
        if (page === 1) {
            leadsPageCursors = { 1: '' };
        }
        var dateRange = $('#filter-date-range').val();
        var dateBasis = $('#filter-date-basis').val();
        var source = $('#filter-source').val();
        var repId = $('#filter-rep').val();
        var stage = $('#filter-stage').val();
        var callStatus = $('#filter-call-status').val();
        var quality = $('#filter-quality').val();
        var followup = $('#filter-followup').val();
        var search = $('#filter-search').val();

        if (leadsTableRequest && leadsTableRequest.readyState !== 4) {
            leadsTableRequest.abort();
        }

        var requestData = {
            action: 'idlm_get_leads_table',
            nonce: idlm_vars.nonce,
            page: page,
            date_range: dateRange,
            date_basis: dateBasis,
            source: source,
            rep_id: repId,
            stage: stage,
            call_status: callStatus,
            quality: quality,
            followup_filter: followup,
            active_only: leadsActiveOnly ? '1' : '0',
            search: search,
            sort_by: leadsSortBy,
            sort_order: leadsSortOrder,
            per_page: $('#idlm-leads-per-page').val() || 25
        };
        if (page > 1 && Object.prototype.hasOwnProperty.call(leadsPageCursors, page) && leadsPageCursors[page]) {
            requestData.cursor = leadsPageCursors[page];
        }

        leadsTableRequest = $.ajax({
            url: idlm_vars.ajax_url,
            type: 'POST',
            data: requestData,
            success: function(response) {
                if (response.success) {
                    $('#idlm-leads-table tbody').html(response.data.html);
                    if (response.data.metric_stats) {
                        Object.keys(response.data.metric_stats).forEach(function(key) {
                            $('[data-metric-count="' + key + '"]').text(Number(response.data.metric_stats[key]) || 0);
                        });
                        var overdueHandoffs = Number(response.data.metric_stats.overdue_handoffs) || 0;
                        $('[data-metric-hint="qualified_handoffs"]').text(overdueHandoffs > 0 ? overdueHandoffs + ' overdue' : 'awaiting assignment');
                        $('.idlm-metric[data-filter="Qualified Handoffs"]').toggleClass('is-alert', overdueHandoffs > 0);
                    }
                    currentPage = response.data.current_page;
                    if (response.data.next_cursor) {
                        leadsPageCursors[currentPage + 1] = response.data.next_cursor;
                    } else {
                        delete leadsPageCursors[currentPage + 1];
                    }
                    $('#page-num').text('Page ' + currentPage + ' of ' + response.data.total_pages);
                    
                    // Toggle paging buttons
                    $('#prev-page').prop('disabled', currentPage <= 1);
                    $('#next-page').prop('disabled', currentPage >= response.data.total_pages);
                    
                    // Reset bulk action bars
                    $('.idlm-bulk-bar').hide();
                    $('#idlm-select-all').prop('checked', false);

                    // Update pending calls count pill
                    if (response.data && response.data.hasOwnProperty('pending_calls_count')) {
                        updatePendingCallsPill(response.data.pending_calls_count);
                    }
                    if (response.data && response.data.hasOwnProperty('unread_whatsapp_count')) {
                        updateUnreadWhatsAppPill(response.data.unread_whatsapp_count);
                    }
                }
            },
            error: function(xhr) {
                if (requestData.cursor && xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) {
                    showToast(xhr.responseJSON.data.message, 'error');
                    leadsPageCursors = { 1: '' };
                    window.setTimeout(function() { loadLeadsTable(1); }, 0);
                }
            },
            complete: function() {
                leadsTableRequest = null;
            }
        });
    }

    // Helper functions
    function getCurrentDateTimeString() {
        var now = new Date();
        var yyyy = now.getFullYear();
        var mm = String(now.getMonth() + 1).padStart(2, '0');
        var dd = String(now.getDate()).padStart(2, '0');
        var hh = String(now.getHours()).padStart(2, '0');
        var min = String(now.getMinutes()).padStart(2, '0');
        return yyyy + '-' + mm + '-' + dd + ' ' + hh + ':' + min;
    }

    // Custom non-intrusive notification toasts
    function showToast(message, type) {
        var $toast = $('<div class="idlm-toast ' + type + '">' + message + '</div>');
        $('body').append($toast);
        $toast.fadeIn(300).delay(2500).fadeOut(300, function() {
            $(this).remove();
        });
    }

    // CSS inject for Toast notifications
    $("<style>")
        .prop("type", "text/css")
        .html("\
        .idlm-toast {\
            position: fixed;\
            bottom: 25px;\
            right: 25px;\
            padding: 12px 20px;\
            border-radius: 8px;\
            color: white;\
            font-size: 13px;\
            font-weight: 500;\
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);\
            z-index: 100001;\
            display: none;\
            min-width: 200px;\
        }\
        .idlm-toast.success { background-color: var(--idlm-success, #10b981); }\
        .idlm-toast.error { background-color: var(--idlm-danger, #ef4444); }\
        ")
        .appendTo("head");

    // Dynamic Lead Drawer Edit Form submit handler
    $(document).on('submit', '#idlm-edit-lead-form', function(e) {
        e.preventDefault();
        var formData = $(this).serialize();
        var leadId = $(this).find('input[name="lead_id"]').val();

        $.ajax({
            url: idlm_vars.ajax_url,
            type: 'POST',
            data: {
                action: 'idlm_save_lead_details',
                nonce: idlm_vars.nonce,
                form_data: formData
            },
            success: function(response) {
                if (response.success) {
                    showToast(response.data.message, 'success');
                    // Reload drawer to update values
                    openLeadDrawer(leadId);
                    // Reload leads table so details match row instantly
                    loadLeadsTable(currentPage);

                    // Update pending calls count pill
                    if (response.data && response.data.hasOwnProperty('pending_calls_count')) {
                        updatePendingCallsPill(response.data.pending_calls_count);
                    }
                } else {
                    showToast(response.data.message || 'Save failed.', 'error');
                }
            },
            error: function() {
                showToast('Server error saving lead updates.', 'error');
            }
        });
    });

    function updatePendingCallsPill(count) {
        var $pill = $('#idlm-pending-calls-pill');
        if (count > 0) {
            $pill.find('.idlm-pending-text strong').text(count);
            $pill.fadeIn(300);
        } else {
            $pill.fadeOut(300);
        }
    }

    function updateUnreadWhatsAppPill(count) {
        var $pill = $('#idlm-whatsapp-unread-pill');
        count = parseInt(count, 10) || 0;
        $pill.find('.idlm-whatsapp-unread-text strong').text(count);
        $pill.attr('title', count + ' unread WhatsApp messages');
        $pill.attr('aria-label', 'Show ' + count + ' unread WhatsApp messages');
        if (count > 0) {
            $pill.fadeIn(300);
        } else {
            $pill.fadeOut(300);
        }
    }
    window.idlmUpdateUnreadWhatsAppPill = updateUnreadWhatsAppPill;

    // Automatically refresh the leads list table every 15 minutes (900,000 ms)
    setInterval(function() {
        if (!document.hidden && $('#idlm-leads-table').length > 0) {
            loadLeadsTable(currentPage);
        }
    }, 900000);

    // Dynamic Sample CSV Template Generator
    $('#idlm-import-download-template').click(function(e) {
        e.preventDefault();
        var headers = [
            "First Name", "Last Name", "Email Address", "Phone Number", "Budget", 
            "Project Timeline", "Property Location", "Source Channel", "Property Type", 
            "Number of Rooms", "Preferred Studio", "Lead Quality", "UTM Source", 
            "UTM Medium", "UTM Campaign", "UTM Adgroup", "UTM Term", "UTM Content", 
            "Gclid", "Gbraid", "Wbraid", "Fbclid", "Meta Lead ID", "LI Fat ID", "Landing Page", "Conversion Time"
        ];
        var sampleRow = [
            "John", "Doe", "john.doe@example.com", "+1234567890", "$25,000", 
            "1-3 Months", "Downtown", "Google Ads", "Apartment", 
            "3 BHK", "Downtown Studio", "Qualified", "google", 
            "cpc", "Spring_Campaign", "Adgroup_1", "interior design", "banner_ad", 
            "gclid12345", "", "", "", "1000000000001", "", "https://example.com/landing", "2026-06-12 12:00:00"
        ];
        var csvContent = headers.join(",") + "\n" + sampleRow.join(",");
        var blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
        var url = URL.createObjectURL(blob);
        var link = document.createElement("a");
        link.setAttribute("href", url);
        link.setAttribute("download", "lead_manager_import_template.csv");
        link.style.visibility = 'hidden';
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
    });

    // File selection / drag-drop variables
    var parsedHeaders = [];
    var parsedRows = [];

    $('#idlm-import-drag-zone').click(function() {
        $('#idlm-import-file-input').click();
    });

    $('#idlm-import-file-input').change(function(e) {
        if (e.target.files.length > 0) {
            handleSelectedFile(e.target.files[0]);
        }
    });

    var dragZone = document.getElementById('idlm-import-drag-zone');
    if (dragZone) {
        dragZone.addEventListener('dragover', function(e) {
            e.preventDefault();
            $(dragZone).addClass('dragover');
        });
        dragZone.addEventListener('dragleave', function(e) {
            e.preventDefault();
            $(dragZone).removeClass('dragover');
        });
        dragZone.addEventListener('drop', function(e) {
            e.preventDefault();
            $(dragZone).removeClass('dragover');
            if (e.dataTransfer.files.length > 0) {
                handleSelectedFile(e.dataTransfer.files[0]);
            }
        });
    }

    // RFC 4180 Double-quote aware CSV parser
    function parseCSV(text) {
        var lines = [];
        var row = [""];
        var inQuotes = false;
        for (var i = 0; i < text.length; i++) {
            var c = text[i];
            var next = text[i+1];
            if (c === '"') {
                if (inQuotes && next === '"') {
                    row[row.length - 1] += '"';
                    i++;
                } else {
                    inQuotes = !inQuotes;
                }
            } else if (c === ',' && !inQuotes) {
                row.push('');
            } else if ((c === '\r' || c === '\n') && !inQuotes) {
                if (c === '\r' && next === '\n') {
                    i++;
                }
                lines.push(row);
                row = [''];
            } else {
                row[row.length - 1] += c;
            }
        }
        if (row.length > 1 || row[0] !== '') {
            lines.push(row);
        }
        return lines;
    }

    function handleSelectedFile(file) {
        if (!file.name.endsWith('.csv')) {
            alert('Please select a valid CSV file.');
            return;
        }
        var reader = new FileReader();
        reader.onload = function(e) {
            var text = e.target.result;
            var allRows = parseCSV(text);
            if (allRows.length === 0) {
                alert('The selected CSV file is empty.');
                return;
            }
            parsedRows = allRows.filter(function(r) {
                return r.length > 0 && r.some(function(cell) { return cell.trim() !== ''; });
            });
            if (parsedRows.length < 2) {
                alert('The selected CSV contains no data rows besides headers.');
                return;
            }
            parsedHeaders = parsedRows[0].map(function(h) { return h.trim(); });
            parsedRows = parsedRows.slice(1);

            goToImportStep(2);
            populateMappingTable();
        };
        reader.readAsText(file);
    }

    function goToImportStep(stepNum) {
        $('.idlm-import-step-content').removeClass('active');
        $('.idlm-import-step-item').removeClass('active').removeClass('completed');
        $('#import-step-content-' + stepNum).addClass('active');
        
        for (var i = 1; i <= 4; i++) {
            var $nav = $('#import-step-nav-' + i);
            if (i === stepNum) {
                $nav.addClass('active');
            } else if (i < stepNum) {
                $nav.addClass('completed');
            }
        }
    }

    var crmFields = [
        { key: 'first_name', label: 'First Name*', required: true, fuzzy: ['first name', 'first_name', 'firstname', 'name', 'given name'] },
        { key: 'last_name', label: 'Last Name', required: false, fuzzy: ['last name', 'last_name', 'lastname', 'surname', 'family name'] },
        { key: 'email', label: 'Email Address', required: false, fuzzy: ['email', 'email address', 'email_address'] },
        { key: 'phone', label: 'Phone Number*', required: true, fuzzy: ['phone', 'phone number', 'phone_number', 'mobile', 'tel', 'contact'] },
        { key: 'budget', label: 'Budget', required: false, fuzzy: ['budget', 'price', 'investment'] },
        { key: 'deal_value', label: 'Deal Value (INR)', required: false, fuzzy: ['deal value', 'deal_value', 'revenue', 'value', 'deal_size'] },
        { key: 'quote_value', label: 'Quote Value (INR)', required: false, fuzzy: ['quote value', 'quote_value', 'estimate value', 'estimated value'] },
        { key: 'project_timeline', label: 'Project Timeline', required: false, fuzzy: ['timeline', 'project timeline', 'project_timeline', 'timeframe', 'when'] },
        { key: 'property_location', label: 'Property Location / Pincode', required: false, fuzzy: ['location', 'pincode', 'postal code', 'zip', 'zipcode', 'property location'] },
        { key: 'source', label: 'Source Channel', required: false, fuzzy: ['source', 'channel', 'utm_source'] },
        { key: 'property_type', label: 'Property Type', required: false, fuzzy: ['property type', 'property_type', 'house type', 'villa', 'apartment'] },
        { key: 'num_rooms', label: 'Number of Rooms', required: false, fuzzy: ['rooms', 'num rooms', 'num_rooms', 'bhk', 'bedrooms'] },
        { key: 'preferred_studio', label: 'Preferred Studio', required: false, fuzzy: ['studio', 'branch', 'preferred studio', 'preferred_studio'] },
        { key: 'lead_quality', label: 'Lead Quality', required: false, fuzzy: ['quality', 'lead quality', 'lead_quality'] },
        { key: 'utm_source', label: 'UTM Source', required: false, fuzzy: ['utm_source'] },
        { key: 'utm_medium', label: 'UTM Medium', required: false, fuzzy: ['utm_medium'] },
        { key: 'utm_campaign', label: 'UTM Campaign', required: false, fuzzy: ['utm_campaign'] },
        { key: 'utm_adgroup', label: 'UTM Adgroup', required: false, fuzzy: ['utm_adgroup'] },
        { key: 'utm_term', label: 'UTM Term', required: false, fuzzy: ['utm_term'] },
        { key: 'utm_content', label: 'UTM Content', required: false, fuzzy: ['utm_content'] },
        { key: 'gclid', label: 'Google Click ID (Gclid)', required: false, fuzzy: ['gclid'] },
        { key: 'gbraid', label: 'Google App Click ID (Gbraid)', required: false, fuzzy: ['gbraid'] },
        { key: 'wbraid', label: 'Google Web Click ID (Wbraid)', required: false, fuzzy: ['wbraid'] },
        { key: 'fbclid', label: 'Facebook Click ID (Fbclid)', required: false, fuzzy: ['fbclid'] },
        { key: 'fb_lead_id', label: 'Meta Lead ID', required: false, fuzzy: ['meta_lead_id', 'fb_lead_id', 'meta lead id', 'facebook lead id', 'lead_id', 'lead id'] },
        { key: 'li_fat_id', label: 'LinkedIn Click ID (LI FAT ID)', required: false, fuzzy: ['li_fat_id', 'li fat id'] },
        { key: 'landing_page', label: 'Landing Page URL', required: false, fuzzy: ['landing page', 'landing_page', 'url', 'page'] },
        { key: 'conversion_time', label: 'Conversion Time', required: false, fuzzy: ['conversion time', 'conversion_time', 'time'] },
        { key: 'floor_plan_file', label: 'Floor Plan File URL', required: false, fuzzy: ['floor plan file', 'floor_plan_file', 'floor plan url', 'floor_plan_url', 'floor_plan_file_url'] },
        { key: 'quote_file', label: 'Quote File URL', required: false, fuzzy: ['quote file', 'quote_file', 'quote url', 'quote_url', 'quote_file_url'] }
    ];

    function populateMappingTable() {
        var html = '';
        crmFields.forEach(function(field) {
            var optionsHtml = '<option value="">-- Skip Field / Choose Mapping --</option>';
            parsedHeaders.forEach(function(header, index) {
                var lowerHeader = header.toLowerCase();
                var isMatch = field.fuzzy.some(function(fuz) {
                    return lowerHeader === fuz || lowerHeader.indexOf(fuz) !== -1;
                });
                var selected = isMatch ? 'selected' : '';
                optionsHtml += '<option value="' + index + '" ' + selected + '>' + header + '</option>';
            });

            html += '<tr>';
            html += '<td><strong>' + field.label + '</strong></td>';
            html += '<td><select class="idlm-input idlm-import-map-select" data-field="' + field.key + '" data-required="' + field.required + '" style="width:100%;">' + optionsHtml + '</select></td>';
            html += '</tr>';
        });
        $('#idlm-import-mapping-tbody').html(html);
    }

    // Step 2 Mapping Navigation
    $('#import-btn-next-2').click(function() {
        var mappings = {};
        var missingRequired = false;
        
        $('.idlm-import-map-select').each(function() {
            var key = $(this).data('field');
            var val = $(this).val();
            var required = $(this).data('required');
            
            if (val !== '') {
                mappings[key] = parseInt(val);
            } else if (required) {
                missingRequired = true;
            }
        });

        if (missingRequired) {
            alert('Please map the required fields: First Name and Phone Number.');
            return;
        }

        goToImportStep(3);
        generatePreviewTable(mappings);
    });

    function generatePreviewTable(mappings) {
        var theadHtml = '';
        var mappedKeys = [];
        
        crmFields.forEach(function(field) {
            if (mappings.hasOwnProperty(field.key)) {
                theadHtml += '<th>' + field.label.replace('*', '') + '</th>';
                mappedKeys.push(field.key);
            }
        });
        $('#idlm-import-preview-thead-tr').html(theadHtml);

        var tbodyHtml = '';
        var previewRows = parsedRows.slice(0, 3);
        
        previewRows.forEach(function(row) {
            tbodyHtml += '<tr>';
            crmFields.forEach(function(field) {
                if (mappings.hasOwnProperty(field.key)) {
                    var cellIndex = mappings[field.key];
                    var cellVal = row[cellIndex] || '';
                    tbodyHtml += '<td>' + escapeHtml(cellVal) + '</td>';
                }
            });
            tbodyHtml += '</tr>';
        });
        $('#idlm-import-preview-tbody').html(tbodyHtml);
    }

    function escapeHtml(text) {
        return text
            .replace(/&/g, "&amp;")
            .replace(/</g, "&lt;")
            .replace(/>/g, "&gt;")
            .replace(/"/g, "&quot;")
            .replace(/'/g, "&#039;");
    }

    $('#import-btn-back-1').click(function() {
        goToImportStep(1);
    });

    $('#import-btn-back-2').click(function() {
        goToImportStep(2);
    });

    // Batch Import Trigger Loop
    $('#import-btn-start').click(function() {
        var mappings = {};
        $('.idlm-import-map-select').each(function() {
            var key = $(this).data('field');
            var val = $(this).val();
            if (val !== '') {
                mappings[key] = parseInt(val);
            }
        });

        var defaultSource = $('#import-default-source').val();
        var defaultStage = $('#import-default-stage').val();
        var defaultCallStatus = $('#import-default-call-status').val();
        var runRouting = $('#import-opt-route').is(':checked') ? 1 : 0;
        var runCapi = $('#import-opt-capi').is(':checked') ? 1 : 0;

        goToImportStep(4);
        
        var totalLeads = parsedRows.length;
        var batchSize = 50;
        var batches = [];
        
        for (var i = 0; i < totalLeads; i += batchSize) {
            var batchRows = parsedRows.slice(i, i + batchSize);
            batches.push(batchRows);
        }

        var totalBatches = batches.length;
        var currentBatchIndex = 0;
        var stats = { success: 0, duplicate: 0, failed: 0 };
        
        $('#idlm-import-status-list').html('<div class="idlm-import-status-item info">🚀 Starting Lead Import: ' + totalLeads + ' rows total partitioned into ' + totalBatches + ' batches...</div>');
        $('#import-btn-close-final').hide();
        
        updateProgressBar(0, 'Importing batch 1 of ' + totalBatches + '...');

        function importNextBatch() {
            if (currentBatchIndex >= totalBatches) {
                updateProgressBar(100, 'Import complete!');
                var summaryMsg = '<strong>Import Complete Summary:</strong><br>' +
                    '✅ Successfully Imported: ' + stats.success + ' leads<br>' +
                    '🔗 Duplicates Linked: ' + stats.duplicate + ' leads<br>' +
                    '❌ Failed: ' + stats.failed + ' rows';
                
                $('#idlm-import-status-list').append('<div class="idlm-import-status-item success">' + summaryMsg + '</div>');
                $('#idlm-import-status-list').scrollTop($('#idlm-import-status-list')[0].scrollHeight);
                $('#import-btn-close-final').show();
                
                if (typeof window.idlmLoadLeadsTable === 'function') {
                    window.idlmLoadLeadsTable(1);
                }
                return;
            }

            var batchRows = batches[currentBatchIndex];
            var leadsBatch = [];
            
            batchRows.forEach(function(row) {
                var leadData = {};
                crmFields.forEach(function(field) {
                    if (mappings.hasOwnProperty(field.key)) {
                        var colIndex = mappings[field.key];
                        leadData[field.key] = row[colIndex] || '';
                    }
                });
                leadsBatch.push(leadData);
            });

            $.ajax({
                url: idlm_vars.ajax_url,
                type: 'POST',
                data: {
                    action: 'idlm_import_leads_batch',
                    nonce: idlm_vars.nonce,
                    leads: leadsBatch,
                    default_source: defaultSource,
                    default_stage: defaultStage,
                    default_call_status: defaultCallStatus,
                    run_routing: runRouting,
                    run_capi: runCapi
                },
                success: function(response) {
                    if (response.success) {
                        stats.success += response.data.imported || 0;
                        stats.duplicate += response.data.duplicates || 0;
                        stats.failed += response.data.failed || 0;
                        
                        var batchNum = currentBatchIndex + 1;
                        var logMsg = 'Batch ' + batchNum + ' of ' + totalBatches + ' complete. (' + 
                            (response.data.imported || 0) + ' saved, ' + 
                            (response.data.duplicates || 0) + ' duplicates linked, ' + 
                            (response.data.failed || 0) + ' failed)';
                            
                        $('#idlm-import-status-list').append('<div class="idlm-import-status-item success">✔ ' + logMsg + '</div>');
                    } else {
                        stats.failed += batchRows.length;
                        var batchNum = currentBatchIndex + 1;
                        $('#idlm-import-status-list').append('<div class="idlm-import-status-item error">✘ Batch ' + batchNum + ' failed: ' + (response.data.message || 'Unknown Server Error') + '</div>');
                    }
                    
                    $('#idlm-import-status-list').scrollTop($('#idlm-import-status-list')[0].scrollHeight);
                    currentBatchIndex++;
                    
                    var pct = Math.round((currentBatchIndex / totalBatches) * 100);
                    var nextBatchNum = currentBatchIndex + 1;
                    var statusText = currentBatchIndex < totalBatches ? 'Importing batch ' + nextBatchNum + ' of ' + totalBatches + '...' : 'Finalizing...';
                    updateProgressBar(pct, statusText);

                    setTimeout(importNextBatch, 200);
                },
                error: function() {
                    stats.failed += batchRows.length;
                    var batchNum = currentBatchIndex + 1;
                    $('#idlm-import-status-list').append('<div class="idlm-import-status-item error">✘ Server error processing Batch ' + batchNum + '.</div>');
                    $('#idlm-import-status-list').scrollTop($('#idlm-import-status-list')[0].scrollHeight);
                    
                    currentBatchIndex++;
                    var pct = Math.round((currentBatchIndex / totalBatches) * 100);
                    updateProgressBar(pct, 'Processing...');
                    
                    setTimeout(importNextBatch, 200);
                }
            });
        }

        importNextBatch();
    });

    function updateProgressBar(pct, text) {
        $('#import-progress-percent').text(pct + '%');
        $('#idlm-import-progress-fill').css('width', pct + '%');
        $('#import-progress-text').text(text);
    }

    // Trigger direct file input click
    $(document).on('click', '.idlm-direct-upload-trigger', function(e) {
        e.preventDefault();
        $(this).siblings('.idlm-file-input').click();
    });

    // Handle direct file input changes (Immediate AJAX Upload)
    $(document).on('change', '.idlm-file-input', function() {
        var $inputEl = $(this);
        var files = $inputEl[0].files;
        if (files.length === 0) return;

        var file = files[0];
        var inputId = $inputEl.data('input-id');
        var previewId = $inputEl.data('preview-id');
        var $hiddenInput = $('#' + inputId);
        var $preview = $('#' + previewId);
        var $button = $inputEl.siblings('.idlm-direct-upload-trigger');

        var formData = new FormData();
        var uploadPurpose = inputId === 'idlm_floor_plan_file' ? 'floor_plan' : 'quote';
        formData.append('action', 'idlm_upload_lead_file');
        formData.append('nonce', idlm_vars.nonce);
        formData.append('purpose', uploadPurpose);
        formData.append('file', file);

        $preview.html('<span style="color:#666;">Uploading...</span>');
        $button.prop('disabled', true);

        $.ajax({
            url: idlm_vars.ajax_url,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                $button.prop('disabled', false);
                if (response.success) {
                    $hiddenInput.val(response.data.url);
                    var previewHtml = '<a href="' + response.data.url + '" target="_blank" class="idlm-file-link" style="color: #c99644; font-weight: 500; text-decoration: none;">📄 View File</a>' +
                                      '<button type="button" class="idlm-remove-file-btn" data-input-id="' + inputId + '" data-preview-id="' + previewId + '" style="background:none; border:none; color:#ef4444; cursor:pointer; font-size:12px; padding:0; margin-left:5px; font-weight:500;">Remove</button>';
                    $preview.html(previewHtml);
                    $button.text('Change File');

                    // Automatically select 'Yes' for the dropdown if a file is uploaded
                    if (inputId === 'idlm_floor_plan_file') {
                        $('select[name="floor_plan_received"]').val('1');
                    } else if (inputId === 'idlm_quote_file') {
                        $('select[name="quote_shared"]').val('1');
                    }
                    showToast('File uploaded successfully!', 'success');
                } else {
                    $preview.html('<span style="color:#ef4444;">Upload failed</span>');
                    showToast(response.data.message || 'Upload failed.', 'error');
                }
            },
            error: function() {
                $button.prop('disabled', false);
                $preview.html('<span style="color:#ef4444;">Server error</span>');
                showToast('Server error uploading file.', 'error');
            }
        });
    });

    // Remove file handler
    $(document).on('click', '.idlm-remove-file-btn', function(e) {
        e.preventDefault();
        var $button = $(this);
        var inputId = $button.data('input-id');
        var previewId = $button.data('preview-id');
        var $hiddenInput = $('#' + inputId);
        var $preview = $('#' + previewId);

        $hiddenInput.val('');
        $preview.html('No file uploaded');
        
        var uploadBtnText = 'Upload File';
        if (inputId === 'idlm_floor_plan_file') {
            uploadBtnText = 'Upload Floor Plan';
            $('select[name="floor_plan_received"]').val('0');
        } else if (inputId === 'idlm_quote_file') {
            uploadBtnText = 'Upload Quote';
            $('select[name="quote_shared"]').val('0');
        } else if (inputId.indexOf('quote-file') !== -1) {
            uploadBtnText = 'Upload Quote';
        }
        
        // Find sibling trigger button and update text
        $hiddenInput.siblings('.idlm-direct-upload-trigger').text(uploadBtnText);
    });

    function setQuoteUploadState(inputId, previewId, quoteFile) {
        var $hiddenInput = $('#' + inputId);
        var $preview = $('#' + previewId);
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
                'data-input-id': inputId,
                'data-preview-id': previewId
            }).css({ background: 'none', border: 'none', color: '#ef4444', cursor: 'pointer', fontSize: '12px', padding: 0, marginLeft: '5px', fontWeight: 500 });
            $preview.empty().append($link, $removeButton);
            $uploadButton.text('Change File');
        } else {
            $preview.text('No file uploaded');
            $uploadButton.text('Upload Quote');
        }
    }

    // Danger Zone: Reset CRM Leads & Analytics Modal Trigger
    var expectedLeadsCount = 0;

    $(document).on('click', '#idlm-reset-data-btn', function(e) {
        e.preventDefault();
        var $btn = $(this);
        $btn.prop('disabled', true).text('Loading Leads Count...');

        $.ajax({
            url: idlm_vars.ajax_url,
            type: 'POST',
            data: {
                action: 'idlm_get_total_leads_count',
                nonce: idlm_vars.nonce
            },
            success: function(response) {
                $btn.prop('disabled', false).text('Delete All Leads & Reset Analytics');
                if (response.success) {
                    expectedLeadsCount = response.data.count;
                    $('#idlm-reset-instruction-text').html(
                        'To confirm, please enter the total number of leads currently in the database (<strong>' + expectedLeadsCount + '</strong>) to proceed:'
                    );
                    $('#idlm-reset-confirm-input').val('');
                    $('#idlm-reset-confirm-btn').prop('disabled', true);
                    $('#idlm-reset-modal').addClass('active');
                } else {
                    showToast(response.data.message || 'Failed to fetch lead count.', 'error');
                }
            },
            error: function() {
                $btn.prop('disabled', false).text('Delete All Leads & Reset Analytics');
                showToast('Server error fetching lead count.', 'error');
            }
        });
    });

    // Check verification input on keystroke
    $(document).on('input', '#idlm-reset-confirm-input', function() {
        var inputVal = $(this).val().trim();
        if (inputVal === String(expectedLeadsCount)) {
            $('#idlm-reset-confirm-btn').prop('disabled', false);
        } else {
            $('#idlm-reset-confirm-btn').prop('disabled', true);
        }
    });

    // Hide reset modal on cancel
    $(document).on('click', '#idlm-reset-cancel-btn, #idlm-reset-close-btn', function(e) {
        e.preventDefault();
        $('#idlm-reset-modal').removeClass('active');
    });

    // Confirm and execute database reset
    $(document).on('click', '#idlm-reset-confirm-btn', function(e) {
        e.preventDefault();
        var $btn = $(this);
        var inputVal = $('#idlm-reset-confirm-input').val().trim();

        if (inputVal !== String(expectedLeadsCount)) {
            alert('Incorrect verification count. Reset cancelled.');
            return;
        }

        $btn.prop('disabled', true).text('Resetting Database...');

        $.ajax({
            url: idlm_vars.ajax_url,
            type: 'POST',
            data: {
                action: 'idlm_reset_crm_data',
                nonce: idlm_vars.nonce,
                confirm_count: inputVal
            },
            success: function(response) {
                $btn.prop('disabled', false).text('Confirm Reset');
                $('#idlm-reset-modal').removeClass('active');
                if (response.success) {
                    showToast(response.data.message, 'success');
                    // Reload settings page to refresh UI state
                    setTimeout(function() {
                        location.reload();
                    }, 1500);
                } else {
                    showToast(response.data.message || 'Database reset failed.', 'error');
                }
            },
            error: function() {
                $btn.prop('disabled', false).text('Confirm Reset');
                $('#idlm-reset-modal').removeClass('active');
                showToast('Server error trying to reset CRM database.', 'error');
            }
        });
    });

    // Inline Edit/Delete manual comments in activity timeline
    $(document).on('click', '.idlm-edit-comment-link', function(e) {
        e.preventDefault();
        var $link = $(this);
        var logId = $link.attr('data-id');
        var $item = $link.closest('.idlm-timeline-item');
        var $details = $item.find('.idlm-timeline-details');
        var $actions = $item.find('.idlm-timeline-actions');

        // Check if already editing
        if ($details.find('.idlm-inline-edit-wrapper').length > 0) {
            return;
        }

        var originalText = $details.data('original-text');
        if (originalText === undefined) {
            originalText = $details.text().trim();
            $details.data('original-text', originalText);
        }

        // Hide other actions for this comment
        $actions.hide();

        var editHtml = 
            '<div class="idlm-inline-edit-wrapper" style="margin-top: 8px;">' +
                '<textarea class="idlm-input idlm-inline-edit-textarea" style="width: 100%; min-height: 80px; margin-bottom: 6px; resize: vertical; box-sizing: border-box;"></textarea>' +
                '<div style="display: flex; gap: 8px; justify-content: flex-end;">' +
                    '<button class="idlm-btn idlm-btn-secondary idlm-inline-edit-cancel" style="padding: 4px 10px; font-size: 12px; height: auto;">Cancel</button>' +
                    '<button class="idlm-btn idlm-btn-primary idlm-inline-edit-save" style="padding: 4px 10px; font-size: 12px; height: auto;">Save</button>' +
                '</div>' +
            '</div>';

        $details.html(editHtml);
        $details.find('.idlm-inline-edit-textarea').val(originalText).focus();
    });

    $(document).on('click', '.idlm-inline-edit-cancel', function(e) {
        e.preventDefault();
        var $btn = $(this);
        var $item = $btn.closest('.idlm-timeline-item');
        var $details = $item.find('.idlm-timeline-details');
        var $actions = $item.find('.idlm-timeline-actions');
        var originalText = $details.data('original-text');

        $details.text(originalText);
        $actions.show();
    });

    $(document).on('click', '.idlm-inline-edit-save', function(e) {
        e.preventDefault();
        var $btn = $(this);
        var $item = $btn.closest('.idlm-timeline-item');
        var $details = $item.find('.idlm-timeline-details');
        var $actions = $item.find('.idlm-timeline-actions');
        var logId = $item.find('.idlm-edit-comment-link').attr('data-id');
        var newComment = $details.find('.idlm-inline-edit-textarea').val().trim();

        if (!newComment) {
            showToast('Comment cannot be empty.', 'error');
            return;
        }

        $btn.prop('disabled', true).text('Saving...');

        $.ajax({
            url: idlm_vars.ajax_url,
            type: 'POST',
            data: {
                action: 'idlm_edit_activity_comment',
                nonce: idlm_vars.nonce,
                log_id: logId,
                comment: newComment
            },
            success: function(response) {
                console.log("Edit comment response:", response);
                if (response && response.success) {
                    var msg = (response.data && response.data.message) ? response.data.message : 'Comment updated successfully.';
                    showToast(msg, 'success');
                    var leadId = $('#idlm-edit-lead-form input[name="lead_id"]').val();
                    if (leadId) {
                        loadLeadActivityLog(leadId, true);
                    }
                } else {
                    var errMsg = (response && response.data && response.data.message) ? response.data.message : 'Failed to update comment.';
                    showToast(errMsg, 'error');
                    $btn.prop('disabled', false).text('Save');
                }
            },
            error: function(xhr, status, error) {
                console.error("Edit comment AJAX error:", status, error);
                showToast('Server error trying to update comment.', 'error');
                $btn.prop('disabled', false).text('Save');
            }
        });
    });

    $(document).on('click', '.idlm-delete-comment-link', function(e) {
        e.preventDefault();
        var $link = $(this);
        var logId = $link.attr('data-id');

        if (!confirm('Are you sure you want to delete this comment?')) {
            return;
        }

        $.ajax({
            url: idlm_vars.ajax_url,
            type: 'POST',
            data: {
                action: 'idlm_delete_activity_comment',
                nonce: idlm_vars.nonce,
                log_id: logId
            },
            success: function(response) {
                console.log("Delete comment response:", response);
                if (response && response.success) {
                    var msg = (response.data && response.data.message) ? response.data.message : 'Comment deleted successfully.';
                    showToast(msg, 'success');
                    var leadId = $('#idlm-edit-lead-form input[name="lead_id"]').val();
                    if (leadId) {
                        loadLeadActivityLog(leadId, true);
                    }
                } else {
                    var errMsg = (response && response.data && response.data.message) ? response.data.message : 'Failed to delete comment.';
                    showToast(errMsg, 'error');
                }
            },
            error: function(xhr, status, error) {
                console.error("Delete comment AJAX error:", status, error);
                showToast('Server error trying to delete comment.', 'error');
            }
        });
    });

    // Click on pending call pill filters table
    $(document).on('click', '#idlm-pending-calls-pill', function(e) {
        e.preventDefault();
        
        // Reset all other filter fields first
        $('#filter-search').val('');
        $('#filter-date-range').val('');
        $('#filter-stage').val('');
        $('#filter-source').val('');
        $('#filter-rep').val('');
        $('#filter-call-status').val('');
        $('#filter-quality').val('');
        
        // Set followup status to pending_calls and trigger change to reload table
        $('#filter-followup').val('pending_calls').change();
        
        // Show secondary filters container and activate toggle button
        $('#idlm-secondary-filters-container').slideDown(200);
        $('#btn-toggle-more-filters').addClass('active');
    });

    // Click on unread WhatsApp pill filters the table to unread replies.
    $(document).on('click', '#idlm-whatsapp-unread-pill', function(e) {
        e.preventDefault();

        $('#filter-search').val('');
        $('#filter-date-range').val('');
        $('#filter-stage').val('');
        $('#filter-source').val('');
        $('#filter-rep').val('');
        $('#filter-call-status').val('');
        $('#filter-quality').val('');
        $('#filter-followup').val('unread_whatsapp').change();

        $('#idlm-secondary-filters-container').slideDown(200);
        $('#btn-toggle-more-filters').addClass('active');
    });

    // Ensure all file links open in a new tab
    $(document).on('click', '.idlm-file-link', function(e) {
        e.preventDefault();
        var href = $(this).attr('href');
        if (href) {
            window.open(href, '_blank');
        }
    });

    // Track lead details form edits
    $(document).on('input change', '#idlm-edit-lead-form :input', function() {
        var name = $(this).attr('name');
        if (name && name !== 'lead_id') {
            var label = $(this).closest('.idlm-form-group').find('label').text().replace('*', '').trim();
            if (!label) {
                label = name.replace('_', ' ');
            }
            window.idlmChangedFields[name] = label;
            window.idlmHasUnsavedChanges = true;
        }
    });

    // Unsaved Changes Confirmation Modal Event Handlers
    $(document).on('click', '#idlm-unsaved-save-exit', function() {
        var $btn = $(this);
        $btn.prop('disabled', true).text('Saving...');
        
        var formData = $('#idlm-edit-lead-form').serialize();
        var leadId = $('#idlm-edit-lead-form input[name="lead_id"]').val();
        
        $.ajax({
            url: idlm_vars.ajax_url,
            type: 'POST',
            data: {
                action: 'idlm_save_lead_details',
                nonce: idlm_vars.nonce,
                form_data: formData
            },
            success: function(response) {
                $btn.prop('disabled', false).text('Save & Exit');
                if (response.success) {
                    window.idlmHasUnsavedChanges = false;
                    window.idlmChangedFields = {};
                    $('#idlm-unsaved-changes-modal').removeClass('active');
                    jQuery('#idlm-lead-drawer').removeClass('active');
                    updateBodyScrollLock();
                    showToast(response.data.message, 'success');
                    loadLeadsTable(currentPage);
                } else {
                    alert(response.data.message || 'Save failed.');
                }
            },
            error: function() {
                $btn.prop('disabled', false).text('Save & Exit');
                alert('Server error saving lead updates.');
            }
        });
    });

    $(document).on('click', '#idlm-unsaved-discard-exit', function() {
        window.idlmHasUnsavedChanges = false;
        window.idlmChangedFields = {};
        $('#idlm-unsaved-changes-modal').removeClass('active');
        jQuery('#idlm-lead-drawer').removeClass('active');
        updateBodyScrollLock();
    });

    $(document).on('click', '#idlm-unsaved-cancel, #idlm-unsaved-modal-close', function() {
        $('#idlm-unsaved-changes-modal').removeClass('active');
        updateBodyScrollLock();
    });

    // Esc key listener to close active pop-ups (modals & drawer)
    $(document).on('keydown', function(e) {
        if (e.key === 'Escape' || e.keyCode === 27) {
            // Close frontmost active element
            if ($('#idlm-unsaved-changes-modal').hasClass('active')) {
                $('#idlm-unsaved-changes-modal').removeClass('active');
                updateBodyScrollLock();
                return;
            }
            if ($('#idlm-reset-modal').hasClass('active')) {
                $('#idlm-reset-modal').removeClass('active');
                updateBodyScrollLock();
                return;
            }
            if ($('#idlm-deal-value-modal').hasClass('active')) {
                $('#idlm-deal-value-modal-cancel').trigger('click');
                return;
            }
            if ($('#idlm-callback-modal').hasClass('active')) {
                $('#idlm-callback-modal-cancel').trigger('click');
                return;
            }
            if ($('#idlm-lead-modal').hasClass('active')) {
                closeLeadModal();
                return;
            }
            if ($('#idlm-import-modal').hasClass('active')) {
                closeImportModal();
                return;
            }
            if ($('#idlm-lead-drawer').hasClass('active')) {
                closeLeadDrawer();
                return;
            }
        }
    });

    // Expose table reloading and current page for global scope access
    window.idlmLoadLeadsTable = loadLeadsTable;
    window.idlmCurrentPage = function() { return currentPage; };
    window.idlmShowToast = showToast;
});

// Helper function to manage body scroll lock when modals or drawers are open
function updateBodyScrollLock() {
    var $ = jQuery;
    if ($('.idlm-modal.active, .idlm-drawer.active, #idlm-import-modal.active').length > 0) {
        $('body').addClass('idlm-modal-open');
    } else {
        $('body').removeClass('idlm-modal-open');
    }
}

// Global modal and drawer pings available inside table scopes
function openLeadModal() {
    jQuery('#idlm-lead-modal').addClass('active');
    updateBodyScrollLock();
}

function closeLeadModal() {
    jQuery('#idlm-lead-modal').removeClass('active');
    updateBodyScrollLock();
}

function openLeadDrawer(leadId, startTab) {
    window.idlmHasUnsavedChanges = false;
    window.idlmChangedFields = {};
    var $ = jQuery;
    var activeTab = startTab || '';
    var currentLeadId = $('#idlm-edit-lead-form input[name="lead_id"]').val();
    
    // Only preserve active tab if we are reloading the same lead (e.g., after comment or edit save) and startTab isn't specified
    if (!startTab && currentLeadId && parseInt(currentLeadId) === parseInt(leadId)) {
        var activeTabId = $('.idlm-drawer-tab.active').attr('onclick');
        if (activeTabId) {
            var match = activeTabId.match(/'([^']+)'/);
            if (match && match[1]) {
                activeTab = match[1];
            }
        }
    }

    $('#idlm-lead-drawer').addClass('active');
    updateBodyScrollLock();
    
    $('#idlm-drawer-content-wrapper-inner').html('<div style="text-align:center; padding:50px;"><span class="spinner is-active" style="float:none;"></span> Loading Detailed Profile...</div>');
    
    $.ajax({
        url: idlm_vars.ajax_url,
        type: 'POST',
        data: {
            action: 'idlm_get_lead_details',
            nonce: idlm_vars.nonce,
            lead_id: leadId,
            workspace_url: $('[data-idlm-workspace]').length ? window.location.href : ''
        },
        success: function(response) {
            if (response.success) {
                $('#idlm-drawer-content-wrapper-inner').html(response.data.html);
                if (response.data.whatsapp_marked_read) {
                    $('.idlm-whatsapp-row-badge[data-lead-id="' + leadId + '"]').remove();
                }
                if (response.data.hasOwnProperty('unread_whatsapp_count') && typeof window.idlmUpdateUnreadWhatsAppPill === 'function') {
                    window.idlmUpdateUnreadWhatsAppPill(response.data.unread_whatsapp_count);
                }
                
                // Restore active tab if saved
                if (activeTab) {
                    $('.idlm-drawer-tab-content').removeClass('active');
                    $('.idlm-drawer-tab').removeClass('active');
                    $('#' + activeTab).addClass('active');
                    $('.idlm-drawer-tab').each(function() {
                        if ($(this).attr('onclick') && $(this).attr('onclick').indexOf(activeTab) !== -1) {
                            $(this).addClass('active');
                        }
                    });
                    
                    if (activeTab === 'drawer-tab-tasks') {
                        loadLeadTasks(leadId);
                    }
                }
                
                // Timeline comments are already loaded inline in PHP detail view
            } else {
                $('#idlm-drawer-content-wrapper-inner').html('<div class="idlm-alert idlm-alert-warning">' + response.data.message + '</div>');
            }
        },
        error: function() {
            $('#idlm-drawer-content-wrapper-inner').html('<div class="idlm-alert idlm-alert-warning">Server connection error.</div>');
        }
    });
}

function closeLeadDrawer() {
    var $ = jQuery;
    if (window.idlmHasUnsavedChanges) {
        var fieldsArray = Object.values(window.idlmChangedFields);
        if (fieldsArray.length > 0) {
            $('#idlm-unsaved-fields-list').text('Edits made to: ' + fieldsArray.join(', '));
        } else {
            $('#idlm-unsaved-fields-list').text('');
        }
        $('#idlm-unsaved-changes-modal').addClass('active');
        updateBodyScrollLock();
    } else {
        jQuery('#idlm-lead-drawer').removeClass('active');
        updateBodyScrollLock();
    }
}

function switchDrawerTab(evt, tabId) {
    var $ = jQuery;
    $('.idlm-drawer-tab-content').removeClass('active');
    $('.idlm-drawer-tab').removeClass('active');
    $('#' + tabId).addClass('active');
    $(evt.currentTarget).addClass('active');

    if (tabId === 'drawer-tab-activity') {
        var leadId = $('#idlm-edit-lead-form input[name="lead_id"]').val();
        if (leadId) {
            loadLeadActivityLog(leadId);
        }
    }
    if (tabId === 'drawer-tab-tasks') {
        var leadId = $('#idlm-edit-lead-form input[name="lead_id"]').val();
        if (leadId) {
            loadLeadTasks(leadId);
        }
    }
}

function loadLeadActivityLog(leadId, forceRefresh) {
    var $ = jQuery;
    var $container = $('#idlm-timeline-container-inner');
    var $detailsContainer = $('#idlm-timeline-container-details-inner');
    
    if (!$container.length && !$detailsContainer.length) return;

    if ($container.hasClass('loaded') && !forceRefresh) {
        return;
    }

    if ($container.length) {
        $container.html('<div style="text-align:center; padding:30px;"><span class="spinner is-active" style="float:none;"></span> Loading timeline...</div>').removeClass('loaded');
    }
    if ($detailsContainer.length) {
        $detailsContainer.html('<div style="text-align:center; padding:15px;"><span class="spinner is-active" style="float:none;"></span> Loading...</div>').removeClass('loaded');
    }

    $.ajax({
        url: idlm_vars.ajax_url,
        type: 'POST',
        data: {
            action: 'idlm_get_lead_activity',
            nonce: idlm_vars.nonce,
            lead_id: leadId
        },
        success: function(response) {
            if (response.success) {
                if ($container.length) {
                    $container.html(response.data.html).addClass('loaded');
                }
                if ($detailsContainer.length) {
                    $detailsContainer.html(response.data.comments_html).addClass('loaded');
                }
            } else {
                if ($container.length) {
                    $container.html('<div class="idlm-alert idlm-alert-warning">' + response.data.message + '</div>');
                }
                if ($detailsContainer.length) {
                    $detailsContainer.html('<div class="idlm-alert idlm-alert-warning">' + response.data.message + '</div>');
                }
            }
        },
        error: function() {
            if ($container.length) {
                $container.html('<div class="idlm-alert idlm-alert-warning">Server connection error.</div>');
            }
            if ($detailsContainer.length) {
                $detailsContainer.html('<div class="idlm-alert idlm-alert-warning">Server connection error.</div>');
            }
        }
    });
}

function saveActivityComment(btn, leadId) {
    var $ = jQuery;
    var $btn = $(btn);
    var commentText = $('#idlm-comment-text').val();
    if (!commentText) return;

    $btn.prop('disabled', true).text('Adding...');

    $.ajax({
        url: idlm_vars.ajax_url,
        type: 'POST',
        data: {
            action: 'idlm_save_activity_comment',
            nonce: idlm_vars.nonce,
            lead_id: leadId,
            comment: commentText
        },
        success: function(response) {
            if (response.success) {
                $('#idlm-comment-text').val('');
                loadLeadActivityLog(leadId, true);
            }
        },
        complete: function() {
            $btn.prop('disabled', false).text('Add Comment');
        }
    });
}

function saveActivityCommentDetails(btn, leadId) {
    var $ = jQuery;
    var $btn = $(btn);
    var commentText = $('#idlm-comment-text-details').val();
    if (!commentText) return;

    $btn.prop('disabled', true).text('Adding...');

    $.ajax({
        url: idlm_vars.ajax_url,
        type: 'POST',
        data: {
            action: 'idlm_save_activity_comment',
            nonce: idlm_vars.nonce,
            lead_id: leadId,
            comment: commentText
        },
        success: function(response) {
            if (response.success) {
                $('#idlm-comment-text-details').val('');
                loadLeadActivityLog(leadId, true);
            }
        },
        complete: function() {
            $btn.prop('disabled', false).text('Add Comment');
        }
    });
}

function idlmCopyToClipboard(button, text) {
    // Legacy support if called in old format: idlmCopyToClipboard(text, label)
    if (typeof button === 'string') {
        var legacyText = button;
        var legacyLabel = text || 'Text';
        copyTextToClipboardLegacy(legacyText, legacyLabel);
        return;
    }

    var $btn = jQuery(button);
    var originalText = $btn.text() || 'Copy';

    if (navigator.clipboard && window.isSecureContext) {
        navigator.clipboard.writeText(text).then(function() {
            showSuccessFeedback($btn, originalText);
        }).catch(function() {
            fallbackCopy($btn, text, originalText);
        });
    } else {
        fallbackCopy($btn, text, originalText);
    }

    function showSuccessFeedback($buttonEl, origText) {
        $buttonEl.text('Copied!');
        $buttonEl.addClass('idlm-copied');
        $buttonEl.prop('disabled', true);
        setTimeout(function() {
            $buttonEl.text(origText);
            $buttonEl.removeClass('idlm-copied');
            $buttonEl.prop('disabled', false);
        }, 2000);
    }

    function fallbackCopy($buttonEl, textVal, origText) {
        var textArea = document.createElement("textarea");
        textArea.value = textVal;
        
        textArea.style.position = "fixed";
        textArea.style.left = "-999999px";
        textArea.style.top = "-999999px";
        document.body.appendChild(textArea);
        textArea.focus();
        textArea.select();
        
        try {
            var successful = document.execCommand('copy');
            if (successful) {
                showSuccessFeedback($buttonEl, origText);
            } else {
                alert('Failed to copy. Please manually select and copy the text.');
            }
        } catch (err) {
            alert('Failed to copy. Please manually select and copy the text.');
        }
        document.body.removeChild(textArea);
    }

    function copyTextToClipboardLegacy(textVal, labelVal) {
        if (navigator.clipboard && window.isSecureContext) {
            navigator.clipboard.writeText(textVal).then(function() {
                alert(labelVal + ' copied to clipboard!');
            }).catch(function() {
                fallbackCopyLegacy(textVal, labelVal);
            });
        } else {
            fallbackCopyLegacy(textVal, labelVal);
        }
    }

    function fallbackCopyLegacy(textVal, labelVal) {
        var textArea = document.createElement("textarea");
        textArea.value = textVal;
        textArea.style.position = "fixed";
        textArea.style.left = "-999999px";
        textArea.style.top = "-999999px";
        document.body.appendChild(textArea);
        textArea.focus();
        textArea.select();
        try {
            var successful = document.execCommand('copy');
            if (successful) {
                alert(labelVal + ' copied to clipboard!');
            } else {
                alert('Failed to copy. Please manually select and copy the text.');
            }
        } catch (err) {
            alert('Failed to copy. Please manually select and copy the text.');
        }
        document.body.removeChild(textArea);
    }
}

function deleteSingleLead(leadId) {
    var $ = jQuery;
    if (!confirm('Are you absolutely sure you want to delete this lead? This action cannot be undone and will also delete all associated activity and CAPI logs.')) {
        return;
    }

    $.ajax({
        url: idlm_vars.ajax_url,
        type: 'POST',
        data: {
            action: 'idlm_delete_lead',
            nonce: idlm_vars.nonce,
            lead_id: leadId
        },
        success: function(response) {
            if (response.success) {
                closeLeadDrawer();
                if (typeof window.idlmShowToast === 'function') {
                    window.idlmShowToast(response.data.message || 'Lead successfully deleted.', 'success');
                } else {
                    alert(response.data.message || 'Lead successfully deleted.');
                }
                if (typeof window.idlmLoadLeadsTable === 'function') {
                    var page = (typeof window.idlmCurrentPage === 'function') ? window.idlmCurrentPage() : 1;
                    window.idlmLoadLeadsTable(page);
                }
            } else {
                if (typeof window.idlmShowToast === 'function') {
                    window.idlmShowToast(response.data.message || 'Failed to delete lead.', 'error');
                } else {
                    alert(response.data.message || 'Failed to delete lead.');
                }
            }
        },
        error: function() {
            if (typeof window.idlmShowToast === 'function') {
                window.idlmShowToast('Server error trying to delete lead.', 'error');
            } else {
                alert('Server error trying to delete lead.');
            }
        }
    });
}

function openImportModal() {
    var $ = jQuery;
    $('.idlm-import-step-content').removeClass('active');
    $('#import-step-content-1').addClass('active');
    $('.idlm-import-step-item').removeClass('active').removeClass('completed');
    $('#import-step-nav-1').addClass('active');
    
    $('#idlm-import-file-input').val('');
    $('#idlm-import-progress-fill').css('width', '0%');
    $('#import-progress-percent').text('0%');
    $('#import-progress-text').text('Initializing import...');
    $('#idlm-import-status-list').html('');
    $('#import-btn-close-final').hide();
    
    $('#idlm-import-modal').addClass('active');
    updateBodyScrollLock();
}

function closeImportModal() {
    jQuery('#idlm-import-modal').removeClass('active');
    updateBodyScrollLock();
}

function openRoutingSettingsModal() {
    var modal = jQuery('#idlm-routing-modal');
    modal.find('.idlm-routing-modal-body').scrollTop(0);
    modal.addClass('active');
    window.requestAnimationFrame(function() {
        modal.find('.idlm-routing-modal-body').scrollTop(0);
    });
    updateBodyScrollLock();
}

function closeRoutingModal() {
    jQuery('#idlm-routing-modal').removeClass('active');
    updateBodyScrollLock();
}

function openExportModal() {
    var $ = jQuery;
    var selectedIds = [];
    $('.idlm-row-selector:checked').each(function() {
        selectedIds.push($(this).val());
    });

    var count = selectedIds.length;
    $('#idlm-export-selected-count').text(count);

    if (count > 0) {
        $('#idlm-export-scope-selected').prop('disabled', false);
        $('#idlm-export-scope-selected-label').css('opacity', '1').css('cursor', 'pointer');
        $('#idlm-export-scope-selected').prop('checked', true);
        // Dim the filters section since "selected" is active
        $('#idlm-export-filters-section').css('opacity', '0.5').find('input, select').prop('disabled', true);
    } else {
        $('#idlm-export-scope-selected').prop('disabled', true);
        $('#idlm-export-scope-selected-label').css('opacity', '0.5').css('cursor', 'not-allowed');
        $('#idlm-export-scope-filtered').prop('checked', true);
        // Enable the filters section since "filtered" is active
        $('#idlm-export-filters-section').css('opacity', '1').find('input, select').prop('disabled', false);
    }

    // Pre-populate filters from the main UI table filters
    $('#export-filter-search').val($('#filter-search').val() || '');
    $('#export-filter-date-basis').val($('#filter-date-basis').val() || 'assigned_at');
    $('#export-filter-date-range').val($('#filter-date-range').val() || '');
    $('#export-filter-source').val($('#filter-source').val() || '');
    $('#export-filter-stage').val($('#filter-stage').val() || '');
    $('#export-filter-call-status').val($('#filter-call-status').val() || '');
    if ($('#filter-rep').length) {
        $('#export-filter-rep').val($('#filter-rep').val() || '');
    }
    $('#export-filter-quality').val($('#filter-quality').val() || '');
    $('#export-filter-followup').val($('#filter-followup').val() || '');

    $('#idlm-export-modal').addClass('active');
    updateBodyScrollLock();
}

function closeExportModal() {
    jQuery('#idlm-export-modal').removeClass('active');
    updateBodyScrollLock();
}

function toggleRoutingModalSourcePanel(val) {
    if (val === 'source_based') {
        jQuery('#idlm-routing-source-mapping-subpanel').show();
    } else {
        jQuery('#idlm-routing-source-mapping-subpanel').hide();
    }
}

jQuery(document).ready(function($) {
    $(document).on('submit', '#idlm-routing-settings-form', function(e) {
        e.preventDefault();
        var form = $(this);
        var submitBtn = form.find('button[type="submit"]');
        var originalText = submitBtn.text();
        
        submitBtn.prop('disabled', true).text('Saving...');
        
        $.ajax({
            url: (typeof idlm_vars !== 'undefined' && idlm_vars.ajax_url) ? idlm_vars.ajax_url : (typeof ajaxurl !== 'undefined' ? ajaxurl : '/wp-admin/admin-ajax.php'),
            method: 'POST',
            data: form.serialize(),
            success: function(response) {
                submitBtn.prop('disabled', false).text(originalText);
                if (response.success) {
                    if (typeof window.idlmShowToast === 'function') {
                        window.idlmShowToast(response.data || 'Routing settings saved successfully!', 'success');
                    } else {
                        alert(response.data || 'Routing settings saved successfully!');
                    }
                    closeRoutingModal();
                    // Reload the page or list to reflect settings changes (like active reps dropdown updates)
                    location.reload();
                } else {
                    if (typeof window.idlmShowToast === 'function') {
                        window.idlmShowToast(response.data || 'Failed to save routing settings.', 'error');
                    } else {
                        alert(response.data || 'Failed to save routing settings.');
                    }
                }
            },
            error: function() {
                submitBtn.prop('disabled', false).text(originalText);
                if (typeof window.idlmShowToast === 'function') {
                    window.idlmShowToast('Server error trying to save routing settings.', 'error');
                } else {
                    alert('Server error trying to save routing settings.');
                }
            }
        });
    });

    // Load active tasks count on page load
    updateFloatingTasksCount();

    // Close global sidebar when clicking close button
    $(document).on('click', '#idlm-global-tasks-sidebar-close', function() {
        toggleGlobalTasksSidebar();
    });
});

// Task Manager & Global Sidebar functions
var globalTasksFilterStatus = 'pending';

function loadLeadTasks(leadId) {
    var $ = jQuery;
    var $container = $('#idlm-drawer-tasks-list-container');
    if (!$container.length) return;

    $container.html('<div style="text-align:center; padding:30px;"><span class="spinner is-active" style="float:none;"></span> Loading tasks...</div>');

    $.ajax({
        url: (typeof idlm_vars !== 'undefined' && idlm_vars.ajax_url) ? idlm_vars.ajax_url : (typeof ajaxurl !== 'undefined' ? ajaxurl : '/wp-admin/admin-ajax.php'),
        method: 'POST',
        data: {
            action: 'idlm_get_lead_tasks',
            lead_id: leadId,
            nonce: idlm_vars.nonce
        },
        success: function(response) {
            if (response.success) {
                var tasks = response.data.tasks || [];
                renderLeadTasks(tasks, $container);
            } else {
                $container.html('<div style="text-align:center; color:var(--idlm-danger); padding:20px;">' + (response.data.message || 'Failed to load tasks.') + '</div>');
            }
        },
        error: function() {
            $container.html('<div style="text-align:center; color:var(--idlm-danger); padding:20px;">Server error loading tasks.</div>');
        }
    });
}

function renderLeadTasks(tasks, $container) {
    var $ = jQuery;
    $('#idlm-task-count-badge').text(tasks.length + ' task' + (tasks.length === 1 ? '' : 's'));

    if (tasks.length === 0) {
        $container.html('<div style="text-align: center; color: var(--idlm-text-muted); padding: 40px 0;" id="idlm-tasks-empty-state"><span class="dashicons dashicons-saved" style="font-size: 32px; width: 32px; height: 32px; margin-bottom: 8px; color: var(--idlm-border-strong); display: inline-block;"></span><p style="margin: 0; font-size: 12px;">No active tasks for this lead.</p></div>');
        return;
    }

    var html = '<ul class="idlm-task-list">';
    tasks.forEach(function(task) {
        var isCompleted = task.status === 'completed';
        var priorityClass = 'priority-' + task.priority.toLowerCase();
        var dueHtml = '';
        if (task.due_date_formatted) {
            var overdueClass = task.is_overdue ? ' overdue' : '';
            var alertIcon = task.is_overdue ? '⚠️ ' : '📅 ';
            dueHtml = '<span class="idlm-task-due' + overdueClass + '">' + alertIcon + task.due_date_formatted + '</span>';
        }

        html += '<li class="idlm-task-item ' + (isCompleted ? 'completed ' : '') + priorityClass + '" data-task-id="' + task.id + '">';
        html += '<input type="checkbox" class="idlm-task-checkbox" ' + (isCompleted ? 'checked' : '') + ' onchange="toggleTaskStatus(this, ' + task.id + ')">';
        html += '<div class="idlm-task-content">';
        html += '<p class="idlm-task-text">' + escapeHtml(task.title) + '</p>';
        html += '<div class="idlm-task-meta">';
        html += '<span class="idlm-task-type-badge">' + escapeHtml(task.task_type) + '</span>';
        if (dueHtml) html += dueHtml;
        html += '</div>';
        html += '</div>';
        html += '<div class="idlm-task-actions">';
        html += '<button type="button" class="idlm-task-delete-btn" onclick="deleteLeadTask(' + task.id + ', ' + task.lead_id + ')" title="Delete task">&times;</button>';
        html += '</div>';
        html += '</li>';
    });
    html += '</ul>';
    $container.html(html);
}

function addLeadTask(leadId) {
    var $ = jQuery;
    var title = $('#idlm-new-task-title').val().trim();
    var dueDate = $('#idlm-new-task-due').val();
    var priority = $('#idlm-new-task-priority').val();
    var taskType = $('#idlm-new-task-type').val();

    if (!title) {
        alert('Please enter a task title.');
        return;
    }

    var $btn = $('#idlm-btn-add-task');
    var originalText = $btn.text();
    $btn.prop('disabled', true).text('Adding...');

    $.ajax({
        url: (typeof idlm_vars !== 'undefined' && idlm_vars.ajax_url) ? idlm_vars.ajax_url : (typeof ajaxurl !== 'undefined' ? ajaxurl : '/wp-admin/admin-ajax.php'),
        method: 'POST',
        data: {
            action: 'idlm_add_lead_task',
            lead_id: leadId,
            title: title,
            due_date: dueDate,
            priority: priority,
            task_type: taskType,
            nonce: idlm_vars.nonce
        },
        success: function(response) {
            $btn.prop('disabled', false).text(originalText);
            if (response.success) {
                $('#idlm-new-task-title').val('');
                $('#idlm-new-task-due').val('');
                $('#idlm-new-task-priority').val('Medium');
                $('#idlm-new-task-type').val('General');
                if (typeof window.idlmShowToast === 'function') {
                    window.idlmShowToast('Task added successfully!', 'success');
                }
                loadLeadTasks(leadId);
                refreshLeadsTableIfNeeded();
                updateFloatingTasksCount();
            } else {
                alert(response.data.message || 'Failed to add task.');
            }
        },
        error: function() {
            $btn.prop('disabled', false).text(originalText);
            alert('Server error adding task.');
        }
    });
}

function toggleTaskStatus(checkbox, taskId) {
    var $ = jQuery;
    var $item = $(checkbox).closest('.idlm-task-item');
    var isChecked = checkbox.checked;
    var newStatus = isChecked ? 'completed' : 'pending';

    $item.toggleClass('completed', isChecked);

    $.ajax({
        url: (typeof idlm_vars !== 'undefined' && idlm_vars.ajax_url) ? idlm_vars.ajax_url : (typeof ajaxurl !== 'undefined' ? ajaxurl : '/wp-admin/admin-ajax.php'),
        method: 'POST',
        data: {
            action: 'idlm_toggle_task_status',
            task_id: taskId,
            status: newStatus,
            nonce: idlm_vars.nonce
        },
        success: function(response) {
            if (response.success) {
                if (typeof window.idlmShowToast === 'function') {
                    window.idlmShowToast('Task status updated.', 'success');
                }
                refreshLeadsTableIfNeeded();
                updateFloatingTasksCount();
                var leadId = $('#idlm-edit-lead-form input[name="lead_id"]').val();
                if (leadId) {
                    loadLeadTasks(leadId);
                }
                if ($('#idlm-global-tasks-sidebar').hasClass('active')) {
                    loadGlobalTasks();
                }
            } else {
                checkbox.checked = !isChecked;
                $item.toggleClass('completed', !isChecked);
                alert(response.data.message || 'Failed to update task.');
            }
        },
        error: function() {
            checkbox.checked = !isChecked;
            $item.toggleClass('completed', !isChecked);
            alert('Server error updating task status.');
        }
    });
}

function deleteLeadTask(taskId, leadId) {
    var $ = jQuery;
    if (!confirm('Are you sure you want to delete this task?')) return;

    $.ajax({
        url: (typeof idlm_vars !== 'undefined' && idlm_vars.ajax_url) ? idlm_vars.ajax_url : (typeof ajaxurl !== 'undefined' ? ajaxurl : '/wp-admin/admin-ajax.php'),
        method: 'POST',
        data: {
            action: 'idlm_delete_lead_task',
            task_id: taskId,
            nonce: idlm_vars.nonce
        },
        success: function(response) {
            if (response.success) {
                if (typeof window.idlmShowToast === 'function') {
                    window.idlmShowToast('Task deleted successfully.', 'success');
                }
                loadLeadTasks(leadId);
                refreshLeadsTableIfNeeded();
                updateFloatingTasksCount();
                if ($('#idlm-global-tasks-sidebar').hasClass('active')) {
                    loadGlobalTasks();
                }
            } else {
                alert(response.data.message || 'Failed to delete task.');
            }
        },
        error: function() {
            alert('Server error deleting task.');
        }
    });
}

function toggleGlobalTasksSidebar() {
    var $ = jQuery;
    var $sidebar = $('#idlm-global-tasks-sidebar');
    if ($sidebar.hasClass('active')) {
        $sidebar.removeClass('active');
        jQuery('body').removeClass('idlm-modal-open');
    } else {
        $sidebar.addClass('active');
        loadGlobalTasks();
    }
}

function filterGlobalTasks(status) {
    var $ = jQuery;
    globalTasksFilterStatus = status;
    $('.idlm-global-sidebar-filter-btn').removeClass('active');
    if (status === 'pending') {
        $('#idlm-global-tasks-filter-pending').addClass('active');
    } else {
        $('#idlm-global-tasks-filter-completed').addClass('active');
    }
    loadGlobalTasks();
}

function loadGlobalTasks() {
    var $ = jQuery;
    var $body = $('#idlm-global-tasks-sidebar-body');
    if (!$body.length) return;

    $body.html('<div style="text-align: center; color: var(--idlm-text-muted); padding: 40px 0;"><span class="spinner is-active" style="float:none;"></span> Loading tasks...</div>');

    $.ajax({
        url: (typeof idlm_vars !== 'undefined' && idlm_vars.ajax_url) ? idlm_vars.ajax_url : (typeof ajaxurl !== 'undefined' ? ajaxurl : '/wp-admin/admin-ajax.php'),
        method: 'POST',
        data: {
            action: 'idlm_get_rep_tasks',
            status: globalTasksFilterStatus,
            nonce: idlm_vars.nonce
        },
        success: function(response) {
            if (response.success) {
                var tasks = response.data.tasks || [];
                renderGlobalTasksList(tasks, $body);
            } else {
                $body.html('<div style="text-align:center; color:var(--idlm-danger); padding:20px;">' + (response.data.message || 'Failed to load tasks.') + '</div>');
            }
        },
        error: function() {
            $body.html('<div style="text-align:center; color:var(--idlm-danger); padding:20px;">Server error loading tasks.</div>');
        }
    });
}

function renderGlobalTasksList(tasks, $body) {
    var $ = jQuery;
    if (tasks.length === 0) {
        $body.html('<div style="text-align: center; color: var(--idlm-text-muted); padding: 40px 0;"><span class="dashicons dashicons-saved" style="font-size: 32px; width: 32px; height: 32px; margin-bottom: 8px; color: var(--idlm-border-strong); display: inline-block;"></span><p style="margin: 0; font-size: 13px;">No tasks found.</p></div>');
        return;
    }

    var groupedTasks = {};
    tasks.forEach(function(task) {
        if (!groupedTasks[task.lead_id]) {
            groupedTasks[task.lead_id] = {
                lead_name: task.lead_name,
                tasks: []
            };
        }
        groupedTasks[task.lead_id].tasks.push(task);
    });

    var html = '';
    for (var leadId in groupedTasks) {
        var group = groupedTasks[leadId];
        html += '<div class="idlm-sidebar-group" data-lead-id="' + leadId + '">';
        html += '<h4 class="idlm-sidebar-group-title" onclick="openLeadDrawer(' + leadId + ', \'drawer-tab-tasks\'); toggleGlobalTasksSidebar();">' + escapeHtml(group.lead_name) + ' &rarr;</h4>';
        html += '<ul class="idlm-task-list">';
        
        group.tasks.forEach(function(task) {
            var isCompleted = task.status === 'completed';
            var priorityClass = 'priority-' + task.priority.toLowerCase();
            var dueHtml = '';
            if (task.due_date_formatted) {
                var overdueClass = task.is_overdue ? ' overdue' : '';
                var alertIcon = task.is_overdue ? '⚠️ ' : '📅 ';
                dueHtml = '<span class="idlm-task-due' + overdueClass + '">' + alertIcon + task.due_date_formatted + '</span>';
            }

            html += '<li class="idlm-task-item ' + (isCompleted ? 'completed ' : '') + priorityClass + '" data-task-id="' + task.id + '" style="padding: 10px; margin-bottom: 5px;">';
            if (!task.is_qualification_summary) {
                html += '<input type="checkbox" class="idlm-task-checkbox" ' + (isCompleted ? 'checked' : '') + ' onchange="toggleTaskStatus(this, ' + task.id + ')">';
            } else {
                html += '<span class="dashicons dashicons-groups idlm-qualification-summary-icon" aria-hidden="true"></span>';
            }
            html += '<div class="idlm-task-content">';
            html += '<p class="idlm-task-text" style="font-size:12px;">' + escapeHtml(task.title) + '</p>';
            if (task.is_qualification_summary) {
                html += '<div class="idlm-qualification-summary-progress"><strong>' + task.complete_days + '/6 cycles</strong><span>' + task.today_calls + '/' + task.calls_target + ' calls</span><span>' + task.today_whatsapp + '/' + task.whatsapp_target + ' WA</span></div>';
            }
            html += '<div class="idlm-task-meta" style="font-size:10px;">';
            html += '<span class="idlm-task-type-badge">' + escapeHtml(task.task_type) + '</span>';
            if (dueHtml) html += dueHtml;
            html += '</div>';
            html += '</div>';
            if (!task.is_qualification_summary) {
                html += '<div class="idlm-task-actions">';
                html += '<button type="button" class="idlm-task-delete-btn" onclick="deleteLeadTask(' + task.id + ', ' + task.lead_id + ')" title="Delete task">&times;</button>';
                html += '</div>';
            }
            html += '</li>';
        });

        html += '</ul>';
        html += '</div>';
    }

    $body.html(html);
}

function updateFloatingTasksCount() {
    var $ = jQuery;
    var $badge = $('#idlm-floating-tasks-badge');
    if (!$badge.length) return;

    $.ajax({
        url: (typeof idlm_vars !== 'undefined' && idlm_vars.ajax_url) ? idlm_vars.ajax_url : (typeof ajaxurl !== 'undefined' ? ajaxurl : '/wp-admin/admin-ajax.php'),
        method: 'POST',
        data: {
            action: 'idlm_get_rep_tasks',
            status: 'pending',
            nonce: idlm_vars.nonce
        },
        success: function(response) {
            if (response.success) {
                var count = (response.data.tasks || []).length;
                if (count > 0) {
                    $badge.text(count).show();
                    $('#idlm-floating-tasks-btn').css('box-shadow', '0 6px 20px rgba(239, 68, 68, 0.35)');
                } else {
                    $badge.hide();
                    $('#idlm-floating-tasks-btn').css('box-shadow', '0 6px 20px rgba(201, 150, 68, 0.35)');
                }
            }
        }
    });
}

function refreshLeadsTableIfNeeded() {
    var $ = jQuery;
    if (typeof refreshLeadsTable === 'function') {
        refreshLeadsTable();
    }
}

function escapeHtml(text) {
    if (!text) return '';
    return text
        .replace(/&/g, "&amp;")
        .replace(/</g, "&lt;")
        .replace(/>/g, "&gt;")
        .replace(/"/g, "&quot;")
        .replace(/'/g, "&#039;");
}

// Attach globally
window.openLeadDrawer = openLeadDrawer;
window.switchDrawerTab = switchDrawerTab;
window.addLeadTask = addLeadTask;
window.toggleTaskStatus = toggleTaskStatus;
window.deleteLeadTask = deleteLeadTask;
window.toggleGlobalTasksSidebar = toggleGlobalTasksSidebar;
window.filterGlobalTasks = filterGlobalTasks;
window.loadLeadTasks = loadLeadTasks;
window.loadGlobalTasks = loadGlobalTasks;
window.updateFloatingTasksCount = updateFloatingTasksCount;
window.refreshLeadsTableIfNeeded = refreshLeadsTableIfNeeded;
window.escapeHtml = escapeHtml;
