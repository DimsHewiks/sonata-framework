<?php

namespace Sonata\Framework\Container;

use Closure;
use ReflectionClass;
use ReflectionNamedType;

class Container implements ContainerInterface
{
    private array $definitions = [];
    private array $instances = [];

    public function set(string $id, callable|object|string|null $concrete = null): void
    {
        $this->definitions[$id] = $concrete ?? $id;
    }

    public function has(string $id): bool
    {
        return isset($this->definitions[$id]) || class_exists($id);
    }

    /**
     * @throws \ReflectionException
     */
    public function get(string $id)
    {
        if (isset($this->instances[$id])) {
            return $this->instances[$id];
        }

        $concrete = $this->definitions[$id] ?? $id;

        // Поддержка фабрик
        if (is_callable($concrete)) {
            $instance = $concrete($this);
            if (isset($this->definitions[$id])) {
                $this->instances[$id] = $instance;
            }
            return $instance;
        }

        if (!is_string($concrete) || !class_exists($concrete)) {
            throw new \Exception("Service or class not found: $id");
        }

        $shouldCache = isset($this->definitions[$id]);

        $reflection = new \ReflectionClass($concrete);
        if (!$reflection->isInstantiable()) {
            throw new \Exception("Cannot instantiate $concrete");
        }

        $constructor = $reflection->getConstructor();
        if ($constructor === null) {
            $instance = new $concrete();
        } else {
            $dependencies = [];
            foreach ($constructor->getParameters() as $param) {
                // Проверяем #[Inject]
                $injectAttr = null;
                foreach ($param->getAttributes(\Sonata\Framework\Attributes\Inject::class) as $attr) {
                    $injectAttr = $attr->newInstance();
                    break;
                }

                if ($injectAttr) {
                    $serviceId = $injectAttr->id ?? $param->getType()?->getName();
                    if ($serviceId) {
                        $dependencies[] = $this->get($serviceId);
                        continue;
                    }
                }

                // Старый способ (fallback)
                $type = $param->getType();
                if (!$type || !$type instanceof \ReflectionNamedType || $type->isBuiltin()) {
                    if ($param->isDefaultValueAvailable()) {
                        $dependencies[] = $param->getDefaultValue();
                    } else {
                        throw new \Exception("Cannot resolve parameter \${$param->getName()} in $concrete");
                    }
                } else {
                    $typeName = $type->getName();
                    $dependencies[] = $this->get($typeName);
                }
            }
            $instance = $reflection->newInstanceArgs($dependencies);
        }

        if ($shouldCache) {
            $this->instances[$id] = $instance;
        }

        return $instance;
    }
}