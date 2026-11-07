<?php

/**
 * A wish somebody fulfilled for somebody else.
 *
 * Gifts are a snapshot of the wish at the time it was fulfilled, so the giver
 * can still see what they gave, to whom and when after the wish or wishlist
 * has been deleted. Only the giver ever sees their gifts.
 *
 * Gifts of somebody who isn't logged in are remembered in their session and
 * moved into their account when they log in.
 */

namespace wishthis;

class Gift
{
    /**
     * How long the gifts of somebody who isn't logged in are kept, so they
     * can still log in to keep them.
     */
    public const ANONYMOUS_HOURS = 24;

    /** The wish is available again. */
    public const UNDO_DONE = 'done';

    /** The wish no longer exists, the gift was forgotten anyway. */
    public const UNDO_WISH_GONE = 'wish-gone';

    /** Unknown gift, someone else's, or too old. */
    public const UNDO_DENIED = 'denied';

    /**
     * Remembers that the current user (who doesn't own the wishlist) fulfilled
     * a wish.
     *
     * @return int The gift id.
     */
    public static function record(Wish $wish): int
    {
        global $database;

        $giver     = User::getCurrent();
        $wishData  = $wish->serialise();
        $wishlist  = Wishlist::getFromId($wish->getWishlistId());
        $recipient = $wishlist ? User::getFromID($wishlist->getUserId()) : null;

        /** Never show the recipient's email address, only a name they chose. */
        $recipientName = $recipient ? ($recipient->getNameNick() ?: $recipient->getNameFirst()) : '';
        $title         = $wishData['title'] ?: $wishData['description'] ?: $wishData['url'];

        $database->query(
            'INSERT INTO `gifts` (
                `user`, `wish`, `wishlist`, `recipient`,
                `recipient_name`, `wishlist_name`, `title`, `url`, `price`
            ) VALUES (
                :user, :wish, :wishlist, :recipient,
                :recipient_name, :wishlist_name, :title, :url, :price
            )',
            [
                'user'           => $giver->isLoggedIn() ? $giver->getId() : null,
                'wish'           => $wish->getId(),
                'wishlist'       => $wishlist ? $wishlist->getId() : null,
                'recipient'      => $recipient ? $recipient->getId() : null,
                'recipient_name' => $recipientName ?: null,
                'wishlist_name'  => $wishlist ? $wishlist->getName() : null,
                'title'          => $title ? Wish::truncateTitle($title) : null,
                'url'            => $wishData['url'] ?: null,
                'price'          => $wishData['price'],
            ]
        );

        $giftId = $database->lastInsertId();

        if (!$giver->isLoggedIn()) {
            $_SESSION['gifts']   = self::getSessionGiftIds();
            $_SESSION['gifts'][] = $giftId;
        }

