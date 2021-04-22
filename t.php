<?php

require_once "vendor/autoload.php";

use SunnyFlail\DI\Entry;

class str
{
    public function __construct(public int $int) {}

}


$e = new str("123");


$ref = new ReflectionClass("string");