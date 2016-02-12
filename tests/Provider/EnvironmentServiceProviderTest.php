<?php
namespace Common\Tests\Provider;

use Common\Application;
use Common\Constants\Environment;
use Common\Provider\EnvironmentProvider;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class EnvironmentServiceProviderTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var \Silex\Application
     */
    protected $app;

    public function setup()
    {
        $this->app = new Application();
        $this->app->match("/", function(){ return new Response(); });

    }

    public function testDefault()
    {
       $this->app->register(new EnvironmentProvider(), array());
       $this->assertInstanceOf("Common\\Environment", $this->app['env']);
       $this->assertEquals(Environment::TEST, $this->app['env.name']);
    }

    public function testWithLoader()
    {
        $this->app['env.loader'] = $this->app->protect(function() {
            return $_SERVER;
        });

        $this->app->register(new EnvironmentProvider());
        $this->app->boot();

        $this->assertEquals($_SERVER, $this->app['env']->getVars());
    }

    public function testSetEnvVariable()
    {
        $this->app->register(new EnvironmentProvider());
        $this->app['env']->global = "myglobal";

        $this->assertEquals("myglobal", $this->app['env']->global);
    }

}
 