/**
 * Gifts
 *
 * Remembers which wishes a guest has fulfilled, so they can see what they
 * gave, to whom and when, and undo a mistake for a while.
 * SQLite runs `sqlite/1-4-0.sql` instead.
 */
CREATE TABLE IF NOT EXISTS `gifts` (
    `id`             INT          NOT NULL PRIMARY KEY AUTO_INCREMENT,
    `user`           INT          NULL     DEFAULT NULL,
    `wish`           INT          NULL     DEFAULT NULL,
    `wishlist`       INT          NULL     DEFAULT NULL,
    `recipient`      INT          NULL     DEFAULT NULL,
    `recipient_name` VARCHAR(255) NULL     DEFAULT NULL,
    `wishlist_name`  VARCHAR(128) NULL     DEFAULT NULL,
    `title`          VARCHAR(128) NULL     DEFAULT NULL,
    `url`            VARCHAR(255) NULL     DEFAULT NULL,
    `price`          FLOAT        NULL     DEFAULT NULL,
    `currency`       VARCHAR(3)   NULL     DEFAULT NULL,
    `created`        TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,

    INDEX `idx_gifts_wish` (`wish`),
    CONSTRAINT `FK_gifts_users`      FOREIGN KEY (`user`)      REFERENCES `users`     (`id`) ON DELETE CASCADE,
    CONSTRAINT `FK_gifts_wishlists`  FOREIGN KEY (`wishlist`)  REFERENCES `wishlists` (`id`) ON DELETE SET NULL,
    CONSTRAINT `FK_gifts_recipients` FOREIGN KEY (`recipient`) REFERENCES `users`     (`id`) ON DELETE SET NULL
);
