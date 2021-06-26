<?php

namespace SunnyFlail\DI;

use \Psr\Container\{
    ContainerInterface,
    ContainerExceptionInterface
};
use \ReflectionClass;
use \ReflectionMethod;
use \ReflectionException;
use \ReflectionFunctionAbstract;
use ReflectionParameter;

class Container implements ContainerInterface
{

    use \SunnyFlail\Traits\GetTypesTrait;

    private array $entries;
    private bool $autowire;

    public function __construct(
        bool $autowire = true,
    ) {
        $this->entries = [];
        $this->autowire = $autowire;
    }

    public function get(string $id)
    {
        if (!class_exists($id)) {
            throw new NotFoundException(sprintf("Class '%s' does not exist!", $id));
        }
        if (!isset($this->entries[$id])) {
            if (!$this->autowire) {
                throw new NotFoundException(sprintf("Entry '%s' not found!", $id));
            }
            $this->entries[$id] = $this->invokeEntry($id, []);
        }
        if (is_array($this->entries[$id])) {
            $this->entries[$id] = $this->invokeEntry($id, $this->entries[$id]);
        }
               
        return $this->entries[$id];
    }

    public function has(string $id): bool
    {
        return isset($this->entries[$id]);
    }

    public function withEntries(array $entries): self
    {
        $this->entries = $entries;
        return $this;
    }

    private function invokeEntry(string $className, array $config)
    {
        try {
            return $this->invoke($className, $config);            
        } catch (\Throwable $e) {
            if ($e instanceof ContainerExceptionInterface) {
                throw $e;
            }
            throw new ContainerException(
                sprintf("An error occurred while invoking '%s'.", $className),
                0, $e
            );
        }
    }

    private function invoke(string $className, array $config)
    {
        $reflection = new ReflectionClass($className);       

        if ($constructor = $reflection->getConstructor()) {
            $constructorParams = $this->resolveFunctionParams($constructor, $config);
        }
    
        return $this->instantiateObject($reflection, $constructorParams ?? []);
    }

    private function instantiateObject(ReflectionClass $reflection, array $constructorParams): object
    {
        try{
            return $reflection->newInstance(...$constructorParams);
        } catch (\ReflectionException $e) {
            throw new ContainerException(
                sprintf(
                    "Something went wrong during instation of '%s'.",
                    $reflection->getName()
                ), 0, $e
            );
        }
    }

    private function resolveFunctionParams(
        ReflectionMethod $function,
        array $config
    ): ?array
    {
        $methodName = $function->getName();
        $className = $function->getDeclaringClass()->getName();

        if (!($params = $function->getParameters()) && $config) {
            throw new ContainerException(
                sprintf("Method %s::%s doesn't take in any parameters!", $className, $methodName)
            );
        }   

        $arguments = [];

        foreach ($params as $param) {
            $arguments[] = $this->resolveFunctionParam(
                $methodName,
                $className,
                $config,
                $param
            );
        }

        return $arguments;
    }

    private function resolveFunctionParam(
        string $methodName,
        string $className,
        array $config,
        ReflectionParameter $param,
    ) {
        $paramName = $param->getName();
        $requiredTypes = $this->getTypeStrings($param);

        if (in_array(ContainerInterface::class, $requiredTypes)) {
            return $this;
        }

        if (isset($config[$paramName])) {
            $argument = $config[$paramName];
            if (is_string($argument) && class_exists("\\$argument")) {
                try {
                    $argument = $this->get($argument);
                } catch (ContainerExceptionInterface $e) {
                    throw new ContainerException(
                        sprintf(
                            "Provided parameter %s for %s::%s points to a class which isn't available!",
                            $paramName, $className, $methodName
                        ), 0, $e
                    );
                }
            }
            if ($requiredTypes) {
                if (is_object($argument)) {
                    foreach ($requiredTypes as $type) {
                        $type = "\\$type";
                        if ((interface_exists($type) || class_exists($type))
                            && $argument instanceof $type
                        ) {
                            return $argument;
                        }
                    }
                    $argType = get_class($argument);
                }

                if (in_array($argType = gettype($argument), $requiredTypes)
                    || ($argType === "integer" && in_array("int", $requiredTypes))
                ) {
                    return $argument;
                }

                throw new ContainerException(
                    sprintf(
                        "Parameter %s of %s::%s should be one of '%s', got %s.",
                        $paramName, $className, $methodName,
                        implode(", ", $requiredTypes), $argType
                    )
                );
            }
        }

        if ($this->autowire) {
            foreach ($requiredTypes as $type) {
                if (class_exists("\\".$type) || interface_exists("\\".$type)) {
                    return $this->get($type);
                }
            }
        }

        if (!$param->isDefaultValueAvailable()) {
            throw new ContainerException(
                sprintf(
                    "Value not provided for parameter %s in %s::%s!",
                    $paramName, $className, $methodName
                )
            );
        }

        return $param->getDefaultValue();
    }
    
}