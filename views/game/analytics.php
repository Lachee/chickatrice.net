<?php

use app\models\cockatrice\ReplayGame;
use app\models\User;
use kiss\helpers\HTML;
use kiss\helpers\HTTP;

/** @var User $profile */
/** @var ReplayGame $replay */
?>



<section class="hero is-info is-fullheight-with-navbar">
    <div class="hero-body">
        <div class="container">
            <div class="columns">
                <div class="column is-one-fifth"></div>
                <div class="column" id="site-heading">
                    <nav class="breadcrumb" aria-label="breadcrumbs">
                        <ul>
                            <li><a href="<?= HTTP::url(['/profile/:profile/', 'profile' => $profile->getUsername()]) ?>"><span class="icon"><i class="fal fa-user"></i></span><?= HTML::encode($profile->getUsername()) ?></a></li>
                            <li><a href="<?= HTTP::url(['/profile/:profile/games', 'profile' => $profile->getUsername()]) ?>">Games</a></li>
                            <li class="is-active"><a href="#" aria-current="page">Analysis</a></li>
                        </ul>
                    </nav>

                    <h1 class="title is-size-1">Replay Analysis</h1>
                    <h2 class="subtitle is-size-3">Coming Soon&trade;</h2>
                    <div class="block">
                        <p>Learn how well you played, the average powers, card frequency, overall win rates, and much more. Replay Analysis coming soon!</p>
                    </div>
                    <div class="block">
                        <a class="button is-primary is-inverted is-outlined is-large" id="invite-button" target="_BLANK" href="https://discord.gg/py3Xbnv">
                            <span class="icon"><i class="fab fa-discord"></i></span>
                            <span>Give us Suggestions</span>
                        </a>
                    </div>

                </div>
                <div class="column is-one-third">
                    <figure class="image is-bobbing">
                        <img src="/images/cards-small.png" />
                    </figure>
                </div>
            </div>
        </div>
</section>