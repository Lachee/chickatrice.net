<?php
namespace kiss\helpers;

use kiss\Kiss;
use Throwable;

class HTTP {
    const DELETE = 'DELETE';
    const PUT = 'PUT';
    const POST = 'POST';
    const GET = 'GET';
    const HEAD = 'HEAD';

    const CONTINUE = 100;
    const SWITCHING_PROTOCOLS = 101;
    const PROCESSING = 102;            // RFC2518
    const EARLY_HINTS = 103;           // RFC8297
    const OK = 200;
    const CREATED = 201;
    const ACCEPTED = 202;
    const NON_AUTHORITATIVE_INFORMATION = 203;
    const NO_CONTENT = 204;
    const RESET_CONTENT = 205;
    const PARTIAL_CONTENT = 206;
    const MULTI_STATUS = 207;          // RFC4918
    const ALREADY_REPORTED = 208;      // RFC5842
    const IM_USED = 226;               // RFC3229
    const MULTIPLE_CHOICES = 300;
    const MOVED_PERMANENTLY = 301;
    const FOUND = 302;
    const SEE_OTHER = 303;
    const NOT_MODIFIED = 304;
    const USE_PROXY = 305;
    const RESERVED = 306;
    const TEMPORARY_REDIRECT = 307;
    const PERMANENTLY_REDIRECT = 308;  // RFC7238
    const BAD_REQUEST = 400;
    const UNAUTHORIZED = 401;
    const PAYMENT_REQUIRED = 402;
    const FORBIDDEN = 403;
    const NOT_FOUND = 404;
    const METHOD_NOT_ALLOWED = 405;
    const NOT_ACCEPTABLE = 406;
    const PROXY_AUTHENTICATION_REQUIRED = 407;
    const REQUEST_TIMEOUT = 408;
    const CONFLICT = 409;
    const GONE = 410;
    const LENGTH_REQUIRED = 411;
    const PRECONDITION_FAILED = 412;
    const REQUEST_ENTITY_TOO_LARGE = 413;
    const REQUEST_URI_TOO_LONG = 414;
    const UNSUPPORTED_MEDIA_TYPE = 415;
    const REQUESTED_RANGE_NOT_SATISFIABLE = 416;
    const EXPECTATION_FAILED = 417;
    const I_AM_A_TEAPOT = 418;                                               // RFC2324
    const MISDIRECTED_REQUEST = 421;                                         // RFC7540
    const UNPROCESSABLE_ENTITY = 422;                                        // RFC4918
    const LOCKED = 423;                                                      // RFC4918
    const FAILED_DEPENDENCY = 424;                                           // RFC4918
    const TOO_EARLY = 425;                                                   // RFC-ietf-httpbis-replay-04
    const UPGRADE_REQUIRED = 426;                                            // RFC2817
    const PRECONDITION_REQUIRED = 428;                                       // RFC6585
    const TOO_MANY_REQUESTS = 429;                                           // RFC6585
    const REQUEST_HEADER_FIELDS_TOO_LARGE = 431;                             // RFC6585
    const UNAVAILABLE_FOR_LEGAL_REASONS = 451;
    const INTERNAL_SERVER_ERROR = 500;
    const NOT_IMPLEMENTED = 501;
    const BAD_GATEWAY = 502;
    const SERVICE_UNAVAILABLE = 503;
    const GATEWAY_TIMEOUT = 504;
    const VERSION_NOT_SUPPORTED = 505;
    const VARIANT_ALSO_NEGOTIATES_EXPERIMENTAL = 506;                        // RFC2295
    const INSUFFICIENT_STORAGE = 507;                                        // RFC4918
    const LOOP_DETECTED = 508;                                               // RFC5842
    const NOT_EXTENDED = 510;                                                // RFC2774
    const NETWORK_AUTHENTICATION_REQUIRED = 511;                             // RFC6585

    const CONTENT_TEXT_HTML = 'text/html';
    const CONTENT_TEXT_PLAIN = 'text/plain';
    const CONTENT_APPLICATION_JSON = 'application/json';
    const CONTENT_APPLICATION_XML = 'application/xml';
    const CONTENT_APPLICATION_JAVASCRIPT = 'application/javascript';
    const CONTENT_APPLICATION_OCTET_STREAM = 'application/octet-stream';

