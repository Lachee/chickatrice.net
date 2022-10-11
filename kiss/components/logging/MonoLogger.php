<?php namespace kiss\components\logging;

use kiss\helpers\HTTP;
use kiss\models\BaseObject;
use \Monolog\Level as MLevel;
use \Monolog\Logger as MLogger;

class MonoLogger extends Logger {
    
    /** @var MLogger */
    private $_logger;

    public $handlers = [ ];

    public function createChild($childName) { 
        $logger = BaseObject::new(MonoLogger::class, [ 
            'name'      => $childName,
            'handlers'  => $this->handlers
        ]);
        return $logger;
    }

    /**
     * TODO: IMplement this
     * Using processors
     * The second way is to add extra data for all records by using a processor. Processors can be any callable. They will get the record as parameter and must return it after having eventually changed the extra part of it. Let's write a processor adding some dummy data in the record:
     * 
     * <?php
     * 
     * $logger->pushProcessor(function ($record) {
     *     $record->extra['dummy'] = 'Hello world!';
     * 
     *     return $record;
     * });
     *  
     */


    public function trace($message, $context = []) {
        $this->injectStackTrace($context, 2);
        $this->_logger->debug($message, $context);
        return $this;
    }

    public function info($message, $context = []) {
        $this->injectStackTrace($context, 2);
        $this->_logger->info($message, $context);
        return $this;
     }

    public function warning($message, $context = []) {
        $this->injectStackTrace($context, 2);
        $this->_logger->warning($message, $context);
        return $this;
    }

    public function error($message, $context = []) { 
        $this->injectStackTrace($context, 2);
        $this->_logger->error($message, $context);
        return $this;
    }

    protected function init()
    {
        parent::init();

        // Create teh logger
        $this->_logger = new MLogger($this->name);

        // Setup default handlers
        if ($this->handlers == null || count($this->handlers)) {
            $this->handlers = [
                new \Monolog\Handler\StreamHandler('log/error.log', MLogger::ERROR),
                new \Monolog\Handler\StreamHandler('log/default.log', MLogger::DEBUG),
            ];
        }

        $this->_logger->pushProcessor(new \Monolog\Processor\WebProcessor());
        $this->_logger->pushProcessor(new \Monolog\Processor\UidProcessor(16));

        foreach($this->handlers as $handler) {
            $this->_logger->pushHandler($handler);
        }
    }
}