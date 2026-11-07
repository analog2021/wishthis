/**
 * Received wishes: off by default
 *
 * Tidying up after a birthday is now something users turn on themselves,
 * because wishes bought early for another occasion (e.g. on Black Friday for
 * Christmas) would leave the list too soon. 1.5.0 had it on for everyone, so
 * turn it off again and put wishes it took off the lists back on.
 * SQLite runs `sqlite/1-5-1.sql` instead.
 */
ALTER TABLE `users`
    ALTER COLUMN `remove_received_wishes` SET DEFAULT 0;

UPDATE `users`
   SET `remove_received_wishes` = 0;

UPDATE `wishes`
   SET `received` = NULL,
       `edited`   = `edited`
 WHERE `received` IS NOT NULL;
