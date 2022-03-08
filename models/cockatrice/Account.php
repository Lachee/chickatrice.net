<?php namespace app\models\cockatrice;

use kiss\db\ActiveQuery;
use kiss\db\ActiveRecord;
use kiss\exception\QueryException;
use kiss\exception\ArgumentException;
use kiss\exception\SQLException;
use kiss\exception\SQLDuplicateException;
use kiss\exception\InvalidOperationException;
use kiss\helpers\HTTP;
use kiss\helpers\Strings;

class Account extends ActiveRecord {
 
    public static function tableName() { return "cockatrice_users"; }

    public $id;
    public $admin;
    public $name;
    public $realname;

    // /** @deprecated Gender has been removed. */
    // public $gender;

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

    
    /** @return ActiveQuery|Account[] finds accounts with the given email */
    public static function findByEmail($email) {
        return self::find()->where([ 'email', Strings::toLowerCase(Strings::trim($email)) ]);
    }
    
    /** @return ActiveQuery|Account[] finds accounts with the given email */
    public static function findByName($name) {
        return self::find()->where([ 'name',  $name ]);
    }
    

    /**
     * Creates a new account
     * @param string $username username of the account
     * @param string $email email of the account
     * @param string $password password of the account
     * @return Account 
     * @throws QueryException 
     * @throws ArgumentException 
     * @throws SQLException 
     * @throws SQLDuplicateException 
     * @throws InvalidOperationException 
     */
    public static function createAccount($username, $email, $password) {
        // Create a new account
        $account = new Account([
            'admin'             => 0,
            'name'              => $username,
            'realname'          => '',
            'gender'            => 'r',
            'password_sha512'   => '',
            'email'             => Strings::toLowerCase($email),
            'active'            => 1,
            'country'           => 'AQ',
            'registrationDate'  => date('Y-m-d H:i:s'),
            'clientid'          => '',
            'privlevel'         => 'NONE',
            'privlevelStartDate'=> '0000-00-00 00:00:00',
            'privlevelEndDate'  => '0000-00-00 00:00:00',
        ]);

        // Setupt he password
        $account->setPassword($password);

        // Guess the best country code
        $country = HTTP::header('CF-IPCountry', '', null);
        if (!empty($country) && strlen($country) == 2) {
            $account->country = Strings::toLowerCase($country);
        }

        // ensure we dont have someone using that username already
        $count = 0;
        while (Account::find()->where(['name', $account->name ])->any()) {
            $count++;
            $account->name = $username . $count;
        }

        // Create the account
        $account->save();
        return $account;
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