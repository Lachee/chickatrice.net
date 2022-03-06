<?php

chdir(__DIR__);

// This line specifically tells KISS what the route is
define('BASE_URL', 'http://localhost:' . $_SERVER['SERVER_PORT'] . '/');
$URL = $URI = $_SERVER["REQUEST_URI"]; 
if (($i = strpos($URI, '?')) !== false) $URL = substr($URL, 0, $i);
$_REQUEST['route'] = $_SERVER['REQUEST_URL'] = $URL;

if (strpos($_REQUEST['route'], '/api') === 0) {

    // rewrite to our api file
    //$_REQUEST["route"] = substr($_SERVER["REQUEST_URI"], 4); 
    include __DIR__ . DIRECTORY_SEPARATOR . '/api/api.php';
}

$filePath = realpath(ltrim($_SERVER["REQUEST_URI"], '/'));
if ($filePath && is_dir($filePath)){
    // attempt to find an index file
    foreach (['index.php', 'index.html'] as $indexFile){
        if ($filePath = realpath($filePath . DIRECTORY_SEPARATOR . $indexFile)){
            break;
        }
    }
}
if ($filePath && is_file($filePath)) {
    // 1. check that file is not outside of this directory for security
    // 2. check for circular reference to router.php
    // 3. don't serve dotfiles
    if (strpos($filePath, __DIR__ . DIRECTORY_SEPARATOR) === 0 &&
        $filePath != __DIR__ . DIRECTORY_SEPARATOR . 'router.php' &&
        substr(basename($filePath), 0, 1) != '.'
    ) {
        if (strtolower(substr($filePath, -4)) == '.php') {
            // php file; serve through interpreter
            include $filePath;
        } else {
            // asset file; serve from filesystem
            return false;
        }
    } else {
        // disallowed file
        header("HTTP/1.1 404 Not Found");
        echo "404 Not Found";
    }
} else {
    


    // rewrite to our index file
    include __DIR__ . DIRECTORY_SEPARATOR . 'index.php';
    
}