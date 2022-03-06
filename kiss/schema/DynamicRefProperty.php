<?php
namespace kiss\schema;

use kiss\exception\ArgumentException;

/** Just like a regular reference but it has a function for the resolves */
class DynamicRefProperty extends RefProperty {

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

    /** @var string The name of the reference type */
    public $referenceName;

    /** The function to resovle the reference */
    public $referenceFunction = null;

    /**  Creates a new reference
     * @param string $reference name of the reference
     * @param function $function the function that resolves it.
    */
    public function __construct($reference, $function = null, $description = null, $properties = [])
    {
        parent::__construct(null, $properties);
        $this->description = $description;
        if (empty($reference) || !is_string($reference))
            throw new ArgumentException("{$reference} is not a string. If you mean to link an object, please use a normal RefProperty.");

        $this->referenceName = $reference;
        $this->referenceFunction = $function;
    }

    /** {@inheritdoc} */
    public function getReferenceClassName() {
        return $this->referenceName;
    }

    /** {@inheritdoc}  */
    public function getReferenceProperties($options = []) {
        if ($this->referenceFunction == null) return null;
        if (!is_callable($this->referenceFunction)) 
            return $this->referenceFunction;

        $func = $this->referenceFunction;
        return $func($this, $options);
    }
}