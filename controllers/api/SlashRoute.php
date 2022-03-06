<?php namespace app\controllers\api;

use app\components\discord\interaction\Interaction;
use app\models\Tag;
use GALL;
use kiss\controllers\api\ApiRoute;
use kiss\exception\HttpException;
use kiss\exception\UncaughtException;
use kiss\helpers\Arrays;
use kiss\helpers\HTTP;
use kiss\helpers\Response;
use kiss\Kiss;
use kiss\router\Route;
use kiss\router\RouteFactory;

class SlashRoute extends BaseApiRoute {
    use \kiss\controllers\api\Actions;


    const DEFAULT_PAGE_SIZE = 10;
    const MAX_PAGE_SIZE = 150;

    /** @inheritdoc */
    protected static function route() { return "/slash"; }
    
    /** @inheritdoc */
    protected function scopes() { return null; } // Proxy doesn't need any scopes since it handles its own.

    public function authenticate($identity) {
        if (!GALL::$app->discord->verifyInteractionSignature())
            throw new HttpException(HTTP::UNAUTHORIZED, 'Bad signature');
    }


    public function action($endpoint) {
        //We are going to be HYPER CRITICAL in the API
        // And any error we didnt expect, we will capture and immediately terminate
        set_error_handler(function($errno, $errstr, $errfile, $errline) {
            $exception = new UncaughtException($errno, $errstr, $errfile, $errline);        
            Kiss::$app->respond($exception);
            exit;
        });

        //verify our authentication
        $this->authenticate(Kiss::$app->user);

        //Prepare the data
        $data = HTTP::json();
        
        /** Handle the Pings */
        if ($data['type'] == Interaction::REQUEST_TYPE_PING)
            return Response::jsonRaw(HTTP::OK, [ 'type' => Interaction::RESPONSE_TYPE_PONG ]);
                
        /** Handle ApplicationCommand */
        if ($data['type'] == Interaction::REQUEST_TYPE_APPLICATION_COMMAND) {
            return Response::jsonRaw(HTTP::OK, [ 'type' => Interaction::RESPONSE_TYPE_ACKNOWLEDGE ]);
            $interaction = GALL::$app->discord->createInteraction($data);
            return Response::jsonRaw(HTTP::OK, $interaction->respond());
        }

        //Verify the resposne then return it.
        return Response::jsonRaw(HTTP::BAD_REQUEST, [ ]);
    }

    /** Handles the command  */
    public function command($interaction) {
        return [
            'type' => Interaction::RESPONSE_TYPE_ACKNOWLEDGE
        ];
    }
}