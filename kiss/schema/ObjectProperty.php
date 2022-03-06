<?php
namespace kiss\schema;

use ArrayObject;
use JsonSerializable;
use xve\configuration\Configurable;

class ObjectProperty extends Property {

    /** @var int Maximum depth of recursion allowed for properties. */
    public const MAX_DEFINITION_DEPTH = 10;

    /** {@inheritdoc} */
    public $type = 'object';

    /** @var Property[string] associative array of properties, where the keys are the name of the property. */
    public $properties = [];

    /** @var ObjectProperty[string] external definitions */
    public $definitions = null;

    /** @var bool this object is being used as a definition reference. */
    protected $isReference = false;

    /** @var string Class */
    protected $class = null;

    /** @var array default options for resolving */
    public $options = [];

    /** Creates a new ObjectPropert.
     * @param string $title Title name of the object
     * @param Property[]|SchemaInterface|string $properties either an array of Property or a class that implements SchemaInterface
     */
    public function __construct($title, $schemaProperties, $properties = [])
    {
        $this->title = $title;
        
        if ($schemaProperties instanceof Property) 
            $schemaProperties = [ $schemaProperties ];

        if (is_array($schemaProperties)) {
            //Manually defined the properties
            $this->properties = $schemaProperties;
            $this->class = null;
        } else {
            
            $this->class = $schemaProperties;

            //Check it matches and setup the properties
            if (in_array(SchemaInterface::class, class_implements($schemaProperties))) {
                $this->properties = $schemaProperties::getSchemaProperties();
            } else {
                $this->properties = [];
            }
        }

        parent::__construct($properties);
    }

    /** {@inheritdoc} */
    protected function init() {
        parent::init();
        
        //Do some additional resolutions.
        if (!$this->isReference) {
            $this->resolveReferences($this->options);
        }
    }

    /** Adds additional definitions. */
    public function addDefinitions($defs) {
        $this->definitions = array_merge($defs);
        return $this;
    }

    /** Update the properties to have correct results */
    public function updateDefaults() {
        if (empty($this->class)) return false;
        $defaults = get_class_vars($this->class);        
        foreach($this->properties as $title => $property) {
            if (isset($defaults[$title])) {
                
                //Prepare the property. If its an array, then its the items instead
                $p = $property instanceof ArrayProperty ? $property->items : $property;

                //We dont want ref properties
                if (!($p instanceof RefProperty)) {
                    $p->default = $defaults[$title];
                }
            }
        }

        return true;
    }

    /** Updates the validation checks  */
    public function updateValidation() {
        $this->required = [];
        foreach($this->properties as $title => $property) {
            if ($property->required) {
                $this->required[] = $title;
            }
        }

    }

    /** Updates the definitions to implement the resolutions.
     * @param array $options the options to send to the resolver
     * @param Property[string] $parentDefinitions existing definitions.
     * @param int $depth the maximum recusion allowed. When this number reaches 0 then it will exit early.
     */
    public function resolveReferences($options = [], $parentDefinitions = [], $depth = self::MAX_DEFINITION_DEPTH) {
        
        //We have reached the depth limit, so abort please.
        if ($depth <= 0) return false;

        $this->definitions = $parentDefinitions ?: [];
        foreach($this->properties as $name => $property) {
            $p = $property;

            //Breakout the array
            if ($property instanceof ArrayProperty) {
                $p = $property->items;
            }

            //Resolve reference
            if ($p instanceof RefProperty) {
                $name = $p->getReferenceClassName();
                if (!isset($this->definitions[$name])) {

                    $props = $p->getReferenceProperties($options);

                    //If this assert failed, you probably forgot to set some sort of option like xve configuration for a ValueType.
                    //assert($props != null, 'Reference Properties are not null');

                    if ($props !== null) {
                        if ($props instanceof Property) { 
                            $obj = $props;
                        } else {
                                
                            //Create a new object for this reference
                            $obj = new ObjectProperty($name, $props, [ 'isReference' => true ]);

                            //Resolve the references from that object and add them to our own.
                            $otherReferences = $obj->resolveReferences($options, $this->definitions, $depth - 1);
                            if ($otherReferences !== false) {
                                $this->definitions = array_merge($this->definitions, $otherReferences);
                            }
                            
                            //Clear out hte objects reference so when we add it, there isny cyclic.
                            $obj->definitions = [];                                                                
                        }
                    
                        //Assign us. Have to do this early so we dont get into a loop
                        $this->definitions[$name] = $obj;
                    } else {
                        //Its null D:
                        $this->definitions[$name] = [];
                    }
                }
            }
        }

        //We are done
        return $this->definitions;
    }

    function jsonSerialize() {
        if (!$this->isReference)
            $this->resolveReferences($this->options);

        $this->updateValidation();
        $this->updateDefaults();

        $properties = parent::jsonSerialize();
        unset($properties['class']);
        unset($properties['options']);
        return $properties;
    }

    
    /** @inheritdoc */
    public function parse($value) {
        if ($this->parser != null) 
            return call_user_func($this->parser, $value);
        
        /** We cannot parse objects */
        /** TODO: Write an object parser */
        return null;
    }
}