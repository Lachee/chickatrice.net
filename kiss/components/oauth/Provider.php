<?php namespace kiss\components\oauth;

use kiss\exception\ExpiredOauthException;
use kiss\exception\InvalidStateException;
use kiss\helpers\HTML;
use kiss\helpers\HTTP;
use kiss\helpers\Response;
use kiss\helpers\Strings;
use kiss\Kiss;
use kiss\models\BaseObject;
use kiss\session\Session;

class Provider extends BaseObject {
    
    protected $tokenClass = TokenCollection::class;
    protected $storageClass = Storage::class;

    /** @var string the Identity class */
    public $identityClass = null;

    /** @var string the name of the provider. */
    public $name = 'generic';

    /** @var string the URL to exchange the tokens */
    public $urlToken;

    /** @var string the URL to authorize */
    public $urlAuthorize;

    /** @var string the URL to identify */
    public $urlIdentify;
    
    /** @var string the client id */
    public $clientId;

    /** @var string the client secret */
    public $clientSecret;

    /** @var string the URL that will be redirected back to */
    public $redirectUri = '/auth';

    /** @var string the scopes to request */
    public $scopes;

    /** @var int Duration (in seconds) that refresh tokens last for. */
    public $refreshDuration = 365 * 24 * 60 * 60;

    /** @var \GuzzleHttp\Client guzzle client */
    public $guzzle;    

    protected function init() {
        if ($this->guzzle == null) {
            $this->guzzle = new \GuzzleHttp\Client();
        }
    }

    /** @return Storage gets the current token storage mechanism */
    public function getStorage($identity) {
        return new Storage([ 'identity' => $identity, 'provider' => $this ]);
    }

    /** Gets the state for the current session 
     * @param bool $regenerate should the state be regenerated.
     * @return string|false returns the current state, unless the session is unavialable.
    */
    public function getSessionState($regenerate = false) {
        if (Kiss::$app->session == null) return false;
        if ($regenerate) { 
            $state = Strings::token();
            Kiss::$app->session->set('oauth:' . $this->name, $state);
            return $state;
        }
        return Kiss::$app->session->get('oauth:' . $this->name, false);
    }

    /** Clears the seession state. Used to prevent replays */
    public function clearSessionState() {
        if (Kiss::$app->session == null) return false;
        return Kiss::$app->session->delete('oauth:' . $this->name);
    }

    /** Validates the current sessions state.
     * @param string $state the state to validate against
     * @return bool returns if the state is valid.
     */
    public function validateSessionState($state) {
        if (Kiss::$app->session == null)
            return $state == null;
            
        $self = $this->getSessionState(false);
        if ($self === false) return false;

        return $self === $state;
    }

    /** creates the URL to the authorization
     * @param bool $noPrompt hides the login prompt if they have already authorized this app
     * @return string the appropriate redirect URL
     */
    public function getAuthUrl($noPrompt = true) {        
        $url = $this->urlAuthorize;   
        $query = [
            'response_type'     => 'code',
            'client_id'         => $this->clientId,
            'scope'             => $this->getScope(),
            'redirect_uri'      => $this->getRedirectUri(),
        ];

        if ($noPrompt) $query['prompt'] = 'none';

        $state = $this->getSessionState(true);
        if ($state !== false) $query['state'] = $state; 
        return "{$url}?" . http_build_query($query);
    }

    /** redirects the user to the oauth authorization
     * @param string $state additional state information for validating authorizations.
     * @param bool $noPrompt hides the login prompt if they have already authorized this app
     * @return Response the redirect response
     */
    public function redirect($noPrompt = true) {
        $url = $this->getAuthUrl($noPrompt);
        return Response::redirect($url);
    }

    /** Handles the HTTP requests
     * @return TokenCollection|bool the authorized tokens
     */
    public function handleRequest() {
        //Get the code
        $code = HTTP::get('code', false);
        if ($code === false) return false;

        //validate the state
        $state = HTTP::get('state', null);
        if (!$this->validateSessionState($state)) {
            $this->clearSessionState();
            throw new InvalidStateException("State passed from oAuth2 was invalid");
        }

        //Clear the state and exchange the token. We clear the state so we cannot have replay attacks.
        $this->clearSessionState();
        return $this->exchangeToken($code);
    }

    /** Exchanges the code for the access token 
     * @return TokenCollection tokens
     * */
    public function exchangeToken($code) {
        $query = http_build_query([            
            'grant_type'        => 'authorization_code',
            'client_id'         => $this->clientId,
            'client_secret'     => $this->clientSecret,
            'code'              => $code,
            'scope'             => $this->getScope(),
            'redirect_uri'      => $this->getRedirectUri()
        ]);

        $response = $this->guzzle->request('POST', $this->urlToken, [
            'headers'   => [ 'content-type' => 'application/x-www-form-urlencoded'],
            'body'      => $query
        ]);

        $json = json_decode($response->getBody()->getContents(), true);
        $json['provider'] = $this;
        return BaseObject::new($this->tokenClass, $json);
    }

    /** Refreshes the token 
     * @param TokenCollection|string refresh tokens
     * @return TokenCollection refreshed token
    */
    public function refreshToken($token) {
        if ($token instanceof TokenCollection) $token = $token->refresh_token;
        $query = http_build_query([            
            'grant_type'        => 'refresh_token',
            'client_id'         => $this->clientId,
            'client_secret'     => $this->clientSecret,
            'refresh_token'     => $token,
            'scope'             => $this->getScope(),
            'redirect_uri'      => $this->getRedirectUri()
        ]);

        $response = $this->guzzle->request('POST', $this->urlToken, [
            'headers'   => [ 'content-type' => 'application/x-www-form-urlencoded'],
            'body'      => $query
        ]);

        $json = json_decode($response->getBody()->getContents(), true);
        $json['provider'] = $this;
        return BaseObject::new($this->tokenClass, $json);
    }


    /** Gets the current identity
     * @param TokenCollection|Storage|string $token
     * @return object the identity
    */
    public function identify($token) {
        
        //Prepare the token
        $accessToken = $token;
        if ($token instanceof TokenCollection) {
            $accessToken = $token->access_token;
        } else  if ($token instanceof Storage) { 
            $accessToken = $token->getAccessToken(true);
        }

        //Get the user
        $response = $this->guzzle->request('GET', $this->urlIdentify, [
            'headers' => [
                'content-type'  => 'application/json',
                'authorization' => 'Bearer ' . $accessToken 
            ]
        ]);
        
        $json = json_decode($response->getBody()->getContents(), true);
        $json['provider'] = $this;
        if (empty($this->identityClass)) return $json;
        return BaseObject::new($this->identityClass, $json);
    }

    /** Validates the access token
     * @param TokenCollection|Storage|string $token the access token
     * @return bool if its valid
     */
    public function validateAccessToken($token) {
        if ($token == null) return false;

        try {

            //Get the actual token and make sure it isn't null or empty
            $accessToken = $token;
            if ($token instanceof TokenCollection) {
                $accessToken = $token->access_token;
            } else  if ($token instanceof Storage) { 
                $accessToken = $token->getAccessToken(true);
            }
            
            return $accessToken != null && !empty($accessToken);

        }catch(ExpiredOauthException $e) {

            //We threw an expired oAuth exception, its not valid.
            return false;
        }catch(\Exception $e) {
            
            //We threw a generic error oh no :c
            return false;
        }
    }

    /** @return string the current scope. */
    public function getScope() {
        return join(' ', $this->scopes);
    }

    /** @return string the redirect uri */
    public function getRedirectUri() {
        return HTTP::url($this->redirectUri, true, false);
    }

}