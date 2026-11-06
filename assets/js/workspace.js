(function () {
    'use strict';

    var storageKey = 'idlmWorkspaceSidebarCollapsed';

    try {
        if (window.localStorage.getItem(storageKey) === '1') {
            document.documentElement.classList.add('idlm-workspace-sidebar-precollapsed');
        }
    } catch (error) {
        // Storage can be unavailable in privacy-restricted browser contexts.
    }

    document.addEventListener('DOMContentLoaded', function () {
        var workspaces = document.querySelectorAll('[data-idlm-workspace]');
        if (workspaces.length) {
            document.documentElement.classList.add('idlm-workspace-page');
        }

        workspaces.forEach(function (workspace) {
            var sidebar = workspace.querySelector('.idlm-workspace-sidebar');
            var desktopToggle = workspace.querySelector('.idlm-workspace-toggle');
            var mobileToggle = workspace.querySelector('.idlm-workspace-mobile-toggle');
            var backdrop = workspace.querySelector('[data-idlm-workspace-backdrop]');
            var accountTrigger = workspace.querySelector('.idlm-workspace-account-trigger');
            var accountMenu = workspace.querySelector('.idlm-workspace-account-menu');
            var compactQuery = window.matchMedia('(max-width: 782px)');
            var collapsed = false;
            var mobileOpen = false;
            var designLayer = document.body.classList.contains('idlm-crm-design');
            var content = workspace.querySelector('.idlm-workspace-content');
            var topbar = workspace.querySelector('.idlm-workspace-topbar');
            var drawerClose = workspace.querySelector('.idlm-workspace-drawer-close');
            var navItems = workspace.querySelectorAll('.idlm-workspace-nav-item');

            if (!sidebar || !desktopToggle || !mobileToggle || !backdrop) {
                return;
            }

            try {
                collapsed = window.localStorage.getItem(storageKey) === '1';
            } catch (error) {}

            function render() {
                var mobile = compactQuery.matches;
                workspace.classList.toggle('is-collapsed', !mobile && collapsed);
                workspace.classList.toggle('is-mobile-open', mobile && mobileOpen);
                backdrop.hidden = !(mobile && mobileOpen);

                desktopToggle.setAttribute('aria-expanded', collapsed ? 'false' : 'true');
                desktopToggle.setAttribute('aria-label', collapsed ? 'Expand navigation' : 'Collapse navigation');
                mobileToggle.setAttribute('aria-expanded', mobileOpen ? 'true' : 'false');
                mobileToggle.setAttribute('aria-label', mobileOpen ? 'Close navigation' : 'Open navigation');
                mobileToggle.querySelector('.dashicons').className = 'dashicons ' + (mobileOpen ? 'dashicons-no-alt' : 'dashicons-menu');
                document.documentElement.classList.remove('idlm-workspace-sidebar-precollapsed');
                positionAccountMenu();
                if (designLayer) {
                    renderDesignShell(mobile);
                }
            }

            // Design layer only: the phone drawer behaves as a modal, and the icon rail keeps its names.
            function renderDesignShell(mobile) {
                var drawerOpen = mobile && mobileOpen;
                if (content) {
                    content.inert = drawerOpen;
                }
                if (topbar) {
                    topbar.inert = drawerOpen;
                }
                sidebar.inert = mobile && !mobileOpen;
                document.body.classList.toggle('idlm-modal-open', drawerOpen || !!document.querySelector('.idlm-modal.active, .idlm-dialog.active, .idlm-drawer.active'));
                // The rail's names show in a styled tooltip (below), not the slow native title.
                navItems.forEach(function (item) {
                    if (!item.classList.contains('idlm-floating-tasks-btn')) {
                        item.removeAttribute('title');
                    }
                });
                if (mobile || !collapsed) { hideTip(); }
                placeTodayEntry(mobile);
            }

            // Collapsed rail tooltip: shown on hover and on keyboard focus, with the item's count.
            var tip = null, tipOwner = null;
            function tipText(item) {
                if (item.classList.contains('idlm-workspace-today')) {
                    var badge = item.querySelector('.idlm-floating-tasks-badge');
                    return 'Today’s work' + (badge && badge.textContent.trim() ? ' · ' + badge.textContent.trim() : '');
                }
                var label = item.querySelector('.idlm-workspace-nav-label');
                return item.getAttribute('data-idlm-tip') || (label ? label.textContent.trim() : '');
            }
            function showTip(item) {
                if (!designLayer || compactQuery.matches || !collapsed) { return; }
                var text = tipText(item);
                if (!text) { return; }
                if (!tip) {
                    tip = document.createElement('div');
                    tip.className = 'idlm-nav-tip';
                    tip.setAttribute('aria-hidden', 'true');
                    document.body.appendChild(tip);
                }
                if (item.hasAttribute('title')) { item.dataset.idlmTitle = item.getAttribute('title'); item.removeAttribute('title'); }
                tipOwner = item;
                tip.textContent = text;
                var rect = item.getBoundingClientRect();
                tip.style.left = Math.round(rect.right + 8) + 'px';
                tip.style.top = Math.round(rect.top + rect.height / 2) + 'px';
                tip.classList.add('is-visible');
            }
            function hideTip() {
                if (tip) { tip.classList.remove('is-visible'); }
                if (tipOwner && tipOwner.dataset.idlmTitle) { tipOwner.setAttribute('title', tipOwner.dataset.idlmTitle); delete tipOwner.dataset.idlmTitle; }
                tipOwner = null;
            }
            if (designLayer) {
                sidebar.addEventListener('mouseover', function (event) { var item = event.target.closest('.idlm-workspace-nav-item, .idlm-workspace-toggle'); if (item && item !== tipOwner) { showTip(item); } });
                sidebar.addEventListener('mouseleave', hideTip);
                sidebar.addEventListener('focusin', function (event) { var item = event.target.closest('.idlm-workspace-nav-item, .idlm-workspace-toggle'); if (item) { showTip(item); } });
                sidebar.addEventListener('focusout', hideTip);
                sidebar.addEventListener('scroll', hideTip, {passive: true});
                document.addEventListener('keydown', function (event) { if ('Escape' === event.key) { hideTip(); } });
            }

            function placeTodayEntry(mobile) {
                var entry = workspace.querySelector('.idlm-workspace-today');
                var slot = workspace.querySelector('[data-idlm-today-slot="' + (mobile ? 'topbar' : 'nav') + '"]');
                if (entry && slot && entry.parentElement !== slot) {
                    slot.appendChild(entry);
                }
            }

            function loadLinkCount(link) {
                var vars = window.idlm_vars;
                if (!vars || !vars.ajax_url || !vars.nonce || 'function' !== typeof window.fetch || 'function' !== typeof window.FormData) {
                    return;
                }
                var body = new FormData();
                body.append('action', 'idlm_get_rep_tasks');
                body.append('view', 'attention');
                body.append('counts_only', '1');
                body.append('nonce', vars.nonce);
                window.fetch(vars.ajax_url, {method: 'POST', body: body, credentials: 'same-origin'})
                    .then(function (response) { return response.json(); })
                    .then(function (response) {
                        var attention = response && response.success && response.data ? (response.data.attention || {}) : {};
                        var due = Number(attention.due_today || 0);
                        var overdue = Number(attention.overdue || 0);
                        var total = due + overdue;
                        if (total < 1) {
                            return;
                        }
                        var label = due + ' today · ' + overdue + ' overdue';
                        var badge = link.querySelector('.idlm-floating-tasks-badge');
                        if (!badge) {
                            badge = document.createElement('span');
                            badge.className = 'idlm-floating-tasks-badge';
                            link.appendChild(badge);
                        }
                        badge.textContent = label;
                        link.setAttribute('data-idlm-today-count', total > 99 ? '99+' : String(total));
                        link.setAttribute('aria-label', label + '. Open today’s work.');
                    })
                    .catch(function () {});
            }

            /**
             * Today's work becomes a navigation entry, so it never covers a record. The real
             * button keeps its ID, inline handler and badge; only its position changes. Screens
             * without the overlays keep a link that opens the panel on Leads.
             */
            function initTodayEntry() {
                var button = document.getElementById('idlm-floating-tasks-btn');
                var panel = document.getElementById('idlm-global-tasks-sidebar');
                var link = workspace.querySelector('[data-idlm-today-link]');
                var badge = document.getElementById('idlm-floating-tasks-badge');
                var panelTrigger = null;

                if (!button || !workspace.contains(button)) {
                    // Screens without the overlays only have the link, so it reads the same counts itself.
                    if (link) {
                        loadLinkCount(link);
                    }
                    return;
                }
                if (link) {
                    link.remove();
                }
                button.classList.add('idlm-workspace-nav-item', 'idlm-workspace-today');
                navItems = workspace.querySelectorAll('.idlm-workspace-nav-item');

                function syncCount() {
                    var hidden = !badge || 'none' === badge.style.display || !badge.textContent.trim();
                    var numbers = !hidden ? badge.textContent.match(/\d+/g) : null;
                    var total = (numbers || []).reduce(function (sum, value) { return sum + Number(value); }, 0);
                    if (total > 0) {
                        button.setAttribute('data-idlm-today-count', total > 99 ? '99+' : String(total));
                    } else {
                        button.removeAttribute('data-idlm-today-count');
                    }
                    // A glow painted before this entry existed is cleared; admin-app.js skips it from now on.
                    button.style.removeProperty('box-shadow');
                }
                if (badge) {
                    new MutationObserver(syncCount).observe(badge, {attributes: true, attributeFilter: ['style'], childList: true, characterData: true, subtree: true});
                }
                syncCount();

                if (!panel) {
                    return;
                }
                new MutationObserver(function () {
                    var open = panel.classList.contains('active');
                    if (open === ('true' === button.getAttribute('aria-expanded'))) {
                        return;
                    }
                    button.setAttribute('aria-expanded', open ? 'true' : 'false');
                    button.classList.toggle('is-active', open);
                    if (open) {
                        // Opened from a link on another page, nothing was focused, so focus returns to the entry itself.
                        panelTrigger = document.activeElement === document.body ? null : document.activeElement;
                        var title = panel.querySelector('.idlm-global-sidebar-title');
                        if (title) {
                            title.focus({preventScroll: true});
                        }
                    } else if (panel.contains(document.activeElement) || document.activeElement === document.body) {
                        (panelTrigger && document.contains(panelTrigger) && panelTrigger.getClientRects().length ? panelTrigger : button).focus({preventScroll: true});
                    }
                }).observe(panel, {attributes: true, attributeFilter: ['class']});

                document.addEventListener('keydown', function (event) {
                    var blocked = document.querySelector('.idlm-dialog.active, .idlm-modal.active, .idlm-drawer.active, .idlm-filters-panel.is-open');
                    if ('Escape' === event.key && panel.classList.contains('active') && !blocked && 'function' === typeof window.toggleGlobalTasksSidebar) {
                        window.toggleGlobalTasksSidebar();
                    }
                });

                try {
                    var url = new URL(window.location.href);
                    if ('today' === url.searchParams.get('idlm_open')) {
                        url.searchParams.delete('idlm_open');
                        window.history.replaceState(null, '', url.toString());
                        if ('function' === typeof window.toggleGlobalTasksSidebar) {
                            window.toggleGlobalTasksSidebar();
                        }
                    }
                } catch (error) {}
            }

            function accountMenuItems() {
                return accountMenu ? Array.prototype.slice.call(accountMenu.querySelectorAll('[role="menuitem"]')) : [];
            }

            function closeMobile(restoreFocus) {
                mobileOpen = false;
                render();
                if (restoreFocus) {
                    mobileToggle.focus();
                }
            }
            function positionAccountMenu() {
                if (!accountTrigger || !accountMenu || accountMenu.hidden) return;
                if (!compactQuery.matches && collapsed) {
                    var triggerRect = accountTrigger.getBoundingClientRect();
                    var top = Math.max(12, Math.min(triggerRect.top, window.innerHeight - accountMenu.offsetHeight - 12));
                    accountMenu.style.position = 'fixed';
                    accountMenu.style.top = Math.round(top) + 'px';
                    accountMenu.style.right = 'auto';
                    accountMenu.style.bottom = 'auto';
                    accountMenu.style.left = Math.round(triggerRect.right + 8) + 'px';
                    return;
                }
                accountMenu.style.position = '';
                accountMenu.style.top = '';
                accountMenu.style.right = '';
                accountMenu.style.bottom = '';
                accountMenu.style.left = '';
            }
            function setAccountMenu(open) { if (!accountTrigger || !accountMenu) return; accountMenu.hidden = !open; accountTrigger.setAttribute('aria-expanded', open ? 'true' : 'false'); positionAccountMenu(); }
            if (accountTrigger && accountMenu) { accountTrigger.addEventListener('click', function () { setAccountMenu(accountMenu.hidden); }); document.addEventListener('click', function (event) { if (!accountMenu.hidden && !event.target.closest('.idlm-workspace-account-menu-wrap')) setAccountMenu(false); }); }

            if (designLayer) {
                // Overlays lock page scrolling; keeping the gutter stops the page jumping sideways.
                document.documentElement.style.scrollbarGutter = 'stable';
                initTodayEntry();
            }
            render();

            desktopToggle.addEventListener('click', function () {
                if (compactQuery.matches) {
                    return;
                }
                collapsed = !collapsed;
                render();
                try {
                    window.localStorage.setItem(storageKey, collapsed ? '1' : '0');
                } catch (error) {}
            });

            mobileToggle.addEventListener('click', function () {
                mobileOpen = !mobileOpen;
                render();
                if (designLayer && mobileOpen) {
                    (drawerClose || sidebar).focus();
                }
            });

            if (drawerClose) {
                drawerClose.addEventListener('click', function () {
                    closeMobile(true);
                });
            }

            if (designLayer && accountTrigger && accountMenu) {
                accountTrigger.addEventListener('click', function () {
                    var items = accountMenuItems();
                    if (!accountMenu.hidden && items.length) {
                        items[0].focus();
                    }
                });
                accountMenu.addEventListener('keydown', function (event) {
                    var items = accountMenuItems();
                    var index = items.indexOf(document.activeElement);
                    if ('ArrowDown' === event.key || 'ArrowUp' === event.key) {
                        event.preventDefault();
                        items[(index + ('ArrowDown' === event.key ? 1 : -1) + items.length) % items.length].focus();
                    } else if ('Tab' === event.key) {
                        setAccountMenu(false);
                    }
                });
            }

            backdrop.addEventListener('click', function () {
                closeMobile(true);
            });

            document.addEventListener('keydown', function (event) {
                if (event.key === 'Escape' && compactQuery.matches && mobileOpen) {
                    closeMobile(true);
                }
                if (event.key === 'Escape' && accountMenu && !accountMenu.hidden) { setAccountMenu(false); accountTrigger.focus(); }
            });

            function onViewportChange() {
                mobileOpen = false;
                render();
            }

            if (compactQuery.addEventListener) {
                compactQuery.addEventListener('change', onViewportChange);
            } else {
                compactQuery.addListener(onViewportChange);
            }
            window.addEventListener('resize', positionAccountMenu);
        });
    });
}());
