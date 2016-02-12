<?php
namespace Common\Storage;

/**
 * This is a persistable object that is used for making changes specifically
 * to the database.  The model serves a checks and validations around DB
 * specific rules we must adhere to when persisting data to a DB Adapter.
 *
 */
use Common\Storage\Connection\Mysql;
use Common\Tool\Introspection;

class DBPersistable
{
    const NULL_VALUE = "null";
    const DB_TIMEZONE = "America/Chicago";
    const SHORTDATE = 'Y-m-d';
    const ISO_DATETIME = 'Y-m-d H:i:s';

    /**
     * Existing connection to a connection interface.
     *
     * @param Common\Connection\Mysql
     */
    protected $connection;

    /**
     * Name of the table in which this persistable model read/writes to
     *
     * @var string
     */
    protected $table;

    /**
     * Name of the database which this persistable model read/writes to
     *
     * @var string
     */
    protected $database;

    /**
     * list of columns associated to this database/table combination
     *
     * @var array
     */
    protected $columnList;

    /**
     * The primary key of the table
     *
     */
    protected $primary;

    /**
     * Unique search index on the table
     * @var mix
     */
    protected $unique;

    /**
     * Temporarily force a specific lookup key for running updates or similar
     * methods on the table
     * @var array
     */
    protected $tempForceLookupCols;

    /**
     *  Mapper array that identifies columns from an incoming model and maps them to
     *  their respective DB storage equivalent
     *
     *  @var array
     */
    protected $mapArray = array();

    /**
     * callable that will be called
     *
     * @var callable
     */
    protected $mapper;

    /**
     * last insert id
     *
     * @var int
     */
    protected $lastInsertId;

    /**
     * List of fields that set on the object mapper
     * @var array
     */
    protected $dataList = array();

    /**
     * Setup this persistance model
     *
     * @param Mysql $conn
     * @param string $database
     * @param string $table
     */
    public function __construct($conn, $database, $table)
    {
        if(!$conn instanceof Mysql) {
            throw new \InvalidArgumentException("Passed in connection must be a valid connection to create a persistable object.");
        }

        if(empty($database)) {
            throw new \InvalidArgumentException("Name of database can not be empty when creating a new persistable object");
        }

        if(empty($table)) {
            throw new \InvalidArgumentException("Name of the table can not be empty when creating a new persistable object");
        }

        $this->connection = $conn;
        $this->database = $database;
        $this->table = $table;
    }

    /**
     * only allow items to be set that are in the database table.
     *
     * @var string $name
     * @var mixed $value
     */
    public function __set($name, $value)
    {
        $column = $this->determineColumn($name);

        // if we have a column then go ahead and map it
        if($column) {
            $this->$column = $value;
            $this->dataList[] = $column;
            $this->dataList = array_unique($this->dataList);
        }
    }

    /**
     * When this object is cloned reset the values of the colum list\
     */
    public function __clone()
    {
        if(!empty($this->dataList)) {
            foreach($this->dataList as $column) {
                $this->$column = null;
            }
        }
    }

    /**
     * @see \Common\Connection\Mysql::getColumnList()
     * @return array
     */
    public function loadColumns()
    {
       $this->columnList = $this->connection->getColumnList($this->database, $this->table);
    }

    /**
     * Populate the model from a similar model meaning the columname and modelname
     * match other than case
     *
     * @param mixed array | object
     */
    public function populate($values)
    {
        if(is_object($values)) {
            $vars = Introspection::getPublicVars($values);
        }

        if(is_array($values)) {
            $vars = $values;
        }
                
        foreach($vars as $member => $value){        

            if(is_scalar($value) || is_null($value)) {

                // if the value is already set do not unset it.  in order to reset a value you need to pass in "null" explictly
                // or use DBPersitable::NULL_VALUE
                $column = $this->determineColumn($member);
                if(isset($this->$column) && is_null($value)) {
                    continue;
                }

                $this->__set($member, $value);
            }
        }
    }

    /**
     * This will change the lookup key before performing update or related
     * searchs.
     * @param array $properties
     * @example array('myfield','myid')
     */
    public function setTempForceLookup(array $properties)
    {
        $mapped = array();
        foreach($properties as $field) {
            $mapped[] = $this->determineColumn($field);
        }

        $this->tempForceLookupCols = $mapped;
    }

