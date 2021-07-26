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
use Throwable;

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
            throw new NotFoundException(sprintf("Class %s does not exist!", $id));

        }
        if (!isset($this->entries[$id])) {
            if (!$this->autowire) {
                throw new NotFoundException(sprintf("Entry %s not found!", $id));
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
            
            if (!is_object($object)) {
                $object = $this->get($object);
            }
            
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
                sprintf("An error occurred while invoking %s", $className),
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
                "An error occured while invoking function %s",
                $function->getName()
            ));
        }

        return $function->invokeArgs($parameters);
    }

    /**
     * Invokes method of provided object
     * 
     * @param string $method Name of the method to invoke 
     * @param object $object Object on which to invoke the method 
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
        } catch (Throwable $e) {
            throw new ContainerException(
                sprintf(
                    "An error occured while invoking %s::%s",
                    (new ReflectionClass($object))->getShortName(), $method
                ), 0, $e
            );
        }

        return $method->invokeArgs($object, $parameters);
    }

    /**
     * Creates a copy of object of provided class with provided parameters
     * 
     * @param ReflectionClass $reflection Reflection of class
     * @param array $constructorParams Parameters to be passed into object constructor
     * 
     * @return object
     * 
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
    private function resolveFunctionParams(ReflectionFunctionAbstract $function, array $config): ?array
    {
        $params = $function->getParameters();

        $arguments = [];

        foreach ($params as $param) {
            $arguments[] = $this->resolveFunctionParam($function, $param, $config);
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
        ReflectionFunctionAbstract $function,
        ReflectionParameter $param,
        array $config
    ): mixed
    {
        $paramName = $param->getName();
        $requiredTypes = $this->getTypeStrings($param);

        if (in_array(ContainerInterface::class, $requiredTypes)) {
            return $this;
        }

        if (isset($config[$paramName])) {
            $argument = $config[$paramName];

            if (is_string($argument) && class_exists('\\' . $argument)) {
                return $this->resolveReferencedParam($function, $paramName, $argument);
            }
            if ($requiredTypes) {
                if (is_object($argument)) {
                    return $this->resolveProvidedObject($paramName, $function, $argument, $requiredTypes);
                }

                return $this->resolvePrimitiveParam($paramName, $function, $argument, $requiredTypes);
            }
        }

        if ($this->autowire) {
            foreach ($requiredTypes as $type) {
                if (class_exists('\\' . $type)) {
                    return $this->get($type);
                }
                if (isset($this->interfaces[$type]) && interface_exists('\\' . $type)) {
                    $type = $this->interfaces[$type];
                    
                    return $this->get($type);
                }
            }
        }

        if (!$param->isDefaultValueAvailable()) {
            throw new ContainerException(
                sprintf(
                    "Value not provided for parameter %s in %s!",
                    $paramName, $this->getFunctionName($function)
                )
            );
        }

        return $param->getDefaultValue();
    }
    
    /**
     * Gets object of class referenced by user
     * 
     * @return object
     * 
     * @throws ContainerException
     */
    private function resolveReferencedParam(
        ReflectionFunctionAbstract $function,
        string $paramName,
        mixed $argument
    ): object
    {
        try {
            return $this->get($argument);
        } catch (\Throwable $e) {
            throw new ContainerException(
                sprintf(
                    "Parameter %s provided for %s points to a class which isn't available!",
                    $paramName, $this->getFunctionName($function)
                ), 0, $e
            );
        }
    }

    /**
     * Checks whether provided object fits with parameter constraints
     * 
     * @param string $paramName Name of the parameter
     * @param ReflectionFunctionAbstract $function
     * @param object $argument User-provided object
     * @param array $requiredTypes String names of types accepted by parameter
     * 
     * @return object
     * 
     * @throws ContainerException
     */
    private function resolveProvidedObject(
        string $paramName,
        ReflectionFunctionAbstract $function,
        object $argument,
        array $requiredTypes
    ): object
    {
        if (!$requiredTypes) {
            return $argument;
        }

        foreach ($requiredTypes as $type) {
            $type = '\\' . $type;
            if (((interface_exists($type) || class_exists($type)) && $argument instanceof $type)
                || $type === 'mixed'
            ) {
                return $argument;
            }
        }

        throw new ContainerException(sprintf(
            "Object provided to param %s of %s is of wrong type. Expected one of '%s', got %s!",
            $paramName, $this->getFunctionName($function), implode(', ', $requiredTypes), get_class($argument)
        ));
    }

    /**
     * Checks whether provided param fits with required types
     * 
     * @param string $paramName Name of the parameter
     * @param ReflectionFunctionAbstract $function
     * @param mixed $argument Primitive user-provided argument
     * @param array $requiredTypes String names of types accepted by parameter
     * 
     * @return mixed
     * 
     * @throws ContainerException
     */
    private function resolvePrimitiveParam(
        string $paramName,
        ReflectionFunctionAbstract $function,
        mixed $argument,
        array $requiredTypes
    ): mixed
    {
        if (!$requiredTypes) {
            return $argument;
        }

        foreach ($requiredTypes as $type) {
            if ($type === 'mixed') {
                return $argument;
            }
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
        }

        throw new ContainerException(
            sprintf(
                "Parameter %s of %s should be one of '%s', got %s.",
                $paramName, $this->getFunctionName($function),
                implode(", ", $requiredTypes), $this->getArgumentType($argument)
            )
        );
    }
    
    /**
     * Returns the name of function, or Class::MethodName for methods
     * 
     * @param ReflectionFunctionAbstract $function
     * 
     * @return string
     */
    private function getFunctionName(ReflectionFunctionAbstract $function): string
    {
        if ($function instanceof ReflectionMethod) {
            return $function->getDeclaringClass()->getName() . '::' . $function->getName();
        }

        return $function->getName();
    }

    /**
     * Returns the name of type / class of provided argument
     * 
     * @param mixed $argument
     * 
     * @return string
     */
    private function getArgumentType(mixed $argument): mixed
    {
        if (is_object($argument)) {
            return get_class($argument);
        }

        return gettype($argument);
    }

}