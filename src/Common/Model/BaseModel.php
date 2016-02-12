<?php
namespace Common\Model;

/**
 * Base model of all things order service/wrapper.
 *
 * This model and all of it's children are designed to NOT mirror database
 * structure on purpose. They are also designed to be very json friendly.
 * This means you will see all properties camel cased with a lowercase first
 * letter. Any properties that are arrays should end in array (eg: addressArray),
 * all models should end in model (eg: customerModel), and all collections should
 * end in collection (eg: productCollection).
 *
 * Any mapping to and from other object should be handled by a Mapper object
 * named the same as the model and residing in the Mappers folder.
 *
 */
use Common\Exception\ModelException;
use Common\Tool\Introspection;

class BaseModel
{
    /**
     * yyyy-mm-dd
     * @var string
     */
    public $dateAdded;

    /**
     * yyyy-mm-dd hh:ii:ss
     * @var string
     */
    public $dateTimeAdded;

    /**
     * @var string
     */
    public $lastUpdated;

    /**
     * Type of the model for internal transformation to originating class
     * @var string
     */
    public $___type;

    /**
     * This is jacked in here for now. This goes against naming conventions but we do what me must
     * to make bricks fit into nail holes.
     * This is a special property that represents the self referencing api call to retrieve the results of this resource
     *
     * @var string
     */
    public $_self;

    /**
     * @var boolean
     */
    private $hasHash = false;

    /**
     * has meta data attached to this model
     * @var boolean
     */
    private $hasMeta = false;

    /**
     * Create and instance of our model.
     *
     * Anything passed into $inputData that is an array or object will attempt
     * to populate the new model with $existingOnly set to true. This allows
     * single method creation or models with data from things like json_decode'd
     * arrays and objects.
     *
     * @param array|object $inputData
     * @param null $metaModel
     */
    public function __construct($inputData = null, $metaModel = null)
    {
        if(method_exists($this, "beforeCreate")) {
            $this->beforeCreate();
        }

        if (is_array($inputData) || is_object($inputData)) {
            $this->populate($inputData, true);
        }

        if($this instanceof HashableModel) {
            $this->hasHash = true;
        }

        if(method_exists($this, "getMeta")) {
            $this->hasMeta = true;
        }

        if($this->hasMeta && $metaModel) {
            $this->setMeta($metaModel);
        }

        if(method_exists($this, "afterCreate")) {
            $this->afterCreate();
        }


    }

    /**
     * Validate our model and make sure everything seems peachy keen.
     *
     * This should throw exceptions if things are not valid.
     *
     * @throws ValidationException
     * @return void
     */
    public function validate()
    {
        return;
    }

    /**
     * Populate the current instance with the provided input data.
     *
     * @param array|object $input
     * @param bool $existingOnly Whether to only populate existing properties or all.
     * @param bool $noOverwrite Whether to overwrite existing values or only nulls/empties.
     * @return \RVLibraries\Order\Models\BaseModel
     * @throws \Exception
     */
    public function populate($input, $existingOnly = true, $noOverwrite = false)
    {
        // Allow objects to be populated
        if (is_object($input)) {
            if (method_exists($input, "to_array")) {
                $input = $input->to_array();
            } elseif (method_exists($input, "toArray")) {
                $input = $input->toArray();
            } else {
                $input = get_object_vars($input);
            }
        }
        
        // check to make sure an array was passed in
        if (is_array($input)) {
            // Populate Data
            foreach ($input as $key => $value) {

                // Processing for FLAG_IF_EXISTS
                if ($existingOnly === true && !property_exists($this, $key)) {
                    continue;
                }

                // Processing for FLAG_NO_OVERWRITE
                if ($noOverwrite === true && !empty($this->$key)) {
                    continue;
                }

                if($value === null || is_scalar($value)) {
                    $this->$key = $value;

                } else {
                    $this->autoPopulateObject($key, $value);
                }

            }
        } else {

            throw new \Exception(
                "No array could be derived from passed in data"
            );

        }

        if(method_exists($this, "validate")) {
            $this->validate();
        }

        if(method_exists($this, "afterPopulate")) {
            $this->afterPopulate();
        }

        // Method Chaining
        return $this;
    }

    /**
     * Only populate new values set in the passed object values that are not set will not overwrite existing values,
     * for that functionality use base populate for exact copy.
     *
     * @param \Traversable $object
     * @return $this
     */
    public function populateDelta($object)
    {

        foreach($object as $member => $value) {

            if(!property_exists($this, $member)) {
                continue;
            }

            if($value === null) {
                continue;
            } elseif(is_scalar($value)) {
                $this->$member = $value;
            } elseif(is_array($value) || $value instanceof \ArrayObject) {
                $this->$member = $value;
            } else {
                $this->recursiveModelUpdate($member, $value);
            }

        }

        if(method_exists($this, "validate")) {
            $this->validate();
        }

        if(method_exists($this, "afterPopulate")) {
            $this->afterPopulate();
        }

        // Method Chaining
        return $this;
    }

    /**
     * This will iterate through recursively an update models internally expecting the ___type
     * field to be set. This is used in delta write where we want to maintain the existing
     * model in its current state and deep update nested objects.
     *
     * @param $key
     * @param $value
     */
    public function recursiveModelUpdate($key, $value)
    {
         // if we have the same object type then we only need to populate our model differences and move on.
        if(isset($value->___type) && (get_class($this->$key) === $value->___type)) {
            return $this->$key->populateDelta($value);
        }

        // otherwise we are going to use the normal populate that does all that magic
        $this->autoPopulateObject($key, $value);
    }

