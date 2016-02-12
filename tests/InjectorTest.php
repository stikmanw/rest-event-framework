<?php
namespace Tests;

include '/usr/share/www/src/rope/core/bootstrap.php';

use Common\Injector;
use Rope\Model\Serviceable;
use Rope\Model\BaseModel;

class InjectorTest extends \PHPUnit_Framework_TestCase
{
    public $scope;

    public function setup()
    {
        $this->scope = new \stdClass();
        $this->scope->serviceable = new Serviceable;
        $this->scope->serviceable->companyId = 1;
        $this->scope->serviceable->serviceable = 1;
        $this->scope->scalar = 1;
        $this->scope->test = 'yep';
        $this->scope->myArray = array(1,2,3);
        $this->scope->simpleModel = new BaseModel;
    }


    public function testNewClassObjAndValMake()
    {
        $Injector = new Injector($this->scope);
        $Instance = $Injector->make('Tests\NewClassObjectAndValue');

        print_r($Instance);

    }

    public function testNewClassWithRef()
    {
        $Injector = new Injector($this->scope);
        $Instance = $Injector->make('Tests\NewClassWithReference');

        print_r($Instance);
        echo $this->scope->scalar;
    }

    public function testExecuteMethodString()
    {
        $Injector = new Injector($this->scope);
        $result = $Injector->execute("Tests\NewClassObjectAndValue::myMethod");

        print_r($result);

        $Injector = new Injector($this->scope);
        $result = $Injector->execute("Tests\NewClassObjectAndValue::myMethod");
        print_r($result);

    }

    public function testExecuteMethodCallable()
    {
        $Injector = new Injector($this->scope);
        $result = $Injector->execute(array("Tests\NewClassObjectAndValue", "myMethod"));

        print_r($result);

    }
}

class NewClassObjectAndValue
{
    protected $serviceable;
    protected $test;

    public function __construct($serviceable, $test)
    {
        $this->serviceable = $serviceable;
        $this->test = $test;
    }

    public function myMethod(Serviceable $model, &$myArray, $simpleModel)
    {
        $myArray[0] += 20;
        $model->dateAdded = date('Y-m-d H:i:s');
        $simpleModel->hash = "teststestt";

            return array($myArray, $model, $simpleModel);
    }
}

class NewClassWithReference
{
    protected $foo;

    public function __construct(&$scalar)
    {
        $scalar++;
        $scalar++;
        $this->foo =& $scalar;
    }

}
