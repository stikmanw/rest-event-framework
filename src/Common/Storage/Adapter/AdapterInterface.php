<?php
namespace Common\Storage\Adapter;

interface AdapterInterface
{

    /**
     * @return string
     */
    function getAdapterType();

    /**
     * Write the model to the persistent storage engine.
     *
     * @param $Model
     * @param array $existingColumns set of columns used for determining an existing check
     * @return $Model
     */
    function write($Model,  $existingColumns = array());

    /**
     * @return connection to a resource the adapter uses to persist data
     */
    function getConnection();

    /**
     * Removes a list of records + meta based on passed in array of criteria
     *
     * @param $Model
     * @param $criteria
     * @return mixed
     */
    function deleteBatch($Model, $criteria);

}