        return $giftId;
    }

    /**
     * Returns the current user's gifts, newest first.
     *
     * @return array[] Each gift with `minutes_ago`, the `wishlist_hash` (while
     *                 the wishlist exists) and whether it `can_undo`.
     */
    public static function getForCurrentUser(): array
    {
        global $database;

        [$owner, $placeholders] = self::getOwnerCondition();

        $giftsQuery = $database->query(
            '  SELECT `gifts`.*,
                      `wishlists`.`hash` AS "wishlist_hash",
                      `wishes`.`status`  AS "wish_status",
                      ' . $database->getMinutesSince('`gifts`.`created`') . ' AS "minutes_ago",
                      CASE WHEN `gifts`.`created` >= ' . $database->getDateMinutesAgo(Wish::UNDO_MINUTES) . '
                           THEN 1 ELSE 0
                      END AS "within_undo"
                 FROM `gifts`
            LEFT JOIN `wishlists` ON `gifts`.`wishlist` = `wishlists`.`id`
            LEFT JOIN `wishes`    ON `gifts`.`wish`     = `wishes`.`id`
                                 AND `gifts`.`wishlist` = `wishes`.`wishlist`
                WHERE ' . $owner . '
             ORDER BY `gifts`.`created` DESC, `gifts`.`id` DESC',
            $placeholders
        );

        $gifts = $giftsQuery ? $giftsQuery->fetchAll(\PDO::FETCH_ASSOC) : [];

        foreach ($gifts as &$gift) {
            $gift['minutes_ago'] = \max(0, (int) $gift['minutes_ago']);
            $gift['can_undo']    = 1 === (int) $gift['within_undo']
                                && Wish::STATUS_UNAVAILABLE === $gift['wish_status'];
        }

        return $gifts;
    }

    /**
     * Returns how many wishes on a wishlist the current user has fulfilled.
     */
    public static function countForWishlist(int $wishlistId): int
    {
        global $database;

        [$owner, $placeholders] = self::getOwnerCondition();

        $countQuery = $database->query(
            'SELECT COUNT(*) AS "gifts"
               FROM `gifts`
              WHERE `gifts`.`wishlist` = :wishlist_id
                AND ' . $owner,
            \array_merge($placeholders, ['wishlist_id' => $wishlistId])
        );

        return $countQuery ? (int) $countQuery->fetchColumn() : 0;
    }

    /**
     * Undoes a gift the current user made recently: the wish becomes
     * available again and the gift is forgotten.
     *
     * @return string One of the `UNDO_*` constants.
     */
    public static function undo(int $giftId): string
    {
        global $database;

        [$owner, $placeholders] = self::getOwnerCondition();

        $giftQuery = $database->query(
            'SELECT `gifts`.*
               FROM `gifts`
              WHERE `gifts`.`id`       = :gift_id
                AND `gifts`.`created` >= ' . $database->getDateMinutesAgo(Wish::UNDO_MINUTES) . '
                AND ' . $owner,
            \array_merge($placeholders, ['gift_id' => $giftId])
        );
        $gift      = $giftQuery ? $giftQuery->fetch(\PDO::FETCH_ASSOC) : false;

        if (false === $gift) {
            return self::UNDO_DENIED;
        }

        $wish = $gift['wish'] ? Wish::getFromId((int) $gift['wish']) : false;

        if (false === $wish || (int) $gift['wishlist'] !== $wish->getWishlistId()) {
            self::delete($giftId);

            return self::UNDO_WISH_GONE;
        }

        /** The owner marked it as fulfilled themselves, leave it alone. */
        if (Wish::STATUS_FULFILLED === $wish->getStatus()) {
            return self::UNDO_DENIED;
        }

        /**
         * Only make the wish available again if this gift is what made it
         * unavailable. Someone else may have fulfilled it since, if the owner
         * made it available again in between.
         */
        $newerGiftQuery = $database->query(
            'SELECT COUNT(*)
               FROM `gifts`
              WHERE `wish` = :wish_id
                AND `id`   > :gift_id',
            [
                'wish_id' => $wish->getId(),
                'gift_id' => $giftId,
            ]
        );
        $hasNewerGift   = $newerGiftQuery && (int) $newerGiftQuery->fetchColumn() > 0;

        if (Wish::STATUS_UNAVAILABLE === $wish->getStatus() && !$hasNewerGift) {
            $database->query(
                'UPDATE `wishes`
                    SET `status` = NULL,
                        `edited` = ' . $database->getDateNow() . '
                  WHERE `id`     = :wish_id',
                [
                    'wish_id' => $wish->getId(),
                ]
            );
        }

        self::delete($giftId);

        return self::UNDO_DONE;
    }

    /**
     * Moves the gifts made in this session before logging in into the
     * user's account.
     */
    public static function claimSessionGifts(int $userId): void
    {
        global $database;

        $giftIds = self::getSessionGiftIds();

        unset($_SESSION['gifts']);

        if (empty($giftIds) || false === $database) {
            return;
        }

        $database->query(
            'UPDATE `gifts`
                SET `user` = :user_id
              WHERE `user` IS NULL
                AND `id` IN (' . \implode(', ', $giftIds) . ')',
            [
                'user_id' => $userId,
            ]
        );
    }

    /**
     * Forgets the gifts of people who never logged in to keep them.
     */
    public static function purgeAnonymous(): void
    {
        global $database;

        $database->query(
            'DELETE FROM `gifts`
                   WHERE `user` IS NULL
                     AND `created` < ' . $database->getDateMinutesAgo(self::ANONYMOUS_HOURS * 60)
        );
    }

    private static function delete(int $giftId): void
    {
        global $database;

        $database->query(
            'DELETE FROM `gifts`
                   WHERE `id` = :gift_id',
            [
                'gift_id' => $giftId,
            ]
        );
    }

    /**
     * @return int[] The ids of the gifts made in this session while not logged
     *               in.
     */
    private static function getSessionGiftIds(): array
    {
        $giftIds = $_SESSION['gifts'] ?? [];

        if (!\is_array($giftIds)) {
            return [];
        }

        return \array_values(\array_unique(\array_map('intval', $giftIds)));
    }

    /**
     * Returns an SQL condition matching the current user's gifts and its
     * placeholders.
     *
     * @return array{0: string, 1: array}
     */
    private static function getOwnerCondition(): array
    {
        $user = User::getCurrent();

        if ($user->isLoggedIn()) {
            return ['`gifts`.`user` = :gift_user', ['gift_user' => $user->getId()]];
        }

        $giftIds = self::getSessionGiftIds();

        if (empty($giftIds)) {
            return ['1 = 0', []];
        }

        /** The ids are integers, so they are safe to put into the query. */
        return ['`gifts`.`user` IS NULL AND `gifts`.`id` IN (' . \implode(', ', $giftIds) . ')', []];
    }
}
