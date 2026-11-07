/**
 * Recently deleted wishes
 *
 * Deleted wishes are kept here for a short while so they can be restored.
 * Written to run on both MySQL/MariaDB and SQLite.
 */
CREATE TABLE IF NOT EXISTS `wishes_deleted` (
    `id`             INTEGER      NOT NULL PRIMARY KEY,
    `wishlist`       INTEGER      NOT NULL,
    `title`          VARCHAR(128) NULL     DEFAULT NULL,
    `description`    TEXT         NULL     DEFAULT NULL,
    `image`          TEXT         NULL     DEFAULT NULL,
    `url`            VARCHAR(255) NULL     DEFAULT NULL,
    `priority`       TINYINT(1)   NULL     DEFAULT NULL,
    `status`         VARCHAR(32)  NULL     DEFAULT NULL,
    `is_purchasable` BOOLEAN      NOT NULL DEFAULT FALSE,
    `price`          FLOAT        NULL     DEFAULT NULL,
    `deleted`        TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (`wishlist`) REFERENCES `wishlists` (`id`) ON DELETE CASCADE
);
