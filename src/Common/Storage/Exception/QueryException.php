<?php
namespace Common\Storage\Exception;

/**
 * This is specifically for query details
 *
 */
use Common\Exception\AbstractException;

class QueryException extends AbstractException
{
    private $query;

    public function setQuery($sql)
    {
        $this->query = $sql;
        return $this;
    }

    public function getQuery()
    {
        return $this->query;
    }
}