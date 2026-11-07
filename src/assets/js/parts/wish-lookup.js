/**
 * Looks up the details of a wish URL while the wish form is being filled in,
 * fills in the empty fields and tells the user what could not be found and
 * why.
 */

const wish_lookup_forms = ['.ui.form.wishlist-wish-add', '.ui.form.wishlist-wish-edit'];

/**
 * Returns the selector of a field in the wish forms.
 */
function getWishLookupField(name) {
    return wish_lookup_forms.map(form => form + ' [name="' + name + '"]').join(', ');
}

/**
 * Whether the text looks like a complete web address.
 */
function isWishURL(url) {
    return /^https?:\/\/[^\s/.]+\.[^\s]+$/i.test(url);
}

/**
 * Remembers the URL a form was opened with, so it is not looked up until the
 * user changes it.
 */
function rememberWishURL(form) {
    form.data('wishLookupURL', form.find('[name="wish_url"]').val().trim());
    form.data('wishLookup', Promise.resolve(null));
}

/**
 * Forgets the last lookup, e. g. when the add form is opened again.
 */
function resetWishLookup(form) {
    form.removeData(['wishLookupURL', 'wishLookup', 'wishLookupResult', 'wishLookupImage', 'wishImageFailed', 'wishAddAnyway']);
    form.find('.field.warning').removeClass('warning');
    form.find('.message.wish-lookup').hide().empty();
    form.find('.wish-image-preview').hide().removeAttr('src');

    updateWishTitleLength(form);
}

/**
 * Shows how long the title is once it gets near the limit, and says so when
 * it is full: the browser silently ignores typing in a full field.
 */
function updateWishTitleLength(form) {
    var input  = form.find('[name="wish_title"]');
    var hint   = form.find('.wish-title-length');
    var max    = parseInt(input.attr('maxlength'), 10);
    var length = input.val().length;

    if (!max || length < max - 28) {
        hint.hide().removeClass('at-limit').empty();

        return;
    }

    var isFull = length >= max;

    hint
    .toggleClass('at-limit', isFull)
    .text(length + ' / ' + max + (isFull ? ' – ' + wishthis.strings.title_limit : ''))
    .show();
}

/**
 * Restores the label of the add button after it said "Add anyway".
 */
function resetWishAddButton(modal) {
    var button = modal.find('.actions .approve.button');

    if (undefined === button.data('label')) {
        button.data('label', button.text().trim());
    }

    button.text(button.data('label'));
}

/**
 * Looks up the URL in the form, unless it has already been looked up.
 *
 * @returns {Promise} Resolves with the API response (`info` and `details`),
 *                    or `null` if there was nothing to look up.
 */
function lookUpWishURL(form) {
    var url = form.find('[name="wish_url"]').val().trim();

    if (url === form.data('wishLookupURL')) {
        return form.data('wishLookup');
    }

    form.data('wishLookupURL', url);
    form.removeData(['wishLookupResult', 'wishAddAnyway']);
    form.find('.field.warning').removeClass('warning');
    resetWishAddButton(form.closest('.modal'));

    if (!isWishURL(url)) {
        form.find('.message.wish-lookup').hide().empty();
        form.data('wishLookup', Promise.resolve(null));

        return form.data('wishLookup');
    }

    showWishLookupMessage(form, 'loading', '', wishthis.strings.lookup.loading);

    var params = new URLSearchParams(
        {
            'module'   : 'wishes',
            'page'     : 'api',
            'wish_url' : url,
        }
    );

    var lookup = fetch('/index.php?' + params, {
        'method'      : 'GET',
        'credentials' : 'include',
    })
    .then(function(response) {
        if (!response.ok) {
            throw Error(response.statusText);
        }

        return response.json();
    })
    .then(function(response) {
        /** A newer lookup has started in the meantime */
        if (url !== form.data('wishLookupURL')) {
            return response;
        }

        form.data('wishLookupResult', response);

        fillWishForm(form, response.details);
        showWishLookupResult(form);

        return response;
    })
    .catch(function(error) {
        console.log(error);

        if (url === form.data('wishLookupURL')) {
            form.data('wishLookupResult', null);

            showWishLookupMessage(
                form,
                'error',
                wishthis.strings.lookup.error.title,
                wishthis.strings.lookup.error.content
            );
        }

        return null;
    });

    form.data('wishLookup', lookup);

    return lookup;
}

/**
 * Fills the details into the fields the user has left empty.
 */
function fillWishForm(form, details) {
    ['title', 'description'].forEach(function(field) {
        var input = form.find('[name="wish_' + field + '"]');

        if (!input.val() && details[field]) {
            input.val(details[field]);
        }
    });

    updateWishTitleLength(form);

    var inputImage = form.find('[name="wish_image"]');

    if (!inputImage.val() && details.image) {
        inputImage.val(details.image);
        form.data('wishLookupImage', details.image);

        showWishImagePreview(form);
    }

    var inputPrice = form.find('[name="wish_price"]');

    if (!inputPrice.val() && details.price) {
        inputPrice.val(details.price);
        form.find('.checkbox.wish-is-purchasable').checkbox('check');
    }
}

/**
 * Returns the required fields (title, image) which are still empty.
 */
function getMissingWishFields(form) {
    return ['title', 'image'].filter(function(field) {
        return !form.find('[name="wish_' + field + '"]').val();
    });
}

/**
 * Returns the fields which the lookup could not find and which are still
 * empty.
 */
function getWishFieldsNotFound(form) {
    var result = form.data('wishLookupResult');

    if (!result || !result.details) {
        return [];
    }

    return ['title', 'image', 'description', 'price'].filter(function(field) {
        return result.details.missing.includes(field) && !form.find('[name="wish_' + field + '"]').val();
    });
}

