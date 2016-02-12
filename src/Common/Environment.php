<?php
namespace Common;
/**
 * Module class for information about the environment in which we are working in.
 *
 * Class Environment
 * @package Common\Module
 */

use Symfony\Component\HttpFoundation\Request;
use Common\Tool\ServerUtility;

class Environment
{
    /**
     * @var string
     */
    protected $environment;

    /**
     * Closure that can be used to change how the internal traffic is determined.
     * @var callable
     */
    protected $trafficMethod = null;

    /**
     * environment variables that can be set on the app at runtime
     * @var array
     */
    protected $variables = array();

    /**
     * Closure for loading environment variables into the class
     * @var
     */
    protected $loader = null;

    /**
     * @var bool
     */
    protected $isLocal;

    /**
     * @var string
     */
    protected $appRoot;

    /**
     * @var Request
     */
    protected $request;

    /**
     * @var string
     */
    protected $baseUrl;

    /**
     * Create the instance of the environment settings
     * @param string $environment
     */
    public function __construct($environment = null)
    {
        if(is_null($environment)) {
            $this->environment = $this->determineEnvironment();
        } else {
            $this->environment = $environment;
        }
    }

    public function __get($key)
    {
        return isset($this->variables[$key]) ? $this->variables[$key] : null;
    }

    public function __set($key, $value)
    {
        $this->variables[$key] = $value;
    }

    public function getName()
    {
        return $this->environment;
    }

    public function setName($name)
    {
        $this->environment = $name;
    }

    public function setRequest(Request $request)
    {
        $this->request = $request;
    }

    public function getRequest()
    {
        return $this->request;
    }

    public function getAppRootDir()
    {
        return $this->appRoot;
    }

    public function setAppRootDir($directory) {
        if(is_dir($directory)) {
            $this->appRoot = $directory;
        } else {
            throw new \InvalidArgumentException($directory . " is not a valid directory");
        }
    }

    public function setIsLocal($isLocal)
    {
        $this->isLocal = $isLocal;
    }

    public function getIsLocal()
    {
        if(!$this->isLocal) {
            $this->isLocal = ServerUtility::isLocal($this->environment);
        }

        return $this->isLocal;
    }

    public function setVars(array $vars = array())
    {
        $this->variables = array_merge($this->variables, $vars);
    }

    public function getVars()
    {
        return $this->variables;
    }

    public function setLoader(callable $method)
    {
        $this->loader = $method;
    }

    /**
     * Set the base url for the api services
     * @param string $url
     */
    public function setBaseUrl($url)
    {
        $this->baseUrl = $url;
    }

    /**
     * Get the base url used for api calls
     * @param boolean $prefixWithEnv set to true to get the result back with environment encoded.
     * @return string
     */
    public function getBaseUrl($prefixWithEnv = false)
    {
        if($this->baseUrl && $prefixWithEnv) {
            return $this->environmentizeUrl($this->baseUrl);
        }

        return $this->baseUrl;
    }

    /**
     * Determines where the code is running on a local engineer machine.
     * @return boolean
     */
    public function isLocal()
    {
        return $this->getIsLocal();
    }

    /**
     * @return bool
     */
    public function isTest()
    {
        return ($this->getName() === \Common\Constants\Environment::TEST);
    }

    /**
     * @return bool
     */
    public function isProduction()
    {
        return ($this->getName() === \Common\Constants\Environment::PRODUCTION);
    }

    public function environmentizeUrl($url)
    {
        return ServerUtility::environmentizeUrl($url, $this->environment);
    }

    /**
     * This will start the environment up and load the variables for the
     * environment that have been passed in.
     * @return void
     */
    public function start()
    {
        if(is_callable($this->loader)) {
            $method = $this->loader;
            $result = $method();

            if(is_array($result)) {
                foreach($result as $key => $value) {
                    $this->variables[$key] = $value;
                }
            }
        }
    }

    /**
     * This allows override of how environment information is loaded into this
     * class.
     *
     * @return string
     */
    protected function determineEnvironment()
    {
        return ServerUtility::determineEnvironment();
    }

}