<?php namespace app\components\discord;

use app\components\discord\interaction\Interaction;
use kiss\components\oauth\Provider;
use kiss\components\oauth\Storage;
use kiss\components\oauth\TokenCollection;
use kiss\exception\ArgumentException;
use kiss\exception\ExpiredOauthException;
use kiss\helpers\HTML;
use kiss\helpers\HTTP;
use kiss\helpers\Response;
use kiss\models\BaseObject;

/** Handles Discord Auth
 * @see https://github.com/Lachee/discord-php-kiss
 */
class Discord extends Provider  {

    public $name            = 'discord';
    public $urlToken        = 'https://discordapp.com/api/v6/oauth2/token';
    public $urlAuthorize    = 'https://discordapp.com/api/v6/oauth2/authorize';
    public $urlIdentify     = 'https://discordapp.com/api/v6/users/@me';
    public $urlAPI          = 'https://discord.com/api/v8';

    /** @var string public key for the interactivity module */
    public $interactivityPublicKey = null;

    public $identityClass   = User::class;

    public $botToken        = null;

    /** Gets a Discord User by the snowflake. 
     * It will only contain some of the information and requires the bot token. 
     * @return User
    */
    public function getUser($snowflake) {
        $json = $this->request('GET', "/users/$snowflake");
        return BaseObject::new($this->identityClass, $json);
    }

    /** Gets a guild object */
    public function getGuild($snowflake) {
        $json = $this->request('GET', "/guilds/{$snowflake}");
        return $json;
    }

    /** Gets all the guilds the user is in
     * @param TokenCollection|Storage|string $token
     * @return object the identity
    */
    public function getGuilds($token) {

        //Get the actual token and make sure it isn't null or empty
        $accessToken = $token;
        if ($token instanceof TokenCollection) {
            $accessToken = $token->access_token;
        } else  if ($token instanceof Storage) { 
            $accessToken = $token->getAccessToken(true);
        }
        

        //Get the user
        $response = $this->guzzle->request('GET', 'https://discordapp.com/api/v6/users/@me/guilds', [
            'headers' => [
                'content-type'  => 'application/json',
                'authorization' => 'Bearer ' . $accessToken 
            ]
        ]);
        
        $json = json_decode($response->getBody()->getContents(), true);
        return $json;
    }



    /** Verifies the interaction signature passed in the current request.
     * @return bool true if its valid
     */
    public function verifyInteractionSignature() {
        $signature  	= HTTP::header('x-signature-ed25519');
        $timestamp  	= HTTP::header('x-signature-timestamp');
        if (empty($signature) || empty($timestamp)) 
            return false;

        $binary_signature = \sodium_hex2bin($signature);
        $binary_key = \sodium_hex2bin($this->interactivityPublicKey);

        $body = HTTP::body();
        $message = $timestamp . $body;
        if (!\sodium_crypto_sign_verify_detached($binary_signature, $message, $binary_key))
            return false;

        return true;
    }

    /** registers all the commands */
    public static function registerCommands() {
        $directory = __DIR__ . '/components/discord/interaction/commands/';
        return Interaction::registerDirectory($directory);
    }

    /** Creates a new interaction object based of the body payload 
     * @return Interaction|null interaction object */
    public function createInteraction($data = null) {
        if (empty(Interaction::commands())) self::registerCommands();
        $payload = array_merge($data ?? HTTP::json(), [ 'discord' => $this ]);
        return BaseObject::new(Interaction::class, $payload);
    }

    /** Createsa  new bot request and returns the response */
    private function request($method, $endpoint) {
        if (empty($this->botToken)) throw new ArgumentException('Bot token is empty and cannot use bot functionality');
        $response = $this->guzzle->request($method, $this->urlAPI . $endpoint, [
            'headers' => [
                'content-type'  => 'application/json',
                'authorization' => 'Bot ' . $this->botToken,
                'user-agent'    => 'KissPHP (https://github.com/lachee, 1)'
            ]
        ]);
        $json = json_decode($response->getBody()->getContents(), true);
        $json['provider'] = $this;
        return $json;
    }
}