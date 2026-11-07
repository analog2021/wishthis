/**
 * Received wishes: off by default (SQLite version of `../1-5-1.sql`)
 *
 * SQLite can't change the default of a column, but 1.5.0 was never released
 * with the old default, so no SQLite database has it.
 */
UPDATE users  SET remove_received_wishes = 0;
UPDATE wishes SET received = NULL WHERE received IS NOT NULL;
