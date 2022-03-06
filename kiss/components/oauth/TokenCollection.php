<?php namespace kiss\components\oauth;

use kiss\models\BaseObject;

class TokenCollection extends BaseObject {
    protected $provider;
    
    public $access_token;
    public $token_type;
    public $expires_in;
    public $expires_at;
    public $refresh_token;
    public $scope;

    protected function init()
    {
        if (!empty($this->expires_at)) {
            //We have an expires at, so reverse it
            $this->expires_in = $this->expires_at - time();
        } else {
            //Otherwise calculate the at
            $this->expires_at = $this->expires_in + time();
        }
    }
}