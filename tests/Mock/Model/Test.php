<?php
namespace Common\Tests\Mock\Model;

use Common\Model\BaseModel;

class Test extends BaseModel
{
    public $testId;

    public $string = "data";

    public $array = array();

    public $integer = 10;

    public $float = 10.50;

    public $boolean = true;
}