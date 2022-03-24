<?php

use app\models\cockatrice\Deck;
use kiss\helpers\{ HTML, HTTP, Strings };
use app\models\{ cockatrice\Account, User };
/** @var User $user */
/** @var Account $account */
/** @var int $privacy */
/** @var Deck[] $decks */

$decksAvailable = count($decks);
$maxDecksAvailable = $user ? $user->max_allowed_decks : 999999;
$precentage = $decksAvailable / $maxDecksAvailable;

HTML::$title = Chickatrice::$app->title . " || " . $account->name;
HTML::$meta = [
    'description' => 'List of public decks on ' . ucfirst($account->name) . '\'s account',
    'image'       => HTTP::url(['/profile/:profile/avatar', 'profile' => $user ? $user->uuid : $account->id], true),
];
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
            <li><a href="<?= HTTP::url(['/profile/:profile/', 'profile' => $user ? $user->getUsername() : $account->name ]) ?>"><span class="icon"><i class="fal fa-user"></i></span><?= HTML::encode($user ? $user->getUsername() : $account->name ) ?></a></li>
            <li class="is-active"><a href="#" aria-current="page">Decks</a></li>
        </ul>
    </nav>

    <?php if (Chickatrice::$app->loggedIn() && $user && $user->id === Chickatrice::$app->user->id): ?>
        <p class="block">
            <p>Storing <strong><?= $decksAvailable ?></strong> of <strong><?= $maxDecksAvailable ?></strong> decks</p>
            <p><I>Exceeding the capacity will result in <strong>NEWER</strong> decks being deleted within a week of upload.</I></p>
            <progress class="progress is-primary" value="<?= $decksAvailable ?>" max="<?= $maxDecksAvailable ?>"><?= intval($precentage * 100) ?>%</progress>
        </p>
    
        <form method="GET" action="<?= HTTP::url(['importDeck']) ?>">
            <nav class="level">
                <div class="level-left"></div>
                <div class="level-right">
                    <div class="level-item field has-addons is-fullwidth" >
                        <div class="control">
                            <input class="input" name="mox" type="text" placeholder="Moxfield url or id...">
                        </div>
                        <div class="control">
                            <button class="button is-secondary has-icon" type="submit">
                                <span class="icon"><i class="fal fa-file-import"></i></span>
                                <span>Moxfield</span>
                            </button>
                        </div>
                    </div>
                </div>
            </nav>
        </form>
    
    <?php endif; ?>

    <!-- Account Size Advert -->
    <?php if (Chickatrice::$app->loggedIn() && $user && $user->id === Chickatrice::$app->user->id): ?>
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
        
                            <?php if ($privacy >= 1 || ($user && $user->id === Chickatrice::$app->user->id)): ?>
                            <a class="button" href="<?= HTTP::url(['/profile/:profile/decks/:deck/download', 'profile' => $this->name, 'deck' => $deck]) ?>">
                                <span class="icon">
                                    <i class="fal fa-download"></i>
                                </span>
                            </a>
                            <?php endif; ?>
                            
                            <a class="button" href="<?= HTTP::url(['/profile/:profile/decks/:deck/', 'profile' => $this->name, 'deck' => $deck]) ?>">
                                <span class="icon">
                                    <i class="fal fa-eye"></i>
                                </span>
                            </a>

                            <a class="button" href="<?= HTTP::url(['/profile/:profile/decks/:deck/analytics',  'profile' => $this->name, 'deck' => $deck]) ?>">
                                <span class="icon">
                                    <i class="fal fa-analytics"></i>
                                </span>
                            </a>
                        </div>
                    </div>
                </div>

            <?php endforeach; ?>

        </div>
    </div>
</section>