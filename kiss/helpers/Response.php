<?php

namespace kiss\helpers;

use controllers\main\MainController;
use \Exception;
use kiss\controllers\Controller;
use kiss\exception\HttpException;
use kiss\exception\NotYetImplementedException;
use kiss\Kiss;
use kiss\models\BaseObject;

class Response {
    
    private $status;
    private $headers;
    private $contentType;
    private $content;
    
    /** @var bool saves the request to the disk */
    public static $saveRequest = false;

    /** @var int $jsonFlags Flags for serializing the json */
    public static $jsonFlags = JSON_BIGINT_AS_STRING;
    /** @var int $jsonDepth Depth of JSON serialization */
    public static $jsonDepth = 512;

    function __construct($status, $headers, $content, $contentType)
    {
        $this->status = $status;
        $this->headers = $headers ?? [];
        $this->content = $content;
        $this->contentType = $contentType;
    }

    /** Creates a new response to handle the exception. If the supplied mode is null, it will use the server's default.
     * @param \Throwable $exception 
     * @return Response the response
     */
    public static function exception($exception, $status = HTTP::INTERNAL_SERVER_ERROR, $mode = null) {
        if ($exception instanceof HttpException)                return self::httpException($exception, $mode);
        if ($exception instanceof NotYetImplementedException)   return self::httpException(new HttpException(HTTP::NOT_IMPLEMENTED, $exception->getMessage()), $mode);
        return self::httpException(new HttpException($status, $exception), $mode);
    }

    /** Creates a new response to handle the exception. If the supplied mode is null, it will use the server's default. 
     * @return Response the response
     */
    public static function httpException(HttpException $exception, $mode = null) {
        if ($mode == null) $mode = Kiss::$app->getDefaultResponseType();
        switch ($mode){
            default:
            case HTTP::CONTENT_TEXT_PLAIN:
                return self::text($exception->getStatus(), $exception->getMessage());

            case HTTP::CONTENT_APPLICATION_JSON:
                return self::json($exception->getStatus(), $exception->getInnerException(), $exception->getMessage());

            case HTTP::CONTENT_TEXT_HTML:
                try {
                    //Try to get the controller and execute the actionException on it
                    $controllerClass = Kiss::$app->mainControllerClass;
                    $controller = BaseObject::new( $controllerClass);
                    $response = $controller->action('exception', $exception);
                    //$response = $controller->renderException($exception);
                    return self::html($exception->getStatus(), $response);
                } catch(Exception $ex) { 
                    //An error occured, so we ill just use the default plain text handling
                    return self::httpException($exception, HTTP::CONTENT_TEXT_PLAIN);
                }
        }
    }

    /** Creates a new plain text response 
     * @return Response the response
     */
    public static function text($status, $data) {
        return new Response($status, [], $data, HTTP::CONTENT_TEXT_PLAIN);
    }

    /** Creates a new json response 
     * @return Response the response
     */
    public static function json($status, $data, $message = '') {
        return self::jsonRaw($status,  ['status' => $status, 'message' => $message, 'data' => $data ]);
    }
       
    /** Creates a new json response without any of the wrappings. A raw response.
     * @return Response the response
     */
    public static function jsonRaw($status, $data) {
        return new Response($status, [], $data, HTTP::CONTENT_APPLICATION_JSON);
    }


    /** Creates a new xml response
     * @return Response the response
     */
    public static function xml($status, $data) {
        return new Response($status, [], $data, HTTP::CONTENT_APPLICATION_XML);
    }

    /** Creates a new html response 
     * @return Response the response
     */
    public static function html($status, $data) {
        return new Response($status, [], $data, HTTP::CONTENT_TEXT_HTML);
    }

    /** Creates a new file response 
     * @return Response the response
     */
    public static function file($fileName, $data) {
        return new Response(HTTP::OK, [ 
            'Content-Transfer-Encoding' => 'Binary', 
            'Content-disposition' => 'attachment; filename="'.$fileName.'"' 
        ], $data, HTTP::CONTENT_APPLICATION_OCTET_STREAM);
    }

