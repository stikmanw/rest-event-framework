<?php
namespace Common\Decorator;

class JsonObjectDecorator
{
    protected $object;

    /**
     * @var string
     */
    protected $dataGroup;

    /**
     * Custom json encode options
     * @var int
     */
    protected $jsonOptions = 0;

    public function setObject($object)
    {
        if(!is_object($object)) {
            throw new \InvalidArgumentException("Decorator class requires and object is:  ". gettype($object));
        }
        $this->object = $object;
    }

    /**
     * Prefix the output to a string group in json
     *
     * @example
     * $decorator->setDataGroup("data");
     * {
     *    "data": {
     *      // object output
     *    }
     * }
     * @param string $group
     */
    public function setDataGroup($group = "")
    {
        $this->dataGroup = $group;
    }

    /**
     * Set custom json options for rendering the output
     * @param int $options
     * @throws \InvalidArgumentException
     */
    public function setJsonOptions($options)
    {
        if(!is_int($options)) {
            throw new \InvalidArgumentException("JSON options must be set using an integer value");
        }
        $this->jsonOptions = $options;
    }

    public function getResult()
    {
        $json = json_encode($this->object, $this->jsonOptions);

        if($this->dataGroup) {
            $output = new \stdClass();
            $output->$group = $json;
        } else {
            $output = $json;
        }

        return $output;
    }

    public function __toString()
    {
        return $this->getResult();
    }
}