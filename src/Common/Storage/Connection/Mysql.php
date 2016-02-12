<?php
namespace Common\Storage\Connection;

use Common\Storage\Credential\MysqlEncryption;
use Common\Storage\Schema\MysqlTableInfo;
use Common\Tool\ServerUtility;
use Common\Storage\PDOMysql;
use Common\Storage\Schema\Index;
use Doctrine\DBAL;
use phpFastCache\CacheManager;
use Seld\JsonLint\JsonParser;
use Seld\JsonLint\ParsingException;

/**
 * Will establish a connection to the master/slave based on the passed in params
 * that define the clustered environments for our MySQL Servers
 *
 * required settings groups
 * username, password, host
 *
 * environment
 * pdo.*
 *
 * @property mixed environment
 */
class Mysql extends AbstractConnection implements ConnectionInterface
{
    /**
     * Database credentials username
     * @var string
     */
    protected $username;

    /**
     * Database credentials password
     * @var string
     */
    protected $password;

    /**
     * Set the database name we are connected to
     * @var string
     */
    protected $database;

    /**
     * Local column lookup cache
     *
     * @example array("DbName.TableName" => array('myColumn' => 'int(8) unsigned'));
     * @var array
     */
    protected $columnLookup = array();

    /**
     * How long the table information should live.  Typically this should live for an hour.
     * Table Schema changes should use the table purger as necessary for performance.
     * Number of seconds
     *
     * @var int
     */
    protected $tableInfoCacheSec = 3600;

    /**
     * If the table info cache always store the table info hourly so we do not need to rebuild the
     * internal schema for it. This allows us to automap columns based on the last schema seen.
     *
     * @var bool
     */
    protected $tableInfoCache = true;

    /**
     * This will create a local variable to store the table info for the database.table in memory
     * instead of request a reparse from server. This is really only used to help in dev instances
     * @var array
     */
    protected $tableInfoRegistry = array();

    /**
     * Connect using the credential encryption class that contains credentials
     */
    public static function connectFromEncryption(MysqlEncryption $encryptor, $params = array())
    {
        $config = array(
           "username" => $encryptor->getUser(),
           "password" => $encryptor->getPassword(),
           "host" => $encryptor->getHost()
        );
        $config = array_merge($config, $params);
        return new static($config);
    }

    /**
     * This will establish a connection to databases based on the parameters settings
     *
     * @param bool|string $identifier slave/master/false
     * @throws \DomainException
     * @return array| PDOMysql
     */
    public function connect($identifier = false)
    {
        if(empty($this->hosts)) {
            $msg = "No connection host names present.  Check settings configuration settings.";
            throw new \DomainException($msg);
        }

        $connParams = $this->validateConnectionParams($this->params);

        if(isset($this->params['database'])) {
            $dbString = ";dbname=" . $this->params['database'];
        } else {
            $dbString = "";
        }

        // make a connection and setup the PDO resource internally.
        switch($identifier) {
            case 'slave':
                $slave = $this->determineSlave();
                $this->resources['slave'] = new PDOMysql("mysql:host={$slave}{$dbString}", $this->username, $this->password, $connParams);
                return $this->resources['slave'];
                break;

            case 'master':
                $master = current($this->hosts['master']);
                $this->resources['master'] = new PDOMysql("mysql:host={$master}{$dbString}", $this->username, $this->password, $connParams);
                return $this->resources['master'];
                break;

            default:
                $slave = $this->determineSlave();
                $master = current($this->hosts['master']);

                $this->resources['master'] = new PDOMysql("mysql:host={$master}{$dbString}", $this->username, $this->password, $connParams);
                $this->resources['slave'] = new PDOMysql("mysql:host={$slave}{$dbString}", $this->username, $this->password, $connParams);
                return $this->resources;
                break;
        }

    }

    /**
     * @return \Common\Storage\PDOMysql
     */
    public function slave()
    {
        if(isset($this->resources['slave']) && $this->isValidResource($this->resources['slave'])) {
            return $this->resources['slave'];
        }

        return $this->connect('slave');
    }

    /**
     * @return \Common\Storage\PDOMysql
     */
    public function master()
    {
        if(isset($this->resources['master']) && $this->isValidResource($this->resources['master'])) {
            return $this->resources['master'];
        }

        return $this->connect('master');
    }

