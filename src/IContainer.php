<?php

namespace SunnyFlail\DI;

use \Psr\Container\ContainerInterface;

interface IContainer extends ContainerInterface
{

    /**
     * Invokes a function with provided parameters
     * 
     * @param array|string|callable $function - It may be an array containing class name and method name, name of the function or a Closure
     * @param array $parameters Associative array with keys as parameter names
     * 
     * @return mixed
     */
    public function invokeFunction(array|string|callable $function, array $parameters): mixed;

}