<?php

namespace Tests;

use \ReflectionClass;
use \SunnyFlail\DI\{
    Entry,
    Container,
    IContainerLoader,
    NotFoundException,
    ContainerException
};
use \ArrayIterator;

class ContainerProvider
{

    private static $CONTAINER;

    private static function setUpContainer(): Container
    {
        $container = new Container();
        return $container->withEntries([
            ReflectionClass::class => [
                "objectOrClass" => ArrayIterator::class
            ],
            ArrayIterator::class => [
                "array" => []
            ]
        ]);
    }

    private static function getContainer(): Container
    {
        if (!isset(self::$CONTAINER)) {
            self::$CONTAINER = self::setUpContainer();
        }
        return self::$CONTAINER;
    }

    public static function validProvider(): array
    {
        $container = self::getContainer();

        return [
            "Simple Invocation" => [
                $container, ArrayIterator::class, ArrayIterator::class
            ],
            "Recursive invoking" => [
                $container, ReflectionClass::class, ReflectionClass::class
            ]
        ];
    }

    public static function exceptionProvider(): array
    {
        $container = self::getContainer();
        
        return [
            "Not found" => [
                $container, "SimpleObject", NotFoundException::class
            ],
            "Bad configuration" => [
                $container, \SplFileObject::class, ContainerException::class
            ]
        ];
    }

}