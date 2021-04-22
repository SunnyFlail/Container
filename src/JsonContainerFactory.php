<?php

namespace SunnyFlail\DI;

final class JsonContainerFactory
{

    private function __construct() {}

    public static function fromJson(string $json): ContainerInterface
    {
        


        return new Container($data);
    }

}