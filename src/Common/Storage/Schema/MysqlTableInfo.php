<?php
namespace Common\Storage\Schema;

use Common\Registry\AbstractRegistry;

/**
 * Class MysqlTableInfo
 * This class is used as a registry of table information per request so request do not need
 * to re-parse the same database/table information multiple times.
 */
class MysqlTableInfo extends AbstractRegistry
{
    /**
     * Get the table information in this registry
     * @param string $host
     * @param string $database
     * @param string $table
     * @return mixed
     */
    public static function getInfo($host, $database, $table)
    {
        return static::checkRegistry(array(
            "host" => $host,
            "db" => $database,
            "table" => $table
        ));
    }

    /**
     * Set the table info for this registry
     * @param $host
     * @param $database
     * @param $table
     * @param mixed $data
     */
    public static function setInfo($host, $database, $table, $data)
    {
        return static::setFromConfig(array(
            "host" => $host,
            "db" => $database,
            "table" => $table
        ), $data);
    }

}