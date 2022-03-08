<?php

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
/** @var Deck[] $decks */

$decksAvailable = count($decks);
$maxDecksAvailable = 5;

?>

<style>
    img.is-rounded.is-landscape {
        width: auto;
        height: 100%;
    }
</style>

<section class="section container is-max-desktop">
    <nav class="breadcrumb" aria-label="breadcrumbs">
    <ul>
        <li><a href="<?= HTTP::url(['/profile/:profile/', 'profile' => $profile->uuid]) ?>"><span class="icon"><i class="fal fa-user"></i></span><?= HTML::encode($profile->getUsername()) ?></a></li>
        <li class="is-active"><a href="#" aria-current="page">Decks</a></li>
    </ul>
    </nav>

    <p class="block">
    <p>Storing <strong><?= $decksAvailable ?></strong> of <strong><?= $maxDecksAvailable ?></strong> decks</p>
    <p><I>Exceeding the capacity will result in newer decks being deleted within a week of upload.</I></p>
    <progress class="progress is-primary" value="<?= $decksAvailable ?>" max="<?= $maxDecksAvailable ?>"><?= intval(($decksAvailable / $maxDecksAvailable) * 100) ?>%</progress>

    </p>

    <div class="box">
        <h1 class="title is-4 mb-2">Decks</h1>

        <div class="list has-hoverable-list-items has-overflow-ellipsis">
            <?php foreach ($decks as $index => $deck) : ?>
                <div class="list-item <?= $index >= $maxDecksAvailable ? 'has-background-danger-light' : '' ?>">
                    <div class="list-item-image">
                        <figure class="image is-48x48 ">
                            <img class="is-rounded is-landscape" src="<?= $deck->getImageUrl() ?>&version=art_crop" alt="<?= HTML::encode($deck->name) ?> artwork">
                        </figure>
                    </div>

                    <div class="list-item-content ">
                        <div class="list-item-title is-flex is-justify-content-space-between">
                            <span><?= HTML::encode($deck->name) ?></span>
                            <span class="has-text-weight-normal has-text-grey "><?= $deck->getCardCount() ?> Cards</span>
                        </div>
                        <div class="list-item-description"><?= HTML::encode($deck->comment ?: 'No Comment') ?></div>
                    </div>

                    <div class="list-item-controls is-hidden-mobile">
                        <div class="buttons">
                            <button class="button" onclick="navigator.share({url: `<?= HTTP::url(['/profile/:profile/decks/:deck/', 'profile' => $profile->uuid, 'deck' => $deck ], true) ?>`});">
                                <span class="icon">
                                    <i class="fal fa-share-alt"></i>
                                </span>
                            </button>

                            <!--
                            <a class="button" href="">
                                <span class="icon">
                                    <i class="fal fa-trash"></i>
                                </span>
                            </a>
                            -->

                            <a class="button" href="<?= HTTP::url(['/profile/:profile/decks/:deck/', 'profile' => $profile->uuid, 'deck' => $deck]) ?>">
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
</section>