    /** Creates a new javascript response 
     * @return Response the response
     */
    public static function javascript($data) {
        return new Response(HTTP::OK, [], $data, HTTP::CONTENT_APPLICATION_JAVASCRIPT);
    }

    /** Creates a new javascript response 
     * @param mixed $data the raw image data
     * @param string $extension the file extension
     * @param string $fileName optional file name. If given, then the image will be downloaded instead. File name must not contain any extension.
     * @return Response the response
     */
    public static function image($data, $extension, $fileName = false) {
        if ($fileName != false) {
            return new Response(HTTP::OK, [
                        'Content-Transfer-Encoding' => 'Binary', 
                        'Content-disposition' => 'attachment; filename="'.$fileName.'.'.$extension.'"' 
            ], $data, "image/$extension");
        }
        
        return new Response(HTTP::OK, [ ], $data, "image/$extension");
    }

    /** Creates a new redirect response 
     * @return Response the response
    */
    public static function redirect($location, $status = HTTP::OK) {
        return (new Response($status, [], "Redirecting...", HTTP::CONTENT_TEXT_PLAIN))->setLocation($location);
    }

    /** Creates a new redirect response to the current route
     * @return Response the response */
    public static function refresh($time = false, $status = HTTP::OK) {
        if ($time === false || $time <= 0)
            return (new Response($status, [], "Reloading...", HTTP::CONTENT_TEXT_PLAIN))->setLocation(HTTP::route());
        return (new Response($status, [], "Reloading in " . $time .'s...', HTTP::CONTENT_TEXT_PLAIN))->setRefresh(HTTP::route(), $time);
    }

    /** Sets a header and returns the response. */
    public function setHeader($header, $value) {
        $this->headers[$header] = $value;
        return $this;
    }

    /** Sets the content type and returns the response. */
    public function setContentType($type) {
        $this->contentType = $type;
        return $this;
    }

    /** Sets the location header and returns the response. */
    public function setLocation($location) {
        $this->headers['location'] = HTTP::url($location);
        return $this;
    }

    /** Sets the refresh header and returns the response */
    public function setRefresh($location, $time) {
        $this->headers['refresh'] = "{$time}; URL=" . HTTP::url($location);
        return $this;
    }

    /** Sets the response contents and returns the response itself. */
    public function setContent($content, $contentType = null) {
        $this->content = $content;
        $this->contentType = $contentType ?? $this->contentType;
        return $this;
    }

    /** @return string gets the content */
    public function getContent() { return $this->content; }


    /** Executes the response, setting the current page's response code & headers, echoing out the contents and then exiting. */
    public function respond() {
        
        //Prepare the response data.
        // We want to make sure the were able to parse the json data
        $body = $this->content;
        if ($this->contentType == HTTP::CONTENT_APPLICATION_JSON) {
            $body = json_encode($this->content, self::$jsonFlags, self::$jsonDepth);
            if ($body === false) {
                $this->status       = HTTP::INTERNAL_SERVER_ERROR;
                $this->contentType  = HTTP::CONTENT_TEXT_PLAIN;
                $body = 'failed to parse json: ' . json_last_error_msg();
            }
        }
        
        if (KISS_ENV !== 'CLI') {
            //Set the status code
            http_response_code($this->status);

            //Cookies
            HTTP::applyCookies();
            
            //Update the content type
            $this->headers['content-type'] = $this->headers['content-type'] ?? $this->contentType;

            //Add all the headers
            foreach($this->headers as $key => $pair) {
                header($pair == null ? $key : $key . ": " . $pair);
            }
        } else {
            $body .= PHP_EOL;
        }

        if (self::$saveRequest) {
            file_put_contents('./last_request.json', json_encode([
                '_ROUTE'    => HTTP::route(),
                '_HEAD'     => HTTP::headers(),
                '_REQUEST'  => HTTP::request(), 
                '_GET'      => HTTP::get(),
                '_POST'     => HTTP::post(), 
                '_BODY'     => HTTP::body(), 
                '_RESPONSE' => [ 
                    'headers' => $this->headers,
                    'body' => $body
                ]
                ], JSON_PRETTY_PRINT));
        }

        //Finally, respond with the body
        die($body);
    }
}