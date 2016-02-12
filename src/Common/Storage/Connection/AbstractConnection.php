<?php
namespace Common\Storage\Connection;

abstract class AbstractConnection
{
    /**
     * @var array
     */
    protected $params;

    /**
     * @var array
     */
    protected $resources;

    /**
     * @var array
     */
    protected $hosts;

    /**
     * Make the connection to the adapter and apply any settings
     *
     * @param $params array
     */
    public function __construct($params = array())
    {
        $this->params = $params;
        $this->setup();
    }

    /**
     * Get a list of the current assigned parameters on this connection
     *
     * @return array
     */
    public function getParams()
    {
        return $this->params;
    }

    /**
     * Get the list of hosts that are applicable for this connection instance
     *
     * @param string $identifier slave/master
     * @return array
     */
    public function getRegisteredHosts($identifier = null)
    {
        if(!is_null($identifier) && isset($this->hosts[$identifier])) {
            return $this->hosts[$identifier];
        } else {
            return $this->hosts;
        }

    }

    /**
     * Replace the existing hosts and remove resources so next connect will use new host information.
     * @param $identifier
     * @param array $hosts
     */
    public function replaceHosts($identifier, array $hosts)
    {
        $this->hosts[$identifier] = array();
        foreach($hosts as $host) {
            if(is_string($host)) {
                $this->registerHost($identifier, $host);
            }

            if(is_array($host) && isset($host['host'])) {
                $port = $host['port'] ?: null;
                $this->registerHost($identifier, $host['host'], $port);
            }
        }

        $this->resources = array();
    }

    /**
     * register a new hostname as a particular type of identifier
     */
    public function registerHost($identifier, $host, $port = null)
    {
        if(!is_null($port)) {
            $host . ":" . $port;
        }

        // hosts[identifier] is an array and it already contains the host, return
        if( !empty($this->hosts)
            && isset($this->hosts[$identifier])
            && is_array($this->hosts[$identifier])
            && in_array($host, $this->hosts[$identifier]) ) {
                return;
        }

        $this->hosts[$identifier][] = $host;
    }

    /**
     * Split the host name into its port and host
     * @param $hostName
     */
    public function splitHostPort($hostName)
    {
        if(strpos($hostName, ":") !== false) {
            return explode(":", $hostName);
        }

        return array($hostName, null);
    }

}