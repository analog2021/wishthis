CREATE TABLE `wishes_deleted` (
    `id`             INT          NOT NULL PRIMARY KEY,
    `wishlist`       INT          NOT NULL,
    `title`          VARCHAR(128) NULL     DEFAULT NULL,
    `description`    TEXT         NULL     DEFAULT NULL,
    `image`          TEXT         NULL     DEFAULT NULL,
    `url`            VARCHAR(255) NULL     DEFAULT NULL,
    `priority`       TINYINT(1)   NULL     DEFAULT NULL,
    `status`         VARCHAR(32)  NULL     DEFAULT NULL,
    `is_purchasable` BOOLEAN      NOT NULL DEFAULT FALSE,
    `price`          FLOAT        NULL     DEFAULT NULL,
    `deleted`        TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT `FK_wishes_deleted_wishlists` FOREIGN KEY (`wishlist`) REFERENCES `wishlists` (`id`) ON DELETE CASCADE
);
