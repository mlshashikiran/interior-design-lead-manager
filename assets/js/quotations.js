(function () {
    'use strict';
    // QUO008 integer decimal helpers. Keep rounding identical to the PHP calculator.
    function decimalUnits(value, minimum, maximum) {
        var text = String(value == null ? '' : value).trim() || '0';
        var match = /^\+?(?:([0-9]+)(?:\.([0-9]*))?|\.([0-9]+))$/.exec(text);
        if (text.length > 128 || !match) { return null; }
        var whole = (match[1] || '0').replace(/^0+/, '') || '0';
        var fraction = ((match[2] || match[3] || '') + '000');
        if (whole.length > String(Math.floor(maximum / 100)).length) { return null; }
        var units = Number(whole) * 100 + Number(fraction.slice(0, 2)) + (Number(fraction[2]) >= 5 ? 1 : 0);
        return units > maximum ? null : Math.max(minimum, units);
    }
    function decimalProduct(left, right, maximum) {
        if (right && left > Math.floor(maximum * 100 / right)) { return null; }
        var units = Math.floor((left * right + 50) / 100);
        return units > maximum ? null : units;
    }
    function decimalPercentage(amount, percent) {
        return Math.floor(amount / 10000) * percent + Math.floor(((amount % 10000) * percent + 5000) / 10000);
    }
    // End QUO008 integer decimal helpers.
    function crmTrapFocus(dialog) {
        dialog.addEventListener('keydown', function (event) {
            if (event.key !== 'Tab') { return; }
            var items = Array.from(dialog.querySelectorAll('a[href],button:not([disabled]),input:not([disabled]):not([type="hidden"]),select:not([disabled]),textarea:not([disabled]),[tabindex="0"]')).filter(function (item) { return item.offsetParent !== null; });
            if (!items.length) { return; }
            var first = items[0], last = items[items.length - 1];
            if (event.shiftKey && document.activeElement === first) { event.preventDefault(); last.focus(); }
            else if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first.focus(); }
        });
    }
    function crmFocusFirst(dialog) {
        var target = dialog.querySelector('input:not([type="hidden"]):not([readonly]),select,textarea');
        if (target) { target.focus(); }
    }
    // A notice from a redirect is shown once; it must not return on reload.
    var crmNoticeUrl = new URL(window.location.href);
    if (crmNoticeUrl.searchParams.has('idlm_notice') || crmNoticeUrl.searchParams.has('idlm_error')) {
        crmNoticeUrl.searchParams.delete('idlm_notice');
        crmNoticeUrl.searchParams.delete('idlm_error');
        window.history.replaceState({}, '', crmNoticeUrl.href);
    }
    document.addEventListener('click', function (event) {
        document.querySelectorAll('.idlm-quote-menu[open]').forEach(function (menu) { if (!menu.contains(event.target)) { menu.open = false; } });
    });
    document.addEventListener('keydown', function (event) {
        if (event.key !== 'Escape') { return; }
        document.querySelectorAll('.idlm-quote-menu[open]').forEach(function (menu) { menu.open = false; menu.querySelector('summary').focus(); });
    });
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
    function escapeHtml(value) { var node = document.createElement('div'); node.textContent = value == null ? '' : String(value); return node.innerHTML.replace(/"/g, '&quot;').replace(/'/g, '&#39;'); }
    function currencyCode(value) { value = String(value || '').trim().toUpperCase(); return /^[A-Z]{3}$/.test(value) ? value : 'INR'; }
    function formatMoney(value, code) {
        code = currencyCode(code || (idlmQuoteData && idlmQuoteData.currencyCode));
        try { return new Intl.NumberFormat(code !== 'INR' ? 'en-US' : 'en-IN', {style: 'currency', currency: code, maximumFractionDigits: 2}).format(Number(value || 0)); }
        catch (error) { return code + ' ' + Number(value || 0).toFixed(2); }
    }
    function liveListUrl(args) {
        var url = new URL(window.location.href);
        Object.keys(args).forEach(function (key) { if (args[key]) { url.searchParams.set(key, args[key]); } else { url.searchParams.delete(key); } });
        return url;
    }
    function crmDate(value) {
        // Stored values are already in site time, so format the parts without converting them.
        var match = /^(\d{4})-(\d{2})-(\d{2})(?: (\d{2}):(\d{2}))?/.exec(String(value || ''));
        if (!match || Number(match[2]) < 1) { return {date: '—', time: ''}; }
        var hour = Number(match[4] || 0);
        return {
            date: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'][Number(match[2]) - 1] + ' ' + Number(match[3]) + ', ' + match[1],
            time: match[4] ? ((hour % 12) || 12) + ':' + match[5] + ' ' + (hour < 12 ? 'AM' : 'PM') : ''
        };
    }
    function crmStatusBadge(status) {
        var label = String(status || 'Draft').trim() || 'Draft';
        var tones = {shared: 'is-info', accepted: 'is-success', approved: 'is-success', rejected: 'is-danger', expired: 'is-warning'};
        return '<span class="idlm-badge ' + (tones[label.toLowerCase()] || 'is-neutral') + '">' + escapeHtml(label.charAt(0).toUpperCase() + label.slice(1).toLowerCase()) + '</span>';
    }
    function crmCreator(row) {
        if (row.creator_name) { return escapeHtml(row.creator_name); }
        return Number(row.created_by) > 0 ? '<span class="idlm-quote-creator-none" title="This user account no longer exists.">Former user</span>' : '<span class="idlm-quote-creator-none" title="Created from the lead’s quote value during migration, so no user was recorded.">Migrated</span>';
    }
    function crmQuotationRow(row) {
        var href = escapeHtml(liveListUrl({action: 'view', quote_id: row.id}).href);
        var customer = [row.first_name, row.last_name].filter(Boolean).join(' ') || '—';
        var revision = /(R\d+)$/.exec(String(row.revision_name || ''));
        var updated = crmDate(row.updated_at);
        return '<tr class="idlm-quote-row" data-href="' + href + '"><td data-label="Quotation"><a class="idlm-quote-identity-link" href="' + href + '"><strong>' + escapeHtml(row.quote_number) + '</strong></a><small>' + escapeHtml(row.title) + '</small></td><td data-label="Customer"><strong>' + escapeHtml(customer) + '</strong><small>' + escapeHtml([row.project_name, row.phone].filter(Boolean).join(' · ')) + '</small></td><td data-label="Status"><span class="idlm-quote-status-cell">' + crmStatusBadge(row.status) + '<span class="idlm-quote-revision-label">' + escapeHtml(revision ? revision[1] : (row.revision_name || '—')) + '</span></span></td><td data-label="Value" class="idlm-quote-num">' + escapeHtml(formatMoney(row.grand_total, row.currency_code)) + '</td><td data-label="Created by">' + crmCreator(row) + '<small>' + escapeHtml(crmDate(row.created_at).date) + '</small></td><td data-label="Updated"><span class="idlm-table-timestamp"><span class="idlm-table-timestamp-date">' + escapeHtml(updated.date) + '</span><span class="idlm-table-timestamp-time">' + escapeHtml(updated.time) + '</span></span></td><td class="idlm-quote-row-action"><a class="idlm-btn idlm-btn-secondary idlm-btn-quiet" href="' + href + '" aria-label="' + escapeHtml('Open ' + row.quote_number) + '">Open</a></td></tr>';
    }
    function crmCatalogueRow(row) {
        var editUrl = escapeHtml(liveListUrl({workspace: 'catalogue', edit_item: row.id, catalogue_search: document.querySelector('[name="catalogue_search"]').value}).href);
        var name = escapeHtml(row.item_name), active = 'active' === row.status;
        return '<tr class="' + (active ? '' : 'is-archived') + '"><td class="idlm-checkbox-column"><input type="checkbox" name="item_ids[]" value="' + escapeHtml(row.id) + '" data-row-select aria-label="Select ' + name + '"></td><td data-label="Code">' + escapeHtml(row.item_code) + '</td><td data-label="Category">' + escapeHtml(row.category) + '</td><td data-label="Item" class="idlm-catalogue-item-cell"><strong>' + name + '</strong><small title="' + escapeHtml(row.description) + '">' + escapeHtml(row.description) + '</small></td><td data-label="Unit">' + escapeHtml(row.unit) + '</td><td data-label="Wood">' + (Number(row.is_wood) ? 'Yes' : 'No') + '</td><td data-label="Rate" class="idlm-quote-num">' + escapeHtml(formatMoney(row.default_rate)) + '</td><td data-label="Status">' + (active ? 'Active' : '<span class="idlm-badge is-neutral">Archived</span>') + '</td><td class="idlm-catalogue-row-actions"><div class="idlm-catalogue-actions"><a class="button-link" href="' + editUrl + '" aria-label="Edit ' + name + '">Edit</a>' + (active ? '<button class="button-link-delete" name="bulk_action" value="archive" data-single-submit aria-label="Archive ' + name + '">Archive</button>' : '') + '<button class="button-link-delete" name="bulk_action" value="delete" data-single-submit aria-label="Delete ' + name + '">Delete</button></div></td></tr>';
    }
    function crmEmptyRow(type, filtered) {
        var clear = '<div class="idlm-state__actions"><button type="button" class="idlm-btn idlm-btn-secondary" data-quote-filter-clear>Clear ' + ('catalogue' === type ? 'search' : 'filters') + '</button></div>';
        var message = 'catalogue' === type
            ? (filtered ? '<strong>No catalogue items match this search</strong><span>Try a different name, code or category.</span>' + clear : '<strong>No catalogue items yet</strong><span>Add items here so they can be searched and copied into quotations.</span>')
            : (filtered ? '<strong>No quotations match these filters</strong><span>Try a different name, phone or date range.</span>' + clear : '<strong>No quotations yet</strong><span>A quotation is created for a lead, and revisions keep its history.</span>');
        return '<tr class="idlm-quote-empty"><td colspan="' + ('catalogue' === type ? 9 : 7) + '"><div class="idlm-state is-empty">' + message + '</div></td></tr>';
    }
    function crmPagination(type, data) {
        var total = Number(data.total), perPage = Number(data.perPage), page = Number(data.page), pages = Math.max(1, Math.ceil(total / perPage)), noun = 'catalogue' === type ? 'items' : 'quotations', number = new Intl.NumberFormat('en-US');
        if (!total) { return ''; }
        return '<span class="idlm-pagination-info">' + number.format(((page - 1) * perPage) + 1) + '–' + number.format(Math.min(total, page * perPage)) + ' of ' + number.format(total) + ' ' + noun + '</span>' +
            (page > 1 ? '<button type="button" class="idlm-btn idlm-btn-secondary" data-live-list-page="' + (page - 1) + '">Previous</button>' : '') +
            (page < pages ? '<button type="button" class="idlm-btn idlm-btn-secondary" data-live-list-page="' + (page + 1) + '">Next</button>' : '');
    }
    function quotationRow(row) {
 return crmQuotationRow(row);
        var customer = [row.first_name, row.last_name].filter(Boolean).join(' ') || '—';
        return '<tr class="idlm-quote-row" data-href="' + escapeHtml(liveListUrl({action: 'view', quote_id: row.id}).href) + '"><td><strong>' + escapeHtml(row.quote_number) + '</strong><br><small>' + escapeHtml(row.title) + '</small></td><td><strong>' + escapeHtml(customer) + '</strong><br><small>' + escapeHtml([row.phone, row.project_name].filter(Boolean).join(' · ')) + '</small></td><td>' + escapeHtml(row.revision_name || '—') + '</td><td>' + escapeHtml(formatMoney(row.grand_total, row.currency_code)) + '</td><td>' + escapeHtml(row.creator_name || 'Unknown') + '<br><small>' + escapeHtml(row.created_at) + '</small></td><td>' + escapeHtml(row.updated_at) + '</td><td><a class="idlm-btn idlm-btn-secondary" href="' + escapeHtml(liveListUrl({action: 'view', quote_id: row.id}).href) + '">Open</a></td></tr>';
    }
    function catalogueRow(row) {
 return crmCatalogueRow(row);
        var editUrl = liveListUrl({workspace: 'catalogue', edit_item: row.id, catalogue_search: document.querySelector('[name="catalogue_search"]').value}).href;
        return '<tr><td class="idlm-checkbox-column"><input type="checkbox" name="item_ids[]" value="' + escapeHtml(row.id) + '" data-row-select aria-label="Select ' + escapeHtml(row.item_name) + '"></td><td>' + escapeHtml(row.item_code) + '</td><td>' + escapeHtml(row.category) + '</td><td><strong>' + escapeHtml(row.item_name) + '</strong><br><small>' + escapeHtml(row.description) + '</small></td><td>' + escapeHtml(row.unit) + '</td><td>' + (Number(row.is_wood) ? 'Yes' : 'No') + '</td><td>' + escapeHtml(formatMoney(row.default_rate)) + '</td><td>' + escapeHtml((row.status || '').charAt(0).toUpperCase() + (row.status || '').slice(1)) + '</td><td><div class="idlm-catalogue-actions"><a class="button-link" href="' + escapeHtml(editUrl) + '">Edit</a>' + ('active' === row.status ? '<button class="button-link-delete" name="bulk_action" value="archive" data-single-submit>Archive</button>' : '') + '<button class="button-link-delete" name="bulk_action" value="delete" data-single-submit>Delete</button></div></td></tr>';
    }
    document.querySelectorAll('[data-live-list]').forEach(function (filterForm) {
        var type = filterForm.dataset.liveList;
        var rows = document.querySelector('[data-live-list-rows="' + type + '"]');
        var pagination = document.querySelector('[data-live-list-pagination="' + type + '"]');
        var status = document.querySelector('[data-live-list-status="' + type + '"]');
        var searchInput = filterForm.querySelector(type === 'catalogue' ? '[name="catalogue_search"]' : '[name="quote_search"]');
        if (!rows || !searchInput) { return; }
        var timer = 0, controller = null, page = 1;
        var crmFilterFields = 'quotations' === type ? ['created_by', 'date_range', 'date_from', 'date_to'] : [];
        function crmFiltersActive() {
            return !!searchInput.value.trim() || crmFilterFields.some(function (name) { return filterForm.elements[name] && filterForm.elements[name].value; });
        }
        function crmSyncUrl(data) {
            var args = {};
            args['catalogue' === type ? 'catalogue_search' : 'quote_search'] = searchInput.value;
            args['catalogue' === type ? 'catalogue_page' : 'quote_page'] = data.page > 1 ? data.page : null;
            crmFilterFields.forEach(function (name) { args[name] = filterForm.elements[name] ? filterForm.elements[name].value : null; });
            // Rows read this URL, so opening a record and coming back keeps the filters and page.
            window.history.replaceState({}, '', liveListUrl(args).href);
        }
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
 rows.setAttribute('aria-busy', 'true');
            fetch(idlmQuoteData.ajaxUrl + '?' + params.toString(), {credentials: 'same-origin', signal: controller.signal}).then(function (response) {
                if (!response.ok) { throw new Error('Search failed.'); }
                return response.json();
            }).then(function (payload) {
                if (!payload.success || !payload.data || !Array.isArray(payload.data.rows)) { throw new Error(payload.data && payload.data.message ? payload.data.message : 'Search failed.'); }
                var data = payload.data, rowMarkup = type === 'catalogue' ? catalogueRow : quotationRow;
 crmSyncUrl(data);
                rows.innerHTML = data.rows.length ? data.rows.map(rowMarkup).join('') : (crmEmptyRow(type, crmFiltersActive()));
                if ('quotations' === type) { bindQuotationRows(rows); }
                var pages = Math.max(1, Math.ceil(Number(data.total) / Number(data.perPage)));
                rows.removeAttribute('aria-busy');
                rows.dispatchEvent(new CustomEvent('idlm-rows-updated', {bubbles: true}));
                var filterClear = filterForm.querySelector('[data-quote-filter-clear]');
                if (filterClear) { filterClear.hidden = !crmFiltersActive(); }
                pagination.innerHTML = crmPagination(type, data);
                if (status) { status.textContent = data.total + ' result' + (Number(data.total) === 1 ? '' : 's') + ' found.'; }
 return;
                var url = liveListUrl({[type === 'catalogue' ? 'catalogue_search' : 'quote_search']: searchInput.value, [type === 'catalogue' ? 'catalogue_page' : 'quote_page']: data.page > 1 ? data.page : null});
                window.history.replaceState({}, '', url.href);
            }).catch(function (error) {
                if ('AbortError' === error.name) { return; }
                if (status) { status.textContent = error.message || 'Search failed.'; }
                // Keep the rows on screen and offer the same request again.
                rows.removeAttribute('aria-busy');
                window.IDLMDialog.notify('The list could not be updated. Check your connection and try again.', 'error', {action: {label: 'Retry', onClick: function () { request(page); }}});
            });
        }
        filterForm.addEventListener('submit', function (event) { event.preventDefault(); window.clearTimeout(timer); request(1); });
        searchInput.addEventListener('input', function () { window.clearTimeout(timer); timer = window.setTimeout(function () { request(1); }, 250); });
        pagination.addEventListener('click', function (event) { var button = event.target.closest('[data-live-list-page]'); if (button) { event.preventDefault(); rows.closest('.idlm-table-wrapper').scrollIntoView({block: 'nearest'}); request(Number(button.dataset.liveListPage)); } });
        filterForm.classList.add('is-live');
        filterForm.addEventListener('change', function (event) { if (event.target !== searchInput) { window.clearTimeout(timer); request(1); } });
        document.addEventListener('click', function (event) {
            var clear = event.target.closest('[data-quote-filter-clear]');
            if (!clear) { return; }
            event.preventDefault();
            searchInput.value = '';
            crmFilterFields.forEach(function (name) { if (filterForm.elements[name]) { filterForm.elements[name].value = ''; } });
            if (window.IDLMDialog && window.IDLMDialog.syncDateRange) { window.IDLMDialog.syncDateRange(filterForm.elements.date_range); }
            request(1);
            searchInput.focus();
        });
    });
    if (catalogueSortForm) {
        catalogueSortForm.classList.add('is-live');
        catalogueSortForm.addEventListener('change', function () { catalogueSortForm.requestSubmit(); });
    }
    var catalogueEditor = document.querySelector('.idlm-catalogue-editor');
    // The catalogue editor is a page of its own, so closing it reloads the list. Remember what opened it and give that control focus back.
    var catalogueReturnKey = 'idlmCatalogueReturn';
    if (!/[?&](add_item|edit_item)=/.test(window.location.search)) {
        try {
            var catalogueReturn = window.sessionStorage.getItem(catalogueReturnKey);
            window.sessionStorage.removeItem(catalogueReturnKey);
            if (catalogueReturn) {
                var catalogueTrigger = 'add' === catalogueReturn
                    ? document.querySelector('a[href*="add_item="]')
                    : document.querySelector('a[href*="edit_item=' + String(catalogueReturn).replace(/\D/g, '') + '&"], a[href$="edit_item=' + String(catalogueReturn).replace(/\D/g, '') + '"]');
                if (catalogueTrigger) { catalogueTrigger.focus({preventScroll: true}); }
            }
        } catch (error) { /* Storage can be unavailable; focus then stays at the top of the page. */ }
    }
    if (catalogueEditor) {
        var catalogueParams = new URLSearchParams(window.location.search);
        if ((catalogueParams.has('edit_item') || catalogueParams.has('add_item'))) {
            try { window.sessionStorage.setItem(catalogueReturnKey, catalogueParams.has('edit_item') ? 'edit:' + catalogueParams.get('edit_item') : 'add'); } catch (error) { /* Not needed to work. */ }
        }
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
 crmTrapFocus(catalogueEditor); crmFocusFirst(catalogueEditor);
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
 crmTrapFocus(catalogueImport); crmFocusFirst(catalogueImport);
        }
    }
    var importForm = document.querySelector('[data-catalogue-import-form]');
    if (importForm && window.idlmQuoteData) {
        var importFile = importForm.querySelector('[name="catalogue_csv"]');
        var previewButton = importForm.querySelector('[data-catalogue-preview]');
        var confirmImport = importForm.querySelector('[data-catalogue-confirm]');
        var importDone = importForm.querySelector('[data-catalogue-import-done]');
        var importStatus = importForm.querySelector('[data-catalogue-import-status]');
        var importPreview = importForm.querySelector('[data-catalogue-import-preview]');
        var importRequest = 0;
        var importBusy = false;
        var selectedImportFile = null;
        if (catalogueImport) { catalogueImport.setAttribute('aria-labelledby', 'idlm-catalogue-import-title'); }
        function showImportRows(report, complete) {
            importPreview.replaceChildren();
            var summary = document.createElement('p');
            summary.className = 'idlm-catalogue-import-summary';
            summary.textContent = report.total + ' items: ' + (complete ? report.imported + ' imported' : report.valid + ' ready to import') +
                ', ' + report.invalid + ' invalid, ' + report.duplicates + ' duplicate codes skipped.';
            importPreview.appendChild(summary);
            if (!complete && report.generated_codes) {
                var warning = document.createElement('p');
                warning.textContent = report.generated_codes + ' items have blank codes and will receive new codes. Reimporting them creates additional items.';
                importPreview.appendChild(warning);
            }
            var scroll = document.createElement('div');
            scroll.className = 'idlm-catalogue-import-table';
            scroll.tabIndex = 0;
            scroll.setAttribute('role', 'region');
            scroll.setAttribute('aria-label', 'CSV row validation results');
            var table = document.createElement('table');
            table.className = 'widefat striped';
            var head = table.createTHead().insertRow();
            ['CSV row', 'Item', 'Code', 'Category', 'Unit', 'Rate', 'Result'].forEach(function (label) {
                var cell = document.createElement('th'); cell.scope = 'col'; cell.textContent = label; head.appendChild(cell);
            });
            var body = table.createTBody();
            report.rows.forEach(function (row) {
                var tr = body.insertRow();
                tr.dataset.state = row.state;
                [row.row, row.data.item_name, row.data.item_code || 'Auto-generated', row.data.category, row.data.unit,
                    row.data.default_rate, (row.state === 'valid' ? 'Ready' : row.state.charAt(0).toUpperCase() + row.state.slice(1)) +
                    (row.messages.length ? ': ' + row.messages.join(' ') : '')].forEach(function (value) {
                    tr.insertCell().textContent = String(value);
                });
            });
            scroll.appendChild(table); importPreview.appendChild(scroll);
        }
        function requestCatalogueImport(mode) {
            if (importBusy || !importFile.files.length) { return; }
            if (mode === 'import' && (confirmImport.disabled || selectedImportFile !== importFile.files[0])) { return; }
            var file = importFile.files[0];
            var request = ++importRequest;
            importBusy = true;
            confirmImport.disabled = true;
            previewButton.disabled = true;
            importFile.disabled = true;
            importForm.setAttribute('aria-busy', 'true');
            importStatus.classList.remove('is-error');
            importStatus.textContent = mode === 'preview' ? 'Checking file and existing item codes…' : 'Importing valid items…';
            var formData = new FormData(importForm);
            // Disabled file inputs are excluded from FormData.
            formData.set('catalogue_csv', file);
            formData.set('import_mode', mode);
            fetch(window.idlmQuoteData.ajaxUrl, { method: 'POST', body: formData, credentials: 'same-origin' })
                .then(function (response) {
                    return response.text().then(function (text) {
                        var result;
                        try { result = JSON.parse(text); } catch (error) { throw new Error('The server returned an unreadable response. Your session may have expired.'); }
                        if (!result.success || !result.data || !Array.isArray(result.data.rows)) {
                            throw new Error(result.data && result.data.message || 'Unable to process the CSV. Check your session and try again.');
                        }
                        return result.data;
                    });
                }).then(function (report) {
                    if (request !== importRequest) { return; }
                    showImportRows(report, mode === 'import');
                    selectedImportFile = file;
                    confirmImport.hidden = mode === 'import';
                    confirmImport.disabled = mode === 'import' || report.valid === 0;
                    confirmImport.textContent = 'Import ' + report.valid + ' valid items';
                    importDone.hidden = mode !== 'import';
                    previewButton.hidden = mode === 'import';
                    importStatus.textContent = mode === 'import' ? 'Import complete. Review the results below, then refresh the catalogue.' :
                        (report.valid ? 'Preview ready. Invalid rows and duplicate codes will be skipped.' : 'No items can be imported. Review the row errors below and choose a corrected file.');
                }).catch(function (error) {
                    if (request !== importRequest) { return; }
                    selectedImportFile = null;
                    importStatus.classList.add('is-error');
                    importStatus.textContent = error.message + (mode === 'import' ? ' If the connection was interrupted, check the catalogue before importing again; the server may have completed the import.' : '');
                    if (mode === 'import') { importDone.hidden = false; }
                }).finally(function () {
                    if (request !== importRequest) { return; }
                    importBusy = false; importFile.disabled = false; previewButton.disabled = false;
                    importForm.removeAttribute('aria-busy');
                });
        }
        importForm.addEventListener('submit', function (event) { event.preventDefault(); requestCatalogueImport('preview'); });
        importFile.addEventListener('change', function () {
            selectedImportFile = null; confirmImport.hidden = true; confirmImport.disabled = true;
            importDone.hidden = true; previewButton.hidden = false; importPreview.replaceChildren(); importStatus.textContent = '';
            requestCatalogueImport('preview');
        });
        confirmImport.addEventListener('click', function () { requestCatalogueImport('import'); });
        importDone.addEventListener('click', function () {
            var params = new URLSearchParams(window.location.search);
            params.delete('import_catalogue'); params.delete('idlm_notice'); params.delete('idlm_error');
            window.location.search = params.toString();
        });
    }
    var categoryManager = document.querySelector('.idlm-category-manager.is-open');
    if (categoryManager) {
        if (categoryManager.parentNode !== document.body) { document.body.appendChild(categoryManager); }
        document.body.classList.add('idlm-catalogue-editor-open');
        document.addEventListener('keydown', function (event) { if (event.key === 'Escape') { categoryManager.querySelector('.idlm-catalogue-editor-close').click(); } });
 crmTrapFocus(categoryManager); var managerField = categoryManager.querySelector('input[name="unit_name"],input[name="category_name"]'); if (managerField) { managerField.focus(); }
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
            if (counter && counter.closest('.idlm-bulk-action-bar')) { counter.closest('.idlm-bulk-action-bar').classList.toggle('has-selection', selected > 0); }
            if (selectAll) { selectAll.checked = checkboxes.length > 0 && selected === checkboxes.length; selectAll.indeterminate = selected > 0 && selected < checkboxes.length; }
        }
        if (selectAll) { selectAll.addEventListener('change', function () { rowSelects().forEach(function (checkbox) { checkbox.checked = selectAll.checked; }); updateSelection(); }); }
        bulkForm.addEventListener('change', function (event) { if (event.target.matches('[data-row-select]')) { updateSelection(); } });
 bulkForm.addEventListener('idlm-rows-updated', updateSelection);
        var crmSingleSubmit = false;
        bulkForm.addEventListener('click', function (event) {
            var button = event.target.closest('[data-single-submit]');
            if (button) {
                rowSelects().forEach(function (checkbox) { checkbox.checked = false; });
                var rowCheckbox = button.closest('tr, .idlm-category-manager-list>div').querySelector('[data-row-select]');
                if (rowCheckbox) { rowCheckbox.checked = true; }
                if (actionSelect) { actionSelect.value = button.value; }
                crmSingleSubmit = true;
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
            if (actionSelect && ('delete' === actionSelect.value || ('archive' === actionSelect.value)) && bulkForm.dataset.idlmDeleteConfirmed !== 'true') {
                event.preventDefault();
                var crmDialog = {};
                var crmCount = rowSelects().filter(function (checkbox) { return checkbox.checked; }).length;
                var crmNoun = bulkForm.dataset.noun || 'record';
                var crmName = crmCount + ' ' + (crmCount === 1 ? crmNoun : ('category' === crmNoun ? 'categories' : crmNoun + 's'));
                var crmDelete = 'delete' === actionSelect.value;
                crmDialog = {
                    title: (crmDelete ? 'Delete ' : 'Archive ') + crmName + '?',
                    message: crmDelete ? 'This permanently deletes ' + crmName + ' and cannot be undone. ' + (bulkForm.dataset.deleteNote || '') : (bulkForm.dataset.archiveNote || ''),
                    confirmLabel: (crmDelete ? 'Delete ' : 'Archive ') + crmName,
                    destructive: crmDelete
                };
                window.IDLMDialog.confirm(Object.assign({
                    title: 'Delete selected records?',
                    message: bulkForm.dataset.deleteMessage || 'Permanently delete the selected records?',
                    confirmLabel: 'Delete selected',
                    destructive: true,
                    trigger: event.submitter || document.activeElement
                }, crmDialog)).then(function (confirmed) {
                    if (!confirmed) {
                        if (crmSingleSubmit) {
                            // A cancelled row action must not leave that row selected.
                            rowSelects().forEach(function (checkbox) { checkbox.checked = false; });
                            actionSelect.value = '';
                            crmSingleSubmit = false;
                            updateSelection();
                        }
                        return;
                    }
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
        var paymentTotal = document.querySelector('[data-payment-total]');
        function updatePaymentTotal() {
            var total = Array.from(paymentStructure.querySelectorAll('input[name$="[percentage]"]')).reduce(function (sum, input) { return sum + Math.max(0, Number(input.value) || 0); }, 0);
            if (paymentTotal) { paymentTotal.textContent = 'Milestone total: ' + total.toFixed(2) + '% of 100%.' + (total > 100 ? ' Reduce the milestones by ' + (total - 100).toFixed(2) + '% before saving.' : ''); paymentTotal.classList.toggle('idlm-payment-total-error', total > 100); }
        }
        function renumberPaymentRows() {
            paymentStructure.querySelectorAll('.idlm-payment-structure-row').forEach(function (row, index) {
                row.querySelectorAll('[name]').forEach(function (input) { input.name = input.name.replace(/payment_structure\[\d+\]/, 'payment_structure[' + index + ']'); });
            });
        }
        document.querySelector('[data-add-payment-row]').addEventListener('click', function () {
            var source = paymentStructure.querySelector('.idlm-payment-structure-row:last-child');
            var row = source.cloneNode(true);
            row.querySelectorAll('input').forEach(function (input) { input.value = ''; });
            paymentStructure.appendChild(row); renumberPaymentRows(); updatePaymentTotal();
        });
        paymentStructure.addEventListener('click', function (event) {
            if (!event.target.matches('[data-remove-payment-row]')) { return; }
            var rows = paymentStructure.querySelectorAll('.idlm-payment-structure-row');
            if (rows.length > 1) { event.target.closest('.idlm-payment-structure-row').remove(); }
            else { rows[0].querySelectorAll('input').forEach(function (input) { input.value = ''; }); }
            renumberPaymentRows(); updatePaymentTotal();
        });
        paymentStructure.addEventListener('input', updatePaymentTotal);
        updatePaymentTotal();
    }
    var crmSettingsForm = document.querySelector('.idlm-quotation-settings');
    if (crmSettingsForm) {
        var crmSettingsDirty = false;
        var crmNumberPreview = crmSettingsForm.querySelector('.idlm-settings-preview p');
        crmSettingsForm.addEventListener('input', function (event) {
            crmSettingsDirty = true;
            if (crmNumberPreview && /number_(prefix|separator|padding)/.test(event.target.name || '')) {
                var separator = crmSettingsForm.elements.number_separator.value;
                crmNumberPreview.textContent = crmSettingsForm.elements.number_prefix.value + separator + new Date().getFullYear() + separator + '125'.padStart(Math.max(3, Math.min(10, Number(crmSettingsForm.elements.number_padding.value) || 0)), '0');
            }
        });
        crmSettingsForm.addEventListener('change', function () { crmSettingsDirty = true; });
        crmSettingsForm.addEventListener('click', function (event) { if (event.target.closest('[data-add-payment-row],[data-remove-payment-row]')) { crmSettingsDirty = true; } });
        crmSettingsForm.addEventListener('submit', function (event) {
            var percentages = Array.from(crmSettingsForm.querySelectorAll('input[name$="[percentage]"]'));
            var milestoneTotal = percentages.reduce(function (sum, input) { return sum + Math.max(0, Number(input.value) || 0); }, 0);
            // Compare whole hundredths: 0.01 + 64.18 + 35.81 sums to just over 100 in floating point.
            if (Math.round(milestoneTotal * 100) > 10000) {
                event.preventDefault();
                window.IDLMDialog.notify('Milestones total ' + milestoneTotal.toFixed(2) + '%. Reduce them to 100% or less, then save. Nothing has been lost.', 'error');
                percentages[0].focus();
                return;
            }
            crmSettingsDirty = false;
        });
        window.addEventListener('beforeunload', function (event) {
            if (!crmSettingsDirty) { return; }
            event.preventDefault();
            event.returnValue = '';
        });
    }
    var form = document.getElementById('idlm-quote-form');
    if (!form) { return; }
    var sections = document.getElementById('idlm-quote-sections');
    var bulkModal = document.getElementById('idlm-bulk-items-modal');
    var bulkItems = [];
    var bulkRequest = null;
    // Design Reps keep the rates they start with; the server enforces this too.
    if (form.dataset.lockRates) { form.querySelectorAll('[name$="[unit_rate]"]').forEach(function (input) { input.readOnly = true; }); }
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
    var revisionReasonPromptOpen = false;
    function ensureRevisionReason(event) {
        if (Number(form.elements.quote_id.value) <= 0) { return true; }
        var revisionReason = form.elements.revision_reason;
        if (revisionReason && revisionReason.value.trim()) { return true; }
        event.preventDefault();
        if (revisionReasonPromptOpen) { return false; }
        revisionReasonPromptOpen = true;
        window.IDLMDialog.prompt({
            title: 'Save new revision',
            message: 'Add a revision remark. The previous quotation version will be preserved.',
            label: 'Revision remark',
            placeholder: 'Explain what changed and why',
            confirmLabel: 'Save revision',
            required: true,
            requiredMessage: 'Enter a revision remark to save this revision.',
            trigger: event.submitter || document.activeElement
        }).then(function(remark) {
            revisionReasonPromptOpen = false;
            if (!remark || !revisionReason) { return; }
            revisionReason.value = remark;
            form.requestSubmit();
        });
        return false;
    }
    // A new quotation with no lead is refused by the server after the page has gone. Catch it here so nothing typed is lost.
    var crmLeadField = document.getElementById('idlm-quote-lead');
    form.addEventListener('submit', function (event) {
        if (Number(form.elements.quote_id.value) > 0 || !crmLeadField || Number(crmLeadField.value) > 0) { return; }
        event.preventDefault();
        event.stopImmediatePropagation();
        window.IDLMDialog.notify('Choose a lead before saving this quotation. Your entries are still here.', 'error');
        document.getElementById('idlm-lead-search').focus();
    });
    // Enter in a search box searches; it must not submit the quotation.
    form.addEventListener('keydown', function (event) {
        if (event.key === 'Enter' && event.target.matches('#idlm-lead-search,#idlm-bulk-items-search,.idlm-catalogue-search')) { event.preventDefault(); }
        // Nor in any plain field. Textareas keep their line breaks and buttons still press.
        if (event.key === 'Enter' && event.target.matches('input:not([type="submit"],[type="button"],[type="reset"],[type="image"],[type="file"])')) { event.preventDefault(); }
    });
    form.addEventListener('submit', ensureRevisionReason);
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
        if (form.dataset.idlmNumericInvalid === '1') { autosaveSaving = false; setAutosaveStatus('Correct invalid numeric values before autosaving.', 'error'); return; }
        setAutosaveStatus('Saving…', 'saving');
        var autosaveData = new FormData(form);
        autosaveData.set('action', form.dataset.autosaveAction || 'idlm_autosave_quote');
        autosaveData.set('nonce', form.dataset.autosaveNonce || idlmQuoteData.autosaveNonce);
        fetch(idlmQuoteData.ajaxUrl, {method: 'POST', body: autosaveData, credentials: 'same-origin', keepalive: !!useKeepalive})
            .then(function (response) { return response.json().then(function (payload) { return {payload: payload, status: response.status}; }); })
            .then(function (result) {
                var payload = result.payload;
                if (!payload.success) { var autosaveError = new Error(payload.data && payload.data.message ? payload.data.message : 'Autosave failed.'); autosaveError.retry = result.status >= 500; throw autosaveError; }
                autosaveHasWorkingCopy = true;
                formDirty = false;
                autosaveStatus.dataset.hasWorkingCopy = '1';
                setAutosaveStatus('Saved at ' + ((function (stamp) {
                    var clock = /^(\d{1,2}):(\d{2})/.exec(String(stamp || ''));
                    return clock ? ((Number(clock[1]) % 12) || 12) + ':' + clock[2] + ' ' + (Number(clock[1]) < 12 ? 'AM' : 'PM') : stamp;
                }(payload.data.savedAt))), 'saved');
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
            if (event.defaultPrevented) { return; }
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
                discardData.set('action', form.dataset.discardAction || 'idlm_discard_quote_autosave');
                discardData.set('nonce', form.dataset.autosaveNonce || idlmQuoteData.autosaveNonce);
                if (form.elements.handoff_id) { discardData.set('handoff_id', form.elements.handoff_id.value); }
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
    var leadField = document.getElementById('idlm-quote-lead');
    var createdByField = document.getElementById('idlm-quote-created-by');
    function num(input) { var value = parseFloat(input && input.value); return Number.isFinite(value) ? value : 0; }
    function field(row, suffix) { return row.querySelector('[name$="[' + suffix + ']"]'); }
    function escapeHtml(value) { var node = document.createElement('div'); node.textContent = value; return node.innerHTML.replace(/"/g, '&quot;').replace(/'/g, '&#39;'); }

    if (createdByField) {
        var salesRepValue = document.getElementById('idlm-quote-sales-rep');
        if (salesRepValue && salesRepValue.parentNode && !document.getElementById('idlm-quote-created-by-value')) {
            var creatorRow = document.createElement('div');
            creatorRow.innerHTML = '<dt>Created by</dt><dd id="idlm-quote-created-by-value">' + escapeHtml(createdByField.value || 'Unknown') + '</dd>';
            salesRepValue.parentNode.insertAdjacentElement('afterend', creatorRow);
        }
    }

    if (leadSearch && leadField) {
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
        function applyLead(lead) {
            leadField.value = lead.id;
            form.elements.customer_name.value = [lead.first_name, lead.last_name].filter(Boolean).join(' ');
            form.elements.customer_email.value = lead.email || '';
            form.elements.customer_phone.value = lead.phone || '';
            form.elements.project_name.value = lead.project_name || '';
            form.elements.property_location.value = lead.property_location || '';
            document.getElementById('idlm-quote-studio').textContent = lead.preferred_studio || '—';
            document.getElementById('idlm-quote-sales-rep').textContent = lead.assigned_rep_name || 'Unassigned';
            leadSearch.value = [lead.first_name, lead.last_name].filter(Boolean).join(' ') + (lead.project_name ? ' — ' + lead.project_name : '');
        }
        leadResults.addEventListener('click', function (event) {
            var result = event.target.closest('[data-lead-id]');
            if (!result) { return; }
            var lead = leadSearchRows.get(result.dataset.leadId);
            if (lead) { applyLead(lead); }
            leadResults.hidden = true;
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
        var limits = idlmQuoteData.numericLimits, invalid = false;
        function reject(input, message) { invalid = true; if (input) { input.setCustomValidity(message); } }
        function units(input, minimum, maximum) {
            if (!input) { return 0; }
            input.setCustomValidity('');
            var value = decimalUnits(input.value, minimum, maximum);
            if (value === null || input.validity.badInput) { reject(input, 'Enter a nonnegative decimal within the supported range; exponent notation is not accepted.'); return 0; }
            return value;
        }
        var currencyInput = form.elements.currency_code;
        var currentCurrency = currencyCode(currencyInput ? currencyInput.value : idlmQuoteData.currencyCode);
        function money(value) { return formatMoney(value / 100, currentCurrency); }
        function updateCurrencySymbols() {
            var symbol = money(0).replace(/[\d,.\s]/g, '') || currentCurrency + ' ';
            form.querySelectorAll('.idlm-currency-input > span').forEach(function (element) { element.textContent = symbol; });
        }
        updateCurrencySymbols();
        var subtotal = 0, taxable = 0, woodGroups = {Wood: {base: 0}, Others: {base: 0}}, sectionTotals = [];
        sections.querySelectorAll('.idlm-quote-section').forEach(function (section) {
            var sectionTotal = 0;
            section.querySelectorAll('.idlm-quote-item-row').forEach(function (row) {
                if (field(row, 'row_type') && 'header' === field(row, 'row_type').value) { return; }
                var quantityField = field(row, 'quantity');
                var width = units(field(row, 'length'), 0, limits.maxMeasure), height = units(field(row, 'height'), 0, limits.maxMeasure);
                var qty = units(quantityField, 1, limits.maxMeasure);
                if (width > 0 && height > 0) {
                    qty = decimalProduct(width, height, limits.maxMeasure);
                    if (qty === null) { reject(field(row, 'length'), 'Billable area exceeds the supported quantity range.'); qty = 1; }
                    qty = Math.max(1, qty); quantityField.value = (qty / 100).toFixed(2);
                }
                var rateField = field(row, 'unit_rate'), rate = units(rateField, 0, limits.maxAmount), base = decimalProduct(qty, rate, limits.maxAmount);
                if (base === null) { reject(rateField, 'Line subtotal exceeds the supported amount range.'); base = 0; }
                var net = base;
                var optionalField = field(row, 'is_optional'), optional = 'checkbox' === optionalField.type ? optionalField.checked : '1' === optionalField.value, total = net;
                row.querySelector('.idlm-line-total').textContent = optional ? 'Optional' : money(total); row.classList.toggle('is-optional', optional);
                if (!optional) { subtotal += base; sectionTotal += total; var woodGroup = Number(field(row, 'is_wood').value) === 1 ? 'Wood' : 'Others'; woodGroups[woodGroup].base += net; }
            });
            var sectionName = String(section.querySelector('.idlm-section-name').value || 'Untitled Section').trim() || 'Untitled Section';
            section.querySelector('.idlm-section-subtotal-label').textContent = sectionName + (' subtotal');
            section.querySelector('.idlm-section-subtotal').textContent = money(sectionTotal);
            sectionTotals.push({name: sectionName, total: sectionTotal});
        });
        Object.keys(woodGroups).forEach(function (group) {
            woodGroups[group].total = woodGroups[group].base;
            taxable += woodGroups[group].base;
        });
        var woodenSubtotal = woodGroups.Wood.base;
        var woodenDiscountPercent = units(form.elements.wooden_discount_percent, 0, 10000);
        var woodenDiscount = decimalPercentage(woodenSubtotal, woodenDiscountPercent);
        var afterWoodenDiscount = Math.max(0, woodenSubtotal - woodenDiscount);
        var otherSubtotal = subtotal - woodenSubtotal;
        var totalsPanel = form.querySelector('#idlm-live-totals');
        var softClosePercent = decimalUnits(totalsPanel.dataset.softClosePercent || '0', 0, 10000);
        var softCloseInvalid = softClosePercent === null;
        if (softCloseInvalid) { softClosePercent = 0; }
        var softCloseEnabled = form.elements.soft_close_enabled.checked;
        var softClose = softCloseEnabled ? decimalPercentage(afterWoodenDiscount, softClosePercent) : 0;
        var total = afterWoodenDiscount + otherSubtotal + softClose;
        var additionalDiscount = Math.min(total, units(form.elements.additional_discount_amount, 0, limits.maxAmount));
        var afterAdditionalDiscount = Math.max(0, total - additionalDiscount);
        var gst = decimalPercentage(afterAdditionalDiscount, 1800);
        var preRoundTotal = afterAdditionalDiscount + gst;
        var grand = Math.floor((preRoundTotal + 50) / 100) * 100;
        var roundOff = grand - preRoundTotal;
        if (softCloseInvalid) { reject(form.elements.additional_discount_amount, 'Ask the administrator to correct the soft close percentage.'); }
        if (subtotal > limits.maxAmount || total > limits.maxAmount || preRoundTotal > limits.maxAmount || grand > limits.maxAmount) { reject(form.elements.additional_discount_amount, 'Quotation total including GST and round off exceeds 9,999,999,999.99.'); }
        if (form.querySelectorAll('.idlm-quote-item-row').length > limits.maxRows || sections.querySelectorAll('.idlm-quote-section').length > limits.maxSections) { reject(form.elements.additional_discount_amount, 'A quotation supports at most 200 rows and 50 sections.'); }
        form.dataset.idlmNumericInvalid = invalid ? '1' : '0';
        var hasAdditionalDiscount = additionalDiscount > 0;
        form.querySelector('[data-total="subtotal"]').textContent = money(subtotal);
        form.querySelector('[data-total="wooden-subtotal"]').textContent = money(woodenSubtotal);
        form.querySelector('[data-total="wooden-discount"]').textContent = money(woodenDiscount);
        form.querySelector('[data-total="after-wooden-discount"]').textContent = money(afterWoodenDiscount);
        form.querySelector('[data-total="other-subtotal"]').textContent = money(otherSubtotal);
        form.querySelector('[data-total="soft-close"]').textContent = money(softClose);
        form.querySelector('[data-total="total"]').textContent = money(total);
        form.querySelector('[data-after-additional-discount-row]').hidden = !hasAdditionalDiscount;
        form.querySelector('[data-total="after-additional-discount"]').textContent = money(afterAdditionalDiscount);
        form.querySelector('[data-total="tax"]').textContent = money(gst);
        var roundOffOutput = form.querySelector('[data-total="round-off"]');
        if (!roundOffOutput) {
            var roundOffRow = document.createElement('div');
            roundOffRow.innerHTML = '<dt>' + ('Round off') + '</dt><dd data-total="round-off"></dd>';
            form.querySelector('.idlm-grand-total').before(roundOffRow);
            roundOffOutput = roundOffRow.querySelector('[data-total="round-off"]');
        }
        roundOffOutput.textContent = money(roundOff);
        form.querySelector('[data-total="grand"]').textContent = money(grand);
        var totalMirror = form.querySelector('[data-quote-total-mirror]');
        if (totalMirror) { totalMirror.textContent = money(grand); }
        document.getElementById('idlm-wood-totals').innerHTML = Object.keys(woodGroups).map(function (group) { return '<div><span>' + group + '</span><strong>' + escapeHtml(money(woodGroups[group].total)) + '</strong></div>'; }).join('');
        document.getElementById('idlm-section-totals').innerHTML = sectionTotals.map(function (section) { return '<div><span>' + escapeHtml(section.name) + '</span><strong>' + escapeHtml(money(section.total)) + '</strong></div>'; }).join('') || '<p>No section totals yet.</p>';
    }

    var addAdditionalDiscount = form.querySelector('[data-add-additional-discount]');
    if (addAdditionalDiscount) addAdditionalDiscount.addEventListener('click', function () {
        form.querySelector('[data-additional-discount-row]').hidden = false;
        addAdditionalDiscount.hidden = true;
        form.elements.additional_discount_amount.focus();
    });

    function blankRow(source) {
        var clone = source.cloneNode(true);
        clone.classList.remove('is-catalogue-item');
        if (form.dataset.lockRates) { var cloneRate = clone.querySelector('[name$="[unit_rate]"]'); if (cloneRate) { cloneRate.readOnly = false; } }
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
        if (form.dataset.lockRates) { field(row, 'unit_rate').readOnly = true; }
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
 document.getElementById('idlm-add-bulk-items').textContent = selected.length ? 'Add ' + selected.length + (selected.length === 1 ? ' item' : ' items') : 'Add selected';
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
 crmTrapFocus(bulkModal);
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

    function crmRemoveWithUndo(node, message, nextFocus) {
        var parent = node.parentNode, next = node.nextSibling;
        node.remove();
        if (nextFocus) { nextFocus.focus(); }
        window.IDLMDialog.notify(message, 'info', {duration: 8000, action: {label: 'Undo', onClick: function () {
            parent.insertBefore(node, next && next.parentNode === parent ? next : null);
            renumberAll(); calculate();
            // The Undo button leaves with its toast, so focus goes to the restored item.
            var restoredControl = node.querySelector('input:not([type="hidden"]), select, textarea, button');
            if (restoredControl) { restoredControl.focus(); }
            // A new quotation has no autosave, and no submit listener to clear the dirty flag this would set.
            if (autosaveEnabled) { queueAutosave(); }
        }}});
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
            if (isHeader || remainingLineItems > 1) {
 var rowNeighbour = row.nextElementSibling || row.previousElementSibling; crmRemoveWithUndo(row, isHeader ? 'Header removed.' : 'Line item removed.', rowNeighbour && rowNeighbour.querySelector('.idlm-remove-row'));
            } else window.IDLMDialog.notify('A section needs at least one line item. Remove the section instead.', 'warning');
        }
        else if (event.target.classList.contains('idlm-remove-section') && sections.querySelectorAll('.idlm-quote-section').length > 1) {
            var removedSection = event.target.closest('.idlm-quote-section');
 var removedName = removedSection.querySelector('.idlm-section-name').value.trim() || 'Untitled section', removedCount = removedSection.querySelectorAll('.idlm-quote-item-row:not(.idlm-quote-header-row)').length, sectionNeighbour = removedSection.nextElementSibling || removedSection.previousElementSibling; crmRemoveWithUndo(removedSection, removedName + ' removed with ' + removedCount + (removedCount === 1 ? ' line item.' : ' line items.'), sectionNeighbour && sectionNeighbour.querySelector('.idlm-remove-section'));
        }
        else if (event.target.classList.contains('idlm-remove-section')) { window.IDLMDialog.notify('A quotation needs at least one section.', 'warning'); }
        renumberAll(); calculate();
    });

    var crmOpenResults = null;
    function crmPlaceResults(input, results) {
        var rect = input.getBoundingClientRect(), below = window.innerHeight - rect.bottom - 12, above = rect.top - 12, flip = below < 180 && above > below;
        results.classList.add('is-floating');
        results.style.left = rect.left + 'px';
        results.style.width = Math.max(rect.width, 280) + 'px';
        results.style.top = flip ? 'auto' : (rect.bottom + 4) + 'px';
        results.style.bottom = flip ? (window.innerHeight - rect.top + 4) + 'px' : 'auto';
        results.style.maxHeight = Math.min(280, flip ? above : below) + 'px';
        crmOpenResults = {input: input, results: results};
    }
    function crmCloseResults() {
        if (!crmOpenResults) { return; }
        crmOpenResults.results.innerHTML = '';
        crmOpenResults = null;
    }
    window.addEventListener('scroll', function () { if (crmOpenResults) { crmPlaceResults(crmOpenResults.input, crmOpenResults.results); } }, true);
    window.addEventListener('resize', function () { if (crmOpenResults) { crmPlaceResults(crmOpenResults.input, crmOpenResults.results); } });
    document.addEventListener('click', function (event) {
        if (crmOpenResults && !crmOpenResults.results.contains(event.target) && event.target !== crmOpenResults.input) { crmCloseResults(); }
    });
    form.addEventListener('keydown', function (event) {
        if (!crmOpenResults) { return; }
        var options = Array.from(crmOpenResults.results.querySelectorAll('.idlm-catalogue-result'));
        var current = options.indexOf(document.activeElement);
        if (event.key === 'Escape') { var searchField = crmOpenResults.input; crmCloseResults(); searchField.focus(); event.stopPropagation(); }
        else if (event.key === 'ArrowDown' && options.length) { event.preventDefault(); options[Math.min(options.length - 1, current + 1)].focus(); }
        else if (event.key === 'ArrowUp' && options.length) { event.preventDefault(); if (current <= 0) { crmOpenResults.input.focus(); } else { options[current - 1].focus(); } }
    });
    var catalogueSearchState = new WeakMap();
    form.addEventListener('input', function (event) {
        if (!event.target.classList.contains('idlm-catalogue-search')) { calculate(); return; }
        var input = event.target, results = input.nextElementSibling, term = input.value.trim(), previous = catalogueSearchState.get(input) || {};
        window.clearTimeout(previous.timer);
        if (previous.controller) { previous.controller.abort(); }
        if (term.length < 2) { results.innerHTML = ''; catalogueSearchState.delete(input); if (crmOpenResults && crmOpenResults.results === results) { crmOpenResults = null; } return; }
 results.innerHTML = '<p class="idlm-catalogue-search-note">Searching the catalogue…</p>'; crmPlaceResults(input, results);
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
                if (!payload.data.length) { results.innerHTML = '<p class="idlm-catalogue-search-note">No catalogue match. Keep typing to enter a custom item.</p>'; }
                crmPlaceResults(input, results);
            }).catch(function (error) { if ('AbortError' !== error.name) { results.innerHTML = '<p class="idlm-catalogue-search-error">' + escapeHtml(error.message || 'Catalogue search failed.') + '</p>'; } });
        }, 250);
        catalogueSearchState.set(input, state);
    });

    form.addEventListener('click', function (event) {
        var result = event.target.closest('.idlm-catalogue-result'); if (!result) { return; }
        var item = JSON.parse(decodeURIComponent(result.dataset.item)), row = result.closest('.idlm-quote-item-row');
        populateCatalogueRow(row, item); row.querySelector('.idlm-catalogue-results').innerHTML = ''; crmOpenResults = null; calculate();
    });

    form.addEventListener('change', function (event) {
        if (event.target.matches('.idlm-calc[type="number"]') && event.target.value !== '') {
            var isMeasure = event.target.classList.contains('idlm-measurement-input');
            var maximum = isMeasure ? idlmQuoteData.numericLimits.maxMeasure : idlmQuoteData.numericLimits.maxAmount;
            var minimum = event.target.classList.contains('idlm-item-quantity') ? 1 : 0;
            var value = decimalUnits(event.target.value, minimum, maximum);
            if (value !== null) { event.target.value = (value / 100).toFixed(2); }
        }
        calculate();
    }); renumberAll(); calculate();
})();
