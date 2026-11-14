<?php

/**
 * Inline JavaScript
 */

namespace wishthis;

global $options;
?>

<script type="text/javascript">
    var wishthis = {};

    /** General */
    wishthis.version = '<?= VERSION ?>';
    wishthis.locale  = '<?= \str_replace('_', '-', $this->language) ?>';
    wishthis.$_GET   = JSON.parse('<?= isset($_GET) ? \json_encode($_GET) : \json_encode([]) ?>');

    /** Wish */
    wishthis.wish = {
        'no_image' : '<?= Wish::NO_IMAGE ?>',
        'status'   : {
            'unavailable' : '<?= Wish::STATUS_UNAVAILABLE ?>',
            'fulfilled'   : '<?= Wish::STATUS_FULFILLED ?>',
        }
    }

    /** Strings */
    wishthis.strings = {
        'modal' : {
            'error' : {
                'title'   : '<?= __('Error') ?>',
                'content' : '<?= __('Something went wrong, that\'s all I know.') ?>',
                'approve' : '<?= __('Thanks for nothing') ?>',
            },
            'failure' : {
                'title'   : '<?= __('Unexpected output') ?>',
                'content' : [
                    '<?= __('Your request likely succeeded but the server did not confirm that the action was successful.') ?>',
                    '<?= __('For nerds: The API returned HTTP 200, there was no (error) output, yet the response is still not as expected.') ?>',
                    '<?= __('In any case, please report this.') ?>',
                ],
                'approve' : '<?= __('Ok then') ?>',
            },
            'success' : {
                'title' : '<?= __('Success') ?>',
            },

            'wishlist' : {
                'warning' : {
                    'approve' : '<?= __('Close this tab') ?>',
                    'deny'    : '<?= __('Show wishlist anyway') ?>',
                },
                'delete' : {
                    'title'   : '<?= __('Really delete?') ?>',
                    'content' : '<?= \sprintf(__('Do you really want to delete the wishlist %s?'), '<strong>WISHLIST_NAME</strong>') ?>',
                    'approve' : '<?= __('Yes, delete') ?>',
                    'deny'    : '<?= __('No, keep') ?>',
                },
            },

            'wish' : {
                'delete' : {
                    'title'   : '<?= __('Really delete?') ?>',
                    'content' : '<?= __('Would you really like to delete this wish? You can undo this for an hour.') ?>',
                    'approve' : '<?= __('Yes, delete') ?>',
                    'deny'    : '<?= __('No, keep') ?>',
                },
                'delete_received' : {
                    'content' : '<?= __('Would you really like to delete all received wishes on this list? You can restore them for an hour with Undo a mistake. The people who gave them to you keep them in their gifts.') ?>',
                },
            }
        },

        'form' : {
            'profile' : {
                'password' : '<?= __('Passwords must match.') ?>',
            },
            'prompt' : {
                'empty'                : '<?= __('{name} must have a value') ?>',
                'checked'              : '<?= __('{name} must be checked') ?>',
                'email'                : '<?= __('{name} must be a valid e-mail') ?>',
                'url'                  : '<?= __('{name} must be a valid URL') ?>',
                'regExp'               : '<?= __('{name} is not formatted correctly') ?>',
                'integer'              : '<?= __('{name} must be an integer') ?>',
                'decimal'              : '<?= __('{name} must be a decimal number') ?>',
                'number'               : '<?= __('{name} must be set to a number') ?>',
                'is'                   : '<?= __('{name} must be "{ruleValue}"') ?>',
                'isExactly'            : '<?= __('{name} must be exactly "{ruleValue}"') ?>',
                'not'                  : '<?= __('{name} cannot be set to "{ruleValue}"') ?>',
                'notExactly'           : '<?= __('{name} cannot be set to exactly "{ruleValue}"') ?>',
                'contain'              : '<?= __('{name} cannot contain "{ruleValue}"') ?>',
                'containExactly'       : '<?= __('{name} cannot contain exactly "{ruleValue}"') ?>',
                'doesntContain'        : '<?= __('{name} must contain "{ruleValue}"') ?>',
                'doesntContainExactly' : '<?= __('{name} must contain exactly "{ruleValue}"') ?>',
                'minLength'            : '<?= __('{name} must be at least {ruleValue} characters') ?>',
                'length'               : '<?= __('{name} must be at least {ruleValue} characters') ?>',
                'exactLength'          : '<?= __('{name} must be exactly {ruleValue} characters') ?>',
                'maxLength'            : '<?= __('{name} cannot be longer than {ruleValue} characters') ?>',
                'match'                : '<?= __('{name} must match {ruleValue} field') ?>',
                'different'            : '<?= __('{name} must have a different value than {ruleValue} field') ?>',
                'creditCard'           : '<?= __('{name} must be a valid credit card number') ?>',
                'minCount'             : '<?= __('{name} must have at least {ruleValue} choices') ?>',
                'exactCount'           : '<?= __('{name} must have exactly {ruleValue} choices') ?>',
                'maxCount'             : '<?= __('{name} must have {ruleValue} or less choices') ?>',
            }
        },

        'toast' : {
            'wishlist' : {
                'rename' : '<?= __('Wishlist successfully renamed.') ?>',
                'delete' : '<?= __('Wishlist successfully deleted.') ?>',
            },

            'wish' : {
                'create'    : '<?= __('Wish successfully created.') ?>',
                'update'    : '<?= __('Wish information updated.') ?>',
                'delete'    : '<?= __('Wish successfully deleted.') ?>',
                'delete_received' : '<?= __('The received wishes have been deleted.') ?>',
                'available' : '<?= __('The wish is available again.') ?>',
                'undo'      : '<?= __('Undo') ?>',
                'restore'   : '<?= __('The wish has been restored.') ?>',
                'blocked'   : {
                    'title'   : '<?= __('Details not filled in') ?>',
                    'content' : '<?= __('This shop did not let wishthis read the page. Please add the title and image yourself. Tip: right-click the product image and copy its address.') ?>',
                },
                'missing'   : '<?= __('Not found automatically: {fields}.') ?>',
            },

            'gift' : {
                'title'         : '<?= __('Thank you!') ?>',
                'content'       : '<?= __('The wish is now unavailable for everybody else. You can find it in My gifts.') ?>',
                'content_login' : '<?= __('The wish is now unavailable for everybody else. Log in to keep track of it in My gifts.') ?>',
                'view'          : '<?= __('My gifts') ?>',
                'gone'          : '<?= __('The owner removed this wish, so it was taken off your gifts.') ?>',
            },

            'clipboard' : {
                'error' : {
                    'title'   : '<?= __('Error') ?>',
                    'content' : '<?= __('Unable to copy to clipboard. There is likely a permission issue.') ?>',
                },
                'success' : {
                    'content' : '<?= __('Link copied to clipboard.') ?>',
                },
            },
        },

        /** Looking up the details of a wish URL */
        'lookup' : {
            'loading'       : '<?= __('Looking up the product…') ?>',
            'found'         : {
                'title' : '<?= __('Found it!') ?>',
                'add'   : '<?= __('Check the details and press Add.') ?>',
                'edit'  : '<?= __('Check the details and press Save.') ?>',
            },
            /** Nothing could be read from the page */
            'blocked'       : {
                'title' : '<?= __('Fully blocked by the shop\'s site protection.') ?>',
                'add'   : '<?= __('You\'ll need to copy and paste the details, or just type them, then press Add.') ?>',
                'edit'  : '<?= __('You\'ll need to copy and paste the details, or just type them, then press Save.') ?>',
            },
            'nothing'       : '<?= __('Couldn\'t get any details from this page.') ?>',
            'reason'        : {
                'not_found'   : '<?= __('The link doesn\'t lead to a product page. Please check the link.') ?>',
                'unreachable' : '<?= __('The shop\'s website didn\'t answer. Try again later, or fill in the details yourself.') ?>',
                'error_page'  : '<?= __('The shop showed an error page instead of the product. Please fill in the details yourself.') ?>',
                'error'       : '<?= __('Something went wrong while looking it up. Please fill in the details yourself.') ?>',
            },
            /** Some details could not be found */
            'no_image'      : '<?= __('Couldn\'t find the product picture.') ?>',
            'image_error'   : '<?= __('Couldn\'t load the product picture.') ?>',
            'missing'       : '<?= __('Couldn\'t find the {fields}.') ?>',
            'fill_in'       : '<?= __('Please fill in what\'s missing yourself.') ?>',
            'title_guessed' : '<?= __('The title was guessed from the link – please check it.') ?>',
            'tip'           : '<?= __('You can try copying the picture\'s address from the shop: press and hold the picture (on a computer: right-click it), choose "Copy image address" and paste it into the Image field. This works on some shops, not all.') ?>',
            'currency'      : '<?= __('The price is in {currency}, so it wasn\'t filled in.') ?>',
            /** A picture the user added can't be shown */
            'image_note'    : '<?= __('This picture can\'t be shown. Please check the picture address.') ?>',
            /** Pressing Add with the title or picture still empty */
            'still_empty'   : '<?= __('Still empty: {fields}.') ?>',
            'anyway_hint'   : '<?= __('Press "Add anyway" to continue without it.') ?>',
            'add_anyway'    : '<?= __('Add anyway') ?>',
            'and'           : '<?= __('and') ?>',
            'fields'        : {
                'title'       : '<?= __('title') ?>',
                'image'       : '<?= __('picture') ?>',
                'description' : '<?= __('description') ?>',
                'price'       : '<?= __('price') ?>',
            },
        },

        /** The wish title field is full */
        'title_limit' : '<?= __('The title is full. Symbols such as & and quotation marks take up several characters.') ?>',

        /** A name field (wishlist, first/last/nick name) is full */
        'name_limit' : '<?= __('The name is full. Symbols such as & and quotation marks take up several characters.') ?>',

        'calendar' : {
            'today'   : '<?= _x('Today', 'Calendar') ?>',
            'now'     : '<?= _x('Now', 'Calendar') ?>',
            'am'      : '<?= _x('AM', 'Calendar') ?>',
            'pm'      : '<?= _x('PM', 'Calendar') ?>',
            'week_no' : '<?= _x('Week', 'Calendar') ?>',
        },

        'button' : {
            'wishlist' : {
                'remember' : '<?= __('Remember list') ?>',
                'forget'   : '<?= __('Forget list') ?>',
            }
        },

        'message' : {
            'wishlist' : {
                'empty' : {
                    'header'  : '<?= __('Empty') ?>',
                    'content' : '<?= __('This wishlist seems to be empty.') ?>',
                }
            }
        },

        'popup' : {
            'last_modified' : '<?= __('Last modified') ?>',
        }
    }

    /**
     * Fomantic UI
     */
    <?php
    $api_urls = [
        'get wishlists'               => '/index.php?page=api&module=wishlists',
        'get wishes by wishlist id'   => '/index.php?page=api&module=wishlists&style={style}&priority={priority}&wishlist_id={wishlistid}',
        'get wishes by wishlist hash' => '/index.php?page=api&module=wishlists&style={style}&priority={priority}&wishlist_hash={wishlisthash}',
        'delete wishlist'             => '/index.php?page=api&module=wishlists',
        'delete wish'                 => '/index.php?page=api&module=wishes&wish_id={wishid}',
    ];
    ?>

    /**
     * API URLs for Fomantic UI's `action` setting. `default.js` hands them to
     * `$.fn.api.settings.api` as soon as Fomantic UI has loaded. Waiting for
     * `DOMContentLoaded` is too late: jQuery can run the pages' ready handlers
     * before that event, and an `.api()` set up without its URL never sends
     * a request (e.g. a wishlist that shows no wishes).
     */
    wishthis.api = {
        <?php
        /** `new URL()` replaces `$_GET`, which the page still needs after the header. */
        $requestGET = $_GET;

        foreach ($api_urls as $action => $url) {
            $url    = new URL($url);
            $pretty = $url->getPretty();

            echo '\'' . $action . '\' : \'' . $pretty . '\',' . PHP_EOL;
        }

        $_GET = $requestGET;
        ?>
    };

</script>
