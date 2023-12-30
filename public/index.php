<?php 
// enables debugging if we are a local host
defined('KISS_DEBUG') or define('KISS_DEBUG', in_array(@$_SERVER['REMOTE_ADDR'], ['127.0.0.1', '::1']));
define('PUBLIC_DIR', __DIR__);
ini_set('display_errors', KISS_DEBUG ? 1 : 0);

require_once '../autoload.php';

use kiss\controllers\Controller;
use kiss\exception\HttpException;
use kiss\helpers\HTML;
use kiss\helpers\HTTP;
use kiss\Kiss;
use kiss\router\RouteFactory;

//Update the base URL on the HTML
HTML::$route = HTTP::route();
HTTP::setReferral();

//Prepare the segments
$segments = explode('/', HTML::$route);                       //Get all the segments in the route
$routable = array_slice($segments, 0, -1);              //Lob off the last segment as that is our action
$endpoint = $segments[count($segments)-1];              

//Account for the main controller
if (count($segments) <= 2) {
    $routable = [ '', 'main' ];
    $endpoint = $segments[1] ?? 'index';
}

 //If the last part is empty, then we are index
if (empty($endpoint))       
    $endpoint = 'index';       

try {
    //Register all the routes in the specified folder
    $basedir = Kiss::$app->baseDir();
    RouteFactory::registerDirectory($basedir . "/controllers/", ["*.php", "**/*.php"]);

    //Get the controller
    $controller = RouteFactory::route($routable);
    if ($controller == null) {
        throw new HttpException(HTTP::NOT_FOUND, 'controller could not be found.');
    }

    if (!($controller instanceof Controller)) {
        throw new HttpException(HTTP::INTERNAL_SERVER_ERROR, 'route is not a valid Controller');
    }

    //Attempt to get the event
    $response = $controller->action($endpoint);
    return Kiss::$app->respond($response);
} catch(HttpException $exception) {
    return Kiss::$app->respond($exception);
} catch(\Exception $exception) {
    return Kiss::$app->respond(new HttpException(HTTP::INTERNAL_SERVER_ERROR, $exception));
}
