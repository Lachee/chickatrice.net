<?php namespace app\widget;

use app\controllers\BaseController;
use app\models\User;
use kiss\controllers\Controller;
use kiss\helpers\Arrays;
use kiss\helpers\HTML;
use kiss\helpers\HTTP;
use kiss\helpers\Strings;
use kiss\widget\Widget;

class UptimeGraph extends Widget {
    
    public function init() {
        parent::init();
        $this->registerAssets();
    }

    public function begin() {
        $html = '';

        echo $html;
    }

    public function registerAssets() {
    }
}