<?php
namespace Common\Controller;

use Common\Event\CrudEvent;
use Common\Event\Definition\CrudEvents;
use Common\Event\SearchEvent;
use Common\Collection\BaseCollection;
use Common\Provider\MysqlManagerProvider;
use Common\Service\Instance\GenericInstanceFactory;
use Common\Service\Instance\MultiInstanceFactory;
use Common\Storage\Manager;
use Common\Tool\MysqlUtility;
use Silex\Application;
use Silex\ControllerCollection;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Yaml\Exception\RuntimeException;

class MysqlCrudController extends AbstractController
{
    /**
     * @var Manager
     */
    protected $mysqlManager;

    /**
     * @var EventDispatcherInterface
     */
    protected $eventDispatcher;

    /**
     * @var GenericInstanceFactory
     */
    protected $modelFactory;

    /**
     * @var GenericInstanceFactory
     */
    protected $adapterFactory;

    /**
     * Database to wire the manager up to
     * @var string
     */
    protected $database;

    /**
     * Set the event dispatcher on the method request. For sending events during the handling
     * of the request inside the controller.
     * @param \Symfony\Component\EventDispatcher\EventDispatcherInterface $dispatcher
     * @internal param $EventDispatcherInterface
     */
    public function setDispatcher(EventDispatcherInterface $dispatcher)
    {
        $this->eventDispatcher = $dispatcher;
    }

    /**
     * Get the event dispatcher for this class
     * @return EventDispatcher|EventDispatcherInterface
     */
    public function getDispatcher()
    {
        if (empty($this->eventDispatcher)) {
            $this->eventDispatcher = new EventDispatcher();
        }

        return $this->eventDispatcher;
    }

    /**
     * @param GenericInstanceFactory $factory
     */
    public function setModelFactory(GenericInstanceFactory $factory)
    {
        $this->modelFactory = $factory;
    }

    /**
     * @throws \RuntimeException
     * @return GenericInstanceFactory
     */
    public function getModelFactory()
    {
        if($this->modelFactory) {
            return $this->modelFactory;
        }

        // use a default based on application settings
        if(isset($this->app['namespace.model'])) {
            return new GenericInstanceFactory($this->app['namespace.model']);
        }

        throw new \RuntimeException("could not find a model factory on the controller");
    }

    /**
     * @param GenericInstanceFactory $factory
     */
    public function setAdapterFactory(GenericInstanceFactory $factory)
    {
        $this->adapterFactory = $factory;
    }

    /**
     * @return mixed
     */
    public function getAdapterFactory()
    {
        if($this->adapterFactory) {
            return $this->adapterFactory;
        }

        $factory = null;
        // use a default based on application settings
        if($this->app['namespace.mysql.adapter']) {
            $factory = new MultiInstanceFactory($this->app['namespace.mysql.adapter']);
            $factory->pushNamespace("Common\\Storage\\Adapter\\Mysql");
            return $factory;
        } else {
            return new GenericInstanceFactory("Common\\Storage\\Adapter\\Mysql");
        }
    }

    /**
     * @param $database
     */
    public function setDatabase($database)
    {
        $this->database = $database;
    }

    /**
     * @return string
     * @throws \RuntimeException
     */
    public function getDatabase()
    {
        if(isset($this->database)) {
            return $this->database;
        }

        if($this->app['mysql.database']) {
            return $this->app['mysql.database'];
        }

        throw new \RuntimeException("a default database was not specified in application configuration");
    }

    /**
     * Setup the basic crud endpoints for this controller
     * @param ControllerCollection $controller
     * @return ControllerCollection
     */
    public function addCrudOperations(ControllerCollection $controller)
    {
        $controller->get("/search", array($this, "search"));
        $controller->get("/{id}", array($this, "get"));
        $controller->post("/", array($this, "create"));
        $controller->put("/{id}", array($this, "update"));
        $controller->delete("/{id}", array($this, "delete"));

        return $controller;
    }

    /**
     * Set the mysql manager that will be used in the built in methods
     * @param Manager $manager
     */
    public function setMysqlManager(Manager $manager)
    {
        $this->mysqlManager = $manager;
    }

    /**
     * Get the storage manager that will communicate to the database
     * @return Manager
     * @throws \RuntimeException
     */
    public function getMysqlManager()
    {
        if (empty($this->mysqlManager)) {
            throw new \RuntimeException("mysqlManager options must be set in order to utilize the crudController abstract");
        }

        return $this->mysqlManager;
    }

    /**
     * Search for looking up a collection records based on the passed in query params
     * current search only supports existing columns equals/wildcard searches.
     * @param Request $request
     * @return array|mixed
     */
    public function search(Request $request)
    {
        $query = $request->query->all();
        $newQuery = array_map(
            function ($value) {
                return MysqlUtility::convertWildcardSearch($value);
            },
            $query
        );

        if ($query === $newQuery) {
            $criteria = $query;
        } else {
            $criteria = array();
            foreach ($newQuery as $field => $value) {
                $criteria[$field] = array(
                    "operation" => "LIKE",
                    "value" => $value
                );
            }

        }

        $manager = $this->getMysqlManager();

        // send an event allowing children to attach/modify search behavior
        $event = new SearchEvent($request, $manager);
        $event->setSearchQuery($criteria);
        $this->getDispatcher()->dispatch(CrudEvents::BEFORE_SEARCH, $event);
        $criteria = $event->getSearchQuery();

        $result = $manager->findAll($criteria);

        // attach an event for children to filter search results
        if(!$result) {
            $result = new BaseCollection();
        }

        $event->setCollection($result);
        $this->getDispatcher()->dispatch(CrudEvents::AFTER_SEARCH, $event);
        $result = $event->getCollection();

        return empty($result) ? array() : $result;
    }