    /**
     * Get the currently set records
     *
     * @return \stdClass
     */
    public function getRecord()
    {
        return Introspection::getPublicVars($this);
    }

    /**
     * Set the date fields on the persistence object unless they have already been set
     */
    public function setDateFields()
    {
        if(isset($this->columnList['DateAdded'])) {
            if(!empty($this->DateAdded)) {
                $this->DateAdded = $this->DateAdded;
            } else {

                /**
                 * @todo swap this out into the connection to let the connected server determine timezone settings
                 **/
                $Date = new \DateTime("now",new \DateTimeZone(self::DB_TIMEZONE));
                $this->DateAdded = $Date->format(self::SHORTDATE);
            }
        }
        if(isset($this->columnList['DateTimeAdded'])) {
            if(!empty($this->DateTimeAdded)) {
                $this->DateTimeAdded = $this->DateTimeAdded;
            } else {
                $Date = new \DateTime("now",new \DateTimeZone(self::DB_TIMEZONE));
                $this->DateTimeAdded = $Date->format(self::ISO_DATETIME);
            }
        }

        $this->updateLastUpdated();
    }

    /**
     * Update the last updated to column with the current time
     */
    public function updateLastUpdated()
    {
        if(isset($this->columnList['LastUpdated'])) {
            $Date = new \DateTime("now",new \DateTimeZone(self::DB_TIMEZONE));
            $this->LastUpdated = $Date->format(self::ISO_DATETIME);
        }
    }

    /**
     * insert this instance into the table
     */
    public function insert($dupeUpdate = true)
    {
        $this->setDateFields();

        $Stmt =  $this->connection->master()->autoInsert($this->tableWithDBPrefix(), $this, $dupeUpdate);
        $this->lastInsertId = $this->connection->master()->lastInsertId();

        if($this->lastInsertId !== "0") {
            $primary = $this->getPrimaryKey();
            if(count($primary) === 1) {
                $this->$primary[0] = $this->lastInsertId;
            }
        }        

        return $Stmt;
    }

    /**
     * update this instance in the table
     */
    public function update()
    {
        $this->updateLastUpdated();
        $where = $this->buildWhereFromPrimary();
        $result = $this->connection->master()->autoUpdate($this->tableWithDBPrefix(), $this, $where);
    }

    /**
     * Increment a column on the current record based on the offset
     * @param string $column
     * @param int $offset
     */
    public function increment($columns, $offset = 1)
    {
        if(is_scalar($columns)) {
            $columns = array($columns);
        }

        $this->updateLastUpdated();
        $where = $this->buildWhere();
        $master = $this->connection->master();
        $search = $master->buildSearchString($where);

        $updates = array();

        foreach($columns as $column) {
            $dbcolumn = $this->determineColumn($column);
            if(empty($dbcolumn)) {
                throw new \InvalidArgumentException("{$column} is not a valid column on the table");
            }

            $updates[] =  "{$dbcolumn} = COALESCE({$dbcolumn},0) + {$offset}";
        }
        $update = implode(",", $updates);

        $Stmt = $master->prepare("UPDATE {$this->tableWithDBPrefix()} SET {$update} {$search['query']}");
        $Stmt->execute($search['values']);
    }

    /**
     * Decrement a column on the current record based on the offset
     * @param $columns
     * @param int $offset
     */
    public function decrement($columns, $offset = 1)
    {
        if(is_scalar($columns)) {
            $columns = array($columns);
        }

        $this->updateLastUpdated();
        $where = $this->buildWhere();
        $master = $this->connection->master();
        $search = $master->buildSearchString($where);

        $updates = array();
        foreach($columns as $column) {
            $dbcolumn = $this->determineColumn($column);
            if(empty($dbcolumn)) {
                throw new \InvalidArgumentException("{$column} is not a valid column on the table");
            }

            $updates[] =  "{$dbcolumn} = COALESCE({$dbcolumn} - {$offset},0) ";
        }
        $update = implode(",", $updates);

        $Stmt = $master->prepare("UPDATE {$this->tableWithDBPrefix()} SET {$update} {$search['query']}");
        $Stmt->execute($search['values']);
    }

    /**
     * This will return true or false if the the column specified exists in the column list
     * after running the mapper on the input column
     *
     * @param string $column
     * @return boolean
     */
    public function hasColumn($column)
    {
        return ($this->determineColumn($column) ? true : false);
    }

