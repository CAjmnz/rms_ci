/*
 * Departments server-side table navigation.
 * The CI3 controller still performs filtering, sorting, counting, and paging;
 * this file replaces only the returned table region instead of the whole page.
 */
(function () {
    'use strict';

    var searchForm = document.getElementById('departments-search-form');
    var searchInput = document.getElementById('departments-search');
    var searchButton = searchForm
        ? searchForm.querySelector('button[type="submit"]')
        : null;
    var originalButtonText = searchButton ? searchButton.textContent : 'Search';
    var activeRequest = null;

    if (!searchForm || !searchInput) {
        return;
    }

    /** Encode a GET form without requiring newer browser APIs. */
    function encodeForm(form) {
        var fields = form.elements;
        var parts = [];
        var index;

        for (index = 0; index < fields.length; index++) {
            if (!fields[index].name || fields[index].disabled) {
                continue;
            }

            parts.push(
                encodeURIComponent(fields[index].name) + '=' +
                encodeURIComponent(fields[index].value)
            );
        }

        return parts.join('&');
    }

    /** Keep the toolbar query state synchronized with the returned page. */
    function syncSearchForm(nextDocument) {
        var nextForm = nextDocument.getElementById('departments-search-form');
        var names = ['search', 'per_page', 'sort', 'order'];
        var index;
        var currentField;
        var nextField;

        if (!nextForm) {
            return;
        }

        for (index = 0; index < names.length; index++) {
            currentField = searchForm.querySelector('[name="' + names[index] + '"]');
            nextField = nextForm.querySelector('[name="' + names[index] + '"]');

            if (currentField && nextField) {
                currentField.value = nextField.value;
            }
        }
    }

    /** Reset actions because a newly loaded table has no selected row. */
    function resetDepartmentSelection() {
        var editButton = document.getElementById('dept-edit');
        var deleteButton = document.getElementById('dept-delete');

        if (editButton) {
            editButton.disabled = true;
        }

        if (deleteButton) {
            deleteButton.disabled = true;
        }
    }

    function setLoading(isLoading) {
        var region = document.getElementById('departments-ajax-region');

        searchForm.classList[isLoading ? 'add' : 'remove']('is-searching');

        if (isLoading) {
            searchForm.setAttribute('aria-busy', 'true');
        } else {
            searchForm.removeAttribute('aria-busy');
        }

        if (searchButton) {
            searchButton.disabled = isLoading;
            searchButton.textContent = isLoading ? 'Searching...' : originalButtonText;
        }

        if (region) {
            region.classList[isLoading ? 'add' : 'remove']('is-loading');

            if (isLoading) {
                region.setAttribute('aria-busy', 'true');
            } else {
                region.removeAttribute('aria-busy');
            }
        }
    }

    /** Load the normal CI3 page and paint only its Department result region. */
    function loadTable(url, addHistory) {
        var currentRegion = document.getElementById('departments-ajax-region');
        var request;

        if (!currentRegion || !window.XMLHttpRequest || !window.DOMParser) {
            window.location.href = url;
            return;
        }

        if (activeRequest) {
            /* Mark the old request stale before aborting its callback. */
            request = activeRequest;
            activeRequest = null;
            request.abort();
        }

        setLoading(true);
        request = new XMLHttpRequest();
        activeRequest = request;
        request.open('GET', url, true);
        request.setRequestHeader('X-Requested-With', 'XMLHttpRequest');

        request.onreadystatechange = function () {
            var nextDocument;
            var nextRegion;
            var nextTotal;
            var currentTotal;

            if (request.readyState !== 4) {
                return;
            }

            if (request !== activeRequest) {
                return;
            }

            if (request.status < 200 || request.status >= 300) {
                window.location.href = url;
                return;
            }

            nextDocument = new DOMParser().parseFromString(
                request.responseText,
                'text/html'
            );
            nextRegion = nextDocument.getElementById('departments-ajax-region');

            if (!nextRegion) {
                window.location.href = url;
                return;
            }

            currentRegion.innerHTML = nextRegion.innerHTML;
            nextTotal = nextDocument.getElementById('departments-matching-total');
            currentTotal = document.getElementById('departments-matching-total');

            if (nextTotal && currentTotal) {
                currentTotal.textContent = nextTotal.textContent;
            }

            syncSearchForm(nextDocument);
            resetDepartmentSelection();
            activeRequest = null;
            setLoading(false);

            if (addHistory && window.history && window.history.pushState) {
                window.history.pushState({departmentsTable: true}, '', url);
            }

            searchInput.focus();
        };

        request.send(null);
    }

    function submitSearch() {
        var action = searchForm.getAttribute('action') || window.location.pathname;
        var query = encodeForm(searchForm);

        loadTable(action + (query ? '?' + query : ''), true);
    }

    /* Search button and Enter submit without a full-page refresh. */
    searchForm.onsubmit = function (event) {
        if (event && event.preventDefault) {
            event.preventDefault();
        }

        submitSearch();
        return false;
    };

    /* Sorting, pagination, and Clear links all reuse their server-built URLs. */
    document.addEventListener('click', function (event) {
        var target = event.target || event.srcElement;
        var region = document.getElementById('departments-ajax-region');
        var clearLink;

        while (target && target !== document && target.tagName !== 'A') {
            target = target.parentNode;
        }

        clearLink = target && searchForm.contains(target);

        if (!target || target === document ||
            (!clearLink && (!region || !region.contains(target)))) {
            return;
        }

        if (target.className && String(target.className).indexOf('disabled') !== -1) {
            event.preventDefault();
            return;
        }

        event.preventDefault();
        loadTable(target.href, true);
    });

    /* Rows-per-page remains a database-side page request. */
    document.addEventListener('change', function (event) {
        var target = event.target || event.srcElement;
        var form;
        var action;
        var query;

        if (!target || target.id !== 'departments-per-page') {
            return;
        }

        form = target.form;
        action = form.getAttribute('action') || window.location.pathname;
        query = encodeForm(form);
        loadTable(action + (query ? '?' + query : ''), true);
    });

    /* Back and Forward restore the matching server-side table state. */
    if (window.addEventListener) {
        window.addEventListener('pageshow', function () {
            setLoading(false);
        });

        window.addEventListener('popstate', function () {
            loadTable(window.location.href, false);
        });
    }
}());
