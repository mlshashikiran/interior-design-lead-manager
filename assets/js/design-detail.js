/**
 * Project page: secondary actions open their form in the shared dialog.
 * Each form posts normally; the server checks every field again.
 *
 * Builder: Shashi
 */
(function () {
    'use strict';
    if (!window.IDLMDialog) { return; }
    document.addEventListener('click', function (event) {
        var button = event.target.closest('[data-design-dialog]');
        if (!button) { return; }
        var dialog = document.getElementById(button.getAttribute('data-design-dialog'));
        if (!dialog) { return; }
        event.preventDefault();
        window.IDLMDialog.open(dialog, {trigger: button});
    });
    // One submit per dialog: a second click while the page is posting does nothing.
    document.addEventListener('submit', function (event) {
        var form = event.target.closest('.idlm-design-dialog-form');
        if (!form) { return; }
        if (form.dataset.submitting) { event.preventDefault(); return; }
        form.dataset.submitting = '1';
        var submit = form.querySelector('.idlm-dialog__footer .idlm-btn-primary');
        if (submit) { submit.setAttribute('aria-disabled', 'true'); }
    });
    // Coming back to this page from history leaves its forms usable again.
    window.addEventListener('pageshow', function () {
        document.querySelectorAll('.idlm-design-dialog-form[data-submitting]').forEach(function (form) {
            delete form.dataset.submitting;
            var submit = form.querySelector('.idlm-dialog__footer .idlm-btn-primary');
            if (submit) { submit.removeAttribute('aria-disabled'); }
        });
    });
})();
