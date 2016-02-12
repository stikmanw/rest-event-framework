<?php
namespace Common\Storage\Schema;

/**
 * This is an extension to the index that doctrine supplies. WE need to add the abililty
 * to support identifying the autoIncrement property of an index.  I think doctrine natively
 * supoprt AUTO_INCREMENT as a flag but to make it more apparent we can just set it in the constructor.
 *
 * @see \Doctrine\DBAL\Schema\Index

 *
 */
use Doctrine\DBAL\Schema\Index as DoctrineIndex;

class Index extends DoctrineIndex
{
    /**
     * The auto increment flag on this index is set or not set
     * @var boolean
     */
    protected $autoIncrement;

    public function __construct($indexName, array $columns, $isUnique = false, $isPrimary = false, $isAutoIncrement = false, array $flags = array())
    {
        $this->autoIncrement = $isAutoIncrement;
        parent::__construct($indexName, $columns, $isUnique, $isPrimary, $flags);
    }

    /**
     * Return the state of the autoincrement field
     * @return boolean
     */
     public function isAutoIncrement()
     {
           return $this->autoIncrement;
     }
}
