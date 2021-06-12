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

        $classes = [
            [
                ReflectionClass::class, [
                    "constructor" => [
                        "objectOrClass" => ArrayIterator::class
                    ]
                ]
            ], [
                ArrayIterator::class, [
                    "constructor" => [
                        "array" => []
                    ]
                ]
            ]
        ];

        foreach ($classes as [$classname, $config]) {
            $container->register($classname, $config);
        }

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