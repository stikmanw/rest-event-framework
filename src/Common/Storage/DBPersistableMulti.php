<?php
namespace Common\Storage;

use Common\Storage\Connection\Mysql;
use Common\Tool\Introspection;

class DBPersistableMulti extends \ArrayObject
{
    /**
     * Setup this persistance model
     *
     * @param Common\Connection\Mysql $conn
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
     * @todo add accessors to mapper,functions,and column overrides internally useable on persistable
     */

    /**
     * Create a new instance of dbpersitable which does column mapping for each record.
     * @param array|\Traversable $records something that is
     */
    public function populate($records)
    {

        $this->inputRecords = $records;

        foreach($records as $record) {
            $persist = new DBPersistable($this->connection, $this->database, $this->table);
            $persist->populate($record);
            $persist->setDateFields();
            $this->append($persist->getRecord());
        }

    }

    /**
     * Insert the batch of records as one MySql query.
     *
     * @param bool $onDupeUpdate used to do on duplicate key update
     */
    public function insert($onDupeUpdate = true)
    {
        $master = $this->connection->master();

        $records = (array)$this;
        $master->insertMultiple($this->tableWithDBPrefix(), $records);

        $result = $this->connection->master()->autoSelect("SELECT LAST_INSERT_ID() as lastId");

        if($result->lastId !== "0") {

            $primary = $this->getPrimaryKey();
            if(count($primary) === 1) {
                $start = $result->lastId;
                foreach($this->inputRecords as $index => $record) {
                    $member = Introspection::modelizeName($primary[0]);
                    $record->$member = $start + $index;
                }
            }

        }
    }


    /**
     * Simple proxy to access underlying classes method foreach persistence object.
     * This useful for setting mappers and other persistable layer items across all instances
     * in our collection.
     *
     * @param $method
     * @param $params
     */
    public function proxy($method, $params)
    {
        $iterator = $this->getIterator();

        while($iterator->valid()) {
            call_user_func_array(array($iterator->current(), $method), $params);
            $iterator->next();
        }

    }

    public function getPrimaryKey()
    {
        if(!isset($this->primary)) {
            $this->primary = $this->connection->getPrimaryKey($this->database, $this->table);
        }

        return $this->primary;
    }

    /**
     * @return string
     */
    public function tableWithDBPrefix()
    {
        return "{$this->database}.{$this->table}";
    }
}