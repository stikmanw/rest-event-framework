<?php
namespace Common\Tests\Mock\Service;

use Common\Service\Instance\GenericInstanceFactory;

class TestInstanceFactory extends GenericInstanceFactory
{
   public static function modelFactory()
   {
       return new static("\\Common\\Tests\\Mock\\Model");
   }

   public static function adapterFactory()
   {
       return new static("\\Common\\Tests\\Mock\\Storage\\Adapter\\Test");
   }

}