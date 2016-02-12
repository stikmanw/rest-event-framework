<?php
namespace Common\Event;

/**
 * This event represents the request state or model state in crud controller implementations.
 */
use Common\Storage\Manager;
use Symfony\Component\EventDispatcher\Event;
use Symfony\Component\HttpFoundation\Request;

class CrudEvent extends Event
{
    /**
     * Storage Adapter that will be used to get data state from persistent source
     * @var Common\Storage\Manager
     */
    private $manager;

    /**
     * Access to the request object, you should not modify the request object in anyway. It's bad
     * news, and a golden teddy bear will jump out and stuff you into animatronic suit if you
     * perform such a feat.
     * @var \Symfony\Component\HttpFoundation\Request
     */
    private $request;

    /**
     * Construct model from either the request or the result of the model write
     * to persistent source.
     * @var Common\Model\BaseModel;
     */
    private $model;

    /**
     * ID for get,put,delete operations
     * @var int
     */
    private $id;

    /**
     * Flag to prevent data from being deleted.
     * @var boolean
     */
    private $allowDelete = true;

    /**
     * @param Request $request
     * @param Manager $manager
     */
    public function __construct(Request $request, Manager $manager)
    {
        $this->request = $request;
        $this->manager = $manager;
    }

    /**
     * @param $id
     */
    public function setId($id)
    {
        $this->id = $id;
    }

    /**
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @param $model
     */
    public function setModel($model)
    {
        $this->model = $model;
    }

    /**
     * @return Common\Model\BaseModel
     */
    public function getModel()
    {
        return $this->model;
    }

    /**
     * Get the original request passed into the method
     * @return Request
     */
    public function getRequest()
    {
        return $this->request;
    }

    /**
     * Get the manager this operation will use to save/retrieve the data
     * @return Common\Storage\Manager|Manager
     */
    public function getManager()
    {
        return $this->manager;
    }

    /**
     * refuse to let this delete controller delete on this request
     */
    public function refuseDeletion()
    {
        $this->allowDelete = false;
    }

    /**
     * @return bool
     */
    public function canDelete()
    {
        return $this->allowDelete;
    }

}