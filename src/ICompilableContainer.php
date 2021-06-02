<?php

namespace SunnyFlail\DI;

interface ICompilableContainer
{

    /**
     * Adds a new 
     * 
     * @param class-string $id Absolute Class name of the service
     * @param array|string $options 
     */
    public function register(string $id, array|string $options);

    /**
     * Creates a CompiledContainer class with provided configuration
     */
    public function compile();

}