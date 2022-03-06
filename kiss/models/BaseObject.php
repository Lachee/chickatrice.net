<?php
namespace kiss\models;

use JsonSerializable;
use kiss\db\ActiveQuery;
use kiss\db\Query;
use kiss\exception\InvalidOperationException;
use kiss\helpers\Arrays;
use kiss\helpers\Strings;
use kiss\schema\ArrayProperty;
use kiss\schema\BooleanProperty;
use kiss\schema\EnumProperty;
use kiss\schema\IntegerProperty;
use kiss\schema\NumberProperty;
use kiss\schema\ObjectProperty;
use kiss\schema\RefProperty;
use kiss\schema\SchemaInterface;
use kiss\schema\StringProperty;

class BaseObject implements SchemaInterface, JsonSerializable {
    
    /** @property array[string] $defaults list of default settings for objects created using BaseObject::new. It is indexed by the class name.*/
    public static $defaults = [];

    /** @var string[] errors from validation */
    private $errors = null;

    /** Called after the constructor init the properties */
    protected function init() {}

    /** Before the load */
    protected function beforeLoad($data) {}

    /** After the load */
    protected function afterLoad($data, $success) {}

    /** Validates if this model is able to save. 
     * This method should use [[addError]] to record the reason the object is unable to save.
     * @return bool true if its validated.
     */
    public function validate() {
        $valid = true;
        $schema = get_called_class()::getSchemaProperties(['serializer' => 'form']);
        foreach($schema as $property => $scheme) {
            $value          = $this->{$property};
            $validation     = $scheme->validateValue($value);
            if ($validation !== true) {
                $this->addError($validation);
                $valid = false;
            }
        }
        return $valid;
    }


    /** Creates a new instance of the class */
    function __construct($properties = [])
    {
        //If its a function, execute it to get the properties back
        if (is_callable($properties)) 
            $properties = call_user_func($properties, $this);

        //Iterate over the properties
        foreach($properties as $key => $pair) {
            if (property_exists($this, $key)) {

                $type = get_called_class()::getPropertyType($key);
                if ($type == 'object') $type = BaseObject::class;
                
                //Set the value
                $this->{$key} = $pair;

                if (is_array($pair) && $type != 'array') {

                    //It is suppose to be an array
                    if (is_countable($pair) && (isset($pair[0]) || (isset($pair['$assoc']) && $pair['$assoc'] === true))) {

                        $this->{$key} = [];
                        foreach($pair as $i => $p) {
                            if (Strings::startsWith($i, '$')) continue;
                            if ($p instanceof BaseObject) {
                                $this->{$key}[$i] = $p;
                            } else {
                                if ($type == 'string' || $type == 'int' || $type == 'float' || $type == 'double' || $type == 'decimal' || $type == 'single' || $type == 'bool' || $type == 'boolean') {

                                    //We are just a static
                                    $this->{$key}[$i] = $p;

                                } else {

                                    //Validate the class
                                    $class = $p['$class'] ?? $type;
                                    if ($class != $type && !is_subclass_of($class, $type)) {
                                        throw new InvalidOperationException("{$key}'s class {$class} is not of type {$type}!");
                                    }

                                    //Append to the list
                                    $this->{$key}[$i] = $class == null ? $p : self::new($class, $p);
                                }
                            }
                        }

                    } else {
                    
                        //Validate the class
                        $class = $pair['$class'] ?? $type;
                        if ($class != $type && !is_subclass_of($class, $type)) {
                            throw new InvalidOperationException("{$key}'s class {$class} is not of type {$type}!");
                        }

                        if (is_subclass_of($class, BaseObject::class))
                            $this->{$key} = self::new($class, $pair);
                        else 
                            $this->{$key} = $pair;
                    }
                }
            }
        }

        //Init our properties
        $this->init();
    }

    public function __get($name) {
        if (is_callable(get_called_class() . "::get$name")) {
            $result = $this->{"get$name"}();
            if ($result instanceof ActiveQuery) {
                $all = $result->all();
                $limit = $result->getLimit();
                if ($limit == null || $limit[1] > 1) return $all;
                return $all[0] ?? null;
            }
            return $result;
        }

        if (stripos($name, '_') !== 0 && property_exists($this, $name))
            return $this->{$name};
    }

    public function __set($name, $value) {      
        if (is_callable(get_called_class() . "::set$name")) {            
            $this->{"set$name"}($value);
        } else  if (stripos($name, '_') !== 0 && property_exists($this, $name)) {
            $this->{$name} = $value;
        }         
    }

