<?php

/**
 * Allows administrators to login as a user. For debugging purposes.
 *
 * @author Jay Trees <github.jay@grandel.anonaddy.me>
 */

namespace wishthis;

/**
 * While impersonating, the current user is usually not an administrator, so
 * the page has to stay reachable for them to switch back.
 */
$impersonatorId = $_SESSION['impersonator'] ?? 0;

$page = new Page(__FILE__, __('Login as'), 0 !== $impersonatorId ? 1 : 100);
$user = User::getCurrent();

$getUserFields = function (string $column, int|string $value) use ($database): array|false {
    return $database
    ->query(
        'SELECT *
           FROM `users`
          WHERE `' . $column . '` = :value;',
        [
            'value' => $value,
        ]
    )
    ->fetch(\PDO::FETCH_ASSOC);
};

$impersonatorFields = 0 !== $impersonatorId ? $getUserFields('id', $impersonatorId) : false;

if (0 !== $impersonatorId && (false === $impersonatorFields || 100 !== (int) $impersonatorFields['power'])) {
    unset($_SESSION['impersonator']);

    redirect(Page::PAGE_POWER . '&required=100');
}

/**
 * Point the current session at another user. `loadFromSession` rebuilds the
 * user from the `sessions` table on every request, so that row has to change
 * as well, not just `$_SESSION`.
 */
$switchTo = function (array $fields) use ($database): User {
    $database
    ->query(
        'UPDATE `sessions`
            SET `user`    = :user
          WHERE `session` = :session;',
        [
            'user'    => $fields['id'],
            'session' => $_COOKIE['wishthis_session'],
        ]
    );

    $_SESSION['user'] = new User($fields);

    return $_SESSION['user'];
};

if (isset($_POST['return']) && false !== $impersonatorFields) {
    $user = $switchTo($impersonatorFields);

    unset($_SESSION['impersonator']);

    $impersonatorFields = false;
    $returned           = true;
} elseif (isset($_POST['email'])) {
    $email  = Sanitiser::getEmail($_POST['email']);
    $fields = $getUserFields('email', $email);

    $success = false !== $fields;

    if ($success) {
        if (false === $impersonatorFields && $user->getId() !== (int) $fields['id']) {
            $_SESSION['impersonator'] = $user->getId();

            $impersonatorFields = $getUserFields('id', $user->getId());
        }

        if (false !== $impersonatorFields && (int) $impersonatorFields['id'] === (int) $fields['id']) {
            unset($_SESSION['impersonator']);

            $impersonatorFields = false;
        }

        $user = $switchTo($fields);
    }
}

$page->header();
$page->bodyStart();
$page->navigation();

/**
 * Recent users
 */
$users = $database
->query(
    '  SELECT *
         FROM `users`
     ORDER BY `last_login` DESC
        LIMIT 100;'
)
->fetchAll();
?>
<main>
    <div class="ui container">
        <h1 class="ui header"><?= $page->title ?></h1>

        <?php
        if (isset($success)) {
            if ($success) {
                echo Page::success(\sprintf(__('Successfully logged in as %s.'), $user->getEmail()), __('Success'));
            } else {
                echo Page::error(__('User not found!'), __('Error'));
            }
        }

        if (isset($returned)) {
            echo Page::success(\sprintf(__('Successfully logged in as %s.'), $user->getEmail()), __('Success'));
        }
        ?>

        <?php if (false !== $impersonatorFields) { ?>
            <div class="ui info message">
                <div class="header"><?= __('Impersonating') ?></div>
                <p><?= \sprintf(__('You are logged in as %s on behalf of %s.'), $user->getEmail(), $impersonatorFields['email']) ?></p>

                <form method="POST">
                    <input class="ui button"
                           type="submit"
                           name="return"
                           value="<?= __('Return to admin') ?>"
                           title="<?= __('Return to admin') ?>"
                    />
                </form>
            </div>
        <?php } ?>

        <div class="ui segment">
            <form class="ui form" method="POST">
                <div class="field">
                    <label><?= __('Email') ?></label>

                    <select class="ui fluid search selection dropdown" name="email">
                        <?php foreach ($users as $userRow) { ?>
                            <option value="<?= $userRow['email'] ?>"><?= $userRow['email'] ?></option>
                        <?php } ?>
                    </select>
                </div>

                <input class="ui primary button"
                       type="submit"
                       value="<?= __('Login') ?>"
                       title="<?= __('Login') ?>"
                />
            </form>
        </div>
    </div>
</main>

<?php
$page->bodyEnd();
