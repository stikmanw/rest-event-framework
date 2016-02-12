<?php
namespace Common;
/**
 * Dependency Injection class for wrapping up injectable objects
 * from a base state model.  Concepts of this are to locate the injectable
 * dependencies from a class that is passed in.  I found a lot of projects
 * in the wild that use more open ended injection resolution.  I want to force
 * a specific container around the scope of where dependencies can be resolved from.
 *
 * I also did not want to use the pimple service method which handles a lot more than
 * simply injecting requires arguments to a class.
 *
 * So use this class if you want to build an instance of a class with it's dependencies
 * injected based on a common object.
 *
 */
use Common\Injector\ReflectionCache;

class Injector
{
    /**
     * @var \stdClass
     */
    private $scope;

    /**
     * @var array
     */
    private $scopeArray;

    /**
     * In order for an injector to be able to determine scope it must have an
     * object container of the dependencies.
     *
     * @param \stdClass $scope
     * @throws \InvalidArgumentException
     */
    public function __construct($scope)
    {
        // change over arrays automatically... to an object
        if(is_array($scope)) {
            $scope = (object)$scope;
        }

        if(!is_object($scope)) {
            throw new \InvalidArgumentException("Injection requires an object scope to be passed in.");
        }

        $this->scope = $scope;
        $this->scopeArray = (array)$scope;
    }

    /**
     * Allows for runtime changes to the scope object
     * @param $key
     * @param $value
     * @throws \DomainException
     */
    public function updateScope($key, $value)
    {
        if(isset($this->scope->$key)) {
           throw new \DomainException("The $key is already set on the scope and would create a collision. ");
        }

        $this->scope->$key = $value;
        $this->scopeArray[$key] = $value;
    }


    public function execute($mixedFunction)
    {
        if(is_string($mixedFunction) && strpos($mixedFunction, "::") !== false) {
            return $this->executeFromString($mixedFunction);
        }

        if(is_array($mixedFunction)) {
            return $this->executeFromArray($mixedFunction);
        }

        if(is_object($mixedFunction) && method_exists($mixedFunction, "__invoke")) {
            return $this->executeFromObject($mixedFunction);
        }

        throw new \InvalidArgumentException("The callable passed into execute is not supported or incorrectly specified see documentation");

    }

    public function make($className)
    {
        if(!class_exists($className)) {
            throw new \InvalidArgumentException("$className does not exist");
        }

        $reflection = ReflectionCache::getInstance();
        $const = $reflection->getConstructor($className);

        if(!$const) {
            $dependencies = array();
        } else {
            $dependencies = $this->getDependencies($const);
        }

        $reflection = ReflectionCache::getInstance();
        $classInvoker = $reflection->getClass($className);
        $classInstance = $classInvoker->newInstanceArgs($dependencies);

        return $classInstance;
    }

    private function executeFromString($stringDef)
    {
        list($class, $method) = explode("::", $stringDef);
        if(empty($class) || empty($method)) {
            throw new \RuntimeException("$stringDef is not a valid function class::method");
        }

        return $this->executeFromArray(array($class, $method));

    }

    private function executeFromArray($array)
    {
        $objectOrClass = $array[0];

        if(is_object($objectOrClass)) {
            return $this->executeFromObject($objectOrClass, $array[1]);
        }

        list($class, $method) = $array;
        $reflection = ReflectionCache::getInstance();
        $method = $reflection->getMethod($class, $method);

        $dependencies = $this->getDependencies($method);

        $object = $this->make($class);
        return $method->invokeArgs($object, $dependencies);

    }

    private function executeFromObject($object, $methodName = "__invoke")
    {
        $reflection = ReflectionCache::getInstance();
        $method = $reflection->getMethod($object, $methodName);

        $dependencies = $this->getDependencies($method);

        return $method->invokeArgs($object, $dependencies);
    }

    /**
     * This will find the class dependencies for creating an instance of a class and
     * return the valid dependencies.
     *
     * @param \ReflectionMethod $method
     * @return array
     */
    private function getDependencies(\ReflectionMethod $method)
    {
        $params = $method->getParameters();

        $args = array();
        if(count($params)) {
            foreach($params as $v) {

                // if we are resolving an object look up the object
                $class = $v->getClass();

                // check to see if we have a default value in which case the we need to behave differently as it is not a hard dependency
                try{
                    $defaultValue = $v->getDefaultValue();
                } catch(\Exception $e) {
                    // we can not use null values since it is possible that nulls are what the default value should be
                    $defaultValue = '_value_not_set_';
                }

                try {
                    if($class) {
                        $args[] = $this->resolveObjectDependency($v->getName(), $class->getName(), $defaultValue);
                    } else {
                        // have to tackle this a little differently for the purpose of reference maintenance have to use reference
                        // in order to pass references into the newInstance function...its a little confusing ask me sometime if
                        // you need to understand. -- bcarter

                        $this->resolveValueDependency($v->getName(), $args, $v->isPassedByReference(), $defaultValue);
                    }
                } catch(\Exception $e) {

                }

            }
        }

        return $args;
    }

