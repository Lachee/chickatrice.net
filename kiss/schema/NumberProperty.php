<?php
namespace kiss\schema;


class NumberProperty extends Property {

    /** {@inheritdoc} */
    public $type = 'number';


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
        $result = filter_var($value, FILTER_VALIDATE_FLOAT, FILTER_NULL_ON_FAILURE);
        if ($result == null){
            return "Expected a float.";
        }
        return parent::validateValue($value);
    }
}