    private static $statusMessages = [
        400 => 'Bad Request',
        401 => 'Unauthorized',
        402 => 'Payment Required',
        403 => 'Forbidden',
        404 => 'Not Found',
        405 => 'Method Not Allowed',
        406 => 'Not Acceptable',
        407 => 'Proxy Authentication Required',
        408 => 'Request Timeout',
        409 => 'Conflict',
        410 => 'Gone',
        411 => 'Length Required',
        412 => 'Precondition Failed',
        413 => 'Request Entity Too Large',
        414 => 'Request-URI Too Long',
        415 => 'Unsupported Media Type',
        416 => 'Requested Range Not Satisfiable',
        417 => 'Expectation Failed',
        418 => 'I\'m a teapot', // RFC 2324
        419 => 'Authentication Timeout', // not in RFC 2616
        420 => 'Method Failure', // Spring Framework
        420 => 'Enhance Your Calm', // Twitter
        422 => 'Unprocessable Entity', // WebDAV; RFC 4918
        423 => 'Locked', // WebDAV; RFC 4918
        424 => 'Failed Dependency', // WebDAV; RFC 4918
        424 => 'Method Failure', // WebDAV)
        425 => 'Unordered Collection', // Internet draft
        426 => 'Upgrade Required', // RFC 2817
        428 => 'Precondition Required', // RFC 6585
        429 => 'Too Many Requests', // RFC 6585
        431 => 'Request Header Fields Too Large', // RFC 6585
        444 => 'No Response', // Nginx
        449 => 'Retry With', // Microsoft
        450 => 'Blocked by Windows Parental Controls', // Microsoft
        451 => 'Unavailable For Legal Reasons', // Internet draft
        451 => 'Redirect', // Microsoft
        494 => 'Request Header Too Large', // Nginx
        495 => 'Cert Error', // Nginx
        496 => 'No Cert', // Nginx
        497 => 'HTTP to HTTPS', // Nginx
        499 => 'Client Closed Request', // Nginx
        500 => 'Internal Server Error',
        501 => 'Not Implemented',
        502 => 'Bad Gateway',
        503 => 'Service Unavailable',
        504 => 'Gateway Timeout',
        505 => 'HTTP Version Not Supported',
        506 => 'Variant Also Negotiates', // RFC 2295
        507 => 'Insufficient Storage', // WebDAV; RFC 4918
        508 => 'Loop Detected', // WebDAV; RFC 5842
        509 => 'Bandwidth Limit Exceeded', // Apache bw/limited extension
        510 => 'Not Extended', // RFC 2774
        511 => 'Network Authentication Required', // RFC 6585
        598 => 'Network read timeout error', // Unknown
        599 => 'Network connect timeout error', // Unknown
    ];

    /** Private internal cookie cache */
    private static $SET_COOKIES = [];
    private static $_ROUTE = null;
    private static $_REFERAL;
    private static $_CSRF = null;

    /** @return string the status message associated with the code */
    public static function status($code) {
        if (isset(self::$statusMessages[$code])) return self::$statusMessages[$code];
        return 'Unkown Exception';
    }

    /** @return string the HTTP method in upper case */
    public static function method() {
        return strtoupper($_SERVER['REQUEST_METHOD']);
    }

    
    /** @return string either http or https */
    public static function https() {
        if (!empty($_SERVER['HTTP_X_FORWARDED_PROTO'])) return $_SERVER['HTTP_X_FORWARDED_PROTO'];
        return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? 'https' : $_SERVER['REQUEST_SCHEME'];
    }

    /** Checks if the request is likely to be a scraper from discord
     * @return boolean
     */
    public static function isDiscordBot() {
        //$_SERVER['HTTP_USER_AGENT'] == 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10.10; rv:38.0) Gecko/20100101 Firefox/38.0'
        if (HTTP::get('_DISCORDBOT', false, FILTER_VALIDATE_BOOLEAN) == true) return true;
        if (strpos($_SERVER['HTTP_USER_AGENT'], 'discordapp.com') !== false) return true;
        if (empty($_SERVER['HTTP_ACCEPT'])) return true;
        return false;
    }

