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

    /** @var \Mailgun\Mailgun $mail Mailgun client */
    public $mail = null;

    /** @var bool allows logged out users */
    public $allowVisitors = true;

    public $defaultAllowedDecks = 50;
    public $defaultAllowedReplays = 10;

    public $unlinkedAllowedDecks = 500;
    public $unlinkedAllowedReplays = 10;

    protected function init() {
        parent::init();

    }
    
} 