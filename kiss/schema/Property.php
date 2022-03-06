<?php
namespace kiss\schema;

use JsonSerializable;
use kiss\models\BaseObject;

class Property extends BaseObject implements JsonSerializable {

    /** @var string type */
    public $type;

    /** @var string title */
    public $title = null;

    /** @var string description */
    public $description = null;

    /** @var mixed default value */
    public $default = null;

    /** @var string format for the schema */
    public $format = null;

    /** @var string reference */
    public $ref = null;

    /** @var array */
    public $options = [];

    /** @var bool is the field required. */
    public $required = true;

    /** @var bool the field is considered a read only. Not included in the schema. */
    public $readOnly = false;

    /** @inheritdoc */
    public function validate() { return true; }

    /** Validates the data
     * @param mixed $data the data to validate 
     * @var bool|string true if valid, otherwise it will return the error message. */
    public function validateValue($value) {
        if ($this->required && $value === null) 
            return ($this->title ?? ($this->description ?? $this->type)) . ' is required.';
        return true;
    }

    function jsonSerialize() {
        $props = $this->getProperties();
        if (!empty($this->ref)) { 
            $props['$ref'] = $this->ref; 
            unset($props['ref']); 

            return [ '$ref' => $this->ref ];
        }
        
        if ($this->options != null && (is_countable($this->options) && count($this->options) == 0)) {
            unset($props['options']);
        }

        if (is_bool($this->required))
            unset($props['required']);

        return $props;
    }

    /** {@inheritdoc} */
    public static function getSchemaProperties($options = [])
    {
        return [
            'type'          => new StringProperty(''),
            'title'         => new StringProperty(''),
            'description'   => new StringProperty(''),
            'default'       => new StringProperty(''),
            'format'        => new StringProperty(''),
            'ref'           => new StringProperty(''),
            'options'       => new ArrayProperty(''),
            'required'      => new BooleanProperty(''),
        ];
    }

    /** {@inheritdoc} */
    public static function getPropertyTypes()
    {
        return [
            'type'          => 'string',
            'title'         => 'string',
            'description'   => 'string',
            'default'       => 'string',
            'format'        => 'string',
            'ref'           => 'string',
            'options'       => 'array',
            'required'      => 'boolean',
        ];
    }
}