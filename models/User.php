<?php namespace app\models;

use app\models\cockatrice\Account;
use Chickatrice;
use Exception;
use kiss\models\Identity;
use GALL;
use kiss\db\ActiveQuery;
use kiss\db\ActiveRecord;
use kiss\db\Query;
use kiss\exception\ArgumentException;
use kiss\exception\InvalidOperationException;
use kiss\exception\NotYetImplementedException;
use kiss\exception\SQLDuplicateException;
use kiss\exception\SQLException;
use kiss\helpers\Arrays;
use kiss\helpers\HTTP;
use kiss\helpers\Strings;
use kiss\K;
use kiss\Kiss;
use kiss\schema\BooleanProperty;
use kiss\schema\IntegerProperty;
use kiss\schema\RefProperty;
use kiss\schema\StringProperty;
use Ramsey\Uuid\Uuid;

/**
 * @property int $sparkles the number of sparkles a user has
 * @package app\models
 */
class User extends Identity {
    
    /** @var \app\components\discord\User stored discord user. */
    private $_discordUser;
    protected $snowflake;
    protected $last_seen;
    
    /** @var Account cockatrice account */
    private $_cockatriceAccount;
    protected $cockatrice_id;
    
    protected $email;

    public static function getSchemaProperties($options = [])
    {
        return [
            'uuid'          => new StringProperty('ID of the user'),
            'email'          => new StringProperty('Email address of the user'),
            'snowflake'     => new StringProperty('Discord Snowflake id'),
            'last_seen'     => new StringProperty('Last time this user was active')
        ];
    }

    /** @return Account $cockatrice account */
    public function getAccount() {
        if ($this->_cockatriceAccount != null) return $this->_cockatriceAccount;
        return $this->_cockatriceAccount = Account::findByKey($this->cockatrice_id)->one();
    }

    /** @return string Current discord snowflake of the logged in user. */
    public function getSnowflake() { 
        return $this->snowflake; 
    }

    /** Finds by snowflake */
    public static function findBySnowflake($snowflake) {
        return self::find()->where(['snowflake', $snowflake]);
    }

    /** Gets the current Discord user
     * @return \app\components\discord\User the discord user
     */
    public function getDiscordUser() {
        if ($this->_discordUser != null) return $this->_discordUser;
        $storage = Chickatrice::$app->discord->getStorage($this->uuid);
        $this->_discordUser = Chickatrice::$app->discord->identify($storage);
        return $this->_discordUser;
    }

    /** Gets the discord guilds */
    public function getDiscordGuilds() {
        $storage = Chickatrice::$app->discord->getStorage($this->uuid);
        return Chickatrice::$app->discord->getGuilds($storage);
    }

    /** Runs a quick validation on the discord token
     * @return bool true if the token is valid
     */
    public function validateDiscordToken() {
        if ($this->_discordUser != null) return true;
        
        $storage = Chickatrice::$app->discord->getStorage($this->uuid);
        return Chickatrice::$app->discord->validateAccessToken($storage);
    }

#region Profile
    /** Gets the URL of the users avatar
     * @return string the URL
     */
    public function getAvatarUrl($size = 64) {
        $bmp = $this->getAccount()->getAvatarDataUrl();
        if ($bmp !== null) return $bmp;
        return "https://d.lu.je/avatar/{$this->getSnowflake()}?size=$size";
    }
    
    /** @return string the username */
    public function getUsername() {
        return $this->getAccount()->name;
    }
#endregion


#region History
    /** Updates that hte user has been seen */
    public function seen() {
        $stm = Kiss::$app->db()->prepare('UPDATE $users SET `last_seen` = now() WHERE `id` = :id');
        $stm->bindParam(':id', $this->id);
        $stm->execute();
    }
#endregion


    /** @return bool is the profile the signed in user */
    public function isMe() {
        if (Kiss::$app->user == null) return false;
        return $this->id == Kiss::$app->user->id;
    }

    public static function CreateUser($username, $email, $snowflake) {
        // Create a new user with the given username, email and snowflake
        $user = new User([
            'uuid'      => Uuid::uuid1(Chickatrice::$app->uuidNodeProvider->getNode()),
            'username'  => $username,
            'email'     => $email,
            'snowflake' => $snowflake,
        ]);
        
        // Find cockatrice accounts associated with the email
        /** @var Account $account cockatrice account to link up */
        $account = Account::findByEmail($email)->one();
        $createAccount = $account == null || User::find()->where(['cockatrice_id', $account->id])->any();

        if ($createAccount) {
            $account = new Account([
                'admin'             => 0,
                'name'              => $username,
                'realname'          => '',
                'gender'            => 'r',
                'password_sha512'   => '<!NEEDS SETTING!>',
                'email'             => $email,
                'active'            => 1
            ]);

            // ensure we dont have someone using that username already
            $count = 0;
            while (Account::find()->where(['name', $account->name ])->any()) {
                $count++;
                $account->name = $username . $count;
            }

            // Create the account
            $account->save();
        }

        // Setup the account and save the user
        $user->cockatrice_id = $account->id;
        $user->save();
        return $user;
    }
}