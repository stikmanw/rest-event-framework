<?php
namespace Common\Registry;

abstract class AbstractRegistry
{
    static protected $registry;

    /**
     * Based on a configuration for this registry lookup the item in our registry memory space
     * @param array $config
     * @return mixed
     */
    public static function checkRegistry(array $config = array())
    {
        $key = static::createRegistryKey($config);
        $result = static::get($key);

        return $result;
    }

    /**
     * Create a new registry string key from a mixed set of data.
     * @param array $config
     * @return string
     */
    public static function createRegistryKey(array $config = array())
    {
        return serialize($config);
    }

    /**
     * Lookup a key in our memory registry and return the result
     * @param $key
     * @return mixed
     */
    public static function get($key)
    {
        if(isset(static::$registry[$key])) {
            return static::$registry[$key];
        }

        return null;
    }

    /**
     * Set a value in the registry based on a existing string key format
     * @param $key
     * @param $data
     */
    public static function set($key, $data)
    {
        static::$registry[$key] = $data;
    }

    /**
     * Create a new entry
     * @param array $config
     * @param $data
     */
    public static function setFromConfig(array $config = array(), $data)
    {
        $key = static::createRegistryKey($config);
        static::set($key, $data);
    }

}