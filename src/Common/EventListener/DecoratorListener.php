<?php
namespace Common\EventListener;

use Common\Decorator\CollectionJsonDecorator;
use Common\Decorator\ModelJsonDecorator;
use Common\Decorator\ObjectDecorator;
use Symfony\Component\HttpKernel\Event\FilterControllerEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Silex\Route;

/**
 * Determine the correct decorator to apply to the request based on the
 * matched route object.  Expressions can be used to change how the decorator
 * is determine.  You can also completely override the decorator resolver to
 * setup a custom resolver.  See getOptions for details about application
 * setting for this listener.
 */
class DecoratorListener implements EventSubscriberInterface
{
    protected $app;

    /**
     * @param $app
     */
    public function __construct($app)
    {
        $this->app = $app;
    }

    /**
     * @return array
     */
    public static function getSubscribedEvents()
    {
        return array(
            KernelEvents::CONTROLLER => array('onControllerStart', 128)
        );
    }

    /*
     * By default this class does not need any configuration settings setup
     * it will simply assume anything that ends with id / or is not specified
     * is returning a single resource.  search calls will always return a
     * collection of resources.
     * @param FilterControllerEvent $event
     * @throws \RuntimeException
     */
    public function onControllerStart(FilterControllerEvent $event)
    {
        $route = $event->getRequest()->get('_route');
        $route = $this->app['routes']->get($route);

        $options = $this->getOptions();
        foreach($options as $key => $value) {
            if(!isset($this->app[$key])) {
                $this->app[$key] = $value;
            }
        }

        if(isset($this->app['decorator.resolver']) && is_callable($this->app['decorator.resolver'])) {
           $decorator = $this->app['decorator.resolver']($route);
        } else {

            if(isset($this->app['decorator.resolver.expressions']) &&
                !empty($this->app['decorator.resolver.expressions'])) {

                $expressions = $this->app['decorator.resolver.expressions'];
            } else {

                $expressions = $this->getDefaultExpressions();
            }

            $decorator = $this->defaultResolver($route, $expressions);
        }

        if(! $decorator instanceof ObjectDecorator ) {
            throw new \RuntimeException("decorator resolver did not return a valid ObjectDecorator");
        }

        $this->app['response.decorator'] = $decorator;
    }

    /**
     * Get the list of expressions that will be used against the route path and
     * determine the type of decorator that will be used.
     * @return array
     */
    public function getDefaultExpressions()
    {
        return array(
            "#/search$#" => $this->app['decorator.multiple'],
            "#/\{id\}$#" => $this->app['decorator.single'],
            "#.*#" => $this->app['decorator.single']
        );
    }

    /**
     * Get a list of options that identify what can be set on the event listener.
     * @return array
     */
    public function getOptions()
    {
        return array(
            /**
             * This is a resolver used to return a decorator object to modify the output
             * of the response body. It is passed the route and additional expressions
             * set on the application object.
             *
             * @callback
             * @return ObjectDecorator
             */
            "decorator.resolver" => null,

            /**
             * Regular Expression to use for matching path information to a specific decorator
             * value should
             * key => expression
             * value => ObjectDecorator instance
             * see getDefaultExpressions for example
             */
            "decorator.resolver.expressions" => array(),

            /**
             * default decorator to use for single object response
             * @param ObjectDecorator
             */
            "decorator.single" => new ModelJsonDecorator(),

            /**
             * default decorator to use for a collection of objects response
             * @param ObjectDecorator
             */
            "decorator.multiple" => new CollectionJsonDecorator()
        );
    }

    /**
     * Built in decorator resolver for using
     * @param Route $route
     * @param array $expressions
     * @return mixed
     */
    protected function defaultResolver(Route $route, array $expressions)
    {
        $path = $route->getPath();
        foreach($expressions as $expression => $decorator) {
            if(preg_match($expression, $path)) {
                return $decorator;
            }
        }
        exit();
    }
}