<?php
namespace Common\Storage\Connection;

Interface ConnectionInterface
{
    /**
     * At this point the params are setup so setup the class based on
     * passed in configuration.
     * @return mixed
     */
    function setup();

    /**
     * Establish a connection to the list resources in
     *
     * @param string $identifier
     */
     function connect($identifier);

    /**
     * Destroy existing connections forcing a reconnect next time an access command is called.
     */
     function close();

    /**
     * Get the master persisting resource
     *
     * @return mixed
     */
     function master();

    /**
     * Get the slave peristing resource
     *
     * @return mixed
     */
     function slave();

    /**
     * Check the adapter object resource to make sure it is still valid
     *
     * @param $resource
     * @return boolean
     */
    function isValidResource($resource);

   /**
    * determine how the slave is selected
    *
    * @return string hostname of the slave
    */
    function determineSlave();

    /**
     * Set an identifier for a given hostname that we are going to connect
     * to.
     *
     * @example $Connection->registerHost('master', 'rv-atl-master01');
     */
    function registerHost($identifier, $host);
}