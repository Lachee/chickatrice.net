<?php

use app\models\Gallery;
use app\models\Identifier;
use app\models\Image;
use app\models\Tag;
use app\widget\GalleryList;
use app\widget\ProfileCard;
use kiss\helpers\HTML;
use kiss\helpers\HTTP;

/** @var User $profile */
/** @var Deck $deck */
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
                            <li><a href="<?= HTTP::url(['/profile/:profile/decks', 'profile' => $profile->getUsername()]) ?>">Decks</a></li>
                            <li class="is-active"><a href="#" aria-current="page">Analysis</a></li>
                        </ul>
                    </nav>


                    <h1 class="title is-size-1">Deck Analysis</h1>
                    <h2 class="subtitle is-size-3">Coming Soon&trade;</h2>
                    <div class="block">
                        <p>Deck Analysis is coming soon! Stay tunned to get statistics about games involved, land ratios, special rulings and valid play modes!</p>
                    </div>
                    <div class="block">
                        <a class="button is-primary is-inverted is-outlined is-large" id="invite-button" target="_BLANK" href="https://discord.gg/py3Xbnv">
                            <span class="icon"><i class="fab fa-discord"></i></span>
                            <span>Give us Suggestions</span>
                        </a>
                    </div>

                </div>
                <div class="column is-one-third">
                    <figure class="image">
                        <img src="/images/cards-small.png" />
                    </figure>
                </div>
            </div>
        </div>
</section>