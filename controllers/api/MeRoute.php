<?php namespace app\controllers\api;

use GALL;
use kiss\controllers\api\ApiRoute;
use kiss\Kiss;
use kiss\router\Route;
use kiss\router\RouteFactory;

class MeRoute extends BaseApiRoute {
    use \kiss\controllers\api\Actions;


    //We are going to return our routing. Any segment that starts with : is a property.
    // Note that more explicit routes get higher priority. So /example/apple will take priority over /example/:fish
    protected static function route() { return "/@me"; }

    /** @inheritdoc */
    protected function scopes() { return [ ]; } 
    
    //HTTP GET on the route. Return an object and it will be sent back as JSON to the client.
    // Throw an exception to send exceptions back.
    // Supports get, delete
    public function get() {
        return [
            'auth'      => GALL::$app->user->authorization(),
            'user'      => GALL::$app->user,
            'acting'    => $this->actingUser == GALL::$app->user ? null : $this->actingUser
        ];
    }
}