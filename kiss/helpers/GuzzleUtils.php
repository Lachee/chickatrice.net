<?php namespace kiss\helpers;

use \Psr\Http\Message\StreamInterface;

class GuzzleUtils {

    /**
     * 
     * @param \Psr\Http\Message\StreamInterface $body 
     * @param Callable $callback 
     * @return string the chunked HTML content 
     */
    public static function chunkReadUntil($body, $callback) {
        $line = '';
        while(!$body->eof()) {
            $line .= $body->read(2048);
            if (is_callable($callback) && call_user_func($callback, $line) === true)
                break;
        }
        return $line;
    }
}