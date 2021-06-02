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

class Container implements ContainerInterface
{

    use \SunnyFlail\Traits\GetTypesTrait;

    private array $entries;

    public function __construct()
    {
        $this->entries = [];
    }

    public function get(string $id)
    { 
        if (!isset($this->entries[$id])) {
            throw new NotFoundException(sprintf("Entry '%s' not found!", $id));
        }

        if (!is_object($this->entries[$id])) {
            throw new ContainerException(sprintf("Configuration corrupted for %s", $id));
        }
               
        if ($this->entries[$id] instanceof Entry) {
            try {
                $this->entries[$id] = $this->invoke($this->entries[$id]);            
            } catch (ContainerExceptionInterface $e) {
                throw new ContainerException($e->getMessage());
            }
        }
        return $this->entries[$id];
    }

    public function has(string $id): bool
    {
        return isset($this->entries[$id]);
    }

    public function register(string $id, array $options)
    {
        $this->entries[$id] = new Entry($id, $options);
    }

    private function invoke(Entry $entry)
    {
        $className = "\\".$entry->getClassName();
        $config = $entry->getConfig();

        if (!class_exists($className)) {
            throw new ContainerException(sprintf("Class %s does not exist!", $className));
        }

        $reflection = new ReflectionClass($className);       

        if ($constructor = $reflection->getConstructor()) {
            $constructorParams = $this->resolveFunctionParams($constructor, $config["constructor"] ?? []);
        }
        try{
            $object = $reflection->newInstance(...$constructorParams ?? []);
        } catch (\ReflectionException $e) {
            throw new ContainerException(
                sprintf("Something went wrong during instation of %s. Message: %s", $className, $e->getMessage())
            );
        }
        if (isset($config["methods"])) {
            foreach ($config["methods"] as $methodName => $calls) {
                try {
                    $method = $reflection->getMethod($methodName);
                } catch (\ReflectionException $e) {
                    throw new ContainerException(
                        sprintf("Method %s::%s doesn't exist!", $className, $methodName)
                    );
                }
                foreach ($calls as $attemptNum => $params) {
                    $params = $this->resolveFunctionParams($method, $params);
                    try{
                        $method->invoke($object, ...$params);
                    } catch (\ReflectionException) {
                        throw new ContainerException(
                            sprintf("Something went wrong during invoking %s::%s for the %s time!",
                                $className, $methodName, $attemptNum
                            )
                        );
                    }
                }
            }
        }
    
        return $object;
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
            $paramName = $param->getName();
            $requiredTypes = $this->getTypeStrings($param);

            if ($paramName === ContainerInterface::class) {
                $arguments[] = $this;
                continue;
            }

            if (isset($config[$paramName])) {
                $argument = $config[$paramName];
                if (is_string($argument) && class_exists("\\$argument")) {
                    try {
                        $argument = $this->get($argument);
                    } catch (NotFoundException $e) {
                        throw new ContainerException(
                            sprintf(
                                "Provided parameter %s for %s::%s points to a class which isn't registered!",
                                $paramName, $className, $methodName
                            )
                        );
                    }
                }
                if ($requiredTypes) {
                    if (is_object($argument)) {
                        foreach ($requiredTypes as $type) {
                            $type = "\\$type";
                            if ((interface_exists($type) || class_exists($type)) && $argument instanceof $type) {

                                $arguments[] = $argument;

                                continue(2);
                            }
                        }
                        $argType = get_class($argument);
                    }
                    if (in_array($argType = gettype($argument), $requiredTypes)) {
                        $arguments[] = $argument;
                        continue;
                    }

                    throw new ContainerException(
                        sprintf(
                            "Parameter %s of %s::%s should be one of '%s', got %s.",
                            $paramName, $className, $methodName, implode(", ", $requiredTypes), $argType
                        )
                    );
                }

            }

            if (!$param->isDefaultValueAvailable()) {
                throw new ContainerException(
                    sprintf("Value not provided for parameter %s in %s::%s!", $paramName, $className, $methodName)
                );
            }

            $arguments[] = $param->getDefaultValue();
        }

        return $arguments;
    }

    public function configure(IContainerLoader $loader)
    {
        $this->entries = $loader->loadEntries();
    }
}