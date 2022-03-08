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

<style>
    .mtg-card {
        position: relative;
        width: 150px;
        height: 209px;
        float: left;
    }

    .mtg-card .mtg-card-title {
        position: absolute;
        top: 10px;
        left: 10px;
        right: 10px;
        color: white;
        font-weight: bold;
        text-shadow: black 0px 0px 6px;
        font-size: 8pt;

    }

    .mtg-card .mtg-card-count {
        position: absolute;
        bottom: 0;
        left: 0;
        background: #17140f;
        border-radius: 3px 3px 3px 6px;;
        width: 32px;
        height: 32px;
        text-align: center;
        padding-top: 2px;
        color: white;
    }
</style>

<section class="section container is-max-desktop">
    <nav class="breadcrumb" aria-label="breadcrumbs">
    <ul>
        <li><a href="<?= HTTP::url(['/profile/:profile/', 'profile' => $profile->uuid]) ?>"><span class="icon"><i class="fal fa-user"></i></span><?= HTML::encode($profile->getUsername()) ?></a></li>
        <li><a href="<?= HTTP::url(['/profile/:profile/decks', 'profile' => $profile->uuid]) ?>">Decks</a></li>
        <li class="is-active"><a href="#" aria-current="page"><?= HTML::encode($deck->name) ?></a></li>
    </ul>
    </nav>

    <p class="block"><?= HTML::encode($deck->comment) ?></p>
</section>

<div class="columns">
    <?php 
    $firstZone = true;
    foreach($deck->zones as $name => $cards) {
        echo HTML::begin('tag', [ 'class' => 'column ' . ($firstZone ? '' : 'is-one-third')]);
        {
            echo HTML::tag('div', HTML::encode($name), [ 'class' => 'title' ]);
            foreach($cards as $card)
            {                
                echo HTML::begin('div', [ 'class' => 'mtg-card' ]);
                {
                    if (isset($card['identifier'])) {
                        $imageURL = $card['identifier']->getImageUrl();
                        echo HTML::tag('img', '', ['src' => $imageURL]);
                    } else {
                        echo HTML::tag('img', '', ['src' => 'images/back.webp' ]);
                        echo HTML::tag('div', HTML::encode($card['name']), [ 'class' => 'mtg-card-title']);
                    }

                    if ($card['count'] > 1)
                        echo HTML::tag('span', 'x' . $card['count'], [ 'class' => 'mtg-card-count' ]);
                }
                echo HTML::end('div');
            }
        }
        echo HTML::end('tag');
        $firstZone = false;
    }
    ?>
</div>
