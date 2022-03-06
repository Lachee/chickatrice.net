<?php
namespace app\widget;

use kiss\helpers\HTML;
use kiss\Kiss;
use kiss\widget\Widget;

class Notification extends Widget {

    
    /** {@inheritdoc} */
    public function begin() {
        $notifications = Kiss::$app->session->consumeNotifications();
        if (count($notifications) > 0) { 
            echo '<section class="notifications toast content">';
            foreach($notifications as $notification) {
                $content = $notification['content'];
                $type = $notification['type'];
                if (isset($notification['html']) && $notification['html'] == true) 
                    $content = $notification['raw'];

                echo "<div class='notification is-{$type}'>";
                echo '<button class="delete" onclick="this.parentNode.classList.add(\'is-closed\')"></button>';
                echo $content;
                echo "</div>";
            }
            echo '</section>';
        }
    }

}