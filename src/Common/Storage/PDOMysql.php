<?php
namespace Common\Storage;
/**
 * Extended PDO Wrapper with additional feature set.  Added some more specific settings
 * for our environment.
 *
 * @see \PDO
 */
use \PDO;
use Common\Tool\Introspection;
use Common\Storage\Exception\QueryException;

class PDOMysql extends PDO
{
    /**
     * @var string
     */
    protected $prepareStmt;

    /**
     * @var string
     */
    protected $userName;

    public function __construct($dsn, $user = null, $password = null, array $options = null)
    {
        $this->userName = $user;
        $this->dsn = $dsn;

        parent::__construct($dsn, $user, $password, $options);
        $this->setAttribute(PDO::ATTR_STATEMENT_CLASS, array('Common\Storage\PDOStatement',array()));
        $this->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // if the caller has not specifically set emulation for prepared statements then do not use since
        // we are on 5.1.59 that supports query caching of Native prepared statements

        if(isset($options[PDO::ATTR_EMULATE_PREPARES])) {
            $this->setAttribute(PDO::ATTR_EMULATE_PREPARES, $options[PDO::ATTR_EMULATE_PREPARES]);
        } else {
            $this->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
        }
    }

    public function ping()
    {
        $sql = "SELECT 1";

        try {
            $Stmt = $this->query($sql);
        } catch(\PDOException $e) {
            return false;
        }

        return true;

    }

    /**
     * Take a flat model and auto insert it into the table listed
     *
     * @param string $table name of the table to run the insert on
     * @param array | stdClass $insert associative array of variables to be inserted or class representing variables to be inserted.
     * @param boolean by default run on duplicate
     * @return \Common\Storage\PDOStatement
     * @throws \InvalidArgumentException
     */
    public function autoInsert($table, $insert, $onDupeUpdate = true)
    {
        if(empty($table)) {
            throw new \InvalidArgumentException("Table being referenced must be set.");
        }

        if(empty($insert)) {
            throw new \InvalidArgumentException("variables to be inserted must be set");
        }

        if(is_object($insert)) {
            $insert = Introspection::flattenToArray($insert);
        }

        $skipValues = array();

        foreach($insert as $columnName => $value) {
            if ($value === "NOW()" || $value === "CURDATE()" || $value === "NULL") {
                $set[] = "{$columnName} = {$value}";
                $skipValues[] = $columnName;
            } else {
                $set[] = '`' . $columnName . '` = ?';
            }
        }

        // need remove and null values from the update param s
        foreach($skipValues as $skipValue) {
            unset($insert[$skipValue]);
        }

        $input = implode(", ", $set);
        // build a query template, inserting placeholder ? instead of actual values. The ? are replaced when we call execute on the prepared statement.
        $query = 'INSERT INTO ' . $table . ' SET ' . $input;

        if($onDupeUpdate) {
            $query .= ' ON DUPLICATE KEY UPDATE ' . $input;
            $params = array_merge(array_values($insert), array_values($insert));
        } else {
            $params = array_values($insert);
        }

        try{
            $Stmt = $this->prepare($query);
        } catch(\Exception $e) {
            throw QueryException::create("Insert Query Prepare failed: " . $e->getMessage())->setQuery($this->getLastQuery());
        }

        $Stmt->execute($params);

        return $Stmt;

    }

    /**
     * This will handle doing a batch of records that come in as an array of object/array data
     * that will be wrote to the table specified.  On dupe update the query includes the necessary
     * data to make duplicate key updated on a batch of records.
     *
     * @param string $table
     * @param array $records
     * @param bool $dupeUpdate
     * @return \PDOStatement
     * @throws \InvalidArgumentException
     */
    public function insertMultiple($table, array $records, $dupeUpdate = true)
    {
        if(empty($table)) {
            throw new \InvalidArgumentException("Table being referenced must be set.");
        }

        // now records to write?
        if(empty($records)) {
            throw new \InvalidArgumentException("there were no new records to record to the table {$table}");
        }

        $columns = array_keys($records[0]);
        $query = 'INSERT INTO ' . $table . ' ( ' . $this->createColumnString($columns) . ' ) ';


        $dataValues = array();
        $multiInsert = array();

        // run through our records to handle batch updates of our new data
        foreach($records as $data) {

            if(is_object($data)) {
                $data = Introspection::flattenToArray($data);
            }

            $bindParams = array();

            foreach($data as $columnName => $value) {
                if ($value === "NOW()" || $value === "CURDATE()" || $value === "NULL") {
                    $bindParams[] = $value;
                } else {
                    $bindParams[] = "?";
                    $dataValues[] = $value;
                }
            }

            $multiInsert[] = "(" . implode(",", $bindParams) . ")";
        }

        $query .= " VALUES " . implode(",", $multiInsert);

        if($dupeUpdate) {
            $query .= ' ON DUPLICATE KEY UPDATE ' . $this->createOnUpdateString($columns);
        }

        try{
            $this->beginTransaction();
            $Stmt = $this->prepare($query);
        } catch(\Exception $e) {
            throw QueryException::create("Insert Query Prepare failed: " . $e->getMessage())->setQuery($this->getLastQuery());
        }

         $Stmt->execute($dataValues);
         $this->commit();

        return $Stmt;
    }

