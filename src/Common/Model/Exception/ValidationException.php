<?php
namespace Common\Exception;

/**
 * This is thrown in cases where the models input data is not validated against the validate method locally called in the model.
 * Currently we are not handling these any differently than other exceptions.
 *
 * @see \Exception
 */
class ValidationException extends \Exception
{

}
