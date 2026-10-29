<?php

/**
 * Configuration
 *
 * Usually, the wishthis installer will create the config.php for you.
 *
 * Fixture: written in the format of the v1.2.3 installer, with dummy values.
 */

namespace wishthis;

/**
 * Database
 *
 * Used to connect to your MySQL database.
 */
define('DATABASE_HOST', 'db.example');
define('DATABASE_NAME', 'wishthis_test');
define('DATABASE_USER', 'wishthis_user');
define('DATABASE_PASSWORD', 'not-a-real-password');

/**
 * Development
 *
 * Keep this disabled for production sites.
 */
define('ENV_IS_DEV', false);

/**
 * plausible
 *
 * Whether to make calls to plausible.io.
 */
define('PLAUSIBLE', false);

/**
 * Miscellaneous
 */
define('DISABLE_USER_REGISTRATION', false);
