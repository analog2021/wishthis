<?php

/**
 * Redirect to URL
 *
 * @author Jay Trees <github.jay@grandel.anonaddy.me>
 */

use wishthis\User;

function redirect(string $target)
{
    $user = User::getCurrent();

    /**
     * Redirect user based on channel setting
     */
    $isDevEnvironment = \defined('ENV_IS_DEV') && true === ENV_IS_DEV;
    $isHostInChannel  = false;

    /** Determine if host is a \defined channel */
    if (\defined('CHANNELS') && \is_array(CHANNELS)) {
        foreach (CHANNELS as $channel) {
            if ($channel['host'] === $_SERVER['HTTP_HOST']) {
                $isHostInChannel = true;
                break;
            }
        }
    }

    /** Determine channel to redirect to */
    if (
           \defined('CHANNELS')
        && \is_array(CHANNELS)
        && $user->getChannel()
        && !$isDevEnvironment
    ) {
        $host = null;

        foreach (CHANNELS as $channel) {
            if (
                   $channel['branch'] === $user->getChannel()
                && $channel['host']   !== $_SERVER['HTTP_HOST']
                && $isHostInChannel
            ) {
                $host = $channel['host'];
                break;
            }
        }

        if (null !== $host) {
            $target = 'https://' . $host . $target;

            \header('Location: ' . $target);
            die();
        }
    }

    /**
     * `URL` decodes the request URI, so targets built from it are decoded too.
     * Compare decoded values, otherwise URLs like `?q=%3C` redirect forever.
     */
    if (\urldecode($target) !== \urldecode($_SERVER['REQUEST_URI'])) {
        \header('Location: ' . $target);
        die();
    }
}
