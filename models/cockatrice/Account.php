<?php namespace app\models\cockatrice;

use Chickatrice;
use kiss\db\ActiveQuery;
use kiss\db\ActiveRecord;
use kiss\exception\QueryException;
use kiss\exception\ArgumentException;
use kiss\exception\SQLException;
use kiss\exception\SQLDuplicateException;
use kiss\exception\InvalidOperationException;
use kiss\helpers\HTTP;
use kiss\helpers\Strings;

/**
 * @property string $id;
 * @property int $admin;
 * @property string $name;
 * @property string $realname;
 * @property string $password_sha512;
 * @property string $email;
 * @property string $country;
 * @property mixed $avatar_bmp;
 * @property string $registrationDate;
 * @property boolean $active;
 * @property string $token;
 * @property string $clientid;
 * @property string $privlevel;
 * @property string $privlevelStartDate;
 * @property string $privlevelEndDate;
 * 
 * @property bool $isAdmin
 * @property bool $isModerator
 * @property bool $isJudge
 * @package app\models\cockatrice
 */
class Account extends ActiveRecord {
 
    const ADMIN_USER = 0;
    const ADMIN_OWNER = 1;
    const ADMIN_MODERATOR = 2;
    const ADMIN_JUDGE = 4;
    
    /*
    
    administrator | judge = 7
    moderator | judge = 6
    administrator | judge = 5
    registered user | judge = 4
    administrator = 3
    administrator = 2
    administrator = 1
    */
    public static function tableName() { return "cockatrice_users"; }

    protected $id;
    protected $admin;
    protected $name;
    protected $realname;

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

    /** @return bool The account is an admin */
    public function isAdmin() {
        return $this->admin & self::ADMIN_OWNER != 0;
    }
    
    /** @return bool The account is an admin */
    public function isModerator() {
        return $this->admin & self::ADMIN_MODERATOR != 0;
    }
    
    /** @return bool The account is an admin */
    public function isJudge() {
        return $this->admin & self::ADMIN_JUDGE != 0;
    }

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

    /** @return ActiveQuery|Account[] finds all the friends of the user */
    public function getFriends() {
        return Account::find()
                ->leftJoin('cockatrice_buddylist', [ 'id' => 'id_user2' ])
                ->where(['id_user1', $this->id]);
    }

    /** Removes a friend
     * @param Account|int $account the friend to remove
     * @return $this
     */
    public function removeFriend($account) {
        Chickatrice::$app->db()->createQuery()
            ->delete('cockatrice_buddylist')
            ->where(['id_user1', $this->id ])
            ->andWhere(['id_user2', $account])
            ->execute();
        return $this;
    }

    /** @return ActiveQuery|Account[] finds all the accounts this user is ignoring */
    public function getIgnores() {
        return Account::find()
            ->leftJoin('cockatrice_ignorelist', [ 'id' => 'id_user2' ])
            ->where(['id_user1', $this->id]);
    }    
    
    /** Removes a ignore
    * @param Account|int $account the ignore to remove
    * @return $this
    */
   public function removeIgnore($account) {
       Chickatrice::$app->db()->createQuery()
           ->delete('cockatrice_ignorelist')
           ->where(['id_user1', $this->id ])
           ->andWhere(['id_user2', $account])
           ->execute();
       return $this;
   }

    /** @return ActiveQuery|Account[] finds accounts with the given email */
    public static function findByEmail($email) {
        return self::find()->where([ 'email', Strings::toLowerCase(Strings::trim($email)) ]);
    }
    
    /** @return ActiveQuery|Account[] finds accounts with the given email */
    public static function findByName($name) {
        return self::find()->where([ 'name',  $name ]);
    }
    
    /** @return ActiveQuery|Account[] finds accounts with the given email */
    public static function findByToken($token) {
        return self::find()->where([ 'token',  $token ]);
    }
    
    /** @return ActiveQuery|Account[] finds accounts that have active sessions */
    public static function findByOnline() {
        $date = date("Y-m-d H:i:s",strtotime("-1 month"));
        return self::find()
                    ->leftJoin('cockatrice_sessions', ['name' => 'user_name' ])
                    ->where(['end_time', null])
                    ->andWhere(['start_time', '>', $date])
                    ->orderByDesc('start_time');
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
        // Stip messy characters
        $username = Strings::safe($username);
        if (strlen($username) > 30)
            $username = substr($username, 0, 30);

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