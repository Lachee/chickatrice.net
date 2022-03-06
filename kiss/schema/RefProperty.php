<?php
namespace kiss\schema;

use JsonSerializable;
use kiss\exception\ArgumentException;

class RefProperty extends Property {

    /** {@inheritdoc} */
    public $type = null;
    /** {@inheritdoc} */
    public $title = null;
    /** {@inheritdoc} */
    public $description = null;
    /** {@inheritdoc} */
    public $default = null;
    /** {@inheritdoc} */
    public $format = null;

    /** @var string|SchemaInterface The referenced class name. */
    public $reference;

    public function __construct($reference, $description = null, $properties = [])
    {
        parent::__construct($properties);
        $this->description = $description;
        $this->reference = $reference;
    }

    /** @return string Referenced name */
    public function getReferenceClassName() {
        if (is_string($this->reference)) return $this->reference;
        return get_class($this->reference);
    }

    /** Gets the schema properties for the referenced type. 
     * @return Property[string] referenced properties
    */
    public function getReferenceProperties($options = []) {
        $reference = $this->getReferenceClassName();

        if (empty($reference) || !in_array(SchemaInterface::class, class_implements($reference)))
            throw new ArgumentException("{$reference} does not implement SchemaInterface.");

        $opts           = $options;
        $opts['ref']    = $this;
        $opts['depth']  = ($options['depth'] ?? 0) + 1;
        return $reference::getSchemaProperties($opts);
    }

    /** {@inheritdoc} */
    function jsonSerialize() {
        return [ '$ref' => '#/definitions/' . $this->getReferenceClassName() ];
    }

        
    /** @inheritdoc */
    public function parse($value) {
        if ($this->parser != null) 
            return call_user_func($this->parser, $value);
        
        // apple: 10, mango: 30, orange: 3
        $class = $this->getReferenceClassName();
        if (empty($class)) {
            return null;
        }

        if (!method_exists($class, "load")) {
            if (is_subclass_of($class, BaseObject::class)) {
                $result = new $class($value);
            } else {
                return null;
            }
        } else {
            $result = new $class();
            $result->load($value);
        }

        return $result;
    }
}