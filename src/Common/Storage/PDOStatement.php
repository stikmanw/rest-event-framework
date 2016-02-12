<?php
namespace Common\Storage;

/**
 * PDO Stamement that adds some additional functionality.. that I felt was missing
 * from the base  PDO class.
 *
 */
class PDOStatement extends \PDOStatement
{
    protected $_debugValues;
    protected $executionTime;

    private function __construct()
    {
        $this->setFetchMode(\PDO::FETCH_OBJ);
        // need this empty construct()!
    }

    public function execute($values = null)
    {
        if(is_object($values)) {
            $values = (array)$values;
        }

        if(is_array($values)) {
            foreach($values as $index => $value) {
                if(!is_scalar($value) && !is_null($value)) {
                    throw new \PDOException("Can not pass nested arrays into PDO execute statement. At index: {$index}");
                }
            }
        }

        // if we have parameters already bound and the array is not empty
        if(!empty($values)) {
            $this->_debugValues = $values;
        }

        try {
            $start = microtime(true);
            $t = parent::execute($values);
            $this->executionTime = microtime(true) - $start;
        } catch (\PDOException $e) {
            throw $e;
        }

        return $t;
    }

    /**
     * @see \PDOStatement::bindParam
     * @link http://www.php.net/manual/en/pdostatement.bindparam.php
     */

    public function bindParam($parameter, &$variable, $dataType=\PDO::PARAM_STR, $length=null, $driverOptions=null)
    {
        $this->_debugValues[$parameter] =  $variable;
        return parent::bindParam($parameter, $variable, $dataType, $length, $driverOptions);
    }

    public function bindValue($parameter, $variable, $dataType = \PDO::PARAM_STR)
    {
        $this->_debugValues[] = $variable;
        return parent::bindValue($parameter, $variable, $dataType);
    }

    /**
     * @return string
     */
    public function getLastQuery($replaced = true)
    {
        $q = $this->queryString;

        if(!empty($this->_debugValues)) {

            $assoc = (bool)count(array_filter(array_keys($this->_debugValues), 'is_string'));

            // this is statement is true if it is an indexed array
            if( $assoc === false) {

                $iterator = 0;
                $values = $this->_debugValues;
                $test = function($matches) use ($values, &$iterator) {
                    $newValue = "'" . $values[$iterator] . "'";
                    $iterator++;
                    return $newValue;
                };

                return preg_replace_callback('/\?/i', $test, $q);
            } else {
                return preg_replace_callback('/:([0-9a-z_]+)/i', array($this, '_debugReplace'), $q);
            }
        } else {
            return $this->queryString;
        }

    }

    /**
     * Return the (calculated) execution time for this statement.
     * null if not executed yet.
     * 
     * @return float
     */
    public function getExecutionTime()
    {
        return $this->executionTime;
    }

    protected function _debugReplace($m)
    {
        $v = $this->_debugValues[$m[1]];

        if ($v === null) {
            return "NULL";
        }

        if (!is_numeric($v)) {
            $v = str_replace("'", "''", $v);
        }

        return "'" . $v . "'";
    }

}