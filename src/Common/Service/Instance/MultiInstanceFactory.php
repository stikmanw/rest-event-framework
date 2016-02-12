<?php
namespace Common\Service\Instance;

use Common\Service\Instance\Exception\ClassNotFoundException;

/**
 * This is very similar to the generic instance interface except it will allow for additional namespaces beyond the
 * first namespace.
 */
class MultiInstanceFactory extends GenericInstanceFactory
{
    protected $namespaces = array();

    /**
     * Create an object to create objects from a list of namespaces in order of load order
     * @param array $namespaces
     * @param array $config
     * return MultiInstanceFactory
     */
    public static function createFromNamespaceList(array $namespaces, $config)
    {
        $namespace = array_shift($namespaces);
        $config['namespaces'] = $namespaces;

        return new self($namespace, $config);
    }

    /**
     * Setup the instance creator with the rules about how object instances are found / created.
     * @param $namespace
     * @param array $config
     * @see parent::__construct
     */
    public function __construct($namespace, array $config = array())
    {
        $this->unshiftNamespace($namespace);
        $this->config = $config;
        $this->parseConfig();
    }

    /**
     *
     */
    public function parseConfig()
    {
        parent::parseConfig();

        if (is_array($this->config['namespaces']) && !empty($this->config['namespaces'])) {
            $this->namespaces = array_merge($this->namespaces, $this->config['namespaces']);
        }

    }

    /**
     * Add a namespace to the front of the valid namespace list giving the new namespace top priority
     * in the class load chain. This is good for setting a specific class set by user input be loaded first.
     *
     * @param string
     */
    public function unshiftNamespace($namespace)
    {
        array_unshift($this->namespaces, $namespace);
    }

    /**
     * Add a namespace to the end of the valid namespace list giving the namespace lowest priority to load from.
     * This is good for runtime setting of default namespace
     *
     * @param string
     */
    public function pushNamespace($namespace)
    {
        array_push($this->namespaces, $namespace);
    }

    /**
     * Get the name of the class we can create based on the list of namespaces registered in the config
     * @param $class
     * @return string
     * @throws Exception\ClassNotFoundException
     * @see parent::getFullClass
     */
    public function getFullClass($class)
    {
        $valid = false;
        foreach ($this->namespaces as $namespace) {
            $validClass = $namespace . '\\' . $class;
            if (class_exists($validClass)) {
                $valid = true;
                break;
            }
        }

        if (!$valid) {
            throw new ClassNotFoundException("{$class} was not found in the namespace list + class");
        }

        return $validClass;
    }

}
