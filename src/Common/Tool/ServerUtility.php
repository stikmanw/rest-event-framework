<?php
namespace Common\Tool;

class ServerUtility
{
    /**
     * This will determine the environment for ths system based on globally applied DEVELOPMENT_ENV variable
     * and host.
     *
     * @return string
     */
    public static function determineEnvironment()
    {
        if (defined('APPLICATION_ENV') ) {
            return APPLICATION_ENV;
        } elseif(isset($GLOBALS['APPLICATION_ENV'])) {
            return $GLOBALS['APPLICATION_ENV'];
        } else {
            trigger_error("APPLICATION_ENV should be set as system constant by webserver or by \$GLOBALS", E_USER_WARNING);
        }
    }

    public static function isLocal($env = null)
    {
        if (!$env) {
            $env = static::determineEnvironment();
        }

        if(defined('LOCALHOST')) {
            return true;
        }

        if(isset($GLOBALS['LOCALHOST'])) {
            return true;
        }

    }

    /**
     * Will return an environment based url for testing
     *
     * @example http://myapi.com/test/1
     *     localhost -  http://localhost/test/1
     *     test - http://test.myapi.com/test/1
     *     production - http://myapi.com/test/1
     *
     * @param $url
     * @param string $env [
     * @return mixed|string
     */
    public static function environmentizeUrl($url, $env = null)
    {
        if (!$env) {
            $env = static::determineEnvironment();
        }

        if (!preg_match("/^http/", $url)) {
            $url = "http://" . $url;
        }

        if ($env !== 'production') {

            $prefix = $env;
            if(static::isLocal($env) || $env === Con) {
                return preg_replace("#http(s)?://(\w+)/(\w+)#", "http$1://$prefix/$3", $url);
            }

            return preg_replace("#http(s)?://(\w+)#", "http$1://$prefix.$2", $url);
        }

        return $url;

    }

}
