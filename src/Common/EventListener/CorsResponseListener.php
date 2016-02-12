<?php
namespace Common\EventListener;

/**
 * Event Listener on the specific accept request that will allow CORS based
 * traffic to pass through.
 *
 */
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Event\FilterResponseEvent;
use Symfony\Component\HttpKernel\Event\GetResponseEvent;
use Symfony\Component\HttpKernel\Event\GetResponseForControllerResultEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class CorsResponseListener implements EventSubscriberInterface
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
            KernelEvents::RESPONSE => array('onResponse', 10)
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

    public function onResponse(FilterResponseEvent $responseEvent)
    {
        $acceptHeaders = array_merge($this->getDefaultAcceptHeaders(), $this->acceptHeaders);
        $headerString = implode(", ", $acceptHeaders);

        $responseHeaders = $this->getResponseHeaders();
        $responseHeaders["Access-Control-Allow-Headers"] = $headerString;

        $response = $responseEvent->getResponse();
        $response->headers->add($responseHeaders);

        $responseEvent->setResponse($response);
    }

}