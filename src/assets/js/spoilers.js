$(function () {

    /**
     * Removes a table row and shows the empty state once the table is empty.
     */
    function removeRow(row) {
        row.fadeOut(400, function () {
            var table = row.closest('table');

            row.remove();

            if (0 === table.find('tbody tr').length) {
                table.remove();
                $('.spoilers-empty').removeAttr('hidden');
            }
        });
    }

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

            removeRow(row);
        })
        .catch(function (error) {
            button.prop('disabled', false);

            handleFetchCatch(error);
        });
    });

    /**
     * Restore
     *
     * Puts a recently deleted wish back on its wishlist.
     */
    $(document).on('click', '.restore-wish', function () {
        var button = $(this);

        button.addClass('disabled loading');

        wishRestore(button.attr('data-wish-id'))
        .then(function () {
            removeRow(button.closest('tr'));
        })
        .catch(function (error) {
            button.removeClass('disabled loading');

            handleFetchCatch(error);
        });
    });

    /**
     * Delete received
     *
     * Deletes wishes which left the list after the owners birthday. They can
     * be restored for an hour, the givers keep them in their gifts.
     */
    function deleteWish(wishId) {
        return fetch('/index.php?page=api&module=wishes', {
            'method'      : 'DELETE',
            'credentials' : 'include',
            'body'        : new URLSearchParams({
                'wish_id' : wishId,
            }),
        })
        .then(handleFetchError)
        .then(handleFetchResponse);
    }

    function confirmDelete(content, onApprove) {
        $('body')
        .modal({
            'title'     : wishthis.strings.modal.wish.delete.title,
            'content'   : content,
            'class'     : 'tiny',
            'actions'   : [
                {
                    'text'  : wishthis.strings.modal.wish.delete.approve,
                    'class' : 'approve primary'
                },
                {
                    'text' : wishthis.strings.modal.wish.delete.deny
                }
            ],
            'onApprove' : function (buttonApprove) {
                var modal = buttonApprove.closest('.ui.modal');

                buttonApprove.addClass('loading');

                onApprove()
                .catch(handleFetchCatch)
                .finally(function () {
                    modal.modal('hide');
                });

                return false;
            }
        })
        .modal('show');
    }

    function hideReceivedMessage() {
        if (0 === $('.delete-received').length) {
            $('.spoilers-received').fadeOut(400);
        }
    }

    $(document).on('click', '.delete-received', function () {
        var button = $(this);
        var row    = button.closest('tr');
        var wishId = button.attr('data-wish-id');

        confirmDelete(wishthis.strings.modal.wish.delete.content, function () {
            return deleteWish(wishId)
            .then(function () {
                $('body').toast({ message: wishthis.strings.toast.wish.delete });

                button.remove();
                removeRow(row);
                hideReceivedMessage();
            });
        });
    });

    $(document).on('click', '.delete-received-all', function () {
        confirmDelete(wishthis.strings.modal.wish.delete_received.content, function () {
            var buttons = $('.delete-received').toArray();

            /** One after the other, so a failure stops the rest. */
            return buttons.reduce(function (previous, element) {
                var button = $(element);

                return previous.then(function () {
                    return deleteWish(button.attr('data-wish-id'))
                    .then(function () {
                        var row = button.closest('tr');

                        button.remove();
                        removeRow(row);
                    });
                });
            }, Promise.resolve())
            .then(function () {
                $('body').toast({ message: wishthis.strings.toast.wish.delete_received });
            })
            .finally(hideReceivedMessage);
        });
    });

    /**
     * When
     *
     * Shows how long ago a change was made, e.g. "12 minutes ago".
     */
    var relativeTime = new Intl.RelativeTimeFormat(wishthis.locale, { 'numeric' : 'auto' });

    $('.minutes-ago').each(function () {
        var minutes = parseInt($(this).attr('data-minutes-ago'), 10) || 0;

        $(this).text(relativeTime.format(-minutes, 'minute'));
    });

});
