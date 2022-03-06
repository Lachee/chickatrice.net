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
        <div class="title"><?= $profile->displayName ?>'s <?= $title ?></div>
        <?= GalleryList::widget(['galleries' => $galleries, 'grid' => true]); ?>
    </div>
</div>