    /** Gets the routed URL
     * 
     * @param string|array|null $action
     * Prefix determines how it works.
     * | Prefix | Result |
     * |--------|--------|
     * |  | Action |
     * | / | Controller/Action |
     * | \ | Class |
     * | [a-z]:// | Absolute |
     * 
     * For example, if the page is currently `/project/index`:     * 
     * | $action | Result |
     * |--------|--------|
     * | update | /project/update |
     * | /update | /update |
     * | /update/test | /update/test |
     * | /api/project/:id | /api/project/{id} |
     * | http://index.com/ | http://index.com/ |
     * 
     * @param boolean $absolute include the absolute URL or not.
     * @param boolean $includeQuery include the built queries in the url
     */
    public static function url($action, $absolute = false, $includeQuery = true) {

        if (!is_array($action)) $action = [ $action ];

        //Prepare the queries
        $queries = [];
        $route = $action[0];
        
        //Convert the class to a route
        if (strpos($route, '\\') !== false) {
            $route = join('/', $route::getRouting()) . '/';
        }  

        foreach($action as $key => $pair){
            if ($key === 0) continue;

            //Covnert active records
            if (method_exists($pair, 'getKey')) {
                $pair = $pair->getKey();
            }

            if (!is_array($pair)) {
                $route = str_replace(":$key", self::safeURLEncode($pair), $route, $count);
            } else {
                $count = 0;
            }

            if ($count == 0) {
                $queries[$key] = $pair;
            }
            
        }

        //Build queries
        $query = http_build_query($queries);
        if (!empty($query)) $query = "?" . $query;

        //Its a base URL
        if (preg_match('/[a-z]+:\/\//', $route)) {
            return $route . ($includeQuery ? $query : '');
        }

        //Its relative
        if ($route[0] != '/') {
            $base = substr(self::route(), 0, strrpos(self::route(), "/") + 1);
            $route = $base . $route;
        }

        //Absolute, so slap the route down
        if ($absolute)
            return trim(Kiss::$app->baseURL(), '/') . $route .  ($includeQuery ? $query : '');

        return $route . ($includeQuery ? $query : '');
    }

    /** @return string safely encoded URL, where its all URL encoded (no spaces) */
    public static function safeURLEncode($url) {
        $str = urlencode($url);
        return str_replace('+', '%20', $str);
    }

    /** @return string The current route*/
    public static function route() {
        if (self::$_ROUTE == null) {
            self::$_ROUTE = self::setRoute($_REQUEST['route'] ?? '');
        }
        return self::$_ROUTE;
    }

    /** Sets the route. This shouldn't be used. */
    public static function setRoute($route) {
        $route = $route ?? '';
        if (empty($route) && !empty($_SERVER['REDIRECT_URL'])) $route = $_SERVER['REDIRECT_URL'];
        return self::$_ROUTE = empty($route) ? "/" : $route;
    }

    /** Sets teh referal information */
    public static function setReferral($referal = null) {
        
        if (Kiss::$app->session != null) {
            self::$_REFERAL = $referal ?? Kiss::$app->session->get('REFERRAL', HTTP::header('Referer', HTTP::get('referer', null)));
            Kiss::$app->session->set('REFERRAL', HTTP::route());
        } else {
            self::$_REFERAL = $referal ?? HTTP::header('Referer', null);
        }
    }

    /** @return string gets the page that refered us. */
    public static function referral() {
        return self::$_REFERAL;
    }

    /** Gets the current host */
    public static function host() {
        return $_SERVER['HTTP_HOST'];
    }

    /** Get query parameters passed with the request */
    public static function get($variable = null, $default = null, $filter = null) {
        if ($variable === null) return $_GET;
        if ($filter == FILTER_VALIDATE_BOOLEAN && isset($_GET[$variable]) && empty($_GET[$variable])) {
            $_GET[$variable] = $default;
        }

        $val = $_GET[$variable] ?? $default;
        if ($filter == null) return $val;

        $result = filter_var($val, $filter, FILTER_NULL_ON_FAILURE);
        return $result !== null ? $result : $default;
    }
    
