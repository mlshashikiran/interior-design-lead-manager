/**
 * Design board moves. The Move button works without this file, and the server
 * decides every move. A refused move leaves the card and offers its checklist.
 *
 * Builder: Shashi
 */
(function () {
    'use strict';
    var board = document.querySelector('.idlm-design-board');
    if (!board) { return; }
    var scrollKey = 'idlmDesignBoardScroll';

    function remember(value) {
        try { window.sessionStorage.setItem(scrollKey, String(value)); } catch (error) { /* Storage can be unavailable; the board then starts at the left. */ }
    }
    try {
        var saved = window.sessionStorage.getItem(scrollKey);
        if (saved) { board.scrollLeft = Number(saved) || 0; window.sessionStorage.removeItem(scrollKey); }
    } catch (error) { /* See above. */ }

    function showRefusal(card, message, code) {
        if ('idlm_design_stage_checklist' !== code || !window.IDLMDialog.confirm) {
            window.IDLMDialog.notify(message, 'error');
            return;
        }
        window.IDLMDialog.confirm({title: 'Not ready to move', message: message, confirmLabel: 'Open checklist', cancelLabel: 'Stay here', trigger: card.querySelector('button, a')}).then(function (open) {
            if (open) { window.location.href = card.dataset.url; }
        });
    }

    function move(card) {
        var button = card.querySelector('.idlm-design-move button');
        if (!button || button.disabled) { return; }
        button.disabled = true;
        var body = new FormData();
        body.set('action', 'idlm_design_stage_move');
        body.set('nonce', board.dataset.nonce);
        body.set('handoff_id', card.dataset.handoff);
        body.set('from_stage', card.dataset.stage);
        fetch(board.dataset.ajaxUrl, {method: 'POST', body: body, credentials: 'same-origin'})
            .then(function (response) { return response.json(); })
            .then(function (payload) {
                if (payload.success) {
                    remember(board.scrollLeft);
                    window.location.reload();
                    return;
                }
                button.disabled = false;
                showRefusal(card, payload.data && payload.data.message ? payload.data.message : 'The project was not moved.', payload.data && payload.data.code);
            })
            .catch(function () {
                button.disabled = false;
                window.IDLMDialog.notify('The project was not moved. Check your connection and try again.', 'error');
            });
    }

    board.addEventListener('submit', function (event) {
        var card = event.target.closest('.idlm-design-card-item');
        if (!card) { return; }
        event.preventDefault();
        move(card);
    });

    // Dragging is a shortcut for the same move: one stage forward only.
    var dragged = null;
    // The next column a project can enter: removed stages only show while emptying.
    function nextColumn(card) {
        var column = card.closest('.idlm-design-column');
        var next = column ? column.nextElementSibling : null;
        while (next && next.dataset.archived) { next = next.nextElementSibling; }
        return next;
    }
    board.addEventListener('dragstart', function (event) {
        var card = event.target.closest && event.target.closest('.idlm-design-card-item');
        if (!card || !card.draggable) { return; }
        dragged = card;
        card.classList.add('is-dragging');
        event.dataTransfer.effectAllowed = 'move';
        event.dataTransfer.setData('text/plain', card.dataset.handoff);
    });
    board.addEventListener('dragend', function () {
        if (dragged) { dragged.classList.remove('is-dragging'); }
        dragged = null;
        board.querySelectorAll('.is-drop-target').forEach(function (column) { column.classList.remove('is-drop-target'); });
    });
    board.addEventListener('dragover', function (event) {
        var column = event.target.closest('.idlm-design-column');
        if (dragged && column && column === nextColumn(dragged)) {
            event.preventDefault();
            column.classList.add('is-drop-target');
        }
    });
    board.addEventListener('dragleave', function (event) {
        var column = event.target.closest('.idlm-design-column');
        if (column && !column.contains(event.relatedTarget)) { column.classList.remove('is-drop-target'); }
    });
    board.addEventListener('drop', function (event) {
        var column = event.target.closest('.idlm-design-column');
        if (!dragged || !column || column !== nextColumn(dragged)) { return; }
        event.preventDefault();
        column.classList.remove('is-drop-target');
        move(dragged);
    });
})();