    /**
     * @return array|mixed
     */
    public function all()
    {
        $result = $this->search(new Request);
        return empty($result) ? array() : $result;
    }

    /**
     * @param $id
     * @return mixed
     */
    public function get($id)
    {
        $manager = $this->getMysqlManager();
        $event = $this->createCrudEvent($this->app['request'], $manager);
        $event->setId($id);

        $this->getDispatcher()->dispatch(CrudEvents::BEFORE_GET, $event);

        $adapter = $manager->adapter(Manager::MYSQL_ADAPTER);
        $idName = $adapter->getModelIdName();
        $result = $manager->findOne(
            array(
                $idName => $id
            )
        );

        if($result) {
            $event->setModel($result);
            $this->getDispatcher()->dispatch(CrudEvents::AFTER_GET, $event);
            return $event->getModel();
        }

    }

    /**
     * @param Request $request
     * @return \Common\Model\BaseModel
     */
    public function create(Request $request)
    {
        $manager = $this->getMysqlManager();
        $event = $this->createCrudEvent($request, $manager);

        $adapter = $manager->adapter(Manager::MYSQL_ADAPTER);
        $model = $adapter->getModelFactory()->instance($adapter->getModelName());
        $model->populate(json_decode($request->getContent()));

        $event->setModel($model);
        $this->getDispatcher()->dispatch(CrudEvents::BEFORE_CREATE, $event);

        $model = $manager->write($event->getModel());
        $event->setModel($model);
        $this->getDispatcher()->dispatch(CrudEvents::AFTER_CREATE, $event);
        return $event->getModel();
    }

    /**
     * @param Request $request
     * @param $id
     * @return \Common\Model\BaseModel
     */
    public function update(Request $request, $id)
    {
        $manager = $this->getMysqlManager();
        $event = $this->createCrudEvent($request, $manager);

        $existing = $this->get($id);
        if ($existing) {
            $event->setId($id);
            $existing->populate(json_decode($request->getContent()));
            $event->setModel($existing);

            $this->getDispatcher()->dispatch(CrudEvents::BEFORE_UPDATE, $event);
            $existing = $event->getModel();

            $model = $manager->write($existing);

            $event->setModel($model);
            $this->getDispatcher()->dispatch(CrudEvents::AFTER_UPDATE, $event);

            return $event->getModel();
        }

    }

    /**
     * @param $id
     */
    public function delete($id)
    {
        $manager = $this->getMysqlManager();
        $event = $this->createCrudEvent($this->app['request'], $manager);

        $existing = $this->get($id);
        if ($existing) {

            $event->setId($id);
            $event->setModel($existing);
            $this->getDispatcher()->dispatch(CrudEvents::BEFORE_DELETE, $event);

            // if our event listeners marked the data as not something we can delete we do not.
            if($event->canDelete()) {
                $manager->delete($existing);
                $this->getDispatcher()->dispatch(CrudEvents::AFTER_DELETE, $event);
            }
        }
    }

    /**
     * This will register a model with common settings for a model making the
     * controller storage a simple spinup.
     *
     * @param Application $app
     * @param string $modelName name of the model to register
     * @param array $options
     */
    public function registerManager($app, $modelName, $options = array())
    {

        $lowerModel = strtolower($modelName);
        $modelName = ucwords($modelName);
        $options = array(
            $lowerModel => array_merge(array(
                'database' => $this->getDatabase(),
                'modelName' => $modelName,
                'modelFactory' =>$this->getModelFactory(),
                'adapterFactory' => $this->getAdapterFactory()
            ), $this->mergeMysqlSettings($app, $options))
        );

        $app->registerOverride(
            new MysqlManagerProvider(),
            array(
             //   'mysql.manager.credential' => ($options['credential']) ? $options['credential'] : AppConfig::MYSQL_CREDENTIAL,
                'mysql.manager.options' => $options
            )
        );
        $this->app = $app;
        $this->setMysqlManager($app['mysql.manager'][$lowerModel]);
    }

    /**
     * Shortcut operation for specifying an event around this controller action
     * @param $eventName
     * @param callable $handler
     * @param int $priority
     */
    public function on($eventName, callable $handler, $priority = 0)
    {
        $this
            ->getDispatcher()
            ->addListener($eventName, $handler, $priority);
    }

    /**
     * @param Request $request
     * @param Manager $manager
     * @return CrudEvent
     */
    protected function createCrudEvent(Request $request, Manager $manager)
    {
        return new CrudEvent($request, $manager);
    }

    protected function mergeMysqlSettings($app, $options)
    {
        $mapped = array(
           'configPath' => isset($app['mysql.configPath']) ? $app['mysql.configPath'] : null,
           'group' => isset($app['mysql.group']) ? $app['mysql.group'] : null,
           'username' => isset($app['mysql.username']) ? $app['mysql.username'] : null,
           'password' => isset($app['mysql.password']) ? $app['mysql.password'] : null,
           'host' => isset($app['mysql.host']) ? $app['mysql.host'] : null
        );

        return array_merge($mapped, $options);
    }

}