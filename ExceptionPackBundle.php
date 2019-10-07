<?php

namespace LCV\ExceptionPackBundle;

use LCV\ExceptionPackBundle\DependencyInjection\ExceptionPackExtension;
use Symfony\Component\HttpKernel\Bundle\Bundle;

class ExceptionPackBundle extends Bundle
{
    public function getContainerExtension()
    {
        return new ExceptionPackExtension();
    }
}
