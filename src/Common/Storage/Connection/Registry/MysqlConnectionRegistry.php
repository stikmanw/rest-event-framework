<?php
namespace Common\Storage\Connection\Registry;
/**
 * Connection to Mysql resources registry so connection are never
 * reused if they are still a valid resource.
 *
 */
use Common\Storage\Connection\Mysql;
use Common\Storage\Credential\MysqlEncryption;

class MysqlConnectionRegistry
{
    /**
     * @var array
     */
    static protected $registry = array();

    /**
     * Lookup a entry in the registry if it exists
     * @param $key
     * @internal param $database
     * @return null
     */
    public static function get($key)
    {
        if(isset(static::$registry[$key])) {
            return static::$registry[$key];
        }

        return null;
    }

    /**
     * Get the connection from the registry of connection otherwise create a new connection
     * and register the connection for later use.
     * @param array $config
     * @return null
     */
    public static function connect(array $config = array())
    {
        $key = static::createRegistryKey($config);
        $conn = static::get($key);

        if($conn) {
            return $conn;
        }

        static::$registry[$key] = new Mysql($config);
        return static::$registry[$key];
    }

    public static function connectSecure(MysqlEncryption $enc, $params = array())
    {
        $regKey = static::createRegistryKey($enc);
        $conn = static::get($regKey);

        if($conn) {
            return $conn;
        }

        static::$registry[$regKey] = Mysql::connectFromEncryption($enc, $params);
        return static::$registry[$regKey];
    }

    /**
     * Create a registry key for based on the host setting of a configuration
     * @param array $config
     * @return string
     */
    public static function createRegistryKey(array $config = array())
    {
         return serialize($config);
    }

    /**
     * Empty the connection registry
     */
    public static function cleanRegistry()
    {
        static::$registry = array();
    }

    /**
     * Get the current connection registry
     */
    public static function getRegistry()
    {
        return static::$registry;
    }
}