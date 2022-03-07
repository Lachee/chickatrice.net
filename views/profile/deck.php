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
        <?php 
        foreach($zones as $name => $deck) {
            echo HTML::tag('div', $name, [ 'class' => 'title' ]);
            foreach($deck as $card) 
            {
                /** @var Identifier $identifier */
                $identifier = $card['identifier'];
                $count = $card['count'];
                
                $imageUrl = "https://api.scryfall.com/cards/{$identifier->scryfall_id}?format=image";
                echo HTML::tag('img', null, [ 
                    'src'       => "{$imageUrl}&version=small", 
                    'alt-src'   => "{$imageUrl}&version=large",
                    'class'     => 'card',
                    'width'     => '100px',
                ]);

                //echo '<div class="tile">';
                //echo '<div class="card">';
                //echo '    <div class="card-image">';
                //echo '        <figure class="image is-4by3">';
                // echo '        </figure>';
                // echo '    </div>';
                // echo '</div>';
                //echo '</div>';
            }
            
        }
        ?>
    </div>
</div>