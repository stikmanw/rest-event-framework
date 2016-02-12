<?php
namespace Common\Collection;

/**
 * Added methods to populate from common data types
 */
class BaseCollection extends \ArrayObject
{
    protected $modelName;

    public function __construct(Array $values = array(), $flags = 0, $iteratorClass = "ArrayIterator")
    {
        foreach($values as $index => $value) {
            $values[$index] = $this->applyModel($value);
        }
        parent::__construct($values, $flags, $iteratorClass);
    }

    public function append($value)
    {
        $Object = $this->applyModel($value);
        parent::append($Object);
    }

    public function populate($values)
    {
        foreach((array)$values as $index => $value) {
            $this->append($value);
        }
    }

    /**
     * clears out the contents of the current array objects
     *
     * @return void
     */
    public function emptyCollection()
    {
        $this->exchangeArray(array());
    }

    /**
     * Manually set the name of the model this collection should represent.
     *
     * @todo add model validation of the name as being a valid integration
     * model namespace or rope namespace.
     * @param string
     */
    public function setModelName($name)
    {
        $this->modelName = $name;
    }

    /**
     * Return the specific name of the model that should be represented by this collection
     *
     * @return mixed | string
     */
    public function getModelName()
    {
        return $this->modelName;
    }

    /**
     * Apply the model set in the collection if it has been set.
     *
     * @return mixed | \Rope\BaseModel\Model
     */
    public function applyModel($value)
    {
        $Class = $this->getModelName();

        // if the model is already in the correct format then use the model set
        if(class_exists($Class)) {
            $Object = new $Class();
            $Object->populate($value);
            return $Object;
        } else {
            return $value;
        }

    }

    public function isEmpty()
    {
        return ! (bool)$this->count();
    }

    public function toJson()
    {
        $result = $this->toJsonEncodeable();
        return json_encode((array)$result);
    }

    public function toJsonEncodeable()
    {
        $object = clone $this;

        $iterator = $object->getIterator();

        while ($iterator->valid()) {
            $current = $iterator->current();

            if(is_object($current)) {
                $current = clone $current;
            }

            if (method_exists($current, 'toTypedArray')) {
                $iterator->offsetSet($iterator->key(), $current->toTypedArray());
            } elseif (method_exists($current, 'to_typed_array')) {
                $iterator->offsetSet($iterator->key(), $current->to_typed_array());
            } elseif (is_object($current)) {
                $iterator->offsetSet($iterator->key(), get_object_vars($current));
            }

            $iterator->next();
        }

        return $object;
    }

    public static function fromTypedArray($Items)
    {
        $Collection = new static();

        foreach ($Items as $Item) {
            if (isset($Item['___type'])) {

                if (!class_exists($Item['___type'])) {
                    throw new \UnexpectedValueException('Class does not exist for type' . $Item['___type']);
                }

                $modelName = $Item['___type'];
                $Model = $modelName::fromTypedArray($Item);
                $Collection->append($Model);
            }
        }

        return $Collection;
    }

    public static function fromJson($jsonTyped)
    {
        $data = json_decode($jsonTyped, true);
        return static::fromTypedArray($data);
    }

    /**
     * Find an object containing a key with a set scalar value.
     *
     *
     * @todo add ability to lookup path using array
     *
     * @warning this is not very performant in large nested models
     * @param string $key
     * @param mixed $value
     * @return mixed
     */
    public function findObjectByKey($key, $value)
    {
        $iterator = $this->getIterator();
        while($iterator->valid()) {
            $arrayLookup = $iterator->current()->toTypedArray();

            foreach(new \RecursiveIteratorIterator(new \RecursiveArrayIterator($arrayLookup), \RecursiveIteratorIterator::SELF_FIRST) as $member=>$current) {
                if (is_scalar($current) && $member == $key && $current == $value) {
                    return $iterator->current();
                }
            }

            $iterator->next();

        }

    }

    /**
     * Extract a list of values from a collection of objects
     * @param string $member name of the public member on the object to extract the value of
     * @return array
     */
    public function extractValue($member)
    {
        $extracted = array();

        foreach($this as $object)
        {
            if(isset($object->$member)) {
                $extracted[] = $object->$member;
            }
        }

        return $extracted;
    }

    /**
     * Merge another colleciton into this collection assuming they are of the same type
     *
     * @param \Common\Collection\BaseCollection $Collection
     * @throws
     */
    public function merge(BaseCollection $Collection)
    {
        $class = get_class($this);
        $newClass = get_class($Collection);
        if($class !== $newClass) {
            throw \UnexpectedValueException('You can only merge classes of the same type. Expected collection of type ' . $class . ' and received ' . $newClass);
        }

        foreach($Collection as $Item) {
            $this->append($Item);
        }
    }
}