    /**
     * Return if this partciluar instance has date related fields
     *
     * @return boolean
     */
    public function hasDateAddedField()
    {
        if(isset($this->columnList['DateAdded']) && $this->columnList['DateTimeAdded']) {
            return true;
        }
        return false;
    }

    /**
     * @return string
     */
    public function tableWithDBPrefix()
    {
        return "{$this->database}.{$this->table}";
    }

    /**
     * Determine the column that we should write to
     *
     * @param string
     * @return string
     */
    public function determineColumn($column)
    {
        if(empty($this->columnList)) {
            $this->loadColumns();
        }

        // if the column is exactly the same just return it
        if(isset($this->columnList[$column])) {
            return $column;
        }

        // check to see if the injected mapper function can solve our column lookup problem
        if(is_callable($this->mapper)) {
            $mapped = call_user_func_array($this->mapper, array($column));
            if($mapped) {
                return $mapped;
            }
        }

        // tabelize the column and see if we have a match and if so return the tabelized version
        $tablized = Introspection::tabelizeName($column);
        if(isset($this->columnList[$tablized])) {
            return $tablized;
        }

        // check to see if the column has a specific abbreviation in which case we uppercase the entire word ex SSN, DOB, CC
        $upperColumn = strtoupper($column);
        if(isset($this->columnList[$upperColumn])) {
            return $upperColumn;
        }

        // if we have a map array look it up there
        if(isset($this->mapArray[$column])) {
            return $this->mapArray[$column];
        }

    }

    /**
     * Set the map array for this persistable to use when looking up a respective db column to populate
     * a value to.
     *
     * @example array('inputName' => 'dbColumn');
     * @param array
     */
    public function setMapperArray($mapArray)
    {
        $this->mapArray = $mapArray;
    }

    /**
     * Set a callable mapper function that will lookup a column.  if you are not sure
     * what a callable is reference the link listed below to read more about them.
     *
     *  The function passed will receive the column to be mapped and should return
     *  the db column name.
     *
     * @example setMapper(function($column) { switch($column) case 'email': return 'emailAddress'; });
     * @link http://www.php.net/manual/en/language.types.callable.php
     * @param callable $mapper
     */
    public function setMapper($mapper)
    {
        if(!is_callable($mapper)) {
            throw new \InvalidArgumentException("passed in mapper is not a callable");
        }

        $this->mapper = $mapper;
    }

    public function getPrimaryKey()
    {
        if(!isset($this->primary)) {
            $this->primary = $this->connection->getPrimaryKey($this->database, $this->table);
        }

        return $this->primary;
    }

    public function getUniqueKey()
    {
        if(!isset($this->unique)) {
            $this->unique = $this->connection->getUniqueSearchKey($this->database, $this->table);
        }

        return $this->unique;
    }

    public function getLastInsertId(){
        return $this->lastInsertId;
    }

    /**
     * This will get the lookup key for this class starting with the manually
     * overriden index, primay, then unique search keys
     */
    public function getLookupKey()
    {
        if(isset($this->tempForceLookupCols)) {
            $lookup = $this->tempForceLookupCols;
            unset($this->tempForceLookupCols);
            return $lookup;
        }

        $primary = $this->getPrimaryKey();
        if(!empty($primary)) {
            return $primary;
        }

        $unique = $this->getUniqueKey();
        return $unique;
    }

    public function buildWhere()
    {
        $columns = $this->getLookupKey();
        $where = array();

        if(empty($columns)) {
            throw new \InvalidArgumentException("Can not perform lookup, the lookup keys could not be determined from table {$this->database}.{$this->table}");
        }

        foreach($columns as $key) {
            if(!isset($this->$key)) {
                $where[$key] = self::NULL_VALUE;
            } else {
                $where[$key] = $this->$key;
            }
        }

        return $where;
    }

    /**
     * Build where conditions using the primary key of the table
     *
     * @return string
     * @throws \InvalidArgumentException
     */
    public function buildWhereFromPrimary()
    {
        $primary = $this->getPrimaryKey();

        $where = array();
        if(!is_array($primary)) {
            throw new \InvalidArgumentException("Can not perform an update a primary key could not be found for {$this->database}.{$this->table}");
        }

        foreach($primary as $key) {
            if(!isset($this->$key)) {
                $where[$key] = self::NULL_VALUE;
            } else {
                $where[$key] = $this->$key;
            }
        }

        return $where;
    }

}
