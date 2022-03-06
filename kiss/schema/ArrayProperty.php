<?php
namespace kiss\schema;


class ArrayProperty extends Property {

    /** {@inheritdoc} */
    public $type = 'array';

    /** {@inheritdoc} */
    public $format = 'table';

    /** @var Property Items in the array */
    public $items =  null;

    /** @var int|null max items in the array */
    public $maxItems = null;

    /** @var int|null min items in the array */
    public $minItems = null;

    /** {@inheritdoc}
     * @param Property|Property[] $items
     */
    public function __construct($items, $properties = [])
    {
        parent::__construct($properties);

        $this->items = $items;
        //if (!is_array($this->items))
        //    $this->items = $this->items;
    }

    /** @inheritdoc */
    public function validateValue($value)
    {
        if ($value == null) return parent::validateValue($value);
        if (!is_array($value)) {
            return "Expected an array.";
        }

        $count = count($value);
        if ($this->maxItems != null && $count > $this->maxItems) {
            return "Too many items. Expect {$this->maxItems} but got {$count}.";
        }

        if ($this->minItems != null && $count < $this->minItems) {
            return "Too few items. Expect {$this->minItems} but got {$count}.";
        }

        return parent::validateValue($value);
    }
}