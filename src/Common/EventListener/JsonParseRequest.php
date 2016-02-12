<?php
namespace Common\EventListener;

/**
 * This is used to verify the json data coming in to validate it is indeed proper
 * json and issue an appropriate error when it is not.
 *
 */

use Common\ErrorHandler;
use Seld\JsonLint\JsonParser;
use Seld\JsonLint\ParsingException;
use Symfony\Component\HttpKernel\Event\GetResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class JsonParseRequest implements EventSubscriberInterface
{
    protected $app;

    public function __construct($app)
    {
        $this->app = $app;
    }

    public static function getSubscribedEvents()
    {
        return array(
           KernelEvents::REQUEST => array('onRequestStart', 128)
        );
    }

    public function onRequestStart(GetResponseEvent $responseEvent)
    {
        $request = $responseEvent->getRequest();
        $jsonContent = $request->getContent();

        if(empty($jsonContent)) {
           return;
        }

        $parser = new JsonParser();
        $result = $parser->lint($jsonContent);

        if($result instanceof ParsingException) {
            $appPrefix = null;

            if(is_callable(array($this->app, "getClientName"))) {
                $appPrefix = ucwords($this->app->getClientName());
            }

            if(is_callable(array($this->app, "getAppName"))) {
                $appPrefix = ucwords($this->app->getAppName());
            }

            /**
             * @todo make use configurable DI injected error handler provider
             */
            $responseEvent->setResponse(ErrorHandler::jsonExceptionError($result, $appPrefix));

        }

    }


}