    /**
     * @param $resource
     * @return boolean
     */
    public function isValidResource($resource)
    {
        if(!$resource instanceof PDOMysql) {
             return false;
        }

        return true;

        /**
         * if we ever have resource gone away problems in the future this is the safest way to do this,
         * however for speed opti we are going to assume we do not need it.
         */
        // return $resource->ping();


    }

    public function determineSlave()
    {
        $hosts = $this->getRegisteredHosts();

        if(!is_array($hosts['slave'])) {
            throw new \UnexpectedValueException('Expected an array of slave hosts.');
        }

        if(empty($this->username) && empty($this->password)) {
            throw new \Exception('Username and password have not been setup for this connection instance.  Has setup been run prior to determing the slave?');
        }

        $hosts = $hosts['slave'];
        shuffle($hosts);

        return array_shift($hosts);
    }

    /**
     * Important note on closing connections.  If you have specified a persistant connection the connection is not really
     * closed.  So be mindful of your persistant connection and do be stupid with them or I we will hold you down
     * and Ammon gets to punch you a few times.
     *
     */
    public function close()
    {
        unset($this->resources['slave']);
        unset($this->resources['master']);
    }

    /**
     * Set the database for this connection if it is different or not set
     * @param string $database name of the database to set the scope of our future queries against
     */
    public function setDatabase($database)
    {
        if($this->database != $database) {
            $this->database = $database;
            $this->slave()->exec("USE `{$this->database}`");
            $this->master()->exec("USE `{$this->database}`");
        }

    }

    /**
     * Get the name of the database we are currently have set as our scope.
     *
     * @return string
     */
    public function getDatabase()
    {
        return $this->database;
    }

    public function getMasterHost()
    {
        return current($this->hosts["master"]);
    }

    /**
     * Validate / Filter the extra pdo connection parameters passed in issue warnings for set parameter that are not going to be used.
     *
     * @param array $params
     * @return array
     */
    public function validateConnectionParams($params)
    {
        $filtered = array();

        // for every parameter we need to check for pdo.* and see if it is a valid constant if, yes? add it to our connection settings.
        foreach($params as $name => $value) {
            if(strpos($name, "pdo.") !== false) {
                $name = str_replace("pdo.", "", $name);

                if(contstant('\PDO::' . $name) !== null) {
                    trigger_error($name . " is not valid attribute or driver setting for PDO", E_USER_WARNING);
                } else {
                    $filtered[constant('\PDO::'.$name)] = $value;
                }

            }
        }

        return $filtered;
    }

    /**
     * Generate a mysql schema for the given database / table
     *
     * @param string $database
     * @param string $table
     * @throws InvalidArguementException
     * @internal param string $database
     * @internal param string $table
     * @return string
     */
    public function generateSchemaJson($database, $table)
    {
        $table = "`$database`.`$table`";
        $result = $this->master()->query("SHOW CREATE TABLE {$table}");

        $schema = array();

        if($result->rowCount()) {
            $row = $result->fetch();

            $createTable = "Create Table";
            $createTable = preg_replace("/(AUTO_INCREMENT=[0-9]+)?/","",$row->$createTable);
            $lines = explode("\n", $createTable);

            foreach($lines as $line) {
                $line = trim($line);

                if(stripos($line, "CREATE TABLE") !== false) {
                    continue;
                }

                if(stripos($line, "ENGINE=") !== false) {
                    continue;
                }

                $origLine = $line;
                $line = preg_replace("#[`'\"\(\)]#", "", $line);
                $parts = explode(" ",$line);

                if($origLine{0} !== "`") {

                    if(stripos($line, "PRIMARY KEY") !== false) {

                        if(stripos($parts[2], ",") !== false){
                            $primaryKeyParts = explode(",",$parts[2]);
                            $parts[2] = $primaryKeyParts[0];
                        }

                        // if the column posted has an extra field for AUTO_INCREMENT then we know this is an autoincrement index
                        $autoincrement = false;
                        foreach($schema['columns'] as $column) {
                            if($column['name'] == $parts[2] && array_search('AUTO_INCREMENT', $column['extra']) !== false) {
                                $autoincrement = true;
                            }
                        }

                        $schema['index'][] = array(
                            "name" => $parts[2],
                            "columns" => explode(",", preg_replace("/,$/","",$parts[2])),
                            "unique" => true,
                            "primary" => true,
                            "autoincrement" => $autoincrement
                        );

                        continue;
                    }

                    if(stripos($line, "UNIQUE KEY") !== false) {
                        $schema['index'][] = array(
                            "name" => $parts[2],
                            "columns" => explode(",", preg_replace("/,$/","",$parts[3])),
                            "unique" => true,
                            "primary" => false,
                            "autoincrement" => false
                        );
                        continue;
                    }

                    if(stripos($line, "FULLTEXT") !== false) {
                        continue;
                    }

                    if(strpos($line, "CONSTRAINT ") !== false) {
                        continue;
                    }

                    if(strpos($line, "FOREIGN KEY ") !== false) {
                        continue;
                    }

                    if(strpos($line, "PARTITION " !== false )) {
                        continue;
                    }

                    if(stripos($line, "KEY ") === 0 ) {

                        $schema['index'][] = array(
                            "name" => $parts[1],
                            "columns" => explode(",", preg_replace("/,$/","",$parts[2])),
                            "unique" => false,
                            "primary" => false,
                            "autoincrement" => false
                        );
                        continue;
                    }

                } else {

                    // walk the details and store them on the extra field
                    if(isset($parts[2])) {
                        $extra = array_slice($parts, 2);

                        // clean up the columns inside
                        array_walk($extra, function (&$value) {
                                $value = str_replace(",", "", $value);
                            });
                    } else {
                        $extra = null;
                    }

                    // if we made it here then we are a column
                    $schema['columns'][] = array(
                        "name" => $parts[0],
                        "type" => preg_replace("/[0-9]+|enum|/","",preg_replace("/,$/", "",$parts[1])),
                        "typedetail" => (preg_match("/[0-9]+/", $parts[1], $matches)) ? $matches[0] : null,
                        "extra" => $extra
                    );

                }

            }

            return json_encode($schema);

        } else {
            throw new InvalidArguementException("Could nor retrieve schema for $table");
        }

    }

