<?php namespace app\models\cockatrice;

use kiss\db\ActiveQuery;
use kiss\db\ActiveRecord;
use kiss\helpers\Strings;

class Account extends ActiveRecord {
    public static function tableName() { return "cockatrice_users"; }

    public $id;
    public $admin;
    public $name;
    public $realname;
    public $gender;
    public $password_sha512;
    public $email;
    public $country;
    public $avatar_bmp;
    public $registrationDate;
    public $active;
    public $token;
    public $clientid;
    public $privlevel;
    public $privlevelStartDate;
    public $privlevelEndDate;

    public function getAvatarDataUrl() {
        if ($this->avatar_bmp == null) 
            return null;
            
        $base = "data:image/bmp;base64,";
        $base .= base64_encode($this->avatar_bmp);
        return $base;
    }

    /** @return ActiveQuery|Account[] finds accounts with the given email */
    public static function findByEmail($email) {
        return self::find()->where([ 'email', Strings::toLowerCase(Strings::trim($email)) ]);
    }
}