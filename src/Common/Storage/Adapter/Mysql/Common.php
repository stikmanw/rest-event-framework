<?php
namespace Common\Storage\Adapter\Mysql;

/**
 * Common Storage Adapter for Mysql Based models.
 *
 */
use Common\Storage\Adapter\AbstractAdapter;
use Common\Storage\Connection\Registry\MysqlConnectionRegistry;
use Common\Storage\DBPersistable;
use Common\Storage\DBPersistableMulti;
use Common\Tool\Introspection;
use Common\Storage\Connection\Mysql as MysqlConnection;
use Common\Storage\Adapter\AdapterInterface;
use Common\Model\BaseModel;
use Common\Exception;
use Common\Storage\Schema\ModelIndexes;

class Common extends AbstractAdapter implements AdapterInterface
{

    /**
     * Connection to the MySql database using our connection object
     *
     * @var MysqlConnection
     */
    protected $connection;

    /**
     * The database we are running our tables against.
     *
     *  @var string
     */
    protected $database;

    /**
     * Name of the database table that the model information will be persisted to.
     *
     * @var string
     */
    protected $modelTable;

    /**
     * Inferred name of the meta model table which is {ModelName}Meta
     * @var string
     */
    protected $modelMetaTable;

    /**
     * @var
     */
    protected $modelPersistable;

    /**
     * @var
     */
    protected $metaPersistable;

    /**
     * This method is executed when the class is constructed. Here are a list of the options
     * that can be specifically passed into our mysql common adapter the ones marked with an *
     * are required.
     *
     * database string name of the database to explicitly tell models to write to
     * metaModelName string name of a custom meta model to attach to the model
     * connection MysqlConnection
     *
     * @see AdapterAdapter->__construct()
     */
    protected function _setup() {

        // set adapter type
        $this->adapterType = 'mysql';

        foreach($this->settings as $name => $value) {

            switch($name) {
                case 'connection':
                case 'modelTableName':
                case 'metaModelTableName':
                    $this->$name = $value;
                    break;

                case 'database':
                    $this->setDatabaseName($value);
                    break;

            }
        }

        if(empty($this->database)) {
            throw new Exception\StorageException("Mysql adapter requires a database be set in settings: see database option");
        }
    }

    /**
     * Get the connection or lazy load a connection based on the adatper settings that have been passed in
     * @return MysqlConnection
     */
    public function getConnection()
    {
        if(empty($this->connection)) {
            $this->connection = MysqlConnectionRegistry::connect($this->settings);
        }

        return $this->connection;
    }

    /**
     * Assign a valid mysql connection to this adapter
     * @param MysqlConnection $conn
     */
    public function setConnection(MysqlConnection $conn)
    {
        $this->connection = $conn;
    }

    /**
     * Set the name of the table the model will be wrote to.
     * @param string $tableName
     */
    public function setModelTable($tableName)
    {
        $this->modelTable = $tableName;
    }

    /**
     * Get the name of the table the model will be saved to
     * @return string
     */
    public function getModelTable()
    {
        if(empty($this->modelTable)) {
            if(empty($this->modelTableName)) {
                $this->setModelTable($this->getModelName());
            } else {
                $this->setModelTable($this->modelTableName);
            }
        }
        return $this->modelTable;
    }

    /**
     * @param $tableName
     */
    public function setModelMetaTable($tableName)
    {
        $this->modelMetaTable = $tableName;
    }

    /**
     * @return string
     */
    public function getModelMetaTable()
    {
        if(empty($this->modelMetaTable)) {
            $this->modelMetaTable = $this->getMetaModelName();
        }

        return $this->modelMetaTable;
    }

    /**
     * @param $database
     * @internal param $dbName
     */
    public function setDatabaseName($database)
    {
        $this->database = $database;
    }

    /**
     * @return string
     */
    public function getDatabaseName()
    {
        return $this->database;
    }

    /**
     * Set a custom dbpersistable
     * @param DBPersistable $modelPersistable
     */
    public function setModelPersistable(DBPersistable $modelPersistable)
    {
        $this->modelPersistable = $modelPersistable;
    }

    /**
     * get the model persistable object used to save to the database
     * @param bool $new create a clone instance with similar settings
     * @return \Common\Storage\DBPersistable
     */
    public function getModelPersistable($new = true)
    {
        if(isset($this->modelPersistable)) {
            if($new) {
                return clone $this->modelPersistable;
            } else {
                return $this->modelPersistable;
            }
        } else {
            return new DBPersistable($this->getConnection(), $this->getDatabaseName(), $this->getModelTable());
        }
    }

