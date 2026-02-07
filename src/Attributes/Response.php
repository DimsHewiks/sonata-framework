<?php

namespace Sonata\Framework\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_METHOD)]
class Response
{
    public function __construct(
        public ?string $class = null,
        public bool $isArray = false
    ) {}
}