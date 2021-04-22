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
        
        if ($this->entries[$id] instanceof Entry) {
            try {
                $this->entries[$id] = $this->invoke($this->entries[$id]);            
            } catch (ContainerExceptionInterface $e) {
                throw new ContainerException($e->getMessage());
            }
        }
        return $this->entries[$id];
    }

    public function has(string $id)
    {
        return isset($this->entries[$id]);
    }

    public function register(string $id, array $options)
    {
        $this->entries[$id] = new Entry($id, $options);
    }

    private function invoke(Entry $entry)
    {
        $className = $entry->getClassName();
        $config = $entry->getConfig();

        if (!class_exists($className)) {
            throw new ContainerException(
                sprintf("Class %s doesn't exist!"));
        }

        $reflection = new ReflectionClass($className);       

        if ($constructor = $reflection->getConstructor()) {
            $constructorParams = $this->resolveFunctionParams($constructor, $config["constructor"] ?? []);
        }
        try{
            $object = $reflection->newInstance(...$constructorParams ?? []);
        } catch (\ReflectionException) {
            throw new ContainerException(
                sprintf("Something went wrong during instation of %s", $className));
        }
        if (isset($config["methods"])) {
            // Methods to be called just after initialisation
            foreach ($config["methods"] as $methodName => $calls) {
                try {
                    $method = $reflection->getMethod($methodName);
                } catch (\ReflectionException $e) {
                    throw new ContainerException(
                        sprintf("Method %s::%s doesn't exist!", ));
                }
                foreach ($calls as $attemptNum => $params) {
                    $params = $this->resolveFunctionParams($method, $params);
                    try{
                        $method->invoke($object, ...$params);
                    } catch (\ReflectionException) {
                        throw new ContainerException(
                            sprintf("Something went wrong during invoking %s::%s for the %s time!",
                                    $className, $methodName, $attemptNum));
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

        if (!$params = $function->getParameters() && $config) {
                throw new ContainerException(
                    sprintf("Method %s::%s doesn't take in any parameters!", $className, $methodName));
        }   

       //foreach ($params as $param) {
        return array_map(
            function($param) {
                $paramName = $param->getName();
                $requiredTypes = $this->getTypeStrings($param);

                if (isset($config[$paramName])) {
                    $argument = $config[$paramName];
                    

                }
                if (!$param->isDefaultValueAvailable() && !isset($config[$paramName])) {
                    throw new ContainerException(
                        sprintf("Value not provided for parameter %s in %s::%s!", $paramName, $className, $methodName));
                }

                return $param->getDefaultValue();
            },
        $params);

    }

    public function configure(IContainerLoader $loader)
    {
        $this->entries = $loader->loadEntries();
    }
}