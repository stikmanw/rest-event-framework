<?php
namespace Common\Exception;
/**
 * Thrown when a request handler class cannot be found to deal with a specific request.
 *
 */
class MissingHandlerException extends \InvalidArgumentException
{

}
