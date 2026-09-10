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
            });

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
