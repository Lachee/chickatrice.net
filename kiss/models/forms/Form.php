<?php
namespace kiss\models\forms;

use JsonSerializable;
use kiss\db\ActiveRecord;
use kiss\exception\ArgumentException;
use kiss\exception\InvalidOperationException;
use kiss\helpers\Arrays;
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
  
    /** @var string form name to help distringuish against multiple forms */
    public $formName = null;

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

        $inputName = $name;
        if (!empty($this->formName))
            $inputName = $this->formName . "[$inputName]";
        
        $field = HTML::comment("'$name' input");
        $field .= HTML::begin('div', [ 'class' => 'field' ]); 
        {
            if (!($scheme instanceof BooleanProperty) && !empty($scheme->title))
                $field .= HTML::tag('label', $scheme->title, [ 'class' => 'label' ]);

            $field .= HTML::begin('div', ['class' => 'control']);
            {
                $field .= $this->{$renderer}($inputName, $scheme, $options);
            }
            $field .= HTML::end('div');

            if (!empty($scheme->description))
                $field .= HTML::tag('p', $scheme->description, [ 'class' => 'help' ]);
        
        }
        $field .= HTML::end('div');
        return $field;
    }

    /** Renders a text field
     * @param string $name the property name
     * @param StringProperty $scheme
     * @return string
    */
    protected function inputString($name, $scheme, $options = []) {
        $type = 'text';
        if (Arrays::value($scheme->options, 'password', false))
            $type = 'password';

        return HTML::input($type, [ 
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

    /**
     * @param mixed $name 
     * @param EnumProperty $scheme 
     * @param array $options 
     * @return void 
     * @throws ArgumentException 
     */
    protected function inputEnum($name, $scheme, $options = []) {
        // Current selection
        $selected   = $this->getProperty($name, '');

        // select optiosn
        $options    = [ 'name' => $name,  ];
        if ($scheme->getProperty('readOnly', false))
            $options['disabled'] = true;

        // Draw
        $html = '';
        $html .= HTML::begin('span', ['class' => 'select is-fullwidth']);
        {
            $html .= HTML::begin('select', $options);
            {
                $labels = false;
                if (isset($scheme->options['enum_titles']) && is_array($scheme->options['enum_titles']))
                    $labels = $scheme->options['enum_titles'];

                foreach($scheme->enum as $i => $key) {
                    $label = $labels !== false ? $labels[$i] : $key;
                    $optionOptions = [ 'value' => $key ];
                    if ($key === $selected) $optionOptions['selected'] = 'true';
                    $html .= HTML::tag('option', $label, $optionOptions);
                }
            }
            $html .= HTML::end('select');
        }
        $html .= HTML::end('span');
        $html .= HTML::tag('script', "$('[name=\"{$name}\"]').select2();");
        return $html;
    }

    /** @inheritdoc */
    public function load($data = null)
    {
        // Validate the CSFR
        if (!HTTP::checkCSRF()) {
            $this->addError('Invalid CSFR. Your request may have been forged.');
            return false;
        }

        $subdata = $data;
        if (!empty($this->formName))
            $subdata = Arrays::value($data, $this->formName, null);

        if ($subdata == null)
            return false;

        //send it
        if (!parent::load($subdata))
            return false;
            
        // Validate the load
        if (!$this->validate())
            return false;
        
        return true;
    }
}