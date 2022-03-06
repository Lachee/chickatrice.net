<?php
namespace kiss\models\forms;

use JsonSerializable;
use kiss\db\ActiveRecord;
use kiss\exception\ArgumentException;
use kiss\exception\InvalidOperationException;
use kiss\helpers\HTML;
use kiss\helpers\HTTP;
use kiss\helpers\Strings;
use kiss\models\BaseObject;
use kiss\schema\ArrayProperty;
use kiss\schema\BooleanProperty;
use kiss\schema\EnumProperty;
use kiss\schema\IntegerProperty;
use kiss\schema\NumberProperty;
use kiss\schema\ObjectProperty;
use kiss\schema\Property;
use kiss\schema\RefProperty;
use kiss\schema\SchemaInterface;
use kiss\schema\StringProperty;

class Form extends BaseObject {
  
    /** Saves the form
     * @param bool $validate validates the record before submitting. Isn't required if loaded using Load
     * @return bool if successful 
     */
    public function save($validate = false) {

        //Failed to load
        if ($validate && !$this->validate()) {
            return false;
        }

        return true;
    }

    /** TODO: Implement this as base? */
    private function saveRecord($record, $validate) {
        $record->beforeLoad($record);
        $fields = [];

        //Load the properties into the object
        $schema = get_called_class()::getSchemaProperties(['serializer' => 'form']);
        foreach($schema as $property => $scheme) {
            if ($scheme->getProperty('readOnly', false) === true) continue;
            if (($err = $scheme->validateValue($this->{$property})) !== true) {
                $this->addError($err);
                return false;
            }

            //Load the property and store any errors
            $fields[] = $property;
            if (!$record->loadProperty($scheme, $property, $this->{$property})) {
                $this->addError($record->errors());
                return false;
            }
        }
        
        $record->afterLoad($record, true);
        
        //Save the object
        if ($record instanceof ActiveRecord) {
            if ($record->save(true, $fields)) {
                return true;
            } else {                    
                $this->addError($record->errors());
                return false;
            }
        }
        
        //Done
        return true;
    }

    /** Renders the form
     * @return string HTML form
     */
    public function render($options = []) {
        $schema = get_called_class()::getSchemaProperties(['serializer' => 'form']);
        $html = HTTP::CSRF();
        foreach($schema as $property => $scheme) {
            $html .= $this->renderScheme($property, $scheme, $options);
        }
        return $html;
    }

    /** Renders a scheme
     * @param string $name the property name
     * @param Property $scheme
     * @return string
    */
    protected function renderScheme($name, $scheme, $options = []) {
        if (!($scheme instanceof Property)) throw new ArgumentException('$scheme has to be a property');

        if (isset($scheme->options['hidden']) && $scheme->options['hidden'] == true)
            return '';

        $propertyType = $scheme->type;

        $renderer = "field{$name}";
        if (!method_exists($this, $renderer)) {
            $renderer = "input{$propertyType}";
            if (!method_exists($this, $renderer)) 
            {
                throw new ArgumentException('Form does not have a `input' . $propertyType . '()` renderer');
                return;
            }
        }

    
        
        $field = HTML::comment("'$name' input");
        $field .= HTML::begin('div', [ 'class' => 'field' ]); 
        {
            if (!($scheme instanceof BooleanProperty) && !empty($scheme->title))
                $field .= HTML::tag('label', $scheme->title, [ 'class' => 'label' ]);

            $field .= HTML::begin('div', ['class' => 'control']);
            {
                $field .= $this->{$renderer}($name, $scheme, $options);
            }
            $field .= HTML::end('div');

            if (!empty($scheme->description))
                $field .= HTML::tag('p', $scheme->description, [ 'class' => 'help' ]);
        
        }
        $field .= HTML::end('div');
        return $field;
    }

    /**
     * Renders a password field
     * @param string $name the property name
     * @param StringProperty $scheme
     * @param mixed $options 
     * @return string 
     * @throws ArgumentException 
     */
    protected function fieldPassword($name, $scheme, $options) {
        return HTML::input('password', [
            'class' => 'input',
            'name' => $name,
            'placeholder' => $scheme->default,
            'value' => $this->getProperty($name, ''),
            'disabled' => $scheme->getProperty('readOnly', false)
        ]);
    }

    /** Renders a text field
     * @param string $name the property name
     * @param StringProperty $scheme
     * @return string
    */
    protected function inputString($name, $scheme, $options = []) {
        return HTML::input('text', [ 
            'class'         => 'input',
            'name'          => $name,
            'placeholder'   => $scheme->default,
            'value'         => $this->getProperty($name, ''),
            'disabled'      => $scheme->getProperty('readOnly', false)
        ]);
    }
    
    /** Renders a text field
     * @param string $name the property name
     * @param StringProperty $scheme
     * @return string
    */
    protected function inputBoolean($name, $scheme, $options = []) {
        $options = [ 
            'name'          => $name,
            'content'       => $scheme->description,
            'disabled'      => $scheme->getProperty('readOnly', false)
        ];
        if ($this->getProperty($name, false)) $options['checked'] = true;

        $tag = HTML::input('checkbox', $options);
        return "<label class='label'>{$tag} {$scheme->title}</label>";
    }

    /** @inheritdoc */
    public function load($data = null)
    {
        // Validate the CSFR
        if (!HTTP::checkCSRF()) {
            $this->addError('Invalid CSFR. Your request may have been forged.');
            return false;
        }

        //send it
        if (!parent::load($data))
            return false;
            
        // Validate the load
        if (!$this->validate())
            return false;
        
        return true;
    }
}