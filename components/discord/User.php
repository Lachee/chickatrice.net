<?php namespace app\components\discord;

use kiss\models\BaseObject;

class User extends BaseObject {
    public $discord;
    public $id;
    public $username;
    public $discriminator;
    public $avatar;
    public $bot;
    public $mfa_enabled;
    public $locale;
    public $verified;
    public $email;
    public $flags;
    public $premium_type;
    public $public_flags;

    public function getAvatarUrl() {
        return "https://cdn.discordapp.com/avatars/{$this->id}/{$this->avatar}";
    }
}