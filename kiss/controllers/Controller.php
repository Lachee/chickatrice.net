<?php
namespace kiss\controllers;

use Exception;
use kiss\exception\AggregateException;
use kiss\exception\HttpException;
use kiss\exception\MissingViewException;
use kiss\exception\UncaughtException;
use kiss\helpers\HTML;
use kiss\helpers\HTTP;
use kiss\helpers\Response;
use kiss\helpers\Strings;
use kiss\Kiss;
use kiss\models\Identity;
use kiss\router\Route;
use RuntimeException;
use Throwable;

class Controller extends Route {

    public const POS_HEAD = 0;
    public const POS_START = 1;
    public const POS_END = 2;

    public const DEPENDENCY_SCRIPT = 0;
    public const DEPENDENCY_STYLE = 1;

    /** @var Controller $current current controller */
    public static $current;

    private $js = [];
    private $deps = [];

    private $uncaughtException = null;

    /** Gets the route for the controller.
     * @return string 
     */
    protected static function route() {
        $class = get_called_class();

        $lastIndex = strrpos($class, "Controller");
        if ($lastIndex !== false) {
            $name = substr($class, 0, $lastIndex);
        }

        $parts = explode('\\', $name);
        $count = count($parts);
        $route = '';

        if (strtolower($parts[$count - 2]) == strtolower($parts[$count - 1]))
            $count -= 1;

        for ($i = 2; $i < $count; $i++) {
            if (empty($parts[$i])) continue;

            $lwr = strtolower($parts[$i]);
            $route .= '/' . $lwr;
            
        }
        return $route;
    }

    /**
     * Registers
     * @param string $name Name of the JS variable
     * @param mixed $value Value to store. This will be JSON encoded by default unless explicitly disabled
     * @param int $position Position of the register.
     * @param string $scope Scope of the variable. Supports `const`, `let`, `var`, or a custom object such as `Kiss` (which turns into `Kiss.set()`)
     * @param bool $encode encode the $value as JSON
     * @return void 
     */
    public function registerJsVariable($name, $value, $position = self::POS_START, $scope = 'const', $encode = true) {
        
        //Force the scope if we are setting to the kiss object.
        $name = str_replace(' ', '_', $name);         
        if ($scope == 'kiss') {
            $this->js[$position]["_$name"] = "kiss.set(".json_encode($name).", " . ($encode ? json_encode($value) : $value) . ");"; 
        } else {
            $scope = empty($scope) ? '' : ($scope == 'var' || $scope == 'let' || $scope == 'const' ? "$scope " : "$scope.");
            $this->js[$position]["_$name"] = "{$scope}{$name} = " . ($encode ? json_encode($value) : $value) . ";"; 
        }
    }

    /** Registers some javascript */
    public function registerJs($js, $position = self::POS_END, $key = null) {
        $key = $key ?? md5($js);
        $this->js[$position][$key] = $js;
    }

    /** Registers a JavaScript or CSS dependency 
     * @param string $source the source URL for the object
     * @param int $position position of the array 
     * @param int $type the type of the dependency. See DEPENDENCY_SCRIPT or DEPENDENCY_STYLE. 
     * If -1, then it will try to determien the best one from the given extension of source
     * @return $this
    */
    public function registerDependency($source, $position = self::POS_END, $type = -1) {

        if ($type === -1) {
            $extension = Strings::extension($source);
            if ($extension === '.js') $type = self::DEPENDENCY_SCRIPT;
            else if($extension === '.css') $type = self::DEPENDENCY_STYLE;
        }

        $item = [
            'source'    => $source,
            'type'      => $type
        ];

        if ($position == self::POS_END) {
            array_push($this->deps, $item);
        } else {
            array_unshift($this->deps, $item);
        }

        return $this;
    }


    //protected $headerFile = "@/views/base/header.php";
    //protected $contentFile = "@/views/base/content.php";
    //protected $footerFile = "@/views/base/footer.php";
    protected $templateFile = "@/views/base/page.php";
    protected $exceptionView = '@/views/base/error';

    /** Renders an exception */
    function renderException(HttpException $exception) {
        try {
            $content = $this->render($this->exceptionView, [ 'exception' => $exception ]);
            return Response::html($exception->getStatus(), $content);
        }catch(\Exception $e) {
            return Response::html(500, '<h1>Failed to render an exception!</h1>A critical error has occured, which means we are unable to render the exception page. <pre>' . $e->getMessage());
        }
    }

    /** Renders the page. */
    public function render($action, $options = []) {
        $options['_VIEW'] = $this->renderContent($action, $options);
        $options['_CONTROLLER'] = $this;

        //$html = '';
        //if (!empty($this->headerFile)) $html .= $this->renderFile($this->headerFile, $options);
        //if (!empty($this->contentFile)) $html .= $this->renderFile($this->contentFile, $options); else $html .= $options['_VIEW'];
        //if (!empty($this->footerFile)) $html .= $this->renderFile($this->footerFile, $options);
        //return $html;
        return $this->renderFile($this->templateFile, $options);
    }

    /** Renders only the content */
    public function renderContent($action, $options = []) {
        self::$current = $this;
        $options['_CONTROLLER'] = $this;
        $filepath = $this->getContentViewPath($action) . ".php";
        return $this->renderFile($filepath, $options);
    }

    /** Gets the route for the JS controller. This should not contain any parameters at all.
     * @return string 
    */
    public function getContentJSPath() {
        return substr($this->getContentViewPath('src'), strlen('@/views'));
    }

