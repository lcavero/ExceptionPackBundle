<?php

namespace LCV\ExceptionPackBundle\Exception;

class InvalidFormularyException extends ApiException
{
    protected $constraintsErrors;

    public function __construct($constraintsErrors = [], $message = "lcv.invalid_formulary", \Exception $previous = null,
                                $statusCode = 400)
    {
        $this->constraintsErrors = $constraintsErrors;
        parent::__construct($statusCode, $message, $previous);
    }

    /**
     * @return array
     */
    public function getConstraintsErrors()
    {
        return $this->constraintsErrors;
    }


}
