/**
 * Users and integration-log interaction enhancements.
 *
 * Builder: Shashi
 */
(function () {
    'use strict';

    function postAjax(data) {
        return window.fetch(window.idlm_vars.ajax_url, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'},
            body: new URLSearchParams(data).toString()
        }).then(function (response) {
            return response.json().catch(function () {
                throw new Error('The server returned an unreadable response.');
            });
        });
    }

    function setPageStatus(page, message, isError) {
        var status = page.querySelector('[data-idlm-log-page-status]');
        if (!status) {
            return;
        }
        status.textContent = message || '';
        status.classList.toggle('is-error', !!isError);
        status.setAttribute('role', isError ? 'alert' : 'status');
    }

    function payloadDialog(page) {
        return page.querySelector('.idlm-log-payload-dialog');
    }

    function resetPayloadDialog(dialog, label) {
        dialog.querySelector('[data-idlm-log-dialog-title]').textContent = label || 'Integration payload';
        dialog.querySelector('[data-idlm-log-dialog-status]').textContent = 'Loading request and response…';
        dialog.querySelector('[data-idlm-log-payload]').textContent = 'Loading…';
        dialog.querySelector('[data-idlm-log-response]').textContent = 'Loading…';
        dialog.querySelectorAll('[data-idlm-log-copy]').forEach(function (button) {
            button.disabled = true;
        });
    }

    function inspectPayload(button) {
        var page = button.closest('[data-idlm-log-page]');
        var dialog = page ? payloadDialog(page) : null;
        if (!page || !dialog || !window.IDLMDialog) {
            return;
        }

        var type = button.dataset.logType;
        var action = 'capi' === type ? 'idlm_get_capi_log_payload' : 'idlm_get_audience_log_payload';
        resetPayloadDialog(dialog, button.dataset.logLabel || 'Integration payload');
        window.IDLMDialog.open(dialog, {trigger: button});

        postAjax({
            action: action,
            nonce: page.dataset.idlmLogNonce,
            log_id: button.dataset.logId
        }).then(function (response) {
            if (!response.success) {
                throw new Error(response.data && response.data.message ? response.data.message : 'Unable to load this log.');
            }
            dialog.querySelector('[data-idlm-log-payload]').textContent = response.data.payload || 'No request payload was recorded.';
            dialog.querySelector('[data-idlm-log-response]').textContent = response.data.response || 'No platform response was recorded.';
            dialog.querySelector('[data-idlm-log-dialog-status]').textContent = 'Payload loaded.';
            dialog.querySelectorAll('[data-idlm-log-copy]').forEach(function (copyButton) {
                copyButton.disabled = false;
            });
        }).catch(function (error) {
            dialog.querySelector('[data-idlm-log-payload]').textContent = error.message;
            dialog.querySelector('[data-idlm-log-response]').textContent = '';
            dialog.querySelector('[data-idlm-log-dialog-status]').textContent = 'Unable to load this log. Try again.';
        });
    }

    function copyPayload(button) {
        var dialog = button.closest('.idlm-log-payload-dialog');
        var selector = 'payload' === button.dataset.idlmLogCopy ? '[data-idlm-log-payload]' : '[data-idlm-log-response]';
        var source = dialog ? dialog.querySelector(selector) : null;
        var status = dialog ? dialog.querySelector('[data-idlm-log-dialog-status]') : null;
        if (!source || !navigator.clipboard) {
            if (status) {
                status.textContent = 'Copy is not available in this browser. Select the text manually.';
            }
            return;
        }
        navigator.clipboard.writeText(source.textContent).then(function () {
            status.textContent = 'Copied to clipboard.';
        }).catch(function () {
            status.textContent = 'Copy failed. Select the text manually.';
        });
    }

    function updateCapiSelection(page) {
        var selectors = Array.prototype.slice.call(page.querySelectorAll('.idlm-capi-log-selector'));
        var selected = selectors.filter(function (checkbox) { return checkbox.checked; });
        var selectAll = page.querySelector('#idlm-select-all-capi');
        var bulkBar = page.querySelector('[data-idlm-capi-bulk-bar]');
        var count = page.querySelector('[data-idlm-selected-capi-count]');

        if (selectAll) {
            selectAll.checked = selectors.length > 0 && selected.length === selectors.length;
            selectAll.indeterminate = selected.length > 0 && selected.length < selectors.length;
        }
        if (bulkBar) {
            bulkBar.hidden = 0 === selected.length;
        }
        if (count) {
            count.textContent = selected.length + ' log' + (1 === selected.length ? '' : 's') + ' selected';
        }
    }

    function selectFailed(button) {
        var page = button.closest('[data-idlm-log-page="capi"]');
        if (!page) {
            return;
        }
        var count = 0;
        page.querySelectorAll('tbody tr').forEach(function (row) {
            var box = row.querySelector('.idlm-capi-log-selector');
            if (box) {
                box.checked = row.classList.contains('is-failed');
                count += box.checked ? 1 : 0;
            }
        });
        updateCapiSelection(page);
        setPageStatus(page, count + ' failed event' + (1 === count ? '' : 's') + ' selected. Review them, then choose Retry selected events.', false);
    }

    function retrySelected(button) {
        var page = button.closest('[data-idlm-log-page="capi"]');
        var selected = page ? Array.prototype.map.call(page.querySelectorAll('.idlm-capi-log-selector:checked'), function (checkbox) {
            return checkbox.value;
        }) : [];
        if (!page || !selected.length) {
            if (page) {
                setPageStatus(page, 'Select at least one conversion log to retry.', true);
            }
            return;
        }

        if (window.IDLMDialog && window.IDLMDialog.confirm && !button.dataset.idlmRetryConfirmed) {
            window.IDLMDialog.confirm({
                title: 'Retry ' + selected.length + ' conversion event' + (1 === selected.length ? '' : 's') + '?',
                message: 'Each selected event is sent to its ad platform again. Events that already reached the platform may be counted twice.',
                confirmLabel: 'Retry ' + selected.length + ' event' + (1 === selected.length ? '' : 's'),
                trigger: button
            }).then(function (confirmed) {
                if (confirmed) {
                    button.dataset.idlmRetryConfirmed = '1';
                    retrySelected(button);
                }
            });
            return;
        }
        delete button.dataset.idlmRetryConfirmed;

        var original = button.textContent;
        button.disabled = true;
        button.setAttribute('aria-busy', 'true');
        button.textContent = 'Retrying…';
        setPageStatus(page, 'Retrying ' + selected.length + ' selected event' + (1 === selected.length ? '' : 's') + '…', false);

        var data = {action: 'idlm_retry_capi_events', nonce: page.dataset.idlmLogNonce};
        selected.forEach(function (id, index) {
            data['log_ids[' + index + ']'] = id;
        });
        postAjax(data).then(function (response) {
            if (!response.success) {
                throw new Error(response.data && response.data.message ? response.data.message : 'The selected events could not be retried.');
            }
            setPageStatus(page, (response.data.message || 'Retry complete.') + ' Refresh the log to see new delivery entries.', false);
            page.querySelectorAll('.idlm-capi-log-selector:checked').forEach(function (checkbox) {
                checkbox.checked = false;
            });
            updateCapiSelection(page);
            if (window.IDLMDialog && window.IDLMDialog.notify) {
                window.IDLMDialog.notify(response.data.message || 'Retry complete.', 'success');
            }
        }).catch(function (error) {
            setPageStatus(page, error.message, true);
            if (window.IDLMDialog && window.IDLMDialog.notify) {
                window.IDLMDialog.notify(error.message, 'error');
            }
        }).finally(function () {
            button.disabled = false;
            button.removeAttribute('aria-busy');
            button.textContent = original;
        });
    }

    function openLeadsSentence(form) {
        var open = Number(form.dataset.idlmOpenLeads || 0);
        if (open < 1) { return ''; }
        return ' ' + open + ' open lead' + (1 === open ? '' : 's') + ' stay' + (1 === open ? 's' : '') + ' assigned to them until you reassign ' + (1 === open ? 'it' : 'them') + ' on Leads.';
    }

    function enhancePendingForm(form) {
        if (form.matches('.idlm-role-editor-form')) {
            form.addEventListener('submit', function (event) {
                var access = form.querySelector('[data-idlm-capability="idlm_access_leads"]');
                var people = Number(form.dataset.idlmRolePeople || 0);
                if (form.dataset.idlmAccessConfirmed || !access || access.checked || !access.defaultChecked || people < 1 || !window.IDLMDialog || !window.IDLMDialog.confirm) {
                    return;
                }
                event.preventDefault();
                event.stopImmediatePropagation();
                var who = people + (1 === people ? ' person' : ' people');
                window.IDLMDialog.confirm({
                    title: 'Remove CRM leads access from ' + (form.dataset.idlmRoleName || 'this role') + '?',
                    message: who + ' with this role will lose access to CRM leads when you save, unless another role or a grant on their own account gives it to them.',
                    confirmLabel: 'Remove access for ' + who,
                    destructive: true,
                    trigger: form.querySelector('button[type="submit"]')
                }).then(function (confirmed) {
                    if (confirmed) {
                        form.dataset.idlmAccessConfirmed = '1';
                        form.requestSubmit();
                    }
                });
            });
        }
        if (form.matches('.idlm-user-edit-form')) {
            form.addEventListener('submit', function (event) {
                var suspend = form.querySelector('input[name="crm_access_disabled"]');
                if (form.dataset.idlmSuspendConfirmed || !suspend || !suspend.checked || suspend.defaultChecked || !window.IDLMDialog || !window.IDLMDialog.confirm) {
                    return;
                }
                event.preventDefault();
                event.stopImmediatePropagation();
                window.IDLMDialog.confirm({
                    title: 'Suspend CRM access?',
                    message: (form.dataset.idlmUserName || 'This person') + ' will lose access to CRM leads and quotations from their next page load. Their account and lead history stay as they are, and you can restore access later.' + (form.dataset.idlmUserManagesSite ? ' This account can manage the site, so it keeps settings, users and backups.' : '') + openLeadsSentence(form),
                    confirmLabel: 'Suspend access',
                    destructive: true,
                    trigger: form.querySelector('button[type="submit"]')
                }).then(function (confirmed) {
                    if (confirmed) {
                        form.dataset.idlmSuspendConfirmed = '1';
                        form.requestSubmit();
                    }
                });
            });
        }
        form.addEventListener('submit', function () {
            if (!form.checkValidity()) {
                return;
            }
            var button = form.querySelector('button[type="submit"]');
            if (!button) {
                return;
            }
            button.dataset.idlmOriginalLabel = button.textContent;
            button.textContent = button.dataset.idlmSubmitLabel || 'Saving…';
            button.disabled = true;
            form.setAttribute('aria-busy', 'true');
        });
    }

    function updateFilterDrawerCount(drawer) {
        var count = Array.prototype.filter.call(drawer.querySelectorAll('[data-idlm-filter-field]'), function (field) {
            return '' !== String(field.value || '');
        }).length;
        var page = drawer.closest('.idlm-wrap');
        if (!page) {
            return;
        }
        page.querySelectorAll('[data-idlm-filter-count]').forEach(function (badge) {
            badge.textContent = count;
            badge.setAttribute('aria-label', count + ' active filter' + (1 === count ? '' : 's'));
            badge.hidden = 0 === count;
        });
    }

    function closeFilterDrawer(drawer) {
        var page = drawer.closest('.idlm-wrap');
        var trigger = page ? page.querySelector('[data-idlm-filter-open]') : null;
        var backdrop = page ? page.querySelector('[data-idlm-filter-backdrop]') : null;
        drawer.classList.remove('is-open');
        drawer.setAttribute('aria-hidden', 'true');
        if (backdrop) {
            backdrop.hidden = true;
        }
        if (trigger) {
            trigger.setAttribute('aria-expanded', 'false');
            trigger.focus();
        }
        document.body.classList.remove('idlm-filters-open');
    }

    function openFilterDrawer(trigger) {
        var page = trigger.closest('.idlm-wrap');
        var drawer = page && document.getElementById(trigger.getAttribute('aria-controls'));
        var backdrop = page ? page.querySelector('[data-idlm-filter-backdrop]') : null;
        if (!drawer) {
            return;
        }
        if (backdrop) {
            backdrop.hidden = false;
        }
        drawer.classList.add('is-open');
        drawer.setAttribute('aria-hidden', 'false');
        trigger.setAttribute('aria-expanded', 'true');
        document.body.classList.add('idlm-filters-open');
        window.setTimeout(function () {
            var close = drawer.querySelector('[data-idlm-filter-close]');
            (close || drawer).focus();
        }, 20);
    }

    function trapFilterDrawerFocus(event) {
        var drawer = document.querySelector('[data-idlm-filter-drawer].is-open');
        if (!drawer) {
            return;
        }
        if ('Escape' === event.key) {
            closeFilterDrawer(drawer);
            return;
        }
        if ('Tab' !== event.key) {
            return;
        }
        var focusable = Array.prototype.filter.call(drawer.querySelectorAll('a[href], button:not([disabled]), input:not([disabled]), select:not([disabled]), [tabindex]:not([tabindex="-1"])'), function (element) {
            return null !== element.offsetParent;
        });
        if (!focusable.length) {
            return;
        }
        var first = focusable[0];
        var last = focusable[focusable.length - 1];
        if (event.shiftKey && document.activeElement === first) {
            event.preventDefault();
            last.focus();
        } else if (!event.shiftKey && document.activeElement === last) {
            event.preventDefault();
            first.focus();
        }
    }

    document.addEventListener('click', function (event) {
        var filterOpen = event.target.closest('[data-idlm-filter-open]');
        if (filterOpen) {
            openFilterDrawer(filterOpen);
            return;
        }
        var filterClose = event.target.closest('[data-idlm-filter-close], [data-idlm-filter-backdrop]');
        if (filterClose) {
            var filterPage = filterClose.closest('.idlm-wrap');
            var filterDrawer = filterPage ? filterPage.querySelector('[data-idlm-filter-drawer].is-open') : null;
            if (filterDrawer) {
                closeFilterDrawer(filterDrawer);
            }
            return;
        }
        var inspect = event.target.closest('[data-idlm-log-inspect]');
        if (inspect) {
            inspectPayload(inspect);
            return;
        }
        var copy = event.target.closest('[data-idlm-log-copy]');
        if (copy) {
            copyPayload(copy);
            return;
        }
        var failedSelect = event.target.closest('[data-idlm-select-failed]');
        if (failedSelect) {
            selectFailed(failedSelect);
            return;
        }
        var permissionToggle = event.target.closest('[data-idlm-permission-all]');
        if (permissionToggle) {
            var group = permissionToggle.closest('.idlm-permission-group');
            var turnOn = '1' === permissionToggle.getAttribute('data-idlm-permission-all');
            group.querySelectorAll('input[type="checkbox"]').forEach(function (box) { box.checked = turnOn; });
            refreshPermissionForm(permissionToggle.closest('.idlm-permission-form'));
            return;
        }
        var showWebsite = event.target.closest('[data-idlm-show-website-roles]');
        if (showWebsite) {
            var websiteWrap = showWebsite.closest('[data-idlm-website-roles]');
            var websiteSelect = document.getElementById(showWebsite.getAttribute('aria-controls'));
            var websiteOptions = websiteWrap.querySelector('[data-idlm-website-role-options]');
            if (websiteSelect && websiteOptions && !websiteSelect.querySelector('optgroup')) {
                websiteSelect.appendChild(websiteOptions.content.cloneNode(true));
            }
            showWebsite.hidden = true;
            if (websiteSelect) { websiteSelect.focus(); }
            return;
        }
        var foldOpener = event.target.closest('[data-idlm-open-fold]');
        if (foldOpener) {
            var fold = document.getElementById(foldOpener.dataset.idlmOpenFold);
            var details = fold ? fold.querySelector('details') : null;
            if (details) {
                details.open = true;
                var firstField = details.querySelector('input, select');
                window.setTimeout(function () { if (firstField) { firstField.focus(); } }, 30);
            }
            return;
        }
        var retry = event.target.closest('#idlm-retry-capi-btn');
        if (retry) {
            retrySelected(retry);
        }
    });

    document.addEventListener('change', function (event) {
        var filterDrawer = event.target.closest('[data-idlm-filter-drawer]');
        if (filterDrawer) {
            updateFilterDrawerCount(filterDrawer);
        }
        var page = event.target.closest('[data-idlm-log-page="capi"]');
        if (!page) {
            return;
        }
        if ('idlm-select-all-capi' === event.target.id) {
            page.querySelectorAll('.idlm-capi-log-selector').forEach(function (checkbox) {
                checkbox.checked = event.target.checked;
            });
        }
        if (event.target.matches('#idlm-select-all-capi, .idlm-capi-log-selector')) {
            updateCapiSelection(page);
        }
    });

    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('[data-idlm-pending-form]').forEach(enhancePendingForm);
        document.querySelectorAll('[data-idlm-log-page="capi"]').forEach(updateCapiSelection);
        document.querySelectorAll('[data-idlm-filter-drawer]').forEach(updateFilterDrawerCount);
 document.querySelectorAll('.idlm-role-editor-form').forEach(refreshPermissionForm);
        var notice = document.querySelector('[data-idlm-users-notice]');
        if (notice) {
            notice.focus({preventScroll: false});
        }
    });
    document.addEventListener('keydown', trapFilterDrawerFocus);

    // Role editor: warnings never block saving. The server accepts what it always accepted.
    function refreshRoleEditor(form) {
        if (!form.matches('.idlm-role-editor-form')) { return; }
        var byCapability = {};
        form.querySelectorAll('[data-idlm-capability]').forEach(function (box) { byCapability[box.dataset.idlmCapability] = box; });
        var access = byCapability.idlm_access_leads;
        var assign = byCapability.idlm_assign_leads;
        var scope = form.querySelector('[data-idlm-role-scope]:checked');
        var isDefault = !!scope && 'default' === scope.value;

        var outcome = form.querySelector('[data-idlm-default-outcome]');
        if (outcome && access) {
            var state = !access.checked ? 'None' : (assign && assign.checked ? 'Assign' : 'Base');
            outcome.textContent = outcome.getAttribute('data-idlm-text-' + state.toLowerCase());
            var settingLink = form.querySelector('[data-idlm-default-link]');
            if (settingLink) { settingLink.hidden = !('Base' === state && '1' === outcome.dataset.idlmBaseDepends); }
        }

        var warnings = form.querySelector('[data-idlm-permission-warnings]');
        if (!warnings) { return; }
        var messages = [];
        var unmet = {};
        Object.keys(byCapability).forEach(function (capability) {
            var box = byCapability[capability];
            var needed = box.dataset.idlmNeeds ? byCapability[box.dataset.idlmNeeds] : null;
            if (box.checked && needed && !needed.checked) {
                (unmet[box.dataset.idlmNeeds] = unmet[box.dataset.idlmNeeds] || []).push(box.dataset.idlmCapabilityLabel);
            }
        });
        Object.keys(unmet).forEach(function (capability) {
            messages.push(unmet[capability].join(', ') + (1 === unmet[capability].length ? ' needs ' : ' need ') + byCapability[capability].dataset.idlmCapabilityLabel + ' to work. You can still save this.');
        });
        if (assign && assign.checked && !assign.defaultChecked && isDefault && (!access || access.checked)) {
            messages.push('With default lead visibility, Assign leads also shows this role all leads. Choose Only assigned leads to keep their view narrow.');
        }
        warnings.textContent = '';
        messages.forEach(function (message) {
            var line = document.createElement('p');
            line.className = 'idlm-alert idlm-alert-warning';
            line.setAttribute('data-idlm-notice-inline', '');
            line.textContent = message;
            warnings.appendChild(line);
        });
    }

    // Permission editing: nothing changes until Save, and leaving with unsaved ticks asks first.
    function refreshPermissionForm(form) {
        if (!form) { return; }
        var boxes = form.querySelectorAll('.idlm-permission-option input[type="checkbox"]');
        var on = 0;
        var dirty = false;
        boxes.forEach(function (box) {
            if (box.checked) { on += 1; }
            if (box.checked !== box.defaultChecked) { dirty = true; }
        });
        form.querySelectorAll('[data-idlm-role-scope]').forEach(function (radio) {
            if (radio.checked !== radio.defaultChecked) { dirty = true; }
        });
        refreshRoleEditor(form);
        form.classList.toggle('is-dirty', dirty);
        var status = form.querySelector('[data-idlm-permission-status]');
        if (status) { status.textContent = status.getAttribute(dirty ? 'data-idlm-dirty-text' : 'data-idlm-saved-text'); }
        var role = form.closest('.idlm-permission-role');
        var count = role ? role.querySelector('[data-idlm-permission-count]') : null;
        if (count) { count.textContent = on + ' of ' + boxes.length + ' permissions on' + (dirty ? ' (unsaved)' : ''); }
    }

    var submitting = false;
    document.addEventListener('change', function (event) {
        if (event.target.closest('.idlm-permission-option') || event.target.matches('[data-idlm-role-scope]')) { refreshPermissionForm(event.target.closest('.idlm-permission-form')); }
        if (event.target.matches('.idlm-user-edit-form input[name="crm_access_disabled"]')) {
            var suspendNote = event.target.closest('.idlm-user-edit-form').querySelector('[data-idlm-suspend-note]');
            if (suspendNote) { suspendNote.hidden = !event.target.checked; }
        }
    });
    document.addEventListener('submit', function () { submitting = true; }, true);
    // A rejected invite returns to an empty page, so keep what was typed for this tab, once.
    var inviteDraftKey = 'idlm_invite_draft';
    var inviteFields = ['first_name', 'last_name', 'user_login', 'email', 'phone'];
    var inviteForm = document.querySelector('[data-idlm-invite-form]');
    if (inviteForm) {
        try {
            var draft = inviteForm.hasAttribute('data-idlm-invite-restore') ? JSON.parse(window.sessionStorage.getItem(inviteDraftKey) || 'null') : null;
            window.sessionStorage.removeItem(inviteDraftKey);
            if (draft) {
                inviteFields.forEach(function (name) {
                    if (draft[name] && inviteForm.elements[name]) { inviteForm.elements[name].value = draft[name]; }
                });
                if (draft.user_login && inviteForm.querySelector('[data-idlm-login-fold]')) { inviteForm.querySelector('[data-idlm-login-fold]').open = true; }
            }
        } catch (ignore) {}
        inviteForm.addEventListener('submit', function () {
            try {
                var saved = {};
                inviteFields.forEach(function (name) { if (inviteForm.elements[name]) { saved[name] = inviteForm.elements[name].value; } });
                window.sessionStorage.setItem(inviteDraftKey, JSON.stringify(saved));
            } catch (ignore) {}
        });
    }
    window.addEventListener('beforeunload', function (event) {
        if (!submitting && document.querySelector('.idlm-permission-form.is-dirty')) {
            event.preventDefault();
            event.returnValue = '';
        }
    });
}());
