$(function () {

    /**
     * Undo
     *
     * Makes a wish fulfilled by mistake available again.
     */
    $(document).on('click', '.gift-undo', function () {
        var button = $(this);
        var row    = button.closest('tr');

        button.addClass('disabled loading');

        giftUndo(button.attr('data-gift-id'))
        .then(function () {
            row.fadeOut(400, function () {
                var table = row.closest('table');

                row.remove();

                if (0 === table.find('tbody tr').length) {
                    table.remove();
                    $('.gifts-empty').removeAttr('hidden');
                }
            });
        })
        .catch(function (error) {
            button.removeClass('disabled loading');

            handleFetchCatch(error);
        });
    });

    /**
     * When
     *
     * Shows how long ago a wish was fulfilled, e.g. "12 minutes ago".
     */
    var relativeTime = new Intl.RelativeTimeFormat(wishthis.locale, { 'numeric' : 'auto' });

    $('.minutes-ago').each(function () {
        var minutes = parseInt($(this).attr('data-minutes-ago'), 10) || 0;

        $(this).text(relativeTime.format(-minutes, 'minute'));
    });

});
