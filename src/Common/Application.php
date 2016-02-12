<?php
namespace Common;
/**
 * Common application housing centralized methods attached the generic Silex/Application;
 * In most cases you should be extending the common to do your specific application common
 * functionality for your app.  If you have no need for those you can simply use the common
 * application as is.  The Application name will be generated using the current namespace "Common"
 * unless you explicitly set it using. Application Name. The name is used through controllers to
 * tell external/internal logging what application data this belongs to.
 *
 */
use Common\EventListener\CorsAcceptListener;
use Common\EventListener\CorsResponseListener;
use Common\EventListener\JsonExceptionListener;
use Common\EventListener\JsonParseRequest;
use Common\EventListener\JsonResponseListener;
use Common\Provider\EnvironmentProvider;
use Common\Provider\JsonFatalErrorProvider;
use Common\Tool\ServerUtility;
use NewRelic\Silex\NewRelicServiceProvider;
use Silex\Application as SilexApplication;
use Silex\ServiceProviderInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\KernelEvents;

class Application extends SilexApplication
{
    /**
     * Name of the application for internal/external identification
     * @var string
     */
    protected $name = "Common";

    /**
     * Environment name test,staging,production
     * @var string
     */
    protected $environment;

    /**
     * Fatal Error Handlers / shutdown function handler. Will default to common/errorhandler     *
     * @var callable
     */
    protected $fatalErrorHandler;

    /**
     * Custom exception handler called when an application exception is encountered will default to
     * generic handler in common
     * @var
     */
    protected $exceptionHandler;

    /**
     * This will handle the main processing of the response the controller returning
     * The specific event inside of
     *
     * This should be fired after the controller has returned internally the listener
     * should attach to one fo the following events based on application structure.
     *
     * Events:
     * kernel.view - response data was not an existing response (exception, model, scalar, array)
     * kernel.response - response was present modify as needed.
     *
     * The default listener will attach to the raw data and format it correctly to a JSON
     * object for various object types.
     *
     * @var EventSubscriberInterface
     */
    protected $responseListener;

    /**
     * This will handle the first processing of the request after it has been passed into
     * the handle call of the main application run statement.
     *
     * Events:
     * kernel.request - processed before the request is passed into the controller matched
     * by the route.
     *
     * The default listener loaded will parse the json request object validating for valid json
     * syntax.
     *
     * @var EventSubscriberInterface
     */
    protected $requestListener;

    /**
     * Configuration options for the application can be set here
     * @see Silex\Application
     * @param array $values
     */
    public function __construct(array $values = array())
    {
        if(isset($values['appName'])) {
            $this->setAppName($values['appName']);
        }

        if(isset($values['environment'])) {
            $this->setEnvironment($values['environment']);
        }

        parent::__construct($values);

        // setup the default providers supplied by the base application
        $this->setupProviders();
        $this->setupListeners($values);

    }

    /**
     * To support backward compatibility with directly referencing the request object
     * added a magic method wrapping $app->request;
     * @param $name
     * @return Request
     */
    public function __get($name)
    {
        if($name === "request") {
            return $this['request'];
        }
    }

    /**
     * This method is used for allowing the application to setup various providers to be attached to
     * the application as default providers.   These are called after construction of the application
     * and after
     * @internal param array $values
     */
    public function setupProviders()
    {
        // central provider for accessing environment variables and information
        $this->register(new EnvironmentProvider);

        // This will track all request through the newrelic php-agent if installed on the machine
        $this->register(new NewRelicServiceProvider(), array(
            "newrelic.options" => array(
                "application_name" => $this->getAppName(),
                "capture_params" => 1
            )
        ));

        // This allows the capture of fatal errors and responds in JSON details based on the application config
        $this->register(new JsonFatalErrorProvider());
    }

    /**
     * This is a base setup for the application broken into Request lifecycle
     * events.  Child applications can override specific listeners lifecycle to attach
     * their own subscribers or use the common JSON defaults setup out of the box.
     *
     * This method is called during the initialization of the application.
     *
     * @param array $values
     */
    public function setupListeners(array $values = array())
    {
         // KERNEL.REQUEST
        $this->registerRequestListeners($values);

        // KERNEL.CONTROLLER
        $this->registerPreControllerListeners($values);

        // KERNEL.VIEW
        $this->registerViewListeners($values);

        // KERNEL.RESPONSE
        $this->registerResponseListeners($values);

        // KERNEL.EXCEPTION
        $this->registerExceptionListeners($values);

        // KERNEL.FINISH_REQUEST
        $this->registerPostProcessListeners($values);
    }

