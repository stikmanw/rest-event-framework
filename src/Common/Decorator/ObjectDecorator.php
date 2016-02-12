<?php
namespace Common\Decorator;

class ObjectDecorator
{
    protected $object;

    public function setObject($object)
    {
        if(!is_object($object)) {
            throw new \InvalidArgumentException("Decorator class requires and object is:  ". gettype($object));
        }
        $this->object = $object;
    }

    public function getResult()
    {
        if(is_callable($this->object, "__toString")) {
            return $this->object->__toString();
        } else {
            return print_r($this->object, true);
        }
    }

    public function __toString()
    {
        return $this->getResult();
    }
}