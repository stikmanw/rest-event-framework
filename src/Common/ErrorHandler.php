<?php
namespace Common;
/**
 * Common error handler methods to formatting response errors to deliver back to the system
 *
 */
use Symfony\Component\HttpFoundation\JsonResponse;
use Teapot\StatusCode;

class ErrorHandler
{
    /**
     * Will return an valid HTTP 500 response error for php fatal errors that occur
     * @param array $error
     * @param string $appPrefix
     * @return JsonResponse
     */
    public static function sendJsonFatalError($error, $appPrefix = null)
    {
        $response = static::jsonFatalError($error, $appPrefix);
        $response->send();
    }

    /**
     * @param $error
     * @param null $appPrefix
     * @return JsonResponse
     */
    public static function jsonFatalError($error, $appPrefix = null)
    {
        $Error = new \stdClass();
        $Error->$appPrefix = new \stdClass();
        $Error->$appPrefix->ExceptionType = "FatalServerError";
        $Error->$appPrefix->ExceptionMessage = $error['message'];
        $Error->$appPrefix->File = $error['file'];
        $Error->$appPrefix->ErrorLine = $error['line'];

        return new JsonResponse($Error, StatusCode::INTERNAL_SERVER_ERROR);
    }

    /**
     * @param $e
     * @param null $appPrefix
     * @return JsonResponse
     */
    public static function jsonExceptionError($e, $appPrefix = null)
    {
        $fullClass = get_class($e);
        $parts = explode('\\', $fullClass);
        end($parts);

        $Error = new \stdClass();
        $Error->$appPrefix = new \stdClass();
        $Error->$appPrefix->ExceptionType = end($parts);
        $Error->$appPrefix->ExceptionMessage = $e->getMessage();
        $Error->$appPrefix->ErrorLine= $e->getLine();
        $Error->$appPrefix->File = $e->getFile();
        $Error->$appPrefix->Trace = $e->getTraceAsString();

        if(is_callable(array($e, "getQuery"))) {
            $Error->$appPrefix->Query = $e->getQuery;
        }

        return new JsonResponse($Error, StatusCode::INTERNAL_SERVER_ERROR);
    }

    /**
     * This will take an exception deliver a json response back based on the exception type
     * and data.
     * @param \Exception $e
     * @return mixed
     */
    public static function sendJsonExceptionError($e, $appPrefix = null)
    {
        $response = static::jsonExceptionError($e, $appPrefix);
        $response->send();
    }
}
