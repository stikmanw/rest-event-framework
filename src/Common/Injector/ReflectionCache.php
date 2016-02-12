<?php
namespace Common\Injector;
/**
 * Small wrapper around reflection class so we do not find the need to
 * rerun reflection on objects we have already ran them on.
 */

class ReflectionCache
{
    /**
     * @var ReflectionCache
     */
    static public $instance;

    /**
     * @var array
     */
    private $cache = array();

    /**
     * must use get instance to ensure cache locally is used
     */
    private function __construct() {}

    /**
     * @return ReflectionCache
     */
    public static function getInstance()
    {
        if(!static::$instance) {
            static::$instance  = new ReflectionCache();
        }

        return static::$instance;
    }

    /**
     * @param $class
     * @return bool|\ReflectionClass
     * @throws \InvalidArgumentException
     */
    public function getClass($class)
    {
       if(!class_exists($class)) {
            throw new \InvalidArgumentException("$class is not an existing class in the system");
       }

        $lookup = "class-{$class}";
        if($this->getCache($lookup)) {
            return $this->getCache($lookup);
        }

        $reflectionClass = new \ReflectionClass($class);

        if(!$reflectionClass) {
            throw new \InvalidArgumentException("failure when receiving class information");
        }
        $this->setCache($lookup, $reflectionClass);

        return $reflectionClass;
    }

    /**
     * @param $nameOrInstance
     * @param $method
     * @return bool|\ReflectionMethod
     */
    public function getMethod($nameOrInstance, $method)
    {
        $lookup = "method-{$method}";
        if($this->getCache($lookup)) {
            return $this->getCache($lookup);
        }

        $reflectionMethod = new \ReflectionMethod($nameOrInstance, $method);
        $this->setCache($lookup, $reflectionMethod);

        return $reflectionMethod;
    }

    /**
     * @param $class
     * @return bool|\ReflectionMethod
     */
    public function getConstructor($class)
    {
        $lookup = "construct-{$class}";
        if($this->getCache($lookup)) {
            return $this->getCache($lookup);
        }

        $class = $this->getClass($class);
        $constructor = $class->getConstructor();

        $this->setCache($lookup, $constructor);
        return $constructor;
    }

    /**
     * @param $key
     * @return bool
     */
    public function getCache($key)
    {
        $key = strtolower($key);
        return isset($this->cache[$key]) ? $this->cache[$key] : false;
     }

    /**
     * @param $key
     * @param $data
     */
    public function setCache($key, $data)
    {
        $key = strtolower($key);
        $this->cache[$key] = $data;
    }



}
