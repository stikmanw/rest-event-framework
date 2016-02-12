<?php
namespace Common\Storage;

/**
 * Manager for starting a class that will handle multiple adapter save types...
 * Use Adapters directly instead of using this class if you only intend to run
 */
use Common\Exception\StorageException;
use Common\Injector;
use Common\Model\BaseModel;
use Common\Collection\BaseCollection;
use Common\Service\Instance\Exception\ClassNotFoundException;
use Common\Service\Instance\GenericInstanceFactory;
use Common\Service\Instance\MultiInstanceFactory;
use Common\Tool\Introspection;

class Manager {

    const BASE_ADAPTER_NAME = "Common";
    const MYSQL_ADAPTER = "mysql";

    /**
     * Configuration for this storage manager.
     *
     * @var \Common\Storage\Configuration
     */
    protected $config;

    /**
     * List of storage adapter currently configured for this object manager
     *
     * @var AbstractAdapter []
     */
    protected $storageAdapters = array();

    /**
     * Create a new Model Manager with the configuration applied that allows writing to multiple persistent
     * engines.  The Configuration required most declare the model scope you it is currently operating in.
     * This class wraps the individual adapters and reads from the adapter set to be highest priority.
     *
     * @param Configuration $Config
     */
    public function __construct(Configuration $Config)
    {
        $this->config = $Config;

        // set all the adapters that have been passed in
        $adapters = $this->config->getAdapters();

        if(empty($adapters)) {
            throw new \InvalidArgumentException("Missing adapters setting (array) to for manager see supported adapters");
        }

        foreach($adapters as $adapter) {
            $settings = $this->config->getAdapterSettings($adapter);
            $this->addAdapter($adapter, $settings, $this->config->getNamespaces());
        }

    }

    /**
     * Get the configuration object that was originally passed into the manager.
     * @return Configuration
     */
    public function getConfig()
    {
        return $this->config;
    }

    /**
     * Caller to get the name of the model
     * @return string
     */
    public function getModelName()
    {
        return $this->getConfig()->modelName;
    }

    /**
     * Get the list of storage adapters currently configured in the instance
     *
     * @return AdapterBase []
     */
    public function getAdapters()
    {
        return $this->storageAdapters;
    }

    /**
     * get a list of suported adapter types based on the directory structure in Adapter folder
     *
     * @example array('mysql', 'memcached', 'memory')
     * @return array
     */
    public static function getSupportedAdapters()
    {
        $adapterStorageDir = dirname(__FILE__) . "/Adapter/";
        $adapter = glob($adapterStorageDir . "*", GLOB_ONLYDIR | GLOB_NOSORT);
        array_walk($adapter, function(&$value) {
            $value = basename($value);
        });

        return $adapter;
    }

    /**
     * This will either lazy load a multi injector or find the specific adapter factory on the class and allow
     * the user to override the factory class loader to their app structure.
     *
     * @param $type
     * @return GenericInstanceFactory|MultiInstanceFactory
     */
    public function getAdapterFactory($type)
    {
        $adapterFactory = $this->config->getAdapterFactory();
        if($adapterFactory instanceof GenericInstanceFactory)
        {
            return $adapterFactory;
        }

        // if they have used the namespaces option in the config load up the namespace chain
        $namespaces = $this->getConfig()->getNamespaces();
        if(is_array($namespaces) && !empty($namespaces)) {
            $adapterFactory = MultiInstanceFactory::createFromNamespaceList($namespaces, array());
        } else {

            $namespace = Introspection::getNamespaceBase(get_class($this));

            $type = ucwords($type);
            $modelClass = $namespace . "\\Storage\\Adapter\\{$type}";
            $adapterFactory = new MultiInstanceFactory($modelClass);
        }

        return $adapterFactory;
    }

