<?php

use kiss\helpers\{ HTML, HTTP, Strings };
use app\models\{ cockatrice\Account, User };
/**
 * @var User $user
 * @var Account $account
*/


HTML::$title = Chickatrice::$app->title . " || " . $account->name;
HTML::$meta = [
    'description' => ucfirst($account->name) . '\'s public profile.',
    'image'       => HTTP::url(['/profile/:profile/avatar', 'profile' => $user ? $user->uuid : $account->id], true),
];

?>
<style>
    .image.is-rounded {
        border-radius: 10px;
        overflow: hidden;
    }
</style>

<section class="hero is-info is-fullheight-with-navbar">
    <div class="hero-body">
        <div class="container">
            <div class="columns">
                <div class="column is-one-fifth"></div>
                <div class="column" id="site-heading">
                    <h1 class="title is-size-1"><?= HTML::encode(ucfirst($account->name)) ?>'s Profile</h1>
                    <h2 class="subtitle is-size-3">Coming Soon&trade;</h2>
                    <div class="block">
                        <p>Public user profiles are coming soon. View common colours, deck types, favourite cards and more.</p>
                    </div>
                    <div class="block">
                        <a class="button is-primary is-inverted is-outlined is-large" id="invite-button" target="_BLANK" href="https://discord.gg/py3Xbnv">
                            <span class="icon"><i class="fab fa-discord"></i></span>
                            <span>Give us Suggestions</span>
                        </a>
                    </div>

                </div>
                <div class="column is-one-third">
                    <figure class="image is-profile is-rounded ">
                        <img  src="<?= $user ? $user->getAvatarUrl() : $account->getAvatarDataUrl() ?>" alt="">
                    </figure>
                </div>
            </div>
        </div>
</section>