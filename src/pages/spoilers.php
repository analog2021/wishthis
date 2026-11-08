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
$recentOnly  = $reveal && 'recent' === $_POST['reveal'];

/**
 * Wish values may already be stored as HTML entities, so decode them before
 * escaping to avoid double encoding.
 */
$escape = function (string $value): string {
    return \htmlspecialchars(\html_entity_decode($value, \ENT_QUOTES | \ENT_HTML5, 'UTF-8'), \ENT_QUOTES);
};

$dateFormatter = \getShortDateFormatter($user->getLocale());

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
                    <p><?= __('Just fixing a slip? Undo a mistake shows only what you changed in the last hour.') ?></p>
                </div>
            </div>

            <form class="ui form" method="POST">
                <div class="flex buttons">
                    <button class="ui labeled icon primary button" type="submit" name="reveal" value="all">
                        <i class="eye icon"></i>
                        <?= __('Yes, show me') ?>
                    </button>

                    <button class="ui labeled icon button" type="submit" name="reveal" value="recent">
                        <i class="undo icon"></i>
                        <?= __('Undo a mistake') ?>
                    </button>

                    <a class="ui labeled icon button" href="<?= Page::PAGE_WISHLISTS . '&id=' . $wishlist->getId() ?>">
                        <i class="arrow left icon"></i>
                        <?= __('Back to my lists') ?>
                    </a>
                </div>
            </form>
        <?php } elseif ($recentOnly) { ?>
            <?php
            Wish::purgeDeleted();

            $changes = $wishlist->getRecentChanges();
            ?>

            <div class="spoilers-empty" <?= empty($changes) ? '' : 'hidden' ?>>
                <?= Page::info(__('You haven\'t fulfilled or deleted any wishes on this list in the last hour.'), __('Nothing to undo')) ?>
            </div>

            <?php if (!empty($changes)) { ?>
                <table class="ui celled table spoilers">
                    <thead>
                        <tr>
                            <th><?= __('Wish') ?></th>
                            <th><?= __('Description') ?></th>
                            <th><?= __('When') ?></th>
                            <th><?= __('Status') ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($changes as $change) { ?>
                            <?php
                            if ('fulfilled' === $change['type']) {
                                $wish_id   = $change['wish']->getId();
                                $wish_data = $change['wish']->serialise();
                            } else {
                                $wish_id   = (int) $change['deleted']['id'];
                                $wish_data = $change['deleted'];
                            }

                            $wish_title       = $escape(($wish_data['title'] ?? '') ?: (($wish_data['url'] ?? '') ?: '#' . $wish_id));
                            $wish_url         = $escape($wish_data['url'] ?? '');
                            $wish_description = $escape($wish_data['description'] ?? '');
                            ?>
                            <tr>
                                <td>
                                    <?php if (\preg_match('/^https?:\/\//i', $wish_url)) { ?>
                                        <a href="<?= $wish_url ?>" target="_blank" rel="noopener"><?= $wish_title ?></a>
                                    <?php } else { ?>
                                        <?= $wish_title ?>
                                    <?php } ?>
                                </td>
                                <td><?= \nl2br($wish_description) ?></td>
                                <td>
                                    <span class="minutes-ago" data-minutes-ago="<?= $change['minutes'] ?>"></span>
                                </td>
                                <td>
                                    <?php if ('fulfilled' === $change['type']) { ?>
                                        <?php $wish_status = $statuses[Wish::STATUS_FULFILLED]; ?>
                                        <button class="ui <?= $wish_status['color'] ?> label still-need-it"
                                                type="button"
                                                data-wish-id="<?= $wish_id ?>"
                                                title="<?= __('Still need it') ?>"
                                        >
                                            <span class="default">
                                                <i class="<?= $wish_status['icon'] ?> icon"></i>
                                                <?= $wish_status['name'] ?>
                                            </span>
                                            <span class="hover">
                                                <i class="undo icon"></i>
                                                <?= __('Still need it') ?>
                                            </span>
                                        </button>
                                        <p><?= $wish_status['description'] ?></p>
                                    <?php } else { ?>
                                        <div class="ui grey label">
                                            <i class="trash icon"></i>
                                            <?= __('Deleted') ?>
                                        </div>
                                        <button class="ui mini labeled icon button restore-wish"
                                                type="button"
                                                data-wish-id="<?= $wish_id ?>"
                                        >
                                            <i class="undo icon"></i>
                                            <?= __('Restore') ?>
                                        </button>
                                        <p><?= __('You deleted this wish.') ?></p>
                                    <?php } ?>
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
        <?php } else { ?>
            <?php
            $wishes      = $wishlist->getSpoilers();
            $hasReceived = !empty(
                \array_filter(
                    $wishes,
                    function (Wish $wish) {
                        return null !== $wish->getReceived();
                    }
                )
            );
            ?>

            <div class="spoilers-empty" <?= empty($wishes) ? '' : 'hidden' ?>>
                <?= Page::info(__('No wishes on this list have been fulfilled or claimed yet.'), __('Nothing to spoil')) ?>
            </div>

            <?php if ($hasReceived) { ?>
                <div class="ui icon info message spoilers-received">
                    <i class="gift icon"></i>
                    <div class="content">
                        <div class="header">
                            <?= __('Received wishes') ?>
                        </div>
                        <p><?= __('Fulfilled wishes leave your list once your birthday or Christmas is over. They are kept here, so you can look up what you were given.') ?></p>
                        <p><?= __('Prefer a tidy list? Delete them. The people who gave them to you keep them in their gifts.') ?></p>
                        <button class="ui labeled icon button delete-received-all" type="button">
                            <i class="trash icon"></i>
                            <?= __('Delete all received') ?>
                        </button>
                    </div>
                </div>
            <?php } ?>

            <?php if (!empty($wishes)) { ?>
                <table class="ui celled table spoilers">
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
                                    <?php if (Wish::STATUS_FULFILLED === $wish->getStatus()) { ?>
                                        <button class="ui <?= $wish_status['color'] ?> label still-need-it"
                                                type="button"
                                                data-wish-id="<?= $wish->getId() ?>"
                                                title="<?= __('Still need it') ?>"
                                        >
                                            <span class="default">
                                                <i class="<?= $wish_status['icon'] ?> icon"></i>
                                                <?= $wish_status['name'] ?>
                                            </span>
                                            <span class="hover">
                                                <i class="undo icon"></i>
                                                <?= __('Still need it') ?>
                                            </span>
                                        </button>
                                    <?php } else { ?>
                                        <div class="ui <?= $wish_status['color'] ?> label">
                                            <i class="<?= $wish_status['icon'] ?> icon"></i>
                                            <?= $wish_status['name'] ?>
                                        </div>
                                    <?php } ?>
                                    <p><?= $wish_status['description'] ?></p>

                                    <?php if (null !== $wish->getReceived()) { ?>
                                        <div class="received">
                                            <div class="ui blue label">
                                                <i class="birthday cake icon"></i>
                                                <?= __('Received') ?>
                                                <div class="detail"><?= $dateFormatter->format($wish->getReceived()) ?></div>
                                            </div>
                                            <button class="ui mini labeled icon button delete-received"
                                                    type="button"
                                                    data-wish-id="<?= $wish->getId() ?>"
                                            >
                                                <i class="trash icon"></i>
                                                <?= __('Delete') ?>
                                            </button>
                                        </div>
                                    <?php } ?>
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
