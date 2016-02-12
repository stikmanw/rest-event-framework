<?php
namespace Common\Exception;
/**
 * This exception should be thrown when an invalid body is sent to a controller
 * request.  Required parameters that are not passed in are a good example to send this
 * exception.
 *
 */

class InvalidBodyRequest extends \InvalidArgumentException
{

}
