<?php namespace kiss;

if (!defined('KISS_AUTOLOAD_DIR'))
    define('KISS_AUTOLOAD_DIR', __DIR__ . '/../');

if (!defined('KISS_SESSIONLESS'))
    define('KISS_SESSIONLESS', false);

use Exception;
use kiss\db\Connection;
use kiss\exception\HttpException;
use kiss\exception\InvalidOperationException;
use kiss\helpers\HTTP;
use kiss\helpers\Response;
use kiss\helpers\Strings;
use kiss\models\BaseObject;
use kiss\models\JWTProvider;
use kiss\models\Identity;
use kiss\schema\RefProperty;
use kiss\session\Session;
use kiss\session\PhpSession;
use Throwable;

/** Base application */
class Kiss extends BaseObject {
    
    /** @var Kiss static instance of the current application */
    public static $app;

    /** @var string the title of the application */
    public $title = 'KISS';
    /** @var string the description of the application. Used in OpenGraph tags */
    public $description = '';
    /** @var string the path to the logo */
    public $logo = '/images/logo.png';
    /** @var string the path to the favicon */
    public $favicon = null;
    /** @var string colour code for the website theme. Used in some browsers. */
    public $themeColor = '#ff6666';
    
    /** @var string the base URL */
    protected $baseUrl;

    /** @var string the base namespace */
    protected $baseNamespace = 'app';

    /** @var string main controller */
    public $mainControllerClass = 'app\\controllers\\MainController';

    /** @var string main class for the identity */
    public $mainIdentityClass = 'app\\models\\User';

    /** @var \Predis\Client the current redis instance */
    protected $redis = null;

    /** Node Provider for uuids. */
    public $uuidNodeProvider =  null;

    /** @var Connection the database */
    protected $db = null;
    
    /** @var string default response type */
    private $defaultResponseType = 'text/html';

    /** @var BaseObject[] collection of components */
    protected $components = [];

    /** @var JWTProvider the JWT provider. */
    public $jwtProvider = [ '$class' => JWTProvider::class ];

    /** @var Session $session current session object */
    public $session = [ '$class' => PhpSession::class ];

    /** @var Identity current user */
    public $user;

    /** {@inheritdoc} */
    public static function getSchemaProperties($options = []) {
        return array_merge(parent::getSchemaProperties($options), [
            'jwtProvider' => new RefProperty(JWTProvider::class)
        ]);
    }

    public function __construct($options = []) {
        Kiss::$app = $this;
        parent::__construct($options);
    }

    protected function init() {
        if ($this->uuidNodeProvider == null)
            $this->uuidNodeProvider = new \Ramsey\Uuid\Provider\Node\RandomNodeProvider();
        
        if ($this->redis == null)
            $this->redis = new \Predis\Client();
            
        if ($this->db != null) {
            $this->db = new Connection($this->db['dsn'],$this->db['user'],$this->db['pass'], array(), $this->db['prefix']);
            $this->db->exec("SET NAMES 'utf8mb4'");
        }
        
        //Create the session
        if (KISS_SESSIONLESS) {
            $this->session = null;
        } else {
            $this->initializeObject($this->session);
            $this->session->start();
            
        }

        //Login
        $this->authorizeIdentity();
    }
    
