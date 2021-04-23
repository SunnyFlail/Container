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

    private static function setUpContainer(): Container
    {
        $container = new Container();

        $loader = new class implements IContainerLoader{
            public function loadEntries(): array
            {
                return [
                    ReflectionClass::class => new Entry(
                        ReflectionClass::class, [
                            "constructor" => [
                                "objectOrClass" => ArrayIterator::class
                            ]
                        ]
                    ),
                    ArrayIterator::class => new Entry(
                        ArrayIterator::class, [
                            "constructor" => [
                                "array" => []
                            ]
                        ]
                    )
                ];
            }
        };
        $container->configure($loader);

        return $container;
    }

    public static function validProvider(): array
    {
        $container = self::setUpContainer();

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
        $container = self::setUpContainer();

        return [
            "Not found" => [
                $container, \PDO::class, NotFoundException::class
            ]
        ];
    }

}