<?php

namespace Nexion\Container;

use Closure;
use Exception;
use ReflectionClass;
use ReflectionParameter;

class Container
{
    protected array $bindings = [];
    protected array $instances = [];
    protected static ?Container $instance = null;

    public function bind(string $abstract, $concrete = null, bool $shared = false): void
    {
        if ($concrete === null) {
            $concrete = $abstract;
        }

        $this->bindings[$abstract] = [
            'concrete' => $concrete,
            'shared' => $shared
        ];
    }

    public function singleton(string $abstract, $concrete = null): void
    {
        $this->bind($abstract, $concrete, true);
    }

    public function instance(string $abstract, $instance): void
    {
        $this->instances[$abstract] = $instance;
    }

    public function make(string $abstract, array $parameters = [])
    {
        if (isset($this->instances[$abstract])) {
            return $this->instances[$abstract];
        }

        $concrete = $this->getConcrete($abstract);

        if ($this->isBuildable($concrete, $abstract)) {
            $object = $this->build($concrete, $parameters);
        } else {
            $object = $this->make($concrete, $parameters);
        }

        if ($this->isShared($abstract)) {
            $this->instances[$abstract] = $object;
        }

        return $object;
    }

    protected function getConcrete(string $abstract)
    {
        if (isset($this->bindings[$abstract])) {
            return $this->bindings[$abstract]['concrete'];
        }

        return $abstract;
    }

    protected function isBuildable($concrete, string $abstract): bool
    {
        return $concrete === $abstract || $concrete instanceof Closure;
    }

    protected function isShared(string $abstract): bool
    {
        return isset($this->bindings[$abstract]['shared']) && $this->bindings[$abstract]['shared'] === true;
    }

    public function build($concrete, array $parameters = [])
    {
        if ($concrete instanceof Closure) {
            return $concrete($this, $parameters);
        }

        $reflector = new ReflectionClass($concrete);

        if (!$reflector->isInstantiable()) {
            throw new Exception("Class {$concrete} is not instantiable");
        }

        $constructor = $reflector->getConstructor();

        if (is_null($constructor)) {
            return new $concrete();
        }

        $dependencies = $constructor->getParameters();
        $instances = $this->resolveDependencies($dependencies, $parameters);

        return $reflector->newInstanceArgs($instances);
    }

    protected function resolveDependencies(array $dependencies, array $parameters): array
    {
        $results = [];

        foreach ($dependencies as $dependency) {
            if (array_key_exists($dependency->name, $parameters)) {
                $results[] = $parameters[$dependency->name];
                continue;
            }

            $results[] = $this->resolve($dependency);
        }

        return $results;
    }

    protected function resolve(ReflectionParameter $parameter)
    {
        $type = $parameter->getType();

        if (!$type || $type->isBuiltin()) {
            if ($parameter->isDefaultValueAvailable()) {
                return $parameter->getDefaultValue();
            }
            throw new Exception("Unresolvable dependency: {$parameter->name}");
        }

        return $this->make($type->getName());
    }

    public static function getInstance(): Container
    {
        if (is_null(static::$instance)) {
            static::$instance = new static();
        }

        return static::$instance;
    }

    public static function setInstance(Container $container = null): ?Container
    {
        return static::$instance = $container;
    }

    public function call($callback, array $parameters = [])
    {
        if (is_string($callback) && str_contains($callback, '@')) {
            [$class, $method] = explode('@', $callback);
            $callback = [$this->make($class), $method];
        }

        if (is_array($callback)) {
            $reflector = new \ReflectionMethod($callback[0], $callback[1]);
        } else {
            $reflector = new \ReflectionFunction($callback);
        }

        $dependencies = $reflector->getParameters();
        $instances = $this->resolveDependencies($dependencies, $parameters);

        return call_user_func_array($callback, $instances);
    }
}
