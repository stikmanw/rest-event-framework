<?php
namespace Common\Provider;

use Common\Storage\Configuration;
use Common\Storage\Manager;
use Silex\Application;
use Silex\ServiceProviderInterface;

class MysqlManagerProvider implements ServiceProviderInterface
{
    protected $app;

    /**
     * Registers services on the given app.
     *
     * This method should only be used to configure services and parameters.
     * It should not get services.
     */
    public function register(Application $app)
    {
        $this->app = $app;
        $self = $this;

        // let the environment exist specifically for the database but rely on the environment provider
        $app['mysql.manager.env'] = $this->getEnvironment($app);

        if(!isset($app['mysql.managers'])) {
            $app['mysql.managers'] = array();
        }

        // we will copy options first if they are set.
        // keep track of all options registered and store them application scope
        $this->copyOptions($app);

        $app['mysql.manager'] = function() use($app, $self) {

            $this->copyOptions($app);

            foreach($app['mysql.manager.options.all'] as $name => $options) {
                if(! $self->isRegistered($name)) {
                    $self->registerManager($name, $options);
                }
            }

            return $app['mysql.managers'];
        };

    }

    /**
     * Bootstraps the application.
     *
     * This method is called after all services are registered
     * and should be used for "dynamic" configuration (whenever
     * a service must be requested).
     */
    public function boot(Application $app)
    {
        // allows us to force boot the mysql manager loader
        $app['mysql.manager'];
    }

    /**
     * Add a manager to the list of managers on the application chain.
     * @param $name
     * @param $options
     * @return $this
     */
    public function registerManager($name, $options)
    {
        $this->app['mysql.managers'] = array_merge($this->app['mysql.managers'], array(
            $name => $this->createManager($this->app, $options)
        ));

        return $this;
    }

    /**
     * Check if the manager is already registered on the application object
     * @param $name
     * @return bool
     */
    public function isRegistered($name)
    {
        if(isset($this->app['mysql.managers'][$name])) {
            return true;
        }

        return false;
    }

    /**
     * Copy options for multiple connections to a central options store for all managers.
     * @param $app
     */
    protected function copyOptions($app)
    {
        // keep track of all options registered and store them application scope
        if(isset($app['mysql.manager.options'])) {
            if(!isset($app['mysql.manager.options.all'])) {
                $app['mysql.manager.options.all'] = $app['mysql.manager.options'];
            } else {
                $app['mysql.manager.options.all'] = array_merge($app['mysql.manager.options.all'], $app['mysql.manager.options']);
            }
        }
    }

    /**
     *
     * @param $app
     * @param $options
     * @throws \RuntimeException
     * @internal param $name
     * @return \Common\Storage\Manager
     */
    protected function createManager($app, $options)
    {
        $options = array_merge(array(
             "environment" => $app['mysql.manager.env'],
             "adapters" => array('mysql')
        ), $options);

//        if(!isset($options['credential']) && !isset($app['mysql.manager.credential'])) {
//            throw new \RuntimeException("db.credential must be set globally or specify credential name in the
//            individual db configuration.");
//        }
//
//        if(isset($options['credential'])) {
//            $options['appName'] = $options['credential'];
//            unset($options['credential']);
//        } elseif($app['mysql.manager.credential']) {
//            $options['appName'] = $app['mysql.manager.credential'];
//        }

        if(!isset($options['database'])) {
            throw new \RuntimeException("Missing database in configuration required option must be set");
        }

        return new Manager(
            new Configuration($options)
        );
    }

    /**
     * Get the various options that can be set on for the storage manager on
     * a mysql driver.
     * @return array
     */
    public function getOptions()
    {
        return array(

            // name of the model to use for storage mapping to database
            // @var string
            // @required
            "modelName" => null,

            // the pase prefix for the id ex) Call  for CallID
            // @var string
            "idBaseName" => null,

            // database table name to store the models in
            // @var string
            "modelTableName" => null,

            // database table name to store the meta data for the model
            // @var string
            "metaModelTableName" => null,

            // string database name to set as the default for all queries
            // @var string
            "database" => null,

            // pass in an existing MysqlConnection
            // @var MysqlConnection
            "connection" => null,

            // collection name used for results sets that return a collection
            // @var string
            "collectionName" => null,

            // instance factory for creating models should required
            // @var GenericInstanceFactory
            // @required
            "modelFactory" => null,

            "username" => null,
            "password" => null,
            "host" => null,
            "configPath" => null,
            "group" => null,

            // instance factory for creating collections
            // @var GenericInstanceFactory
            // @required
            "collectionFactory" => null,

            // Factory used to find the correct adapter
            "adapterFactory" => null
        );
    }

    /**
     * @param Application $app
     * @return mixed
     * @throws \RuntimeException
     */
    protected function getEnvironment(Application $app)
    {
        // forced db environment option
        if(isset($app['mysql.manager.env'])) {
            return $app['mysql.manager.env'];
        }

        // application has their own environment provider
        if(isset($app['env'])) {
            return $app['env.name'];
        }

        if(isset($app['environment'])) {
            return $app['environment'];
        }

        throw new \RuntimeException("Environment was not specified in configuration and environemnt provider
        could not be found.");

    }
}