<?php

function shutdown_handler() {    
    $error = error_get_last();
    if ($error !== null && $error['type'] === E_ERROR | E_COMPILE_ERROR) {
        $errfile = $error["file"];
        $errline = $error["line"];
        $errstr  = $error["message"];

        http_response_code(500);
        echo "<h1>FATAL E_ERROR OCCURED</h1>";
        echo "<h3>$errstr</h3>";
        echo "<a href='vscode://file/$errfile:$errline'>$errfile :: <i>$errline</i></a>";
        return;
    }
}
register_shutdown_function('shutdown_handler');


ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

//Define the autoload directory and file
define('KISS_AUTOLOAD_DIR', __DIR__);
define('KISS_AUTOLOAD_FILE', __FILE__);

defined('KISS_DEBUG') or define('KISS_DEBUG', false);
defined('KISS_PROD') or define('KISS_PROD', !KISS_DEBUG);
defined('KISS_ENV') or define('KISS_ENV', KISS_DEBUG ? 'DEBUG' : 'PRODUCTION');

//Register the vendor autoload
include 'vendor/autoload.php';

//Register the basic autoloader
spl_autoload_register(function ($name) 
{ 
    if ($name == false) return false;  
        
    //Try to find a map based of kiss
    if (class_exists('\\kiss\\Kiss', false) && \kiss\Kiss::$app != null) {


        //Attempt to trim
        $base = \kiss\Kiss::$app->getBaseNamespace();
        if (strpos($name, $base) === 0) {
            $name = substr($name, strlen($base));            
        }
    }

    $file = __DIR__ . "/$name.php";
    $file = str_replace('\\', '/', $file);
    if (!file_exists($file)) return false;
    return @include_once($file);
});

//Implement functions for older versions of PHP
if (!function_exists('is_countable')) {
    function is_countable($c) {
        return is_array($c) || $c instanceof Countable;
    }    
}

//Configure and create instance
include 'config.php';
if (empty($config)) { $config = []; }

Global $kiss;
$kiss = \kiss\models\BaseObject::new(\kiss\Kiss::class, $config);