    /** Checks if the hget data is available 
     * @param string|null $name Name of the form. When supplied, it will check for the existance of that key specifically and that it isn't empty. Default behaviour is the entire $_POST object. 
     * @return bool true if the data exists 
    */
    public static function hasGet($name = null){ 
        if ($name !== null) return isset($_GET[$name]) && count($_GET[$name]) > 0;
        return isset($_GET) && count($_GET) > 0; 
    }

    /** Post query parameters passed with the request 
     * @return mixed
    */
    public static function post($variable = null, $default = null, $filter = null) {
        if ($variable === null) return $_POST;
        if ($filter == null) return $_POST[$variable] ?? $default;
        $result = filter_var($_POST[$variable] ?? $default, $filter);
        return $result !== false ? $result : $default;
    }    
    
    /** Checks if the post data is available 
     * @param string|null $name Name of the form. When supplied, it will check for the existance of that key specifically and that it isn't empty. Default behaviour is the entire $_POST object. 
     * @return bool true if the data exists 
    */
    public static function hasPost($name = null){ 
        if ($name !== null) return isset($_POST[$name]) && count($_POST[$name]) > 0;
        return isset($_POST) && count($_POST) > 0; 
    }


    /** Sets the CSRF token and returns a HTML tag with it 
     * @return string HTML hidden input with CSRF */
    public static function CSRF() {
        $csrf = static::$_CSRF;
        if ($csrf == null || empty($csrf)){
            $data = [
                'tok' => Strings::token(), // <- generates a cryptographically secure random string
                'uid' => Kiss::$app->user != null ? Kiss::$app->user->id : '-1',
            ];

            Kiss::$app->session->set('_csrf', $data);
            static::$_CSRF = $csrf = Kiss::$app->jwtProvider->encode($data, 3600);
        }

        return "<input name='_csrf' type='hidden' value='$csrf' />";
    }

    /** Validates the CSRF passed in the POST and checks it against the stored version
     * @return bool true if it is valid, otherwise false.
     */
    public static function checkCSRF() {
        $csrf = self::post('_csrf', null);
        if ($csrf == null) 
            return false;
            
        try {
            $csrfData = Kiss::$app->jwtProvider->decode($csrf);
            $selfData = Kiss::$app->session->get('_csrf', null);
            if ($selfData == null)                      
                return false;

            if ($csrfData->tok != $selfData['tok'])   // Remove this token part to allow for multi-tab editing
                return false;

            if ($csrfData->uid != $selfData['uid'])   
                return false;

            if ($csrfData->uid != (Kiss::$app->user != null ? Kiss::$app->user->id : '-1'))
                return false;

            return true;
        } catch(Throwable $e) {
            // Since the decode will throw if its invalid, we will catch it and return false.
            return false;
        }
    }

    /** An query paramaters passed with the request */
    public static function request($variable = null, $default = null, $filter = null) {
        if ($variable === null) return $_REQUEST;
        if ($filter == null) return $_REQUEST[$variable] ?? $default;
        $result = filter_var($_REQUEST[$variable] ?? $default, $filter);
        return $result !== false ? $result : $default;
    }

    /** Checks if the request data is available 
     * @param string|null $name Name of the form. When supplied, it will check for the existance of that key specifically and that it isn't empty. Default behaviour is the entire $_POST object. 
     * @return bool true if the data exists 
    */
    public static function hasRequest($name = null){ 
        if ($name !== null) return isset($_REQUEST[$name]) && count($_REQUEST[$name]) > 0;
        return isset($_REQUEST) && count($_REQUEST) > 0; 
    }

    /** Gets a header value. */
    public static function header($variable, $default = null, $filter = null) {
        $variable = Strings::toLowerCase($variable);
        $_HEADERS = self::headers();
        if ($filter == null) return $_HEADERS[$variable] ?? $default;
        $result = filter_var($_HEADERS[$variable] ?? $default, $filter);
        return $result !== false ? $result : $default;
    }