    /**
     * Get the meta persistance object
     */
    public function getMetaPersistable()
    {
        return new DBPersistable($this->getConnection(), $this->getDatabaseName(), $this->getModelMetaTable());
    }

    /**
     * Write a series of models in one sql statement instead of multiple.
     *
     * This is currently not working with Models that contain meta information and individual writes
     * need to be performed.
     *
     * @todo add more support for meta models
     * @beta Currently limited tests against this method.
     * @param $Collection
     */
    public function writeBatch($Collection)
    {
        $MultiPersist = new DBPersistableMulti($this->getConnection(), $this->getDatabaseName(), $this->getModelTable());

        foreach($Collection as $Model) {
            if($Model->hasHash()) {
                $Model->hash = $Model->generateHash();
            }
        }

        $MultiPersist->populate($Collection);
        $MultiPersist->insert();
    }

    /**
     * Write a new database record, or update an existing one, based on the existing
     * column list passed in the app can determine which columns define uniqueness
     *
     * @param $Model
     * @param array $existingColumns - array of key:value to lookup for existance to update
     * @param boolean $skipLookup skip looking up the value before inserting on duplicateKey update
     * @return mixed
     */
    public function write($Model, $existingColumns = array(), $skipLookup = false)
    {
        $idName = $this->getModelIdName();

        // if we have existing columns to
        if(!empty($existingColumns)) {
            $criteria = $this->getCriteriaFromColumns($Model, $existingColumns);
        } else {
            $criteria = $this->getLookupCriteria($Model, $this->getModelTable());
        }

        // if we have specific existing criteria to lookup then use it otherwise assume the most unique search keys on the table.
        if(!empty($criteria) && !$skipLookup) {
            $Existing = $this->_findOne($this->getModelTable(), $criteria);
        } else {
            $Existing = false;
        }

        $Persistable = $this->getModelPersistable();
        $tableIdName = Introspection::tabelizeName($idName);

        // If we did not find an existing model then write the model record to the listening connection
        if(!$Existing) {

            $Persistable->populate($Model);

            if($Model->hasHash()) {
                $Persistable->hash = $Model->generateHash();
            }

            $Persistable->insert();

            if(empty($Persistable->$tableIdName)) {
                throw Exception\StorageException::create("The record did not write to the database as there is not a new id generated.")->setContext($Model, get_class($this));
            }

        } else {
            $Existing->populateDelta($Model);
            $Persistable->populate($Existing);
            if($Model->hasHash()) {
                $Persistable->hash = $Model->generateHash();
            }

            $Persistable->update();
            $Model->populate($Existing);
        }

        $Model->$idName = $Persistable->$tableIdName;
        $this->attachDateAddedFields($Model, $Persistable);

        // write the meta data if we have a model that has meta data.  I guess we may not always have one
        if($Model->hasMeta()) {


            $Meta = $this->writeMetaModel($Model);
            $Model->setMeta($Meta);
        }

        return $Model;
    }

    /**
     * Fix the meta model to write in a stateful manner.  This means each model has no more than a 1:1
     * mapping to their meta examples of this would be with Serviceability where each model instance should
     * only have 1 state at a time (Either it is serviceable or not)
     *
     * See the CommonDelta Adapter for writing multiple records per model in which each change to the meta is recorded
     * as a unique instance of the model.  This would hold true in something like products.
     *
     */
    public function writeMetaModel($Model)
    {
        // get the different ids for
        $modelId = $this->getModelIdName();
        $metaModelId = $this->getMetaIdName();

        // get the meta model that applies to the base model
        $MetaModel = $Model->getMeta();

        $MetaModel->$modelId = $Model->$modelId;

        if($MetaModel->hasHash()) {
            $MetaModel->hash = $MetaModel->generateHash();
        }

        if($this->statefulMeta) {
            $criteria = array($modelId => $MetaModel->$modelId);
        } else {
            if($MetaModel->hasHash()) {
                $criteria = array('hash' => $MetaModel->hash);
            } else {
                $criteria = array();
            }
        }

        /**
         * @todo look it up or just insert...on dupe update??? not sure revisit later
         */
        // if the criteria could not be built then we do not have an existing record and we more than likely need to create one
        if(!empty($criteria)) {
            $Existing = $this->findMeta($criteria);
        } else {
            $Existing = false;
        }

        $Persistable = $this->getMetaPersistable();
        $tableIdName = Introspection::tabelizeName($metaModelId);

        if(!$Existing) {
            $Persistable->populate($MetaModel);
            if($MetaModel->hasHash()) {
                $Persistable->hash = $MetaModel->generateHash();
            }
            $Persistable->insert();

            if(empty($Persistable->$tableIdName)) {
                throw Exception\StorageException::create("The meta record did not write to the database as there is not a new id generated.")->setContext($MetaModel, get_class($this));
            }
        } else {
            $Persistable->populate($Existing);
            if($MetaModel->hasHash()) {
                $Persistable->hash = $MetaModel->generateHash();
            }

            $Persistable->populate($MetaModel);
            $Persistable->update();
        }

        $MetaModel->$metaModelId = $Persistable->$tableIdName;
        $this->attachDateAddedFields($MetaModel, $Persistable);

        $this->writeMetaJson($Persistable, $MetaModel);
        return $MetaModel;
    }

