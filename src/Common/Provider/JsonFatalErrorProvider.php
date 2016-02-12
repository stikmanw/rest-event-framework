<?php
namespace Common\Provider;

use Common\ErrorHandler;
use Silex\Application;
use Silex\ServiceProviderInterface;

class JsonFatalErrorProvider implements ServiceProviderInterface
{
    /**
     * Registers services on the given app.
     *
     * This method should only be used to configure services and parameters.
     * It should not get services.
     */
    public function register(Application $app)
    {
        ini_set("display_errors", 0);

        if (!isset($app['fatalerror.options'])) {
            $app['fatalerror.options'] = $this->getOptions();
        }

        if (!isset($app['fatalerror.handler'])) {

            $self = $this;
            $handler = function() use ($app, $self) {
               $self->defaultHandler($app);
            };
        } else {
            $handler = $app['fatalerror.handler'];
        }

        register_shutdown_function($handler);
        error_reporting($app['fatalerror.options']['level']);
    }

    /**
     * Bootstraps the application.
     *
     * This method is called after all services are registered
     * and should be used for "dynamic" configuration (whenever
     * a service must be requested).
     */
    public function boot(Application $app)
    {}

    public function getOptions()
    {
        return array(
            /**
             * Typical value you would pass into error_reporting
             * @var integer
             */
            "level" => E_ALL & ~E_NOTICE & ~E_DEPRECATED & ~E_WARNING,
        );
    }

    protected function defaultHandler(Application $app)
    {
        $error = error_get_last();
        if ($error['type'] == E_ERROR || $error['type'] == E_COMPILE_ERROR || $error['type'] == E_PARSE
            || $error['type'] == E_USER_ERROR || $error['type'] == E_RECOVERABLE_ERROR
        ) {

            /*
             * SOAP Errors are handled as exceptions so we need to ignore the fact that soap also places errors on the
             * PHP ErrorLog stack internally.
             */
            if (stristr($error['message'], 'SOAP-ERROR') !== false) {
                return;
            }

            if (isset($app['newrelic'])) {
                $app['newrelic']->noticeError(implode(":", $error));
            }

            ErrorHandler::sendJsonFatalError($error, $app->getAppName());
            exit();

        } else {
            /*
             * @todo add code to handle notices / warnings
             */
        }
    }

}