    /** Creates an object of the class.
     * If the properties has a $class, then it will validate that it extends it.
     * It will call initializationDefaults to setup the default settings.
     * @return $class the newly created object
    */
    public static function new($class, $properties = []) {
        
        //Evalulate the proeprties
        if (is_callable($properties)) 
            $properties = call_user_func($properties);
        
        //Get the class and check it
        $subclass = $properties['$class'] ?? $class;
        if ($class != $subclass && !is_subclass_of($class, BaseObject::class)) 
            throw new InvalidOperationException("Cannot create {$subclass} because its not a {$class}");

        //Apply the defaults if we have any
        if (empty(BaseObject::$defaults)) {
            $configuration = $properties;
        } else {
            $configuration = array_merge(
                                $subclass::initializationDefaults(),
                                $properties
                            );
        }
        
        //Set the class and return the new object
        //$properties['$class'] = $subclass;
        return new $subclass($configuration);
    }

    /** Recursively scans upwards the inheritence tree and builds an array of default values,
     * with the higher inheritence overriding below.
     * @return array array of default configurations.
     */
    public static function initializationDefaults() {
        $class = get_called_class();
        if ($class == BaseObject::class) return [];
        $parent = get_parent_class($class);
        if ($parent == false) return [];
        return array_merge($parent::initializationDefaults(), BaseObject::$defaults[$class] ?? []);
    }

    /** Creates a class, tries to get the class from the properties */
    public static function newObject($properties) {
        
        //Evaluate the properties
        if (is_callable($properties)) 
            $properties = call_user_func($properties);

        //Get the class and check it
        $class = $properties['$class'] ?? BaseObject::class;
        if ($class != BaseObject::class && !is_subclass_of($class, BaseObject::class))  
            throw new InvalidOperationException("Cannot create {$class} because its not a BaseObject");

        //Create a new object with the class
        return self::new($class, $properties);
    }

    /** Checks the object. If it is an array with $class set, it will be created */
    public static function initializeObject(&$obj) {
        if (is_subclass_of($obj, BaseObject::class)) return $obj;
        if (isset($obj['$class'])) return ($obj = self::newObject($obj));
        return $obj;
    }

    /** Loads the data into the object. Different to a regular construction because it bases the load of the schema properties.
     * @param array $data the data to read in.
     * @return bool if the read was succesful.
    */
    public function load($data = null) {
        if ($data == null) return false;

        //Convert the base object into an array
        if ($data instanceof BaseObject) {
            $data = $data->jsonSerialize();
        }

        $this->beforeLoad($data);
        $this->errors = null;
        $properties = get_called_class()::getSchemaProperties();
        foreach($properties as $property => $schema) {

            //Clean up missing properties
            if ($schema instanceof BooleanProperty && !isset($data[$property])) {
                $data[$property] = false;
            }

            //This field is required.
            if (!($schema instanceof ArrayProperty) && $schema->required && !isset($data[$property])) {
                $this->addError("{$property} is required");
                continue;
            }

            //Skip empty
            if (!isset($data[$property])) 
                continue;
                
            //Validate the individual property
            $this->loadProperty($schema, $property, $data[$property]);
        }

        //Run through a validation quickly
        //$this->validate();
        $success = $this->errors == null || count($this->errors) == 0;
        $return = $this->afterLoad($data, $success);
        if (is_bool($return)) return $return;
        return $success;
    }

    /** Loads individual properties
     * @param \kiss\schema\Property $schema
     */
    protected function loadProperty($schema, $property, $value, $append = false) {
        if (($err = $schema->validateValue($value)) !== true) {
            $this->addError("$property: $err");
            return false;
        }

        if ($schema instanceof ArrayProperty) {
            //Iterate over every item and load them
            $this->{$property} = [];
            foreach($value as $val) {
                $this->loadProperty($schema->items, $property, $val, true);
            }

        } else {
            $result = null;

            if ($schema instanceof EnumProperty) {
                $result = $value;
                if ($schema->assoc) {               
                    //If we are an integer enum, then upate the key
                    if ($schema->type == 'integer') { 
                        $result = intval($value);
                    }
                }
            } else  if ($schema instanceof ObjectProperty) {
                $this->addError("{$property} cannot parse ObjectProperty");
                return;
            } else {
                $result = $schema->parse($value);
            }

            //Set the properties value. If we are an array then append.
            if ($append) { 
                $this->{$property}[] = $result;
            } else {
                $this->{$property} = $result;
            }
        }

        return true;
    }

    /** Adds an error */
    protected function addError($error) { 
        if ($this->errors == null) $this->errors = [];
        if (is_array($error)) { 
            foreach($error as $e) $this->errors[] = $e;
        } else {
            $this->errors[] = $error;
        }
        return $this;
    }

