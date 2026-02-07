<?php

namespace Sonata\Framework\Http;

class ApiResponse
{
    public function toArray(): array
    {
        return get_object_vars($this);
    }
}