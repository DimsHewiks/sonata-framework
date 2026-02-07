<?php

namespace Sonata\Framework\Container;

interface ContainerInterface
{
    public function get(string $id);
    public function has(string $id): bool;
}