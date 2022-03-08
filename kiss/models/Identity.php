<?php namespace kiss\models;

use kiss\db\ActiveRecord;
use kiss\exception\NotYetImplementedException;
use kiss\db\ActiveQuery;
use app\components\mixer\MixerUser;
use kiss\exception\ArgumentException;
use kiss\exception\InvalidOperationException;
use kiss\helpers\Arrays;
use kiss\Kiss;
use kiss\models\BaseObject;
use kiss\models\OAuthContainer;
use PhpParser\Node\Expr\Cast\Array_;
use Ramsey\Uuid\Uuid;

class Identity extends ActiveRecord {

    public static function tableName() { return '$users'; }

    /** @var int DB ID of the user */
    protected $id;
    
    /** @var Uuid UUID of the user */
    protected $uuid;
    private $_uuid;

    /** @var string Unique key that is generated for every "logout" or account. */
    protected $accessKey;

    /** @var string Unique key that is generated for every "regenerate" of the api. If this is null, then the user cannot use the API's that require this.. */
    protected $apiKey;

    /** @var object The current JWT used for the login */
    protected $jwt = null;

    protected function init() {
        if (is_string($this->uuid)) $this->uuid = Uuid::fromString($this->uuid);
        $this->_uuid = $this->uuid;
    }

    protected function beforeSave() { 
        parent::beforeSave(); 

        //Make sure we have a UUID and then update our DB version to the string version.
        if ($this->_uuid == null) throw new ArgumentException('UUID cannot be null');
        $this->uuid = $this->_uuid->toString();

        //The current access key is in a illegal state, lets fix that
        if ($this->accessKey == null)   {
            $this->accessKey = substr(bin2hex(random_bytes(32)), 0, 32);
            $this->markDirty('accessKey');
        }

        if ($this->apiKey == null) { 
            $this->apiKey = substr(bin2hex(random_bytes(32)), 0, 32);
            $this->markDirty('apiKey');
        }
    }
    protected function afterSave() {
        parent::afterSave();
        $this->uuid = $this->_uuid;
    }
    protected function afterLoad($data, $success) {
        parent::afterLoad($data, $success);
        if (is_string($this->uuid))
            $this->uuid = $this->_uuid = Uuid::fromString($this->uuid);
    }
    
    /** @return ActiveQuery|User|null finds a user by a JWT claim */
    public static function findByJWT($jwt) {
        $sub = Arrays::value($jwt, 'sub'); 
        $key = Arrays::value($jwt, 'key');
        $src = Arrays::value($jwt, 'src', 'login');
        $var = $src == 'api' ? 'apiKey' : 'accessKey';
        return self::find()->where([ ['uuid', $sub ], [ $var, $key ] ]);
    }

    /** Logs the user in and generates a new JWT. */
    public function login() {

        //Create a new JWT for the user
        $jwt = $this->jwt([
            'src'      => 'login',
            'sid'       => Kiss::$app->session->getSessionId(),
        ]);

        //Set the JWT
        Kiss::$app->session->setJWT($jwt);
        $this->authorize($jwt);
        return $this->save();
    }

    /** Logs the user out */
    public function logout() {
        $this->accessKey = null;
        Kiss::$app->session->stop()->start();
        return $this->save();
    }

    /** Sets up the internal JWT
     * @return $this
     */
    public function authorize($jwt) {
        $this->jwt = $jwt;
        return $this;
    }

    /** Returns thw JWT used to authorize us
     * @return object
     */
    public function authorization() {
        return $this->jwt;
    }

    /** Gets the current authorization scopes, if any */
    public function scopes() {
        return $this->jwt->scopes ?? [];
    }

    /** Creates a new JWT for this user 
     * @return string
    */
    public function jwt($payload = [], $expiry = null) {
        if (!is_array($payload)) $payload = json_encode($payload);
        $payload['sub'] = $this->uuid->toString();
        $payload['key'] = $this->accessKey;
        $payload['src'] = 'user';
        return Kiss::$app->jwtProvider->encode($payload, $expiry);
    }

    /** Creates an API token for this user. Similar to the JWT but strictly limited to the API */
    public function apiToken($metadata = [], $expiry = null) {        
        if ($this->apiKey == null) return null;
        $payload = $metadata;
        $payload['sub'] = $this->uuid;
        $payload['key'] = $this->apiKey;
        $payload['src'] = 'api';
        return Kiss::$app->jwtProvider->encode($payload, $expiry == null ? 3.154e+7 : $expiry);
    }

    /** Forces the API key to regenerate and saves the record */
    public function regenerateApiKey() {
        $this->apiKey = false;
        return $this->save(false, ['apiKey']);
    }
}