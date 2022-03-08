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
use kiss\exception\QueryException;
use kiss\exception\SQLDuplicateException;
use kiss\exception\SQLException;
use kiss\helpers\Arrays;
use kiss\helpers\HTML;
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
    protected $last_sync;

    /** @var int $max_allowed_decks */ 
    public $max_allowed_decks;
    /** @var int $max_allowed_replays */ 
    public $max_allowed_replays;

    /** @var Account cockatrice account */
    private $_cockatriceAccount;
    protected $cockatrice_id;
    
    protected $email;

    public static function getSchemaProperties($options = [])
    {
        return [
            'uuid'          => new StringProperty('ID of the user'),
            'email'          => new StringProperty('Email address of the user'),
            'snowflake'     => new IntegerProperty('Discord Snowflake id'),
            'last_seen'     => new StringProperty('Last time this user was active'),
            'last_sync'     => new StringProperty('Last time the avatar was synchronised')
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

    /** Updates the current discord snowflake of the logged in user.
     * @param string $snowflake discord ID
     * @return $this. */
    public function setSnowflake($snowflake) {
        $this->snowflake = $snowflake;
        $this->_discordUser = null;
        return $this;
    }

    /** Finds by snowflake */
    public static function findBySnowflake($snowflake) {
        return self::find()->where(['snowflake', $snowflake]);
    }

    /** Gets the current Discord user
     * @return \app\components\discord\User the discord user
     */
    public function getDiscordUser() {
        if ($this->snowflake <= 0) return null;
        if ($this->_discordUser != null) return $this->_discordUser;
        $storage = Chickatrice::$app->discord->getStorage($this->uuid);
        $this->_discordUser = Chickatrice::$app->discord->identify($storage);
        return $this->_discordUser;
    }

    /** Stores the internal discord user cache */
    public function setDiscordUserCache(\app\components\discord\User $user) {
        $this->_discordUser = $user;
        return $this;
    }

    /** Gets the discord guilds */
    public function getDiscordGuilds() {
        $storage = Chickatrice::$app->discord->getStorage($this->uuid);
        return Chickatrice::$app->discord->getGuilds($storage);
    }

    /** Synchronises the Discord Avatar to the user */
    public function synchroniseDiscordAvatar() {
        $discord = $this->getDiscordUser();
        if ($discord == null)
            throw new InvalidOperationException('User is not linked to Discord');

        $account = $this->getAccount();
        if ($account == null)
            throw new InvalidOperationException('User is not linked to an account');

        // Download the discord image
        $guzzle = Chickatrice::$app->discord->guzzle;
        $response = $guzzle->request('GET', $discord->getAvatarUrl() . '.jpg?size=512');
        $body = $response->getBody()->getContents();

        // Create image
        $img = imagecreatefromstring($body);
        try {
            ob_start();
            imagebmp($img);
            $binary = ob_get_contents();
            $account->avatar_bmp = $binary;
            ob_end_clean();
        } finally {
            imagedestroy($img);
        }

        // Save the account and flush it
        $account->save(false, ['avatar_bmp']);
        $this->_cockatriceAccount = Account::findByKey($this->cockatrice_id)->flush()->one();

        $stm = Kiss::$app->db()->prepare('UPDATE $users SET `last_sync` = now() WHERE `id` = :id');
        $stm->bindParam(':id', $this->id);
        $stm->execute();
        return $this;
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
        if ($bmp !== null) 
            return $bmp;

        if ($this->getSnowflake() > 0)
            return "https://d.lu.je/avatar/{$this->getSnowflake()}?size=$size";

        return 'images/placeholder_profile.png';
    }
    
    /** @return string the username */
    public function getUsername() {
        return HTML::encode($this->getAccount()->name);
    }

    

    /** Finds a user from the username */
    public static function findByUsername($name) {
        return self::find()
                        ->fields('`$users`.*')
                        ->leftJoin('cockatrice_users', ['cockatrice_id' => 'id'])
                        ->where(['name', $name]);
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

    /** @return ActiveQuery|User finds a user by the account */
    public static function findByAccount(Account $account) {
        return User::find()->where(['cockatrice_id', $account->id]);
    }

    /**
     * Creates a new user
     * @param string $username The username for a new account
     * @param string $email The email address of the account
     * @param string $snowflake The discord snowflake of the account
     * @param Account|null $account The linked account. If null, then a new one is made.
     * @return User 
     * @throws QueryException 
     * @throws ArgumentException 
     * @throws SQLException 
     * @throws SQLDuplicateException 
     * @throws InvalidOperationException 
     */
    public static function createUser($username, $email, $snowflake, $account = null) {
        // Create a new user with the given username, email and snowflake
        $user = new User([
            'uuid'      => Uuid::uuid1(Chickatrice::$app->uuidNodeProvider->getNode()),
            'email'     => $email,
            'snowflake' => $snowflake,
        ]);
        
        // Find cockatrice accounts associated with the email
        /** @var Account $account cockatrice account to link up */
        if ($account == null) {
            $account = Account::findByEmail($email)->one();
            $createAccount = $account == null || User::findByAccount($account)->any();
            
            if ($createAccount)
                $account = Account::createAccount($username, $email, Strings::token(32));
        }

        // Setup the account and save the user
        $user->cockatrice_id = $account->id;
        $user->save();
        return $user;
    }
}