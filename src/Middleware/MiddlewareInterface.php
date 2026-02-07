<?php

namespace Sonata\Framework\Middleware;

interface MiddlewareInterface
{
    /**
     * @param array $context
     * @param callable $next
     * @return mixed
     */
    public function handle(array $context, callable $next): mixed;
}
