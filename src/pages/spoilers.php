<?php

/**
 * Template for revealing the fulfilled and unavailable wishes of a wishlist
 * to its owner.
 */

namespace wishthis;

$page = new Page(__FILE__, __('Spoilers'), 1);
$user = User::getCurrent();

$wishlist_id = \filter_var($_GET['wishlist'] ?? null, \FILTER_VALIDATE_INT);
$wishlist    = $wishlist_id ? Wishlist::getFromId($wishlist_id) : false;

/**
 * Only the owner may see the spoilers. Everybody else is treated as if the
 * wishlist does not exist.
 */
if (false === $wishlist || $user->getId() !== $wishlist->getUserId()) {
    $page->errorDocument(404, Wishlist::class);
}

$page->title = \sprintf(__('Spoilers for %s'), $wishlist->getTitle());
$reveal      = 'POST' === $_SERVER['REQUEST_METHOD'] && isset($_POST['reveal']);

/**
 * Wish values may already be stored as HTML entities, so decode them before
 * escaping to avoid double encoding.
 */
$escape = function (string $value): string {
    return \htmlspecialchars(\html_entity_decode($value, \ENT_QUOTES | \ENT_HTML5, 'UTF-8'), \ENT_QUOTES);
};

$statuses = [
    Wish::STATUS_FULFILLED   => [
        'name'        => __('Fulfilled'),
        'description' => __('You marked this wish as fulfilled.'),
        'color'       => 'green',
        'icon'        => 'check',
    ],
    Wish::STATUS_UNAVAILABLE => [
        'name'        => __('Unavailable'),
        'description' => __('Someone has claimed this wish.'),
        'color'       => 'orange',
        'icon'        => 'gift',
    ],
];

$page->header();
$page->bodyStart();
$page->navigation();
?>

<main>
    <div class="ui container">
        <h1 class="ui header"><?= $escape($page->title) ?></h1>

        <?= $page->messages() ?>

        <?php if (!$reveal) { ?>
            <div class="ui icon warning message">
                <i class="exclamation triangle icon"></i>
                <div class="content">
                    <div class="header">
                        <?= __('Are you sure?') ?>
                    </div>
                    <p><?= __('This will show you which wishes on this list have been fulfilled or claimed by someone else. Don\'t you want to be surprised?') ?></p>
                </div>
            </div>

            <form class="ui form" method="POST">
                <input type="hidden" name="reveal" value="1" />

                <div class="flex buttons">
                    <button class="ui labeled icon primary button" type="submit">
                        <i class="eye icon"></i>
                        <?= __('Yes, show me') ?>
                    </button>

                    <a class="ui labeled icon button" href="<?= Page::PAGE_WISHLISTS . '&id=' . $wishlist->getId() ?>">
                        <i class="arrow left icon"></i>
                        <?= __('Back to my lists') ?>
                    </a>
                </div>
            </form>
        <?php } else { ?>
            <?php $wishes = $wishlist->getSpoilers(); ?>

            <?php if (empty($wishes)) { ?>
                <?= Page::info(__('No wishes on this list have been fulfilled or claimed yet.'), __('Nothing to spoil')) ?>
            <?php } else { ?>
                <table class="ui celled unstackable table spoilers">
                    <thead>
                        <tr>
                            <th><?= __('Wish') ?></th>
                            <th><?= __('Description') ?></th>
                            <th><?= __('Status') ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($wishes as $wish) { ?>
                            <?php
                            $wish_data   = $wish->serialise();
                            $wish_title  = $escape($wish_data['title'] ?: $wish_data['url'] ?: '#' . $wish->getId());
                            $wish_url    = $escape($wish_data['url']);
                            $wish_status = $statuses[$wish->getStatus()];
                            ?>
                            <tr>
                                <td>
                                    <?php if (\preg_match('/^https?:\/\//i', $wish_url)) { ?>
                                        <a href="<?= $wish_url ?>" target="_blank" rel="noopener"><?= $wish_title ?></a>
                                    <?php } else { ?>
                                        <?= $wish_title ?>
                                    <?php } ?>
                                </td>
                                <td><?= \nl2br($escape($wish_data['description'])) ?></td>
                                <td>
                                    <div class="ui <?= $wish_status['color'] ?> label">
                                        <i class="<?= $wish_status['icon'] ?> icon"></i>
                                        <?= $wish_status['name'] ?>
                                    </div>
                                    <p><?= $wish_status['description'] ?></p>
                                </td>
                            </tr>
                        <?php } ?>
                    </tbody>
                </table>
            <?php } ?>

            <a class="ui labeled icon button" href="<?= Page::PAGE_WISHLISTS . '&id=' . $wishlist->getId() ?>">
                <i class="arrow left icon"></i>
                <?= __('Back to my lists') ?>
            </a>
        <?php } ?>
    </div>
</main>

<?php
$page->bodyEnd();
