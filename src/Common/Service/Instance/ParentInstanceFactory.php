<?php
namespace Common\Service\Instance;

use Common\Service\Instance\Exception\ClassNotFoundException;

/**
 * Use for creating instances that have a fallback parent class to load when not found.
 * @package Common\Service\Instance\GenericInstanceFactory

 */
class ParentInstanceFactory extends GenericInstanceFactory
{
    /**
     * Name of the parentClass to fall back to.
     * @var string
     */
    protected $parentClass;

    /**
     * Add the parentClass option to have a default fallback class if specified class not found in the namespace
     * config
     *
     * @throws \InvalidArgumentException
     */
    public function parseConfig()
    {
        parent::parseConfig();
        if(empty($this->config['parentClass'])) {
            throw new \InvalidArgumentException("parentClass configuration option is required to use parent factory");
        }
        $this->parentClass = $this->config['parentClass'] ?: null;
        $this->parentNamespace = $this->config['parentNamespace'] ?: $this->namespace;
    }

    /**
     * Check the specified namespace for the class if it does not exist then use the default parent.
     * @param $class
     * @return string
     * @throws Exception\ClassNotFoundException
     */
    public function getFullClass($class)
    {
        $class = $this->namespace . '\\' . $class;
        if (!class_exists($class)) {
           $class = $this->parentNamespace . '\\' . $this->parentClass;
        }

        if(!class_exists($class)) {
            throw new ClassNotFoundException("{$class} could not be loaded for this instance factory");
        }

        return $class;
    }

}
