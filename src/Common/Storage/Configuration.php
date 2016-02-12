<?php
namespace Common\Storage;

use Common\Service\Instance\GenericInstanceFactory;
use Common\Tool\Introspection;

/**
 * Very light configuration object for containment of settings used for Storage Adapters
 * Lenient system for passing in configuration options related to the supported storage adapters.
 * @example
 * $Config = new Configuration(array(
 *     'modelName' => 'Contact',
 *     'adapters' => array('mysql'),
 *
 *     'mysql.pdo.ATTR_PERSISTANCE => 'true',
 *     'mysql.slavethreshold' => 1 second,
 *     'mysql.pdo.[DRIVER SETTINGS]
 *
 *     'reddis.setting' => ''
 *     'memcache.connectionDriver' => ''
 * ));
 *
 *

 */
class Configuration
{
    /**
     * Root name of the application in used to autocreate models
     * @var string
     */
    public $appName;

    /**
     *
     * @var array
     */
    public $adapters;

    /**
     * Name of the model we need to scope to
     *
     *  @var string
     */
    public $modelName;

    /**
     * Name of the collection we need to scope to
     *
     *  @var string
     */
    public $collectionName;

    /**
     * Factory for creating Model instance in the adapters
     * @var GenericInstanceFactory
     */
    public $modelFactory;

    /**
     * Factory for creating collections in the adapters
     * @var GenericInstanceFactory
     */
    public $collectionFactory;

    /**
     * Name of the model identifier
     *
     *  @var string
     */
    public $identifierName;

    /**
     * Name of the database we plan to connect to
     *
     *  @var string
     */
    public $database;

    /**
     * Environment type
     *
     * @var string
     */
    public $environment;

    /**
     * @var string
     */
    public $configPath;

    /**
     * @var string
     */
    public $group;

    /**
     * list of supported adapters
     */
    private $supportedAdapters = array('mysql');

    /**
     * List of settings not matched up with the configuration options of the class
     * @array
     */
    private $settings;

    /**
     * @array
     */
    private $adapterSettings = array();

    /**
     * List of namespaces that will be used to determine where models/collections are loaded from.
     * @var array
     */
    private $namespaceArray = array();

    /**
     * This allows for configuration on where to load the adapter factory.  We will default this to a common
     * factory loader.
     * @var GenericInstanceFactory
     */
    private $adapterFactory;


    /**
     * Build and populate the based on the settings
     */
    public function __construct($settings = array())
    {
        if(empty($settings['modelName'])) {
            throw new \InvalidArgumentException('modelName is an expected required setting. Null or empty value found');
        }

        $this->populate($settings);
        $this->parseAdapters();
    }

    /**
     * Shortcut into settings array object
     * @param string $name
     */
    public function __get($name)
    {
        if(isset($this->settings[$name])) {
            return $this->settings[$name];
        }

    }

    public function getSettings()
    {
        $settings = array();
        $settingNames = Introspection::getPublicVars($this);
        foreach($settingNames as $key => $value) {
            if(isset($value)) {
                $settings[$key] = $value;
            }
        }

        if(!empty($this->settings)) {
            return array_merge($settings, $this->settings);
        } else {
            return $settings;
        }
    }

    /**
     * @return array
     */
    public function getNamespaces() {
        return $this->namespaceArray;
    }

    /**
     * takes an array and merges it with existing namespace array
     */
    public function setNamespaces($namespaceArray) {

        if ( is_array($namespaceArray) && !empty($namespaceArray) ) {
            $this->namespaceArray = array_merge($this->namespaceArray, $namespaceArray);
        }

    }

    /**
     * Get the Adapter factory that will figure out where the adapters live and can be used
     * to create new instances.
     *
     * @return GenericInstanceFactory
     */
    public function getAdapterFactory()
    {
        return $this->adapterFactory;
    }

    /**
     * Set the adapter factory for this configuration
     * @param GenericInstanceFactory $factory
     */
    public function setAdapterFactory(GenericInstanceFactory $factory)
    {
        $this->adapterFactory = $factory;
    }

