<?php
namespace kiss\controllers\cli;

use Exception;
use kiss\exception\AggregateException;
use kiss\exception\HttpException;
use kiss\exception\MissingViewException;
use kiss\exception\UncaughtException;
use kiss\helpers\HTTP;
use kiss\helpers\Response;
use kiss\helpers\Strings;
use kiss\Kiss;
use kiss\models\Identity;
use kiss\router\Route;
use Throwable;

class Command extends Route {
   

    public function authenticate($identity) {
        return KISS_ENV == 'CLI';
    }

    /** Gets the route for the controller.
     * @return string 
     */
    protected static function route() {
        $class = get_called_class();

        //Trim off the command
        $lastIndex = strrpos($class, "Command");
        if ($lastIndex !== false) {
            $name = substr($class, 0, $lastIndex);
        }

        $parts = explode('\\', $name);
        $count = count($parts);
        $route = '';

        if (strtolower($parts[$count - 2]) == strtolower($parts[$count - 1]))
            $count -= 1;

        for ($i = 3; $i < $count; $i++) {
            if (empty($parts[$i])) continue;

            $lwr = strtolower($parts[$i]);
            $route .= '/' . $lwr;
            
        }
        return $route;
    }

    /** Performs the endpoint's action */
    public function action($endpoint, ...$args) {

        //Set hte error handling
        $this->uncaughtException = null;
        set_error_handler(function($errno, $errstr, $errfile, $errline) {
            if ( E_RECOVERABLE_ERROR === $errno ) {
                $this->uncaughtException = new UncaughtException($errno, $errstr, $errfile, $errline, $this->uncaughtException);
                return true;
            }
            return false;
        });

        //verify our authentication
        $this->authenticate(Kiss::$app->user);

        //Attempt to get the event
        $action = $this->getAction($endpoint);
        if ($action === false) {
            throw new HttpException(HTTP::NOT_FOUND, 'Action not found');
        }
        
        try {
            //Perform the action
            $value = $this->{$action}(...$args);
        } catch(\Throwable $throwable) {
            if ($this->uncaughtException != null)
                return Response::exception(new AggregateException($throwable, $this->uncaughtException));
            return Response::exception($throwable);
        }

        //We didnt catch any errors, but we still have an exception to respond with.
        if ($this->uncaughtException != null) 
            return Response::exception($this->uncaughtException);

        //Proceed as normal and just return the value
        $response = Kiss::$app->respond($value);
        return $response;  
    }

    /** Prints a new line
     * @param string|mixed the line to print, otherwise the data to echo out.
     */
    public static function print($line) {
        $str = $line;
        if (!is_string($line)) {
            ob_start();
            var_dump($line);
            $str = ob_get_clean();
        }
        
        echo $str . PHP_EOL;
    }

    /** Gets the action name */
    protected function getAction($endpoint) {
        $endpoint = ucfirst(strtolower($endpoint));
        $action = "cmd{$endpoint}";
        if (!method_exists($this, $action)) { return false; }
        return $action;
    }

    /** Exports all the variables */
    protected function export() 
    {        
        $properties = self::getParameters();
        $exported = [];
        foreach($properties as $name) {
            $exported[$name] = $this->{$name};
        }
        return $exported;
    }
}