    /**
     * Check the table information cache for the given table data
     *
     * @param string $database
     * @param string $table
     * @return string
     */
    public function tableInfo($database, $table)
    {
        // use the mysql registry for this request to look up information about the database/table on this host
        $tableInfo = MysqlTableInfo::getInfo($this->hosts['master'], $database, $table);
        $MemcacheConn = null;

        if($tableInfo) {
            return $tableInfo;
        }

        $key = current($this->hosts['master']) . "|{$database}.{$table}";
        $isLocal = ServerUtility::isLocal();

        // Using file cache to make thie more portable need to expose caching layer in the future
        CacheManager::setup(
            array(
                "path" => sys_get_temp_dir(),
            )
        );
        $InstanceCache = CacheManager::Files();

        // when running a local machine we want to allow schema updates to happen at anytime so no caching layer in place
        if($isLocal) {
            $tableInfo = false;
        } else {
           $tableInfo = $InstanceCache->get($key);
        }

        if(!$tableInfo) {
             $tableInfo = $this->generateSchemaJson($database, $table);
             $InstanceCache->set($key, $tableInfo, $this->tableInfoCacheSec);
             MysqlTableInfo::setInfo($this->hosts['master'], $database, $table, $tableInfo);
        }

        return $tableInfo;

    }

    /**
     * Get the list of columns for a given database / table
     *
     * @param string $dbName
     * @param string $tableName
     * @example array ('myColumn' => stdClass());
     * @return array
     * @todo Create an actual Column class model doctrines is rather annoying with how they handle their types so we will do better
     */
    public function getColumnList($dbName, $tableName)
    {
        $tableData = json_decode($this->tableInfo($dbName, $tableName));

        $columnsArray = array();
        foreach($tableData->columns as $column) {
            $columnsArray[$column->name] = $column;
        }
        return $columnsArray;
    }

    /**
     * Get the list of indexes
     *
     * @param $dbName
     * @param $tableName
     * @return array<\Common\Storage\Schema\Index>
     */
    public function getIndexes($dbName, $tableName)
    {
        $tableData = json_decode($this->tableInfo($dbName, $tableName));

        $indexArray = array();
        if(isset($tableData->index) && is_array($tableData->index)) {
            foreach($tableData->index as $index) {
                $indexArray[] = new Index($index->name, $index->columns, $index->unique, $index->primary, $index->autoincrement);
            }
        }

        return $indexArray;

    }

    /**
     * Get the unique index columns for datbase/table
     *
     * @param string $dbName
     * @param string $tableName
     * @return array <\Common\Storage\Schema\Index
     */
    public function getUniqueIndexes($dbName, $tableName)
    {
        $indexes = $this->getIndexes($dbName, $tableName);

        $unique = array();
        foreach($indexes as $index) {
            if($index->isUnique()) {
                $unique[] = $index;
            }
        }

        return $unique;
    }

