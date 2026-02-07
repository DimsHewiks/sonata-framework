<?php

namespace Sonata\Framework\Service;

class ConfigService
{
    public function getJwtSecret(): string
    {
        return $_ENV['JWT_SECRET'] ?? throw new \RuntimeException('JWT_SECRET not set');
    }
}