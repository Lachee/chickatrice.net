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

?>


<div class="columns">
    <div class="column">
        <div class="title">Main Zone</div>
            <?php 
            foreach($cards as $identifier) {
                /** @var Identifier $identifier */
                $img = "https://api.scryfall.com/cards/{$identifier->scryfall_id}?format=image&image=small";
                //echo '<div class="tile">';
                //echo '<div class="card">';
                //echo '    <div class="card-image">';
                //echo '        <figure class="image is-4by3">';
                echo '        <img src="'.$img.'" alt="Placeholder image" width="100px">';
                // echo '        </figure>';
                // echo '    </div>';
                // echo '</div>';
                //echo '</div>';
            }
            ?>
    </div>
</div>