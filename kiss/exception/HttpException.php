<?php
namespace kiss\exception;

use Exception;
use Throwable;

class HttpException extends \Exception
{
    private $status = 500;
    private $exception;


    /**
     * @param int[optional]    $statusCode   If NULL will use 500 as default
     * @param string[optional] $body If NULL will use the default status phrase
     * @param array[optional]  $headers      List of additional headers
     */
    public function __construct($statusCode = 500, $error = null)
    {
        $message = '';

        $this->status = $statusCode;

        if ($error instanceof Throwable) {
            $message = get_class($error) . ": " . $error->getMessage();
            $this->exception = $error;
        } else if (is_array($error)) {
            $message = $error['message'] ?? 'an exception occured';
            $this->exception = $error;
            $error = null;
        } else {
            $message = $error;
            $this->exception = null;
            $error = null;
        }

        parent::__construct($message, $statusCode, $error);
    }


    /** Gets the status code. */
    public function getStatus() { return $this->status; }

    /**
     * Return the body message.
     *
     * @return string
     */
    public function getInnerException()
    {
        return $this->exception;
    }
}

/*
    private $statuses = array(
        400 => 'Bad Request',
        401 => 'Unauthorized',
        402 => 'Payment Required',
        403 => 'Forbidden',
        404 => 'Not Found',
        405 => 'Method Not Allowed',
        406 => 'Not Acceptable',
        407 => 'Proxy Authentication Required',
        408 => 'Request Timeout',
        409 => 'Conflict',
        410 => 'Gone',
        411 => 'Length Required',
        412 => 'Precondition Failed',
        413 => 'Request Entity Too Large',
        414 => 'Request-URI Too Long',
        415 => 'Unsupported Media Type',
        416 => 'Requested Range Not Satisfiable',
        417 => 'Expectation Failed',
        418 => 'I\'m a teapot', // RFC 2324
        419 => 'Authentication Timeout', // not in RFC 2616
        420 => 'Method Failure', // Spring Framework
        420 => 'Enhance Your Calm', // Twitter
        422 => 'Unprocessable Entity', // WebDAV; RFC 4918
        423 => 'Locked', // WebDAV; RFC 4918
        424 => 'Failed Dependency', // WebDAV; RFC 4918
        424 => 'Method Failure', // WebDAV)
        425 => 'Unordered Collection', // Internet draft
        426 => 'Upgrade Required', // RFC 2817
        428 => 'Precondition Required', // RFC 6585
        429 => 'Too Many Requests', // RFC 6585
        431 => 'Request Header Fields Too Large', // RFC 6585
        444 => 'No Response', // Nginx
        449 => 'Retry With', // Microsoft
        450 => 'Blocked by Windows Parental Controls', // Microsoft
        451 => 'Unavailable For Legal Reasons', // Internet draft
        451 => 'Redirect', // Microsoft
        494 => 'Request Header Too Large', // Nginx
        495 => 'Cert Error', // Nginx
        496 => 'No Cert', // Nginx
        497 => 'HTTP to HTTPS', // Nginx
        499 => 'Client Closed Request', // Nginx
        500 => 'Internal Server Error',
        501 => 'Not Implemented',
        502 => 'Bad Gateway',
        503 => 'Service Unavailable',
        504 => 'Gateway Timeout',
        505 => 'HTTP Version Not Supported',
        506 => 'Variant Also Negotiates', // RFC 2295
        507 => 'Insufficient Storage', // WebDAV; RFC 4918
        508 => 'Loop Detected', // WebDAV; RFC 5842
        509 => 'Bandwidth Limit Exceeded', // Apache bw/limited extension
        510 => 'Not Extended', // RFC 2774
        511 => 'Network Authentication Required', // RFC 6585
        598 => 'Network read timeout error', // Unknown
        599 => 'Network connect timeout error', // Unknown
    );
    */