<?php
namespace Common\Provider\Session;

/**
 * Session write handler for writing to Redis for session data.
 * changed to use internal Redis connection class for session management.
 * Basically modified version of jrschumacher/symfony-redis-session-handler
 **
 */
use Common\Storage\Connection\Redis;

class RedisSessionHandler implements \SessionHandlerInterface
{
    /**
     * @var Redis
     */
    private $redis;

    /**
     * @var integer
     */
    private $lifetime;

    /**
     * @var array
     */
    private $options;

    /**
     * Constructor
     *
     * List of available options:
     *  * key_prefix: The key prefix [default: '']
     *
     * @param Redis $redis The redis instance
     * @param integer $lifetime Max lifetime in seconds to keep sessions stored.
     * @param array $options Options for the session handler
     *
     * @throws \InvalidArgumentException When Redis instance not provided
     */
    public function __construct(Redis $redis, $lifetime, array $options = array())
    {
        if (!$redis instanceof Redis) {
            throw new \InvalidArgumentException('Redis Common Connection instance required');
        }

        $this->redis = $redis->master();
        $this->lifetime = $lifetime;

        if(!is_array($options)) $options = array();
        $this->options = array_merge(array(
            'key_prefix' => ''
        ), $options);
    }

    public function open($savePath, $sessionName)
    {
        return true;
    }

    public function read($sessionId)
    {
        $key = $this->getKey($sessionId);
        return (string) $this->redis->get($key);
    }

    public function write($sessionId, $data)
    {
        $key = $this->getKey($sessionId);
        return $this->redis->setex($key, $this->lifetime, $data);
    }

    public function destroy($sessionId)
    {
        $key = $this->getKey($sessionId);
        return 1 === $this->redis->delete($key);
    }

    public function gc($lifetime)
    {
        return true;
    }

    public function close()
    {
        return true;
    }

    protected function getKey($sessionId)
    {
        if(is_string($this->options['key_prefix'])) {
            return $this->options['key_prefix'].$sessionId;
        }
        return $sessionId;
    }
}