    /**
     * This will lookup the dependency inside of our scope and pass back the matched result.
     *
     * In order to resolve dependency we need some rules about what exists on our state model and the
     * priority we use to find a dependency
     *
     * 1. Requested Variable Name match ie Contact $contact -> $scope->Contact (types match then we return injection)
     * 2. Request Type Base Name match Contact $model -> if no $scope->model && $scope->Contact/$scope->contact retrn $scope->Contact
     *
     * Right now this is all that is supported but future adds could contain functional scope followed by global scope
     * At this point in time that seems dangerous and unnecessary.
     *
     * @param string $property name of the property in the signature of the make class
     * @param null|string $type
     * @param string $default
     * @throws \RuntimeException
     * @internal param null|string $type hinted class the property should be validated against if set
     * @return mixed
     */
    private function resolveObjectDependency($property, $type = null, $default = "_value_not_set_")
    {
        // do the easy case 1 of matching case insensitively the variable name to object scope
        $dependencyInstance = $this->findDependency($property, $type);
        if($dependencyInstance) {
            return $dependencyInstance;
        }

        // do case 2
        if($type) {

            // most common case we have a namespace full qualified name
            if(strpos($type, '\\') !== false) {
                $lookup = end(explode('\\', $type));
            } else {
                $lookup = $type;
            }

            $dependencyInstance = $this->findDependency($lookup, $type);
        }
        if($dependencyInstance) {
            return $dependencyInstance;
        }

        // in this case since we are resolving an object the only default we can allow is null
        if($default === null) {
            return $default;
        }

        throw new \RuntimeException("Could not resolve dependency {$property} of object type {$type}");
    }

    private function resolveValueDependency($property, &$args, $ref = false, $default = "_value_not_set_")
    {
        // probably cleaner way to do this magic but I am tired and cant see very well due to messed up contacts.
        $lowProperty = strtolower($property);
        if(array_key_exists($lowProperty, $this->scopeArray) && !array_key_exists($property, $this->scopeArray)) {
            $property = $lowProperty;
        }

        if(array_key_exists(ucwords($property),$this->scopeArray)) {
            $property = ucwords($property);
        }

        // look up the property
        if(array_key_exists($property, $this->scopeArray)) {
            if($ref) {
                $args[] = & $this->ref($this->scope, $property);
                return;
            } else {
                $args[] = $this->scope->$property;
                return;
            }
        }

        // if a default value has been set our method signature will not be the same and we know there is a default value meeting
        // the dependency requirement
        if($default) {
            return $default;
        }

        throw new \RuntimeException("Could not resolve dependency of value {$property}");
    }

    /**
     * Look up the dependency in the scope definition object
     * @param $property name of the property from the scope definition
     * @param null $type required class type if hinted
     * @return bool|mixed
     */
    private function findDependency($property, $type = null)
    {
        $lowProperty = strtolower($property);
        $ucProperty = ucwords($property);

        // case 1 listed above
        if(array_key_exists($property, $this->scopeArray)) {
            if($type) {
                return $this->validateObjectType($property, $type);
            }

            return $this->scope->$property;
        }
        if (array_key_exists($lowProperty, $this->scopeArray)) {
            if($type) {
                return $this->validateObjectType($lowProperty, $type);
            }
            return $this->scope->$lowProperty;
        }


        if(array_key_exists($ucProperty, $this->scopeArray)) {
            if($type) {
                return $this->validateObjectType($ucProperty, $type);
            }

            return $this->scope->ucProperty;
        }



        return false;
    }

    /**
     * Validate the object property is of the correct object type
     * @param string $property
     * @param string $type
     * @return mixed
     * @throws \RuntimeException
     */
    private function validateObjectType($property, $type)
    {
        if(! $this->scope->$property instanceof $type) {  
            // at this point we know the property is on the scope 
            if(is_null($this->scope->$property)){
                // if it's null then assume we need to lazy load it
                $this->scope->$property = new $type();
            } else {
                // if its something else, then something has gone terribly wrong
                throw new \RuntimeException("Resolved dependency $property but scope is not of current type");
            }
        }

        return $this->scope->$property;
    }

    /**
     * Return a reference to an inner object public variable instead of a copy.
     *
     * @example ref($myObject, 'foo'); // return &$object->foo
     * @param $object
     * @param string $property name of the property to return a reference of
     * @return mixed
     */
    private function &ref($object, $property)
    {
        $value = & \Closure::bind(function & () use ($property) {
                return $this->$property;
            }, $object, $object)->__invoke();
        return $value;
    }

}
