<?php
namespace kiss\schema;


class BooleanProperty extends Property {

    /** {@inheritdoc} */
    public $type = 'boolean';


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
        $val = $value === '' ? 'false' : $value;
        $result = filter_var($val, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
        if ($result === null){
            return "Expected a boolean.";
        }
        return parent::validateValue($value);
    }
}