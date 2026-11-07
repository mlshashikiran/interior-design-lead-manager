/**
 * Simple payment page (design layer). Dialogs open through design-detail.js;
 * this file only shows the fields a dialog needs. The server checks everything again.
 *
 * Builder: Shashi
 */
(function () {
    'use strict';
    function normalize(value) { return String(value || '').toLowerCase().replace(/\s+/g, ''); }

    // Repeated reference: ask why only when this method and reference match an earlier payment.
    function checkDuplicate(form) {
        var list;
        try { list = JSON.parse(form.getAttribute('data-payment-references') || '[]'); } catch (e) { list = []; }
        var method = form.querySelector('[name="method"]');
        var reference = form.querySelector('[name="reference"]');
        var group = form.querySelector('[data-payment-duplicate]');
        if (!method || !reference || !group) { return; }
        var ref = normalize(reference.value);
        var match = ref !== '' && list.indexOf(method.value + '|' + ref) !== -1;
        var field = group.querySelector('textarea');
        group.hidden = !match;
        field.disabled = !match;
        field.required = match;
    }

    // Plan dialog: only the chosen quotation's advance milestones post.
    function showPlan(form) {
        var select = form.querySelector('[name="payment_revision_id"]');
        if (!select) { return; }
        form.querySelectorAll('fieldset[data-revision]').forEach(function (set) {
            var on = set.getAttribute('data-revision') === select.value;
            set.hidden = !on;
            set.disabled = !on;
        });
    }

    document.addEventListener('input', function (event) {
        var form = event.target.closest('form[data-payment-references]');
        if (form) { checkDuplicate(form); }
    });
    document.addEventListener('change', function (event) {
        var form = event.target.closest('form[data-payment-references]');
        if (form) { checkDuplicate(form); }
        var plan = event.target.closest('form[data-payment-plan]');
        if (plan && event.target.name === 'payment_revision_id') { showPlan(plan); }
    });
    document.addEventListener('click', function (event) {
        var add = event.target.closest('[data-payment-add-row]');
        if (!add) { return; }
        event.preventDefault();
        var next = add.closest('form').querySelector('[data-payment-legacy-row][hidden]');
        if (!next) { return; }
        next.hidden = false;
        next.querySelectorAll('input').forEach(function (input) { input.disabled = false; });
        next.querySelector('input').focus();
        if (!add.closest('form').querySelector('[data-payment-legacy-row][hidden]')) { add.hidden = true; }
    });
    document.querySelectorAll('form[data-payment-plan]').forEach(showPlan);
})();
