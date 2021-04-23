<?php

namespace SunnyFlail\DI;
// To implement
class MethodConfiguration
{

    private string $methodName;
    private array $arguments;

    public function __construct(string $methodName, array $arguments) {
        $this->methodName = $methodName;
        $this->arguments = $arguments;
    }

    public function getMethodName(): string
    {
        return $this->methodName;
    }

    public function getArguments(): array
    {
        return $this->arguments;
    }

}