/**
 * Received wishes (SQLite version of `../1-5-0.sql`)
 *
 * SQLite adds one column per statement.
 */
ALTER TABLE users          ADD COLUMN remove_received_wishes INTEGER NOT NULL DEFAULT 0;
ALTER TABLE wishes         ADD COLUMN received               TEXT             DEFAULT NULL;
ALTER TABLE wishes_deleted ADD COLUMN received               TEXT             DEFAULT NULL;