    /** Gets the identity and stores it under the user */
    public function authorizeIdentity() {
        //No identity class so we cant authorize
        $identityClass = $this->mainIdentityClass;
        if (empty($identityClass)) {
            return false;
        }

        $jwt = null;

        //Find the identity by the session.
        if ($this->session != null) { 
            $jwt = $this->session->getClaims();
        }

        //Find the identity by the header
        if (($auth = HTTP::header('Authorization', false)) !== false) {
            $parts = explode(' ', $auth, 2);
            if (Strings::toLowerCase($parts[0]) == 'bearer') {
                $token = $parts[1];
                if (empty($token)) {
                    $this->respond(new HttpException(HTTP::UNAUTHORIZED, 'Token is empty'));
                    exit;
                }

                $claims = Kiss::$app->jwtProvider->decode($token);
                if (empty($claims->sub)) {
                    $this->respond(new HttpException(HTTP::UNAUTHORIZED, 'Invalid Authorization'));
                    exit;
                }

                $jwt = $claims;
                if ($jwt == null) {
                    $this->respond(new HttpException(HTTP::FORBIDDEN, 'Invalid Claims'));
                    exit;
                }
            } else {
                $this->respond(new HttpException(HTTP::UNAUTHORIZED, 'Bearer is missing'));
                exit;
            }
                
            if ($jwt == null || !isset($jwt->sub)) {
                $this->respond(new HttpException(HTTP::FORBIDDEN, 'Invalid claims or invalid JWT'));
                exit;
            }
        }

        //MAke sure the JWT isnt null
        if ($jwt == null || !isset($jwt->sub)) {
            return $this->user = null;
        }

        //Get the user and authorize the JWT
        $this->user = $identityClass::findByJWT($jwt)->one();
        if ($this->user != null) { 
            $this->user->authorize($jwt);
        } else {
            if ($this->session != null) $this->session->clearJWT();
            $this->respond(new HttpException(HTTP::UNAUTHORIZED, 'Didn\'t find matching JWT'));
            exit;
        }
        return $this->user;
    }

    /** @return Connection the current database. */
    public function db() { 
        return $this->db;
    }

    /** @return \Predis\Client the current redis client */
    public function redis() {
        return $this->redis;
    }

    /** magic get value */
    public function __get($name) {
        if (isset($this->components[$name]))
            return $this->components[$name];
    }

    /** Gets the current default response type. This can be used to determine how we should respond */
    public function getDefaultResponseType() { return $this->defaultResponseType; }
    /** Sets the current defualt response type. */
    public function setDefaultResponseType($type) { $this->defaultResponseType = $type; return $this; }

    /** Gets teh current base namespace */
    public function getBaseNamespace() { return $this->baseNamespace; }

    /** The base directory  of the application
     * @return string
    */
    public function baseDir() { return KISS_AUTOLOAD_DIR; }
    
    /** The base URL 
     * @return string
     */
    public function baseURL() { return $this->baseUrl ?? sprintf( "%s://%s%s",
        isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] != 'off' ? 'https' : 'http',
        $_SERVER['SERVER_NAME'],
        $_SERVER['REQUEST_URI']
      );
     }

    /** @return Identity gets the currently signed in user */
    public function getUser() { return $this->user; }

    /** @return bool is a user currently logged in */
    public function loggedIn() { return $this->user != null; }
    
    /** Writes the response out
     * @param Response|Exception|Throwable|object $response the response to write out. If this is a Response, then it will be written out. 
     * However, if it isn't then there will be special rules used to generate appropriate content:
     * 
     * If a Exception/Throwable is given, then a error Response will be created. 
     * 
     * If a object is given, then a Response will be created based of the current [[defaultResponseType]].  
     * @param int $status 
     * @return exit 
     */
    public function respond($response, $status = HTTP::OK) {
        //Prepare the response if it isn't already a Response object.
        if (!($response instanceof Response)) {

            //If the response is an exception, then make it an exception response
            if ($response instanceof Throwable) {                
                $response = Response::exception($response);
            } else {

                //Its just a payload, so check if the payload is raw contents or if it should be encoded as a JSON payload.
                if ($this->defaultResponseType == HTTP::CONTENT_APPLICATION_JSON) {
                    //Prepare a json default response
                    $response = Response::json($status, $response);

                } else if ($this->defaultResponseType == HTTP::CONTENT_TEXT_PLAIN) {
                    
                    //Prepare text response
                    $response = Response::text($status, $response);

                } else {

                    //Prepare a regular default response
                    $response = new Response($status, [], $response, $this->defaultResponseType);
                }

            }

        }


        //Return the response
        $response->respond();
        exit;
    }

}

//Setup an Alias of 'K'
class_alias(Kiss::class, 'kiss\\K');