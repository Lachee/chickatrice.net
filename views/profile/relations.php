<?php

use app\models\cockatrice\Account;
use app\models\cockatrice\Deck;
use app\models\Gallery;
use app\models\Identifier;
use app\models\Image;
use app\models\Tag;
use app\widget\GalleryList;
use app\widget\ProfileCard;
use kiss\helpers\HTML;
use kiss\helpers\HTTP;

/** @var User $profile */
/** @var string[] $online_ids */
/** @var Account[] $buddies */
/** @var Account[] $ignores */

HTML::$title = 'Relations';
?>

<style>
    img.is-rounded.is-landscape {
        width: auto;
        height: 100%;
    }
    .image.is-profile { 
        background: white;
        background-image: url('/images/placeholder_profile.png');
        background-size: contain;
    }
    .image.is-profile img {
        background: white;
    }
    .image.is-rounded, .image.is-rounded img{
        border-radius: 9999px;
    }
</style>

<section class="section container is-max-desktop">

    <nav class="breadcrumb" aria-label="breadcrumbs">
        <ul>
            <li><a href="<?= HTTP::url(['/profile/:profile/', 'profile' => $profile->getUsername()]) ?>"><span class="icon"><i class="fal fa-user"></i></span><?= HTML::encode($profile->getUsername()) ?></a></li>
            <li class="is-active"><a href="#" aria-current="page">Relations</a></li>
        </ul>
    </nav>
    <div class="columns">
    <div class="column">
            <div class="box">
                <h1 class="title is-4 mb-2">Buddies</h1>
                <div class="list has-hoverable-list-items ">
                    <?php foreach ($buddies as $index => $buddy): ?>
                        <div class="list-item">
                            <div class="list-item-image">
                                <figure class="image is-profile is-48x48 is-rounded ">
                                    <img  src="<?= $buddy->getAvatarDataUrl() ?>" alt="">
                                    <?php if (in_array($buddy->id, $online_ids)): ?>
                                        <span class="badge is-online" title="Online"></span>
                                    <?php endif; ?>
                                </figure>
                            </div>

                            <div class="list-item-content is-hidden-mobile">
                                <div class="list-item-title is-flex is-justify-content-space-between">
                                    <span><?= HTML::encode($buddy->name) ?></span>
                                    <span class="has-text-weight-normal has-text-grey "></span>
                                </div>
                                <div class="list-item-description"><?= HTML::encode($buddy->realname) ?></div>
                            </div>

                            <div class="list-item-controls">
                                <div class="buttons">
                                
                                    <?php if (Chickatrice::$app->loggedIn() && $profile->id === Chickatrice::$app->user->id): ?>
                                    <a class="button" href="<?= HTTP::url(['/profile/@me/relations', 'rf' => $buddy->id]) ?>" onclick="return confirm('Are you sure?')">
                                        <span class="icon">
                                            <i class="fal fa-trash"></i>
                                        </span>
                                    </a>
                                    <?php endif; ?>

                                    <a class="button" href="<?= HTTP::url(['/profile/:profile/decks', 'profile' => $buddy->name ]) ?>">
                                        <span class="icon">
                                            <i class="fal fa-eye"></i>
                                        </span>
                                    </a>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <div class="column">
            <div class="box">
                <h1 class="title is-4 mb-2">Ignores</h1>
                <div class="list has-hoverable-list-items ">
                    <?php foreach ($ignores as $index => $ignore): ?>
                        <div class="list-item">
                            <div class="list-item-image">
                                <figure class="image is-profile is-48x48 is-rounded ">
                                    <img  src="/images/ignore_profile.png" alt="">
                                </figure>
                            </div>

                            <div class="list-item-content is-hidden-mobile">
                                <div class="list-item-title is-flex is-justify-content-space-between">
                                    <span><?= HTML::encode($ignore->name) ?></span>
                                    <span class="has-text-weight-normal has-text-grey "></span>
                                </div>
                                <div class="list-item-description"><?= HTML::encode($ignore->realname) ?></div>
                            </div>

                            <div class="list-item-controls">
                                <div class="buttons">
                                
                                    <?php if (Chickatrice::$app->loggedIn() && $profile->id === Chickatrice::$app->user->id): ?>
                                    <a class="button" href="<?= HTTP::url(['/profile/@me/relations', 'rf' => $ignore->id]) ?>" onclick="return confirm('Are you sure?')">
                                        <span class="icon">
                                            <i class="fal fa-trash"></i>
                                        </span>
                                    </a>
                                    <?php endif; ?>

                                    <a class="button" href="<?= HTTP::url(['/profile/:profile/decks', 'profile' => $ignore->name ]) ?>">
                                        <span class="icon">
                                            <i class="fal fa-eye"></i>
                                        </span>
                                    </a>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
</section>