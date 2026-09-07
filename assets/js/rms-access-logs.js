/* global window, jQuery */
(function ($) {
    'use strict';

    var config = window.RMS_ACCESS_LOGS || {};
    var page = 1;

    /** Filter and paginate without reloading the page. */
    function render() {
        var query = $.trim($('#access-log-search').val()).toLowerCase();
        var limit = parseInt($('#access-log-limit').val(), 10) || 10;
        var matches = $('#access-log-table tr[data-log-row]').filter(function () {
            return $(this).text().toLowerCase().indexOf(query) !== -1;
        });
        var pages = Math.max(1, Math.ceil(matches.length / limit));
        page = Math.min(page, pages);

        $('#access-log-table tr[data-log-row]').attr('hidden', 'hidden');
        matches.slice((page - 1) * limit, page * limit).removeAttr('hidden');

        var start = matches.length ? ((page - 1) * limit) + 1 : 0;
        var end = Math.min(page * limit, matches.length);
        $('#access-log-range').text('Showing ' + start + '–' + end + ' of ' + matches.length + ' entries');
        $('#access-log-page').text('Page ' + page + ' of ' + pages);
        $('#access-log-prev').prop('disabled', page <= 1);
        $('#access-log-next').prop('disabled', page >= pages);
        $('#access-log-empty').prop('hidden', matches.length !== 0);
    }

    $('#access-log-search').on('input', function () { page = 1; render(); });
    $('#access-log-limit').on('change', function () { page = 1; render(); });
    $('#access-log-prev').on('click', function () { if (page > 1) { page -= 1; render(); } });
    $('#access-log-next').on('click', function () { page += 1; render(); });

    // Backdrop clicks and Escape intentionally have no close handler.
    $('#access-log-clear').on('click', function () {
        $('#access-log-confirm').removeAttr('hidden');
        $('body').addClass('modal-open');
    });
    $('#access-log-cancel').on('click', function () {
        $('#access-log-confirm').attr('hidden', 'hidden');
        $('body').removeClass('modal-open');
    });
    $('#access-log-confirm-delete').on('click', function () {
        var $button = $(this).prop('disabled', true).text('Deleting...');
        var data = {};
        data[config.csrfName] = config.csrfHash;
        $.ajax({url: config.clear, method: 'POST', data: data, dataType: 'json'})
            .done(function (response) {
                if (response.csrfHash) { config.csrfHash = response.csrfHash; }
                if (response.success) { window.location.reload(); return; }
                $('#access-log-toast').addClass('error').text(response.message).removeAttr('hidden');
                $button.prop('disabled', false).text('Delete all');
            })
            .fail(function () {
                $('#access-log-toast').addClass('error').text('The logs could not be deleted.').removeAttr('hidden');
                $button.prop('disabled', false).text('Delete all');
            });
    });

    render();
}(jQuery));
