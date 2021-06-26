<?php

namespace SunnyFlail\DI;

use Exception;
use \Psr\Container\NotFoundExceptionInterface;

class NotFoundException extends Exception implements NotFoundExceptionInterface 
{
}