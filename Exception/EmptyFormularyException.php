<?php

namespace LCV\ExceptionPackBundle\Exception;

class EmptyFormularyException extends InvalidFormularyException
{
    public function __construct()
    {
        parent::__construct([], "lcv.empty_formulary");
    }
}
