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
    
    /** Sets the password for Cockatrice
     * @return $this
     */
    public function setPassword($password) {
        $this->password_sha512 = static::hashPassword($password);
        return $this;
    }

    /** @return string insecurely checks the password */
    public function checkPassword($password, $salt = '') {
        $hash = $this->password_sha512;
        if ($salt == '') $salt = substr($hash, 0, 16);
		return static::hashPassword($password, $salt) === $hash;
    }

    /** @return string insecurely hashes the passwords */
    public static function hashPassword($password, $salt = '') {
		if ($salt == '') {
			$saltChars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ1234567890';
			for ($i = 0; $i < 16; ++$i)
			$salt .= $saltChars[rand(0, strlen($saltChars) - 1)];
		}

		$key = $salt . $password;
		for ($i = 0; $i < 1000; ++$i) $key = hash('sha512', $key, true);
		return $salt . base64_encode($key);
    }
}