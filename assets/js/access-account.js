(function () {
    'use strict';

    function setPasswordVisibility(button) {
        var input = document.getElementById(button.getAttribute('aria-controls'));
        var label = button.querySelector('[data-idlm-password-toggle-label]');
        var icon = button.querySelector('.dashicons');
        var showing;

        if (!input) {
            return;
        }

        showing = input.type === 'text';
        input.type = showing ? 'password' : 'text';
        button.setAttribute('aria-pressed', showing ? 'false' : 'true');
        button.setAttribute('aria-label', (showing ? 'Show ' : 'Hide ') + (input.id.indexOf('confirm') !== -1 ? 'password confirmation' : 'password'));
        if (label) {
            label.textContent = showing ? 'Show' : 'Hide';
        }
        if (icon) {
            icon.classList.toggle('dashicons-visibility', showing);
            icon.classList.toggle('dashicons-hidden', !showing);
        }
    }

    function initializePasswordForm(form) {
        var password = form.querySelector('[data-idlm-password-primary]');
        var confirmation = form.querySelector('[data-idlm-password-confirm]');
        var error = form.querySelector('[data-idlm-password-error]');
        var submit = form.querySelector('button[type="submit"]');

        function validateMatch() {
            var mismatch;
            if (!password || !confirmation || !error) {
                return true;
            }
            mismatch = Boolean(password.value || confirmation.value) && password.value !== confirmation.value;
            confirmation.setCustomValidity(mismatch ? 'The passwords do not match.' : '');
            confirmation.setAttribute('aria-invalid', mismatch ? 'true' : 'false');
            error.hidden = !mismatch;
            return !mismatch;
        }

        if (password && confirmation) {
            password.addEventListener('input', validateMatch);
            confirmation.addEventListener('input', validateMatch);
            confirmation.addEventListener('blur', validateMatch);
        }

        form.addEventListener('submit', function (event) {
            if (!validateMatch()) {
                event.preventDefault();
                confirmation.focus();
                return;
            }
            form.setAttribute('aria-busy', 'true');
            if (submit) {
                submit.setAttribute('data-idlm-original-label', submit.textContent);
                submit.disabled = true;
                submit.textContent = form.getAttribute('data-idlm-pending-label') || 'Submitting…';
            }
        });
    }

    document.addEventListener('DOMContentLoaded', function () {
        var notice = document.querySelector('[data-idlm-notice]');

        document.querySelectorAll('[data-idlm-password-toggle]').forEach(function (button) {
            button.addEventListener('click', function () {
                setPasswordVisibility(button);
            });
        });

        document.querySelectorAll('form[data-idlm-pending-label], form[data-idlm-password-form]').forEach(initializePasswordForm);

        if (notice) {
            try {
                notice.focus({preventScroll: false});
            } catch (error) {
                notice.focus();
            }
        }
    });

    window.addEventListener('pageshow', function () {
        document.querySelectorAll('form[aria-busy="true"]').forEach(function (form) {
            var submit = form.querySelector('button[type="submit"]');
            form.removeAttribute('aria-busy');
            if (submit) {
                submit.disabled = false;
                submit.textContent = submit.getAttribute('data-idlm-original-label') || submit.textContent;
                submit.removeAttribute('data-idlm-original-label');
            }
        });
    });
}());
