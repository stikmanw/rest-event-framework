<?php
namespace Common\Storage\Adapter;

use Common\Service\Instance\GenericInstanceFactory;
use ICanBoogie\Inflector as Inflector;

/**
 * Base Class for creating storage association between models and connectors.
 * Required Stub:
 *  _setup() - builds the class up makes connections and makes adapter type decisions
 *
 * @package Common\Storage\Adapter
 */
abstract class AbstractAdapter
{
    const DEFAULT_COLLECTION_CLASS = "\\Common\\Collection\\BaseCollection";

    /**
     * @var array
     */
    protected $settings;

    /**
     * @var array
     */
    protected $adapterType;

    /**
     * @var string
     */
    protected $modelName;

    /**
     * @var string
     */
    protected $metaModelName;

    /**
     * @var string
     */
    protected $collectionName;

    /**
     * @var \Common\Storage\Connection\AbstractConnection
     */
    protected $connection;

    /**
     * This is a stateful model->meta mapping see constructor options for details
     * @var boolean
     */
    protected $statefulMeta = true;

    /**
     * Create instance of model object inside the manager
     * @var GenericInstanceFactory
     */
    protected $modelFactory;

    /**
     * Create an instance of a collection object inside the manager
     * @var GenericInstanceFactory
     */
    protected $collectionFactory;

    /**
     * The model primary id name for unique identification
     * @var string
     */
    protected $idBaseName;

    /**
     * See Docs on settings options for building an adapter
     *
     * @param array $settings
     */
    public function __construct($settings)
    {
        $this->settings = $settings;

        foreach ($this->settings as $name => $value) {

            switch ((string)$name) {

                case 'modelFactory':
                    $this->setModelFactory($value);
                    break;

                case 'collectionFactory':
                    $this->setCollectionFactory($value);
                    break;

                case 'collectionName':
                    $this->setCollectionName($value);
                    break;

                case 'modelName':
                case 'metaModelName':
                case 'statefulMeta':
                case 'idBaseName':
                case 'metaIdBaseName':
                    $this->$name = $value;
                    break;

                default:
                    break;

            }
        }

        // assume the model meta follows the ModelMeta pattern
        if (empty($this->metaModelName)) {
            $this->metaModelName = $this->getModelName() . "Meta";
        }

        $this->_setup();
    }

    /**
     * @return GenericInstanceFactory
     */
    public function getModelFactory()
    {
        return $this->modelFactory;
    }



    /**
     * @param GenericInstanceFactory $factory
     */
    public function setModelFactory(GenericInstanceFactory $factory)
    {
        $this->modelFactory = $factory;
    }

    public function getCollectionFactory()
    {
        return $this->collectionFactory;
    }

    /**
     * Set the class factory that created collections in the system
     * @param GenericInstanceFactory $factory
     */
    public function setCollectionFactory(GenericInstanceFactory $factory)
    {
        $this->collectionFactory = $factory;
    }

    /**
     * Get the name of the base model
     *
     * @return string
     */
    public function getModelName()
    {
        return $this->modelName;
    }

    /**
     * @return string
     */
    public function getMetaModelName()
    {
        if (empty($this->metaModelName)) {
            $this->modelMetaName = $this->getModelName() . "Meta";
        }

        return $this->modelMetaName;
    }

    /**
     * Set the name of the collection used in collection based methods
     * @param $collectionName
     */
    public function setCollectionName($collectionName)
    {
        $this->collectionName = $collectionName;
    }

    /**
     * Get the collection name or lazy load the plural default collection
     * @return string
     */
    public function getCollectionName()
    {
        if (empty($this->collectionName)) {
            $modelName = $this->getModelName();

            if ($modelName) {
                $inflector = Inflector::get();
                $this->setCollectionName($inflector->pluralize($this->modelName));
            }

        }

        return $this->collectionName;
    }

    /**
     * Return the string name of the adapter that identifies the type of resource underneath
     * that write the data
     *
     * @example mysql, memory
     * @return string
     */
    public function getAdapterType()
    {
        return $this->adapterType;
    }

    /**
     * Get the stem for the id name
     * @return string
     */
    public function getIdBaseName()
    {
        if (empty($this->idBaseName)) {
            $this->idBaseName = lcfirst($this->getModelName());
        }
        return $this->idBaseName;
    }

    /**
     * Get the name of the model Id configured or make assumption it is the name of the model + Id
     * @return string
     */
    public function getModelIdName()
    {
        $idBase = $this->getIdBaseName();
        return $idBase . 'Id';
    }

    /**
     * @return string
     */
    public function getMetaIdName()
    {
        $idBase = $this->getIdBaseName();
        return $idBase . 'MetaId';
    }

    /**
     * Return a new instance of the collection
     * @return object
     */
    public function collectionInstance()
    {
        $factory = $this->getCollectionFactory();
        if (!$factory) {

            // use a default collection class to store the data
            $class = static::DEFAULT_COLLECTION_CLASS;
            return new $class;
        }

        return $factory->instance($this->getCollectionName());
    }

    /**
     * @deprecated
     */
    public function createCollectionInstance()
    {
        return $this->collectionInstance();
    }

    /**
     * @return \Common\Storage\Connection\AbstractConnection
     */
    public function getConnection()
    {
        return $this->connection;
    }

    /**
     * Each child class is required to implement a custom setup method that
     * handles implementing a connection to the storage system the adapter will use.
     */
    protected abstract function _setup();

}