    /**
     * This will load the model factory from the configuration if set. Otherwise, it will
     * make some assumptions about other settings to arrive at a reasonable default.
     *
     * @return GenericInstanceFactory
     */
    public function getModelFactory()
    {
        if(! $this->modelFactory instanceof GenericInstanceFactory) {

            $namespace = ucwords($this->appName) . "\\Model\\";
            $this->modelFactory = new GenericInstanceFactory($namespace);

        }

        return $this->modelFactory;
    }

    /**
     * Populate the configuration from an array of settings
     *
     * @param array $settings
     * @throws \InvalidArgumentException
     */
    public function populate($settings = array())
    {
        $publicVars = Introspection::getPublicVars($this);

        foreach ($settings as $name => $value) {

            if($name === 'adapters') {

                if(!is_array($value)) {
                    throw new \InvalidArgumentException("adapter value must be an array " . print_r($value, true) . " given.");
                }

                foreach($value as $adapterType) {
                    if(!$this->validAdapter($adapterType)) {
                        throw new \InvalidArgumentException("$adapterType is not a support adapter type. Supported Adapters " . implode("|", $this->supportedAdapters));
                    }
                }
            }

            if ( $name === 'namespaces' ) {
                $this->setNamespaces($value);
            } elseif ($name === 'adapterFactory') {
                $this->setAdapterFactory($value);
            } elseif (array_key_exists($name, $publicVars)) {
                $this->$name = $value;
            } else {
                $this->settings[$name] = $value;
            }
        }
    }

    /**
     * Configuration has an adapter set on the configuration
     *
     * @return boolean
     */
    public function hasAdapter()
    {
        return (is_array($this->adapters) && !empty($this->adapters)) ? true : false;
    }

    /**
     * True if the passed adapter type is currently set to be used.
     *
     * @param $adapterType
     * @return bool
     */
    public function usingAdapter($adapterType)
    {
        return in_array($adapterType, $this->adapters);
    }

    public function getAdapters()
    {
        return $this->adapters;
    }

    /**
     * Get the list of adapter settings that are current set to this adapter.
     *
     * @param $adapterType
     * @return array
     * @throws \InvalidArgumentException
     */
    public function getAdapterSettings($adapterType)
    {
        if(!isset($this->adapterSettings[$adapterType]) && !$this->usingAdapter($adapterType)) {
            throw new \InvalidArgumentException($adapterType . " is not set on this configuration");
        }

        // get the specific adapter settings
        $adapterSettings =  (isset($this->adapterSettings[$adapterType]) &&
            is_array($this->adapterSettings[$adapterType])) ? $this->adapterSettings[$adapterType] : array();

        // apply global settings also
        $globalSettings = $this->getSettings();

        if(is_array($globalSettings)) {
            return array_merge($globalSettings, $adapterSettings);
        } else {
            return $adapterSettings;
        }

    }

    /**
     * Parse the setting into identifier / name
     *
     * @param $setting
     * @return array
     */
    public function parseSetting($setting)
    {
        $parts = explode(".", $setting);

        if(count($parts) >= 2) {
            return array(
                'identifier' => $parts[0],
                'name' => implode(".", array_slice($parts, 1))
            );
        } else {
            return array(
                'identifier' => '',
                'name' => $parts[0]
            );
        }
    }

    /**
     * Parse through all of the adapter settings that have been setup. This will put all of the
     * adapter settings split out by type into adapterSettings.
     *
     * @return void
     */
    protected function parseAdapters()
    {
        if(!is_array($this->settings)) {
            return;
        }

        foreach ($this->settings as $setting => $value) {
            $setting = $this->parseSetting($setting);

            if ($this->validAdapter($setting['identifier'])) {

                if(empty($this->adapterSettings) || !is_array($this->adapterSettings[$setting['identifier']])) {
                    $this->adapterSettings[$setting['identifier']] = array();
                }

                $this->adapterSettings[$setting['identifier']][$setting['name']] = $value;
            }
        }
    }

    /**
     * If the passed adapter type is one of the supported adatper types
     *
     * @param $adapterType
     * @return bool
     */
    protected function validAdapter($adapterType)
    {
        return in_array($adapterType, $this->supportedAdapters);
    }

}
