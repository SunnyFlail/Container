<?php

namespace SunnyFlail\DI;

class Entry
{
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

}