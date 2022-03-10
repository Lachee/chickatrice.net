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
$maxDecksAvailable = $profile->max_allowed_decks;
$precentage = $decksAvailable / $maxDecksAvailable;
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
            <li><a href="<?= HTTP::url(['/profile/:profile/', 'profile' => $profile->getUsername()]) ?>"><span class="icon"><i class="fal fa-user"></i></span><?= HTML::encode($profile->getUsername()) ?></a></li>
            <li class="is-active"><a href="#" aria-current="page">Decks</a></li>
        </ul>
    </nav>

    <?php if (Chickatrice::$app->loggedIn() && $profile->id === Chickatrice::$app->user->id): ?>
    <p class="block">
        <p>Storing <strong><?= $decksAvailable ?></strong> of <strong><?= $maxDecksAvailable ?></strong> decks</p>
        <p><I>Exceeding the capacity will result in <strong>NEWER</strong> decks being deleted within a week of upload.</I></p>
        <progress class="progress is-primary" value="<?= $decksAvailable ?>" max="<?= $maxDecksAvailable ?>"><?= intval($precentage * 100) ?>%</progress>
    </p>
    <?php endif; ?>

    <!-- Account Size Advert -->
    <?php if (Chickatrice::$app->loggedIn() && $profile->id === Chickatrice::$app->user->id): ?>
    <?php if ($precentage >= 1): ?>
    <section class="notification hero has-gradient is-info is-small">
        <div class="hero-body">
            <div class="container">
                <h1 class="title">Need Space?</h1>
                <p class="subtitle">Contact Lachee on how you can get your account limits raised.</p>
                <p>At the moment, all decks in red will be deleted within the week. Lachee will be increasing limits on extordanary cases, or with reimbursements via her Kofi.</p>
            </div>
        </div>
    </section>
    <?php endif; ?>
    <?php else: ?>
    <section class="notification is-info is-small">
        <div class="body">
            <div class="container">
                <p>Currently browsing another user's deck list. </p>
                <p><a href="/profile/@me/decks">Click Here</a> to go to your own decks.</p>
            </div>
        </div>
    </section>
    <?php endif; ?>

    <div class="box">
        <h1 class="title is-4 mb-2">Decks</h1>

        <div class="list has-hoverable-list-items">
            <?php foreach ($decks as $index => $deck) : ?>
                <div class="list-item <?= $index >= $maxDecksAvailable ? 'has-background-danger-light' : '' ?>">
                    <div class="list-item-image">
                        <figure class="image is-48x48 ">
                            <img class="is-rounded is-landscape" src="<?= $deck->getImageUrl() ?>&version=art_crop" alt="<?= HTML::encode($deck->name) ?> artwork">
                        </figure>
                    </div>

                    <div class="list-item-content is-hidden-mobile">
                        <div class="list-item-title is-flex is-justify-content-space-between">
                            <span><?= HTML::encode($deck->name) ?></span>
                            <span class="has-text-weight-normal has-text-grey "><?= $deck->getCardCount() ?> Cards</span>
                        </div>
                        <div class="list-item-description"><?= HTML::encode($deck->comment ?: 'No Comment') ?></div>
                    </div>

                    <div class="list-item-controls">
                        <div class="buttons">
                            <button class="button" onclick="navigator.share({url: `<?= HTTP::url(['/profile/:profile/decks/:deck/', 'profile' => $profile->getUsername(), 'deck' => $deck ], true) ?>`});">
                                <span class="icon">
                                    <i class="fal fa-share-alt"></i>
                                </span>
                            </button>

                            <?php if (Chickatrice::$app->loggedIn() && $profile->id === Chickatrice::$app->user->id): ?>
                            <a class="button" href="<?= HTTP::url(['/profile/:profile/decks/:deck/remove', 'profile' => $profile->getUsername(), 'deck' => $deck->id]) ?>" onclick="return confirm('Are you sure? This cannot be undone!')">
                                <span class="icon">
                                    <i class="fal fa-trash"></i>
                                </span>
                            </a>
                            <?php endif; ?>
                            
                            <a class="button" href="<?= HTTP::url(['/profile/:profile/decks/:deck/', 'profile' => $profile->getUsername(), 'deck' => $deck]) ?>">
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