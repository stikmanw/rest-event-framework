<?php
namespace Common\Storage\Schema;

/**
 * A collection of for a model indexes
 *
 */
use Common\Collection\BaseCollection;

class ModelIndexes extends BaseCollection
{
    /**
     * Get the primary index listed on the model
     *
     * @return Index
     */
    public function getPrimary()
    {
        foreach($this as $Index)
        {
            if($Index->isPrimary()) {
                 return $Index;
             }
        }

    }

    /**
     * Get the unique index list for the model
     *
     * @return \Common\Collection\ModelIndexes
     */
    public function getUnique()
    {
        $collection = new ModelIndexes;
        foreach($this as $Index) {
            if($Index->isUnique()) {
                $collection->append($Index);
            }
        }

        return $collection;
    }

    /**
     * Get the auto increment column for this model if it is set.
     *
     * @return \Common\Storage\Schema\Index
     */
    public function getAutoIncrement()
    {
        foreach($this as $Index) {
            if($Index->isAutoIncrement()) {
                return $Index;
            }
        }
    }

    /**
     * see if the property has an index on it.
     *
     * @return boolean
     */
    public function hasIndex($property)
    {
        foreach($this as $Index) {
            $columns = $this->getColumns();
            if(in_array($column, $columns)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Find the index by the name of the index
     *
     * @return \Common\Storage\Schema\Index
     */
    public function getIndexByName($name)
    {
        foreach($this  as $Index) {
            if($name === $Index->getName()) {
                return $Index;
            }
        }

        return false;
    }

    /**
     * Get the unique search key that from the colleciton of indexes
     *
     * @return \Common\Storage\Schema\Index
     */
    public function getUniqueSearchKey()
    {
        $uniqueList = $this->getUnique();

        $uniquest = array();
        $numColumns = 0;
        foreach($uniqueList as $unique) {
            if(count($unique->getColumns()) > $numColumns) {
                $uniquest = $unique;
            }
        }

        return $uniquest;
    }
}