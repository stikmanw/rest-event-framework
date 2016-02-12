<?php
namespace Silex\Tests\EventListener;

use Common\Application;
use Common\EventListener\CorsAcceptListener;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class CorsAcceptListenerTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var $Request
     */
    public $request;

    /**
     * @var Application
     */
    public $app;

    public function setup()
    {
        $this->app = new Application();
        $this->app->match("/", function(){ return new Response(); });

        $this->request = new Request();
        $this->request->setMethod("OPTIONS");
    }

    public function testDefaultOptionsResponse()
    {
        $this->app['dispatcher']->addSubscriber(new CorsAcceptListener($this->app));
        $response = $this->app->handle($this->request);

        $this->assertArraySubset(array(
            "access-control-allow-origin" => array("*"),
            "access-control-allow-credentials" => array("true"),
            "access-control-allow-methods" => array("POST, GET, PUT, DELETE, OPTIONS"),
            "access-control-allow-headers" => array("X-Requested-With, Content-Type, Accept"),
            "content-type" => array("application/json")

        ), $response->headers->all());
    }

    public function testDifferentAcceptHeaders()
    {
        $this->app['dispatcher']->addSubscriber(new CorsAcceptListener($this->app, array("MyCustomToken")));
        $response = $this->app->handle($this->request);

        $this->assertArraySubset(array(
                "access-control-allow-origin" => array("*"),
                "access-control-allow-credentials" => array("true"),
                "access-control-allow-methods" => array("POST, GET, PUT, DELETE, OPTIONS"),
                "access-control-allow-headers" => array("X-Requested-With, Content-Type, Accept, MyCustomToken"),
                "content-type" => array("application/json")

        ), $response->headers->all());
    }

    public function testNonOptionsRequest()
    {
        $this->app['dispatcher']->addSubscriber(new CorsAcceptListener($this->app));
        $this->request->setMethod("GET");
        $response = $this->app->handle($this->request);

        $this->assertNull($response->headers->get("access-control-allow-headers"));
        $this->assertInstanceOf("Symfony\\Component\\HttpFoundation\\Response", $response);

    }
}
 