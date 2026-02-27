<?php

declare(strict_types=1);

namespace Smallwork\Core;

class Container
{
    private array $bindings = [];
    private array $singletons = [];
    private array $instances = [];

    public function bind(string $abstract, callable $factory): void
    {
        $this->bindings[$abstract] = $factory;
    }

    public function singleton(string $abstract, callable $factory): void
    {
        $this->bindings[$abstract] = $factory;
        $this->singletons[$abstract] = true;
    }

    public function instance(string $abstract, mixed $instance): void
    {
        $this->instances[$abstract] = $instance;
    }

    public function has(string $abstract): bool
    {
        return isset($this->bindings[$abstract]) || isset($this->instances[$abstract]);
    }

    public function resolve(string $abstract): mixed
    {
        if (isset($this->instances[$abstract])) {
            return $this->instances[$abstract];
        }

        if (!isset($this->bindings[$abstract])) {
            throw new \RuntimeException("No binding found for '$abstract'.");
        }

        $result = ($this->bindings[$abstract])($this);

        if (isset($this->singletons[$abstract])) {
            $this->instances[$abstract] = $result;
        }

        return $result;
    }

    public function make(string $class): mixed
    {
        if ($this->has($class)) {
            return $this->resolve($class);
        }

        $reflection = new \ReflectionClass($class);
        $constructor = $reflection->getConstructor();

        if ($constructor === null) {
            return new $class();
        }

        $params = [];
        foreach ($constructor->getParameters() as $param) {
            $type = $param->getType();
            if ($type instanceof \ReflectionNamedType && !$type->isBuiltin()) {
                $params[] = $this->resolve($type->getName());
            } elseif ($param->isDefaultValueAvailable()) {
                $params[] = $param->getDefaultValue();
            } else {
                throw new \RuntimeException("Cannot autowire parameter '{$param->getName()}' of class '$class'.");
            }
        }

        return $reflection->newInstanceArgs($params);
    }
}
