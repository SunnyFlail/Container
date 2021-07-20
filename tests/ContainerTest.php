<?php

namespace Tests;

use \PHPUnit\Framework\TestCase;
use \SunnyFlail\DI\{
    Entry,
    Container,
    IContainerLoader
};

class ContainerTest extends TestCase
{

    /**
     * @dataProvider Tests\ContainerProvider::validProvider
     */
    public function testValidInvoking(Container $container, string $key, string $expected)
    {
        $object = $container->get($key);

        $this->assertEquals(
            $expected,
            get_class($object)
        );
    }

    /**
     * @dataProvider Tests\ContainerProvider::exceptionProvider
     */
    public function testFailedInvoking(Container $container, string $key, string $expected)
    {
        $this->expectException($expected);

        $container->get($key);
    }

    /**
     * @dataProvider Tests\ContainerProvider::functionProvider
     */
    public function testFunctionInvoking(Container $container, string $functionName, array $parameters, mixed $expected)
    {
        $result = $container->invoke($functionName, $parameters);

        $this->assertEquals(
            $expected,
            $result
        );
    }

}