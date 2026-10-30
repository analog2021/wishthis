<?php

/**
 * power.php
 *
 * @author Jay Trees <github.jay@grandel.anonaddy.me>
 */

namespace wishthis;

$page = new Page(__FILE__, __('Insufficient power'));
$page->header();
$page->bodyStart();
$page->navigation();

/** Administrators have a power of 100, see `User::$power`. */
$requiresAdmin = (int) ($_GET['required'] ?? 0) >= 100;
?>

<main>
    <div class="ui container">
        <h1 class="ui header"><?= $page->title ?></h1>

        <div class="ui segment">
            <h2 class="ui header"><?= __('Restricted access') ?></h2>
            <p><?= $requiresAdmin ? __('Admin permissions required - access denied.') : __('Access denied.') ?></p>
        </div>
    </div>
</main>

<?php
$page->bodyEnd();
?>
