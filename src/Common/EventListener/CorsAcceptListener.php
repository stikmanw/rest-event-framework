<?php
namespace Common\EventListener;

/**
 * Event Listener on the specific accept request that will allow CORS based
 * traffic to pass through.
 *
 */
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\GetResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class CorsAcceptListener implements EventSubscriberInterface
{
    /**
     * @var \Silex\Application
     */
    protected $app;

    /**
     * @var array
     */
    protected $acceptHeaders;

    /**
     * @param \Silex\Application $app
     */
    public function __construct($app, array $allowedHeaders = array())
    {
        $this->app = $app;
        $this->acceptHeaders = $allowedHeaders;
    }

    public static function getSubscribedEvents()
    {
        return array(
            KernelEvents::REQUEST => array('onRequestStart', 128)
        );
    }

    public function getDefaultAcceptHeaders()
    {
        return array("X-Requested-With", "Content-Type", "Accept");
    }

    public function getResponseHeaders()
    {
        return array(
            "Access-Control-Allow-Origin"=>"*",
            "Access-Control-Allow-Credentials" => "true",
            "Access-Control-Allow-Methods"=>"POST, GET, PUT, DELETE, OPTIONS"
        );
    }

    public function onRequestStart(GetResponseEvent $responseEvent)
    {
        $request = $responseEvent->getRequest();

        if($request->getMethod() !== "OPTIONS") {
            return;
        }

        $acceptHeaders = array_merge($this->getDefaultAcceptHeaders(), $this->acceptHeaders);
        $headerString = implode(", ", $acceptHeaders);

        $responseHeaders = $this->getResponseHeaders();
        $responseHeaders["Access-Control-Allow-Headers"] = $headerString;

        $responseEvent->setResponse(new Response("", 204, $responseHeaders));
    }

}