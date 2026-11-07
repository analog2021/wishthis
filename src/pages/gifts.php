<?php

/**
 * Template for the wishes the current user has fulfilled for others.
 */

namespace wishthis;

$page = new Page(__FILE__, __('My gifts'));
$user = User::getCurrent();

Gift::purgeAnonymous();

$gifts      = Gift::getForCurrentUser();
$isLoggedIn = $user->isLoggedIn();

/** Come back here after logging in, to see the gifts kept. */
if (!$isLoggedIn) {
    $_SESSION['REDIRECT_URL'] = Page::PAGE_GIFTS;
}

/**
 * Gift values may already be stored as HTML entities, so decode them before
 * escaping to avoid double encoding.
 */
$escape = function (?string $value): string {
    return \htmlspecialchars(\html_entity_decode($value ?? '', \ENT_QUOTES | \ENT_HTML5, 'UTF-8'), \ENT_QUOTES);
};

$dateFormatter = new \IntlDateFormatter(
    $user->getLocale(),
    \IntlDateFormatter::MEDIUM,
    \IntlDateFormatter::SHORT
);

$page->header();
$page->bodyStart();
$page->navigation();
?>

<main>
    <div class="ui container">
        <h1 class="ui header"><?= $page->title ?></h1>

        <?= $page->messages() ?>

        <p>
            <?= \sprintf(
                /** TRANSLATORS: %d: Amount of minutes */
                __('The wishes you fulfilled for others. Did you fulfil one by mistake? You can undo it for %d minutes.'),
                Wish::UNDO_MINUTES
            ) ?>
        </p>

        <?php if (!$isLoggedIn) { ?>
            <div class="ui icon info message">
                <i class="user icon"></i>
                <div class="content">
                    <div class="header">
                        <?= __('Log in to keep track of your gifts') ?>
                    </div>
                    <p>
                        <?= \sprintf(
                            /** TRANSLATORS: %d: Amount of hours */
                            __('You are not logged in, so your gifts are only remembered in this browser for %d hours. Log in and they will be kept in your account.'),
                            Gift::ANONYMOUS_HOURS
                        ) ?>
                    </p>
                    <div class="flex buttons">
                        <a class="ui primary labeled icon button" href="<?= Page::PAGE_LOGIN ?>">
                            <i class="sign in alternate icon"></i>
                            <?= __('Login') ?>
                        </a>
                        <a class="ui labeled icon button" href="<?= Page::PAGE_REGISTER ?>">
                            <i class="user plus icon"></i>
                            <?= __('Register') ?>
                        </a>
                    </div>
                </div>
            </div>
        <?php } ?>

        <div class="gifts-empty" <?= empty($gifts) ? '' : 'hidden' ?>>
            <?= Page::info(__('When you fulfil a wish on someone\'s list, it will show up here.'), __('No gifts yet')) ?>
        </div>

        <?php if (!empty($gifts)) { ?>
            <table class="ui celled table gifts">
                <thead>
                    <tr>
                        <th><?= __('Gift') ?></th>
                        <th><?= __('For') ?></th>
                        <th><?= __('Price') ?></th>
                        <th><?= __('When') ?></th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($gifts as $gift) { ?>
                        <?php
                        $gift_title    = $escape($gift['title'] ?: $gift['url'] ?: __('Wish'));
                        $gift_url      = $escape($gift['url']);
                        $wishlist_name = $escape($gift['wishlist_name'] ?: __('Wishlist'));
                        $created       = \time() - $gift['minutes_ago'] * 60;

                        if (null !== $gift['price']) {
                            $priceFormatter = $gift['currency']
                                            ? new \NumberFormatter($user->getLocale() . '@currency=' . $gift['currency'], \NumberFormatter::CURRENCY)
                                            : new \NumberFormatter($user->getLocale(), \NumberFormatter::DECIMAL);
                            $gift_price     = $priceFormatter->format((float) $gift['price']);
                        } else {
                            $gift_price = '';
                        }
                        ?>
                        <tr>
                            <td>
                                <?php if (\preg_match('/^https?:\/\//i', $gift_url)) { ?>
                                    <a href="<?= $gift_url ?>" target="_blank" rel="noopener"><?= $gift_title ?></a>
                                <?php } else { ?>
                                    <?= $gift_title ?>
                                <?php } ?>
                            </td>
                            <td>
                                <?php if ($gift['recipient_name']) { ?>
                                    <strong><?= $escape($gift['recipient_name']) ?></strong><br />
                                <?php } ?>

                                <?php if ($gift['wishlist_hash']) { ?>
                                    <a href="<?= Page::PAGE_WISHLIST . '&hash=' . $escape($gift['wishlist_hash']) ?>"><?= $wishlist_name ?></a>
                                <?php } else { ?>
                                    <?= $wishlist_name ?>
                                <?php } ?>
                            </td>
                            <td><?= $gift_price ?></td>
                            <td>
                                <?php if ($gift['minutes_ago'] < 60) { ?>
                                    <span class="minutes-ago" data-minutes-ago="<?= $gift['minutes_ago'] ?>"></span>
                                <?php } else { ?>
                                    <?= $dateFormatter->format($created) ?>
                                <?php } ?>
                            </td>
                            <td>
                                <?php if ($gift['can_undo']) { ?>
                                    <button class="ui mini labeled icon button gift-undo"
                                            type="button"
                                            data-gift-id="<?= (int) $gift['id'] ?>"
                                    >
                                        <i class="undo icon"></i>
                                        <?= __('Undo') ?>
                                    </button>
                                <?php } ?>
                            </td>
                        </tr>
                    <?php } ?>
                </tbody>
            </table>
        <?php } ?>
    </div>
</main>

<?php
$page->bodyEnd();
