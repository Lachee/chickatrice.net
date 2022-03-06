<?php

use app\models\Gallery;
use app\models\Image;
use app\models\Tag;
use app\widget\GalleryList;
use app\widget\ProfileCard;
use kiss\helpers\HTML;
use kiss\helpers\HTTP;

/** @var User $profile */

?>


<div class="columns">
    <div class="column is-one-fifth pt-4">
       <?= ProfileCard::widget(['profile' => $profile ]); ?>

    </div>
    <div class="column is-four-fifths">
        <div class="title">Best Submissions</div>
        <div class="subtitle">Collection of their best submissions - <a href="<?= HTTP::url(['/profile/:id/submissions', 'id' => $profile->profileName ]) ?>">View All</a></div>
        <?= GalleryList::widget(['galleries' => $submissions, 'grid' => false]); ?>
        
        <div class="title">Favourites</div>
        <div class="subtitle">Collection of their favourited galleries - <a href="<?= HTTP::url(['/profile/:id/favourites', 'id' => $profile->profileName ]) ?>">View All</a></div>
        <?= GalleryList::widget(['galleries' => $favourites, 'grid' => count($favourites) <= 3 || count($favourites) > 10]); ?>
    </div>
</div>