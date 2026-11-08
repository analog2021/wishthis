<?php

namespace wishthis;

// The forms on the profile page. Each one has its own handler file.
$profileSections = [
    'personal',
    'password',
    'preferences',
    'received',
    'account',
];

$section = $_POST['section'] ?? null;

if (!\is_string($section) || !\in_array($section, $profileSections, true)) {
    return;
}

$loginRequired = false;
$userId        = $user->getId();

require __DIR__ . '/profile-handle-post-' . $section . '.php';

if ($loginRequired) {
    \session_destroy();
    unset($_SESSION);

    $page->messages[] = Page::warning(
        __('Your account credentials have changed and you have been logged out. Please log in again.'),
        __('Account credentials changed')
    );
}
