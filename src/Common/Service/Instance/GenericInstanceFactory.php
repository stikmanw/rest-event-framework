<?php
namespace Common\Service\Instance;

use Common\Injector;
use Common\Service\Instance\Exception\ClassNotFoundException;
use Common\Tool\Introspection;

/**
 * Very simple container for passing around flexible class locator.  Other find patterns should
 * be layered on by use case.
 *
 * @package Common\Service\Instance\GenericInstanceFactory

 */
class GenericInstanceFactory
{
    /**
     * Base namespace to look in for class creation.
     * @var string
     */
    protected $namespace;

    /**
     * Configuration options for how instances are created
     * @var array
     */
    protected $config = array();

    /**
     * This is used to inject a scope of variable via object into the class at create time see 'injector' option
     * @var Injector
     */
    protected $injector;

    /**
     * Set a common prefix for the instance creator
     * @var string
     */
    protected $prefix = "";

    /**
     * Set a common suffix for the instance creator
     * @var string
     */
    protected $suffix = "";

    /**
     * A callable function that is passed the instance after creation.
     * @var callable
     */
    protected $afterCreate;

    /**
     * Setup the instance creator with the rules about how object instances are found / created.
     * @param $namespace
     * @param array $config
     */
    public function __construct($namespace, array $config = array())
    {
        $this->namespace = $namespace;
        $this->config = $config;
        $this->parseConfig();
    }

    /**
     * Set the prefix for the loader
     * @param $prefix
     */
    public function setPrefix($prefix)
    {
        $this->prefix = $prefix;
    }

    /**
     * Set the injector to allow params to be passed into the instance creator this allows us to easier
     * mock different states and have different parts of the app represent the state the class should be created
     * with.
     *
     * @param Injector $injector
     */
    public function setInjector(Injector $injector)
    {
        $this->injector = $injector;
    }

    /**
     * @return Injector
     */
    public function getInjector()
    {
        return $this->injector;
    }

    /**
     * Create a new instance from the factory based on the configured settings
     * @param $identifier
     */
    public function instance($identifier)
    {
        $class = $this->determineClassName($identifier);
        $class = $this->getFullClass($class);

        if ($this->injector) {
            $instance = $this->getInjector()->make($class);
        } else {
            $instance = new $class;
        }

        if (is_callable($this->afterCreate)) {
            $this->afterCreate($instance);
        }

        return $instance;
    }

    public function getFullClass($class)
    {
        $class = $this->namespace . '\\' . $class;
        if (!class_exists($class)) {
            throw new ClassNotFoundException("{$class} was not found for the identifier: $class");
        }

        return $class;
    }

    public function parseConfig()
    {
        $this->injector = ($this->config['injector'] instanceof Injector) ? $this->config['injector'] : null;
        $this->prefix = $this->config['prefix'] ? : "";
        $this->suffix = $this->config['suffix'] ? : "";
        $this->afterCreate = is_callable($this->config['afterCreate']) ? $this->config['afterCreate'] : null;
    }

    /**
     * Apply prefix/suffix rules to the class
     * @param string $identifier
     * @return string
     */
    public function determineClassName($identifier)
    {
        return $this->prefix . $identifier . $this->suffix;
    }

    /**
     * Function tests if the passed in object can be created by this instance creator based on current configuration
     * settings on the class
     * @param object
     * @return boolean
     */
    public function willCreate($object)
    {
        $class = Introspection::getClassName($object);
        $namespaceClass = get_class($object);

        try {
            $createdName = $this->getFullClass($class);
        } catch(ClassNotFoundException $e) {
            return false;
        }

        if($createdName{0} === "\\") {
            $namespaceClass = "\\${namespaceClass}";
        }

        return ($namespaceClass === $createdName);
    }

}