    /** @return string the path to the content */
    private function getContentViewPath($action) {
        $name = $class = get_called_class();
        $path = '';
        
        $lastIndex = strrpos($name, "Controller");
        if ($lastIndex !== false) { $name = substr($class, 0, $lastIndex); }
        $parts = explode('\\', $name);
        $count = count($parts);

        if (strtolower($parts[$count - 2]) == strtolower($parts[$count - 1]))
            $count -= 1;

        for ($i = 2; $i < $count; $i++) {
            if (empty($parts[$i])) continue;
            $lwr = strtolower($parts[$i]);
            if (!Strings::startsWith($lwr, ':'))  $path .= '/' . $lwr;
        }
        
        if (empty($path))
            $path = '/main';

        return  (strpos($action, "@") === 0 ? $action : "@/views" . $path . "/" . $action);
    }

    /** Renders all the current js variables */
    protected function renderJsVariables($position) {
        if (!isset($this->js[$position])) return '';

        $lines = [];
        foreach($this->js[$position] as $name => $def)
            $lines[] = $def;

        return '<script>' . join("\n", $lines) . '</script>';
    }

    /** Renders all the additional dependencies */
    protected function renderDependencies() {
        $lines = [];
        foreach($this->deps as $hash => $dep) {
            switch($dep['type']) {
                default:
                    throw new RuntimeException('Dependency of invalid type ' . $dep['type']);
                case self::DEPENDENCY_SCRIPT:
                    $lines[] = HTML::tag('script', '', [ 'src' => $dep['source'] ]);
                    break;
                case self::DEPENDENCY_STYLE:
                    $lines[] = HTML::tag('link', '', [ 'rel' => 'stylesheet', 'href' => $dep['source']]);
                    break;
            }
        }
        return join("\n", $lines);
    }

    //protected function setHeaderTemplate($file) { $this->headerFile = $file; return $this; }
    //protected function setContentTemplate($file) { $this->contentFile = $file; return $this; }

    /** Renders a single file */
    private function renderFile($file, $_params_ = array()) {
        if (strpos($file, '@') === 0) {
            $file = Kiss::$app->baseDir() . substr($file, 1);
        }

        $_obInitialLevel_ = ob_get_level();
        ob_start();
        ob_implicit_flush(false);
        extract($_params_, EXTR_OVERWRITE);
        try {
            //Make sure the file exists
            if (!file_exists($file)) 
                throw new MissingViewException($file);

            require $file;
            return ob_get_clean();
        } catch (\Exception $e) {
            while (ob_get_level() > $_obInitialLevel_) {
                if (!@ob_end_clean()) {
                    ob_clean();
                }
            }
            throw $e;
        } catch (\Throwable $e) {
            while (ob_get_level() > $_obInitialLevel_) {
                if (!@ob_end_clean()) {
                    ob_clean();
                }
            }
            throw $e;
        }
    }

    /** Performs the endpoint's action
     * @return Response|null Returns a response if a valid one is generated. Otherwise null.
     */
    public function action($endpoint, ...$args) {

        //Set hte error handling
        $this->uncaughtException = null;
        set_error_handler(function($errno, $errstr, $errfile, $errline) {
            if ( E_RECOVERABLE_ERROR === $errno ) {
                $this->uncaughtException = new UncaughtException($errno, $errstr, $errfile, $errline, $this->uncaughtException);
                return true;
            }
            return false;
        });

        //Attempt to get the event
        $action = $this->getAction($endpoint);
        if ($action === false) {
            throw new HttpException(HTTP::NOT_FOUND, 'Action not found');
        }

        //If this was a PHP file, perma redirect
        if (Strings::endsWith($endpoint, '.php'))
            return Response::redirect(HTTP::url(lcfirst(substr($action, strlen('action')))), HTTP::PERMANENTLY_REDIRECT);

        //verify our authentication
        $this->authenticate(Kiss::$app->user);

        //Define some JS actions
        $this->registerJsVariable("BASE", Kiss::$app->baseURL(), self::POS_HEAD, 'kiss');
        $this->registerJsVariable("ROUTE", get_called_class()::route(), self::POS_HEAD, 'kiss');
        $this->registerJsVariable("JS_PATH", $this->getContentJSPath(), self::POS_HEAD, 'kiss');
        $this->registerJsVariable("ACTION", $endpoint, self::POS_HEAD, 'kiss');
        $this->registerJsVariable("PARAMS", $this->export(), self::POS_HEAD, 'kiss');
        
        
        // Try to perform the action
        $response = null;
        try {
            $response = $this->{$action}(...$args);
        } catch(\Throwable $throwable) {
            if ($this->uncaughtException != null)
                return Response::exception(new AggregateException($throwable, $this->uncaughtException));
            return Response::exception($throwable);
        }

        //We didnt catch any errors, but we still have an exception to respond with.
        if ($this->uncaughtException != null) 
            return Response::exception($this->uncaughtException);

        return $response;
    }

    /** Renders the exception page*/
    public function actionException($exception) {
        return $this->renderException($exception);
    }

    /** Gets the action name */
    protected function getAction($endpoint) {
        $endpoint = ucfirst(Strings::toLowerCase($endpoint));
        $action = "action{$endpoint}";
        if (method_exists($this, $action))
            return $action;

        if (Strings::endsWith($endpoint, '.php'))
            return $this->getAction(substr($endpoint, 0,-4));

        return false;
    }

    /** Exports all the variables */
    protected function export() 
    {        
        $properties = self::getParameters();
        $exported = [];
        foreach($properties as $name) {
            $exported[$name] = $this->{$name};
        }
        return $exported;
    }

}