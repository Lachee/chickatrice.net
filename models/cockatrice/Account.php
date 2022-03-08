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
 
    const ADMIN_LEVEL_USER = 0;
    const ADMIN_LEVEL_OWNER = 1;
    const ADMIN_LEVEL_MODERATOR = 2;

    public static function tableName() { return "cockatrice_users"; }

    public $id;
    public $admin;
    public $name;
    public $realname;

    // /** @deprecated Gender has been removed. */
    // public $gender;

    protected $password_sha512;
    protected $email;
    protected $country;
    protected $avatar_bmp;
    protected $registrationDate;
    protected $active;
    protected $token;
    protected $clientid;
    protected $privlevel;
    protected $privlevelStartDate;
    protected $privlevelEndDate;

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
        $this->markDirty('password_sha512');
        return $this;
    }

    /** @return string insecurely checks the password */
    public function checkPassword($password, $salt = '') {
        $hash = $this->password_sha512;
        if ($salt == '') $salt = substr($hash, 0, 16);
		return static::hashPassword($password, $salt) === $hash;
    }

    /** @inheritdoc */
    public function afterDelete() {
        parent::afterDelete();

        // Delete everything else.
        // Many of these things have FK setup correctly, but just to be safe
        $this->query()->delete('cockatrice_buddylist')->where(['id_user1', $this->id])->orWhere(['id_user2', $this->id])->execute();
        $this->query()->delete('cockatrice_ignorelist')->where(['id_user1', $this->id])->orWhere(['id_user2', $this->id])->execute();
        $this->query()->delete('cockatrice_decklist_files')->where(['id_user', $this->id])->execute();
        $this->query()->delete('cockatrice_decklist_folders')->where(['id_user', $this->id])->execute();
        $this->query()->delete('cockatrice_replays_access')->where(['id_player', $this->id])->execute();
        $this->query()->delete('cockatrice_warnings')->where(['user_id', $this->id])->execute();        
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