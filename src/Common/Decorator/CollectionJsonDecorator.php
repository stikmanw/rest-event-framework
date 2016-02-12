<?php
namespace Common\Decorator;

/**
 * Throwing up all over collections for reelz.
 */
use Common\Model\BaseModel;

class CollectionJsonDecorator extends ObjectDecorator
{
    /**
     * @var Common\Model\BaseCollection
     */
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

    /**
     * For adding additional information to the json response on the fly
     *
     * @example array("debug", array("filepath" => ...");
     * @var array()
     */
    protected $extraInfo = null;

    /**
     * @param BaseCollection $object
     * @throws \InvalidArgumentException
     */
    public function setObject($object)
    {
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
        if (!is_int($options)) {
            throw new \InvalidArgumentException("JSON options must be set using an integer value");
        }
        $this->jsonOptions = $options;
    }

    /**
     * For setting specific information to be prepended to the results
     * best used with dataGroup setting
     * @param array $info
     */
    public function setExtraInfo(array $info)
    {
        $this->extraInfo = $info;
    }

    /**
     * Return the json formatted string based on decorator settings
     * @return mixed|string
     */
    public function getResult()
    {
        $json = (array)$this->object->toJsonEncodeable();

        if ($this->dataGroup) {
            $output = new \stdClass();
            $group = $this->dataGroup;

            if (!empty($this->extraInfo) && is_array($this->extraInfo)) {
                foreach ($this->extraInfo as $key => $value) {
                    if ($key === $group) {
                        continue;
                    }

                    $output->$key = $value;
                }
            }
            $output->$group = $json;

        } else {
            if (!empty($this->extraInfo) && is_array($this->extraInfo)) {
                $output = new \stdClass();
                foreach ($this->extraInfo as $key => $value) {
                    if ($key === "body") {
                        continue;
                    }
                    $output->$key = $value;
                }
                $output->body = $json;
            } else {
                $output = $json;
            }
        }

        return json_encode($output, $this->jsonOptions);
    }

}