    public function attachDateAddedFields(BaseModel $Model, DBPersistable $Persistable)
    {
        if($Persistable->hasDateAddedField()) {
            $column = $this->lookupModelColumn($Model, "dateAdded");
            if($column) {
                $Model->dateAdded = $Persistable->DateAdded;
            }

            $column = $this->lookupModelColumn($Model, "dateTimeAdded");
            if($column) {
                $Model->dateTimeAdded = $Persistable->DateTimeAdded;
            }
        }
    }

    /**
     * @param $Model
     */
    public function delete($Model)
    {
        $modelTable = $this->getModelTable();
        $modelIdName = $this->getModelIdName();

        $deleteSql = "DELETE FROM " . $this->getTableWithDBPrefix($modelTable) . " WHERE `" . $this->getModelIdName() . "` = ?";

        $stmt = $this->getConnection()->master()->prepare($deleteSql);
        $stmt->execute(array($Model->$modelIdName));
    }

    /**
     * Delete a bunch of records based on the passed in criteria
     *
     * @param \Common\Model\BaseModel $Model
     * @param $criteria
     * @return mixed|void
     */
    public function deleteBatch($Model, $criteria)
    {
        $criteria = $this->tablizeCriteria($criteria);
        list($conditions, $values) = $this->buildConditionFromCriteria($criteria);

        $modelTable = $this->getModelTable();
        if($Model->hasMeta()) {
            $metaTable = $this->getModelMetaTable();

            $sql = "SELECT " . $this->getModelIdName() . "
                FROM " . $this->getTableWithDBPrefix($modelTable) .
                " WHERE " . $conditions;

            $deletedIds = $this->getConnection()->master()->autoSelect($sql, $values);
            $deletedIds = (array)$deletedIds;
            $deletedIds = implode(",", $deletedIds);

            $deleteSql = "DELETE FROM " . $this->getTableWithDBPrefix($metaTable) . " WHERE " . $this->getModelIdName() . " IN(" . $deletedIds . ")";
            $this->getConnection()->master()->exec($deleteSql);
        }

        $deleteSql = "DELETE FROM " . $this->getTableWithDBPrefix($modelTable) . " WHERE {$conditions} ";
        $Stmt = $this->getConnection()->master()->prepare($deleteSql);
        $Stmt->execute($values);

        // Log this query
        // \Common\Tool\Logging\QueryLogger::addPdo($Stmt, get_called_class());

    }

    /**
     * Get a critiera set based on a column list and a model in the system.
     *
     * @param $Model
     * @param $columnList
     * @return array
     */
    public function getCriteriaFromColumns($Model, $columnList)
    {
        $criteria = array();
        foreach($columnList as $column) {
            if(isset($Model->$column)) {
                $criteria[$column] = $Model->$column;
            }
        }

        return $this->tablizeCriteria($criteria);
    }

