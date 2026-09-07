(function () {
    'use strict';

    /* Locate the document workspace before registering page-only controls. */
    var fileList = document.querySelector('[data-file-layout]');
    var viewButtons = document.querySelectorAll('[data-document-view]');
    var selectors = document.querySelectorAll('.document-selector');
    var selectAll = document.getElementById('select-all-documents');
    var selectionCount = document.getElementById('document-selection-count');
    var downloadButton = document.getElementById('download-selected-documents');
    var batchForm = document.getElementById('document-batch-form');
    var previewImages = document.querySelectorAll('.drive-file-preview img');
    var fileOpenButtons = document.querySelectorAll('.drive-file-open');
    var viewerModal = document.getElementById('document-viewer-modal');
    var viewerPanel = viewerModal ? viewerModal.querySelector('.document-viewer-panel') : null;
    var viewerTitle = document.getElementById('document-viewer-title');
    var viewerDescription = document.getElementById('document-viewer-description');
    var viewerImage = document.getElementById('document-viewer-image');
    var viewerFrame = document.getElementById('document-viewer-frame');
    var viewerDownload = document.getElementById('document-viewer-download');
    var viewerLoading = viewerModal ? viewerModal.querySelector('.document-viewer-loading') : null;
    var viewerStage = viewerModal ? viewerModal.querySelector('.document-viewer-stage') : null;
    var viewerCloseControls = document.querySelectorAll('[data-close-document-viewer]');
    var viewerPrevious = document.getElementById('document-viewer-previous');
    var viewerNext = document.getElementById('document-viewer-next');
    var viewerZoomOut = document.getElementById('document-viewer-zoom-out');
    var viewerZoomReset = document.getElementById('document-viewer-zoom-reset');
    var viewerZoomIn = document.getElementById('document-viewer-zoom-in');
    var viewerZoomLevel = document.getElementById('document-viewer-zoom-level');
    var viewerPosition = document.getElementById('document-viewer-position');
    var lastViewerTrigger = null;
    var currentViewerIndex = 0;
    var currentZoom = 1;
    var currentPanX = 0;
    var currentPanY = 0;
    var panStartX = 0;
    var panStartY = 0;
    var isPanning = false;
    var storageKey = 'rms-document-file-view';

    /* Apply one layout and synchronize the visible segmented control. */
    function setView(view) {
        if (!fileList) {
            return;
        }

        var selectedView = view === 'list' ? 'list' : 'grid';
        var index;

        fileList.classList.toggle('is-grid-view', selectedView === 'grid');
        fileList.classList.toggle('is-list-view', selectedView === 'list');
        fileList.setAttribute('data-file-layout', selectedView);

        for (index = 0; index < viewButtons.length; index++) {
            var isCurrent = viewButtons[index].getAttribute('data-document-view') === selectedView;
            viewButtons[index].classList.toggle('is-active', isCurrent);
            viewButtons[index].setAttribute('aria-pressed', isCurrent ? 'true' : 'false');
        }

        try {
            window.localStorage.setItem(storageKey, selectedView);
        } catch (error) {
            /* The layout still works when browser storage is unavailable. */
        }
    }

    /* Restore the user's previous layout without making it a server setting. */
    var defaultView = fileList && fileList.getAttribute('data-default-layout') === 'list' ? 'list' : 'grid';
    var savedView = defaultView;
    try {
        savedView = window.localStorage.getItem(storageKey) || defaultView;
    } catch (error) {
        savedView = defaultView;
    }
    setView(savedView);

    Array.prototype.forEach.call(viewButtons, function (button) {
        button.addEventListener('click', function () {
            setView(button.getAttribute('data-document-view'));
        });
    });

    /* Clear the secure preview source after the close animation completes. */
    function resetViewerContent() {
        if (viewerImage) {
            viewerImage.removeAttribute('src');
            viewerImage.hidden = true;
            viewerImage.classList.remove('is-ready');
            viewerImage.style.transform = '';
        }
        if (viewerFrame) {
            viewerFrame.removeAttribute('src');
            viewerFrame.hidden = true;
            viewerFrame.classList.remove('is-ready');
            viewerFrame.style.transform = '';
        }
        if (viewerLoading) {
            viewerLoading.querySelector('p').textContent = 'Loading secure preview...';
        }
    }

    /* Apply zoom and drag position to whichever secure preview is visible. */
    function applyViewerTransform() {
        var transform = 'translate(' + currentPanX + 'px,' + currentPanY + 'px) scale(' + currentZoom + ')';

        if (viewerImage && !viewerImage.hidden) {
            viewerImage.style.transform = transform;
        }
        if (viewerFrame && !viewerFrame.hidden) {
            viewerFrame.style.transform = transform;
        }
    }

    /* Apply a bounded zoom value to whichever secure preview is visible. */
    function setViewerZoom(zoom) {
        currentZoom = Math.max(0.5, Math.min(3, zoom));
        applyViewerTransform();
        if (viewerZoomLevel) {
            viewerZoomLevel.textContent = Math.round(currentZoom * 100) + '%';
        }
        if (viewerZoomOut) {
            viewerZoomOut.disabled = currentZoom <= 0.5;
        }
        if (viewerZoomIn) {
            viewerZoomIn.disabled = currentZoom >= 3;
        }
    }

    /* Close the modal and return keyboard focus to the opened file. */
    function closeViewer() {
        if (!viewerModal || viewerModal.hidden) {
            return;
        }

        viewerModal.classList.remove('is-open');
        document.body.classList.remove('document-viewer-open');
        window.setTimeout(function () {
            viewerModal.hidden = true;
            resetViewerContent();
            if (lastViewerTrigger) {
                lastViewerTrigger.focus();
            }
        }, 220);
    }

    /* Open an authorized image or document inside the professional viewer. */
    function openViewer(button) {
        if (!viewerModal || !viewerPanel) {
            return;
        }

        var viewUrl = button.getAttribute('data-file-view-url');
        var downloadUrl = button.getAttribute('data-file-download-url');
        var fileName = button.getAttribute('data-file-name');
        var filePage = button.getAttribute('data-file-page');
        var fileKind = button.getAttribute('data-file-kind');
        var previewElement = fileKind === 'image' ? viewerImage : viewerFrame;

        lastViewerTrigger = button;
        currentViewerIndex = Array.prototype.indexOf.call(fileOpenButtons, button);
        currentPanX = 0;
        currentPanY = 0;
        resetViewerContent();
        setViewerZoom(1);
        viewerTitle.textContent = fileName || 'File preview';
        viewerDescription.textContent = filePage || 'Authorized document';
        if (viewerPosition) {
            viewerPosition.textContent = (currentViewerIndex + 1) + ' of ' + fileOpenButtons.length;
        }
        viewerLoading.hidden = false;

        if (viewerDownload) {
            if (downloadUrl) {
                viewerDownload.href = downloadUrl;
                viewerDownload.hidden = false;
            } else {
                viewerDownload.hidden = true;
                viewerDownload.removeAttribute('href');
            }
        }

        viewerModal.hidden = false;
        document.body.classList.add('document-viewer-open');
        window.setTimeout(function () {
            viewerModal.classList.add('is-open');
            viewerPanel.focus();
        }, 20);

        if (previewElement) {
            previewElement.onload = function () {
                viewerLoading.hidden = true;
                previewElement.hidden = false;
                previewElement.classList.add('is-ready');
                setViewerZoom(currentZoom);
            };
            previewElement.onerror = function () {
                viewerLoading.hidden = false;
                viewerLoading.querySelector('p').textContent = 'This preview could not be displayed.';
            };
            previewElement.src = viewUrl;
        }
    }

    /* Move through files in their current visible order and wrap at each end. */
    function moveViewer(direction) {
        if (!fileOpenButtons.length) {
            return;
        }

        currentViewerIndex = (currentViewerIndex + direction + fileOpenButtons.length) % fileOpenButtons.length;
        openViewer(fileOpenButtons[currentViewerIndex]);
    }

    Array.prototype.forEach.call(fileOpenButtons, function (button) {
        button.addEventListener('click', function () {
            openViewer(button);
        });
    });

    Array.prototype.forEach.call(viewerCloseControls, function (control) {
        control.addEventListener('click', closeViewer);
    });

    if (viewerPrevious) {
        viewerPrevious.addEventListener('click', function () {
            moveViewer(-1);
        });
    }
    if (viewerNext) {
        viewerNext.addEventListener('click', function () {
            moveViewer(1);
        });
    }
    if (viewerZoomOut) {
        viewerZoomOut.addEventListener('click', function () {
            setViewerZoom(currentZoom - 0.25);
        });
    }
    if (viewerZoomReset) {
        viewerZoomReset.addEventListener('click', function () {
            setViewerZoom(1);
        });
    }
    if (viewerZoomIn) {
        viewerZoomIn.addEventListener('click', function () {
            setViewerZoom(currentZoom + 0.25);
        });
    }

    /* Ctrl plus mouse wheel zooms the preview instead of the whole page. */
    if (viewerStage) {
        viewerStage.addEventListener('wheel', function (event) {
            if (!event.ctrlKey || viewerModal.hidden) {
                return;
            }

            event.preventDefault();
            setViewerZoom(currentZoom + (event.deltaY < 0 ? 0.25 : -0.25));
        }, {passive: false});

        /* VIEWER FIX: hold the left mouse button and drag to reposition the preview.
           Uses mousedown on the stage plus document-level mousemove/mouseup (rather than Pointer Events
           + setPointerCapture) so dragging keeps working even if the pointer moves outside the stage. */
        viewerStage.addEventListener('mousedown', function (event) {
            if (event.button !== 0 || viewerModal.hidden) {
                return;
            }

            isPanning = true;
            panStartX = event.clientX - currentPanX;
            panStartY = event.clientY - currentPanY;
            viewerStage.classList.add('is-panning');
            event.preventDefault();
        });

        document.addEventListener('mousemove', function (event) {
            if (!isPanning) {
                return;
            }

            currentPanX = event.clientX - panStartX;
            currentPanY = event.clientY - panStartY;
            applyViewerTransform();
            event.preventDefault();
        });

        document.addEventListener('mouseup', function () {
            if (!isPanning) {
                return;
            }
            isPanning = false;
            viewerStage.classList.remove('is-panning');
        });

        /* VIEWER FIX: also stop dragging if the window loses focus mid-drag (e.g. alt-tab). */
        window.addEventListener('blur', function () {
            if (isPanning) {
                isPanning = false;
                viewerStage.classList.remove('is-panning');
            }
        });
    }

    document.addEventListener('keydown', function (event) {
        if (!viewerModal || viewerModal.hidden) {
            return;
        }

        if (event.key === 'Escape' || event.keyCode === 27) {
            closeViewer();
        } else if (event.key === 'ArrowLeft' || event.keyCode === 37) {
            event.preventDefault();
            moveViewer(-1);
        } else if (event.key === 'ArrowRight' || event.keyCode === 39) {
            event.preventDefault();
            moveViewer(1);
        } else if (event.key === '+' || event.key === '=') {
            event.preventDefault();
            setViewerZoom(currentZoom + 0.25);
        } else if (event.key === '-' || event.key === '_') {
            event.preventDefault();
            setViewerZoom(currentZoom - 0.25);
        }
    });

    /* Replace an unavailable image thumbnail with the document fallback icon. */
    Array.prototype.forEach.call(previewImages, function (image) {
        image.addEventListener('error', function () {
            image.parentNode.classList.add('has-preview-error');
            image.hidden = true;
        });
    });

    /* Keep selection count, Select all, and ZIP button states synchronized. */
    function updateSelection() {
        var checked = document.querySelectorAll('.document-selector:checked').length;
        var total = selectors.length;

        if (selectionCount) {
            selectionCount.textContent = checked + ' selected';
        }
        if (downloadButton) {
            downloadButton.disabled = checked === 0;
        }
        if (selectAll) {
            selectAll.checked = total > 0 && checked === total;
            selectAll.indeterminate = checked > 0 && checked < total;
        }
    }

    Array.prototype.forEach.call(selectors, function (selector) {
        selector.addEventListener('change', updateSelection);
    });

    if (selectAll) {
        selectAll.addEventListener('change', function () {
            Array.prototype.forEach.call(selectors, function (selector) {
                selector.checked = selectAll.checked;
            });
            updateSelection();
        });
    }

    if (batchForm) {
        batchForm.addEventListener('submit', function (event) {
            if (document.querySelectorAll('.document-selector:checked').length === 0) {
                event.preventDefault();
                return;
            }

            if (downloadButton) {
                downloadButton.classList.add('is-preparing');
                downloadButton.querySelector('span').textContent = 'Preparing ZIP...';
                window.setTimeout(function () {
                    downloadButton.classList.remove('is-preparing');
                    downloadButton.querySelector('span').textContent = 'Download selected';
                    updateSelection();
                }, 2500);
            }
        });
    }

    updateSelection();

    /* Refresh references and controls after an AJAX-rendered folder response. */
    function initializeAjaxWorkspace() {
        fileList = document.querySelector('[data-file-layout]');
        viewButtons = document.querySelectorAll('[data-document-view]');
        selectors = document.querySelectorAll('.document-selector');
        selectAll = document.getElementById('select-all-documents');
        selectionCount = document.getElementById('document-selection-count');
        downloadButton = document.getElementById('download-selected-documents');
        batchForm = document.getElementById('document-batch-form');
        previewImages = document.querySelectorAll('.drive-file-preview img');
        fileOpenButtons = document.querySelectorAll('.drive-file-open');

        if (!fileList) {
            return;
        }

        setView(savedView);
        Array.prototype.forEach.call(viewButtons, function (button) {
            button.addEventListener('click', function () {
                setView(button.getAttribute('data-document-view'));
            });
        });
        Array.prototype.forEach.call(fileOpenButtons, function (button) {
            button.addEventListener('click', function () {
                openViewer(button);
            });
        });
        Array.prototype.forEach.call(selectors, function (selector) {
            selector.addEventListener('change', updateSelection);
        });
        Array.prototype.forEach.call(previewImages, function (image) {
            image.addEventListener('error', function () {
                image.parentNode.classList.add('has-preview-error');
                image.hidden = true;
            });
        });
        if (selectAll) {
            selectAll.addEventListener('change', function () {
                Array.prototype.forEach.call(selectors, function (selector) {
                    selector.checked = selectAll.checked;
                });
                updateSelection();
            });
        }
        updateSelection();
    }

    /* Request a server-rendered folder and replace only the document workspace. */
    function loadDocumentWorkspace(url, addHistory) {
        var currentWorkspace = document.querySelector('.drive-workspace');
        if (!currentWorkspace || !window.fetch || !window.DOMParser) {
            window.location.href = url;
            return;
        }

        currentWorkspace.classList.add('is-ajax-loading');
        fetch(url, {
            credentials: 'same-origin',
            headers: {'X-Requested-With': 'XMLHttpRequest'}
        }).then(function (response) {
            if (!response.ok) {
                throw new Error('Workspace request failed');
            }
            return response.text();
        }).then(function (html) {
            var nextPage = new DOMParser().parseFromString(html, 'text/html');
            var nextWorkspace = nextPage.querySelector('.drive-workspace');
            if (!nextWorkspace) {
                window.location.href = url;
                return;
            }

            currentWorkspace.parentNode.replaceChild(nextWorkspace, currentWorkspace);
            if (addHistory) {
                window.history.pushState({documentWorkspace: true}, '', url);
            }
            initializeAjaxWorkspace();
            nextWorkspace.scrollIntoView({behavior: 'smooth', block: 'start'});
        }).catch(function () {
            window.location.href = url;
        });
    }

    /* Intercept folder, breadcrumb, Back, and search navigation only. */
    document.addEventListener('click', function (event) {
        var link = event.target.closest('.drive-folder-card,.drive-breadcrumbs a,.drive-back-button');
        if (!link || event.ctrlKey || event.metaKey || event.shiftKey || event.altKey) {
            return;
        }
        event.preventDefault();
        loadDocumentWorkspace(link.href, true);
    });

    document.addEventListener('submit', function (event) {
        var form = event.target;
        if (!form.classList.contains('drive-search')) {
            return;
        }
        event.preventDefault();
        var query = new URLSearchParams(new FormData(form)).toString();
        loadDocumentWorkspace(form.action + (query ? '?' + query : ''), true);
    });

    window.addEventListener('popstate', function () {
        loadDocumentWorkspace(window.location.href, false);
    });
}());