    /** @return string[] errors that have been generated */
    public function errors() { return $this->errors ?: []; }

    /** @return string summary of all errors. */
    public function errorSummary() { return join('. ', $this->errors()); }

    /** Gets the name of the current class */
    public function className() {
        return get_called_class();
    }
   
    /** Tries to get the property. If it doesnt exist then $default will be returned
     * @param string $name name of the property
     * @param mixed $default the default return value
     * @return mixed the properties value, otherwise the default
     */
    public function getProperty($name, $default = null) {
        return Arrays::value($this, $name, $default);
    }

    /**
     * Get all the properties of the object
     * @param bool $skipNull skip null values.
     * @return array
     */
    public function getProperties($skipNull = true) {
        $all_properties = get_object_vars($this);
        $properties = [];

        foreach($all_properties as $full_name => $value) {
            if ($skipNull && $value == null) continue;
            $full_name_components = explode("\0", $full_name);
            $property_name = array_pop($full_name_components);
            if ($property_name && isset($value)) 
                $properties[$property_name] = $value;
        }

        return $properties;
    }

    /** @return array the default properties */
    public static function getPropertyDefaults() {        
        $class = get_called_class();
        return get_class_vars($class);
    }

    /** Gets the type of the property */
    public static function getPropertyType($property, $schema = null) {
        $schema = $schema ?: get_called_class()::getSchemaProperties();
        if (isset($schema[$property])) {
            
            $p = $schema[$property];
            if ($p instanceof ArrayProperty) {
                $p = $p->items;
                if ($p == null || $p == '') return 'array';
            } 

            if ($p instanceof RefProperty) {
                return $p->getReferenceClassName();
            }

            return $p->type;
        } else {
            $defaults = get_called_class()::getPropertyDefaults();
            if (isset($defaults[$property])){ 
                if (is_string($defaults[$property])) return 'string';
                if (is_float($defaults[$property])) return 'float';
                if (is_integer($defaults[$property])) return 'integer';
                if (is_bool($defaults[$property])) return 'boolean';
                return 'object';
            }
        }

        return null;
    }

    /** @return array returns the type of Configurable children the object has. */
    public static function getPropertyTypes() { 
        $class = get_called_class();
        $schema = $class::getSchemaProperties();
        $types = [];

        foreach($schema as $field => $property) {
            $type = self::getPropertyType($field, $schema);
            if ($type != null) $types[$field] = $type;
        }

        return $types;
    }


    /** Gets the entire schema and resolves the definitions.
     * @return ObjectProperty 
     */
    public static function getJsonSchema($options = []) {
     
        //Prepare the title
        $class  = get_called_class();
        $index  = strrpos($class, '\\');
        $title  = substr($class, $index+1);

        //Get the schema
        $schema = new ObjectProperty($title, $class, [
            'options' => $options,
            'class' => $class
        ]);     

        //Add the definitions
        if (isset($options['definitions']))
            $schema->addDefinitions($options['definitions']);

        //Return the schema
        return $schema;
    }

    /** Gets the schema of an object's properties 
     * @return Property[] Associative array of properties*/
    public static function getSchemaProperties($options = []) {
        
        $variables    =  self::getPropertyDefaults();
        $properties = [];

        foreach($variables as $name => $value) {
            $resp = self::getValueSchemaProperty($value);
            if ($resp !== false) $properties[$name] = $resp;
        }

        return $properties;
    }

    /** Gets the default schema value for the given value. */
    private static function getValueSchemaProperty($value) {
        if ($value === null) return false;            
        
        if (is_float($value)) { 
            return new NumberProperty(null, $value); 
        }
        else if (is_numeric($value)) { 
            return new IntegerProperty(null, $value); 
        }
        else if (is_string($value)) { 
            return new StringProperty(null, $value); 
        } 
        else if (is_array($value)) {
            $first = reset($value); 
            return self::getValueSchemaProperty($first);
        }
        else if (is_object($value)) {
            //Get the object's class and make sure its an SchemaInterface
            $valueClass = get_class($value);
            if (in_array(SchemaInterface::class, class_implements($valueClass))) {
                return new RefProperty($valueClass);
            }
        } 
        
        return false;
    }

    /** {@inheritdoc} */
    function jsonSerialize() {
        //Only serializing what is available in the schema properties
        // This is really basic. Probably should do a more indepth version but meh
        // If we have sub BaseObject, they will get called themselves and will exclude the shit
        $properties = [];
        $schema = get_called_class()::getSchemaProperties([ 'serializer' => 'json' ]);
        foreach($schema as $name => $property) {
            $properties[$name] = $this->__get($name);
        }
        return $properties;
    }
}