    /**
     * Get the unique search lookup key on the model that we can use to identify if this record is a duplicate
     * or not.
    $criteria = array();
     *
     * @param \Common\Model\BaseModel
     * @param string name of the table the indexes apply to
     * @return array
     */
    public function getLookupCriteria($Model, $tableName)
    {
        $Index = null;
        if(method_exists($Model, "getIndexes")) {
            $Indexes = $Model->getIndexes();

            if($Indexes instanceof ModelIndexes) {
                $Index = $Indexes->getUniqueSearchKey();
            }

            if($Index) {
                $columns = $Index->getColumns();
            }
        }

        /**
         * @todo add application logging to this not that we are using the slow ass way of doing this
         * and we should explictly set the lookups in the model...in otherwords add getIndexes to your models
         * you lazy ass!!!
         */
        if(empty($columns)) {
            $columns = $this->getConnection()->getUniqueSearchKey($this->getDatabaseName(), $tableName);
        }

        if(empty($columns)) {
            throw Exception\StorageException::create("Expected a unique index to have columns to determine lookup criteria.")->setContext($Model, get_class($this));
        }

        $criteria = array();
        foreach($columns as $column) {

            if(($column == 'hash' || $column == 'Hash') && $Model->hasHash()) {
                $criteria[$column] = $Model->generateHash();
            } else {
                if(!empty($Model->$column)) {
                    $criteria[$column] = $Model->$column;
                }
            }
        }

        $criteria = $this->tablizeCriteria($criteria);

        return $criteria;
    }

    /**
     * @param $criteria
     * @param bool $or
     * @return BaseCollection
     */
    public function findAll($criteria, $or = false)
    {
        $criteria = $this->tablizeCriteria($criteria);
        $result = $this->_findAll($this->getModelTable(), $criteria, $or);
        return $result;
    }

    /**
     * Find a instance of the model passed in
     * @param $criteria
     * @param bool $or
     * @return BaseModel
     */
    public function findOne($criteria, $or = false)
    {
        $criteria = $this->tablizeCriteria($criteria);
        $result = $this->_findOne($this->getModelTable(), $criteria, $or);

        return $result;
    }

    /**
     * find one by the main table id
     *
     * @throws \RuntimeException
     * @internal param $criteria
     * @return BaseModel
     */
    public function findOneById()
    {
        $keys = $this->getConnection()->getPrimaryKey($this->getDatabaseName(), $this->getModelTable());
        if(empty($keys)) {
            throw new \RuntimeException(
                "Primary Key for {$this->getModelName()} could not be found verify indexes on model"
            );
        }

        $args = func_get_args();

        if(count($args) !== count($keys)) {
            throw new \RuntimeException("findOneBy expected: " . count($keys) . " value(s), " . count($args) ." received");
        }

        $criteria = array();
        foreach($keys as $i => $key) {
            $criteria[$key] = $args[$i];
        }
        return $this->findOne($criteria);
    }

    /**
     * @param $criteria
     * @param bool $or
     * @param null $limit
     * @param null $offset
     * @return BaseCollection
     */
    public function find($criteria, $or = false, $limit = null, $offset = null)
    {
        $criteria = $this->tablizeCriteria($criteria);
        $result = $this->_findAll($this->getModelTable(), $criteria, $or, $limit, $offset);

        return $result;
    }


    /**
     * @param $criteria
     * @param bool $or
     * @throws \Common\Exception\StorageException
     * @return bool
     */
    public function findMeta($criteria, $or = false)
    {
        $criteria = $this->tablizeCriteria($criteria);

        $factory = $this->getModelFactory();
        if(!$factory) {
            throw new Exception\StorageException("model factory instance creator has not been specified is not setup. ");
        }
        $metaModel = $factory->instance($this->getMetaModelName());
        return $this->_findOne($this->getModelMetaTable(), $criteria, $or, $metaModel);
    }

    /**
     * Get the full Database Qualified name in mysql
     * @param $table
     * @return string
     */
    public function getTableWithDBPrefix($table)
    {
        return "`{$this->getDatabaseName()}`.`{$table}`";
    }

    /**
     * @param \Common\Storage\PDOStatement $Stmt
     * @throws \Exception
     */
    public function raiseError($Stmt)
    {
        $err = $Stmt->errorInfo();
        if(!empty($err[1])) {
            throw new \Exception("Error occurred during model write: Debug" . print_r($err, true));
        }
    }

    /**
     * Turn the search criteria on a model to the respective db search columns
     *
     * @param array
     * @return array
     */
    protected function tablizeCriteria($criteria)
    {

        return $criteria;

        // deprecating this to see what breaks

        // remap the criteria to the correct DB format
        $mappedCriteria = array();

        if(!empty($criteria) || count($criteria) > 0) {
            foreach($criteria as $key => $value) {
                $colName = Introspection::tabelizeName($key);
                $mappedCriteria[$colName] = $value;
            }
        }

        return $mappedCriteria;
    }

