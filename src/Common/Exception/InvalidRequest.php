<?php
namespace Common\Exception;

/**
 * Thrown in cases where the request was malformed
 *
 * @extends AbstractException
 */
use Symfony\Component\HttpFoundation\Request;

class InvalidRequest extends AbstractException
{
    /**
     * @var Request
     */
    public $request;

    /**
     * set the request the caused all the issues.
     *
     * @param Request $Request
     * @return $this
     */
    public function setRequest(Request $Request)
    {
        $this->request = $Request;
        return $this;
    }

}