    /**
     * Find the index for a given database and table
     *
     * @param $dbName
     * @param $tableName
     * @return array <string>
     */
    public function getPrimaryKey($dbName, $tableName)
    {
         $indexes = $this->getIndexes($dbName, $tableName);

         $primary = null;
         foreach($indexes as $index) {
             if($index->isPrimary()) {
                 return $index->getColumns();
             }
         }
    }

    /**
     * Get the most relevant unique key (the one with the most columns)
     *
     * @param $dbName
     * @param $tableName
     * @return array <string>
     */
    public function getUniqueSearchKey($dbName, $tableName)
    {
        $uniques = $this->getUniqueIndexes($dbName, $tableName);

        $uniquest = array();
        $numColumns = 0;
        foreach($uniques as $unique) {
            if(count($unique) > $numColumns) {
                $uniquest = $unique->getColumns();
            }
        }

        return $uniquest;
    }

    /**
     * Establish a connection based on the params that have been passed into
     * @see Connection::setup
     */
    public function setup()
    {
        $params = $this->getParams();

        if(isset($params['database'])) {
            $this->database = $params['database'];
        }

        if(isset($params['environment'])) {
            $this->environment = $params['environment'];
        }

        if(isset($params['tableInfoCache'])) {
            $this->tableInfoCache = $params['tableInfoCache'];
        }

        if(empty($params)) {
            throw new \InvalidArgumentException('Mysql adapter requires some parameters');
        }

        $valid = false;
        // custom setting of the connection and credentials we will use for this connection
        if(isset($params['username']) && isset($params['password']) && isset($params['host'])) {
            $this->setSpecifiedHost($params['host'], $params['username'], $params['password']);
            $valid = true;
        }

        if(isset($params['configPath'])) {
            $group = isset($params['group']) ? $params['group'] : null;
            $this->setHostByConfig($params['configPath'], $group);
        }

        if(!$valid) {
            throw new \InvalidArgumentException('Mysql connection requires a mysql credentials or a valid config path');
        }

    }

    /**
     * The params have set a specific host name to connect to and treat as Master/Slave
     *
     * @param string $host
     * @param string $user
     * @param string $password
     * @return bool
     */
    protected function setSpecifiedHost($host, $user, $password)
    {
            $this->username = $user;
            $this->password = $password;
            $this->registerHost('master', $host);
            $this->registerHost('slave', $host);
            return true;
    }

    protected function setHostByConfig($configPath, $group = "default")
    {
        if(!is_file($configPath)) {
            throw new \RuntimeException("configuration path for hosts is not a valid file");
        }

        $configData = file_get_contents($configPath);

        $parser = new JsonParser();
        $result = $parser->lint($configData);

        if($result instanceof ParsingException) {
            throw $result;
        }

        if(!$configData->{$group}) {
            throw new \RuntimeException("group {$group} not found");
        }

        $dbGroup = $configData->{$group};

        if(!isset($dbGroup->username) && !isset($dbGroup->password)) {
            throw new \RuntimeException("Group {$group} did not have a valid username/password set");
        }

        $this->username = $dbGroup->username;
        $this->password = $dbGroup->password;

        if(!isset($dbGroup->master)) {
            throw new \RunTimeException("Group: {$group} in config path did not have a master host setup");
        }

        $this->registerHost('master', $dbGroup->master);

        // no slave set use the master
        if(!isset($dbGroup->slave)) {
            $this->registerHost('slave', $dbGroup->master);
        }

        if(isset($dbGroup->slave)) {
           if(is_string($dbGroup->slave)) {
               $this->registerHost('slave', $dbGroup->slave);
           }

           if(is_array($dbGroup->slave)) {
               foreach($dbGroup->slave as $host) {
                   if(is_string($host)) {
                       $this->registerHost('slave', $host);
                   }
               }
           }
        }

    }

    /**
     * return the filtered result of hosts based on the passed in prefix
     *
     * @param string $prefix the prefix on the servers to look for
     * @param array hosts list of db host names to search
     * @return array
     */
    protected function filterHostByPrefix($prefix, $hosts)
    {
        $filteredHosts = array();
        foreach($hosts as $host => $hostList) {
            if(stripos($host, $prefix) !== false && stripos($host, "server") !== false) {
                $filteredHosts[$host] = $hostList;
            }
        }

        return $filteredHosts;
    }

}
