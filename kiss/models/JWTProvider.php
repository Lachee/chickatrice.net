<?php namespace kiss\models;

use kiss\Kiss;
use Firebase\JWT\JWT;
use kiss\exception\ArgumentException;

/** Handles JWT tokens */
class JWTProvider extends BaseObject {
    
    public const ALGO_ES256 = 'ES256';
    public const ALGO_HS256 = 'HS256';
    public const ALGO_HS384 = 'HS384';
    public const ALGO_HS512 = 'HS512';
    public const ALGO_RS256 = 'RS256';
    public const ALGO_RS384 = 'RS384';
    public const ALGO_RS512 = 'RS512';

    /** @var string Private Key of the JWTs. Only applicable if algorithm is ALGO_RS[...] */
    protected $privateKey = null;
    
    /** @var string Public Key of the JWT. Only applicable if algorithm is ALGO_RS[...] */
    public $publicKey = null;

    /** @var string Key for the JWT. */
    public $key = null;

    /** @var string Current algorithm. */
    protected $algo = self::ALGO_HS256;

    /** @var int the time in seconds a token will expire by default */
    public $defaultExpiry = 86400;

    /** @var string[] List of issuers that are allowed to sign. */
    public $allowedIssuers = [];

    /** Encodes an object into a JWT 
     * @param mixed $object the payload to include in the JWT
     * @param int|null $expiry the expiry to add. If null, then defaultExpiry will be used.
     * @return string current JWT
    */
    public function encode($object, $expiry = null) {

        //Fetch the encryption key
        $key = null;
        switch($this->algo) {
            case self::ALGO_RS256:
            case self::ALGO_RS384:
            case self::ALGO_RS512:
                $key = $this->privateKey;
                break;

            default:
                $key = $this->key;
                break;
        } 

        //Validate it
        if (empty($key)) 
            throw new ArgumentException("Key cannot be null");
        
        $expiry = $expiry ?? $this->defaultExpiry;
        $payload = is_array($object) ? $object : json_encode($object);

        //Set the expiry information
        $now = time();
        $payload['iat'] = $now;
        $payload['exp'] = $expiry != null ? $now + $expiry : $now + 31540000;
        $payload['iss'] = Kiss::$app->baseURL();
        return JWT::encode($payload, $key, $this->algo);
    }

    /** Decodes a JWT into an object representation. Will throw errors if its not a valid token. 
     *  @return object The JWT's payload as a PHP object
     *
     * @throws UnexpectedValueException     Provided JWT was invalid
     * @throws SignatureInvalidException    Provided JWT was invalid because the signature verification failed
     * @throws BeforeValidException         Provided JWT is trying to be used before it's eligible as defined by 'nbf'
     * @throws BeforeValidException         Provided JWT is trying to be used before it's been created as defined by 'iat'
     * @throws ExpiredException             Provided JWT has since expired, as defined by the 'exp' claim
    */
    public function decode($jwt) {

        //Fetch the encryption key
        $key = null;
        switch($this->algo) {
            case self::ALGO_RS256:
            case self::ALGO_RS384:
            case self::ALGO_RS512:
                $key = $this->publicKey;
                break;

            default:
                $key = $this->key;
                break;
        } 

        //Validate it
        if (empty($key)) 
            throw new ArgumentException("Key cannot be null");

        $object = JWT::decode($jwt, $key, [ $this->algo ]);
        if ($object->iss != Kiss::$app->baseURL() && !in_array($object->iss, $this->allowedIssuers))
            throw new ArgumentException("ISS is not in the list of issuers");

        return $object;
    }
}