(function () {
    'use strict';

    /* Initialize the dedicated User Portal desktop sidebar toggle once. */
    if (window.rmsUserSidebarToggleReady) {
        return;
    }
    window.rmsUserSidebarToggleReady = true;

    var body = document.body;
    var toggle = document.getElementById('portal-sidebar-edge-toggle');
    var storageKey = 'rmsUserPortalSidebarCollapsed';
    var desktopBreakpoint = 980;

    /* Add or remove the collapsed class without changing other body classes. */
    function setBodyClass(enabled) {
        var className = 'portal-sidebar-collapsed';
        var pattern = new RegExp('(^|\\s)' + className + '(?=\\s|$)', 'g');
        var current = body.className.replace(pattern, ' ').replace(/\s+/g, ' ');

        body.className = (enabled ? current + ' ' + className : current)
            .replace(/^\s+|\s+$/g, '');
    }

    /* Read the saved User Portal sidebar state safely. */
    function readState() {
        try {
            return window.localStorage.getItem(storageKey) === '1';
        } catch (error) {
            return false;
        }
    }

    /* Save the selected User Portal sidebar state safely. */
    function saveState(collapsed) {
        try {
            window.localStorage.setItem(storageKey, collapsed ? '1' : '0');
        } catch (error) {
            return;
        }
    }

    /* Apply the compact icon rail and synchronize the toggle label. */
    function applyState(collapsed, remember) {
        if (window.innerWidth <= desktopBreakpoint) {
            collapsed = false;
        }

        setBodyClass(collapsed);
        toggle.setAttribute('aria-expanded', collapsed ? 'false' : 'true');
        toggle.setAttribute('aria-label', collapsed ? 'Expand sidebar' : 'Collapse sidebar');
        toggle.setAttribute('title', collapsed ? 'Expand sidebar' : 'Collapse sidebar');

        if (remember) {
            saveState(collapsed);
        }
    }

    if (!body || !toggle) {
        return;
    }

    applyState(readState(), false);

    toggle.addEventListener('click', function () {
        var collapsed = body.className.indexOf('portal-sidebar-collapsed') !== -1;
        applyState(!collapsed, true);
    });

    window.addEventListener('resize', function () {
        applyState(readState(), false);
    });
}());
