<?php

namespace SunnyFlail\DI;

class Entry
{

    private string $className;
    private array $config;

    private MethodConfiguration $constructor;

    public function __construct(
        string $className,
        array $config
    )
    {
        $this->className = $className;
        $this->config = $config;
    }

    public function getClassName(): string
    {
        return $this->className;
    }

    public function getConfig(): array
    {
        return $this->config;
    }

    public function getConstructorParams(): ?MethodConfiguration
    {
        return $this->constructor;
    }

}