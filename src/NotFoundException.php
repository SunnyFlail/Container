<?php

namespace SunnyFlail\DI;

use \Psr\Container\NotFoundExceptionInterface;

class NotFoundException extends ContainerException implements NotFoundExceptionInterface 
{
}