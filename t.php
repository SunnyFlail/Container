<?php

require_once "vendor/autoload.php";

use SunnyFlail\DI\Entry;

$c = new class {

    use SunnyFlail\Traits\GetTypesTrait;

    public function getIt() {

        $reflection = new ReflectionClass(Entry::class);

        $i = $reflection->getMethod("invoke");
        $ps = $i->getParameters();

        foreach ($ps as $p) {
            $t = $this->getTypeStrings($p);
            var_dump($t);
        }

    }

};

$fn = function () {
    echo "DECHO";
};


$ref = new ReflectionFunction($fn);

var_dump($ref->getClosureThis());

echo PHP_EOL, PHP_EOL;

$refe = new ReflectionClass($c);
$fere = $refe->getMethod("getIt");
echo($fere->getDeclaringClass());