    /**
     * Default listeners that fire before the request is passed into the controller
     * @see \Symfony\Component\HttpKernel\KernelEvents
     * @param array $values
     */
    public function registerRequestListeners(array $values = array())
    {
        $this['dispatcher']->addSubscriber(new JsonParseRequest($this));
        $this['dispatcher']->addSubscriber(new CorsAcceptListener($this));
    }

    /**
     * Called once the controller for the route has been matched.
     * @see \Symfony\Component\HttpKernel\KernelEvents
     * @param array $values
     */
    public function registerPreControllerListeners(array $values = array())
    {}

    /**
     * Fires if controller does not return valid response object
     * @see \Symfony\Component\HttpKernel\KernelEvents
     */
    public function registerViewListeners(array $values = array())
    {
        $this['dispatcher']->addSubscriber(new CorsResponseListener($this));
        $this['dispatcher']->addSubscriber(new JsonResponseListener($this));
    }

    /**
     * Fires when we have a valid symfony response object
     * @see \Symfony\Component\HttpKernel\KernelEvents
     * @param array $values
     */
    public function registerResponseListeners(array $values = array())
    {}

    /**
     * @see \Symfony\Component\HttpKernel\KernelEvents
     * @param array $values
     */
    public function registerExceptionListeners(array $values = array())
    {
        $this['dispatcher']->addSubscriber(new JsonExceptionListener($this));
    }

    /**
     * @see \Symfony\Component\HttpKernel\KernelEvents
     * @param array $value
     */
    public function registerPostProcessListeners(array $value = array())
    {}

    /**
     * Initialize the php runtime environment specific to our front index controller patter
     * for web services.
     *
     * This was replaced by the fatalErrorProvider allowing easier testing and extensibility
     *
     * @deprecated
     * @param int $displayErrors 1|0
     */
    public function setupFrontController($displayErrors = 0)
    {
        trigger_error("This method is no longer used, you should rely on the fatal error provider instead", E_USER_DEPRECATED);
    }

    /**
     * Magical naming scheme for controller based on application name
     *
     */
    public function controller($name, $method)
    {
       return  "\\" . $this->getAppName() . "\\Controller\\" . ucwords($name) . "Controller::" . $method;
    }

    /**
     * Setup Cross site scripting headers required for ajax calls from different domain.
     *
     * @param array $acceptHeaders
     * @internal param array $allowedHeaders list of allowed headers fo XSS
     * @return JsonResponse
     */
    public function setXSS($acceptHeaders = array())
    {
       trigger_error("set XSS is no longer used and will be removed in future versions", E_USER_DEPRECATED);
    }

    /**
     * Handler will return an error.  The last error array will be passed into the function
     * @see error_get_last()
     * @param callable $handler
     */
    public function setFatalErrorHandler($handler)
    {
        $this->fatalErrorHandler = $handler;
    }

    /**
     * Instead simply setup json.exception.handler or override the default listener for exceptions
     *
     * @deprecated
     * @param callable $handler
     */
    public function setExceptionErrorHandler($handler)
    {
        $this['json.exception.handler'] = $handler;
    }

    /**
     * Get the exception handler setup for this application or return the
     * common handler
     * @return callable
     */
    public function getExceptionHandler()
    {
        if(isset($this['json.exception.handler'])) {
            return $this['json.exception.handler'];
        } else {
            return null;
        }
    }

    /**
     * Set the name of the application
     * @param string $name
     */
    public function setAppName($name)
    {
        $this->name = $name;
    }

    /**
     * @return string
     *
     */
    public function getAppName()
    {
        return $this->name;
    }

    /**
     * Use the environment provider service directly instead
     * @deprecated
     */
    public function setEnvironment($name)
    {
        if(isset($app['env'])) {
            $this['env']->setEnvironment($name);
        } else {
            // load the default environment provider
            $this['environment'] = $name;
            $this->register(new EnvironmentProvider);
        }
    }

    /**
     * Will return the environment from the environment service provider
     * @return string
     */
    public function getEnvironment()
    {
        return $this['env.name'];
    }

    /**
     * This will pass all the override parameters into the serviceprovider prior to
     * creating the instance. This allows service providers to be setup via the additional
     * array instead of relying on application global state.
     * @param ServiceProviderInterface $provider
     * @param array $values
     * @return $this
     */
    public function registerOverride(ServiceProviderInterface $provider, array $values = array())
    {
        $this->providers[] = $provider;

        foreach ($values as $key => $value) {
            $this[$key] = $value;
        }

        $provider->register($this);

        return $this;
    }
}
