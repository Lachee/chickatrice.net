<?php

use app\models\Gallery;
use app\models\Tag;
use kiss\helpers\Arrays;
use kiss\helpers\HTML;
use kiss\helpers\HTTP;
use kiss\helpers\Strings;
use kiss\Kiss;

$user = Kiss::$app->getUser();

?>

<!-- START NAV -->
<nav class="navbar ">
    <div class="support-flags">
        <!--<div class="ukraine"></div>-->
        <!--<div class="trans"></div>-->
    </div>
    <div class="container">
        <!-- BRAND -->
        <div class="navbar-brand is-justify-content-space-evenly">
            <a class="navbar-item brand-text is-tab <?= HTTP::route() != '/' ?: 'is-active' ?>" href="<?= HTTP::url('/')?>"><img src="<?= Kiss::$app->logo ?>" data-tooltip="Home" /></a>

            <a class="navbar-item has-icon is-tab <?= !Strings::startsWith(HTTP::route(), '/stats') ?: 'is-active' ?>" data-tooltip="Stats" href="<?= HTTP::url('/stats')?>">
                <span class="icon"><i class="fal fa-chart-area"></i></span>
                <span class="is-hidden-touch">Statistics</span>
            </a>

            <?php if ($user): ?>
                <a class="navbar-item has-icon is-tab <?= !Strings::startsWith(HTTP::route(), '/profile/@me/replays') ?: 'is-active' ?>" data-tooltip="Replays" href="<?= HTTP::url('/profile/@me/replays')?>">
                    <span class="icon"><i class="fal fa-cassette-tape"></i></span>
                    <span class="is-hidden-touch">Replays</span>
                </a>
                <a class="navbar-item has-icon is-tab <?= !Strings::startsWith(HTTP::route(), '/profile/@me/decks') ?: 'is-active' ?>" data-tooltip="Decks" href="<?= HTTP::url('/profile/@me/decks')?>">
                    <span class="icon"><i class="fal fa-book-spells"></i></span>
                    <span class="is-hidden-touch">Decks</span>
                </a>
                <a class="navbar-item has-icon is-tab <?= !Strings::startsWith(HTTP::route(), '/profile/@me/relations') ?: 'is-active' ?>" data-tooltip="Relations" href="<?= HTTP::url('/profile/@me/relations')?>">
                    <span class="icon"><i class="fal fa-user-friends"></i></span>
                    <span class="is-hidden-touch">Relations</span>
                </a>

                <!-- Profile ( Hidden Desktop ) -->
                <a class="navbar-item has-icon is-tab is-hidden-desktop <?= !Strings::endsWith(HTTP::route(), '/profile/@me/settings') ?: 'is-active' ?>" data-tooltip="Settings" href="<?= HTTP::url('/profile/@me/settings')?>">
                    <i class="fal fa-cogs"></i>
                </a>    
            <?php else: ?>
                <!-- Login for mobile only -->
                <a class="navbar-item has-icon is-tab is-hidden-desktop <?= !Strings::endsWith(HTTP::route(), '/login') ?: 'is-active' ?>" data-tooltip="Login" href="<?= HTTP::url('/login')?>">
                    <i class="fal fa-user"></i>
                </a>    
            <?php endif; ?>
        </div>

        <!-- RHS ITEMS ( Hidden Mobile ) -->
        <div id="navMenu" class="navbar-end is-hidden-touch">
            <div class="navbar-start">
                <div class="navbar-item">
                    <?php if ($user): ?>
                        <!-- LOGGED IN USERS -->
                        <div class="field has-addons"> 
                            <!-- Webatrice Control: Disabled on Zach's request
                            <p class="control">
                                <a class="button" href="<?= HTTP::url(['/game']); ?>"  data-tooltip="Play on Webatrice">
                                    <span class="icon"><i class="fal fa-play"></i></span>
                                </a>
                            </p>
                            --> 
                            <p class="control">
                                <a class="button" id="login-button" href="<?= HTTP::url(['/profile/@me/']); ?>"  data-tooltip="Profile">
                                    <span class="icon"><i class="fal fa-user"></i></span>
                                    <span><?= HTML::encode($user->username) ?></span>
                                </a>
                            </p>
                            <p class="control">
                                <a class="button" href="<?= HTTP::url(['/profile/@me/settings']); ?>"  data-tooltip="Settings">
                                    <span class="icon"><i class="fal fa-cog"></i></span>
                                </a>
                            </p>

                            <p class="control">
                                <a class="button" href="<?= HTTP::url(['/logout']); ?>"  data-tooltip="Logout">
                                    <span class="icon"><i class="fal fa-sign-out"></i></span>
                                </a>
                            </p>
                        </div>
                    <?php else: ?>
                        <!-- GUEST USERS -->
                        <div class="field has-addons">
                            <p class="control">
                                <a class="button" id="login-button" href="<?= HTTP::url(['/login' ]); ?>" data-tooltip="Login">
                                    <span class="icon"><i class="fal fa-sign-in"></i></span>
                                    <span>Login</span>
                                </a>
                            </p>                 
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

    </div>
</nav>
<!-- END NAV -->
