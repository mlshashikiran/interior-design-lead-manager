/**
 * Project quick panel, like the lead popup. Links still open the full project page
 * without this file, or with a modified click (new tab, new window).
 * The address carries ?project=ID while the panel is open, so a refresh reopens it.
 *
 * Builder: Shashi
 */
(function () {
    'use strict';
    var dialog = document.getElementById('idlm-design-project-panel');
    if (!dialog || !window.IDLMDialog || !window.fetch) { return; }
    var content = dialog.querySelector('[data-design-panel-content]');
    var request = 0;

    function setAddress(id) {
        try {
            var url = new URL(window.location.href);
            if (id) { url.searchParams.set('project', id); } else { url.searchParams.delete('project'); }
            window.history.replaceState(window.history.state, '', url.toString());
        } catch (error) { /* The panel still works; only the address is not updated. */ }
    }

    // Loading and error states keep the panel's title and close button, built as text.
    function message(text, loading) {
        content.textContent = '';
        var header = document.createElement('header');
        header.className = 'idlm-drawer-header';
        var heading = document.createElement('h2');
        heading.id = dialog.id + '-title';
        heading.className = 'idlm-design-panel-loading-title';
        heading.textContent = 'Project';
        var close = document.createElement('button');
        close.type = 'button';
        close.className = 'idlm-close';
        close.setAttribute('data-idlm-dialog-close', '');
        close.setAttribute('aria-label', 'Close project');
        close.textContent = '\u00d7';
        header.appendChild(heading);
        header.appendChild(close);
        var body = document.createElement('div');
        body.className = 'idlm-drawer-body';
        var line = document.createElement(loading ? 'div' : 'p');
        line.className = loading ? 'idlm-popup-load-state' : 'idlm-design-note';
        line.setAttribute('role', 'status');
        if (loading) {
            var spinner = document.createElement('span');
            spinner.className = 'spinner is-active idlm-spinner-inline';
            spinner.setAttribute('aria-hidden', 'true');
            var label = document.createElement('span');
            label.textContent = text;
            line.appendChild(spinner);
            line.appendChild(label);
        } else {
            line.textContent = text;
        }
        body.appendChild(line);
        content.appendChild(header);
        content.appendChild(body);
    }

    function post(fields) {
        var data = new FormData();
        Object.keys(fields).forEach(function (key) { data.set(key, fields[key]); });
        return fetch(dialog.dataset.ajaxUrl, {method: 'POST', body: data, credentials: 'same-origin'})
            .then(function (response) { return response.json(); });
    }

    function open(id, trigger) {
        var current = ++request;
        message('Loading project…', true);
        window.IDLMDialog.open(dialog, {trigger: trigger, initialFocus: '[data-design-panel-content]'});
        setAddress(id);
        post({action: 'idlm_design_project_panel', nonce: dialog.dataset.nonce, handoff_id: id})
            .then(function (payload) {
                if (current !== request) { return; }
                if (!payload || !payload.success) { message(payload && payload.data && payload.data.message ? payload.data.message : 'This project could not be loaded.'); return; }
                // Server-rendered and escaped there, from a row this person may see.
                content.innerHTML = payload.data.html;
            })
            .catch(function () { if (current === request) { message('This project could not be loaded. Check your connection and try again.'); } });
    }

    dialog.addEventListener('idlm:dialogclose', function () { request++; setAddress(''); });

    document.addEventListener('click', function (event) {
        var link = event.target.closest('[data-design-panel]');
        if (!link || event.defaultPrevented || 0 !== event.button || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) { return; }
        event.preventDefault();
        open(link.dataset.designPanel, link);
    });

    // The board's own move rule: one stage forward, refused with the reason when not ready.
    content.addEventListener('click', function (event) {
        var button = event.target.closest('[data-design-panel-move]');
        if (!button || button.disabled) { return; }
        button.disabled = true;
        post({action: 'idlm_design_stage_move', nonce: dialog.dataset.moveNonce, handoff_id: button.dataset.handoff, from_stage: button.dataset.stage})
            .then(function (payload) {
                if (payload && payload.success) { window.location.reload(); return; }
                button.disabled = false;
                window.IDLMDialog.notify(payload && payload.data && payload.data.message ? payload.data.message : 'The project was not moved.', 'error');
            })
            .catch(function () {
                button.disabled = false;
                window.IDLMDialog.notify('The project was not moved. Check your connection and try again.', 'error');
            });
    });

    var start = new URL(window.location.href).searchParams.get('project');
    if (start && /^\d+$/.test(start)) { open(start, null); }
})();
