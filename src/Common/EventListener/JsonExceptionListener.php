<?php
namespace Common\EventListener;

/**
 * This will handle exceptions in a consistent way and return them in json as the class name implies.

 */

use Common\ErrorHandler;
use Symfony\Component\HttpKernel\Event\GetResponseForExceptionEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class JsonExceptionListener implements EventSubscriberInterface
{
    protected $app;

    public function __construct($app)
    {
        $this->app = $app;
    }

    public static function getSubscribedEvents()
    {
        return array(
           KernelEvents::EXCEPTION => array('onException', 10)
        );
    }

    public function onException(GetResponseForExceptionEvent $exceptionEvent)
    {
        $exception = $exceptionEvent->getException();

        if(isset($this->app['json.exception.handler'])) {
            $response = $this->app['json.exception.handler']($exception, $this->app);
        } else {
            $response = ErrorHandler::jsonExceptionError($exception, $this->app['appName']);
        }

        $exceptionEvent->setResponse($response);
    }
}
