/**
 * Shared accessible dialog controller for IDLM admin and frontend surfaces.
 *
 * Builder: Shashi
 */
(function () {
    'use strict';

    var activeDialog = null;
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
            open(dialog, {trigger: options.trigger, initialFocus: promptMode ? '#' + control.id : '[data-idlm-action-confirm]'});
        });
    }

    function notify(message, type) {
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
        open: open,
        close: close,
        setFieldError: setFieldError,
        clearFieldError: clearFieldError,
        confirm: function(options) { return actionDialog(options, false); },
        prompt: function(options) { return actionDialog(options, true); },
        notify: notify,
        getActive: function () { return activeDialog; }
    };
}());
