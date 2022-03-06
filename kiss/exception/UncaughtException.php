<?php namespace kiss\exception;

use Exception;

class UncaughtException extends Exception {
    
    public $no;
    public $str;
    public $file;
    public $line;

    /** {@inheritdoc}
     */
    public function __construct($errno, $errstr, $errfile, $errline, \Throwable $previous = null) {
        parent::__construct($errstr, $errno, $previous);
        $this->no = $errno;
        $this->str = $errstr;
        $this->file = $errfile;
        $this->line = $errline;
    }
}