    /**
     * Find one looks up a single row that matches criteria in the table specified.
     *
     * @param string $table
     * @param array $criteria
     * @param bool $or
     * @param null $Model
     * @throws \Common\Exception\AbstractExeption
     * @throws \Common\Exception\StorageException
     * @return bool|\Common\Model\BaseModel|null|object
     */
    protected function _findOne($table, $criteria, $or = false, $Model = null)
    {
        // if the caller has not passed in their own custom Model
        if(is_null($Model)) {
            $factory = $this->getModelFactory();
            if(empty($factory)) {
                throw new Exception\StorageException("model factory is not set can not create model for result");
            }
            $Model = $factory->instance($this->getModelName());
        }

        // do a lookup on the table for our model
        $Stmt = $this->prepareFindStatement($table, $criteria, $or);

        // fetch one record
        $Results = $Stmt->fetch();
        $Stmt->closeCursor();

        // do we have results?
        if($Results) {

            // instantiate the model, and map the rows to the model
            $Model = $this->mapDBRowToModel($Results, $Model);

            // if the model has a meta model then we need to look up also
            if($Model->hasMeta()) {

                $baseModelID = $this->getModelIdName();
                $metaModelID = $this->getMetaIdName();

                if($this->statefulMeta) {
                    $Meta = $this->findMeta(array($baseModelID => $Model->$baseModelID));
                } else {
                    if(array_key_exists("hash", $criteria) || array_key_exists("Hash", $criteria)) {
                        $hashValue = array_key_exists("hash", $criteria) ? $criteria['hash'] : $criteria['Hash'];
                        $Meta = $this->findMeta(array($baseModelID => $Model->$baseModelID, "hash" => $hashValue ));
                    } elseif(in_array($metaModelID, $criteria)) {
                        $Meta = $this->findMeta(array($baseModelID => $Model->$baseModelID, $metaModelID => $criteria[$metaModelID]));
                    } else {
                        throw Exception\StorageException::create("When looking up a meta model not set to a stateful meta you must specify a hash or metaid in the search criteria");
                    }
                }

                if($Meta) {
                    $Model->setMeta($Meta);
                }

            }

            // return our model
            return $Model;

        }

        return false;
    }

    /**
     * Find All looks up all records that match criteria in the specified table.
     *
     * @param $table
     * @param $criteria
     * @param bool $or
     * @param null $limit
     * @param null $offset
     * @throws \Common\Exception\StorageException
     * @return bool
     */
    protected function _findAll($table, $criteria, $or = false, $limit = null, $offset = null)
    {
        $factory = $this->getModelFactory();
        if(!$factory) {
            throw new Exception\StorageException("model factory is required to fetch a model from the system.");
        }

        $Model = $factory->instance($this->getModelName());
        if($Model->hasMeta()) {
            $Stmt = $this->prepareFindStatementWithMeta($table, $criteria, $or, $limit, $offset);
        } else {
            $Stmt = $this->prepareFindStatement($table, $criteria, $or, $limit, $offset);
        }

        // fetch all records
        $Results = $Stmt->fetchAll();

        // do we have results?
        if($Results) {

            $Collection = $this->collectionInstance();

            // iterate over rows, hydrate models, attach to our collection
            foreach($Results as $Row) {
                $Model = $factory->instance($this->getModelName());
                if($Model->hasMeta()) {
                    $MetaModel = $factory->instance($this->getMetaModelName());
                    $MetaModel = $this->mapDBRowToMetaModel($Row, $MetaModel);

                    unset($Row->Data);
                    $Model = $this->mapDBRowToModel($Row, $Model);
                    $Model->setMeta($MetaModel);

                } else {
                    $Model = $this->mapDBRowToModel($Row, $Model);
                }

                $Collection[] = $Model;
            }

            // return our collection
            return $Collection;
        }

        return false;
    }

