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
    form.removeData(['wishLookupURL', 'wishLookup', 'wishLookupResult', 'wishLookupImage', 'wishLookupTitle', 'wishLookupDone', 'wishLookupHidden', 'wishImageFailed', 'wishAddAnyway']);
    form.find('.field.warning').removeClass('warning');
    form.find('.message.wish-lookup').hide().empty();
    form.find('.wish-image-preview').hide().removeAttr('src');
    form.find('.wish-image-note').hide().empty();

    updateWishTitleLength(form);
}

/**
 * Returns how many characters a title takes up in the database, see
 * `getStoredTextLength()` in `default.js`. Counts the same way as
 * `Wish::getStoredTitleLength()`.
 */
function getWishTitleStoredLength(title) {
    return getStoredTextLength(title, true);
}

/**
 * Keeps the title within the limit as stored, see `limitStoredLength()` in
 * `default.js`.
 *
 * @returns {boolean} Whether some of the input was left out.
 */
function limitWishTitle(input) {
    return limitStoredLength(
        input,
        parseInt(input.attr('data-max-length'), 10),
        input.data('wishTitleBefore'),
        getWishTitleStoredLength
    );
}

/**
 * Shows how long the title is (as stored) once it gets near the limit, and
 * says so when it is full or something didn't fit: otherwise typing would
 * seem to do nothing.
 */
