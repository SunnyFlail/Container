<?php

namespace SunnyFlail\DI;

use \ReflectionClass;
use \ReflectionMethod;
use ReflectionFunction;
use ReflectionParameter;
use \ReflectionException;
use \ReflectionFunctionAbstract;
use \Psr\Container\ContainerInterface;
use \Psr\Container\ContainerExceptionInterface;

class Container implements IContainer
{

    use \SunnyFlail\Traits\GetTypesTrait;

    private array $entries;
    private array $interfaces;
    private bool $autowire;

    public function __construct(
        bool $autowire = true,
    ) {
        $this->entries = [];
        $this->interfaces = [];
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

    public function withEntries(array $entries): IContainer
    {
        $this->entries = $entries;
        return $this;
    }

    public function withInterfaces(array $entries): IContainer
    {
        $this->interfaces = $entries;
        return $this;
    }

    public function invoke(array|string|callable $function, array $parameters): mixed
    {
        if (is_array($function)) {
            [$object, $function] = $function;
            $object = $this->get($object);
            
            return $this->invokeMethod($function, $object, $parameters);
        }

        return $this->invokeFunction($function, $parameters);
    }

    /**
     * Invokes object of provided class with provided parameters
     * 
     * @param string $className FQCN of class
     * @param array $parameters
     * 
     * @throws ContainerException
     */
    private function invokeEntry(string $className, array $config)
    {
        try {
            return $this->invokeObject($className, $config);            
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

    /**
     * Invokes object of provided class with provided parameters
     * 
     * @param string $className FQCN of class
     * @param array $parameters
     * 
     * @return object
     */
    private function invokeObject(string $className, array $parameters): object
    {
        $reflection = new ReflectionClass($className);       

        if ($constructor = $reflection->getConstructor()) {
            $constructorParams = $this->resolveFunctionParams($constructor, $parameters);
        }
    
        return $this->instantiateObject($reflection, $constructorParams ?? []);
    }

    /**
     * Invokes method of provided object
     * 
     * @param string|callable $function Name of the method to invoke or Closure
     * 
     * @return mixed
     * 
     * @throws ContainerException
     */
    private function invokeFunction(string|callable $function, array $parameters): mixed
    {
        try{
            $function = new ReflectionFunction($function);
            $parameters = $this->resolveFunctionParams($function, $parameters);
        } catch (ReflectionException $e) {
            throw new ContainerException(sprintf(
                "An error occured while invoking function",
                $function->getName()
            ));
        }

        return $function->invokeArgs($parameters);
    }

    /**
     * Invokes method of provided object
     * 
     * @param string $method Name of the method to invoke 
     * 
     * @return mixed
     * 
     * @throws ContainerException
     */
    private function invokeMethod(string $method, object $object, array $parameters): mixed
    {
        try{
            $method = new ReflectionMethod($object, $method);
            $parameters = $this->resolveFunctionParams($method, $parameters);
        } catch (ReflectionException $e) {
            throw new ContainerException(sprintf(
                "An error occured while invoking method %s of class %s",
                $method->getName(), (new ReflectionClass($object))->getShortName()
            ));
        }

        return $method->invokeArgs($object, $parameters);
    }

    /**
     * Creates a copy of object of provided class with provided parameters
     * 
     * @param ReflectionClass $reflection Reflection of class
     * @param array $constructorParams
     * 
     * @return object
     * @throws ContainerException
     */
    private function instantiateObject(ReflectionClass $reflection, array $constructorParams): object
    {
        try{
            return $reflection->newInstance(...$constructorParams);
        } catch (ReflectionException $e) {
            throw new ContainerException(
                sprintf(
                    'Something went wrong during instation of %s.',
                    $reflection->getName()
                ), 0, $e
            );
        }
    }

    /**
     * Returns an associative array containing parameters for function
     * 
     * @param ReflectionFunctionAbstract $function Reflection of function
     * @param array $config User provided parameters for function
     * 
     * @return array
     */
    private function resolveFunctionParams(
        ReflectionFunctionAbstract $function,
        array $config
    ): ?array
    {
        $functionName = $function->getName();
        $className = null;
        if ($function instanceof ReflectionMethod) {
            $className = $function->getDeclaringClass()->getName();
        }

        if (!($params = $function->getParameters()) && $config) {
            throw new ContainerException(
                sprintf("%s doesn't take in any parameters!", $this->getFunctionFullName($className, $functionName))
            );
        }   

        $arguments = [];

        foreach ($params as $param) {
            $arguments[] = $this->resolveFunctionParam(
                $functionName,
                $className,
                $config,
                $param
            );
        }

        return $arguments;
    }

    /**
     * Returns value of param
     * 
     * @param string $functionName
     * @param string|null $className
     * @param array $config
     * @param ReflectionParameter $param
     * 
     * @return mixed
     * @throws ContainerException
     */
    private function resolveFunctionParam(
        string $functionName,
        ?string $className,
        array $config,
        ReflectionParameter $param,
    ): mixed
    {
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
                            "Parameter %s provided for %s points to a class which isn't available!",
                            $paramName, $this->getFunctionFullName($className, $functionName)
                        ), 0, $e
                    );
                }
            }
            if ($requiredTypes) {
                if (is_object($argument)) {
                    foreach ($requiredTypes as $type) {
                        $type = '\\$type';
                        if ((interface_exists($type) || class_exists($type))
                            && $argument instanceof $type
                        ) {
                            return $argument;
                        }
                    }
                    $argType = get_class($argument);
                }

                try {
                    return $this->resolvePrimitiveParam($argument, $requiredTypes);
                } catch (ContainerExceptionInterface) {
                    $argType = gettype($argument);

                    throw new ContainerException(
                        sprintf(
                            "Parameter %s of %s should be one of '%s', got %s.",
                            $paramName, $this->getFunctionFullName($className, $functionName),
                            implode(", ", $requiredTypes), $argType
                        )
                    );
                }
            }
        }

        if ($this->autowire) {
            foreach ($requiredTypes as $type) {
                if (class_exists("\\".$type)) {
                    return $this->get($type);
                }
                if (interface_exists("\\".$type) && isset($this->interfaces[$type])) {
                    $type = $this->interfaces[$type];
                    return $this->get($type);
                }
            }
        }

        if (!$param->isDefaultValueAvailable()) {
            throw new ContainerException(
                sprintf(
                    "Value not provided for parameter %s in %s::%s!",
                    $paramName, $className, $functionName
                )
            );
        }

        return $param->getDefaultValue();
    }

    /**
     * Checks whether provided param fits with required types
     * 
     * @param mixed $argument Primitive user-provided argument
     * @param array $requiredTypes String names of types accepted by parameter
     * 
     * @return mixed
     * @throws ContainerException
     */
    private function resolvePrimitiveParam(mixed $argument, array $requiredTypes): mixed
    {
        foreach ($requiredTypes as $type) {
            if ($type === 'int' && is_numeric($argument)) {
                return $argument;
            }
            if ($type === 'bool' && is_bool($argument)) {
                return $argument;
            }
            if ($type === 'array' && is_array($argument)) {
                return $argument;
            }
            if ($type === 'string' && is_string($argument)) {
                return $argument;
            }
            if ($type === 'null' && is_string($argument)) {
                return $argument;
            }
            if ($type === 'callable' && is_callable($argument)) {
                return $argument;
            }
            if ($type === 'mixed') {
                return $argument;
            }
        }

        throw new ContainerException();
    }
    
    /**
     * Returns the full name of function, or Class::function name for methods
     * 
     * @return string
     */
    private function getFunctionFullName(string $functionName, ?string $className): string
    {
        if ($className === null) {
            return 'Function ' . $functionName;
        }
        return 'Method ' . $className . '::'. $functionName;
    }

}