    public function populateFromDBRecord($record)
    {
        if(is_array($record)) {
            $record = (object)$record;
        }

        $record = Introspection::camelizeMembers($record);
        $this->populate($record);
    }

    public function autoPopulateObject($key, $value)
    {
        $methodKey = ucwords($key);

        // if the model has explictly set it's own populate method on the key the honor it
        if(method_exists($this, "populate{$methodKey}")) {
            $method = "populate{$methodKey}";
            $this->$method($value);

        // if the model already has a collection assigned to it than use the collection and populate it
        } elseif ($this->$key instanceof \Common\Collection\BaseCollection) {
            $this->$key->populate($value);
        } else {

            $varType = Introspection::getVarType($this, $key);
            if(!class_exists($varType)) {
                $this->$key = $value;
                return;
            }

            if(is_object($value) && isset($value->___type)) {
                $varType = new $value->___type;
            }
            $newClass = new $varType();

            // if the varType and the expected value match we do not need to do any special mapping
            if(is_object($value) && get_class($newClass) === get_class($value)) {
                $this->$key = $value;
                return;
            }

            if( $newClass instanceof \Common\Collection\BaseCollection ) {

                // if the value is an object at this point it would have been a collection
                if(is_object($value)) {
                    $value = (array)$value;
                }

                $this->$key = new $varType();

                $item = null;
                if(count($value)) {
                    $item = current($value);
                    if(!empty($item->___type)) {
                            $this->$key->setModelName($item->___type);
                            $this->$key->populate($value);
                    } else {
                        $this->$key = new $varType($value);
                    }
                }

                return;
            }

            if( $newClass instanceOf \Common\Model\BaseModel ) {
                $this->$key = $newClass->populate($value);
                return;
            }
        }

    }

    /**
     * Convert out model to json.
     *
     * @var int $options json_encode options
     * @return string
     */
    public function toJson($options = 0)
    {
        $obj = $this->toJsonEncodeable();
        return json_encode($obj, $options);
    }

    /**
     * Convert out object to a standard array.
     *
     * @return array
     */
    public function toArray()
    {
        return Introspection::getPublicVars($this);
    }

    public function toJsonEncodeable() {

        $dataArray = $this->toArray();
        $dataArray['___type'] = get_class($this);
        unset($dataArray['hasMeta']);
        unset($dataArray['hasHash']);
        unset($dataArray['reservedHash']);

        if(!isset($dataArray['_self'])) {
            unset($dataArray['_self']);
        }

        array_walk(
            $dataArray,
             function(&$value, $key) use (&$dataArray) {

                if(is_object($value) && $value instanceof \Common\Model\BaseModel) {
                    $value = $value->toJsonEncodeable();
                }

                if(is_object($value) && $value instanceof \Common\Collection\BaseCollection) {
                    $value = (array)$value;
                    foreach($value as $index => $instance) {
                        if($instance instanceof \Common\Model\BaseModel || $instance instanceof \Common\Collection\BaseCollection) {
                            $value[$index] = $instance->toJsonEncodeable();
                        }
                    }
                }
        });

        return $dataArray;
    }

    /**
     * Convert our model to an array with a type specified.
     *
     * @return array
     */
    public function toTypedArray()
    {
        $dataArray = $this->toArray();

        if(!isset($dataArray['___type'])) {
            $dataArray['___type'] = get_class($this);
        }

        array_walk(
            $dataArray,
            function (&$value, $key) {
                if (is_object($value) && $value instanceof \Common\Model\BaseModel) {
                    $value = $value->toTypedArray();
                }

                if(is_object($value) && $value instanceof \Common\Collection\BaseCollection) {
                    foreach($value as $index => $instance) {
                        if(method_exists($instance, "toTypedArray")) {
                            $value[$index] = $instance->toTypedArray();
                        }
                    }
                }
            }
        );
        return $dataArray;
    }

    /**
     * Create an instance of this class with a raw non type json string
     *
     * @param string $inputString
     * @param null $jsonOptions
     * @return static
     */
    public static function fromJson($inputString, $jsonOptions = null)
    {
        $data = json_decode($inputString);

        $instance = new static();

        if(!empty($data)) {
            $instance->populate($data);
        }

        return $instance;
    }

    public function populateFromJson($inputString, $jsonOptions = null)
    {
        $data = json_decode($inputString, $jsonOptions);
        if(!empty($data)) {
            $this->populate($data);
        } else {
           throw ModelException::create("JSON input string was empty could not populate model.")->setModel(get_class($this));
        }

        return $this;
    }

    /**
     * Create a new model instance from a generic model that has the ___type set on the object and child objects.
     *
     * @param \stdClass $object
     * @return mixed
     * @throws \InvalidArgumentException
     */
    public static function instanceFromTypedObject(\stdClass $object)
    {
        if(!isset($object->___type)) {
            throw new \InvalidArgumentException("object passed in must be declared with ___type argument");
        }

        $modelName = $object->___type;
        $Model = new $modelName();
        $Model->populate($object);
        return $Model;
    }

    /**
     * Create a new empty model
     *
     * @return BaseModel
     */
    public static function createNew()
    {
        return new static();
    }

    /**
     * has meta object
     *
     * @var boolean
     * @return bool
     */
    public function hasMeta()
    {
        return $this->hasMeta;
    }

    /**
     * @return boolean
     */
    public function hasHash()
    {
        return $this->hasHash;
    }

}
