<?php

/**
 * Wishes
 *
 * @category API
 */

namespace wishthis;

global $page, $database;

switch ($_SERVER['REQUEST_METHOD']) {
    case 'GET':
        $getWish = isset($_GET['wish_id']);

        if ($getWish) {
            $wish = Wish::getFromId((int) $_GET['wish_id']);

            if (false === $wish) {
                \http_response_code(404);

                die(__('The requested Wish was not found.'));
            }

            /**
             * Only the wishlist owner may see the status of a wish or wishes
             * which have been fulfilled or claimed.
             */
            $userOwnsWish = User::getCurrent()->ownsWishlist($wish->getWishlistId());

            if (!$userOwnsWish && $wish->isHiddenFromGuests()) {
                \http_response_code(404);

                die(__('The requested Wish was not found.'));
            }

            $response['info'] = $wish->serialise();

            if (!$userOwnsWish) {
                unset($response['info']['status']);
            }

            if (isset($_GET['wishlist_user'])) {
                $response['html'] = $wish->getCard($_GET['wishlist_user']);
            }
        } elseif (!empty($_GET['wish_url'])) {
            if (isset($_GET['isAffiliate'])) {
                $wishUrl = \base64_decode($_GET['wish_url']);

                $response['isAffiliate'] = Wish::hasAffiliateLink($wishUrl);
            } else {
                $url   = trim($_GET['wish_url']);
                $cache = new Cache\Embed($url);
                $info  = $cache->get($cache->generateCache());
                $user  = User::getCurrent();

                $response = [
                    'info'    => $info,
                    'details' => Wish::getDetailsFromInfo($info, $user->isLoggedIn() ? $user->getCurrency() : ''),
                ];
            }
        } elseif (isset($_GET['wishlist_id'], $_GET['wishlist_style'], $_GET['wish_priority'])) {
            /**
             * Get wishes by priority
             */
            $wishlist                                 = Wishlist::getFromId($_GET['wishlist_id']);
            $options                                  = [
                'style' => $_GET['wishlist_style'],
            ];
            $where                                    = [
                'priority' => '`priority` = :wish_priority',
            ];
            $options['placeholders']['wish_priority'] = $_GET['wish_priority'];

            if (-1 === \intval($_GET['wish_priority'])) {
                unset($where['priority']);
                unset($options['placeholders']['wish_priority']);
            }

            if (empty($_GET['wish_priority'])) {
                $where['priority'] = '`priority` IS NULL';
            }

            $options['WHERE'] = '(' . \implode(') AND (', $where) . ')';

            $wishes = \array_map(
                function (Wish $wish) use ($wishlist) {
                    $wish->card = $wish->getCard($wishlist->user);

                    return $wish;
                },
                $wishlist->getWishes($options)
            );

            $response['results'] = $wishes;
        }
        break;

    case 'POST':
        if (isset($_POST['wishlist_id']) || isset($_POST['wish_id'])) {
            /**
             * Save wish
             */
            if (
                   empty($_POST['wish_title'])
                && empty($_POST['wish_description'])
                && empty($_POST['wish_url'])
            ) {
                break;
            }

            $user = User::getCurrent();

            /** Updates are checked against the wishlist the wish actually belongs to. */
            if (isset($_POST['wish_id'])) {
                $wishToSave         = Wish::getFromId((int) $_POST['wish_id']);
                $wishlistIdToModify = false === $wishToSave ? 0 : $wishToSave->getWishlistId();
            } else {
                $wishlistIdToModify = (int) $_POST['wishlist_id'];
            }

            if (!$user->ownsWishlist($wishlistIdToModify)) {
                \http_response_code(403);

                die(__('You may only modify wishes you own.'));

                return;
            }

            $wish_title          = addslashes(filter_input(INPUT_POST, 'wish_title', FILTER_SANITIZE_SPECIAL_CHARS) ?? '');
            $wish_description    = addslashes(filter_input(INPUT_POST, 'wish_description', FILTER_SANITIZE_SPECIAL_CHARS) ?? '');
            $wish_image          = addslashes(filter_input(INPUT_POST, 'wish_image', FILTER_SANITIZE_URL) ?? '');
            $wish_url            = addslashes(filter_input(INPUT_POST, 'wish_url', FILTER_SANITIZE_URL) ?? '');
            $wish_priority       = filter_input(INPUT_POST, 'wish_priority', FILTER_SANITIZE_NUMBER_INT);
            $wish_is_purchasable = isset($_POST['wish_is_purchasable']);

            if ('' === $wish_priority) {
                $wish_priority = null;
            }

            if (Wish::NO_IMAGE === $wish_image) {
                $wish_image = '';
            }

            $wish_price_found = null;

            if (isset($_POST['wish_id'], $_POST['wishlist_id'])) {
                /** Update wish */
                $wish = Wish::getFromId($_POST['wish_id']);

                /** Update wish information */
                if (!empty($wish_url)) {
                    $cache   = new Cache\Embed($wish_url);
                    $info    = $cache->get($cache->generateCache());
                    $details = Wish::getDetailsFromInfo($info, $user->getCurrency());

                    if (empty($wish_title) && empty($wish->title) && $details['title']) {
                        $wish_title = Sanitiser::getTitle($details['title']);
                    }

                    if (empty($wish_description) && empty($wish->description) && $details['description']) {
                        $wish_description = Sanitiser::getText($details['description']);
                    }

                    if (empty($wish_image) && empty($wish->image)) {
                        $wish_image = $details['image'];
                    }

                    /**
                     * Price, if the shop shows it in the user's currency. Only
                     * for wishes the user marked as purchasable.
                     */
                    if ($wish_is_purchasable && empty($_POST['wish_price']) && $details['price']) {
                        $wish_price_found = $details['price'];
                    }

                    $response = [
                        'info' => $info,
                    ];
                }

                /** Update */
                $wish_title       = empty($wish_title) ? null : Wish::truncateTitle($wish_title);
                $wish_description = empty($wish_description) ? null : $wish_description;
                $wish_image       = empty($wish_image) || Wish::NO_IMAGE === $wish_image ? null : Wish::getFullSizeImage($wish_image);
                $wish_url         = empty($wish_url) ? null : \substr(Wish::getAffiliateLink($wish_url), 0, 255);
                $wish_priority    = empty($wish_priority) ? null : $wish_priority;

                $database
                ->query(
                    'UPDATE `wishes`
                        SET `wishlist`       = :wishlist_id,
                            `title`          = :wish_title,
                            `description`    = :wish_description,
                            `image`          = :wish_image,
                            `url`            = :wish_url,
                            `priority`       = :wish_priority,
                            `is_purchasable` = :wish_is_purchasable,
                            `edited`         = ' . $database->getDateNow() . '
                      WHERE `id`             = :wish_id',
                    [
                        'wishlist_id'         => $wish->getWishlistId(),
                        'wish_title'          => $wish_title,
                        'wish_description'    => $wish_description,
                        'wish_image'          => $wish_image,
                        'wish_url'            => $wish_url,
                        'wish_priority'       => $wish_priority,
                        'wish_is_purchasable' => $wish_is_purchasable,
                        'wish_id'             => $wish->getId(),
                    ]
                );

                /**
                 * Product
                 */
                $wish_price = empty($_POST['wish_price']) || 'false' === $wish_is_purchasable
                            ? $wish_price_found
                            : Sanitiser::getNumber($_POST['wish_price']);

                $database
                ->query(
                    'REPLACE INTO `products`
                    (
                        `wish`,
                        `price`
                    ) VALUES (
                        :wish_id,
                        :wish_price
                    );',
                    [
                        'wish_id'    => $wish->getId(),
                        'wish_price' => $wish_price,
                    ]
                );

                $response['lastInsertId'] = $wish->getId();
            } elseif (isset($_POST['wishlist_id'])) {
                /** Insert wish */
                $wishlist_id = $_POST['wishlist_id'];

                /** Update wish information */
                if (!empty($wish_url)) {
                    $cache   = new Cache\Embed($wish_url);
                    $info    = $cache->get($cache->generateCache());
                    $details = Wish::getDetailsFromInfo($info, $user->getCurrency());

                    if (empty($wish_title) && $details['title']) {
                        $wish_title = Sanitiser::getTitle($details['title']);
                    }

                    if (empty($wish_description) && $details['description']) {
                        $wish_description = Sanitiser::getText($details['description']);
                    }

                    if (empty($wish_image)) {
                        $wish_image = $details['image'];
                    }

                    /** Price, if the shop shows it in the user's currency */
                    if (empty($_POST['wish_price']) && $details['price']) {
                        $wish_price_found    = $details['price'];
                        $wish_is_purchasable = true;
                    }

                    $response = [
                        'info' => $info,
                    ];
                }

                /** Update */
                $wish_title       = empty($wish_title) ? null : Wish::truncateTitle($wish_title);
                $wish_description = empty($wish_description) ? null : $wish_description ;
                $wish_image       = empty($wish_image) || Wish::NO_IMAGE === $wish_image ? null : Wish::getFullSizeImage($wish_image);
                $wish_url         = empty($wish_url) ? null : \substr(Wish::getAffiliateLink($wish_url), 0, 255);

                $database
                ->query(
                    'INSERT INTO `wishes`
                    (
                        `wishlist`,
                        `title`,
                        `description`,
                        `image`,
                        `url`,
                        `priority`,
                        `is_purchasable`
                    ) VALUES (
                         :wishlist_id,
                         :wish_title,
                         :wish_description,
                         :wish_image,
                         :wish_url,
                         :wish_priority,
                         :wish_is_purchasable
                    );',
                    [
                        'wishlist_id'         => $wishlist_id,
                        'wish_title'          => $wish_title,
                        'wish_description'    => $wish_description,
                        'wish_image'          => $wish_image,
                        'wish_url'            => $wish_url,
                        'wish_priority'       => $wish_priority,
                        'wish_is_purchasable' => $wish_is_purchasable,
                    ]
                );

                /**
                 * Product
                 */
                $wish_id    = $database->lastInsertId();
                $wish_price = !empty($_POST['wish_price']) ? Sanitiser::getNumber($_POST['wish_price']) : $wish_price_found;

                if ($wish_price > 0) {
                    $database
                    ->query(
                        'INSERT INTO `products`
                        (
                            `wish`,
                            `price`
                        ) VALUES (
                            :wish_id,
                            :wish_price
                        );',
                        [
                            'wish_id'    => $wish_id,
                            'wish_price' => $wish_price,
                        ]
                    );
                }

                $response['lastInsertId'] = $wish_id;
            }
        }
        break;

    case 'PUT':
        $_PUT = $this->input;

        if (isset($_PUT['wish_id'], $_PUT['wish_status'])) {
            /**
             * Update Wish Status
             *
             * The wishlist owner may set any status, including an empty one to
             * make the wish available again. Everybody else (also guests) may
             * only claim a wish which is currently available.
             */
            $wish_status = Sanitiser::getStatus($_PUT['wish_status']);
            $wish        = Wish::getFromId((int) $_PUT['wish_id']);

            $statusesValid = [
                '',
                Wish::STATUS_UNAVAILABLE,
                Wish::STATUS_FULFILLED,
            ];
            $statusesGuest = [
                Wish::STATUS_UNAVAILABLE,
            ];

            if (!\in_array($wish_status, $statusesValid, true)) {
                \http_response_code(400);

                die(__('Invalid wish status.'));
            }

            if (false === $wish) {
                \http_response_code(404);

                die(__('The requested Wish was not found.'));
            }

            /**
             * Older versions stored a timestamp while a wish was held for
             * someone for a while. Such wishes count as available.
             */
            $userOwnsWish    = User::getCurrent()->ownsWishlist($wish->getWishlistId());
            $wishIsAvailable = null === $wish->getStatus() || \is_numeric($wish->getStatus());
            $guestMayClaimIt = $wishIsAvailable && \in_array($wish_status, $statusesGuest, true);

            if (!$userOwnsWish && !$guestMayClaimIt) {
                \http_response_code(403);

                die(__('You may not change the status of this wish.'));
            }

            $wish_id = $wish->getId();

            if ('' === $wish_status) {
                $wish_status = null;
            }

            /**
             * A wish which is available again goes back on the owners list,
             * even if it had been received.
             */
            $database->query(
                'UPDATE `wishes`
                    SET `status`   = :wish_status,
                        `received` = CASE WHEN :wish_status_received IS NULL THEN NULL ELSE `received` END,
                        `edited`   = ' . $database->getDateNow() . '
                  WHERE `id`       = :wish_id',
                [
                    'wish_status'          => $wish_status,
                    'wish_status_received' => $wish_status,
                    'wish_id'              => $wish_id,
                ]
            );

            /**
             * Remember the gift, so the giver can see it later and undo it
             * for a while.
             */
            if (!$userOwnsWish && Wish::STATUS_UNAVAILABLE === $wish_status) {
                Gift::purgeAnonymous();

                $response['gift'] = [
                    'id'        => Gift::record($wish),
                    'logged_in' => User::getCurrent()->isLoggedIn(),
                ];
            }

            $response['success'] = true;
        } elseif (isset($_PUT['gift_undo'])) {
            /**
             * Undo Gift
             *
             * Makes a wish the current user fulfilled recently available
             * again.
             */
            $undone = Gift::undo((int) Sanitiser::getNumber($_PUT['gift_undo']));

            if (Gift::UNDO_DENIED === $undone) {
                \http_response_code(403);

                die(__('This gift can no longer be undone.'));
            }

            $response['undone']  = $undone;
            $response['success'] = true;
        } elseif (isset($_PUT['wish_restore'])) {
            /**
             * Restore Wish
             *
             * Puts a wish the owner deleted recently back on its wishlist.
             */
            Wish::purgeDeleted();

            /** Only the owner can restore a wish, so a guest has nothing to restore. */
            $restored = User::getCurrent()->isLoggedIn()
                     && Wish::restore(
                         (int) Sanitiser::getNumber($_PUT['wish_restore']),
                         User::getCurrent()->getId()
                     );

            if (!$restored) {
                \http_response_code(404);

                die(__('This wish can no longer be restored.'));
            }

            $response['success'] = true;
        }
        break;

    case 'DELETE':
        $_DELETE = $this->input;

        $user = User::getCurrent();

        if (!$user->isLoggedIn()) {
            \http_response_code(403);
            die(__('You must be logged in to delete a wish.'));

            return;
        }

        $user_id = $user->getId();

        $userOwnsWishQuery = $database->query(
            '  SELECT `wishes`.`id`    AS "wish_id",
                      `wishlists`.`id` AS "wishlist_id",
                      `users`.`id`     AS "user_id"
                 FROM `wishes`
            LEFT JOIN `wishlists` ON `wishes`.`wishlist` = `wishlists`.`id`
            LEFT JOIN `users`     ON `wishlists`.`user`  = `users`.`id`
                WHERE `wishes`.`id` = :wish_id
                  AND `users`.`id`  = :user_id',
            [
                'wish_id' => $_DELETE['wish_id'],
                'user_id' => $user_id,
            ]
        );
        $userOwnsWishData  = $userOwnsWishQuery->fetch(\PDO::FETCH_ASSOC);
        $userOwnsWish      = isset($userOwnsWishData['user_id']) && $userOwnsWishData['user_id'] === $user_id;

        if (!$userOwnsWish) {
            \http_response_code(403);
            die(__('You must own a wish in order to delete it.'));

            return;
        }

        if (isset($_DELETE['wish_id'])) {
            /** Keep a copy for a while, so the wish can be restored. */
            Wish::purgeDeleted();
            Wish::archive(Sanitiser::getNumber($_DELETE['wish_id']));

            $database->query(
                'DELETE FROM `wishes`
                       WHERE `id` = :wish_id',
                [
                    'wish_id' => Sanitiser::getNumber($_DELETE['wish_id']),
                ]
            );

            $response['success'] = true;
        }

        break;
}
