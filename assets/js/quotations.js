(function () {
    'use strict';
    function bindQuotationRows(scope) {
        scope.querySelectorAll('.idlm-quote-row[data-href]').forEach(function (row) {
            if (row.dataset.idlmRowBound) { return; }
            row.dataset.idlmRowBound = '1';
            row.addEventListener('click', function (event) { if (!event.target.closest('a,button,input,select')) { window.location.href = row.dataset.href; } });
        });
    }
    bindQuotationRows(document);
    var catalogueSortForm = document.querySelector('.idlm-catalogue-sort-form');
    var catalogueToolbar = document.querySelector('.idlm-catalogue-toolbar');
    if (catalogueSortForm && catalogueToolbar) {
        catalogueToolbar.insertBefore(catalogueSortForm, catalogueToolbar.querySelector('.idlm-bulk-action-bar'));
    }
    function escapeHtml(value) { var node = document.createElement('div'); node.textContent = value == null ? '' : String(value); return node.innerHTML; }
    function liveListUrl(args) {
        var url = new URL(window.location.href);
        Object.keys(args).forEach(function (key) { if (args[key]) { url.searchParams.set(key, args[key]); } else { url.searchParams.delete(key); } });
        return url;
    }
    function quotationRow(row) {
        var customer = [row.first_name, row.last_name].filter(Boolean).join(' ') || '—';
        return '<tr class="idlm-quote-row" data-href="' + escapeHtml(liveListUrl({action: 'view', quote_id: row.id}).href) + '"><td><strong>' + escapeHtml(row.quote_number) + '</strong><br><small>' + escapeHtml(row.title) + '</small></td><td><strong>' + escapeHtml(customer) + '</strong><br><small>' + escapeHtml([row.phone, row.project_name].filter(Boolean).join(' · ')) + '</small></td><td>' + escapeHtml(row.revision_name || '—') + '</td><td>' + escapeHtml(new Intl.NumberFormat('en-IN', {style: 'currency', currency: 'INR', maximumFractionDigits: 2}).format(Number(row.grand_total || 0))) + '</td><td>' + escapeHtml(row.creator_name || 'Unknown') + '<br><small>' + escapeHtml(row.created_at) + '</small></td><td>' + escapeHtml(row.updated_at) + '</td><td><a class="button" href="' + escapeHtml(liveListUrl({action: 'view', quote_id: row.id}).href) + '">Open</a></td></tr>';
    }
    function catalogueRow(row) {
        var editUrl = liveListUrl({workspace: 'catalogue', edit_item: row.id, catalogue_search: document.querySelector('[name="catalogue_search"]').value}).href;
        return '<tr><td class="idlm-checkbox-column"><input type="checkbox" name="item_ids[]" value="' + escapeHtml(row.id) + '" data-row-select aria-label="Select ' + escapeHtml(row.item_name) + '"></td><td>' + escapeHtml(row.item_code) + '</td><td>' + escapeHtml(row.category) + '</td><td><strong>' + escapeHtml(row.item_name) + '</strong><br><small>' + escapeHtml(row.description) + '</small></td><td>' + escapeHtml(row.unit) + '</td><td>' + (Number(row.is_wood) ? 'Yes' : 'No') + '</td><td>' + escapeHtml(new Intl.NumberFormat('en-IN', {style: 'currency', currency: 'INR', maximumFractionDigits: 2}).format(Number(row.default_rate || 0))) + '</td><td>' + escapeHtml((row.status || '').charAt(0).toUpperCase() + (row.status || '').slice(1)) + '</td><td><div class="idlm-catalogue-actions"><a class="button-link" href="' + escapeHtml(editUrl) + '">Edit</a>' + ('active' === row.status ? '<button class="button-link-delete" name="bulk_action" value="archive" data-single-submit>Archive</button>' : '') + '<button class="button-link-delete" name="bulk_action" value="delete" data-single-submit>Delete</button></div></td></tr>';
    }
    document.querySelectorAll('[data-live-list]').forEach(function (filterForm) {
        var type = filterForm.dataset.liveList;
        var rows = document.querySelector('[data-live-list-rows="' + type + '"]');
        var pagination = document.querySelector('[data-live-list-pagination="' + type + '"]');
        var status = document.querySelector('[data-live-list-status="' + type + '"]');
        var searchInput = filterForm.querySelector(type === 'catalogue' ? '[name="catalogue_search"]' : '[name="quote_search"]');
        if (!rows || !searchInput) { return; }
        var timer = 0, controller = null, page = 1;
        function request(nextPage) {
            page = nextPage || 1;
            if (controller) { controller.abort(); }
            controller = new AbortController();
            var params = new URLSearchParams(new FormData(filterForm));
            if ('catalogue' === type) {
                var sortForm = document.querySelector('.idlm-catalogue-sort-form');
                if (sortForm) {
                    params.set('catalogue_sort', sortForm.elements.catalogue_sort.value);
                    params.set('catalogue_order', sortForm.elements.catalogue_order.value);
                }
            }
            params.set(type === 'catalogue' ? 'catalogue_page' : 'quote_page', page);
            params.set('action', type === 'catalogue' ? 'idlm_filter_catalogue_list' : 'idlm_filter_quotation_list');
            params.set('nonce', idlmQuoteData.nonce);
            fetch(idlmQuoteData.ajaxUrl + '?' + params.toString(), {credentials: 'same-origin', signal: controller.signal}).then(function (response) {
                if (!response.ok) { throw new Error('Search failed.'); }
                return response.json();
            }).then(function (payload) {
                if (!payload.success || !payload.data || !Array.isArray(payload.data.rows)) { throw new Error(payload.data && payload.data.message ? payload.data.message : 'Search failed.'); }
                var data = payload.data, rowMarkup = type === 'catalogue' ? catalogueRow : quotationRow;
                rows.innerHTML = data.rows.length ? data.rows.map(rowMarkup).join('') : '<tr><td colspan="' + (type === 'catalogue' ? 9 : 7) + '">No ' + (type === 'catalogue' ? 'catalogue items' : 'quotations') + ' found.</td></tr>';
                if ('quotations' === type) { bindQuotationRows(rows); }
                var pages = Math.max(1, Math.ceil(Number(data.total) / Number(data.perPage)));
                pagination.innerHTML = pages > 1 ? '<button type="button" class="button" data-live-list-page="' + Math.max(1, data.page - 1) + '" ' + (data.page <= 1 ? 'disabled' : '') + '>Previous</button> <span>Page ' + data.page + ' of ' + pages + '</span> <button type="button" class="button" data-live-list-page="' + Math.min(pages, data.page + 1) + '" ' + (data.page >= pages ? 'disabled' : '') + '>Next</button>' : '';
                if (status) { status.textContent = data.total + ' result' + (Number(data.total) === 1 ? '' : 's') + ' found.'; }
                var url = liveListUrl({[type === 'catalogue' ? 'catalogue_search' : 'quote_search']: searchInput.value, [type === 'catalogue' ? 'catalogue_page' : 'quote_page']: data.page > 1 ? data.page : null});
                window.history.replaceState({}, '', url.href);
            }).catch(function (error) { if ('AbortError' !== error.name && status) { status.textContent = error.message || 'Search failed.'; } });
        }
        filterForm.addEventListener('submit', function (event) { event.preventDefault(); window.clearTimeout(timer); request(1); });
        searchInput.addEventListener('input', function () { window.clearTimeout(timer); timer = window.setTimeout(function () { request(1); }, 250); });
        pagination.addEventListener('click', function (event) { var button = event.target.closest('[data-live-list-page]'); if (button) { request(Number(button.dataset.liveListPage)); } });
    });
    var catalogueEditor = document.querySelector('.idlm-catalogue-editor');
    if (catalogueEditor) {
        var catalogueParams = new URLSearchParams(window.location.search);
        if (catalogueParams.has('edit_item') || catalogueParams.has('add_item')) {
            catalogueEditor.classList.add('is-open');
            catalogueEditor.setAttribute('role', 'dialog');
            catalogueEditor.setAttribute('aria-modal', 'true');
            document.body.classList.add('idlm-catalogue-editor-open');
            var heading = catalogueEditor.querySelector('.idlm-panel-heading');
            if (heading && !heading.querySelector('.idlm-catalogue-editor-close')) {
                var closeButton = document.createElement('button');
                closeButton.type = 'button';
                closeButton.className = 'button idlm-catalogue-editor-close';
                closeButton.setAttribute('aria-label', 'Close catalogue editor');
                closeButton.textContent = '×';
                heading.appendChild(closeButton);
            }
            catalogueEditor.querySelector('.idlm-catalogue-editor-close').addEventListener('click', function () {
                catalogueParams.delete('edit_item'); catalogueParams.delete('add_item');
                window.location.search = catalogueParams.toString();
            });
            document.addEventListener('keydown', function (event) { if (event.key === 'Escape') { catalogueEditor.querySelector('.idlm-catalogue-editor-close').click(); } });
        }
    }
    var catalogueImport = document.querySelector('.idlm-catalogue-editor + .idlm-quote-panel');
    if (catalogueImport) {
        catalogueImport.classList.add('idlm-catalogue-import');
        var importParams = new URLSearchParams(window.location.search);
        if (importParams.has('import_catalogue')) {
            catalogueImport.classList.add('is-open');
            catalogueImport.setAttribute('role', 'dialog');
            catalogueImport.setAttribute('aria-modal', 'true');
            document.body.classList.add('idlm-catalogue-editor-open');
            var importHeading = catalogueImport.querySelector('h2');
            var importClose = document.createElement('button');
            importClose.type = 'button';
            importClose.className = 'button idlm-catalogue-editor-close';
            importClose.setAttribute('aria-label', 'Close CSV import');
            importClose.textContent = '×';
            if (importHeading) { importHeading.insertAdjacentElement('afterend', importClose); }
            importClose.addEventListener('click', function () { importParams.delete('import_catalogue'); window.location.search = importParams.toString(); });
            document.addEventListener('keydown', function (event) { if (event.key === 'Escape') { importClose.click(); } });
        }
    }
    var categoryManager = document.querySelector('.idlm-category-manager.is-open');
    if (categoryManager) {
        if (categoryManager.parentNode !== document.body) { document.body.appendChild(categoryManager); }
        document.body.classList.add('idlm-catalogue-editor-open');
        document.addEventListener('keydown', function (event) { if (event.key === 'Escape') { categoryManager.querySelector('.idlm-catalogue-editor-close').click(); } });
    }
    document.querySelectorAll('[data-bulk-form]').forEach(function (bulkForm) {
        var selectAll = bulkForm.querySelector('[data-select-all]');
        function rowSelects() { return Array.from(bulkForm.querySelectorAll('[data-row-select]')); }
        var counter = bulkForm.querySelector('[data-selection-count]') || (bulkForm.closest('.idlm-quote-panel') && bulkForm.closest('.idlm-quote-panel').querySelector('.idlm-catalogue-toolbar [data-selection-count]'));
        var actionSelect = bulkForm.querySelector('select') || (bulkForm.id ? document.querySelector('select[form="' + bulkForm.id + '"]') : null);
        function updateSelection() {
            var checkboxes = rowSelects();
            var selected = checkboxes.filter(function (checkbox) { return checkbox.checked; }).length;
            if (counter) { counter.textContent = selected + ' selected'; }
            if (selectAll) { selectAll.checked = checkboxes.length > 0 && selected === checkboxes.length; selectAll.indeterminate = selected > 0 && selected < checkboxes.length; }
        }
        if (selectAll) { selectAll.addEventListener('change', function () { rowSelects().forEach(function (checkbox) { checkbox.checked = selectAll.checked; }); updateSelection(); }); }
        bulkForm.addEventListener('change', function (event) { if (event.target.matches('[data-row-select]')) { updateSelection(); } });
        bulkForm.addEventListener('click', function (event) {
            var button = event.target.closest('[data-single-submit]');
            if (button) {
                rowSelects().forEach(function (checkbox) { checkbox.checked = false; });
                var rowCheckbox = button.closest('tr, .idlm-category-manager-list>div').querySelector('[data-row-select]');
                if (rowCheckbox) { rowCheckbox.checked = true; }
                if (actionSelect) { actionSelect.value = button.value; }
                updateSelection();
            }
        });
        bulkForm.addEventListener('submit', function (event) {
            if (!rowSelects().some(function (checkbox) { return checkbox.checked; })) {
                event.preventDefault();
                window.IDLMDialog.notify('Select at least one item.', 'error');
                (actionSelect || rowSelects()[0]).focus();
                return;
            }
            if (actionSelect && 'delete' === actionSelect.value && bulkForm.dataset.idlmDeleteConfirmed !== 'true') {
                event.preventDefault();
                window.IDLMDialog.confirm({
                    title: 'Delete selected records?',
                    message: bulkForm.dataset.deleteMessage || 'Permanently delete the selected records?',
                    confirmLabel: 'Delete selected',
                    destructive: true,
                    trigger: event.submitter || document.activeElement
                }).then(function (confirmed) {
                    if (!confirmed) { return; }
                    bulkForm.dataset.idlmDeleteConfirmed = 'true';
                    bulkForm.requestSubmit(event.submitter || undefined);
                });
                return;
            }
            delete bulkForm.dataset.idlmDeleteConfirmed;
        });
        updateSelection();
    });
    var paymentStructure = document.querySelector('[data-payment-structure]');
    if (paymentStructure) {
        function renumberPaymentRows() {
            paymentStructure.querySelectorAll('.idlm-payment-structure-row').forEach(function (row, index) {
                row.querySelectorAll('[name]').forEach(function (input) { input.name = input.name.replace(/payment_structure\[\d+\]/, 'payment_structure[' + index + ']'); });
            });
        }
        document.querySelector('[data-add-payment-row]').addEventListener('click', function () {
            var source = paymentStructure.querySelector('.idlm-payment-structure-row:last-child');
            var row = source.cloneNode(true);
            row.querySelectorAll('input').forEach(function (input) { input.value = ''; });
            paymentStructure.appendChild(row); renumberPaymentRows();
        });
        paymentStructure.addEventListener('click', function (event) {
            if (!event.target.matches('[data-remove-payment-row]')) { return; }
            var rows = paymentStructure.querySelectorAll('.idlm-payment-structure-row');
            if (rows.length > 1) { event.target.closest('.idlm-payment-structure-row').remove(); }
            else { rows[0].querySelectorAll('input').forEach(function (input) { input.value = ''; }); }
            renumberPaymentRows();
        });
    }
    var form = document.getElementById('idlm-quote-form');
    if (!form) { return; }
    var sections = document.getElementById('idlm-quote-sections');
    var bulkModal = document.getElementById('idlm-bulk-items-modal');
    var bulkItems = [];
    var bulkRequest = null;
    var autosaveStatus = form.querySelector('[data-autosave-status]');
    var autosaveEnabled = !!(autosaveStatus && idlmQuoteData.autosaveNonce && Number(form.elements.quote_id.value) > 0 && Number(form.elements.revision_id.value) > 0);
    var autosaveTimer = 0;
    var autosaveDirty = false;
    var autosaveSaving = false;
    var autosaveSubmitPending = false;
    var finalSubmitStarted = false;
    var autosaveHasWorkingCopy = !!(autosaveStatus && '1' === autosaveStatus.dataset.hasWorkingCopy);
    var formDirty = false;
    var autosaveCancelModal = form.querySelector('[data-autosave-cancel-modal]');
    function setAutosaveStatus(message, state) {
        if (!autosaveStatus) { return; }
        autosaveStatus.textContent = message;
        autosaveStatus.dataset.state = state || '';
    }
    function queueAutosave() {
        formDirty = true;
        if (!autosaveEnabled) { return; }
        autosaveDirty = true;
        window.clearTimeout(autosaveTimer);
        setAutosaveStatus('Unsaved changes', 'dirty');
        autosaveTimer = window.setTimeout(performAutosave, 1800);
    }
    function performAutosave(useKeepalive) {
        if (!autosaveEnabled || autosaveSaving || !autosaveDirty) { return; }
        autosaveDirty = false;
        autosaveSaving = true;
        renumberAll(); calculate();
        setAutosaveStatus('Saving…', 'saving');
        var autosaveData = new FormData(form);
        autosaveData.set('action', 'idlm_autosave_quote');
        autosaveData.set('nonce', idlmQuoteData.autosaveNonce);
        fetch(idlmQuoteData.ajaxUrl, {method: 'POST', body: autosaveData, credentials: 'same-origin', keepalive: !!useKeepalive})
            .then(function (response) { return response.json().then(function (payload) { return {payload: payload, status: response.status}; }); })
            .then(function (result) {
                var payload = result.payload;
                if (!payload.success) { var autosaveError = new Error(payload.data && payload.data.message ? payload.data.message : 'Autosave failed.'); autosaveError.retry = result.status >= 500; throw autosaveError; }
                autosaveHasWorkingCopy = true;
                formDirty = false;
                autosaveStatus.dataset.hasWorkingCopy = '1';
                setAutosaveStatus('Saved at ' + payload.data.savedAt, 'saved');
            })
            .catch(function (error) {
                autosaveDirty = false !== error.retry;
                setAutosaveStatus(error.message || 'Could not autosave', 'error');
            })
            .finally(function () {
                autosaveSaving = false;
                if (autosaveSubmitPending) {
                    autosaveSubmitPending = false;
                    autosaveEnabled = false;
                    form.requestSubmit();
                } else if (autosaveDirty) {
                    window.clearTimeout(autosaveTimer);
                    autosaveTimer = window.setTimeout(performAutosave, 2500);
                }
            });
    }
    if (autosaveEnabled) {
        form.addEventListener('input', function (event) { if (event.target.name) { queueAutosave(); } });
        form.addEventListener('change', function (event) { if (event.target.name) { queueAutosave(); } });
        form.addEventListener('click', function (event) {
            if (event.target.closest('.idlm-add-section-item,.idlm-add-section-header,#idlm-add-section,#idlm-add-bulk-items,.idlm-move-row-up,.idlm-move-row-down,.idlm-move-section-up,.idlm-move-section-down,.idlm-duplicate-row,.idlm-remove-row,.idlm-remove-section,.idlm-catalogue-result')) {
                window.setTimeout(queueAutosave, 0);
            }
        });
        form.addEventListener('submit', function (event) {
            window.clearTimeout(autosaveTimer);
            if (finalSubmitStarted) {
                event.preventDefault();
                return;
            }
            if (autosaveSaving) {
                event.preventDefault();
                autosaveSubmitPending = true;
                setAutosaveStatus('Finishing autosave…', 'saving');
                return;
            }
            finalSubmitStarted = true;
            formDirty = false;
            form.querySelectorAll('button[type="submit"],input[type="submit"]').forEach(function (submitter) { submitter.disabled = true; });
            autosaveEnabled = false;
        });
        var quoteCancel = form.querySelector('[data-quote-cancel]');
        if (quoteCancel && autosaveCancelModal) {
            var cancelDestination = quoteCancel.dataset.cancelUrl;
            function closeCancelDialog() { window.IDLMDialog.close(autosaveCancelModal, {reason: 'continue'}); }
            quoteCancel.addEventListener('click', function () {
                if (!autosaveHasWorkingCopy && !autosaveDirty && !autosaveSaving) { window.location.href = cancelDestination; return; }
                window.IDLMDialog.open(autosaveCancelModal, {trigger: quoteCancel});
            });
            autosaveCancelModal.querySelector('[data-autosave-continue]').addEventListener('click', closeCancelDialog);
            autosaveCancelModal.querySelector('[data-autosave-save]').addEventListener('click', function () {
                closeCancelDialog();
                form.requestSubmit();
            });
            autosaveCancelModal.querySelector('[data-autosave-discard]').addEventListener('click', function discardWorkingCopy() {
                if (autosaveSaving) { setAutosaveStatus('Finishing autosave…', 'saving'); window.setTimeout(discardWorkingCopy, 200); return; }
                autosaveEnabled = false;
                autosaveDirty = false;
                window.clearTimeout(autosaveTimer);
                setAutosaveStatus('Discarding…', 'saving');
                var discardData = new FormData();
                discardData.set('action', 'idlm_discard_quote_autosave');
                discardData.set('nonce', idlmQuoteData.autosaveNonce);
                discardData.set('quote_id', form.elements.quote_id.value);
                discardData.set('revision_id', form.elements.revision_id.value);
                fetch(idlmQuoteData.ajaxUrl, {method: 'POST', body: discardData, credentials: 'same-origin'})
                    .then(function (response) { return response.json(); })
                    .then(function (payload) { if (!payload.success) { throw new Error(payload.data && payload.data.message ? payload.data.message : 'Unable to discard changes.'); } window.location.href = cancelDestination; })
                    .catch(function (error) { autosaveEnabled = true; closeCancelDialog(); setAutosaveStatus(error.message || 'Unable to discard changes', 'error'); });
            });
        }
    }
    if (!autosaveEnabled) {
        form.addEventListener('input', function (event) { if (event.target.name) { formDirty = true; } });
        form.addEventListener('change', function (event) { if (event.target.name) { formDirty = true; } });
        form.addEventListener('click', function (event) { if (event.target.closest('.idlm-add-section-item,.idlm-add-section-header,#idlm-add-section,#idlm-add-bulk-items,.idlm-duplicate-row,.idlm-remove-row,.idlm-remove-section')) { formDirty = true; } });
        form.addEventListener('submit', function (event) {
            if (finalSubmitStarted) { event.preventDefault(); return; }
            finalSubmitStarted = true;
            formDirty = false;
            form.querySelectorAll('button[type="submit"],input[type="submit"]').forEach(function (submitter) { submitter.disabled = true; });
        });
    }
    window.addEventListener('beforeunload', function (event) {
        if (!formDirty && !autosaveSaving) { return; }
        event.preventDefault();
        event.returnValue = '';
    });
    window.addEventListener('pagehide', function () {
        if (autosaveEnabled && autosaveDirty && !autosaveSaving) {
            window.clearTimeout(autosaveTimer);
            performAutosave(true);
        }
    });
    var selectedBulkItems = new Map();
    var bulkReturnFocus = null;
    var leadSearch = document.getElementById('idlm-lead-search');
    var leadSelect = document.getElementById('idlm-quote-lead');
    var createdByField = document.getElementById('idlm-quote-created-by');
    var revisionReason = form.elements.revision_reason;
    if (form.elements.quote_id.value && revisionReason) {
        revisionReason.required = true;
        revisionReason.value = '';
        revisionReason.placeholder = 'Explain what changed and why';
        var remarkLabel = revisionReason.closest('label');
        var identityPanel = document.querySelector('.idlm-quote-identity');
        if (remarkLabel && identityPanel) {
            var remarkPanel = document.createElement('section');
            remarkPanel.className = 'idlm-quote-panel idlm-edit-remark';
            remarkPanel.innerHTML = '<h2>Edit remark</h2><p>Saving creates a new revision and preserves the previous version.</p>';
            remarkPanel.appendChild(remarkLabel);
            identityPanel.insertAdjacentElement('afterend', remarkPanel);
        }
        var saveButton = form.querySelector('.idlm-quote-actions button[type="submit"]');
        if (saveButton) { saveButton.textContent = 'Save as New Revision'; }
    }

    function num(input) { var value = parseFloat(input && input.value); return Number.isFinite(value) ? value : 0; }
    function field(row, suffix) { return row.querySelector('[name$="[' + suffix + ']"]'); }
    function escapeHtml(value) { var node = document.createElement('div'); node.textContent = value; return node.innerHTML; }

    if (createdByField) {
        var salesRepValue = document.getElementById('idlm-quote-sales-rep');
        if (salesRepValue && salesRepValue.parentNode && !document.getElementById('idlm-quote-created-by-value')) {
            var creatorRow = document.createElement('div');
            creatorRow.innerHTML = '<dt>Created by</dt><dd id="idlm-quote-created-by-value">' + escapeHtml(createdByField.value || 'Unknown') + '</dd>';
            salesRepValue.parentNode.insertAdjacentElement('afterend', creatorRow);
        }
    }

    if (leadSearch && leadSelect) {
        var leadResults = document.createElement('div');
        leadResults.className = 'idlm-lead-search-results';
        leadResults.hidden = true;
        leadSearch.insertAdjacentElement('afterend', leadResults);
        var leadSearchTimer = 0, leadSearchController = null, leadSearchRows = new Map();
        leadSearch.addEventListener('input', function () {
            var query = leadSearch.value.trim();
            window.clearTimeout(leadSearchTimer);
            if (leadSearchController) { leadSearchController.abort(); }
            if (query.length < 2) { leadResults.hidden = true; leadResults.innerHTML = ''; return; }
            leadSearchTimer = window.setTimeout(function () {
                leadSearchController = new AbortController();
                fetch(idlmQuoteData.ajaxUrl + '?action=idlm_search_quote_leads&nonce=' + encodeURIComponent(idlmQuoteData.nonce) + '&term=' + encodeURIComponent(query), {credentials: 'same-origin', signal: leadSearchController.signal}).then(function (response) {
                    if (!response.ok) { throw new Error('Lead search failed.'); }
                    return response.json();
                }).then(function (payload) {
                    if (!payload.success || !Array.isArray(payload.data)) { throw new Error(payload.data && payload.data.message ? payload.data.message : 'Lead search failed.'); }
                    leadSearchRows.clear();
                    payload.data.forEach(function (lead) { leadSearchRows.set(String(lead.id), lead); });
                    leadResults.innerHTML = payload.data.length ? payload.data.map(function (lead) { return '<button type="button" data-lead-id="' + escapeHtml(String(lead.id)) + '"><strong>' + escapeHtml([lead.first_name, lead.last_name].filter(Boolean).join(' ')) + '</strong><small>' + escapeHtml([lead.phone, lead.email, lead.project_name].filter(Boolean).join(' · ')) + '</small></button>'; }).join('') : '<p>No leads found.</p>';
                    leadResults.hidden = false;
                }).catch(function (error) { if ('AbortError' !== error.name) { leadResults.innerHTML = '<p>' + escapeHtml(error.message || 'Lead search failed.') + '</p>'; leadResults.hidden = false; } });
            }, 250);
        });
        leadResults.addEventListener('click', function (event) {
            var result = event.target.closest('[data-lead-id]');
            if (!result) { return; }
            var lead = leadSearchRows.get(result.dataset.leadId);
            if (lead && !Array.from(leadSelect.options).some(function (option) { return option.value === String(lead.id); })) {
                var option = document.createElement('option');
                option.value = lead.id;
                option.textContent = [lead.first_name, lead.last_name].filter(Boolean).join(' ') + (lead.project_name ? ' — ' + lead.project_name : '');
                option.dataset.name = [lead.first_name, lead.last_name].filter(Boolean).join(' ');
                option.dataset.email = lead.email || ''; option.dataset.phone = lead.phone || ''; option.dataset.property = lead.project_name || ''; option.dataset.location = lead.property_location || ''; option.dataset.studio = lead.preferred_studio || ''; option.dataset.rep = lead.assigned_rep_name || 'Unassigned';
                leadSelect.appendChild(option);
            }
            leadSelect.value = result.dataset.leadId;
            leadSelect.dispatchEvent(new Event('change', {bubbles: true}));
            leadSearch.value = leadSelect.options[leadSelect.selectedIndex].textContent.trim();
            leadResults.hidden = true;
        });
        leadSelect.addEventListener('change', function () {
            var option = leadSelect.options[leadSelect.selectedIndex];
            if (!option || !option.value) { return; }
            form.elements.customer_name.value = option.dataset.name || '';
            form.elements.customer_email.value = option.dataset.email || '';
            form.elements.customer_phone.value = option.dataset.phone || '';
            form.elements.project_name.value = option.dataset.property || '';
            form.elements.property_location.value = option.dataset.location || '';
            document.getElementById('idlm-quote-studio').textContent = option.dataset.studio || '—';
            document.getElementById('idlm-quote-sales-rep').textContent = option.dataset.rep || 'Unassigned';
        });
        document.addEventListener('click', function (event) { if (event.target !== leadSearch && !leadResults.contains(event.target)) { leadResults.hidden = true; } });
        leadSearch.addEventListener('keydown', function (event) { if (event.key === 'Escape') { leadResults.hidden = true; } });
    }

    function renumberAll() {
        var itemIndex = 0;
        sections.querySelectorAll('.idlm-quote-section').forEach(function (section, sectionIndex) {
            var key = section.dataset.sectionKey || ('section-' + (sectionIndex + 1));
            section.dataset.sectionKey = key; section.querySelector('.idlm-section-key').value = key;
            section.querySelectorAll('header [name]').forEach(function (input) { input.name = input.name.replace(/sections\[\d+\]/, 'sections[' + sectionIndex + ']'); });
            section.querySelectorAll('.idlm-quote-item-row').forEach(function (row) {
                row.querySelectorAll('[name]').forEach(function (input) { input.name = input.name.replace(/items\[\d+\]/, 'items[' + itemIndex + ']'); });
                field(row, 'section_key').value = key; itemIndex++;
            });
        });
    }

    function calculate() {
        function round2(value) { return Math.round((value + Number.EPSILON) * 100) / 100; }
        var subtotal = 0, taxable = 0, woodGroups = {Wood: {base: 0}, Others: {base: 0}}, sectionTotals = [];
        sections.querySelectorAll('.idlm-quote-section').forEach(function (section) {
            var sectionTotal = 0;
            section.querySelectorAll('.idlm-quote-item-row').forEach(function (row) {
                if (field(row, 'row_type') && 'header' === field(row, 'row_type').value) { return; }
                var length = Math.max(0, num(field(row, 'length'))), height = Math.max(0, num(field(row, 'height'))), quantityField = field(row, 'quantity');
                if (length > 0 && height > 0) { quantityField.value = (length * height).toFixed(2); }
                var qty = Math.max(0, num(quantityField)), rate = Math.max(0, num(field(row, 'unit_rate'))), base = qty * rate;
                var net = base;
                var optionalField = field(row, 'is_optional'), optional = 'checkbox' === optionalField.type ? optionalField.checked : '1' === optionalField.value, total = net;
                row.querySelector('.idlm-line-total').textContent = optional ? 'Optional' : '₹' + total.toFixed(2); row.classList.toggle('is-optional', optional);
                if (!optional) { subtotal += base; sectionTotal += total; var woodGroup = Number(field(row, 'is_wood').value) === 1 ? 'Wood' : 'Others'; woodGroups[woodGroup].base += net; }
            });
            var sectionName = String(section.querySelector('.idlm-section-name').value || 'Untitled Section').trim() || 'Untitled Section';
            section.querySelector('.idlm-section-subtotal-label').textContent = sectionName + ' Subtotal';
            section.querySelector('.idlm-section-subtotal').textContent = '₹' + sectionTotal.toFixed(2);
            sectionTotals.push({name: sectionName, total: sectionTotal});
        });
        Object.keys(woodGroups).forEach(function (group) {
            woodGroups[group].total = woodGroups[group].base;
            taxable += woodGroups[group].base;
        });
        var woodenSubtotal = woodGroups.Wood.base;
        var woodenDiscountPercent = Math.min(100, Math.max(0, num(form.elements.wooden_discount_percent)));
        var woodenDiscount = round2(woodenSubtotal * woodenDiscountPercent / 100);
        var afterWoodenDiscount = round2(Math.max(0, woodenSubtotal - woodenDiscount));
        var otherSubtotal = round2(subtotal - woodenSubtotal);
        var totalsPanel = form.querySelector('#idlm-live-totals');
        var softClosePercent = Math.min(100, Math.max(0, Number(totalsPanel.dataset.softClosePercent || 0)));
        var softCloseEnabled = form.elements.soft_close_enabled.checked;
        var softClose = softCloseEnabled ? round2(afterWoodenDiscount * softClosePercent / 100) : 0;
        var total = round2(afterWoodenDiscount + otherSubtotal + softClose);
        var additionalDiscount = Math.min(total, Math.max(0, num(form.elements.additional_discount_amount)));
        var afterAdditionalDiscount = round2(Math.max(0, total - additionalDiscount));
        var gst = round2(afterAdditionalDiscount * 18 / 100);
        var preRoundTotal = round2(afterAdditionalDiscount + gst);
        var grand = Math.round(preRoundTotal);
        var roundOff = round2(grand - preRoundTotal);
        var hasAdditionalDiscount = additionalDiscount > 0;
        form.querySelector('[data-total="subtotal"]').textContent = '₹' + subtotal.toFixed(2);
        form.querySelector('[data-total="wooden-subtotal"]').textContent = '₹' + woodenSubtotal.toFixed(2);
        form.querySelector('[data-total="wooden-discount"]').textContent = '₹' + woodenDiscount.toFixed(2);
        form.querySelector('[data-total="after-wooden-discount"]').textContent = '₹' + afterWoodenDiscount.toFixed(2);
        form.querySelector('[data-total="other-subtotal"]').textContent = '₹' + otherSubtotal.toFixed(2);
        form.querySelector('[data-total="soft-close"]').textContent = '₹' + softClose.toFixed(2);
        form.querySelector('[data-total="total"]').textContent = '₹' + total.toFixed(2);
        form.querySelector('[data-after-additional-discount-row]').hidden = !hasAdditionalDiscount;
        form.querySelector('[data-total="after-additional-discount"]').textContent = '₹' + afterAdditionalDiscount.toFixed(2);
        form.querySelector('[data-total="tax"]').textContent = '₹' + gst.toFixed(2);
        var roundOffOutput = form.querySelector('[data-total="round-off"]');
        if (!roundOffOutput) {
            var roundOffRow = document.createElement('div');
            roundOffRow.innerHTML = '<dt>Round Off</dt><dd data-total="round-off">₹0.00</dd>';
            form.querySelector('.idlm-grand-total').before(roundOffRow);
            roundOffOutput = roundOffRow.querySelector('[data-total="round-off"]');
        }
        roundOffOutput.textContent = '₹' + roundOff.toFixed(2);
        form.querySelector('[data-total="grand"]').textContent = '₹' + grand.toFixed(2);
        document.getElementById('idlm-wood-totals').innerHTML = Object.keys(woodGroups).map(function (group) { return '<div><span>' + group + '</span><strong>₹' + woodGroups[group].total.toFixed(2) + '</strong></div>'; }).join('');
        document.getElementById('idlm-section-totals').innerHTML = sectionTotals.map(function (section) { return '<div><span>' + escapeHtml(section.name) + '</span><strong>₹' + section.total.toFixed(2) + '</strong></div>'; }).join('') || '<p>No section totals yet.</p>';
    }

    var addAdditionalDiscount = form.querySelector('[data-add-additional-discount]');
    addAdditionalDiscount.addEventListener('click', function () {
        form.querySelector('[data-additional-discount-row]').hidden = false;
        addAdditionalDiscount.hidden = true;
        form.elements.additional_discount_amount.focus();
    });

    function blankRow(source) {
        var clone = source.cloneNode(true);
        clone.classList.remove('is-catalogue-item');
        clone.querySelectorAll('input, textarea').forEach(function (input) { if (input.matches('[name$="[quantity]"]')) { input.value = '1'; } else if (input.matches('[name$="[length]"], [name$="[height]"]')) { input.value = '0'; } else if (input.matches('[name$="[unit]"]')) { input.value = 'Nos'; } else if (input.matches('[name$="[is_optional]"], [name$="[is_wood]"]')) { input.value = '0'; input.checked = false; } else if ('checkbox' === input.type) { input.checked = false; } else { input.value = ''; } if (input.classList.contains('idlm-catalogue-locked')) { input.readOnly = false; } });
        clone.querySelectorAll('select').forEach(function (select) { select.selectedIndex = 0; }); return clone;
    }

    function headerRow() {
        var row = document.createElement('tr');
        row.className = 'idlm-quote-item-row idlm-quote-header-row';
        row.innerHTML = '<td colspan="7" data-label="Header"><input type="hidden" name="items[0][section_key]" value=""><input type="hidden" name="items[0][row_type]" value="header"><input class="idlm-inline-header-input" aria-label="Header title" name="items[0][description]" placeholder="Header title" required></td><td class="idlm-line-actions" data-label="Header actions"><button type="button" class="button-link idlm-line-action idlm-move-row-up" title="Move up" aria-label="Move header up">↑</button><button type="button" class="button-link idlm-line-action idlm-move-row-down" title="Move down" aria-label="Move header down">↓</button><button type="button" class="button-link-delete idlm-line-action idlm-remove-row" title="Remove" aria-label="Remove header">×</button></td>';
        return row;
    }

    function populateCatalogueRow(row, item) {
        row.classList.add('is-catalogue-item');
        field(row, 'catalogue_item_id').value = item.id;
        field(row, 'is_wood').value = Number(item.is_wood) === 1 ? '1' : '0';
        field(row, 'item_code').value = item.item_code;
        field(row, 'item_name').value = item.item_name;
        field(row, 'category').value = item.category;
        field(row, 'description').value = item.description || item.item_name;
        field(row, 'unit').value = item.unit;
        field(row, 'unit_rate').value = item.default_rate;
        row.querySelectorAll('.idlm-catalogue-locked').forEach(function (input) { input.readOnly = true; });
    }

    function bulkItemMarkup(item, selected) {
        return '<button type="button" class="idlm-bulk-item" data-bulk-item-id="' + escapeHtml(String(item.id)) + '"' + (selected ? ' disabled' : '') + '><span><strong>' + escapeHtml(item.item_name) + '</strong><small>' + escapeHtml((item.item_code || 'No code') + ' · ' + (item.category || 'Uncategorised') + ' · Wood: ' + (Number(item.is_wood) === 1 ? 'Yes' : 'No')) + '</small></span><span aria-hidden="true">' + (selected ? 'Selected' : '+') + '</span></button>';
    }

    function renderBulkItems() {
        var query = document.getElementById('idlm-bulk-items-search').value.trim().toLowerCase();
        var filtered = bulkItems.filter(function (item) { return [item.item_name, item.item_code, item.category].join(' ').toLowerCase().indexOf(query) !== -1; });
        document.getElementById('idlm-bulk-items-available').innerHTML = filtered.length ? filtered.map(function (item) { return bulkItemMarkup(item, selectedBulkItems.has(String(item.id))); }).join('') : '<p class="idlm-bulk-items-empty">No catalogue items match your search.</p>';
        var selected = Array.from(selectedBulkItems.values());
        document.getElementById('idlm-bulk-items-selected').innerHTML = selected.length ? selected.map(function (item) { return '<button type="button" class="idlm-bulk-item is-selected" data-remove-bulk-item-id="' + escapeHtml(String(item.id)) + '"><span><strong>' + escapeHtml(item.item_name) + '</strong><small>' + escapeHtml(item.item_code || 'No code') + '</small></span><span aria-hidden="true">&times;</span></button>'; }).join('') : '<p class="idlm-bulk-items-empty">No items selected.</p>';
        document.getElementById('idlm-bulk-items-count').textContent = selected.length;
        document.getElementById('idlm-add-bulk-items').disabled = !selected.length;
    }

    function closeBulkItems() {
        bulkModal.hidden = true;
        document.body.classList.remove('idlm-bulk-items-open');
        if (bulkReturnFocus) { bulkReturnFocus.focus(); }
    }

    function openBulkItems() {
        bulkReturnFocus = document.activeElement;
        selectedBulkItems.clear();
        document.getElementById('idlm-bulk-items-search').value = '';
        bulkModal.hidden = false;
        document.body.classList.add('idlm-bulk-items-open');
        document.getElementById('idlm-bulk-items-available').innerHTML = '<p class="idlm-bulk-items-empty">Loading catalogue…</p>';
        document.getElementById('idlm-bulk-items-search').focus();
        loadBulkItems('');
    }

    function loadBulkItems(term) {
        if (bulkRequest) { bulkRequest.abort(); }
        bulkRequest = new AbortController();
        document.getElementById('idlm-bulk-items-available').innerHTML = '<p class="idlm-bulk-items-empty">Loading catalogue…</p>';
        fetch(idlmQuoteData.ajaxUrl + '?action=idlm_search_quote_catalogue&bulk=1&nonce=' + encodeURIComponent(idlmQuoteData.nonce) + '&term=' + encodeURIComponent(term), {credentials: 'same-origin', signal: bulkRequest.signal}).then(function (response) {
            if (!response.ok) { throw new Error('Catalogue request failed.'); }
            return response.json();
        }).then(function (payload) {
            if (!payload.success || !Array.isArray(payload.data)) { throw new Error(payload.data && payload.data.message ? payload.data.message : 'Catalogue request failed.'); }
            bulkItems = payload.data;
            renderBulkItems();
        }).catch(function (error) {
            if ('AbortError' !== error.name) { document.getElementById('idlm-bulk-items-available').innerHTML = '<p class="idlm-bulk-items-empty">' + escapeHtml(error.message || 'Catalogue items could not be loaded.') + '</p>'; }
        });
    }

    document.getElementById('idlm-open-bulk-items').addEventListener('click', openBulkItems);
    bulkModal.querySelector('.idlm-bulk-items-close').addEventListener('click', closeBulkItems);
    bulkModal.querySelector('.idlm-bulk-items-cancel').addEventListener('click', closeBulkItems);
    bulkModal.addEventListener('click', function (event) {
        if (event.target === bulkModal) { closeBulkItems(); return; }
        var addButton = event.target.closest('[data-bulk-item-id]');
        var removeButton = event.target.closest('[data-remove-bulk-item-id]');
        if (addButton) {
            var item = bulkItems.find(function (candidate) { return String(candidate.id) === addButton.dataset.bulkItemId; });
            if (item) { selectedBulkItems.set(String(item.id), item); renderBulkItems(); }
        } else if (removeButton) {
            selectedBulkItems.delete(removeButton.dataset.removeBulkItemId);
            renderBulkItems();
        }
    });
    var bulkSearchTimer = 0;
    document.getElementById('idlm-bulk-items-search').addEventListener('input', function (event) {
        window.clearTimeout(bulkSearchTimer);
        bulkSearchTimer = window.setTimeout(function () { loadBulkItems(event.target.value.trim()); }, 250);
    });
    document.getElementById('idlm-add-bulk-items').addEventListener('click', function () {
        var targetSection = sections.querySelector('.idlm-quote-section:last-child');
        var body = targetSection.querySelector('.idlm-section-items');
        var availableBlank = body.querySelector('.idlm-quote-item-row:not(.idlm-quote-header-row):last-child');
        Array.from(selectedBulkItems.values()).forEach(function (item, index) {
            var canReuse = index === 0 && availableBlank && !field(availableBlank, 'item_name').value && !field(availableBlank, 'description').value;
            var row = canReuse ? availableBlank : blankRow(body.querySelector('.idlm-quote-item-row:not(.idlm-quote-header-row)'));
            if (!canReuse) { body.appendChild(row); }
            populateCatalogueRow(row, item);
        });
        renumberAll(); calculate(); closeBulkItems();
    });
    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape' && !bulkModal.hidden) { closeBulkItems(); }
    });

    document.getElementById('idlm-add-section').addEventListener('click', function () {
        var source = sections.querySelector('.idlm-quote-section:last-child'), clone = source.cloneNode(true), key = 'section-new-' + Date.now();
        clone.dataset.sectionKey = key; clone.querySelector('.idlm-section-name').value = 'New Section';
        var body = clone.querySelector('.idlm-section-items'), first = blankRow(body.querySelector('.idlm-quote-item-row:not(.idlm-quote-header-row)')); body.innerHTML = ''; body.appendChild(first); sections.appendChild(clone); renumberAll(); calculate();
    });

    var headerButtons = Array.from(sections.querySelectorAll('.idlm-add-section-header'));
    var globalHeaderButton = headerButtons.shift();
    headerButtons.forEach(function (button) { button.remove(); });
    if (globalHeaderButton) {
        var addSectionRow = document.querySelector('.idlm-add-section-row');
        var addSectionButton = document.getElementById('idlm-add-section');
        addSectionRow.insertBefore(globalHeaderButton, addSectionButton);
    }

    form.addEventListener('click', function (event) {
        var row = event.target.closest('.idlm-quote-item-row');
        if (event.target.classList.contains('idlm-add-section-item')) { var targetSection = event.target.closest('.idlm-quote-section') || sections.querySelector('.idlm-quote-section:last-child'); var body = targetSection.querySelector('.idlm-section-items'); body.appendChild(blankRow(body.querySelector('.idlm-quote-item-row:not(.idlm-quote-header-row)'))); }
        else if (event.target.classList.contains('idlm-add-section-header')) { var headerSection = event.target.closest('.idlm-quote-section') || sections.querySelector('.idlm-quote-section:last-child'); var headerBody = headerSection.querySelector('.idlm-section-items'); var newHeader = headerRow(); headerBody.appendChild(newHeader); newHeader.querySelector('.idlm-inline-header-input').focus(); }
        else if (event.target.classList.contains('idlm-move-row-up') && row && row.previousElementSibling) { row.parentNode.insertBefore(row, row.previousElementSibling); }
        else if (event.target.classList.contains('idlm-move-row-down') && row && row.nextElementSibling) { row.parentNode.insertBefore(row.nextElementSibling, row); }
        else if (event.target.classList.contains('idlm-move-section-up')) { var sectionUp = event.target.closest('.idlm-quote-section'); if (sectionUp.previousElementSibling) { sections.insertBefore(sectionUp, sectionUp.previousElementSibling); } }
        else if (event.target.classList.contains('idlm-move-section-down')) { var sectionDown = event.target.closest('.idlm-quote-section'); if (sectionDown.nextElementSibling) { sections.insertBefore(sectionDown.nextElementSibling, sectionDown); } }
        else if (event.target.classList.contains('idlm-duplicate-row') && row) { row.parentNode.insertBefore(row.cloneNode(true), row.nextSibling); }
        else if (event.target.classList.contains('idlm-remove-row') && row) {
            var isHeader = row.classList.contains('idlm-quote-header-row');
            var remainingLineItems = row.parentNode.querySelectorAll('.idlm-quote-item-row:not(.idlm-quote-header-row)').length;
            if (isHeader || remainingLineItems > 1) { row.remove(); }
        }
        else if (event.target.classList.contains('idlm-remove-section') && sections.querySelectorAll('.idlm-quote-section').length > 1) { event.target.closest('.idlm-quote-section').remove(); }
        renumberAll(); calculate();
    });

    var catalogueSearchState = new WeakMap();
    form.addEventListener('input', function (event) {
        if (!event.target.classList.contains('idlm-catalogue-search')) { calculate(); return; }
        var input = event.target, results = input.nextElementSibling, term = input.value.trim(), previous = catalogueSearchState.get(input) || {};
        window.clearTimeout(previous.timer);
        if (previous.controller) { previous.controller.abort(); }
        if (term.length < 2) { results.innerHTML = ''; catalogueSearchState.delete(input); return; }
        var state = {timer: 0, controller: null, sequence: (previous.sequence || 0) + 1};
        state.timer = window.setTimeout(function () {
            state.controller = new AbortController();
            var sequence = state.sequence;
            fetch(idlmQuoteData.ajaxUrl + '?action=idlm_search_quote_catalogue&nonce=' + encodeURIComponent(idlmQuoteData.nonce) + '&term=' + encodeURIComponent(term), {credentials: 'same-origin', signal: state.controller.signal}).then(function (response) {
                if (!response.ok) { throw new Error('Catalogue search failed.'); }
                return response.json();
            }).then(function (payload) {
                var current = catalogueSearchState.get(input);
                if (!current || current.sequence !== sequence) { return; }
                if (!payload.success || !Array.isArray(payload.data)) { throw new Error(payload.data && payload.data.message ? payload.data.message : 'Catalogue search failed.'); }
                results.innerHTML = payload.data.map(function (item) { return '<button type="button" class="idlm-catalogue-result" data-item="' + encodeURIComponent(JSON.stringify(item)) + '"><strong>' + escapeHtml(item.item_name) + '</strong><small>' + escapeHtml(item.item_code + ' · ' + item.category + ' · Wood: ' + (Number(item.is_wood) === 1 ? 'Yes' : 'No')) + '</small></button>'; }).join('');
            }).catch(function (error) { if ('AbortError' !== error.name) { results.innerHTML = '<p class="idlm-catalogue-search-error">' + escapeHtml(error.message || 'Catalogue search failed.') + '</p>'; } });
        }, 250);
        catalogueSearchState.set(input, state);
    });

    form.addEventListener('click', function (event) {
        var result = event.target.closest('.idlm-catalogue-result'); if (!result) { return; }
        var item = JSON.parse(decodeURIComponent(result.dataset.item)), row = result.closest('.idlm-quote-item-row');
        populateCatalogueRow(row, item); row.querySelector('.idlm-catalogue-results').innerHTML = ''; calculate();
    });

    form.addEventListener('change', function (event) {
        if (event.target.classList.contains('idlm-measurement-input') && event.target.value !== '') { event.target.value = Math.max(0, num(event.target)).toFixed(2); }
        calculate();
    }); renumberAll(); calculate();
})();
