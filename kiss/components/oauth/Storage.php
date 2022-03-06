<?php namespace kiss\components\oauth;

use kiss\exception\ExpiredOauthException;
use kiss\exception\MissingOauthException;
use kiss\Kiss;
use kiss\models\BaseObject;

class Storage extends BaseObject {
    
    /** @var string namespace of the oauth tokens */
    private const REDIS_NAMESPACE = 'oauth';

    /** @var string Identifier of the owner */
    public $identity;

    /** @var Redis */
    protected $redis;

    /** @var Provider $provider the oauth provider */
    protected $provider;
    protected $refreshToken;
    protected $expiresAt;
    protected $scopes;
    
    protected function init() {
        if ($this->redis == null) 
            $this->redis = Kiss::$app->redis();
            
        //try to load teh refresh from the cache
        try { 
            $this->loadRedis(); 
        } catch(MissingOauthException $moe) {
            
        }
    }

    /** @return Provider the current provider */
    public function getProvider() { return $this->provider; }

    /** @var string the refresh token. */
    public function getRefreshToken() { return $this->refreshToken; }

    /** @var DateTime the expiry date */
    public function getExpiry() { return $this->expiresAt; }

    /** @var string[] the requested scopes */
    public function getScopes() { return $this->scopes; }

    /** Checks the cahce for the current access token. If none is available, then a ExpiredOauthException will be thrown.
     * @throws ExpiredOauthException thrown when there is no access token. Its recommended to call [[refresh]] if th is occures.
     *  @return string the current access token.  */
    public function getAccessToken($refresh = false) {        
        $keyAccess  = self::REDIS_NAMESPACE . ":{$this->identity}:{$this->provider->name}:access";
        $accessToken = $this->redis->get($keyAccess);
        if (empty($accessToken)) {

            //If we are refreshing, then do so and return the access token
            if ($refresh) {
                $collection = $this->provider->refreshToken($this->getRefreshToken());
                $this->setTokens($collection);
                return $collection->access_token;
            }

            //Just fail
            throw new ExpiredOauthException('Access token was not found in the cache');
        }
        return $accessToken;
    }

    /** Sets the tokens
     * @param TokenCollection $tokens
     * @return Storage $this
     */
    public function setTokens($tokens) {
        $keyRefresh = self::REDIS_NAMESPACE . ":{$this->identity}:{$this->provider->name}:meta";
        $keyAccess  = self::REDIS_NAMESPACE . ":{$this->identity}:{$this->provider->name}:access";

        $metadata = [
            'refresh_token' => $tokens->refresh_token,
            'expires_at'    => $tokens->expires_at,
            'scope'         => $tokens->scope
        ];

        $this->redis->set($keyAccess, $tokens->access_token);
        $this->redis->expire($keyAccess, $tokens->expires_in);

        $this->redis->hmset($keyRefresh, $metadata);        
        $this->redis->expire($keyAccess, $this->provider->refreshDuration);

        return $this->loadRedis();
    }

    /** Loads up the redis
     * @throws MissingOauthException thrown when there is no valid meta data.
     * @return Storage $this
     */
    public function loadRedis() {
        //Check the cache
        $keyRefresh = self::REDIS_NAMESPACE . ":{$this->identity}:{$this->provider->name}:meta";
        $meta = Kiss::$app->redis()->hgetall($keyRefresh);
        if ($meta == null || empty($meta['refresh_token'])) throw new MissingOauthException('There is no available refresh token');
  
        //Store the values
        $this->refreshToken     = $meta['refresh_token'];
        $this->expiresAt        = $meta['expires_at'];
        $this->scopes           = explode(' ', $meta['scope']);
        return $this;
    }

}