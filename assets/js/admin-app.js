jQuery(document).ready(function($) {
    var currentPage = 1;
    var activeCallbackSelect = null;
    var activeWonSelect = null;
    window.idlmHasUnsavedChanges = false;
    window.idlmChangedFields = {};

    // Load table on start if present
    if ($('#idlm-leads-table').length > 0) {
        loadLeadsTable(1);
    }

    // Filter changes trigger table refresh
    $('.idlm-filters-panel select').change(function() {
        loadLeadsTable(1);
    });

    // Search input changes trigger table refresh with a small debounce
    var searchTimeout;
    $('#filter-search').on('input keyup', function() {
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(function() {
            loadLeadsTable(1);
        }, 300);
    });

    // Clear all filters
    $('#btn-clear-filters').click(function() {
        $('#filter-search').val('');
        $('#filter-date-range').val('');
        $('#filter-stage').val('');
        $('#filter-source').val('');
        $('#filter-rep').val('');
        $('#filter-call-status').val('');
        $('#filter-quality').val('');
        $('#filter-followup').val('');
        $('#idlm-secondary-filters-container').slideUp(200);
        $('#btn-toggle-more-filters').removeClass('active');
        loadLeadsTable(1);
    });

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
    $('.idlm-metric').click(function() {
        var filter = $(this).data('filter');
        
        // Reset all filter fields
        $('#filter-search').val('');
        $('#filter-date-range').val('');
        $('#filter-stage').val('');
        $('#filter-source').val('');
        $('#filter-rep').val('');
        $('#filter-call-status').val('');
        $('#filter-quality').val('');
        $('#filter-followup').val('');
        
        // Apply selected filter
        if (filter === 'New') {
            $('#filter-stage').val('New');
        } else if (filter === 'Not Called') {
            $('#filter-call-status').val('Not Called');
        } else if (filter === 'Follow-up') {
            $('#filter-call-status').val('Call Back Requested');
        } else if (filter === 'Unassigned') {
            $('#filter-rep').val('0');
        } else if (filter === 'Qualified') {
            $('#filter-quality').val('Qualified');
            // Show secondary filters row since Quality is in it
            $('#idlm-secondary-filters-container').slideDown(200);
            $('#btn-toggle-more-filters').addClass('active');
        }
        
        loadLeadsTable(1);
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

    // Helper function to submit Inline Quick Edits via AJAX
    function submitQuickEdit(leadId, field, val, callbackTime, $select, dealValue) {
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
        $.ajax({
            url: idlm_vars.ajax_url,
            type: 'POST',
            data: ajaxData,
            success: function(response) {
                if (response.success) {
                    showToast('Changes saved successfully!', 'success');
                    if ($select) {
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
            $('#idlm-deal-value-modal').addClass('active');
            updateBodyScrollLock();
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
        if (!dealValue || isNaN(dealValue) || parseFloat(dealValue) < 0) {
            alert('Please enter a valid deal value.');
            return;
        }

        $('#idlm-deal-value-modal').removeClass('active');
        updateBodyScrollLock();
        submitQuickEdit(activeWonSelect.leadId, 'stage', 'Won', '', activeWonSelect.select, parseFloat(dealValue));
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

        // Prompt confirmation for bulk delete
        if (action === 'bulk_delete') {
            if (!confirm('Are you absolutely sure you want to permanently delete these ' + selectedIds.length + ' selected leads? This action cannot be undone and will also delete all associated logs.')) {
                return;
            }
        }

        $.ajax({
            url: idlm_vars.ajax_url,
            type: 'POST',
            data: {
                action: 'idlm_bulk_action',
                nonce: idlm_vars.nonce,
                ids: selectedIds,
                bulk_action: action,
                rep_id: repId,
                stage: stage
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
    });

    // Export CSV hook
    $('#idlm-export-btn').click(function() {
        var selectedIds = [];
        $('.idlm-row-selector:checked').each(function() {
            selectedIds.push($(this).val());
        });

        var exportUrl = idlm_vars.ajax_url + '?action=idlm_export_leads';
        if (selectedIds.length > 0) {
            exportUrl += '&ids=' + selectedIds.join(',');
        }
        window.open(exportUrl, '_blank');
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
                access_token: accessToken
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
        var dateRange = $('#filter-date-range').val();
        var source = $('#filter-source').val();
        var repId = $('#filter-rep').val();
        var stage = $('#filter-stage').val();
        var callStatus = $('#filter-call-status').val();
        var quality = $('#filter-quality').val();
        var followup = $('#filter-followup').val();
        var search = $('#filter-search').val();

        $.ajax({
            url: idlm_vars.ajax_url,
            type: 'POST',
            data: {
                action: 'idlm_get_leads_table',
                nonce: idlm_vars.nonce,
                page: page,
                date_range: dateRange,
                source: source,
                rep_id: repId,
                stage: stage,
                call_status: callStatus,
                quality: quality,
                followup_filter: followup,
                search: search
            },
            success: function(response) {
                if (response.success) {
                    $('#idlm-leads-table tbody').html(response.data.html);
                    currentPage = response.data.current_page;
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
                }
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

    // Automatically refresh the leads list table every 15 minutes (900,000 ms)
    setInterval(function() {
        if ($('#idlm-leads-table').length > 0) {
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
            "Gclid", "Fbclid", "Meta Lead ID", "LI Fat ID"
        ];
        var sampleRow = [
            "John", "Doe", "john.doe@example.com", "+1234567890", "$25,000", 
            "1-3 Months", "Downtown", "Google Ads", "Apartment", 
            "3 BHK", "Downtown Studio", "Qualified", "google", 
            "cpc", "Spring_Campaign", "Adgroup_1", "interior design", "banner_ad", 
            "gclid12345", "", "1000000000001", ""
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
        { key: 'fbclid', label: 'Facebook Click ID (Fbclid)', required: false, fuzzy: ['fbclid'] },
        { key: 'fb_lead_id', label: 'Meta Lead ID', required: false, fuzzy: ['meta_lead_id', 'fb_lead_id', 'meta lead id', 'facebook lead id', 'lead_id', 'lead id'] },
        { key: 'li_fat_id', label: 'LinkedIn Click ID (LI FAT ID)', required: false, fuzzy: ['li_fat_id', 'li fat id'] },
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
        formData.append('action', 'idlm_upload_lead_file');
        formData.append('nonce', idlm_vars.nonce);
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
        }
        
        // Find sibling trigger button and update text
        $hiddenInput.siblings('.idlm-direct-upload-trigger').text(uploadBtnText);
    });

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

function openLeadDrawer(leadId) {
    window.idlmHasUnsavedChanges = false;
    window.idlmChangedFields = {};
    var $ = jQuery;
    var activeTab = '';
    var currentLeadId = $('#idlm-edit-lead-form input[name="lead_id"]').val();
    
    // Only preserve active tab if we are reloading the same lead (e.g., after comment or edit save)
    if (currentLeadId && parseInt(currentLeadId) === parseInt(leadId)) {
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
            lead_id: leadId
        },
        success: function(response) {
            if (response.success) {
                $('#idlm-drawer-content-wrapper-inner').html(response.data.html);
                
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
                }
                
                // Always load timeline comments on open
                loadLeadActivityLog(leadId, true);
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

function saveActivityComment(leadId) {
    var $ = jQuery;
    var commentText = $('#idlm-comment-text').val();
    if (!commentText) return;

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
        }
    });
}

function saveActivityCommentDetails(leadId) {
    var $ = jQuery;
    var commentText = $('#idlm-comment-text-details').val();
    if (!commentText) return;

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
        }
    });
}

function idlmCopyToClipboard(text, label) {
    if (navigator.clipboard && window.isSecureContext) {
        navigator.clipboard.writeText(text).then(function() {
            alert(label + ' copied to clipboard!');
        }).catch(function() {
            fallbackCopy(text, label);
        });
    } else {
        fallbackCopy(text, label);
    }

    function fallbackCopy(text, label) {
        var textArea = document.createElement("textarea");
        textArea.value = text;
        
        // Position off-screen safely
        textArea.style.position = "fixed";
        textArea.style.left = "-999999px";
        textArea.style.top = "-999999px";
        document.body.appendChild(textArea);
        textArea.focus();
        textArea.select();
        
        try {
            var successful = document.execCommand('copy');
            if (successful) {
                alert(label + ' copied to clipboard!');
            } else {
                alert('Failed to copy. Please manually select and copy the URL.');
            }
        } catch (err) {
            alert('Failed to copy. Please manually select and copy the URL.');
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

