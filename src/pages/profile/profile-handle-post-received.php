<?php

/**
 * The "Received wishes" option in the "Preferences" section of the users
 * profile.
 */

namespace wishthis;

/**
 * Tidy up after my birthday
 *
 * Only possible with a birthday set, the checkbox is disabled otherwise.
 */
if ('' !== $user->getBirthdate()) {
    $userRemoveReceivedWishes = isset($_POST['remove-received-wishes']);

    if ($userRemoveReceivedWishes !== $user->getRemoveReceivedWishes()) {
        $database->query(
            'UPDATE `users`
                SET `remove_received_wishes` = :remove_received_wishes
              WHERE `id` = :user_id',
            [
                'remove_received_wishes' => $userRemoveReceivedWishes,
                'user_id'                => $userId,
            ]
        );

        $user->setRemoveReceivedWishes($userRemoveReceivedWishes);

        /** Check again on the next page. */
        unset($_SESSION['received_checked']);

        $page->messages[] = Page::success(
            $userRemoveReceivedWishes
                ? __('Fulfilled wishes will leave your lists once your birthday or Christmas is over.')
                : __('Fulfilled wishes will stay on your lists.'),
            __('Success')
        );
    }
}
