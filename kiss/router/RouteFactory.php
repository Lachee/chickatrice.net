<?php
namespace kiss\router;

use kiss\exception\ArgumentException;
use kiss\helpers\Strings;
use kiss\models\BaseObject;

class RouteFactory {

    const ARGUMENT_PREFIX = ":";
    private static $routes = [];

    /** @var string the class name of the route */
    private $className;

    /**
     * Creates a new RouteFactory
     * @param string $routeClass class that inherits from Route
     * @return void 
     */
    public function __construct($routeClass) {        
        assert(is_subclass_of($routeClass, Route::class), 'Class isnt a route');
        $this->className = $routeClass;
    }
    
    /** @return string[] the routing */
    public function getRouting() { return $this->className::getRouting(); }

    /** @return string[] list of parameters in the route */
    public function getParameters() { return $this->className::getParameters(); }

    /**
     * Scores how good the Route matches the supplied segments. Higher the better.
     * @param string[] $segments the URL segments
     * @return int the score. Higher is better.
     * @throws ArgumentException 
     */
    public function score($segments) {
        
        if (!is_array($segments)) 
            throw new ArgumentException("Segements must be an array");

        $routing = $this->getRouting();
        if (count($segments) != count($routing)) return 0;

        $score = 0;
        for ($i = count($routing) - 1; $i >= 0; $i--) {
            if (trim($routing[$i]) == trim($segments[$i]))                              $score += 2;    //We match exactly, bonus points
            else if (Strings::startsWith($routing[$i], self::ARGUMENT_PREFIX))     $score += 1;    //We match in the argument, so some points
            else return 0;                                                                              //We stopped matching, so abort early.
        }

        //Return the score
        return $score;
    }

    /** create will convert the provided URL segments into a new object with properties that match the routing settings.
     * IE: /apples/:count => /apples/4252 :
     * AppleRoute {
     *     count => 4252
     * }
     * @return Route the created route
     */
    public function create($segments) { 
        
        //Go throught he routes and find all the properties 
        $routing = $this->getRouting();
        $properties = [];
        for ($i = 0; $i < count($routing); $i++) {
            if (Strings::startsWith($routing[$i], self::ARGUMENT_PREFIX)) {
                $name = substr($routing[$i], 1);
                $properties[$name] = $segments[$i];
            }
        }

        //Return the new object
        return BaseObject::new($this->className, $properties);
    }


    /**
     * Creates a new route factory for the class and registers it to the global list
     * @param string $routeClass the class of the [[Route]]
     * @return bool true if it was registered
     */
    public static function register($routeClass) {
        if ($routeClass == Route::class || !is_subclass_of($routeClass, Route::class)) return false;
        self::$routes[] = new RouteFactory($routeClass);
        return true;
    }

    /** Registers the directory of routes. */
    public static function registerDirectory($directory, $filters = "*.php") {
        if (!is_array($filters)) 
            $filters = [$filters];

        //List of files
        $files = [];

        //Go through every filter
        foreach($filters as $filter) {

            //Scan the directory and include all the files
            $glob = glob($directory . $filter);
            foreach ($glob as $filename)
            {
                if (@include_once $filename) {
                    $files[] = $filename;
                }
            }
        }

        //Search all the declared classes and register them
        //TODO: Be smart and only iterate over the files array.
        foreach(get_declared_classes() as $class) {
            if(is_subclass_of($class, Route::class)) 
                self::register($class);
        }

        //Return how many we found.
        return count($files);
    }

    /**
     * Finds and creates the best route that matches the supplied segments
     * @param string[] $segments parts of the URL
     * @return Route|null the best route, otherwise null.
     */
    public static function route($segments) {
        $bestScore = 0;
        $bestRoute = null;
        foreach(self::$routes as $r) {
            $score = $r->score($segments);
            if ($score > $bestScore) {
                $bestScore = $score;
                $bestRoute = $r;
            }
        }

        if ($bestRoute == null) return null;
        return $bestRoute->create($segments);
    }

    /** Gets a list of routes and their supported methods. */
    public static function getRoutes() {
        $names = [];
        foreach(self::$routes as $r) {
            $path = join('/', $r->getRouting());
            $methods = [];
            $controller = new $r->className;
            if (method_exists($controller, 'get')) $methods[] = 'get'; 
            if (method_exists($controller, 'delete')) $methods[] = 'delete';
            if (method_exists($controller, 'put')) $methods[] = 'put';
            if (method_exists($controller, 'post')) $methods[] = 'post';
            $names[$path] = $methods;
        }
        return $names;
    }
}