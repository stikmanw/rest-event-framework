<?php
namespace Common\Storage\Connection;

use Common\Storage\Exception\ConnectionException;
use Common\Tool\ServerUtility;
use \Redis as PHPRedis;

/**
 * @todo update configuration format to be closer to MySQL configuration
 * Class Redis
 * @package Common\Storage\Connection
 */
class Redis extends AbstractConnection implements ConnectionInterface
{
    /**
     * @var
     */
    protected $authPassword;

    /**
     * @var
     */
    protected $timeout = 1;

    /**
     * List of options to pass into redis
     * @var array
     */
    protected $options;

    /**
     * @return int
     */
    public function getTimeout()
    {
        return $this->timeout;
    }

    /**
     * Setup the hosts based on the configuration file
     * @throws \Exception
     * @return mixed|void
     */
    public function setup()
    {
        if(empty($this->params['hostGroup'])) {
            $this->params['hostGroup'] = 'default';
        }

        if(empty($this->params['environment'])) {
           $this->params['environment'] = ServerUtility::determineEnvironment();
        }

        if(isset($this->params['timeout'])) {
            $this->timeout = $this->params['timeout'];
        }

        if($this->params['forceLocalhost']) {
            $this->registerHost('master', '127.0.0.1:6379');
            return;
        }

        $config = $this->getConfig();

        $serverGroups = $config['servergroup'];

        if(!isset($serverGroups[$this->params['hostGroup']])) {
            throw new \Exception('hostgroup is not a valid group setup in the configuration');
        }

        $servers = $serverGroups[$this->params['hostGroup']];
        $this->setMaster($servers);
        $this->setSlaves($servers);
    }

    /**
     * Get the array of server configuration data from disk or from the config options passed in.
     * @throws \RuntimeException
     * @return mixed
     */
    public function getConfig()
    {
        if(isset($this->params['config'])) {
            return $this->params['config'];
        }

        if(isset($this->params['configPath'])) {
            if(!is_file($this->params['configPath'])) {
                throw new \RuntimeException("configPath parameter is not a valid file path");
            }
        }

        $contents = file_get_contents($this->params['configPath']);
        return json_decode($contents, true);
    }

    /**
     * Add the server to be used as the master connection
     * @param $servers
     */
    public function setMaster($servers)
    {
        $master = $servers["{$this->params['environment']}_master"];
        if(is_array($master)) {
          $master = $master[0];
        }

        $this->registerHost('master', $master);
    }

    /**
     * Add the servers to be used as slaves
     * @param $servers
     * @throws \RuntimeException
     */
    public function setSlaves($servers)
    {

        $slaves = $servers["{$this->params['environment']}_slave"];
        if(is_string($slaves)) {
            $this->registerHost('slave', $slaves);
        }

        if(is_array($slaves)) {
            foreach($slaves as $slave) {
                $this->registerHost('slave', $slave);
            }
        }
    }

    public function connect($identifier = false)
    {
        if(!extension_loaded("redis")) {
            throw new \Exception("Redis extension is not installed can not use this connection class");
        }

        if(empty($this->hosts)) {
            throw new \DomainException("Failed to connect to redis servers missing valid hosts");
        }

        $master = $this->getRegisteredHosts('master');

        switch($identifier) {

            case 'master':
                list($host, $port) = $this->splitHostPort($master[0]);
                $this->resources['master'] = new PHPRedis();
                $this->resources['master']->connect($host, $port, $this->timeout, null, 100);

                if(!$this->resources['master']) {
                    throw new ConnectionException("Failed to connect to host: {$host} master redis server");
                }
                return $this->resources['master'];

            case 'slave':
                $slave = $this->determineSlave();
                list($host, $port) = $this->splitHostPort($slave);
                $this->resources['slave'] = new PHPRedis();
                $this->resources['slave']->connect($host, $port, $this->timeout, null, 100);

                if(!$this->resources['slave']) {
                    throw new ConnectionException("Failed to connect to host: {$host} slave redis server");
                }

                return $this->resources['slave'];
        }
    }

    /**
     * Destroy existing connections forcing a reconnect next time an access command is called.
     */
    function close()
    {
        /* @todo close resource */
    }

    /**
     * Get the master persisting resource
     *
     * @return \Redis
     */
    function master()
    {
        if(!$this->isValidResource($this->resources['master'])) {
            return $this->connect('master');
        }

        return $this->resources['master'];
    }

    /**
     * Get the slave peristing resource
     *
     * @return mixed
     */
    function slave()
    {
        if(!$this->isValidResource($this->resources['slave'])) {
            return $this->connect('slave');
        }

        return $this->resources['slave'];
    }

    /**
     * Check the adapter object resource to make sure it is still valid
     *
     * @param $resource
     * @return boolean
     */
    function isValidResource($resource)
    {
        if (!$resource instanceof PHPRedis) {
            return false;
        }

        return true;
    }

    /**
     * determine how the slave is selected
     *
     * @return string hostname of the slave
     */
    function determineSlave()
    {
        $hosts = $this->getRegisteredHosts('slave');
        shuffle($hosts);
        return current($hosts);
    }

}