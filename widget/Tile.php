<?php
namespace app\widget;

use kiss\helpers\HTML;
use kiss\Kiss;
use kiss\widget\Widget;

class Tile extends Widget {

    public $isParent = true;
    public $options = [];
        
    public $title = null;
    public $subtitle = null;

    /** {@inheritdoc} */
    public function begin() {
        $options = $this->options;
        HTML::addCssClass($options, ['tile', 'is-parent']);
        echo HTML::begin('div', $options);
        echo HTML::begin('article', [ 'class' => 'tile is-child box']);

        if (!empty($this->title)) 
            echo HTML::p($this->title, ['class' => 'title']);

        if (!empty($this->subtitle))
            echo HTML::p($this->subtitle, ['class' => 'subtitle']);

        echo HTML::begin('div', [ 'class' => 'content']);
    }

    /** {@inheritdoc} */
    public function end()
    {
        echo HTML::end('div');      //end content
        echo HTML::end('article');  //end article
        echo HTML::end('div');      //end to;e
    }
}