<?php namespace kiss\controllers\api;

use kiss\exception\HttpException;
use kiss\exception\UncaughtException;
use kiss\helpers\HTTP;
use kiss\Kiss;
use kiss\router\Route;

class ApiRoute extends Route {
    use Actions;

    /** @inheritdoc */
    protected function scopes() { 
        $perm = get_called_class()::route();
        $perm = preg_replace('/\/:[a-zA-Z_]*/', '', $perm);
        $perm = str_replace('/', '.', $perm);
        $perm = substr($perm, 1);
        return [ $perm ];
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

        //Depending on the method, we want to execute specific functions
        //TODO: Catch exceptions and return them
        switch (HTTP::method()) {
            default: 
                throw new HttpException(HTTP::METHOD_NOT_ALLOWED);
            
            case 'GET': 
                return $this->get();
            case 'HEAD': 
                return $this->head();   
            
            case 'DELETE': 
                return $this->delete();

            case 'OPTIONS': 
                $this->options();

            case 'PUT':
            case 'PATCH':
                return $this->put(HTTP::json(true));

            case 'POST':
                return $this->post(HTTP::json(true));
        }
    }

    /** HTTP HEAD Request. This should contain no content and only give back the header information.
     * @return Response response for KISS. If an object is pass, its turned into a JSON object.
    */ 
    public function head() { 
        $response = $this->get(); 
        $response->setContent(null);
        return $response;
    }
}