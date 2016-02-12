<?php
namespace Common\Decorator;

/**
 * Decorators is such a lame name for a pattern... I would like to slap the comp sci PHD that came
 * up with it.  Forcing me to have to use that term in sentences around the office. I vote for data
 * fluffers in the future.
 */
use Common\Model\BaseModel;
class ModelJsonDecorator extends ObjectDecorator
{
    /**
     * @var \Common\Model\BaseModel
     */
    protected $object;

    /**
     * @var string
     */
    protected $dataGroup;

    /**
     * Show empty values on the return model or leave off the response.
     * @var boolean
     */
    protected $showEmpty = true;

    /**
     * Custom json encode options
     * @var int
     */
    protected $jsonOptions = 0;

    /**
     * Show type of the model when encoding
     * @var bool
     */
    protected $showType = true;

    /**
     * For adding additional information to the json response on the fly
     *
     * @example array("debug", array("filepath" => ...");
     * @var array()
     */
    protected $extraInfo = null;

    /**
     * @param BaseModel $object
     * @throws \InvalidArgumentException
     */
    public function setObject(BaseModel $object)
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
     * Change the output to not show empty fields in the result.
     * @param bool $empty
     */
    public function showEmpty($empty = true)
    {
        $this->showEmpty = $empty;
    }

    /**
     * Show / hide the object type in the result
     * @param bool $type
     */
    public function showType($type = true)
    {
        $this->showType = $type;
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
        $json = $this->object->toJsonEncodeable();

        if(! $this->showEmpty) {
            $json = $this->removeEmpty($json);
        }

        if(! $this->showType) {
            $json = $this->removeType($json);
        }

        if($this->dataGroup) {
            $output = new \stdClass();
            $group = $this->dataGroup;

            if(!empty($this->extraInfo) && is_array($this->extraInfo)) {
                foreach($this->extraInfo as $key => $value) {
                    if($key === $group) {
                        continue;
                    }

                    $output->$key = $value;
                }
            }
            $output->$group = $json;

        } else {
            if(!empty($this->extraInfo) && is_array($this->extraInfo)) {
                $output = new \stdClass();
                foreach($this->extraInfo as $key => $value) {
                    if($key === "body") {
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

    /**
     * Remove empty elements of an array so they are not used in the jsonEncode
     * @param $array
     * @return mixed
     */
    protected function removeEmpty($array)
    {
        foreach ($array as $key => $value) {
            if (is_array($value)) {
                $array[$key] = $this->removeEmpty($array[$key]);
            }

            if (empty($array[$key])) {
                unset($array[$key]);
            }
        }

        return $array;
    }

    /**
     * Remove empty elements of an array so they are not used in the jsonEncode
     * @param $array
     * @return mixed
     */
    protected function removeType($array)
    {
        foreach ($array as $key => $value) {
            if (is_array($value)) {
                $array[$key] = $this->removeEmpty($array[$key]);
            }

            if ($key === "___type") {
                unset($array[$key]);
            }
        }

        return $array;
    }
}