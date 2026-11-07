CREATE TABLE gifts (
    id             INTEGER   PRIMARY KEY AUTOINCREMENT,
    user           INTEGER            DEFAULT NULL,
    wish           INTEGER            DEFAULT NULL,
    wishlist       INTEGER            DEFAULT NULL,
    recipient      INTEGER            DEFAULT NULL,
    recipient_name TEXT               DEFAULT NULL,
    wishlist_name  TEXT               DEFAULT NULL,
    title          TEXT               DEFAULT NULL,
    url            TEXT               DEFAULT NULL,
    price          REAL               DEFAULT NULL,
    created        TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user)      REFERENCES users(id)     ON DELETE CASCADE,
    FOREIGN KEY (wishlist)  REFERENCES wishlists(id) ON DELETE SET NULL,
    FOREIGN KEY (recipient) REFERENCES users(id)     ON DELETE SET NULL
);