    /**
     * Updates the table with data from the data model.
     *
     * @param $table
     * @param $update
     * @param $where
     * @return \Common\Storage\PDOStatement
     * @throws \InvalidArgumentException
     */
    public function autoUpdate($table, $update, $where)
    {
        if(empty($table)) {
            throw new \InvalidArgumentException("Table being referenced must be set when using autoUpdate");
        }

        if(empty($update)) {
            throw new \InvalidArgumentException("variables to be inserted must be set when using autoUpdate");
        }

        if(empty($where)) {
            throw new \InvalidArgumentException('a where clause is expected to be set when using autoUpdate');
        }

        if(is_object($update)) {
            $update = Introspection::flattenToArray($update);
        }

        $skipValues = array();

        foreach ($update as $columnName => $value) {
        if ($value === "NOW()" || $value === "CURDATE()" || $value === "NULL") {
                $set[] = "{$columnName} =  {$value}";
                $skipValues[] = $columnName;
            } else {
                $set[] = '`' .$columnName . '` = ?';
            }
        }

        // need remove and null values from the update param s
        foreach($skipValues as $skipValue) {
            unset($update[$skipValue]);
        }

        $params = array_merge(array_values($update), array_values($where));
        $query = 'UPDATE ' . $table . ' SET ' . implode(", ", $set) . ' WHERE '  . implode(' = ? AND ', array_keys($where)) . ' = ? ';

        try{
            $Stmt = $this->prepare($query);
        } catch(\Exception $e) {
            throw QueryException::create("Update Query Prepare failed: " . $e->getMessage())->setQuery($this->getLastQuery());
        }

        try {
            $Stmt->execute(array_values($params));
        } catch (\PDOException $e) {
            switch($e->getCode()) {
                case '23000':
                        return $Stmt;

                case '40001':
                        $Stmt->execute(array_values($params));
                        break;
                default:
                    throw $e;
            }
        }

        return $Stmt;

    }

    /**
     * Automatically run a query and bind on the fly.  If specified return the result directly instead
     * of the PDOStatement
     *
     * @param string $query
     * @param array<mixed>
     * @param boolean $returnResult tell the statement to automatically return result set
     */
    public function autoSelect($query, $bindings = array(), $returnResult = true)
    {
        try {
            $Stmt = $this->prepare($query);

        } catch(\Exception $e) {
            throw QueryException::create("Select Query Prepare failed: " . $e->getMessage())->setQuery($this->getLastQuery());
        }

        $Result = $Stmt->execute($bindings);
        if(!$Result) {
            throw QueryException::create("Select Query Error occurred: " . $Stmt->errorInfo)->setQuery($Stmt->getLastQuery());
        }

        if(!$returnResult) {
            return $Stmt;
        }

        if($Result) {
            $count = $Stmt->rowCount();

            if($count > 1) {
                return $Stmt->fetchAll();
            }

            if($count == 1) {
                return $Stmt->fetch();
            }

            return false;

        }
    }

    /**
     * @see \PDO::query
     */
    public function query($statement)
    {
        $statement = $this->getQueryComment() . $statement;
        return parent::query($statement);
    }

    /**
     * @see \PDO::prepare
     */
    public function prepare($statement, $driverOptions = array())
    {
        $statement = $this->getQueryComment() . $statement;
        $this->prepareStmt = $statement;
        return parent::prepare($statement, $driverOptions);
    }

    /**
     * See the last prepare statement that was run on the connection
     * @return string
     */
    public function getLastQuery()
    {
        if(isset($this->prepareStmt)) {
            return $this->prepareStmt;
        }
    }

