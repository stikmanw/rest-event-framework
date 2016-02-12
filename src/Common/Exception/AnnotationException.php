<?php
namespace Common\Exception;

/**
 * This is the exception that should be thrown when annotation has been set incorrectly in code
 * and needs to be corrected by a developer.
 *
 */

class AnnotationException extends AbstractException
{
    protected $invalidAnnotation;

    /**
     * Set the name of the invalid annotation tagname
     *
     * @param $tagName
     * @return $this
     */
    public function setInvalidAnnotation($tagName)
    {
        $this->invalidAnnotation = $tagName;
        return $this;
    }

    /**
     * @return string
     */
    public function getInvalidAnnotation()
    {
        return $this->invalidAnnotation;
    }
}