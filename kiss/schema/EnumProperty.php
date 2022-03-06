<?php
namespace kiss\schema;


class EnumProperty extends StringProperty {

    /** {@inheritdoc} */
    public $type = 'string';

    /** {@inheritdoc} */
    public $format = 'selectize';

    /** @var Property[] Items in the array */
    public $enum =  [];

    /** @var bool is it an associative */
    public $assoc = false;

    /** {@inheritdoc}
     * @param Property|Property[] $items
     */
    public function __construct($description, $enum, $default = null, $properties = [])
    {
        parent::__construct($description, $default, $properties);
        
        $this->enum = $enum;
        if (!is_array($this->enum)) $this->enum = [$this->enum];
        
        //Convert to enums and titles
        if (self::associative($this->enum) || isset($properties['assoc'])) {
            $enum = $this->enum;
            $this->enum = array_keys($enum);
            $this->options['enum_titles'] = array_values($enum);
        }
    }

    public static function associative(array $arr)
    {
        if (array() === $arr) return false;
        return array_keys($arr) !== range(0, count($arr) - 1);
    }

    /** @inheritdoc */
    public function validateValue($value)
    {
        if ($value == null) return parent::validateValue($value);
        if ($this->assoc) {
            foreach($this->enum as $index => $enumValue) {
                if ($index == $value) {
                    return parent::validateValue($value);
                }
            }
        } else {
            foreach($this->enum as $index => $enumValue) {
                if ($enumValue == $value) {
                    return parent::validateValue($value);
                }
            }
        }
        
        return "Value is not within the enum";
    }
}