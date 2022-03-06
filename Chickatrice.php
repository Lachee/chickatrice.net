<?php

use kiss\Kiss;

/**
 * @property Chickatrice $app
 * @property \app\components\discord\Discord $discord Discord API instance
 * @property \app\components\Scraper $scraper content scraper
 * @property \app\models\User $user current user
 * @method \app\models\User getUser() gets the currently signed in user 
 * 
 */
class Chickatrice extends Kiss {

    /** @var bool allows logged out users */
    public $allowVisitors = true;

    protected function init() {
        parent::init();

    }
    
} 