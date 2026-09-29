(function () {
    'use strict';

    /* Initialize shared User Portal menus and password controls once. */
    if (window.rmsUserPortalControlsReady) {
        return;
    }
    window.rmsUserPortalControlsReady = true;

    var body = document.body;
    var mobileToggle = document.querySelector('[data-portal-sidebar-toggle]');
    var sidebarOverlay = document.querySelector('[data-portal-sidebar-close]');
    var accountToggle = document.querySelector('[data-portal-account-toggle]');
    var accountMenu = document.querySelector('[data-portal-account-menu]');
    var sidebarCollapseControl = document.getElementById('portal-sidebar-collapse-control');
    var sidebarCollapseStorageKey = 'rmsUserPortalSidebarCollapsed';
    var sidebarDesktopBreakpoint = 980;

    /* Read the saved desktop sidebar preference without breaking restricted browsers. */
    function readSidebarCollapseState() {
        try {
            return window.localStorage.getItem(sidebarCollapseStorageKey) === '1';
        } catch (error) {
            return false;
        }
    }

    /* Save the desktop sidebar preference without affecting page navigation. */
    function saveSidebarCollapseState(collapsed) {
        try {
            window.localStorage.setItem(sidebarCollapseStorageKey, collapsed ? '1' : '0');
        } catch (error) {
            return;
        }
    }

    /* Restore the saved state only on desktop-sized User Portal pages. */
    function restoreSidebarCollapseState() {
        if (!sidebarCollapseControl) {
            return;
        }

        sidebarCollapseControl.checked = window.innerWidth > sidebarDesktopBreakpoint
            ? readSidebarCollapseState()
            : false;
    }

    /* Open or close the existing responsive sidebar drawer. */
    function setMobileSidebar(open) {
        body.classList.toggle('portal-sidebar-open', open);
        if (mobileToggle) {
            mobileToggle.setAttribute('aria-expanded', open ? 'true' : 'false');
        }
    }

    /* Open or close the account dropdown. */
    function setAccountMenu(open) {
        if (!accountToggle || !accountMenu) {
            return;
        }
        accountMenu.classList.toggle('is-open', open);
        accountToggle.setAttribute('aria-expanded', open ? 'true' : 'false');
    }

    if (mobileToggle) {
        mobileToggle.addEventListener('click', function () {
            setMobileSidebar(!body.classList.contains('portal-sidebar-open'));
        });
    }

    if (sidebarOverlay) {
        sidebarOverlay.addEventListener('click', function () {
            setMobileSidebar(false);
        });
    }

    if (accountToggle) {
        accountToggle.addEventListener('click', function (event) {
            event.stopPropagation();
            setAccountMenu(!accountMenu.classList.contains('is-open'));
        });
    }

    /* Keep the selected sidebar design across Dashboard, Documents, and Profile. */
    if (sidebarCollapseControl) {
        restoreSidebarCollapseState();
        sidebarCollapseControl.addEventListener('change', function () {
            if (window.innerWidth > sidebarDesktopBreakpoint) {
                saveSidebarCollapseState(sidebarCollapseControl.checked);
            }
        });

        window.addEventListener('resize', function () {
            restoreSidebarCollapseState();
        });

        /* Restore the saved state when returning through browser history. */
        window.addEventListener('pageshow', function () {
            restoreSidebarCollapseState();
        });
    }

    /* Toggle each profile password exactly once, including its SVG icon. */
    document.addEventListener('click', function (event) {
        var toggle = event.target;
        var input;
        var reveal;

        while (toggle && toggle !== document && !toggle.getAttribute('data-profile-password-toggle')) {
            toggle = toggle.parentNode;
        }

        if (!toggle || toggle === document) {
            if (accountMenu && !accountMenu.contains(event.target) && event.target !== accountToggle) {
                setAccountMenu(false);
            }
            return;
        }

        event.preventDefault();
        event.stopImmediatePropagation();
        input = document.getElementById(toggle.getAttribute('data-profile-password-toggle'));
        if (!input) {
            return;
        }

        reveal = input.getAttribute('type') === 'password';
        input.setAttribute('type', reveal ? 'text' : 'password');
        toggle.setAttribute('aria-pressed', reveal ? 'true' : 'false');
        toggle.setAttribute('aria-label', reveal ? 'Hide password' : 'Show password');
        toggle.classList.toggle('is-visible', reveal);
        input.focus();
    }, true);

    /* Escape closes menus without changing the desktop sidebar state. */
    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape' || event.keyCode === 27) {
            setMobileSidebar(false);
            setAccountMenu(false);
        }
    });
}());
