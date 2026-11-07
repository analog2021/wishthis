/**
 * Received wishes
 *
 * Two weeks after a user's birthday, the wishes others fulfilled for them
 * leave their lists and are kept under Spoilers as received. Users turn this
 * on themselves, it is off by default.
 * SQLite runs `sqlite/1-5-0.sql` instead.
 */
ALTER TABLE `users`
    ADD COLUMN `remove_received_wishes` TINYINT(1) NOT NULL DEFAULT 0;

ALTER TABLE `wishes`
    ADD COLUMN `received` TIMESTAMP NULL DEFAULT NULL;

ALTER TABLE `wishes_deleted`
    ADD COLUMN `received` TIMESTAMP NULL DEFAULT NULL;
