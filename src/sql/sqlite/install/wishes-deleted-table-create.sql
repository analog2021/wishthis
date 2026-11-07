CREATE TABLE wishes_deleted (
    id             INTEGER   PRIMARY KEY,
    wishlist       INTEGER   NOT NULL,
    title          TEXT               DEFAULT NULL,
    description    TEXT               DEFAULT NULL,
    image          TEXT               DEFAULT NULL,
    url            TEXT               DEFAULT NULL,
    priority       INTEGER            DEFAULT NULL,
    status         TEXT               DEFAULT NULL,
    is_purchasable INTEGER   NOT NULL DEFAULT 0,
    price          REAL               DEFAULT NULL,
    deleted        TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (wishlist) REFERENCES wishlists(id) ON DELETE CASCADE
);
