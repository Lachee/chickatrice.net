<?php
namespace kiss\widget;

use kiss\exception\InvalidOperationException;
use kiss\models\BaseObject;

class Widget extends BaseObject {

    /** Stack of in-progress widgets */
    protected static $stack = [];

    /** Adds the start tags for the widget */
    public function begin() {}

    /** Adds the end tags for the widget */
    public function end() {}

    /** echos out the instance immediately */
    public function run() { 
        $this->begin();
        $this->end();
    }

    /** Creates a new wiget instance. */
    public static function widget($options = []) {
        $class = get_called_class();
        $obj = BaseObject::new($class, $options);
        $obj->run();
        return '';
    }

    /** Creates a new widget instance, pushes it to a stack and invokes only its begin() */
    public static function widgetBegin($options = []) {
        $class = get_called_class();
        $obj = BaseObject::new($class, $options);
        $class::$stack[] = $obj;
        $obj->begin();
        return '';
    }

    /** Ends a widget from the stack */
    public static function widgetEnd() {
        $class = get_called_class();
        if (empty($class::$stack)) 
            throw new InvalidOperationException('endWidget was called before a startWidget');

        $obj = array_pop($class::$stack);
        $obj->end();
        return '';
    }
}