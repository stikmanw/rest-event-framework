<?php
namespace Common\Exception;

/**
 * This exception should be thrown when a failure occurs when attempting to write a model
 * to it's respective persistance adapter.  This should occur internally only for Persitant adapters
 * and should be given context of what adapter and model fired the exception
 *
 * @see \Exception
 * @see Rope\Exception\AbstractException

 *
 */
class StorageException extends AbstractException
{
    /**
     * @var \Rope\Model\BaseModel
     */
    protected $model;

    /**
     * @var string
     */
    protected $adapterName;

    /**
     * @param $model
     * @param $adapterName
     * @return $this
     */
    public function setContext($model, $adapterName)
    {
        $this->adapterName = $adapterName;
        $this->model = $model;

        return $this;
    }
}