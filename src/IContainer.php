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
    public function invoke(array|string|callable $function, array $parameters): mixed;

    /**
     * Registers new Entries
     * 
     * @param array $entries Associative array of arrays, Keys are class FQCN, values are arrays with param names as keys
     * 
     * @return IContainer 
     */
    public function withEntries(array $entries): IContainer;

    /**
     * Registers classes defaulting to Interfaces
     * 
     * @param array $entries Asssociative array with Interface FQCN as key and Class FQCN as value
     * 
     * @return IContainer
     */
    public function withIntefaces(array $entries): IContainer;

}