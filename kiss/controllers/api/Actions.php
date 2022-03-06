<?php namespace kiss\controllers\api;

use kiss\exception\HttpException;
use kiss\helpers\HTTP;

trait Actions {
    
    /** HTTP GET Request 
     * @return object|Response response for KISS. If an object is pass, its turned into a JSON object.
    */ 
    public function get() { throw new HttpException(HTTP::METHOD_NOT_ALLOWED, 'method not allowed'); }
    
    /** HTTP OPTIONS Request 
     * @return object|Response response for KISS. If an object is pass, its turned into a JSON object.
    */ 
    public function options() { throw new HttpException(HTTP::METHOD_NOT_ALLOWED, 'method not allowed'); }
    
    /** HTTP DELETE Request 
     * @return object|Response response for KISS. If an object is pass, its turned into a JSON object.
    */ 
    public function delete() { throw new HttpException(HTTP::METHOD_NOT_ALLOWED, 'method not allowed'); }
    
    /**  HTTP PUT Request
     * @param array $data array of data from the body
     * @return object|Response response for KISS. If an object is pass, its turned into a JSON object.
    */ 
    public function put($data) { throw new HttpException(HTTP::METHOD_NOT_ALLOWED, 'method not allowed'); }    
    
    /**  HTTP POST Request
     * @param array $data array of data from the body
     * @return object|Response response for KISS. If an object is pass, its turned into a JSON object.
    */ 
    public function post($data) { throw new HttpException(HTTP::METHOD_NOT_ALLOWED, 'method not allowed'); }

}