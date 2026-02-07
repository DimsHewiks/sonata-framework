<?php

namespace Sonata\Framework\Middleware;

use Sonata\Framework\Container\ContainerInterface;

class MiddlewarePipeline
{
    /**
     * @var array<int, object|string|callable>
     */
    private array $middlewares;

    public function __construct(
        private ContainerInterface $container,
        array $middlewares
    ) {
        $this->middlewares = array_values($middlewares);
    }

    public function handle(array $context, callable $destination): mixed
    {
        $next = array_reduce(
            array_reverse($this->middlewares),
            function (callable $next, $middleware): callable {
                return function (array $context) use ($middleware, $next): mixed {
                    $resolved = $this->resolveMiddleware($middleware);
                    if (is_callable($resolved)) {
                        return $resolved($context, $next);
                    }

                    return $resolved->handle($context, $next);
                };
            },
            $destination
        );

        return $next($context);
    }

    private function resolveMiddleware(object|string|callable $middleware): object|callable
    {
        if (is_object($middleware)) {
            return $middleware;
        }

        if (is_callable($middleware)) {
            return $middleware;
        }

        $resolved = $this->container->get($middleware);
        if ($resolved instanceof MiddlewareInterface || is_callable($resolved)) {
            return $resolved;
        }

        throw new \RuntimeException("Invalid middleware: {$middleware}");
    }
}
