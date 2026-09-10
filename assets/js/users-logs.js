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
        }).catch(function (error) {
            setPageStatus(page, error.message, true);
        }).finally(function () {
            button.disabled = false;
            button.removeAttribute('aria-busy');
            button.textContent = original;
        });
    }

    function enhancePendingForm(form) {
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
        var notice = document.querySelector('[data-idlm-users-notice]');
        if (notice) {
            notice.focus({preventScroll: false});
        }
    });
    document.addEventListener('keydown', trapFilterDrawerFocus);
}());
