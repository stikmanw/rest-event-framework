<?php
namespace Common\Exception;

/**
 * Abstract Exception fix around chaining on the constructor
 *
 */

abstract class AbstractException extends \Exception
{
    public function __construct($message = "", $code = 0, \Exception $previous = null)
    {
        parent::__construct($message, $code, $previous);

        if(extension_loaded("newrelic")) {
            newrelic_add_custom_parameter("errorDetails: " . $this->getMessage());
        }
    }

    /**
     * create new version of self in a way we can chain...useful for the severly lazy programmer
     * (like myself)
     * @return AbstractExeption
     */
    static public function create($message, $code = 0, $previous = null)
    {
        return new static($message, $code, $previous);
    }
}