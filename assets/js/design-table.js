/**
 * Design projects table: save a designer and target date from the row.
 * Every row is a working form without this file; the server decides every change.
 *
 * Builder: Shashi
 */
(function () {
    'use strict';
    var table = document.querySelector('.idlm-design-projects[data-ajax-url]');
    if (!table || !window.IDLMDialog) { return; }

    function controls(row) {
        return {
            designer: row.querySelector('select[name="designer_id"]'),
            target: row.querySelector('input[name="target_date"]'),
            form: row.querySelector('form')
        };
    }

    function setBusy(row, busy) {
        table.querySelectorAll('[form="' + 'idlm-design-row-' + row.dataset.handoff + '"], tr[data-handoff="' + row.dataset.handoff + '"] button').forEach(function (control) { control.disabled = busy; });
        row.setAttribute('aria-busy', busy ? 'true' : 'false');
    }

    function revert(row) {
        var c = controls(row);
        if (c.designer) { c.designer.value = '0' === row.dataset.designer ? '' : row.dataset.designer; }
        if (c.target) { c.target.value = row.dataset.target || ''; }
    }

    // FormData before disabling the row, since disabled controls are left out.
    function send(row, operation, extra) {
        var c = controls(row);
        var body = c.form ? new FormData(c.form) : new FormData();
        body.set('action', 'idlm_design_handoff_decide');
        body.set('operation', operation);
        body.set('handoff_id', row.dataset.handoff);
        Object.keys(extra || {}).forEach(function (key) { body.set(key, extra[key]); });
        setBusy(row, true);
        return fetch(table.dataset.ajaxUrl, {method: 'POST', body: body, credentials: 'same-origin'})
            .then(function (response) { return response.json(); })
            .catch(function () { return {success: false, data: {message: 'The change was not saved. Check your connection and try again.'}}; })
            .then(function (payload) {
                setBusy(row, false);
                if (!payload || !payload.success) {
                    window.IDLMDialog.notify(payload && payload.data && payload.data.message ? payload.data.message : 'The change was not saved.', 'error');
                    return null;
                }
                return payload.data;
            });
    }

    function assign(row, reason) {
        send(row, 'assign', {reason: reason || ''}).then(function (data) {
            if (!data) { revert(row); return; }
            row.dataset.designer = String(data.designer);
            row.dataset.target = data.target || '';
            window.IDLMDialog.notify(data.message, 'success');
        });
    }

    // A first assignment saves at once; a change of designer asks why first.
    function save(row, trigger) {
        var c = controls(row);
        var designer = c.designer.value; var target = c.target.value;
        if (designer === row.dataset.designer && target === (row.dataset.target || '')) { return; }
        if (!designer) { if (trigger === c.target) { return; } window.IDLMDialog.notify('Choose a designer to save this row.', 'info'); c.designer.focus(); return; }
        if (!target) { window.IDLMDialog.notify('Choose a target date to save the designer.', 'info'); c.target.focus(); return; }
        if ('0' === row.dataset.designer || designer === row.dataset.designer) { assign(row, ''); return; }
        window.IDLMDialog.prompt({
            title: 'Change the designer',
            message: 'The project moves from ' + (c.designer.querySelector('option[value="' + row.dataset.designer + '"]') || {textContent: 'the current designer'}).textContent + ' to ' + c.designer.options[c.designer.selectedIndex].textContent + '.',
            label: 'Reason for the change',
            required: true,
            requiredMessage: 'Give a reason for changing the designer.',
            confirmLabel: 'Change designer',
            trigger: trigger
        }).then(function (reason) {
            if (null === reason) { revert(row); return; }
            assign(row, reason);
        });
    }

    // Rows save as they change, so the no-script Save button goes (button styles override hidden).
    table.querySelectorAll('tr[data-status="accepted"] .idlm-design-row-save').forEach(function (button) { button.remove(); });

    table.addEventListener('change', function (event) {
        var row = event.target.closest('tr[data-status="accepted"]');
        if (row && event.target.matches('.idlm-design-inline')) { save(row, event.target); }
    });

    table.addEventListener('submit', function (event) {
        var row = event.target.closest('tr');
        if (!row) { return; }
        event.preventDefault();
        if ('accepted' === row.dataset.status) { save(row, event.submitter || null); return; }
        send(row, 'accept').then(function (data) { if (data) { window.location.reload(); } });
    });

    table.addEventListener('click', function (event) {
        var link = event.target.closest('[data-design-send-back]');
        if (!link) { return; }
        event.preventDefault();
        var row = link.closest('tr');
        if ('true' === row.getAttribute('aria-busy')) { return; }
        window.IDLMDialog.prompt({
            title: 'Send back to sales',
            message: row.dataset.name + ' goes back to sales to fix and resend.',
            label: 'What needs fixing?',
            required: true,
            requiredMessage: 'A reason is required to return a handoff.',
            confirmLabel: 'Send back',
            trigger: link
        }).then(function (reason) {
            if (null === reason) { return; }
            send(row, 'send_back', {reason: reason, _wpnonce: link.dataset.nonce}).then(function (data) { if (data) { window.location.reload(); } });
        });
    });
})();
