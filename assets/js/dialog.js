/**
 * Shared accessible dialog controller for IDLM admin and frontend surfaces.
 *
 * Builder: Shashi
 */
(function () {
    'use strict';

    // Mouse and touch users get no lingering focus ring; the keyboard keeps it. Design layer only.
    // A click that loads a new page (for example "Add item") must not show a ring on the field it focuses, so the pointer state survives one navigation.
    var pointerFlag = function (value) {
        try {
            if (null === value) { window.sessionStorage.removeItem('idlmPointerInput'); } else if (undefined === value) { return '1' === window.sessionStorage.getItem('idlmPointerInput'); } else { window.sessionStorage.setItem('idlmPointerInput', value); }
        } catch (error) { /* Storage can be unavailable; the ring then simply shows. */ }
        return false;
    };
    if (pointerFlag()) {
        document.body.classList.add('idlm-pointer-input');
    }
    document.addEventListener('pointerdown', function () {
        document.body.classList.add('idlm-pointer-input');
        pointerFlag('1');
    }, true);
    document.addEventListener('keydown', function (event) {
        pointerFlag(null);
        if ('Tab' === event.key) {
            document.body.classList.remove('idlm-pointer-input');
        }
    }, true);

    // Popups lock page scroll with overflow: hidden on body. That removes a classic scrollbar, so the
    // page behind would widen and shift. While locked, pad the body by the scrollbar's width, measured
    // just before the popup opens (on the click or key that opens it). Overlay scrollbars measure 0.
    var scrollLockClasses = ['idlm-modal-open', 'idlm-filters-open', 'idlm-catalogue-editor-open', 'idlm-bulk-items-open'];
    var scrollbarWidth = 0;
    var scrollPadded = false;
    var isScrollLocked = function () {
        return scrollLockClasses.some(function (name) { return document.body.classList.contains(name); });
    };
    var measureScrollbar = function () {
        if (!isScrollLocked()) {
            scrollbarWidth = Math.max(0, window.innerWidth - document.documentElement.clientWidth);
        }
    };
    var keepScrollbarSpace = function () {
        if (isScrollLocked()) {
            if (!scrollPadded && scrollbarWidth > 0) {
                document.body.style.setProperty('padding-right', scrollbarWidth + 'px');
                scrollPadded = true;
            }
        } else if (scrollPadded) {
            document.body.style.removeProperty('padding-right');
            scrollPadded = false;
        }
    };
    ['pointerdown', 'keydown'].forEach(function (type) { document.addEventListener(type, measureScrollbar, true); });
    window.addEventListener('resize', measureScrollbar);
    measureScrollbar();
    if (window.MutationObserver) {
        new MutationObserver(keepScrollbarSpace).observe(document.body, {attributes: true, attributeFilter: ['class']});
    }

    var activeDialog = null;
    var toastStack = null;
    var toastIcons = {success: 'dashicons-yes-alt', error: 'dashicons-dismiss', warning: 'dashicons-warning', info: 'dashicons-info'};
    var returnFocus = new WeakMap();
    var hiddenOnClose = new WeakMap();
    var inertedNodes = [];
    var focusableSelector = [
        'a[href]',
        'area[href]',
        'button:not([disabled])',
        'input:not([disabled]):not([type="hidden"])',
        'select:not([disabled])',
        'textarea:not([disabled])',
        'iframe',
        'object',
        'embed',
        '[contenteditable="true"]',
        '[tabindex]:not([tabindex="-1"])'
    ].join(',');

    function resolveDialog(target) {
        if (!target) {
            return null;
        }
        if (typeof target === 'string') {
            return document.querySelector(target);
        }
        if (target.jquery) {
            return target.get(0) || null;
        }
        return target.nodeType === 1 ? target : null;
    }

    function visibleFocusable(dialog) {
        return Array.prototype.filter.call(dialog.querySelectorAll(focusableSelector), function (element) {
            return !element.hidden && element.getAttribute('aria-hidden') !== 'true' && element.getClientRects().length > 0;
        });
    }

    function makeBackgroundInert(dialog) {
        var current = dialog;
        inertedNodes = [];
        while (current && current !== document.body) {
            var parent = current.parentElement;
            if (!parent) {
                break;
            }
            Array.prototype.forEach.call(parent.children, function (sibling) {
                if (sibling === current || sibling.tagName === 'SCRIPT' || sibling.tagName === 'STYLE') {
                    return;
                }
                inertedNodes.push({
                    node: sibling,
                    inert: !!sibling.inert,
                    ariaHidden: sibling.getAttribute('aria-hidden')
                });
                sibling.inert = true;
                sibling.setAttribute('aria-hidden', 'true');
            });
            current = parent;
        }
    }

    function restoreBackground() {
        inertedNodes.forEach(function (record) {
            record.node.inert = record.inert;
            if (record.ariaHidden === null) {
                record.node.removeAttribute('aria-hidden');
            } else {
                record.node.setAttribute('aria-hidden', record.ariaHidden);
            }
        });
        inertedNodes = [];
    }

    function updateScrollLock() {
        var hasOverlay = document.querySelector('.idlm-modal.active, .idlm-dialog.active, .idlm-drawer.active, #idlm-import-modal.active');
        document.body.classList.toggle('idlm-modal-open', !!hasOverlay);
    }

    function dispatch(dialog, name, detail) {
        dialog.dispatchEvent(new CustomEvent(name, {
            bubbles: true,
            detail: detail || {}
        }));
    }

    function open(target, options) {
        var dialog = resolveDialog(target);
        options = options || {};
        if (!dialog) {
            return null;
        }
        if (activeDialog && activeDialog !== dialog) {
            close(activeDialog, {reason: 'replaced', restoreFocus: false});
        }

        hiddenOnClose.set(dialog, dialog.hasAttribute('hidden') || dialog.dataset.idlmUseHidden === 'true');
        returnFocus.set(dialog, resolveDialog(options.trigger) || document.activeElement);
        dialog.hidden = false;
        dialog.classList.add('active');
        dialog.setAttribute('aria-hidden', 'false');
        activeDialog = dialog;
        makeBackgroundInert(dialog);
        updateScrollLock();

        window.setTimeout(function () {
            var initialSelector = options.initialFocus || dialog.dataset.idlmInitialFocus;
            var initial = initialSelector ? dialog.querySelector(initialSelector) : null;
            var focusable = visibleFocusable(dialog);
            if (!initial || initial.disabled || initial.hidden) {
                initial = focusable[0] || dialog.querySelector('.idlm-dialog__panel, .idlm-modal-content') || dialog;
            }
            if (!initial.hasAttribute('tabindex') && initial === dialog) {
                initial.setAttribute('tabindex', '-1');
            }
            initial.focus({preventScroll: true});
            dispatch(dialog, 'idlm:dialogopen', {trigger: returnFocus.get(dialog)});
        }, 0);
        return dialog;
    }

    function close(target, options) {
        var dialog = resolveDialog(target) || activeDialog;
        options = options || {};
        if (!dialog || !dialog.classList.contains('active')) {
            return false;
        }

        var beforeClose = new CustomEvent('idlm:dialogbeforeclose', {
            bubbles: true,
            cancelable: true,
            detail: {reason: options.reason || 'programmatic'}
        });
        if (!dialog.dispatchEvent(beforeClose)) {
            return false;
        }

        var trigger = returnFocus.get(dialog);
        dialog.classList.remove('active');
        dialog.setAttribute('aria-hidden', 'true');
        if (hiddenOnClose.get(dialog)) {
            dialog.hidden = true;
        }
        if (dialog === activeDialog) {
            activeDialog = null;
        }
        restoreBackground();
        updateScrollLock();
        dispatch(dialog, 'idlm:dialogclose', {reason: options.reason || 'programmatic'});

        if (options.restoreFocus !== false && trigger && document.contains(trigger) && !trigger.disabled) {
            window.setTimeout(function () {
                trigger.focus({preventScroll: true});
            }, 0);
        }
        return true;
    }

    function setFieldError(control, message) {
        var field = resolveDialog(control);
        if (!field) {
            return false;
        }
        var errorId = field.getAttribute('aria-errormessage');
        var error = errorId ? document.getElementById(errorId) : null;
        field.setAttribute('aria-invalid', 'true');
        if (error) {
            error.textContent = message;
            error.hidden = false;
            var describedBy = (field.getAttribute('aria-describedby') || '').split(/\s+/).filter(Boolean);
            if (describedBy.indexOf(errorId) === -1) {
                describedBy.push(errorId);
                field.setAttribute('aria-describedby', describedBy.join(' '));
                field.dataset.idlmErrorDescription = errorId;
            }
        }
        field.focus({preventScroll: true});
        return false;
    }

    function clearFieldError(control) {
        var field = resolveDialog(control);
        if (!field) {
            return;
        }
        var errorId = field.getAttribute('aria-errormessage');
        var error = errorId ? document.getElementById(errorId) : null;
        field.removeAttribute('aria-invalid');
        if (field.dataset.idlmErrorDescription) {
            var describedBy = (field.getAttribute('aria-describedby') || '').split(/\s+/).filter(Boolean);
            describedBy = describedBy.filter(function(id) { return id !== field.dataset.idlmErrorDescription; });
            if (describedBy.length) {
                field.setAttribute('aria-describedby', describedBy.join(' '));
            } else {
                field.removeAttribute('aria-describedby');
            }
            delete field.dataset.idlmErrorDescription;
        }
        if (error) {
            error.textContent = '';
            error.hidden = true;
        }
    }

    function ensureActionDialog() {
        var dialog = document.getElementById('idlm-action-dialog');
        if (dialog) {
            return dialog;
        }

        dialog = document.createElement('div');
        dialog.id = 'idlm-action-dialog';
        dialog.className = 'idlm-modal idlm-dialog';
        dialog.setAttribute('role', 'dialog');
        dialog.setAttribute('aria-modal', 'true');
        dialog.setAttribute('aria-hidden', 'true');
        dialog.setAttribute('aria-labelledby', 'idlm-action-dialog-title');
        dialog.setAttribute('aria-describedby', 'idlm-action-dialog-description');
        dialog.setAttribute('data-idlm-dialog', '');
        dialog.dataset.idlmUseHidden = 'true';
        dialog.hidden = true;
        dialog.innerHTML = '' +
            '<div class="idlm-modal-content idlm-dialog__panel">' +
                '<div class="idlm-modal-header idlm-dialog__header">' +
                    '<div><h2 class="idlm-dialog__title" id="idlm-action-dialog-title"></h2><p class="idlm-dialog__description" id="idlm-action-dialog-description"></p></div>' +
                    '<button type="button" class="idlm-dialog__close" data-idlm-dialog-close aria-label="Close dialog"><span class="dashicons dashicons-no-alt" aria-hidden="true"></span></button>' +
                '</div>' +
                '<div class="idlm-dialog__body">' +
                    '<label class="idlm-dialog__field" for="idlm-action-dialog-input" hidden></label>' +
                    '<textarea class="idlm-input" id="idlm-action-dialog-input" rows="4" hidden></textarea>' +
                    '<input class="idlm-input" id="idlm-action-dialog-datetime" type="datetime-local" hidden>' +
                    '<p class="idlm-dialog__error" role="alert" hidden></p>' +
                    '<div class="idlm-dialog__actions"><button type="button" class="idlm-btn idlm-btn-secondary" data-idlm-action-cancel>Cancel</button><button type="button" class="idlm-btn idlm-btn-primary" data-idlm-action-confirm>Continue</button></div>' +
                '</div>' +
            '</div>';
        document.body.appendChild(dialog);
        // A confirmation has no form body, so its actions belong in the shared dialog footer.
        var actions = dialog.querySelector('.idlm-dialog__actions');
        actions.classList.add('idlm-dialog__footer');
        dialog.querySelector('.idlm-dialog__panel').appendChild(actions);
        return dialog;
    }

    function actionDialog(options, promptMode) {
        options = options || {};
        var dialog = ensureActionDialog();
        var title = dialog.querySelector('#idlm-action-dialog-title');
        var description = dialog.querySelector('#idlm-action-dialog-description');
        var field = dialog.querySelector('.idlm-dialog__field');
        var input = dialog.querySelector('#idlm-action-dialog-input');
        var datetimeInput = dialog.querySelector('#idlm-action-dialog-datetime');
        var error = dialog.querySelector('.idlm-dialog__error');
        var confirmButton = dialog.querySelector('[data-idlm-action-confirm]');
        var cancelButton = dialog.querySelector('[data-idlm-action-cancel]');

        title.textContent = options.title || (promptMode ? 'Provide details' : 'Confirm action');
        description.textContent = options.message || '';
        var useDatetimePicker = promptMode && options.inputType === 'datetime-local';
        var control = useDatetimePicker ? datetimeInput : input;
        field.hidden = !promptMode;
        input.hidden = !promptMode || useDatetimePicker;
        datetimeInput.hidden = !promptMode || !useDatetimePicker;
        input.value = useDatetimePicker ? '' : (options.value || '');
        datetimeInput.value = useDatetimePicker ? (options.value || '') : '';
        input.placeholder = useDatetimePicker ? '' : (options.placeholder || '');
        datetimeInput.min = useDatetimePicker && options.min ? options.min : '';
        datetimeInput.step = useDatetimePicker && options.step ? options.step : '60';
        input.required = !useDatetimePicker && !!options.required;
        datetimeInput.required = useDatetimePicker && !!options.required;
        field.htmlFor = control.id;
        field.textContent = options.label || 'Details';
        error.hidden = true;
        error.textContent = '';
        confirmButton.textContent = options.confirmLabel || (promptMode ? 'Continue' : 'Confirm');
        confirmButton.classList.toggle('idlm-btn-danger', !!options.destructive);
        dialog.querySelector('.idlm-dialog__body').hidden = !promptMode;
        dialog.classList.toggle('idlm-dialog--destructive', !!options.destructive);
        cancelButton.textContent = options.cancelLabel || 'Cancel';

        return new Promise(function(resolve) {
            var settled = false;
            function finish(value, reason) {
                if (settled) {
                    return;
                }
                settled = true;
                dialog.removeEventListener('idlm:dialogclose', onClose);
                confirmButton.removeEventListener('click', onConfirm);
                cancelButton.removeEventListener('click', onCancel);
                close(dialog, {reason: reason || 'action'});
                resolve(value);
            }
            function onClose() {
                if (!settled) {
                    settled = true;
                    dialog.removeEventListener('idlm:dialogclose', onClose);
                    confirmButton.removeEventListener('click', onConfirm);
                    cancelButton.removeEventListener('click', onCancel);
                    resolve(promptMode ? null : false);
                }
            }
            function onCancel() {
                finish(promptMode ? null : false, 'cancel');
            }
            function onConfirm() {
                var value = promptMode ? control.value.trim() : true;
                if (promptMode && options.required && !value) {
                    error.textContent = options.requiredMessage || 'Complete this field to continue.';
                    error.hidden = false;
                    control.setAttribute('aria-invalid', 'true');
                    control.focus({preventScroll: true});
                    return;
                }
                control.removeAttribute('aria-invalid');
                finish(value, 'confirm');
            }
            dialog.addEventListener('idlm:dialogclose', onClose);
            confirmButton.addEventListener('click', onConfirm);
            cancelButton.addEventListener('click', onCancel);
            // Enter on a destructive confirmation must not complete it by accident.
            var safeFocus = options.destructive ? '[data-idlm-action-cancel]' : '[data-idlm-action-confirm]';
            open(dialog, {trigger: options.trigger, initialFocus: promptMode ? '#' + control.id : safeFocus});
        });
    }

    function ensureToastStack() {
        if (!toastStack || !document.body.contains(toastStack)) {
            toastStack = document.createElement('div');
            toastStack.className = 'idlm-toast-stack';
            toastStack.setAttribute('aria-label', 'Notifications');
            document.body.appendChild(toastStack);
        }
        return toastStack;
    }

    /**
     * Stacked, readable feedback. Errors stay longer and can carry a retry action:
     * notify(message, 'error', {action: {label: 'Retry', onClick: fn}, duration: 8000}).
     */
    function stackedToast(message, type, options) {
        var stack = ensureToastStack();
        var kind = toastIcons[type] ? type : 'info';
        var duration = options.duration || ('error' === kind || options.action ? 10000 : ('warning' === kind ? 8000 : 5000));
        var existing = Array.prototype.filter.call(stack.children, function (item) {
            return item.dataset.idlmToastMessage === String(message);
        })[0];
        var toast = document.createElement('div');
        var timer = null;

        function dismiss() {
            window.clearTimeout(timer);
            toast.classList.remove('is-visible');
            window.setTimeout(function () { toast.remove(); }, 220);
        }
        function schedule() {
            window.clearTimeout(timer);
            timer = window.setTimeout(dismiss, duration);
        }

        if (existing) {
            existing.remove();
        }
        while (stack.children.length >= 5) {
            stack.firstElementChild.remove();
        }

        toast.className = 'idlm-toast ' + kind;
        toast.dataset.idlmToastMessage = String(message);
        toast.setAttribute('role', 'error' === kind ? 'alert' : 'status');
        toast.setAttribute('aria-live', 'error' === kind ? 'assertive' : 'polite');

        var icon = document.createElement('span');
        icon.className = 'dashicons ' + toastIcons[kind] + ' idlm-toast__icon';
        icon.setAttribute('aria-hidden', 'true');
        toast.appendChild(icon);

        var text = document.createElement('span');
        text.className = 'idlm-toast__message';
        text.textContent = message;
        toast.appendChild(text);

        if (options.action && options.action.label) {
            var action = document.createElement('button');
            action.type = 'button';
            action.className = 'idlm-toast__action';
            action.textContent = options.action.label;
            action.addEventListener('click', function () {
                dismiss();
                if ('function' === typeof options.action.onClick) {
                    options.action.onClick();
                }
            });
            toast.appendChild(action);
        }

        var close = document.createElement('button');
        close.type = 'button';
        close.className = 'idlm-toast__dismiss';
        close.setAttribute('aria-label', 'Dismiss notification');
        close.innerHTML = '<span class="dashicons dashicons-no-alt" aria-hidden="true"></span>';
        close.addEventListener('click', dismiss);
        toast.appendChild(close);

        toast.addEventListener('mouseenter', function () { window.clearTimeout(timer); });
        toast.addEventListener('mouseleave', schedule);
        toast.addEventListener('focusin', function () { window.clearTimeout(timer); });
        toast.addEventListener('focusout', schedule);

        stack.appendChild(toast);
        window.setTimeout(function () { toast.classList.add('is-visible'); }, 0);
        schedule();
    }

    function notify(message, type, options) {
        stackedToast(message, type, options || {});
        return;
        var toast = document.createElement('div');
        toast.className = 'idlm-toast ' + (type || 'info');
        toast.setAttribute('role', 'status');
        toast.setAttribute('aria-live', 'polite');
        toast.textContent = message;
        document.body.appendChild(toast);
        window.setTimeout(function() {
            toast.classList.add('is-visible');
        }, 0);
        window.setTimeout(function() {
            toast.classList.remove('is-visible');
            window.setTimeout(function() { toast.remove(); }, 220);
        }, 3000);
    }

    /*
     * Design layer: page notices become toasts, so none stays pinned above the page.
     * These stay where they are: anything with a link or control (it must stay usable),
     * anything inside a dialog or drawer, and anything marked data-idlm-notice-inline
     * (standing context about the record, or a problem with a form field).
     * The original stays hidden in the DOM because other scripts read it, such as Settings' saved state.
     */
    function noticeKind(notice) {
        if (notice.matches('.notice-error, .idlm-alert-danger, .idlm-alert-error')) { return 'error'; }
        if (notice.matches('.notice-warning, .idlm-alert-warning')) { return 'warning'; }
        if (notice.matches('.notice-success, .idlm-alert-success, .idlm-account-success')) { return 'success'; }
        return 'info';
    }

    function promoteNotice(notice) {
        if (notice.dataset.idlmNoticePromoted || notice.hidden
            || !notice.closest('.idlm-workspace, .idlm-wrap')
            || notice.closest('[data-idlm-notice-inline], [data-idlm-dialog], .idlm-dialog, .idlm-modal, .idlm-drawer, .idlm-toast-stack')
            || notice.querySelector('a, button:not(.notice-dismiss), input, select, textarea')) {
            return;
        }
        var copy = notice.cloneNode(true);
        copy.querySelectorAll('.notice-dismiss').forEach(function (button) { button.remove(); });
        var message = copy.textContent.replace(/\s+/g, ' ').trim();
        if (!message) {
            return;
        }
        var requested = parseInt(notice.getAttribute('data-idlm-toast-autoclose'), 10);
        notice.dataset.idlmNoticePromoted = '1';
        notice.style.setProperty('display', 'none', 'important');
        stackedToast(message, noticeKind(notice), requested > 0 ? {duration: requested} : {});
    }

    function promoteNotices(scope) {
        if (scope.matches && scope.matches('.notice, .idlm-alert')) {
            promoteNotice(scope);
        }
        scope.querySelectorAll('.notice, .idlm-alert').forEach(promoteNotice);
    }

    function watchNotices() {
        promoteNotices(document);
        new MutationObserver(function (mutations) {
            mutations.forEach(function (mutation) {
                mutation.addedNodes.forEach(function (node) {
                    if (1 === node.nodeType) {
                        promoteNotices(node);
                    }
                });
            });
        }).observe(document.body, {childList: true, subtree: true});
    }

    /* Date range filters: "Custom range" reveals its From and To fields (data-idlm-date-custom-for). */
    function syncDateRange(select) {
        if (!select || !select.id) { return; }
        document.querySelectorAll('[data-idlm-date-custom-for="' + select.id + '"]').forEach(function (fields) {
            fields.hidden = 'custom' !== select.value;
            fields.style.display = fields.hidden ? 'none' : '';
        });
    }
    document.addEventListener('change', function (event) {
        if (event.target.matches && event.target.matches('select[data-idlm-date-range]')) {
            syncDateRange(event.target);
            // Report forms apply a period at once; a custom range waits for its dates.
            if (event.target.matches('[data-idlm-date-submit]') && 'custom' !== event.target.value && event.target.form) { event.target.form.requestSubmit(); }
        }
    });
    function syncAllDateRanges() { document.querySelectorAll('select[data-idlm-date-range]').forEach(syncDateRange); }
    if ('loading' === document.readyState) { document.addEventListener('DOMContentLoaded', syncAllDateRanges); } else { syncAllDateRanges(); }

    if ('loading' === document.readyState) {
        document.addEventListener('DOMContentLoaded', watchNotices);
    } else {
        watchNotices();
    }

    document.addEventListener('click', function (event) {
        var closeButton = event.target.closest('[data-idlm-dialog-close]');
        if (closeButton) {
            var dialog = closeButton.closest('[data-idlm-dialog]');
            if (dialog) {
                event.preventDefault();
                close(dialog, {reason: 'button'});
            }
            return;
        }
        var confirmationSubmit = event.target.closest('[data-idlm-confirm-submit]');
        if (confirmationSubmit) {
            var formId = confirmationSubmit.getAttribute('form');
            var form = formId ? document.getElementById(formId) : confirmationSubmit.closest('form');
            if (!form) {
                return;
            }
            event.preventDefault();
            actionDialog({
                title: confirmationSubmit.dataset.idlmConfirmTitle || 'Confirm action',
                message: confirmationSubmit.dataset.idlmConfirmMessage || 'Continue with this action?',
                confirmLabel: confirmationSubmit.dataset.idlmConfirmLabel || 'Confirm',
                destructive: confirmationSubmit.dataset.idlmConfirmDestructive === 'true',
                trigger: confirmationSubmit
            }, false).then(function(confirmed) {
                if (confirmed) {
                    form.requestSubmit(confirmationSubmit);
                }
            });
            return;
        }
        if (activeDialog && event.target === activeDialog && activeDialog.dataset.idlmBackdropClose === 'true') {
            close(activeDialog, {reason: 'backdrop'});
        }
    });

    document.addEventListener('input', function (event) {
        if (event.target.matches('[aria-invalid="true"]')) {
            clearFieldError(event.target);
        }
    });

    document.addEventListener('change', function (event) {
        if (event.target.matches('[aria-invalid="true"]')) {
            clearFieldError(event.target);
        }
    });

    document.addEventListener('keydown', function (event) {
        if (!activeDialog) {
            return;
        }
        if (event.key === 'Escape') {
            event.preventDefault();
            event.stopImmediatePropagation();
            close(activeDialog, {reason: 'escape'});
            return;
        }
        if (event.key !== 'Tab') {
            return;
        }
        var focusable = visibleFocusable(activeDialog);
        if (!focusable.length) {
            event.preventDefault();
            activeDialog.focus();
            return;
        }
        var first = focusable[0];
        var last = focusable[focusable.length - 1];
        if (event.shiftKey && (document.activeElement === first || !activeDialog.contains(document.activeElement))) {
            event.preventDefault();
            last.focus();
        } else if (!event.shiftKey && (document.activeElement === last || !activeDialog.contains(document.activeElement))) {
            event.preventDefault();
            first.focus();
        }
    }, true);

    window.IDLMDialog = {
        syncDateRange: syncDateRange,
        open: open,
        close: close,
        setFieldError: setFieldError,
        clearFieldError: clearFieldError,
        confirm: function(options) { return actionDialog(options, false); },
        prompt: function(options) { return actionDialog(options, true); },
        notify: notify,
        getActive: function () { return activeDialog; }
    };

    // Display-only formats shared by the design layer. Nothing here changes a stored or submitted value.
    var months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
    function moneyLocale(symbol) {
        return /^(₹|INR|Rs\.?)$/i.test(String(symbol || '').trim()) ? 'en-IN' : 'en-US';
    }
    window.IDLMFormat = {
        // Indian lakh grouping for rupees, thousands grouping for every other currency.
        number: function (value, symbol, options) {
            var settings = options || {};
            var amount = Number(value);
            if (!isFinite(amount)) { amount = 0; }
            try {
                return amount.toLocaleString(moneyLocale(symbol), {
                    minimumFractionDigits: settings.min || 0,
                    maximumFractionDigits: undefined === settings.max ? 0 : settings.max
                });
            } catch (error) {
                return String(amount);
            }
        },
        money: function (value, symbol, options) {
            return (symbol || '') + window.IDLMFormat.number(value, symbol, options);
        },
        // Stored site-time values such as 2026-09-09 20:21:00 read as Sep 9, 2026, 8:21 PM.
        dateTime: function (value) {
            var match = /^(\d{4})-(\d{2})-(\d{2})(?:[ T](\d{2}):(\d{2}))?/.exec(String(value || ''));
            if (!match || Number(match[2]) < 1 || Number(match[2]) > 12) { return String(value || ''); }
            var text = months[Number(match[2]) - 1] + ' ' + Number(match[3]) + ', ' + match[1];
            if (match[4]) {
                var hour = Number(match[4]);
                text += ', ' + ((hour % 12) || 12) + ':' + match[5] + ' ' + (hour < 12 ? 'AM' : 'PM');
            }
            return text;
        }
    };
}());
