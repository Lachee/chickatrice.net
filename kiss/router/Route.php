<?php
namespace kiss\router;

use Exception;
use kiss\exception\HttpException;
use kiss\exception\NotYetImplementedException;
use kiss\helpers\HTTP;
use kiss\helpers\Scope;
use kiss\helpers\Strings;
use kiss\models\BaseObject;
use kiss\models\Identity;

class Route extends BaseObject {

    const ARGUMENT_PREFIX = ":";
    private static $_cache = [];

    /** @return string the route the controller is on */
    protected static function route() { 
        $class = get_called_class();
        return str_replace('\\', '/', $class);
    }

    /** @return string[] a list of scopes that are required for this route. Return null for no scopes required.
     * jwt:key:value for JWT specific scopes (like API checks)
     */
    protected function scopes() { return null; }

    /** Checks if hte identity has the requried scopes.
     * @param Identity $identity
     * @return bool true if they have meet all the scope requirements
    */
    public function authenticate($identity) {
        //TODO: Put RBAC system here.
        if (!Scope::authenticate($identity, $this->scopes())) {
            if ($identity == null) throw new HttpException(HTTP::UNAUTHORIZED, 'Please provide authorization');
            throw new HttpException(HTTP::FORBIDDEN, 'Invalid Scopes. Required: ' . join(', ', $this->scopes()));
        }
    }

    /** @return string[] Gets the routing itself */
    public static function getRouting() { 
        $cache = self::getRouteCache(get_called_class());
        return $cache[0];
    }

    /** @return string[] Gets the route parameters */
    public static function getParameters() {
        $cache = self::getRouteCache(get_called_class());
        return $cache[1];
    }

    /** Clears the cached routes and parameters
     * @return void 
     */ 
    public static function clearRouteCache() { 
        self::$_cache = [];
    }

    /** Gets the route from the cache  */
    private static function getRouteCache($class) {       
        
        //Just return the cache
        if (isset(self::$_cache[$class]))
            return self::$_cache[$class];
      
        $str = $class::route();
        //if (empty($str)) throw new Exception("Route $class has no routing");
        $route  = explode('/', $str);
        $params = [];

        //Calculate hte params from the route
        for ($i = 0; $i < count($route); $i++) {
            if (Strings::startsWith($route[$i], self::ARGUMENT_PREFIX)) 
                $params[] =  substr($route[$i], 1);
        }

        //Creat ethe cahce
        self::$_cache[$class] = [ $route, $params ];
        return self::$_cache[$class];
    }
}