    /**
     * Build a search string out of a specially crafted array of values.
     * The operation is optional and will default to '=' (equals to)
     *
     * Basic Usage
     * @example array('fieldName' => '1);
     *
     * Advanced Usage
     * @deprecated
     * @example array(fieldName => array('value' => '10', 'operation' => '>=')
     * @return array("where" => "", "values" =>);
     */
    public function buildSearchStringOld($criteria, $operand = 'AND')
    {
        $values = array();
        foreach($criteria as $field => $mixed) {
            if(is_array($mixed)) {

                if(empty($mixed['value'])) {
                    throw new \Exception("When using a complex criteria you must specify the 'value' field as part of the criteria for a given field");
                }
                $values[]  = $mixed['value'];

                $operation = "=";
                if(empty($mixed['operation'])) {
                    $operation = "=";
                } else {
                    $operation = $mixed['operation'];
                }

                $inputSql[] = "{$field} {$operation} ?";
            } else {
                $inputSql[] = "{$field} = ?";
                $values = array_values($criteria);
            }
        }

        if(!empty($inputSql)) {
            return array("query" => "WHERE " . implode(" " . $operand . " ", $inputSql), "values" => $values);
        } else {
            return array();
        }
    }

    /**
     * Return a quoted list of columns that can be used for column queries using a column list
     * @param array $columns
     * @return string
     */
    public function createColumnString(array $columns, $escapeChar = "`")
    {
        $selfObject = $this;

        return implode(",",
            array_map(function($item) use ($selfObject, $escapeChar) {
                    return $selfObject->quoteIdentifier($item, $escapeChar);
                }, $columns)
        );
    }

    /**
     * In mysql you can use on duplicate key update as long as you use the VALUES() command
     * on the columns that are to be affected in this case we update all the columns.  There may
     * be some integrity issues if we hit unique keys during the update...that have to be handled in application
     * logic not here.
     *
     * @param array $columns
     * @param string $escapeChar
     * @return string
     */
    public function createOnUpdateString(array $columns, $escapeChar = "`") {

        $selfObject = $this;

        return implode(",",
            array_map(function($item) use ($selfObject,$escapeChar){
                    $escapedColumn = $selfObject->quoteIdentifier($item, $escapeChar);
                    return "{$escapedColumn} = VALUES({$escapedColumn})";
                }, $columns)
        );
    }

    /**
     * Quote an identifier that is not a value, like a column name or other DB specific content that is passed in dynamically
     * For quoting values you should should use PDO::quote()
     *
     * @param $identifier
     * @param string $escapeChar
     * @return string
     */
    public function quoteIdentifier($identifier, $escapeChar = "`")
    {
        // If the string is already quoted, return the string
        if (
            (($pos = strpos($identifier, $escapeChar)) !== FALSE  && $pos === 0) &&  // if the first character is our escape character
            (($pos = strrpos($identifier, $escapeChar)) !== false && ($pos === strlen($identifier)-1)) // if the last character is our escape character
        )
        {
            return $identifier;
        }

        // Split each identifier by the period
        $parts = explode('.', $identifier);

        // Return the escaped string
        return implode(
            '.',
            array_map(
                function ($piece) use ($escapeChar) {
                    return $escapeChar . $piece . $escapeChar;
                },
                $parts
            )
        );
    }

    /**
     * Build a search string out of a specially crafted array of values.
     * The operation is optional and will default to '=' (equals to)
     *
     * Basic Usage
     * @example array('fieldName' => '1);
     *
     * Advanced Usage
     * @example array(fieldName => array('value' => '10', 'operation' => '>=')
     * @return array("where" => "", "values" =>);
     */
    public function buildSearchString($criteria, $operand = 'AND')
    {
        if($operand == 'AND'){
            list($query,$values) = $this->buildConditionFromCriteria($criteria, false);
        } else {
            list($query,$values) = $this->buildConditionFromCriteria($criteria, true);
        }

        return array("query" => "WHERE " . $query, "values" => $values);
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
     *  @example $criteria = array('field' => 'test value');
     *  // different operation
     *  @example $criteria = array('field' => array('value' => 'test value', 'operation' => '<'));
     *  // in
     *  @example $criteria = array('field' => array('in' => array(1,2,3)));
     *  //range search
     *  @example $criteria = array('field' => array('between' => array('startValue','endValue'))
     *
     *  @param array list of criteria to create
     *  @param boolean what glue to use for expressions
     */
    protected function buildConditionFromCriteria($criteria, $or = false)
    {
        // if $or is set to true, lookup using OR instead of AND
        $operand = ($or === true) ? "OR" : "AND";

        $inputSql = array();
        $inputValues = array();
        foreach($criteria as $field => $mixed) {

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
     * Encodes debug information to beginning of the query
     * @return string
     */
    protected function getQueryComment()
    {
        return "/* " . $_SERVER['SCRIPT_NAME'] . " " . $this->userName . " " . $this->dsn . "*/ ";
    }

}