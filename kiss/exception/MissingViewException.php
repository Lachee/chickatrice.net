<?php namespace kiss\exception;

class MissingViewException extends \Exception {
    public $view;
    public function __construct($view, $message = null, $code = 0)
    {
        parent::__construct($message ?: 'Cannot find view ', $code);
        $this->view = $view;
    }
}