    /**
     * This will prepare the find statement and run the query
     *
     * @see buildConditionFromCriteria for example of how to build criteria
     * @param string $table name of the table to search in
     * @param array $criteria
     * @param boolean $or
     * @param int $limit limit the results
     * @param in $offset start the results from specific point in result set
     * @throws \Common\Exception\AbstractExeption
     * @return \PDOStatement
     */
    protected function prepareFindStatement($table, $criteria = null, $or = false, $limit = null, $offset = null)
    {
        if(empty($table)) {
            throw Exception\StorageException::create("The table name to lookup for the model was empty.");
        }

        $Conn = $this->getConnection()->master();
        $db = $this->getDatabaseName();

        if (empty($criteria)) {
            $sql = "SELECT * FROM `{$db}`.`{$table}`";
        } else {
            list($condition, $values) = $this->buildConditionFromCriteria($criteria, $or);
            $sql = "SELECT * FROM `{$db}`.`{$table}` WHERE {$condition}";
        }

        if(is_int($limit)) {
            $sql .= " LIMIT {$limit}";
        }

        if(is_int($offset)) {
            $sql .= " OFFSET {$offset}";
        }

        // prepare and execute
        try {
            $Stmt = $Conn->prepare($sql);
            $Stmt->execute($values);

        } catch (\PDOException $e) {

            if($Stmt) {
                // recast this and throw again with more details for our top level handler
                throw Exception\QueryException::create("An issue occurred when attempting execute a find statement on the model." . $e->getMessage())->setQuery($Stmt->getLastQuery());
            } else {
                throw Exception\QueryException::create("An issue occurred when attempting execute a find statement on the model." . $e->getMessage())->setQuery($Conn->getLastQuery());
            }
        }


        return $Stmt;
    }

    /**
     * Prepare a find statement that will also pull in data from the meta model
     *
     * @param string $table name of the table to search in
     * @param array $criteria
     * @param boolean $or
     * @param null $limit
     * @param null $offset
     * @throws \Common\Exception\AbstractExeption
     * @return \PDOStatement
     */
    protected function prepareFindStatementWithMeta($table, $criteria, $or = false, $limit = null, $offset = null)
    {
        $Stmt = null;
        if(empty($table)) {
            throw Exception\StorageException::create("The table name to lookup for the model was empty.");
        }

        $metaTable = $this->getModelMetaTable();
        $joinId = $this->getModelIdName();

        $Conn = $this->getConnection()->slave();
        $db = $this->getDatabaseName();

        // validate criteria
        if(empty($criteria)) {
            throw Exception\StorageException::create("No criteria set when issuing a find statement.");
        }

        if (empty($criteria)) {
            $sql = "SELECT * FROM `{$db}`.`{$table}` LEFT JOIN `{$db}`.`{$metaTable}` USING({$joinId})";
        } else {
            list($condition, $values) = $this->buildConditionFromCriteria($criteria, $or, $table);
            $sql = "SELECT * FROM `{$db}`.`{$table}` LEFT JOIN `{$db}`.`{$metaTable}` USING({$joinId}) WHERE {$condition}";

        }

        if(is_int($limit)) {
            $sql .= " LIMIT {$limit}";
        }

        if(is_int($offset)) {
            $sql .= " OFFSET {$offset}";
        }

        // prepare and execute
        try {
            $Stmt = $Conn->prepare($sql);
            $Stmt->execute($values);

        } catch (\PDOException $e) {

            if($Stmt) {
                // recast this and throw again with more details for our top level handler
                throw Exception\QueryException::create("An issue occurred when attempting execute a find statement on the model." . $e->getMessage())->setQuery($Stmt->getLastQuery());
            } else {
                throw Exception\QueryException::create("An issue occurred when attempting execute a find statement on the model." . $e->getMessage())->setQuery($Conn->getLastQuery());
            }
        }

        return $Stmt;

    }

