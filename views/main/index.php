<?php

use kiss\helpers\HTML;
use kiss\helpers\HTTP;
use kiss\Kiss;

?>


<section class="hero is-medium is-dark is-bold is-fullheight-with-navbar">
    <div class="hero-body">
        <div class="container">
            <div class="columns">
                <div class="column is-one-fifth"></div>
                <div class="column" id="site-heading">
                    <h1 class="title is-size-1">Chickatrice</h1>
                    <h2 class="subtitle is-size-3">Socially share images in Discord</h2>
                    <a class="button is-primary is-inverted is-outlined is-large" id="login-button" href="<?= HTTP::url([Kiss::$app->loggedIn() ? '/gallery/' : '/login']); ?>">
                        <span class="icon"><i class="fal fa-share"></i></span>
                        <span>Start Sharing</span>
                    </a>
                    <a class="button is-primary is-inverted is-outlined is-large" id="invite-button" href="https://discord.gg/py3Xbnv">
                        <span class="icon"><i class="fab fa-discord"></i></span>
                        <span>Join Server</span>
                    </a>
                </div>
                <div class="column is-one-third">
                </div>
            </div>
        </div>
    </div>
</section>