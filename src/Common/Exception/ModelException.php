<?php
namespace Common\Exception;

/**
 * This exception is for use within model functionality to identify which model was
 * affected.
 *
 */
class ModelException extends AbstractException
{
    /**
     * @var string
     */
    public $modelName;

    /**
     * @param $name
     * @return $this
     */
    public function setModel($name)
    {
        $this->modelName = $name;
        return $this;
    }

}