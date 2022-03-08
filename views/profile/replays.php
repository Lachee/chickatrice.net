<?php

use app\models\cockatrice\Deck;
use app\models\cockatrice\ReplayAccess;
use app\models\cockatrice\ReplayGame;
use app\models\Gallery;
use app\models\Identifier;
use app\models\Image;
use app\models\Tag;
use app\widget\GalleryList;
use app\widget\ProfileCard;
use kiss\helpers\HTML;
use kiss\helpers\HTTP;

/** @var User $profile */
/** @var ReplayGame[] $replays */

$replaysAvailable = count($replays);
$maxReplaysAvailable = $profile->max_allowed_replays;
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
            <li class="is-active"><a href="#" aria-current="page">Games</a></li>
        </ul>
    </nav>

    <p class="block">
        <p>Storing <strong><?= $replaysAvailable ?></strong> of <strong><?= $maxReplaysAvailable ?></strong> replays</p>
        <p><I>Exceeding the capacity will result in <strong>OLDER</strong> replays being deleted within a week.</I></p>
        <progress class="progress is-primary" value="<?= $replaysAvailable ?>" max="<?= $maxReplaysAvailable ?>"><?= intval(($replaysAvailable / $maxReplaysAvailable) * 100) ?>%</progress>
    </p>

    <div class="box">
        <h1 class="title is-4 mb-2">Replays</h1>

        <div class="list has-hoverable-list-items has-overflow-ellipsis">
            <?php foreach ($replays as $index => $replay) : ?>
                <div class="list-item <?= $index >= $maxReplaysAvailable ? 'has-background-danger-light' : '' ?>">
                    <div class="list-item-content ">
                        <div class="list-item-title is-flex is-justify-content-space-between">
                            <span><?= HTML::encode($replay->description) ?></span>
                            <div>
                                <span class="has-text-weight-normal has-text-grey mr-5"><?= HTML::encode($replay->game_types) ?></span>
                                <span class="has-text-weight-normal has-text-grey "><?= $replay->time_started ?></span>
                            </div>
                        </div>
                        <div class="list-item-description"><?= HTML::encode(join(', ', $replay->players)) ?></div>
                    </div>

                    <div class="list-item-controls is-hidden-mobile">
                        <div class="buttons">
                            <a class="button" href="<?= HTTP::url(['games', 'remove' => $replay->id]) ?>" onclick="return confirm('Are you sure?')">
                                <span class="icon">
                                    <i class="fal fa-trash"></i>
                                </span>
                            </a>

                            <a class="button" href="<?= HTTP::url(['games', 'download' => $replay->id]) ?>">
                                <span class="icon">
                                    <i class="fal fa-download"></i>
                                </span>
                            </a>
                        </div>
                    </div>
                </div>

            <?php endforeach; ?>

        </div>
    </div>
</section>