    /**
     * This is used to fetch the dep injector on the adapter factory if we do not have one then we will
     * create one for you and assume the only deps are the standard settings. Yes, sir we are creating
     * super flexibility at the cost of some complexity in code....Suck it up and learn some shit.
     *
     * @param GenericInstanceFactory $adapterFactory
     * @param $settings
     */
    public function getInjector(GenericInstanceFactory $adapterFactory, $settings)
    {
        $injector = $adapterFactory->getInjector();
        if(!$injector) {
            $injector = new Injector(array("settings" => $settings));
        } else {
            $injector->updateScope("settings", $settings);
        }

        return $injector;
    }

    /**
     * When adding an adapter we need to know a few specific pieces of input
     * We need to know what the adapter type is (mysql,memory,memcache to name a few)
     * We also need to know if the particular model has its own extended class to use instead
     * of the common adapter type.  This allows models to have flexibility per adapter without
     * every model needing to implement/use that flexibility.
     *
     * @param $type
     * @param $settings
     * @param array $namespaceArray
     * @throws \Common\Exception\StorageException
     */
    public function addAdapter($type, $settings)
    {
        // get our adapter instance creator if we do not have one we will create one
        $factory = $this->getAdapterFactory($type);
        $adapter = null;

        /*
         *  get the injector for our factory and load it into the adapter factory
         *  we inject our settings into a container that will be auto-mapped to dependencies at run time.
         *  if you are confused by this...well too bad read the non-existent docs.
         */
        $injector = $this->getInjector($factory, $settings);
        $factory->setInjector($injector);

        // we need to catch a class not found exception so we can roll back and
        try {
            $adapter = $factory->instance($this->getModelName());
        } catch(ClassNotFoundException $e) {
            // this is not really an error we just know the class did not exist ...maybe it should not be an exception??
        }

        // load the common adapter for the namespace specified by the adapter factory
        if(!$adapter) {

            // try again with the common adapter for the class...since we are using the factory it could live anywhere...
            try {
                $adapter = $factory->instance(self::BASE_ADAPTER_NAME);
            } catch(ClassNotFoundException $e) {
                throw new StorageException("Could not find adapter specific class for " . $this->getModelName()
                    . " check adapter factory or add common adapter for default handler."
                );
            }
        }

        return array_push($this->storageAdapters, $adapter);
    }

    /**
     * Target a specific adapter in the list of adapters throw exception if the adapter targeted
     * was not found
     *
     * @param string $type type of adapter to find
     * @return AbstractAdapter
     * @throws \Common\Exception\StorageException
     */
    public function adapter($type)
    {

        foreach($this->storageAdapters as $instance) {
            $type = ucwords($type);
            $lookup = "\\{$type}\\";

            if(stripos(get_class($instance), $lookup) !== false) {
                return $instance;
            }
        }

        throw new StorageException("$type adapter could not be found as registered on the manager");
    }

    /**
     * This will write the model + meta (if meta exists) to the storage observers
     * on this manager.  If an existing model check needs to be done before the write on
     * new records the existingColumns variable can be used to specify a lookup before
     * write.  The default behavior is to assume your model is set with the data that
     * needs to be wrote and the adapters will handle how that model is actually persisted.
     *
     *
     * @param BaseModel $Model
     * @param array $existingColumns
     * @param bool $skipLookup
     * @return BaseModel
     * @throws \InvalidArgumentException
     */
    public function write(BaseModel $Model, $existingColumns = array(), $skipLookup = false)
    {
        // validate thar our model passed aligns with what
        $modelFactory = $this->getConfig()->getModelFactory();
        $modelFactory->willCreate($Model);

        // validate the model
        if (!$this->validateModel($Model)) {
            throw new \InvalidArgumentException("Manager configure modelName: {$this->getModelName()} different then model object type basename.");
        }

        foreach ($this->storageAdapters as $Adapter) {
            $Model = $Adapter->write($Model, $existingColumns, $skipLookup);
        }

        return $Model;
    }

