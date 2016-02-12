<?php
namespace Common\EventListener;

/**
 * Listener on the controllers that will take a result and put it into a JSON format
 *
 */
use Common\Decorator\ObjectDecorator;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\HttpKernel\Event\GetResponseForControllerResultEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Response;

class JsonResponseListener implements EventSubscriberInterface
{
    /**
     * @var \Silex\Application
     */
    protected $app;

    /**
     * @param \Silex\Application $app
     */
    public function __construct($app)
    {
        $this->app = $app;
    }

    /**
     * Handle converting the result into a valid JSON Response object back to the caller.
     *
     * @param GetResponseForControllerResultEvent $event The event to handle
     */
    public function onKernelView(GetResponseForControllerResultEvent $event)
    {
        $response = $event->getControllerResult();

        if($response instanceof Response) {
            $event->setResponse($response);
        }

        if(is_null($response)) {
            $event->setResponse(
                new Response(
                    '',
                    404,
                    $this->getResponseHeaders()
                )
            );
            return;
        }

        // we allow the response object that come back to be encoded by our default handler
        // or decorator if need be.
        if(is_object($response)) {

            $decorator = $this->getDecorator();

            if($decorator) {
                $decorator->setObject($response);
                $event->setResponse(
                    new Response($decorator->getResult(),
                        200,
                        $this->getResponseHeaders()
                    )
                );

                return;
            }

            if(is_callable($response, "toJson")) {
                $event->setResponse(
                    new Response(
                        $response->toJson(),
                        200,
                        $this->getResponseHeaders()
                    )
                );
                return;
            }
        }

        $event->setResponse(new JsonResponse($response));
    }

    public static function getSubscribedEvents()
    {
        return array(
            KernelEvents::VIEW => array('onKernelView', -10),
        );
    }

    public function getDecorator()
    {
        $app = $this->app;

        if(isset($app['response.decorator']) && $app['response.decorator'] instanceof ObjectDecorator) {
            return $app['response.decorator'];
        } else {
            return false;
        }
    }

    public function getResponseHeaders()
    {
        $app = $this->app;
        if(isset($app['response.headers']) && is_array($app['response.headers'])) {
            return $app['response.headers'];
        } else {
            return array("Content-Type" => "application/json");
        }
    }

}
