<?php
namespace Common\Controller;

use Common\Application;
use Common\Storage\Manager;
use Common\Tool\MysqlUtility;
use Silex\ControllerCollection;
use Symfony\Component\HttpFoundation\Request;

class AbstractController
{
    /**
     * @var Application
     */
    protected $app;

    public function __construct(Application $app)
    {
        $this->app = $app;
    }
}