    /**
     * Allow us to save a list of our models if they are in our base collection form. Additionally
     * each adapter can declare their own method for handling a batch of models. If that method
     * has not been implemented it will simply iterate through the collection and apply the standard
     * write function individually.
     *
     * @param BaseCollection $Collection
     */
    public function writeBatch(BaseCollection $Collection)
    {
        foreach($this->storageAdapters as $Adapter) {
            if(method_exists($Adapter, "writeBatch")) {
                $Adapter->writeBatch($Collection);
            } else {
                foreach($Collection as $Item) {
                    $this->write($Item);
                }
            }
        }
    }


    public function appendBatch(BaseCollection $Collection)
    {
        foreach($this->storageAdapters as $Adapter) {
            if(method_exists($Adapter, "appendBatch")) {
                $Adapter->appendBatch($Collection);
            } else {
                throw new StorageException(get_class($Adapter) . ' has not implemented appendBatch');
            }
        }
    }

    /**
     * @param BaseModel $Model
     * @todo build functionality
     */
    public function delete(BaseModel $Model)
    {
        foreach($this->storageAdapters as $Adapter) {
            $Adapter->delete($Model);
        }
    }

    /**
     * This will delete a batch of records from the adapter
     *
     * @example $mgr->deleteBatch($serviceableModel, array('ropeId' => '12323123123232');  // delete all records+meta where ropeID is set
     * @param BaseModel $Model
     * @param $criteria
     */
    public function deleteBatch(BaseModel $Model, $criteria)
    {
        foreach($this->storageAdapters as $Adapter) {
            $Adapter->deleteBatch($Model, $criteria);
        }
    }

    /**
     * Accessor method for running a find in the findAll mode.
     *
     * @param $criteria
     * @param bool $or
     * @param null $adapterType
     * @return mixed
     */
    public function findAll($criteria, $or = false, $adapterType = null) {
        return $this->_find($criteria, 'findAll', $or, $adapterType);
    }

    /**
     * Accessor method for running a find in the findOne mode.
     *
     * @param $criteria
     * @param bool $or
     * @param null $adapterType
     * @return mixed
     */
    public function findOne($criteria, $or = false, $adapterType = null) {
        return $this->_find($criteria, 'findOne', $or, $adapterType);
    }

    /**
     * Find a model by the id
     * @param $id
     * @return mixed
     */
    public function findOneById() {
        foreach($this->storageAdapters as $adapter) {
            if(is_callable(array($adapter, "findOneById"))) {
                $args = func_get_args();

                if(empty($args)) {
                    throw new \InvalidArgumentException("findOneById expects at least one id");
                }

                return call_user_func_array(array($adapter, "findOneById"), $args);
            }
        }
    }

    /**
     * Validate a model being operated on fits the context settings of the class so we do not get into any
     * undesirable states.
     *
     * @param $model
     * return boolean
     */
    public function validateModel($model)
    {
        $modelName = $this->getConfig()->modelName;

        if(Introspection::getClassName($model) !== $modelName) {
            return false;
        }

        $modelFactory = $this->getConfig()->getModelFactory();
        return $modelFactory->willCreate($model);
    }


    /**
     * Take a set of criteria which is a list of model properties and lookup an existing model in the system.
     * When doing a lookup it will use the fastest adapter that has the result.  In the future the model
     * may be populated by multiple adapters taking on their piece.
     *
     * @param $criteria
     * @param $findMethod
     * @param bool $or
     * @param null $adapterType
     * @return mixed
     */
    private function _find($criteria, $findMethod, $or = false, $adapterType = null)
    {
        // for now just loop through the adapters in their priority order
        foreach($this->storageAdapters as $Adapter) {

            // if adapterType is not specified, we run the first adapter
            // otherwise, we only run the specific adapter requested and skip the rest
            if($adapterType === null || $Adapter->getAdapterType() === $adapterType) {

                $Result = $Adapter->$findMethod($criteria, $or);
                if($Result) {
                    return $Result;
                }

            }
        }

    }

}
