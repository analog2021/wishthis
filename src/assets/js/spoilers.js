$(function () {

    /**
     * Still need it
     *
     * Makes a fulfilled wish available again.
     */
    var lastPointerType = 'mouse';

    $(document).on('pointerdown', '.still-need-it', function (event) {
        lastPointerType = event.originalEvent.pointerType;
    });

    $(document).on('click', '.still-need-it', function () {
        var button = $(this);
        var row    = button.closest('tr');

        /** Touch screens can't hover, so the first tap only reveals "Still need it". */
        if ('touch' === lastPointerType && !button.hasClass('armed')) {
            button.addClass('armed');

            return;
        }

        button.prop('disabled', true);

        fetch('/index.php?page=api&module=wishes', {
            'method'      : 'PUT',
            'credentials' : 'include',
            'body'        : new URLSearchParams({
                'wish_id'     : button.attr('data-wish-id'),
                'wish_status' : '',
            }),
        })
        .then(handleFetchError)
        .then(handleFetchResponse)
        .then(function (response) {
            $('body').toast({ message: wishthis.strings.toast.wish.available });

            row.fadeOut(400, function () {
                var table = row.closest('table');

                row.remove();

                if (0 === table.find('tbody tr').length) {
                    table.remove();
                    $('.spoilers-empty').removeAttr('hidden');
                }
            });
        })
        .catch(function (error) {
            button.prop('disabled', false);

            handleFetchCatch(error);
        });
    });

});
