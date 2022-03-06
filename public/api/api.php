<?php 


//defined('KISS_SESSIONLESS') or define('KISS_SESSIONLESS', true);
defined('KISS_DEBUG') or define('KISS_DEBUG', in_array(@$_SERVER['REMOTE_ADDR'], ['127.0.0.1', '::1']));
define('PUBLIC_DIR', __DIR__ . '/..');

include __DIR__ . "/../../autoload.php";

use kiss\controllers\api\ApiRoute;
use kiss\db\Query;
use kiss\exception\AggregateException;
use kiss\exception\HttpException;
use kiss\exception\UncaughtException;
use kiss\helpers\HTTP;
use kiss\helpers\Response;
use kiss\helpers\Strings;
use kiss\Kiss;
use kiss\models\BaseObject;
use kiss\router\RouteFactory;
use PhpParser\Node\Expr\Cast;

//Setup a collection of defaults
Kiss::$app->setDefaultResponseType(HTTP::CONTENT_APPLICATION_JSON);
BaseObject::$defaults[Query::class] = [ //Clear all the caching settings for API calls.
    'remember'      => false,
    'cacheDuration' => 0,
    'cacheVersion'  => -1,
    'flushCache'    => true,
];

//We are going to be HYPER CRITICAL in the API
// And any error we didnt expect, we will capture and immediately terminate
set_error_handler(function($errno, $errstr, $errfile, $errline) {
    $exception = new UncaughtException($errno, $errstr, $errfile, $errline);        
    Kiss::$app->respond($exception);
    exit;
});

if (KISS_DEBUG)
    Response::$saveRequest = true;

try {
    //Prepare the route we wish to use 
    //Just exit with no response because they are accessing the API page directly
    $route = Strings::trimEnd(HTTP::route(), '/');
    if (empty($route)) die('no route given');

    //Register all the routes in the specified folder    
    $basedir = Kiss::$app->baseDir();
    RouteFactory::registerDirectory($basedir . "/controllers/api/", ["*.php", "**/*.php"]);

    //Break up the segments and get the controller
    $segments = explode('/', substr($route, 4));
    $controller = RouteFactory::route($segments);
    
    if ($controller == null) 
        throw new HttpException(HTTP::NOT_FOUND, "'{$route}' is not a valid endpoint");

    if (!($controller instanceof ApiRoute))
        throw new HttpException(HTTP::INTERNAL_SERVER_ERROR, 'route is not a valid API route');
    
    //Invoke the event
    $response = $controller->action($route);
    return Kiss::$app->respond($response);
} catch(HttpException $exception) {
    return Kiss::$app->respond($exception);
} catch(\Throwable $exception) {
    return Kiss::$app->respond(new HttpException(HTTP::INTERNAL_SERVER_ERROR, $exception));
}
