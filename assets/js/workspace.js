(function () {
    'use strict';

    try {
        if (window.localStorage.getItem('idlmWorkspaceSidebarCollapsed') === '1') {
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
            var toggle = workspace.querySelector('.idlm-workspace-toggle');
            var storageKey = 'idlmWorkspaceSidebarCollapsed';
            var collapsed = false;

            try { collapsed = window.localStorage.getItem(storageKey) === '1'; } catch (error) {}

            function applyState() {
                workspace.classList.toggle('is-collapsed', collapsed);
                toggle.setAttribute('aria-expanded', collapsed ? 'false' : 'true');
                toggle.setAttribute('aria-label', collapsed ? 'Expand navigation' : 'Collapse navigation');
                document.documentElement.classList.remove('idlm-workspace-sidebar-precollapsed');
            }

            applyState();
            toggle.addEventListener('click', function () {
                collapsed = !collapsed;
                applyState();
                try { window.localStorage.setItem(storageKey, collapsed ? '1' : '0'); } catch (error) {}
            });
        });
    });
}());
