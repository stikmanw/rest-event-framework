<?php
namespace Common\Storage\Exception;
/**
 * Class ConnectionException
 * This exception should be used with connection adapters for sending back the issue
 * that prevented connecting to the internal resource handle.
 *
 * @package Common\Storage\Exception
 */
class ConnectionException extends \RunTimeException
{
} 