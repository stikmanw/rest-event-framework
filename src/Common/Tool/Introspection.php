<?php
namespace Common\Tool;
/**
 * Class utility for inspecting and formatting data on objects and classes.
 *
 */
class Introspection
{
    public static function getPublicVars($Object)
    {
        return get_object_vars($Object);
    }

    public static function hasMember($Object, $memberName)
    {
        $vars = static::getPublicVars($Object);
        return in_array($memberName, array_keys($vars));
    }

    /**
     * check to see if the variable exists.  This does not work on object member variables
     * @var string $variableName
     * @return bool
     */
    public static function variableExists($variableName)
    {
        return array_key_exists($variableName, compact($variableName));
    }

    /**
     * Flatten an object to an array of member => value array that represents
     * the object as an associative array of set variables.
     *
     * @param stdClass $Object
     * @param boolean $includeNulls include nulls when flattening the array
     * @return array
     */
    public static function flattenToArray($Object, $includeNulls = false)
    {
        $variables = array();

        $vars = get_object_vars($Object);
        foreach($vars as $member => $value) {

            if(is_scalar($value)) {
                $variables[$member] = $value;
            }

            if($includeNulls && $value === null) {
                $variables[$member] = $value;
            }
        }

        return $variables;
    }

    /**
     * Create a new Object of the same type and camelize the members return the new object
     *
     * @param stdClass $Object
     * @return mixed
     */
    public static function camelizeMembers($Object)
    {
        $class = get_class($Object);
        $newObject = new $class();
        foreach($Object as $member => $value) {
            $member = static::modelizeName($member);
            $newObject->$member = $value;
        }

        return $newObject;

    }

    /**
     * create a new object of the same type with tablized properties
     *
     * @param stdClass
     * @return mixed
     */
    public static function tabelizeMembers($Object)
    {
        $class = get_class($Object);
        $newObject = new $class();
        foreach($Object as $property => $value){
            $property = static::tabelizeName($property);
            $newObject->{$property} = $value;
        }

        return $newObject;
    }

    /**
     * Turn the name of a variable into the it's respective model version
     * which involves camelcasing and capitalizing id when it appears.
     *
     * @param string $name
     * @return string
     */
    public static function modelizeName($name)
    {
        $name = static::camelize($name);
        $len = strlen($name);
        if(strpos($name, 'ID') == ($len - 2)) {
            $name[$len -1] = 'd';
        }

        return $name;
    }

    /**
     * Turn a word into our DB Standards table name
     *
     * Rules
     * 1. First Character is capital
     * 2. ID is always capital
     * 3. Fields are CamelCased
     *
     * @param string $name
     * @return string
     */
    public static function tabelizeName($name)
    {
        $name = static::camelize($name);

        $len = strlen($name);
        if(strpos($name, 'Id')  == ($len - 2)) {
            $name[$len - 1] = 'D';
        }

        $name[0] = strtoupper($name[0]);
        return $name;
    }

    /**
     * @param stdClass $Object to inspect
     * @param string $property name of the property to look up
     * @return qualified classname
     */
    public static function getVarType($Object, $property)
    {
      $Property = new \ReflectionProperty($Object, $property);
      preg_match("/@var\s([A-Za-z_0-9\\\\]+)\s/", $Property->getDocComment(), $match);

      if(isset($match[1])) {
          return $match[1];
      }
    }

    /**
     * Get the base class for full namespaced instance
     *
     * @param object
     * @return string
     */
    public static function getClassName($object)
    {
        $className = get_class($object);
        $parts = explode("\\", $className);
        return end($parts);
    }

    /**
     * Return just the root class name from a namespace class
     *
     * @param string $namespaceClass
     * @return string
     */
    public static function getClassFromNamespace($namespaceClass)
    {
        $parts = explode('\\', $namespaceClass);
        if(is_array(($parts))) {
            return array_pop($parts);
        } else {
            return false;
        }
    }

    /**
     * Get the name of the namespace for the given object
     * @param $object
     * @return array
     */
    public static function getNamespace($object)
    {
        $className = get_class($object);
        return array_slice(explode('\\', $className), 0, -1);
    }

    /**
     * Get the base namespace level
     *
     * ie )  getNamespaceBase('\Common\Test\IDontWriteTests');
     *       Common
     *
     * @param $class
     * @return mixed
     */
    public static function getNamespaceBase($class)
    {
        $parts = explode('\\', $class);
        return array_shift($parts);
    }

    /**
     * Recurse through the parent chain and find all parents
     *
     * @param object $class
     * @param array<string> $list used internally to grab all parents
     * @return array<string>
     */
    public static function getParentClasses($class, $list = array())
    {
        $parent = get_parent_class($class);

        // if we have not parent we have reached the end of the chain
        if($parent) {
            $list[] = $parent;
            $list = static::getParentClasses($parent, $list);
        }

       return array_reverse($list);
    }

    /**
     * Create an empty shell for a model that has the same structure minus all
     * the data that makes it unique. Revisit this as it may need somw tweaking to handle nested collections
     *
     * @param $item
     * @internal param object $model
     * @return object
     */
    public static function createShell(&$item)
    {
        $shell = unserialize(serialize($item));

        foreach($shell as $member => &$value) {
            if(is_scalar($value)) {
                $value = null;
            } elseif(is_object($value)) {
                $shell->$member = static::createShell($value);
            }
        }

        return $shell;
    }

    /**
     * Convert a word in to the format for a Doctrine class name. Converts 'table_name' to 'TableName'
     *
     * @param string  $word  Word to classify
     * @return string $word  Classified word
     */
    public static function classify($word)
    {
        return str_replace(" ", "", ucwords(strtr($word, "_-", "  ")));
    }

    /**
     * Camelize a word. This uses the classify() method and turns the first character to lowercase
     *
     * @param string $word
     * @return string $word
     */
    public static function camelize($word)
    {
        return lcfirst(self::classify($word));
    }

}
