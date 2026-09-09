/* =========================================================
   DRIVE-STYLE LIVE UPLOAD TOASTER
   Merge into assets/js/rms-documents.js
   ========================================================= */

/* Add near the existing upload variables: */
var uploadToastState = {
    element: null,
    total: 0,
    collapsed: false
};

function removeUploadProgressToast() {
    if (uploadToastState.element && uploadToastState.element.parentNode) {
        uploadToastState.element.parentNode.removeChild(uploadToastState.element);
    }

    uploadToastState.element = null;
    uploadToastState.total = 0;
    uploadToastState.collapsed = false;
}

function showUploadProgressToast(totalItems) {
    removeUploadProgressToast();

    var container = document.createElement('section');
    var header = document.createElement('div');
    var title = document.createElement('strong');
    var actions = document.createElement('span');
    var collapse = document.createElement('button');
    var close = document.createElement('button');
    var body = document.createElement('div');
    var status = document.createElement('div');
    var uploadIcon = document.createElement('span');
    var statusText = document.createElement('span');
    var spinner = document.createElement('span');
    var progressTrack = document.createElement('span');
    var progressBar = document.createElement('span');

    uploadToastState.total = Math.max(1, Number(totalItems) || 1);

    container.className = 'rms-upload-toast is-visible';
    container.setAttribute('role', 'status');
    container.setAttribute('aria-live', 'polite');

    header.className = 'rms-upload-toast-header';

    title.className = 'rms-upload-toast-title';
    title.textContent =
        'Uploading ' +
        uploadToastState.total +
        (uploadToastState.total === 1 ? ' item' : ' items');

    actions.className = 'rms-upload-toast-actions';

    collapse.type = 'button';
    collapse.className = 'rms-upload-toast-collapse';
    collapse.setAttribute('aria-label', 'Collapse upload progress');
    collapse.textContent = '⌄';

    close.type = 'button';
    close.className = 'rms-upload-toast-close';
    close.setAttribute('aria-label', 'Hide upload progress');
    close.textContent = '×';

    body.className = 'rms-upload-toast-body';
    status.className = 'rms-upload-toast-status';

    uploadIcon.className = 'rms-upload-toast-upload-icon';
    uploadIcon.textContent = '⇧';

    statusText.className = 'rms-upload-toast-status-text';
    statusText.textContent =
        'Uploading items 0 of ' + uploadToastState.total;

    spinner.className = 'rms-upload-toast-spinner';
    spinner.setAttribute('aria-hidden', 'true');

    progressTrack.className = 'rms-upload-toast-track';
    progressBar.className = 'rms-upload-toast-bar';
    progressTrack.appendChild(progressBar);

    status.appendChild(uploadIcon);
    status.appendChild(statusText);
    status.appendChild(spinner);

    body.appendChild(status);
    body.appendChild(progressTrack);

    actions.appendChild(collapse);
    actions.appendChild(close);

    header.appendChild(title);
    header.appendChild(actions);

    container.appendChild(header);
    container.appendChild(body);

    document.body.appendChild(container);

    collapse.addEventListener('click', function () {
        uploadToastState.collapsed = !uploadToastState.collapsed;

        container.classList.toggle(
            'is-collapsed',
            uploadToastState.collapsed
        );

        collapse.textContent =
            uploadToastState.collapsed ? '⌃' : '⌄';

        collapse.setAttribute(
            'aria-label',
            uploadToastState.collapsed
                ? 'Expand upload progress'
                : 'Collapse upload progress'
        );
    });

    close.addEventListener('click', function () {
        removeUploadProgressToast();
    });

    uploadToastState.element = container;
}

function updateUploadProgressToast(percent) {
    var toast = uploadToastState.element;

    if (!toast) {
        return;
    }

    var total = uploadToastState.total;
    var current = Math.min(
        total,
        Math.floor(total * percent / 100)
    );

    if (percent > 0 && current === 0) {
        current = 1;
    }

    var text = toast.querySelector(
        '.rms-upload-toast-status-text'
    );

    var bar = toast.querySelector(
        '.rms-upload-toast-bar'
    );

    if (text) {
        text.textContent =
            percent >= 100
                ? 'Finishing upload...'
                : 'Uploading items ' +
                  current +
                  ' of ' +
                  total;
    }

    if (bar) {
        bar.style.width = percent + '%';
    }
}

/* Add this inside your existing setUploadProgress(percent): */
updateUploadProgressToast(percent);

/* Add this immediately before the upload begins: */
showUploadProgressToast(
    originalFiles.length + watermarkFiles.length
);