    /** Gets all the headers */
    private static $_headers = null;
    public static function headers() { 
        if (self::$_headers != null) return self::$_headers;
        self::$_headers = [];
        $headers = getallheaders();
        foreach($headers as $key => $value) 
            self::$_headers[Strings::toLowerCase($key)] = $value;
        return self::$_headers;
    }

    /** Gets a cookie value, either from the cookie header or the stored internal cookie cache */
    public static function cookie($variable, $default = null, $includeResponseCookies = true) {
        if ($includeResponseCookies && isset(self::$SET_COOKIES[$variable]))
            return self::$SET_COOKIES[$variable][0];
        return $_COOKIE[$variable] ?? $default;
    }

    private const COOKIE_VALUE = 'VALUE';
    public const COOKIE_EXPIRES = 'Expires';
    public const COOKIE_MAX_AGE = 'Max-Age';
    public const COOKIE_DOMAIN = 'Domain';
    public const COOKIE_PATH = 'Path';
    public const COOKIE_SECURE = 'Secure';
    public const COOKIE_HTTP_ONLY = 'HttpOnly';
    public const COOKIE_SAME_SITE = 'SameSite';

    /** Compatability replacement for PHP's setcookie
     * @deprecated Please use the more modern setCookie function
     */
    public static function setCookieCompat(string $name, string $value = "", int $expires = 0, string $path = "/", string $domain = "", bool $secure = false, bool $httponly = false) {
        self::setCookie($name, $value, [
            self::COOKIE_EXPIRES    => $expires,
            self::COOKIE_PATH       => $path,
            self::COOKIE_DOMAIN     => $domain,
            self::COOKIE_SECURE     => $secure,
            self::COOKIE_HTTP_ONLY  => $httponly,
            self::COOKIE_SAME_SITE  => 'Lax',
        ]);
    }

    /**
     * Sets the cookie
     * @param string $name the name of the cookie
     * @param string $value the value of the cookie
     * @param array $options the options to include. See the constants COOKIE_ for the available options. 
     * @return void 
     */
    public static function setCookie($name, $value, $options = []) {
        self::$SET_COOKIES[$name] = array_merge([self::COOKIE_VALUE => '', self::COOKIE_SAME_SITE => 'Lax' ], $options, [ self::COOKIE_VALUE => $value ]);
    }

    /** Applys all the cookies that need to be set using the setcookie function. */
    public static function applyCookies() {
        foreach(self::$SET_COOKIES as $name => $args) {
            $attributes = [ $name . '=' . $args[self::COOKIE_VALUE] ];
            
            foreach($args as $attr => $val) {
                if ($attr == self::COOKIE_VALUE) continue;

                //if (is_bool($val)) $val = $val ? 'true' : 'false';
                if (empty($val)) continue;
                $attributes[] = $attr . '=' . $val; // NOTE: It might be incorrect to urlencode it here
            }

            $cookie = join('; ', $attributes);
            self::setHeader('Set-Cookie', $cookie, false);
        }
            
        //setcookie($name, ...$args); //this operator is called the "splat operator". It unpacks the arguments.
    }

    /** Sets a header */
    public static function setHeader($key, $value, $replace = true) {
        header("$key: $value", $replace);
    }

    
    /** Static cache of the body, so its only read once. */
    private static $_body = false;

    /** The body supplied with the request */
    public static function body() {
        if (self::$_body !== false) return self::$_body;
        return self::$_body = file_get_contents('php://input');
    }

    /** The json supplied in the request body, as an associative array. */
    public static function json($assoc = true) {
        return json_decode(self::body(), $assoc);
    }

    /** @return string the current IP address of the user */
    public static function ip() {
        return HTTP::header('X-Forwarded-For', HTTP::header('CF-Connecting-IP', $_SERVER['REMOTE_ADDR']));
    }

    /** @return string the current user agent */
    public static function userAgent() {
        return HTTP::header('user-agent', null);
    }

}