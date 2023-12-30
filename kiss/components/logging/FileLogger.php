<?php  namespace kiss\components\logging;

use Chickatrice;
use kiss\helpers\HTTP;
use kiss\Kiss;
use kiss\models\BaseObject;

class FileLogger extends Logger {

    /** @var string name of the logger */
    public $name;

    /** @var bool include the stack trace in every log */
    public $includeStackTrace = false;

    protected function init() {
        parent::init();

        if ($this->name == null) 
            $this->name = 'Logger';
    }

    /**
     * Creates a trace log with the information required
     * @param string|Stringable $message the message to log
     * @param array $context context about the log
     * @return $this
     */
    public function trace($message, $context = []) {}

    /**
     * Creates a info log with the information required
     * @param string|Stringable $message the message to log
     * @param array $context context about the log
     * @return $this
     */
    public function info($message, $context = []) {}

    /**
     * Creates a warning log with the information required
     * @param string|Stringable $message the message to log
     * @param array $context context about the log
     * @return $this
     */
    public function warning($message, $context = []) {}

    /**
     * Creates a error log with the information required
     * @param string|Stringable|Exception $message the message or error to log
     * @param array $context creates the context
     * @return $this
     */
    public function error($message, $context = []) {}

    /**
     * Creates a new logger with the specific name
     * @param string $childName 
     * @return Logger the new logger
     */
    public function createChild($childName) {
	return new FileLogger(['name' => $childName]);
    }

    /**
     * Injects the request
     * @param mixed $context 
     * @return void 
     */
    protected function injectRequest(&$context) {
        $request = static::dumpRequest();
        $context['request'] = $request;
        return $request;
    }

    /**
     * Injects the stacktrace
     * @param array $context reference to the context array
     * @param int $traceBack how many traces to go back 
     * @return void 
     */
    protected function injectStackTrace(&$context, $traceBack) {
        if (!$this->includeStackTrace) 
            return null;
        
        $trace = debug_backtrace();
        if ($traceBack > 0) {
            $trace = array_splice($trace, $traceBack);
        }
        
        $context['_trace'] = $trace;
        return $trace;
    }

    
    /**
     * Creates an associative array of request information
     * Somethings such as POST will be excluded unless the enviroment is KISS_DEBUG for security reasons
     * @return void 
     */
    public static function dumpRequest() {
        $dump = [
            'host'  => HTTP::host(),
            'ip'    => HTTP::ip(),
            'ua'    => HTTP::userAgent(),
            'route' => HTTP::route(),
            '_GET'  => $_GET,
        ];

        //TODO: Get current controller

        if (Kiss::$app->user != null)
            $dump['user'] = [
                'id'        => Kiss::$app->user->id,
                'scopes'    => Kiss::$app->user->scopes(),
                'jwt'       => Kiss::$app->user->authorization()
            ];

        if (KISS_DEBUG) {
            $dump['_POST'] = $_POST;
        }

        return $dump;
    }
}