/**
 * Says what the last lookup found and highlights what is still missing.
 */
function showWishLookupResult(form) {
    var result = form.data('wishLookupResult');

    if (!result || !result.details) {
        return;
    }

    var strings = wishthis.strings.lookup;
    var details = result.details;
    var missing = getMissingWishFields(form);
    var isAdd   = form.hasClass('wishlist-wish-add');

    form.find('.field.warning').removeClass('warning');

    missing.forEach(function(field) {
        form.find('[name="wish_' + field + '"]').closest('.field').addClass('warning');
    });

    /** What went wrong */
    var title   = '';
    var content = [];
    var notes   = [];

    if (details.priceCurrency) {
        notes.push(strings.currency.replace('{currency}', $('<div>').text(details.priceCurrency).html()));
    }

    if (details.problem && strings[details.problem]) {
        title = strings[details.problem].title;
        content.push(strings[details.problem].content);
    } else if ((details.imageError || form.data('wishImageFailed')) && missing.includes('image')) {
        title = strings.image_error;
    } else if (missing.includes('image')) {
        title = strings.no_image;
    } else if (missing.includes('title')) {
        title = strings.no_title;
    }

    /** What to do about it. A wrong link needs fixing first. */
    if ('not_found' !== details.problem) {
        if (missing.length > 1) {
            content.push(strings.add.title_image);
        } else if (missing.length === 1) {
            content.push(strings.add[missing[0]]);
        }

        if (missing.includes('image')) {
            content.push(strings.tip);
        }
    }

    if (missing.length > 0) {
        showWishLookupMessage(form, 'warning', title, content.concat(notes).join(' '));
    } else if (form.data('wishImageFailed')) {
        /** The picture was filled in, but can't be shown */
        form.find('[name="wish_image"]').closest('.field').addClass('warning');

        showWishLookupMessage(form, 'warning', strings.image_error, [strings.image_check].concat(notes).join(' '));
    } else {
        /** Everything there, either found or filled in by the user */
        showWishLookupMessage(
            form,
            'success',
            details.problem ? '' : strings.found.title,
            [isAdd ? strings.found.add : strings.found.edit].concat(notes).join(' ')
        );
    }
}

/**
 * Shows a message below the URL field.
 *
 * @param {string} type    `loading`, `success`, `warning` or `error`.
 * @param {string} title   HTML.
 * @param {string} content HTML.
 */
function showWishLookupMessage(form, type, title, content) {
    var types = {
        'loading' : { 'color' : 'blue',   'icon' : 'notched circle loading' },
        'success' : { 'color' : 'green',  'icon' : 'check circle' },
        'warning' : { 'color' : 'yellow', 'icon' : 'exclamation triangle' },
        'error'   : { 'color' : 'red',    'icon' : 'times circle' },
    };
    var message = form.find('.message.wish-lookup');

    message
    .removeClass('blue green yellow red')
    .addClass(types[type].color)
    .empty()
    .append($('<i class="icon">').addClass(types[type].icon))
    .append(
        $('<div class="content">')
        .append(title ? $('<div class="header">').html(title) : '')
        .append($('<p>').html(content))
    )
    .show();
}

/**
 * Shows the picture of the image field, so the user can see whether it is the
 * right one.
 */
function showWishImagePreview(form) {
    var url     = form.find('[name="wish_image"]').val().trim();
    var preview = form.find('.wish-image-preview');

    form.removeData('wishImageFailed');

    if (!isWishURL(url)) {
        preview.hide().removeAttr('src');

        return;
    }

    preview.attr('src', url).show();
}

$(function() {
    /**
     * Look up the URL once it has been pasted or typed. Not on `change`: that
     * happens when the save button is clicked, and the message appearing
     * would move the button away from under the pointer.
     */
    var lookupTimeout;

    $(document).on('paste', getWishLookupField('wish_url'), function() {
        var form = $(this).closest('.ui.form');

        /** The pasted text is only in the field after the event */
        setTimeout(function() {
            lookUpWishURL(form);
        });
    });

    $(document).on('input', getWishLookupField('wish_url'), function() {
        var form = $(this).closest('.ui.form');

        clearTimeout(lookupTimeout);
        lookupTimeout = setTimeout(function() {
            lookUpWishURL(form);
        }, 1000);
    });

    /** Image */
    $(document).on('input', getWishLookupField('wish_image'), function() {
        var form = $(this).closest('.ui.form');

        showWishImagePreview(form);

        if (form.data('wishLookupResult')) {
            showWishLookupResult(form);
        }
    });

    /** A picture which can't be shown is no use on the wish either */
    document.addEventListener('error', function(event) {
        if (!event.target.matches || !event.target.matches('.wish-image-preview')) {
            return;
        }

        var preview    = $(event.target);
        var form       = preview.closest('.ui.form');
        var inputImage = form.find('[name="wish_image"]');

        preview.hide();
        form.data('wishImageFailed', true);

        /** A picture found automatically is simply left out */
        if (inputImage.val() === form.data('wishLookupImage')) {
            inputImage.val('');
        }

        if (form.data('wishLookupResult')) {
            showWishLookupResult(form);
        } else {
            inputImage.closest('.field').addClass('warning');
        }
    }, true);

    /** Clear the highlight once a missing field has been filled in */
    $(document).on('input', getWishLookupField('wish_title'), function() {
        var form = $(this).closest('.ui.form');

        updateWishTitleLength(form);

        if (form.data('wishLookupResult')) {
            showWishLookupResult(form);
        }
    });
});
