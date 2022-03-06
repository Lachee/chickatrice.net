<?php
namespace app\widget;

use kiss\helpers\HTML;
use kiss\helpers\HTTP;
use kiss\widget\Widget;

class Menu extends Widget {

    public $items = [
            'General' => [  
                [ 'title' => 'Dashboard', 'url' => [ '/management/' ] ],
                [ 'title' => 'Definitions', 'url' => [ '/management/definition/' ], 'items' => [
                    ['title' => 'Value Types', 'url' => ['/management/type/' ]]
                    //['title' => 'Events', 'url' => ['/manager/definition/events' ]],
                    //['title' => 'Classes', 'url' => ['/manager/definition/classes' ]],
                ]],
                [ 'title' => 'Font Awesome', 'url' => 'https://fontawesome.com/cheatsheet/pro/' ]
            ],
            //'Administration' => [
            //    [ 'title' => 'Team Settings', 'url' => [ '/manager/teams/' ] ],
            //    [ 'title' => 'Manage Your Team', 'url' => ['/manager/teams/me' ], 'items' => [                    
            //        [ 'title' => 'Members', 'url' => [ '/manager/teams/me/potato' ] ],
            //        [ 'title' => 'Plugins', 'url' => [ '/manager/teams/me/apricot' ] ],
            //    ]]
            //]
        ];
    

    /** {@inheritdoc} */
    public function begin() {
        echo "<aside class='menu'>";
        $this->renderGroups($this->items);
        echo "</aside>";
    }


    private function renderGroups($groups) {
        foreach($groups as $name => $items) {
            echo "<p class='menu-label'>{$name}</p>";
            echo "<ul class='menu-list'>";
            $this->renderItems($items);
            echo "</ul>";
        }
    }

    private function renderItems($items) {
        foreach($items as $item) { 
            $route  = self::lob(HTTP::url(HTTP::route(), true));
            $url    = self::lob(HTTP::url($item['url'], true));
            $options = isset($items['options']) ? $items['options'] : [];

            $class = $url == $route ? 'is-active' : '';
            echo "<li>";
            echo "<a class='{$class}' href='" . $url . "' >" . HTML::encode($item['title']) . "</a>";
            if (isset($item['items'])) {                
                echo "<ul class='menu-list menu-sublist'>";
                $this->renderItems($item['items']);
                echo "</ul>";
            }
            echo "</li>";
        }
    }

    private static function lob($route) {
        $l = strrpos($route, '/');
        if ($l === false) return $route;
        return substr($route, 0, $l + 1);
    }
}