<?php
namespace Common\Provider;

use Common\Environment;
use Silex\Application;
use Silex\ServiceProviderInterface;

/**
 * Environment Provider that supplies information about the application state environment we are in
 * this includes the different environments we work on as engineers and environments specific
 * to the request like internal/external, application monitoring, ...and more as needed.
 *
 */
class EnvironmentProvider implements ServiceProviderInterface
{
    /**
     * Registers services on the given app.
     *
     * This method should only be used to configure services and parameters.
     * It should not get services.
     *
     * @param Application $app An Application instance
     */
    public function register(Application $app)
    {
        if (isset($app['environment'])) {
            $environment = $app['environment'];
        } else {
            $environment = null; 
        }

        $app['env'] = $app->share(
            function () use ($environment) {
                return new Environment($environment);

            }
        );

        $app['env.name'] = $app->share(
            function () use ($app) {
                return $app['env']->getName();
            }
        );

        if (isset($app['env.variables'])) {
            $app['env']->setVars($app['env.variables']);
        }

        if (isset($app['env.loader'])) {
            $app['env']->setLoader($app['env.loader']);
        }

        if (isset($app['env.loader'])) {
            $app['env']->setAppRootDir($app['env.application.root']);
        }

    }

    /**
     * Bootstraps the application.
     *
     * This method is called after all services are registered
     * and should be used for "dynamic" configuration (whenever
     * a service must be requested).
     */
    public function boot(Application $app)
    {
        $this->setupListeners($app);
        $app['env']->start();
    }

    /**
     * @param \Silex\Application $app
     */
    protected function setupListeners($app)
    {
        $app->before(
            function () use ($app) {
                if (isset($app['request'])) {
                    $app['env']->setRequest($app['request']);
                }
            }
        );

    }
}