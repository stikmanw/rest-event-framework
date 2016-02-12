<?php
namespace Common\Tests\Decorator;

use Common\Decorator\ModelJsonDecorator;
use Common\Model\BaseModel;

class ModelJsonDecoratorTest extends \PHPUnit_Framework_TestCase
{
    public function testBaseModel()
    {
        $model = new BaseModel();
        $decorator = new ModelJsonDecorator();
        $decorator->setObject($model);
        $this->assertEquals(
            "{\"dateAdded\":null,\"dateTimeAdded\":null,\"lastUpdated\":null,\"___type\":\"Common\\\\Model\\\\BaseModel\"}",
            $decorator->getResult()
        );
    }

    public function testToString()
    {
        $model = new BaseModel();
        $decorator = new ModelJsonDecorator();
        $decorator->setObject($model);
        $result = sprintf($decorator->getResult());
        $this->assertEquals(
            '{"dateAdded":null,"dateTimeAdded":null,"lastUpdated":null,"___type":"Common\\\\Model\\\\BaseModel"}',
            $result
        );
    }

    public function testDataGroup()
    {
        $model = new BaseModel();
        $decorator = new ModelJsonDecorator();
        $decorator->setObject($model);
        $decorator->setDataGroup("data");
        $this->assertEquals(
            '{"data":{"dateAdded":null,"dateTimeAdded":null,"lastUpdated":null,"___type":"Common\\\\Model\\\\BaseModel"}}',
            $decorator->getResult()
        );
    }

    public function testNoEmpty()
    {
        $model = new BaseModel();
        $decorator = new ModelJsonDecorator();
        $decorator->setObject($model);
        $decorator->showEmpty(false);
        $this->assertEquals(
            '{"___type":"Common\\\\Model\\\\BaseModel"}',
            $decorator->getResult()
        );
    }

    public function testDontShowType()
    {
        $model = new BaseModel();
        $model->message = "test";
        $decorator = new ModelJsonDecorator();
        $decorator->setObject($model);
        $decorator->showType(false);
        $decorator->showEmpty(false);
        $this->assertEquals(
            '{"message":"test"}',
            $decorator->getResult()
        );
    }

    public function testExtraInfo()
    {
        $model = new BaseModel();
        $model->message = "test";
        $decorator = new ModelJsonDecorator();
        $decorator->setObject($model);
        $decorator->showType(false);
        $decorator->showEmpty(false);
        $decorator->setExtraInfo(array("debug" => "true"));
        $this->assertEquals(
            '{"debug":"true","body":{"message":"test"}}',
            $decorator->getResult()
        );
    }

    public function testExtraInfoWithGroup()
    {
        $model = new BaseModel();
        $model->message = "test";
        $decorator = new ModelJsonDecorator();
        $decorator->setObject($model);
        $decorator->showType(false);
        $decorator->showEmpty(false);
        $decorator->setDataGroup("data");
        $decorator->setExtraInfo(array("debug" => "true"));
        $this->assertEquals(
            '{"debug":"true","data":{"message":"test"}}',
            $decorator->getResult()
        );
    }

    public function testGenericContainer()
    {
        $model = new BaseModel();
        $model->message = "hello";
        $decorator = new ModelJsonDecorator();
        $decorator->setObject($model);
        $decorator->showEmpty(false);
        $this->assertEquals(
            '{"___type":"Common\\\\Model\\\\BaseModel","message":"hello"}',
            $decorator->getResult()
        );
    }
}
 