<?php
namespace kiss\schema;


class StringProperty extends Property {

    /** {@inheritdoc} */
    public $type = 'string';


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
        if (!is_string($value)) return "Expected a string.";
        if (empty($value) && $this->required) return parent::validateValue(null);
        return parent::validateValue($value);
    }
}