<?php namespace kiss\exception;

use Exception;

class AggregateException extends Exception {

    /** @var \Throwable[] */
    public $exceptions;

    public function __construct(...$exceptions)
    {
        parent::__construct("Aggregate exception has occured");
        
        $this->exceptions = [];
        foreach($exceptions as $e) {
            if ($e instanceof AggregateException) {
                array_push($this->exceptions, $e->exceptions);
            } else {
                $this->exceptions[] = $e;
            }
        }
    }
}