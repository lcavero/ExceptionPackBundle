<?php

namespace LCV\ExceptionPackBundle\Exception;

use Symfony\Component\HttpKernel\Exception\HttpException;

class ApiException extends HttpException
{
    protected $translationParams;

    public function __construct($statusCode, $message = null, $translationParams = [], \Throwable $previous = null, array $headers = [], $code = 0)
    {
        $this->translationParams = $translationParams;
        parent::__construct($statusCode, $message, $previous, $headers, $code);
    }

    public function getTranslationParams()
    {
        return $this->translationParams;
    }
}