function updateWishTitleLength(form, wasLimited) {
    var input  = form.find('[name="wish_title"]');
    var hint   = form.find('.wish-title-length');
    var max    = parseInt(input.attr('data-max-length'), 10);
    var length = getWishTitleStoredLength(input.val());

    /** For `limitWishTitle()`, also after the value was set by a script */
    input.data('wishTitleBefore', input.val());

    if (!max || length < max - 28) {
        hint.hide().removeClass('at-limit').empty();

        return;
    }

    var isFull = length >= max || true === wasLimited;

    /** The string is HTML */
    hint
    .toggleClass('at-limit', isFull)
    .html(length + ' / ' + max + (isFull ? ' – ' + wishthis.strings.title_limit : ''))
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
    form.removeData(['wishLookupResult', 'wishLookupImage', 'wishLookupTitle', 'wishLookupDone', 'wishLookupHidden', 'wishAddAnyway']);
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
                wishthis.strings.lookup.nothing,
                wishthis.strings.lookup.reason.error
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

            /** Only a title filled in here is worth a note that it was guessed */
            if ('title' === field && details.titleGuessed) {
                form.data('wishLookupTitle', details[field]);
            }
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
 * Highlights the required fields (title, picture) which are still empty.
 */
function highlightMissingWishFields(form) {
    form.find('.field.warning').removeClass('warning');

    getMissingWishFields(form).forEach(function(field) {
        form.find('[name="wish_' + field + '"]').closest('.field').addClass('warning');
    });
}

/**
 * Turns field names into a list for a sentence, e.g. "title, description
 * and picture".
 */
function joinWishFields(fields) {
    var names = fields.map(field => wishthis.strings.lookup.fields[field]);

    if (names.length < 2) {
        return names.join('');
    }

    return names.slice(0, -1).join(', ') + ' ' + wishthis.strings.lookup.and + ' ' + names[names.length - 1];
}

/**
 * Says what the lookup found and what it couldn't. This is worked out once,
 * when the lookup is done; the message goes away as soon as the user edits
 * the details (see `hideWishLookupResult()`).
 */
function showWishLookupResult(form) {
    var result = form.data('wishLookupResult');

    if (!result || !result.details) {
        return;
    }

    var strings  = wishthis.strings.lookup;
    var details  = result.details;
    var isAdd    = form.hasClass('wishlist-wish-add');
    var hasValue = field => '' !== form.find('[name="wish_' + field + '"]').val().trim();
    var notes    = [];

    form.data('wishLookupDone', true);
    highlightMissingWishFields(form);

    if (form.data('wishLookupTitle') && form.find('[name="wish_title"]').val() === form.data('wishLookupTitle')) {
        notes.push(strings.title_guessed);
    }

    var tip = hasValue('image') ? [] : [strings.tip];

    if (details.priceCurrency) {
        tip.push(strings.currency.replace('{currency}', $('<div>').text(details.priceCurrency).html()));
    }

    /** The page couldn't be read at all */
    if (details.problem === 'blocked') {
        showWishLookupMessage(form, 'warning', strings.blocked.title, [isAdd ? strings.blocked.add : strings.blocked.edit].concat(notes, tip).join(' '));

        return;
    }

    if (details.problem) {
        /** A wrong link needs fixing first */
        if ('not_found' === details.problem) {
            tip = [];
        }

        showWishLookupMessage(form, 'warning', strings.nothing, [strings.reason[details.problem] || strings.reason.error].concat(notes, tip).join(' '));

        return;
    }

    /** Only what couldn't be found and is still empty */
    var imageFailed = details.imageError || form.data('wishImageFailed');
    var failed      = ['title', 'description', 'image'].filter(function(field) {
        var notFound = details.missing.includes(field) || ('image' === field && imageFailed);

        return notFound && !hasValue(field);
    });

    if (0 === failed.length) {
        if (notes.length > 0) {
            showWishLookupMessage(form, 'warning', strings.title_guessed, [isAdd ? strings.found.add : strings.found.edit].concat(tip).join(' '));
        } else {
            showWishLookupMessage(form, 'success', strings.found.title, [isAdd ? strings.found.add : strings.found.edit].concat(tip).join(' '));
        }

        return;
    }

    /** The picture message only when the picture is all that's missing */
    if (1 === failed.length && 'image' === failed[0]) {
        showWishLookupMessage(form, 'warning', imageFailed ? strings.image_error : strings.no_image, notes.concat(tip).join(' '));

        return;
    }

    showWishLookupMessage(
        form,
        'warning',
        strings.missing.replace('{fields}', joinWishFields(failed)),
        [strings.fill_in].concat(notes, tip).join(' ')
    );
}

/**
 * The user has started editing the details: they know what the lookup found,
 * so its message goes away and isn't worked out again.
 */
function hideWishLookupResult(form) {
    if (!form.data('wishLookupDone')) {
        return;
    }

    form.data('wishLookupHidden', true);
    form.find('.message.wish-lookup').hide().empty();
}

/**
 * Asks to fill in the title or picture before adding, the first time Add is
 * pressed without them.
 */
function showWishMissingPrompt(form, missing) {
    var strings = wishthis.strings.lookup;

    highlightMissingWishFields(form);

    showWishLookupMessage(
        form,
        'warning',
        strings.still_empty.replace('{fields}', joinWishFields(missing)),
        strings.anyway_hint
    );
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
    form.find('.wish-image-note').hide().empty();

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

    /**
     * Editing the details. The lookup's message goes away, and the highlight
     * of a missing field once it has been filled in.
     */
    $(document).on('input', ['wish_description', 'wish_price', 'wish_image'].map(getWishLookupField).join(', '), function() {
        var input = $(this);
        var form  = input.closest('.ui.form');

        if ('wish_image' === input.attr('name')) {
            showWishImagePreview(form);
        }

        if (input.val().trim()) {
            input.closest('.field').removeClass('warning');
        }

        hideWishLookupResult(form);
    });

    /** A picture which can't be shown is no use on the wish either */
    document.addEventListener('error', function(event) {
        if (!event.target.matches || !event.target.matches('.wish-image-preview')) {
            return;
        }

        var preview    = $(event.target);
        var form       = preview.closest('.ui.form');
        var inputImage = form.find('[name="wish_image"]');
        var isFound    = form.data('wishLookupImage') && inputImage.val() === form.data('wishLookupImage');

        preview.hide();
        form.data('wishImageFailed', true);

        /**
         * A picture found automatically is simply left out. While the
         * lookup's message is still showing, it now says so.
         */
        if (isFound) {
            inputImage.val('');

            if (form.data('wishLookupDone') && !form.data('wishLookupHidden')) {
                showWishLookupResult(form);

                return;
            }
        }

        inputImage.closest('.field').addClass('warning');
        form.find('.wish-image-note')
        .html(isFound ? wishthis.strings.lookup.image_error : wishthis.strings.lookup.image_note)
        .show();
    }, true);

    /** Title */
    $(document).on('input compositionend', getWishLookupField('wish_title'), function(event) {
        var input = $(this);
        var form  = input.closest('.ui.form');

        /** Changing the value would break off typing with an input method */
        if (event.originalEvent && event.originalEvent.isComposing) {
            return;
        }

        updateWishTitleLength(form, limitWishTitle(input));

        if (input.val().trim()) {
            input.closest('.field').removeClass('warning');
        }

        hideWishLookupResult(form);
    });
});
