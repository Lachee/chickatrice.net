<?php namespace app\controllers\api;

use app\models\cockatrice\Uptime;
use kiss\controllers\api\ApiRoute;
use kiss\Kiss;
use kiss\router\Route;
use kiss\router\RouteFactory;

class StatusRoute extends BaseApiRoute {
    use \kiss\controllers\api\Actions;


    //We are going to return our routing. Any segment that starts with : is a property.
    // Note that more explicit routes get higher priority. So /example/apple will take priority over /example/:fish
    protected static function route() { return "/status"; }

    //HTTP GET on the route. Return an object and it will be sent back as JSON to the client.
    // Throw an exception to send exceptions back.
    // Supports get, delete
    public function get() {
        return Uptime::getUptimes()
                ->fields(['timest', 'uptime', 'users_count', 'games_count'])
                ->all(true);
    }
}