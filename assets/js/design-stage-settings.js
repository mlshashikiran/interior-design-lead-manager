/**
 * Design stages screen: move stages up and down and add rows. Without this file the
 * Order numbers and the blank rows do the same job; the server checks everything.
 *
 * Builder: Shashi
 */
(function () {
    'use strict';
    var form = document.querySelector('[data-design-stage-settings]');
    if (!form) { return; }
    var list = form.querySelector('[data-stage-list]');
    var next = 1000;

    function renumber() {
        list.querySelectorAll('[data-stage-row]').forEach(function (row, index) {
            var position = row.querySelector('[data-stage-position]');
            if (position) { position.value = String(index + 1); }
        });
    }

    function button(label, attribute, aria) {
        var element = document.createElement('button');
        element.type = 'button';
        element.className = 'idlm-btn idlm-btn-secondary';
        element.setAttribute(attribute, '');
        element.textContent = label;
        if (aria) { element.setAttribute('aria-label', aria); }
        return element;
    }

    // Arrows are added here, so a page without scripts never shows controls that do nothing.
    function enhance(row) {
        var slot = row.querySelector('[data-stage-move]');
        if (slot && !slot.childElementCount) {
            var name = row.querySelector('input[name$="[label]"]');
            var label = name && name.value ? name.value : 'new stage';
            slot.appendChild(button('↑', 'data-stage-up', 'Move ' + label + ' up'));
            slot.appendChild(button('↓', 'data-stage-down', 'Move ' + label + ' down'));
        }
        // Order is set by the arrows, so the number box would only repeat it.
        var position = row.querySelector('.idlm-design-stage-position');
        if (position) { position.classList.add('is-script-ordered'); }
    }

    function add(template, container, focusSelector) {
        var holder = document.createElement('div');
        holder.innerHTML = template.innerHTML.replace(/__i__/g, String(next++));
        var row = holder.firstElementChild;
        container.appendChild(row);
        if (row.hasAttribute('data-stage-row')) { enhance(row); }
        renumber();
        var field = row.querySelector(focusSelector);
        if (field) { field.focus(); }
    }

    list.querySelectorAll('[data-stage-row]').forEach(enhance);
    form.querySelector('[data-add-stage-slot]').appendChild(button('Add another stage', 'data-add-stage'));
    form.querySelector('[data-add-document-slot]').appendChild(button('Add another document', 'data-add-document'));

    form.addEventListener('click', function (event) {
        var control = event.target.closest('button');
        if (!control) { return; }
        var row = control.closest('[data-stage-row]');
        if (control.hasAttribute('data-stage-up') && row && row.previousElementSibling) {
            list.insertBefore(row, row.previousElementSibling); renumber(); control.focus(); return;
        }
        if (control.hasAttribute('data-stage-down') && row && row.nextElementSibling) {
            list.insertBefore(row.nextElementSibling, row); renumber(); control.focus(); return;
        }
        if (control.hasAttribute('data-add-stage')) {
            add(form.querySelector('[data-stage-template]'), list, 'input[name$="[label]"]');
            return;
        }
        if (control.hasAttribute('data-add-document')) {
            add(form.querySelector('[data-document-template]'), form.querySelector('[data-document-list]'), 'input[name$="[label]"]');
        }
    });
})();
