<?php
namespace kiss\schema;


class IntegerProperty extends Property {

    /** {@inheritdoc} */
    public $type = 'integer';


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
        if ($value == null) return parent::validateValue($value);
        $result = filter_var($value, FILTER_VALIDATE_INT, FILTER_NULL_ON_FAILURE);
        if ($result == null){
            return "Expected a integer.";
        }
        return parent::validateValue($value);
    }

    /** @inheritdoc */
    public function parse($value) {
        if ($this->parser != null) 
            return call_user_func($this->parser, $value);
    
        $result = filter_var($value, FILTER_VALIDATE_INT, FILTER_NULL_ON_FAILURE);
        if ($result == null) return null;
        return intval($result);
    }
}