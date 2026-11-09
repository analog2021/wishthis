<?php

/**
 * wishthis - Make a wish
 *
 * @author Jay Trees <github.jay@grandel.anonaddy.me>
 */

namespace wishthis;

\define('VERSION', '1.7.1');
\define('ROOT', __DIR__);
\define('DEFAULT_LOCALE', 'en_GB');

/**
 * Include
 */
require 'vendor/autoload.php';

require_once ROOT . '/src/functions/getCookieDomain.php';
require_once ROOT . '/src/functions/getShortDateFormatter.php';
require_once ROOT . '/src/functions/gettext.php';
require_once ROOT . '/src/functions/getWishlistNameSuggestion.php';
require_once ROOT . '/src/functions/redirect.php';

\spl_autoload_register(
    function (string $absoluteNamespace) {
        if (__NAMESPACE__ !== \substr($absoluteNamespace, 0, \strlen(__NAMESPACE__))) {
            return;
        }

        $absoluteNamespace = \str_replace('\\', '/', $absoluteNamespace);

        $filepath = ROOT . '/src/classes/' . $absoluteNamespace . '.php';

        require $filepath;
    }
);

/**
 * Config
 */
$configPath = __DIR__ . '/' . 'src/config/config.php';
$config     = new Config($configPath);
$config->load();

/**
 * Session
 *
 * Has to be setup first, before anything else, so translations can be loaded.
 * The configuration is the only exception, since `loadFromSession` needs the
 * database.
 */
\session_start(
    [
        'name' => 'wishthis',
    ]
);

$user = User::getCurrent();

/**
 * Database
 */
$database = false;
$options  = false;

if (
       \defined('DATABASE_ENGINE')
    && \defined('DATABASE_HOST')
    && \defined('DATABASE_NAME')
    && \defined('DATABASE_USER')
    && \defined('DATABASE_PASSWORD')
) {
    $database = new Database(
        DATABASE_ENGINE,
        DATABASE_HOST,
        DATABASE_NAME,
        DATABASE_USER,
        DATABASE_PASSWORD
    );
    $database->connect();

    /**
     * Options
     */
    $options = new Options($database);

    /**
     * User session
     */
    $user->loadFromSession();
}

/**
 * Language
 */
\Locale::setDefault(DEFAULT_LOCALE);

/** Determine Locale */
$locales = \array_filter(
    \array_map(
        function ($value) {
            $extension = \pathinfo($value, \PATHINFO_EXTENSION);
            $filename  = \pathinfo($value, \PATHINFO_FILENAME);

            if ('po' === $extension) {
                return \pathinfo($value, \PATHINFO_FILENAME);
            }
        },
        \scandir(ROOT . '/translations')
    )
);

$locale = isset($_REQUEST['locale']) ? $_REQUEST['locale'] : \Locale::lookup($locales, $user->getLocale(), false, 'en_GB');

/**
 * Wish
 */
Wish::initialize();

/**
 * Pretty URLs
 */
$url = new URL($_SERVER['REQUEST_URI']);

/**
 * Database Update
 */
if ($options && $options->getOption('isInstalled')) {
    if (-1 === \version_compare($options->version, VERSION)) {
        /**
         * Without database changes, there is nothing to migrate: the new
         * version is simply recorded, instead of sending everyone to the
         * maintenance page until an admin presses "Migrate".
         */
        if ([] === $options->getPendingUpdates()) {
            $options->completeUpdate();
        } else {
            $options->setOption('updateAvailable', true);
        }
    }
}

/**
 * Received wishes
 *
 * Fulfilled wishes leave the users lists two weeks after their birthday.
 * Checked once a day per user and session (an admin may log in as someone
 * else in the same session), and only once the database has the columns for
 * it.
 */
if (
       $options
    && $options->getOption('isInstalled')
    && -1 !== \version_compare($options->version, '1.5.0')
    && $user->isLoggedIn()
) {
    $receivedChecked = \date('Y-m-d') . '/' . $user->getId();

    if (($_SESSION['received_checked'] ?? '') !== $receivedChecked) {
        $user->markReceivedWishes();

        $_SESSION['received_checked'] = $receivedChecked;
    }
}

/**
 * Page
 */
if (!isset($page)) {
    $page = isset($_GET['page']) ? $_GET['page'] : 'home';
}

/**
 * Page names are plain words like `login-as`. Anything else (`../`, markup)
 * would let a request load other PHP files, even this one again and again.
 */
if (!\is_string($page) || 1 !== \preg_match('/^[a-z][a-z\-]*$/', $page)) {
    $page = 'not-found';
}

$pagePath    = __DIR__ . '/src/pages/' . $page . '.php';
$pagePathAlt = __DIR__ . '/src/pages/' . $page . '/' . $page . '.php';

if (\file_exists($pagePath)) {
    require $pagePath;
} elseif (\file_exists($pagePathAlt)) {
    require $pagePathAlt;
} else {
    \http_response_code(404);
    ?>
    <h1>Not found</h1>
    <p>The requested URL was not found on this server.</p>
    <?php
    die();
}
