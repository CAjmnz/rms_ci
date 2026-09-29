/*
 * Shared admin sidebar behavior
 *
 * Controls the mobile drawer and the desktop compact/full-width toggle.
 * The selected desktop state is remembered across all admin pages.
 */
(function () {
    'use strict';

    // Prevent this shared script from being initialized twice on older pages.
    if (window.rmsSidebarInitialized) {
        return;
    }
    window.rmsSidebarInitialized = true;

    var sidebar = document.getElementById('sidebar');
    var overlay = document.getElementById('mobile-overlay');
    var openButton = document.getElementById('mobile-menu');
    var closeButton = document.getElementById('mobile-close');
    var toggleButton = document.getElementById('sidebar-toggle');
    var storageKey = 'rmsSidebarCollapsed';
    var desktopBreakpoint = 980;
    var expandButtons = document.querySelectorAll
        ? document.querySelectorAll('.nav-expand')
        : [];

    // Add or remove one body class without deleting classes used by a page.
    function setBodyClass(className, enabled) {
        var pattern = new RegExp('(^|\\s)' + className + '(?=\\s|$)', 'g');
        var current = document.body.className.replace(pattern, ' ').replace(/\s+/g, ' ');

        document.body.className = (enabled ? current + ' ' + className : current)
            .replace(/^\s+|\s+$/g, '');
    }

    // Read the saved preference safely when browser storage is unavailable.
    function getSavedCollapsedState() {
        try {
            return window.localStorage.getItem(storageKey) === '1';
        } catch (error) {
            return false;
        }
    }

    // Save the user's desktop choice so navigation keeps the same layout.
    function saveCollapsedState(collapsed) {
        try {
            window.localStorage.setItem(storageKey, collapsed ? '1' : '0');
        } catch (error) {
            // The toggle still works for this page when storage is blocked.
        }
    }

    // Synchronize the desktop collapse button text and accessibility state.
    function updateToggleButton(collapsed) {
        if (!toggleButton) {
            return;
        }

        toggleButton.setAttribute('aria-expanded', collapsed ? 'false' : 'true');
        toggleButton.setAttribute(
            'aria-label',
            collapsed ? 'Expand sidebar' : 'Collapse sidebar'
        );
        toggleButton.setAttribute(
            'title',
            collapsed ? 'Expand sidebar' : 'Collapse sidebar'
        );
    }

    // Apply the compact desktop rail while leaving the mobile drawer unchanged.
    function setDesktopCollapsed(collapsed, remember) {
        if (window.innerWidth <= desktopBreakpoint) {
            collapsed = false;
        }

        setBodyClass('sidebar-collapsed', collapsed);
        updateToggleButton(collapsed);

        if (remember) {
            saveCollapsedState(collapsed);
        }
    }

    // Open or close the mobile drawer and preserve unrelated sidebar classes.
    function setMobileSidebar(open) {
        if (!sidebar || !overlay) {
            return;
        }

        if (sidebar.classList) {
            sidebar.classList.toggle('is-open', open);
            overlay.classList.toggle('is-visible', open);
        } else {
            sidebar.className = open ? 'sidebar is-open' : 'sidebar';
            overlay.className = open ? 'mobile-overlay is-visible' : 'mobile-overlay';
        }

        overlay.setAttribute('aria-hidden', open ? 'false' : 'true');
        if (openButton) {
            openButton.setAttribute('aria-expanded', open ? 'true' : 'false');
        }
        if (toggleButton) {
            toggleButton.setAttribute('aria-expanded', open ? 'true' : 'false');
            toggleButton.setAttribute('aria-label', open ? 'Close navigation' : 'Open navigation');
            toggleButton.setAttribute('title', open ? 'Close navigation' : 'Open navigation');
        }
        setBodyClass('nav-open', open);
    }

    // Restore the compact preference as soon as the shared sidebar loads.
    setDesktopCollapsed(getSavedCollapsedState(), false);
    if (window.innerWidth <= desktopBreakpoint) {
        // Initialize the responsive control as a closed mobile menu button.
        setMobileSidebar(false);
    }

    if (toggleButton) {
        toggleButton.onclick = function () {
            // On smaller screens the same visible control operates the drawer.
            if (window.innerWidth <= desktopBreakpoint) {
                var mobileOpen = document.body.className.indexOf('nav-open') !== -1;
                setMobileSidebar(!mobileOpen);
                return;
            }

            var collapsed = document.body.className.indexOf('sidebar-collapsed') !== -1;
            setDesktopCollapsed(!collapsed, true);
        };
    }

    if (openButton) {
        openButton.onclick = function () {
            setMobileSidebar(true);
        };
    }

    if (closeButton) {
        closeButton.onclick = function () {
            setMobileSidebar(false);
        };
    }

    if (overlay) {
        overlay.onclick = function () {
            setMobileSidebar(false);
        };
    }

    // Preserve the existing optional nested-menu behavior.
    for (var index = 0; index < expandButtons.length; index++) {
        expandButtons[index].onclick = function () {
            var targetId = this.getAttribute('data-target');
            var target = document.getElementById(targetId);
            var open = this.getAttribute('aria-expanded') === 'true';

            if (!target) {
                return;
            }

            this.setAttribute('aria-expanded', open ? 'false' : 'true');
            target.className = open ? 'nav-submenu' : 'nav-submenu is-open';
        };
    }

    // Re-evaluate the layout when crossing between desktop and mobile widths.
    window.addEventListener('resize', function () {
        if (window.innerWidth <= desktopBreakpoint) {
            setDesktopCollapsed(false, false);
            setMobileSidebar(false);
        } else {
            setMobileSidebar(false);
            setDesktopCollapsed(getSavedCollapsedState(), false);
        }
    });

    // Escape closes only the mobile drawer; desktop compact state is preserved.
    document.addEventListener('keydown', function (event) {
        event = event || window.event;

        if (event.keyCode === 27) {
            setMobileSidebar(false);
        }
    });
}());