    /**
     * Build a where conditional from an array of critia where the keys in the
     * array represent the field to lookup and the values represent the type of lookup
     * to perform.
     *
     * By default a keyed scalar value will be an incusive lookup of each key there the field
     * is equal to the set scalar value.
     *
     * To add more functionality to can pass in a setup of specific options using operation and value functionality
     *
     *  // basic usage
     * @example $criteria = array('field' => 'test value');
     *  // different operation
     * @example $criteria = array('field' => array('value' => 'test value', 'operation' => '<'));
     *  // in
     * @example $criteria = array('field' => array('in' => array(1,2,3)));
     *  //range search
     * @example $criteria = array('field' => array('between' => array('startValue','endValue'))
     *
     * @param $criteria
     * @param bool $or
     * @param array list of criteria to create
     * @throws \Common\Exception\StorageException
     * @internal param \Common\Storage\Adapter\Mysql\what $boolean glue to use for expressions
     * @return array
     */
    protected function buildConditionFromCriteria($criteria, $or = false, $prefix = null)
    {
        // if $or is set to true, lookup using OR instead of AND
        $operand = ($or === true) ? "OR" : "AND";

        $inputSql = array();
        $inputValues = array();
        foreach($criteria as $field => $mixed) {

            $field = "`{$field}`";

            if($prefix) {
                if($prefix{1} !== "`") {
                    $prefix = "`{$prefix}`";
                }
                $field = "{$prefix}.{$field}";
            }

            if(is_array($mixed)) {

                // handle range queries
                if(isset($mixed['between']) && is_array($mixed['between'])) {
                    $inputSql[] = "{$field} BETWEEN ? AND ? ";

                    if(count($mixed['between']) !== 2) {
                        throw new Exception\StorageException("Critieria using between requires a 2 values for a range. " );
                    }
                    $inputValues[] = array_shift($mixed['between']);
                    $inputValues[] = array_shift($mixed['between']);
                    continue;
                }

                if(isset($mixed['in']) && is_array($mixed['in']) ) {

                    $placeHolder = array();
                    foreach($mixed['in'] as $val){
                        $placeHolder[] = "?";
                        $inputValues[] = $val;
                    }

                    $inputSql[] = "{$field} in (" . implode(',',$placeHolder). ")";
                    continue;
                }

                $operation = "=";
                if(empty($mixed['operation'])) {
                    $operation = "=";
                } else {
                    $operation = $mixed['operation'];
                }

                $inputSql[] = "{$field} {$operation} ?";
                $inputValues[] = $mixed['value'];


            } else {
                $inputSql[] = "{$field} = ?";
                $inputValues[] = $mixed;
            }
        }

        // decided to just make this a numeric indexed array for the purpose of using list in places it will likely be used.
        // should probably change this over to an expression builder class similar to that of doctrine.
        return array(implode(" {$operand} ", $inputSql), $inputValues);
    }

    /**
     * Map a Mysql Database row the the model instance populating it based on
     * look ups of matching column in many cases it may be beneficial for the child adapter to override this
     *
     * @param \stdClass
     * @param \Common\Model\BaseModel
     * @return \Common\Model\BaseModel
     */
    protected function mapDBRowToModel($Row, BaseModel $Model)
    {
        if(!empty($Row->Data)) {
            $Model->populateFromJson($Row->Data);
        }

        foreach($Row as $field => $value) {
            $column = $this->lookupModelColumn($Model, $field);

            if($column) {
                $Model->$column = $value;
            }
        }
        return $Model;
    }

    /**
     * Map a metaModel that contains potential json details back to the originating model
     *
     * @param $Row
     * @param \Common\Model\BaseModel $MetaModel
     * @internal param $ \stdClass
     * @internal param \Common\Model\BaseModel $Model
     * @return \Common\Model\BaseModel
     */
    protected function mapDBRowToMetaModel($Row, BaseModel $MetaModel)
    {
        // do the normal scalar mapping
        $Model = $this->mapDBRowToModel($Row, $MetaModel);

        return $Model;
    }

    /**
     * Lookup the name a similarly names column or use the mapping annotation
     * to determine the model column
     *
     * @todo AddMapper support
     * @param $Model
     * @param string $lookup name of the equivalent db column
     * @return string | false
     */
    protected function lookupModelColumn($Model, $lookup)
    {
        $modelData = $Model->toArray();
        foreach($modelData as $member => $value) {
            if(strtolower($lookup) === strtolower($member)) {
                return $member;
            }
        }

        return false;
    }

    /**
     * Write the JSON Data to the Mysql Database if the Database supports the database column
     * Data.  This is currently hardcoded to and in order to force a common convention I shall leave it
     * that way.
     *
     * @throws \InvalidArgumentException
     */
    protected function writeMetaJson($Persistable, $MetaModel)
    {
        // if the persistable data structure does not have a primary id set then we need to populate it from the model
        $Indexes = $MetaModel->getIndexes();
        $Primary  = $Indexes->getPrimary();
        $primaryKey = $Primary->getName();

        if(empty($Persistable->$primaryKey)) {
            $Persistable->$primaryKey = $MetaModel->$primaryKey;
        }

        if($Persistable->hasColumn('data')) {
            $json = $MetaModel->toJson();

            $Persistable->data = $json;
            $Persistable->update();
        }

        return $MetaModel;
    }

}
