<?php
namespace kiss\schema;


class BooleanProperty extends Property {

    /** {@inheritdoc} */
    public $type = 'boolean';

    /** @var Callable $validate validate function that determines if a value is true or false */
    public $validate;

    /** {@inheritdoc} */
    public function __construct($description, $default = null, $properties = [])
    {
        parent::__construct($properties);
        $this->description = $description;
        $this->default = $default;
    }
    
    /** @inheritdoc */
    public function validateValue($value)
    {
        if ($value == null) 
            return parent::validateValue($value);

        if ($this->validate) {
            $result = call_user_func($this->validate, $value);
        } else {
            $val = $value === '' ? 'false' : $value;
            $result = filter_var($val, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
            if ($result === null){
                return "Expected a boolean.";
            }
        }
        return parent::validateValue($value);
    }

    /** @inheritdoc */
    public function parse($value) {
        if ($this->parser != null) 
            return call_user_func($this->parser, $value);
            
        $val = $value === '' ? 'false' : $value;
        $result = filter_var($val, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
        if ($result === null) return null;
        return boolval($result);
    }
}